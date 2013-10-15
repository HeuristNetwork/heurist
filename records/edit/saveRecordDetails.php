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


    /* Save a bibliographic record (create a new one, or update an existing one) */

    define('SAVE_URI', 'disabled');

    require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
    require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
    require_once(dirname(__FILE__)."/../../common/php/utilsTitleMask.php");
    require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");
    require_once(dirname(__FILE__)."/../disambig/findFuzzyRecordMatches.php");
    require_once(dirname(__FILE__)."/../../search/getSearchResults.php");
    require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

    if (! is_logged_in()) return;

    mysql_connection_overwrite(DATABASE);
    mysql_query('set @logged_in_user_id = ' . get_user_id());


    $checkSimilar = array_key_exists("check-similar", $_POST);
    if ($checkSimilar) {
        $rec_id = intval(@$_POST["recID"]);
        $rec_types = array(intval(@$_POST["rectype"]));

        $fields = array();
        foreach ($_POST as $key => $val) {
            if (preg_match('/^type:(\d+)/', $key, $matches)) {
                $fields["t:".$matches[1]] = $val;
            }
        }
        $matches = findFuzzyMatches($fields, $rec_types, $rec_id);

        if (count($matches)) {
            print '({ matches: ' . json_format($matches) . ' })';
            return;
        }
    }

    $rtyID = @$_POST['rectype'] ? $_POST['rectype'] : (defined('RT_NOTE') ? RT_NOTE : null);
    if (!$rtyID && @$_POST['recID']) {
        $res = mysql_query("select rec_RecTypeID from Records where rec_ID = ".$_POST['recID']);
        if ($res){
            $rtyID = mysql_fetch_row($res);
            $rtyID = $rtyID[0];
        }
    }
    $TL = array();
    $query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms';
    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
        $TL[$row['trm_ID']] = $row;
    }


    /*****DEBUG****///error_log(" Save dtl Post before  ".print_r($_POST,true));

    if ($_POST["save-mode"] == "edit"  &&  intval($_POST["recID"])) {
        $updated = updateRecord(intval($_POST["recID"]),$rtyID);
    } else if ($_POST["save-mode"] == "new") {
        $updated = insertRecord($rtyID);
    } else {
        $updated = false;
    }

    /*****DEBUG****///error_log(" Save dtl Request  ".print_r($_REQUEST,true));
    /*****DEBUG****///error_log(" Save dtl Post  ".print_r($_POST,true));

    if ($updated) {

        updateRecTypeUsageCount(); //getRecordInfoLibrary

        // Update bib record data
        // Update recDetails, rec_ScratchPad and rec_Title in (parent.parent).HEURIST.record
        print "(";
        define("JSON_RESPONSE", 1);
        require_once(dirname(__FILE__)."/../../common/php/loadRecordData.php");
        print ")";
    }
    /***** END OF OUTPUT *****/

    function getTermsFromFormat($formattedStringOfTermIDs) {
        global $TL;
        $validTermIDs = array();
        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return $validTermIDs;
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            /*****DEBUG****///error_log( "term tree string = ". $formattedStringOfTermIDs);
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            /*****DEBUG****///error_log( "term array string = ". $formattedStringOfTermIDs);
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }
        // Validate termIDs
        /*****DEBUG****///error_log( "term IDS = ". print_r($termIDs,true));
        foreach ($termIDs as $trmID) {
            // check that the term valid
            if ( $trmID && array_key_exists($trmID,$TL) && !in_array($trmID,$validTermIDs)){ // valid trm ID
                array_push($validTermIDs,$trmID);
            }
        }
        return $validTermIDs;
    }

    function isValidID($id, $dtyID, $rtyID = null) {
        static $rtFieldDefs = null;
        static $dtyIDDefs = null;
        if (!is_numeric($id)) return false;
        if (!$dtyIDDefs) {
            $dtyIDDefs = array();
            $res = mysql_query("select dty_ID, dty_Type, dty_JsonTermIDTree,dty_TermIDTreeNonSelectableIDs,dty_PtrTargetRectypeIDs".
                               " from defDetailTypes".
                               " where dty_Type in ('enum','relationtype','resource')");
            while ($res && $row = mysql_fetch_row($res)) {
                //use first element as index
                if ( $row[1] === 'enum' || $row[1] === 'relationtype') {
                    //create term Id list and term list.
                    $terms = getTermsFromFormat($row[2]);
                    if (($cntTrm = count($terms)) > 0) {
                        if ($cntTrm == 1) {
                            $terms = getTermOffspringList($terms[0]);
                        }else{
                            $nonTerms = getTermsFromFormat($row[3]);
                            if (count($nonTerms) > 0) {
                                $terms = array_diff($terms,$nonTerms);
                            }
                        }
                        if (!empty($terms)) {
                            $dtyIDDefs[$row[0]] = $terms;
                        }
                    }
                } else if ($row[1] === 'resource') {
                    // create list of valid rectypes
                    if (count($row[4])>0 && $row[4] != "") {
                        $temp = explode(",",$row[4]);
                        if (!empty($temp)) {
                            $dtyIDDefs[$row[0]] = $temp;
                        }
                    }
                }
            }
        }
        if ($rtyID && !$rtFieldDefs) {
            $rtFieldDefs = array('max'=>array());
            $res = mysql_query("select rst_DetailTypeID, dty_Type, rst_MaxValues,".
                                    " if(rst_FilteredJsonTermIDTree is not null and CHAR_LENGTH(rst_FilteredJsonTermIDTree)>0,rst_FilteredJsonTermIDTree,dty_JsonTermIDTree) as rst_FilteredJsonTermIDTree,".
                                    " if(rst_TermIDTreeNonSelectableIDs is not null and CHAR_LENGTH(rst_TermIDTreeNonSelectableIDs)>0,rst_TermIDTreeNonSelectableIDs,dty_TermIDTreeNonSelectableIDs) as rst_TermIDTreeNonSelectableIDs,".
                                    " if(rst_PtrFilteredIDs is not null and CHAR_LENGTH(rst_PtrFilteredIDs)>0,rst_PtrFilteredIDs,dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs".
                               " from defRecStructure".
                                 " left join defDetailTypes on rst_DetailTypeID = dty_ID".
                               " where rst_RecTypeID=" . $rtyID );
            while ($res && $row = mysql_fetch_row($res)) {
                //use first element as index
                if (is_numeric($row[2])) {
                    $rtFieldDefs['max'][$row[0]] = $row[2];
                }
/*                
These fields are never defined in UI. Thus, they should aleays be null. It was for overwrite of base field constraints by further contraints within the recstructure, 
which is one step too many and has been removed from design by Ian in approx 2011
                
                if ( $row[1] === 'enum' || $row[1] === 'relationtype') {
                    //create term Id list and term list.
                    $terms = getTermsFromFormat($row[3]);
                    if (($cntTrm = count($terms)) > 0) {
                        if ($cntTrm == 1) {
                            $terms = getTermOffspringList($terms[0]);
                        }else{
                            $nonTerms = getTermsFromFormat($row[4]);
                            if (count($nonTerms) > 0) {
                                $terms = array_diff($terms,$nonTerms);
                            }
                        }
                        if (!empty($terms)) {
                            $rtFieldDefs[$row[0]] = $terms;
                        }
                    }
                } else if ($row[1] === 'resource') {
                    // create list of valid rectypes
                    if (count($row[5])>0 && $row[5] != "") {
                        $temp = explode(",",$row[5]);
                        if (!empty($temp)) {
                            $rtFieldDefs[$row[0]] = $temp;
                        }
                    }else{
                            $rtFieldDefs[$row[0]] = "all";
                    }
                }
*/                
            }
        }
        /*****DEBUG****///error_log("save record isValidID rtFields = ".print_r($rtFieldDefs,true));
        /*****DEBUG****///error_log("save record isValidID rdtyDefs = ".print_r($dtyIDDefs,true));
        
        if ($rtyID && array_key_exists($dtyID, $rtFieldDefs)) {
            return $rtFieldDefs[$dtyID] === "all" || in_array($id,$rtFieldDefs[$dtyID]);
        } else if (array_key_exists($dtyID, $dtyIDDefs)) {
            return $dtyIDDefs[$dtyID] === "all" || in_array($id,$dtyIDDefs[$dtyID]);
        }
        return false;
    }

    /**
    * Main method that parses POST and update details for given record ID
    *
    * @param int $recID
    */
    function updateRecord($recID, $rtyID = null) {
        // Update the given record.
        // This is non-trivial: so that the versioning stuff (achive_*) works properly
        // we need to separate this into updates, inserts and deletes.
        // We get the currect record details and compare them against the post
        // if the details id is in the post[dtyID][dtlID] then compare the values

        $recID = intval($recID);

        // Check that the user has permissions to edit it.
        $res = mysql_query("select * from Records".
                            " left join sysUsrGrpLinks on ugl_GroupID=rec_OwnerUGrpID".
                            " left join defRecTypes on rty_ID=rec_RecTypeID".
                            " where rec_ID=$recID and (! rec_OwnerUGrpID or rec_OwnerUGrpID=".get_user_id()." or ugl_UserID=".get_user_id().")");
        if (mysql_num_rows($res) == 0) {
            $res = mysql_query("select grp.ugr_Name from Records, ".USERS_DATABASE.".sysUGrps grp where rec_ID=$recID and grp.ugr_ID=rec_OwnerUGrpID");
            $grpName = mysql_fetch_row($res);
            $grpName = $grpName[0];

            print '({ error: "\nSorry - you can\'t edit this record.\nYou aren\'t in the ' . slash($grpName) . ' workgroup" })';
            return;
        }
        $record = mysql_fetch_assoc($res);
        /*****DEBUG****///error_log("save record dtls POST ".print_r($_POST,true));
        // Upload any files submitted ... (doesn't have to take place right now, but may as well)
        uploadFiles();  //Artem: it does not work here - since we uploaded files at once

        // Get the existing records details and compare them to the incoming data
        $recDetails = getRecordDetails($recID);

// find UPDATES - everything that is in current record and has a post value is treated as an update
        $recDetailUpdates = array();
        /*****DEBUG****///error_log("save record dtls ".print_r($recDetails,true));
        foreach ($recDetails as $dtyID => $dtlIDs) {
            $eltName ="type:".$dtyID;
            $skipEltName = "_type:".$dtyID;
            if (@$_POST[$skipEltName]  &&  is_array($_POST[$skipEltName])) { // handle any _type post meant for ignore, need to remove from recDetails
              foreach (@$_POST[$skipEltName] as $codedDtlID => $ingnoreVal) {
                if (! preg_match("/^bd:\\d+$/", $codedDtlID)) continue;

                $dtlID = substr($codedDtlID, 3);  // everything after "bd:"

                unset($_POST[$skipEltName][$codedDtlID]);  // remove data from post submission
                if (count($_POST[$skipEltName]) == 0){  // if nothing left in post dtyID then remove it also
                    unset($_POST[$skipEltName]);
                }
                unset($recDetails[$dtyID][$dtlID]);  // remove data from local reflection of the database
              }
            }
            if (! (@$_POST[$eltName]  &&  is_array($_POST[$eltName]))) {
                // element wasn't in POST: ignore it -this could be a non-rectype detail
                unset($recDetails[$dtyID]); // remove from details so it's not deleted
                continue;
            }
            if (count($_POST[$eltName]) == 0) {
                // element was in POST but without content: values have been deleted client-side (need to be deleted in DB so leave POST)
                continue;
            }

            $bdInputHandler = getInputHandlerForType($dtyID); //returns the particular handler (processor) for given field type
            foreach ($dtlIDs as $dtlID => $val) {
                /*****DEBUG****///error_log(" in saveRecord details loop  $dtyID,  $dtlID, ".print_r($val,true));
                $eltID ="bd:".$dtlID;

                $val = @$_POST[$eltName][$eltID];
                if (! $bdInputHandler->inputOK($val,$dtyID,$rtyID)) {
                    /*****DEBUG****///error_log(" in saveRecord update details value check error  $dtyID,  $dtlID, ".print_r($val,true));
                    continue;	// faulty input ... ignore
                }

                $toadd = $bdInputHandler->convertPostToMysql($val);
                /*****DEBUG****///error_log(" in saveRecord update details value converted from $val to $toadd");
                if ($toadd==null) continue;

                $recDetailUpdates[$dtlID] = $toadd;
                $recDetailUpdates[$dtlID]["dtl_DetailTypeID"] = $dtyID;

                /*
                @TODO Since this function is utilized in (email)import we need to add verification of values according to detail type
                at the first for terms (enumeration field type)
                */

                unset($_POST[$eltName][$eltID]);	// remove data from post submission
                if (count($_POST[$eltName]) == 0){  // if nothing left in post dtyID then remove it also
                    unset($_POST[$eltName]);
                }
                unset($recDetails[$dtyID][$dtlID]);	// remove data from local reflection of the database
            }
        }
        /*****DEBUG****///error_log("save record dtls POST after updates removed ".print_r($_POST,true));
        /*****DEBUG****///error_log("save record dtls after updates removed ".print_r($recDetails,true));


// find DELETES
        // Anything left in recDetails now represents recDetails rows that need to be deleted

        $bibDetailDeletes = array();

        foreach ($recDetails as $dtyID => $dtlIDs) {
            foreach ($dtlIDs as $dtlID => $val) {
                array_push($bibDetailDeletes, $dtlID);
            }
        }
        /*****DEBUG****///error_log("save record dtlIDs to delete = ".print_r($bibDetailDeletes,true));

// find INSERTS
        // Try to insert anything left in POST as new recDetails rows
        $bibDetailInserts = array();


        /*****DEBUG****///error_log(" in saveRecord checking for inserts  _POST =".print_r($_POST,true));

        foreach ($_POST as $eltName => $bds) {
            // if not properly formatted or empty or an empty array then skip it
            if (! preg_match("/^type:\\d+$/", $eltName)  ||  ! $_POST[$eltName]  ||  count($_POST[$eltName]) == 0) continue;

            $dtyID = substr($eltName, 5);
            $bdInputHandler = getInputHandlerForType($dtyID);
            foreach ($bds as $eltID => $val) {
                if (! $bdInputHandler->inputOK($val,$dtyID,$rtyID)) {
                    /*****DEBUG****///error_log(" in saveRecord insert details value check error for $eltName,  $eltID, ".print_r($val,true));
                    continue;	// faulty input ... ignore
                }

                $newBibDetail = $bdInputHandler->convertPostToMysql($val);
                $newBibDetail["dtl_DetailTypeID"] = $dtyID;
                $newBibDetail["dtl_RecID"] = $recID;
                /*****DEBUG****///error_log("new detail ".print_r($newBibDetail,true));
                array_push($bibDetailInserts, $newBibDetail);

                unset($_POST[$eltName][$eltID]);	// remove data from post submission
            }
        }

        // Anything left in POST now is stuff that we have no intention of inserting ... ignore it

        // We now have:
        //  - $recDetailUpdates: an assoc. array of dtl_ID => column values to be updated in recDetails
        //  - $bibDetailInserts: an array of column values to be inserted into recDetails
        //  - $bibDetailDeletes: an array of dtl_ID values corresponding to rows to be deleted from recDetails

        // Commence versioning ...
        mysql_query("start transaction");

        $recUpdates = array("rec_Modified" => array("now()"), "rec_FlagTemporary" => 0);
        $recUpdates["rec_ScratchPad"] = $_POST["notes"];
        if (intval(@$_POST["rectype"])) {
            $recUpdates["rec_RecTypeID"] = intval($_POST["rectype"]);
        }
        if (array_key_exists("rec_url", $_POST)) {
            $recUpdates["rec_URL"] = $_POST["rec_url"];
        }
        $owner = $record['rec_OwnerUGrpID'];
        if (is_admin() || is_admin('group',$owner) || $owner == get_user_id()) {// must be grpAdmin or record owner to changes ownership or visibility
            if (array_key_exists("rec_owner", $_POST)) {
                $recUpdates["rec_OwnerUGrpID"] = $_POST["rec_owner"];
            }
            if (array_key_exists("rec_visibility", $_POST)) {
                $recUpdates["rec_NonOwnerVisibility"] = $_POST["rec_visibility"];
            }else if ($record['rec_NonOwnerVisibility'] == 'public' && HEURIST_PUBLIC_TO_PENDING){
                $recUpdates["rec_NonOwnerVisibility"] = 'pending';
            }
        }
        /*****DEBUG****///error_log(" in saveRecord update recUpdates = ".print_r($recUpdates,true));
        mysql__update("Records", "rec_ID=$recID", $recUpdates);
        $biblioUpdated = (mysql_affected_rows() > 0)? true : false;
        if (mysql_error()) error_log("error rec update".mysql_error());
        $updatedRowCount = 0;
        foreach ($recDetailUpdates as $bdID => $vals) {

            /*****DEBUG****///error_log(" in saveRecord update details dtl_ID = $bdID value =".print_r($vals,true));

            mysql__update("recDetails", "dtl_ID=$bdID and dtl_RecID=$recID", $vals);
            if (mysql_affected_rows() > 0) {
                ++$updatedRowCount;
            }
        }
        if (mysql_error()) error_log("error detail updates".mysql_error());

        $insertedRowCount = 0;
        foreach ($bibDetailInserts as $vals) {
            /*****DEBUG****///error_log(" in saveRecord insert details detail =".print_r($vals,true));
            mysql__insert("recDetails", $vals);
            if (mysql_affected_rows() > 0) {
                ++$insertedRowCount;
            }
        }
        if (mysql_error()) error_log("error detail inserts".mysql_error());

        $deletedRowCount = 0;
        if ($bibDetailDeletes) {
            /*****DEBUG****///error_log(" in saveRecord delete details ".print_r($bibDetailDeletes,true));
            mysql_query("delete from recDetails where dtl_ID in (" . join($bibDetailDeletes, ",") . ") and dtl_RecID=$recID");
            if (mysql_affected_rows() > 0) {
                $deletedRowCount = mysql_affected_rows();
            }
        }
        if (mysql_error()) error_log("error detail deletes".mysql_error());

        // eliminate any duplicated lines
        $notesIn = explode("\n", str_replace("\r", "", $_POST["notes"]));
        $notesOut = "";
        $notesMap = array();
        for ($i=0; $i < count($notesIn); ++$i) {
            if (! @$notesMap[$notesIn[$i]]  ||  ! $notesIn[$i]) {	// preserve blank lines
                $notesOut .= $notesIn[$i] . "\n";
                $notesMap[$notesIn[$i]] = true;
            }
        }
        $_POST["notes"] = preg_replace("/\n\n+/", "\n", $notesOut);

        if ($updatedRowCount > 0  ||  $insertedRowCount > 0  ||  $deletedRowCount > 0  ||  $biblioUpdated) {
            /* something changed: update the records title and commit all changes */
            $new_title = fill_title_mask($record["rty_TitleMask"], $record["rec_ID"], $record["rec_RecTypeID"]);

            mysql_query("update Records
                set rec_Title = '" . mysql_real_escape_string($new_title) . "'
                where rec_ID = $recID");

            mysql_query("commit");

            // Update memcached's copy of record (if it is cached)
            updateCachedRecord($recID);

            return true;
        } else {
            /* nothing changed: rollback the transaction so we don't get false versioning */
            mysql_query("rollback");
            return false;
        }
    }


    function insertRecord($rtyID = null) {
// check if there is preference for OwnerGroup and visibility
        $addRecDefaults = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['addRecDefaults'];
        if ($addRecDefaults){
            if ($addRecDefaults[1]){
                $userDefaultOwnerGroupID = intval($addRecDefaults[1]);
            }
            if ($addRecDefaults[2]){
                $userDefaultVisibility = $addRecDefaults[2];
            }
        }
        $usrID = get_user_id();
        //set owner to passed value else to NEWREC default if defined else to user
        //ART $owner = @$_POST["owner"]?$_POST["owner"]:( defined("HEURIST_NEWREC_OWNER_ID") ? HEURIST_NEWREC_OWNER_ID : get_user_id());
        //ART $owner = ((@$_POST["owner"] || @$_POST["owner"] === '0') ? intval($_POST["owner"]) :(defined('HEURIST_NEWREC_OWNER_ID') ? HEURIST_NEWREC_OWNER_ID : get_user_id()));

        $owner = (is_numeric(@$_POST['rec_owner'])?intval($_POST['rec_owner']):
            ( is_numeric(@$userDefaultOwnerGroupID) ? $userDefaultOwnerGroupID :
                (defined('HEURIST_NEWREC_OWNER_ID') ? HEURIST_NEWREC_OWNER_ID: intval($usrID))));

        $nonownervisibility = (@$_POST['rec_visibility']?(strtolower($_POST['rec_visibility'])):
            (@$userDefaultVisibility ? $userDefaultVisibility :
                (defined('HEURIST_NEWREC_ACCESS') ? HEURIST_NEWREC_ACCESS: 'viewable')));

//error_log(" in insertRecord");
        // if non zero (everybody group, test if user is member, if not then set owner to user
        if (intval($owner) != 0 && !in_array($owner,get_group_ids())) {
            $owner = get_user_id();
        }
        // Try to insert anything in POST as details of a new Record.
        // We do this by creating a stub record, and then updating it.
        mysql__insert("Records", array(
                "rec_Added" => date('Y-m-d H:i:s'),
                "rec_AddedByUGrpID" => get_user_id(),
                "rec_RecTypeID" => intval($rtyID),
                "rec_ScratchPad" => @$_POST["notes"] ? $_POST["notes"]:null,
                "rec_OwnerUGrpID" => $owner,
                "rec_NonOwnerVisibility" => $nonownervisibility,
                "rec_URL" => @$_POST["rec_url"]? $_POST["rec_url"] : ""));

        $_REQUEST["recID"] = $recID = mysql_insert_id();
        if($recID){
//error_log(" in insertRecord recID = $recID");

            if ($usrID) {
                mysql__insert('usrBookmarks', array(
                        'bkm_recID' => $recID,
                        'bkm_Added' => date('Y-m-d H:i:s'),
                        'bkm_Modified' => date('Y-m-d H:i:s'),
                        'bkm_UGrpID' => $usrID
                    ));
            }

            updateRecord($recID, $rtyID);
            return true;
        }else{
            return false;
        }
    }

    function getRecordDetails($recID) {
        $recID = intval($recID);
        $details = array();
        $dtlColumns = array("dtl_ID",
                            "dtl_RecID",
                            "dtl_DetailTypeID",
                            "dtl_Value",
                            "dtl_AddedByImport",
                            "dtl_UploadedFileID",
                            "if(dtl_Geo is not null, astext(dtl_Geo),null) as dtl_Geo",
                            "dtl_ValShortened",
                            "dtl_Modified");
        $res = mysql_query("select ".join(",",$dtlColumns)." from recDetails where dtl_RecID = " . $recID);
        while ($val = mysql_fetch_assoc($res)) {
            $dtyID = $val["dtl_DetailTypeID"];
            $dtlID = $val["dtl_ID"];

            if (! @$details[$dtyID]) {
                $details[$dtyID] = array();
            }
            $details[$dtyID][$dtlID] = $val;
        }

        return $details;
    }


    function uploadFiles() {
        /*
        * Check if there are any files submitted for uploading;
        * process them (save them to disk) and commute their element values to the appropriate ulf_ID.
        */

        if (! $_FILES) return; // this is likely deprecated since each file gets upload one at a time.
        foreach ($_FILES as $eltName => $upload) {
            /* check that $elt_name is a sane element name */
            if (! preg_match('/^type:\\d+$/', $eltName)  ||  ! $_FILES[$eltName]  ||  count($_FILES[$eltName]) == 0) continue;

            /* FIXME: should check that the given element is supposed to be a file */
            /*
            $bdr = &get_bdr($matches[1]);
            $bdt = &get_bdt($bdr['rst_DetailTypeID']);
            if (! $bdr  ||  ! $bdt  ||  $bdt['dty_Type'] != 'file') continue;
            */

            /* Ooh, this is annoying / odd:
            * if several file elements have the name "foobar[]"
            * then $_FILES['foobar'] will not be an array of { name, type, tmp_name, error, size } values;
            * rather, it is a value of { name array, type array, tmp_name array, ... }
            */
            if (! $upload["size"]) continue;
            foreach ($upload["size"] as $eltID => $size) {
                if ($size <= 0) continue;

                $fileID = upload_file($upload["name"][$eltID], null, //$upload["type"][$eltID],
                    $upload["tmp_name"][$eltID], $upload["error"][$eltID], $upload["size"][$eltID], null, false);

                if (is_numeric($fileID)) {
                    /* We got ourselves an uploaded file.
                    * Put an appropriate entry in the $_POST array:
                    *  - if a bdID was specified, preserve that bdID slot in the $_POST (for UPDATE)
                    *  - otherwise, add it as just another "new" input in $_POST (for INSERT)
                    */
                    if (! $_POST[$eltName]) $_POST[$eltName] = array();

                    if (preg_match("/^bd:\\d+$/", $eltID)) {
                        $_POST[$eltName][$eltID] = $fileID;
                    } else {
                        array_push($_POST[$eltName], $fileID);
                    }
                }
            }
        }
    }

    /**
    * Creates static array of classes for each particular detail type
    * Returns the class for specified detail type
    *
    * @param mixed $typeID - detail type name
    * @return mixed - class to parse the particular detail type in POST
    */
    function getInputHandlerForType($dtyID) {
        static $dtyToBaseType = null;
        if (! $dtyToBaseType) {
            $dtyToBaseType = mysql__select_assoc("defDetailTypes", "dty_ID", "dty_Type", "1");
        }

        static $baseTypeToInputHandler = null;
        if (! $baseTypeToInputHandler) {
            $baseTypeToInputHandler = array(
                "freetext" => new BibDetailFreetextInput(),
                "blocktext" => new BibDetailBlocktextInput(),
                "date" => new BibDetailTemporalInput(),
                "resource" => new BibDetailResourceInput(),
                "float" => new BibDetailFloatInput(),
                "enum" => new BibDetailDropdownInput(),
                "file" => new BibDetailFileInput(),
                "geo" => new BibDetailGeographicInput(),
                "separator" => new BibDetailSeparator(),
                "default" => new BibDetailInput(),
                "relationtype" => new BibDetailDropdownInput(),
                // Note: The following types can no logner be defined, but are incldued here for backward compatibility
                "boolean" => new BibDetailBooleanInput(),
                "integer" => new BibDetailIntegerInput(),
                "year" => new BibDetailYearInput(),
                "urlinclude" => new BibDetailUrlIncludeInput() //artem to remove
            );
        }

        if (array_key_exists($dtyID, $dtyToBaseType)  &&  array_key_exists($dtyToBaseType[$dtyID], $baseTypeToInputHandler)) {
            return $baseTypeToInputHandler[$dtyToBaseType[$dtyID]];
        } else {
            return $baseTypeToInputHandler["default"];
        }
    }


    class BibDetailSeparator {
        function convertPostToMysql($postVal) {
            // Given a value corresponding to a single input from a POST submission,
            // return an empty array of values
            return array();
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            // Separator has no input to return true
            return true;
        }
    }
    class BibDetailInput {
        function convertPostToMysql($postVal) {
            // Given a value corresponding to a single input from a POST submission,
            // return array of values split into their respective MySQL columns
            return array("dtl_Value" => $postVal);
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            // This is abstract
            return false;
        }
    }
    class BibDetailFreetextInput extends BibDetailInput {
        function convertPostToMysql($postVal) {
            return array("dtl_Value" => trim($postVal));
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            return (strlen(trim($postVal)) > 0);
        }
    }
    class BibDetailIntegerInput extends BibDetailFreetextInput {
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/^\\s*-?\\d+\\s*$/", $postVal);
        }
    }
    class BibDetailFloatInput extends BibDetailFreetextInput {
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+)?)\\s*$/", $postVal);
        }
    }
    class BibDetailYearInput extends BibDetailFreetextInput {
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$/i", $postVal);
        }
    }
    class BibDetailTemporalInput extends BibDetailFreetextInput {
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/\\S/", $postVal);
        }
    }
    class BibDetailBlocktextInput extends BibDetailInput {
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/\\S/", $postVal);
        }
    }
    class BibDetailResourceInput extends BibDetailInput {
        function inputOK($postVal, $dtyID, $rtyID) {
            $res = mysql_query("select rec_RecTypeID from Records where rec_ID = ".$postVal);
            
error_log("select rec_RecTypeID from Records where rec_ID = ".$postVal);
            if ($res){
                $tempRtyID = mysql_fetch_row($res);
                $tempRtyID = $tempRtyID[0];
error_log("RES=".$tempRtyID);
            } else {
                return false;
            }
            $res = isValidID($tempRtyID,$dtyID,$rtyID);
error_log("VALID ID=".$res);
            return $res;
        }
    }
    class BibDetailBooleanInput extends BibDetailInput {
        function convertPostToMysql($postVal) {
            if ($postVal == "yes"  ||  $postVal == "true")
                return array("dtl_Value" => "true");
            else if ($postVal)
                return array("dtl_Value" => "false");
                else
                    return array("dtl_Value" => NULL);
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/^(?:yes|true|no|false)$/", $postVal);
        }
    }
    class BibDetailDropdownInput extends BibDetailInput {
        static $labelToID = null;
        function convertPostToMysql($postVal) {
            //SAW  TODO: need to validate that the term is valid for given dtyID also need to accept concept ids
            /*  query for concept id  to local id lookup
            select trm_Label, concat(convert(if(trm_OriginatingDBID is null,
            (SELECT sys_dbRegisteredID FROM sysIdentificationlimit 1),
            trm_OriginatingDBID),
            char),
            "-",
            convert(if(trm_IDInOriginatingDB is null,
            trm_ID,
            trm_IDInOriginatingDB),
            char)) as trm_ConceptID
            from defTerms;
            */
            if (! @$labelToID) {
                $labelToID = mysql__select_assoc("defTerms", "trm_Label", "trm_ID", "1");
            }
            if(is_numeric($postVal)){//termID validate it exist TODO check valid for this type, need to pass type id
                return array("dtl_Value" => $postVal);
            }else{  //convert label to  trm_ID
                return array("dtl_Value" => $labelToID[$postVal]);
            }
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            if (! @$labelToID) {
                $labelToID = mysql__select_assoc("defTerms", "trm_Label", "trm_ID", "1");
            }
            /*****DEBUG****///
            // if value is term label
            if (!is_numeric($postVal) && array_key_exists($postVal,$labelToID)) {
                $postVal = $labelToID[$postVal];
            }
            return isValidID($postVal,$dtyID,$rtyID);
        }
    }
    class BibDetailFileInput extends BibDetailInput {
        function convertPostToMysql($postVal) {

            //artem
            if(is_numeric($postVal)){  //this is old way - ulf_ID
                return array("dtl_UploadedFileID" => $postVal);

            }else{  // new way - $postVal - json string with file data array - structure similar get_uploaded_file_info

                $ulf_ID = register_external($postVal); //in uploadFile.php
                return ($ulf_ID==null)?null:array("dtl_UploadedFileID" => $ulf_ID);
            }
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            /*****DEBUG****///error_log("FILE:>>>>>>>>>>>".$postVal);
            return (is_numeric($postVal) || preg_match("/\\S/", $postVal));
        }
    }
    class BibDetailUrlIncludeInput extends BibDetailInput {
        function convertPostToMysql($postVal) {
            return array("dtl_Value" => $postVal, "dtl_ValShortened" => "KUKU");
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            return preg_match("/\\S/", $postVal);	// has non space characters
        }
    }
    class BibDetailGeographicInput extends BibDetailInput {
        function convertPostToMysql($postVal) {
            if (preg_match("/^(p(?= point)|r(?= polygon)|[cl](?= linestring)|pl(?= polygon)) ((?:point|polygon|linestring)\\(?\\([-0-9.+, ]+?\\)\\)?)$/i", $postVal, $matches)) {
                return array("dtl_Value" => $matches[1], "dtl_Geo" => array("geomfromtext(\"" . $matches[2] . "\")"));
            } else
                return array();
        }
        function inputOK($postVal, $dtyID, $rtyID) {
            if (! preg_match("/^(p point|r polygon|[cl] linestring)\\(?\\(([-0-9.+, ]+?)\\)\\)?$/i", $postVal, $matches)) {
                if (! preg_match("/^(pl polygon)\\(\\(([-0-9.+, ]+?)\\)(?:,\\([-0-9.+, ]+?\\))?\\)$/i", $postVal, $matches)) {
                    return false;	// illegal value
                }
            }

            $type = strtolower($matches[1]);
            $pointString = $matches[2];

            $FLOAT = '-?[0-9]+(?:\\.[0-9]*)?'; // ensure that we have a list of float pairs  xx.xxxxx yy.yyyyyy, x.xx y.yy, etc
            return preg_match("/^$FLOAT $FLOAT(?:,$FLOAT $FLOAT)*$/", $pointString);
        }
    }

?>
