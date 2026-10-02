<?php
/**
* DBUpgradeAll.php - Upgrades all Heurist databases on the server to schema version HEURIST_MIN_DBVERSION.
*
* @fileOverview This script iterates through all databases prefixed with `HEURIST_DB_PREFIX`
*               on the current MySQL server and upgrades each of them with `doUpgradeDatabase`
*               in automatic mode. Databases older than DBUPGRADE_MIN_AUTO_VERSION are not upgraded
*               and must be upgraded manually (upgradeDatabase.php).
*               It outputs a report of databases upgraded, up to date, requiring manual update and failed.
*               This script requires owner-level access.
*
* @project     Heurist academic knowledge management system
* @package Admin/dbupgrade
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       3.1
*/

ini_set('max_execution_time', '0');

define('OWNER_REQUIRED',1);

define('PDIR','../../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../../admin/setup/dbupgrade/DBUpgrade.php';


print '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">';


$mysqli = $system->getMysqli();

    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while ($row = $res->fetch_row()) {
        if( strpos($row[0], HEURIST_DB_PREFIX)===0 ){
                $databases[] = $row[0];
        }
    }

    $db_undef = array();//it seems this is not heurist db
    $reports = array('upgraded'=>array(), 'uptodate'=>array(), 'manual'=>array(), 'error'=>array());

    //2. upgrade databases one by one
    foreach ($databases as $db_name){

        if(!hasTable($mysqli, 'sysIdentification', $db_name)){
            $db_undef[] = $db_name;
            continue;
        }

        $system->clearError();

        $res = doUpgradeDatabase($system, $db_name);

        $status = $res['status'];
        if($status=='upgraded'){
            $info = $res['from'].' => '.$res['to'];
        }elseif($status=='uptodate'){
            $info = $res['from'];
        }elseif($status=='manual'){
            $info = $res['from'].' need manual update';
        }else{
            $info = $res['from'].' '.end($res['report']);
        }
        $reports[$status][$db_name] = $info;
    }//foreach databases

    //restore current database
    $system->clearError();
    mysql__usedatabase($mysqli, $system->dbname());
    $system->settings->get(null, true);

    //3. report
    $titles = array(
        'error' => 'Failed to upgrade',
        'manual' => 'Need manual update (version older than '.DBUPGRADE_MIN_AUTO_VERSION.'). Open database and follow upgrade instructions',
        'upgraded' => 'Upgraded to '.HEURIST_MIN_DBVERSION,
        'uptodate' => 'Up to date'
    );
    foreach ($titles as $status => $title){
        $dbs = $reports[$status];
        print '<p><b>'.$title.'</b>   Cnt: '.count($dbs).'</p>';
        if($status=='uptodate'){
            continue; //no need to list them
        }
        foreach ($dbs as $db_name => $info){
            print ($status=='error') ?errorDiv($db_name.'   '.$info) :htmlspecialchars($db_name.'   '.$info).'<br>';
        }
    }

    if(!isEmptyArray($db_undef)){
        print '<p>It seems these are not Heurist databases</p>';
        foreach ($db_undef as $db_name){
            print htmlspecialchars($db_name).'<br>';
        }
    }

    print '[end report]</div>';
?>
