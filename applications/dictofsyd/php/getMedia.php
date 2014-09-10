<?php
/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Script to obtain full size file or thumbnail image
*
* Used as main access point to media files in links
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

require_once("incvars.php");
require_once("utilsFile.php");

if (!@$_REQUEST['id']) return;

if(!$db_selected){
    $db_selected = mysql_connection_select();
}

$filedata = get_uploaded_file_info_internal($_REQUEST['id']);

$isthumbnail = (@$_REQUEST['thumb']==1);

if($isthumbnail){

    echo makeThumbnailImage($filedata, HEURIST_THUMB_DIR, true);

}else{

    $filename = $filedata['fullpath'];
    if(file_exists($filename))
    {
        downloadFile($filedata['mimeType'], $filename);
    }
    else if($filedata['URL']){ //not used here

        if($filedata['ext']=="kml"){
            // use proxy
            //downloadViaProxy(HEURIST_FILESTORE_DIR."proxyremote_".$filedata['id'].".kml", $filedata['mimeType'], $filedata['URL']);
        }else{
            header('Location: '.$filedata['URL']);
        }
    }
}
?>