<?php

    /**
    * db access to recUploadedFiles table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');
require_once (dirname(__FILE__).'/../dbaccess/db_records.php');
require_once(dirname(__FILE__).'/../../import/fieldhelper/harvestLib.php');

/**
* some public methods
* 
*   registerImage - saves encoded image data as file and register it
*   registerFile - uses getFileInfoForReg to get file info
*   registerURL - register url: retrieves MimeExt 
* 
*/
    
    



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
        

        $value = @$this->data['fxm_MimeType']; 
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
            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_FilePath,fxm_MimeType,ulf_PreferredSource,ulf_FileSizeKB';
            $needCalcFields = true;
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_Description,ulf_FileSizeKB,ulf_MimeExt,ulf_Added,ulf_UploaderUGrpID,fxm_MimeType,ulf_PreferredSource';
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
                            array_push($row, fileGetPlayerTag($this->system, $fileid, $mimeType, null, $external_url));  //add ulf_PlayerTag  
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
            $s = $mysqli->real_escape_string($s);
        
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
        
        if(!$this->system->is_dbowner() && is_array($this->recordIDs) && count($this->recordIDs)>0){
            
            $ugr_ID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
            
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    $this->primaryField.' in ('.implode(',', $this->recordIDs).') AND ulf_UploaderUGrpID != '.$ugr_ID.')');

            $cnt = is_array($recIDs_norights)?count($recIDs_norights):0;       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1) )
                    ? 'File is'
                    : $cnt.' files are')
                    .' uploaded by other user. Insufficient rights (logout/in to refresh) for this operation');
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
            
            $mimeType = strtolower($this->records[$idx]['ulf_MimeExt']);
        
            if(@$record['ulf_ExternalFileReference']){
                
                if(strpos(@$this->records[$idx]['ulf_OrigFileName'],'_tiled')!==0 &&
                   strpos(@$this->records[$idx]['ulf_OrigFileName'],'_iiif')!==0 &&
                   strpos(@$this->records[$idx]['ulf_PreferredSource'],'iiif')!==0 &&
                   strpos(@$this->records[$idx]['ulf_PreferredSource'],'tiled')!==0) 
                {
                    
                    /*(strpos($record['ulf_ExternalFileReference'], 'iiif')!==false
                    || strpos($record['ulf_ExternalFileReference'], 'manifest.json')!==false
                    || strpos($record['ulf_ExternalFileReference'], 'info.json')!==false) */                    
                    //check iiif - either manifest of image
                    if($mimeType=='json' || $mimeType=='application/json'|| $mimeType=='application/ld+json'){
/*
We can register either info.json (reference to local or remote IIIF server that describes particular IIIF image) or manifest.json (that describes set of media and their appearance).  

On registration if mime type is application/json we loads this file and check whether it is image info or manifest. For former case we store in ulf_OrigFileName “iiif_image”, for latter one “iiif”.

When we open "iiif_image" in mirador viewer we generate manifest dynamically.
@see miradorViewer.php
*/
                        
                        
                        //verify that url points to iiif manifest
                        $iiif_manifest = loadRemoteURLContent($record['ulf_ExternalFileReference']); //check that json is iiif manifest
                        $iiif_manifest = json_decode($iiif_manifest, true);
                        if($iiif_manifest!==false && is_array($iiif_manifest))
                        {
                            if(@$iiif_manifest['@type']=='sc:Manifest' ||   //v2
                                @$iiif_manifest['type']=='Manifest')        //v3
                            {
                                //take label, description, thumbnail
                                //@$iiif_manifest['label'];

                                if(!@$record['ulf_Description'] && @$iiif_manifest['description']){
                                    $this->records[$idx]['ulf_Description'] = @$iiif_manifest['description']; 
                                }
                                if(@$iiif_manifest['thumbnail']){

                                    if(@$iiif_manifest['thumbnail']['@id']){  //v2
                                        $this->records[$idx]['ulf_TempThumbUrl'] = @$iiif_manifest['thumbnail']['@id'];      
                                    }else if(@$iiif_manifest['thumbnail']['id']){  //v3
                                        $this->records[$idx]['ulf_TempThumbUrl'] = @$iiif_manifest['thumbnail']['id'];      
                                    }
                                }

                                if(!@$this->records[$idx]['ulf_OrigFileName']){
                                    $this->records[$idx]['ulf_OrigFileName'] = '_iiif';   
                                }
                                $this->records[$idx]['ulf_PreferredSource'] = 'iiif';
                                $mimeType = 'json';
                                $this->records[$idx]['ulf_MimeExt'] = 'json';
                                
                            }else if(@$iiif_manifest['@context'] && (@$iiif_manifest['@id'] || @$iiif_manifest['id']))
                            {   //IIIF image
                                
                                //create url for thumbnail
                                
                                $x = $iiif_manifest['width'];
                                $y = $iiif_manifest['height'];
                                
                                $rx = 200 / $x;
                                $ry = 200 / $y;
                                
                                $scale = $rx ? ($ry ? min($rx, $ry) : $rx) : $ry;
                                
                                if ($scale > 1) { //no enlarge
                                    $scale = 1;
                                }
                                
                                $new_x = ceil($x * $scale);
                                $new_y = ceil($y * $scale);
                                
                                //https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9604118j/f25/full/90,120/0/default.jpg
                                
                                $thumb_url = $record['ulf_ExternalFileReference'];
                                
                                //remove info.json
                                $thumb_url = substr($thumb_url, 0, -9).'full/'.$new_x.','.$new_y.'/0/default.jpg';
                                
                                $this->records[$idx]['ulf_TempThumbUrl'] = $thumb_url;      
                                
                                if(!@$this->records[$idx]['ulf_OrigFileName']){
                                    $this->records[$idx]['ulf_OrigFileName'] = '_iiif_image';  
                                }
                                $this->records[$idx]['ulf_PreferredSource'] = 'iiif_image';
                                $mimeType = 'json';
                                $this->records[$idx]['ulf_MimeExt'] = 'json';
                            }
                            
                        }

                    }
                    
                    if(!$this->records[$idx]['ulf_OrigFileName']){
                        $this->records[$idx]['ulf_OrigFileName'] = '_remote';    
                    }
                    if(!@$this->records[$idx]['ulf_PreferredSource']){
                        $this->records[$idx]['ulf_PreferredSource'] = 'external';
                    }
                }
            }else{
                $this->records[$idx]['ulf_PreferredSource'] = 'local';
            
                if(@$record['ulf_FileUpload']){
                
                    $fields_for_reg = $this->getFileInfoForReg($record['ulf_FileUpload'], null); //thumbnail is created here           
                    if(is_array($fields_for_reg)){
                        $this->records[$idx] = array_merge($this->records[$idx], $fields_for_reg);
                    }
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
            if($mimeType==''){
                $mimeType = 'dat';
                $this->records[$idx]['ulf_MimeExt'] = 'dat';
                /*
                    $this->system->addError(HEURIST_ACTION_BLOCKED, $this->error_ext);
                    return false;
                */
            }
            if(strpos($mimeType,'/')>0){ //this is mimetype - find extension
            
                $mysqli = $this->system->get_mysqli();
            
                $query = 'select fxm_Extension from defFileExtToMimetype where fxm_Mimetype="'.
                $mysqli->real_escape_string($mimeType).'"';
            
                if($mimeType=='application/x-zip-compressed'){
                    $query = $query.' OR fxm_Mimetype="application/zip"'; //backward capability
                }
            
                $fileExtension = mysql__select_value($mysqli, $query);

                if($fileExtension==null && 
                    $this->records[$idx]['ulf_PreferredSource']=='local')
                    //$this->records[$idx]['ulf_OrigFileName'] != '_remote' && 
                    //strpos($this->records[$idx]['ulf_OrigFileName'],'_tiled')!==0)
                {
                    //mimetype not found - try to get extension from name
                    $extension = strtolower(pathinfo($this->records[$idx]['ulf_OrigFileName'], PATHINFO_EXTENSION));
                    if($extension){
                        $fileExtension = mysql__select_value($mysqli, 
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
                    
                    if(strpos($record['ulf_OrigFileName'],'_tiled')===0 || $record['ulf_PreferredSource']=='tiled')
                    {
                        if(!@$record['ulf_ExternalFileReference']){
                            $file2['ulf_ExternalFileReference'] = $ulf_ID.'/'; //HEURIST_TILESTACKS_URL.
                        }
                        $file2['ulf_FilePath'] = '';
                    }else
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
            
            if( (strpos($record['ulf_OrigFileName'],'_iiif')===0  || strpos($record['ulf_PreferredSource'],'iiif')===0)
                && @$record['ulf_TempThumbUrl']){

                    $thumb_name = HEURIST_THUMB_DIR.'ulf_'.$this->records[$rec_idx]['ulf_ObfuscatedFileID'].'.png';
                    $temp_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");
                    if(saveURLasFile($record['ulf_TempThumbUrl'], $temp_path)){ //save to temp in scratch folder
                        UtilsImage::createThumbnailFile($temp_path, $thumb_name); //create thumbnail for iiif image
                        unlink($temp_path);       
                    }
            }else
            //if there is file to be copied                        
            if(@$this->records[$rec_idx]['ulf_TempFile']){ 
                    
                    $ulf_ID = $this->records[$rec_idx]['ulf_ID'];
                    $ulf_ObfuscatedFileID = $this->records[$rec_idx]['ulf_ObfuscatedFileID'];
                    
                    //copy temp file from scratch to fileupload folder
                    $tmp_name = $this->records[$rec_idx]['ulf_TempFile'];
                    
                    if(strpos($record['ulf_OrigFileName'],'_tiled')===0  || $record['ulf_PreferredSource']=='tiled')
                    {
                        //create destination folder
                        $dest = HEURIST_TILESTACKS_DIR.$ulf_ID.'/';
                        
                        $warn = folderCreate2($dest, '');
                        
                        //unzip archive to HEURIST_TILESTACKS_DIR
                        if(unzipArchive($tmp_name, $dest)){
                            //remove temp file
                            unlink($tmp_name);

                            $file2 = array();
                            
                            //detect 1) mimetype 2) summary size of stack images 3) copy first image as thumbnail
                            $size = folderSize2($dest);
                            
                            //get first file from first folder
                            $filename = folderFirstTileImage($dest);
                            
                            $thumb_name = HEURIST_THUMB_DIR.'ulf_'.$ulf_ObfuscatedFileID.'.png';
                            
                            $mimeExt = UtilsImage::getImageType($filename);
                            
                            if($mimeExt){
                                UtilsImage::createThumbnailFile($filename, $thumb_name); //create thumbnail for tiled image
                                $file2['ulf_MimeExt'] = $mimeExt;    
                            }else{
                                $file2['ulf_MimeExt'] = 'png';
                            }
                            
                            $file2['ulf_ID'] = $ulf_ID;
                            $file2['ulf_FileSizeKB'] = $size/1024;
            
                            mysql__insertupdate($this->system->get_mysqli(), $this->config['tableName'], 'ulf', $file2);
                            
                            
                        }else{
                            $this->system->addError(HEURIST_INVALID_REQUEST,
                                 'Can\'t extract tiled images stack. It couldn\'t be saved to upload path definied for db = '
                                . $this->system->dbname().' ('.$dest
                                .'). Please ask your system administrator to correct the path and/or permissions for this directory');
                        }
                        
                    }else{
                        
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
                        
            }
            
        }//after save loop
        return $ret;
    } 
    
    //   Actions:
    //   register URL/Path in batch
    //   optionally: download URL and register locally
    //   
    //    csv_import (with optional is_download)
    //    delete_unused 
    //    regExternalFiles (with optional is_download)
    public function batch_action(){

        $mysqli = $this->system->get_mysqli();

        $this->need_transaction = false;
        $keep_autocommit = mysql__begin_transaction($mysqli);

        $ret = true;
        $is_csv_import = false;
        $cnt_skipped = 0;
        $cnt_imported = 0;
        $cnt_error = 0;
        $is_download = (@$this->data['is_download']==1);
        
        if($is_download){
            ini_set('max_execution_time', '0');
        }

        if(@$this->data['csv_import']){ // import new media via CSV. See importMedia.js
        
            $is_csv_import = true;

            if(@$this->data['fields'] && is_string($this->data['fields'])){ // new to perform extra validations first
                $this->data['fields'] = json_decode($this->data['fields'], true);
            }

            if(is_array($this->data['fields']) && count($this->data['fields'])>0){
                
                set_time_limit(0);

                foreach($this->data['fields'] as $idx => $record){
                    
                    $is_url = false;
                    //url or relative path
                    $url = trim($record['ulf_ExternalFileReference']);
                    $description = @$record['ulf_Description'];                    
                    
                    if(strpos($url,'http')===0){
                        //find if url is already registered
                        $is_url = true;
                        $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference="'
                        .$mysqli->real_escape_string($url).'"';

                    }else{

                        $k = strpos($url,'uploaded_files/');
                        if($k===false) $k = strpos($url,'file_uploads/');

                        if($k===0 || $k===1){
                            //relative path in database folder
                            $filename = HEURIST_FILESTORE_DIR.$url;
                            if(file_exists($url)){
                                //this methods checks if file is already registered
                                $fres = fileRegister($this->system, $filename, $description); //see db_files.php
                            }
                        }else {
                            $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ObfuscatedFileID="'
                            .$mysqli->real_escape_string($url).'"';
                        }
                    }

                    if($file_query){
                        $fres = mysql__select_value($mysqli, $file_query);    
                    }                    
                    
                                if($fres>0){
                                    $ulf_ID = $fres;
                                    $cnt_skipped++;
                                    
                                }else if($is_url) {

                                    $fields = array(
                                        'ulf_Description'=>$description, 
                                        'ulf_MimeExt'=>getURLExtension($url));
                    
                                    if($is_download){
                                        //download and register , last parameter - validate name and hash
                                        $ulf_ID = $this->downloadAndRegisterdURL($url, $fields, 2); //it returns ulf_ID    
                                    }else{
                                        $ulf_ID = $this->registerURL( $url, false, 0, $fields);    
                                    }
                                    
                                    if($ulf_ID>0){
                                        $cnt_imported++;    
                                    }else {
                                        $cnt_error++;    
                                    }
                                }
                                
                } //foreach
                    
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'No import data has been provided. Ensure that you have enter the necessary CSV rows.<br>Please contact the Heurist team if this problem persists.');
            }
        }
        else if(@$this->data['delete_unused']){ // delete file records not in use

            $ids = $this->data['delete_unused'];
            $operate = $this->data['operate'];

            $where_clause = 'WHERE dtl_ID IS NULL';
            if(is_array($ids) && count($ids) > 0){ // multiple
                $where_clause .= ' AND ulf_ID IN (' . implode(',', $ids) . ')';
            }else if(is_int($ids) && $ids > 0){ // single
                $where_clause .= ' AND ulf_ID = ' . $ids;
            }// else use all

            $query = 'SELECT DISTINCT ulf_ID, ulf_OrigFileName as filename, ulf_ExternalFileReference as url FROM ' . $this->config['tableName'] . ' LEFT JOIN recDetails ON ulf_ID = dtl_UploadedFileID ' . $where_clause;
            $to_delete = mysql__select_assoc($mysqli, $query);

            if(count($to_delete) > 0){

                // Check if Obfuscated ID is referenced in values
                foreach ($to_delete as $ulf_ID => $details) {
                    
                    $ulf_ObfuscatedFileID = mysql__select_value($mysqli, 'SELECT ulf_ObfuscatedFileID FROM ' . $this->config['tableName'] . ' WHERE ulf_ID = ' . $ulf_ID);
                    if(!$ulf_ObfuscatedFileID){ // missing ulf_ObfuscatedFileID
                        unset($to_delete[$ulf_ID]);
                        continue;
                    }

                    $is_used = mysql__select_value($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_Value LIKE '%". $ulf_ObfuscatedFileID ."%'");
                    if($is_used){
                        unset($to_delete[$ulf_ID]);
                        continue;
                    }
                }

                if($operate == 'delete' && count($to_delete) > 0){ // delete files

                    $to_delete = array_keys($to_delete);

                    $this->data[$this->primaryField] = $to_delete;
                    $res = $this->delete();
    
                    if($res === true){
                        $ret = count($to_delete);
                    }
                }else{ // return file details
                    $ret = $to_delete;
                }
            }
        }
        else if(@$this->data['regExternalFiles']){ // attempt to register multiple URLs at once, and return necessary information for record editor

            $rec_fields = $this->data['regExternalFiles'];

            if(!empty($rec_fields) && is_string($rec_fields)){
                $rec_fields = json_decode($rec_fields, TRUE);
            }

            if(is_array($rec_fields) && count($rec_fields) > 0){

                $results = array();

                foreach ($rec_fields as $dt_id => $urls) {

                    if(!array_key_exists($dt_id, $results)){
                        $results[$dt_id] = array();
                    }

                    if(is_array($urls)){

                        foreach ($urls as $idx => $url) {

                            if(strpos($url, 'http') === 0){
                                $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference = "' . $mysqli->real_escape_string($url) . '"';
                                $file_id = mysql__select_value($mysqli, $query);
        
                                if(!$file_id){ // new external file to save
                                    $file_id = $this->registerURL($url);
                                }

                                if($file_id > 0){ // retrieve file Obfuscated ID
                                    $query = 'SELECT ulf_ObfuscatedFileID, ulf_MimeExt FROM recUploadedFiles WHERE ulf_ID = ' . $file_id;
                                    $file_dtls = mysql__select_row($mysqli, $query);

                                    if(!$file_dtls){
                                        $results[$dt_id]['err_id'][$file_id] = $url; // cannot retrieve obfuscated id
                                    }else{
                                        $results[$dt_id][$idx] = array(
                                            'ulf_ID' => $file_id, 
                                            'ulf_ExternalFileReference' => $url, 
                                            'ulf_ObfuscatedFileID' => $file_dtls[0], 
                                            'ulf_MimeExt' => $file_dtls[1], 
                                            'ulf_OrigFileName' => '_remote'
                                        );
                                    }
                                }else{
                                    $results[$dt_id]['err_save'][] = $url; // unable to save
                                }
                            }else{
                                $results[$dt_id]['err_invalid'][] = $url; // invalid
                            }
                        }
                    }else if(is_string($urls) && strpos($urls, 'http') === 0){

                        $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference = "' . $mysqli->real_escape_string($urls) . '"';
                        $file_id = mysql__select_value($mysqli, $query);

                        if(!$file_id){ // new external file to save
                            $file_id = $this->registerURL($urls);
                        }

                        if($file_id > 0){ // retrieve file Obfuscated ID
                            $query = 'SELECT ulf_ObfuscatedFileID, ulf_MimeExt FROM recUploadedFiles WHERE ulf_ID = ' . $file_id;
                            $file_dtls = mysql__select_row($mysqli, $query);

                            if(!$file_dtls){
                                $results[$dt_id]['err_id'] = $urls;
                            }else{
                                $results[$dt_id] = array(
                                    'ulf_ID' => $file_id, 
                                    'ulf_ExternalFileReference' => $urls, 
                                    'ulf_ObfuscatedFileID' => $file_dtls[0], 
                                    'ulf_MimeExt' => $file_dtls[1], 
                                    'ulf_OrigFileName' => '_remote'
                                );
                            }
                        }else{
                            $results[$dt_id]['err_save'] = $urls;
                        }
                    }else if(!empty($urls)){
                        $results[$dt_id]['err_invalid'] = $urls;
                    }
                }

                $ret = $results;
            }
        }
        else if(@$this->data['merge_duplicates']){ // merge duplicate local + remote files

            $ids = $this->data['merge_duplicates'];
            $where_ids = '';

            $local_fixes = 0;
            $remote_fixes = 0;
            $dif_local_fixes = 0;
            $to_delete = array(); // array(new_ulf_id => array(dup_ulf_ids))

            if(is_array($ids) && count($ids) > 0){ // multiple
                $where_ids .= ' AND ulf_ID IN (' . implode(',', $ids) . ')';
            }else if(is_int($ids) && $ids > 0){ // single
                $where_ids .= ' AND ulf_ID = ' . $ids;
            }// else use all

            //search for duplicated local files
            $query = 'SELECT ulf_FilePath, ulf_FileName, count(*) as cnt FROM recUploadedFiles WHERE ulf_FileName IS NOT NULL' . $where_ids . ' GROUP BY ulf_FilePath, ulf_FileName HAVING cnt > 1';
            $local_dups = $mysqli->query($query);

            if($local_dups && $local_dups->num_rows > 0){

                //find id with duplicated path+name
                while($local_file = $local_dups->fetch_row()){

                    $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_FilePath' 
                        . ( @$local_file[0]!=null ? '="' . $mysqli->real_escape_string($local_file[0]) . '"' : ' IS NULL' ) .' AND ulf_FileName="'
                        .$mysqli->real_escape_string($local_file[1]).'"' . $where_ids;
                    $res = $mysqli->query($query);

                    $dups_ids = array();
                    while ($local_id = $res->fetch_row()) {
                        array_push($dups_ids, $local_id[0]);
                    }
                    $res->close();

                    $new_ulf_id = array_shift($dups_ids);
                    $upd_query = 'UPDATE recDetails set dtl_UploadedFileID='.$new_ulf_id.' WHERE dtl_UploadedFileID in ('.implode(',',$dups_ids).')';
                    $mysqli->query($upd_query);

                    if($mysqli->error !== ''){
                        $ret = false;
                        $this->system->addError(HEURIST_DB_ERROR, $mysqli->error);
                        break;
                    }else if($mysqli->affected_rows == 0){
                        continue;
                    }

                    $to_delete[$new_ulf_id] = $dups_ids;

                    $local_fixes = $local_fixes + count($dups_ids);
                    //$del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.implode(',',$dups_ids).')';
                    //$mysqli->query($del_query);
                }
                $local_dups->close();
            }

            //search for duplicated remote files
            $query = 'SELECT ulf_ExternalFileReference, count(*) as cnt FROM recUploadedFiles WHERE ulf_ExternalFileReference IS NOT NULL'. $where_ids .' GROUP BY ulf_ExternalFileReference HAVING cnt > 1';
            $remote_dups = $mysqli->query($query);
            
            if ($remote_dups && $remote_dups->num_rows > 0) {

                //find id with duplicated url 
                while ($res = $remote_dups->fetch_row()) {

                    $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference="'.$mysqli->real_escape_string($res[0]).'"' . $where_ids;
                    $res = $mysqli->query($query);

                    $dups_ids = array();
                    while ($remote_id = $res->fetch_row()) {
                        array_push($dups_ids, $remote_id[0]);
                    }
                    $res->close();

                    $new_ulf_id = array_shift($dups_ids);

                    $upd_query = 'UPDATE recDetails set dtl_UploadedFileID='.$new_ulf_id.' WHERE dtl_UploadedFileID in ('.implode(',',$dups_ids).')';
                    $mysqli->query($upd_query);

                    if($mysqli->error !== ''){
                        $ret = false;
                        $this->system->addError(HEURIST_DB_ERROR, $mysqli->error);
                        break;
                    }else if($mysqli->affected_rows == 0){
                        continue;
                    }

                    $to_delete[$new_ulf_id] = $dups_ids;

                    $remote_fixes = $remote_fixes + count($dups_ids); 
                    //$del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.implode(',',$dups_ids).')';
                    //$mysqli->query($del_query);
                }

                $remote_dups->close();
            }

            $query = 'SELECT ulf_OrigFileName, count(*) AS cnt '
            . 'FROM recUploadedFiles '
            . 'WHERE ulf_OrigFileName IS NOT NULL AND ulf_OrigFileName<>"_remote" AND ulf_OrigFileName NOT LIKE "_iiif%"'. $where_ids . ' '
            . 'GROUP BY ulf_OrigFileName HAVING cnt > 1';
            $local_dups = $mysqli->query($query);

            if($local_dups && $local_dups->num_rows > 0){

                while($local_file = $local_dups->fetch_row()){

                    $dup_query = 'SELECT ulf_ID, ulf_FilePath, ulf_FileName FROM recUploadedFiles WHERE ulf_OrigFileName="'.$mysqli->real_escape_string($local_file[0]).'"';
                    $dup_local_files = $mysqli->query($dup_query);
            
                    $dups_files = array(); //ulf_ID => path, size, md, array(dup_ulf_ids)
                    
                    while ($file_dtls = $dup_local_files->fetch_assoc()) {
                        
                        //compare files 
                        if(@$file_dtls['ulf_FilePath']==null){
                            $res_fullpath = $file_dtls['ulf_FileName'];
                        }else{
                            $res_fullpath = resolveFilePath( $file_dtls['ulf_FilePath'].$file_dtls['ulf_FileName'] ); //see db_files.php
                        }
                       
                        
                        $f_size = filesize($res_fullpath);
                        $f_md5 = md5_file($res_fullpath);
                        $is_unique = true;
                        foreach ($dups_files as $ulf_ID => $file_arr){ 
                            if ($file_arr['size'] == $f_size && $file_arr['md5'] == $f_md5){ // same file
                                $is_unique = false;
                                $dups_files[$ulf_ID]['dups'][] = $file_dtls['ulf_ID'];
                                break;
                            }
                        }
                        if($is_unique){
                            $dups_files[$file_dtls['ulf_ID']] = array('path'=>$res_fullpath, 'md5'=>$f_md5, 'size'=>$f_size, 'dups'=>array());
                        }
                    }
                    $dup_local_files->close();

                    foreach ($dups_files as $ulf_ID => $file_arr) {
                        if(count($file_arr['dups']) > 0){

                            $dup_ids = implode(',', $file_arr['dups']);
                            $upd_query = 'UPDATE recDetails SET dtl_UploadedFileID='.$ulf_ID.' WHERE dtl_UploadedFileID IN (' . $dup_ids .')';
                            $affected_rows = $mysqli->query($upd_query);

                            if($mysqli->error !== ''){
                                $ret = false;
                                $this->system->addError(HEURIST_DB_ERROR, $mysqli->error);
                                break;
                            }else if($mysqli->affected_rows == 0){
                                continue;
                            }

                            $to_delete[$ulf_ID] = $file_arr['dups'];
                            $dif_local_fixes = $dif_local_fixes + count($file_arr['dups']);
                        }
                    }
                }
            }

            // Add existing descriptions in dup records to new main record, then delete
            if($ret && count($to_delete) > 0){
                foreach ($to_delete as $ulf_ID => $d_ids) {

                    $dup_ids = $d_ids;
                    if(is_array($dup_ids)){
                        $dup_ids = implode(',', $dup_ids);
                        $to_delete[$ulf_ID] = $dup_ids;
                    }

                    // Concat descriptions
                    $query = 'SELECT ulf_Description FROM recUploadedFiles WHERE ulf_ID IN (' . $dup_ids . ') AND ulf_Description != ""';
                    $res = $mysqli->query($query);
                    $extra_desc = "";

                    if($mysqli->error != ''){
                        $this->system->addError(HEURIST_DB_ERROR, $mysqli->error);
                        $ret = false;
                        break;
                    }else if(!$res){
                        $this->system->addError(HEURIST_DB_ERROR, 'An unknown error occurred');
                        $ret = false;
                        break;
                    }

                    while($file_desc = $res->fetch_row()){
                        $extra_desc .= $file_desc[0] . "\n";
                    }

                    $query = 'SELECT ulf_Description FROM recUploadedFiles WHERE ulf_ID = ' . $ulf_ID;
                    $res = $mysqli->query($query);

                    if($mysqli->error == ''){
                        $desc = $res->fetch_row()[0];
                        if(!empty($desc)){
                            $extra_desc = $desc . "\n" . $extra_desc;
                        }
                    }

                    if(!empty($extra_desc)){
                        $upd_query = 'UPDATE recUploadedFiles SET ulf_Description ="'. $extra_desc .'" WHERE ulf_ID=' . $ulf_ID;
                        $mysqli->query($upd_query);
                    }
                }

                // Delete files
                $this->data[$this->primaryField] = $to_delete;
                $ret = $this->delete();
            }

            if($ret){
                $ret = array('local' => $local_fixes, 'remote' => $remote_fixes, 'location_local' => $dif_local_fixes);
            }
        }
        else if(@$this->data['create_media_records']){ // create Multi Media records for files without one

            $ids = $this->data['create_media_records'];
            if(is_int($ids) && $ids > 0){ // multiple
                $ids = array($ids);
            }else if(!is_array($ids) && count($ids) > 0){ // single
                $ids = explode(',', $ids);
            }

            $cnt_skipped = 0;
            $cnt_error = array();
            $cnt_new = array();

            // ----- Reqruied
            $rty_id = 0;
            $dty_file = 0;
            $dty_title = 0;
            // ----- Recommended
            $dty_desc = defined('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0;
            $dty_name = defined('DT_FILE_NAME') ? DT_FILE_NAME : 0;
            // ----- Optional
            $dty_path = defined('DT_FILE_FOLDER') ? DT_FILE_FOLDER : 0;
            $dty_ext = defined('DT_FILE_EXT') ? DT_FILE_EXT : 0;
            $dty_size = defined('DT_FILE_SIZE') ? DT_FILE_SIZE : 0;
            // ulf_ExternalFileReference goes into rec_URL

            if(defined('RT_MEDIA_RECORD') || ($this->system->defineConstant('RT_MEDIA_RECORD') && RT_MEDIA_RECORD > 0)){
                $rty_id = RT_MEDIA_RECORD;
            }
            if(defined('DT_FILE_RESOURCE') || ($this->system->defineConstant('DT_FILE_RESOURCE') && DT_FILE_RESOURCE > 0)){
                $dty_file = DT_FILE_RESOURCE;
            }
            if(defined('DT_NAME') || ($this->system->defineConstant('DT_NAME') && DT_NAME > 0)){
                $dty_title = DT_NAME;
            }

            if(defined('DT_SHORT_SUMMARY') || ($this->system->defineConstant('DT_SHORT_SUMMARY') && DT_SHORT_SUMMARY > 0)){
                $dty_desc = DT_SHORT_SUMMARY;
            }
            if(defined('DT_FILE_NAME') || ($this->system->defineConstant('DT_FILE_NAME') && DT_FILE_NAME > 0)){
                $dty_name = DT_FILE_NAME;
            }

            if(defined('DT_FILE_FOLDER') || ($this->system->defineConstant('DT_FILE_FOLDER') && DT_FILE_FOLDER > 0)){
                $dty_path = DT_FILE_FOLDER;
            }
            if(defined('DT_FILE_EXT') || ($this->system->defineConstant('DT_FILE_EXT') && DT_FILE_EXT > 0)){
                $dty_ext = DT_FILE_EXT;
            }
            if(defined('DT_FILE_SIZE') || ($this->system->defineConstant('DT_FILE_SIZE') && DT_FILE_SIZE > 0)){
                $dty_size = DT_FILE_SIZE;
            }

            if($rty_id > 0 && $dty_file > 0 && $dty_title > 0){

                $rec_search = 'SELECT count(rec_ID) AS cnt '
                            . 'FROM Records INNER JOIN recDetails ON rec_ID = dtl_RecID '
                            . 'WHERE rec_FlagTemporary!=1 AND rec_RecTypeID='.$rty_id
                            . ' AND dtl_DetailTypeID='.$dty_file.' AND dtl_UploadedFileID=';

                $file_search = 'SELECT ulf_OrigFileName, ulf_Description, ulf_FileName, ulf_FilePath, ulf_MimeExt, ulf_FileSizeKB, ulf_ExternalFileReference '
                            .  'FROM recUploadedFiles '
                            .  'WHERE ulf_ID=';

                $record = array(
                    'ID' => 0,
                    'RecTypeID' => $rty_id,
                    'no_validation' => true,
                    'URL' => '',
                    'ScratchPad' => null,
                    'AddedByUGrpID' => $this->system->get_user_id(), //ulf_UploaderUGrpID
                    'details' => array()
                );
                foreach ($ids as $ulf_id) {

                    $record['URL'] = '';
                    $record['details'] = array();

                    $rec_res = mysql__select_value($mysqli, $rec_search . $ulf_id);
                    if($rec_res > 0){ // already have a record
                        $cnt_skipped ++;
                        continue;
                    }

                    $file_details = mysql__select_row_assoc($mysqli, $file_search . $ulf_id);
                    if($file_details == null || $file_details == false){ // unable to retrieve file data
                        $cnt_error[] = $ulf_id;
                        continue;
                    }

                    $details = array(
                        $dty_file => $ulf_id,
                        $dty_title => $file_details['ulf_OrigFileName']
                    );

                    if($file_details['ulf_OrigFileName'] == '_remote'){
                        $record['URL'] = $file_details['ulf_ExternalFileReference'];
                    }

                    if($dty_desc > 0 && !empty($file_details['ulf_Description'])){
                        $details[$dty_desc] = $file_details['ulf_Description'];
                    }
                    if($dty_name > 0 && !empty($file_details['ulf_FileName'])){
                        $details[$dty_name] = $file_details['ulf_FileName'];
                    }
                    if($dty_path > 0 && !empty($file_details['ulf_FilePath'])){
                        $details[$dty_path] = $file_details['ulf_FilePath'];
                    }
                    if($dty_ext > 0 && !empty($file_details['ulf_MimeExt'])){
                        $details[$dty_ext] = $file_details['ulf_MimeExt'];
                    }
                    if($dty_size > 0 && !empty($file_details['ulf_FileSizeKB'])){
                        $details[$dty_size] = $file_details['ulf_FileSizeKB'];
                    }

                    $record['details'] = $details;

                    $res = recordSave($this->system, $record); //see db_records.php
                    if(@$res['status'] != HEURIST_OK){
                        $cnt_error[] = $ulf_id;
                        continue;
                    }

                    $cnt_new[] = $res['data'];
                }
                
                $ret = array('new' => $cnt_new, 'error' => $cnt_error, 'skipped' => $cnt_skipped);
            }else{

                $extra = '';
                if($rty_id <= 0){
                    $extra = 'missing the Digital media record type (2-5)';
                }
                if($dty_file <= 0){
                    $extra .= (($extra == '' && $dty_title > 0) ? ', ': ($extra == '' ? ' and ' : '')) . 'missing the required file field (2-38)';
                }
                if($dty_title <= 0){
                    $extra .= (($extra == '') ? ' and ': '') . 'missing the required title field (2-1)';
                }

                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Unable to proceed with Media record creations, due to ' . $extra);
            }
        }
        else if(@$this->data['bulk_reg_filestore']){ // create new file entires 

            $error = array(); // file missing or other errors
            $skipped = array(); // already registered
            $created = array(); // total ulf records created
            $exists = 0; // count of files that already exists

            $files = array();

            $dirs_and_exts = getMediaFolders($mysqli);

            if(array_key_exists('files', $this->data) && !empty($this->data['files'])){ // manageFilesUpload.php
                $files = json_decode($this->data['files']);
            }else{ // manageRecUploadedFiles.js

                // Get non-registered files 
                doHarvest($this->system, $dirs_and_exts, false, 1);
                $files = getRegInfoResult()['nonreg'];
            }

            // Add filestore path
            $dirs_and_exts['dirs'] = array_map(function($dir){
                if(strpos($dir, HEURIST_FILESTORE_DIR) === false){
                    $dir = HEURIST_FILESTORE_DIR . ltrim($dir, '/');
                }
                return rtrim($dir, '/');
            }, $dirs_and_exts['dirs']);

            $system_folders = $this->system->getSystemFolders();
            foreach ($files as $file_details) {

                $file = $file_details;
                if(is_object($file_details)){ // from decoded JS stringified
                    if(property_exists($file_details, 'file_path')){
                        $file = $file_details->file_path;
                    }else{ // not handled
                        $skipped[] = implode(',', $file) . ' => File data is not in valid format';
                        continue;
                    }
                }

                $provided_file = $file;
                if(strpos($file, HEURIST_FILESTORE_DIR) === false){
                    $file = HEURIST_FILESTORE_DIR . $file;
                }

                if(!file_exists($file)){ // not found, or not in file store
                    $error[] = $provided_file . ' => File does not exist';
                    continue;
                }

                $fileinfo = pathinfo($file);
                $path = $fileinfo['dirname'];
                $name = $fileinfo['basename'];
                $valid_dir = false;

                // Check file directory against set 'upload file' directories
                foreach ($dirs_and_exts['dirs'] as $file_dir) {
                    if(strpos($path, $file_dir) !== false){
                        $valid_dir = true;
                        break;
                    }
                }
                if(!$valid_dir){
                    $skipped[] = $name . ' => File is not located within any set upload directories';
                    continue;
                }

                // Check extension
                if(!in_array(strtolower($fileinfo['extension']), $dirs_and_exts['exts'])){
                    $skipped[] = $name . ' => File extension is not allowed';
                    continue;   
                }

                // Check if file is already registered
                if(fileGetByFileName($this->system, $file) > 0){
                    $exists ++;
                    continue;
                }

                $ulf_ID = fileRegister($this->system, $file);
                if($ulf_ID > 0){
                    $created[] = $name . ' => Registered file as #' . $ulf_ID;
                }else{
                    $msg = $this->system->getError();
                    $error[] = $name . ' => Unable to register file' . (is_array($msg) && array_key_exists('message', $msg) ? ', <br>' . $msg['message'] : '');
                }
            }

            $ret = array();

            if(count($created) > 0){
                $ret[] = 'Created:<br>' . implode('<br>', $created);
            }
            if($exists > 0){
                $ret[] = 'Already registered: ' . $exists;
            }
            if(count($skipped) > 0){
                $ret[] = 'Skipped:<br>' . implode('<br>', $skipped);
            }
            if(count($error) > 0){
                $ret[] = 'Errors:<br>' . implode('<br>', $error);
            }

            $ret = implode('<br><br>', $ret);
        }

        if($ret===false){
            $mysqli->rollback();
        }else{
            $mysqli->commit();    
        }

        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
        if($ret && $is_csv_import){
            $ret = 'Uploaded / registered: '.$cnt_imported.' media resources. ';
            if($cnt_skipped>0){
                $ret = $ret.' Skipped/already exist: '.$cnt_skipped.' media resources';    
            }
        }

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
    //  get information for information for uploaded file
    //
    private function getFileInfoForReg($file, $newname){
        
        if(!is_a($file, 'stdClass')){
            
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
                
                //!!!!! ,'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt)); //backward capability            
                
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

            if (!in_array($type, [ 'jpg', 'jpeg', 'jpe', 'jfif', 'gif', 'png' ])) {
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
        
        $filename = fileNameSanitize($newname.'.'.$type);

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
    * @param mixed $needclean - remove file from temp location after reg
    * @returns record or false
    */
    public function registerFile($file, $newname, $needclean = true, $tiledImageStack=false, $_fields=null){
        
       $this->records = null; //reset 
        
       $fields = $this->getFileInfoForReg($file, $newname);
                    
       if($fields!==false){             
           
                if($tiledImageStack){
                    //special case for tiled images stack
                    $fields['ulf_OrigFileName'] = '_tiled@'.substr($fields['ulf_OrigFileName'],0,-4);
                    $fields['ulf_PreferredSource'] = 'tiled';
                }else{
                    $fields['ulf_PreferredSource'] = 'local';
                }
                
                if(@$_fields['ulf_Description']!=null){
                    $fields['ulf_Description'] = $_fields['ulf_Description'];
                }
           
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
    * Download url to server and register as local file
    * 
    * $validate_same_file - 0: don't validate at all, 1: validata name only, 2: name and hash
    * if the same name exists - returns ulf_ID of existing registered file
    * 
    * @param mixed $url
    */
    public function downloadAndRegisterdURL($url, $fields=null, $validate_same_file=0){
        
        $orig_name = basename($url); //get filename
        if(strpos($orig_name,'%')!==false){
            $orig_name = urldecode($orig_name);
        }
        
        $ulf_ID_already_reg = 0;
        
        if($orig_name){
            if($validate_same_file>0){
                //check filename
                $mysqli = $this->system->get_mysqli();
                $query2 = 'SELECT ulf_ID, concat(ulf_FilePath,ulf_FileName) as fullPath FROM recUploadedFiles '
                .'WHERE ulf_OrigFileName="'.$mysqli->real_escape_string($orig_name).'"';    
                $fileinfo = mysql__select_row($mysqli, $query2);
                if($fileinfo!=null){
                    
                    $filepath = $fileinfo[1];
                    $filepath = resolveFilePath($filepath);
                    
                    if(file_exists($filepath))
                    {
                        $ulf_ID_already_reg = $fileinfo[0];
                        
                        if($validate_same_file==1){
                            //already exist
                            return $ulf_ID_already_reg;
                        }else if($validate_same_file==2){
                            //get file hash of already registered local file
                            $old_md5 = md5_file($filepath);    
                        }
                    }
                }
            }
        }
        
        $tmp_file = HEURIST_SCRATCH_DIR.$orig_name;
        
        if(saveURLasFile($url, $tmp_file)>0){
            
            if($validate_same_file==2 && $ulf_ID_already_reg>0){
                //check file hash
                $new_md5 = md5_file($tmp_file);
                if($old_md5==$new_md5){
                    //skip - this file is quite the same
                    unlink($tmp_file);
                    return $ulf_ID_already_reg;
                }
            }
            
            //temp file will be removed in save method
            $ulf_ID = $this->registerFile($tmp_file, null, false, false, $fields);
            if($ulf_ID && is_array($ulf_ID)) $ulf_ID = $ulf_ID[0];
            
            return $ulf_ID;
        }else{
            return false;
        }

    }
    
    /**
    * Register remote resource - used to fix flaw in database - detail type "file" has value but does not have registered
    * It may happen when user converts text field to "file"
    * 
    * $dtl_ID - update recDetails as well
    * 
    * @param mixed $url
    * @param mixed $generate_thumbmail
    */
    public function registerURL($url, $tiledImageStack=false, $dtl_ID=0, $fields=null){
        
       $this->records = null; //reset 
       
       if($fields==null)$fields = array();
       $fields['ulf_PreferredSource'] = $tiledImageStack?'tiled':'external';
       $fields['ulf_OrigFileName']    = $tiledImageStack?'_tiled@':'_remote';  //or _iiif
       $fields['ulf_ExternalFileReference'] = $url;

       if(!@$fields['ulf_MimeExt']){
           if($tiledImageStack){
                $fields['ulf_MimeExt'] = 'png';
           }else{
               $ext = recognizeMimeTypeFromURL($this->system->get_mysqli(), $url);
               if(@$ext['extension']){
                   $fields['ulf_MimeExt'] = $ext['extension'];
               }else{
                   $fields['ulf_MimeExt'] = 'bin';  //default value
               }
           }
       }
       $fields['ulf_UploaderUGrpID'] = $this->system->get_user_id(); 

       $fileinfo = array('entity'=>'recUploadedFiles', 'fields'=>$fields);
                
       $this->setData($fileinfo);
       $ulf_ID = $this->save();
       if($ulf_ID && is_array($ulf_ID)) $ulf_ID = $ulf_ID[0];
       
       if( $ulf_ID>0 && $dtl_ID>0 ){ //register in recDetails
               
               //update in recDetails
               $query2 = 'update recDetails set dtl_Value=null, `dtl_UploadedFileID`='.$ulf_ID
                                            .' where dtl_ID='.$dtl_ID;
                                            
               $this->system->get_mysqli()->query($query2);

               //get full file info
               $fileinfo = fileGetFullInfo($this->system, $ulf_ID);
               if(is_array($fileinfo) && count($fileinfo)>0){
                    return $fileinfo[0];
               }

       }
       return $ulf_ID;           
       
    }   

    
}
?>
