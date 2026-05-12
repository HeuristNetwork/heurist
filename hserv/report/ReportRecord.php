<?php
/**
* ReportReport.php - Calss ReportRecord
* 
* Data provider and formatting helper for Smarty templates
*
* @project     Heurist academic knowledge management system
* @package Report
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.5
*/

namespace hserv\report;

use hserv\structure\ConceptCode;
use hserv\entity\DbDefRecStructure;
use hserv\utilities\USanitize;
use hserv\utilities\Temporal;


require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../records/search/recordSearch.php';
require_once dirname(__FILE__).'/../records/search/relationshipData.php';
require_once dirname(__FILE__).'/../structure/dbsTerms.php';

require_once dirname(__FILE__).'/../../vendor/autoload.php';//for geoPHP

/**
 * Suffix used for Smarty variables to access the original, unprocessed value of a field.
 * @var string
 */
define('RAW','_originalvalue');
/**
 * A string of allowed HTML tags for sanitizing record titles, typically used in link generation.
 * @var string
 */
define('ALLOWED_TAGS', '<i><b><u><em><strong><sup><sub><small><br>');//for recTitle

/**
 * Class ReportRecord
 *
 * This class serves as a data provider and formatting helper for Smarty templates used in Heurist reports.
 * It provides methods to access various aspects of Heurist records, including their details,
 * related records, associated files, and definitions (like record type names, field labels, term labels).
 * It also offers utility functions to retrieve system information, constants, and formatted links.
 * The class employs caching for frequently accessed data like record details and definitions
 * to optimize performance within the scope of a single report execution.
 *
 * An instance of this class is typically made available to Smarty templates under a variable
 * like `$heurist` or `$h`, allowing template designers to easily fetch and display Heurist data
 * using syntax like `{$heurist->getRecord($rec_id).f10}` or `{$heurist->rty_Name($record.recTypeID)}`.
 *
 */
class ReportRecord
{
    /** @var array Cache for fully loaded and formatted record data, keyed by record ID. */
    protected $recordsCache;
    /** @var array Cache for record type names, keyed by record type ID (rty_ID). */
    protected $rtyNames;
    /** @var array Cache for detail type definitions (dty_ID => dty_Type string), loaded from `dbs_GetDetailTypes`. */
    protected $dtyTypes;
    /** @var array Cache for record type structures, storing field display names. Format: `[rty_ID => [dty_ID => rst_DisplayName, ...]]`. */
    protected $rstFields;
    /** @var array|null Cache for term definitions, populated by `$dbsTerms`. Initially null. */
    protected $dtTerms = null;
    /** @var \DbsTerms|null Instance of the DbsTerms class for accessing term information. */
    protected $dbsTerms;
    /** @var \hserv\System The Heurist system object. */
    protected $system;
    /** @var array Cache for translated strings (e.g., term labels), structured by entity type (e.g., 'trm') and language code. */
    protected $translations;

    /**
     * ReportRecord constructor.
     *
     * Initializes the ReportRecord object with the Heurist system context.
     * It pre-caches record type names and basic detail type information.
     *
     * @param \hserv\System $system The main Heurist system object.
     */
    public function __construct($system)
    {
        $this->system = $system;
        $this->rtyNames = dbs_GetRectypeNames($system->getMysqli());
        $this->dtyTypes = dbs_GetDetailTypes($system, null, 4);   //dty_ID => dty_Type
        $this->recordsCache = array(); // Cache for loaded records
        
        $this->rstFields = array(); //Cache for rty structure
        
        //for backward capability
        if(!defined('HEURIST_DBNAME')){
            define('HEURIST_DBNAME', $this->system->dbname());
        }
        
        $this->translations = [
            'trm' => [],
            'ulf' => [],
            'rty' => [],
            'dty' => []
        ];        
    }

    /**
     * Returns the value of a Heurist system constant (e.g., a predefined Record Type ID or Detail Type ID).
     *
     * Example in Smarty: `{$heurist->constant('RT_PERSON')}`
     *
     * @param string $name The name of the Heurist constant (e.g., "RT_PERSON", "DT_NAME").
     * @param mixed|null $smarty_obj This parameter is present for compatibility with Smarty plugin registration
     *                               but is not used by the method.
     * @return mixed|null The value of the defined constant, or null if the constant is not defined.
     */
    public function constant($name, $smarty_obj = null)
    {
        $id = $this->system->getConstant($name);
        return $id;
    }

    /**
     * Returns the base URL of the Heurist instance.
     *
     * Example in Smarty: `{$heurist->baseURL()}`
     *
     * @return string The base URL as defined by `HEURIST_BASE_URL`.
     */
    public function baseURL()
    {
        return HEURIST_BASE_URL;
    }

    /**
     * Retrieves various pieces of system or database-level information.
     *
     * Supported parameters for `$param`:
     * - 'db_total_records': Total count of non-temporary records in the database.
     * - 'db_rty_counts': An associative array of record counts per record type ID.
     * - 'lang': The current layout language code (3-letter, e.g., 'eng').
     * - 'dbname': The name of the current Heurist database.
     * - 'user': An array of information about the current user (excluding preferences).
     *
     * Example in Smarty: `{$heurist->getSysInfo('dbname')}`
     *
     * @param string $param The specific piece of system information requested.
     * @return mixed|null The requested information, or null if the parameter is not recognized.
     */
    public function getSysInfo($param)
    {
        $res = null;
        $mysqli = $this->system->getMysqli();

        if ($param == 'db_total_records') {
            $res = mysql__select_value($mysqli, 'SELECT count(*) FROM Records WHERE not rec_FlagTemporary');
        } elseif ($param == 'db_rty_counts') {
            $res = mysql__select_assoc2($mysqli, 'SELECT rec_RecTypeID, count(*) FROM Records WHERE not rec_FlagTemporary GROUP BY rec_RecTypeID');
        } elseif ($param == 'lang') {
            $res = $_REQUEST['lang'] ?? $this->system->userGetPreference('layout_language', '');
            $res = getLangCode3($res);
        } elseif ($param == 'dbname') {
            $res = $this->system->dbname();
        } elseif ($param == 'user') {
            $usr = $this->system->getCurrentUser();
            unset($usr['ugr_Preferences']);
            return $usr;
        }

        return $res;
    }

    /**
     * Retrieves the name of a record type given its ID.
     *
     * Example in Smarty: `{$heurist->rty_Name($record.recTypeID)}`
     *
     * @param int $rty_ID The Record Type ID.
     * @return string The name of the record type, or an empty string if not found.
     */
    public function rty_Name($rty_ID)
    {
        return $this->rtyNames[$rty_ID];
    }

    /**
     * Retrieves the local Heurist Record Type ID for a given concept code.
     *
     * Example in Smarty: `{$heurist->rty_id('CMSRecord')}`
     *
     * @param string $conceptCode The concept code of the record type (e.g., "RTEvent", "1-23").
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return int The local Record Type ID, or 0 if not found/invalid.
     */
    public function rty_id($conceptCode, $smarty_obj = null)
    {
        return ConceptCode::getRecTypeLocalID($conceptCode);
    }

