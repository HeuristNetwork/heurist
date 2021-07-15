<?php
//@TODO - NOT IMPLEMENTED

/* 
THIS IS Heurist v.3. 
It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
*/

/*
* Copyright (C) 2005-2020 University of Sydney
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
* browseRectypeTemplatesForImport.php
* Browse through a series of documented record type templates on the Heursit Network site, HeuristNetwork.org
* and allow the user a one-click download of the record type and all its associated record types, fields and terms 
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
?>
<html>
	<head>
		<title>Browse documented record type templates for import</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

		<link rel=stylesheet href="../../../common/css/global.css">
		<link rel=stylesheet href="../../../common/css/admin.css">

        <script>
            if (!window['postMessage'])
                alert("postMessage is not supported");
            else {
                if (window.addEventListener) {
                    //alert("standards-compliant");
                    // For standards-compliant web browsers (ie9+)
                    window.addEventListener("message", function(event){alert('!!!!')}, false);
                }
                else {
                    //alert("not standards-compliant (ie8)");
                    window.attachEvent("onmessage", receiveMessage);
                }
            }
            
            function receiveMessage(event)
            {
                var message;
                if (event.origin !== "https://Heuristplus.sydney.edu.au"){
                //if (false) {
                    message = 'Your server ("' + event.origin + '") is not authorised';
                } else {
                    message = 'I got "' + event.data + '" from "' + event.origin + '"';
                    document.getElementById('header-full').style.display = 'none';
                    document.getElementById('topbar').style.display = 'none';
                    document.getElementById('footer').style.display = 'none';
                }
                alert(message);
            }        
        </script>
	</head>

	<body>
        <!--
         
            TODO: Artem, at this point allow the user to browse the web pages starting at http://heuristnetwork.org/annotated-templates
            If possible, strip out page furniture around the content
            Add an [Import into database] link below or to the right of the page title (record type name)
            Pick up database and record type ID to get structurer from recorded as 
            hidden metadata embedded in page eg. <RecTypeSource>1234-567</RecTypeSource>
        
        -->
            
		<p>Function under development April 2014<br />

	</body>
</html>
