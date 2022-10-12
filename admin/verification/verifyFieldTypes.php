<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* Verify rst_DefaultValue, dty_JsonTermIDTree dty_TermIDTreeNonSelectableIDs dty_PtrTargetRectypeIDs
* for terms and rectype existance
* 
* see listDatabaseErrors.php
* 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
$TL = array();  //list of all terms
$RTN = array();  //rty_ID=>rty_Name

function getInvalidFieldTypes($mysqli, $rectype_id){

    global $TL, $RTN;

    // lookup detail type enum values
    $query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms order by trm_ParentTermID,trm_Label';
    $res = $mysqli->query($query);
    while ($row = $res->fetch_assoc()) {
        $TL[$row['trm_ID']] = $row;
    }
    //record type name
    $query = 'SELECT rty_ID, rty_Name FROM defRecTypes';
    $res = $mysqli->query($query);
    while ($row = $res->fetch_assoc()) {
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


    $res = $mysqli->query($query);
    if($res)
    while ($row = $res->fetch_assoc()) {
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
            if (is_array($invalidTerms) && count($invalidTerms)>0){
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
            if (is_array($invalidNonSelectableTerms) && count($invalidNonSelectableTerms)>0){
                $dtysWithInvalidNonSelectableTerms[$dtyID] = $dty;
                $dtysWithInvalidNonSelectableTerms[$dtyID]['invalidNonSelectableTermIDs'] = $invalidNonSelectableTerms;
                $dtysWithInvalidNonSelectableTerms[$dtyID]['validNonSelTermsString'] = $validNonSelTermsString;
            }
        }
        if ($dty['dty_PtrTargetRectypeIDs']){
            $res = getInvalidRectypes($dty['dty_PtrTargetRectypeIDs']);
            $invalidRectypes = $res[0];
            $validRectypes   = $res[1];
            if (is_array($invalidRectypes) && count($invalidRectypes)>0){
                $dtysWithInvalidRectypeConstraint[$dtyID] = $dty;
                $dtysWithInvalidRectypeConstraint[$dtyID]['invalidRectypeConstraint'] = $invalidRectypes;
                $dtysWithInvalidRectypeConstraint[$dtyID]['validRectypeConstraint'] = $validRectypes;
            }
        }
        
    }//for   
    
    
    $query = "SELECT dty_ID,".
    "dty_Type,".
    "rst_RecTypeID,".
    "rst_DisplayName, rty_Name,".
    "rst_DefaultValue, rst_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree".
    " FROM defDetailTypes, defRecStructure, defRecTypes WHERE rst_RecTypeID=rty_ID ".
    " AND rst_DetailTypeID=dty_ID and rst_DefaultValue is not null and rst_DefaultValue<>'' AND ".
    "dty_Type in ('resource','enum') ";  //,'relationtype','relmarker'
    if($rectype_id>0) {
        $query = $query.' and rst_RecTypeID='.$rectype_id;
    }
    $query = $query.' ORDER BY rst_RecTypeID, dty_ID';
    $res = $mysqli->query($query);
    
    $rtysWithInvalidDefaultValues = array();
    if($res){
        while ($row = $res->fetch_assoc()) {
            
            $reason = null;

            $dtyID = $row['dty_ID'];
            $rtyID = $row['rst_RecTypeID'];
            

            if(is_numeric($row['rst_DefaultValue']) && $row['rst_DefaultValue']>0){
                if($row['dty_Type']=='resource'){
                    
                        //check that record for resource field exists
                        $res2 = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$row['rst_DefaultValue']);    
                        if($res2>0){
                            //record exists - check that it fits constraints
                            if($row['dty_PtrTargetRectypeIDs']){
                                if(!in_array($res2, explode(',',$row['dty_PtrTargetRectypeIDs']))){
                                    $reason = ' Record type does not fit constraints';
                                }
                            }
                        }else{
                            //record does not exist
                            $reason = ' Record does not exist';
                        }
                }else{
                    //check that default term belongs to vocabulary
                    if(!VerifyValue::isValidTerm($row['dty_JsonTermIDTree'], null, $row['rst_DefaultValue'], $dtyID )){
                        $reason = ' Value does not belong to specified vocabulary';
                    }
                }
            }else{
                    $reason = ' Value is not numeric';
                    
            }
                
            
            if($reason){
                //clear wrong defult value
                $row['reason'] = $reason;
                $rtysWithInvalidDefaultValues[] = $row;    
                $mysqli->query('UPDATE defRecStructure set rst_DefaultValue=NULL where rst_ID='.$row['rst_ID']);
            }
            
        }
    }

    return array("terms"=>$dtysWithInvalidTerms, 
                 "terms_nonselectable"=>$dtysWithInvalidNonSelectableTerms, 
                 "rt_contraints"=>$dtysWithInvalidRectypeConstraint,
                 "rt_defvalues"=>$rtysWithInvalidDefaultValues);  //wrong default values
}

//
// searches for terms with missed parent and inverse term ids
// detect duplications on the same level (exact and with numbers)
//
function getTermsWithIssues($mysqli){
    
    global $TL;
    
    //terms with missed parents
    $query = 'SELECT t1.trm_ID FROM defTerms t1 left join defTerms t2 '
    .'on t1.trm_ParentTermID = t2.trm_ID where t1.trm_ParentTermID>0  and t2.trm_ID is null';

    $missed_parents = mysql__select_list2($mysqli, $query);
    
    //terms with missed inverse terms
    $query = 'SELECT t1.trm_ID FROM defTerms t1 left join defTerms t2 '
    .'on t1.trm_InverseTermId = t2.trm_ID where t1.trm_InverseTermId>0  and t2.trm_ID is null';

    $missed_inverse = mysql__select_list2($mysqli, $query);
    
    //find label duplications
    $all_dupes = array();
    $dupes = array(); //dupes for parent
    
    $parent_id = 0;
    $prev_id = 0;
    $prev_lbl = '';
    
    foreach ($TL as $trm_ID=>$trm){
        
        if($parent_id!=$trm['trm_ParentTermID']){
            if(count($dupes)>0 && $parent_id>0){
                $all_dupes[$parent_id] = $dupes;                  
            } 
            $parent_id = $trm['trm_ParentTermID'];
            $dupes = array(); //reset
            
            $prev_lbl = removeLastNum($trm['trm_Label']);
            $prev_id = $trm_ID;
        }else{ 
        
            $lbl = removeLastNum($trm['trm_Label']);
            if($lbl==$prev_lbl){
                if($prev_id>0){
                    $dupes[] = $prev_id; //$TL[$prev_id]['trm_Label'];
                    $prev_id = 0;
                }
                $dupes[] = $trm_ID;
            }else{
                $prev_lbl = $lbl;
                $prev_id = $trm_ID;
            }
        }
        
    }
    if(count($dupes)>0 && $parent_id>0){
        $all_dupes[$parent_id] = $dupes;                  
    } 
    
    
    

    return array(
        'trm_missed_parents'=>$missed_parents,
        'trm_missed_inverse'=>$missed_inverse,
        'trm_dupes'=>$all_dupes
    );
    
}


//
// function that translates all term ids in $formattedStringOfTermIDs to there local/imported value
//
function getInvalidTerms($formattedStringOfTermIDs, $is_tree) {
    global $TL;
    $invalidTermIDs = array();
    if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
        return array($invalidTermIDs, "");
    }

    $isvocabulary = false;
    $pos = strpos($formattedStringOfTermIDs,"{");

    //@todo similar code in getTermsFromFormat
    
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

//
//
//
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

//
// function that check the existance of all rectype ids in the passed string
//
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