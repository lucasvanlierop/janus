<?php
/*
 * JANUS
 */

$session = SimpleSAML_Session::getInstance();
$config = SimpleSAML_Configuration::getInstance();
$janus_config = SimpleSAML_Configuration::getConfig('module_janus.php');

if(isset($_GET['error'])) {
    $et = new SimpleSAML_XHTML_Template($config, 'janus:error.php', 'janus:janus');
    $et->data['header'] = 'JANUS';
    if($_GET['error'] == 'error_index_user_inactive') {
        $et->data['error'] = 'Access error. User is inactive.';
    }
    else {
        $et->data['error'] = 'Unknown error when authorizating the user to access';
    }
    $et->show();
    exit();
}

$authsource = $janus_config->getValue('auth', 'login-admin');
$useridattr = $janus_config->getValue('useridattr', 'eduPersonPrincipalName');

if ($session->isValid($authsource)) {
	$attributes = $session->getAttributes();
	// Check if userid exists
	if (!isset($attributes[$useridattr])) 
		throw new Exception('User ID is missing');
	$userid = $attributes[$useridattr][0];
} else {
	SimpleSAML_Auth_Default::initLogin(
		$authsource, 
		SimpleSAML_Utilities::selfURL(), 
		NULL, 
		array(
			'SPMetadata' =>	array(
				'token' => $_REQUEST['token'],
				'mail' => $_REQUEST['mail']
			)
		)
	);
}

$selectedtab = isset($_REQUEST['selectedtab']) ? $_REQUEST['selectedtab'] : 1;

$user = new sspmod_janus_User($janus_config->getValue('store'));
$user->setUserid($userid);

$user_load_type = $janus_config->getValue('user_load_type', EMAIL_LOAD);

if($user_load_type == USERID_LOAD) {
    $mode = sspmod_janus_User::USERID_LOAD;
}
else if ($user_load_type == UID_LOAD) {
    $mode = sspmod_janus_User::UID_LOAD;
}
else {
    $mode = sspmod_janus_User::EMAIL_LOAD;
}

if(!$user->load($mode)) {
     if ($user->getActive() === 'yes') {
        $session->doLogout();
        SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/index.php?error=unknown'));
    } else {
        SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/newUser.php'), array('userid' => $userid));
    }
} else {
	if(isset($_GET['truncate'])) {
		$ucontrol = new sspmod_janus_UserController($janus_config);
		$ucontrol->truncateDB();
	} 
    if ($user->getActive() === 'yes') {
	    SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/dashboard.php?selectedtab='.$selectedtab));
    } else {
        $session->doLogout();
        SimpleSAML_Utilities::redirect(SimpleSAML_Module::getModuleURL('janus/index.php?error=error_index_user_inactive'));
    }
}
?>
