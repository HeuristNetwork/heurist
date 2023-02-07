<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
*  Load rectype names and groups into object - need for selectors on bookmarklet popup
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* load some very basic HEURIST objects into top.HEURIST */

require_once (dirname(__FILE__).'/../../hsapi/System.php');
require_once (dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');

$system = new System();
if(!$system->init(@$_REQUEST['db'])){
    return;
}    

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", '5');
//ob_start('ob_gzhandler');

$mysqli = $system->get_mysqli();

header("Content-type: text/javascript");

$lastModified = mysql__select_value($mysqli, "select max(rty_Modified) from defRecTypes");
$lastModified = strtotime($lastModified[0]);

if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit();
}

ob_start();

print "HEURIST_rectypes = {};\n\n";

$names = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Name from defRecTypes order by rty_Name');

print "top.HEURIST_rectypes.names = " . json_encode($names) . ";\n\n";

$names = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Plural from defRecTypes');

print "top.HEURIST_rectypes.pluralNames = " . json_encode($names) . ";\n\n";

//print "top.HEURIST_rectypes.groupNamesInDisplayOrder = " . json_format(getRectypeGroups()) . ";\n\n";
print "top.HEURIST_rectypes.groups = " . json_encode(dbs_GetRectypeGroups($mysqli, true)) . ";\n\n";

print "if (window.HEURIST_rectypesOnload) HEURIST_rectypesOnload();\n\n";

ob_end_flush();
?>
