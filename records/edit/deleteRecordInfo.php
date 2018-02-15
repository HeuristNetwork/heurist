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
* Remove record and all related info 
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


require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
require_once(dirname(__FILE__)."/../../records/index/elasticSearchFunctions.php");

//
// important: transaction/rollback must performed in caller of this function
//
function deleteRecord($id, $needDeleteFile=true) {
	$id = intval($id);

	$res = mysql_query("SELECT rec_AddedByUGrpID, rec_OwnerUGrpID, rec_RecTypeID FROM Records WHERE rec_ID = " . $id);
	$row = mysql_fetch_assoc($res);
    $recTypeID = $row["rec_RecTypeID"];
	$owner = $row["rec_OwnerUGrpID"];
        
    if (!is_admin()) {
		if (!($owner == get_user_id() || is_admin('group', $owner))){
			return  array("error" => "user not authorised to delete record");
		}
	}

	// find any references to the record
	if(false)
	{
		$res = mysql_query("SELECT DISTINCT dtl_RecID
		                      FROM defDetailTypes
		                 LEFT JOIN recDetails ON dtl_DetailTypeID = dty_ID
		                     WHERE dty_Type = 'resource'
		                       AND dtl_Value = " . $id);
		$reference_count = mysql_num_rows($res);
		if($reference_count>0){
			return  array("error" => "record cannot be deleted - there are existing references to it");
		}
	}

	$bkmk_count = 0;
	$rels_count = 0;

	$error = null;

	// find any bookmarks of the record
	/* AO:  what we should do with $bkmk_ids?????
	$reference_ids = array();
	while ($row = mysql_fetch_assoc($res)) array_push($reference_ids, $row["dtl_RecID"]);
	$res = mysql_query("select bkm_ID from Records left join usrBookmarks on bkm_recID=rec_ID where rec_ID = " . $id . " and bkm_ID is not null");
	$bkmk_count = mysql_num_rows($res);
	$bkmk_ids = array();
	while ($row = mysql_fetch_assoc($res)) {
		array_push($bkmk_ids, $row["bkm_ID"]);
	}

			$res = mysql_query('select '.USERS_USERNAME_FIELD.' from Records left join usrBookmarks on bkm_recID=rec_ID left join '.USERS_DATABASE.'.'.USERS_TABLE.' on '.USERS_ID_FIELD.'=bkm_UGrpID where rec_ID = ' . $rec_id);
			$bkmk_count = mysql_num_rows($res);
			$bkmk_users = array();
			while ($row = mysql_fetch_assoc($res)) array_push($bkmk_users, $row[USERS_USERNAME_FIELD]);

				 ($bkmk_count == 0  ||
				 ($bkmk_count == 1  &&  $bkmk_users[0] == get_user_username())))) {

	*/

		while (true) {
			//delete uploaded files
            $fd_res = unregister_for_recid2($id, $needDeleteFile);
			if ($fd_res) { $error = "database error - " . $fd_res; break; }

			mysql_query('SET foreign_key_checks = 0');

			//
			mysql_query('delete from recDetails where dtl_RecID = ' . $id);
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }

			//
			mysql_query('delete from Records where rec_ID = ' . $id);
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }
			$deleted = mysql_affected_rows();
            deleteRecordIndexEntry(DATABASE, $recTypeID, $id);
            
			mysql_query('delete from usrReminders where rem_RecID = ' . $id);
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }

            mysql_query('delete from recForwarding where rfw_NewRecID = ' . $id);
            if (mysql_error()) { $error = "database error - " . mysql_error(); break; }
            
			mysql_query('delete from usrRecTagLinks where rtl_RecID = ' . $id);
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }

			mysql_query('delete from recThreadedComments where cmt_RecID = ' . $id);
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }


			//change all woots with title bookmark: to user:
			mysql_query('update woots set woot_Title="user:" where woot_Title in (select concat("boomark:",bkm_ID) as title from usrBookmarks where bkm_recID = ' . $id.')');
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }


			mysql_query('delete from usrBookmarks where bkm_recID = ' . $id);
			if (mysql_error()) { $error = "database error - " . mysql_error(); break; }
			$bkmk_count = mysql_affected_rows();

			//delete from woot
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

			mysql_query('SET foreign_key_checks = 1');

			//remove special kind of record - relationship
			$refs_res = mysql_query('select rec_ID from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records on rec_ID=dtl_RecID where dty_Type="resource" and dtl_Value='.$id.' and rec_RecTypeID='.RT_RELATION);
			while ($row = mysql_fetch_assoc($refs_res)) {
				$res = deleteRecord($row['rec_ID']);
				if( array_key_exists("error", $res) ){
					$error = $res["error"];
					break;
				}else{
					$rels_count += $res["rel_count"];
					$bkmk_count += $res["bkmk_count"];
				}
			}
                
			break;
		}

		if($error==null){
			return array("deleted"=>$id, "bkmk_count"=>$bkmk_count, "rel_count"=>$rels_count);
		}else{
			return  array("error" => $error);
		}
}

