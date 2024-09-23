<?php
/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* URLChecker.php - class to check and validate URLs from various sources in the database.
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

define('CURL_RANGE','0-500');

/**
 * Class URLChecker
 * A class to check and validate URLs from various sources in the database.
 */
class URLChecker {

    /** @var mysqli $mysqli Database connection */
    private $mysqli;

    /** @var string $heuristServerUrl The base URL of the Heurist server */
    private $heuristServerUrl;

    /** @var bool $isHeuristReferenceIndex Whether the database is Heurist_Reference_Index */
    private $isHeuristReferenceIndex;

    /** @var array $passedRecIds Array of record IDs that passed URL validation */
    private $passedRecIds = [];
    
    
    /** @var bool $isVerbose Whether echo results at once */
    private $isVerbose = false;
    
    /**
     * Constructor for URLChecker.
     *
     * @param mysqli $mysqli
     * @param string $heuristServerUrl
     * @param bool $isHeuristReferenceIndex
     */
    public function __construct($mysqli, $heuristServerUrl, $isHeuristReferenceIndex) {
        $this->mysqli = $mysqli;
        $this->heuristServerUrl = strtolower($heuristServerUrl);
        $this->isHeuristReferenceIndex = $isHeuristReferenceIndex;
    }

    /**
     * Updates the last verified date for the given record IDs.
     *
     * @return void
     */
    private function updateRecordsLastVerified() {
        $date = date(DATE_8601);
        $query = 'UPDATE Records SET rec_URLLastVerified="' . $date . '", rec_URLErrorMessage=null WHERE rec_ID IN (' 
                            . implode(',', $this->passedRecIds) . ')';
        $this->mysqli->query($query);
        $this->passedRecIds = []; // reset the array
    }

    /**
     * Check URLs in various sources and validate them.
     *
     * @param bool $isVerbose echo results at once
     * @param bool $listOnly Only list the URLs, do not perform any validations.
     * @return array Results of the URL validation or boolean.
     */
    public function checkURLs($isVerbose = false, $listOnly = false) {

        $results =  [
            0 => [],  // Broken record URLs   'count' => 0, 'broken_urls' => []
            1 => [],  // Broken free text/block text URLs
            2 => [],  // Broken external file URLs
            3 => ''   //fatal CURL error message
            ];
            
        $this->isVerbose = $isVerbose;
        $this->listOnly = $listOnly;

        // Check record URLs
        $this->checkRecordURLs($results);
        
        // Check free text/block text fields for URLs
        $this->checkTextFieldURLs($results);

        // Check external URLs in use (e.g., file fields)
        $this->checkExternalFileURLs($results);

        return $results;
    }
    
    private function printHeader($sHeader){
        if($this->isVerbose || $this->listOnly){
            print $sHeader;
        }
    }

    /**
     * Check URLs from records and validate.
     *
     * @param array $results
     * @return void
     */
    private function checkRecordURLs(&$results) {
        $query = 'SELECT rec_ID, rec_URL, rec_RecTypeID FROM Records WHERE (rec_URL != "") AND (rec_URL IS NOT NULL)';
        $res = $this->mysqli->query($query);
        
        $this->printHeader('<h4>Records URLs</h4>');
        
        if (!$res) {
            if($this->isVerbose || $this->listOnly){
                print error_Div('Cant retrieve records with URLs: '.$this->mysqli->error);
            }
            return;
        }
        
        $passed_cnt=0;
    
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $recUrl = $row[1];
            $recTypeId = $row[2];

            // Modify URL for Heurist Reference Index if necessary
            $isReferenceDatabase = false;
            if ($this->isHeuristReferenceIndex && ($recTypeId == 101 || $recTypeId == 103)) {
                $isReferenceDatabase = true;
                $recUrl .= '&isalive=1';
            }

            // Handle listing URLs without validation
            if ($this->listOnly) {
                $recUrl = htmlentities($recUrl);
                echo intval($recId) . ' : <a href="' . $recUrl . '" target="_blank" rel="noopener">' . $recUrl . '</a><br>';
                continue;
            }
            // Skip URLs that match the Heurist server URL
            if (strpos(strtolower($recUrl), $this->heuristServerUrl) === 0) {
                continue;
            }

            // Validate the URL
            $data = $this->loadRemoteURLContent($recUrl);

            if ($data) {

                $this->handleRecordUrl($recId, $recUrl, $data, $isReferenceDatabase);
                
            } elseif($this->handleBrokenRecordUrl($recId, $recUrl, $results)){
                break;
            }     
            $passed_cnt++;
        } //while
        $res->close();
        

