<?php
/**
* utils_db.php - Library of mySql database functions
*
* @project     Heurist academic knowledge management system
* @package Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
* 
* @todo convert to class
*/
use hserv\utilities\DbUtils;
use hserv\utilities\USanitize;
use hserv\utilities\Temporal;
use hserv\structure\ConceptCode;

    /**
    *  Database utilities :   mysql_ - prefix for function
    *
    *  mysql__connection - establish connection to db server
    *  mysql__usedatabase - USE DATABASE switch the database
    *  mysql__create_database - create new database
    *  mysql__drop_database - delete the database
    *  mysql__foreign_check - SET FOREIGN_KEY_CHECKS
    *  mysql__supress_trigger - SUPPRESS_UPDATE_TRIGGER
    *  mysql__safe_updatess  - SET SQL_SAFE_UPDATES
    *  mysql__found_rows - total rows in databaase
    *
    *  mysql__getdatabases4 - get list of databases on the server
    *  mysql__check_dbname
    *  mysql__get_names - get database name with and without hdb prefix
    *
    *  mysql__select - base function
    *  mysql__select_assoc - returns array  key_column(first field)=>array(field=>val,....)
    *  mysql__select_assoc2 - returns array  key_column=>val_column for given table
    *  mysql__select_list - returns array of one column values
    *  mysql__select_value   - return the first column of first row
    *  mysql__select_row   - returns first row
    *  mysql__select_row_assoc - returns first row assoc fieldnames
    *  mysql__select_all
    *  mysql__duplicate_table_record
    *  mysql__insertupdate
    *  mysql__select_param_query
    *  mysql__exec_param_query
    *  mysql__delete
    *  mysql__begin_transaction
    *  mysql__script - executes sql script file
    *
    *
    *  getSysValues - Returns values from sysIdentification
    *  isFunctionExists - verifies that mysql stored function exists
    *  checkDatabaseFunctions - checks that all db functions exists and recreates them if they are missed
    *  checkDatabaseFunctionsForDuplications
    *  trim_item
    *  stripAccents
    *  prepareIds
    *  prepareStrIds
    *  predicateId - prepare field compare with one or more ids
    *
    *  checkMaxLength - check max length for TEXT field
    *  getDefinitionsModTime - returns timestamp of last update of db denitions
    *
    *  recreateRecLinks
    *  recreateRecDetailsDateIndex
    * 
    *  createTable
    *  alterTable
    *  hasTable - Returns true if table exists in database
    *  hasColumn - Returns true if column exists in given table
    *  checkUserStatusColumn - Checks that sysUGrps.ugr_Enabled has proper set - @todo remove
    */

    /**
     * Connects to the database server and selects the specified database.
     *
     * Uses connection parameters (server name, admin credentials, port) defined
     * in `heuristConfigIni.php`.
     *
     * @param string $dbname The name of the database to select.
     * @return \mysqli|array A mysqli instance on success, or an array with an error code
     *                       and message on failure.
     */
    function mysql__init($dbname=null){
        
        //connecction parameter defined in heuristConfigIni.php
        $mysqli = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DB_PORT);
        
        if (is_a($mysqli, 'mysqli') && $dbname){
            
            $res = mysql__usedatabase($mysqli, $dbname);
            if ( $res!==true ){
                //open of database failed
                return $res;
            }
        }
        
        return $mysqli;  
    }
    
    /**
     * Establishes a connection to the MySQL database server.
     *
     * @param string $dbHost The hostname or IP address of the database server.
     * @param string $dbUsername The MySQL username.
     * @param string $dbPassword The MySQL password.
     * @param int|null $dbPort The port number to use for the connection. Defaults to null (MySQL default).
     * @return \mysqli|array A mysqli instance on successful connection, or an array
     *                       containing an error code and message on failure.
     */
    function mysql__connection($dbHost, $dbUsername, $dbPassword, $dbPort=null){

        if(null==$dbHost || $dbHost==""){
            return array(HEURIST_SYSTEM_FATAL, "Database server is not defined. Check your configuration file");
        }
        if (!function_exists('mysqli_init')) {
          return array(HEURIST_SYSTEM_FATAL, "PHP extension 'mysqli' is not loaded. Install/enable it (php-mysql / php-mysqlnd) and restart your web server.");
        }
        
        $res = true;

        try{
            $mysqli = mysqli_init();
            if($mysqli){
                //debug mode mysqli_report(MYSQLI_REPORT_ALL);
                mysqli_report(MYSQLI_REPORT_STRICT);//MYSQLI_REPORT_ERROR |
                $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 1);
                $res = $mysqli->real_connect($dbHost, $dbUsername, $dbPassword, null, $dbPort);
            }
        } catch (Exception $e)  {
        }
        if(!($mysqli && $res)){
            return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }

        /* check connection */
        if (mysqli_connect_errno()) {
            return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }
        return $mysqli;
    }

    /**
     * Selects a database to use for the current MySQL connection.
     *
     * Also sets the character set to utf8mb4.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $dbname The name of the database to select.
     * @return bool|array True on success, or an array with an error code and message on failure.
     */
    function mysql__usedatabase($mysqli, $dbname){

        if($dbname){

            list($database_name_full, $database_name) = mysql__get_names( $dbname );

            $res = mysql__check_dbname($dbname);
            if($res==null){
                $success = $mysqli->select_db($database_name_full);
                if(!$success){
                    $db_exists = mysql__select_value($mysqli, "SHOW DATABASES LIKE '$database_name_full'");

                    if($db_exists == null){
                        return array(HEURIST_ACTION_BLOCKED,
                            "The requested database '".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8')."' does not exist", $mysqli->error);
                    }else{
                        return array(HEURIST_INVALID_REQUEST,
                            "Could not open database ".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8'), $mysqli->error);
                    }
                }
            }else{
                return array(HEURIST_INVALID_REQUEST, $res);
            }

            //$mysqli->query('SET CHARACTER SET utf8mb4');//utf8 is utf8mb3 by default
            //$mysqli->query('SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $mysqli->query('SET NAMES utf8mb4');
            //$mysqli->query('SET SESSION MAX_EXECUTION_TIME=2000');//60000 = 1 min
        }
        return true;
    }

    /**
     * Validates a database name.
     *
     * Checks for emptiness, illegal characters (allows only alphanumeric and underscore),
     * and excessive length (max 64 characters).
     *
     * @param string $db_name The database name to validate.
     * @return string|null An error message if validation fails, null otherwise.
     */
    function mysql__check_dbname($db_name){

        $res = null;

        if( !isset($db_name) || $db_name === null || $db_name === '' ){
            $res = 'Database parameter not defined';
        }elseif(preg_match('/[^A-Za-z0-9_\$]/', $db_name)){ //validatate database name
            $res = 'Database name '.htmlspecialchars($db_name).' is invalid. Only letters, numbers and underscores (_) are allowed in the database name';
        }elseif(strlen($db_name)>64){
            $res = 'Database name '.htmlspecialchars($db_name).' is too long. Max 64 characters allowed';
        }

        return $res;
    }

    /**
     * Creates a new database.
     *
     * The database is created with the utf8 character set and utf8_general_ci collation by default.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $db_name The full name of the database to create (e.g., including prefix).
     * @return bool|array True on success, or an array with an error code and message on failure.
     */
    function mysql__create_database( $mysqli, $db_name ){

        $res = mysql__check_dbname($db_name);

        // Avoid illegal chars in db
        if ($res==null) {
            // Create database
            // databse is created wiht utf8 (3-bytes encoding) and case insensetive collation order
            // Records, recDetails and defTerms are create with utf8mb4 (4bytes encoding) - see blankDBStructure.sql
            //
            $sql = 'CREATE DATABASE `'.$db_name.'` '
                     .' DEFAULT CHARACTER SET = utf8 DEFAULT COLLATE = utf8_general_ci';
                    //.' DEFAULT CHARACTER SET = utf8mb4 DEFAULT COLLATE = utf8mb4_0900_ai_ci';
                    //

            if ($mysqli->query($sql)) {
                $res = true;
            } else {
                $res = array(HEURIST_DB_ERROR,
                        'Unable to create database '
                            .htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8')
                            .' SQL error: '.$mysqli->error);
            }
        }else{
            $res = array(HEURIST_INVALID_REQUEST, $res);
        }
        return $res;
    }

    /**
     * Drops (deletes) a database.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $db_name The full name of the database to drop.
     * @return bool True on success, false on failure.
     */
    function mysql__drop_database( $mysqli, $db_name ){

        return $mysqli->query('DROP DATABASE `'.$db_name.'`');
    }

    /**
     * Enables or disables foreign key checks for the current session.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param bool $is_on True to enable foreign key checks, false to disable.
     * @return void
     */
    function mysql__foreign_check( $mysqli, $is_on ){
        $mysqli->query('SET FOREIGN_KEY_CHECKS = '.($is_on?'1':'0'));
    }

    /**
     * Enables or disables update triggers for the current session via a session variable.
     *
     * Sets `@SUPPRESS_UPDATE_TRIGGER` to 1 to suppress triggers, or NULL to enable them.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param bool $is_on True to suppress triggers, false to enable them.
     * @return void
     */
    function mysql__supress_trigger($mysqli, $is_on ){
        $mysqli->query('SET @SUPPRESS_UPDATE_TRIGGER='.($is_on?'1':'NULL'));
    }

    /**
     * Enables or disables SQL safe updates for the current session.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param bool $is_on True to enable safe updates, false to disable.
     * @return void
     */
    function mysql__safe_updatess($mysqli, $is_on ){
        $mysqli->query('SET SQL_SAFE_UPDATES='.($is_on?'1':'0'));
    }

    /**
     * Retrieves the number of rows found by the previous SELECT query.
     *
     * Note: `FOUND_ROWS()` is deprecated in newer MySQL versions.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @return int|null The number of found rows, or null on error.
     */
    function mysql__found_rows($mysqli){
        return mysql__select_value($mysqli, 'SELECT FOUND_ROWS()');
    }

    /**
     * Gets the database name with and without the Heurist prefix.
     *
     * @param string|null $db The database name. If it starts with `HEURIST_DB_PREFIX`,
     *                        the prefix is stripped for the short name. Otherwise, the
     *                        prefix is added for the full name.
     * @return array An array containing two elements: `[$database_name_full, $database_name]`.
     */
    function mysql__get_names( string $db ): array{

        if($db==null){
            return [null, null];
        }
        
        if(strpos($db, HEURIST_DB_PREFIX)===0){
            $database_name_full = $db;
            $database_name = substr($db,strlen(HEURIST_DB_PREFIX));
        }else{
            $database_name = $db;
            $database_name_full = HEURIST_DB_PREFIX.$db;
        }
    
        return array($database_name_full, $database_name);
    }

    /**
     * Returns a list of databases filtered by various criteria.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param bool $with_prefix If true, returned database names will include `HEURIST_DB_PREFIX`. Default is false.
     * @param string|null $starts_with Optional filter to list only databases whose names start with this string (after the prefix).
     * @param string|null $email Optional email of the user to filter databases based on user roles.
     * @param string|null $role Optional role ('admin' or 'user') to filter databases. Requires $email to be set.
     * @param string $prefix The database prefix to use (defaults to `HEURIST_DB_PREFIX`).
     * @return array An array of database names.
     * @throws \Exception If the `SHOW DATABASES` query fails.
     */
    function mysql__getdatabases4($mysqli, $with_prefix = false, $starts_with = null,
                                  $email = null, $role = null, $prefix = HEURIST_DB_PREFIX)
    {
        // Step 1: Validate and construct the `LIKE` clause for database filtering
        $where = $prefix . '%'; // Default case
        if ($starts_with && mysql__check_dbname($starts_with) == null) { // && preg_match('/^[A-Za-z0-9_\$]+$/', $starts_with)
            $where = $prefix . $starts_with . '%';
        }

        // Step 2: Execute the database query
        $query = "SHOW DATABASES WHERE `database` LIKE '" . $mysqli->real_escape_string($where) . "'";
        $res = $mysqli->query($query);

        if (!$res) {
            throw new Exception('Error executing SHOW DATABASES query: ' . $mysqli->error);
        }

        $databases = [];

        // Step 3: Filter databases based on role and email, if provided
        while ($row = $res->fetch_row()) {
            $database = $row[0];
            if (strpos($database, $prefix) !== 0) {
                continue;
            }
            $filtered_db = mysql__checkUserRole($mysqli, $database, $email, $role);
            if ($filtered_db) {
                $databases[] = htmlspecialchars($with_prefix ? $database : substr($database, strlen($prefix)));
            }
        }
        $res->close();

        // Step 4: Sort the result case-insensitively
        natcasesort($databases);
        return array_values($databases); // Re-index for JSON compatibility
    }


    /**
     * Checks if a user has a specified role in a given database.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $database The full name of the database (including prefix).
     * @param string|null $email The email of the user. If empty or null, role check is skipped and returns true.
     * @param string|null $role The role to check for ('admin' or 'user'). If empty or null, role check is skipped.
     * @return bool True if the user has the specified role (or if email/role is not provided for filtering),
     *              false otherwise or if the user is not found with that role.
     */
    function mysql__checkUserRole($mysqli, $database, $email, $role) {
        if(empty($email) || !$role){
            return true; // No filtering required
        }

        $sanitized_db = $mysqli->real_escape_string($database);
        $query = null;

        // Determine the query based on the role
        if ($role == 'user') {
            $query = "SELECT ugr_ID FROM `$sanitized_db`.sysUGrps
                      WHERE ugr_eMail = '" . $mysqli->real_escape_string($email) . "'";
        } elseif ($role == 'admin') {
            $query = "SELECT ugr_ID FROM `$sanitized_db`.sysUGrps
                      JOIN `$sanitized_db`.sysUsrGrpLinks ON ugr_ID = ugl_UserID
                      JOIN sysIdentification ON ugl_GroupID = sys_OwnerGroupID
                      WHERE ugl_Role = 'admin' AND ugr_eMail = '" . $mysqli->real_escape_string($email) . "'";
        }

        $value = mysql__select_value($mysqli, $query);

        return $value!=null;
    }


    /**
     * Executes a simple MySQL SELECT query.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $query The SQL query string.
     * @return \mysqli_result|null A mysqli_result object if the query was successful,
     *                             null otherwise. Errors are logged.
     */
    function mysql__select($mysqli, $query){

        $res = null;
        if($mysqli && $query){
            $res = $mysqli->query($query);
            if (!$res){
                error_log($mysqli->errno.'****'.$mysqli->error);
//remarked to avoid security report alert  error_log($query)
                return null;

/*
determine our thread id and kill connection
$thread_id = $mysqli->thread_id;
$mysqli->kill($thread_id);
*/
            }
        }

        return $res;
    }

    /**
     * Executes a query and returns the result as an associative array mapping the first column's values to the second column's values.
     *
     * Example: If query returns rows ( (1, 'apple'), (2, 'banana') ),
     * the function returns `[1 => 'apple', 2 => 'banana']`.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $query The SQL query string. Expected to return at least two columns.
     * @return array An associative array where keys are values from the first column
     *               and values are from the second column of the result set.
     */
    function mysql__select_assoc2($mysqli, $query):array{

        $matches = array();
        if($mysqli && $query){

            $res = $mysqli->query($query);
            if ($res){
                while ($row = $res->fetch_row()){
                    $matches[$row[0]] = $row[1];
                }
                $res->close();
            }
        }
        return $matches;
    }

    /**
    * returns array  key_column(first filed)=>array(field=>val,....)
    *
    * @param mixed $mysqli
    * @param mixed $query
    * @param mixed $mode
    *                   0 - Returns a numerically indexed array of associative arrays (each representing a row).
    *                   1 - Returns an associative array where keys are the values of the first column
    *                       of each row, and values are associative arrays of the remaining columns for that row.
    * @return array The result set as an array, formatted according to the $mode.
    */
    function mysql__select_assoc($mysqli, $query, $mode=1):array{

        $matches = array();
        if($mysqli && $query){

            $res = $mysqli->query($query);
            if ($res){
                while ($row = $res->fetch_assoc()){
                    if($mode==0){
                        $matches[] = $row;
                    }else{
                        $key = array_shift($row);
                        $matches[$key] = $row;
                    }
                }
                $res->close();
            }
        }
        return $matches;
    }

    /**
    * Returns an array containing the values of the first column from the result set.
    *
    * Optionally, a callback function can be applied to each value.
    * Always returns an array, even if the query fails or returns no results.
    *
    * @param \mysqli $mysqli The mysqli connection object.
    * @param string $query The SQL query string.
    * @param callable|null $functionName An optional callback function to apply to each value from the first column.
    * @return array A list of values from the first column of the result set.
    */
    function mysql__select_list2($mysqli, $query, $functionName=null):array {


        if(!($mysqli && $query)){
            return array();
        }

        $matches = array();

        $res = $mysqli->query($query);

        if ($res){
            if($functionName!=null){
                while ($row = $res->fetch_row()){
                    array_push($matches, $functionName($row[0]));
                }
            }else{
                while ($row = $res->fetch_row()){
                    array_push($matches, $row[0]);
                }
            }
            $res->close();
        }

        return $matches;
    }

    /**
     * Selects a list of values from a single column in a table based on a condition.
     *
     * This is a convenience wrapper around `mysql__select_list2`.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $table The name of the table.
     * @param string $column The name of the column to select.
     * @param string $condition The WHERE clause condition (without the 'WHERE' keyword).
     * @return array A list of values from the specified column matching the condition.
     */
    function mysql__select_list($mysqli, $table, $column, $condition):array {
        $query = "SELECT $column FROM $table WHERE $condition";
        return mysql__select_list2($mysqli, $query);
    }

    /**
     * Executes a query and returns the value of the first column of the first row.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $query The SQL query string.
     * @param array|null $params Optional parameters for a prepared statement (see `mysql__select_param_query`).
     * @return mixed|null The value of the first column of the first row, or null if no result or on error.
     */
    function mysql__select_value($mysqli, $query, $params=null) {
        $row = mysql__select_row($mysqli, $query, $params);

        if($row && @$row[0]!=null){
            $result = $row[0];
        }else{
            $result = null;
        }
        return $result;
    }

    /**
     * Executes a query and returns the first row of the result set as a numerically indexed array.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $query The SQL query string.
     * @param array|null $params Optional parameters for a prepared statement (see `mysql__select_param_query`).
     * @return array|null The first row as a numerically indexed array, or null if no result or on error.
     */
    function mysql__select_row($mysqli, $query, $params=null) {
        $result = null;
        if($mysqli){

            $res = mysql__select_param_query($mysqli, $query, $params);
            if($res){
                $row = $res->fetch_row();
                if($row){
                    $result = $row;
                }
                $res->close();
            }else{
                USanitize::errorLog('Query: '.$query.'.  mySQL error: '.$mysqli->error);
            }
        }
        return $result;
    }

    /**
     * Executes a query and returns the first row of the result set as an associative array.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $query The SQL query string.
     * @return array|null The first row as an associative array, or null if no result or on error.
     */
    function mysql__select_row_assoc($mysqli, $query) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if($res){
                $row = $res->fetch_assoc();
                if($row){
                    $result = $row;
                }
                $res->close();
            }
        }
        return $result;
    }


    /**
    * returns all rows as two dimensional array
    *
    * @param mixed $mysqli
    * @param mixed $query
    * @param mixed $mode
    *                   0 - Returns a numerically indexed array of numerically indexed arrays (each representing a row).
    *                   1 - Returns an associative array where keys are the values of the first column
    *                       of each row, and values are numerically indexed arrays of the remaining columns for that row.
    * @param int $i_trim If > 0, trims each value in the row to this maximum length using `trim_item`. Default is 0 (no trim).
    * @return array|null An array containing all rows from the result set, formatted according to $mode,
    *                    or null if the mysqli object is not valid. Returns an empty array if the query executes
    *                    successfully but returns no rows, or if there's a MySQL error during execution.
    */
    function mysql__select_all($mysqli, $query, $mode=0, $i_trim=0) {

        if(!$mysqli){
            return null;
        }

        $result = array();
        $res = $mysqli->query($query);
        if ($res){
            while ($row = $res->fetch_row()){

                if($i_trim>0) {array_walk($row, 'trim_item', $i_trim);}

                if($mode==1){
                    $rec_id = array_shift($row);
                    $result[$rec_id] = $row;
                }else {
                    array_push($result, $row);
                }
            }
            $res->close();

        }elseif($mysqli->error){
            return null;
        }

        return $result;
    }

    /**
     * Retrieves the column names of a specified table.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $table The name of the table.
     * @return array|null An array of column names, or null if the query fails.
     */
    function mysql__get_table_columns($mysqli, $table){

        $res = $mysqli->query('DESCRIBE '.$table);
        if (!$res) {return null;}
        $matches = array();
        if($res){
            while ($row = $res->fetch_row()) {array_push($matches, $row[0]);}

            $res->close();
        }
        return $matches;
    }

    /**
     * Duplicates a record within a table, assigning a new ID.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $table The name of the table.
     * @param string $idfield The name of the primary key ID field.
     * @param int $oldid The ID of the record to duplicate.
     * @param int $newid The new ID for the duplicated record.
     * @return int|string The insert ID of the new record on success, or an error string on failure.
     */
    function mysql__duplicate_table_record($mysqli, $table, $idfield, $oldid, $newid){

        $columns = mysql__get_table_columns($mysqli, $table);

        //in our scheme first column is always id (primary key)
        array_shift($columns);

        $columns3 = array();
        foreach($columns as $idx=>$column){
            $columns3[] = '`'.preg_replace(REGEX_ALPHANUM, "", $column).'`';//for snyk
        }

        if($idfield!=null && $newid!=null){

            $idx = array_search('`'.$idfield.'`', $columns3);
            $columns2 = $columns3;
            $columns2[$idx] = intval($newid);
            $columns2 = implode(',',$columns2);

        }else{
            $columns2 = implode(',',$columns3);
        }

        $where = " where `$idfield`=".intval($oldid);

        $columns3 = implode(',',$columns3);
        //
        $query = "INSERT INTO `$table` ($columns3) SELECT $columns2 FROM `$table`".$where;

        $res = $mysqli->query($query);
        if(!$res){
            $ret = 'database error - ' .$mysqli->error;
        }else{
            $ret = $mysqli->insert_id;
       }
        return $ret;
    }

    /**
    * delete record for given table
    *
    * returns record ID in case success or error message
    *
    * @param \mysqli $mysqli The mysqli connection object.
    * @param string $table_name The name of the table.
    * @param string $table_prefix The prefix for the ID field (e.g., 'rec' for 'rec_ID').
    *                             A '_' will be appended if not present.
    * @param int|string|array $rec_ID A single ID, a comma-separated string of IDs, or an array of IDs to delete.
    * @return bool|string True on successful deletion, or an error message string on failure or invalid input.
    */
    function mysql__delete($mysqli, $table_name, $table_prefix, $rec_ID){

        $ret = null;

        $rec_ID = prepareIds($rec_ID);

        if(!empty($rec_ID)){

            if (substr($table_prefix, -1) !== '_') {
                $table_prefix = $table_prefix.'_';
            }

            $query = SQL_DELETE."`$table_name`".SQL_WHERE.predicateId($table_prefix.'ID', $rec_ID);

            $res = $mysqli->query($query);

            if(!$res){
                $ret = $mysqli->error;
            }else{
                $ret = true;
            }

        }else{
            $ret = 'Invalid set of record identificators';
        }
        return $ret;
    }


    /**
    * insert or update record for given table
    *
    * returns record ID in case success or error message
    *
    * @param \mysqli $mysqli The mysqli connection object.
    * @param string $table_name The name of the table.
    * @param string|array $table_prefix If a string, it's the prefix for the primary key field (e.g., 'rec' for 'rec_ID').
    *                                   A '_' will be appended if not present.
    *                                   If an array, it's a configuration array mapping field names to their properties
    *                                   (like 'dty_Role', 'dty_Type'), used to identify the primary key and filter fields.
    * @param array $record An associative array representing the record data (fieldname => value).
    *                      Field names not matching the prefix (if string $table_prefix) or not in the config array
    *                      (if array $table_prefix) are ignored.
    *                      Values for fields ending in 'ID' (case-insensitive) are treated as integers; others as strings.
    *                      `dtl_Geo` values are handled with `ST_GeomFromText`.
    * @param bool $allow_insert_with_newid If true and inserting a record with an integer primary key,
    *                                      a negative record ID in $record will be made positive and used as the new ID.
    *                                      Default is false.
    * @return int|string|null The ID of the inserted/updated record (for integer primary keys),
    *                         true for non-integer primary key updates if successful,
    *                         or null/error string on failure.
    */
    function mysql__insertupdate($mysqli, $table_name, $table_prefix, $record, $allow_insert_with_newid=false){

        $ret = null;
        $primary_field_type = 'integer';

        if(is_array($table_prefix)){ //fields

            $fields = array();
            foreach($table_prefix as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual') {continue;}
                if(@$field_config['dty_Role']=='primary'){
                    $primary_field = $fieldname;
                    $primary_field_type = $field_config['dty_Type'];
                }
                $fields[] = $fieldname;
            }

        }else{
            if (substr($table_prefix, -1) !== '_') {
                $table_prefix = $table_prefix.'_';
            }
            $primary_field = $table_prefix.'ID';
        }

        //if integer it is assumed autoincrement
        if($primary_field_type=='integer'){
            $rec_ID = intval(@$record[$primary_field]);
            $isinsert = ($rec_ID<1);
        }else{
            $rec_ID = @$record[$primary_field];
            if($rec_ID==null){
                //assign guid?
            }else{
                //check insert or update
                $res = mysql__select_value($mysqli,
                    "SELECT `$primary_field` FROM `$table_name` WHERE `$primary_field`=?", array('s', $rec_ID));
                $isinsert = ($res==null);
            }
        }


        if($isinsert){
            $query = "INSERT into `$table_name` (";
            $query2 = ') VALUES (';
        }else{
            $query = "UPDATE `$table_name` set ";
        }

        $params = array();
        $params[0] = '';

        foreach($record as $fieldname => $value){

            if(is_array($table_prefix)){

                if(!in_array($fieldname, $fields)) {continue;}

            }elseif(strpos($fieldname, $table_prefix)!==0){ //ignore fields without prefix
                //$fieldname = $table_prefix.$fieldname;
                continue;
            }

            $fieldname = preg_replace(REGEX_ALPHANUM, "", $fieldname);//for snyk

            if($isinsert){
                if($primary_field_type=='integer' && $fieldname==$primary_field){ //ignore primary field for update
                    if($allow_insert_with_newid){
                        $value = abs(intval($value));
                    }else{
                        continue;
                    }
                }
                $query = $query."`$fieldname`, ";

                if($fieldname=='dtl_Geo'){
                    $query2 = $query2.'ST_GeomFromText(?), ';
                }else{
                    $query2 = $query2.'?, ';
                }

            }else{
                if($fieldname==$primary_field){ //ignore primary field for update
                    continue;
                }
                if($fieldname=='dtl_Geo'){
                    $query = $query.'dtl_Geo=ST_GeomFromText(?), ';
                }else{
                    $query = $query."`$fieldname`=?, ";
                }
            }

            $dtype = ((substr($fieldname, -2) === 'ID' || substr($fieldname, -2) === 'Id')?'i':'s');
            if($fieldname == 'ulf_ObfuscatedFileID') {$dtype = 's';}//exception
            //elseif($fieldname == 'dtl_Value') $dtype = 'b';//exception

            $params[0] = $params[0].$dtype;
            if($dtype=='i' && $value==''){
                $value = null;
            }
            array_push($params, $value);
        }

        $query = substr($query,0,strlen($query)-2);
        if($isinsert){
            $query2 = substr($query2,0,strlen($query2)-2).")";
            $query = $query.$query2;
        }else{
            $query = $query.SQL_WHERE.$primary_field.'=?';

            if($primary_field_type=='integer'){
                $params[0] = $params[0].'i';
            }else{
                $params[0] = $params[0].'s';
            }
            array_push($params, $rec_ID);
        }

        $result = mysql__exec_param_query($mysqli, $query, $params);

        if($result===true && $primary_field_type=='integer'){
            $result = ($isinsert) ?$mysqli->insert_id :$rec_ID;
        }//for non-numeric it returns null


        return $result;
    }

    /**
     * Executes a SELECT SQL query, optionally using prepared statements if parameters are provided.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $query The SQL query string. Can contain '?' placeholders if $params are used.
     * @param array|null $params An array for parameterized queries. The first element must be a string
     *                           specifying the types of the parameters (e.g., 'isd' for integer, string, double).
     *                           Subsequent elements are the parameter values. If null, a direct query is executed.
     * @return \mysqli_result|false A mysqli_result object on success, or false on failure.
     */
    function mysql__select_param_query($mysqli, $query, $params=null){

        $result = false;

        if ($params==null || !is_array($params) || count($params) < 2) {// not parameterised
            $result = $mysqli->query($query);
        }else{

            $stmt = $mysqli->prepare($query);
            if($stmt){
                //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
                call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));
                if($stmt->execute()){
                    $result = $stmt->get_result();
                }else{
                    $result = false;
                }
                $stmt->close();
            }else{
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Executes a MySQL query with optional parameters and returns the result or error.
     *
     * For `INSERT` and `UPDATE` queries, returns the affected rows or insert ID.
     * If the query fails, returns the MySQL error message.
     *
     * @param mysqli $mysqli - The MySQLi connection object
     * @param string $query - The SQL query with placeholders for parameters
     * @param array|null $params - An array of parameters, first element is a string of types (e.g., 'sdi')
     * @param bool $return_affected_rows - If true, return affected rows or insert ID (default: false)
     *
     * @return mixed - True on success, MySQL error string on failure, affected rows or insert ID if requested
     */
    function mysql__exec_param_query($mysqli, $query, $params = null, $return_affected_rows = false) {

        // Determine if the query is an INSERT operation
        $is_insert = (stripos($query, 'INSERT') === 0);
        $result = false;

        // Non-parameterized query execution
        if (isEmptyArray($params)) {
            if ($mysqli->query($query)) {
                $result = handleResult($mysqli, $is_insert, $return_affected_rows);
            } else {
                $result = $mysqli->error;
            }
            return $result;
        }

        // Parameterized query execution
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));

            if (!$stmt->execute()) {
                $result = $stmt->error;
            } else {
                $result = handleResult($mysqli, $is_insert, $return_affected_rows);
            }

            $stmt->close(); // Close the statement
        } else {
            $result = $mysqli->error;
        }

        return $result;
    }

    /**
     * Handles the result of the query, returning the affected rows or insert ID if required.
     *
     * @param mysqli $mysqli - The MySQLi connection object
     * @param bool $is_insert - Whether the query is an INSERT operation
     * @param bool $return_affected_rows - Whether to return affected rows or insert ID
     *
     * @return mixed - True on success, insert ID or affected rows if requested
     */
    function handleResult($mysqli, $is_insert, $return_affected_rows) {
        if ($return_affected_rows) {
            return $is_insert ? $mysqli->insert_id : $mysqli->affected_rows;
        }
        return true;
    }

    /**
     * Converts an array of values to a format suitable for `call_user_func_array`.
     *
     * @param array $arr - The array of values (first element is the types string)
     * @return array - The array with references for binding parameters
     */
    function referenceValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key]; // Make reference for call_user_func_array
        }
        return $refs;
    }

    /**
    * Execute mysql script file
    *
    * @param string $database_name_full The full name of the database.
    * @param string $script_file The name of the SQL script file.
    * @param string|null $dbfolder Optional path to the folder containing the script. If null,
    *                              defaults to `HEURIST_DIR . 'admin/setup/dbcreate/'`.
    * @return bool|array True on success, or an array with an error code and message(s) on failure.
    */
    function mysql__script($database_name_full, $script_file, $dbfolder=null) {
        global $errorScriptExecution;

        $error = '';
        $res = false;

        //0: use 3d party PDO mysqldump, 2 - call mysql via shell (default)
        $dbScriptMode = defined('HEURIST_DB_MYSQL_SCRIPT_MODE')?HEURIST_DB_MYSQL_SCRIPT_MODE :0;

        $script_file = basename($script_file);
        if($dbfolder!=null){
            $script_file = $dbfolder.$script_file;
        }else{
            //all scripts are in admin/setup/dbcreate
            $script_file = HEURIST_DIR.'admin/setup/dbcreate/'.$script_file;
        }


        if(!file_exists($script_file)){
            $res = 'Unable to find sql script '.htmlspecialchars($script_file);
        }else{

            if($dbScriptMode==2){
                if (!defined('HEURIST_DB_MYSQLPATH') || !file_exists(HEURIST_DB_MYSQLPATH)){

                    $msg = 'The path to mysql executable has not been correctly specified. '
                    .'Please ask your system administrator to fix this in the heuristConfigIni.php '
                    .'(note the settings required for a single server vs mysql running on a separate server)';

                    return array(HEURIST_SYSTEM_CONFIG, $msg);
                }
            }else {
                $dbScriptMode = 0;
            }

            //  cat sourcefile.sql | sed '/^CREATE DATABASE/d' | sed '/^USE/d' > destfile.sql
            //  cat sourcefile.sql | sed '/^CREATE DATABASE/d' | sed '/^USE/d' | mysql newdbname

            //$dbScriptMode = 0; //disable all others

            if($dbScriptMode==2){  //DEFAULT
                //shell script - server admin must specify "local" login-path with mysql_config_editor
                // mysql_config_editor set --login-path=local --host=127.0.0.1 --user=username --password

                $arr_out = array();
                $res2 = null;

                $cmd = escapeshellcmd(HEURIST_DB_MYSQLPATH);
                if(strpos(HEURIST_DB_MYSQLPATH,' ')>0){
                    $cmd = '"'.$cmd.'"';
                }

                $port = '';
                if(HEURIST_DB_PORT){
                    $port = " -P ".HEURIST_DB_PORT;
                }

                /* remarked temporary to avoid security warnings */
                $cmd = $cmd         //." --login-path=local "
                ." -h ".HEURIST_DBSERVER_NAME." ".$port
                ." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD
                ." --default-character-set=utf8mb4 "
                ." --max-allowed-packet=1024M "
                .' --init-command="SET SESSION FOREIGN_KEY_CHECKS=0; SET SESSION UNIQUE_CHECKS=0; SET SESSION SQL_LOG_BIN=0;" '
                ." -D ".escapeshellarg($database_name_full)." < ".escapeshellarg($script_file). ' 2>&1';

                $shell_res = exec($cmd, $arr_out, $res2);

                if ($res2 != 0) { // $shell_res is either empty or contains $arr_out as a string
                    $error = 'Error. Shell returns status: '.($res2!=null?intval($res2):'unknown')
                        .'. Output: '.(!isEmptyArray($arr_out)?print_r($arr_out, true):'');
                }else{
                    $res = true;
                }


            }else{ //3d party function that uses PDO

                if(!function_exists('execute_db_script')){
                        include_once dirname(__FILE__).'/../utilities/DbExecuteScript.php';// used to load procedures/triggers
                }
                if(db_script($database_name_full, $script_file, false)){
                        $res = true;
                }else{
                        $error = $errorScriptExecution;
                }
            }

            if(!$res){
                $res = 'Unable to execute script '.htmlspecialchars(basename($script_file)).' for database '.$database_name_full;
            }
        }

        if($res!==true){
            $res = array(HEURIST_DB_ERROR, $res, $error);
        }

        return $res;
    }
    
    /**
     * Returns the Heurist database schema version.
     *
     * Retrieves version components (sys_dbVersion, sys_dbSubVersion, sys_dbSubSubVersion)
     * from the `sysIdentification` table and concatenates them.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @return string|null The database version string (e.g., "6.5.0") or null if not found or on error.
     */
    function getDbVersion($mysqli){
        
        $db_version = null;
        
        if (is_a($mysqli, 'mysqli')){

            $system_settings = getSysValues($mysqli);
            if(is_array($system_settings)){

                $db_version = $system_settings['sys_dbVersion'].'.'
                              .$system_settings['sys_dbSubVersion'].'.'
                              .$system_settings['sys_dbSubSubVersion'];
            }
        }        
        
        return $db_version;
        
    }
    

    /**
     * Returns all values from the `sysIdentification` table for the current database.
     *
     * @todo This function could potentially be moved to a more specific entity class related to system identification.
     * @param \mysqli $mysqli The mysqli connection object.
     * @return array|null An associative array of all columns and their values from the
     *                    `sysIdentification` table, or null if the query fails or no data is found.
     */
    function getSysValues($mysqli){

        $sysValues = null;

        if($mysqli){
            $res = $mysqli->query('select * from sysIdentification');
            if ($res){
                $sysValues = $res->fetch_assoc();
                $res->close();
            }

        }
        return $sysValues;
    }

    /**
     * Checks if a MySQL stored function exists in the current database.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $name The name of the function to check.
     * @return bool True if the function exists, false otherwise.
     */
    function isFunctionExists($mysqli, $name){
        $res = false;
        try{

             // search function
             $res = $mysqli->query('SHOW CREATE FUNCTION '.$name);
             if($res){
                $row2 = mysqli_fetch_row($res);
                if($row2){
                    $res = true;
                 }
             }

        } catch (Exception $e) {
        }
        return $res;
    }


    /**
    * This function is called on login
    * Validates the presence of essential Heurist database functions (like `getEstDate`).
    * If a key function is missing, it attempts to recreate them by executing the
    * `addProceduresTriggers.sql` script.
    *
    * @param \mysqli $mysqli The mysqli connection object.
    * @return bool|array True if functions exist or are successfully recreated,
    *                    or an array with error details if script execution fails.
    */
    function checkDatabaseFunctions($system){

            $res = false;

            if(!isFunctionExists($system->getMysqli(), 'getEstDate')){ //getTemporalDateString need drop old functions
                $res = mysql__script($system->dbnameFull(), 'addProceduresTriggers.sql');
            }else{
                $res = true;
            }

            return $res;
    }

    /**
     * Checks for the presence of database functions required for duplication detection.
     *
     * Specifically, it checks for `NEW_LIPOSUCTION_255`. If missing, it attempts
     * to create it by executing the `addFunctions.sql` script.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @return bool|array True if the function exists or is successfully created,
     *                    or an array with error details if script execution fails.
     */
    function checkDatabaseFunctionsForDuplications($system){

         if(!isFunctionExists($system->getMysqli(), 'NEW_LIPOSUCTION_255')){
                $res = mysql__script($system->dbnameFull(), 'addFunctions.sql');
         }else{
                $res = true;
         }

         return $res;

    }

    /**
     * Recreates the `recLinks` table, which caches record relationships.
     *
     * If `$is_forced` is true or the `recLinks` table does not exist, the table is dropped (if exists)
     * and then recreated by executing `addProceduresTriggers.sql` (to ensure triggers are up-to-date)
     * and `sqlCreateRecLinks.sql` (to populate the table).
     *
     * @param \hserv\System $system The system object.
     * @param bool $is_forced If true, the `recLinks` table will be recreated even if it already exists.
     * @return bool True on success, false on failure (errors will be added to the system object).
     */
    function recreateRecLinks($system, $is_forced)
    {

        $mysqli = $system->getMysqli();

        $res = true;
        $is_table_exist = hasTable($mysqli, 'recLinks');

        if($is_forced || !$is_table_exist){
                //recreate cache
                if($is_table_exist){

                    $query = "drop table IF EXISTS recLinks";
                    if (!$mysqli->query($query)) {
                        $system->addError(HEURIST_DB_ERROR, 'Cannot drop table cache table: ' . $mysqli->error);
                        $res = false;
                    }

                }else{
                    //recreate triggers if recLinks does not exist
                }
                if($res){

                    $res = mysql__script($system->dbnameFull(), 'addProceduresTriggers.sql');
                    if($res===true){
                        $res = mysql__script($system->dbnameFull(), 'sqlCreateRecLinks.sql');
                    }
                }

                if($res!==true){
                    $system->addErrorArr($res);
                    $res = false;
                }

        }
        return $res;
    }

    /**
     * Recreates and optionally populates the `recDetailsDateIndex` table.
     *
     * This table caches parsed min/max dates from temporal data in `recDetails` for faster querying.
     * If `$json_for_record_details` is true (legacy, not directly used for this decision anymore but implies data conversion intention),
     * it also converts plain string temporal values in `recDetails` to JSON format.
     *
     * The process involves:
     * 1. Dropping and recreating `recDetailsDateIndex` if `$offset` is 0.
     * 2. If `$offset` is 0, recreating triggers by running `addProceduresTriggers.sql`.
     * 3. If `$need_populate` is true:
     *    a. Iterating through date-type details in `recDetails`.
     *    b. Parsing the temporal value.
     *    c. If the date is not a simple YYYY format or parsing results in a complex temporal object,
     *       the original `dtl_Value` in `recDetails` is updated to its JSON representation.
     *    d. Inserting the calculated min/max dates into `recDetailsDateIndex`.
     *    e. Logging errors and complex temporal conversions.
     *    f. Reporting progress if `$progress_report_step` is provided.
     *
     * @param \hserv\System $system The system object.
     * @param bool $need_populate If true, the `recDetailsDateIndex` table will be populated with data from `recDetails`.
     * @param bool $json_for_record_details If true, indicates an intention to convert plain string temporals in `recDetails` to JSON.
     *                                      (Actual conversion depends on the nature of the parsed date).
     * @param int $offset The starting offset for processing records (for batch processing). Defaults to 0.
     * @param int $progress_report_step If >= 0, progress will be reported via `DbUtils::setSessionVal`.
     *                                  The value will be "$progress_report_step,$percentage". Defaults to -1 (no progress reporting).
     * @return array|false An array with report messages on success, or false on failure.
     */
    function recreateRecDetailsDateIndex($system, $need_populate, $json_for_record_details, $offset=0, $progress_report_step=-1){

        $mysqli = $system->getMysqli();

        $dbVerSubSub = $system->settings->get('sys_dbSubSubVersion');

        $isok = true;
        $is_table_exist = hasTable($mysqli, 'recDetailsDateIndex');

        $err_prefix = '';
        $cnt = 0;
        $cnt_all = 0;
        $cnt_to_json = 0;
        $cnt_err = 0;
        $report = array();

        $log_file = $system->getSysDir().'recDetailsDateIndex.log';

        if($offset>0){
            $res = true;
        }else{
            $mysqli->query('DROP TABLE IF EXISTS recDetailsDateIndex;');
            $res = $mysqli->query("CREATE TABLE recDetailsDateIndex (
                  rdi_ID   int unsigned NOT NULL auto_increment COMMENT 'Primary key',
                  rdi_RecID int unsigned NOT NULL COMMENT 'Record ID',
                  rdi_DetailTypeID int unsigned NOT NULL COMMENT 'Detail type ID',
                  rdi_DetailID int unsigned NOT NULL COMMENT 'Detail ID',
                  rdi_estMinDate DECIMAL(15,4) NOT NULL COMMENT '',
                  rdi_estMaxDate DECIMAL(15,4) NOT NULL COMMENT '',
                  PRIMARY KEY  (rdi_ID),
                  KEY rdi_RecIDKey (rdi_RecID),
                  KEY rdi_DetailTypeKey (rdi_DetailTypeID),
                  KEY rdi_DetailIDKey (rdi_DetailID),
                  KEY rdi_MinDateKey (rdi_estMinDate),
                  KEY rdi_MaxDateKey (rdi_estMaxDate)
                ) ENGINE=InnoDB COMMENT='A cache for date fields to speed access';");
        }
        
        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot create recDetailsDateIndex', $mysqli->error);
            return false;
        }else{

            if($offset==0){

                $report[] = 'recDetailsDateIndex created';
                //recreate triggers
                $res = mysql__script($system->dbnameFull(), 'addProceduresTriggers.sql');
                if($res!==true){
                    $system->addErrorArr($res);
                    return false;
                }

                $report[] = 'Triggers to populate recDetailsDateIndex created';

            }

            if($need_populate){

            //fill database with min/max date values
            //1. find all date values in recDetails
            $query = 'SELECT dty_ID FROM defDetailTypes WHERE dty_Type="date"';
            $fld_dates = mysql__select_list2($mysqli, $query);

            $whereDateFields = predicateId('dtl_DetailTypeID',$fld_dates);

            $query = 'SELECT count(dtl_ID) FROM recDetails '.SQL_WHERE.$whereDateFields;
            $cnt_dates = mysql__select_value($mysqli, $query);
            if($offset>0){
                $cnt_dates = $cnt_dates - $offset;
            }

            $query = 'SELECT dtl_ID,dtl_RecID,dtl_DetailTypeID,dtl_Value FROM recDetails '
            .SQL_WHERE.$whereDateFields;
            if($offset>0){
                $query = $query.' LIMIT '.$offset.', 18446744073709551615';
            }
            $res = $mysqli->query($query);

            if ($res){

                if($json_for_record_details){
                    $mysqli->query('DROP TABLE IF EXISTS bkpDetailsDateIndex'); //no used anymore
                }

                if($cnt_dates<150000){
                    $keep_autocommit = mysql__begin_transaction($mysqli);
                }

                while ($row = $res->fetch_row()){
                    $dtl_ID = intval($row[0]);
                    $dtl_RecID = intval($row[1]);
                    $dtl_DetailTypeID = intval($row[2]);
                    $dtl_Value = $row[3];
                    $dtl_NewValue = '';
                    $error = '';

                    if(trim($dtl_Value)=='') {continue;}

                    $iYear = intval($row[3]);

                    if($iYear==$dtl_Value && $iYear>0 && $iYear<10000){
                        //just year
                        $is_date_simple = true;
                        $query = 'insert into recDetailsDateIndex (rdi_RecID, rdi_DetailTypeID, rdi_DetailID, rdi_estMinDate, rdi_estMaxDate)'
." values ($dtl_RecID, $dtl_DetailTypeID, $dtl_ID, $iYear, $iYear)";
                        $res5 = $mysqli->query($query);

                        if(!$res5){
                            //fails insert into recDetailsDateIndex
                            $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on index insert query:'.$query, $mysqli->error);
                            $isok = false;
                            break;
                        }
                    }else{



            //2. Create temporal object
                    $preparedDate = new Temporal( $dtl_Value );

                    if($preparedDate && $preparedDate->isValid()){

                            // saves as usual date
                            // if date is Simple, 0<year>9999 (CE) and has both month and day
                            $is_date_simple = $preparedDate->isValidSimple();
                            $dtl_NewValue_for_update = null;
                            if($is_date_simple){
                                $dtl_NewValue = $preparedDate->getValue(true);//returns simple yyyy-mm-dd
                                $dtl_NewValue_for_update = $dtl_NewValue;
                            }else{
                                $v_json = $preparedDate->getValue();
                                $dtl_NewValue_for_update = json_encode($v_json);
                                $v_json['comment'] = '';//to avoid issue with special charss
                                $dtl_NewValue = json_encode($v_json);//$preparedDate->toJSON();//json encoded string
                            }
                            if($dtl_NewValue==null || $dtl_NewValue=='' || $dtl_NewValue=='null'){
                                $error = 'Not valid date: '.$dtl_Value;
                            }else{

            //3. Validate estMin and estMax from JSON
                            $query = 'SELECT getEstDate(\''.$dtl_NewValue
                                    .'\',0) as minD, getEstDate(\''.$dtl_NewValue.'\',1) as maxD';
                            try{
                                $res2 = $mysqli->query($query);
                            }catch(Exception $e){
                                $res2 = false;
                            }

                            if($res2){
                                $row2 = $res2->fetch_row();
                                if(($row2[0]=='' && $row2[1]=='') || ($row2[0]=='0' && $row2[1]=='0')){
                                    //fails extraction estMinDate, estMaxDate
                                    $error = 'Empty min, max dates. Min:"'.
                                        htmlspecialchars($row2[0].'" Max:"'.$row2[1]).'". Query:'.$query;
                                }else{
            //4. Keep old plain string temporal object in backup table - removed
            //5A. If simple date - retain value in recDetails
            //5B. If temporal object it saves JSON in recDetails
                                    if($dtl_Value != $dtl_NewValue_for_update){
                                        $query = 'UPDATE recDetails SET dtl_Value=? WHERE dtl_ID=?';

                                        $affected = mysql__exec_param_query($mysqli, $query,
                                                        array('si',$dtl_NewValue_for_update, $dtl_ID),true);

                                        if(!($affected>0)){
                                            //fails update recDetails  recreateRecDetailsDateIndex
                                            $system->addError(HEURIST_DB_ERROR,
                                                $err_prefix.
                                                'recreateRecDetailsDateIndex. Error on recDetails update query:'
                                                .$query.' ('.$dtl_NewValue_for_update.', '.$dtl_ID.')  ', $mysqli->error);
                                            $isok = false;
                                            break;
                                        }
                                    }


            //6. update recDetailsDateIndex should be updated by trigger
                                    $mysqli->query('delete ignore from recDetailsDateIndex where rdi_DetailID='.$dtl_ID);

                                    $mindate = floatval($row2[0]);
                                    $maxdate = floatval($row2[1]);

                                    $query = 'insert into recDetailsDateIndex (rdi_RecID, rdi_DetailTypeID, rdi_DetailID, rdi_estMinDate, rdi_estMaxDate)'
        ." values ($dtl_RecID, $dtl_DetailTypeID, $dtl_ID, $mindate, $maxdate)";
                                    $res5 = $mysqli->query($query);
        //getEstDate('$dtl_NewValue',0), getEstDate('$dtl_NewValue',1)

                                    if(!$res5){
                                        //fails insert into recDetailsDateIndex
                                        $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on index insert query:'.$query, $mysqli->error);
                                        $isok = false;
                                        break;
                                    }

                                }

                            }else{
                                //fails request
                                $error = 'Error on retrieve min and max dates. Query:'.$query.' '.$mysqli->error;
                            }

                            }
                    }else{
                        //unchange

                        //fails temporal parsing - wrong date
                        //$system->addError(HEURIST_ERROR, $err_prefix.'Cannot parse temporal "'.$dtl_Value);
                        $error = 'Cannot parse temporal';
                    }
                    }

                    //keep log
                    if(!$is_date_simple || $error){
                        // file_put_contents($log_file, $dtl_ID.';'.$dtl_Value.';'.$dtl_NewValue.';'.$error."\n", FILE_APPEND )
                        if(!$is_date_simple) {$cnt_to_json++;}
                        if($error){
                            $error = errorDiv($error);
                            $cnt_err++;
                        }

                        if($need_populate && $error){ //verbose output
                            $report[] = 'Rec# '.$dtl_RecID.'  '.htmlspecialchars($dtl_Value.' '
                                    .(($dtl_Value!=$dtl_NewValue)?$dtl_NewValue:'')).' '.$error;
                        }

                    }
                    if(!$error){
                        $cnt++;
                    }

                    $cnt_all++;

                    if($progress_report_step>=0 && $cnt_all%1000==0 ){
                        $percentage = intval($cnt_all*100/$cnt_dates);
                        if(DbUtils::setSessionVal($progress_report_step.','.$percentage)){
                            //terminated by user
                            $system->addError(HEURIST_ACTION_BLOCKED, 'Database Verification has been terminated by user');
                            if($cnt_dates<150000){
                                $mysqli->rollback();
                                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                            }
                            return false;
                        }
                    }
                }//while
                $res->close();

                if($isok){
                    if($cnt_dates<150000){
                        $mysqli->commit();
                    }
                }else{
                    if($cnt_dates<150000){
                        $mysqli->rollback();
                    }
                }
                if( $cnt_dates<150000 && $keep_autocommit===true) {$mysqli->autocommit(true);}

            }
        }
        }

        if($isok && $need_populate){ //verbose output
            $report[] = '<ul><li>Added into date index: '.$cnt.'</li>'
                        .'<li>Errors date pasring: '.$cnt_err.'</li>'
                        .'<li>Complex temporals: '.$cnt_to_json.'</li></ul>';
        }

        return $isok?$report:false;
    }


    /**
     * Trims an item (string) to a specified length.
     *
     * This function is often used as a callback for `array_walk`.
     * It first trims whitespace from the item, then truncates it to `$len` characters.
     *
     * @param string &$item The item to be trimmed (passed by reference).
     * @param mixed $key The key of the item in the array (unused).
     * @param int $len The maximum length to trim the item to.
     * @return void
     */
    function trim_item(&$item, $key, $len){
        if($item!='' && $item!=null){
            $item = substr(trim($item),0,$len);
        }
    }

    /**
     * Replaces null values in an array item with an empty string.
     *
     * This function is often used as a callback for `array_walk`.
     *
     * @param mixed &$item The item to check and potentially modify (passed by reference).
     * @param mixed $key The key of the item in the array (unused).
     * @return void
     */
    function replace_nulls(&$item, $key){
        if($item==null){
            $item = '';
        }
    }

    /**
     * Multi-byte safe version of strtr (character translation).
     *
     * Translates characters in `$inputStr` from `$from` to their corresponding
     * characters in `$to`.
     *
     * @param string $inputStr The input string.
     * @param string $from A string containing characters to be replaced.
     * @param string $to A string containing the replacement characters.
     * @param string $encoding The character encoding. Defaults to 'UTF-8'.
     * @return string The translated string.
     */
    function my_strtr($inputStr, $from, $to, $encoding = 'UTF-8') {
        $inputStrLength = mb_strlen($inputStr, $encoding);

        $translated = '';

        for($i = 0; $i < $inputStrLength; $i++) {
            $currentChar = mb_substr($inputStr, $i, 1, $encoding);

            $translatedCharPos = mb_strpos($from, $currentChar, 0, $encoding);

            if($translatedCharPos === false) {
                $translated .= $currentChar;
            }
            else {
                $translated .= mb_substr($to, $translatedCharPos, 1, $encoding);
            }
        }

        return $translated;
    }

    /**
     * Removes common accents from a string.
     *
     * Replaces accented characters (e.g., à, é, ñ) with their non-accented equivalents (a, e, n).
     * Handles both lowercase and uppercase accented characters.
     *
     * @param string $stripAccents The input string with potential accents.
     * @return string The string with accents removed.
     */
    function stripAccents($stripAccents){
        return my_strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝß',
                                      'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYs');
    }

    /**
     * Trims whitespace, non-breaking spaces (`&nbsp;`), and Byte Order Marks (BOM) from a string.
     *
     * Recursively removes leading/trailing instances of regular whitespace,
     * `\xC2\xA0` (UTF-8 non-breaking space), and `\xEF\xBB\xBF` (UTF-8 BOM).
     *
     * @param string $str The input string.
     * @return string The trimmed string.
     */
    function super_trim( $str ){

        $str = trim($str);
        $len = strlen($str);
        $k = strpos($str,"\xC2\xA0");
        if($k===0){
            $str = substr($str,2);
            return super_trim($str);
        }elseif($k===$len-2){
            $str = substr($str,0,$len-2);
            return super_trim($str);
        }
        $k = strpos($str,"\xEF\xBB\xBF");
        if($k===0){
            $str = substr($str,3);
            return super_trim($str);
        }elseif($k===$len-3){
            $str = substr($str,0,$len-3);
            return super_trim($str);
        }

        return $str;

        //return trim($str);//trim($str, " \n\r\t\v\x00\xC2\xA0\xEF\xBB\xBF");
    }

    /**
     * Trims, converts to lowercase, and strips accents from a string.
     *
     * Uses `super_trim`, `stripAccents`, and `mb_strtolower`.
     *
     * @param string $item The input string.
     * @return string The processed string.
     */
    function  trim_lower_accent($item){
        return mb_strtolower(stripAccents(super_trim($item)));//including &nbsp; and &xef; (BOM)
    }

    /**
     * Applies `trim_lower_accent` to an item, designed for use with `array_walk`.
     *
     * Modifies the item by reference.
     *
     * @param string &$item The item to process (passed by reference).
     * @param mixed $key The key of the item in the array (unused).
     * @return void
     */
    function  trim_lower_accent2(&$item, $key){
        $item = trim_lower_accent($item);
    }

    /**
     * Multi-byte safe case-insensitive string comparison.
     *
     * @param string $str1 The first string.
     * @param string $str2 The second string.
     * @param string|null $encoding The character encoding. Defaults to `mb_internal_encoding()`.
     * @return int < 0 if $str1 is less than $str2; > 0 if $str1 is greater than $str2, and 0 if they are equal.
     */
    function mb_strcasecmp($str1, $str2, $encoding = null) {
        if (null === $encoding) { $encoding = mb_internal_encoding();}
        return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
    }

    /**
     * Checks if a value represents a boolean true.
     *
     * Considers true, 'y', 'yes', 'true', 't', 'ok' (case-insensitive) as true.
     *
     * @param mixed $val The value to check.
     * @return bool True if the value represents true, false otherwise.
     */
    function is_true($val){
        return $val===true || (is_string($val) && in_array(strtolower($val), array('y','yes','true','t','ok')));
    }

    /**
     * Escapes special characters in an array of string values for use in an SQL statement.
     *
     * Modifies the input array by reference.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param array &$values An array of string values to be escaped (passed by reference).
     * @return void
     */
    function escapeValues($mysqli, &$values){
        foreach($values as $idx=>$v){
            $values[$idx] = $mysqli->real_escape_string($v);
        }
    }

    /**
     * Prepares a list of IDs, ensuring they are positive integers.
     *
     * Accepts a single ID, a comma-separated string of IDs, or an array of IDs.
     * Filters out non-numeric values and values less than or equal to 0 (unless $can_be_zero is true).
     *
     * @param int|string|array|null $ids The ID(s) to prepare.
     * @param bool $can_be_zero If true, allows 0 as a valid ID. Defaults to false.
     * @return array An array of valid integer IDs. Returns an empty array if input is null.
     */
    function prepareIds($ids, $can_be_zero=false){

        if($ids==null){
            return array();
        }

        if(!is_array($ids)){
            if(is_numeric($ids)){
                $ids = array($ids);
            }else{
                $ids = explode(',', $ids);
            }
        }

        $res = array();
        foreach($ids as $v){
            if (is_numeric($v) && ($v > 0 || ($can_be_zero && $v==0))){
                $res[] = intval($v);
            }
        }
        return $res;
    }

    /**
     * Prepares a list of string IDs by enclosing each in double quotes.
     *
     * Accepts a comma-separated string of IDs or an array of IDs.
     *
     * @param string|array $ids The ID(s) to prepare.
     * @return array An array of string IDs, each enclosed in double quotes.
     */
    function prepareStrIds($ids){

        if(!is_array($ids)){
            $ids = explode(',', $ids);
        }

        $ids = array_map(function ($v) {
             return '"'.$v.'"';
        }, $ids);

        return $ids;

    }

    /**
     * Constructs an SQL predicate for a field based on a list of IDs.
     *
     * Examples:
     * - `predicateId('rec_ID', 1)` returns "(`rec_ID`=1)"
     * - `predicateId('rec_ID', [1,2,3])` returns "(`rec_ID` IN (1,2,3))"
     * - `predicateId('rec_ID', [], 'AND')` returns "" (empty string)
     * - `predicateId('rec_ID', [1], 'AND')` returns " AND (`rec_ID`=1)"
     * - `predicateId('rec_ID', [])` returns "(1=0)" (SQL_FALSE)
     *
     * @param string $field The name of the database field.
     * @param int|string|array $ids A single ID, a comma-separated string of IDs, or an array of IDs.
     * @param string|null $operation Optional SQL operation (e.g., 'AND', 'OR') to prepend if IDs are present.
     * @return string The SQL predicate string.
     */
    function predicateId($field, $ids, $operation=null)
    {
        $ids = prepareIds($ids);

        $isNegate = false;
        if($operation==SQL_NOT){
            $operation='';
            $isNegate = true;
        }
        
        $cnt = count($ids);
        if($cnt==0){
            return isEmptyStr($operation)?SQL_FALSE:''; // (1=0) none
        }elseif($cnt==1){
            $q = '='.$ids[0];
            if($isNegate){
                $q = '!'.$q;
            }
        }elseif($cnt>1){
            $q = SQL_IN.implode(',',$ids).')';
            if($isNegate){
                $q = ' '.SQL_NOT.' '.$q;
            }
        }

        return (!isEmptyStr($operation)?" $operation ":'').'('.$field.$q.')';
    }

    /**
     * Validates if a comma-separated string or an array contains only integer IDs.
     *
     * @param string|array $value A comma-separated string of IDs or an array of IDs.
     * @return string|null The original comma-separated string (or joined array) if all IDs are integers,
     *                     null otherwise.
     */
    function getCommaSepIds($value)
    {
        if(is_array($value)){
            $a = $value;
        }else{
            if(substr($value, -1) === ','){
                //remove last comma
                $value = substr($value,0,-1);
            }

            $a = explode(',', $value);
        }
        $n = array_map('intval', $a);

        if(!array_diff($a, $n)){
            if(is_array($value)){
                return implode(',', $value);
            }else{
                return $value;
            }

        }else{
            return null;
        }
    }

    /**
     * Checks if the byte length of a string exceeds MySQL TEXT field limits.
     *
     * Determines an appropriate limit (32KB or 64KB) based on the difference
     * between byte length and multi-byte character length.
     *
     * @param string $dtl_Value The string value to check.
     * @return int Returns the limit exceeded (e.g., 32000 or 64000) if the length is too great,
     *             0 otherwise.
     */
    function checkMaxLength2($dtl_Value){
        $dtl_Value = trim($dtl_Value);
        $len  = strlen($dtl_Value);//number of bytes
        $len2 = mb_strlen($dtl_Value);//number of characters
        $lim = ($len-$len2<200)?64000:32000; //32768;
        if($len>$lim){   //size in bytes more than allowed limit
            return $lim;
        }else{
            return 0;
        }
    }

    /**
     * Checks if a string value exceeds the maximum length for a TEXT field, returning a user-friendly message.
     *
     * @param string $dty_Name The name of the detail type (field name) for the error message.
     * @param string $dtl_Value The string value to check.
     * @return string|null An error message if the length is exceeded, null otherwise.
     */
    function checkMaxLength($dty_Name, $dtl_Value){

        $lim = checkMaxLength2($dtl_Value);
        //number of bytes more than limit
        //limit: if number of bytes and chars is slightly different it takes 64KB
        // otherwise it is assumed utf and limit is 32KB
        if($lim>0){ //65535){  32768
            $lim2 = ($lim>32000)?64:32;
            return 'The data in field ('.$dty_Name
            .') exceeds the maximum size for a field of '.$lim2.'Kbytes. '
            .'Note that this does not mean '.$lim2.'K characters, '
            .'as Unicode uses multiple bytes per character.';
        }else{
            return null;
        }

    }

    /**
     * Gets the timestamp of the last modification to database definitions.
     *
     * Checks `rst_Modified`, `rty_Modified`, `dty_Modified`, and `trm_Modified` fields
     * in their respective tables (`defRecStructure`, `defRecTypes`, `defDetailTypes`, `defTerms`).
     * Converts the timestamp to UTC (+00:00).
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param bool $recstructure_only If true, only checks `defRecStructure`. Defaults to false.
     * @return \DateTime|false A DateTime object representing the last modification time, or false on failure.
     */
    function getDefinitionsModTime($mysqli, $recstructure_only=false)
    {
        //CONVERT_TZ(MAX(trm_Modified), @@session.time_zone, '+00:00')
        $rst_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(rst_Modified), @@session.time_zone, "+00:00") FROM defRecStructure');
        if($recstructure_only){
            $last_mod = $rst_mod;
        }else{

            $rty_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(rty_Modified), @@session.time_zone, "+00:00") FROM defRecTypes');
            $dty_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(dty_Modified), @@session.time_zone, "+00:00") FROM defDetailTypes');
            $trm_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(trm_Modified), @@session.time_zone, "+00:00") FROM defTerms');

            $last_mod = $rst_mod > $rty_mod ? $rst_mod : $rty_mod;
            $last_mod = $last_mod > $dty_mod ? $last_mod : $dty_mod;
            $last_mod = $last_mod > $trm_mod ? $last_mod : $trm_mod;
        }

        return date_create($last_mod);
    }

    /**
     * Begins a database transaction.
     *
     * Disables autocommit if it's enabled and starts a new transaction.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @return bool Returns true if autocommit was originally enabled (and thus disabled by this function),
     *              false if autocommit was already disabled.
     */
    function mysql__begin_transaction($mysqli){

        $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
        if($keep_autocommit===true || $keep_autocommit==1){
                $mysqli->autocommit(false);
                $keep_autocommit = true;
        }else{
                $keep_autocommit = false;
        }
        if (strnatcmp(phpversion(), '5.5') >= 0) {
            $mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        }

        return $keep_autocommit;
    }

    /**
     * Ends a database transaction by committing or rolling back.
     *
     * Optionally re-enables autocommit if it was disabled by `mysql__begin_transaction`.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param bool $res If true, the transaction is committed. If false, it's rolled back.
     * @param bool $keep_autocommit If true, autocommit will be re-enabled.
     * @return void
     */
    function mysql__end_transaction($mysqli, $res, $keep_autocommit){

        if($res){
            $mysqli->commit();
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
    }

    /**
     * Updates or retrieves a progress value stored in a session file.
     *
     * Session files are stored in `HEURIST_SCRATCH_DIR` named `session<session_id>`.
     *
     * @param \mysqli|null $mysqli The mysqli connection object (currently unused in this function's logic
     *                             but kept for historical reasons or potential future use - to store progress in database table).
     * @param int $session_id The session ID.
     * @param bool $is_init Unused parameter.
     * @param string|null $value If not null, this value is written to the session file.
     *                           If 'REMOVE', the session file is deleted.
     *                           If null, the current value from the session file is returned.
     * @return string|null The current progress value, 'terminate' if removed or previously terminated,
     *                     or null if session_id is invalid or scratch directory is not defined.
     */
    function mysql__update_progress($mysqli, $session_id, $is_init, $value){

        // Normalize session_id to string
        if (is_int($session_id)) {
            $session_id = (string)$session_id;
        } elseif (!is_string($session_id)) {
            return null;
        }

        // Validate: 1–15 digits only
        if (!preg_match('/^\d{1,15}$/', $session_id)) {
            return null;
        }
        
        if(!defined('HEURIST_SCRATCH_DIR')) {return null;}

        $res = null;

        $session_file = HEURIST_SCRATCH_DIR.'session'.$session_id;
        $is_exist = file_exists($session_file);

        if($value==='REMOVE'){
            if($is_exist) {fileDelete($session_file);}
            $res = 'terminate';
        }else{
            //get
            if($is_exist) {
                $res = file_get_contents($session_file);
            }

            if($value!=null && $res!='terminate'){ //already terminated
                clearstatcache(true, $session_file);
                file_put_contents($session_file,  (string)$value, LOCK_EX);
                $res = $value;
            }
        }
        return $res;
    }


    /**
     * Validates the presence of all essential Heurist tables in a given or current database.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string|null $db_name Optional database name. If null, uses the current database.
     * @return array|string An array of missing table names if validation passes but some tables are missing.
     *                      A string with an error message if the `SHOW TABLES` query fails or a MySQL connection error occurs.
     *                      An empty array if all essential tables are present.
     */
    function hasAllTables($mysqli, $db_name=null){

        $query = '';
        if($db_name!=null){
            $query = 'FROM `'.$db_name.'`';
        }

        $list = mysql__select_list2($mysqli, "SHOW TABLES $query", 'strtolower');


        $mysql_gone_away_error = $mysqli && $mysqli->errno==2006;
        if($mysql_gone_away_error){

            return 'There is database server intermittens. '.CRITICAL_DB_ERROR_CONTACT_SYSADMIN;

        }elseif($mysqli->error){

            return $mysqli->error;

        }else{

    /*not used
    defcrosswalk,defontologies,defrelationshipconstraints,defurlprefixes,
    recthreadedcomments,sysdocumentation,syslocks,usrhyperlinkfilters,
    */

    //auto recreated
    //'reclinks'

    //recreated via upgrade
    //'recdetailsdateindex','sysdashboard','sysworkflowrules','usrrecpermissions','usrworkingsubsets'
    //

            $check_list = array(
    'defcalcfunctions','defdetailtypegroups','defdetailtypes','deffileexttomimetype',
    'defrecstructure','defrectypegroups','defrectypes','defterms','deftermslinks',
    'deftranslations','defvocabularygroups','recdetails','recforwarding','records',
    'recsimilarbutnotdupes','recuploadedfiles','sysarchive','sysidentification',
    'sysugrps','sysusrgrplinks','usrbookmarks','usrrectaglinks','usrreminders',
    'usrremindersblocklist','usrreportschedule','usrsavedsearches','usrtags',
    'recdetailsdateindex','sysdashboard','sysworkflowrules','usrrecpermissions','usrworkingsubsets'
    );

            $missed = array_diff($check_list, $list);

            return $missed;
        }
    }

    /**
     * Creates a table in the database.
     *
     * Optionally drops the table if it already exists and `$recreate` is true.
     *
     * @param \hserv\System $system The system object.
     * @param string $table_name The name of the table to create.
     * @param string $query The SQL `CREATE TABLE` statement.
     * @param bool $recreate If true, drops the table if it exists before creating. Defaults to false.
     * @return array An array with two elements: a boolean indicating if an action was taken (true if created/recreated),
     *               and a string message (e.g., "$table_name created", "$table_name already exists").
     * @throws \Exception If the table creation query fails.
     */
    function createTable($system, $table_name, $query, $recreate = false){

        $mysqli = $system->getMysqli();

        if($recreate || !hasTable($mysqli, $table_name)){

            $res = $mysqli->query('DROP TABLE IF EXISTS '.$table_name);

            $res = $mysqli->query($query);
            if(!$res){
                $msg = "Cannot create $table_name";
                $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                throw new Exception($msg);
            }
            $res = array(true, "$table_name created");
        }else{
            $res = array(false, "$table_name already exists");
        }
        return $res;
    }

    /**
     * Alters a table to add or modify a column.
     *
     * @param \hserv\System $system The system object.
     * @param string $table_name The name of the table to alter.
     * @param string $field_name The name of the field to add or modify.
     * @param string $query The SQL `ALTER TABLE` statement (typically an `ADD COLUMN` clause).
     * @param bool $modify_if_exists If true and the column already exists, attempts to modify it
     *                                (by changing `ADD COLUMN` to `MODIFY` in the query and removing `AFTER` clause).
     *                                Defaults to false.
     * @return array An array with two elements: a boolean indicating if an action was taken (true if added/altered),
     *               and a string message.
     * @throws \Exception If the alter table query fails.
     */
    function alterTable($system, $table_name, $field_name, $query, $modify_if_exists = false){

        $mysqli = $system->getMysqli();

        $column_exists = hasColumn($mysqli, $table_name, $field_name);

        $rep1 = 'add';
        $rep2 = 'added';

        if($column_exists && $modify_if_exists){
            $query = str_replace('ADD COLUMN','MODIFY',$query);
            if(stripos($query,' AFTER `')>0){
                $query = stristr($query,' AFTER `',true);
            }
            $column_exists = false;
            $rep1 = 'alter';
            $rep2 = 'altered';
        }

        if(!$column_exists){ //column not defined
            $res = $mysqli->query($query);
            if(!$res){
                $msg = "Can not $rep1 field $field_name to $table_name";
                $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                throw new Exception($msg);
            }
            $res = array(true, "$table_name: $field_name $rep2");
        }else{
            $res = array(false, "$table_name: $field_name already exists");
        }

        return $res;
    }

    /**
     * Checks if a table exists in the specified (or current) database.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $table_name The name of the table to check.
     * @param string|null $db_name Optional. The name of the database. If null, checks in the current database.
     * @return bool True if the table exists, false otherwise.
     */
    function hasTable($mysqli, $table_name, $db_name=null){

            $query = '';
            if($db_name!=null){
                $query = 'FROM `'.$db_name.'`';
            }

            $value = mysql__select_value($mysqli, "SHOW TABLES $query LIKE '$table_name'");
            $not_exist = ($value==null || $value=='');

            return !$not_exist;
    }

    /**
     * Checks if a column exists in a given table, and optionally if it has a specific data type.
     *
     * @param \mysqli $mysqli The mysqli connection object.
     * @param string $table_name The name of the table.
     * @param string $column_name The name of the column to check.
     * @param string|null $db_name Optional. The name of the database. If null, uses the current database.
     * @param string|null $given_type Optional. The expected data type of the column (e.g., 'varchar(255)').
     *                                If provided, the function returns true only if the column exists AND matches this type.
     * @return bool True if the column exists (and matches type if specified), false otherwise.
     */
    function hasColumn($mysqli, $table_name, $column_name, $db_name=null, $given_type=null){

        if($db_name==null){
            $db_name = '';
        }else{
            $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name); //for snyk
            $db_name = "`$db_name`.";
        }

        $query = "SHOW COLUMNS FROM $db_name`$table_name` LIKE '$column_name'";

        $res = $mysqli->query($query);
        $row_cnt = 0;
        if($res) {
            $row_cnt = $res->num_rows;

            if($row_cnt>0 && $given_type!=null){
                $row = $res->fetch_assoc();
                return $row['Type']==$given_type;
            }


            $res->close();
        }
        return $row_cnt>0;
    }

    /**
     * Checks and updates the ENUM definition for the `sysUGrps.ugr_Enabled` column.
     *
     * Ensures the `ugr_Enabled` column includes all necessary ENUM values
     * ('y','n','y_no_add','y_no_delete','y_no_add_delete'). If not, it attempts to
     * alter the table to update the ENUM definition.
     *
     * @todo This function seems to duplicate some functionality of `hasColumn` and `alterTable`
     *       and might be a candidate for removal or refactoring.
     *
     * @param \hserv\System $system The system object.
     * @param string $db_source Optional. The full name of the database to check.
     *                          Defaults to `$system->dbnameFull()` if defined and `$db_source` is empty.
     * @return bool True if the column has the correct ENUM definition or was successfully updated,
     *              false on error (errors are added to the system object).
     */
    function checkUserStatusColumn($system, $db_source = ''){

        if(empty($db_source) && $system->dbnameFull()){
            $db_source = $system->dbnameFull();
        }

        $mysqli = $system->getMysqli();

        // Check that sysUGrps.ugr_Enabled has y_no_add, y_no_delete, y_no_add_delete
        $validate_query = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '". $db_source ."' AND TABLE_NAME = 'sysUGrps' AND COLUMN_NAME = 'ugr_Enabled'";

        $res = $mysqli->query($validate_query);

        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot check available user permissions.<br>Please contact the Heurist team, if this persists.');
            return false;
        }

        $result = $res->fetch_row()[0];
        if(strpos($result, "'y','n','y_no_add','y_no_delete','y_no_add_delete'") === false){ // check if all values are accounted for

            // Update enum values
            $update_query = "ALTER TABLE sysUGrps MODIFY COLUMN ugr_Enabled ENUM('y','n','y_no_add','y_no_delete','y_no_add_delete')";
            $res = $mysqli->query($update_query);

            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Unable to update user permissions column.<br>Please contact the Heurist team, if this persists.');
                return false;
            }
        }

        return true;
    }

?>