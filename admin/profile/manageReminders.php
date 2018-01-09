<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

if (@$_REQUEST["action"] == "delete"  &&  @$_REQUEST["rem_ID"]) {
	mysql_connection_overwrite(DATABASE);
	mysql_query("delete from usrReminders where rem_ID = " . intval($_REQUEST["rem_ID"]) . " and rem_OwnerUGrpID = " . get_user_id());
}

$future = (! @$_REQUEST["show"]  ||  $_REQUEST["show"] === "future");

?>
<html>
     <head>
          <title>Profile . Manage Reminders</title>
          <meta http-equiv="content-type" content="text/html; charset=utf-8">
          <link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
          <link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
          <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">

          <style>
               div#page { padding: 10px; }
               div#page .headline { margin-bottom: 10px; }
               div#page img { border: none; }
          </style>

          <script>
	        function del(rem_id) {
		        document.getElementById("rem_id_input").value = rem_id;
		        document.getElementById("action_input").value = "delete";
		        document.getElementById("action_input").form.submit();
	        }
          </script>
     </head>

     <body class="popup" width=600 height=300>
        <div>
            <div class=headline>Reminders</div>

           <form>
                <input type=hidden name="db" value="<?= HEURIST_DBNAME?>">
                <label for=show-future>
                    <input type=radio name=show value=future id=show-future <?= $future ? "checked" : "" ?> onchange="form.submit();">
                    Show future reminders only
                </label>
                <br>
                <label for=show-all>
                    <input type=radio name=show value=all id=show-all <?= $future ? "" : "checked" ?> onchange="form.submit();">
                    Show all reminders
                </label>
           </form>

           <form method=post>
                <input type=hidden name="db" value="<?= HEURIST_DBNAME?>">
                <input type=hidden name=rem_ID id=rem_id_input>
                <input type=hidden name=action id=action_input>
           </form>

           <table class=reminder width="100%">
                <tr>
                    <th width="20"></th>
                    <th width="200">Record</th>
                    <th width="100">Recipient</th>
                    <th width="100">Notification frequency</th>
                    <th>Message</th>
                </tr>
                <?php
                mysql_connection_select(DATABASE);

                $future_clause = $future ? "and rem_Freq != 'once' or rem_StartDate > now()" : "";

                $res = mysql_query("select usrReminders.*, rec_Title, grp.".GROUPS_NAME_FIELD.",  concat(usr.".USERS_FIRSTNAME_FIELD.",' ',usr.".USERS_LASTNAME_FIELD.") as username
					                  from usrReminders
				                 left join Records on rec_ID = rem_RecID
				                 left join ".USERS_DATABASE.".".GROUPS_TABLE." grp on grp.".GROUPS_ID_FIELD." = rem_ToWorkgroupID
				                 left join ".USERS_DATABASE.".".USERS_TABLE." usr on usr.".USERS_ID_FIELD." = rem_ToUserID
					                 where rem_OwnerUGrpID = " . get_user_id() . "
					                 $future_clause
				                  order by rem_RecID, rem_StartDate");

                if (mysql_num_rows($res) == 0) {
	                print "<tr><td></td><td colspan=4>No reminders</td></tr>";
                }

                while ($row = mysql_fetch_assoc($res)) {
	                $recipient = $row[GROUPS_NAME_FIELD] ? $row[GROUPS_NAME_FIELD] :
					                ($row["username"] ? $row["username"] : $row["rem_ToEmail"]);
                ?>
                    <tr>
                     <td><a title=delete href=# onclick="del(<?= $row["rem_ID"] ?>); return false;"><img src="<?=HEURIST_BASE_URL?>common/images/cross.png"></a></td>
                     <td><a href="<?=HEURIST_BASE_URL?>?fmt=edit&recID=<?= $row["rem_RecID"] ?>&db=<?= HEURIST_DBNAME?>#personal" target="_blank"><b><?= $row["rec_Title"] ?></b></a></td>
                     <td><b><?= $recipient ?></b></td>
                     <td><b><?= $row["rem_Freq"] ?></b> from <b><?= $row["rem_StartDate"] ?></b></td>
                     <td><?= $row["rem_Message"] ?></td>
                    </tr>
                <?php
                }
                ?>
           </table>
        </div>
     </body>
</html>