/**
* Find all related record IDs for given set record IDs     - see analog in H4 db_recsearch.php 
*
* @param mixed $system
* @param mixed $ids -
* @param mixed $direction -  1 direct/ -1 reverse/ 0 both
*
* @return array of direct and reverse links (record id, relation type (termid), detail id)
*/
function recordSearchRelated($ids, $ids_only=false, $direction=0){

    if(!@$ids){
        return null;
    }
    if(is_array($ids)){
        $ids = implode(",", $ids);
    }
    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }

    $direct = array();
    $reverse = array();
    $headers = array(); //record title and type for main record

    //find all rectitles and record types for main recordset
    if(!$ids_only){
        $query = 'SELECT rec_ID, rec_Title, rec_RecTypeID from Records where rec_ID in ('.$ids.')';
        $res = mysql_query($query);
        if (!$res){
            return null;//$system->addError(HEURIST_DB_ERROR, "Search query error on search related. Query ".$query, $mysqli->error);
        }else{
                while ($row = mysql_fetch_row($res)) {
                    $headers[$row[0]] = array($row[1], $row[2]);   
                }
        }
    }
    
    
    if($direction>=0){
        //find all target related records
        if($ids_only){
            $query = 'SELECT DISTINCT rl_TargetID';    
        }else{
            $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID';
        }
        $query = $query.' FROM recLinks '
            .'where rl_SourceID in ('.$ids.') order by rl_SourceID';

        $res = mysql_query($query);
        if (!$res){
            return null;// $system->addError(HEURIST_DB_ERROR, "Search query error on related records. Query ".$query, $mysqli->error);
        }else{
            if($ids_only){
                while ($row = mysql_fetch_row($res)) {
                    array_push($direct, $row[0]);
                }
            }else{
                while ($row = mysql_fetch_row($res)) {
                    $relation = new stdClass();
                    $relation->recID = intval($row[0]);
                    $relation->targetID = intval($row[1]);
                    $relation->trmID = intval($row[2]);
                    $relation->dtID  = intval($row[3]);
                    $relation->relationID  = intval($row[4]);
                    array_push($direct, $relation);
                }
            }
        }
    }

    if($direction<=0){
        //find all reverse related records
        if($ids_only){
            $query = 'SELECT rl_TargetID, rl_SourceID';    
        }else{
            $query = 'SELECT rl_TargetID, rl_SourceID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID';
        }
        $query = $query.' FROM recLinks '
            .'where rl_TargetID in ('.$ids.') order by rl_TargetID';


        $res = mysql_query($query); //$res = $mysqli->query
        if (!$res){
            return null; //$system->addError(HEURIST_DB_ERROR, "Search query error on reverse related records. Query ".$query, $mysqli->error);
        }else{
            if($ids_only){
                $reverse = array('source'=>array(),'target'=>array());
                while ($row = mysql_fetch_row($res)) {
                    array_push($reverse['source'], $row[0]);
                    array_push($reverse['target'], $row[1]);
                }
            }else{
                while ($row = mysql_fetch_row($res)) {
                        $relation = new stdClass();
                        $relation->recID = intval($row[0]);
                        $relation->sourceID = intval($row[1]);
                        $relation->trmID = intval($row[2]);
                        $relation->dtID  = intval($row[3]);
                        $relation->relationID  = intval($row[4]);
                    array_push($reverse, $relation);
                }
            }
        }
    }

    $response = array("direct"=>$direct, "reverse"=>$reverse, "headers"=>$headers);


    return $response;

}

?>
