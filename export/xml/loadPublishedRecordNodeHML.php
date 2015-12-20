<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* load a published records HML
*
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export/xml
*/



header('Content-type: text/xml; charset=utf-8');
/*echo "<?xml version='1.0' encoding='UTF-8'?>\n";
*/
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
if (!is_logged_in()) { // check if the record being retrieved is a single non-protected record
    return;
}

mysql_connection_select(DATABASE);

// set parameter defaults
$recID = @$_REQUEST['recID'] ? $_REQUEST['recID'] : null;

$res = mysql_query("select * from Records where rec_ID = $recID");
if (!$recID || !mysql_num_rows($res)){
    returnXMLErrorMsgPage(" Non-existent record ID ($recID)");
}
$row = mysql_fetch_assoc($res);
$ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
    'ugl_UserID='.get_user_id().' and grp.ugr_Type != "user" order by ugl_GroupID');
array_push($ACCESSABLE_OWNER_IDS,get_user_id());
array_push($ACCESSABLE_OWNER_IDS,0);	// 0 = belong to everyone

$rec_owner_id = mysql__select_array("Records","rec_OwnerUGrpID","rec_ID=$recID");

if ( $row['rec_NonOwnerVisibility'] == 'hidden' && (count($rec_owner_id) < 1 || !in_array($rec_owner_id[0],$ACCESSABLE_OWNER_IDS))){
    returnXMLErrorMsgPage(" no access to record id $recID ");
}
$inputFilename ="".HEURIST_HML_DIR.HEURIST_DBID."-".$recID.".hml";

echo loadRecordHML($inputFilename);


function loadRecordHML($filename){
    global $recID;
    $dom = new DOMDocument;
    $dom->load($filename);
    $dom->xinclude();
    return $dom->saveXML();
}

function returnXMLErrorMsgPage($msg) {
    die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
