<?php
/**
* UploadHandlerInit.php - Initialization script for jQuery File Uploads in Heurist.
*
* This script serves as the primary server-side entry point for the jQuery file upload widget,
* facilitating multi-file uploads. It is responsible for:
* 1. Sanitizing input request parameters.
* 2. Validating essential parameters, particularly the database name (`db`).
* 3. Setting up options for the `hserv\utilities\UploadHandler` class based on request parameters.
* 4. Instantiating the `UploadHandler`, which then processes the upload request (e.g., GET, POST, DELETE).
*
* If initial checks (like a missing or invalid `db` parameter) fail, this script will
* set appropriate HTTP error headers (e.g., 400 Bad Request, 403 Forbidden) and exit.
*
* Expected $_REQUEST parameters used for configuration:
* - `db` (string): Required. The name of the Heurist database to operate on.
* - `acceptFileTypes` (string, optional): A regex string defining allowed file types
*   (e.g., '/\.(gif|jpe?g|png)$/i'). Defaults to HEURIST_ALLOWED_EXT if not provided.
* - `unique_filename` (string, optional): If '0', attempts to overwrite files with the same name
*   (behavior further controlled by `replace_edited_file` option in UploadHandler).
*   Otherwise (default or '1'), generates unique filenames for uploads.
* - `max_file_size` (int, optional): Maximum allowed file size in bytes. Overrides server defaults if set.
* - `upload_subfolder` (string, optional): Specifies a subfolder within the database's
*   default filestore directory for uploads. If this parameter is provided, image versioning
*   (e.g., thumbnail generation) by the UploadHandler is typically disabled by clearing
*   the 'image_versions' option, keeping only the original image settings.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
* 
* @todo move controller
*/
error_reporting(E_ALL | E_STRICT);

use hserv\utilities\USanitize;
use hserv\utilities\UploadHandler;

require_once dirname(__FILE__).'/../../autoload.php';

$options = array();

$system = null;

$params = USanitize::sanitizeInputArray();


if(@$params['db']){
    $system = new hserv\System();//to init folder const without actual coonection to db

    $error = mysql__check_dbname(@$params['db']);
    if($error!=null){
        //database name is wrong
        header('HTTP/1.1 400 Bad Request');
        exit;
    }else{
        $dbname = $params['db'];
        $system->initPathConstants($dbname);
    }
    $options['database'] = $dbname;
}else{
    //database not defined
    header('HTTP/1.1 403 Forbidden');
    exit;
}


if(@$params['acceptFileTypes']!=null){
    $options['accept_file_types'] = $params['acceptFileTypes'];
}
if(@$params['unique_filename']!=null){
    $options['unique_filename'] = ($params['unique_filename']!='0');
}
if(@$params['max_file_size']>0){
    $options['max_file_size'] = $params['max_file_size'];
}
if(@$params['upload_subfolder']){
    $options['upload_subfolder'] = $params['upload_subfolder'];
    $options['image_versions'] = array('' => array('auto_orient' => true)); //disable thumbnails
}

//if(@$_REQUEST['upload_folder']){
//    $options['upload_dir'] = $_REQUEST['upload_folder'];
//}
$upload_handler = new UploadHandler($options);

