<?php
    /** 
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0      
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    /**
    * Generates an array - list and treeview of fields for given recordtypes 
    * used in smarty editor, titlemask editor, facet search wizard
    *
    * dbs_ - prefix for functions
    * 
    * main function: rtt_GetRectypeStructureTree
    * 
    * Internal function
    *  __getRecordTypeTree
    *  __getDetailSection
    */

    $dbs_rtStructs = null;
    $dbs_lookups = null; //human readale field type names

    /**
    * Returns  
    * {
    *   id          : rectype id, field id in form fNNN, name of default rectype field
    *   title        : rectype or field display name
    *   type        : rectype|Relationship|field type|term
    *   children    : []  // array of fields
    * }
    * 
    * @param mixed $system
    * @param mixed $rectypeids
    * @param mixed $mode  5 - fields only (no header fields), 3 - all
    *    4 - for treeview, for faceted search (with type names)
    *    5 - for treeview, for faceted search (with type names) ONE level only - lazy load
    * 
    * @param mixed $fieldtypes - field types to be listed
    * @param mixed $parentcode
    */
    function dbs_GetRectypeStructureTree($system, $rectypeids, $mode, $fieldtypes=null, $parentcode=null){

        global $dbs_rtStructs, $dbs_lookups;
        
        $system->defineConstant('DT_PARENT_ENTITY');

        if($mode>=4) set_time_limit(0); //no limit
        
        if($fieldtypes==null){
            $fieldtypes = array('integer','date','freetext','year','float','enum','resource','relmarker');
        }else if(!is_array($fieldtypes)){
            $fieldtypes = explode(",",$fieldtypes);
        }

        //loads plain array for rectypes
        $dbs_rtStructs = dbs_GetRectypeStructures($system, ($mode==4||$mode==5)?null:$rectypeids, 1);  //need all
        $dbs_lookups = dbs_GetDtLookups();

        $rtypes = $dbs_rtStructs['names'];
        $res = array();
        
        
        
        $rectypeids = (!is_array($rectypeids)?explode(",", $rectypeids):$rectypeids);

        //create hierarchy tree 
        foreach ($rectypeids as $rectypeID){
                
                //find all parent recordtypes and modify fieldstype (add fake resource fields)
                __addParentResourceFields($rectypeID);
            
                $def = __getRecordTypeTree($system, $rectypeID, 0, $mode, $fieldtypes, null);
                if($def!==null) {
                    if($parentcode!=null){
                        if(@$def['code']){
                            $def['code'] = $parentcode.':'.$def['code'];
                        }else{
                            $def['code'] = $parentcode;
                        }
                    }
                    //debug $def['title'] = @$def['code'].$def['title'];   
                    //asign codes
                    if(is_array(@$def['children'])){
                        $def = __assignCodes($def);
                        array_push($res, $def);
                    }                    
                }
        }

        return $res;    
    }
    
    //
    // add parent code to children
    //
    function __assignCodes($def){
                        foreach($def['children'] as $idx => $det){
                            if(@$def['code']){

                                if(@$det['code']){
                                    $def['children'][$idx]['code'] = $def['code'].":".$det['code']; 
                                }else{
                                    $def['children'][$idx]['code'] = $def['code'];    
                                }
                            }
                            //debug $def['children'][$idx]['title'] = $def['children'][$idx]['code'].$det['title']; 
                                 
                            if(is_array(@$det['children'])){
                                   $def['children'][$idx] = __assignCodes($def['children'][$idx]);
                            }
                        }
                        return $def;
    }

    //
    // adds resource fields to parent 
    //
    function __addParentResourceFields($recTypeId){
        
        global $dbs_rtStructs;
        
        if(!(defined('DT_PARENT_ENTITY') && DT_PARENT_ENTITY>0)) return;
        
        $rst_fi = $dbs_rtStructs['typedefs']['dtFieldNamesToIndex'];
        $parent_Rts = array();
        
        foreach ($dbs_rtStructs['typedefs'] as $rtKey => $recstruct){
            if($rtKey>0){
                $details =  @$recstruct['dtFields'];
                
                if(!$details){
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
                  
            $dbs_rtStructs['typedefs'][$recTypeId][DT_PARENT_ENTITY] = $ffr;
        }        
        
    }
    
    
    //
    //   {rt_id: , rt_name, recID, recTitle, recModified, recURL, recWootText,
    //                  fNNN: 'display name of field', 
    //                  fNNN: array(termfield_name: , id, code:  )  // array of term's subfields
    //                  fNNN: array(rt_name: , recID ...... )       // unconstrained pointer or exact constraint
    //                  fNNN: array(array(rt_id: , rt_name, recID, recTitle ... ) //constrained pointers
    //     NNN - field type ID
    //
    // $mode
    //  3
    //  4 treeview faceted search
    //  5 treeview - lazy
    //  6 import csv   
    //
    // $pointer_fields - to avoid recursion for reverse pointers
    //
    function __getRecordTypeTree($system, $recTypeId, $recursion_depth, $mode, $fieldtypes, $pointer_fields){

        global $dbs_rtStructs;

        $res = array();
        $children = array();
        
        //add default fields
        if($mode<5 || ($mode==5 && $recursion_depth==0)){
            if($mode==3) array_push($children, array('key'=>'recID', 'type'=>'integer', 'title'=>'ID', 'code'=>$recTypeId.":id"));
            
            array_push($children, array('key'=>'recTitle',    'type'=>'freetext',  
                'title'=>"RecTitle <span style='font-size:0.7em'>(Constructed text)</span>", 
                'code'=>$recTypeId.":title", 'name'=>'Record title'));
            array_push($children, array('key'=>'recModified', 'type'=>'date',
                'title'=>"Modified  <span style='font-size:0.7em'>(Date)</span>", 'code'=>$recTypeId.":modified", 'name'=>'Record modified'));
                
            if($mode==3) {
                array_push($children, array('key'=>'recURL',      'type'=>'freetext',  'title'=>'URL', 'code'=>$recTypeId.":url"));
                array_push($children, array('key'=>'recWootText', 'type'=>'blocktext', 'title'=>'WootText', 'code'=>$recTypeId.":woot"));
                //array_push($children, array('key'=>'recTags',     'type'=>'freetext',  'title'=>'Tags', 'code'=>$recTypeId.":tags"));
            }
        }

        if($recTypeId && is_numeric($recTypeId)){

            if(!@$dbs_rtStructs['typedefs'][$recTypeId]){
                //this rectype is not loaded yet - load it
                $rt0 = dbs_GetRectypeStructures($system, $recTypeId, 1);
                if($rt0){ //merge with $dbs_rtStructs 
                    $dbs_rtStructs['typedefs'][$recTypeId] = $rt0['typedefs'][$recTypeId];    
                    $dbs_rtStructs['names'][$recTypeId] = $rt0['names'][$recTypeId];
                }else{
                    return null;
                }
            }

            $res['key'] = $recTypeId;
            $res['title'] = $dbs_rtStructs['names'][$recTypeId];
            $res['type'] = 'rectype';
                                                                                                              
            if(@$dbs_rtStructs['typedefs'][$recTypeId] && ($mode!=5 || $recursion_depth==0)){
                $details =  @$dbs_rtStructs['typedefs'][$recTypeId]['dtFields'];
                if(!$details) $details = array(); //rectype without fields - exceptional case
                
                $children_links = array();
                $new_pointer_fields = array();

                foreach ($details as $dtID => $dtValue){
                    
                    if($dtValue[$dbs_rtStructs['typedefs']['dtFieldNamesToIndex']['rst_RequirementType']]=='forbidden') continue;

                    $dt_type = $dtValue[$dbs_rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type']];
                    if($dt_type=='resource' || $dt_type=='relmarker'){
                            array_push($new_pointer_fields, $dtID);
                    }
                    
                    $res_dt = __getDetailSection($system, $recTypeId, $dtID, $recursion_depth, $mode, 
                                                            $fieldtypes, null, $new_pointer_fields);
                    if($res_dt){
                        
                        if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){
                            array_push($children_links, $res_dt);
                        }else{
                            array_push($children, $res_dt);
                        }
                        /*
                        if(is_array($res_dt) && count($res_dt)==1){
                        $res["f".$dtID] = $res_dt[0];    
                        }else{
                        //multi-constrained pointers or simple variable
                        $res["f".$dtID] = $res_dt;
                        }
                        */
                    }
                }//for
                
                //add resource and relation at the end of result array
                $children = array_merge($children, $children_links);
                
                //find all reverse links and relations
                if( ($mode==4 && $recursion_depth<2) || ($mode==5 && $recursion_depth==0) ){
                    $reverse_rectypes = __getReverseLinkedRecordTypes($recTypeId);
                    
                    foreach ($reverse_rectypes as $rtID => $dtID){
                        //$dtValue =  $dbs_rtStructs['typedefs'][$rtID]['dtFields'][$dtID];
                        if( $pointer_fields==null || (is_array($pointer_fields) && !in_array($dtID, $pointer_fields)) ){  // to avoid recursion
                            $res_dt = __getDetailSection($system, $rtID, $dtID, $recursion_depth, $mode, $fieldtypes, $recTypeId, null);
             
                            if($res_dt){
                                array_push($children, $res_dt);
                            }
                        }
                    }
                }
            }
            if($mode==3 && $recursion_depth==0){
                array_push($children, __getRecordTypeTree($system, 'Relationship', $recursion_depth+1, $mode, $fieldtypes, null));
            }   

        }else if($recTypeId=="Relationship") {

            $res['title'] = "Relationship";
            $res['type'] = "relationship";

            //add specific Relationship fields
            array_push($children, array('key'=>'recRelationType', 'title'=>'RelationType'));
            array_push($children, array('key'=>'recRelationNotes', 'title'=>'RelationNotes'));
            array_push($children, array('key'=>'recRelationStartDate', 'title'=>'RelationStartDate'));
            array_push($children, array('key'=>'recRelationEndDate', 'title'=>'RelationEndDate'));
        }

        
        if($mode!=5 || $recursion_depth==0){
            $res['children'] = $children;
        }

        return $res;
    }
    
    //
    // returns array of record types that are linked to given record type
    //
    function __getReverseLinkedRecordTypes($rt_ID){
        
        global $dbs_rtStructs;
        
        //find all reverse links (pointers and relation that point to selected rt_ID)
        $alldetails = $dbs_rtStructs['typedefs'];
        $fi_type = $alldetails['dtFieldNamesToIndex']['dty_Type'];
        $fi_rectypes = $alldetails['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        
        $arr_rectypes = array();
        
        foreach ($alldetails as $recTypeId => $details){
        
            if(is_numeric($recTypeId) && $recTypeId!=$rt_ID){ //not itself
                
                $details = @$dbs_rtStructs['typedefs'][$recTypeId]['dtFields'];
                if(!is_array($details)) continue;
                
                foreach ($details as $dtID => $dtValue){
                
                        if(($dtValue[$fi_type]=='resource' || $dtValue[$fi_type]=='relmarker')){

                                //find constraints
                                $constraints = $dtValue[$fi_rectypes];
                                $constraints = explode(",", $constraints);
                                //verify that selected record type is in this constaint
                                if(count($constraints)>0 && in_array($rt_ID, $constraints) && !@$arr_rectypes[$recTypeId] ){
                                    $arr_rectypes[$recTypeId] = $dtID;
                                }
                        }
                }
            }
        }
        
        return  $arr_rectypes;
        
    }

    /*
    $dtID   - detail type ID

    $dtValue - record type structure definition

    returns display name  or if enum array

    $mode - 3 all, 4,5 for treeview (5 lazy) , 6 - for import csv(dependencies)
    */
    function __getDetailSection($system, $recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, $reverseRecTypeId, $pointer_fields){

        global $dbs_rtStructs, $dbs_lookups;    

        $res = null;

        $rtNames = $dbs_rtStructs['names']; //???need
        $rst_fi = $dbs_rtStructs['typedefs']['dtFieldNamesToIndex'];

        $dtValue = $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'][$dtID];

        $detailType = $dtValue[$rst_fi['dty_Type']];
        $dt_label   = $dtValue[$rst_fi['rst_DisplayName']];
        $dt_title   = $dtValue[$rst_fi['rst_DisplayName']];
        $dt_tooltip = $dtValue[$rst_fi['rst_DisplayHelpText']]; //help text

        $pref = "";
        //$dt_maxvalues = $dtValue[$rst_fi['rst_MaxValues']]; //repeatable
        //$issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)==1)?"true":"false";
        
        if (($mode==3) ||  in_array($detailType, $fieldtypes)) //$fieldtypes - allowed types
        {
            
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
                return null;
            case 'enum':

                $res = array();
                if($mode==3){
                    $res['children'] = array(
                        array("text"=>"internalid"),
                        array("text"=>"code"),
                        array("text"=>"term"),
                        array("text"=>"conceptid"));
                }
                break;

            case 'resource': // link to another record type
            case 'relmarker':
            
                $max_depth = 2;
                if ($mode==6 || $mode==4)
                   $max_depth = 3;
                else if ($mode==5) //make it 1 for lazy load
                   $max_depth = 1; 
                                                                
                if($recursion_depth<$max_depth){
                    
                    if($reverseRecTypeId!=null){
                            $res = __getRecordTypeTree($system, $recTypeId, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                            if($res){
                                $res['rt_ids'] = "".$recTypeId; //list of rectype - constraint
                                //$res['reverse'] = "yes";
                                $pref = ($detailType=="resource")?"lf":"rf";
                                //before 2017-06-20 $dt_title = " <span style='font-style:italic'>" . $rtNames[$recTypeId] ."  ". $dt_title . "</span>";

                                $dt_title = "<span>&lt;&lt; <span style='font-weight:bold'>" . $rtNames[$recTypeId] ."</span> . ". $dt_title.'</span>';
                                
                                if($mode==5){
                                    $res['lazy'] = true;
                                }
                            }
                    }else{

                            $pref = ($detailType=="resource")?"lt":"rt";

                            $pointerRecTypeId = @$dtValue[$rst_fi['rst_PtrFilteredIDs']];
                            if($pointerRecTypeId==null) $pointerRecTypeId = '';
                            $is_required      = ($dtValue[$rst_fi['rst_RequirementType']]=='required');
                            $rectype_ids = explode(",", $pointerRecTypeId);

                             
                            if($mode==4 || $mode==5){
                                /*
                                if($pointerRecTypeId=="" || count($rectype_ids)==0){ //TEMP
                                     $dt_title .= ' unconst';
                                }
                                */
                                
                                $dt_title = " <span style='font-style:italic'>" . $dt_title . "</span>";
                            }
                            
                            
                            if($pointerRecTypeId=="" || count($rectype_ids)==0){ //unconstrainded

                                $res = __getRecordTypeTree($system, null, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                //$res['constraint'] = 0;

                            }else{ //constrained pointer

                                $res = array();


                                if(is_array($rectype_ids) && count($rectype_ids)>1){
                                    $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    $res['constraint'] = count($rectype_ids);
                                    if($mode!=5) $res['children'] = array();
                                }
                                if($mode==5){
                                    $res['rt_ids'] = $pointerRecTypeId;
                                    $res['lazy'] = true;
                                }else{
                                
                                    foreach($rectype_ids as $rtID){
                                        $rt_res = __getRecordTypeTree($system, $rtID, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                        if(is_array($rectype_ids) && count($rectype_ids)==1){//exact one rectype constraint
                                            //avoid redundant level in tree
                                            $res = $rt_res;
                                            $res['constraint'] = 1;
                                            $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                        }else if($rt_res!=null){
                                            array_push($res['children'], $rt_res);
                                            $res['constraint'] = count($rt_res);
                                        }
                                    }
                                
                                }
                            
                            }
                            $res['required'] = $is_required;

                    }
                }

                break;

            default:
                    $res = array();
        }//end switch
        }

        if(is_array($res)){

            if(!@$res['code']) $res['code'] = (($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId).":".$pref.$dtID;  //(($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)
            $res['key'] = "f:".$dtID;
            if($mode==4 || $mode==5){
                    
                $stype = ($detailType=='resource' || $detailType=='relmarker')?"":$dbs_lookups[$detailType];
                if($reverseRecTypeId!=null){
                    //before 2017-06-20  $stype = $stype."linked from";
                    $res['isreverse'] = 1;
                }
                if($stype!=''){
                    $stype = " <span style='font-size:0.7em'>(" . $stype .")</span>";   
                }
                
                $res['title'] = $dt_title . $stype;
                //$res['code'] = 
            }else{
                $res['title'] = $dt_title;    
            }
            $res['type'] = $detailType;
            $res['name'] = $dt_label;
            
            
            
        }            
        return $res;
    }

