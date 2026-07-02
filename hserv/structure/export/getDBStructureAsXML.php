<?php
/**
* getDBStructureAsXML.php - Returns database definitions (rectypes, details etc.) as XML (HML)
*
* @param int $includeUgrps=1 will output user and group information in addition to definitions
*
* @project     Heurist academic knowledge management system
* @package Structure
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.2.0
*/
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';

header("Content-Type: application/xml");

// Normally jsut outputs definitions, this will include users/groups
$includeUgrps = @$_REQUEST["includeUgrps"];// returns null if not set

$mysqli = $system->getMysqli();

$sysinfo = $system->settings->get();

$db_version = getDbVersion($mysqli);


define('HEURIST_DBID', $system->settings->get('sys_dbRegisteredID'));

$is_subset = false;
$rty_IDs = @$_REQUEST["rty"];
$dty_IDs = @$_REQUEST["dty"];
$trm_IDs = @$_REQUEST["trm"];
$extra_term_IDs = [];
$include_connected_rectypes = @$_REQUEST['connected_rty'] == 1;
$include_terms = @$_REQUEST['include_term'] == 1;

if($rty_IDs !== null){
    $is_subset = true;
    $rty_IDs = prepareMixedIds('rty', $rty_IDs);
    if($rty_IDs !== null && $include_connected_rectypes || $include_terms){
        getRelevantIDs($rty_IDs, $extra_term_IDs, $include_connected_rectypes, $include_terms);
    }
}
if($dty_IDs !== null){
    $is_subset = true;
    $dty_IDs = prepareMixedIds('dty', $dty_IDs);
}
if($trm_IDs !== null){
    $is_subset = true;
    $trm_IDs = prepareMixedIds('trm', $trm_IDs);
}
if($is_subset && $extra_term_IDs !== []){
    $trm_IDs = is_array($trm_IDs) ? array_unique(array_merge($trm_IDs, $extra_term_IDs), SORT_NUMERIC) : $extra_term_IDs;
}
if($is_subset){
    $is_subset = $rty_IDs !== null || $dty_IDs !== null || $trm_IDs !== null;
    if(!$is_subset){
print XML_HEADER;
print "\n\n<hml_structure>";
print "\n<error>Definition not found</error>";
print "\n</hml_structure>";// end of file
exit;
    }
}



// * IMPORTANT *
// UPDATE THE FOLLOWING WHEN DATABASE FORMAT IS CHANGED:
// Version info in common/config/initialise.php
// admin/setup/dbcreate/blankDBStructure.sql - dump structure of hdb_HeuristCoreDefinitions database and insert where indicated in file
// admin/setup/dbcreate/coreDefinitions.txt (get this from the admin interface lsiting in exchange format)
// admin/setup/dbcreate/coreDefinitionsHuNI.txt (get this from the admin interface lsiting in exchange format)
// admin/setup/dbcreate/coreDefinitionsFAIMS.txt (get this from the admin interface lsiting in exchange format)


print XML_HEADER;
print "\n\n<hml_structure>";

// TODO: ADD OTHER XML HEADER INFORMATION *************

// File headers to explain what the listing represents and for version checking
print "\n\n<!--Heurist Definitions Exchange File, generated: ".date("d M Y @ H:i")."-->";
print "\n<HeuristBaseURL>" . HEURIST_BASE_URL. "</HeuristBaseURL>";
print "\n<HeuristDBName>" . $system->dbname() . "</HeuristDBName>";
print "\n<HeuristProgVersion>".HEURIST_VERSION."</HeuristProgVersion>";

// *** MOST IMPORTANT ***
// ** Check this on structure import to make sure versions match **
// However use of XML tags should allow import even if structure has evolved
print "\n<HeuristDBVersion>".$db_version."</HeuristDBVersion>";

// TODO: Also need to output general properties of the database set in Structure > Properties / dvanced Properties

