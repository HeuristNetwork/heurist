<?php
/**
* helper class - to obtain access to heurist data from smarty report

The reason is our last changes in access to records. From now the query for smarty returns only list of record IDs. Consequently all relations and pointer fields contain record ID only. As a result the performance has been increased significantly.
Thus, we need to obtain all records data in code of report template. To achieve this goal we provide $heurist object. This object has 3 public methods

getRecord - returns a record by recID or reload record if record array is given as parameter
getRelatedRecords - returns an array of related record for given recID or record array
getLinkedRecords - returns array of linkedto and linkedfrom record IDs
getWootText  - returns text related with given record ID
*/

require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_recsearch.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_rel_details_temp.php');

require_once(dirname(__FILE__).'/../../hsapi/utilities/Temporal.php');
require_once (dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP
//require_once(dirname(__FILE__).'/../../records/woot/woot.php');

/*
public methods
   constant - returns value of heurist constants
   rty_id, dty_id, trm_id - returns local code for given concept code
   getRecord - returns full info for given record ID in heurist smarty format (including visibility for current user)
   getRelatedRecords
   getLinkedRecords

   getRecords - returns records ids for given query 
   getRecordsAggr - returns aggregation values
   
   _getTranslation - returns translation for given entity + id
   
*/
class ReportRecord {
    
       protected $loaded_recs; //cache

       protected $rty_Names;
       protected $dty_Types;
       protected $dtTerms = null;
       protected $dbsTerms;
       protected $system;
    
       protected $translations; //cache for translated db definitions (terms,...)
    
    function __construct() {
       global $system; 
       
       $this->system = $system;
       $this->rty_Names = dbs_GetRectypeNames($system->get_mysqli());
       $this->dty_Types = dbs_GetDetailTypes($system, null, 4);
       /* loads on first request
       $this->dtTerms = dbs_GetTerms($system);
       $this->dbsTerms = new DbsTerms($system, $this->dtTerms);
       */
       $this->loaded_recs = array(); //cache

        $this->translations = array(
            'trm' => array()
        );
    }    

    //
    // returns value of heurist constants for Record and Detail types
    //
    public function constant($name, $smarty_obj=null) {
        if(defined($name)){
            return constant($name);  
        }else{
            if(strpos($name,'RT_')===0 || strpos($name,'DT_')===0){
                if($this->system->defineConstant($name)) return constant($name);  
            }
            
            return null;  
        }
    }
    
    public function baseURL(){
        return HEURIST_BASE_URL;
    }

    //
    //
    //
    public function getSysInfo($param){
        
        $res = null;
        $mysqli = $this->system->get_mysqli();
        
        if($param=='db_total_records'){
            
            $res = mysql__select_value($mysqli, 'SELECT count(*) FROM Records WHERE not rec_FlagTemporary');

        }else if($param=='db_rty_counts'){

            $res = mysql__select_assoc2($mysqli, 'SELECT rec_RecTypeID, count(*) FROM Records WHERE not rec_FlagTemporary GROUP BY rec_RecTypeID');
            
        }else if($param=='lang'){
            
            $res = $_REQUEST['lang'];
            if (!$res) {
                $res = $this->system->user_GetPreference('layout_language', '');
            }

            $res = getLangCode3($res);
        }

        
        return $res;
    }
    
    //
    //
    //
    public function rty_Name($rty_ID){
        return  $this->rty_Names[$rty_ID];
    }

    
    //
    // Returns local code for given concept code
    //
    public function rty_id($conceptCode, $smarty_obj=null) {
        return ConceptCode::getRecTypeLocalID($conceptCode);
    }

    public function dty_id($conceptCode, $smarty_obj=null) {
        return ConceptCode::getDetailTypeLocalID($conceptCode);
    }

    public function trm_id($conceptCode, $smarty_obj=null) {
        return ConceptCode::getTermLocalID($conceptCode);
    }
    
    //
    // this method is used in smarty template in main loop to access record info by record ID
    //
    public function getRecord($rec, $smarty_obj=null) {

        if(is_array($rec) && $rec['recID']){
            $rec_ID = $rec['recID'];
        }else{
            $rec_ID = $rec;
        }
        

        if(@$this->loaded_recs[$rec_ID]){ //already loaded
            return $this->loaded_recs[$rec_ID];
        }

        $rec = recordSearchByID($this->system, $rec_ID); //from db_recsearch.php
        
        if($rec){
            $rec['rec_Tags'] = recordSearchPersonalTags($this->system, $rec_ID); //for current user only
            if(is_array($rec['rec_Tags'])) $rec['rec_Tags'] = implode(',',$rec['rec_Tags']);
            
            $rec['rec_IsVisible'] = $this->recordIsVisible($rec); //for current user only
        }
        
        $res1 = $this->getRecordForSmarty($rec); 
        
        return $res1;
    }
    
    //
    // returns true if record is visible for current user
    //
    public function recordIsVisible($rec)
    {
        
        if(@$rec['rec_FlagTemporary']==1) return false;
        
        $currentUser = $this->system->getCurrentUser();
        
        $res = true;
        
        if($currentUser['ugr_ID']!=2) //db owner
        {
            if($rec['rec_NonOwnerVisibility']=='hidden'){
                $res = false;
            }else if($currentUser['ugr_ID']>0 && $rec['rec_NonOwnerVisibility']=='viewable'){
                
                $wg_ids = array();
                if(@$currentUser['ugr_Groups']){
                        $wg_ids = array_keys($currentUser['ugr_Groups']);
                        array_push($wg_ids, $currentUser['ugr_ID']);
                }else{
                        $wg_ids = $this->system->get_user_group_ids();    
                }
                array_push($wg_ids, 0); // be sure to include the generic everybody workgroup    
                
                if(is_array($wg_ids) && count($wg_ids)>0){
                    if(!in_array($rec['rec_OwnerUGrpID'],$wg_ids)){
                        
                        $query = 'select rcp_UGrpID from usrRecPermissions where rcp_RecID='.$rec['rec_ID'];
                        $allowed_groups = mysql__select_list2($this->system->get_mysqli(), $query);     
                        if(count($allowed_groups)>0 && count(array_intersect($allowed_groups, $wg_ids)==0)){
                            $res = false;
                        }
                    }
                }
            
            }
        }
        
        return $res; 
        
    }
    
    //
    // retuns array of related record with additional header values
    //                            recRelationID  
    //                            recRelationType
    //                            recRelationNotes
    //                            recRelationStartDate
    //                            recRelationEndDate
    //
    public function getRelatedRecords($rec, $smarty_obj=null){
        
        
            if(is_array($rec) && $rec['recID']){
                $rec_ID = $rec['recID'];
            }else{
                $rec_ID = $rec;
            }
            
        
            $relRT = ($this->system->defineConstant('RT_RELATION')?RT_RELATION:0);
            $relSrcDT = ($this->system->defineConstant('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
            $relTrgDT = ($this->system->defineConstant('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);

            $res = array();
            $rel_records = array();
            
            /* find related records */
            if($rec_ID>0 && $relRT>0 && $relSrcDT>0 && $relTrgDT>0){

                $mysqli = $this->system->get_mysqli();
                // get rel records where current record is source
                $from_res = $mysqli->query('SELECT rl_RelationID as dtl_RecID FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_SourceID='.$rec_ID);

                // get rel records where current record is target
                $to_res = $mysqli->query('SELECT rl_RelationID as dtl_RecID FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_TargetID='.$rec_ID);
                if($from_res && $to_res){
                    if ($from_res->num_rows > 0  ||  $to_res->num_rows > 0) {

                        //load relationship record details
                        while ($reln = $from_res->fetch_assoc()) {
                            $bd = fetch_relation_details($reln['dtl_RecID'], true);
                            array_push($rel_records, $bd);
                        }
                        while ($reln = $to_res->fetch_assoc()) {
                            $bd = fetch_relation_details($reln['dtl_RecID'], false);
                            array_push($rel_records, $bd);
                        }
                        
                        foreach ($rel_records as $key => $value){
                            if(array_key_exists('RelatedRecID',$value) && array_key_exists('RelTerm',$value)){
                                
                                
                                $record = $this->getRecord($value['RelatedRecID']['rec_ID']); //related record
                                                            
                                
                                //add specific variables from relationship record (rty_ID=1)
                                $record["recRelationID"] = $value['recID'];
                                
                                $record["recRelationType"] = $value['RelTerm'];

                                if(array_key_exists('Notes', $value)){
                                    $record["recRelationNotes"] = $value['Notes'];
                                }
                                if(array_key_exists('StartDate', $value)){
                                    $record["recRelationStartDate"] = Temporal::toHumanReadable($value['StartDate']);
                                }
                                if(array_key_exists('EndDate', $value)){
                                    $record["recRelationEndDate"] = Temporal::toHumanReadable($value['EndDate']);
                                }
                                
                                array_push($res, $record);
                            }
                        }
                        
                    }
                    
                    $from_res->close();
                    $to_res->close();
                }
            }
            return $res;
    }
    
    //
    // $rec - record id or record array - record to find records linked to or from this record
    // $rty_ID - record type or array of record type to filter output
    // $direction - linkedfrom or linkedto or null to return  both directions
    // returns array of record IDs devided to 2 arrays "linkedto" and "linkedfrom"
    //
    public function getLinkedRecords($rec, $rty_ID=null, $direction=null, $smarty_obj=null){
        
            if(is_array($rec) && $rec['recID']){
                $rec_ID = $rec['recID'];
            }else{
                $rec_ID = $rec;
            }
            
            $where = ' WHERE ';  
            
            if($rty_ID!=null){
                if(is_int($rty_ID) && $rty_ID>0) 
                {
                    $where = ', Records WHERE linkID=rec_ID and rec_RecTypeID='.$rty_ID.' AND ';
                }else{
                    if(!is_array($rty_ID)){
                        $ids = explode(',', $rty_ID);
                    }
                    $ids = array_map('intval', $ids);
                    if(count($ids)>1){
                        $where = ', Records WHERE linkID=rec_ID and rec_RecTypeID in ('
                                 .implode(',', $ids).') and ';
                    }
                }
            }
            
            $mysqli = $this->system->get_mysqli();
        
            $to_records = array();
            $from_records = array();
            
            if($direction==null || $direction=='linkedto'){
                // get linked records where current record is source
                $from_query = 'SELECT rl_TargetID as linkID FROM recLinks '
                    .str_replace('linkID','rl_TargetID',$where).' rl_RelationID IS NULL AND rl_SourceID='.$rec_ID;

               $to_records = mysql__select_list2($mysqli, $from_query);     
            }

            if($direction==null || $direction=='linkedfrom'){
                // get linked records where current record is target
                $to_query = 'SELECT rl_SourceID as linkID FROM recLinks '
                    .str_replace('linkID','rl_SourceID',$where).' rl_RelationID IS NULL AND rl_TargetID='.$rec_ID;

                    
                $from_records = mysql__select_list2($mysqli, $to_query);     
            }
            
            $res = array('linkedto'=>$to_records, 'linkedfrom'=>$from_records);
          
            return $res;
    }
    
    //
    // convert record array to array to be assigned to smarty variable
    //
    private function getRecordForSmarty($rec){

        if(!$rec){
            return null;
        }
        else
        {
            $recordID = $rec['rec_ID'];    
            
            if(@$this->loaded_recs[$recordID]){
                return $this->loaded_recs[$recordID];
            }
            
            
            $record = array();
            $recTypeID = null;
            
            $lang = $this->getSysInfo('lang');

            //$record["recOrder"] = $order;

            //loop for all record properties
            foreach ($rec as $key => $value){


                $pos = strpos($key,"rec_");
                if(is_numeric($pos) && $pos==0){
                    //array_push($record, array(substr($key,4) => $value));
                    $record['rec'.substr($key,4)] = $value;

                    if($key=='rec_RecTypeID'){ //additional field
                        $recTypeID = $value;
                        $record['recTypeID'] = $recTypeID;
                        $record['recTypeName'] = $this->rty_Names[$recTypeID];
                    }else if ($key=='rec_Tags'){ 
                        
                        $record['rec_Tags'] = $value;
                        
                    }else if ($key=='rec_ID'){ //load tags and woottext once per record

                        $record['recWootText'] = $this->getWootText($value); //htmlspecialchars(, ENT_QUOTES, 'UTF-8'); //@todo load dynamically 
                        
                    }else if ($key == 'rec_ScratchPad'){
                        
                        //$record['rec_ScratchPad'] = htmlspecialchars($record['rec_ScratchPad'], ENT_QUOTES, 'UTF-8');
                    }

                }
                else  if ($key == "details")
                {

                    $details = array();
                    foreach ($value as $dtKey => $dtValue){
                        $dt = $this->getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recordID, $lang); //$record['recID']);
                        if($dt){
                            $record = array_merge($record, $dt);
                        }
                    }

                }
            }
            
            if(count($this->loaded_recs)>2500){
                unset($this->loaded_recs);
                $this->loaded_recs = array(); //reset to avoid memory overflow
            }
            
            $this->loaded_recs[$recordID] = $record; 

            return $record;
        }
    }



    /**
    *
    */
    private function _add_term_val($res, $val){

        if($val){
            if(strlen($res)>0) $res = $res.", ";
            $res = $res.$val;
        }
        return $res;
    }

    //
    // convert details to array to be assigned to smarty variable
    // $dtKey - detailtype ID, if <1 this dummy relationship detail
    //
    private function getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recID, $lang){

        $issingle = false;

        if($dtKey<1 || $this->dty_Types[$dtKey]){

            if($dtKey<1){
                $dt_label = "Relationship";
                $dtname = "Relationship";
                $issingle = false;
                $dtDef = "dummy";

            }else{
                $dtname = "f".$dtKey;
            }


            if(is_array($dtValue)){ //complex type - need more analize
                $res = null;

                if($dtKey<1){
                    $detailType =  "relmarker";
                }else{
                    $detailType =  $this->dty_Types[ $dtKey  ];
                    /*
                    //detect single or repeatable - if repeatable add as array for enum and pointers
                    $dt_maxvalues = @$rt_structure[$dtKey][$dtmaxval_index];
                    if($dt_maxvalues){
                        $issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)===1);
                    }else{
                        $issingle = false;
                    }
                    */
                    $issingle = false;
                }


                switch ($detailType) {
                        case 'enum':
                        case 'relationtype':
                        
                            if($this->dtTerms==null){
                                $this->dtTerms = dbs_GetTerms($this->system);
                                $this->dbsTerms = new DbsTerms($this->system, $this->dtTerms);
                            }

                            $domain = ($detailType=="enum")?"enum":"relation";

                            $fi = $this->dtTerms['fieldNamesToIndex'];

                            $res_id = "";
                            $res_cid = "";
                            $res_code = "";
                            $res_label = "";
                            $res_label_full = '';
                            $res_desc = "";
                            $res = array();


                            foreach ($dtValue as $key => $value){
                                
                                $term = $this->dbsTerms->getTerm($value);
                                if($term){

                                    //IJ wants to show terms for all parents
                                    $term_full = $this->dbsTerms->getTermLabel($value, true);

                                    $term_label = $this->getTranslation('trm', $value, 'trm_Label', $lang);
                                    $term_desc = $this->getTranslation('trm', $value, 'trm_Description', $lang);
                                    
                                    $res_id = $this->_add_term_val($res_id, $value);
                                    $res_cid = $this->_add_term_val($res_cid, $term[ $fi['trm_ConceptID'] ]);
                                    $res_code = $this->_add_term_val($res_code, $term[ $fi['trm_Code'] ]);
                                    
                                    $res_label_full = $this->_add_term_val($res_label_full, $term_full);
                                    $res_label = $this->_add_term_val($res_label, $term_label); //$term[ $fi['trm_Label'] ]);
                                    $res_desc = $this->_add_term_val($res_desc, $term_desc); //$term[ $fi['trm_Description'] ]);

                                    //NOTE id and label are for backward
                                    //original value
                                    array_push($res, array("id"=>$value, "internalid"=>$value, 
                                        "code"=>$term[ $fi['trm_Code'] ], 
                                        "label"=>$term_label, 
                                        "term"=>$term_full, 
                                        "conceptid"=>$term[ $fi['trm_ConceptID'] ],
                                        "desc"=>$term_desc 
                                    ));
                                }
                            }
                            $res_united = array("id"=>$res_id, "internalid"=>$res_id, "code"=>$res_code, 
                                "term"=>$res_label_full, "label"=>$res_label, "conceptid"=>$res_cid, "desc"=>$res_desc
                            );

                            if(count($res)>0){
                                if($issingle){//no used
                                    $res = array( $dtname =>$res_united );
                                }else{
                                    $res = array( $dtname =>$res[0], $dtname."s" =>$res );
                                }
                            }

                            break;

                        case 'date':

                            $res = "";
                            $origvalues = array();
                            foreach ($dtValue as $key => $value){
                                if(strlen($res)>0) $res = $res.", ";
                                $res = $res.Temporal::toHumanReadable($value, true, 1);
                                array_push($origvalues, $value);
                            }
                            if(strlen($res)==0){ //no valid terms
                                $res = null;
                            }else{
                                $res = array( $dtname=>$res, $dtname."_originalvalue"=>$origvalues);
                            }
                            break;

                        case 'file':
                            //get url for file download

                            //if image - special case

                            $res = array(); //list of urls
                            $origvalues = array();

                            foreach ($dtValue as $key => $value){
                                
                                $external_url = @$value['file']['ulf_ExternalFileReference'];
                                if($external_url && strpos($external_url,'http://')!==0){
                                    array_push($res, $external_url);  //external 

                                }else 
                                if(@$value['file']['ulf_ObfuscatedFileID']){
                                    //local
                                    array_push($res, HEURIST_BASE_URL."?db=".$this->system->dbname()
                                            ."&file=".$value['file']['ulf_ObfuscatedFileID']);
                                }
                                //keep reference to record id
                                $value['file']['rec_ID'] = $recID;
                                
                                //original value keeps the whole 'file' array
                                array_push($origvalues, $value['file']);
                            }
                            if(count($res)==0){
                                $res = null;
                            }else{
                                $res = array($dtname=>implode(', ',$res), $dtname."_originalvalue"=>$origvalues);
                                //array_merge($arres, array($dtname=>$res));
                            }

                            break;

                        case 'geo':

                            $res = "";
                            $arres = array();
                            foreach ($dtValue as $key => $value){

                                //original value keeps whole geo array
                                $dtname2 = $dtname."_originalvalue";
                                $value['geo']['recid'] = $recID;
                                $arres = array_merge($arres, array($dtname2=>$value['geo']));
                                
                                $geom = geoPHP::load($value['geo']['wkt'], 'wkt');
                                if(!$geom->isEmpty()){
                                    $geojson_adapter = new GeoJSON();                                     
                                    $json = $geojson_adapter->write($geom, true); 
                                }
                                if(!$json) $json = array();
                                $dtname2 = $dtname."_geojson";
                                $arres = array_merge($arres, array($dtname2=>$json));
                                

                                $res = $value['geo']['wkt'];
                                break; //only one geo location at the moment
                            }

                            if(strlen($res)==0){
                                $res = null;
                            }else{
                                $res = array_merge($arres, array($dtname=>$res));
                                //$res = array( $dtname=>$res );
                            }

                            break;

                        case 'separator':
                        case 'calculated':
                        case 'fieldsetmarker':
                            break;

                        case 'relmarker': // NOT USED
                            break;
                        case 'resource': // link to another record type

                            $res = array();
                            if(count($dtValue)>0){
                            
                            foreach ($dtValue as $key => $value){
                                $recordID = $value['id'];
                                array_push($res, $recordID);
                            }
                            
                            if($issingle){
                                $res = array( $dtname =>$res[0] );
                            }else{
                                $res = array( $dtname =>$res[0], $dtname."s" =>$res );
                            }
                            
                            
                            }
                            break;
                            
                        default:
                            // repeated basic detail types
                            $res = "";
                            $origvalues = array();
                            foreach ($dtValue as $key => $value){
                                //$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                if(strlen($res)>0) $res = $res.", ";
                                $res = $res.$value;
                                array_push($origvalues, $value);
                            }
                            
                            if(count($dtValue)>1 && ($detailType=='freetext' || $detailType=='blocktext')){
                                $translated_value = getCurrentTranslation($dtValue, $lang);
                                if($translated_value!=null){
                                    $res = $translated_value;   
                                }
                            }
                            
                            if(strlen($res)==0){ //no valid value
                                $res = null;
                            }else{
                                $res = array( $dtname=>$res, $dtname."s" =>$origvalues, $dtname."_originalvalue"=>$origvalues);
                            }


                    }//end switch

                return $res;
            }
            else {
                return array( $dtname=>$dtValue );
                //return array( $dtname=>htmlspecialchars($dtValue, ENT_QUOTES, 'UTF-8') );
            }

        }else{ //name is not defined
            return null;
        }

    }



    //
    // Returns the united woot text
    //
    public function getWootText($recID){

        $res = "";

/* woot is disabled in this version
        $woot = loadWoot(array("title"=>"record:".$recID));
        if(@$woot["success"])
        {
            if(@$woot["woot"]){

                $chunks = $woot["woot"]["chunks"];
                $cnt = count($chunks);

                for ($i = 0; $i < $cnt; $i++) {
                    $chunk = $chunks[$i];
                    if(@$chunk["text"]){
                        $res = $res.$chunk["text"];
                    }
                }//for
            }
        }else if (@$woot["errorType"]) {
            $res = "WootText: ".$woot["errorType"];
        }
*/

        return $res;
    }
    
    public function getRecordTags($recID){
        
    }
    
    //
    // returns record ids for given query 
    //
    public function getRecords($query, $current_rec=null){
        
        $rec_ID = 0;
        if(is_array($current_rec) && $current_rec['recID']){
            $rec_ID = $current_rec['recID'];
        }else{
            $rec_ID = $current_rec;
        }
        
        if($rec_ID>0){
            //replace placeholder [ID] in query to current query id
            if(is_array($query)){
                $query = json_encode($query);
            }
            if(strpos($query,'[ID]')!==false)
                $query = str_replace('[ID]', strval($rec_ID), $query);
        }else{
            if(strpos($query,'[ID]')!==false){
                return null;
            }
        }
        
        $params = array('detail'=>'ids', 'q'=>$query, 'needall'=>1);
        
        $response = recordSearch($this->system, $params);
        
        if(@$response['status']==HEURIST_OK){
            return $response['data']['records'];
        }else{
            return null;
        }
        
    }
    
    //
    // returns aggregation values
    // $query_or_ids - heurist query or ids list 
    // $functions - array of pairs (field_id, avg|count|sum)
    //
    public function getRecordsAggr($functions, $query_or_ids, $current_rec=null){
        
        $ids = prepareIds($query_or_ids);
        if(count($ids)<1){
            $ids = $this->getRecords( $query_or_ids, $current_rec );
        }
        
        //calculate aggregation values
        $select = array();
        $from = array('Records ');
        $result = array();
        $idx = 0;
        
        if(is_array($functions) && count($functions)==2 && !is_array($functions[0])){
            $functions = array($functions);
        }
        
        foreach($functions as $idx2 =>$func){
            
            $dty_ID = @$func[0];
            $func = @$func[1];
            
            if($func=='avg' || $func=='sum' || $func=='count'){
                if($dty_ID>0){
                    array_push($select, $func.'(d'.$idx.'.dtl_Value)' );
                    array_push($from, ' JOIN recDetails d'.$idx.' ON rec_ID=d'.$idx.'.dtl_RecID AND d'.$idx.'.dtl_DetailTypeID='.$dty_ID );
                }else{
                    $func = 'count';
                    $dty_ID = '*';
                    array_push($select, 'count(rec_ID)' );
                }
                array_push($result, array($dty_ID, $func, 0));
                $idx++;
            }
        }
        
        if(count($select)>0){
            
            if(!$ids || count($ids)<1){
                $ids = array(0);
            }
            
            //@todo make chunks if count($ids)>10000?
            $query = 'SELECT '.implode(',',$select)
                        .' FROM '.implode(' ',$from)
                        .' WHERE rec_ID IN ('.implode(',',$ids).')';
            
            $res = mysql__select_row($this->system->get_mysqli(), $query);
            
            if($res!=null){
                
                if(count($res)==1){
                    return $res[0];
                }else{
                    //returns
                    foreach($res as $idx =>$val){
                       $result[$idx][2] = $val;
                    }
                    return $result;
                }
            }

        }
        return null;    
        
    }

    // 
    // returns translation for given entity + id
    //
    public function getTranslation($entity, $ids, $field, $language_code){
        
        $language_code = getLangCode3($language_code);

        $rtn = array();
        $def_values = array();

        $id_clause = '';

        if(!is_array($ids)){
            $ids = explode(',', $ids);
        }

        if(!array_key_exists($language_code, $this->translations[$entity])){
            $this->translations[$entity][$language_code] = array();
        }

        $cache = $this->translations[$entity][$language_code];

        
        if($entity == 'trm'){
            $field = (strpos(strtolower($field), 'desc') === false) ? 'trm_Label' : 'trm_Description'; // grab label by default
        }
        
        
        if(count($cache) > 0){ // check cache first
            foreach ($ids as $idx => $id) {
                if(array_key_exists($id, $cache) && @$cache[$id][$field]){
                    $rtn[$id] = $cache[$id][$field];
                    unset($ids[$idx]);
                }
            }
        }

        if(count($ids) == 0){
            return count($rtn) == 1 ? array_shift($rtn) : $rtn;
        }else if(count($ids) == 1){
            $id_clause = ' ='.$ids[0];
        }else{
            $id_clause = ' IN (' .implode(',', $ids). ')';    
        }

        if($entity == 'trm'){

            if($this->dtTerms==null){
                $this->dtTerms = dbs_GetTerms($this->system);
            }
            if($this->dbsTerms==null){
                $this->dbsTerms = new DbsTerms($this->system, $this->dtTerms);
            }

            // retrieve original term
            $idx = $this->dtTerms['fieldNamesToIndex'];
            $term = null;

            foreach ($ids as $trm_id) {
                $term = $this->dbsTerms->getTerm($trm_id);
                $def_values[$trm_id] = !empty($term[$idx[$field]]) ? $term[$idx[$field]] : '';
            }
        }

        if($id_clause != ''){

            $query = "SELECT trn_Code, trn_Translation FROM defTranslations WHERE trn_Code $id_clause AND trn_Source = '$field' AND trn_LanguageCode = '$language_code'";

            $res = mysql__select_assoc2($this->system->get_mysqli(), $query);

            foreach ($ids as $id) {
                    
                if(array_key_exists($id, $res) && !empty($res[$id])){
                    $rtn[$id] = $res[$id];
                }else if(array_key_exists($id, $def_values) && !empty($def_values[$id])){
                    $rtn[$id] = $def_values[$id];
                }else{
                    $rtn[$id] = '';
                }

                $cache[$id] = array($field => $rtn[$id]);
            }
        }

        $this->translations[$entity][$language_code] = $cache; //array_replace($this->translations[$entity][$language_code], $cache)

        return count($rtn) == 1 ? array_shift($rtn) : $rtn;
    }
    
}
?>
