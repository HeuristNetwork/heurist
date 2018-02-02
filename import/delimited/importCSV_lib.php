<?php

/**
* importCSV_lib.php: functions for delimeted data import
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
require_once(dirname(__FILE__)."/../../admin/verification/valueVerification.php");

// global variable for progress report
$rep_processed = 0;
$rep_unique_ids = array();
$rep_added = 0;
$rep_updated = 0;
$rep_skipped = 0;
$rep_permission = 0;

$wg_id = 1;  //database owners
$rec_visibility = 'viewable';

//a couple of functions from h4/utils_db.php
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

        }else{
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


// matching functions ===================================


/**
* Finds record ids in heurist database by key fields - not used
*
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function matchingSearch($mysqli, $imp_session, $params){

    //add result of validation to session
    $imp_session['validation'] =
    array( "count_update"=>0, "count_insert"=>0,
           "count_update_rows"=>999, "count_insert_rows"=>999,
           "count_error"=>0,   //total number of errors (may be several per row)
           "error"=>array());

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
                    ." (t".$index.".trm_Label=$field_name OR t".$index.".trm_Code=$field_name)";
                    //." if(concat('',$field_name * 1) = $field_name,d".$index.".dtl_Value=$field_name,t".$index.".trm_Label=$field_name) ";

                    array_push($select_query_update_from, "defTerms t".$index);

                }else if(false && $dt_type == "freetext"){

                    $where = $where." (REPLACE(REPLACE(TRIM(d".$index.".dtl_Value),'  ',' '),'  ',' ')=".$field_name.")";

                }else{

                    $where = $where." (d".$index.".dtl_Value=".$field_name.")";

                }

                array_push($select_query_update_where, "rec_ID=d".$index.".dtl_RecID and ".$where);
                array_push($select_query_update_from, "recDetails d".$index);
            }

        }
    }

    if(count($sel_query)<1){
        return "One, and only one, key field must be selected";
    }

    $imp_session['validation']['mapped_fields'] = $mapped_fields;


    //query to search record ids  FOR UPDATE
    $select_query = "SELECT SQL_CALC_FOUND_ROWS rec_ID, group_concat(imp_id), ".implode(",",$sel_query)
    ." FROM ".implode(",",$select_query_update_from)
    ." WHERE ".implode(" and ",$select_query_update_where)
    ." GROUP BY rec_ID, ".implode(",",$sel_query);

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
        return "SQL error: Cannot execute query to calculate the number of records to be updated ".$mysqli->error;
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
        return "SQL error: Cannot execute query to calculate number of records to be inserted";
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
*
* Not used anymore!!!! Redirection to  assignMultivalues
*/
function matchingAssign($mysqli, $imp_session, $params){

    //Now we always use it
    if(true || @$params['mvm']>0){
        return assignMultivalues($mysqli, $imp_session, $params);
    }

    //everything below is NOT IN USE!!!

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
        array_push($imp_session['columns'], @$params['new_idfield']?$params['new_idfield'] : "ID field for Record type #$recordType" );
        array_push($imp_session['uniqcnt'], 0);

        //$imp_session["mapping"][$id_field] = $recordType.".id"; //$recTypeName."(id# $recordType) ID";
        $imp_session['indexes'][$id_field] = $recordType;

        $altquery = "alter table ".$import_table." add column ".$id_field." int(10) ";

        if (!$mysqli->query($altquery)) {
            return "SQL error: cannot alter import session table, cannot add new index field: " . $mysqli->error;
        }
    }else{
        $id_field_idx = substr($id_dield,6);
    }

    if(@$imp_session['indexes_keyfields'] && @$imp_session['indexes_keyfields'][$id_field]){

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
                    ." (t".$index.".trm_Label=$field_name OR t".$index.".trm_Code=$field_name)";

                    array_push($select_query_update_from, "defTerms t".$index);

                }else{

                    $where = $where." (d".$index.".dtl_Value=".$field_name.")";

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
        return "SQL error: cannot update import table (cannot clear record ID field). ".$updquery;
    }
    //matched records
    $updquery = "UPDATE ".implode(",",$select_query_update_from)." SET ".$id_field."=rec_ID WHERE "
    .implode(" and ",$select_query_update_where)." and imp_id>0";

    if(!$mysqli->query($updquery)){
        return "SQL error: cannot update import table (set record id field) ".$updquery;
    }

    //new records   ".implode(",",$sel_query).",
    $mysqli->query("SET SESSION group_concat_max_len = 1000000");

    //FIND RECORDS FOR INSERT
    $select_query = "SELECT group_concat(imp_id), ".implode(",",$sel_query)
    ." FROM ".$import_table
    . " WHERE (imp_id NOT IN "
    ." (SELECT imp_id "
    ." FROM ".implode(",",$select_query_update_from)
    ." WHERE ".implode(" and ",$select_query_update_where)
    .")) GROUP BY ".implode(",",$sel_query);

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
                if(!$mysqli->query($updquery)){
                    return "SQL error: cannot update import table: mark records for insert. ".$updquery;
                }
                $k = $k+100;
            }
            $ind--;
        }
    }else{
        return "SQL error: cannot perform query to find unmatched records ".$select_query;
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

    return $imp_session;
}


//====================================================================
/**
* Perform matching
*
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
* 
* we may work with the only multivalue field for matching - otherwise it is not possible to detect proper combination
*/
function matchingMultivalues($mysqli, $imp_session, $params){

    $imp_session['validation'] = array( "count_update"=>0, "count_insert"=>0,
        "count_update_rows"=>0,
        "count_insert_rows"=>0,
        "count_error"=>0,  //total number of errors (may be several per row)
        "error"=>array(),
        "recs_insert"=>array(),
        "recs_update"=>array() );

    $import_table = $imp_session['import_table'];
    $multivalue_field_name = $params['multifield']; //name of multivalue field
    $multivalue_field_name_idx = 0;
    $cnt_update_rows = 0;
    $cnt_insert_rows = 0;

    //disambiguation resolution
    $disamb_ids = @$params['disamb_id'];   //record ids
    $disamb_keys = @$params['disamb_key'];  //key values
    $disamb_resolv = array();
    if($disamb_keys){
        foreach($disamb_keys as $idx => $keyvalue){
            $disamb_resolv[$disamb_ids[$idx]] = str_replace("\'", "'", $keyvalue);  //rec_id => keyvalue
        }
    }

    //get rectype to import
    $recordType = @$params['sa_rectype'];

    if(intval($recordType)<1){
        return "record type not defined";
    }

    //create search query  - based on mapping (search for  sa_keyfield_ - checkboxes in UI)


    $detDefs = getAllDetailTypeStructures(true);
    $detDefs = $detDefs['typedefs'];
    $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
    $mapped_fields = array(); //field_XXX => dty_ID
    $sel_fields = array();

    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_keyfield_")===0 && $field_type){
            //get index
            $index = substr($key,12);
            $field_name = "field_".$index;

            if($field_type=="url" || $field_type=="id" || @$detDefs[$field_type]){
                $mapped_fields[$field_name] = $field_type;
                $sel_fields[] = $field_name;
            }
            
        }
    }//foreach
    //keep mapping   field_XXX => dty_ID
    $imp_session['validation']['mapped_fields'] = $mapped_fields;
    

    //already founded IDs
    $pairs = array(); //to avoid search for the same combination of match values
    $records = array();
    $disambiguation = array();
    $tmp_idx_insert = array(); //to keep indexes
    $tmp_idx_update = array(); //to keep indexes


    //loop all records in import table and detect what is for insert and what for update
    $select_query = "SELECT imp_id, ".implode(",", $sel_fields)." FROM ".$import_table;
    $res = $mysqli->query($select_query);
    if($res){
        $ind = -1;
        while ($row = $res->fetch_assoc()){
            
                $imp_id = $row['imp_id'];
                
                $values_tobind = array();
                $values_tobind[] = ''; //first element for bind_param must be a string with field types

                //BEGIN statement constructor
                $select_query_match_from = array("Records");
                $select_query_match_where = array("rec_RecTypeID=".$recordType);
                
                $multivalue_selquery_from = null;
                $multivalue_selquery_where = null;
                $multivalue_field_value = null;

                $index = 0;
                foreach ($mapped_fields as $fieldname => $field_type) {

                    if($row[$fieldname]==null || trim($row[$fieldname])=='') continue; //ignore empty values
                    
                        if($field_type=="url" || $field_type=="id"){  // || $field_type=="scratchpad"){
                            array_push($select_query_match_where, "rec_".$field_type."=?");
                            $values_tobind[] = trim($row[$fieldname]);
                        }else if(is_numeric($field_type)){

                            $from = '';
                            $where = "d".$index.".dtl_DetailTypeID=".$field_type." and ";
                            $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];

                            if( $dt_type == "enum" ||  $dt_type == "relationtype") {
                                //if fieldname is numeric - compare it with dtl_Value directly
                                $where = $where."( d".$index.".dtl_Value=t".$index.".trm_ID and "
                                ." (? in (t".$index.".trm_Label, t".$index.".trm_Code)))";
                                
                                $from = 'defTerms t'.$index.',';
                            }else{
                                $where = $where." (d".$index.".dtl_Value=?)";
                            }
                            
                            $where = 'rec_ID=d'.$index.'.dtl_RecID and '.$where;
                            $from = $from.'recDetails d'.$index;
                            
                            //we may work with the only multivalue field for matching - otherwise it is not possible to detect proper combination
                            if($multivalue_field_name==$field_name){
                                $multivalue_selquery_from = $where;
                                $multivalue_selquery_where = $from;
                                $multivalue_field_value = trim($row[$fieldname]);
                            }else{  
                                array_push($select_query_match_where, $where);
                                array_push($select_query_match_from, $from);
                                $values_tobind[] = trim($row[$fieldname]);
                            }
                            $values_tobind[0] = $values_tobind[0].'s';
                            
                        }
                        $index++;
                }//for all fields in match array
                
                if($index==0){//all matching fields in import table are empty - skip it
                    continue;
                }
                
                
                $is_update = false;
                $is_insert = false;

                $ids = array();
                //split multivalue field
                $values = getMultiValues($multivalue_field_value, $params['csv_enclosure'], $params['csv_mvsep']);
                
                if(!is_array($values) || count($values)==0){
                    $values = array(''); //at least one value
                }

                foreach($values as $idx=>$value){
                    
                    $a_tobind = $values_tobind; //values for prepared query
                    $a_from = $select_query_match_from;
                    $a_where = $select_query_match_where;
                    
                    $row[$multivalue_field_name] = $value;
                    
                    if(trim($value)!=''){
                            $a_tobind[0] = $a_tobind[0].'s';
                            $a_tobind[] = $value;
                 
                            $a_from[] = $multivalue_selquery_from;
                            $a_where[] = $multivalue_selquery_where;
                    }
                    
                    $fc = $a_tobind;
                    array_shift($fc); //remove ssssss
                    array_walk($fc, 'trim_lower_accent2');
                    //merge all values - to create unuque key for combination of values
                    $keyvalue = implode($params['csv_mvsep'], $fc);
                    
                    if(@$pairs[$keyvalue]){  //we already found record for this combination
                    
                        if(array_key_exists($keyvalue, $tmp_idx_insert)){
                            $imp_session['validation']['recs_insert'][$tmp_idx_insert[$keyvalue]][0] .= (",".$imp_id);
                            $is_insert = true;
                        }else if(array_key_exists($keyvalue, $tmp_idx_update)) {
                            $imp_session['validation']['recs_update'][$tmp_idx_update[$keyvalue]][1] .= (",".$imp_id);
                            $is_update = true;
                        }
                        array_push($ids, $pairs[$keyvalue]);
                    
                    }else{
                        
                        //query to search record ids
                        $search_query = "SELECT rec_ID, rec_Title "
                        ." FROM ".implode(",",$a_from)
                        ." WHERE ".implode(" and ",$a_where);

                        $search_stmt = $mysqli->prepare($search_query);
                        //$search_stmt->bind_param('s', $field_value);
                        $search_stmt->bind_result($rec_ID, $rec_Title);
                        
                        //assign parameters for search query
                        call_user_func_array(array($search_stmt, 'bind_param'), refValues($a_tobind));
                        $search_stmt->execute();
                        $disamb = array();
                        while ($search_stmt->fetch()) {
                            //keep pair ID => key value
                            $disamb[$rec_ID] = $rec_Title; //get value from binding
                        }

                        if(count($disamb)==0){ //nothing found - insert
                            $new_id = $ind;
                            $ind--;
                            $rec = $row;
                            $rec[0] = $imp_id;
                            $tmp_idx_insert[$keyvalue] = count($imp_session['validation']['recs_insert']); //keep index in rec_insert
                            array_push($imp_session['validation']['recs_insert'], $rec); //group_concat(imp_id), ".implode(",",$sel_query)
                            $is_insert = true;

                        }else if(count($disamb)==1 ||  array_search($keyvalue, $disamb_resolv, true)!==false){ // @$disamb_resolv[addslashes($keyvalue)]){
                            //either found exact or disamiguation is resolved

                            $new_id = $rec_ID;
                            $rec = $row;
                            $rec[0] = $imp_id;
                            array_unshift($rec, $rec_ID);
                            $tmp_idx_update[$keyvalue] = count($imp_session['validation']['recs_update']); //keep index in rec_update
                            array_push($imp_session['validation']['recs_update'], $rec); //rec_ID, group_concat(imp_id), ".implode(",",$sel_query)
                            $is_update = true;
                        }else{
                            $new_id= 'Found:'.count($disamb); //Disambiguation!
                            $disambiguation[$keyvalue] = $disamb;
                        }
                        $pairs[$keyvalue] = $new_id;
                        array_push($ids, $new_id);
                        
                        
                    }
                    
                }//for multivalues

            $records[$imp_id] = implode($params['csv_mvsep'], $ids);   //IDS to be added to import table

            if($is_update) $cnt_update_rows++;
            if($is_insert) $cnt_insert_rows++;

        }//while import table
    }


    $search_stmt->close();

    // result of work - counts of records to be inserted, updated
    $imp_session['validation']['count_update'] = count($imp_session['validation']['recs_update']);
    $imp_session['validation']['count_insert'] = count($imp_session['validation']['recs_insert']);
    $imp_session['validation']['count_update_rows'] = $cnt_update_rows;
    $imp_session['validation']['count_insert_rows'] = $cnt_insert_rows;
    $imp_session['validation']['disambiguation'] = $disambiguation;
    $imp_session['validation']['disambiguation_lines'] = '';
    $imp_session['validation']['pairs'] = $pairs;     //keyvalues => record id - count number of unique values

    //MAIN RESULT - ids to be assigned to each record in import table
    $imp_session['validation']['records'] = $records; //imp_id(line#) => list of records ids

    return $imp_session;
}


