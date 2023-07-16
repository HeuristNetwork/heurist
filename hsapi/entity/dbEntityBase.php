<?php
   /**
    * Base class for all db entities
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
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
    
    //name of primary key field from $config  by dty_Role="primary"
    protected $primaryField; 


    //names of multilang fiekds from $config by rst_MultiLang=1
    protected $multilangFields = array(); 
    
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
    protected $records = array();
    
    
    /**
    * keeps translated values for fields that are extracted from request in prepareRecords
    * 
    * @var array
    */
    protected $translation = array();
    
    
    /**
    * IDs of records to update,delete
    * it is extracted from $data in prepareRecords
    * need for permissions validation
    * 
    * @var array
    */
    protected $recordIDs = array();

    //
    // constructor - loads configuration from json file
    //    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
       $this->_readConfig();    
       
       $this->init();
       
       
       //rename generic ID or recID to valid primary field name for particular entity
       if(@$this->data[$this->primaryField]==null){
            if(@$this->data['ID']>0) {
                $this->data[$this->primaryField] = $this->data['ID'];
            }else if(@$this->data['recID']>0) {
                $this->data[$this->primaryField] = $this->data['recID'];
            }
       }
    }

    //
    // verifies that entity is valid 
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
    public function config( $locale='en' ){

        if(!@$this->config['locale']){
            @$this->config['locale'] = 'en';
        }
        
        if(@$this->config['locale']!=$locale){
            $this->_readConfigLocale($locale);     
        }
        
        return $this->config;
    }
    
    //
    // working with config/setting  see configEntity.js
    //
    public function files( $action ){
        
        if(!($this->system->get_user_id()>0)){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'Insufficient rights (logout/in to refresh) for this operation');
            return false;
        }
        
        $res = false;
        
        $folder = $action['folder']; //folder
        $operation = $action['operation'];
        $content = @$action['content'];
        $filename = @$action['file'];
        $entity_name = $this->config['entityName'];
        $rec_ID = @$action['rec_ID'];
        
        //available values are hardcoded - prevent 
        if($entity_name!='defRecTypes'){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Entity parameter is wrong or not defined');
            $res = false;
        }else if($folder==null || !in_array($folder, array('datatable','csvexport','crosstabs'))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Folder parameter is wrong or not defined');
            $res = false;
        }else{
        
        $path = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/'.$folder.'/'.($rec_ID>0?$rec_ID.'/':''); 
        
        if($operation=='list'){
            
            if($rec_ID=='all'){
                
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
            
            $sMsg = 'Cannot get content of settings file. ';
            
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

            //verify exetension for $filename
            $path_parts = pathinfo($filename);
            $ext = strtolower(@$path_parts['extension']);
            
            $sMsg = 'Cannot save content the settings file. ';
            
            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Filename parameter is not defined');
                $res = false;
            }else if($ext!='cfg'){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Only cfg extension allowed for configuration file');
                $res = false;
            }else if ($content==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Content parameter is not defined');
                $res = false;
            }else{
                
                //verify that content is valid JSON
                $config = json_decode($content, true);
                if(!is_array($config)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Content is not valid json');
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
            }
            
        }else if($operation=='rename'){
            
            $sMsg = 'Cannot rename the settings file. ';
            
            $fileOld = @$action['fileOld'];
            if(file_exists($path.$fileOld)){
                
                $filename = fileNameSanitize($filename);

                //verify exetension for $filename
                $path_parts = pathinfo($filename);
                $ext = strtolower(@$path_parts['extension']);

                
                if($filename==null){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'New filename parameter is not defined');
                    $res = false;
                }else if($ext!='cfg'){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Only cfg extension allowed for configuration file');
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
         
            $sMsg = 'Cannot remove the settings file. ';
            
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
                $res = $this->config( @$this->data['locale'] );
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
                if($res){
                    $this->_cleanDbDefCache();
                }
            }else {
                $this->system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
            }
        }
        
        return $res;
        
    }
    
    //
    //
    //
    private function _cleanDbDefCache(){
        
        if(is_array($this->config) && 
            in_array($this->config['tablePrefix'], array('rty','dty','rst','trm','rtg','dtg','vcg','swf')))
        { //affected entity
            fileDelete($this->system->getFileStoreRootFolder().$this->system->dbname().'/entity/db.json');
        }
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

            $isinsert = (intval(@$record[$this->primaryField])<1);
                                    
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
            
            //update translations
            if(is_array(@$this->translation[$rec_idx]) && count($this->translation[$rec_idx])>0)
            {
                foreach($this->multilangFields as $fieldname){
                    //delete previous translations for this record
                    if(!$isinsert){
                        $mysqli->query('DELETE FROM defTranslations where trn_Source="'
                            .$fieldname.'" AND trn_Code='.$this->records[$rec_idx][$this->primaryField]);
                    }
                    if(@$this->translation[$rec_idx][$fieldname]!=null){
                        
                        $langs = $this->translation[$rec_idx][$fieldname];
                        
                        foreach($langs as $lang=>$value){
                            if($value!=null && trim($value)!=''){
                                mysql__insertupdate($mysqli, 
                                        'defTranslations', 'trn',
                                array('trn_ID'=>0,
                                      'trn_Source'=>$fieldname,          
                                      'trn_Code'=>$this->records[$rec_idx][$this->primaryField],
                                      'trn_LanguageCode'=>$lang,
                                      'trn_Translation'=>$value));
                            }
                        }
                    }
                }
                
            }else if(!$isinsert && count($this->multilangFields)>0){ 
                //remove all translation for this record
                
                $mysqli->query('DELETE FROM defTranslations where trn_Source LIKE "'
                .$this->config['tablePrefix']
                .'%" AND trn_Code='.$this->records[$rec_idx][$this->primaryField]);
                
            }
            
            
            
        }//for records
        if($this->need_transaction){
            //commit
            $mysqli->commit();
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
            
            $this->_cleanDbDefCache();
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
        
        if(count($this->recordIDs)>1){
            $recids_compare = ' in ('.implode(',', $this->recordIDs).')';
        }else{
            $recids_compare = ' = '.$this->recordIDs[0];    
        }
        
        
        $mysqli->query('SET foreign_key_checks = 0');
        $query = 'DELETE FROM '.$this->config['tableName'].' WHERE '.$this->primaryField
                .$recids_compare;
        $ret = $mysqli->query($query);
        $affected = $mysqli->affected_rows;
        
        //
        // delete from translation table all fields that starts with current table prefix and with given record ids
        // array('rty','dty','ont','vcb','trm','rst','rtg')
        //
        if(count($this->multilangFields)>0)
        {
            $mysqli->query('DELETE FROM defTranslations where trn_Source LIKE "'
                                .$this->config['tablePrefix'].'%" AND trn_Code '
                                .$recids_compare);
        }
        
        $mysqli->query('SET foreign_key_checks = 1');
        
        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR, 
                    'Cannot delete from table '.$this->config['entityName'], $mysqli->error);
            return false;
        }else if($affected===0){
            $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found');
            return false;
        }
        
        $this->_cleanDbDefCache();
                
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
            
            if(@$field_config['rst_MultiLang'] && is_array($value)){
                $value = count($value)>0?$value[0]:'';
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
    //
    //    
    private function _readConfigLocale( $locale='en' ){

        $entity_file = HEURIST_DIR.'hsapi/entity/'.lcfirst(@$this->data['entity'])
            .($locale=='en'?'':('_'.$locale)).'.json';
        
        if(file_exists($entity_file)){

           $json = file_get_contents($entity_file);
           $locale_config = json_decode($json, true);

           $this->config['locale'] = $locale;
           $this->config['entityTitle'] = $locale_config['entityTitle'];
           $this->config['entityTitlePlural'] = $locale_config['entityTitlePlural'];
           
           $this->_fieldsSetLocale( $this->config['fields'], $locale_config['fields'] );
        }
    }

    //
    //
    //
    private function _getFieldByID($id, $fields){
        foreach($fields as $field){
            
            if(@$field['dtID']==$id){
                return $field;
            }else if(is_array(@$field['children']) && count($field['children'])>0){
                $res = $this->_getFieldByID($id, $field['children']);
                if($res){
                    return $res;
                }
            }
        }
        return null;
    }
    
    //
    // assign localized name and description for fields
    //
    private function _fieldsSetLocale( &$fields, $fields_locale ){
        
        foreach($fields as $idx=>$field){
            
            if(is_array(@$field['children']) && count($field['children'])>0){
                
                $fld_loc = $this->_getFieldByID($field['dtID'], $fields_locale);
                if($fld_loc && @$fld_loc['groupHeader']){
                    $fields[$idx]['groupHeader'] = $fld_loc['groupHeader'];    
                }
                
                $this->_fieldsSetLocale($fields[$idx]['children'], $fields_locale);
                
            }else{
                
                $fld_loc = $this->_getFieldByID($field['dtID'], $fields_locale);
                if($fld_loc && @$fld_loc['dtFields']){
                    if(@$fld_loc['dtFields']['rst_DisplayName'])
                        $fields[$idx]['dtFields']['rst_DisplayName'] = $fld_loc['dtFields']['rst_DisplayName'];    
                    if(@$fld_loc['dtFields']['rst_DisplayHelpText'])
                        $fields[$idx]['dtFields']['rst_DisplayHelpText'] = $fld_loc['dtFields']['rst_DisplayHelpText'];    
                }
                
                
            }
        }
    }
    
    //
    // read fields definition from config file
    // assign primaryField, multilangFields
    //
    private function _readFields($fields){
        
        //$this->multilangFields = array();

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
                if(@$field['dtFields']['rst_MultiLang']){
                    array_push($this->multilangFields, $field['dtID']);
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

            //special case - remove file with the same name and different ext
            $ext2 = ($ext=='svg')?'png':'svg';
            
            if($version!=''){ //copy only icon or thumb
                $new_name = $path.$version.'/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name);
                
                fileDelete($path.$version.'/'.$recID.'.'.$ext2);
            }else{
                $new_name = $path.$recID.'.'.$ext;
                $new_name_thumb = $path.'thumbnail/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name) &&  fileCopy($tempfile, $new_name_thumb);
                
                fileDelete($path.$recID.'.'.$ext2);
                fileDelete($path.'thumbnail/'.$recID.'.'.$ext2);
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
            
            list($filename, $content_type, $url) = resolveEntityFilename($entity_name, $recID, $verions, $db_name, $extension);
            
            return $filename;
/*            
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
                    $exts = $extension?array($extension):array('png','jpg','jpeg','jpe','jfif','gif');
                    foreach ($exts as $ext){
                        $filename = $path.$recID.'.'.$ext;
                        if(file_exists($filename)){
                            if($ext=='jpg' || $ext=='jfif' || $ext=='jpe'){
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
*/            
    }
    
    //
    // extracts records from "data" parameter and fills $this->recordIDs and $this->records 
    // it is used in delete, save
    //            
    //  fields:[fldname:value,fieldname2:values,.....]
    //  translation:[fldname:"lang:value",fieldname2:"lang:value",.....]
    //
    protected function prepareRecords(){
        //fields contains record data
        if(@$this->data['fields'] && is_string($this->data['fields'])){
            $this->data['fields'] = json_decode($this->data['fields'], true);
        }
        
        
        if(!is_array(@$this->data['fields']) || count($this->data['fields'])<1){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Missing 'fields' parameter. Fields are not defined");
                return false;    
        }
        //detect whether this is multi record save
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
            $rec_ID = @$this->records[$idx][$this->primaryField];
            if($rec_ID>0){
                $this->recordIDs[] = $rec_ID;
            }
            
            //extract translated values into separate array
            foreach($this->multilangFields as $fieldname){
                $values = $record[$fieldname];
                if(is_array($values)){
                    $mainvalue = null;
                    foreach($values as $k => $val){
                        
                        list($lang, $val) = extractLangPrefix($val);
                        
                        if($lang!=null)
                        {
                            if(!@$this->translation[$idx]){
                                $this->translation[$idx] = array();  
                            } 
                            if(!@$this->translation[$idx][$fieldname]){
                                $this->translation[$idx][$fieldname] = array();
                            }
                            
                            $this->translation[$idx][$fieldname][$lang] = $val;
                        
                        }else if(!$mainvalue){
                            //without lang prefix, take first only
                            $mainvalue = $val;
                        } 
                    }//values
                    if($mainvalue!=null && @$this->translation[$idx][$fieldname]){
                        $this->records[$idx][$fieldname] = $mainvalue;    
                    }
                }
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
