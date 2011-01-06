<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');
require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	return;
}
if (! is_admin()  ||  HEURIST_INSTANCE != "") {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	return;
}

//define('T1000_DEBUG', 1);

$_REQUEST['_bdr_search_search'] = 1;

mysql_connection_db_overwrite(HEURIST_COMMON_DB);

$delete_bdt_id = intval(@$_REQUEST['delete_bdt_field']);
if ($delete_bdt_id) {
	mysql_query('delete from defDetailTypes where dty_ID = ' . $delete_bdt_id);
	mysql_query('delete from defRecStructure where rst_DetailTypeID = ' . $delete_bdt_id);
	header('Location: editStructure.php?instance='.HEURIST_INSTANCE);
	return;
}


$template = file_get_contents('editStructure.html');
$template = str_replace('{PageHeader}', '[literal]'.file_get_contents('../../common/html/simpleHeader.html').'[end-literal]', $template);
$template = str_replace('[special-rty_ID]', intval(@$_REQUEST['rty_ID']), $template);
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['edit-success'] = false;
$body->global_vars['added-detail-type'] = false;
$body->global_vars['field-errors'] = false;
$body->global_vars['new-field-errors'] = false;
$body->global_vars['new'] = @$_REQUEST['new']? 1 : 0;
$body->global_vars['editing_reftype'] = @$_REQUEST['rty_ID']? 1 : 0;
$body->global_vars['instance-name'] = HEURIST_INSTANCE;



$body->verify();
if (@$_REQUEST['_submit']) {

	$title_mask = @$_REQUEST['rt1_rt_title_mask'];
	$field_error = check_title_mask($title_mask, intval($_REQUEST['rty_ID']));
	if ($field_error) $body->global_vars['new-field-errors'] = 'The title mask has an error: ' . $field_error;

	$body->input_check();
	if (!$field_error  &&  $body->satisfied) {
		if ($body->execute()) {	// 'after insert or update' extra processing
			if (@$_REQUEST['action'] == 'Add record type') {
				$rt_id = mysql_insert_id();
				if ($rt_id > 0) {
					system('cd ../../common/images/reftype-icons  &&  cp questionmark.gif ' . $rt_id . '.gif');
				}

				header('Location: '.HEURIST_URL_BASE.'admin/structure/editStructure.php?instance='.HEURIST_INSTANCE.'&amp;rty_ID=' . $rt_id . '&new=1');
				return;
			} else if (! $MYSQL_ERRORS  and  @$_REQUEST['action'] == 'Save') {
				$body->global_vars['edit-success'] = true;
				$ctm = make_canonical_title_mask($title_mask, intval($_REQUEST['rty_ID']));
				mysql_query('update defRecTypes
				                set rty_CanonicalTitleMask = "' . $ctm . '"
				              where rty_ID = ' . intval($_REQUEST['rty_ID']));
			} else if (! $MYSQL_ERRORS  and  @$_REQUEST['action'] == 'Add detail type (field)') {
				$body->global_vars['added-detail-type'] = true;
			}
		}
	}
}

if (! @$_REQUEST['new_bdt_bdt_required'])
	$_REQUEST['new_bdt_bdt_required'] = 'Y';
$body->render();


?>
