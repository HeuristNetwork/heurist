<?php

    /**
    * elasticSearch.php: Functions to interact with ElasticSearch
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    /*
    A hook has been added in createNewDB.php which calls createElasticIndex($newDBName).
    The method createElasticIndex checks if Elastic has been enabled, and if it is running.
    If that is the case, it creates the index for the given database.

    The index that is created has a dynamic_mapping, which adds an additional .raw field for every string field.
    The raw field is not_analyzed which means it is not processed by Elastic.
    By default, strings are processed and cut into pieces for fuzzy searching.
    - Fuzzy searching can be done against the normal field
    - Exact matching or aggregations should be done against the raw field.

    Additional hooks have been added to methods that add or modify the underlaying MySQL database.
    With the help of these hooks the MySQL database and ElasticSearch index should always be synchronized.

    The methods getHighestMySqlTimestamp() and getHighestElasticTimestamp() are used to
    determine if the timestamps used in each system are equal. This check is done during
    each of the functions below, to make sure the systems are synchronized at all times.


    PUBLIC METHODS:

    updateRecordIndexEntry() -> whenever a record is written (new or updated, from record edit or record import or record recode)
    - db_record.php

    deleteRecordIndexEntry() -> whenever a record is saved with a different type (editRecord)
    - db_record.php

    deleteIndexForDatabase -> whenever a database is deleted (admin actions)
    - clearCurrentDB.php
    - deleteCurrentDB.php

    buildAllIndices() -> whenever a database upgrade has occurred or when a database has been cloned
    - uses deleteIndexForRectype()
    - uses buildAllIndices()
    - cloneDB.php
    - rebuildLuceneIndices.php

    */
require_once 'elasticSearchHelper.php';

/**
 * Class ElasticSearch
 *
 * Provides a static interface for interacting with an Elasticsearch server to index,
 * update, delete, and manage Heurist record data. It aims to keep Elasticsearch
 * synchronized with the MySQL database.
 *
 * This class relies on helper functions in `elasticSearchHelper.php` for actual
 * HTTP communication with the Elasticsearch server (e.g., `isElasticUp`, `putElastic`,
 * `deleteElastic`, `postElastic`, `getElasticAddress`, `checkElasticResponse`).
 *
 * The indexing process involves creating an Elasticsearch index per Heurist database,
 * with types within that index corresponding to Heurist record types. Record data,
 * including both header fields and detail fields, is indexed.
 *
 * Note: The class includes comments indicating it was developed around 2012 by Jan Jaap de Groot
 * and might not have been fully integrated or kept up-to-date with later Heurist versions
 * or Elasticsearch best practices due to security concerns at the time regarding
 * running Elasticsearch on the server.
 *
 * @package hserv\records\indexing
 */
class ElasticSearch {

    /**
     * Private constructor to prevent instantiation, as this class provides only static methods.
     */
    private function __construct() {}

    /**
     * @var \mysqli|null The mysqli database connection object, initialized from the global $system object.
     */
    private static $mysqli = null;

    /**
     * @var bool Flag indicating if the class (and its $mysqli property) has been initialized.
     */
    private static $initialized = false;


    /**
     * Initializes the static class properties, primarily setting up the mysqli connection.
     *
     * This method fetches the global `$system` object to get the mysqli connection.
     * It ensures initialization happens only once.
     */
    private static function initialize()
    {
        if (self::$initialized)  {return;}

        global $system;
        self::$mysqli = $system->getMysqli();
        self::$initialized = true;
    }

