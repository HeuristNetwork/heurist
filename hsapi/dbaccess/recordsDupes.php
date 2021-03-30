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
//    distance - 0 exact duplication (by default) 
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
            $p = 'd'.$v;
            $detail_joins[] = ' left join recDetails '.$p.' on rec_ID='.$p.'.dtl_RecID and dtl_DetailTypeID='.$v;
            
            $s = 'NEW_LIPOSUCTION(IFNULL('.$p.'.dtl_Value,""))';
            $detail_fields[] = $s.' as '.$p;
            $detail_fields2[$v] = $s;
            $dty_IDs[] = $v;
        }else if (strpos($v,'rec_')===0){
            $s = 'NEW_LIPOSUCTION(IFNULL('.$v.',""))';
            $header_fields[] = $s.' as '.$v; //for retrieve       
            $header_fields2[$v] = $s; //for compare query
        }
    }
    if(count($detail_fields)==1 && count($header_fields)==1){
        //ERROR
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Required parameter rty_ID is missed. Define record type ID');
        return false;
    }
    
    //get field types
    $dty_IDs = mysql__select_assoc2(self::$mysqli, 'select dty_ID, dty_Type from defDetailTypes where dty_ID in ('
                .implode(',',$dty_IDs).')');

    
    //1. search for all records
    $query = 'select rec_ID '.implode(',',$header_fields).' '.implode(',',$detail_fields)
    .' from Records '.implode(' ',$detail_joins)
    .' where rec_RecTypeID ='.$rty_ID.' and not rec_FlagTemporary'
    .' order by rec_ID asc';
    
    $res = self::$mysqli->query($query);

    
    //3. loop for records
    if($res){
        
        //3. create search string and search query (for non-text fields)
        // all non freetext, blocktext are compared equal
        /*
        $s = '';
        foreach ($dty_IDs as $dty_ID => $dty_Type){
            if($distance>0 && $dty_Type=='freetext' || $dty_Type=='blocktext'){
                //text field will be compared according to distance parameter 
                            
            }else{
                //other fields have exact comparison
                $p = 'd'.$dty_ID;
                //$s = '(exists (select dtl_ID from recDetails '.$p.' where r.rec_ID='.$p.'.dtl_RecID AND '
                //    .$p.'.dtl_DetailTypeID='.$dty_ID.' AND '.$p.'.dtl_Value="XXX")';        
                $s = $s . ' LEFT JOIN recDetails '.$p.' ON (r.rec_ID='.$p.'.dtl_RecID AND '
                    .$p.'.dtl_DetailTypeID='.$dty_ID.' AND '.$p.'.dtl_Value="XXX")';        
            }
        }
        */
        
        $compare_fields = array();
        if($distance>0)
        foreach($fields as $v){
             if($v=='rec_Title' || $v=='rec_URL' || $v=='rec_ScratchPad'){
                $compare_fields[] = $header_fields2[$v];
             }else if(@$dty_IDs[$v]=='freetext' || @$dty_IDs[$v]=='blocktext'){
                 $compare_fields[] = $detail_fields2[$v];
             }
        }
        
        if(count($compare_fields)>0){
            $compare_fields = count($compare_fields)>1?'CONCAT('.implode(',',$compare_fields).')':$compare_fields[0];
            $compare_fields = 'SUBSTRING('. $compare_fields .',1,255)';
        }
        
        $all_similar_ids = array(); //plain array of ids of similar records (to facilitate search) 
        $all_similar_records = array(); //result: ids of all similar records by groups
        
        
        while ($row = $res->fetch_assoc()) {
            
            //4. find                     
            $query = 'SELECT rec_ID, rec_Title FROM Records ';
            $query = $query.' '.implode(' ',$detail_joins);
            
            $query = $query . ' WHERE (rec_ID='.$row['rec_ID'].') OR ';
            
            $compare_with_distance = '';
            $where = array('(rec_RecTypeID ='.$rty_ID.')', '(not rec_FlagTemporary)');
            $values = array('');
            
            //rec_RecTypeID ='.rty_ID.' and not rec_FlagTemporary
            
            //exclude this record and records that are already included in other groups
            if(count($all_similar_ids)>0){
                $where[] = '(rec_ID NOT IN ('.implode(',',$all_similar_ids).'))';    
            }
            
            //compare with header fields
            if($distance>0)
            foreach($fields as $v){
                if($v=='rec_Title' || $v=='rec_URL' || $v=='rec_ScratchPad'){
                    
                    $compare_with_distance = $compare_with_distance.$row[$v]; 
                    
                }else if(@$dty_IDs[$v]=='freetext' || @$dty_IDs[$v]=='blocktext'){
                    
                    $compare_with_distance = $compare_with_distance.$row['d'.$v]; 
                    
                }else if(@$dty_IDs[$v]){
                    
                    //NP $where[] = '('.$detail_fields2[$v].'="'.$row['d'.$v].'")';

                    $where[] = '('.$detail_fields2[$v].'=?)';
                    $values[0] = $values[0].'s';
                    $values[] = $row['d'.$v];
                }
            }
            //
            if($compare_with_distance){
                
                if(mb_strlen($compare_with_distance)>255){
                    $compare_with_distance = mb_substr($compare_with_distance,0,255);
                }
                
                //NP $where[] = '(NEW_LEVENSHTEIN("'.$compare_with_distance.'", '.$compare_fields.')<'.$distance.')';
                
                $where[] = '(NEW_LEVENSHTEIN(?, '.$compare_fields.')<'.$distance.')';
                $values[0] = $values[0].'s';
                $values[] = $compare_with_distance;
            }
      
            $query = $query . '('. implode(' AND ', $where) .')';

            $group = null;            
            $stmt = self::$mysqli->prepare($query);
            if($stmt){
                call_user_func_array(array($stmt, 'bind_param'), referenceValues($values));
                if(!$stmt->execute()){
                    self::$system->addError(HEURIST_DB_ERROR, 'Can not execute query to find duplication records', 
                        self::$mysqli->error);
                    return false;
                }
                $res2 = $stmt->get_result();
                if ($res2){
                    $group = array();            
                    while ($row2 = $res2->fetch_row()){
                        $group[$row2[0]] = $row2[1];
                    }
                    $res2->close();
                }
            }
            
            //NP $group = mysql__select_assoc2(self::$mysqli, $query);
            if($group && count($group)>1){
                    $all_similar_ids = array_merge($all_similar_ids, array_keys($group)); //add new set of ids
                    $all_similar_records[] = $group;
            }

        
/*        
        ON a.rec_ID != b.rec_ID AND b.rec_RecTypeID=10
    AND NEW_LEVENSHTEIN(NEW_LIPOSUCTION(a.rec_Title), NEW_LIPOSUCTION(b.rec_Title))<5
WHERE a.rec_ID=:XXX'
*/            
        }//while
        $res->close();
    }//if $res 
    
    
    if($is_debug){
        foreach ($all_similar_records as $group){
            foreach ($group as $rec_ID=>$rec_Title ){
                print '<div>'.$rec_ID.'  '.$rec_Title.'</div>';
            }
            print '<hr>';
        }
    }else{
        return $all_similar_records;
    }
}//findDupes

} //end class
?>