    /**
     * Retrieves the local Heurist Detail Type ID for a given concept code.
     *
     * Example in Smarty: `{$heurist->dty_id('hasTitle')}`
     *
     * @param string $conceptCode The concept code of the detail type (e.g., "DTDescription", "2-45").
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return int The local Detail Type ID, or 0 if not found/invalid.
     */
    public function dty_id($conceptCode, $smarty_obj = null)
    {
        return ConceptCode::getDetailTypeLocalID($conceptCode);
    }

    /**
     * Retrieves the local Heurist Term ID for a given concept code.
     *
     * Example in Smarty: `{$heurist->trm_id('eventType:Conference')}`
     *
     * @param string $conceptCode The concept code of the term (e.g., "relationType:isParentOf", "3-100").
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return int The local Term ID, or 0 if not found/invalid.
     */
    public function trm_id($conceptCode, $smarty_obj = null)
    {
        return ConceptCode::getTermLocalID($conceptCode);
    }

    /**
     * Retrieves and formats a Heurist record for use in Smarty templates.
     *
     * Fetches the record data using `recordSearchByID` if not already cached.
     * Formats the record into a Smarty-friendly associative array where detail fields
     * are accessible via keys like `f<ID>` (e.g., `f10`).
     * Also includes record header fields prefixed with `rec` (e.g., `recTitle`, `recID`),
     * `recTypeName`, `rec_Tags` (comma-separated), and `rec_IsVisible`.
     *
     * @param int|array $rec The Record ID, or an array containing `recID`.
     * @param bool $details If true (default), fetches full record details. If false,
     *                      may fetch only header data (depends on `recordSearchByID` behavior).
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return array|null The formatted record array suitable for Smarty, or null if the record is not found.
     */
    public function getRecord($rec, $details=true, $smarty_obj = null)
    {
        $rec_ID = intval(is_array($rec) && $rec['recID'] ? $rec['recID'] : $rec);
        if ($details===true && isset($this->recordsCache[$rec_ID])) {
            return $this->recordsCache[$rec_ID];
        }

        if ($this->interruptEnabled) {
            if(in_array($rec_ID, $this->mainRecordSet)){
                $this->done++;    
            }
            $this->tickInterrupt('tick');
        }        
        
        $rec = recordSearchByID($this->system, $rec_ID, $details);
        if ($details===true && $rec) {
            $rec['rec_Tags'] = recordSearchPersonalTags($this->system, $rec_ID);
            if (is_array($rec['rec_Tags'])) {
                $rec['rec_Tags'] = implode(',', $rec['rec_Tags']);
            }
            $rec['rec_IsVisible'] = $this->recordIsVisible($rec);
        }
        
        //converts to array suitable for smarty  r.title, r.fNNN
        $record = $this->getRecordForSmarty($rec);
        if($record){
            $this->recordsCache[$rec_ID] = $record;
        }
        return $record;
    }
    
    
    /**
     * Returns the URL of the default thumbnail for a given record.
     *
     * It uses `fileGetThumbnailURL()` to determine the thumbnail.
     *
     * @param int|array $rec The Record ID or a record array containing `recID`.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return string|null The URL of the thumbnail, or null if no thumbnail is found.
     */
    public function getRecordThumbnail($rec, $smarty_obj = null){
        
        $rec_ID = is_array($rec) && $rec['recID'] ? $rec['recID'] : $rec;
        
        $file_details = fileGetThumbnailURL($this->system, $rec_ID, false);
        
        if(!empty($file_details) && !empty($file_details['url'])){
                return $file_details['url'];
        }

        return null;
    }
            
            

    /**
     * Checks if a given record is visible to the current user based on its visibility settings and user's permissions.
     *
     * - Temporary records (`rec_FlagTemporary` = 1) are not visible.
     * - Database owners (user ID 2) can see all non-temporary records.
     * - 'hidden' records are not visible to others.
     * - For 'viewable' records, it checks if the current user is the owner or belongs to a group
     *   that has explicit permission via `usrRecPermissions`.
     *
     * @param array $rec The Heurist record array (must contain `rec_FlagTemporary`, `rec_NonOwnerVisibility`, `rec_OwnerUGrpID`, `rec_ID`).
     * @return bool True if the record is deemed visible to the current user, false otherwise.
     */
    public function recordIsVisible($rec)
    {
        if (@$rec['rec_FlagTemporary'] == 1) {
            return false;
        }

        $currentUser = $this->system->getCurrentUser();

        if ($currentUser['ugr_ID'] == 2) { // db owner
            return true;
        }

        $res = true;

        if ($rec['rec_NonOwnerVisibility'] == 'hidden') {
            $res = false;
        } elseif ($currentUser['ugr_ID'] > 0 && $rec['rec_NonOwnerVisibility'] == 'viewable') {
            $wg_ids = @$currentUser['ugr_Groups'] ? array_keys($currentUser['ugr_Groups']) : $this->system->getUserGroupIds();
            array_push($wg_ids, 0); // Include generic everybody workgroup

            if (!isEmptyArray($wg_ids) && !in_array($rec['rec_OwnerUGrpID'], $wg_ids)) {
                $allowed_groups = mysql__select_list2($this->system->getMysqli(), 'SELECT rcp_UGrpID FROM usrRecPermissions WHERE rcp_RecID=' . $rec['rec_ID']);
                if (empty($allowed_groups) && count(array_intersect($allowed_groups, $wg_ids)) > 0) {
                    $res = false;
                }
            }
        }

        return $res;
    }

    /**
     * Retrieves records related to the given record via Heurist relationships.
     *
     * For each relationship, it fetches details of the related record and information
     * about the relationship itself (type, notes, start/end dates).
     * The relationship record itself is also fetched and included.
     *
     * @param int|array $rec The Record ID or a record array containing `recID`.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return array An array of formatted related records. Each element includes:
     *               - Standard formatted fields of the related record (from `getRecord()`).
     *               - `recRelationID`: ID of the relationship record.
     *               - `recRelationType`: Label of the relationship type term.
     *               - `recRelationNotes`: Notes from the relationship record.
     *               - `recRelationStartDate`, `recRelationEndDate`: Formatted dates from the relationship.
     *               - `relationRecord`: The fully formatted relationship record itself.
     */
    public function getRelatedRecords($rec, $smarty_obj = null)
    {
        $rec_ID = $rec['recID'] ?? $rec;

        $relRT = $this->rty_id('2-1'); //RT_RELATION
        $relSrcDT =  $this->dty_id('2-7');  //DT_PRIMARY_RESOURCE
        $relTrgDT = $this->dty_id('2-5'); //DT_TARGET_RESOURCE

        $res = array();
        $rel_records = array();

        if (!($rec_ID > 0 && $relRT > 0 && $relSrcDT > 0 && $relTrgDT > 0)) {
             return $res;
        }

        $mysqli = $this->system->getMysqli();
        $from_res = $mysqli->query('SELECT rl_RelationID as dtl_RecID FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_SourceID=' . $rec_ID);
        $to_res = $mysqli->query('SELECT rl_RelationID as dtl_RecID FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_TargetID=' . $rec_ID);

        if (!($from_res && $to_res && ($from_res->num_rows > 0 || $to_res->num_rows > 0))) {
             return $res;
        }

        while ($reln = $from_res->fetch_assoc()) {
            $bd = fetch_relation_details($this->system, $reln['dtl_RecID'], true);
            array_push($rel_records, $bd);
        }
        while ($reln = $to_res->fetch_assoc()) {
            $bd = fetch_relation_details($this->system, $reln['dtl_RecID'], false);
            array_push($rel_records, $bd);
        }

        foreach ($rel_records as $value) {
            if (array_key_exists('RelatedRecID', $value) && array_key_exists('RelTerm', $value)) {
                $record = $this->getRecord($value['RelatedRecID']['rec_ID']);

                $record["recRelationID"] = $value['recID'];
                $record["recRelationType"] = $value['RelTerm'];
                $record["recRelationNotes"] = $value['Notes'] ?? null;
                $record["recRelationStartDate"] = Temporal::toHumanReadable($value['StartDate']) ?? null;
                $record["recRelationEndDate"] = Temporal::toHumanReadable($value['EndDate']) ?? null;

                $record["relationRecord"] = $this->getRecord($value['recID']);

                array_push($res, $record);
            }
        }

        $from_res->close();
        $to_res->close();


        return $res;
    }

