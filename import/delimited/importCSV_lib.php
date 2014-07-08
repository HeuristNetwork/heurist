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

// global variable for progress report
$rep_processed = 0;
$rep_added = 0;
$rep_updated = 0;


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
                        $result[$row[0]] = stripAccents(trim($row[1]));
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
    $imp_session['validation'] = array( "count_update"=>0, "count_insert"=>0, "count_error"=>0, "error"=>array());  
    $import_table = $imp_session['import_table'];
    
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
/*  EXAMPLE for update 
    
use hdb_BoRO_experiments;
SELECT imp_id, field_1,field_2, d1.dtl_RecID, rec_ID, rec_RecTypeID
FROM import20140428033030
left join recDetails d1 on d1.dtl_DetailTypeID=1 and d1.dtl_Value=field_1
left join Records on d1.dtl_RecID = rec_ID
where rec_ID is null || 
(rec_RecTypeID!=10 and imp_id not in 
(SELECT  imp_id
FROM import20140428033030, recDetails d1, recDetails d2, Records
where d1.dtl_DetailTypeID=1 and d1.dtl_Value = field_1 
and d2.dtl_DetailTypeID=18 and d2.dtl_Value = field_2
and rec_RecTypeID=10 and rec_ID=d1.dtl_RecID and rec_ID=d2.dtl_RecID))
    
*/    
    
    //get fields that will be used in search
    $sel_query = array();
    $mapped_fields = array();
    
    //for insert
    //NOT USED $select_query_join_rec = array();  " left join Records on "; //"rec_RecTypeID!=".$recordType;
    //NOT USED $select_query_join_det = "";
    
    //for update
    $select_query_update_from = array($import_table, "Records");
    $select_query_update_where = array("rec_RecTypeID=".$recordType);
    
    $detDefs = getAllDetailTypeStructures(true);
    $detDefs = $detDefs['typedefs'];
    $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
    
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_keyfield_")===0 && $field_type){
            //get index 
            $index = substr($key,12);
            $field_name = "field_".$index;
        
            array_push($sel_query, $field_name);
            $mapped_fields[$field_name] = $field_type; 
            
            if($field_type=="id" || $field_type=="url" || $field_type=="scratchpad"){
                
                //array_push($select_query_join_rec, "rec_".$field_type."=".$field_name);
                array_push($select_query_update_where, "rec_".$field_type."=".$field_name);
            }else{
                
                $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];
                
                $where = "d".$index.".dtl_DetailTypeID=".$field_type." and ";
                
                if( $dt_type == "enum" ||  $dt_type == "relationtype") {
                
                    //if fieldname is numeric - compare it with dtl_Value directly
                    $where = $where." d".$index.".dtl_Value=t".$index.".trm_ID and "
     ." if(concat('',$field_name * 1) = $field_name,d".$index.".dtl_Value=$field_name,t".$index.".trm_Label=$field_name) ";
            
                    array_push($select_query_update_from, "defTerms t".$index); 
            
                /*}else if( $dt_type == "resource" ||  $dt_type == "integer" ||  $dt_type == "year" ||  $dt_type == "float") {
                    
                    $where = $where.
                    " TRIM(d".$index.".dtl_Value)=TRIM(".$field_name.")";
                */      
                }else if(false && $dt_type == "freetext"){
                
                    $where = $where." (REPLACE(REPLACE(TRIM(d".$index.".dtl_Value),'  ',' '),'  ',' ')=".$field_name.")";
                }else{
                    $where = $where." (d".$index.".dtl_Value=".$field_name.")";
                    //" REPLACE(REPLACE(TRIM(d".$index.".dtl_Value),'  ',' '),'  ',' ')=REPLACE(REPLACE(TRIM(".$field_name."),'  ',' '),'  ',' ')";
                 
                }
                
                //array_push($select_query_join_rec, "rec_ID=d".$index.".dtl_RecID");
                //$select_query_join_det = $select_query_join_det . " left join recDetails d".$index." on ".$where;
                
                array_push($select_query_update_where, "rec_ID=d".$index.".dtl_RecID and ".$where);
                array_push($select_query_update_from, "recDetails d".$index); 
            }
            
        }            
    }  
    if(count($sel_query)<1){
        return "no one key field is selected";
    }

    $imp_session['validation']['mapped_fields'] = $mapped_fields;        
    
        
    //query to search record ids  FOR UPDATE               
    $select_query = "SELECT SQL_CALC_FOUND_ROWS rec_ID, group_concat(imp_id), ".implode(",",$sel_query)
                    ." FROM ".implode(",",$select_query_update_from)
                    ." WHERE ".implode(" and ",$select_query_update_where)
                    ." GROUP BY rec_ID, ".implode(",",$sel_query);

//DEBUG error_log(">>>".$select_query);
    //array_push($sel_query, "rec_ID");
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
                if($cnt>4999) break;
            }
        }
    }else{
        return "Can not execute query to calculate number of records to be updated ".$mysqli->error;
    }

    //FIND RECORDS FOR INSERT
    $select_query = "SELECT SQL_CALC_FOUND_ROWS group_concat(imp_id), ".implode(",",$sel_query)
                    ." FROM ".$import_table
                    . " WHERE (imp_id NOT IN "
                    ." (SELECT imp_id "
                    ." FROM ".implode(",",$select_query_update_from)
                    ." WHERE ".implode(" and ",$select_query_update_where)
                    .")) GROUP BY ".implode(",",$sel_query);
                    
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
                if($cnt>4999) break;
            }
        }
    }else{
        return "Can not execute query to calculate number of records to be inserted";
    }
    
    
    return $imp_session; 
}

