<?php
/**
* relationshipData.php - Functions library to search records
*
* legacy of h3 - used in reportRecord and renderRecordData 
* 
* @todo 1) use recLinks  2) move to recordSearch use recordGetRelationship?
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\records\search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.0
*/

/** @var array|null $inverses Cache for relationship term inverses, mapping trm_ID to its inverse trm_ID. Loaded by `reltype_inverse`. */
global $inverses;

/**
 * Retrieves and assembles details for a specific relationship record (typically a Type 1 record).
 *
 * This function fetches various details from a given relationship record (`$recID`),
 * such as the relationship type, source record, target record, interpretation record,
 * notes, title, start date, and end date.
 *
 * The interpretation of source and target, and the relationship term itself, depends on the
 * `$i_am_primary` flag. If true, the function assumes the context is from the perspective
 * of the source record of the relationship; otherwise, it's from the target's perspective,
 * and the inverse relationship term is used.
 *
 * Constants like `RT_RELATION`, `DT_RELATION_TYPE`, etc., are used to identify specific
 * record and detail types used in relationship structures.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The Record ID of the relationship record itself.
 * @param bool $i_am_primary True if the current context is that of the primary/source record in the relationship,
 *                           false if it's the target/secondary record. This affects which linked record is
 *                           considered "RelatedRecID" and whether the inverse term is fetched.
 * @return array An associative array (`$bd`) containing the relationship details:
 *               - 'recID': (int) The ID of the relationship record.
 *               - 'RelTermID': (int|null) The term ID of the relationship type (or its inverse if applicable).
 *               - 'RelTerm': (string|null) The label of the relationship term.
 *               - 'ParentTermID': (int|null) The parent term ID of the relationship term, if any.
 *               - 'RelatedRecID': (array|null) Associative array of the "other" record in the relationship
 *                                 (target if $i_am_primary is true, source otherwise), containing its
 *                                 'rec_ID', 'rec_Title', 'rec_RecTypeID', 'rec_URL'. Null if not found.
 *               - 'InterpRecID': (array|null) Associative array for an interpretation record, if linked. Null if not found.
 *               - 'Notes': (string|null) Short summary/notes of the relationship.
 *               - 'Title': (string|null) Name/title of the relationship record itself.
 *               - 'StartDate': (string|null) Start date of the relationship.
 *               - 'EndDate': (string|null) End date of the relationship.
 * @todo      Change `$i_am_primary` to a more descriptive name like `$useInverseRelation` or clarify its meaning.
 */
