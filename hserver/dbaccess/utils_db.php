<?php

    /**
    *  Database utilities :   mysql_ - prefix for function
    *
    *  mysql_connection - establish connection
    *  mysql__getdatabases4 - get list of databases
    *  mysql__select_assoc - returns array  key_column=>val_column for given table
    *  mysql__select_list - returns array of column values
    *  mysql__select_value   - return the first column of first row
    *  mysql__select_array   - returns first row
    *  mysql__insertupdate
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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
    function mysql_connection($dbHost, $dbUsername, $dbPassword){


        if(null==$dbHost || $dbHost==""){
            return array(HEURIST_SYSTEM_FATAL, "Database server is not defined. Check your configuration file");
        }

        try{
            $mysqli = new mysqli($dbHost, $dbUsername, $dbPassword);
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

            $success = $mysqli->select_db($dbname);
            if(!$success){
                return array(HEURIST_INVALID_REQUEST, "Could not open database ".$dbname);
            }

            $mysqli->query('set character set "utf8"');
            $mysqli->query('set names "utf8"');

            // @todo verify that in stored procedures we use user_id
            // if so, set this mysql variable on session open 
            // if (function_exists('get_user_id')) $mysqli->query('set @logged_in_user_id = ' . get_user_id());
        }
        return true;
    }
    
    
    /**
    * returns list of databases as array
    * @param    mixed $with_prefix - if false it remove "hdb_" prefix
    * @param    mixed $email - current user email
    * @param    mixed $role - admin - returns database where current user is admin, user - where current user exists
    */
    function mysql__getdatabases4($mysqli, $with_prefix = false, $email = null, $role = null, $prefix=HEURIST_DB_PREFIX)
    {
        $query = "show databases";
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
    function mysql__select_assoc($mysqli, $table, $key_column, $val_column, $condition) {

        $matches = null;
        if($mysqli){
            $res = $mysqli->query("SELECT $key_column, $val_column FROM $table WHERE $condition");
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
    * returns array of column values
    */
    function mysql__select_list($mysqli, $table, $column, $condition) {

        $matches = null;
        if($mysqli){
            $query = "SELECT $column FROM $table WHERE $condition";
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
    
    /**
    * return the first column of first row
    *
    * @param mixed $mysqli
    * @param mixed $query
    */
    function mysql__select_value($mysqli, $query) {
        $row = mysql__select_array($mysqli, $query);
        if($row && @$row[0]){
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
    function mysql__select_array($mysqli, $query) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if($res){
                $row = $res->fetch_row();
                if($row){
                    $result = $row;
                }
                $res->close();
            }
        }
        return $result;
    }

    /**
    * insert or update record for given table
    *
    * returns record ID in case success or error message
    *
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $table_prefix
    * @param mixed $record   - array(fieldname=>value) - all values considered as String except when field ended with ID
    *                          fields that don't have specified prefix are ignored
    */
    function mysql__insertupdate($mysqli, $table_name, $table_prefix, $record){

        $ret = null;

        if (substr($table_prefix, -1) !== '_') {
            $table_prefix = $table_prefix.'_';
        }

        $rec_ID = intval(@$record[$table_prefix.'ID']);
        $isinsert = ($rec_ID<1);

        if($isinsert){
            $query = "INSERT into $table_name (";
            $query2 = ') VALUES (';
        }else{
            $query = "UPDATE $table_name set ";
        }

        $params = array();
        $params[0] = '';

        foreach($record as $fieldname => $value){

            if(strpos($fieldname, $table_prefix)!==0){ //ignore fields without prefix
                //$fieldname = $table_prefix.$fieldname;
                continue;
            }

            if($isinsert){
                $query = $query.$fieldname.', ';
                $query2 = $query2.'?, ';
            }else{
                if($fieldname==$table_prefix."ID"){
                    continue;
                }
                $query = $query.$fieldname.'=?, ';
            }

            $params[0] = $params[0].((substr($fieldname, -2) === 'ID')?'i':'s');
            array_push($params, $value);
        }

        $query = substr($query,0,strlen($query)-2);
        if($isinsert){
            $query2 = substr($query2,0,strlen($query2)-2).")";
            $query = $query.$query2;
        }else{
            $query = $query." where ".$table_prefix."ID=".$rec_ID;
        }

        $stmt = $mysqli->prepare($query);
        if($stmt){
            call_user_func_array(array($stmt, 'bind_param'), refValues($params));
            if(!$stmt->execute()){
                $ret = $mysqli->error;
            }else{
                $ret = ($isinsert)?$stmt->insert_id:$rec_ID;
            }
            $stmt->close();
        }else{
            $ret = $mysqli->error;
        }

        return $ret;
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

    /**
    * Returns values from sysIdentification
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

            if(isFunctionExists($mysqli, 'NEW_LEVENSHTEIN') && isFunctionExists($mysqli, 'NEW_LIPOSUCTION')
                && isFunctionExists($mysqli, 'hhash') && isFunctionExists($mysqli, 'simple_hash')
                //&& isFunctionExists('set_all_hhash')
                && isFunctionExists($mysqli, 'getTemporalDateString')){

                $res = true;

            }else{

                include(dirname(__FILE__).'utils_db_load_script.php'); // used to load procedures/triggers
                if(db_script(HEURIST_DBNAME_FULL, HEURIST_BASE_URL.'admin/setup/dbcreate/addProceduresTriggers.sql')){
                    $res = true;
                }
            }

            return $res;
    }


?>
