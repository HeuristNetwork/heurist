<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
* filename, brief description, date of creation, by whom
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristNetwork.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/



require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

if (! defined("USING-XSS")) {
    function outputAsRedirect($text) {
        $val = base64_encode($text);
        header("Location: ".HEURIST_BASE_URL."/#data=" . $val);
        return "";
    }
    ob_start("outputAsRedirect");

    if ($_POST["heurist-sessionid"] != $_COOKIE["heurist-sessionid"]) {	// saw TODO: check that this is ok or should this be the database session?
        // saveFile is only available through dispatcher.php, or if heurist-sessionid is known (presumably only our scripts will know this)
        jsonError("unauthorised HAPI user");
    }
}

if (! is_logged_in()) {
    jsonError("no logged-in user");
}

if ( $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) &&  empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0 ){

    jsonError("File is too large. ".$_SERVER['CONTENT_LENGTH']." bytes exceeds the limit of ".ini_get('post_max_size').
        ". Please get system administrator to increase the file size limits or load your large files on a video server or other suitable web service and use the URL to reference the file here");

}else{
    $upload = @$_FILES["file"];

    if($upload){

        mysql_connection_overwrite(DATABASE);

        mysql_query("start transaction");
        //POST Content-Length of 103399974 bytes exceeds the limit of 29360128 bytes in Unknown on line

        //$upload["type"]
        $fileID = upload_file($upload["name"], null, $upload["tmp_name"], $upload["error"], $upload["size"], $_REQUEST["description"], false);

        if (is_numeric($fileID)) {

            $file = get_uploaded_file_info($fileID, false);
            print json_format($file);

            mysql_query("commit");
        }
        else if ($fileID) {
            jsonError($fileID);
        }else if ($_FILES["file"]["error"]) {
            jsonError("uploaded file was too large");
        }
        else {
            jsonError("file upload was interrupted");
        }
    }else{
        jsonError("File data are not posted to server side");
    }
}

//***** END OF OUTPUT *****/


function jsonError($message) {
    mysql_query("rollback");
    print json_encode(array('error'=>$message));
    
    //print "{\"error\":\"" . addslashes($message) . "\"}";
}


?>
