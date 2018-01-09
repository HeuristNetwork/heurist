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


define('dirname(__FILE__)', dirname(__FILE__));    // this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

mysql_connection_select(DATABASE);

$invalidChars = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),chr(18),chr(19),chr(20),chr(21),chr(22),chr(23),chr(24),chr(25),chr(26),chr(27),chr(28),chr(29),chr(30),chr(31)); // invalid chars that need to be stripped from the data.
$replacements = array("?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?"," ","?","?","?","?","?");
$textDetails = array();
$res = mysql_query("SELECT dtl_ID,dtl_RecID,dtl_Value,dty_Name ".
    "FROM recDetails left join defDetailTypes on dtl_DetailTypeID = dty_ID  ".
    "WHERE dty_Type in ('freetext','blocktext') ORDER BY dtl_RecID");
while ($row = mysql_fetch_assoc($res)) {
    array_push($textDetails, $row);
}
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Clean Invalid Characters</title>
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
    </head>
    <body class="popup">
        <div class="banner"><h2>Clean Invalid Characters</h2></div>

        <div id="page-inner" style="overflow:auto;padding-left: 20px;">
            <div>This function removes invalid characters in the data fields in the database records<br />&nbsp;<hr /></div>

            <table>
                <?php

                mysql_connection_overwrite(DATABASE);

                $prevInvalidRecId = 0;
                foreach ($textDetails as $textDetail) {
                    if (! check($textDetail['dtl_Value'])){
                        if ($prevInvalidRecId < $textDetail['dtl_RecID']) {
                            print "<tr><td><a target=_blank href='".HEURIST_BASE_URL."?fmt=edit&recID=".
                            $textDetail['dtl_RecID'] . "&db=".HEURIST_DBNAME. "'> " . $textDetail['dtl_RecID']. "</a></td></tr>\n";
                            $prevInvalidRecId = $textDetail['dtl_RecID'];
                            mysql__update("Records", "rec_ID=".$textDetail['dtl_RecID'],array("rec_Modified" => $now));
                        }
                        print "<tr><td><pre>" . "Invalid characters found in ".$textDetail['dty_Name'] . " field :</pre></td></tr>\n";
                        $newText = str_replace($invalidChars ,$replacements,$textDetail['dtl_Value']);
                        mysql__update("recDetails", "dtl_ID=".$textDetail['dtl_ID'], array("dtl_Value" =>$newText));
                        if (mysql_error()) {
                            print "<tr><td><pre>" . "Error ". mysql_error()."while updating to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
                        }else {
                            print "<tr><td><pre>" . "Updated to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
                        }
                    }
                }

                function check($text) {
                    global $invalidChars;
                    foreach ($invalidChars as $charCode){
                        if (strpos($text,$charCode)) {
                            return false;
                        }
                    }
                    return true;
                }

                ?>
            </table>

            <p>
                [end of check]
            </p>
        </div>
    </body>
</html>