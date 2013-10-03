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
                    $invalidTerms = getInvalidTerms($dty['dty_JsonTermIDTree']);
                    if (count($invalidTerms)){
                        $dtysWithInvalidTerms[$dtyID] = $dty;
                        $dtysWithInvalidTerms[$dtyID]['invalidTermIDs'] = $invalidTerms;
                    }
                }
                if ($dty['dty_TermIDTreeNonSelectableIDs'])
                {
                    $invalidNonSelectableTerms = getInvalidTerms($dty['dty_TermIDTreeNonSelectableIDs']);
                    if (count($invalidNonSelectableTerms)){
                        $dtysWithInvalidNonSelectableTerms[$dtyID] = $dty;
                        $dtysWithInvalidNonSelectableTerms[$dtyID]['invalidNonSelectableTermIDs'] = $invalidNonSelectableTerms;
                    }
                }
                /*****DEBUG****///error_log("selectable - ".print_r($dtysWithInvalidNonSelectableTerms,true));
                if ($dty['dty_PtrTargetRectypeIDs']){
                    $invalidRectypes = getInvalidRectypes($dty['dty_PtrTargetRectypeIDs']);
                    if (count($invalidRectypes)){
                        $dtysWithInvalidRectypeConstraint[$dtyID] = $dty;
                        $dtysWithInvalidRectypeConstraint[$dtyID]['invalidRectypeConstraint'] = $invalidRectypes;
                    }
                }
            }//for
  
    return array("terms"=>$dtysWithInvalidTerms, "terms_nonselectable"=>$dtysWithInvalidNonSelectableTerms, "rt_contraints"=>$dtysWithInvalidRectypeConstraint);
}

// function that translates all term ids in the passed string to there local/imported value
function getInvalidTerms($formattedStringOfTermIDs) {
    global $TL;
    $invalidTermIDs = array();
    if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
        return $invalidTermIDs;
    }

    if (strpos($formattedStringOfTermIDs,"{")!== false) {
        /*****DEBUG****///error_log( "term tree string = ". $formattedStringOfTermIDs);
        $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
        if (strrpos($temp,":") == strlen($temp)-1) {
            $temp = substr($temp,0, strlen($temp)-1);
        }
        $termIDs = explode(":",$temp);
    } else {
        /*****DEBUG****///error_log( "term array string = ". $formattedStringOfTermIDs);
        $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
        $termIDs = explode(",",$temp);
    }
    // Validate termIDs
    
    foreach ($termIDs as $trmID) {
        // check that the term valid
        if (!$trmID ){ // invalid trm ID null or 0 is not allowed
            if(count($termIDs)>1){
                array_push($invalidTermIDs,"blank");    
            }
        }else if ( !@$TL[$trmID]){ // invalid trm ID
            array_push($invalidTermIDs,$trmID);
        }
    }
    return $invalidTermIDs;
}

// function that check the existaance of all rectype ids in the passed string
function getInvalidRectypes($formattedStringOfRectypeIDs) {
    global $RTN;
    $invalidRectypeIDs = array();
    if (!$formattedStringOfRectypeIDs || $formattedStringOfRectypeIDs == "") {
        return $invalidRectypeIDs;
    }

    $rtyIDs = explode(",",$formattedStringOfRectypeIDs);
    // Validate rectypeIDs
    foreach ($rtyIDs as $rtID) {
        // check that the rectype is valid
        if (!@$RTN[$rtID]){ // invalid rty ID
            array_push($invalidRectypeIDs,$rtID);
        }
    }
    return $invalidRectypeIDs;
}

?>