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
* importRectype.php - import rectype from another Heurist database
* 
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  
*/

//heur-db-pro-1.ucc.usyd.edu.au/HEURIST/h3-ao/admin/structure/import/importRectype.php?&db=artem_delete1&id=1126-15

define('ISSERVICE',1);
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../../saveStructureLib.php');

//temp header("Content-type: text/javascript");

// User must be system administrator or admin of the owners group for this database
if(!(is_logged_in() && is_admin())){
    error_exit("Sorry, you need to be a database owner to be able to modify the database structure");
}

$excludeDuplication = (@$_REQUEST["dup"]=="1");

//combination of db and rectype id
$code = @$_REQUEST["id"]; 
if(!$code){
    error_exit("Rectype code not defined");
}
$code = explode("-",$code);
$database_id = @$code[0];
$rectype_id = @$code[1];
if(!(is_numeric($database_id) && is_numeric($rectype_id))){
    error_exit("Rectype has wrong format");
}

//1. get database url ------------------------------------------------------------------------------
//2. get definitions from remote database ----------------------------------------------------------
//3. Add the record type as the first element in a list.
//4. Parse record types in the list to identify any constrained record pointers or relmarkers to other record types. 
// Add these record types to the list. Call step repeatedly until no new record types are added to the list 
// Limit maximum number of repeats to 10
//5. With list of all record types, build a list of all the base field types:
//6. With list of all base field types: Return data for all terms used ------------------------------
//7. Perform database action - add reccords, structure, fields and terms into our database



//1. get database url ------------------------------------------------------------------------------
$reg_url =   HEURIST_INDEX_BASE_URL  . "admin/setup/dbproperties/getDatabaseURL.php" . 
                    "?db=H3MasterIndex&id=".$database_id;
$data = loadRemoteURLContent($reg_url);                    
if (!$data) {                            
    error_exit("Unable to contact Heurist master index, possibly due to timeout or proxy setting<br />".
        "URL requested: ".$reg_url);
}
$data = json_decode($data, true);
if(!@$data['rec_URL']){
    error_exit("Heurist master index returns wrong data for registered db #".$database_id);
}
//2. get definitions from remote database ----------------------------------------------------------
$reg_url = $data['rec_URL'];
$reg_url = explode("?",$reg_url);

$url_params = @$reg_url[1];
$reg_url = @$reg_url[0];
if(!$url_params || !$reg_url){
    error_exit("Heurist master index returns wrong data for registered db #".$database_id);
}

$reg_url = $reg_url."common/php/reloadCommonInfo.php?".$url_params;
$defs = loadRemoteURLContent($reg_url);                    
if (!$defs) {                            
    error_exit("Unable to contact source database, possibly due to timeout or proxy setting");    
}
$defs = json_decode($defs, true);
if (!($defs['rectypes'] && $defs['detailTypes'] && $defs['terms'])) {                            
    error_exit("Defintions from source database are invalid");    
}

//DEBUG print $data;

//target(local) definitions
$trg_rectypes = getAllRectypeStructures();
$trg_fieldtypes = getAllDetailTypeStructures();
$trg_terms = getTerms();

//definitions to be imported - list of ids
$imp_recordtypes = array();
$imp_fieldtypes = array();
$imp_terms = array("enum"=>array(), "relation"=>array());

//source id => target id
$fields_correspondence = array();
$rectypes_correspondence = array(); 
$terms_correspondence = array("enum"=>array(), "relation"=>array());
$group_ft_ids = array();
$group_rt_ids = array();


//3. Add the record type as the first element in a list.
if(!@$defs['rectypes']['typedefs'][$rectype_id]){
    error_exit("Record type ".$rectype_id." not found among definitions of source database");    
}

//4. Parse record types in the list to identify any constrained record pointers or relmarkers to other record types. 
// Add these record types to the list. Call step repeatedly until no new record types are added to the list 
// Limit maximum number of repeats to 10
findDependentRecordTypes($rectype_id, 0);

