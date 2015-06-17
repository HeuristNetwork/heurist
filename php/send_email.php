<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* Shows an email form
*
* @author      Jan Jaap de Groot <jjedegroot@gmail.com>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     4.0.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

require_once(dirname(__FILE__).'/common/utils_mail.php');

// POST request    
if(isset($_POST['data'])) {
    $data = json_decode($_POST['data']);
    $response = "";

    $subject = $data->subject;  // Email subject
    $header = $data->header;    // Email header
    foreach($data->emails as $email) {
        // Determine message & recipients
        $message = $email->message;       // Email message
        $recipients = $email->recipients; // One or more e-mail adresses
        foreach($recipients as $recipient) {
            // Check if the e-mail address is valid
            if(filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                // Send e-mail
                $result = sendEmail($recipient, $subject, $message, $header); // utils_mail.php 
                $response .= $recipient . " --> " . $result . "\n";    
            }else{
                $response .= $recipient . " --> invalid e-mail address\n";
            }
        }
    }
    
    echo $response;
    exit(); 
}
   
// GET REQUEST 
?> 
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Send email</title>

  <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
  
  <style>
    #form {
        display: inline-block;
    }
    
    .row {
        position: relative;
        padding: 3px;
        margin-left: 5px;
        margin-right: 5px;
    }
    
    /** Label */
    .row label {
        width: 100px;
        text-align: right;
        display: inline-block;
        margin-right: 5px;
    }
    
    .required {
        color: #f11;
        font-weight: bold;
    }
    
    
    .row select {
        min-width: 200px;
    }
    
    .row input {
        width: 300px;
    }
    
    /** Message */
    #top {
        width: 100px;
        display: inline-block;
        text-align: right;
        vertical-align: top;
        margin-right: 5px;
    }
    
    #right {
        display: inline-block;
    }
    
    #redo {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(255, 255, 255, 0.5);
        color: #000;
        border: 1px solid white;
        font-size: 20px;
        cursor: pointer;
    }
    
    .row textarea {
        width: 300px;       
    }
    
    #message-info {
        font-size: 10px;   
    }
    
    
    /** Prepare */
    #prepare {
        float: right;
        border: 1px solid black;
        padding: 5px;
        background-color: #fff;
        font-weight: bold;
        cursor: pointer;
    }
  </style>
</head>

