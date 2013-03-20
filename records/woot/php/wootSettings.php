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
* util to get woot information
*
* @author      Kim Jackson
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Woot
*/



define("WOOT_TABLE", "woots");
define("CHUNK_TABLE", "woot_Chunks");
define("PERMISSION_TABLE", "wootChunkPermissions");
define("WOOT_PERMISSION_TABLE", "woot_RecPermissions");


function hasWootReadPermission($wootId) {
	/* Given a woot id, return true if the user has permission to read from it */

	if (is_admin()) { return true; }

	$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wrprm_WootID=$wootId and
	                   (wrprm_UGrpID=".get_user_id()." or wrprm_GroupID in (".join(",", get_group_ids()).",-1))");
	return (mysql_num_rows($res) > 0);
}
function hasWootWritePermission($wootId) {
	/* Given a woot id, return true if the user has permission to write to it */

	if (is_admin()) { return true; }

	$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wrprm_WootID=$wootId and
	                   (wrprm_UGrpID=".get_user_id()." or wrprm_GroupID in (".join(",", get_group_ids()).",-1)) and wrprm_Type='RW'");
	return (mysql_num_rows($res) > 0);
}


function getReadableChunks($wootId=NULL, $restrictToCurrent=false) {
	/* Given a wootId to which the user has read-access,
	 * return the ids of the chunks which the user can read.
	 * If restrictToCurrent is true, then the chunks should be restricted
	 * to those which have not been superseded by newer versions.
	 * If the wootId is omitted, then the chunks may be sourced from any woot.
	 */

	$restriction = is_admin()? "1 " : "(wprm_UGrpID=".get_user_id()." or wprm_GroupID in (".join(",", get_group_ids()).",-1)) ";
	if (! $restrictToCurrent) {
		return mysql__select_array(PERMISSION_TABLE, "wprm_ChunkID", $restriction . ($wootId? " and chunk_WootID=$wootId" : ""));
	}
	else {
		return mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_ID=wprm_ChunkID", "wprm_ChunkID",
		                           "$restriction and chunk_IsLatest" . ($wootId? " and chunk_WootID=$wootId" : "") . " and wprm_ChunkID is not null");
	}
}
function getWritableChunks($wootId=NULL, $restrictToCurrent=false) {
	/* Given a wootId to which the user has write-access,
	 * return the ids of the chunks to which the user can write.
	 * If restrictToCurrent is true, then the chunks should be restricted
	 * to those which have not been superseded by newer versions.
	 * If the wootId is omitted, then the chunks may be sourced from any woot.
	 */

	$restriction = is_admin()? "1 " : "(wprm_UGrpID=".get_user_id()." or wprm_GroupID in (".join(",", get_group_ids()).",-1)) and wprm_Type='RW' ";
	if (! $restrictToCurrent) {
		return mysql__select_array(PERMISSION_TABLE, "wprm_ChunkID",
		                           $restriction . ($wootId? " and chunk_WootID=$wootId" : ""));
	}
	else {
		return mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_ID=wprm_ChunkID", "wprm_ChunkID",
		                           "$restriction and chunk_IsLatest" . ($wootId? " and chunk_WootID=$wootId" : "") . " and wprm_ChunkID is not null");
	}
}

function jsonReturn($value, $commit=false) {
	print json_encode($value);
	mysql_query($commit? "commit" : "rollback");
	exit();
}


function jsonError($message) {
        mysql_query("rollback");
        print "{\"error\":\"" . addslashes($message) . "\"}";
        exit(0);
}

?>
