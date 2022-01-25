<?php
/**
* dbsImport.php - import definitions from other database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
require_once (dirname(__FILE__).'/../controller/entityScrudSrv.php');
require_once('dbsTerms.php');

require_once(dirname(__FILE__).'/../utilities/utils_file.php');
require_once(dirname(__FILE__).'/../../admin/structure/saveStructureLib.php');

define('_DBG', false); //debug log output

class DbsImport {

    private $system = null;

    // definition to be imported
    private $imp_recordtypes; //ids in source database
    private $rectypes_correspondence; //source->target id
    
    private $imp_fieldtypes;
    private $fields_correspondence;
        
    private $imp_terms;  //ids of source vocabularies to be imported
    private $terms_correspondence;
    private $vcg_correspondence; //vocab group src->target ids

    private $source_db_reg_id = 0;
            
    private $source_defs = null;
    private $target_defs = null;

    private $targetTerms = null;
    private $sourceTerms = null; //ref to DbsTerms
    private $sourceIconURL = null; //url to copy rectype icons
    private $source_db_name;

    //report data
    private $rectypes_upddated;
    private $rectypes_added;
    
    private $broken_terms;
    private $broken_terms_reason;
    
    
    //  $data = 
    function __construct( $system ) {
        $this->system = $system;
    }

    /*
    // getter
    //    
    public function getCorrespondences(){
        return array('rectypes'=>$this->rectypes_correspondence,
                     'fields'=>$this->fields_correspondence,
                     'terms'=>$this->terms_correspondence);
    }
    */
    
    //
    // getter
    //    
    public function getDefinitions($type='target'){
        if($type=='target'){
            return $this->target_defs;
        }else{
            return $this->source_defs;   
        }
    }
    
    
// 1. get database url
// 2. get definitions from remote database
// 3. Find what defintions will be imported
//        for rectypes perfrom deep check for field structure and compare terms

