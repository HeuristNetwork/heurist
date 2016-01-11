<?php

    /**
    * Library to update records
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/db_users.php');
    require_once (dirname(__FILE__).'/db_structure.php');
    require_once (dirname(__FILE__).'/db_recsearch.php');

    $recstructures = array();
    $detailtypes   = array();
    $terms         = null;

    /**
    * Creates temporary record for given user
    */
    function recordAdd($system, $record, $return_id_only=false){

        if ( $system->get_user_id()<1 ) {
            return $system->addError(HEURIST_REQUEST_DENIED);
        }

        $addRecDefaults = @$_SESSION[$system->dbname_full()]["preferences"]['addRecDefaults'];
        if ($addRecDefaults){
            if (@$addRecDefaults[0]){
                $userDefaultRectype = intval($addRecDefaults[0]);
            }
            if (@$addRecDefaults[1]){
                $userDefaultOwnerGroupID = intval($addRecDefaults[1]);
            }
            if (@$addRecDefaults[2]){
                $userDefaultAccess = $addRecDefaults[2];
            }
        }


        $mysqli = $system->get_mysqli();
        $sysvals = $system->get_system();

        if($record){
            $rectype = @$record['RecTypeID'];
            $ownerid = @$record['OwnerUGrpID'];
            $access = @$record['NonOwnerVisibility'];
        }else{
            $rectype = null;
            $ownerid = null;
            $access = null;
        }

        // RECTYPE
        $rectype = intval($rectype);
        if(!$rectype && isset($userDefaultRectype)){
            $rectype = $userDefaultRectype;
        }

        if (!($rectype && dbs_GetRectypeByID($mysqli, $rectype)) ) {
            return $system->addError(HEURIST_INVALID_REQUEST, "Record type not defined or wrong");
        }

        // OWNER -----------
        $ownerid = intval($ownerid);
        if(!$ownerid && isset($userDefaultOwnerGroupID)){
            $ownerid = $userDefaultOwnerGroupID;
        }
        if(!$ownerid){
            $ownerid = @$sysvals['sys_NewRecOwnerGrpID'];
        }
        if(!$ownerid){
            $ownerid = $system->get_user_id();
        }

        // ACCESS -------------
        if(!$access && isset($userDefaultAccess)) {
            $access = $userDefaultAccess;
        }
        if(!$access){
            $access = @$sysvals['sys_NewRecAccess'];
        }
        if(!$access){
            $access = 'viewable';
        }

        if(isWrongOwnership($system, $ownerid) || isWrongAccessRights($access)){
            return $system->getError();
        }


        //ActioN!

        $query = "INSERT INTO Records
        (rec_AddedByUGrpID, rec_RecTypeID, rec_OwnerUGrpID, rec_NonOwnerVisibility,"
        ."rec_URL, rec_ScratchPad, rec_AddedByImport, rec_FlagTemporary) "
        ."VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $mysqli->prepare($query);

        $currentUserId = $system->get_user_id();
        $rec_url  = @$record['URL'];
        $rec_scr  = @$record['ScratchPad'];
        $rec_imp  = (@$record['AddedByImport']?1:0);
        $rec_temp = (@$record['FlagTemporary']?1:0);

        $stmt->bind_param('iiisssii', $currentUserId, $rectype, $ownerid, $access,
            $rec_url, $rec_scr, $rec_imp, $rec_temp);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $syserror = $mysqli->error;
        $stmt->close();


        if(!$newId){

            $response = $system->addError(HEURIST_DB_ERROR, "Cannot add record", $syserror);

        }else if($return_id_only){

            $response = array("status"=>HEURIST_OK, "data"=> $newId);

        }else{

            $params = array("q"=>"ids:".$newId, "w"=>"e");
            //retrieve new record with structure
            $response = recordSearch($system, $params, true, true);
        }
        return $response;
    }

    /**
    * put your comment there...
    *
    * @param mixed $system
    * @param mixed $record
    *       [ID:, RecTypeID:, OwnerUGrpID:, NonOwnerVisibility:, AddedByImport:, URL:, FlagTemporary:,
    *               details:
    *            ]
    *    details = array("t:1" => array("bd:234463" => "7th Ave"),
    *                      ,,,
    *                     "t:11" => array("0" => "p POINT(-73.951172 40.805661)"));
    *
    */
    function recordSave($system, $record){

        if ( $system->get_user_id()<1 ) {
            return $system->addError(HEURIST_REQUEST_DENIED);
        }

        $mysqli = $system->get_mysqli();

        $modeImport = @$record['AddedByImport']?intval($record['AddedByImport']):0;

        $recID = intval(@$record['ID']);
        $rectype = intval(@$record['RecTypeID']);

        if ($rectype && !dbs_GetRectypeByID($mysqli, $rectype))  {
            return $system->addError(HEURIST_INVALID_REQUEST, "Record type is wrong");
        }

        // recDetails data
        if ( @$record['details'] ) {
            $detailValues = prepareDetails($system, $rectype, $record['details'], ($modeImport<2));
            if(!$detailValues){
                return $system->getError();
            }
        }  else {
            return $system->addError(HEURIST_INVALID_REQUEST, "Details not defined");
        }

        if($recID<1){   // ADD NEW RECORD

            // start transaction
            $mysqli->autocommit(FALSE);

            $response = recordAdd($system, $record, true);
            if($response['status'] == HEURIST_OK){
                $recID = intval($response['data']);
            }else{
                $mysqli->rollback();
                return $response;
            }

        }else{  //UPDATE EXISTING ONE

            $ownerid = @$record['OwnerUGrpID'];
            $access = @$record['NonOwnerVisibility'];

            if(!recordCanChangeOwnerwhipAndAccess($system, $recID, $ownerid, $access)){
                return $system->getError();
            }

            // start transaction
            $mysqli->autocommit(FALSE);

            $query = "UPDATE Records set rec_Modified=?, rec_RecTypeID=?, rec_OwnerUGrpID=?, rec_NonOwnerVisibility=?,"
            ."rec_URL=?, rec_ScratchPad=?, rec_FlagTemporary=0 "
            ." where rec_ID=".$recID;

            $stmt = $mysqli->prepare($query);

            $rec_mod = date('Y-m-d H:i:s');
            $rec_url = @$record['URL'];
            $rec_spad = @$record['ScratchPad'];

            $stmt->bind_param('siisss', $rec_mod, $rectype, $ownerid, $access, $rec_url, $rec_spad);

            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                $stmt->close();
                $mysqli->rollback();
                return $system->addError(HEURIST_DB_ERROR, 'Cannot save record', $syserror);
            }
            $stmt->close();

            //delete ALL existing details
            $query = "DELETE FROM recDetails where dtl_RecID=".$recID;
            if(!$mysqli->query($query)){
                $syserror = $mysqli->error;
                $mysqli->rollback();
                return $system->addError(HEURIST_DB_ERROR, 'Cannot delete old details', $syserror);
            }
        }
        //END HEADER SAVE

        //ADD DETAILS
        $addedByImport = ($modeImport?1:0);

        $query = "INSERT INTO recDetails ".
        "(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_UploadedFileID, dtl_Geo) ".
        "VALUES ($recID, ?, ?, $addedByImport, ?, geomfromtext(?) )";
        $stmt = $mysqli->prepare($query);

        /* $query_geo = "INSERT INTO recDetails ".
        "(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_Geo) ".
        "VALUES ($recID, ?, ?, $addedByImport, geomfromtext(?) )";
        $stmt_geo = $mysqli->prepare($query2); */

        if ($stmt) {

            // $stmt->bind_param('isis', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo);
            foreach ($detailValues as $values) {

                $dtyID = $values['dtl_DetailTypeID'];
                $dtl_Value = @$values['dtl_Value'];
                $dtl_UploadedFileID = @$values['dtl_UploadedFileID'];
                $dtl_Geo = @$values['dtl_Geo'];

                $stmt->bind_param('isis', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo);
                if(!$stmt->execute()){
                    $syserror = $mysqli->error;
                    $mysqli->rollback();
                    return $system->addError(HEURIST_DB_ERROR, 'Cannot save details', $syserror);
                }

                /*if($dtl_Geo){
                $stmt_geo->bind_param('iss', $dtyID, $dtl_Value, $dtl_Geo);
                $stmt_geo->execute();
                }else{
                $stmt->bind_param('isi', $dtyID, $dtl_Value, $dtl_UploadedFileID);
                $stmt->execute();
                }*/
            }
            $stmt->close();
            //$stmt_geo->close();
        }else{
            $syserror = $mysqli->error;
            $mysqli->rollback();
            return $system->addError(HEURIST_DB_ERROR, 'Cannot save details', $syserror);
        }

        $mysqli->commit();

        recordUpdateTitle($mysqli, $recID, $rectype);

        return array("status"=>HEURIST_OK, "data"=> $recID);
        /*
        $response = array("status"=>HEURIST_OK,
        "data"=> array(
        "count"=>$num_rows,
        "fields"=>$fields,
        "records"=>$records,
        "rectypes"=>$rectypes,
        "structures"=>$rectype_structures));
        */
    }

    /**
    * @todo - to be implemented
    *
    * @param mixed $mysqli
    * @param mixed $user
    * @param mixed $recids
    */
    function recordDelete($mysqli, $user, $recids){

        $response = array("status"=>HEURIST_UNKNOWN_ERROR, "data"=>"action to be implemented");
    }


    // verify ACCESS RIGHTS -------------
    //
    //
    function isWrongAccessRights($access){
        if (!($access=='viewable' || $access=='hidden' || $access=='public' || $access=='pending')) {
            $system->addError(HEURIST_INVALID_REQUEST, "Access rights not defined or has wrong value");
            return true;
        }else{
            return false;
        }
    }

    /**
    * Verifies user/group id for record ownership and is the current user is member of ownership group
    *
    * @param mixed $system
    * @param mixed $ownerid
    */
    function isWrongOwnership($system, $ownerid)
    {

        if( $system->is_member($ownerid) )
        {
            return false;
        }else{

            $system->addError(HEURIST_REQUEST_DENIED, "Cannot set ownership to group without membership");
            return true;

            /* not used: since only group member can set ownership - it means that it exists
            $mysqli = $system->get_mysqli();
            $user = user_getById($mysqli, $ownerid);
            if (!($user && $user['ugr_ID']>0 & $user['ugr_Enabled']=='y')){
            $system->addError(HEURIST_INVALID_REQUEST, "Owner not defined or not found");
            return true;
            }
            */
        }

        return false;
    }

    /**
    * verifies access right value and is the current user able to change ownership for given record
    *
    * @param mixed $system
    * @param mixed $recID
    * @param mixed $ownerid
    * @param mixed $access
    */
    function recordCanChangeOwnerwhipAndAccess($system, $recID, &$ownerid, &$access)
    {

        //if defined and wrong it fails
        if(($ownerid && isWrongOwnership($system, $ownerid)) ||
            ($access && isWrongAccessRights($access)))
        {
            return false;
        }


        $mysqli = $system->get_mysqli();
        //get current values
        $query = 'select rec_OwnerUGrpID, rec_NonOwnerVisibility from Records where rec_ID = '.$recID;
        $res = $mysqli->query($query);
        if($res){
            $record = $res->fetch_assoc();
            $res->close();
        } else {
            $system->addError(HEURIST_DB_ERROR, 'Cannot get record', $mysqli->error);
            return false;
        }

        //if not defined set current values
        if(!$ownerid){
            $ownerid = $record["rec_OwnerUGrpID"];
        }
        if(!$access){
            $access = $record["rec_NonOwnerVisibility"];
        }

        $ownerid_old = $record["rec_OwnerUGrpID"];
        $res = true;
        if(!($system->is_admin() || $ownerid == $ownerid_old)){

            if($ownerid_old>0 && !$system->is_admin('group', $ownerid_old)) {  //changing ownership

                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot change ownership. User is not a group admin');
                $res = false;

            }else if($ownerid_old == 0) {
                $system->addError(HEURIST_REQUEST_DENIED,
                    'User does not have sufficient authority to change public record to group record');
                $res = false;
            }
        }
        return $res;


    }

    /**
    * update ownership and access rights for given record
    *
    * @param mixed $system
    * @param mixed $recID
    * @param mixed $ownerid
    * @param mixed $access
    */
    function recordSetOwnerwhipAndAccess($system, $recID, $ownerid, $access)
    {

        if(recordCanChangeOwnerwhipAndAccess($system, $recID, $ownerid, $access))
        {
            $query = "UPDATE Records (rec_OwnerUGrpID, rec_NonOwnerVisibility)"
            ." VALUES (?, ?) where rec_ID=".$recID;

            $stmt = $mysqli->prepare($query);

            $stmt->bind_param('is', $ownerid, $access );
            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                $stmt->close();
                return $system->addError(HEURIST_DB_ERROR, 'Cannot save record', $syserror);
            }
            $stmt->close();

        }else{
            return $system->getError();
        }

    }

    /*
    function recordVerifyRequiredFields($mysqli, $recID, $rectype)
    {

    $query = "select rst_ID, rst_DetailTypeID, rst_DisplayName".
    " from defRecStructure".
    " left join recDetails on dtl_RecID=$recID and rst_DetailTypeID=dtl_DetailTypeID".
    " where rst_RecTypeID=$rectype and rst_RequirementType='required' and dtl_ID is null";

    $res = $mysqli->query($query);
    $res->
    if($res){
    $rec = $res->fetch_assoc();
    }
    $res->close();
    if(!isset($rec)){
    return $system->addError(HEURIST_DB_ERROR, 'Cannot get record', $mysqli->error);
    }

    // check that all the required fields are present
    $res = mysql_query("select rst_ID, rst_DetailTypeID, rst_DisplayName".
    " from defRecStructure".
    " left join recDetails on dtl_RecID=$recordID and rst_DetailTypeID=dtl_DetailTypeID".
    " where rst_RecTypeID=$rectype and rst_RequirementType='required' and dtl_ID is null");
    if (mysql_num_rows($res) > 0) {
    //        $log .= "- testing missing detatils ";
    $missed = "";
    while ($row = mysql_fetch_row($res)) {
    $missed = $missed.$row[2]." ";
    }
    // at least one missing field
    if($modeImport==2){
    warnSaveRec("record is missing required field(s): ".$missed);
    }else{
    errSaveRec("record is missing required field(s): ".$missed);
    return $msgInfoSaveRec;
    }
    }
    return true;
    }
    */


    /**
    * calculate title, do an update
    *
    * @param mixed $mysqli
    * @param mixed $recID
    * @param mixed $rectype
    */
    function recordUpdateTitle($mysqli, $recID, $rectype)
    {

        $mask = mysql__select_value($mysqli,"select rty_TitleMask from defRecTypes where rty_ID=".$rectype);
        if(!$mask){
            $system->addError(HEURIST_DB_ERROR, 'Cannot get title mask for record type', $mysqli->error);
            return false;
        }

        $title = null;

        //@TODO $title = fill_title_mask($mask, $recID, $rectype);

        if ($title) {
            $query = "UPDATE Records set rec_Title=? where rec_ID=".$recID;

            $stmt = $mysqli->prepare($query);

            $stmt->bind_param('s', $title);
            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                $stmt->close();
                $system->addError(HEURIST_DB_ERROR, 'Cannot save record title', $syserror);
                return false;
            }
            $stmt->close();
        }

        return true;
    }


    //function doDetailInsertion($recID, $details, $rectype, $wg, &$nonces, &$retitleRecs, $modeImport)
    /**
    * put your comment there...
    *
    * @param mixed $mysqli
    * @param mixed $rectype
    * @param mixed $details
    * @param mixed $is_strict - check resources and terms ID
    */
    function prepareDetails($system, $rectype, $details, $is_strict)
    {
        global $terms;

        /*
        * $details is the form
        *    $details = array("t:1" => array("bd:234463" => "7th Ave"),
        *                      ,,,
        *                     "t:11" => array("0" => "p POINT(-73.951172 40.805661)"));
        * where t:id means detail type id  and bd:id means detail record id
        * new details are array values without a preceeding detail ID as in the last line of this example
        */


        //1. load record structure
        //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence
        //3. delete existing details
        //4. insert new set


        $mysqli = $system->get_mysqli();

        //exlude empty and wrong entries
        $details2 = array();
        foreach ($details as $dtyID => $pairs) {
            if( (!preg_match("/^t:\\d+$/", $dtyID)) || count($pairs)==0 ) continue;

            $dtyID = substr($dtyID, 2);
            $details2[$dtyID] = $pairs;
        }

        //get list of fieldtypes for all details
        $det_types = mysql__select_assoc($mysqli, "defDetailTypes", "dty_ID", "dty_Type",
            "dty_ID in (" . join(array_keys($details2), ",") . ")");

        $det_required = array();
        if($is_strict){
            //load list of required details
            $det_required = mysql__select_assoc($mysqli, "defRecStructure",
                "rst_DetailTypeID", "rst_DisplayName",
                "rst_RecTypeID=$rectype and rst_RequirementType='required'");
        }

        //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence
        $insertValues = array();
        $errorValues = array();
        foreach ($details2 as $dtyID => $values) {
            foreach ($values as $eltID => $dtl_Value) {

                if(strlen(trim($dtl_Value))==0){
                    continue;
                }

                $dval = array('dtl_DetailTypeID'=>$dtyID);

                $dtl_UploadedFileID = null;
                $dtl_Geo = null;
                $isValid = false;

                switch ($det_types[$dtyID]) {

                    case "freetext":
                    case "blocktext":
                    case "date":
                        $isValid = (strlen(trim($dtl_Value)) > 0); //preg_match("/\\S/", $dtl_Value);
                        break;
                    case "float":
                        $isValid = preg_match("/^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+)?)\\s*$/", $dtl_Value);
                        //preg_match('/^0(?:[.]0*)?$/', $dtl_Value)
                        break;
                    case "enum":
                    case "relationtype":

                        if($is_strict){

                            if(!$terms){
                                $terms = dbs_GetTerms($system);
                            }

                            $term_domain = ($det_types[$dtyID]=="enum"?"enum":"relation");

                            if (is_numeric($dtl_Value)){
                                $term_tocheck = $dtl_Value;
                            }else{
                                $term_tocheck = getTermByLabel($dtl_Value, $term_domain);
                            }
                            $isValid = isValidTerm($system, $term_tocheck, $term_domain, $dtyID, $rectype);
                            if($isValid){
                                $dtl_Value = $term_tocheck;
                            }
                        }else{
                            $isValid = (intval($dtl_Value)>0);
                        }

                        break;

                    case "resource":

                        if($is_strict){
                            //check if resource record exists
                            $rectype_tocheck = mysql__select_value($mysqli, "select rec_RecTypeID from Records where rec_ID = ".$dtl_Value); //or dbs_GetRectypeByID from db_strucuture
                            if($rectype_tocheck){
                                //check that this rectype is valid for given detail (constrained pointer)
                                $isValid = isValidRectype($system, $rectype_tocheck, $dtyID, $rectype);
                            }
                        }else{
                            $isValid = (intval($dtl_Value)>0);
                        }

                        break;


                    case "file": //@TODO

                        if(is_numeric($dtl_Value)){  //this is ulf_ID
                            $dtl_UploadedFileID = intval($dtl_Value);

                            //TODO !!! mysql_num_rows(mysql_query("select ulf_ID from recUploadedFiles where ulf_ID=".dtl_UploadedFileID)) <=0 )

                        }else{  // new way - URL or JSON string with file data array (structure similar get_uploaded_file_info)
                            $dtl_UploadedFileID = register_external($dtl_Value);
                        }

                        if ($dtl_UploadedFileID!=null)
                        {
                            $dtl_Value = null;
                            $isValid = true;
                        }

                        break;

                    case "geo":

                        $geoType = trim(substr($dtl_Value, 0, 2));
                        $dtl_Geo = trim(substr($dtl_Value, 2));
                        $res = mysql__select_value($mysqli, "select astext(geomfromtext('".addslashes($dtl_Geo)."'))");
                        if($res){
                            $dtl_Value = $geoType;
                            $isValid = true;
                        }
                        /*
                        $res = $mysqli->query("select astext(geomfromtext('".addslashes($dtl_Geo)."'))");
                        if ($res){
                        if($res->fetch_row()){
                        $dtl_Value = $geoType;
                        $isValid = true;
                        }
                        $res->close();
                        }*/

                        // retained for backward compatibility
                    case "year":
                        $isValid = preg_match("/^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$/i", $dtl_Value);
                        break;
                    case "boolean":

                        $isValid = preg_match("/^(?:yes|true|no|false)$/", $dtl_Value);
                        if($isValid){
                            if ($dtl_Value == "yes"  ||  $dtl_Value == "true")
                                $dtl_Value = "true";
                            else
                                $dtl_Value = "false";
                        }
                        break;
                    case "integer":
                        $isValid = preg_match("/^\\s*-?\\d+\\s*$/", $dtl_Value);
                        break;

                    case "separator":
                    case "relmarker":
                    default:
                        continue;    //noop since separators and relmarker have no detail values
                } //switch

                if($isValid){

                    if(@$det_required[$dtyID]){
                        unset($det_required[$dtyID]);
                    }

                    $dval['dtl_Value'] = $dtl_Value;
                    $dval['dtl_UploadedFileID'] = $dtl_UploadedFileID;
                    $dval['dtl_Geo'] = $dtl_Geo;
                    array_push($insertValues, $dval);

                }else{
                    array_push($errorValues, $dtl_Value." is not valid. Detail type ".$det_types[$dtyID]);
                }

            }//for values
        }//for detail types

        $res = false;

        //there is undefined required details
        if (count($det_required)>0) {

            $system->addError(HEURIST_INVALID_REQUEST, "Required details not defined", $det_required);

        }else if (count($errorValues)>0) {

            $system->addError(HEURIST_INVALID_REQUEST, "Details have wrong values", $errorValues);

        }else if (count($insertValues)<1) {

            $system->addError(HEURIST_INVALID_REQUEST, "Details not defined");

        }else{
            $res = $insertValues;
        }

        return $res;

    } //END prepareDetails

    /**
    * check that rectype is valid for given detail (constrained pointer)
    *
    * @param mixed $mysqli
    * @param mixed $rectype_tocheck  - rectype to be verified
    * @param mixed $dtyID  - detail type id
    * @param mixed $rectype - for rectype
    */
    function isValidRectype($system, $rectype_tocheck, $dtyID, $rectype)
    {
        global $recstructures, $detailtypes;

        $rectype_ids = null;

        $recstr = dbs_GetRectypeStructure($system, $recstructures, $rectype);

        if($recstr && @$recstr['dtFields'][$dtyID])
        {
            $val = $recstr['dtFields'][$dtyID];
            $idx = $recstructures['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
            $rectype_ids = $val[$idx]; //constraint for pointer
        }else{
            //detail type may be not in rectype structure

            $dtype = getDetailType($system, $detailtypes, $dtyID);
            if ($dtype) {
                $idx = $detailtypes['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
                $rectype_ids = @$dtype[$idx];
            }
        }

        if($rectype_ids){
            $allowed_rectypes = explode(",", $rectype_ids);
            return in_array($rectype_tocheck, $allowed_rectypes);
        }

        return true;
    }

    // @todo move terms function in the separate 
    
    //
    // get terms from json string
    //
    function getTermsFromFormat($formattedStringOfTermIDs, $domain) {

        global $terms;

        $validTermIDs = array();
        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return $validTermIDs;
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }
        // Validate termIDs

        $TL = $terms['termsByDomainLookup'][$domain];

        foreach ($termIDs as $trmID) {
            // check that the term valid
            if ( $trmID && array_key_exists($trmID,$TL) && !in_array($trmID, $validTermIDs)){ // valid trm ID
                array_push($validTermIDs,$trmID);
            }
        }
        return $validTermIDs;
    }


    function getTermsByParent($term_id, $domain, $getalldescents = true)
    {
        global $terms;

        $offspring = array();

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $terms['treesByDomain'][$domain];
        }
        foreach($lvl as $sub_term_id=>$childs){

            if($term_id==null || $sub_term_id == $term_id){
                array_push($offspring, $sub_term_id);
                if( $getalldescents && count($childs)>0) {
                    $offspring = array_merge($offspring, getTermsByParent(null, $childs) );
                }
            }
        }

        return $offspring;
    }

    //
    //
    //
    function getTopMostTermParent($term_id, $domain, $topmost=null)
    {
        global $terms;

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $terms['treesByDomain'][$domain];
        }
        foreach($lvl as $sub_term_id=>$childs){

            if($sub_term_id == $term_id){
                return $topmost?$topmost:$term_id;
            }else if( count($childs)>0 ) {

                $res = getTopMostTermParent($term_id, $childs, $topmost?$topmost:$sub_term_id );
                if($res) return $res;
            }
        }

        return null; //not found
    }

    function isValidTerm($system, $term_tocheck, $domain, $dtyID, $rectype)
    {
        global $recstructures, $detailtypes;

        $terms_ids = null;

        $recstr = dbs_GetRectypeStructure($system, $recstructures, $rectype);

        if($recstr && @$recstr['dtFields'][$dtyID])
        {
            $val = $recstr['dtFields'][$dtyID];
            $idx = $recstructures['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
            $terms_ids = $val[$idx];
            $idx = $recstructures['dtFieldNamesToIndex']['rst_TermIDTreeNonSelectableIDs'];
            $terms_none = $val[$idx];
        }else{
            //detail type may be not in rectype structure

            $dtype = getDetailType($system, $detailtypes, $dtyID);
            if ($dtype) {
                $idx = $detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree'];
                $terms_ids = @$dtype[$idx];
                $idx = $detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
                $terms_none = @$dtype[$idx];
            }
        }

        if($terms_ids){

            $allowed_terms = null;

            $terms = getTermsFromFormat($terms_ids, $domain);

            if (($cntTrm = count($terms)) > 0) {
                if ($cntTrm == 1) { //vocabulary
                    $terms = getTermsByParent($terms[0], $domain);
                }else{
                    $nonTerms = getTermsFromFormat($terms_none, $domain);
                    if (count($nonTerms) > 0) {
                        $terms = array_diff($terms, $nonTerms);
                    }
                }
                if (!empty($terms)) {
                    $allowed_terms = $terms;
                }
            }

            return $allowed_terms && in_array($term_tocheck, $allowed_terms);
        }

        return true;

    }

?>
