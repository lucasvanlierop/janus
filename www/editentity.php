<?php
/**
 * @author Jacob Christiansen, <jach@wayf.dk>
 * @author Sixto Martín, <smartin@yaco.es>
 */
error_reporting(E_ALL);
// Initial import
$session = SimpleSAML_Session::getInstance();
$config = SimpleSAML_Configuration::getInstance();
$janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

// Get data from config
$authsource = $janus_config->getValue('auth', 'login-admin');
$useridattr = $janus_config->getValue('useridattr', 'eduPersonPrincipalName');
$workflow = $janus_config->getValue('workflow_states');

// Validate user
if ($session->isValid($authsource)) {
    $attributes = $session->getAttributes();
    // Check if userid exists
    if (!isset($attributes[$useridattr]))
        throw new Exception('User ID is missing');
    $userid = $attributes[$useridattr][0];
} else {
    SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/index.php'));
}

// Get metadata to present remote entitites
$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
// Get Entity controller
$mcontroller = new sspmod_janus_EntityController($janus_config);

// Get the user
$user = new sspmod_janus_User($janus_config->getValue('store'));
$user->setUserid($userid);
$user->load(sspmod_janus_User::USERID_LOAD);

// Get correct revision
$revisionid = -1;
if(isset($_GET['revisionid'])) {
    $revisionid = $_GET['revisionid'];
}

// Get the correct entity
if(!empty($_POST)) {
    $eid = $_POST['eid'];
    $revisionid = $_POST['revisionid'];
} else {
    $eid = $_GET['eid'];
}

if($revisionid > -1) {
    if(!$entity = $mcontroller->setEntity($eid, $revisionid)) {
        die('Error in setEntity');
    }
} else {
    // Revision not set, get latest
    if(!$entity = $mcontroller->setEntity($eid)) {
        die('Error in setEntity');
    }
}
// load entity
$mcontroller->loadEntity();

// Check if user is allowed to se entity
$allowedUsers = $mcontroller->getUsers();
if(!array_key_exists($userid, $allowedUsers)) {
    SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/index.php'));
}

// Init template object
$et = new SimpleSAML_XHTML_Template($config, 'janus:editentity.php', 'janus:janus');

// Retrive current language
$language = $et->getLanguage();

$update = FALSE;
$note = '';