//=================================================================
/**
* Assign record ids to field in import table
* 
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function matchingAssign($mysqli, $imp_session, $params){
    
    //Now we always use it
    if(true || @$params['mvm']>0){
        return assignMultivalues($mysqli, $imp_session, $params);
    }
    
    $import_table = $imp_session['import_table'];
    
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    
    if(intval($recordType)<1){
        return "record type not defined";
    }

    $id_field = @$params['idfield'];
    $field_count = count($imp_session['columns']);
    
    
    if(!$id_field){ //add new field into import table
            //ID field not defined, create new field
            $id_field_idx = $field_count;
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
    }else{
            $id_field_idx = substr($id_dield,6);
    }
    
    if(@$imp_session['indexes_keyfields'] && @$imp_session['indexes_keyfields'][$id_field]){
//DEBUG print "UNSET indexes_keyfields<br>";
        
        unset($imp_session['indexes_keyfields'][$id_field]);        
    }
    //remove from multivals
    $k = array_search($id_field_idx, $imp_session['multivals']);
    if($k!==false){
        unset($imp_session['multivals'][$k]);
    }

    
    //get fields that will be used in search
    $sel_query = array();
    //for update
    $select_query_update_from = array($import_table, "Records");
    $select_query_update_where = array("rec_RecTypeID=".$recordType);
    
    $detDefs = getAllDetailTypeStructures(true);
    $detDefs = $detDefs['typedefs'];
    $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
    
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_keyfield_")===0 && $field_type){
            //get index 
            $index = substr($key,12);
            $field_name = "field_".$index;
        
            array_push($sel_query, $field_name); 
            
            if($field_type=="id" || $field_type=="url" || $field_type=="scratchpad"){
                array_push($select_query_update_where, "rec_".$field_type."=".$field_name);
            }else{
                
                $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];
                
                $where = "d".$index.".dtl_DetailTypeID=".$field_type." and ";
                
                if( $dt_type == "enum" ||  $dt_type == "relationtype") {
                
                    //if fieldname is numeric - compare it with dtl_Value directly
                    $where = $where." d".$index.".dtl_Value=t".$index.".trm_ID and "
     ." if(concat('',$field_name * 1) = $field_name,d".$index.".dtl_Value=$field_name,t".$index.".trm_Label=$field_name) ";
            
                    array_push($select_query_update_from, "defTerms t".$index); 
            
                /*}else if( $dt_type == "resource" ||  $dt_type == "integer" ||  $dt_type == "year" ||  $dt_type == "float") {
                    
                    $where = $where.
                    " TRIM(d".$index.".dtl_Value)=TRIM(".$field_name.")";
                */       
                }else{
                
                    $where = $where." (d".$index.".dtl_Value=".$field_name.")";
                    //" REPLACE(REPLACE(TRIM(d".$index.".dtl_Value),'  ',' '),'  ',' ')=REPLACE(REPLACE(TRIM(".$field_name."),'  ',' '),'  ',' ')";
                 
                }
                
                array_push($select_query_update_where, "rec_ID=d".$index.".dtl_RecID and ".$where);
                array_push($select_query_update_from, "recDetails d".$index); 
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
    $updquery = "UPDATE ".implode(",",$select_query_update_from)." SET ".$id_field."=rec_ID WHERE "
                         .implode(" and ",$select_query_update_where)." and imp_id>0";
    
//DEBUG error_log("matched records 1>>>>".$updquery);
    if(!$mysqli->query($updquery)){
        return "can not update import table (set record id field)";
    }
    
    //new records   ".implode(",",$sel_query).",
    $mysqli->query("SET SESSION group_concat_max_len = 1000000");
    /*OLD $select_query = "SELECT group_concat(imp_id), ".implode(",",$sel_query)." FROM ".$import_table
                    . $select_query_join_det.$select_query_join_rec
                    . " WHERE ".implode(" and ",$select_query_join_det_where)  //"$id_field is NULL"
                    . " GROUP BY ". implode(",",$sel_query);*/

    //FIND RECORDS FOR INSERT
    $select_query = "SELECT group_concat(imp_id), ".implode(",",$sel_query)
                    ." FROM ".$import_table
                    . " WHERE (imp_id NOT IN "
                    ." (SELECT imp_id "
                    ." FROM ".implode(",",$select_query_update_from)
                    ." WHERE ".implode(" and ",$select_query_update_where)
                    .")) GROUP BY ".implode(",",$sel_query);
                    
//DEBUG error_log("records to insert 2>>>>".$select_query);
                    
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
//DEBUG error_log("3>>>>".$updquery);            
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

//====================================================================
/**
* special case - multivalue index field
* 
* @param mixed $mysqli               
* @param mixed $imp_session
* @param mixed $params
*/
function matchingMultivalues($mysqli, $imp_session, $params){

//DEBUG print "SESSION:".print_r($imp_session, true);
    $imp_session['validation'] = array( "count_update"=>0, "count_insert"=>0, "count_error"=>0, "error"=>array(), 
                "recs_insert"=>array(), "recs_update"=>array() );  
    
    $import_table = $imp_session['import_table'];
    $multivalue_field_name = $params['multifield']; //name of multivalue field
    $multivalue_field_name_idx = 0;
    
    //disambiguation resolution 
    $disamb_ids = @$params['disamb_id'];   //record ids
    $disamb_keys = @$params['disamb_key'];  //key values
    $disamb_resolv = array();
    if($disamb_keys){
        foreach($disamb_keys as $idx => $keyvalue){
            $disamb_resolv[$keyvalue] = $disamb_ids[$idx];
        }
        
        print "<br>dis resolv ".print_r($disamb_resolv, true);
    }
    
    
    
    
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    
    if(intval($recordType)<1){
        return "record type not defined";
    }

//get search query
/*  OLD  WORK
    $select_query_update_from = array("Records");
    $select_query_update_where = array("rec_RecTypeID=".$recordType);
    
    $field_type = $params['sa_keyfield_type'];
    $field_name = $params['sa_keyfield'];
        
    if($field_type=="url"){ // || $field_type=="id" || $field_type=="scratchpad"){
        array_push($select_query_update_where, "rec_".$field_type."=?");
    }else{
        
        $where = "d".$index.".dtl_DetailTypeID=".$field_type." and (d".$index.".dtl_Value=?)";
        
        array_push($select_query_update_where, "rec_ID=d".$index.".dtl_RecID and ".$where);
        array_push($select_query_update_from, "recDetails d".$index); 
    }
   
//query to search record ids
    $search_query = "SELECT rec_ID "
                    ." FROM ".implode(",",$select_query_update_from)
                    ." WHERE ".implode(" and ",$select_query_update_where);
*/
                    
    //for update
    $select_query_update_from = array("Records");
    $select_query_update_where = array("rec_RecTypeID=".$recordType);
    $sel_fields = array();
    
    $detDefs = getAllDetailTypeStructures(true);
    $detDefs = $detDefs['typedefs'];
    $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
    $mapped_fields = array();
    
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_keyfield_")===0 && $field_type){
            //get index 
            $index = substr($key,12);
            $field_name = "field_".$index;
        
            $mapped_fields[$field_name] = $field_type;
        
            if($field_type=="url"){  //$field_type=="id" || $field_type=="scratchpad"){
                array_push($select_query_update_where, "rec_".$field_type."=?");
                
            }else if(is_numeric($field_type)){
                
                $where = "d".$index.".dtl_DetailTypeID=".$field_type." and ";
                
                $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];
                
                if( $dt_type == "enum" ||  $dt_type == "relationtype") {
                
                    //if fieldname is numeric - compare it with dtl_Value directly
                    $where = $where."( d".$index.".dtl_Value=t".$index.".trm_ID and t".$index.".trm_Label=?)";
     //." if(concat('',? * 1) = ?,d".$index.".dtl_Value=?,t".$index.".trm_Label=?) ";
            
                    array_push($select_query_update_from, "defTerms t".$index); 
                }else{
                    $where = $where." (d".$index.".dtl_Value=?)";
                }
                array_push($select_query_update_where, "rec_ID=d".$index.".dtl_RecID and ".$where);
                array_push($select_query_update_from, "recDetails d".$index); 
            }else{
                continue;
            }
            
            array_push($sel_fields, $field_name);
            if($multivalue_field_name==$field_name){
                 $multivalue_field_name_idx = count($sel_fields);
            }
        }            
    } 
    
    $imp_session['validation']['mapped_fields'] = $mapped_fields;        

    
