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
//heur-db-pro-1.ucc.usyd.edu.au/HEURIST/h3-ao/admin/structure/import/importRectype.php?&db=artem_delete1&id=1126-12

$outputFormat = null; //@$_REQUEST["output"]; //html (default) or json
if($outputFormat=="json"){
    define('ISSERVICE',1);
}

require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../saveStructureLib.php');

// User must be system administrator or admin of the owners group for this database
if( !is_admin() ){
    error_exit("Sorry, you need to be a database owner to be able to modify the database structure");
}

$excludeDuplication = (@$_REQUEST["dup"]!="1"); //by defaul exclude

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
$reg_url = $data['rec_URL'];         //base url for source database
$reg_url = explode("?",$reg_url);

$url_params = @$reg_url[1];
$reg_url = @$reg_url[0];
if(!$url_params || !$reg_url){
    error_exit("Heurist master index returns wrong data for registered db #".$database_id);
}

$reg_url = $reg_url."common/php/reloadCommonInfo.php?".$url_params;

//print $reg_url."<br>";

$defs = loadRemoteURLContent($reg_url);                    
if (!$defs) {                            
    error_exit("Unable to contact source database, possibly due to timeout or proxy setting");    
}
//error_log(
//print $defs['rectypes'];
$defs = json_decode($defs, true);

if (!($defs['rectypes'] && $defs['detailTypes'] && $defs['terms'])) {                            
    error_exit("Defintions from source database are invalid");    
}

$sourceIconURL = $defs['icon_url'];

//DEBUG print $data;

//target(local) definitions
$trg_rectypes = getAllRectypeStructures();
$trg_fieldtypes = getAllDetailTypeStructures();
$trg_terms = getTerms();

//definitions to be imported - list of ids
$imp_recordtypes = array();
$imp_fieldtypes = array();
$imp_terms = array("enum"=>array(), "relation"=>array());

//remote database
$def_rts = $defs['rectypes']['typedefs'];
$def_dts = $defs['detailTypes']['typedefs'];

//source id => target id
$fields_correspondence = array();
$fields_correspondence_existed = array();
$rectypes_correspondence = array(); 
$terms_correspondence = array(); //"enum"=>array(), "relation"=>array());
$terms_correspondence_existed = array();
$group_ft_ids = array();
$group_rt_ids = array();


//3. Add the record type as the first element in a list.
if(!@$def_rts[$rectype_id]){
    error_exit("Record type ".$rectype_id." not found among definitions of source database");    
}

//4. Parse record types in the list to identify any constrained record pointers or relmarkers to other record types. 
// Add these record types to the list. Call step repeatedly until no new record types are added to the list 
// Limit maximum number of repeats to 10
findDependentRecordTypes($rectype_id, 0);

if(count($rectypes_correspondence)>0 && count($imp_recordtypes)==0){
    error_exit("It appears that all record types are already in this database");
}

//5. With list of all record types, build a list of all the base field types:------------------------
    
$idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
$idx_ccode = $def_dts['fieldNamesToIndex']['dty_ConceptID'];
$idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];


//DEBUG print ">>>>".print_r($trg_fieldtypes['typedefs'][1],true)."<br>";

foreach ($imp_recordtypes as $recid){
    $fields = $def_rts[$rectype_id]['dtFields'];
    foreach ($fields as $ftId => $field){
        if(!in_array($ftId, $fields_correspondence)){
            
            if(true || $excludeDuplication){
                $ccode = $def_dts[$ftId]['commonFields'][$idx_ccode];
                $local_ftId = findByConceptCode($ccode, $trg_fieldtypes['typedefs'], $idx_ccode);
                
//DEBUG print $idx_ccode."  ".$ccode." = ".$local_ftId."<br/>";                
                if($local_ftId){
                    $fields_correspondence[$ftId] = $local_ftId;
                    $fields_correspondence_existed[$ftId] = $local_ftId;
                    continue; //rectype with the same concert code is already in database
                }
            }
            
              array_push($imp_fieldtypes, $ftId);
              
//6. With list of all base field types: Return data for all terms used ------------------------------------------------
//DEBUG print $field[0]."  ".$field[$idx_type]."<br>";
              if($field[$idx_type] == "enum" || $field[$idx_type] == "relationtype"){
                         //get top most vocabulary
//DEBUG print "val>>>  ".$def_dts[$ftId]['commonFields'][$idx_terms]."<br>";                         
                         getTopMostVocabulary($def_dts[$ftId]['commonFields'][$idx_terms], $field[$idx_type]);
              }
        }
    }
}

