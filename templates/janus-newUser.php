<?php
/**
 * Main template for JANUS.
 *
 * @author Jacob Christiansen, <jach@wayf.dk>
 * @package simpleSAMLphp
 * @subpackage JANUS
 * @version $Id: janus-main.php 11 2009-03-27 13:51:02Z jach@wayf.dk $
 */
$this->data['header'] = 'JANUS';
$this->includeAtTemplateBase('includes/header.php');

?>
<div id="content">

<?php
if($this->data['allow_usercreation'] === TRUE) {

    echo '<h1>' . $this->t('header_new_user') . '</h1>';

    if($this->data['user_created'] === TRUE) {
        echo '<p>' . $this->t('text_new_user_created', array('%USERID%' => $this->data['userid'])) .'</p>';
        echo '<a href="'. SimpleSAML_Module::getModuleURL('janus/index.php?selectedtab=0') .'">Dashboard</a><br /><br />';
    } else {
        echo '<form method="post" action="">';
        echo $this->t('text_create_new_user', array('%USERID%' => $this->data['userid']));
        echo '<input type="hidden" name="userid" value="'. $this->data['userid'].'" /><br />';
        echo '<input type="hidden" name="type" value="technical" /><br />';

        /*
        echo 'Type: <select name="type">';
        foreach($this->data['usertypes'] AS $type) {
            echo '<option value="'. $type .'">'. $type .'</option>';
        }

        echo '</select><br />';

        if (isset($this->data['mail'])) {
            echo 'E-mail: <input type="text" name="email" value="'. $this->data['mail'].'" /><br />';
        } else {
            echo 'E-mail: <input type="text" name="email" /><br />';
        }
      */
        echo '<br /><br />';
        echo '<input type="submit" name="submit" value="' . $this->t('text_submit_button') .'">';
        echo '</form>';
    }
} else {
    echo '<h1>' . $this->t('error_createuser_permission') . '</h1>';
    echo('<p>' . $this->t('error_createuser_permission_reason') .
         ', <a href="mailto:' . $this->data['admin_email'] . '">' .
         $this->t('error_createuser_permission_admin_contact') . '</a></p>.');
}


//foreach($this->data['users'] AS $user) {
//  echo $user['uid'] .' - '. $user['type'] .' - '. $user['email'] .' - '. $user['update'] .' - '. $user['created'] .' - '. $user['ip'] .'<br />';
//}
?>

</div>

<?php $this->includeAtTemplateBase('includes/footer.php'); ?>