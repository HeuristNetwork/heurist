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

$rtStructs = null;
$system = null;

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
*/
function dbs_GetRectypeStructureTree($system_, $rectypeids){
   
    global $rtStructs, $system;
    
    $rtStructs = dbs_GetRectypeStructures($system, $rectypeids, 1);    
    $system = $system_;
    
    $rtypes = $rtStructs['names'];
    $res = array();
    
    foreach ($rtypes as $rectypeID=>$rectypeName){
            array_push($res, __getRecordTypeTree($rectypeID, 0));    
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
function __getRecordTypeTree($recTypeId, $recursion_depth){

    global $rtStructs, $system;
    
    $res = array();
    $children = array();
    //add default fields
    array_push($children, array('key'=>'recID',       'title'=>'ID'));
    array_push($children, array('key'=>'recTitle',    'title'=>'RecTitle'));
    array_push($children, array('key'=>'recURL',      'title'=>'URL'));
    array_push($children, array('key'=>'recModified', 'title'=>'Modified'));
    array_push($children, array('key'=>'recWootText', 'title'=>'WootText'));
 
    if($recTypeId && is_numeric($recTypeId)){
        
            $res['key'] = $recTypeId;
            $res['title'] = $rtStructs['names'][$recTypeId];
            $res['type'] = 'rectype';
        
        
            if(!@$rtStructs['typedefs'][$recTypeId]){
                 //this rectype is not loaded yet - load it
                 $rt0 = dbs_GetRectypeStructures($system, $recTypeId, 1);
                 if(rt0){ //merge with $rtStructs 
                        $rtStructs['typedefs'][$recTypeId] = $rt0['typedefs'][$recTypeId];    
                        $rtStructs['names'][$recTypeId] = $rt0['names'][$recTypeId];
                 }
            }
            if(@$rtStructs['typedefs'][$recTypeId]){
                $details =  $rtStructs['typedefs'][$recTypeId]['dtFields'];

                foreach ($details as $dtID => $dtValue){

                    $res_dt = __getDetailSection($dtID, $dtValue, $recursion_depth);
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
            if($recursion_depth==0){
                array_push($children, __getRecordTypeTree('Relationship', $recursion_depth+1));
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
function __getDetailSection($dtID, $dtValue, $recursion_depth){

    global $rtStructs;    
    
    $res = null;

            $rtNames = $rtStructs['names']; //???need
            $rst_fi = $rtStructs['typedefs']['dtFieldNamesToIndex'];
        
            
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
                    return null;
            case 'enum':

                $res = array();
                $res['children'] = array(
                        array("text"=>"id"),
                        array("text"=>"code"),
                        array("text"=>"label"),
                        array("text"=>"conceptid"));
                
            break;

            case 'resource': // link to another record type
            case 'relmarker':

               if($recursion_depth<2){

                    $pointerRecTypeId = $dtValue[$rst_fi['rst_PtrFilteredIDs']];
                    $rectype_ids = explode(",", $pointerRecTypeId);
                    
                    if($pointerRecTypeId=="" || count($rectype_ids)==0){ //unconstrainded
                    
                        $res = __getRecordTypeTree(null, $recursion_depth+1);
                    
                    }else{ //constrained pointer
                         
                         $res = array();
                         
                         if(count($rectype_ids)>1){
                            $res['children'] = array();
                         }
                         
                         foreach($rectype_ids as $rtID){
                            $rt_res = __getRecordTypeTree($rtID, $recursion_depth+1);
                            if(count($rectype_ids)==1){//exact one rectype constraint
                                //avoid redundant level in tree
                                $res = $rt_res;
                                $res['rt_id'] = $rtID;
                            }else{
                                array_push($res['children'], $rt_res);
                            }
                         }
                         
                         
                    }
                }

            break;

            default:
                $res = array();
            }//end switch

   if($res){
       $res['key'] = "f".$dtID;
       $res['title'] = $dt_label;
       $res['type'] = $detailType;
   }            
   return $res;
}

