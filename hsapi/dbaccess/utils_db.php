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
    function mysql__getdatabases4($mysqli, $with_prefix = false, $email = null, $role = null, $prefix=HEURIST_DB_PREFIX)
    {
        $query = "show databases where `database` like 'hdb_%'";
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
    
    
    //
    //
    //
    /*
    function updateDatabseToLatest3($system){
        
        return;
        
        $ret = false;        
        $mysqli = $system->get_mysqli();

        $version = mysql__select_row_assoc($mysqli, "SELECT sys_dbVersion,sys_dbSubVersion,sys_dbSubSubVersion FROM sysIdentification");

        if (!($version['sys_dbVersion']==1 && $version['sys_dbSubVersion']==2 &&
           $version['sys_dbSubSubVersion']<1)){
           
           return;     
        }else{
            $mysqli->query('UPDATE sysIdentification SET sys_dbSubSubVersion=1 WHERE sys_ID=1');
        }
      
        //create new tables
        $value = mysql__select_value($mysqli, "SHOW TABLES LIKE 'defVocabularyGroups'");
        if($value==null || $value==""){        
            
$query = "CREATE TABLE defVocabularyGroups (
  vcg_ID tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Vocabulary group ID referenced in vocabs editor',
  vcg_Name varchar(40) NOT NULL COMMENT 'Name for this group of vocabularies, shown as heading in lists',
  vcg_Domain enum('enum','relation') NOT NULL default 'enum' COMMENT 'Field of application of the vocabulary - can be both',
  vcg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of vocabulary groups within pulldown lists',
  vcg_Description varchar(250) default NULL COMMENT 'A description of the vocabulary group and its purpose',
  vcg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this vocabulary group record, used to get last updated date for table',
  PRIMARY KEY  (vcg_ID),
  UNIQUE KEY vcg_Name (vcg_Name)
) ENGINE=InnoDB COMMENT='Grouping mechanism for vocabularies in vocabularies/terms editor'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create defVocabularyGroups', $mysqli->error);
                return false;
            }
            $report[] = 'defVocabularyGroups created';
            
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("User-defined")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Semantic web")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Place")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("People,  events, biography")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Bibliographic, copyright")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Spatial")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Categorisation and flags")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Internal")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name,vcg_Domain) VALUES ("RELATIONSHIPS","relation")');
            
            //alter table
            //verify that required column exists
            $query = "SHOW COLUMNS FROM `defTerms` LIKE 'trm_VocabularyGroupID'";
            $res = $mysqli->query($query);
            $row_cnt = $res->num_rows;
            if($res) $res->close();
            if(!$row_cnt){ //column not defined
                $query = "ALTER TABLE `defTerms` ADD COLUMN trm_VocabularyGroupID smallint(5) unsigned NULL default '1' COMMENT 'Vocabulary group to which this term belongs, if a top level term (vocabulary)'";
                 
                $res = $mysqli->query($query);
                if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot modify defTerms to add trm_VocabularyGroupID', $mysqli->error);
                    return false;
                }
            }
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=9 where (NOT (trm_ParentTermID>0)) and trm_Domain="relation"');

            //Semantic web
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=2 where trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (5668,5520,5805,5792,6091,5445,5842,6177,6214)');
            //Place
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=3 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (509,506)) OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB=5039');
            //People,  events, biography
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=4 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (5389,500,501,507,496,497,5432,505,511,513))'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB=5065)'
            .' OR (trm_OriginatingDBID=9 AND trm_IDInOriginatingDB=3297)'
            .' OR (trm_OriginatingDBID=1161 AND trm_IDInOriginatingDB=5419)');
            //Bibliographic, copyright
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=5 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB=503)'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB IN (5024,5021,5012,5099))'
            .' OR (trm_OriginatingDBID=1144 AND trm_IDInOriginatingDB=5986)');
            //Spatial
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=6 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (512,5362,5440,510,546,551))'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB IN (5087,5080,5091,5073,5080,5028,5083,5077))'
            .' OR (trm_OriginatingDBID=1125 AND trm_IDInOriginatingDB IN (3659,3339))');
            //Categorisation and flags
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=7 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (508,498,530))'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB IN (5030,3440))'
            .' OR (trm_OriginatingDBID=99 AND trm_IDInOriginatingDB=5445)'
            .' OR (trm_OriginatingDBID=1125 AND trm_IDInOriginatingDB=3339)'
            .' OR (trm_OriginatingDBID=1144 AND trm_IDInOriginatingDB IN (6002,5993))');
            //Internal
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=8 where trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (533,3272,520,6252,6250)');
            
            
            $report[] = 'defTerms: trm_VocabularyGroupID added';            
            
        }
             
        //$mysqli->query('DROP TABLE IF EXISTS defTermsLinks');
             
        $value = mysql__select_value($mysqli, "SHOW TABLES LIKE 'defTermsLinks'");
        if($value==null || $value==""){        
            
$query = "CREATE TABLE defTermsLinks (
  trl_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key for vocablary-terms hierarchy',
  trl_ParentID smallint(5) unsigned NOT NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  trl_TermID smallint(5) unsigned NOT NULL COMMENT 'Term identificator',
  PRIMARY KEY  (trl_ID),
  UNIQUE KEY trl_CompositeKey (trl_ParentID,trl_TermID)
) ENGINE=InnoDB COMMENT='Identifies hierarchy of vocabularies and terms'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create defTermsLinks', $mysqli->error);
                return false;
            }
            $report[] = 'defTermsLinks created';
            
            $mysqli->query('INSERT INTO defTermsLinks (trl_ParentID, trl_TermID) '
            .'SELECT trm_ParentTermID, trm_ID FROM defTerms WHERE trm_ParentTermID>0');
            
            $res = $mysqli->query('DROP TRIGGER IF EXISTS defTerms_last_insert');

            ///$res = $mysqli->query('DELIMITER $$');
            $res = $mysqli->query('CREATE DEFINER=CURRENT_USER TRIGGER `defTerms_last_insert` AFTER INSERT ON `defTerms` FOR EACH ROW
            begin
                if NEW.trm_ParentTermID > 0 then
                    insert into defTermsLinks (trl_ParentID,trl_TermID)
                            values (NEW.trm_ParentTermID, NEW.trm_ID);
                end if;
            end');  
            
            $res = $mysqli->query('DROP TRIGGER IF EXISTS defTerms_last_update');

            $res = $mysqli->query('CREATE DEFINER=CURRENT_USER TRIGGER `defTerms_last_update` AFTER UPDATE ON `defTerms`
            FOR EACH ROW
            begin
                if NEW.trm_ParentTermID != OLD.trm_ParentTermID then
                    update defTermsLinks SET trl_ParentID=NEW.trm_ParentTermID
                        where trl_ParentID=OLD.trm_ParentTermID and trl_TermID=NEW.trm_ID;
                end if;
            end');
            
            $res = $mysqli->query('DROP TRIGGER IF EXISTS defTerms_last_delete');
            $res = $mysqli->query('CREATE DEFINER=CURRENT_USER  TRIGGER `defTerms_last_delete` AFTER DELETE ON `defTerms` FOR EACH ROW
            begin
                delete ignore from defTermsLinks where trl_TermID=OLD.trm_ID || trl_ParentID=OLD.trm_ID;
            end');            
            //$mysqli->query('DELIMITER ;');            

            $vocab_group = 7;//
            
            //converts custom-selected term tree to vocab with references
            $query = 'SELECT dty_Name,dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, dty_ID, dty_Type FROM '
                     .'defDetailTypes WHERE  dty_Type="enum" or dty_Type="relmarker"';
        
            $res = $mysqli->query($query);
            while (($row = $res->fetch_row())) {
                //if the only numeric - assume this is vocabulary
                if(@$row[1]>0 && is_numeric(@$row[1])){
                    continue;
                }
                
                $domain = $row[4]=='enum'?'enum':'relation';
                $name = $row[0].' - selection';
                
                $cnt = mysql__select_value($mysqli, "SELECT count(trm_ID) FROM defTerms WHERE trm_Label LIKE '".$name."%'");
                if($cnt>0){
                   $name = $name . '  ' . $cnt; 
                }

error_log($row[3].'  '.$name);                
                
                //{"11":{"518":{},"519":{}},"94":{},"95":{},"3260":{"3115":{"3100":{}}}}
                $terms = json_decode(@$row[1], true);
                
                if($terms){
                    
                    //add new vocabulary
                    $vocab_id = mysql__insertupdate($mysqli, 'defTerms', 'trm', array(
                                'trm_Label'=>$name,
                                'trm_Domain'=>$domain,
                                'trm_VocabularyGroupID'=>$vocab_group));
                        
                    //parent->term_id
                    $terms_links = _prepare_terms($vocab_id, $terms);
                    
                    foreach ($terms_links as $line){
                        $mysqli->query('INSERT INTO defTermsLinks(trl_ParentID, trl_TermID) VALUES('.$line[0].','.$line[1].')');    
                    }

                    //dty_TermIDTreeNonSelectableIDs=dty_JsonTermIDTree, 
                    $query = 'UPDATE defDetailTypes SET dty_JsonTermIDTree='
                                .$vocab_id.' WHERE dty_ID='.$row[3];
                    $mysqli->query($query);

                }else{
                    error_log(" bummer ".@$row[1]);                
                }
            }

            
        }
        
        $value = mysql__select_value($mysqli, "SHOW TABLES LIKE 'sysTableLastUpdated'");
        if($value)
        {        
            $mysqli->query('DROP TRIGGER IF EXISTS sysUGrps_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS sysUGrps_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypes_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypes_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypes_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypes_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypes_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypes_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecStructure_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecStructure_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecStructure_last_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRelationshipConstraints_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRelationshipConstraints_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRelationshipConstraints_last_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypeGroups_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypeGroups_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypeGroups_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypeGroups_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypeGroups_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypeGroups_delete');
            $mysqli->query('DROP TABLE IF EXISTS sysTableLastUpdated');            
        }
        
        
    }
    
    // {"11":{"518":{},"519":{}},"94":{},"95":{},"3260":{"3115":{"3100":{}}}}
    function _prepare_terms($parent_id, $terms){
        $res = array();       
        foreach($terms as $trm_ID=>$children){
            array_push($res, array($parent_id, $trm_ID));
            if($children && count($children)>0){
                $res2 = _prepare_terms($trm_ID, $children);
                $res = array_merge($res, $res2);
            }
        }
        return $res;
    }
    */
    
    //
    //  adds/modifies columns in defRecStructure
    //     rst_DefaultValue, rst_PointerBrowseFilter, rst_PointerMode
    // 
    //  creates table usrWorkingSubsets
    //
    //  adds  defTerms.trm_SemanticReferenceURL
    //
    /*
    function updateDatabseToLatest2($system){

        $report = array();

        $ret = false;        
        $mysqli = $system->get_mysqli();
    
        $query = "SHOW COLUMNS FROM `defRecStructure` LIKE 'rst_DefaultValue'";
        $res = $mysqli->query($query);
        if($res){
            $row = $res->fetch_assoc();
            $method = null;
            if(!$row){
                $method = 'ADD';
            }else if (strpos($row['Type'],'varchar')!==false){
                $method = 'MODIFY';
            }
            if($method!=null){
                $query = "ALTER TABLE `defRecStructure` $method "
                        ." `rst_DefaultValue` text COMMENT 'The default value for this detail type for this record type'";
                $res = $mysqli->query($query);
                if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure.rst_DefaultValue', $mysqli->error);
                }
            }
        }
        
        //verify that required column exists
        $query = "SHOW COLUMNS FROM `defRecStructure` LIKE 'rst_PointerMode'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if($res) $res->close();
        if(!$row_cnt){ //column not defined
            //alter table
            $query = "ALTER TABLE `defRecStructure` ADD COLUMN `rst_PointerBrowseFilter` varchar(255)  DEFAULT NULL COMMENT 'When adding record pointer values, defines a Heurist filter to restrict the list of target records browsed' AFTER `rst_CreateChildIfRecPtr`, ADD COLUMN `rst_PointerMode` enum('addorbrowse','addonly','browseonly') DEFAULT 'addorbrowse' COMMENT 'When adding record pointer values, default or null = show both add and browse, otherwise only allow add or only allow browse-for-existing' AFTER `rst_CreateChildIfRecPtr`;";
             
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to add rst_PointerMode and rst_PointerBrowseFilter', $mysqli->error);
                return false;
            }
            $report[] = 'defRecStructure: rst_PointerMode and rst_PointerBrowseFilter added';
        }
    
    
        //create new tables
        $value = mysql__select_value($mysqli, "SHOW TABLES LIKE 'usrWorkingSubsets'");
        if($value==null || $value==""){        
            
$query = 'CREATE TABLE usrWorkingSubsets ( '
  ."wss_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Unique ID for the working subsets table',"
  ."wss_RecID int(10) unsigned NOT NULL COMMENT 'ID of a Record to be included in the working subset for a specific user',"
  ."wss_OwnerUGrpID smallint(5) unsigned NOT NULL COMMENT 'Person to whose working subset this Record ID is assigned',"
  ."PRIMARY KEY  (wss_ID),"
  .'KEY wss_RecID (wss_RecID),'
  .'KEY wss_OwnerUGrpID (wss_OwnerUGrpID)'
.") ENGINE=InnoDB COMMENT='Lists a set of Records to be included in a working subset for a user. Working susbset is an initial filter on all filter actions.'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create usrWorkingSubsets', $mysqli->error);
                return false;
            }
            $report[] = 'usrWorkingSubsets created';
        }
    
    
        
        $query = "SHOW COLUMNS FROM `defTerms` LIKE 'trm_SemanticReferenceURL'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if(!$row_cnt){ //column not defined
            $query = 'ALTER TABLE `defTerms` ADD '
                    .' `trm_SemanticReferenceURL` VARCHAR( 250 ) NULL'
                    ." COMMENT 'The URI to a semantic definition or web page describing the term'"
                    .' AFTER `trm_Code`';
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot add defTerms.trm_SemanticReferenceURL', $mysqli->error);
            }else{
                $ret = true;    
            }
        }else{
            $ret = true;
        }
        
        return $ret;
    }
    */
    //
    //  Creates usrRecPermissions, sysDashboard
    //
    //  Adds sysIdentification.sys_TreatAsPlaceRefForMapping
    //
    //  Adds defRecStructure.rst_CreateChildIfRecPtr
    //  Modifies sysUGrps.ugr_NavigationTree,   ugr_Preferences to MEDIUMTEXT
    //           usrBookmarks.bkm_Notes
    //
    //  Adds detail field 247 (child to parent reference)   
    //
    /*
    function updateDatabseToLatest($system){
        
        $mysqli = $system->get_mysqli();

        $query = 'DROP TRIGGER IF EXISTS update_sys_index_trigger';
        $res = $mysqli->query($query);

        $report = array();
        
        //create new tables
        $value = mysql__select_value($mysqli, "SHOW TABLES LIKE 'usrRecPermissions'");
        if($value==null || $value==""){        
        
            $query = 'CREATE TABLE IF NOT EXISTS `usrRecPermissions` ('
                  ."`rcp_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary table key',"
                  ."`rcp_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'ID of group',"
                  ."`rcp_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which permission is linked',"
                  ."`rcp_Level` enum('view','edit') NOT NULL default 'view' COMMENT 'Level of permission',"
                  ."PRIMARY KEY  (rcp_ID)"
                .") ENGINE=InnoDB COMMENT='Permissions for groups to records'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create usrRecPermissions', $mysqli->error);
                return false;
            }
            $report[] = 'usrRecPermissions created';
        }else{
            $query = 'DROP INDEX rcp_composite_key ON usrRecPermissions';
            $res = $mysqli->query($query);
        }

        //create new tables
        $value = mysql__select_value($mysqli, "SHOW TABLES LIKE 'sysDashboard'");
        if($value==null || $value==""){        
            
$query = 'CREATE TABLE sysDashboard ('
  .'dsh_ID tinyint(3) unsigned NOT NULL auto_increment,'
  ."dsh_Order smallint COMMENT 'Used to define the order in which the dashboard entries are shown',"
  ."dsh_Label varchar(64) COMMENT 'The short text which will describe this function on the dashboard',"
  ."dsh_Description varchar(1024) COMMENT 'A longer text giving more information about this function to show as a description below the label or as a rollover',"
  ."dsh_Enabled enum('y','n') NOT NULL default 'y' COMMENT 'Allows unused functions to be retained so they can be switched back on',"
  ."dsh_ShowIfNoRecords enum('y','n') NOT NULL default 'y' COMMENT 'Deteremines whether the function will be shown on the dashboard if there are no records in the database (eg. no point in showing searches if nothing to search)',"
  ."dsh_CommandToRun varchar(64) COMMENT 'Name of commonly used functions',"
  ."dsh_Parameters varchar(250) COMMENT 'Parameters to pass to the command eg the record type to create',"
  ."PRIMARY KEY  (dsh_ID)"
.") ENGINE=InnoDB COMMENT='Defines an editable list of shortcuts to functions to be displayed on a popup dashboard at startup unless turned off'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create sysDashboard', $mysqli->error);
                return false;
            }
            $report[] = 'sysDashboard created';
        }

        
        $sysValues = $system->get_system();
        //add new field into sysIdentification
        if(!array_key_exists('sys_TreatAsPlaceRefForMapping', $sysValues)){
            $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_TreatAsPlaceRefForMapping` VARCHAR(1000) DEFAULT '' COMMENT 'Comma delimited list of additional rectypes (local codes) to be considered as Places'";
            $res = $mysqli->query($query);
            $report[] = 'sysIdentification: sys_TreatAsPlaceRefForMapping added';
        }
        
        //verify that required column exists in defRecStructure  it was in 1.2
        $query = "SHOW COLUMNS FROM `defRecStructure` LIKE 'rst_CreateChildIfRecPtr'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if($res) $res->close();
        if(!$row_cnt){ //column not defined
            //alter table
             $query = "ALTER TABLE `defRecStructure` ADD COLUMN `rst_CreateChildIfRecPtr` TINYINT(1) DEFAULT 0 COMMENT 'For pointer fields, flags that new records created from this field should be marked as children of the creating record' AFTER `rst_PtrFilteredIDs`;";
             
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to add rst_CreateChildIfRecPtr', $mysqli->error);
                return false;
            }
            $report[] = 'defRecStructure: rst_CreateChildIfRecPtr added';
        }
        

        //verify that required column exists in sysUGrps
        $query = "SHOW COLUMNS FROM `sysUGrps` LIKE 'ugr_NavigationTree'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if($res) $res->close();
        
            //alter table
            $query = "ALTER TABLE `sysUGrps` ".(!$row_cnt?'ADD':'MODIFY')
                ." `ugr_NavigationTree` mediumtext COMMENT 'JSON array that describes treeview for filters'";
                
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify sysUGrps to add ugr_NavigationTree', $mysqli->error);
                return false;
            }
        if(!$row_cnt) $report[] = 'sysUGrps: ugr_NavigationTree added';
        
        $query = "SHOW COLUMNS FROM `sysUGrps` LIKE 'ugr_Preferences'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if($res) $res->close();
        
            $query = "ALTER TABLE `sysUGrps` ".(!$row_cnt?'ADD':'MODIFY')
                ." `ugr_Preferences` mediumtext COMMENT 'JSON array with user preferences'";
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify sysUGrps to add ugr_Preferences', $mysqli->error);
                return false;
            }
            if(!$row_cnt) $report[] = 'sysUGrps: ugr_Preferences added';
        
        
        //verify that required column exists in sysUGrps
        $query = "SHOW COLUMNS FROM `usrBookmarks` LIKE 'bkm_Notes'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if($res) $res->close();
        if(!$row_cnt){
            //alter table
            $query = "ALTER TABLE `usrBookmarks` ADD `bkm_Notes` mediumtext COMMENT 'Personal notes'";
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify usrBookmarks to add bkm_Notes', $mysqli->error);
                return false;
            }
            $report[] = 'usrBookmarks: bkm_Notes added';
        }

        
        //insert special field type - reference to parent record
        $dty_ID = mysql__select_value($mysqli,  
            "SELECT dty_ID FROM `defDetailTypes` WHERE dty_OriginatingDBID=2 AND dty_IDInOriginatingDB=247");

        if($dty_ID==null || !($dty_ID>0)){
            
            $res = $mysqli->query("INSERT INTO `defDetailTypes`
(`dty_Name`, `dty_Documentation`, `dty_Type`, `dty_HelpText`, `dty_ExtendedDescription`, `dty_EntryMask`, `dty_Status`, `dty_OriginatingDBID`, `dty_NameInOriginatingDB`, `dty_IDInOriginatingDB`, `dty_DetailTypeGroupID`, `dty_OrderInGroup`, `dty_JsonTermIDTree`, `dty_TermIDTreeNonSelectableIDs`, `dty_PtrTargetRectypeIDs`, `dty_FieldSetRecTypeID`, `dty_ShowInLists`, `dty_NonOwnerVisibility` ,`dty_LocallyModified`) VALUES
('Parent entity','Please document the nature of this detail type (field)) ...','resource','The parent of a child record (a record which is specifically linked to one and only one parent record by a pointer field in each direction)', '','','approved','2', '','247','99', '0','','', '','0','1','viewable','0')");

           if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify defDetailTypes parent entity field (2-247)', $mysqli->error);
                return false;
           }else{
                $report[] = 'defDetailTypes: parent entity field (2-247) added';
           }
        }
        
        return $report;
    }
    */

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

/*            
            isFunctionExists($mysqli, 'NEW_LEVENSHTEIN') && isFunctionExists($mysqli, 'NEW_LIPOSUCTION')
                && isFunctionExists($mysqli, 'hhash') && isFunctionExists($mysqli, 'simple_hash')
                //&& isFunctionExists('set_all_hhash')
*/                            
            if(!isFunctionExists($mysqli, 'getTemporalDateString') || isFunctionExists($mysqli, 'NEW_LIPOSUCTION')){ //need drop old functions
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
            $from_res = $mysqli->query("show tables like 'sysSessionProgress'");
            if ($from_res && $from_res->num_rows > 0) {
                //remove old data
                //mysql_query('DELETE FtmpUsrSession where field_id<'.);
            }else{
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

    //
    //
    //
    function updateDatabseToLatest4($system){
        
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
        

/*        
        $system->defineConstant('RT_CMS_HOME');
        $system->defineConstant('DT_POPUP_TEMPLATE');
        
        //adds 2-922 int CMS_HOME_RECORD
        if(defined('RT_CMS_HOME') && defined('DT_POPUP_TEMPLATE') && RT_CMS_HOME>0 && DT_POPUP_TEMPLATE>0 && 
            !(mysql__select_value($mysqli, 
                'select rst_ID from defRecStructure where rst_RecTypeID='.RT_CMS_HOME
                    .' and rst_DetailTypeID='.DT_POPUP_TEMPLATE)>0))
        {
            
            $entity = new DbDefRecStructure($system, array('rtyID'=>RT_CMS_HOME,
                'newfields'=>array('fields'=>DT_POPUP_TEMPLATE,
                'values'=>array(DT_POPUP_TEMPLATE=>array('dty_Name'=>'Website template',
                'dty_HelpText'=>'Specify the name of a template php file in the /HEURIST/ directory to provide a particular (eg. institutional) layout The template will have been set up by the server administrator.'))  )));
            $ret = $entity->batch_action();
        }
        
*/    
        
    }  
?>
