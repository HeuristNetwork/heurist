<?php
/**
* MapDataSourceService.php - Map datasource resolver and converter
*
* Resolves file-backed map datasource records and returns either GeoJSON,
* original source content, or a raw-file archive. Supported source formats are
* KML, KMZ, CSV, TSV, GeoJSON and SHP/DBF/SHX.
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

use hserv\structure\ConceptCode;
use hserv\utilities\USanitize;
use hserv\utilities\UArchive;
use hserv\records\map\MapShapefileService;

require_once dirname(__FILE__).'/../search/recordSearch.php';
require_once dirname(__FILE__).'/../import/importParser.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../../vendor/autoload.php';

/**
 * Single public service for file-backed map data.
 *
 * Format detection is completed before conversion. Column mappings influence
 * CSV interpretation only and never decide which parser is used.
 */
class MapDataSourceService
{
    /** @var \hserv\System */
    private $system;

    /** @var MapShapefileService */
    private $shapefileService;

    public function __construct($system)
    {
        $this->system = $system;
        $this->shapefileService = new MapShapefileService($system);
        ConceptCode::setSystem($system);
    }

    /**
     * Return map data for a datasource record.
     *
     * $format:
     *   geojson - convert source to GeoJSON
     *   rawfile - ZIP source file(s), optionally adding metadata
     *   source  - proxy original source content (shapefiles remain ZIP)
     *
     * @return array Result descriptor consumed by MapDataController.
     */
    public function getData(int $recordId, string $format = 'geojson', array $options = array()): array
    {
        if($recordId < 1){
            throw new \InvalidArgumentException('recID parameter value is missing or invalid');
        }

        $this->defineConstants();
        $record = recordSearchByID(
            $this->system,
            $recordId,
            true,
            'rec_ID,rec_RecTypeID,rec_Title'
        );

        if(!is_array($record) || empty($record['rec_ID'])){
            throw new \RuntimeException('Map datasource record not found or is not visible');
        }

        $format = strtolower(trim($format));
        if($format === ''){
            $format = 'source';
        }
        if(!in_array($format, array('geojson','rawfile','source'), true)){
            throw new \InvalidArgumentException('Unsupported map data output format: '.$format);
        }

        if($this->shapefileService->isShapefileRecord($record)){
            if($format === 'geojson'){
                return $this->shapefileService->toGeoJson($record, $options);
            }
            if($format === 'rawfile'){
                return $this->shapefileService->getRawFile($record, $options);
            }
            return $this->shapefileService->getSource($record, $options);
        }

        $source = $this->resolveSource($record);

        try{
            if($format === 'rawfile'){
                return $this->buildRawArchive($record, $source, $options);
            }

            if($format === 'source'){
                return $this->sourceResult($record, $source);
            }

            return $this->convertToGeoJson($record, $source, $options);
        }finally{
            $this->cleanupSource($source);
        }
    }

    /**
     * Detect a supported source format from extension, MIME type and content.
     */
    public function detectInputFormat(
        ?string $sourceName,
        ?string $mimeType,
        ?string $content = null,
        bool $preferKml = false
    ): ?string {
        $path = parse_url((string)$sourceName, PHP_URL_PATH);
        $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
        $byExt = array(
            'kmz'=>'kmz',
            'kml'=>'kml',
            'csv'=>'csv',
            'tsv'=>'tsv',
            'geojson'=>'geojson',
            'json'=>'geojson'
        );
        if(isset($byExt[$ext])){
            return $byExt[$ext];
        }

        $mime = strtolower(trim((string)$mimeType));
        if(in_array($mime, array('application/vnd.google-earth.kmz'), true)){ return 'kmz'; }
        if(in_array($mime, array('application/vnd.google-earth.kml+xml','application/kml+xml'), true)){ return 'kml'; }
        if(in_array($mime, array('text/tab-separated-values','text/tsv','application/tsv'), true)){ return 'tsv'; }
        if(in_array($mime, array('text/csv','application/csv','application/vnd.ms-excel'), true)){ return 'csv'; }
        if(in_array($mime, array('application/geo+json','application/json','text/geojson'), true)){ return 'geojson'; }

        if(is_string($content) && $content !== ''){
            $trimmed = ltrim($content);

            if(strncmp($trimmed, 'PK', 2) === 0){
                return 'kmz';
            }

            if($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')){
                $json = json_decode($trimmed, true);
                if(is_array($json)
                    && (
                        isset($json['features'])
                        || in_array($json['type'] ?? null, array(
                            'FeatureCollection','Feature','Point','MultiPoint',
                            'LineString','MultiLineString','Polygon','MultiPolygon',
                            'GeometryCollection'
                        ), true)
                    )
                ){
                    return 'geojson';
                }
            }

            $lower = strtolower(substr($trimmed, 0, 200000));
            if(strpos($lower, '<kml') !== false || strpos($lower, '<placemark') !== false){
                return 'kml';
            }
        }

        if($preferKml){
            return 'kml';
        }

        // Historical generic text/plain map sources are overwhelmingly CSV.
        if($mime === 'text/plain'){
            return 'csv';
        }

        return null;
    }

