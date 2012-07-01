<?php


//define('_PREF', 'c:/xampp/');
define('_PREF', '/var/www/');

//define('SMARTY_DIR', _PREF.'htdocs/smarty/Smarty-3.0.7/libs/');

define('SMARTY_DIR', dirname(__FILE__).'/../../external/Smarty-3.0.7/libs/');


require_once(SMARTY_DIR.'Smarty.class.php');
require_once(dirname(__FILE__).'/../../common/config/initialise.php');

/*****DEBUG****///error_log(">>>>".HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'viewers/smarty/templates/');

$smarty = new Smarty();

/*
$smarty->template_dir = '/var/www/htdocs/h3-ao/viewers/smarty/templates/';
$smarty->compile_dir  = '/var/www/htdocs/smarty/sandpit5/templates_c/';
$smarty->config_dir   = '/var/www/htdocs/h3-ao/viewers/smarty/configs/';
$smarty->cache_dir    = '/var/www/htdocs/smarty/sandpit5/cache/';
*/
//$smarty->template_dir = HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'viewers/smarty/templates/';
//$smarty->config_dir = HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'viewers/smarty/configs/';

//check folder existance and create new folders if they missed
if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR)){
	if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR, 0777, true)) {
    	die('Failed to create folder for smarty templates');
	}
}
if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR."configs/")){
	if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR."configs/", 0777, true)) {
    	die('Failed to create folder for smarty templates');
	}
}
if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR."templates_c/")){
	if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR."templates_c/", 0777, true)) {
    	die('Failed to create folder for smarty templates');
	}
}
if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR."cache/")){
	if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR."cache/", 0777, true)) {
    	die('Failed to create folder for smarty templates');
	}
}

$smarty->template_dir = HEURIST_SMARTY_TEMPLATES_DIR;
$smarty->config_dir   = HEURIST_SMARTY_TEMPLATES_DIR."configs/";
$smarty->compile_dir  = HEURIST_SMARTY_TEMPLATES_DIR.'templates_c/';
$smarty->cache_dir    = HEURIST_SMARTY_TEMPLATES_DIR.'cache/';

/*****DEBUG****///error_log(">>>>>".HEURIST_SMARTY_TEMPLATES_DIR);

$smarty->registerResource("string", array("str_get_template",
                                       "str_get_timestamp",
                                       "str_get_secure",
                                       "str_get_trusted"));

function str_get_template ($tpl_name, &$tpl_source, &$smarty_obj)
{
    $tpl_source = $tpl_name;
	$tpl_name = "from_str_test";
    // return true on success, false to generate failure notification
    return true;
}

function str_get_timestamp($tpl_name, &$tpl_timestamp, &$smarty_obj)
{
    // do database call here to populate $tpl_timestamp
    // with unix epoch time value of last template modification.
    // This is used to determine if recompile is necessary.
    $tpl_timestamp = time(); // this example will always recompile!
    // return true on success, false to generate failure notification
    return true;
}

function str_get_secure($tpl_name, &$smarty_obj)
{
    // assume all templates are secure
    return true;
}

function str_get_trusted($tpl_name, &$smarty_obj)
{
    // not used for templates
}

?>