<body class="popup" onload="setup()">

    <div id="form">
        <!-- Selected records -->
        <span id="selected-records"></span>
        
        <hr>
        
        <!-- Email -->
        <div class="row">
            <label for="email" class="required">Email:</label>
            <select name="email" id="email"></select>
        </div>
        
        <!-- First name -->
        <div class="row">
            <label for="firstname">FirstName:</label>
            <select name="firstname" id="firstname"></select>
        </div>
        
        <!-- Family name -->
        <div class="row">
            <label for="familyname">FamilyName:</label>
            <select name="familyname" id="familyname"></select>
        </div>
        
        <hr>
        
        <!-- Field #1 -->
        <div class="row">
            <label for="field1">Field1:</label>
            <select name="field1" id="field1"></select>
        </div>
        
        <!-- Field #2 -->
        <div class="row">
            <label for="field2">Field2:</label>
            <select name="field2" id="field2"></select>
        </div>
        
        <!-- Field #3 -->
        <div class="row">
            <label for="field3">Field3:</label>
            <select name="field3" id="field3"></select>
        </div>
        
        <hr>
        
        <!-- Subject -->
        <div class="row">
            <label for="subject" class="required">Subject:</label>
            <input type="text" name="subject" id="subject">
        </div>
        
        <!-- Message -->
        <div class="row">
            <div id="top">
                <label for="message" class="required">Message:</label>
                <br/>
                <div id="message-info">
                    may include html
                    <br/>
                    #fieldname to include content of field
                </div>
            </div>
            <div id="right">
                <textarea name="message" id="message" rows="15"></textarea>
                <textarea name="message" id="message-prepared" rows="15" style="display: none"></textarea>
                <button id="redo" style="display: none" onclick="redo()">&#10226;</button>
            </div>
        </div>
        
        <!-- Prepare -->
        <button id="prepare" onClick="prepare()">Prepare emails</button>
    </div>
 

    <!-- Javascript -->
    <script type="text/javascript">
        var storage_key = "email"; // Key used to store data
        var dropdowns = ["#email", "#firstname", "#familyname", "#field1", "#field2", "#field3"]; // ID's of the dropdowns 
        var text_types = ["freetext", "blocktext"]; // Text only types for the dropdowns
        var all_types = ["freetext", "blocktext", "memo", "seperator", "numeric", "date", "enum", "termlist"]; // All types that can be selected from the dropdown
        var definitions; // Record type field definitions
        var rectype_index = 4; // Record type index in object
        var type_index = 23; // Field type index in object
        var ids = top.HEURIST.user.selectedRecordIds; // Selected record ID's
        var records = top.HAPI4.currentRecordset.getSubSetByIds(ids).getRecords(); // Array of record objects
        var record; // First record in the list, used to determine the Record Types
        
        // Retrieves an item from localStorage
        function getItem(name) {
            return localStorage[storage_key+name];
        }
        
        // Stores an item in localStorage
        function putItem(name, value) {
            localStorage[storage_key+name] = value;
        }
        
        // Determines valid options
        function determineOptions(types) {
            // Determine options
            var options = []; 
            for(var d in definitions) { // Go through each field for the first record type
                var field = definitions[d]; 
                // Appropriate type check
                if(types.indexOf(field[type_index]) >= 0) { // Check if this field is allowed 
                    options.push({name: field[0], value: d}); 
                }
            }  
            
            // Sort alphabetically
            options.sort(function(a, b) {
                return a.name.localeCompare(b.name);
            });
            console.log("Determined options", options);
            
            // Generate HTML  
            var html = "<option value=\"-1\" disabled=\"disabled\" selected=\"selected\">Select...</option>";
            if(options.length > 0) {
                for(var i=0; i<options.length; i++) {
                    html += "<option value=\"" +options[i].value+ "\">" + options[i].name + "</option>"; // Add field to dropdown    
                }
            }
            return html;
        }
        
        // Fills a dropdown with options
        function fillDropdown(i, options) {
            // Append options to each dropdown
            $(dropdowns[i]).html(options);
            
            // Remember last selected index
            var selectedIndex = getItem(dropdowns[i]);
            if(selectedIndex) {
                $(dropdowns[i]).prop("selectedIndex", selectedIndex);
            }

            // Listen to dropdown hanges
            $(dropdowns[i]).change(function(e) {
                var id = $(this).attr("id"); // Dropdown ID
                var value = $(this).prop("selectedIndex"); // Selected dropdown index
                ///console.log("Checkbox " + id + " changed to " + value);  
                putItem("#"+id, value); // Store data
                redo(); // Message needs to be re-done
            });
        }
        
        // Sets up all fields
        function setup() {
            // Selected records
            $("#selected-records").html("# of records selected: " + ids.length);
            
            // Determine record type of first record
            for(var r in records) {
                this.record = records[r]; // Reference to first record
                var rectype = record[rectype_index]; // Record type of first record
                this.definitions = top.HEURIST4.rectypes.typedefs[rectype].dtFields; // Definitions for this record type
                console.log("Rectype: " + rectype + ", definitions", definitions);
                
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
                break;
            }
            
            // Setup subject field
            $("#subject").on("keyup", function(e) {
                var text = $(this).val();
                putItem("subject", text); // Store subject text    
            });
            $("#subject").val(getItem("subject")); // Set subject text
            
            // Setup message field
            $("#message").on("keyup", function(e) {
                var text = $(this).val();
                putItem("message", text); // Store message text
            });
            $("#message").val(getItem("message")); // Set message text
            
        }
        
        // Swaps the text area's
        function redo() {
            $("#prepare").text("Prepare emails");    
            $("#redo").hide(); // Hide redeo button
            $("#message-prepared").slideUp(500, function(e) {  // Hide prepared message
                $("#message").slideDown(500);  // Show raw message
            })   
        }
        
         // Determines the actual record value at the given index
        function getValue(record, type, index) {
            //console.log("Getting value for record", record);
            
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
                    return top.HEURIST4.terms.termsByDomainLookup.enum[enumID][0];
                }
                  
            }else if(type == "termlist") {
                         alert("terms");
                         
            } 
 
            return null;
        }
        
        // Replaces the raw message text with fields in a record
        function prepareMessage(message, record, fields) {
            // Replace hashtags by actual content
            for(var i=0; i<dropdowns.length; i++) {
                // Index selected in the dropdown
                var index = $(dropdowns[i]).val();   // Record index
                var value = "?";
                if(index && index > 0) {
                    value = getValue(record, fields[index][type_index], index); // Record value at the given index
                }
                
                // Regex
                var regex = new RegExp(dropdowns[i], "ig"); // Replace #xxx case insensitive
                message = message.replace(regex, value);    // Replace all occurences with @value
            }    
            return message; 
        }
        
        // Prepares the email
        function prepare() {    
            // Raw message
            var rawMessage =  $("#message").val();
            //console.log("Raw message: " + rawMessage);  
 
            // Check action
            var buttonText = $("#prepare").text()
            if(buttonText.indexOf("Prepare") >= 0) { // Check button text to determine action
                // PREPARE EMAILS
                var message = prepareMessage(rawMessage, record, definitions);
                
                // Show prepared message in new text area
                //console.log("Prepared message: " + message);
                $("#message").slideUp(500, "linear", function(e) {
                    $("#redo").slideDown(500);
                    $("#message-prepared").val(message).slideDown(500);
                    $("#prepare").text("Send emails");    
                });
            }else{
                // SEND EMAILS
                var data = {};
                data.subject = $("#subject").val();
                data.header = "header";
                data.emails = [];
 
                // Construct a message based on record data
                for(var r in records) {
                    var email = {};
                    
                    // Email
                    var emailIndex = $("#email").val();  // Dropdown index
                    var emailType = definitions[emailIndex][type_index];  // Field type
                    email.recipients = getValue(records[r], emailType, emailIndex);  // Determine e-mail address(es) [comma seperated]
                    
                    // Message
                    email.message = prepareMessage(rawMessage, records[r], definitions); // Determine message
                    data.emails.push(email);
                }
                
                // Include e-mail to current user/database owner
                var owner = {recipients: [top.HAPI4.sysinfo.dbowner_email], message: rawMessage};
                data.emails.push(owner);
                
                // Send data to PHP file, everything is checked server-sided
                console.log("Data to send", data);
                $.post("send_email.php", {data: JSON.stringify(data)}, function(response) {
                    console.log("Posted data, response: ", response);
                    alert(response);  
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    alert(jqXHR.status + " --> " + jqXHR.responseText);
                });

            }
        }
        
    </script>
</body>
</html>
