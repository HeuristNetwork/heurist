<?php
/**
* conceptCode.php - gets local code by concept code and vice versa
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @link        https://HeuristNetwork.org
* @version     4
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage Structure
*/
namespace hserv\structure;

/**
 * Class ConceptCode
 *
 * A static utility class for translating between local Heurist database IDs and
 * globally unique "Concept IDs" for various definition types (Terms, DetailTypes,
 * RecordTypes, Ontologies). A Concept ID is typically formatted as "OriginDBID-OriginalIDInThatDB".
 * This class is essential for ensuring interoperability and consistent referencing of
 * definitions when data or structures are exchanged between different Heurist databases.
 *
 * It provides methods to:
 *  - Generate a Concept ID from a local ID and its definition type.
 *  - Resolve a Concept ID back to a local ID in the context of the current database.
 *
 * The class operates statically and should not be instantiated.
 * It relies on database access to look up originating ID information stored with definitions.
 *
 * @package hserv\structure
 */
class ConceptCode {

    /**
     * Private constructor to prevent instantiation of this static utility class.
     */
    private function __construct() {}

    /** @var bool Flag to ensure the class is initialized only once per request or context. */
    private static $initialized = false;
    /** @var \hserv\System|null The Heurist system object, providing database access and settings. */
    private static $system = null;
    /** @var string|null The registered ID of the current Heurist database. Used for forming Concept IDs. */
    private static $database_id = null;

    /**
     * Initializes the static properties of the class, primarily the system object and database ID.
     *
     * This method is called internally by other methods to ensure that the necessary
     * context (like the current database's registered ID) is available. It can be
     * explicitly called with a system object (e.g., via `setSystem`) or will use
     * the global `$system` object if not otherwise initialized.
     *
     * @param \hserv\System|null $init_system (Optional) A specific Heurist system object to use.
     *                                        If null, and the class is not yet initialized,
     *                                        it will use the global `$system` object.
     */
    private static function initialize($init_system=null)
    {
        if($init_system!=null){
            self::$system = $init_system;
        }
        elseif (self::$initialized){
            return;
        }else{

            global $system;
            self::$system = $system;
        }

        self::$initialized = true;

        self::$database_id = self::$system->settings->get('sys_dbRegisteredID');
    }

