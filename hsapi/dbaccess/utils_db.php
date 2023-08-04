<?php
//@TODO convert to class

    /**
    *  Database utilities :   mysql_ - prefix for function
    *
    *  mysql__connection - establish connection
    *  mysql__usedatabase
    *  mysql__create_database
    *  mysql__drop_database
    * 
    *  mysql__getdatabases4 - get list of databases
    * 
    *  mysql__select_assoc - returns array  key_column(first filed)=>array(field=>val,....)
    *  mysql__select_assoc2 - returns array  key_column=>val_column for given table
    *  mysql__select_list - returns array of one column values
    *  mysql__select_value   - return the first column of first row
    *  mysql__select_row   - returns first row
    *  mysql__select_row_assoc - returns first row assoc fieldnames
    *  mysql__select_all
    *  mysql__duplicate_table_record
    *  mysql__insertupdate
    *  mysql__exec_param_query
    *  mysql__delete
    *  mysql__begin_transaction
    *
    *  getSysValues - Returns values from sysIdentification
    *  isFunctionExists - verifies that mysql stored function exists
    *  checkDatabaseFunctions - checks that all db functions reauired for H4 exists and recreates them if need it
    *  trim_item
    *  stripAccents
    *  prepareIds
    *  checkMaxLength - check max length for TEXT field
    * 
    *  updateDatabaseToLatest - make changes in database structure according to the latest version
    *  recreateRecLinks
    *  recreateRecDetailsDateIndex
    * 
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
    /**
    * Connect to db server 
    *
    * @param mixed $dbHost
    * @param mixed $dbUsername
    * @param mixed $dbPassword
    *
    * @return a MySQL link identifier on success or array with code and error message on failure.
    */
    function mysql__connection($dbHost, $dbUsername, $dbPassword, $dbPort=null){

        if(null==$dbHost || $dbHost==""){
            return array(HEURIST_SYSTEM_FATAL, "Database server is not defined. Check your configuration file");
        }

        try{
            $mysqli = mysqli_init();
            //debug mode mysqli_report(MYSQLI_REPORT_ALL);
            mysqli_report(MYSQLI_REPORT_STRICT); //MYSQLI_REPORT_ERROR | 
            $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 1);
            $mysqli->real_connect($dbHost, $dbUsername, $dbPassword, null, $dbPort);
            //if (!$mysqli->set_charset("utf8mb4")) {
            //    return array(HEURIST_SYSTEM_FATAL, 'Error loading character set utf8mb4', $mysqli->error);
            //}       
            
        } catch (Exception $e)  {
            //return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }
        if(!$mysqli){
            return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }
        
        /* check connection */
        if (mysqli_connect_errno()) {
            return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }
        return $mysqli;
    }

    /**
    * open database
    * 
    * @param mixed $dbname
    */
    function mysql__usedatabase($mysqli, $dbname){
        
        if($dbname){
            
            list($database_name_full, $database_name) = mysql__get_names( $dbname );
            
            $res = mysql__check_dbname($dbname);           
            if($res===true){
                $success = $mysqli->select_db($database_name_full);
                if(!$success){
                    $db_exists = mysql__select_value($mysqli, "SHOW DATABASES LIKE '$database_name_full'");

                    if($db_exists == null){
                        return array(HEURIST_ACTION_BLOCKED, "The requested database '".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8')."' does not exist");
                    }else{
                        return array(HEURIST_INVALID_REQUEST, "Could not open database ".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8'));
                    }
                }
            }else{
                return $res;
            }

            //$mysqli->query('SET CHARACTER SET utf8mb4'); //utf8 is utf8mb3 by default
            //$mysqli->query('SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $mysqli->query('SET NAMES utf8mb4');
        }
        return true;
    }
    
    //
    // Avoid illegal chars in db
    //
    function mysql__check_dbname($db_name){
        
        $res = true;
        if (preg_match('[\W]', $db_name)){
            $res = array(HEURIST_INVALID_REQUEST, 
                'Only letters, numbers and underscores (_) are allowed in the database name');
        }
        return $res;
    }
    
    //
    //
    //
    function mysql__create_database( $mysqli, $db_name ){

        $res = mysql__check_dbname($db_name);

        // Avoid illegal chars in db
        if ($res===true) {
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
        }
        return $res;
    }
    
    //
    //
    //
    function mysql__drop_database( $mysqli, $db_name ){

        return $mysqli->query('DROP DATABASE '.$db_name);
    }

    //
    // get database name with and without hdb prefix
    //
    function mysql__get_names( $db=null ){
    
        if($db==null){
            $database_name = HEURIST_DBNAME;
            $database_name_full = HEURIST_DBNAME_FULL;
        }else{
            if(strpos($db, HEURIST_DB_PREFIX)===0){
                $database_name_full = $db;
                $database_name = substr($db,strlen(HEURIST_DB_PREFIX));
            }else{
                $database_name = $db;
                $database_name_full = HEURIST_DB_PREFIX.$db;
            }
        }
        return array($database_name_full, $database_name);
    }
    
    
    /**
    * returns list of databases as array
    * @param    mixed $with_prefix - if false it remove "hdb_" prefix
    * @param    mixed $email - current user email
    * @param    mixed $role - admin - returns database where current user is admin, user - where current user exists
    */
    function mysql__getdatabases4($mysqli, $with_prefix = false, $starts_with=null,
                             $email = null, $role = null, $prefix=HEURIST_DB_PREFIX)
    {
        
        if($starts_with!=null){
            $where = "'hdb_$starts_with%'";
        }else{
            $where = "'hdb_%'";    
        }
        
        $query = "show databases where `database` like $where";
        $res = $mysqli->query($query);
        $result = array();
        $isFilter = ($email != null && $role != null);

        if($res){
            while ($row = $res->fetch_row()) {
                $database  = $mysqli->real_escape_string($row[0]);
                $test = strpos($database, $prefix);
                if ($test === 0) {
                    if ($isFilter) {
                        if ($role == 'user') {
                            $query = "select ugr_ID from " . $database . ".sysUGrps where ugr_eMail='" . addslashes($email) . "'";
                        } else if ($role == 'admin') {
                            $query = "select ugr_ID from " . $database . ".sysUGrps, " . $database .".sysUsrGrpLinks".
                            " left join sysIdentification on ugl_GroupID = sys_OwnerGroupID".
                            " where ugr_ID=ugl_UserID and ugl_Role='admin' and ugr_eMail='" . addslashes($email) . "'";
                        }
                        if ($query) {
                            $res2 = $mysqli->query($query);
                            $cnt = $res2->num_rows; // mysql_num_rows($res2);
                            $res2->close();
                            if ($cnt < 1) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }
                    if ($with_prefix) {
                        array_push($result, $database);
                    } else {
                        // delete the prefix
                        array_push($result, substr($database, strlen($prefix)));
                    }
                }
            }//while
            $res->close();
        }//if

        natcasesort($result); // case insensetive order
        $result = array_values($result); // correct array indexes, for json object

        return $result;

    }

    /**
    * returns array  key_column=>val_column for given table
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
    */
    function mysql__select_assoc($mysqli, $query):array{
        
        $matches = array();
        if($mysqli && $query){
            
            $res = $mysqli->query($query);
            if ($res){
                while ($row = $res->fetch_assoc()){
                    $key = array_shift($row);
                    $matches[$key] = $row;
                }
                $res->close();
            }
        }
        return $matches;
    }

    /**
    * returns array of FIRST column values
    */
    function mysql__select_list2($mysqli, $query):array {

        $matches = array();
        if($mysqli && $query){
            $res = $mysqli->query($query);

            if ($res){
                while ($row = $res->fetch_row()){
                    array_push($matches, $row[0]);
                }
                $res->close();
            }
        }

        return $matches;
    }
    
    function mysql__select_list($mysqli, $table, $column, $condition):array {
        $query = "SELECT $column FROM $table WHERE $condition";
        return mysql__select_list2($mysqli, $query);
    }
    
    /**
    * return the first column of first row
    *
    * @param mixed $mysqli
    * @param mixed $query
    */
    function mysql__select_value($mysqli, $query) {
        $row = mysql__select_row($mysqli, $query);

        if($row && @$row[0]!=null){
            $result = $row[0];
        }else{
            $result = null;
        }
        return $result;
    }

    /**
    * returns first row
    *
    * @param mixed $mysqli
    * @param mixed $query
    */
    function mysql__select_row($mysqli, $query) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if($res){
                $row = $res->fetch_row();
                if($row){
                    $result = $row;
                }
                $res->close();
            }else{
                error_log('Query: '.$query.'.  mySQL error: '.$mysqli->error);
            }
        }
        return $result;
    }

    /**
    * returns first row with assoc field names 
    *
    * @param mixed $mysqli
    * @param mixed $query
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
    *                   0 - two dimensional array of records
    *                   1 - array of records with index from first column
    * @return []
    */
    function mysql__select_all($mysqli, $query, $mode=0, $i_trim=0) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if ($res){
                $result = array();
                while ($row = $res->fetch_row()){
                    
                    if($i_trim>0) array_walk($row, 'trim_item', $i_trim);
                    
                    if($mode==1){
                        $rec_id = array_shift($row);
                        $result[$rec_id] = $row;  //stripAccents(trim($row[1]));
                    }else {
                        array_push($result, $row);
                    }
                }
                $res->close();

            }else{
            }
        }
        return $result;
    }
    
    //
    //
    function mysql__get_table_columns($mysqli, $table){

        $res = $mysqli->query('DESCRIBE '.$table);
        if (!$res) return NULL;
        $matches = array();
        if($res){
            while (($row = $res->fetch_row())) array_push($matches, $row[0]);
            
            $res->close();
        }
        return $matches;    
    }

