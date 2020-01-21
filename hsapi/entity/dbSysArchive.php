<?php

    /**
    * db access to sysArchive table 
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');


class DbSysArchive extends DbEntityBase
{

    /**
    *  search groups
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

        if(parent::search()===false){
            return false;   
        }

        $needCheck = false;
        $is_ids_only = false;

        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('arc_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('arc_PriKey');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('arc_ChangedByUGrpID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('arc_TimeOfChange');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('arc_ContentType');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('arc_Table');
        if($pred!=null) array_push($where, $pred);
        

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'arc_ID';
            $is_ids_only = true;

        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'arc_ID, arc_DataBeforeChange';

        }else if(@$this->data['details']=='list')
        {
            $this->data['details'] = 'arc_ID, arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_TimeOfChange, arc_ContentType';
            
        }else if(@$this->data['details']=='full')
        {
            $this->data['details'] = 'arc_ID, arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_TimeOfChange, arc_DataBeforeChange, arc_ContentType';
            
        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //user specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);

        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        //compose ORDER BY 
        $order = array();

        $value = @$this->data['sort:arc_TimeOfChange'];
        if($value!=null){
            array_push($order, 'arc_TimeOfChange '.($value>0?'ASC':'DESC'));
        }  

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

        if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
        }
        if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
        }

        $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $calculatedFields = null;

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        if(@$this->data['convert']=='records_list'){
            $result = $this->convertToHeuristRecords($result, 'records_list');
        }
        
        
        return $result;
    }
    
    //
    // extract data from arc_DataBeforeChange and converts resultset to Heurist records
    //    
    private function convertToHeuristRecords($response, $details){
        
        if(is_array($response) && $response['reccount']>0){
            
            $rectypes = array();
            $records = array();
            $order = array();
            $csv_delimiter = "\t";
            $csv_enclosure = '|';//'@';
            
            if($details=='records_list'){ //returns fields suitable for list only
                //0,1,2,3,4,6,11,12
                $fields = 'rec_ID,rec_URL,rec_Added,rec_Modified,rec_Title,rec_RecTypeID,'
                    .'rec_OwnerUGrpID, rec_NonOwnerVisibility,'
                    .'arc_ID,arc_ChangedByUGrpID,arc_TimeOfChange,arc_ContentType';
            }else{
                $fields = 'rec_ID,rec_URL,rec_Added,rec_Modified,rec_Title,rec_ScratchPad,rec_RecTypeID,rec_AddedByUGrpID,'
                .'rec_AddedByImport,rec_Popularity,rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URLLastVerified,'
                .'rec_URLErrorMessage,rec_URLExtensionForMimeType,rec_Hash';
            }
            $fields = explode(',',$fields);
            
            $idx_data = array_search('arc_DataBeforeChange', $response['fields']);
            
            
            foreach ($response['records'] as $arc_ID => $arcrow){
                
                $arc = $arcrow[$idx_data];
                //$arc = substr(str_replace('","', "\t", $arc),1);
                $arc = str_replace('NULL','""', $arc);
                $arc = str_replace('","', "|\t|", $arc);
                /*
                $arc = str_replace('",NULL', "@\tNULL", $arc);
                $arc = str_replace('NULL,"', "NULL\t@", $arc);
                $arc = str_replace('NULL,NULL', "NULL\tNULL", $arc);
                */
                $arc = '|'.substr($arc, 1);
                $rec = str_getcsv($arc, $csv_delimiter, $csv_enclosure);
                /*
                if(count($rec)>17){
                    $rec2 = array();
                    $cnt = -1;
                    foreach ($rec as $fld){
                        if(strpos($fld,'"')===strlen($fld)-1){
                            $rec2[$cnt] = $rec2[$cnt].$fld;
                        }else{
                            $rec2[] = $fld;
                            $cnt++;
                        }
                    }
                    $rec = $rec2;
                }*/
                
                $rec_ID = $arc_ID;
                $rec_RecTypeID = $rec[6];
                
                array_push($order, $arc_ID);
                
                if($details=='records_list'){
                    
                    $records[$arc_ID] = array($rec[0],$rec[1],$rec[2],$rec[3],$rec[4],$rec[6],$rec[11],$rec[12],
                                    $arc_ID,$arcrow[3],$arcrow[6],$arcrow[8]);
                    
                }else{
                    $records[$arc_ID] = $rec;
                }
                
                if(!in_array($rec_RecTypeID, $rectypes))
                    array_push($rectypes, $rec_RecTypeID);
            }
            
/*            
"212","","2019-12-21 09:49:35","2019-12-22 13:26:23","Note2 [ Alice Lee Roosevelt Longworth, 21 Dec 2019 ]","", rt"3","2","0","0",ft"0",
own"0","viewable",NULL,NULL,NULL,NULL
*/            
            
            $response['fields'] = $fields;
            $response['records'] = $records;
            $response['order'] = $order;
            $response['entityName']='Records';
            $response['rectypes'] = $rectypes;
            
            return $response;            
        }else{
            return $response;
        }
        
    }
        
    //
    // this table is updated via triggers only 
    //
    public function save(){
        return false;
    }     
    
    //
    // delete group
    //
    public function delete(){
        return false;
    }

}
?>
