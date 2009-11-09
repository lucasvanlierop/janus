<?php
$janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

$metaentries = array(
    'saml20-idp' => array(),
    'saml20-sp' => array(),
    'shib13-idp' => array(),
    'shib13-sp' => array(),
    );

$now = time();
$util = new sspmod_janus_AdminUtil();
foreach ($util->getEntities() as $entity) {

    $entry = array();

    $eid = $entity['eid'];

    // Get Entity controller
    $mcontroller = new sspmod_janus_EntityController($janus_config);
    $mcontroller->setEntity($eid);
    $mcontroller->loadEntity();

    // Grab some basic fields
    $metadata = $mcontroller->getMetadata();
    $entity_type = $mcontroller->getEntity()->getType();
    $entity_id = $mcontroller->getEntity()->getEntityid();

    $metadata_keys = array();
    foreach($metadata AS $k => $v) {
        $metadata_keys[] = $v->getKey();
    }

    $metaArray = $mcontroller->getMetaArray();
    $entry['entityid'] = $entity_id;

    // Check if the entity has all the required fields
    $requiredmeta = $janus_config->getArray('required.metadatafields.' . $entity_type,
                                            array());
    $missing_required = array_diff($requiredmeta, $metadata_keys);

    $entry['invalid_metadata'] = false;
    if($missing_required) {
        $entry['invalid_metadata'] = $missing_required;
    }

    // Now validate the certificate
    $entry['invalid_certificate'] = false;
    if(!isset($metaArray['certData'])) {
        $entry['invalid_certificate'] = 'cert_not_found';
    } else if (SimpleSAML_Module::isModuleEnabled('x509')) {
        $pem = trim($metaArray['certData']);
        $pem = chunk_split($pem, 64, "\r\n");
        $pem = substr($pem, 0, -1); // remove the last \n character
        $result = sspmod_x509_CertValidator::validateCert($pem);
        if ($result != 'cert_validation_success') {
            $entry['invalid_certificate'] = $result;
        }
    } else {
        $entry['invalid_certificate'] = 'x509_module_not_enabled';
    }

    // Check if this entry is rotten
    if (array_key_exists('expire', $metaArray)) {
        if ($metaArray['expire'] < $now) {
            $entry['expired'] = true;
            $entry['expiration_time'] = $now - $metaArray['expire'];
        } else {
            $entry['expired'] = false;
            $entry['expiration_time'] = $metaArray['expire'] - $now;
        }
    } else {
        $entry['expired'] = false;
        $entry['expiration_time'] = null;
    }

    // Fill in some more data
    $entry['name'] = (array_key_exists('name', $metaArray)) ? $metaArray['name'] : null;
    $entry['url'] = (array_key_exists('url', $metaArray)) ? $metaArray['url'] : null;

    // Check if we have a flag icon
    $entry['flag'] = null;
    $entry['flag_name'] = null;
    if (SimpleSAML_Module::isModuleEnabled('metalisting')
        && (array_key_exists('tags', $metaArray))) {
        $countries = array(
            'denmark' => 'dk',
            'finland' => 'fi',
            'france' => 'fr',
            'germany' => 'de',
            'norway' => 'no',
            'poland' => 'pl',
            'spain' => 'es',
            'sweden' => 'se',
            'switzerland' => 'ch',
            );
        foreach($countries as $country_name => $code) {
            if (in_array($country_name, $metaArray['tags'])) {
                $entry['flag'] = SimpleSAML_Module::getModuleURL('metalisting/flags/' . $code . '.png');
                $entry['flag_name'] = $country_name;
                break;
            }
        }
    }


    // Store the data in the result array
    if (array_key_exists($entity_type, $metaentries)) {
        array_push($metaentries[$entity_type], $entry);
    }
}

$config = SimpleSAML_Configuration::getInstance();
$t = new SimpleSAML_XHTML_Template($config, 'janus:metalisting.php', 'janus:janus.php');
$t->data['header'] = 'Federation entities';
$t->data['metaentries'] = $metaentries;
$t->show();

?>