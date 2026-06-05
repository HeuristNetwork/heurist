<?php
/**
* verifyFieldUsage.php - Checks for usage of given detail field (dty) by concept code within each database.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7
*/

define('ADMIN_PWD_REQUIRED', 1);
define('PDIR', '../../'); // need for proper path to js and css

$conceptID = trim($_POST['code'] ?? '');

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

if (!preg_match('/^([1-9][0-9]*)-([1-9][0-9]*)$/', $conceptID, $matches)) {
    echo '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">';
    echo 'Concept code is not defined or has invalid format. Expected format: dbID-recID, for example 2-72.';
    echo '</div>';
    exit;
}

$dbID = intval($matches[1]);
$recID = intval($matches[2]);

$mysqli = $system->getMysqli();
?>

<script>
window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>')
</script>

<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
    <h4>
        This list shows use of field <?php echo $conceptID;?> within each database:
        1) usage in record structures (defRecStructure)
        2) occurrences in recDetails
    </h4>

    <table border="1" cellpadding="4" cellspacing="0">
        <thead>
            <tr>
                <th>Database</th>
                <th>Local field ID</th>
                <th>Record structure usages</th>
                <th>Record detail occurrences</th>
            </tr>
        </thead>
        <tbody>

<?php
$databases = mysql__getdatabases4($mysqli, true);

foreach ($databases as $db_name) {

    // Keep only safe DB-name characters.
    $db_name = preg_replace(REGEX_ALPHANUM, '', $db_name);

    if ($db_name === '') {
        continue;
    }

    // Find local field id by original concept code.
    $query = 'SELECT dty_ID '
        ." FROM `$db_name`.defDetailTypes "
        .' WHERE dty_OriginatingDBID = '.$dbID
        .' AND dty_IDInOriginatingDB = '.$recID;

    $dty_ID = intval(mysql__select_value($mysqli, $query));

    if ($dty_ID <= 0) {
        continue;
    }

    // Count usages in record structures.
    $query = 'SELECT COUNT(DISTINCT rst_RecTypeID) '
        ." FROM `$db_name`.defRecStructure "
        .' WHERE rst_DetailTypeID = '.$dty_ID;

    $cnt1 = intval(mysql__select_value($mysqli, $query));

    // Count actual values in record details.
    $query = 'SELECT COUNT(dtl_RecID) '
        ." FROM `$db_name`.recDetails "
        .' WHERE dtl_DetailTypeID = '.$dty_ID;

    $cnt2 = intval(mysql__select_value($mysqli, $query));

    if ($cnt1 > 0 || $cnt2 > 0) {
        echo '<tr>';
        echo '<td>'.htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8').'</td>';
        echo '<td>'.$dty_ID.'</td>';
        echo '<td>'.$cnt1.'</td>';
        echo '<td>'.$cnt2.'</td>';
        echo '</tr>';
    }
}
?>

        </tbody>
    </table>

    <p>[end report]</p>
</div>