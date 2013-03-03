<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

?>

<?php

	require_once(dirname(__FILE__).'/../../common/config/initialise.php');
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	// Deals with all the database connections stuff

	mysql_connection_overwrite(DATABASE);
	if (! is_logged_in()) {
		header("Location: " . HEURIST_BASE_URL . "common/connect/login.php?db=".HEURIST_DBNAME);
		return;
	}

	if (!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap>".
		"<div id=errorMsg><span>You must be an adminstrator of the owner's group to unlock the database '".HEURIST_DBNAME."'</span>".
		"<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
		" target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}
mysql_query("delete from sysLocks where 1");
/*****DEBUG****///error_log("in unlock ".print_r(HEURIST_DBNAME,true));
if (!mysql_error()){
	returnXMLSuccessMsgPage(" Successfully unlocked '".HEURIST_DBNAME."'");
}

returnXMLErrorMsgPage("The was a problem unlocking '".HEURIST_DBNAME."' error - ".mysql_error());

function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}

function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}

?>
