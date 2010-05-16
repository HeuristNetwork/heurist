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

require_once('../php/modules/cred.php');
require_once('../php/modules/db.php');
require_once('t1000.php');

if (!is_logged_in()) {
	header('Location: ../php/login.php');
	return;
}

mysql_connection_db_overwrite(DATABASE);
$template = file_get_contents('templates/user_clouds_delete.html');
$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->verify();
if ($_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) $body->execute();
}
$body->render();

?>