if(!$is_subset){
// Output each of the definition tables in turn

// ------------------------------------------------------------------------------------------
// defRecTypeGroups

doPrintTableXML('defRecTypeGroups');


// ------------------------------------------------------------------------------------------
// defDetailTypeGroups

doPrintTableXML('defDetailTypeGroups');

// ------------------------------------------------------------------------------------------
// defVocabularyGroups

doPrintTableXML('defVocabularyGroups');

// ------------------------------------------------------------------------------------------
// Detail Type ONTOLOGIES

doPrintTableXML('defOntologies');

}
// ------------------------------------------------------------------------------------------
// Detail Type TERMS
if(!$is_subset || $trm_IDs !== null){

    doPrintTableXML('defTerms', $trm_IDs);
}
// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)
if(!$is_subset || $rty_IDs !== null){

    doPrintTableXML('defRecTypes', $rty_IDs);
}
// ------------------------------------------------------------------------------------------
// DETAIL TYPES
if(!$is_subset || $dty_IDs !== null){

    doPrintTableXML('defDetailTypes', $dty_IDs);
}
// ------------------------------------------------------------------------------------------
// RECORD STRUCTURE
if(!$is_subset || $rty_IDs !== null){

    doPrintTableXML('defRecStructure', $rty_IDs);

}

if(!$is_subset){

// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

doPrintTableXML('defRelationshipConstraints');

// ------------------------------------------------------------------------------------------
// defFileExtToMimetype

doPrintTableXML('defFileExtToMimetype');

// ------------------------------------------------------------------------------------------
// defTranslations

doPrintTableXML('defTranslations');

// ------------------------------------------------------------------------------------------
// sysDashboard

doPrintTableXML('sysDashboard');

// ------------------------------------------------------------------------------------------
// defCalcFunctions

doPrintTableXML('defCalcFunctions');

// ------------------------------------------------------------------------------------------
// defCrosswalk

doPrintTableXML('defCrosswalk');

// ------------------------------------------------------------------------------------------
// defURLPrefixes

doPrintTableXML('defURLPrefixes');

}
// ------------------------------------------------------------------------------------------
// Output the following only if parameter switch set and user is an admin

if (!$includeUgrps) {
    print "\n\n<!-- User and group information not requested -->";
    print "\n\n</hml_structure>";
    return;
}

if (! $system->isAdmin() ) {
    print "\n\n<!-- You do not have sufficient privileges to list users and groups -->";
    print "\n</hml_structure>";
    return;
}
if(!$is_subset){

// ------------------------------------------------------------------------------------------
// sysUGrps

doPrintTableXML('sysUGrps');

// ------------------------------------------------------------------------------------------
// sysUsrGrpLinks

doPrintTableXML('sysUsrGrpLinks');

// ------------------------------------------------------------------------------------------
// usrHyperlinkFilters

doPrintTableXML('usrHyperlinkFilters');

// ------------------------------------------------------------------------------------------
// usrTags

doPrintTableXML('usrTags');

}

print "\n</hml_structure>";// end of file


/**
 * Prints the data of a given table as XML elements.
 *
 * Fetches rows from the specified table (optionally filtered by an ID)
 * and outputs each row as an XML element named after the table's prefix (e.g., `<rty>`),
 * with child elements for each field named after the field name.
 * String values are HTML-escaped. Special handling for originating DB IDs and record IDs
 * is applied similar to `do_print_table` in the SQL export script.
 *
 * @global \mysqli $mysqli The global mysqli database connection object.
 *
 * @param string $tname The name of the database table to export (e.g., 'defRecTypes').
 * @param array|null $ids (Optional) If provided and positive, filters the table records by this ID.
 *                The specific ID field used for filtering depends on the table's prefix
 *                (e.g., `rst_RecTypeID` for 'defRecStructure', or primary key for others).
 *                Defaults to 0 (no specific ID filter).
 * @return void
 */
