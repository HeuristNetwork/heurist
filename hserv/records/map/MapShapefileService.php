<?php
/**
* MapShapefileService.php - Shapefile map data service
*
* Resolves SHP/DBF/SHX datasource records, converts shapefile features to
* GeoJSON, validates WGS84 coordinates, and creates raw datasource archives.
*
* @project     Heurist academic knowledge management system
* @package     map
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/
namespace hserv\records\map;

use hserv\utilities\USanitize;
use hserv\utilities\UArchive;
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader;

require_once dirname(__FILE__).'/../../../vendor/gasparesganga/php-shapefile/src/Shapefile/ShapefileAutoloader.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';

\Shapefile\ShapefileAutoloader::register();

/**
 * Handles the specialised multi-file shapefile datasource format.
 */
class MapShapefileService
{
    /** @var \hserv\System */
    private $system;

    public function __construct($system)
    {
        $this->system = $system;
    }

    public function isShapefileRecord(array $record): bool
    {
        $this->defineConstants();

        $rty = intval($record['rec_RecTypeID'] ?? 0);
        if(defined('RT_SHP_SOURCE') && intval(RT_SHP_SOURCE) > 0 && $rty === intval(RT_SHP_SOURCE)){
            return true;
        }

        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        return (defined('DT_SHAPE_FILE') && intval(DT_SHAPE_FILE) > 0 && !empty($details[DT_SHAPE_FILE]))
            || (defined('DT_ZIP_FILE') && intval(DT_ZIP_FILE) > 0 && !empty($details[DT_ZIP_FILE]));
    }

