<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* recordTitleMask.php
*
* static class with
* Three MAIN methods
*
*   check($mask, $rt) => returns an error string if there is a fault in the given mask for the given record type
*   fill($mask, $rec_id, $rt) => returns the filled-in title mask for this record entry
*   execute($mask, $rt, $mode, $rec_id=null) => converts titlemask to coded, humanreadable or fill mask with values
*
*
* Note that title masks have been updated (Artem Osmakov late 2013) to remove the awkward storage of two versions - 'canonical' and human readable.
* They are now read and used as internal code values (the old 'canonical' form), decoded to human readable for editing,
* and then recoded back to internal codes for storage, as per original design.
*
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @link        https://HeuristNetwork.org
* @version     3.1.6
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  CommonPHP
*/

use hserv\utilities\USystem;
use hserv\utilities\Temporal;


define('ERROR_REP_WARN', 0);// returns general message that titlemask is invalid - default
define('ERROR_REP_MSG', 1);// returns detailed error message
define('ERROR_REP_SILENT', 2);// returns empty string

define('TITLEMASK_ERROR_MSG', 'Invalid title mask: please define the title mask in record structure editor');
define('TITLEMASK_ERROR_MSG2', 'Error in title mask. Please look for syntax errors or special characters. '
.'If the problem is not clear, please rebuild the mask one field at a time and let the Heurist team know which field causes the problem so we can fix it');

define('TITLEMASK_EMPTY_MSG', '**** No data in title fields for this record ****');

/**
 * Static class for handling Heurist record title masks.
 *
 * Provides methods to check the validity of a title mask, fill a mask with record data
 * to generate a title, and convert masks between internal coded format and human-readable format.
 * Title masks allow dynamic generation of record titles based on field values and static text.
 */
class TitleMask {

    /**
     * Private constructor to prevent instantiation, as this is a static class.
     */
    private function __construct() {}

    /** @var \hserv\System|null The Heurist system object, initialized by `initialize()`. */
    private static $system = null;
    /** @var \mysqli|null The mysqli database connection object, initialized by `initialize()`. */
    private static $mysqli = null;
    /** @var int The registered ID of the current database, initialized by `initialize()`. */
    private static $db_regid = 0;
    /** @var bool Flag indicating whether the class has been initialized. */
    private static $initialized = false;

    /** @var array|null Stores field correspondence mappings, used during import processes. */
    private static $fields_correspondence = null;
    /** @var array|null Cache for detail type definitions, indexed by ID, name, and concept code. */
    private static $rdt = null;
    /** @var array|null Cache for record detail type structures (from defRecStructure), indexed by record type ID. */
    private static $rdr = null;
    /** @var array|null Cache for record data, indexed by record ID. */
    private static $records = null;

    /** @var string|null Stores the title mask currently being checked or processed. */
    private static $provided_mask = null;

    /**
     * Initializes the TitleMask static class with necessary system context.
     *
     * This method must be called before any other static methods of this class are used.
     * It sets up the system object, mysqli connection, database registration ID,
     * and defines the DT_PARENT_ENTITY constant if available.
     *
     * @param \hserv\System|null $_system Optional. The Heurist system object. If null, it will try to use a global $system variable.
     * @return void
     */
    public static function initialize($_system=null)
    {

        if (self::$initialized) {return;}

        if(isset($_system)){
            self::$system = $_system;
        }else{
            global $system;
            self::$system = $system;
        }
        
        self::$mysqli = self::$system->getMysqli();
        self::$db_regid = self::$system->settings->get('sys_dbRegisteredID');
        self::$initialized = true;

        self::$system->defineConstant('DT_PARENT_ENTITY');
    }

