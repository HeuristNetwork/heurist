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
    /*
        recordAdd  - create temporary record for given user
        recordSave - Save record
        recordDuplicate - Duplicate record
        recordDelete  
        
        isWrongAccessRights - validate parameter values
        recordCanChangeOwnerwhipAndAccess  - Verifies access right value and is the current user able to change ownership for given record
    
        recordUpdateTitle
        prepareDetails - validate records detail (need to combine with validators in fileParse)
    
    */
    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/db_users.php');
    require_once (dirname(__FILE__).'/db_structure.php');
    require_once (dirname(__FILE__).'/db_recsearch.php');
    require_once (dirname(__FILE__).'/../utilities/titleMask.php');

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
//IMPORTANT !!!!        to implement
//$addRecDefaults = getDefaultOwnerAndibility($_REQUEST);

        $addRecDefaults = @$_SESSION[$system->dbname_full()]["preferences"]['record-add-defaults'];
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
        if(!($ownerid>=0) && isset($userDefaultOwnerGroupID)){
            $ownerid = $userDefaultOwnerGroupID;
        }
        if(!($ownerid>=0)){
            $ownerid = @$sysvals['sys_NewRecOwnerGrpID'];
        }
        if(!($ownerid>=0)){
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

        
        if ($ownerid>0  && !($system->is_admin() || $system->is_member($ownerid))){ 
            $system->addError(HEURIST_REQUEST_DENIED,
                    'Current user does not have sufficient authority to add record with default ownership ('.$ownerid
                    .'). User must be member of the group that will own this record');
            return false;
        }  
            
        if(isWrongAccessRights($system, $access)){
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

            $params = array("q"=>"ids:".$newId, 'detail'=>'complete', "w"=>"e");
            //retrieve new record with structure
            $response = recordSearch($system, $params, true, true);
        }
        return $response;
    }

    /**
    * Save record
    *   1) prepareDetails
    *   2) add or update header
    *   3) remove old details, add new details
    *   4) recordUpdateTitle 
    *
    * @param mixed $system
    * @param mixed $record
    *       [ID:, RecTypeID:, OwnerUGrpID:, NonOwnerVisibility:, AddedByImport:, URL:, FlagTemporary:,
    *               details:
    *            ]
    *    details = array("t:1" => array("bd:234463" => "7th Ave"),
    *                      ,,,
    *                     "t:11" => array("0" => "p POINT (-73.951172 40.805661)"));
    *
    */
    function recordSave($system, $record){

//error_log('captcha '.@$_SESSION["captcha_code"].'   '.@$record['Captcha']);
        //check capture for newsletter subscription
        if (@$record['Captcha'] && @$_SESSION["captcha_code"]){
            
            $is_InValid = (@$_SESSION["captcha_code"] != @$record['Captcha']);
            
            if (@$_SESSION["captcha_code"]){
                unset($_SESSION["captcha_code"]);
            }
            if(@$record['Captcha']){
                unset($record['Captcha']);
            }
            
            if($is_InValid) {
                return $system->addError(HEURIST_UNKNOWN_ERROR, 
                    'Are you a bot? Please enter the correct answer to the challenge question');
            }else{
                if($system->get_user_id()<1){ //if captcha is valid allow   (for ExpertNation - submit feedback)
                    $system->setCurrentUser(array('ugr_ID'=>5, 'ugr_FullName'=>'Guest'));
                }
            }
        }
        
        if ( $system->get_user_id()<1 ) {
            return $system->addError(HEURIST_REQUEST_DENIED);
        }

        $recID = intval(@$record['ID']);
        if ( $recID==0 ) {
            return $system->addError(HEURIST_INVALID_REQUEST, "Record ID is not defined");
        }

        $mysqli = $system->get_mysqli();

        //0 normal, 1 import, 2 - faims import
        $modeImport = @$record['AddedByImport']?intval($record['AddedByImport']):0;

        $is_strict_validation = true;
        if(@$record['no_validation']){
            $is_strict_validation = false;
            unset($record['no_validation']);
        }
        
        $rectype = intval(@$record['RecTypeID']);

        if ($rectype && !dbs_GetRectypeByID($mysqli, $rectype))  {
            return $system->addError(HEURIST_INVALID_REQUEST, "Record type is wrong");
        }

        // recDetails data
        if ( @$record['details'] ) {
            $detailValues = prepareDetails($system, $rectype, $record['details'], ($modeImport<2 && $is_strict_validation));
            if(!$detailValues){
                return $system->getError();
            }
        }  else {
            return $system->addError(HEURIST_INVALID_REQUEST, "Details not defined");
        }
        
        $is_insert = ($recID<1);

        if($is_insert){   // ADD NEW RECORD

            // start transaction
            $keep_autocommit = mysql__begin_transaction($mysqli);

            $response = recordAdd($system, $record, true);
            if($response['status'] == HEURIST_OK){
                $recID = intval($response['data']);
            }else{
                $mysqli->rollback();
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                return $response;
            }

        }else{  //UPDATE EXISTING ONE

            $ownerid = @$record['OwnerUGrpID'];
            $access = @$record['NonOwnerVisibility'];
            $rectypes = array();

            if(!recordCanChangeOwnerwhipAndAccess($system, $recID, $ownerid, $access, $rectypes)){
                return $system->getError();
            }

            // start transaction
            $keep_autocommit = mysql__begin_transaction($mysqli);

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
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                return $system->addError(HEURIST_DB_ERROR, 'Cannot save record', $syserror);
            }
            $stmt->close();

            //delete ALL existing details
            $query = "DELETE FROM recDetails where dtl_RecID=".$recID;
            if(!$mysqli->query($query)){
                $syserror = $mysqli->error;
                $mysqli->rollback();
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                return $system->addError(HEURIST_DB_ERROR, 'Cannot delete old details', $syserror);
            }
        }
        //END HEADER SAVE

        //ADD DETAILS
        $addedByImport = ($modeImport?1:0);

        $query = 'INSERT INTO recDetails '.
        '(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_UploadedFileID, dtl_Geo) '.
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
                if($dtl_Value) $dtl_Value = trim($dtl_Value);
                $dtl_UploadedFileID = @$values['dtl_UploadedFileID'];
                $dtl_Geo = @$values['dtl_Geo'];

                $stmt->bind_param('isis', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo);
                if(!$stmt->execute()){
                    $syserror = $mysqli->error;
                    $mysqli->rollback();if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                    return $system->addError(HEURIST_DB_ERROR, 'Cannot save details', $syserror);
                }

                /*if($dtl_Geo){
                $stmt_geo->bind_param('iss', $dtyID, $dtl_Value, $dtl_Geo);
                $stmt_geo->execute();
                }else{
                $stmt->bind_param('isi', $dtyID, $dtl_Value, $dtl_UploadedFileID);
                $stmt->execute();
                }*/
                
                //add reverce field "Parent Entity" (#247) in child resource record
                if(@$values['dtl_ParentChild']==true){
                    
                    if($system->defineConstant('DT_PARENT_ENTITY')){
                    
                        // $dtl_Value  is id of child record 
                        $res = addReverseChildToParentPointer($mysqli, $dtl_Value, $recID, $addedByImport, false);
                        
                        if($res<0){
                            $syserror = $mysqli->error;
                            $mysqli->rollback();
                            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                            return $system->addError(HEURIST_DB_ERROR, 
                                'Cannot save details. Cannot insert reverse pointer for child record', $syserror);
                        }else if($res!=0){ 
                            //update record title for child record
                            list($child_rectype, $child_title) = mysql__select_row($mysqli,
                                'SELECT rec_RecTypeID, rec_Title FROM Records WHERE rec_ID='
                                .$dtl_Value);
                            recordUpdateTitle($system, $dtl_Value, $child_rectype, $child_title);
                        }
                    }
                }
                
            }
            $stmt->close();
            //$stmt_geo->close();
        }else{
            $syserror = $mysqli->error;
            $mysqli->rollback();
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
            return $system->addError(HEURIST_DB_ERROR, 'Cannot save details', $syserror);
        }

        $newTitle = recordUpdateTitle($system, $recID, $rectype, @$record['RecTitle']);

        if(!$is_insert){
            removeReverseChildToParentPointer($system, $recID, $rectype);    
        }
        
        $mysqli->commit();
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return array("status"=>HEURIST_OK, "data"=> $recID, 'rec_Title'=>$newTitle);
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
    function recordDelete($system, $recids){

        $recids = prepareIds($recids);
        if(count($recids)>0){
            
            $rectypes = array();
        
            //check permission
            foreach ($recids as $recID) {
                $ownerid = null;
                $access = null;
                if(!recordCanChangeOwnerwhipAndAccess($system, $recID, $ownerid, $access, $rectypes)){
                    return $system->getError();
                }
            }
            
            $is_error = false;
            $mysqli = $system->get_mysqli();
            $keep_autocommit = mysql__begin_transaction($mysqli);


            $bkmk_count = 0;
            $rels_count = 0;
            $deleted = array();
            $msg_error = '';
            
            $system->defineConstant('RT_RELATION');
                        
            foreach ($recids as $id) {
                $stat = deleteOneRecord($mysqli, $id, $rectypes[$id]);
                
                if( array_key_exists('error', $stat) ){
                    $msg_error = $stat['error'];
                    break;
                }else{
                    $deleted = array_merge($deleted, $stat['deleted']);
                    $rels_count += $stat['rels_count'];
                    $bkmk_count += $stat['bkmk_count'];
                }
                
            }//foreach
            
            if($msg_error){
                $mysqli->rollback();
                $res = $system->addError(HEURIST_DB_ERROR, 'Cannot delete record. '.$msg_error);
            }else{
                $mysqli->commit();
                $res = array("status"=>HEURIST_OK, 
                    "data"=> array("deleted"=>$deleted, "bkmk_count"=>$bkmk_count, "rels_count"=>$rels_count));
            }
            
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
            return $res;
            
        }else{
            return $system->addError(HEURIST_INVALID_REQUEST, 'Record IDs not defined');
        }
    }
    
    //
    //
    //
    function deleteOneRecord($mysqli, $id, $rectype){
        
        $bkmk_count = 0;
        $rels_count = 0;
        $deleted = array();
        $msg_error = '';
        
        //get list if child records
        $query = 'SELECT dtl_Value FROM recDetails, defRecStructure WHERE dtl_RecID='
        .$id.' AND dtl_DetailTypeID=rst_DetailTypeID AND rst_CreateChildIfRecPtr=1 AND rst_RecTypeID='.$rectype;
        $child_records = mysql__select_list2($mysqli, $query);
        if(count($child_records)>0){
            $query = 'SELECT rec_ID, rec_RecTypeID FROM Records WHERE rec_ID in ('.implode(',',$child_records).')';    
            $child_records = mysql__select_assoc2($mysqli, $query);
        }

        while(true){
                $mysqli->query('SET foreign_key_checks = 0');
                //
                $mysqli->query('delete from recDetails where dtl_RecID = ' . $id);
                if ($mysqli->error) break;

                //
                $mysqli->query('delete from Records where rec_ID = ' . $id);
                if ($mysqli->error) break;
                array_push($deleted, $id);
               
//ELASTIC todo !!!!1   deleteRecordIndexEntry(DATABASE, $recTypeID, $id);
                
                $mysqli->query('delete from usrReminders where rem_RecID = ' . $id);
                if ($mysqli->error) break;

                $mysqli->query('delete from recForwarding where rfw_NewRecID = ' . $id);
                if ($mysqli->error) break;
                
                $mysqli->query('delete from usrRecTagLinks where rtl_RecID = ' . $id);
                if ($mysqli->error) break;

                $mysqli->query('delete from recThreadedComments where cmt_RecID = ' . $id);
                if ($mysqli->error) break;


                //change all woots with title bookmark: to user:
                $mysqli->query('update woots set woot_Title="user:" where woot_Title in (select concat("boomark:",bkm_ID) as title from usrBookmarks where bkm_recID = ' . $id.')');
                if ($mysqli->error) break;


                $mysqli->query('delete from usrBookmarks where bkm_recID = ' . $id);
                if ($mysqli->error) break;
                $bkmk_count = $bkmk_count + $mysqli->affected_rows;

                //delete from woot
                $mysqli->query('delete from woot_ChunkPermissions where wprm_ChunkID in '.
                '(SELECT chunk_ID FROM woots, woot_Chunks where chunk_WootID=woot_ID and woot_Title="record:'.$id.'")');
                if ($mysqli->error) break;

                $mysqli->query('delete from woot_Chunks where chunk_WootID in '.
                '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
                if ($mysqli->error) break;

                $mysqli->query('delete from woot_RecPermissions where wrprm_WootID in '.
                '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
                if ($mysqli->error) break;

                $mysqli->query('delete from woots where woot_Title="record:'.$id.'"');
                if ($mysqli->error) break;

                $mysqli->query('SET foreign_key_checks = 1');

                //remove special kind of record - relationship
                $refs_res = $mysqli->query('select rec_ID from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records on rec_ID=dtl_RecID where dty_Type="resource" and dtl_Value='.$id.' and rec_RecTypeID='.RT_RELATION);
                if($refs_res){
                    while ($row = $refs_res->fetch_assoc()) {
                        $res = deleteOneRecord($mysqli, $row['rec_ID'], RT_RELATION);
                        if( array_key_exists('error', $res) ){
                            $msg_error = $res['error'];
                            break;
                        }else{
                            $deleted = array_merge($deleted, $res['deleted']);
                            $rels_count += $res['rels_count'];
                            $bkmk_count += $res['bkmk_count'];
                        }
                    }
                    $refs_res->close();
                } else {
                    $msg_error = 'Cannot get relationship records';
                    break;
                }
                
                
                if(count($child_records)>0){
                    foreach ($child_records as $recid => $rectypeid) {
                        $res = deleteOneRecord($mysqli, $recid, $rectypeid);
                        if( array_key_exists('error', $res) ){
                            $msg_error = 'Cannot delete child records'.$res['error'];
                            break;
                        }else{
                            $deleted = array_merge($deleted, $res['deleted']);
                            $rels_count += $res['rels_count'];
                            $bkmk_count += $res['bkmk_count'];
                        }
                    }
                }
                break;
        }//while
        
        if($mysqli->error || $msg_error){
            return array("error" => $msg_error.'  '.$mysqli->error);
        }else{
            return array("deleted"=>$deleted, "bkmk_count"=>$bkmk_count, "rels_count"=>$rels_count);
        }
    }
    
    //
    // add/update reverse pointer detail field in child record 
    // return -1 - error, 0 - nothing done, 1 - insert, 2 - update(change parent)
    //
    // $allow_multi_parent - if true means that there can be many parents for child, if true - insert only
    function addReverseChildToParentPointer($mysqli, $child_id, $parent_id, $addedByImport=0, $allow_multi_parent=false){

        $res = 0; 

        if(defined('DT_PARENT_ENTITY')){

            $dtl_ID = -1;

            $query = 'SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID='
            .$child_id.' AND dtl_DetailTypeID='.DT_PARENT_ENTITY;
            $res = $mysqli->query($query);
            if ($res){
                $matches = array();
                while ($row = $res->fetch_row()){
                    if($parent_id == $row[1]){
                        return 0; //exactly the same already exists                           
                    }
                    $dtl_ID = $row[0];
                }
                $res->close();
                $res = ($dtl_ID>0)?2:1;
            }

            if($dtl_ID>0 && !$allow_multi_parent){ //pointer already exists
                $mysqli->query('UPDATE recDetails '.
                        "SET dtl_Value=$parent_id WHERE dtl_ID=$dtl_ID");                    
                if($mysqli->error) $res = -1; //($mysqli->affected_rows>0);
            }else{
                $mysqli->query('INSERT INTO recDetails '.
                    "(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) ".
                    "VALUES ($child_id, ".DT_PARENT_ENTITY.", $parent_id, $addedByImport )");                    
                if(!($mysqli->insert_id>0)) $res=-1;
            }
        }

        return $res;

    }
    
    
    //
    // remove reverse pointer detail field from child record in case there is not direct pointer to child record
    //
    function removeReverseChildToParentPointer($system, $parent_id, $rectype){

       if($system->defineConstant('DT_PARENT_ENTITY')){
           //get list of valid record 
           $query = 'SELECT dtl_Value FROM recDetails, defRecStructure WHERE dtl_RecID='
            .$parent_id.' AND dtl_DetailTypeID=rst_DetailTypeID AND rst_CreateChildIfRecPtr=1 AND rst_RecTypeID='.$rectype;
           
           $mysqli = $system->get_mysqli();
           
           $recids = mysql__select_list2($mysqli, $query);
                   
           $query = 'DELETE FROM recDetails WHERE dtl_Value='.$parent_id.' AND dtl_DetailTypeID='.DT_PARENT_ENTITY;
           
           if(is_array($recids) && count($recids)>0){
                $query = $query.' AND dtl_RecID NOT IN ('.implode(',',$recids).')';
           }
            
           $mysqli->query($query);
       }
    }

    // verify ACCESS RIGHTS -------------
    //
    //
    function isWrongAccessRights($system, $access){
        if ($access=='viewable' || $access=='hidden' || $access=='public' || $access=='pending') {
            return false;
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, 'Non owner visibility is not defined or has wrong value');
            return true;
        }
    }

    /**
    * Verifies access right value and is the current user able to change ownership for given record
    *
    * @param mixed $system
    * @param mixed $recID
    * @param mixed $ownerid
    * @param mixed $access
    *   $rectypes  - return record type of current record
    */
    
    function recordCanChangeOwnerwhipAndAccess($system, $recID, &$ownerid, &$access, &$rectypes)
    {

        
        
        $mysqli = $system->get_mysqli();
        //get current values
        $query = 'select rec_OwnerUGrpID, rec_NonOwnerVisibility, rec_RecTypeID from Records where rec_ID = '.$recID;
        $res = $mysqli->query($query);
        
        if($res){
            $record = $res->fetch_assoc();
            $res->close();
        } else {
            $system->addError(HEURIST_DB_ERROR, 'Cannot get record', $mysqli->error);
            return false;
        }
        
        $ownerid_old = $record["rec_OwnerUGrpID"]; //current ownership
        if(!($ownerid>=0)){
            $ownerid = $record["rec_OwnerUGrpID"];
        }
        
        //1. Can current user edit this record?
        // record is not "everyone" and current user is_admin or itself or member of group
        if ($ownerid_old>0  && !($system->is_admin() || $system->is_member($ownerid_old))){ 

            $system->addError(HEURIST_REQUEST_DENIED,
                    'Current user does not have sufficient authority to change the record ID:'.$recID
                    .'. User must be either the owner or member of the group that owns this record');
            return false;
        }  
        
        //2. Can current user change ownership of this record
        if($ownerid != $ownerid_old && !$system->is_admin()){
            
            $res = true;
            //A. Has rights for current ownership 
            //current user is db admin or itself or admin of currrent onwership group
            if($ownerid_old>0 && ! ($system->is_admin() || $system->has_access($ownerid_old)) ) {  //changing ownership

                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot change ownership. User does not have ownership rights. '
                    .'User must be either database administrator, record owner or administrator or record\'s ownership group');
                $res = false;
            }
            //B. Has rights for new ownership 
            else if ($ownerid>0 && !$system->is_member($ownerid)){
                
                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot set ownership of record to the group without membership in this group');
                $res = false;
            
            //C. Only DB admin can change "Everyone" record to group record
            }else if($ownerid_old == 0 && $ownerid>0) {
                $system->addError(HEURIST_REQUEST_DENIED,
                    'User does not have sufficient authority to change public record to group record');
                $res = false;
            }
            if(!$res){
                return false;
            }
        }


        //---------------------------        
        //change public to pending in case db system preferences
        if($access=='public' && $record["rec_NonOwnerVisibility"]=='public' 
            && $system->get_system('sys_SetPublicToPendingOnEdit')==1){
                $access='pending';
        }else if(!$access){
            $access = $record["rec_NonOwnerVisibility"];
        }
        //if defined and wrong it fails
        if($access && isWrongAccessRights($system, $access))
        {
            return false;
        }

        //return record type for given record id        
        if(is_array($rectypes)){
            $rectypes[$recID] = $record["rec_RecTypeID"];
        }
        
        return $res;


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
    function recordUpdateTitle($system, $recID, $rectype, $recTitleDefault)
    {
        
        $mysqli = $system->get_mysqli();

        if(!(isset($rectype) && $rectype>0)){
            $rectype = mysql__select_value($mysqli, "select rec_RecTypeID from Records where rec_ID=".$recID);
            if(!($rectype>0)){
                $system->addError(HEURIST_DB_ERROR, 'Cannot get record for title mask update. Rec#'.$recID);
                return false;
            }
        }
        
        $mask = mysql__select_value($mysqli,"select rty_TitleMask from defRecTypes where rty_ID=".$rectype);
        if(!$mask){
            $system->addError(HEURIST_DB_ERROR, 'Cannot get title mask for record type', $mysqli->error);
            return false;
        }


        $new_title = TitleMask::fill($recID);
        
        if($new_title==null && $recTitleDefault!=null) $new_title = $recTitleDefault;

        if ($new_title) {
            $query = "UPDATE Records set rec_Title=? where rec_ID=".$recID;

            $stmt = $mysqli->prepare($query);

            $stmt->bind_param('s', $new_title);
            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                $stmt->close();
                $system->addError(HEURIST_DB_ERROR, 'Cannot save record title', $syserror);
                return false;
            }
            $stmt->close();
        }

        return $new_title;
    }


    //function doDetailInsertion($recID, $details, $rectype, $wg, &$nonces, &$retitleRecs, $modeImport)
    /**
    * 
    *
    * @param mixed $mysqli
    * @param mixed $rectype
    * @param mixed $details
    * @param mixed $is_strict - check mandatory fields, resources and terms ID
    */
    function prepareDetails($system, $rectype, $details, $is_strict)
    {
        global $terms;

        /*
        * $details is the form
        *    $details = array("t:1" => array("bd:234463" => "7th Ave"),
        *                      ,,,
        *                     "t:11" => array("0" => "p POINT (-73.951172 40.805661)"));
        * where t:id means detail type id  and bd:id means detail record id
        * new details are array values without a preceeding detail ID as in the last line of this example
        */


        //1. load record structure
        //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence
        //3. delete existing details
        //4. insert new set


        $mysqli = $system->get_mysqli();

        //exlude empty and wrong entries         t:dty_ID:[0:value, 1:value]
        $details2 = array();
        foreach ($details as $dtyID => $pairs) {
            
            if((is_array($pairs) && count($pairs)==0) || $pairs=='') continue; //empty value
            
            if(preg_match("/^t:\\d+$/", $dtyID)){
                $dtyID = substr($dtyID, 2);
            }
            if($dtyID>0){
                $details2[$dtyID] = is_array($pairs)?$pairs:array($pairs);    
            }
        }

        //get list of fieldtypes for all details
        $query = 'SELECT dty_ID, dty_Type FROM defDetailTypes WHERE dty_ID in (' . implode(',', array_keys($details2)) . ')';    
        $det_types = mysql__select_assoc2($mysqli, $query);

        $det_required = array();
        if($is_strict){
            //load list of required details except relmarker
            $query = 'SELECT rst_DetailTypeID, IF((rst_DisplayName=\'\' OR rst_DisplayName IS NULL), dty_Name, rst_DisplayName) as rst_DisplayName '
            .'FROM defRecStructure, defDetailTypes WHERE '
            ."rst_RecTypeID=$rectype and rst_RequirementType='required' and dty_ID=rst_DetailTypeID and dty_Type!='relmarker'";
            $det_required = mysql__select_assoc2($mysqli, $query);
        }

        $det_childpointers =  mysql__select_list($mysqli, "defRecStructure",
                "rst_DetailTypeID",
                "rst_RecTypeID=$rectype and rst_CreateChildIfRecPtr=1");
        
        
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
                $err_msg = '';

                switch ($det_types[$dtyID]) {

                    case "freetext":
                    case "blocktext":
                    case "date":
                        $isValid = (strlen(trim($dtl_Value)) > 0); //preg_match("/\\S/", $dtl_Value);
                        if(!$isValid ) $err_msg = 'It is empty';
                        break;
                    case "float":
                        $isValid = preg_match("/^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+)?)\\s*$/", $dtl_Value);
                        //preg_match('/^0(?:[.]0*)?$/', $dtl_Value)
                        if(!$isValid ) $err_msg = 'Not valid float value';
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
                            }else{
                                $err_msg = '';
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
                                if(!$isValid){
                                    $err_msg = 'Record type '.$rectype_tocheck.' is not valid for specified constraints';
                                }
                            }else{
                                $err_msg = 'Record with specified id does not exist';
                            }
                        }else{
                            $isValid = (intval($dtl_Value)>0);
                            if(!$isValid){
                                $err_msg = 'Record ID is valid integer';
                            }
                        }
                        //this is parent-child resource
                        if($isValid && in_array($dtyID, $det_childpointers)){
                            $dval['dtl_ParentChild'] = true;
                        }
                       
                        break;


                    case "file": //@TODO

                        if(is_numeric($dtl_Value)){  //this is ulf_ID
                            $dtl_UploadedFileID = intval($dtl_Value);

                            //TODO !!! mysql_num_rows(mysql_query("select ulf_ID from recUploadedFiles where ulf_ID=".dtl_UploadedFileID)) <=0 )

                        }else{  // new way - URL or JSON string with file data array (structure similar get_uploaded_file_info)
                            //TODO!!!!!
                            // $dtl_UploadedFileID = register_external($dtl_Value);
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
                        $res = mysql__select_value($mysqli, "select AsWKT(geomfromtext('".addslashes($dtl_Geo)."'))");
                        if($res){
                            $dtl_Value = $geoType;
                            $isValid = true;
                        }
                        /*
                        $res = $mysqli->query("select AsWKT(geomfromtext('".addslashes($dtl_Geo)."'))");
                        if ($res){
                        if($res->fetch_row()){
                        $dtl_Value = $geoType;
                        $isValid = true;
                        }
                        $res->close();
                        }*/
                        break;
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

                    if(@$det_required[$dtyID]!=null){
                        unset($det_required[$dtyID]);
                    }

                    $dval['dtl_Value'] = $dtl_Value;
                    $dval['dtl_UploadedFileID'] = $dtl_UploadedFileID;
                    $dval['dtl_Geo'] = $dtl_Geo;
                    array_push($insertValues, $dval);

                }else{
                    $dt_names = dbs_GetDtLookups();
                    array_push($errorValues, $dtl_Value." is not valid. Field type ".@$dt_names[$det_types[$dtyID]]." (field id: $dtyID). ".$err_msg);
                }

            }//for values
        }//for detail types

        $res = false;

        //there is undefined required details
        if (count($det_required)>0) {

            $system->addError(HEURIST_ERROR, 'Required field'.(count($det_required)>1?'s':'').' not defined: '.implode(',',array_values($det_required)));

        }else if (count($errorValues)>0) {

            $ss = (count($errorValues)>1?'s':'');    
array_push($errorValues,                                                        
'<br><br>Please run Manage > Database > Verify to check for and fix data problems.<br>' 
.'If the problem cannot be fixed, or re-occurs frequently, please email the Heurist development team (support at HeuristNetwork dor org)');
            
            $system->addError(HEURIST_ERROR, 'Encountered invalid value'.$ss
                                                        .' for field'.$ss, $errorValues);

        }else if (count($insertValues)<1) {

            $system->addError(HEURIST_INVALID_REQUEST, "Fields not defined");

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

    //
    //
    //
    function recordDuplicate($system, $id){
        
        if ( $system->get_user_id()<1 ) {
            return $system->addError(HEURIST_REQUEST_DENIED);
        }

        $mysqli = $system->get_mysqli();

        $id = intval($id);
        if ( $id<1 ) {
            return $system->addError(HEURIST_INVALID_REQUEST, "Record ID is not defined");
        }

        $new_owner = 0;

        $owner = mysql__select_value($mysqli, "SELECT rec_OwnerUGrpID FROM Records WHERE rec_ID = ".$id);
        if (!$system->is_member($owner)){   //current user is not member of current group
            $new_owner = $system->get_user_id();
            //return $system->addError(HEURIST_REQUEST_DENIED, 'User not authorised to duplicate record');
        }

        $bkmk_count = 0;
        $rels_count = 0;

        $error = null;
        
        $system->defineConstant('DT_TARGET_RESOURCE');
        $system->defineConstant('DT_PRIMARY_RESOURCE');

        while (true) {

            $mysqli->query('SET foreign_key_checks = 0');

            $new_id = mysql__duplicate_table_record($mysqli, 'Records', 'rec_ID', $id, null);
            //@todo addRecordIndexEntry(DATABASE, $recTypeID, $id);
            
            $query = '';
            if($new_owner>0){
                $query = ', rec_OwnerUGrpID='.$new_owner;
            }
                
            $query = 'UPDATE Records set rec_Modified=NOW()'.$query.' where rec_ID='.$new_id;
            $res = $mysqli->query($query);
            if(!$res){
                $error = 'database error - ' .$mysqli->error;
                break;
            }
            
            
            if(!is_int($new_id)){ $error = $new_id; break; }

            $res = mysql__duplicate_table_record($mysqli, 'recDetails', 'dtl_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }
            
            //@todo duplicate uploaded files
            //$fd_res = unregister_for_recid2($id, $needDeleteFile);
            //if ($fd_res) { $error = "database error - " . $fd_res; break; }
            
            //@todo update details with new file ids


            $res = mysql__duplicate_table_record($mysqli, 'usrReminders', 'rem_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }

            $res = mysql__duplicate_table_record($mysqli, 'usrRecTagLinks', 'rtl_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }

            //$res = mysql__duplicate_table_record($mysqli, 'recThreadedComments', 'cmt_RecID', $id, $new_id);
            //if(!is_int($res)){ $error = $res; break; }

            //@todo change all woots with title bookmark: to user:
            /*
            mysql_query('update woots set woot_Title="user:" where woot_Title in (select concat("boomark:",bkm_ID) as title from usrBookmarks where bkm_recID = ' . $id.')');
            if (mysql_error()) { $error = "database error - " . mysql_error(); break; }
            */

            $res = mysql__duplicate_table_record($mysqli, 'usrBookmarks', 'bkm_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }
            $bkmk_count = $mysqli->affected_rows;

            /*@todo add to woot
            mysql_query('delete from woot_ChunkPermissions where wprm_ChunkID in '.
            '(SELECT chunk_ID FROM woots, woot_Chunks where chunk_WootID=woot_ID and woot_Title="record:'.$id.'")');
            if (mysql_error()) { $error = "database error - " . mysql_error(); break; }

            mysql_query('delete from woot_Chunks where chunk_WootID in '.
            '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
            if (mysql_error()) { $error = "database error - " . mysql_error(); break; }

            mysql_query('delete from woot_RecPermissions where wrprm_WootID in '.
            '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
            if (mysql_error()) { $error = "database error - " . mysql_error(); break; }

            mysql_query('delete from woots where woot_Title="record:'.$id.'"');
            if (mysql_error()) { $error = "database error - " . mysql_error(); break; }
            */

            $mysqli->query('SET foreign_key_checks = 1');

            //add special kind of record - relationships
            $refs_res = mysql__select_list($mysqli, 'recLinks', 'rl_RelationID', 
                '(rl_RelationTypeID is not null) and  (rl_SourceID='.$id.' or rl_TargetID='.$id.')');

            
            foreach ($refs_res as $rel_recid){
                
                $res = recordDuplicate($system, $rel_recid);
                
                if($res && @$res['status']==HEURIST_OK){
                
                $new_rel_recid = @$res['data']['added'];
//error_log($rel_recid.'   '.$new_rel_recid);                
                if($new_rel_recid){
                
                    //change reference to old record id to new one
                    $query = 'UPDATE recDetails set dtl_Value='.$new_id
                    .' where dtl_RecID='.$new_rel_recid
                    .' and dtl_Value='.$id   //old record id
                    .' and (dtl_DetailTypeID='.DT_TARGET_RESOURCE.' or dtl_DetailTypeID='.DT_PRIMARY_RESOURCE.')';
//error_log($query);                  
                    $res = $mysqli->query($query);
                    if(!$res){
                        $error = 'database error - ' .$mysqli->error;
                        break;
                    }else{
                        $rels_count++;   
                    }
                }
                }else{
                   $error = @$res['message'];
                }
            } //foreach

            break;
        }

        if($error==null){
            return array("status"=>HEURIST_OK, 'data'
                        =>array("added"=>$new_id, "bkmk_count"=>$bkmk_count, "rel_count"=>$rels_count));
        }else{
            return $system->addError(HEURIST_DB_ERROR, $error);
        }
        
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

        //terms constraints are not defined in rectype structure anymore
        /*
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
        }
        */

        $dtype = getDetailType($system, $detailtypes, $dtyID);
        if ($dtype) {
            $idx = $detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree'];
            $terms_ids = @$dtype[$idx];
            $idx = $detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
            $terms_none = @$dtype[$idx];
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