    /**
     * Returns an array of linked records for a given record.
     *
     * @param int|array $rec The Record ID or a record array containing `recID`.
     * @param int|null $rty_ID (Optional) Filter linked records by this Record Type ID.
     * @param string|null $direction (Optional) Direction of links:
     *                               - 'linkedto': Records linked *to* by `$rec`.
     *                               - 'linkedfrom': Records linked *from* by `$rec`.
     *                               - null (default): Both directions.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return array An associative array with two keys:
     *               - 'linkedto': An array of record IDs that `$rec` links to.
     *               - 'linkedfrom': An array of record IDs that link to `$rec`.
     *               Each array is empty if no links are found for that direction or filter.
     */
    public function getLinkedRecords($rec, $rty_ID = null, $direction = null, $dty_ID = null, $smarty_obj = null)
    {                        
        $rec_ID = is_array($rec) && $rec['recID'] ? $rec['recID'] : $rec;
        $where = SQL_WHERE;
        $predicateRty = predicateId('rec_RecTypeID', $rty_ID, SQL_AND);

        if ($predicateRty != '') {
            $where = ', Records WHERE linkID=rec_ID ' . $predicateRty . SQL_AND;
        }

        $mysqli = $this->system->getMysqli();
        $to_records = array();
        $from_records = array();

        if ($direction == null || $direction == 'linkedto') {
            $to_query = 'SELECT rl_TargetID as linkID FROM recLinks ' . str_replace('linkID', 'rl_TargetID', $where) . ' rl_RelationID IS NULL AND rl_SourceID=' . $rec_ID;
            if($dty_ID>0){
                $to_query = $to_query.' AND rl_DetailTypeID='.intval($dty_ID);
            }
            $to_records = mysql__select_list2($mysqli, $to_query);
        }

        if ($direction == null || $direction == 'linkedfrom') {
            $from_query = 'SELECT rl_SourceID as linkID FROM recLinks ' . str_replace('linkID', 'rl_SourceID', $where) . ' rl_RelationID IS NULL AND rl_TargetID=' . $rec_ID;
            if($dty_ID>0){
                $from_query = $from_query.' AND rl_DetailTypeID='.intval($dty_ID);
            }
            $from_records = mysql__select_list2($mysqli, $from_query);
        }
        

        return array('linkedto' => $to_records, 'linkedfrom' => $from_records);
    }
    
    public function getLinkedFromRecords($rec, $rty_ID, $dty_ID, $smarty_obj = null)
    {
        $res = $this->getLinkedRecords($rec, $rty_ID, 'linkedfrom', $dty_ID, $smarty_obj);
        
        return $res['linkedfrom'];
    }

    /**
     * Converts a raw Heurist record array into a format more accessible for Smarty templates.
     *
     * - Standard record fields (rec_ID, rec_Title, etc.) are mapped to keys like `recID`, `recTitle`.
     * - Record Type ID is mapped to `recTypeID` and its name to `recTypeName`.
     * - Tags are included as `rec_Tags` (comma-separated string).
     * - Visibility is checked and stored in `rec_IsVisible`.
     * - Detail fields are processed by `processRecordDetails` and added as `f<ID>` keys.
     *
     * Caches the processed record in `$this->recordsCache`.
     *
     * @param array|null $rec Raw record data.
     * @return array|null Formatted record array for Smarty, or null if input is null.
     */
    private function getRecordForSmarty($rec)
    {
        if (!$rec) {
            return null;
        }

        $recordID = $rec['rec_ID'];

        if (@$this->recordsCache[$recordID]) {
            return $this->recordsCache[$recordID]; //form cache
        }

        $record = array();
        $recTypeID = null;
        $lang = $this->getSysInfo('lang');

        foreach ($rec as $key => $value) {
            if (strpos($key, "rec_") === 0) {
                $this->processRecordField($record, $key, $value, $recTypeID);
            } elseif ($key == "details") {
                $this->processRecordDetails($record, $value, $recTypeID, $recordID, $lang);
            }
        }

        if (count($this->recordsCache) > 2500) {
            $this->recordsCache = array(); // Reset cache if too many records are loaded
        }

        return $record;
    }

    /**
     * Helper function for `getRecordForSmarty` to process standard record header fields.
     *
     * Maps fields like `rec_ID` to `recID`, `rec_Title` to `recTitle`, etc.
     * Sets `recTypeID` and `recTypeName`.
     *
     * @param array &$record The Smarty-formatted record array being built (passed by reference).
     * @param string $key The original key of the record field (e.g., "rec_ID").
     * @param mixed $value The value of the record field.
     * @param int &$recTypeID Passed by reference; this variable is updated if the current key is "rec_RecTypeID".
     */
    private function processRecordField(&$record, $key, $value, &$recTypeID)
    {
        $record['rec' . substr($key, 4)] = $value;

        if ($key == 'rec_RecTypeID') {
            $recTypeID = $value;
            $record['recTypeID'] = $recTypeID;
            $record['recTypeName'] = $this->rtyNames[$recTypeID];
        } elseif ($key == 'rec_Tags') {
            $record['rec_Tags'] = $value;
        }
    }