        if (!empty($this->passedRecIds)) {
            $this->updateRecordsLastVerified();
        }
        
        
        if($this->isVerbose){
            echo '<p>Record URLs. Processed: '.$passed_cnt.' records</p>';
            
            $broken_cnt = count($results[0]);

            if($broken_cnt>0){

                print '<div style="padding-top:20px;color:red">There are <b>'.$broken_cnt
                .'</b> records with broken url. Search "_BROKEN_" for details</div>';
            }else{
                print '<div><h3 class="res-valid">OK: All records have valid URL</h3></div>';
            }
        }
    }
    
    /**
    * Helper function
    * 
    * @param mixed $recId
    * @param mixed $recUrl
    * @param mixed $data
    */
    private function handleRecordUrl($recId, $recUrl, $data, $isReferenceDatabase){
        
        //special case for reference index database
        if($isReferenceDatabase && strpos($data, 'error: ')===0 && $this->isVerbose){

            $recUrl = htmlspecialchars($recUrl);
            print intval($recId)
            ." : <a href=\"$recUrl\" target=\"_blank\" rel=\"noopener\">$recUrl</a>";

            $data = strpos($data, 'timeout') !== false ? 'Timeout occurred' : $data;
            $data = strpos($data, 'does not exist') !== false ? 'Database does not exist' : $data;

            print error_Div(htmlspecialchars($data));
        }else{
            $this->passedRecIds[] = $recId;
            if (count($this->passedRecIds) > 1000) {
                $this->updateRecordsLastVerified();
            }
        }
    }

    /**
     * Check free text and block text fields for URLs.
     *
     * @param array $results
     * @return void
     */
    private function checkTextFieldURLs(&$results) {
        $query = 'SELECT dtl_RecID, dtl_Value, dtl_DetailTypeID FROM recDetails ' .
            'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID ' .
            'INNER JOIN Records ON rec_ID = dtl_RecID ' .
            'WHERE (dty_Type = "freetext" OR dty_Type = "blocktext") AND dtl_Value REGEXP "https?://"';
        
        $res = $this->mysqli->query($query);

        $this->printHeader('<h4>URLs in text fields</h4>');
        
        if (!$res) {
            if($this->isVerbose || $this->listOnly){
                print error_Div('Cant retrieve records with URLs in text fields: '.$this->mysqli->error);
            }
            return;
        }
        
        $passed_cnt = 0;
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $value = $row[1];
            $detailTypeId = $row[2];

            $urls = [];
            preg_match_all("/https?:\/\/[^\s\"'>()\\\\]*/", $value, $urls);
            $urls = $urls[0] ?? [];
            
            foreach ($urls as $url) {
                if($this->validateAndHandleFieldUrl($recId, $url, $detailTypeId, $results, 1)){
                    break;
                }
            }
            $passed_cnt++;
        }
        $res->close();
        
        if($this->isVerbose){
            echo '<p>URL is text fields. Processed: '.$passed_cnt.' records</p>';
            
            $broken_cnt = count($results[1]);

            if($broken_cnt>0){

                print '<div style="padding-top:20px;color:red">There are <b>'.$broken_cnt
                .'</b> text fields with broken url.</div>';
            }else{
                print '<div><h3 class="res-valid">OK: All text fields have valid URL</h3></div>';
            }
        }
    }

    /**
     * Check external file URLs in use.
     *
     * @param array $results
     * @return void
     */
    private function checkExternalFileURLs(&$results) {
        $query = 'SELECT dtl_RecID, ulf_ExternalFileReference, dtl_DetailTypeID FROM recDetails ' .
            'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID ' .
            'INNER JOIN recUploadedFiles ON ulf_ID = dtl_UploadedFileID ' .
            'WHERE dty_Type = "file" AND ulf_ExternalFileReference != ""';

        $res = $this->mysqli->query($query);
        
        $this->printHeader('<h4>External URLs (File fields)</h4>');

        if (!$res) {
            if($this->isVerbose){
                print error_Div('Cant retrieve records with URLs in text fields: '.$this->mysqli->error);
            }
            return;
        }
        
        $passed_cnt = 0;
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $recUrl = $row[1];
            $detailTypeId = $row[2];

        if($this->validateAndHandleFieldUrl($recId, $recUrl, $detailTypeId, $results, 2)){
                break;
            }
            $passed_cnt++;
        }
        $res->close();
        
        
        if($this->isVerbose){
            echo '<p>URL is file fields. Processed: '.$passed_cnt.' records</p>';
            
            $broken_cnt = count($results[2]);

            if($broken_cnt>0){

                print '<div style="padding-top:20px;color:red">There are <b>'.$broken_cnt
                .'</b> records with broken url in file fields</div>';
            }else{
                print '<div><h3 class="res-valid">OK: All records have valid file fields URL</h3></div>';
            }
        }
    }

    /**
     * Loads the content from a remote URL.
     *
     * @param string $url
     * @return mixed The content or false if an error occurs.
     */
    private function loadRemoteURLContent($url) {
        return loadRemoteURLContentWithRange($url, CURL_RANGE, true, 5);
    }

    /**
     * Handles the case when a record URL is broken.
     *
     * @param int $recId
     * @param string $recUrl
     * @param array $results
     * @return true in case of fatal CURL error 
     */
    private function handleBrokenRecordUrl($recId, $recUrl, &$results) {
        global $glb_curl_code, $glb_curl_error;

        if ($glb_curl_code == HEURIST_SYSTEM_FATAL) {
            if ($this->isVerbose) {
                echo error_Div( $glb_curl_error );
            }
            $results[3] = $glb_curl_error;
            return true;
        }

        $brokenUrlMessage = $glb_curl_error ?? 'Unknown error';
        $query = 'UPDATE Records SET rec_URLLastVerified=?, rec_URLErrorMessage=? WHERE rec_ID='.intval($recId);
        mysql__exec_param_query($this->mysqli, $query, ['ss', date(DATE_8601), substr($brokenUrlMessage, 0, 255)], true);

        if ($this->isVerbose) {
            $recUrl = htmlspecialchars($recUrl);
            echo '<div>' . intval($recId) . ' : <a href="' . $recUrl . '" target="_blank" rel="noopener">' . $recUrl . '</a>&nbsp;' . $brokenUrlMessage . '</div>';
        }

        $results[0][$recId] = $recUrl;
        
        return false;
    }

    /**
     * Validates and handles URLs found in free text or block text fields or in file fields.
     *
     * @param int $recId
     * @param string $url
     * @param int $detailTypeId
     * @param array $results
     * @param int $idx_in_results  0 - no ouput, 1 for text fields, 2 - for file fields
     * @return true in case of fatal CURL error 
     */
    private function validateAndHandleFieldUrl($recId, $url, $detailTypeId, &$results, $idx_in_results) {
        global $glb_curl_error, $glb_curl_code;

        if ($this->listOnly) {
            $url = htmlentities($url);
            echo intval($recId) . ' : ' . intval($detailTypeId) . ' : <a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a><br>';
            return false;
        }

        if (strpos(strtolower($url), $this->heuristServerUrl) === 0) {
            return false; // Skip URLs from the same server
        }

        $data = $this->loadRemoteURLContent($url);

        if ($glb_curl_code == HEURIST_SYSTEM_FATAL) {
            if ($this->isVerbose) {
                echo error_Div( $glb_curl_error );
            }
            $results[3] = $glb_curl_error;
            return true;
        }
        
        
        if (!$data) {

            if ($idx_in_results>0) {
                $results[$idx_in_results][$recId][$detailTypeId][] = $url;
            }
            
            if($this->isVerbose){
                $brokenUrlMessage = $glb_curl_error ?? 'Unknown error';
                $urlMessage = '<div><a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a>&nbsp;' . htmlspecialchars($brokenUrlMessage) . '</div>';
                echo $urlMessage;
            }
            
        }
        
        return false;
    }
}
?>
