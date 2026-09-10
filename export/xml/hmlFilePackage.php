<?php
/**
 * Utility functions for adding the local files emitted by an HML export to a ZIP package.
 */

require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';

/**
 * Create a ZIP containing an HML file and the local uploaded files represented in it.
 *
 * @param hserv\System $system
 * @param string $hml_file
 * @param array<int,int> $file_ids IDs collected while HML details were output.
 * @return string|false Path to the ZIP, or false on failure.
 */
function createHmlFilePackage($system, $hml_file, $file_ids) {
    if (!extension_loaded('zip') || !is_file($hml_file)) {
        return false;
    }

    $zip_file = tempnam(HEURIST_SCRATCHSPACE_DIR, 'hmlzip');
    if ($zip_file === false) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::OVERWRITE) !== true) {
        unlink($zip_file);
        return false;
    }

    $zip->addFile($hml_file, 'Export_'.$system->dbname().'.xml');

    $file_ids = prepareIds(array_keys($file_ids));
    $files = empty($file_ids) ? [] : fileGetFullInfo($system, $file_ids);
    $used_names = [];
    $filestore_root = realpath(HEURIST_FILESTORE_DIR);
    $filestore_root = $filestore_root === false ? '' : rtrim(str_replace('\\', '/', $filestore_root), '/').'/';

    foreach ((array)$files as $file) {
        if (!empty($file['ulf_ExternalFileReference'])) {
            continue; // The HML retains the external URL; there is no local file to package.
        }

        $source = resolveFilePath($file['fullPath']);
        if (!is_file($source)) {
            continue;
        }

        $source_normalized = str_replace('\\', '/', realpath($source));
        $relative = $filestore_root !== '' && strpos($source_normalized, $filestore_root) === 0
            ? substr($source_normalized, strlen($filestore_root))
            : 'uploaded_files/'.$file['ulf_ObfuscatedFileID'].'_'.basename($source);

        if (isset($used_names[$relative])) {
            $relative = 'uploaded_files/'.$file['ulf_ObfuscatedFileID'].'_'.basename($source);
        }
        $used_names[$relative] = true;
        if (!$zip->addFile($source, $relative)) {
            $zip->close();
            unlink($zip_file);
            return false;
        }
    }

    if (!$zip->close()) {
        unlink($zip_file);
        return false;
    }
    return $zip_file;
}
