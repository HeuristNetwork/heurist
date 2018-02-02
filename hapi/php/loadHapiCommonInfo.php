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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/*<!--
* filename, brief description, date of creation, by whom
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristNetwork.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
-->*/


header("Content-Type: text/javascript");
define('ISSERVICE',1);
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");

mysql_connection_select(DATABASE);


if (defined('HEURIST_USER_GROUP_ID')) {
    $res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(if(usr.ugr_FirstName is null,'Fred',usr.ugr_FirstName),' ',if(usr.ugr_LastName is null,'Nerks',usr.ugr_LastName)) as Realname from ".USERS_DATABASE.".sysUGrps usr, ".USERS_DATABASE.".sysUsrGrpLinks
    where ugl_GroupID=2 and ugl_UserID=usr.ugr_ID and usr.ugr_Enabled='y' and !usr.ugr_IsModelUser");
    //usr.ugr_FirstName is not null and usr.ugr_LastName is not null and
} else {
    $res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(if(usr.ugr_FirstName is null,'Fred',usr.ugr_FirstName),' ',if(usr.ugr_LastName is null,'Nerks',usr.ugr_LastName)) as Realname from ".USERS_DATABASE.".sysUGrps usr
    where usr.ugr_Enabled='y' and !usr.ugr_IsModelUser");
    //usr.ugr_FirstName is not null and usr.ugr_LastName is not null and
}

$users = array();
while ($row = mysql_fetch_row($res)) { array_push($users, $row); }

