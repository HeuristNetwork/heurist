<?php

/**
* longOperationInit.php: 
* 
* iframe (wait) wrapper for listUploadedFilesErrors and listDatabaseErrors and rebuild titles
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
set_time_limit(0);

$recTypeIDs = (@$_REQUEST['recTypeIDs']!=null)?htmlspecialchars($_REQUEST['recTypeIDs']):null;
$dbname = htmlspecialchars($_REQUEST['db']);
            
 if(@$_REQUEST['type']=='titles'){
    if($recTypeIDs){
        $srcURL = 'rebuildRecordTitles.php?recTypeIDs='.$recTypeIDs.'&db='.$dbname;    
    }else{
        $srcURL = 'rebuildRecordTitles.php?db='.$dbname;    
    }
    $sTitle = 'Recalculation of composite record titles';
 
 }else
 if(@$_REQUEST['type']=='calcfields'){
    if($recTypeIDs){
        $srcURL = 'rebuildCalculatedFields.php?recTypeIDs='.$recTypeIDs.'&db='.$dbname;    
    }else{
        $srcURL = 'rebuildCalculatedFields.php?db='.$dbname;    
    }
    $sTitle = 'Recalculation of calculated fields';
 
 }else
 if(@$_REQUEST['type']=='files'){
    $srcURL = 'listUploadedFilesErrors.php?db='.$dbname;
    $sTitle = 'Verifying files';
 }else{
    $srcURL = 'listDatabaseErrors.php?db='.$dbname;
    $sTitle = 'Verifying database';
 }

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title><?php echo $sTitle; ?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <link rel="stylesheet" type="text/css" href="../../h4styles.css">
        
        <script type="text/javascript">
        
        $(document).ready(function() {   
        
            setTimeout(function(){
                var $dosframe = $('#verification_output');
                $dosframe.on('load', function(){
                    $dosframe.css({width:'100%',height:'100%'}).show(); 
                    $('#in_porgress').hide()
                });
                
                $dosframe.attr("src", "<?php echo $srcURL; ?>");
             },500);
        });
        
        </script>
        <style>
        div#in_porgress{
            background-color:#FFF;
            background-image: url(../../hclient/assets/loading-animation-white.gif);
            background-repeat: no-repeat;
            background-position:50%;
            cursor: wait;
            width:100%;
            height:100%
        }
        </style>            
    </head>
    <body class="popup" style="overflow:hidden">
        <div id='in_porgress'><h2><?php echo $sTitle; ?>. This may take up to a few minutes for large databases...</h2></div>    
        <iframe id="verification_output" style='display:none;border:none;width:1;height:1'>
        </iframe>
    </body>
</html>
