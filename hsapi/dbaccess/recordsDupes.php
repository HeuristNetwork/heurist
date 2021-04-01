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
//    distance - 0 exact duplication (by default), maxvalue - 5
//
public static function findDupes( $params ){

    self::initialize();
    
    $rty_ID = @$params['rty_ID'];
    if(!($rty_ID>0)){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Required parameter rty_ID is missed. Define record type ID');
        return false;
    }
    
    $distance = @$params['distance'];
    if(!($distance>0)) $distance = 0;
    if($distance>5) $distance = 5;
    
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
    
    $need_levenshtein = false;
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
             }else if(@$dty_IDs[$v]=='freetext' || @$dty_IDs[$v]=='blocktext'){
                 if($distance>0){
                    $compare_fields[] = $detail_fields2[$v];
                 }else{
                    $exact_fields[] = $detail_fields[$v];        
                    $search_where[] = '(d'.$v.'=?)'; //$detail_fields2[
                    $search_params = $search_params.'s';
                 }
             }
    }
        
    if(count($compare_fields)>0){
            $compare_fields = count($compare_fields)>1?'CONCAT('.implode(',',$compare_fields).')':$compare_fields[0];
            $compare_fields = ', SUBSTRING('. $compare_fields .',1,255) as C1 ';
            
            $search_where[] = '(ABS(CHAR_LENGTH(?)-CHAR_LENGTH(C1))<'.$distance.') AND '
                .'(LEVENSHTEIN_LIMIT(?, C1,'.$distance.')<'.$distance.')';
            $search_params = $search_params.'ss';
            $need_levenshtein = true;
            
    }else{
            $compare_fields = '';
    }                           
    if(count($exact_fields)>0){
        $exact_fields = ', '.implode(',',$exact_fields);
    }else{
        $exact_fields = '';
    }
    
    
    self::$mysqli->query('DROP TABLE IF EXISTS tmp_find_dupes');
    
    //1. search for all records and create temporary table
    $query = 'select rec_ID '.$compare_fields.' '.$exact_fields
    .' from Records '.implode(' ',$detail_joins)
    .' where rec_RecTypeID ='.$rty_ID.' and not rec_FlagTemporary'
    .' order by rec_ID asc';
    
    $query = 'CREATE TEMPORARY TABLE tmp_find_dupes (PRIMARY KEY find_dupes_pkey (rec_ID)) '.$query;    
    
    $res = self::$mysqli->query($query);
    
    if(!$res){
        
        $response = self::$system->addError(HEURIST_DB_ERROR, 
                $savedSearchName.'Search duplications. Can not create temporary table', self::$mysqli->error);
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
    $tot_count = 0;
    $processed = 0;
    
    $query = 'SELECT SQL_CALC_FOUND_ROWS * FROM tmp_find_dupes';
    
    $res = self::$mysqli->query($query);
    
    //3. loop for records
    if(!$res){
        
        $response = self::$system->addError(HEURIST_DB_ERROR, 
                $savedSearchName.'Search duplications (base query for records)', self::$mysqli->error);
        return false;
        
    }else{
        
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
        
        $limit_pc = $tot_count*$limit_pc/100;
        $limit_cnt = min($limit_cnt, $limit_pc);
        
        
        if($progress_session_id){
            //init progress session
            mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);
        }
        
        //3. create search query 
        $search_query = 'SELECT rec_ID FROM tmp_find_dupes WHERE ';
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
        while ($row = $res->fetch_row()) {  //main query

            $curr_recid = $row[0];

            
            //exclude this record and records that are already included in other groups
            if(count($all_similar_ids)>0){
                $idx = array_search($curr_recid,  $all_similar_ids, true); 
                if($idx>0){
                    continue;
                    
                }
                /* QQQQ
                //remove ids less than current rec_ID
                foreach ($all_similar_ids as $idx => $id) {
                    if ($id > $curr_recid) {
                        $all_similar_ids = array_slice($all_similar_ids, $idx);  
                        break;
                    }
                }                   
                
                if(count($all_similar_ids)>0){
                    $where[] = '(rec_ID NOT IN ('.implode(',',$all_similar_ids).'))';    
                }
                */
            }
            
            //fill values array    
            if($need_levenshtein) array_push($row, $row[count($row)-1]);
            array_unshift($row, $search_params);

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
                    $group[] = $row2[0];//'Record '.$row2[1];
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
        
        self::$mysqli->query('DROP TABLE tmp_find_dupes');
        
        
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
