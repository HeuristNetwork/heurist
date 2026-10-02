<?php
/**
* DBUpgrade.php - Core database upgrade logic for Heurist.
*
* @fileOverview This file contains the primary function `doUpgradeDatabase` responsible for
*               applying incremental updates to a Heurist database to bring its schema
*               to the version HEURIST_MIN_DBVERSION. It is used by
*               initPage.php (automatic upgrade), upgradeDatabase.php (manual verbose upgrade)
*               and DBUpgradeAll.php (upgrade of all databases on server)
*
*               Upgrade sequence:
*               up to 1.2      - sql files DBUpgrade_1.N.0_to_1.N+1.0.sql
*               1.2 to 1.3     - updateDatabseTo_v3 in DBUpgrade_1.2.0_to_1.3.0.php
*               1.3 to 1.3.19  - updateDatabseTo_v1_3_19 in DBUpgrade_1.3.0_to_1.3.19.php
*                                (+recreateRecDetailsDateIndex if source is older than 1.3.14)
*               1.3.19 to 1.4  - updateDatabseTo_v1_4 in DBUpgrade_1.4.php
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

require_once dirname(__FILE__).'/../../../hserv/utilities/DbExecuteScript.php';

// databases older than this version can be upgraded in manual (verbose) mode only
// since recreateRecDetailsDateIndex takes considerable time with extensive report
define('DBUPGRADE_MIN_AUTO_VERSION', '1.3.14');

/**
 * Upgrades a Heurist database from its current version to HEURIST_MIN_DBVERSION.
 *
 * In automatic mode ($manual=false) databases older than DBUPGRADE_MIN_AUTO_VERSION
 * are not upgraded - status 'manual' is returned and database has to be upgraded with upgradeDatabase.php
 *
 * Note: there is no transaction since DDL statements autocommit in MySQL.
 * All steps are idempotent, so upgrade can be repeated after failure.
 *
 * @param hserv\System $system    The Heurist system object
 * @param string|null  $dbname    The name of database to upgrade. If not set - current database
 * @param bool         $verbose   If true, progress messages are printed to output
 * @param bool         $manual    If true, all steps are allowed (including long date index rebuild)
 * @return array  ['status'=>'uptodate'|'upgraded'|'manual'|'error', 'from'=>version, 'to'=>version, 'report'=>array of messages]
 *                for 'error' the details are in $system->getError()
 */
