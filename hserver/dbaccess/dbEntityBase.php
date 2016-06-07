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
        fields structure description (used in validataion and access)
    */
    protected $fields;  

    /**
    * keeps several records for delete,update actions
    * it is extracted from $data in begining of method
    * 
    * @var mixed
    */
    protected $records;
    
        
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
       $this->_readConfig();
    }

    //
    //
    //
    public function isvalid(){
        return is_array($this->config) && is_array($this->fields) && count($this->fields)>0;
    }

    //
    //
    ///
    public function config(){
        return $this->config;
    }
    
    //
    // save one or several records 
    //
    public function save(){

        //extract records
        if(!$this->prepareRecords()){
                return false;    
        }
        
        //validate permission
        if(!$this->_validatePermission()){
            return false;
        }
        
//error_log(print_r($this->records, true));        
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
        
        $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
        if($keep_autocommit===true) $mysqli->autocommit(FALSE);
        if (strnatcmp(phpversion(), '5.5') >= 0) {
            $mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        }
        
        
        
        foreach($this->records as $rec_idx => $record){
            
            //exclude virtual fields
            $fieldvalues = $record;
            $fields = array();
            foreach($this->fields as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual' || !array_key_exists($fieldname, $record)) continue;
                $fields[$fieldname] = $record[$fieldname];
            }
            
//error_log(print_r($fields,true));            

            //save data
            $ret = mysql__insertupdate($mysqli, 
                                    $this->config['tableName'], $this->config['tablePrefix'],
                                    $fields );
            if(is_numeric($ret)){
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
    public function delete(){

        if(!$this->_validatePermission()){
            return false;
        }
        $ret = mysql__delete($this->system->get_mysqli(), 
                                $this->config['tableName'], $this->config['tablePrefix'],
                                $this->data['recID'] );
        if($ret===true){
            return true;
        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, "Cannot delete from table ".$this->config['entityName'], $ret);
            return false;
        }
        
    }
    
    
    //
    //@todo
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
            if($field_config['dty_Type']=='resource'){
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
        $rec_ID = intval(@$fieldvalues[$table_prefix.'ID']);
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
    
    private function _readFields($fields){

        foreach($fields as $field){
            
            if(is_array(@$field['children']) && count($field['children'])>0){
                $this->_readFields($field['children']);
                
            }else{
                if(@$field['dtFields']['dty_Role']=='virtual') continue; //skip
                $this->fields[ $field['dtID'] ] = $field['dtFields'];
                
                if(@$field['dtFields']['dty_Role']=='primary'){
                    $this->primaryField = $field['dtID'];
                }
                
            }
        }
        
    }
    
    //
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
              $extension = $info->getExtension();
              
              if ($filename==$tempfile.'.'.$extension) {
                  $pathname = $info->getPath();
                  $new_name = $pathname.'/'.$recID.'.'.$extension;

                  rename ($info->getPathname(), $new_name);
              }
        }        
    }

    //
    // extract records from data parameter - it is used in delete, save
    //            
    protected function prepareRecords(){

        if(!is_array(@$this->data['fields']) || count($this->data['fields'])<1){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Missed 'fields' parameter. Fields are not defined");
                return false;    
        }
        //detect wehter this is milti record save
        if(array_keys($this->data['fields']) !== range(0, count($this->data['fields']) - 1)){
            $this->records = array();
            $this->records[0] = $this->data['fields']; 
        }else{
             //this is sequental array 
            $this->records = $this->data['fields'];
        }
       
        return true; 
    }    
    
}  
?>
