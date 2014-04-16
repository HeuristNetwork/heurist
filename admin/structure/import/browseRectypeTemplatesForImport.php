<?php

/*
* Copyright (C) 2005-2014 University of Sydney
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
* Browse through a seeries of documented record type templates on the Heursit Network site, HeuristNetwork.org
* and allow the user a one-click download of the record type and all its associated record types, fields and terms 
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @copyright   (C) 2005-2014 University of Sydney
* @link        http://HeuristScholar.org
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');

    if(isForAdminOnly("to import structural elements")){
       return;
    }

?>
<html>
	<head>
		<title>Browse documented record type templates for import</title>

		
		<link rel=stylesheet href="../../../common/css/global.css">
		<link rel=stylesheet href="../../../common/css/admin.css">

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
