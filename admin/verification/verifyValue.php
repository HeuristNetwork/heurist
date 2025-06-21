<?php
/**
* verifyValue.php - Library of static functions for validating Heurist data values.
*
* @fileOverview This file provides the `VerifyValue` class, a collection of static methods
*               used to validate various types of data within Heurist, particularly focusing on
*               record pointers and term selections to ensure they conform to defined constraints.
*               These functions are utilized by other administrative and import scripts like
*               `dbVerify.php` and `importCSV_lib.php`, and are intended to be integrated into
*               core data saving processes (e.g., `saveRecordDetail`, `importRectype`).
*               The class uses static properties to cache term and definition lookups for efficiency
*               within a single database context and provides a `reset()` method to clear these caches
*               when switching databases.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/verification
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//getAllowedTerms
//isValidTerm
//isValidTermLabel
//isValidTermCode
//isValidPointer

class VerifyValue {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;

    private static $dtyIDDefs = array();//list of allowed terms for particular detail type ID
    private static $dtyIDDefsLabels = array();//with hierarchy
    private static $dtyIDDefsLabelsPlain = array();//without hierarchy
    private static $dtyIDDefsCodes = array();
    private static $terms = null;
    private static $dbsTerms = null;

    /**
     * Initializes static properties for the class, primarily setting up system and mysqli objects.
     * Ensures initialization only occurs once.
     *
     * @access private
     * @static
     * @global hserv\System $system The global Heurist System object, assigned to self::$system.
     * @return void
     */
    private static function initialize()
    {
        if (self::$initialized) {return;}

        global $system;
        self::$system = $system;
        self::$mysqli = $system->getMysqli();

        self::$initialized = true;
    }


    //
    // clear all global variables
    // it is required in case database switch
    //
    /**
     * Resets static caches used by the class.
     * Call this when switching database contexts to ensure fresh lookups.
     * @static
     * @return void
     */
    public static function reset(){
        self::$dtyIDDefs = array();//list of allowed terms for particular detail type ID
        self::$dtyIDDefsLabels = array();
        self::$dtyIDDefsLabelsPlain = array();
        self::$dtyIDDefsCodes = array();

        self::$terms = null;
    }

/**
* get all terms ids allowed for given field type
*
* @static
* @param array|string|null $defs The definition string or array for allowed terms.
*                                This can be a JSON string representing a term tree, a comma-separated list of term IDs,
*                                a single term ID (for a vocabulary root), or null/empty if not restricted.
* @param int|null $dtyID The detail type ID. Used for caching the resolved term list.
* @return array<int>|string|null An array of allowed term IDs, the string "all" if no specific terms are defined (meaning all terms in the domain are allowed),
*                                or null if the definition string is empty or invalid.
*/
public static function getAllowedTerms($defs, $dtyID){

    self::initialize();

    $allowed_terms = null;

    if($dtyID==null || !@self::$dtyIDDefs[$dtyID]){ //detail type ID is not defined or terms are already found

        if ( $dtyID == self::$system->getConstant('DT_RELATION_TYPE')) {
            $parent_id = 'relation';
        }elseif(is_array($defs) && count($defs)==1){
            $parent_id = $defs[0];
        }else{
            $parent_id = $defs;
        }
        if($parent_id==null || $parent_id==''){
            $allowed_terms = 'all';
        }else{
            self::getTerms();
            $allowed_terms = self::$dbsTerms->treeData($parent_id, 3);
        }

        self::$dtyIDDefs[$dtyID] = $allowed_terms;

    }else{
        //take from store
        $allowed_terms = self::$dtyIDDefs[$dtyID];
    }
    return $allowed_terms;
}

/**
 * Retrieves or initializes the DbsTerms object containing all term information for the current database.
 *
 * @static
 * @return \DbsTerms An instance of the DbsTerms class.
 */
public static function getTerms(){
    if(self::$terms == null){
        self::initialize();
        self::$terms = dbs_GetTerms(self::$system);
        self::$dbsTerms = new DbsTerms(self::$system, self::$terms);
    }
    return self::$dbsTerms;
}

/**
 * Checks if a vocabulary contains a term with the given label.
 *
 * @static
 * @param int    $vocab_id The ID of the vocabulary (parent term) to search within.
 * @param string $label    The label of the term to find.
 * @return int|false The term ID if found, otherwise false.
 */
public static function hasVocabGivenLabel($vocab_id, $label){
    self::getTerms();
    return self::$dbsTerms->getTermByLabel($vocab_id, $label);
}


/**
* Verifies that term ID value is valid for given detail id
*
* @static
* @param array|string|null $defs        JSON string, array of term IDs, or single term ID (vocabulary root) defining allowed terms.
* @param array|string|null $defs_nonsel JSON string or array of term IDs that are not selectable (currently unused by this method).
* @param int               $id          The term ID to validate.
* @param int|null          $dtyID       The detail type ID, used for caching allowed terms.
* @return bool True if the term ID is valid for the given definitions, false otherwise.
*/
public static function isValidTerm($defs, $defs_nonsel, $id, $dtyID){

    $allowed_terms = self::getAllowedTerms($defs, $dtyID);

    return $allowed_terms && ($allowed_terms === "all" || in_array($id, $allowed_terms));
}

