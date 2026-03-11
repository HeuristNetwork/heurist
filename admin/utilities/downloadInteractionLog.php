<?php
/**
* downloadInteractionLog.php - Allows download of the user interaction log as a CSV file, with filtering options.
*
* @fileOverview This script provides a user interface for filtering and downloading the
*               `userInteraction.log` file for the current Heurist database.
*               Manager-level access is required.
*               Users can filter the log by:
*               - Action type (e.g., record usage, website visits, account actions, all).
*               - Date range or period (e.g., last 3 months).
*               - User workgroups.
*               The output is a CSV file with columns: User, Function, Date, Operating System,
*               Browser, IP Address, Record ID, Resultset Size.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6
*/

define('MANAGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$log_file = $system->getSysDir().'userInteraction.log';
$mysqli = $system->getMysqli();

if(!file_exists($log_file)){
    print '<h2>There is no interactions log file</h2>';
    exit;
}elseif(!is_readable($log_file)){
    $system->addError(HEURIST_ERROR, 'Unable to read the interaction log file for DB ' . htmlspecialchars($_REQUEST['db']));
    print '<h2>Unable to read User interactions file</h2>';
    exit;
}

function processJsonLog($json){

    global $mysqli;

    if(!is_array($json)){
        return null;
    }

    $line = [];

    if(empty($json['user']) || empty($json['user']['id']) || empty($json['action']) || empty($json['date'])){
        return null;
    }

    $line[] = $json['user']['id'];
    $line[] = mysql__select_value($mysqli, 'SELECT ugr_Name FROM sysUGrps WHERE ugr_ID = ?', ['i', $json['user']['id']]);
    $line[] = $json['action'];

    if(!empty($json['details']['ids'])){
        $line[] = is_array($json['details']['ids']) ? implode('|', $json['details']['ids']) : $json['details']['ids'];
    }elseif($json['action'] === 'VisitPage' && !empty($json['details']['website'])){
        $value = $json['details']['website'];
        $value .= !empty($json['details']['website']) && $json['details']['website'] != $json['details']['page'] ? "/{$json['details']['page']}" : '';
        $line[] = $value;
    }else{
        $line[] = '';
    }

    if(!empty($json['details']['q'])){
        $line[] = is_array($json['details']['q']) ? json_encode($json['details']['q']) : $json['details']['q'];
    }elseif(!empty($json['details']['svs'])){
        $line[] = 'Saved Search: ' . intval($json['details']['q']);
    }else{
        $line[] = '';
    }

    $line[] = !empty($json['details']['count']) ? $json['details']['count'] : '';

    $date = new DateTime($json['date']);
    $line[] = $date->format('Y-m-d H:i:s');

    $line[] = !empty($json['user']['ip']) ? $json['user']['ip'] : 'Unknown';
    $line[] = !empty($json['user']['os']) ? $json['user']['os'] : 'Unknown';
    $line[] = !empty($json['user']['browser']) ? $json['user']['browser'] : 'Unknown';

    return $line;
}

function processStringLog($string){

    global $mysqli;

    $line = [];

    $chunks = explode(',', $string);

    if(empty($chunks) || count($chunks) < 3 || count($chunks) > 7){ // check for valid entry (userID, action, timestamp) and apply action filter
        return null;
    }

    $line[] = $chunks[0];
    $line[] = mysql__select_value($mysqli, 'SELECT ugr_Name FROM sysUGrps WHERE ugr_ID = ?', ['i', $chunks[0]]);
    $line[] = $chunks[1];

    if(count($chunks) >= 4 && strpos($chunks[3], 'recs') !== false){ // contains a listing of rec ids + rec count, re-make indexes

        $part_chunks = explode(' ', $chunks[3]);// [0] => count, [1] => 'recs:', [2] => rec id

        if(count($chunks) == 4){
            $recids = [$part_chunks[2]];
        }else{
            $recids = array_splice($chunks, 4);
            array_unshift($recids, $part_chunks[2]);
        }

        $chunks[] = implode('|', $recids);
        $chunks[] = $part_chunks[0];
        $chunks[] = '';

    }elseif($chunks[1] === 'recEdit' || $chunks[1] === 'viewRec'){ // expect record ID [6] 
        $line[] = intval($chunks[6]);
        $line[] = '';
        $line[] = 1;
    }elseif($chunks[1] === 'VisitPage'){ // expect homePageID/pageID [6]
        $line[] = $chunks[6];
        $line[] = '';
        $line[] = '';
    }else{
        $line[] = '';
        $line[] = '';
        $line[] = '';
    }

    $date = new DateTime($chunks[2]);
    $line[6] = $date->format('Y-m-d H:i:s');

    if(empty($chunks[3]) || is_numeric($chunks[3]) ||
        $chunks[3] == 'Array' || preg_match("/\d\s\d/", $chunks[3])){ // older format

        $line[] = 'Unknown';
        $line[] = 'Unknown';
        $line[] = 'Unknown';
    }else{

        $line[] = $chunks[5];
        $line[] = $chunks[3];
        $line[] = $chunks[4];
    }

    return $line;
}

if(@$_REQUEST['actionType']){ // filter and download interaction log as CSV file

    $log_fd = fopen($log_file, 'r');
    $csv_fd = fopen('php://output', 'w');

    if(!$log_fd){ // Unable to open log
        $system->addError(HEURIST_ERROR, 'Unable to open the interaction log file for DB ' . htmlspecialchars($_REQUEST['db']));
        print '<h2>An error has occurred while trying to open the Interaction log for this database</h2>';
        exit;
    }elseif(!$csv_fd){
        $system->addError(HEURIST_ERROR, 'Unable to open temporary file for exporting');
        print '<h2>An error has occurred</h2>';
        exit;
    }

    $action_filter = [];
    $is_all_actions = false;
    $fileprefix = "interaction";

    switch ($_REQUEST['actionType']) {
        case 'recuse': // record use [Edit Record, View Record, Custom Report]
            $action_filter = ['rec', 'editRec', 'viewRec', 'custRep'];
            $filename = 'record';
            break;

        case 'website': // end user visiting a CMS webpage/homepage
            $action_filter = ['VisitPage', 'cms'];
            $fileprefix = 'website';
            break;

        case 'accounts': // account actions [Log in, Log off, Reset Password]
            $action_filter = ['Login', 'Logout', 'ResetPassword'];
            $fileprefix = 'account';
            break;

        case 'database': // database actions
            $action_filter = ['db', 'st', 'configure'];

            $fileprefix = 'database';
            break;

        case 'import':  // importing actions
            $action_filter = ['imp', 'sync'];
            $fileprefix = 'import';
            break;

        case 'export':  // exporting actions
            $action_filter = ['exp'];
            $fileprefix = 'export';
            break;

        case 'search';
            $action_filter = ['search'];
            $fileprefix = 'search';
            break;

        default: // all actions
            $is_all_actions = true;
            break;
    }

    // Construct initial headers
    $filename = "{$fileprefix}_logs_{$_REQUEST['db']}.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Pragma: no-cache');
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

    // Add column headers
    fputcsv($csv_fd, ["User ID", "User name", "Function", "Record ID", "Query", "Resultset Size", "Date", "IP Address", "Operating System", "Browser"]);

    // Prepare user filtering by workgroups
    $users = [];
    $providedWorkgroup = array_key_exists('workGroups', $_REQUEST) && $_REQUEST['workGroups'] != '' ? prepareIds($_REQUEST['workGroups']) : [];
    $providedUsers = array_key_exists('users', $_REQUEST) && $_REQUEST['users'] != '' ? prepareIds($_REQUEST['users']) : [];

    if(!empty($providedWorkgroup)){
        $query = 'SELECT DISTINCT ugl_UserID FROM sysUsrGrpLinks WHERE ugl_GroupID IN ('. implode(',', $providedWorkgroup) .')';

        $users = mysql__select_list2($mysqli, $query);
    }
    if(!empty($providedUsers)){
        $query = 'SELECT DISTINCT ugr_ID FROM sysUGrps WHERE ugr_ID IN ('. implode(',', $providedUsers) .')';

        $users = array_unique(array_merge($users, mysql__select_list2($mysqli, $query, 'intval')));
    }
    $users = !empty($users) ? $users : null;

    // Prepare date period filtering
    $today = new DateTime();
    $date_int = null;
    $lastest_date = null;
    if(@$_REQUEST['enableDF']){
        $date_int = new DateInterval('P'.$_REQUEST['dateAmount'].$_REQUEST['datePeriod']);
        if($date_int){
            $lastest_date = $today->sub($date_int);
        }
    }

    while(!feof($log_fd)){

        $line = fgets($log_fd);
        $line = rtrim($line, "\n");// remove trailing newlines, interaction log only uses \n

        if(empty($line)){
            continue;
        }            

        $jsonFormat = json_decode($line, true);

        if(json_last_error() === JSON_ERROR_NONE){
            $line = processJsonLog($jsonFormat);
        }else{
            $line = processStringLog($line);
        }

        // Apply action filter
        if(!$is_all_actions){

            $allowedAction = false;
            foreach($action_filter as $action){
                if(strpos($line[2], $action) === 0){
                    $allowedAction = true;
                    break;
                }
            }
            if(!$allowedAction){
                continue;
            }
        }

        // Apply user filter
        if(!empty($users) && !in_array($line[0], $users)){
            continue;
        }

        // Apply date filtering
        if(@$_REQUEST['enableDF']){
            $action_date = new DateTime($line[6]);

            switch (@$_REQUEST['dfType']) {
                case 1: // time period, e.g. last 3 months
                    if(!$date_int || !$lastest_date) { break; } // skip date filtering

                    if($lastest_date >= $action_date){ // out of time period, skip action
                        continue 2;
                    }

                    break;
                case 2: // time range, e.g. between 10 Jan and 11 April
                    $start_date = new DateTime($_REQUEST['dateStart']);
                    $end_date = new DateTime($_REQUEST['dateEnd']);

                    if(!$start_date || !$end_date) { break; } // skip date filtering

                    if(($start_date > $action_date) || ($action_date > $end_date)){ // out of range, skip action
                        continue 2;
                    }

                    break;
                default: // Unknown, skip date filtering
                    break;
            }
        }

        $date = new DateTime($line_chunks[2]);
        $line_chunks[2] = $date->format('Y-m-d H:i:s');

        if(count($line) < 10){
            $line = array_pad($line, 10, '');
        }

        // Add row
        fputcsv($csv_fd, $line);
    }// end WHILE

    // close descriptors
    fclose($csv_fd);
    fclose($log_fd);

    exit;
}
//else, display download form, allows for filtering
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
    <head>
        <title>Heurist Interaction Log</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

        <?php
        includeJQuery();
        ?>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <script type="text/javascript">
            $(document).ready(function(){

                var $dateSection = $('label#dateLastSev, label#dateRange');

                $('input#enableDF').on('click', function(event){
                    window.hWin.HEURIST4.util.setDisabled($('label#dateLastSev'), !$(this).is(':checked'));
                    window.hWin.HEURIST4.util.setDisabled($('label#dateRange'), !$(this).is(':checked'));
                });
                window.hWin.HEURIST4.util.setDisabled($('label#dateLastSev'), true);
                window.hWin.HEURIST4.util.setDisabled($('label#dateRange'), true);

                $('div#usrFilter')
                .css('cursor', 'pointer')
                .on('click', function(){
                    var popup_opts = {
                        select_mode: 'select_multi',
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        title: 'Filter by User',
                        onselect: function(event, data){
                            if(data && data.selection){
                                var selection = data.selection;

                                var ids = [];
                                var names = [];

                                selection.each2(function(id, record){
                                    ids.push(id);
                                    names.push(record['ugr_Name']);
                                });

                                $('span#userList').text(names.join(', '));
                                $('input#users').val(ids.join(','));
                            }else{
                                $('span#userList').text('All');
                                $('input#users').val('');
                            }
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', popup_opts);
                });

                $('div#wrkGroup')
                .css('cursor', 'pointer')
                .on('click', function(){
                    var popup_opts = {
                        select_mode: 'select_multi',
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        title: 'Filter by Workgroups',
                        ugl_UserID: window.hWin.HAPI4.user_id,
                        onselect: function(event, data){
                            if(data && data.selection){
                                var selection = data.selection;

                                var ids = [];
                                var names = [];

                                selection.each2(function(id, record){
                                    ids.push(id);
                                    names.push(record['ugr_Name']);
                                });

                                $('span#workgroupList').text(names.join(', '));
                                $('input#workGroups').val(ids.join(','));
                            }else{
                                $('span#workgroupList').text('All');
                                $('input#workGroups').val('');
                            }
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', popup_opts);
                });

                $('button#exportForm').on('click', function(event){
                    var formData = $('input, select').serialize();
                    var url = 'downloadInteractionLog.php?db=' + window.hWin.HAPI4.database + '&' + formData;

                    window.open(url, '_blank');
                    return false;
                });
            });
        </script>

        <style type="text/css">

        </style>

    </head>
    <body class="popup" style="overflow:auto;">

        <div style="padding-top: 20px;">
            Download the user interactions log as a CSV file, select options below to filter the output as needed.
        </div>

        <div id="form">
            <!-- Action types -->
            <h2>Filter Actions:</h2>
            <div>
                <label for="completeLog"><input type="radio" name="actionType" id="completeLog" value="all" checked="true"> Download entire log</label>
            </div>

            <div style="margin-top: 10px;">
                <label for="recUsage"><input type="radio" name="actionType" id="recUsage" value="recuse"> Download record usage (when a record is viewed, edited, or used within a custom report)</label><br><br>
                <label for="webUsage"><input type="radio" name="actionType" id="webUsage" value="website"> Download webpage usages (when users view a CMS Homepage or CMS webpage, when the pages were edited)</label><br><br>
                <label for="accUsage"><input type="radio" name="actionType" id="accUsage" value="accounts"> Download account related actions (when accounts login, logout or request a password reset)</label><br><br>
                <label for="dbUsage"><input type="radio" name="actionType" id="dbUsage" value="database"> Download database related actions (when renamed, cleared, archived, registered, etc...)</label><br><br>
                <label for="impUsage"><input type="radio" name="actionType" id="impUsage" value="import"> Download importing related actions (when records are imported by CSV, HML, or JSON)</label><br><br>
                <label for="expUsage"><input type="radio" name="actionType" id="expUsage" value="export"> Download exporting related actions (when records are exported in various formats)</label><br><br>
                <label for="searchUsage"><input type="radio" name="actionType" id="searchUsage" value="search"> Download record searching related actions (when users perform searches, use a facet search, or saved filter)</label>
            </div>
            <!-- Add other actions -->

            <br><hr>

            <!-- Dates -->
            <h2>Filter Dates:</h2>
            <label for="enableDF"><input type="checkbox" name="enableDF" id="enableDF" value="1"> Enable date filtering</label>

            <!-- Last bit of time period (e.g. last 3 months) -->
            <label id="dateLastSev" for="enableLastSev" style="display: block;margin-top: 10px;">
                <input type="radio" name="dfType" value="1" id="enableLastSev" checked="true">
                Within the last <input type="number" name="dateAmount" min="1" value="30">
                <select name="datePeriod">
                    <option value="D">Days</option>
                    <option value="M">Months</option>
                    <option value="Y">Years</option>
                </select>
            </label>

            <!-- Between times (e.g. between 10 January to 30 April) -->
            <label id="dateRange" for="enableRange" style="display: block;margin-top: 10px;">
                <input type="radio" name="dfType" value="2" id="enableRange">
                Between <input type="date" name="dateStart"> to <input type="date" name="dateEnd">
            </label>

            <br><hr>

            <!-- User Types -->
            <h2>Filter Users:</h2>
            <!-- Certain workgroups (e.g. Members of DB admins) [utilise manageSysWorkroups' multi select] -->
            <div id="usrFilter">
                Filter by Users:
                <span id="userList" style="font-weight: bold;">All</span>
                <input type="hidden" name="users" id="users" value="">
            </div>

            <div id="wrkGroup">
                Filter by Workgroups:
                <span id="workgroupList" style="font-weight: bold;">All</span>
                <input type="hidden" name="workGroups" id="workGroups" value="">
            </div>

            <br><hr>

            <!-- Records -->
            <!-- <h2>Filter by Records:</h2> -->
            <!-- Record Types (e.g. Persons, Organsiations, etc...) -->
            <!-- <div id="recType">
            Filter by Record Types:
            <span id="rectypeList" style="font-weight: bold;">All</span>
            <input type="hidden" name="recordTypes" id="recordTypes" value="">
            </div>-->

            <!-- Record IDs? Too specific? Too niche (e.g. 100, 13, 201, 511) -->

            <button id="exportForm">Download Log</button>
        </div>

    </body>
</html>