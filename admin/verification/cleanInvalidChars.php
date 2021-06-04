<?php

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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

$is_included = (defined('PDIR'));

if(!$is_included){

define('MANAGER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

$mysqli = $system->get_mysqli();

}

$invalidChars = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),chr(18),chr(19),chr(20),chr(21),chr(22),chr(23),chr(24),chr(25),chr(26),chr(27),chr(28),chr(29),chr(30),chr(31)); // invalid chars that need to be stripped from the data.
$replacements = array("?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?"," ","?","?","?","?","?");
$textDetails = array();

$res = $mysqli->query('SELECT dtl_ID,dtl_RecID,dtl_Value,dty_Name '.
    'FROM recDetails left join defDetailTypes on dtl_DetailTypeID = dty_ID  '.
    "WHERE dty_Type in ('freetext','blocktext') ORDER BY dtl_RecID");
if($res){
    while ($row = $res->fetch_assoc()) {
        array_push($textDetails, $row);
    }
    $res->close();
}
if($is_included){
?>    
<div>
<h3 id="invalid_msg">Check and fix invalid characters in the data fields in the database records</h3>
<?php    
$is_not_found = true;
}else{
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Clean Invalid Characters</title>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner"><h2>Clean Invalid Characters</h2></div>
        <div id="page-inner" style="overflow:auto;padding-left: 6px;">
            <div>This function removes invalid characters in the data fields in the database records<br />&nbsp;<hr /></div>
<?php
}
?>
            <table>
                <?php
                $now = date('Y-m-d H:i:s');
                $prevInvalidRecId = 0;
                foreach ($textDetails as $textDetail) {
                    if (! check_invalid_chars($textDetail['dtl_Value'])){
                        if ($prevInvalidRecId < $textDetail['dtl_RecID']) {
                            print "<tr><td><a target=_blank href='".HEURIST_BASE_URL."?fmt=edit&recID=".
                            $textDetail['dtl_RecID'] . "&db=".HEURIST_DBNAME. "'> " . $textDetail['dtl_RecID']. "</a></td></tr>\n";
                            $prevInvalidRecId = $textDetail['dtl_RecID'];
                            
                            mysql__insertupdate($mysqli, 'Records', 'rec_', 
                                    array('rec_ID'=>$textDetail['dtl_RecID'], 'rec_Modified'=>$now) );
                            
                        }
                        print "<tr><td><pre>" . "Invalid characters found in ".$textDetail['dty_Name'] 
                                    . " field :</pre></td></tr>\n";
                                    
                        $newText = str_replace($invalidChars ,$replacements,$textDetail['dtl_Value']);
                        
                        $res = mysql__insertupdate($mysqli, 'recDetails', 'dtl_', 
                                    array('dtl_ID'=>$textDetail['dtl_ID'], 'dtl_Value'=>$newText) );
                        
                        
                        if ($res>0) {
                            print "<tr><td><pre>" . "Updated to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
                        }else{
                            print "<tr><td><pre>" . "Error ". res."while updating to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
                        }
                        
                        $is_not_found = false;
                    }
                }

                function check_invalid_chars($text) {
                    global $invalidChars;
                    foreach ($invalidChars as $charCode){
                        if (strpos($text,$charCode)) {
                            return false;
                        }
                    }
                    return true;
                }

                ?>
            </table>

<?php 
if($is_included){ 
    if($is_not_found){
        echo '<script>$("#invalid_msg").text("OK: All records have valid characters in freetext and blocktext fields.").addClass("res-valid");';        
        echo '$(".invalid_chars").css("background-color", "#6AA84F");</script>';        
    }else{
        echo '<script>$(".invalid_chars").css("background-color", "#E60000");</script>';
    }
    print '</div>';
}else{
?>
            <p>
                [end of check]
            </p>
        </div>
    </body>
</html>
<?php } ?>