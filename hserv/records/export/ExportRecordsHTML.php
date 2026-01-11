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

        $langDir = $htmlOutputDir.$lang.'/';
        if (!is_dir($langDir)) {
            @mkdir($langDir, 0775, true);
        }

        $force = true || !empty($params['force']); // optional: regenerate even if file exists

        // Public host (used only for Host header, not for routing)
        $publicHost = HEURIST_DOMAIN;//'heuristau.net';

        // Local URL to avoid Cloudflare/network
        $publishUrl = 'http://127.0.0.1/h7-alpha/viewers/record/renderRecordData.php'
            .'?forceCache=1'
            .'&db='.rawurlencode($this->system->dbname())
            .'&lang='.rawurlencode($lang)
            .'&recID=';
            

        if (!$this->_outputPrepare($data, $params)) {
            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'HeuristCachePublisher/1.0',
            CURLOPT_HTTPHEADER     => [
                'Host: '.HEURIST_DOMAIN,
                'Accept: text/html',
            ],
        ]);
        
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
                //error_log("Cache publish failed (cURL $errno) recID=$recID url=$url");
                continue;
            }

            if ($http >= 400) {
                $cntErros++;
                //error_log("Cache publish failed (HTTP $http) recID=$recID url=$url");
                continue;
            }
            
            // Optional: verify cache file created
            if (!is_file($cachedFile)) {
                
                if($body==1){
                    $cntNotFound++; 
                }elseif($body==2){
                    $cntSkip++; 
                }else{
                    $cntErros++;
                }
                //error_log("Cache publish did not create file recID=$recID (expected $cachedFile)");
                continue;
            }
            
            $cntCached++;

            if (!empty($params['sleep_ms'])) {
                usleep((int)$params['sleep_ms'] * 1000);
            }
        }
        
        echo 'Processed: '.count($this->records).'. Cached: '.$cntCached.
                '. Not public: '.$cntSkip.'. Not found: '.$cntNotFound.'. Errors: '.$cntErros;

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
