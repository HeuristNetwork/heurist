<?php
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");

//a couple functions from h4/utils_db.php
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

//DEBUG print $query."<br>";

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
* take settings from $_REQUEST
* 
* @param mixed $mysqli
*/
function validateImport($mysqli, $imp_session, $params){
 
    $imp_session['validation'] = array( "rec_to_update"=>0, "rec_to_add"=>0, "rec_error"=>null, "err_message"=>null );  
  
    //rectype to import
    $recordType = @$params['sa_rectype'];
    $is_secondary = ($params['sa_type']==1);
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    $import_table = $imp_session['import_table'];
    $is_update = false;
    $rt_field = null;
    
    //get field mapping and selection query from params 
    $mapping = array();  //keeps fieldtypes for fields in import table
    $sel_query = array();
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_dt_")===0 && $field_type){
            //get index 
            $index = substr($key,6);
            $field_name = "field_".$index;
            
            if($field_type=="id"){
                $imp_session['indexes'][$recordType] = $field_name;
            }else {
                array_push($sel_query, $field_name);    
                array_push($mapping, $field_type);
                $imp_session["mapping"][$field_name] = $recordType.".".$field_type;
            }
        }
    }  
  
    //detect rectype in indexes 
    if(@$imp_session['indexes'][$recordType]){ //exists - it means update
        
            $rt_field = $imp_session['indexes'][$recordType];
            $is_update = true;
            
     }else{ //insert
            //add new field in import table - to keep key values (primary index)
            /*
            $rt_field = "field_".count($imp_session['columns']);
            array_push($imp_session['columns'], $recordType."  index" );
            array_push($imp_session['uniqcnt'], 0);
            if(!$is_secondary) $imp_session["mapping"][$rt_field] = $recordType.".id";
            $imp_session['indexes'][$recordType] = $rt_field;
            */
    }
    if(count($sel_query)<1){
        return "mapping not defined";
    }
    
    array_push($mapping, "id");
    $select_query = "select ".($is_secondary?" distinct ":""). implode(",",$sel_query) 
        . ", imp_id from ".$import_table;
        
        //"," . $rt_field.

  //    
  $recStruc = getRectypeStructures(array($recordType));  
    
 $missed = array();
 $query_reqs = array();
 $query_reqs_where = array();

 $query_enum = array();
 $query_enum_join = "";
 $query_enum_where = array();

 $query_res = array();
 $query_res_join = "";
 $query_res_where = array();
 
 $query_num = array();
 $query_num_where = array();
 
 $query_date = array();
 $query_date_where = array();
 
 $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
 $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
 foreach ($recStruc[$recordType]['dtFields'] as $ft_id => $ft_vals) {
     
    //find among mappings
    $field_name = array_search($recordType.".".$ft_id, $imp_session["mapping"], true);
     
    if($ft_vals[$idx_reqtype] == "required"){
        if(!$field_name){
            array_push($missed, $ft_vals[0]);
        }else{
            array_push($query_reqs, $field_name);
            array_push($query_reqs_where, $field_name." is null or ".$field_name."=''");
        }
    }
    
    if($field_name){
        
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
     $wrong_records = getWrongRecords($mysqli, $query, "required fields", $imp_session, $query_reqs);
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

function getWrongRecords($mysqli, $query, $message, $imp_session, $fields_checked){
    
    $res = $mysqli->query($query);
    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){
            array_push($wrong_records, $row);
        }        
        $res->close();
        if(count($wrong_records)>0){
            $imp_session['validation']["rec_error"] = $wrong_records;
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
    $is_secondary = ($params['sa_type']==1);
    
    if(intval($recordType)<1){
        return "record type not defined";
    }
    
    $import_table = $imp_session['import_table'];
    $is_update = false;
    $rt_field = null;
    
    //get field mapping and selection query from params 
    $mapping = array();  //keeps fieldtypes for fields in import table
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
            }
            
            
        }
    }

    //detect rectype in indexes 
    if(@$imp_session['indexes'][$recordType]){ //exists - it means update
        
            $rt_field = $imp_session['indexes'][$recordType];
            $is_update = true;
            
     }else{ //insert
            //add new field in import table - to keep key values (primary index)
            $rt_field = "field_".count($imp_session['columns']);
            array_push($imp_session['columns'], $recordType."  index" );
            array_push($imp_session['uniqcnt'], 0);
            if(!$is_secondary) $imp_session["mapping"][$rt_field] = $recordType.".id";
            $imp_session['indexes'][$recordType] = $rt_field;
        
            $altquery = "alter table ".$import_table." add column ".$rt_field." int(10) unsigned";
            if (!$mysqli->query($altquery)) {
                return "can not alter import session table, can not add new index field: " . $mysqli->error;
            }    
    }
    if($sel_query==""){
        return "mapping not defined";
    }
    
    array_push($mapping, "id");
    $sel_query = "select ".($is_secondary?" distinct ":""). $sel_query . $rt_field.", imp_id from ".$import_table;
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
                    $details["t:".$field_type] = array("1"=>$row[$index]);
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
            
            $out = array("bibID"=>null);
            
            if (@$out['error']) {
                //print "<div style='color:red'>Error: ".implode("; ",$out["error"])."</div>";
                return "can not insert record ".implode("; ",$out["error"]);
            }else{
                if($recordId==null){
                    
                    $updquery = "update ".$import_table." set ".$rt_field."=".$out["bibID"]." where imp_id=".$row[count($row)-1];
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
            array("imp_id"=>$imp_session["import_id"], "imp_session"=>json_encode($imp_session) ));    
    if(intval($imp_id)<1){
        return "can not save session";
    }else{
        return $imp_session;
    }
}


?>