//=====================================================================================================================               
//7. Perform database action - add records, structure, fields and terms into our database -----------------------------
$mysqli = mysqli_connection_overwrite(DATABASE); // mysqli
$mysqli->autocommit(FALSE);
//$mysqli->begin_transaction();

// I. Add Terms (whole vocabulary)
importVocabulary(null, "enum");
importVocabulary(null, "relation");

// II. Add missed rectype groups --------------------------------------------------------------------------------------

$columnNames = array("rtg_Name","rtg_Order","rtg_Description");
$idx_rt_grp = $def_rts['commonNamesToIndex']['rty_RecTypeGroupID'];

foreach ($imp_recordtypes as $recId){

//DEBUG print "<div>".$recId."  ".print_r($imp_recordtypes,true)."</div>";

    $grp_id = $def_rts[$recId]['commonFields'][$idx_rt_grp];
    if(@$group_rt_ids[$grp_id]){ //already found
        continue;
    }
    foreach ($defs['rectypes']['groups'] as $idx=>$group){
       if(is_numeric($idx) && $group['id']==$grp_id){ 
           $src_group = $group;
           $grp_name = $src_group['name'];       
           break;
       }
    }
    
    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_rectypes['groups'] as $idx=>$group){
        //if(is_numeric($idx))
        //print "<div>".$group['name']." = ".$grp_name."</div>";
        if(is_numeric($idx) && trim($group['name'])== trim($grp_name)){

             $group_rt_ids[$grp_id] = $group['id'];
             $isNotFound = false;
             break;
        }
    }
    if($isNotFound){

        $res = createRectypeGroups($columnNames,
            array(array("values" =>
             array($grp_name, $src_group['order'], $src_group['description']))) );
             
        if( is_numeric(@$res['result']) ){
            $group_rt_ids[$grp_id] = $res['result'];
        }else{
            error_exit("Can't add record type group '".$grp_name."'. ".@$res['error']);
        }
    }
}
 
// III. Add record types ----------------------------------------------------------------------------------------------

$columnNames = $def_rts['commonFieldNames'];
$dtFieldNames = $def_rts['dtFieldNames'];

$idx_name        = $def_rts['commonNamesToIndex']['rty_Name'];
$idx_origin_dbid = $def_rts['commonNamesToIndex']['rty_OriginatingDBID'];
$idx_origin_id   = $def_rts['commonNamesToIndex']['rty_IDInOriginatingDB'];
$idx_ccode       = $def_rts['commonNamesToIndex']['rty_ConceptID'];
$idx_titlemask   = $def_rts['commonNamesToIndex']['rty_TitleMask'];
$idx_titlemask_canonical = $def_rts['commonNamesToIndex']['rty_CanonicalTitleMask'];

