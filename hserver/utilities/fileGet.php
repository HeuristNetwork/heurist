<?php
/**
* fileGet.php - get image for given entity, record ID, version and color 
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

$system = new System();
$system->initPathConstants(@$_REQUEST['db']);

$entity_name = @$_REQUEST['entity'];
$recID = @$_REQUEST['recID'];

if(!$entity_name){
    $system->addError(HEURIST_INVALID_REQUEST, "Entity parameter is not defined");



if($system->init()){

    //define upload folder   HEURIST_FILESTORE_DIR/ $_REQUEST['entity'] /
    $entity_name = @$_REQUEST['entity'];
    $recID = @$_REQUEST['recID'];
    
    if(!$entity_name){
            $system->addError(HEURIST_INVALID_REQUEST, "Entity parameter is not defined");
    }
    if(!($recID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Record ID parameter is not defined");
    }

    $filename = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/',

    $version  = @$_REQUEST['version'];
    if($version!=null){
        $filename =  $filename.$version.'/';   
    }
    //find file by recID
    
    
    
    if(file_exists($filename)){
    
        ob_start();    
        header('Content-type: image/png');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();        
        readfile($filename);
    
    }
?>
