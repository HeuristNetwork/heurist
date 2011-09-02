<?php


//define('_PREF', 'c:/xampp/');
define('_PREF', '/var/www/');

define('SMARTY_DIR', _PREF.'htdocs/smarty/Smarty-3.0.7/libs/');


require_once(SMARTY_DIR.'Smarty.class.php');

$smarty = new Smarty();

/*
$smarty->template_dir = '/var/www/htdocs/h3-ao/viewers/smarty/templates/';
$smarty->compile_dir  = '/var/www/htdocs/smarty/sandpit5/templates_c/';
$smarty->config_dir   = '/var/www/htdocs/h3-ao/viewers/smarty/configs/';
$smarty->cache_dir    = '/var/www/htdocs/smarty/sandpit5/cache/';
*/
$smarty->template_dir = _PREF.'htdocs/h3-ao/viewers/smarty/templates/';
$smarty->compile_dir  = _PREF.'htdocs/smarty/sandpit5/templates_c/';
$smarty->config_dir   = _PREF.'htdocs/h3-ao/viewers/smarty/configs/';
$smarty->cache_dir    = _PREF.'htdocs/smarty/sandpit5/cache/';

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