//query to search record ids 
    $search_query = "SELECT rec_ID, rec_Title "
                    ." FROM ".implode(",",$select_query_update_from)
                    ." WHERE ".implode(" and ",$select_query_update_where);
                    
//DEBUG print ">>>>".$search_query;
    $search_stmt = $mysqli->prepare($search_query);

    $params_dt = str_repeat('s',count($sel_fields));
    //$search_stmt->bind_param('s', $field_value);
    $search_stmt->bind_result($rec_ID, $rec_Title);
    
//already founded IDs    
    $pairs = array();
    $records = array();
    $disambiguation = array();
    $tmp_idx_insert = array(); //to keep indexes
    $tmp_idx_update = array(); //to keep indexes
    
    
//loop all records
    $select_query = "SELECT imp_id, ".implode(",", $sel_fields)." FROM ".$import_table;
    $res = $mysqli->query($select_query);
    if($res){
        $ind = -1;
        while ($row = $res->fetch_row()){
            $imp_id = $row[0];
            $row[0] = $params_dt;
            
            $multivalue = $row[$multivalue_field_name_idx];    
            $multivalue = $row[$multivalue_field_name_idx];    
            
            $ids = array();
            //split multivalue field
            $values = getMultiValues($multivalue, $params['csv_enclosure']);
            foreach($values as $idx=>$value){
                $row[$multivalue_field_name_idx] = $value;
                //verify that not empty
                $fc = $row;
                array_shift($fc);
                $fc = trim(implode("", $fc));

                if($fc==null || $fc=="") continue;       //key is empty
                
                
                $keyvalue = implode("|", $row);

                if(!@$pairs[$keyvalue]){  //was $value && $value!="" && 
//search for ID 

                    call_user_func_array(array($search_stmt, 'bind_param'), refValues($row));
                    $search_stmt->execute();
                    $disamb = array();
                    while ($search_stmt->fetch()) {
                        //keep pair ID => key value
                        $disamb[$rec_ID] = $rec_Title; //get value from binding
                    }
                    
                    if(count($disamb)==0){
                          $new_id = $ind;
                          $ind--;
                          $rec = $row;
                          $rec[0] = $imp_id;
                          $tmp_idx_insert[$keyvalue] = count($imp_session['validation']['recs_insert']); //keep index in rec_insert
                          array_push($imp_session['validation']['recs_insert'], $rec); //group_concat(imp_id), ".implode(",",$sel_query) 
//DEBUG print "<br>push :".print_r($imp_session['validation']['recs_insert'], true);                            
                    }else if(count($disamb)==1 || @$disamb_resolv[addslashes($keyvalue)]){
                                
                          if(count($disamb)>1){
//DEBUG print "<br> ".$keyvalue."   ".@$disamb_resolv[addslashes($keyvalue)].".";
                            $rec_ID = $disamb_resolv[addslashes($keyvalue)];
                          }

                          $new_id = $rec_ID;
                          $rec = $row;
                          $rec[0] = $imp_id;
                          array_unshift($rec, $rec_ID);
                          $tmp_idx_update[$keyvalue] = count($imp_session['validation']['recs_update']); //keep index in rec_update
                          array_push($imp_session['validation']['recs_update'], $rec); //rec_ID, group_concat(imp_id), ".implode(",",$sel_query) 
                    }else{
                          $new_id= 'Found:'.count($disamb); //Disambiguation!
                          $disambiguation[$keyvalue] = $disamb;
                    }
                    $pairs[$keyvalue] = $new_id;
                    array_push($ids, $new_id);
                }else{ //already found
                
                    if(array_key_exists($keyvalue, $tmp_idx_insert)){
                        $imp_session['validation']['recs_insert'][$tmp_idx_insert[$keyvalue]][0] .= (",".$imp_id);
                        
//DEBUG print "<br> added key=".$keyvalue."   idx=".$tmp_idx_insert[$keyvalue]."  line=".$imp_id;                        
                    }else if(array_key_exists($keyvalue, $tmp_idx_update)) {
                        $imp_session['validation']['recs_update'][$tmp_idx_update[$keyvalue]][1] .= (",".$imp_id);
                    }
                    //$imp_session['validation']['recs_update'][]
                    array_push($ids, $pairs[$keyvalue]);
                }
            }//foreach multivalues
            $records[$imp_id] = implode("|", $ids);   //IDS to be added to import table
        }//while import table
    }
    
//DEBUG print "<br>".print_r($imp_session['validation']['recs_insert'], true);
    
    $search_stmt->close();         
                    
