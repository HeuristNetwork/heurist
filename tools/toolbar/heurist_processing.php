<?php
//possible modes:
//	1 - online check
//	2 - synchronization

//error_log('request: ' . print_r($_REQUEST, 1));
//error_log('post: ' . print_r($_POST, 1));
//error_log("mode: " . $_REQUEST['mode']);

require_once('../php/modules/cred.php');

unset($mode);

//$mode=2;
//$_REQUEST['ZoteroItems']=array();
//$_REQUEST['ZoteroItems'][]="item 1";
//$_REQUEST['ZoteroItems'][]="item 2";

if(isset($_REQUEST['mode'])&&is_numeric($_REQUEST['mode'])&&($_REQUEST['mode']>0)&&($_REQUEST['mode']<3)){
	$mode=$_REQUEST['mode'];
}

if(!isset($mode)){
	//invalid request, mode is not specified

	header("Location: sorry.html");
	exit;
};

if($mode==1){
	//just send "online"
//error_log("answering mode 1 call");
	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"windows-1251\"?>"."\n";
	echo "<online>"."\n";
	echo "\tonline"."\n";
	echo "</online>";
	exit;
};

if($mode==2){
	//tell synchronizer that data was transfered
error_log("answering mode 2 call");

	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"windows-1251\"?>"."\n";
	echo "<ok>"."\n";
	echo "\tok"."\n";
	echo "</ok>";

	//save data
	jump_sessions();
	$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['ZoteroItems'] = $_REQUEST['ZoteroItems'];
error_log("jumped sessions in  mode 2 call");
//error_log('session ZoteroItems: ' . print_r($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['ZoteroItems'], 1));


	exit;
};

?>
