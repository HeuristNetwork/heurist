<?php

/**
* importRectype.php : import a record type and all dependent record types, fields and terms from another Heurist database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
$outputFormat = @$_REQUEST["output"]; //html (default) or json
if($outputFormat=="json"){
    define('ISSERVICE',1);
}

require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../../../common/php/utilsMail.php');
require_once(dirname(__FILE__).'/../saveStructureLib.php');

// User must be system administrator or admin of the owners group for this database
if( !is_admin() ){
    error_exit("Sorry, you need to be a database owner to be able to modify the database structure");
}

$excludeDuplication = (@$_REQUEST["dup"]!="1"); //by default exclude duplication of existing record types (identified by concept IDs)

//combination of db and record type id eg. 1126-13
// TODO: Artem, is this meant to be the concept ID for the record type? or si the record type referenced by local ID?
$code = @$_REQUEST['code'];
$req_import_types  = @$_REQUEST["import"];
$req_correspondence = @$_REQUEST["correspondence"];

if(!$code){
    
//print print_r($_REQUEST, true);    
    $code = @$_REQUEST["checkid"];
    $is_checkonly = true;
}else{
    $is_checkonly = false;
}

global $database_id;

if(!$code){
    error_exit("Record type code not defined in call to importRectype.php - should be two numbers separated by a dash(program glitch)");
}

$req_rectype_code = $code;

$code = explode("-",$code);
$database_id = @$code[0];
$rectype_id = @$code[1];

if(!(is_numeric($database_id) && is_numeric($rectype_id))){
    error_exit("Record type code passed to importRectype.php ( $code ) has wrong format - should be two numbers separated by dash (program glitch)");
}


// STEPS IN THE PROCEDURE

// 1. get database url
// 2. get definitions from remote database
// 3. Add the record type as the first element in a list.
// 4. Parse record types in the list to identify any constrained record pointers or relmarkers to other record types.
//    Add these record types to the list. Call step repeatedly until no new record types are added to the list
//    Limit maximum number of repeats to 10
// 5. With list of all record types, build a list of all the base field types:
// 6. With list of all base field types: Return data for all terms used
// 7. Perform database action - add reccords, structure, fields and terms into our database


// ------------------------------------------------------------------------------------------------

// 1. get database url by looking it up from the Heusit Master index using database registered ID
   
    $to_include = dirname(__FILE__).'/../../setup/dbproperties/getDatabaseURL.php';
    if (is_file($to_include)) {
        include $to_include;
    }
    
    if(isset($error_msg)){
        error_exit($error_msg);
        return;
    }
    $reg_url = $database_url;

/* OLD WAY   
$reg_url =   HEURIST_INDEX_BASE_URL  . "admin/setup/dbproperties/getDatabaseURL.php" . "?db=Heurist_Master_Index&id=".$database_id;

$data = loadRemoteURLContentSpecial($reg_url);

if (!$data) {
    error_exit("Unable to contact Heurist Master Index, possibly due to timeout or proxy setting<br /><br />".
        "URL requested: ".$reg_url);
}

$data = json_decode($data, true);

// Artem TODO: What circumstance would give rise to this? Explain how the data is 'wrong'/'incorrect'
if(!@$data['rec_URL']){
    error_exit("Heurist Master Index returns incorrect data for registered database # ".$database_id.
        " The page may contain an invalid database reference (0 indicates no reference has been set)");
}

$reg_url = $data['rec_URL'];         //base url for source database
*/

// ------------------------------------------------------------------------------------------------

// 2. get definitions from remote database

$reg_url = explode("?",$reg_url);

$remote_url_params = @$reg_url[1];
$remote_url = @$reg_url[0];
if(!$remote_url_params || !$remote_url){
    error_exit("Heurist Master Index returns incorrect data for registered database # ".$database_id.
        " The page may contain an invalid database reference (0 indicates no reference has been set)");
}

$reg_url = $remote_url."common/php/reloadCommonInfo.php?".$remote_url_params;

//$defs = loadRemoteURLContent($reg_url); it does not because of proxy settings on usyd server

$defs = loadRemoteURLContentSpecial($reg_url);

if (!$defs) {
    error_exit("Unable to contact the selected source database, possibly due to a timeout or proxy setting");
}

$defs = json_decode($defs, true);

