<?php

    /**
    * db access to sysUGrpps table
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
require_once (dirname(__FILE__).'/dbEntitySearch.php');


class DbSysUGrps
{
    private $system;  
    
    /*  
     parametrs
    
     list of fields to search or update
     
    
    */    
    private $data;  

    //@todo???? load this mapping dynamically fromn db
    // to validate fieldnames and value
    
    
    //data types: ids, int, float, date, bool, enum
    private static $fields = array( 
   'ugr_ID'=>'ids',    //ids
   'ugr_Type'=>array('user','workgroup','ugradclass'), //t
   'ugr_Name'=>63,     //title
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
   'ugr_Enabled'=>'bool',
   'ugr_Modified'=>'date',    //date, after, before
   'ugl_Role'=>array('admin','member'),
   'ugl_UserID'=>'ids',
   'ugl_GroupID'=>'ids' );
   
    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }
    

    /**
    *  search user or/and groups
    * 
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
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
    *  @todo overwrite
    */
    public function search(){
        
//error_log(print_r($this->data,true));        
        $this->searchMgr = new dbEntitySearch( $this->system, DbSysUGrps::$fields);

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
        $res = $this->searchMgr->validateParams( $this->data );
        if(!is_bool($res)){
            $this->data = $res;
        }else{
            if(!$res) return false;        
        }        
        
        
        $isJoin = false;
        $joinSysUsrGrpLinks = '';

        //compose WHERE 
        $where = array('(ugr_ID>0)');    
        
        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ugr_Name');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('ugr_Enabled');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ugr_Modified');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ugr_Type');
        if($pred!=null) {
            array_push($where, $pred);
        
            //find group where this user is member or admin
            $pred = $this->searchMgr->getPredicate('ugl_UserID');
            if($pred!=null && $this->data['ugr_Type']=='workgroup') {
                array_push($where, $pred);
                $isJoin = true;
            }
            //find users for given groups
            $pred = $this->searchMgr->getPredicate('ugl_GroupID');
            if($pred!=null && $this->data['ugr_Type']=='user') {
                array_push($where, $pred);
                $isJoin = true;
            }
            $pred = $this->searchMgr->getPredicate('ugl_Role');
            if($pred!=null) {
                array_push($where, $pred);
                $isJoin = true;
            }
            
            if($isJoin){
                if($this->data['ugr_Type']=='user'){
                    array_push($where, "(sysUsrGrpLinks.ugl_UserID = ugr_ID)");
                }else{
                    array_push($where, "(sysUsrGrpLinks.ugl_GroupID = ugr_ID)");
                }
                $joinSysUsrGrpLinks = ', sysUsrGrpLinks';
            }
        }

       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ugr_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'ugr_ID,ugr_Type,ugr_Name,ugr_FirstName,ugr_LastName,ugr_Description,ugr_Enabled,ugr_Organisation';
            if($isJoin) $this->data['details'] .= ',ugl_Role';
            
        }else if(@$this->data['details']=='full'){

            //remove ugl_XXX fields from the end of fields array
            $this->data['details'] = implode(',',  $isJoin
                        ? array_slice(DbSysUGrps::$fields,0,count(DbSysUGrps::$fields)-2)
                        : array_slice(DbSysUGrps::$fields,0,count(DbSysUGrps::$fields)-3) );
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
        //exclude ugr_Password   - DO NOT SEND to client side 
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
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();
        
        $res = $this->searchMgr->execute($query, $is_ids_only);
        return $res;
    }
    
}
?>
