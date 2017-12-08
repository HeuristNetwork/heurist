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
* importerFramework.php
A process presenting a half-dozen or so pages to the user.
Temporary objects describing the entries are stored in the session data and meddled with;
for now, all the data is left there so that we can pick at it and debug it;
it would be possible, though probably of low value, to provide a page wherein the user can
look through their session data at old imports.
import-clear.php goes through any old import data and removes it from the session.

Rather than sticking all our data in the global session scope,
each import is allocated an import_id based on the name of the file being imported,
the user's ID, and the current time.
All data relating to this import is stored in an array stored in $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['heurist-import-' . <import_id>]
including - and this is important - the current stage of the import (mode).  By moving this stuff into the
session rather than keeping it in the request as is usual, the potential for user meddling is somewhat decreased.
It would probably be possible (using JavaScript and appropriate HTTP redirects) to minimise the impact on the
user's browser history to two pages -- the page wherein they specify the file, and the page telling them they're done,
either of which should be refresh-happy (no POST data).
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


/* Tuning parameters to determine how similar two bibliographic records are.
* HASH_FUZZINESS controls how many differences there can be between the records' hashes,
* as a fraction of the length.
* HASH_PREFIX_LENGTH controls how many characters from the beginning of the records' hashes need to be identical.
*/
define("HASH_FUZZINESS", 0.1);		// 0.1 is 10% difference
define("HASH_PREFIX_LENGTH", 0);	// 5 is a good value 0 ignores prefix

// Make sure these are loaded before the session data is loaded, so that the class definitions are in place
require_once(dirname(__FILE__)."/importerBaseClass.php");
require_once(dirname(__FILE__)."/biblio/importRefer.php");
require_once(dirname(__FILE__)."/biblio/importEndnoteRefer.php");
require_once(dirname(__FILE__)."/biblio/importZotero.php");
require_once(dirname(__FILE__)."/xml/importKML.php");

require_once(dirname(__FILE__)."/algorithms/parseAuthorNames.php");

require_once(dirname(__FILE__)."/../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/algorithms/calcLevenshteinDelta.php");

require_once(dirname(__FILE__)."/../common/php/saveRecord.php");

require_once(dirname(__FILE__)."/../common/php/utilsTitleMask.php");

global $rectype_to_bdt_id_map;
$rectype_to_bdt_id_map = array(
    /* the appropriate bib_detail_type for a resource pointer constrained to type X */

    5 => 227,  /* Book Reference */
    7 => 238,  /* Conference Proceedings Reference */
    28 => 225,  /* Journal Volume Reference */
    29 => 226,  /* Journal Reference */
    30 => 229,  /* Publisher Reference */
    44 => 228,  /* Publication Series Reference */
    49 => 217,  /* ConferenceRef */
    53 => 244,  /* Organisation Reference */
    54 => 250,  /* Research Group Reference */
    55 => 251,  /* Partner Reference */
    55 => 249,  /* Person Reference */
    60 => 254,  /* Funding Source Reference */
    61 => 253,  /* Grant Reference */
    63 => 264,  /* Research Projects Reference */
    66 => 237,  /* Newspaper Volume Reference */
    67 => 236,  /* Magazine Volume Reference */
    68 => 241,  /* Magazine Reference */
    69 => 242,  /* Newspaper Reference */
    70 => 263   /* Course Unit Reference */
);


mysql_connection_overwrite(DATABASE);
mysql_query('set @logged_in_user_id = ' . get_user_id());

jump_sessions();
setup_session_vars();
if ($import_id) { 
    $session_data = &$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["heurist-import-" . $_REQUEST["import_id"]]; 
}
choose_next_mode();

print_common_header(($session_data["mode"] != "file parsing")? @$session_data["in_filename"] : NULL);	/* note that in_filename may be undefined */

switch (@$session_data['mode']) {
    case 'file selection':
        mode_file_selection(); break;
    case 'file parsing':
        mode_file_parsing(); break;

    case 'zotero request parsing':
        mode_zotero_request_parsing(); break;

    case 'print rectype selection':
        mode_print_rectype_selection(); break;
    case 'apply rectype heuristic':
        mode_apply_rectype_heuristic(); break;
    case 'crosswalking':
        mode_crosswalking(); break;
    case 'entry insertion':
        mode_entry_insertion(); break;

    default:
        mode_file_selection();
}

print_common_footer();

/***** END OF OUTPUT *****/



function print_common_header($fileName) {
    global $session_data;
    ?>
    <html>
        <head>
            <title>Import Records <?=(@$_REQUEST['format']=="GEO"?"from KML":@$_REQUEST['format']=="BIB"?"from Bibliography":"") ?></title>

            <meta http-equiv="content-type" content="text/html; charset=utf-8">

            <link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
            <link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">

            <link rel=stylesheet href='<?=HEURIST_BASE_URL?>common/css/global.css'>

            <script src=displayImportProgress.js></script>
        </head>
        <body class="popup" width="640" height="480">
            <script src='<?=HEURIST_BASE_URL?>common/js/utilsLoad.js'></script>
            <script>
                if (! top.HEURIST.user) top.HEURIST.loadScript('<?=HEURIST_BASE_URL?>common/php/loadUserInfo.php', true);
            </script>
            <script src='<?=HEURIST_BASE_URL?>records/tags/autocompleteTags.js'></script>

            <?php	if (defined('use_alt_db')) {	?>
                <div style="color: red; padding: 10px; font-weight: bold;">Warning: using alternative database</div>
                <?php	}	 if ( (@$session_data['zoteroImport']  ||  @$_REQUEST['zoteroEntryPoint']) ) { ?>
                <h2>Synchronising Zotero records with Heurist</h2
                <?php } ?>

            <div id="progress_indicator"><div id="progress_indicator_bar"></div><div id="progress"></div></div>
            <div id="progress_indicator_title"></div>

            <form action="importerFramework.php" method="post" enctype="multipart/form-data" name="import_form">
                <input type=hidden name=current-mode value="<?= htmlspecialchars($session_data["mode"]) ?>">
                <input type=hidden name=db value="<?=HEURIST_DBNAME?>">

                <?php	if ($fileName) {	?>
                    <div style="margin-top: 1em; margin-bottom: 1.5em;">
                        <span style="position: absolute;">Source:</span>
                        <span style="position: relative; left: 5px; margin-left: 5em;">
                            <b><?= htmlspecialchars($fileName) ?></b>
                            <?php if (@$session_data['parser']) { ?>
                                [<?= str_replace('parser', 'format', $session_data['parser']->parserDescription()) ?>]
                                <?php } ?>
                        </span>
                    </div>
                    <?php	}
            }

            function print_common_footer() {
                global $import_id;
                ?>
                <?php	if ($import_id) {	?>
                    <input type=hidden name=import_id value="<?= htmlspecialchars($import_id) ?>">
                    <?php	}	?>
            </form>
        </body>
    </html>
    <?php
}



function clear_session() {
    if(is_array(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'])){
        foreach ($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'] as $name => $val) {
            if (strpos($name, 'heurist-import-') === 0)
                unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$name]);
        }
    }
}








function mode_file_selection() {
    global $session_data;

    if (@$_REQUEST['format']) {
    $frm = $_REQUEST['format'];
    
    if ($frm == "GEO"){
?>
    <h2>IMPORT KML</h2>

    <p style="font-weight:bold;font-size:larger;padding:10 0">This function is designed for the importing of KML into map enabled Heurist records</p>      
    
    <p>Note: you may use KML (or KML snippet) on map directly. Use KML Map source record type for such aim. Although to manipulate data stored along with coordinates use this function</p>
    
    <p><a href='<?=HEURIST_BASE_URL?>context_help/kml_import.html' onClick="top.HEURIST.util.popupURL(window, href); return false;">More info</a></p>
    
<?php  
    }else{
        
    ?>
    
    <h2>File selection</h2>

    <div class="explanation">
        Use this webpage to import your records from another program into Heurist.<br>
        <?php
            if ($frm == "BIB") {
                ?>
                Currently, support is limited to EndNote REFER and Zotero formats.<br>
                <a href="<?=HEURIST_BASE_URL?>import/interface/listRequiredElements.php" target=_new>Show tag definitions</a> for supported REFER record formats.<br>
                <?php
            }
        ?>
        For additional formats email <a  href="mailto:<?=HEURIST_MAIL_TO_INFO?>"><?=HEURIST_MAIL_TO_INFO?></a>.
    </div>
    <?php
    }
    }
    print '<br>';
    if (@$session_data['error']) {
        print '   <div class="error">' . $session_data['error'] . "</div><br>\n";
    }
    ?>
    <div class="file_selection">
        Select a file:
        <input type="file" size="50" name="import_file" onchange="{document.forms[0].submit()}">
    </div>
    <br clear=all>

    <div class="separator_row" style="margin:10px 0"></div>

    <?php
/*    
    <div class="actionButtons">
        <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 5px;">
        <input type="submit" value="Continue" style="font-weight: bold;">
    </div>
*/    
    
}


function postmode_file_selection() {
    global $session_data;

    // there are two ways into the file selection mode;
    // either the user has just arrived at the import page,
    // or they've selected a file *and might progress to file-parsing mode*
    $error = '';
    if (@$_FILES['import_file']) {
        if ($_FILES['import_file']['size'] == 0) {
            $error = 'no file was uploaded';
        } else {
            switch ($_FILES['import_file']['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = "The uploaded file was too large.  Please consider importing it in several stages.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = "Failed to write file to disk";
                    break;
                default:
                    $error = "Unknown file error";
            }
        }

        if (! $error) {	// move on to the next stage!
            initialise_import_session();

            $session_data['infile'] = new HeuristInputFile();
            $error = $session_data['infile']->open($_FILES['import_file']['tmp_name']);
            if (! $error) {	// Fairly obscure error by this stage, but it MIGHT happen I guess.
                $session_data['mode'] = 'file parsing';
                return;
            }
        }
    }

    $session_data['error'] = $error;
}


function mode_file_parsing() {
    global $session_data;

    ?>
    <?php
    // determine file type
    $found_parser = FALSE;
    $parsers = getHeuristFiletypeParsers(); //was &
    foreach (array_keys($parsers) as $i) {
        if ($parsers[$i]->recogniseFile($session_data['infile'])) {
            $session_data['parser'] = &$parsers[$i];
            $found_parser = TRUE;
            break;
        }
    }
    if (! $found_parser) {
        $fp = $session_data['infile']->getRawFile();
        rewind($fp);
        $snippet = htmlspecialchars(fread($fp, 200));
        $snippet = str_replace("\r", "", $snippet);
        $snippet = preg_replace('/([^\040-\176\n]+)/es',"'<span>'.str_repeat('.',strlen('\\1')).'</span>'",$snippet);
        ?>
        <div class="error">
            <div>File read: <?= htmlspecialchars($session_data['in_filename']) ?></div>
            <div>Sorry, this file is not in a currently supported format (REFER, EndNote REFER, KML)</div>
            <div>Start of file looks like:
                <div class="file_snippet"><pre><?= $snippet ?></pre></div>
            </div>
            <div>Your file has not been imported.</div>
        </div>
        </div>

        <?php	if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');">
            <?php	} else { ?>
            <input type="button" value="Close" onClick="window.close();">
            <?php	} ?>
        <?php
        $session_data['mode'] = 'error';
        return;
    }


    ?>
    <div style="margin-top: 1em; margin-bottom: 1.5em;">
        <span style="position: absolute;">Source:</span>
        <span style="position: relative; left: 5px; margin-left: 5em;">
            <b><?= htmlspecialchars($session_data['in_filename']) ?></b>
            <?php if (@$session_data['parser']) { ?>
                [<?= str_replace('parser', 'format', $session_data['parser']->parserDescription()) ?>]
                <?php } ?>
        </span>
    </div>
    <?php

    flush_fo_shizzle();

    list($errors, $entries) = $session_data['parser']->parseFile($session_data['infile']);
    if ($errors) {
        ?>
        <div class="error">
            <div>
                <div>There <?= (count($errors) > 1)? 'were errors' : 'was an unrecoverable error' ?> encountered while parsing the input file:</div>
                <ul>
                    <?php foreach ($errors as $error) { print '<li>' . $error . '</li>'; } ?>
                </ul>
                <div>Your file has not been imported.</div>
            </div>
        </div>

        <?php	if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');">
            <?php	} else { ?>
            <input type="button" value="Close" onClick="window.close();">
            <?php	} ?>
        <?php
        $session_data['mode'] = 'error';
        return;
    }

    if (count($entries) == 0) {
        ?>
        <div class="error">
            <div>No entries were found in the file.</div>
        </div>

        <?php	if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');">
            <?php	} else { ?>
            <input type="button" value="Close" onClick="window.close();">
            <?php	} ?>
        <?php
        $session_data['mode'] = 'error';
        return;
    }

    $session_data['in_entries'] = &$entries;
    do_entry_parsing();
    ?>
    <br clear=all>