if (!($defs['rectypes'] && $defs['detailTypes'] && $defs['terms'])) {
    error_exit("Structure definitions read from source database # $database_id are invalid. Please advise Heurist development team");
}

//remote database
$def_rts = $defs['rectypes']['typedefs'];
$def_dts = $defs['detailTypes']['typedefs'];


// ------------------------------------------------------------------------------------------------

// 3. Add the record type as the first element in a list

if(!@$def_rts[$rectype_id]){
    error_exit("Sorry, record type $rectype_id was not found in source database # $database_id. Please advise Heurist development team");
}

if($is_checkonly){
    header("Content-type: text/javascript");
    print json_format(array("ok"=>"Record type ".$rectype_id." is found"));
    exit();
}


$sourceIconURL = $defs['icon_url'];

//target(local) definitions
$trg_rectypes = getAllRectypeStructures();

//definitions to be imported - list of ids
$imp_recordtypes = array();
$imp_fieldtypes = array();
$imp_terms = array("enum"=>array(), "relation"=>array());

//source id => target id
$fields_correspondence = array();  //import field id -> target id - IMPORTANT for proper titlemask conversion
$fields_correspondence_existed = array();
$rectypes_correspondence = array(); //source rectypeID => new (target) rectype ID
$terms_correspondence = array(); //"enum"=>array(), "relation"=>array());
$terms_correspondence_existed = array();
$group_ft_ids = array();
$group_rt_ids = array();

if(is_array($req_import_types) && count($req_import_types)>0){

    //rectypes that will be imported - list of source rectype ids
    $imp_recordtypes = $req_import_types;

    //rectypes that will be ommited out of export
    $data = $req_correspondence;

    if($data){ //
        $rectypes_correspondence = json_decode($data, true);

        foreach($imp_recordtypes as $rty_id){
            if(@$rectypes_correspondence[$rty_id]){
                unset($rectypes_correspondence[$rty_id]);
            }
        }
    }

}else{

    // ------------------------------------------------------------------------------------------------

    // 4. Parse record types in the list to identify any constrained record pointers or relmarkers to other record types

    // Add these record types to the list. Call step repeatedly until no new record types are added to the list
    // Limit maximum number of repeats to 10
    $imp_recordtypes_tree = array();
    $imp_recordtypes_tree[$rectype_id] = findDependentRecordTypes($rectype_id, 0);
    if(count($imp_recordtypes)>1 || count($rectypes_correspondence)>0){
        renderPreviewForm();
        exit();
    }

}

//other target definitions
$trg_fieldtypes = getAllDetailTypeStructures();
$trg_terms = getTerms();


// ------------------------------------------------------------------------------------------------

// 5. With list of all record types, build a list of all the base field types

$idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
$idx_ccode = $def_dts['fieldNamesToIndex']['dty_ConceptID'];
$idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];

foreach ($imp_recordtypes as $rty_id){
    $fields = $def_rts[$rty_id]['dtFields'];
    foreach ($fields as $ftId => $field){

        if(!(@$fields_correspondence[$ftId] || in_array($ftId, $imp_fieldtypes) )){

            if(true || $excludeDuplication){
                $ccode = $def_dts[$ftId]['commonFields'][$idx_ccode];
                $local_ftId = findByConceptCode($ccode, $trg_fieldtypes['typedefs'], $idx_ccode);

                if($local_ftId){
                    $fields_correspondence[$ftId] = $local_ftId;
                    $fields_correspondence_existed[$ftId] = $local_ftId;
                    continue; //rectype with the same concert code is already in database
                }
            }

            array_push($imp_fieldtypes, $ftId);

            // ------------------------------------------------------------------------------------------------

            // 6. With list of all base field types: Return data for all terms used

            if($field[$idx_type] == "enum" || $field[$idx_type] == "relationtype"){
                //get topmost vocabulary
                getTopMostVocabulary($def_dts[$ftId]['commonFields'][$idx_terms], $field[$idx_type]);
            }
        }
    }
}


// ---------------------------------------------------------------------------------------------------------------------

// 7. Perform database action - add records, structure, fields and terms into our target database

$mysqli = mysqli_connection_overwrite(DATABASE); // mysqli
$mysqli->autocommit(FALSE);

// I. Add Terms (whole vocabulary)
importVocabulary(null, "enum");
importVocabulary(null, "relation");

// II. Add new record type groups --------------------------------------------------------------------------------------

