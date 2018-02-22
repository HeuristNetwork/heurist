<?php
/**
* fileUpload.php - file uploader handler
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
require_once(dirname(__FILE__)."/../System.php");
require_once(dirname(__FILE__).'/../../ext/jquery-file-upload/server/php/UploadHandler.php');
require_once(dirname(__FILE__).'/../dbaccess/dbRecUploadedFiles.php');

$response = null;
$system = new System();

$content_length = (int)@$_SERVER['CONTENT_LENGTH'];
            
$post_max_size = $system->get_php_bytes('post_max_size');
if ($post_max_size && ($content_length > $post_max_size)) {
    $error = 'The uploaded file exceeds the post_max_size directive in php.ini';
    $response = $system->addError(HEURIST_INVALID_REQUEST, $error);                
}else
if($system->init(@$_REQUEST['db'])){

    //define upload folder   HEURIST_FILESTORE_DIR/ $_REQUEST['entity'] /
    $entity_name = @$_REQUEST['entity'];
    $recID = @$_REQUEST['recID'];
    
    if(!$entity_name){
            $response = $system->addError(HEURIST_INVALID_REQUEST, "'entity' parameter is not defined");
    }else if ( !$system->has_access() ) { //not logged in
            $response = $system->addError(HEURIST_REQUEST_DENIED);
    }else if ($entity_name=='sysGroups' || $entity_name=='sysUsers') {
            if(!$system->has_access($recID)){ //only user or group admin
              $response = $system->addError(HEURIST_REQUEST_DENIED);
            }
    }else if(!($entity_name=='recUploadedFiles' || $entity_name=='sysBugreport'))
    { //for all other entities other than recUploadedFile must be admin of dbowners group
            if(!$system->is_admin()){
              $response = $system->addError(HEURIST_REQUEST_DENIED);
            }
    }
}else{
    $response = $system->getError();
}

if($response!=null){
    header('Content-type: application/json');
    print json_encode($response);
    exit();
}
    
    
/*
        'thumbnail' => array(
                    'upload_dir' => dirname($this->get_server_var('SCRIPT_FILENAME')).'/icons/',
                    'upload_url' => $this->get_full_url().'/icons/',
                    'crop' => true,
                    'max_width' => 18,
                    'max_height' => 18
*/    
    //error_reporting(E_ALL | E_STRICT);

//define options for upload handler    
    
    if($entity_name=="terms"){//for terms from old term management - upload term image

    $options = array(
            'upload_dir' => HEURIST_FILESTORE_DIR.'term-images/',
            'upload_url' => HEURIST_FILESTORE_URL.'term-images/',
            'unique_filename' => false,
            'newfilename' => @$_REQUEST['newfilename'],
            'correct_image_extensions' => true,
            'image_versions' => array(
                ''=>array(
                    'max_width' => 400,
                    'max_height' => 400,
                    'scale_to_png' => true    
                )
            )
    );
    
    }else if($entity_name=="temp"){//redirect uploaded content back to client side after some processing
                                   // for example in term list import 
    
        $options = array(
                'upload_dir' => HEURIST_FILESTORE_DIR.'scratch/',
                'upload_url' => HEURIST_FILESTORE_URL.'scratch/',
                'max_file_size' => @$_REQUEST['max_file_size'],
                // 'unique_filename' => false,  force unique file name
                //'image_versions' => array()
                //'print_response' => false,
                //'download_via_php' => 1
                );
                
    }else if($entity_name=="recUploadedFiles"){
        
        $options = array(
                'upload_dir' => HEURIST_SCRATCH_DIR,
                'upload_url' => HEURIST_FILESTORE_URL.'scratch/', //file_uploads/
                'unique_filename' => false,
                'newfilename' => @$_REQUEST['newfilename'],
                'correct_image_extensions' => true,
                'image_versions' => array(
                    'thumbnail'=>array(
                        'upload_dir' => HEURIST_SCRATCH_DIR.'thumbs/',//'filethumbs/',
                        'upload_url' => HEURIST_FILESTORE_URL.'scratch/thumbs/',
                        'max_width' => 200,
                        'max_height' => 200,
                        'scale_to_png' => true    
                    )
                )
                //'max_file_size' => 1024,
                //'print_response ' => false
        );
        
    
    }else{

        $options = array(
                'upload_dir' => HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/',
                'upload_url' => HEURIST_FILESTORE_URL.'entity/'.$entity_name.'/',
                'unique_filename' => false,
                'newfilename' => @$_REQUEST['newfilename'],
                'correct_image_extensions' => true,
                'image_versions' => array(
                    'thumbnail'=>array(
                        'max_width' => 120,
                        'max_height' => 120,
                        'scale_to_png' => true    
                    )
                )
                
                //'max_file_size' => 1024,
                //'print_response ' => false
        );

    
    }
    
    $options['print_response'] = false;
    
    $upload_handler = new UploadHandler($options);  // from 3d party uploader
    
    //@todo set print_response=false
    //and send to client standard HEURIST response
    $response = null;
    $res = $upload_handler->get_response(); //it returns file object

    foreach($res['files'] as $file){
        if(@$file->error){
            $response = $system->addError(HEURIST_UNKNOWN_ERROR, "File cannot be processed ".$file->error, null);
            break;            
        }else if($entity_name=="recUploadedFiles"){
            
            $ret = registerUploadedFile($system, $file); //it returns ulf_ID
            if( is_bool($ret) && !$ret ){
                $response = $system->getError();
            }else{
                $file->ulf_ID = $ret[0];
            }
        }
    }
    if($response==null){
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }
    header('Content-type: application/json');
    print json_encode($response);
    