//5. With list of all record types, build a list of all the base field types:------------------------
$def_rts = $defs['rectypes']['typedefs'];
$def_dts = $defs['detailTypes']['typedefs'];
    
$idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
$idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];

foreach ($imp_recordtypes as $recid){
    $fields = $def_rts[$rectype_id]['dtFields'];
    foreach ($fields as $ftId => $field){
        if(!in_array($ftId, $detailtypes)){
              array_push($imp_fieldtypes, $ftId);
              
//6. With list of all base field types: Return data for all terms used ------------------------------------------------
              if($field[$idx_type] == "enum" || $field[$idx_type] == "relationtype"){
                         //get top most vocabulary
                         getTopMostVocabulary($def_dts[$ftId][$idx_terms], $field[$idx_type]);
              }
        }
    }
}

//7. Exclude record types and field types in case the entities with the same concept code already in target DB --------
if($excludeDuplication){
    
    //$trg_rectypes
}

//=====================================================================================================================               
//8. Perform database action - add records, structure, fields and terms into our database -----------------------------
$mysqli = mysqli_connection_overwrite(DATABASE); // mysqli

// I. Add Terms (whole vocabulary)
importVocabulary(null, "enum");
importVocabulary(null, "relation");

// II. Add missed rectype groups --------------------------------------------------------------------------------------

$columnNames = array("rtg_Name","rtg_Order","rtg_Description");
$idx_rt_grp = $def_rts['commonNamesToIndex']['rty_RecTypeGroupID'];

foreach ($imp_recordtypes as $recId){
    $grp_id = $def_rts[$recId]['commonFields'][$idx_rt_grp];
    if(@$group_rt_ids[$grp_id]){ //already found
        continue;
    }
    $src_group = $defs['rectypes']['groups'][$grp_id];
    $grp_name = $src_group['name'];
    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_rectypes['groups'] as $group){
        if(trim($group['name'])== trim($grp_name)){
             $group_rt_ids[$grp_id] = $group['id'];
             $isNotFound = false;
             break;
        }
    }
    if($isNotFound){
        $res = createRectypeGroups($columnNames, array($grp_name, $src_group['order'], $src_group['description']) );
        if(is_numeric($res['result'])){
            $group_rt_ids[$grp_id] = $res['result'];
        }else{
            error_exit("Can't add record type group '".$grp_name."'. ".$res);
        }
    }
}
 
// III. Add record types ----------------------------------------------------------------------------------------------

$columnNames = $def_rts['commonFieldNames'];
$dtFieldNames = $def_rts['dtFieldNames'];

$idx_name = $def_rts['commonNamesToIndex']['rty_Name'];

foreach ($imp_recordtypes as $recId){

    $def_rectype = $def_rts[$recId]['commonFields'];

    //replace group id with local one
    $grp_id = $def_rectype[$idx_rt_grp];
    $def_rectype[$idx_rt_grp] = $group_rt_ids[$grp_id];
    
    //disambiguate name
    $def_field[$idx_name] = doDisambiguate($def_rectype[$idx_name], $idx_name, $trg_rectypes);

    
    $res = createRectypes($columnNames, array("common"=>$def_rectype), false);
    
    if(is_numeric($res)){
        
        $rtyID  = abs($res);
        $rectypes_correspondence[$recId] = $rtyID;
        
        
    }else{
        error_exit("Can not add record type for id#".$recId.". ".$res);
    }
}

// IV. Add missed field type groups -----------------------------------------------------------------------------------
$columnNames = array("dtg_Name","dtg_Order","dtg_Description");
$idx_dt_grp = $def_dts['fieldNamesToIndex']['dty_DetailTypeGroupID'];
foreach ($imp_fieldtypes as $ftId){
    $grp_id = $def_dts[$ftId]['commonFields'][$idx_dt_grp];
    if(@$group_ft_ids[$grp_id]){ //already found
        continue;
    }
    $src_group = $defs['detailTypes']['groups'][$grp_id];
    $grp_name = $src_group['name'];
    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_fieldtypes['groups'] as $group){
        if(trim($group['name'])== trim($grp_name)){
             $group_ft_ids[$grp_id] = $group['id'];
             $isNotFound = false;
             break;
        }
    }
    if($isNotFound){
        $res = createDettypeGroups($columnNames, array($grp_name, $src_group['order'], $src_group['description']) );
        if(is_numeric($res['result'])){
            $group_ft_ids[$grp_id] = $res['result'];
        }else{
            error_exit("Can't add field type group for '".$grp_name."'. ".$res);
        }
    }
}

