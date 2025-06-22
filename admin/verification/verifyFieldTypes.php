<?php
/**
* verifyFieldTypes.php - Library of functions to validate field definitions and term configurations.
*
* @fileOverview This script provides a collection of functions used to verify the integrity of
*               field (detail type) definitions within a Heurist database. These checks are
*               often invoked by other administrative scripts like `dbVerify.php`.
*               Key validations include:
*               - Ensuring terms specified in `dty_JsonTermIDTree` (for vocabularies/term lists)
*                 and `dty_TermIDTreeNonSelectableIDs` (for exclusions) actually exist.
*               - Ensuring record types specified in `dty_PtrTargetRectypeIDs` (for pointer field
*                 constraints) actually exist.
*               - Validating `rst_DefaultValue` for resource and enumeration fields to ensure
*                 the default value points to an existing and valid record/term that respects
*                 any defined constraints.
*               - Identifying issues within the term hierarchy itself, such as terms with missing
*                 parents, missing inverse terms, or duplicate labels at the same hierarchy level.
*               These functions typically operate on the currently selected database.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/verification
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/
global $trmLookup, $rtyNames;

$trmLookup = array();//list of all terms
$rtyNames = array();//rty_ID=>rty_Name

/**
 * Initializes global lookup arrays for terms and record type names.
 *
 * This function populates `$trmLookup` with all terms from `defTerms` and
 * `$rtyNames` with all record type IDs and their names from `defRecTypes`.
 * It's intended to be called once per database context to cache these lookups.
 *
 * @global array $trmLookup Array to be populated with term data (trm_ID => term_details_array).
 * @global array $rtyNames Array to be populated with record type names (rty_ID => rty_Name).
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param string|null $type Optional. If 'trm', only initializes term lookup. If 'rty', only initializes
 *                          record type name lookup. If null or any other value, initializes both.
 * @return void
 */
function initGlobalArr($mysqli, $type=null){

    global $trmLookup, $rtyNames;

    if($type==null || $type='trm'){
        // lookup detail type enum values
        $query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms order by trm_ParentTermID,trm_Label';
        $trmLookup = mysql__select_assoc($mysqli, $query);
    }
    if($type==null || $type='rty'){
        //record type name
        $query = 'SELECT rty_ID, rty_Name FROM defRecTypes';
        $rtyNames = mysql__select_assoc2($mysqli, $query);
    }
}

/**
 * Finds detail types ('enum', 'relationtype', 'relmarker', 'resource') with invalid configurations.
 *
 * This includes checks for:
 * - Invalid terms in `dty_JsonTermIDTree`.
 * - Invalid terms in `dty_TermIDTreeNonSelectableIDs`.
 * - Invalid record type IDs in `dty_PtrTargetRectypeIDs` for pointer fields.
 *
 * @global array $trmLookup Global array of all terms, used by `getInvalidTerms`.
 * @global array $rtyNames Global array of record type names, used by `getInvalidRectypes`.
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param int|null $rectype_id Optional. If provided, limits the check to detail types associated
 *                             with this specific record type via `defRecStructure`. If null, checks all relevant detail types.
 * @return array An associative array containing lists of detail types with issues:
 *               - 'terms': Fields with invalid terms in their main term selection tree.
 *               - 'terms_nonselectable': Fields with invalid terms in their non-selectable list.
 *               - 'rt_contraints': Pointer fields with invalid record type constraints.
 */