<!--     <hr>  -->
    <br clear=all>
    <div style="max-width:250;text-align:right">
    <input type="submit" value="Continue" style="font-weight: bold;">
    <?php	if (! @$session_data['zoteroImport']) { ?>
        <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" style="margin-right: 4ex;">
    <?php	} else { ?>
        <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
    <?php	} ?>
    </div>
    <?php
}


function postmode_file_parsing() {
    global $session_data;

    // Might skip over rectype selection if there are no unknown rectypes
    $known_rectype_count = 0;
    $unknown_rectype_count = 0;
    foreach (array_keys($session_data['in_entries']) as $i) {
        if ($session_data['in_entries'][$i]->getReferenceType()) ++$known_rectype_count;
        else ++$unknown_rectype_count;
    }

    if ($unknown_rectype_count) {
        $session_data['known-rectype-count'] = $known_rectype_count;
        $session_data['unknown-rectype-count'] = $unknown_rectype_count;
        $session_data['mode'] = 'print rectype selection';
    } else {
        $session_data['mode'] = 'crosswalking';
    }
}


function mode_zotero_request_parsing() {
    global $session_data;
    global $import_id;

    $_import_id = 'zotero-'.get_user_id().'-'.date('Y_m_d-H:i:s');
    $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["heurist-import-$_import_id"] = &$session_data;
    $session_data['in_filename'] = 'Zotero items';
    $import_id = $_import_id;


    $session_data['parser'] = new HeuristZoteroParser();
    $session_data['zoteroImport'] = true;

    list($errors, $entries) = $session_data['parser']->parseRequest($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['ZoteroItems']);
    unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['ZoteroItems']);

    if ($errors) {
        ?>
        <div class="error">
            <div>
                <div>There <?= (count($errors) > 1)? 'were errors' : 'was an unrecoverable error' ?> encountered while parsing the Zotero data:</div>
                <ul>
                    <?php foreach ($errors as $error) { print '<li>' . $error . '</li>'; } ?>
                </ul>
                <div>Your entries have not been imported.</div>
            </div>
        </div>

        <br clear=all>
        <hr>
        <br clear=all>
        <input type="button" value="Cancel" onClick="window.close();">
        <?php
        $session_data['mode'] = 'error';
        return;
    }

    if (count($entries) == 0) {
        ?>
        <div class="error">
            <div>No entries were found for import.</div>
        </div>

        <br clear=all>
        <hr>
        <br clear=all>
        <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
        <?php
        $session_data['mode'] = 'error';
        return;
    }

    $session_data['in_entries'] = &$entries;
    do_entry_parsing();
    ?>
    <br clear=all>
    <hr>
    <br clear=all>
    <input type="submit" value="Continue" style="font-weight: bold;">
    <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
    <?php
}

function mode_print_rectype_selection() {
    global $session_data, $import_id;

    ?>
    <h1>Specify record types</h1>

    <div>File read: <?= htmlspecialchars($session_data['in_filename']) ?></div>

    <table cellpadding="5">
        <tr><td>Records read:</td><td><?= $session_data['known-rectype-count']+$session_data['unknown-rectype-count'] ?></td></tr>
        <tr><td>Record type:</td>
            <td>specified <?= $session_data['known-rectype-count'] ?>
                &nbsp;&nbsp;&nbsp;
                unspecified / unrecognised <?= $session_data['unknown-rectype-count'] ?></td></tr>
    </table>
    <br>
    <hr>
    <br>

    <div style="width: 500px;">To help Heurist accurately identify the record types where these have not been specified, please select the types of record which may be in the file.  Be specific, as you will have further chances to include additional types if there are records which do not fit with the selected types.</div>

    <p>
        <a target="_new" href="interface/downloadOriginalInputFile.php/<?php echo htmlspecialchars($session_data['in_filename']); ?>?db=<?php echo HEURIST_DBNAME; ?>&import_id=<?php echo htmlspecialchars($import_id); ?>">View import file</a>
        &nbsp;&nbsp;&nbsp;
        <a target="_new" href="interface/listRequiredElements.php?db=<?php echo HEURIST_DBNAME; ?>">View tag definitions</a>
    </p>

    <?php	if (@$session_data['error']) {	?>
        <div class="error">
            <div><?= $session_data['error'] ?></div>
        </div>
        <br>
        <?php	}	?>

    <table cellpadding="5">
        <tr>
            <td>
                <!-- span style="vertical-align: top;">All unspecified records <b>have this type</b>&nbsp;</span>&nbsp; -->
            </td>

            <?php	if ($session_data['parser']->supportsReferenceTypeGuessing()) { ?>

                <script type="text/javascript">
                    <!--
                    function heuristic_enabler(enabled) {
                        var elts = document.getElementsByName('use-heuristic[]');
                        for (i=0; i < elts.length; ++i)
                            elts[i].disabled = ! enabled;
                    }
                    //-->
                </script>
                <td>
                    <label><input type="radio" name="set-rectype" value="use heuristic" onClick="if (this.checked) heuristic_enabler(true)">
                        <span style="vertical-align: top;">or <b>let Heurist guess</b> the additional record types</span></label>
                    <br>
                    <input type="radio" style="visibility: hidden;">
                    (mark the types which may occur in this file - see note above)
                </td>
                <?php	} else {	/* reference type guessing is not supported by this parser */ ?>
                <script>
                    function heuristic_enabler() { }
                </script>
                <td></td>
                <?php	} ?>
        </tr>
        <tr><td>Select record type: <select name="set-rectype">
        <?php	
            foreach ($session_data['parser']->getReferenceTypes() as $rectype) { 
                print '<option val="'.htmlspecialchars($rectype).'>'.htmlspecialchars($rectype).'</option>';
            }
            if (false) //it never works
            foreach ($session_data['parser']->getReferenceTypes() as $rectype) { ?>
            <tr>
                <td style="text-align: right;"><label>&nbsp;<input type="radio" name="set-rectype" value="<?= htmlspecialchars($rectype) ?>" onClick="if (this.checked) heuristic_enabler(false);">&nbsp;</label></td>
                <td>
                    <label>
                        <?php	if ($session_data['parser']->supportsReferenceTypeGuessing()) { ?>
                            <input type="checkbox" name="use-heuristic[]" value="<?= htmlspecialchars($rectype) ?>"
                                <?= ($_REQUEST['set-rectype'] == 'use heuristic')? '' : 'disabled' ?>>
                            <?php	} ?>
                        <?= htmlspecialchars($rectype) ?>
                    </label>
                </td>
            </tr>
            <?php	} ?>
          </select></td><td></td></tr>  
          <tr><td style="text-align:right">
         <input type="submit" value="Continue" style="font-weight: bold;">
    <?php    
             if (! @$session_data['zoteroImport']) { ?>
        <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" style="margin-right: 4ex;">
    <?php    } else { ?>
        <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
    <?php    } ?>
         </td><td></td>
         </tr> 
    </table>
    <?php
}


function postmode_print_rectype_selection() {
    global $session_data;
    if (@$_REQUEST['set-rectype']  &&  in_array($_REQUEST['set-rectype'], $session_data['parser']->getReferenceTypes())) {
        // set the record type for all un-typed (and therefore still un-imported) entries
        foreach (array_keys($session_data['in_entries']) as $i)
            if (!$session_data['in_entries'][$i]->getReferenceType()){ // set only the ones that don't already have a type.
                $session_data['in_entries'][$i]->setReferenceType($_REQUEST['set-rectype']);
            }
            $session_data['mode'] = 'crosswalking';
        return;

    } else if (@$_REQUEST['set-rectype'] == 'use heuristic') {
        // user can leave all types unchecked, in which case any choices the heuristic makes are suggestions only
        $all_okay = TRUE;
        foreach (@$_REQUEST['use-heuristic'] as $heuristic_type) {
            if (! in_array($heuristic_type, $session_data['parser']->getReferenceTypes())) {
                $all_okay = FALSE;	// funny buggers
                break;
            }
        }
        if ($all_okay) {
            $session_data['mode'] = 'apply rectype heuristic';
            return;
        }
    }

    // Only get here if no options were selected, or funny buggers.  Remember: never ACCUSE the user of anything.
    $session_data['error'] = 'Please select a single record type for all remaining entries, or one or more types for Heurist to choose from.';
    $session_data['mode'] = 'print rectype selection';	// go back to this page
}