    /**
     * Sets the field correspondence map, used for title mask generation during imports.
     *
     * This allows mapping source field identifiers to target Heurist field identifiers
     * when a title mask from a source system refers to fields by different IDs.
     *
     * @param array|null $fields_correspondence An associative array mapping source field IDs to target field IDs.
     * @return void
     */
    public static function set_fields_correspondence($fields_correspondence){
        self::$fields_correspondence = $fields_correspondence;
    }

/**
 * Checks if a given title mask string is well-formed for a specific record type.
 *
 * It uses `TitleMask::execute()` in mode 1 (convert to coded format) and checks for errors.
 *
 * @param string $mask The title mask string to check.
 * @param int $rt The record type ID for which the mask is intended.
 * @param bool $checkempty If true, an empty mask or a mask without any field placeholders `[field]`
 *                         will be considered an error.
 * @return string An empty string if the mask is valid, or an error message string if faults are found.
 */
 public static function check($mask, $rt, $checkempty) {

    self::initialize();
    // \[([^]]+)\]  - works in php     \[([^\]]+)\] - is js

    if (! preg_match_all('/\\[\\[|\\]\\]|\\[\\s*([^]]+)\\s*\\]/', $mask, $matches))
    {
        // no substitutions to make
        return $checkempty?'Title mask must have at least one data field ( in [ ] ) to replace':'';
    }

    self::$provided_mask = $mask;

    $res = self::execute($mask, $rt, 1, null, ERROR_REP_MSG);
    if(is_array($res)){
        return $res[0];// mask is invalid - this is error message
    }else{
        return "";
    }
}

/**
 * Fills a title mask with values from a specific record to generate the record's title.
 *
 * If `$mask` is not provided, it fetches the `rty_TitleMask` for the record's type.
 * It then calls `TitleMask::execute()` in mode 0 to substitute field placeholders with actual values.
 *
 * @param int $rec_id The ID of the record whose title is to be generated.
 * @param string|null $mask Optional. The title mask string. If null, it's retrieved from the record type definition.
 * @return string The generated title string, or an error message if the record is not found or the mask is invalid.
 */
public static function fill($rec_id, $mask=null){

    self::initialize();

    $rec_value = self::__get_record_value($rec_id, true);
    if($rec_value){
        if($mask==null){
            $mask = $rec_value['rty_TitleMask'];
        }
        $rt = $rec_value['rec_RecTypeID'];
        return self::execute($mask, $rt, 0, $rec_id, ERROR_REP_WARN);
    }else{
        return "Title mask not generated. Record ".$rec_id." not found";
    }
}

/*
* Converts titlemask to coded, human readable or fill mask with values
* In case of invalid titlemask it returns either general warning, error message or empty string (see $rep_mode)
*
* @param string $mask The title mask string.
* @param int $rt The record type ID.
* @param int $mode The execution mode:
*                  - 0: Fill coded mask with values from `$rec_id` to produce the final title.
*                  - 1: Convert human-readable mask to internal coded format (field names to concept codes/IDs).
*                  - 2: Convert internal coded mask to human-readable format (concept codes/IDs to field names).
*                  - 3: Fill human-readable mask with values (effectively mode 1 then mode 0).
* @param int|null $rec_id The record ID, required if mode is 0 or 3.
* @param int $rep_mode Error reporting mode:
*                      - `ERROR_REP_WARN` (0): Return a generic error message (default).
*                      - `ERROR_REP_MSG` (1): Return a detailed error message.
*                      - `ERROR_REP_SILENT` (2): Return an empty string on error (or default title if mode 0).
* @return string|array The processed title mask string, the generated title, or an error array (if mode 1 or 2 fails with $rep_mode=ERROR_REP_MSG).
*                      If mode 0 and all fields are blank, returns a default "no data" message or result of `__get_forempty`.
*/
public static function execute($mask, $rt, $mode, $rec_id=null, $rep_mode=ERROR_REP_WARN) {

    self::initialize();

    if(self::$fields_correspondence!=null){
        self::$rdr = null;
    }

    if($rec_id){
        self::__get_record_value($rec_id, true);//keep recvalue in static
    }

    if (!$mask) {
        $ret = ($rep_mode!=ERROR_REP_SILENT)?"Title mask is not defined": ($mode==0?self::__get_forempty($rec_id, $rt):"");
        return $ret;
    }

    if($mode==3){
        //get value from human readable
        //execute($mask, $rt, $mode, $rec_id=null, $rep_mode=ERROR_REP_WARN)
        $res = self::execute($mask, $rt, 1, $rec_id, ERROR_REP_MSG);
        if (is_array($res)) {
            return $res[0];
        }else{
            return self::execute($res, $rt, 0, $rec_id, ERROR_REP_MSG);
        }
    }


    //find inside brackets
    if (! preg_match_all('/\s*\\[\\[|\s*\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches)){
        return $mask;    // nothing to do -- no substitutions
    }

    $replacements = array();
    $len = count($matches[1]);
    $fields_err = 0;
    $fields_blank = 0;
    for ($i=0; $i < $len; ++$i) {
        /* $matches[3][$i] contains the field name as supplied (the string that we look up),
        * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
        *        (this is what we replace if there is a substitution)
        * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
        *        (this is what we replace with an empty string if there is no substitution value available)
        */

        if(!trim($matches[3][$i])) {continue;} //empty []

        $value = self::__fill_field($matches[3][$i], $rt, $mode, $rec_id);

        if(is_array($value)){
            //ERROR
            if($rep_mode==ERROR_REP_WARN){
                return TITLEMASK_ERROR_MSG;
            }elseif($rep_mode==ERROR_REP_MSG){
                return $value;
            }else{
                $replacements[$matches[1][$i]] = "";
                $fields_err++;
            }
        }elseif (null==$value || trim($value)==""){
            $replacements[$matches[1][$i]] = "";
            $fields_blank++;

        }else{
            if($mode==0){ //value
                $replacements[$matches[2][$i]] = $value;
            }else{ //coded or human readable
                $replacements[$matches[2][$i]] = "[$value]";
            }
        }
    }

    if($mode==0){
        if($fields_err==$len){
            return self::__get_forempty($rec_id, $rt);
        }
        $replacements['[['] = '[';
        $replacements[']]'] = ']';

        // Check if there are any conditional parts in the title mask
        /* Two versions:
         * Old format: Checks if the preceeding field has a value, if it has a value append it to the first section; otherwise print the second section or remove completely
         * New format: The conditional field/s are placed with the output string, e.g. {\Full name: [Given name] [Last name] \...},
         *              all fields needs to have a value to print out that string, a section without a field (except the first section) will be printed out if reached
         */
        if(preg_match_all("/(?:\[[^\[\]]+?\])?\s?{\d*\s?(?:\\\\[^\\\\\}]*\s?)*}/", $mask, $conditions_mask)){ // get all conditional strings

            foreach ($conditions_mask[0] as $key => $cond_str) {

                $cond_field = array();
                $cond_mask = array();
                $cond_replace = null;
                $str_maxlen = 0;

                // retrieve conditional sections
                preg_match("/{\d*\s?(?:\\\\[^\\\\]*\s?)*}/", $cond_str, $cond_mask);

                $cond_mask[0] = trim($cond_mask[0], ' {}');// remove curly brackets

                $cond_parts = mb_split("\\\\", $cond_mask[0]);
                if(is_numeric(trim($cond_parts[0])) || empty($cond_parts[0])){
                    $str_maxlen = intval($cond_parts[0]);
                    array_shift($cond_parts);
                }

                if(strpos($cond_parts[0], '[') !== false && strpos($cond_parts[0], ']') !== false){ // new method

                    foreach ($cond_parts as $cond_part) { // process each section, checking for each field value
                        preg_match_all("/(?:\[[^\[\]]+?\])+/", $cond_part, $cond_fields);

                        $is_valid = true;
                        foreach ($cond_fields[0] as $cond_field) {
                            if(!array_key_exists($cond_field, $replacements)){
                                $is_valid = false;
                                break;
                            }

                            $new_str = ($str_maxlen > 0 && mb_strlen($replacements[$cond_field]) > $str_maxlen ? mb_substr($replacements[$cond_field], 0, $str_maxlen) . '...' : $replacements[$cond_field]);
                            $cond_part = mb_eregi_replace(preg_quote($cond_str, "/"), $new_str, $cond_part);
                        }

                        if($is_valid){
                            $cond_replace = $cond_part;
                            break;
                        }
                    }

                    if($cond_replace === null){ // default, replace with empty
                        $cond_replace = '';
                    }
                }elseif(count($cond_parts) == 1 || count($cond_parts) == 2){ // original method

                    // retrieve proceeding field
                    preg_match("/\[[^\[\]]+?\]/", $cond_str, $cond_field);
                    $new_str = array_key_exists($cond_field[0], $replacements) ? $replacements[$cond_field[0]] : '';

                    if(!empty($new_str)){
                        $cond_replace = $cond_parts[0] . ' ' . ($str_maxlen > 0 && mb_strlen($new_str) > $str_maxlen ? mb_substr($new_str, 0, $str_maxlen) . '...' : $new_str);
                    }elseif(empty($cond_parts[1])){
                        $cond_replace = '';
                    }else{
                        $cond_replace = $cond_parts[1];
                    }
                }elseif(empty($cond_parts) && $str_maxlen > 0){

                    // retrieve proceeding field
                    preg_match("/\[[^\[\]]+?\]/", $cond_str, $cond_field);
                    $new_str = array_key_exists($cond_field[0], $replacements) ? $replacements[$cond_field[0]] : '';

                    $cond_replace = !empty($new_str) && mb_strlen($new_str) > $str_maxlen ? mb_substr($new_str, 0, $str_maxlen) . '...' : $new_str;
                }

                if($cond_replace !== null){ // replace part
                    $mask = mb_eregi_replace(preg_quote($cond_str, "/"), $cond_replace, $mask);
                }
            }
        }
    }

    $title = array_str_replace(array_keys($replacements), array_values($replacements), $mask);

    if($mode==0){  //fill the mask with values


        if($fields_blank==$len && $rec_id){ //If all the title mask fields are blank
            $title =  "Record ID $rec_id - no data has been entered in the fields used to construct the title";
        }

        /* Clean up miscellaneous stray punctuation &c. */
        if (! preg_match('/^\\s*[0-9a-z]+:\\S+\\s*$/i', $title)) {    // not a URI

            $puncts = '-:;,.@#|+=&(){}'; // These are stripped from begining and end of title
            $punctsNoFullstops = '-:;,@#|+=&(){}'; // as above, minus fullstops
            $puncts2 = '-:;,@#|+=&'; // as above, minus brackets

            $regex_ = '/\\s]*(.*?)[';
            $regex_2 = '!\\([';

            $title = preg_replace('!^['.$puncts.$regex_.$puncts2.'/\\s]*$!s', '\\1', $title);// remove leading and trailing punctuation
            $title = preg_replace($regex_2.$puncts.'/\\s]+\\)!s', '', $title);// remove brackets containing only punctuation
            $title = preg_replace($regex_2.$puncts.$regex_.$puncts2.'/\\s]*\\)!s', '(\\1)', $title);// remove leading and trailing punctuation within brackets
            $title = preg_replace($regex_2.$puncts.'/\\s]*\\)|\\[['.$punctsNoFullstops.'/\\s]*\\]|\\[[.]{,2}\\]|\\[[.]{4,}\\]!s', '', $title);// remove brackets containing only punctuation
            $title = preg_replace('!^['.$puncts.$regex_.$puncts2.'/\\s]*$!s', '\\1', $title);// remove leading and trailing punctuation
            $title = preg_replace('!,\\s*,+!s', ',', $title);// replace commas with nothing between them, e.g. "Hello, , World" => "Hello, World"
            $title = preg_replace('!\\s+,!s', ',', $title);// remove leading spaces before comma, e.g. "Hello    , World" => "Hello, World"
        }
        $title = trim(preg_replace('!  +!s', ' ', $title));//remove double spaces

        if($title==""){

            if($rep_mode==ERROR_REP_SILENT){
                $title = self::__get_forempty($rec_id, $rt);
            }elseif($rep_mode==ERROR_REP_MSG){
                return array(TITLEMASK_EMPTY_MSG);
            }else{
                return TITLEMASK_EMPTY_MSG;
            }
        }
    }

    return $title;
}

//-------------- private methods -----------------

/**
 * Generates a default title if the title mask is blank or contains no valid fields.
 *
 * The default title is constructed using the values of the first three non-empty,
 * non-memo data fields, truncated to 40 characters each, and separated by pipe symbols.
 * If no such data is found, a generic "Record ID X - no data..." message is returned.
 *
 * @param int $rec_id The record ID.
 * @param int $rt The record type ID.
 * @return string The generated default title.
 */
private static function __get_forempty($rec_id, $rt){

    $rdr = self::__get_rec_detail_types($rt);
    //$rec_values = self::__get_record_value($rec_id);

    $allowed = array('freetext', 'enum', 'float', 'date', 'relmarker', 'integer', 'year', 'boolean');
    $cnt = 0;
    $title = array();
    foreach($rdr as $dt_id => $detail){
        if( is_numeric($dt_id) && in_array($detail['dty_Type'], $allowed) && $detail['rst_RequirementType']!='forbidden'){
            $val = self::__get_field_value($dt_id, $rt, 0, $rec_id);
            $val = trim(mb_substr($val,0,40));
            if($val){
                array_push($title, $val);
                $cnt++;
                if($cnt>2) {break;}
            }
        }
    }
    $title = implode("|", $title);
    if(!$title){
        $title =  "Record ID $rec_id - no data has been entered in the fields used to construct the title";
    }
    return $title;
}


/**
 * Retrieves all detail type definitions and caches them.
 *
 * Fetches from `defDetailTypes` and stores them in `self::$rdt`, indexed by
 * `dty_ID`, lowercase `dty_Name`, and `dty_ConceptCode`.
 *
 * @return array The cached array of all detail type definitions.
 */
private static function __get_detail_types() {

    if (! self::$rdt) {
        self::$rdt = array();

        $res = self::$mysqli->query('select dty_ID, lower(dty_Name) as dty_Name, dty_Name as originalName, dty_Type, '
            .' dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs, dty_Name as dty_NameOrig, '
            .' dty_OriginatingDBID, dty_IDInOriginatingDB from defDetailTypes');

        if ($res){
            while ($row = $res->fetch_assoc()) {

                if (is_numeric($row['dty_OriginatingDBID']) && $row['dty_OriginatingDBID']>0 &&
                is_numeric($row['dty_IDInOriginatingDB']) && $row['dty_IDInOriginatingDB']>0) {
                    $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
                } elseif (self::$db_regid>0) {
                    $dt_cc = "" . self::$db_regid . "-" . $row['dty_ID'];
                } else {
                    $dt_cc = $row['dty_ID'];
                }

                $row['dty_ConceptCode'] = $dt_cc;

                self::$rdt[$row['dty_ID']] = $row;
                self::$rdt[$row['dty_Name']] = $row;
                self::$rdt[$dt_cc] = $row;
            }
            $res->close();
        }
    }

    return self::$rdt;
}

/**
 * Retrieves the record structure (fields) for a given record type and caches it.
 *
 * Fetches from `defRecStructure` joined with `defDetailTypes`.
 * Stores the result in `self::$rdr[$rt]`, indexed by `dty_ID`,
 * lowercase `rst_DisplayName`, and `dty_ConceptCode`.
 *
 * @param int $rt The record type ID.
 * @return array The cached array of field definitions for the specified record type.
 */
private static function __get_rec_detail_types($rt) {

    if (!self::$rdr) {
        self::$rdr = array();
    }

    if(!@self::$rdr[$rt]){

        //dty_Name as dty_NameOrig,

        $query ='select rst_RecTypeID, '
        .' lower(rst_DisplayName) as rst_DisplayName, rst_DisplayName as originalName, '   //lower(dty_Name) as dty_Name,
        .' dty_Type, if(rst_PtrFilteredIDs,rst_PtrFilteredIDs, dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs,'
        .' dty_OriginatingDBID, dty_IDInOriginatingDB, dty_ID, rst_RequirementType '
        .' from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID '
        .SQL_WHERE//since 2017-11-25 rst_RequirementType in ("required", "recommended", "optional") and '
        .' rst_RecTypeID='.intval($rt)
        .' order by rst_DisplayOrder';

        $res = self::$mysqli->query($query);

        if($res){
            self::$rdr[$rt] = array();
            while ($row = $res->fetch_assoc()) {

                if (is_numeric($row['dty_OriginatingDBID']) && $row['dty_OriginatingDBID']>0 &&
                is_numeric($row['dty_IDInOriginatingDB']) && $row['dty_IDInOriginatingDB']>0) {

                    $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
                } elseif (self::$db_regid>0) {
                    $dt_cc = "" . self::$db_regid . "-" . $row['dty_ID'];
                } else {
                    $dt_cc = $row['dty_ID'];
                }

                $row['dty_ConceptCode'] = $dt_cc;

                $fld_name_idx = mb_eregi_replace("/\s{2,}/", " ", $row['rst_DisplayName']);// remove double spacing from field name used for indexing

                //keep 3 indexes by id, name and concept code
                self::$rdr[$rt][$row['dty_ID']] = $row;
                self::$rdr[$rt][$fld_name_idx] = $row;
                self::$rdr[$rt][$dt_cc] = $row;
            }
            $res->close();
        }
    }
    return self::$rdr[$rt];

}

/**
 * Retrieves IDs of records related via a specific 'relmarker' (relationship marker) field.
 *
 * Considers both direct and reverse relationships based on `recLinks`.
 * Filters by relation types (terms under the field's vocabulary) and record type
 * constraints defined for the relmarker field.
 *
 * @param int $rec_id The ID of the source/target record.
 * @param int $dty_ID The detail type ID of the 'relmarker' field.
 * @return array An array of related record IDs.
 */
private static function __get_related_record_ids($rec_id, $dty_ID) {

    //1. find all relation types
    $vocab_id = mysql__select_value(self::$mysqli,
        'SELECT dty_JsonTermIDTree FROM defDetailTypes WHERE dty_ID='.$dty_ID);

    $reltypes = null;
    if($vocab_id>0){
        $reltypes = getTermChildrenAll(self::$mysqli, $vocab_id, true);
        if(count($reltypes)==1){
            $reltypes = '='.$reltypes[0];
        }else{
            $reltypes = ' IN ('.implode(',',$reltypes).')';
        }
    }
    //2. find rectype constraints
    $constr_ids = mysql__select_value(self::$mysqli,
        'SELECT dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_ID='.$dty_ID);
    $constr_ids = prepareIds($constr_ids);
    if(count($constr_ids)==1){
        $constr_ids = '='.$constr_ids[0];
    }elseif(count($constr_ids)>1){
        $constr_ids = ' IN ('.implode(',',$constr_ids).')';
    }else{
        $constr_ids = null;
    }

    //direct
    $query = 'SELECT rl_TargetID  as record_ID '
        .'FROM recLinks, Records WHERE rl_SourceID='.$rec_id
        .' AND rl_TargetID=rec_ID AND rec_FlagTemporary=0 ';
    if($reltypes){
        $query = $query.' AND rl_RelationTypeID'.$reltypes;
    }
    if($constr_ids){
        $query = $query.' AND rec_RecTypeID'.$constr_ids;
    }

    //reverse
    $query = $query.' UNION '
        .'SELECT rl_SourceID as record_ID '
        .'FROM recLinks, Records WHERE rl_TargetID='.$rec_id
        .' AND rl_SourceID=rec_ID AND rec_FlagTemporary=0 ';
    if($reltypes){
        $query = $query.' AND rl_RelationTypeID'.$reltypes;
    }
    if($constr_ids){
        $query = $query.' AND rec_RecTypeID'.$constr_ids;
    }

    $record_ids = mysql__select_list2(self::$mysqli, $query);

    return $record_ids;
}

/**
 * Retrieves and caches the data for a specific record, including its header and details.
 *
 * Fetches from `Records` and `recDetails` tables. Skips forbidden fields.
 * Caches results in `self::$records` to avoid redundant queries for the same record.
 *
 * @param int $rec_id The ID of the record to retrieve.
 * @param bool $reset If true, forces a refresh of the cache for this record. Defaults to false.
 * @return array|null The record data as an associative array, or null if not found.
 *                    The array includes 'rec_ID', 'rec_Title', 'rec_Modified', 'rec_RecTypeID',
 *                    'rty_Name', 'rty_TitleMask', and 'rec_Details' (an array of detail rows).
 */
private static function __get_record_value($rec_id, $reset=false) {

/*
    $memory_limit = USystem::getConfigBytes('memory_limit');
    $mem_used = memory_get_usage();
    if($mem_used>$memory_limit-104857600){ //100M

    }
*/
    //if not reset it leads to memory exhaustion
    //$reset = true;
    if ($reset || !is_array(self::$records) || count(self::$records)>1000) {
        self::$records = array();
    }

    if(@self::$records[$rec_id]){
        return self::$records[$rec_id];
    }

        $ret = null;

        $query = 'SELECT rec_ID, rec_Title, rec_Modified, rec_RecTypeID, rty_Name, rty_TitleMask '
                    .'FROM Records, defRecTypes where rec_RecTypeID=rty_ID and rec_ID='.intval($rec_id);
        $res = self::$mysqli->query($query);
        if($res){
            $row = $res->fetch_assoc();
            if($row){

                $ret = $row;
                $ret['rec_Details'] = array();

                //trim(substr(dtl_Value,0,300)) as
                $query = 'SELECT dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, rst_RequirementType '
                .'FROM recDetails LEFT JOIN defRecStructure '
                .'ON rst_RecTypeID='.intval($ret['rec_RecTypeID'])
                   .' AND rst_DetailTypeID=dtl_DetailTypeID '
                   .' WHERE dtl_RecID='.intval($rec_id)." ORDER BY dtl_DetailTypeID, dtl_ID";
                $res2 = self::$mysqli->query($query);
                while ($row = $res2->fetch_assoc()){
                    if($row['rst_RequirementType']!='forbidden'){
                        array_push($ret['rec_Details'], $row);
                    }
                }
                $res2->close();
            }
            $res->close();
        }

        self::$records[$rec_id] = $ret;

    return self::$records[$rec_id];
}

/**
 * Retrieves a specific attribute of an enumeration term (e.g., label, code, concept ID).
 *
 * If 'label' is requested and the term has a parent, it constructs a hierarchical label
 * (e.g., "Parent.Child.Grandchild").
 *
 * @param int $enum_id The ID of the term.
 * @param string|null $enum_param_name The attribute to retrieve:
 *                                     'label' or 'term' (default): The term's label (potentially hierarchical).
 *                                     'id' or 'internalid': The term's `trm_ID`.
 *                                     'code': The term's `trm_Code`.
 *                                     'conceptid': The term's `trm_ConceptID`.
 * @return string|null The requested term attribute value, or null if not found.
 */
private static function __get_enum_value($enum_id, $enum_param_name)
{

    if($enum_param_name==null || strcasecmp($enum_param_name,'term')==0){
        $enum_param_name = "label";
    }elseif(strcasecmp($enum_param_name,'internalid')==0){
        $enum_param_name = "id";
    }

    $ress = self::$mysqli->query('select trm_id, trm_label, trm_code, '
    .'concat(trm_OriginatingDBID, \'-\', trm_IDInOriginatingDB) as trm_conceptid, trm_parenttermid from defTerms where trm_ID = '.intval($enum_id));
    if(!$ress){
        return null;
    }


        $relval = $ress->fetch_assoc();
        $ress->close();

        $get_param = mb_strtolower($enum_param_name, 'UTF-8');

        // If trm_label then construct is: "branch_trm_label. ... .leaf_term_label", ignore root label
        if(!(strcasecmp($get_param, 'label') == 0 && @$relval['trm_parenttermid'] > 0 && $relval['trm_label'] != null)){

            return @$relval['trm_'.$get_param];

        }

        $ret = null;

        $trm_id = @$relval['trm_parenttermid'];
        $ret = @$relval['trm_label'];

        while(1){

            $parent_ress = self::$mysqli->query("select trm_label, trm_ParentTermID from defTerms where trm_ID = " . intval($trm_id));

            if(!$parent_ress){
                break;
            }

            $parent_trm = $parent_ress->fetch_assoc();
            if($parent_trm == null || $parent_trm['trm_ParentTermID'] == null || $parent_trm['trm_ParentTermID'] == 0){
                $parent_ress->close();
                break;
            }

            $ret = $parent_trm['trm_label'] . "." . $ret;

            $trm_id = $parent_trm['trm_ParentTermID'];

            $parent_ress->close();
        }//while

        return $ret;
}

/**
 * Gets the display name for an uploaded file.
 *
 * If the file is external (ULF_REMOTE), returns its `ulf_ExternalFileReference`.
 * Otherwise, returns `ulf_OrigFileName`.
 *
 * @param int $ulf_ID The ID of the uploaded file (`recUploadedFiles.ulf_ID`).
 * @return string The filename or URL, or an empty string if not found.
 */
private static function __get_file_name($ulf_ID){

    if($ulf_ID>0){
        $fileinfo = fileGetFullInfo(self::$system, $ulf_ID);
        if(!isEmptyArray($fileinfo)){
            return $fileinfo[0]['ulf_OrigFileName'] == ULF_REMOTE ?
                    $fileinfo[0]['ulf_ExternalFileReference'] : $fileinfo[0]['ulf_OrigFileName'];
            //  array("file" => $fileinfo[0], "fileid"=>$fileinfo[0]["ulf_ObfuscatedFileID"]);
        }
    }
    return '';
}


/*
* Returns value for given detail type
*
* @param mixed $rdt_id - detail type id
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param mixed $rec_id - record id for value mode
* @param mixed $enum_param_name - name of term field for value mode
* @return mixed
*/
private static function __get_field_value( $rdt_id, $rt, $mode, $rec_id, $enum_param_name=null) {

    if($mode==0){

        $local_dt_id = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_ID');//local dt id
        $dt_type = '';
        if($local_dt_id>0){
            $dt_type = self::__get_dt_field($rt, $local_dt_id, $mode, 'dty_Type');
        }
        if($dt_type=='relmarker'){
            //find related record id
            $res = self::__get_related_record_ids($rec_id, $local_dt_id);

        }else{

            $rec_values = self::__get_record_value($rec_id);

            if(!$rec_values){
                return "";
            }elseif (strcasecmp($rdt_id,'id')==0){
                return $rec_values['rec_ID'];
            }elseif (strcasecmp($rdt_id,'rectitle')==0) {
                return $rec_values['rec_Title'];
            }elseif (strcasecmp($rdt_id,'rectypeid')==0) {
                return $rec_values['rec_RecTypeID'];
            }elseif (strcasecmp($rdt_id,'rectypename')==0) {
                return $rec_values['rty_Name'];
            }elseif (strcasecmp($rdt_id,'modified')==0) {
                return $rec_values['rec_Modified'];
            }

            $details = $rec_values['rec_Details'];
            $rdt_id = $local_dt_id;

            //dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, rst_RequirementType
            $res = array();
            $found = false;
            foreach($details as $detail){
                if($detail['dtl_DetailTypeID']==$rdt_id){
                    $found = true;
                    if($dt_type=="enum" || $dt_type=="relationtype"){
                        $value = self::__get_enum_value($detail['dtl_Value'], $enum_param_name);
                    }elseif($dt_type=='date'){
                        $value = Temporal::toHumanReadable(trim($detail['dtl_Value']));
                    }elseif($dt_type=="file"){
                        $value = self::__get_file_name(intval($detail['dtl_UploadedFileID']));
                    }elseif($dt_type=='freetext' || $dt_type=='blocktext'){
                        list(, $value) = extractLangPrefix($detail['dtl_Value']);// remove possible language prefix
                    }else{
                        $value = $detail['dtl_Value'];
                    }
                    if($value!=null && $value!=''){
                        array_push($res, $value);
                    }
                }elseif($found){
                    break;
                }
            }

        }

        if(empty($res)){
            return "";
        /*}elseif($dt_type == 'file'){
            return count($res)." file".(count($res)>1?"s":"");*/
        }elseif($dt_type == 'geo') {
            return count($res)." geographic object".(count($res)>1?"s":"");
        }else{
            return implode(",", $res);
        }

    }else{

        if (strcasecmp($rdt_id,'id')==0 ||
        strcasecmp($rdt_id,'rectitle')==0 ||
        strcasecmp($rdt_id,'modified')==0){
            return $rdt_id;
        }elseif($mode==1){ //convert to
            return $rdt_id; //concept code
        } else {
            return self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');//original name (with capital chars)
        }
    }
}

/**
 * Retrieves a specific attribute of a detail type definition.
 *
 * Searches first within the context of a specific record type's structure (`self::$rdr`),
 * then falls back to the global list of detail types (`self::$rdt`) if not found in the structure
 * (and mode is not 1, which implies strict structure adherence for coded masks).
 * Handles special "Parent Entity" field name.
 *
 * @param int $rt The current record type ID (context for `rst_DisplayName`).
 * @param string $search_fieldname The identifier to search for (can be dty_ID, rst_DisplayName, or dty_ConceptCode).
 * @param int $mode The current processing mode (affects fallback search behavior).
 * @param string $result_fieldname The name of the attribute to return from the detail type definition
 *                                 (e.g., 'dty_ConceptCode', 'dty_Type', 'originalName'). Defaults to 'dty_ConceptCode'.
 * @return mixed|null The value of the requested attribute, or null if the field is not found.
 */
private static function __get_dt_field($rt, $search_fieldname, $mode, $result_fieldname='dty_ConceptCode'){

    $rdr = self::__get_rec_detail_types($rt);

    $search_fieldname = mb_strtolower($search_fieldname, 'UTF-8');
    //$search_fieldname = strtolower($search_fieldname);

    if(self::_is_parent_entity($search_fieldname)){

        if (defined('DT_PARENT_ENTITY')){
            $rdt = self::__get_detail_types();
            if(@$rdt[DT_PARENT_ENTITY]){
                return $rdt[DT_PARENT_ENTITY][$result_fieldname];
            }
        }else{
            return '';
        }
    }elseif(@$rdr[$search_fieldname]){  //search by dty_ID, rst_DisplayName, dty_ConceptCode
        //search in record type structure
        return $rdr[$search_fieldname][$result_fieldname];
    }elseif($mode!=1) { //allow to search among all fields
        //if not found in structure - search among all detail types
        $rdt = self::__get_detail_types();
        if(@$rdt[$search_fieldname]){
            return $rdt[$search_fieldname][$result_fieldname];
        }
    }
    return null;
}

/**
 * Retrieves record type information (ID, concept code, name) by various identifiers.
 *
 * Searches `defRecTypes` by:
 * 1. Concept code (e.g., "DBID-RTID").
 * 2. Record type ID (`rty_ID`).
 * 3. Record type name (`rty_Name`, case-insensitive).
 *
 * @param string|int $rt_search The identifier to search for.
 * @return array An array `[rty_ID, rty_ConceptCode, rty_Name]`. Returns `[0, '', '']` if not found.
 */
private static function __get_rt_id( $rt_search ){

        $query = 'SELECT rty_ID, rty_Name, rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes where ';
        $where = '';

        $pos = mb_strpos($rt_search,'-');
        if ($pos>0){
            $db_oid = mb_substr($rt_search,0,$pos);
            $oid = mb_substr($rt_search,$pos+1);
            if(is_numeric($db_oid) && $db_oid>=0 && is_numeric($oid) && $oid>0){
                $where = 'rty_OriginatingDBID ='.$db_oid
                    .' AND rty_IDInOriginatingDB ='.$oid;
            }
        }
        $params = null;
        if($where==''){
            if($rt_search>0){
                $params = array('i',intval($rt_search));
                $where = 'rty_ID=?';
            }else{
                $params = array('s', mb_strtolower($rt_search, 'UTF-8'));
                $where = 'LOWER(rty_Name)=?';
            }
        }
        $query = $query . $where;

        $res = mysql__select_param_query(self::$mysqli, $query, $params);

        if(!$res){
            return array(0, '', '');

        }

        $row = $res->fetch_assoc();
        $res->close();
        if(!$row){
            return array(0, '', '');
        }

        if (is_numeric($row['rty_OriginatingDBID']) && $row['rty_OriginatingDBID']>0 &&
        is_numeric($row['rty_IDInOriginatingDB']) && $row['rty_IDInOriginatingDB']>0) {
            $rt_cc = "" . $row['rty_OriginatingDBID'] . "-" . $row['rty_IDInOriginatingDB'];
        } elseif (self::$db_regid>0) {
            $rt_cc = "" . self::$db_regid . "-" . $row['rty_ID'];
        } else {
            $rt_cc = $row['rty_ID'];
        }
        return array($row['rty_ID'], $rt_cc, $row['rty_Name']);
}

/*
* replace title mask tag to value, coded (concept codes) or textual representation
*
* @param mixed $field_name - mask tag
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param $rec_id - record id for value mode
* @return mixed
*/
private static function __fill_field($field_name, $rt, $mode, $rec_id=null) {

    if (is_array($rt)){
        //ERROR
        return array("Field name '$field_name' was tested with Array of record types - bad parameter");
        // TODO: what does this error message mean? Make it comprehensible to the user
    }

    if(strcasecmp($field_name,'Record Title')==0){
        $field_name = 'rectitle';
    }elseif(strcasecmp($field_name,'Record ID')==0){
        $field_name = 'id';
    }elseif(strcasecmp($field_name,'Record TypeID')==0){
        $field_name = 'rectypeid';
    }elseif(strcasecmp($field_name,'Record TypeName')==0){
        $field_name = 'rectypename';
    }elseif(strcasecmp($field_name,'Record Modified')==0){
        $field_name = 'modified';
    }


    if (strcasecmp($field_name,'id')==0 ||
    strcasecmp($field_name,'rectitle')==0 ||
    strcasecmp($field_name,'modified')==0 ||
    strcasecmp($field_name,'rectypeid')==0 ||
    strcasecmp($field_name,'rectypename')==0)
    {
        $field_val = self::__get_field_value( $field_name, $rt, $mode, $rec_id );
        return $field_val;
    }

    $fullstop = '.';
    $fullstop_ex = '/^([^.]+?)\\s*\\.\\s*(.+)$/';
    $fullstop_concat = '.';

    if($mode==1 || mb_strpos($field_name, '..')>0){ //convert to concept codes
        $fullstop = '..';
        $fullstop_ex = '/^([^..]+?)\\s*\\..\\s*(..+)$/';//parsing
    }
    if($mode==2){ //convert to human readable codes
        $fullstop_concat = '..';
    }

    // Return the rec-detail-type ID for the given field in the given record type
    if (mb_strpos($field_name, $fullstop) === false && mb_strpos($field_name,'{')!==0) {    // direct field name lookup

        if($mode==1 && self::$fields_correspondence!=null){
            $field_name = self::__replaceInCaseOfImport($field_name);
        }

        $rdt_id = self::__get_dt_field($rt, $field_name, $mode);//get concept code
        if(!$rdt_id){
            //ERROR
            $msg = "Field name '$field_name' not recognised";
            $check_mask = $mode == 1 && !empty(self::$provided_mask);
            if($check_mask && mb_ereg("(^|[^\[])\[ +$field_name|$field_name +\]([^\]]|$)", self::$provided_mask)){
                // check for possible error
                $msg .= "<br>This may be due to leading, trailing or multiple spaces in"
                       ."<br>the field names - please edit the field names if this is the case";
            }
            return array($msg);
        }else {
            return self::__get_field_value( $rdt_id, $rt, $mode, $rec_id );
        }
    }

    $parent_field_name = null;
    $inner_field_name = null;

    $matches = array();

    //
    if(false && $fullstop == '.'){
        //preg_match does not split   A...term or A(s.)..term correctly
        preg_match_all($fullstop_ex, $field_name, $matches);
    }else{
        //parse human readable with double fullstops
        $matches = explode($fullstop, $field_name);

        if (!empty($matches)) {
            // fix rare case when we have more than 2 fullstops
            // in this case redundant fullstops are added to previous field
            //  AAA...BBB  =>  AAA. and BB
            $i = 1;
            while ($i<count($matches)){
                while(mb_strpos($matches[$i],'.')===0){
                    //move fullstop to the end of previous field
                    $matches[$i-1] = $matches[$i-1].'.';
                    $matches[$i] = mb_substr($matches[$i],1);
                }
                $i++;
            }
            //add full string to the begining
            array_unshift($matches, $field_name);
        }

    }



    if ($matches && count($matches)>1) {
        $parent_field_name = $matches[1];


        if($mode==1 && self::$fields_correspondence!=null){  //special case
            $parent_field_name = self::__replaceInCaseOfImport($parent_field_name);
        }//special case

        $rdt_id = self::__get_dt_field($rt, $parent_field_name, $mode);

        if($rdt_id){

            $inner_field_name = $matches[2];

            $dt_type = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_Type');
            if($dt_type=="enum" || $dt_type=="relationtype"){

                if(!$inner_field_name || strcasecmp($inner_field_name,'label')==0){
                    $inner_field_name = "term";
                }elseif(strcasecmp($inner_field_name,'id')==0){
                    $inner_field_name = "internalid";
                }

                if (strcasecmp($inner_field_name,'internalid')==0 ||
                strcasecmp($inner_field_name,'term')==0 ||
                strcasecmp($inner_field_name,'code')==0 ||
                strcasecmp($inner_field_name,'conceptid')==0)
                {

                    if($mode==0){
                        return self::__get_field_value( $rdt_id, $rt, $mode, $rec_id, $inner_field_name);
                    }else{
                        if($mode==1){
                            $s1 = $rdt_id;
                        }else{
                            $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                        }

                        return $s1. $fullstop_concat .strtolower($inner_field_name);
                    }

                }else{
                    //ERROR
                    return array("error_title" => "Syntax error",
                                 "message" => "Unable to interpret '$inner_field_name' as a field<br><br>"
                                            + "Fields must be enclosed in square brackets []. If the name appears<br>"
                                            + "correct, please check for unwanted spaces, formatting or other characters.<br><br>"
                                            + "If you have used the tree on the left to insert a field and it insert incorrect<br>"
                                            + "text, please let us know with name of database, record type and field name,<br>"
                                            + "as this should not happen.");
                }
            }elseif($dt_type== 'resource'){


            }


            if(false && $dt_type== 'relmarker') { //@todo - to implement it in nearest future
                return array("'$parent_field_name' is a relationship marker field type. This type is not supported at present.");
            }
            if($dt_type!== 'resource' && $dt_type!=='relmarker') {
                //ERROR
                return array("'$parent_field_name' must be either a record type name, a terms list field name or a record pointer field name. "
                    ."Periods are used as separators between record type name and field names. If you have a period in your record type name or field name, "
                    ."please rename it to use an alternative punctuation such as - _ , ) |");
            }

        }else{
            //ERROR
            $msg = "'$parent_field_name' not recognised as a field name";
            $check_mask = $mode == 1 && !empty(self::$provided_mask);
            if($check_mask && mb_ereg("(^|[^\[])\[ +$parent_field_name|$parent_field_name +\]([^\]]|$)", self::$provided_mask)){
                // check for possible error
                $msg .= "<br>This may be due to leading, trailing or multiple spaces in"
                       ."<br>the field names - please edit the field names if this is the case";
            }
            return array($msg);
        }
    } else {
        return "";
    }

    //parent field id and inner field
    if ($rdt_id  &&  $inner_field_name) {

        //recordttype for pointer field may be defined in mask
        //it is required to distiguish rt for multiconstrained pointers
        $inner_rectype = 0;
        $inner_rectype_name = '';
        $inner_rectype_cc = '';//concept code
        $multi_constraint = false;

        if(count($matches)>3){ //this is resource (record pointer) field  [Places referenced..Media..Media item title]

            $ishift = 0;
            $pos = mb_strpos($inner_field_name, '{');//{Organization}..Name - name of target rectype is defined
            $pos2 = mb_strpos($inner_field_name, '}');
            $is_parent_entity = !empty($inner_field_name) ? self::_is_parent_entity($inner_field_name) : false;
            if($pos===0 && $pos2==mb_strlen($inner_field_name)-1){
                $inner_rectype_search = mb_substr($inner_field_name, 1, -1);

                $ishift = 3;
                $multi_constraint = true;
            }else{

                $inner_rectype = self::__get_dt_field($rt, $rdt_id, $mode, 'rst_PtrFilteredIDs');
                $inner_rectype = explode(",", $inner_rectype);//mb_split
                if(count($inner_rectype)==1 && $inner_rectype[0]>0 || $is_parent_entity){
                    $inner_rectype = $inner_rectype[0];
                    $ishift = 2;
                }else{
                    $inner_rectype = 0;
                    $inner_rectype_search = $inner_field_name;
                    $ishift = 3;
                }

            }
            if($inner_rectype==0 && !$is_parent_entity){
                list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search );
                if(!($inner_rectype>0)){
                    return array("error_title" => "Syntax error",
                                 "message" => "Unable to interpret '$inner_rectype_search' as a record type<br><br>"
                                            .'Record types must be enclosed in curly brackets {}. If the name appears<br>'
                                            .'correct, please check for unwanted spaces, formatting or other characters.<br><br>'
                                            .'If you have used the tree on the left to insert a field and it insert incorrect<br>'
                                            .'text, please let us know with name of database, record type and field name,<br>'
                                            .'as this should not happen.');
                }
            }

            $f_name = implode($fullstop, array_splice($matches,$ishift));

            if($mode==0){//replace with value
                $pointer_ids = self::__get_field_value( $rdt_id, $rt, $mode, $rec_id);
                $pointer_ids = prepareIds($pointer_ids);
                $res = array();
                foreach ($pointer_ids as $rec_id){
                    $fld_value = self::__fill_field($f_name, $inner_rectype, $mode, $rec_id);//recursion
                    array_push($res, $fld_value);
                }
                $res = implode(", ", $res);
            }else{

                if($mode==1){
                    $s1 = $rdt_id; //parent detail id
                    if($multi_constraint){
                        $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_cc.'}';
                    }
                }else{
                    $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                    if($multi_constraint){
                        $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_name. '}';
                    }
                }

                $s2 = self::__fill_field($f_name, $inner_rectype, $mode, $rec_id);
                if(is_array($s2)){
                    $res = $s2; //error
                } else {
                    $res = $s1. $fullstop_concat .$s2; //recursion
                }
            }


            return $res;
            // TEMP
            //list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search );
            //$inner_field_name = $matches[3];
        }else{


            $pos = mb_strpos($inner_field_name, $fullstop);//{Organization}..Name
            $pos2 = mb_strpos($inner_field_name, '}');
            if ( $pos>0 &&  $pos2>0 && $pos2 < $pos ) {
                $inner_rectype_search = mb_substr($inner_field_name, 1, $pos2-1);//was $pos-mb_strlen($fullstop)
                list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search );
                $inner_field_name = mb_substr($inner_field_name, $pos+mb_strlen($fullstop));
            }
        }

        if($mode==0){ //replace with values
//[Note title]  [Author(s).{PersonBig}.Family Name] ,  [Author(s).{Organisation}.Full name of organisation]
// [2-1]  [2-15.{2-10}.2-1] ,  [2-15.{2-4}.2-1]

            //get values for resource (record pointer) field
            $pointer_ids = self::__get_field_value( $rdt_id, $rt, $mode, $rec_id);
            $pointer_ids = prepareIds($pointer_ids);
            $res = array();
            foreach ($pointer_ids as $rec_id){

                $rec_value = self::__get_record_value($rec_id);
                if($rec_value){
                    $res_rt = $rec_value['rec_RecTypeID'];//resource (linked record) type rt

                    if($inner_rectype>0 && $inner_rectype!=$res_rt) {continue;}

                    $fld_value = self::__fill_field($inner_field_name, $res_rt, $mode, $rec_id);
                    if(is_array($fld_value)){
                        //for multiconstraint it may return error since field may belong to different rt
                        return '';//$fld_value; //ERROR
                    }elseif($fld_value) {
                        array_push($res, $fld_value);
                    }
                }
                //self::__get_field_value( $rdt_id, $rt, $mode, $rec_id) );
            }
            return implode(", ", $res);

        }else{ //convert  coded<->human

            if($inner_rectype>0){
                $inner_rec_type = array($inner_rectype);
            }else{
                $inner_rec_type = self::__get_dt_field($rt, $rdt_id, $mode, 'rst_PtrFilteredIDs');
                $inner_rec_type = explode(",", $inner_rec_type);
            }
            if(!empty($inner_rec_type)){ //constrained
                $field_not_found = null;
                foreach ($inner_rec_type as $rtID){
                    $rtid = intval($rtID);
                    if (!$rtid) {continue;}
                    if($inner_rectype>0){
                        if($inner_rectype!=$rtid) {continue;} //skip
                    }else{
                        list($rtid, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $rtid );
                    }

                    $inner_rdt = self::__fill_field($inner_field_name, $rtid, $mode);
                    if(is_array($inner_rdt)){
                        //it may be found in another record type for multiconstaints
                        $field_not_found = $inner_rdt; //ERROR
                    }elseif($inner_rdt) {

                        if($mode==1){
                            $s1 = $rdt_id; //parent detail id
                            if($inner_rectype>0){
                                $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_cc.'}';
                            }
                        }else{
                            $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                            if($inner_rectype>0){
                                $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_name. '}';
                            }
                        }
                        return $s1. $fullstop_concat .$inner_rdt;
                    }
                }
                if($field_not_found){
                    return $field_not_found;
                }
            }
            if($mode==1){  //return concept code
                $s1 = $rdt_id;
            }else{
                $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
            }
            return $s1. ($inner_field_name? $fullstop_concat.$inner_field_name:"");
        }
    }

