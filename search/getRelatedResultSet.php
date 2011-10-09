<?php

/*<!--hml.php
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

header("Content-type: text/javascript");
//header('Content-type: text/xml; charset=utf-8');
/*echo "<?xml version='1.0' encoding='UTF-8'?>\n";
*/
require_once(dirname(__FILE__).'/../common/config/defineFriendlyServers.php');
require_once(dirname(__FILE__).'/../common/config/initialise.php');
require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../search/getSearchResults.php');
require_once(dirname(__FILE__).'/../common/php/getRecordInfoLibrary.php');

mysql_connection_db_select(DATABASE);


//----------------------------------------------------------------------------//
//  Retrieve record- and detail- type metadata
//----------------------------------------------------------------------------//

$RTN = array();	//record type name
$RQS = array();	//record type specific detail name
$DTN = array();	//detail type name
$DTT = array();	//detail type base type
$INV = array();	//relationship term inverse
$WGN = array();	//work group name
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
//error_log(print_r($RQS,true));
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
mysql_connection_db_select(USERS_DATABASE) or die(mysql_error());
$WGN = mysql__select_assoc('sysUGrps grp', 'grp.ugr_ID', 'grp.ugr_Name', "ugr_Type ='workgroup'");
mysql_connection_db_select(DATABASE) or die(mysql_error());


$GEO_TYPES = array(
	'r' => 'bounds',
	'c' => 'circle',
	'pl' => 'polygon',
	'l' => 'path',
	'p' => 'point'
);

$MAX_DEPTH = @$_REQUEST['depth'] ? intval($_REQUEST['depth']) : 1;
$REVERSE = @$_REQUEST['rev'] === 'no' ? false : true;
$EXPAND_REV_PTR = @$_REQUEST['revexpand'] === 'no' ? false : true;

$filterString = (@$_REQUEST['rtfilters'] ? $_REQUEST['rtfilters'] : null);
if ( $filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/',$filterString)) {
	die(" error invalid json rectype filters string");
}
$RECTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RECTYPE_FILTERS)) {
	die(" error decoding json rectype filters string");
}
//error_log("rt filters".print_r($RECTYPE_FILTERS,true));

$filterString = (@$_REQUEST['relfilters'] ? $_REQUEST['relfilters'] : null);
if ( $filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/',$filterString)) {
	die(" error invalid json relation type filters string");
}
$RELTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RELTYPE_FILTERS)) {
	die(" error decoding json relation type filters string");
}
//error_log("rel filters".print_r($RELTYPE_FILTERS,true));

$filterString = (@$_REQUEST['ptrfilters'] ? $_REQUEST['ptrfilters'] : null);
if ( $filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/',$filterString)) {
	die(" error invalid json pointer type filters string");
}
$PTRTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($PTRTYPE_FILTERS)) {
	die(" error decoding json pointer type filters string");
}
//error_log("ptr filters".print_r($PTRTYPE_FILTERS,true));
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

require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
$ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
								'ugl_UserID='.get_user_id().' and grp.ugr_Type != "User" order by ugl_GroupID');
if (is_logged_in()){
	array_push($ACCESSABLE_OWNER_IDS,get_user_id());
	if (!in_array(0,$ACCESSABLE_OWNER_IDS)){
		array_push($ACCESSABLE_OWNER_IDS,0);
	}
}

//----------------------------------------------------------------------------//
// Traversal functions
// The aim here is to bundle all the queries for each level of relationships
// into one query, rather than doing them all recursively.
//----------------------------------------------------------------------------//

