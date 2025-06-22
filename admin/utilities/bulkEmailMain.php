<?php
/**
* bulkEmailMain.php - Main user interface for the Heurist Bulk Email utility.
*
* @fileOverview This script generates the HTML page that users interact with to send bulk emails.
*               It requires System Administrator privileges to access. The page allows users to:
*               - Filter and select target databases based on criteria like record count and last modification date.
*               - Select target user groups (owners, managers, all users, etc.).
*               - Choose an "Email" record from the current database to use as a template for subject and body.
*               - Edit the email subject and body (with WYSIWYG editor support).
*               - Preview user counts and database counts.
*               - Initiate the email sending process (handled by `bulkEmailController.php` and `bulkEmailSystem.php`).
*               - Export a CSV of targeted users and databases.
*               It handles cases where the "Email" record type (2-9) might be missing and prompts for its download.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

define('PDIR','../../');//need for proper path to js and css

require_once __DIR__ . '/../../autoload.php';

use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once __DIR__ . '/../../hclient/framecontent/initPageMin.php';
require_once __DIR__ . '/bulkEmailSystem.php';

// Retrieve the System Administrator password securely.
$sysadminPwd = USanitize::getAdminPwd();
$req_params = USanitize::sanitizeInputArray();

// Handle CSV export functionality.
if (isset($req_params["exportCSV"]) && $req_params["exportCSV"] == 1) {
    if ($system->verifyActionPassword($sysadminPwd, $passwordForServerFunctions)) {
        echo "The System Administrator password is invalid, please re-try in the previous tab/window.";
    } else {
        getCSVDownload($req_params); // Trigger CSV download if verification succeeds.
    }
    exit;
}

// Check for required parameters and verify the system password.
if (!isset($req_params['db']) || $system->verifyActionPassword($sysadminPwd, $passwordForServerFunctions)) {
    echo '<h3>A Heurist database and Server Manager password are required to enter this function.</h3>';
    exit;
}

// Retrieve the mysqli object for database operations.
$mysqli = $system->getMysqli();

$emails = [];
$hasEmails = false;

// Get the current database name.
$currentDb = HEURIST_DB_PREFIX . htmlspecialchars($_REQUEST['db']);

// Retrieve the record type ID for "Email".
$emailRecTypeId = ConceptCode::getRecTypeLocalID("2-9");

if (empty($emailRecTypeId)) {
    includeJQuery();
    ?>

    <!-- Include styles and scripts -->
    <link rel="stylesheet" type="text/css" href="../../h4styles.css">
    <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>
    <script type="text/javascript" src="../../hclient/core/hapi.js"></script>
    <script type="text/javascript" src="../../hclient/core/HSystemMgr.js"></script>
    <script type="text/javascript" src="../../hclient/core/recordset.js"></script>
    <script type="text/javascript" src="../../hclient/core/utils.js"></script>
    <script type="text/javascript" src="../../hclient/core/utils_dbs.js"></script>
    <script type="text/javascript" src="../../hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="../../hclient/core/utils_msg.js"></script>

    <script>
        // Initialize the HAPI4 library if not already available.
        if (!window.hWin.HAPI4 && typeof hAPI === 'function') {
            window.hWin.HAPI4 = new hAPI('<?php echo HEURIST_DBNAME; ?>', $.noop);
        }

        $(document).ready(() => {
            window.hWin.HAPI4.EntityMgr.refreshEntityData('rty');

            // Handle the download link click event.
            $('a').on('click', () => {
                window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('2-9', 2, false, () => {
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'The Email record type has been downloaded.<br><br>'
                        + 'You will now need to create an email record and then you can return to this function (simply refresh the page once the new record has been saved).',
                        null,
                        {title: 'Email record type downloaded successfully'}
                    );
                });
            });
        });
    </script>

    <?php
    // Display user instructions for downloading the required record type.
    echo "Unable to retrieve the ID for the Email record type.<br><br>"
       . "You can choose to download the required record type <a href='#'>here</a>, or,<br>"
       . "manually download it from the Heurist Core Definitions database, available via Design > Browser templates.<br><br>"
       . "Afterwards, you will need to create a new email record to use the bulk mailer, then you can simply refresh this page (<strong>once the record has been saved</strong>).";
    exit;
}

// Build the query to retrieve email records.
$query = "
    SELECT rec_ID, rec_Title 
    FROM Records 
    WHERE rec_RecTypeID = ? 
      AND rec_Title != '' 
      AND rec_Title IS NOT NULL 
      AND rec_FlagTemporary != 1
";

// Prepare the statement to prevent SQL injection.
$stmt = $mysqli->prepare($query);
$ERR_EMAIL = "Unable to retrieve Email records from the current database. Please try again later.";

if (!$stmt) {
    // Log the error and display a user-friendly message.
    error_log("Failed to prepare statement: " . $mysqli->error);
    echo $ERR_EMAIL;
    exit;
}

// Bind the email record type ID as a parameter.
$stmt->bind_param('i', $emailRecTypeId);

// Execute the statement.
if (!$stmt->execute()) {
    // Log the error and display a user-friendly message.
    error_log("Failed to execute query: " . $stmt->error);
    echo $ERR_EMAIL;
    exit;
}

// Fetch the result set.
$result = $stmt->get_result();

if (!$result) {
    // Log the error and display a user-friendly message.
    error_log("Failed to fetch result set: " . $stmt->error);
    echo $ERR_EMAIL;
    exit;
}

// Process the email records.
while ($email = $result->fetch_assoc()) {
    if (empty($email['rec_Title'])) {
        continue; // Skip records with an empty title.
    }

    $emails[$email['rec_ID']] = $email['rec_Title']; // Map ID to title.
    $hasEmails = true;
}

if (empty($emails)) {
    $safeDb = htmlspecialchars($currentDb, ENT_QUOTES, 'UTF-8'); // Sanitize database name
    print "<br><br>This function sends bulk emails based on text in a selected <i>Email</i> record.<br><br>"
    ."<strong>" . $safeDb . " contains no valid Email records.</strong><br><br>"
    . "<strong>Please create an Email record in the database containing the text<br>"
    . "you want to send out, using ##xxxx## markers for values to be inserted.</strong><br><br>"
    . "The Email record to be used must contain a title field and a short summary field - the latter will be used as the email's body. The title and body can be edited before sending. <br>"
    . "If you want to create your email on-the-fly, simply create a dummy record with placeholders for title and body to enable this function. <br><br>"
    . "Placeholders that will be replaced with proper values (case insensitive):<br><br>"
    . "##firstname## > User's First Name,<br>"
    . "##lastname## > User's Last Name,<br>"
    . "##email## > User's Email,<br>"
    . "##database## > Database Name,<br>"
    . "##dburl## > Database URL,<br>"
    . "##records## > Record Count, and<br>"
    . "##lastmodified## > Date of the Last Modified Record<br>";
    exit;
}

// Free resources.
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Heurist System Email</title>

        <!-- Style Sheets -->
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<?php
        includeJQuery();
?>

        <link rel="stylesheet" type="text/css" href="../../h4styles.css">
        <link rel="stylesheet" type="text/css" href="../../h6styles.css">

        <!-- Scripts -->
        <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>

        <script type="text/javascript" src="../../hclient/core/utils.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>

        <!-- Inner Styling and Script -->
        <style type="text/css">

            .label {
                font-size: 14px;

                height: 20px;

                padding: 5px;
            }
            .label:nth-child(odd) {
                background:-moz-linear-gradient(center top, #FFFFFF, #EEEEEE) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#FFFFFF), to(#EEEEEE));
            }
            .label:nth-child(odd):hover {
                background:-moz-linear-gradient(center top, #EFEFEF, #DDDDDD) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#EFEFEF), to(#DDD));
            }
            .label:nth-child(even) {
                background:-moz-linear-gradient(center top, #EDF5FF, #EDF5FF) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#EDF5FF), to(#EDF5FF));
            }

            .instruction {
                font-size: 0.9em;
            }

            input[type=number] {
                font-size: 0.9em;

                margin: 2px;

                width: 70px;
            }

            #recModifiedLogic-button {
                min-width: 8em !important;

                margin-right: 5px;
            }

            #recModifiedSel-button {
                min-width: 7em !important;

                margin-left: 5px;
            }

            #recModifiedSel-button > .ui-selectmenu-text {
                padding-right: 0px;
            }

            /* Active State CSS */
            span.ui-button.ui-state-active {
                color: #000 !important;
                background: #F6F6F6 !important;
            }

            div.ui-state-active {
                color: #000 !important;
                background: #DDDDDD !important;
            }

            /* Misc */
            .non-selectable {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;

                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }

            .t-row {
                display: block;

                margin: 10px 0px 30px 0px;
            }

            .l-col {
                float: left;

                min-width: 15%;
                max-width: 25%;
            }

            #dbArea {
                max-height: 1px;
            }

            #dbSelection {
                overflow-y: auto;

                max-height: 75%;
            }

            .r-col {
                float: left;

                max-width: 70%;

                margin: 20px 0px 0px 65px;
            }

            span.truncate {
                display: inline-block;

                max-width: 250px;

                vertical-align: middle;
            }

        </style>

        <script type="text/javascript">
            /**
             * @fileOverview Inline JavaScript for the Bulk Email Main interface (bulkEmailMain.php).
             * This script handles UI interactions, form validation, AJAX calls to the
             * bulkEmailController.php, and dynamic updates to the page.
             * @author Brandon McKay <blmckay13@gmail.com>
             * @author Ian Johnson <ian.johnson.heurist@gmail.com>
             */

            window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>');

            var all_emails = <?php echo json_encode($emails)?>;// Object of Email records id->title

            const BASE_URL = "<?php echo HEURIST_BASE_URL ?>";
            const CURRENT_DB = "<?php echo $currentDb ?>";
            var getting_databases = false; // Flag for database retrieval operation in progress; true - general, 1 - intial list, false - none
            var run_filter = false;
            var isFormSubmit = false;

            const handled_sort = ['name', 'rec_count', 'last_update'];
            var database_details = null; // [{name: db_name, rec_count: db_rec_count, last_update: db_last_update}, ...]

            /**
             * Gets the list of currently selected (checked) databases from the UI.
             * Updates the hidden 'db_list' input field with a comma-separated string of selected database names.
             * @returns {Array<string>} An array of selected database names (with HEURIST_DB_PREFIX).
             */
            function getDbList(){

                var checked_dbs = $("#dbSelection").find(".dbListCB:checked");
                var dbs = [];
                checked_dbs.each(function(idx, ele){
                    dbs.push($(ele).attr("id"));
                });

                //input[name="databases"]
                $('#db_list').val(dbs.join(','));

                return dbs;
            }

            /**
             * Gets a list of all databases currently displayed in the UI's selection area,
             * regardless of their checked state.
             * @returns {Array<string>} An array of all database names (with HEURIST_DB_PREFIX) in the list.
             */
            function getAllDbs() {

                var dbs = [];

                var checked_dbs = $("#dbSelection").find(".dbListCB");
                checked_dbs.each(function(idx, ele){
                    dbs.push($(ele).attr("id"));
                });

                return dbs;
            }

            /**
             * Handles the CSV export functionality.
             * Validates the form, sets a flag for CSV export, submits the form,
             * and then resets the flag.
             * @param {Event} e The click event object from the export button.
             * @returns {boolean} Returns false to prevent default form submission behavior.
             */
            function doExportCSV(e) {

                if(!validateForm(e)) {
                    return false;
                }

                //prevent dbl click
                if(isFormSubmit){
                    return;
                }
                isFormSubmit = true;

                $("input[name='exportCSV']").val(1);

                getDbList();
                $("#emailOptions").trigger('submit');


                $("input[name='exportCSV']").val('');

                setTimeout('isFormSubmit=false', 5000);

                return false;
            }

            /**
             * Validates the main email options form.
             * Checks for required fields: database selection, email title, email body, and admin password.
             * Also validates numeric inputs for record count and last modified period.
             * Displays a flash message with errors if validation fails.
             * @param {Event} e The event object, typically from a submit or button click.
             * @returns {boolean} True if the form is valid, false otherwise.
             */
            function validateForm(e) {

                var isValid = true;

                var err_text = "The following actions are required:<br><br>";
                var messages = {
                    "dbs": "Select at least one database for use<br>",
                    "workgroups": "Select at least one workgroup for use<br>",
                    "title": "Please enter a Email Title<br>",
                    "body": "Please enter a Email Body<br>",
                    "pwd": "Enter the System Admin password to proceed<br>",
                    "invalid_count": "Record count needs to be a non-negative number<br>",
                    "invalid_period": "Last modified amount needs to be a non-negative number higher than one<br>"
                };

                var $dbSel = $("#dbSelection");
                var $emailTitle = $("#emailTitle");
                var $emailBody = $("#emailBody");
                var $recCount = $("#recTotal");
                var $lmPeriod = $("#recModified");

                if(!$dbSel.find("input[type='checkbox']").is(":checked")){

                    err_text += messages["dbs"];
                    isValid = false;
                }

                if(window.hWin.HEURIST4.util.isempty($emailTitle.val())){

                    isValid = false;
                    err_text += messages["title"];
                }

                if(window.hWin.HEURIST4.util.isempty($emailBody.val())){

                    isValid = false;
                    err_text += messages["body"];
                }

                if($recCount.val() < 0){

                    isValid = false;
                    err_text += messages["invalid_count"];
                }

                if($lmPeriod.val() <= 0 && !$lmPeriod.attr("disabled")){

                    isValid = false;
                    err_text += messages["invalid_period"];
                }

                if(window.hWin.HEURIST4.util.isempty($("#sm_pwd").val())){

                    err_text += messages["pwd"];
                    isValid = false;
                }

                if(!isValid) {
                    window.hWin.HEURIST4.msg.showMsgFlash(err_text, 5000);
                }

                return isValid;
            }

            /**
             * Populates the database selection area in the UI.
             * Creates checkboxes for each database and sets up event handlers for selection
             * and double-click. Initializes database and user counts to zero.
             * @param {Array<string>} dbs An array of database names (with HEURIST_DB_PREFIX) to display.
             * @returns {void}
             */
            function setupDBSelection(dbs) {

                var $db_selection = $("#dbSelection");

                if(window.hWin.HEURIST4.util.isempty(dbs)){
                    window.hWin.HEURIST4.msg.showMsgFlash("There are no databases based on the filters");
                    $("#filterMsg").hide();
                    return;
                }

                $.each(dbs, function(key, value) {

                    var name = value.substring(4);

                    $db_selection.append(
                        "<div class='label non-selectable' title='"+ name +"'> "
                        + '<label><input type="checkbox" class="dbListCB" id="'+value+'" value="'+ value +'"><span class="truncate">' + name + "</span></label>"
                        + '<label data-id="'+value+'"></label>'
                      + '</div>'
                    );
                });

                $db_selection.find("div")
                    .on('dblclick', function(e){

                        if($(e.target).is('div')){

                            $(e.target).find('input').trigger('click');
                        }
                    });

                $("#dbArea").find("#allDBs")
                    .on("click", function(e){

                        var is_checked = $(e.target).is(":checked");

                        $db_selection.find(".dbListCB").prop("checked", is_checked);

                        getDBCount();
                        getUserCount();
                    })
                    .css("vertical-align", "middle");

                $db_selection.find(".dbListCB").on("change", () => {
                    getDBCount();
                    getUserCount();
                });

                $("#dbCount").text("0");
                $("#userCount").text("0");
                $("#filterMsg").hide();
            }

            /**
             * Sorts the displayed list of databases in the UI.
             * The sorting is based on the `database_details` global array and the selected sort order.
             * Reorders the DOM elements in the #dbSelection container.
             * @param {string} [order='name'] The field to sort by: 'name', 'rec_count', or 'last_update'.
             *                                Defaults to 'name' or the currently checked radio button.
             * @returns {void}
             */
            function applyDBSort(order = 'name') {

                if(getting_databases){
                    setTimeout(() => {
                        applyDBSort(order);
                    }, 2000);
                    return;
                }

                let $db_list = $('#dbSelection');

                if(!database_details || database_details.length == 0){ // TODO: attempt another retrieval
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Unable to apply sort order to database list, there were no databases found/provided.',
                        error_title: 'Database sorting failed'
                    });
                    return;
                }

                if(!order){
                    order = $('input[name="dbSortBy"]:checked').attr('id');
                }
                if(!order || window.hWin.HEURIST4.util.isempty(order) || !handled_sort.includes(order)){
                    order = 'name';
                }

                if($db_list.attr('data-order') == order){
                    return;
                }

                $db_list.attr('data-order', order);

                // Sort database_details
                database_details.sort((a, b) => {

                    let a_item = a[order];
                    let b_item = b[order];

                    if(order == 'name'){ // to lower case first
                        a_item = a_item.toLowerCase();
                        b_item = b_item.toLowerCase();
                    }

                    if(order == 'rec_count'){
                        return a_item - b_item;
                    }/*else{
                        return a_item < b_item;
                    }*/
                    if(a_item < b_item){
                        return -1;
                    }else if(a_item > b_item){
                        return 1;
                    }
                    return 0;
                });

                let $prev_child = null;

                for(let i = 0; i < database_details.length; i++){

                    const name = database_details[i]['name'];
                    let $ele = $db_list.find('input[id="'+ name +'"]');

                    if($ele.length == 0){
                        continue;
                    }

                    $ele = $ele.parent().parent();
                    if($prev_child){
                        $ele.insertAfter($prev_child);
                        $prev_child = $ele;
                    }else{
                        $ele.prependTo($db_list);
                        $prev_child = $ele;
                    }
                }
            }

            /**
             * Sets up the user selection dropdown menu.
             * Populates it with options for user types (Owner, Manager, Admin, All Users)
             * and attaches an event handler to update user counts on change.
             * @returns {void}
             */
            function setupUserSelection() {

                var $user_selection = $('#userSelection');

                var select = $("<select>")
                    .attr("name", "users")
                    .attr("id", "userSel")
                    .appendTo($user_selection);

                var options = [
                    {key:"owner", title:"Database Owner/s", selected: true},
                    {key:"manager", title:"Administrators - Database Managers"},
                    {key:"admin", title:"Administrators - All Workgroups"},
                    {key:"user", title:"All Users"}
                ];

                window.hWin.HEURIST4.ui.createSelector(select.get(0), options);

                if(select.hSelect("instance")!=undefined) {
                    select.hSelect("widget").css({"margin-top": "5px", "min-width": "15em", "width": "310px"});
                }

                $("<span id='wg-error' class='error-msg non-selectable' style='width: 234px;'></span>").appendTo($user_selection).hide();

                select.on("change", getUserCount);
            }

            /**
             * Sets up the email template selection dropdown menu.
             * Populates it with "Email" records from the current database (titles from `all_emails` global).
             * Attaches an event handler to fetch and display email details (title and body) when a template is selected.
             * @returns {void}
             */
            function setupEmailSelection() {

                var $email_selection = $("#emailOutline");

                var options = [
                    {key:"null", title: "Select a email record..."},
                ];

                $.each(all_emails, function(idx, value){

                    var opt = {key: idx, title: value};

                    options.push(opt);
                });

                window.hWin.HEURIST4.ui.createSelector($email_selection.get(0), options);

                $email_selection.on({
                    change: function(event) {

                        var emailDraft = $(event.target).val();

                        if (emailDraft == null || emailDraft == "null") {
                            $("#emailTitle").text("");
                            $("#emailBody").text("");
                        } else {
                            getEmailDetails(emailDraft);
                        }
                    }
                });
            }

            /**
             * Sets up event handlers and initial states for other UI elements.
             * This includes the "Last Modified" filter controls and the "Apply Filter" and "Send Emails" buttons.
             * @returns {void}
             */
            function setupOtherElements() {

                var modifySel = $("#recModifiedSel");
                var modifyLogic = $("#recModifiedLogic");

                window.hWin.HEURIST4.util.setDisabled($("#recModified"), true);
                window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic-button"), true);

                modifySel.on({
                    change: function(event, data){

                        if($(event.target).val()==='ALL') {
                            window.hWin.HEURIST4.util.setDisabled($("#recModified"), true);

                            window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic"), true);
                        } else {
                            window.hWin.HEURIST4.util.setDisabled($("#recModified"), false);

                            window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic"), false);
                        }
                    }
                });

                $("#btnApply").on({
                    click: function(event, data) {

                        if(getting_databases){
                            run_filter = getting_databases == 1;
                            window.hWin.HEURIST4.msg.showMsgFlash('Please wait for the database list to update...', 5000);
                            return;
                        }

                        let cont_height = $('#dbSelection').height() + 100;
                        let cont_width = $('.l-col').width() + 50;
                        let cont_top = $('.l-col').position().top;

                        getting_databases = true;
                        window.hWin.HEURIST4.msg.bringCoverallToFront($('.l-col'), {top: `${cont_top}px`, 'max-height': `${cont_height}px`, width: `${cont_width}px`, color: 'white', opacity: 0.8}, 'Appling database filter...');

                        $("#dbSelection").find(".dbListCB").off("change");
                        $("#dbSelection").empty();
                        $('#allDBs').prop('checked', false).trigger('change');

                        $("#filterMsg").show().text("Filtering Databases...");

                        var data = {
                            a: 'list_databases',
                            db: CURRENT_DB,
                            db_filtering: {
                                count: $("#recTotal").val(),
                                lastmod_logic: $("#recModifiedLogic").val(),
                                lastmod_period: $("#recModified").val(),
                                lastmod_unit: $("#recModifiedSel").val()
                                //filterIncompleteDesc: $("#filterIncompleteDesc").is(":checked") ? 1 : 0 // New filter
                            },
                            req_id: window.hWin.HEURIST4.util.random()
                        }

                        $.ajax({
                            url: 'bulkEmailController.php',
                            type: 'POST',
                            data: data,
                            dataType: 'json',
                            cache: false,
                            xhrFields: {
                                withCredentials: true
                            },
                            //fail:
                            error: function(jqXHR, textStatus, errorThrown){

                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An error has occurred with retrieving the filtered list of databases.<br>"
                                        + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                        + "Please contact the Heurist team if this problem persists",
                                    error_title: 'Unable to retrieve database list',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            },
                            //done:
                            success: function(response, textStatus, jqXHR){

                                if(response.status == "ok"){
                                    setupDBSelection(response.data);
                                    applyDBSort($('input[name="dbSortBy"]:checked').attr('id'));

                                } else {

                                    if(window.hWin.HEURIST4.util.isempty(response.message)){
                                        window.hWin.HEURIST4.msg.showMsgErr({
                                            message: "An unknown error has occurred with retrieving the filtered list of databases.",
                                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                        });
                                    } else {
                                        var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                        window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve database list'});
                                    }
                                }
                            },
                            //always:
                            complete: function(jqXHR, textStatus){

                                getting_databases = false;
                                window.hWin.HEURIST4.msg.sendCoverallToBack();

                                getUserCount();

                                if(textStatus == 'success'){
                                    $("#filterMsg").text('Database Filtering is Completed, Loading List');
                                }else{
                                    $("#filterMsg").text('An error has occurred!');
                                }
                            }
                        });
                    }
                });

                $("#btnEmail").on("click", function(event){
                    if(validateForm(event)){
                        getDbList();
                        $("input[name='exportCSV']").val('');
                        sendEmails();
                        return false;
                    }
                });

                $('.dbSort').on('change', function(event){
                    let order = $('input[name="dbSortBy"]:checked').attr('id');
                    applyDBSort(order);
                });

                $('input[id="name"]').prop('checked', true);
            }

            /**
             * Initiates the process of sending emails.
             * Serializes the form data, makes an AJAX request to the controller's 'send_emails' action,
             * and displays a progress dialog that polls for updates.
             * @returns {void}
             */
            function sendEmails(){

                const SESSION_ID = window.hWin.HEURIST4.util.random();
                let params = {};
                let $prog_dlg;
                let interval;

                $('#emailOptions').serializeArray().reduce((params, value) => {

                    if(window.hWin.HEURIST4.util.isempty(value['value'])){
                        value['value'] = 0;
                    }

                    params[value['name']] = value['value'];
                    return params;
                }, params);

                params['a'] = 'send_emails';
                params['sessionID'] = SESSION_ID;

                let mail_url = `${BASE_URL}admin/utilities/bulkEmailController.php`;

                window.hWin.HEURIST4.util.sendRequest(mail_url, params, null, (response) => {

                    if(interval > 0) { clearInterval(interval); interval = null; }

                    $prog_dlg.parent().find('.ui-dialog-titlebar button').show();
                    $prog_dlg.parent().find('.ui-dialog-buttonpane').show();

                    if(response?.data === 'terminated'){
                        $prog_dlg.find('#email-results').html('<strong>CANCELLED</strong>');
                        return;
                    }

                    window.hWin.HEURIST4.util.sendRequest(mail_url, {a: 'session', session: SESSION_ID, db: CURRENT_DB}, null, (session_resp) => {

                        if(session_resp.status == 'ok'){
                            $prog_dlg.find('#progress-report').html(session_resp.data);
                        }

                        $prog_dlg.find('#email-results').html(`<strong>Saved final receipt as a Note record: ID #${response.data} ${response.rec_Title}</strong>`);
                    });
                });

                $prog_dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                    '<div id="progress-report" style="padding-bottom: 10px;"></div><div id="email-results" style="padding-top: 10px; border-top: 1px solid black;"></div>',
                    null,
                    {title: 'Email progress tracker'}
                );
                $prog_dlg.parent().find('.ui-dialog-titlebar button').hide();
                $prog_dlg.parent().find('.ui-dialog-buttonpane').hide();

                let progress_url = `${BASE_URL}hserv/controller/progress.php`;

                interval = setInterval(() => {

                    let request = { t: new Date().getMilliseconds(), session: SESSION_ID, db: CURRENT_DB };

                    window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, (response) => {
                        if(response.message != 'terminate'){
                            $prog_dlg.find('#progress-report').html(response.message);
                        }else{
                            if(interval > 0) { clearInterval(interval); interval = null; }
                            return;
                        }
                    });
                }, 1000);

            }

            /**
             * Fetches the initial complete list of databases from the server.
             * Populates `database_details` and calls `setupDBSelection` on success.
             * Handles potential filtering if `run_filter` is true after the initial load.
             * @returns {void}
             */
            function getInitDbList() {

                getting_databases = 1;

                $.ajax({
                    url: 'bulkEmailController.php',
                    type: 'POST',
                    data: { a: 'list_databases', db: CURRENT_DB, db_filtering: "all", req_id: window.hWin.HEURIST4.util.random() },
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    //fail:
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the complete list of databases.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Unable to retrieve database list',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    },
                    //done:
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            database_details = response.data.details;
                            setupDBSelection(response.data.list);
                            //applyDBSort('name'); already in alphabetic order by default

                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving the complete list of databases, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve database list',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve database list'});
                            }
                        }
                    },
                    complete: function(jqXHR, textStatus){
                        getting_databases = false;

                        if(textStatus == 'success' && run_filter){
                            run_filter = false;
                            $("#btnApply").trigger('click');
                        }
                    }
                });
            }

            /**
             * Retrieves the title and body for a selected email template record.
             * Makes an AJAX call to the controller's 'email_details' action.
             * Updates the email title input and TinyMCE editor content on success.
             * @param {(string|number)} id The ID of the "Email" record to fetch details for.
             * @returns {void}
             */
            function getEmailDetails(id) {

                $.ajax({
                    url: 'bulkEmailController.php',
                    type: 'POST',
                    data: { a: 'email_details', db: CURRENT_DB, recid: id, req_id: window.hWin.HEURIST4.util.random() },
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the Email record short summary field (email body).<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to retrieve email details'
                        });
                    },
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            $("#emailTitle").val(response.data[0]);
                            if(tinyMCE.activeEditor){
                                tinyMCE.activeEditor.setContent(response.data[1]);
                            }else{
                                $("#emailBody").text(response.data[1]);
                            }

                            return;
                        }

                        if(window.hWin.HEURIST4.util.isempty(response.message)){
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: "An unknown error has occurred with retrieving email record details, please contact the Heurist team.",
                                error_title: 'Unable to retrieve email details',
                                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                            });
                        } else {
                            var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                            window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve email details'});
                        }
                    }
                });
            }

            /**
             * Displays record counts next to each database in the list and updates the total record count for selected databases.
             * @param {(Object|Array)} data Either an object mapping database names (prefixed) to record counts,
             *                            or an array of database detail objects (from `database_details`).
             *                            If empty, uses `database_details` global.
             * @returns {void}
             */
            function displayRecordCount(data) {

                if(window.hWin.HEURIST4.util.isempty(data)){
                    data = database_details;
                }
                if(window.hWin.HEURIST4.util.isempty(data)){
                    return;
                }

                // Update individual record counts + update total record count for selected databases
                let $db_list = $("#dbSelection");

                let selected_dbs = getDbList();
                let total = 0;

                $.each(data, (db, count) => {

                    if($.isPlainObject(count)){
                        db = count['name'];
                        count = count['rec_count'];
                    }

                    let $ele = $db_list.find('[data-id="'+ db +'"]');

                    if($ele.length > 0){

                        let max_width = $ele.parent().width() - 30;
                        $ele.text('[' + count + ']').css({'float': 'right', 'padding-left': '5px'});

                        if(count > 0 && selected_dbs.indexOf(db) >= 0){
                            total += parseInt(count, 10);
                        }
                    }
                });

                $("#allDBs").parent().parent().find('span').show();
                $("#recCount").text(total);

                set_element_position();
            }

            /**
             * Retrieves and displays the record count for all listed databases.
             * If `database_details` already contains record counts, it uses that data directly.
             * Otherwise, makes an AJAX call to the 'record_count' action.
             * @returns {void}
             */
            function getRecordCount() {

                if(getting_databases){
                    window.hWin.HEURIST4.msg.showMsgFlash('Please wait for the database list to update...', 3000);
                    return;
                }

                if(window.hWin.HEURIST4.util.isArrayNotEmpty(database_details) && Object.hasOwn(database_details[0], 'rec_count')){
                    displayRecordCount();
                    return;
                }

                let dbs = getAllDbs();

                if(dbs.length == 0){
                    return;
                }

                var data = {
                    a: 'record_count',
                    db: CURRENT_DB,
                    db_list: dbs,
                    req_id: window.hWin.HEURIST4.util.random()
                };

                $.ajax({
                    url: 'bulkEmailController.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: (jqXHR, textStatus, errorThrown) => {

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the the record count for the selected databases.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to retrieve record count'
                        });
                    },
                    success: (response, textStatus, jqXHR) => {

                        if(response.status == "ok"){
                            displayRecordCount(response.data);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving the record counts, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve record counts',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve record count'});
                            }
                        }
                    }
                })
            }

            /**
             * Counts the number of selected databases and updates the corresponding label in the UI.
             * @returns {void}
             */
            function getDBCount() {

                const $sel_dbs = $("#dbSelection").find(".dbListCB:checked");
                $("#dbCount").text($sel_dbs.length);
            }

            /**
             * Retrieves and displays the count of distinct users based on the selected databases and user type filter.
             * Makes an AJAX call to the 'user_count' action.
             * @returns {void}
             */
            function getUserCount() {

                if(getting_databases){
                    window.hWin.HEURIST4.msg.showMsgFlash('Please wait for the database list to update...', 3000);
                    return;
                }

                var dbs = getDbList();

                if(dbs.length == 0){
                    $("#userCount").text('0');
                    return;
                }

                var data = {
                    a: 'user_count',
                    db: CURRENT_DB,
                    user_count: $("#userSel").val(),
                    db_list: dbs.join(','),
                    req_id: window.hWin.HEURIST4.util.random()
                };

                $.ajax({
                    url: 'bulkEmailController.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the the user count for the selected databases and user type.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to retrieve user count'
                        });
                    },
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            $("#userCount").text(response.data);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving user counts, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve user count',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve user counts'});
                            }
                        }
                    }
                });
            }

            /**
             * Adjusts the position of the "Send Emails" and "Export CSV" buttons
             * based on the position of the password input field.
             * @returns {void}
             */
            function set_element_position(){
                $("#btnEmail")
                    .position({
                        my: "left top+20",
                        at: "left bottom",
                        of: "#sm_pwd"
                    });

                $("#btnCsvExport")
                    .position({
                        my: "left+10 top",
                        at: "right top",
                        of: "#btnEmail"
                    });
            }

            /**
             * Initializes the "View WYSIWYG" / "View Plain Text" button for the email body editor.
             * Toggles between TinyMCE and a plain textarea.
             * @returns {void}
             */
            function initEditorButton(){

                let currentMode = 'Plain Text';

                $('#btnSwitchEditor').on('click', () => {

                    $('#btnSwitchEditor').text(`View ${currentMode}`);

                    if(currentMode === 'Plain Text'){
                        initTinyMCE();
                        currentMode = 'WYSIWYG';
                    }else{
                        tinyMCE.remove();
                        currentMode = 'Plain Text';
                    }
                });
            }

            /**
             * Initializes the TinyMCE WYSIWYG editor on the #emailBody textarea.
             * Configures plugins, toolbar, and custom buttons.
             * @returns {void}
             */
            function initTinyMCE(){

                if(typeof tinyMCE === 'undefined'){
                    return;
                }

                let tinyMCEOptions = {
                    selector: '#emailBody',
                    menubar: false,
                    inline: false,
                    branding: false,
                    elementpath: false,
                    statusbar: true,
                    resize: 'both', 

                    remove_script_host: false,
                    forced_root_block: false,

                    entity_encoding:'raw',
                    inline_styles: true,

                    autoresize_bottom_margin: 15,
                    autoresize_on_init: false,

                    setup: function(editor){

                        // Insert horizontal rule
                        editor.ui.registry.addButton('customHRtag', {
                            text: '&lt;hr&gt;',
                            onAction: function (_) {
                                tinyMCE.activeEditor.insertContent( '<hr>' );
                            }
                        });
                        // Clear text formatting - to replace the original icon
                        editor.ui.registry.addIcon('clear-formatting', `<img style="padding-left: 5px;" src="${BASE_URL}hclient/assets/clear_formatting.svg" />`)
                        editor.ui.registry.addButton('customClear', {
                            text: '',
                            icon: 'clear-formatting',
                            tooltip: 'Clear formatting',
                            onAction: function (_) {
                                tinyMCE.activeEditor.execCommand('RemoveFormat');
                            }
                        });
                    },

                    plugins: [
                        'advlist autolink lists link preview ', //anchor charmap print 
                        'searchreplace visualblocks code fullscreen',
                        'media table paste help autoresize'  //insertdatetime  wordcount
                    ],

                    toolbar: ['styleselect | fontselect fontsizeselect | bold italic forecolor backcolor customClear customHRtag | link | align | bullist numlist outdent indent | table | help'],

                    content_css: [ '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i' ]
                };

                tinyMCE.init(tinyMCEOptions);
            }

            $(document).ready(function() {

                setupUserSelection();
                setupEmailSelection();
                setupOtherElements();

                if(!window.hWin.HR){
                    window.hWin.HR = function(token){return token};
                }

                set_element_position();

                $("#btnCalRecCount").on('click',getRecordCount);

                getInitDbList();

                initEditorButton();
            });

        </script>
    </head>

    <body style="margin: 10px 10px 10px 20px;">

        <div style="font-family:Arial,Helvetica,sans-serif;">
            <h3>Heurist System Email</h3>

            <span class="instruction">
                This tool allows you to email all users / specified types of user on all / selected Heurist databases available on this server. <br><br>
                The email to be sent should be created as a <strong>Email</strong> record in the current database, including subject line, body text and fields to be substituted using ##....## notation. <br><br>
            </span>

            <form id="emailOptions">

                <div class="t-row">

                    <span style="margin-right: 50px;">Filter: </span>

                    <span>

                        <label for="recTotal" class="non-selectable"> Over <input type="number" class="input-num" name="recTotal" id="recTotal" min="0" value="0"> records </label>

                        &nbsp;&nbsp;&nbsp;

                        <span class="non-selectable"> Database Last modified

                            <select name="recModLogic" id="recModifiedLogic">
                                <option value="more">more than</option>
                                <option value="less" selected>less than</option>
                            </select>

                            <input type="number" class="input-num" name="recModVal" id="recModified" min="1" value="">

                            <select name="recModInt" id="recModifiedSel">
                                <option value="DAY">Days</option>
                                <option value="MONTH">Months</option>
                                <option value="YEAR">Years</option>
                                <option value="ALL" selected>All</option>
                            </select>

                            Ago
                        </span>

                        <!-- New Checkbox for Incomplete Descriptions
                        <label>
                            <input type="checkbox" name="filterIncompleteDesc" id="filterIncompleteDesc" value="1">
                            Include databases with incomplete descriptions
                        </label>-->
                    </span>

                    <span style="margin-left: 15px;">
                        <button type="button" id="btnApply">Apply</button>
                        <span id="filterMsg" style="display: none;">Filtering Databases...</span>
                    </span>

                </div>

                <div class="l-col">

                    <div id="dbArea">

                        <div>Get users from these databases:</div>

                        <div style="margin: 10px 0px;">
                            Sort by:
                            <label><input type="radio" name="dbSortBy" class="dbSort" id="name"> Name</label>
                            <label><input type="radio" name="dbSortBy" class="dbSort" id="rec_count"> Record count</label>
                            <label><input type="radio" name="dbSortBy" class="dbSort" id="last_update"> Last updated</label>
                        </div>

                        <div class="non-selectable" style="margin: 0px 0px 10px 5px;">
                            <label><input type="checkbox" id="allDBs"> Select All</label>
                            <span style="float: right; display: none; margin-right: 10px;">Record count:</span>
                        </div>
                        <div id="dbSelection" data-order="name">
                        </div>

                    </div>

                </div>

                <div class="r-col">

                    <div style="margin-bottom: 20px;">
                        Send email to: <span id="userSelection"></span> &nbsp;&nbsp;&nbsp;
                        Count of distinct users: <span id="userCount">0</span>
                        <button id="btnCalRecCount" style="margin-left: 10px;" onclick="return false;">Count total DB records</button>
                        <span style="float: right; margin-left: 50px;">Number of databases selected: <span id="dbCount">0</span></span>
                        <br>
                        <span style="float: right;">Total count of records (selected databases): <span id="recCount">0</span></span>
                    </div>

                    <div class="non-selectable" style="margin-bottom: 20px;">
                        Email record containing the email outline
                        <br>
                        <select id="emailOutline" name="emailId" style="width: 99%;"></select>
                    </div>

                    <div style="margin-bottom: 20px;">

                        <div style="margin-bottom: 15px;">
                            Email Subject: <input type="text" id="emailTitle" name="emailTitle" style="margin-left: 5px;width: 86.6%;">
                        </div>

                        <div class="non-selectable" style="margin-bottom: 10px;">Email Body (use html tags): <span id="btnSwitchEditor">View WYSIWYG</span></div>
                        <textarea id="emailBody" rows="20" cols="90" name="emailBody"></textarea>
                    </div>

                    <div class="instruction" style="margin-bottom: 51px;">

                            <div style="margin-bottom: 10px;">Placeholders that will be replaced with proper values (case insensitive): </div>

                            <div style="float: left;margin: 0px 20px 35px 0px;">
                                ##firstname## &rarr; User's First Name, <br>
                                ##lastname## &rarr; User's Last Name, <br>
                                ##email## &rarr; User's Email, <br>
                                ##database## &rarr; Database Name
                            </div>

                            <div style="display: inline-block;">
                                ##dburl## &rarr; Database URL, <br>
                                ##records## &rarr; Record Count, and <br>
                                ##lastmodified## &rarr; Date of the Last Modified Record
                            </div>

                    </div>

                    <div id="authenContainer">

                        Please enter the System Manager password to confirm:&nbsp;

                        <input type="password" name="pwd" autocomplete="off" id="sm_pwd" />


                        <label><input type="checkbox" name="use_native" id="use_native" value="1"/>use native mail</label>

                        <label style="padding-left: 10px;" title="Include the content in GDPR.html (using one located in the Heurist parent directory, otherwise the one in /movetoparent)">
                            <input type="checkbox" name="add_gdpr" id="add_gdpr" value="1" checked="checked"/>include GDPR statement
                        </label>

                    </div>

                    <div style="margin-top: 10px;">

                        <button style="margin-left: 5px;" type="button" id="btnEmail">Send Emails</button>

                        <input type="button" id="btnCsvExport" value="Export CSV" onclick="doExportCSV(event)"/>

                    </div>

                </div>

                <input name="db" value="<?php echo htmlspecialchars($_REQUEST['db']);?>" style="display: none;" readonly />

                <input id="db_list" name="databases" type="hidden" />
                <input name="exportCSV" value="0" type="hidden"/>
            </form>

        </div>

    </body>
</html>