$columnNames = array("rtg_Name","rtg_Order","rtg_Description");
$idx_rt_grp = $def_rts['commonNamesToIndex']['rty_RecTypeGroupID'];

foreach ($imp_recordtypes as $recId){

    $rt_name = @$defs['rectypes']['names'][$recId];
    if(!@$def_rts[$recId]){
        if(!$rt_name){
            error_exit("Can't find record type #'".$recId."'. in source database");
        }else{
            error_exit("Can't find definitions for record type #'".$recId."'. \"$rt_name\" in source database");
        }
    }

    $grp_id = @$def_rts[$recId]['commonFields'][$idx_rt_grp];

    if(!$grp_id){
        error_exit("Group ID is not defined for record type #'".$recId."'. \"$rt_name\" in source database");
    }

    $src_group=null;

    if(@$group_rt_ids[$grp_id]){ //already found
        continue;
    }

    //find group in source by ID
    foreach ($defs['rectypes']['groups'] as $idx=>$group){
        if(is_numeric($idx) && $group['id']==$grp_id){
            $src_group = $group;
            $grp_name = @$src_group['name'];
            break;
        }
    }

    if($src_group==null){
        error_exit("Can't find group #".$grp_id." for record type #'".$recId."'. \"$rt_name\" in source database");
    }
    if(!$grp_name){
        error_exit("Name of group is empty. Can't add group #".$grp_id." for record type #'".$recId."'. \"$rt_name\" in source database");
    }

    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_rectypes['groups'] as $idx=>$group){
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

        $new_grp_id = @$res['result'];

        if( is_numeric($new_grp_id) ){
            $group_rt_ids[$grp_id] = $new_grp_id;
            $trg_rectypes['groups'][$new_grp_id] = array('name'=>$grp_name);
        }else{
            error_exit("Can't add record type group '".$grp_name."'. ".@$res['error']);
        }
    }
}


// ------------------------------------------------------------------------------------------------

// III. Add record types

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

    //fill original ids if missed
    if($def_rectype[$idx_ccode] && (!$def_rectype[$idx_origin_dbid] || !$def_rectype[$idx_origin_id])){
        $codes = explode("-",$def_rectype[$idx_ccode]);
        if($codes && count($codes)==2){
            $def_rectype[$idx_origin_dbid] = $codes[0];
            $def_rectype[$idx_origin_id] = $codes[1];
        }
    }

    $res = createRectypes($columnNames, array("0"=>array("common"=>$def_rectype)), false, false, null);

    if(is_numeric($res)){

        $new_rtyID  = abs($res);
        $rectypes_correspondence[$rtyID] = $new_rtyID;
        $trg_rectypes['names'][$new_rtyID] = $def_rectype[$idx_name];
        copyRectypeIcon($rtyID, $new_rtyID);

    }else{

        error_exit("Cannot add record type for id#".$recId.". ".$res);

    }
}


// ------------------------------------------------------------------------------------------------

// IV. Add new field type groups

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

        $new_grp_id = @$res['result'];

        if(is_numeric($new_grp_id)){
            $group_ft_ids[$grp_id] = $new_grp_id;
            $trg_fieldtypes['groups'][$new_grp_id] = array('name'=>$grp_name);

        }else{

            error_exit("Can't add field type group for '".$grp_name."'. ".$res['error']);

        }
    }
}


// ------------------------------------------------------------------------------------------------

// V. Add field types

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

    //replace group id
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

    array_shift($def_field); //remove dty_ID
    $res = createDetailTypes($columnNames, array("common"=>$def_field));

    if(is_numeric($res)){
        $fields_correspondence[$ftId] = abs($res);
        $trg_fieldtypes['names'][abs($res)] = $def_field[$idx_name-1]; //new name
    }else{
        error_exit("Can't add field type for id#".$ftId.". ".$res);
    }
}


// ------------------------------------------------------------------------------------------------

// VI. Add record structures

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


// ------------------------------------------------------------------------------------------------

// VII. Update titlemasks with new ids

$mysqli->commit();

foreach ($imp_recordtypes as $rtyID){
    if(@$rectypes_correspondence[$rtyID]){

        $mask = $def_rts[$rtyID]['commonFields'][$idx_titlemask_canonical];

        // note we use special global array $fields_correspondence - for proper conversion of remote id to concept code
        $res = updateTitleMask( $rectypes_correspondence[$rtyID], $mask);
        if(!is_numeric($res)){
            error_exit($res);
        }
    }
}

