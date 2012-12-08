<?php
	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	/*<!-- saveRecordDetails.php
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
	/* Save a bibliographic record (create a new one, or update an existing one) */

	define('SAVE_URI', 'disabled');

	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
	require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
	require_once(dirname(__FILE__)."/../../common/php/utilsTitleMask.php");
	require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");
	require_once(dirname(__FILE__)."/../disambig/findFuzzyRecordMatches.php");
	require_once(dirname(__FILE__)."/../../search/getSearchResults.php");
	require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

	if (! is_logged_in()) return;

	mysql_connection_db_overwrite(DATABASE);
	mysql_query('set @logged_in_user_id = ' . get_user_id());


	$checkSimilar = array_key_exists("check-similar", $_POST);
	if ($checkSimilar) {
		$rec_id = intval(@$_POST["recID"]);
		$rec_types = array(intval(@$_POST["rectype"]));

		$fields = array();
		foreach ($_POST as $key => $val) {
			if (preg_match('/^type:(\d+)/', $key, $matches)) {
				$fields["t:".$matches[1]] = $val;
			}
		}
		$matches = findFuzzyMatches($fields, $rec_types, $rec_id);

		if (count($matches)) {
			print '({ matches: ' . json_format($matches) . ' })';
			return;
		}
	}



	if ($_POST["save-mode"] == "edit"  &&  intval($_POST["recID"])) {
		$updated = updateRecord(intval($_POST["recID"]));
	} else if ($_POST["save-mode"] == "new") {
		$updated = insertRecord();
	} else {
		$updated = false;
	}