function mode_apply_rectype_heuristic() {
    global $session_data;

    ?>
    <table cellpadding="5">
        <tr><td>Total records read:</td><td><?= $session_data['known-rectype-count']+$session_data['unknown-rectype-count'] ?></td></tr>
        <tr><td>Record type specified in file:</td><td><?= $session_data['known-rectype-count'] ?></td></tr>
        <tr><td>Record type unspecified / unrecognised:</td><td><?= $session_data['unknown-rectype-count'] ?></td></tr>
    </table>

    <?php
    $allowed_types = $_REQUEST['use-heuristic'];
    $allowed_type_lookup = array();
    foreach ($allowed_types as $type)
        $allowed_type_lookup[$type] = TRUE;

    $parser = &$session_data['parser'];

    set_progress_bar_title('Determining record types');

    $suggested_by_rectype = array();
    $definite_by_rectype = array();
    $unknown_count = 0;
    foreach (array_keys($session_data['in_entries']) as $i) {
        $entry = &$session_data['in_entries'][$i];

        if (! $entry->getReferenceType()) {
            $type = $parser->guessReferenceType($entry, $allowed_types);

            // check that the returned type is one of those specified by the user; if not then make it just a suggestion
            if ($type) {
                if (@$allowed_type_lookup[$type]) {
                    $entry->setReferenceType($type);
                    @++$definite_by_rectype[$type];
                } else {
                    $entry->setPotentialReferenceType($type);
                    @++$suggested_by_rectype[$type];
                }
            } else {
                ++$unknown_count;
            }
        } else {
            @++$definite_by_rectype[$entry->getReferenceType()];
        }

        update_progress_bar(++$j / count($session_data['in_entries']));
    }
    update_progress_bar(-1);

    if (@$definite_by_rectype  ||  $unknown_count) {
        ?>
        <h2>Fully allocated record types</h2>

        <table cellpadding="5">
            <?php		if ($unknown_count > 0) { ?>
                <tr><td>Unknown</td><td><?= $unknown_count ?> <small><i>omitted from import</i></small></td></tr>
                <?php		}
            foreach ($already_known_by_rectype as $type => $count) {
                ?>
                <tr><td><?= htmlspecialchars($type) ?></td><td><?= $count ?></td></tr>
                <?php		} ?>
        </table>

        <?php
    }

    if (@$suggested_by_rectype) {
        ?>
        <h2>Pending record types</h2>

        <div>Heurist has identified the following record types.  Check the boxes for the <b>suggested</b> record types you would like to include.  Any record type <i>not checked</i> will be left out when you continue with the import.  All non-imported record will be available to you in a new file.</div>

        <table cellpadding="5">
            <tr>
                <td></td>
                <th>Record type</th>
                <th>Suggested (only imported if checked)</th>
                <th>Definite (will be imported)</th>
            </tr>
            <?php
            foreach (array_keys($suggested_by_rectype) as $type) {
                ?>
                <tr>
                    <td><label>&nbsp;<input type="checkbox" name="use-suggested[]" value="<?= htmlspecialchars($type) ?>">&nbsp;</label></td>
                    <td><?= htmlspecialchars($type) ?></td>
                    <td><?= intval($suggested_by_rectype[$type]) ?></td>
                    <td><?= intval($definite_by_rectype[$type]) ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <br clear=all>
        <hr>
        <br clear=all>

        <?php		if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" style="margin-right: 4ex;">
            <?php		} else { ?>
            <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
            <?php		} ?>
        <input type="submit" name="use-suggestions" value="Add checked record types">
        <input type="submit" name="continue" value="Continue" style="font-weight: bold;">
        <?php	} else if ($definite_by_rectype) { ?>

        <br clear=all>
        <hr>
        <br clear=all>

        <?php		if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" style="margin-right: 4ex;">
            <?php		} else { ?>
            <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
            <?php		} ?>
        <input type="submit" name="continue" value="Continue" style="font-weight: bold;">
        <?php	} else { ?>
        <div>Heurist could not determine a type for any record in your file.<!--  We suggest having a cry. --></div>

        <br clear=all>
        <hr>
        <br clear=all>

        <?php		if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');">
            <?php		} else { ?>
            <input type="button" value="Cancel" onClick="window.close();">
            <?php		} ?>
        <?php
        $session_data['mode'] = 'error';
    }
}


function mode_crosswalking() {
    global $session_data;
    global $import_id;
    global $heurist_rectypes;

    if (! $heurist_rectypes) load_heurist_rectypes();

    set_progress_bar_title('Crosswalking entries');

    $out_entries = array();
    $out_entry_count_by_rectype = array();
    $data_error_entries = array();
    $no_rectype_entries = array();
    $non_out_entries = array();	// = data_error_entries + no_rectype_entries
    $j = 0;
    foreach (array_keys($session_data['in_entries']) as $i) {
        // FIXME: do fancy progress bar stuff
        update_progress_bar(++$j / count($session_data['in_entries']));

        $in_entry = &$session_data['in_entries'][$i];

        if ($in_entry->getPotentialReferenceType()  &&
        in_array($in_entry->getPotentialReferenceType(), $_REQUEST['use-suggested'])) {
            $in_entry->setReferenceType($in_entry->getPotentialReferenceType());
        }

        if ($in_entry->getReferenceType()) {
            unset($out_entry);
            $out_entry = $in_entry->crosswalk();
            if ($out_entry) {
                if(strlen(trim($out_entry->getTitle()))>0) print trim($out_entry->getTitle())."<br>";
                if ($out_entry->isValid()) {
                    $out_entries[] = &$out_entry;
                    @++$out_entry_count_by_rectype[$out_entry->getReferenceType()];
                } else {
                    $in_entry->addValidationErrors(format_missing_field_errors($out_entry));
                    $in_entry->addValidationErrors($out_entry->getOtherErrors());
                    $data_error_entries[] = &$in_entry;
                    $non_out_entries[] = &$in_entry;
                }
            } else {
                $data_error_entries[] = &$in_entry;
                $non_out_entries[] = &$in_entry;
            }
        } else {
            $no_rectype_entries[] = &$in_entry;
            $non_out_entries[] = &$in_entry;
        }
    }
    update_progress_bar(-1);
    $session_data['out_entries'] = &$out_entries;

    // make the error entries available to the session so that they can be downloaded
    $session_data['no_rectype_entries'] = &$no_rectype_entries;
    $session_data['data_error_entries'] = &$data_error_entries;
    $session_data['non_out_entries'] = &$non_out_entries;

    if ($out_entry_count_by_rectype) {
        ?>
        <table border=0 cellspacing=0 cellpadding=0>
            <tr>
                <td style="vertical-align: top; text-align: left; width: 5em; padding-top: 5px;">Types:</td>
                <td style="vertical-align: top; text-align: left; width: 300px;">
                    <table cellpadding="5">
                        <!-- <b>Valid entries for import:</b> -->
                        <?php
                        foreach ($out_entry_count_by_rectype as $type => $count) { ?>
                            <tr><td><?= htmlspecialchars($heurist_rectypes[$type]['rty_Name']) ?></td><td><?= intval($count) ?></td></tr>
                            <?php		}
                        ?>
                    </table>
                </td>
                <td style="vertical-align: top; text-align: left;">
                    <table cellpadding="5">
                        <tr><td>Valid records:</td><td><b><?= intval(count($out_entries)) ?></b></td><td>&nbsp;</td></tr>
                        <?php
                        if ($non_out_entries) {
                            if ($no_rectype_entries) { ?>
                                <tr>
                                    <td style="color: red;white-space:nowrap;">Unallocated record type:<br />(will not be imported)</td>
                                    <td><?= count($no_rectype_entries) ?></td>
                                    <td><a target="_errors" href="interface/downloadRecsWithoutType.php/<?= htmlspecialchars($import_id) ?>-no_rectype.txt?import_id=<?= htmlspecialchars($import_id) ?>">Download errors</td>
                                    <td></td>
                                </tr>
                                <?php 		}
                            if ($data_error_entries) { ?>
                                <tr>
                                    <td style="color: red;white-space:nowrap;">Data errors:<br />(will not be imported)</td>
                                    <td><?= count($data_error_entries) ?></td>
                                    <td><a target="_errors" href="interface/downloadRecsWithErrors.php/<?= htmlspecialchars($import_id) ?>-data_error.txt?import_id=<?= htmlspecialchars($import_id) ?>">Download errors</a></td>
                                </tr>
                                <?php		} ?>
                            <tr><td>Total records:</td><td><b><?= intval(count($out_entries) + count($no_rectype_entries) + count($data_error_entries)) ?></b></td><td>&nbsp;</td></tr>
                            <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
        </table>

        <hr>

        <?php
    }

    if ($out_entries) {
        print_tag_stuff($out_entries);
        ?>
        <p style="margin-left: 15px;">
        <p>Specify tags to add to all imported records:</p>

        <div class="smallgr" style="padding-left: 10ex; margin-left: 10px;white-space:nowrap;">
            Add: <a href="#" target="_ignore" onClick="add_tag('Favourites'); return false;">Favourites</a>&nbsp;
            <a href="#" target="_ignore" onClick="add_tag('To Read'); return false;">To Read</a>&nbsp;
        </div>
        <?php
        $top_tags = mysql__select_array('usrRecTagLinks left join usrTags on rtl_TagID=tag_ID',
            'tag_Text, count(tag_ID) as count',
            'tag_UGrpID='.get_user_id().' group by tag_ID order by count desc limit 5');
        if ($top_tags) {
            ?>
            <div class="smallgr" style="padding-left: 10ex; margin-left: 10px;white-space:nowrap;">
                Top:&nbsp;
                <?php
                foreach ($top_tags as $tag) {
                    $tag = htmlspecialchars($tag);
                    ?>      	<a href="#" target="_ignore" onClick="add_tag('<?=$tag?>'); return false;"><?=$tag?></a>&nbsp; <?php
                }
                ?>
            </div>
            <?php
        }
        ?>

        <?php
        $recent_tags = mysql__select_array('usrRecTagLinks left join usrTags on rtl_TagID=tag_ID',
            'distinct(tag_Text)',
            'tag_UGrpID='.get_user_id().' order by rtl_ID desc limit 5');
        if ($recent_tags) {
            ?>
            <div class="smallgr" style="padding-left: 10ex; margin-left: 10px; padding-bottom: 5px;white-space:nowrap;">
                Recent:
                <?php
                foreach ($recent_tags as $tag) {
                    $tag = htmlspecialchars($tag);
                    ?>      	<a href="#" target="_ignore" onClick="add_tag('<?=$tag?>'); return false;"><?=$tag?></a>&nbsp; <?php
                }
                ?>
            </div>
            <?php
        }
        ?>


        </div>



        <div style="padding-left: 10ex;"><input type="text" name="tags_for_all" id="tags_for_all" style="width: 180px; border: 1px solid black;" autocomplete=off>
            <script>
                var tagsElt = document.getElementById("tags_for_all");
                new top.HEURIST.autocomplete.AutoComplete(tagsElt, top.HEURIST.util.tagAutofill, { nonVocabularyCallback: top.HEURIST.util.showConfirmNewTag });

                function add_tag(tag) {
                    // check if the tag is already in the list somewhere
                    var tags = tagsElt.value.split(/,/);
                    for (var i=0; i < tags.length; ++i) {
                        if (tags[i].replace(/^\s+|\s+$/g, '').replace(/\s+/, ' ').toLowerCase() == tag.toLowerCase()) return;
                    }

                    // otherwise, add it to the end
                    if (tagsElt.value.match(/^\s*$/)) tagsElt.value = tag;
                    else tagsElt.value += "," + tag;
                }

            </script>
            <span class="smallgr">Separate tags with commas</span>
        </div>

        <?php
        /* are there any workgroup-tags for any workgroups this user is in? If so, show the workgroup-tag section */
        $res = mysql_query('select tag_ID, grp.ugr_Name, tag_Text from usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks, '.USERS_DATABASE.'.sysUGrps grp where tag_UGrpID=ugl_GroupID and ugl_GroupID=grp.ugr_ID and ugl_UserID=' . get_user_id() . ' order by grp.ugr_Name, tag_Text');
        if (mysql_num_rows($res) > 0) {
            ?>
            <div style="margin-top: 1ex; margin-left: 10ex;white-space:nowrap;">
                Workgroup tag:
                <select name="workgroup_tag">
                    <option selected></option>
                    <?php		while ($row = mysql_fetch_assoc($res)) {	//saw TODO: add option grouping by workgroup and remove groupname\ ?>
                        <option value="<?= addslashes($row['tag_ID']) ?>">
                            <?= htmlspecialchars($row['ugr_Name']) ?> \ <?= htmlspecialchars($row['tag_Text']) ?>
                        </option>
                        <?php		}	?>
                </select>
            </div>
            <?php
        }
        ?>
        </p>

        <br clear=all>
        <hr>
        <br clear=all>
        <div style="max-width:500px;text-align:right">
        <input type="submit" name="continue" value="Continue" style="font-weight: bold;">
        
            <?php	if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" style="margin-right: 4ex;">
            <?php	} else { ?>
            <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 4ex;">
            <?php	} ?>
        </div>
        <p>
            <b>Import is non-reversible!</b> Data will be written to the Heurist database.
        </p>

        <?php
    } else {
        ?>
        <div>Heurist was unable to import any of your entries.<!-- Maybe you should try it with somebody else's data that doesn't suck --></div>
        <br clear=all>
        <hr>
        <br clear=all>

        <?php		if (@$session_data['data_error_entries']) { ?>
            <a target="_errors" href="interface/downloadRecsWithErrors.php/<?= htmlspecialchars($import_id) ?>-data_error.txt?import_id=<?= htmlspecialchars($import_id) ?>">Download errors</a>
            <?php		} ?>
        <?php		if (! @$session_data['zoteroImport']) { ?>
            <input type="button" value="Cancel" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');">
            <?php		} else { ?>
            <input type="button" value="Cancel" onClick="window.close();">
            <?php		} ?>
        <?php
        $session_data['mode'] = 'error';
    }
}


