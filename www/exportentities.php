<?php
/**
 * @author Sixto Martín, <smartin@yaco.es>
 * @author Jacob Christiaansen, <jach@wayf.dk>
 */

$session = SimpleSAML_Session::getInstance();
$config = SimpleSAML_Configuration::getInstance();
$janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

$et = new SimpleSAML_XHTML_Template($config, 'janus:exportentities.php', 'janus:janus');

$et->data['export.states'] = $janus_config->getValue('export.states');

$util = new sspmod_janus_AdminUtil();

if (array_key_exists('state', $_GET)) {
    $entities = $util->getEntitiesByState($_GET['state']);
} else {
    $entities = $util->getEntities();
}

if (!array_key_exists('id', $_GET)) {
    $et->data['header'] = 'JANUS';
    if(isset($msg)) {
        $et->data['msg'] = $msg;
    }
    $et->show();
    exit();
}

try {
    $xml = new DOMDocument();
    $entitiesDescriptor = $xml->createElementNS('urn:oasis:names:tc:SAML:2.0:metadata', 'EntitiesDescriptor');
    $entitiesDescriptorName = $janus_config->getString('export.entitiesDescriptorName', 'Federation');
    $entitiesDescriptor->setAttribute('Name', $entitiesDescriptorName);
    $xml->appendChild($entitiesDescriptor);

    foreach ($entities as $entity) {
        $entityDescriptor = NULL;

        $eid = $entity['eid'];

        // Get Entity controller
        $mcontroller = new sspmod_janus_EntityController($janus_config);
        $mcontroller->setEntity($eid);
        $mcontroller->loadEntity();

        $metadata = $mcontroller->getMetadata();
        $entity_type = $mcontroller->getEntity()->getType();

        if (array_key_exists('entity_type_filter', $_GET)) {
            $entity_type_filter = $_GET['entity_type_filter'];
            if($entity_type == 'saml20-idp' &&
               $config->getBoolean('enable.saml20-idp', FALSE) &&
               !in_array($entity_type_filter, array('idp-sp-all','idp-all','saml20-idp','saml20-all'))) {
                continue ;
            }
            else if ($entity_type == 'saml20-sp' && $config->getBoolean('enable.saml20-sp', FALSE) &&
                     !in_array($entity_type_filter, array('idp-sp-all','sp-all','saml20-sp','saml20-all'))) {
                continue ;
            }
            else if ($entity_type == 'shib13-idp' && $config->getBoolean('enable.shib13-sp', FALSE) &&
                     !in_array($entity_type_filter, array('idp-sp-all','idp-all','shib13-idp','shib13-all'))) {
                continue ;
            }
            else if ($entity_type == 'shib13-sp' && $config->getBoolean('enable.shib13-idp', FALSE) &&
                     !in_array($entity_type_filter, array('idp-sp-all','sp-all','shib13-sp','shib13-all'))) {
                continue ;
            }
        } else {
            $t = new SimpleSAML_XHTML_Template($config, 'janus:error.php', 'janus:janus');
            $t->data['header'] = 'JANUS';
            $t->data['error'] = 'Missing parameter entity_type_filter';
            $t->show();
            exit();
        }

        $metadata_keys = array();
        foreach($metadata AS $k => $v) {
            $metadata_keys[] = $v->getKey();
        }

        $metaBuilder = new SimpleSAML_Metadata_SAMLBuilder($entity['entityid'], $janus_config->getValue('maxCache', NULL), $janus_config->getValue('maxDuration', NULL));

        $requiredmeta = $janus_config->getArray('required.metadatafields.'.$entity_type, array());

        $missing_required = array_diff($requiredmeta, $metadata_keys);
        if(!empty($missing_required)){
            $entity = $mcontroller->getEntity();
            $t = new SimpleSAML_XHTML_Template($config, 'janus:error.php', 'janus:janus');
            $t->data['header'] = 'JANUS';
            $t->data['error'] = 'The following metadatafields are required but not present.';
            $t->data['extra_data'] = 'Entityid: '. $entity->getEntityid() .'<br /><br />'.  implode("<br />", $missing_required);
            $t->show();
            exit();
        }

        $metaArray = $mcontroller->getMetaArray();
        $metaBuilder->addMetadata($entity_type.'-remote', $metaArray);

        if(!empty($metaArray['contact'])) {
            $metaBuilder->addContact('technical', $metaArray['contact']);
        }

        if(!empty($metaArray['organization'])) {
            $metaBuilder->addOrganizationInfo($metaArray['organization']);
        }

        $entityDescriptor = $metaBuilder->getEntityDescriptor();
        $entitiesDescriptor->appendChild($xml->importNode($entityDescriptor, TRUE));
    }

    /* Sign the metadata if enabled. */
    if ($janus_config->getBoolean('sign.enable', FALSE)) {
        $privateKey = $janus_config->getString('sign.privatekey');
        $privateKeyPass = $janus_config->getString('sign.privatekey_pass', NULL);
        $certificate = $janus_config->getString('sign.certificate');

        $signer = new SimpleSAML_XML_Signer(
            array(
                'privatekey' => $privateKey,
                'privatekey_pass' => $privateKeyPass,
                'certificate' => $certificate,
                'id' => 'ID',
                )
            );
        $signer->sign($entitiesDescriptor, $entitiesDescriptor, $entitiesDescriptor->firstChild);
    }

    /* Show the metadata. */
    if(array_key_exists('mimetype', $_GET)) {
        $mimeType = $_GET['mimetype'];
    } else {
        $mimeType = 'application/samlmetadata+xml';
    }
    header('Content-Type: ' . $mimeType);
    echo($xml->saveXML());
} catch(Exception $exception) {
    SimpleSAML_Utilities::fatalError($session->getTrackID(), 'METADATA', $exception);
}

?>
