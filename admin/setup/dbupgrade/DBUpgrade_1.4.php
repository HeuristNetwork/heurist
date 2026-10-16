<?php
/**
*
* Upgrade database from 1.3.19 to 1.4.0
* (origin identity and sync state for records and files, RT_QUERY_SOURCE update)
*
* Modify tables:  Records(rec_OriginatingDBID,rec_IDInOriginatingDB,rec_SyncState, key rec_OriginIdentity),
                  recUploadedFiles(ulf_OriginatingDBID,ulf_IDInOriginatingDB,ulf_SyncState, key ulf_OriginIdentity)
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*
*/

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

ini_set('max_execution_time', '0');

use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../../hserv/structure/import/dbsImport.php';

    define('DBUPGRADE_1_4_DESCRIPTION', 'Modify tables: Records (rec_OriginatingDBID, rec_IDInOriginatingDB, rec_SyncState), '
        .'recUploadedFiles (ulf_OriginatingDBID, ulf_IDInOriginatingDB, ulf_SyncState). '
        .'Add concept identities and completeness states for records and files');

    /**
    * Upgrades database from 1.3.19 to 1.4.0
    *
    * @param hserv\System $system
    * @param string|null $dbname - database to upgrade, current database if not defined
    * @return array|false report or false on failure (error is added to $system)
    */
    function updateDatabseTo_v1_4($system, $dbname=null){

        $mysqli = $system->getMysqli();

        if($dbname){
            mysql__usedatabase($mysqli, $dbname);
        }

        $system->settings->get(null, true);//refresh
        $dbVer = intval($system->settings->get('sys_dbVersion'));
        $dbVerSub = intval($system->settings->get('sys_dbSubVersion'));
        $dbVerSubSub = intval($system->settings->get('sys_dbSubSubVersion'));

        $dbRegisteredID = intval($system->settings->get('sys_dbRegisteredID'));
        
        $report = array();

        $report[] = 'Db version : '.$dbVer.'.'.$dbVerSub.'.'.$dbVerSubSub;

        if(!($dbVer==1 && $dbVerSub==3 && $dbVerSubSub>=19)) {return $report;}

       try{

            [$is_added, $report[]] = alterTable($system, 'Records', 'rec_OriginatingDBID', "ALTER TABLE `Records` ADD COLUMN `rec_OriginatingDBID` MEDIUMINT UNSIGNED DEFAULT NULL COMMENT 'Registered ID of the database where this record originated'", true);
            [$is_added, $report[]] = alterTable($system, 'Records', 'rec_IDInOriginatingDB', "ALTER TABLE `Records` ADD COLUMN `rec_IDInOriginatingDB` INT UNSIGNED DEFAULT NULL COMMENT 'rec_ID assigned by the database where this record originated'", true);
            [$is_added, $report[]] = alterTable($system, 'Records', 'rec_SyncState', "ALTER TABLE `Records` ADD COLUMN `rec_SyncState` ENUM('local','allocated','pending_dependencies','complete') NOT NULL DEFAULT 'local' COMMENT 'Completeness state for restartable master-satellite synchronisation'", true);

            [$is_added, $report[]] = alterTable($system, 'recUploadedFiles', 'ulf_OriginatingDBID', "ALTER TABLE `recUploadedFiles` ADD COLUMN `ulf_OriginatingDBID` MEDIUMINT UNSIGNED DEFAULT NULL COMMENT 'Registered ID of the database where this file reference originated'", true);
            [$is_added, $report[]] = alterTable($system, 'recUploadedFiles', 'ulf_IDInOriginatingDB', "ALTER TABLE `recUploadedFiles` ADD COLUMN `ulf_IDInOriginatingDB` MEDIUMINT UNSIGNED DEFAULT NULL COMMENT 'ulf_ID assigned by the database where this file reference originated'", true);
            [$is_added, $report[]] = alterTable($system, 'recUploadedFiles', 'ulf_SyncState', "ALTER TABLE `recUploadedFiles` ADD COLUMN `ulf_SyncState` ENUM('local','pending_metadata','pending_content','complete') NOT NULL DEFAULT 'local' COMMENT 'Completeness state for restartable master-satellite synchronisation'", true);

            //backfill origin identity for existing records and files - for registered databases only
            $dbRegisteredID = intval($system->settings->get('sys_dbRegisteredID'));
            if($dbRegisteredID>0){
                $queries = array(
                    'Records: origin identity populated' =>
                        "UPDATE Records SET rec_OriginatingDBID=$dbRegisteredID, rec_IDInOriginatingDB=rec_ID"
                        .' WHERE rec_OriginatingDBID IS NULL OR rec_IDInOriginatingDB IS NULL',
                    'recUploadedFiles: origin identity populated' =>
                        "UPDATE recUploadedFiles SET ulf_OriginatingDBID=$dbRegisteredID, ulf_IDInOriginatingDB=ulf_ID"
                        .' WHERE ulf_OriginatingDBID IS NULL OR ulf_IDInOriginatingDB IS NULL'
                );
                foreach($queries as $msg=>$query){
                    if(!$mysqli->query($query)){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot populate origin identity', $mysqli->error);
                        throw new Exception('Cannot populate origin identity');
                    }
                    $report[] = $msg;
                }
            }else{
                $report[] = 'Database is not registered: origin identity is not populated';
            }

            //unique keys are added after backfill
            $keys = array(
                'Records' => array('rec_OriginIdentity', '(rec_OriginatingDBID,rec_IDInOriginatingDB)'),
                'recUploadedFiles' => array('ulf_OriginIdentity', '(ulf_OriginatingDBID,ulf_IDInOriginatingDB)')
            );
            foreach($keys as $table_name=>$key){
                if(hasIndex($mysqli, $table_name, $key[0])){
                    $report[] = "$table_name: key {$key[0]} already exists";
                }elseif($mysqli->query("ALTER TABLE `$table_name` ADD UNIQUE KEY {$key[0]} {$key[1]}")){
                    $report[] = "$table_name: key {$key[0]} added";
                }else{
                    $msg = "Cannot add key {$key[0]} to $table_name";
                    $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                    throw new Exception($msg);
                }
            }

            //Update definitions with DbsImport for RT_QUERY_SOURCE
            /*
            $to_be_imported = array();
            ConceptCode::setSystem($system); //reset cached sys_dbRegisteredID (DBUpgradeAll loops databases)
            if(!isPositiveInt(ConceptCode::getDetailTypeLocalID('2-1165'))){
                $to_be_imported['3-1021'] = 'Record type 3-1021 "Query Source"';
            }
            if(!empty($to_be_imported)){
                    $res = false;
                    $importDef = new DbsImport( $system );
                    if($importDef->doPrepare(  array(
                    'defType'=>'rectype',
                    'databaseID'=>2,
                    'definitionID'=>array_keys($to_be_imported))))
                    {
                        $res = $importDef->doImport();
                    }
                    if($res){
                        $report[] = 'Record type 3-1021 "Query Source" has been updated';
                    }else{
                        //not critical for upgrade - report and continue
                        $error = $system->getError();
                        $report[] = 'Failed to update record type 3-1021 "Query Source"'
                            .($error ?': '.$error['message'] :'');
                        $system->clearError();
                    }
            }
            */

            if(!$mysqli->query('UPDATE sysIdentification SET sys_dbVersion=1, sys_dbSubVersion=4, sys_dbSubSubVersion=0 WHERE 1')){
                $system->addError(HEURIST_DB_ERROR, 'Cannot update database version', $mysqli->error);
                throw new Exception('Cannot update database version');
            }

            $report[] = 'Upgraded to 1.4.0';

       }catch(Throwable $exception){
            if(!$system->getError()){
                $system->addError(HEURIST_DB_ERROR, 'Database upgrade to 1.4.0 failed', $exception->getMessage());
            }
            return false;
       }

        return $report;
    }
?>
