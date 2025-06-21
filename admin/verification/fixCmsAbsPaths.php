<?php
    /*
    * Copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * https://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    * fixCmsAbsPaths.php - Replaces absolute paths with relative paths in Heurist CMS records.
    *
    * @fileOverview This script iterates through all databases and specifically targets CMS-related
    *               record types (identified by originating DB ID 99, types 51 and 52, typically
    *               CMS Homepage and CMS Webpage) and their content fields (detail type from
    *               originating DB ID 2, type 4, typically 'Extended description').
    *               It searches for absolute URLs that match a predefined list of server hostnames
    *               (e.g., heuristref.net, heurist.sydney.edu.au) followed by common Heurist
    *               installation paths (e.g., /HEURIST/, /h5-alpha/) and replaces them with
    *               relative paths (e.g., './'). This helps ensure portability and resilience
    *               of internal links within CMS content if the server or Heurist installation
    *               path changes.
    *               Requires admin password.
    *
    * @package     Heurist academic knowledge management system
    * @subpackage  /admin/verification
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @since       3.1
    */

define('ADMIN_PWD_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordsBatch.php';

//clear url parameters
?>
<script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>')</script>


<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
    <p>Fix absolute paths in web page content</p>
<?php

$mysqli = $system->getMysqli();

//find all database
$databases = mysql__getdatabases4($mysqli, false);

//
$servers = array('https:\/\/heuristref.net', 'https:\/\/heurist.sydney.edu.au', 'https:\/\/heuristplus.sydney.edu.au', 'https:\/\/heurist.huma-num.fr', 'https:\/\/heuristest.fdm.uni-hamburg.de:443');
if (!isEmptyArray($absolutePathsToRemoveFromWebPages)){
    foreach($absolutePathsToRemoveFromWebPages as $srv){
        $srv = str_replace("/","\/",$srv);
        $servers[] = $srv;
    }
}
print 'Servers: '.implode('<br>',$servers).'<br>';

 __correctAbsPaths();

/**
 * Main function to iterate through databases and correct absolute paths in CMS content.
 *
 * It identifies relevant CMS record types and detail types, then uses RecordsBatch::detailsReplace
 * with a custom callback `replaceAbsPathinCMS` to perform the replacements.
 *
 * @global hserv\System $system The Heurist system object.
 * @global \mysqli $mysqli The mysqli database connection object.
 * @global array $databases List of database names on the server.
 * @return void
 */
function __correctAbsPaths(){

    global $system, $mysqli, $databases;

    $dbRecDetails = new RecordsBatch($system, null);





    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

/*
    *       recIDs - list of records IDS to be processed or 'ALL'
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added,replaced or deleted
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value (if missed - replace all occurences),  rVal - replace value,  subs= 1 | 0
    *       for delete: sVal, subs= 1 | 0
    *       tag  = 0|1  - add system tag to mark processed records
*/

        $query = 'select rty_ID from defRecTypes where rty_OriginatingDBID=99 and rty_IDInOriginatingDB in (51, 52)';
        $rty_IDs = mysql__select_list2($mysqli, $query);

        $query = 'select dty_ID from defDetailTypes where dty_OriginatingDBID=2 and dty_IDInOriginatingDB=4';
        $dty_ID = mysql__select_value($mysqli, $query);

        $data = array(
        'recIDs'=>'ALL',
        'rtyID'=>$rty_IDs,
        'dtyID'=>$dty_ID,
        'dt_extended_description'=>$dty_ID,
        'sVal'=>'https://heurist',
        'rVal'=>'replaceAbsPathinCMS',
        'substr'=>1, //substring
        'debug'=>0,
        'tag'=>0
        );

        print '<h4>'.htmlspecialchars($db_name).'</h4><br>';

        print 'Rectypes: '.htmlspecialchars(implode(',',$rty_IDs)).' Fields: '.intval($dty_ID).'<br>';

        $dbRecDetails->setData($data);
        $res = $dbRecDetails->detailsReplace();
        if(!$res){
            print 'ERROR: '.$system->getErrorMsg();
        }

        print '<hr>';
    }//for

}

/**
 * Replaces occurrences of a given absolute path regex pattern with a relative path ('./') in the provided text.
 *
 * @param string $absPath The regex pattern for the absolute path to find and replace.
 * @param string &$text   The text content to modify (passed by reference).
 * @return int The number of replacements made.
 */
function replaceAbsPath($absPath, &$text){

        $cnt = 0;
        $matches = array();

        if(preg_match($absPath, $text, $matches)){

            $res = preg_replace($absPath, './', $text);
            if($res!=null && $text != $res){
                $text = $res;
                $cnt = $cnt + count($matches);
            }

            foreach ($matches as $fnd) {print $fnd.' &nbsp;&nbsp;&nbsp; ';}
        }
        return $cnt;
}

/**
 * Callback function used by RecordsBatch::detailsReplace to modify CMS content.
 *
 * This function takes the text value of a CMS detail field and applies a series of
 * regular expression replacements to convert known absolute Heurist URLs into
 * relative paths. It iterates through predefined server hostnames and common Heurist
 * installation directory names.
 *
 * @global array $servers An array of regex patterns representing known server hostnames.
 * @param int    $recID The ID of the CMS record being processed.
 * @param string $val   The text content of the detail field (e.g., extended description).
 * @return string The modified text content with absolute paths replaced.
 */
function replaceAbsPathinCMS($recID, $val){

    global $servers;



    $paths0 = array('\/HEURIST', '\/html', '');
    $paths = array('heurist', 'h5-alpha', 'h5-ao', 'h5', 'h5-beta', 'h6-alpha', 'h6-ao', 'h6', 'h6-beta');

    $cnt = 0;

    foreach ($servers as $srv) {
        foreach ($paths0 as $path0) {
            foreach ($paths as $path) {
                $absPath = '/'.$srv.$path0.'\/'.$path.'\//i';

                $cnt = $cnt + replaceAbsPath($s, $val);
            }
        }
    }


    //report if anything has been fixed
    if($cnt > 0){
        print '<br>RecID: '.$recID.'. Replaced '.$cnt.' entries<br>';
    }

    return $val;
}
?>