function getInvalidFieldTypes($mysqli, $rectype_id){

    global $trmLookup, $rtyNames;

    if(empty($rtyNames)) {
        initGlobalArr($mysqli, 'rty');
    }

    //list of detail types to validate
    $dtyToValidate = array();
    $query = "SELECT dty_ID,".
    "dty_Name,".
    "dty_Type,".
    "dty_JsonTermIDTree,".
    "dty_TermIDTreeNonSelectableIDs,".
    "dty_PtrTargetRectypeIDs".
    " FROM defDetailTypes";

    if($rectype_id>0){ //detail types for given recordtype
        $query = $query.", defRecStructure WHERE rst_RecTypeID=".$rectype_id." and rst_DetailTypeID=dty_ID and ";

    }else{
        $query = $query.SQL_WHERE;
    }
    $query = $query.
    "(dty_Type in ('enum','relationtype','relmarker','resource')".
    " and (dty_JsonTermIDTree is not null or dty_TermIDTreeNonSelectableIDs is not null)) ".
    "or (dty_Type in ('relmarker','resource') and dty_PtrTargetRectypeIDs is not null)";


    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            $dtyToValidate[$row['dty_ID']] = $row;
        }
    }

    $dtysWithInvalidTerms = array();
    $dtysWithInvalidNonSelectableTerms = array();
    $dtysWithInvalidRectypeConstraint = array();
    foreach ( $dtyToValidate as $dtyID => $dty) {
        if ($dty['dty_JsonTermIDTree']){
            $res = getInvalidTerms($dty['dty_JsonTermIDTree'], true);
            $invalidTerms = $res[0];
            $validTermsString = $res[1];
            if (!isEmptyArray($invalidTerms)){
                $dtysWithInvalidTerms[$dtyID] = $dty;
                $dtysWithInvalidTerms[$dtyID]['invalidTermIDs'] = $invalidTerms;
                $dtysWithInvalidTerms[$dtyID]['validTermsString'] = $validTermsString;
            }
        }
        if ($dty['dty_TermIDTreeNonSelectableIDs'])
        {
            $res = getInvalidTerms($dty['dty_TermIDTreeNonSelectableIDs'], false);
            $invalidNonSelectableTerms = $res[0];
            $validNonSelTermsString = $res[1];
            if (!isEmptyArray($invalidNonSelectableTerms)){
                $dtysWithInvalidNonSelectableTerms[$dtyID] = $dty;
                $dtysWithInvalidNonSelectableTerms[$dtyID]['invalidNonSelectableTermIDs'] = $invalidNonSelectableTerms;
                $dtysWithInvalidNonSelectableTerms[$dtyID]['validNonSelTermsString'] = $validNonSelTermsString;
            }
        }
        if ($dty['dty_PtrTargetRectypeIDs']){
            $res = getInvalidRectypes($dty['dty_PtrTargetRectypeIDs']);
            $invalidRectypes = $res[0];
            $validRectypes   = $res[1];
            if (!isEmptyArray($invalidRectypes)){
                $dtysWithInvalidRectypeConstraint[$dtyID] = $dty;
                $dtysWithInvalidRectypeConstraint[$dtyID]['invalidRectypeConstraint'] = $invalidRectypes;
                $dtysWithInvalidRectypeConstraint[$dtyID]['validRectypeConstraint'] = $validRectypes;
            }
        }

    }//for

    return array("terms"=>$dtysWithInvalidTerms,
                 "terms_nonselectable"=>$dtysWithInvalidNonSelectableTerms,
                 "rt_contraints"=>$dtysWithInvalidRectypeConstraint);//wrong default values
}

/**
 * Finds and clears invalid default values (`rst_DefaultValue`) for record pointer and enum/term fields.
 *
 * For 'resource' (pointer) fields, it checks if the default record ID exists and if its record type
 * matches the field's pointer constraints (`dty_PtrTargetRectypeIDs`).
 * For 'enum' (term) fields, it checks if the default term ID is valid for the field's
 * vocabulary (`dty_JsonTermIDTree`).
 * If an invalid default value is found, it is cleared (set to NULL) in `defRecStructure`.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param int|null $rectype_id Optional. If provided, limits the check to fields associated
 *                             with this specific record type. If null, checks fields for all record types.
 * @return array An associative array with one key:
 *               - 'rt_defvalues': An array of fields that had invalid default values (which were then cleared).
 *                                 Each element contains details of the field and the reason for invalidity.
 */
function getInvalidDefaultValues($mysqli, $rectype_id=null){

    $rtysWithInvalidDefaultValues = array();


    $query = "SELECT dty_ID,".
    "dty_Type,".
    "rst_RecTypeID,".
    "rst_DisplayName, rty_Name,".
    "rst_DefaultValue, rst_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree".
    " FROM defDetailTypes, defRecStructure, defRecTypes WHERE rst_RecTypeID=rty_ID ".
    " AND rst_DetailTypeID=dty_ID and rst_DefaultValue is not null and rst_DefaultValue<>'' AND ".
    "dty_Type in ('resource','enum') ";//,'relationtype','relmarker'
    if($rectype_id>0) {
        $query = $query.' and rst_RecTypeID='.$rectype_id;
    }
    $query = $query.' ORDER BY rst_RecTypeID, dty_ID';
    $res = $mysqli->query($query);

    if($res){
        while ($row = $res->fetch_assoc()) {

            $reason = null;

            $dtyID = $row['dty_ID'];

            if(is_numeric($row['rst_DefaultValue']) && $row['rst_DefaultValue']>0){
                if($row['dty_Type']=='resource'){

                        //check that record for record pointer field exists
                        $res2 = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$row['rst_DefaultValue']);
                        if($res2>0){
                            //record exists - check that it fits constraints
                            if($row['dty_PtrTargetRectypeIDs'] &&
                                !in_array($res2, explode(',',$row['dty_PtrTargetRectypeIDs']))){
                                    $reason = ' Record type does not fit constraints';
                                
                            }
                        }else{
                            //record does not exist
                            $reason = ' Record does not exist';
                        }
                }else{
                    //check that default term belongs to vocabulary
                    if(!VerifyValue::isValidTerm($row['dty_JsonTermIDTree'], null, $row['rst_DefaultValue'], $dtyID )){
                        $reason = ' Value does not belong to specified vocabulary';
                    }
                }
            }else{
                    $reason = ' Value is not numeric';

            }

            if($reason){
                //clear wrong defult value
                $row['reason'] = $reason;
                $rtysWithInvalidDefaultValues[] = $row;
                $mysqli->query('UPDATE defRecStructure set rst_DefaultValue=NULL where rst_ID='.intval($row['rst_ID']));
            }

        }//while
    }

    return array("rt_defvalues"=>$rtysWithInvalidDefaultValues);//wrong default values
}

