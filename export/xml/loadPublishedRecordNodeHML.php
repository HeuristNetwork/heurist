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
require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
if (!is_logged_in()) { // check if the record being retrieved is a single non-protected record
	return;
}

mysql_connection_db_select(DATABASE);

// set parameter defaults
$recID = @$_REQUEST['recID'] ? $_REQUEST['recID'] : null;

$res = mysql_query("select * from Records where rec_ID = $recID");
if (!$recID || !mysql_num_rows($res)){
	returnXMLErrorMsgPage(" No record ID ");
}
$row = mysql_fetch_assoc($res);
$ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
								'ugl_UserID='.get_user_id().' and grp.ugr_Type != "user" order by ugl_GroupID');
array_push($ACCESSABLE_OWNER_IDS,get_user_id());
array_push($ACCESSABLE_OWNER_IDS,0);	// 0 = belong to everyone

$rec_owner_id = mysql__select_array("Records","rec_OwnerUGrpID","rec_ID=$recID");
//error_log(" rec owner = $rec_owner_id[0]  ".count($rec_owner_id)." vis = ".$row['rec_NonOwnerVisibility']." ".print_r($ACCESSABLE_OWNER_IDS,true));

if ( $row['rec_NonOwnerVisibility'] != 'public' && (count($rec_owner_id) != 1 || !in_array($rec_owner_id[0],$ACCESSABLE_OWNER_IDS))){
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
	// convert to xml
	$xml = simplexml_import_dom($dom);
	if (!$xml){
		returnXMLErrorMsgPage("unable to generate valid hml for $filename");
	}else{
//		$xpath = new DOMXPath($xml);
//		$recHML = new DOMNodelist();
		$recHML = $xml->xpath("//hml/records/record[id=$recID]");
//error_log("recHML = ".print_r($recHML,true));
//		$recHML = $recHML[0];
//error_log(" text = ".print_r($recHML,true));
		echo "<?xml version='1.0' encoding='UTF-8'?>\n";
//		$text =  $recHML->asXML();
//error_log(" text = ".print_r($text,true));
//		echo "count + ".count($text);
//		$text = preg_replace("/<record[^>]*>/","",$text);
//		echo "count + ".count($text);
//		$text = preg_replace('/<\/record>$/',"",$text);
//		echo "count + ".count($text);
//		echo $text;
//		$text = "";
//		foreach ( $recHML[0]->children() as $dNode) {
//			$text .= $dNode->asXML();
//		}
			$text = $recHML[0]->asXML();
//error_log(" text = ".print_r($text,true));
		echo $text;
	}
}

function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
