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

    require_once(dirname(__FILE__)."/../../search/getSearchResults.php");
    require_once(dirname(__FILE__)."/../../common/php/utilsTitleMask.php");
    require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
    require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");


    $msgInfoSaveRec = array(); //array for containing the warning and error information for the calling code.

    //utility function for recording an error message
    function errSaveRec($msg){
        global $msgInfoSaveRec;
        if (!@$msgInfoSaveRec['error']){
            $msgInfoSaveRec['error'] = array($msg);
        }else{
            array_push($msgInfoSaveRec['error'],$msg);
        }
        mysql_query("rollback");
    }
    //utility function for recording an error message
    function warnSaveRec($msg){
        global $msgInfoSaveRec;
        if (!@$msgInfoSaveRec['warning']){
            $msgInfoSaveRec['warning'] = array($msg);
        }else{
            array_push($msgInfoSaveRec['warning'],$msg);
        }
    }
    // NOTE  tags are a complete replacement list of personal tags for this record and are only used if personalised is true
    // $modeImport    0 - no import, 1 - import and check structure, 2 - import as is (without check record type structure
    function saveRecord($recordID, $rectype, $url, $notes, $wg, $vis, $personalised, $pnotes, $rating, $tags, $wgTags, $details, $notifyREMOVE, $notifyADD, $commentREMOVE, $commentMOD, $commentADD, &$nonces=null, &$retitleRecs=null, $modeImport=0) {
        global $msgInfoSaveRec;
        $msgInfoSaveRec = array(); // reset the message array
        mysql_query("start transaction");
        //	$log = " saving record ($recordID) ";
        $recordID = intval($recordID);
        $wg = intval($wg);
        if ($wg || !is_logged_in()) {// non-member saves are not allowed
            $res = mysql_query("select * from ".USERS_DATABASE.".sysUsrGrpLinks where ugl_UserID=" . get_user_id() . " and ugl_GroupID=" . $wg);
            if (mysql_num_rows($res) < 1) {
                errSaveRec("invalid workgroup, record save aborted");
                return $msgInfoSaveRec;
            }
        }

        $rectype = intval($rectype);
        if ($recordID  &&  ! $rectype) {
            errSaveRec("cannot change existing record to private note, record save aborted");
            return $msgInfoSaveRec;
        }

        if ($vis && (!in_array(strtolower($vis),array('hidden','viewable','pending','public')))){
            $vis = null;
        }
        $now = date('Y-m-d H:i:s');

        // public records data
        if (! $recordID) {
            //		$log .= "- inserting record ";
            mysql__insert("Records", array(
                    "rec_RecTypeID" => $rectype,
                    "rec_URL" => $url,
                    "rec_ScratchPad" => $notes,
                    "rec_OwnerUGrpID" => ($wg||$wg==0?$wg:get_user_id()),
                    "rec_NonOwnerVisibility" => ($vis? $vis:"viewable"),
                    "rec_AddedByUGrpID" => get_user_id(),
                    "rec_Added" => $now,
                    "rec_Modified" => $now,
                    "rec_AddedByImport" => ($modeImport>0?1:0)
                ));
            if (mysql_error()) {
                errSaveRec("database record insert error - " . mysql_error());
                return $msgInfoSaveRec;
            }
            $recordID = mysql_insert_id();
        }else{
            $res = mysql_query("select * from Records left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID=rec_OwnerUGrpID and ugl_UserID=".get_user_id()." where rec_ID=$recordID");
            $record = mysql_fetch_assoc($res);
            if ($wg != $record["rec_OwnerUGrpID"] && $record["rec_OwnerUGrpID"] != get_user_id() ) {
                if ($record["rec_OwnerUGrpID"] > 0  &&  $record["ugl_Role"] != "admin") {
                    // user is trying to change the workgroup when they are not an admin
                    errSaveRec("user is not a workgroup admin");
                    return $msgInfoSaveRec;
                } else if (! is_admin()) {
                    // you must be an database admin to change a public record into a workgroup record
                    errSaveRec("user does not have sufficient authority to change public record to workgroup record");
                    return $msgInfoSaveRec;
                }
            }

            //		$log .= "- updating record ";
            mysql__update("Records", "rec_ID=$recordID", array(
                    "rec_RecTypeID" => $rectype,
                    "rec_URL" => $url,
                    "rec_ScratchPad" => $notes,
                    "rec_OwnerUGrpID" => ($wg||$wg==0?$wg:get_user_id()),
                    "rec_NonOwnerVisibility" => ($vis? $vis:"viewable"),
                    "rec_FlagTemporary" => 0,
                    "rec_Modified" => $now
                ));
            if (mysql_error()) {
                errSaveRec("database record update error - " . mysql_error());
                return $msgInfoSaveRec;
            }
        }

        // public recDetails data
        if ($details) {
            //		$log .= "- inserting details ";
            $dtlIDsByAction = doDetailInsertion($recordID, $details, $rectype, $wg, $nonces, $retitleRecs, $modeImport);
            if (@$dtlIDsByAction['error']){
                array_push($msgInfoSaveRec['error'],$dtlIDsByAction['error']);
                return $msgInfoSaveRec;
            }
        }

        // check that all the required fields are present
        $res = mysql_query("select rst_ID, rst_DetailTypeID, rst_DisplayName".
                            " from defRecStructure".
                            " left join recDetails on dtl_RecID=$recordID and rst_DetailTypeID=dtl_DetailTypeID".
                            " where rst_RecTypeID=$rectype and rst_RequirementType='required' and dtl_ID is null");
        if (mysql_num_rows($res) > 0) {
            //		$log .= "- testing missing detatils ";
            $missed = "";
            while ($row = mysql_fetch_row($res)) {
                $missed = $missed.$row[2]." ";
            }
            /*****DEBUG****///error_log("MISSED ".$missed);
            // at least one missing field
            if($modeImport==2){
                warnSaveRec("record is missing required field(s): ".$missed);
            }else{
                errSaveRec("record is missing required field(s): ".$missed);
                return $msgInfoSaveRec;
            }
        }
        mysql_query("commit");// if we get to here we have a valid save of the core record.

        // calculate title, do an update
        //	$log .= "- filling titlemask ";
        $mask = mysql__select_array("defRecTypes", "rty_TitleMask", "rty_ID=$rectype");  
        $mask = $mask[0];
        
        $title = fill_title_mask($mask, $recordID, $rectype);
        /*****DEBUG****///error_log("DEBUG >>>>>>MASK=".$mask."=".$title);

        if ($title) {
            mysql_query("update Records set rec_Title = '" . mysql_real_escape_string($title) . "' where rec_ID = $recordID");
        }

        // Update memcache: we can do this here since it's only the public data that we cache.
        updateCachedRecord($recordID);

        // private data
        $bkmk = @mysql_fetch_row(mysql_query("select bkm_ID from usrBookmarks where bkm_UGrpID=" . get_user_id() . " and bkm_recID=" . $recordID));
        $bkm_ID = @$bkmk[0];
        if ($personalised) {
            if (! $bkm_ID) {
                // Record is not yet bookmarked, but we want it to be
                mysql_query("insert into usrBookmarks (bkm_Added,bkm_Modified,bkm_UGrpID,bkm_recID) values (now(),now(),".get_user_id().",$recordID)");
                if (mysql_error()) {
                    warnSaveRec("trying to create a bookmark - database error - " . mysql_error());
                }else{
                    $bkm_ID = mysql_insert_id();
                }
            }

            //		$log .= "- updating bookmark ";

            mysql__update("usrBookmarks", "bkm_ID=$bkm_ID", array(
                    //		"pers_notes" => $pnotes,	//saw TODO: need to add code to place this in a personal woot
                    "bkm_Rating" => $rating,
                    "bkm_Modified" => date('Y-m-d H:i:s')
                ));
            //WARNING  tags is assumed to be a complete replacement list for personal tags on this record.
            doTagInsertion($recordID, $bkm_ID, $tags);
        } else if ($bkm_ID) {
            // Record is bookmarked, but the user doesn't want it to be
            //		$log .= "- deleting bookmark ";
            $query = "delete usrBookmarks, usrRecTagLinks ".
            "from usrBookmarks left join usrRecTagLinks on rtl_RecID = bkm_recID ".
            "left join usrTags on tag_ID = rtl_TagID ".
            "where bkm_ID=$bkm_ID and bkm_recID=$recordID and bkm_UGrpID = tag_UGrpID and bkm_UGrpID=" . get_user_id();
            /*****DEBUG****///error_log("saveRecord delete bkmk - q = $query");
            mysql_query($query);
            if (mysql_error()) {
                warnSaveRec("database error while removing bookmark- " . mysql_error());
            }
            //saw TODO: add code to remove other personal data reminders, personal notes (woots), etc.
        }

        doWgTagInsertion($recordID, $wgTags);

        if ($notifyREMOVE  ||  $notifyADD) {
            $notifyIDs = handleNotifications($recordID, $notifyREMOVE, $notifyADD);
        }

        if ($commentREMOVE  ||  $commentMOD  ||  $commentADD) {
            $commentIDs = handleComments($recordID, $commentREMOVE, $commentMOD, $commentADD);
        }


        $rval = array("bibID" => $recordID, "bkmkID" => $bkm_ID, "modified" => $now);
        if ($title) {
            $rval["title"] = $title;
        }
        if (@$dtlIDsByAction) {
            $rval["detail"] = $dtlIDsByAction;
        }
        if (@$notifyIDs) {
            $rval["notify"] = $notifyIDs;
        }
        if (@$commentIDs) {
            $rval["comment"] = $commentIDs;
        }
        if (@$msgInfoSaveRec['warning']) {
            $rval["warning"] = $msgInfoSaveRec['warning'];
        }
        if (@$msgInfoSaveRec['error']) {//should never get here with error set
            $rval["error"] = $msgInfoSaveRec['error'];
        }else{
            //$rval["usageCount"] =
            updateRecTypeUsageCount();
        }
        /*****DEBUG****///error_log($log);

        return $rval;
    }

    /*
    id
    type
    url
    notes
    group
    vis
    pnotes
    rating
    irate //deprecated
    qrate //deprecated
    tags
    wgTags
    details : [t:xxx] => [ [bd:yyy] => val ]*

    */


    function doDetailInsertion($recordID, $details, $recordType, $wg, &$nonces, &$retitleRecs, $modeImport) {
        /* $nonces :  nonce-to-bibID mapping, makes it possible to resolve recDetails values of reference variety */
        /* $retitleRecs : set of records whos titles could be out of date and need recalc */

        /*
        * $details is the form
        *    $details = array("t:1" => array("bd:234463" => "7th Ave"),
        *                      ,,,
        *                     "t:11" => array("0" => "p POINT(-73.951172 40.805661)"));
        * where t:id means detail type id  and bd:id means detail record id
        * new details are array values without a preceeding detail ID as in the last line of this example
        */
        $dtyIDs = array();
        // do a double-pass to grab the expected varieties for each bib-detail-type we encounter, along with other constraints
        foreach ($details as $dtyID => $pairs) {
            if (substr($dtyID, 0, 2) != "t:") continue;
            if (! ($bdtID = intval(substr($dtyID, 2)))) continue;
            array_push($dtyIDs, $bdtID);
        }
        $dtyVarieties = mysql__select_assoc("defDetailTypes", "dty_ID", "dty_Type", "dty_ID in (" . join($dtyIDs, ",") . ")");
        if($modeImport!=2){ //import without check of record type structure
            //TODO saw: need to change this to include min value or perhaps we let it go and allow saving the min across multiple saves.
            $repeats = mysql__select_assoc("defRecStructure", "rst_DetailTypeID", "rst_MaxValues", "rst_RecTypeID=" . $recordType);
//            $repeats = mysql__select_assoc("defRecStructure", "rst_DetailTypeID", "rst_MaxValues", "rst_DetailTypeID in (" . join($dtyIDs, ",") . ") and rst_RecTypeID=" . $recordType);
        }
        /*****DEBUG****///error_log("repeats = ".print_r($repeats,true));
        /*****DEBUG****///error_log("details = ".print_r($details,true));
        $updateIDs = array();
        $updateQueries = array();
        $insertQueryValues = array();
        $badIdInsertQueryValues = array();
        $deleteIDs = array();
        $ignoreIDs = array();
        $translated = array();
        $translatedIDs = array();
        // second pass to divide the work up in to inserts, updates, deletes, translates and ignores
        foreach ($details as $dtyID => $pairs) {
            if (substr($dtyID, 0, 2) != "t:") continue;	// skip any non t: or non type designators
            if (! ($bdtID = intval(substr($dtyID, 2)))) continue;	// invalid (non integer) type id so skip it

            $firstDetail = true;
            foreach ($pairs as $bdID => $val) {

                if (substr($bdID, 0, 3) == "bd:") {// this detail corresponds to an existing recDetails: remember its existing dtl_ID
                    if (! ($bdID = intval(substr($bdID, 3)))) continue; // invalid (non integer) id so skip it
                    // check detail exist for the given record
                    $resDtl = mysql_query("select dtl_DetailTypeID from recDetails where dtl_RecID = $recordID and dtl_ID = $bdID");
                    if (mysql_num_rows($resDtl) == 1){
                        $dtlTypeID = (mysql_fetch_row($resDtl));
                        if ($dtlTypeID[0] != $bdtID){// invalid type supplied so skip and give warning
                            warnSaveRec("invalid detail type supplied $bdtID for existing detail, did not update detail id $bdID ignoring");
                            array_push($ignoreIDs,$bdID);
                            continue;
                        }
                    }else{// no existing dtl id for the given record so change this to insert and give warning.
                        warnSaveRec("detail id $bdID is not part of record $recordID, inserting new detail instead");
                        $oldBdID = $bdID;
                        $bdID = false; // fail test differently to signal translate
                    }
                }else {	// simple case: this is a new detail (no existing dtl_ID) it assumes an array index number

                    if ($bdID != intval($bdID)) {
                            continue;  //bd155  is not equal to 155 while if this is an array index $bdID is numeric
                    }
                    $bdID = "";//signal insert
                }
                $val = trim($val);

                $bdVal = $bdFileID = $bdGeo = "NULL";
                if(false && $modeImport!=2){ //import without check of record type structure
                    //check max limit constraints
                    if ( ! array_key_exists($bdtID, $repeats)) {//if no entry in repeats then extra detail
                        /*****DEBUG****///error_log("non rectype detail type $bdtID found");
                        warnSaveRec("non rectype detail type $bdtID found");
                    }else if (is_numeric($repeats[$bdtID])) {
                        if ($repeats[$bdtID] >= 1) {
                            $repeats[$bdtID] = $repeats[$bdtID] - 1; // decrement to reduce limit count NOTE: assumes that all details are given to save
                        }else if ($firstDetail && $repeats[$bdtID] == 0){ // case of not allowed
                            warnSaveRec("detail type supplied $bdtID is not allowed, marking detail id $bdID for delete");
                            continue;
                        }else{
                            warnSaveRec("hit max for detail type supplied $bdtID , ignoring update detail id $bdID");
                            array_push($ignoreIDs,$bdID);
                        }
                    }
                }


                switch ($dtyVarieties[$bdtID]) {
                    case "integer":  // these should no logner exist, retained for backward compatibility
                                    // bug: non-integer values are not saved
                        if (intval($val)  ||  $val == "0"){
                            $bdVal = intval($val);
                        } else if ($bdID) {//not integer so ignore
                            array_push($ignoreIDs, $bdID);
                            continue;
                        }
                        break;

                    case "float":
                        if (floatval($val)  ||  preg_match('/^0(?:[.]0*)?$/', $val)) {
                            $bdVal = floatval($val);
                        } else if ($bdID){ //not a float so ignore
                            array_push($ignoreIDs, $bdID);
                            continue;
                        }
                        break;

                    case "freetext": case "blocktext":
                    case "date":
                    case "year": case "urlinclude": // these (year and urlinclude) should no logner exist, retained for backward compatibility
                        if (! $val) {//TODO: SAW check if this includes setting a string to "". if so perhaps this is a second pass delete
                            if ($bdID) array_push($ignoreIDs, $bdID);
                            continue;
                        }

                        $bdVal = "'" . mysql_real_escape_string($val) . "'";
                        break;

                    case "boolean":  // these should no logner exist, retained for backward compatibility
                        $bdVal = ($val && $val != "0")? "'true'" : "'false'";
                        break;

                    case "enum":
                    case "relationtype":	//saw TODO: change this to call validateEnumTerm(RectypeID, DetailTypeID) also Term limits
                        // also may need to separate enum from relationtype
                        // validate that the id is for the given detail type.
                        /*if (mysql_num_rows(mysql_query("select trm_ID from defTerms
                        left join defDetailTypes on dty_NativeVocabID = trm_VocabID
                        where dty_ID=$bdtID and trm_ID='".$val."'")) <= 0) {
                        jsonError("invalid enumeration value \"$val\"");
                        }
                        */
                        $bdVal = "'" . $val . "'";
                        break;

                    case "resource":
                        // check that the given resource exists and is fit to point to
                        if ($val[0] == "#") {
                            // a nonce value -- find the appropriate bibID
                            if ($nonces && $nonces[$val]) {
                                $val = $nonces[$val];
                                if (is_array($retitleRecs)) {
                                    array_push($retitleRecs,$val);
                                }
                            }else{
                                errSaveRec("invalid resource reference '".$val."' for detail type '".$bdtID);
                                return array("error" => "recordID = $recordID rectype = $recordType detailtype = $bdtID".
                                    ($bdID ? " detailID = $bdID":""));
                            }
                        }
                        //FIXME :saw  change this to check for superuser and valid recID or valid and viewable record for current user.
                        if (mysql_num_rows(mysql_query("select rec_ID from Records where (! rec_OwnerUGrpID or rec_OwnerUGrpID=$wg) and rec_ID=".intval($val))) <= 0) {
                            //	jsonError("invalid resource #".intval($val));
                        }
                        $bdVal = intval($val);
                        break;

                    case "file":

                        if(is_numeric($val)){  //this is ulf_ID
                            $ulf_ID = intval($val);
                        }else{  // new way - URL or JSON string with file data array (structure similar get_uploaded_file_info)
                            $ulf_ID = register_external($val); //this is URL
                        }

                        if ($ulf_ID==null || mysql_num_rows(mysql_query("select ulf_ID from recUploadedFiles where ulf_ID=".$ulf_ID)) <= 0){
                            errSaveRec("invalid file pointer '".$val."' for detail type '".$bdtID);
                            return array("error" => "recordID = $recordID rectype = $recordType detailtype = $bdtID".
                                ($bdID ? " detailID = $bdID":""));
                        }
                        $bdFileID = intval($ulf_ID);
                        break;

                    case "geo":
                        $geoType = trim(substr($val, 0, 2));
                        $geoVal = trim(substr($val, 2));
                        $res = mysql_query("select geomfromtext('".mysql_real_escape_string($geoVal)."') = 'Bad object'");
                        $row = mysql_fetch_row($res);
                        if ($row[0]) {
                            // bad object!  Go stand in the corner.
                            errSaveRec("invalid geographic value '".$val."' for detail type '".$bdtID);
                            return array("error" => "recordID = $recordID rectype = $recordType detailtype = $bdtID".
                                ($bdID ? " detailID = $bdID":""));
                        }
                        $bdVal = '"' . mysql_real_escape_string($geoType) . '"';
                        $bdGeo = "geomfromtext('".mysql_real_escape_string($geoVal)."')";

                    case "separator":
                    case "relmarker":
                        continue;	//noop since separators and relmarker have no detail values
                        // saw Decide - should we do a relationConstraints check here

                    default:
                        // ???
                        if ($bdID) array_push($ignoreIDs, $bdID);
                        continue;
                }

                if ($bdID) {//danger the following update must restrict to a single detail id.
                    //TODO: saw perhaps we should check value the same?
                    array_push($updateQueries, "update recDetails set dtl_Value=$bdVal, dtl_UploadedFileID=$bdFileID, dtl_Geo=$bdGeo where dtl_ID=$bdID and dtl_DetailTypeID=$bdtID and dtl_RecID=$recordID");
                    array_push($updateIDs, $bdID);
                } else if ($bdID === false) { //bad bdID passed insert detail
                    array_push($badIdInsertQueryValues, "($recordID, $bdtID, $bdVal, $bdFileID, $bdGeo,".($modeImport>0?1:0).")");
                    $translated[$oldBdID] = array('val' => $val,'dtType' => $bdtID);
                    array_push($translatedIDs, $oldBdID);
                }else {
                    array_push($insertQueryValues, "($recordID, $bdtID, $bdVal, $bdFileID, $bdGeo,".($modeImport>0?1:0).")");
                }
                $firstDetail = false;
            }//end details values loop for dty
        }//end dty loop
        //delete all details except the one that are being updated
        $deleteDetailIDsQuery = "select dtl_ID from recDetails where dtl_RecID=$recordID";
        if (count($updateIDs)) $deleteDetailIDsQuery .= " and dtl_ID not in (" . join(",", $updateIDs) . ")";
        if (count($ignoreIDs)) $deleteDetailIDsQuery .= " and dtl_ID not in (" . join(",", $ignoreIDs) . ")";
        $resDel = mysql_query($deleteDetailIDsQuery);
        if (mysql_error()) {
            errSaveRec("db error while finding details to be deleted for record ID ".$recordID." error : ".mysql_error());
            return array("error" => "recordID = $recordID rectype = $recordType ");
        }

        // find details to be deleted
        if (mysql_num_rows($resDel)) {
            while ($row = mysql_fetch_row($resDel)) {
                array_push($deleteIDs, $row[0]);
            }
        }
        /*****DEBUG****///error_log("ignore detail IDs = ".print_r($ignoreIDs,true));
        /*****DEBUG****///error_log("update detail IDs = ".print_r($updateIDs,true));
        /*****DEBUG****///error_log("delete detail IDs = ".print_r($deleteIDs,true));

        /*	$retval is an array of arrays of detail ids
        *	with an array for deletes, updates and inserts
        *	if they occurred
        */
        $retval = array();

        if (count($ignoreIDs)) {
            $retval["ignored"] = $ignoreIDs;
        }
        //update all details to be kept
        if (count($deleteIDs)) {
            $deleteDetailsQuery = "delete from recDetails where dtl_ID in (" . join(",", $deleteIDs) . ")";
//            mysql_query($deleteDetailsQuery);
            if (mysql_error()) {
                errSaveRec("db error while deleteing details (" . join(",", $deleteIDs) . ") for record ID ".$recordID." error : ".mysql_error());
                return array("error" => "recordID = $recordID rectype = $recordType ");
            }
//            $retval["deleted"] = $deleteIDs;
        }

        //update all details to be kept
        if (count($updateQueries)) {
            /*****DEBUG****///error_log("in DoInserts updating details ".print_r($updateQueries,true));
            foreach ($updateQueries as $update) {
                mysql_query($update);
                if (mysql_error()) {
                    errSaveRec("db error while running '" . $update . "' for record ID ".$recordID." error : ".mysql_error());
                    return array("error" => "recordID = $recordID rectype = $recordType ");
                }
            }
            $retval["updated"] = $updateIDs;
        }

        if (count($insertQueryValues)) {//insert all new details
            /*****DEBUG****///error_log("in DoInserts inserting details ".print_r($inserts,true));
            mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, dtl_Geo, dtl_AddedByImport) values " . join(",", $insertQueryValues));
            $first_bd_id = mysql_insert_id();
            if (mysql_error()) {
                errSaveRec("db error while inserting '" . $insertQueryValues . "' for record ID ".$recordID." error : ".mysql_error());
                return array("error" => "recordID = $recordID rectype = $recordType ");
            }
            $retval["inserted"] = range($first_bd_id, $first_bd_id + count($insertQueryValues) - 1);
        }

        if (count($badIdInsertQueryValues)) {//insert all new details
            /*****DEBUG****///error_log("in DoInserts inserting Bad ID details ".print_r($badIdInsertQueryValues,true));
            $j = 0;
            foreach ($badIdInsertQueryValues as $valueSet ) {
                mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, dtl_Geo, dtl_AddedByImport) values " . $valueSet);
                if (mysql_error()) {
                    errSaveRec("db error while inserting '" . $valueSet . "' for record ID ".$recordID." error : ".mysql_error());
                    return array("error" => "recordID = $recordID rectype = $recordType ");
                }
                $new_bdID = mysql_insert_id();
                $translated[$translatedIDs[$j]]['new_bdID'] = $new_bdID;
            }
            $retval["translated"] = $translated;
            $retval["translatedIDs"] = $translatedIDs;
        }
        return $retval;
    }

    function doTagInsertion($recordID, $bkmkID, $tagString) {
        $usrID = get_user_id();
        //get all existing personal tags for this record
        $kwds = mysql__select_array("usrRecTagLinks, usrTags",
            "tag_Text", "rtl_RecID=$recordID and tag_ID=rtl_TagID and tag_UGrpID=$usrID order by rtl_Order, rtl_ID");
        $existingTagString = join(",", $kwds);

        // if tags are already there Nothing to do
        if (strtolower(trim($tagString)) == strtolower(trim($existingTagString))) return;


        $tags = array_filter(array_map("trim", explode(",", str_replace("\\", "/", $tagString))));     // replace backslashes with forwardslashes
        // create a map of this user's personal tags to tagIDs
        $tagMap = mysql__select_assoc("usrTags", "trim(lower(tag_Text))", "tag_ID",
            "tag_UGrpID=".get_user_id()." and tag_Text in (\"".join("\",\"", array_map("mysql_real_escape_string", $tags))."\")");

        //create an ordered list of personal tag ids
        $tag_ids = array();
        foreach ($tags as $tag) {
            if (@$tagMap[strtolower($tag)]) {// existing tag
                $tag_id = $tagMap[strtolower($tag)];
            } else { // new tag so add it
                mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values (\"" . mysql_real_escape_string($tag) . "\", $usrID)");
                $tag_id = mysql_insert_id();
            }
            array_push($tag_ids, $tag_id);
        }

        // Delete all non-workgroup personal tags for this record
        mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags where rtl_RecID=$recordID and tag_ID=rtl_TagID and tag_UGrpID =$usrID");

        if (count($tag_ids) > 0) {
            $query = "";
            for ($i=0; $i < count($tag_ids); ++$i) {
                if ($query) $query .= ", ";
                $query .= "($recordID, ".($i+1).", ".$tag_ids[$i].")";
            }
            $query = "insert into usrRecTagLinks (rtl_RecID, rtl_Order, rtl_TagID) values " . $query;
            mysql_query($query);
        }
    }

    function doWgTagInsertion($recordID, $wgTagIDs) {
        if ($wgTagIDs != ""  &&  ! preg_match("/^\\d+(?:,\\d+)*$/", $wgTagIDs)) return;

        if ($wgTagIDs) {
            mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where rtl_RecID=$recordID and rtl_TagID=tag_ID and tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id()." and tag_ID not in ($wgTagIDs)");
            if (mysql_error()) jsonError("database error - " . mysql_error());
        } else {
            mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where rtl_RecID=$recordID and rtl_TagID=tag_ID and tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id());
            if (mysql_error()) jsonError("database error - " . mysql_error());
            return;
        }

        $existingKeywordIDs = mysql__select_assoc("usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks", "rtl_TagID", "1", "rtl_RecID=$recordID and rtl_TagID=tag_ID and tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id());
        $newKeywordIDs = array();
        foreach (explode(",", $wgTagIDs) as $kwdID) {
            if (! @$existingKeywordIDs[$kwdID]) array_push($newKeywordIDs, $kwdID);
        }

        if ($newKeywordIDs) {
            mysql_query("insert into usrRecTagLinks (rtl_TagID, rtl_RecID) select tag_ID, $recordID from usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id()." and tag_ID in (" . join(",", $newKeywordIDs) . ")");
            if (mysql_error()) jsonError("database error - " . mysql_error());
        }
    }


    function handleNotifications($recordID, $removals, $additions) {
        // removals are encoded as just the notification ID# ... easy!
        $removals = array_map("intval", $removals);
        if ($removals) {
            mysql_query("delete from usrReminders where rem_ID in (" . join(",",$removals) . ") and rem_RecID=$recordID and rem_OwnerUGrpID=" . get_user_id());
        }

        // additions have properties
        // {.user OR .workgroup OR ..email}, .date, .frequency and .message
        $newIDs = array();
        foreach ($additions as $addition) {
            // Input-checking ... this is all done in JS too, so if somebody gets this far they've been doing funny buggers.

            if (! (@$addition["user"] || @$addition["workgroup"] || @$addition["email"])) {
                array_push($newIDs, array("error" => "invalid recipient"));
                continue;
            }

            if (! strtotime(@$addition["date"])) {
                array_push($newIDs, array("error" => "invalid start date"));
                continue;
            }

            if (! preg_match('/^(?:once|daily|weekly|monthly|annually)$/', @$addition["frequency"])) {
                array_push($newIDs, array("error" => "invalid notification frequency"));
                continue;
            }

            $insertVals = array(
                "rem_RecID" => $recordID,
                "rem_OwnerUGrpID" => get_user_id(),
                "rem_StartDate" => date('Y-m-d', strtotime($startDate)),
                "rem_Message" => $addition["message"]
            );

            if (@$addition["user"]) {
                if (! mysql__select_array(USERS_DATABASE.".sysUGrps usr", "usr.ugr_ID", "usr.ugr_ID=".intval($addition["user"])." and usr.ugr_Type = 'User' and  and usr.ugr_Enabled='y'")) {
                    array_push($newIDs, array("error" => "invalid recipient"));
                    continue;
                }
                $insertVals["rem_ToUserID"] = intval($addition["user"]);
            }
            else if (@$addition["workgroup"]) {
                if (! mysql__select_array(USERS_DATABASE.".sysUsrGrpLinks", "ugl_ID", "ugl_GroupID=".intval($addition["workgroup"])." and ugl_UserID=" . get_user_id())) {
                    array_push($newIDs, array("error" => "invalid recipient"));
                    continue;
                }
                $insertVals["rem_ToWorkgroupID"] = intval($addition["workgroup"]);
            }
            else if (@$addition["email"]) {
                $insertVals["rem_ToEmail"] = $addition["email"];
            }
            else {	// can't happen
                array_push($newIDs, array("error" => "invalid recipient"));
                continue;
            }

            mysql__insert("usrReminders", $insertVals);
            array_push($newIDs, array("id" => mysql_insert_id()));
        }

        return $newIDs;
    }


    function handleComments($recordID, $removals, $modifications, $additions) {
        // removals are encoded as just the comments ID# ... easy.
        if ($removals) {
            $removals = array_map("intval", $removals);
            mysql_query("update recThreadedComments set cmt_Deleted=1
                where cmt_OwnerUGrpID=".get_user_id()." and cmt_RecID=$recordID and cmt_ID in (".join(",",$removals).")");
        }

        // modifications have the values
        // .id, .parentComment, .text
        foreach ($modifications as $modification) {
            // note that parentComment (of course) cannot be modified
            mysql__update("recThreadedComments", "cmt_ID=".intval($modification["id"])." and cmt_OwnerUGrpID=".get_user_id(),
                array("cmt_Text" => $modification["text"], "cmt_Modified" => date('Y-m-d H:i:s')));
        }

        // additions are the same as modifications, except that the COMMENT-ID is blank (of course!)
        $newIDs = array();
        foreach ($additions as $addition) {
            $parentID = intval($addition["parentComment"]);

            // do a sanity check first: does this reply make sense?
            $parentTest = $parentID? "cmt_ID=$parentID" : "cmt_ID is null";

            if (! mysql__select_array("Records left join recThreadedComments on rec_ID=cmt_RecID and $parentTest", "rec_ID", "rec_ID=$recordID and $parentTest")) {
                array_push($newIDs, array("error" => "invalid parent comments"));
                continue;
            }

            if (!$parentID || intval($parentID)===0) {
                $parentID = null;
            }

            mysql__insert("recThreadedComments", array("cmt_Text" => $addition["text"], "cmt_Added" => date('Y-m-d H:i:s'), "cmt_OwnerUGrpID" => get_user_id(),
                    "cmt_ParentCmtID" => $parentID, "cmt_RecID" => $recordID));
            array_push($newIDs, array("id" => mysql_insert_id()));
        }

        return $newIDs;
    }

?>
