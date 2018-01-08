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
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');


class DbDefRecTypes extends DbEntityBase 
{
    //remove
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
       
       
       $this->config = array(); 
       $this->fields = array( 
    'rty_ID'=>'ids',    //ids
    'rty_Name'=>63,     //title
   
    'rty_OrderInGroup'=>'int',
    'rty_Description'=>5000,
    'rty_TitleMask'=>500,
    'rty_Plural'=>63,
    'rty_Status'=>array('reserved','approved','pending','open'),
    'rty_OriginatingDBID'=>'int',
    'rty_NameInOriginatingDB'=>63,
    'rty_IDInOriginatingDB'=>'int',
    'rty_NonOwnerVisibility'=>array('hidden','viewable','public','pending'),
    'rty_ShowInLists'=>'bool2',
    'rty_RecTypeGroupID'=>'ids',
    'rty_RecTypeModelIDs'=>63,
    'rty_FlagAsFieldset'=>'bool2',
    'rty_ReferenceURL'=>250,
    'rty_AlternativeRecEditor'=>63,
    'rty_Type'=>array('normal','relationship','dummy'),
    'rty_ShowURLOnEditForm'=>'bool2',
    'rty_ShowDescriptionOnEditForm'=>'bool2',
    'rty_Modified'=>'date',
    'rty_LocallyModified'=>'bool2'
    );
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
        $this->searchMgr = new dbEntitySearch( $this->system, DbDefRecTypes::$fields);

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

        //compose WHERE 
        $where = array();    
        
        $pred = $this->searchMgr->getPredicate('rty_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rty_Name');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('rty_Status');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rty_Modified');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rty_RecTypeGroupID');
        if($pred!=null) array_push($where, $pred);
        

       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'rty_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'rty_ID,rty_Name';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'rty_ID,rty_Name,rty_ShowInLists,rty_Description,rty_Status,rty_RecTypeGroupID';
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = implode(',', DbDefRecTypes::$fields );
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@DbDefRecTypes::$fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }            
        }

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('rty_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'rty_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details']).' FROM defRecTypes';
                
         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();
        
//error_log($query);     

        $res = $this->searchMgr->execute($query, $is_ids_only);
        return $res;

    }
    
    //
    //
    //
    public function counts(){

        $res = null;
                
        if(@$this->data['mode']=='record_count'){
            $query = 'SELECT d.rty_ID, count(r.rec_ID) FROM defRecTypes d LEFT OUTER JOIN Records r ON r.rec_RectypeID=d.rty_ID '
            .' GROUP BY d.rty_ID';
          
           $res = mysql__select_assoc2($this->system->get_mysqli(), $query);
        }
        
        return $res;
    }
     
    
}
?>
