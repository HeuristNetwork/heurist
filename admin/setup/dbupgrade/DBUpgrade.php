<?php
/**
* DBUpgrade.php - Core database upgrade logic for Heurist.
*
* @fileOverview This file contains the primary function `doUpgradeDatabase` responsible for
*               applying incremental updates to a Heurist database to bring its schema
*               to a newer version. It iterates through version steps, applying corresponding
*               SQL or PHP upgrade scripts.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/setup/dbupgrade
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       1.0 (Assumed, based on function content)
*/

require_once dirname(__FILE__).'/../../../hserv/utilities/DbExecuteScript.php';

/**
 * Upgrades a specified Heurist database from its current version to a target version.
 *
 * This function iteratively applies version upgrade scripts (SQL or PHP) located in
 * the `admin/setup/dbupgrade/` directory. Scripts are named convention-based, e.g.,
 * `DBUpgrade_1.0.0_to_1.1.0.sql`.
 * The process is transactional: if any step fails, changes are rolled back.
 *
 * @param hserv\System $system    The Heurist system object, providing access to database connections and error handling.
 * @param string       $dbname    The name of the database to upgrade (e.g., 'mydata', without 'hdb_' prefix).
 * @param int          $trg_maj   The target major version number for the database schema.
 * @param int          $trg_min   The target minor version number for the database schema.
 * @param bool         $verbose   Optional. If true, progress messages are printed to output. Defaults to false.
 * @return bool True if the upgrade completes successfully for all steps, false otherwise.
 */
function doUpgradeDatabase($system, $dbname, $trg_maj, $trg_min, $verbose=false)
{

    $dir = HEURIST_DIR.'admin/setup/dbupgrade/';

    $mysqli = $system->getMysqli();

    //select database
    if($dbname){
        mysql__usedatabase($mysqli, $dbname);
    }

    $row = mysql__select_row_assoc($mysqli, 'select sys_dbVersion, sys_dbSubVersion from sysIdentification WHERE 1');

    $src_maj = intval( $row['sys_dbVersion'] );
    $src_min = intval( $row['sys_dbSubVersion'] );

    $upgrade_success = true;

    if($src_min>=$trg_min){
        return true;
    }

    $keep_autocommit = mysql__begin_transaction($mysqli);

    while ($src_min<$trg_min) {
        $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).'.0';

        if($trg_maj==1 && $src_min==2){
            $filename = $filename.'.php';
        }else{
            $filename = $filename.'.sql';
        }

        if( file_exists($dir.$filename) ){

            if($trg_maj==1 && $src_min==2){
                include_once $filename;
                $rep = updateDatabseTo_v3($system, $dbname);//PHP
            }elseif(!db_script($dbname, $dir.$filename)){ //SQL
                $system->addError(HEURIST_DB_ERROR, 'Error: Unable to execute '.$filename.' for database '.$dbname
                    .'Please check whether this file is valid');
                $rep = false;
            }else{
                $rep = true;
            }

            if($rep){
                $src_min++;

                if($verbose){
                    if(is_array($rep)){
                        array_walk($rep, function($msg){ echo '<p>'.htmlspecialchars($msg).'</p>';} );
                    }
                    print "<p>Upgraded to $src_maj.$src_min.0</p>";
                }

            }else{
                $error = $system->getError();
                if($verbose && $error){
                    print errorDiv($error['message'].BR.@$error['sysmsg']);
                }

                $upgrade_success = false;
                break;
            }

        }else{
            $sMsg = "<p style='font-weight:bold'>Cannot find the database upgrade script '$filename'</p>";
            if($verbose){
                print $sMsg.CONTACT_HEURIST_TEAM_PLEASE;
            }else{
                $system->addError(HEURIST_SYSTEM_CONFIG, $sMsg);
            }
            $upgrade_success = false;
            break;
        }

    }//while


    mysql__end_transaction($mysqli, $upgrade_success, $keep_autocommit);

    return $upgrade_success;
}
?>