/**
 * Identifies various issues within the term hierarchy (`defTerms`).
 *
 * Checks for:
 * - Terms with `trm_ParentTermID` set to a non-existent parent term.
 * - Terms with `trm_InverseTermID` set to a non-existent inverse term.
 * - Duplicate term labels at the same level within a vocabulary (i.e., terms sharing the same parent
 *   and having the same label, or labels that become identical after removing trailing numbers).
 *
 * @global array $trmLookup Global array of all terms, used if not already initialized.
 * @param \mysqli $mysqli The mysqli database connection object.
 * @return array An associative array detailing the issues found:
 *               - 'trm_missed_parents': Array of term IDs with missing parents.
 *               - 'trm_missed_inverse': Array of term IDs with missing inverse terms.
 *               - 'trm_dupes': Associative array where keys are parent term IDs and values are arrays
 *                              of term IDs under that parent which have duplicate labels.
 */
function getTermsWithIssues($mysqli){

    global $trmLookup;

    if(empty($trmLookup)) {
        initGlobalArr($mysqli, 'trm');
    }

    //terms with missed parents
    $query = 'SELECT t1.trm_ID FROM defTerms t1 left join defTerms t2 '
    .'on t1.trm_ParentTermID = t2.trm_ID where t1.trm_ParentTermID>0  and t2.trm_ID is null';

    $missed_parents = mysql__select_list2($mysqli, $query);

    //terms with missed inverse terms
    $query = 'SELECT t1.trm_ID FROM defTerms t1 left join defTerms t2 '
    .'on t1.trm_InverseTermID = t2.trm_ID where t1.trm_InverseTermID>0  and t2.trm_ID is null';

    $missed_inverse = mysql__select_list2($mysqli, $query);

    //find label duplications
    $all_dupes = array();
    $dupes = array();//dupes for parent

    $parent_id = 0;
    $prev_id = 0;
    $prev_lbl = '';

    foreach ($trmLookup as $trm_ID=>$trm){

        if($parent_id!=$trm['trm_ParentTermID']){
            if(!empty($dupes) && $parent_id>0){
                $all_dupes[$parent_id] = $dupes;
            }
            $parent_id = $trm['trm_ParentTermID'];
            $dupes = array();//reset

            $prev_lbl = removeLastNum($trm['trm_Label']);
            $prev_id = $trm_ID;
            continue;
        }

        $lbl = removeLastNum($trm['trm_Label']);
        if($lbl!=$prev_lbl){
            $prev_lbl = $lbl;
            $prev_id = $trm_ID;
            continue;

        }

        if($prev_id>0){
            $dupes[] = $prev_id;
            $prev_id = 0;
        }
        $dupes[] = $trm_ID;
    }//foreach
    if(!empty($dupes) && $parent_id>0){
        $all_dupes[$parent_id] = $dupes;
    }

    return array(
        'trm_missed_parents'=>$missed_parents,
        'trm_missed_inverse'=>$missed_inverse,
        'trm_dupes'=>$all_dupes
    );

}


/**
 * Parses a string of term IDs (from `dty_JsonTermIDTree` or `dty_TermIDTreeNonSelectableIDs`)
 * and identifies any term IDs that do not exist in the `$trmLookup`.
 *
 * @global array $trmLookup Global array of all terms, used for validation.
 * @param string $formattedStringOfTermIDs The string containing term IDs. This can be:
 *                                         - A JSON object string for hierarchical trees (e.g., `{"1":{"2":{}}}`).
 *                                         - A single term ID (representing a vocabulary root).
 *                                         - A JSON array string for flat lists (e.g., `["1","2"]`).
 * @param bool $is_tree Indicates if `$formattedStringOfTermIDs` represents a hierarchical tree (true)
 *                      or a flat list/single vocabulary ID (false).
 * @return array{0: array<int|string>, 1: string} Returns an array where:
 *                                                 - Index 0: An array of invalid term IDs found.
 *                                                 - Index 1: A string representation of the valid terms,
 *                                                            reformatted into the original structure (JSON object/array or single ID).
 *                                                            Empty if all terms were invalid or input was empty.
 */
