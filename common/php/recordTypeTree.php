<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* recordTypeTree.php
*
* Generates an array - list and treeview of fields for given recordtype to be used in smarty editor and titlemask editor
*
* the result is JSON array
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/getSearchResults.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');


    mysql_connection_overwrite(DATABASE);
    //was mysql_connection_select(DATABASE);

    //load definitions (USE CACHE)
    $rtStructs = getAllRectypeStructures(true);
    //$dtStructs = getAllDetailTypeStructures(true);
    //$dtTerms = getTerms(true);
    //$first_record = null;  //to obtain names of record header fields

    $resVars = array();
    //$resVarsByType = array();

    $mode = @$_REQUEST['mode'];
    
    if($mode=='list'){
        
        $rectypeIDs = explode(',', @$_REQUEST['rty_id']);
        foreach ($rectypeIDs as $rectypeID){
              $res = getRecordTypeTree($rectypeID, 0);
              array_push($resVars, $res);
        }        
        
        header("Content-type: text/javascript");
        echo json_format($resVars, true);
        
    }else{
    
    
    $isvarname = ($mode=='varsonly');

    $_REQUEST['limit'] = 100;
    $qresult = loadSearch($_REQUEST); //from search/getSearchResults.php - loads array of records based on GET request
    
    if($isvarname){

        $rectypeID = @$_REQUEST['rty_id'];
        $res = getRecordTypeTree($rectypeID, 0);
        
        header("Content-type: text/javascript");
        echo json_format( array("vars"=>$res,
                                "records"=>$qresult["records"]), true);

    }else{

            if(!array_key_exists('records',$qresult)){
                echo "Empty query. Cannot generate template";
                exit();
            }

            //convert to array that will assigned to smarty variable
            $records =  $qresult["records"];

            $recTypes = array();

            //find all record type in result set
            foreach ($records as $rec){

                //generate entry for each record type only once
                $rectypeID = $rec['rec_RecTypeID'];
                $key = array_search($rectypeID, $recTypes);
                if( !(is_numeric($key) && $key>=0) )
                {
                    $res = getRecordTypeTree($rectypeID, 0);

                    //text for rectypes fill be inserted manually:  $resText  = $resText.$res['text'];
                    array_push($resVars, $res);

                    array_push($recTypes, $rectypeID);
                }
            }

            header("Content-type: text/javascript");
            echo json_format($resVars, true);
    }
    }

exit();

//
//   {rt_id: , rt_name, recID, recTitle, recModified, recURL, ecWootText,
//                  fNNN:'name', 
//                  fNNN:array(termfield_name: , id, code:  )
//                  fNNN:array(rt_name: , recID ...... ) //unconstrained pointer or exact constraint
//                  fNNN:array(array(rt_id: , rt_name, recID, recTitle ... ) //constrined pointers
//
function getRecordTypeTree($recTypeId, $recursion_depth){

    global $rtStructs, $mode;
    
    $res = array();
    
    if($recTypeId && is_numeric($recTypeId)){
        $res['rt_id'] = $recTypeId;
        $res['rt_name'] = $rtStructs['names'][$recTypeId];
    }
    
    //add default fields
    $res['recID'] = 'Record ID';
    $res['recTitle'] = 'Record Title';
    $res['recTypeID'] = 'Record TypeID';
    $res['recTypeName'] = 'Record TypeName';
    $res['recURL'] = 'Record URL';
    $res['recModified'] = 'Record Modified';
    $res['recWootText'] = 'Record WootText';
    
    if($recTypeId=="Relationship") {
        //add specific Relationship fields
            $res['rt_name'] = "Relationship";
            $res['recRelationType'] = 'RelationType';
            $res['recRelationNotes'] = 'RelationNotes';
            $res['recRelationStartDate'] = 'RelationStartDate';
            $res['recRelationEndDate'] = 'RelationEndDate';
            //__addvar("RelationInterpretation");
            $recTypeId = null;
    }    
    
    
    
    //find ALL parent recordtypes
    $rst_fi = $rtStructs['typedefs']['dtFieldNamesToIndex'];
    $parent_Rts = array();
    
    foreach ($rtStructs['typedefs'] as $rtKey => $recstruct){
        if($rtKey>0){
            $details =  @$recstruct['dtFields'];
            
            if(!$details){
//error_log($rtKey.'  '.print_r($recstruct, true));
                continue;
            }
            
            foreach ($details as $dtKey => $dtValue){
                
                if($dtValue[$rst_fi['rst_RequirementType']]=='forbidden') continue;
                
                if($dtValue[$rst_fi['dty_Type']]=='resource' && $dtValue[$rst_fi['rst_CreateChildIfRecPtr']]==1){
                    
                    $constraint = $dtValue[$rst_fi['rst_PtrFilteredIDs']];
                    if($constraint && in_array($recTypeId, explode(',',$constraint)) && !in_array($rtKey, $parent_Rts) ){
                        array_push($parent_Rts, $rtKey);    
                        //break;
                    }
                    
                }
            }
        }
    }
    if(count($parent_Rts)>0){
        //$res['recParent'] = 'Record Parent';
        $dtKey = DT_PARENT_ENTITY;
        
        //create fake rectype structure field
        $ffr = array();
        $ffr[$rst_fi['rst_DisplayName']] = 'Parent entity';//'Record Parent ('.$rtStructs['names'][$parent_Rt].')';
        $ffr[$rst_fi['rst_PtrFilteredIDs']] = implode(',',$parent_Rts);
        $ffr[$rst_fi['dty_Type']] = 'resource';
        $ffr[$rst_fi['rst_DisplayHelpText']] = 'Reverse pointer to parent record';
        $ffr[$rst_fi['rst_RequirementType']] = 'optional';
              
        $rtStructs['typedefs'][$recTypeId][DT_PARENT_ENTITY] = $ffr;
        
        $res_dt = getDetailSection($dtKey, $ffr, $recursion_depth);
        if($res_dt){
           if(is_array($res_dt) && count($res_dt)==1){
               $res["f".$dtKey] = $res_dt[0];    
           }else{
               //multi-constrained pointers or simple variable
               $res["f".$dtKey] = $res_dt;
           }
        }
    }
    
    //get the list of details from record structure
    if($recTypeId && @$rtStructs['typedefs'][$recTypeId] && @$rtStructs['typedefs'][$recTypeId]['dtFields'])
    {
        $details =  $rtStructs['typedefs'][$recTypeId]['dtFields'];

        foreach ($details as $dtKey => $dtValue){

            $res_dt = getDetailSection($dtKey, $dtValue, $recursion_depth);
            if($res_dt){
                   if(is_array($res_dt) && count($res_dt)==1){
                       $res["f".$dtKey] = $res_dt[0];    
                   }else{
                       //multi-constrained pointers or simple variable
                       $res["f".$dtKey] = $res_dt;
                   }
            }
        }//for
    }//else unconstrained
    else {
        $res['remark'] = 'Unconstrained pointer; constrain to record type to see field';    
    }
    
    if($recursion_depth==0){
        $res["Relationship"] = getRecordTypeTree('Relationship', $recursion_depth+1);
    }   

    return $res;
}


