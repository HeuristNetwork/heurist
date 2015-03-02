<?php

    /**
    * Produces the page - list of available databases
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once(dirname(__FILE__)."/System.php");

    $system = new System();
    $isSystemInited = $system->init(@$_REQUEST['db'], false);

    if( !$isSystemInited ){  //can not init system (apparently connection to DB is wrong)
        $err = $system->getError();
        $error_msg = @$err['message'];
    }else if (@$_REQUEST['msg']){
        $error_msg = $_REQUEST['msg'];
    }
    
    
?>
<html>
    <head>
        <title><?=HEURIST_TITLE?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel="stylesheet" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../style3.css">

        <script type="text/javascript">
        </script>

    </head>
    <body style="padding:44px;">
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; margin:0px auto; padding: 0.5em;">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>
            
<?php
    if(isset($error_msg)){
            echo '<div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">';
            echo '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>';
            echo $error_msg.'</div>';
    }

    if($isSystemInited){
?>
            
            
            <div style="padding: 0.5em;">Please select a database from the list</div>
            <div style="overflow-y:auto;display: inline-block;width:100%;height:80%">

                <ul class="db-list">
                    <?php
                        $list =  mysql__getdatabases($system->get_mysqli());

                        /* DEBUG for($i=0;$i<100;$i++) {
                        array_push($list, "database".$i);
                        }*/
                        foreach ($list as $name) {
                            print("<li><a href='".HEURIST_BASE_URL."?db=$name'>$name</a></li>");
                        }

                    ?>
                </ul>

            </div>
            
<?php
    }
?>
            

        </div>
    </body>
</html>
