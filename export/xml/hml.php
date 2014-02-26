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
* XML encode a heurist result set
*
* @author      Kim Jackson
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



if (@$argv) {
	// handle command-line queries

	$ARGV = array();
	for ($i=0; $i < count($argv); ++$i) {
		if ($argv[$i][0] === '-') {
			if (@$argv[$i+1] && $argv[$i+1][0] != '-') {
			$ARGV[$argv[$i]] = $argv[$i+1];
			++$i;
			}else{
				$ARGV[$argv[$i]] = true;
			}
		} else {
			array_push($ARGV, $argv[$i]);
		}
	}

	if(@$ARGV['-db']) $_REQUEST["db"] = $ARGV['-db'];

	if (@$ARGV['-f']) $_REQUEST['f'] = $ARGV['-f'];
	$_REQUEST['q'] = @$ARGV['-q'];
	$_REQUEST['w'] = @$ARGV['-w']? $ARGV['-w'] : 'a';	// default to ALL RESOURCES
	if (@$ARGV['-stype']) $_REQUEST['stype'] = $ARGV['-stype'];
	$_REQUEST['style'] = '';
	$_REQUEST['depth'] = @$ARGV['-depth'] ? $ARGV['-depth']: 0;
	if (@$ARGV['-rev'])$_REQUEST['rev'] = $ARGV['-rev'];
	if (@$ARGV['-woot'])$_REQUEST['woot'] = $ARGV['-woot'];
	if (@$ARGV['-stub']) $_REQUEST['stub'] = '1';
	if (@$ARGV['-fc']) $_REQUEST['fc'] = '1'; // inline file content
}


header('Content-type: text/xml; charset=utf-8');
echo "<?xml version='1.0' encoding='UTF-8'?>\n";


require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/getSearchResults.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');

mysql_connection_select(DATABASE);

//----------------------------------------------------------------------------//
//  Tag construction helpers
//----------------------------------------------------------------------------//
function output($str){
    echo $str;       
}

function makeTag($name, $attributes=null, $textContent=null, $close=true,$encodeContent=true) {
	$tag = "<$name";
	if (is_array($attributes)) {
		foreach ($attributes as $attr => $value) {
			$tag .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
		}
	}
	if ($close  &&  ! $textContent) {
		$tag .= '/>';
	} else {
		$tag .= '>';
	}
	if ($textContent) {
		if ($encodeContent) {
			$tag .= htmlspecialchars($textContent);
		}else{
			$tag .= $textContent;
		}
		$tag .= "</$name>";
	}
	output($tag . "\n");
/*****DEBUG****///	error_log("in makeTag tag = $tag");
}

function openTag($name, $attributes=null) {
	makeTag($name, $attributes, null, false);
}

function closeTag($name) {
	output( "</$name>\n" );
/*****DEBUG****///	error_log("in closeTag name = $name");
}

function openCDATA() {
	output( "<![CDATA[\n" );
}

function closeCDATA() {
	output( "]]>\n" );
}


//----------------------------------------------------------------------------//
//  Authentication helpers
//----------------------------------------------------------------------------//

function single_record_retrieval($q) {
   if (preg_match ('/\bids?:([0-9]+)(?!,)\b/i', $q, $matches)) {
		$query = 'select * from Records where rec_ID='.$matches[1];
		$res = mysql_query($query);
		if (mysql_num_rows($res) < 1) return false;
		$rec = mysql_fetch_assoc($res);
		//saw FIXME need to compare against current user's UGrpID, need to say no user or group user belongs to and is HIDDEN
		if ($rec['rec_OwnerUGrpID']  &&  $rec['rec_NonOwnerVisibility'] === 'hidden') {
			return false;
		}
		return true;
	}
	return false;
}


//----------------------------------------------------------------------------//
//  Retrieve record- and detail- type metadata
//----------------------------------------------------------------------------//

$RTN = array();	//record type name
$RQS = array();	//record type specific detail name
$DTN = array();	//detail type name
$DTT = array();	//detail type base type
$INV = array();	//relationship term inverse
$WGN = array();	//work group name
$UGN = array();	//User name
$TL = array();	//term lookup
$TLV = array();	//term lookup by value
// record type labels
$query = 'SELECT rty_ID, rty_Name FROM defRecTypes';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$RTN[$row['rty_ID']] = $row['rty_Name'];
	foreach (getRectypeFields($row['rty_ID']) as $rst_DetailTypeID => $rdr) {
	// type-specific names for detail types
		$RQS[$row['rty_ID']][$rst_DetailTypeID] = @$rdr[0];
	}
}
/*****DEBUG****///error_log(print_r($RQS,true));
// base names, varieties for detail types
$query = 'SELECT dty_ID, dty_Name, dty_Type FROM defDetailTypes';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$DTN[$row['dty_ID']] = $row['dty_Name'];
	$DTT[$row['dty_ID']] = $row['dty_Type'];
}

