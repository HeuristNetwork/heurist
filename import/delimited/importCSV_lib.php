<?php

/**
* importCSV_lib.php: functions for delimeted data import
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");

//a couple functions from h4/utils_db.php
/**
* returns first row for given query
* 
* @param mixed $mysqli
* @param mixed $query
*/
function mysql__select_array2($mysqli, $query) {
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
* return all rows as index with key as first column in result set
* 
* @param mixed $mysqli
* @param mixed $query
*/
function mysql__select_array3($mysqli, $query) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if ($res){
                $matches = array();
                while ($row = $res->fetch_row()){
                    $matches[$row[0]] = $row[1];
                }
                $res->close();
            }
        }
        return $result;
}

/**
* insert/update - creates and executes the parmetrized query
* 
* @param mixed $mysqli
* @param mixed $table_name
* @param mixed $table_prefix
* @param mixed $record
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

//DEBUG print 
//error_log(" upfate: ".$query);

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

// import functions =====================================

/**
* 1) Performs mapping validation (required fields, enum, pointers, numeric/date)
* 2) Counts matched (update) and new records
* 
* @param mixed $mysqli
*/
function validateImport($mysqli, $imp_session, $params){

    //add result of validation to session 
    $imp_session['validation'] = array( "count_update"=>0, "count_insert"=>0, "count_error"=>0, "err_message"=>null );  
    
    
  
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    $is_secondary = ($params['sa_type']==0);
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    $import_table = $imp_session['import_table'];
    $is_update = false;
    $rt_field = null;
    
    //get field mapping and selection query from _REQUEST(params)
    $mapping = array();  // fieldtype ID => fieldname in import table
    $sel_query = array();
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_dt_")===0 && $field_type){
            //get index 
            $index = substr($key,6);
            $field_name = "field_".$index;
            
            //all mapped fields - they will be used in validation query
            array_push($sel_query, $field_name); 
            
            if($field_type=="id"){
                //AA $imp_session['indexes'][$recordType] = $field_name;
                $rt_field = $field_name;
            }else {
                //AA array_push($sel_query, $field_name);    
                $mapping[$field_type] = $field_name;
                //AA $imp_session["mapping"][$field_name] = $recordType.".".$field_type;
            }
        }
    }  
    if(count($sel_query)<1){
        return "mapping not defined";
    }
    
    if(!$rt_field && @$imp_session['indexes'][$recordType]){
        $rt_field = $imp_session['indexes'][$recordType];
    }
  
           
   if($rt_field){ //index field is defined
            //select all mapped fields   .$rt_field.", "
            $select_query = "select SQL_CALC_FOUND_ROWS distinct ".implode(",",$sel_query)." from ".$import_table
            ." left join Records on rec_RecTypeID=".$recordType." and rec_ID=".$rt_field;
            
   }else{
            //if index field is not specified
            $select_query = "select SQL_CALC_FOUND_ROWS distinct ".implode(",",$sel_query)." from ".$import_table;
            
            $i = 1;
            $select_query_join = " left join Records on rec_RecTypeID=".$recordType;
            foreach ($mapping as $field_type => $field_name) {
                $select_query = $select_query . " left join recDetails d".$i." on d".$i.".dtl_Value=".$field_name;
                $select_query_join = $select_query_join . " and rec_ID=d".$i.".dtl_RecID";
                
                $i++;    
            }
            $select_query = $select_query . $select_query_join;
   }
            
        
    $imp_session['validation']['mapped_fields'] = $sel_query;        
    //find records to update
    $res = $mysqli->query($select_query . " where rec_ID is not null");
    if($res){
        $fres = $mysqli->query('select found_rows()');
        $row = $fres->fetch_row(); 
        $imp_session['validation']['count_update'] = $row[0];
        if($row[0]>0){
            $imp_session['validation']['recs_update'] = array();
            $cnt = 0;
            while ($row = $res->fetch_row()){
                array_push($imp_session['validation']['recs_update'], $row);
                $cnt++;
                if($cnt>99) break;
            }
        }
    }else{
        return "Can not execute query to calculate number of records to be updated";
    }
    
    //find records to insert
    $res = $mysqli->query($select_query . " where rec_ID is null");