function findPointers($qrec_ids, &$recSet, $depth, $rtyIDs, $dtyIDs) {
global $ACCESSABLE_OWNER_IDS;
//error_log("in findPointers");
	$nlrIDs = array(); // new linked record IDs
	$query = 'SELECT dtl_RecID as srcRecID, src.rec_RecTypeID as srcType,'.
					'dtl_Value as trgRecID, trg.rec_RecTypeID as trgType,'.
					'dtl_DetailTypeID as ptrDetailTypeID '.
					', trg.rec_Title as trgTitle '.
					',trg.rec_URL as trgURL '.
					', trg.rec_OwnerUGrpID as trgOwner '.
					', trg.rec_NonOwnerVisibility as trgVis '.
				'FROM recDetails LEFT JOIN defDetailTypes on dtl_DetailTypeID = dty_ID '.
									'LEFT JOIN Records src on src.rec_ID = dtl_RecID '.
									'LEFT JOIN Records trg on trg.rec_ID = dtl_Value '.
				'WHERE dtl_RecID in (' . join(',', $qrec_ids) .') '.
				($rtyIDs && count($rtyIDs)>0 ? 'AND trg.rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '').
				($dtyIDs && count($dtyIDs)>0 ? 'AND dty_ID in ('.join(',', $dtyIDs).') ' : '').
					'AND dty_Type = "resource" AND '.
					(count($ACCESSABLE_OWNER_IDS)>0?'(trg.rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') ':'(0 ').
					(is_logged_in()?'OR NOT trg.rec_NonOwnerVisibility = "hidden")':
									'OR trg.rec_NonOwnerVisibility = "public")');

//error_log("find d $depth pointer q = $query");
//echo "\n $query\n";
	$res = mysql_query($query);
//error_log("mysql error = ".mysql_error($res));
	while ($res && $row = mysql_fetch_assoc($res)) {
		// if target is not in the result
//echo "\n".print_r($row);
		$nlrIDs[$row['trgRecID']] = 1;	//save it for next level query
		if (!@$recSet['infoByDepth'][$depth]['ptrtypes']) {
			$recSet['infoByDepth'][$depth]['ptrtypes'] = array();
		}
		if (!@$recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']]) {
			$recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
		} else if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']])){
			array_push($recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']],$row['trgRecID']);
		}
		if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['recIDs'])){
			array_push($recSet['infoByDepth'][$depth]['recIDs'],$row['trgRecID']);
		}
		if (!@$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']]) {
			$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']] = array($row['trgRecID']);
		} else if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']])){
			array_push($recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']],$row['trgRecID']);
		}
		if ( !array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
			$recSet['relatedSet'][$row['trgRecID']]=array('depth'=>$depth,
															'record'=> array(null,null,
																				@$row['trgRecID'],
																				@$row['trgURL'],
																				@$row['trgType'],
																				@$row['trgTitle'],
																				@$row['trgOwner'],
																				@$row['trgVis'],
																				null,
																				null,
																				null,null));
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']) {
			$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']= array('byInvDtlType'=>array(),'byRecIDs' => array());	//create an entry
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']]) {
			$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']] = array($row['srcRecID']);
		}else if ( !in_array($row['srcRecID'],$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']])){
			array_push($recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']],$row['srcRecID']);
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']]) {
			$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']] = array($row['ptrDetailTypeID']);
		}else if ( !in_array($row['ptrDetailTypeID'],$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']])){
			array_push($recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']],$row['ptrDetailTypeID']);
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']) {
			$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']= array('byDtlType'=>array(),'byRecIDs' => array());	//create an entry
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']]) {
			$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
		}else if ( !in_array($row['trgRecID'],$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']])){
			array_push($recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']],$row['trgRecID']);
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']]) {
			$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']] = array($row['ptrDetailTypeID']);
		}else if ( !in_array($row['ptrDetailTypeID'],$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']])){
			array_push($recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']],$row['ptrDetailTypeID']);
		}
	}
	return array_keys($nlrIDs);
//	return $rv;
}
$relRT = (defined('RT_RELATION')?RT_RELATION:0);
$relTypDT = (defined('DT_RELATION_TYPE')?DT_RELATION_TYPE:0);
$relSrcDT = (defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = (defined('DT_LINKED_RESOURCE')?DT_LINKED_RESOURCE:0);

function findReversePointers($qrec_ids, &$recSet, $depth, $rtyIDs, $dtyIDs) {
global $REVERSE, $ACCESSABLE_OWNER_IDS, $relRT;
//if (!$REVERSE) return array();
//error_log("in findReversePointers");
	$nlrIDs = array(); // new linked record IDs
	$query = 'SELECT dtl_Value as srcRecID, src.rec_RecTypeID as srcType, '.
					'dtl_RecID as trgRecID, trg.rec_RecTypeID as trgType, dty_ID as ptrDetailTypeID '.
					', trg.rec_Title as trgTitle '.
					', trg.rec_URL as trgURL '.
					', trg.rec_OwnerUGrpID as trgOwner '.
					', trg.rec_NonOwnerVisibility as trgVis '.
			'FROM recDetails '.
				'LEFT JOIN defDetailTypes ON dtl_DetailTypeID = dty_ID '.
				'LEFT JOIN Records trg on trg.rec_ID = dtl_RecID '.
				'LEFT JOIN Records src on src.rec_ID = dtl_Value '.
			'WHERE dty_Type = "resource" '.
				'AND dtl_Value IN (' . join(',', $qrec_ids) .') '.
				($rtyIDs && count($rtyIDs)>0 ? 'AND trg.rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '').
				($dtyIDs && count($dtyIDs)>0 ? 'AND dty_ID in ('.join(',', $dtyIDs).') ' : '').
				"AND trg.rec_RecTypeID != $relRT AND ".
				(count($ACCESSABLE_OWNER_IDS)>0?'(trg.rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') ':'(0 ').
				(is_logged_in()?'OR NOT trg.rec_NonOwnerVisibility = "hidden")':
								'OR trg.rec_NonOwnerVisibility = "public")');

error_log("find  d $depth rev pointer q = $query");
	$res = mysql_query($query);
	while ($res && $row = mysql_fetch_assoc($res)) {
		// if target is not in the result
		$nlrIDs[$row['trgRecID']] = 1;	//save it for next level query
		if (!@$recSet['infoByDepth'][$depth]['ptrtypes']) {
			$recSet['infoByDepth'][$depth]['ptrtypes'] = array();
		}
		if (!@$recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']]) {
			$recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
		} else if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']])){
			array_push($recSet['infoByDepth'][$depth]['ptrtypes'][$row['ptrDetailTypeID']],$row['trgRecID']);
		}
		if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['recIDs'])){
			array_push($recSet['infoByDepth'][$depth]['recIDs'],$row['trgRecID']);
		}
		if (!@$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']]) {
			$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']] = array($row['trgRecID']);
		} else if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']])){
			array_push($recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']],$row['trgRecID']);
		}
		if ( !array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
			$recSet['relatedSet'][$row['trgRecID']]=array('depth'=>$depth,
															'record'=> array(null,null,
																				@$row['trgRecID'],
																				@$row['trgURL'],
																				@$row['trgType'],
																				@$row['trgTitle'],
																				@$row['trgOwner'],
																				@$row['trgVis'],
																				null,
																				null,
																				null,null));
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']) {
			$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']= array('byDtlType'=>array(),'byRecIDs' => array());	//create an entry
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']]) {
			$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']] = array($row['srcRecID']);
		}else if ( !in_array($row['srcRecID'],$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']])){
			array_push($recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']],$row['srcRecID']);
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']]) {
			$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']] = array($row['ptrDetailTypeID']);
		}else if ( !in_array($row['ptrDetailTypeID'],$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']])){
			array_push($recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']],$row['ptrDetailTypeID']);
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']) {
			$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']= array('byInvDtlType'=>array(),'byRecIDs' => array());	//create an entry
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']]) {
			$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
		}else if ( !in_array($row['trgRecID'],$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']])){
			array_push($recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']],$row['trgRecID']);
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']]) {
			$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']] = array($row['ptrDetailTypeID']);
		}else if ( !in_array($row['ptrDetailTypeID'],$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']])){
			array_push($recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']],$row['ptrDetailTypeID']);
		}
	}
	return array_keys($nlrIDs);
}

function findRelatedRecords($qrec_ids, &$recSet, $depth, $rtyIDs, $relTermIDs) {
	global $REVERSE, $ACCESSABLE_OWNER_IDS, $relRT, $relSrcDT, $relTrgDT, $relTypDT;
//error_log("in findRelatedRecords");
	$nlrIDs = array();
	$query = 'SELECT f.dtl_Value as srcRecID, rel.rec_ID as relID, '.// from detail
				'IF( f.dtl_Value IN ('. join(',', $qrec_ids) . '),1,0) as srcIsFrom, '.
				't.dtl_Value as trgRecID, trm.trm_ID as relType, trm.trm_InverseTermId as invRelType '.
				', src.rec_RecTypeID as srcType, trg.rec_RecTypeID as trgType '.
				', src.rec_Title as srcTitle, trg.rec_Title as trgTitle '.
				', src.rec_URL as srcURL, trg.rec_URL as trgURL '.
				', src.rec_OwnerUGrpID as srcOwner, trg.rec_OwnerUGrpID as trgOwner '.
				', src.rec_NonOwnerVisibility as srcVis, trg.rec_NonOwnerVisibility as trgVis '.
			'FROM recDetails f '.
				"LEFT JOIN Records rel ON rel.rec_ID = f.dtl_RecID and f.dtl_DetailTypeID = $relSrcDT ".
				"LEFT JOIN recDetails t ON t.dtl_RecID = rel.rec_ID and t.dtl_DetailTypeID = $relTrgDT ".
				"LEFT JOIN recDetails r ON r.dtl_RecID = rel.rec_ID and r.dtl_DetailTypeID = $relTypDT ".
				'LEFT JOIN defTerms trm ON trm.trm_ID = r.dtl_Value '.
				'LEFT JOIN Records trg ON trg.rec_ID = t.dtl_Value '.
				'LEFT JOIN Records src ON src.rec_ID = f.dtl_Value '.
			"WHERE rel.rec_RecTypeID = $relRT ".
				'AND (f.dtl_Value IN (' . join(',', $qrec_ids) . ') '.
				($rtyIDs && count($rtyIDs)>0 ? 'AND trg.rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '').
				($REVERSE ?'OR t.dtl_Value IN (' . join(',', $qrec_ids) . ') '.
					($rtyIDs && count($rtyIDs)>0 ? 'AND src.rec_RecTypeID in ('.join(',', $rtyIDs).') ' : '') :'').')'.
				(count($ACCESSABLE_OWNER_IDS)>0?'AND (src.rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') ':'AND (0 ').
					(is_logged_in()?'OR NOT src.rec_NonOwnerVisibility = "hidden")':
									'OR src.rec_NonOwnerVisibility = "public")').
				(count($ACCESSABLE_OWNER_IDS)>0?'AND (trg.rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') ':'AND (0 ').
					(is_logged_in()?'OR NOT trg.rec_NonOwnerVisibility = "hidden")':
									'OR trg.rec_NonOwnerVisibility = "public")').
				($relTermIDs && count($relTermIDs)>0 ? 'AND (trm.trm_ID in ('.join(',', $relTermIDs).') OR trm.trm_InverseTermID in ('.join(',', $relTermIDs).')) ' : '');
//error_log("find  d $depth related q = $query");
//echo $query;
	$res = mysql_query($query);
	while ($res && $row = mysql_fetch_assoc($res)) {
		if (!$row['relType'] && ! $row['invRelType']){ // no type information invalid relationship
			continue;
		}
//echo "row is ".print_r($row,true);
		// if source is not in the result
		if (!@$recSet['infoByDepth'][$depth]['reltypes']) {
			$recSet['infoByDepth'][$depth]['reltypes'] = array();
		}
		if ( !$row['srcIsFrom']) {//inverse relation
			$nlrIDs[$row['srcRecID']] = 1;	//save it for next level query
			if ( !in_array($row['srcRecID'],$recSet['infoByDepth'][$depth]['recIDs'])){
				array_push($recSet['infoByDepth'][$depth]['recIDs'],$row['srcRecID']);
			}
			if (!@$recSet['infoByDepth'][$depth]['rectypes'][$row['srcType']]) {
				$recSet['infoByDepth'][$depth]['rectypes'][$row['srcType']] = array($row['srcRecID']);
			} else if ( !in_array($row['srcRecID'],$recSet['infoByDepth'][$depth]['rectypes'][$row['srcType']])){
				array_push($recSet['infoByDepth'][$depth]['rectypes'][$row['srcType']],$row['srcRecID']);
			}
			if (!@$recSet['infoByDepth'][$depth]['reltypes'][$row['invRelType']]) {
				$recSet['infoByDepth'][$depth]['reltypes'][$row['invRelType']] = array($row['srcRecID']);
			} else if ( !in_array($row['srcRecID'],$recSet['infoByDepth'][$depth]['reltypes'][$row['invRelType']])){
				array_push($recSet['infoByDepth'][$depth]['reltypes'][$row['invRelType']],$row['srcRecID']);
			}
		}else{
			$nlrIDs[$row['trgRecID']] = 1;	//save it for next level query
			if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['recIDs'])){
				array_push($recSet['infoByDepth'][$depth]['recIDs'],$row['trgRecID']);
			}
			if (!@$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']]) {
				$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']] = array($row['trgRecID']);
			} else if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']])){
				array_push($recSet['infoByDepth'][$depth]['rectypes'][$row['trgType']],$row['trgRecID']);
			}
			if (!@$recSet['infoByDepth'][$depth]['reltypes'][$row['relType']]) {
				$recSet['infoByDepth'][$depth]['reltypes'][$row['relType']] = array($row['trgRecID']);
			} else if ( !in_array($row['trgRecID'],$recSet['infoByDepth'][$depth]['reltypes'][$row['relType']])){
				array_push($recSet['infoByDepth'][$depth]['reltypes'][$row['relType']],$row['trgRecID']);
			}
		}
		if ( !array_key_exists($row['srcRecID'], $recSet['relatedSet'])) {
			$recSet['relatedSet'][$row['srcRecID']]=array('depth'=>$depth,
				'record'=> array(null,null,
									$row['srcRecID'],
									$row['srcURL'],
									$row['srcType'],
									$row['srcTitle'],
									$row['srcOwner'],
									$row['srcVis'],
									null,
									null,
									null,null));
		}
		// if target is not in the result
		if ( !array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
			$recSet['relatedSet'][$row['trgRecID']]=array('depth'=>$depth,
				'record'=> array(null,null,
									$row['trgRecID'],
									$row['trgURL'],
									$row['trgType'],
									$row['trgTitle'],
									$row['trgOwner'],
									$row['trgVis'],
									null,
									null,
									null,null));
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['relLinks']) {
			$recSet['relatedSet'][$row['srcRecID']]['relLinks']= array('byRelType'=>array(),'byRecIDs' => array(), 'relRecIDs'=> array());	//create an entry
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']]) {
			$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']] = array($row['trgRecID']);
		}else if ( !in_array($row['trgRecID'],$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']])){
			array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']],$row['trgRecID']);
		}
		if ( !@$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']]) {
			$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']] = array($row['relType']);
		}else if ( !in_array($row['relType'],$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']])){
			array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']],$row['relType']);
		}
		if ( $row['relID'] && !in_array($row['relID'],$recSet['relatedSet'][$row['srcRecID']]['relLinks']['relRecIDs'])) {
			array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['relRecIDs'],$row['relID']);
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']) {
			$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']= array('byInvRelType'=>array(),'byRecIDs' => array(), 'relRecIDs'=> array());	//create an entry
		}
		$inverse = $row['invRelType']?$row['invRelType']: "-".$row['relType'];
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse]) {
			$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse] = array($row['srcRecID']);
		}else if ( !in_array($row['srcRecID'],$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse])){
			array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse],$row['srcRecID']);
		}
		if ( !@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']]) {
			$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']] = array($inverse);
		}else if ( !in_array($inverse,$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']])){
			array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']],$inverse);
		}
		if ( $row['relID'] && !in_array($row['relID'],$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['relRecIDs'])) {
			array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['relRecIDs'],$row['relID']);
		}
	}
	return array_keys($nlrIDs);
}

function buildGraphStructure($rec_ids, &$recSet) {
	global $MAX_DEPTH, $REVERSE, $RECTYPE_FILTERS, $RELTYPE_FILTERS, $PTRTYPE_FILTERS, $EXPAND_REV_PTR;
	$depth = 0;
	$rtfilter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth]: null );
	if ($rtfilter){
		$query = 'SELECT rec_ID from Records '.
					'WHERE rec_ID in ('.join(",",$rec_ids).') '.
					'AND rec_RecTypeID in ('.join(",",$rtfilter).')';
//echo "query = $query <br/>\n";
		$filteredIDs = array();
		$res = mysql_query($query);
		while ($res && $row = mysql_fetch_row($res)) {
			$filteredIDs[$row[0]] =1;
		}
		$rec_ids = array_keys($filteredIDs);
	}
//echo "depth = $depth  recID = ". json_format($rec_ids)."\n";
	while ($depth++ < $MAX_DEPTH  &&  count($rec_ids) > 0) {
		$rtfilter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth]: null );
		$relfilter = (array_key_exists($depth, $RELTYPE_FILTERS) ? $RELTYPE_FILTERS[$depth]: null );
		$ptrfilter = (array_key_exists($depth, $PTRTYPE_FILTERS) ? $PTRTYPE_FILTERS[$depth]: null );
//echo "depth = $depth  rtfilter = ". print_r($rtfilter,true)."\n<br/>";
//echo "depth = $depth  ptrfilter = ". print_r($ptrfilter,true)."\n<br/>";
//echo "depth = $depth  relfilter = ". print_r($relfilter,true)."\n<br/>";
		if (!@$recSet['infoByDepth'][$depth]) {
			$recSet['infoByDepth'][$depth] = array('recIDs'=>array(),'rectypes'=>array());
		}
		$p_rec_ids = findPointers($rec_ids,$recSet, $depth, $rtfilter, $ptrfilter);
//echo "depth = $depth  new ptr recID = ". json_format($p_rec_ids)."\n";
		$rp_rec_ids = $REVERSE ? findReversePointers($rec_ids, $recSet, $depth, $rtfilter, $ptrfilter) : array();
//echo "depth = $depth  new revptr recID = ". json_format($rp_rec_ids)."\n";
		$rel_rec_ids = findRelatedRecords($rec_ids, $recSet, $depth, $rtfilter, $relfilter);
//echo "depth = $depth  new rel recID = ". json_format($rel_rec_ids)."\n";
		$rec_ids = array_merge($p_rec_ids, ($EXPAND_REV_PTR?$rp_rec_ids:array()), $rel_rec_ids); // record set for a given level
//echo "depth = $depth  recID = ". json_format($rec_ids)."\n";
	}
}

//----------------------------------------------------------------------------//
//  Output functions
//----------------------------------------------------------------------------//

function getRelationStructure($queryResult) {
	$recSet = array('count'=> 0,
					'relatedSet'=>array(),
					'params'=> array_intersect_key($_REQUEST, array('q'=>1,'w'=>1,'depth'=>1,'f'=>1,'limit'=>1,'offset'=>1,'rev'=>1,'revexpand'=>1,'db'=>1,'stub'=>1,'woot'=>1,'fc'=>1,'slb'=>1,'rtfilters'=>1,'relfilters'=>1,'ptrfilters'=>1)),
					'infoByDepth'=>array(array('recIDs'=>array(),'rectypes'=>array())));
//echo "query reults = ".json_format($result,true);
	$rec_ids = array();
	foreach ($queryResult['records'] as $record) {
		array_push($rec_ids, $record['rec_ID']);
		$recSet['relatedSet'][$record['rec_ID']] = array('depth'=>0,
						'record'=> array(null,null,
									$record['rec_ID'],
									$record['rec_URL'],
									$record['rec_RecTypeID'],
									$record['rec_Title'],
									$record['rec_OwnerUGrpID'],
									$record['rec_NonOwnerVisibility'],
									null,
									null,
									null,null));
		array_push($recSet['infoByDepth'][0]['recIDs'],$record['rec_ID']);
		if (!@$recSet['infoByDepth'][0]['rectypes'][$record['rec_RecTypeID']]) {
			$recSet['infoByDepth'][0]['rectypes'][$record['rec_RecTypeID']] = array($record['rec_ID']);
		} else if ( !in_array($record['rec_ID'],$recSet['infoByDepth'][0]['rectypes'][$record['rec_RecTypeID']])){
			array_push($recSet['infoByDepth'][0]['rectypes'][$record['rec_RecTypeID']],$record['rec_ID']);
		}
	}
//echo "rec IDs = ".json_format($rec_ids,true);
//echo "relationships = ".json_format($relationships,true);

	buildGraphStructure($rec_ids, $recSet);
	$recSet['count'] = count($recSet['relatedSet']);
/*	foreach ($relationships as $recID => $linkInfo) {
		if (!@$recSet['infoByDepth'][$linkInfo['depth']]) {
			$recSet['infoByDepth'][$linkInfo['depth']] = array('recIDs'=>array(),'rectypes'=>array());
		}
		array_push($recSet['infoByDepth'][$linkInfo['depth']]['recIDs'],$recID);
			array_push($recSet['infoByDepth'][$linkInfo['depth']]['rectypes'],$linkInfo['record'][4]);
		}
		if (!array_key_exists('ptrtypes',$recSet['infoByDepth'][$linkInfo['depth']]) &&
				(@$linkInfo['ptrLinks'] || @$linkInfo['revPtrLinks'])) {
				$recSet['infoByDepth'][$linkInfo['depth']]['ptrtypes'] = array();
		}
		if ( @$linkInfo['ptrLinks']){
			foreach ($linkInfo['ptrLinks']['byDtlType'] as $ptrType => $recIDs){
				if ( !in_array($ptrType,$recSet['infoByDepth'][$linkInfo['depth']]['ptrtypes'])){
					array_push($recSet['infoByDepth'][$linkInfo['depth']]['ptrtypes'],$ptrType);
				}
			}
		}
		if ( @$linkInfo['revPtrLinks']){
			foreach ($linkInfo['revPtrLinks']['byInvDtlType'] as $ptrType => $recIDs){
				if ( !in_array($ptrType,$recSet['infoByDepth'][$linkInfo['depth']]['ptrtypes'])){
					array_push($recSet['infoByDepth'][$linkInfo['depth']]['ptrtypes'],$ptrType);
				}
			}
		}
		if (!array_key_exists('reltypes',$recSet['infoByDepth'][$linkInfo['depth']]) &&
				(@$linkInfo['revRelLinks'] || @$linkInfo['relLinks'])) {
				$recSet['infoByDepth'][$linkInfo['depth']]['reltypes'] = array();
		}
		if ( @$linkInfo['relLinks']){
			foreach ($linkInfo['relLinks']['byRelType'] as $relType => $recIDs){
				if ( !in_array($relType,$recSet['infoByDepth'][$linkInfo['depth']]['reltypes']) && intval($relType)>0){
					array_push($recSet['infoByDepth'][$linkInfo['depth']]['reltypes'],$relType);
				}
			}
		}
		if ( @$linkInfo['revRelLinks']){
			foreach ($linkInfo['revRelLinks']['byInvRelType'] as $relType => $recIDs){
				if ( !in_array($relType,$recSet['infoByDepth'][$linkInfo['depth']]['reltypes']) && intval($relType)>0){
					array_push($recSet['infoByDepth'][$linkInfo['depth']]['reltypes'],$relType);
				}
			}
		}
	}
*/
	return $recSet;
}


//----------------------------------------------------------------------------//
//  Output
//----------------------------------------------------------------------------//

//$result = loadSearch($_REQUEST);
//echo "request = ".print_r($_REQUEST,true)."\n";
$qresult = loadSearch($_REQUEST);
//echo "query result = ".print_r($qresult,true)."\n";
echo json_format( getRelationStructure($qresult), true);

?>

