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
// called by applyCredentials require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
if (!is_logged_in()) { // check if the record being retrieved is a single non-protected record
	return;
}
/*****DEBUG****///error_log(print_r($_SESSION,true));

// set parameter defaults
$recID = (@$_REQUEST['recID'] ? $_REQUEST['recID'] : null);
$q = (@$_REQUEST['q'] ? $_REQUEST['q'] : ($recID ? "ids:$recID" : null));
$outName = @$_REQUEST['outName'] ? $_REQUEST['outName'] : null;	// outName returns the hml direct.
if (!$q) {
	returnXMLErrorMsgPage(" You must specify a record id (recID=#) or a heurist query (q=valid heurst search string)");
}
$depth = @$_REQUEST['depth'] ? $_REQUEST['depth'] : 1;
$hinclude = (@$_REQUEST['hinclude'] ? $_REQUEST['hinclude'] : ($recID?0:-1)); //default to 0 will output xincludes all non record id related records, -1 puts out all xinclude

/*****DEBUG****///error_log("recID = .$recID.  q = .$q.  outName = .$outName. depth = .$depth");

mysql_connection_db_select(DATABASE);

if ($recID ){ // check access first
	$res = mysql_query("select * from Records where rec_ID = $recID");
	$row = mysql_fetch_assoc($res);
	$ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
									'ugl_UserID='.get_user_id().' and grp.ugr_Type != "user" order by ugl_GroupID');
	if (is_logged_in()){
		array_push($ACCESSABLE_OWNER_IDS,get_user_id());
		if (!in_array(0,$ACCESSABLE_OWNER_IDS)){
			array_push($ACCESSABLE_OWNER_IDS,0);// 0 = belong to everyone
		}
	}

	$rec_owner_id = mysql__select_array("Records","rec_OwnerUGrpID","rec_ID=$recID");
	/*****DEBUG****///error_log(" rec owner = $rec_owner_id[0]  ".count($rec_owner_id)." vis = ".$row['rec_NonOwnerVisibility']." ".print_r($ACCESSABLE_OWNER_IDS,true));

	if ( $row['rec_NonOwnerVisibility'] != 'public' && (count($rec_owner_id) < 1 ||
			!in_array($rec_owner_id[0],$ACCESSABLE_OWNER_IDS) ||
			(is_logged_in() &&  $row['rec_NonOwnerVisibility'] == 'hidden'))){
		returnXMLErrorMsgPage(" no access to record id $recID ");
	}
	if (!$outName){
		$outName ="".HEURIST_HML_PUBPATH.HEURIST_DBID."-".$recID.".hml";
	}
}

saveRecordHML(HEURIST_URL_BASE."export/xml/flathml.php?ver=1&a=1&f=1&depth=$depth&hinclude=$hinclude&w=all&q=$q&db=".HEURIST_DBNAME);


//  ---------Helper Functions
function saveRecordHML($filename){
global $recID, $outName;
/*****DEBUG****///error_log(" file name = $filename");
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
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}
	curl_setopt($ch, CURLOPT_URL, $filename);
	$hml = curl_exec($ch);
/*****DEBUG****///error_log(" output from flatHML ".print_r($hml,true));
	$xml = new DOMDocument;
	$xml->loadXML($hml);
	// convert to xml
	if (!$xml){
		returnXMLErrorMsgPage("unable to generate valid hml for $filename");
	}else if($outName){
		$text = $xml->saveXML();
		$ret = file_put_contents( $outName,$text);
/*****DEBUG****///error_log(" output ".($ret?"2":"1")." complete $outputFilename");
		if (!$ret){
			returnXMLErrorMsgPage("output of $outName failed to write");
		}else if ($ret < strlen($text)){
			returnXMLErrorMsgPage("output of $outName wrote $ret bytes of ".strlen($text));
		}else{ // success output the contents of the saved file file
			$text = file_get_contents($outName);
		}
		echo $text;
	}else{
		echo $xml->saveXML();
	}
}

function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}

function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