$INV = mysql__select_assoc('defTerms',	//saw Enum change just assoc id to related id
							'trm_ID',
							'trm_InverseTermID',
							'1');

// lookup detail type enum values
$query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID FROM defTerms';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$TL[$row['trm_ID']] = $row;
	$TLV[$row['trm_Label']] = $row;
}

/// group names
mysql_connection_select(USERS_DATABASE) or die(mysql_error());
$WGN = mysql__select_assoc('sysUGrps grp', 'grp.ugr_ID', 'grp.ugr_Name', "ugr_Type ='workgroup'");
$UGN = mysql__select_assoc('sysUGrps grp', 'grp.ugr_ID', 'grp.ugr_Name', "ugr_Type ='user'");
mysql_connection_select(DATABASE) or die(mysql_error());


$GEO_TYPES = array(
	'r' => 'bounds',
	'c' => 'circle',
	'pl' => 'polygon',
	'l' => 'path',
	'p' => 'point'
);

// set parameter defaults
$MAX_DEPTH = @$_REQUEST['depth'] ? intval($_REQUEST['depth']) : 0;	// default to only one level
$REVERSE = @$_REQUEST['rev'] === 'no' ? false : true;	//default to including reverse pointers
$WOOT = @$_REQUEST['woot'] ? intval($_REQUEST['woot']) : 0;	//default to not output text content
$OUTPUT_STUBS = @$_REQUEST['stub'] === '1'? true : false;	//default to not output stubs
$INCLUDE_FILE_CONTENT = (@$_REQUEST['fc'] && $_REQUEST['fc'] == 0? false :true);	// default to expand xml file content
$SUPRESS_LOOPBACKS = (@$_REQUEST['slb'] && $_REQUEST['slb'] == 0? false :true);	// default to supress loopbacks

// check filter string has restricted characters only
$filterString = (@$_REQUEST['filters'] ? $_REQUEST['filters'] : null);
if ( $filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/',$filterString)) {
	die(" error invalid json record type filters string");
}
//decode the json string to convert it to an array of rectypes for each level
$RECTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RECTYPE_FILTERS)) {
	die(" error decoding json record type filters string");
}

// handle special case for collection where ids are stored in teh session.
if (preg_match('/_COLLECTED_/', $_REQUEST['q'])) {
	if (!session_id()) session_start();
	$collection = &$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['record-collection'];
	if (count($collection) > 0) {
		$_REQUEST['q'] = 'ids:' . join(',', array_keys($collection));
	} else {
		$_REQUEST['q'] = '';
	}
}


//----------------------------------------------------------------------------//
//  Authentication
//----------------------------------------------------------------------------//

if (@$ARGV) {	// commandline actuation
	function get_user_id() { return 0; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(HEURIST_USER_GROUP_ID); }
	function is_admin() { return false; }
	function is_logged_in() { return true; }
	$pub_id = 0;

} else {	// loggin required entry
	$pub_id = 0;
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	if (!is_logged_in()) { // check if the record being retrieved is a single non-protected record
		if (!single_record_retrieval($_REQUEST['q'])) {
			header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
			return;
		}
	}
}

