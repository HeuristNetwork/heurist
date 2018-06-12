<?php

/*
* Copyright (C) 2005-2018 University of Sydney
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
* @copyright   (C) 2005-2018 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* Take rec_ID or bkm_ID, fill in window.HEURIST.record.bibID and window.HEURIST.record.bkmkID as appropriate */
/* FIXME: leave around some useful error messages */

if (! defined("SAVE_URI")) {
    define("SAVE_URI", "disabled");
}

/* define JSON_RESPONSE to strip out the JavaScript commands;
* just output the .record object definition
*/

if (!defined("JSON_RESPONSE")) {
    require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
    require_once(dirname(__FILE__)."/getRecordInfoLibrary.php");
    if (! is_logged_in()) return;

    header('Content-type: text/javascript');
}

mysql_connection_select(DATABASE);
list($rec_id, $bkm_ID, $replaced) = getResolvedIDs(@$_REQUEST["recID"], @$_REQUEST['bkmk_id']);

if(@$_REQUEST["action"]=="getrelated"){

    if ($rec_id) {
        $related = getAllRelatedRecords($rec_id);
        print json_format($related);
    }

}else{

    preg_match("/^.*\/([^\/\.]+)/",$_SERVER['HTTP_REFERER'],$matches);
    $refer = $matches[1];
    $isPopup = false;
    if ($refer == "formEditRecordPopup") {
        $isPopup = true;
    }

    if (! $rec_id) {
        // record does not exist
        $record = null;
    } else if ($replaced) {
        // the record has been deprecated
        $record = array();
        $record["replacedBy"] = $rec_id;
    } else {
        $record = getBaseProperties($rec_id, $bkm_ID);
        if (@$record["workgroupID"] && $record["workgroupID"] != get_user_id() &&
        $record[@"visibility"] == "hidden"  &&
        ! @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"][$record["workgroupID"]]) {
            // record is hidden and user is not the owner or a member of owning workgroup
            $record = array();
            $record["denied"] = true;
        } else {
            $record["bdValuesByType"] = getAllRecordDetails($rec_id);
            $record["reminders"] = getAllReminders($rec_id);
            $record["comments"] = getAllComments($rec_id);
            $record["workgroupTags"] = getAllworkgroupTags($rec_id);
            $record["relatedRecords"] = getAllRelatedRecords($rec_id);
            $record["rtConstraints"] = getRectypeConstraints($record['rectypeID']);
            $record["retrieved"] = date('Y-m-d H:i:s');	// the current time according to the server
        }
    }

    if (! defined("JSON_RESPONSE")) {
        if ($isPopup) {
            ?>
            if (! window.HEURIST) window.HEURIST = {};
            if (! window.HEURIST.edit) window.HEURIST.edit = {};
            window.HEURIST.edit.record = <?= json_format($record) ?>;
            if (top.HEURIST.fireEvent) top.HEURIST.fireEvent(window, "heurist-record-loaded");
            <?php

        } else {

            ?>
            if (! window.HEURIST) window.HEURIST = {};
            if (! window.HEURIST.edit) window.HEURIST.edit = {};
            window.HEURIST.edit.record = <?= json_format($record) ?>;
            if (top.HEURIST.fireEvent) top.HEURIST.fireEvent(window, "heurist-record-loaded");
            <?php
        }

    } else {
        print json_format($record);
    }
    /***** END OF OUTPUT *****/
}
?>
