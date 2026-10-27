<?php
/**
* fileGet.php - handler to get icons and thumbs for entities
*
* 1) get image for given entity, record ID, version and color
* 2) get or check file from code folders - tips, help, doc content
* 3) load file from scratch folder (tries to convert to UTF8) - for import terms
* (for recUploadFiles see fileDownload.php)
* 
* Parameters:
* db - The target database name.
* entity - The name of the entity type (e.g., rst, dty) for which to retrieve an image.
* id - The record ID of the specific entity instance.
* version - (Optional) Specifies the image version to retrieve, typically 'icon' or 'thumb'.
* color - (Optional) Specifies the color for the icon (if applicable and not an SVG).
* circle - (Optional) If set, renders the icon within a circle.
* bg - (Optional) Specifies the background color of the circle (if `circle` is used).
* 
* Special parameter for csv import file downloading:
* file - The name of the CSV file (located in the scratch folder) to be downloaded.
* encoding - (Optional) The character encoding of the CSV file, if it's not UTF-8 (will be converted to UTF-8).
* 
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

use hserv\utilities\UImage;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';
require_once 'entityScrudSrv.php';

/*

id - record id
entity - by default recordtypes
def - if file not found return empty gif (0) or add image gif (1) or default icon/thumb for entity (2)
version - thumb or thumbnail|icon|full (thumb is default)

*/

$req_params = USanitize::sanitizeInputArray();

//main purpose - download entity images
$db = @$req_params['db'];
$filename = basename(@$req_params['file'] ?? '');
$entity_name = htmlspecialchars(@$req_params['entity'] ?? '');

$error = mysql__check_dbname($db); // HOSTISSUE !!!

if($error!==null){

    if(!$entity_name){ //print error as text
        print htmlspecialchars($error);
    }else{
        _download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif', 'image/gif');
    }
    exit;
}

$system = new hserv\System();

if($filename){ // Private import files require the uploading user and session.
    if (!$system->init($db)) {
        http_response_code(400);
        exit('Invalid database parameter');
    }
    try {
        $copy = \hserv\utilities\ScratchFileAccess::downloadCopy($system, $filename, $req_params['encoding'] ?? null);
        try {
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            header('Pragma: no-cache');
            header(CONTENT_LENGTH . fstat($copy)['size']);
            fpassthru($copy);
        } finally {
            fclose($copy);
        }
    } catch (\RuntimeException $error) {
        $code = $error->getCode();
        http_response_code(in_array($code, [400, 401, 403, 404, 500], true) ? $code : 500);
        header('Cache-Control: no-store');
        echo htmlspecialchars($error->getMessage());
    }
    exit;
}else{  //download entity images (icons, thumbs) for entity folder in HEURIST_FILESTORE_DIR
        $system->initPathConstants($db);


        $content_type = 'image/png';

        $rec_id = @$req_params['icon'];
        if($rec_id==null) {$rec_id = @$req_params['id'];}

        //icon, thumb, full
        $viewmode = rawurlencode(@$req_params['version']);

        if($rec_id && substr($rec_id,0,4)=='term'){
            //backward support - icons for Digital Harlem
            $rec_id = substr($rec_id, 4);
            $entity_name  = 'trm';
            $viewmode = 'icon';
        }elseif($rec_id && strpos($rec_id, 'thumb/th_')===0){
            //backward support - thumbnail for rectype import
            $rec_id = substr($rec_id, 9);
            $entity_name  = 'rty';
            $viewmode = 'thumb';

        }elseif(!$entity_name) {
            $entity_name = 'rty';//default - defRecTypes
        }

        $entity_name = entityResolveName($entity_name);

        if(!$entity_name){
            exit;
        }

        if(strpos($rec_id, '.png')>0){
            $rec_id = substr($rec_id, 0, -4);
        }

        list($filename, $content_type, $file_url) = resolveEntityFilename($entity_name, $rec_id, $viewmode, $db);


        //entity id either not defined or requested file doesn't exist
        //editmode: empty gif (0) or add image gif (1) or default icon/thumb for entity (2), or (check)  'ok' if it exists or '' missing

        //what return by default?
        // 3 (or check) - return "ok" if file exists otherwise "not found"
        // 2 - entity default icon or thumb
        // 1 - returns image with invitation "add image"
        // otherwise it returns empty image placeholder (100x100 or 16x16 for icons)
        $default_mode = @$req_params['def'];
        if($default_mode=='check') {$default_mode = 3;}
        elseif($default_mode==null) {$default_mode = 2;}

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if(file_exists($filename) && !is_dir($filename)){
            if($default_mode==3){ //check

                $response = array('status'=>HEURIST_OK, 'data'=>'ok');
                header(CTYPE_JSON);
                print json_encode($response);

            }else{

                //color, bg, circle
                if(@$req_params['color'] && $ext!='svg'){
                    UImage::changeImageColor($filename, null, @$req_params['color'], @$req_params['circle'], @$req_params['bg']);
                }else{
                    if($file_url!=null && isset($allowWebAccessEntityFiles) && $allowWebAccessEntityFiles){
                        $file_url .= (strpos($file_url, '?') !== false ? '&' : '?') . 'v=' . filemtime($filename);
                        header('Location:'.$file_url);
                    }else{
                        _download_file($filename, $content_type);
                    }
                }
            }

        }elseif($default_mode==3){ //check existance

                $response = array('status'=>HEURIST_OK, 'data'=>'not found');
                header(CTYPE_JSON);
                print json_encode($response);

        }else{

            if($entity_name && ($default_mode=='view' || $default_mode==2)) //get entity default icon or thumb
            {
                //at the moment we don't have full images that describe entity - only icons and thumbs
                $filename = dirname(__FILE__).'/../../hclient/assets/'
                                .$entity_name.(($viewmode=='icon')?'':'_thumb').'.png';

                if(file_exists($filename) && !is_dir($filename)){
                    _download_file($filename, $content_type);
                    exit;
                }
            }

            if ($default_mode=='edit' || $default_mode==1){ //show invitation to add image
                _download_file(dirname(__FILE__).'/../../hclient/assets/100x100click.png', $content_type);
            }else {
                $content_type = 'image/gif';
                if($viewmode == 'icon'){
                    _download_file(dirname(__FILE__).'/../../hclient/assets/16x16.gif', $content_type);
                }else{
                    _download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif', $content_type);
                }
            }

        }
        exit;
}


/**
 * Outputs a file for download.
 *
 * Sets appropriate headers and reads the file content to the output buffer.
 *
 * @param string $filename The path to the file to be downloaded.
 * @param string|null $content_type The MIME type of the file. If null, it's omitted from headers.
 * @return void
 */
function _download_file($filename, $content_type){

        ob_start();
        if($content_type) {header('Content-type: '.$content_type);}
        header('Pragma: public');
        header(CONTENT_LENGTH . filesize($filename));
        @ob_clean();
        flush();
        readfile($filename);
}
?>