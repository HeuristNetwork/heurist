<?php

require_once(dirname(__FILE__)."/../../common/connect/db.php");

define("WOOT_TABLE", "woots");
define("CHUNK_TABLE", "woot_chunks");
define("PERMISSION_TABLE", "woot_chunk_permissions");
define("WOOT_PERMISSION_TABLE", "woot_permissions");



function hasWootReadPermission($wootId) {
	/* Given a woot id, return true if the user has permission to read from it */

	if (is_admin()) { return true; }

	if (is_logged_in()) {
		$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wperm_woot_id=$wootId and
		                   (wperm_user_id=".get_user_id()." or wperm_group_id in (".join(",", get_group_ids()).",-1))");
		return (mysql_num_rows($res) > 0);
	} else {
		$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wperm_woot_id=$wootId and wperm_group_id = -1");
		return (mysql_num_rows($res) > 0);
	}
}
function hasWootWritePermission($wootId) {
	/* Given a woot id, return true if the user has permission to write to it */

	if (is_admin()) { return true; }

	if (is_logged_in()) {
		$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wperm_woot_id=$wootId and
		                   (wperm_user_id=".get_user_id()." or wperm_group_id in (".join(",", get_group_ids()).",-1)) and wperm_type='RW'");
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
		$restriction = "(perm_user_id=".get_user_id()." or perm_group_id in (".join(",", get_group_ids()).",-1)) ";
	} else {
		$restriction = "(perm_group_id = -1) ";
	}

	if (! $restrictToCurrent) {
		return mysql__select_array(PERMISSION_TABLE, "perm_chunk_id", $restriction . ($wootId? " and chunk_woot_id=$wootId" : ""));
	}
	else {
		return mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_id=perm_chunk_id", "perm_chunk_id",
		                           "$restriction and chunk_is_latest" . ($wootId? " and chunk_woot_id=$wootId" : "") . " and perm_chunk_id is not null");
	}
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

	$restriction = is_admin()? "1 " : "(perm_user_id=".get_user_id()." or perm_group_id in (".join(",", get_group_ids()).",-1)) and perm_type='RW' ";
	if (! $restrictToCurrent) {
		return mysql__select_array(PERMISSION_TABLE, "perm_chunk_id",
		                           $restriction . ($wootId? " and chunk_woot_id=$wootId" : ""));
	}
	else {
		return mysql__select_array(CHUNK_TABLE . " left join " . PERMISSION_TABLE . " on chunk_id=perm_chunk_id", "perm_chunk_id",
		                           "$restriction and chunk_is_latest" . ($wootId? " and chunk_woot_id=$wootId" : "") . " and perm_chunk_id is not null");
	}
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

	mysql_connection_db_select(DATABASE);

	$wootTitle = addslashes(@$args["title"]);
	$wootId = intval(@$args["id"]);
	$wootPermissions = array();

	$query = $wootId? "woot_id=$wootId" : "woot_title='$wootTitle'";
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
				"woot_id" => $wootId,
				"woot_title" => $wootTitle,
				"woot_version" => 0,
				"woot_creator" => get_user_id()
			);
		}
	}
	else {
		$woot = mysql_fetch_assoc($res);
		$wootId = $woot["woot_id"];
		if (! hasWootReadPermission($wootId)) {
			return(array("success" => false, "errorType" => "insufficient permissions on woot"));
		}

		$pres = mysql_query("select ".WOOT_PERMISSION_TABLE.".*, grp_name, Username from ".WOOT_PERMISSION_TABLE."
						  left join ".USERS_DATABASE.".Groups on grp_id=wperm_group_id left join ".USERS_DATABASE.".Users on Id=wperm_user_id
							  where wperm_woot_id=".$wootId);
		while ($perm = mysql_fetch_assoc($pres)) {
			array_push($wootPermissions, array("type" => $perm["wperm_type"],
										   "userId" => $perm["wperm_user_id"]? $perm["wperm_user_id"] : NULL,
										   "userName" => $perm["wperm_user_id"]? $perm["Username"] : NULL,
										   "groupId" => $perm["wperm_group_id"]? $perm["wperm_group_id"] : NULL,
										   "groupName" => $perm["wperm_group_id"]? $perm["grp_name"] : NULL));
		}
	}


	$chunks = array();
	$chunkIds = getReadableChunks($wootId, /* restrictToCurrent= */ true);
	if ($chunkIds) {
		$res = mysql_query("select * from ".CHUNK_TABLE."
									where chunk_woot_id=$wootId and chunk_is_latest and !chunk_deleted and chunk_id in (" . join(",", $chunkIds) . ")
								 order by chunk_order");
		$res = mysql_query("select * from ".CHUNK_TABLE."
									where chunk_woot_id=$wootId and chunk_is_latest and !chunk_deleted and chunk_id in (" . join(",", $chunkIds) . ")
								 order by chunk_order");
		while ($chunkData = @mysql_fetch_assoc($res)) {	// the @ hides the fact that there might not be any chunks for this woot
			$chunk = array(
				"number" => $chunkData["chunk_number"],
				"text" => $chunkData["chunk_text"],
				"modified" => $chunkData["chunk_modified"],
				"editorId" => $chunkData["chunk_editor"],
				"ownerId" => $chunkData["chunk_owner"]
			);

			$permissions = array();
			$pres = mysql_query("select ".PERMISSION_TABLE.".*, grp_name, Username from ".PERMISSION_TABLE."
							  left join ".USERS_DATABASE.".Groups on grp_id=perm_group_id left join ".USERS_DATABASE.".Users on Id=perm_user_id
								  where perm_chunk_id=".$chunkData["chunk_id"]);
			while ($perm = mysql_fetch_assoc($pres)) {
				array_push($permissions, array("type" => $perm["perm_type"],
											   "userId" => $perm["perm_user_id"]? $perm["perm_user_id"] : NULL,
											   "userName" => $perm["perm_user_id"]? $perm["Username"] : NULL,
											   "groupId" => $perm["perm_group_id"]? $perm["perm_group_id"] : NULL,
											   "groupName" => $perm["perm_group_id"]? $perm["grp_name"] : NULL));
			}
			$chunk["permissions"] = $permissions;

			array_push($chunks, $chunk);
		}
	}

	return(array("success" => true,
				 "woot" => array("id" => $wootId,
								 "title" => $woot["woot_title"],
								 "version" => $woot["woot_version"],
								 "creator" => $woot["woot_creator"],
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

	mysql_connection_db_overwrite(DATABASE);

	$wootId = intval(@$args["id"]);
	$wootTitle = addslashes(@$args["title"]);

	mysql_query("start transaction");

		if (! $wootId || $wootId === "new") {
			/* This is a new WOOT that hasn't been saved yet */

			if (! $wootTitle) {
				return(array("success" => false, "errorType" => "missing title"));
			}

			mysql__insert(WOOT_TABLE, array(
				"woot_title" => $wootTitle,
				"woot_created" => array("now()"),
				"woot_modified" => array("now()"),
				"woot_version" => 0,
				"woot_creator" => get_user_id()
			));
			$wootId = mysql_insert_id();
			if (! $wootId) {
				return(array("success" => false, "errorType" => "a woot with the given title already exists"));
			}
			$woot = mysql_fetch_assoc(mysql_query("select * from ".WOOT_TABLE." where woot_id=$wootId"));
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

			mysql_query("update ".WOOT_TABLE." set woot_version=woot_version+1 where woot_id=$wootId");
		}
		$res = mysql_query("select * from ".WOOT_TABLE." where woot_id=$wootId");

	mysql_query("commit and chain");

		$woot = mysql_fetch_assoc($res);
		$version = intval($woot["woot_version"]);

		$chunkIds = getReadableChunks($wootId, /* restrictToCurrent= */ true);

		$res = mysql_query("select * from ".CHUNK_TABLE."
							 where chunk_woot_id=$wootId and chunk_is_latest and !chunk_deleted and chunk_id in (" . join(",", $chunkIds) . ")
						  order by chunk_order");
		$existingVisibleChunks = array();
		while ($chunk = @mysql_fetch_assoc($res)) {	/* The @ takes care of the possibility that there are no chunks in this woot */
			$existingVisibleChunks[$chunk["chunk_number"]] = $chunk;
		}

		$incomingChunks = $args["chunks"];

		// Get the current chunk ordering (including the chunks the current user can't actually see)
		$existingChunkOrder = mysql__select_array(CHUNK_TABLE, "chunk_number", "chunk_woot_id=$wootId and chunk_is_latest and ! chunk_deleted order by chunk_order");
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
					$res = mysql_query("select chunk_id, chunk_order, chunk_owner from ".CHUNK_TABLE." where chunk_woot_id=$wootId and chunk_number=$chunkNumber and chunk_is_latest");
					if (mysql_num_rows($res) != 1) { /* should do something ... do we care? */ }

					$prevChunk = mysql_fetch_assoc($res);
					$prevChunkId = $prevChunk["chunk_id"];
					$chunkOrder = $prevChunk["chunk_order"];
					$chunkOwner = $prevChunk["chunk_owner"];

					mysql__update(CHUNK_TABLE, "chunk_woot_id=$wootId and chunk_number=$chunkNumber", array( "chunk_is_latest" => 0 ));
				}
				else {
					// Chunk exists, but is not modified.  Nothing more to do.
					continue;
				}
			}
			else {
				$res = mysql_query("select max(chunk_number) from ".CHUNK_TABLE." where chunk_woot_id=$wootId");
				$chunkNumber = @mysql_fetch_row($res);
				$chunkNumber = intval(@$chunkNumber[0]) + 1;
				$chunkOrder = 0;	// chunk order will be overridden anyway since there is a new chunk to take care of
				$chunkOwner = get_user_id();

				array_push($newChunks[$lastExistingChunk], $chunkNumber);
				++$newChunkCount;
			}

			$chunkDeleted = preg_match('/^\s*$/', $chunk["text"]);

			mysql__insert(CHUNK_TABLE, array(
				"chunk_woot_id" => $wootId,
				"chunk_number" => $chunkNumber,
				"chunk_version" => $version,
				"chunk_text" => $chunk["text"],
				"chunk_is_latest" => 1,
				"chunk_order" => $chunkOrder,
				"chunk_modified" => array("now()"),
				"chunk_owner" => $chunkOwner,
				"chunk_editor" => get_user_id(),
				"chunk_deleted" => $chunkDeleted
			));
			$chunkId = mysql_insert_id();

			if (! $chunkDeleted) {
				if ($chunkOwner == get_user_id()  ||  is_admin()) {
					// only the owner (or an admin) can change the permissions
					$result = insertPermissions($chunkId, $chunk, $woot["woot_creator"]);
					if ($result["success"] != true) { return($result); }
				}
				else {
					// copy the permissions from the previous version of the chunk
					mysql_query("insert into ".PERMISSION_TABLE."
								 (perm_chunk_id, perm_user_id, perm_group_id, perm_type, perm_creator, perm_created)
						   select distinct $chunkId, perm_user_id, perm_group_id, perm_type, perm_creator, perm_created
							 from ".PERMISSION_TABLE." where perm_chunk_id=$prevChunkId");
				}

				if ($chunk["nonce"]) {
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
				mysql_query("update ".CHUNK_TABLE." set chunk_order=$order
							  where chunk_woot_id=$wootId and chunk_number=$chunkNumber and chunk_is_latest");
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
		    ! ($permission["userId"]  ||  $permission["groupId"])) {
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
			"perm_chunk_id" => $chunkId,
			"perm_user_id" => @$permission["userId"]? $permission["userId"] : 0,
			"perm_group_id" => @$permission["groupId"]? $permission["groupId"] : 0,
			"perm_type" => $permission["type"],
			"perm_creator" => get_user_id(),
			"perm_created" => array("now()")
		);
	}
	foreach ($insertValues as $values) {
		mysql__insert(PERMISSION_TABLE, $values);
	}

	if (! $userHasReadWriteAccess  &&  ! is_admin()) {
		// Woah, hang on ... is the user REALLY trying to lock themselves out of this chunk?  Don't let them do THAT.
		mysql__insert(PERMISSION_TABLE, array(
			"perm_chunk_id" => $chunkId,
			"perm_user_id" => get_user_id(),
			"perm_type" => "RW",
			"perm_creator" => get_user_id(),
			"perm_created" => array("now()")
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
		    ! ($permission["userId"]  ||  $permission["groupId"])) {
			return(array("success" => false, "errorType" => "invalid woot permissions"));
		}
		if (@$permission["userId"] == -1) {	// automagic reference to userId -1 is converted to the owner's id
			$permission["userId"] = $woot["woot_creator"];
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
			"wperm_woot_id" => $wootId,
			"wperm_user_id" => @$permission["userId"]? $permission["userId"] : 0,
			"wperm_group_id" => @$permission["groupId"]? $permission["groupId"] : 0,
			"wperm_type" => $permission["type"],
			"wperm_creator" => get_user_id(),
			"wperm_created" => array("now()")
		);
	}
	foreach ($insertValues as $values) {
		mysql__insert(WOOT_PERMISSION_TABLE, $values);
	}

	if (! $userHasReadWriteAccess  &&  ! is_admin()) {
		// Woah, hang on ... is the user REALLY trying to lock themselves out of this woot?  Don't let them do THAT.
		mysql__insert(WOOT_PERMISSION_TABLE, array(
			"wperm_woot_id" => $wootId,
			"wperm_user_id" => get_user_id(),
			"wperm_type" => "RW",
			"wperm_creator" => get_user_id(),
			"wperm_created" => array("now()")
		));
	}

	return(array("success" => true));
}


/* SEARCHING */

function searchWoots($args) {

	mysql_connection_db_select(DATABASE);

	$text_search = getTextSearch($args["q"]);
	if (! $text_search) {
		return(array("success" => false, "errorType" => "invalid query"));
	}

	$res = mysql_query("select distinct woot_id, woot_title, woot_version
						  from ".WOOT_TABLE.",".CHUNK_TABLE."
						 where woot_id=chunk_woot_id and chunk_is_latest and !chunk_deleted
							   and " . $text_search . "
							   and chunk_id in (" . join(",", getReadableChunks(NULL, true)) . ")");

	$woots = array();
	while ($woot = mysql_fetch_assoc($res)) {
		array_push($woots, array(
			"id" => $woot["woot_id"],
			"version" => $woot["woot_version"],
			"title" => $woot["woot_title"]
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
			$TEXT_SEARCH .= "chunk_text like '%" . addslashes($term) . "%'";
		}
		else {
			if ($NEGATIVE_TEXT_SEARCH) { $NEGATIVE_TEXT_SEARCH .= " or "; }
			$NEGATIVE_TEXT_SEARCH .= "chunk_text like '%" . addslashes(substr($term, 1)) . "%'";
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