$relRT = (defined('RT_RELATION')?RT_RELATION:0);
$relTypDT = (defined('DT_RELATION_TYPE')?DT_RELATION_TYPE:0);
$relSrcDT = (defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = (defined('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);

//----------------------------------------------------------------------------//
// Traversal functions
// The aim here is to bundle all the queries for each level of relationships
// into one query, rather than doing them all recursively.
//----------------------------------------------------------------------------//

/**
* findPointers - Helper function that finds recIDs of record pointer details for all records in a given set of recIDs
* which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search from details
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @return $ret a comma separated list of recIDs
**/
function findPointers($rec_ids, $rtyIDs) {
	$rv = array();
	//saw TODO add error checking for numeric values in $rtyIDs
	// find all detail values for resource type details which exist for any record with an id in $rec_ids
	// and also is of a type in rtyIDs if rtyIDs is set to non null
	$query = 'SELECT distinct dtl_Value '.
			'FROM recDetails '.
				'LEFT JOIN defDetailTypes on dtl_DetailTypeID = dty_ID '.
				(isset($rtyIDs) && count($rtyIDs)>0 ? 'LEFT JOIN Records on rec_ID = dtl_Value ': '').
			'WHERE dtl_RecID in (' . join(',', $rec_ids) .') '.
				($rtyIDs && count($rtyIDs)>0 ? 'AND rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '').
				'AND dty_Type = "resource"';
	$res = mysql_query($query);
	while ($res && $row = mysql_fetch_assoc($res)) {
		$rv[$row['dtl_Value']] = 1;
	}
	return array_keys($rv);
}

/**
* findReversePointers - Helper function that finds recIDs of all records excluding relationShip records
* that have a pointer detail value that is in a given set of recIDs which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search from details
* @param $pointers an out array of recIDs to store look up detailTypeID by pointed_to recID by pointed_to_by recID
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @return $ret a comma separated list of recIDs of pointed_to_by records
**/
function findReversePointers($rec_ids, &$pointers, $rtyIDs) {
global $relRT;
	$rv = array();
	//saw TODO add error checking for numeric values in $rtyIDs
	// find all detail values, detailTypeID and source recID for resource type details
	// which exist for any record with an id in $rec_ids
	// and also is of a type in rtyIDs if rtyIDs is set to non null
	$query = 'SELECT dtl_Value, dtl_DetailTypeID, dtl_RecID '.
			'FROM recDetails '.
				'LEFT JOIN defDetailTypes ON dtl_DetailTypeID = dty_ID '.
				'LEFT JOIN Records ON rec_ID = dtl_RecID '.
			'WHERE dty_Type = "resource" '.
				'AND dtl_Value IN (' . join(',', $rec_ids) .') '.
				($rtyIDs && count($rtyIDs)>0 ? 'AND rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '').
				'AND rec_RecTypeID != '.$relRT;
	$res = mysql_query($query);
	while ($res && $row = mysql_fetch_assoc($res)) {
		if (! @$pointers[$row['dtl_Value']]) {
			$pointers[$row['dtl_Value']] = array();
		}
		// [targetRecID][sourceRecID] => detailTypeID
		//saw TODO: change this to 3d lookup to allow for multiple detailType pointers
		$pointers[$row['dtl_Value']][$row['dtl_RecID']] = $row['dtl_DetailTypeID'];
		$rv[$row['dtl_RecID']] = 1;
	}
	return array_keys($rv);
}

/**
* findRelatedRecords - Helper function that finds recIDs of all related records using relationShip records
* that have a pointer detail value that is in a given set of recIDs which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search related records
* @param $relationships an out array of recIDs to store look up relationRecID by supplied target/source recIDs
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @return $ret a comma separated list of recIDs of other record recIDs
**/
function findRelatedRecords($rec_ids, &$relationships, $rtyIDs) {
global $relSrcDT, $relTrgDT, $relTypDT, $relRT;
	$rv = array();
	//saw TODO add error checking for numeric values in $rtyIDs
	// find all from recID, relRecID and toRecID triples
	// which exist for any from record with an id in $rec_ids
	// and also is of a type in rtyIDs if rtyIDs is set to non null
	$query = 'SELECT f.dtl_Value, '. // from detail
				'rel.rec_ID, '.	// relation record
				't.dtl_Value '.
			'FROM recDetails f '.
				'LEFT JOIN Records rel ON rel.rec_ID = f.dtl_RecID '.
				'LEFT JOIN recDetails t ON t.dtl_RecID = rel.rec_ID '.
				($rtyIDs && count($rtyIDs)>0 ? 'LEFT JOIN Records c on c.rec_ID = t.dtl_Value ': '').
			"WHERE f.dtl_DetailTypeID IN ($relSrcDT,$relTrgDT) ".
				"AND rel.rec_RecTypeID = $relRT ".
				'AND f.dtl_Value IN (' . join(',', $rec_ids) . ') '.
				($rtyIDs && count($rtyIDs)>0 ? 'AND c.rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '').
				"AND t.dtl_DetailTypeID = IF(f.dtl_DetailTypeID = $relSrcDT, $relTrgDT, $relSrcDT)";
	$res = mysql_query($query);
	while ($res && $row = mysql_fetch_row($res)) {
		if (! @$relationships[$row[0]]) {
			$relationships[$row[0]] = array();
		}
		// check for duplicates saw TODO: optimize to lookup [fromRecID][relRecID] => 1
		if (!in_array($row[1],$relationships[$row[0]])) {
			array_push($relationships[$row[0]], $row[1]);
		}
		if ($row[2]) {
			$rv[$row[2]] = 1;
		}
	}
	return array_keys($rv);
}


/**
* buildTree - Function to build the structure for a set of records and all there related(linked) records
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to build the tree
* @param $reverse_pointers an out array of recIDs to store look up detailTypeID by pointed_to recID by pointed_to_by recID
* @param $relationships an out array of recIDs to store look up relationRecID by supplied target/source recIDs
**/
function buildTree($rec_ids, &$reverse_pointers, &$relationships) {
	global $MAX_DEPTH, $REVERSE, $RECTYPE_FILTERS;
	$depth = 0;
	$filter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth]: null );
	if ($filter){
		$query = 'SELECT rec_ID from Records '.
					'WHERE rec_ID in ('.join(",",$rec_ids).') '.
					'AND rec_RecTypeID in ('.join(",",$filter).')';
//echo "query = $query <br/>";
		$filteredIDs = array();
		$res = mysql_query($query);
		while ($res && $row = mysql_fetch_row($res)) {
			$filteredIDs[$row[0]] =1;
		}
		$rec_ids = array_keys($filteredIDs);
	}
//echo "depth = $depth  filter = ". print_r($filter,true)."<br/>";
//echo json_format($rec_ids).'<br/>';
	while ($depth++ < $MAX_DEPTH  &&  count($rec_ids) > 0) {
		$filter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth]: null );
//echo "depth = $depth  filter = ". print_r($filter,true)."<br/>";
		$p_rec_ids = findPointers($rec_ids, $filter);
		$rp_rec_ids = $REVERSE ? findReversePointers($rec_ids, $reverse_pointers, $filter) : array();
		$rel_rec_ids = findRelatedRecords($rec_ids, $relationships, $filter);
		$rec_ids = array_merge($p_rec_ids, $rp_rec_ids, $rel_rec_ids); // record set for a given level
//echo json_format($rec_ids).'<br/>';
	}
}


//----------------------------------------------------------------------------//
//  Output functions
//----------------------------------------------------------------------------//

function outputRecords($result) {
	global $OUTPUT_STUBS;
	$reverse_pointers = array();
	$relationships = array();

	$rec_ids = array();
	foreach ($result['records'] as $record) {	// get all the result recIDs
		array_push($rec_ids, $record['rec_ID']);
	}

	buildTree($rec_ids, $reverse_pointers, $relationships);

	foreach ($result['records'] as $record) {
		outputRecord($record, $reverse_pointers, $relationships, 0,$OUTPUT_STUBS);
	}
}

function outputRecord($record, &$reverse_pointers, &$relationships, $depth=0, $outputStub=false, $parentID = null) {
	global $RTN, $DTN, $RQS, $WGN, $MAX_DEPTH, $WOOT, $RECTYPE_FILTERS, $SUPRESS_LOOPBACKS, $relRT, $relTrgDT, $relSrcDT;
/*****DEBUG****///error_log("rec = ".$record['rec_ID']);
/*****DEBUG****///error_log(" in outputRecord record = \n".print_r($record,true));
	$filter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth]: null );
	if ( isset($filter) && !in_array($record['rec_RecTypeID'],$filter)){
		if ($record['rec_RecTypeID'] != $relRT) {
			if ($depth > 0) {
				if ($outputStub){
					outputRecordStub($record);
				}else{
					output( $record['rec_ID'] );
				}
			}
			return;
		}
	}
    
	openTag('record');
	makeTag('id', null, $record['rec_ID']);
	makeTag('type', array('id' => $record['rec_RecTypeID'], 'conceptID'=>getRecTypeConceptID($record['rec_RecTypeID'])), $RTN[$record['rec_RecTypeID']]);
	makeTag('title', null, $record['rec_Title']);
	if ($record['rec_URL']) {
		makeTag('url', null, $record['rec_URL']);
	}
	if ($record['rec_ScratchPad']) {
		makeTag('notes', null, replaceIllegalChars($record['rec_ScratchPad']));
	}
	makeTag('added', null, $record['rec_Added']);
	makeTag('modified', null, $record['rec_Modified']);
	// saw FIXME  - need to output groups only
	if (array_key_exists($record['rec_OwnerUGrpID'],$WGN) || array_key_exists($record['rec_OwnerUGrpID'],$UGN)) {
		makeTag('workgroup', array('id' => $record['rec_OwnerUGrpID']),
							$record['rec_OwnerUGrpID'] > 0 ?
								(array_key_exists($record['rec_OwnerUGrpID'],$WGN)?
									$WGN[$record['rec_OwnerUGrpID']]
									: (array_key_exists($record['rec_OwnerUGrpID'],$UGN)?
										$UGN[$record['rec_OwnerUGrpID']]:'Unknown'))
								: 'public');
	}

	foreach ($record['details'] as $dt => $details) {
		foreach ($details as $value) {
			outputDetail($dt, $value, $record['rec_RecTypeID'], $reverse_pointers, $relationships, $depth, $outputStub, $record['rec_RecTypeID'] == $relRT ? $parentID: $record['rec_ID']);
		}
	}

	if ($WOOT > $depth) {
/*****DEBUG****///error_log(" in outputRecord WOOT = \n".print_r($WOOT,true));
		$result = loadWoot(array('title' => 'record:'.$record['rec_ID']));
/*****DEBUG****///error_log(" in outputRecord-WOOT result = \n".print_r($result,true));
		if ($result['success']) {
			openTag('woot', array('title' => 'record:'.$record['rec_ID']));
//			openCDATA();
			foreach ($result['woot']['chunks'] as $chunk) {
				$text = preg_replace("/&nbsp;/g"," ",$chunk['text']);
				output( replaceIllegalChars($text) . "\n" );
			}
//			closeCDATA();
			closeTag('woot');
		}
	}

	if ($depth < $MAX_DEPTH) {
		if (array_key_exists($record['rec_ID'], $reverse_pointers)) {
			foreach ($reverse_pointers[$record['rec_ID']] as $rec_id => $dt) {
				if ($SUPRESS_LOOPBACKS && $rec_id == $parentID){	//pointing back to the parent so skip
					continue;
				}
				$child = loadRecord($rec_id);
				openTag('reversePointer', array('id' => $dt, 'conceptID'=>getDetailTypeConceptID($dt), 'type' => $DTN[$dt], 'name' => $RQS[$child['rec_RecTypeID']][$dt]));
				outputRecord($child, $reverse_pointers, $relationships, $depth + 1, $outputStub,$record['rec_ID']);
				closeTag('reversePointer');
			}
		}
		if (array_key_exists($record['rec_ID'], $relationships)  &&  count($relationships[$record['rec_ID']]) > 0) {
			openTag('relationships');
			foreach ($relationships[$record['rec_ID']] as $rel_id) {
				$rel = loadRecord($rel_id);
/*****DEBUG****///error_log(" relRec = ".print_r($rel,true));
				foreach ( $rel['details'][$relSrcDT] as $dtID => $from){
					$fromType = $from['type'];
				}
				foreach ( $rel['details'][$relTrgDT] as $dtID => $to){
					$toType = $to['type'];
				}
				if (in_array($fromType,$filter) || in_array($toType,$filter)) {
					outputRecord($rel, $reverse_pointers, $relationships, $depth, $outputStub,$record['rec_ID']);
			}
			}
			closeTag('relationships');
		}
	}
	closeTag('record');
}

