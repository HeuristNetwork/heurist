<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

$TL = array();
$RTN = array();

function getInvalidFieldTypes($rectype_id){

    global $TL, $RTN;

    mysql_connection_select(DATABASE);

    // lookup detail type enum values
    $query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms';
    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
        $TL[$row['trm_ID']] = $row;
    }
    //record type name
    $query = 'SELECT rty_ID, rty_Name FROM defRecTypes';
    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
        $RTN[$row['rty_ID']] = $row['rty_Name'];
    }

    //list of detail types to validate
    $DTT = array();
    $query = "SELECT dty_ID,".
    "dty_Name,".
    "dty_Type,".
    "dty_JsonTermIDTree,".
    "dty_TermIDTreeNonSelectableIDs,".
    "dty_PtrTargetRectypeIDs".
    " FROM defDetailTypes";

    if(null!=$rectype_id){ //detail types for given recordtype
        $query = $query.", defRecStructure WHERE rst_RecTypeID=".$rectype_id." and rst_DetailTypeID=dty_ID and ";

    }else{
        $query = $query." WHERE ";
    }
    $query = $query.
    "(dty_Type in ('enum','relationtype','relmarker','resource')".
    " and (dty_JsonTermIDTree is not null or dty_TermIDTreeNonSelectableIDs is not null)) ".
    "or (dty_Type in ('relmarker','resource') and dty_PtrTargetRectypeIDs is not null)";


    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
        $DTT[$row['dty_ID']] = $row;
    }

    $dtysWithInvalidTerms = array();
    $dtysWithInvalidNonSelectableTerms = array();
    $dtysWithInvalidRectypeConstraint = array();
    foreach ( $DTT as $dtyID => $dty) {
        if ($dty['dty_JsonTermIDTree']){
            $res = getInvalidTerms($dty['dty_JsonTermIDTree'], true);
            $invalidTerms = $res[0];
            $validTermsString = $res[1];
            if (count($invalidTerms)){
                $dtysWithInvalidTerms[$dtyID] = $dty;
                $dtysWithInvalidTerms[$dtyID]['invalidTermIDs'] = $invalidTerms;
                $dtysWithInvalidTerms[$dtyID]['validTermsString'] = $validTermsString;
            }
        }
        if ($dty['dty_TermIDTreeNonSelectableIDs'])
        {

            $res = getInvalidTerms($dty['dty_TermIDTreeNonSelectableIDs'], false);
            $invalidNonSelectableTerms = $res[0];
            $validNonSelTermsString = $res[1];
            if (count($invalidNonSelectableTerms)){
                $dtysWithInvalidNonSelectableTerms[$dtyID] = $dty;
                $dtysWithInvalidNonSelectableTerms[$dtyID]['invalidNonSelectableTermIDs'] = $invalidNonSelectableTerms;
                $dtysWithInvalidNonSelectableTerms[$dtyID]['validNonSelTermsString'] = $validNonSelTermsString;
            }
        }
        if ($dty['dty_PtrTargetRectypeIDs']){
            $res = getInvalidRectypes($dty['dty_PtrTargetRectypeIDs']);
            $invalidRectypes = $res[0];
            $validRectypes   = $res[1];
            if (count($invalidRectypes)){
                $dtysWithInvalidRectypeConstraint[$dtyID] = $dty;
                $dtysWithInvalidRectypeConstraint[$dtyID]['invalidRectypeConstraint'] = $invalidRectypes;
                $dtysWithInvalidRectypeConstraint[$dtyID]['validRectypeConstraint'] = $validRectypes;
            }
        }
    }//for

    return array("terms"=>$dtysWithInvalidTerms, "terms_nonselectable"=>$dtysWithInvalidNonSelectableTerms, "rt_contraints"=>$dtysWithInvalidRectypeConstraint);
}

// function that translates all term ids in the passed string to there local/imported value
function getInvalidTerms($formattedStringOfTermIDs, $is_tree) {
    global $TL;
    $invalidTermIDs = array();
    if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
        return array($invalidTermIDs, "");
    }

    $isvocabulary = false;
    $pos = strpos($formattedStringOfTermIDs,"{");

    if ($pos!==false){ //}is_numeric($pos) && $pos>=0) {

        $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
        if (strrpos($temp,":") == strlen($temp)-1) {
            $temp = substr($temp,0, strlen($temp)-1);
        }
        $termIDs = explode(":",$temp);
    } else if ($is_tree){ //vocabulary

        $isvocabulary = true;
        $termIDs = array($formattedStringOfTermIDs);
    } else {
        $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
        $termIDs = explode(",",$temp);
    }
    // Validate termIDs

    foreach ($termIDs as $trmID) {
        // check that the term valid
        if (!$trmID ){ // invalid trm ID null or 0 is not allowed
            if(count($termIDs)>1){
                array_push($invalidTermIDs, "blank");
            }
        }else if ( !@$TL[$trmID]){ // invalid trm ID
            array_push($invalidTermIDs,$trmID);
        }
    }

    $validStringOfTerms = "";
    //create valid set of terms
    if(count($invalidTermIDs)>0){

        if($isvocabulary ){ //vocabulary
            $validStringOfTerms =  "";
        } else if($is_tree) {
            $termTree = json_decode($formattedStringOfTermIDs);
            $validStringOfTerms = createValidTermTree($termTree, $invalidTermIDs);
            if($validStringOfTerms!=""){
                $validStringOfTerms = "{".$validStringOfTerms."}";
            }
        } else {
            $termIDs = array_diff($termIDs, $invalidTermIDs);
            if(count($termIDs)>0){
                $validStringOfTerms = '["'.implode('","',$termIDs).'"]';
            }else{
                $validStringOfTerms = "";
            }
        }
    }

    return array($invalidTermIDs, $validStringOfTerms);
}

function createValidTermTree($termTree, $invalidTermIDs){
    $validStringOfTerms = "";
    $res = "";
    foreach ($termTree as $termid=>$child_terms){

        $key = array_search($termid, $invalidTermIDs);
        if($key===false){
            $res = $res.'"'.$termid.'":{'.createValidTermTree($child_terms, $invalidTermIDs).'},';
        }else{ //invalid
            //return "";
        }
    }
    return ($res=="")?"": substr($res,0,-1);
}

// function that check the existaance of all rectype ids in the passed string
function getInvalidRectypes($formattedStringOfRectypeIDs) {
    global $RTN;
    $invalidRectypeIDs = array();

    if (!$formattedStringOfRectypeIDs || $formattedStringOfRectypeIDs == "") {
        return array($invalidRectypeIDs, "");
    }

    $validRectypeIDs = array();
    $rtyIDs = explode(",",$formattedStringOfRectypeIDs);
    // Validate rectypeIDs
    foreach ($rtyIDs as $rtID) {
        // check that the rectype is valid
        if (!@$RTN[$rtID]){ // invalid rty ID
            array_push($invalidRectypeIDs,$rtID);
        }else{
            array_push($validRectypeIDs, $rtID);
        }
    }

    return array($invalidRectypeIDs, implode(",", $validRectypeIDs) );
}

?>