    return "";
}

/**
 * Replaces a local detail type ID with its corresponding concept code during import.
 *
 * Uses the `self::$fields_correspondence` map if available.
 *
 * @param string|int $dty_ID The local detail type ID or an existing concept code.
 * @return string|int The concept code if a replacement is found, otherwise the original `$dty_ID`.
 */
private static function __replaceInCaseOfImport($dty_ID){
    //special case - replace dty_ID in case of definition import
    if(strpos($dty_ID,"-")===false && is_numeric($dty_ID)){ //this is not concept code and numeric

        if(self::$fields_correspondence!=null && count(self::$fields_correspondence)>0 && @self::$fields_correspondence[$dty_ID]){
            $dty_ID = @self::$fields_correspondence[$dty_ID];
        }
    }
    return $dty_ID;
}

/**
 * Checks if a given field name refers to a "Parent Entity" type field.
 *
 * Matches against common names ("parent entity", "record parent"), the defined
 * constant `DT_PARENT_ENTITY`, or the specific concept code "2-247".
 *
 * @param string $field_name The field name to check (case-insensitive).
 * @return bool True if it's considered a parent entity field, false otherwise.
 */
private static function _is_parent_entity($field_name){

    $field_name = mb_strtolower($field_name, 'UTF-8');

    return mb_strpos($field_name, 'parent entity')===0
        || mb_strpos($field_name, 'record parent')===0
        || (defined('DT_PARENT_ENTITY') && $field_name==DT_PARENT_ENTITY)
        || $field_name=='2-247';
}

}//end of class

