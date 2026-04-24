<?php
/**
* ExportRecordsHTML.php - Class ExportRecordsHTML
* 
* Extends `ExportRecords` to export records as HTML - file per file
*
* @project     Heurist academic knowledge management system
* @package Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\records\export;
//use hserv\records\export\ExportRecords;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

/**
 * Class ExportRecordsHTML
 *
 * Extends `ExportRecords` to export records as HTML - file per file.
 * This class is typically controlled by the 'records_output' controller.
 */
class ExportRecordsHTML extends ExportRecords {

    protected function _outputHeader(){
    }
    protected function _outputRecord($record){
        return true;
    }
    protected function _outputFooter(){
    }    

    public function output($data, $params)
    {
        $htmlOutputDir = rtrim($this->system->getSysDir('html-output'), '/').'/';

        $params['linkmode'] = 'none';

        $lang = $params['lang'] ?? 'eng';
        $lang = preg_replace('~[^a-z0-9_-]+~i', '', $lang) ?: 'eng';
        $lang = strtolower($lang);

        $langDir = $htmlOutputDir.$lang.'/';
        if (!is_dir($langDir)) {
            @mkdir($langDir, 0775, true);
        }

        $force = true; //!empty($params['force']);

        // Public host only for vhost routing via Host header
        $publicHost = defined('HEURIST_DOMAIN') ? HEURIST_DOMAIN : 'heuristau.net';

        // Local URL to avoid Cloudflare/network
        $publishUrl = 'https://127.0.0.1/heurist/viewers/record/renderRecordData.php'
            .'?forceCache=1'
            .'&db='.rawurlencode($this->system->dbname())
            .'&lang='.rawurlencode($lang)
            .'&recID=';

        if (!$this->_outputPrepare($data, $params)) {
            return false;
        }

        $ch = curl_init();

        // --- Common options for HTTPS loopback ---
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'HeuristCachePublisher/1.0',

            // Ensure we do GET (some environments behave oddly otherwise)
            CURLOPT_HTTPGET        => true,

            // Route to the correct vhost/app using Host header
            CURLOPT_HTTPHEADER     => [
                'Host: '.$publicHost,
                'Accept: text/html',
            ],

            /**
             * TLS note:
             * We connect to https://127.0.0.1 but the cert is for heuristau.net,
             * so verification would fail. This is loopback only, so disabling
             * verify is acceptable.
             */
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,

            // Optional: avoid “Expect: 100-continue” edge cases
            CURLOPT_HTTPHEADER     => [
                'Host: '.$publicHost,
                'Accept: text/html',
                'Expect:',
            ],
        ]);

        // --- Preflight check before processing records ---
        // Use a lightweight request; we just want to confirm the endpoint is reachable.
        $testRecId = 183; // any integer; the goal is reachability
        $testUrl   = $publishUrl.$testRecId;

        curl_setopt($ch, CURLOPT_URL, $testUrl);
        $testBody  = curl_exec($ch);
        $testErrno = curl_errno($ch);
        $testHttp  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($testErrno !== 0) {
            curl_close($ch);
            error_log("Cache publisher preflight failed (cURL $testErrno) url=$testUrl");
            echo "Preflight failed (cURL $testErrno). Aborting.";
            return false;
        }

        if ($testHttp >= 500) { // treat 5xx as service not healthy
            curl_close($ch);
            error_log("Cache publisher preflight failed (HTTP $testHttp) url=$testUrl");
            echo "Preflight failed (HTTP $testHttp). Aborting.";
            return false;
        }
        // Note: 404 is OK for preflight (record might not exist); we only care that service responds.

        $cntErros = 0;
        $cntSkip = 0;
        $cntCached = 0;
        $cntNotFound = 0;

        foreach ($this->records as $record) {
            $recID = is_array($record) ? (int)($record['rec_ID'] ?? 0) : (int)$record;
            if ($recID <= 0) {
                continue;
            }

            $cachedFile = $langDir.$recID.'.html';

            if (!$force && is_file($cachedFile)) {
                $cntSkip++;
                continue;
            }

            $url = $publishUrl.$recID;
            curl_setopt($ch, CURLOPT_URL, $url);

            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($errno !== 0) {
                $cntErros++;
                error_log("Cache publish failed (cURL $errno) recID=$recID url=$url");
                continue;
            }

            if ($http >= 400) {
                $cntErros++;
                error_log("Cache publish failed (HTTP $http) recID=$recID url=$url");
                continue;
            }

            // Verify cache file created
            if (!is_file($cachedFile)) {
                // body codes: 1=not found, 2=not public
                if ((string)$body === '1') {
                    $cntNotFound++;
                } elseif ((string)$body === '2') {
                    $cntSkip++;
                } else {
                    $cntErros++;
                }
                //error_log("Cache publish did not create file recID=$recID (expected $cachedFile) Code=".$body);
                continue;
            }

            $cntCached++;

            if (!empty($params['sleep_ms'])) {
                usleep((int)$params['sleep_ms'] * 1000);
            }
        }

        echo 'Processed: '.count($this->records)
            .'. Cached: '.$cntCached
            .'. Not public: '.$cntSkip
            .'. Not found: '.$cntNotFound
            .'. Errors: '.$cntErros;

        curl_close($ch);
        return true;
    }

    
    
    /**
     * Clear all cached HTML files from html-output language subfolders.
     * $cacheDir = $system->getSysDir('html-output');
     *
     * @param string $htmlOutputDir Absolute filesystem path to html-output directory
     * @return int Number of files deleted
     */
    public static function clearHtmlOutputCache(string $htmlOutputDir): int
    {
        $deleted = 0;

        $htmlOutputDir = rtrim($htmlOutputDir, '/').'/';

        if (!is_dir($htmlOutputDir)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $htmlOutputDir,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            
            if ($item->isFile() && 
                strtolower($item->getFilename()) !== 'index.html' && 
                strtolower($item->getExtension()) === 'html') 
            {
                if (@unlink($item->getPathname())) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
    
    public static function clearHtmlOutputCacheForRecord(string $htmlOutputDir, int $recId): int
    {
        $deleted = 0;

        $htmlOutputDir = rtrim($htmlOutputDir, '/').'/';

        if (!is_dir($htmlOutputDir)) {
            return 0;
        }
        foreach (glob($htmlOutputDir.'*/'.$recId.'.html') as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
    

} //end class
