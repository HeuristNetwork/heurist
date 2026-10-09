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

    private ?string $crateDirectory;
    private ?string $databaseDirectory;
    private ?string $databaseFilestoreDir;
    private ?string $sqlFile;
    private ?array $tsvFiles;

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
     *   includeSqlDump  bool        Whether to include an SQL database dump.
     *   includeFiles    bool        Whether to include the files from the filestore.
     *   sqlDump         string|null Location of existing SQL dump.
     *   tsvFiles        array|null  Location of existing TSV files.
     *   parentDir       string|null Location of ro-crate placement, defaults to database filestore.
     *
     * @throws RuntimeException
     * @return string Path to created RO-Crate
     */
    public function export(string $crateDirectory, array $options = []) : string{

        $databaseDetails = $this->getDatabaseDetails();
        $databaseOwner = user_getDbOwner($this->mysqli);
        $ownerName = null;

        if($databaseOwner){
            $ownerName = $databaseOwner['ugr_FirstName'] && $databaseOwner['ugr_LastName'] ? "{$databaseOwner['ugr_FirstName']} {$databaseOwner['ugr_LastName']}" : null;
        }

        $databaseName = $this->system->dbname() ?? $databaseDetails['sys_dbName'];
        $description = $databaseDetails['sys_dbDescription'] ?? null;
        $ownerName ??= $databaseDetails['sys_dbOwner'];
        $license = $databaseDetails['sys_dbRights'] ?? null;

        $creator = [
            'name' => $ownerName,
            'email' => $databaseOwner['ugr_eMail'],
            'orcid' => $databaseOwner['ugr_ORCID'] ?? null
        ];

        $this->databaseFilestoreDir = $options['parentDir'] ?? $this->system->getSysDir(null, $databaseName);
        $this->databaseFilestoreDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->databaseFilestoreDir), DIRECTORY_SEPARATOR);

        $this->crateDirectory = $this->databaseFilestoreDir . DIRECTORY_SEPARATOR . trim($crateDirectory, DIRECTORY_SEPARATOR);

        if(!folderCreate($this->crateDirectory, true)){
            throw new RuntimeException('Unable to create RO-Crate directory.');
        }

        $includeSqlDump = (bool)($options['includeSqlDump'] ?? false);
        $includeFiles = (bool)($options['includeFiles'] ?? false);
        $sqlDump = $options['sqlDump'] ?? null;

        $this->databaseDirectory = $this->crateDirectory . DIRECTORY_SEPARATOR . 'database';

        if(!folderCreate($this->databaseDirectory, true)){
            throw new RuntimeException('Unable to create /database within the RO-Crate directory.');
        }

        $this->sqlFile = '';
        $this->tsvFiles = [];

        if($options['tsvFiles']){
            $this->processTSVFiles($options['tsvFiles']);
        }else{

            // Export database content as TSV
            $TSVExporter = new DbExportTSV();
            $TSVExporter->setSession($this->system, '{RTYNAME}');
            $TSVExporter->setBackupFolder($this->databaseDirectory, 'tables', 'records');

            [$warnings, $this->tsvFiles] = $TSVExporter->output();

            if(!empty($warnings)){
                $warnings = implode('<br>', $warnings);
                throw new RuntimeException("Encountered the following errors during TSV exporting:<br>{$warnings}");
            }
            $this->tsvFiles = array_merge($this->tsvFiles['tables'], $this->tsvFiles['records']);
        }

        // Export SQL dump
        $sqlDumpStatus = false;
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
        if(false && $includeFiles){

            $filesDirectory = $this->crateDirectory . DIRECTORY_SEPARATOR . 'files';
            $filestoreDir = $this->system->getSysDir(null, $databaseName);

            $this->copyDirectory($filestoreDir, $filesDirectory);
        }

        // Build the RO-Crate metadata after all files have been created
        $metadata = $this->buildMetadata($databaseName, $description, $creator, $license);

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
     * @return array<string,mixed> RO-Crate metadata as JSON
     */
    private function buildMetadata(string $databaseName, ?string $description, ?array $creator, ?string $license) : array{

        $graph = [];

        // RO-Crate metadata descriptor
        $graph[] = [
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
            'dateCreated' => gmdate('Y-m-d'),
            'hasPart' => []
        ];
        //'datePublished' => ???

        if($description !== null){
            $dataset['description'] = $description;
        }

        if($creator !== null){
            $dataset['creator'] = [
                '@id' => $creator['orcid'] ? "https://orcid.org/{$creator['orcid']}" : null,
                '@type' => 'Person',
                'name' => $creator['name'],
                'email' => $creator['email']
            ];
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

        // Database-specific metadata
        $databaseEntity = [
            '@id' => '#database',
            '@type' => 'Dataset',
            'name' => $databaseName,
        ];

        foreach($this->tsvFiles as $relativePath){

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
                '@id' => $relativePath,
            ];
        }

        // SQL dump, if present
        if(file_exists($this->sqlFile)){

            $relativePath = str_replace($this->crateDirectory, '', $this->sqlFile);

            $graph[] = [
                '@id' => $relativePath,
                '@type' => 'File',
                'name' => basename($this->sqlFile),
                'encodingFormat' => 'application/sql',
                'contentSize' => filesize($this->sqlFile),
                'sha256' => hash_file('sha256', $this->sqlFile),
            ];

            $dataset['hasPart'][] = [
                '@id' => $relativePath,
            ];
        }

        // Filestore files
        $filesDirectory = $this->crateDirectory . DIRECTORY_SEPARATOR . 'files';

        if(false && is_dir($filesDirectory)){

            $files = $this->findFiles($filesDirectory);

            foreach($files as $file){

                $relativePath = $this->relativePath($file);

                $entity = [
                    '@id' => $relativePath,
                    '@type' => 'File',
                    'name' => basename($file),
                    'contentSize' => filesize($file),
                    'sha256' => hash_file('sha256', $file)
                ];

                $mimeType = $this->mimeType($file);

                if ($mimeType !== null) {
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

        $graph[] = $databaseEntity;
        $graph[] = $dataset;

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
     * @return array Database system information from sysIdentification
     */
    private function getDatabaseDetails(?string $databaseName = null) : array{

        if(!empty($databaseName) && !preg_match(REGEX_ALPHANUM, $databaseName)){
            throw new InvalidArgumentException('Provided database name is invalid.');
        }

        $fromDatabase = !empty($databaseName) ? "{$databaseName}." : '';
        $sysInfoRow = mysql__select_row_assoc($this->mysqli, "SELECT sys_dbRegisteredID, sys_dbName, sys_dbOwner, sys_dbRights, sys_dbDescription FROM {$fromDatabase}sysIdentification LIMIT 1");
        if(!$sysInfoRow){
            throw new RuntimeException('Unable to retrieve database details from sysIdentification.');
        }

        return $sysInfoRow;
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

            foreach($tsvFiles['tables'] as $file){

                $file = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), DIRECTORY_SEPARATOR);

                $fullPath = $tsvRecordsDir . DIRECTORY_SEPARATOR . $file;
                if(file_exists($fullPath) && fileCopy($fullPath, $recordsDirectory . DIRECTORY_SEPARATOR . $file)){
                    $this->tsvFiles['records'][] = $file;
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
     * Return a relative path from the RO-Crate base
     *
     * @param string $path
     * @throws RuntimeException
     * @return string
     */
    private function relativePath(string $path): string{

        $crateDirectory = rtrim(realpath($this->crateDirectory) ?: $this->crateDirectory, DIRECTORY_SEPARATOR);

        $realPath = realpath($path) ?: $path;

        if ($realPath !== $crateDirectory && strpos($realPath, $crateDirectory . DIRECTORY_SEPARATOR) !== 0){
            throw new RuntimeException("File is outside crate: {$path}");
        }

        $relative = substr($realPath, strlen($crateDirectory));

        return ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relative), '/');
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
}