    /**
     * Helper function for `getRecordForSmarty` to process the 'details' part of a raw record.
     *
     * Iterates through each detail field, calling `getDetailForSmarty` to format it,
     * and then merges the formatted detail into the main `$record` array being built for Smarty.
     *
     * @param array &$record The Smarty-formatted record array being built (passed by reference).
     * @param array $details The 'details' array from the raw Heurist record.
     * @param int $recTypeID The Record Type ID of the current record.
     * @param int $recordID The Record ID of the current record.
     * @param string $lang The current language code for translations.
     */
    private function processRecordDetails(&$record, $details, $recTypeID, $recordID, $lang)
    {
        foreach ($details as $dtKey => $dtValue) {
            $dt = $this->getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recordID, $lang);
            if ($dt != null) {
                $record = array_merge($record, $dt);
            }
        }
    }

    /**
     * Helper function to concatenate a new term value to an existing string of term values.
     *
     * If the result string `$res` is not empty, a comma and space are appended before adding `$val`.
     *
     * @param string $res The existing string of concatenated term values.
     * @param string|null $val The new term value to add.
     * @return string The updated string of concatenated term values.
     */
    private function addTermValue($res, $val){

        if($val){
            if(strlen($res)>0) {$res = $res.", ";}
            $res = $res.$val;
        }
        return $res;
    }

    /**
     * Formats a single detail field's value(s) for Smarty.
     *
     * This is a key formatting method that handles different Heurist detail types:
     * - 'enum', 'relationtype': Uses `getDetailForEnum`.
     * - 'date': Formats using `Temporal::toHumanReadable`.
     * - 'file': Generates links or player tags, potentially preparing data for Fancybox.
     * - 'geo': Creates a link to Google Maps for point data.
     * - 'resource': Stores the linked record ID.
     * - Text types ('freetext', 'blocktext'): Handles translations and basic text.
     *
     * The formatted field is returned as an array with keys:
     * - `f<ID>`: Concatenated string of processed values (e.g., term labels, formatted dates).
     * - `f<ID>s`: Array of processed values (e.g., array of term objects, array of file link strings).
     * - `f<ID>_originalvalue`: Array of original, raw values from the database.
     * - For 'geo' type, it also adds `f<ID>_geojson` with the GeoJSON representation.
     *
     * @param int|string $dtKey The Detail Type ID, or a special key for non-standard details (e.g., relationship info).
     * @param mixed $dtValue The raw value(s) of the detail field.
     * @param int $recTypeID The Record Type ID of the parent record.
     * @param int $recordID The Record ID of the parent record.
     * @param string $lang The current language code for translations.
     * @return array|null An associative array with formatted values for Smarty, or null if the detail type is unknown/unhandled.
     */
    private function getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recordID, $lang){

        $dtname = null;

        if($dtKey<1){
            $dtname = 'Relationship';
            $detailType =  'relmarker';
        }elseif (@$this->dtyTypes[$dtKey]) {
            $dtname = 'f'.$dtKey;
            $detailType =  $this->dtyTypes[ $dtKey ];
        }else{
            return null;//name is not defined
        }

        if(!is_array($dtValue)){
                return array( $dtname=>$dtValue );
        }

        //complex type - need more analize

        $res = null;

        // fNNN - concatenated value (or first for blocktext)
        // fNNNs - prepared
        //              date - array of human readable dates   see toHumanReadable
        //              term - array of terms data (id, label, code, conceptid) see getDetailForEnum
        //              file - array of urls
        //              geo  - human readable link
        //              
        // fNNN_originalvalue 
        //
        
        switch ($detailType) {
            case 'enum':
            case 'relationtype':

                $res = $this->getDetailForEnum($dtname, $dtValue, $lang);
                break;

            case 'date':

                $res = "";
                $origvalues = array();
                $preparedvalues = array();
                foreach ($dtValue as $value){
                    if(strlen($res)>0) {$res = $res.", ";}
                    $val = Temporal::toHumanReadable($value, true, 0, '|', 'native');
                    $res = $res.$val;
                    array_push($preparedvalues, $val);
                    array_push($origvalues, $value);
                }
                if(strlen($res)==0){ //no valid terms
                    $res = null;
                }else{
                    $res = array( $dtname=>$res, $dtname.'s'=>$preparedvalues, $dtname.RAW=>$origvalues);
                }
                break;

            case 'file':
                //get url for file download

                //if image - special case

                $res = array();//list of urls
                $origvalues = array();
                $preparedvalues = array();
                $file_url=null;

                foreach ($dtValue as $value){
                    
                    //keep reference to record id
                    $value['file']['rec_ID'] = $recordID;

                    $value['file']['ulf_Caption'] = $this->getTranslation('ulf', $value['file']['ulf_ID'], 'ulf_Caption', $lang);
                    $value['file']['ulf_Description'] = $this->getTranslation('ulf', $value['file']['ulf_ID'], 'ulf_Description', $lang);

                    $link = $this->composeFileLink($value['file']);
                    array_push($preparedvalues, $link);
                    //original value keeps the whole 'file' array
                    array_push($origvalues, $value['file']);
                    
                    if($file_url!=null){
                        continue;
                    }
                    $external_url = @$value['file']['ulf_ExternalFileReference'];
                    if ($external_url && strpos($external_url,'http://')!==0) {
                        $file_url = $external_url;//external

                    }elseif (@$value['file']['ulf_ObfuscatedFileID']) {
                        //local
                        $file_url = HEURIST_BASE_URL."?db=".$this->system->dbname()
                                ."&file=".$value['file']['ulf_ObfuscatedFileID'];
                    }
                }
                //$res = implode(', ',$preparedvalues);
                
                if($file_url==null){
                    $res = null;
                }else{
                    $res = array($dtname=>$file_url, $dtname.'s'=>$preparedvalues, $dtname.RAW=>$origvalues);
                }

                break;

            case 'geo':

                $res = "";
                $arres = array();
                $origvalues = array();
                $preparedvalues = array();
                
                foreach ($dtValue as $key => $value){

                    //original value keeps whole geo array
                    $dtname2 = $dtname.RAW;
                    $value['geo']['recid'] = $recordID;
                    $arres = array_merge($arres, array($dtname2=>$value['geo']));
                    array_push($origvalues, $value['geo']);

                    $geom = \geoPHP::load($value['geo']['wkt'], 'wkt');
                    if(!$geom->isEmpty()){
                        $geojson_adapter = new \GeoJSON();
                        $json = $geojson_adapter->write($geom, true);
                        
                        //$geom->envelope();
                        $bbox = $geom->getBBox();
                        
                        switch ($value['geo']['type']) {
                            case "p": $type = "Point"; break;
                            case "pl": $type = "Polygon"; break;
                            case "c": $type = "Circle"; break;
                            case "r": $type = "Rectangle"; break;
                            case "l": $type = "Path"; break;
                            case "m": $type = "Collection"; break;
                            default: $type = "Collection";
                        }
                        
                        if ($type == "Point"){
                            $link = "<b>Point</b> ".($bbox['minx']!=null?round($bbox['minx'],7).", ".round($bbox['miny'],7):'');
                        }else{
                            $link = "<b>$type</b> X ".($bbox['minx']!=null?round($bbox['minx'],7).", ".round($bbox['maxx'],7).
                            " Y ".round($bbox['miny'],7).", ".round($bbox['maxx'],7):'');
                        }   

                        $url = HEURIST_BASE_URL.'viewers/map/map.php?q=ids:'.$recordID
                            .'&db='.$this->system->dbname()
                            .'&notimeline=1&nocluster=1&basemap=OpenStreetMap&controls=none&published=true&popup=none';
                        
                        $geoimage =
                        '<img class="geo-image" style="vertical-align:top;" src="'.HEURIST_BASE_URL
                            .'hclient/assets/geo.gif" onclick="{if(window.hWin && window.hWin.HEURIST4){window.hWin.HEURIST4.msg.showDialog(\''
                            .$url.'\')}}">&nbsp;';
                        
                        array_push($preparedvalues, $geoimage.$link);
                    }
                    if(!$json) {$json = array();}
                    $dtname2 = $dtname."_geojson";
                    $arres = array_merge($arres, array($dtname2=>$json));

                    $res = $value['geo']['wkt'];
                    break; //only one geo location at the moment
                }

                if(strlen($res)==0){
                    $res = null;
                }else{
                    //fNNN=>wkt, fNNNs=>human readable links, fNNN_originalvalue=>array(recid,wkt), fNNN_geojson=>json
                    $res = array($dtname=>$res, $dtname.'s'=>$preparedvalues, $dtname.RAW=>$origvalues);
                    //array_merge($arres, array($dtname=>$res));
                }

                break;

            case 'separator':
            //case 'calculated':
            case 'fieldsetmarker':
                break;

            case 'relmarker': // NOT USED
                break;
            case 'resource': // link to another record type

                $res = array();
                if(empty($dtValue)){
                   break;
                }
                
                foreach ($dtValue as $value){
                    array_push($res, $value['id']);
                }
                
                $res = array( $dtname =>$res[0], $dtname.'s' =>$res );

                break;

            default:
                // repeated basic detail types
                $res = "";
                $origvalues = array();
                $preparedvalues = array();

                if($detailType=='freetext' || $detailType=='blocktext'){
                    $lang = getLangCode3($lang);
                    $def = array();
                
                    //get trnaslated values
                    foreach ($dtValue as $value){
                        
                            list($lang_, $val) = extractLangPrefix($value);    

                            $val = USanitize::sanitizeString($val);
                            
                            if ($lang_!=null && $lang_==$lang){
                                array_push($preparedvalues, $val);
                            }elseif($lang_==null){
                                //without prefix
                                array_push($def, $val);
                            }
                            
                            array_push($origvalues, $value); //all
                    }
                    
                    if(count($preparedvalues)==0 && count($def)>0){
                        $preparedvalues = $def;
                    }
                    
                    //USanitize::sanitizeString($rec_title,ALLOWED_TAGS)
                    
                }else{
                    $origvalues = array_values($dtValue);
                    $preparedvalues = $origvalues;
                }
                
                if(count($preparedvalues)==0){ //no valid values
                    $res = null;
                }else{
                    $res = implode(', ', $preparedvalues);
                    $res = array( $dtname=>$res, $dtname.'s'=>$preparedvalues, $dtname.RAW=>$origvalues);
                }

    
        }//end switch

        return $res;

    }

    /**
     * Formats enumeration or relationtype fields for Smarty.
     *
     * For each term ID in `$dtValue`:
     * - Fetches the term object using `DbsTerms`.
     * - Retrieves translations for term label and description if applicable.
     * - Constructs an array for Smarty containing `id` (term ID), `internalid` (term ID),
     *   `code`, `label` (translated), `term` (full hierarchical label), `conceptid`, and `desc` (translated).
     *
     * The returned array for Smarty includes:
     * - `f<ID>`: Comma-separated string of translated term labels.
     * - `f<ID>s`: Array of the term objects described above.
     * - `f<ID>_originalvalue`: Array of original term IDs.
     *
     * @param string $dtname The Smarty variable base name for this field (e.g., "f10").
     * @param array $dtValue An array of term IDs.
     * @param string $lang The current language code for translations.
     * @return array|null An associative array with formatted term data for Smarty, or null if no valid terms processed.
     */
    private function getDetailForEnum($dtname, $dtValue, $lang){

        if($this->dtTerms==null){
            $this->dtTerms = dbs_GetTerms($this->system);
            $this->dbsTerms = new \DbsTerms($this->system, $this->dtTerms);
        }

        $fi = $this->dtTerms['fieldNamesToIndex'];

        $res_id = "";
        $res_cid = "";
        $res_code = "";
        $res_label = "";
        $res_label_full = '';
        $res_desc = "";
        $res = array();
        $origvalues = array();

        foreach ($dtValue as $value){

            $term = $this->dbsTerms->getTerm($value);
            if($term){

                //IJ wants to show terms for all parents
                $term_full = $this->dbsTerms->getTermLabel($value, true);

                $term_label = $this->getTranslation('trm', $value, 'trm_Label', $lang);
                $term_desc = $this->getTranslation('trm', $value, 'trm_Description', $lang);

                $res_id = $this->addTermValue($res_id, $value);
                $res_cid = $this->addTermValue($res_cid, $term[ $fi['trm_ConceptID'] ]);
                $res_code = $this->addTermValue($res_code, $term[ $fi['trm_Code'] ]);

                $res_label_full = $this->addTermValue($res_label_full, $term_full);
                $res_label = $this->addTermValue($res_label, $term_label);//$term[ $fi['trm_Label'] ]);
                $res_desc = $this->addTermValue($res_desc, $term_desc);//$term[ $fi['trm_Description'] ]);

                //NOTE id and label are for backward
                //original value
                array_push($res, array("id"=>$value, "internalid"=>$value,
                    "code"=>$term[ $fi['trm_Code'] ],
                    "label"=>$term_label,
                    "term"=>$term_full,
                    "conceptid"=>$term[ $fi['trm_ConceptID'] ],
                    "desc"=>$term_desc
                ));
                array_push($origvalues, $value);
            }
        }

        if(!empty($res)){
            $res = array( $dtname =>$res[0], $dtname.'s'=>$res, $dtname.RAW=>$origvalues );
        }

        return $res;
    }

    /**
     * Executes a Heurist search query and returns an array of matching record IDs.
     *
     * If the query string contains the placeholder `[ID]`, and `$current_rec` (a record ID)
     * is provided, `[ID]` will be replaced with the current record's ID in the query.
     * This allows for context-dependent sub-queries within a report.
     *
     * Example in Smarty: `{$heurist->getRecords('t:Person AND f123:[ID]')}`
     *
     * @param string|array $query A Heurist query string or its JSON representation as a PHP array.
     * @param int|array|null $current_rec (Optional) The current record's ID or a record array containing `recID`.
     *                                    Used for substituting `[ID]` in the query.
     * @return array|null An array of record IDs matching the query, or null if the search fails or returns no results.
     */
    public function getRecords($query, $current_rec = null)
    {
        $rec_ID = is_array($current_rec) && $current_rec['recID'] ? $current_rec['recID'] : $current_rec;

        if(is_array($query)){
            $query = json_encode($query);
        }


        if ($rec_ID > 0 && strpos($query, '[ID]') !== false) {
            $query = str_replace('[ID]', strval($rec_ID), $query);
        } elseif (strpos($query, '[ID]') !== false) {
            return null;
        }

        $params = array('detail' => 'ids', 'q' => $query, 'needall' => 1);
        $response = recordSearch($this->system, $params);

        if (@$response['status'] == HEURIST_OK) {
            return $response['data']['records'];
        } else {
            return null;
        }
    }

    /**
     * Performs aggregation (count, sum, average) on specified fields for a given set of records.
     *
     * The set of records can be defined by a list of IDs or by a Heurist query.
     *
     * Example in Smarty:
     * `{$aggr = $heurist->getRecordsAggr([ [10, 'sum'], [12, 'count'] ], $myRecordIds)}`
     * `Sum of field 10: {$aggr[0][2]}`
     *
     * @param array $functions An array of aggregation function definitions. Each definition is an array:
     *                         `[detail_type_ID, aggregation_type]`, where `aggregation_type`
     *                         is 'avg', 'count', or 'sum'. If `detail_type_ID` is 0 or not positive for 'count',
     *                         it counts records (`count(rec_ID)`).
     * @param string|array $query_or_ids Either a Heurist query string/JSON array to select records,
     *                                   or a comma-separated string/array of record IDs.
     * @param int|array|null $current_rec (Optional) The current record context, used if `$query_or_ids` is a query
     *                                    containing `[ID]`.
     * @return array|mixed|null If multiple functions are specified, returns an array where each element is
     *                          `[detail_type_ID, aggregation_type, result_value]`.
     *                          If a single function is specified, returns just the `result_value`.
     *                          Returns null if no functions, no IDs, or an error occurs.
     */
    public function getRecordsAggr($functions, $query_or_ids, $current_rec = null)
    {
        $ids = prepareIds($query_or_ids);
        if (empty($ids)) {
            $ids = $this->getRecords($query_or_ids, $current_rec);
        }

        //calculate aggregation values
        $select = array();
        $from = array('Records');
        $result = array();
        $idx = 0;

        if(is_array($functions) && count($functions)==2 && !is_array($functions[0])){
            $functions = array($functions);
        }
        
        if(count($functions)==1 && $functions[0]==0){
            return count($ids);
        }

        foreach ($functions as $func) {
            $dty_ID = $func[0];
            $func_type = $func[1];

            if (in_array($func_type, ['avg', 'sum', 'count'])) {
                if ($dty_ID > 0) {
                    array_push($select, $func_type . '(d' . $idx . '.dtl_Value)');
                    array_push($from, 'JOIN recDetails d' . $idx . ' ON rec_ID=d' . $idx . '.dtl_RecID AND d' . $idx . '.dtl_DetailTypeID=' . $dty_ID);
                } else {
                    array_push($select, 'count(DISTINCT rec_ID)');
                }
                array_push($result, array($dty_ID, $func_type, 0));
                $idx++;
            }
        }

        if (empty($select) || empty($ids)) {
            return null;
        }

        $query = 'SELECT ' . implode(',', $select) . ' FROM ' . implode(' ', $from) . ' WHERE rec_ID IN (' . implode(',', $ids) . ')';
        $res = mysql__select_row($this->system->getMysqli(), $query);

        if ($res == null) {
               return null;
        }

        if (count($res) == 1) {
            return $res[0];
        }

        foreach ($res as $idx => $val) {
            $result[$idx][2] = $val;
        }
        return $result;

    }

    /**
     * Retrieves translations for Heurist definition labels (terms, record types, detail types).
     *
     * It fetches translations from the `defTranslations` table for the specified entity IDs
     * and language. If a translation is not found for a specific ID in the requested language,
     * it falls back to the default label of the entity. Results are cached per language per entity type.
     *
     * @param string $entity The type of entity to translate: 'trm' (term), 'rty' (record type), 'dty' (detail type), 'ulf' (record files).
     * @param int|string|array $ids A single ID, or a comma-separated string/array of IDs for the entities.
     * @param string|null $field The specific field to translate. For 'trm', can be 'trm_Label' (default) or 'trm_Description'.
     *                           For 'rty'/'dty', it defaults to 'rty_Name'/'dty_Name'.
     *                           For 'ulf', can be 'ulf_Caption' (default) or 'ulf_Description'
     * @param string|null $language_code The 3-letter language code (e.g., 'fre'). If null, uses the current system language.
     * @return string|array If a single ID was provided, returns the translated string.
     *                      If multiple IDs were provided, returns an associative array `[ID => translated_string]`.
     */
    public function getTranslation($entity, $ids, $field = null, $language_code = null)
    {
        if ($language_code == null) {
            $language_code = $this->getSysInfo('lang');
        }
        $language_code = getLangCode3($language_code);
        $rtn = array();
        $def_values = array();
        $id_clause = '';

        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        if (!array_key_exists($language_code, $this->translations[$entity])) {
            $this->translations[$entity][$language_code] = array();
        }

        $cache = $this->translations[$entity][$language_code];

        if ($entity == 'trm') {
            $field = strpos(strtolower($field), 'desc') === false ? 'trm_Label' : 'trm_Description';
        } elseif($entity == 'ulf') {
            $field = strpos(strtolower($field), 'desc') === false ? 'ulf_Caption' : 'ulf_Description';
        } else {
            $field = $entity . '_Name';
        }

        //take translation from cache
        foreach ($ids as $idx => $id) {
            if (array_key_exists($id, $cache) && @$cache[$id][$field]) {
                $rtn[$id] = $cache[$id][$field];
                unset($ids[$idx]);
            }
        }

        if (empty($ids)) {
            return count($rtn) == 1 ? array_shift($rtn) : $rtn;
        }

        $ids = prepareIds($ids);
        $id_clause = predicateId('trn_Code', $ids, SQL_AND);

        if ($id_clause != '') {

            if ($entity == 'trm') {
                $def_values = $this->fillTermNames($ids, $field);
            } elseif ($entity == 'ulf') {
                $def_values = $this->fillFileNames($ids, $field);
            }

            $mysqli = $this->system->getMysqli();
            $query = "SELECT trn_Code, trn_Translation FROM defTranslations WHERE trn_Source = '$field' AND trn_LanguageCode = '$language_code' $id_clause";
            $res = mysql__select_assoc2($mysqli, $query);

            foreach ($ids as $id) {
                $rtn[$id] = $res[$id] ?? $def_values[$id] ?? '';
                $cache[$id] = array($field => $rtn[$id]);
            }
        }

        $this->translations[$entity][$language_code] = $cache;
        return count($rtn) == 1 ? array_shift($rtn) : $rtn;
    }

    /**
     * Helper function to get default term names/descriptions for a list of term IDs.
     *
     * Used by `getTranslation` as a fallback when a specific language translation is not found.
     *
     * @param array $ids Array of term IDs.
     * @param string $field The term field to retrieve ('trm_Label' or 'trm_Description').
     * @return array Associative array `[term_ID => default_value]`.
     */
    private function fillTermNames($ids, $field){

        $def_values = array();

        if ($this->dtTerms == null) {
            $this->dtTerms = dbs_GetTerms($this->system);
        }
        if ($this->dbsTerms == null) {
            $this->dbsTerms = new \DbsTerms($this->system, $this->dtTerms);
        }

        foreach ($ids as $trm_id) {
            $term = $this->dbsTerms->getTerm($trm_id);
            $def_values[$trm_id] = $term ? $term[$this->dtTerms['fieldNamesToIndex'][$field]] : '';
        }
        return $def_values;
    }

    /**
     * Helper function to get default file captions/descriptions for a list of file IDs/Obfuscated IDs.
     * 
     * Used by `getTranslation` as a fallback when a specific language translation is not found.
     *
     * @param array $ids Array of file IDs or Obfuscated IDs
     * @param string $field The file field to retrieve ('ulf_Caption' or 'ulf_Description')
     * @return array Associative array `[ulf_ID => default_value]`
     */
    private function fillFileNames($ids, $field){

        $def_value = [];
        $mysqli = $this->system->getMysqli();

        $query = "SELECT {$field} FROM recUploadedFiles WHERE ";
        foreach ($ids as $ulf_id) {
            $where = isPositiveInt($ulf_id) ? "ulf_ID = {$ulf_id}" : "ulf_ObfuscatedFileID = '{$ulf_id}'";
            $def_value[$ulf_id] = mysql__select_value($mysqli, "{$query}{$where}");
        }

        return $def_value;
    }

    /**
     * Retrieves a specific metadata field (e.g., description, caption, copyright) for one or more uploaded files.
     *
     * The input `$file_details` can be:
     * - A single file detail array (as stored in `ReportRecord`'s cache for `f<ID>_originalvalue`).
     * - An array of such file detail arrays.
     * - A comma-separated string of file URLs (from which obfuscated file IDs are extracted).
     *
     * The `$field` parameter specifies which metadata to retrieve, using short aliases
     * (e.g., 'desc' for description, 'cap' for caption, 'rights' for copyright).
     *
     * Example in Smarty: `{$heurist->getFileField($myFile, 'desc')}`
     *
     * @param array|string $file_details File information.
     * @param string $field Alias for the metadata field to retrieve (default 'name' for `ulf_OrigFileName`).
     *                      Valid aliases: 'desc'/'description', 'cap'/'caption', 'rights'/'copyright',
     *                      'owner'/'copyowner', 'type'/'ext'/'extension', 'filename'/'name'.
     * @return string|array If a single file's data is processed, returns the string value of the field.
     *                      If multiple files are processed, returns an array of string values.
     *                      Returns the original `$file_details` if `$field` is unrecognized.
     */
    public function getFileField($file_details, $field = 'name')
    {
        $mysqli = $this->system->getMysqli();
        $fields_map = [
            'desc' => 'ulf_Description',
            'description' => 'ulf_Description',
            'cap' => 'ulf_Caption',
            'caption' => 'ulf_Caption',
            'rights' => 'ulf_Copyright',
            'copyright' => 'ulf_Copyright',
            'owner' => 'ulf_Copyowner',
            'copyowner' => 'ulf_Copyowner',
            'type' => 'ulf_MimeExt',
            'ext' => 'ulf_MimeExt',
            'extension' => 'ulf_MimeExt',
            'filename' => 'ulf_OrigFileName',
            'name' => 'ulf_OrigFileName'
        ];
        $field = $fields_map[$field] ?? '';

        if (empty($field)) {
            return $file_details;
        }

        $results = [];

        if (is_array($file_details)){
            foreach ($file_details as $file_dtls) {
                $results[] = $file_dtls[$field] ?? '';
            }
            return count($results) == 1 ? $results[0] : $results;
        }

        $files = explode(',', $file_details);
        foreach ($files as $f_url) {
            $url_params = [];
            parse_str(parse_url(trim($f_url), PHP_URL_QUERY), $url_params);
            $ulf_ObfuscatedFileID = $url_params['file'] ?? null;
            if ($ulf_ObfuscatedFileID && preg_match('/^[a-z0-9]+$/', $ulf_ObfuscatedFileID)) {
                $result = mysql__select_value($mysqli, "SELECT $field FROM recUploadedFiles WHERE ulf_ObfuscatedFileID = '$ulf_ObfuscatedFileID'");
                $results[] = $result ?: '';
            }
        }

        return count($results) == 1 ? $results[0] : $results;
    }
    
    /*
    
    1) ordered fields for given rectype
    2) field label (DisplayName) for rectype+field  |modifier label
    3) field value                                  |modifier raw  display  
    4) formatted pairs: label+values based on given template
    
    */

    /**
     * Retrieves the defined structure (ordered fields and their display names) for a given record's type.
     *
     * Uses `DbDefRecStructure` to fetch the field structure for the record's type (`$rec['recTypeID']`).
     * Results are cached in `$this->rstFields` to optimize repeated calls for the same record type.
     *
     * @param array $rec A Smarty-formatted record array, which must contain `recTypeID`.
     * @return array|null An associative array `[detail_type_ID => display_name, ...]` for the record type,
     *                    or null if the record type is invalid or has no defined structure.
     */
    public function getRecordStructure($rec){
        
        if(!($rec && @$rec['recTypeID']>0)){
            return null;
        }

        $rty_ID = @$rec['recTypeID'];
        
        if(array_key_exists($rty_ID, $this->rstFields)){
            return $this->rstFields[$rty_ID];
        }
        
        //find record type structure
        $defRecStructure = new DbDefRecStructure($this->system, array('details'=>'listshort','rst_RecTypeID'=>$rty_ID));
        $structure = $defRecStructure->search();
        
        if(!$structure || @$structure['reccount']==0){ //not found
            return null;
        }
        
        //'rst_ID,rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,dty_Type
        $this->rstFields[$rty_ID] = array();
        foreach($structure['records'] as $rst){
            $this->rstFields[$rty_ID][$rst[2]] = $rst[3];
        }
        
        return $this->rstFields[$rty_ID];
    }

    /**
     * Retrieves the display label for a specific detail type (field) within a given record's type.
     *
     * Uses `getRecordStructure()` to get all field labels for the record's type,
     * then returns the label for the specified `$dty_ID`.
     *
     * @param array $rec A Smarty-formatted record array (must contain `recTypeID`).
     * @param int $dty_ID The Detail Type ID of the field.
     * @return string The display label of the field, or "Field [dty_ID]" if not found.
     */
    public function getFieldLabel($rec, $dty_ID){

        $rst = $this->getRecordStructure($rec);
        
        if($rst==null || @$rst[$dty_ID]==null){
            //structure not found or field is not standard
            return 'Field '.$dty_ID;
        }
        
        return @$rst[$dty_ID];
    }
    
    /**
     * Retrieves the data type string (e.g., 'enum', 'freetext', 'date') for a given Detail Type ID.
     *
     * @param int $dty_ID The Detail Type ID.
     * @return string|null The data type string, or 'relmarker' if $dty_ID < 1, or null if not found.
     */
    public function getFieldType($dty_ID){
        
        $detailType = null;
    
        if($dty_ID<1){
            $detailType =  'relmarker';
        }elseif (@$this->dtyTypes[$dty_ID]) {
            $detailType =  @$this->dtyTypes[ $dty_ID ];
        }
        return $detailType;
    }
    
    /**
     * Prepares a record for display, primarily by identifying and flagging empty field groups (sections).
     *
     * It iterates through the fields defined in the record's type structure. If a field is a 'separator'
     * and all subsequent fields until the next separator (or end) are empty in the given `$rec` data,
     * the separator field itself (e.g., `$rec['f123']`) is set to the string 'empty'.
     * It also calculates `recGroupCount` which is the number of non-empty groups.
     *
     * Note: This method modifies the input `$rec` array indirectly by potentially setting separator fields to 'empty'.
     *
     * @param array $rec A Smarty-formatted record array.
     * @param string|null $lang Language code, currently unused in this method's logic but kept for potential future use.
     * @return array The modified (or original if no changes made) record array with `recGroupCount` and potentially
     *               separator fields marked as 'empty'. Returns the original `$rec` if structure is not found.
     */
    public function prepareRecord($rec, $lang=null){
        
        $rts = $this->getRecordStructure($rec);
        
        $sepKey = '';
        $cntGroups = 0;
        $isEmpty = false;

        foreach ($rts as $dty_ID=>$label){
  
            $dtyKey ='f'.$dty_ID;
            $dtyType = $this->getFieldType($dty_ID);
            
            if ($dtyType=='separator'){
                
                if ($sepKey!=''){
                    if($isEmpty){
                        $rec[$sepKey]='empty';
                    }else{
                        $cntGroups++;
                    }
                }
                  
                $sepKey = $dtyKey;
                $isEmpty = true;
                continue;
            }

            if ($rec[$dtyKey]!=null){ //&& count($rec[$dtyKey.'s']
                $isEmpty = false;
            }
        }//for

        if ($sepKey!=''){
            if($isEmpty){
                $rec[$sepKey]='empty';
            }else{
                $cntGroups++;
            }
        }
        
        $rec['recGroupCount'] = $cntGroups;
        
        return $rec;
    }
    
    //
    /**
     * Composes an HTML link to a Heurist record, optionally using a specified report template for display.
     *
     * If a `$template_name` is provided and the record is visible, the link will point to the report
     * generated by that template for the given record. Otherwise, or if the record is not visible,
     * it returns the sanitized record title.
     * The link is constructed to open in a popup (`target="_popup"`) and uses `open_link()` JavaScript.
     *
     * @param int $rec_ID The ID of the record to link to.
     * @param string|null $template_name The basename of the Smarty template file to use for displaying the record.
     *                                   If null, only the record title is returned.
     * @return string An HTML `<a>` tag linking to the record/report, or the sanitized record title.
     *                Returns an empty string if the record is not found.
     */
    public function composeRecLink($rec_ID, $template_name){
        
        $rec = recordSearchByID($this->system, $rec_ID, false);
        
        if(!$rec){
            return ''; //not found
        }
        
        $recTitle = USanitize::sanitizeString($rec['rec_Title'], ALLOWED_TAGS);
        
        if($template_name==null || !$this->recordIsVisible($rec)){
            return $recTitle;
        }
        
        $url = HEURIST_BASE_URL.'?db='.$this->system->dbname()."&template=$template_name&q=ids:$rec_ID";
        
        print '<a href="'.$url.'" target="_popup" onclick="open_link(this)">'.$recTitle.'</a>';
    }
    
    //
    /**
     * Composes an HTML link for a file, including an icon and file information.
     *
     * Generates an `<a>` tag that links to the file (either its external URL or a Heurist download link).
     * The link text includes an external link icon, the IIIF logo if applicable, the filename
     * (or URL if no filename), and the file size in KB.
     *
     * @param array $fileinfo An associative array of file metadata, typically from a `ulf_` prefixed field set
     *                        (e.g., `ulf_ExternalFileReference`, `ulf_OrigFileName`, `ulf_FileSizeKB`, `ulf_ObfuscatedFileID`).
     * @return string The generated HTML string for the file link.
     */
    public function composeFileLink($fileinfo){
        
        $filepath = $fileinfo['fullPath'];
        $external_url = $fileinfo['ulf_ExternalFileReference'];
        $originalFileName = $fileinfo['ulf_OrigFileName'];
        $fileSize = $fileinfo['ulf_FileSizeKB'];
        $file_nonce = $fileinfo['ulf_ObfuscatedFileID'];
                    
        $file_URL   = HEURIST_BASE_URL.'?db='.$this->system->dbname()."&file=$file_nonce"; //download
        
        $link = '<a target="_surf" href="'.htmlspecialchars($external_url?$external_url:$file_URL).'">';

        $link .= '<span style="padding-left: 16px;background-image: url('  //class="external-link" 
                .HEURIST_BASE_URL.'hclient/assets/external_link_16x16.gif);vertical-align: bottom;"></span>';
        if(strpos($originalFileName, ULF_IIIF)===0){
            $link .= '<img src="'.HEURIST_BASE_URL.'hclient/assets/iiif_logo.png" style="width:16px"/>';
            $originalFileName = null;
        }

        $link .= '<span>'.htmlspecialchars(($originalFileName && $originalFileName!=ULF_REMOTE)
                            ?$originalFileName
                            :($external_url?$external_url:$file_URL)).'</span></a> '
                .($fileSize>0?'[' .htmlspecialchars($fileSize) . 'kB]':'');
        
        return $link;
    }

