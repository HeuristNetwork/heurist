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
    .top {
        width: 100px;
        display: inline-block;
        text-align: right;
        vertical-align: top;
        margin-right: 5px;
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

<body class="popup">

    <div id="form">
        <!-- Selected records -->
        <div>
            <span># of records selected: </span>
            <span>4</span>
        </div>
        
        <hr>
        
        <!-- Email -->
        <div class="row">
            <label for="email" class="required">Email:</label>
            <select name="email" id="email"></select>
        </div>
        
        <!-- First name -->
        <div class="row">
            <label for="firstname">First name:</label>
            <select name="firstname" id="firstname"></select>
        </div>
        
        <!-- Family name -->
        <div class="row">
            <label for="familyname">Family name:</label>
            <select name="familyname" id="familyname"></select>
        </div>
        
        <hr>
        
        <!-- Field #1 -->
        <div class="row">
            <label for="field1">Field #1:</label>
            <select name="field1" id="field1"></select>
        </div>
        
        <!-- Field #2 -->
        <div class="row">
            <label for="field2">Field #2:</label>
            <select name="field2" id="field2"></select>
        </div>
        
        <!-- Field #3 -->
        <div class="row">
            <label for="field3">Field #3:</label>
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
            <div class="top">
                <label for="message" class="required">Message:</label>
                <br/>
                <div id="message-info">
                    may include html
                    <br/>
                    #fieldname to include content of field
                </div>
            </div>
            <textarea name="message" id="message" rows="15"></textarea>
        </div>
        
        <!-- Prepare -->
        <button id="prepare">Prepare emails</button>
    </div>
 
    

  
    <script type="text/javascript">
        //alert("Test");
    </script>
</body>
</html>
