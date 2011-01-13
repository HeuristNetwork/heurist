<?php
/* T1000 Database template system
   (c) 2005 Archaeological Computing Laboratory, University of Sydney
   Developed by Tom Murtagh, Version 1.0,  16 Aug 2005 */

/* INSTRUCTIONS: Edit IN the user name and password (3 locations below)
   The configure script will automatically substitute the readonly
   etc. names with the values provided to the script */

/* MySQL utility functions */

function mysql_connection_select($database='', $server=HEURIST_DBSERVER_NAME) {
/* User name and password for Select access */
	if (! READONLY_DBUSERNAME && ! READONLY_DBUSERPSWD) { print "PLEASE SET USERNAME/PASSWORD for SELECT in configIni.php\n"; exit(2); }

	$db = mysql_connect($server, READONLY_DBUSERNAME, READONLY_DBUSERPSWD) or die(mysql_error());
	if ($database != '') mysql_query("use $database") or die(mysql_error());

	mysql_query('set character set "utf8"');
	mysql_query('set names "utf8"');

	return $db;
}
function mysql_connection_insert($database='', $server=HEURIST_DBSERVER_NAME) {
/* User name and password for insert access - must allow writing to database */
	if (! ADMIN_DBUSERNAME && ! ADMIN_DBUSERPSWD) { print "PLEASE SET USERNAME/PASSWORD for INSERT in configIni.php\n"; exit(2); }

//	if (defined('use_alt_db')  &&  $database == 'heuristdb') $database = 'heuristdb_alt';

	$db = mysql_connect($server, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD) or die(mysql_error());
	if ($database != '') mysql_query("use $database") or die(mysql_error());

	mysql_query('set character set "utf8"');
	mysql_query('set names "utf8"');
	if (function_exists('get_user_id'))
		mysql_query('set @logged_in_user_id = ' . get_user_id());

	return $db;
}
function mysql_connection_overwrite($database='', $server=HEURIST_DBSERVER_NAME) {
/* User name and password for overwrite access - must allow writing to database */
	if (! ADMIN_DBUSERNAME && ! ADMIN_DBUSERPSWD) { print "PLEASE SET USERNAME/PASSWORD for OVERWRITE in configIni.php\n"; exit(2); }

//	if (defined('use_alt_db')  &&  $database == 'heuristdb') $database = 'heuristdb_alt';

	$db = mysql_connect($server, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD) or die(mysql_error());
	if ($database != '') mysql_query("use $database") or die(mysql_error());

	mysql_query('set character set "utf8"');
	mysql_query('set names "utf8"');
	if (function_exists('get_user_id'))
		mysql_query('set @logged_in_user_id = ' . get_user_id());

	return $db;
}


function mysql_connection_localhost_select($database='') {
	return mysql_connection_select($database,'localhost');
}
function mysql_connection_localhost_insert($database='') {
	return mysql_connection_insert($database,'localhost');
}
function mysql_connection_localhost_overwrite($database='') {
	return mysql_connection_overwrite($database,'localhost');
}

function mysql_connection_db_select($database='') {
	return mysql_connection_select($database);
}
function mysql_connection_db_insert($database='') {
	return mysql_connection_insert($database);
}
function mysql_connection_db_overwrite($database='') {
	return mysql_connection_overwrite($database);
}


function sql_niceify($val) {
	if (isset($val) and !is_null($val)) {
		return '"'.addslashes(preg_replace('/\r\n?/', "\n", $val)).'"';
	} else {
		return 'NULL';
	}
}


function mysql__insert($table, $pairs_assoc, $ignoreDupes=false) {
	/* Insert a new row into the named table,
	 * with a key/value pair for every entry in the associative array.
	 * If a pairs_assoc entry is an array it is treated specially (see mysql__update)
	 */

	// Prepare statement
	$keys = array_keys($pairs_assoc);

	$ignore = $ignoreDupes? " ignore " : "";

	if (count($keys) == 0) return 1;
	else {
		$stmt = "INSERT $ignore INTO " . $table . ' (' . join(',', $keys) . ') VALUES (';
		for ($i=0; $i < count($keys); $i++) {
			if ($i > 0) $stmt .= ",";

			if (! is_array($pairs_assoc[$keys[$i]]))
				$stmt .= sql_niceify($pairs_assoc[$keys[$i]]);
			else if (preg_match('/^[a-zA-Z0-9_]+[(](?:"[^\\"]+")?[)]$/', $pairs_assoc[$keys[$i]][0]))
				$stmt .= $pairs_assoc[$keys[$i]][0];
		}
		$stmt .= ')';
	}

if (defined("T1000_DEBUG")) error_log($stmt);

	return mysql_query($stmt);	// Maybe something bad, maybe something good!
}


