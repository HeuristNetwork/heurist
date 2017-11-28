<?php

/**
* listDatabaseErrorsInit.php: Lists structural errors and records with errors:
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
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        
        <script type="text/javascript">
        
        $(document).ready(function() {   
        
            var $dosframe = $('#verification_output');
            $dosframe.on('load', function(){
                $dosframe.css({width:'100%',height:'100%'}).show(); 
                $('#in_porgress').hide()
            });
            
            <?php
             if(@$_REQUEST['type']=='files'){
                print '$dosframe.attr("src", "listUploadedFilesErrors.php?db='.$_REQUEST['db'].'");';     
             }else{
                print '$dosframe.attr("src", "listDatabaseErrors.php?db='.$_REQUEST['db'].'");';     
             }
             ?>
        });
        
        </script>
        <style>
        div#in_porgress{
            background-color:#FFF;
            background-image: url(../../common/images/loading-animation-white.gif);
            background-repeat: no-repeat;
            background-position:50%;
            cursor: wait;
            width:100%;
            height:100%
        }
        </style>            
    </head>
    <body class="popup" style="overflow:hidden">
        <div id='in_porgress'><h2>Verifying <?php echo @$_REQUEST['type']=='files'?'files':'database'; ?>. This may take up to a minute...</h2></div>    
        <iframe id="verification_output" style='display:none;border:none'>
        </iframe>
    </body>
</html>