function doPrintTableXML( $tname, $ids = [] )
{
    global $mysqli;

    $tname_tag = substr($tname,3);

    print "\n\n<$tname_tag>";


    $flds_list = mysql__select_assoc2($mysqli, 'SHOW COLUMNS FROM '.$tname);
    $flds_names = array_keys($flds_list);
    $flds = '`'.implode('`,`', $flds_names).'`';
    print "\n\n<!-- $flds -->";

    //get table prefix
    $id_field = $flds_names[0];
    $prefix = substr($id_field,0,3);
    $where = '';

    if(!empty($ids)){
        $ids = count($ids) === 1 ? "= {$ids[0]}" : 'IN (' . implode(',', $ids) . ')';
        $where = $prefix=='rst' ? " where rst_RecTypeID {$ids}" : " where {$id_field} {$ids}";
    }


    $query = "select {$flds} from {$tname}{$where}";
    $res = $mysqli->query($query);

    while ($row = $res->fetch_assoc()) {

        if($prefix=='rty' && !(@$row[$id_field]>0)) {continue;}

        print "<$prefix>";
        foreach($flds_list as $fld => $type){

            $val = $row[$fld];
            if(strpos($type,'text')!==false || strpos($type,'varchar')!==false){
                $val = htmlspecialchars($mysqli->real_escape_string($val));
            }elseif(strpos($fld,'OriginatingDBID')!==false){
                if(!($val>0)){
                    $val = HEURIST_DBID;
                }
            }elseif(strpos($fld,'IDInOriginatingDB')!==false){
                if(HEURIST_DBID>0 && !($val>0)){
                    $val = $row[$id_field];
                }
            }
            print "<$fld>$val</$fld>";
        }
        print "</$prefix>\n";

    }
    print "</$tname_tag>";

    $res->close();
}

/**
 * @param string $entity
 * @param string|int|array $IDs
 * @return array|null
 */
function prepareMixedIds(string $entity, $IDs){

    $preparedIDs = null;
    $entityFunc = $entity === 'rty' ? 'getRecTypeLocalID' : 'getDetailTypeLocalID';
    $entityFunc = $entity === 'trm' ? 'getTermLocalID' : $entityFunc;

    if(is_array($IDs) || strpos($IDs, ',') !== false){

        $IDparts = is_string($IDs) ? explode(',', $IDs) : $IDs;
        $preparedIDs = [];

        foreach($IDparts as $conceptID){

            $ID = intval(ConceptCode::$entityFunc($conceptID));

            if($ID > 0){
                $preparedIDs[] = $ID;
            }
        }
    }else{
        $preparedIDs = intval(ConceptCode::$entityFunc((string)$IDs));
        $preparedIDs = $preparedIDs > 0 ? [$preparedIDs] : null;
    }

    return empty($preparedIDs) ? null : $preparedIDs;
}

function getRelevantIDs(array &$recTypeIDs, array &$termIDs, bool $getConnectedRecTypes = false, bool $getTerms = false){

    global $mysqli;

    $query = <<<QUERY
    SELECT rst_RecTypeID, dty_Type, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree 
    FROM defDetailTypes 
    INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID 
    WHERE (dty_Type = 'enum' OR dty_Type = 'resource') AND rst_RecTypeID = ?
    QUERY;

    $newRtyIDs = [];
    foreach($recTypeIDs as $rtyID){

        if(!isPositiveInt($rtyID)){
            continue;
        }

        $results = mysql__select_param_query($mysqli, $query, ['i', $rtyID]);

        if(!$results){
            continue;
        }

        while($row = $results->fetch_assoc()){

            if(!empty($row['dty_PtrTargetRectypeIDs']) && $getConnectedRecTypes){

                $rtyIDs = prepareIds($row['dty_PtrTargetRectypeIDs']);
                $newRtyIDs = array_merge($newRtyIDs, $rtyIDs);

                continue;
            }

            if(!empty($row['dty_JsonTermIDTree']) && $getTerms){

                $trmIDs = prepareIds($row['dty_JsonTermIDTree']);
                $termIDs = array_merge($termIDs, $trmIDs);

                continue;
            }
        }
    }

    if(!empty($termIDs)){
        $termIDs = array_unique($termIDs, SORT_NUMERIC);
    }
    if(!empty($newRtyIDs)){
        $recTypeIDs = array_unique(array_merge($recTypeIDs, $newRtyIDs), SORT_NUMERIC);
    }
}
?>
