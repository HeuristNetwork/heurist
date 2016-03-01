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

    
    /*
        fields structure description (used in validataion and access)
    */
    protected $fields;  


        
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
    
    
    public function save(){

        //validate permission
        if(!$this->_validatePermission()){
            return false;
        }
        
        //validate mandatory fields
        if(!$this->_validateMandatory()){
            return false;
        }
        
        //validate values
        if(!$this->_validateValues()){
            return false;
        }
        
        //exclude virtual fields
        $fieldvalues = $this->data['fields'];
        $fields = array()
        foreach($this->fields as $fieldname=>$field_config){
            if(@$field_config['dty_Role']=='virtual') continue;
            $fields[$fieldname] = $this->data['fields'][$fieldname];
        }
        
        
        //save data
        $ret = mysql__insertupdate($this->system->get_mysqli(), 
                                $this->config['tableName'], $this->config['tablePrefix'],
                                $fields );
        if(is_numeric($ret)){
                return $ret;
        }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, "Can not save data in table ".$this->config['entityName'], $ret);
                return false;
        }
    }

    //
    //
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
            $this->system->addError(HEURIST_INVALID_REQUEST, "Can not delete from table ".$this->config['entityName'], $ret);
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
        
        foreach($this->fields as $fieldname=>$field_config){
            if(@$field_config['dty_Role']=='virtual') continue;
            
            $value = @$fieldvalues[$fieldname];
            
            if(($value==null || trim($value)=='') && 
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

        $entity_file = dirname(__FILE__)."/".@$this->data['entity'].'.json';
        
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
                    "Configuration file $entity_file is invalid. Can not init instance on server");     
           }
        }else{
           $this->system->addError(HEURIST_INVALID_REQUEST, "Can not find configuration for entity ".@$data['entity']);     
        }
    }
    
    private function _readFields($fields){

        foreach($fields as $field){
            
            if(is_array(@$field['children']) && count($field['children'])>0){
                $this->_readFields($field['children']);
                
            }else{
                if(@$field['dtFields']['dty_Role']=='virtual') continue; //skip
                $this->fields[ $field['dtID'] ] = $field['dtFields'];
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
              $filename = $info->getFilename();
              if ($filename==$tempfile) {
                  $pathname = $info->getPathname();
                  $extension = $info->getExtension()
                  $new_name = $pathname.$recID.'.'.$extension;
error_log($filepath.' to '.$new_name);                  
                  rename ($filepath, $new_name);
              }
        }        
    }
        
    
}  
?>