if(!empty($_POST)) {
    // Change entity type
    if($entity->setType($_POST['entity_type'])) {
        $update = TRUE;
        $note .= 'Changed entity type: ' . $_POST['entity_type'] . '<br />';
    }

    // Delete attribute
    if(isset($_POST['delete-attribute'])) {
        foreach($_POST['delete-attribute'] AS $data) {
            if($mcontroller->removeAttribute($data)) {
                $update = TRUE;
                $note .= 'Attribute deleted: ' . $data . '<br />';
            }
        }
    }

    // Attribute
    if(!empty($_POST['attr_value'])) {
        foreach($_POST['attr_value'] AS $k => $v) {
            if($mcontroller->addAttribute($k, $v)) {
                $update = TRUE;
                $note .= 'Attribute added: ' . $k . ' => ' . $v . '<br />';
            }
        }
    }

    // Metadata
    if(!empty($_POST['meta_value'])) {
        foreach($_POST['meta_value'] AS $k => $v) {
            // If field is boolean
            if(substr($k, -4) == 'TRUE') {
                $k = substr($k, 0, -5);
            } else if(substr($k, -5) == 'FALSE') {
                $k = substr($k, 0, -6);
            }
            if($mcontroller->addMetadata($k, $v)) {
                $update = TRUE;
                $note .= 'Metadata added: ' . $k . ' => ' . $v . '<br />';
            }
        }
    }

    // Update metadata and attributes
    foreach($_POST AS $key => $value) {
        //Metadata
        if(substr($key, 0, 14) == 'edit-metadata-') {
            if(!is_array($value)) {
                $newkey = substr($key, 14, strlen($key));

                // If field is boolean
                if(substr($newkey, -4) == 'TRUE') {
                    $newkey = substr($newkey, 0, -5);
                    $value = 'true';
                } else if(substr($newkey, -5) == 'FALSE') {
                    $newkey = substr($newkey, 0, -6);
                    $value = 'false';
                }

                if($mcontroller->updateMetadata($newkey, $value)) {
                    $update = TRUE;
                    $note .= 'Metadata edited: ' . $newkey . ' => ' . $value . '<br />';
                }
            }
        // Attributes
        } else if(substr($key, 0, 15) == 'edit-attribute-') {
            if(!empty($value) && !is_array($value)) {
                $newkey = substr($key, 15, strlen($key));
                if($mcontroller->updateAttribute($newkey, $value)) {
                    $update = TRUE;
                    $note .= 'Attribute edited: ' . $newkey . ' => ' . $value . '<br />';
                }
            }
        }
    }

    // Delete metadata
    if(isset($_POST['delete-metadata'])) {
        foreach($_POST['delete-metadata'] AS $data) {
            if($mcontroller->removeMetadata($data)) {
                $update = TRUE;
                $note .= 'Metadata deleted: ' . $data . '<br />';
            }
        }
    }

    // Add metadata from a URL.
    // NOTE. This will overwrite everything paster to the XML field
    if(isset($_POST['add_metadata_from_url'])) {
        if(!empty($_POST['meta_url'])) {
            try {
                $res = @file_get_contents($_POST['meta_url']);
                if($res) {
                    $_POST['meta_xml'] = $res;
                } else {
                    $msg = 'error_import_metadata_url';
                }
            } catch(Exception $e) {
                SimpleSAML_Logger::warning('Janus: Failed to retrieve metadata. ' . $e->getMessage());
            }
        }
    }

    // Add metadata from pasted XML
    if(!empty($_POST['meta_xml'])) {
        if($entity->getType() == 'saml20-sp') {
            if($msg = $mcontroller->importMetadata20SP($_POST['meta_xml'])) {
                $update = TRUE;
                $note .= 'Imported SAML 2.0 SP metadata: ' . $_POST['meta_xml'] . '<br />';
            }
        } else if($entity->getType() == 'saml20-idp') {
            if($msg = $mcontroller->importMetadata20IdP($_POST['meta_xml'])) {
                $update = TRUE;
                $note .= 'Imported SAML 2.0 IdP metadata: ' . $_POST['meta_xml'] . '<br />';
            }
        } else {
            die('Type error');
        }
    }

    // Disable consent
    if(isset($_POST['add-consent'])) {
        $mcontroller->clearConsent();
        foreach($_POST['add-consent'] AS $key) {
            if($mcontroller->addDisableConsent($key)) {
                $update = TRUE;
                $note .= 'Consent disabled for: ' . $key . '<br />';
            }
        }
    }

    // Remote entities
    if(isset($_POST['add'])) {
        $mcontroller->setAllowedAll('yes');
        $mcontroller->setAllowedAll('no');
        foreach($_POST['add'] AS $key) {
            if($mcontroller->addBlockedEntity($key)) {
                $update = TRUE;
                $note .= 'Remote entity added: ' . $key . '<br />';
            }
        }
    }

    // Allowedal
    if(isset($_POST['allowedall'])) {
        if($mcontroller->setAllowedAll('yes')) {
            $update = TRUE;
            $note .= 'Set allow all remote entities<br />';
        }
    } else {
        if($mcontroller->setAllowedAll('no')) {
            $update = TRUE;
            $note .= 'Removed set allow all remote entities<br />';
        }
    }

    // Change workflow
    if(isset($_POST['entity_workflow'])) {
        if($entity->setWorkflow($_POST['entity_workflow'])) {
            $update = TRUE;
            $note .= 'Changed workflow: ' . $_POST['entity_workflow'] . '<br />';
        }
    }

    // Set parent revision
    $entity->setParent($entity->getRevisionid());

    $norevision = array(
        'da' => 'Ingen revisionsnote',
        'en' => 'No revision note',
    );

    // Set revision note
    if(empty($_POST['revisionnote'])) {
        if (array_key_exists($language, $norevision)) {
            $entity->setRevisionnote($norevision[$language]);
        } else {
            $entity->setRevisionnote($norevision['en']);
        }
    } else {
        $entity->setRevisionnote($_POST['revisionnote']);
    }

    // Update entity if updated
    if($update) {
        $mcontroller->saveEntity();
        $pm = new sspmod_janus_Postman();
        $pm->post('Entity updated - ' . $entity->getEntityid(), $entity->getRevisionnote() . '<br />' . $note, 'ENTITYUPDATE-'.$entity->getEid(), $user->getUid());
    }
}

