<?php
/*
 * Generate metadata
 *
 * @author Jacob Christiansen, <jach@wayf.dk>
 * @package SimpleSAMLphp
 * @subpackeage JANUS
 * @version $Id$
 */
class sspmod_janus_MetaExport
{
    const FLATFILE = '__FLAT_FILE_METADATA__';
    
    const XML = '__XML_METADATA__';
    
    const XMLREADABLE = '__XML_READABLE_METADATA__';

    private static $_error;
    private static $_extra_data_error;

    public static function getError()
    {
        return self::$_error;
    }
    
    public static function getExtraDataError()
    {
        return self::$_extra_data_error;
    }

    public static function getFlatMetadata($eid, $revision, array $option = null)
    {   
        return self::getMetadata($eid, $revision, self::FLATFILE, $option);
    }
    
    public static function getXMLMetadata($eid, $revision, array $option = null)
    {   
        return self::getMetadata($eid, $revision, self::XML, $option);
    }

    public static function getReadableXMLMetadata($eid, $revision, array $option = null)
    {   
        return self::getMetadata($eid, $revision, self::XMLREADABLE, $option);
    }

    private static function getMetadata($eid, $revision, $type = null, array $option = null)
    {
        assert('ctype_digit($eid)');
        assert('ctype_digit($revision)');

        $janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');
        $econtroller = new sspmod_janus_EntityController($janus_config);
        
        if (SimpleSAML_Module::isModuleEnabled('x509')) {
            $strict_cert_validation = $janus_config->getBoolean('cert.strict.validation',true);
            $cert_allowed_warnings = $janus_config->getArray('cert.allowed.warnings',array());
        }

        if(!$entity = $econtroller->setEntity($eid, $revision)) {
            return false;
        }

        $entityid = $entity->getEntityid();

        $metadata_raw = $econtroller->getMetadata();

        $metadata_alowed = $janus_config->getArray('metadatafields.' . $entity->getType(), array());
        $metadata_required = array();

        foreach($metadata_alowed AS $k => $v) {
            if(array_key_exists('required', $v) && $v['required'] === true) {
                $metadata_required[] = $k;
            }
        }

        $metadata = array();
        foreach($metadata_raw AS $k => $v) {
            $metadata[] = $v->getKey();
        }
        
        $missing_required = array_diff($metadata_required, $metadata);
        
        if (empty($missing_required)) {
            try {
                $metaArray = $econtroller->getMetaArray();

                if (isset($metaArray['expire']) && $metaArray['expire'] < time()) {
                    SimpleSAML_Logger::info('JANUS - Metadata of the entity '.$entityid.' expired ');
                    self::$_error = 'metadata_expired';
                    return false;
                }
                
                if (SimpleSAML_Module::isModuleEnabled('x509') && isset($metaArray['certData'])) {
                    $pem = trim($metaArray['certData']);
                    $pem = chunk_split($pem, 64, "\r\n");
                    $pem = substr($pem, 0, -1); // remove the last \n character
                    $result = sspmod_x509_CertValidator::validateCert($pem, true);
                    if ($result != 'cert_validation_success') {
                        if($strict_cert_validation || !in_array($result, $cert_allowed_warnings)) {
                            SimpleSAML_Logger::info('JANUS - Invalid certificate of the entity '.$entityid);
                            self::$_error = 'invalid_certificate';
                            self::$_extra_data_error = $result;
                            return false;
                        }
                    }
                }
                
                $blocked_entities = $econtroller->getBlockedEntities();
                $disable_consent = $econtroller->getDisableConsent();

                $metaflat = '// Revision: '. $entity->getRevisionid() ."\n";
                $metaflat .= var_export($entityid, TRUE) . ' => ' . var_export($metaArray, TRUE) . ',';

                // Add authproc filter to block blocked entities
                if(!empty($blocked_entities)) {
                    $metaflat = substr($metaflat, 0, -2);
                    $metaflat .= "  'authproc' => array(\n";
                    $metaflat .= "    10 => array(\n";
                    $metaflat .= "      'class' => 'janus:AccessBlocker',\n";
                    $metaflat .= "      'blocked' => array(\n";
                    foreach($blocked_entities AS $bentity => $value) {
                        $metaflat .= "        '". $bentity ."',\n";
                    }
                    $metaflat .= "      ),\n";
                    $metaflat .= "    ),\n";
                    $metaflat .= "  ),\n";
                    $metaflat .= '),';
                }

                // Add disable consent
                if(!empty($disable_consent)) {
                    $metaflat = substr($metaflat, 0, -2);
                    $metaflat .= "  'consent.disable' => array(\n";

                    foreach($disable_consent AS $key => $value) {
                        $metaflat .= "    '". $key ."',\n";
                    }

                    $metaflat .= "  ),\n";
                    $metaflat .= '),';
                }

                $maxCache = isset($option['maxCache']) ? $option['maxCache'] : null;
                $maxDuration = isset($option['maxDuration']) ? $option['maxDuration'] : null;
                

                $metaBuilder = new SimpleSAML_Metadata_SAMLBuilder($entityid, $maxCache, $maxDuration);
                $metaBuilder->addMetadata($metaArray['metadata-set'], $metaArray);

                // Add organization info
                if(!empty($metaArray['organization'])) {
                    $metaBuilder->addOrganizationInfo($metaArray['organization']);
                }

                // Add contact info
                if(!empty($metaArray['contact'])) {
                    $metaBuilder->addContact('technical', $metaArray['contact']);
                }

                switch($type) {
                    case self::XML:
                        return $metaBuilder->getEntityDescriptor();
                    case self::XMLREADABLE:
                        return $metaBuilder->getEntityDescriptorText();
                    case self::FLATFILE:
                    default:
                        return $metaflat;
                }
            } catch(Exception $exception) {
                $session = SimpleSAML_Session::getInstance();
                SimpleSAML_Utilities::fatalError($session->getTrackID(), 'JANUS - Metadatageneration', $exception);
            }
        }  else {
            SimpleSAML_Logger::info('JANUS - Missing required metadata fields in '.$entityid);
            self::$_error = 'missing_required';
            self::$_extra_data_error = $missing_required;
            return false;
        }
    }
}