if (! function_exists('array_str_replace')) {
    /**
     * Replaces all occurrences of the search strings with the replacement strings,
     * processing from left to right and ensuring non-overlapping replacements.
     *
     * This function is designed to behave more predictably than PHP's built-in `str_replace`
     * when `$search` is an array, especially when search strings might overlap or when
     * replacements might re-introduce earlier search strings. It processes the subject string
     * by finding the earliest occurrence of any search string, performing that replacement,
     * and then continuing the search on the remainder of the string.
     *
     * @param array|string $search The value being searched for, otherwise known as the needle.
     *                             An array may be used to designate multiple needles.
     * @param array|string $replace The replacement value that replaces found search values.
     *                              An array may be used to designate multiple replacements.
     * @param string $subject The string being searched and replaced on, otherwise known as the haystack.
     * @return string A string with the replaced values.
     */
    function array_str_replace($search, $replace, $subject) {
        /*
        * PHP's built-in str_replace is broken when $search is an array:
        * it goes through the whole string replacing $search[0],
        * then starts again at the beginning replacing $search[1], &c.
        * array_str_replace instead looks for non-overlapping instances of each $search string,
        * favouring lower-indexed $search terms.
        *
        * Whereas str_replace(array("a","b"), array("b", "x"), "abcd") returns "xxcd",
        * array_str_replace returns "bxcd" so that the user values aren't interfered with.
        */

        $val = '';

        while ($subject) {
            $match_idx = -1;
            $match_offset = -1;
            for ($i=0; $i < count($search);++$i) {
                if(isEmptyStr($search[$i])) {continue;}
                $offset = mb_strpos($subject, $search[$i]);
                if ($offset === false) {continue;}

                if ($match_offset == -1  ||  $offset < $match_offset) {
                    $match_idx = $i;
                    $match_offset = $offset;
                }
            }

            if ($match_idx == -1) {
                // no matches for any of the strings
                $val .= $subject;
                $subject = '';
                break;
            }

            $val .= mb_substr($subject, 0, $match_offset) . $replace[$match_idx];
            $subject = mb_substr($subject, $match_offset + mb_strlen($search[$match_idx]));
        }

        return $val;
    }

}
?>