//error_log(">>>>".$select_query. " where rec_ID is null");    
    if($res){
        $fres = $mysqli->query('select found_rows()');
        $row = $fres->fetch_row(); 
        $imp_session['validation']['count_insert'] = $row[0];
        if($row[0]>0){
            $imp_session['validation']['recs_insert'] = array();
            $cnt = 0;
            while ($row = $res->fetch_row()){
                array_push($imp_session['validation']['recs_insert'], $row);
                $cnt++;
                if($cnt>99) break;
            }
        }
    }else{
        return "Can not execute query to calculate number of records to be inserted";
    }
            
            
            
/*            
$res = mysql_query($query);
if (mysql_error()) {
    error_log("queryError in getResultsPageAsync -".mysql_error());
}
$fres = mysql_query('select found_rows()');
*/            

    
    

 // fill array with field in import table to be validated 
 $recStruc = getRectypeStructures(array($recordType));  
 $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
 $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    
 $missed = array();
 $query_reqs = array(); //fieldname from import table
 $query_reqs_where = array(); //where clause for validation

 $query_enum = array();
 $query_enum_join = "";
 $query_enum_where = array();

 $query_res = array();
 $query_res_join = "";
 $query_res_where = array();
 
 $query_num = array();
 $query_num_nam = array();
 $query_num_where = array();
 
 $query_date = array();
 $query_date_nam = array();
 $query_date_where = array();
 
 //loop for all fields in record type structure
 foreach ($recStruc[$recordType]['dtFields'] as $ft_id => $ft_vals) {
     
    //find among mappings
    $field_name = @$mapping[$ft_id];
    if(!$field_name){
       $field_name = array_search($recordType.".".$ft_id, $imp_session["mapping"], true); //from previos session
    }
     
  //DEBUG  if($field_name) error_log($field_name."=>".$recordType.".".$ft_id);     
     
    if($ft_vals[$idx_reqtype] == "required"){
        if(!$field_name){
            array_push($missed, $ft_vals[0]);
        }else{
            array_push($query_reqs, $field_name);
            array_push($query_reqs_where, $field_name." is null or ".$field_name."=''");
        }
    }
    
    if($field_name){
        
        //$field_alias = $imp_session['columns'][substr($field_name,6)];
        
        if($ft_vals[$idx_fieldtype] == "enum" ||  $ft_vals[$idx_fieldtype] == "relationtype") {
            array_push($query_enum, $field_name);
            $trm1 = "trm".count($query_enum);
            $query_enum_join  = $query_enum_join.
     " left join defTerms $trm1 on if(concat('',$field_name * 1) = $field_name,$trm1.trm_ID=$field_name,$trm1.trm_Label=$field_name) ";
            array_push($query_enum_where, $trm1.".trm_Label is null");
            
        }else if($ft_vals[$idx_fieldtype] == "resource"){
            array_push($query_res, $field_name);
            $trm1 = "rec".count($query_res);
            $query_res_join  = $query_res_join.
     " left join Records $trm1 on $trm1.rec_ID=$field_name ";
            array_push($query_res_where, $trm1.".rec_ID is null");
            
        }else if($ft_vals[$idx_fieldtype] == "float" ||  $ft_vals[$idx_fieldtype] == "integer") {
            
            array_push($query_num, $field_name);
            array_push($query_num_where, " concat('',$field_name * 1) != $field_name ");

        }else if($ft_vals[$idx_fieldtype] == "date" ||  $ft_vals[$idx_fieldtype] == "year") {

            array_push($query_date, $field_name);
            if($ft_vals[$idx_fieldtype] == "year"){
                array_push($query_date_where, " concat('',$field_name * 1) != $field_name ");
            }else{
                array_push($query_date_where, "str_to_date($field_name, '%Y-%m-%d %H:%i:%s') is null ");
            }
            
        }
    
    }
 }
 
 //1. Verify that all required field are mapped  =====================================================
 if(count($missed)>0){
     return "Required fields are missed in mapping: ".implode(",", $missed);
 }
 
 //2. In DB: Verify that all required fields have values =============================================
 if(count($query_reqs)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
        ." from $import_table "
        ." where ".implode(" or ",$query_reqs_where);
        
//error_log("check empty: ".$query);
     $wrong_records = getWrongRecords($mysqli, $query, "required fields are null or empty", $imp_session, $query_reqs);
     if($wrong_records) return $wrong_records;
 } 
 //3. In DB: Verify that enumeration fields have correct values =====================================
 /*
 "select $field_name from $import_table "
 ."left join defTerms trm1 on if(concat('',$field_name * 1) = $field_name,trm1.trm_ID=$field_name,trm1.trm_Label=$field_name) "
 ." where trm1.trm_Label is null";
 */
 if(count($query_enum)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table ".$query_enum_join
                ." where ".implode(" or ",$query_enum_where);
     $wrong_records = getWrongRecords($mysqli, $query, "fields mapped as enumeration", $imp_session, $query_enum);
     if($wrong_records) return $wrong_records;
 }
 //4. In DB: Verify resource fields
 if(count($query_res)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table ".$query_res_join
                ." where ".implode(" or ",$query_res_where);
     $wrong_records = getWrongRecords($mysqli, $query, "fields mapped as resources(pointers)", $imp_session, $query_res);
     if($wrong_records) return $wrong_records;
 }
 
 //5. Verify numeric fields
 if(count($query_num)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table "
                ." where ".implode(" or ",$query_num_where);
     $wrong_records = getWrongRecords($mysqli, $query, "fields mapped as numeric", $imp_session, $query_num);
     if($wrong_records) return $wrong_records;
 }
 
 //6. Verify datetime fields
 if(count($query_date)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table "
                ." where ".implode(" or ",$query_date_where);
     $wrong_records = getWrongRecords($mysqli, $query, "fields mapped as date", $imp_session, $query_date);
     if($wrong_records) return $wrong_records;
 }
 
 //7. TODO Verify geo fields
 

 return $imp_session;   
}