function mode_entry_insertion() {
    global $session_data;
    global $import_id;


    $tag_map = array();
    if (@$_REQUEST['orig_tags']) {
        $orig_tags = $_REQUEST['orig_tags'];
        $tags = $_REQUEST['tags'];
        if (count($orig_tags) == count($tags)) {
            for ($i=0; $i < count($tags); ++$i)
                $tag_map[strtolower($orig_tags[$i])] = $tags[$i];
        }
    }

    $tags_for_all = explode(',', @$_REQUEST['tags_for_all']);
    $workgroup_tag_id = intval(@$_REQUEST["workgroup_tag"]);

    $session_data['import_time'] =  date('Y-m-d H:i:s');
    // add a tag (tag) for this import session to all entries
    $import_tag = 'File Import ' . $session_data['import_time'];
    $res = mysql__insert('usrTags', array(
        'tag_Text'		=> $import_tag,
        'tag_UGrpID'	=> get_user_id()));
    // add a saved search for records with this tag
    $now = date('Y-m-d');
    mysql__insert('usrSavedSearches', array(
        'svs_Name'		=> $import_tag,
        'ss_url'		=> '?ver=1&w=all&q=kwd%3A%22'.str_replace(' ','%20',$import_tag).'%22',
        'svs_UGrpID'	=> get_user_id(),
        'svs_Added'		=> $now,
        'svs_Modified'	=> $now));

    set_progress_bar_title('Preparing database entries');

    $creatorDT = defined('DT_CREATOR')?DT_CREATOR:0;

    $j = 0;
    foreach (array_keys($session_data['out_entries']) as $i) {
        $entry = &$session_data['out_entries'][$i];
        $entry->addTag($import_tag); // add general import kwd
        if ($tags_for_all) {
            foreach ($tags_for_all as $tag)
                if (trim($tag)) $entry->addTag(trim($tag), true);
        }
        if ($workgroup_tag_id)
            $entry->setWorkgroupTag($workgroup_tag_id);

        $_entry = &$entry;
        do { // for each container set up the author records
            $fields = $_entry->getFields();

            foreach (array_keys($fields) as $i) {
                if (! $fields[$i]->getValue()) continue;
                if ($fields[$i]->getType() ==  $creatorDT) {//MAGIC NUMBER 158
                    // an author: cook the value -- could be several authors
                    process_author($fields[$i]);
                    foreach ($fields[$i]->getValue() as $person_bib_id) {
                        $_entry->addAuthor($person_bib_id);
                    }
                }
            }

            unset($fields);

            $_entry = &$_entry->_container;
        } while ($_entry);
        unset($_entry);

        unset($entry);

        update_progress_bar(++$j / count($session_data['out_entries']));
    }
    update_progress_bar(-1);

    process_disambiguations();
    $session_data['ambiguities'] = array();

    $ambig_count = 0;
    $j = 0;
    $out_entry_count = count($session_data['out_entries']);

    global $zoteroItems;
    $zoteroItems = array();

    list($usec, $sec) = explode(' ', microtime());
    $stime = $sec + $usec;
    set_progress_bar_title('Checking against existing entries');
    foreach (array_keys($session_data['out_entries']) as $i) {
        unset($entry);
        $entry = &$session_data['out_entries'][$i];
        if ($entry->getBookmarkID()) {
            ++$session_data['dupe-records-count'];
            ++$session_data['dupe-bookmark-count'];

            unset($session_data['out_entries'][$i]);
            // in the future we should unset these from the session data

            continue;		// already inserted;
        }

        //		if (! $entry->getBiblioID())
        //			insert_biblio($entry);	// insert a (temporary) records entry
        //		$temp_bib_id = $entry->getBiblioID();

        if (! find_exact_entry($entry)) {
            find_similar_entries($entry);
        }

        if ($entry->getBiblioID()  &&  $entry->_permanent) {  //set in find_exact_entry
            // already in the database

            // copy across any new data
            // FIXME			merge_biblio($entry->getBiblioID(), $temp_bib_id);

            // find any data that is not already part of the entry, and merge it in
            merge_new_biblio_data($entry->getBiblioID(), $entry);

            // Make sure there's a bookmark
            @++$session_data['dupe-records-count'];

            if (! @$session_data['dupe-biblios']) $session_data['dupe-biblios'] = array();
            array_push($session_data['dupe-biblios'], $entry->getBiblioID());

            if (insert_bookmark($entry)) @++$session_data['added-bookmark-count'];
            else @++$session_data['dupe-bookmark-count'];
            insert_tags($entry, $tag_map);
            delete_biblio($entry);
            unset($session_data['out_entries'][$i]);

        } else if ($entry->getPotentialMatches()) {
            // present disambiguation options
            if (! $entry->getBiblioID()) insert_biblio($entry);

            if (! @$session_data['disambig-biblios']) $session_data['disambig-biblios'] = array();
            array_push($session_data['disambig-biblios'], $entry->getBiblioID());

            print_disambiguation_options($entry);
            @++$ambig_count;
        } else if ($entry->getAncestor()  &&  $entry->_ancestor->getPotentialMatches()) {
            // present disambiguation options
            if (! $entry->getBiblioID()) insert_biblio($entry);
            if (! @$session_data['disambig-ancestor-biblios']) $session_data['disambig-ancestor-biblios'] = array();
            array_push($session_data['disambig-ancestor-biblios'], $entry->getBiblioID());

            print_disambiguation_options($entry);
            @++$ambig_count;
        } else if (! $entry->getAncestor()  ||  $entry->_ancestor->_permanent) {
            // The entry (and possibly several container entries) needs to be inserted into records
            $_entry = $entry;
            do {
                if (! $_entry->getBiblioID()) insert_biblio($_entry);
                perm_biblio($_entry);
                $_entry = &$_entry->_container;
            } while ($_entry  &&  ! $_entry->_permanent);

            if (! @$session_data['added-biblios']) $session_data['added-biblios'] = array();
            array_push($session_data['added-biblios'], $entry->getBiblioID());

            @++$session_data['added-records-count'];
            if (insert_bookmark($entry)) @++$session_data['added-bookmark-count'];
            else @++$session_data['dupe-bookmark-count'];
            insert_tags($entry, $tag_map);
            unset($session_data['out_entries'][$i]);
        } else {
            // If we get here then the entry has an ancestor set but no definite/possible records ID for that ancestor.
            // This goes against the definition of an ancestor, so we shouldn't be here.
            // This means an internal error, but we may Recover Gracefully.
            // Insert the entry and all containers.
            $entry->setAncestor(NULL);
            perm_biblio($entry);

            if (! @$session_data['other-biblios']) $session_data['other-biblios'] = array();
            array_push($session_data['other-biblios'], $entry->getBiblioID());

            ++$session_data['added-records-count'];
            if (insert_bookmark($entry)) ++$session_data['added-bookmark-count'];
            else ++$session_data['dupe-bookmark-count'];
            insert_tags($entry, $tag_map);
            unset($session_data['out_entries'][$i]);
        }

        // FIXME: need to check that entry is inserted properly -- if not, append it to $non_out_entries
        // print "<a target='_other' href='../edit.php?bkmk_id=".$entry->getBookmarkID()."'>".$entry->getBiblioID()."</a><br>";

        // do fancy progress bar stuff

        update_progress_bar(++$j / $out_entry_count);
    }
    update_progress_bar(-1);
    list($usec, $sec) = explode(' ', microtime());
    $etime = $sec + $usec;


    if ($zoteroItems  &&  count($zoteroItems) > 0) {
        ?>
        <div style="display: none;"><xml id="ZoteroItems">
            <?= '<?xml version="1.0"?>' ?>                           
            <ZoteroItems>
                <?php		foreach ($zoteroItems as $zoteroID => $heuristBibID) { ?>
                    <ZoteroItem>
                        <ZoteroID><?= $zoteroID ?></ZoteroID>
                        <HeuristID><?= $heuristBibID ?></HeuristID>
                        <HeuristStatus>OK</HeuristStatus>
                        <SyncDate><?= time() ?></SyncDate>
                    </ZoteroItem>
                    <?php		} ?>
            </ZoteroItems></xml>
        </div>
        <?php
    }

    if ($ambig_count) {
        ?>
        <hr>
        <br clear=all>
        <input type="submit" value="Continue" style="font-weight: bold;">

        <?php
    }

    // print summary, print link to non-imported data, print magic "Finished" button
    ?>
    <br clear=all>
    <br clear=all>
    <hr>
    <br clear=all>
    <table cellpadding="5">
        <tr><td><b>Records:</b></td></tr>
        <tr><td>Read:</td><td><?= count(@$session_data['in_entries']) ?></td></tr>
        <tr><td>Added:</td><td><?= intval(@$session_data['added-records-count']) ?></td></tr>
        <tr><td>Dupes:</td><td><?= intval(@$session_data['dupe-records-count']) ?></td></tr>
        <tr><td><b>Bookmarks:</b></td></tr>
        <tr><td>Added:</td><td><?= intval(@$session_data['added-bookmark-count']) ?></td></tr>
        <tr><td>Dupes:</td><td><?= intval(@$session_data['dupe-bookmark-count']) ?></td></tr>
        <tr><td colspan=2>Note: duplicate bookmarks/records and records with errors were not added to the database</td></tr>
        <?php /*
        <tr><td colspan=2>
        <br>added biblios: <a target=_new href="/heurist/?q=ids:<?= join(',', $session_data['added-biblios']) ?>">here</a>
        <br>dupe biblios: <a target=_new href="/heurist/?q=ids:<?= join(',', $session_data['dupe-biblios']) ?>">here</a>
        <br>disambig biblios: <a target=_new href="/heurist/?q=ids:<?= join(',', $session_data['disambig-biblios']) ?>">here</a>
        <br>disambig-ancestor biblios: <a target=_new href="/heurist/?q=ids:<?= join(',', $session_data['disambig-ancestor-biblios']) ?>">here</a>
        <br>other biblios: <a target=_new href="/heurist/?q=ids:<?= join(',', $session_data['other-biblios']) ?>">here</a>
        </td></tr>
        */ ?>
        <?php	if ($ambig_count) { ?>
            <tr><td>Possible duplicates waiting for disambiguation:</td><td><?= $ambig_count ?></td></tr>
            <?php	}
        if ($session_data['non_out_entries']) { ?>
            <tr><td>Records with data errors:</td><td><?= count($session_data['non_out_entries']) ?></td></tr>
            <?php	} ?>
    </table>

    <?php	if (! @$session_data['zoteroImport']) { ?>
        <?php		if ($ambig_count  ||  $session_data['non_out_entries']) { ?>
            <a target="_errors" href="interface/downloadNonImported.php/<?= htmlspecialchars($import_id) ?>-unimported.txt?import_id=<?= htmlspecialchars($import_id) ?>" onClick="elt=document.getElementById('finished_button'); if (elt) elt.disabled = false;" style="color: red;">Download non-imported records</a>
            <?php		}
        if (! $ambig_count  &&  $session_data['non_out_entries']) { ?>
            <input type="button" value="Finished" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" disabled="true" id="finished_button" style="font-weight: bold;" title="You must download non-imported records before clicking this button">
            <?php		}	?>
        <?php	} else {	?>
        <?php		if ($ambig_count  ||  $session_data['non_out_entries']) { ?>
            <a href="interface/downloadNonImported.php/<?= htmlspecialchars($import_id) ?>-unimported.txt?import_id=<?= htmlspecialchars($import_id) ?>" onClick="elt=document.getElementById('finished_button'); if (elt) elt.disabled = false;" style="color: red;">Download non-imported records</a>
            <?php		}	?>
        <?php	} ?>
    <?php	if (! $ambig_count  &&  ! $session_data['non_out_entries']) { ?>
        <?php		if (@$session_data["zoteroImport"]) {	?>
            <input type="button" value="Finished" onClick="window.close();" style="font-weight: bold;">
            <?php		} else {	?>
            <input type="button" value="Finished" onClick="window.location.replace('importerFramework.php?db=<?=HEURIST_DBNAME?>');" style="font-weight: bold;">
            <?php		} ?>
        <?php	}

    if ($ambig_count == 0) $session_data['mode'] = 'finished';
}


