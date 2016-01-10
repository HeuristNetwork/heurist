<?php
    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    * Load the user's display preferences.
    * Display preferences are added as CSS classes to the document body:
    * you should include this file in the BODY, not in the head.
    * If arguments  xxx=yyy  are supplied, set those for future display,
    * and suppress normal output.
    *
    * Setting  xxx=yyy  will add class  xxx-yyy  to the body,
    * but then setting  xxx=xyz  would add  xxx-xyz  INSTEAD.
    *
    * Preferences are currently stored in the $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'], maybe they would eventually be in the DB.
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Stephen White   
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

    define('ISSERVICE',1);
    define("SAVE_URI", "disabled");

    require_once(dirname(__FILE__)."/../connect/applyCredentials.php");

    /* An array of the properties that may be set, and default values. 
       Values may be edited for many of these in admin/profile/editPreferbneces.html
    */
    $prefs = array(
    "help" => "show", // show help texts under fields and in other locations
    "advanced" => "hide", // not sure if this is ever used
    "input-visibility" => "all", // allows hiding of optional fields
    "action-on-save" => "stay", // used locally in editRercord but usage probably deprecated by changed save/save and close behaviour
    "gigitiser-view" => "", // remembers geo window between calls, blank = world
    "double-click-action" => "edit", // double clicking on record in search opens edit, 'edit' is the only value ever used

    "my-records-searches" => "show", // show the My Records section in the navigation menu 
    "all-records-searches" => "show", // show the All records section in the navigation menu
    "workgroup-searches" => "show", // show the workgroup searches section in the navigation menu
    "left-panel-scroll" => 0,

    "record-search-string" => "",
    "record-search-type" => "",
    "record-search-scope" => "r-all",
    "record-search-last" => "", //list of rectypes and last selected records - up to 50

    "search-result-style0" => "list", // default mode of display of results in each level
    "search-result-style1" => "list",
    "search-result-style2" => "list",
    "search-result-style3" => "list",
    "results-per-page" => 50,

    "scratchpad-bottom" => 0, // set by moving and scaling the scratchpad in edit mode
    "scratchpad-right" => 0,
    "scratchpad-width" => 0,
    "scratchpad-height" => 0,
    "scratchpad" => "hide",

    "applicationPanel" => "open",
    "sidebarPanel" => "open",
    "leftWidth" => 180,
    "oldLeftWidth" => 180,
    "rightWidth" => 360,
    "oldRightWidth" => 360,
    "searchWidth" => 360, //never used
    "oldSearchWidth" => 360, //never used
    "viewerTab" =>0,
    "viewerCurrentTemplate" =>"",
    "defaultPrintView" => "default",
    "showSelectedOnlyOnMapAndSmarty" => "all", //by default show all records

    // Properties which can be set in the My profile > Preferences dialogue
    "savedSearchDest" => "",  //last saved search destination (workgroup id)
    "defaultSearch" => "sortby:-m after:\"1 week ago\"", // was "tag:Favourites"
    "searchQueryInBrowser" => "true", // was false, I presume for neatness. But less informative

    "favourites" => "Favourites", // standard spelling for default search
    "loadRelatedOnSearch" => "false", // by default load related records, can be set in Preferences dialogue
    "defaultRecentPointerSearch" => "true", // when searching for pointers to records, show recent records by default
    "findFuzzyMatches" => "false", //check similar records on addition
    "defaultMyBookmarksSearch" => "false", // hitting Enter will do a My Bookmarks search
    "showMyBookmarks" => "true", // turn on/off My Bookmarks heading in the navigation menu
    "autoSelectRelated" => "false", // autoSelect related records
    "autoDeselectOtherLevels" => "true", // auto deselct other level before selecting current.
    "relationship-optional-fields" => "false", //show optional fields for add relationship dialogue
    "tagging-popup" => "false", // popup a tagging dialogue if record saved without any tags -switch on/off in this popup or preferences
    "showAggregations" => "false", // show link Aggregations under My Bookmarks and All records in teh search navigation menu
    "showNavMenuAlways" => "false", // show the navigation menu even when the navigation panel is open
    "showFavouritesSearch" => "false", // ditto for Favourites search
    "mapbackground" => "", // the background to be used for the Google map in the map tab
    "report-output-limit" => "1000",   //report output limit for smarty and map

    "record-edit-date" => "", // TODO: what is this for, it is not set in profile
    "record-edit-advancedmode" => "false", //by default record editing comes up in stripped down 'simple' mode
    "record-add-showaccess" => "true",     //show access right selectors in add record popup
    "record-add-defaults" => ""            //default settings for new record in add record popup    

    // Notes: to add new preferences, add here, add in editPreferences (if required)
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
    header("Content-type: text/javascript");
?>
<!-- TODO: did someone mean to comment this stuff out? ... -->
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