    /**
     * Convert a shapefile datasource record to GeoJSON.
     *
     * @return array MapDataController result descriptor.
     */
    public function toGeoJson(array $record, array $options = array()): array
    {
        $resolved = $this->resolveFiles($record);
        $simplify = !empty($options['simplify']);

        try{
            $reader = $this->createReader($resolved);
            $tmp = tempnam(HEURIST_SCRATCHSPACE_DIR, 'map_shp_');
            if(!$tmp){
                throw new \RuntimeException('Cannot create temporary GeoJSON file');
            }

            $fd = fopen($tmp, 'wb');
            if(!$fd){
                @unlink($tmp);
                throw new \RuntimeException('Cannot open temporary GeoJSON file');
            }

            fwrite($fd, '{"type":"FeatureCollection","features":[');
            $count = 0;

            while($shapeRecord = $reader->fetchRecord()){
                if($shapeRecord->isDeleted()){
                    continue;
                }

                $feature = json_decode($shapeRecord->getGeoJSON(false, true), true);
                if(!is_array($feature)){
                    continue;
                }

                $geometry = $feature['geometry'] ?? null;
                if(is_array($geometry)){
                    $this->validateWgs84Geometry($geometry);
                    if($simplify){
                        $this->simplifyGeometry($geometry);
                    }
                    $feature['geometry'] = $geometry;
                }

                if(!isset($feature['properties']) || !is_array($feature['properties'])){
                    $feature['properties'] = array();
                }
                // External feature IDs must not look like Heurist record IDs.
                // $feature['properties']['rec_ID'] = 'shp-'.($count + 1);

                if($count > 0){
                    fwrite($fd, ',');
                }
                fwrite($fd, json_encode($feature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $count++;

                // Preserve the historical safety limit.
                if(memory_get_usage() > 104857600){
                    break;
                }
            }

            fwrite($fd, ']}');
            fclose($fd);

            return array(
                'type' => 'file',
                'path' => $tmp,
                'contentType' => 'application/geo+json; charset=utf-8',
                'contentEncoding' => 'gzip',
                'filename' => null,
                'deleteAfterOutput' => true
            );
        }catch(ShapefileException $e){
            throw new \RuntimeException('Cannot process shp file: '.$e->getMessage(), 0, $e);
        }finally{
            $this->cleanupResolvedFiles($resolved);
        }
    }

    /**
     * Build a ZIP archive containing the source SHP/DBF/SHX files.
     */
    public function getRawFile(array $record, array $options = array()): array
    {
        $resolved = $this->resolveFiles($record);
        $name = $this->datasetName($record);
        $zipPath = tempnam(HEURIST_SCRATCHSPACE_DIR, 'map_shp_raw_');
        if(!$zipPath){
            $this->cleanupResolvedFiles($resolved);
            throw new \RuntimeException('Cannot create temporary archive');
        }

        $zip = new \ZipArchive();
        if(!$zip->open($zipPath, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE)){
            @unlink($zipPath);
            $this->cleanupResolvedFiles($resolved);
            throw new \RuntimeException('Cannot create shapefile archive');
        }

        try{
            foreach(array('shp','dbf','shx') as $key){
                if(!empty($resolved[$key]) && is_file($resolved[$key])){
                    $zip->addFile($resolved[$key], basename($resolved[$key]));
                }
            }

            if(!empty($options['metadata'])){
                $zip->addFromString($name.'.txt', recordLinksFileContent($this->system, $record));
            }
        }finally{
            $zip->close();
            $this->cleanupResolvedFiles($resolved);
        }

        return array(
            'type' => 'file',
            'path' => $zipPath,
            'contentType' => 'application/zip',
            'filename' => $name.'.zip',
            'deleteAfterOutput' => true
        );
    }

    /**
     * A shapefile is intrinsically multi-file, therefore direct source output
     * uses the same ZIP representation as rawfile.
     */
    public function getSource(array $record, array $options = array()): array
    {
        return $this->getRawFile($record, $options);
    }

    private function createReader(array $resolved): ShapefileReader
    {
        $shp = $resolved['shp'] ?? null;
        $dbf = $resolved['dbf'] ?? null;
        $shx = $resolved['shx'] ?? null;

        if(!$shp || !is_file($shp)){
            throw new \RuntimeException('Cannot process shp file');
        }

        if($dbf && is_file($dbf)){
            $files = array('shp'=>$shp, 'dbf'=>$dbf);
            $ignoreShx = true;
            if($shx && is_file($shx)){
                $files['shx'] = $shx;
                $ignoreShx = false;
            }

            return new ShapefileReader($files, array(
                Shapefile::OPTION_IGNORE_FILE_SHX => $ignoreShx,
                Shapefile::OPTION_IGNORE_FILE_DBF => false
            ));
        }

        return new ShapefileReader($shp, array(
            Shapefile::OPTION_IGNORE_FILE_SHX => true,
            Shapefile::OPTION_IGNORE_FILE_DBF => true
        ));
    }

    private function resolveFiles(array $record): array
    {
        $this->defineConstants();
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();

        $result = array('shp'=>null, 'dbf'=>null, 'shx'=>null, 'cleanup'=>array());

        if(defined('DT_ZIP_FILE') && intval(DT_ZIP_FILE) > 0 && !empty($details[DT_ZIP_FILE])){
            return $this->extractArchive($this->firstDetail($details[DT_ZIP_FILE]));
        }

        if(defined('DT_SHAPE_FILE') && intval(DT_SHAPE_FILE) > 0 && !empty($details[DT_SHAPE_FILE])){
            $result['shp'] = $this->materialiseFile($this->firstDetail($details[DT_SHAPE_FILE]), $result['cleanup']);

            if(defined('DT_DBF_FILE') && intval(DT_DBF_FILE) > 0 && !empty($details[DT_DBF_FILE])){
                $result['dbf'] = $this->materialiseFile($this->firstDetail($details[DT_DBF_FILE]), $result['cleanup']);
            }
            if(defined('DT_SHX_FILE') && intval(DT_SHX_FILE) > 0 && !empty($details[DT_SHX_FILE])){
                $result['shx'] = $this->materialiseFile($this->firstDetail($details[DT_SHX_FILE]), $result['cleanup']);
            }

            return $result;
        }

        if(defined('DT_FILE_RESOURCE') && intval(DT_FILE_RESOURCE) > 0 && !empty($details[DT_FILE_RESOURCE])){
            return $this->extractArchive($this->firstDetail($details[DT_FILE_RESOURCE]));
        }

        throw new \RuntimeException(
            'Cannot process shp file. Datasource record does not contain SHP/DBF files or a shapefile archive.'
        );
    }

    private function extractArchive($detail): array
    {
        $cleanup = array();
        $archive = $this->materialiseFile($detail, $cleanup);
        if(!$archive || !is_file($archive)){
            throw new \RuntimeException('Cannot read shapefile archive');
        }

        $files = \hserv\utilities\UArchive::unzipFlat($archive, HEURIST_SCRATCH_DIR);
        if($files === false){
            $this->cleanupFiles($cleanup);
            throw new \RuntimeException('Cannot extract shapefile archive');
        }

        $result = array('shp'=>null, 'dbf'=>null, 'shx'=>null, 'cleanup'=>$cleanup);
        foreach($files as $file){
            $result['cleanup'][] = $file;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if(in_array($ext, array('shp','dbf','shx'), true) && $result[$ext] === null){
                $result[$ext] = $file;
            }
        }

        if(!$result['shp']){
            $this->cleanupResolvedFiles($result);
            throw new \RuntimeException('Shapefile archive does not contain a .shp file');
        }

        return $result;
    }

    private function materialiseFile($detail, array &$cleanup): ?string
    {
        if(is_array($detail) && isset($detail['file']) && is_array($detail['file'])){
            $detail = $detail['file'];
        }
        if(!is_array($detail)){
            return null;
        }

        $path = resolveFilePath($detail['fullPath'] ?? '');
        if($path){
            $path = isPathInHeuristUploadFolder($path);
        }
        if($path && is_file($path)){
            return $path;
        }

        $url = trim((string)($detail['ulf_ExternalFileReference'] ?? ''));
        if($url !== ''){
            $tmp = tempnam(HEURIST_SCRATCH_DIR, '_map_shp_remote_');
            if(!$tmp){
                return null;
            }
            saveURLasFile($url, $tmp);
            if(!is_file($tmp) || filesize($tmp) < 1){
                @unlink($tmp);
                return null;
            }
            $cleanup[] = $tmp;
            return $tmp;
        }

        return null;
    }

    private function validateWgs84Geometry(array $geometry): void
    {
        if(empty($geometry['coordinates'])){
            return;
        }
        $this->validateCoordinateTree($geometry['coordinates']);
    }

    private function validateCoordinateTree($value): void
    {
        if(!is_array($value) || empty($value)){
            return;
        }

        if(isset($value[0], $value[1]) && is_numeric($value[0]) && is_numeric($value[1])){
            $lon = (float)$value[0];
            $lat = (float)$value[1];
            if(abs($lon) > 190 || abs($lat) > 90){
                throw new \RuntimeException(
                    'Cannot process shp file. Heurist uses WGS84 (World Geographic System) '.
                    'to support the plotting of maps worldwide. This shapefile is not in this format '.
                    'and will not therefore display on maps. Please use a GIS or other converter to convert to WGS84'
                );
            }
            return;
        }

        foreach($value as $child){
            $this->validateCoordinateTree($child);
        }
    }

    private function simplifyGeometry(array &$geometry): void
    {
        if(empty($geometry['coordinates']) || empty($geometry['type'])){
            return;
        }

        switch($geometry['type']){
            case 'LineString':
                simplifyCoordinates($geometry['coordinates']);
                break;

            case 'Polygon':
            case 'MultiLineString':
                foreach($geometry['coordinates'] as &$line){
                    simplifyCoordinates($line);
                }
                unset($line);
                break;

            case 'MultiPolygon':
                foreach($geometry['coordinates'] as &$polygon){
                    foreach($polygon as &$ring){
                        simplifyCoordinates($ring);
                    }
                    unset($ring);
                }
                unset($polygon);
                break;
        }
    }

    private function datasetName(array $record): string
    {
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        if(defined('DT_NAME') && intval(DT_NAME) > 0 && !empty($details[DT_NAME])){
            $value = $this->firstDetail($details[DT_NAME]);
            if(is_scalar($value)){
                $name = USanitize::sanitizeFileName((string)$value);
                if($name !== ''){
                    return $name;
                }
            }
        }
        return 'Dataset_'.intval($record['rec_ID'] ?? 0);
    }

    private function firstDetail($value)
    {
        if(!is_array($value)){
            return $value;
        }
        if(array_key_exists('file', $value)){
            return $value;
        }
        $values = array_values($value);
        return $values[0] ?? null;
    }

    private function cleanupResolvedFiles(array $resolved): void
    {
        $this->cleanupFiles($resolved['cleanup'] ?? array());
    }

    private function cleanupFiles(array $files): void
    {
        foreach(array_unique($files) as $file){
            if(is_string($file) && is_file($file)){
                @unlink($file);
            }
        }
    }

    private function defineConstants(): void
    {
        foreach(array(
            'RT_SHP_SOURCE',
            'DT_ZIP_FILE',
            'DT_SHAPE_FILE',
            'DT_DBF_FILE',
            'DT_SHX_FILE',
            'DT_FILE_RESOURCE',
            'DT_NAME'
        ) as $name){
            $this->system->defineConstant($name);
        }
    }
}
