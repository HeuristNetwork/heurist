<?php

    /**
    * db access to recUploadedFiles table
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');


class DbRecUploadedFiles extends DbEntityBase
{
    private $error_ext;
    
    //
    // constructor - load configuration from json file
    //    
    function __construct( $system, $data ) {
        
       if($data==null){
           $data = array();
       } 
       if(!@$data['entity']){
           $data['entity'] = 'recUploadedFiles';
       }
        
       parent::__construct( $system, $data );
       
       $this->error_ext = 'Error inserting file metadata or unable to recognise uploaded file format. '
.'This generally means that the mime type for this file has not been defined for this database (common mime types are defined by default). '
.'Please add mime type from Admin > Manage files > Define mime types. '
.'Otherwise please '.CONTACT_SYSADMIN.' or '.CONTACT_HEURIST_TEAM.'.';       
    }
    
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
        
        if(parent::search()===false){
              return false;   
        }
        
        if(@$this->data['details']=='related_records'){
            return $this->_getRelatedRecords($this->data['ulf_ID'], true);
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
        $needMimeType = !($value==null || $value=='any');
        if($needMimeType){
            array_push($where, "(fxm_MimeType like '$value%')");
        }
        if($needMimeType || @$this->data['details']=='full' || @$this->data['details']=='list'){
            array_push($where, "(fxm_Extension=ulf_MimeExt)");
            array_push($from_table, 'defFileExtToMimetype');
        }
        //----- order by ------------
        
        //compose ORDER BY 
        $order = array();
        
        //$pred = $this->searchMgr->getSortPredicate('ulf_UploaderUGrpID');
        //if($pred!=null) array_push($order, $pred);
        $value = @$this->data['sort:ulf_Added'];
        if($value!=null){
            array_push($order, 'ulf_Added '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:ulf_FileSizeKB'];
            if($value!=null){
                array_push($order, 'ulf_FileSizeKB '.($value>0?'ASC':'DESC'));
            }else{
                array_push($order, 'ulf_OrigFileName ASC');
            }
        }
        
        
        $needRelations = false;
        $needCheck = false;
        $needCalcFields = false;
        $calculatedFields = null;
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ulf_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName';
            
        }else if(@$this->data['details']=='list'){

            //$this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference, ulf_ObfuscatedFileID';
            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_FilePath,fxm_MimeType';
            $needCalcFields = true;
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_Description,ulf_FileSizeKB,ulf_MimeExt,ulf_Added,ulf_UploaderUGrpID,fxm_MimeType';
            //$this->data['details'] = implode(',', $this->fields );
            $needRelations = true;
            $needCalcFields = true;
        }else{
            $needCheck = true;
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        $is_ids_only = (count($this->data['details'])==1);
         
        if($needCalcFields){
            //compose player html tag
            $calculatedFields = function ($fields, $row=null) {
                
                    if($row==null){
                        array_push($fields, 'ulf_PlayerTag');
                        return $fields;   
                    }else{

                        $idx = array_search('ulf_ObfuscatedFileID', $fields);
                        if($idx!==false){
                            $fileid = $row[$idx]; 
                            $mimeType=null;
                            $external_url=null;
                            $idx = array_search('fxm_MimeType', $fields);
                            if($idx!==false) $mimeType = $row[$idx]; 
                            $idx = array_search('ulf_ExternalFileReference', $fields);
                            if($idx!==false) $external_url = $row[$idx]; 
                            array_push($row, fileGetPlayerTag($fileid, $mimeType, null, $external_url));  //add ulf_PlayerTag  
                        }else{
                            array_push($row, '');
                        }
                        
                        return $row;
                    }
            };
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

        
         $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['tableName'], $calculatedFields);
        
        //find related records 
        if($needRelations && !(is_bool($result) && $result==false) && count($result['order'])>0 ){
            
            $result['relations'] = $this->_getRelatedRecords($result['order'], false);
            if(!$result['relations']){
                return false;
            }
            
        
        }//end find related records 
        
        return $result;

    }

    //
    //
    //    
    private function _getRelatedRecords($ulf_IDs, $ids_only){
        
            $ulf_IDs = prepareIds($ulf_IDs);
        
            if(count($ulf_IDs)>1){
                $s = ' in ('.implode(',',$ulf_IDs).')';
            }else{
                $s = '='.$ulf_IDs[0];
            }
     
            $mysqli = $this->system->get_mysqli();
        
            //find all related records (that refer to this file)
            if($ids_only){
                $query = 'SELECT dtl_RecID FROM recDetails WHERE dtl_UploadedFileID '.$s;                
                
                $res = mysql__select_list2($mysqli, $query);
                
            }else{
                $query = 'SELECT dtl_UploadedFileID, dtl_RecID, dtl_ID, rec_Title, rec_RecTypeID '
                        .'FROM recDetails, Records WHERE dtl_UploadedFileID '.$s.' and dtl_RecID=rec_ID';
                $direct = array();
                $headers = array();
                
                $res = $mysqli->query($query);
                if ($res){
                        while ($row = $res->fetch_row()) {
                            $relation = new stdClass();
                            $relation->recID = intval($row[0]);  //file id 
                            $relation->targetID = intval($row[1]);  //record id
                            $relation->dtID  = intval($row[2]);
                            array_push($direct, $relation);
                            $headers[$row[1]] = array($row[3], $row[4]);   
                        }
                        $res->close();
                        
                        $res = array("direct"=>$direct, "headers"=>$headers);
                }            
            }
            if($res===null || $res===false){
                    $this->system->addError(HEURIST_DB_ERROR, 
                        'Search query error for records that use files. Query '.$query,
                        $mysqli->error);
                    return false;
            }else{
                    return $res;
            }
    }
    
    //
    //
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_dbowner() && count($this->recordIDs)>0){
            
            $ugr_ID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
            
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    $this->primaryField.' in ('.implode(',', $this->recordIDs).') AND ulf_UploaderUGrpID != '.$ugr_ID.')');

            $cnt = count($recIDs_norights);       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                (($cnt==1 && (!isset($this->records) || count($this->records)==1) )
                    ? 'File is'
                    : $cnt.' files are')
                    .' uploaded by other user. Insufficient rights for this operation');
                return false;
            }
        }
        
        return true;
        
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
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Extension: '.$mimetypeExt.'<br> '.$this->error_ext);
                    return false;
            }
            
            if($fieldvalues['ulf_FileSizeKB']<0 || !is_numeric($fieldvalues['ulf_FileSizeKB'])){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid file size value: '.$fieldvalues['ulf_FileSizeKB']);
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
            }else if(@$record['ulf_FileUpload']){
                
                $fields_for_reg = $this->getFileInfoForReg($record['ulf_FileUpload'], null);            
                if(is_array($fields_for_reg)){
                    $this->records[$idx] = array_merge($this->records[$idx], $fields_for_reg);
                }
            }
                
            if($isinsert){
                $this->records[$idx]['ulf_UploaderUGrpID'] = $this->system->get_user_id();
                $this->records[$idx]['ulf_Added'] = date('Y-m-d H:i:s');
            }else{
                //do not change these params on update
                if(@$this->records[$idx]['ulf_FilePath']=='') unset($this->records[$idx]['ulf_FilePath']);
            }
            if(@$this->records[$idx]['ulf_FileName']=='') unset($this->records[$idx]['ulf_FileName']);
            
            if(@$record['ulf_ExternalFileReference']==null || @$record['ulf_ExternalFileReference']==''){
                $this->records[$idx]['ulf_ExternalFileReference'] = null;
                unset($this->records[$idx]['ulf_ExternalFileReference']);
            }
            
            //change mimetype to extension
            $mimeType = strtolower($this->records[$idx]['ulf_MimeExt']);
            if($mimeType==''){
                $mimeType = 'dat';
                $this->records[$idx]['ulf_MimeExt'] = 'dat';
                /*
                    $this->system->addError(HEURIST_ACTION_BLOCKED, $this->error_ext);
                    return false;
                */
            }
            if(strpos($mimeType,'/')>0){ //this is mimetype - find extension
                $fileExtension = mysql__select_value($this->system->get_mysqli(), 
                    'select fxm_Extension from defFileExtToMimetype where fxm_Mimetype="'.addslashes($mimeType).'"');

                if($fileExtension==null && $this->records[$idx]['ulf_OrigFileName'] != '_remote'){
                    //mimetype not found - try to get extension from name
                    $extension = strtolower(pathinfo($this->records[$idx]['ulf_OrigFileName'], PATHINFO_EXTENSION));
                    if($extension){
                        $fileExtension = mysql__select_value($this->system->get_mysqli(), 
                            'select fxm_Extension from defFileExtToMimetype where fxm_Extension="'.addslashes($extension).'"');
                        if($fileExtension==null){
                            //still not found
                            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Neither mimetype: '.$mimeType
                                    .' nor extension '.$extension.' are registered in database.<br><br>'.$this->error_ext);
                            return false;
                        }    
                    }
                }
                if($fileExtension==null){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'File mimetype is detected as: '.$mimeType
                        .'. It is not registered in database.<br><br>'.$this->error_ext);
                    return false;
                }
                $this->records[$idx]['ulf_MimeExt'] = $fileExtension;
            }

            if(!@$this->records[$idx]['ulf_FileSizeKB']) {
                $this->records[$idx]['ulf_FileSizeKB'] = 0;
            }
            
            //$this->records[$idx] = $record;
