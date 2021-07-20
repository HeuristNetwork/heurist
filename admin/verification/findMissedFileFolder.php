<?php

/**
* findMissedFileFolder - list of all databases w/o upload folder
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
ini_set('max_execution_time', 0);

//define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

    $mysqli = $system->get_mysqli();
    
    $counter = 0;
            
    $dbs = mysql__getdatabases4($mysqli, true);   
    $inaccessible = array();
    $missed = array();
             
    foreach ($dbs as $db){
        
        //if($counter>50) break;
        $counter++;
        
        $dbName = substr($db,4);
        $folder = HEURIST_FILESTORE_ROOT . $dbName . '/';
        if(file_exists($folder) && is_dir($folder)){
            if (!is_writable($folder)) {
                array_push($inaccessible, $db);    
            }
        }else{
            array_push($missed, $db);
        }
    }//for

    if(count($missed)>0 || count($inaccessible)>0){
        $rep = '<h2>List of databases with missing/inaccessible upload folder</h2><hr>';
        if(count($missed)>0){
            $rep .= '<h3>Missed</h3>';
            foreach ($missed as $db){
                $rep .= ($db.'<br>');
            }
        }    
        if(count($inaccessible)>0){
            $rep .= '<h3>Inaccessible</h3>';
            foreach ($inaccessible as $db){
                $rep .= ($db.'<br>');
            }
        }    
    }
    if(@$_REQUEST['mail']){
        
        $email_header = " <no-reply@".HEURIST_SERVER_NAME.">\r\nContent-Type: text/html;";
        $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'List of databases with missing/inaccessible upload folder', 
                                            $rep, $email_header);
        exit();
    }
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <?php print $rep; ?>
    </body>
</html>