/*
 $dtKey     - detail type ID

 $dtValue - record type structure definition

 returns display name
    or if enum
 
 
*/
function getDetailSection($dtKey, $dtValue, $recursion_depth){

    global $rtStructs, $mode;
    
    $res = null;

    if(true){//}@$dtStructs['typedefs'][$dtKey]){
    
            $rtNames = $rtStructs['names']; //???need
            $rst_fi = $rtStructs['typedefs']['dtFieldNamesToIndex'];
        
            if($dtValue[$rst_fi['rst_RequirementType']]=='forbidden') return null;
        
            $detailType = $dtValue[$rst_fi['dty_Type']];
            $dt_label   = $dtValue[$rst_fi['rst_DisplayName']];
            $dt_tooltip = $dtValue[$rst_fi['rst_DisplayHelpText']]; //help text
        

            //$dt_maxvalues = $dtValue[$rst_fi['rst_MaxValues']]; //repeatable
            //$issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)==1)?"true":"false";

            switch ($detailType) {
            /* @TODO
            case 'file':
            break;
            case 'geo':
            break;
            case 'calculated':
            break;
            case 'fieldsetmarker':
            break;
            case 'relationtype':
            */
            case 'separator':
            case 'relmarker':
                    return null;
            case 'enum':
            case 'relationtype':

                $res = array(
                    "termfield_name" => $dt_label,
                    "term"=>"Term", //was label
                    "code"=>"Code",
                    "conceptid"=>"Concept ID",
                    "internalid"=>"Internal ID"); //was id
                
            break;

            case 'resource': // link to another record type

                //load this record
                if($recursion_depth<2){ // && ($mode=='varsonly' || array_key_exists($pointerRecTypeId, $rtNames))) {

                    $pointerRecTypeId = $dtValue[$rst_fi['rst_PtrFilteredIDs']]; //@TODO!!!!! it may be comma separated string
                    
                    if($pointerRecTypeId==""){ //unconstrainded
                    
                        $res = getRecordTypeTree(null, $recursion_depth+1);
                        $res['rt_name'] = $dt_label;
                    
                    }else{
                         $rectype_ids = explode(",", $pointerRecTypeId);
                         $res = array();
                         if(count($rectype_ids)>1){
                            array_push($res, $dt_label);
                         }
                         foreach($rectype_ids as $rtID){
                            $rt_res = getRecordTypeTree($rtID, $recursion_depth+1);
                            if(count($rectype_ids)==1){
                                $rt_res['rt_name'] = $dt_label;
                            } 
                            array_push($res, $rt_res);
                         }
                         
                         
                    }

                }

            break;

            default:
                $res =  $dt_label;
            }//end switch

    }

    return $res;
}


?>