/*   OLD  WORK                 
    $search_stmt = $mysqli->prepare($search_query);
    $search_stmt->bind_param('s', $field_value);
    $search_stmt->bind_result($rec_ID);
    
//already founded IDs    
    $pairs = array();
    $records = array();
    
//loop all records
    $select_query = "SELECT imp_id, ".$field_name." FROM ".$import_table;
    $res = $mysqli->query($select_query);
    if($res){
        $ind = -1;
        while ($row = $res->fetch_row()){
//split multivalue field
            $ids = array();
            $values = getMultiValues($row[1], $params['csv_enclosure']);
            foreach($values as $idx=>$value){
                if($value && $value!="" && !@$pairs[$value]){
//search for ID 
                    $field_value = $value; //assign parametrized value
                    $search_stmt->execute();
                    $fnd = 0;
                    while ($search_stmt->fetch()) {
                        //keep pair ID => key value
                        $pairs[$value] = $rec_ID;
                        $fnd++;
                    }
                    if($fnd==0){
                          $pairs[$value] = $ind;
                          $ind--;
                    }else if($fnd>1){
                          $pairs[$value] = 'Found:'.$fnd; //Disambiguation!
                    }
                }
                array_push($ids, $pairs[$value]);
            }
            $records[$row[0]] = implode("|", $ids);   //IDS to be added to import table
        }//while import table
    }
    $search_stmt->close();
*/

    
    $imp_session['validation']['count_update'] = count($imp_session['validation']['recs_update']);
    $imp_session['validation']['count_insert'] = count($imp_session['validation']['recs_insert']);
    $imp_session['validation']['disambiguation'] = $disambiguation;
    $imp_session['validation']['pairs'] = $pairs;     //keyvalues => record id - count number of unique values
    $imp_session['validation']['records'] = $records; //imp_id(line#) => list of records ids

    return $imp_session;
}

/**
* Assign multivalues IDs 
* (negative if not found)
* 
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
* @return mixed
*/
function assignMultivalues($mysqli, $imp_session, $params){

    $imp_session = matchingMultivalues($mysqli, $imp_session, $params); 
    if(is_array($imp_session)){
       $records = $imp_session['validation']['records']; //imp_id(line#) => list of records ids
       $pairs = $imp_session['validation']['pairs'];     //keyvalues => record id - count number of unique values
       $disambiguation = $imp_session['validation']['disambiguation'];
    }else{
        return $imp_session;
    }
    
    if(count($disambiguation)>0){
        return $imp_session; //"It is not possible to proceed because of disambiguation";
    }
    
    $is_create_records = false;
    $import_table = $imp_session['import_table'];
    
    //get rectype to import
    $recordType = @$params['sa_rectype'];
    
    if(intval($recordType)<1){
        return "record type not defined";
    }

    $id_field = @$params['idfield'];
    
    $field_count = count($imp_session['columns']);
    
    if(!$id_field || $id_field=="null"){ 
//add new field into import table
            //ID field not defined, create new field
            $id_field = "field_".$field_count;
            array_push($imp_session['columns'], @$params['new_idfield']?$params['new_idfield'] : "Record type #$recordType index" );
            array_push($imp_session['uniqcnt'], count($pairs));
            array_push($imp_session['multivals'], $field_count);
            
            $imp_session['indexes'][$id_field] = $recordType;
        
            $altquery = "alter table ".$import_table." add column ".$id_field." varchar(255) ";
            if (!$mysqli->query($altquery)) {
                return "can not alter import session table, can not add new index field: " . $mysqli->error;
            }    
    }else{
            $index = intval(substr($id_field, 6));
            $imp_session['uniqcnt'][$index] = count($pairs);    
    }    
    
    //get field type 
    $field_type = $params['sa_keyfield_type'];
    
    if(!@$imp_session['indexes_keyfields']){
        $imp_session['indexes_keyfields'] = array();
    }
    $imp_session['indexes_keyfields'][$id_field] = $imp_session['validation']['mapped_fields'];        
    
//DEBUG print "<br>mapped_fields  ".$id_field."   ".print_r($imp_session['validation']['mapped_fields'],true); 
//DEBUG print "<br>indexes_keyfields ".print_r($imp_session['indexes_keyfields'],true);   //DEBUG
    
//add NEW records    
    $rep_processed=0;
    $rep_added   = 0;
    $rep_updated = 0;
/* OLD WORK
    $newrecs = array();
    $details= array( "t:".$field_type => array() );
    foreach($pairs as $value => $rec_ID){
        if($rec_ID<0){
           $details["t:".$field_type][0] = $value;
           $newrecs[$rec_ID] = doInsertUpdateRecord(null, $params, $details, null);
        }
    }
*/
    //NOT USED ANYMORE
    if($is_create_records){
    
        $newrecs = array();
        $details = array();

        $detDefs = getAllDetailTypeStructures(true);
        $detDefs = $detDefs['typedefs'];
        $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
        $terms_enum = null;
        $terms_relation = null;
        
    //create new records
        foreach($pairs as $value => $rec_ID){
            
            if($rec_ID<0){
                $values = explode("|", $value);
                
                $k=1;
                foreach ($params as $key => $field_type) {
                    if(strpos($key, "sa_keyfield_")===0 && is_numeric($field_type)){
                        $details["t:".$field_type] = array();
                        $r_value = $values[$k];

                        $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];
                        if(( $dt_type == "enum" ||  $dt_type == "relationtype")
                            && !ctype_digit($r_value)) {
                                        
                                $r_value = stripAccents(mb_strtolower($r_value)); 
                            
                                if($dt_type == "enum"){
                                    if(!$terms_enum) { //find ALL term IDs
                                        $terms_enum = mysql__select_array3($mysqli, "select LOWER(trm_Label), trm_ID  from defTerms where trm_Domain='enum' order by trm_Label");
                                    }
                                    $r_value = @$terms_enum[$r_value];
                                    
                                }else if($ft_vals[$idx_fieldtype] == "relationtype"){
                                    if(!$terms_relation){ //find ALL relation IDs
                                        $terms_relation = mysql__select_array3($mysqli, "select LOWER(trm_Label), trm_ID from defTerms where trm_Domain='relation'");
                                    }
                                    $r_value = @$terms_relation[$r_value];
                                }
                                
                        }
                        
                        $details["t:".$field_type][0] = $r_value;
                        $k++;
                    }
                }
                $newrecs[$rec_ID] = doInsertUpdateRecord(null, $params, $details, null);
            }
        }
    }

