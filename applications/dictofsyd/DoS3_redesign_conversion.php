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
    * File: DoS3Redesign.php Update the existing DoS3 database from DoS Trsut to a properly strcutured H3 database
    *       suitable for export of HML for HuNI 
    *
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2014 University of Sydney
    * @link        http://Sydney.edu.au/Heurist
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */


    // ---- DATABASE SETUP ---------------------------------------------------------------------------------

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    // Deals with all the database connections stuff

    mysql_connection_select(DATABASE);
    if(mysql_error()) {
        die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
    }

    if (!is_logged_in() && HEURIST_DBNAME!="") {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db=DoS3_redesign&last_uri='.urlencode(HEURIST_CURRENT_URL) );
        return;
    }

    // ---- SUBSTANTIVE CODE STARTS HERE ---------------------------------------------------------------------------------

    echo "<h3>Requires DoS3_redesign database as copy of original supplied by DoS, before running this script</h3>" ;

    $id=29; // 30 is next available

    $entityname = array ("Artefact","Building","Event","Natural Feature","Organisation (DoS)","Person (DoS)","Place","Structure"); 
    // Organisation and Person already exist as rectypes so need to be differentiated

    foreach($entityname as $rtname) { // Duplicate record type

        $id++; // incremented within loop

        // Dulicate record type definition
        $query="Insert into hdb_DoS3_redesign.defRecTypes
        (SELECT $id,'$rtname', rty_OrderInGroup, rty_Description, rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, rty_Status, rty_OriginatingDBID, rty_NameInOriginatingDB, 
        rty_IDInOriginatingDB, rty_NonOwnerVisibility, rty_ShowInLists, rty_RecTypeGroupID, rty_RecTypeModelIDs, rty_FlagAsFieldset, rty_ReferenceURL, rty_AlternativeRecEditor, 
        rty_Type, rty_ShowURLOnEditForm, rty_ShowDescriptionOnEditForm, rty_Modified, rty_LocallyModified
        FROM hdb_DoS3_redesign.defRecTypes where rty_ID=25)";
        $res = mysql_query($query);
        if (mysql_error()) {
            echo "<b>Warning: </b> Unable to duplicate record type 25 into record type $id = $rtname (".mysql_error().")<br/>";
        }

        // Duplicate record structure details (fields)
        $query="INSERT INTO hdb_DoS3_redesign.defRecStructure 
        (rst_RecTypeID,rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, 
        rst_RecordMatchOrder, rst_CalcFunctionID, rst_RequirementType, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_OriginatingDBID, rst_IDInOriginatingDB,rst_MaxValues, rst_MinValues, 
        rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs, rst_OrderForThumbnailGeneration,rst_TermIDTreeNonSelectableIDs, rst_Modified, rst_LocallyModified)
        (SELECT $id, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, rst_RecordMatchOrder, rst_CalcFunctionID, 
        rst_RequirementType, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_OriginatingDBID, rst_IDInOriginatingDB, rst_MaxValues, rst_MinValues, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, 
        rst_PtrFilteredIDs, rst_OrderForThumbnailGeneration, rst_TermIDTreeNonSelectableIDs, rst_Modified, rst_LocallyModified 
        from hdb_DoS3_redesign.defRecStructure where rst_RecTypeID=25)";
        $res = mysql_query($query);
        if (mysql_error()) {               
            echo "<b>Warning: </b> Unable to duplicate record structure (fields) for record type 25 --> $id (".mysql_error().")<br/>";
        }

        // Convert undifferentiated Entity types (record type 25) to separate entity types

        switch ($id) {  // term codes for each of the uindifferntiated entity types in DoS3 Entuity records
            case 30: $termCode=3291; break;
            case 31: $termCode=3294; break;   
            case 32: $termCode=3296; break;   
            case 33: $termCode=3298; break;   
            case 34: $termCode=3300; break;   
            case 35: $termCode=3301; break;   
            case 36: $termCode=3302; break;   
            case 37: $termCode=3305; break;   
        };

        $query="update Records, recDetails set rec_RecTypeID=$id where (Records.rec_ID=recDetails.dtl_RecID) 
            And (rec_RecTypeID=25) and (dtl_DetailTypeID=75) and (dtl_Value='$termcode')";

        $res = mysql_query($query);
        if (mysql_error()) {
            echo "<b>Warning: </b> Unable to convert undifferentiated entity type $id (".mysql_error().")<br/>";
        }

    } // duplication of record types, splitting undifferentiated entity type to multiple



?>
