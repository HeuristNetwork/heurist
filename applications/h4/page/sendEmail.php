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

//require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

?>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
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
        var dropdowns = ["#email", "#firstname", "#familyname", "#field1", "#field2", "#field3"]; 
        var types = ["freetext", "blocktext", "date"/*,"enum"*/];
        var ids = top.HEURIST.user.selectedRecordIds;
        var records = top.HAPI4.currentRecordset.getSubSetByIds(ids).getRecords();
        var record;
        
        // Sets up all fields
        function setup() {
            // Selected records
            $("#selected-records").html("# of records selected: " + ids.length);
            
            // Determine record type of first record
            for(var r in records) {
                this.record = records[r];
                var rectype = record[4];
                console.log("Rectype of first record: " + rectype);
                var definition = top.HEURIST4.rectypes.typedefs[rectype];
                console.log("Definition", definition);
                
                // Add record fields to dropdown
                var options = "<option value=\"-1\" disabled=\"disabled\" selected=\"selected\">Select...</option>";
                for(var d in definition.dtFields) {
                    var field = definition.dtFields[d];
                    console.log("Field", field);
                    
                    if(types.indexOf(field[23]) >= 0) { // Appropriate type
                        options += "<option value=\"" +d+ "\">" + field[0] + "</option>";     
                    }
                }
                console.log("Options", options);
                
                // Append options to each dropdown
                for(var i=0; i < dropdowns.length; i++) {
                    $(dropdowns[i]).html(options);
                }
                
                break;
            }
        }
        
        // Changes the text area's
        function redo() {
            $("#prepare").text("Prepare emails");    
            $("#redo").hide();
            $("#message-prepared").slideToggle(500, function(e) {
                $("#message").slideToggle(500);
            })   
        }
        
        // Prepares the email
        function prepare() {
            // Raw message
            var rawMessage =  $("#message").val();
            console.log("Raw message: " + rawMessage);  
                
            // Check action
            var text = $("#prepare").text();
            console.log(text);
            if(text.indexOf("Prepare") >= 0) {
                // PREPARE EMAILS
                var message = prepareMessage(rawMessage, record);
                
                // Show prepared message in new text area
                console.log("Prepared message: " + message);
                $("#message").slideToggle(500, function(e) {
                    $("#redo").show();
                    $("#message-prepared").val(message).slideToggle(500);
                    $("#prepare").text("Send emails");    
                });
            }else{
                // SEND EMAILS
                for(var r in records) {
                    var message = prepareMessage(rawMessage, records[r]);
                    console.log("Prepared message: " + message);
                }
            }
        }
        
        // Replaces the raw message text with fields in a record
        function prepareMessage(message, record) {
            // Replace hashtags by actual content
            for(var i=0; i<dropdowns.length; i++) {
                // Value
                var val = $(dropdowns[i]).val();
                console.log(record);
                var value = record.d[val];
                
                if(value != null) {
                    // Regex
                    var regex = new RegExp(dropdowns[i], "ig");
                    message = message.replace(regex, value);
                }
            }    
            return message; 
        }
        
    </script>
</body>
</html>
