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
function mysql__select_array3($mysqli, $query, $withindex=true) {
//error_log($query);
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if ($res){
                $result = array();
                while ($row = $res->fetch_row()){
                    if($withindex){
                        $result[$row[0]] = $row[1];
                    }else{
                        array_push($result, $row);
                    }
                }
                $res->close();
                
//error_log(">>>>".count($result));                
            }else{
                error_log("ERROR: ".$mysqli->error);
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
// matching functions ===================================

/**
* Finds record ids in heurist database by key fields
* 
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function matchingSearch($mysqli, $imp_session, $params){
    
    //add result of validation to session 
    $imp_session['validation'] = array( "count_update"=>0, "count_insert"=>0, "count_error"=>0, "err_message"=>null );  
    $import_table = $imp_session['import_table'];
    
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    //get fields that will be used in search
    $sel_query = array();
    $select_query_join_rec = " left join Records on rec_RecTypeID=".$recordType;
    $select_query_join_det = "";
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_keyfield_")===0 && $field_type){
            //get index 
            $index = substr($key,12);
            $field_name = "field_".$index;
        
            array_push($sel_query, $field_name); 
            
            if($field_type=="id" || $field_type=="url" || $field_type=="scratchpad"){
                $select_query_join_rec = $select_query_join_rec." and rec_".$field_type."=".$field_name;
            }else{
                $select_query_join_rec = $select_query_join_rec . " and rec_ID=d".$index.".dtl_RecID";
                $select_query_join_det = $select_query_join_det . 
                 " left join recDetails d".$index." on d".$index.".dtl_DetailTypeID=".$field_type.
                 " and REPLACE(REPLACE(TRIM(d".$index.".dtl_Value),'  ',' '),'  ',' ')=REPLACE(REPLACE(TRIM(".$field_name."),'  ',' '),'  ',' ')";
            }
        }            
    }  
    if(count($sel_query)<1){
        return "no one key field is selected";
    }
        
    //query to search record ids                distinct 
    $select_query = "SELECT SQL_CALC_FOUND_ROWS rec_ID, group_concat(imp_id), ".implode(",",$sel_query)
                    ." FROM ".$import_table
                    .$select_query_join_det.$select_query_join_rec
                    ." WHERE rec_ID is not null"
                    ." GROUP BY rec_ID, ".implode(",",$sel_query);

//DEBUG error_log(">>>".$select_query);
    //array_push($sel_query, "rec_ID");
    $imp_session['validation']['mapped_fields'] = $sel_query;        
    //find records to update
    $res = $mysqli->query($select_query);
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
    $select_query = "SELECT SQL_CALC_FOUND_ROWS group_concat(imp_id), ".implode(",",$sel_query)." FROM ".$import_table
                    .$select_query_join_det.$select_query_join_rec
                    ." WHERE rec_ID is null "
                    ." GROUP BY ".implode(",",$sel_query);
    $res = $mysqli->query($select_query);
//DEBUG error_log(">>>>".$select_query);    
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
    
    
    return $imp_session; 
}

/**
* Assign record ids to field in import table
* 
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function matchingAssign($mysqli, $imp_session, $params){
    
    $import_table = $imp_session['import_table'];
    
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    $id_field = @$params['idfield'];
    $field_count = count($imp_session['columns']);
    
//DEBUG error_log(">>>".$id_field);    
    
    if(!$id_field){ //add new field into import table
            //ID field not defined, create new field
        
            $id_field = "field_".$field_count;
            array_push($imp_session['columns'], @$params['new_idfield']?$params['new_idfield'] : "Record type #$recordType index" );
            array_push($imp_session['uniqcnt'], 0);
            
            //$imp_session["mapping"][$id_field] = $recordType.".id"; //$recTypeName."(id# $recordType) ID";
            $imp_session['indexes'][$id_field] = $recordType;
        
            $altquery = "alter table ".$import_table." add column ".$id_field." int(10) ";
            
//DEBUG error_log(">>>".$altquery);   
            
            if (!$mysqli->query($altquery)) {
                return "can not alter import session table, can not add new index field: " . $mysqli->error;
            }    
    }
    
    //get fields that will be used in search
    $sel_query = array();
    $select_query_join_rec = " left join Records on rec_RecTypeID=".$recordType;
    $select_query_join_det = "";
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_keyfield_")===0 && $field_type){
            //get index 
            $index = substr($key,12);
            $field_name = "field_".$index;
        
            array_push($sel_query, $field_name); 
            
            if($field_type=="id" || $field_type=="url" || $field_type=="scratchpad"){
                $select_query_join_rec = $select_query_join_rec." and rec_".$field_type."=".$field_name;
            }else{
                $select_query_join_rec = $select_query_join_rec . " and rec_ID=d".$index.".dtl_RecID";
                $select_query_join_det = $select_query_join_det . 
                 " left join recDetails d".$index." on d".$index.".dtl_DetailTypeID=".$field_type
                 ." and REPLACE(REPLACE(TRIM(d".$index.".dtl_Value),'  ',' '),'  ',' ')=REPLACE(REPLACE(TRIM(".$field_name."),'  ',' '),'  ',' ')";
            }
        }            
    }  
    if(count($sel_query)<1){
        return "no one key field is selected";
    }
        
    //query to search record ids
   
    //to update - assign existing rec_ID from heurist
    
    //SET SQL_SAFE_UPDATES=1; 
    //reset all values 
    $updquery = "UPDATE ".$import_table." SET ".$id_field."=NULL WHERE imp_id>0";
    if(!$mysqli->query($updquery)){
        return "can not update import table (clear record id field)";
    }
    //matched records
    $updquery = "UPDATE ".$import_table.$select_query_join_det.$select_query_join_rec." SET ".$id_field."=rec_ID WHERE rec_ID is not null and imp_id>0";
    
//DEBUG error_log("1>>>>".$updquery);
    if(!$mysqli->query($updquery)){
        return "can not update import table (set record id field)";
    }
    
    //new records   ".implode(",",$sel_query).",
    $mysqli->query("SET SESSION group_concat_max_len = 1000000");
    $select_query = "SELECT group_concat(imp_id), ".implode(",",$sel_query)." FROM ".$import_table
                    . $select_query_join_det.$select_query_join_rec
                    . " WHERE $id_field is NULL"
                    . " GROUP BY ". implode(",",$sel_query);
    //$select_query = "select group_concat(imp_id) from ".$import_table
    //                ." where $id_field is NULL";
                    
//DEBUG error_log("2>>>>".$select_query);
                    
    $res = $mysqli->query($select_query);
    if($res){
        $ind = -1;
        while ($row = $res->fetch_row()){
            
            $ids = $row[0];
            if(substr($ids, -1)==","){
                $ids = substr($row[0], 0, -1);
            }
            $ids = explode(",",$ids);
            $k = 0;
            while ($k<count($ids)) {
                $ids_part = array_slice($ids,$k,100);
                
                $updquery = "update ".$import_table." set ".$id_field."=".$ind." where imp_id in (".implode(",",$ids_part).")";  //end($row)
//DEBUG 
//error_log("3>>>>".$updquery);            
                if(!$mysqli->query($updquery)){
                    return "can not update import table: mark records for insert";
                }
                $k = $k+100;
            }
            $ind--;
        }    
    }else{
        return "can not perform query - find unmatched records";
    }    
    
    //calculate distinct number of ids
    $row = mysql__select_array2($mysqli, "select count(distinct ".$id_field.") from ".$import_table);
    if(is_array($row)){

            $index = intval(substr($id_field, 6));
            $imp_session['uniqcnt'][$index] = $row[0];
        
            //save index field into import_session
            $imp_session = saveSession($mysqli, $imp_session);
            if(!is_array($imp_session)){
                return $imp_session;
            }
    }

/*    
    $updquery0 =  "SET @pos := -1";
    $updquery1 = "update ".$import_table.$select_query_join_det.$select_query_join_rec." set ".$id_field."=( SELECT @pos := @pos - 1 ) where rec_ID is null and imp_id>0 group by ". implode(",",$sel_query);
    
error_log("2>>>>".$updquery1);
    
    $mysqli->query($updquery0);
    if(!$mysqli->query($updquery1)){
        return "can not update import table: mark records for insert";
    }
*/

    return $imp_session;
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
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    $import_table = $imp_session['import_table'];
    $id_field = @$params['recid_field']; //record ID field is always defined explicitly
   
//error_log(">>>>".$id_field)   ;
    
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
            $mapping[$field_type] = $field_name;
        }
    }  
    if(count($sel_query)<1){
        return "mapping not defined";
    }
    $imp_session['validation']['mapped_fields'] = $sel_query;
    
    if(!$id_field){ //ID field not defined - all records will be inserted
        $imp_session['validation']["count_insert"] = $imp_session['reccount'];
        $select_query = "SELECT imp_id, ".implode(",",$sel_query)." FROM ".$import_table." LIMIT 100";
        $imp_session['validation']['recs_insert'] = mysql__select_array3($mysqli, $select_query, false);
    }else{
        
        $cnt_recs_insert_nonexist_id = 0;
        
        // validate selected record ID field
        // in case id field is not created on match step (it is from original set of columns)
        // we have to verify that its values are valid
        if(!@$imp_session['indexes'][$id_field]){ 
        
             //find recid with different rectype
             $query = "select imp_id, ".implode(",",$sel_query).", ".$id_field
                ." from ".$import_table 
                ." left join Records on rec_ID=".$id_field
                ." where rec_RecTypeID<>".$recordType;
        //error_log("check wrong rt: ".$query);
             $wrong_records = getWrongRecords($mysqli, $query, "Your input data contains record IDs in the selected ID column for existing records which are of a different type from that specified. The import cannot proceed until this is corrected.", $imp_session, array($id_field));
             if($wrong_records) {
                array_push($imp_session['validation']['mapped_fields'], $id_field);
                return $wrong_records;          
             }
             
             //find record ID that are not exists in HDB - to insert
             $query = "select count(imp_id) "
                ." from ".$import_table 
                ." left join Records on rec_ID=".$id_field
                ." where ".$id_field.">0 and rec_ID is null";
             $row = mysql__select_array2($mysqli, $query);   
             if($row && $row[0]>0){
                $cnt_recs_insert_nonexist_id = $row[0];
             }
        }
        
       // find records to update
       $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table
        ." left join Records on rec_ID=".$id_field." WHERE rec_ID is not null and ".$id_field.">0";
//DEBUG error_log("1.upd >>>>".$select_query);        
       $row = mysql__select_array2($mysqli, $select_query);
       if($row){
           
           if( $row[0]>0 ){
           
           $imp_session['validation']['count_update'] = $row[0];
           //find first 100 records to display
           $select_query = "SELECT ".$id_field.", imp_id, ".implode(",",$sel_query)
           ." FROM ".$import_table
           ." left join Records on rec_ID=".$id_field
           ." WHERE rec_ID is not null and ".$id_field.">0"
           ." ORDER BY ".$id_field." LIMIT 100";
//DEBUG error_log("2.upd >>>>".$select_query);           
           $imp_session['validation']['recs_update'] = mysql__select_array3($mysqli, $select_query, false);
           
           }
       }else{
            return "Can not execute query to calculate number of records to be updated!";
       }
       // find records to insert
       $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table." WHERE ".$id_field." is null OR ".$id_field."<0";
//DEBUG error_log("1.ins >>>>".$select_query);           
       $row = mysql__select_array2($mysqli, $select_query);
       if($row){
           if( $row[0]>0 ){
                $imp_session['validation']['count_insert'] = $row[0];
                //find first 100 records to display
                $select_query = "SELECT imp_id, ".implode(",",$sel_query)." FROM ".$import_table." WHERE ".$id_field." is null OR ".$id_field."<0 LIMIT 100";
//DEBUG error_log("2.ins >>>>".$select_query);           
                $imp_session['validation']['recs_insert'] = mysql__select_array3($mysqli, $select_query, false);
           }
       }else{
            return "Can not execute query to calculate number of records to be inserted";
       }
       //additional query for non-existing IDs
       if($cnt_recs_insert_nonexist_id>0){
           
               $imp_session['validation']['count_insert_nonexist_id'] = $cnt_recs_insert_nonexist_id;
               $imp_session['validation']['count_insert'] = $imp_session['validation']['count_insert']+$cnt_recs_insert_nonexist_id;
           
               $select_query = "SELECT imp_id, ".implode(",",$sel_query)
                ." FROM ".$import_table 
                ." LEFT JOIN Records on rec_ID=".$id_field
                ." WHERE ".$id_field.">0 and rec_ID is null LIMIT 100";
                $res = mysql__select_array3($mysqli, $select_query, false);
                if($res && count($res)>0){
                    if(@$imp_session['validation']['recs_insert']){
                        $imp_session['validation']['recs_insert'] = array_merge($imp_session['validation']['recs_insert'], $res);    
                    }else{
                        $imp_session['validation']['recs_insert'] = $res;
                    }
                }
       }
           
    }//id_field defined
    

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
 if(count($missed)>0  &&
    ($imp_session['validation']['count_insert']>0 ||  // there are records to be inserted
    ($params['sa_upd']==2 && $params['sa_upd2']==1)   // Delete existing if no new data supplied for record  
    )){
     return "Required fields are missed in mapping: ".implode(",", $missed);
 }
 
 //2. In DB: Verify that all required fields have values =============================================
 if(count($query_reqs)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
        ." from $import_table "
        ." where ".implode(" or ",$query_reqs_where);
        
//error_log("check empty: ".$query);
     $wrong_records = getWrongRecords($mysqli, $query, "Required fields are null or empty", $imp_session, $query_reqs);
     if($wrong_records) return $wrong_records;
 } 
 //3. In DB: Verify that enumeration fields have correct values =====================================
 /*
 "select $field_name from $import_table "
 ."left join defTerms trm1 on if(concat('',$field_name * 1) = $field_name,trm1.trm_ID=$field_name,trm1.trm_Label=$field_name) "
 ." where trm1.trm_Label is null";
 */
 $hwv = " have wrong values";
 if(count($query_enum)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table ".$query_enum_join
                ." where ".implode(" or ",$query_enum_where);
//DEBUG error_log("check enum: ".$query);                
                
     $wrong_records = getWrongRecords($mysqli, $query, "Fields mapped as enumeration".$hwv, $imp_session, $query_enum);
     if($wrong_records) return $wrong_records;
 }
 //4. In DB: Verify resource fields
 if(count($query_res)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table ".$query_res_join
                ." where ".implode(" or ",$query_res_where);
     $wrong_records = getWrongRecords($mysqli, $query, "Fields mapped as resources(pointers)".$hwv, $imp_session, $query_res);
     if($wrong_records) return $wrong_records;
 }
 
 //5. Verify numeric fields
 if(count($query_num)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table "
                ." where ".implode(" or ",$query_num_where);
     $wrong_records = getWrongRecords($mysqli, $query, "Fields mapped as numeric".$hwv, $imp_session, $query_num);
     if($wrong_records) return $wrong_records;
 }
 
 //6. Verify datetime fields
 if(count($query_date)>0){
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table "
                ." where ".implode(" or ",$query_date_where);
     $wrong_records = getWrongRecords($mysqli, $query, "Fields mapped as date".$hwv, $imp_session, $query_date);
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

// global variable for progress report
$rep_processed = 0;
$rep_added = 0;
$rep_updated = 0;

/**
* create or update records
* 
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function doImport($mysqli, $imp_session, $params){
    
    global $rep_processed,$rep_added,$rep_updated;

    
    //rectype to import
    $import_table = $imp_session['import_table'];
    $recordType = @$params['sa_rectype'];
    $id_field = @$params['recid_field']; //record ID field is always defined explicitly
    
    if(intval($recordType)<1){
        return "record type not defined";
    }

    //get field mapping and selection query from _REQUEST(params)
    $field_types = array();  // idx => fieldtype ID 
    $sel_query = array();
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_dt_")===0 && $field_type){
            //get index 
            $index = substr($key,6);
            $field_name = "field_".$index;
            
            //all mapped fields - they will be used in validation query
            array_push($sel_query, $field_name); 
            array_push($field_types, $field_type);
            $imp_session["mapping"][$field_name] = $recordType.".".$field_type; // to keep used
        }
    }  
    if(count($sel_query)<1){
        return "mapping not defined";
    }
    
    //indexes
    $recStruc = getRectypeStructures(array($recordType));
    $recTypeName = $recStruc[$recordType]['commonFields'][ $recStruc['commonNamesToIndex']['rty_Name'] ];
    $idx_name = $recStruc['dtFieldNamesToIndex']['rst_DisplayName'];
    
    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    //get terms name=>id
    $terms_enum = null;
    $terms_relation = null;

    $select_query = "SELECT ". implode(",", $sel_query) 
                    . ( $id_field ?", ".$id_field:"" ) 
                    . ", imp_id "  //last field is row# - imp_id
                    . " FROM ".$import_table;

    if($id_field){  //add to list of columns
            $id_field_idx = count($field_types); //last one
            $select_query = $select_query." ORDER BY ".$id_field;
    }else{
            //create id field by defauld
            $field_count = count($imp_session['columns']);
            $id_field_def = "field_".$field_count;
            array_push($imp_session['columns'], "ID field for Record type #$recordType" );
            
            
            $imp_session['indexes'][$id_field_def] = $recordType;
        
            $altquery = "alter table ".$import_table." add column ".$id_field_def." int(10) ";
            if (!$mysqli->query($altquery)) {
                return "can not alter import session table, can not add new index field: " . $mysqli->error;
            }    
    }
        
//error_log($sel_query);
    
    $res = $mysqli->query($select_query);
    if (!$res) {
        
        return "import table is empty";
        
    }else{

        $rep_processed = 0;
        $rep_added = 0;
        $rep_updated = 0;
        $tot_count = $imp_session['reccount'];
        $first_time = true;
        $step = ceil($tot_count/100);
        if($step>100) $step = 100;
        else if($step<1) $step=1;
   
        
        $previos_recordId = null;
        $recordId = null;
        $details = array();
        $details2 = array(); //to keep original for sa_mode=2 (replace all existing value)

        while ($row = $res->fetch_row()){
            
            //detect mode
            if($id_field){ //id field defined - detect insert or update
                
                $recordId_in_import = $row[$id_field_idx];
                if($previos_recordId!=$recordId_in_import){ //next record ID 
                    
                    //save data
                    if($previos_recordId!=null){ //perform action
                        $details = retainExisiting($details, $details2, $params);
                        doInsertUpdateRecord($recordId, $params, $details, $id_field); //$recordURL, $recordNotes, 
                    }
                    $previos_recordId = $recordId_in_import;
                    
                    //reset
                    $details = array();
                    $details2 = array();
                    
                    if($recordId_in_import>0){ //possible update
                        // find original record in HDB
                        $details2 = findOriginalRecord($recordId_in_import);
                        if(count($details2)==0){ 
                            //record not found - this is insert with predefined ID
                            $recordId = -$recordId_in_import;
                        }else{
                            // record found - update detail according TO settings
                            $recordId = $recordId_in_import;
                        }
                        
                        //if(!($params['sa_upd']==2 && $params['sa_upd2']==1)){//Delete existing if no new data supplied for record
                        //    $details2 = $details;   //copy array - original record values
                        //}
                    }else{
                        $recordId = null; //insert for negative
                    }
                }
                
            }
            else
            { //INSERT - id field is not defined - always insert for each line in import data
                 $recordId = null;
                 $details = array();
            }

            if(@$details['imp_id']==null){
                        $details['imp_id'] = array();
            }
            array_push( $details['imp_id'], end($row));
        
            foreach ($field_types as $index => $field_type) {
                
                if($field_type=="url"){
                    if($row[$index])
                        $details['recordURL'] = $row[$index];
                }else if($field_type=="scratchpad"){
                    if($row[$index])
                        $details['recordNotes'] = $row[$index];
                }else{
                    
                    $ft_vals = $recStruc[$recordType]['dtFields'][$field_type];
                    $value = null;        
                    if(($ft_vals[$idx_fieldtype] == "enum" || $ft_vals[$idx_fieldtype] == "relationtype") 
                            && !ctype_digit($row[$index])) {
                    
                        if($ft_vals[$idx_fieldtype] == "enum"){
                            if(!$terms_enum) { //find ALL term IDs
                                $terms_enum = mysql__select_array3($mysqli, "select trm_Label, trm_ID from defTerms where trm_Domain='enum'");
                            }
                            $value = @$terms_enum[$row[$index]];
                            
                        }else if($ft_vals[$idx_fieldtype] == "relationtype"){
                            if(!$terms_relation){ //find ALL relation IDs
                                $terms_relation = mysql__select_array3($mysqli, "select trm_Label, trm_ID from defTerms where trm_Domain='relation'");
                            }
                            $value = @$terms_relation[$row[$index]];
                        }
                        
                    }else{
                        $value = trim(preg_replace('/([\s])\1+/', ' ', $row[$index]));
                    }

                    if($value  && 
                        ($params['sa_upd']!=1 || !@$details2["t:".$field_type] ) ){
                            //Add new data only if field is empty (new data ignored for non-empty fields)
                        
                        if (!@$details["t:".$field_type] || array_search($value, $details["t:".$field_type], true)===false){
                            $cnt = count(@$details["t:".$field_type])+1;
                            $details["t:".$field_type][$cnt] = $value;
                        }else{
                            //DEBUG error_log(">>>>".$value."   ".print_r($details["t:".$field_type],true));
                        }
                    }
                }
            }//for import data
            
            if(!$id_field && count($details)>0){ //id field not defined - insert for each line
                doInsertUpdateRecord($recordId, $params, $details, $id_field_def);
            }
            
            $rep_processed++;
            
            if ($rep_processed % $step == 0) {
                if($first_time){
                    print '<script type="text/javascript">$("#div-progress").hide();</script>';
                    $first_time = false;
                }
                print '<script type="text/javascript">update_counts('.$rep_processed.','.$rep_added.','.$rep_updated.','.$tot_count.')</script>'."\n";
                ob_flush();
                flush();
            }            
   
        }//while
        $res->close();
        
        if($id_field && count($details)>0){ //action for last record
            $details = retainExisiting($details, $details2, $params);
            doInsertUpdateRecord($recordId, $params, $details, $id_field);
        }
        if(!$id_field){
            array_push($imp_session['uniqcnt'], $rep_added);    
        }
        print '<script type="text/javascript">update_counts('.$rep_processed.','.$rep_added.','.$rep_updated.','.$tot_count.')</script>'."\n";
        
    }  

    //save mapping into import_sesssion
    return saveSession($mysqli, $imp_session);
}

//
//  Add and replace all existing value(s) for the record with new data    
//  Retain existing if no new data supplied for record
//  details2 - original
//  details - new
//
function retainExisiting($details, $details2, $params){

    if($params['sa_upd']==2){

//error_log("DET2>>>".print_r($details2, true));
        
            foreach ($details2 as $field_type => $pairs) {
                if(substr($field_type,0,2)!="t:") continue;
                if( @$details[$field_type] ){ //replace field type
                    //keep bd id
                    $k = 1;
                    $details3 = array();
                    foreach ($details2[$field_type] as $bdid => $oldvalue) { //replace 1=>val to bd:id=>val
                        if(count($details[$field_type])>0){
                            $details3[$bdid] = array_shift($details[$field_type]);
                        }
                    }
                    //rest of new values
                    if(count($details[$field_type])>0){
                        $details3 = array_merge($details3, $details[$field_type]);
                    }
                    
                    $details[$field_type] = $details3;

//error_log("DET3>>>".print_r($details3, true));
                    
                }else {
                     
                    if($params['sa_upd2']==1){ //delete if no new data provided
                        $details[$field_type] = array("bd:delete"=>"ups!");
                    }else{
                        //$details[$field_type] = $details2[$field_type]; //retain
                    }
                    
                }
            }
    }
//error_log("FIN>>>".print_r($details, true));                    
    return $details;                                                
}

//
//
//
function findOriginalRecord($recordId){
    global $mysqli;
    $details = array();

    $query = "SELECT rec_URL, rec_ScratchPad FROM Records WHERE rec_ID=".$recordId;
    $row = mysql__select_array2($mysqli, $query);
    if($row){
        
        $details['recordURL'] = $row[0];
        $details['recordNotes'] = $row[1];
    
        $query = "SELECT dtl_Id, dtl_DetailTypeID, dtl_Value FROM recDetails WHERE dtl_RecID=".$recordId." ORDER BY dtl_DetailTypeID";
        $dets = mysql__select_array3($mysqli, $query, false);
        if($dets){
            foreach ($dets as $row){
                   $bd_id = $row[0];
                   $field_type = $row[1];
                   $value = $row[2];
                   if(!@$details["t:".$field_type]) $details["t:".$field_type] = array();
                   $details["t:".$field_type]["bd:".$bd_id] = $value;
            }
        }
    }
    return $details;
}

//
//
//
function doInsertUpdateRecord($recordId, $params, $details, $id_field){

    global $mysqli, $imp_session, $rep_processed, $rep_added, $rep_updated;

    $import_table = $imp_session['import_table'];
    $recordType = @$params['sa_rectype'];
    //$id_field = @$params['recid_field']; //record ID field is always defined explicitly
    
            //add-update Heurist record
            $out = saveRecord($recordId, $recordType,
                @$details["recordURL"],
                @$details["recordNotes"],
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
                print "<div style='color:red'>Error: ".implode("; ",$out["error"])."</div>";
                //return "can not insert record ".implode("; ",$out["error"]);
            }else{
                if($recordId!=$out["bibID"]){ //}==null){
                    
                    if($recordId==null || $recordId>0){
                        $updquery = "UPDATE ".$import_table
                            ." SET ".$id_field."=".$out["bibID"]
                            ." WHERE imp_id in (". implode(",",$details['imp_id']) .")";
                            
    //error_log(">>>>".$updquery);
                        if(!$mysqli->query($updquery)){
                            print "<div style='color:red'>Can not update import table (set record id ".$out["bibID"].") for lines:".implode(",",$details['imp_id'])."</div>";
                            //return "can not update import table (set record id)";
                        }
                    }
                    
                    $rep_added++;
                }else{
                    $rep_updated++;
                }
                
                if (@$out['warning']) {
                    print "<div style=\"color:#ff8844\">Warning: ".implode("; ",$out["warning"])."</div>";
                }

            }
            

    
}


/**
* update record in import session table
* 
* @param mixed $mysqli
* @param mixed $imp_session
*/
function saveSession($mysqli, $imp_session){
    
    $imp_id = mysql__insertupdate($mysqli, "import_sessions", "imp", 
            array("imp_ID"=>@$imp_session["import_id"], 
                  "ugr_id"=>get_user_id(), 
                  "imp_table"=>$imp_session["import_name"],
                  "imp_session"=>json_encode($imp_session) ));    
                  
    if(intval($imp_id)<1){
        return "can not save session";
    }else{
        $imp_session["import_id"] = $imp_id;
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
* Drop import table and empty session table
* 
* @param mixed $session_id
*/
function clear_import_session($session_id){
    global $mysqli;
    
    $ret = "";
    $where = "";
    if(is_numeric($session_id)){
        $where = " where imp_id=".$session_id;
    }
    
    $res = mysql__select_array3($mysqli, 
                "select imp_id, imp_session from import_sessions".$where);
    
    if(!$res){
        $ret = "can not get list of sessions";
    }else{
    
        foreach($res as $id => $session){

            $session = json_decode($session, true);
            $query = "drop table IF EXISTS ".$session['import_table'];
            
            if (!$mysqli->query($query)) {
                $ret = "can not drop table: " . $mysqli->error;
                break;
            }    
        }
        
        if($ret==""){
            if($where==""){
                $where = " where imp_id>0";
            }
            
            if (!$mysqli->query("delete from import_sessions ".$where)) {
                $ret = "can not delete data from import session table: " . $mysqli->error;
            }else{
                $ret = "ok";
            }
        }
    }

    header('Content-type: text/javascript');
    print json_encode($ret);
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
                "select imp_session from import_sessions where imp_id=".$import_id);
        
        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        //$session["import_table"] = $res[1];
    
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
// print list of load data warnings
//
function renderWarnings($imp_session){
    
    $warnings = $imp_session['load_warnings'];
    foreach ($warnings as $line) {
        print $line."<br />";
    }
    print '<br /><br /><input type="button" value="Back" onClick="showRecords(\'mapping\');">';
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
        print '<table class="tbmain"  cellspacing="0" cellpadding="2"><thead><tr>'; // class="tbmain"
        if($type=="error"){
            $checked_fields = $imp_session['validation']['field_checked'];
        }else{
            $checked_fields = array(); 
        }

        if($type=="update"){
            print '<th>Record ID</th>';
        }
            print '<th>Line #</th>';
            
            foreach($mapped_fields as $field_name) {
                
                $colname = @$imp_session['columns'][substr($field_name,6)];
                if(array_search($field_name, $checked_fields)!==false){
                    $colname = "<i>".$colname."*</i>";
                }
                
                print "<th>".$colname."</th>";
            }
            
            print "</tr></thead>";
            foreach ($records as $row) {  

                print "<tr>";
                if(is_array($row)){
                    foreach($row as $value) {     
                        print "<td class='truncate'>".($value?$value:"&nbsp;")."</td>";
                    }
                }
                print "</tr>";
            }
        print "</table>";
        
        print '<br /><br /><input type="button" value="Back" onClick="showRecords(\'mapping\');">';
    
}

/*
delimiter $$

CREATE DEFINER=`root`@`localhost` FUNCTION `trim_spaces`(`dirty_string` text, `trimChar` varchar(1))
    RETURNS text
    LANGUAGE SQL
    NOT DETERMINISTIC
    CONTAINS SQL
    SQL SECURITY DEFINER
    COMMENT ''
BEGIN
  declare cnt,len int(11) ;
  declare clean_string text;
  declare chr,lst varchar(1);

  set len=length(dirty_string);
  set cnt=1;  
  set clean_string='';

 while cnt <= len do
      set  chr=right(left(dirty_string,cnt),1);           

      if  chr <> trimChar OR (chr=trimChar AND lst <> trimChar ) then  
          set  clean_string =concat(clean_string,chr);
      set  lst=chr;     
     end if;

     set cnt=cnt+1;  
  end while;

  return clean_string;
END
$$
delimiter ;
*/
?>
