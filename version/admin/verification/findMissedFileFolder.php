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

define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

$mysqli = $system->get_mysqli();
?>
<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
        </style>

    </head>


    <body class="popup">

        <div class="banner">
            <h2>List of databases without upload folder</h2>
        </div>

        <div><br/><br/>
            
        </div>
        <hr>

        <div id="page-inner">

            <?php

    $counter = 0;
            
    $dbs = mysql__getdatabases4($mysqli, true);            
    foreach ($dbs as $db){
        
        //if($counter>50) break;
        $counter++;
        
        $dbName = substr($db,4);
        $fpath = HEURIST_FILESTORE_ROOT . $dbName . '/';
        if(!file_exists($fpath)){
            print "<h2>".$db."</h2>";
        }
            
    }//for
            
            ?>
        </div>
    </body>
</html>
