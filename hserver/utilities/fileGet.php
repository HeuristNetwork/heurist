&nbsp;<?php
/**
* 
* fileGet.php - 1) get image for given entity, record ID, version and color 
*               2) get or check file from code folders - tips, help, doc content
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
//require_once(dirname(__FILE__).'/../../ext/jquery-file-upload/server/php/UploadHandler.php');

//secondary purpose - check file existance in code
$path = @$_REQUEST['check'];
if($path){
  if(file_exists(HEURIST_DIR.$path)){
        print 'ok';      
  }else{
        print '';
  }
  exit();
}

//main purpose - download entity images
$entity_name = @$_REQUEST['entity'];
$recID = @$_REQUEST['recID'];
$db = @$_REQUEST['db'];
$filename = @$_REQUEST['file'];

$error = null;

if(!$db){
    $error = "Db parameter is not defined";
}else 
if(!$entity_name && !$filename){
    $error = "Entity parameter is not defined";
}else 
if(!$filename){
    $error = "File name not defined"; //to get file from scratch
}else
if($entity_name && !($recID>0)){
    $error = "Entity ID is not defined";
}else{

    $system = new System(); //without db connection
    $system->initPathConstants($db);
    
    if($filename){ //download from scratch
        
        $file_read = HEURIST_FILESTORE_DIR.'scratch/'.$filename;

        $content_type = null;//'image/'.$file_ext;
        
    }else{

        $filename = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/';

        $version  = @$_REQUEST['version'];
        if($version=='thumbnail' || $version=='icon'){
            $filename =  $filename.$version.'/';   
        }
        //find file by recID
        $filename = $filename.$recID.'.';
        
        $exts = array('png','jpg','jpeg','gif');
        
        $file_read = HEURIST_DIR.'hclient/assets/13x13.gif';
        $file_ext = 'gif';
        
        foreach ($exts as $ext) {
           
            if(file_exists($filename.$ext)){
                $file_read = $filename.$ext;
                $file_ext = $ext;
                break;
            }        
        }
        
        $content_type = 'image/'.$file_ext;
    }
    
    ob_start();    
    if($content_type) header('Content-type: '.$content_type);
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_read));
    @ob_clean();
    flush();        
    readfile($file_read);
    
}

if($error){
    if(!$entity_name){ //print error as text
        print $error;
    }else{ //@todo return error image
    
    }
}
?>