function outputRecordStub($recordStub) {
	global $RTN;
/*****DEBUG****///error_log( "ouput recordStub ".print_r($recordStub,true));

	openTag('record',array('isStub'=> 1));
	makeTag('id', null, array_key_exists('id',$recordStub)?$recordStub['id']:$recordStub['rec_ID']);
	$type = array_key_exists('type',$recordStub)?$recordStub['type']:$recordStub['rec_RecTypeID'];
	makeTag('type', array('id' => $type, 'conceptID'=>getRecTypeConceptID($type)), $RTN[$type]);
	$title = array_key_exists('title',$recordStub)?$recordStub['title']:$recordStub['rec_Title'];
	makeTag('title', null, $title);
	closeTag('record');
}

function makeFileContentNode($file){
//	$filename = HEURIST_UPLOAD_DIR ."/" . $file['id'];
	$filename = $file['URL'];
	if ($file['type'] ==="application/xml"){// && file_exists($filename)) {
		$xml = simplexml_load_file($filename);
/*****DEBUG****///error_log(" xml = ". print_r($xml,true));
		// convert to xml
		$xml = $xml->asXML();
		// remove the name space
		$xml = preg_replace("/\s*xmlns=(?:\"[^\"]*\"|\'[^\']*\'|\S+)\s*/","",$xml);
/*****DEBUG****///error_log(" xml = ". print_r($xml,true));
		$xml = simplexml_load_string($xml);
		if (!$xml){
			$attrs = array("type" => "unknown", "error" => "invalid xml content");
			$content = "Unable to read ". $file['origName']. " as xml file";
			makeTag('content',$attrs,$content);
		}else{
			if ( count($xml->xpath('//TEI'))) {
				$attrs = array("type" => "TEI");
				$teiHeader = $xml->xpath('//TEI/teiHeader');
				$content = $xml->xpath('//TEI/text');
			}else if ( count($xml->xpath('//TEI.2'))) {
				$attrs = array("type" => "TEI.2");
				$teiHeader = $xml->xpath('//TEI.2/teiHeader');
				$content = $xml->xpath('//TEI.2/text');
			}
			$content = $teiHeader[0]->asXML().$content[0]->asXML();
/*****DEBUG****///error_log(" content = ". print_r($content,true));
			makeTag('content',$attrs,$content,true,false);
		}
	}
}

