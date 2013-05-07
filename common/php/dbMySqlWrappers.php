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
 * this is the layer file that isolates Heurist code from the underlying SQL interface. Ideally this is where you would
 * write code to use a different SQl engine.
 *
 * @author      Tom Murtagh
 * @author      Stephen White  <stephen.white@sydney.edu.au>
 * @author      Artem Osmakov  <artem.osmakov@sydney.edu.au>
 * @copyright   (C) 2005-2013 University of Sydney
 * @link        http://Sydney.edu.au/Heurist
 * @version     3.1.0
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package     Heurist academic knowledge management system
 * @subpackage  DataStore
 * @todo        funnel all calls to functions in this file. Not all system code uses these abstractions. Especially services.
 */
/**
*
* Function list:
* - mysql_connection_select()
* - mysql_connection_insert()
* - mysql_connection_overwrite()
* - mysqli_connection_overwrite()
* - sql_niceify()
* - mysql__insert()
* - mysql__replace()
* - mysql__update()
* - mysql__select_array()
* - mysql__select_assoc()
* - mysql__lookup()
* - slash()
* - isZeroBasedOrderedArray()
* - json_format()
* - mysql__getdatabases()
* - execSQL()
* - refValues()
*
*/
/* MySQL utility functions */
/**
* creates a readonly connection to the configured database server and use the database name if supplied.
* @param    string [$database] the name of the database to use. Empty by default and no use execute.
* @param    string [$server] name of the server running MySQL server to connect to
* @return   integer/boolean a MySQL link identifier on success, or FALSE on failure
* @uses     HEURIST_DBSERVER_NAME defined in initialise.php as the default server name
* @uses     READONLY_DBUSERNAME defined in initialise.php as the user name
* @uses     READONLY_DBUSERPSWD defined in initialise.php as the user password
*/
function mysql_connection_select($database = '', $server = HEURIST_DBSERVER_NAME) {
	/* User name and password for Select access */
	if (!READONLY_DBUSERNAME && !READONLY_DBUSERPSWD) {
		print "PLEASE SET USERNAME/PASSWORD for SELECT in configIni.php\n";
		exit(2);
	}
	$db = mysql_connect($server, READONLY_DBUSERNAME, READONLY_DBUSERPSWD) or die(mysql_error());
	if ($database != '') mysql_query("use $database") or die(mysql_error());
	mysql_query('set character set "utf8"');
	mysql_query('set names "utf8"');
	return $db;
}
/**
* creates a read/write connection to the configured database server and use the database name if supplied.
* It ensures that the character set is utf-8. This function will also set the MySQL @logged_in_user_id variable
* to the logged in user if the user is logged in.
* @param    string [$database] the name of the database to use. Empty by default and no use execute.
* @param    string [$server] name of the server running MySQL server to connect to
* @return   integer/boolean a MySQL link identifier on success, or FALSE on failure
* @uses     HEURIST_DBSERVER_NAME defined in initialise.php as the default server name
* @uses     ADMIN_DBUSERNAME defined in initialise.php as the read/write user name
* @uses     ADMIN_DBUSERPSWD defined in initialise.php as the read/write user password
*/
function mysql_connection_insert($database = '', $server = HEURIST_DBSERVER_NAME) {
	/* User name and password for insert access - must allow writing to database */
	if (!ADMIN_DBUSERNAME && !ADMIN_DBUSERPSWD) {
		print "PLEASE SET USERNAME/PASSWORD for INSERT in configIni.php\n";
		exit(2);
	}
	//	if (defined('use_alt_db')  &&  $database == 'heuristdb') $database = 'heuristdb_alt';
	$db = mysql_connect($server, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD) or die(mysql_error());
	if ($database != '') mysql_query("use $database") or die(mysql_error());
	mysql_query('set character set "utf8"');
	mysql_query('set names "utf8"');
	if (function_exists('get_user_id')) mysql_query('set @logged_in_user_id = ' . get_user_id());
	return $db;
}
/**
* creates a read/write connection to the configured database server and use the database name if supplied.
* It ensures that the character set is utf-8. This function will also set the MySQL @logged_in_user_id variable
* to the logged in user if the user is logged in.
* @param    string [$database] the name of the database to use. Empty by default and no use execute.
* @param    string [$server] name of the server running MySQL server to connect to
* @return   integer/boolean a MySQL link identifier on success, or FALSE on failure
* @uses     HEURIST_DBSERVER_NAME defined in initialise.php as the default server name
* @uses     ADMIN_DBUSERNAME defined in initialise.php as the read/write user name
* @uses     ADMIN_DBUSERPSWD defined in initialise.php as the read/write user password
*/
function mysql_connection_overwrite($database = '', $server = HEURIST_DBSERVER_NAME) {
	/* User name and password for overwrite access - must allow writing to database */
	if (!ADMIN_DBUSERNAME && !ADMIN_DBUSERPSWD) {
		print "PLEASE SET USERNAME/PASSWORD for OVERWRITE in configIni.php\n";
		exit(2);
	}
	//	if (defined('use_alt_db')  &&  $database == 'heuristdb') $database = 'heuristdb_alt';
	$db = mysql_connect($server, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD) or die(mysql_error());
	if ($database != '') mysql_query("use $database") or die(mysql_error());
	mysql_query('set character set "utf8"');
	mysql_query('set names "utf8"');
	if (function_exists('get_user_id')) mysql_query('set @logged_in_user_id = ' . get_user_id());
	return $db;
}
/**
* creates a read/write mysqli connection object connected to the configured database server and use the database name if supplied.
* It ensures that the character set is utf-8. This function will also set the MySQL @logged_in_user_id variable
* to the logged in user if the user is logged in.
* @param    string [$database] the name of the database to use. Empty by default and no use execute.
* @param    string [$server] name of the server running MySQL server to connect to
* @return   mysqli a MySQL connection object
* @uses     HEURIST_DBSERVER_NAME defined in initialise.php as the default server name
* @uses     ADMIN_DBUSERNAME defined in initialise.php as the read/write user name
* @uses     ADMIN_DBUSERPSWD defined in initialise.php as the read/write user password
*/
function mysqli_connection_overwrite($database = '', $server = HEURIST_DBSERVER_NAME) {
	if (!ADMIN_DBUSERNAME && !ADMIN_DBUSERPSWD) {
		print "PLEASE SET USERNAME/PASSWORD for OVERWRITE in configIni.php\n";
		exit(2);
	}
	$mysqli = new mysqli($server, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $database);
	/* check connection */
	if (mysqli_connect_errno()) {
		printf("Connect failed: %s\n", mysqli_connect_error());
		die(mysqli_connect_error());
	}
	$mysqli->query('set character set "utf8"');
	$mysqli->query('set names "utf8"');
	if (function_exists('get_user_id')) $mysqli->query('set @logged_in_user_id = ' . get_user_id());
	return $mysqli;
}
/**
* format input string into sql query usable string
* @param    string [$val] description
* @return   string formatted for use in am SQL query.
*/
function sql_niceify($val) {
	if (isset($val) and !is_null($val)) {
		return '"' . addslashes(preg_replace('/\r\n?/', "\n", $val)) . '"';
	} else {
		return 'NULL';
	}
}
/**
* Inserts a new row into the named $table,
* with a key/value pair for every entry in the associative array.
* If a pairs_assoc entry is an array it is treated specially ({@see mysql__update()})
* detailed desription
* @param    string [$table] name of target table
* @param    mixed [$pairs_assoc] name of target table
* @param    boolean [$ignoreDupes] determine if the IGNORE is placed into the query for ignore duplicates
* @return   mixed returns 1 if no pairs are given or the result from mysql_query {@link http://php.net/manual/en/function.mysql-query.php}
* @see      mysql__update()
*/
function mysql__insert($table, $pairs_assoc, $ignoreDupes = false) {
	// Prepare statement
	$keys = array_keys($pairs_assoc);
	$ignore = $ignoreDupes ? " ignore " : "";
	if (count($keys) == 0) {
		return 1;
	} else {
		$stmt = "INSERT $ignore INTO " . $table . ' (' . join(',', $keys) . ') VALUES (';
		for ($i = 0;$i < count($keys);$i++) {
			if ($i > 0) $stmt.= ",";
			if (!is_array($pairs_assoc[$keys[$i]])) {
				$stmt.= sql_niceify($pairs_assoc[$keys[$i]]);
			} else if (preg_match('/^[a-zA-Z0-9_]+[(](?:"[^\\"]+")?[)]$/', $pairs_assoc[$keys[$i]][0])) {
				$stmt.= $pairs_assoc[$keys[$i]][0];
			}
		}
		$stmt.= ')';
	}
	if (defined("T1000_DEBUG")) error_log($stmt);
	return mysql_query($stmt); // Maybe something bad, maybe something good!

}
/**
* Update row/rows in the table with a fixed set of data:
* each key/value pair in the associative array.
* If a VALUE in $pairs_assoc is an array with a single element
* of the form  functionName(args)  where the args contain no quotes or backslashes,
* then the value is not NOT escaped.  This allows simple SQL calls to be used in the update.
* e.g.
* <code>
*   mysql__update("table", "tbl_id=1", array(
*                                        "col1" => "now()",
*                                        "col2" => array("now()")
*                                      ));
* </code>
* This will set col1 to the string "col1", and col2 to the current date (according to SQL)
*
* Be careful: $table and $condition are passed un-modified to the query.
* @param    string [$table] name of target table
* @param    string [$condition] condition used directly in the SQL statement
* @param    mixed [$pairs_assoc] name of target table
* @return   mixed returns 1 if no pairs are given or the result from mysql_query {@link http://php.net/manual/en/function.mysql-query.php}
*/
function mysql__update($table, $condition, $pairs_assoc) {
	// Prepare statement
	if (count($pairs_assoc) == 0) return 1; // nothing to do
	else {
		$stmt = "UPDATE $table SET ";
		$first = true;
		foreach ($pairs_assoc as $key => $val) {
			if (!$first) $stmt.= ",";
			if (!is_array($val)) {
				$stmt.= $key . ' = ' . sql_niceify($val);
			} else if (preg_match('/^[a-zA-Z0-9_]+(?:[(](?:"[^\\"]+")?[)])?$/', $val[0])) {
				$stmt.= $key . ' = ' . $val[0];
			} else continue;
			$first = false;
		}
		$stmt.= " WHERE $condition";
	}
	if (defined('T1000_DEBUG')) error_log($stmt);
	return mysql_query($stmt); // Up to the user to check for error.

}
/**
* Return an array containing all criteria matching values of a column.
* $table, $column and $condition are passed un-modified to the query.
* NULL is returned on failure.
* @param    string [$table] name of target table
* @param    string [$column] column name of target table to return values from
* @param    string [$condition] condition used directly in the SQL statement
* @return   array of results from the $column column of $table or NULL on failure
*/
function mysql__select_array($table, $column, $condition) {
	if (defined('T1000_DEBUG')) error_log("SELECT $column FROM $table WHERE $condition");
	$res = mysql_query("SELECT $column FROM $table WHERE $condition");
	if (!$res) return NULL;
	$matches = array();
	while (($row = mysql_fetch_array($res))) array_push($matches, $row[0]);
	return $matches;
}
/**
* Return an associative array containing all matching rows,
* using the two columns to construct an associative array.
* $table, $*column and $condition are passed un-modified to the query.
* NULL is returned on failure.
* @param    string [$table] name of target table
* @param    string [$key_column] key column name of target table to return keys from
* @param    string [$val_column] value column name of target table to return values from
* @param    string [$condition] condition used directly in the SQL statement
* @return   array of key-value pairs from the $key_column and $val_column columns of $table or NULL on failure
*/
function mysql__select_assoc($table, $key_column, $val_column, $condition) {
	if (defined('T1000_DEBUG')) error_log("SELECT $key_column, $val_column FROM $table WHERE $condition");
	$res = mysql_query("SELECT $key_column, $val_column FROM $table WHERE $condition");
	if (!$res) return NULL;
	$matches = array();
	while (($row = mysql_fetch_array($res))) $matches[$row[0]] = $row[1];
	return $matches;
}
/**
* Run this query, reporting errors;
* make a lookup table from the first column to an associative array of the remaining columns.
* $query is passed un-modified to the query.
* NULL is returned on failure.
* @param    string [$query] description
* @return   mixed returns an associative array (lookup) on success or NULL on failure.
*/
function mysql__lookup($query) {
	/*	*/
	$res = mysql_query($query);
	if (!$res) {
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
/**
* enhancement to addslashes that standardises the return character.
* @param    string [$str] sql query string to be slashed
* @return   string slashed string with \n for return
*/
function slash($str) {
	return preg_replace("/\r\n|\r|\n/", "\\n", addslashes($str));
}
/**
* simple test to see if this is a zero based ordered array
* @param    object [$obj] variable to test
* @return   boolean true if ordered array
*/
function isZeroBasedOrderedArray($obj) {
	if (is_object($obj)) {
		return false;
	}
	$keys = array_keys($obj);
	$cnt = count($keys);
	for ($i = 0;$i < $cnt;$i++) {
		if ($i !== $keys[$i]) {
			return false;
		}
	}
	return true;
}
/**
* change php object in to it's json description as a string
* @param    mixed [$obj] variable to convert to JSON
* @param    boolean [$purdy] whether to out put in pretty format (use newlines)
* @return   string JSON formatted description of the supplied variable/object
*/
function json_format($obj, $purdy = false) {
	// Return the data from $obj as a JSON format string
	if (!is_array($obj) && !is_object($obj)) {
		// Primitive scalar types
		if ($obj === null) return "null";
		else if (is_bool($obj)) return $obj ? "true" : "false";
		else if (is_integer($obj)) return $obj;
		else if (is_float($obj)) return $obj;
		//else if (preg_match('/^-?[1-9]\d*$/', $obj)) return intval($obj);
		else return '"' . slash($obj) . '"';
	}
	// is it an array or an object?
	if (count($obj) == 0) {
		return "[]";
	} else if (isZeroBasedOrderedArray($obj)) {
		// Has a "0" element ... we'll call it an array
		$json = "";
		foreach ($obj as $val) {
			if ($json) {
				$json.= ",";
				if ($purdy) $json.= "\n";
			}
			$json.= json_format($val);
		}
		return "[" . $json . "]";
	} else {
		// Do object output
		$json = "";
		foreach ($obj as $key => $val) {
			if ($json) {
				$json.= ",";
				if ($purdy) $json.= "\n";
			}
			if (preg_match('/^\d+$/', $key)) {
				$json.= "\"" . $key . "\"" . ":" . json_format($val);
			} else {
				$json.= "\"" . slash($key) . "\":" . json_format($val);
			}
		}
		return "{" . $json . "}";
	}
}
/**
* returns list of databases as array
* @param    mixed $with_prefix - if false it remove "hdb_" prefix
* @param    mixed $email - current user email
* @param    mixed $role - admin - returns database where current user is admin, user - where current user exists
*/
function mysql__getdatabases($with_prefix = false, $email = null, $role = null, $prefix=HEURIST_DB_PREFIX) {
	$query = "show databases";
	$res = mysql_query($query);
	$result = array();
	$isFilter = ($email != null && $role != null);
	while ($row = mysql_fetch_array($res)) {
		$test = strpos($row[0], $prefix);
		if (is_numeric($test) && ($test == 0)) {
			if ($isFilter) {
				if ($role == 'user') {
					$query = "select ugr_ID from " . $row[0] . ".sysUGrps where ugr_eMail='" . $email . "'";
				} else if ($role == 'admin') {
					$query = "select ugr_ID from " . $row[0] . ".sysUGrps, " . $row[0] .".sysUsrGrpLinks".
							" left join sysIdentification on ugl_GroupID = sys_OwnerGroupID".
							" where ugr_ID=ugl_UserID and ugl_Role='admin' and ugr_eMail='" . $email . "'";
				}
				if ($query) {
					$res2 = mysql_query($query);
					if (mysql_num_rows($res2) < 1) {
						continue;
					}
				} else {
					continue;
				}
			}
			if ($with_prefix) {
				array_push($result, $row[0]);
			} else {
				// delete the prefix
				array_push($result, substr($row[0], strlen($prefix)));
			}
		}
	}

	natcasesort($result); //AO: Ian wants case insensetive order

	return $result;
}

/**
* put your comment there...
*
*/
function get_dbowner_email()
{
        $query = "select ugr_eMail from ".DATABASE.".sysUGrps where ugr_ID=2";
        $res = mysql_query($query);
        $row = mysql_fetch_array($res);
        if($row){
               return $row[0];
        }
        error_log("email for dbowner is not found");
        return null;
}

//ARTEM uses mysqli in saveStructure, saveUsergrps, srvMimetypes and loadReports
/**
* Execute a query (can be parameterised) and return either rows affected or data
* @param    object [$mysqli] mysqli object used to execute the query;
* @param    string [$sql] SQL statement to execute;
* @param    array [$parameters] = array of type and values of the parameters (if any)
* @param    boolean [$retCnt] true to return row count false to return an array with the values;
*/
function execSQL($mysqli, $sql, $params, $retCnt=true) {
	$result;
	if ($params == null || count($params) < 1) {// not parameterised
		if ($result = $mysqli->query($sql)) {
			if ($retCnt) {
				$result = $mysqli->affected_rows;
			}
		} else {
			$result = $mysqli->error;
			if ($result == "") {
				$result = $mysqli->affected_rows;
			} else {
				error_log(">>>Error=" . $mysqli->error);
			}
		}
	} else { //prepared query
		$stmt = $mysqli->prepare($sql) or die("Failed to prepared the statement!");
		call_user_func_array(array($stmt, 'bind_param'), refValues($params));
		$stmt->execute();
		if ($retCnt) {
			$result = $mysqli->error;
			if ($result == "") {
				$result = $mysqli->affected_rows;
			} else {
				error_log(">>>Error=" . $mysqli->error);
			}
		} else {
			$meta = $stmt->result_metadata();
			while ($field = $meta->fetch_field()) {
				$parameters[] = & $row[$field->name];
			}
			call_user_func_array(array($stmt, 'bind_result'), refValues($parameters));
			while ($stmt->fetch()) {
				$x = array();
				foreach ($row as $key => $val) {
					$x[$key] = $val;
				}
				$results[] = $x;
			}
			$result = $results;
		}
		$stmt->close();
	}
	return $result;
}
/**
* converts array of values to array of value references for PHP 5.3+
* detailed desription
* @param    array [$arr] of values
* @return   array of values or references to values
*/
function refValues($arr) {
	if (strnatcmp(phpversion(), '5.3') >= 0) //Reference is required for PHP 5.3+
	{
		$refs = array();
		foreach ($arr as $key => $value) $refs[$key] = & $arr[$key];
		return $refs;
	}
	return $arr;
}
?>