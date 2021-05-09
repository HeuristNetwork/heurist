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
    * 
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once (dirname(__FILE__).'/../consts.php');

    /**
    * Connect to db server 
    *
    * @param mixed $dbHost
    * @param mixed $dbUsername
    * @param mixed $dbPassword
    *
    * @return a MySQL link identifier on success or array with code and error message on failure.
    */
    function mysql__connection($dbHost, $dbUsername, $dbPassword){

        if(null==$dbHost || $dbHost==""){
            return array(HEURIST_SYSTEM_FATAL, "Database server is not defined. Check your configuration file");
        }

        try{
            $mysqli = mysqli_init();
            $mysqli -> options(MYSQLI_OPT_LOCAL_INFILE, 1);
            $mysqli -> real_connect($dbHost, $dbUsername, $dbPassword);
            //$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword);
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

            $success = $mysqli->select_db($database_name_full);
            if(!$success){
                return array(HEURIST_INVALID_REQUEST, "Could not open database ".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8'));
            }

            //$mysqli->query('SET CHARACTER SET utf8mb4'); //utf8 is utf8mb3 by default
            //$mysqli->query('SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $mysqli->query('SET NAMES utf8mb4');
        }
        return true;
    }
    
    //
    //
    //
    function mysql__create_database( $mysqli, $db_name ){

        $res = false;

        // Avoid illegal chars in db
        if (preg_match('[\W]', $db_name)){
            $res = array(HEURIST_INVALID_REQUEST, 
                'Only letters, numbers and underscores (_) are allowed in the database name');
        }else{
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
                $database  = $row[0];
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

        natcasesort($result); //AO: Ian wants case insensetive order

        return $result;

    }

    /**
    * returns array  key_column=>val_column for given table
    */
    function mysql__select_assoc2($mysqli, $query){
        
        $matches = null;
        if($mysqli && $query){
            
            $res = $mysqli->query($query);
            if ($res){
                $matches = array();
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
    function mysql__select_assoc($mysqli, $query){
        
        $matches = null;
        if($mysqli && $query){
            
            $res = $mysqli->query($query);
            if ($res){
                $matches = array();
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
    function mysql__select_list2($mysqli, $query) {

        $matches = null;
        if($mysqli && $query){
            $res = $mysqli->query($query);

            if ($res){
                $matches = array();
                while ($row = $res->fetch_row()){
                    array_push($matches, $row[0]);
                }
                $res->close();
            }
        }

        return $matches;
    }
    
    function mysql__select_list($mysqli, $table, $column, $condition) {
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
                        $value = abs($value);
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

        if ($params == null || count($params) < 1) {// not parameterised
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

            if(!isFunctionExists($mysqli, 'getTemporalDateString')){ //need drop old functions
                include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to load procedures/triggers
                if(db_script(HEURIST_DBNAME_FULL, dirname(__FILE__).'/../../admin/setup/dbcreate/addProceduresTriggers.sql', false)){
                    $res = true;
                }
            }else{
                $res = true;
            }

            return $res;
    }

    //
    //
    //
    function checkDatabaseFunctionsForDuplications($mysqli){
        
         if(!isFunctionExists($mysqli, 'NEW_LIPOSUCTION_255')){
                include(dirname(__FILE__).'/../utilities/utils_db_load_script.php'); // used to load procedures/triggers
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
    //
    //
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

    //
    //
    //
    function stripAccents($stripAccents){
        return my_strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝß','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYs');
    }    

    function  trim_lower_accent($item){
        return mb_strtolower(stripAccents($item));
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
    // return array of integers
    //
    function prepareIds($ids, $can_be_zero=false){
        
        if($ids!=null){
            if(!is_array($ids)){
                if(is_int($ids)){
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
        
        if($session_id==null) return null;
        
        if(!defined('HEURIST_SCRATCH_DIR')) return null;
        
        $res = null;
        
        $session_file = HEURIST_SCRATCH_DIR.'session'.$session_id;
        $is_exist = file_exists($session_file);
        
        if($value=='REMOVE'){
            if($is_exist) unlink($session_file);
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

    // @todo - remove
    // see DBUpgrade_1.2.0_to_1.3.0.php
    // Adds trash group
    // Adds sysIdentification.sys_ExternalReferenceLookups 
    //
    function updateDatabseToLatest4($system){
        
        $dbVer = $system->get_system('sys_dbVersion');
        $dbVerSub = $system->get_system('sys_dbSubVersion');

        if($dbVer==1 && $dbVerSub>2){
        
            $mysqli = $system->get_mysqli();
            
            //adds trash groups
            if(!(mysql__select_value($mysqli, 'select rtg_ID FROM defRecTypeGroups WHERE rtg_Name="Trash"')>0)){
    $query = 'INSERT INTO defRecTypeGroups (rtg_Name,rtg_Order,rtg_Description) '
    .'VALUES ("Trash",255,"Drag record types here to hide them, use dustbin icon on a record type to delete permanently")';
                $mysqli->query($query);
            }

            if(!(mysql__select_value($mysqli, 'select vcg_ID FROM defVocabularyGroups WHERE vcg_Name="Trash"')>0)){
    $query = 'INSERT INTO defVocabularyGroups (vcg_Name,vcg_Order,vcg_Description) '
    .'VALUES ("Trash",255,"Drag vocabularies here to hide them, use dustbin icon on a vocabulary to delete permanently")';
                $mysqli->query($query);
            }

            if(!(mysql__select_value($mysqli, 'select dtg_ID FROM defDetailTypeGroups WHERE dtg_Name="Trash"')>0)){
    $query = 'INSERT INTO defDetailTypeGroups (dtg_Name,dtg_Order,dtg_Description) '
    .'VALUES ("Trash",255,"Drag base fields here to hide them, use dustbin icon on a field to delete permanently")';        
                $mysqli->query($query);
            }
            
            $sysValues = $system->get_system();
            if(!array_key_exists('sys_ExternalReferenceLookups', $sysValues))
            {
                $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_ExternalReferenceLookups` TEXT default NULL COMMENT 'Record type-function-field specifications for lookup to external reference sources such as GeoNames'";
                $res = $mysqli->query($query);
            }
            
        }
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
    function hasColumn($mysqli, $table_name, $column_name, $db_name=null){

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
            $res->close();
        }
        return ($row_cnt>0);
    }

?>