    /**
     * Detect longitude/latitude columns by common header names.
     *
     * Explicit datasource mappings always take precedence over this fallback.
     */
    public function detectCoordinateColumns(array $fields): array
    {
        $normalised = array();

        foreach($fields as $field){
            $key = strtolower(trim((string)$field));
            $key = preg_replace('/[^a-z0-9]+/', '', $key);
            if($key !== '' && !array_key_exists($key, $normalised)){
                $normalised[$key] = $field;
            }
        }

        $find = static function(array $candidates) use ($normalised){
            foreach($candidates as $candidate){
                if(array_key_exists($candidate, $normalised)){
                    return $normalised[$candidate];
                }
            }
            return null;
        };

        return array(
            'longitude' => $find(array(
                'longitude','long','lon','lng','longitud','x','easting','east'
            )),
            'latitude' => $find(array(
                'latitude','lattitude','lat','latitud','y','northing','north'
            ))
        );
    }

    /**
     * Resolve KML/KMZ/CSV/TSV/GeoJSON source content.
     */
    private function resolveSource(array $record): array
    {
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        $source = array(
            'format' => null,
            'content' => null,
            'path' => null,
            'sourceName' => null,
            'mimeType' => null,
            'originalFileName' => null,
            'cleanup' => array()
        );

        if(defined('DT_KML') && intval(DT_KML) > 0 && !empty($details[DT_KML])){
            $content = $this->firstDetail($details[DT_KML]);
            $source['content'] = is_scalar($content) ? (string)$content : '';
            $source['sourceName'] = 'map-source.kml';
            $source['format'] = $this->detectInputFormat(null, null, $source['content'], true);
            return $source;
        }

        $preferKml = false;
        $fileDetail = null;

        if(defined('DT_KML_FILE') && intval(DT_KML_FILE) > 0 && !empty($details[DT_KML_FILE])){
            $fileDetail = $this->firstDetail($details[DT_KML_FILE]);
            $preferKml = true;
        }elseif(defined('DT_FILE_RESOURCE') && intval(DT_FILE_RESOURCE) > 0 && !empty($details[DT_FILE_RESOURCE])){
            $fileDetail = $this->firstDetail($details[DT_FILE_RESOURCE]);
        }

        if(!$fileDetail){
            throw new \RuntimeException(
                'Datasource record does not contain KML, KMZ, CSV, TSV or GeoJSON data'
            );
        }

        if(is_array($fileDetail) && isset($fileDetail['file']) && is_array($fileDetail['file'])){
            $fileInfo = $fileDetail['file'];
        }else{
            $fileInfo = is_array($fileDetail) ? $fileDetail : array();
        }

        $source['mimeType'] = $fileInfo['fxm_MimeType'] ?? null;
        $source['originalFileName'] = $fileInfo['ulf_OrigFileName'] ?? null;
        $url = trim((string)($fileInfo['ulf_ExternalFileReference'] ?? ''));

        if($url !== ''){
            $source['sourceName'] = $url ?: $source['originalFileName'];
            $content = loadRemoteURLContent($url, true);
            if($content === false){
                throw new \RuntimeException('Cannot load remote file '.$url);
            }
            $source['content'] = $content;
        }else{
            $path = resolveFilePath($fileInfo['fullPath'] ?? '');
            $path = $path ? isPathInHeuristUploadFolder($path) : null;

            if(!$path || !is_file($path)){
                throw new \RuntimeException('Cannot load map datasource file');
            }

            $source['path'] = $path;
            $source['sourceName'] = $source['originalFileName'] ?: $path;
        }

        // Read enough/all content for reliable format detection. These map-source
        // files are subsequently parsed as a whole by the existing import code.
        $detectionContent = $source['content'];
        if($detectionContent === null && $source['path']){
            $detectionContent = file_get_contents($source['path']);
        }

        $source['format'] = $this->detectInputFormat(
            $source['sourceName'],
            $source['mimeType'],
            $detectionContent,
            $preferKml
        );

        if($source['format'] === null){
            throw new \RuntimeException(
                'Cannot determine map datasource format. Supported formats are KMZ, KML, CSV, TSV and GeoJSON'
            );
        }

        return $source;
    }

