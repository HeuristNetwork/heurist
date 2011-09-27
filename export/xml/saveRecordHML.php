<?php

/*<!--saveRecordHML.php
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

saveRecordHML(HEURIST_URL_BASE."export/xml/flathml.php?ver=1&a=1&f=1&depth=1&hinclude=0&w=all&q=ids:$recID&db=".HEURIST_DBNAME);

function saveRecordHML($filename){
global $recID;
//error_log(" file name = $filename");
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return teh output as a string from curl_exec
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
//	curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
	curl_setopt($ch, CURLOPT_URL, $filename);
	$hml = curl_exec($ch);
	$xml = new DOMDocument;
	$xml->loadXML($hml);
	// convert to xml
	if (!$xml){
		returnXMLErrorMsgPage("unable to generate valid hml for $filename");
	}else{
		$outputFilename ="".HEURIST_HML_PUBPATH.HEURIST_DBID."-".$recID.".hml";
		$text = $xml->saveXML();
		$ret = file_put_contents( $outputFilename,$text);
//error_log(" output ".($ret?"2":"1")." complete $outputFilename");
		if (!$ret){
			returnXMLErrorMsgPage("output of $outputFilename failed to write");
		}else if ($ret < strlen($text)){
			returnXMLErrorMsgPage("output of $outputFilename wrote $ret bytes of ".strlen($text));
		}else{ // success output the read file
			$text = file_get_contents($outputFilename);
			echo $text;
		}
	}
}

function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
