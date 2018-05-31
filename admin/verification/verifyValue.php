<?php
//@TODO wrap to class

/**
* verifyValue.php - library of functions to verify values - pointers and terms to conform to
* the constraints in detail and record type definitions
* Used in listDatabaseErrors.php, importCSV_lib.php
* @todo saveRecordDetail and importRectype
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

class VerifyValue {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}    
    private static $mysqli = null;
    private static $initialized = false;

    private static $dtyIDDefs = array();  //list of allowed terms for particular detail type ID
    private static $dtyIDDefs_labels = array();
    private static $dtyIDDefs_codes = array();
    private static $terms = array();

    private static function initialize()
    {
        if (self::$initialized)
            return;

        global $system;
        self::$mysqli = $system->get_mysqli();    
        
        self::$initialized = true;
    }


    //
    // clear all global variables
    // it is required in case database switch
    //
    public static function reset(){
        self::$dtyIDDefs = array();  //list of allowed terms for particular detail type ID
        self::$dtyIDDefs_labels = array();
        self::$dtyIDDefs_codes = array();
        
        self::$terms = null;
    }

/**
* get all terms ids allowed for given field type
* 
* @param mixed $defs - array of all terms
* @param mixed $defs_nonsel - array of disabled(header) terms
* @param mixed $dtyID - detail type i
*/
public static function getAllowedTerms($defs, $defs_nonsel, $dtyID){
    
    self::initialize();

    $allowed_terms = null;

    if($dtyID==null || !@self::$dtyIDDefs[$dtyID]){ //detail type ID is not defined or terms are already found

        if ( $dtyID == DT_RELATION_TYPE) {
            //get all root terms (vocabs)
            $allowed_terms = getTermListAll(self::$mysqli, 'relation'); //from db_structure
            self::$dtyIDDefs[$dtyID] = $allowed_terms;

        } else {

            $terms = getTermsFromFormat($defs); //db_structure
            
            if (($cntTrm = count($terms)) > 0) {

                if ($cntTrm == 1) {  //vocabulary
                
                    $terms = getTermOffspringList(self::$mysqli, $terms[0]); //db_structure
                    
                }else{
                    $nonTerms = getTermsFromFormat($defs_nonsel); //from db_structure
                    if (count($nonTerms) > 0) {
                        $terms = array_diff($terms, $nonTerms);
                    }
                }
                if (count($terms)<1) {
                    $allowed_terms = "all";
                }else{
                    $allowed_terms = $terms;
                }

                if($dtyID!=null){ //keep for future use
                    self::$dtyIDDefs[$dtyID] = $allowed_terms;
                }

            }
        }
    }else{
        
        $allowed_terms = self::$dtyIDDefs[$dtyID];
    }
    return $allowed_terms;
}

/**
* Verifies that term ID value is valid for given detail id
*
* @param mixed $defs    - json or list of allowed terms (or vocabulary term id)
* @param mixed $defs_nonsel - list of terms that are not selectable
* @param mixed $id - term id
* @param mixed $dtyID - detail type id
*/
public static function isValidTerm($defs, $defs_nonsel, $id, $dtyID){

    $allowed_terms = self::getAllowedTerms($defs, $defs_nonsel, $dtyID);

    return $allowed_terms && ($allowed_terms === "all" || in_array($id, $allowed_terms));
}

/**
* Returns term ID if label is valid and false if invalid
*
* used in import csv
* 
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $label
* @param mixed $dtyID
*/
public static function isValidTermLabel($defs, $defs_nonsel, $label, $dtyID, $isStripAccents=false){

    if($dtyID==null || !@self::$dtyIDDefs_labels[$dtyID]){
    
        self::initialize();
        if(self::$terms==null)  self::$terms = dbs_GetTerms();
        $allowed_terms = self::getAllowedTerms($defs, $defs_nonsel, $dtyID);
        
        $allowed_labels = array();
        
        $idx_code = self::$terms['fieldNamesToIndex']['trm_Label'];
    
        //get all labels    
        $domain = @self::$terms['termsByDomainLookup']['relation'][$allowed_terms[0]]?'relation':'enum';
        $list = self::$terms['termsByDomainLookup'][$domain];
        foreach($allowed_terms as $term_id){
           array_push( $allowed_labels, $list[$term_id][$idx][$idx_code] );
        }
    
        if($isStripAccents && is_array($allowed_labels)){
            array_walk($allowed_labels, 'trim_lower_accent2');
        }
        
        //keep for future use
        if($dtyID!=null){
            self::$dtyIDDefs_labels[$dtyID] = $allowed_labels;
        }
        
    }else{
        $allowed_labels = self::$dtyIDDefs_labels[$dtyID];
    }
    
    //check if given label among allowed
    $label = trim(mb_strtolower($label));

    if(count($allowed_labels)>0){
        $term_ID = array_search($label, $allowed_labels, true);
    }else{
        return false;
        //$term_ID = getTermByLabel($label); //db_structure
    }

    return $term_ID;
}

/**
* Returns term ID if code is valid and false if invalid
*
* used in import csv
* 
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $code
* @param mixed $dtyID
*/
public static function isValidTermCode($defs, $defs_nonsel, $code, $dtyID){
    
    if($dtyID==null || !@self::$dtyIDDefs_codes[$dtyID]){
    
        self::initialize();
        if(self::$terms==null)  self::$terms = dbs_GetTerms();
        $allowed_terms = self::getAllowedTerms($defs, $defs_nonsel, $dtyID);
        
        $allowed_codes = array();
        
        $idx_code = self::$terms['fieldNamesToIndex']['trm_Code'];
    
        //get all codes  
        $domain = @self::$terms['termsByDomainLookup']['relation'][$allowed_terms[0]]?'relation':'enum';
        $list = self::$terms['termsByDomainLookup'][$domain];
        foreach($allowed_terms as $term_id){
           array_push( $allowed_codes, mb_strtolower($list[$term_id][$idx][$idx_code]) );
        }
    
        //keep for future use
        if($dtyID!=null){
            self::$dtyIDDefs_codes[$dtyID] = $allowed_codes;
        }
        
    }else{
        $allowed_codes = self::$dtyIDDefs_codes[$dtyID];
    }
    
    //check if given code among allowed
    $code = trim(mb_strtolower($code));

    if(is_array($allowed_codes)){
        $term_ID = array_search($code, $allowed_codes, true);
    }else{
        return false;
        //$term_ID = getTermByCode($code); //db_structure
    }

    return $term_ID;
}

//-------------------------------------
//
// verify that given record $rec_id is a rectype that suits $constraints
// 
public static function isValidPointer($constraints, $rec_id, $dtyID ){

    $isvalid = false;
    
    if(isset($rec_id) && is_numeric($rec_id) && $rec_id>0){
    
        $res = mysql_query("select rec_RecTypeID from Records where rec_ID = ".$rec_id);
        if ($res){
            $tempRtyID = mysql_fetch_row($res);
            if ($tempRtyID){
                $tempRtyID = @$tempRtyID[0];

                $allowed_types = "all";
                if ($constraints!=null && $constraints != "") {
                    $temp = explode(",",$constraints); //get allowed record types
                    if (count($temp)>0) {
                        $allowed_types = $temp;
                    }
                }

                $isvalid = ($allowed_types === "all" || in_array($tempRtyID, $allowed_types));

            }
        }
    }
    return $isvalid;
}

}
?>
