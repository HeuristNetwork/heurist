<?php
/**
* Service
* fileUpload.php - file uploader handler
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
require_once(dirname(__FILE__)."/../System.php");
require_once(dirname(__FILE__).'/../entity/dbRecUploadedFiles.php');
require_once(dirname(__FILE__).'/../utilities/utils_file.php');
require_once(dirname(__FILE__).'/../utilities/utils_image.php');
require_once('UploadHandler.php');

$response = null;
$system = new System();

$content_length = (int)@$_SERVER['CONTENT_LENGTH'];
            
$post_max_size = get_php_bytes('post_max_size');
if ($post_max_size && ($content_length > $post_max_size)) {
    $error = 'The uploaded file exceeds the post_max_size directive in php.ini';
    $response = $system->addError(HEURIST_INVALID_REQUEST, $error);                
}else
if($system->init(@$_REQUEST['db'])){

    //define upload folder   HEURIST_FILESTORE_DIR/ $_REQUEST['entity'] /
    $entity_name = @$_REQUEST['entity'];
    $is_autodect_csv = (@$_REQUEST['autodect']==1);
    $recID = @$_REQUEST['recID'];
    $registerAtOnce = (@$_REQUEST['registerAtOnce']==1);
    
    if ( !$system->has_access() ) { //not logged in
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
    header('Content-type: application/json;charset=UTF-8');
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
    
    if(!$entity_name){
        
        //direct upload from manageFileUpload
        $options = array(
                'upload_dir' => HEURIST_FILESTORE_DIR.'insitu/',
                'upload_url' => HEURIST_FILESTORE_URL.'insitu/',
                'unique_filename' => false,
                'correct_image_extensions' => true,
                'image_versions' => array(
                    ''=>array(
                        'auto_orient' => true,
                        ),
                    'thumbnail'=>array(
                        'auto_orient' => true,
                        'upload_dir' => HEURIST_THUMB_DIR,
                        'upload_url' => HEURIST_THUMB_URL,
                        'max_width' => 400,
                        'max_height' => 400,
                        'scale_to_png' => true    
                    )
                )
        );
/*
  it was form parameters in manageFilesUpload        
            <input type="hidden" name="upload_thumb_dir" value="<?php echo HEURIST_THUMB_DIR; ?>"/>
            <input type="hidden" name="upload_thumb_url" value="<?php echo (defined('HEURIST_THUMB_URL')?HEURIST_THUMB_URL:''); ?>"/>
*/        
        //$response = $system->addError(HEURIST_INVALID_REQUEST, "'entity' parameter is not defined");
            
    }else
    if($entity_name=="terms"){//for terms from old term management - upload term image

        $options = array(
                'upload_dir' => HEURIST_FILESTORE_DIR.'term-images/',
                'upload_url' => HEURIST_FILESTORE_URL.'term-images/',
                'unique_filename' => false,
                'newfilename' => @$_REQUEST['newfilename'],
                'correct_image_extensions' => true,
                'image_versions' => array(
                    ''=>array(
                        'auto_orient' => true,
                        'max_width' => 400,
                        'max_height' => 400,
                        'scale_to_png' => true    
                    )
                )
        );

        allowWebAccessForForlder(HEURIST_FILESTORE_DIR.'term-images/');
    
    }else if($entity_name=="temp"){//redirect uploaded content back to client side after some processing
                                   // for example in term list import 
           
        $max_file_size = @$_REQUEST['max_file_size'];                           
        if($max_file_size>0){
// it does not work             
//            file_put_contents(HEURIST_FILESTORE_DIR.'scratch/.htaccess', 
//                "php_value post_max_size $max_file_size\nphp_value upload_max_filesize $max_file_size");
        }
    
        $options = array(
                'upload_dir' => HEURIST_FILESTORE_DIR.'scratch/',
                'upload_url' => HEURIST_FILESTORE_URL.'scratch/',
                'max_file_size' => $max_file_size,
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
                    ''=>array(
                        'auto_orient' => true,
                        ),
                    'thumbnail'=>array(
                        'auto_orient' => true,
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
        
        allowWebAccessForForlder(HEURIST_SCRATCH_DIR.'thumbs/');
    
    }else{
        
        $entityDir = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/';
        
        $version = @$_REQUEST['version']!='icon'?'thumbnail':'icon';
        $maxsize = @$_REQUEST['maxsize']>0?$_REQUEST['maxsize']:120;

        $options = array(
                'upload_dir' => $entityDir,
                'upload_url' => HEURIST_FILESTORE_URL.'entity/'.$entity_name.'/',
                'unique_filename' => false,
                'newfilename' => @$_REQUEST['newfilename'],
                'correct_image_extensions' => true,
                'image_versions' => array(
                    ''=>array(
                        'auto_orient' => true,
                        ),
                    $version=>array(
                        'auto_orient' => true,
                        'max_width' => $maxsize,
                        'max_height' => $maxsize,
                        'scale_to_png' => true    
                    )
                )
                
                //'max_file_size' => 1024,
                //'print_response ' => false
        );

        allowWebAccessForForlder($entityDir.$version.'/');    
    
    }
    
    $options['print_response'] = false;
    
    $upload_handler = new UploadHandler($options);  // from 3d party uploader
    
    //@todo set print_response=false
    //and send to client standard HEURIST response
    $response = null;
    $res = $upload_handler->get_response(); //it returns file object

    foreach($res['files'] as $idx=>$file){
        if(@$file->error){
            $sMsg = "Sorry, file was not processed due to the following reported error: ".$file->error.".\n\n";
            
            if(strpos($file->error, 'ownership permissions')==false){
                $sMsg = $sMsg.' The most likely cause is that the file extension ('. ($file->type?$file->type:'XXX!') .') is not currently enabled for the upload function, jquery UploadHandler. Please use the bug report link above to request addition of this file type.';
            }
            
            $response = $system->addError(HEURIST_UNKNOWN_ERROR, $sMsg, null);

            break;            
        }else if($entity_name=="recUploadedFiles"){ //register at once
            
            if($registerAtOnce==1){
            
                $entity = new DbRecUploadedFiles($system, array('entity'=>'recUploadedFiles'));
                $ret = $entity->registerFile($file, null); //it returns ulf_ID
                
                if( is_bool($ret) && !$ret ){
                    $response = $system->getError();
                }else{
                    $file->ulf_ID = $ret;
                }
            }else if(!@$file->thumbnailUrl){ //if UploadHandler does not create thunb - creates it as image with extension
                
                $thumb_file = HEURIST_SCRATCH_DIR.'thumbs/'.@$_REQUEST['newfilename'];
                $img = image_CreateFromString($file->type?$file->type:'XXX!');
                imagepng($img, $thumb_file);//save into file
                imagedestroy($img);
                $res['files'][$idx] ->thumbnailUrl = HEURIST_FILESTORE_URL.'scratch/thumbs/'.@$_REQUEST['newfilename'];
            }
                
        }else if($entity_name=="temp" && $is_autodect_csv) {
            
            
            $filename = HEURIST_FILESTORE_DIR.'scratch/'.$file->original_name;
            
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $isKML = ($extension=='kml' || $extension=='kmz');
            if($isKML){ //no need to detect params for kml
                $res['files'][$idx]->isKML = true;
            }else{
                $csv_params = autoDetectSeparators( $filename );
                if(is_array($csv_params) && !@$csv_params['error']){
                    $res['files'][$idx]->csv_params = $csv_params;
                }
            }
        }
    }
    if($response==null){
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }
    header('Content-type: application/json;charset=UTF-8');
    print json_encode($response);

//------------
//  NOT USED. verification of uploaded file integrated with UploadHandler
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
            
            $post_max_size = get_php_bytes('post_max_size');
            if ($post_max_size && ($content_length > $post_max_size)) {
                $error = 'The uploaded file exceeds the post_max_size directive in php.ini';
            }else{
                if ($_FILES[$param_name]['tmp_name'] && is_uploaded_file($_FILES[$param_name]['tmp_name'])) {
                    $file_size = get_file_size($_FILES[$param_name]['tmp_name']);
                } else {
                    $file_size = $content_length;
                }
                $file_max_size = get_php_bytes('upload_max_filesize');
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
