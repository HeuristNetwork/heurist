<?php
//NOT USED IN H4

/*
* Copyright (C) 2005-2016 University of Sydney
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
* editSysIdentification.php, edits the system identification record which provides core settigns for the database  
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/t1000/t1000.php');

    if(isForAdminOnly("to modify properties")){
       return;
    }


mysql_connection_overwrite(DATABASE);

$template = file_get_contents('editSysIdentification.html');
// $template = str_replace('{PageHeader}', '[literal]'.file_get_contents('menu.html').'[end-literal]', $template);
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['dbname'] = HEURIST_DBNAME;
$body->verify();
if (@$_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) {
	   $body->execute();
	}
}

$body->render();
?>
