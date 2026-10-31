<?php
/**
* DbExportROCrate.php - Export database in RO-Crate format
*
* @project     Heurist academic knowledge management system
* @package     Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace hserv\utilities;

use FilesystemIterator;
use hserv\System;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function strlen;
use function is_string;
use function in_array;

/**
 * Generates an RO-Crate for the Heurist database.
 *
 * The crate may contain:
 *
 *   crate/
 *     ro-crate-metadata.json
 *     database/
 *       tables/
 *         *.tsv
 *       records/
 *         recordType_Name.tsv
 *       database.sql
 *       database.hml
 *     files/
 *       ...
 */
class DbExportROCrate{

    private const RO_CRATE_VERSION = '1.2';
    private const RO_CRATE_CONTEXT = 'https://w3id.org/ro/crate/1.2/context';
    private const RO_CRATE_SPECIFICATION = 'https://w3id.org/ro/crate/1.2';
    private const MYSQL_DUMP_OPTIONS = [
        'skip-triggers' => true,
        'single-transaction' => true,
        'quick' => true,
        'add-drop-trigger' => false,
        'no-create-db' => true,
        'add-drop-table' => true
    ];

    private ?System $system;
    private ?\mysqli $mysqli;

    private string $baseDirectory = '';
    private string $crateDirectory = '';
    private string $databaseDirectory = '';
    private string $databaseFilestoreDir = '';
    private string $fileDirectory = '';

    private string $sqlFile = '';
    private string $hmlFile = '';
    private array $tsvFiles = [
        'tables' => [],
        'records' => []
    ];

    private array $databaseDetails = [];

    /**
     * Class constructor
     *
     * @param System $system
     */
    public function __construct(System $system){
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    /**
     * Export the database as an RO-Crate
     *
     * @param string $crateDirectory
     * @param array<string,mixed> $options
     *
     * Supported options:
     *
     *   includeSqlDump  bool           Whether to include an SQL database dump.
     *   includeFiles    bool           Whether to include the files from the filestore.
     *   recordFormat    array|null     Format to export records as by default [TSV,HML], defaults to TSV
     *   sqlDump         string|null    Location of existing SQL dump.
     *   hmlDump         string|null    Location of existing HML dump.
     *   tsvFiles        array|null     Location of existing TSV files.
     *   fileDirectories array|null     Location of files to pack.
     *   parentDir       string|null    Location of ro-crate placement, defaults to database filestore.
     *
     * @throws RuntimeException
     * @return string Path to created RO-Crate
     */
    public function export(string $crateDirectory, array $options = []) : string{

        $this->getDatabaseDetails();
        $databaseOwner = user_getDbOwner($this->mysqli);
        $ownerName = null;

        if($databaseOwner){
            $ownerName = $databaseOwner['ugr_FirstName'] && $databaseOwner['ugr_LastName'] ? "{$databaseOwner['ugr_FirstName']} {$databaseOwner['ugr_LastName']}" : null;
        }

        $databaseName = $this->system->dbname() ?? $this->databaseDetails['sys_dbName'];
        $description = $this->databaseDetails['sys_dbDescription'] ?? null;
        $ownerName ??= $this->databaseDetails['sys_dbOwner'];
        $license = $this->databaseDetails['sys_dbRights'] ?? null;
        $creationDate = $this->databaseDetails['firstRecord'] ?? null;

        $creator = [
            'name' => $ownerName,
            'email' => $databaseOwner['ugr_eMail'],
            'orcid' => $databaseOwner['ugr_ORCID'] ?? null
        ];

        $this->databaseFilestoreDir = $this->system->getSysDir(null, $databaseName);
        $this->databaseFilestoreDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->databaseFilestoreDir), DIRECTORY_SEPARATOR);

