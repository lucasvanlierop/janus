<?php

$this->data['jquery'] = array('version' => '1.6', 'core' => TRUE, 'ui' => TRUE, 'css' => TRUE);
$this->includeAtTemplateBase('includes/header.php');
$this->data['extended'] = true;

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
        echo '<td width="130px" align="center">';
        if ($entry['invalid_metadata']) {
            echo('<img src="images/icons/reject.png" title="' .
                 $t->t('missing_require_metadata') . implode(" ", $entry['invalid_metadata']) .
                 '" alt="' . $t->t('validation_problem') . '" />');
        } else {
            $alt = $t->t('valid');
            echo('<img src="images/icons/accept.png" title="" alt="' .
                 $t->t('validation_success') . '" />');
        }
        echo '</td>';

        // Certificate column
        if (SimpleSAML_Module::isModuleEnabled('x509')) {
            echo '<td width="130px" align="center">';
            if ($entry['invalid_certificate']) {
                $title = $t->t('{x509:certvalidator:' . $entry['invalid_certificate'] . '}');
                // error 18 is due to self signed certificates, so we display a warning instead
                if ($entry['invalid_certificate'] == 'error_found_18') {
                    echo('<img src="images/icons/warning.png" title="' .
                         $title. '" alt="' .
                         $t->t('validation_warning') . '" />');
                } else {
                    echo('<img src="images/icons/reject.png" title="' .
                         $title. '" alt="' .
                         $t->t('validation_problem') . '" />');
                }
            } else {
                echo('<img src="images/icons/accept.png" alt="' .
                     $t->t('validation_success') . '" />');
            }
            echo '</td>';
        }

        // Name column
        echo '<td>';
        if ($entry['flag'] !== null) {
            echo '<img style="display: inline; margin-right: 5px" src="' . $flag . '" alt="' . $entry['flag_name'] . '" />';
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

        if ($extended) {
            if ($entry['expired']) {
                echo(' <span style="color: #500; font-weight: bold"> (expired ' .
                     number_format($entry['expiration_time']/3600, 1) .
                     ' hours ago)</span>');
            } else if ($entry['expiration_time'] !== null) {
                echo(' <span style="color: #ccc; "> (expires in ' .
                     number_format($entry['expiration_time']/3600, 1) .
                     ' hours)</span>');
            }
        }

        echo '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';

}

if(!empty($this->data['metaentries']['saml20-idp'])) {
    echo '<h2>' . $this->t('text_saml20-idp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['saml20-idp'], $this->data['extended']);
}
if(!empty($this->data['metaentries']['shib13-idp'])) {
    echo '<h2>' . $this->t('text_shib13-idp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['shib13-idp'], $this->data['extended']);
}

if(!empty($this->data['metaentries']['saml20-sp'])) {
    echo '<h2>' . $this->t('text_saml20-sp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['saml20-sp'], $this->data['extended']);
}
if(!empty($this->data['metaentries']['shib13-sp'])) {
    echo '<h2>' . $this->t('text_shib13-sp') . '</h2>';
    listMetadata($this, $this->data['metaentries']['shib13-sp'], $this->data['extended']);
}

$this->includeAtTemplateBase('includes/footer.php');

?>