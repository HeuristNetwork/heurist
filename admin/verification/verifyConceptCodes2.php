<?php
/**
* verifyConceptCodes2.php - Checks for missing `xxx_IDInOriginatingDB` values in definitions.
*
* @fileOverview This script iterates through all Heurist databases (excluding 'hdb_DEF19')
*               and checks record types, detail types, and terms for missing or invalid
*               `xxx_IDInOriginatingDB` values when `xxx_OriginatingDBID` is set (greater than 0).
*               A valid `xxx_IDInOriginatingDB` should be a positive integer. Missing or zero
*               values indicate an inconsistency in how the definition is linked to its origin.
*               The script outputs an HTML report listing definitions with such issues,
*               grouped by database and definition table.
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
    <title>Missing ID-in-originating-database values in Heurist definitions</title>
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

        tr:first-child td {
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

<h1>Missing ID-in-originating-database values in Heurist definitions</h1>

<div class="report-description">
    This report checks Heurist databases on this server, excluding DEF19 databases,
    and identifies definitions where an originating database ID is set but the matching
    ID-in-originating-database value is missing, zero or otherwise invalid. These issues
    indicate inconsistent concept-code metadata: the definition claims to originate from
    another registered database, but does not record the original definition ID needed to
    resolve that link. The report is grouped by database and definition table.
</div>

<div>
<?php


$mysqli = $system->getMysqli();

    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while ($row = $res->fetch_row()) {
        if( strpos($row[0], 'hdb_DEF19')===0 || strpos($row[0], 'hdb_def19')===0) {continue;}

        if( strpos($row[0], HEURIST_DB_PREFIX)===0 ){
                $databases[] = $row[0];
        }
    }

    $need_Details = true;
    $need_Terms = false;

    foreach ($databases as $idx=>$db_name){

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);

        $query = 'SELECT sys_dbSubVersion from `'.$db_name.'`.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);

        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES

        $query = 'SELECT rty_ID, rty_Name, rty_NameInOriginatingDB, rty_OriginatingDBID, rty_IDInOriginatingDB FROM `'
            .$db_name.'`.defRecTypes WHERE rty_OriginatingDBID>0 AND '
            ."(rty_IDInOriginatingDB='' OR rty_IDInOriginatingDB=0 OR rty_IDInOriginatingDB IS NULL)";

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($rec_types, array_map('htmlspecialchars',$row));
        }

        if($need_Details){

        //FIELD TYPES
        $query = 'SELECT dty_ID, dty_Name, dty_NameInOriginatingDB, dty_OriginatingDBID, dty_IDInOriginatingDB FROM `'
            .$db_name.'`.defDetailTypes WHERE  dty_OriginatingDBID>0 AND '
            ."(dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";


        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($det_types, array_map('htmlspecialchars',$row));
        }

        }
        if($need_Terms){

        //TERMS
        $query = 'SELECT trm_ID, trm_Label, trm_NameInOriginatingDB, trm_OriginatingDBID, trm_IDInOriginatingDB FROM `'
            .$db_name.'`.defTerms WHERE  trm_OriginatingDBID>0 AND (NOT (trm_IDInOriginatingDB>0)) ';

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($terms, array_map('htmlspecialchars',$row));
        }

        }

        if($is_found){
            print '<h4 style="margin:0;padding-top:20px">'.htmlspecialchars(substr($db_name,4)).'</h4><table style="font-size:12px">';

            print '<tr><td>Internal code</td><td>Name in this DB</td><td>Name in origin DB</td><td>xxx_OriginDBID</td><td>xxx_IDinOriginDB</td></tr>';

            if(!empty($rec_types)){
                print '<tr><td colspan=5><i>Record types</i></td></tr>';
                foreach($rec_types as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$row)));
                    print TR_S.$list.TR_E;
                }
            }
            if(!empty($det_types)){
                print '<tr><td colspan=5>&nbsp;</td></tr>';
                print '<tr><td colspan=5><i>Detail types</i></td></tr>';
                foreach($det_types as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$row)));
                    print TR_S.$list.TR_E;
                }
            }
            if(!empty($terms)){
                print '<tr><td colspan=5><i>Terms</i></td></tr>';
                foreach($terms as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$row)));
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