function outputDetail($dt, $value, $rt, &$reverse_pointers, &$relationships, $depth=0, $outputStub, $parentID) {
	global $DTN, $DTT, $TL, $RQS, $INV, $GEO_TYPES, $MAX_DEPTH, $INCLUDE_FILE_CONTENT, $SUPRESS_LOOPBACKS, $relTypDT, $relTrgDT, $relSrcDT;
/*****DEBUG****///error_log("in outputDetail dt = $dt value = ". print_r($value,true));

	$attrs = array('id' => $dt, 'conceptID'=>getDetailTypeConceptID($dt));
	if (array_key_exists($dt, $DTN)) {
		$attrs['type'] = $DTN[$dt];
	}
	if (array_key_exists($rt, $RQS)  &&  array_key_exists($dt, $RQS[$rt])) {
		$attrs['name'] = $RQS[$rt][$dt];
	}
	if ($dt === $relTypDT  &&  array_key_exists($value, $INV) && $INV[$value] && array_key_exists($INV[$value], $TL)) {	//saw Enum change
		$attrs['inverse'] = $TL[$INV[$value]]['trm_Label'];
		$attrs['invTermConceptID'] = getTermConceptID($INV[$value]);
	}

	if (is_array($value)) {
		if (array_key_exists('id', $value)) {
			// record pointer
			if ($dt === $relSrcDT || $dt === $relTrgDT) { // in a relationship record don't expand from side
				if ($value['id'] == $parentID){
					$attrs['direction'] = "from";
					if ($dt === $relTrgDT) {
						$attrs['useInverse'] = 'true';
					}
					if ($SUPRESS_LOOPBACKS){
						openTag('detail', $attrs);
						if ($outputStub) {
							outputRecordStub(loadRecordStub($value['id']));
						}else{
							output( $value['id'] );
						}
						closeTag('detail');
						return;
					}
				}else{
					$attrs['direction'] = "to";
					if ($dt === $relSrcDT) {
						$attrs['useInverse'] = 'true';
					}
				}
				openTag('detail', $attrs);
				outputRecord(loadRecord($value['id']), $reverse_pointers, $relationships, $depth + 1, $outputStub, $parentID);
				closeTag('detail');
			}else if ($depth < $MAX_DEPTH) {
				openTag('detail', $attrs);
				outputRecord(loadRecord($value['id']), $reverse_pointers, $relationships, $depth + 1, $outputStub, $parentID);
				closeTag('detail');
			} else if ($outputStub) {
				openTag('detail', $attrs);
				outputRecordStub(loadRecordStub($value['id']));
				closeTag('detail');
			} else {
				makeTag('detail', $attrs, $value['id']);
			}
		} else if (array_key_exists('file', $value)) {
			$file = $value['file'];
/*****DEBUG****///error_log(" in outputDetail file = \n".print_r($file,true));
			openTag('detail', $attrs);
				openTag('file');
					makeTag('id', null, $file['id']);
					makeTag('nonce', null, $file['nonce']);
					makeTag('origName', null, $file['origName']);
					makeTag('type', null, $file['type']);
					makeTag('size', array('units' => 'kB'), $file['size']);
					makeTag('date', null, $file['date']);
					makeTag('description', null, $file['description']);
					makeTag('url', null, $file['URL']);
					makeTag('thumbURL', null, $file['thumbURL']);
					if ($INCLUDE_FILE_CONTENT) {
						makeFileContentNode($file);
					}
				closeTag('file');
			closeTag('detail');
		} else if (array_key_exists('geo', $value)) {
			openTag('detail', $attrs);
				openTag('geo');
					makeTag('type', null, $GEO_TYPES[$value['geo']['type']]);
					makeTag('wkt', null, $value['geo']['wkt']);
				closeTag('geo');
			closeTag('detail');
		}
	} else if ($DTT[$dt] === 'date') {
		openTag('detail', $attrs);
		if (strpos($value,"|")===false) {
			outputDateDetail($attrs, $value);
		}else{
			outputTemporalDetail($attrs, $value);
		}
		closeTag('detail');
	} else if ($DTT[$dt] === 'resource') {
		openTag('detail', $attrs);
		outputRecord(loadRecord($value), $reverse_pointers, $relationships, $depth + 1, $outputStub, $parentID);
		closeTag('detail');
	} else if (($DTT[$dt] === 'enum' || $DTT[$dt] === 'relationtype' ) && array_key_exists($value,$TL)) {
		$attrs['termConceptID'] = getTermConceptID($value);
		if (@$TL[$value]['trm_ParentTermID']) {
			$attrs['ParentTerm'] = $TL[$TL[$value]['trm_ParentTermID']]['trm_Label'];
		}
/*****DEBUG****///error_log("value = ".$value." label = ".$TL[$value]['trm_Label']);
		makeTag('detail', $attrs, $TL[$value]['trm_Label']);
	} else {
		makeTag('detail', $attrs, replaceIllegalChars($value));
	}
}

