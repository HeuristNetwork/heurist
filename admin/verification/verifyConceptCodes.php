<?php
/**
* verifyConceptCodes.php - Checks for duplicate concept codes within each database.
*
* @fileOverview This script iterates through all Heurist databases on the server and checks
*               for duplicated concept codes (i.e., the same combination of `xxx_OriginatingDBID`
*               and `xxx_IDInOriginatingDB`) within:
*               - Record types (`defRecTypes`)
*               - Detail types (`defDetailTypes`)
*               - Terms (`defTerms`)
*               Duplicate concept codes are an error condition, as they can lead to ambiguity
*               in identifying and linking definitions across databases. The script outputs
*               an HTML report listing any duplicate concept codes found, grouped by database
*               and definition table.
*               Requires admin password.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/

define('ADMIN_PWD_REQUIRED', 1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Duplicate concept codes in Heurist definitions</title>
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

        td {
            padding: 3px 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        .section-row td {
            padding-top: 10px;
            font-style: italic;
            font-weight: bold;
            background: #f5f5f5;
        }

        .header-row td {
            font-weight: bold;
            background: #eee;
        }

        h4 {
            margin: 0;
            padding-top: 20px;
            font-size: 15px;
        }

        .end-report {
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<script>
window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>');
</script>

<h1>Duplicate concept codes in Heurist definitions</h1>

<div class="report-description">
    This report checks all Heurist databases on this server and identifies duplicated
    concept codes within each database. A duplicate concept code means that two or more
    record types, detail types or terms share the same combination of originating database ID
    and ID-in-originating-database. This is an error condition because it can make definition
    matching ambiguous when importing, synchronising or comparing databases, although it may
    have little visible effect on local database operations.
</div>

<div>
<?php


$mysqli = $system->getMysqli();

    //1. find all database
    $databases = mysql__getdatabases4($mysqli, true);

    foreach ($databases as $idx=>$db_name){

        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);//for snyk

        //RECORD TYPES

        $query = 'SELECT rty_OriginatingDBID, rty_IDInOriginatingDB, count(rty_ID) as cnt '
            ." FROM `$db_name`.defRecTypes "
            .' WHERE  rty_OriginatingDBID>0 AND rty_IDInOriginatingDB>0 '
            .' GROUP BY rty_OriginatingDBID, rty_IDInOriginatingDB HAVING cnt>1';

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {

               $is_found = true;

               $query = 'SELECT rty_ID, rty_Name, CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB), rty_NameInOriginatingDB '
               ." FROM `$db_name`.defRecTypes "
                .' WHERE  rty_OriginatingDBID='.intval($row[0]).' AND rty_IDInOriginatingDB='.intval($row[1])
                .' ORDER BY rty_OriginatingDBID, rty_IDInOriginatingDB';

               $res2 = $mysqli->query($query);
               if (!$res2) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }
               while ($row2 = $res2->fetch_row()) {
                      array_push($rec_types, array_map('htmlspecialchars',$row2));
               }
        }

        //FIELD TYPES

        $query = 'SELECT dty_OriginatingDBID, dty_IDInOriginatingDB, count(dty_ID) as cnt '
            ." FROM `$db_name`.defDetailTypes "
            .' WHERE  dty_OriginatingDBID>0 AND dty_IDInOriginatingDB>0 '
            .' GROUP BY dty_OriginatingDBID, dty_IDInOriginatingDB HAVING cnt>1';

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        $not_found = true;
        while ($row = $res->fetch_row()) {

               $is_found = true;

               $query = 'SELECT dty_ID, dty_Name, CONCAT(dty_OriginatingDBID,"-",dty_IDInOriginatingDB), dty_NameInOriginatingDB '
               ." FROM `$db_name`.defDetailTypes "
                .' WHERE  dty_OriginatingDBID='.intval($row[0]).' AND dty_IDInOriginatingDB='.intval($row[1])
                .' ORDER BY dty_OriginatingDBID, dty_IDInOriginatingDB';

               $res2 = $mysqli->query($query);
               if (!$res2) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }
               while ($row2 = $res2->fetch_row()) {
                      array_push($det_types, array_map('htmlspecialchars',$row2));
               }
        }

        //TERMS

        $query = 'SELECT trm_OriginatingDBID, trm_IDInOriginatingDB, count(trm_ID) as cnt '
               ." FROM `$db_name`.defTerms "
            .' WHERE  trm_OriginatingDBID>0 AND trm_IDInOriginatingDB>0 '
            .' GROUP BY trm_OriginatingDBID, trm_IDInOriginatingDB HAVING cnt>1';

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {

               $is_found = true;

               $query = 'SELECT trm_ID, trm_Label, CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB), trm_NameInOriginatingDB '
                ." FROM `$db_name`.defTerms "
                .' WHERE  trm_OriginatingDBID='.intval($row[0]).' AND trm_IDInOriginatingDB='.intval($row[1])
                .' ORDER BY trm_OriginatingDBID, trm_IDInOriginatingDB';

               $res2 = $mysqli->query($query);
               if (!$res2) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }
               while ($row2 = $res2->fetch_row()) {
                      array_push($terms, array_map('htmlspecialchars',$row2));
               }
        }

        if($is_found){
            print '<h4>'.htmlspecialchars(substr($db_name, 4)).'</h4>';
            print '<table>';

            if(!isEmptyArray($rec_types)){
                print '<tr class="section-row"><td colspan="4">Record types</td></tr>';
                print '<tr class="header-row">'
                    .'<td>Internal code</td>'
                    .'<td>Name in this DB</td>'
                    .'<td>Concept code</td>'
                    .'<td>Name in origin DB</td>'
                    .'</tr>';

                foreach($rec_types as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29), TD, htmlspecialchars(implode(chr(29), $row)));
                    print TR_S.$list.TR_E;
                }
            }

            if(!isEmptyArray($det_types)){
                print '<tr class="section-row"><td colspan="4">Detail types</td></tr>';
                print '<tr class="header-row">'
                    .'<td>Internal code</td>'
                    .'<td>Name in this DB</td>'
                    .'<td>Concept code</td>'
                    .'<td>Name in origin DB</td>'
                    .'</tr>';

                foreach($det_types as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29), TD, htmlspecialchars(implode(chr(29), $row)));
                    print TR_S.$list.TR_E;
                }
            }

            if(!isEmptyArray($terms)){
                print '<tr class="section-row"><td colspan="4">Terms</td></tr>';
                print '<tr class="header-row">'
                    .'<td>Internal code</td>'
                    .'<td>Label in this DB</td>'
                    .'<td>Concept code</td>'
                    .'<td>Name in origin DB</td>'
                    .'</tr>';

                foreach($terms as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29), TD, htmlspecialchars(implode(chr(29), $row)));
                    print TR_S.$list.TR_E;
                }
            }

            print '</table>';
        }

    }//while  databases
print '<div class="end-report">[end report]</div>';
?>
</div>
</body>
</html>