<?php

/*

This file is part of the T1000 web database templating system

Developed by Tom Murtagh,
Archaeological Computing Laboratory,
University of Sydney

Copyright (c) 2005, University of Sydney

T1000 is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

T1000 is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (!is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}

if (@$_REQUEST['filterId'])
	$_REQUEST['Id'] = $_REQUEST['filterId'];

$template = file_get_contents('usergroupadmin.html');

$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->global_vars['presetScrollTop'] = intval(@$_REQUEST['scrollTop']);
$body->global_vars['filterId'] = @$_REQUEST['filterId'];

$body->global_vars['proj-group-link'] = HEURIST_INSTANCE === '' ? ' | <a href="projectgroupadmin.php">Edit project groups</a>' : '';

mysql_connection_overwrite(USERS_DATABASE);
$body->verify();

if (@$_REQUEST['make_admin']) {
	mysql_connection_overwrite(USERS_DATABASE);
	mysql_query('update UserGroups set ug_role="admin" where ug_id="'.intval($_REQUEST['make_admin']).'"');
}
if (@$_REQUEST['unmake_admin']) {
	mysql_connection_overwrite(USERS_DATABASE);
	mysql_query('update UserGroups set ug_role="" where ug_id="'.intval($_REQUEST['unmake_admin']).'"');
}
if (@$_REQUEST['remove_group_link']) {
	mysql_connection_overwrite(USERS_DATABASE);
	mysql_query('delete from UserGroups where ug_id="'.intval($_REQUEST['remove_group_link']).'"');
}

mysql_connection_overwrite(USERS_DATABASE);
if (@$_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) {
		if ($body->execute()) {
			$ug_id = mysql_insert_id();

			header('Location: usergroupadmin.php'. (@$_REQUEST['filterId'] ? '?filterId='.$_REQUEST['filterId'] : '') );
		}
	}
}
$body->render();

?>
