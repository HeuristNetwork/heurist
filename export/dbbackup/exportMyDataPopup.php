<?php

<?php

/**
 * exportMyDataPopup.php: Provides a user interface for exporting database data.
 *
 * This script allows database administrators to set up and initiate the export of
 * some or all data as an HML (Heurist Markup Language) file, SQL dump, TSV files,
 * or a complete ZIP/TAR archive including uploaded files and documentation.
 * It also supports uploading the generated archive to configured repositories (e.g., Nakala).
 *
 * The script operates in different modes:
 * - No mode (initial display): Shows the export options form.
 * - mode=1: Processes the export options submitted from the form.
 * - mode=2: Downloads the complete archived folder (ZIP/TAR).
 * - mode=3: Downloads the SQL dump only (ZIP/TAR).
 * - mode=4: Cleans up the backup folder (called via AJAX on window close).
 * - mode=5: Downloads the HML file only (ZIP/TAR).
 * - mode=6: Downloads the TSV folder only (ZIP/TAR).
 *
 * @package     HeuristWebService
 * @subpackage  Interface
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network Ltd.
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     5
 *
 * @uses $_REQUEST['mode'] Determines the operation mode (display form, process export, download, cleanup).
 * @uses $_REQUEST['is_zip'] If 1, forces ZIP format (though ZIP is default).
 * @uses $_REQUEST['is_tar'] If 1, forces TAR.BZ2 format. REMARK: UI for this option is currently commented out.
 * @uses $_REQUEST['repository'] Specifies the target repository for upload.
 * @uses $_REQUEST['repo_account'] Specifies the account to use for the repository.
 * @uses $_REQUEST['includeresources'] If 1, includes uploaded files in the archive.
 * @uses $_REQUEST['include_tilestacks'] If 1, includes tiled map images.
 * @uses $_REQUEST['include_hml'] If 1, includes HML export.
 * @uses $_REQUEST['include_tsv'] If 1, includes TSV export.
 * @uses $_REQUEST['include_docs'] If 1, includes background documentation (hidden, checked by default).
 * @uses $_REQUEST['allrecs'] If 1, includes resources from other users (hidden, checked by default).
 * @uses $_REQUEST['license'] Specifies the license for Nakala uploads.
 * @uses $_REQUEST['use_test_url'] For Nakala, if 1, uses the test Nakala instance.
 *
 * @const MANAGER_REQUIRED Indicates that manager-level access is required for this page.
 * @const PDIR Path to the parent directory, used for constructing URLs to JS/CSS assets.
 * @const FOLDER_BACKUP Path to the main backup folder for the current database.
 * @const FOLDER_SQL_BACKUP Path to the folder for storing standalone SQL backups.
 * @const FOLDER_HML_BACKUP Path to the folder for storing standalone HML backups.
 * @const FOLDER_TSV_BACKUP Path to the folder for storing standalone TSV backups.
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


/**
 * Indicates that manager-level access is required for this page.
 * @var int
 */
define('MANAGER_REQUIRED', 1);
/**
 * Relative path to the parent directory, used for JS/CSS links.
 * @var string
 */
define('PDIR','../../');

set_time_limit(0); // No time limit for this potentially long-running script.

// Required Heurist classes
use hserv\structure\ConceptCode;
use hserv\utilities\DbUtils;
use hserv\utilities\UArchive;
use hserv\utilities\DbExportTSV;

// Initialize the page (minimal version for popups)
require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
// For downloadFile function and other utilities
require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';

// Define paths for various backup folders
/**
 * Path to the main temporary backup folder for the current database.
 * @var string
 */
