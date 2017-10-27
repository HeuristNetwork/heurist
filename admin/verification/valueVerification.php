<?php

/**
* valueVerification.php - library of functions to verify values - pointers and terms to conform to
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

$dtyIDDefs = array();  //list of allowed terms for particular detail type ID
$dtyID_term_label = array();
$dtyID_term_codes = array();


//
// clear all global variables
// it is required in case database switch
//
function resetGlobalTermsArrays(){
    global $dtyIDDefs, $dtyID_term_label, $dtyID_term_codes;
    
    $dtyIDDefs = array();  //list of allowed terms for particular detail type ID
    $dtyID_term_label = array();
    $dtyID_term_codes = array();
}

/**
* 
*
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $dtyID
*/
function getAllowedTerms($defs, $defs_nonsel, $dtyID){
    global $dtyIDDefs;

    $allowed_terms = null;

    if($dtyID==null || !@$dtyIDDefs[$dtyID]){ //detail type ID is not defined or terms are already found

        if ( $dtyID == DT_RELATION_TYPE) {
            //get all root terms (vocabs)
            $allowed_terms = getTermListAll('relation');
            $dtyIDDefs[$dtyID] = $allowed_terms;

        } else {

            $terms = getTermsFromFormat($defs);
            
            if (($cntTrm = count($terms)) > 0) {

                if ($cntTrm == 1) {  //vocabulary
                
                    $terms = getTermOffspringList($terms[0]);
                    
                }else{
                    $nonTerms = getTermsFromFormat($defs_nonsel);
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
                    $dtyIDDefs[$dtyID] = $allowed_terms;
                }

            }
        }
    }else{
        
        $allowed_terms = $dtyIDDefs[$dtyID];
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
function isValidTerm($defs, $defs_nonsel, $id, $dtyID){

    $allowed_terms = getAllowedTerms($defs, $defs_nonsel, $dtyID);

    return $allowed_terms && ($allowed_terms === "all" || in_array($id, $allowed_terms));
}

/**
* Returns term ID if label is valid and false if invalid
*
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $label
* @param mixed $dtyID
*/
function isValidTermLabel($defs, $defs_nonsel, $label, $dtyID, $isStripAccents=false){
    global $dtyID_term_label;

    $allowed_terms = null;

    if($dtyID==null || !@$dtyID_term_label[$dtyID]){

        //ids
        $allowed_terms = getAllowedTerms($defs, $defs_nonsel, $dtyID);

        //get labels
        if(is_array($allowed_terms)){
            $allowed_terms = getTermLabels($allowed_terms);
        }

        //keep for future use
        if($dtyID!=null){
            $dtyID_term_label[$dtyID] = $allowed_terms;
        }

    }else{
        $allowed_terms = $dtyID_term_label[$dtyID];
    }

    if($isStripAccents && is_array($allowed_terms)){
        array_walk($allowed_terms, 'trim_lower_accent2');
    }
    
    $label = trim(mb_strtolower($label));

    if(is_array($allowed_terms)){
        $term_ID = array_search($label, $allowed_terms, true);
    }else{
        $term_ID = getTermByLabel($label);
    }

    return $term_ID;

}

/**
* Returns term ID if code is valid and false if invalid
*
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $label
* @param mixed $dtyID
*/
function isValidTermCode($defs, $defs_nonsel, $code, $dtyID){
    global $dtyID_term_codes;

    $allowed_terms = null;

    if($dtyID==null || !@$dtyID_term_codes[$dtyID]){

        //ids
        $allowed_terms = getAllowedTerms($defs, $defs_nonsel, $dtyID);

        //get labels
        if(is_array($allowed_terms)){
            $allowed_terms = getTermCodes($allowed_terms);
        }

        //keep for future use
        if($dtyID!=null){
            $dtyID_term_codes[$dtyID] = $allowed_terms;
        }

    }else{ //already found
        $allowed_terms = $dtyID_term_codes[$dtyID];
    }
    
    $code = mb_strtolower($code);

    if(is_array($allowed_terms)){
        $term_ID = array_search($code, $allowed_terms, true);
    }else{
        $term_ID = getTermByCode($code);
    }

    return $term_ID;

}


function stripAccents($stripAccents){
    return my_strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝß','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYs');
}

function  trim_lower_accent($item){
    return mb_strtolower(stripAccents($item));
}

function  trim_lower_accent2(&$item, $key){
    $item = trim_lower_accent($item);
}



//
// Is given termid in list of allowed terms or in vocab
// NOT - it is quite similar to isValidTerm 
//
function isInvalidTerm($defs, $defs_nonsel, $id, $dtyID){
    global $dtyIDDefs;

    if(!@$dtyIDDefs[$dtyID]){
        $terms = getTermsFromFormat($defs);
        if (($cntTrm = count($terms)) > 0) {
            if ($cntTrm == 1) {  //vocabulary
                $terms = getTermOffspringList($terms[0]);
            }else{
                $nonTerms = getTermsFromFormat($defs_nonsel);
                if (count($nonTerms) > 0) {
                    $terms = array_diff($terms,$nonTerms);
                }
            }
            if (count($terms)<1) {
                $dtyIDDefs[$dtyID] = "all";
            }else{
                $dtyIDDefs[$dtyID] = $terms;
            }
        }
    }
    if(!@$dtyIDDefs[$dtyID]){
        return true; //terms not found
    }
    return !($dtyIDDefs[$dtyID] === "all" || in_array($id, $dtyIDDefs[$dtyID]));
}

//
// parse terms string
// similar functions are in saveRecordDetail and importRectype
// @todo - use this library!
//
function getTermsFromFormat($formattedStringOfTermIDs){

    if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
        return array();
    }


    if (strpos($formattedStringOfTermIDs,"{")!== false) {
        $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
        if (strrpos($temp,":") == strlen($temp)-1) {
            $temp = substr($temp,0, strlen($temp)-1);
        }
        $termIDs = explode(":",$temp);
    } else {
        $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
        $termIDs = explode(",",$temp);
    }
    return $termIDs;
}

//-------------------------------------

function isValidPointer($constraints, $rec_id, $dtyID ){
    global $dtyIDDefs;

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

?>