function fetch_relation_details($system, $recID, $i_am_primary) {

    $relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0); // Record Type ID for 'Relationship'
    $relTypDT = ($system->defineConstant('DT_RELATION_TYPE')?DT_RELATION_TYPE:0); // Detail Type ID for 'Relation Type' (enum)
    $relSrcDT = ($system->defineConstant('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0); // Detail Type ID for 'Primary Resource' (source record pointer)
    $relTrgDT = ($system->defineConstant('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0); // Detail Type ID for 'Target Resource' (target record pointer)
    $intrpDT = ($system->defineConstant('DT_INTERPRETATION_REFERENCE')?DT_INTERPRETATION_REFERENCE:0); // Detail Type ID for 'Interpretation Reference'
    $notesDT = ($system->defineConstant('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:0); // Detail Type ID for 'Short Summary' / Notes
    $startDT = ($system->defineConstant('DT_START_DATE')?DT_START_DATE:0); // Detail Type ID for 'Start Date'
    $endDT = ($system->defineConstant('DT_END_DATE')?DT_END_DATE:0); // Detail Type ID for 'End Date'
    $titleDT = ($system->defineConstant('DT_NAME')?DT_NAME:0); // Detail Type ID for 'Name' / Title

    $mysqli = $system->getMysqli();
    $recID = intval($recID); // Ensure recID is an integer
    $res = $mysqli->query('select * from recDetails where dtl_RecID = ' . $recID);

    $bd = array('recID' => $recID);
    if($res){

        $query_select = 'select rec_ID, rec_Title, rec_RecTypeID, rec_URL from Records where rec_ID = ';

        while ($row = $res->fetch_assoc()) {

        switch ($row['dtl_DetailTypeID']) {
            case $relTypDT:
                if ($i_am_primary) {
                    $bd['RelTermID'] = (int)$row['dtl_Value'];
                } else {
                    $bd['RelTermID'] = reltype_inverse($system, (int)$row['dtl_Value']);
                }
                if (!empty($bd['RelTermID'])) {
                    $relval = mysql__select_row_assoc($mysqli,
                            'select trm_Label, trm_ParentTermID from defTerms where trm_ID = ' . intval($bd['RelTermID']));
                    if($relval!=null){
                        $bd['RelTerm'] = $relval['trm_Label'];
                        if ($relval['trm_ParentTermID']) {
                            $bd['ParentTermID'] = (int)$relval['trm_ParentTermID'];
                        }
                    }
                }
                break;
            case $relTrgDT: // linked resource (Target)
                if (!$i_am_primary) {break;} // If context is target, this field points to self, so skip. We want the other record.
                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    $query_select.intval($row['dtl_Value']) );
                break;
            case $relSrcDT: // linked resource (Source)
                if ($i_am_primary) {break;} // If context is source, this field points to self.
                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    $query_select.intval($row['dtl_Value']) );
                break;
            case $intrpDT:
                $bd['InterpRecID'] = mysql__select_row_assoc($mysqli,
                                    $query_select.intval($row['dtl_Value']) );
                break;
            case $notesDT:
                $bd['Notes'] = $row['dtl_Value'];
                break;
            case $titleDT:
                $bd['Title'] = $row['dtl_Value'];
                break;
            case $startDT:
                $bd['StartDate'] = $row['dtl_Value'];
                break;
            case $endDT:
                $bd['EndDate'] = $row['dtl_Value'];
                break;
            default;
        }
    }
        $res->close();
    }

    return $bd;
}

/**
 * Determines the inverse of a relationship term ID.
 *
 * It uses a statically cached array `$inverses` to store and look up term inverses.
 * If the inverse is not directly found, it attempts a reverse search in the cache.
 * If no inverse is found, it returns the original term ID.
 *
 * @global array|null $inverses Cache for relationship term inverses. Maps `trm_ID` to its inverse `trm_ID`.
 *                             It's populated on the first call if null.
 * @param \hserv\System $system The Heurist system object (used to get mysqli connection).
 * @param int $relTermID The relationship term ID (`trm_ID`) for which to find the inverse.
 * @return int|null The `trm_ID` of the inverse term. Returns the original `$relTermID` if no specific inverse is found.
 *                  Returns null if `$relTermID` is falsey (e.g., 0 or null).
 * @todo Modify to return a distinct value (e.g., specific constant or null) if no inverse is defined,
 *       instead of returning the original term ID. The current fallback to original ID can be misleading.
 */
function reltype_inverse($system, $relTermID) {
    global $inverses;

    $mysqli = $system->getMysqli();
    $relTermID = intval($relTermID);

    if (!$relTermID) {return null;} // Return null for invalid/zero term ID

    if (!isset($inverses)) { // Populate cache on first run
        $inverses = array(); // Initialize to prevent errors if query fails
        // Fetches pairs of [trm_ID => inverse_trm_ID]
        // Assuming mysql__select_assoc2 returns an associative array [trm_ID => inverse_trm_ID]
        $result = mysql__select_assoc2($mysqli,
                "SELECT A.trm_ID, A.trm_InverseTermID FROM defTerms A WHERE A.trm_Label IS NOT NULL");
        if ($result) {
            $inverses = $result;
        }
    }

    // Lookup inverse: $inverses should map trm_ID => inverse_trm_ID (which could be null if not set)
    if (isset($inverses[$relTermID]) && $inverses[$relTermID] !== null && $inverses[$relTermID] != 0) { // Check for actual inverse value
        return (int)$inverses[$relTermID];
    }
    
    // Attempt reverse search if direct lookup failed or inverse was null/0.
    // This assumes that if B is inverse of A, then A is inverse of B, and this might not always be explicitly set both ways.
    $inverse_key = array_search($relTermID, $inverses, true); // Strict search for $relTermID in the values of $inverses
    if ($inverse_key !== false) {
        return (int)$inverse_key; // Return the key, which is the ID of the term whose inverse is $relTermID
    }

    // If no inverse found by any means, return the original term ID as per original logic's fallback.
    return $relTermID;
}
?>
