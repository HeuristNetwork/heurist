<?php

    /**
    * db access to Heurist_DBs_index.sysIdentifications and sysUsers tables
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


class DbSysDatabases extends DbEntityBase 
{
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
        
        //compose WHERE 
        $where = array(); 
        $current_user_email = null;
        
        $mysqli = $this->system->get_mysqli(); 
        $user = user_getById($mysqli, $this->system->get_user_id());
        if($user){
            $current_user_email = $user['ugr_eMail'];
        }

        if(false){  
            $order   = array();
            $records = array();
            
            $query = "show databases";
            $res = $this->system->get_mysqli()->query($query);
            $result = array();
            while ($row = $res->fetch_row()) {
                $database  = $row[0];
                $test = strpos($database, HEURIST_DB_PREFIX);
                if ($test === 0){
                    $records[$database] = array($database);
                    //array_push($order, $database);
                    $order[] = $database;
                }
            }
            $res->close();
            //natcasesort($order);
        
            return array(
                            'queryid'=>@$this->data['request_id'],  //query unqiue id set in doRequest
                            'pageno'=>@$this->data['pageno'],  //page number to sync
                            'offset'=>@$this->data['offset'],
                            'count'=>count($records),
                            'reccount'=>count($records),
                            'fields'=>array('sys_Database'),
                            'records'=>$records,
                            'order'=>$order,
                            'entityName'=>'sysDatabases');
        
        }else {
        
        if($current_user_email){    
            //compose query
            $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '
            .'sys_Database, sys_dbRegisteredID, '//'concat(sys_dbVersion,".",sys_dbSubVersion,".",sys_dbSubSubVersion) as sys_Version, '
            .'sys_dbName, sys_AllowRegistration, sus_Role, ' //sys_dbOwner, sys_dbDescription, 
            .'(select count(distinct sus_Email) from Heurist_DBs_index.sysUsers where sys_Database=sus_Database) as sus_Count '        
            .' from Heurist_DBs_index.sysIdentifications '
            .' LEFT JOIN Heurist_DBs_index.sysUsers on sys_Database=sus_Database and sus_Email="'.$current_user_email.'"';
        }else{
            $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '
            .'sys_Database, sys_dbRegisteredID, '//'concat(sys_dbVersion,".",sys_dbSubVersion,".",sys_dbSubSubVersion) as sys_Version, '
            .'sys_dbName, sys_AllowRegistration, "" as sus_Role, ' //sys_dbOwner, sys_dbDescription, 
            .'0 as sus_Count '        
            .' from Heurist_DBs_index.sysIdentifications ';
        }
        
        
        /*
         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();
         */
//error_log($query);     

        $this->searchMgr = new DbEntitySearch( $this->system, $this->fields );
        $res = $this->searchMgr->execute($query, false, $this->config['entityName']);
        return $res;
        
        }
    }
    
    //
    // nothing to save/delete in central index
    // all operations are performed by database indexes
    //
    public function delete(){
    }
    public function save(){    
    }

}
?>