$typeDict = array(	"s" =>	"Simple Date",
					"c" =>	"C14 Date",
					"f" =>	"Aproximate Date",
					"p" =>	"Date Range",
					"d" =>	"Duration"
					);

$fieldsDict = array(	"VER" =>	"Version Number",
						"TYP" =>	"Temporal Type Code",
						"PRF" =>	"Probability Profile",
						"SPF" =>	"Start Profile",
						"EPF" =>	"End Profile",
						"CAL" =>	"Calibrated",
						"COD" =>	"Laboratory Code",
						"DET" =>	"Determination Type",
						"COM" =>	"Comment",
						"EGP" =>	"Egyptian Date"
						);

$determinationCodes = array(	0 =>	"Unknown",
								1 =>	"Attested",
								2 =>	"Conjecture",
								3 =>	"Measurement"
							);

$profileCodes = array(	0 =>	"Flat",
						1 =>	"Central",
						2 =>	"Slow Start",
						3 =>	"Slow Finish"
						);

$tDateDict = Array(	"DAT" =>	"ISO DateTime",
					"BPD" =>	"Before Present (1950) Date",
					"BCE" =>	"Before Current Era",
					"TPQ" =>	"Terminus Post Quem",
					"TAQ" =>	"Terminus Ante Quem",
					"PDB" =>	"Probable begin",
					"PDE" =>	"Probable end",
					"SRT" =>	"Sortby Date"
					);

