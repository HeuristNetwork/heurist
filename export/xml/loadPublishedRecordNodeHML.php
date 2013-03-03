<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

?>

<?php

/*<!--publishRecordHML.php
 * configIni.php - Configuration information for Heurist Initialization - USER EDITABLE
 * @version $Id$
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 *

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/



header('Content-type: text/xml; charset=utf-8');
/*echo "<?xml version='1.0' encoding='UTF-8'?>\n";
*/
// called by applyCredentials require_once(dirname(__FILE__).'/../../common/config/initialise.php');
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
/*****DEBUG****///error_log(" rec owner = $rec_owner_id[0]  ".count($rec_owner_id)." vis = ".$row['rec_NonOwnerVisibility']." ".print_r($ACCESSABLE_OWNER_IDS,true));

if ( $row['rec_NonOwnerVisibility'] == 'hidden' && (count($rec_owner_id) < 1 || !in_array($rec_owner_id[0],$ACCESSABLE_OWNER_IDS))){
	returnXMLErrorMsgPage(" no access to record id $recID ");
}
$inputFilename ="".HEURIST_HML_PUBPATH.HEURIST_DBID."-".$recID.".hml";

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