    private function convertToGeoJson(array $record, array &$source, array $options): array
    {
        $format = $source['format'];

        if($format === 'geojson'){
            $content = $this->sourceContent($source);
            return array(
                'type' => 'content',
                'content' => $content,
                'contentType' => 'application/geo+json; charset=utf-8',
                'filename' => null
            );
        }

        $content = $this->sourceContent($source);

        if($format === 'kmz'){
            $content = $this->extractKmzContent($content);
            $format = 'kml';
        }

        if($format === 'csv' || $format === 'tsv'){
            return $this->convertDelimitedToGeoJson(
                $record,
                $content,
                $format === 'tsv',
                $options
            );
        }

        if($format === 'kml'){
            return $this->convertKmlToGeoJson($record, $content, $options);
        }

        throw new \RuntimeException('Unsupported map datasource format: '.$format);
    }

    private function convertDelimitedToGeoJson(
        array $record,
        string $content,
        bool $isTsv,
        array $options
    ): array {
        $parserParams = array('csvdata'=>true);
        if($isTsv){
            $parserParams['csv_delimiter'] = 'tab';
        }

        $mapping = $this->legacyMapping($record, false);

        $parsed = \ImportParser::parseAndValidate(
            $content,
            null,
            PHP_INT_MAX,
            $parserParams
        );

        if(!is_array($parsed) || !is_array($parsed['fields'] ?? null)){
            throw new \RuntimeException('Cannot parse CSV/TSV map datasource');
        }

        if(empty($mapping['longitude']) || empty($mapping['latitude'])){
            $detected = $this->detectCoordinateColumns($parsed['fields']);
            if(empty($mapping['longitude'])){
                $mapping['longitude'] = $detected['longitude'];
            }
            if(empty($mapping['latitude'])){
                $mapping['latitude'] = $detected['latitude'];
            }
        }

        if(empty($mapping['longitude']) || empty($mapping['latitude'])){
            throw new \RuntimeException(
                'CSV/TSV map datasource requires longitude and latitude columns. '.
                'No explicit mapping was supplied and suitable columns could not be detected from the header.'
            );
        }

        if(empty($mapping[DT_NAME])){
            $mapping[DT_NAME] = $this->detectHeader(
                $parsed['fields'],
                array('name','title','label')
            );
        }

        return $this->parsedRowsToGeoJson($parsed, $mapping, $options);
    }

    private function convertKmlToGeoJson(array $record, string $content, array $options): array
    {
        $parserParams = array('kmldata'=>true);
        $mapping = $this->legacyMapping($record, true);

        $mapping[DT_GEO_OBJECT] = 'geometry';
        if(empty($mapping[DT_START_DATE])){ $mapping[DT_START_DATE] = 'timespan_begin'; }
        if(empty($mapping[DT_END_DATE])){ $mapping[DT_END_DATE] = 'timespan_end'; }
        if(defined('DT_DATE') && empty($mapping[DT_DATE])){ $mapping[DT_DATE] = 'timestamp'; }
        if(empty($mapping[DT_NAME])){ $mapping[DT_NAME] = 'name'; }

        $parsed = \ImportParser::parseAndValidate(
            $content,
            null,
            PHP_INT_MAX,
            $parserParams
        );

        if(!is_array($parsed) || empty($parsed['fields'])){
            throw new \RuntimeException('Cannot parse KML map datasource');
        }

        return $this->parsedRowsToGeoJson($parsed, $mapping, $options);
    }