function do_entry_parsing() {
    global $session_data;

    $entries = &$session_data['in_entries'];
    ?>
    <p>Records:</p>
    <table class="references_summary">
        <?php if (! (@$session_data['zoteroImport']  ||  @$_REQUEST['zoteroEntryPoint'])) { ?>
            <tr><td style="padding-left: 10ex;">Total records read:</td><td><?= count($entries) ?></td></tr>
            <?php } else { ?>
            <tr><td style="padding-left: 10ex;">Number of unsynchronised Zotero records:</td><td><?= count($entries) ?></td></tr>
            <?php } ?>
        <?php
        flush_fo_shizzle();

        set_progress_bar_title('Parsing file');

        $good_entries = array();
        $bad_entries = array();
        $known_rectypes = 0;
        $j = 0;
        foreach (array_keys($entries) as $i) {
            $entry = & $entries[$i];
            $errors = $entry->parseEntry();
            if (! $errors) {
                $good_entries[] = &$entry;
                if ($entry->getReferenceType()) ++$known_rectypes;
            } else {
                $bad_entries[] = &$entry;
            }

            // FIXME: do fancy progress bar stuff

            update_progress_bar(++$j / count($entries));
        }
        update_progress_bar(-1);

        ?>
        <tr<?php if (count($bad_entries) > 0) print ' style="color: red;"'; ?>><td style="padding-left: 10ex;">Bad format:</td><td><?= count($bad_entries) ?></td></tr>
        <tr><td style="padding-left: 10ex; padding-right: 4ex;">Record type not specified:</td><td><?= count($good_entries) - $known_rectypes ?></td></tr>
    </table>
    <?php
    flush_fo_shizzle();
}