define('FOLDER_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME);
/**
 * Path to the temporary folder for storing a standalone SQL backup.
 * @var string
 */
define('FOLDER_SQL_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.'_sql');
/**
 * Path to the temporary folder for storing a standalone HML backup.
 * @var string
 */
define('FOLDER_HML_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.'_hml');
/**
 * Path to the temporary folder for storing a standalone TSV backup.
 * @var string
 */
define('FOLDER_TSV_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.'_tsv');

// --- Main script logic: Handle request parameters ---
$mode = @$_REQUEST['mode']; // Current operation mode
$format = 'zip'; // Default archive format
// REMARK: is_tar option is present in logic but UI checkbox is commented out.
if (array_key_exists('is_tar', $_REQUEST) && $_REQUEST['is_tar'] == 1) {
    $format = 'tar';
}

$mime = $format == 'tar' ? 'application/x-bzip2' : 'application/zip';
$is_repository = array_key_exists('repository', $_REQUEST); // True if a repository upload is intended

// --- Handle direct download or cleanup actions (modes > 1) ---
if ($mode > 1) {
    if ($format == 'tar') {
        $format = 'tar.bz2'; // TAR files are compressed with bzip2
    }

    if ($mode == '2' && file_exists(FOLDER_BACKUP.'.'.$format)) { // Download entire archived folder
        downloadFile($mime, FOLDER_BACKUP.'.'.$format); // downloadFile is from recordFile.php
    } elseif ($mode == '3' && file_exists(FOLDER_SQL_BACKUP.'.'.$format)) {  // Download archived SQL dump
        downloadFile($mime, FOLDER_SQL_BACKUP.'.'.$format);
    } elseif ($mode == '5' && file_exists(FOLDER_HML_BACKUP.'.'.$format)) {  // Download archived HML file
        downloadFile($mime, FOLDER_HML_BACKUP.'.'.$format);
    } elseif ($mode == '6' && folderExists(FOLDER_TSV_BACKUP, false)) {  // Download archived TSV subdirectory
        // REMARK: Assumes FOLDER_TSV_BACKUP gets zipped/tarred with its name + .$format
        downloadFile($mime, FOLDER_TSV_BACKUP.'.'.$format);
    } elseif ($mode == '4') {  // Cleanup backup folder (called on exit/cancel)
        folderDelete2(HEURIST_FILESTORE_DIR.DIR_BACKUP, false); // false = do not delete parent folder itself
    }
    exit; // Terminate script after download or cleanup
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Create data archive package</title>

<?php
        includeJQuery(); // Include jQuery library
?>
        <!-- Heurist Core JS Utilities -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <script type="text/text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; // Common CSS for Heurist pages ?>

        <script type=text/javascript>
            /**
             * @var {boolean} is_repository True if the page is loaded in repository upload mode.
             */
            var is_repository = <?php echo $is_repository ? 'true' : 'false';?>;
            /**
             * @var {object} complete_list_repositories Stores details of available repositories and accounts, populated by initRepositorySelector.
             */
            var complete_list_repositories = {};

            /**
             * Initializes UI elements and event handlers on document ready.
             */
            $(document).ready(function() {
                // Apply jQuery UI button styling
                $('input[type="submit"]').button();
                $('input[type="button"]').button();

                // Check if Heurist HAPI4 is available (newer interface)
                if (!window.hWin.HAPI4) {
                    $('#btnClose_1').hide(); // Hide close buttons if HAPI4 not available
                    $('#btnClose_2').hide();

                    if (is_repository) {
                        // Repository operations require HAPI4; redirect to info page if not available.
                        $('body').children().hide();
                        window.location = '<?php echo PDIR.'hclient/framecontent/infoPage.php?error='.rawurlencode('It is possible to perform this operation from Heurist admin interface only');?>';
                    }
                } else if (is_repository) {
                    // If in repository mode and HAPI4 is available, initialize the repository selector.
                    initRepositorySelector();
                }
            });

            /**
             * Cleans up the backup folder by making an AJAX call and then closes the window.
             * This is typically called when the user clicks a "Cancel" or "Close" button.
             */
            function closeArchiveWindow() {
                // Perform AJAX request to cleanup backup folder (mode=4)
                <?php print '$.ajax("'.HEURIST_BASE_URL.'/export/dbbackup/exportMyDataPopup.php?mode=4&db='.HEURIST_DBNAME.'");';?>
                window.close(); // Close the popup window
            }

            /**
             * Initializes the repository and account selection dropdowns.
             * Fetches available repositories via HAPI4 and populates the selectors.
             * Sets up event handlers for selection changes to dynamically update account options
             * and show/hide repository-specific fields (e.g., Nakala licenses).
             */
            function initRepositorySelector() {
                let $repos = $('#sel_repository'); // Repository dropdown
                let $accounts = $('#sel_accounts'); // Account dropdown

                if ($repos.length == 0 || $accounts.length == 0) {
                    return; // Exit if selectors are not found
                }

                $repos.empty(); // Clear existing options
                window.hWin.HEURIST4.ui.addoption($repos[0], '', 'select a repository...'); // Add default option

                // Fetch repository list from the server
                window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list', 'include_test': 1}, (response) => {
                    if (response.status != window.hWin.ResponseStatus.OK) {
                        window.hWin.HEURIST4.msg.showMsgErr(response); // Show error if API call fails
                        return;
                    }

                    // Process and group repositories
                    $.each(response.data, (idx, repo_details) => {
                        let repo_name = repo_details[1]; // Repository name (e.g., "Nakala")
                        repo_name = repo_name.charAt(0).toUpperCase() + repo_name.slice(1);

                        // repo_details[4] = true; // REMARK: Original code, purpose unclear without further context on repo_details structure.
                                                // This might be a flag or placeholder.

                        // Group accounts by repository name
                        if (Object.hasOwn(complete_list_repositories, repo_name)) {
                            complete_list_repositories[repo_name].push(repo_details);
                        } else {
                            complete_list_repositories[repo_name] = [repo_details];
                            window.hWin.HEURIST4.ui.addoption($repos[0], repo_name, repo_name); // Add unique repository name to dropdown
                        }
                    });

                    $accounts.parent().hide(); // Hide account dropdown initially

                    // Initialize custom HSelect for repository dropdown
                    window.hWin.HEURIST4.ui.initHSelect($repos, false, {width: '150px', 'margin-left': '5px'}, {
                        onSelectMenu: () => { // Event handler for when a repository is selected
                            let value = $repos.val(); // Selected repository name

                            $accounts.empty(); // Clear account options
                            $('[class*="repo-"]').hide(); // Hide all repository-specific sections

                            if (value == '') { // No repository selected
                                $accounts.parent().hide();
                                return;
                            }

                            $accounts.parent().show(); // Show account dropdown
                            window.hWin.HEURIST4.ui.addoption($accounts[0], '', 'Select an account to use...');

                            $(`.repo-${value}`).show(); // Show specific section for this repository (e.g., Nakala options)

                            // Populate account dropdown for the selected repository
                            let accounts_data = complete_list_repositories[value];
                            $.each(accounts_data, (idx, details) => {
                                // details[0] is account ID, details[3] is account label/name
                                let lbl = `${details[0].indexOf('_') >= 0 ? '' : 'Test - '}${details[3]}`;
                                window.hWin.HEURIST4.ui.addoption($accounts[0], details[0], lbl);
                            });

                            if ($accounts.hSelect('instance') !== undefined) {
                                $accounts.hSelect('refresh'); // Refresh HSelect if already initialized
                            }

                            // If Nakala is selected, fetch licenses
                            if (value == 'Nakala') {
                                getNakalaLicenses();
                            }
                        }
                    });

                    // Initialize custom HSelect for account dropdown
                    window.hWin.HEURIST4.ui.initHSelect($accounts, false, {width: '200px', 'margin-left':'5px'}, {
                        onSelectMenu: () => { // Event handler for when an account is selected
                            let value = $accounts.val(); // Selected account ID
                            // Show "setup keys" link if it's a test account (heuristic: ID doesn't contain '_')
                            value != '' && value.indexOf('_') == -1 ? $('.setup-keys').show() : $('.setup-keys').hide();
                        }
                    });
                });
            }

            /**
             * Fetches and populates the license selector specifically for the Nakala repository.
             * This is called when "Nakala" is selected in the repository dropdown.
             */
            function getNakalaLicenses() {
                let $sel_license = $('#sel_license'); // License dropdown for Nakala

                // Avoid re-fetching if already initialized and populated
                if ($sel_license.attr('data-init') == 'Nakala' && $sel_license.find('option').length > 1) {
                    return;
                }

                let request = {
                    serviceType: 'nakala',
                    service: 'nakala_get_metadata',
                    type: 'licenses'
                };

                window.hWin.HEURIST4.msg.bringCoverallToFront($('body')); // Show loading overlay

                // API call to fetch Nakala licenses
                window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {
                    window.hWin.HEURIST4.msg.sendCoverallToBack(); // Hide loading overlay
                    data = window.hWin.HEURIST4.util.isJSON(data); // Ensure data is JSON

                    if (data.status && data.status != window.hWin.ResponseStatus.OK) {
                        window.hWin.HEURIST4.msg.showMsgErr({ // Show error message
                            message: 'An error occurred while attempting to retrieve the licenses for Nakala records, however the archiving process can still be completed.',
                            error_title: 'Unable to retrieve Nakala licenses',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                        $sel_license.parent().parent().hide(); // Hide license section
                        return;
                    }

                    if (data.length > 0) { // Licenses found
                        $.each(data, (idx, license) => { // Populate dropdown
                            window.hWin.HEURIST4.ui.addoption($sel_license[0], license, license);
                        });
                        window.hWin.HEURIST4.ui.initHSelect($sel_license, false, {'margin-left': '21px'}); // Initialize HSelect
                        $sel_license.attr('data-init', 'Nakala'); // Mark as initialized
                        $sel_license.parent().parent().show(); // Show license section
                    } else { // No licenses found or other error
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'An unknown error has occurred while attempting to retrieve the licenses for Nakala records, however the archiving process can still be completed.',
                            error_title: 'No Nakala licenses found',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                        $sel_license.parent().parent().hide(); // Hide license section
                    }
                });
            }

            /**
             * Handles the click event for the "Create Archive" or "Export & Upload" button.
             * Validates repository selections if applicable, shows a loading message,
             * and submits the form to initiate the backend archive creation process.
             */
            function exportArchive() {
                // Validate repository and account selection if in repository mode
                if (is_repository) {
                    let repo = $('#sel_repository').val();
                    let acc = $('#sel_accounts').val();
                    if (repo == '') {
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a repository...', 2000);
                        return;
                    } else if (acc == '') {
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select an account to use...', 2000);
                        return;
                    } else if (acc.indexOf('nakala') >= 0 && $('#sel_license').val() == '') { // Nakala specific: license required
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a license...', 2000);
                        return;
                    }
                }

                // Show "Preparing archive file..." overlay message
                if (window.hWin.HAPI4) {
                    window.hWin.HEURIST4.msg.bringCoverallToFront(null, null, 'Preparing archive file...');
                }

                // Hide buttons and submit the form to trigger backend processing (mode=1)
                document.getElementById('buttons').style.visibility = 'hidden';
                document.forms[0].submit(); // This will reload the page with mode=1
            }
        </script>
    </head>
    <body class="popup ui-heurist-admin">

        <?php
        // $please_advise is defined but not consistently used. It could be appended to error messages.
        $please_advise = "<br>Please consult with your system administrator for a resolution.";

        // --- Display initial form if $mode is not set (i.e., initial page load) ---
        if (!$mode) {
            ?>
            <!-- Initial form for selecting export options -->
            <h3 class="ui-heurist-title">This function is available to database adminstrators only (therefore you are a database administrator!)</h3>
            <p>The data will be exported as a fully self-documenting HML (Heurist XML) file, as a complete MySQL SQL data dump,
            as textual and wordprocessor descriptions of the structure of Heurist and of your database, and as a directory of
            attached files (image files, video, XML, maps, documents etc.) which have been uploaded or indexed in situ.
            </p>
            <p>The MySQL dump will contain the complete database which can be reloaded on any up-to-date MySQL database server.
            </p>
            <?php if (!$is_repository) { ?>
            <!-- Information for non-repository export -->
            <p>The output of the process will be made available as a link to a downloadable zip file
            <br>but is also available to the system adminstrator as a file in the backup directory in the database.
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases with large numbers of images or very large files such as high
            <br>resolution maps or video may bog down the server and the zip file may be too big to download.
            <br>In that case you may need to ask your sysadmin to give you the files separately on a USB drive.</p>
            <?php } else { ?>
            <!-- Information for repository export -->
            <p>The output of the process will be zipped and uploaded to the selected repository
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases and including lots of images or video may bog down the server and the file may not upload to the repository
            - in that case it may be better to ask your sysadmin to give you the files separately on a USB drive and attempt to upload it yourself.</p>
            <?php } ?>
            <p>Attached files may be omitted by unchecking the first checkbox.
            <br>This may also be useful for databases with lots of attached files which are already backed up elsewhere.
            </p>

            <!-- Export options form -->
            <form name='f1' action='exportMyDataPopup.php' method='get'>
                <input name='db' value='<?php echo HEURIST_DBNAME; ?>' type='hidden'>
                <input name='mode' value='1' type='hidden'> <!-- Submit form in mode=1 to process options -->

                <!-- Checkbox options for including different data types -->
                <div class="input-row" style="padding-top:10px">
                    <label>
                        <input type="checkbox" name="includeresources" value="1">
                        Include attached (uploaded) files eg. images (essential for full database archive).
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds all tilestacks that have been uploaded to Heurist">
                        <input type="checkbox" name="include_tilestacks" value="1">
                        Include tiled map images - these are typically very large and are probably already available elsewhere
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds fully self-documenting HML (Heurist XML) file">
                        <input type="checkbox" name="include_hml" value="1">
                        Include HML (not required for transfer to a new server, but recommended for long-term archive)
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds a folder containing the database dump in TSV format">
                        <input type="checkbox" name="include_tsv" value="1">
                        Include database dump in TSV format (includes a complete record export in TSV format)
                    </label>
                </div>

                <!-- REMARK: BZip format option is commented out in HTML as of 2024-04-09, but PHP logic for 'tar' still exists. -->
                <!-- 2024-04-09 - we use solely zip
                <div class="input-row">
                    <label title="Export / Upload the archive in BZip format, instead of Zip">
                        <input type="checkbox" name="is_tar" value="1">
Use BZip format rather than Zip (BZip is more efficient for archiving, but Zip is faster if there are lot of images and easier to open on personal computers)
                    </label>
                </div>
                -->

                <!-- Hidden options, checked by default -->
                <div class="input-row" style="display:none;">
                    <label
                        title="Adds documents describing Heurist structure and data formats - check this box if the output is for long-term archiving">
                        <input type="checkbox" name="include_docs" value="1" checked>
                        Include background documentation for archiving
                    </label>
                </div>
                <div class="input-row" style="display: none;">
                    <label>
                        <input type="checkbox" name="allrecs" value="1" checked>
                        Include resources from other users (everything to which I have access)
                    </label>
                </div>

                <!-- Repository selection section (only shown if $is_repository is true) -->
                <?php if ($is_repository) { ?>
                <div class="input-row" style="padding: 20px 0 5px 0;">
                    <span>Select a repository <select id='sel_repository' name='repository'></select> </span>
                </div>

                <div class="input-row" style="padding: 10px 0px;">
                    <span>
                        Select an account
                        <select id='sel_accounts' name='repo_account'></select>
                        <span class="heurist-helper1 setup-keys" style="vertical-align: middle;display: none;">
                            This key is for a test account, you can setup your own key at Design > External repositories
                        </span>
                    </span>
                </div>

                <!-- Nakala-specific options -->
                <div id="nakala-url" class="input-row repo-Nakala" style="padding: 10px 0px; display: none;">
                    <span>
                        Select which version of Nakala to use: <br><br>
                        <label style="display: inline-block; margin-bottom: 5px;"> <input type='radio' name='use_test_url' value='0' checked="checked"> Standard</label> <br>
                        <label style="display: inline-block; margin-bottom: 5px;"> <input type='radio' name='use_test_url' value='1'> Test (test.nakala.fr)</label>
                        <span class="heurist-helper1" style="vertical-align: middle;">
                            Please note that this version should only be used for testing / temporary storage as at any moment the uploaded Zip can be cleared by Nakala/Huma-num
                        </span>
                    </span>
                </div>
                <div class="input-row repo-Nakala" style="display: none;padding: 5px 0 10px 0;">
                    <span>
                        Select a license
                        <select id='sel_license' name='license'> <option value="">select a license...</option> </select>
                    </span>
                </div>
                <?php } ?>

                <!-- Action buttons -->
                <div id="buttons" class="actionButtons" style="padding-top:10px;text-align:left">
                    <input type="button" value="<?php echo $is_repository ? 'Export & Upload' : 'Create Archive';?>"
                        style="margin-right: 20px;" class="ui-button-action" onClick="exportArchive();">
                    <input type="button" id="btnClose_1" value="Cancel" onClick="closeArchiveWindow();">
                </div>
            </form>
            <?php
        // --- Process export request (mode=1) ---
        } else {
            $operation_in_progress = 'It appears that backup operation has been started already. Please try this function later';

            // Check for existing backup operation lock
            if (!isActionInProgress('exportDB', 2, HEURIST_DBNAME)) { // 2 minutes lock
                report_message($operation_in_progress, false); // False = not an error, just info
            } else {
                echo_flush2("<br>Beginning archive process<br>"); // Send progress to client
            }

            // Determine if separate archives for SQL, HML, TSV should be created
            $separate_sql_zip = !$is_repository;
            $separate_hml_zip = !$is_repository && @$_REQUEST['include_hml'] == '1';
            $separate_tsv_zip = !$is_repository && @$_REQUEST['include_tsv'] == '1';

            // --- Prepare backup folders ---
            if (file_exists(FOLDER_BACKUP)) { // Main backup folder
                echo_flush2("<br>Clear folder ".FOLDER_BACKUP."<br>");
                $res = folderDelete2(FOLDER_BACKUP, true); // true = recursive delete
                if (!$res) { report_message($operation_in_progress, false); } // Show in-progress if delete fails
            }
            if (!folderCreate(FOLDER_BACKUP, true)) {
                report_message('Failed to create folder '.FOLDER_BACKUP.'<br> in which to create the backup. Please consult your sysadmin.', true);
            }

            if (file_exists(FOLDER_SQL_BACKUP)) { // SQL-only backup folder
                $res = folderDelete2(FOLDER_SQL_BACKUP, true);
                if (!$res) { report_message($operation_in_progress, false); }
            }
            if ($separate_sql_zip && !folderCreate(FOLDER_SQL_BACKUP, true)) {
                $separate_sql_zip = false; // Disable option if folder creation fails
            }

            if ($separate_hml_zip && !folderCreate(FOLDER_HML_BACKUP, true)) { // HML-only backup folder
                $separate_hml_zip = false;
            }
            if ($separate_tsv_zip && !folderCreate(FOLDER_TSV_BACKUP, true)) { // TSV-only backup folder
                $separate_tsv_zip = false;
            }
            // Folder for TSV output within the main backup package
            if (@$_REQUEST['include_tsv'] == 1 && !folderCreate(FOLDER_BACKUP . '/tsv-output/records', true)) {
                $_REQUEST['include_tsv'] = 0; // Disable TSV if subfolder creation fails
                echo_flush2("Failed to create sub directory for TSV output within backup directory<br>");
            }

            // Validate repository if specified
            $repo = !empty(@$_REQUEST['repository']) ? htmlspecialchars($_REQUEST['repository']) : null;
            if ($is_repository && (!$repo || $repo != 'Nakala')) { // Currently only Nakala seems fully supported
                // REMARK: CONTACT_HEURIST_TEAM constant is used here but not defined in this file.
                report_message('The repository ' . $repo . ' is not supported please ' . (defined('CONTACT_HEURIST_TEAM') ? CONTACT_HEURIST_TEAM : 'contact the support team'), true, false);
            }

            // --- Collect files and folders to include in the archive ---
            $folders_to_copy = [];
            $copy_uploaded_files = (@$_REQUEST['includeresources'] == '1');

            if (@$_REQUEST['include_docs'] == '1') { // Include system documentation folders
                $folders_to_copy = folderSubs(HEURIST_FILESTORE_DIR,
                    array('backup', 'scratch', 'generated-reports', 'file_uploads', 'filethumbs',
                          'tileserver', 'uploaded_files', 'uploaded_tilestacks', 'rectype-icons',
                          'term-images', 'webimagecache', 'blurredimagescache')); // Exclude these
                echo_flush2("<br><br>Exporting system folders<br>");
            }
            
            // Include custom user media folders if defined in settings
            $user_media_folders_str = $system->settings->get('sys_MediaFolders');
            if (!empty($user_media_folders_str)) {
                $user_media_folders = explode(';', $user_media_folders_str);
                foreach ($user_media_folders as $dir) {
                    $dir = basename(trim($dir));
                    if (empty($dir) || $dir == 'backup') continue;

                    $path = HEURIST_FILESTORE_DIR . $dir;
                    if (!file_exists($path)) continue;

                    $path_with_slash = rtrim($path, '/') . '/';
                    if ($copy_uploaded_files) {
                       folderRecurseCopy($path_with_slash, FOLDER_BACKUP.'/'.$dir);
                    }
                    // Exclude from $folders_to_copy if already handled
                    $key = array_search($path_with_slash, $folders_to_copy);
                    if ($key !== false) {
                       unset($folders_to_copy[$key]);
                    }
                }
            }


            if ($copy_uploaded_files) { // Include standard uploaded files and thumbs
                if (defined('HEURIST_FILES_DIR')) $folders_to_copy[] = HEURIST_FILES_DIR;
                if (defined('HEURIST_THUMB_DIR')) $folders_to_copy[] = HEURIST_THUMB_DIR;
                $copy_files_in_root = true; // Copy files from the root of HEURIST_FILESTORE_DIR
            } else {
                $copy_files_in_root = false;
            }

            if (@$_REQUEST['include_tilestacks'] == '1' && defined('HEURIST_TILESTACKS_DIR')) {
                $folders_to_copy[] = HEURIST_TILESTACKS_DIR;
            }

            // Perform recursive copy of selected folders
            if (@$_REQUEST['include_docs'] == '1' || $copy_uploaded_files) {
                folderRecurseCopy(HEURIST_FILESTORE_DIR, FOLDER_BACKUP, $folders_to_copy, $copy_files_in_root);
            }

            if (@$_REQUEST['include_docs'] == '1') { // Include Heurist application documentation
                echo_flush2('Copy documentation/context_help folder<br>');
                folderRecurseCopy(HEURIST_DIR.'documentation/context_help/', FOLDER_BACKUP.'/documentation/context_help/');
            }

           // Remove database definition cache files from backup
           fileDelete(FOLDER_BACKUP.'/entity/db.json'); // Old name
           fileDelete(FOLDER_BACKUP.'/entity/dbdef_cache.json');

           // --- HML Export ---
           if (@$_REQUEST['include_hml'] == '1') {
               echo_flush2("Exporting database as HML (Heurist Markup Language = XML)<br>(may take several minutes for large databases)<br>");

               // Set parameters for flathml.php script
               if (@$_REQUEST['allrecs'] != "1") { // Export records owned by current user
                   $userid = $system->getUserId();
                   $q_param = "owner:$userid";
                   $_REQUEST['depth'] = '5';
               } else { // Export all records
                   $q_param = "sortby:-m"; // Sort by modification date descending
                   $_REQUEST['depth'] = '0'; // Full depth
                   $_REQUEST['linkmode'] = 'none';
               }
               $_REQUEST['w'] = 'all';    // All record types
               $_REQUEST['a'] = '1';      // Include annotations
               $_REQUEST['q'] = $q_param; // Query
               $_REQUEST['rev'] = 'no';   // Do not include reverse pointers
               $_REQUEST['filename'] = '1'; // Save to file (flathml.php handles actual saving to FOLDER_BACKUP)

               $to_include = dirname(__FILE__).'/../../export/xml/flathml.php';
               if (is_file($to_include)) {
                   include_once $to_include; // Execute HML export script
               }

               // If separate HML zip is requested, copy the generated XML file
               if (file_exists(FOLDER_BACKUP.'/'.HEURIST_DBNAME.'.xml') && $separate_hml_zip) {
                   $separate_hml_zip = fileCopy(FOLDER_BACKUP.'/'.HEURIST_DBNAME.'.xml', FOLDER_HML_BACKUP."/".HEURIST_DBNAME.".xml");
               }
           }

           // --- TSV Export ---
           if (@$_REQUEST['include_tsv'] == '1') {
                echo_flush2("Exporting database records as TSV<br>(may take several minutes for large databases)<br>");
                $dbExportTSV = new DbExportTSV($system);
                // REMARK: The DbExportTSV class from a previous task did not have an `output()` method.
                // This might be a call to a non-existent method or `DbExportTSV` is more complex than the snippet seen.
                // Assuming `output()` generates files into FOLDER_BACKUP . '/tsv-output/'.
                $warns = $dbExportTSV->output(); // This should generate files in FOLDER_BACKUP . '/tsv-output/'
                if (!empty($warns)) {
                    echo_flush2(implode('<br>', $warns));
                }

                // If separate TSV zip, copy the generated TSV files
                $separate_tsv_zip = $separate_tsv_zip && folderSize2(FOLDER_BACKUP . "/tsv-output") > 0;
                if ($separate_tsv_zip) {
                    $separate_tsv_zip = folderRecurseCopy(FOLDER_BACKUP . '/tsv-output', FOLDER_TSV_BACKUP);
                }
            }

           // --- Export Database Structure Definitions ---
           echo_flush2("Exporting database definitions as readable text<br>");
           $url_txt = HEURIST_BASE_URL . "hserv/structure/export/getDBStructureAsSQL.php?db=".HEURIST_DBNAME."&pretty=1";
           saveURLasFile($url_txt, FOLDER_BACKUP."/Database_Structure.txt");

           echo_flush2("Exporting database definitions as XML<br>");
           $url_xml = HEURIST_BASE_URL . "hserv/structure/export/getDBStructureAsXML.php?db=".HEURIST_DBNAME;
           saveURLasFile($url_xml, FOLDER_BACKUP."/Database_Structure.xml");

           // --- SQL Dump ---
           if ($system->isAdmin()) { // Only admins can perform full SQL dump
                echo_flush2("Exporting SQL dump of the whole database (several minutes for large databases)<br>");
                $database_dumpfile = FOLDER_BACKUP."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql";
                $dump_options = array('skip-triggers' => true,
                                      'single-transaction' => true,
                                      'quick' =>true,
                                      'add-drop-trigger' => false, 'no-create-db' =>true, 'add-drop-table'=>true);

                $res_dump = DbUtils::databaseDump(HEURIST_DBNAME_FULL, $database_dumpfile, $dump_options, false);

                if (!$res_dump) {
                    // REMARK: `DIV_E` is likely a constant for an error div, not defined here.
                    // Assuming it's defined in initPageMin.php or similar.
                    if (defined('DIV_E')) print DIV_E;
                    report_message("Sorry, unable to generate MySQL database dump. ".$system->getErrorMsg().'  '.$please_advise, true, true);
                }

                if ($separate_sql_zip) { // Copy SQL dump for separate archive
                    $separate_sql_zip = fileCopy($database_dumpfile, FOLDER_SQL_BACKUP."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql");
                }
           }

           // Remove old style SQL dump file if it exists (named with HEURIST_DBNAME_FULL)
           if (file_exists(FOLDER_BACKUP.'/'.HEURIST_DBNAME_FULL.'.sql')) {
               unlink(FOLDER_BACKUP.'/'.HEURIST_DBNAME_FULL.'.sql');
           }

           // --- Create Archives (ZIP/TAR.BZ2) ---
           echo_flush2('<br>Zipping files<br>');
           $destination = FOLDER_BACKUP.'.'.$format; // Path for the main archive
           if (file_exists($destination)) unlink($destination);
           // Ensure old tar.bz2 is removed if format changed to zip for the same base name
           if ($format == 'zip' && file_exists(FOLDER_BACKUP.'.tar.bz2')) unlink(FOLDER_BACKUP.'.tar.bz2');
           if ($format == 'tar' && file_exists(FOLDER_BACKUP.'.zip')) unlink(FOLDER_BACKUP.'.zip');


           if ($format == 'zip') {
               $res_archive = UArchive::zip(FOLDER_BACKUP, null, $destination, true); // true = delete original folder after zipping
           } else { // tar.bz2
               $res_archive = UArchive::createBz2(FOLDER_BACKUP, null, $destination, true);
           }

           if ($res_archive === true) { // Main archive creation successful
                $res_sql_archive = false;
                if ($separate_sql_zip) { // Create separate SQL archive
                    $destination_sql = FOLDER_SQL_BACKUP.'.'.$format;
                    if (file_exists($destination_sql)) unlink($destination_sql);
                    if ($format == 'zip') {
                        $res_sql_archive = UArchive::zip(FOLDER_SQL_BACKUP, null, $destination_sql, true);
                    } else {
                        $res_sql_archive = UArchive::createBz2(FOLDER_SQL_BACKUP, null, $destination_sql, true);
                    }
                }

                $res_hml_archive = false;
                if ($separate_hml_zip) { // Create separate HML archive
                    $destination_hml = FOLDER_HML_BACKUP.'.'.$format;
                    if (file_exists($destination_hml)) unlink($destination_hml);
                    if ($format == 'zip') {
                        $res_hml_archive = UArchive::zip(FOLDER_HML_BACKUP, null, $destination_hml, true);
                    } else {
                        $res_hml_archive = UArchive::createBz2(FOLDER_HML_BACKUP, null, $destination_hml, true);
                    }
                }

                $res_tsv_archive = false;
                if ($separate_tsv_zip) { // Create separate TSV archive
                    $destination_tsv = FOLDER_TSV_BACKUP.'.'.$format;
                    if (file_exists($destination_tsv)) unlink($destination_tsv);
                    if ($format == 'zip') {
                        $res_tsv_archive = UArchive::zip(FOLDER_TSV_BACKUP, null, $destination_tsv, true);
                    } else {
                        $res_tsv_archive = UArchive::createBz2(FOLDER_TSV_BACKUP, null, $destination_tsv, true);
                    }
                }

                // --- Display Download Links or Upload to Repository ---
                if (!$is_repository) { // Provide download links
                    $param_format = ($format == 'tar' || $format == 'tar.bz2') ? '&is_tar=1' : '&is_zip=1'; // Keep original tar/zip param for download URL
                    $display_format = ($format == 'tar' || $format == 'tar.bz2') ? 'tar.bz2' : 'zip';
    ?>
    <!-- Download links section -->
    <p>Your data has been backed up in <?php echo htmlspecialchars(FOLDER_BACKUP);?></p>
    <br><br><div class='lbl_form'></div> <!-- Label placeholder? -->
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>.<?php echo $display_format; ?>?mode=2&db=<?php echo HEURIST_DBNAME.$param_format;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download your data as a <?php echo $display_format;?> archive</a>

    <?php
    if ($separate_sql_zip) {
        if ($res_sql_archive === true) { ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_sql.<?php echo $display_format; ?>?mode=3&db=<?php echo HEURIST_DBNAME.$param_format;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the SQL <?php echo $display_format;?> file only</a>
        <span class="heurist-helper1">(for db transfer on tiered servers)</span>
    <?php } else { ?>
        <br><br>
        <div>Failed to create standalone SQL dump. <?php echo htmlspecialchars(is_string($res_sql_archive) ? $res_sql_archive : '');?></div>
    <?php
        }
    }
    if ($separate_hml_zip) {
        if ($res_hml_archive === true) { ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_hml.<?php echo $display_format; ?>?mode=5&db=<?php echo HEURIST_DBNAME.$param_format;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the HML <?php echo $display_format;?> file only</a>
    <?php } else { ?>
        <br><br>
        <div>Failed to create / set up a standalone HML file. <?php echo htmlspecialchars(is_string($res_hml_archive) ? $res_hml_archive : '');?></div>
    <?php
        }
    }
    if ($separate_tsv_zip) {
        if ($res_tsv_archive === true) { ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_tsv.<?php echo $display_format; ?>?mode=6&db=<?php echo HEURIST_DBNAME.$param_format;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the TSV <?php echo $display_format;?> folder only</a>
    <?php } else { ?>
        <br><br>
        <div>Failed to create / set up a standalone TSV folder. <?php echo htmlspecialchars(is_string($res_tsv_archive) ? $res_tsv_archive : '');?></div>
    <?php
        }
    }
    ?>
    <p class="heurist-helper1">
    Note: If this file fails to download properly (eg. "Failed … file incomplete") the file is too large to download. Please ask your system administrator (<?php echo defined('HEURIST_MAIL_TO_ADMIN') ? HEURIST_MAIL_TO_ADMIN : 'your admin'; ?>) to send it to you via a large file transfer service.
    </p>
    <br>
    <input type="button" id="btnClose_2" class="ui-button-action" value="Close" onClick="closeArchiveWindow();" style="margin-top: 10px;">

    <?php
                } elseif ($is_repository) { // Upload to repository
                    $repo_account = htmlspecialchars($_REQUEST['repo_account']);
                    $display_format = ($format == 'tar' || $format == 'tar.bz2') ? 'tar.bz2' : 'zip';

                    // REMARK: user_getRepositoryCredentials2 function is external to this file.
                    $repo_details_all = user_getRepositoryCredentials2($system, $repo_account);
                    $repo_details = $repo_details_all[$repo_account] ?? null;


                    echo_flush2('<hr><br>Uploading archive to ' . htmlspecialchars($repo) . '...');

                    if ($repo_details === null || empty($repo_details['params']['writeApiKey'])) {
                        $msg = $repo_details === null ?
                                'Credentials for specified repository and user/group not found.' : 'Write Credentials for specified repository and user/group not defined.';
                        $msg .= ' Please check the credentials within Design > External repositories.';
                        report_message($msg, true, true);
                    } elseif ($repo == 'Nakala') { // Nakala specific upload logic
                        $date = date('Y-m-d');
                        $params = [];
                        $params['file'] = [
                            'path' => FOLDER_BACKUP . '.' . $display_format, // Path to the generated archive
                            'type' => $mime,
                            'name' => HEURIST_DBNAME . '.' . $display_format
                        ];

                        // Metadata for Nakala
                        $params['meta']['title'] = [
                            'value' => 'Archive of ' . HEURIST_DBNAME . ' on ' . $date, 'lang' => null,
                            'typeUri' => XML_SCHEMA, 'propertyUri' => NAKALA_REPO.'terms#title'
                        ];
                        $usr = $system->getCurrentUser();
                        if (is_array($usr) && !empty($usr['ugr_FullName'])) {
                            $params['meta']['creator'] = [
                                'value' => $usr['ugr_FullName'], 'lang' => null,
                                'typeUri' => XML_SCHEMA, 'propertyUri' => 'http://purl.org/dc/terms/creator'
                            ];
                        }
                        $params['meta']['created'] = [
                            'value' => $date, 'lang' => null,
                            'typeUri' => XML_SCHEMA, 'propertyUri' => NAKALA_REPO.'terms#created'
                        ];
                        $params['meta']['type'] = [ // Default type: Dataset
                            'value' => 'http://purl.org/coar/resource_type/c_ddb1', 'lang' => null,
                            'typeUri' => 'http://www.w3.org/2001/XMLSchema#anyURI', 'propertyUri' => NAKALA_REPO.'terms#type'
                        ];
                        if (array_key_exists('license', $_REQUEST) && !empty($_REQUEST['license'])) {
                            $params['meta']['license'] = [
                                'value' => $_REQUEST['license'], 'lang' => null,
                                'typeUri' => XML_SCHEMA, 'propertyUri' => NAKALA_REPO.'terms#license'
                            ];
                        }

                        $params['api_key'] = $repo_details['params']['writeApiKey'];
                        // REMARK: Original logic `strpos($repo_account,'nakala') === 1` seems potentially fragile.
                        // A more robust check might be needed if account naming conventions change.
                        // Assuming test accounts might not have '_' or have a specific prefix.
                        $params['use_test_url'] = (strpos($repo_account, '_') === false); // Heuristic: if no underscore, it's a test account.
                        if (isset($_REQUEST['use_test_url'])) { // Allow override from form if provided
                            $params['use_test_url'] = intval($_REQUEST['use_test_url']);
                        }


                        $params['status'] = 'pending'; // Keep new record private initially
                        $params['return_type'] = 'editor'; // Return link to the editor interface

                        // REMARK: uploadFileToNakala function is external to this file.
                        $rtn_upload = uploadFileToNakala($system, $params);

                        if ($rtn_upload === false) {
                            $rtn_msg = $system->getErrorMsg();
                            echo_flush2('failed<br>');
                        } else {
                            echo_flush2('finished<br>');
                            $rtn_msg = htmlspecialchars($rtn_upload);
                            $rtn_msg = 'The uploaded archive is at <a href="' . $rtn_msg . '" target="_blank">'
                                        . $rtn_msg . '&nbsp;<span class="ui-icon ui-icon-extlink"></span> </a>';
                        }
                        echo_flush2('<br>'. $rtn_msg .'<br>');
                    } else { // Other repositories not supported for direct upload
                        report_message('The repository ' . htmlspecialchars($repo) . ' is not supported for direct upload by this script. Please ' . (defined('CONTACT_HEURIST_TEAM') ? CONTACT_HEURIST_TEAM : 'contact the support team'), true, true);
                    }
                }
                report_message('', false, true); // Final message, performs cleanup
            } else { // Main archive creation failed
                report_message(htmlspecialchars(is_string($res_archive) ? $res_archive : 'Archive creation failed.') .'<br>Try different archive format otherwise please consult system adminstrator', true, true);
            }
        }
        ?>
    </body>
</html>

<?php
/**
 * Displays a status or error message to the user and performs cleanup operations.
 *
 * @param string $message The message to display. If empty, only cleanup is performed.
 * @param bool $is_error True if the message is an error, false for an informational message.
 *                       This affects the styling of the message box.
 * @param bool $need_cleanup True to perform cleanup operations (delete temporary folders, release lock).
 * @return void
 */
function report_message($message, $is_error = true, $need_cleanup = false)
{
    global $system; // Access to the global $system object for HEURIST_DBNAME

    if ($need_cleanup) {
        if (array_key_exists('repository', $_REQUEST)) {
            // Cleanup entire backup super-directory after successful repository upload
            folderDelete2(HEURIST_FILESTORE_DIR.DIR_BACKUP, false);
        } else {
            // Cleanup temporary folders for individual downloads
            // REMARK: Main FOLDER_BACKUP is deleted by UArchive::zip/createBz2 if successful and last param is true.
            // These lines ensure specific SQL/HML/TSV folders are removed if they were created for separate zips.
            if (defined('FOLDER_BACKUP')) folderDelete2(FOLDER_BACKUP, true);
            if (defined('FOLDER_SQL_BACKUP')) folderDelete2(FOLDER_SQL_BACKUP, true);
            if (defined('FOLDER_HML_BACKUP')) folderDelete2(FOLDER_HML_BACKUP, true);
            if (defined('FOLDER_TSV_BACKUP')) folderDelete2(FOLDER_TSV_BACKUP, true);
        }
        // Release the action lock
        isActionInProgress('exportDB', -1, HEURIST_DBNAME);
    }

    if ($message) { // Display message if provided
?>
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">
            <div class="<?php echo $is_error ? 'ui-state-error' : 'ui-state-highlight'; // Use highlight for info ?>"
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon <?php echo $is_error ? 'ui-icon-alert' : 'ui-icon-info';?>"
                      style="float: left; margin-right:.3em;font-weight:bold"></span>
                <?php echo $message; // Message is already expected to be HTML or escaped ?>
            </div>
        </div>
<?php
    }
?>
        <!-- Ensure loading overlay is hidden -->
        <script>if(window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg){ window.hWin.HEURIST4.msg.sendCoverallToBack(true);}</script>
    </body>
</html>
<?php
    exit; // Ensure script terminates after reporting message
}
?>