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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Field usage by concept code in Heurist databases</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 8px 0;
            color: #333;
        }

        .report-description {
            max-width: 980px;
            margin: 0 0 18px 0;
            padding: 10px 12px;
            border-left: 4px solid #666;
            background: #f5f5f5;
            line-height: 1.45;
        }

        table {
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 16px;
        }

        th,
        td {
            padding: 4px 8px;
            border: 1px solid #ccc;
            vertical-align: top;
        }

        th {
            font-weight: bold;
            background: #eee;
            text-align: left;
        }

        .concept-code {
            font-family: monospace;
            font-weight: bold;
        }

        .end-report {
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<script>
window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>');
</script>

<h1>Field usage by concept code in Heurist databases</h1>

<div class="report-description">
    This report checks all Heurist databases on this server for use of the detail field
    identified by concept code
    <span class="concept-code"><?php echo htmlspecialchars($conceptID, ENT_QUOTES, 'UTF-8'); ?></span>.
    For each database, the script first resolves the matching local detail type ID from
    <code>defDetailTypes</code> using the supplied originating database ID and
    ID-in-originating-database. It then reports whether that local field is used in
    record structures via <code>defRecStructure</code>, and whether actual values exist
    for the field in <code>recDetails</code>.
</div>

<?php
if (!preg_match('/^([1-9][0-9]*)-([1-9][0-9]*)$/', $conceptID, $matches)) {
?>
<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
    Concept code is not defined or has invalid format. Expected format: dbID-recID, for example 2-72.
</div>
</body>
</html>
<?php
    exit;
}   

$dbID = intval($matches[1]);
$recID = intval($matches[2]);

$mysqli = $system->getMysqli();
?>

<table>
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

<div class="end-report">[end report]</div>

</body>
</html>