<?php
   /**
    * Base class for all db entities
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
class DbEntityBase
{
    protected $system;  
    
    // set transaction in save,delete action - otherwise transaction is set on action above (batch action)
    protected $need_transaction = true;

    //reset all primary fields to zero - to force addition (POST request via API)
    protected $is_addition = false;
    
    /*  
        request from client side - contains field values for search and update
        
        data[fields] - values for particular record 
                 initiated in prepareRecords
    */    
    protected $data;  
    
    /*
        configuration form json file
    */
    protected $config;  
    
    //name of primary key field from $config
    protected $primaryField; 
    
    /*
        fields structure description from json (used in validataion and access)
    */
    protected $fields;  
    
    protected $fieldsNames; //non virtual field names

    /**
    * keeps several records for delete,update actions
    * it is extracted from $data in prepareRecords
    * 
    * @var array
    */
    protected $records = null;
    
    
    /**
    * IDs of records to update,delete
    * it is extracted from $data in prepareRecords
    * need for permissions validation
    * 
    * @var array
    */
    protected $recordIDs;

    //
    // constructor - load configuration from json file
    //    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
       $this->_readConfig();    
       
       $this->init();
       
       
       //rename generic ID or recID to primary field name for particular entity
       if(@$this->data[$this->primaryField]==null){
            if(@$this->data['ID']>0) {
                $this->data[$this->primaryField] = $this->data['ID'];
            }else if(@$this->data['recID']>0) {
                $this->data[$this->primaryField] = $this->data['recID'];
            }
       }
    }

    //
    // verify that entity is valid 
    // configuration is loaded
    // fields is not empty array
    //
    public function isvalid(){
        return is_array($this->config) && is_array($this->fields) && count($this->fields)>0;
    }

    //
    // config getter
    //
    public function init(){
    }

    //
    // assign parameters on server side
    //
    public function setData($data){
        $this->data = $data; 
        $this->records = null;
    }

    public function getData(){
        return $this->data; 
    }
    
    public function setNeedTransaction($value){
        $this->need_transaction = $value;
    }
    
    public function setRecords($records){
        $this->records = $records;
    }
    
    //
    // config getter
    //
    public function config(){
        return $this->config;
    }
    
    //
    // working with config/setting 
    //
    public function files( $action ){
        
        if(!($this->system->get_user_id()>0)){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'Insufficient rights for this operation');
            return false;
        }
        
        $res = false;
        
        $folder = $action['folder']; //folder
        $operation = $action['operation'];
        $content = @$action['content'];
        $filename = @$action['file'];
        $entity_name = $this->config['entityName'];
        $rec_ID = @$action['rec_ID'];
        
        $path = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/'.$folder.'/'.($rec_ID>0?$rec_ID.'/':''); 
        
        if($operation=='list'){
            
            if($folder==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Can not get list of setting files. Folder parameter is not defined');
                $res = false;
            }else if($rec_ID=='all'){
                
                if(file_exists($path)){
                
                    $dirs = array();
                    $dir = new DirectoryIterator($path);
                    foreach ($dir as $node) {
                        if ($node->isDir() && !$node->isDot()) {
                            $folder_name = $node->getFilename();
                            if(is_numeric($folder_name)){
                                array_push($dirs, $path.$folder_name.'/');
                            }
                        }
                    }
                    $res = folderContent($dirs, 'cfg');    
                }else{
                    $res = array('count'=>0,'reccount'=>0,'order'=>array());
                }
            }else{
                $res = folderContent($path, 'cfg');    
            }
            
        }else if($operation=='get'){
            
            $sMsg = 'Can not get content of settings file. ';
            
            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Filename parameter is not defined');
                $res = false;
            }else if (!file_exists($path.$filename)){
                $this->system->addError(HEURIST_ERROR, $sMsg.'File does not exist');
                $res = false;
            }else{
                $res = file_get_contents($path.$filename);    
            }
            
        }else if($operation=='put'){
            
            $filename = fileNameSanitize($filename);
            
            $sMsg = 'Can not save content the settings file. ';
            
            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Filename parameter is not defined');
                $res = false;
            }else if ($content==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Content parameter is not defined');
                $res = false;
            }else{
                $swarn = folderCreate2($path, '', false);
                if($swarn!=''){
                    $this->system->addError(HEURIST_ERROR, $sMsg.$swarn);
                    $res = false;
                }else{
                    $res = file_put_contents($path.$filename, $content);    
                    if($res!==false){
                        $res = $filename;
                    }
                }
            }
            
        }else if($operation=='rename'){
            
            $sMsg = 'Can not rename the settings file. ';
            
            $fileOld = @$action['fileOld'];
            if(file_exists($path.$fileOld)){
                
                $filename = fileNameSanitize($filename);
                if($filename==null){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'New filename parameter is not defined');
                    $res = false;
                }else if(!copy($path.$fileOld, $path.$filename)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg);
                    $res = false;
                }else{
                    unlink($path.$fileOld);
                    $res = $filename;
                }
                
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Settings file does not exist');
                $res = false;
            }
            
            
            
        }else if($operation=='delete'){
         
            $sMsg = 'Can not remove the settings file. ';
            
            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Filename parameter is not defined');
                $res = false;
            }else if (!file_exists($path.$filename)){
                $this->system->addError(HEURIST_ERROR, $sMsg.'File does not exist');
                $res = false;
            }else{
                $res = unlink($path.$filename);    
            }

        }
        
        return $res;
    }
    
    
    //
    //
    //
    public function records(){
        return $this->records;
    }
    
    public function run(){
        
        $res = false;
        
        if($this->isvalid()){
            if(@$this->data['a'] == 'search'){
                $res = $this->search();
            }else  if(@$this->data['a'] == 'title'){ //search for entity title by id
                $res = $this->search_title();
            }else if(@$this->data['a'] == 'add'){
                $this->is_addition = true;
                $this->data['a'] = 'save';
                $res = $this->save();
                $this->is_addition = false;
            }else if(@$this->data['a'] == 'save'){
                $res = $this->save();
            }else if(@$this->data['a'] == 'delete'){
                $res = $this->delete();

            }else if(@$this->data['a'] == 'config'){ // return configuration
                $res = $this->config();
            }else if(@$this->data['a'] == 'files'){ // working with settings/config files
            
                // get list of files by extension
                // put data into file
                // load date from file 
                $res = $this->files( $this->data ); 
                
            }else if(@$this->data['a'] == 'counts'){  //various counts(aggregations) request - implementation depends on entity
                $res = $this->counts();
            }else if(@$this->data['a'] == 'action' || @$this->data['a'] == 'batch'){ 
                //special and batch action. see details of operaion for method of particular class
                $res = $this->batch_action();
            }else {
                $this->system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
            }
        }
        
        return $res;
        
    }
    
    //
    // save one or several records 
    // returns false or array of record IDs
    //
    public function save(){

        //extract records from $_REQUEST data 
        if($this->records==null){ //records can be pepared beforehand
            if(!$this->prepareRecords()){
                    return false;    
            }
        }

        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }
        
        //validate values and check mandatory fields
        foreach($this->records as $record){
        
            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }
            
            //validate values
            if(!$this->_validateValues()){
                return false;
            }
        }
        
        //array of inserted or updated record IDs
        $results = array(); 
        
        //start transaction
        $mysqli = $this->system->get_mysqli();
        
        if($this->need_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }
        
        foreach($this->records as $rec_idx => $record){
            
            //$primary_field_type = 'integer';
            
            //exclude virtual fields
            $fieldvalues = $record;
            $values = array();
            foreach($this->fields as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual' || !array_key_exists($fieldname, $record)) continue;
                $values[$fieldname] = $record[$fieldname];
                
                /*if(@$field_config['dty_Role']=='primary'){
                    $primary_field_type = $field_config['dty_Type']; 
                }*/
            }
            
            //save data
            $ret = mysql__insertupdate($mysqli, 
                                    $this->config['tableName'], $this->fields,
                                    $values );

            if($ret===true || $ret==null){ //it returns true for non-numeric primary field
                   $results[] = $record[$this->primaryField];
            }else if(is_numeric($ret)){
                   $this->records[$rec_idx][$this->primaryField] = $ret;
                   $results[] = $ret;
            }else{
                    //rollback
                    if($this->need_transaction){
                        $mysqli->rollback();
                        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                    }
                    $this->system->addError(HEURIST_INVALID_REQUEST, 
                        'Cannot save data in table '.$this->config['entityName'], $ret);
                    return false;
            }
            
        }//for records
        if($this->need_transaction){
            //commit
            $mysqli->commit();
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        }
        return $results;
    }

    //
    // @todo multirecords and transaction
    //
    // returns - deleted:[], no_rights:[], in_use:[]
    //
    public function delete($disable_foreign_checks=false){
        
        if(!@$this->recordIDs){
            $this->recordIDs = prepareIds($this->data[$this->primaryField]);
        }

        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }        
        
        if(!$this->_validatePermission()){
            return false;
        }
        
        $mysqli = $this->system->get_mysqli();
        
        
        $mysqli->query('SET foreign_key_checks = 0');
        $query = 'DELETE FROM '.$this->config['tableName'].' WHERE '.$this->primaryField.' in ('.implode(',', $this->recordIDs).')';
        $ret = $mysqli->query($query);
        $affected = $mysqli->affected_rows;
        $mysqli->query('SET foreign_key_checks = 1');
        
        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR, 
                    'Cannot delete from table '.$this->config['entityName'], $mysqli->error);
            return false;
        }else if($affected===0){
            $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found');
            return false;
        }
                
        return true;
    }

    //
    // various counts(aggregations) request - implementation depends on entity
    //
    public function counts(){
    }
    
    //
    // see specific implemenation for every class 
    //
    public function batch_action(){
    }
    
    //
    //
    //
    protected function _validateFieldsForSearch(){
        
            foreach($this->data['details'] as $fieldname){
                if(!@$this->fields[$fieldname]){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                    return false;
                }            
            }
            //ID field is mandatory and MUST be first in the list
            $idx = array_search($this->primaryField, $this->data['details']);
            if($idx>0){
                unset($this->data['details'][$idx]);
                $idx = false;
            }
            if($idx===false){
                array_unshift($this->data['details'], $this->primaryField);
            }
            
            return true;
    }
    
    
    //
    //@todo validate permission per record
    //
    protected function _validatePermission(){
        return true;
    }
    //
    // @todo
    //
    protected function _validateValues(){
        
        $fieldvalues = $this->data['fields'];
        
        foreach($this->fields as $fieldname=>$field_config){
            if(@$field_config['dty_Role']=='virtual') continue;
                
            $value = @$fieldvalues[$fieldname];
            
            //ulf_MimeExt is the only nonnumeric resource
            if(@$field_config['dty_Type']=='resource' && $fieldname!='ulf_MimeExt'){ 
                if(intval($value)<1) $this->data['fields'][$fieldname] = null;
            }
        }
        
        return true;
    }

    //
    // 
    //    
    protected function _validateMandatory(){
        
        $fieldvalues = $this->data['fields'];
        
        $table_prefix = @$this->config['tablePrefix'];
        if($table_prefix==null){
            $table_prefix = '';
        }else if (substr($table_prefix, -1) !== '_') {
            $table_prefix = $table_prefix.'_';
        }
        $rec_ID = intval(@$fieldvalues[$this->primaryField]); // $table_prefix.'ID'
        $isinsert = ($rec_ID<1);
            
        foreach($this->fields as $fieldname=>$field_config){
            if(@$field_config['dty_Role']=='virtual') continue;
            
            if(array_key_exists($fieldname, $fieldvalues)){
                $value = $fieldvalues[$fieldname];    
            }else{
                if(!$isinsert) continue;
                $value = null;
            }
            
            if( ( $value===null  || trim($value)=='' ) && 
                (@$field_config['dty_Role']!='primary') &&
                (@$field_config['rst_RequirementType'] == 'required')){
             
                $this->system->addError(HEURIST_INVALID_REQUEST, "Field $fieldname is mandatory.");
                return false;    
            }
        }
        return true;
    }    

    //
    //
    //
    private function _readConfig(){
        
        if(is_array($this->config)){ //config may be predefined as part of code
            $this->fields = array();
            $this->_readFields($this->config['fields']);
            return;
        }

        //$entity_file = dirname(__FILE__)."/".@$this->data['entity'].'.json';
        $entity_file = HEURIST_DIR.'hsapi/entity/'.lcfirst(@$this->data['entity']).'.json';
        
        if(file_exists($entity_file)){
            
           $json = file_get_contents($entity_file);
           
           $this->config = json_decode($json, true);
           
           
           if(is_array($this->config) && $this->config['fields']){
               
                $this->fields = array();
                $this->_readFields($this->config['fields']);
           }
           
           if(!$this->isvalid()){
                $this->system->addError(HEURIST_SYSTEM_FATAL, 
                    "Configuration file $entity_file is invalid. Cannot init instance on server");     
           }
           
        }else{
           $this->system->addError(HEURIST_SYSTEM_FATAL, 'Cannot find configuration for entity '.@$this->data['entity'].' in '.HEURIST_DIR.'hsapi/entity/');     
        }
    }
    
    //
    // read fields definition from config file
    // assign primaryField
    //
    private function _readFields($fields){

        foreach($fields as $field){
            
            if(is_array(@$field['children']) && count($field['children'])>0){
                $this->_readFields($field['children']);
                
            }else{
                if(@$field['dtFields']['dty_Role']!='virtual'){
                    $this->fieldNames[] = $field['dtID'];
                }
                $this->fields[ $field['dtID'] ] = $field['dtFields'];
                
                if(@$field['dtFields']['dty_Role']=='primary'){
                    $this->primaryField = $field['dtID'];
                }
                
            }
        }
        
    }
    
    // need to rename temporary enity files to permanent  "entity/[entity name]/recID.png"
    // $tempfile - file to be either 
    // 1) renamed to recID (if it is temp file started with ~)
    // 2) copied 
    protected function renameEntityImage($tempfile, $recID, $version=null){

        $isSuccess = false;
        
        $entity_name = $this->config['entityName'];
        if($version==null){  //if version is defined we copy only it (icon or thumbnail)
            $version = '';
        }
        $lv = strlen($version);
        
        $path = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/'; //destination
        
        if(strpos($tempfile,'~')===0){  
            //temp file is in the same folder as destination
            
            $directory = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($directory);        
            
            foreach ($iterator as $filepath => $info) {  //rec. iteration need to copy all versions (thumb and full img)
                  if(!$info->isFile()) continue;
                  
                  $filename = $info->getFilename();
                  $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
                  //$extension = $info->getExtension(); since 5.3.6 
                  

                  if ($filename==$tempfile.'.'.$extension) {
                      $pathname = $info->getPath();
                      $tempfile_ = $info->getPathname();
                      if($lv==0 || substr($pathname, -$lv) === $version){
                            $new_name = $pathname.'/'.$recID.'.'.$extension;
                            $isSuccess = rename ($tempfile_, $new_name);
                      }
                      if(file_exists($tempfile_)){
                            unlink( $tempfile_ );
                      }
                  }
            }
            
        }else if(file_exists($tempfile)){
            $path_parts = pathinfo($tempfile);
            $ext = strtolower($path_parts['extension']);
            
            if($version!=''){ //copy only icon or thumb
                $new_name = $path.$version.'/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name);
            }else{
                $new_name = $path.$recID.'.'.$ext;
                $new_name_thumb = $path.'thumbnail/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name) &&  fileCopy($tempfile, $new_name_thumb);
            }
        }
        
        if(!$isSuccess){
            error_log('Can\'t copy file '.$tempfile.' as '.$entity_name.' image');
        }
    }
    
    //find $tempfile among temporary files in entity folder and return file info
    protected function getTempEntityFile($tempfile){
        $entity_name = $this->config['entityName'];
        
        $path = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/';

        $directory = new \DirectoryIterator($path);  //RecursiveDirectoryIterator
        $iterator = new \IteratorIterator($directory);  //Recursive      
        
        foreach ($iterator as $filepath => $info) {
              if(!$info->isFile()) continue;
              
              $filename = $info->getFilename();
              $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
              //$extension = $info->getExtension(); since 5.3.6
              
              if ($filename==$tempfile.'.'.$extension) {
                    return $info;                  
              }
        }
        return null;
    }

    //
    //
    //
    protected function getEntityImagePath($recID, $version=null, $db_name=null, $extension=null){
            
            $entity_name = $this->config['entityName'];
            
            if($entity_name=='sysDatabases'){
                
                $db_name = $recID;
                if(strpos($recID,'hdb_')===0){
                    $db_name = substr($recID,4);    
                }
                $rec_id = 1;    
                $path = HEURIST_FILESTORE_ROOT . $db_name . '/entity/sysIdentification/';    
            }else{
                if($db_name==null) $db_name = HEURIST_DBNAME;
                
                $path = HEURIST_FILESTORE_ROOT.$db_name.'/entity/'.$entity_name.'/';
                //$path = HEURIST_FILESTORE_DIR . 'entity/'.$entity_name.'/';
            }
        
            if($recID>0){
        
                if($version=='thumb' || $version=='thumbnail'){
                    $filename = $path.'thumbnail/'.$recID.'.png'; 
                }else if($version=='icon'){
                    $filename = $path.'icon/'.$recID.'.png';    
                }else{
                    $filename = null;
                    $exts = $extension?array($extension):array('png','jpg','jpeg','jfif','gif');
                    foreach ($exts as $ext){
                        $filename = $path.$recID.'.'.$ext;
                        if(file_exists($filename)){
                            if($ext=='jpg' || $ext=='jfif'){
                                $content_type = 'image/jpeg';
                            }else{
                                $content_type = 'image/'.$ext;    
                            }
                            break;
                        }
                    }
                }                  
                
                return $filename;     
            }else{
                return null;                     
            }
    }
    
    //
    // extracts records from "data" parameter and fills $this->recordIDs and $this->records 
    // it is used in delete, save
    //            
    //  fields:[fldname:value,fieldname2:values,.....]
    //
    protected function prepareRecords(){
        //fields contains record data
        if(is_string($this->data['fields'])){
            $this->data['fields'] = json_decode($this->data['fields'], true);
        }
        
        
        if(!is_array(@$this->data['fields']) || count($this->data['fields'])<1){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Missing 'fields' parameter. Fields are not defined");
                return false;    
        }
        //detect wheter this is multi record save
        if(array_keys($this->data['fields']) !== range(0, count($this->data['fields']) - 1)){
            //number of keys equals to number of entries it means single record
            $this->records = array();
            $this->records[0] = $this->data['fields']; 
            //$this->recordIDs = $record[$this->primaryField];           
        }else{
             //this is 2dim array
            $this->records = $this->data['fields'];
        }
        
        //exctract primary keys
        $this->recordIDs = array();
        foreach($this->records as $idx=>$record){
            if($this->is_addition){
                $this->records[$idx][$this->primaryField] = 0;
            }
            if(@$record[$this->primaryField]>0){
                $this->recordIDs[] = $record[$this->primaryField];
            }
        }
       
        return true; 
    }    
    
    //
    //
    //
    public function search_title(){

        $this->data['details'] = 'name'; 

        $ret = $this->search(); 
        if($ret!==false){
            $res = array();
            foreach($ret['records'] as $record){
                //$record[0]
                $res[] = $record[1];    
            }
            return $res;
        }else{
            return false;
        }

    }
    
    //
    //
    //
    public function search(){
        
        $this->searchMgr = new DbEntitySearch( $this->system, $this->fields);

        $res = $this->searchMgr->validateParams( $this->data );
        if(!is_bool($res)){
            $this->data = $res;
        }else{
            if(!$res) return false;        
        }        

        return true;        
    }
    
}  
?>
