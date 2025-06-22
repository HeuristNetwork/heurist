<?php
/**
* elasticHelper.php - functions library for ElasticSearch class
* 
* Helper functions to interact with ElasticSearch index server.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\records]indexing
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com> 
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    //****************************************************************************************************************
    // ElasticSearch index helpers
    //****************************************************************************************************************

    /**
     * @var bool|null Global flag to cache the status of Elasticsearch (enabled and running).
     *                Null initially, then set to true/false by the first call to `isElasticUp()`.
     */
    $isElasticUp = null; // Global variable whether or not Elastic indexing is enabled & operational

    /**
     * Checks if Elasticsearch indexing is enabled via global configuration.
     *
     * It looks for global variables `$indexServerAddress` and `$indexServerPort`,
     * which are expected to be defined in `configIni.php`.
     *
     * @return bool True if both Elasticsearch server address and port are configured, false otherwise.
     */
    function isElasticEnabled() {
        global $indexServerAddress, $indexServerPort; // Set in configIni.php
        return !empty($indexServerAddress) && !empty($indexServerPort);
    }

    /**
     * Generates a clean Elasticsearch index name from a Heurist database name.
     *
     * The Heurist database name (after potentially removing a prefix via `mysql__get_names`)
     * is converted to lowercase, and any non-alphanumeric characters are replaced with underscores.
     *
     * @param string $dbName The original Heurist database name.
     * @return string The derived Elasticsearch index name.
     */
    function getElasticIndex($dbName) {

        list($database_name_full, $database_name) = mysql__get_names( $dbName );

        $elasticIndex = strtolower($database_name);// Must be lowercase
        preg_replace('/[^A-Za-z0-9 ]/', '_', $elasticIndex);// Replace non-alphanumeric with underscore
        return $elasticIndex;
    }

    /**
     * Constructs a URL for interacting with the Elasticsearch server.
     *
     * Builds the URL based on global configuration for server address and port,
     * and optionally appends paths for a specific database index, record type (as type in ES),
     * and record ID (as document ID in ES).
     *
     * @param string|null $dbName (Optional) The Heurist database name. If provided, `getElasticIndex()` is used to form the index path.
     * @param int|string|null $recTypeID (Optional) The Heurist record type ID, used as the Elasticsearch type.
     * @param int|string|null $recID (Optional) The Heurist record ID, used as the Elasticsearch document ID.
     * @return string The fully constructed Elasticsearch URL.
     */
    function getElasticAddress($dbName=null, $recTypeID=null, $recID=null) {
        global $indexServerAddress, $indexServerPort; // Set in configIni.php
        $url = $indexServerAddress .':'. $indexServerPort;

        // dbName check
        if(!empty($dbName)) {
            $url .= '/' . getElasticIndex($dbName);
        }

        // recTypeID check
        if(!empty($recTypeID)) {
            $url .= '/' . $recTypeID;
        }

        // recID check
        if(!empty($recID)) {
            $url .= '/' . $recID;
        }

        return $url;
    }

    /**
     * Checks if a given Elasticsearch URL endpoint exists and is responsive (returns HTTP 200).
     *
     * Uses cURL to make a request to the specified address.
     *
     * @param string $address The full Elasticsearch URL to check.
     * @return bool True if the HTTP response code is 200, false otherwise.
     */
    function isElasticAddressCreated($address) {
        $handle = curl_init($address);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return $httpCode == 200;
    }

    /**
     * Checks if an Elasticsearch index for the specified Heurist database likely exists.
     *
     * It does this by constructing the index URL and checking if that address is created.
     *
     * @param string $dbName The Heurist database name.
     * @return bool True if the index address returns HTTP 200, false otherwise.
     */
    function isElasticIndexCreated($dbName) {
        $address = getElasticAddress($dbName);
        return isElasticAddressCreated($address);
    }

    /**
     * Checks if the base Elasticsearch server is running and responsive.
     *
     * It calls `isElasticAddressCreated` with just the base server address.
     *
     * @return bool True if the Elasticsearch server root responds with HTTP 200, false otherwise.
     */
    function isElasticRunning() {
        $address = getElasticAddress();
        return isElasticAddressCreated($address);
    }

    /**
     * Checks if Elasticsearch is configured (enabled) and currently running.
     *
     * This function uses a global variable `$isElasticUp` to cache the result
     * to avoid repeated checks within the same request lifecycle.
     *
     * @return bool True if Elasticsearch is enabled in `configIni.php` AND the server is responsive.
     */
    function isElasticUp() {
        global $isElasticUp;
        if($isElasticUp == null) {
            $isElasticUp = isElasticEnabled() && isElasticRunning();
        }
        return $isElasticUp;
    }


    //****************************************************************************************************************
    // ElasticSearch query helpers
    //****************************************************************************************************************

    /**
     * Sends a GET request to Elasticsearch.
     *
     * @param string $address The full Elasticsearch URL.
     * @param stdClass|array $query The query payload (typically an object or array to be JSON encoded).
     * @return string|false The JSON response from Elasticsearch, or false on cURL error.
     */
    function getElastic($address, $query) {
        $curl = curl_init();
        return queryElastic($curl, $address, $query);
    }

    /**
     * Sends a POST request to Elasticsearch.
     *
     * @param string $address The full Elasticsearch URL.
     * @param stdClass|array $query The query payload.
     * @return string|false The JSON response from Elasticsearch, or false on cURL error.
     */
    function postElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        return queryElastic($curl, $address, $query);
    }

    /**
     * Sends a PUT request to Elasticsearch.
     *
     * @param string $address The full Elasticsearch URL.
     * @param stdClass|array $query The query payload.
     * @return string|false The JSON response from Elasticsearch, or false on cURL error.
     */
    function putElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        return queryElastic($curl, $address, $query);
    }

    /**
     * Sends an update_by_query request (via POST) to Elasticsearch.
     *
     * @param string $address The base Elasticsearch URL (index/type). `/_update_by_query` is appended.
     * @param stdClass|array $query The query payload for the update_by_query operation.
     * @return string|false The JSON response from Elasticsearch, or false on cURL error.
     */
    function updateElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        return queryElastic($curl, $address.'/_update_by_query', $query);
    }

    /**
     * Sends a DELETE request to Elasticsearch.
     *
     * @param string $address The full Elasticsearch URL.
     * @param stdClass|array $query The query payload (can be empty for simple deletes by ID).
     * @return string|false The JSON response from Elasticsearch, or false on cURL error.
     */
    function deleteElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return queryElastic($curl, $address, $query);
    }

    /**
     * Executes a cURL request to Elasticsearch.
     *
     * This is the core function used by `getElastic`, `postElastic`, etc.
     * It sets common cURL options (RETURNTRANSFER, HTTPHEADER for JSON, URL, POSTFIELDS, TIMEOUTS)
     * and executes the request. The HTTP method (GET, POST, PUT, DELETE) should be set
     * on the `$curl` handle before calling this function if it's not a GET.
     *
     * @param resource $curl An initialized cURL handle.
     * @param string $address The full Elasticsearch URL to send the request to.
     * @param stdClass|array $query The query payload to be JSON encoded and sent.
     * @return string|false The JSON response string from Elasticsearch, or `false` if `curl_exec` fails.
     */
    function queryElastic($curl, $address, $query) {
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(CTYPE_JSON));
        curl_setopt($curl, CURLOPT_URL, $address);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);// Max 10 seconds connection time
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);// Max 60 seconds query time

        $json = curl_exec($curl);
        curl_close($curl);
        //error_log("[elasticSearchHelper.php] Query $query --> resulted in $json");

        return $json;
    }

    /**
     * Checks if a JSON response string from Elasticsearch indicates success for certain operations.
     *
     * It decodes the JSON string and checks if the specified `$property` exists at the top
     * level of the response object and if its value is boolean `true`. This is often used
     * for checking properties like "acknowledged" or "created" in Elasticsearch responses.
     *
     * @param string|false $json The JSON response string from Elasticsearch, or `false` if the request failed.
     * @param string $property The name of the boolean property to check in the JSON response.
     * @return bool True if the JSON is valid, the property exists, and its value is true. False otherwise.
     */
    function checkElasticResponse($json, $property) {
        if ($json != null) {
            $response = json_decode($json);
            return property_exists($response, $property) && $response->$property;
        }
        return false;
    }


    //****************************************************************************************************************
    // ElasticSearch synchronisation helpers
    //****************************************************************************************************************

    /**
     * Creates a new Elasticsearch index for a Heurist database with specific dynamic mapping settings.
     *
     * This function is intended to be called when a new Heurist database is created (e.g., from `createNewDB.php`).
     * It defines an Elasticsearch index with:
     * - Default settings for shards (1) and replicas (1).
     * - A dynamic template named "raw_template". This template matches all fields ("*") of all types ("*")
     *   and configures them as "string" type (note: Elasticsearch has evolved its type system, 'text' or 'keyword'
     *   might be more appropriate now for strings, depending on ES version).
     *   Crucially, it adds a multi-field named "raw" to each field, which is of type "string"
     *   and set to "not_analyzed". This `.raw` field stores the original, non-tokenized string,
     *   making it suitable for exact matches, sorting, and aggregations.
     *
     * If the index creation fails, an error is logged.
     *
     * @param string $database The name of the Heurist database for which to create the Elasticsearch index.
     *                         The actual index name will be derived by `getElasticAddress()`.
     */
    function createElasticIndex($database) {
        if(isElasticUp()) {
            $query = '{
                        "settings": {
                            "number_of_replicas": 1,
                            "number_of_shards": 1
                        },
                        "mappings": {
                            "_default_": {
                                "dynamic_templates": [
                                    {
                                        "raw_template": {
                                            "match": "*",
                                            "match_mapping_type": "*",
                                            "mapping": {
                                                "type": "string",
                                                "fields": {
                                                    "raw": {
                                                        "type":  "string",
                                                        "index": "not_analyzed"
                                                    }
                                                }
                                            }
                                        }
                                    }
                                ]
                            }
                        }
                     }';

            $address = getElasticAddress($database);
            $json = putElastic($address, json_decode($query));

            if(!checkElasticResponse($json, 'acknowledged')) {
                error_log("[elasticSearchHelper.php] Failed to create dynamic template for index $database : $json");
            }
        }
    }
?>