//
//
//
    function mysql__duplicate_table_record($mysqli, $table, $idfield, $oldid, $newid){

        $columns = mysql__get_table_columns($mysqli, $table);    
       
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
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $table_prefix
    * @param mixed $record   - array(fieldname=>value) - all values considered as String except when field ended with ID
    *                          fields that don't have specified prefix are ignored
    */
    function mysql__delete($mysqli, $table_name, $table_prefix, $rec_ID){

        $ret = null;
        
        $rec_ID = prepareIds($rec_ID);

        if(count($rec_ID)>0){
            
            if (substr($table_prefix, -1) !== '_') {
                $table_prefix = $table_prefix.'_';
            }

            $query = "DELETE from $table_name WHERE ".$table_prefix.'ID in ('.implode(',', $rec_ID).')';

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
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $table_prefix  - config array of fields or table prefix
    * @param mixed $record   - array(fieldname=>value) - all values considered as String except when field ended with ID
    *                          fields that don't have specified prefix are ignored
    * @param boolean $allow_insert_with_newid if true, negative record id will be abs and turns into new record id 
    */
    function mysql__insertupdate($mysqli, $table_name, $table_prefix, $record, $allow_insert_with_newid=false){

        $ret = null;
        $primary_field_type = 'integer';

        if(is_array($table_prefix)){ //fields
            
            $fields = array();  
            foreach($table_prefix as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual') continue;
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
                    "SELECT $primary_field FROM $table_name WHERE $primary_field='"
                        .$mysqli->real_escape_string($rec_ID)."'");
                $isinsert = ($res==null);
            }
        }


        if($isinsert){
            $query = "INSERT into $table_name (";
            $query2 = ') VALUES (';
        }else{
            $query = "UPDATE $table_name set ";
        }

        $params = array();
        $params[0] = '';

        foreach($record as $fieldname => $value){

            if(is_array($table_prefix)){
                
                if(!in_array($fieldname, $fields)) continue;
                
            }else if(strpos($fieldname, $table_prefix)!==0){ //ignore fields without prefix
                //$fieldname = $table_prefix.$fieldname;
                continue;
            }

            if($isinsert){
                if($primary_field_type=='integer' && $fieldname==$primary_field){ //ignore primary field for update
                    if($allow_insert_with_newid){
                        $value = abs(intval($value));
                    }else{
                        continue;     
                    }
                }
                $query = $query.$fieldname.', ';
                
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
                    $query = $query.$fieldname.'=ST_GeomFromText(?), ';
                }else{
                    $query = $query.$fieldname.'=?, ';
                }
            }

            $dtype = ((substr($fieldname, -2) === 'ID' || substr($fieldname, -2) === 'Id')?'i':'s');
            if($fieldname == 'ulf_ObfuscatedFileID') $dtype = 's'; //exception
            //else if($fieldname == 'dtl_Value') $dtype = 'b'; //exception
            
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
            $val = $mysqli->real_escape_string($rec_ID);
            $query = $query." where ".$primary_field."="
                .($primary_field_type=='integer'?$val:("'".$val."'"));
        }

        $result = mysql__exec_param_query($mysqli, $query, $params);

        if($result===true && $primary_field_type=='integer'){
            $result = ($isinsert) ?$mysqli->insert_id :$rec_ID;
        }//for non-numeric it returns null


        return $result;
    }
    //
    // returns true ot mysql error
    //  $query with parameters "?"
    //  $params - array for parameters, first element is string with types "sdi"
    //
    function mysql__exec_param_query($mysqli, $query, $params, $return_affected_rows=false){

        if (!is_array($params) || count($params) < 1) {// not parameterised
            if ($result = $mysqli->query($query)) {

                $result = $return_affected_rows ?$mysqli->affected_rows  :true;
                
            } else {
                $result = $mysqli->error;
                if ($result == "") {
                   $result = $return_affected_rows ?$mysqli->affected_rows  :true;
                }
            }
        }else{        

            $stmt = $mysqli->prepare($query);
            if($stmt){

//error_log($query);
//error_log(print_r($params, true));
/*  faster
                $refArr = referenceValues($params); 
                $ref    = new ReflectionClass('mysqli_stmt'); 
                $method = $ref->getMethod("bind_param"); 
                $method->invokeArgs($stmt, $refArr); 
*/                
                //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
                call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));
                if(!$stmt->execute()){
                    $result = $mysqli->error;
                }else{
                    $result = $return_affected_rows ?$mysqli->affected_rows  :true;
                    //$result = $stmt->insert_id ?$stmt->insert_id :$mysqli->affected_rows;
                }
                $stmt->close();

//error_log('pq res='.$result);                
            }else{
                $result = $mysqli->error;
            }
        }

        return $result;
    }
    /**
    * converts array of values to array of value references for PHP 5.3+
    * detailed desription
    * @param    array [$arr] of values
    * @return   array of values or references to values
    */
    function referenceValues($arr) {
        if (true || strnatcmp(phpversion(), '5.3') >= 0) //Reference is required for PHP 5.3+
        {
            $refs = array();
            foreach ($arr as $key => $value) $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }

    /**
    * Returns values from sysIdentification 
    * 
    * @todo move to specific entity class
    *
    * @param mixed $mysqli
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
    * Check that db function exists
    *
    * @param mixed $mysqli
    * @param mixed $name
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
    * Validate the presence of db functions. If one of functions does not exist - run admin/setup/dbcreate/addFunctions.sql
    *
    */
    function checkDatabaseFunctions($mysqli){

            $res = false;

            if(!isFunctionExists($mysqli, 'getEstDate')){ //getTemporalDateString need drop old functions
                if(!function_exists('execute_db_script')){
                    include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to load procedures/triggers
                }
                if(db_script(HEURIST_DBNAME_FULL, dirname(__FILE__).'/../../admin/setup/dbcreate/addProceduresTriggers.sql', false)){
                    $res = true;
                }
            }else{
                $res = true;
            }

            return $res;
    }

    //
    //  NEW_LIPOSUCTION_255 is used in recordDupes
    //
    function checkDatabaseFunctionsForDuplications($mysqli){
        
         if(!isFunctionExists($mysqli, 'NEW_LIPOSUCTION_255')){
                if(!function_exists('execute_db_script')){
                    include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to load procedures/triggers
                }
                if(db_script(HEURIST_DBNAME_FULL, dirname(__FILE__).'/../../admin/setup/dbcreate/addFunctions.sql', false)){
                    $res = true;
                }else{
                    $res = false;
                }
         }else{
                $res = true;
         }

         return $res;
        
    }
    
    
    //
    //
    //
    function recreateRecLinks($system, $is_forced)
    {
        
        $mysqli = $system->get_mysqli();
        
        $isok = true;
        $is_table_exist = hasTable($mysqli, 'recLinks');    
            
        if($is_forced || !$is_table_exist){
                //recreate cache
                if(!function_exists('execute_db_script')){
                    include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to execute SQL script
                }

                if($is_table_exist){
                    
                    $query = "drop table IF EXISTS recLinks";
                    if (!$mysqli->query($query)) {
                        $system->addError(HEURIST_DB_ERROR, 'Cannot drop table cache table: ' . $mysqli->error);
                        $isok = false;
                    }
                    
                }else{
                    //recreate triggers if recLinks does not exist
                }
                if($isok){
                    
                    if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__).'/../../admin/setup/dbcreate/addProceduresTriggers.sql', false))
                    {
                        $system->addError(HEURIST_DB_ERROR, 'Cannot execute script addProceduresTriggers.sql');
                        //$response = $system->getError();
                        $isok = false;
                    }
                    if($isok){
                        if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__)."/sqlCreateRecLinks.sql"))
                        {
                            $system->addError(HEURIST_DB_ERROR, 'Cannot execute script sqlCreateRecLinks.sql');
                            //$response = $system->getError();
                            $isok = false;
                        }
                    }
                }
        }
        return $isok;
    }
    
    //
    // $need_populate - adds entries to recDetailsDateIndex
    // $json_for_record_details - update recDetails - change Plain string temporals to JSON 
    //
    function recreateRecDetailsDateIndex($system, $need_populate, $json_for_record_details){
        
        $mysqli = $system->get_mysqli();
        
        $dbVerSubSub = $system->get_system('sys_dbSubSubVersion');        
        
        $isok = true;
        $is_table_exist = hasTable($mysqli, 'recDetailsDateIndex');    
        
        $err_prefix = '';//'Update date index: ';
        $cnt = 0;
        $cnt_to_json = 0;
        $cnt_err = 0;
        $report = array();
        
        $log_file = $system->getSysDir().'recDetailsDateIndex.log';
        
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

        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot create recDetailsDateIndex', $mysqli->error);
            return false;
        }else{
            $report[] = 'recDetailsDateIndex created';
            //recreate triggers
            if(!function_exists('execute_db_script')){
                include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to load procedures/triggers
            }
            if(!db_script($system->dbname_full(), dirname(__FILE__).'/../../admin/setup/dbcreate/addProceduresTriggers.sql', false)){
                    $system->addError(HEURIST_DB_ERROR, $err_prefix.'Cannot recreate database triggers', $mysqli->error);
                    return false;
            }
            $report[] = 'Triggers to populate recDetailsDateIndex created';
            
            if($need_populate){
            
            //fill database with min/max date values
            //1. find all date values in recDetails
            $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value!=""';
            $cnt_dates = mysql__select_value($mysqli, $query);
            
            $query = 'SELECT dtl_ID,dtl_RecID,dtl_DetailTypeID,dtl_Value FROM recDetails, defDetailTypes WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value!=""';
            $res = $mysqli->query($query);
            
            if ($res){
                
                if($json_for_record_details){
                    $mysqli->query('DROP TABLE IF EXISTS bkpDetailsDateIndex');
                    $res3 = $mysqli->query('CREATE TABLE bkpDetailsDateIndex (
                         bkp_ID int unsigned NOT NULL auto_increment,
                         dtl_ID int unsigned NOT NULL,
                         dtl_Value TEXT,
                         PRIMARY KEY (bkp_ID))');
                }
                
                if($cnt_dates<150000){
                    $keep_autocommit = mysql__begin_transaction($mysqli);
                }
                
                while ($row = $res->fetch_row()){
                    $dtl_ID = $row[0];
                    $dtl_RecID = $row[1];
                    $dtl_DetailTypeID = $row[2];
                    $dtl_Value = $row[3];
                    $dtl_NewValue = '';
                    $error = '';
                    
                    if(trim($dtl_Value)=='') continue;
                    
            //2. Create temporal object
                    $preparedDate = new Temporal( $dtl_Value );
                        
                    if($preparedDate && $preparedDate->isValid()){
                            
                            // saves as usual date
                            // if date is Simple, 0<year>9999 (CE) and has both month and day 
                            $is_date_simple = $preparedDate->isValidSimple();
                            $dtl_NewValue_for_update = null;
                            if($is_date_simple){
                                $dtl_NewValue = $preparedDate->getValue(true); //returns simple yyyy-mm-dd
                                $dtl_NewValue_for_update = $dtl_NewValue;
                            }else{
                                $v_json = $preparedDate->getValue();
                                $dtl_NewValue_for_update = json_encode($v_json);
                                $v_json['comment'] = ''; //to avoid issue with special charss
                                $dtl_NewValue = json_encode($v_json); //$preparedDate->toJSON(); //json encoded string
                            }
                            if($dtl_NewValue==null || $dtl_NewValue=='' || $dtl_NewValue=='null'){
                                $error = 'Not valid date: '.$dtl_Value;
                            }else{
                            
            //3. Validate estMin and estMax from JSON
                            $query = 'SELECT getEstDate(\''.$dtl_NewValue  //$mysqli->real_escape_string(
                                    .'\',0) as minD, getEstDate(\''.$dtl_NewValue.'\',1) as maxD'; //$mysqli->real_escape_string(
                            try{
                                $res2 = $mysqli->query($query);            
                            }catch(Exception $e){
                                $res2 = false;
                            }
                            
                            if($res2){
                                $row2 = $res2->fetch_row();
                                if(($row2[0]=='' && $row2[1]=='') || ($row2[0]=='0' && $row2[1]=='0')){
                                    //fails extraction estMinDate, estMaxDate
                                    $error = 'Empty min, max dates. Min:"'.$min.'" Max:"'.$max.'". Query:'.$query;
                                }else{
            //4. Keep old plain string temporal object in backup table 
                                    if($json_for_record_details && strpos($dtl_Value,'|VER=1|')===0){ // !$is_date_simple
                                        $query = 'INSERT INTO bkpDetailsDateIndex(dtl_ID,dtl_Value) VALUES('.$dtl_ID.',\''
                                            .$mysqli->real_escape_string($dtl_Value).'\')';
                                        $res4 = $mysqli->query($query);
                                        if(!$res4){
                                            $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on backup for query:'.$query, $mysqli->error);
                                            $isok = false;
                                            break;
                                        }
                                    }
            //5A. If simple date - retain value in recDetails                                    
            //5B. If temporal object it saves JSON in recDetails
                                    if($dtl_Value != $dtl_NewValue_for_update){
                                        $query = 'UPDATE recDetails SET dtl_Value="'.
                                                $mysqli->real_escape_string($dtl_NewValue_for_update).'" WHERE dtl_ID='.$dtl_ID;
                                        $mysqli->query($query);
                                        if(!($mysqli->affected_rows>=0)){
                                            //fails update recDetails
                                            $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on recDetails update query:'.$query, $mysqli->error);
                                            $isok = false;
                                            break;
                                        }
                                    }
                                    
                                    
            //6. update recDetailsDateIndex should be updated by trigger
                                    $mysqli->query('delete ignore from recDetailsDateIndex where rdi_DetailID='.$dtl_ID); 
            
                                    $query = 'insert into recDetailsDateIndex (rdi_RecID, rdi_DetailTypeID, rdi_DetailID, rdi_estMinDate, rdi_estMaxDate)'
        ." values ($dtl_RecID, $dtl_DetailTypeID, $dtl_ID, {$row2[0]}, {$row2[1]})";
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
                    
                    //keep log
                    if(!$is_date_simple || $error){
//file_put_contents($log_file, $dtl_ID.';'.$dtl_Value.';'.$dtl_NewValue.';'.$error."\n", FILE_APPEND );    
                        if(!$is_date_simple) $cnt_to_json++;
                        if($error){
                            $error = '<span style="color:red">'.$error.'</span>';
                            $cnt_err++;
                        } 
                        
                        if($need_populate && $error){ //verbose output
                            $report[] = 'Rec# '.$dtl_RecID.'  '.$dtl_Value.' '.(($dtl_Value!=$dtl_NewValue)?$dtl_NewValue:'').' '.$error;
                        }
                        
                    }
                    if(!$error) $cnt++;
                 
                    
                }//while
                $res->close();
                
                if($isok){
                    
                    if($json_for_record_details && $dbVerSubSub<14){
                        $mysqli->query('UPDATE sysIdentification SET sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=14 WHERE 1');
                    }
                    
                    
                    if($cnt_dates<150000){
                        $mysqli->commit();  
                    }
                }else{
                    if($cnt_dates<150000){
                        $mysqli->rollback();
                    }
                }
                if( $cnt_dates<150000 && $keep_autocommit===true) $mysqli->autocommit(TRUE);

            }
        }
        }
        
        //if(!$isok){
        //    file_put_contents($log_file, json_encode( $system->getError() ));
        //        }
        if($isok && $need_populate){ //verbose output
            $report[] = '<ul><li>Added into date index: '.$cnt.'</li>'
                        .'<li>Errors date pasring: '.$cnt_err.'</li>'
                        .'<li>Complex temporals: '.$cnt_to_json.'</li></ul>';
        }
        
        return $isok?$report:false;
    }
    
    
    //
    //
    //
    function trim_item(&$item, $key, $len){
        if($item!='' && $item!=null){
            $item = substr(trim($item),0,$len);
        }
    }
    
    //
    //
    //
    function repalce_nulls(&$item, $key){
        if($item==null){
            $item = '';
        }
    }

    //
    // for strip accents
    //
    function my_strtr($inputStr, $from, $to, $encoding = 'UTF-8') {
        $inputStrLength = mb_strlen($inputStr, $encoding);

        $translated = '';
        
        //$inputStr = preg_replace("/\s+/u", ' ', $inputStr); //any spaces

//error_log($inputStr.'  '.$inputStrLength);
        
        for($i = 0; $i < $inputStrLength; $i++) {
            $currentChar = mb_substr($inputStr, $i, 1, $encoding);
            /*
            if(mb_ord($currentChar)==0xA0 || mb_ord($currentChar)==65279){ //non breaking space or BOM
                $translatedCharPos = '';    
            }else{
                $translatedCharPos = mb_strpos($from, $currentChar, 0, $encoding);    
            }*/
            $translatedCharPos = mb_strpos($from, $currentChar, 0, $encoding);    
/*          
if($i<5){
    error_log ($i.'  >'.$currentChar.'<  ord='.mb_ord($currentChar).'  chr='.mb_chr(mb_ord($currentChar)));
}*/
            
            
            if($translatedCharPos === false) {
                $translated .= $currentChar;
            }
            else {
                $translated .= mb_substr($to, $translatedCharPos, 1, $encoding);
            }
        }

        return $translated;
    }
    
/*    
    //override standard trim function to sanitize unicode white spaces
    //Rename existing function
    rename_function('trim', '__trim');
    //Override function with another
    override_function('trim', '$string', 'return override_trim($string);');

    //new trim  function
    function override_trim($string){
        $str = preg_replace('/\xc2\xa0/', ' ', $str);  //non breakable space
        $str = preg_replace("/\xEF\xBB\xBF/", "", $str); // BOM
        //$str = preg_replace("/\s+/u", ' ', $str); //any spaces
        return __trim($str);
    }
*/
    //
    //
    //
    function stripAccents($stripAccents){ 
        return my_strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝß',
                                      'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYs');
    }  
    
    //
    // trim including &nbsp; and &xef; (BOM)
    //
    function super_trim( $str ){
        
        $str = trim($str);
        $len = strlen($str);
        $k = strpos($str,"\xC2\xA0");
        if($k===0){
            $str = substr($str,2);            
            return super_trim($str);
        }else if($k===$len-2){
            $str = substr($str,0,$len-2);
            return super_trim($str);
        }
        $k = strpos($str,"\xEF\xBB\xBF");
        if($k===0){
            $str = substr($str,3);            
            return super_trim($str);
        }else if($k===$len-3){
            $str = substr($str,0,$len-3);
            return super_trim($str);
        }
        
        return $str;
        
        //return trim($str); //trim($str, " \n\r\t\v\x00\xC2\xA0\xEF\xBB\xBF");
    }  
    
    //
    //
    //
    function  trim_lower_accent($item){
        return mb_strtolower(stripAccents(super_trim($item))); //including &nbsp; and &xef; (BOM)
    }

    function  trim_lower_accent2(&$item, $key){
        $item = trim_lower_accent($item);
    }

    function mb_strcasecmp($str1, $str2, $encoding = null) {
        if (null === $encoding) { $encoding = mb_internal_encoding(); }
        return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
    }
    
    function is_true($val){
        return $val===true || in_array(strtolower($val), array('y','yes','true','t','ok'));
    }

    //
    // $rec_IDs - may by csv string or array 
    // returns array of integers
    //
    function prepareIds($ids, $can_be_zero=false){
        
        if($ids!=null){
            if(!is_array($ids)){
                if(is_numeric($ids)){
                    $ids = array($ids);
                }else{
                    /*if(substr($ids, -1) === ','){//remove last comma
                        $ids = substr($ids,0,-1);
                    }*/
                    $ids = explode(',', $ids);
                }
            }
            
            $res = array();
            foreach($ids as $v){
                if (is_numeric($v) && ($v > 0 || ($can_be_zero && $v==0))){
                    $res[] = $v;
                }
            }
            return $res;
            /*
            $ids = array_filter($ids, function ($v) {
                 return (is_numeric($v) && ($v > 0 || ($can_be_zero && $v==0)) );
            });
            */
        }else{
            return array();
        }
    }

    function prepareStrIds($ids){
    
        if(!is_array($ids)){
            $ids = explode(',', $ids);
        }
        
        $ids = array_map(function ($v) {
             return '"'.$v.'"';
        }, $ids);
        
        return $ids;
        
    }
    

    //
    // returns null if some of csv is not integer 
    // otherwise returns validated string with CSV
    //
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
    
    //
    //
    //
    function checkMaxLength2($dtl_Value){
        $dtl_Value = trim($dtl_Value);
        $len  = strlen($dtl_Value);  //number of bytes
        $len2 = mb_strlen($dtl_Value); //number of characters
        $lim = ($len-$len2<200)?64000:32000; //32768;
        if($len>$lim){   //size in bytes more than allowed limit
            return $lim;
        }else{
            return 0;
        }
    }
    
    //
    // check max length for TEXT field
    //
    function checkMaxLength($dty_Name, $dtl_Value){
        
        $lim = checkMaxLength2($dtl_Value);
        /*
        if($len>10000){
            $stmt_size->bind_param('s', $dtl_Value);
            if($stmt_size->execute()){
                $stmt_size->bind_result($str_size);
                $stmt_size->fetch();
                if($str_size>65535){
                    $len = $str_size;
                }
            }
        }
        */
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


    
    //
    //
    //
    function mysql__begin_transaction($mysqli){
        
        $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
        if($keep_autocommit===true || $keep_autocommit==1){
                $mysqli->autocommit(FALSE);
                $keep_autocommit = true;  
        }else{
                $keep_autocommit = false;  
        } 
        if (strnatcmp(phpversion(), '5.5') >= 0) {
            $mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        }
        
        return $keep_autocommit;
        
    }

    

    //
    // works with temporary table sysSessionProgress that allows trace long server side process like smarty report or csv import
    //
    function mysql__update_progress2($mysqli, $session_id, $is_init, $value){
        
        if($session_id==null) return;
        
        $res = null;
        $need_close = false;
        /*
        if($mysqli===null){
            $need_close = true;
            $mysqli = mysqli_connection_overwrite(DATABASE);
        }*/
        
        if($is_init){
            //check that session table exists
            if(!hasTable($mysqli, 'sysSessionProgress')){
                //recreate
                $mysqli->query('CREATE TABLE sysSessionProgress(stp_ID varchar(32) NOT NULL COMMENT "User session ID generated by the server", stp_Data varchar(32) COMMENT "Stores progress data for the session identified by the session ID", PRIMARY KEY (stp_ID))');
                
            }
        }
        
        if($value=='REMOVE'){
            //$mysqli->query("DELETE FROM sysSessionProgress where stp_ID=".$session_id);
        }else{
            $session_id = $mysqli->real_escape_string($session_id);
            
            $query = "select stp_Data from sysSessionProgress where stp_ID=".$session_id;
            $res = mysql__select_value($mysqli, $query);
            if($value!=null && $res!='terminate'){
                $value = $mysqli->real_escape_string($value);
                //write 
                if($res==null){
//error_log('INSERTED '.$session_id.'  '.$value);                                        
                    $query = "insert into sysSessionProgress values (".$session_id.",'".$value."')";
                }else{
                    
                list($execution_counter, $tot)  = explode(',',$value);
                if ($execution_counter>0 && intdiv($execution_counter,500) == $execution_counter/500){
//error_log('UPDATED '.$session_id.'  '.$value);                    
                }
                    
    //error_log('upd_prog '.$session_id.'  '.$value);                    
                    $query = "update sysSessionProgress set stp_Data='".$value."' where stp_ID=".$session_id;
                }
                $mysqli->query($query);
                $res = $value;
            }
            //$mysqli->commit();
        }
        if($need_close)  $mysqli->close();
   
        if($value==null){
//error_log('get_prog '.$session_id.'  '.$res);                    
        }

        return $res;
    }    
    
    //
    //  returns value of session file
    //
    function mysql__update_progress($mysqli, $session_id, $is_init, $value){
        
        if($session_id==null || $session_id==0) return null;
        
        if(!defined('HEURIST_SCRATCH_DIR')) return null;
        
        $res = null;
        
        $session_file = HEURIST_SCRATCH_DIR.'session'.$session_id;
        $is_exist = file_exists($session_file);
        
        if($value=='REMOVE'){
            if($is_exist) fileDelete($session_file);
        }else{
            //get    
            if($is_exist) $res = file_get_contents($session_file);
            
            if($value!=null && $res!='terminate'){
                file_put_contents($session_file, $value);
                $res = $value;
            }
                        
            /*            
                list($execution_counter, $tot)  = explode(',',$value);
                if ($execution_counter>0 && intdiv($execution_counter,500) == $execution_counter/500){
error_log('UPDATED '.$session_id.'  '.$value);                    
                }
            */    
        }
   
        return $res;
    }    

    // 
    // For Sybversion update see DBUpgrade_1.2.0_to_1.3.0.php
    //
    // This method updates from 1.3.14 to 1.3.xxxx
    //
    function updateDatabaseToLatest($system){
       
        $sysValues = $system->get_system(null, true);        
        $dbVer = $system->get_system('sys_dbVersion');
        $dbVerSub = $system->get_system('sys_dbSubVersion');
        $dbVerSubSub = $system->get_system('sys_dbSubSubVersion');
        
        if($dbVer==1 && $dbVerSub==3 && $dbVerSubSub>13){
            
            $report = array();

            if($dbVerSubSub<15){
            
                // import IIIF Annonation field
                if(!(ConceptCode::getDetailTypeLocalID('2-1098')>0)){
                    $importDef = new DbsImport( $system );
                    if($importDef->doPrepare(  array(
                    'defType'=>'detailtype', 
                    'databaseID'=>2, 
                    'conceptCode'=>'2-1098')))
                    {
                        $res = $importDef->doImport();
                    }
                    if($res){
                        $report[] = 'Field 2-1098 "IIIF Annonation imported';    
                    }
                }
                
                $mysqli = $system->get_mysqli();
                
                //add new columns for 
                if(!hasColumn($mysqli, 'recUploadedFiles', 'ulf_Caption', null, 'varchar(255)')){
                    
                    $query = 'ALTER TABLE `recUploadedFiles` '
."ADD `ulf_Caption` varchar(255) COMMENT 'A user-entered textual name of the file or image' AFTER `ulf_Thumbnail`,"
."ADD `ulf_Copyright` varchar(255) COMMENT 'Copyright statement or a URI leading to a copyright statement. Consider using Creative Commons categories.' AFTER `ulf_Description`,"
."ADD `ulf_Copyowner` varchar(255) COMMENT 'The owner of the copyright in the file ir image (person or organisation)'  AFTER `ulf_Copyright`";
                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify recUploadedFiles to add ulf_Caption, ulf_Copyright and ulf_Copyowner', $mysqli->error);
                        return false;
                    }
                    $report[] = 'recUploadedFiles:ulf_Caption, ulf_Copyright and ulf_Copyowner added';
                } else {
                    $report[] = 'defTrecUploadedFileserms:ulf_Caption, ulf_Copyright and ulf_Copyowner already exist';
                } 
                
                $mysqli->query('UPDATE sysIdentification SET sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=15 WHERE 1');
            }

        }
        return true;
    }  
    
    /**
    * Returns true if table exists in database
    * 
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $db_name
    */
    function hasTable($mysqli, $table_name, $db_name=null){
        
            $query = '';
            if($db_name!=null){
                //$db_name = HEURIST_DBNAME_FULL;
                $query = 'FROM '.$db_name;
            }
    
            $value = mysql__select_value($mysqli, "SHOW TABLES $query LIKE '$table_name'");
            $not_exist = ($value==null || $value=='');
        
            return !$not_exist;        
    }

    /**
    * Returns true if column exists in given table
    *     
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $column_name
    * @param mixed $db_name
    */
    function hasColumn($mysqli, $table_name, $column_name, $db_name=null, $given_type=null){

        if($db_name==null){
            //$db_name = HEURIST_DBNAME_FULL;
            $db_name = ''; //$query = ;
        }else{
            $db_name = "`$db_name`.";
        }
    
        $query = "SHOW COLUMNS FROM $db_name`$table_name` LIKE '$column_name'";
        
        $res = $mysqli->query($query);
        $row_cnt = 0;
        if($res) {
            $row_cnt = $res->num_rows; 
            
            if($row_cnt>0 && $given_type!=null){
                $row = $res->fetch_assoc();
                if($row['Type']==$given_type){
                    return true;
                }else{
                    return false; 
                }
            }
            
            
            $res->close();
        }
        return ($row_cnt>0);
    }

    function checkUserStatusColumn($system, $db_source = ''){

        if(empty($db_source) && defined(HEURIST_DBNAME_FULL)){
            $db_source = HEURIST_DBNAME_FULL;
        }

        $mysqli = $system->get_mysqli();
        
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