foreach ($imp_recordtypes as $rtyID){
    
    $def_rectype = $def_rts[$rtyID]['commonFields'];

    //replace group id with local one
    $grp_id = $def_rectype[$idx_rt_grp];
    $def_rectype[$idx_rt_grp] = $group_rt_ids[$grp_id];
    
    //disambiguate name
    $def_rectype[$idx_name] = doDisambiguate($def_rectype[$idx_name], $trg_rectypes['names']);
    
    //assign canonical to title mask (since in DB we store only rty_TitleMask)
    $def_rectype[$idx_titlemask] = $def_rectype[$idx_titlemask_canonical];

//DEBUG print "canonical:$def_rectype[$idx_titlemask_canonical]<br>";    
    
    //fill original ids if missed
    if($def_rectype[$idx_ccode] && (!$def_rectype[$idx_origin_dbid] || !$def_rectype[$idx_origin_id])){
        $codes = explode("-",$def_rectype[$idx_ccode]);
        if($codes && count($codes)==2){
            $def_rectype[$idx_origin_dbid] = $codes[0];
            $def_rectype[$idx_origin_id] = $codes[1];
        }
    }

    
    $res = createRectypes($columnNames, array("0"=>array("common"=>$def_rectype)), false, false);
    
    if(is_numeric($res)){
        
        $new_rtyID  = abs($res);
        $rectypes_correspondence[$rtyID] = $new_rtyID;
        
        copyRectypeIcon($rtyID, $new_rtyID);
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
    
    foreach ($defs['detailTypes']['groups'] as $idx=>$group){
       if(is_numeric($idx) && $group['id']==$grp_id){ 
           $src_group = $group;
           $grp_name = $src_group['name'];       
           break;
       }
    }

    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_fieldtypes['groups'] as $idx=>$group){
        if(is_numeric($idx) && trim($group['name'])== trim($grp_name)){
             $group_ft_ids[$grp_id] = $group['id'];
             $isNotFound = false;
             break;
        }
    }
    if($isNotFound){
        
        $res = createDettypeGroups($columnNames,
            array(array("values" =>
             array($grp_name, $src_group['order'], $src_group['description']))) );
        
        if(is_numeric(@$res['result'])){
            $group_ft_ids[$grp_id] = $res['result'];
        }else{
            error_exit("Can't add field type group for '".$grp_name."'. ".$res['error']);
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
$idx_origin_dbid = $def_dts['fieldNamesToIndex']['dty_OriginatingDBID'];
$idx_origin_id   = $def_dts['fieldNamesToIndex']['dty_IDInOriginatingDB'];
$idx_ccode       = $def_dts['fieldNamesToIndex']['dty_ConceptID'];

foreach ($imp_fieldtypes as $ftId){

    $def_field = $def_dts[$ftId]['commonFields'];
    
    //replace grouop id
    $grp_id = $def_field[$idx_dt_grp];
    $def_field[$idx_dt_grp] = $group_ft_ids[$grp_id];
   
    //disambiguate name
    $def_field[$idx_name] = doDisambiguate($def_field[$idx_name], $trg_fieldtypes['names']);
    
    if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype"){
            //change terms ids for enum and reltypes
            $def_field[$idx_terms_tree] = replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
            $def_field[$idx_terms_disabled] = replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
        
    }else if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
            //change record ids for pointers
            $def_field[$idx_constraints] = replaceRecIds(@$def_field[$idx_constraints]);
    }
    
    //fill original ids if missed
    if($def_field[$idx_ccode] && (!$def_field[$idx_origin_dbid] || !$def_field[$idx_origin_id])){
        $codes = explode("-",$def_field[$idx_ccode]);
        if($codes && count($codes)==2){
            $def_field[$idx_origin_dbid] = $codes[0];
            $def_field[$idx_origin_id] = $codes[1];
        }
    }
    
//DEBUG print print_r($def_field,true);
    
    array_shift($def_field); //remove dty_ID
    $res = createDetailTypes($columnNames, array("common"=>$def_field));
    
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

$dtFieldNames = $def_rts['dtFieldNames'];

foreach ($imp_recordtypes as $rtyID){
    if(@$rectypes_correspondence[$rtyID]){
        
        $fields = array();
        foreach ($def_rts[$rtyID]['dtFields'] as $ftId => $def_field){
            
            if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype"){
                    //change terms ids for enum and reltypes
                    $def_field[$idx_terms_tree] = ""; //replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
                    $def_field[$idx_terms_disabled] = ""; //replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
                
            }else if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
                    //change record ids for pointers
                    $def_field[$idx_constraints] = replaceRecIds(@$def_field[$idx_constraints]);
            }
            
            $fields[ $fields_correspondence[$ftId] ] = $def_field;
        }

//DEBUG print print_r($fields, true);
        
        $ret = updateRecStructure( $dtFieldNames , $rectypes_correspondence[$rtyID], array("dtFields"=>$fields));
        if(is_array($ret)){
        foreach($ret as $id=>$res2){
            foreach($res2 as $dtid=>$res){
                if(!is_numeric($res)){
                    error_exit("Can't update record type structure rectype#".$id.". ".$res);
                }
            }
        }
        }else{
            error_exit("Can't update record type structure rectype#".$rectypes_correspondence[$rtyID].". ".$ret);   
        }
    }
}

// VII. Update titlenasks with new ids --------------------------------------------------------------------------------
$mysqli->commit();

