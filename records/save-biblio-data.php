<?php
	/*<!-- save-biblio-data.php

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

require_once(dirname(__FILE__)."/../common/connect/cred.php");
require_once(dirname(__FILE__)."/../common/connect/db.php");
require_once("TitleMask.php");
require_once(dirname(__FILE__)."/relationships/relationships.php");
require_once("disambig/approx-matches.php");
require_once(dirname(__FILE__)."/../search/saved/loading.php");

if (! is_logged_in()) return;

mysql_connection_db_overwrite(DATABASE);
mysql_query('set @logged_in_user_id = ' . get_user_id());


$checkSimilar = array_key_exists("check-similar", $_POST);
if ($checkSimilar) {
	$rec_id = intval(@$_POST["bib_id"]);
	$rec_types = array(intval(@$_POST["reftype"]));

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



if ($_POST["save-mode"] == "edit"  &&  intval($_POST["bib_id"])) {
	$updated = updateRecord(intval($_POST["bib_id"]));
} else if ($_POST["save-mode"] == "new") {
	$updated = insertRecord();
} else {
	$updated = false;
}


if ($updated) {
	// Update bib record data
	// Update rec_details, rec_scratchpad and rec_title in (parent.parent).HEURIST.record
	print "(";
	define("JSON_RESPONSE", 1);
	require_once(dirname(__FILE__)."/pointer/bib.php");
	print ")";
}
/***** END OF OUTPUT *****/

function updateRecord($bibID) {
	// Update the given record.
	// This is non-trivial: so that the versioning stuff (achive_*) works properly
	// we need to separate this into updates, inserts and deletes.

	$bibID = intval($bibID);

	// Check that the user has permissions to edit it.
	$res = mysql_query("select * from records
	                        left join ".USERS_DATABASE.".UserGroups on ug_group_id=rec_wg_id
	                        left join rec_types on rt_id=rec_type
	                     where rec_id=$bibID and (! rec_wg_id or ug_user_id=".get_user_id().")");
	if (mysql_num_rows($res) == 0) {
		$res = mysql_query("select grp_name from records, ".USERS_DATABASE.".Groups where rec_id=$bibID and grp_id=rec_wg_id");
		$grpName = mysql_fetch_row($res);  $grpName = $grpName[0];

		print '({ error: "Sorry - you can\'t edit this record.\nYou aren\'t in the ' . slash($grpName) . ' workgroup" })';
		return;
	}
	$bib = mysql_fetch_assoc($res);

	// Upload any files submitted ... (doesn't have to take place right now, but may as well)
	uploadFiles();

	// Get the existing records details and compare them to the incoming data
	$bibDetails = getBiblioDetails($bibID);
	$bibDetailUpdates = array();
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
			// element was in POST but without content: values have been delete client-side (need to be deleted in DB so leave POST)
			continue;
		}

		$bdType = substr($eltName, 5);	// everything after "type:"
		$bdInputHandler = getInputHandlerForType($bdType);
		foreach ($bibDetails[$eltName] as $eltID => $val) {
			if (! preg_match("/^bd:\\d+$/", $eltID)) continue;

			$val = @$_POST[$eltName][$eltID];
			if (! $bdInputHandler->inputOK($val)) continue;	// faulty input ... ignore

			$bdID = substr($eltID, 3);	// everything after "bd:"
			$bibDetailUpdates[$bdID] = $bdInputHandler->convertPostToMysql($val);
			$bibDetailUpdates[$bdID]["rd_type"] = $bdType;

			unset($_POST[$eltName][$eltID]);	// remove data from post submission
			unset($bibDetails[$eltName][$eltID]);	// remove data from local reflection of the database
		}
	}

	// Anything left in bibDetails now represents rec_details rows that need to be deleted because they were removed before submission

	$bibDetailDeletes = array();

	foreach ($bibDetails as $eltName => $bds) {
		foreach ($bds as $eltID => $val) {
			$bdID = substr($eltID, 3);	// everything after "bd:"
			array_push($bibDetailDeletes, $bdID);
		}
	}

	// Try to insert anything left in POST as new rec_details rows
	$bibDetailInserts = array();

	foreach ($_POST as $eltName => $bds) {
		// if not properly formatted or empty or an empty array then skip it
		if (! preg_match("/^type:\\d+$/", $eltName)  ||  ! $_POST[$eltName]  ||  count($_POST[$eltName]) == 0) continue;

		$bdType = substr($eltName, 5);
		$bdInputHandler = getInputHandlerForType($bdType);
		foreach ($bds as $eltID => $val) {
			if (! $bdInputHandler->inputOK($val)) continue;	// faulty input ... ignore

			$newBibDetail = $bdInputHandler->convertPostToMysql($val);
			$newBibDetail["rd_type"] = $bdType;
			$newBibDetail["rd_rec_id"] = $bibID;

			array_push($bibDetailInserts, $newBibDetail);

			unset($_POST[$eltName][$eltID]);	// remove data from post submission
		}
	}

	// Anything left in POST now is stuff that we have no intention of inserting ... ignore it

	// We now have:
	//  - $bibDetailUpdates: an assoc. array of rd_id => column values to be updated in rec_details
	//  - $bibDetailInserts: an array of column values to be inserted into rec_details
	//  - $bibDetailDeletes: an array of rd_id values corresponding to rows to be deleted from rec_details

	// Commence versioning ...
	mysql_query("start transaction");

	$bibUpdates = array("rec_modified" => array("now()"), "rec_temporary" => 0);
	$bibUpdates["rec_scratchpad"] = $_POST["notes"];
	if (intval(@$_POST["reftype"])) {
		$bibUpdates["rec_type"] = intval($_POST["reftype"]);
	}
	if (array_key_exists("bib_url", $_POST)) {
		$bibUpdates["rec_url"] = $_POST["bib_url"];
	}
	if (is_admin()) {
		if (array_key_exists("bib_workgroup", $_POST)) {
			$bibUpdates["rec_wg_id"] = $_POST["bib_workgroup"];
		}
		if (array_key_exists("bib_visibility", $_POST)) {
			$bibUpdates["rec_visibility"] = $_POST["bib_visibility"];
		}
	}
	mysql__update("records", "rec_id=$bibID", $bibUpdates);
	$biblioUpdated = (mysql_affected_rows() > 0)? true : false;

	$updatedRowCount = 0;
	foreach ($bibDetailUpdates as $bdID => $vals) {
		mysql__update("rec_details", "rd_id=$bdID and rd_rec_id=$bibID", $vals);
		if (mysql_affected_rows() > 0) {
			++$updatedRowCount;
		}
	}

	$insertedRowCount = 0;
	foreach ($bibDetailInserts as $vals) {
		mysql__insert("rec_details", $vals);
		if (mysql_affected_rows() > 0) {
			++$insertedRowCount;
		}
	}

	$deletedRowCount = 0;
	if ($bibDetailDeletes) {
		mysql_query("delete from rec_details where rd_id in (" . join($bibDetailDeletes, ",") . ") and rd_rec_id=$bibID");
		if (mysql_affected_rows() > 0) {
			$deletedRowCount = mysql_affected_rows();
		}
	}

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
		mysql_query("update records
		                set rec_title = '" . addslashes(fill_title_mask($bib["rt_title_mask"], $bib["rec_id"], $bib["rec_type"])) . "'
		              where rec_id = $bibID");

		mysql_query("commit");

		// Update memcached's copy of record (if it is cached)
		updateCachedRecord($bibID);

		return true;
	} else {
		/* nothing changed: rollback the transaction so we don't get false versioning */
		mysql_query("rollback");
		return false;
	}
}