/**
* execute validation query and fill session array with validation results
* 
* @param mixed $mysqli
* @param mixed $query
* @param mixed $message
* @param mixed $imp_session
* @param mixed $fields_checked
*/
function getWrongRecords($mysqli, $query, $message, $imp_session, $fields_checked){
    
    $res = $mysqli->query($query);
    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){
            array_push($wrong_records, $row);
        }        
        $res->close();
        if(count($wrong_records)>0){
            $imp_session['validation']["count_error"] = count($wrong_records);
            $imp_session['validation']["recs_error"] = array_slice($wrong_records,0,100);
            $imp_session['validation']["field_checked"] = $fields_checked;
            $imp_session['validation']["err_message"] = $message;
            return $imp_session;
        }
        
    }else{
        return "Can not perform validation query: ".$message;
    }
    return null;
}

/**
* create or update records
* 
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function doImport($mysqli, $imp_session, $params){
    
    //rectype to import
    $recordType = @$params['sa_rectype'];
    $is_secondary = ($params['sa_type']==0);
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    $import_table = $imp_session['import_table'];
    $is_update = false;
    $rt_field = null;
    $recStruc = getRectypeStructures(array($recordType));
    $recTypeName = $recStruc[$recordType]['commonFields'][ $recStruc['commonNamesToIndex']['rty_Name'] ];
    $idx_name = $recStruc['dtFieldNamesToIndex']['rst_DisplayName'];
    
    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    //get terms name=>id
    $terms_enum = null;
    $terms_relation = null;
    
    //get field mapping and selection query from _REQUEST(params)
    $mapping = array();  // fieldtype ID => fieldname in import table
    $sel_query = "";
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_dt_")===0 && $field_type){
            //get index 
            $index = substr($key,6);
            $field_name = "field_".$index;
            
            if($field_type=="id"){
                $imp_session['indexes'][$recordType] = $field_name;
            }else {
                $sel_query = $sel_query.$field_name.", ";    
                array_push($mapping, $field_type);
                $imp_session["mapping"][$field_name] = $recordType.".".$field_type;
                    //$recTypeName."  ".$recStruc[$recordType]['dtFields'][$field_type][$idx_name]; 
                        
            }
            
            
        }
    }

    //detect rectype in indexes 
    if(@$imp_session['indexes'][$recordType]){ //exists - it means update
        
            $rt_field = $imp_session['indexes'][$recordType];
            $is_update = true;
            
     }else{ //insert
            //add new field in import table - to keep key values (primary index)
            $index = count($imp_session['columns']);
            $rt_field = "field_".$index;
            array_push($imp_session['columns'], $recTypeName."  index" );
            array_push($imp_session['uniqcnt'], 0);
            if(!$is_secondary) $imp_session["mapping"][$rt_field] = $recordType.".id"; //$recTypeName."(id# $recordType) ID";
            $imp_session['indexes'][$recordType] = $rt_field;
        
            $altquery = "alter table ".$import_table." add column ".$rt_field." int(10) unsigned";
            if (!$mysqli->query($altquery)) {
                return "can not alter import session table, can not add new index field: " . $mysqli->error;
            }    
    }
    if($sel_query==""){
        return "mapping not defined";
    }
  
/*
       if($is_update){ //index field is defined
                //select all mapped fields   .$rt_field.", "
                $select_query = "select SQL_CALC_FOUND_ROWS distinct ".implode(",",$sel_query)." from ".$import_table
                ." left join Records on rec_RecTypeID=".$recordType." and rec_ID=".$rt_field;
                
       }else{
                //if index field is not specified
                $select_query = "select SQL_CALC_FOUND_ROWS distinct ".implode(",",$sel_query)." from ".$import_table;
                
                $i = 1;
                $select_query_join = " left join Records on rec_RecTypeID=".$recordType;
                foreach ($mapping as $field_type => $field_name) {
                    $select_query = $select_query . " left join recDetails d".$i." on d".$i.".dtl_Value=".$field_name;
                    $select_query_join = $select_query_join . " and rec_ID=d".$i.".dtl_RecID";
                    
                    $i++;    
                }
                $select_query = $select_query . $select_query_join;
       }
*/    
    
    array_push($mapping, "id"); //last field is row# - imp_id
    if($is_secondary){
        $sel_query = "select ". $sel_query . ($is_update?"":$rt_field.",") 
                    . " group_concat(imp_id) from ".$import_table
                    . " group by ". $sel_query . ($is_update?"":$rt_field);    
        
    }else{ //@todo: remove this - to avoid duplications
        $sel_query = "select ". $sel_query . $rt_field.", imp_id from ".$import_table;
    }
    
