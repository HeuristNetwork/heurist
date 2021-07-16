<?php


/*
* Copyright (C) 2005-2020 University of Sydney
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
* Duplicate an existing Record type, creating a new copy with the same description and fields but a different internal code
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     4
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  AdminStructure
* @todo    Add functionality to look for rectype, to save a pointer
* @todo    Figure out a way to display all groups in which the rectype is located, and to change it
* @todo    (art) replace document.getElementById to jquery $
* @todo    (art) use display block for terms fields instead of dynamic addition
*/
require_once (dirname(__FILE__).'/../System.php');

$res = false;

$system = new System();
if( $system->init(@$_REQUEST['db']) ){

    if(!$system->is_admin()){
        $system->addError(HEURIST_REQUEST_DENIED, 
            'To perform this action you must be logged in as Administrator of group \'Database Managers\'');
    }else if(!@$_REQUEST['rtyID']){
        $system->addError(HEURIST_INVALID_REQUEST, 'Sorry, record type to duplicate has not been defined');
    }else{

        $rv = array();

        $old_rt_id = $_REQUEST['rtyID'];

    $query= "INSERT into defRecTypes (rty_Name, rty_OrderInGroup, rty_Description, rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, rty_Status, "
    ."rty_NonOwnerVisibility, rty_ShowInLists, rty_RecTypeGroupID, rty_RecTypeModelIDs, rty_FlagAsFieldset,"
    ."rty_ReferenceURL, rty_AlternativeRecEditor, rty_Type, rty_ShowURLOnEditForm, rty_ShowDescriptionOnEditForm, rty_Modified, rty_LocallyModified) "
    ." SELECT CONCAT('Duplication of ', rty_Name), rty_OrderInGroup, rty_Description, rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, 'open', "
    ."rty_NonOwnerVisibility, rty_ShowInLists, rty_RecTypeGroupID, rty_RecTypeModelIDs, rty_FlagAsFieldset,"
    ."rty_ReferenceURL, rty_AlternativeRecEditor, rty_Type, rty_ShowURLOnEditForm, rty_ShowDescriptionOnEditForm, rty_Modified, IFNULL(rty_LocallyModified,0) "
    ."FROM defRecTypes where rty_ID=".$old_rt_id;

        define('HEURIST_DBID', $system->get_system('sys_dbRegisteredID'));

        $mysqli = $system->get_mysqli();
        $res = $mysqli->query($query);
        $new_rt_id = $mysqli->insert_id;
    
        $dbID = HEURIST_DBID;
        if(!($dbID>0)) $dbID = 0;
        
        $query= 'UPDATE defRecTypes SET rty_OriginatingDBID='.$dbID
                    .', rty_NameInOriginatingDB=rty_Name'
                    .', rty_IDInOriginatingDB='.$new_rt_id.' WHERE rty_ID='.$new_rt_id;
        $res = $mysqli->query($query);
        

        $query= "INSERT INTO defRecStructure (rst_RecTypeID,rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
        rst_DisplayOrder, rst_DisplayWidth, rst_DisplayHeight, rst_DefaultValue,
        rst_RecordMatchOrder, rst_CalcFunctionID, rst_RequirementType, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_OriginatingDBID, rst_IDInOriginatingDB,
        rst_MaxValues, rst_MinValues, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs,
        rst_CreateChildIfRecPtr, rst_PointerMode, rst_PointerBrowseFilter, rst_OrderForThumbnailGeneration,
        rst_TermIDTreeNonSelectableIDs, rst_Modified, rst_LocallyModified)
        SELECT $new_rt_id, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
        rst_DisplayOrder, rst_DisplayWidth, rst_DisplayHeight, rst_DefaultValue,
        rst_RecordMatchOrder, rst_CalcFunctionID, rst_RequirementType, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_OriginatingDBID, rst_IDInOriginatingDB,
        rst_MaxValues, rst_MinValues, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs,
        rst_CreateChildIfRecPtr, rst_PointerMode, rst_PointerBrowseFilter, rst_OrderForThumbnailGeneration,
        rst_TermIDTreeNonSelectableIDs, rst_Modified, rst_LocallyModified
        from defRecStructure where rst_RecTypeID=$old_rt_id";

        $res = $mysqli->query($query);
            
        //remove icon if exists
        $filename = HEURIST_ICON_DIR . $new_rt_id . '.png';
        if(file_exists($filename)) unlink($filename);
        $filename = HEURIST_THUMB_DIR . 'th_' . $new_rt_id . '.png';
        if(file_exists($filename)) unlink($filename);
    }
}
    
if($res){ 
    $rv['id'] = $new_rt_id;
    //2021-06-15 we don't use old format for defintions $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
    $response = array("status"=>HEURIST_OK, "data"=>$rv);
 
}else{   
    $response = $system->getError();
}

header('Content-type: text/javascript; charset=utf-8');
print json_encode( $response );
?>
