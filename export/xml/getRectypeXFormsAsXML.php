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
 * returns database definitions (rectypes, details etc.)
 * as form data ready for use in mobile app - primarily intended for NeCTAR FAIMS projectbrief description of file
 *
 * @author      Stephen White  <stephen.white@sydney.edu.au>
 * @copyright   (C) 2005-2013 University of Sydney
 * @link        http://Sydney.edu.au/Heurist/about.html
 * @version     3.1.0
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package     Heurist academic knowledge management system
 * @subpackage  XForms
 * @uses        HEURIST_DBNAME
 * @uses        HEURIST_BASE_URL
 */
require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
require_once (dirname(__FILE__) . '/../../common/php/dbMySqlWrappers.php');
require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');
require_once (dirname(__FILE__) . '/../../admin/describe/rectypeXFormLibrary.php');
mysql_connection_select(DATABASE);
if (mysql_error()) {
	die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
}
if (!is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db=' . HEURIST_DBNAME);
	return;
}
$rtyID = (@$_REQUEST['rtyID'] ? $_REQUEST['rtyID'] : null);
if (!$rtyID) {
	print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must supply a rectype ID</span><p></div></div></body></html>";
	return;
}
list($form, $rtName, $rtConceptID, $rtDescription, $report) = buildform($rtyID);
if (!$form) {
	echo $report;
} else {
	echo $form;
}
return;
?>