/*            
                'ulf_MimeExt ' => array_key_exists('ext', $filedata)?$filedata['ext']:NULL,
                'ulf_FileSizeKB' => 0,
*/            
        }

        return $ret;
        
    }    

    // there are 3 ways
    // 1) add for local files - via register
    // 2) remote - save as usual and define ulf_ObfuscatedFileID and ulf_FileName
    // 3) update just parent:save
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
                
                if($ulf_ID>0){
                    $nonce = addslashes(sha1($ulf_ID.'.'.rand()));
                        
                    $file2 = array();
                    $file2['ulf_ID'] = $ulf_ID;
                    $file2['ulf_ObfuscatedFileID'] = $nonce;
                    
                    if(!@$record['ulf_ExternalFileReference'] && !@$record['ulf_FileName']){
                        if($record['ulf_MimeExt']=='mbtiles'){
                            $this->records[$rec_idx]['ulf_FileName'] = 'ulf_'.$ulf_ID.'.mbtiles'; 
                        }else{
                            $this->records[$rec_idx]['ulf_FileName'] = 'ulf_'.$ulf_ID.'_'.$record['ulf_OrigFileName']; 
                        }
                        $file2['ulf_FileName'] = $this->records[$rec_idx]['ulf_FileName']; 
                    }

                    $res = mysql__insertupdate($this->system->get_mysqli(), $this->config['tableName'], 'ulf', $file2);
                    
                    if($res>0){
                        $this->records[$rec_idx]['ulf_ObfuscatedFileID'] = $nonce;
    //                    $this->records[$rec_idx]['ulf_ID'] = $res;
                    }
                }
            }
            
            //if there is file to be copied                        
            if(@$this->records[$rec_idx]['ulf_TempFile']){ 
                    
                    $ulf_ID = $this->records[$rec_idx]['ulf_ID'];
                    $ulf_ObfuscatedFileID = $this->records[$rec_idx]['ulf_ObfuscatedFileID'];
                    
                    //copy temp file from scratch to fileupload folder
                    $tmp_name = $this->records[$rec_idx]['ulf_TempFile'];
                    $new_name = $this->records[$rec_idx]['ulf_FileName']; 
                    
                    if( copy($tmp_name, HEURIST_FILES_DIR.$new_name) ) 
                    {
                        //remove temp file
                        unlink($tmp_name);
                        
                        //copy thumbnail
                        if(@$record['ulf_TempFileThumb']){
                            $thumb_name = HEURIST_SCRATCH_DIR.'thumbs/'.$record['ulf_TempFileThumb'];
                            if(file_exists($thumb_name)){
                                $new_name = HEURIST_THUMB_DIR.'ulf_'.$ulf_ObfuscatedFileID.'.png';
                                copy($thumb_name, $new_name);
                                //remove temp file
                                unlink($thumb_name);
                            }
                        }
                        
                    }else{
                        $this->system->addError(HEURIST_INVALID_REQUEST,
                             "Upload file: $new_name couldn't be saved to upload path definied for db = "
                            . $this->system->dbname().' ('.HEURIST_FILES_DIR
                            .'). Please ask your system administrator to correct the path and/or permissions for this directory');
                    }                    

            }
            
        }//after save loop
        return $ret;
    } 

    //
    //
    //
    public function delete($disable_foreign_checks = false){
        
        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }        
        
        if(!$this->_validatePermission()){
            return false;
        }
            
        $mysqli = $this->system->get_mysqli();
        
        
        $cnt = mysql__select_value($mysqli, 'SELECT count(dtl_ID) '
            .'FROM recDetails WHERE dtl_UploadedFileID in ('.implode(',', $this->recordIDs).')');
                    
        /*            
        $recIDs_inuse = mysql__select_list2($mysqli, 'SELECT DISTINCT dtl_RecID '
                    .'FROM recDetails WHERE dtl_UploadedFileID in ('.implode(',', $this->recordIDs).')');
        $cnt = count($recIDs_inuse);       
        */
                    
        if($cnt>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 
            (($cnt==1 && count($this->records)==1)
                ? 'There is a reference'
                : 'There are '.$cnt.' references')
                .' from record(s) to this File.<br>You must delete the records'
                .' or the File field values in order to be able to delete the file.');
            return false;
        }
        
        
        //gather data to remove files
        $query = 'SELECT ulf_ObfuscatedFileID, ulf_FilePath, ulf_FileName FROM recUploadedFiles WHERE ulf_ID in ('
                .implode(',',$this->recordIDs).')';

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
        $ret = $mysqli->query('DELETE FROM '.$this->config['tableName']
                               .' WHERE '.$this->primaryField.' in ('.implode(',',$this->recordIDs).')');
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
        }
        
        return true;
    }
    
    
    //
    // 
    //
    private function getFileInfoForReg($file, $newname){
        
        if(!is_a($file,'stdClass')){
            
            $tmp_thumb = null;
            
            if(is_array($file)){
                
                $tmp_name  = $file[0]['name'];   //name only
                $newname   = $file[0]['original_name'];
                $tmp_thumb = @$file[0]['thumbnailName'];

            }else{
                $tmp_name  = $file;
            }
            
            if(!file_exists($tmp_name)){
                $fileinfo = pathinfo($tmp_name);
                if($fileinfo['basename']==$tmp_name){ //only name - by default in scratch folder
                    $tmp_name = HEURIST_SCRATCH_DIR.$tmp_name;
                }
            }
            
            if(file_exists($tmp_name)){
                $fileinfo = pathinfo($tmp_name);
                
                $file = new \stdClass();
                //name with ext
                $file->original_name = $newname?$newname:$fileinfo['basename']; //was filename
                $file->name = $file->original_name;
                $file->size = filesize($tmp_name); //fix_integer_overflow
                $file->type = @$fileinfo['extension'];
                
                $file->thumbnailName = $tmp_thumb;
            }
            
        }else{
            //uploaded via UploadHandler is in scratch
            if(@$file->fullpath){
                $tmp_name = $file->fullpath;
            }else{
                $tmp_name = HEURIST_SCRATCH_DIR.$file->name;
            }
        }
        
        
        $errorMsg = null;        
        if(file_exists($tmp_name)){
            
                $fields = array();
                /* clean up the provided file name -- these characters shouldn't make it through anyway */
                $name = $file->original_name;
                $name = str_replace("\0", '', $name);
                $name = str_replace('\\', '/', $name);
                $name = preg_replace('!.*/!', '', $name);
                
                $extension = null;
                if($file->type==null || $file->type=='application/octet-stream'){ 
                    //need to be more specific - try ro save extension
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                }
                
                $ret = array(    
                'ulf_OrigFileName' => $name,
                'ulf_MimeExt' => $extension?$extension:$file->type, //extension or mimetype allowed
                'ulf_FileSizeKB' => ($file->size<1024?1:intval($file->size/1024)),
                'ulf_FilePath' => 'file_uploads/',   //relative path to HEURIST_FILESTORE_DIR - db root
                'ulf_TempFile' => $tmp_name);   //file in scratch to be copied 
                
                if(isset($file->thumbnailName)){
                    $ret['ulf_TempFileThumb'] = $file->thumbnailName;
                }
                
                //,'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt)); //backward capability            
                
        }else{
        
            /*if(is_a($file,'stdClass')){
                $errorMsg = 'Cant find temporary uploaded file: '.$file->name
                            .' for db = ' . $this->system->dbname().' ('.HEURIST_SCRATCH_DIR
                            .')';
            }else{ */
            $errorMsg = 'Cant find file to be registred : '.$tmp_name
                           .' for db = ' . $this->system->dbname();
            
            $errorMsg = $errorMsg
                    .'. Please ask your system administrator to correct the path and/or permissions for this directory';
                    
            $this->system->addError(HEURIST_INVALID_REQUEST, $errorMsg);        
            
            $ret = false;
        }
       
        return $ret;
    }
    
    /**
    * Save encoded image data as file and register it
    * 
    * @param mixed $data - image data
    * @param mixed $newname
    */
    public function registerImage($data, $newname){
        
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (!in_array($type, [ 'jpg', 'jpeg', 'jfif', 'gif', 'png' ])) {
                //throw new \Exception('invalid image type');
                return false;
            }

            $data = base64_decode($data);

            if ($data === false) {
                //throw new \Exception('base64_decode failed');
                return false;
            }
        } else {
            //throw new \Exception('did not match data URI with image data');
            return false;
        }
        
        $filename = $newname.'.'.$type;

        file_put_contents(HEURIST_SCRATCH_DIR.$filename, $data);        
        
        return $this->registerFile($filename, $newname);
    }
    
    
    /**
    * register file in database
    * 
    * @param mixed $file - flle object see UploadHabdler->get_file_object) 
    *     
            $file = new \stdClass();
            original_name, type, name, size, url (get_download_url), 
    * 
    * 
    * @param mixed $needclean - remove file from temp lcoation after reg
    * @returns record or false
    */
    public function registerFile($file, $newname, $needclean = true){
        
       $this->records = null; //reset 
        
       $fields = $this->getFileInfoForReg($file, $newname);
                    
       if($fields!==false){             
           
                //$tmp_name = $fields['ulf_TempFile'];
                //unset($fields['ulf_TempFile']);
           
                $fileinfo = array('entity'=>'recUploadedFiles', 'fields'=>$fields);
                
                $this->setData($fileinfo);
                $ret = $this->save();   //copies temp from scratch to file_upload it returns ulf_ID
                
                //unlink($tmp_name);
                
                return $ret;    
       }else{
           return false;
       }        
       
    }
    
    /**
    * @todo
    * 
    * @param mixed $url
    * @param mixed $generate_thumbmail
    */
    public function registerURL($url, $generate_thumbmail = false){

 
    }   

    
}
?>
