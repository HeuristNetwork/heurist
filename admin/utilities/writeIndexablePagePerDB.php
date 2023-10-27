<?php

/**
* writeIndexablePagePerDB.php: Creates a html page containing details about each database 
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// example:
//  sudo php -f /var/www/html/heurist/admin/utilities/writeIndexablePagePerDB.php -- -db=database_1,database_2
//  If dbs are not specified, all dbs are processed 

/*
 This routine:
 Creates a html page for each database, containing details such as:
    - Name + Logo
    - Hosting server
    - Database URL
    - Generated websites
    - Database registration ID, description, ownership, copyright, etc...
    - Database Owner details
    - Record count, File count
    - Last record update and last structure update
*/

// Default values for arguments
$arg_database = null; // databases
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

if (@$argv) {

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else if(strpos($argv[$i],'-db=')===0){
                $ARGV['-db'] = substr($argv[$i],4);
            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }

    if (@$ARGV['-db']) $arg_database = explode(',', $ARGV['-db']);

}else{
    /*web browser
    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;">';
    $tabs = "</div>".$tabs0;

    if(array_key_exists('db', $_REQUEST)){
        $arg_database = explode(',',$_REQUEST['db']);
    }*/

    exit('This function is for command line execution');
}

define('HEURIST_DIR', dirname(__FILE__).'/../../');

require_once dirname(__FILE__).'/../../configIni.php'; // read in the configuration file
require_once dirname(__FILE__).'/../../hsrv/consts.php';
require_once dirname(__FILE__).'/../../hsrv/System.php';
require_once dirname(__FILE__).'/../../hsrv/records/search/recordFile.php';
require_once dirname(__FILE__).'/../../hsrv/utilities/dbUtils.php';

//retrieve list of databases
$system = new System();
if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

// Setup server name
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) define('HEURIST_SERVER_NAME', $serverName);//'heurist.huma-num.fr'

if(!defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME)){ // filter_var(HEURIST_SERVER_NAME, FILTER_VALIDATE_IP)
    exit('The script was unable to determine the server\'s name, please define it within heuristConfigIni.php then re-run this script.');
}

// Setup Base URL
$host = getHostParams();
$base_url = '';
if(defined('HEURIST_BASE_URL_PRO')){
    $base_url = HEURIST_BASE_URL_PRO;
}else{
    $base_url = 'https://' . HEURIST_SERVER_NAME . '/heurist/';
}

if(empty($base_url) || strcmp($base_url, 'http://') == 0 || strcmp($base_url, 'https://') == 0){
    exit('The script was unable to determine the server\'s name, please define it within heuristConfigIni.php then re-run this script.');
}

if(substr($base_url, -1, 1) != '/'){
    $base_url .= '/';
}
if(strpos($base_url, '/heurist/') === false){
    $base_url = rtrim($base_url, '/') . '/heurist/';
}

$mysqli = $system->get_mysqli();
$databases = mysql__getdatabases4($mysqli, false);

// TODO: Should be using setting for web root in configIni.php 
$index_dir = dirname(__FILE__)."/../../../HarvestableDatabaseDescriptions";

$is_dir_writable = folderExists($index_dir, true);

if($is_dir_writable === -1){ // Create directory
    $res = folderCreate2($index_dir, '', true);
    if($res !== ''){
        exit('Unable to create directory for Database Pages'.$eol.$res.$eol);
    }
}else if($is_dir_writable === -2 || $is_dir_writable === -3){
    $msg = $tabs0 . ($is_dir_writable === -2 ? 'Unable to write to directory for Database Pages' : 
        'The Database Pages directory has been replaced by a file, that cannot be removed.').$eol.'Please remove it and run this script again.';
    exit($msg);
}

$value_to_replace = array('{db_name}','{db_desc}','{db_url}','{db_website}','{db_rights}','{db_owner}','{db_id}','{db_logo}','{db_dname}',
                          '{server_host}','{server_url}','{owner_name}','{owner_email}',
                          '{rec_count}','{file_count}','{rec_last}','{struct_last}','{struct_names}','{date_now}');