// Get remote entities
if($entity->getType() == 'saml20-sp') {
    $remote_entities = $metadata->getList('saml20-idp-remote');
    $remote_entities = array_merge($metadata->getList('shib13-idp-remote'), $remote_entities);
    $et->data['metadata_fields'] = $janus_config->getValue('metadatafields.saml20-sp');
} else if($entity->getType() == 'saml20-idp') {
    $remote_entities = $metadata->getList('saml20-sp-remote');
    $remote_entities = array_merge($metadata->getList('shib13-sp-remote'), $remote_entities);
    $et->data['metadata_fields'] = $janus_config->getValue('metadatafields.saml20-idp');
} else if($entity->getType() == 'shib13-sp') {
    $remote_entities = $metadata->getList('saml20-idp-remote');
    $remote_entities = array_merge($metadata->getList('shib13-idp-remote'), $remote_entities);
    $et->data['metadata_fields'] = $janus_config->getValue('metadatafields.saml20-sp');
} else if($entity->getType() == 'shib13-idp') {
    $remote_entities = $metadata->getList('saml20-sp-remote');
    $remote_entities = array_merge($metadata->getList('shib13-sp-remote'), $remote_entities);
    $et->data['metadata_fields'] = $janus_config->getValue('metadatafields.saml20-idp');
}

// Only parse name and description in current language
foreach($remote_entities AS $key => $value) {
    if(isset($value['name'])) {
        if(is_array($value['name'])) {
            if(array_key_exists($language, $value['name'])) {
                $value['name'] = $value['name'][$language];
            } else {
                $value['name'] = $value['name'][0];
            }
        }
    } else {
        $value['name'] = 'No name given';
    }
    if(isset($value['description'])) {
        if(is_array($value['description'])) {
            if(array_key_exists($language, $value['description'])) {
                $value['description'] = $value['description'][$language];
            } else {
                $value['description'] = $value['description'][0];
            }
        }
    } else {
        $value['description'] = 'No description given';
    }
    $remote_entities[$key] = $value;
}

// Sorting functions
function cmp($a, $b) {
    if ($a['order'] == $b['order']) {
        return 0;
    }
    return ($a['order'] < $b['order']) ? -1 : 1;
}

function cmp2($a, $b) {
    global $et;
    $aorder = $et->data['metadata_fields'][$a->getKey()]['order'];
    $border = $et->data['metadata_fields'][$b->getKey()]['order'];
    if ($aorder == $border) {
        return 0;
    }
    return ($aorder < $border) ? -1 : 1;
}

// Sort metadatafields according to order
uasort($et->data['metadata_fields'], 'cmp');

$et->data['metadata'] = $mcontroller->getMetadata();

// Sort metadata according to order
uasort($et->data['metadata'], 'cmp2');

// Get allowed workflows
$allowed_workflow = array();
$allowed_workflow[] = $entity->getWorkflow();
foreach($workflow[$entity->getWorkflow()] AS $k_wf => $v_wf) {
    if(in_array($user->getType(), $v_wf['role']) || in_array('all', $v_wf['role'])) {
        $allowed_workflow[] = $k_wf;
    }
}

$et->data['attribute_fields'] = $janus_config->getValue('attributes.'. $entity->getType());
$et->data['entity_state'] = $entity->getWorkflow();
$et->data['entity_type'] = $entity->getType();
$et->data['revisionid'] = $entity->getRevisionid();
$et->data['types'] = $janus_config->getValue('types');
$et->data['workflowstates'] = $janus_config->getValue('workflowstates');
$et->data['access'] = $janus_config->getValue('access');
$et->data['workflow'] = $allowed_workflow;
$et->data['entity'] = $entity;
$et->data['user'] = $user;
$et->data['uiguard'] = new sspmod_janus_UIguard($janus_config->getValue('access'));
$et->data['mcontroller'] = $mcontroller;
$et->data['blocked_entities'] = $mcontroller->getBlockedEntities();
$et->data['disable_consent'] = $mcontroller->getDisableConsent();
$et->data['remote_entities'] = $remote_entities;

$et->data['header'] = 'JANUS';
if(isset($msg)) {
    $et->data['msg'] = $msg;
}

$et->show();
?>