function choose_next_mode() {
    global $session_data;

    if (@$_REQUEST['zoteroEntryPoint']) {
        $session_data['mode'] = 'zotero request parsing';
        return;
    }

    $session_data['prev-mode'] = @$session_data['mode']? $session_data['mode'] : '';


    // allow the user to keep re-visiting the entry insertion page as long as there are records to disambiguate
    if ($session_data['prev-mode'] == 'entry insertion'  &&  (count(@$session_data['disambig-biblios']) > 0  ||  count(@$session_data['disambig-ancestor-biblios']) > 0)) {
        $session_data['mode'] = 'entry insertion';
        return;
    }

    /*
    if (@$_REQUEST['clear-session'] == 'Yes') {
    $session_data['mode'] = 'clear session';
    return;
    } else if (@$_REQUEST['clear-session'] == 'No') {
    $session_data['mode'] = 'file selection';
    return;
    }
    */
    if ($session_data['prev-mode'] == ''  &&  @$_FILES['import_file']['name']) {
        $session_data['prev-mode'] = 'file selection';
    }

    switch ($session_data['prev-mode']) {
        case 'file selection':
            // might progress to file parsing, might stay at file selection
            postmode_file_selection();
            break;

        case 'file parsing':
        case 'zotero request parsing':
            // might skip over rectype selection if there are no unknown rectypes
            postmode_file_parsing();
            break;

        case 'print rectype selection':
            // user might have selected "set all to X", or "choose between X, Y, Z"
            postmode_print_rectype_selection();
            break;

        case 'apply rectype heuristic':
            // only one way forward
            $session_data['mode'] = 'crosswalking';
            break;

        case 'crosswalking':
            // only one way forward
            $session_data['mode'] = 'entry insertion';
            break;

        case '':
            $heurist_import_count = 0;
            if(is_array(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']))
            foreach ($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'] as $name => $val) {
                if (strpos($name, 'heurist-import-') === 0) ++$heurist_import_count;
            }
            if ($heurist_import_count > 0) {
                // get rid of old import data automatically
                clear_session();
            }

        case 'finished':
        case 'entry insertion':
        default:
            $session_data['mode'] = 'file selection';
    }
}


function format_missing_field_errors(&$entry) {
    // $entry is a HeuristNativeEntry with validation problems;
    // return an array of errors describing the missing fields in the entry and its containers
    global $heurist_rectypes, $bib_requirement_names;
    if (! $heurist_rectypes) load_heurist_rectypes();
    if (! $bib_requirement_names) load_bib_requirement_names();

    $errors = array();

    $missing_fields = $entry->getMissingFields();
    if ($missing_fields) {
        $err_msg = '';
        $have_multiple = (count($missing_fields) > 1);
        while ($field = array_shift($missing_fields)) {
            if ($err_msg  &&  $missing_fields) $err_msg .= ', ';	// join missing fields with commas ...
            else if ($err_msg) $err_msg .= ' and ';			// except for the final one
                $err_msg .= strtoupper($bib_requirement_names[$entry->getReferenceType()][$field]);
        }
        $err_msg = strtoupper($heurist_rectypes[$entry->getReferenceType()]['rty_Name'])
        . ' is missing ' . $err_msg . ($have_multiple? ' fields' : ' field');
        array_push($errors, $err_msg);
    }
    if ($entry->getContainerEntry()) {
        $container = & $entry->getContainerEntry();
        $container_errors = format_missing_field_errors($container);
        if ($container_errors  &&  ! $entry->containerIsOptional()
            /*
            &&  (($entry->getReferenceType() == 5  ||  $entry->getReferenceType() == 4)
            ||
            (  ! ($container->getReferenceType() == 30  &&  count($container->_fields) == 0)
            &&  ! ($container->getReferenceType() == 44  &&  count($container->_container->_fields) == 0)))
            */
            )
            // publisher is "optional" for non-book types (i.e. it's disregarded if none of its fields are specified at all)
            foreach ($container_errors as $error) array_push($errors, $error);
    }

    return $errors;
}


function find_exact_entry(&$entry) {
    // See if there's an entry in the database that exactly matches this one in all the rst_RecordMatchOrder fields

    if ($entry->getBiblioID()  &&  $entry->_permanent) {
        // might be an exact match already, if specified in the input file
        return true;
    }

    $res = mysql_query("select rec_ID from Records where ! rec_FlagTemporary and rec_RecTypeID = " . $entry->getReferenceType() . " and rec_Hash = upper('" . mysql_real_escape_string($entry->getHHash()) . "') order by rec_ID");

    if (mysql_num_rows($res) < 1) return false;
    // choose One Of The Matches ... tend to think that the one with the lowest bibID has precedence ...
    $someMatch = mysql_fetch_row($res);
    $someMatch = $someMatch[0];
    if ($someMatch) {
        setPermanentBiblioID($entry, $someMatch);
        return true;
    }

    return false;
}


function find_similar_entries(&$entry) {
    // Fill in the match and possible-match fields for the given entry
    // with records IDs for entries already in the database that do or might correspond to it

    if ($entry->getReferenceType() == 44  ||  $entry->getReferenceType() == 28) {	// a publication series or journal volume
        $hash = $entry->getHHash();
        $hashColumn = "rec_Hash";
    }else{
        return;
    }
    $hash_len = intval(strlen($hash) * HASH_FUZZINESS);

    // use a strict substring to take advantage of the index on hash
    if (HASH_PREFIX_LENGTH) {
        $hprefix = mb_substr($hash, 0, HASH_PREFIX_LENGTH);
        $similar_query = "select rec_ID as matching_bib_id, new_levenshtein($hashColumn, upper('" . mysql_real_escape_string($hash) . "')) as lev from Records where ! rec_FlagTemporary and rec_RecTypeID = " . $entry->getReferenceType() . " and $hashColumn like '" . mysql_real_escape_string($hprefix) . "%' having lev < $hash_len order by lev";
    }
    else {
        $similar_query = "select rec_ID as matching_bib_id, new_levenshtein($hashColumn, upper('" . mysql_real_escape_string($hash) . "')) as lev from Records where ! rec_FlagTemporary and rec_RecTypeID = " . $entry->getReferenceType() . " having lev < $hash_len order by lev";
    }

    $res = mysql_query($similar_query);

    $near_misses = array();
    while ($row = mysql_fetch_assoc($res)) {
        array_push($near_misses, $row["matching_bib_id"]);
    }

    if (count($near_misses) > 0) {
        foreach ($near_misses as $near_miss)
            $entry->addPotentialMatch($near_miss);
        if ($entry->getPotentialMatches()) return;
    }

    // No matches: go up a level, see if there's a match there
    if ($entry->_container) {
        if (! find_exact_entry($entry->_container))
            find_similar_entries($entry->_container);

        if ($entry->_container->_permanent  ||  $entry->_container->getPotentialMatches())	// immediate container is the ancestor that the user has to disambiguate
            $entry->setAncestor($entry->_container);
        else if ($entry->_container->getAncestor())		//pass ancestor down the containment hierarchy
            $entry->setAncestor($entry->_container->getAncestor());	// container's ancestor is our ancestor too
            else
                $entry->_ancestor = NULL;
    }
    else $entry->_ancestor = NULL;
}


function biblio_are_equal($bib_id1, $bib_id2) {
    // do a recursive comparison on the two Records records
    // regard the first one as "authoritative", the second as "speculative" where this makes any sense
    $res = mysql_query("select 1 from Records where rec_ID = $bib_id1 and rec_Hash = hhash($bib_id2)");
    return (mysql_num_rows($res) > 0);

    $equality_query =
    '
    select sum(rst_RecordMatchOrder) as bdr_match_count
    from recDetails BD1
    left join recDetails BD2 on BD1.dtl_DetailTypeID=BD2.dtl_DetailTypeID and (BD1.dtl_Value=BD2.dtl_Value or (length(BD1.dtl_ValShortened) > 20 and cast(new_liposuction(BD1.dtl_Value) as char) = cast(new_liposuction(BD2.dtl_Value) as char)))
    left join Records on BD1.dtl_RecID=rec_ID
    left join defRecStructure on rst_DetailTypeID=BD1.dtl_DetailTypeID and rst_RecTypeID=rec_RecTypeID
    left join defDetailTypes on dty_ID=BD1.dtl_DetailTypeID
    where BD1.dtl_RecID=' . $bib_id1 . ' and BD2.dtl_RecID in (' . $bib_id1 . ',' . $bib_id2 . ')
    and (BD1.dtl_DetailTypeID = 158  or  dty_Type != "resource")
    group by BD2.dtl_RecID
    order by BD1.dtl_RecID != BD2.dtl_RecID
    ';//MAGIC NUMBER
    $res = mysql_query($equality_query);
    $bd1_counts = mysql_fetch_assoc($res);
    $bd2_counts = mysql_fetch_assoc($res);

    if ($bd1_counts['bdr_match_count'] != $bd2_counts['bdr_match_count']) return false;	// not at all equal

    /* This one took a long time to compose, so DON'T MESS IT UP.
    * For each detail type which is marked rst_RecordMatchOrder,
    * grab the corresponding bib_ids from the two records currently being matched.
    * If this returns any rows, then each row gives us two new Records records that need to be tested for equality.
    */
    $res = mysql_query('select BD1.dtl_Value as bd1_resource, BD2.dtl_Value as bd2_resource
        from defDetailTypes
        left join Records on rec_ID='.$bib_id1.'
        left join defRecStructure on rst_DetailTypeID=dty_ID and rst_RecTypeID=rec_RecTypeID
        left join recDetails BD1 on BD1.dtl_DetailTypeID=dty_ID
        left join recDetails BD2 on BD2.dtl_DetailTypeID=dty_ID and BD2.dtl_RecID='.$bib_id2.'
    where BD1.dtl_RecID=rec_ID and (dty_ID != 158  and  dty_Type = "resource") and rst_RecordMatchOrder and BD1.dtl_ID is not null');//MAGIC NUMBER

    if (mysql_num_rows($res) == 0) return true;	// there are no resource-pointer types required for a match
    while ($row = mysql_fetch_row($res)) {
        if (! $row[1]) return false;	// the trail went dead! BD2 doesn't have a pointer where it needs one: not a match

        if (! biblio_are_equal($row[0], $row[1]))	// containers are equal
            return false;
    }

    // all containers passed the comparison!
    return true;
}


function insert_biblio(&$entry) {
    // Insert records entries for this entry and its containers,
    // and the associated recDetails and usrRecTagLinks entris.
    global $session_data;
    global $bib_type_names;
    if (! $bib_type_names) load_bib_type_names();

    // resolve the container: we may have to insert records entries for the base entry's container, container-container etc
    if ($entry->_container  &&  ! $entry->_container->getBiblioID()) {
        if ($entry->_container  &&  $entry->_container->isValid())	// there's the possibility that the container is invalid, but not required (so don't hook it up)
            insert_biblio($entry->_container);
        // check container->getBiblioID()
    }

    $bib = array('rec_RecTypeID' => $entry->getReferenceType(),
        'rec_Added' => date('Y-m-d H:i:s'),
        'rec_Modified' => date('Y-m-d H:i:s'),
        'rec_AddedByImport' => 1,
        'rec_AddedByUGrpID' => get_user_id(),
        'rec_FlagTemporary' => 1);	// always insert entries as temporary, we can perm them later

    $bib_details = array();

    $rec_scratchpad = '[' .
    str_replace('parser', 'import', $session_data['parser']->parserDescription()) .
    ', ' . $session_data['import_time'] .
    ', from file: ' . $session_data['in_filename'] .
    ', by user: ' . get_user_name(). ']';

    $fields = $entry->getFields();
    foreach (array_keys($fields) as $i) {
        if (! $fields[$i]->getValue()) continue;

        if (! $fields[$i]->getType()) {
            if ($rec_scratchpad) $rec_scratchpad .= "\n";
            $val = $fields[$i]->getRawValue();
            $rec_scratchpad .= is_array($val)? join("\n", $val) : $val;
        } else if ($fields[$i]->getType() === "url") {
            // set as the rec_URL
            $bib["rec_URL"] = $fields[$i]->getRawValue();
        } else if ($fields[$i]->getType() === 256  &&  ! @$bib["rec_URL"]) { //256 - web links MAGIC NUMBER
            // use first web link as the rec_URL
            $bib["rec_URL"] = $fields[$i]->getRawValue();
        } else {
            $bib_details[] = &$fields[$i];
        }
    }
    if ($rec_scratchpad) $bib['rec_ScratchPad'] = $rec_scratchpad;


    $creatorDT = (defined('DT_CREATOR')?DT_CREATOR:0);

    mysql__insert('Records', $bib);
    $rec_id = mysql_insert_id();
    $entry->setBiblioID($rec_id);

    $bib_detail_insert = '';
    foreach (array_keys($bib_details) as $i) {
        unset($field);
        $field = &$bib_details[$i];

        if ($field->getType() == $creatorDT) {//MAGIC NUMBER - Author/Creator
            foreach ($field->getValue() as $person_bib_id) {
                if ($bib_detail_insert) $bib_detail_insert .= ', ';
                $bib_detail_insert .= '('.$rec_id.','.$field->getType().', "'.mysql_real_escape_string($person_bib_id).'", NULL, 1)';
            }
        }
        else if ($field->getGeographicValue()) {
            if ($bib_detail_insert) $bib_detail_insert .= ', ';
            $bib_detail_insert .= '('.$rec_id.','.$field->getType().',"'.mysql_real_escape_string($field->getValue()).'",geomfromtext("'.mysql_real_escape_string($field->getGeographicValue()).'"),1)';
        }
        else {
            if ($bib_detail_insert) $bib_detail_insert .= ', ';
            $bib_detail_insert .= '('.$rec_id.','.$field->getType().', "'.mysql_real_escape_string($field->getValue()).'", NULL, 1)';
        }
    }

    if ($entry->_container  &&  $entry->_container->isValid()) {
        global $rectype_to_bdt_id_map;
        if ($bib_detail_insert) $bib_detail_insert .= ', ';

        $resource_pointer_type = @$rectype_to_bdt_id_map[$entry->_container->getReferenceType()];
        if (! $resource_pointer_type) $resource_pointer_type = 267; //MAGIC bibliographic reference
        $bib_detail_insert .= '('.$rec_id.','.$resource_pointer_type.','.$entry->_container->getBiblioID().', NULL, 1)';
    }
    if ($bib_detail_insert) {
        $bib_detail_insert = 'insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_Geo, dtl_AddedByImport) values '
        . $bib_detail_insert;

        mysql_query($bib_detail_insert);
    }

    $recTitle = $entry->getTitle();
    mysql_query('set @suppress_update_trigger := 1');            //, rec_Hash = hhash(rec_ID) - this function has error
    mysql_query('update Records set rec_Title = "'.mysql_real_escape_string($recTitle).'" where rec_ID='.$rec_id);
    mysql_query('set @suppress_update_trigger := NULL');
}


function perm_biblio(&$entry) {
    // mark the bibliographic record associated with this entry as NON-TEMPORARY
    mysql_query('set @suppress_update_trigger := 1');
    mysql_query('update Records set rec_FlagTemporary = 0 where rec_ID = ' . $entry->getBiblioID());

    if ($entry->_container  &&  $entry->_container->_permanent) {
        // container is already permanent: this means that we found a match in the database for the container, so our recDetails is out-of-date.  Update it.

        global $rectype_to_bdt_id_map;

        mysql_query('update recDetails set dtl_Value='.$entry->_container->getBiblioID().
            ' where dtl_RecID='.$entry->getBiblioID().' and dtl_DetailTypeID='.$rectype_to_bdt_id_map[ $entry->_container->getReferenceType() ]);
    }

    if ($entry->_author_bib_ids) mysql_query('update Records set rec_FlagTemporary=0 where rec_ID in ('.join(',', $entry->_author_bib_ids).')');

    mysql_query('set @suppress_update_trigger := NULL');
    $entry->_permanent = true;

    updateCachedRecord($entry->getBiblioID());
}


function merge_biblio($master_bib_id, $slave_bib_id) {
    global $session_data;
    // When confronted by two matching records,
    // make sure that the record that will stay in the database (master)
    // contains at least all the info in the one we're importing (slave)

    // We need to insert into recDetails fields which aren't already in the old entry.
    // Here we just determine whether such data exists,
    // this is to prevent false deltas in the archive tables.


    $new_bd_query = '
    select '.$master_bib_id.', S.dtl_DetailTypeID, S.dtl_Value, 2
    from recDetails S
    left join recDetails M on S.dtl_DetailTypeID=M.dtl_DetailTypeID and S.dtl_ValShortened=M.dtl_ValShortened and M.dtl_RecID='.$master_bib_id.'
    left join defDetailTypes on dty_ID=S.dtl_DetailTypeID
    where S.dtl_RecID='.$slave_bib_id.' and M.dtl_RecID is null and (dty_Type != "resource" or S.dtl_DetailTypeID=158)';//MAGIC NUMBER	// ignore non-author references
    $res = mysql_query($new_bd_query);
    $num_bd_rows = mysql_num_rows($res);

    // Consider, line-by-line, the values in the entries' respective rec_ScratchPad fields.
    // Any lines that are not already in the master field will be added at the end.

    $rec_scratchpad = mysql__select_assoc('Records', 'rec_ID', 'rec_ScratchPad', 'rec_ID in ('.$master_bib_id.','.$slave_bib_id.')');
    if (! $rec_scratchpad[$master_bib_id]) {
        $new_val = $rec_scratchpad[$slave_bib_id];
    } else {
        $master_lines = explode("\n", $rec_scratchpad[$master_bib_id]);
        $master_lines_map = array();
        foreach ($master_lines as $line) $master_lines_map[strtolower($line)] = 1;

        $slave_lines = explode("\n", $rec_scratchpad[$slave_bib_id]);
        foreach ($slave_lines as $i => $slave_line)
            if (@$master_lines_map[strtolower($slave_line)]) unset($slave_lines[$i]);

            if ($slave_lines) $new_val = $rec_scratchpad[$master_bib_id] . "\n\n[" .
            str_replace('parser', 'import', $session_data['parser']->parserDescription()) .
            ', ' . $session_data['import_time'] .
            ', from file: ' . $session_data['in_filename'] .
            ', by user: ' . get_user_name() . "]\n" .
            join("\n", $slave_lines);
        else $new_val = NULL;
    }

    if ($new_val) {
        mysql_query('update Records set rec_ScratchPad="'.mysql_real_escape_string($new_val).'", rec_Modified=now() where rec_ID='.$master_bib_id);
    } else if ($num_bd_rows) {
        mysql_query('update Records set rec_Modified=now() where rec_ID='.$master_bib_id);
    }

    // Insert the rows identified before.  We have the exact same select query so the database's internal-cache
    // should recognise this and impose no performance hit; doing it this way (instead of retrieving the values above,
    // making a valid request, etc etc) reduces parsing and traffic.
    if ($num_bd_rows) {
        $res = mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) ' . $new_bd_query);
    }

    // update the memcached copy of this record
    updateCachedRecord($master_bib_id);
}


function merge_new_biblio_data($master_biblio_id, &$entry) {
    global $session_data;

    $master_biblio_id = intval($master_biblio_id);

    $existingFields = array();
    $bib_ids = array($master_biblio_id);

    while ($rec_id = array_pop($bib_ids)) {
        $res = mysql_query("select dtl_DetailTypeID, dtl_Value, dty_Type from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID where dtl_RecID = " . intval($rec_id));

        while ($bd = mysql_fetch_assoc($res)) {
            if ($bd["dty_Type"] === "resource") {
                if ($bd["dtl_DetailTypeID"] !== 158) array_push($bib_ids, $bd["dtl_Value"]);	//MAGIC NUMBER// also pull in fields from non-author related fields
            }
            else {
                $existingFields[$bd["dtl_DetailTypeID"]."-".trim(strtolower($bd["dtl_Value"]))] = 1;
            }
        }
    }
    $res = mysql_query("select rec_ScratchPad from Records where rec_ID = " . $master_biblio_id);
    $notesString = mysql_fetch_row($res);  $notesString = $notesString[0];
    $notes = array();
    foreach (explode("\n", $notesString) as $line) { $notes[trim(strtolower($line))] = 1; }

    $fields = &$entry->getFields();

    $extraNotesString = "";
    $newFields = array();
    foreach (array_keys($fields) as $i) {
        if (! $fields[$i]->getValue()) continue;

        if (! $fields[$i]->getType()) {
            // add type-less data to the notes field
            foreach (explode("\n", $fields[$i]->getRawValue()) as $line) {
                if (! @$notes[trim(strtolower($line))]) {
                    if ($extraNotesString) $extraNotesString .= "\n";
                    $extraNotesString .= $line;
                    $notes[trim(strtolower($line))] = 1;
                }
            }
        }
        else if ($fields[$i]->getGeographicValue()) {
            $newFields[] = &$fields[$i];
        }
        else if ($fields[$i]->getType() != 158) {//MAGIC NUMBER
            // add typed data, if it doesn't exist exactly already
            if (! @$existingFields[$fields[$i]->getType() . "-" . trim(strtolower($fields[$i]->getRawValue()))]) {
                $newFields[] = &$fields[$i];
                $existingFields[$fields[$i]->getType() . "-" . trim(strtolower($fields[$i]->getRawValue()))] = 1;
            }
        }
    }


    if ($extraNotesString) {
        $newNotesString = '[' .
        str_replace('parser', 'import', $session_data['parser']->parserDescription()) .
        ', ' . $session_data['import_time'] .
        ', from file: ' . $session_data['in_filename'] .
        ', by user: ' . get_user_name(). ']' . "\n" . $extraNotesString;

        if ($notesString) $newNotesString = $notesString . "\n" . $newNotesString;
        mysql_query("update Records set rec_Modified=now(), rec_ScratchPad='" . mysql_real_escape_string($newNotesString) . "' where rec_ID=" . $master_biblio_id);
    }
    else if (count($newFields) > 0) {
        mysql_query("update Records set rec_Modified=now() where rec_ID=" . $master_biblio_id);
    }
    else {
        // nothing to do!
        return;
    }

    // insert missing fields
    $insertStmt = "";
    foreach (array_keys($newFields) as $i) {
        if ($newFields[$i]->getGeographicValue()) {
            // delete existing geos
            mysql_query("delete from recDetails where dtl_RecID = $master_biblio_id and dtl_DetailTypeID = " . $newFields[$i]->getType());
            if ($insertStmt) $insertStmt .= ', ';
            $insertStmt .= "(" . $master_biblio_id . "," . $newFields[$i]->getType() . ",'" . mysql_real_escape_string($newFields[$i]->getValue())."',geomfromtext('".mysql_real_escape_string($newFields[$i]->getGeographicValue()) . "'), 1)";
        } else {
            if ($insertStmt) $insertStmt .= ",";
            $insertStmt .= "(" . $master_biblio_id . "," . $newFields[$i]->getType() . ",'" . mysql_real_escape_string($newFields[$i]->getRawValue()) . "', NULL, 1)";
        }
    }
    $insertStmt = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_Geo, dtl_AddedByImport) values " . $insertStmt;

    mysql_query($insertStmt);

    // update the memcached copy of this record
    updateCachedRecord($master_bib_id);
}


function delete_biblio(&$entry) {
    // delete a temporary bibliographic record
    mysql_query('set @suppress_update_trigger := 1');
    mysql_query('delete from Records where rec_ID = ' . $entry->getBiblioID() . ' and rec_FlagTemporary');
    if (mysql_affected_rows() == 1) mysql_query('delete from_bib_detail where dtl_RecID = ' . $entry->getBiblioID());
    mysql_query('set @suppress_update_trigger := NULL');
}


function insert_bookmark(&$entry) {
    // Make sure that there is a bookmark for this entry (which has Biblio ID set)
    // and insert tags as necessary.
    // Returns true if a bookmark was added.

    global $zoteroItems;

    if (! $entry->getBiblioID()) return false;

    // First: check if the user already has a bookmark for this records
    $res = mysql_query('select bkm_ID from usrBookmarks where bkm_recID = ' . $entry->getBiblioID()
        . ' and bkm_UGrpID = ' . get_user_id());
    if (mysql_num_rows($res) > 0) {
        $bkm_ID = mysql_fetch_row($res);
        $bkm_ID = $bkm_ID[0];

        if (is_a($entry->getForeignPrototype(), 'HeuristZoteroEntry')) {
            mysql_query('update usrBookmarks set bkm_ZoteroID = ' . $entry->getForeignPrototype()->getZoteroID().' where bkm_ID='.$bkm_ID);
            $zoteroItems[$entry->getForeignPrototype()->getZoteroID()] = $entry->getBiblioID();
        }

        $entry->setBookmarkID($bkm_ID);
        return false;
    } else {
        // Otherwise insert a new bookmark.
        $bkmk = array('bkm_recID' => $entry->getBiblioID(),
            'bkm_Added' => date('Y-m-d H:i:s'),
            'bkm_Modified' => date('Y-m-d H:i:s'),
            'bkm_UGrpID' => get_user_id(),
            'bkm_AddedByImport' => 1);

        if (is_a($entry->getForeignPrototype(), 'HeuristZoteroEntry')) {
            $bkmk['bkm_ZoteroID'] = $entry->getForeignPrototype()->getZoteroID();
            $zoteroItems[$entry->getForeignPrototype()->getZoteroID()] = $entry->getBiblioID();
        }

        /* dead code
        if ($entry->getBkmkNotes()) {
        // pers_notes aren't visible in heurist any more
        // stick this stuff in the scratchpad instead
        //$bkmk['pers_notes'] = $entry->getBkmkNotes();
        }
        */
        mysql__insert('usrBookmarks', $bkmk);
        $bkm_ID = mysql_insert_id();

        $entry->setBookmarkID($bkm_ID);
        return true;
    }
}


function insert_tags(&$entry, $tag_map=array()) {
    // automatic tag insertion for the bookmark associated with this entry

    // easy one first: see if there is a workgroup tag to be added, and that we have access to that workgroup
    $wgKwd = $entry->getWorkgroupTag();
    if ($wgKwd) {
        $res = mysql_query("select * from usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where tag_UGrpID=ugl_GroupID and ugl_UserID=" . get_user_id() . " and tag_ID=" . $wgKwd);
        if (mysql_num_rows($res) != 1) $wgKwd = 0;// saw CHECK SPEC: can there be more than 1 , this code ingnores if 0 or more than 1
    }

    if (! $entry->getTags()) return;

    $tag_select_clause = '';
    foreach ($entry->getTags() as $tag) {
        $tag = str_replace('\\', '/', $tag);

        if (array_key_exists(strtolower(trim($tag)), $tag_map))
            $tag = $tag_map[strtolower(trim($tag))];

        if ($tag_select_clause) $tag_select_clause .= ',';
        $tag_select_clause .= '"'.mysql_real_escape_string($tag).'"';
    }
    // create user specific tagText to tagID lookup
    $res = mysql_query('select tag_ID, lower(trim(tag_Text)) from usrTags where tag_Text in (' . $tag_select_clause . ')'
        . ' and tag_UGrpID= ' . get_user_id());
    $tags = array();
    while ($row = mysql_fetch_row($res)) $tags[$row[1]] = $row[0];

    //now let's add in all the wgTags for this user's workgroups
    $all_wgTags = mysql__select_assoc('usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks, '.USERS_DATABASE.'.sysUGrps grp', 'lower(concat(grp.ugr_Name, "\\\\", tag_Text))', 'tag_ID',
        'tag_UGrpID=ugl_GroupID and ugl_GroupID=grp.ugr_ID and ugl_UserID='.get_user_id()); //saw CHECK SPEC: is it correct to import wgTags with a slash
    foreach ($all_wgTags as $tag => $id) $tags[$tag] = $id;

    $entry_tag_ids = array();
    foreach ($entry->getTags() as $tag) {
        if (preg_match('/^(.*?)\\s*\\\\\\s*(.*)$/', $tag, $matches)) {
            $tag = $matches[1] . '\\' . $matches[2];
            $wg_tag = true;
        } else $wg_tag = false;

        $tag_id = @$tags[strtolower(trim($tag))];
        if ($tag_id) array_push($entry_tag_ids, $tag_id);
        /**** 	"Don't insert new tags unannounced"
        Well, it's very very difficult to get feedback from the user, so we just won't insert any new tags at all, I guess.  Hope you're happy.
        */		else if (! $wg_tag) {	// do not insert new workgroup tags
            mysql_query('insert into usrTags (tag_UGrpID, tag_Text) ' .
                ' values ('.get_user_id().', "'.mysql_real_escape_string($tag).'")');
            $tag_id = mysql_insert_id();
            array_push($entry_tag_ids, $tag_id);
            $tags[strtolower(trim($tag))] = $tag_id;
        }

    }

    $kwi_insert_stmt = '';
    if ($wgKwd) {
        $kwi_insert_stmt .= '(' . $entry->getBiblioID() . ', ' . $wgKwd . ', 1)';
    }
    foreach ($entry_tag_ids as $tag_id) {
        if ($kwi_insert_stmt) $kwi_insert_stmt .= ',';
        $kwi_insert_stmt .= '(' . $entry->getBiblioID() . ', ' . $tag_id . ', 1)'; //FIXME getBookmarkID and getBiblioID return empty string
    }
    mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID, rtl_AddedByImport) values ' . $kwi_insert_stmt);
}