/**
* MAIN method for first step - finding exisiting /matching records in destination
* Assign record ids to field in import table
* (negative if not found)
*
* since we do match and assign in ONE STEP - first we call matchingMultivalues
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

    $id_fieldname = @$params['idfield'];
    $id_field = null;
    $field_count = count($imp_session['columns']);

    if(!$id_fieldname || $id_fieldname=="null"){
        $id_fieldname = "ID field for Record type #".$recordType; //not defined - create new one
    }
    $index = array_search($id_fieldname, $imp_session['columns']); //find it among existing columns
    if($index!==false){ //this is existing field
        $id_field  = "field_".$index;
        $imp_session['uniqcnt'][$index] = count($pairs);
    }

    //add new field into import table
    if(!$id_field){

        $id_field = "field_".$field_count;
        $altquery = "alter table ".$import_table." add column ".$id_field." varchar(255) ";
        if (!$mysqli->query($altquery)) {
            return "SQL error: cannot alter import session table; cannot add new index field: " . $mysqli->error;
        }
        array_push($imp_session['columns'], $id_fieldname );
        array_push($imp_session['uniqcnt'], count($pairs) );
        if(@$params['idfield']){
            array_push($imp_session['multivals'], $field_count ); //!!!!
        }
    }
    //define field as index in session
    @$imp_session['indexes'][$id_field] = $recordType;


    //get field type
    $field_type = @$params['sa_keyfield_type'];

    if(!@$imp_session['indexes_keyfields']){
        $imp_session['indexes_keyfields'] = array();
    }
    $imp_session['indexes_keyfields'][$id_field] = $imp_session['validation']['mapped_fields'];


    //add NEW records
    $rep_processed=0;
    $rep_unique_ids = array();
    $rep_added   = 0;
    $rep_updated = 0;
    $rep_skipped = 0;
    $rep_permission = 0;

    //update values in import table - replace negative to new one
    foreach($records as $imp_id => $ids){

        if($is_create_records){
            $ids = explode($params['csv_mvsep'], $ids);
            $newids = array();
            foreach($ids as $id){
                if($id<0) $id = $newrecs[$id];
                if($id)
                    array_push($newids,$id);
            }
            $newids = implode($params['csv_mvsep'], $newids);
        }else{
            $newids = $ids;
        }

        if($ids){
            //update
            $updquery = "update ".$import_table." set ".$id_field."='".$ids
            ."' where imp_id = ".$imp_id;
            if(!$mysqli->query($updquery)){
                return "SQL error: cannot update import table: set ID field ".$mysqli->error."    QUERY:".$updquery;
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
function getMultiValues($values, $csv_enclosure, $csv_mvsep){

    $nv = array();
    $values =  explode($csv_mvsep, $values);
    if(count($values)==1){
        array_push($nv, trim($values[0]));
    }else{

        $csv_enclosure = ($csv_enclosure==1)?"'":'"'; //need to remove quotes for multivalues fields

        foreach($values as $idx=>$value){
            if($value!=""){
                if(strpos($value,$csv_enclosure)===0 && strrpos($value,$csv_enclosure)===strlen($value)-1){
                    $value = substr($value,1,strlen($value)-2);
                }
                array_push($nv, trim($value));
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
/*
sa_rectype
ignore_insert = 1
recid_field   - field_X
*/
function validateImport($mysqli, $imp_session, $params){
    
    $is_json = ($params["action"]=='step4');

    //add result of validation to session
    $imp_session['validation'] = array( "count_update"=>0,
        "count_insert"=>0,       //records to be inserted
        "count_update_rows"=>0,
        "count_insert_rows"=>0,  //row that are source of insertion
        "count_ignore_rows"=>0,
        "count_error"=>0, 
        "error"=>array(),
        "count_warning"=>0, 
        "warning"=>array()
         );

    //get rectype to import
    $recordType = @$params['sa_rectype'];

    $currentSeqIndex = @$params['seq_index'];
    $sequence = null;
    if($currentSeqIndex>=0 && @$imp_session['sequence'] && is_array(@$imp_session['sequence'][$currentSeqIndex])){
        $sequence = $imp_session['sequence'][$currentSeqIndex];    
    }
    
    if(intval($recordType)<1){
        return "record type not defined";
    }

    $import_table = $imp_session['import_table'];
    $id_field = @$params['recid_field']; //record ID field is always defined explicitly
    $ignore_insert = (@$params['ignore_insert']==1); //ignore new records

    $cnt_update_rows = 0;
    $cnt_insert_rows = 0;

    //get field mapping and selection query from _REQUEST(params)
    if(@$params['mapping']){    //new way
        $mapping_params = @$params['mapping'];

        $mapping = array();  // fieldtype => fieldname in import table
        $sel_query = array();
        
        if(is_array($mapping_params) && count($mapping_params)>0){
            foreach ($mapping_params as $index => $field_type) {
            
                $field_name = "field_".$index;

                $mapping[$field_type] = $field_name;
                
                $imp_session['validation']['mapped_fields'][$field_name] = $field_type;

                //all mapped fields - they will be used in validation query
                array_push($sel_query, $field_name);
            }
        }else{
            return 'Mapping is not defined';
        }
    }else{
    
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
    }
//error_log(' validation upd:'.$cnt_update_rows.'  to insert '.$cnt_insert_rows);
    

    // calculate the number of records to insert, update and insert with existing ids
    // @todo - it has been implemented for non-multivalue indexes only
    if(!$id_field){ //ID field not defined - all records will be inserted
        if(!$ignore_insert){
            $imp_session['validation']["count_insert"] = $imp_session['reccount'];
            $imp_session['validation']["count_insert_rows"] = $imp_session['reccount'];  //all rows will be imported as new records
            $select_query = "SELECT imp_id, ".implode(",",$sel_query)." FROM ".$import_table." LIMIT 5000";
            $imp_session['validation']['recs_insert'] = mysql__select_array3($mysqli, $select_query, false);
        }

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
            // TPDO: I'm not sure whether message below has been correctly interpreted
            $wrong_records = getWrongRecords($mysqli, $query, $imp_session,
                "Your input data contain record IDs in the selected ID column for existing records which are not numeric IDs. ".
                "The import cannot proceed until this is corrected.","Incorrect record types", $id_field);
            if(is_array($wrong_records) && count($wrong_records)>0) {
                $wrong_records['validation']['mapped_fields'][$id_field] = 'id';
                $imp_session = $wrong_records;
            }else if($wrong_records) {
                return $wrong_records;
            }

            if(!$ignore_insert){      //WARNING - it ignores possible multivalue index field
                //find record ID that do not exist in HDB - to insert
                $query = "select count(imp_id) "
                ." from ".$import_table
                ." left join Records on rec_ID=".$id_field
                ." where ".$id_field.">0 and rec_ID is null";
                $row = mysql__select_array2($mysqli, $query);
                if($row && $row[0]>0){
                    $cnt_recs_insert_nonexist_id = $row[0];
                }
            }
        }

        // find records to update
        $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table
        ." left join Records on rec_ID=".$id_field." WHERE rec_ID is not null and ".$id_field.">0";
        $row = mysql__select_array2($mysqli, $select_query);
        if($row){

            if( $row[0]>0 ){

                $imp_session['validation']['count_update'] = $row[0];
                $imp_session['validation']['count_update_rows'] = $row[0];
                //find first 100 records to display
                $select_query = "SELECT ".$id_field.", imp_id, ".implode(",",$sel_query)
                ." FROM ".$import_table
                ." left join Records on rec_ID=".$id_field
                ." WHERE rec_ID is not null and ".$id_field.">0"
                ." ORDER BY ".$id_field." LIMIT 5000";
                $imp_session['validation']['recs_update'] = mysql__select_array3($mysqli, $select_query, false);

            }

        }else{
            return "SQL error: Cannot execute query to calculate number of records to be updated!";
        }

        if(!$ignore_insert){

            // find records to insert
            $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table." WHERE ".$id_field."<0"; //$id_field." is null OR ".
            $row = mysql__select_array2($mysqli, $select_query);
            $cnt = ($row && $row[0]>0)?intval($row[0]):0;

            $select_query = "SELECT count(*) FROM ".$import_table." WHERE ".$id_field." IS NULL"; 
            $row = mysql__select_array2($mysqli, $select_query);
            $cnt = $cnt + (($row && $row[0]>0)?intval($row[0]):0);

            if( $cnt>0 ){
                    $imp_session['validation']['count_insert'] = $cnt;
                    $imp_session['validation']['count_insert_rows'] = $cnt;

                    //find first 100 records to display
                    $select_query = "SELECT imp_id, ".implode(",",$sel_query)." FROM ".$import_table
                            .' WHERE '.$id_field.'<0 or '.$id_field.' IS NULL LIMIT 5000';
                    $imp_session['validation']['recs_insert'] = mysql__select_array3($mysqli, $select_query, false);
            }
        }
        //additional query for non-existing IDs
        if($cnt_recs_insert_nonexist_id>0){

            $imp_session['validation']['count_insert_nonexist_id'] = $cnt_recs_insert_nonexist_id;
            $imp_session['validation']['count_insert'] = $imp_session['validation']['count_insert']+$cnt_recs_insert_nonexist_id;
            $imp_session['validation']['count_insert_rows'] = $imp_session['validation']['count_insert'];

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

    $dt_mapping = array(); //mapping to detail type ID

    $missed = array();
    $query_reqs = array(); //fieldnames from import table
    $query_reqs_where = array(); //where clause for validation of required fields

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

    $numeric_regex = "'^([+-]?[0-9]+\.*)+'"; // "'^([+-]?[0-9]+\\.?[0-9]*e?[0-9]+)|(0x[0-9A-F]+)$'";

    if($sequence){
        $mapping_prev_session = @$sequence['mapping_keys']; //OR mapping_flds???
    }else{
        $mapping_prev_session = @$imp_session['mapping'];
    }

    //loop for all fields in record type structure
    foreach ($recStruc[$recordType]['dtFields'] as $ft_id => $ft_vals) {


        //find among mappings
        $field_name = @$mapping[$ft_id];
        if(!$field_name){
            
            if(is_array($mapping_prev_session)){
                $field_name = array_search($recordType.".".$ft_id, $mapping_prev_session, true); //from previous session    
            }
        }

        if(!$field_name && $ft_vals[$idx_fieldtype] == "geo"){
            //specific mapping for geo fields
            //it may be mapped to itself or mapped to two fields - lat and long

            $field_name1 = @$mapping[$ft_id."_lat"];
            $field_name2 = @$mapping[$ft_id."_long"];
            if(!$field_name1 && !$field_name2 && is_array($mapping_prev_session)){
                $field_name1 = array_search($recordType.".".$ft_id."_lat", $mapping_prev_session, true);
                $field_name2 = array_search($recordType.".".$ft_id."_long", $mapping_prev_session, true);
            }

            //search for empty required fields in import table
            if($ft_vals[$idx_reqtype] == "required"){
                if(!$field_name1 || !$field_name2){
                    array_push($missed, $ft_vals[0]);
                }else{
                    array_push($query_reqs, $field_name1);
                    array_push($query_reqs, $field_name2);
                    array_push($query_reqs_where, $field_name1." is null or ".$field_name1."=''");
                    array_push($query_reqs_where, $field_name2." is null or ".$field_name2."=''");
                }
            }
            if($field_name1 && $field_name2){
                array_push($query_num, $field_name1);
                array_push($query_num_where, "(NOT($field_name1 is null or $field_name1='') and NOT($field_name1 REGEXP ".$numeric_regex."))");
                array_push($query_num, $field_name2);
                array_push($query_num_where, "(NOT($field_name2 is null or $field_name2='') and NOT($field_name2 REGEXP ".$numeric_regex."))");
            }


        }else
        if($ft_vals[$idx_reqtype] == "required"){
            if(!$field_name){
                array_push($missed, $ft_vals[0]);
            }else{
                if($ft_vals[$idx_fieldtype] == "resource"){ //|| $ft_vals[$idx_fieldtype] == "enum"){
                    $squery = "not (".$field_name.">0)";
                }else{
                    $squery = $field_name." is null or ".$field_name."=''";
                }

                array_push($query_reqs, $field_name);
                array_push($query_reqs_where, $squery);
            }
        }

        if($field_name){  //mapping exists

            $dt_mapping[$field_name] = $ft_id; //$ft_vals[$idx_fieldtype];

            if($ft_vals[$idx_fieldtype] == "enum" ||  $ft_vals[$idx_fieldtype] == "relationtype") {
                array_push($query_enum, $field_name);
                $trm1 = "trm".count($query_enum);
                array_push($query_enum_join,
                    " defTerms $trm1 on ($trm1.trm_Label=$field_name OR $trm1.trm_Code=$field_name)");
                array_push($query_enum_where, "(".$trm1.".trm_Label is null and not ($field_name is null or $field_name=''))");
            }else if($ft_vals[$idx_fieldtype] == "resource"){
                array_push($query_res, $field_name);
                $trm1 = "rec".count($query_res);
                array_push($query_res_join, " Records $trm1 on $trm1.rec_ID=$field_name ");
                array_push($query_res_where, "(".$trm1.".rec_ID is null and not ($field_name is null or $field_name=''))");

            }else if($ft_vals[$idx_fieldtype] == "float" ||  $ft_vals[$idx_fieldtype] == "integer") {

                array_push($query_num, $field_name);
                array_push($query_num_where, "(NOT($field_name is null or $field_name='') and NOT($field_name REGEXP ".$numeric_regex."))");



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

    //ignore_required

    //1. Verify that all required field are mapped  =====================================================
    if(count($missed)>0  &&
        ($imp_session['validation']['count_insert']>0   // there are records to be inserted
            //  || ($params['sa_upd']==2 && $params['sa_upd2']==1)   // Delete existing if no new data supplied for record
        )){
            return 'The following fields are required fields. You will need to map 
them to incoming data before you can import new records:<br><br>'.implode(",", $missed);
    }

    if($id_field){ //validate only for defined records IDs
        if($ignore_insert){
            $only_for_specified_id = " (".$id_field." > 0) AND ";
        }else{
            $only_for_specified_id = " (".$id_field."!='') AND ";//otherwise it does not for skip matching " (NOT(".$id_field." is null OR ".$id_field."='')) AND ";
        }
    }else{
        $only_for_specified_id = "";
    }

    //2. In DB: Verify that all required fields have values =============================================
    $k=0;
    foreach ($query_reqs as $field){
        $query = "select imp_id, ".implode(",",$sel_query)
        ." from $import_table "
        ." where ".$only_for_specified_id."(".$query_reqs_where[$k].")"; // implode(" or ",$query_reqs_where);
        $k++;
        
        $wrong_records = getWrongRecords($mysqli, $query, $imp_session,
            "This field is required. It is recommended that a value must be supplied for every record. "
            ."You can find and correct these values using Manage > Structure > Verify",
            "Missing Values", $field, 'warning');
        if(is_array($wrong_records)) {
            
            
            $cnt = count(@$imp_session['validation']['warning']);//was
            //@$imp_session['validation']['count_warning']
            $imp_session = $wrong_records;
            $imp_session['validation']['missed_required'] = true;

            //allow add records with empty required field - 2017/03/12
            if(false && count(@$imp_session['validation']['recs_insert'])>0 ){
                //--remove from array to be inserted - wrong records with missed required field
                $cnt2 = count(@$imp_session['validation']['warning']);//now
                if($cnt2>$cnt){
                    $wrong_recs_ids = $imp_session['validation']['warning'][$cnt]['recs_error_ids'];
                    if(count($wrong_recs_ids)>0){ 
                        $badrecs = array();
                        foreach($imp_session['validation']['recs_insert'] as $idx=>$flds){
                            if(in_array($flds[0], $wrong_recs_ids)){
                                array_push($badrecs, $idx);
                            }
                        }
                        $imp_session['validation']['recs_insert'] = array_diff_key($imp_session['validation']['recs_insert'],
                                    array_flip($badrecs) );
                        $imp_session['validation']["count_insert"] = count($imp_session['validation']['recs_insert']);                                     
                    }
                }
            }


       }else if($wrong_records) {
            return $wrong_records;
       }
    }
    //3. In DB: Verify that enumeration fields have correct values =====================================
    if(!@$imp_session['csv_enclosure']){
        $imp_session['csv_enclosure'] = $params['csv_enclosure'];
    }
    if(!@$imp_session['csv_mvsep']){
        $imp_session['csv_mvsep'] = $params['csv_mvsep'];
    }


    $hwv = " have incorrect values";
    $k=0;
    foreach ($query_enum as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;
            
            $wrong_records = validateEnumerations($mysqli, $query, $imp_session, $field, $dt_mapping[$field], 
                $idx, $recStruc, $recordType,
                "Term list values read must match existing terms defined for the field", "new terms");

        }else{

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table left join ".$query_enum_join[$k]   //implode(" left join ", $query_enum_join)
            ." where ".$only_for_specified_id."(".$query_enum_where[$k].")";  //implode(" or ",$query_enum_where);
            
            $wrong_records = getWrongRecords($mysqli, $query, $imp_session,
                "Term list values read must match existing terms defined for the field",
                "Invalid Terms", $field);
        }

        $k++;

        //if($wrong_records) return $wrong_records;
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records) {
            return $wrong_records;
        }
    }
    //4. In DB: Verify resource fields ==================================================
    $k=0;
    foreach ($query_res as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateResourcePointers($mysqli, $query, $imp_session, $field, $dt_mapping[$field], $idx, $recStruc, $recordType);

        }else{
            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table left join ".$query_res_join[$k]  //implode(" left join ", $query_res_join)
            ." where ".$only_for_specified_id."(".$query_res_where[$k].")"; //implode(" or ",$query_res_where);
            $wrong_records = getWrongRecords($mysqli, $query, $imp_session,
                "Record pointer field values must reference an existing record in the database",
                "Invalid Pointers", $field);
        }

        $k++;

        //"Fields mapped as resources(pointers)".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records) {
            return $wrong_records;
        }
    }

    //5. Verify numeric fields
    $k=0;
    foreach ($query_num as $field){

        if(in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateNumericField($mysqli, $query, $imp_session, $field, $idx, 'warning');

        }else{
            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table "
            ." where ".$only_for_specified_id."(".$query_num_where[$k].")";

            $wrong_records = getWrongRecords($mysqli, $query, $imp_session,
                "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation",
                "Invalid Numerics", $field, 'warning');
        }

        $k++;

        // "Fields mapped as numeric".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records) {
            return $wrong_records;
        }
    }
    
    //6. Verify datetime fields
    $k=0;
    foreach ($query_date as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateDateField($mysqli, $query, $imp_session, $field, $idx, 'warning');

        }else{
            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table "
            ." where ".$only_for_specified_id."(".$query_date_where[$k].")"; //implode(" or ",$query_date_where);
            $wrong_records = getWrongRecords($mysqli, $query, $imp_session,
                "Date values must be in dd-mm-yyyy, dd/mm/yyyy or yyyy-mm-dd formats",
                "Invalid Dates", $field, 'warning');

        }

        $k++;
        //"Fields mapped as date".$hwv,
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
* @param mixed $type  error or warning
*/
function getWrongRecords($mysqli, $query, $imp_session, $message, $short_message, $fields_checked, $type='error'){

//error_log('valquery: '.$query);

    $res = $mysqli->query($query." LIMIT 5000");
    if($res){
        $wrong_records = array();
        $wrong_records_ids = array();
        while ($row = $res->fetch_row()){
            array_push($wrong_records, $row);
            array_push($wrong_records_ids, $row[0]);
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_".$type] = $cnt_error;
            $error["recs_error"] = $wrong_records; //array_slice($wrong_records,0,1000); //imp_id, fields
            $error["recs_error_ids"] = $wrong_records_ids;
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            $error["short_message"] = $short_message;

            $imp_session['validation']['count_'.$type] = $imp_session['validation']['count_'.$type]+$cnt_error;
            array_push($imp_session['validation'][$type], $error);

            return $imp_session;
        }

    }else{
        return "SQL error: Cannot perform validation query: ".$query;
    }
    return null;
}


