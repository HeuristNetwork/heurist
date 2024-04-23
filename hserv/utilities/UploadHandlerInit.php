<?php
    /**
    * UploadHandlerInit.php
    * Entry point for fileupload widget. It is used in multi file uploads. 
    * For single file upload see fileUpload.php
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     6
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


error_reporting(E_ALL | E_STRICT);

require_once dirname(__FILE__).'/../System.php';

$options = array();

$system = null;

if(@$_REQUEST['db']){
    $system = new System(); //to init folder const without actual coonection to db
    $dbname = @$_REQUEST['db'];
    $error = System::dbname_check($dbname);
    if($error){
        exit;
    }else{
        $system->initPathConstants($dbname);
    }
    $options['database'] = $dbname;
}else{
    //database not defined
    header('HTTP/1.1 403 Forbidden');
    exit;
}


if(@$_REQUEST['acceptFileTypes']!=null){
    $options['accept_file_types'] = $_REQUEST['acceptFileTypes'];   
}
if(@$_REQUEST['unique_filename']!=null){
    $options['unique_filename'] = ($_REQUEST['unique_filename']!='0');   
}
if(@$_REQUEST['max_file_size']>0){
    $options['max_file_size'] = $_REQUEST['max_file_size']; 
}
if(@$_REQUEST['upload_subfolder']){
    $options['upload_subfolder'] = $_REQUEST['upload_subfolder']; 
}

//if(@$_REQUEST['upload_folder']){
//    $options['upload_dir'] = $_REQUEST['upload_folder'];   
//}

require_once 'UploadHandler.php';
$upload_handler = new UploadHandler($options);

