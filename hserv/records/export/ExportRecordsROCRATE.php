<?php
/**
* ExportRecordsROCRATE.php - Export record data in formatted for RO-Crates, this will always download as a zip.
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace hserv\records\export;

use hserv\records\export\ExportRecords;
use hserv\records\export\ExportRecordsJSON;
use hserv\records\export\RecordsExportCSV;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

use function intval;
use function is_array;
use function array_key_exists;
use function count;
use function strlen;

require_once __DIR__ . '/../search/recordFile.php';

class ExportRecordsROCRATE extends ExportRecords{

    private const RO_CRATE_CONTEXT = 'https://w3id.org/ro/crate/1.2/context';
    private const RO_CRATE_SPECIFICATION = 'https://w3id.org/ro/crate/1.2';

    private string $crateDirectory = '';
    private string $recordDirectory = '';
    private string $jsonlDirectory = '';
    private string $fileDirectory = '';
    private string $schemaDirectory = '';
    private string $provenanceDirectory = '';
    private string $crateMetadadataFile = '';

    private bool $logExport = false;
    private array $exportLog = [];
    private array $metadataRecords = [];
    private array $metadataFiles = [];
    private array $metadataSchemas = [];
    private array $metadataProvenance = [];

    private bool $setSession = false;
    private ?ExportRecordsJSON $JSONExporter = null;

    protected function _outputHeader(){}

    protected function _outputRecord($record){
        return true;
    }

    protected function _outputFooter(){}

    public function setSession($system){

        $this->setupOtherExporters($system);

        return parent::setSession($system);
    }

    private function setupOtherExporters(?\hserv\System $system){

        if(!$system){ // default to current system
            $system = $this->system;
        }

        RecordsExportCSV::setSession($system); // Set CSV exporter

        // Set JSON exporter
        if(!$this->JSONExporter){
            $this->JSONExporter = new ExportRecordsJSON($system);
        }else{
            $this->JSONExporter->setSession($system);
        }

        $this->setSession = true;
    }

    public function output($data, $params) : bool{

        if(!$this->_outputPrepare($data, $params)){
            return false;
        }

        $this->logExport = array_key_exists('log', $params) ? intval($params['log']) !== 0 : false;
        if($this->logExport){
            $this->exportLog = [
                'Records' => [],
                'Files' => [],
                'Schemas' => []
            ];
        }

        if(!$this->setSession){
            $this->setupOtherExporters($this->system);
        }

        $this->prepareCrate();

        $records = [];
        foreach($this->records as $record){

            $recID = is_array($record) ? (int)($record['rec_ID'] ?? 0) : (int)$record;

            if(!isPositiveInt($recID)){
                continue;
            }

            $rtyID = mysql__select_value($this->mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = ?", ['i', $recID]);

            if(!array_key_exists($rtyID, $records)){
                $records[$rtyID] = [];
            }
            $records[$rtyID][] = $recID;
        }

        foreach($records as $rtyID => $recordIDs){

            if(!$this->addRecords($rtyID, $recordIDs) || !$this->addFiles($recordIDs) || !$this->addSchema($rtyID)){
                return false;
            }
        }

        $this->addProvenanceDetails($params);

        return $this->_outputResults();
    }

    private function _outputResults() : bool{

        $this->setSession = false;
        $timestamp = date('YmdHis');

        $this->createCrate();

        $filename = "RO-Crate_{$this->system->dbname()}_{$timestamp}.zip";
        $crateZip = rtrim($this->crateDirectory, '/\\') . '.zip';

        $zip = new ZipArchive();
        if(!$zip->open($crateZip, ZipArchive::CREATE | ZipArchive::OVERWRITE)){
            $this->system->addError(HEURIST_ERROR, 'Unable to create ZIP folder for output.');
            return false;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->crateDirectory), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach($files as $file){

            if($file->isDir()){
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen(rtrim($this->crateDirectory, DIRECTORY_SEPARATOR)) + 1);

            if(!$zip->addFile($file->getRealPath(), $relativePath)){
                $zip->close();
                $this->system->addError(HEURIST_ERROR, 'Failed to write to Zip folder.');
                return false;
            }
        }

        $zip->close();

        folderDelete($this->crateDirectory, true);

        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename={$filename}");
        header(CONTENT_LENGTH . filesize($crateZip));

        fileReadByChunks($crateZip);
        fileDelete($crateZip);

        return true;
    }

    private function addRecords(int $rtyID, array $recordIDs) : bool{

        $rtyName = mysql__select_value($this->mysqli, "SELECT rty_Name FROM defRecTypes WHERE rty_ID = ?", ['i', $rtyID]);
        $rtyName = str_replace(' ', '_', $rtyName);

        $fields = $this->getRecTypeFields($rtyID);

        $TSVFile = "{$this->recordDirectory}{$rtyName}.tsv";
        $JSONLFile = "{$this->jsonlDirectory}{$rtyName}.jsonl";

        $JSONParams = [
            'format' => 'json',
            'jsonl' => 1,
            'toFile' => $JSONLFile,
            'linkmode' => 'none',
            'depth' => 0
        ];

        $TSVParams = [
            'prefs' => [
                'main_record_type_ids' => $rtyID,
                'term_ids_only' => 1,
                'include_resource_titles' => 1,
                'include_temporals' => 1,
                'fields' => $fields,
                'csv_delimiter' => "\t"
            ],
            'save_to_file' => 1,
            'file' => [
                'directory' => $this->recordDirectory,
                'filename' => "{$rtyName}.tsv"
            ]
        ];

        $data = ['status' => HEURIST_OK, 'data' => ['records' => $recordIDs, 'reccount' => count($recordIDs)]];

        if($this->logExport){
            $this->exportLog['Records'][$rtyID] = [
                'Records' => implode(',', $recordIDs),
                'JSON Parameters' => [
                    'jsonl' => true, 'toFile' => "/records/json/{$rtyName}.jsonl"
                ],
                'CSV Parameters' => [
                    'Preferences' => [
                        'Term IDs Only' => true,
                        'Include Resource Titles' => true,
                        'Include Temporals' => true,
                        'Fields' => $fields,
                        'Delimiter' => '\t'
                    ],
                    'To File' => "/records/{$rtyName}.tsv"
                ]
            ];
        }

        $result = $this->JSONExporter->output($data, $JSONParams) && RecordsExportCSV::output($data, $TSVParams);
        if(!$result){
            return false;
        }

        $TSVRelativeFile = str_replace($this->crateDirectory, '/', $TSVFile);
        $JSONLRelativeFile = str_replace($this->crateDirectory, '/', $JSONLFile);

        $this->metadataRecords[$TSVRelativeFile] = [
            '@id' => $TSVRelativeFile,
            '@type' => 'File',
            'name' => pathinfo($TSVFile, PATHINFO_FILENAME),
            'encodingFormat' => 'text/tab-separated-values',
            'contentSize' => filesize($TSVFile),
            'sha256' => hash_file('sha256', $TSVFile)
        ];

        $this->metadataRecords[$JSONLRelativeFile] = [
            '@id' => $JSONLRelativeFile,
            '@type' => 'File',
            'name' => pathinfo($JSONLFile, PATHINFO_FILENAME),
            'encodingFormat' => 'text/tab-separated-values',
            'contentSize' => filesize($JSONLFile),
            'sha256' => hash_file('sha256', $JSONLFile)
        ];

        return true;
    }

    private function addFiles(array $recordIDs) : bool{

        foreach($recordIDs as $recID){

            $record = ['rec_ID' => $recID, 'details' => []];
            recordSearchDetails($this->system, $record, ['file']);
            if(empty($record['details'])){
                continue;
            }

            $files = $this->processFiles($record['details']);
            if($this->logExport){
                $this->exportLog['Files'][$recID] = [
                    'File Count' => count($files)
                ];
            }

            foreach($files as $file){
                // @todo - Add ulf_Copyright and ulf_Copyoowner

                if($file['ulf_WhoCanView'] === 'loginrequired' && !$this->system->hasAccess()){
                    continue;
                }

                if(!empty($file['ulf_ExternalFileReference'])){

                    // Link external file
                    $this->metadataFiles[$file['ulf_ExternalFileReference']] = [
                        '@id' => $file['ulf_ExternalFileReference'],
                        '@type' => 'WebResource',
                        'name' => '',
                        'description' => $file['ulf_Description'],
                        'encodingFormat' => $file['fxm_MimeType']
                    ];

                    continue;
                }

                $filePath = resolveFilePath($file['fullPath'], $this->system->dbname());
                if(!file_exists($filePath)){
                    continue;
                }

                $fileFolder = ltrim($file['fullPath'], '/\\');
                $fileCrateLocation = "{$this->fileDirectory}{$fileFolder}";
                if(!fileCopy($filePath, $fileCrateLocation)){

                    // Add as external resource, linked to Heurist
                    $heuristURL = rtrim(HEURIST_BASE_URL_PRO, '/\\') . DIRECTORY_SEPARATOR . "?db={$this->system->dbname()}&file={$file['ulf_ObfuscatedFileID']}";
                    $this->metadataFiles[$heuristURL] = [
                        '@id' => $heuristURL,
                        '@type' => 'WebResource',
                        'name' => $file['ulf_OrigFileName'],
                        'description' => $file['ulf_Description'],
                        'encodingFormat' => $file['fxm_MimeType']
                    ];

                    continue;
                }

                $relativePath = DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $fileFolder;

                $this->metadataFiles[$relativePath] = [
                    '@id' => $relativePath,
                    '@type' => 'File',
                    'name' => $file['ulf_OrigFileName'] ?? pathinfo($fileCrateLocation, PATHINFO_FILENAME),
                    'description' => $file['ulf_Description'],
                    'encodingFormat' => $file['fxm_MimeType'],
                    'contentSize' => filesize($fileCrateLocation),
                    'sha256' => hash_file('sha256', $fileCrateLocation)
                ];
            }
        }

        return true;
    }

    private function addSchema(int $rtyID) : bool{

        $rtyName = mysql__select_value($this->mysqli, "SELECT rty_Name FROM defRecTypes WHERE rty_ID = ?", ['i', $rtyID]);

        $fieldQuery = <<<QUERY
        SELECT rst_DisplayName, rst_DisplayHelpText, dty_Type, rst_RequirementType, rst_NonOwnerVisibility, rst_MayModify, CONCAT(rst_OriginatingDBID, '-', rst_IDInOriginatingDB) AS rst_ConceptID, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs, rst_DefaultValue
        FROM defRecStructure
        INNER JOIN defDetailTypes ON dty_ID = rst_DetailTypeID
        WHERE rst_RecTypeID = $rtyID
        ORDER BY rst_DisplayOrder
        QUERY;

        $fields = mysql__select_assoc($this->mysqli, $fieldQuery);
        if(empty($fields)){
            return true;
        }

        if($this->logExport){
            $this->exportLog['Schemas'][$rtyID] = [
                'Fields' => array_map(fn($field) => current($field), $fields)
            ];
        }

        $fullPath = "{$this->schemaDirectory}{$rtyName}.json";
        $relativePath = str_replace($this->crateDirectory, DIRECTORY_SEPARATOR, $fullPath);
        file_put_contents($fullPath, json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->metadataSchemas[$relativePath] = [
            '@id' => $relativePath,
            '@type' => 'File',
            'name' => "Structure for {$rtyName}",
            'encodingFormat' => 'application/json',
            'contentSize' => filesize($fullPath),
            'sha256' => hash_file('sha256', $fullPath)
        ];

        return true;
    }

    private function addProvenanceDetails(array $parameters){

        $exportConfig = [
            'Exporting Program' => 'Heurist',
            'Script Version' => '1.0',
            'Heurist Database' => $parameters['db'],
            'Heurist Version' => HEURIST_VERSION,
            'Heurist Database Version' => getDbVersion($this->mysqli),
            'Export Timestamp' => gmdate('Y-m-d H:i:s'),
            'Query' => $parameters['q'],
            'Settings' => [
                'search depth' => $parameters['depth'],
                'linking mode' => $parameters['linkmode']
            ]
        ];

        $configFullPath = "{$this->provenanceDirectory}export-config.json";
        $configRelativePath = '/provenance/export-config.json';
        file_put_contents($configFullPath, json_encode($exportConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->metadataProvenance[$configRelativePath] = [
            '@id' => $configRelativePath,
            '@type' => 'File',
            'name' => 'Export Configuration',
            'encodingFormat' => 'application/json',
            'contentSize' => filesize($configFullPath),
            'sha256' => hash_file('sha256', $configFullPath)
        ];

        if(!empty($this->exportLog)){
            return;
        }

        $logFullPath = "{$this->provenanceDirectory}export-log.json";
        $logRelativePath = '/provenance/export-log.json';
        file_put_contents($logFullPath, json_encode($exportConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->metadataProvenance[$logRelativePath] = [
            '@id' => $logRelativePath,
            '@type' => 'File',
            'name' => 'Export Log',
            'encodingFormat' => 'application/json',
            'contentSize' => filesize($logFullPath),
            'sha256' => hash_file('sha256', $logFullPath)
        ];
    }

    private function createCrate(){

        $graph = [];

        // RO-Crate metadata descriptor
        $roCrateMetadata = [
            '@id' => 'ro-crate-metadata.json',
            '@type' => 'CreativeWork',
            'conformsTo' => [
                '@id' => self::RO_CRATE_SPECIFICATION
            ],
            'about' => [
                '@id' => './'
            ]
        ];

        // Root dataset - database
        $dataset = [
            '@id' => './',
            '@type' => 'Dataset',
            'name' => "Heurist RO-Crate Record Export for {$this->system->dbname()}",
            'datePublished' => gmdate('Y-m-d'),
            'hasPart' => []
        ];

        $parts = array_merge($this->metadataRecords, $this->metadataFiles, $this->metadataSchemas, $this->metadataProvenance);
        $dataset['hasPart'] = array_map(fn($key) => ['@id' => $key], array_keys($parts));
        $graph = array_values($parts);

        $databaseEntity = [
            '@id' => '#database',
            '@type' => 'Dataset',
            'id' => $this->databaseDetails['sys_dbRegisteredID'] ?? 0,
            'name' => $this->system->dbname()
        ];

        array_unshift($graph, $databaseEntity);
        array_unshift($graph, $dataset);
        array_unshift($graph, $roCrateMetadata);

        $metadata = [
            '@context' => [self::RO_CRATE_CONTEXT],
            '@graph' => $graph
        ];
        $metadata = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if(!$metadata || json_last_error() !== JSON_ERROR_NONE){
            $this->system->addError(HEURIST_ERROR, 'Unable to encode crate metadata.');
            return false;
        }

        $writeResult = file_put_contents($this->crateMetadadataFile, $metadata);
        if($writeResult === 0 || $writeResult === false){
            $this->system->addError(HEURIST_ERROR, 'Failed to write crate metadata to file.');
            return false;
        }

        return true;
    }

    private function prepareCrate(){

        $this->crateDirectory = "{$this->system->getSysDir(DIR_SCRATCH)}ro-crate/";
        $this->recordDirectory = "{$this->crateDirectory}records/";
        $this->jsonlDirectory = "{$this->recordDirectory}JSON/";
        $this->fileDirectory = "{$this->crateDirectory}files/";
        $this->schemaDirectory = "{$this->crateDirectory}schema/";
        $this->provenanceDirectory = "{$this->crateDirectory}provenance/";

        $this->crateMetadadataFile = "{$this->crateDirectory}ro-crate-metadata.json";

        folderCreate($this->crateDirectory, true);
        folderCreate($this->recordDirectory, true);
        folderCreate($this->jsonlDirectory, true);
        folderCreate($this->fileDirectory, true);
        folderCreate($this->schemaDirectory, true);
        folderCreate($this->provenanceDirectory, true);
    }

    private function getRecTypeFields(int $rtyID) : array{

        $fields = ['rec_ID', 'rec_Title', 'rec_RecTypeID', 'rec_URL', 'rec_Added', 'rec_Modified', 'rec_ScratchPad', 'rec_AddedByUGrpID', 'rec_OwnerUGrpID', 'rec_NonOwnerVisibility'];

        $rstFields = mysql__select_list2($this->mysqli, "SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID = {$rtyID}", 'intval');

        foreach($rstFields as $dtyID){
            $fields[] = (string)$dtyID;
        }

        return [$rtyID => $fields];
    }

    private function processFiles(array $fileFields) : array{

        $files = [];
        foreach($fileFields as $fileValues){
            foreach($fileValues as $value){
                $ulfID = $value['file']['ulf_ID'];
                if(!array_key_exists($ulfID, $files)){
                    $files[$ulfID] = $value['file'];
                }
            }
        }

        return $files;
    }
}