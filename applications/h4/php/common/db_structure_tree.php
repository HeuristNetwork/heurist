<?php
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

/**
* Returns  
* {
*   id          : rectype id, field id in form fNNN, name of default rectype field
*   text        : rectype or field display name
*   type        : rectype|Relationship|field type|term
*   children    : []  // array of fields
* }
* 
* @param mixed $system
* @param mixed $rectypeids
* @param mixed $mode  3 - all, 4 - limited for faceted search
*/
function dbs_GetRectypeStructureTree($system, $rectypeids, $mode){
   
    global $dbs_rtStructs;
    
    $dbs_rtStructs = dbs_GetRectypeStructures($system, $rectypeids, 1);    
    
    $rtypes = $dbs_rtStructs['names'];
    $res = array();
    
    foreach ($rtypes as $rectypeID=>$rectypeName){
            array_push($res, __getRecordTypeTree($system, $rectypeID, 0, $mode));    
    }
    
    return $res;    
}

//
//   {rt_id: , rt_name, recID, recTitle, recModified, recURL, ecWootText,
//                  fNNN: 'display name of field', 
//                  fNNN: array(termfield_name: , id, code:  )  // array of term's subfields
//                  fNNN: array(rt_name: , recID ...... )       // unconstrained pointer or exact constraint
//                  fNNN: array(array(rt_id: , rt_name, recID, recTitle ... ) //constrined pointers
//     NNN - field type ID
function __getRecordTypeTree($system, $recTypeId, $recursion_depth, $mode){

    global $dbs_rtStructs;
    
    $res = array();
    $children = array();
    //add default fields
    if($mode==3) array_push($children, array('key'=>'recID',       'title'=>'ID'));
    array_push($children, array('key'=>'recTitle',    'title'=>'RecTitle'));
    array_push($children, array('key'=>'recModified', 'title'=>'Modified'));
    if($mode==3) {
    array_push($children, array('key'=>'recURL',      'title'=>'URL'));
    array_push($children, array('key'=>'recWootText', 'title'=>'WootText'));
    }
 
    if($recTypeId && is_numeric($recTypeId)){
        
            if(!@$dbs_rtStructs['typedefs'][$recTypeId]){
                 //this rectype is not loaded yet - load it
                 $rt0 = dbs_GetRectypeStructures($system, $recTypeId, 1);
                 if(rt0){ //merge with $dbs_rtStructs 
                        $dbs_rtStructs['typedefs'][$recTypeId] = $rt0['typedefs'][$recTypeId];    
                        $dbs_rtStructs['names'][$recTypeId] = $rt0['names'][$recTypeId];
                 }
            }
            
            $res['key'] = $recTypeId;
            $res['title'] = $dbs_rtStructs['names'][$recTypeId];
            $res['type'] = 'rectype';
        
            
            if(@$dbs_rtStructs['typedefs'][$recTypeId]){
                $details =  $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'];

                foreach ($details as $dtID => $dtValue){

                    $res_dt = __getDetailSection($system, $dtID, $dtValue, $recursion_depth, $mode);
                    if($res_dt){
                           array_push($children, $res_dt);
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
            }
            if($mode==3 && $recursion_depth==0){
                array_push($children, __getRecordTypeTree($system, 'Relationship', $recursion_depth+1, $mode));
            }   
        
    }else if($recTypeId=="Relationship") {
        
        $res['title'] = "Relationship";
        $res['type'] = "Relationship";
        
        //add specific Relationship fields
        array_push($children, array('key'=>'recRelationType', 'title'=>'RelationType'));
        array_push($children, array('key'=>'recRelationNotes', 'title'=>'RelationNotes'));
        array_push($children, array('key'=>'recRelationStartDate', 'title'=>'RelationStartDate'));
        array_push($children, array('key'=>'recRelationEndDate', 'title'=>'RelationEndDate'));
    }
    
    $res['children'] = $children;

    return $res;
}

/*
 $dtID   - detail type ID

 $dtValue - record type structure definition

 returns display name  or if enum array
*/
function __getDetailSection($system, $dtID, $dtValue, $recursion_depth, $mode){

    global $dbs_rtStructs;    
    
    $res = null;

            $rtNames = $dbs_rtStructs['names']; //???need
            $rst_fi = $dbs_rtStructs['typedefs']['dtFieldNamesToIndex'];
        
            
            $detailType = $dtValue[$rst_fi['dty_Type']];
            $dt_label   = $dtValue[$rst_fi['rst_DisplayName']];
            $dt_tooltip = $dtValue[$rst_fi['rst_DisplayHelpText']]; //help text

            //$dt_maxvalues = $dtValue[$rst_fi['rst_MaxValues']]; //repeatable
            //$issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)==1)?"true":"false";
//error_log("1>>>".$mode."  ".$detailType."  ".$dt_label);

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
                        array("text"=>"id"),
                        array("text"=>"code"),
                        array("text"=>"label"),
                        array("text"=>"conceptid"));
                }
            break;

            case 'resource': // link to another record type
            case 'relmarker':

               if($recursion_depth<2){

                    $pointerRecTypeId = $dtValue[$rst_fi['rst_PtrFilteredIDs']];
                    $rectype_ids = explode(",", $pointerRecTypeId);
                    
                    if($pointerRecTypeId=="" || count($rectype_ids)==0){ //unconstrainded
                    
                        $res = __getRecordTypeTree($system, null, $recursion_depth+1, $mode);
                    
                    }else{ //constrained pointer
                         
                         $res = array();
                         
                         if(count($rectype_ids)>1){
                            $res['children'] = array();
                         }
                         
                         foreach($rectype_ids as $rtID){
                            $rt_res = __getRecordTypeTree($system, $rtID, $recursion_depth+1, $mode);
                            if(count($rectype_ids)==1){//exact one rectype constraint
                                //avoid redundant level in tree
                                $res = $rt_res;
                                $res['rt_id'] = $rtID;
                            }else{
//error_log("<<<".print_r($rt_res,true));
                                
                                array_push($res['children'], $rt_res);
                            }
                         }
                         
                         
                    }
                }

            break;

            default:
//error_log("2>>>".$mode."  ".$detailType."  ".$dt_label."   ".($detailType=='float'));            
                if (($mode==3) ||
                     ($detailType=='integer') || ($detailType=='date') || 
                     ($detailType=='year') || ($detailType=='float'))
                {
//error_log("!!!!!!!!!");                    
                        $res = array();
                }
            }//end switch

//error_log("3>>>>".is_array($res)."<  ".$detailType."  ".$dt_label);
   if(is_array($res)){

       $res['key'] = "f".$dtID;
       $res['title'] = $dt_label;
       $res['type'] = $detailType;
   }            
   return $res;
}