function mysql__replace($table, $pairs_assoc, $ignoreDupes=false) {
	/* Insert a new row into the named table,
	 * with a key/value pair for every entry in the associative array.
	 * If a pairs_assoc entry is an array it is treated specially (see mysql__update)
	 */

	// Prepare statement
	$keys = array_keys($pairs_assoc);

	$ignore = $ignoreDupes? " ignore " : "";

	if (count($keys) == 0) return 1;
	else {
		$stmt = "REPLACE $ignore INTO " . $table . ' (' . join(',', $keys) . ') VALUES (';
		for ($i=0; $i < count($keys); $i++) {
			if ($i > 0) $stmt .= ",";

			if (! is_array($pairs_assoc[$keys[$i]]))
				$stmt .= sql_niceify($pairs_assoc[$keys[$i]]);
			else if (preg_match('/^[a-zA-Z0-9_]+[(](?:"[^\\"]+")?[)]$/', $pairs_assoc[$keys[$i]][0]))
				$stmt .= $pairs_assoc[$keys[$i]][0];
		}
		$stmt .= ')';
	}

if (defined("T1000_DEBUG")) error_log($stmt);

	return mysql_query($stmt);	// Maybe something bad, maybe something good!
}


function mysql__update($table, $condition, $pairs_assoc) {
	/* Update row/rows in the table with a fixed set of data:
	 * each key/value pair in the associative array.
	 *
	 * If a VALUE in $pairs_assoc is an array with a single element
	 * of the form  functionName(args)  where the args contain no quotes or backslashes,
	 * then the value is not NOT escaped.  This allows simple SQL calls to be used in the update.
	 * e.g.
	 *   mysql__update("table", "tbl_id=1", array(
	 *                                        "col1" => "now()",
	 *                                        "col2" => array("now()")
	 *                                      ));
	 * This will set col1 to the string "col1", and col2 to the current date (according to SQL)
	 *
	 * Be careful: $table and $condition are passed un-modified to the query.
	 */

	// Prepare statement
	if (count($pairs_assoc) == 0) return 1;	// nothing to do
	else {
		$stmt = "UPDATE $table SET ";
		$first = true;
		foreach ($pairs_assoc as $key => $val) {
			if (! $first) $stmt .= ",";

			if (! is_array($val)) {
				$stmt .= $key . ' = ' . sql_niceify($val);
			}
			else if (preg_match('/^[a-zA-Z0-9_]+(?:[(](?:"[^\\"]+")?[)])?$/', $val[0])) {
				$stmt .= $key . ' = ' . $val[0];
			}
			else continue;

			$first = false;
		}
		$stmt .= " WHERE $condition";
	}

	if (defined('T1000_DEBUG')) error_log($stmt);

	return mysql_query($stmt);	// Up to the user to check for error.
}


function mysql__select_array($table, $column, $condition) {
	/* Return an array containing all matching values of a column.
	 * $table, $column and $condition are passed un-modified to the query.
	 * NULL is returned on failure.
	 */

	if (defined('T1000_DEBUG')) error_log("SELECT $column FROM $table WHERE $condition");
	$res = mysql_query("SELECT $column FROM $table WHERE $condition");
	if (! $res) return NULL;

	$matches = array();
	while (($row = mysql_fetch_array($res)))
		array_push($matches, $row[0]);

	return $matches;
}


function mysql__select_assoc($table, $key_column, $val_column, $condition) {
	/* Return an associative array containing all matching rows,
	 * using the two columns to construct an associative array.
	 * $table, $*column and $condition are passed un-modified to the query.
	 * NULL is returned on failure.
	 */

	if (defined('T1000_DEBUG')) error_log("SELECT $key_column, $val_column FROM $table WHERE $condition");
	$res = mysql_query("SELECT $key_column, $val_column FROM $table WHERE $condition");
	if (! $res) return NULL;

	$matches = array();
	while (($row = mysql_fetch_array($res)))
		$matches[$row[0]] = $row[1];

	return $matches;
}


function mysql__lookup($query) {
/* Run this query, reporting errors;
 * make a lookup table from the first column to an associative array of the remaining columns.
 */
	$res = mysql_query($query);

	if (! $res) {
		error_log('mysql__lookup: ' . mysql_error());
		return NULL;
	}

	$lookup = array();
	while (($row = mysql_fetch_assoc($res))) {
		$pri = array_shift($row);
		$lookup[$pri] = $row;
	}

	return $lookup;
}


function slash($str) {
	return preg_replace("/\r\n|\r|\n/", "\\n", addslashes($str));
}


function json_format($obj, $purdy=false) {
	// Return the data from $obj as a JSON format string

	if (! is_array($obj)) {
		// Primitive scalar types
		if ($obj === null) return "null";
		else if (is_bool($obj)) return $obj? "true" : "false";
		else if (is_integer($obj)) return $obj;
		else if (is_float($obj)) return $obj;
		//else if (preg_match('/^-?[1-9]\d*$/', $obj)) return intval($obj);
		else return '"' . slash($obj) . '"';
	}

	// is it an array or an object?
	if (count($obj) == 0) {
		return "[]";
	}
	else if (array_key_exists(0, $obj)) {
		// Has a "0" element ... we'll call it an array
		$json = "";
		foreach ($obj as $val) {
			if ($json) { $json .= ",";  if ($purdy) $json .= "\n"; }
			$json .= json_format($val);
		}
		return "[".$json."]";
	}
	else {
		// Do object output
		$json = "";
		foreach ($obj as $key => $val) {
			if ($json) { $json .= ","; if ($purdy) $json .= "\n"; }
			if (preg_match('/^\d+$/', $key)) $json .= $key . ":" . json_format($val);
			else $json .= "\"" . slash($key) . "\":" . json_format($val);
		}
		return "{".$json."}";
	}
}

?>