foreach ($imp_recordtypes as $rtyID){
    if(@$rectypes_correspondence[$rtyID]){
        
            $mask = $def_rts[$rtyID]['commonFields'][$idx_titlemask_canonical];

///DEBUG print "<br>mask before: ".$mask."  rty=".$rectypes_correspondence[$rtyID];
        
            $res = updateTitleMask( $rectypes_correspondence[$rtyID], $mask);
            if(!is_numeric($res)){
                error_exit($res);   
            }
    }
}

$mysqli->commit();
$mysqli->close();

//output 
if($outputFormat=="json"){
    header("Content-type: text/javascript");
    
}else{
$trg_rectypes = getAllRectypeStructures();
$trg_fieldtypes = getAllDetailTypeStructures();
$trg_terms = getTerms();
?>    
<html>
<body>
<h4>IMPORT COMPLETED</h4>
<hr />
<b>Record types</b>
<table border="1">
<tr><th colspan="2">Source</th><th>&nbsp;</th><th colspan="3">Target</th></tr>
<tr><th>ID</th><th>Name</th><th>Concept Code</th><th>ID</th><th>Name</th><th></th></tr>
<?php
    $idx_name  = $def_rts['commonNamesToIndex']['rty_Name'];
    $idx_ccode = $def_rts['commonNamesToIndex']["rty_ConceptID"];

    foreach ($rectypes_correspondence as $imp_id=>$trg_id){
        print "<tr><td>$imp_id</td><td>".$def_rts[$imp_id]['commonFields'][$idx_name]
            ."</td><td>"
            .$def_rts[$imp_id]['commonFields'][$idx_ccode]
            ."</td><td>$trg_id</td><td>"
            .$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_name]
            ."</td><td>".$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_titlemask_canonical]
            ."</td></tr>";
    }
?>
</table>
<br/><br/>
<b>Field types</b>
<table border="1">
<tr><th colspan="2">Source</th><th>&nbsp;</th><th colspan="3">Target</th></tr>
<tr><th>ID</th><th>Name</th><th>Concept Code</th><th>ID</th><th>Name</th></tr>
<?php
    $idx_name  = $def_dts['fieldNamesToIndex']['dty_Name'];
    $idx_ccode = $def_dts['fieldNamesToIndex']["dty_ConceptID"];

    foreach ($fields_correspondence as $imp_id=>$trg_id){
        if($fields_correspondence_existed[$imp_id]) continue;
        
        print "<tr><td>$imp_id</td><td>".$def_dts[$imp_id]['commonFields'][$idx_name]
            ."</td><td>"
            .$def_dts[$imp_id]['commonFields'][$idx_ccode]
            ."</td><td>$trg_id</td><td>"
            .$trg_fieldtypes['typedefs'][$trg_id]['commonFields'][$idx_name]."</td></tr>";
    }
    
?>
</table>
<br/><br/>
<b>Terms</b>
<table border="1">
<tr><th colspan="2">Source</th><th>&nbsp;</th><th colspan="3">Target</th></tr>
<tr><th>ID</th><th>Name</th><th>Concept Code</th><th>ID</th><th>Name</th></tr>
<?php
    $idx_name  = $defs['terms']['fieldNamesToIndex']['trm_Label'];
    $idx_ccode = $defs['terms']['fieldNamesToIndex']["trm_ConceptID"];

    foreach ($terms_correspondence as $imp_id=>$trg_id){
        if($terms_correspondence_existed[$imp_id]) continue;
        
        if(@$defs['terms']['termsByDomainLookup']['enum'][$imp_id]){
            $domain = 'enum';
        }else{
            $domain = 'relation';
        }
        
        print "<tr><td>$imp_id</td><td>".$defs['terms']['termsByDomainLookup'][$domain][$imp_id][$idx_name]
            ."</td><td>"
            .$defs['terms']['termsByDomainLookup'][$domain][$imp_id][$idx_ccode]
            ."</td><td>$trg_id</td><td>"
            .$trg_terms['termsByDomainLookup'][$domain][$trg_id][$idx_name]."</td></tr>";
    }
?>
</table>
</body>    
</html>
<?php    
}

