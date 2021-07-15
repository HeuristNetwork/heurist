<?php
//@TODO - deprecated (Heurist v.3)
//It is not used anywhere. This code either should be removed or re-implemented wiht new libraries

    /**
    * annotatedTemplate.php: Import record types via annotated templates  - load external descripton (from heuristnetwork.org) into frame
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
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
        <title>Import record types from Annotated templates on HeuristNetwork.org</title>

        <link rel="icon" href="../../../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../../../favicon.ico" type="image/x-icon">

        <script type="text/javascript" src="../../../external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>

        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
    </head>

    <body>
        <script type="text/javascript" src="annotatedTemplate.js"></script>
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>

        <!-- div class="banner">
            <h2>Import record types from Annotated templates on HeuristNetwork.org</h2>
        </div -->

        <div>
            <iframe id="templates" onload="onFrameLoad()" src="http://heuristnetwork.org/annotated-templates" width="100%" height="100%"></iframe>
        </div>
    </body>
</html>