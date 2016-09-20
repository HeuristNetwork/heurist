<?php

    /**
    * elasticSearchFunctions.php: Functions to index and search for records using Elastic Search
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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


    /*
    Elastic Search creates indexes automatically and updates the index record when new data is supplied for an existing key
    However we do need to delete keys explicitely if 1. the record type changes or 2. we delete the record altogether
    Test for installation allows us to fall back to ordinary searching or render warning that feature is not supported

    ACTIONS TODO:

    Call updateRecordIndexEntry whenever a record is written (new or updated, from record edit or record import or record recode)

    Call buildAllIndices as part of database upgrade 1.1.0 to 1.2.0

    Call deleteRecordIndexEntry whenever a record is saved with a different type (editRecord)

    Call deleteRecordIndexEntry whenever a record is deleted (Search Actions)

    */

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    $elasticSearch = false;

    // ****************************************************************************************************************
    /**
    * Test whether Elastic Search is installed/operational
    * @returns  Returns CURL result code, 0 if OK, >0 indicates error
    */
    function testElasticSearchOK () {
        global $elasticSearch;
        if($elasticSearch) {
            global $indexServerAddress, $indexServerPort;
            $url="$indexServerAddress:$indexServerPort"; // Set in configIni.php, address can be blank (not set), default port is 9200
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_GET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $error = curl_error($ch);
            if ($error) {
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                curl_close($ch);
                return($code);
            }
        }
        return(0);
    } //testElasticSearchOK



    // ****************************************************************************************************************
    /**
    * Remove uppercase letters from database name (Elastic Search rejects uppercase)
    * @param    $dbname - the database name to be converted to lower case
    * @returns  Returns corrected database name
    */
    function sanitiseForES ($dbname) {
        $dbname_new=strtolower($dbname);
        if ($dbname == $dbname_new) {
            return($dbname); // no change
        } else {
            $dbname_new = $dbname_new."_nocaps"; // disambiguate eg. MyDB and mydb
            return($dbname_new);
        }
    } //sanitiseForES



    // ****************************************************************************************************************
    /**
    * Add a new key or update an existing key - Elastic Search adds or updates as appropriate, no need to specify
    * By reading record from database we ensure that we are indexing only records which have been successfully written
    * @param $dbName        The name of the Heurist database, excluding prefix
    * @param $recTypeID     The record type ID of the record being indexed
    * @return               curl return code, 0 = success
    */
    function updateRecordIndexEntry ($dbName, $recTypeID, $recID) {
        global $elasticSearch;
        if($elasticSearch) {
            global $indexServerAddress, $indexServerPort;

            $jsonData = "{";

            // Add the record level data:
            // DO NOT CHANGE ORDER unless changing JSON construction below
            $query="SELECT rec_URL,rec_Added,rec_Modified,rec_Title,rec_RecTypeID,rec_AddedByUGrpID,rec_AddedByImport,rec_Popularity,".
            "rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URLLastVerified,rec_URLErrorMessage,rec_URLExtensionForMimeType ".
            "from Records where rec_ID=$recID"; // omits scratchpad
            $res = mysql_query($query);

            if (($res) && ($row[3] != $recTypeID)) {// record type has changed

                // TODO: Delete index for old record type before updating index for new record type
                //print "<br />TODO: Delete index for old record type before updating index for new record type<br />";
            } // change of record type / delete existing index entry

            if ($res) { // add record level data to json
                $row = mysql_fetch_array($res); // fetch Record data
                $jsonData .= '"URL":"'          .$row[0].'"';
                $jsonData .= ',"Added":"'       .$row[1].'"';
                $jsonData .= ',"Modified":"'    .$row[2].'"';
                $jsonData .= ',"Title":"'       .$row[3].'"';
                $jsonData .= ',"RecTypeID":"'   .$row[4].'"';
                $jsonData .= ',"AddedBy":"'     .$row[5].'"';
                $jsonData .= ',"Imported":"'    .$row[6].'"';
                $jsonData .= ',"Popularity":"'  .$row[7].'"';
                $jsonData .= ',"Temporary":"'   .$row[8].'"';
                $jsonData .= ',"OwnerUGrpID":"' .$row[9].'"';
                $jsonData .= ',"NonOwnerVis":"' .$row[10].'"';
                $jsonData .= ',"URLLastVerif":"'.$row[11].'"';
                $jsonData .= ',"URLErrMsg":"'   .$row[12].'"';
                $jsonData .= ',"URLExtMimeType":"'.$row[13].'"';
            } else {
                // TODO: Should really check and warn and exit if bad query
                // Also exit if record marked as temporary
                //print "<br />Query $query has failed";
            }

            // Add the detail level data
            $queryDtl="SELECT dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,dtl_Geo from recDetails where dtl_RecID=$recID";
            $res = mysql_query($queryDtl);
            if ($res) {
                while (($row = mysql_fetch_array($res))) { // fetch detail data
                    $jsonData .= ',"' .$row[0]. '":"'.$row[1].$row[2].$row[3].'"';
                    // numeric detail ID is used as the key for the index, so no namespace conflict
                    // with textual keys from the Record itself
                    // TODO: should use dtl_Value OR dtl_UploadedFileID OT dtl_Geo according to detail type
                    // Curent code makes the simplistic assumption that only one of these three
                    // fields is set or the cioncat is useful. TODO: Verify if this is the case
                }
            }

            // Terminate the json data
            $jsonData .= '}';
            $dbnameLoc=sanitiseForES($dbName); // remove any capitalisation and append _nocaps if this is done to distinguish DB from db

            // PUT request to Elasticsearch
            $url = "$indexServerAddress:$indexServerPort/$dbnameLoc/$recTypeID/$recID";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

            $data = curl_exec($ch);
            $error = curl_error($ch);
            if ($error) {
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                //print "<br />ERROR: updateRecordIndexEntry indexing: $error ($code) & url = $url & data = $jsonData";
                curl_close($ch);
                return $code;
            } else {
                //print "<br />SUCCESS: updateRecordIndexEntry indexed: $url with $jsonData";
                curl_close(ch); // is this necessary?
            }
        }
        return(0);
    } // addUpdateRecordIndex



    // ****************************************************************************************************************
    // Note: Reported bug in PHP @ 18/11/13: must reset to NULL to obtain internal default.
    //       Resetting directly to eg. PUT or GET will not reset, it will remain set as DELETE
    // ****************************************************************************************************************


    // ****************************************************************************************************************
    /**
    * Delete the index entry for a specified record - use when record type changed or record deleted
    * @param $dbName        The name of the Heurist databasem, excluding prefix
    * @param $recTypeID     The record type ID of the record being deleted from the index
    * @param $recID         The record to be deleted from the index
    * @return               curl return code, 0 = success
    */
    function deleteRecordIndexEntry ($dbName, $recTypeID, $recID ) {
        global $elasticSearch;
        if($elasticSearch) {
            global $indexServerAddress, $indexServerPort;

            $url="$indexServerAddress:$indexServerPort/$dbname/$recTypeID/$recID";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $error = curl_error($ch);
            if ($error) {
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                //print "<br />ERROR: deleteRecordIndexEntry: $error ($code)" . " url = ". $url;
                curl_close($ch);
                return $code;
            } else {
                curl_close(ch); // is this necessary?
            }
        }
        return(0);
    } // deleteRecordIndex


    // ****************************************************************************************************************
    /**
    * Delete the index for a specified record type
    * @param $dbName       The name of the Heurist databasem, excluding prefix
    *  @param $recTypeID    The record type ID of the record being deleted from the index
    */
    function deleteIndexForRectype ($dbName, $recTypeID) {
        global $elasticSearch;
        if($elasticSearch) {
            // TODO: check that this is correct spec for deletion of the index for a record type
            global $indexServerAddress, $indexServerPort;

            $url="$indexServerAddress:$indexServerPort/$dbname/$recTypeID";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $error = curl_error($ch);
            if ($error) {
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                //print "<br />ERROR: deleteIndexForRectype: $error ($code)" . " url = ". $url;
                curl_close($ch);
                return $code;
            } else {
                curl_close(ch); // is this necessary?
            }
        }
        return(0);
    } // deleteIndexForRectype


    // ****************************************************************************************************************
    /**
    * Delete the index for a specified database
    * @param $dbName       The name of the Heurist databasem, excluding prefix
    */
    function deleteIndexForDatabase ($dbName) {
        global $elasticSearch;
        if($elasticSearch) {
            // TODO: check that this is correct spec for deletion of the index for a database
            global $indexServerAddress, $indexServerPort;

            $url="$indexServerAddress:$indexServerPort/$dbName";
            print "Deleting all Elasticsearch indices for $dbName at $url<br />";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $error = curl_error($ch);
            if ($error) {
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                //print "<br />ERROR: deleteIndexForDatabase: $error ($code)" . " url = ". $url;
                curl_close($ch);
                return($code);
            } else {
                curl_close(ch); // is this necessary?
            }
        }
        return(0);
    } // deleteIndexForDatabase


    // ****************************************************************************************************************
    /**
    * Rebuild the index for a specified record type
    * @param $dbName       The name of the Heurist databasem, excluding prefix
    * @param
    * @returns 0 = OK, any other = error
    */
    function buildIndexForRectype ($dbName, $recTypeID) {
        global $elasticSearch;
        if($elasticSearch) {
            //print "buildIndexForRectype: indexing record type $recTypeID for $dbName<br />";
            deleteIndexForRectype ($dbName, $recTypeID); // clear the existing index

            $query="Select rec_ID from Records where rec_RecTypeID = $recTypeID";
            $res = mysql_query($query);
            if ($res) {
                while (($row = mysql_fetch_array($res))) { // fetch records
                    $code = updateRecordIndexEntry ($dbName, $recTypeID, $row[0]/*recID*/);
                    if($code != 0) {
                        //print "<br />ERROR while updating record index; code = $code, dbName = $dbname, rectypeID = $recTypeID, row = " + $row[0] + "<br />";
                        return($code); // curl error code
                    }
                }
                return(0);
            }
        }
        return(-1);
    } // buildIndexForRectype


    // ****************************************************************************************************************
    /**
    * Rebuild the index for all record types
    * @param    $dbName  The name of the Heurist databasem, excluding prefix
    * @returns  0 = OK, 1 = error
    */
    function buildAllIndices ($dbName) {
        global $elasticSearch;
        if($elasticSearch) {
            print "Building all Elasticsearch indices for: $dbName<br />";

            $query="Select MAX(rec_RecTypeID) from Records where 1";
            $res = mysql_query($query);
            if ($res) {
                $row = mysql_fetch_array($res);
                $maxRecTypeID = $row[0];
                //print "<br />MaxRecTypeID = $maxRecTypeID<br />";
                for ($i = 1; $i <= $maxRecTypeID; $i++) { // call index function for each record type
                    $code = buildIndexForRectype ($dbName, $i);
                    if($code != 0) {
                        //print "<br />ERROR while building index for rectype; code = $code, dbName = $dbName, i = $i<br />";
                        return($code);
                    }
                }
                return(0);
            }
        }
        return(-1);
    } // buildAllIndices

?>