$res = mysql_query("select distinct grp.ugr_ID, grp.ugr_Name, grp.ugr_LongName, grp.ugr_Description, grp.ugr_URLs
    from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks
where ugl_GroupID=grp.ugr_ID");
$workgroups = array();
while ($row = mysql_fetch_row($res)) { array_push($workgroups, $row); }
$res = mysql_query("select rty_ID, rty_Name, rty_CanonicalTitleMask from defRecTypes ");
$recordTypes = array();
while ($row = mysql_fetch_row($res)) array_push($recordTypes, $row);

$res = mysql_query("select dty_ID, dty_Name, dty_HelpText, dty_Type, NULL as enums, dty_PtrTargetRectypeIDs,
    dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, dty_ExtendedDescription, dty_DetailTypeGroupID,
    dty_FieldSetRecTypeID, dty_ShowInLists, dty_NonOwnerVisibility
from defDetailTypes");

$detailTypes = array();
$detailTypesById = array();
while ($row = mysql_fetch_row($res)) {
    switch ($row[3]) {	// determine variety from dty_Type
        // these ones thoughtfully have the same name for their variety as they do for their dty_Type
        case "date":
        case "file":
            break;

        case "resource":
            $row[3] = "reference";
            break;

        case "enum":
            $row[3] = "enumeration";
            //calculate the list of terms for this detail type
            if (!$row[6]){
                $row[4] = array( array("0","No Terms for $row[1]"));
            }else{
                preg_match_all("/\d+/",$row[6],$matches);
                $trmIDs = $matches[0];
                if (count($trmIDs) == 1) { //term set parent term, so expand to direct children
                    $childTerms = getTermOffspringList($trmIDs[0],false);
                    if (count($childTerms) > 0) {
                        $trmIDs = $childTerms;
                    }
                }else if ($row[7]){
                    preg_match_all("/\d+/",$row[7],$hdrTermIDs);
                    if ($hdrTermIDs[0] && is_array($hdrTermIDs[0])) {
                        $trmIDS = array_diff($trmIDs,$hdrTermIDs[0]);	//remove disabled Terms
                    }
                }
                $trmCnt = count($trmIDs);
                $trmIDs = join(",",$trmIDs);
                if ($trmIDs) {
                    $resTerm = mysql_query("select trm_ID,trm_Label from defTerms ".//test to see that all terms are in this domain
                        "where trm_ID in ($trmIDs) and trm_Domain = 'enum'");
                    if (mysql_num_rows($resTerm) != $trmCnt){
                        $row[4] = array( array("0","Invalid Terms for $row[1]"));
                    }else{
                        $row[4] = array();
                        while($trmSet = mysql_fetch_row($resTerm)) {
                            array_push($row[4],$trmSet);
                        }
                    }
                }
            }
            break;

        case "relationtype":
            //calculate the list of terms for this detail type
            if (!$row[6]){
                $row[4] = array( array("0","No Terms for $row[1]"));
            }else{
                preg_match_all("/\d+/",$row[6],$matches);
                $trmIDs = $matches[0];
                if (count($trmIDs) == 1) { //term set parent term, so expand to direct children
                    $childTerms = getTermOffspringList($trmIDs[0],false);
                    if (count($childTerms) > 0) {
                        $trmIDs = $childTerms;
                    }
                }else if ($row[7]){
                    preg_match_all("/\d+/",$row[7],$hdrTermIDs);
                    if ($hdrTermIDs[0] && is_array($hdrTermIDs[0])) {
                        $trmIDS = array_diff($trmIDs,$hdrTermIDs[0]);	//remove disabled Terms
                    }
                }
                $trmCnt = count($trmIDs);
                $trmIDs = join(",",$trmIDs);
                if ($trmIDs) {
                    $resTerm = mysql_query("select trm.trm_ID,trm.trm_Label,inv.trm_ID as invID, inv.trm_Label as invLabel ".
                        "from defTerms trm left join defTerms inv on trm.trm_InverseTermID = inv.trm_ID ".
                        "where trm.trm_ID in ($trmIDs) and trm.trm_Domain = 'relation' ");
                    if (mysql_num_rows($resTerm) != $trmCnt){
                        $row[4] = array( array("0","Invalid Terms for $row[1]"));
                    }else{
                        $row[4] = array();
                        while($trmSet = mysql_fetch_row($resTerm)) {
                            array_push($row[4],$trmSet);
                        }
                    }
                }
            }
            break;

        case "geo":
            $row[3] = "geographic";
            break;

        case "boolean":
            $row[3] = "boolean";
            break;

        case "blocktext":
            $row[3] = "blocktext";
            break;
        case "urlinclude":
            $row[3] = "urlinclude";
            break;
        case "integer":
        case "float":
            $row[3] = "numeric";
            break;
        default:
            $row[3] = "literal";
    }
    array_push($detailTypes, $row);
}

// detailRequirements is an array of [recordTypeID, detailTypeID, requiremence, repeatable, name, prompt, match, size, order, default] values
$detailRequirements = array();
$rec_types = mysql__select_array("defRecTypes","distinct rty_ID", "1 order by rty_ID");
//$rec_types = mysql__select_array("defRecStructure left join defDetailType on dty_ID = rst_DetailTypeID",
//									"distinct rst_RecTypeID", "1 order by rst_RecTypeID");
// rdr = [ rst_DetailTypeID => [
// 0-rst_DisplayName
// 1-rst_DisplayHelpText
// 2-rst_DisplayExtendedDescription
// 3-rst_DefaultValue
// 4-rst_RequirementType
// 5-rst_MaxValues
// 6-rst_MinValues
// 7-rst_DisplayWidth
// 8-rst_RecordMatchOrder
// 9-rst_DisplayOrder
//10-rst_DisplayDetailTypeGroupID
//11-rst_FilteredJsonTermIDTree
//12-rst_PtrFilteredIDs
//13-rst_TermIDTreeNonSelectableIDs
//14-rst_CalcFunctionID
//15-rst_Status
//16-rst_OrderForThumbnailGeneration
//17-dty_TermIDTreeNonSelectableIDs
//18-dty_FieldSetRectypeID
//19-rst_NonOwnerVisibility]....]


//rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
// rst_DefaultValue, rst_RequirementType, rst_MaxValues, rst_MinValues, rst_DisplayWidth, rst_RecordMatchOrder,
// rst_DisplayOrder, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs,
// rst_TermIDTreeNonSelectableIDs, rst_CalcFunctionID, rst_Status, rst_OrderForThumbnailGeneration,
// dty_TermIDTreeNonSelectableIDs, dty_FieldSetRectypeID, rst_NonOwnerVisibility] ...]
$rstC2I = getColumnNameToIndex(getRectypeStructureFieldColNames());
foreach ($rec_types as $rec_type) {
    $rectype_fields = getRectypeFields($rec_type);
    if($rectype_fields && is_array($rectype_fields))
    foreach ($rectype_fields as $dtyID => $rdr) {
        // saw TODO need to represent the trm ids  and rectype pointer ids that are valid for this rectype.detailtype.
        array_push($detailRequirements, array(
            $rec_type,																							// 0-recTypeID
            $dtyID,																								// 1-detailTypeID
            $rstC2I['rst_RequirementType']?$rdr[$rstC2I['rst_RequirementType']]:null,							// 2-RequirementType
            $rstC2I['rst_MaxValues'] && $rdr[$rstC2I['rst_MaxValues']]?intval($rdr[$rstC2I['rst_MaxValues']]):null,// 3-MaxValue
            array_key_exists('rst_DisplayName',$rstC2I)?$rdr[$rstC2I['rst_DisplayName']]:null,									// 4-Name
            $rstC2I['rst_DisplayHelpText']?$rdr[$rstC2I['rst_DisplayHelpText']]:null,							// 5-HelpText
            $rstC2I['rst_RecordMatchOrder']?intval($rdr[$rstC2I['rst_RecordMatchOrder']]):0,					// 6-Match Order
            $rstC2I['rst_DisplayWidth']?intval($rdr[$rstC2I['rst_DisplayWidth']]):0,							// 7-DisplayWidth
            $rstC2I['rst_DisplayHeight']?intval($rdr[$rstC2I['rst_DisplayHeight']]):0,                            // 7-DisplayHeight
            $rstC2I['rst_DisplayOrder']?intval($rdr[$rstC2I['rst_DisplayOrder']]):0,							// 8-Display Order
            $rstC2I['rst_DisplayExtendedDescription']?$rdr[$rstC2I['rst_DisplayExtendedDescription']]:null,		// 9-Extended Description
            $rstC2I['rst_DefaultValue']?$rdr[$rstC2I['rst_DefaultValue']]:null,									//10-Default Value
            $rstC2I['rst_MinValues']?intval($rdr[$rstC2I['rst_MinValues']]):0,									//11-MinValue
            $rstC2I['rst_DisplayDetailTypeGroupID']?$rdr[$rstC2I['rst_DisplayDetailTypeGroupID']]:null,			//12-DetailGroupID
            $rstC2I['rst_FilteredJsonTermIDTree']?$rdr[$rstC2I['rst_FilteredJsonTermIDTree']]:null,				//13-Filtered Enum Term IDs
            $rstC2I['rst_TermIDTreeNonSelectableIDs']?$rdr[$rstC2I['rst_TermIDTreeNonSelectableIDs']]:null,		//14-Extended Disabled Term IDs
            $rstC2I['dty_TermIDTreeNonSelectableIDs']?$rdr[$rstC2I['dty_TermIDTreeNonSelectableIDs']]:null,		//15-Detail Type Disabled Term IDs
            $rstC2I['rst_PtrFilteredIDs']?$rdr[$rstC2I['rst_PtrFilteredIDs']]:null,								//16-Filtered Pointer Constraint Rectype IDs
            $rstC2I['rst_CreateChildIfRecPtr']?$rdr[$rstC2I['rst_CreateChildIfRecPtr']]:0,                      //17- Create child record
            $rstC2I['rst_CalcFunctionID']?$rdr[$rstC2I['rst_CalcFunctionID']]:null,								//18-Calc Function ID
            $rstC2I['rst_OrderForThumbnailGeneration']?$rdr[$rstC2I['rst_OrderForThumbnailGeneration']]:null,	//19-Thumbnail selection Order
            $rstC2I['rst_Status']?$rdr[$rstC2I['rst_Status']]:null,												//20-Status
            $rstC2I['rst_NonOwnerVisibility']?$rdr[$rstC2I['rst_NonOwnerVisibility']]:null,));					//21-Non-Owner Visibility
    }
}

$commonData = array(
    "users" => $users,
    "workgroups" => $workgroups,
    "ratings" => array("0"=>"not rated",
        "1"=>"*",
        "2"=>"**",
        "3"=>"***",
        "4"=>"****",
        "5"=>"*****"),
    "recordTypes" => $recordTypes,
    "detailTypes" => $detailTypes,
    "detailRequirements" =>$detailRequirements,
    'max_post_size'=>__get_config_bytes(ini_get('post_max_size')),
    'max_file_size'=>__get_config_bytes(ini_get('upload_max_filesize'))
);
if (! @$_REQUEST["json"]) {
    print "var HAPI_commonData = ";
}
print json_encode($commonData);
if (! @$_REQUEST["json"]) {
    print ";\n";
}

function __fix_integer_overflow($size) {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
}
function __get_config_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return __fix_integer_overflow($val);
}
?>
