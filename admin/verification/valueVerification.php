<?php

/**
* valueVerification.php - library of functions to verify values - pointers and terms to conform to
* the constraints in detail and record type definitions
* Used in listDataErrors.php, importCSV_lib.php
* @todo saveRecordDetail and importRectype
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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

/**
* put your comment there...
*
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $dtyID
*/
function getAllowedTerms($defs, $defs_nonsel, $dtyID){
    global $dtyIDDefs;

    $allowed_terms = null;

    if($dtyID==null || !@$dtyIDDefs[$dtyID]){
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
* put your comment there...
*
* @param mixed $defs
* @param mixed $defs_nonsel
* @param mixed $label
* @param mixed $dtyID
*/
function isValidTermLabel($defs, $defs_nonsel, $label, $dtyID){
    global $dtyID_term_label;

    $allowed_terms = null;

    if($dtyID==null || !@$dtyID_term_label[$dtyID]){

        //ids
        $allowed_terms = getAllowedTerms($defs, $defs_nonsel, $dtyID);

        //get labels
        if(is_array($allowed_terms)){
            $allowed_terms = getTermLabels($allowed_terms);
        }

        //keep for fitire use
        if($dtyID!=null){
            $dtyID_term_label[$dtyID] = $allowed_terms;
        }

    }else{
        $allowed_terms = $dtyID_term_label[$dtyID];
    }

    $label = mb_strtolower($label);

    return $allowed_terms && ($allowed_terms === "all" || in_array($label, $allowed_terms));
}

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
            if (count($temp)<1) {        
                $dtyIDDefs[$dtyID] = "all";        
            }else{        
                $dtyIDDefs[$dtyID] = $terms;        
            }        
        }        
    }        

    return $dtyIDDefs[$dtyID] === "all" || in_array($id, $dtyIDDefs[$dtyID]);        
}        

//
// similar functions are in saveRecordDetail and importRectype
// @todo - use this library!
//
function getTermsFromFormat($formattedStringOfTermIDs){

        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return array();
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            /*****DEBUG****///error_log( "term tree string = ". $formattedStringOfTermIDs);
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            /*****DEBUG****///echo ( "term array string = ". $formattedStringOfTermIDs);
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }
        return $termIDs;
}

//-------------------------------------

function isValidPointer($constraints, $rec_id, $dtyID ){
    global $dtyIDDefs;

    $res = mysql_query("select rec_RecTypeID from Records where rec_ID = ".$rec_id);
    if ($res){
        $tempRtyID = mysql_fetch_row($res);
        if ($tempRtyID){
              $tempRtyID = @$tempRtyID[0];
        } else {
             return false;
        }

        $allowed_types = "all";
        if ($constraints!=null && $constraints != "") {
                $temp = explode(",",$constraints); //get allowed record types
                if (count($temp)>0) {
                       $allowed_types = $temp;
                }
        }

        return ($allowed_types === "all" || in_array($tempRtyID, $allowed_types));

    }else{
        //record not found
        return false;
    }
}

?>
