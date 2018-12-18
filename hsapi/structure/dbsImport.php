<?php
/**
* dbsImport.php - import definition from other database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
require_once(dirname(__FILE__).'/../System.php');
require_once(dirname(__FILE__).'/../dbaccess/db_structure.php');
require_once('dbsTerms.php');

require_once(dirname(__FILE__).'/../utilities/utils_file.php');
require_once(dirname(__FILE__).'/../../admin/structure/saveStructureLib.php');


class DbsImport {

    private $system = null;

    private $imp_recordtypes;
    private $rectypes_correspondence;
    
    private $imp_fieldtypes;
    private $fields_correspondence;
        
    private $imp_terms;
    private $terms_correspondence;
            
    private $source_defs = null;
    private $target_defs = null;

    private $targetTerms = null;
    private $sourceTerms = null; //ref to DbsTerms
    private $sourceIconURL = null; //url to copy rectype icons
    private $source_db_name;

    //  $data = 
    function __construct( $system ) {
        $this->system = $system;
    }
    
    
// 1. get database url
// 2. get definitions from remote database
// 3. Find what defintions will be imported

// 4. With list of all record types, build a list of all the base field types:
// 5. Perform database action - add rectypes, structure, fields and terms into our database
    
    /**
    * Find all defintions to be imported
    * 
    * @param mixed $data
    * 
    * array(
        defType - rectype,detailtype,term
        conceptCode - array(dbid, defid, dbregid) or string dbid-defid
                    dbregid - where to search definition - optional, if not defined take dbid
        conceptName
      )
    * @return mixed
    */
    public function doPrepare( $data ){
        
        $defType = $data['defType']; //rt|rectype,field|dt|detailtype,term
        
        $allowed_types = array('rectype','detailtype','term');
        
        if(!in_array($defType,$allowed_types)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "No allowed type to import");
            return false;
        }
        
        $cCode = $data['conceptCode'];
        
        if($cCode){
            if(is_array($cCode)){
                $cCode = implode('-',$cCode);
            }            
            list($db_reg_id, $def_id) = explode('-', $cCode);
        }
        
        if(!($cCode && is_numeric($db_reg_id) && $db_reg_id>0 && is_numeric($def_id) && $def_id>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Concept code ($cCode) has wrong format - should be two numbers separated by dash");
            return false;
        }
        
        $db_origin_id = $db_reg_id; //not used
        
        // 1. get database url by database id
        $database_url = $this->_getDatabaseURL($db_reg_id);

        if(!$database_url){
            return false; //see $system->getError
        }        
        
        // 2. get definitions from remote database
        $this->source_defs  = $this->_getDatabaseDefinitions($database_url, $db_reg_id, ($defType=='term'));
        if (!$this->source_defs) {
            return false; //see $system->getError
        }

        $this->sourceTerms = new DbsTerms(null, $this->source_defs['terms']);
        if($defType=='term'){
            $def_id = $this->sourceTerms->findTermByConceptCode($cCode); 
        }else{
            $def_id = $this->_getLocalCode($defType, $this->source_defs, $cCode); //get local id is source db
        }
        
        if (!($def_id>0)) { //definition not found in source database
            $this->system->addError(HEURIST_ERROR, 'Unable to get '.$defType. ' definition with concept code '.$cCode.' in database #'.$db_reg_id);
            return false; //see $system->getError
        }
        
        //definitions to be imported - list of source local ids
        $this->imp_recordtypes = array();
        $this->imp_fieldtypes = array();
        $this->imp_terms = array("enum"=>array(), "relation"=>array());

        //source id => target id  - local ids 
        $this->rectypes_correspondence = array(); //source rectypeID => new (target) rectype ID
        $this->fields_correspondence = array();  //import field id -> target id - IMPORTANT for proper titlemask conversion
        //$fields_correspondence_existed = array();
        $this->terms_correspondence = array(); //"enum"=>array(), "relation"=>array());
        //$terms_correspondence_existed = array();
        
        //target(local) definitions
        $this->target_defs = array();
        if($defType!='term'){
            $this->target_defs['rectypes'] = dbs_GetRectypeStructures($this->system, null, 2);
            $this->target_defs['detailtypes'] = dbs_GetDetailTypes($this->system, null, 2);
        }

        $this->target_defs['terms'] = dbs_GetTerms($this->system);
        $this->targetTerms = new DbsTerms($this->system, $this->target_defs['terms']);

    
        // 3. Find what defintions will be imported
        if($defType=='term'){
            
            $this->_getTopMostVocabulary($def_id, 'enum');
            if(count($this->imp_terms['enum'])==0)
                $this->_getTopMostVocabulary($def_id, 'relation');
            
        }else{
            
            if($defType=='rectype'){
                //find record types
                $this->_findDependentRecordTypes($def_id, null, 0);
                
                if(count($this->imp_recordtypes)==0){
                    $this->system->addError(HEURIST_NOT_FOUND, 'No one record type to be imported found');
                    return false;
                }
        
                
            } else if($defType=='detailtype'){
                
                $this->_findDependentRecordTypesByFieldId($def_id);
                
                if(count($this->imp_fieldtypes)==0){
                    $this->system->addError(HEURIST_NOT_FOUND, 'No one field to be imported found');
                    return false;
                }
                
            }
            
            
            // 4. With list of all record types, build a list of all base field types to be imported
            $def_rts = $this->source_defs['rectypes']['typedefs'];
            $def_dts = $this->source_defs['detailtypes']['typedefs'];
            
            $idx_type = $def_dts['fieldNamesToIndex']['dty_Type'];
            $idx_ccode = $def_dts['fieldNamesToIndex']['dty_ConceptID'];
            $idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];

            foreach ($this->imp_recordtypes as $rty_id){
                $fields = $this->source_defs['rectypes']['typedefs'][$rty_id]['dtFields'];
                foreach ($fields as $ftId => $field){

                    if(!(@$this->fields_correspondence[$ftId] || in_array($ftId, $this->imp_fieldtypes) )){

                        $ccode = $def_dts[$ftId]['commonFields'][$idx_ccode];
                        $local_ftId = $this->_getLocalCode('detailtype', $this->target_defs, $ccode);

                        if($local_ftId>0){
                            $this->fields_correspondence[$ftId] = $local_ftId;
                            //$this->fields_correspondence_existed[$ftId] = $local_ftId;
                            continue; //field with the same concerpt code is already in database
                        }

                        array_push($this->imp_fieldtypes, $ftId);

                        // ------------------------------------------------------------------------------------------------

                        //Get vocabulary for all terms used
                        $dt_Type = $def_dts[$ftId]['commonFields'][$idx_type];
                        if($dt_Type == "enum" || $dt_Type == "relmarker" || $dt_Type == "relationtype"){
                            //get topmost vocabulary
                            $this->_getTopMostVocabulary($def_dts[$ftId]['commonFields'][$idx_terms], $dt_Type);
                        }
                    }
                }
            }//for
        }
     
        return true;   
    }
    
    private function error_exit2($msg){

        $this->system->addError(HEURIST_ERROR, $msg);
        
        $mysqli = $this->system->get_mysqli();
        $mysqli->rollback();
        $mysqli->close();
        
    }

    //
    // Perform database action - add rectypes, structure, fields and terms into our database
    //
    public function doImport( ){
        
        global $mysqli;
        $mysqli = $this->system->get_mysqli();
        $mysqli->autocommit(FALSE);

        
        // I. Add Terms (whole vocabulary)
        if(! ($this->_importVocabulary(null, "enum") && 
              $this->_importVocabulary(null, "relation")) ){

            $mysqli->rollback();
            $mysqli->close();
            return false;                
        }
        
        
        $group_ft_ids = array();
        $group_rt_ids = array();
        $def_rts = $this->source_defs['rectypes']['typedefs'];
        $def_dts = $this->source_defs['detailtypes']['typedefs'];
        
        $trg_rectypes = $this->target_defs['rectypes'];
        $trg_detailtypes = $this->target_defs['detailtypes'];
        
// II. Add new record type groups --------------------------------------------------------------------------------------

$columnNames = array("rtg_Name","rtg_Order","rtg_Description");
$idx_rt_grp = $def_rts['commonNamesToIndex']['rty_RecTypeGroupID'];

foreach ($this->imp_recordtypes as $recId){

    $rt_name = @$this->source_defs['rectypes']['names'][$recId];
    if(!@$def_rts[$recId]){
        if(!$rt_name){
            $this->error_exit2("Can't find record type #'".$recId."'. in source database");
        }else{
            $this->error_exit2("Can't find definitions for record type #'".$recId."'. \"$rt_name\" in source database");
        }
        return false;
    }

    $grp_id = @$def_rts[$recId]['commonFields'][$idx_rt_grp];

    if(!$grp_id){
        $this->error_exit2("Group ID is not defined for record type #'".$recId."'. \"$rt_name\" in source database");
        return false;
    }

    $src_group=null;

    if(@$group_rt_ids[$grp_id]){ //already found
        continue;
    }

    //find group in source by ID
    foreach ($this->source_defs['rectypes']['groups'] as $idx=>$group){
        if(is_numeric($idx) && $group['id']==$grp_id){
            $src_group = $group;
            $grp_name = @$src_group['name'];
            break;
        }
    }

    if($src_group==null){
        $this->error_exit2("Can't find group #".$grp_id." for record type #'".$recId."'. \"$rt_name\" in source database");
        return false;
    }
    if(!$grp_name){
        $this->error_exit2("Name of group is empty. Can't add group #".$grp_id." for record type #'"
                                    .$recId."'. \"$rt_name\" in source database");
        return false;
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
            $this->error_exit2("Can't add record type group '".$grp_name."'. ".@$res['error']);
            return false;
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

foreach ($this->imp_recordtypes as $rtyID){

    $def_rectype = $def_rts[$rtyID]['commonFields'];

    //replace group id with local one
    $grp_id = $def_rectype[$idx_rt_grp];
    $def_rectype[$idx_rt_grp] = $group_rt_ids[$grp_id];

    //disambiguate name
    $def_rectype[$idx_name] = $this->doDisambiguate($def_rectype[$idx_name], $trg_rectypes['names']);

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
        $this->rectypes_correspondence[$rtyID] = $new_rtyID;
        $trg_rectypes['names'][$new_rtyID] = $def_rectype[$idx_name];
        $this->copyRectypeIcon($rtyID, $new_rtyID);

    }else{

        $this->error_exit2("Cannot add record type for id#".$recId.". ".$res);
        return false;

    }
}


// ------------------------------------------------------------------------------------------------

// IV. Add new field type groups

$columnNames = array("dtg_Name","dtg_Order","dtg_Description");
$idx_dt_grp = $def_dts['fieldNamesToIndex']['dty_DetailTypeGroupID'];

foreach ($this->imp_fieldtypes as $ftId){
    $grp_id = $def_dts[$ftId]['commonFields'][$idx_dt_grp];
    if(@$group_ft_ids[$grp_id]){ //already found
        continue;
    }

    foreach ($this->source_defs['detailtypes']['groups'] as $idx=>$group){
        if(is_numeric($idx) && $group['id']==$grp_id){
            $src_group = $group;
            $grp_name = $src_group['name'];
            break;
        }
    }

    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_detailtypes['groups'] as $idx=>$group){
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
            $trg_detailtypes['groups'][$new_grp_id] = array('name'=>$grp_name);

        }else{

            $this->error_exit2("Can't add field type group for '".$grp_name."'. ".$res['error']);
            return false;

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

foreach ($this->imp_fieldtypes as $ftId){

    $def_field = $def_dts[$ftId]['commonFields'];

    //replace group id
    $grp_id = $def_field[$idx_dt_grp];
    $def_field[$idx_dt_grp] = $group_ft_ids[$grp_id];

    //disambiguate name
    $def_field[$idx_name] = $this->doDisambiguate($def_field[$idx_name], $trg_detailtypes['names']);

    if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype" || $def_field[$idx_type] == "relmarker"){
        //change terms ids for enum and reltypes
        $def_field[$idx_terms_tree] = $this->replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
        $def_field[$idx_terms_disabled] = $this->replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
    }
    if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
        //change record ids for pointers
        $def_field[$idx_constraints] = $this->replaceRecIds(@$def_field[$idx_constraints]);
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
        $this->fields_correspondence[$ftId] = abs($res);
        $trg_detailtypes['names'][abs($res)] = $def_field[$idx_name-1]; //new name
    }else{
        $this->error_exit2("Can't add field type for id#".$ftId.". ".$res);
        return false;
    }
}


// ------------------------------------------------------------------------------------------------

// VI. Add record structures

$idx_type           = $def_rts['dtFieldNamesToIndex']['dty_Type'];
$idx_terms_tree     = $def_rts['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];  //value is the same as
$idx_terms_disabled = $def_rts['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
$idx_constraints    = $def_rts['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];

$dtFieldNames = $def_rts['dtFieldNames'];

foreach ($this->imp_recordtypes as $rtyID){

    if(@$this->rectypes_correspondence[$rtyID]){

        $fields = array();
        foreach ($def_rts[$rtyID]['dtFields'] as $ftId => $def_field){

            if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype" || $def_field[$idx_type] == "relmarker"){
                //change terms ids for enum and reltypes
                $def_field[$idx_terms_tree] = ""; //$this->replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
                $def_field[$idx_terms_disabled] = ""; //$this->replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);

            }
            if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
                //change record ids for pointers
                $def_field[$idx_constraints] = ""; //$this->replaceRecIds(@$def_field[$idx_constraints]);
            }

            $fields[ $this->fields_correspondence[$ftId] ] = $def_field;
        }

        $ret = updateRecStructure( $dtFieldNames , $this->rectypes_correspondence[$rtyID], array("dtFields"=>$fields));
        if(is_array($ret)){
            foreach($ret as $id=>$res2){
                foreach($res2 as $dtid=>$res){
                    if(!is_numeric($res)){
                        $this->error_exit2("Can't update record type structure rectype#".$id.". ".$res);
                        return false;
                    }
                }
            }

        }else{

            $this->error_exit2("Can't update record type structure rectype#".$this->rectypes_correspondence[$rtyID].". ".$ret);
            return false;

        }
    }
}


// ------------------------------------------------------------------------------------------------

// VII. Update titlemasks with new ids

//$mysqli->commit();

TitleMask::set_fields_correspondence($this->fields_correspondence);

foreach ($this->imp_recordtypes as $rtyID){
    if(@$this->rectypes_correspondence[$rtyID]){

        $mask = $def_rts[$rtyID]['commonFields'][$idx_titlemask_canonical];
//echo $mask;
        // note we use special global array $this->fields_correspondence - for proper conversion of remote id to concept code
        $res = updateTitleMask( $this->rectypes_correspondence[$rtyID], $mask);
        if(!is_numeric($res)){
            $this->error_exit2($res);
            return true;
        }
    }
}

TitleMask::set_fields_correspondence(null);

$mysqli->commit();        
            
            return true;
    }
    
    //
    // returns database URL by its ID in master index database see getDatabaseURL
    // @todo move it to special class?
    //
    private function _getDatabaseURL($database_id){
        
        $to_include = dirname(__FILE__).'/../../admin/setup/dbproperties/getDatabaseURL.php';
        if (is_file($to_include)) {
            include_once $to_include;
        }

        if(isset($error_msg)){
            $this->system->addError(HEURIST_ERROR, $error_msg);
            return false;
        }
        
        return $database_url;
    }


    //
    // returns source database definitions by database url
    //
    private function _getDatabaseDefinitions($database_url, $database_id, $only_terms){
        
        
        $reg_url = explode("?",$database_url);
        $remote_url = @$reg_url[0];

        preg_match("/db=([^&]*).*$/", $database_url, $match);
        $remote_dbname = $match[1];

        //TEMP
        if(strpos($database_url, 'http://heurist.sydney.edu.au/')===0){
            $remote_url = 'http://heurist.sydney.edu.au/h5-ao/';
        }


        if(!$remote_dbname || !$remote_url){
            $this->system->addError(HEURIST_ERROR, 
                "Heurist Master Index returns incorrect data for registered database # ".$database_id.
                " The page may contain an invalid database reference");
            return false;
        }
        
        if(strpos($remote_url, HEURIST_SERVER_URL)===0){ //same domain

          $defs = array();  
          
          $system2 = new System();
          $system2->init($remote_dbname, true, false); //init without paths and consts
          
          
          if(!$only_terms){
            $defs['rectypes'] = dbs_GetRectypeStructures($system2, null, 2 );
            $defs['detailtypes'] = dbs_GetDetailTypes($system2, null, 2 );
          }
          $defs["terms"] = dbs_GetTerms($system2);
          
        }else{
        //2b. if remote server - call sys_strcture.php with loadRemoteURLContentWithRange
        
           $remoteURL = $remote_url.'hsapi/controller/sys_structure.php?mode=2&terms=all&db='.$remote_dbname;
           if(!$only_terms){
                $remoteURL = $remoteURL.'&rectypes=all&detailtypes=all';
           }

           $defs = loadRemoteURLContent($remoteURL);            
           $defs = json_decode(gzdecode($defs), true);
           if (!$defs || @$defs['status']!=HEURIST_OK) {
                $this->system->addError(HEURIST_ERROR,
                        "Unable to contact the selected source database, possibly due to a timeout or proxy setting");
                return false;
           }
           $defs = $defs['data'];
        }
        if (!($defs['terms']  &&  ($only_terms || ($defs['rectypes'] && $defs['detailtypes'])))) {
            $this->system->addError(HEURIST_ERROR, "Structure definitions read from source database # $database_id are invalid. Please advise Heurist development team");
            return false;
        }
        
        $this->sourceIconURL = $remote_url.'hsapi/dbaccess/rt_icon.php?db='.$remote_dbname.'&id=';
        $this->source_db_name = $remote_dbname;
        
        return $defs; 
    }
    
    //
    // get local code by concept code
    //
    private function _getLocalCode($defType, $database_defs, $conceptCode, $sall=false){

        $res = array();

        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){
            $defType = 'rectypes';
            $fieldName = 'rty_ConceptID';
            $defs = $database_defs[$defType]['typedefs'];
            $idx_ccode = intval($defs['commonNamesToIndex'][$fieldName]);
            
        }else if($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){
            $defType = 'detailtypes';
            $fieldName = 'dty_ConceptID';
            $defs = $database_defs[$defType]['typedefs'];
            $idx_ccode = intval($defs['fieldNamesToIndex'][$fieldName]);
        }
    
        
    
        foreach ($defs as $id => $def) {
            if(is_numeric($id) && $def['commonFields'][$idx_ccode]==$conceptCode){
                if($sall){
                    array_push($res, $id);
                }else{
                    return $id;
                }
            }
        }
        
        return ($sall)?$res:null;
        
    }
    
    // Find vocabularies to be imported
    // Fill global $this->imp_terms with the top most vocabulary
    // $terms_ids - list of terms (json or csv) from field definition
    // $imp_terms - list of source vocabulary ids to be imported
    //
    private function _getTopMostVocabulary($terms_ids, $domain){

        if($domain=='relationtype' || $domain=='relmarker'){
            $domain = 'relation';    
        } 

        //array of valid ids
        $terms_ids =  $this->sourceTerms->getTermsFromFormat($terms_ids, $domain);
        if(is_array($terms_ids))
        foreach ($terms_ids as $term_id){
            $topmost = $this->sourceTerms->getTopMostTermParent($term_id, $domain);
            if($topmost && !in_array($topmost, $this->imp_terms[$domain])){
                array_push($this->imp_terms[$domain], $topmost);
            }
        }
    }    

    //
    //
    //
    private function _findDependentRecordTypesByFieldId($field_id){

        $res = array();
        
        $def_dts = $this->source_defs['detailtypes']['typedefs'];
        $idx_type = $def_dts['fieldNamesToIndex']['dty_Type'];
        $idx_ccode = $def_dts['fieldNamesToIndex']['dty_ConceptID'];
        $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
        $idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];

        $field = $def_dts[$field_id]['commonFields'];
        
        $ccode = $field[$idx_ccode]; //from source

        //is this field type already in target?
        $local_dtid = $this->_getLocalCode('detailtype', $this->target_defs, $ccode); 
        if($local_dtid>0){  //already exist
            $this->fields_correspondence[$field_id] = $local_dtid;
        }
        
        array_push($this->imp_fieldtypes, $field_id);
        
        $res = array();
        
        $dt_Type = $field[$idx_type];

        if($dt_Type == "resource" || $dt_Type == "relmarker"){

            $constraints = $field[$idx_constraints];

            $rty_ids = explode(",", $constraints);
            foreach ($rty_ids as $rty_id){
                if(@$this->source_defs['rectypes']['names'][$rty_id]){

                    $dep = $this->_findDependentRecordTypes($rty_id, 0);
                    if($dep){
                        array_push($res, $dep);
                    }

                }
            }
        }
        if($dt_Type == "enum" || $dt_Type == "relmarker" || $dt_Type == "relationtype"){
            //find vocabulary to be imported
            $this->_getTopMostVocabulary($field[$idx_terms], $dt_Type);
        }
        
        
        return $res; 
    }
    
    //
    // search source defintions
    // find all dependend record types in pointer constraints
    // 1) returns rt tree 2) fills $this->imp_recordtypes, $this->rectypes_correspondence
    //
    private function _findDependentRecordTypes($rectype_id, $depth){
        
        $excludeDuplication = true;
        
        $trg_rectypes = $this->target_defs['rectypes'];
       

        if(!($rectype_id>0) || in_array($rectype_id, $this->imp_recordtypes) || $depth>9){
            //already in array
            return false;
        }

        $def_rts = $this->source_defs['rectypes']['typedefs'];
        $def_dts = $this->source_defs['detailtypes']['typedefs'];
        $idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
        $idx_ccode = $def_rts['commonNamesToIndex']['rty_ConceptID'];
        $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];

        $ccode = $def_rts[$rectype_id]['commonFields'][$idx_ccode]; //from source

        //is this record type already in target?
        $local_recid = $this->_getLocalCode('rectype', $this->target_defs, $ccode); 
        if($local_recid>0){  //already exist
            $this->rectypes_correspondence[$rectype_id] = $local_recid;
            if($excludeDuplication){
                    return false; //rectype with the same concept code is already in database
            }
            //$correspondence = array($local_recid);
        }

        array_push($this->imp_recordtypes, $rectype_id);

        $res = array('correspondence'=>$local_recid, 'dependence'=>array());

        $fields = $def_rts[$rectype_id]['dtFields'];

        if(is_array($fields))
        //loop all fields and check constraint for pointers and relmarkers
        foreach ($fields as $ftId => $field){
                if($field[$idx_type] == "resource" || $field[$idx_type] == "relmarker"){

                    $constraints = $def_dts[$ftId]['commonFields'][$idx_constraints];

                    $rty_ids = explode(",", $constraints);
                    foreach ($rty_ids as $rty_id){
                        if(@$this->source_defs['rectypes']['names'][$rty_id]!=null){

                            $dep = $this->_findDependentRecordTypes($rty_id, $depth+1);
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
    // import entire vocabulary
    //
    private function _importVocabulary($term_id, $domain, $children=null, $parent_id=null, $same_level_labels=null){
        

        if($term_id==null){
            //top level import vocabularies
                
            foreach($this->imp_terms[$domain] as $term_id){
                $res = $this->_importVocabulary($term_id, $domain, 
                            @$this->source_defs['terms']['treesByDomain'][$domain][$term_id]);
                if(!$res) return false;
            }
            
            return true;
        }else{

            $terms = $this->source_defs['terms'];
        
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
            $new_term_id = $this->targetTerms->findTermByConceptCode($term_import[$idx_ccode], $domain);

            if($new_term_id){
                //such term already exists
                //$terms_correspondence_existed[$term_id] = $new_term_id;
            }else{
                //if not found add new term

                //change trm_InverseTermID, trm_ParentTermID  to new id from target
                $term_import[$idx_parentid] = $parent_id;//@$this->terms_correspondence[$term_import[$idx_parentid]];
                $term_import[$idx_inverseid] = @$this->terms_correspondence[$term_import[$idx_inverseid]]; //@todo - after all terms addition?
                
                //get level - all terms of the same level - to search same name and codes
                //$lvl_src = $this->targetTerms->findChildren($term_import[$idx_parentid], $domain);
                
                //verify that code and label is unique for the same level in target(local) db
                if($parent_id==null){
                    //for vocabularies
                    $term_import[$idx_code] = $this->targetTerms->doDisambiguateTerms($term_import[$idx_code], 
                                                            null, $domain, $idx_code);
                    $term_import[$idx_label] = $this->targetTerms->doDisambiguateTerms($term_import[$idx_label],
                                                            null, $domain, $idx_label);
                }else{
                    $term_import[$idx_code] = $this->targetTerms->doDisambiguateTerms2($term_import[$idx_code], $same_level_labels['code']);
                    $term_import[$idx_label] = $this->targetTerms->doDisambiguateTerms2($term_import[$idx_label], $same_level_labels['label']);
                }
                

                //fill original ids if missed
                if($term_import[$idx_ccode] && (!$term_import[$idx_origin_dbid] || !$term_import[$idx_origin_id])){
                    $codes = explode("-",$term_import[$idx_ccode]);
                    if($codes && count($codes)==2){
                        $term_import[$idx_origin_dbid] = $codes[0];
                        $term_import[$idx_origin_id] = $codes[1];
                    }
                }

                $res = updateTerms($columnNames, null, $term_import, $this->system->get_mysqli()); //see saveStructureLib
                if(is_numeric($res)){
                    $new_term_id = $res;
                    
                    $this->targetTerms->addNewTerm($new_term_id, $term_import);
                    
                }else{
                    $this->system->addError(HEURIST_ERROR, "Can't add term ".$term_id.' '.print_r($term_import, true)."  ".$res);
                    return false;
                }
            }

            //fill $terms_correspondence
            $this->terms_correspondence[$term_id] = $new_term_id;

            if($children){
                $lvl_src = array('code'=>array(),'label'=>array());
                
                foreach($children as $id=>$children2){
                    //($term_id, $domain, $children=null, $parent_id=null, $same_level_labels=null)
                    $new_id = $this->_importVocabulary($id, $domain, $children2, $new_term_id, $lvl_src);
                    if($new_id>0){
                        
                        $lvl_src['code'][] = $this->targetTerms->getTermCode($new_id);
                        $lvl_src['label'][] = $this->targetTerms->getTermLabel($new_id);
                    } else {
                        return false;
                    }
                    
                }
            }
            
            return $new_term_id;
        }
    }

    // @todo
    // Copy record type icon and thumbnail from source to destination database
    //
    private function copyRectypeIcon($source_RtyID, $target_RtyID, $thumb=""){
        
        $targetPath = HEURIST_ICON_DIR.$thumb.$target_RtyID.".png";
        
        //check if the same server with target
        if(strpos($this->sourceIconURL, HEURIST_SERVER_URL)===0){ 
            
            $filename = HEURIST_FILESTORE_ROOT.$this->source_db_name."/rectype-icons/".$thumb.$source_RtyID.".png";
            if(file_exists($filename)){
                
                if(file_exists($targetPath)){
                    unlink($targetPath);
                }

                if (!copy($filename, $targetPath)) {
                    //makeLogEntry("<b>Warning</b> Importing Record-type", $importRtyID, " Can't copy ".(($thumb=="")?"icon":"thumbnail")." ".$filename." to ".$target_filename);
                }
            }else{
                //makeLogEntry("<b>Warning</b> Importing Record-type", $importRtyID, " ".(($thumb=="")?"icon":"thumbnail")." does not exist");
            }
        

        }else{
            $sourceURL = $this->sourceIconURL.$thumb.$source_RtyID.".png";

            //print "<br>sourcce=".$sourceURL;
            //print "<br>path=".$targetPath;

            saveURLasFile($sourceURL, $targetPath); //see utils_file
        }

        if($thumb==""){
            $this->copyRectypeIcon($source_RtyID, $target_RtyID, "thumb/th_");
        }
    }
    
    //
    //
    //
    private function doDisambiguate($newvalue, $entities){

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
    // replace recid in constraint string to local id
    //
    private function replaceRecIds($constraints){
        
        if($constraints){
            $recids = explode(",", $constraints);
            $recids_new = array();
            foreach($recids as $recid){
                if(@$this->rectypes_correspondence[$recid])
                    array_push($recids_new, $this->rectypes_correspondence[$recid]);
            }
            $constraints = implode(",",$recids_new);
        }
        return $constraints;
    }
    
    //
    // replace term_ids in string to new ones
    // function that translates all term ids in the passed string to their local/imported value
    //
    private function replaceTermIds( $sterms, $domain ) {

        if($sterms==null || $sterms=="") return $sterms;

        if($domain=="relationtype") $domain = "relation";

        // Import terms

        if (strpos($sterms,"{")!== false) {
            foreach ($this->terms_correspondence as $imp_id=>$trg_id) {
                //replace termID in string
                $sterms = preg_replace("/\"".$imp_id."\"/","\"".$trg_id."\"",$sterms);
            }
        }else{
            $aterms = explode(",",$sterms);
            $aterms_new = array();
            foreach ($aterms as $imp_id) {
                if(@$this->terms_correspondence[$imp_id]){
                    array_push($aterms_new, $this->terms_correspondence[$imp_id]);
                }
            }
            $sterms = implode(",", $aterms_new);
        }
        return $sterms;
    }    
        
    //
    // Preparation report output
    //    
    public function getReport(){

            //reload structures
            $trg_rectypes = null;
            $trg_detailtypes = null;
            $trg_terms = null;

            $def_rts = @$this->source_defs['rectypes']['typedefs'];
            $def_dts = @$this->source_defs['detailtypes']['typedefs'];

            $sRectypes = '';
            $sFields  = '';
            $sTerms  = '';
            
       //RECORD TYPES
       if($def_rts){
                
            $idx_name  = $def_rts['commonNamesToIndex']['rty_Name'];
            $idx_ccode = $def_rts['commonNamesToIndex']["rty_ConceptID"];
            
            foreach ($this->imp_recordtypes as $imp_id){
                if(@$this->rectypes_correspondence[$imp_id]){
                    $trg_id = $this->rectypes_correspondence[$imp_id];
                    
                    if($trg_rectypes==null){
                        $trg_rectypes = dbs_GetRectypeStructures($this->system, null, 2);
                    }

                    $sRectypes = $sRectypes."<tr><td>$imp_id</td><td>".$def_rts[$imp_id]['commonFields'][$idx_name]
                    ."</td><td>"
                    .$def_rts[$imp_id]['commonFields'][$idx_ccode]
                    ."</td><td>$trg_id</td><td>"
                    .$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_name]
                    ."</td>" //."<td>".$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_titlemask_canonical]."</td>"
                    ."</tr>";
                }
            }

            //FIELD TYPES
            $idx_name  = $def_dts['fieldNamesToIndex']['dty_Name'];
            $idx_ccode = $def_dts['fieldNamesToIndex']["dty_ConceptID"];

            foreach ($this->fields_correspondence as $imp_id=>$trg_id){

                if(@$this->fields_correspondence_existed[$imp_id]) continue;

                if($trg_detailtypes==null){
                    $trg_detailtypes = dbs_GetDetailTypes($this->system, null, 2);
                }

                $sFields = $sFields."<tr><td>$imp_id</td><td>".$def_dts[$imp_id]['commonFields'][$idx_name]
                ."</td><td>"
                .$def_dts[$imp_id]['commonFields'][$idx_ccode]
                ."</td><td>$trg_id</td><td>"
                .$trg_detailtypes['typedefs'][$trg_id]['commonFields'][$idx_name]."</td></tr>";
            }
            
       }

        //TERMS TYPES
        $def_terms = $this->source_defs['terms'];
        $idx_name  = $def_terms['fieldNamesToIndex']['trm_Label'];
        $idx_ccode = $def_terms['fieldNamesToIndex']["trm_ConceptID"];

        foreach ($this->terms_correspondence as $imp_id=>$trg_id){
            if(@$this->terms_correspondence_existed[$imp_id]) continue;

            if(@$def_terms['termsByDomainLookup']['enum'][$imp_id]){
                $domain = 'enum';
            }else{
                $domain = 'relation';
            }

            if($trg_terms==null){
                $trg_terms = dbs_GetTerms($this->system);
            }

            $sTerms = $sTerms . "<tr><td>$imp_id</td><td>".$def_terms['termsByDomainLookup'][$domain][$imp_id][$idx_name]
            ."</td><td>"
            .$def_terms['termsByDomainLookup'][$domain][$imp_id][$idx_ccode]
            ."</td><td>$trg_id</td><td>"
            .$trg_terms['termsByDomainLookup'][$domain][$trg_id][$idx_name]."</td></tr>";
        }
        
        return array("data"=>array('rectypes'=>$trg_rectypes,'detailtypes'=>$trg_detailtypes,'terms'=>$trg_terms),
            "report"=>array('rectypes'=>$sRectypes,'detailtypes'=>$sFields,'terms'=>$sTerms) );
    }    
}
?>
