<?php

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
* /redirects/viewRecord.php
* acts as a PID redirector to viewRecord.php
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');

// Input is of the form .../viewRecord.php?db=mydatabase&recID=3456

$id = @$_REQUEST["recID"];

// Redirect to .../records/view/viewRecord.php
// TODO: change to use /records/view/renderRecordAsXML.php (XML with parameterisation for human-readable view)

header('Location: '.HEURIST_BASE_URL.'records/view/viewRecord.php?db='.HEURIST_DBNAME.'&recID='.$id);

return;

?>
