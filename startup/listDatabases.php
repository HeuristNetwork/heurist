<?php
/**
* listDatabases.php - Produces a page listing available databases or returns a JSON list.
* @fileOverview This script lists all available Heurist databases. It can either render an HTML page with the list or return a JSON formatted list if requested. It also handles basic system initialization and error reporting for database connectivity.
* @project     Heurist academic knowledge management system
* @package  Startup
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson ian.johnson.heurist@gmail.com
* @since       4.0
*/

if(!defined('PDIR')){
    define('PDIR','../');
    require_once dirname(__FILE__).'/../autoload.php';
}

$is_json = @$_REQUEST['format'] == 'json';
$list = [];

if(!isset($system)){
    $system = new hserv\System();
}

if(!$system->isInited()){
    $system->init(@$_REQUEST['db'], false);//init wihout db
}

if( !$system->isInited() ){  //cannot init system (apparently connection to Database Server is wrong or server is down)
    $err = $system->getError();
    $error_msg = @$err['message'];
}

if($system->getMysqli()!=null) { //server is connected

    $list = mysql__getdatabases4($system->getMysqli());
    if(!$is_json && empty($list)){
        //redirect to create database
        redirectURL(HEURIST_BASE_URL . 'startup/index.php');
        exit;
    }
}

if(@$_REQUEST['includeRemote'] == 1 && !empty($remoteServers) && !empty($defaultRootFileUploadPath)){

    $fileStoreBase = str_replace('HEURIST_FILESTORE/', 'HEURIST_FILESTORE_', $defaultRootFileUploadPath);
    $list = [ 'current' => $list, 'server_names' => [] ];

    foreach($remoteServers as $remoteID => $remoteDetails){

        $remoteFilestore = "{$fileStoreBase}{$remoteID}/";
        if(folderExists($remoteFilestore, false) !== 1){
            continue;
        }

        $remoteDBs = folderGetSubFolders($remoteFilestore);
        $remoteDBs = array_filter($remoteDBs, fn($db) => mb_strpos($db, '_') !== 0);

        natcasesort($remoteDBs);
        $list[$remoteID] = array_values($remoteDBs);

        $list['server_names'][$remoteID] = $remoteDetails['server'];
    }
}

if($is_json){

    header( CTYPE_JSON);

    if(isset($error_msg) && $error_msg!=''){
        $response = $system->getError();
    }else{
        $response = ["status"=>HEURIST_OK, "data"=> $list];
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
        <meta name="robots" content="nofollow">
        
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
                echo $error_msg.DIV_E;
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
                        $name = htmlentities($name);
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