    private function parsedRowsToGeoJson(array $parsed, array $mapping, array $options): array
    {
        $records = \ImportParser::convertParsedToRecords($parsed, $mapping);
        $recdata = array(
            'status' => HEURIST_OK,
            'data' => array(
                'reccount' => count($records),
                'records' => $records
            )
        );

        // ExportRecordsGEOJSON currently owns the canonical Heurist conversion
        // from parsed record-like rows to GeoJSON. Capture its body so HTTP
        // response handling remains in MapDataController.
        $className = 'hserv\records\export\ExportRecordsGEOJSON';
        $outputHandler = new $className($this->system);

        ob_start();
        try{
            $ok = $outputHandler->output($recdata, array(
                'format' => 'geojson',
                'leaflet' => false,
                'depth' => 0,
                'simplify' => !empty($options['simplify'])
            ));
            $content = ob_get_clean();
        }catch(\Throwable $e){
            ob_end_clean();
            throw $e;
        }

        if(!$ok){
            throw new \RuntimeException('Cannot convert map datasource to GeoJSON');
        }

        return array(
            'type' => 'content',
            'content' => $content,
            'contentType' => 'application/geo+json; charset=utf-8',
            'filename' => null
        );
    }

    /**
     * Read optional legacy field mappings stored on a datasource record.
     */
    private function legacyMapping(array $record, bool $kml): array
    {
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        $mapping = array();

        $fields = array(
            'name' => ConceptCode::getDetailTypeLocalID('2-934'),
            'description' => ConceptCode::getDetailTypeLocalID('2-935'),
            'longitude' => ConceptCode::getDetailTypeLocalID('2-930'),
            'latitude' => ConceptCode::getDetailTypeLocalID('2-931'),
            'start' => ConceptCode::getDetailTypeLocalID('2-932'),
            'end' => ConceptCode::getDetailTypeLocalID('2-933')
        );

        if($fields['name'] && !empty($details[$fields['name']])){
            $mapping[DT_NAME] = $this->firstDetail($details[$fields['name']]);
        }
        if($fields['description'] && !empty($details[$fields['description']])){
            $mapping[DT_EXTENDED_DESCRIPTION] = $this->firstDetail($details[$fields['description']]);
        }
        if($fields['start'] && !empty($details[$fields['start']])){
            $mapping[DT_START_DATE] = $this->firstDetail($details[$fields['start']]);
        }
        if($fields['end'] && !empty($details[$fields['end']])){
            $mapping[DT_END_DATE] = $this->firstDetail($details[$fields['end']]);
        }

        if(!$kml){
            if($fields['longitude'] && !empty($details[$fields['longitude']])){
                $mapping['longitude'] = $this->firstDetail($details[$fields['longitude']]);
            }
            if($fields['latitude'] && !empty($details[$fields['latitude']])){
                $mapping['latitude'] = $this->firstDetail($details[$fields['latitude']]);
            }
        }

        return $mapping;
    }

    private function extractKmzContent(string $content): string
    {
        $res = folderExistsVerbose(HEURIST_SCRATCH_DIR, true, 'scratch');
        if($res !== true){
            throw new \RuntimeException('Cannot extract kmz data to scratch folder. '.$res);
        }

        $tmp = tempnam(HEURIST_SCRATCH_DIR, 'map_kmz_');
        if(!$tmp){
            throw new \RuntimeException('Cannot create temporary KMZ file');
        }
        file_put_contents($tmp, $content);

        $files = UArchive::unzipFlat($tmp, HEURIST_SCRATCH_DIR);
        @unlink($tmp);

        if($files === false){
            throw new \RuntimeException('Cannot extract KMZ archive');
        }

        $kml = null;
        foreach($files as $file){
            if($kml === null && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'kml'){
                $value = file_get_contents($file);
                if($value !== false){
                    $kml = $value;
                }
            }
            @unlink($file);
        }

        if($kml === null){
            throw new \RuntimeException('KMZ archive does not contain a KML file');
        }

        return $kml;
    }

