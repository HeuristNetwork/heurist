<?php

    /**
     * elasticHelper.php: Functions to help interacting with ElasticSearch
     *
     * @package     Heurist academic knowledge management system
     * @link        http://HeuristNetwork.org
     * @copyright   (C) 2005-2016 University of Sydney
     * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
     * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
     * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
     * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
     * @version     4.0
     */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    //****************************************************************************************************************
    // ElasticSearch index helpers
    //****************************************************************************************************************

    // Enable or disable ElasticSearch indexing
    function isElasticEnabled() {
        error_log("[elasticSearchHelper.php] isElasticEnabled");
        return true;
    }

    /**
     * Determines the ElasticSearch index name for a database
     * @param $dbName Name of the database
     * @return Name of the ElasticSearch index
     */
    function getElasticIndex($dbName) {
        $elasticIndex = strtolower($dbName); // Must be lowercase
        preg_replace("/[^A-Za-z0-9 ]/", '_', $elasticIndex); // Replace non-alphanumeric with underscore
        return $elasticIndex;
    }

    /**
     * Determines the ElasticSearch address based on the given parameters
     * @param $dbName Name of the database
     * @param $recTypeID Record Type ID
     * @param $recID Record ID
     * @return The ElasticSearch address for the given parameters
     */
    function getElasticAddress($dbName=null, $recTypeID=null, $recID=null) {
        global $indexServerAddress, $indexServerPort;
        $url = $indexServerAddress .':'. $indexServerPort; // Set in configIni.php

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
     * Test whether ElasticSearch is installed/operational
     * @returns  Returns true if ElasticSearch is operational
     */
    function testElasticOK() {
        error_log("[elasticSearchHelper.php] testElasticSearchOK");

        if(isElasticEnabled()) {
            $address = getElasticAddress();
            $query = new stdClass();
            $json = getElastic($address, $query);

            // Check if the response contains ElasticSearch its version number
            if(!empty($json)) {
                $object = json_decode($json);
                return !empty($object->version);
            }
        }

        return false;
    }


    //****************************************************************************************************************
    // ElasticSearch query helpers
    //****************************************************************************************************************

    // GET-request to adress with query
    function getElastic($address, $query) {
        $curl = curl_init();
        return queryElastic($curl, $address, $query);
    }

    // PUT-request to adress with query
    function putElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        return queryElastic($curl, $address, $query);
    }

    // UPDATE-request to adress with query
    function updateElastic($address, $query) {
        $curl = curl_init();
        $address .= '/_update';
        return queryElastic($curl, $address, $query);
    }

    // DELETE-request to adress with query
    function deleteElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return queryElastic($curl, $address, $query);
    }

    /**
     * @param $curl Curl instance reference
     * @param $address ElasticSearch instance address
     * @param $query The query to send to the ElasticSearch instance
     * @return ElasticSearch response (JSON)
     */
    function queryElastic($curl, $address, $query) {
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_URL, $address);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10); // Max 10 seconds connection time
        curl_setopt($curl, CURLOPT_TIMEOUT, 60); // Max 60 seconds query time

        $json = curl_exec($curl);
        curl_close($curl);

        return $json;
    }

?>