$tDurationDict = array(	"DUR" =>	"Simple Duration",
						"RNG" =>	"Range",
						"DEV" =>	"Standard Deviation",
						"DVP" =>	"Deviation Positive",
						"DVN" =>	"Deviation Negative",
						"ERR" =>	"Error Margin"
						);

function outputTemporalDetail($attrs, $value) {
	global $typeDict,$fieldsDict,$determinationCodes,$profileCodes,$tDateDict,$tDurationDict;
	$temporalStr = substr_replace($value,"",0,1);
	$props = explode("|", $temporalStr);
	$properties = array();
	foreach ($props as $prop) {
		list($tag, $val) = explode("=",$prop);
		$properties[$tag] = $val;
	}
	openTag('temporal',array("version" => $properties['VER'], "type" => $typeDict[$properties['TYP']] ));
		unset($properties['VER']);
		unset($properties['TYP']);
		foreach( $properties as $tag => $val) {
			if (array_key_exists($tag,$fieldsDict)) { //simple property
				openTag('property',array('type'=>$tag, 'name'=>$fieldsDict[$tag]));
				switch ($tag) {
					case "DET":
						output( $determinationCodes[$val] );
						break;
					case "PRF":
					case "SPF":
					case "EPF":
						output( $profileCodes[$val] );
						break;
					default:
						output( $val );
				}
				closeTag('property');
			}else if (array_key_exists($tag,$tDateDict)) {
				openTag('date',array('type'=>$tag, 'name'=>$tDateDict[$tag]));
				outputTDateDetail(null,$val);
				closeTag('date');
			}else if (array_key_exists($tag,$tDurationDict)) {
				openTag('duration',array('type'=>$tag, 'name'=>$tDurationDict[$tag]));
				outputDurationDetail(null,$val);
				closeTag('duration');
			}
		}
	closeTag('temporal');
}

function outputDateDetail($attrs, $value) {
	makeTag('raw', null, $value);
	if (preg_match('/^\\s*-?(\\d+)\\s*$/', $value, $matches)) { // year only
		makeTag('year', null, $matches[1]);
	} else {
		$date = strtotime($value);
		if ($date) {
			makeTag('year', null, date('Y', $date));
			makeTag('month', null, date('n', $date));
			makeTag('day', null, date('j', $date));
			if (preg_match("![ T]!", $value)) {	// looks like there's a time
				makeTag('hour', null, date('H', $date));
				makeTag('minutes', null, date('i', $date));
				makeTag('seconds', null, date('s', $date));
			}
		} else {
			// cases that strtotime doesn't catch
			if (preg_match('/^([+-]?\d\d\d\d+)-(\d\d)$/', $value, $matches)) {
				// e.g. MMMM-DD
				makeTag('year', null, intval($matches[1]));
				makeTag('month', null, intval($matches[2]));
			} else {
				@list($date, $time) = preg_split("![ T]!", $value);
				@list($y,$m,$d) = array_map("intval", preg_split("![-\/]!", $date));
				if (! (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31)) {
					@list($d,$m,$y) = array_map("intval", preg_split("![-\/]!", $date));
				}
				if (! (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31)) {
					@list($m,$d,$y) = array_map("intval", preg_split("![-\/]!", $date));
				}
				if (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31) {
					makeTag('year', null, $y);
					makeTag('month', null, $m);
					makeTag('day', null, $d);
				}

				@list($h,$m,$s) = array_map("intval", preg_split("![-:]!", $time));
				if (0 <= $h && $h <= 23) {
					makeTag('hour', null, $h);
				}
				if (0 <= $m && $m <= 59) {
					makeTag('minutes', null, $m);
				}
				if (0 <= $s && $s <= 59) {
					makeTag('seconds', null, $s);
				}
			}
		}
	}
}

function outputTDateDetail($attrs, $value) {
	makeTag('raw', null, $value);
	if (preg_match('/^([^T\s]*)(?:[T\s+](\S*))?$/', $value, $matches)) { // valid ISO Date split into date and time
		$date = @$matches[1];
		$time = @$matches[2];
		if (!$time && preg_match('/:\./',$date)) {
			$time = $date;
			$date = null;
		}
		if ($date) {
			preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/',$date,$matches);
			if (@$matches[1]) makeTag('year', null, $matches[1]);
			if (@$matches[2]) makeTag('month', null, $matches[2]);
			if (@$matches[3]) makeTag('day', null, $matches[3]);
		}
		if ($time) {
			preg_match('/(?:(0?[1-9]|1\d|2[0-3])[:\.])?(?:(0?[1-9]|[0-5]\d)[:\.])?(?:(0?[1-9]|[0-5]\d))?/',$time,$matches);
			if (@$matches[1]) makeTag('hour', null, $matches[1]);
			if (@$matches[2]) makeTag('minutes', null, $matches[2]);
			if (@$matches[3]) makeTag('seconds', null, $matches[3]);
		}
	}
}