/**
* Returns term ID if label is valid and false if invalid
* Label can be dot separated hierarchical label Parent.Child
*
* used in import csv
*
* @static
* @param array|string|null $defs        JSON string, array of term IDs, or single term ID (vocabulary root) defining allowed terms.
* @param array|string|null $defs_nonsel JSON string or array of term IDs that are not selectable (currently unused by this method).
* @param string            $label       The term label to validate. Can be a simple label or a dot-separated hierarchical label.
* @param int|null          $dtyID       The detail type ID, used for caching allowed term labels.
* @param bool              $isStripAccents Optional. If true, performs accent-insensitive comparison. Defaults to false.
* @return int|false The term ID if the label is valid and found within the allowed terms, false otherwise.
*/
public static function isValidTermLabel($defs, $defs_nonsel, $label, $dtyID, $isStripAccents=false){

    if($dtyID==null || !@self::$dtyIDDefsLabels[$dtyID]){

        //label may have fullstop in its own name - so we always search with and without hierarchy $withHierarchy = true;

        self::initialize();
        self::getTerms();
        $allowed_terms = self::getAllowedTerms($defs, $dtyID);

        $allowed_labels = array();
        $allowed_labels_plain = array();

        $idx_label = self::$terms['fieldNamesToIndex']['trm_Label'];

        //get all labels
        $domain = @self::$terms['termsByDomainLookup']['relation'][$allowed_terms[0]]?'relation':'enum';
        $list = self::$terms['termsByDomainLookup'][$domain];
        foreach($allowed_terms as $term_id){
           $allowed_labels[$term_id] = getTermFullLabel(self::$terms, $list[$term_id], $domain, false);//returns term with parent
           $allowed_labels_plain[$term_id] = $list[$term_id][$idx_label];
           //remove last point
           $allowed_labels[$term_id] = trim($allowed_labels[$term_id],'.');
        }//for

        if($isStripAccents && is_array($allowed_labels)){
            array_walk($allowed_labels, 'trim_lower_accent2');

            array_walk($allowed_labels_plain, 'trim_lower_accent2');
        }

        //keep for future use
        if($dtyID!=null){
            self::$dtyIDDefsLabels[$dtyID] = $allowed_labels;
            self::$dtyIDDefsLabelsPlain[$dtyID] = $allowed_labels_plain;
        }

    }else{
        $allowed_labels = self::$dtyIDDefsLabels[$dtyID];
        $allowed_labels_plain = self::$dtyIDDefsLabelsPlain[$dtyID];
    }

    //check if given label among allowed
    $label = trim(mb_strtolower($label));
    $label = trim($label,'.');

    if(empty($allowed_labels)){
        return false;
    }

    $term_ID = array_search($label, $allowed_labels, true);
    if(!isPositiveInt($term_ID)){
        $term_ID = array_search($label, $allowed_labels_plain, true);
    }

    return $term_ID;
}

/**
* Returns term ID if code is valid and false if invalid
*
* used in import csv
*
* @static
* @param array|string|null $defs        JSON string, array of term IDs, or single term ID (vocabulary root) defining allowed terms.
* @param array|string|null $defs_nonsel JSON string or array of term IDs that are not selectable (currently unused by this method).
* @param string            $code        The term code to validate.
* @param int|null          $dtyID       The detail type ID, used for caching allowed term codes.
* @return int|false The term ID if the code is valid and found within the allowed terms, false otherwise.
*/
public static function isValidTermCode($defs, $defs_nonsel, $code, $dtyID){

    if($dtyID==null || !@self::$dtyIDDefsCodes[$dtyID]){

        self::initialize();
        self::getTerms();
        $allowed_terms = self::getAllowedTerms($defs, $dtyID);

        $allowed_codes = array();

        $idx_code = self::$terms['fieldNamesToIndex']['trm_Code'];

        //get all codes
        $domain = @self::$terms['termsByDomainLookup']['relation'][$allowed_terms[0]]?'relation':'enum';
        $list = self::$terms['termsByDomainLookup'][$domain];
        foreach($allowed_terms as $term_id){
           $allowed_codes[$term_id] = mb_strtolower($list[$term_id][$idx_code]);
        }

        //keep for future use
        if($dtyID!=null){
            self::$dtyIDDefsCodes[$dtyID] = $allowed_codes;
        }

    }else{
        $allowed_codes = self::$dtyIDDefsCodes[$dtyID];
    }

    //check if given code among allowed
    $code = trim(mb_strtolower($code));

    if(is_array($allowed_codes)){
        $term_ID = array_search($code, $allowed_codes, true);
    }else{
        return false;
        //$term_ID = getTermByCode($code);//see dbsData.php
    }

    return $term_ID;
}

//-------------------------------------
/**
 * Verifies if a given record ID is a valid pointer based on record type constraints.
 *
 * Checks if the record pointed to by `$rec_id` exists and if its record type ID
 * is included in the list of allowed record type IDs specified by `$constraints`.
 *
 * @static
 * @param string|null $constraints A comma-separated string of allowed record type IDs.
 *                                 If null or empty, or "all", any record type is considered valid.
 * @param int         $rec_id    The ID of the record being pointed to.
 * @return bool True if the pointer is valid (record exists and its type matches constraints), false otherwise.
 */
public static function isValidPointer($constraints, $rec_id ){

    $isvalid = false;

    if(isset($rec_id) && is_numeric($rec_id) && $rec_id>0){

        self::initialize();

        $tempRtyID = mysql__select_value( self::$mysqli, "select rec_RecTypeID from Records where rec_ID = ".$rec_id);

        if ($tempRtyID>0){

                $allowed_types = "all";
                if ($constraints!=null && $constraints != "") {
                    $temp = explode(",",$constraints);//get allowed record types
                    if (!empty($temp)) {
                        $allowed_types = $temp;
                    }
                }

                $isvalid = ($allowed_types === "all" || in_array($tempRtyID, $allowed_types));
        }
    }
    return $isvalid;
}

}
