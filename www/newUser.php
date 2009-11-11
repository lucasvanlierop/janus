<?php
$session = SimpleSAML_Session::getInstance();
$config = SimpleSAML_Configuration::getInstance();
$janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

$authsource = $janus_config->getValue('auth', 'login-admin');
$useridattr = $janus_config->getValue('useridattr', 'eduPersonPrincipalName');


if ($session->isValid($authsource)) {
    $attributes = $session->getAttributes();
    // Check if userid exists
    if (!isset($attributes[$useridattr]))
        throw new Exception('User ID is missing');
    $userid = $attributes[$useridattr][0];
} else {
    SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/index.php'));
}

$econtroller = new sspmod_janus_UserController($janus_config);

$usertypes = $janus_config->getValue('usertypes');

$et = new SimpleSAML_XHTML_Template($config, 'janus:janus-newUser.php', 'janus:janus');

$allow_usercreation = $janus_config->getBoolean('usercreation.allow', true);
if($allow_usercreation && isset($_POST['submit'])) {
    $user = new sspmod_janus_User($janus_config->getValue('store'));
    $user->setUserid($_POST['userid']);
    $user->setType($_POST['type']);
    $user->setActive('yes');
    $user->save();
    $et->data['user_created'] = TRUE ;
}

if(isset($_GET['userid'])) {
    $et->data['userid'] = $_GET['userid'];
}
$et->data['users'] = $econtroller->getUsers();
$et->data['usertypes'] = $usertypes;
$et->data['allow_usercreation'] = $allow_usercreation;
$et->data['admin_email'] = $janus_config->getValue('admin.email', '');
$et->show();
?>