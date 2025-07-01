<?php
/**
* resetDB.php - Removes and recreates a demo database, typically for a daily cron job.
*
* @fileOverview This script is designed to reset a specific Heurist demo database (identified by
*               the constants `DEMO_DB` and `DEMO_DB_TEMPLATE`). It first drops the existing
*               demo database. Then, if `DEMO_DB_ONLY` is true, it creates a new empty database.
*               Otherwise, it creates the new database and clones it from the `DEMO_DB_TEMPLATE`,
*               copies the filestore, and adjusts user #2 to be 'guest' with a 'guest' password.
*               This is often used as a cron job to ensure a clean demo environment.
*
* @project     Heurist academic knowledge management system
* @package Admin/dboperations
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4
*/



use hserv\utilities\DbUtils;

require_once dirname(__FILE__).'/../../../autoload.php';
require_once dirname(__FILE__).'/../../../hserv/structure/dbsUsersGroups.php';


define('DEMO_DB', 'hdb_demo');
define('DEMO_DB_TEMPLATE', 'hdb_demo_template');
define('DEMO_DB_ONLY', false);

set_time_limit(0);

$system = new hserv\System();

$res = false;

$isSystemInited = $system->init(DEMO_DB);

if($isSystemInited){

    $mysqli = $system->getMysqli();
    $user_record = user_getById($mysqli, 2);

    $res = DbUtils::databaseDrop(false, DEMO_DB, false);

    if($res) {

        //clone
        if(DEMO_DB_ONLY){
            //new empty
            $res = DbUtils::databaseCreateFull(DEMO_DB, $user_record);
        }else{
            $res = false;
            if(DbUtils::databaseCreate(DEMO_DB, 1)){
                if( DbUtils::databaseClone(DEMO_DB_TEMPLATE, DEMO_DB, false, false, false) ){
                    if(DbUtils::databaseCreateConstraintsAndTriggers(DEMO_DB)){

                        $source_db = substr(DEMO_DB_TEMPLATE, 4);
                        $target_db = substr(DEMO_DB, 4);
                        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_db, HEURIST_FILESTORE_ROOT.$target_db );
                        $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$target_db.
                        "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$source_db."/' and ulf_ID>0";
                        $res1 = $mysqli->query($query1);

                        //change user#2 to guest
                        mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>2, 'ugr_Name'=>'guest',
                                    'ugr_Password' => hash_it( 'guest' ) ) );

                        $res = true;
                    }
                }
            }
        }
    }
}

if(is_bool($res) && !$res){
    $response = $system->getError();
    $response = $response['message'];
}elseif(is_array($res) && !empty($res)){
    $response = 'not able to create all file directories '.implode(', ',$res);
}else{
    $response = 'Database '.DEMO_DB.' has been reset';
}

print $response;
