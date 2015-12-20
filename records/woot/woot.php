<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* Main woot service    
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Woot 
*/


require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

define("WOOT_TABLE", "woots");
define("CHUNK_TABLE", "woot_Chunks");
define("PERMISSION_TABLE", "woot_ChunkPermissions");
define("WOOT_PERMISSION_TABLE", "woot_RecPermissions");



function hasWootReadPermission($wootId) {
	/* Given a woot id, return true if the user has permission to read from it */

	if (is_admin()) { return true; }

	if (is_logged_in()) {
		$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wrprm_WootID=$wootId and
		                   (wrprm_UGrpID=".get_user_id()." or wrprm_GroupID in (".join(",", get_group_ids()).",-1))");
		return (mysql_num_rows($res) > 0);
	} else {
		$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wrprm_WootID=$wootId and wrprm_GroupID = -1");
		return (mysql_num_rows($res) > 0);
	}
}
function hasWootWritePermission($wootId) {
	/* Given a woot id, return true if the user has permission to write to it */

	if (is_admin()) { return true; }

	if (is_logged_in()) {
		$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wrprm_WootID=$wootId and ".
					"(wrprm_UGrpID=".get_user_id()." or wrprm_GroupID in (".join(",", get_group_ids()).",-1))".
						" and wrprm_Type='RW'");
		return (mysql_num_rows($res) > 0);
	} else {
		// non-logged-in users can't edit woots!
		return false;
	}
}


function getReadableChunks($wootId=NULL, $restrictToCurrent=false) {
	/* Given a wootId to which the user has read-access,
	 * return the ids of the chunks which the user can read.
	 * If restrictToCurrent is true, then the chunks should be restricted
	 * to those which have not been superseded by newer versions.
	 * If the wootId is omitted, then the chunks may be sourced from any woot.
	 */

	if (is_admin()) {
		$restriction = "1 ";
	} else if (is_logged_in()) {
		$restriction = "(wprm_UGrpID=".get_user_id()." or wprm_GroupID in (".join(",", get_group_ids()).",-1)) ";
	} else {
		$restriction = "(wprm_GroupID = -1) ";
	}

	if (! $restrictToCurrent) {
		$result = mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_ID=wprm_ChunkID", "wprm_ChunkID", $restriction . ($wootId? " and chunk_WootID=$wootId" : ""));
	}
	else {
		$result =  mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_ID=wprm_ChunkID", "wprm_ChunkID",
		                           "$restriction and chunk_IsLatest" . ($wootId? " and chunk_WootID=$wootId" : "") . " and wprm_ChunkID is not null");
	}
	return $result;
}
function getWritableChunks($wootId=NULL, $restrictToCurrent=false) {
	/* Given a wootId to which the user has write-access,
	 * return the ids of the chunks to which the user can write.
	 * If restrictToCurrent is true, then the chunks should be restricted
	 * to those which have not been superseded by newer versions.
	 * If the wootId is omitted, then the chunks may be sourced from any woot.
	 */

	if (! is_logged_in()) {
		return array();
	}

	$restriction = is_admin()? "1 " : "(wprm_UGrpID=".get_user_id()." or wprm_GroupID in (".join(",", get_group_ids()).",-1)) and wprm_Type='RW' ";
	if (! $restrictToCurrent) {
		$result = mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_ID=wprm_ChunkID",
										"wprm_ChunkID",
		                           $restriction . ($wootId? " and chunk_WootID=$wootId" : ""));
	}
	else {
		$result = mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_ID=wprm_ChunkID",
										"wprm_ChunkID",
										"$restriction and chunk_IsLatest" .
											($wootId? " and chunk_WootID=$wootId" : "") .
											" and wprm_ChunkID is not null");
	}
	return $result;
}



/* LOADING */