exit();
//-----------------------------------------
//
//
//
function error_exit($msg){
    global $outputFormat, $mysqli;
    
    if($outputFormat=="json"){
        header("Content-type: text/javascript");
        print json_format(array("error"=>$msg));
    }else{
        print "ERROR: ".$msg."<br />";
    }
    
    if(isset($mysqli)){
        if($outputFormat!="json") print "ROLLBACK";
        $mysqli->rollback();
        $mysqli->close();
    }
    
    exit;
}


//
// find all dependend record types in constraints
//
function findDependentRecordTypes($rectype_id, $depth){
    global $trg_rectypes, $imp_recordtypes, $defs, $excludeDuplication, $rectypes_correspondence;
    
    if(!$rectype_id || in_array($rectype_id, $imp_recordtypes) || $depth>9){
        //already in array
        return;
    }

    $def_rts = $defs['rectypes']['typedefs'];
    $def_dts = $defs['detailTypes']['typedefs'];
    $idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
    $idx_ccode = intval($def_rts['commonNamesToIndex']["rty_ConceptID"]);
    $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
        
    if($excludeDuplication){
        $ccode = $def_rts[$rectype_id]['commonFields'][$idx_ccode];
        $local_recid = findByConceptCode($ccode, $trg_rectypes['typedefs'], $idx_ccode);
        if($local_recid){
            $rectypes_correspondence[$rectype_id] = $local_recid;
            return; //rectype with the same concert code is already in database
        }
    }

    array_push($imp_recordtypes, $rectype_id);

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
    
    if($sterms==null || $sterms=="") return $sterms;
    
    if($domain=="relationtype") $domain = "relation";
    // Import terms

//DEBUG print "<div> bef ".$sterms."<div>";
    
    if (strpos($sterms,"{")!== false) {
        foreach ($terms_correspondence as $imp_id=>$trg_id) {
            //replace termID in string
            $sterms = preg_replace("/\"".$imp_id."\"/","\"".$trg_id."\"",$sterms);
        }
    }else{
        $aterms = explode(",",$sterms);
        $aterms_new = array();
        foreach ($aterms as $imp_id) {
           if(@$terms_correspondence[$imp_id]){
               array_push($aterms_new, $terms_correspondence[$imp_id]);
           }
        }
        $sterms = implode(",", $aterms_new);
    }
//DEBUG print "<div> afer ".$sterms."<div>";    
    return $sterms;
}

//
// Fill global $imp_terms with the top most vocabulary
// $terms_ids - list of terms (json or csv) from field definition
//
function getTopMostVocabulary($terms_ids, $domain){
    global $imp_terms;
    
    if($domain=="relationtype") $domain = "relation";
    
    //array of valid ids
    $terms_ids =  getTermsFromFormat($terms_ids, $domain);
//DEBUG print "terms_ids= ".print_r($terms_ids, true)."<br>";    
    foreach ($terms_ids as $term_id){
          $topmost = getTopMostTermParent($term_id, $domain);
//DEBUG print "topmost= ".$topmost."<br>";    
          if($topmost && !in_array($topmost, $imp_terms[$domain])){
                  array_push($imp_terms[$domain], $topmost);
          }
    }
    
}

//
// import entire vocabulary 
//
function importVocabulary($term_id, $domain, $children=null){
    
    global $defs, $imp_terms, $terms_correspondence, $terms_correspondence_existed, $trg_terms, $excludeDuplication;

    $terms = $defs['terms'];
    
    if($term_id==null){
        
//DEBUG print $domain." >>>  ".print_r(@$imp_terms[$domain], true);

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
        $idx_origin_dbid  = intval($terms['fieldNamesToIndex']["trm_OriginatingDBID"]);
        $idx_origin_id  = intval($terms['fieldNamesToIndex']["trm_IDInOriginatingDB"]);
        
        $term_import = $terms['termsByDomainLookup'][$domain][$term_id];
        //find term by concept code among local terms
        if(true || $excludeDuplication){
            $new_term_id = findTermByConceptCode($term_import[$idx_ccode], $domain);
        }else{
            $new_term_id = null;
        }
        
        if($new_term_id){
             $terms_correspondence_existed[$term_id] = $new_term_id;
        }else{
            //if not found add new term
            
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
            
            //fill original ids if missed
            if($term_import[$idx_ccode] && (!$term_import[$idx_origin_dbid] || !$term_import[$idx_origin_id])){
                $codes = explode("-",$term_import[$idx_ccode]);
                if($codes && count($codes)==2){
                    $term_import[$idx_origin_dbid] = $codes[0];
                    $term_import[$idx_origin_id] = $codes[1];
                }
            }

//DEBUG print "<br>ADD ".print_r($term_import, true);
            
            $res = updateTerms($columnNames, null, $term_import, null);    
            if(is_numeric($res)){
                $new_term_id = $res;
            }else{
                error_exit("Can't add term ".print_r($term_import, true)."  ".$res);
            }
        }
        //fill $terms_correspondence    
        $terms_correspondence[$term_id] = $new_term_id;
        
        if($children){
            
 //DEBUG print "<br>CHILDREN:".print_r($children, true);
            foreach($children as $id=>$children2){
                importVocabulary($id, $domain, $children2);
            }
        }
    }
}


