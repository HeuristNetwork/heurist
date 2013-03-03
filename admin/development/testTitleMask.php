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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

mysql_connection_select(DATABASE);

require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');


$mask = @$_REQUEST['mask']; //
$rt = @$_REQUEST['rtID']; //
$recID = @$_REQUEST['recID']; //
if (!$rt or !$mask){
	echo "please pass in at least a mask and a rectype ID  as mask=validMaskStringHere&rtID=# <br />";
	echo "you may also padd in a record ID of rectype to calculate the title <br />";
	echo "be sure to add the db=databaseName for the database you are working on.<br />";
	exit();
}
echo (($ret=check_title_mask($mask,$rt))?$ret."<br /><br /><br />":"title mask\"<b>$mask</b>\"checks out to be valid for rectype $rt<br /><br /><br />");
echo "canonical form for mask is \"<b>".make_canonical_title_mask($mask,$rt)."</b>\"<br /><br /><br />";
if (!$ret && $recID) {
	echo "Title for record $recID :     <b>".fill_title_mask($mask,$recID,$rt)."</b><br />";
}

//echo json_format(_title_mask__get_rec_detail_types(),true)."<br><br><br>";
//echo json_format(_title_mask__get_rec_detail_requirements(),true)."<br><br><br>";


//echo json_format(getTermTree("reltype","prefix"),true).";\n";
//echo print_r(getTermSets('reltypevocab'),true)."<br><br><br>";
//require_once(dirname(__FILE__).'/../../hapi/php/loadHapiCommonInfo.php');
//echo json_format(getAllRectypeConstraint(),true)."<br>";
//require_once(dirname(__FILE__).'/../../common/php/loadCommonInfo.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
//require_once(dirname(__FILE__).'/../../import/bookmarklet/getRectypesAsJSON.php');//echo print_r(getRectypeStructureFields("174"),true)."c14<br>";
?>
