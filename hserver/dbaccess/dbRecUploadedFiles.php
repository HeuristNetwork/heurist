<?php

    /**
    * db access to defTerms table
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
require_once (dirname(__FILE__).'/db_files.php');


class DbRecUploadedFiles extends DbEntityBase
{
    /*
    'trm_OriginatingDBID'=>'int',
    'trm_NameInOriginatingDB'=>63,
    'trm_IDInOriginatingDB'=>'int',

    'trm_AddedByImport'=>'bool2',
    'trm_IsLocalExtension'=>'bool2',

    'trm_OntID'=>'int',
    'trm_ChildCount'=>'int',
    
    'trm_Depth'=>'int',
    'trm_LocallyModified'=>'bool2',
    */

    /**
    *  search uploaded fils
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
        
        $this->searchMgr = new dbEntitySearch( $this->system, $this->fields);

        $res = $this->searchMgr->validateParams( $this->data );
        if(!is_bool($res)){
            $this->data = $res;
        }else{
            if(!$res) return false;        
        }        

        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);  //'recUploadedFiles'
        
        $pred = $this->searchMgr->getPredicate('ulf_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ulf_OrigFileName');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ulf_ExternalFileReference');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('ulf_FilePath');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ulf_Modified');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('ulf_UploaderUGrpID');
        if($pred!=null) array_push($where, $pred);
        

        $value = @$this->data['ulf_Parameters'];
        if(!($value==null || $value=='any')){
            array_push($where, "(fxm_Extension=ulf_MimeExt) AND (fxm_MimeType like '$value%')");
            array_push($from_table, 'defFileExtToMimetype');
        }
        //----- order by ------------
        
        //compose ORDER BY 
        $order = array();
        
        //$pred = $this->searchMgr->getSortPredicate('ulf_UploaderUGrpID');
        //if($pred!=null) array_push($order, $pred);
        $value = @$this->data['ulf_Added'];
        if($value!=null){
            array_push($order, 'ulf_Added '.($value>1?'ASC':'DESC'));
        }
        
        
        $needRelations = false;

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ulf_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName';
            
        }else if(@$this->data['details']=='list'){

            //$this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference, ulf_ObfuscatedFileID';
            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_FilePath';
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_Description,ulf_FileSizeKB,ulf_MimeExt,ulf_Added,ulf_UploaderUGrpID';
            //$this->data['details'] = implode(',', $this->fields );
            $needRelations = true;
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
        $idx = array_search('ulf_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'ulf_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
         }
         
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();

        $calculatedFields = null;
        /*compose thumbnail and url fields
        $calculatedFields = function ($fields, $row) {
            $idx = array_search('ulf_OrigFileName', $fields);
            if($idx!==false){
                $row[$idx] = 'AAAA'.$row[$idx]; 
            }
            return $row;
        };
        */
        
        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['tableName'], $calculatedFields);
        
        //find related records 
        if($needRelations && !(is_bool($result) && $result==false) && count($result['order'])>0 ){
            
            $mysqli = $this->system->get_mysqli();
        
            //find all reverse related records
            $query = 'SELECT dtl_UploadedFileID, dtl_RecID, dtl_ID, rec_Title, rec_RecTypeID FROM recDetails, Records WHERE dtl_UploadedFileID in ('.implode(',',$result['order']).') and dtl_RecID=rec_ID';

            $direct = array();
            $headers = array();
            
            $res = $mysqli->query($query);
            if (!$res){
                $this->system->addError(HEURIST_DB_ERROR, "Search query error for records that use files. Query ".$query
                                        , $mysqli->error);
                return false;
            }else{
                    while ($row = $res->fetch_row()) {
                        $relation = new stdClass();
                        $relation->recID = intval($row[0]);  //file id 
                        $relation->targetID = intval($row[1]);  //record id
                        $relation->dtID  = intval($row[2]);
                        array_push($direct, $relation);
                        $headers[$row[1]] = array($row[3], $row[4]);   
                    }
                    $res->close();
            }            
            
            $result['relations']  = array("direct"=>$direct, "headers"=>$headers);
        
        }//end find related records 
        
        return $result;

    }
    
    
    //
    //
    //    
    protected function _validatePermission(){
        
        if($this->system->is_dbowner() || $this->system->is_member($this->data['fields']['ulf_UploaderUGrpID'])){
            return true;
        }else{
            $this->system->addError(HEURIST_REQUEST_DENIED, 'File uploaded by other user. Cannot modify file info');
            return false;
        }
    }
    
    protected function _validateValues(){
        
        $ret = parent::_validateValues();
        
        if($ret){
            
            $fieldvalues = $this->data['fields'];
            
            /*
            if(!@$fieldvalues['ulf_OrigFileName']){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Name of file not defined");
                return false;
            }
            if (!@$fieldvalues['ulf_ExternalFileReference']){
                
                if(!(@$fieldvalues['ulf_FilePath'] && @$fieldvalues['ulf_FileName'])){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Path or link to file not defined");
                    return false;
                }else{
                }
            }
            */
            
            $mimetypeExt = strtolower($fieldvalues['ulf_MimeExt']);
            $mimeType = mysql__select_value($this->system->get_mysqli(), 
                    'select fxm_Mimetype from defFileExtToMimetype where fxm_Extension="'.addslashes($mimetypeExt).'"');

            if(!$mimeType){
                    $this->system->addError(HEURIST_INVALID_REQUEST, 
'Error inserting file metadata or unable to recognise uploaded file format. '
.'This generally means that the mime type for this file has not been defined for this database (common mime types are defined by default). '
.'Please add mime type from Database > Administration > Structure > Define mime types. '
.'Otherwise please contact your system administrator or the Heurist developers.');
                    return false;
            }
        }
        
        return $ret;
    }

    
    //
    //
    //    
    protected function prepareRecords(){
    
        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
        
        
            if(@$record['ulf_ExternalFileReference']){
                $this->records[$idx]['ulf_OrigFileName'] = '_remote';
            }
            if($isinsert){
                $this->records[$idx]['ulf_UploaderUGrpID'] = $this->system->get_user_id();
                $this->records[$idx]['ulf_Added'] = date('Y-m-d H:i:s');
            }else{
                //do not change these params on update
                if(@$this->records[$idx]['ulf_FilePath']=='') unset($this->records[$idx]['ulf_FilePath']);
                if(@$this->records[$idx]['ulf_FileName']=='') unset($this->records[$idx]['ulf_FileName']);
            }
            if(@$record['ulf_ExternalFileReference']==null || @$record['ulf_ExternalFileReference']==''){
                $this->records[$idx]['ulf_ExternalFileReference'] = null;
                unset($this->records[$idx]['ulf_ExternalFileReference']);
            }
        
            
            //change mimetype to extension
            $mimeType = strtolower($record['ulf_MimeExt']);
            if(strpos($mimeType,'/')>0){ //this is extension
                $this->records[$idx]['ulf_MimeExt'] = mysql__select_value($this->system->get_mysqli(), 
                    'select fxm_Extension from defFileExtToMimetype where fxm_Mimetype="'.addslashes($mimeType).'"');
            }
            
            //$this->records[$idx] = $record;
/*            
                'ulf_MimeExt ' => array_key_exists('ext', $filedata)?$filedata['ext']:NULL,
                'ulf_FileSizeKB' => 0,
*/            
        }

        return $ret;
        
    }    
    //
    //
    //
    public function save(){

        $ret = parent::save();
/*
        if($ret!==false){
            //treat thumbnail image
            foreach($this->records as $record){
                if(in_array(@$record['trm_ID'], $ret)){
                    $thumb_file_name = @$record['trm_Thumb'];
            
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }
                }
            }
        }
*/   
        if($ret!==false)
        foreach($this->records as $rec_idx => $record){
          
            if(!@$record['ulf_ObfuscatedFileID']){ //define obfuscation
            
                $ulf_ID = $record['ulf_ID'];
                $nonce = addslashes(sha1($ulf_ID.'.'.rand()));
                    
                $file2 = array();
                $file2['ulf_ID'] = $ulf_ID;
                $file2['ulf_ObfuscatedFileID'] = $nonce;
                $this->records[$rec_idx]['ulf_ObfuscatedFileID'] = $nonce;
                if(!@$record['ulf_ExternalFileReference']){
                    $file2['ulf_FileName'] = 'ulf_'.$ulf_ID.'_'.$record['ulf_OrigFileName']; 
                }

                $res = mysql__insertupdate($this->system->get_mysqli(), $this->config['tableName'], 'ulf', $file2);
                
            }
        }//after save loop
        return $ret;
    } 

    /**
    * 1. exclude non numeric
    * 2. find wrong permission   
    * 3. find in use 
    * 
    * @returns  array of 'deleted', 'no enough right' and 'in use' ids
    */
    public function delete(){
        
        $result = array('deleted'=>array(), 'no_rights'=>array(), 'in_use'=>array());
        
        $mysqli = $this->system->get_mysqli();
        
        $rec_IDs = prepareIds($this->data['recID']);
        
        if(count($rec_IDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of record identificators');
            return false;
        }
        
        //find 'no enough rights'
        if(!$this->system->is_dbowner()){
            $ugrs = $this->system->get_user_group_ids();
            
            $result['no_rights'] = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    'ulf_ID in ('.implode(',',$rec_IDs).') AND ulf_UploaderUGrpID not in ('.implode(',',$ugrs).')');

            $rec_IDs = array_diff($rec_IDs, $result['no_rights']);            
        }

        //find in use        
        if(count($rec_IDs)>0){             
            
            $result['in_use'] = mysql__select_list($mysqli, 'recDetails', 'dtl_UploadedFileID', 
                    'dtl_UploadedFileID in ('.implode(',',$rec_IDs).')');
                                               
            $rec_IDs = array_diff($rec_IDs, $result['no_rights']);            
        }
        
        if(count($rec_IDs)>0){        
        
            //gather data to remove files
            $query = 'SELECT ulf_ObfuscatedFileID, ulf_FilePath, ulf_FileName FROM recUploadedFiles WHERE ulf_ID in ('
                    .implode(',',$rec_IDs).')';

            $res = $mysqli->query($query);
            if ($res){
                $file_data = array();
                while ($row = $res->fetch_row()){
                    if(@$row[1] || @$row[2]){
                        $fullpath = @$row[1] . @$row[2];
                        //add database media storage folder for relative paths
                        $fullpath = resolveFilePath($fullpath);
                        if(!file_exists($fullpath)) $fullpath=null;
                    }else{
                        $fullpath = null; //remote
                    }
                    $file_data[$row[0]] = $fullpath;
                }
                $res->close();
            }

            
            $mysqli->query('SET foreign_key_checks = 0');
            $ret = $mysqli->query('DELETE FROM recUploadedFiles WHERE ulf_ID in ('.implode(',',$rec_IDs).')');
            $mysqli->query('SET foreign_key_checks = 1');
            
            if(!$ret){
                $this->system->addError(HEURIST_DB_ERROR, 
                        "Cannot delete from table ".$this->config['entityName'], $mysqli->error);
                return false;
            }else{
                
                //remove files
                foreach ($file_data as $file_id=>$filepath){
                    if($filepath!=null){
                        unlink($filepath);
                    }
                    //remove thumbnail
                    $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$file_id.".png";
                    if(file_exists($thumbnail_file)){
                        unlink($thumbnail_file);
                    }
                }
                $result['deleted'] = $rec_IDs;
            }
            
        }
        
        return $result;
    }
}
?>
