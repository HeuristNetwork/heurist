<?php
/**
* orderLinksByTitle.php - Reorders multi-valued record pointer fields alphabetically by the title of the pointed-to records.
*
* @fileOverview This script is an administrative utility that processes a specified multi-valued
*               record pointer field within a given record type. For each source record, it
*               retrieves all its linked records through this field, orders these linked records
*               alphabetically by their `rec_Title`, and then updates the `dtl_Value` in the
*               `recDetails` table to reflect this new order. This ensures that when multiple
*               records are linked via such a field, they appear in a consistent, alphabetized order.
*               Requires admin privileges and `rty_ID` (record type ID) and `dty_ID` (detail type ID)
*               as request parameters.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/

require_once dirname(__FILE__).'/../../autoload.php';

$rv = array();

// init main system class
$system = new hserv\System();

if(!$system->init(@$_REQUEST['db'])){
    $response = $system->getError();
    print json_encode($response);
    exit;
}
if (!$system->isAdmin()) {
    print 'To perform this action you must be logged in  as Administrator of group \'Database Managers\'';
    exit;
}

if(!(@$_REQUEST['rty_ID']>0 && @$_REQUEST['dty_ID']>0)){
    print 'You have to define rty_ID (rectype id) and dty_ID (field id) parameters';
    exit;
}

$mysqli = $system->getMysqli();

//3, 134

$query = 'SELECT dtl_ID, r1.rec_ID, dtl_Value, r2.rec_Title FROM recDetails, Records r1, Records r2 '
.' where r1.rec_ID=dtl_RecID and r1.rec_RecTypeID='.intval($_REQUEST['rty_ID']).' and dtl_DetailTypeID='.intval($_REQUEST['dty_ID']
).' and dtl_Value=r2.rec_ID order by r1.rec_ID, r2.rec_Title ';
// and r1.rec_ID=494461
$res = $mysqli->query($query);

$rec_ID = 0;

$vals = array();
$titles = array();
$ids = array();

$cnt = 0;

if($res){
    while ($row = $res->fetch_row()) {

        if($rec_ID!=$row[1]){
            $cnt = $cnt + updateDtlValues($mysqli, $ids, $vals, $titles);
            $rec_ID=$row[1];
            $vals = array();
            $ids = array();
            $titles = array();
        }
        $ids[]  = intval($row[0]);
        $vals[] = intval($row[2]);

    }
    $cnt = $cnt + updateDtlValues($mysqli, $ids, $vals, $titles);
}

print $cnt.' records updated';

/**
 * Updates the dtl_Value for a set of detail records to reorder them.
 *
 * Given arrays of detail IDs and their corresponding target record IDs (values),
 * this function sorts the detail IDs and then updates each dtl_Value with the
 * target record ID from the original (but now implicitly title-sorted due to query order) $vals array.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param array<int> $ids Array of detail IDs (dtl_ID) for a specific source record and detail type.
 *                        These are assumed to correspond to the $vals order as retrieved from the initial query.
 * @param array<int> $vals Array of target record IDs (dtl_Value) for the given details,
 *                         ordered by the target records' titles from the initial query.
 * @param array<string> $titles Array of titles of the target records (unused in the current implementation
 *                              but fetched by the calling query, likely for original sorting intent).
 * @return int Returns 1 if any updates were made (i.e., if there was more than one value to order),
 *             otherwise returns 0.
 */
function updateDtlValues($mysqli, $ids, $vals, $titles){

    if(is_array($vals) && count($vals)>1){

        sort($ids);
        $k = 0;
        foreach ($ids as $dt) { //sorted dtl_ID
            $query = "update recDetails set dtl_Value=".$vals[$k].' where dtl_ID='.$ids[$k];

            $res = $mysqli->query($query);
            if ($mysqli->error) {
                print 'Error for query '.htmlspecialchars($query).' '.htmlspecialchars($mysqli->error);
                exit;
            }

            $k++;
        }
        return 1;
    }else{
        return 0;
    }
}
