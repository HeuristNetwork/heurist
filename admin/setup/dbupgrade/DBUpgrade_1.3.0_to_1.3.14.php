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

use hserv\structure\ConceptCode;

    function updateDatabseTo_v1_3_12($system, $dbname=null){
        //update sysIdentification set sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=4 where sys_ID=1

        $mysqli = $system->get_mysqli();

        if($dbname){
            mysql__usedatabase($mysqli, $dbname);
        }

        $sysValues = $system->get_system(null, true);//refresh
        $dbVer = $system->get_system('sys_dbVersion');
        $dbVerSub = $system->get_system('sys_dbSubVersion');
        $dbVerSubSub = $system->get_system('sys_dbSubSubVersion');

        $report = array();

        if(!($dbVer==1 && $dbVerSub==3 && $dbVerSubSub<13)) {return $report;}

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

        try{
            
        if($dbVerSubSub<1){

            list($is_added,$report[]) = alterTable($system, 'defRecStructure', 'rst_SemanticReferenceURL', "ALTER TABLE `defRecStructure` ADD COLUMN `rst_SemanticReferenceURL` VARCHAR( 250 ) NULL "
                    ." COMMENT 'The URI to a semantic definition or web page describing this field used within this record type' "
                    ." AFTER `rst_LocallyModified`");

            list($is_added,$report[]) = alterTable($system, 'defRecStructure', 'rst_TermsAsButtons', "ALTER TABLE `defRecStructure` ADD COLUMN `rst_TermsAsButtons` TinyInt DEFAULT '0' "
                    ." COMMENT 'If 1, term list fields are represented as buttons (if single value) or checkboxes (if repeat values)' "
                    ." AFTER `rst_SemanticReferenceURL`");
                    
            list($is_added,$report[]) = alterTable($system, 'defTerms', 'trm_Label', "ALTER TABLE `defTerms` ADD COLUMN `trm_Label` VARCHAR(250) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank'", true);

            list($is_added,$report[]) = alterTable($system, 'defTerms', 'trm_NameInOriginatingDB', "ALTER TABLE `defTerms` ADD COLUMN `trm_NameInOriginatingDB` VARCHAR(250) default NULL COMMENT 'Name (label) for this term in originating database'", true);             
            
        }
        if($dbVerSubSub<2){

            list($is_added,$report[]) = alterTable($system, 'defRecStructure', 'rst_PointerMode', "ALTER TABLE `defRecStructure` "
                ."ADD COLUMN `rst_PointerMode` enum('dropdown_add','dropdown','addorbrowse','addonly','browseonly') DEFAULT 'dropdown_add' COMMENT 'When adding record pointer values, default or null = show both add and browse, otherwise only allow add or only allow browse-for-existing'", true);             
        }

        if($dbVerSubSub<4){
            
            $query = <<<EXP
CREATE TABLE sysWorkflowRules  (
  swf_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key',
  swf_RecTypeID  smallint unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
  swf_Stage int unsigned NOT NULL default '0' COMMENT 'trm_ID from vocabulary "Workflow stage" 2-9453',
  swf_Order tinyint unsigned zerofill NOT NULL default '000' COMMENT 'Ordering of stage per record type',
  swf_StageRestrictedTo varchar(255) default NULL Comment 'Comma separated list of ugr_ID who are allowed to set workgroup stage to this value. Null = anyone',
  swf_SetOwnership smallint NULL default NULL COMMENT 'Workgroup to be set as the owner group, Null = No change, 0=everyone',
  swf_SetVisibility  varchar(255) default NULL COMMENT 'public=anyone, viewable=all logged in, hidden = private, hidden may be followed by comma separated list of ugr_ID that should be set to have view permission',
  swf_SendEmail  varchar(255) default NULL COMMENT 'Comma separated list of ugr_ID that will be emailed on stage change',
PRIMARY KEY  (swf_ID),
UNIQUE KEY swf_StageKey (swf_RecTypeID, swf_Stage)
) ENGINE=InnoDB COMMENT='Describes the rules to be applied when the value of the Workflow stage field is changed to this value';
EXP;
            list($is_created,$report[]) = createTable($system, 'sysWorkflowRules', $query, true);
            
       }

       if($dbVerSubSub<5){

            list($is_added,$report[]) = alterTable($system, 'recUploadedFiles', 'ulf_PreferredSource', "ALTER TABLE `recUploadedFiles` "
                ."ADD COLUMN `ulf_PreferredSource` enum('local','external','iiif','iiif_image','tiled') "
                ."NOT NULL default 'local' COMMENT 'Preferred source of file if both local file and external reference set'", true);             
           

            $query = <<<EXP
CREATE TABLE defCalcFunctions (
  cfn_ID smallint unsigned NOT NULL auto_increment COMMENT 'Primary key of defCalcFunctions table',
  cfn_Name varchar(63) NOT NULL COMMENT 'Descriptive name for function',
  cfn_Domain enum('calcfieldstring','pluginphp') NOT NULL default 'calcfieldstring' COMMENT 'Domain of application of this function specification',
  cfn_FunctionSpecification text COMMENT 'A function or chain of functions, or some PHP plugin code',
  cfn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  cfn_RecTypeIDs varchar(250) default NULL COMMENT 'CSV list of Rectype IDs that participate in formula',
  PRIMARY KEY  (cfn_ID)
) ENGINE=InnoDB COMMENT='Specifications for generating calculated fields';
EXP;
            list($is_created,$report[]) = createTable($system, 'defCalcFunctions', $query, true);
                
       }

       if($dbVerSubSub<6){
            list($is_added,$report[]) = alterTable($system, 'defTerms', 'trm_OrderInBranch', "ALTER TABLE `defTerms` ADD COLUMN `trm_OrderInBranch` smallint NULL Comment 'Defines sorting order of terms if non-alphabetic. Operates only within a single branch, including root'");
       }

       if($dbVerSubSub<7){
           
            list($is_added,$report[]) = alterTable($system, 'recDetails', 'dtl_HideFromPublic', "ALTER TABLE `recDetails` ADD COLUMN `dtl_HideFromPublic` tinyint unsigned default null Comment 'If set, the value is not shown in Record View, column lists, custom reports or anywhere the value is displayed. It may still be used in filter or analysis'");

            list($is_added,$report[]) = alterTable($system, 'defRecStructure', 'rst_NonOwnerVisibility', "ALTER TABLE `defRecStructure` ADD COLUMN `rst_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL default 'public' COMMENT 'Allows restriction of visibility of a particular field in a specified record type'", true);
            
            $query = "UPDATE `defRecStructure` SET `rst_NonOwnerVisibility`='public' WHERE rst_ID>0";
            $res = $mysqli->query($query);
            
       }

       if(!array_key_exists('sys_NakalaKey', $sysValues)){ //$dbVerSubSub<9 &&

            list($is_added,$report[]) = alterTable($system, 'sysIdentification', 'sys_NakalaKey', "ALTER TABLE `sysIdentification` ADD COLUMN `sys_NakalaKey` TEXT default NULL COMMENT 'Nakala API key. Retrieved from Nakala website'");
       
            $usr_prefs = user_getPreferences($system);
            if($is_added && array_key_exists('nakala_api_key', $usr_prefs)){
                $query = "UPDATE `sysIdentification` SET sys_NakalaKey='"
                    .$mysqli->real_escape_string($usr_prefs['nakala_api_key'])."' WHERE 1";
                            $res = $mysqli->query($query);
            }
       }

            $query = <<<EXP
CREATE TABLE defTranslations (
  trn_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key of defTranslations table',
  trn_Source varchar(64) NOT NULL COMMENT 'The column to be translated (unique names identify source)',
  trn_Code int unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
  trn_LanguageCode char(3) NOT NULL COMMENT 'The translation language code ISO639',
  trn_Translation text NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
  trn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (trn_ID),
  UNIQUE KEY trn_composite (trn_Source,trn_Code,trn_LanguageCode),
  KEY trn_LanguageCode (trn_LanguageCode)
) ENGINE=InnoDB COMMENT='Translation table into multiple languages for all translatab';
EXP;
            list($is_created,$report[]) = createTable($system, 'defTranslations', $query, true);

            
            list($is_added,$report[]) = alterTable($system, 'sysUGrps', 'usr_ExternalAuthentication', "ALTER TABLE `sysUGrps` ADD COLUMN `usr_ExternalAuthentication` varchar(1000) default NULL COMMENT 'JSON array with external authentication preferences'");
            
        }catch(Exception $exception){
            return false;
        }
            

            if($dbVerSubSub<12){
                if(!checkUserStatusColumn($system)){
                    return false;
                }
                $report[] = 'sysUGrps:ugr_Enabled modified';
            }


            //update version
            if($dbVerSubSub<12){
                $mysqli->query('UPDATE sysIdentification SET sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=12 WHERE 1');
            }

            /* date index created in upgradeDatabase.php
            if($dbVerSubSub<14){
                if(!recreateRecDetailsDateIndex( $system, false, false )){
                    return false;
                }
            }
            */


            //import field 2-1080 Workflowstages
            $report[] = importField_2_1080($system);

        
        return $report;
    }
    
    //
    // import field 2-1080 Workflowstages
    //
    function importField_2_1080($system){
            //$dbVerSubSub<4 && 
            $res = false;
            $ret = '';
            
            if(!(ConceptCode::getDetailTypeLocalID('2-1080')>0)){
                $importDef = new DbsImport( $system );
                if($importDef->doPrepare(  array(
                'defType'=>'detailtype',
                'databaseID'=>2,
                'conceptCode'=>'2-1080')))
                {
                    $res = $importDef->doImport();
                }
            }
            if($res){
                $ret = 'Field 2-1080 "Workflow stages imported';
            }
            return $ret;
    }
?>
