<?php

/**
* listDatabases.php: Produces page listing available databases
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

if(!defined('PDIR')){
    define('PDIR','../');
    require_once dirname(__FILE__).'/../hserv/System.php';
}

$is_json = (@$_REQUEST['format']=='json');

if(!isset($system)){
    $system = new System();
}

if(!$system->is_inited()){
    $system->init(@$_REQUEST['db'], false);//init wihout db
}

if( !$system->is_inited() ){  //cannot init system (apparently connection to Database Server is wrong or server is down)
    $err = $system->getError();
    $error_msg = @$err['message'];
}

if($system->get_mysqli()!=null) { //server is connected

    $list =  mysql__getdatabases4($system->get_mysqli());
    if(!$is_json && count($list)<1){
        //redirect to create database
        header('Location: ' . HEURIST_BASE_URL . 'startup/index.php');
        exit;
    }
}

if($is_json){

    header( 'Content-Type: application/json');

    if(isset($error_msg) && $error_msg!=''){
        $response = $system->getError();
    }else{
        $response = array("status"=>HEURIST_OK, "data"=> $list);
    }

    print json_encode( $response );
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title><?=HEURIST_TITLE?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel=icon href="<?php echo PDIR?>favicon.ico" type="image/x-icon">

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../hclient/framecontent/initPageCss.php';?>

        <script type="text/javascript">
        </script>

    </head>
    <body class="ui-heurist-header1">
        <div class="ui-corner-all ui-widget-content"
            style="text-align:left; min-width:220px; padding: 0.5em; position:absolute; top:30px; bottom:30px; left:60px;right:60px">


            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <?php
            if(isset($error_msg) && $error_msg!=''){
                echo '<div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">';
                echo '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>';
                echo $error_msg.'</div>';
                $list_top = '12em';
            }else{
                $list_top = '6em';
            }

            if(isset($list)){
                ?>
                <div style="padding: 0.5em;">Please select a database from the list (^F to find a database by name):</div>
                <div style="overflow-y:auto;position:absolute;top:<?php echo $list_top;?>;bottom:0.5em;left:1em;right:0.5em">
                <ul class="db-list">
                    <?php
                    foreach ($list as $name) {
                        print "<li><a href='".HEURIST_BASE_URL."?db=$name'>$name</a></li>";
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
