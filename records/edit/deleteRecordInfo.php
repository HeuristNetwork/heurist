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



/**
 * Remove record and all related info
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

//
// important: transaction/rollback must performed in caller of this function
//
function deleteRecord($id, $needDeleteFile=true) {
	$id = intval($id);


	if (!is_admin()) {

		$res = mysql_query("SELECT rec_AddedByUGrpID, rec_OwnerUGrpID FROM Records WHERE rec_ID = " . $id);
		$row = mysql_fetch_assoc($res);
		$owner = $row["rec_OwnerUGrpID"];

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

			mysql_query('delete from usrReminders where rem_RecID = ' . $id);
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
					$rels_count += $res["bkmk_count"];
					$bkmk_count += $res["rel_count"];
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

?>
