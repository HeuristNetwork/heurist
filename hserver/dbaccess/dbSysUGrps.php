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

class DbRecDetails
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

    /*
    * records to be processed
    */
    private $recIDs;  
    
    /*
    * distinct array of record types
    */
    private $rtyIDs;
    
    /*
    passed     _tag _error
    noaccess    
    processed - no rights to edit
    undefined - field definition not found (add) or search value not found (edit,delete)
    limitted  
    errors    - sql error on search or updata
    */
    private $result_data = array();
    
    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }
    
    //
    //
    //
    private function _validateParamsAndCounts()
    {
        if ( $this->system->get_user_id()<1 ) {
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }
        
        $rtyID = @$this->data['rtyID'];
        $dtyID = $this->data['dtyID'];
        
        if (!( @$this->data['recIDs'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed: records");
            return false;
        }
        
        if ($rtyID && !(ctype_digit($rtyID) && $rtyID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter record type id $rtyID");
            return false;
        }

        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter detail type id $dtyID");
            return false;
        }

        $mysqli = $this->system->get_mysqli();

        //normalize recIDs to an array for code below
        $recIDs = $this->data['recIDs'];
        if ($recIDs && ! is_array($recIDs)){
            $recIDs = array($recIDs);
        }
        
        $passedRecIDCnt = count(@$recIDs);
        
        if ($passedRecIDCnt>0) {//check editable access for passed records
            if($rtyID){ //filter for record type
                $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in ("
                                    .implode(",",$recIDs).")");
                $passedRecIDCnt = count(@$recIDs);
            }
            if($passedRecIDCnt>0){
                //exclude records user has no right to edit
                $this->recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in (".implode(",",$recIDs).") and rec_OwnerUGrpID in (0,".join(",",$this->system->get_user_group_ids()).")");
                $inAccessibleRecCnt = $passedRecIDCnt - count(@$this->recIDs);
            }
        }

        $this->result_data = array('passed'=> $passedRecIDCnt>0?$passedRecIDCnt:0,
                                   'noaccess'=> @$inAccessibleRecCnt ?$inAccessibleRecCnt :0);

        if (count(@$this->recIDs)==0){
            $this->result_data['processed'] = 0;
            return true;
        }
        
        if($rtyID){
            $this->rtyIDs = array($rtyID);
        }else {
            $this->rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in (".implode(",",$this->recIDs).")");
        }
    
        return true;
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
    */
    public function search(){

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

        $mysqli = $this->system->get_mysqli();

        $query = array();    
        
        if($this->data['ugr_ID']){
            
        }
        
        
        return $this->result_data;        
    }

     
    
}
?>
