<?php
   /**
    * Base class for all db entities
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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
    
    /*  
        request from client side - contains field values for search and update
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

    /**
    * keeps several records for delete,update actions
    * it is extracted from $data in prepareRecords
    * 
    * @var array
    */
    protected $records;
    
    
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
    // config getter
    //
    public function config(){
        return $this->config;
    }
    
    
    public function records(){
        return $this->records;
    }
    
    //
    // save one or several records 
    //
    public function save(){

        //extract records from $_REQUEST data 
        if(!$this->prepareRecords()){
                return false;    
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
        
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        foreach($this->records as $rec_idx => $record){
            
            //exclude virtual fields
            $fieldvalues = $record;
            $values = array();
            foreach($this->fields as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual' || !array_key_exists($fieldname, $record)) continue;
                $values[$fieldname] = $record[$fieldname];
            }
            
//error_log(print_r($values,true));        

            //save data
            $ret = mysql__insertupdate($mysqli, 
                                    $this->config['tableName'], $this->fields,
                                    $values );
//error_log('res on save '.$ret);                                            
            if($ret==null){ //it return null for non-numeric primary field
                   $results[] = $record[$this->primaryField];
            }else if(is_numeric($ret)){
                   $this->records[$rec_idx][$this->primaryField] = $ret;
                   $results[] = $ret;
            }else{
                    //rollback
                    $mysqli->rollback();
                    if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                    $this->system->addError(HEURIST_INVALID_REQUEST, 
                        'Cannot save data in table '.$this->config['entityName'], $ret);
                    return false;
            }
            
        }//for records
        //commit
        $mysqli->commit();
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        return $results;
    }

    //
    // @todo multirecords and transaction
    //
    // returns - deleted:[], no_rights:[], in_use:[]
    //
    public function delete(){
        
        if(!@$this->recordIDs){
            $this->recordIDs = prepareIds($this->data['recID']);
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
        $mysqli->query('SET foreign_key_checks = 1');
        
        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR, 
                    "Cannot delete from table ".$this->config['entityName'], $mysqli->error);
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
        
        $table_prefix = $this->config['tablePrefix'];
        if (substr($table_prefix, -1) !== '_') {
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
            
            if( ( $value==null  || trim($value)=='' ) && 
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

        //$entity_file = dirname(__FILE__)."/".@$this->data['entity'].'.json';
        $entity_file = HEURIST_DIR.'hserver/dbaccess/'.@$this->data['entity'].'.json';
        
        if(file_exists($entity_file)){
            
           $json = file_get_contents($entity_file);
//error_log($json);
           
           $this->config = json_decode($json, true);
           
//error_log($this->config, true);
           
           if(is_array($this->config) && $this->config['fields']){
               
                $this->fields = array();
                $this->_readFields($this->config['fields']);
           }
           
           if(!$this->isvalid()){
                $this->system->addError(HEURIST_INVALID_REQUEST, 
                    "Configuration file $entity_file is invalid. Cannot init instance on server");     
           }
        }else{
           $this->system->addError(HEURIST_INVALID_REQUEST, "Cannot find configuration for entity ".@$data['entity']);     
        }
    }
    
    //
    // read fields definition from config file
    //
    private function _readFields($fields){

        foreach($fields as $field){
            
            if(is_array(@$field['children']) && count($field['children'])>0){
                $this->_readFields($field['children']);
                
            }else{
                //if(@$field['dtFields']['dty_Role']=='virtual') continue;
                $this->fields[ $field['dtID'] ] = $field['dtFields'];
                
                if(@$field['dtFields']['dty_Role']=='primary'){
                    $this->primaryField = $field['dtID'];
                }
                
            }
        }
        
    }
    
    // need to rename temporary enity files to permanent
    // $tempfile - file to rename to recID
    //
    protected function renameEntityImage($tempfile, $recID){

        $entity_name = $this->config['entityName'];
        
        $path = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/';

        $directory = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($directory);        
        
        foreach ($iterator as $filepath => $info) {
              if(!$info->isFile()) continue;
              
              $filename = $info->getFilename();
              $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
              //$extension = $info->getExtension(); since 5.3.6 

              if ($filename==$tempfile.'.'.$extension) {
                  $pathname = $info->getPath();
                  $new_name = $pathname.'/'.$recID.'.'.$extension;
                  rename ($info->getPathname(), $new_name);
              }
        }
    }
    
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
                    $exts = $extension?array($extension):array('png','jpg','jpeg','gif');
                    foreach ($exts as $ext){
                        $filename = $path.$recID.'.'.$ext;
                        if(file_exists($filename)){
                            $content_type = 'image/'.$ext;
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
    // extract records from data parameter - it is used in delete, save
    //            
    //  fields:[fldname:value,fieldname2:values,.....]
    //
    protected function prepareRecords(){

        if(!is_array(@$this->data['fields']) || count($this->data['fields'])<1){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Missed 'fields' parameter. Fields are not defined");
                return false;    
        }
        //detect wheter this is multi record save
        if(array_keys($this->data['fields']) !== range(0, count($this->data['fields']) - 1)){
            $this->records = array();
            $this->records[0] = $this->data['fields']; 
            //$this->recordIDs = $record[$this->primaryField];           
        }else{
             //this is sequental array 
            $this->records = $this->data['fields'];
        }
        
        //exctract primary keys
        $this->recordIDs = array();
        foreach($this->records as $record){
            if(@$record[$this->primaryField]>0){
                $this->recordIDs[] = $record[$this->primaryField];
            }
        }
       
        return true; 
    }    
    
    public function search_title(){

        $this->data[$this->primaryField] = $this->data['recID'];
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
    
}  
?>
