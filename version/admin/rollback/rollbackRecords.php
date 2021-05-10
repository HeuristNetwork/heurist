<?php

/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

    define('MANAGER_REQUIRED',1);   
    define('PDIR','../../');  //need for proper path to js and css    
    
    require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

?>
<html>
	<head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        
        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>
        
        <link rel="icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        
         <style>
              #form { width: 500px; margin:5px }
              #form input[type=text], #form textarea { width: 100% }
              .header div { font-weight: bold; margin-bottom: 10px }
              .detail-type { float: left; width: 200px }
              .current-val, .new-val { float: left; width: 400px }
              .clearall { clear: both }
              .shade div { background-color: #eeeeee }
              .detail-row .delete { background-color: #ff8888 }
              .detail-row.shade .delete { background-color: #ee8888 }
              .detail-row .insert { background-color: #88ff88 }
              .detail-row.shade .insert { background-color: #88ee88 }
         </style>

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
         
         <script type="text/javascript">
	        $(function () {
		        $(".record .detail-row:even").addClass("shade");
	        });
         </script>
     </head>

    <body class="popup">
        <div class="banner"><h2>Record rollback</h2></div>
        <div id="page-inner" style="overflow:auto">
        
            <div>Although rollback data has been recorded, there is currently no end-user interface way of rolling
     back the database. Please <?php echo CONTACT_HEURIST_TEAM;?> to request restoration of previous data.</div>

<?php         
if (FALSE) {       
?>
        
            <div id=errorMsg><span>This function has not yet been converted from Vsn 2</span></div>
            <div>Please <?php echo CONTACT_HEURIST_TEAM;?> if rollback is critical to your use of Heurist</div>

            <?php
/* @TODO replace with H4            
            require_once(dirname(__FILE__)."/../../search/getSearchResults.php");
            require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");
            require_once(dirname(__FILE__)."/rollbackRecordsFuncs.php");
*/
            $ids = @$_REQUEST["ids"];
            $date = @$_REQUEST["date"];
            $rollback = @$_REQUEST["rollback"];

            if (! $ids  && ! $date) {
	            showForm();
	            return;
            }

            if ($rollback) {
	            $n = rollRecordsBack(split(",", $ids), $date);
	            $s = $n != 1 ? "s" : "";
	            $state = $date ? "state as of $date" : "previous version";
	            print "<p>$n record$s rolled back to $state</p>";
	            print '<p><a href="'.HEURIST_BASE_URL.'?q=ids:' . $ids . '">View updated records</a></p>';
            } else {
	            $rollbacks = getRecordRollbacks(split(",", $ids), $date);
	            showRollbacks($rollbacks);
	            showConfirm($ids, $date);
            }


            function showForm () {
            ?>
                 <form method="get">
                    <div id="form">
                       <p>
                            Rollback records to an earlier version.  Specify record IDs, or date, or both.
                            If no date is given, records will be rolled back to their immediate previous version.
                            If no record IDs are given, ALL records changed since the given date will be rolled
                            back to their state as at the given date.
                       </p>

                       <div>Record IDs (comma-separated):</div>
                       <textarea name="ids" class='in'></textarea>
                       <div>Date (YYYY-MM-DD hh:mm:ss):</div>
                       <input type="text" name="date" class='in'>
                       <br><br>
                       <input type="submit" value="show rollback changes">
                   </div>
                 </form>
            <?php
            }

            function showConfirm ($ids, $date) {
            ?>
                <br><br>
                <form method="get">
                    <input type="hidden" name="ids" value="<?= $ids ?>">
                    <input type="hidden" name="date" value="<?= $date ?>">
                    <input type="hidden" name="rollback" value="1">
                    <input type="submit" value="roll back">
                </form>
            <?php
            }
}
            function showRollbacks ($rollbacks) {
	            $s = count($rollbacks) != 1 ? "s" : "";
	            print "<p>" . count($rollbacks) . " record$s can be rolled back.</p>";

	            foreach ($rollbacks as $rec_id => $changes) {
		            $record = loadRecord($rec_id);
		            showRecordRollback($record, $changes);
	            }
            }

            function showRecordRollback ($record, $changes) {
	            print '<div class="record">';
	            print '<div class="header">';
	            print '<div class="detail-type">';
	            print $record["rec_ID"] . ": " . $record["rec_Title"];
	            print '</div>';
	            print '<div class="current-val">Current values</div>';
	            print '<div class="new-val">Will be changed to</div>';
	            print '<div class="clearall"></div>';
	            print '</div>';

	            $reqs = getRectypeStructureFields($record["rec_RecTypeID"]);
	            $detail_names = mysql__select_assoc("defDetailTypes", "dty_ID", "dty_Name", 1);


	            foreach ($reqs as $dt => $req) {
		            if (array_key_exists($dt, $record["details"])) {
			            $values = $record["details"][$dt];
			            showDetails($dt . ': ' . $req["rst_DisplayName"], $values, $changes);
		            }
	            }

	            foreach ($record["details"] as $dt => $values) {
		            if (! array_key_exists($dt, $reqs)) {
			            showDetails($dt . ': ' . $detail_names[$dt], $values, $changes);
		            }
	            }

	            foreach ($changes["inserts"] as $insert) {
		            if (array_key_exists($insert["ard_DetailTypeID"], $reqs)) {
			            $name = $reqs[$insert["ard_DetailTypeID"]]["rst_DisplayName"];
		            } else {
			            $name = $detail_names[$insert["ard_DetailTypeID"]];
		            }
		            showDetail(
			            $insert["ard_DetailTypeID"] . ': ' . $name,
			            "&nbsp;",
			            '<div class="insert">' . getDetailUpdateString($insert) . '</div>'
		            );
	            }

	            print '</div>';
            }

            function showDetails ($name, $values, $changes) {
	            foreach ($values as $rd_id => $rd_val) {
		            showDetail($name, getCurrentValString($rd_val), getChangedValString($rd_id, $changes));
	            }
            }

            function showDetail ($name, $current, $new) {
	            print '<div class="detail-row">';
	            print '<div class="detail-type">';
	            print $name;
	            print '</div>';
	            print '<div class="current-val">';
	            print $current;
	            print '</div>';
	            print '<div class="new-val">';
	            print $new;
	            print '</div>';
	            print '<div class="clearall">';
	            print '</div>';
	            print '</div>';
            }

            function getCurrentValString ($val) {
	            if (is_array($val)) {
		            if (array_key_exists("file", $val)) {
			            return 'file: [<a href="' . $val["file"]["URL"] . '">' . $val["file"]["origName"] . '</a>]';
		            }
		            else if (array_key_exists("geo", $val)) {
			            return 'geo: ' . $val["geo"]["type"] . ': [' . substr($val["geo"]["wkt"], 0, 30) . ' ... ]';
		            }
		            else if (array_key_exists("id", $val)) {
			            return '=> [' . $val["id"] . '] <a href="'.HEURIST_BASE_URL.'viewers/record/viewRecord.php?recID=' . $val["id"] . '&amp;db='.HEURIST_DBNAME.'">' . $val["title"] . '</a>';
		            }
	            } else {
		            return $val;
	            }
            }

            function getDetailUpdateString ($ard_row) {
	            $s = '';
	            if ($ard_row["ard_Value"]) {
		            $s = $ard_row["ard_Value"];
	            }
	            if ($ard_row["ard_UploadedFileID"]) {
		            $s = $ard_row["ard_UploadedFileID"];
	            }
	            if ($ard_row["ard_Geo"]) {
		            $s .= ' [' . substr($ard_row["ard_Geo"], 0, 30) . ' ... ]';
	            }
	            return $s;
            }

            function getChangedValString ($rd_id, $changes) {
	            foreach ($changes["updates"] as $update) {
		            if ($update["ard_ID"] == $rd_id) {
			            return '<div class="update">' . getDetailUpdateString($update) . '</div>';
		            }
	            }
	            foreach ($changes["deletes"] as $delete) {
		            if ($delete == $rd_id) {
			            return '<div class="delete">[delete]</div>';
		            }
	            }
	            return "&nbsp;";
            }

            ?>
        </div>
    </body>

</html>
