<?php

    /**
    * db access to sysImportSessions table
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


class DbSysImportSessions extends DbEntityBase
{
    private $is_table_exists = true;  
    
    
    public function init(){
        
        $mysqli = $this->system->get_mysqli();
    
        $query = "CREATE TABLE IF NOT EXISTS `sysImportSessions` (
        `imp_ID` int(11) unsigned NOT NULL auto_increment,
        `ugr_ID` int(11) unsigned NOT NULL default 0,
        `imp_table` varchar(255) NOT NULL default '',
        `imp_session` text,
        PRIMARY KEY  (`imp_ID`))";
        if (!$mysqli->query($query)) {
            $this->is_table_exists = false;
        }
        
    }

    /**
    */
    public function isvalid(){
        return $this->is_table_exists && parent::isvalid();
    }
    
    
    /**
    *  search import sessions
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
        $this->searchMgr = new dbEntitySearch( $this->system, $this->fields);

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
        
        $pred = $this->searchMgr->getPredicate('imp_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) array_push($where, $pred);

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'imp_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'imp_ID,imp_table';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'imp_ID,imp_table,imp_session';
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = implode(',', $this->fields );
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@$this->fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }            
        }

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('imp_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'imp_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details']).' FROM sysImportSessions';

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();
        
//error_log($query);     

        $res = $this->searchMgr->execute($query, $is_ids_only, 'sysImportSessions');
        return $res;

    }
    
    //
    //
    //
    public function save(){
        
        $ret = parent::save();

        if($ret!==false){
            /* treat thumbnail image
            foreach($this->records as $record){
                if(in_array(@$record['trm_ID'], $ret)){
                    $thumb_file_name = @$record['trm_Thumb'];
            
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }
                }
            }*/
        }
        
        return $ret;
    } 


    //
    //
    //
    public function delete(){
        
        if(!$this->_validatePermission()){
            return false;
        }
        
        $rec_ID = $this->data['recID'];
        $rec_ID = intval(@$rec_ID);
        if($rec_ID>0){        
            $where = " where imp_id=".$rec_ID;
        }else{
            $where = " where imp_id>0";
        }
        
        $mysqli = $this->system->get_mysqli();

        $res = mysql__select_all($mysqli,
                "select imp_id, imp_session from sysImportSessions".$where);

        if(!$res){
            $this->system->addError(HEURIST_NOT_FOUND, 
                "No data found. Cannot delete from import sessions table");
            return false;
        }

        //drop import data
        foreach($res as $id => $session){

            $session = json_decode($session, true);
            $query = "drop table IF EXISTS ".$session['import_table'];

            if (!$mysqli->query($query)) {
                $this->system->addError(HEURIST_DB_ERROR, 
                        'Cannot drop import session table: '.$session['import_table'].' '.$mysqli->error);
                return false;
            }
        }

        if (!$mysqli->query("delete from sysImportSessions ".$where)) {
                $this->system->addError(HEURIST_DB_ERROR, 
                        'Cannot delete data from list of imported files', $mysqli->error);
              return false;
        }else{
              return true;
        }
    }

    
}
?>