function outputDurationDetail($attrs, $value) {
	makeTag('raw', null, $value);
	if (preg_match('/^P([^T]*)T?(.*)$/', $value, $matches)) { // valid ISO Duration split into date and time
		$date = @$matches[1];
		$time = @$matches[2];
		if ($date) {
			if (preg_match('/[YMD]/',$date)){ //char separated version 6Y5M8D
				preg_match('/(?:(\d+)Y)?(?:(\d|0\d|1[012])M)?(?:(0?[1-9]|[12]\d|3[01])D)?/',$date,$matches);
			}else{ //delimited version  0004-12-06
				preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/',$date,$matches);
			}
			if (@$matches[1]) makeTag('year', null, intval($matches[1]));
			if (@$matches[2]) makeTag('month', null, intval($matches[2]));
			if (@$matches[3]) makeTag('day', null, intval($matches[3]));
		}
		if ($time) {
			if (preg_match('/[HMS]/',$time)){ //char separated version 6H5M8S
				preg_match('/(?:(0?[1-9]|1\d|2[0-3])H)?(?:(0?[1-9]|[0-5]\d)M)?(?:(0?[1-9]|[0-5]\d)S)?/',$time,$matches);
			}else{ //delimited version  23:59:59
				preg_match('/(?:(0?[1-9]|1\d|2[0-3])[:\.])?(?:(0?[1-9]|[0-5]\d)[:\.])?(?:(0?[1-9]|[0-5]\d))?/',$time,$matches);
			}
			if (@$matches[1]) makeTag('hour', null, intval($matches[1]));
			if (@$matches[2]) makeTag('minutes', null, intval($matches[2]));
			if (@$matches[3]) makeTag('seconds', null, intval($matches[3]));
		}
	}
}

$invalidChars = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),chr(18),chr(19),chr(20),chr(21),chr(22),chr(23),chr(24),chr(25),chr(26),chr(27),chr(28),chr(29),chr(30),chr(31)); // invalid chars that need to be stripped from the data.
$replacements = array("[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]","[?]"," ","[?]","[?]","[?]","[?]","[?]");
function replaceIllegalChars($text) {
	global $invalidChars, $replacements;
	return str_replace($invalidChars ,$replacements,$text);
}

function check($text) {
	global $invalidChars;
	foreach ($invalidChars as $charCode){
		//$pattern = "". chr($charCode);
		if (strpos($text,$charCode)) {
			error_log("found invalid char " );
			return false;
		}
	}
	return true;
}

//----------------------------------------------------------------------------//
//  Turn off output buffering
//----------------------------------------------------------------------------//

if (! @$ARGV) {
	@apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);


//----------------------------------------------------------------------------//
//  Output
//----------------------------------------------------------------------------//

$result = loadSearch($_REQUEST);
$query_attrs = array_intersect_key($_REQUEST, array('q'=>1,'w'=>1,'depth'=>1,'f'=>1,'limit'=>1,'offset'=>1,'db'=>1,'stub'=>1,'woot'=>1,'fc'=>1,'slb'=>1,'filters'=>1));
if ($pub_id) {
    $query_attrs['pubID'] = $pub_id;
}

   
    openTag('hml');
    /*
    openTag('hml', array(
	    'xmlns' => 'http://heuristscholar.org/heurist/hml',
	    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
	    'xsi:schemaLocation' => 'http://heuristscholar.org/heurist/hml http://heuristscholar.org/heurist/schemas/hml.xsd')
    );
    */
    makeTag('query', $query_attrs);

    makeTag('dateStamp', null, date('c'));

    if (array_key_exists('error', $result)) {
	    makeTag('error', null, $result['error']);
    } else {
    /*	openTag('vocabularies'); //	saw TODO change to output Ontologies
	    foreach($VOC as $vocabulary){
		    $attrs = array('id' => $vocabulary['vcb_ID']);
		    if ($vocabulary['vcb_RefURL']) {
			    $attrs['namespace'] = $vocabulary['vcb_RefURL'];
		    }
		    makeTag('vocabulary', $attrs, $vocabulary['vcb_Name']);
	    }
	    closeTag('vocabularies');
    */	makeTag('resultCount', null, $result['resultCount']);
	    makeTag('recordCount', null, $result['recordCount']);
	    openTag('records');
	    outputRecords($result);
	    closeTag('records');
    }
    closeTag('hml');

?>