//
// copy temp file into file upload folder and register file in table
//    
function registerUploadedFile($system, $file){
    
    $errorMsg = null;        
    $tmp_name = HEURIST_SCRATCH_DIR.$file->name;
    if(file_exists($tmp_name)){
        
            $fields = array();
            /* clean up the provided file name -- these characters shouldn't make it through anyway */
            $name = $file->original_name;
            $name = str_replace("\0", '', $name);
            $name = str_replace('\\', '/', $name);
            $name = preg_replace('!.*/!', '', $name);
            
            $fields = array(    
            'ulf_OrigFileName' => $name,
            'ulf_MimeExt' => $file->type, //extension or mimetype allowed
            'ulf_FileSizeKB' => ($file->size<1024?1:intval($file->size/1024)),
            'ulf_FilePath' => 'file_uploads/'); //relative path to HEURIST_FILESTORE_DIR - db root
            //,'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt)); //backward capability            
                
            $fileinfo = array('entity'=>'recUploadedFiles', 'fields'=>$fields);
            
            $entity = new DbRecUploadedFiles($system, $fileinfo);
            $ret = $entity->save();
            
            if($ret!==false){
                
                $records = $entity->records();
                
                $ulf_ID = $records[0]['ulf_ID'];
                $ulf_ObfuscatedFileID = $records[0]['ulf_ObfuscatedFileID'];
                
                //copy temp file from scratch to fileupload folder
                $tmp_name = HEURIST_SCRATCH_DIR.$file->name;
                $new_name = 'ulf_'.$ret[0].'_'.$name;
                
                if(file_exists($tmp_name)){
                
                    if( copy($tmp_name, HEURIST_FILES_DIR.$new_name) ) 
                    {
                        //remove temp file
                        unlink($tmp_name);
                        
                        //copy thumbnail
                        if(isset($file->thumbnailName)){
                            $thumb_name = HEURIST_SCRATCH_DIR.'thumbs/'.$file->thumbnailName;
                            if(file_exists($thumb_name)){
                                $new_name = HEURIST_THUMB_DIR.'ulf_'.$ulf_ObfuscatedFileID.'.png';
                                copy($thumb_name, $new_name);
                                //remove temp file
                                unlink($thumb_name);
                            }
                        }
                        
                    }else{
                        $errorMsg = "Upload file: $name couldn't be saved to upload path definied for db = "
                            . HEURIST_DBNAME.' ('.HEURIST_FILES_DIR
                            .'). Please ask your system administrator to correct the path and/or permissions for this directory';
                    }
                }
            
            }else{ 
                //remove temp file from scratch
                unlink($tmp_name);
            }
    }else{ //nearly impossible case
                        $errorMsg = 'Cant find temporary uploaded file: '.$file->name
                        .' for db = ' . HEURIST_DBNAME.' ('.HEURIST_SCRATCH_DIR
                        .'). Please ask your system administrator to correct the path and/or permissions for this directory';
    }
   
    if($errorMsg!=null){
        $system->addError(HEURIST_INVALID_REQUEST, $errorMsg);
        $ret = false;
    }
    
    return $ret;
}    
    
//
//  verification of uploaded file - @todo integrate with UploadHandler
//    
function postmode_file_selection() {

    $param_name = 'file';
    
    // there are two ways into the file selection mode;
    // either the user has just arrived at the import page,
    // or they've selected a file *and might progress to file-parsing mode*
    $error = '';
    if (@$_FILES[$param_name]) {
        if ($_FILES[$param_name]['size'] == 0) {
            $error = 'no file was uploaded';
        } else {
 //DEBUG print $_FILES['import_file']['error'];            
            switch ($_FILES[$param_name]['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = "The uploaded file was too large.  Please consider importing it in several stages.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = "Failed to write file to disk";
                    break;
                default:
                    $error = "Unknown file error";
            }
            
            
            $content_length = fix_integer_overflow((int)@$_SERVER['CONTENT_LENGTH']);
            
            $post_max_size = get_config_bytes(ini_get('post_max_size'));
            if ($post_max_size && ($content_length > $post_max_size)) {
                $error = 'The uploaded file exceeds the post_max_size directive in php.ini';
            }else{
                if ($_FILES[$param_name]['tmp_name'] && is_uploaded_file($_FILES[$param_name]['tmp_name'])) {
                    $file_size = get_file_size($_FILES[$param_name]['tmp_name']);
                } else {
                    $file_size = $content_length;
                }
                $file_max_size = get_config_bytes(ini_get('upload_max_filesize'));
                if ($file_max_size && ($content_length > $file_max_size)) {
                    $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                }
                
            }
        }

        if (!$error) {    // move on to the next stage!
            //$error = postmode_file_load_to_db($_FILES[$param_name]['tmp_name'], $_FILES[$param_name]['name'], true);
        }
    }

    return $error;
}    
?>
