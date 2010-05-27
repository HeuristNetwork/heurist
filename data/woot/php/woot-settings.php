<?php

define("WOOT_TABLE", "woots");
define("CHUNK_TABLE", "woot_chunks");
define("PERMISSION_TABLE", "woot_chunk_permissions");
define("WOOT_PERMISSION_TABLE", "woot_permissions");


function hasWootReadPermission($wootId) {
	/* Given a woot id, return true if the user has permission to read from it */

	if (is_admin()) { return true; }

	$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wperm_woot_id=$wootId and
	                   (wperm_user_id=".get_user_id()." or wperm_group_id in (".join(",", get_group_ids()).",-1))");
	return (mysql_num_rows($res) > 0);
}
function hasWootWritePermission($wootId) {
	/* Given a woot id, return true if the user has permission to write to it */

	if (is_admin()) { return true; }

	$res = mysql_query("select * from " . WOOT_PERMISSION_TABLE . " where wperm_woot_id=$wootId and
	                   (wperm_user_id=".get_user_id()." or wperm_group_id in (".join(",", get_group_ids()).",-1)) and wperm_type='RW'");
	return (mysql_num_rows($res) > 0);
}


function getReadableChunks($wootId=NULL, $restrictToCurrent=false) {
	/* Given a wootId to which the user has read-access,
	 * return the ids of the chunks which the user can read.
	 * If restrictToCurrent is true, then the chunks should be restricted
	 * to those which have not been superseded by newer versions.
	 * If the wootId is omitted, then the chunks may be sourced from any woot.
	 */

	$restriction = is_admin()? "1 " : "(perm_user_id=".get_user_id()." or perm_group_id in (".join(",", get_group_ids()).",-1)) ";
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
