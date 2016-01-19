<?php

    /**
    * Library to update records details in batch
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

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/db_tags.php');

class DbSysUGrps
{
    private $system;  
    
    /*  
    *       recIDs - list of records IDS to be processed
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value,  rVal - replace value
    *       for delete: sVal  
    *       tag  = 0|1  - add system tag to mark processed records
    */    
    private $data;  

    //@todo???? load this mapping dynamically fromn db
    // to validate fieldnames and value
    private static $fields = array( 
   'ugr_ID'=>'ids',
   'ugr_Type'=>array('user','workgroup','ugradclass'),
   'ugr_Name'=>63,
   'ugr_LongName'=>128,
   'ugr_Description'=>1000,
   'ugr_Password'=>40,
   'ugr_eMail'=>100,
   'ugr_FirstName'=>40,
   'ugr_LastName'=>63,
   'ugr_Department'=>120,
   'ugr_Organisation'=>120,
   'ugr_City'=>63,
   'ugr_State'=>40,
   'ugr_Postcode'=>20,
   'ugr_Interests'=>255,
   'ugr_Enabled'=>array('y','n'),
   'ugl_Role'=>array('admin','member'));

    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }
    
    //
    //
    //
    private function _validateIds($values, $title){
    
        if(@$values!=null){
            //array of integer or integer
            if(!is_array($values)){
                $values = array($values);
            }
            foreach($values as $val){  //intval()
                if(!(is_numeric($val)&& $val)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter $title: $val");
                    return false;
                }
            }
        }
        return true;        
    }

    private function _validateEnum($value, $title, $enums){
    
        if(@$value){
            //array of integer or integer
            if(array_search($value, $enums, true)===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter $title: $value");
                return false;
            }
        }
        return true;        
    }

    
    private function _validateParams(){
        
        $res = $this->_validateIds(@$this->data['ugr_ID'], 'user/group IDs');
        if(is_bool($res)){
            if(!$res) return false;
        }else{
            $this->data['ugr_ID'] = $res;
        }
        
        if(!$this->_validateEnum(@$this->data['ugr_Type'], 'user/group type', array('workgroup','user') )){
            return false;
        }
        
        if(@$this->data['ugr_Enabled']!=null){
            if(is_bool($this->data['ugr_Enabled'])){
                $this->data['ugr_Enabled'] = $this->data['ugr_Enabled']?'y':'n';
            }else if(is_numeric($this->data['ugr_Enabled'])){
                $this->data['ugr_Enabled'] = $this->data['ugr_Enabled']==1?'y':'n';
            }
            if(!($this->data['ugr_Enabled']=='y' || $this->data['ugr_Enabled']=='n')){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter user/group enabled: ".$this->data['ugr_Enabled']);
                return false;
            }
        }
        
        $res = $this->_validateIds(@$this->data['ugl_GroupID'], 'user/group IDs');
        if(is_bool($res)){
            if(!$res) return false;
        }else{
            $this->data['ugl_GroupID'] = $res;
        }

        $res = $this->_validateIds(@$this->data['ugl_UserID'], 'user/group IDs');
        if(is_bool($res)){
            if(!$res) return false;
        }else{
            $this->data['ugl_UserID'] = $res;
        }
        
        if(!$this->_validateEnum(@$this->data['ugl_Role'], 'user role', array('admin','member') )){
            return false;
        }

        
        return true;
    }
    
    function _getOffset(){
        if(@$this->data['offset']){
            $offset = intval($this->data['offset']);
            if($offset>=0){
                return ' OFFSET '.$offset;
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter offset: ".$this->data['offset']);
                return false;
            }
        }
    }
    function _getLimit(){
        if(@$this->data['limit']){
            $limit = intval($this->data['limit']);
            if($offset>=0){
                return ' LIMIT '.$limit;
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter limit: ".$this->data['limit']);
                return false;
            }
        }
    }
        
    /**
    *  search user or/and groups
    * 
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
    * 
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    * 
    */
    public function search(){
        
//error_log(print_r($this->data,true));        

        /*
        if (!(@$this->data['val'] || @$this->data['geo'] || @$this->data['ulfID'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }
        */
        if(!$this->_validateParams()){
            return false;
        }
        $isJoin = false;
        $joinSysUsrGrpLinks = '';

        $mysqli = $this->system->get_mysqli();

        $where = array('(ugr_ID>0)');    
        
        //compose WHERE 
        if(@$this->data['ugr_ID']){
            if(count($this->data['ugr_ID'])>1){
                array_push($where, '(ugr_ID in ('.implode(',', $this->data['ugr_ID']).'))');
            }else{
                array_push($where, '(ugr_ID = '. $this->data['ugr_ID'][0].')');
            }
        }
        
        if(@$this->data['ugr_Name']){
            $val = $this->data['ugr_Name'];
            //@todo - extract first charcter to determine comparison opeartor = or like
            array_push($where, "(ugr_Name like '%".$mysqli->real_escape_string($val)."%'");
        }

        if(@$this->data['ugr_Enabled']){
            array_push($where, "(ugr_Enabled = '". $this->data['ugr_Enabled']."')");
        }
       
        if(@$this->data['ugr_Type']){
            array_push($where, '(ugr_Type = "'. $this->data['ugr_Type'].'")');

            //find group where this user is member or admin
            if(@$this->data['ugl_UserID']){
                if(count($this->data['ugl_UserID'])>1){
                    array_push($where, '(sysUsrGrpLinks.ugl_UserID in ('.implode(',', $this->data['ugl_UserID']).'))');
                }else{
                    array_push($where, '(sysUsrGrpLinks.ugl_UserID = '. $this->data['ugl_UserID'][0].')');
                }
                array_push($where, "(sysUsrGrpLinks.ugl_GroupID = ugr_ID)");
                $isJoin = true;
            }
            //find users for given groups
            if(@$this->data['ugl_GroupID']){
                
                if(count($this->data['ugl_GroupID'])>1){
                    array_push($where, '(sysUsrGrpLinks.ugl_GroupID in ('.implode(',', $this->data['ugl_GroupID']).'))');
                }else{
                    array_push($where, '(sysUsrGrpLinks.ugl_GroupID = '. $this->data['ugl_GroupID'][0].')');
                }
                array_push($where, "(sysUsrGrpLinks.ugl_UserID = ugr_ID)");
                $isJoin = true;
            }
            if(@$this->data['ugl_Role']){
                array_push($where, "(sysUsrGrpLinks.ugl_Role = '". $this->data['ugl_Role']."')");
                $isJoin = true;
            }
            
            if($isJoin){
                $joinSysUsrGrpLinks = ', sysUsrGrpLinks';
            }
        }

       
        //compose SELECT
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ugr_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'ugr_ID,ugr_Type,ugr_Name,ugr_Description,ugr_Enabled,ugr_Organisation';
            if($isJoin) $this->data['details'] .= ',ugl_Role';
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = implode(',',  $isJoin
                        ?DbSysUGrps::$fields 
                        : array_slice(DbSysUGrps::$fields,0,count(DbSysUGrps::$fields)-1) );
        }
        
        if(!is_array($this->data['details'])){
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@DbSysUGrps::$fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }            
        }
        //exclude ugr_Password    
        $idx = array_search('ugr_Password', $this->data['details']);
        if($idx>=0){
            unset($this->data['details'][$idx]);
        }
        //ID field is mandatory and MUST be first in the list
        $idx = array_search('ugr_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'ugr_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details']).' FROM sysUGrps'
                .$joinSysUsrGrpLinks;
         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->_getOffset()
                        .$this->_getLimit();
        
error_log($query);        
        
        $res = $mysqli->query($query);
        if (!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'UserGroups search error', $mysqli->error);
            return false;
        }else{

            $fres = $mysqli->query('select found_rows()');
            if (!$fres)     {
                $this->system->addError(HEURIST_DB_ERROR, 'UserGroups search error (retrieving number of records)', $mysqli->error);
                return false;
            }else{

                $total_count_rows = $fres->fetch_row();
                $total_count_rows = $total_count_rows[0];
                $fres->close();

                if($is_ids_only){ //------------------------  LOAD and RETURN only IDS

                    $records = array();

                    while ($row = $res->fetch_row()) //&& (count($records)<$chunk_size)  //3000 max allowed chunk
                    {
                        array_push($records, (int)$row[0]);
                    }
                    $res->close();

                    $response = array(
                                'queryid'=>@$this->data['request_id'],  //query unqiue id
                                'offset'=>@$this->data['offset'],
                                'count'=>$total_count_rows,
                                'reccount'=>count($records),
                                'records'=>$records);

                }else{ //----------------------------------


                    // read all field names
                    $_flds =  $res->fetch_fields();
                    $fields = array();
                    foreach($_flds as $fld){
                        array_push($fields, $fld->name);
                    }

                    $rectype_structures  = array();
                    $rectypes = array();
                    $records = array();
                    $order = array();

                    // load all records
                    while ($row = $res->fetch_row())// && (count($records)<$chunk_size) ) {  //3000 maxim allowed chunk
                    {
                        $records[$row[0]] = $row;
                        array_push($order, $row[0]);
                    }
                    $res->close();

                    $response = array(
                            'queryid'=>@$this->data['request_id'],  //query unqiue id
                            'offset'=>@$this->data['offset'],
                            'count'=>$total_count_rows,
                            'reccount'=>count($records),
                            'fields'=>$fields,
                            'records'=>$records,
                            'order'=>$order);

                }//$is_ids_only
            }

        }        
        
        return $response;
    }

     
    
}
?>
