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

$response = null;
$system = new System();
if($system->init(@$_REQUEST['db'])){

    //define upload folder   HEURIST_FILESTORE_DIR/ $_REQUEST['entity'] /
    $entity_name = @$_REQUEST['entity'];
    $recID = @$_REQUEST['recID'];
    
    if(!$entity_name){
            $response = $system->addError(HEURIST_INVALID_REQUEST, "'entity' parameter is not defined");
    }else if ( $system->get_user_id()<1 ) {
            $response = $system->addError(HEURIST_REQUEST_DENIED);
    }else if ($entity_name=='sysUGrps') {
            if(!$system->is_admin2($recID)){ //only user or group admin
              $response = $system->addError(HEURIST_REQUEST_DENIED);
            }
    } if($entity_name!='recUploadedFiles'){ //for all other entities other than recUploadedFile must be admin of dbowners group
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

    if($entity_name=="terms"){//for terms from old term management

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
    
    $options = array(
            'upload_dir' => HEURIST_FILESTORE_DIR.'scratch/',
            'upload_url' => HEURIST_FILESTORE_URL.'scratch/',
            'max_file_size' => 1024*1024,
            //'print_response' => false,
            //'download_via_php' => 1
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
    
    $upload_handler = new UploadHandler($options);
    
    //@todo set print_response=false
    //and send to client standard HEURIST response
    $response = null;
    $res = $upload_handler->get_response();

    foreach($res['files'] as $file){
        if(@$file->error){
            $response = $system->addError(HEURIST_UNKNOWN_ERROR, "File can not be processed", $file->error);
            break;            
        }
    }
    if($response==null){
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }
    header('Content-type: application/json');
    print json_encode($response);
?>
