<?php

    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    * editRectypeSelFields.php
    * select initial set of fields for new record type
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Stephen White   
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

    define('SAVE_URI', 'disabled');

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');

    if (!is_admin()) return;//TOD change this for just admin and return msg. Is probably only called where user is admin


?>
<html>
    <head>

        <title>Select fields for new record type</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/edit.css">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/admin.css">

        <script type="text/javascript" src="../../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        
        <script>
            function closewin(){
                
                var fields = [], reqs=[];
                $('input.ids:checked').each(function() {
                    fields.push($(this).attr('data-id'));
                });                
                $('input.reqs:checked').each(function() {
                    reqs.push($(this).attr('data-id'));
                });                
                
                if(fields.length===0){
                    alert('Select at least one field');
                    return;
                }
                
                window.close({fields:fields, reqs:reqs});
            }
        </script>
    </head>

    <body class="popup">
    
    <p>Please select at least one of the following commonly-used fields to pre-populate this record type and indicate which will be Required fields (you can easily change or delete these fields later):</p>
    <div style="width:100%;text-align:center;pading:10px">
        <table style="margin:auto;width:50%">
            <tr><th align="center">Use</th><th>Req</th><th align="left" style="padding-left:20px">Name</th></tr>
            
<?php
 $fields = array(DT_NAME, DT_SHORT_SUMMARY, DT_THUMBNAIL, DT_GEO_OBJECT, DT_START_DATE, DT_END_DATE, DT_CREATOR);
 
 $dt = getAllDetailTypeStructures();
 $dt = $dt['names'];
 $checked = 'checked';
 
 foreach ($fields as $dty_ID){
     if(isset($dty_ID) && $dty_ID>0){
         
     ?>
            <tr><td style="padding:10px" align="center"><input type="checkbox" class='ids' data-id=<? echo '"'.$dty_ID.'" '.$checked;?>></td>
            <td align="center"><input type="checkbox" class='reqs' data-id=<? echo '"'.$dty_ID.'" '.$checked;?>></td>
            <td style="padding-left:20px"><?php echo $dt[$dty_ID];?></td></tr>
     <?php
        $checked = '';
     }
 }
?>            
        </table>
        <br><br>
        <button onclick="closewin()">Select</button>
    </div>
    
    
    </body>
</html>