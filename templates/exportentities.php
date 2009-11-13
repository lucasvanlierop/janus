<?php
/**
 * Main template for JANUS.
 *
 * @author Sixto Martín, <smartin@yaco.es>
 * @author Jacob Christiansen, <jach@wayf.dk>
 * @package simpleSAMLphp
 * @subpackage JANUS
 * @version $Id: janus-main.php 11 2009-03-27 13:51:02Z jach@wayf.dk $
 */
$this->data['jquery'] = array('version' => '1.6', 'core' => TRUE, 'ui' => TRUE, 'css' => TRUE);
$this->data['head']  = '<link rel="stylesheet" type="text/css" href="/' . $this->data['baseurlpath'] . 'module.php/metaedit/resources/style.css" />' . "\n";
$this->includeAtTemplateBase('includes/header.php');
?>

<div id="tabdiv">
<a href="<?php echo SimpleSAML_Module::getModuleURL('janus/index.php'); ?>"><?php echo $this->t('text_dashboard'); ?></a>
<h2><?php echo $this->t('tab_entities_federation_entity_subheader'); ?></h2>

<?php echo '<p>'.$this->t('text_export_federation_desc').'</p>';?>
<ul>
    <li>
        <a href="?id=federation&entity_type_filter=idp-sp-all"><?php echo $this->t('text_idp&sp-all'); ?></a>&nbsp;
        <a href="?id=federation&entity_type_filter=idp-sp-all&mimetype=application/xml">[xml]</a>&nbsp;
        <a href="?id=federation&entity_type_filter=idp-sp-all&mimetype=text/plain">[text]</a>&nbsp;
    </li>
    <li>
        <a href="?id=federation&entity_type_filter=idp-all"><?php echo $this->t('text_idp-all'); ?></a>&nbsp;
        <a href="?id=federation&entity_type_filter=idp-all&mimetype=application/xml">[xml]</a>&nbsp;
        <a href="?id=federation&entity_type_filter=idp-all&mimetype=text/plain">[text]</a>&nbsp;
        <?php
        foreach ($this->data['export.states'] AS $state) {
            echo '<a href="?id=federation&entity_type_filter=idp-all&mimetype=application/xml&state=' . $state . '">[xml/' . $state . ']</a>&nbsp;';
        }
        ?>
    </li>
    <li>
        <a href="?id=federation&entity_type_filter=sp-all"><?php echo $this->t('text_sp-all'); ?></a>&nbsp;
        <a href="?id=federation&entity_type_filter=sp-all&mimetype=application/xml">[xml]</a>&nbsp;
        <a href="?id=federation&entity_type_filter=sp-all&mimetype=text/plain">[text]</a>&nbsp;
        <?php
        foreach ($this->data['export.states'] AS $state) {
            echo '<a href="?id=federation&entity_type_filter=sp-all&mimetype=application/xml&state=' . $state . '">[xml/' . $state . ']</a>&nbsp;';
        }
        ?>
    </li>
    <li>
        <a href="?id=federation&entity_type_filter=saml20-all"><?php echo $this->t('text_saml20-all'); ?></a>&nbsp;
        <a href="?id=federation&entity_type_filter=saml20-all&mimetype=application/xml">[xml]</a>&nbsp;
        <a href="?id=federation&entity_type_filter=saml20-all&mimetype=text/plain">[text]</a>&nbsp;
        <?php
        foreach ($this->data['export.states'] AS $state) {
            echo '<a href="?id=federation&entity_type_filter=saml20-all&mimetype=application/xml&state=' . $state . '">[xml/' . $state . ']</a>&nbsp;';
        }
        ?>
    </li>
    <li>
        <a href="?id=federation&entity_type_filter=shib13-all"><?php echo $this->t('text_shib13-all'); ?></a>&nbsp;
        <a href="?id=federation&entity_type_filter=shib13-all&mimetype=application/xml">[xml]</a>&nbsp;
        <a href="?id=federation&entity_type_filter=shib13-all&mimetype=text/plain">[text]</a>&nbsp;
        <?php
        foreach ($this->data['export.states'] AS $state) {
            echo '<a href="?id=federation&entity_type_filter=shib13-all&mimetype=application/xml&state=' . $state . '">[xml/' . $state . ']</a>&nbsp;';
        }
        ?>
    </li>
</ul>
<a href="?id=federation&entity_type_filter=saml20-all&mimetype=application/xml&state=prodaccepted">[TEST]</a>&nbsp;

<!-- END CONTENT -->
</div>

<?php $this->includeAtTemplateBase('includes/footer.php');?>