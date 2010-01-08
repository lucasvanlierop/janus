<?php
/**
 * @author Sixto Martín, <smartin@yaco.es>
 * @author Jacob Christiaansen, <jach@wayf.dk>
 */

// Init config
$session = SimpleSAML_Session::getInstance();
$config = SimpleSAML_Configuration::getInstance();
$janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

// Get state
$export_state = null;
if (isset($_GET['state']) && $_GET['state'] != 'null') {
    $export_state = $_GET['state'];
}

// Get type filter
$export_type = null;
if(isset($_GET['type'])) {
    if(is_array($_GET['type'])) {
        $export_type = $_GET['type'];
    } else {
        $export_type = array($_GET['type']);
    }
}

// Get external
$export_external = null;
if (isset($_GET['external']) && $_GET['external'] != 'null') {
    $export_external = $_GET['external'];
}

// Create a AdminUtil object
$util = new sspmod_janus_AdminUtil();

// Show UI
if (!isset($export_state) && !isset($export_type)) {
    // Init session
    $session = SimpleSAML_Session::getInstance();
    
    // Get data from config
    $authsource = $janus_config->getValue('auth', 'login-admin');
    $useridattr = $janus_config->getValue('useridattr', 'eduPersonPrincipalName');

    // Only valid users are allowed to se UI
    if ($session->isValid($authsource)) {
        $attributes = $session->getAttributes();
        // Check if userid exists
        if (!isset($attributes[$useridattr]))
            throw new Exception('User ID is missing');
        $userid = $attributes[$useridattr][0];
    } else {
        SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/index.php'));
    }
    
    // Get the user
    $user = new sspmod_janus_User($janus_config->getValue('store'));
    $user->setUserid($userid);
    $user->load(sspmod_janus_User::USERID_LOAD);
    
    $et = new SimpleSAML_XHTML_Template($config, 'janus:exportentities.php', 'janus:janus');

    $et->data['user_type'] = $user->getType();
    $et->data['uiguard'] = new sspmod_janus_UIguard($janus_config->getValue('access'));
    $et->data['types'] = $util->getAllowedTypes();
    $et->data['states'] = $janus_config->getArray('workflowstates');
    $et->data['external'] = $janus_config->getArray('export.external');
    $et->data['header'] = 'JANUS';
    if(isset($_GET['msg']))
    {
        $et->data['msg'] = $_GET['msg'];
    }
    $et->show();
    exit();
}

// Generate metadata
try {
    $entities = $util->getEntitiesByStateType($export_state, $export_type);

    $xml = new DOMDocument();
    $entitiesDescriptor = $xml->createElementNS('urn:oasis:names:tc:SAML:2.0:metadata', 'EntitiesDescriptor');
    $entitiesDescriptorName = $janus_config->getString('export.entitiesDescriptorName', 'Federation');
    $entitiesDescriptor->setAttribute('Name', $entitiesDescriptorName);

    if($maxCache !== NULL) {
        $entitiesDescriptor->setAttribute('cacheDuration', 'PT' . $maxCache . 'S');
    }

    if($maxDuration !== NULL) {
        $entitiesDescriptor->setAttribute('validUntil', SimpleSAML_Utilities::generateTimestamp(time() + $maxDuration));
    }

    $xml->appendChild($entitiesDescriptor);

    foreach ($entities as $entity) {
        $entityDescriptor = sspmod_janus_MetaExport::getXMLMetadata(
            $entity['eid'], 
            $entity['revisionid'], 
            array(
                'maxCache' => $maxCache, 
                'maxDuration' => $maxDuration
            )
        );

        if(empty($entityDescriptor)) {
            $t = new SimpleSAML_XHTML_Template($config, 'janus:error.php', 'janus:janus');
            if(sspmod_janus_MetaExport::getError() == 'missing_required') {
                $t->data['header'] = 'Required metadatafields are missing';
                $t->data['error'] = 'The following metadatafields are required but not present in ' . $entity['entityid'];
                $t->data['extra_data'] = implode("\n", sspmod_janus_MetaExport::getExtraDataError());
            }
            else if(sspmod_janus_MetaExport::getError() == 'metadata_expired') {
                $t->data['header'] = 'Metadata expired';
                $t->data['error'] = 'Have expired the metadata of the entity ' . $entity['entityid'];
            }
            else if(sspmod_janus_MetaExport::getError() == 'invalid_certificate') {
                $t->data['header'] = 'Invalid certificate';
                $t->data['error'] = 'Invalid certificate of the entity ' . $entity['entityid'];
                $t->data['extra_data'] = sspmod_janus_MetaExport::getExtraDataError();
            }
            else {
                $t->data['header'] = 'Unknown error';
                $t->data['error'] = 'Unknown error when exporting the entity ' . $entity['entityid'];
            }
            $t->show();
            exit(0);
        }

        $entitiesDescriptor->appendChild($xml->importNode($entityDescriptor, TRUE));
    }

    /* Sign the metadata if enabled. */
    if ($janus_config->getBoolean('sign.enable', FALSE)) {
        $signer = new SimpleSAML_XML_Signer(
            array(
                'privatekey' => $janus_config->getString('sign.privatekey'),
                'privatekey_pass' => $janus_config->getString('sign.privatekey_pass', NULL),
                'certificate' => $janus_config->getString('sign.certificate'),
                'id' => 'ID',
            )
        );
        $signer->sign($entitiesDescriptor, $entitiesDescriptor, $entitiesDescriptor->firstChild);
    }

    if(isset($export_external))
    {
        $externalconfig = $janus_config->getArray('export.external');
        if(array_key_exists($export_external, $externalconfig))
        {
            $externalconfig = $externalconfig[$export_external];
            try {
                $exporter = sspmod_janus_Exporter::getInstance($externalconfig['class'], $externalconfig['option']);
                $exporter->export($xml->saveXML());
                header('Location: ' . SimpleSAML_Utilities::selfURLNoQuery() . '?msg=externalexportok');
            }
            catch(Exception $e) {
                SimpleSAML_Utilities::fatalError($session->getTrackID(), 'Can not export metadata externally', $e);
            }
        }
    }

    /* Show the metadata. */
    if(array_key_exists('mimetype', $_GET)) {
        $mimeType = $_GET['mimetype'];
        if($mimeType == 'text/plain') {
            header('Content-Disposition: attachment; filename="federation.txt"');
        }
        else {
            header('Content-Disposition: attachment; filename="federation.xml"');
        }
    } else {
        $mimeType = 'application/samlmetadata+xml';
        header('Content-Disposition: attachment; filename="federation.xml"');
    }
    header('Content-Type: ' . $mimeType);
    echo($xml->saveXML());
} catch(Exception $exception) {
    SimpleSAML_Utilities::fatalError($session->getTrackID(), 'METADATA', $exception);
}
?>