// V. Add field types -------------------------------------------------------------------------------------------------
$columnNames = $def_dts['commonFieldNames'];
array_shift($columnNames); //remove dty_ID

$idx_type           = $def_dts['fieldNamesToIndex']['dty_Type'];
$idx_name           = $def_dts['fieldNamesToIndex']['dty_Name'];
$idx_terms_tree     = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];
$idx_terms_disabled = $def_dts['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
$idx_constraints    = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];

foreach ($imp_fieldtypes as $ftId){

    $def_field = $def_dts[$ftId]['commonFields'];
    array_shift($def_field); //remove dty_ID
    
    //replace grouop id
    $grp_id = $def_field[$idx_dt_grp];
    $def_field[$idx_dt_grp] = $group_ft_ids[$grp_id];
    
    //disambiguate name
    $def_field[$idx_name] = doDisambiguate($def_field[$idx_name], $idx_name, $trg_fieldtypes);
    
    if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype"){
            //change terms ids for enum and reltypes
            $def_field[$idx_terms_tree] = replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
            $def_field[$idx_terms_disabled] = replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
        
    }else if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
            //change record ids for pointers
            $def_field[$idx_constraints] = replaceRecIds(@$def_field[$idx_constraints]);
    }
    
    $res = createDetailTypes($commonNames, array("common"=>$def_field));
    
    if(is_numeric($res)){
        $fields_correspondence[$ftId] = abs($res);
    }else{
        error_exit("Can't add field type for id#".$ftId.". ".$res);
    }
}

// VI. Add record structures ------------------------------------------------------------------------------------------
$idx_type           = $def_rts['dtFieldNamesToIndex']['dty_Type'];
$idx_terms_tree     = $def_rts['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];  //value is the same as 
$idx_terms_disabled = $def_rts['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
$idx_constraints    = $def_rts['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];

foreach ($imp_recordtypes as $recId){
    if(@$rectypes_correspondence[$recId]){
        
        $fields = array();
        foreach ($def_rts['dtFields'] as $ftId => $def_field){
            
            if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype"){
                    //change terms ids for enum and reltypes
                    $def_field[$idx_terms_tree] = replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
                    $def_field[$idx_terms_disabled] = replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
                
            }else if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
                    //change record ids for pointers
                    $def_field[$idx_constraints] = replaceRecIds(@$def_field[$idx_constraints]);
            }
            
            $fields[ $fields_correspondence[$ftId] ] = $def_field;
        }
        updateRecStructure( $dtFieldNames , $rtyID, array("dtFields"=>$fields));
    }
}

$mysqli->close();
exit();
//-----------------------------------------
//
//
//
function error_exit($msg){
    print "ERROR: ".$msg."<br />";
        //print json_format(array("error"=>$msg));
    exit;
}


//
// find all dependend record types in constraints
//
function findDependentRecordTypes($rectype_id, $depth){
    global $imp_recordtypes, $defs;
    
    if(in_array($rectype_id, $imp_recordtypes) || $depth>9){
        //already in array
        return;
    }
    
    array_push($rectype_id, $imp_recordtypes);
    
    $def_rts = $defs['rectypes']['typedefs'];
    $def_dts = $defs['detailTypes']['typedefs'];
    
    $idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
    $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];

    $fields = $def_rts[$rectype_id]['dtFields'];
    //loop all fields and check constraint for pointers and relmarkers
    foreach ($fields as $ftId => $field){
        if($field[$idx_type] == "resource" || $field[$idx_type] == "relmarker"){
            
           $constraints = $def_dts[$ftId][$idx_constraints];
           
           $recids = explode(",", $constraints);
           foreach ($recids as $recid){
                 findDependentRecordTypes($recid, $depth+1);
           }
        }
    }
    
}