    // ****************************************************************************************************************
    /**
     * Adds or updates a record's entry in the Elasticsearch index.
     *
     * Retrieves the specified record's header and detail data from the MySQL database
     * and then sends it to Elasticsearch for indexing. If the record's record type ID
     * has changed, it first deletes the old entry from the index.
     * Elasticsearch handles whether it's an add or update operation internally.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix).
     * @param int $recTypeID The record type ID of the record. This is the type under which
     *                       the record *should* be indexed. If the record's actual type in DB
     *                       is different, the old entry is removed.
     * @param int $recID The ID of the record to update in the index.
     * @return bool True if the operation was successful (Elasticsearch acknowledged 'created' or 'updated'),
     *              false otherwise (e.g., Elasticsearch not up, DB query failed, ES error).
     */
    public static function updateRecordIndexEntry ($dbName, $recTypeID, $recID) {

        if(isElasticUp()) {

            self::initialize();

            $record = new stdClass();

            // Retrieve record level data
            $query = "SELECT rec_URL,rec_Added,rec_Modified,rec_Title,rec_RecTypeID,rec_AddedByUGrpID,rec_AddedByImport,rec_Popularity,".
            "rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URLLastVerified,rec_URLErrorMessage,rec_URLExtensionForMimeType ".
            "from Records where rec_ID=".intval($recID);// omits scratchpad
            $res = self::$mysqli->query($query);

            // Check if query has succeed
            if ($res) {
                $row = $res->fetch_row();// Fetch record data

                // Construct record
                $record->URL            = $row[0];
                $record->Added          = $row[1];
                $record->Modified       = $row[2];
                $record->Title          = $row[3];
                $record->RecTypeID      = $row[4];
                $record->AddedBy        = $row[5];
                $record->Imported       = $row[6];
                $record->Popularity     = $row[7];
                $record->Temporary      = $row[8];
                $record->OwnerUGrpID    = $row[9];
                $record->NonOwnerVis    = $row[10];
                $record->URLLastVerif   = $row[11];
                $record->URLErrMsg      = $row[12];
                $record->URLExtMimeType = $row[13];

                // Check if recTypeID has changed
                if($record->RecTypeID != $recTypeID) {
                    // Delete index for old record type before updating index for new record type
                    self::deleteRecordIndexEntry($dbName, $recTypeID, $recID);
                }
                $res->close();
            } else {
                error_log("[elasticSearch.php] updateRecordIndexEntry --> record query failed: $query");
                return false;
            }

            // Retrieve detail level data
            $query = "SELECT dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,dtl_Geo from recDetails where dtl_RecID=".intval($recID);
            $res = self::$mysqli->query($query);

            // Check if query has succeeded
            if ($res) {
                // Append detail level data to record
                while ($row = $res->fetch_row()) {
                    // Detail ID is used as key, together with dtl_Value, dtl_UploadedFileID and dtl_Geo
                    // TODO: should use dtl_Value OR dtl_UploadedFileID OT dtl_Geo according to detail type
                    $record->$row[0] = $row[1].$row[2].$row[3];
                }
                $res->close();
            }else{
                error_log("[elasticSearch.php] updateRecordIndexEntry --> details query failed: $query");
                return false;
            }

            // PUT data to ElasticSearch
            $address = getElasticAddress($dbName, $recTypeID, $recID);
            $json = putElastic($address, $record);

            // Check if created
            if(checkElasticResponse($json, 'created')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // addUpdateRecordIndex



    // ****************************************************************************************************************
    // Note: Reported bug in PHP @ 18/11/13: must reset to null to obtain internal default.
    //       Resetting directly to eg. PUT or GET will not reset, it will remain set as DELETE
    // ****************************************************************************************************************


    // ****************************************************************************************************************
    /**
     * Deletes a specific record's entry from the Elasticsearch index.
     *
     * This is used when a record is deleted or its record type changes.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix).
     * @param int $recTypeID The record type ID under which the record is currently indexed.
     * @param int $recID The ID of the record to delete from the index.
     * @return bool True if Elasticsearch acknowledges the deletion, false otherwise.
     */
    public static function deleteRecordIndexEntry ($dbName, $recTypeID, $recID ) {
        if(isElasticUp()) {

            // Delete record from ElasticSearch
            $address = getElasticAddress($dbName, $recTypeID, $recID);
            $query = new stdClass();
            $json = deleteElastic($address, $query);

            // Check if acknowledged
            if(checkElasticResponse($json, 'acknowledged')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // deleteRecordIndex


    // ****************************************************************************************************************
    /**
     * Deletes all Elasticsearch index entries for a specific record type within a database.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix).
     * @param int $recTypeID The record type ID whose entries are to be deleted.
     * @return bool True if Elasticsearch acknowledges the deletion, false otherwise.
     */
    private static function deleteIndexForRectype ($dbName, $recTypeID) {
        if(isElasticUp()) {
            // Delete record from ElasticSearch
            $address = getElasticAddress($dbName, $recTypeID);
            $query = new stdClass();
            $json = deleteElastic($address, $query);

            // Check if acknowledged
            if(checkElasticResponse($json, 'acknowledged')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // deleteIndexForRectype


    // ****************************************************************************************************************
    /**
     * Deletes the entire Elasticsearch index for a specified Heurist database.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix) whose index will be deleted.
     * @return bool True if Elasticsearch acknowledges the deletion, false otherwise.
     */
    public static function deleteIndexForDatabase ($dbName) {
        if(isElasticUp()) {

            // Delete record from ElasticSearch
            $address = getElasticAddress($dbName);
            $query = new stdClass();
            $json = deleteElastic($address, $query);

            // Check if acknowledged
            if(checkElasticResponse($json, 'acknowledged')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // deleteIndexForDatabase


    // ****************************************************************************************************************
    /**
     * Rebuilds the Elasticsearch index for all records of a specific record type.
     *
     * This involves first deleting all existing index entries for that record type
     * using `deleteIndexForRectype`, and then iterating through all records of that
     * type in the MySQL database and re-indexing them using `updateRecordIndexEntry`.
     *
     * Note: This method is public but not static. It seems intended to be callable,
     * but `buildAllIndices` calls it statically, which would cause an error.
     * It should likely be `public static function`.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix).
     * @param int $recTypeID The record type ID for which to rebuild the index.
     * @return bool True if the process completes (i.e., all records of the type are iterated
     *              and `updateRecordIndexEntry` is called for each, regardless of individual
     *              successes/failures of those calls, as long as the DB query succeeds).
     *              Returns `false` if Elasticsearch is not up or the initial DB query fails.
     */
    public function buildIndexForRectype ($dbName, $recTypeID) {
        if(isElasticUp()) {

            self::initialize();

            self::deleteIndexForRectype ($dbName, $recTypeID);// clear the existing index

            $query = "SELECT rec_ID FROM Records WHERE rec_RecTypeID = $recTypeID";
            $res = self::$mysqli->query($query);

            if ($res) {
                while ($row = $res->fetch_row()) { // fetch records
                    // Update all records while successful
                    if(!self::updateRecordIndexEntry ($dbName, $recTypeID, $row[0]/*recID*/)) {
                        return false;
                    }
                }
                $res->close();
                return true;
            }else{
                error_log("[elasticSearch.php] buildIndexForRectype --> invalid query: $query");
            }
        }
        return false;
    } // buildIndexForRectype


    // ****************************************************************************************************************
    /**
     * Rebuilds all Elasticsearch indices for all record types in a specified database.
     *
     * It first determines the highest record type ID in the database. Then, it iterates
     * from record type ID 1 up to this maximum, calling `buildIndexForRectype` for each.
     * Optionally prints progress messages.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix).
     * @param bool $print If true, prints progress messages to output. Default true.
     * @return bool True if the process completes (iterates through all potential record type IDs),
     *              `false` if Elasticsearch is not up or the initial query for max recTypeID fails.
     *              Individual failures within `buildIndexForRectype` calls might not cause this to return `false`.
     */
    public static function buildAllIndices ($dbName, $print=true) {
        if(!isElasticUp()) {
            print "ElasticSearch service not detected";
            return false;
        }

            if ($print){
                print "Building all Elasticsearch indices for: $dbName<br>";
            }
            $query = "SELECT MAX(rec_RecTypeID) FROM Records WHERE 1";
            $res = self::$mysqli->query($query);
            $count = 0;

            if ($res) {
                $row = $res->fetch_row();
                $maxRecTypeID = $row[0];

                // Index all record types that exist
                for ($i = 1; $i <= $maxRecTypeID; $i++) {
                    if(self::buildIndexForRectype ($dbName, $i)) {
                        $count++;
                    }
                }

                if ($print){
                    print "ElasticSearch indices have successfully been built for $count record types.";
                }
                $res->close();
                return true;
            }


        error_log("[elasticSearch.php] buildAllIndices --> invalid query: $query");
        return false;
    } // buildAllIndices

    /**
     * Checks if Elasticsearch appears to be synchronized with the MySQL database by comparing timestamps.
     *
     * It retrieves the highest modification timestamp from both MySQL (`getHighestMySqlTimestamp`)
     * and Elasticsearch (`getHighestElasticTimestamp`). If these timestamps do not match,
     * it currently logs an error message (though the error_log call for non-matching timestamps is commented out).
     * This method is called after operations that modify the Elasticsearch index.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix) to check.
     */
    private static function checkElasticSync($dbName) {

        self::initialize();
        // 1. Retrieve highest MySQL timestamp
        $mysqlTimestamp = self::getHighestMySqlTimestamp();

        // 2. Retrieve highest Elastic timestamp
        $elasticTimestamp = self::getHighestElasticTimestamp($dbName);

        // 3. Compare timestamps
        if($mysqlTimestamp != null && $elasticTimestamp != null) {
            if(strcmp($mysqlTimestamp, $elasticTimestamp) !== 0) {
                // The timestamps are not equal. Note that ElasticSearch indexing takes ~100ms.
                //error_log("[elasticSearchHelper.php] mysqlTimestamp: $mysqlTimestamp & elasticTimestamp: $elasticTimestamp are not equal.");
            }
        }
    }

    /**
     * Retrieves the highest (most recent) `rec_Modified` timestamp from the `Records` table in MySQL.
     *
     * @return string|null The most recent modification timestamp as a string (e.g., "YYYY-MM-DD HH:MM:SS"),
     *                     or null if the query fails or no records exist.
     */
    private static function getHighestMySqlTimestamp() {

        $query = 'SELECT MAX(rec_Modified) FROM Records';
        $res = mysql__select_value(self::$mysqli, $query);

        if ($res) {
            return $res; // Gets the rec_Modified value from the first row.
        } else {
            error_log("[elasticSearchHelper.php] getHighestMySqlTimestamp failed - query: $query");
        }
        return null;
    }

    /**
     * Retrieves the highest 'Modified' timestamp from the Elasticsearch index for a given database.
     *
     * It performs a search in Elasticsearch for all record types (`_search`) within the specified
     * database's index, sorts by the `Modified.raw` field in descending order, and limits the result to 1.
     * It then extracts the `Modified` value from the `_source` of the top hit.
     *
     * @param string $dbName The name of the Heurist database (excluding prefix).
     * @return string|null The most recent 'Modified' timestamp string from Elasticsearch,
     *                     or null if the search fails, returns no hits, or the field is missing.
     */
    private static function getHighestElasticTimestamp($dbName) {
        $address = getElasticAddress($dbName) . '/_search?size=1';
        $query = '{
                    "query": {
                        "match_all": {}
                    },
                    "sort": {
                        "Modified.raw": {
                            "order" : "desc"
                        }
                    }
                  }';
        $json = postElastic($address, json_decode($query));

        if ($json != null) {
            $response = json_decode($json);
            return $response->hits->hits[0]->_source->Modified; // Gets the Modified value from the first hit.
        }else{
            error_log("[elasticSearchHelper.php] getHighestElasticTimestamp failed - query: $query");
        }

        return null;
    }

}
?>
