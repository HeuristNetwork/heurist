<?php
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    header('Content-type: text/javascript');
    $rv = array();
    
    if (! is_admin()) {
        $rv['error'] = "Sorry, you need to be a database owner to be able to modify the database structure";
        print json_format($rv);
        return;
    }
        
    if(!@$_REQUEST['rtyID']){        
        $rv = array();
        $rv['error'] = "Sorry, record type to duplicate not defined";
        print json_format($rv);
        return;
    }
            
    mysql_connection_overwrite(DATABASE);
    $old_rt_id = $_REQUEST['rtyID'];

    $query= "INSERT into defRecTypes (rty_Name, rty_OrderInGroup, rty_Description, rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, rty_Status, rty_OriginatingDBID, "
    ."rty_NameInOriginatingDB, rty_IDInOriginatingDB, rty_NonOwnerVisibility, rty_ShowInLists, rty_RecTypeGroupID, rty_RecTypeModelIDs, rty_FlagAsFieldset,"
    ."rty_ReferenceURL, rty_AlternativeRecEditor, rty_Type, rty_ShowURLOnEditForm, rty_ShowDescriptionOnEditForm, rty_Modified, rty_LocallyModified) "
    ." SELECT CONCAT('Duplication of ', rty_Name), rty_OrderInGroup, rty_Description, rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, rty_Status, rty_OriginatingDBID, "
    ."rty_NameInOriginatingDB, rty_IDInOriginatingDB, rty_NonOwnerVisibility, rty_ShowInLists, rty_RecTypeGroupID, rty_RecTypeModelIDs, rty_FlagAsFieldset,"
    ."rty_ReferenceURL, rty_AlternativeRecEditor, rty_Type, rty_ShowURLOnEditForm, rty_ShowDescriptionOnEditForm, rty_Modified, rty_LocallyModified "
    ."FROM defRecTypes where rty_ID=".$old_rt_id;

    $res = mysql_query($query);
    $new_rt_id = mysql_insert_id();
    
    $query= "INSERT INTO defRecStructure (rst_RecTypeID,rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription, 
rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, 
rst_RecordMatchOrder, rst_CalcFunctionID, rst_RequirementType, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_OriginatingDBID, rst_IDInOriginatingDB, 
rst_MaxValues, rst_MinValues, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs, rst_OrderForThumbnailGeneration, 
rst_TermIDTreeNonSelectableIDs, rst_Modified, rst_LocallyModified)
select $new_rt_id, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription, 
rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, 
rst_RecordMatchOrder, rst_CalcFunctionID, rst_RequirementType, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_OriginatingDBID, rst_IDInOriginatingDB, 
rst_MaxValues, rst_MinValues, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs, rst_OrderForThumbnailGeneration, 
rst_TermIDTreeNonSelectableIDs, rst_Modified, rst_LocallyModified 
from defRecStructure where rst_RecTypeID=$old_rt_id";    
    $res = mysql_query($query);

    $rv['id'] = $new_rt_id;
    $rv['rectypes'] = getAllRectypeStructures();
    print json_format($rv);    
?>
