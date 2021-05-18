<?php

    /**
    * db access to Heurist_DBs_index.sysIdentifications and sysUsers tables
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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

        if(true){  
            $order   = array();
            $records = array();
            
            if(true){ //}$this->data['details']=='ids' || !@$this->data['sys_Database']){
            
                $query = "show databases where `database` like 'hdb_%'";
                $res = $this->system->get_mysqli()->query($query);

                $query = array();
                while ($row = $res->fetch_row()) {
                    $database  = $row[0];
                    $test = strpos($database, HEURIST_DB_PREFIX);
                    if ($test === 0){
                        //$query[] = "SELECT '".$database."' as sys_Database, sys_dbRegisteredID, sys_dbName FROM `"
                        //        .$database."`.sysIdentification";
                        $records[$database] = array($database);
                        $order[] = $database;
                    }
                }
                
                $res->close();
            
                return array('queryid'=>@$this->data['request_id'],  //query unqiue id set in doRequest
                                'entityName'=>$this->config['entityName'],
                                'pageno'=>@$this->data['pageno'],  //page number to sync
                                'offset'=>@$this->data['offset'],
                                'count'=>count($records),
                                'reccount'=>count($records),
                                'records'=>$records,
                                
                                'order'=>$order,
                                'fields'=>array('sys_Database')//,'sys_dbRegisteredID','sys_dbName'),
                            );
                                
            }else{
            
                $databases = $this->data['sys_Database'];
                $query = array();
                foreach ($databases as $database) {
                        $query[] = "SELECT '".$database."' as sys_Database, sys_dbRegisteredID, sys_dbName FROM `"
                                .$database."`.sysIdentification";
                        $order[] = $database;
                }
                //natcasesort($order);
                
                $query = implode(' UNION ', $query);
                $res = $this->system->get_mysqli()->query($query);
                while ($row = $res->fetch_row()) {
                    $records[$row[0]] = $row;
                }
                
        
                return array(
                            'queryid'=>@$this->data['request_id'],  //query unqiue id set in doRequest
                            'pageno'=>@$this->data['pageno'],  //page number to sync
                            'offset'=>@$this->data['offset'],
                            'count'=>count($records),
                            'reccount'=>count($records),
                            'fields'=>array('sys_Database','sys_dbRegisteredID','sys_dbName'),
                            'records'=>$records,
                            'order'=>$order,
                            'entityName'=>$this->config['entityName']);
            }
            //sys_Database, sys_dbRegisteredID, sys_dbName, sys_AllowRegistration, sus_Role, sus_Count
        
        }else {//from index database
        
            if($current_user_email){    //for specific user (presents in user table)
                //compose query
                $query = 'SELECT SQL_CALC_FOUND_ROWS  '
                .'sys_Database, sys_dbRegisteredID, '//'concat(sys_dbVersion,".",sys_dbSubVersion,".",sys_dbSubSubVersion) as sys_Version, '
                .'sys_dbName, sys_AllowRegistration, sus_Role, ' //sys_dbOwner, sys_dbDescription, 
                .'(select count(distinct sus_Email) from Heurist_DBs_index.sysUsers where sys_Database=sus_Database) as sus_Count '        
                .' from Heurist_DBs_index.sysIdentifications '
                .' LEFT JOIN Heurist_DBs_index.sysUsers on sys_Database=sus_Database and sus_Email="'.$current_user_email.'"';
            }else{  //all databases
                $query = 'SELECT SQL_CALC_FOUND_ROWS  '
                .'sys_Database, sys_dbRegisteredID, '//'concat(sys_dbVersion,".",sys_dbSubVersion,".",sys_dbSubSubVersion) as sys_Version, '
                .'sys_dbName, sys_AllowRegistration, "" as sus_Role, ' //sys_dbOwner, sys_dbDescription, 
                .'0 as sus_Count '        
                .' from Heurist_DBs_index.sysIdentifications ';
            }
        
        
            /*
             if(count($where)>0){
                $query = $query.' WHERE '.implode(' AND ',$where);
             }
             $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();
             */

            $this->searchMgr = new DbEntitySearch( $this->system, $this->fields );
            $db_list = $this->searchMgr->execute($query, false, $this->config['entityName']);
            
            if(false){ //validation that database (in central index) does really exsist
                
                $query = "show databases";
                $res = $mysqli->query($query);
                $databases = array();
                while ($row = $res->fetch_row()) {
                    $database  = $row[0];
                    if (strpos($database, HEURIST_DB_PREFIX) === 0){
                        array_push($databases, $database);
                    }
                }
                $res->close();
                
                $order = $db_list['order'];
                foreach ($order as $idx => $database){
                    if(!in_array($database, $databases)){
                        //remove from records
                        $db_list['records'][$database] = null;
                        unset($db_list['records'][$database]);
                        //remove from order
                        $db_list['order'][$idx] = null;
                        unset($db_list['order'][$idx]);
                        //remove from index database
                        $mysqli->query('DELETE FROM Heurist_DBs_index.sysUsers WHERE sus_Database=`'.$database.'`');
                        $mysqli->query('DELETE FROM Heurist_DBs_index.sysIdentifications WHERE sys_Database=`'.$database.'`');
                    }
                }
            
            }
            
            return $db_list;
        
        }//from index database
    }

    //
    // deletion and not allowed
    //
    public function delete($disable_foreign_checks = false){
    }
    public function save(){
    }
    
}
?>
