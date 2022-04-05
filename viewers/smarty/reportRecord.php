<?php
/**
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

class ReportRecord {
    
       protected $loaded_recs;
       protected $rtStructs;
       protected $dtStructs;
       protected $dtTerms;
       protected $dbsTerms;
       protected $system;
    
    function __construct() {
       global $system; 
       
       $this->system = $system;
       $this->rtStructs = dbs_GetRectypeStructures($system, null, 2);
       $this->dtStructs = dbs_GetDetailTypes($system);
       $this->dtTerms = dbs_GetTerms($system);
       $this->dbsTerms = new DbsTerms($system, $this->dtTerms);
       
       $this->loaded_recs = array();
    }    

    //
    // return value of heurist constants for Record and Detail types
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
        }
        
        $res1 = $this->getRecordForSmarty($rec); 
        
        return $res1;
    }
    
    //
    // retuns array of related record with additional header values
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
                                
                                
                                $record = $this->getRecord($value['RelatedRecID']['rec_ID']);
                                                            
                                
                                //add relationship specific variables
                                $record["recRelationType"] = $value['RelTerm'];

                                if(array_key_exists('Notes', $value)){
                                    $record["recRelationNotes"] = $value['Notes'];
                                }
                                if(array_key_exists('StartDate', $value)){
                                    $record["recRelationStartDate"] = temporalToHumanReadableString($value['StartDate']);
                                }
                                if(array_key_exists('EndDate', $value)){
                                    $record["recRelationEndDate"] = temporalToHumanReadableString($value['EndDate']);
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
    // $rtyt_ID - record type or array of record type to filter output
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
                        $record['recTypeName'] = $this->rtStructs['typedefs'][$value]['commonFields']
                                                            [ $this->rtStructs['typedefs']['commonNamesToIndex']['rty_Name'] ];
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
                        $dt = $this->getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recordID); //$record['recID']);
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
    private function getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recID){

        $dtNames = $this->dtStructs['names'];
        $rtNames = $this->rtStructs['names'];
        $dty_fi = $this->dtStructs['typedefs']['fieldNamesToIndex'];
        $rt_structure = null;
        $issingle = true;

        if($dtKey<1 || $dtNames[$dtKey]){

            if($dtKey<1){
                $dt_label = "Relationship";
                $dtname = "Relationship";
                $issingle = false;
                $dtDef = "dummy";

            }else{
                $rt_structure = $this->rtStructs['typedefs'][$recTypeID]['dtFields'];
                $rst_fi = $this->rtStructs['typedefs']['dtFieldNamesToIndex'];
                $dtlabel_index = $rst_fi['rst_DisplayName'];
                $dtmaxval_index = $rst_fi['rst_MaxValues'];
                if(array_key_exists($dtKey, $rt_structure)){
                    $dt_label = $rt_structure[$dtKey][ $dtlabel_index ];
                    //$dtname = getVariableNameForSmarty($dt_label);
                }
                $dtname = "f".$dtKey; // TODO: what is this: Artem 2013-12-09 getVariableNameForSmarty($dtNames[$dtKey]);

                $dtDef = @$this->dtStructs['typedefs'][$dtKey]['commonFields'];
            }


            if(is_array($dtValue)){ //complex type - need more analize
                $res = null;

                if($dtDef){

                    if($dtKey<1){
                        $detailType =  "relmarker";
                    }else{
                        $detailType =  $dtDef[ $dty_fi['dty_Type']  ];
                        //detect single or repeatable - if repeatable add as array for enum and pointers
                        $dt_maxvalues = @$rt_structure[$dtKey][$dtmaxval_index];
                        if($dt_maxvalues){
                            $issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)===1);
                        }else{
                            $issingle = false;
                        }
                        $issingle = false;
                    }


                    switch ($detailType) {
                        case 'enum':
                        case 'relationtype':

                            $domain = ($detailType=="enum")?"enum":"relation";

                            $fi = $this->dtTerms['fieldNamesToIndex'];

                            $res_id = "";
                            $res_cid = "";
                            $res_code = "";
                            $res_label = "";
                            $res_label_full = '';
                            $res = array();


                            foreach ($dtValue as $key => $value){
                                
                                $term = $this->dbsTerms->getTerm($value);
                                if($term){

                                    //IJ wants to show terms for all parents
                                    $term_full = $this->dbsTerms->getTermLabel($value, true);
                                    
                                    $res_id = $this->_add_term_val($res_id, $value);
                                    $res_cid = $this->_add_term_val($res_cid, $term[ $fi['trm_ConceptID'] ]);
                                    $res_code = $this->_add_term_val($res_code, $term[ $fi['trm_Code'] ]);
                                    $res_label_full = $this->_add_term_val($res_label_full, $term_full);
                                    $res_label = $this->_add_term_val($res_label, $term[ $fi['trm_Label'] ]);

                                    //NOTE id and label are for backward
                                    array_push($res, array("id"=>$value, "internalid"=>$value, 
                                        "code"=>$term[ $fi['trm_Code'] ], 
                                        "label"=>$term[ $fi['trm_Label'] ], 
                                        "term"=>$term_full, 
                                        "conceptid"=>$term[ $fi['trm_ConceptID'] ]));
                                }
                            }
                            $res_united = array("id"=>$res_id, "internalid"=>$res_id, "code"=>$res_code, 
                                "term"=>$res_label_full, "label"=>$res_label, "conceptid"=>$res_cid);

                            if(count($res)>0){
                                if($issingle){
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
                                $res = $res.temporalToHumanReadableString($value);
                                array_push($origvalues, temporalToHumanReadableString($value));
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
                                    array_push($res, HEURIST_BASE_URL."?db=".HEURIST_DBNAME
                                            ."&file=".$value['file']['ulf_ObfuscatedFileID']);
                                }
                                
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
                            
/* OLD WAY
                            //@todo - parsing will depend on depth level
                            // if there are not mentions about this record type in template (based on obtained array of variables)
                            // we will create href link to this record
                            // otherwise - we have to obtain this record (by ID) and add subarray

                            $res = array();
                            $rectypeID = null;
                            $prevID = null;
                            $order_sub = 0;

                            foreach ($dtValue as $key => $value){

                                if($recursion_depth<$max_allowed_depth && 
                                        (array_key_exists('id',$value) || array_key_exists('RelatedRecID',$value)))
                                {

                                    //this is record ID
                                    if(array_key_exists('RelatedRecID',$value)){
                                        $recordID = $value['RelatedRecID']['rec_ID'];
                                    }else{
                                        $recordID = $value['id'];
                                    }

                                    
                               
                                    $res0 = null;
                                    //get full record info
                                    if(@$this->loaded_recs[$recordID]){
                                        
                                        //already loaded
                                        $res0 = $this->loaded_recs[$recordID];

                                        $rectypeID = $res0['recTypeID'];

                                      
                                        
                                    }else{

                                        $record = loadRecord($recordID, false, true); //from search/getSearchResults.php
                                        
                                        if(true){  //load linked records dynamically
                                            $res0 = getRecordForSmarty($record, $recursion_depth+1, $order_sub); //@todo - need to
                                            $order_sub++;
                                    
                                            
                                        }
                                        if($rectypeID==null && $res0 && @$res0['recRecTypeID']){
                                                $rectypeID = $res0['recRecTypeID'];
                                        }
                                    }
                                    
                                    
                                    if($res0){

                                            //unset rel fields to avoid conflict if this records was already loaded
                                            if(@$res0["recRelationType"] ) unset($res0["recRelationType"]);
                                            if(@$res0["recRelationNotes"] ) unset($res0["recRelationNotes"]);
                                            if(@$res0["recRelationStartDate"] ) unset($res0["recRelationStartDate"]);
                                            if(@$res0["recRelationEndDate"] ) unset($res0["recRelationEndDate"]);
                                            
                                            //add relationship specific variables
                                            if(array_key_exists('RelatedRecID',$value) && array_key_exists('RelTerm',$value)){
                                                $res0["recRelationType"] = $value['RelTerm'];

                                                if(array_key_exists('Notes', $value)){
                                                    $res0["recRelationNotes"] = $value['Notes'];
                                                }
                                                if(array_key_exists('StartDate', $value)){
                                                    $res0["recRelationStartDate"] = temporalToHumanReadableString($value['StartDate']);
                                                }
                                                if(array_key_exists('EndDate', $value)){
                                                    $res0["recRelationEndDate"] = temporalToHumanReadableString($value['EndDate']);
                                                }
                                            }
                                            
                                            $this->loaded_recs[$recordID] = $res0;
                                            //$res0["recOrder"] = count($res);
                                            array_push($res, $res0);

                                    }

                                }
                            }//for each repeated value

                            if( count($res)>0 && array_key_exists($rectypeID, $rtNames))
                            {
    //DEBUG2 if($recID==5434)    error_log('recid '.$recID.'   '.print_r($res, true));                            

                                if(@$dtname){

                                    if($issingle){
                                        $res = array( $dtname =>$res[0] );
                                    }else{
                                        $res = array( $dtname =>$res[0], $dtname."s" =>$res );
                                    }
                                }

                            }else{
                                $res = null;
                            }

                            break;
*/                            
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
                            if(strlen($res)==0){ //no valid terms
                                $res = null;
                            }else{
                                $res = array( $dtname=>$res, $dtname."s" =>$origvalues, $dtname."_originalvalue"=>$origvalues);
                            }


                    }
                    //it depends on detail type - need specific behaviour for each type
                    //foreach ($value as $dtKey => $dtValue){}
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
}
?>