// 4. With list of all record types, build a list of all the base field types:
// 5. Perform database action - add rectypes, structure, fields and terms into our database
    
    /**
    * Finds all defintions to be imported
    * 
    * @param mixed $data
    * 
    * array(
        defType - rectype,detailtype,term
        databaseID - database where to search definition if not defined take db from conceptCode
        conceptCode - unique identifier (optional if definitionID is set)
        
        definitionID  - id in source database OR array of ids   
        
      )
    * @return mixed
    */
    public function doPrepare( $data ){
        
$time_debug = microtime(true);
$time_debug2 = $time_debug;
        
        $entityTypeToBeImported = $data['defType']; //rt|rectype,field|dt|detailtype,term
        
        $allowed_types = array('rectype','detailtype','term');
        
        if(!in_array($entityTypeToBeImported, $allowed_types)){
            $this->system->addError(HEURIST_INVALID_REQUEST, $entityTypeToBeImported." is not allowed type to be import");
            return false;
        }
        
        $db_reg_id = 0;
        $local_ids = array();  //local id of defintion to be imported
        $cCode = null;
        
        if(@$data['databaseID']>0){  //source database id
            $db_reg_id = $data['databaseID'];
        }
        if(@$data['definitionID']){  //id or concept code in source database
            $local_ids = $data['definitionID'];
            if(!is_array($local_ids)) $local_ids = array($local_ids);
        }
            
        if(@$data['conceptCode']){  //take db id and def id from concept code
            
            $cCode = $data['conceptCode'];
            
            if(is_array($cCode)){
                if(count($cCode)>2){
                    $db_reg_id = $cCode[2];
                    $cCode = $cCode[0].'-'.$cCode[1];
                }else{
                    $cCode = implode('-',$cCode);    
                }
            }            
            list($db_id, $ent_id) = explode('-', $cCode);

            if(!(ctype_digit($db_id) && $db_id>0 && ctype_digit($ent_id) && $ent_id>0)){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Concept code ($cCode) has wrong format - should be two numbers separated by dash");
                return false;
            }
            if(!($db_reg_id>0)){
                $db_reg_id = $db_id; //take source database from concept code
            }
        }
        
        if (!($db_reg_id>0)) {
            //@todo - check missed definitions
            //return true;
            $this->system->addError(HEURIST_ERROR, "Not possible to determine an origin database id (source of import)");
            return false;
        }
        if(!(count($local_ids)>0 || $cCode)){
            $this->system->addError(HEURIST_ERROR, "Neither concept code nor local id is defined");
            return false;
        }
        
        $this->source_db_reg_id = $db_reg_id;

        // 1. get database url by database id
        $database_url = $this->_getDatabaseURL($db_reg_id);        
        

if(_DBG) error_log('get db url '.(microtime(true)-$time_debug));        
$time_debug = microtime(true);        
        
        if(!$database_url){
            return false; //see $system->getError
        }        
        
        // 2. get definitions from remote database
        $this->source_defs  = $this->_getDatabaseDefinitions($database_url, $db_reg_id, ($entityTypeToBeImported=='term'));
        if (!$this->source_defs) {
            return false; //see $system->getError
        }

/* We require at least version 1.2 for database defintion */
        if(false && @$this->source_defs['terms']){
            if(!@$this->source_defs['terms']['groups'] || 
                !(@$this->source_defs['terms']['fieldNamesToIndex']['trm_VocabularyGroupID']>0)){
                    
                $this->system->addError(HEURIST_ERROR, 'Database registration URL '.$database_url.' returns old version '
                .'(DB version 1.2, you are using version 1.3) of database definitions. '
                .'Please ask the system administrator (<email address*>) to upgrade the system.');
                return false;
            }
        }
       

if(_DBG) error_log('get src defs '.(microtime(true)-$time_debug));        
$time_debug = microtime(true);        
        
        
        //find source id by conceptcodes or verify local_ids     
        $def_ids = array();
        $wrong_id = null;
        $missed_name = null;
        
        $this->_createTrmLinks(); //create virtual trm_Links if source db is 1.2

        $this->sourceTerms = new DbsTerms(null, $this->source_defs['terms']);
        if($entityTypeToBeImported=='term'){
            if(count($local_ids)>0){
                foreach($local_ids as $local_id){ 
                    $rt = $this->sourceTerms->getTerm($local_id);        
                    if($rt!=null){
                        $def_ids[] = $local_id;
                    }else{
                        $wrong_id = 'id#'.$local_id;
                        break;
                    }
                }
            }else{
                $def_ids[] = $this->sourceTerms->findTermByConceptCode($cCode); 
            }
        }else{
            
            if(count($local_ids)>0){
                foreach($local_ids as $local_id){ //$local_id either id in source db or concept code

                     if($entityTypeToBeImported=='rt' || $entityTypeToBeImported=='rectypes' || $entityTypeToBeImported=='rectype'){
                        $missed_name = @$data['rectypes'][$local_id]['name'];
                     }
                        
                     $found_local_id = $this->_getLocalCode($entityTypeToBeImported, $this->source_defs, $local_id);
                     if($found_local_id){
                         $def_ids[] = $found_local_id;  
                     }else{
                        $wrong_id = 'id#'.$local_id;
                        if($missed_name){
                            $wrong_id = $wrong_id.' "'.$missed_name.'"';
                        }
                        break;
                     } 
                }       
                
            }else{
                //get local id in source db by concept code
                $def_ids[] = $this->_getLocalCode($entityTypeToBeImported, $this->source_defs, $cCode); 
            }
        }
        
        if (count($def_ids)==0 || $wrong_id>0) { //definition not found in source database
            $smsg = ($wrong_id!=null) ?$wrong_id :'concept code '.$cCode;
            $this->system->addError(HEURIST_ERROR, 'Unable to get '.$entityTypeToBeImported. ' definition with '.$smsg.' in database #'.$db_reg_id);
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
        $this->vcg_correspondence = array();
        //$terms_correspondence_existed = array();
        $this->rectypes_upddated  = array();
        $this->rectypes_added  = array();

        
        //target(local) definitions
        $this->target_defs = array();
        if($entityTypeToBeImported!='term'){
            $this->target_defs['rectypes'] = dbs_GetRectypeStructures($this->system, null, 2);
            $this->target_defs['detailtypes'] = dbs_GetDetailTypes($this->system, null, 2);
        }

        $this->target_defs['terms'] = dbs_GetTerms($this->system);
        $this->targetTerms = new DbsTerms($this->system, $this->target_defs['terms']);

    
        // 3. Find what defintions will be imported
        if($entityTypeToBeImported=='term'){
            
            foreach($def_ids as $def_id){
                $this->_getTopMostVocabulary($def_id, 'enum');
                //if(count($this->imp_terms['enum'])==0)
                $this->_getTopMostVocabulary($def_id, 'relation');
            }
            
        }else{
            
            if($entityTypeToBeImported=='rectype'){
                //find record types
                foreach($def_ids as $def_id){
                    $this->_findDependentRecordTypes($def_id, null, 0);
                }
                
                if(count($this->imp_recordtypes)==0 && count($this->imp_fieldtypes)==0 && count($this->imp_terms)==0){
                    $this->system->addError(HEURIST_NOT_FOUND, 'No one entity to be imported found');
                    return false;
                }
        
                
            } else if($entityTypeToBeImported=='detailtype'){
                
                foreach($def_ids as $def_id){
                    $this->_findDependentRecordTypesByFieldId($def_id);
                }
                
                if(count($this->imp_fieldtypes)==0 && count($this->imp_terms)==0){
                    $this->system->addError(HEURIST_NOT_FOUND, 'No one field or vocabulary to be imported found');
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

                        //Get vocabulary for all terms used
                        $dt_Type = $def_dts[$ftId]['commonFields'][$idx_type];
                        if($dt_Type == "enum" || $dt_Type == "relmarker"){ 
                            //get topmost vocabulary
                            $this->_getTopMostVocabulary($def_dts[$ftId]['commonFields'][$idx_terms], $dt_Type);
                        }
                        
                        if($local_ftId>0){ //already exists in target
                            $this->fields_correspondence[$ftId] = $local_ftId;
                            continue; //field with the same concept code is already in database
                        }

                        //there is no such field in target - it must be imported
                        array_push($this->imp_fieldtypes, $ftId);
                        
                    }
                }
            }//for
            
            //5. some field types may not belong to recordtypes, they should be imported too
            // {id: '1', name: 'Titre', code: '2-1'}
            $all_fieldtypes = @$data['fieldtypes'];//[$local_id]['name']  
            if($all_fieldtypes && count($all_fieldtypes)>0){
                foreach ($all_fieldtypes as $ftId => $field){
                    if(!(@$this->fields_correspondence[$ftId] || in_array($ftId, $this->imp_fieldtypes) )){

                            $ccode = $def_dts[$ftId]['commonFields'][$idx_ccode];
                            $local_ftId = $this->_getLocalCode('detailtype', $this->target_defs, $ccode);

                            if($local_ftId>0){ //already exists in target
                                $this->fields_correspondence[$ftId] = $local_ftId;
                                continue; //field with the same concept code is already in database
                            }
                            //there is no such field in target - it must be imported
                            array_push($this->imp_fieldtypes, $ftId);
                    }   
                }
            }
            
        }
     
if(_DBG) error_log('Preparation '.(microtime(true)-$time_debug2));        
     
        return true;   
    }
    
    private function error_exit2($msg){

        $this->system->addError(HEURIST_ERROR, $msg);
        
        $mysqli = $this->system->get_mysqli();
        $mysqli->rollback();
        $mysqli->close();
        
    }

    //--------------------------------------------------------------------------
    //
    // Perform database action - add rectypes, structure, fields and terms into our database
    //
    public function doImport( ){
        
        global $mysqli;
        $mysqli = $this->system->get_mysqli();
        $mysqli->autocommit(FALSE);

$time_debug = microtime(true);
$time_debug2 = $time_debug;

        $this->broken_terms = array();
        $this->broken_terms_reason = array();
        
        // I. Add Terms (whole vocabulary)
        $stub = array();//stub for $all_terms_in_vocab
        if(! ($this->_importVocabulary(null, "enum", $stub) && 
              $this->_importVocabulary(null, "relation", $stub)) ){

            $mysqli->rollback();
            $mysqli->close();
            return false;                
        }
        
        if(count($this->imp_recordtypes)==0 && count($this->imp_fieldtypes)==0){
            $mysqli->commit(); 
            return true;
        }
        
        
        
if(_DBG) error_log('Terms '.(microtime(true)-$time_debug));        
$time_debug = microtime(true);        
        
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
        if(is_numeric($idx) && mb_strtolower(trim($group['name']))== mb_strtolower(trim($grp_name))){
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
$idx_origin_name  = $def_rts['commonNamesToIndex']['rty_NameInOriginatingDB'];
$idx_ccode       = $def_rts['commonNamesToIndex']['rty_ConceptID'];
$idx_titlemask   = $def_rts['commonNamesToIndex']['rty_TitleMask'];
$idx_titlemask_canonical = $def_rts['commonNamesToIndex']['rty_CanonicalTitleMask'];

foreach ($this->imp_recordtypes as $rtyID){

    $def_rectype = $def_rts[$rtyID]['commonFields'];

    $new_rtyID = @$this->rectypes_correspondence[$rtyID];
    if($new_rtyID>0){ //already  exists - update fields only - add missed fields
        $this->rectypes_upddated[] = $new_rtyID;
    }else{
    
        //replace group id with local one
        $grp_id = $def_rectype[$idx_rt_grp];
        $def_rectype[$idx_rt_grp] = $group_rt_ids[$grp_id];
        

        //disambiguate rectype name
        $def_rectype[$idx_name] = $this->doDisambiguate($def_rectype[$idx_name], $trg_rectypes['names']);

        //assign canonical to title mask (since in DB we store only rty_TitleMask)
        $def_rectype[$idx_titlemask] = $def_rectype[$idx_titlemask_canonical];

        //fill original ids if missed
        if(!$def_rectype[$idx_origin_dbid] || !$def_rectype[$idx_origin_id]){
            if($def_rectype[$idx_ccode]){
                $codes = explode("-",$def_rectype[$idx_ccode]);
                if($codes && count($codes)==2){
                    $def_rectype[$idx_origin_dbid] = $codes[0];
                    $def_rectype[$idx_origin_id] = $codes[1];
                }
            }else{
                $def_rectype[$idx_origin_dbid] = $this->source_db_reg_id;
                $def_rectype[$idx_origin_id] = $rtyID;
            }
        }
        if(!$def_rectype[$idx_origin_name]){
            $def_rectype[$idx_origin_name] = $def_rectype[$idx_name];
        }
        

        $res = createRectypes($columnNames, array("0"=>array("common"=>$def_rectype)), false, false, null);
    //if(_DBG) error_log('rt '.$rtyID);
        if(is_numeric($res)){

            $new_rtyID  = abs($res);
            $this->rectypes_correspondence[$rtyID] = $new_rtyID;
            $trg_rectypes['names'][$new_rtyID] = $def_rectype[$idx_name];
            $this->copyRectypeIcon($rtyID, $new_rtyID);

            $this->rectypes_added[] = $new_rtyID;
            
        }else{

            $this->error_exit2("Cannot add record type for id#".$recId.". ".$res);
            return false;

        }
        
    }
}//for
if(_DBG) error_log('Recordtypes '.(microtime(true)-$time_debug).'   '.count($this->imp_recordtypes));        
$time_debug = microtime(true);        


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
        if(is_numeric($idx) && mb_strtolower(trim($group['name']))== mb_strtolower(trim($grp_name))){
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
$idx_origin_name   = $def_dts['fieldNamesToIndex']['dty_NameInOriginatingDB'];
$idx_ccode       = $def_dts['fieldNamesToIndex']['dty_ConceptID'];


foreach ($this->imp_fieldtypes as $ftId){

    $def_field = $def_dts[$ftId]['commonFields'];

    //replace group id
    $grp_id = $def_field[$idx_dt_grp];
    $def_field[$idx_dt_grp] = $group_ft_ids[$grp_id];

    //disambiguate field name
    $def_field[$idx_name] = $this->doDisambiguate($def_field[$idx_name], $trg_detailtypes['names']);
    
    if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype" || $def_field[$idx_type] == "relmarker"){
        //change terms ids for enum and reltypes
        $def_field[$idx_terms_tree] = $this->replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
        $def_field[$idx_terms_disabled] = $this->replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
    }
    if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
        //change record ids for pointers
        $def_field[$idx_constraints] = $this->replaceRectypeIds(@$def_field[$idx_constraints]);
    }

    //fill original ids if missed
    if(!$def_field[$idx_origin_dbid] || !$def_field[$idx_origin_id]){
        if($def_field[$idx_ccode]){
            $codes = explode("-",$def_field[$idx_ccode]);
            if($codes && count($codes)==2){
                $def_field[$idx_origin_dbid] = $codes[0];
                $def_field[$idx_origin_id] = $codes[1];
            }
        }else{
                $def_field[$idx_origin_dbid] = $this->source_db_reg_id;
                $def_field[$idx_origin_id] = $ftId;
        }
    }
    
    
    if(!$def_field[$idx_origin_name]){
        $def_field[$idx_origin_name] = $def_field[$idx_name];
    }
    
    array_shift($def_field); //remove dty_ID
    $res = createDetailTypes($columnNames, array("common"=>$def_field));

    if(is_numeric($res)){
        $this->fields_correspondence[$ftId] = abs($res);
        $trg_detailtypes['names'][abs($res)] = $def_field[$idx_name-1]; //new name
        
    }else{
        //$ftId
        $this->error_exit2('Can\'t add base field "'.$def_field[$idx_name-1]
            .'" (global code '.$def_field[$idx_ccode-1] 
            .') defined in  template. This may be due to a name conflict caused by unrecognised accents on similar names. '
            .'<br><br>Please '.CONTACT_HEURIST_TEAM.' for advice'
            .'<br><br>MySQL message:'.$res);
        return false;
    }
}
if(_DBG) error_log('Fields '.(microtime(true)-$time_debug).'   '.count($this->imp_fieldtypes));        
$time_debug = microtime(true);        

// ------------------------------------------------------------------------------------------------

// VI. Add/update record structures
$trg_def_rts = $this->target_defs['rectypes']['typedefs'];
                
$idx_type           = $def_rts['dtFieldNamesToIndex']['dty_Type'];
$idx_terms_tree     = $def_rts['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];  //value is the same as
$idx_terms_disabled = $def_rts['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
$idx_constraints    = $def_rts['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
$idx_defaultvalue   = $def_rts['dtFieldNamesToIndex']['rst_DefaultValue'];

$dtFieldNames = $def_rts['dtFieldNames'];

foreach ($this->imp_recordtypes as $rtyID){
    
    $target_RtyID = @$this->rectypes_correspondence[$rtyID];

    if($target_RtyID>0){
        
        if(@$trg_def_rts[$target_RtyID]['dtFields']){//this record type is already in destination need to sync structure
            $fields = $trg_def_rts[$target_RtyID]['dtFields'];
        }else{
            $fields = array();    
        }
        
        //assign values from source
        foreach ($def_rts[$rtyID]['dtFields'] as $ftId => $def_field){
            if(!@$fields[ $this->fields_correspondence[$ftId] ]){
                $fields[ $this->fields_correspondence[$ftId] ] = $def_field;
            }
        }
        //clear term trees and resource constraints; assign default value for terms
        foreach ($fields as $ftId => $def_field){
            
            
            if($def_field[$idx_type] == "enum"
                || $def_field[$idx_type] == "relationtype" 
                || $def_field[$idx_type] == "relmarker")
            {
                //change terms ids for enum and reltypes
                $def_field[$idx_defaultvalue] = $this->replaceTermIds(@$def_field[$idx_defaultvalue], $def_field[$idx_type]);
            }
            
            $def_field[$idx_terms_tree] = '';
            $def_field[$idx_terms_disabled] = '';
            $def_field[$idx_constraints] = '';
            $fields[$ftId] = $def_field;
        }
        

        $ret = updateRecStructure( $dtFieldNames , $target_RtyID, array("dtFields"=>$fields));
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

            $error = $this->system->getError();
            if(@$error['message']){
                $ret = substr($error['message'],0,100);
            }
            
            $this->error_exit2("Can't update record type structure rectype#".$this->rectypes_correspondence[$rtyID].". ".$ret);
            return false;

        }
    }
}
if(_DBG) error_log('Structures '.(microtime(true)-$time_debug));        
$time_debug = microtime(true);        

// ------------------------------------------------------------------------------------------------

// VII. Update titlemasks with new ids

//$mysqli->commit();

TitleMask::set_fields_correspondence($this->fields_correspondence);

foreach ($this->imp_recordtypes as $rtyID){
    if(@$this->rectypes_correspondence[$rtyID]){

        $mask = $def_rts[$rtyID]['commonFields'][$idx_titlemask_canonical];
//echo $mask;
        // note we use special global array $this->fields_correspondence - for proper conversion of remote id to concept code
        $res = updateTitleMask( $this->rectypes_correspondence[$rtyID], $mask); //see saveStructureLib
        if(!is_numeric($res)){
            //$this->error_exit2($res);
            $mysqli = $this->system->get_mysqli();
            $mysqli->rollback();
            $mysqli->close();
            return true;
        }
    }
}

TitleMask::set_fields_correspondence(null);
if(_DBG) error_log('Total '.(microtime(true)-$time_debug2));           

$mysqli->commit();        
            
            return true;
    }
    
    
    //
    //  fill "fake" source_defs for record types on base of mappping
    //  it is required for $this->getTargetIdBySourceId
    //
    public function doMapping($mapping){
        
        $defType = 'rectypes';
        $this->source_defs['rectypes'] = array();
        foreach($mapping as $sourceID=>$fields){
            if($sourceID>0){
                $this->source_defs['rectypes'][$sourceID] = $fields['rty_ID'];
                
            }
        }
        //$defType = 'detailtypes';
        
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

        // Feb 2018 - catch references to old http server and convert to new server (https://heuristplus ... from 2019)
        if(strpos($database_url, 'http://heurist.sydney.edu.au/')===0){
            $remote_url = HEURIST_MAIN_SERVER.'/';
        }


        if(!$remote_dbname || !$remote_url){
            $this->system->addError(HEURIST_ERROR, 
                "Heurist Master Index returns incorrect data for registered database # ".$database_id.
                " The page may contain an invalid database reference");
            return false;
        }
        
        $message = '<p>Unable to contact the selected source database (ID # '.$database_id
            .'). This might indicate one of the following:</p>' 
            .'<ol><li>the database is no longer online;</li>'
            .'<li>the registration in the Heurist master index is missing or points to the wrong URL '
            .$remote_url
            .'<br>(check registration in Heurist Master Index);</li>'
            .((strpos($remote_url, HEURIST_SERVER_URL)===0)
                ?'<li>a sql server error in contacting the database;</li>'
                :'<li>a proxy error in contacting the database;</li>')
            .'<li>network timeout.</li></ol> '
            .'<p>If you cannot determine the problem, please '.CONTACT_HEURIST_TEAM.' with the URL to the target database</p>';
        
        
        if(strpos($remote_url, HEURIST_SERVER_URL)===0){ //same server

          $defs = array();  
          
          $system2 = new System();
          if(!$system2->init($remote_dbname, true, false)){ //init without paths and consts
            $this->system->addError(HEURIST_ERROR, $message);
            return false;          
          }
          
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
                $this->system->addError(HEURIST_ERROR, $message);
                return false;
           }
           $defs = $defs['data'];
        }
        if (!($defs['terms']  &&  ($only_terms || ($defs['rectypes'] && $defs['detailtypes'])))) {
            $this->system->addError(HEURIST_ERROR, "Structure definitions read from source database # $database_id are invalid. Please "
                .CONTACT_HEURIST_TEAM);
            return false;
        }
        
        $this->sourceIconURL = $remote_url.'hsapi/controller/fileGet.php?db='.$remote_dbname.'&id=';
        $this->source_db_name = $remote_dbname;
        
        return $defs; 
    }
    
    //
    // verifies that definition with given ID exists
    //
    private function _checkLocalCode($defType, $database_defs, $localID){
        
        $defs = null;
        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){
            $defs = @$database_defs['rectypes']['typedefs'][$localID];
        }else if($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){
            $defs = @$database_defs['detailtypes']['typedefs'][$localID];
        }
        
        return ($defs!=null)?$localID:false;
    }

    // 
    // finds target id by source id via concept code
    // get concept code in source - find local in target
    //
    public function getTargetIdBySourceId($defType, $source_id){
        
        $iscc = (strpos($source_id,'-')!==false);
        
        //get concept code in source
        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){
            
            $defType = 'rectypes';
            
            if(@$this->source_defs[$defType]['typedefs']){
                $defs = $this->source_defs[$defType]['typedefs'];
                //get concept code
                $fieldName = 'rty_ConceptID';
                $idx_ccode = intval($defs['commonNamesToIndex'][$fieldName]);
                $conceptCode = $iscc ?$source_id :@$defs[$source_id]['commonFields'][$idx_ccode];
            }else{
                return $defs = $this->source_defs[$defType][$source_id]; //via mapping
            }
            
        }else if($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){

            $defType = 'detailtypes';
            
            if(@$this->source_defs[$defType]['typedefs']){
                $defs = $this->source_defs[$defType]['typedefs'];
                $fieldName = 'dty_ConceptID';
                $idx_ccode = intval($defs['fieldNamesToIndex'][$fieldName]);
                $conceptCode = $iscc ?$source_id :@$defs[$source_id]['commonFields'][$idx_ccode];

            }else{
                return $defs = $this->source_defs[$defType][$source_id]; //via mapping
            }

            
        }else if($defType=='enum' || $defType=='relationtype'){
            
            //$defs = $this->source_defs['terms']['termsByDomainLookup'][($defType=='enum'?'enum':'relation')];
            
            if($iscc){
                 $conceptCode = $source_id;   
            }else{
                 $fieldName = 'trm_ConceptID';
                 $idx_ccode = intval($this->source_defs['terms']['fieldNamesToIndex'][$fieldName]);
                 $term = $this->sourceTerms->getTerm($source_id, $defType);
                 
                 $conceptCode = @$term[$idx_ccode];
            }
            //$conceptCode = $iscc ?$source_id :@$defs[$source_id][$idx_ccode];
            
        }
        
        if($conceptCode){
            return DbsImport::getLocalCode($defType, $this->target_defs, $conceptCode, false);    
        }else{
            return 0;
        }
    }
    //
    // get local code by concept code
    //
    private function _getLocalCode($defType, $database_defs, $conceptCode, $sall=false){
        return DbsImport::getLocalCode($defType, $database_defs, $conceptCode, $sall);
    }

    //    
    // get local code by concept code or verifies that element with given code/localid exists
    // 
    public static function getLocalCode($defType, $database_defs, $conceptCode, $sall=false){
        $res = array();
        $defs2 = null;
        
        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){
            $defType = 'rectypes';
            $defs = $database_defs[$defType]['typedefs'];

            $fieldName = 'rty_ConceptID';
            $idx_ccode = intval($defs['commonNamesToIndex'][$fieldName]);
            
        }else if($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){
            $defType = 'detailtypes';
            $defs = $database_defs[$defType]['typedefs'];

            $fieldName = 'dty_ConceptID';
            $idx_ccode = intval($defs['fieldNamesToIndex'][$fieldName]);
            
        }else if($defType=='enum' || $defType=='relationtype'){
            
//            $defs = $database_defs['terms']['termsByDomainLookup'][($defType=='enum'?'enum':'relation')];

            $fieldName = 'trm_ConceptID';
            $idx_ccode = intval($database_defs['terms']['fieldNamesToIndex'][$fieldName]);
            
            $defs = $database_defs['terms']['termsByDomainLookup']['enum'];
            $defs2 = $database_defs['terms']['termsByDomainLookup']['relation'];
            
        }
        
        $local_id = 0;
        
        if(strpos($conceptCode,'-')!==false){
            list($db, $id) = explode('-', $conceptCode); 
            if(ctype_digit($db) && $db==0 && ctype_digit($id) && $id>0){
                 $local_id = $id;          
            }
        }else{
            $local_id = $conceptCode;
        }
    
        if(is_numeric($local_id) && $local_id>0){ //this is local already
        
            if(@$defs[$local_id]){
                return $local_id;
            }else{
                return null; //not found
            }
            
        }else{
            //find by concept code

            foreach ($defs as $id => $def) {
                if(is_numeric($id)){
                    
                    if($defType=='enum' || $defType=='relationtype'){
                        $is_equal = $def[$idx_ccode]==$conceptCode;
                    }else{
                        $is_equal = $def['commonFields'][$idx_ccode]==$conceptCode;
                    }
                    
                    if($is_equal){
                        if($sall){
                            array_push($res, $id);
                        }else{
                            return $id;
                        }
                    }
                    
                }
            }
            if($defs2){
                foreach ($defs2 as $id => $def) {
                    if(is_numeric($id) && $def[$idx_ccode]==$conceptCode){
                        return $id;
                    }
                }
            }
            
        }
        
        return ($sall)?$res:null;
        
    }
    
    // 
    // Find vocabularies to be imported
    // It fills global $this->imp_terms with the top most vocabulary
    //
    // $terms_ids - list of terms (json or csv) from field definition
    // $this->imp_terms - list of source vocabulary ids to be imported
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

    // @todo move to dbsRectypes
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
    
    // @todo move to dbsRectypes
    //
    // search source defintions
    // find all dependend record types in pointer constraints
    // 1) returns rt tree 2) fills $this->imp_recordtypes, $this->rectypes_correspondence
    //
    private function _findDependentRecordTypes($rectype_id, $depth){
        
        $excludeDuplication = false;
        
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
        if($local_recid>0){  //already exist in destination
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
        //loop all fields check constraint for pointers and relmarkers
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
    
    /*
     import entire vocabulary (including all children)
     
     $term_id - term to be imported, if not defined use $this->imp_terms to import all missing vocabs
     $domain - enum or relation
     $all_terms_in_vocab - target: all terms by vocabulary (plain array)
     $children - array of term ids (source)
     $parent_id - parent term id (target)
     $same_level_labels - labels and codes on the same level to disambiguate
     
    */ 
    private function _importVocabulary($term_id, $domain, &$all_terms_in_vocab, $children=null, $parent_id=null, $same_level_labels=null){
        

        if($term_id==null){
            //loop through all this->imp_terms
            
            //fills $this->vcg_correspondence
            $this->_importVocabularyGroups($this->imp_terms[$domain]);
            
            //top level import vocabularies
            foreach($this->imp_terms[$domain] as $term_id){
                $children = @$this->source_defs['terms']['trm_Links'] && @$this->source_defs['terms']['trm_Links'][$term_id]
                                ?@$this->source_defs['terms']['trm_Links'][$term_id]   //new structure with terms by reference
                                :@$this->source_defs['terms']['treesByDomain'][$domain][$term_id];

                $all_terms_in_vocab = array(); //reset
                $res = $this->_importVocabulary($term_id, $domain, $all_terms_in_vocab, $children, null, null);                  
                if(!$res) return false;
            }
            
            return true;
        }else{

            $terms = $this->source_defs['terms'];
            
            if(!(@$terms['fieldNamesToIndex']['trm_NameInOriginatingDB']>0)){
                $terms['fieldNamesToIndex']['trm_NameInOriginatingDB'] = 19;
                $terms['commonFieldNames'][19] = 'trm_NameInOriginatingDB';
            }

            $columnNames = $terms['commonFieldNames'];
            $idx_ccode = intval($terms['fieldNamesToIndex']["trm_ConceptID"]);
            $idx_parentid = intval($terms['fieldNamesToIndex']["trm_ParentTermID"]);
            $idx_inverseid = intval($terms['fieldNamesToIndex']["trm_InverseTermID"]);
            $idx_label = intval($terms['fieldNamesToIndex']["trm_Label"]);
            $idx_code  = intval($terms['fieldNamesToIndex']["trm_Code"]);
            $idx_origin_dbid  = intval($terms['fieldNamesToIndex']["trm_OriginatingDBID"]);
            $idx_origin_id  = intval($terms['fieldNamesToIndex']["trm_IDInOriginatingDB"]);
            $idx_origin_name   = $terms['fieldNamesToIndex']['trm_NameInOriginatingDB'];
            
            $idx_vocab_group_id  = intval($terms['fieldNamesToIndex']["trm_VocabularyGroupID"]);
/*            
            if($term_id==5892){
                error_log('!!!!!');
            }
*/
            //search both domains
            $term_import = $this->sourceTerms->getTerm($term_id, $domain);
            //$term_import = $terms['termsByDomainLookup'][$domain][$term_id]; //6256 returns wrong!!

            if(!$term_import[$idx_ccode]){
                if($term_import[$idx_origin_dbid]>0 && $term_import[$idx_origin_id]>0){
                    
                }else if($term_id<999999){
                    $term_import[$idx_origin_dbid] = $this->source_db_reg_id;
                    $term_import[$idx_origin_id] = $term_id;
                }                
                $term_import[$idx_ccode] = $term_import[$idx_origin_dbid].'-'.$term_import[$idx_origin_id];
            }
            
            //find term by concept code among local terms
            $new_term_id = $this->targetTerms->findTermByConceptCode($term_import[$idx_ccode], $domain);
            
/*            
if($term_id==11 || $term_id==518 || $term_id==497){
    error_log('!!!!');
}
*/
            
            if($new_term_id){
                //this term aready exists in target - add it as reference to this vocabulary
                if($parent_id>0){ //this is not vocabulary
                    
                    //check that this term is already in vocab
                    if(!in_array($new_term_id, $all_terms_in_vocab)){
                        
                        //for second level references: term may refer to real parent
                        if($this->targetTerms->isTermLinked($parent_id, $new_term_id)){
                            array_push($all_terms_in_vocab, $new_term_id);
                        }else{
                            //add as reference
                            $res = addTermReference($parent_id, $new_term_id, $this->system->get_mysqli()); //see saveStructureLib
                            if($res!==false){
                                array_push($all_terms_in_vocab, $new_term_id);
                                $this->targetTerms->addNewTermRef($parent_id, $new_term_id); //add in memory
                                
                            }else{
                                //$this->system->addError(HEURIST_ERROR, "Can't add reference ".$term_id.' to '.$parent_id);
                                return false;
                            }
                        }
                    }
                }else{
                    //find vocabuary in target and all its terms
                    $all_terms_in_vocab = $this->targetTerms->treeData($new_term_id, 3);
                }
                                
                $new_term_id = -$new_term_id;
            }else{
                //if not found add new term

                //change trm_InverseTermID, trm_ParentTermID  to new id from target
                $term_import[$idx_parentid] = $parent_id;//@$this->terms_correspondence[$term_import[$idx_parentid]];
                $term_import[$idx_inverseid] = @$this->terms_correspondence[$term_import[$idx_inverseid]]; //@todo - after all terms addition?
                
                //get level - all terms of the same level - to search same name and codes
                //1317-3790 Org type selection
                //verify that code and label is unique for the same vocabulary in target(local) db
                if($parent_id==null){
                    //for vocabularies
                    $term_import[$idx_code] = $this->targetTerms->doDisambiguateTerms($term_import[$idx_code], 
                                                            null, $domain, $idx_code);
                    $term_import[$idx_label] = $this->targetTerms->doDisambiguateTerms($term_import[$idx_label],
                                                            null, $domain, $idx_label);
                    
                    if(@$term_import[$idx_vocab_group_id]>0 && @$this->vcg_correspondence[$term_import[$idx_vocab_group_id]]>0){
                        $term_import[$idx_vocab_group_id] = $this->vcg_correspondence[$term_import[$idx_vocab_group_id]];
                    }else{
                        //be default place into 1
                        $term_import[$idx_vocab_group_id] = 1;
                    }
                                                                                    
                                                            
                }else{
                    $term_import[$idx_code] = $this->targetTerms->doDisambiguateTerms2($term_import[$idx_code], 
                                                $same_level_labels['code']);
                    $term_import[$idx_label] = $this->targetTerms->doDisambiguateTerms2($term_import[$idx_label], 
                                                $same_level_labels['label']);
                    
                    $term_import[$idx_vocab_group_id] = 0;
                }
                
                // fill original ids (concept codes) if missed
                if(!$term_import[$idx_origin_dbid] || !$term_import[$idx_origin_id]){
                    if($term_import[$idx_ccode]){
                        $codes = explode("-",$term_import[$idx_ccode]);
                        if($codes && count($codes)==2){
                            $term_import[$idx_origin_dbid] = $codes[0];
                            $term_import[$idx_origin_id] = $codes[1];
                        }
                    }else{
                        $term_import[$idx_origin_dbid] = $this->source_db_reg_id;
                        $term_import[$idx_origin_id] = $term_id;
                    }
                }
                if(!@$term_import[$idx_origin_name]){
                    $term_import[$idx_origin_name] = $term_import[$idx_label];
                }
                
                $res = updateTerms($columnNames, null, $term_import, $this->system->get_mysqli()); //see saveStructureLib    
                
                if(is_numeric($res)){
                    $new_term_id = $res;

                    $this->targetTerms->addNewTerm($new_term_id, $term_import); //add in memory
                    
                    array_push($all_terms_in_vocab, $new_term_id);
                }else{
                    if($parent_id==null){
                        $this->system->addError(HEURIST_ERROR,
                        "Can't import vocabulary ".$term_id.' '.$res); //.print_r($term_import, true)."  ".$res);
                        return false;
                    }else{
                        //add to issue report - summary will be send to support email
                        array_push($this->broken_terms, $term_import); 
                        array_push($this->broken_terms_reason, $res); 
                        return -1; // if this term has children they are ignored too
                    }
                }
            }

            //fill $terms_correspondence
            $this->terms_correspondence[$term_id] = abs($new_term_id);

            if($children){
                
                $target_parent_id = abs($new_term_id);
                
                //find same level codes and labels in target
                $lvl_src = $this->targetTerms->getSameLevelLabelsAndCodes($target_parent_id, $domain);
                
                foreach($children as $id){
                    
                    if(@$this->source_defs['terms']['trm_Links']){
                        $children2 = @$this->source_defs['terms']['trm_Links'][$id];
                    }else{
                        $children2 = @$children[$id];
                    }
                    
                    //($term_id, $domain, $children=null, $parent_id=null, $same_level_labels=null)
                    $new_id = $this->_importVocabulary($id, $domain, $all_terms_in_vocab, $children2, $target_parent_id, $lvl_src);
                    if($new_id>0){
                        //new term - add to codes and labels
                        $lvl_src['code'][] = $this->targetTerms->getTermCode($new_id);
                        $lvl_src['label'][] = $this->targetTerms->getTermLabel($new_id);
                    } else if($new_id<0){ 
                        //this term aready exists in target - add it as reference to this vocabulary
                    }else {
                        return false;
                    }
                    
                }
            }
            
            return $new_term_id;
        }
    }//_importVocabulary
    
    //
    // check vocabulry group by name and import if missed
    // for older databases all imported vocab will be placed to group "Import"
    //   
    private function _importVocabularyGroups($src_vocab_ids){

        $columnNames = array("vcg_Name","vcg_Domain","vcg_Order","vcg_Description");
        $idx_vcg_grp = @$this->source_defs['terms']['fieldNamesToIndex']['trm_VocabularyGroupID'];
        $idx_parent = $this->source_defs['terms']['fieldNamesToIndex']['trm_ParentTermID'];

        $mysqli = $this->system->get_mysqli();
        
        $is_old_db_version = false;
        if(!($idx_vcg_grp>0) || @$this->source_defs['terms']['groups']==null){
            //old version of source - add new field and set all groups to "Import"
            $is_old_db_version = true;
            
            if(!($idx_vcg_grp>0)){
                $idx_vcg_grp = 20;
                $this->source_defs['terms']['commonFieldNames'][$idx_vcg_grp] = 'trm_VocabularyGroupID';
                $this->source_defs['terms']['fieldNamesToIndex']['trm_VocabularyGroupID'] = $idx_vcg_grp;
            }
            
            $this->source_defs['terms']['groups'] = array(11=>
                array('vcg_ID'=>"11", 'vcg_Name'=>"Import", 'vcg_Domain'=>"enum", 
                'vcg_Order'=>"007", 'vcg_Description'=>"Imported vocabularies"));
        }
        
        foreach($src_vocab_ids as $term_id){
            
            $rt = $this->sourceTerms->getTerm($term_id);

            $parent_id = @$rt[$idx_parent];
            if($parent_id>0) continue; //this is not vocabulary
            
            if($is_old_db_version){
                $rt[$idx_vcg_grp] = 11;
            }

            $grp_id = @$rt[$idx_vcg_grp];

            if(!$grp_id){
                $this->error_exit2("Vocabulary Group ID is not defined for vocabulary #'".$term_id."'. in source database");
                return false;
            }

            $src_group=null;

            if(@$this->vcg_correspondence[$grp_id]){ //already found
                continue;
            }
            
            //find group in source by ID
            $src_group = @$this->source_defs['terms']['groups'][$grp_id];

            if($src_group==null){
                $this->error_exit2("Can't find vocabulary group #".$grp_id." for vocabulary #'".$term_id."'. in source database");
                return false;
            }
            $grp_name = @$src_group['vcg_Name'];
            if(!$grp_name){
                $this->error_exit2("Name of group is empty. Can't add group #".$grp_id." for vocabulary #'"
                    .$term_id."'. in source database");
                return false;
            }

            //get name and try to find in target by name
            $isNotFound = true;
            foreach ($this->target_defs['terms']['groups'] as $id=>$group){
                if(is_numeric($id) && mb_strtolower(trim($group['vcg_Name']))== mb_strtolower(trim($grp_name))){
                    $this->vcg_correspondence[$grp_id] = $id;
                    $isNotFound = false;
                    break;
                }
            }

            if($isNotFound){ //add new one
                
                $src_group['vcg_ID'] = -1;
                $new_grp_id = mysql__insertupdate($mysqli,'defVocabularyGroups','vcg',$src_group);

                if( is_numeric($new_grp_id) ){
                    $this->vcg_correspondence[$grp_id] = $new_grp_id;
                    $src_group['vcg_ID'] = $new_grp_id;
                    $this->target_defs['terms']['groups'][$new_grp_id] = $src_group;
                }else{
                    $this->error_exit2("Can't add vocabulary group '".$grp_name."'. ".@$res['error']);
                    return false;
                }
            }
        }
    }
    

    // @todo
    // Copy record type icon and thumbnail from source to destination database
    //
    private function copyRectypeIcon($source_RtyID, $target_RtyID, $thumb=""){

        //backward capabilities - rectype-icons        
        $targetPath = HEURIST_ICON_DIR.$thumb.$target_RtyID.'.png';

        //check if the same server with target
        if(strpos($this->sourceIconURL, HEURIST_SERVER_URL)===0){ 
            
            $filename = HEURIST_FILESTORE_ROOT.$this->source_db_name.'/rectype-icons/'.$thumb.$source_RtyID.'.png';
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
            $sourceURL = $this->sourceIconURL.$thumb.$source_RtyID.'.png';

            //print "<br>sourcce=".$sourceURL;
            //print "<br>path=".$targetPath;

            saveURLasFile($sourceURL, $targetPath); //see utils_file
        }

        //new  entity/defRecTypes/  --------------------------------------------
        $targetFolder = HEURIST_FILESTORE_DIR.'/entity/defRecTypes/';
        $targetPath = $targetFolder.($thumb==''?'/icon/':'/thumbnail/').$target_RtyID.'.png';
        
        //check if the same server with target
        if(strpos($this->sourceIconURL, HEURIST_SERVER_URL)===0){ 

            $filename = HEURIST_FILESTORE_ROOT.$this->source_db_name.
                    ($thumb==''?'/icon/':'/thumbnail/').$thumb.$source_RtyID.'.png';
            if(file_exists($filename)){
                if(file_exists($targetPath)){
                    unlink($targetPath);
                }
                copy($filename, $targetPath);
            }
        }else{
            $sourceURL = $this->sourceIconURL.$thumb.$source_RtyID.'.png';
            saveURLasFile($sourceURL, $targetPath); //see utils_file
        }

                
        //and thumbnail
        if($thumb==''){
            $this->copyRectypeIcon($source_RtyID, $target_RtyID, 'thumb/th_');
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
            if(strcasecmp($name, $name1)==0){
                $found++;
            }
        }

        if($found>0){
            $found++;
            $newvalue = $name.' '.$found;
        }
        
        do{
            $not_unique = false;
            foreach($entities as $id=>$name1){
                if(strcasecmp($newvalue, $name1)==0){
                    $found++;
                    $newvalue = $name.' '.$found;
                    $not_unique = true;
                    break;
                }
            }
        }while ($not_unique);

        return $newvalue;
    }

    
    //
    // replace rectype ids in constraint string to local id
    //
    private function replaceRectypeIds($constraints){
        
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
    public function replaceTermIds( $sterms, $domain ) {

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
    // it returns updated definitions (as json) and html snippets with report about imported definitions
    //    
    public function getReport($need_updated_defs=true){

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
                        $trg_rectypes = dbs_GetRectypeStructures($this->system, null, 0); //only names
                    }

                    $sRectypes = $sRectypes."<tr><td>$imp_id</td><td>".$def_rts[$imp_id]['commonFields'][$idx_name]
                    ."</td><td>"
                    .$def_rts[$imp_id]['commonFields'][$idx_ccode]
                    ."</td><td>$trg_id</td><td>"
                    .@$trg_rectypes['names'][$trg_id]
                    //.$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_name]
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
                    $trg_detailtypes = dbs_GetDetailTypes($this->system, null, 0); //only names
                }

                $sFields = $sFields."<tr><td>$imp_id</td><td>".$def_dts[$imp_id]['commonFields'][$idx_name]
                ."</td><td>"
                .$def_dts[$imp_id]['commonFields'][$idx_ccode]
                ."</td><td>$trg_id</td><td>"
                .@$trg_detailtypes['names'][$trg_id]."</td></tr>";
                //.$trg_detailtypes['typedefs'][$trg_id]['commonFields'][$idx_name]."</td></tr>";
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

        $resp =  array( 'report'=>array('rectypes'=>$sRectypes,'detailtypes'=>$sFields,'terms'=>$sTerms,
            'updated'=>$this->rectypes_upddated, 'added'=>$this->rectypes_added) );
            
        if(count($this->broken_terms_reason)>0){
            $resp['report']['broken_terms'] = $this->broken_terms;
            $resp['report']['broken_terms_reason'] = $this->broken_terms_reason;
        }

        if($need_updated_defs){
            //2021-06-15 we don't use old format for defintions anymore
            //$resp['defs'] = array('rectypes'=>$trg_rectypes,'detailtypes'=>$trg_detailtypes,'terms'=>$trg_terms);
            
            $data = $this->system->getCurrentUserAndSysInfo(true);
            $resp['defs']['sysinfo'] = $data['sysinfo'];

            $resp['defs']['entities'] = entityRefreshDefs($this->system, 'all', false);

        }
        return $resp;    
    }    

    //
    // helper function
    //
    private function __tree_to_links($tree, &$links){
        
        foreach($tree as $parent=>$children){
           $links[$parent] = array_keys($children);
           $this->__tree_to_links($children, $links); 
        }

    }
    
    //
    // create virtual trm_Links if it is missed for old version of source
    //
    private function _createTrmLinks(){
    
        if(@$this->source_defs['terms']['trm_Links']) return;
        
        //links for individual selections        
        //loop for details
        $det = $this->source_defs['detailtypes']['typedefs'];
        $idx_type = $det['fieldNamesToIndex']['dty_Type'];
        $idx_tree = $det['fieldNamesToIndex']['dty_JsonTermIDTree'];
        $idx_ccode = $det['fieldNamesToIndex']['dty_ConceptID'];
        
        $ids1 = array_keys($this->source_defs['terms']['termsByDomainLookup']['enum']);
        $ids2 = array_keys($this->source_defs['terms']['termsByDomainLookup']['relation']);
        
        $max_id = 999999;//max($ids1[count($ids1)-1], $ids2[count($ids2)-1]);
        //max(max(),
        //            max(array_keys($this->source_defs['terms']['termsByDomainLookup']['relation'])));
        
        foreach($det as $dty_ID=>$detail){
            
            if($dty_ID>0){
                $dty_Type = $detail['commonFields'][$idx_type];
                $dty_Tree = $detail['commonFields'][$idx_tree];
                
                if ($dty_Type=="enum" || $dty_Type=="relmarker"){
                    if($dty_Tree && is_numeric($dty_Tree)){
                        continue;
                    }

                    $dty_cCode = $detail['commonFields'][$idx_ccode];
                    
                    if($dty_cCode=='3-1079'){
                        $vocab_id = 6255;
                    }else if($dty_cCode=='3-1080'){
                        $vocab_id = 6256; 
                    }else if($dty_cCode=='3-1087'){
                        $vocab_id = 6257;
                    }else if($dty_cCode=='3-1088'){
                        $vocab_id = 6258;
                    }else{
                        $vocab_id = 0;
                    }
                    
                    //add virtaul vocabulary for individual selections
                    $domain = $dty_Type=='enum'?'enum':'relation';
                    $name = $detail['commonFields'][1].' - selection';
                    
                    if($vocab_id>0){
                        $orig_db_id = 2;
                    }else{
                        $orig_db_id = 0;
                        $vocab_id = $max_id+1;
                    }
                    $max_id = $max_id+1;
                    
                    $vocab = array($name, '0', '', 'open', $orig_db_id, $vocab_id, '0', '0', 
                            $domain, '0', '0', '0', '0', '2012-06-04 08:18:57', 
                            '0', '', '', '1', //group #1 by default
                            $name, $orig_db_id.'-'.$vocab_id, false);
                    
    /*                
    0: "trm_Label"
    1: "trm_InverseTermID"
    2: "trm_Description"
    3: "trm_Status"
    4: "trm_OriginatingDBID"
    5: "trm_IDInOriginatingDB"
    6: "trm_AddedByImport"
    7: "trm_IsLocalExtension"

    8: "trm_Domain"
    9: "trm_OntID"
    10: "trm_ChildCount"
    11: "trm_ParentTermID"
    12: "trm_Depth"
    13: "trm_Modified"
    
    14: "trm_LocallyModified"
    15: "trm_Code"
    16: "trm_SemanticReferenceURL"
    17: "trm_VocabularyGroupID"
    
    18: "trm_NameInOriginatingDB"
    19: "trm_ConceptID"
    20: "trm_HasImage"                
    */                
                    $this->source_defs['terms']['termsByDomainLookup'][$domain][$max_id] = $vocab;  //ERROR !!!!! assigned as ID
                    $this->source_defs['terms']['treesByDomain'][$domain][$max_id] = json_decode($dty_Tree, true);   
                    $this->source_defs['detailtypes']['typedefs'][$dty_ID]['commonFields'][$idx_tree] = $max_id;
                    
                }
            }
        }//for details

        
        $links = array();

        $tree = $this->source_defs['terms']['treesByDomain']['enum'];
        $this->__tree_to_links($tree, $links);
        
        $tree = $this->source_defs['terms']['treesByDomain']['relation'];
        $this->__tree_to_links($tree, $links);

        $this->source_defs['terms']['trm_Links'] = $links;
    }
    
    
}
?>
