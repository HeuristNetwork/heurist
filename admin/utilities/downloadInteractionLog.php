<?php
/**
* downloadInteractionLog.php: Allow the user to filter and download the userInteraction.log as a CSV file
*   Filters: All, Record Usage, via Dates, via User Workgroups
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Brandon McKay     <blmckay13@gmail.com>
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

define('MANAGER_REQUIRED',1);
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

$log_file = HEURIST_FILESTORE_DIR.'userInteraction.log';

if(!file_exists($log_file)){
	print '<h2>There is no interactions log file</h2>';
	exit();
}else if(!is_readable($log_file)){
    $system->addError(HEURIST_ERROR, 'Unable to read the interaction log file for DB ' . $_REQUEST['db']);
    print '<h2>Unable to read User interactions file</h2>';
    exit();
}

if(@$_REQUEST['actionType']){ // filter and download interaction log as CSV file

    $log_fd = fopen($log_file, 'r');
    $csv_fd = fopen('php://output', 'w');

    if(!$log_fd){ // Unable to open log
        $system->addError(HEURIST_ERROR, 'Unable to open the interaction log file for DB ' . $_REQUEST['db']);
        print '<h2>An error has occurred while trying to open the Interaction log for this database</h2>';
        exit();
    }else if(!$csv_fd){
        $system->addError(HEURIST_ERROR, 'Unable to open temporary file for exporting');
        print '<h2>An error has occurred</h2>';
        exit();
    }

    // Construct initial headers
    $filename = "user_log_". $_REQUEST['db'] .".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Pragma: no-cache');
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

    // Add column headers
    fputcsv($csv_fd, array("User", "Function", "Date", "Record ID", "Resultset Size"));

    $action_filter = array();
    $is_all_actions = false;

    switch ($_REQUEST['actionType']) {
        case 'recuse': // record use [Edit Record, View Record, Custom Report]
            array_push($action_filter, 'editRec', 'viewRec', 'custRep');
            break;
        
        default: // all actions
            $is_all_actions = true;
            break;
    }

    // Prepare user filtering by workgroups
    $users = null;
    if(array_key_exists('workGroups', $_REQUEST) && $_REQUEST['workGroups'] != ''){
        $query = 'SELECT DISTINCT ugl_UserID FROM sysUsrGrpLinks WHERE ugl_GroupID IN ('. $_REQUEST['workGroups'] .')';

        $users = mysql__select_list2($system->get_mysqli(), $query);
    }

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

    $processed_ids = array(array(), array()); // 2d array, [0] => found allowed records [1] => found filtered out records

    //
    // [0] => User ID
    // [1] => Action
    // [2] => Timestamp
    // [3] => Record ID(s) ('|' separated, can also be "Record_Count recs: id1,id2,...")
    // [4] => Record Count
    //
    while(!feof($log_fd)){

        $line = fgets($log_fd);
        $line = rtrim($line, "\n"); // remove trailing newlines, interaction log only uses \n
        $line_chunks = explode(',', $line);

        if(count($line_chunks) < 3 || (!$is_all_actions && !in_array($line_chunks[1], $action_filter))){ // check for valid entry (userID, action, timestamp) and apply action filter
            continue;
        }else if(count($line_chunks) >= 4 && strpos($line_chunks[3], 'recs') !== false){ // contains a listing of rec ids + rec count, re-make indexes
            $part_chunks = explode(' ', $line_chunks[3]); // [0] => count, [1] => 'recs:', [2] => rec id

            if(count($line_chunks) == 4){
                $recids = array($part_chunks[2]);
            }else{            
                $recids = array_splice($line_chunks, 4);
                array_unshift($recids, $part_chunks[2]);
            }

            $line_chunks[4] = $part_chunks[0];
            $line_chunks[3] = implode('|', $recids);
        }else if(count($line_chunks) > 5){ // currently un-supported entry, skip
            continue;
        }

        // Apply user filter
        if($users != null && !in_array($line_chunks[0], $users)){
            continue;
        }

        // Apply date filtering
        if(@$_REQUEST['enableDF']){
            $action_date = new DateTime($line_chunks[2]);

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

        if(count($line_chunks) < 5){
            $line_chunks = array_pad($line_chunks, 5, '');
        }

        // Add row
        fputcsv($csv_fd, $line_chunks);
    }// end WHILE

    // close descriptors
    fclose($csv_fd);
    fclose($log_fd);

	exit();
}
//else, display download form, allows for filtering
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>

        <script type="text/javascript">
            $(document).ready(function(){

                var $dateSection = $('label#dateLastSev, label#dateRange');

                $('input#enableDF').click(function(event){
                    window.hWin.HEURIST4.util.setDisabled($dateSection, !$(this).is(':checked'));
                });
                window.hWin.HEURIST4.util.setDisabled($dateSection, true);

                $('div#wrkGroup')
                    .css('cursor', 'pointer')
                    .click(function(){
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
                /*$('div#recType').click(function(){
                    var popup_opts = {
                        select_mode: 'select_multi',
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        title: 'Filter by Record Type',
                        beforeClose: function(response){
                            console.log(response);
                            // Worded list into span#workgroupList
                            // Id'd list into input#workGroups
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_opts);
                });*/

                $('button#exportForm').click(function(event){
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
                <label for="recUsage"><input type="radio" name="actionType" id="recUsage" value="recuse"> Download record usage (when a record is viewed, edited, or used within a custom report)</label>
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