//update values in import table - replace negative to new one
    foreach($records as $imp_id => $ids){
        
        if($is_create_records){
            $ids = explode("|",$ids);
            $newids = array();
            foreach($ids as $id){
                if($id<0) $id = $newrecs[$id];
                if($id)
                    array_push($newids,$id);
            }
            $newids = implode('|',$newids);
        }else{
            $newids = $ids;
        }
        
        if($ids){
            //update
            $updquery = "update ".$import_table." set ".$id_field."='".$ids
                ."' where imp_id = ".$imp_id;
//DEBUG print ("3>>>>".$updquery);            
            if(!$mysqli->query($updquery)){
                return "can not update import table: set ids for multivalue";
            }
        }
    }
    
    $ret_session = $imp_session;
    unset($imp_session['validation']);
    saveSession($mysqli, $imp_session);
    return $ret_session;
}

/**
* Split multivalue field
* 
* @param array $values
* @param mixed $csv_enclosure
*/
function getMultiValues($values, $csv_enclosure){

        $nv = array();
        $values =  explode("|", $values);                          
        if(count($values)==1){
           array_push($nv, trim($values[0])); 
        }else{

            $csv_enclosure = ($csv_enclosure==1)?"'":'"'; //need to remove quotes for multivalues fields

            foreach($values as $idx=>$value){
                if($value!=""){
                if(strpos($value,$csv_enclosure)===0 && strrpos($value,$csv_enclosure)===strlen($value)-1){
                     $value = substr($value,1,strlen($value)-2);
                }
                    array_push($nv, $value);
                }
            }
        }
        return $nv;
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
    $imp_session['validation'] = array( "count_update"=>0, "count_insert"=>0, "count_error"=>0, "error"=>array() );  
    
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
    $mapped_fields = array(); 
    $sel_query = array();
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_dt_")===0 && $field_type){
            //get index 
            $index = substr($key,6);
            $field_name = "field_".$index;
            
            //all mapped fields - they will be used in validation query
            array_push($sel_query, $field_name); 
            $mapping[$field_type] = $field_name;
            
            $mapped_fields[$field_name] = $field_type; 
        }
    }  
    if(count($sel_query)<1){
        return "mapping not defined";
    }
    
    $imp_session['validation']['mapped_fields'] = $mapped_fields;
    
    if(!$id_field){ //ID field not defined - all records will be inserted
        $imp_session['validation']["count_insert"] = $imp_session['reccount'];
        $select_query = "SELECT imp_id, ".implode(",",$sel_query)." FROM ".$import_table." LIMIT 5000";
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
             $wrong_records = getWrongRecords($mysqli, $query, "Your input data contains record IDs in the selected ID column for existing records which are of a different type from that specified. The import cannot proceed until this is corrected.", $imp_session, $id_field);
             if(is_array($wrong_records) && count($wrong_records)>0) {
                $wrong_records['validation']['mapped_fields'][$id_field] = 'id';
//error_log(print_r($imp_session['validation']['mapped_fields'],true));
                $imp_session = $wrong_records;   
             }else if($wrong_records) {
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
           ." ORDER BY ".$id_field." LIMIT 5000";
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
                $select_query = "SELECT imp_id, ".implode(",",$sel_query)." FROM ".$import_table." WHERE ".$id_field." is null OR ".$id_field."<0 LIMIT 5000";
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
                ." WHERE ".$id_field.">0 and rec_ID is null LIMIT 5000";
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
 $query_reqs = array(); //fieldnames from import table
 $query_reqs_where = array(); //where clause for validation

 $query_enum = array();
 $query_enum_join = array();
 $query_enum_where = array();

 $query_res = array();
 $query_res_join = array();
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
            array_push($query_enum_join, 
     " defTerms $trm1 on if(concat('',$field_name * 1) = $field_name,$trm1.trm_ID=$field_name,$trm1.trm_Label=$field_name) ");
            array_push($query_enum_where, "(".$trm1.".trm_Label is null and not ($field_name is null or $field_name=''))");
            
        }else if($ft_vals[$idx_fieldtype] == "resource"){
            array_push($query_res, $field_name);
            $trm1 = "rec".count($query_res);
            array_push($query_res_join, " Records $trm1 on $trm1.rec_ID=$field_name ");
            array_push($query_res_where, "(".$trm1.".rec_ID is null and not ($field_name is null or $field_name=''))");
            
        }else if($ft_vals[$idx_fieldtype] == "float" ||  $ft_vals[$idx_fieldtype] == "integer") {
            
            array_push($query_num, $field_name);
            array_push($query_num_where, "(concat('',$field_name * 1) != $field_name  and not ($field_name is null or $field_name=''))");

        }else if($ft_vals[$idx_fieldtype] == "date" ||  $ft_vals[$idx_fieldtype] == "year") {

            array_push($query_date, $field_name);
            if($ft_vals[$idx_fieldtype] == "year"){
                array_push($query_date_where, "(concat('',$field_name * 1) != $field_name "
                ."and not ($field_name is null or $field_name=''))");
            }else{
                array_push($query_date_where, "(str_to_date($field_name, '%Y-%m-%d %H:%i:%s') is null "
                ."and str_to_date($field_name, '%d/%m/%Y') is null "
                ."and str_to_date($field_name, '%d-%m-%Y') is null "
                ."and not ($field_name is null or $field_name=''))");
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
 $k=0;
 foreach ($query_reqs as $field){
     $query = "select imp_id, ".implode(",",$sel_query)
        ." from $import_table "
        ." where ".$query_reqs_where[$k]; // implode(" or ",$query_reqs_where);
     $k++;  
//error_log("check empty: ".$query);
     $wrong_records = getWrongRecords($mysqli, $query, "This field is required - a value must be supplied for every record", $imp_session, $field);
     if(is_array($wrong_records)) {
        $imp_session = $wrong_records;   
     }else if($wrong_records) {
         return $wrong_records;
     }
 } 
 //3. In DB: Verify that enumeration fields have correct values =====================================
 /*
 "select $field_name from $import_table "
 ."left join defTerms trm1 on if(concat('',$field_name * 1) = $field_name,trm1.trm_ID=$field_name,trm1.trm_Label=$field_name) "
 ." where trm1.trm_Label is null";
 */
 $hwv = " have wrong values";
 $k=0;
 foreach ($query_enum as $field){
 
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table left join ".$query_enum_join[$k]   //implode(" left join ", $query_enum_join)
                ." where ".$query_enum_where[$k];  //implode(" or ",$query_enum_where);
//DEBUG  error_log("check enum: ".$query);                
     $k++;
     
     //"Fields mapped as enumeration ".$hwv           
     $wrong_records = getWrongRecords($mysqli, $query, "Term list values must match terms defined for the field",
                    $imp_session, $field);
     //if($wrong_records) return $wrong_records;
     if(is_array($wrong_records)) {
        $imp_session = $wrong_records;   
     }else if($wrong_records) {
         return $wrong_records;
     }     
 }
 //4. In DB: Verify resource fields
 $k=0;
 foreach ($query_res as $field){
     
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table left join ".$query_res_join[$k]  //implode(" left join ", $query_res_join)
                ." where ".$query_res_where[$k]; //implode(" or ",$query_res_where);
     $k++;
     $wrong_records = getWrongRecords($mysqli, $query, 
                                "Record pointer fields must reference an existing record in the database",
                                $imp_session, $field);
     //"Fields mapped as resources(pointers)".$hwv,                                 
     //if($wrong_records) return $wrong_records;
     if(is_array($wrong_records)) {
        $imp_session = $wrong_records;   
     }else if($wrong_records) {
         return $wrong_records;
     }     
 }
 
 //5. Verify numeric fields
 $k=0;
 foreach ($query_num as $field){
 
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table "
                ." where ".$query_num_where[$k]; //implode(" or ",$query_num_where);
                $k++;
     $wrong_records = getWrongRecords($mysqli, $query, 
        "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation",
        $imp_session, $field);
     // "Fields mapped as numeric".$hwv,    
     //if($wrong_records) return $wrong_records;
     if(is_array($wrong_records)) {
        $imp_session = $wrong_records;   
     }else if($wrong_records) {
         return $wrong_records;
     }     
 }
 
 //6. Verify datetime fields
 $k=0;
 foreach ($query_date as $field){
 
     $query = "select imp_id, ".implode(",",$sel_query)
                ." from $import_table "
                ." where ".$query_date_where[$k]; //implode(" or ",$query_date_where);
                $k++;
     $wrong_records = getWrongRecords($mysqli, $query, 
        "Date values must be in dd-mm-yyyy, dd/mm/yyyy or yyyy-mm-dd formats",
        $imp_session, $field);
     //"Fields mapped as date".$hwv,    
     //if($wrong_records) return $wrong_records;
     if(is_array($wrong_records)) {
        $imp_session = $wrong_records;   
     }else if($wrong_records) {
         return $wrong_records;
     }     
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
    
    $res = $mysqli->query($query." LIMIT 5000");
    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){
            array_push($wrong_records, $row);
        }        
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            
            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);
            
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
    
    global $rep_processed,$rep_added,$rep_updated;

    
    //rectype to import
    $import_table = $imp_session['import_table'];
    $recordType = @$params['sa_rectype'];
    $id_field = @$params['recid_field']; //record ID field is always defined explicitly
                                                             
    //$is_mulivalue_index = $id_field && in_array(intval(substr($id_field,6)), $imp_session['multivals']);
    
    if($id_field && @$imp_session['indexes_keyfields'][$id_field]){
        $is_mulivalue_index = true;    
    }else{
        $is_mulivalue_index = false;
    }
    
//DEBUG print "<br>IS MULTI ".$is_mulivalue_index."<<  ".print_r(@$imp_session['indexes_keyfields'][$id_field], true);
    
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
            //TEMP ART $imp_session["mapping"][$field_name] = $recordType.".".$field_type; // to keep used
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
            //create id field by default
            $field_count = count($imp_session['columns']);
            $id_field_def = "field_".$field_count;
            array_push($imp_session['columns'], "ID field for Record type #$recordType" );
            
            
            $imp_session['indexes'][$id_field_def] = $recordType;
        
            $altquery = "alter table ".$import_table." add column ".$id_field_def." int(10) ";
            if (!$mysqli->query($altquery)) {
                return "can not alter import session table, can not add new index field: " . $mysqli->error;
            }    
    }
        
    
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
        
        $new_record_ids = array();
        $imp_id = null;
        
        $pairs = array(); // for multivalue rec_id => new_rec_id

        while ($row = $res->fetch_row()){
            
            //split multivalue index field
            if($id_field){ //id field defined - detect insert or update
                $id_field_values = explode("|",$row[$id_field_idx]);
            }else{
                $id_field_values = array(null);
            }
            
        foreach($id_field_values as $idx2 => $recordId_in_import){


            if(@$pairs[$recordId_in_import]){
                 $recordId_in_import = $pairs[$recordId_in_import];
            }
            
            //detect mode
            if($recordId_in_import){ //id field defined - detect insert or update
                
                //@toremove OLD $recordId_in_import = $row[$id_field_idx];
                if($previos_recordId!=$recordId_in_import){ //next record ID 
                    
                    //save data
                    if($previos_recordId!=null && !$is_mulivalue_index){ //perform action
                        $details = retainExisiting($details, $details2, $params);
                        //import detail is sorted by rec_id -0 thus it is possible to assign the same recId for several imp_id
                        doInsertUpdateRecord($recordId, $params, $details, $id_field); 
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
            { //INSERT - if field is not defined - always insert for each line in import data
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
                        $details['recordURL'] = trim($row[$index]);
                }else if($field_type=="scratchpad"){
                    if($row[$index])
                        $details['recordNotes'] = trim($row[$index]);
                }else{
                    
                    $ft_vals = $recStruc[$recordType]['dtFields'][$field_type];
                    
                    if(strpos($row[$index],"|")!==false){
                        $values = getMultiValues($row[$index], $params['csv_enclosure']);
                        
                        //  if this is multivalue index field we have to take only current value
                        if($is_mulivalue_index && @$imp_session['indexes_keyfields'][$id_field][$sel_query[$index]] && $idx2<count($values)){
//DEBUG  print "<br>".$sel_query[$index]."   ".$idx2."   ".@$values[$idx2];   
                            $values = array($values[$idx2]);
                        }
                    }else{
                        $values = array($row[$index]);
                    }
                    
                    
                    foreach ($values as $idx=>$r_value)
                    {
                        $value = null;
                        $r_value = trim($r_value);
                        
                        if(($ft_vals[$idx_fieldtype] == "enum" || $ft_vals[$idx_fieldtype] == "relationtype") 
                                && !ctype_digit($r_value)) {
                                    
                            $r_value = stripAccents(mb_strtolower($r_value)); 
//print "<br>>>>".$r_value;
                            if($ft_vals[$idx_fieldtype] == "enum"){
                                if(!$terms_enum) { //find ALL term IDs
                                    $terms_enum = mysql__select_array3($mysqli, "select LOWER(trm_Label), trm_ID  from defTerms where trm_Domain='enum' order by trm_Label");
//print print_r($terms_enum, true);
//print " <br>try to find=".@$terms_enum["Medaille d'Honneur des Epidemies"];
                                }
                                $value = @$terms_enum[$r_value];
/*                                
                                $value = array_search($r_value, $terms_enum, true);
                                if($value===false) $value = null;
*/                                
                            }else if($ft_vals[$idx_fieldtype] == "relationtype"){
                                if(!$terms_relation){ //find ALL relation IDs
                                    $terms_relation = mysql__select_array3($mysqli, "select LOWER(trm_Label), trm_ID from defTerms where trm_Domain='relation'");
                                }
                                $value = @$terms_relation[$r_value];
                            }
                            
                        }else{
                            //double spaces are removed on preprocess stage $value = trim(preg_replace('/([\s])\1+/', ' ', $r_value));
                            
                            $value = trim($r_value);
                            
                            if($value!="") {
                                if($ft_vals[$idx_fieldtype] == "date") {
                                      //$value = strtotime($value);
                                      //$value = date('Y-m-d H:i:s', $value);
                                }else{
                                    //replace \\r to\r \\n to \n
                                    $value = str_replace("\\r", "\r", $value);
                                    $value = str_replace("\\n", "\n", $value);
                                }
                            }
                            
                        }

                        if($value  && 
                            ($params['sa_upd']!=1 || !@$details2["t:".$field_type] ) ){
                                //Add new data only if field is empty (new data ignored for non-empty fields)
                         
//DEBUG print "<br>".$value."   >>".print_r($details["t:".$field_type], true)."   >>".print_r($details2["t:".$field_type], true);
                            
                            if ((!@$details["t:".$field_type] || array_search($value, $details["t:".$field_type], true)===false) //no duplications
                                && 
                               (!@$details2["t:".$field_type] || array_search($value, $details2["t:".$field_type], true)===false))
                            {
                                $cnt = count(@$details["t:".$field_type])+1;
                                $details["t:".$field_type][$cnt] = $value;
                            }else{
                                //DEBUG 
                                //print (">>>>".$value."   ".print_r($details["t:".$field_type],true)."<br>");
                            }
                        }
                    
                    }
                }
            }//for import data
            
            //add - update record for 2 cases: idfield not defined, idfield is multivalue
            if(!$id_field && count($details)>0){ //id field not defined - insert for each line
            
                   $new_id = doInsertUpdateRecord($recordId, $params, $details, $id_field_def);
                    
            }else if ($is_mulivalue_index){ //idfield is multivalue
            
                   $details = retainExisiting($details, $details2, $params);
                   $new_id = doInsertUpdateRecord($recordId, $params, $details, null);
                   if($new_id && is_numeric($new_id)) array_push($new_record_ids, $new_id);
                   $previos_recordId = null;
                   
                   if($recordId==null || $recordId<0){ //new records OR predefined id
                        $pairs[$id_field_values[$idx2]] = $new_id;
                   }
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
        }//foreach multivalue index
        
            if($is_mulivalue_index){
                    updateRecIds($import_table, end($row), $id_field, $new_record_ids);
                    $new_record_ids = array(); //to save in import table
            }
        
        }//main  all recs in import table
        $res->close();
        
        if($id_field && count($details)>0 && !$is_mulivalue_index){ //action for last record
            $details = retainExisiting($details, $details2, $params);
            $new_id = doInsertUpdateRecord($recordId, $params, $details, $id_field);
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
//
//
function updateRecIds($import_table, $imp_id, $id_field, $newids){
    global $mysqli;

    $newids = "'".implode("|", $newids)."'";
    
    $updquery = "UPDATE ".$import_table
            ." SET ".$id_field."=".$newids
            ." WHERE imp_id = ". $imp_id;
            
//DEBUG print "<br>UPD>>>>".$updquery;
    if(!$mysqli->query($updquery)){
            print "<div style='color:red'>Can not update import table (set record id ".$newids.") for line:".$imp_id.".</div>";
            //return "can not update import table (set record id)";
    }
}


//
//  Add and replace all existing value(s) for the record with new data    
//  Retain existing if no new data supplied for record
//  details2 - original
//  details - new
//
function retainExisiting($details, $details2, $params){

    if($params['sa_upd']==2){ //Add and replace all existing value(s) for the record with new data

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

//DEBUG print "RecordId=".$recordId."<br>";        
//print print_r($details, true);        

    
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
                print "<div style='color:red'>Line: ".implode(",",$details['imp_id']).". Error: ".implode("; ",$out["error"])."</div>";
                //return "can not insert record ".implode("; ",$out["error"]);
            }else{
                if($recordId!=$out["bibID"]){ //}==null){
                    
                    if($id_field && ($recordId==null || $recordId>0)){
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
            
            return @$out["bibID"];
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
                "select imp_session, imp_table from import_sessions where imp_id=".$import_id);
        
        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        if(!@$session["import_table"]){ //backward capability
            $session["import_table"] = $res[1];
        }
    
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
//
//
function renderDisambiguation($type, $imp_session){
    
    $records = $imp_session['validation']['disambiguation'];

    //$disambiguation[$keyvalue] = $disamb;

    if(count($records)>25)    
        print '<br/><input type="button" value="Back" onClick="showRecords(\'mapping\');"><br/><br/>';
        
    print '<div>The following rows match with multiple records. This may be due to the existence of duplicate records in your database, but it is more likely to indicate that you have not chosen all the fields required to correctly disambiguate the incoming rows against existing data records.</div>';
    print '<br/><br/>';
    
    print '<table class="tbmain"  cellspacing="0" cellpadding="2" width="100%"><thead><tr><th>Key values</th><th>Count</th><th>Records in Heurist</th></tr>'; // class="tbmain"

    
    foreach($records as $keyvalue =>$disamb){
                    
        $value = explode("|",$keyvalue);
        array_shift($value); //remove first element
        $value = implode(";&nbsp;&nbsp;",$value); //keyvalue
        
        print '<tr><td>'.$value.'</td><td>'.count($disamb).'</td><td>';
        
        print '<input type="hidden" name="disamb_key[]" value="'.addslashes($keyvalue).'"/><select name="disamb_id[]">';
                                    
        foreach($disamb as $rec_ID =>$rec_Title){
            print '<option value="'.$rec_ID.'">'.$rec_Title.'</option>';
        }
        print '</select></td></tr>';
    }
    
    print "</table><br />";
    print '<div>If you proceed without verification, the first matching record will be chosen - this may not be the correct record if you have used an incompete set of fields for matching.</div>';    
    print '<div>Select correct matching and press "Proceed" button to finalize matching step and assign IDS</div><br/>';    
    print '<input type="button" value="Back" onClick="showRecords(\'mapping\');">&nbsp;&nbsp;<input type="button" value="Proceed" onClick="doMatching()">';
    
}

//
// render the list of records as a table
//
function renderRecords($type, $imp_session){
    
        ///DEBUG print "fields ".print_r(@$validationRes['field_checked'],true)."<br> recs";
        ///DEBUG print print_r(@$validationRes['rec_error'],true);
        $is_missed = false;
        
        if($type=='error'){
            $tabs = $imp_session['validation']['error'];
        }else{
            
            $rec_tab = array();
            $rec_tab['count_'.$type] = $imp_session['validation']['count_'.$type];
            $rec_tab['recs_'.$type] = $imp_session['validation']['recs_'.$type];
//error_log(print_r($imp_session['validation']['recs_'.$type], true))            ;
            $tabs = array($rec_tab);
        }
        
        if(count($tabs)>1){
            $k = 0;

            print '<div id="tabs_records"><ul>';
            foreach($tabs as $rec_tab){
                $colname = @$imp_session['columns'][substr($rec_tab['field_checked'],6)];

                print '<li><a href="#rec__'.$k.'" style="color:red">'.$colname.'</a></li>';
                $k++;
            }
            print '</ul>';
            $k++;
        }
        
        $k = 0;
        //$clr = array('red','lime','blue','green','white');
        foreach($tabs as $rec_tab)
        {
        print '<div id="rec__'.$k.'">'; //' style="background-color:'.$clr[$k].'">';
        $k++;
                
        $cnt = $rec_tab['count_'.$type];
        $records = $rec_tab['recs_'.$type];
        $mapped_fields = $imp_session['validation']['mapped_fields'];

        //print print_r( @$imp_session['validation']['field_checked'], true);
        if($cnt>count($records)){
            print "<div class='error'><b>Only the first ".count($records)." of ".$cnt." rows are shown</b></div>";
        }
        
//print "<div>MAPPED2: ".print_r($mapped_fields, true)."</div>";  $checked_field."|".
        
        if($type=="error"){
            $checked_field = $rec_tab['field_checked'];
            print "<div class='error'>Values in red are invalid<br/></div>";
            print "<div>".$rec_tab['err_message']."<br/><br/></div>";
            
            $is_missed = (strpos($rec_tab['err_message'], 'a value must be supplied')>0);
        }else{
            $checked_field = null;
        }
            
        if(count($records)>25)    
        print '<br/><input type="button" value="Back" onClick="showRecords(\'mapping\');"><br/><br/>';
        
        print '<table class="tbmain"  cellspacing="0" cellpadding="2" width="100%"><thead><tr>'; // class="tbmain"

        if($type=="update"){
            print '<th>Record ID</th>';
        }
            print '<th>Line #</th>';
            
            
            $detDefs = getAllDetailTypeStructures(true);
            $detLookup = $detDefs['lookups'];
            $detDefs = $detDefs['typedefs'];
            $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
            
            $m=1;
            $err_col = 0;
            foreach($mapped_fields as $field_name=>$dt_id) {
                
                $colname = @$imp_session['columns'][substr($field_name,6)];
                if($field_name==$checked_field){
                    $colname = "<font color='red'>".$colname."</font>";
                    $err_col = $m;
                }
                
                $colname = $colname.'<br><font style="font-size:10px;font-weight:normal">'
                    .(is_numeric($dt_id) ?$detLookup[$detDefs[$dt_id]['commonFields'][$idx_dt_type]] :$dt_id)."</font>";
                
                $m++;
                print "<th>".$colname."</th>";
            }
            
            print "</tr></thead>";
            if($records && is_array($records))            
            foreach ($records as $row) {  

                print "<tr>";
                if(is_array($row)){
                    $m=0;
                    foreach($row as $value) {     
                        $value = ($value?$value:"&nbsp;");
                        if($err_col>0 && $err_col==$m){
                            if($is_missed){
                                 $value = "&lt;missed&gt;";
                            }
                            $value = "<font color='red'>".$value."</font>";
                        }
                        if($m==0 || ($type=="update" && $m==1)){
                            print "<td class='truncate'>";
                        }else{
                            print "<td>";
                        }
                        
                        print $value."</td>"; // 
                        
                        $m++;
                    }
                }
                print "</tr>";
            }
        print "</table></div>";
        
        }//tabs
    
        if(count($tabs)>1){
            print '</div>';
        }
        
        print '<br /><br /><input type="button" value="Back" onClick="showRecords(\'mapping\');">';
    
}

function stripAccents($stripAccents){
  return strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
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