//error_log($sel_query);
    
    $res = $mysqli->query($sel_query);
    if (!$res) {
        
        return "import table is empty";
        
    }else{
        
        $rep_processed = 0;
        $rep_added = 0;
        $rep_updated = 0;
        $errors = array();
        $warnings = array();
        $tot_count = $imp_session['reccount'];

        while ($row = $res->fetch_row()){
            
            $recordId = null;
            $recordURL = null;
            $recordNotes = null;
            $details = array();
        
            foreach ($mapping as $index => $field_type) {
                if($field_type=="id" && $is_update){
                    $recordId = $row[$index];
                }else if($field_type=="url"){
                    $recordURL = $row[$index];
                }else if($field_type=="notes"){
                    $recordNotes = $row[$index];
                }else{
                    
                    $ft_vals = $recStruc[$recordType]['dtFields'][$field_type];
                    if(($ft_vals[$idx_fieldtype] == "enum" || $ft_vals[$idx_fieldtype] == "relationtype") 
                            && !ctype_digit($row[$index])) {
                                
                        $value = null;        
                    
                        if($ft_vals[$idx_fieldtype] == "enum"){
                            if(!$terms_enum)
                            $terms_enum = mysql__select_array3($mysqli, "select trm_Label, trm_ID from defTerms where trm_Domain='enum'");
                            $value = @$terms_enum[$row[$index]];
                        }else if($ft_vals[$idx_fieldtype] == "relationtype"){
                            if(!$terms_relation)
                            $terms_relation = mysql__select_array3($mysqli, "select trm_Label, trm_ID from defTerms where trm_Domain='relation'");
                            $value = @$terms_relation[$row[$index]];
                        }
                        
                        $details["t:".$field_type] = array("1"=>$value);    
                        
                    }else{
                        $details["t:".$field_type] = array("1"=>$row[$index]);    
                    }
                    
                }
            }
    
            //add-update Heurist record
            // temp
            $out = saveRecord($recordId, $recordType,
                $recordURL,
                $recordNotes,
                null, //???get_group_ids(), //group
                null, //viewable
                null, //bookmark
                null, //pnotes
                null, //rating
                null, //tags
                null, //wgtags
                $details,
                null, //-notify
                null, //+notify
                null, //-comment
                null, //comment
                null //+comment
            );    
            
            if (@$out['error']) {
                //print "<div style='color:red'>Error: ".implode("; ",$out["error"])."</div>";
                return "can not insert record ".implode("; ",$out["error"]);
            }else{
                if($recordId==null){
                    
                    $updquery = "update ".$import_table." set ".$rt_field."=".$out["bibID"]." where imp_id in (".end($row).")";
//error_log(">>>>".$updquery);
                    if(!$mysqli->query($updquery)){
                        return "can not update import table (set record id)";
                    }
                    
                    $rep_added++;
                }else{
                    $rep_updated++;
                }
                
                if (@$out['warning']) {
                    print "<div style=\"color:#ff8844\">Warning: ".implode("; ",$out["warning"])."</div>";
                }

                $rep_processed++;
            }
   

            if ($rep_processed % 25 == 0) {
                print '<script type="text/javascript">update_counts('.$rep_added.','.$rep_updated.','.$tot_count.')</script>'."\n";
                ob_flush();
                flush();
            }
   
        }//while
        $res->close();
    }    

    //save mapping into import_sesssion
    $imp_id = mysql__insertupdate($mysqli, "import_sessions", "imp", 
            array("imp_ID"=>$imp_session["import_id"], "imp_session"=>json_encode($imp_session) ));    
    if(intval($imp_id)<1){
        return "can not save session";
    }else{
        return $imp_session;
    }
}