/*
	arguments:

	{ title, id }

	returns:

	{	success
		errorType?
		woot? : {	id
					title
					version
					creator
					permissions : {	type
									userId
									userName
									groupId
									groupName
					} +
					chunks : {	number
								text
								modified
								editorId
								ownerId
								permissions : {	type
												userId
												userName
												groupId
												groupName
								} +
					} +
		}
	}
*/
function loadWoot($args) {

	mysql_connection_select(DATABASE);

	$wootTitle = mysql_real_escape_string(@$args["title"]);
	$wootId = intval(@$args["id"]);
	$wootPermissions = array();

	$query = $wootId? "woot_ID=$wootId" : "woot_Title='$wootTitle'";
	$res = mysql_query("select * from ".WOOT_TABLE." where $query");
	if (mysql_num_rows($res) <= 0) {
		if (! is_logged_in()) {
			// non-existent woot, user not logged in
			return(array("success" => false, "errorType" => "woot doesn't exist"));
		}
		if (! @$wootTitle) {
			// non-existent woot, no title specified ... we have no choice really
			return(array("success" => false, "errorType" => "woot doesn't exist"));
		}
		else {
			$wootId = "new";
			$woot = array(
				"woot_ID" => $wootId,
				"woot_Title" => $wootTitle,
				"woot_Version" => 0,
				"woot_CreatorID" => get_user_id()
			);
		}
	}
	else {
		$woot = mysql_fetch_assoc($res);
		$wootId = $woot["woot_ID"];
		if (! hasWootReadPermission($wootId)) {
			return(array("success" => false, "errorType" => "insufficient permissions on woot"));
		}

		$pres = mysql_query("select ".WOOT_PERMISSION_TABLE.".*, a.ugr_Name as Groupname, b.ugr_Name as Username from ".WOOT_PERMISSION_TABLE."
						  left join ".USERS_DATABASE.".sysUGrps a on a.ugr_ID=wrprm_GroupID
						  left join ".USERS_DATABASE.".sysUGrps b on b.ugr_ID=wrprm_UGrpID
							  where wrprm_WootID=".$wootId);
		while ($perm = mysql_fetch_assoc($pres)) {
			array_push($wootPermissions, array("type" => $perm["wrprm_Type"],
										   "userId" => $perm["wrprm_UGrpID"]? $perm["wrprm_UGrpID"] : NULL,
										   "userName" => $perm["wrprm_UGrpID"]? $perm["Username"] : NULL,
										   "groupId" => $perm["wrprm_GroupID"]? $perm["wrprm_GroupID"] : NULL,
										   "groupName" => $perm["wrprm_GroupID"]? $perm["Groupname"] : NULL));
		}
	}


	$chunks = array();
	$chunkIds = getReadableChunks($wootId, /* restrictToCurrent= */ true);
	if ($chunkIds) {
		$res = mysql_query("select * from ".CHUNK_TABLE."
									where chunk_WootID=$wootId and chunk_IsLatest and !chunk_Deleted and chunk_ID in (" . join(",", $chunkIds) . ")
								 order by chunk_DisplayOrder");

		while ($chunkData = @mysql_fetch_assoc($res)) {	// the @ hides the fact that there might not be any chunks for this woot
			$chunk = array(
				"number" => $chunkData["chunk_InsertOrder"],
				"text" => $chunkData["chunk_Text"],
				"modified" => $chunkData["chunk_Modified"],
				"editorId" => $chunkData["chunk_EditorID"],
				"ownerId" => $chunkData["chunk_OwnerID"]
			);

			$permissions = array();
			$pres = mysql_query("select ".PERMISSION_TABLE.".*, a.ugr_Name as Groupname, b.ugr_Name as Username from ".PERMISSION_TABLE."
							  left join ".USERS_DATABASE.".sysUGrps a on a.ugr_ID=wprm_GroupID
							  left join ".USERS_DATABASE.".sysUGrps b on b.ugr_ID=wprm_UGrpID
								  where wprm_ChunkID=".$chunkData["chunk_ID"]);
			while ($perm = mysql_fetch_assoc($pres)) {
				array_push($permissions, array("type" => $perm["wprm_Type"],
											   "userId" => $perm["wprm_UGrpID"]? $perm["wprm_UGrpID"] : NULL,
											   "userName" => $perm["wprm_UGrpID"]? $perm["Username"] : NULL,
											   "groupId" => $perm["wprm_GroupID"]? $perm["wprm_GroupID"] : NULL,
											   "groupName" => $perm["wprm_GroupID"]? $perm["Groupname"] : NULL));
			}
			$chunk["permissions"] = $permissions;

			array_push($chunks, $chunk);
		}
	}

	return(array("success" => true,
				 "woot" => array("id" => $wootId,
								 "title" => $woot["woot_Title"],
								 "version" => $woot["woot_Version"],
								 "creator" => $woot["woot_CreatorID"],
								 "permissions" => $wootPermissions,
								 "chunks" => $chunks)));

}


/* SAVING */

/*
	arguments:

	{	id
		title
		permissions : {	type
						userId
						groupId
		} +
		chunks : {	number?					// order of chunks is important here
					nonce?
					text
					permissions : {	type
									userId
									groupId
					} +
		} +
	}

	returns:

	{	success
		errorType?
		chunkNonce?
		id?
		version?
		chunks? : { (nonce) : (number) + } 		// chunks is a map of chunk nonces to chunk numbers (or null if a chunk has been deleted)
	}

*/
function saveWoot($args) {

	if (! is_logged_in()) {
		return(array("success" => false, "errorType" => "no logged-in user"));
	}

	mysql_connection_overwrite(DATABASE);

	$wootId = intval(@$args["id"]);
	$wootTitle = mysql_real_escape_string(@$args["title"]);

	mysql_query("start transaction");

		if (! $wootId || $wootId === "new") {
			/* This is a new WOOT that hasn't been saved yet */

			if (! $wootTitle) {
				return(array("success" => false, "errorType" => "missing title"));
			}

			mysql__insert(WOOT_TABLE, array(
				"woot_Title" => $wootTitle,
				"woot_Created" => array("now()"),
				"woot_Modified" => array("now()"),
				"woot_Version" => 0,
				"woot_CreatorID" => get_user_id()
			));
			$wootId = mysql_insert_id();
			if (! $wootId) {
				return(array("success" => false, "errorType" => "a woot with the given title already exists"));
			}
			$woot = mysql_fetch_assoc(mysql_query("select * from ".WOOT_TABLE." where woot_ID=$wootId"));
			$woot["permissions"] = $args["permissions"];
			$result = insertWootPermissions($wootId, $woot);
			if ($result["success"] != true) { return($result); }

		}
		else {
			/* We are saving the WOOT -- get a new version number, commit, and then do chunk-wise operations.
			 * Other people can operate on a separate version at the same time.
			 */
			if (! hasWootWritePermission($wootId)) {
				return(array("success" => false, "errorType" => "woot doesn't exist, or insufficient permissions on woot"));
			}

			mysql_query("update ".WOOT_TABLE." set woot_Version=woot_Version+1 where woot_ID=$wootId");
		}
		$res = mysql_query("select * from ".WOOT_TABLE." where woot_ID=$wootId");

	mysql_query("commit and chain");

		$woot = mysql_fetch_assoc($res);
		$version = intval($woot["woot_Version"]);

		$chunkIds = getReadableChunks($wootId, /* restrictToCurrent= */ true);

		$res = mysql_query("select * from ".CHUNK_TABLE."
							 where chunk_WootID=$wootId and chunk_IsLatest and !chunk_Deleted and chunk_ID in (" . join(",", $chunkIds) . ")
						  order by chunk_DisplayOrder");
		$existingVisibleChunks = array();
		while ($chunk = @mysql_fetch_assoc($res)) {	/* The @ takes care of the possibility that there are no chunks in this woot */
			$existingVisibleChunks[$chunk["chunk_InsertOrder"]] = $chunk;
		}

		$incomingChunks = $args["chunks"];

		// Get the current chunk ordering (including the chunks the current user can't actually see)
		$existingChunkOrder = mysql__select_array(CHUNK_TABLE, "chunk_InsertOrder", "chunk_WootID=$wootId and chunk_IsLatest and ! chunk_Deleted order by chunk_DisplayOrder");
		reset($existingChunkOrder);

		// Check that the incoming chunks are in the same order as the existing chunks, otherwise raise an error
		if (count($existingChunkOrder)) {
			foreach ($incomingChunks as $chunk) {
				if (! @$chunk["number"]) { continue; }	// new chunk, doesn't have an ordering yet

				while (current($existingChunkOrder) != $chunk["number"]) {
					if (next($existingChunkOrder) === FALSE) {
						// Ran out of existing chunks
						// The incoming chunk is out of order (you're out of order, the whole court's out of order)
						return(array("success" => false,
									 "errorType" => "invalid chunk ordering",
									 "chunkNonce" => $chunk["nonce"]));
					}
				}
			}
		}

		$chunkNonceToNumber = array();
		$newChunks = array(NULL => array());
		$newChunkCount = 0;

		$firstExistingChunk = NULL;
		$lastExistingChunk = NULL;
		foreach ($incomingChunks as $chunk) {
			$prevChunkId = NULL;

			if (@$chunk["number"]) {
				// If the incoming chunk has a number which doesn't correspond to an existing chunk,
				// then the user has had permissions pulled out from under them (or they're playing funny buggers)
				// Either way, raise an error
				if (! @$existingVisibleChunks[$chunk["number"]]) {
					return(array("success" => false,
								 "errorType" => "chunk permissions have changed",
								 "chunkNonce" => $chunk["nonce"]));
				}
				$chunkNumber = intval($chunk["number"]);

				// Keep track of the position of this (existing) chunk.
				// Any new chunks that occur before the next (existing) chunk will be stored in $newChunks[$lastExistingChunk]
				if (! $firstExistingChunk) { $firstExistingChunk = $chunkNumber; }
				$lastExistingChunk = $chunkNumber;
				$newChunks[$lastExistingChunk] = array();

				if (! @$chunk["unmodified"]) {
					// Chunk exists, and is reported as modified.  Make a new version of it.
					$res = mysql_query("select chunk_ID, chunk_DisplayOrder, chunk_OwnerID from ".CHUNK_TABLE." where chunk_WootID=$wootId and chunk_InsertOrder=$chunkNumber and chunk_IsLatest");
					if (mysql_num_rows($res) != 1) { /* should do something ... do we care? */ }

					$prevChunk = mysql_fetch_assoc($res);
					$prevChunkId = $prevChunk["chunk_ID"];
					$chunkOrder = $prevChunk["chunk_DisplayOrder"];
					$chunkOwner = $prevChunk["chunk_OwnerID"];

					mysql__update(CHUNK_TABLE, "chunk_WootID=$wootId and chunk_InsertOrder=$chunkNumber", array( "chunk_IsLatest" => 0 ));
				}
				else {
					// Chunk exists, but is not modified.  Nothing more to do.
					continue;
				}
			}
			else {
				$res = mysql_query("select max(chunk_InsertOrder) from ".CHUNK_TABLE." where chunk_WootID=$wootId");
				$chunkNumber = @mysql_fetch_row($res);
				$chunkNumber = intval(@$chunkNumber[0]) + 1;
				$chunkOrder = 0;	// chunk order will be overridden anyway since there is a new chunk to take care of
				$chunkOwner = get_user_id();

				array_push($newChunks[$lastExistingChunk], $chunkNumber);
				++$newChunkCount;
			}

			$chunkDeleted = preg_match('/^\s*$/', $chunk["text"]);

			mysql__insert(CHUNK_TABLE, array(
				"chunk_WootID" => $wootId,
				"chunk_InsertOrder" => $chunkNumber,
				"chunk_Version" => $version,
				"chunk_Text" => $chunk["text"],
				"chunk_IsLatest" => 1,
				"chunk_DisplayOrder" => $chunkOrder,
				"chunk_Modified" => array("now()"),
				"chunk_OwnerID" => $chunkOwner,
				"chunk_EditorID" => get_user_id(),
				"chunk_Deleted" => $chunkDeleted
			));
			$chunkId = mysql_insert_id();

			if (! $chunkDeleted) {
				if ($chunkOwner == get_user_id()  ||  is_admin()) {
					// only the owner (or an admin) can change the permissions
					$result = insertPermissions($chunkId, $chunk, $woot["woot_CreatorID"]);
					if ($result["success"] != true) { return($result); }
				}
				else {
					// copy the permissions from the previous version of the chunk
					mysql_query("insert into ".PERMISSION_TABLE."
								 (wprm_ChunkID, wprm_UGrpID, wprm_GroupID, wprm_Type, wprm_CreatorID, wprm_Created)
						   select distinct $chunkId, wprm_UGrpID, wprm_GroupID, wprm_Type, wprm_CreatorID, wprm_Created
							 from ".PERMISSION_TABLE." where wprm_ChunkID=$prevChunkId");
				}

				if (@$chunk["nonce"]) {
					// if the client hasn't specified a nonce they're obviously not interested in the resulting chunk number
					$chunkNonceToNumber[$chunk["nonce"]] = $chunkNumber;
				}
			}
			else {
				if ($chunk["nonce"]) {
					$chunkNonceToNumber[$chunk["nonce"]] = NULL;	// blast away the existing number for this chunk
				}
			}
		}

		if ($newChunkCount) {
			// New chunks have been inserted.
			// Make a merged list of existing chunks and newly inserted chunks, then update their ordering
			$allChunks = array();
			foreach ($existingChunkOrder as $existingChunkNumber) {
				// Consider chunks (A, B*, C*, D, E*) where B*, C* and E* are new chunks, and A and D are existing chunks.
				// In the merged list, B* and C* will directly follow A, and E* will directly follow D.
				// So, given existingChunkOrder (X, A, Y, D, Z) and chunkNonceToNumber (A, B*, C*, D, E*),
				// allChunks becomes (X, A, B*, C*, Y, D, E*, Z)

				if ($existingChunkNumber == $firstExistingChunk  &&  count($newChunks[NULL])) {
					// This is the first chunk that the user can see, and there are new chunks to add before it.
					$allChunks = array_merge($allChunks, $newChunks[NULL]);
				}

				array_push($allChunks, $existingChunkNumber);

				if (count(@$newChunks[$existingChunkNumber])) {
					// There are new chunks to add directly after this chunk
					$allChunks = array_merge($allChunks, $newChunks[$existingChunkNumber]);
				}
			}

			if (! $firstExistingChunk  &&  count($newChunks[NULL])) {
				// Okay, there were no existing chunks that the user could see ... add any new chunks at the end
				$allChunks = array_merge($allChunks, $newChunks[NULL]);
			}

			for ($i=0; $i < count($allChunks); ++$i) {
				$order = $i+1;
				$chunkNumber = $allChunks[$i];
				mysql_query("update ".CHUNK_TABLE." set chunk_DisplayOrder=$order
							  where chunk_WootID=$wootId and chunk_InsertOrder=$chunkNumber and chunk_IsLatest");
			}
		}

	mysql_query("commit");

	return(array("success" => true, "id" => $wootId, "version" => $version, "chunks" => $chunkNonceToNumber));
}


function insertPermissions($chunkId, &$chunk, $creatorId) {
	$myGroups = array(-1 => true);
	foreach (get_group_ids() as $groupId) {
		$myGroups[$groupId] = true;
	}

	$userHasReadWriteAccess = false;

	$insertValues = array();
	foreach ($chunk["permissions"] as $permission) {
		$permission["type"] = strtoupper($permission["type"]);
		if (! preg_match('/^(RW|RO)$/', $permission["type"])  ||
		    ! (@$permission["userId"]  ||  @$permission["groupId"])) {
			return(array("success" => false,
                         "errorType" => "invalid chunk permissions",
                         "chunkNonce" => $chunk["nonce"]));
		}
		if (@$permission["userId"] == -1) {	// automagic reference to userId -1 is converted to the owner's id
			$permission["userId"] = $creatorId;
		}

		if (@$permission["groupId"]) {
			if (! @$myGroups[$permission["groupId"]]) {
				// trying to set a permission for a group we're not in ... ignore it
				continue;
			}
			if ($permission["type"] == "RW") $userHasReadWriteAccess = true;
		}
		if (@$permission["userId"] == get_user_id()  &&  $permission["type"] == "RW") {
			$userHasReadWriteAccess = true;
		}

		$insertValues[@$permission["userId"] . "," . @$permission["groupId"]] = array(
			"wprm_ChunkID" => $chunkId,
			"wprm_UGrpID" => @$permission["userId"]? $permission["userId"] : 0,
			"wprm_GroupID" => @$permission["groupId"]? $permission["groupId"] : 0,
			"wprm_Type" => $permission["type"],
			"wprm_CreatorID" => get_user_id(),
			"wprm_Created" => array("now()")
		);
	}
	foreach ($insertValues as $values) {
		mysql__insert(PERMISSION_TABLE, $values);
	}

	if (! $userHasReadWriteAccess  &&  ! is_admin()) {
		// Woah, hang on ... is the user REALLY trying to lock themselves out of this chunk?  Don't let them do THAT.
		mysql__insert(PERMISSION_TABLE, array(
			"wprm_ChunkID" => $chunkId,
			"wprm_UGrpID" => get_user_id(),
			"wprm_Type" => "RW",
			"wprm_CreatorID" => get_user_id(),
			"wprm_Created" => array("now()")
		));
	}

	return(array("success" => true));
}

function insertWootPermissions($wootId, &$woot) {
	$myGroups = array(-1 => true);
	foreach (get_group_ids() as $groupId) {
		$myGroups[$groupId] = true;
	}

	$userHasReadWriteAccess = false;

	$insertValues = array();
	foreach ($woot["permissions"] as $permission) {
		$permission["type"] = strtoupper($permission["type"]);
		if (! preg_match('/^(RW|RO)$/', $permission["type"])  ||
		    ! (@$permission["userId"]  ||  @$permission["groupId"])) {
			return(array("success" => false, "errorType" => "invalid woot permissions"));
		}
		if (@$permission["userId"] == -1) {	// automagic reference to userId -1 is converted to the owner's id
			$permission["userId"] = $woot["woot_CreatorID"];
		}

		if (@$permission["groupId"]) {
			if (! @$myGroups[$permission["groupId"]]) {
				// trying to set a permission for a group we're not in ... ignore it
				continue;
			}
			if ($permission["type"] == "RW") $userHasReadWriteAccess = true;
		}
		if (@$permission["userId"] == get_user_id()  &&  $permission["type"] == "RW") {
			$userHasReadWriteAccess = true;
		}

		$insertValues[@$permission["userId"] . "," . @$permission["groupId"]] = array(
			"wrprm_WootID" => $wootId,
			"wrprm_UGrpID" => @$permission["userId"]? $permission["userId"] : 0,
			"wrprm_GroupID" => @$permission["groupId"]? $permission["groupId"] : 0,
			"wrprm_Type" => $permission["type"],
			"wrprm_CreatorID" => get_user_id(),
			"wrprm_Created" => array("now()")
		);
	}
	foreach ($insertValues as $values) {
		mysql__insert(WOOT_PERMISSION_TABLE, $values);
	}

	if (! $userHasReadWriteAccess  &&  ! is_admin()) {
		// Woah, hang on ... is the user REALLY trying to lock themselves out of this woot?  Don't let them do THAT.
		mysql__insert(WOOT_PERMISSION_TABLE, array(
			"wrprm_WootID" => $wootId,
			"wrprm_UGrpID" => get_user_id(),
			"wrprm_Type" => "RW",
			"wrprm_CreatorID" => get_user_id(),
			"wrprm_Created" => array("now()")
		));
	}

	return(array("success" => true));
}


/* SEARCHING */

function searchWoots($args) {

	mysql_connection_select(DATABASE);

	$text_search = getTextSearch($args["q"]);
	if (! $text_search) {
		return(array("success" => false, "errorType" => "invalid query"));
	}

	$res = mysql_query("select distinct woot_ID, woot_Title, woot_Version
						  from ".WOOT_TABLE.",".CHUNK_TABLE."
						 where woot_ID=chunk_WootID and chunk_IsLatest and !chunk_Deleted
							   and " . $text_search . "
							   and chunk_ID in (" . join(",", getReadableChunks(NULL, true)) . ")");

	$woots = array();
	while ($woot = mysql_fetch_assoc($res)) {
		array_push($woots, array(
			"id" => $woot["woot_ID"],
			"version" => $woot["woot_Version"],
			"title" => $woot["woot_Title"]
		));
	}

	return(array("success" => true, "woots" => $woots));
}


function getTextSearch($q) {
	$searchTerms = explode(' ', trim(preg_replace('/[^-a-zA-Z0-9_]+/', ' ', $q)));
	$TEXT_SEARCH = "";
	$NEGATIVE_TEXT_SEARCH = "";
	foreach ($searchTerms as $term) {
		if (substr($term, 0, 1) != "-") {
			if ($TEXT_SEARCH) { $TEXT_SEARCH .= " or "; }
			$TEXT_SEARCH .= "chunk_Text like '%" . mysql_real_escape_string($term) . "%'";
		}
		else {
			if ($NEGATIVE_TEXT_SEARCH) { $NEGATIVE_TEXT_SEARCH .= " or "; }
			$NEGATIVE_TEXT_SEARCH .= "chunk_Text like '%" . mysql_real_escape_string(substr($term, 1)) . "%'";
		}
	}
	if ($TEXT_SEARCH) {
		if ($NEGATIVE_TEXT_SEARCH) {
			$TEXT_SEARCH = "($TEXT_SEARCH) and not ($NEGATIVE_TEXT_SEARCH)";
		}
		else {
			$TEXT_SEARCH = "($TEXT_SEARCH)";
		}
	}
	else {
		if ($NEGATIVE_TEXT_SEARCH) {
			$TEXT_SEARCH = "not ($NEGATIVE_TEXT_SEARCH)";
		}
		else {
			return NULL;
		}
	}

	return $TEXT_SEARCH;
}


?>
