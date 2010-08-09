<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');
require_once(dirname(__FILE__).'/../../records/TitleMask.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
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
	mysql_query('delete from rec_detail_types where rdt_id = ' . $delete_bdt_id);
	mysql_query('delete from rec_detail_requirements where rdr_rdt_id = ' . $delete_bdt_id);
	header('Location: master_bib_detail_editor.php');
	return;
}


$template = file_get_contents('master_bib_detail_editor.html');
$template = str_replace('{PageHeader}', '[literal]'.file_get_contents('../describe/menu.html').'[end-literal]', $template);
$template = str_replace('[special-rt_id]', intval(@$_REQUEST['rt_id']), $template);
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['edit-success'] = false;
$body->global_vars['added-detail-type'] = false;
$body->global_vars['field-errors'] = false;
$body->global_vars['new-field-errors'] = false;
$body->global_vars['new'] = @$_REQUEST['new']? 1 : 0;
$body->global_vars['editing_reftype'] = @$_REQUEST['rt_id']? 1 : 0;



$body->verify();
if (@$_REQUEST['_submit']) {

	$title_mask = @$_REQUEST['rt1_rt_title_mask'];
	$field_error = check_title_mask($title_mask, intval($_REQUEST['rt_id']));
	if ($field_error) $body->global_vars['new-field-errors'] = 'The title mask has an error: ' . $field_error;

	$body->input_check();
	if (!$field_error  &&  $body->satisfied) {
		if ($body->execute()) {	// 'after insert or update' extra processing
			if (@$_REQUEST['action'] == 'Add record type') {
				$rt_id = mysql_insert_id();
				if ($rt_id > 0) {
					system('cd ../../common/images/reftype-icons  &&  cp questionmark.gif ' . $rt_id . '.gif');
				}

				header('Location: '.HEURIST_URL_BASE.'admin/rectypes/master_bib_detail_editor.php?rt_id=' . $rt_id . '&new=1');
				return;
			} else if (! $MYSQL_ERRORS  and  @$_REQUEST['action'] == 'Save') {
				$body->global_vars['edit-success'] = true;
				$ctm = make_canonical_title_mask($title_mask, intval($_REQUEST['rt_id']));
				mysql_query('update rec_types
				                set rt_canonical_title_mask = "' . $ctm . '"
				              where rt_id = ' . intval($_REQUEST['rt_id']));
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