/**
* validate values for field that is matched to enumeration fieldtype
*
* @param mixed $mysqli
* @param mixed $query  - query to retrieve values from import table
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $dt_id - mapped detail type ID
* @param mixed $field_idx - index of validation field in query result (to get value)
* @param mixed $recStruc - record type structure
* @param mixed $message - error message
* @param mixed $short_message
*/
function validateEnumerations($mysqli, $query, $imp_session, $fields_checked, $dt_id, $field_idx, $recStruc, $recordType, 
            $message, $short_message){


    $dt_def = $recStruc[$recordType]['dtFields'][$dt_id];

    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $dt_type = $dt_def[$idx_fieldtype];
    
    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        
        $wrong_records = array();
        $wrong_values = array();
        
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                $r_value2 = trim_lower_accent($r_value);
          
                if($r_value2!=""){

                    $is_termid = false;
                    if(ctype_digit($r_value2)){ //value is numeric try to compare with trm_ID
                        $is_termid = isValidTerm( $dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id);
                    }

                    if($is_termid){
                        $term_id = $r_value;
                    }else{
                        //strip accents on both sides
                        $term_id = isValidTermLabel($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id, true );
                     
                        if(!$term_id){
                            $term_id = isValidTermCode($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id );
                        }
                    }

                    if (!$term_id)
                    {//not found
                        $is_error = true;
                        array_push($newvalue, "<font color='red'>".$r_value."</font>");
                        if(array_search($r_value, $wrong_values)===false){
                                array_push($wrong_values, $r_value);
                        }
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["values_error"] = array_slice($wrong_values,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            $error["short_message"] = $short_message;

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        return "SQL error: Cannot perform validation query: ".$query;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $dt_id - mapped detail type ID
* @param mixed $field_idx - index of validation field in query result (to get value)
* @param mixed $recStruc - record type structure
*/
function validateResourcePointers($mysqli, $query, $imp_session, $fields_checked, $dt_id, $field_idx, $recStruc, $recordType){


    $dt_def = $recStruc[$recordType]['dtFields'][$dt_id];
    $idx_pointer_types = $recStruc['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];

    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                $r_value2 = trim($r_value);
                if($r_value2!=""){

                    if (!isValidPointer($dt_def[$idx_pointer_types], $r_value2, $dt_id ))
                    {//not found
                        $is_error = true;
                        array_push($newvalue, "<font color='red'>".$r_value."</font>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Record pointer fields must reference an existing record of valid type in the database";
            $error["short_message"] = "Invalid Pointers";

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        return "SQL error: Cannot perform validation query: ".$query;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $field_idx - index of validation field in query result (to get value)
*/
function validateNumericField($mysqli, $query, $imp_session, $fields_checked, $field_idx, $type){

    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                if($r_value!=null && trim($r_value)!=""){

                    if(!is_numeric($r_value)){
                        $is_error = true;
                        array_push($newvalue, "<font color='red'>".$r_value."</font>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_".$type] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation";
            $error["short_message"] = "Invalid Numerics";
            $imp_session['validation']['count_'.$type] = $imp_session['validation']['count_'.$type]+$cnt_error;
            array_push($imp_session['validation'][$type], $error);

            return $imp_session;
        }

    }else{
        return "SQL error: Cannot perform validation query: ".$query;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $field_idx - index of validation field in query result (to get value)
*/
function validateDateField($mysqli, $query, $imp_session, $fields_checked, $field_idx, $type){

    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                if($r_value!=null && trim($r_value)!=""){


                    if( is_numeric($r_value) && ($r_value=='0' || intval($r_value)) ){
                        array_push($newvalue, $r_value);
                    }else{

                         try{   
                            $t2 = new DateTime($r_value);
                            $value = $t2->format('Y-m-d H:i:s');
                            array_push($newvalue, $value);
                         } catch (Exception  $e){
                            $is_error = true;
                            array_push($newvalue, "<font color='red'>".$r_value."</font>");
                         }                            
                        /* OLD VERSION - strtotime doesn't work for dates prior 1901
                        $date = date_parse($r_value);
                        if ($date["error_count"] == 0 && checkdate($date["month"], $date["day"], $date["year"]))
                        {
                            $value = strtotime($r_value);
                            $value = date('Y-m-d H:i:s', $value);
                            array_push($newvalue, $value);
                        }else{
                            $is_error = true;
                            array_push($newvalue, "<font color='red'>".$r_value."</font>");
                        }
                        */

                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_".$type] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Date values must be in dd-mm-yyyy, mm/dd/yyyy or yyyy-mm-dd formats";
            $error["short_message"] = "Invalid Dates";
            $imp_session['validation']['count_'.$type] = $imp_session['validation']['count_'.$type]+$cnt_error;
            array_push($imp_session['validation'][$type], $error);

            return $imp_session;
        }

    }else{
        return "SQL error: Cannot perform validation query: ".$query;
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
function doImport($mysqli, $imp_session, $params, $mode_output){

    global $rep_processed,$rep_added,$rep_updated,$rep_skipped,$rep_permission,$wg_id,$rec_visibility;

    $addRecDefaults = getDefaultOwnerAndibility(null);
    
    $wg_id = $addRecDefaults[1];
    $rec_visibility = $addRecDefaults[2];
    
    //rectype to import
    $import_table = $imp_session['import_table'];
    $recordType = @$params['sa_rectype'];
    
    $currentSeqIndex = @$params['seq_index'];
    $use_sequence = false;
    if($currentSeqIndex>=0 && @$imp_session['sequence'] && is_array(@$imp_session['sequence'][$currentSeqIndex])){
        $use_sequence = true;    
    }
    
    $id_field = @$params['recid_field']; //record ID field is always defined explicitly
    
    $id_field_not_defined = ($id_field==null || $id_field=='');

    if($id_field && @$imp_session['indexes_keyfields'][$id_field]){  //indexes_keyfields not used anymore in new method
        $is_mulivalue_index = true;
    }else{
        $is_mulivalue_index = false;       //so we always have FALSE
    }

    if(intval($recordType)<1){
        return "record type not defined";
    }

    $field_types = array();  // idx => fieldtype ID
    $sel_query = array();
    $mapping = array();
    
    //get field mapping and selection query from _REQUEST(params)
    if(@$params['mapping']){    //new way
        $mapping = @$params['mapping'];// idx => fieldtype ID
        
        if(is_array($mapping) && count($mapping)>0){
            foreach ($mapping as $index => $field_type) {
                $field_name = "field_".$index;
                array_push($field_types, $field_type);
                array_push($sel_query, $field_name);
            }
        }else{
            return 'Mapping is not defined';
        }
    }else{
    
        foreach ($params as $key => $field_type) {
            if(strpos($key, "sa_dt_")===0 && $field_type){  //search for values of field selector
                //get index
                $index = substr($key,6);
                $field_name = "field_".$index;

                //all mapped fields - they will be used in validation query
                array_push($sel_query, $field_name);
                array_push($field_types, $field_type);
                $mapping[$index] = $field_type;
            }
        }
        if(count($sel_query)<1){
            return "mapping not defined";
        }

    }
    
    //indexes
    $recStruc = getRectypeStructures(array($recordType));
    $recTypeName = $recStruc[$recordType]['commonFields'][ $recStruc['commonNamesToIndex']['rty_Name'] ];
    $idx_name = $recStruc['dtFieldNamesToIndex']['rst_DisplayName'];

    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
    $recordTypeStructure = $recStruc[$recordType]['dtFields'];

    //get terms name=>id
    $terms_enum = null;
    $terms_relation = null;

    $select_query = "SELECT ". implode(",", $sel_query)
    . ( $id_field ?", ".$id_field:"" )
    . ", imp_id "  //last field is row# - imp_id
    . " FROM ".$import_table;

    $ignore_insert = ($params['ignore_insert']==1);

    if($id_field){  //index field defined - add to list of columns
        $id_field_idx = count($field_types); //last one

        if($ignore_insert){
            $select_query = $select_query." WHERE (".$id_field.">0) ";  //use records with defined value in index field
        }else{
            $select_query = $select_query." WHERE (".$id_field."!='') "; //ignore empty values
        }
        $select_query = $select_query." ORDER BY ".$id_field;
        
    }else{
        if($ignore_insert){
            return "id field not defined";
        }

        //create id field by default - add to import table

        $id_fieldname = "ID field for Record type #".$recordType;
        $index = array_search($id_fieldname, $imp_session['columns']); //find it among existing columns

        if($index!==false){ //this is existing field
            $id_field_def  = "field_".$index;
            $imp_session['indexes'][$id_field] = $recordType;
        }else{
            $field_count = count($imp_session['columns']);
            $id_field_def = "field_".$field_count;

            $altquery = "alter table ".$import_table." add column ".$id_field_def." int(10) ";
            if (!$mysqli->query($altquery)) {
                return "SQL error: cannot alter import session table, cannot add new index field: " . $mysqli->error;
            }
            $imp_session['indexes'][$id_field_def] = $recordType;
            array_push($imp_session['columns'], $id_fieldname);
        }
    }

    $res = $mysqli->query($select_query);
    if (!$res) {

        return "import table is empty";

    }else{

        $rep_processed = 0;
        $rep_unique_ids = array();
        $rep_added = 0;
        $rep_updated = 0;
        $rep_skipped = 0;
        $rep_permission = 0;
        $tot_count = $imp_session['reccount'];
        $first_time = true;
        $step = ceil($tot_count/10);
        if($step>10) $step = 10;
        else if($step<1) $step=1;
        
        $csv_mvsep = @$params['csv_mvsep']?$params['csv_mvsep']:$imp_session['csv_mvsep'];
        $csv_enclosure = @$params['csv_enclosure']?$params['csv_enclosure']:$imp_session['csv_enclosure'];
                                   
        $previos_recordId = null;
        $recordId = null;
        $details = array();
        $details2 = array(); //to keep original for sa_mode=2 (replace all existing value)

        $new_record_ids = array();
        $imp_id = null;

        $pairs = array(); // for multivalue rec_id => new_rec_id  (keep new id if such id already used)

        while ($row = $res->fetch_row()){

            //split multivalue index field
            $id_field_values = array();
            if($id_field){
                if(@$row[$id_field_idx]){ //id field defined - detect insert or update
                    $id_field_values = explode($csv_mvsep, $row[$id_field_idx]);
                }else if(!$ignore_insert){
                    //field value is not defined - add new record
                    $id_field_values = array(0=>null);
                }
            }
            
            //loop all id values - because on index field may have multivalue ID
            foreach($id_field_values as $idx2 => $recordId_in_import){

                if($recordId_in_import!=null && @$pairs[$recordId_in_import]){ //already imported
                    $recordId_in_import = $pairs[$recordId_in_import];
                }

                //we already have recordID
                if($recordId_in_import){ //id field defined - detect insert or update

                    //@toremove OLD $recordId_in_import = $row[$id_field_idx];
                    if($previos_recordId!=$recordId_in_import){ //next record ID

                        //record id is changed - save data
                        //$recordId is already set 
                        if($previos_recordId!=null && !$is_mulivalue_index && count($details)>0){ //perform action

                            //$details = retainExisiting($details, $details2, $params, $recordTypeStructure, $idx_reqtype);
                            //import detail is sorted by rec_id -0 thus it is possible to assign the same recId for several imp_id
                            $new_id = doInsertUpdateRecord($recordId, $params, $details, $id_field, $mode_output);
                            $pairs[$previos_recordId] = $new_id;//new_A
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

                //START TO FILL DETAILS ============================

                if(@$details['imp_id']==null){
                    $details['imp_id'] = array();
                }
                array_push( $details['imp_id'], end($row));

                $lat = null;
                $long = null;

                foreach ($field_types as $index => $field_type) {

                    if($field_type=="url"){
                        if($row[$index])
                            $details['recordURL'] = trim($row[$index]);
                    }else if($field_type=="scratchpad"){
                        if($row[$index])
                            $details['recordNotes'] = trim($row[$index]);
                    }else{

                        if(substr($field_type, -strlen("_lat")) === "_lat"){
                            $field_type = substr($field_type, 0, strlen($field_type)-4);
                            $fieldtype_type = "lat";
                        }else if (substr($field_type, -strlen("_long")) === "_long"){
                            $field_type = substr($field_type, 0, strlen($field_type)-5);
                            $fieldtype_type = "long";
                        }else{
                            $ft_vals = $recordTypeStructure[$field_type]; //field type description
                            $fieldtype_type = $ft_vals[$idx_fieldtype];
                        }

                        if(strpos($row[$index], $csv_mvsep)!==false){
                            $values = getMultiValues($row[$index], $csv_enclosure, $csv_mvsep);

                            //  if this is multivalue index field we have to take only current value
                            if($is_mulivalue_index && @$imp_session['indexes_keyfields'][$id_field][$sel_query[$index]] && $idx2<count($values)){
                                $values = array($values[$idx2]);
                            }
                        }else{
                            $values = array($row[$index]);
                        }

                        foreach ($values as $idx=>$r_value)
                        {
                            $value = null;
                            $r_value = trim($r_value);

                            if(($fieldtype_type == "enum" || $fieldtype_type == "relationtype")){

                                $r_value = trim_lower_accent($r_value);

                                
                                if($r_value!=""){

                                    if(ctype_digit($r_value)
                                    && isValidTerm( $ft_vals[$idx_term_tree], $ft_vals[$idx_term_nosel], $r_value, $field_type)){

                                        $value = $r_value;
                                    }

                                    if($value == null){
                                        //stip accents on both sides
                                        $value = isValidTermLabel($ft_vals[$idx_term_tree], $ft_vals[$idx_term_nosel], $r_value, $field_type, true );
                                    }
                                    if(!($value>0)){
                                        $value = isValidTermCode($ft_vals[$idx_term_tree], $ft_vals[$idx_term_nosel], $r_value, $field_type );
                                    }
                                }
                            }
                            else if($fieldtype_type == "resource"){

                                if(!isValidPointer(null, $r_value, $field_type)){
                                     $value  = null;
                                }else{
                                     $value = $r_value;
                                }


                            }
                            else if($fieldtype_type == "geo"){
                                //verify WKT
                                $geoType = null;
                                //get WKT type
                                if(strpos($r_value,'POINT')!==false){
                                    $geoType = "p";
                                }else if(strpos($r_value,'LINESTRING')!==false){
                                    $geoType = "l";
                                }else if(strpos($r_value,'POLYGON')!==false){
                                    $geoType = "pl";
                                }

                                if($geoType){
                                    if(strpos($r_value, $geoType)===0){
                                        //already exists                                        
                                        $value = $r_value;
                                    }else{
                                        $value = $geoType." ".$r_value;    
                                    }
                                }else{
                                    $value = null;
                                }

                            }else if($fieldtype_type == "lat") {
                                $lat = $r_value;
                            }else if($fieldtype_type == "long"){
                                //WARNING MILTIVALUE IS NOT SUPPORTED
                                $long = $r_value;
                            }else{
                                //double spaces are removed on preprocess stage $value = trim(preg_replace('/([\s])\1+/', ' ', $r_value));

                                $value = trim($r_value);

                                if($value!="") {
                                    if($fieldtype_type == "date") {
                                        //$value = strtotime($value);
                                        //$value = date('Y-m-d H:i:s', $value);
                                    }else{
                                        //replace \\r to\r \\n to \n
                                        $value = str_replace("\\r", "\r", $value);
                                        $value = str_replace("\\n", "\n", $value);
                                    }
                                }
                            }

                            if($lat && $long){
                                $value = "p POINT (".$long."  ".$lat.")"; //TODO Where does the 'p' come from? This appears to have been a local invention ...
                                //reset
                                $lat = null;
                                $long = null;
                            }

                            if($value  &&   //value defined
                            ($params['sa_upd']!=1 || !@$details2["t:".$field_type] ) ){
                                //$params['sa_upd']==1 Add new data only if field is empty (new data ignored for non-empty fields)

                                $details_lc = array();
                                $details2_lc = array();

                                /*if($params['sa_upd']==2 && $params['sa_upd2']==1 && !@$details["t:".$field_type]["bd:delete"]){
                                unset($details["t:".$field_type]["bd:delete"]); //new data is porvided - no need to delete
                                }else if($params['sa_upd']==2 && $params['sa_upd2']!=1 && !@$details["t:".$field_type]["bd:delete"]){
                                $details["t:".$field_type]["bd:delete"] = "ups!"; //if new data are provided then remove old data
                                }*/

                                if($params['sa_upd']==2){
                                    $details["t:".$field_type]["bd:delete"] = "ups!"; //if new data are provided then remove old data
                                }


                                if(is_array(@$details["t:".$field_type]))
                                    $details_lc = array_map('trim_lower_accent', $details["t:".$field_type]);
                                if($params['sa_upd']!=2 && is_array(@$details2["t:".$field_type]))
                                    $details2_lc = array_map('trim_lower_accent', $details2["t:".$field_type]);

                                if ((!@$details["t:".$field_type] || array_search(trim_lower_accent($value), $details_lc, true)===false) //no duplications
                                &&
                                (!@$details2["t:".$field_type] || array_search(trim_lower_accent($value), $details2_lc, true)===false))
                                {
                                    $cnt = count(@$details["t:".$field_type])+1;
                                    $details["t:".$field_type][$cnt] = $value;
                                }else{
                                }
                            }

                            if($params['sa_upd']==2 && $params['sa_upd2']==1 && !@$details["t:".$field_type]["bd:delete"]
                            && !$value && $recordTypeStructure[$field_type][$idx_reqtype] != "required"){ //delete old even if new is not provided

                                $details["t:".$field_type]["bd:delete"] = "ups!";
                            }

                        }//$values

                        //if insert and require field is not defined - skip it
                        if( !($recordId>0) &&
                            $recordTypeStructure[$field_type][$idx_reqtype] == "required")
                            //!@$details["t:".$field_type][0])
                        {
//error_log($field_type.' = '.print_r(@$details["t:".$field_type],true));
                            //$is_valid_newrecord = false;
                            //break;
                        }

                    }
                }//for import data

                //END FILL DETAILS =============================

                $new_id = null;

                // || $recordId==null 
                //add - update record for 2 cases: idfield not defined, idfield is multivalue
                if(  ($id_field_not_defined || $recordId==null) && count($details)>0 ){ //id field not defined - insert for each line

                    if(!$ignore_insert){
                        $new_id = doInsertUpdateRecord($recordId, $params, $details, $id_field, $mode_output);
                        $pairs[$recordId_in_import] = $new_id;//new_A
                        $details = array();
                    }
                    

                }else if ($is_mulivalue_index){ //idfield is multivalue (now is is assummed that index field is always multivalue)
                    //THIS SECTION IS NOT USED
                    //$details = retainExisiting($details, $details2, $params, $recordTypeStructure, $idx_reqtype);
                    if(count($details)>1){
                        $new_id = doInsertUpdateRecord($recordId, $params, $details, null, $mode_output);
                        if($new_id!=null && intval($new_id)>0) array_push($new_record_ids, $new_id);
                        $previos_recordId = null;

                        if(($recordId==null || $recordId<0) && intval($new_id)>0){ //new records OR predefined id
                            $pairs[$id_field_values[$idx2]] = $new_id;
                        }
                    }else if($recordId>0){
                        array_push($new_record_ids, $recordId);
                    }
                }


                $rep_processed++;

                if ($mode_output=='html' && $rep_processed % $step == 0) {
                    ob_start();
                    if($first_time){
                        print '<script type="text/javascript">$("#div-progress").hide();</script>';
                        $first_time = false;
                    }
                    print '<script type="text/javascript">update_counts('.$rep_processed.','.$rep_added.','.$rep_updated.','.$tot_count.')</script>'."\n";
                    ob_flush();
                    flush();
                }
            }//foreach multivalue index

            
            if($is_mulivalue_index && is_array($new_record_ids) && count($new_record_ids)>0){
                //save record ids in import table
                updateRecIds($import_table, end($row), $id_field, $new_record_ids, $csv_mvsep);
                $new_record_ids = array(); //to save in import table
            }

        }//main  all recs in import table
        $res->close();

        if($id_field && count($details)>0 && !$is_mulivalue_index && $recordId!=null){ //action for last record
            //$details = retainExisiting($details, $details2, $params, $recordTypeStructure, $idx_reqtype);
            $new_id = doInsertUpdateRecord($recordId, $params, $details, $id_field, $mode_output);
        }
        if(!$id_field){
            array_push($imp_session['uniqcnt'], $rep_added);
        }
        
        $import_report = array(                
              'processed'=>$rep_processed,
              'inserted'=>$rep_added,
              'updated'=>$rep_updated,
              'total'=>$tot_count,
              'skipped'=>$rep_skipped,
              'permission'=>$rep_permission
            );   

        //update counts array                
        $new_counts = array( $rep_updated+$rep_added, $rep_processed, 0,0 );
                                                                        
        if($ignore_insert){
            if($use_sequence){
                $prev_counts = @$imp_session['sequence'][$currentSeqIndex]['counts'];
            }else if(@$imp_session['counts'][$recordType]){
                $prev_counts = $imp_session['counts'][$recordType];
            }
            if(is_array($prev_counts) && count($prev_counts)==4){
                $new_counts[2] = $prev_counts[2];
                $new_counts[3] = $prev_counts[3];
            }
        }
        
        if($use_sequence){
            $imp_session['sequence'][$currentSeqIndex]['counts'] = $new_counts;         
        }else{
            if(!@$imp_session['counts']) $imp_session['counts'] = array();
            $imp_session['counts'][$recordType] = $new_counts;
        }
        
                                                                         
        if($mode_output!='array'){

            print '<script type="text/javascript">update_counts('.$rep_processed.','.$rep_added.','.$rep_updated.','.$tot_count.')</script>'."\n";
            if($rep_skipped>0){
                print '<p>'.$rep_skipped.' rows skipped</p>';
            }
        }

    }

    //save mapping into import_sesssion
    if($use_sequence){
        $imp_session['sequence'][$currentSeqIndex]['mapping_flds'] = $mapping;         
    }else{
        //old way
        if(!@$imp_session['mapping_flds']){
            $imp_session['mapping_flds'] = array();
        }
        $imp_session['mapping_flds'][$recordType] = $mapping;
    }
    
    $imp_session['import_report'] = $import_report;

    return saveSession($mysqli, $imp_session);
} //end doImport


//
// assign real record ID into import (source) table
//
function updateRecIds($import_table, $imp_id, $id_field, $newids, $csv_mvsep){
    global $mysqli;

    if(is_array($newids) && count($newids)>0){
    
    $newids = "'".implode($csv_mvsep, $newids)."'";

    $updquery = "UPDATE ".$import_table
    ." SET ".$id_field."=".$newids
    ." WHERE imp_id = ". $imp_id;

    if(!$mysqli->query($updquery)){
        print "<div style='color:red'>Cannot update import table (set record id ".$newids.") for line:".$imp_id.".</div>";
    }
    
    }
}


//    REMOVE - NOT USED
//  Add and replace all existing value(s) for the record with new data
//  Retain existing if no new data supplied for record
//  details2 - original
//  details - new
//
function retainExisiting($details, $details2, $params, $rectypeStruc, $idx_reqtype){


    print "NEW ".print_r($details, true)."<br><br>";
    print "original ".print_r($details2, true)."<br><br>";

    if($params['sa_upd']==2){ //Add and replace all existing value(s) for the record with new data

        foreach ($details2 as $field_type => $pairs) {
            if(substr($field_type,0,2)!="t:") continue;
            if( @$details[$field_type] ){ //replace field type

                //array_unshift($details[$field_type], array("bd:delete"=>"ups!"));

                $details3 = array("bd:delete"=>"ups!");
                foreach ($details[$field_type] as $idx => $value) { //replace 1=>val to bd:id=>val
                    $details3[$idx] = $value;
                }

                /*
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
                */

                $details[$field_type] = $details3;

            }else {   //no new data supplied for record
                /*
                if($params['sa_upd2']==1 && $rectypeStruc[$field_type][$idx_reqtype] != "required"){ //delete if no new data provided
                $details[$field_type] = array("bd:delete"=>"ups!");
                }else{
                //$details[$field_type] = $details2[$field_type]; //retain
                }
                */
            }
        }
    }

    print "result ".print_r($details, true)."<br><br>";

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
function doInsertUpdateRecord($recordId, $params, $details, $id_field, $mode_output){

    global $mysqli, $imp_session, $rep_processed, $rep_skipped, $rep_permission,
            $wg_id,$rec_visibility, $rep_added, $rep_updated, $rep_unique_ids;

    $import_table = $imp_session['import_table'];
    $recordType = @$params['sa_rectype'];
    //$id_field = @$params['recid_field']; //record ID field is always defined explicitly

    //check permission beforehand
    if($recordId>0){
        $res = checkPermission($recordId, $wg_id);            
        if($res!==true){
            // error message: $res;
            $rep_permission++;
            return null;
        }
    }

    $nonces = null;
    $rectile_recs = null;
    //add-update Heurist record
    $out = saveRecord($recordId, $recordType,
        @$details["recordURL"],
        @$details["recordNotes"],
        $wg_id, //???get_group_ids(), //group
        $rec_visibility, //viewable
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
        null, //+comment
        $nonces, //nonces
        $rectile_recs, //retitle recs 
        2 //save as it is without verification of record type structure
    );

//error_log('to Add '.print_r($details,true));
//error_log('ADD REC '.print_r($out,true));

    if (@$out['error']) {

        //special formatting
        foreach($out["error"] as $idx=>$value){
            $value = str_replace(". You may need to make fields optional. Missing data","",$value);
            $k = strpos($value, "Missing data for Required field(s) in");
            if($k!==false){
                $value = "<span style='color:red'>".substr($value,0,$k+37)."</span>".substr($value,$k+37);
            }else{
                $value  = "<span style='color:red'>".$value."</span>";
            }
            $out["error"][$idx] = $value;
        }
        if ($mode_output!='array'){
            foreach($details['imp_id'] as $imp_id){
                print "<div><span style='color:red'>Line: ".$imp_id.".</span> ".implode("; ",$out["error"]);
                $res = get_import_value($imp_id, $import_table);
                if(is_array($res)){
                    $s = htmlspecialchars(implode(", ", $res));
                    print "<div style='padding-left:40px'>".$s."AAAA</div>";
                }
                print "</div>";
            }
        }

        $rep_skipped++;
        $out["bibID"] = null;
    }else{

        
        if($recordId!=$out["bibID"]){ //}==null){
            $rep_added++;
            $rep_unique_ids[] = $out["bibID"];
        }else{
            //do not count updates if this record was already inserted with doImport
            if(!in_array($out["bibID"], $rep_unique_ids)){
                $rep_unique_ids[] = $out["bibID"];
                $rep_updated++;  
            } 
        }
        
        //change record id in import table from negative temp to id form Huerist records (for insert)        
        if($id_field){ // ($recordId==null || $recordId>0)){
                $updquery = "UPDATE ".$import_table
                ." SET ".$id_field."=".$out["bibID"]
                ." WHERE imp_id in (". implode(",",$details['imp_id']) .")";

                if(!$mysqli->query($updquery) && $mode_output!='array'){
                    print "<div style='color:red'>Cannot update import table (set record id ".$out["bibID"].") for lines:".
                    implode(",",$details['imp_id'])."</div>";
                }
        }



        if (@$out['warning'] && $mode_output!='array') {
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

    $imp_id = mysql__insertupdate($mysqli, "sysImportFiles", "sif",
        array("sif_ID"=>@$imp_session["import_id"],
            "sif_UGrpID"=>get_user_id(),
            "sif_TempDataTable"=>$imp_session["import_name"],
            "sif_ProcessingInfo"=>json_encode($imp_session) ));

    if(intval($imp_id)<1){
        return "Cannot save session. SQL error:".$imp_id;
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
    return $res;
}


/**
* remove unmatched records from import data
*
* @param mixed $session_id
* @param mixed $idfield
*/
function delete_unmatched_records($session_id, $idfield){

    global $mysqli;

    $session = get_import_session($mysqli, $session_id);

    if(!is_array($session)){
        print $session;
        return;
    }

    $import_table = $session['import_table'];

    $query = " DELETE FROM ".$import_table
    ." WHERE ".$idfield."<0";

    $res = $mysqli->query($query);
    if($res){
        //read content of tempfile and send it to client
        print $mysqli->affected_rows;

        $query = " SELECT count(*) FROM ".$import_table;
        $row = mysql__select_array2($mysqli, $query);
        if($row && $row[0]>0){
            $session['reccount'] = $row[0];
            saveSession($mysqli, $session);
        }

    }else{
        print "SQL error: ".$mysqli->error;
    }


}


/**
* download seesion data into file
*
* @param mixed $mysqli
* @param mixed $import_id
* @param mixed $mode - 0 matched, 1 unmatched
* @return mixed
*/
function download_import_session($session_id, $idfield=null, $mode=1){

    global $mysqli;

    $ret = "";
    $where = "";
    if(is_numeric($session_id)){
        $where = " where sif_ID=".$session_id;
    }else{
        print "session id is not defined";
        return;
    }

    $res = mysql__select_array2($mysqli,
        "select sif_ProcessingInfo  from sysImportFiles".$where);

    //get field names and original filename
    $session = json_decode($res[0], true);

    $columns = $session['columns'];
    $import_table = $session['import_table'];
    $list = array();
    $headers = array();

    foreach ($columns as $idx=>$column) {
        array_push($list, " field_".$idx);
        array_push($headers, '"'.str_replace('"','\\"',$column).'"');
    }


    //export content of import table into tempfile
    $tmpFile = tempnam(HEURIST_SCRATCHSPACE_DIR, 'export');
    $tmpFile = $tmpFile."1";
    if(strpos($tmpFile,"\\")>0){
        $tmpFile = str_replace("\\","/",$tmpFile);
    }

    ///ARTEM: issue MySQL server is on different machine. It saves temp file on this machine. This file is not accessible from 
    // web server
    
    $query = "SELECT ".implode(',',$headers)
    ." UNION ALL "
    ." SELECT ".implode(",",$list)." FROM ".$import_table
    .($idfield!=null?" WHERE ".$idfield.($mode==0?">0":"<0"):"")
    ." INTO OUTFILE '".$tmpFile."'"
    ." FIELDS TERMINATED BY ','"
    ." ENCLOSED BY '\"'"
    ." LINES TERMINATED BY '\n' ";


    $res = $mysqli->query($query);
    if($res){
        //read content of tempfile and send it to client
        header('Content-type: text/csv');
        readfile($tmpFile);
    }else{
        print "File cannot be downloaded. SQL error: ".$mysqli->error;
    }
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
        $where = " where sif_ID=".$session_id;
    }

    $res = mysql__select_array3($mysqli,
        "select sif_ID, sif_ProcessingInfo  from sysImportFiles".$where);

    if(!$res){
        $ret = "cannot get list of imported files";
    }else{

        foreach($res as $id => $session){

            $session = json_decode($session, true);
            $query = "drop table IF EXISTS ".$session['import_table'];

            if (!$mysqli->query($query)) {
                $ret = "cannot drop table: " . $mysqli->error;
                break;
            }
        }

        if($ret==""){
            if($where==""){
                $where = " where sif_ID>0";
            }

            if (!$mysqli->query("delete from sysImportFiles ".$where)) {
                $ret = "cannot delete data from list of imported files: " . $mysqli->error;
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
            "select sif_ProcessingInfo , sif_TempDataTable from sysImportFiles where sif_ID=".$import_id);

        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        $session["import_file"] = $res[1];
        if(!@$session["import_table"]){ //backward capability
            $session["import_table"] = $res[1];
        }

        return $session;
    }else{
        return "Cannot load imported file #".$import_id;
    }
}

/**
* Loads all sessions for current user (to populate dropdown)
*/
function get_list_import_sessions(){

    global $mysqli;

    /*
    $query = "CREATE TABLE IF NOT EXISTS `sysImportFiles` (
    `imp_ID` int(11) unsigned NOT NULL auto_increment,
    `ugr_ID` int(11) unsigned NOT NULL default 0,
    `imp_table` varchar(255) NOT NULL default '',
    `imp_session` text,
    PRIMARY KEY  (`imp_ID`))";
    */
    
    $query = "CREATE TABLE IF NOT EXISTS `sysImportFiles` (
    `sif_ID` int(11) unsigned NOT NULL auto_increment
    COMMENT 'Sequentially generated ID for delimited text or other files imported into temporary tables ready for processing',
    `sif_FileType` enum('delimited') NOT NULL Default 'delimited' COMMENT 'The type of file which has been read into a temporary table for this import',   
    `sif_UGrpID` int(11) unsigned NOT NULL default 0 COMMENT 'The user ID of the user who imported the file',   
    `sif_TempDataTable` varchar(255) NOT NULL default '' COMMENT 'The name of the temporary data table created by the import',
    `sif_ProcessingInfo` text  COMMENT 'Primary record type, field matching selections, dependency list etc. created while processing the temporary data table',
    PRIMARY KEY  (`sif_ID`))";    
    
    if (!$mysqli->query($query)) {
        return "SQL error: cannot create imported files table: " . $mysqli->error;
    }

    $ret = '<option value="0">select uploaded file ...</option>';
    $query = "select sif_ID, sif_TempDataTable from sysImportFiles"; //" where sif_UGrpID=".get_user_id();
    $res = $mysqli->query($query);
    if ($res){
        while ($row = $res->fetch_row()){
            $ret = $ret.'<option value="'.$row[0].'">'.$row[1].'</option>';
        }
        $res->close();
    }
    return $ret;
}


//
// print list of load data warnings
//
function renderWarnings($imp_session){

    $columns = $imp_session['columns'];

    $warnings = $imp_session['load_warnings'];
    foreach ($warnings as $line) {

        //replace field names in table to human column names from import file
        while (strpos($line, "field_")>0) {
            $k = strpos($line, "field_");
            $field_name = substr($line, $k, strpos($line, "'", $k)-$k);
            $idx = intval(substr($field_name, 6));
            $column_name = $columns[$idx];
            $line = str_replace($field_name, $column_name, $line);
        }

        print $line."<br />";
    }
    print '<br /><br /><input type="button" value="Close popup" onClick="showRecords(\'mapping\');">';
}

//
//
//
function renderDisambiguation($type, $imp_session){

    $records = $imp_session['validation']['disambiguation'];

    if(count($records)>25)
        print '<br/><input type="button" value="Close popup" onClick="showRecords(\'mapping\');"><br/><br/>';

    print '<div>The following rows match with multiple records. This may be due to the existence of duplicate records in your database,'.
    ' but you may not have chosen all the fields required to correctly disambiguate the incoming rows'.
    ' against existing data records.</div>';
    print '<br/><br/>';

    print '<table class="tbmain"  cellspacing="0" cellpadding="2" width="100%"><thead><tr><th>Key values</th><th>Count</th><th>Records in Heurist</th></tr>';


    foreach($records as $keyvalue =>$disamb){

        $value = explode($imp_session['csv_mvsep'], $keyvalue);
        array_shift($value); //remove first element
        $value = implode(";&nbsp;&nbsp;",$value); //keyvalue

        print '<tr><td>'.$value.'</td><td>'.count($disamb).'</td><td>';

        print '<input type="hidden" name="disamb_key[]" value="'.addslashes($keyvalue).'"/><select name="disamb_id[]">';

        foreach($disamb as $rec_ID =>$rec_Title){
            print '<option value="'.$rec_ID.'">[rec# '.$rec_ID.'] '.$rec_Title.'</option>';
        }

        print '</select>&nbsp;';
        print '<a href="#" onclick="{window.open(\''.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
        '&q=ids:'.implode(",", array_keys($disamb)).'\', \'_blank\');}">view records</a></td></tr>';
    }

    print "</table><br />";
    print '<div>Please select from the possible matches in the dropdowns. You may not be able to determine the correct records if you have used '.
    'an incomplete set of fields for matching.</div>';
    print '<div>Click "Continue" to assign IDs</div><br/>';
    print '<input type="button" value="Close popup" onClick="showRecords(\'mapping\');">'.
    '&nbsp;&nbsp;<input type="button" value="Continue" onClick="doMatchingAfterDisambig()">';

}


//
// render the list of records as a table
//
function renderRecords($type, $imp_session){

    if($type=='error'){
        renderRecordsError($imp_session);
        return;
    }

    $is_missed = false;

    if($type=='error'){
        $tabs = $imp_session['validation']['error'];
    }else{

        $rec_tab = array();
        $rec_tab['count_'.$type] = $imp_session['validation']['count_'.$type];
        $rec_tab['recs_'.$type] = $imp_session['validation']['recs_'.$type];
        $tabs = array($rec_tab);
    }

    if(count($tabs)>1){
        $k = 0;

        print '<div id="tabs_records"><ul>';
        foreach($tabs as $rec_tab){
            $colname = @$imp_session['columns'][substr($rec_tab['field_checked'],6)];

            print '<li><a href="#rec__'.$k.'" style="color:red">'.$colname.'<br><span style="font-size:0.7em">'.$rec_tab['short_message'].'</span></a></li>';
            $k++;
        }
        print '</ul>';
        $k++;
    }

    $k = 0;

    foreach($tabs as $rec_tab)
    {
        print '<div id="rec__'.$k.'">';
        $k++;

        $cnt = $rec_tab['count_'.$type];
        $records = $rec_tab['recs_'.$type];
        $mapped_fields = $imp_session['validation']['mapped_fields'];

        if($cnt>count($records)){
            print "<div class='error'><b>Only the first ".count($records)." of ".$cnt." rows are shown</b></div>";
        }

        if($type=="error"){
            $checked_field  = $rec_tab['field_checked'];
            if(in_array(intval(substr($checked_field ,6)), $imp_session['multivals'])){
                $checked_field = null; //highlight errors individually
            }

            print "<div><span class='error'>Values in red are invalid: ";
            print "</span> ".$rec_tab['err_message']."<br/><br/></div>";

            $is_missed = (strpos($rec_tab['err_message'], 'a value must be supplied')>0);
        }else{
            $checked_field = null;
        }

        if(count($records)>25)
            print '<br/><input type="button" value="Close popup" onClick="showRecords(\'mapping\');"><br/><br/>';


        //all this code only for small asterics
        //$recStruct = getAllRectypeStructures(true);
        //$recStruct = $recStruct['typedefs'][]
        $recordType = @$_REQUEST['sa_rectype'];
        if($recordType){
            $recStruc = getRectypeStructures(array($recordType));
            $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
            $recStruc = $recStruc[$recordType]['dtFields'];
        }else{
            $recStruc = null;
        }


        $detDefs = getAllDetailTypeStructures(true);
        $detLookup = $detDefs['lookups'];
        $detDefs = $detDefs['typedefs'];
        $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];

        //find distict terms values
        if($type=="error" && !$is_missed){
            $is_enum = false;
            $err_col = 0;
            $m = 0;
            foreach($mapped_fields as $field_name=>$dt_id) {
                if($field_name==$checked_field){
                    $err_col = $m;
                    $dttype = $detDefs[$dt_id]['commonFields'][$idx_dt_type];
                    $is_enum = ($dttype=="enum" || $dttype=='relationtype');
                    break;
                }
                $m++;
            }

            if($is_enum){
                $distinct_value = array();
                if($records && is_array($records)) {
                    foreach ($records as $row) {
                        $value = $row[$err_col];
                        if(!in_array($value, $distinct_value)){
                            array_push($distinct_value, $value);
                        }
                    }
                }

                if(count($distinct_value)>0){
                    //print distinct term values
                    print '<div style="display:none;padding-bottom:10px;overflow:auto" id="distinct_terms_'.$k.'"><br>';
                    foreach ($distinct_value as $value) {
                        print '<div style="margin-left:30px;">'.$value.' </div>';
                    }
                    print '</div>';
                    print '<div><a href="#" onclick="{top.HEURIST.util.popupTinyElement(window, document.getElementById(\'distinct_terms_'.
                    $k.'\'),{\'no-close\':false, \'no-titlebar\':false });}">Get list of unrecognised terms</a> '.
                    '(can be imported into terms tree)<br/>&nbsp;</div>';
                }
            }
        }//end find distict terms values

        print '<table class="tbmain"  cellspacing="0" cellpadding="2" width="100%"><thead><tr>'; // class="tbmain"

        if($type=="update"){
            print '<th width="30px">Record ID</th>';
        }
        print '<th width="20px">Line #</th>';


        //HEADER
        $m=1;
        $err_col = 0;
        foreach($mapped_fields as $field_name=>$dt_id) {

            $colname = @$imp_session['columns'][substr($field_name,6)];

            if(@$recStruc[$dt_id][$idx_reqtype] == "required"){
                $colname = $colname."*";
            }

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

        //BODY
        if($records && is_array($records)) {
            foreach ($records as $row) {

                print "<tr>";
                if(is_array($row)){
                    $m=0;
                    foreach($row as $value) {
                        $value = ($value?$value:"&nbsp;");
                        if($err_col>0 && $err_col==$m){
                            if($is_missed){
                                $value = "&lt;missing&gt;";
                            }
                            $value = "<font color='red'>".$value."</font>";
                        }
                        print "<td class='truncate'>";

                        print $value."</td>";

                        $m++;
                    }
                }
                print "</tr>";
            }
        }
        print "</table>";
        print "</div>";
    }//tabs

    if(count($tabs)>1){
        print '</div>';
    }

    print '<br /><br /><input type="button" value="Close popup" onClick="showRecords(\'mapping\');">';

}


//
// render the list of records as a table
//
function renderRecordsError($imp_session){

    $is_missed = false;

    $tabs = $imp_session['validation']['error'];

    if(count($tabs)>1){
        $k = 0;

        print '<div id="tabs_records"><ul>';
        foreach($tabs as $rec_tab){
            $colname = @$imp_session['columns'][substr($rec_tab['field_checked'],6)];

            print '<li><a href="#rec__'.$k.'" style="color:red">'.$colname.'<br><span style="font-size:0.7em">'.@$rec_tab['short_message'].'</span></a></li>';
            $k++;
        }
        print '</ul>';
        $k++;
    }

    $mapped_fields = $imp_session['validation']['mapped_fields'];

    $k = 0;

    foreach($tabs as $rec_tab)
    {
        print '<div id="rec__'.$k.'">';
        $k++;

        $cnt = $rec_tab['count_error'];
        $records = $rec_tab['recs_error'];

        if($cnt>count($records)){
            print "<div class='error'><b>Only the first ".count($records)." of ".$cnt." rows are shown</b></div>";
        }

        $checked_field  = $rec_tab['field_checked'];


        if(in_array(intval(substr($checked_field ,6)), $imp_session['multivals'])){
            $ismultivalue = true;     //highlight errors individually
        }else{
            $ismultivalue = false;
        }
        print "<div><span class='error'>Values in red are invalid: ";
        print "</span> ".$rec_tab['err_message']."<br/><br/></div>";

        $is_missed = (strpos($rec_tab['err_message'], 'a value must be supplied')>0);

        if(count($records)>25)
            print '<br/><input type="button" value="Close popup" onClick="showRecords(\'mapping\');"><br/><br/>';


        //all this code only for small asterics
        //$recStruct = getAllRectypeStructures(true);
        //$recStruct = $recStruct['typedefs'][]
        $recordType = @$_REQUEST['sa_rectype'];
        if($recordType){
            $recStruc = getRectypeStructures(array($recordType));
            $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
            $recStruc = $recStruc[$recordType]['dtFields'];
        }else{
            $recStruc = null;
        }


        $detDefs = getAllDetailTypeStructures(true);
        $detLookup = $detDefs['lookups'];
        $detDefs = $detDefs['typedefs'];
        $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];

        //find distinct terms values
        $is_enum = false;
        if(!$is_missed){
            $err_col = 0;
            $m = 1;
            foreach($mapped_fields as $field_name=>$dt_id) {
                if($field_name==$checked_field && @$detDefs[$dt_id]){
                    $err_col = $m;

                    $dttype = $detDefs[$dt_id]['commonFields'][$idx_dt_type];
                    $is_enum = ($dttype=='enum' || $dttype=='relationtype');
                    break;
                }
                $m++;
            }

            if($is_enum){
                $distinct_value = array();
                if($records && is_array($records)) {
                    foreach ($records as $row) {
                        $value = $row[$err_col];
                        if(!in_array($value, $distinct_value)){
                            array_push($distinct_value, $value);
                        }
                    }
                }

                if(count($distinct_value)>0){
                    //print distinct term values
                    print '<div style="display:none;padding-bottom:10px;" id="distinct_terms_'.$k.'"><br>';
                    foreach ($distinct_value as $value) {
                        print '<div style="margin-left:30px;">'.$value.' </div>';
                    }
                    print '</div>';
                    print '<div><a href="#" onclick="{top.HEURIST.util.popupTinyElement(window, document.getElementById(\'distinct_terms_'.
                    $k.'\'),{\'no-close\':false, \'no-titlebar\':false });}">Get list of unrecognised terms</a>'.
                    ' (can be imported into terms tree)<br/>&nbsp;</div>';
                }
            }

        }//end find distict terms values

        print '<table class="tbmain"  cellspacing="0" cellpadding="2" width="100%"><thead><tr>'; // class="tbmain"

        print '<th width="20px">Line #</th>';

        //HEADER - only error field
        $m=1;
        $err_col = 0;

        foreach($mapped_fields as $field_name=>$dt_id) {

            if($field_name==$checked_field){

                $colname = @$imp_session['columns'][substr($field_name,6)];

                if(@$recStruc[$dt_id][$idx_reqtype] == "required"){
                    $colname = $colname."*";
                }
                if($is_enum){
                    $showlink = '&nbsp;<a href="#" onclick="{showTermListPreview('.$dt_id.')}">show list of terms</a>';
                }else{
                    $showlink = '';
                }

                $colname = "<font color='red'>".$colname."</font>"
                .'<br><font style="font-size:10px;font-weight:normal">'
                .(is_numeric($dt_id) ?$detLookup[$detDefs[$dt_id]['commonFields'][$idx_dt_type]] :$dt_id).$showlink."</font>";

                if($is_enum){
                    $colname = $colname."<div id='termspreview".$dt_id."'></div>"; //container for
                }

                print "<th style='min-width:90px'>".$colname."</th>";
                $err_col = $m;
                break;
            }
            $m++;
        }

        //raw row
        print '<th>Record content</th>';
        print "</tr></thead>";


        $import_table = $imp_session['import_table'];
        //BODY
        if($records && is_array($records)) {
            foreach ($records as $row) {

                print "<tr>";
                if(is_array($row)){
                    print "<td class='truncate'>".$row[0]."</td>";
                    if($is_missed){
                        print "<td style='color:red'>&lt;missing&gt;</td>";
                    } else if($ismultivalue){
                        print "<td class='truncate'>".@$row[$err_col]."</td>";
                    } else {
                        print "<td class='truncate' style='color:red'>".@$row[$err_col]."</td>";
                    }
                    // print raw content of import data
                    $res = get_import_value($row[0], $import_table);
                    if(is_array($res)){
                        $s = htmlspecialchars(implode(", ", $res));
                        print "<td title='".$s."'>".$s."</td>";
                    }else{
                        print "<td>&nbsp;</td>";
                    }
                }
                print "</tr>";
            }
        }
        print "</table>";
        print "</div>";
    }//tabs

    if(count($tabs)>1){
        print '</div>';
    }

    print '<br /><br /><input type="button" value="Back to previous screen" onClick="showRecords(\'mapping\');">';

}


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


?>