function doUpgradeDatabase($system, $dbname=null, $verbose=false, $manual=false)
{
    $mysqli = $system->getMysqli();

    //select database
    if($dbname){
        $res = mysql__usedatabase($mysqli, $dbname);
        if($res!==true){
            $system->addError($res[0], $res[1]);
            return array('status'=>'error', 'from'=>'', 'to'=>'', 'report'=>array($res[1]));
        }
    }

    $res = array('status'=>'uptodate', 'from'=>'', 'to'=>'', 'report'=>array());

    $version = __dbUpgradeGetVersion($system);
    if($version==null){
        $res['status'] = 'error';
        return $res;
    }
    [$src_maj, $src_min, $src_sub] = $version;
    $res['from'] = $res['to'] = "$src_maj.$src_min.$src_sub";

    if(version_compare($res['from'], HEURIST_MIN_DBVERSION) >= 0){
        return $res; //up to date
    }

    $trg_maj = intval(explode('.', HEURIST_MIN_DBVERSION)[0]);

    if($src_maj!=$trg_maj){
        $msg = 'Automatic upgrade applies to minor version updates only (ie. within database version 1, 2 etc.). '
                .'Database version is '.$res['from'];
        $system->addError(HEURIST_ACTION_BLOCKED, $msg);
        $res['status'] = 'error';
        $res['report'][] = $msg;
        __dbUpgradePrint($res['report'], $verbose);
        return $res;
    }

    if(!$manual && version_compare($res['from'], DBUPGRADE_MIN_AUTO_VERSION) < 0){
        $res['status'] = 'manual';
        $res['report'][] = 'Database version '.$res['from'].' needs manual update (older than '
                .DBUPGRADE_MIN_AUTO_VERSION.'). Please use "Upgrade database" page (admin/setup/dbupgrade/upgradeDatabase.php)';
        return $res;
    }

    $dir = dirname(__FILE__).'/';
    $is_success = true;

    try{

        while($is_success && version_compare("$src_maj.$src_min.$src_sub", HEURIST_MIN_DBVERSION) < 0){

            $rep = false;

            if($src_min<2){
                //up to 1.2 - sql scripts
                $filename = "DBUpgrade_$src_maj.$src_min.0_to_$src_maj.".($src_min+1).'.0.sql';
                if(__dbUpgradeExecuteScript($system, $dir.$filename)){
                    $mysqli->query('UPDATE sysIdentification SET sys_dbSubVersion='.($src_min+1).', sys_dbSubSubVersion=0 WHERE 1');
                    $rep = array("Upgraded to $src_maj.".($src_min+1).'.0');
                }

            }elseif($src_min==2){

                include_once $dir.'DBUpgrade_1.2.0_to_1.3.0.php';
                $rep = updateDatabseTo_v3($system);
                if($rep!==false){
                    $rep[] = 'Upgraded to 1.3.0';
                }

            }elseif($src_min==3 && $src_sub<19){

                include_once $dir.'DBUpgrade_1.3.0_to_1.3.19.php';
                $rep = updateDatabseTo_v1_3_19($system);

                if($rep!==false && $src_sub<14){
                    $system->settings->get(null, true);
                    $rep2 = recreateRecDetailsDateIndex($system, true, true);
                    $rep = $rep2 ?array_merge($rep, $rep2) :false;
                }

            }elseif($src_min==3){

                include_once $dir.'DBUpgrade_1.4.php';
                $rep = updateDatabseTo_v1_4($system);

            }else{
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Upgrade script for database version '
                        ."$src_maj.$src_min.$src_sub is not defined. ".CONTACT_HEURIST_TEAM_PLEASE);
            }

            if($rep===false){
                $is_success = false;
                break;
            }

            __dbUpgradePrint($rep, $verbose);
            $res['report'] = array_merge($res['report'], $rep);

            $prev_version = "$src_maj.$src_min.$src_sub";
            $version = __dbUpgradeGetVersion($system);
            if($version==null){
                $is_success = false;
                break;
            }
            [$src_maj, $src_min, $src_sub] = $version;

            if($prev_version=="$src_maj.$src_min.$src_sub"){
                //prevent infinite loop
                $system->addError(HEURIST_DB_ERROR, "Database version $prev_version was not changed by upgrade step");
                $is_success = false;
            }
        }//while

    }catch(Throwable $exception){
        error_log('Heurist database upgrade failed: '.$exception);
        $system->addError(HEURIST_DB_ERROR, 'Database upgrade stopped', $exception->getMessage());
        $is_success = false;
    }

    $res['to'] = "$src_maj.$src_min.$src_sub";

    if($is_success){
        $res['status'] = 'upgraded';
    }else{
        $res['status'] = 'error';
        $error = $system->getError();
        $msg = $error
                ? $error['message'].' '.($error['sysmsg'] ?? '')
                : 'Database upgrade failed without a reported error. Check the PHP error log.';
        $res['report'][] = $msg;
        if($verbose){
            print errorDiv($msg);
        }
    }

    return $res;
}

/**
 * Reloads settings and returns version of current database
 *
 * @param hserv\System $system
 * @return array|null [major, minor, subsub] or null if sysIdentification can not be read
 */
function __dbUpgradeGetVersion($system){
    if(!$system->settings->get(null, true)){
        return null;
    }
    return array(
        intval($system->settings->get('sys_dbVersion')),
        intval($system->settings->get('sys_dbSubVersion')),
        intval($system->settings->get('sys_dbSubSubVersion')));
}

/**
 * Prints report lines
 */
function __dbUpgradePrint($report, $verbose){
    if($verbose && is_array($report)){
        foreach($report as $msg){
            if($msg){
                print '<p>'.$msg.'</p>';
            }
        }
    }
}

/**
 * Executes a given SQL upgrade script file against the current database.
 *
 * @param hserv\System $system
 * @param string $filename The full path to the SQL script file to be executed.
 * @return bool True if the script execution was successful, false otherwise (error is added to $system)
 */
function __dbUpgradeExecuteScript($system, $filename){

    $upgradeDir = realpath(dirname(__FILE__));
    $scriptPath = realpath($filename);

    if (
        $upgradeDir === false ||
        $scriptPath === false ||
        strpos($scriptPath, $upgradeDir . DIRECTORY_SEPARATOR) !== 0 ||
        !preg_match('/^DBUpgrade_[A-Za-z0-9._]+\.sql$/', basename($scriptPath)) ||
        !is_file($scriptPath) ||
        !is_readable($scriptPath)
    ) {
        $system->addError(HEURIST_SYSTEM_CONFIG, 'Cannot find the database upgrade script '
                .basename($filename).'. '.CONTACT_HEURIST_TEAM_PLEASE);
        return false;
    }

    $mysqli = $system->getMysqli();
    $dbname = mysql__select_value($mysqli, 'SELECT DATABASE()');

    if(db_script($dbname, $scriptPath)){
        return true;
    }

    $system->addError(HEURIST_DB_ERROR, 'Unable to execute '.basename($filename).' for database '.$dbname
            .'. Please check whether this file is valid. '.CONTACT_HEURIST_TEAM_PLEASE);
    return false;
}
?>