function process_author(&$field) {
    // field corresponds to a person: make sure they're in the database, and set the field's value to that per_id.
    // We will need to get fairly sophisticated about this eventually, but for now this quick hacque will do.

    $titleDT = (defined('DT_NAME')?DT_NAME:0);


    $person_bib_ids = array();

    $persons = parseName($field->getRawValue());
    foreach ($persons as $person) {
        if (@$person['anonymous']) {
            array_push($person_bib_ids, 'anonymous');
            continue;
        }
        if (@$person['others']) {
            array_push($person_bib_ids, 'et al.');
            continue;
        }
        if (@$person['questionable']) {
            // a "questionable" name -- e.g. one that could not be parsed, one that looks like an organisation etc.
            return NULL;
        }

        $res = mysql_query('select rec_ID from Records
            left join recDetails SURNAME on SURNAME.dtl_RecID=rec_ID and SURNAME.dtl_DetailTypeID=$titleDT
            left join recDetails GIVENNAMES on GIVENNAMES.dtl_RecID=rec_ID and GIVENNAMES.dtl_DetailTypeID=291
            where rec_RecTypeID = 75
            and SURNAME.dtl_Value = "'.mysql_real_escape_string(trim($person['surname'].' '.@$person['postfix'])).'"
            and GIVENNAMES.dtl_Value = "'.mysql_real_escape_string(trim($person['first names'])).'"');//MAGIC NUMBER


        if (mysql_num_rows($res) > 0) {
            // an exact match on the citation value: don't want to know if there's more than one person with this name!
            $rec_id = mysql_fetch_row($res);  $rec_id = $rec_id[0];
        } else {
            // no match -- insert a new person
            mysql_query('insert into Records (rec_Title, rec_RecTypeID, rec_FlagTemporary, rec_Modified, rec_Added) values ("'.mysql_real_escape_string(trim($person['surname'].' '.@$person['postfix']).', '.$person['first names']).'", 75, 1, now(), now())');
            $rec_id = mysql_insert_id();
            mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.$rec_id.', 160, "'.mysql_real_escape_string(trim($person['surname'].' '.@$person['postfix'])).'"),
                ('.$rec_id.', 291, "'.mysql_real_escape_string($person['first names']).'")');//MAGIC NUMBER
            mysql_query("update Records set rec_Hash = hhash(rec_ID) where rec_ID = $rec_id");
        }

        array_push($person_bib_ids, $rec_id);
    }

    $field->_value = $person_bib_ids;
}


function print_disambiguation_options(&$entry) {
    global $session_data;
    global $import_id;
    global $heurist_rectypes;
    if (! $heurist_rectypes) load_heurist_rectypes();

    if (count($session_data['ambiguities']) == 0) {
        // This is the first ambiguous record, print out a header
        ?>
        <br>
        <hr>
        <h1>Potential duplicates</h1>
        <div style="margin-left: 3ex;">
            <p><b>The following record(s) are potential duplicates.  Please choose the appropriate action by selecting the radio buttons below.</b></p>
            <p><a href="NEW record" onClick="return false;"><b>NEW</b></a> indicates the record being imported.  Selecting this will create a new record in Heurist.</p>
            <p>Numbers indicate an existing record ID in Heurist.<br>
                <span style="color: mediumvioletred;">Purple text flags any differences between the imported record and existing records.</span></p>
        </div>
        <?php
    }


    // generate a random number associated with this entry (it doesn't have an ID yet, remember?)
    $nonce = rand();
    $session_data['ambiguities'][$nonce] = &$entry;

    if ($entry->getPotentialMatches())
        $ambig_entry = &$entry;
    else
        $ambig_entry = &$entry->_ancestor;  //disambiguation searches the containment heirarchy for near matches and if found points to it _ancestor to it

    $entry_type = $heurist_rectypes[$entry->getReferenceType()]['rty_Name'];

    $compare_link = "interface/showSideBySidee.php?ids=" . $ambig_entry->getBiblioID() . "," . join(',', $ambig_entry->getPotentialMatches());
    ?>
    <hr>
    <div class="ambiguous">
        <?php if ($entry == $ambig_entry) { ?>
            <div><span style="font-weight: bold;"><?= $entry_type ?></span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=<?= $compare_link ?> onClick="var win = open(href, 'compare', 'width=600,height=300,scrollbars=1,resizable=1'); win.focus(); return false;">compare versions</a></div>
            <?php } else { ?>
            <div><span style="font-weight: bold;"><?= $heurist_rectypes[$ambig_entry->getReferenceType()]["rty_Name"] ?></span> of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=<?= $compare_link ?> onClick="var win = open(href, 'compare', 'width=600,height=300,scrollbars=1,resizable=1'); win.focus(); return false;">compare versions</a></div>
            <div style="margin-top: 1ex; margin-left: 3ex; background-color: #f0f0f0;"><?= htmlspecialchars($entry->getTitle()) ?> <i>(<?= $entry_type ?>)</i></div>
            <?php } ?>

        <table class="new_bib" style="margin: 1em; margin-bottom: 1ex;">
            <tr>
                <td style="text-align: right;"><b>NEW</b>&nbsp;</td>
                <td><input type="radio" name="ambig[<?= $nonce ?>]" value="-1" class="radio" id=<?= $nonce ?>></td>
                <td><label for=<?= $nonce ?>><b><?= htmlspecialchars($ambig_entry->getTitle()) ?></b></label></td>
            </tr>
            <?php
            $res = mysql_query('select rec_ID,rec_Title,
                new_levenshtein(rec_Hash,upper("'.mysql_real_escape_string($ambig_entry->getHHash()).'")) as diff1,
                new_levenshtein(upper(rec_Title),upper("'.mysql_real_escape_string($ambig_entry->getTitle()).'")) as diff2
                from Records where rec_ID in ('.join(',',$ambig_entry->getPotentialMatches()).') order by diff1, diff2');
            $is_first = true;
            while ($bib = mysql_fetch_assoc($res)) {
                $title_with_deltas = levenshtein_delta(strip_tags($bib['rec_Title']), strip_tags($ambig_entry->getTitle()));
                ?>
                <tr>
                    <td style="text-align: right;white-space:nowrap;"><?= $bib['rec_ID'] ?>&nbsp;</td>
                    <td><input type="radio" name="ambig[<?= $nonce ?>]" value="<?= $bib['rec_ID'] ?>" class="radio" <?= $is_first? "checked" : "" ?> id=<?= $bib['rec_ID'] . '-' . $nonce ?>>
                    <td><label for=<?= $bib['rec_ID'] . '-' . $nonce ?>><b title="<?= htmlspecialchars($bib['rec_Title']) ?>"><?= $title_with_deltas ?></b></label></td>
                </tr>

                <?php
                $is_first = false;
            }
            ?>
        </table>
    </div>
    <?php
}


function process_disambiguations() {
    global $session_data;

    set_progress_bar_title('Processing ambiguous entries');
    if (! @$session_data['ambiguities']) return;
    $i = 0;
    foreach (array_keys($session_data['ambiguities']) as $id) {
        $ambig_entry = &$session_data['ambiguities'][$id];
        if (! $ambig_entry->getPotentialMatches())
            $ambig_entry = &$ambig_entry->_ancestor;

        if (! @$_REQUEST['ambig'][$id]) {	// user hasn't chosen an option, let them sit there and fester
            $ambig_entry->_matches = array();
            continue;
        }

        $ambig_bib_id = $_REQUEST['ambig'][$id];
        if ($ambig_bib_id == -1) {	// user has selected NEW -- none of the presented options were correct
            // move the hitherto potential matches to DEFINITE NON-MATCHES
            $ambig_entry->eliminatePotentialMatches();
        } else {
            if (in_array($ambig_bib_id, $ambig_entry->getPotentialMatches())) {
                setPermanentBiblioID($ambig_entry, $ambig_bib_id);
                $ambig_entry->eliminatePotentialMatches();
            } else {	// funny buggers
                $ambig->_matches = array();
            }
        }

        update_progress_bar(++$i / count($session_data['ambiguities']));
    }
    update_progress_bar(-1);

    flush_fo_shizzle();
}


function flush_fo_shizzle() {
    // flush output for SURE
    @ob_flush();
    @flush();
}


function update_progress_bar($fraction) {
    // print out javascript to update the progress bar
    static $last_percent = NULL;

    $percent = 100*$fraction;
    if (intval($percent) == intval($last_percent)) return;

    print '<script type="text/javascript">importer.setProgress('.$percent.")</script>";
    flush_fo_shizzle();

    $last_percent = $percent;
}

function set_progress_bar_title($title) {
    // print out javascript to update the progress bar title
    print '<script type="text/javascript">importer.setProgressTitle(\''.addslashes($title)."')</script>";
    flush_fo_shizzle();
}


function setPermanentBiblioID(&$entry, $new_biblio_id) {
    // housekeeping stuff to be done when a permanent records match is found for some entry

    if ($entry->getBiblioID())
        merge_biblio($new_biblio_id, $entry->getBiblioID());
    $entry->setBiblioID($new_biblio_id);
    $entry->_permanent = true;
}


function print_tag_stuff(&$out_entries) {
    $tags = array();
    $orig_tags = array();
    $j = 0;
    foreach (array_keys($out_entries) as $i) {
        $my_tags = $out_entries[$i]->getTags();
        foreach ($my_tags as $tag) {
            $tag = trim($tag);
            if (! $tag) continue;
            if (! array_key_exists(strtolower($tag), $tags)) {
                $tags[strtolower($tag)] = true;
                array_push($orig_tags, $tag);
            }
        }
    }

    $query = "";
    foreach ($orig_tags as $tag) {
        if ($query) $query .= "', '";
        $query .= mysql_real_escape_string($tag);
    }
    $query = "select tag_Text from usrTags where tag_Text in ('" . $query . "')";
    $res = mysql_query($query);
    $existing_tags = array();
    while ($row = mysql_fetch_row($res)) {
        $tag = $row[0];
        $existing_tags[strtolower($tag)] = $tag;
    }

    $new_tags = false;
    foreach ($orig_tags as $i => $tag) {
        if (! @$existing_tags[strtolower($tag)]) {
            $new_tags = true;
            break;
        }
    }

    if ($orig_tags) {

        if ($new_tags) {
            ?>
            <p>The following tags appear in the import file, but aren't in your list of Heurist tags.<br>
                Un-selected tags will be ignored during the import process.</p>

            <p style="margin-left: 15px;">
                <?php
            }
            foreach ($orig_tags as $i => $tag) {
                if (@$existing_tags[strtolower($tag)]) {
                    print '<input type=hidden name=tags[] value="'.htmlspecialchars($tag).'">';
                    print '<input type=hidden name=orig_tags[] value="'.htmlspecialchars($tag).'">';
                    continue;
                }

                print '<div style="margin-left: 2em;">';
                print '<input type="hidden" name="tags[]" value="' . htmlspecialchars($tag) . '">';
                print '<label for=kwd-' . $i . '>';
                print   '<input type="checkbox" name="orig_tags[]" value="' . htmlspecialchars($tag) . '" id=kwd-' . $i . ' style="margin: 0; border: 0; padding: 0; margin-right: 5px;">';
                print   htmlspecialchars($tag);
                print '</label>';
                print "</div>\n";
            }

            if ($new_tags) {
                ?>

                <br clear=all>

                <input type=button value="Select all tags" onClick="var ok=document.getElementsByName('orig_tags[]'); for (var i=0; i < ok.length; ++i) ok[i].checked = true;">
                <input type=button value="Unselect all tags" onClick="var ok=document.getElementsByName('orig_tags[]'); for (var i=0; i < ok.length; ++i) ok[i].checked = false;">
            </p>

            <hr>
            <?php
        }
    }

    flush_fo_shizzle();
}


function setup_session_vars() {
    global $session_data;
    global $import_id;

    // print "<p><b>" . $_REQUEST['import_id'] . "</b>";
    // print '<i>' . print_r($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['heurist-import-' . $_REQUEST['import_id']], 1) . '</i></p>';
    if (!@$_REQUEST['import_id']  
            || !is_array(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']) 
            || ! @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['heurist-import-' . $_REQUEST['import_id']]) {
        $session_data = array();
        return;
    }

    $import_id = $_REQUEST['import_id'];
}


function initialise_import_session() {
    global $session_data;
    global $import_id;

    if (! @$_FILES['import_file']  ||  ! @$_FILES['import_file']['name']) return;

    $_import_id = substr($_FILES['import_file']['name'],0,200);
    // if the name has already been fiddled by heurist, get rid of our meddlings
    $_import_id = preg_replace('/-heurist-\\d+-\\d+_\\d+\\d+-\\d+:\\d+:\\d+.*/i', '', $_import_id);
    $_import_id = $_import_id.'-heurist-'.get_user_id().'-'.date('Y_m_d-H:i:s');

    if(!is_array(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'])){
        $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'] = array();    
    }
    $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["heurist-import-$_import_id"] = &$session_data;
    $session_data['in_filename'] = $_FILES['import_file']['name'];
    $import_id = $_import_id;
}

?>
