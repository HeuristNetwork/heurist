<?php
/**
* recordsDupes.php
* 
* Searches for duplicates by header or detail fields
* It uses metaphone, levenshtein (or @todo text_similar methods)
* 
* Controller is record_verify
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

/**
* 
*  setSession - work with different database
*  findDupes  - main method
* 
*/
class RecordsDupes {
    private function __construct() {}    
    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;
    
    private static $defDetailtypes = null;
    
    private static $cache_id;
    private static $cache_str;
    
//
//
//    
private static function initialize()
{
    if (self::$initialized)
        return;

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
    
    //check existense NEW_LIPOSUCTION NEW_LEVENSHTEIN
    checkDatabaseFunctionsForDuplications(self::$mysqli);
}

//
// set session different that current global one (to work with different database)
//
public static function setSession($system){
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
}

//
// Main method - finds duplications
//
// $params: 
//    mode - levenshtein or metaphone
//    rty_ID 
//    fields - comma separated list or array of dty_IDs and header fields (rec_Title by default)
//    dista1nce - 0 exact duplication (by default), or percent of strlen.                     1
//      So 5% for 100 characters distance is 5 chars (strlen*d/100) 
//      for strlen<50  distance = 3 chars
//      for strlen<10  distance = 1
//      if defined, min distance is 1, max = 10
//
public static function findDupes( $params ){

    self::initialize();
    
    $rty_ID = @$params['rty_ID'];
    if(!($rty_ID>0)){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Required parameter rty_ID is missed. Define record type ID');
        return false;
    }
    
    $in_memory_limit = 10000;
    
    $distance = @$params['distance'];
    if(!($distance>0)) $distance = 0;
    if($distance>20) $distance = 20; //percentage
    
    $fields = @$params['fields']; 
    if(!$fields) $fields = 'rec_Title'; //by default
    
    if(!is_array($fields)){
        $fields = explode(',', $fields);
    }
    
    //for record header field (rec_Title)
    $header_fields = array(''); //for retrieve query
    $header_fields2 = array();  //for compare query
    //for detail fields
    $detail_joins = array();
    $detail_fields = array('');
    $detail_fields2 = array();
    
    $dty_IDs = array(); //field types

    foreach($fields as $v){
        if(is_numeric($v) && $v>0){
            $dty_IDs[] = $v;
        }
    }
    
    //get field types
    $dty_IDs = mysql__select_assoc2(self::$mysqli, 'select dty_ID, dty_Type from defDetailTypes where dty_ID in ('
                .implode(',',$dty_IDs).')');

    
    foreach($fields as $v){
        if(is_numeric($v) && $v>0){
            $p = 'd'.$v;
            $detail_joins[] = ' left join recDetails '.$p.' on rec_ID='.$p.'.dtl_RecID and '.$p.'.dtl_DetailTypeID='.$v;
            
            $s = 'NEW_LIPOSUCTION(IFNULL('.$p.'.dtl_Value,""))';
            $detail_fields[$v] = $s.' as '.$p; //for retrieve       
            $detail_fields2[$v] = $s;
        }else if (strpos($v,'rec_')===0){
            $s = 'NEW_LIPOSUCTION(IFNULL('.$v.',""))';
            $header_fields[$v] = $s.' as '.$v; //for retrieve       
            $header_fields2[$v] = $s; //for compare query
        }
    }
    
    $search_params = 'i'; //for recid
    $search_where = array('(rec_ID>?)');
    
    $compare_fields = array();
    $exact_fields = array();
    foreach($fields as $v){     
        
             if($v=='rec_Title' || $v=='rec_URL' || $v=='rec_ScratchPad'){
                 if($distance>0){
                    $compare_fields[] = $header_fields2[$v];
                 }else{
                    $exact_fields[] = $header_fields[$v];        
                    $search_where[] = '('.$v.'=?)';  //$header_fields2[
                    $search_params = $search_params.'s';
                 }
             }else if($distance>0 && (@$dty_IDs[$v]=='freetext' || @$dty_IDs[$v]=='blocktext')){
                    $compare_fields[] = $detail_fields2[$v];
             }else{
                    $exact_fields[] = $detail_fields[$v];        
                    $search_where[] = '(d'.$v.'=?)'; //$detail_fields2[
                    $search_params = $search_params.'s';
             }
    }
        
    $compare_mode = 0;    
    //1. leven only           - one request - load ID and C1 into memory completely
    //2. equal+leven          - individual search for every loop
    //3. only  equal searches - individual search for every loop
        
    if(count($compare_fields)>0){
            $compare_fields = count($compare_fields)>1?'CONCAT('.implode(',',$compare_fields).')':$compare_fields[0];
            $compare_fields = ', SUBSTRING('. $compare_fields .',1,255) as C1 ';
            
            // we use php levenshtein now
            //$search_where[] = '(ABS(CHAR_LENGTH(?)-CHAR_LENGTH(C1))<'.$distance.') AND '
            //    .'(LEVENSHTEIN_LIMIT(?, C1,'.$distance.')<'.$distance.')';
            //$search_params = $search_params.'ss';
            $compare_mode = 1;
            
    }else{
            $compare_fields = '';
    }                           
    if(count($exact_fields)>0){
        $compare_mode = ($compare_mode==1)?2:3;
        $exact_fields = ', '.implode(',',$exact_fields);
    }else{
        $exact_fields = '';
    }
    
    if($compare_mode==0){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Required parameter "fields" is missed or has wrong value and none field found');
        return false;
    }
    
    
    self::$mysqli->query('DROP TABLE IF EXISTS tmp_find_dupes');
    
    //1. search for all records and create temporary table
    $query = ' from Records '.implode(' ',$detail_joins)
    .' where rec_RecTypeID ='.$rty_ID.' and not rec_FlagTemporary'
    .' order by rec_ID asc';

    
    $tot_count = mysql__select_value(self::$mysqli, 'SELECT count(rec_ID) '.$query);

    if(!($tot_count>1)){
        $response = self::$system->addError(HEURIST_NOT_FOUND, 
                ($tot_count==1?'Only one record':'No records').' found for record type '.$rty_ID);
        return false;
    }

    $query = ' rec_ID '.$compare_fields.' '.$exact_fields.$query;
    
    if($compare_mode>1 || $tot_count >= $in_memory_limit){
        $query = 'CREATE TEMPORARY TABLE tmp_find_dupes (PRIMARY KEY find_dupes_pkey (rec_ID)) SELECT '.$query;    
    }else{
        $query = 'SELECT SQL_CALC_FOUND_ROWS '.$query;
    }
    
    $res = self::$mysqli->query($query);
    
    if(!$res){
        
        if($compare_mode>1){
            $response = self::$system->addError(HEURIST_DB_ERROR, 
                'Search duplications. Can not create temporary table', self::$mysqli->error);
        }else{
            $response = self::$system->addError(HEURIST_DB_ERROR, 
                'Search duplications. Can not executre main query', self::$mysqli->error);
        }
        return false;
    }
        
    

    $all_similar_ids = array();     // plain array of ids of similar records (to facilitate search) 
    $all_similar_ids_cnt = 0;       // 
    $all_similar_records = array(); //result: grouped similar records - rec_ID=>rec_Title
    
    //limits
    //max allowed similar records
    // total and pecentage 
    $limit_cnt = @$params['limit_cnt'];
    if(!($limit_cnt>0)) $limit_cnt = 1000;
    else if($limit_cnt>3000) $limit_cnt = 3000;
    
    $limit_pc = @$params['limit_cnt'];
    if(!($limit_pc>0)) $limit_pc = 30;
    else if($limit_pc>50) $limit_pc = 50;
    
    
    $progress_session_id = @$params['session'];
    $msg_termination = null;
    $processed = 0;
    
    if($compare_mode>1){
        $query = 'SELECT * FROM tmp_find_dupes'; //SQL_CALC_FOUND_ROWS 
        $res = self::$mysqli->query($query);
    }
    
    //3. loop for records
    if(!$res){
        
        $response = self::$system->addError(HEURIST_DB_ERROR, 
                $savedSearchName.'Search duplications (base query for records)', self::$mysqli->error);
        return false;
        
    }else{
        
        /* we search count in separate query at the begining
        $fres = self::$mysqli->query('select found_rows()');
        if (!$fres)     {
            $response = self::$system->addError(HEURIST_DB_ERROR, 
                $savedSearchName.'Search duplications (retrieving number of records)', self::$mysqli->error);
            return false;
        }else{

            $tot_count = $fres->fetch_row();
            $tot_count = $tot_count[0];
            $fres->close();
        }
        */
        
        $limit_pc = $tot_count*$limit_pc/100;
        $limit_cnt = min($limit_cnt, $limit_pc);
        
        
        if($progress_session_id){
            //init progress session
            mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);
        }
        
        if($tot_count<$in_memory_limit && $compare_mode==1){
            self::$cache_id = array();
            self::$cache_str = array();
            
            while ($row = $res->fetch_row()) {  //main query
                if($row[1]!=''){
                    self::$cache_id[] = $row[0];//array($row[0]=>$row[1]);
                    self::$cache_str[] = $row[1];//array($row[0]=>$row[1]);
                }
            }
            
            foreach (self::$cache_id as $idx=>$curr_recid){
                //self::_findByLevenshtein($id);
                $str1 = self::$cache_str[$idx];
                $len1 = strlen($str1);
                
                $i = array_search($curr_recid,  $all_similar_ids, true); 
                if($i==false && $len1>2){

                    $dist = ceil($len1*$distance/100);
                    if($dist==0){
                        $dist = 1;              
                    }else if($dist>10){
                        $dist = 10; 
                    }
                    
                    $group = array();
                    
                    for ($idx2=$idx+1; $idx2<$tot_count; $idx2++){
                        
                        $str2 = self::$cache_str[$idx2];    
                        if(abs($len1-strlen($str2))<$dist){
                            $d = levenshtein($str1, $str2);
                            if($d<$dist){
                                $group[] = self::$cache_id[$idx2];
                            }
                        }
                    }
                    
                    if(count($group)>0){
                        array_unshift($group, $curr_recid);
                        
                        $all_similar_ids = array_merge($all_similar_ids, $group); //add new set of ids except first (current rec_id)
                        
                        //find titles
                        $group = mysql__select_assoc2(self::$mysqli,'select rec_ID, rec_Title from Records where rec_ID in ('.implode(',',$group).')');
                        
                        $all_similar_records[] = $group; //id=>title
                        $all_similar_ids_cnt = $all_similar_ids_cnt + count($group);
                        
                        if($all_similar_ids_cnt>$limit_cnt){
                            break;
                        }
                    }
                    
                }
                $processed++;
                
                //update session and check for termination                
                if($progress_session_id && ($processed % 10 == 0)){
                    $session_val = $processed.','.$tot_count;    
                    $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        $msg_termination = 'Operation is terminated by user';
                        break;
                    }
                }
            }
        }else{
        
        
            //3. create search query 
            $search_query = 'SELECT rec_ID '.($compare_mode<3?', C1':'').' FROM tmp_find_dupes WHERE ';
            $search_query = $search_query.' ('. implode(' AND ', $search_where) .')';
            //4. prepare query
            $stmt = self::$mysqli->prepare($search_query);
            if(!$stmt){
                    self::$system->addError(HEURIST_DB_ERROR, 'Can not prepare query to find duplication records', 
                        self::$mysqli->error);
                    return false;
            }
            
            //----------------------------
            //
            //
            $str1 = '';
            $len1 = 0;
            $dist = 0;
            
            while ($row = $res->fetch_row()) {  //main query

                $curr_recid = intval($row[0]);
                
                //exclude this record and records that are already included in other groups
                if(count($all_similar_ids)>0){
                    $idx = array_search($curr_recid,  $all_similar_ids, true); 
                    if($idx>0){
                        $processed++;
                        continue;
                        
                        
                    }
                }
                
                if($compare_mode<3){ //need levenshtein
                    $str1 = $row[1];
                    array_splice($row,1,1); //get C1 and remove it from array
                    $len1 = strlen($str1);
                    $dist = ceil($len1*$distance/100);
                    if($dist==0){
                        $dist = 1;              
                    }else if($dist>10){
                        $dist = 10; 
                    }
                }
                //fill values array    
                array_unshift($row, $search_params); //add as a first element - list of parameter types 
                

                $group = null;            
                call_user_func_array(array($stmt, 'bind_param'), referenceValues($row));
                if(!$stmt->execute()){
                    self::$system->addError(HEURIST_DB_ERROR, 'Can not execute query to find duplication records', 
                        self::$mysqli->error);
                    return false;
                }
                $res2 = $stmt->get_result();
                if ($res2){
                    $group = array($curr_recid);
                    while ($row2 = $res2->fetch_row()){
                        
                        if($compare_mode<3){ //need levenshtein
                            $str2 = $row2[1];    
                            if(abs($len1-strlen($str2))<$dist){
                                $d = levenshtein($str1, $str2);
                                if($d<$dist){
                                    $group[] = $row2[0]; //for mix compare mode
                                }
                            }
                        }else{
                            $group[] = $row2[0];//rec_ID - for exact compare mode    
                        }
                        
                    }
                    $res2->close();
                }
                
                //NP $group = mysql__select_assoc2(self::$mysqli, $query);
                if($group && count($group)>1){
                        $all_similar_ids = array_merge($all_similar_ids, $group); //add new set of ids except first (current rec_id)
                        
                        //find titles
                        $group = mysql__select_assoc2(self::$mysqli,'select rec_ID, rec_Title from Records where rec_ID in ('.implode(',',$group).')');
                        
                        $all_similar_records[] = $group; //id=>title
                        $all_similar_ids_cnt = $all_similar_ids_cnt + count($group);
                }

                $processed++;
                
                //update session and check for termination                
                if($progress_session_id && ($processed % 10 == 0)){
                    $session_val = $processed.','.$tot_count;    
                    $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        $msg_termination = 'Operation is terminated by user';
                        break;
                    }
                }
                
                if($all_similar_ids_cnt>$limit_cnt){
                    break;
                }
            
    /*        
            ON a.rec_ID != b.rec_ID AND b.rec_RecTypeID=10
        AND NEW_LEVENSHTEIN(NEW_LIPOSUCTION(a.rec_Title), NEW_LIPOSUCTION(b.rec_Title))<5
    WHERE a.rec_ID=:XXX'
    */            
            }//while
            
            
            $res->close();
            
        }
        
        self::$mysqli->query('DROP TABLE IF EXISTS tmp_find_dupes');
        
        //add info
        $all_similar_records['summary'] = array(
            'scope'=>$tot_count,
            'cnt_groups'=>count($all_similar_records),
            'cnt_records'=>$all_similar_ids_cnt,
            'limit'=>$limit_cnt,
            'is_terminated'=>($msg_termination!=null)
        );

        if($progress_session_id){
            //remove session file
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');    
        }
    }//if $res 
    
    return $all_similar_records;
    
}//findDupes

} //end class
?>