//-----------------------------------------------------------    
protected $interruptEnabled = false;
protected $interruptSessionId = '';
protected $interruptStartTs = 0.0;
protected $interruptTimeoutSec = 0;
protected $interruptLastPollTs = 0.0;
protected $interruptPollIntervalSec = 0.5; // 500ms
protected $done = 0;
protected $total = 0;
protected $mainRecordSet = [];

public function startInterrupt($sessionId, $mainRecordSet, $timeoutSec = 60) {
    if (empty($sessionId)) return;
    $this->interruptEnabled = true;
    $this->interruptSessionId = $sessionId;
    $this->interruptStartTs = microtime(true);
    $this->interruptTimeoutSec = max(1, (int)$timeoutSec);
    $this->interruptLastPollTs = 0.0;
    $this->done = 0;
    $this->mainRecordSet = $mainRecordSet ?? [];
    $this->total = (int)count($mainRecordSet);

    // immediate check (fast fail)
    $this->tickInterrupt('start');
}

protected function tickInterrupt($phase = 'tick') {
    if (!$this->interruptEnabled) return;

    $now = microtime(true);

    // timeout check always
    if ($this->interruptTimeoutSec > 0 && ($now - $this->interruptStartTs) > $this->interruptTimeoutSec) {
        throw new ReportTerminatedException('timeout');
    }

    // throttle DB polling
    if ($this->interruptLastPollTs > 0 && ($now - $this->interruptLastPollTs) < $this->interruptPollIntervalSec) {
        return;
    }
    $this->interruptLastPollTs = $now;

    $session_val = $this->done . ',' . $this->total;
    $current_val = mysql__update_progress(null, $this->interruptSessionId, false, $session_val);

    if ($current_val === 'terminate') {
        mysql__update_progress(null, $this->interruptSessionId, false, ''); // clear if you want
        throw new ReportTerminatedException('terminate');
    }
}
    
    
}