/*****DEBUG****///error_log(" Save dtl Request  ".print_r($_REQUEST,true));
/*****DEBUG****///error_log(" Save dtl Post  ".print_r($_POST,true));

	if ($updated) {

		updateRecTypeUsageCount(); //getRecordInfoLibrary

		// Update bib record data
		// Update recDetails, rec_ScratchPad and rec_Title in (parent.parent).HEURIST.record
		print "(";
		define("JSON_RESPONSE", 1);
		require_once(dirname(__FILE__)."/../../common/php/loadRecordData.php");
		print ")";
	}
	/***** END OF OUTPUT *****/

	/**
	* Main method that parses POST and update details for given record ID
	*
	* @param int $recID
	*/
	function updateRecord($recID) {
		// Update the given record.
		// This is non-trivial: so that the versioning stuff (achive_*) works properly
		// we need to separate this into updates, inserts and deletes.

		$recID = intval($recID);

		// Check that the user has permissions to edit it.
		$res = mysql_query("select * from Records
			left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID=rec_OwnerUGrpID
			left join defRecTypes on rty_ID=rec_RecTypeID
			where rec_ID=$recID and (! rec_OwnerUGrpID or rec_OwnerUGrpID=".get_user_id()." or ugl_UserID=".get_user_id().")");
		if (mysql_num_rows($res) == 0) {
			$res = mysql_query("select grp.ugr_Name from Records, ".USERS_DATABASE.".sysUGrps grp where rec_ID=$recID and grp.ugr_ID=rec_OwnerUGrpID");
			$grpName = mysql_fetch_row($res);  $grpName = $grpName[0];

			print '({ error: "\nSorry - you can\'t edit this record.\nYou aren\'t in the ' . slash($grpName) . ' workgroup" })';
			return;
		}
		$bib = mysql_fetch_assoc($res);
/*****DEBUG****///error_log("save record dtls POST ".print_r($_POST,true));
		// Upload any files submitted ... (doesn't have to take place right now, but may as well)
		uploadFiles();  //Artem: it does not work here - since we uploaded files at once

		// Get the existing records details and compare them to the incoming data
		$bibDetails = getBiblioDetails($recID);
		$bibDetailUpdates = array();
/*****DEBUG****///error_log("save record dtls ".print_r($bibDetails,true));
		foreach ($bibDetails as $eltName => $bds) {
			if (! preg_match("/^type:\\d+$/", $eltName)) {
				// element does not have a correctly-formatted name (shouldn't happen)
				unset($bibDetails[$eltName]);
				continue;
			}
			if (! (@$_POST[$eltName]  &&  is_array($_POST[$eltName]))) {
				// element wasn't in POST: ignore it
				unset($bibDetails[$eltName]);
				continue;
			}
			if (count($_POST[$eltName]) == 0) {
				// element was in POST but without content: values have been deleted client-side (need to be deleted in DB so leave POST)
				continue;
			}

			$bdType = substr($eltName, 5);	// everything after "type:"

			$bdInputHandler = getInputHandlerForType($bdType); //returns the particular handler (processor) for given field type
			foreach ($bibDetails[$eltName] as $eltID => $val) {
/*****DEBUG****///error_log(" in saveRecord details loop  $eltName,  $eltID, ".print_r($val,true));
				if (! preg_match("/^bd:\\d+$/", $eltID)) continue;

				$val = @$_POST[$eltName][$eltID];
				if (! $bdInputHandler->inputOK($val)) {
/*****DEBUG****///error_log(" in saveRecord details value check error  $eltName,  $eltID, ".print_r($val,true));
					continue;	// faulty input ... ignore
				}

				$bdID = substr($eltID, 3);	// everything after "bd:"
				$toadd = $bdInputHandler->convertPostToMysql($val);
				if ($toadd==null) continue;

				$bibDetailUpdates[$bdID] = $toadd;
				$bibDetailUpdates[$bdID]["dtl_DetailTypeID"] = $bdType;

/*
 @TODO Since this function is utilized in (email)import we need to add verification of values according to detail type
 at the first for terms (enumeration field type)
*/

				unset($_POST[$eltName][$eltID]);	// remove data from post submission
				if (count($_POST[$eltName]) == 0){
					unset($_POST[$eltName]);
				}
				unset($bibDetails[$eltName][$eltID]);	// remove data from local reflection of the database
			}
		}
/*****DEBUG****///error_log("save record dtls POST after updates removed ".print_r($_POST,true));
/*****DEBUG****///error_log("save record dtls after updates removed ".print_r($bibDetails,true));

		// Anything left in bibDetails now represents recDetails rows that need to be deleted because they were removed before submission

		$bibDetailDeletes = array();

		foreach ($bibDetails as $eltName => $bds) {
			foreach ($bds as $eltID => $val) {
				$bdID = substr($eltID, 3);	// everything after "bd:"
				array_push($bibDetailDeletes, $bdID);
			}
		}

		// Try to insert anything left in POST as new recDetails rows
		$bibDetailInserts = array();

/*****DEBUG****/// error_log(" in saveRecord checking for inserts  _POST =".print_r($_POST,true));
		foreach ($_POST as $eltName => $bds) {
			// if not properly formatted or empty or an empty array then skip it
			if (! preg_match("/^type:\\d+$/", $eltName)  ||  ! $_POST[$eltName]  ||  count($_POST[$eltName]) == 0) continue;

			$bdType = substr($eltName, 5);
			$bdInputHandler = getInputHandlerForType($bdType);
			foreach ($bds as $eltID => $val) {
				if (! $bdInputHandler->inputOK($val)) continue;	// faulty input ... ignore

				$newBibDetail = $bdInputHandler->convertPostToMysql($val);
				$newBibDetail["dtl_DetailTypeID"] = $bdType;
				$newBibDetail["dtl_RecID"] = $recID;
/*****DEBUG****///error_log("new detail ".print_r($newBibDetail,true));
				array_push($bibDetailInserts, $newBibDetail);

				unset($_POST[$eltName][$eltID]);	// remove data from post submission
			}
		}

		// Anything left in POST now is stuff that we have no intention of inserting ... ignore it

		// We now have:
		//  - $bibDetailUpdates: an assoc. array of dtl_ID => column values to be updated in recDetails
		//  - $bibDetailInserts: an array of column values to be inserted into recDetails
		//  - $bibDetailDeletes: an array of dtl_ID values corresponding to rows to be deleted from recDetails

		// Commence versioning ...
		mysql_query("start transaction");

		$bibUpdates = array("rec_Modified" => array("now()"), "rec_FlagTemporary" => 0);
		$bibUpdates["rec_ScratchPad"] = $_POST["notes"];
		if (intval(@$_POST["rectype"])) {
			$bibUpdates["rec_RecTypeID"] = intval($_POST["rectype"]);
		}
		if (array_key_exists("rec_url", $_POST)) {
			$bibUpdates["rec_URL"] = $_POST["rec_url"];
		}
		$owner = $bib['rec_OwnerUGrpID'];
		if (is_admin() || is_Admin('group',$owner) || $owner == get_user_id()) {// must be grpAdmin or record owner to changes ownership or visibility
			if (array_key_exists("rec_owner", $_POST)) {
				$bibUpdates["rec_OwnerUGrpID"] = $_POST["rec_owner"];
			}
			if (array_key_exists("rec_visibility", $_POST)) {
				$bibUpdates["rec_NonOwnerVisibility"] = $_POST["rec_visibility"];
			}else if ($bib['rec_NonOwnerVisibility'] == 'public' && HEURIST_PUBLIC_TO_PENDING){
				$bibUpdates["rec_NonOwnerVisibility"] = 'pending';
			}
		}
/*****DEBUG****///error_log(" in saveRecord update recUpdates = ".print_r($bibUpdates,true));
		mysql__update("Records", "rec_ID=$recID", $bibUpdates);
		$biblioUpdated = (mysql_affected_rows() > 0)? true : false;
		if (mysql_error()) error_log("error rec update".mysql_error());
		$updatedRowCount = 0;
		foreach ($bibDetailUpdates as $bdID => $vals) {

/*****DEBUG****///error_log(" in saveRecord update details dtl_ID = $bdID value =".print_r($vals,true));

			mysql__update("recDetails", "dtl_ID=$bdID and dtl_RecID=$recID", $vals);
			if (mysql_affected_rows() > 0) {
				++$updatedRowCount;
			}
		}
		if (mysql_error()) error_log("error detail updates".mysql_error());

		$insertedRowCount = 0;
		foreach ($bibDetailInserts as $vals) {
/*****DEBUG****///error_log(" in saveRecord insert details detail =".print_r($vals,true));
			mysql__insert("recDetails", $vals);
			if (mysql_affected_rows() > 0) {
				++$insertedRowCount;
			}
		}
		if (mysql_error()) error_log("error detail inserts".mysql_error());

		$deletedRowCount = 0;
		if ($bibDetailDeletes) {
/*****DEBUG****///error_log(" in saveRecord delete details ".print_r($bibDetailDeletes,true));
			mysql_query("delete from recDetails where dtl_ID in (" . join($bibDetailDeletes, ",") . ") and dtl_RecID=$recID");
			if (mysql_affected_rows() > 0) {
				$deletedRowCount = mysql_affected_rows();
			}
		}
		if (mysql_error()) error_log("error detail deletes".mysql_error());

		// eliminate any duplicated lines
		$notesIn = explode("\n", str_replace("\r", "", $_POST["notes"]));
		$notesOut = "";
		$notesMap = array();
		for ($i=0; $i < count($notesIn); ++$i) {
			if (! @$notesMap[$notesIn[$i]]  ||  ! $notesIn[$i]) {	// preserve blank lines
				$notesOut .= $notesIn[$i] . "\n";
				$notesMap[$notesIn[$i]] = true;
			}
		}
		$_POST["notes"] = preg_replace("/\n\n+/", "\n", $notesOut);

		if ($updatedRowCount > 0  ||  $insertedRowCount > 0  ||  $deletedRowCount > 0  ||  $biblioUpdated) {
			/* something changed: update the records title and commit all changes */
			mysql_query("update Records
				set rec_Title = '" . addslashes(fill_title_mask($bib["rty_TitleMask"], $bib["rec_ID"], $bib["rec_RecTypeID"])) . "'
				where rec_ID = $recID");

			mysql_query("commit");

			// Update memcached's copy of record (if it is cached)
			updateCachedRecord($recID);

			return true;
		} else {
			/* nothing changed: rollback the transaction so we don't get false versioning */
			mysql_query("rollback");
			return false;
		}
	}


	function insertRecord() {
		//set owner to passed value else to NEWREC default if defined else to user
		$owner = @$_POST["owner"]?$_POST["owner"]:( defined("HEURIST_NEWREC_OWNER_ID") ? HEURIST_NEWREC_OWNER_ID : get_user_id());
		$owner = ((@$_POST["owner"] || @$_POST["owner"] === '0') ? intval($_POST["owner"]) :(defined('HEURIST_NEWREC_OWNER_ID') ? HEURIST_NEWREC_OWNER_ID : get_user_id()));
		// if non zero (everybody group, test if user is member, if not then set owner to user
		if (intval($owner) != 0 && !in_array($owner,get_group_ids())) {
			$owner = get_user_id();
		}
		// Try to insert anything in POST as new recDetails rows.
		// We do this by creating a stub record, and then updating it.
		mysql__insert("Records", array(
				"rec_Added" => date('Y-m-d H:i:s'),
				"rec_AddedByUGrpID" => get_user_id(),
				"rec_RecTypeID" => intval(@$_POST["rectype"])? intval($_POST["rectype"]):RT_NOTE,
				"rec_ScratchPad" => @$_POST["notes"] ? $_POST["notes"]:null,
				"rec_OwnerUGrpID" => $owner,
				"rec_NonOwnerVisibility" => @$_POST["visibility"]?$_POST["visibility"]:(HEURIST_NEWREC_ACCESS ? HEURIST_NEWREC_ACCESS:'viewable'),
				"rec_URL" => @$_POST["rec_url"]? $_POST["rec_url"] : ""));

		$_REQUEST["recID"] = $recID = mysql_insert_id();
		if($recID){
			updateRecord($recID);
			return true;
		}else{
			return false;
		}
	}


	function getBiblioDetails($recID) {
		$recID = intval($recID);
		$bd = array();

		$res = mysql_query("select * from recDetails where dtl_RecID = " . $recID);
		while ($val = mysql_fetch_assoc($res)) {
			$elt_name = "type:".$val["dtl_DetailTypeID"];

			if (! @$bd[$elt_name]) {
				$bd[$elt_name] = array();
			}
			$bd[$elt_name]["bd:".$val["dtl_ID"]] = $val;
		}

		return $bd;
	}


	function uploadFiles() {
		/*
		* Check if there are any files submitted for uploading;
		* process them (save them to disk) and commute their element values to the appropriate ulf_ID.
		*/

		if (! $_FILES) return; // this is likely deprecated since each file gets upload one at a time.
		foreach ($_FILES as $eltName => $upload) {
			/* check that $elt_name is a sane element name */
			if (! preg_match('/^type:\\d+$/', $eltName)  ||  ! $_FILES[$eltName]  ||  count($_FILES[$eltName]) == 0) continue;

			/* FIXME: should check that the given element is supposed to be a file */
			/*
			$bdr = &get_bdr($matches[1]);
			$bdt = &get_bdt($bdr['rst_DetailTypeID']);
			if (! $bdr  ||  ! $bdt  ||  $bdt['dty_Type'] != 'file') continue;
			*/

			/* Ooh, this is annoying / odd:
			* if several file elements have the name "foobar[]"
			* then $_FILES['foobar'] will not be an array of { name, type, tmp_name, error, size } values;
			* rather, it is a value of { name array, type array, tmp_name array, ... }
			*/
			if (! $upload["size"]) continue;
			foreach ($upload["size"] as $eltID => $size) {
				if ($size <= 0) continue;

				$fileID = upload_file($upload["name"][$eltID], null, //$upload["type"][$eltID],
					$upload["tmp_name"][$eltID], $upload["error"][$eltID], $upload["size"][$eltID], null, false);

				if (is_numeric($fileID)) {
					/* We got ourselves an uploaded file.
					* Put an appropriate entry in the $_POST array:
					*  - if a bdID was specified, preserve that bdID slot in the $_POST (for UPDATE)
					*  - otherwise, add it as just another "new" input in $_POST (for INSERT)
					*/
					if (! $_POST[$eltName]) $_POST[$eltName] = array();

					if (preg_match("/^bd:\\d+$/", $eltID)) {
						$_POST[$eltName][$eltID] = $fileID;
					} else {
						array_push($_POST[$eltName], $fileID);
					}
				}
			}
		}
	}

	/**
	* Creates static array of classes for each particular detail type
	* Returns the class for specified detail type
	*
	* @param mixed $typeID - detail type name
	* @return mixed - class to parse the particular detail type in POST
	*/
	function getInputHandlerForType($typeID) {
		static $typeToSpecies = null;
		if (! $typeToSpecies) {
			$typeToSpecies = mysql__select_assoc("defDetailTypes", "dty_ID", "dty_Type", "1");
		}

		static $speciesToInput = null;
		if (! $speciesToInput) {
			$speciesToInput = array(
				"freetext" => new BibDetailFreetextInput(),
				"blocktext" => new BibDetailBlocktextInput(),
				"date" => new BibDetailTemporalInput(),
				"resource" => new BibDetailResourceInput(),
				"float" => new BibDetailFloatInput(),
				"enum" => new BibDetailDropdownInput(),
				"file" => new BibDetailFileInput(),
				"geo" => new BibDetailGeographicInput(),
				"separator" => new BibDetailSeparator(),
				"default" => new BibDetailInput(),
				"relationtype" => new BibDetailDropdownInput(),
				// Note: The following types can no logner be defined, but are incldued here for backward compatibility
				"boolean" => new BibDetailBooleanInput(),
				"integer" => new BibDetailIntegerInput(),
				"year" => new BibDetailYearInput(),
				"urlinclude" => new BibDetailUrlIncludeInput() //artem to remove
			);
		}

		if (array_key_exists($typeID, $typeToSpecies)  &&  array_key_exists($typeToSpecies[$typeID], $speciesToInput)) {
			return $speciesToInput[$typeToSpecies[$typeID]];
		} else {
			return $speciesToInput["default"];
		}
	}


	class BibDetailSeparator {
		function convertPostToMysql($postVal) {
			// Given a value corresponding to a single input from a POST submission,
			// return an empty array of values
			return array();
		}
		function inputOK($postVal) {
			// Separator has no input to return true
			return true;
		}
	}
	class BibDetailInput {
		function convertPostToMysql($postVal) {
			// Given a value corresponding to a single input from a POST submission,
			// return array of values split into their respective MySQL columns
			return array("dtl_Value" => $postVal);
		}
		function inputOK($postVal) {
			// This is abstract
			return false;
		}
	}
	class BibDetailFreetextInput extends BibDetailInput {
		function convertPostToMysql($postVal) {
			return array("dtl_Value" => trim($postVal));
		}
		function inputOK($postVal) {
			return (strlen(trim($postVal)) > 0);
		}
	}
	class BibDetailIntegerInput extends BibDetailFreetextInput {
		function inputOK($postVal) {
			return preg_match("/^\\s*-?\\d+\\s*$/", $postVal);
		}
	}
	class BibDetailFloatInput extends BibDetailFreetextInput {
		function inputOK($postVal) {
			return preg_match("/^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+)?)\\s*$/", $postVal);
		}
	}
	class BibDetailYearInput extends BibDetailFreetextInput {
		function inputOK($postVal) {
			return preg_match("/^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$/i", $postVal);
		}
	}
	class BibDetailTemporalInput extends BibDetailFreetextInput {
		function inputOK($postVal) {
			return preg_match("/\\S/", $postVal);
		}
	}
	class BibDetailBlocktextInput extends BibDetailInput {
		function inputOK($postVal) {
			return preg_match("/\\S/", $postVal);
		}
	}
	class BibDetailResourceInput extends BibDetailInput {
		function inputOK($postVal) {
			return preg_match("/^\\d+$/", $postVal)  &&  $postVal != "0";
		}
	}
	class BibDetailBooleanInput extends BibDetailInput {
		function convertPostToMysql($postVal) {
			if ($postVal == "yes"  ||  $postVal == "true")
				return array("dtl_Value" => "true");
			else if ($postVal)
				return array("dtl_Value" => "false");
				else
					return array("dtl_Value" => NULL);
		}
		function inputOK($postVal) {
			return preg_match("/^(?:yes|true|no|false)$/", $postVal);
		}
	}
	class BibDetailDropdownInput extends BibDetailInput {
		function inputOK($postVal) {
			return preg_match("/\\S/", $postVal);	// has non space characters
		}
	}
	class BibDetailFileInput extends BibDetailInput {
		function convertPostToMysql($postVal) {
			//artem
			if(is_numeric($postVal)){  //this is old way - ulf_ID
				return array("dtl_UploadedFileID" => $postVal);

			}else{  // new way - $postVal - json string with file data array - structure similar get_uploaded_file_info

				$ulf_ID = register_external($postVal); //in uploadFile.php
				return ($ulf_ID==null)?null:array("dtl_UploadedFileID" => $ulf_ID);
			}
		}
		function inputOK($postVal) {
			/*****DEBUG****///error_log("FILE:>>>>>>>>>>>".$postVal);
			return (is_numeric($postVal) || preg_match("/\\S/", $postVal));
		}
	}
	class BibDetailUrlIncludeInput extends BibDetailInput {
		function convertPostToMysql($postVal) {
			return array("dtl_Value" => $postVal, "dtl_ValShortened" => "KUKU");
		}
		function inputOK($postVal) {
			return preg_match("/\\S/", $postVal);	// has non space characters
		}
	}
	class BibDetailGeographicInput extends BibDetailInput {
		function convertPostToMysql($postVal) {
			if (preg_match("/^(p(?= point)|r(?= polygon)|[cl](?= linestring)|pl(?= polygon)) ((?:point|polygon|linestring)\\(?\\([-0-9.+, ]+?\\)\\)?)$/i", $postVal, $matches)) {
				return array("dtl_Value" => $matches[1], "dtl_Geo" => array("geomfromtext(\"" . $matches[2] . "\")"));
			} else
				return array();
		}
		function inputOK($postVal) {
			if (! preg_match("/^(p point|r polygon|[cl] linestring)\\(?\\(([-0-9.+, ]+?)\\)\\)?$/i", $postVal, $matches)) {
				if (! preg_match("/^(pl polygon)\\(\\(([-0-9.+, ]+?)\\)(?:,\\([-0-9.+, ]+?\\))?\\)$/i", $postVal, $matches)) {
					return false;	// illegal value
				}
			}

			$type = strtolower($matches[1]);
			$pointString = $matches[2];

			$FLOAT = '-?[0-9]+(?:\\.[0-9]*)?';
			return preg_match("/^$FLOAT $FLOAT(?:,$FLOAT $FLOAT)*$/", $pointString);
		}
	}

?>
