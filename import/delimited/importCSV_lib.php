<?php
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");

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

// import functions

/**
* take settings from $_REQUEST
* 
* @param mixed $mysqli
*/
function validateImport($mysqli, $imp_session, $params){
 
  $validationRes = array( "rec_to_update"=>0, "rec_to_add"=>0, "rec_error"=>0 );  
    
 //1. Verify that all required field are mapped
 
 //2. In DB: Verify that all required fields have values
 
 //3. In DB: Verify that enumeration fields have correct values
 
 //4. In DB: Verify resource fields
 
 //5. Verify numeric fields
 
 //6. Verify datetime fields
 
 //7. TODO Verify geo fields
 

 return $validationRes;   
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
    $tot_count = $imp_session['reccount'];
    $is_update = false;
    $rt_field = null;
    
    //get field mapping mapping
    $mapping = array();  //field index in import table TO field type
    $query = "";
    foreach ($params as $key => $field_type) {
        if(strpos($key, "sa_dt_")===0 && $field_type){
            //get index 
            $index = substr($key,6);
            $field_name = "field_".$index;
            
            if($field_type=="id"){
                $imp_session['indexes'][$recordType] = $field_name;
            }else {
                $query = $query.$field_name.", ";    
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
    if($query==""){
        return "mapping not defined";
    }
    
    array_push($mapping, "id");
    $query = "select ".($is_secondary?" distinct ":"").$query.$rt_field.", imp_id from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        
        return "import table is empty";
        
    }else{
        
        $rep_processed = 0;
        $rep_added = 0;
        $rep_updated = 0;
        $errors = array();
        $warnings = array();

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
            /* temp
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
            */
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
            array("imp_id"=>$imp_session["import_id"], "imp_table"=>$import_table ,"imp_session"=>json_encode($imp_session) ));    
    if(intval($imp_id)<1){
        return "can not save session";
    }else{
        return $imp_session;
    }
            
}


?>
