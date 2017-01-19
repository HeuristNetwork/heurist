<?php

/**
* listDatabaseErrors.php: Lists structural errors and records with errors:
* invalid term codes, field codes, record types in pointers
* pointer fields point to non-existent records or records of the wrong type
*   single value fields with multiple values
*   required fields with no value
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');
require_once('valueVerification.php');
?>

<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">



        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
        </style>
        <link href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet" />
        <script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
        <!-- not accessible  script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.js"></script -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.3/toastr.min.js"></script>
        <script src="../../common/js/utilsUI.js"></script>


    </head>


    <body class="popup">



        <div id= "refreshMsg">

        </div>
        <script>

            toastr.options = {
                "timeOut" : 0,
                "extendedTimeOut" :0,
                "positionClass": "toast-top-full-width"
            };
            toastr.info('Database structure definitions in browser memory have been refreshed. <br/>'+
                'You may need to reload pages to see changes.');

        </script>
    </body>
</html>