function getInvalidTerms($formattedStringOfTermIDs, $is_tree) {
    global $trmLookup;
    if(empty($trmLookup)) {
        initGlobalArr($mysqli, 'trm');
    }

    $invalidTermIDs = array();
    if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
        return array($invalidTermIDs, "");
    }

    $isvocabulary = false;
    $pos = strpos($formattedStringOfTermIDs,"{");

    if ($pos!==false){ //}is_numeric($pos) && $pos>=0) {

        $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
        if (strrpos($temp,":") == strlen($temp)-1) {
            $temp = substr($temp,0, strlen($temp)-1);
        }
        $termIDs = explode(":",$temp);
    } elseif($is_tree){ //vocabulary

        $isvocabulary = true;
        $termIDs = array($formattedStringOfTermIDs);
    } else {
        $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
        $termIDs = explode(",",$temp);
    }
    // Validate termIDs

    foreach ($termIDs as $trmID) {
        // check that the term valid
        if (!$trmID ){ // invalid trm ID null or 0 is not allowed
            if(count($termIDs)>1){
                array_push($invalidTermIDs, "blank");
            }
        }elseif ( !@$trmLookup[$trmID]){ // invalid trm ID
            array_push($invalidTermIDs,$trmID);
        }
    }

    $validStringOfTerms = "";
    //create valid set of terms
    if(!empty($invalidTermIDs)){

        if($isvocabulary ){ //vocabulary
            $validStringOfTerms =  "";
        } elseif($is_tree) {
            $termTree = json_decode($formattedStringOfTermIDs);
            $validStringOfTerms = createValidTermTree($termTree, $invalidTermIDs);
            if($validStringOfTerms!=""){
                $validStringOfTerms = "{".$validStringOfTerms."}";
            }
        } else {
            $termIDs = array_diff($termIDs, $invalidTermIDs);
            if(!empty($termIDs)){
                $validStringOfTerms = '["'.implode('","',$termIDs).'"]';
            }else{
                $validStringOfTerms = "";
            }
        }
    }

    return array($invalidTermIDs, $validStringOfTerms);
}

/**
 * Recursively rebuilds a valid term tree string, excluding any invalid term IDs.
 *
 * This is a helper function for `getInvalidTerms` when processing hierarchical term structures.
 *
 * @param object $termTree A PHP object (decoded from JSON) representing a part of the term tree.
 *                         Keys are term IDs, and values are objects representing their children.
 * @param array<int|string> $invalidTermIDs An array of term IDs that have been identified as invalid.
 * @return string A string representing the valid portion of the term tree, formatted for JSON encoding
 *                (e.g., `"1":{"2":{}},"3":{}`). Returns an empty string if the input tree part is empty
 *                or all its terms are invalid.
 */
function createValidTermTree($termTree, $invalidTermIDs){
    
    $res = "";
    foreach ($termTree as $termid=>$child_terms){

        $key = array_search($termid, $invalidTermIDs);
        if($key===false){
            $res = $res.'"'.$termid.'":{'.createValidTermTree($child_terms, $invalidTermIDs).'},';
        }else{ //invalid

        }
    }
    return $res==''?'': substr($res,0,-1);
}

/**
 * Parses a comma-separated string of record type IDs and identifies any IDs that do not exist.
 *
 * Used to validate `dty_PtrTargetRectypeIDs` for pointer fields.
 *
 * @global array $rtyNames Global array of record type names (rty_ID => rty_Name), used for validation.
 * @param string $formattedStringOfRectypeIDs A comma-separated string of record type IDs.
 * @return array{0: array<int>, 1: string} Returns an array where:
 *                                          - Index 0: An array of invalid record type IDs found.
 *                                          - Index 1: A comma-separated string of the valid record type IDs.
 *                                                     Empty if all IDs were invalid or input was empty.
 */
function getInvalidRectypes($formattedStringOfRectypeIDs) {
    global $rtyNames;

    if(empty($rtyNames)) {
        initGlobalArr($mysqli, 'rty');
    }

    $invalidRectypeIDs = array();

    if (!$formattedStringOfRectypeIDs || $formattedStringOfRectypeIDs == "") {
        return array($invalidRectypeIDs, "");
    }

    $validRectypeIDs = array();
    $rtyIDs = explode(",",$formattedStringOfRectypeIDs);
    // Validate rectypeIDs
    foreach ($rtyIDs as $rtID) {
        // check that the rectype is valid
        if (!@$rtyNames[$rtID]){ // invalid rty ID
            array_push($invalidRectypeIDs,$rtID);
        }else{
            array_push($validRectypeIDs, $rtID);
        }
    }

    return array($invalidRectypeIDs, implode(",", $validRectypeIDs) );
}