/*
* Get values for given ID from imort table (to preview values on UI)
* 
* @param mixed $rec_id
* @param mixed $import_table
*/
function get_import_value($rec_id, $import_table){
    global $mysqli;
    
    $query = "select * from $import_table where imp_id=".$rec_id;
    $res = mysql__select_array2($mysqli, $query);
                
//error_log(">>>".$query."  ".json_encode($res));
                
    header('Content-type: text/javascript');
    print json_encode($res);
}

/**
* Loads import sessions by ID
* 
* @param mixed $import_id
* @return mixed
*/
function get_import_session($mysqli, $import_id){
    
    if($import_id && is_numeric($import_id)){
        
        $res = mysql__select_array2($mysqli, 
                "select imp_session, imp_table from import_sessions where imp_id=".$import_id);
        
        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        $session["import_table"] = $res[1];
    
        return $session;    
    }else{
        return "Can not load import session id#".$import_id;       
    }
}

/**
* Loads all sessions for current user (to populate dropdown)
*/
function get_list_import_sessions(){
    
    global $mysqli;
    
     $query = "CREATE TABLE IF NOT EXISTS `import_sessions` (
    `imp_ID` int(11) unsigned NOT NULL auto_increment,
    `ugr_ID` int(11) unsigned NOT NULL,
    `imp_table` varchar(255) NOT NULL default '',
    `imp_session` text,
    PRIMARY KEY  (`imp_ID`))";
    if (!$mysqli->query($query)) {
        return "can not create import session table: " . $mysqli->error;
    }    

    $ret = '<option value="0">select session...</option>';
    $query = "select imp_ID, imp_table from import_sessions"; //" where ugr_ID=".get_user_id();
    $res = $mysqli->query($query);
    if ($res){
        while ($row = $res->fetch_row()){
            $ret = $ret.'<option value="'.$row[0].'">'.$row[1].'</option>';
        }
        $res->close();
    }    
    //return "can not load list of sessions: " . $mysqli->error;
    return $ret;
}

//
// render the list of records as a table
//
function renderRecords($type, $imp_session){
    
        ///DEBUG print "fields ".print_r(@$validationRes['field_checked'],true)."<br> recs";
        ///DEBUG print print_r(@$validationRes['rec_error'],true);
        
        $cnt = $imp_session['validation']['count_'.$type];
        $records = $imp_session['validation']['recs_'.$type];
        $mapped_fields = $imp_session['validation']['mapped_fields'];

        //print print_r( @$imp_session['validation']['field_checked'], true);
        if($cnt>count($records)){
            print "<div>First 100 records of ".$cnt."</div>";
        }
        print '<table border="0">'; // class="tbmain"
        if($type=="error"){
            $checked_fields = $imp_session['validation']['field_checked'];
            print "<thead><th>Line #</th>";
        }else{
            $checked_fields = array(); 
        }
        
            
            foreach($mapped_fields as $field_name) {
                
                $colname = @$imp_session['columns'][substr($field_name,6)];
                if(array_search($field_name, $checked_fields)!==false){
                    $colname = "<i>".$colname."</i>";
                }
                
                print "<th>".$colname."</th>";
            }
            print "</thead>";
            foreach ($records as $row) {  

                print "<tr>";
                if(is_array($row)){
                    foreach($row as $value) {     
                        print "<td>".($value?$value:"&nbsp;")."</td>";
                    }
                }
                print "</tr>";
            }
        print "</table>";
        
        print '<input type="button" value="Back" onClick="showRecords(\'mapping\');">';
    
}


?>
