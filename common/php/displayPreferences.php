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



	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	/* Load the user's display preferences.
	* Display preferences are added as CSS classes to the document body:
	* you should include this file in the BODY, not in the head.
	*
	* If arguments  xxx=yyy  are supplied, set those for future display,
	* and suppress normal output.
	*
	* Setting  xxx=yyy  will add class  xxx-yyy  to the body,
	* but then setting  xxx=xyz  would add  xxx-xyz  INSTEAD.
	*
	* Preferences are currently stored in the $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'], maybe they would eventually be in the DB.
	*/
	define('ISSERVICE',1);
	define("SAVE_URI", "disabled");

	require_once(dirname(__FILE__)."/../connect/applyCredentials.php");

	header("Content-type: text/javascript");


	/* an array of the properties that may be set, and default values. */
	$prefs = array(
		"help" => "show",
		"advanced" => "hide",
		"input-visibility" => "all",
		"action-on-save" => "stay",
		"gigitiser-view" => "",
		"double-click-action" => "edit",

		"my-records-searches" => "show",
		"all-records-searches" => "show",
		"workgroup-searches" => "show",
		"left-panel-scroll" => 0,

		"record-search-string" => "",
		"record-search-type" => "",
		"record-search-scope" => "r-all",
		"record-search-last" => "", //list of rectypes and last selected records - up to 50

		"search-result-style0" => "list",
		"search-result-style1" => "icons",
		"search-result-style2" => "icons",
		"search-result-style3" => "icons",
		"results-per-page" => 50,

		"scratchpad-bottom" => 0,
		"scratchpad-right" => 0,
		"scratchpad-width" => 0,
		"scratchpad-height" => 0,
		"scratchpad" => "hide",

		"addRecordDefaults" => "",
		"applicationPanel" => "open",
		"sidebarPanel" => "open",
		"leftWidth" => 180,
		"oldLeftWidth" => 180,
		"rightWidth" => 360,
		"oldRightWidth" => 360,
		"searchWidth" => 360,//deprecated for misnaming
		"oldSearchWidth" => 360,//deprecated for misnaming
		"viewerTab" =>0,
		"viewerCurrentTemplate" =>"",
		"defaultPrintView" => "default",
		"showSelectedOnlyOnMapAndSmarty" => "all", //by default show all records

		// Properties which can be set in the My profile > Preferences dialogue
		"savedSearchDest" => "",  //last saved search destination (workgroup id)
		"defaultSearch" => "tag:Favourites",//was  "sortby:-m after:\"1 week ago\" ",
		"searchQueryInBrowser" => "false",

		"favourites" => "Favourites", // standard spelling for default search
		"loadRelatedOnSearch" => "true", // by default load related records, can be set in Preferences dialogue
		"defaultRecentPointerSearch" => "true", // when searching for pointers to records, show recent records by default
		"defaultMyBookmarksSearch" => "true", // hitting Enter will do a My Bookmarks search
		"showMyBookmarks" => "true", // turn on/off My Bookmarks heading in the navigation menu
		"autoSelectRelated" => "false", // autoSelect related records
		"autoDeselectOtherLevels" => "true", // auto deselct other level before selecting current.
		"relationship-optional-fields" => "false", //show optional fields for add relationship dialogue
		"tagging-popup" => "true", // popup a tagging dialogue if record saved without any tags -switch on/off in this popup or preferences
		"showAggregations" => "false", // show link Aggregations under My Bookmarks and All records in teh search navigation menu
		"showNavMenuAlways" => "false", // show the navigation menu even when the navigation panel is open
		"showFavouritesSearch" => "false", // ditto for Favourites search
		"mapbackground" => "",
		"report-output-limit" => "1000",   //report output limit for smarty and map

		"record-edit-date" => "",
	);

	foreach (get_group_ids() as $gid) {
		$prefs["workgroup-searches-$gid"] = "hide";
	}

	session_start();

	//save preference  SAW - this supports multiple preference saving, need to consolidate tpreference saves on client side
	$writeMode = false;
	foreach ($_REQUEST as $property => $value) {
		if (array_key_exists($property, $prefs)) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"][$property] = $value;
//			$writeMode = true;
		}
	}

//	if ($writeMode) return;	// suppress normal output

?>
//document.domain = "<?= HEURIST_SERVER_NAME ?>";
if (! document.body) {
// Document manipulation becomes much harder if we can't access the body.
throw document.location.href + ": include displayPreferences.php in the body, not the head";
}
<?php
	if ($prefs) {
		print "top.HEURIST.displayPreferences = {";
		$first = true;
		$classNames = "";
		$replaceClassNames = "";
		foreach ($prefs as $property => $value) {
			if (! $first) print ",";  $first = false;
			print "\n";

			if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"][$property])
				$value = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"][$property];

			print "\t\"".addslashes($property)."\": \"".addslashes($value)."\"";
//SAW TODO: This seems to be unused and deprecated with no comment. Check old code and verify and remove.
			$classNames .= " " . addslashes($property . "-" . $value);
			if ($replaceClassNames) $replaceClassNames .= "|";
			$replaceClassNames .= "\\b" . $property . "-\\S+\\b";
		}
		print "\n};\n";

	} else {
		print "top.HEURIST.displayPreferences = {};\n";
	}

?>
top.HEURIST.fireEvent(window, "heurist-display-preferences-loaded");
