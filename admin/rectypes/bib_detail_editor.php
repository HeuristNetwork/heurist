<?php

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');
require_once(dirname(__FILE__).'/../../records/TitleMask.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}
if (! is_admin()) {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	return;
}

//define('T1000_DEBUG', 1);

$grp_id = intval(@$_REQUEST['grp_id']);

$_REQUEST['_bdr_search_search'] = 1;

mysql_connection_db_overwrite(DATABASE);

error_log("db = ". DATABASE. " instance = ". HEURIST_INSTANCE);
if (@$_REQUEST['update-active-rec-types']) {
	$arts = array_keys($_REQUEST['active_rt']);
	mysql_query('delete from active_rec_types where art_id not in (' . join(',', $arts) . ')');
	mysql_query('insert ignore into active_rec_types values (' . join('),(', $arts) . ')');
	header('Location: bib_detail_editor.php?instance='.HEURIST_INSTANCE);
	return;
}

$template = file_get_contents('bib_detail_editor.html');
$template = str_replace('{PageHeader}', '[literal]'.file_get_contents('../describe/menu.html').'[end-literal]', $template);
$template = str_replace('[special-rt_id]', intval(@$_REQUEST['rt_id']), $template);
$template = str_replace('[special-grp_id]', $grp_id, $template);
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['edit-success'] = false;
$body->global_vars['added-metadata-element'] = false;
$body->global_vars['field-errors'] = false;
$body->global_vars['new-field-errors'] = false;
$body->global_vars['new'] = @$_REQUEST['new']? 1 : 0;
$body->global_vars['editing_reftype'] = @$_REQUEST['rt_id']? 1 : 0;

$body->global_vars['workgroup-dropdown'] = '<select name=grp_id onchange="form.submit();"><option value="">(this instance)</option>';
$res = mysql_query('select grp_id,grp_name from '.USERS_DATABASE.'.Groups where grp_type != "Usergroup" order by grp_name');
while ($row = mysql_fetch_assoc($res)) {
	$body->global_vars['workgroup-dropdown'] .= '<option value="'.$row['grp_id'].'"'. ($row['grp_id'] == $grp_id ? ' selected ' : '') .'>'.$row['grp_name'].'</option>';
}
$body->global_vars['workgroup-dropdown'] .= '</select>';
$body->global_vars['grp_id'] = $grp_id;
$body->global_vars['instance-name'] = HEURIST_INSTANCE;
//$body->global_vars['new_bdr_bdr_wg_id'] = $grp_id;

$body->global_vars['edit-master-types-link'] = HEURIST_INSTANCE === "" ? "<a href=master_bib_detail_editor.php>Edit MASTER record type definitions</a>" : "Go to <a href=http://".HOST_BASE.$_SERVER["REQUEST_URI"].">primary instance</a> to edit MASTER record type definitions";

$body->verify();
if (@$_REQUEST['_submit']) {

	$field_error = check_title_mask($_REQUEST['rt1_rt_title_mask'], intval($_REQUEST['rt_id']));
	if ($field_error) $body->global_vars['new-field-errors'] = 'The title mask has an error: ' . $field_error;

	$body->input_check();
	if (!$field_error  &&  $body->satisfied) {
		if ($body->execute()) {
			if (@$_REQUEST['action'] == 'Add record type') {
				$rt_id = mysql_insert_id();
				if ($rt_id > 0) {
					system('cd ../../common/images/reftype-icons  &&  cp questionmark.gif ' . $rt_id . '.gif');
				}

				header('Location: bib_detail_editor.php?rt_id=' . $rt_id . '&new=1');
				return;
			} else if (! $MYSQL_ERRORS  and  @$_REQUEST['action'] == 'Save') {
				$body->global_vars['edit-success'] = true;
			} else if (! $MYSQL_ERRORS  and  @$_REQUEST['action'] == 'Add metadata element') {
				$body->global_vars['added-metadata-element'] = true;
			}
		}
	}
}

if (! @$_REQUEST['new_bdt_bdt_required'])
	$_REQUEST['new_bdt_bdt_required'] = 'Y';
$body->render();


?>