//
// replace recid in constraint string to local id
//
function replaceRecIds($constraints){ 
    global $rectypes_correspondence;           
            if($constraints){
                $recids = explode(",", $constraints);
                $recids_new = array();
                foreach($recids as $recid){
                    if(@$rectypes_correspondence[$recid])
                            array_push($recids_new, $rectypes_correspondence[$recid]);
                }
                $constraints = implode(",",$recids_new);
            }
            return $constraints; 
}
//
// replace term_ids in string to new ones
//
// function that translates all term ids in the passed string to there local/imported value
function replaceTermIds( $sterms, $domain ) {
    global $terms_correspondence;
    
    if($domain="relationtype") $domain = "relation";
    // Import terms
    foreach ($terms_correspondence as $importTermID=>$translatedTermID) {
        //replace termID in string
        $sterms = preg_replace("/\"".$importTermID."\"/","\"".$translatedTermID."\"",$sterms);
    }
    return $sterms;
}

//
// Fill global $imp_terms with the top most vocabulary
// $terms_ids - list of terms (json or csv) from field definition
//
function getTopMostVocabulary($terms_ids, $domain){
    global $imp_terms;
    
    if($domain="relationtype") $domain = "relation";
    
    //array of valid ids
    $terms_ids =  getTermsFromFormat($terms_ids, $domain);
    foreach ($terms_ids as $term_id){
          $topmost = getTopMostTermParent($term_id, $domain);
          if($topmost && !in_array($topmost, $imp_terms[$domain])){
                  array_push($imp_terms[$domain], $topmost);
          }
    }
}

//
// import entire vocabulary 
//
function importVocabulary($term_id, $domain, $children=null){
    
    global $defs, $imp_terms, $terms_correspondence, $trg_terms;

    $terms = $defs['terms'];
    
    if($term_id==null){
        foreach($imp_terms[$domain] as $term_id){
            importVocabulary($term_id, $domain, @$terms['treesByDomain'][$domain][$term_id]);
        }
    }else{
    
        $columnNames = $terms['commonFieldNames'];
        $idx_ccode = intval($terms['fieldNamesToIndex']["trm_ConceptID"]);
        $idx_parentid = intval($terms['fieldNamesToIndex']["trm_ParentTermID"]);
        $idx_inverseid = intval($terms['fieldNamesToIndex']["trm_InverseTermID"]);
        $idx_label = intval($terms['fieldNamesToIndex']["trm_Label"]);
        $idx_code  = intval($terms['fieldNamesToIndex']["trm_Code"]);
        
        $term_import = $terms['termsByDomainLookup'][$domain][$term_id];
        //find term by concept code among local terms
        $new_term_id = findTermByConceptCode($term_import[$idx_ccode], $domain);

        //if not found add new term
        if(!$new_term_id){
            //change trm_InverseTermID, trm_ParentTermID
            $term_import[$idx_parentid] = @$terms_correspondence[$term_import[$idx_parentid]];
            $term_import[$idx_inverseid] = @$terms_correspondence[$term_import[$idx_inverseid]]; //@todo - after all terms addition?
            
            //get level - all terms of the same level - to search same name and codes
            if(@$term_import[$idx_parentid]){
                $lvl_src = $trg_terms['treesByDomain'][$domain][$term_import[$idx_parentid]];    
            }else{
                $lvl_src = $trg_terms['treesByDomain'][$domain];
            }
            //verify that code and label is unique for the same level in target(local) db
            $term_import[$idx_code] = doDisambiguateTerms($term_import[$idx_code], $lvl_src, $domain, $idx_code);
            $term_import[$idx_label] = doDisambiguateTerms($term_import[$idx_label], $lvl_src, $domain, $idx_label);
            
            $new_term_id = updateTerms($columnNames, null, $term_import, null);    
            if(!is_numeric($new_term_id)){
                error_exit("Can't add term");
            }
        }
        //fill $terms_correspondence    
        $terms_correspondence[$term_id] = $new_term_id;
        
        if($children){
            foreach($children as $term_id){
                importVocabulary($term_id, $domain, $children[$term_id]);
            }
        }
    }
}


