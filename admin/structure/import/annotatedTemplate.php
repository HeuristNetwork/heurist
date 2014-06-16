<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* Import rectype via annotated templates  - load external descripton (from heuristnetwork.org) into frame
* 
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  
*/

// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

if(isForAdminOnly("to modify database structure")){
    return;
}
?>
<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Import rectypes via Annotated templates</title>

        <link rel="icon" href="../../../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../../../favicon.ico" type="image/x-icon">

        <script type="text/javascript" src="../../../external/jquery/jquery.js"></script>

        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
        
    </head>

    <body>
        <script type="text/javascript" src="annotatedTemplate.js"></script>
        <div>
            <div class="banner"><h2>Import rectypes via Annotated templates</h2></div>

            <iframe id="templates" onload="onFrameLoad()" src="http://heuristnetwork.org/annotated-templates" width="100%" height="100%" />
<!--
 <button onclick="onFrameLoad()">Send</button>
            <iframe id="templates" onload="onFrameLoad()" src="http://heur-db-pro-1.ucc.usyd.edu.au/HEURIST/h3-ao/admin/structure/import/browseRectypeTemplatesForImport.php?db=artem_delete1" width="100%" height="100%" />
-->
        </div>
    </body>
</html>
<?php
  
?>
