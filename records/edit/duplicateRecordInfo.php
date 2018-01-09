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
* Clone record and all related info 
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


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
require_once(dirname(__FILE__)."/../../records/index/elasticSearchFunctions.php");

    
$mode = @$_REQUEST["mode"];
$mode = 'edit'; // at the current time the only mode is implemented

$recID = @$_REQUEST["recID"];
if(!$recID){
    print "Parameter recID is not defined";
    exit();
}
    
mysql_connection_overwrite(DATABASE);

mysql_query("start transaction");

$out = duplicateRecordInfo($recID);

if( array_key_exists("error", $out) ){
    mysql_query("rollback");
    print $out['error'];
}else{
    mysql_query("commit");
    
    if($mode=="edit"){ //open edit for new duplicated record 
        
        $new_recid = @$out['added'];  
        header('Location: '.HEURIST_BASE_URL.'?fmt=edit&recID='.$new_recid.'&db='.HEURIST_DBNAME);
        
    }
}

//
// important: transaction/rollback must performed in caller of this function
//
function duplicateRecordInfo($id, $needDuplicateFile=true) {
	$id = intval($id);

	$res = mysql_query("SELECT rec_AddedByUGrpID, rec_OwnerUGrpID, rec_RecTypeID FROM Records WHERE rec_ID = " . $id);
	$row = mysql_fetch_assoc($res);
    $recTypeID = $row["rec_RecTypeID"];
	$owner = $row["rec_OwnerUGrpID"];
        
    if (!is_admin()) {
		if (!($owner == get_user_id() || is_admin('group', $owner))){
			return  array("error" => "user not authorised to duplicate record");
		}
	}

	$bkmk_count = 0;
	$rels_count = 0;

	$error = null;

		while (true) {

			mysql_query('SET foreign_key_checks = 0');

            //    
            $new_id = duplicateRecord('Records', 'rec_ID', $id, null);
            //@todo addRecordIndexEntry(DATABASE, $recTypeID, $id);
            
            if(!is_int($new_id)){ $error = $new_id; break; }

            $res = duplicateRecord('recDetails', 'dtl_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }
            
            //@todo duplicate uploaded files
            //$fd_res = unregister_for_recid2($id, $needDeleteFile);
            //if ($fd_res) { $error = "database error - " . $fd_res; break; }
            
            //@todo update details with new file ids


            $res = duplicateRecord('usrReminders', 'rem_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }

            $res = duplicateRecord('usrRecTagLinks', 'rtl_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }

            //$res = duplicateRecord('recThreadedComments', 'cmt_RecID', $id, $new_id);
            //if(!is_int($res)){ $error = $res; break; }

			//@todo change all woots with title bookmark: to user:
			/*
            mysql_query('update woots set woot_Title="user:" where woot_Title in (select concat("boomark:",bkm_ID) as title from usrBookmarks where bkm_recID = ' . $id.')');
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }
            */

            $res = duplicateRecord('usrBookmarks', 'bkm_RecID', $id, $new_id);
            if(!is_int($res)){ $error = $res; break; }
			$bkmk_count = mysql_affected_rows();

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

			mysql_query('SET foreign_key_checks = 1');

			//add special kind of record - relationships
            $query = 'SELECT rl_RelationID FROM recLinks '
                .'where (rl_RelationTypeID is not null) and  (rl_SourceID='.$id.' or rl_TargetID='.$id.')';
//error_log($query);                
			$refs_res = mysql_query($query);
            if ($refs_res){
            while (($row = mysql_fetch_array($refs_res))){
                $rel_recid = $row[0];
                $res = duplicateRecordInfo($rel_recid);
                $new_rel_recid = @$res['added'];
//error_log($rel_recid.'   '.$new_rel_recid);                
                if($new_rel_recid){
                
                    //change reference to old record id to new one
                    $query = 'UPDATE recDetails set dtl_Value='.$new_id
                    .' where dtl_RecID='.$new_rel_recid
                    .' and dtl_Value='.$id
                    .' and (dtl_DetailTypeID='.DT_TARGET_RESOURCE.' or dtl_DetailTypeID='.DT_PRIMARY_RESOURCE.')';
//error_log($query);                  
                    mysql_query($query);
                    $rels_count++;   
                }
            } 
            }

			break;
		}

		if($error==null){
			return array("added"=>$new_id, "bkmk_count"=>$bkmk_count, "rel_count"=>$rels_count);
		}else{
			return  array("error" => $error);
		}
}

//
//
//
function getTableColumns($table){

    $res = mysql_query('DESCRIBE '.$table);
    if (!$res) return NULL;
    $matches = array();
    while (($row = mysql_fetch_array($res))) array_push($matches, $row[0]);
    return $matches;    
}

//
//
//
function duplicateRecord($table, $idfield, $oldid, $newid){

    $columns = getTableColumns($table);    
   
    //in our scheme first column is always id (primary key)
    array_shift($columns);
    
    if($idfield!=null && $newid!=null){
        
        $idx = array_search($idfield, $columns);
        $columns2 = $columns;
        $columns2[$idx] = $newid;
        $columns2 = implode(',',$columns2);
        
    }else{
        $columns2 = implode(',',$columns);
    }
    
    $where = ' where '.$idfield.'='.$oldid;
    
    $columns = implode(',',$columns);
    //
    $query = 'INSERT INTO '.$table.' ('.$columns.') SELECT '.$columns2.' FROM '.$table.' '.$where;
//print $query.'<br>';    
    
    mysql_query($query);
    if (mysql_error()) { 
        return "database error - " . mysql_error(); 
    }else{
        return mysql_insert_id();
    }
            
}

?>