function removeLastNum($name){

        $k = strrpos($name," ");
        if( $k>0 && is_numeric($name.substr($k)) ){
          $name.substr(0,$k);  
        }
        return $name;    
}


// Disambiguate elements (including terms at the same level of a vocabulary) which have the same label but ----------
// different concept IDs, by adding 1, 2, 3 etc. to the end of the label.
//
// $lvl_src - level to search
// $idx - field index to check
//
function doDisambiguateTerms($term_import, $lvl_src, $domain, $idx){        
        global $trg_terms;
        
        $found = 0;
        $name = removeLastNum($term_import[$idx]);
        
        foreach($lvl_src as $trmId){
              $name1 = removeLastNum($trg_terms['termsByDomainLookup'][$domain][$trmId][$idx]);
              if($name == $name1){
                $found++;          
              }
        }
        if($found>0){
            return $name." ".($found+1);
        }else{
            return $term_import[$idx];
        }
}        
//
//
//
function doDisambiguate($newvalue, $idx, $entities){        

        $found = 0;
        $name = removeLastNum($newvalue);
        
        foreach($entities as $entity){
              $name1 = removeLastNum($entity['commonFields'][$idx]);
              if($name == $name1){
                $found++;          
              }
        }
        if($found>0){
            return $name." ".($found+1);
        }else{
            return $newvalue;
        }
    
}

//
//
//
function findTermByConceptCode($ccode, $domain){
    global $trg_terms;

    $terms = $trg_terms['termsByDomainLookup'][$type];
    $idx_ccode = intval($trg_terms['fieldNamesToIndex']["trm_ConceptID"]);

    foreach ($terms as $term_id => $def) {
        if(is_numeric($term_id) && $def[$term_id]==$ccode){
                return $term_id;
        }
    }
    return null;
}

/*
$idx_ccode = intval($def_rts['commonNamesToIndex']['rty_ConceptID']);
findByConceptCode($ccode, $imp_recordtypes, $idx_ccode);

$idx_ccode = intval($def_dts['commonNamesToIndex']['dty_ConceptID']);
findByConceptCode($ccode, $imp_fieldtypes , $idx_ccode);
//
//
//
function findByConceptCode($ccode, $entities, $idx_ccode){


    foreach ($entities as $term_id => $def) {
        if(is_numeric($term_id) && $def[$term_id]==$ccode){
                return $term_id;
        }
    }
    return null;
}
*/

// a couple of function from h4/db_records.php

//
// get terms from json string
//
function getTermsFromFormat($formattedStringOfTermIDs, $domain) {

        global $defs;
        
        $terms = $defs['terms'];

        $validTermIDs = array();
        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return $validTermIDs;
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
        /*****DEBUG****///error_log( "term IDS = ". print_r($termIDs,true));

        $TL = $terms['termsByDomainLookup'][$domain];

        foreach ($termIDs as $trmID) {
            // check that the term valid
            if ( $trmID && array_key_exists($trmID,$TL) && !in_array($trmID, $validTermIDs)){ // valid trm ID
                array_push($validTermIDs,$trmID);
            }
        }
        return $validTermIDs;
}

//
//
//
function getTopMostTermParent($term_id, $domain, $topmost=null)
{
        global $terms;

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $terms['treesByDomain'][$domain];
        }
        foreach($lvl as $sub_term_id=>$childs){

            if($sub_term_id == $term_id){
                return $topmost?$topmost:$term_id;                
            }else if( count($childs)>0 ) {
                
                $res = getTopMostTermParent($term_id, $childs, $topmost?$topmost:$sub_term_id );
                if($res) return $res;
            }
        }

        return null; //not found
}
?>
