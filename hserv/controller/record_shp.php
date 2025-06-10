<?php
/**
* record_shp.php - Handler to support shp+dbf for mapping widget
* 
* Converts shp+dbf files to geojson output or downloads zip archive based on Datasource record id.
*
* $_REQUEST parameters:
* recID    - The ID of the datasource record (containing SHP/DBF files or a ZIP archive).
* format   - (Optional) Specifies the output format. Possible values:
*            geojson - Converts the SHP/DBF data to GeoJSON format.
*            rawfile - Returns a zipped archive containing the original file(s) and metadata.
*            (not specified) - Acts as a proxy, downloading the original file(s) with appropriate HTTP headers.
* metadata - (Optional) If set to 1 and 'format' is 'rawfile', includes a text file with a link to a flat HML representation in the ZIP archive.
*
* When it generates geojson it simplifies path by removing extra points with given tolerance
*
* @uses mapSimplify.php
* @uses ShapefileAutoloader.php
*
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

    use hserv\utilities\USanitize;
    use hserv\utilities\UArchive;

    require_once dirname(__FILE__).'/../../autoload.php';

    require_once dirname(__FILE__).'/../records/search/recordSearch.php';
    require_once dirname(__FILE__).'/../utilities/geo/mapSimplify.php';
    //require_once dirname(__FILE__).'/../../vendor/autoload.php';//for ShapeFile

// Register autoloader
require_once '../../vendor/gasparesganga/php-shapefile/src/Shapefile/ShapefileAutoloader.php';
Shapefile\ShapefileAutoloader::register();

// Import classes
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader;

global $is_api;

    $response = array();

    $system = new hserv\System();

    $params = $_REQUEST;
    $is_api = (@$_REQUEST['api']!=0);

    if( ! $system->init(@$params['db']) ){
        //get error and response
        $system->errorExitApi(null, null, $is_api);//exit from script
    }
    if(!isPositiveInt(@$params['recID'])){
        $system->errorExitApi('recID parameter value is missing or invalid', null, $is_api);//exit from script
    }

    $need_simplify = (true || @$params['simplify']=='yes' || @$params['simplify']==1);

    $fields = array();

    if($system->defineConstant('DT_ZIP_FILE')){
        $fields[] = DT_ZIP_FILE;
    }else{
        define('DT_ZIP_FILE',0);
    }
    if($system->defineConstant('DT_SHAPE_FILE')){
        $system->defineConstant('DT_DBF_FILE');
        $system->defineConstant('DT_SHX_FILE');
        $fields[] = DT_SHAPE_FILE;
        $fields[] = DT_DBF_FILE;
        $fields[] = DT_SHX_FILE;
    }else{
        define('DT_SHAPE_FILE',0);
    }
    if($system->defineConstant('DT_FILE_RESOURCE')){
        $fields[] = DT_FILE_RESOURCE;
    }else{
        define('DT_FILE_RESOURCE',0);
    }
    if($system->defineConstant('DT_NAME')){
        $fields[] = DT_NAME;
    }


    if( empty($fields) ){
        $system->errorExitApi('Database '.$params['db']
                    .' does not have field definitions for shp, zip or simple resource file'
                    , HEURIST_SYSTEM_CONFIG, $is_api);//exit from script
    }
    $isZipArchive = false;

    $record = array("rec_ID"=>intval($params['recID']));
    recordSearchDetails($system, $record, $fields);

    if (@$record['details'] &&
       (@$record['details'][DT_SHAPE_FILE] || @$record['details'][DT_ZIP_FILE] || @$record['details'][DT_FILE_RESOURCE]))
    {

            $dbf_file = null;
            $shx_file = null;

            if(DT_ZIP_FILE>0 && @$record['details'][DT_ZIP_FILE]){
                $shp_file = fileRetrievePath(array_shift($record['details'][DT_ZIP_FILE]),'shp',true);
                $isZipArchive = true;

            }elseif(DT_SHAPE_FILE>0 && @$record['details'][DT_SHAPE_FILE]){

                $shp_file = fileRetrievePath(array_shift($record['details'][DT_SHAPE_FILE]),'shp',false);
                $dbf_file = fileRetrievePath(array_shift($record['details'][DT_DBF_FILE]),'dbf',false);
                if(@$record['details'][DT_SHX_FILE]){
                    $shx_file = fileRetrievePath(array_shift($record['details'][DT_SHX_FILE]),'shx',false);
                }

            }else{
                $shp_file = fileRetrievePath(array_shift($record['details'][DT_FILE_RESOURCE]),'shp',true);
                $isZipArchive = true;
            }

            if(@$params['format']=='rawfile'){

                $originalFileName = null;
                if(is_array($record['details'][DT_NAME])){
                    $originalFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
                }
                if(!$originalFileName) {$originalFileName = 'Dataset_'.$record['rec_ID'];}

                $file_zip = $originalFileName.'.zip';
                $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");
                $zip = new ZipArchive();
                if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
                    $system->errorExitApi("Cannot create zip $file_zip_full", null, $is_api);
                }else{
                    if(!$dbf_file){
                        $dbf_file = substr($shp_file,0,strlen($shp_file)-3).'dbf';
                    }
                    if(!$shx_file){
                        $shx_file = substr($shp_file,0,strlen($shp_file)-3).'shx';
                    }
                    $zip->addFile($shp_file, basename($shp_file) );
                    if(file_exists($dbf_file)){
                        $zip->addFile($dbf_file, basename($dbf_file) );
                    }
                    if(file_exists($shx_file)){
                        $zip->addFile($shx_file, basename($shx_file) );
                    }

                    if(@$params['metadata']){//save hml into scratch folder

                        $zip->addFromString($originalFileName.'.txt',
                                       recordLinksFileContent($system, $record));
                    }
                    $zip->close();
                    //donwload
                    $contentDispositionField = 'Content-Disposition: attachment; '
                        . sprintf('filename="%s";', rawurlencode($file_zip))
                        . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));

                    header('Content-Type: application/zip');
                    header($contentDispositionField);
                    header(CONTENT_LENGTH . filesize($file_zip_full));
                    readfile($file_zip_full);


                }

            }else{

                try {

                    if($dbf_file && file_exists($dbf_file)){

                        $files = array(
                            'shp'   => $shp_file,
                            'dbf'   => $dbf_file);

                        if($shx_file && file_exists($shx_file)){
                            $files['shx'] = $shx_file;
                        }
                        $shapeFile = new ShapefileReader($files, array(Shapefile::OPTION_IGNORE_FILE_SHX=>true));
                    }elseif(file_exists($shp_file)){
                        //if provide only shapefile, it finds other automatically
                        $shapeFile = new ShapefileReader($shp_file, array(Shapefile::OPTION_IGNORE_FILE_SHX=>true, Shapefile::OPTION_IGNORE_FILE_DBF=>true));
                    }else{
                        $system->errorExitApi('Cannot process shp file', HEURIST_ERROR, null);
                    }

                    $tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "exp");
                    $fd = fopen($tmp_destination, 'w');//less than 1MB in memory otherwise as temp file
                    fwrite($fd, '[');
                    $rec_cnt = 0;

                    // Read all the records
                    while ($record = $shapeFile->fetchRecord()){

                        // Skip the record if marked as "deleted"
                        if ($record->isDeleted()) {
                            continue;
                        }

                        /* v2 old way
                        $shapeFile->getRecord(Shapefile::GEOMETRY_GEOJSON_FEATURE)) { //GEOMETRY_WKT
                        if ($record['dbf']['_deleted']) {continue;}

                        $record['shp']
                        */

                        $feature = json_decode($record->getGeoJSON(false, true), true);
                        unset($record);


                        $geo = @$feature['geometry'];
                        if(!isEmptyArray(@$geo['coordinates'])){

                            if($geo['type']=='LineString'){

                                checkWGS($system, $geo['coordinates']);
                                if($need_simplify) {simplifyCoordinates($geo['coordinates']);}

                            } elseif($geo['type']=='Polygon'){
                                for($idx=0; $idx<count($geo['coordinates']); $idx++){
                                    checkWGS($system, $geo['coordinates'][$idx]);
                                    if($need_simplify) {simplifyCoordinates($geo['coordinates'][$idx]);}
                                }
                            } elseif ( $geo['type']=='MultiPolygon' || $geo['type']=='MultiLineString')
                            {
                                for($idx=0; $idx<count($geo['coordinates']); $idx++){ //shapes
                                    for($idx2=0; $idx2<count($geo['coordinates'][$idx]); $idx2++) //points
                                    {
                                        checkWGS($system, $geo['coordinates'][$idx][$idx2]);
                                        if($need_simplify) {simplifyCoordinates($geo['coordinates'][$idx][$idx2]);}
                                    }
                                }

                            }
                        }


                        if($rec_cnt>0) {fwrite($fd, ',');}
                        fwrite($fd, json_encode($feature));
                        $rec_cnt++;
                        if(memory_get_usage()>104857600){//100M //$rec_cnt>20 ||
                            break;
                        }
                    }//for records

                    fwrite($fd, ']');
                    $is_compressed = true;

                    if($is_compressed){
                        $output = gzencode(file_get_contents($tmp_destination), 6);
                        header('Content-Encoding: gzip');
                    }else{
                        $output = file_get_contents($tmp_destination);
                    }
                    fclose($fd);

                    header( CTYPE_JSON);
                    unlink($tmp_destination);

                    echo $output;
                    unset($output);


                } catch (ShapeFileException $e) {
                    // Print detailed error information
                    $system->errorExitApi('Cannot process shp file: '.$e->getMessage(), HEURIST_ERROR, $is_api);
                } catch (Exception $e) {
                    $system->errorExitApi('Cannot init ShapeFile library: '.$e->getMessage(), HEURIST_ERROR, $is_api);
                }

            }
    }else{
        $system->errorExitApi(
'Cannot process shp file. Please ask the owner of the layer data source record (id:'
.$params['recID']
.') to check that the file exists, is readable and has not been corrupted.',
            HEURIST_NOT_FOUND, $is_api);
    }

    $system->dbclose();