function insertRecord() {
	// Try to insert anything in POST as new rec_details rows.
	// We do this by creating a stub record, and then updating it.
	mysql__insert("records", array(
		"rec_added" => date('Y-m-d H:i:s'),
		"rec_added_by_usr_id" => get_user_id(),
		"rec_type" => $_POST["reftype"],
		"rec_scratchpad" => $_POST["notes"],
		"rec_url" => $_POST["url"]? $_POST["url"] : ""));
	$_REQUEST["rec_id"] = $bibID = mysql_insert_id();
	updateRecord($bibID);

	return true;
}


function getBiblioDetails($bibID) {
	$bibID = intval($bibID);
	$bd = array();

	$res = mysql_query("select * from rec_details where rd_rec_id = " . $bibID);
	while ($val = mysql_fetch_assoc($res)) {
		$elt_name = "type:".$val["rd_type"];

		if (! @$bd[$elt_name]) {
			$bd[$elt_name] = array();
		}
		$bd[$elt_name]["bd:".$val["rd_id"]] = $val;
	}

	return $bd;
}


function uploadFiles() {
	/*
	 * Check if there are any files submitted for uploading;
	 * process them (save them to disk) and commute their element values to the appropriate file_id.
	 */

	if (! $_FILES) return;
	foreach ($_FILES as $eltName => $upload) {
		/* check that $elt_name is a sane element name */
		if (! preg_match('/^type:\\d+$/', $eltName)  ||  ! $_FILES[$eltName]  ||  count($_FILES[$eltName]) == 0) continue;

		/* FIXME: should check that the given element is supposed to be a file */
/*
		$bdr = &get_bdr($matches[1]);
		$bdt = &get_bdt($bdr['rdr_rdt_id']);
		if (! $bdr  ||  ! $bdt  ||  $bdt['rdt_type'] != 'file') continue;
*/

		/* Ooh, this is annoying / odd:
		 * if several file elements have the name "foobar[]"
		 * then $_FILES['foobar'] will not be an array of { name, type, tmp_name, error, size } values;
		 * rather, it is a value of { name array, type array, tmp_name array, ... }
		 */
		if (! $upload["size"]) continue;
		foreach ($upload["size"] as $eltID => $size) {
			if ($size <= 0) continue;

			$fileID = upload_file($upload["name"][$eltID], $upload["type"][$eltID],
			                      $upload["tmp_name"][$eltID], $upload["error"][$eltID], $upload["size"][$eltID]);

			if ($fileID) {
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


function upload_file($name, $type, $tmp_name, $error, $size) {
	/* Check that the uploaded file has a sane name / size / no errors etc,
	 * enter an appropriate record in the files table,
	 * save it to disk,
	 * and return the file_id for that record.
	 * This will be zero if anything went pear-shaped along the way.
	 */
	if ($size <= 0  ||  $error) { error_log("size is $size, error is $error"); return 0; }

	/* clean up the provided file name -- these characters shouldn't make it through anyway */
	$name = str_replace("\0", '', $name);
	$name = str_replace('\\', '/', $name);
	$name = preg_replace('!.*/!', '', $name);

	$mimetype = null;
	$filedescription = null;
	if (preg_match('/\\.([^.]+)$/', $name, $matches)) {
		$extension = $matches[1];
		$res = mysql_query('select * from file_to_mimetype where extension = "'.addslashes($extension).'"');
		if (mysql_num_rows($res) == 1) {
			$mimetype = mysql_fetch_assoc($res);
			$file_description = $mimetype['file_description'];
			$mimetype = $mimetype['mime_type'];
		}
	}

	$path = '';	/* can change this to something more complicated later on, to prevent crowding the upload directory
				 the path MUST start and NOT END with a slash so that  "UPLOAD_PATH . $path . '/' .$file_id" is valid */

	$file_size = '';
	if ($size < 1000) $file_size = $size . ' bytes';
	else if ($size < 1000000) $file_size = (round($size / 102.4)/10) . ' kb';
	else $file_size = (round($size / (1024*102.4))/10) . ' Mb';

	$res = mysql__insert('files', array('file_orig_name' => $name, 'file_path' => $path, 'file_user_id' => get_user_id(),
	                                    'file_date' => date('Y-m-d H:i:s'),
	                                    'file_typedescription' => $file_description, 'file_mimetype' => $mimetype,
	                                    'file_size' => $file_size));
	if (! $res) { error_log("error inserting: " . mysql_error()); return 0; }
	$file_id = mysql_insert_id();
	mysql_query('update files set file_nonce = "' . addslashes(sha1($file_id.'.'.rand())) . '" where file_id = ' . $file_id);
		/* nonce is a random value used to download the file */

	if (move_uploaded_file($tmp_name, UPLOAD_PATH . $path .'/'. $file_id)) {
		return $file_id;
	} else {
		/* something messed up ... make a note of it and move on */
		error_log("upload_file: <$name> / <$tmp_name> couldn't be saved as <" . UPLOAD_PATH . $path . '/' . $file_id . ">");
		mysql_query('delete from files where file_id = ' . $file_id);
		return 0;
	}
}


function getInputHandlerForType($typeID) {
	static $typeToSpecies = null;
	if (! $typeToSpecies) {
		$typeToSpecies = mysql__select_assoc("rec_detail_types", "rdt_id", "rdt_type", "1");
	}

	static $speciesToInput = null;
	if (! $speciesToInput) {
		$speciesToInput = array(
			"freetext" => new BibDetailFreetextInput(),
			"blocktext" => new BibDetailBlocktextInput(),
			"integer" => new BibDetailIntegerInput(),
			"year" => new BibDetailYearInput(),
			"date" => new BibDetailDateInput(),
			"boolean" => new BibDetailBooleanInput(),
			"resource" => new BibDetailResourceInput(),
			"float" => new BibDetailFloatInput(),
			"enum" => new BibDetailDropdownInput(),
			"file" => new BibDetailFileInput(),
			"geo" => new BibDetailGeographicInput(),
			"separator" => new BibDetailSeparator(),
			"default" => new BibDetailInput()
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
		return array("rd_val" => $postVal);
	}
	function inputOK($postVal) {
		// This is abstract
		return false;
	}
}
class BibDetailFreetextInput extends BibDetailInput {
	function convertPostToMysql($postVal) {
		return array("rd_val" => trim($postVal));
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
class BibDetailDateInput extends BibDetailFreetextInput {
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
			return array("rd_val" => "true");
		else if ($postVal)
			return array("rd_val" => "false");
		else
			return array("rd_val" => NULL);
	}
	function inputOK($postVal) {
		return preg_match("/^(?:yes|true|no|false)$/", $postVal);
	}
}
class BibDetailDropdownInput extends BibDetailInput {
	function inputOK($postVal) {
		return preg_match("/\\S/", $postVal);
	}
}
class BibDetailFileInput extends BibDetailInput {
	function convertPostToMysql($postVal) {
		return array("rd_file_id" => $postVal);
	}
	function inputOK($postVal) {
		return preg_match("/\\S/", $postVal);
	}
}
class BibDetailGeographicInput extends BibDetailInput {
	function convertPostToMysql($postVal) {
		if (preg_match("/^(p(?= point)|r(?= polygon)|[cl](?= linestring)|pl(?= polygon)) ((?:point|polygon|linestring)\\(?\\([-0-9.+, ]+?\\)\\)?)$/i", $postVal, $matches)) {
			return array("rd_val" => $matches[1], "rd_geo" => array("geomfromtext(\"" . $matches[2] . "\")"));
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