//
// File content for (HarvestableDatabaseDescriptions/index.html)
//
$index_page = '<!DOCTYPE html>'
. '<html>'

    . '<head>'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name=”generator” content=”Heurist”>'
        . '<meta name="keywords" content="Heurist, Heurist databases, Digital Humanitites, Database management">' //{sys_kywds}
        . '<meta http-equiv="content-type" content="text/html; charset=UTF-8">'
        . '<title>Index of Heurist Databases</title>'

        . '<style>'
            . '.desc{display: inline-block; max-width: 800px; text-align: justify;}'
            . '.heurist_logo{object-fit: cover;object-position: 5% 0;width: 40px;height: 38px;vertical-align: -12px;}'
        . '</style>'
    . '</head>'

    . '<body>'
        . '<div style="margin: 10px 0px;">'
            . ' <img src="'.$base_url.'hclient/assets/branding/h4logo_small.png" alt="Heurist logo" class="heurist_logo">'
            . ' <strong>Heurist database builder for Humanities research </strong>'
            . ' (<a href="https://HeuristNetwork.org" target="_blank" rel="noopener">https://HeuristNetwork.org</a>)'
        . '</div>'

        . '<div style="margin: 10px 5px 15px;">'
            . 'Databases and websites on this server (<a href="'.$base_url.'" target=_blank>'.$base_url.'</a>)'
        . '</div>'

        . '<div style="margin-left: 10px;">'
            . '{databases}'
        . '</div>'
    . '</body>'

. '</html>';
//
// Format for each row of database details within index.html
//
$index_row = '<strong>{db_name}</strong> (<a href="{db_page_link}" target=_blank>database page</a>)<br>' // <strong>{db_dname} ({db_name})</strong>
            . '{website_url}<br>'
            . '<span class="desc">{db_desc}</span>';
$index_row_replace = array('{db_name}', '{db_page_link}', '{website_url}', '{db_desc}');

//
// File content for each database file (HarvestableDatabaseDescriptions/{database_name}.html)
//
$template_page = '<!DOCTYPE html>'
. '<html>'

    . '<head>'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name=”generator” content=”Heurist”>'
        . '<meta name="description" content="{db_desc}">'
        . '<meta name="keywords" content="Heurist, Heurist database, Digital Humanitites, Database management, {db_name}, {db_dname}, {db_owner}">' //{sys_kywds}
        . '<meta name="author" content="{db_owner}">' //{owner_name}
        . '<meta http-equiv="content-type" content="text/html; charset=UTF-8">'
        . '<title>Heurist DB {db_name} on {server_host} updated {date_now}</title>'

        . '<style>'
            . '.dtl_row{display: table-row}'
            . '.dtl_head{ display: table-cell; width: 175px; }' // 25%
            . '.dtl_value{ display: table-cell; width: 800px; }' // 70%
            . '.dtl_row > span{ padding-bottom: 10px; }'
            . 'img{ vertical-align:middle; }'
            . '.db_logo{ max-width: 120px; max-height: 120px; padding-left: 20px; }'
            . '.heurist_logo{ background-color: #364050; max-width: 150px; max-height: 40px; margin-right: 10px; border-radius: 25px; }'
        . '</style>'
    . '</head>'

    . '<body>'
        . '<div style="margin: 10px 0px;">'
            . '<img src="'.$base_url.'hclient/assets/branding/h4logo_small.png" alt="Heurist logo" class="heurist_logo">'
            . ' <strong>Heurist database builder for Humanities research </strong>'
            . ' (<a href="https://HeuristNetwork.org" target="_blank" rel="noopener">https://HeuristNetwork.org</a>)'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Database name:</span>'
            . '<span class="dtl_value">{db_name} <img class="db_logo" src="{db_logo}" alt="Database logo"></img></span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Hosting server:</span>'
            . '<span class="dtl_value">{server_host} (find a db: <a href="{server_url}" target=_blank>{server_url}</a>)</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Database access:</span>'
            . '<span class="dtl_value"><a href="{db_url}" target=_blank>{db_url}</a></span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Generated website(s):</span>'
            . '<span class="dtl_value">{db_website}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Display name:</span>'
            . '<span class="dtl_value">{db_dname}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Description:</span>'
            . '<span class="dtl_value">{db_desc}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Copyright:</span>'
            . '<span class="dtl_value">{db_rights}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Ownership:</span>'
            . '<span class="dtl_value">{db_owner}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Database owner:</span>'
            . '<span class="dtl_value">{owner_name} [ <a href="mailto:{owner_email}">{owner_email}</a> ]</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Record count:</span>'
            . '<span class="dtl_value">{rec_count}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Files referenced:</span>'
            . '<span class="dtl_value">{file_count}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Data last updated:</span>'
            . '<span class="dtl_value">{rec_last}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Structure last updated:</span>'
            . '<span class="dtl_value">{struct_last}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Registration ID:</span>'
            . '<span class="dtl_value">{db_id}</span>'
        . '</div>'

        . '<div class="dtl_row">'
            . '<span class="dtl_head">Entity types / Record types:</span>'
            . '<span class="dtl_value">{struct_names}</span>'
        . '</div>'
    . '</body>'