/**
 * Checks if the coordinates are within WGS84 bounds.
 *
 * Iterates through a sample of points (or all points) to check if their
 * longitude and latitude fall within the valid WGS84 range (-180 to 180 for longitude, -90 to 90 for latitude).
 * Exits with an error if coordinates are outside these bounds.
 *
 * @global bool $is_api Flag indicating if the request is an API call.
 * @param \hserv\System $system The system object.
 * @param array $orig_points An array of points, where each point is an array [longitude, latitude].
 * @param int|true $check_number_or_all The number of points to check, or true to check all points. Defaults to 3.
 * @return bool True if all checked points are within WGS84 bounds.
 */
function checkWGS($system, $orig_points, $check_number_or_all=3){

    global $is_api;

    $cnt = 0;
    foreach ($orig_points as $point) {
        //if not integer and less than 180/90 this is wgs
        if (!((abs($point[0])<200) && (abs($point[1])<90))){
                $system->errorExitApi(
'Cannot process shp file. Heurist uses WGS84 (World Geographic System) '
.'to support the plotting of maps worldwide. This shapefile is not in this format '
.'and will not therefore display on maps. '
.'Please use a GIS or other converter to convert to WGS84', HEURIST_ACTION_BLOCKED, $is_api);
        }

        if( $check_number_or_all===true || $cnt < $check_number_or_all ){
            $cnt++;
        }else{
            break;
        }
    }

    return true;
}

