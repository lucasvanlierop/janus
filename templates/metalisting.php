<?php

$this->data['jquery'] = array('version' => '1.6', 'core' => TRUE, 'ui' => TRUE, 'css' => TRUE);
$this->data['head']  = '<link rel="stylesheet" type="text/css" href="/' . $this->data['baseurlpath'] . 'module.php/janus/resources/style.css" />' . "\n";
$this->includeAtTemplateBase('includes/header.php');

 echo '<div id="tabdiv">';
 echo '<a href="'.SimpleSAML_Module::getModuleURL('janus/index.php').'">'.$this->t('text_dashboard').'</a>';

function listMetadata($t, $entries, $extended = FALSE) {
    echo '<table width="100%">';
    echo '<thead><tr>';
    echo '<th width="130px" align="center">' . $t->t('validation_metadata_column') . '</th>';
    if (SimpleSAML_Module::isModuleEnabled('x509')) {
        echo '<th width="130px" align="center">' . $t->t('validation_certificate_column') . '</th>';
    }
    echo '<th>' . $t->t('validation_identity_column') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    foreach($entries AS $entry) {
        echo '<tr>';

        // Metadata column

        echo '<td width="250px" align="center">';
        if ($entry['invalid_metadata']) {
            echo('<img class="display_inline" src="resources/images/icons/reject.png" title="' .
                 $t->t('missing_require_metadata') . implode(" ", $entry['invalid_metadata']) .
                 '" alt="' . $t->t('validation_problem') . '" />');
        }
        else {
            echo('<img class="display_inline" src="resources/images/icons/accept.png" title="ok" alt="' .
                 $t->t('validation_success') . '" />');
        }
        
        if ($entry['meta_status'] == 'expired') {
            echo('<img class="display_inline" src="resources/images/icons/expired.png" title="' .$t->t('hour_expired', 
                array('%META_EXPIRED_TIME%' => number_format($entry['meta_expiration_time'], 1))).'" alt="'.$t->t('expired').'">');
        }
        else if ($entry['meta_status'] == 'expires soon') {
            echo('<img class="display_inline" src="resources/images/icons/almost_expired.png" title="' .$t->t('hour_expires', 
                array('%META_EXPIRES_TIME%' => number_format($entry['meta_expiration_time'], 1))).'" alt="'.$t->t('no_expired').'">');
        }
        else if ($entry['meta_status'] == 'expires'){
            echo('<img class="display_inline" src="resources/images/icons/fresh.png" title="' .$t->t('hour_expires', 
                array('%META_EXPIRES_TIME%' => number_format($entry['meta_expiration_time'], 1))).'" alt="'.$t->t('no_expired').'">');
        }

        echo '</td>';

        // Certificate column
        if (SimpleSAML_Module::isModuleEnabled('x509')) {
            echo '<td width="250px" align="center">';
            if ($entry['invalid_certificate']) {
                $title = $t->t('{x509:x509:' . $entry['invalid_certificate'] . '}');
                // if in strict certificate validation and validation error response in 
                // allowed_warnings we display a warning instead of reject
                if ($entry['cert_validation'] == 'poor' || $entry['cert_validation'] == 'unknown') {
                    echo('<img class="display_inline" src="resources/images/icons/warning.png" title="' .
                         $title. '" alt="' .
                         $t->t('validation_warning') . '" />');
                } else {
                    echo('<img class="display_inline" src="resources/images/icons/reject.png" title="' .
                         $title. '" alt="' .
                         $t->t('validation_problem') . '" />');
                }
            } else {
                echo('<img class="display_inline" src="resources/images/icons/accept.png" title="ok" alt="' .
                     $t->t('validation_success') . '" />');
            }
            
            if ($entry['cert_status'] == 'expired') {
                echo('<img class="display_inline" src="resources/images/icons/expired.png" title="' .
                    $t->t('expired').'" alt="'.$t->t('expired').'">');
            }
            else if ($entry['cert_status'] == 'expires soon') {
                echo('<img class="display_inline" src="resources/images/icons/almost_expired.png" title="' .$t->t('day_expires', 
                    array('%CERT_EXPIRES_TIME%' => number_format($entry['cert_expiration_date'], 1))).'" alt="'.$t->t('no_expired').'">');
            }            
            else if ($entry['cert_status'] == 'expires'){
                echo('<img class="display_inline" src="resources/images/icons/fresh.png" title="' .$t->t('day_expires', 
                    array('%CERT_EXPIRES_TIME%' => number_format($entry['cert_expiration_date'], 1))).'" alt="'.$t->t('no_expired').'">');
            }

                
                echo '</td>';
        }

        // Name column
        echo '<td>';
        if ($entry['flag'] !== null) {
            echo '<img class="metalisting_flag" src="' . $flag . '" alt="' . $entry['flag_name'] . '" />';
        }

        if ($entry['name'] !== null) {
            echo $t->getTranslation(SimpleSAML_Utilities::arrayize($entry['name'], 'en'));
        } else {
            echo $entry['entityid'];
        }

        if ($entry['url'] !== null) {
            echo(' [ <a href="' .
                 $t->getTranslation(SimpleSAML_Utilities::arrayize($entry['url'], 'en')) .
                '">more</a> ]');
        }

        echo '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';

}

if(!empty($this->data['metaentries']['saml20-idp'])) {
    echo '<h2>' . $this->t('text_saml20-idp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['saml20-idp']);
}
if(!empty($this->data['metaentries']['shib13-idp'])) {
    echo '<h2>' . $this->t('text_shib13-idp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['shib13-idp']);
}

if(!empty($this->data['metaentries']['saml20-sp'])) {
    echo '<h2>' . $this->t('text_saml20-sp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['saml20-sp']);
}
if(!empty($this->data['metaentries']['shib13-sp'])) {
    echo '<h2>' . $this->t('text_shib13-sp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['shib13-sp']);
}


echo '</div>';

$this->includeAtTemplateBase('includes/footer.php');

?>
