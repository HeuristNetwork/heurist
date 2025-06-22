<?php
/**
* DBUpgradeAll.php - Upgrades all Heurist databases on the server to schema version 1.3.
*
* @fileOverview This script iterates through all databases prefixed with `HEURIST_DB_PREFIX`
*               on the current MySQL server. For each database found to be on a version
*               less than 1.3 (specifically, major version 1, minor version less than 3),
*               it attempts to upgrade it to version 1.3 using the `doUpgradeDatabase` function.
*               It outputs a report of databases processed, upgraded, or any errors encountered.
*               This script requires owner-level access.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/setup/dbupgrade
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

    $db = array();
    $cnt = 0;

    foreach ($databases as $idx=>$db_name){

        $query = 'SELECT sys_dbSubVersion from '.$db_name.'.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);


        if( (!isPositiveInt($ver)) || $ver<3){

            if(!hasTable($mysqli, 'sysIdentification',$db_name)){
                $db_undef[] = $db_name;
                continue;
            }

            if(!@$db[$ver]){
                $db[$ver] = array($db_name);
            }else{
                array_push($db[$ver], $db_name);
            }

            $res = doUpgradeDatabase($system, $db_name, 1, 3, false);
            if(!$res){

                print errorDiv('Error: Unable upgrade '.htmlspecialchars($db_name));

                $error = $system->getError();
                if($error){
                    print errorDiv($error['message'].BR.@$error['sysmsg']);
                }
                break;
            }

            $cnt++;

        }else{
            //check that v1.3 has


        }


    }//while  databases


    if(!isEmptyArray($db_undef)){
        print '<p>It seems these are not Heurist databases</p>';
        foreach ($db_undef as $db_name){
            print htmlspecialchars($db_name).'<br>';
        }
    }
    if(!isEmptyArray($db)){
        foreach ($db as $ver => $dbs){
           print '<p>List of databases with v 1.'.$ver.'   Cnt: '.count($dbs).'</p>';
           foreach ($dbs as $db_name){
                print htmlspecialchars($db_name).'<br>';
           }
        }
    }

    print '[end report]</div>';
?>