/**
 * Retrieves the path to a file, handling local files, remote URLs, and archives.
 *
 * If the file is remote, it's downloaded to a temporary location.
 * If it's an archive and $isArchive is true, it's extracted, and the path
 * to the file with the specified $need_ext extension is returned.
 *
 * @param array $fileinfo Associative array containing file information (e.g., from fileGetFullInfo).
 *                        Expected keys: 'fullPath', 'ulf_ExternalFileReference', 'ulf_OrigFileName', 'fxm_MimeType'.
 *                        Can also be nested under a 'file' key.
 * @param string|null $need_ext The required file extension if extracting from an archive.
 * @param bool $isArchive True if the file is an archive that needs extraction, false otherwise.
 * @return string|null The path to the file, or null if not found or an error occurs.
 */
function fileRetrievePath($fileinfo, $need_ext=null, $isArchive=false){

    if(@$fileinfo['file']){
        $fileinfo = $fileinfo['file'];
    }

    $filepath = $fileinfo['fullPath'];//concat(ulf_FilePath,ulf_FileName as fullPath
    $external_url = $fileinfo['ulf_ExternalFileReference'];
    $originalFileName = $fileinfo['ulf_OrigFileName'];
    $mimeType = $fileinfo['fxm_MimeType'];//fxm_MimeType

    $filepath = resolveFilePath($filepath);

    if(file_exists($filepath)){

    }elseif($external_url){
        $filepath = tempnam(HEURIST_SCRATCH_DIR, '_remote_');
        saveURLasFile($external_url, $filepath);//save remote shp to temp in scratch folder
    }

    if(file_exists($filepath)){

        if($isArchive){ //$need_ext!==null){
            $destination = HEURIST_SCRATCH_DIR;

            $files = UArchive::unzipFlat($filepath, $destination);

            if($files!==false){
                foreach ($files as $filename) {

                        $path_parts = pathinfo($filename);
                        if(array_key_exists('extension', $path_parts))
                        {
                            $ext = strtolower($path_parts['extension']);
                            if(file_exists($filename) && $need_ext==$ext)
                            {
                                //returns only one shp file name, others are assuming
                                return $filename;
                            }
                        }
                }
                return null; //not found
            }else{
                return null; //broken archive
            }
        }else{
            return $filepath;
        }

    }

}
?>