$mysqli->commit();
$mysqli->close();


// ------------------------------------------------------------------------------------------------

// Confirmation of import results

if($outputFormat=="json"){
    header("Content-type: text/javascript");

}else{
    $trg_rectypes = getAllRectypeStructures();
    $trg_fieldtypes = getAllDetailTypeStructures();
    $trg_terms = getTerms();
    ?>

    <html>

        <head>
            <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
            <meta http-equiv="content-type" content="text/html; charset=utf-8">
        </head>

        <body class="popup" title="Annotated template import">

            <h4>Record type and associated structures imported</h4>

            <hr />

            <h3>Record types</h3>
            <table style="padding-left: 10px; padding-right: 10px; margin-left: 40px; border: 1; border-style: dotted; color: lightgray;">
                <tr><th colspan="2">Source</th><th>Concept ID</th><th colspan="3">Target</th></tr>
                <tr><th>ID</th><th>Name</th><th>&nbsp;</th><th>ID</th><th>Name</th><th></th></tr>
                <?php
                $idx_name  = $def_rts['commonNamesToIndex']['rty_Name'];
                $idx_ccode = $def_rts['commonNamesToIndex']["rty_ConceptID"];

                foreach ($imp_recordtypes as $imp_id){
                    if(@$rectypes_correspondence[$imp_id]){
                        $trg_id = $rectypes_correspondence[$imp_id];

                        print "<tr><td>$imp_id</td><td>".$def_rts[$imp_id]['commonFields'][$idx_name]
                        ."</td><td>"
                        .$def_rts[$imp_id]['commonFields'][$idx_ccode]
                        ."</td><td>$trg_id</td><td>"
                        .$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_name]
                        ."</td><td>".$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_titlemask_canonical]
                        ."</td></tr>";
                    }
                }
                ?>
            </table>

            <br/>
            <br/>

            <h3>Field types</h3>
            <table style="padding-left: 10px; padding-right: 10px; margin-left: 40px; border: 1; border-style: dotted; color: lightgray;">
                <tr><th colspan="2">Source</th><th>Concept ID</th><th colspan="3">Target</th></tr>
                <tr><th>ID</th><th>Name</th><th>&nbsp;</th><th>ID</th><th>Name</th><th></th></tr>
                <?php
                $idx_name  = $def_dts['fieldNamesToIndex']['dty_Name'];
                $idx_ccode = $def_dts['fieldNamesToIndex']["dty_ConceptID"];

                foreach ($fields_correspondence as $imp_id=>$trg_id){

                    if(@$fields_correspondence_existed[$imp_id]) continue;

                    print "<tr><td>$imp_id</td><td>".$def_dts[$imp_id]['commonFields'][$idx_name]
                    ."</td><td>"
                    .$def_dts[$imp_id]['commonFields'][$idx_ccode]
                    ."</td><td>$trg_id</td><td>"
                    .$trg_fieldtypes['typedefs'][$trg_id]['commonFields'][$idx_name]."</td></tr>";
                }
                ?>
            </table>

            <br/>
            <br/>

            <h3>Terms</h3>
            <table style="padding-left: 10px; padding-right: 10px; margin-left: 40px; border: 1; border-style: dotted; color: lightgray;">
                <tr><th colspan="2">Source</th><th>Concept ID</th><th colspan="3">Target</th></tr>
                <tr><th>ID</th><th>Name</th><th>&nbsp;</th><th>ID</th><th>Name</th><th></th></tr>
                <?php
                $idx_name  = $defs['terms']['fieldNamesToIndex']['trm_Label'];
                $idx_ccode = $defs['terms']['fieldNamesToIndex']["trm_ConceptID"];

                foreach ($terms_correspondence as $imp_id=>$trg_id){
                    if(@$terms_correspondence_existed[$imp_id]) continue;

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


// --------------------------------------------------------------------------------------------------------------------
//            FUNCTIONS
// --------------------------------------------------------------------------------------------------------------------


//
// Report an error on exit
//
function error_exit($msg){
    global $outputFormat, $mysqli, $database_id;

    if($outputFormat=="json"){
        header("Content-type: text/javascript");
        print json_format(array("error"=>$msg));
    }else{
        ?>

        <html>
            <head>
                <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
                <meta http-equiv="content-type" content="text/html; charset=utf-8">
            </head>
            <body>
                <div class="error" style="text-align:left;padding:40px;">ERROR: <?=$msg?></div>
            </body>
        </html>

        <?php
    }

    if(isset($mysqli)){
        $mysqli->rollback();
        $mysqli->close();
        if($outputFormat!="json") {
            //ROLLBACK
            print '<div style="text-align:left;padding:40px;">No record types, fields or terms have been imported</div>';
        }
    }

    if(checkSmtp()){
    
        $email_title = 'Annotated template. Error on import record type definitions into '.HEURIST_DBNAME;
        $email_text = 'Database '.HEURIST_DBNAME."\n\n".
                      (isset($database_id)?'Source databse #: '.$database_id."\n\n":'').
                      'Error Message: '.$msg;
        sendEmail(HEURIST_MAIL_TO_BUG, $email_title, $email_text, null);
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
        return false;
    }

    $def_rts = $defs['rectypes']['typedefs'];
    $def_dts = $defs['detailTypes']['typedefs'];
    $idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
    $idx_ccode = intval($def_rts['commonNamesToIndex']["rty_ConceptID"]);
    $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];

    $ccode = $def_rts[$rectype_id]['commonFields'][$idx_ccode];

    if(false && $excludeDuplication){
        $local_recid = findByConceptCode($ccode, $trg_rectypes['typedefs'], $idx_ccode);
        if($local_recid){  //already exist
            $rectypes_correspondence[$rectype_id] = $local_recid;
            return false; //rectype with the same concept code is already in database
        }
    }else{
        $correspondence = findByConceptCode($ccode, $trg_rectypes['typedefs'], $idx_ccode, true);
        if(count($correspondence)>0){
            $rectypes_correspondence[$rectype_id] = $correspondence[0];
        }
    }

    array_push($imp_recordtypes, $rectype_id);

    $res = array('correspondence'=>$correspondence, 'dependence'=>array());

    $fields = $def_rts[$rectype_id]['dtFields'];

    if(is_array($fields))
        //loop all fields and check constraint for pointers and relmarkers
        foreach ($fields as $ftId => $field){
            if($field[$idx_type] == "resource" || $field[$idx_type] == "relmarker"){

                $constraints = $def_dts[$ftId]['commonFields'][$idx_constraints];

                $rty_ids = explode(",", $constraints);
                foreach ($rty_ids as $rty_id){
                    if(@$defs['rectypes']['names'][$rty_id]){

                        $dep = findDependentRecordTypes($rty_id, $depth+1);
                        if($dep){
                            $res['dependence'][$rty_id] = $dep;
                        }

                    }
                }
            }
    }

    return $res;
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
// function that translates all term ids in the passed string to their local/imported value
//
function replaceTermIds( $sterms, $domain ) {
    global $terms_correspondence;

    if($sterms==null || $sterms=="") return $sterms;

    if($domain=="relationtype") $domain = "relation";

    // Import terms

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

    global $defs, $imp_terms, $terms_correspondence, $terms_correspondence_existed, $trg_terms, $excludeDuplication;

    $terms = $defs['terms'];

    if($term_id==null){
        //import entire list of terms
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
            if(@$term_import[$idx_parentid] && @$trg_terms['treesByDomain'][$domain][$term_import[$idx_parentid]]){
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

    if(is_array($lvl_src)){

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

    }

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

    if($name1=="Header"){
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
function findByConceptCode($ccode, $entities, $idx_ccode, $sall=false){

    $res = array();

    foreach ($entities as $id => $def) {
        if(is_numeric($id) && $def['commonFields'][$idx_ccode]==$ccode){
            if($sall){
                array_push($res, $id);
            }else{
                return $id;
            }
        }
    }
    return ($sall)?$res:null;
}



// a couple of function from h4/db_records.php
// TODO: Put these in a library



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
        $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
        if (strrpos($temp,":") == strlen($temp)-1) {
            $temp = substr($temp,0, strlen($temp)-1);
        }
        $termIDs = explode(":",$temp);
    } else {
        $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
        $termIDs = explode(",",$temp);
    }

    // Validate termIDs
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
//  Find vocabulary ID
//
function getTopMostTermParent($term_id, $domain, $topmost=null) {
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



//
// Show the form with list of record types to be imported
//
// array($rectype_id=>array('correspondence'=>$correspondence, 'dependence'=>array()));
function renderPreviewForm(){
    global $imp_recordtypes_tree, $imp_recordtypes, $rectypes_correspondence, $defs, $req_rectype_code;
    //if(count($imp_recordtypes)>1 || count($rectypes_correspondence)>0){
    $end_s = count($imp_recordtypes)>1?'s':'';
    if(count($imp_recordtypes)>0){

        $rectype_id = $imp_recordtypes[0];
        $def_rts = $defs['rectypes']['typedefs'];
        $idx_ccode = $def_rts['commonNamesToIndex']['rty_ConceptID'];

        $title = @$defs['rectypes']['names'][$rectype_id]."(".@$def_rts[$rectype_id]['commonFields'][$idx_ccode].")";
    }else{
        $title = "";
    }

    ?>

    <html>
        <head>
            <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
            <title>Importing <?=$title?></title>
            <meta http-equiv="content-type" content="text/html; charset=utf-8">
        </head>
        <body class="popup">

            <br/>
            <form action="importRectype.php" method="POST">
            
                <input type="hidden" name='correspondence' value='<?=json_encode($rectypes_correspondence)?>' />
                <input type="hidden" name='db' value='<?=HEURIST_DBNAME?>' />
                <input type="hidden" name='code' value='<?=$req_rectype_code?>' />
                <?php
                if(count($imp_recordtypes)>1){
                    print "<div>The requested record type <b>"
                    .$defs['rectypes']['names'][$imp_recordtypes[0]]
                    ."</b> has related record types (they are referenced by pointer fields and relationship markers). "
                    ."They will be imported as well.</div><br/>";
                }

                if(count($rectypes_correspondence)>0){
                    ?>
                    <table border="0" width="100%">
                        <tr style='text-align:left'>
                            <th>The requested<br>Record type<?=$end_s?></th><th>Concept ID</th><th>Already represented<br> in your database as:
                            </th><th>Check to import<br>as new record type</th></tr>
                        <?php
                        renderPreviewFormRelated($imp_recordtypes_tree, 0);
                        ?>
                    </table>
                    <br/>
                    <div>Record types that are already represented in your database may have a different structure
                        from the template you are importing, and you may therefore wish to continue with this import
                        to create a new record type. Mark checkboxes in this case
                    </div>
                    <?php
                }else{
                    print "<table>";
                    renderPreviewFormRelated($imp_recordtypes_tree, 0);
                    print "</table>";
                }
                ?>

                <div class="actionButtons">
                    <input type="submit" value="Start Import" style="margin-right: 5px;">
                    <input type="button" value="Cancel" style="font-weight: bold;" onclick=window.close()>
                </div>
            </form>

        </body>
    </html>
    <?php
}



//
//
//
function renderPreviewFormRelated($dep_rectypes, $level) {
    global $defs, $trg_rectypes, $rectypes_correspondence, $remote_url, $remote_url_params;

    $remote_link = $remote_url."admin/adminMenuStandalone.php?".$remote_url_params;

    $def_rts = $defs['rectypes']['typedefs'];
    $idx_ccode = $def_rts['commonNamesToIndex']['rty_ConceptID'];

    foreach($dep_rectypes as $rectype_id=>$info){
        print "<tr><td style='padding-left:".($level*10)."px'>"
        ."<a href='".$remote_link."&mode=rectype&rtID=".$rectype_id."' target='_blank'>"
        .$defs['rectypes']['names'][$rectype_id]."</a></td>";

        print "<td>".$def_rts[$rectype_id]['commonFields'][$idx_ccode]."</td>"; //concept code

        if(count($rectypes_correspondence)>0){
            print "<td>";
            //list of correspondence
            foreach($info['correspondence'] as $idx=>$local_rectype_id){
                print "<div><a href='../../adminMenuStandalone.php?db=".HEURIST_DBNAME."&mode=rectype&rtID=".$local_rectype_id."' target='_blank'>"
                .$trg_rectypes['names'][$local_rectype_id]."</a></div>";
            }

            if(count($info['correspondence'])>0){
                print "</td><td align='center'><input type='checkbox' name='import[]' value='".$rectype_id."' />";
            }else{
                print "&nbsp;</td><td><input type='hidden' name='import[]' value='".$rectype_id."' />&nbsp;";
            }
            print "</td>";
        }

        print "</tr>";

        //list of dependent
        renderPreviewFormRelated($info['dependence'], $level+1);
    }

}

?>