. '</html>';

set_time_limit(0); //no limit
ini_set('memory_limit','1024M');

$today = date('Y-m-d'); //'d-M-Y'
$pages_made = 0;
$list_is_array = is_array($arg_database);

$index_databases = array(); // array of databases with websites (is inserted, with links, into index.html)

foreach ($databases as $idx=>$db_name){

    if($list_is_array && !in_array($db_name, $arg_database)){
        continue;
    }

    echo $tabs0.$db_name.' Starting'.$eol;
    
    $res = mysql__usedatabase($mysqli, $db_name);
    if(!$res){
        echo $tabs0.@$res[1].$eol;
        continue;
    }

/*
0 => '{db_name}', 1 => '{db_desc}', 2 => '{db_url}', 3 => '{db_website}', 4 => '{db_rights}', 5 => '{db_owner}', 6 => '{db_id}', 7 => '{db_logo}', 8 => '{db_dname}'
9 => '{server_host}', 10 => '{server_url}', 11 => '{owner_name}', 12 => '{owner_email}',
13 => '{rec_count}', 14 => '{file_count}', 15 => '{rec_last}', 16 => '{struct_last}', 17 => '{struct_names}', 18 => '{date_now}'
*/

    $db_url = $base_url . '?db=' . $db_name;
    $values = array_fill(0, 19, null);

    $values[18] = $today;

    // Database details
    $values[0] = $db_name;
    $values[2] = $db_url;

    //find db property details

    $vals = mysql__select_row_assoc($mysqli, 'SELECT sys_dbRegisteredID as db_id, sys_dbName as db_dname, sys_dbRights as db_rights, sys_dbOwner as db_owner, sys_dbDescription as db_desc FROM sysIdentification WHERE sys_ID = 1');
    if($vals==null){
        echo $tabs0.$db_name.' cannot execute query for Records table'.$eol;
        continue;
    }

    $values[1] = $vals['db_desc'];
    $values[4] = $vals['db_rights'];
    $values[5] = $vals['db_owner'];
    $values[6] = $vals['db_id'];
    $values[8] = $vals['db_dname'];

    // Replace missing/placeholder values
    if(empty($values[8]) || $values[8] == 'Please enter a DB name ...'){
        $values[8] = $db_name;
    }

    //db logo
    $values[7] = $db_url . '&entity=sysIdentification&icon=1&version=thumb&def=2';

    //list of links CMS_Homepages
    $values[3] = 'None';

    $cms_home_id = mysql__select_value($mysqli, 'SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID = 99 AND rty_IDInOriginatingDB = 51');
    $prime_url_base = $base_url.$db_name.'/web/';
    $alt_url_base = $base_url.'?db='.$db_name.'&website&id=';

    if($cms_home_id !== null){

        $cms_homes = mysql__select_list2($mysqli, 'SELECT rec_ID FROM Records WHERE rec_RecTypeID = ' . $cms_home_id . ' AND rec_NonOwnerVisibility = "public"');
        if(is_array($cms_homes) && count($cms_homes) > 0){

            foreach ($cms_homes as $idx => $rec_ID) {
                $prime_url = $prime_url_base.$rec_ID;
                $alt_url = $alt_url_base.$rec_ID;
                $cms_homes[$idx] = '<a href="'.$prime_url.'" target="_blank" rel="noopener">'.$prime_url.'</a> (<a href="'.$alt_url.'" target="_blank" rel="noopener">alternative link</a>)';
            }
            $values[3] = implode('<br>', $cms_homes);
        }
    }

    // Server details
    $values[9] = HEURIST_SERVER_NAME;
    $values[10] = $base_url;

    //User 2 details

    $vals = mysql__select_row_assoc($mysqli, 'SELECT CONCAT(ugr_FirstName, " ",ugr_LastName) as owner_name, ugr_eMail as owner_email FROM sysUGrps WHERE ugr_ID = 2');
    if($vals==null){
        echo $tabs0.$db_name.' cannot execute query for Records table'.$eol;
        continue;
    }

    $values[11] = $vals['owner_name'];
    $values[12] = $vals['owner_email'];

    if(empty($values[5])){ // check if db owner is blank, if so use user 2
        $values[5] = $vals['owner_name'];
    }

    // Record and Structure details

    //find number of records and date of last update

    $vals = mysql__select_row_assoc($mysqli, 'SELECT count(rec_ID) as cnt, max(rec_Modified) as last_rec FROM Records WHERE rec_FlagTemporary != 1');
    if($vals==null){
        echo $tabs0.$db_name.' cannot execute query for Records table'.$eol;
        continue;
    }

    $values[13] = $vals['cnt'];
    $values[15] = $vals['last_rec'];

    //find date of last modification from definitions

    $vals = mysql__select_row_assoc($mysqli, 'SELECT max(rst_Modified) as last_struct FROM defRecStructure');
    if($vals['last_struct'] == null){
        echo $tabs0.$db_name.' cannot execute query for defRecStructure table'.$eol;
        continue;
    }

    $values[16] = $vals['last_struct'];

    //find number of files in recUploadedFiles

    $vals = mysql__select_row_assoc($mysqli, 'SELECT count(ulf_ID) as cnt FROM recUploadedFiles');
    if($vals==null){
        echo $tabs0.$db_name.' cannot execute query for recUploadedFiles table'.$eol;
        continue;
    }

    $values[14] = $vals['cnt'];

    //list of all rectype names

    // This currently sorts alphabetically within groups, but could later use rty_OrderInGroup if it is ever set
    $vals = mysql__select_list2($mysqli, 'SELECT rty_Name FROM defRecTypes,defRecTypeGroups WHERE rty_ShowInLists = 1 AND rty_RecTypeGroupID=rtg_ID ORDER BY rtg_Order,rty_Name');
    if($vals==null){
        echo $tabs0.$db_name.' cannot execute query for defRecTypes table'.$eol;
        continue;
    }

    $values[17] = implode('<br>', $vals); // produce concatenated string of record types

    // Setup content
    $content = str_replace($value_to_replace, $values, $template_page);

    //echo $content . '<br><hr><br>';

    //Write to file
    $fname = $index_dir.'/'.$db_name.'.html';
    $res = fileSave($content, $fname);
    if($res <= 0){
        echo $tabs0.$db_name.' cannot save html page'.$eol;
        continue;
    }

    // $db_name => Name, [1] => Description, [3] => Websites
    if($values[3] !== 'None'){ // only databases with websites are listed in index.html

        $index_details = str_replace($index_row_replace, array($db_name, $db_name.'.html', $values[3], $values[1]), $index_row);

        array_push($index_databases, $index_details);
    }

    echo $tabs0.$db_name.' Completed, saved to '.$fname.$eol;
}//for

// Update index.html
$index_file = $index_dir . '/index.html';

$index_page = str_replace('{databases}', implode('<br><br><br>', $index_databases), $index_page);

$res = fileSave($index_page, $index_file);
if($res <= 0){
    echo $tabs0.' We were unable to update index.html'.$eol;
}else{
    echo $tabs0.' Updated index.html'.$eol;
}
?>