function removeLastNum($name){

        $k = strrpos($name," ");

        if( $k>0 && is_numeric(substr($name, $k+1)) ){
           $name = substr($name,0,$k);  
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
        
        if(!$term_import || $term_import=="") return $term_import;

        $found = 0;
        $name = removeLastNum($term_import);
        
        foreach($lvl_src as $trmId=>$childs){
              $name1 = removeLastNum($trg_terms['termsByDomainLookup'][$domain][$trmId][$idx]);
              if($name == $name1){
                $found++;          
              }
        }
        if($found>0){
            $term_import = $name." ".($found+1);
        }
//DEBUG print "<br>nam aft ".$term_import;
            
        return $term_import;
}        
//
//
//
function doDisambiguate($newvalue, $entities){        

        if(!$newvalue || $newvalue=="") return $newvalue;
    
        $found = 0;
        $name = removeLastNum($newvalue);
        
        foreach($entities as $id=>$name1){
              $name1 = removeLastNum($name1);
              if($name == $name1){
                    $found++;          
              }
        }
        if($found>0){
            $newvalue = $name." ".($found+1);
        }
    
       return $newvalue;
}

//
//
//
function findTermByConceptCode($ccode, $domain){
    global $trg_terms;

    $terms = $trg_terms['termsByDomainLookup'][$domain];
    $idx_ccode = intval($trg_terms['fieldNamesToIndex']["trm_ConceptID"]);

    foreach ($terms as $term_id => $def) {
        if(is_numeric($term_id) && $def[$idx_ccode]==$ccode){
                return $term_id;
        }
    }
    return null;
}
                                                            
//
//  find by concept code in local definitions
//
function findByConceptCode($ccode, $entities, $idx_ccode){

    foreach ($entities as $id => $def) {
        if(is_numeric($id) && $def['commonFields'][$idx_ccode]==$ccode){
                return $id;
        }
    }
    return null;
}

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
            /*****DEBUG****///echo ( "term array string = ". $formattedStringOfTermIDs);
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }
        // Validate termIDs
        $TL = $terms['termsByDomainLookup'][$domain];

        /*****DEBUG****///print ( $domain." term IDS = ". print_r($termIDs,true))."<br>".print_r($TL, true);
        
        foreach ($termIDs as $trmID) {
            // check that the term valid
            if ( $trmID && array_key_exists($trmID,$TL) && !in_array($trmID, $validTermIDs)){ // valid trm ID
                array_push($validTermIDs,$trmID);
            }
        }
        return $validTermIDs;
}

//
//  Find vocabulary ID
//
function getTopMostTermParent($term_id, $domain, $topmost=null)
{
        global $defs;
        
        $terms = $defs['terms'];

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

//
// Copy record type icon and thumbnail from source to destination database
//
function copyRectypeIcon($source_RtyID, $target_RtyID, $thumb=""){
    global $sourceIconURL;
    
    $sourceURL = $sourceIconURL.$thumb.$source_RtyID.".png";
    $targetPath = HEURIST_ICON_DIR.$thumb.$target_RtyID.".png";
    
//print "<br>sourcce=".$sourceURL;    
//print "<br>path=".$targetPath;
    
    saveURLasFile($sourceURL, $targetPath);
    
    if($thumb==""){
        copyRectypeIcon($source_RtyID, $target_RtyID, "thumb/th_");
    }
}
?>