        $this->baseDirectory = $options['parentDir'] ?? $this->databaseFilestoreDir;
        $this->baseDirectory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->baseDirectory), DIRECTORY_SEPARATOR);

        $crateDirectory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($crateDirectory, '/\\'));
        $this->crateDirectory = $this->baseDirectory . DIRECTORY_SEPARATOR . $crateDirectory;

        if(!folderCreate($this->crateDirectory, true)){
            throw new RuntimeException('Unable to create RO-Crate directory.');
        }

        $includeSqlDump = (bool)($options['includeSqlDump'] ?? false);
        $includeFiles = (bool)($options['includeFiles'] ?? false);
        $recordOutputs = $options['recordFormat'] ?? ['TSV'];
        $sqlDump = $options['sqlDump'] ?? null;
        $hmlDump = $options['hmlDump'] ?? null;

        $this->databaseDirectory = $this->crateDirectory . DIRECTORY_SEPARATOR . 'database';

        if(!folderCreate($this->databaseDirectory, true)){
            throw new RuntimeException('Unable to create /database within the RO-Crate directory.');
        }

        $this->sqlFile = '';
        $this->hmlFile = '';
        $this->tsvFiles = [
            'tables' => [],
            'records' => []
        ];

        // Export database content as TSV
        if($options['tsvFiles']){
            $this->processTSVFiles($options['tsvFiles']);
        }else if(in_array('TSV', $recordOutputs)){

            $TSVExporter = new DbExportTSV();
            $TSVExporter->setSession($this->system, '{RTYNAME}');
            $TSVExporter->setBackupFolder($this->databaseDirectory, 'tables', 'records');

            [$warnings, $this->tsvFiles] = $TSVExporter->output();

            if(!empty($warnings)){
                $warnings = implode('<br>', $warnings);
                throw new RuntimeException("Encountered the following errors during TSV exporting:<br>{$warnings}");
            }
        }

        // Export HML dump
        if($hmlDump || in_array('HML', $recordOutputs)){

            $hmlFile = $this->databaseDirectory . DIRECTORY_SEPARATOR . "{$databaseName}.hml";

            $hmlFileStatus = false;
            if(file_exists($hmlDump)){
                $hmlFileStatus = fileCopy($hmlDump, $hmlFile);
            }

            if(!$hmlFileStatus){

                $_REQUEST['w'] = 'all';
                $_REQUEST['a'] = '1';
                $_REQUEST['q'] = 'sortby:-m';
                $_REQUEST['rev'] = 'no';
                $_REQUEST['depth'] = '0';
                $_REQUEST['linkmode'] = 'none';
                $_REQUEST['filename'] = '1';

                $hmlFileOutput = '';
                if(!defined('DIR_BACKUP')){
                    define('DIR_BACKUP', "{$crateDirectory}/database");
                    $hmlFileOutput = $this->databaseFilestoreDir . DIR_BACKUP;
                }else{
                    $hmlFileOutput = $hmlDump;
                }

                $to_include = __DIR__ . '/../../export/xml/flathml.php';
                if(is_file($to_include)){
                    include_once $to_include; // Execute HML export script
                }

                if(file_exists($hmlFileOutput)){
                    $hmlFileStatus = fileCopy($hmlFileOutput, $hmlFile);
                    fileDelete($hmlFileOutput);
                }
            }

            if($hmlFileStatus){
                $this->hmlFile = $hmlFile;
            }
        }

        // Export SQL dump
        if($includeSqlDump){

            $sqlFile = $this->databaseDirectory . DIRECTORY_SEPARATOR . "{$databaseName}.sql";

            $sqlFileStatus = $sqlDump && file_exists($sqlDump)
                ? fileCopy($sqlDump, $sqlFile)
                : DbUtils::databaseDump($databaseName, $sqlFile, self::MYSQL_DUMP_OPTIONS);

            if($sqlFileStatus){
                $this->sqlFile = $sqlFile;
            }
        }

        // Copy loose files into the crate, if supplied
        if($includeFiles){

            $this->fileDirectory = $this->crateDirectory . DIRECTORY_SEPARATOR . 'files';

            $this->copyFiles($this->databaseFilestoreDir, $options['fileDirectories'] ?? []);
        }

        // Get all other users
        $contributors = $this->getAllUsers();

        // Build the RO-Crate metadata after all files have been created
        $metadata = $this->buildMetadata($databaseName, $description, $creator, $license, $creationDate, $contributors);

        $metadataPath = $this->crateDirectory . DIRECTORY_SEPARATOR . 'ro-crate-metadata.json';

        //if(!json_validate($metadata)){ throw new RuntimeException('Unable to encode RO-Crate metadata, error: ' . json_last_error_msg()); }
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if($json === false){
            throw new RuntimeException('Unable to encode RO-Crate metadata, error: ' . json_last_error_msg());
        }

        if(file_put_contents($metadataPath, $json . PHP_EOL, LOCK_EX) === false){
            throw new RuntimeException("Unable to RO-Crate metadata file {$metadataPath}");
        }

        return $this->crateDirectory;
    }

    /**
     * Construct the RO-Crate metadata graph
     *
     * @param string $databaseName
     * @param ?string $description
     * @param ?array $creator
     * @param ?string $license
     * @param ?string $creationDate
     * @param ?array $contributors
     * @return array<string,mixed> RO-Crate metadata as JSON
     */
    private function buildMetadata(string $databaseName, ?string $description, ?array $creator, ?string $license, ?string $creationDate, ?array $contributors) : array{

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
            'name' => "Heurist RO-Crate for {$databaseName}",
            'datePublished' => gmdate('Y-m-d'),
            'hasPart' => []
        ];

        if($description !== null){
            $dataset['description'] = $description;
        }

        if($creator !== null){

            $entity = [
                '@id' => $creator['orcid'] ? "https://orcid.org/{$creator['orcid']}" : "user:Owner",
                '@type' => 'Person',
                'name' => $creator['name'],
                'email' => $creator['email']
            ];

            $graph[] = $entity;
            $dataset['creator'] = [['@id' => $entity['@id']]];
        }

        if($contributors !== null){

            $dataset['contributor'] = [];
            foreach($contributors as $user){

                $entity = [
                    '@id' => $user['orcid'] ? "https://orcid.org/{$user['orcid']}" : "user:{$user['id']}",
                    '@type' => 'Person',
                    'name' => $user['name'],
                    'email' => $user['email']
                ];

                $graph[] = $entity;
                $dataset['contributor'][] = ['@id' => $entity['@id']];
            }
        }

        if($license !== null){

            if(filter_var($license, FILTER_VALIDATE_URL)){ // URL to license
                $dataset['license'] = [
                    '@id' => $license
                ];
            }else{

                $licenseFile = $this->crateDirectory . DIRECTORY_SEPARATOR . 'LICENSE.txt';

                $dataset['license'] = file_put_contents($licenseFile, $license . PHP_EOL, LOCK_EX) !== false
                    ? [ '@id' => 'LICENSE.txt' ]
                    : [ 'name' => "License for {$databaseName}", 'description' => $license ];
            }
        }

        if($creationDate !== null){
            $dataset['dateCreated'] = $creationDate;
        }

        // Database-specific metadata
        $databaseEntity = [
            '@id' => '#database',
            '@type' => 'Dataset',
            'id' => $this->databaseDetails['sys_dbRegisteredID'] ?? 0,
            'name' => $databaseName
        ];

        // TSV Files
        // Records
        foreach($this->tsvFiles['records'] as $rtyID => $relativePath){

            if(strpos($relativePath, 'records' . DIRECTORY_SEPARATOR) === false){
                $relativePath = 'records' . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
            }
            if(strpos($relativePath, 'database' . DIRECTORY_SEPARATOR) === false){
                $relativePath = DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
            }
            $absolutePath = "{$this->crateDirectory}{$relativePath}";

            $entity = [
                '@id' => $relativePath,
                '@type' => 'File',
                'name' => pathinfo($absolutePath, PATHINFO_FILENAME),
                'encodingFormat' => 'text/tab-separated-values',
                'contentSize' => filesize($absolutePath),
                'sha256' => hash_file('sha256', $absolutePath)
            ];

            $this->retrieveRecTypeDetails($rtyID, $entity);

            $graph[] = $entity;
            $dataset['hasPart'][] = [
                '@id' => $relativePath
            ];
        }

        // Other Tables
        foreach($this->tsvFiles['tables'] as $relativePath){

            if(strpos($relativePath, 'tables' . DIRECTORY_SEPARATOR) === false){
                $relativePath = 'tables' . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
            }
            if(strpos($relativePath, 'database' . DIRECTORY_SEPARATOR) === false){
                $relativePath = DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
            }
            $absolutePath = "{$this->crateDirectory}{$relativePath}";

            $entity = [
                '@id' => $relativePath,
                '@type' => 'File',
                'name' => pathinfo($absolutePath, PATHINFO_FILENAME),
                'encodingFormat' => 'text/tab-separated-values',
                'contentSize' => filesize($absolutePath),
                'sha256' => hash_file('sha256', $absolutePath)
            ];

            $graph[] = $entity;
            $dataset['hasPart'][] = [
                '@id' => $relativePath
            ];
        }

        // SQL dump, if present
        if($this->sqlFile && file_exists($this->sqlFile)){

            $relativePath = str_replace($this->crateDirectory, '', $this->sqlFile);

            $graph[] = [
                '@id' => $relativePath,
                '@type' => 'File',
                'name' => basename($this->sqlFile),
                'encodingFormat' => 'application/sql',
                'contentSize' => filesize($this->sqlFile),
                'sha256' => hash_file('sha256', $this->sqlFile)
            ];

            $dataset['hasPart'][] = [
                '@id' => $relativePath
            ];
        }

        // HML dump, if present
        if($this->hmlFile && file_exists($this->hmlFile)){

            $relativePath = str_replace($this->crateDirectory, '', $this->hmlFile);

            $graph[] = [
                '@id' => $relativePath,
                '@type' => 'File',
                'name' => basename($this->hmlFile),
                'encodingFormat' => 'application/xml',
                'contentSize' => filesize($this->hmlFile),
                'sha256' => hash_file('sha256', $this->hmlFile)
            ];

            $dataset['hasPart'][] = [
                '@id' => $relativePath
            ];
        }

        // Filestore files
        if(folderExists($this->fileDirectory, false)){

            $files = $this->findFiles($this->fileDirectory);

            foreach($files as $file){

                $relativePath = str_replace($this->crateDirectory, '', $file);

                $entity = [
                    '@id' => $relativePath,
                    '@type' => 'File',
                    'name' => basename($file),
                    'contentSize' => filesize($file),
                    'sha256' => hash_file('sha256', $file)
                ];

                $mimeType = $this->mimeType($file);

                if($mimeType !== null){
                    $entity['encodingFormat'] = $mimeType;
                }

                $graph[] = $entity;

                $dataset['hasPart'][] = [
                    '@id' => $relativePath
                ];
            }
        }


        // Preserve the relationship between the root dataset and the logical database dataset
        $dataset['hasPart'][] = [
            '@id' => '#database'
        ];

        // Place the ro-crate-metadata, root dataset, and database entity first
        array_unshift($graph, $databaseEntity);
        array_unshift($graph, $dataset);
        array_unshift($graph, $roCrateMetadata);

        return [
            '@context' => [self::RO_CRATE_CONTEXT],
            '@graph' => $graph
        ];
    }

    /**
     * Obtain details about the current database
     * 
     * @param ?string $databaseName
     * @throws InvalidArgumentException If the database name is invalid
     * @throws RuntimeException If sysIdentification cannot be queried
     */
    private function getDatabaseDetails(?string $databaseName = null){

        if(!empty($databaseName) && !preg_match(REGEX_ALPHANUM, $databaseName)){
            throw new InvalidArgumentException('Provided database name is invalid.');
        }

        $fromDatabase = !empty($databaseName) ? "{$databaseName}." : '';

        $sysInfoRow = mysql__select_row_assoc($this->mysqli, "SELECT sys_dbRegisteredID, sys_dbName, sys_dbOwner, sys_dbRights, sys_dbDescription FROM {$fromDatabase}sysIdentification LIMIT 1");
        if(!$sysInfoRow){
            throw new RuntimeException('Unable to retrieve database details from sysIdentification.');
        }

        $firstRecordDate = mysql__select_value($this->mysqli, "SELECT rec_Added FROM {$fromDatabase}Records ORDER BY rec_Added LIMIT 1");
        if($firstRecordDate){
            $firstRecordDate = date_create($firstRecordDate);
            $sysInfoRow['firstRecord'] = $firstRecordDate ? $firstRecordDate->format('Y-m-d') : null;
        }

        $this->databaseDetails = $sysInfoRow;
    }

    /**
     * Copy pre-made TSV files to crate
     *
     * @param array $tsvFiles
     * @throws RuntimeException
     */
    private function processTSVFiles(array $tsvFiles){

        $tsvTablesDir = rtrim($tsvFiles['tablesDir'] ?? '', '/\\');
        $tsvRecordsDir = rtrim($tsvFiles['recordsDir'] ?? '', '/\\');

        $this->tsvFiles = ['tables' => [], 'records' => []];
        if(folderExists($tsvTablesDir, true)){

            $tablesDirectory = $this->databaseDirectory . DIRECTORY_SEPARATOR . 'tables';
            if(!folderCreate($tablesDirectory, true)){
                throw new RuntimeException('Failed to create tables sub-directory within crate.');
            }

            foreach($tsvFiles['tables'] as $file){

                $file = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), DIRECTORY_SEPARATOR);
                $fileParts = explode(DIRECTORY_SEPARATOR, $file);
                $file = array_pop($fileParts);

                $fullPath = $tsvTablesDir . DIRECTORY_SEPARATOR . $file;
                if(file_exists($fullPath) && fileCopy($fullPath, $tablesDirectory . DIRECTORY_SEPARATOR . $file)){
                    $this->tsvFiles['tables'][] = $file;
                }
            }
        }
        if(folderExists($tsvRecordsDir, true)){

            $recordsDirectory = $this->databaseDirectory . DIRECTORY_SEPARATOR . 'records';
            if(!folderCreate($recordsDirectory, true)){
                throw new RuntimeException('Failed to create tables sub-directory within crate.');
            }

            foreach($tsvFiles['records'] as $rtyID => $file){

                $file = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), DIRECTORY_SEPARATOR);
                $fileParts = explode(DIRECTORY_SEPARATOR, $file);
                $file = $tsvRecordsDir === '' ? $file : array_pop($fileParts);

                $fullPath = $tsvRecordsDir . DIRECTORY_SEPARATOR . $file;
                if(file_exists($fullPath) && fileCopy($fullPath, $recordsDirectory . DIRECTORY_SEPARATOR . $file)){
                    $this->tsvFiles['records'][$rtyID] = $file;
                }
            }
        }
    }

    /**
     * Recursively list files within a directory
     *
     * @param string $directory
     * @param callable(string):bool|null $filter
     * @return array<string>
     */
    private function findFiles(string $directory, ?callable $filter = null) : array{

        if(!is_dir($directory)){
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach($iterator as $file){

            if(!$file->isFile()){
                continue;
            }

            $path = $file->getPathname();

            if($filter !== null && !$filter($path)){
                continue;
            }

            $files[] = $path;
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Copy files from the provided sub-directories
     *
     * @param string $baseDirectory
     * @param array $subDirectories
     */
    private function copyFiles(string $baseDirectory, array $subDirectories){

        if(empty($subDirectories)){
            $this->copyDirectory($baseDirectory, $this->fileDirectory);
            return;
        }

        foreach($subDirectories as $directory){

            $directory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
            $directory = str_replace($baseDirectory, '', $directory);

            $fullDirectory = "{$baseDirectory}{$directory}";
            if(folderExists($fullDirectory, false) !== 1){
                continue;
            }

            $destination = $this->fileDirectory . DIRECTORY_SEPARATOR . $directory;

            $this->copyDirectory($fullDirectory, $destination);
        }
    }

    /**
     * Recursively copy a directory from source to destination
     *
     * @param string $source
     * @param string $destination
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function copyDirectory(string $source, string $destination){

        if(!is_dir($source)){
            throw new InvalidArgumentException("Source file directory does not exist: {$source}");
        }

        if(!folderCreate($destination, true)){
            throw new RuntimeException("Failed to create destination directory {$destination}");
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach($iterator as $item){

            $relative = substr($item->getPathname(), strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1);

            $target = $destination . DIRECTORY_SEPARATOR . $relative;

            if($item->isDir()){

                if(!folderCreate(dirname($target), true)){
                    throw new RuntimeException("Failed to create target directory {$target}");
                }

                continue;
            }

            if(!folderCreate(dirname($target), true)){
                throw new RuntimeException('Failed to create target directory ' . dirname($target));
            }

            if(!copy($item->getPathname(), $target)){
                throw new RuntimeException("Unable to copy {$item->getPathname()} to {$target}");
            }
        }
    }

    /**
     * Determine a MIME type where possible
     * 
     * @param string $file
     * @return ?string
     */
    private function mimeType(string $file) : ?string{

        if(!function_exists('finfo_open')){
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if($finfo === false){
            return null;
        }

        $type = finfo_file($finfo, $file);

        finfo_close($finfo);

        return is_string($type) ? $type : null;
    }

    /**
     * Get a list of non-owner users to list as contributors
     *
     * @return array{email: string, id: string, name: string, orcid: string}|null
     */
    private function getAllUsers() : ?array{

        $userQuery = "SELECT ugr_ID, ugr_eMail, ugr_FirstName, ugr_LastName, ugr_ORCID, ugr_Name FROM sysUGrps WHERE ugr_Type = 'user' AND ugr_ID != 2";
        $results = $this->mysqli->query($userQuery);

        if(!$results){
            return null;
        }

        $users = [];
        while($row = $results->fetch_row()){

            $name = $row[2] && $row[3] ? "{$row[2]} {$row[3]}" : null;
            if(!$name){
                $name = $row[2] ?? $row[3];
            }
            $name ??= $row[5];
            $name ??= $row[1];

            $users[] = [
                'id' => $row[0],
                'email' => $row[1],
                'name' => $name,
                'orcid' => $row[4]
            ];
        }

        $results->free();

        return $users;
    }

    /**
     * Get record type details and add to entity array
     *
     * @param int $rtyID
     * @param array $entity
     */
    private function retrieveRecTypeDetails(int $rtyID, array &$entity){

        if(!isPositiveInt($rtyID)){
            return;
        }

        $recTypeQuery = "SELECT rty_Description, rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes WHERE rty_ID = {$rtyID}";
        $results = $this->mysqli->query($recTypeQuery);

        if(!$results){
            return;
        }

        $row = $results->fetch_assoc();
        $dbID = $this->databaseDetails['sys_dbRegisteredID'] ?? 0;
        
        $row['rty_OriginatingDBID'] ??= $dbID;
        $row['rty_IDInOriginatingDB'] ??= $rtyID;
        $entity['identifier'] = ["{$row['rty_OriginatingDBID']}-{$row['rty_IDInOriginatingDB']}", $rtyID];

        $entity['description'] = $row['rty_Description'];
        $entity['url'] = rtrim(HEURIST_BASE_URL_PRO ?? HEURIST_BASE_URL, '/\\') . DIRECTORY_SEPARATOR . "?db={$this->system->dbname()}&rty={$rtyID}";

        $results->free();
    }
}