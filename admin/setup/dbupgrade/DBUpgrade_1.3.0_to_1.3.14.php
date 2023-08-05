<?php
/**
*             
* Modify tables:  defRecStructure(rst_SemanticReferenceURL,rst_TermsAsButtons,rst_PointerMode,rst_NonOwnerVisibility),  
                  recUploadedFiles(ulf_PreferredSource),   
                  defTerms (trm_OrderInBranch), 
                  recDetails(dtl_HideFromPublic), 
                  sysIdentification(sys_NakalaKey), 
                  sysUGrps(usr_ExternalAuthentication)
* Add tables:     sysWorkflowRules,defTranslations,recDetailsDateIndex
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
    
    
    function updateDatabseTo_v1_3_12($system, $dbname=null){
        //update sysIdentification set sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=4 where sys_ID=1

        $mysqli = $system->get_mysqli();
        
        if($dbname){
            mysql__usedatabase($mysqli, $dbname);
        }
        
        $sysValues = $system->get_system(null, true); //refresh
        $dbVer = $system->get_system('sys_dbVersion');
        $dbVerSub = $system->get_system('sys_dbSubVersion');
        $dbVerSubSub = $system->get_system('sys_dbSubSubVersion');
        
        $report = array();

        if($dbVer==1 && $dbVerSub==3 && $dbVerSubSub<13){

            /*
            if($dbVerSub<3){//not used

                //adds trash groups if they are missed
                if(!(mysql__select_value($mysqli, 'select rtg_ID FROM defRecTypeGroups WHERE rtg_Name="Trash"')>0)){
                    $query = 'INSERT INTO defRecTypeGroups (rtg_Name,rtg_Order,rtg_Description) '
                    .'VALUES ("Trash",255,"Drag record types here to hide them, use dustbin icon on a record type to delete permanently")';
                    $mysqli->query($query);
                }

                if(!(mysql__select_value($mysqli, 'select vcg_ID FROM defVocabularyGroups WHERE vcg_Name="Trash"')>0)){
                    $query = 'INSERT INTO defVocabularyGroups (vcg_Name,vcg_Order,vcg_Description) '
                    .'VALUES ("Trash",255,"Drag vocabularies here to hide them, use dustbin icon on a vocabulary to delete permanently")';
                    $mysqli->query($query);
                }

                if(!(mysql__select_value($mysqli, 'select dtg_ID FROM defDetailTypeGroups WHERE dtg_Name="Trash"')>0)){
                    $query = 'INSERT INTO defDetailTypeGroups (dtg_Name,dtg_Order,dtg_Description) '
                    .'VALUES ("Trash",255,"Drag base fields here to hide them, use dustbin icon on a field to delete permanently")';        
                    $mysqli->query($query);
                }

                if(!array_key_exists('sys_ExternalReferenceLookups', $sysValues))
                {
                    $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_ExternalReferenceLookups` TEXT default NULL COMMENT 'Record type-function-field specifications for lookup to external reference sources such as GeoNames'";
                    $res = $mysqli->query($query);
                }

            }//for v2
            */

            if($dbVerSubSub<1){

                if(!hasColumn($mysqli, 'defRecStructure', 'rst_SemanticReferenceURL')){
                    //alter table
                    $query = "ALTER TABLE `defRecStructure` ADD `rst_SemanticReferenceURL` VARCHAR( 250 ) NULL "
                    ." COMMENT 'The URI to a semantic definition or web page describing this field used within this record type' "
                    .' AFTER `rst_LocallyModified`';
                    $res = $mysqli->query($query);
                    
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to add rst_SemanticReferenceURL', $mysqli->error);
                        return false;
                    }
                    $report[] = 'defRecStructure:rst_SemanticReferenceURL added';
                } else {
                    $report[] = 'defRecStructure:rst_SemanticReferenceURL already exists';
                } 

                if(!hasColumn($mysqli, 'defRecStructure', 'rst_TermsAsButtons')){
                    //alter table
                    $query = "ALTER TABLE `defRecStructure` ADD `rst_TermsAsButtons` TinyInt( 1 ) DEFAULT '0' "
                    ." COMMENT 'If 1, term list fields are represented as buttons (if single value) or checkboxes (if repeat values)' "
                    .' AFTER `rst_SemanticReferenceURL`';
                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to add rst_TermsAsButtons', $mysqli->error);
                        return false;
                    }
                    $report[] = 'defRecStructure:rst_TermsAsButtons added';
                } else {
                    $report[] = 'defRecStructure:rst_TermsAsButtons already exists';
                } 

                if(!hasColumn($mysqli, 'defTerms', 'trm_Label', null, 'varchar(250)')){

                    $mysqli->query('update defTerms set trm_Label = substr(trm_Label,1,250)');

                    $query = "ALTER TABLE `defTerms` "
                    ."CHANGE COLUMN `trm_Label` `trm_Label` VARCHAR(250) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank' ,"
                    ."CHANGE COLUMN `trm_NameInOriginatingDB` `trm_NameInOriginatingDB` VARCHAR(250) NULL DEFAULT NULL COMMENT 'Name (label) for this term in originating database'" ;

                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify defTerms to change trm_Label and trm_NameInOriginatingDB', $mysqli->error);
                        return false;
                    }
                    $report[] = 'defTerms:trm_Label modified';
                } else {
                    $report[] = 'defTerms:trm_Label already 250 characters';
                } 
            }
            if($dbVerSubSub<2){

                $query = "ALTER TABLE `defRecStructure` "
                ."CHANGE COLUMN `rst_PointerMode` `rst_PointerMode` enum('dropdown_add','dropdown','addorbrowse','addonly','browseonly') DEFAULT 'dropdown_add' COMMENT 'When adding record pointer values, default or null = show both add and browse, otherwise only allow add or only allow browse-for-existing'";
                $res = $mysqli->query($query);
                if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to change rst_PointerMode', $mysqli->error);
                    return false;
                }
                $report[] = 'defRecStructure:rst_PointerMode modified';
            }

            if($dbVerSubSub<4){
                if(hasTable($mysqli, 'sysWorkflowRules')){
                    $query = 'DROP TABLE IF EXISTS sysWorkflowRules';
                    $res = $mysqli->query($query);
                }
                $query = "CREATE TABLE sysWorkflowRules  (
                swf_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key',
                swf_RecTypeID  smallint unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
                swf_Stage int unsigned NOT NULL default '0' COMMENT 'trm_ID from vocabulary \"Workflow stage\" 2-9453',
                swf_Order tinyint(3) unsigned zerofill NOT NULL default '000' COMMENT 'Ordering of stage per record type',
                swf_StageRestrictedTo varchar(255) default NULL Comment 'Comma separated list of ugr_ID who are allowed to set workgroup stage to this value. Null = anyone',
                swf_SetOwnership smallint NULL default NULL COMMENT 'Workgroup to be set as the owner group, Null = No change, 0=everyone',
                swf_SetVisibility  varchar(255) default NULL COMMENT 'public=anyone, viewable=all logged in, hidden = private, hidden may be followed by comma separated list of ugr_ID that should be set to have view permission',
                swf_SendEmail  varchar(255) default NULL COMMENT 'Comma separated list of ugr_ID that will be emailed on stage change',
                PRIMARY KEY (swf_ID),
                UNIQUE KEY swf_StageKey (swf_RecTypeID, swf_Stage)
                ) ENGINE=InnoDB COMMENT='Describes the rules to be applied when the value of the Workflow stage field is changed to this value'";
                $res = $mysqli->query($query);
                if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot create sysWorkflowRules table', $mysqli->error);
                    return false;
                }
                $report[] = 'sysWorkflowRules created';
            }

            if($dbVerSubSub<5){

                $query = "ALTER TABLE `recUploadedFiles` "
                ."CHANGE COLUMN `ulf_PreferredSource` `ulf_PreferredSource` enum('local','external','iiif','iiif_image','tiled') "
                ."NOT NULL default 'local' COMMENT 'Preferred source of file if both local file and external reference set'";

                $res = $mysqli->query($query);
                if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot modify recUploadedFiles to change ulf_PreferredSource', $mysqli->error);
                    return false;
                }
                $report[] = 'recUploadedFiles:ulf_PreferredSource modified';

                if(hasTable($mysqli, 'defCalcFunctions')){
                    $query = 'DROP TABLE IF EXISTS defCalcFunctions';
                    $res = $mysqli->query($query);
                }

                $query = "CREATE TABLE defCalcFunctions (
                cfn_ID smallint(3) unsigned NOT NULL auto_increment COMMENT 'Primary key of defCalcFunctions table',
                cfn_Name varchar(63) NOT NULL COMMENT 'Descriptive name for function',
                cfn_Domain enum('calcfieldstring','pluginphp') NOT NULL default 'calcfieldstring' COMMENT 'Domain of application of this function specification',
                cfn_FunctionSpecification text COMMENT 'A function or chain of functions, or some PHP plugin code',
                cfn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
                cfn_RecTypeIDs varchar(250) default NULL COMMENT 'CSV list of Rectype IDs that participate in formula',
                PRIMARY KEY  (cfn_ID)
                ) ENGINE=InnoDB COMMENT='Specifications for generating calculated fields, plugins and'";

                $res = $mysqli->query($query);
                if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot create defCalcFunctions table', $mysqli->error);
                    return false;
                }
                $report[] = 'defCalcFunctions created';
            }

            if($dbVerSubSub<6){

                if(!hasColumn($mysqli, 'defTerms', 'trm_OrderInBranch')){
                    //alter table
                    $query = "ALTER TABLE `defTerms` ADD `trm_OrderInBranch`  smallint(5) NULL Comment 'Defines sorting order of terms if non-alphabetic. Operates only within a single branch, including root' ";
                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify defTerms to add trm_OrderInBranch', $mysqli->error);
                        return false;
                    }
                    $report[] = 'defTerms:trm_OrderInBranch added';
                } else {
                    $report[] = 'defTerms:trm_OrderInBranch already exists';
                } 
            }

            if($dbVerSubSub<7){

                if(!hasColumn($mysqli, 'recDetails', 'dtl_HideFromPublic')){
                    //alter table
                    $query = "ALTER TABLE `recDetails` ADD `dtl_HideFromPublic` tinyint(1) unsigned default null Comment 'If set, the value is not shown in Record View, column lists, custom reports or anywhere the value is displayed. It may still be used in filter or analysis'";

                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify recDetals to add dtl_HideFromPublic', $mysqli->error);
                        return false;
                    }

                    //set default value for rst_NonOwnerVisibility as public
                    $query = "ALTER TABLE `defRecStructure` "
                    ."CHANGE COLUMN `rst_NonOwnerVisibility` `rst_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL default 'public' COMMENT 'Allows restriction of visibility of a particular field in a specified record type'";
                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to change rst_NonOwnerVisibility', $mysqli->error);
                        return false;
                    }
                    $query = "UPDATE `defRecStructure` SET `rst_NonOwnerVisibility`='public' WHERE rst_ID>0";
                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to set rst_NonOwnerVisibility=public', $mysqli->error);
                        return false;
                    }
                    $report[] = 'recDetails:dtl_HideFromPublic added';
                    $report[] = 'defRecStructure:rst_NonOwnerVisibility modified';
                } else {
                    $report[] = 'recDetails:dtl_HideFromPublic already exists';
                } 
            }
            
            if(!array_key_exists('sys_NakalaKey', $sysValues)){ //$dbVerSubSub<9 && 

                if(!hasColumn($mysqli, 'sysIdentification', 'sys_NakalaKey')){
                    $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_NakalaKey` TEXT default NULL COMMENT 'Nakala API key. Retrieved from Nakala website'";
                    $res = $mysqli->query($query);
                    if($res){
                        $usr_prefs = user_getPreferences($system);
                        if(array_key_exists('nakala_api_key', $usr_prefs)){
                            $query = "UPDATE `sysIdentification` SET sys_NakalaKey='"
                            .$mysqli->real_escape_string($usr_prefs['nakala_api_key'])."' WHERE 1";
                            $res = $mysqli->query($query);
                        }
                    }else{
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify sysIdentification to add sys_NakalaKey', $mysqli->error);
                        return false;
                    }                    
                    $report[] = 'sysIdentification:sys_NakalaKey added';
                } else {
                    $report[] = 'sysIdentification:sys_NakalaKey already exists';
                } 
            }

            if(!hasTable($mysqli, 'defTranslations')){ //$dbVerSubSub<10 || 

                $mysqli->query('DROP TABLE IF EXISTS defTranslations;');
                $mysqli->query("CREATE TABLE defTranslations (
                    trn_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key of defTranslations table',
                    trn_Source varchar(64) NOT NULL COMMENT 'The column to be translated (unique names identify source)',
                    trn_Code int unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
                    trn_LanguageCode char(3) NOT NULL COMMENT 'The translation language code ISO639',
                    trn_Translation text NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
                    trn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
                    PRIMARY KEY  (trn_ID),
                    UNIQUE KEY trn_composite (trn_Source,trn_Code,trn_LanguageCode),
                    KEY trn_LanguageCode (trn_LanguageCode)
                ) ENGINE=InnoDB COMMENT='Translation table into multiple languages for all translatab';");
                $report[] = 'defTranslations created';
            } else {
                $report[] = 'defTranslations already exists';
            } 

            if(!hasColumn($mysqli, 'sysUGrps', 'usr_ExternalAuthentication')){ //$dbVerSubSub<11
                    //alter table
                    $query = "ALTER TABLE `sysUGrps` ADD `usr_ExternalAuthentication` varchar(1000) default NULL COMMENT 'JSON array with external authentication preferences'";
                    $res = $mysqli->query($query);
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot modify sysUGrps to add usr_ExternalAuthentication', $mysqli->error);
                        return false;
                    }
                    $report[] = 'sysUGrps:usr_ExternalAuthentication added';
            } else {
                    $report[] = 'sysUGrps:usr_ExternalAuthentication already exists';
            }

            if($dbVerSubSub<12){
                if(!checkUserStatusColumn($system)){
                    return false;
                }
                $report[] = 'sysUGrps:ugr_Enabled modified';
            }


            /*
            if($dbVerSubSub<13){
            if(!recreateRecDetailsDateIndex( $system, false, false )){
            return false;
            }
            }
            */

            //update version
            if($dbVerSubSub<12){
                $mysqli->query('UPDATE sysIdentification SET sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=12 WHERE 1');
            }


            //import field 2-1080 Workflowstages
            if($dbVerSubSub<4 && !(ConceptCode::getDetailTypeLocalID('2-1080')>0)){
                $importDef = new DbsImport( $system );
                if($importDef->doPrepare(  array(
                'defType'=>'detailtype', 
                'databaseID'=>2, 
                'conceptCode'=>'2-1080')))
                {
                    $res = $importDef->doImport();
                }
                if($res){
                    $report[] = 'Field 2-1080 "Workflow stages imported';    
                }
            }

        }
        return $report;
    }  
?>