    private function buildRawArchive(array $record, array &$source, array $options): array
    {
        $name = $this->datasetName($record);
        $ext = $source['format'];
        $pathExt = strtolower(pathinfo((string)$source['sourceName'], PATHINFO_EXTENSION));
        if($ext === 'csv' && $pathExt === 'tsv'){
            $ext = 'tsv';
        }

        $sourcePath = $source['path'];
        if(!$sourcePath){
            $sourcePath = tempnam(HEURIST_SCRATCHSPACE_DIR, 'map_source_');
            if(!$sourcePath){
                throw new \RuntimeException('Cannot create temporary source file');
            }
            file_put_contents($sourcePath, $this->sourceContent($source));
            $source['cleanup'][] = $sourcePath;
        }

        $zipPath = tempnam(HEURIST_SCRATCHSPACE_DIR, 'map_raw_');
        if(!$zipPath){
            throw new \RuntimeException('Cannot create raw datasource archive');
        }

        $zip = new \ZipArchive();
        if(!$zip->open($zipPath, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE)){
            @unlink($zipPath);
            throw new \RuntimeException('Cannot create raw datasource archive');
        }

        $zip->addFile($sourcePath, $name.'.'.$ext);
        if(!empty($options['metadata'])){
            $zip->addFromString($name.'.txt', recordLinksFileContent($this->system, $record));
        }
        $zip->close();

        return array(
            'type' => 'file',
            'path' => $zipPath,
            'contentType' => 'application/zip',
            'filename' => $name.'.zip',
            'deleteAfterOutput' => true
        );
    }

    private function sourceResult(array $record, array &$source): array
    {
        if($source['path']){
            return array(
                'type' => 'file',
                'path' => $source['path'],
                'contentType' => $this->contentType($source['format']),
                'filename' => $this->sourceFilename($record, $source),
                'deleteAfterOutput' => false
            );
        }

        return array(
            'type' => 'content',
            'content' => $this->sourceContent($source),
            'contentType' => $this->contentType($source['format']),
            'filename' => $this->sourceFilename($record, $source)
        );
    }

    private function sourceFilename(array $record, array $source): string
    {
        $original = trim((string)($source['originalFileName'] ?? ''));
        if($original !== ''){
            return USanitize::sanitizeFileName($original);
        }

        $ext = $source['format'];
        return $this->datasetName($record).'.'.$ext;
    }

    private function contentType(string $format): string
    {
        $types = array(
            'kmz' => 'application/vnd.google-earth.kmz',
            'kml' => 'application/vnd.google-earth.kml+xml; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8',
            'tsv' => 'text/tab-separated-values; charset=utf-8',
            'geojson' => 'application/geo+json; charset=utf-8'
        );
        return $types[$format] ?? 'application/octet-stream';
    }

    private function sourceContent(array &$source): string
    {
        if($source['content'] !== null){
            return (string)$source['content'];
        }
        if($source['path'] && is_file($source['path'])){
            $content = file_get_contents($source['path']);
            if($content !== false){
                return $content;
            }
        }
        throw new \RuntimeException('Cannot read map datasource content');
    }

    private function datasetName(array $record): string
    {
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        if(defined('DT_NAME') && intval(DT_NAME) > 0 && !empty($details[DT_NAME])){
            $name = $this->firstDetail($details[DT_NAME]);
            if(is_scalar($name)){
                $name = USanitize::sanitizeFileName((string)$name);
                if($name !== ''){
                    return $name;
                }
            }
        }

        $title = trim((string)($record['rec_Title'] ?? ''));
        if($title !== ''){
            $title = USanitize::sanitizeFileName($title);
            if($title !== ''){
                return $title;
            }
        }

        return 'Dataset_'.intval($record['rec_ID'] ?? 0);
    }

    private function detectHeader(array $fields, array $candidates): ?string
    {
        foreach($candidates as $candidate){
            foreach($fields as $field){
                if(strcasecmp(trim((string)$field), $candidate) === 0){
                    return (string)$field;
                }
            }
        }
        return null;
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

    private function cleanupSource(array $source): void
    {
        foreach(array_unique($source['cleanup'] ?? array()) as $file){
            if(is_string($file) && is_file($file)){
                @unlink($file);
            }
        }
    }

    private function defineConstants(): void
    {
        foreach(array(
            'DT_KML',
            'DT_KML_FILE',
            'DT_FILE_RESOURCE',
            'DT_NAME',
            'DT_EXTENDED_DESCRIPTION',
            'DT_START_DATE',
            'DT_END_DATE',
            'DT_DATE',
            'DT_GEO_OBJECT',
            'RT_SHP_SOURCE'
        ) as $name){
            $this->system->defineConstant($name);
        }
    }
}
