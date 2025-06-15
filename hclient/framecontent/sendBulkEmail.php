<?php
/**
* sendBulkEmail.php - Bulk email form
* 
* Generates emails based on record's values.
* 
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Jan Jaap de Groot <jjedegroot@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
use hserv\utilities\USanitize;

define('PDIR','../../');//need for proper path to js and css
define('MANAGER_REQUIRED', 1);

require_once 'initPageMin.php';

// POST request
if(isset($_POST['data'])) {
    $params = USanitize::sanitizeInputArray();

    $data = json_decode($params['data']);
    $response = "";

    $subject = htmlspecialchars(filter_var($data->subject));// Email subject
    foreach($data->emails as $email) {
        // Determine message & recipients
        USanitize::purifyHTML($email->message);// Email message
        $recipients = $email->recipients; // One or more e-mail adresses
        foreach($recipients as $recipient) {
            // Check if the e-mail address is valid
            $recipient_sanitized = filter_var($recipient, FILTER_VALIDATE_EMAIL);
            if($recipient_sanitized) {
                // Send e-mail
                $result = sendEmail($recipient_sanitized, $subject, $email->message);
                if(!$result){
                    $err = $system->getError();
                    $result = $err['message'];
                }else{
                    $result = 'sent';
                }

                $response .= $recipient_sanitized . " --> " . $result . "\n";
            }else{
                $response .= $recipient . " --> invalid e-mail address\n";
            }
        }
    }

    echo htmlentities($response);
    exit;
}

// GET REQUEST
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex,nofollow">
    <title>Bulk email sender</title>

    <!-- CSS -->
    <?php

    include_once dirname(__FILE__).'/initPageCss.php';
    includeJQuery();

    ?>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>

    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

    <style>
        #btn_redo {
            cursor: pointer;
            float: right;
            font-size: 1.4em;
            height: 1.8em;
            margin-left: 2px;
            margin-top: 6px;
            width: 1.8em;
        }

    </style>

</head>

<body class="ui-heurist-bg-light" onload="setup()">

    <fieldset style="font-size:0.8em"><legend style="display:none"></legend>

        <span id="selected-records"></span>

        <hr>

        <div style="font-size: smaller; margin-top: 2px;display:block">This function assumes that the records contain - at the least - an email address field. Choose this (required) field in the first dropdown. For each record selected, one email will be sent to the address stored in this field.
            If name fields also exist, these can be selected in the next two dropdowns and may be used in the body of the message. The first three dropdowns show only text fields.
        </div>

        <div>
            <div class="header mandatory"><label for="email">Email:</label></div>
            <select name="email" id="email" class="text ui-widget-content ui-corner-all mandatory"></select>
        </div>
        <div>
            <div class="header"><label for="firstname">First Name:</label></div>
            <select name="firstname" id="firstname" class="text ui-widget-content ui-corner-all"></select>
        </div>
        <div>
            <div class="header"><label for="familyname">Family Name:</label></div>
            <select name="familyname" id="familyname" class="text ui-widget-content ui-corner-all"></select>
        </div>

        <hr>

        <div style="font-size: smaller; margin-top: 2px;display:block">Additional user-defined fields can be selected in the other three dropdowns, which show all available fields. Each selected field can be used in the text of the message and will be substituted in the message body for each email sent.
        </div>

        <div>
            <div class="header"><label for="field1">Field 1:</label></div>
            <select name="field1" id="field1" class="text ui-widget-content ui-corner-all"></select>
        </div>
        <div>
            <div class="header"><label for="field2">Field 2:</label></div>
            <select name="field2" id="field2" class="text ui-widget-content ui-corner-all"></select>
        </div>
        <div>
            <div class="header"><label for="field3">Field 3:</label></div>
            <select name="field3" id="field3" class="text ui-widget-content ui-corner-all"></select>
        </div>

        <hr>

        <div>
            <div class="header_narrow"><label for="subject">Subject :</label></div>
            <input type="text" name="subject" id="subject" class="text ui-widget-content ui-corner-all mandatory"  maxlength="40" style="width:24.2em"/>
        </div>
        <div>
            <div class="header_narrow" style="vertical-align:top"><label for="message">Message :</label></div>
            <textarea name="message2" id="message" rows="8" class="text ui-widget-content ui-corner-all mandatory"  style="margin-top:0.4em;width:25em"></textarea>
            <textarea name="message" id="message-prepared" rows="10"
                                                            class="text ui-widget-content ui-corner-all mandatory"  style="margin-top:0.4em;width:25em;display: none"></textarea>
            <button id="btn_redo" style="display: none" onclick="redo()" class="ui-icon ui-icon-arrowrefresh-1-s"></button>

            <div style="font-size: smaller; margin-top: 2px;">may include html; #fieldname to include content of field</div>
       </div>

       <div style="display:block;padding-top:1em;text-align:right;width:100%">
            <button type="button" id="prepare"
                    class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only"
                    aria-disabled="false" onClick="prepare()">
                    <span class="ui-button-text">Prepare emails</span>
            </button>
       </div>

    </fieldset>


    <!-- Javascript -->
    <script type="text/javascript">
        /** @type {string} Key used as a prefix for storing data in localStorage. */
        var storage_key = "email";
        /** @type {string[]} Array of jQuery selectors for the dropdown elements. */
        var dropdowns = ["#email", "#firstname", "#familyname", "#field1", "#field2", "#field3"];
        /** @type {string[]} Array of field type codes considered as text-only fields. */
        var text_types = ["freetext", "blocktext"];
        /** @type {string[]} Array of all field type codes that can be selected in the general dropdowns. */
        var all_types = ["freetext", "blocktext", "memo", "seperator", "numeric", "date", "enum"];
        /** @type {object} Holds the record type field definitions for the first record's type. */
        var definitions;
        /** @type {number[]} Array of selected record IDs from the parent window's HAPI. */
        var ids =  window.hWin.HAPI4.selectedRecordIds;
        /** @type {HRecordSet} A Heurist recordset object containing the selected records. */
        var recordset = window.hWin.HAPI4.currentRecordset.getSubSetByIds(ids);
        /** @type {object[]} Array of record data objects from the recordset. */
        var records = recordset.getRecords();
        /** @type {object} The first record object from the recordset, used to determine the record type for field definitions. */
        var first_record = recordset.getFirstRecord();

        /**
         * Retrieves an item from localStorage, prefixed with storage_key.
         * @param {string} name - The name of the item to retrieve (without prefix).
         * @returns {string|null} The value from localStorage, or null if not found.
         */
        function getItem(name) {
            return localStorage[storage_key+name];
        }

        /**
         * Stores an item in localStorage, prefixed with storage_key.
         * @param {string} name - The name of the item to store (without prefix).
         * @param {string} value - The value to store.
         */
        function putItem(name, value) {
            localStorage[storage_key+name] = value;
        }

        /**
         * Determines valid options for dropdowns based on field types and record definitions.
         * It iterates through the field definitions of the first record's type, filters them
         * by the provided `types` array, sorts them alphabetically, and generates HTML
         * for `<option>` elements.
         *
         * @param {string[]} types - An array of allowed field type codes (e.g., text_types, all_types).
         * @returns {string} HTML string containing `<option>` elements.
         */
        function determineOptions(types) {
            // Determine options
            var options = [];
            // Go through each field for the first record type
            definitions.each2(function(dty_ID, detail){

                var field_type = $Db.dty(dty_ID, 'dty_Type');
                // Appropriate type check
                if(types.indexOf(field_type) >= 0) { // Check if this field is allowed
                    options.push({name: detail['rst_DisplayName'], value: dty_ID});
                }
            });

            // Sort alphabetically
            options.sort(function(a, b) {
                return a.name.localeCompare(b.name);
            });

            // Generate HTML
            var html = "<option value=\"-1\" disabled=\"disabled\" selected=\"selected\">Select...</option>";
            if(options.length > 0) {
                for(var i=0; i<options.length; i++) {
                    html += "<option value=\"" +options[i].value+ "\">" + options[i].name + "</option>";// Add field to dropdown
                }
            }
            return html;
        }

        /**
         * Fills a specific dropdown (identified by its index in the `dropdowns` array)
         * with the provided HTML options. It also restores the last selected index for that
         * dropdown from localStorage and sets up a change listener to save future selections
         * and trigger the `redo()` function.
         *
         * @param {number} i - The index of the dropdown in the `dropdowns` array.
         * @param {string} options - HTML string of `<option>` elements.
         */
        function fillDropdown(i, options) {
            // Append options to each dropdown
            $(dropdowns[i]).html(options);

            // Remember last selected index
            var selectedIndex = getItem(dropdowns[i]);
            if(selectedIndex) {
                $(dropdowns[i]).prop("selectedIndex", selectedIndex);
            }

            // Listen to dropdown hanges
            $(dropdowns[i]).on('change', function(e) {
                let id = $(this).attr("id");// Dropdown ID
                let value = $(this).prop("selectedIndex");// Selected dropdown index
                putItem("#"+id, value);// Store data
                redo();// Message needs to be re-done
            });
        }

        /**
         * Sets up the entire email form interface.
         * This function is called on body onload. It:
         * - Displays the count of selected records.
         * - Determines the record type of the first selected record to get field definitions.
         * - Populates the dropdowns for email, first name, family name (text fields only).
         * - Populates the dropdowns for additional fields (all allowed types).
         * - Sets up event listeners and restores saved values for the subject and message fields.
         */
        function setup() {
            // Selected records
            $("#selected-records").html("# of records selected: " + ids.length);

            // Determine record type of first record

            var rectype = recordset.fld(first_record, 'rec_RecTypeID');// Record type of first record
            definitions = $Db.rst(rectype);

            // TEXT ONLY DROPDOWNS
            var text_options = determineOptions(text_types);
            fillDropdown(0, text_options);
            fillDropdown(1, text_options);
            fillDropdown(2, text_options);

            // OTHER DROPDOWNS
            var all_options = determineOptions(all_types);
            fillDropdown(3, all_options);
            fillDropdown(4, all_options);
            fillDropdown(5, all_options);

            // Setup subject field
            $("#subject").on("keyup", function(e) {
                var text = $(this).val();
                putItem("subject", text);// Store subject text
            });
            $("#subject").val(getItem("subject"));// Set subject text

            // Setup message field
            $("#message").on("keyup", function(e) {
                var text = $(this).val();
                putItem("message", text);// Store message text
            });
            $("#message").val(getItem("message"));// Set message text

        }

        // Swaps the text area's
        function redo() {
            $("#prepare > span").text("Prepare emails");
            $("#btn_redo").hide();// Hide redo button
            $("#message-prepared").slideUp(500, function(e) {  // Hide prepared message
                $("#message").slideDown(500);// Show raw message
            })
        }

        /**
         * Retrieves a specific detail value from a record object based on the field type and its ID (index).
         * Handles different data types appropriately (e.g., direct value for text/date, term label for enum).
         * Note: Some types (memo, separator, numeric, termlist) currently have alert placeholders
         * and may need further implementation to return meaningful values.
         *
         * @param {object} record - The Heurist record object (from recordset.getRecords()).
         * @param {string} type - The field type code (e.g., "freetext", "enum").
         * @param {number} index - The ID of the detail type (dty_ID) to retrieve.
         * @returns {string|null} The formatted value of the field, or null if not found/handled.
         */
        function getValue(record, type, index) {
            // Determine type
            if(type == "freetext" || type =="blocktext" || type == "date") {
                return record.d[index];

            }else if(type == "memo") {
                alert("Memo");

            }else if(type == "seperator") {
                alert("sep");

            }else if(type == "numeric") {
                alert("num");

            }else if(type == "enum") {
                var enumID = record.d[index];
                if(enumID) {
                    return $Db.trm(enumID, 'trm_Label');
                }

            }else if(type == "termlist") { //???
                         alert("terms");

            }

            return null;
        }

        /**
         * Gets a comma-separated string of selected field type IDs (dty_ID) from the dropdowns.
         * Only includes IDs for dropdowns where a valid field has been selected (value > 0).
         *
         * @returns {string} A comma-separated string of selected dty_IDs.
         */
        function getSelectedFieldTypeIds() {
            var res = [];
            for(var i=0; i<dropdowns.length; i++) {
                // Index selected in the dropdown
                var index = $(dropdowns[i]).val();// field type index
                if(index && index > 0) {
                     res.push(index);
                }
            }
            return res.join(',');
        }


        /**
         * Replaces placeholder hashtags (like #email, #firstname) in the raw message text
         * with actual content from the given record's fields.
         * The `fields` parameter (which is `definitions` in practice) is used to determine field types.
         *
         * @param {string} message - The raw message template with placeholders.
         * @param {object} record - The Heurist record object.
         * @param {object} fields - The record type field definitions (same as global `definitions`).
         * @returns {string} The message with placeholders replaced by record data.
         */
        function prepareMessage(message, record, fields) {
            // Replace hashtags by actual content
            for(var i=0; i<dropdowns.length; i++) {
                // Index selected in the dropdown
                var dty_ID = $(dropdowns[i]).val();// field type index
                var value = "?"; // Default if field not selected or value not found
                if(dty_ID && dty_ID > 0) {
                    var field_type = $Db.dty(dty_ID, 'dty_Type');
                    value = getValue(record, field_type, dty_ID);// Record value at the given index
                }

                // Regex
                var regex = new RegExp(dropdowns[i], "ig");// Replace #xxx case insensitive
                message = message.replace(regex, value);// Replace all occurences with @value
            }
            return message;
        }

        /**
         * Handles the two-stage process of preparing and sending emails.
         * If the button text is "Prepare emails":
         *  - It generates a preview of the first email by calling `prepareMessage`.
         *  - It loads necessary record details if not already present.
         *  - It updates the UI to show the prepared message and changes the button text to "Send emails".
         * If the button text is "Send emails":
         *  - It constructs a data object containing the subject and an array of email objects
         *    (each with recipients and a personalized message for each record).
         *  - It includes an email to the database owner.
         *  - It POSTs this data to `sendBulkEmail.php` for actual sending.
         *  - It displays the server's response in a dialog.
         */
        function prepare() {
            // Raw message
            var rawMessage =  $("#message").val();

            var buttonText = $("#prepare > span").text();// stupid check by button text!!!!!

            var details = getSelectedFieldTypeIds();
            if(details!='' && !this.record){ // && !this.record.d
                //load details if required
                 var request = request = {q: 'ids:'+ids.join(','), w: 'all', detail:details };

                 window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( new_recordset )
                 {
                    if(new_recordset!=null){
                        this.records = new_recordset.getRecords();
                        this.record = new_recordset.getFirstRecord();
                        prepare();
                    }
                 });
                 return;
            }

            // Check action
            if(buttonText.indexOf("Prepare") >= 0) { // Check button text to determine action
                // PREPARE EMAILS
                var message = prepareMessage(rawMessage, record, definitions);

                // Show prepared message in new text area
                $("#message").slideUp(500, "linear", function(e) {
                    $("#btn_redo").slideDown(500);
                    $("#message-prepared").val(message).slideDown(500);
                    $("#prepare > span").text("Send emails");
                });
            }else{
                // SEND EMAILS
                var data = {};
                data.subject = $("#subject").val();
                data.emails = [];

                // Construct a message based on record data
                for(var r in records) {
                    var email = {};

                    // Email
                    var emailIndex = $("#email").val();// Dropdown index

                    if(emailIndex>0){
                        var emailType = $Db.dty(emailIndex, 'dty_Type');
                        email.recipients = getValue(records[r], emailType, emailIndex);// Determine e-mail address(es) [comma seperated]
                        if(!top.HEURIST4.util.isArrayNotEmpty(email.recipients)) email.recipients = [];

                        // Message
                        email.message = prepareMessage(rawMessage, records[r], definitions);// Determine message
                        data.emails.push(email);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "Define email field. It is mandatory",
                            error_title: 'Missing email',
                            status: window.hWin.ResponseStatus.ACTION_BLOCKED
                        });
                        return;
                    }
                }

                // Include e-mail to current user/database owner
                var owner = {recipients: [window.hWin.HAPI4.sysinfo.dbowner_email], message: rawMessage};
                data.emails.push(owner);

                // Send data to PHP file, everything is checked server-sided
                $.post("sendBulkEmail.php", {
                    db: window.hWin.HAPI4.database,
                    data: JSON.stringify(data)}, function(response) {
                    window.hWin.HEURIST4.msg.showMsgDlg(response);
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    window.hWin.HEURIST4.msg.showMsgDlg(jqXHR.status + " --> " + jqXHR.responseText);
                });

            }
        }

    </script>
</body>
</html>