    /**
     * Allows explicitly setting the Heurist system context for the class.
     *
     * This re-initializes the class with the provided system object, updating
     * the static `$system` and `$database_id` properties. This can be useful for
     * operations outside the main global system scope or for testing purposes.
     *
     * @param \hserv\System $new_system The Heurist system object to use for subsequent operations.
     */
    public static function setSystem($new_system){
        self::initialize($new_system);
    }


/**
* translate a local id for a given table to it's concept ID
* @param     string $lclID local id of row in $tableName table
* @param     string $tableName name of table
* @param     string $fieldNamePrefix column name prefix used in $tableName table
* @return    string The generated Concept ID (e.g., "DBID-OrigID", "CurrentDBID-LclID", or "0000-LclID"),
*                   or an empty string if `$lclID` is not positive.
* @uses      self::$database_id The registered ID of the current database.
*/
    /**
     * Generates a global Concept ID from a local ID for a given definition type.
     *
     * It queries the specified table (e.g., `defTerms`, `defRecTypes`) for the
     * `OriginatingDBID` and `IDInOriginatingDB` fields associated with the local ID (`$lclID`).
     * - If these fields are found and valid, the Concept ID is `OriginatingDBID-IDInOriginatingDB`.
     * - If not found, but the current database has a registered ID (`self::$database_id`),
     *   the Concept ID is `currentDBID-lclID`.
     * - Otherwise (e.g., unregistered current DB and no origin info in the record),
     *   it defaults to `0000-lclID` to indicate a locally defined concept in an unregistered context.
     *
     * @param int $lclID The local ID of the definition item (e.g., term ID, record type ID).
     * @param string $tableName The database table name for this definition type (e.g., "defTerms").
     * @param string $fieldNamePrefix The prefix for ID-related columns in that table (e.g., "trm_", "rty_").
     * @return string The Concept ID string, or an empty string if the local ID is not positive.
     */
private static function getConceptID($lclID, $tableName, $fieldNamePrefix) {

    self::initialize();

    if($lclID>0){

        $query = "select {$fieldNamePrefix}OriginatingDBID,{$fieldNamePrefix}IDInOriginatingDB from $tableName where {$fieldNamePrefix}ID = $lclID";

        $ids = mysql__select_row(self::$system->getMysqli(), $query);

        //return "".$ids[0]."-".$ids[1];
        if (is_array($ids) && count($ids) == 2 && is_numeric($ids[0]) && is_numeric($ids[1])) {
            return "" . $ids[0] . '-' . $ids[1];
        } elseif (self::$database_id) {
            return '' . self::$database_id . '-' . $lclID;
        } else {
            return '0000-'.$lclID;
        }

    }else{
        return '';
    }
}
/**
* return a terms concpet ID
* @param     int $lclTermID local Term ID
* @return    string The Concept ID string.
* @uses      self::getConceptID()
*/
    /**
     * Returns the Concept ID for a given local Term ID.
     * @param int $lclTermID Local Term ID.
     * @return string Concept ID (e.g., "DBID-OrigID").
     */
public static function getTermConceptID($lclTermID) {
    return self::getConceptID($lclTermID, "defTerms", "trm_");
}
/**
* return a detailTypes concpet ID
* @param     int $lclDtyID local detailType ID
* @return    string The Concept ID string.
* @uses      self::getConceptID()
*/
    /**
     * Returns the Concept ID for a given local DetailType ID.
     * @param int $lclDtyID Local DetailType ID.
     * @return string Concept ID (e.g., "DBID-OrigID").
     */
public static function getDetailTypeConceptID($lclDtyID) {
    return self::getConceptID($lclDtyID, "defDetailTypes", "dty_");
}
/**
* return a recTypes concpet ID
* @param     int $lclRecTypeID local recType ID
* @return    string The Concept ID string.
* @uses      self::getConceptID()
*/
    /**
     * Returns the Concept ID for a given local RecordType ID.
     * @param int $lclRecTypeID Local RecordType ID.
     * @return string Concept ID (e.g., "DBID-OrigID").
     */
public static function getRecTypeConceptID($lclRecTypeID) {
    return self::getConceptID($lclRecTypeID, "defRecTypes", "rty_");
}
/**
* return a ontologies concpet ID
* @param     int $lclOntID local ontology ID
* @return    string The Concept ID string.
* @uses      getConceptID()
*/
    /**
     * Returns the Concept ID for a given local Ontology ID.
     * @param int $lclOntID Local Ontology ID.
     * @return string Concept ID (e.g., "DBID-OrigID").
     */
public static function getOntologyConceptID($lclOntID) {
    return self::getConceptID($lclOntID, "defOntologies", "ont_");
}

//-------------------
    /**
     * Resolves a Concept ID to a local ID for a given definition type in the current database.
     *
     * A Concept ID is typically in the format "OriginDBID-OriginalIDInThatDB".
     * - If OriginDBID matches the current database's registered ID, or if OriginDBID is '0' or empty
     *   (indicating it's effectively local or from an unregistered source assumed to be local),
     *   it queries the specified table for a direct match on the local ID part.
     * - If OriginDBID is different and valid, it queries using the `OriginatingDBID` and
     *   `IDInOriginatingDB` columns in the specified table.
     *
     * @param string $conceptID The Concept ID string (e.g., "DBID-OrigID" or just "LocalID").
     * @param string $tableName The database table name for this definition type (e.g., "defTerms").
     * @param string $fieldNamePrefix The prefix for ID-related columns in that table (e.g., "trm_", "rty_").
     * @return int|null The local ID if found, otherwise null.
     * @uses self::$database_id The registered ID of the current database.
     */
private static function getLocalID($conceptID, $tableName, $fieldNamePrefix) {

    self::initialize();

    $ids = explode('-', $conceptID);
    $res_id = null;
    if (is_array($ids) && (count($ids) == 1 && is_numeric($ids[0]))
            || (count($ids) == 2 && is_numeric($ids[1]) && ( (!($ids[0] > 0)) || $ids[0] == self::$database_id)) )
    {
        //local or defined in this database

        if (count($ids) == 2) {
            $res_id = $ids[1];//this code is already local
        } else {
            $res_id = $ids[0];
        }

        $query = "select {$fieldNamePrefix}ID from $tableName where {$fieldNamePrefix}ID=" . intval($res_id);

        $res_id = mysql__select_value(self::$system->getMysqli(), $query);


    } elseif (is_array($ids) && count($ids) == 2 && is_numeric($ids[0]) && is_numeric($ids[1])) {
 $query = "select {$fieldNamePrefix}ID from $tableName where {$fieldNamePrefix}OriginatingDBID=".intval($ids[0])
             . SQL_AND . $fieldNamePrefix . "IDInOriginatingDB=" . intval($ids[1]);

        $res_id = mysql__select_value(self::$system->getMysqli(), $query);
    }

    if (!($res_id>0)) {
        $res_id = null;
    }
    return $res_id;
}
/**
* return local term id for a terms concept ID
* @param     int $trmConceptID Term concept ID
* @return    int|null The local Term ID, or null if not found.
* @uses      self::getLocalID()
*/
    /**
     * Returns the local Term ID for a given Term Concept ID.
     * @param string $trmConceptID Term Concept ID (e.g., "DBID-OrigID").
     * @return int|null Local Term ID, or null if not found.
     */
public static function getTermLocalID($trmConceptID) {
    return self::getLocalID($trmConceptID, "defTerms", "trm_");
}
/**
* return local detailType id for a detailTypes concept ID
* @param     int $dtyConceptID detailType concept ID
* @return    int|null The local DetailType ID, or null if not found.
* @uses      self::getLocalID()
*/
    /**
     * Returns the local DetailType ID for a given DetailType Concept ID.
     * @param string $dtyConceptID DetailType Concept ID (e.g., "DBID-OrigID").
     * @return int|null Local DetailType ID, or null if not found.
     */
public static function getDetailTypeLocalID($dtyConceptID) {
    return self::getLocalID($dtyConceptID, "defDetailTypes", "dty_");
}
/**
* return local recType id for a recTypes concept ID
* @param     int $rtyConceptID recType concept ID
* @return    int|null The local RecordType ID, or null if not found.
* @uses      self::getLocalID()
*/
    /**
     * Returns the local RecordType ID for a given RecordType Concept ID.
     * @param string $rtyConceptID RecordType Concept ID (e.g., "DBID-OrigID").
     * @return int|null Local RecordType ID, or null if not found.
     */
public static function getRecTypeLocalID($rtyConceptID) {
    return self::getLocalID($rtyConceptID, "defRecTypes", "rty_");
}
/**
* return local ontology id for a ontologys concept ID
* @param     int $ontConceptID ontology concept ID
* @return    int|null The local Ontology ID, or null if not found.
* @uses      self::getLocalID()
*/
    /**
     * Returns the local Ontology ID for a given Ontology Concept ID.
     * @param string $ontConceptID Ontology Concept ID (e.g., "DBID-OrigID").
     * @return int|null Local Ontology ID, or null if not found.
     */
public static function getOntologyLocalID($ontConceptID) {
    return self::getLocalID($ontConceptID, "defOntologies", "ont_");
}

}