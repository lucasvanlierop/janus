<?php
/**
 * Cron hook for JANUS
 *
 * PHP version 5
 *
 * JANUS is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * JANUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with JANUS. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category   SimpleSAMLphp
 * @package    JANUS
 * @subpackage Hooks
 * @author     Sixto Martín <smartin@yaco.es>
 * @author     Lorenzo Gil <lgs@yaco.es>
 * @copyright  2009 Yaco Sistemas
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version    SVN: $Id$
 * @link       http://code.google.com/p/janus-ssp/
 * @since      File available since Release 1.4.0
 */
/**
 * Cron hook for JANUS
 *
 * This hook downloads the metadata of the entities registered in JANUS and
 * update the entities with the new metadata.
 *
 * @param array &$croninfo The array with the tags and output summary of the cron run
 *
 * @return void
 *
 * @since Function available since Release 1.4.0
 */
function janus_hook_cron(&$croninfo) {
    assert('is_array($croninfo)');
    assert('array_key_exists("summary", $croninfo)');
    assert('array_key_exists("tag", $croninfo)');

    SimpleSAML_Logger::info('cron [janus]: Running cron in cron tag [' . $croninfo['tag'] . '] ');

    try {
        $janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

        if(SimpleSAML_Module::isModuleEnabled('x509')) {
            $janus_admin_mail = $janus_config->getValue('admin.email', NULL);
            $notify_expiring_cert_before = $janus_config->getInteger('notify.cert.expiring.before', 30);
        }

        $cron_tags = $janus_config->getArray('cron', array());
        $croninfo['summary'] = array();

        if (!in_array($croninfo['tag'], $cron_tags)) {
            return; // Nothing to do: it's not our time
        }

        $util = new sspmod_janus_AdminUtil();
        $entities = $util->getEntities();

        foreach ($entities as $partial_entity) {
            $mcontroller = new sspmod_janus_EntityController($janus_config);

            $eid = $partial_entity['eid'];
            if(!$mcontroller->setEntity($eid)) {
                $croninfo['summary'][] = 'Error during janus cron: failed import entity. Wrong eid. ' . $eid;
                continue;
            }

            $updated = false;

            $mcontroller->loadEntity();
            $entity = $mcontroller->getEntity();
            $entity_id = $entity->getEntityId();
            $metadata_url = $entity->getMetadataURL();

            if (empty($metadata_url)) {
                SimpleSAML_Logger::debug(
                    'janus_hook_cron - Metadata URL is empty'
                );
                continue;
            }

            $xml = file_get_contents($metadata_url);
	    $filename = '/var/www/metadata/simplesamlphp/log/' . $entity->getEid() . date("-Y-m-d-H-i") . '.xml';
	    file_put_contents($filename, $xml);
            if (!$xml) {
                $croninfo['summary'][] = 'Error during janus cron: failed import entity. Bad URL. ' . $entity_id;
                continue;
            }

            if($entity->getType() == 'saml20-sp') {
                if($mcontroller->importMetadata20SP($xml) === 'status_metadata_parsed_ok') {
                    $updated = true;
                }
                else {
                    $croninfo['summary'][] = '<p>Entity: ' . $entity_id . ' not updated</p>';
                    $updated = false;
                }
            } else if($entity->getType() == 'saml20-idp') {
                if($mcontroller->importMetadata20IdP($xml) === 'status_metadata_parsed_ok') {
                    $updated = true;
                }
                else {
                    $croninfo['summary'][] = '<p>Entity: '. $entity_id . ' not updated</p>';
                    $updated = false;
                }
            }
            else {
                $croninfo['summary'][] = '<p>Error during janus cron: failed import entity ' . $entity_id . '. Wrong type</p>';
                $updated = false;
            }

            if ($updated) {
                $entity->setParent($entity->getRevisionid());
                $mcontroller->saveEntity();
                $croninfo['summary'][] = '<p>Entity: ' . $entity_id . ' updated</p>';
            }
            
            // Send mail to administrator if certificate will be expired soon
            if(SimpleSAML_Module::isModuleEnabled('x509')) {
                $metaArray = $mcontroller->getMetaArray();
                if(isset($metaArray['certData'])) {
                    $days_to_expire = sspmod_x509_CertValidator::getDaysUntilExpiration($metaArray['certData']);
                    if($days_to_expire < $notify_expiring_cert_before) {
                        $toaddress = NULL;
                        if(isset($metaArray['contacts'])) {
                            $contact = array_shift($metaArray['contacts']);
                            if(isset($contact['emailAddress'])) {
                                $toaddress = $contact['emailAddress'];
                            }
                            else {
                                $toaddress = $janus_admin_mail;
                            }
                        }
                        if($toaddress != NULL) {
                            if($days_to_expire == 0) {
                                $message = 'Certificate of the entity_id = "'.$entity_id.'" expired';
                            }
                            else {
                                $message = 'Certificate of the entity_id = "'.$entity_id.'" expire in '.$days_to_expire.' days';
                            }
                            $email = new SimpleSAML_XHTML_EMail($toaddress, $message, 'no-reply@simplesamlphp.com', $janus_admin_mail);
                            $email->setBody($message);
                            $email->send();
                        }
                    }
                }
            }
            
        }

    } catch (Exception $e) {
        $croninfo['summary'][] = 'Error during janus sync metadata: ' . $e->getMessage();
    }
}
?>
