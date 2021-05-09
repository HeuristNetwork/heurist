<?php
require_once(dirname(__FILE__).'/../../verification/verifyValue.php');
require_once(dirname(__FILE__).'/../../verification/verifyFieldTypes.php');

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

//
//
//    
function updateDatabseTo_v3($system, $dbname=null){
    
        $mysqli = $system->get_mysqli();
        
        if($dbname){
            mysql__usedatabase($mysqli, $dbname);
        }
    
        $report = array();
        
        //create new tables
        if(!hasTable($mysqli, 'usrRecPermissions')){        
        
            $query = 'CREATE TABLE IF NOT EXISTS `usrRecPermissions` ('
                  ."`rcp_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary table key',"
                  ."`rcp_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'ID of group',"
                  ."`rcp_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which permission is linked',"
                  ."`rcp_Level` enum('view','edit') NOT NULL default 'view' COMMENT 'Level of permission',"
                  ."PRIMARY KEY  (rcp_ID)"
                .") ENGINE=InnoDB COMMENT='Permissions for groups to records'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create usrRecPermissions', $mysqli->error);
                return false;
            }
            $report[] = 'usrRecPermissions created';
        }else{
            $report[] = 'usrRecPermissions already exists';
            $query = 'DROP INDEX rcp_composite_key ON usrRecPermissions';
            $res = $mysqli->query($query);
        }

        //create new tables
        if(!hasTable($mysqli, 'sysDashboard')){        
            
$query = 'CREATE TABLE sysDashboard ('
  .'dsh_ID tinyint(3) unsigned NOT NULL auto_increment,'
  ."dsh_Order smallint COMMENT 'Used to define the order in which the dashboard entries are shown',"
  ."dsh_Label varchar(64) COMMENT 'The short text which will describe this function in the shortcuts',"
  ."dsh_Description varchar(1024) COMMENT 'A longer text giving more information about this function to show as a description below the label or as a rollover',"
  ."dsh_Enabled enum('y','n') NOT NULL default 'y' COMMENT 'Allows unused functions to be retained so they can be switched back on',"
  ."dsh_ShowIfNoRecords enum('y','n') NOT NULL default 'y' COMMENT 'Deteremines whether the function will be shown on the dashboard if there are no records in the database (eg. no point in showing searches if nothing to search)',"
  ."dsh_CommandToRun varchar(64) COMMENT 'Name of commonly used functions',"
  ."dsh_Parameters varchar(250) COMMENT 'Parameters to pass to the command eg the record type to create',"
  ."PRIMARY KEY  (dsh_ID)"
.") ENGINE=InnoDB COMMENT='Defines an editable list of shortcuts to functions to be displayed on a popup dashboard at startup unless turned off'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create sysDashboard', $mysqli->error);
                return false;
            }
            $report[] = 'sysDashboard created';
        }else{
            $report[] = 'sysDashboard already exists';
        }
        
        //create new tables
        if(!hasTable($mysqli, 'usrWorkingSubsets')){        
            
$query = 'CREATE TABLE usrWorkingSubsets ( '
  ."wss_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Unique ID for the working subsets table',"
  ."wss_RecID int(10) unsigned NOT NULL COMMENT 'ID of a Record to be included in the working subset for a specific user',"
  ."wss_OwnerUGrpID smallint(5) unsigned NOT NULL COMMENT 'Person to whose working subset this Record ID is assigned',"
  ."PRIMARY KEY  (wss_ID),"
  .'KEY wss_RecID (wss_RecID),'
  .'KEY wss_OwnerUGrpID (wss_OwnerUGrpID)'
.") ENGINE=InnoDB COMMENT='Lists a set of Records to be included in a working subset for a user. Working susbset is an initial filter on all filter actions.'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create usrWorkingSubsets', $mysqli->error);
                return false;
            }
            $report[] = 'usrWorkingSubsets created';
        }else{
            $report[] = 'usrWorkingSubsets already exists';
        }
        
        
        if(!hasTable($mysqli, 'defVocabularyGroups')){        
        
$query = "CREATE TABLE defVocabularyGroups (
  vcg_ID tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Vocabulary group ID referenced in vocabs editor',
  vcg_Name varchar(40) NOT NULL COMMENT 'Name for this group of vocabularies, shown as heading in lists',
  vcg_Domain enum('enum','relation') NOT NULL default 'enum' COMMENT 'Normal vocabularies are termed enum, relational are for relationship types but can also be used as normal vocabularies',
  vcg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of vocabulary groups within pulldown lists',
  vcg_Description varchar(250) default NULL COMMENT 'A description of the vocabulary group and its purpose',
  vcg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this vocabulary group record, used to get last updated date for table',
  PRIMARY KEY  (vcg_ID),
  UNIQUE KEY vcg_Name (vcg_Name)
) ENGINE=InnoDB COMMENT='Grouping mechanism for vocabularies in vocabularies/terms editor'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create defVocabularyGroups', $mysqli->error);
                return false;
            }

            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("User-defined")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Semantic web")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Place")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("People,  events, biography")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Bibliographic, copyright")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Spatial")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Categorisation and flags")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Internal")');
            $mysqli->query('INSERT INTO defVocabularyGroups (vcg_Name,vcg_Domain) VALUES ("RELATIONSHIPS","relation")');
            
            $report[] = 'defVocabularyGroups created';
        }else{
            $report[] = 'defVocabularyGroups already exists';
        }
    
        //--------------------------- FIELDS -----------------------------------

        if(!hasColumn($mysqli, 'sysIdentification', 'sys_TreatAsPlaceRefForMapping')){ //column not defined
            $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_TreatAsPlaceRefForMapping` VARCHAR(1000) DEFAULT '' COMMENT 'Comma delimited list of additional rectypes (local codes) to be considered as Places'";
            $res = $mysqli->query($query);
            $report[] = 'sysIdentification: sys_TreatAsPlaceRefForMapping added';
        }else{
            $report[] = 'sysIdentification: sys_TreatAsPlaceRefForMapping already exists';
        }
    
        if(!hasColumn($mysqli, 'sysIdentification', 'sys_ExternalReferenceLookups')){ //column not defined
            $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_ExternalReferenceLookups` TEXT default NULL COMMENT 'Record type-function-field specifications for lookup to external reference sources such as GeoNames'";
            $res = $mysqli->query($query);
            $report[] = 'sysIdentification: sys_ExternalReferenceLookups added';
        }else{
            $report[] = 'sysIdentification: sys_ExternalReferenceLookups already exists';
        }
    
    
        //verify that required column exists in sysUGrps
        $is_exists = hasColumn($mysqli, 'sysUGrps', 'ugr_NavigationTree');
        
        //alter table
        $query = "ALTER TABLE `sysUGrps` ".($is_exists?'MODIFY':'ADD')
            ." `ugr_NavigationTree` mediumtext COMMENT 'JSON array that describes treeview for filters'";
            
        $res = $mysqli->query($query);
        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot modify sysUGrps to add ugr_NavigationTree', $mysqli->error);
            return false;
        }
        $report[] = 'sysUGrps: ugr_NavigationTree '.($is_exists?'modified':'added');

        //----------------------------        
        $is_exists = hasColumn($mysqli, 'sysUGrps', 'ugr_Preferences');
        
        $query = "ALTER TABLE `sysUGrps` ".($is_exists?'MODIFY':'ADD')
                ." `ugr_Preferences` mediumtext COMMENT 'JSON array with user preferences'";
        $res = $mysqli->query($query);
        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot modify sysUGrps to add ugr_Preferences', $mysqli->error);
            return false;
        }
        $report[] = 'sysUGrps: ugr_Preferences '.($is_exists?'modified':'added');
        
        
        //----------------------------        
        if(hasColumn($mysqli, 'usrBookmarks', 'bkm_Notes')){
            $report[] = 'usrBookmarks: bkm_Notes already exists';
        }else{
            //alter table
            $query = "ALTER TABLE `usrBookmarks` ADD `bkm_Notes` mediumtext COMMENT 'Personal notes'";
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify usrBookmarks to add bkm_Notes', $mysqli->error);
                return false;
            }
            $report[] = 'usrBookmarks: bkm_Notes added';
        }    
        
        //----------------------------        
        $is_exists = hasColumn($mysqli, 'defRecStructure', 'rst_DefaultValue');
        
        $query = "ALTER TABLE `defRecStructure` ".($is_exists?'MODIFY':'ADD')
                ." `rst_DefaultValue` text COMMENT 'The default value for this detail type for this record type'";
        $res = $mysqli->query($query);
        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot modify defRecStructure to add rst_DefaultValue', $mysqli->error);
            return false;
        }
        $report[] = 'defRecStructure: rst_DefaultValue '.($is_exists?'modified':'added');
        
        //----------------------------        
        
        if(hasColumn($mysqli, 'defTerms', 'trm_SemanticReferenceURL')){
            $report[] = 'defTerms: trm_SemanticReferenceURL already exists';
        }else{
            //alter table
            $query = "ALTER TABLE `defTerms` ADD `trm_SemanticReferenceURL` VARCHAR( 250 ) NULL "
            ." COMMENT 'The URI to a semantic definition or web page describing the term' "
            .' AFTER `trm_Code`';
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify defTerms to add trm_SemanticReferenceURL', $mysqli->error);
                return false;
            }
            $report[] = 'defTerms: trm_SemanticReferenceURL added';
        }    
        
        //----------------------------        
        
        if(hasColumn($mysqli, 'defTerms', 'trm_VocabularyGroupID')){
            $report[] = 'defTerms: trm_VocabularyGroupID already exists';
        }else{
        
            $query = "ALTER TABLE `defTerms` ADD COLUMN trm_VocabularyGroupID smallint(5) unsigned NULL default '1' COMMENT 'Vocabulary group to which this term belongs, if a top level term (vocabulary)'";
                 
            $res = $mysqli->query($query);
            if(!$res){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot modify defTerms to add trm_VocabularyGroupID', $mysqli->error);
                    return false;
            }
            $report[] = 'defTerms: trm_VocabularyGroupID added';
            
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=9 where (NOT (trm_ParentTermID>0)) and trm_Domain="relation"');

            //Semantic web
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=2 where trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (5668,5520,5805,5792,6091,5445,5842,6177,6214)');
            //Place
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=3 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (509,506)) OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB=5039');
            //People,  events, biography
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=4 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (5389,500,501,507,496,497,5432,505,511,513))'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB=5065)'
            .' OR (trm_OriginatingDBID=9 AND trm_IDInOriginatingDB=3297)'
            .' OR (trm_OriginatingDBID=1161 AND trm_IDInOriginatingDB=5419)');
            //Bibliographic, copyright
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=5 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB=503)'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB IN (5024,5021,5012,5099))'
            .' OR (trm_OriginatingDBID=1144 AND trm_IDInOriginatingDB=5986)');
            //Spatial
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=6 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (512,5362,5440,510,546,551))'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB IN (5087,5080,5091,5073,5080,5028,5083,5077))'
            .' OR (trm_OriginatingDBID=1125 AND trm_IDInOriginatingDB IN (3659,3339))');
            //Categorisation and flags
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=7 where (trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (508,498,530))'
            .' OR (trm_OriginatingDBID=3 AND trm_IDInOriginatingDB IN (5030,3440))'
            .' OR (trm_OriginatingDBID=99 AND trm_IDInOriginatingDB=5445)'
            .' OR (trm_OriginatingDBID=1125 AND trm_IDInOriginatingDB=3339)'
            .' OR (trm_OriginatingDBID=1144 AND trm_IDInOriginatingDB IN (6002,5993))');
            //Internal
            $mysqli->query('UPDATE defTerms set trm_VocabularyGroupID=8 where trm_OriginatingDBID=2 AND '
            .'trm_IDInOriginatingDB IN (533,3272,520,6252,6250)');
            
            
            $report[] = 'defTerms: trm_VocabularyGroupID filled';           
        }
        
        //-----------------------------
        $needCreateTermsLinks = !hasTable($mysqli, 'defTermsLinks');
        if($needCreateTermsLinks){        
            
$query = "CREATE TABLE defTermsLinks (
  trl_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key for vocablary-terms hierarchy',
  trl_ParentID smallint(5) unsigned NOT NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  trl_TermID smallint(5) unsigned NOT NULL COMMENT 'Term identificator',
  PRIMARY KEY  (trl_ID),
  UNIQUE KEY trl_CompositeKey (trl_ParentID,trl_TermID)
) ENGINE=InnoDB COMMENT='Identifies hierarchy of vocabularies and terms'";
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot create defTermsLinks', $mysqli->error);
                return false;
            }
            $report[] = 'defTermsLinks created';
            
        }else{
            $report[] = 'defTermsLinks already exists';            
        }


        $res = $mysqli->query('DROP TRIGGER IF EXISTS defTerms_last_insert');
        $res = $mysqli->query('CREATE DEFINER=CURRENT_USER TRIGGER `defTerms_last_insert` AFTER INSERT ON `defTerms` FOR EACH ROW
        begin
            if NEW.trm_ParentTermID > 0 then
                insert into defTermsLinks (trl_ParentID,trl_TermID)
                        values (NEW.trm_ParentTermID, NEW.trm_ID);
            end if;
        end');  
        
        $res = $mysqli->query('DROP TRIGGER IF EXISTS defTerms_last_update');

        $res = $mysqli->query('CREATE DEFINER=CURRENT_USER TRIGGER `defTerms_last_update` AFTER UPDATE ON `defTerms`
        FOR EACH ROW
        begin
            if NEW.trm_ParentTermID != OLD.trm_ParentTermID then
                update defTermsLinks SET trl_ParentID=NEW.trm_ParentTermID
                    where trl_ParentID=OLD.trm_ParentTermID and trl_TermID=NEW.trm_ID;
            end if;
        end');
        
        $res = $mysqli->query('DROP TRIGGER IF EXISTS defTerms_last_delete');
        $res = $mysqli->query('CREATE DEFINER=CURRENT_USER  TRIGGER `defTerms_last_delete` AFTER DELETE ON `defTerms` FOR EACH ROW
        begin
            delete ignore from defTermsLinks where trl_TermID=OLD.trm_ID || trl_ParentID=OLD.trm_ID;
        end');            
        
        $report[] = 'defTerms triggers updated';

        if($needCreateTermsLinks){
            // fill values
            $res = fillTermsLinks( $mysqli );
        
            $report = array_merge($report, $res);
        }
        
        if(hasTable($mysqli, 'sysTableLastUpdated'))
        {        
            $mysqli->query('DROP TRIGGER IF EXISTS sysUGrps_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS sysUGrps_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypes_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypes_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypes_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypes_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypes_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypes_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecStructure_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecStructure_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecStructure_last_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRelationshipConstraints_last_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRelationshipConstraints_last_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRelationshipConstraints_last_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypeGroups_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypeGroups_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defRecTypeGroups_delete');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypeGroups_insert');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypeGroups_update');
            $mysqli->query('DROP TRIGGER IF EXISTS defDetailTypeGroups_delete');
            $mysqli->query('DROP TABLE IF EXISTS sysTableLastUpdated');            
            $report[] = 'sysTableLastUpdated and related triggers removed';            
        }
        
        
        //update version
        $mysqli->query('UPDATE sysIdentification SET sys_dbVersion=1, sys_dbSubVersion=3, sys_dbSubSubVersion=0 WHERE 1');
        
        $system->get_system(null, true); //reset system values - to update version
        
        //validate default values for record type structures
        $list = getInvalidFieldTypes($mysqli, null);
        
        if($list && @$list['rt_defvalues'] && count($list['rt_defvalues'])>0){
            $report[] = count($list['rt_defvalues']).' wrong default values have been cleared';                        
/*            
            $list = $list['rt_defvalues'];
            foreach ($list as $row) {
?>                
                <div class="msgline"><b><a href="#" onclick='{ onEditRtStructure(<?= $row['rst_RecTypeID'] ?>); return false}'><?= $row['rst_DisplayName'] ?></a></b> field (code <?= $row['dty_ID'] ?>) in record type <?= $row['rty_Name'] ?>  has invalid default value (<?= ($row['dty_Type']=='resource'?'record ID ':'term ID ').$row['rst_DefaultValue'] ?>)
                    <span style="font-style:italic"><?=$row['reason'] ?></span>
                </div>
<?php                
            }//for
*/            
        }
        
        
        //adds trash groups
        if(!(mysql__select_value($mysqli, 'select rtg_ID FROM defRecTypeGroups WHERE rtg_Name="Trash"')>0)){
$query = 'INSERT INTO defRecTypeGroups (rtg_Name,rtg_Order,rtg_Description) '
.'VALUES ("Trash",255,"Drag record types here to hide them, use dustbin icon on a record type to delete permanently")';
            $mysqli->query($query);
            $report[] = '"Trash" group has been added to rectype groups';                        
        }

        if(!(mysql__select_value($mysqli, 'select vcg_ID FROM defVocabularyGroups WHERE vcg_Name="Trash"')>0)){
$query = 'INSERT INTO defVocabularyGroups (vcg_Name,vcg_Order,vcg_Description) '
.'VALUES ("Trash",255,"Drag vocabularies here to hide them, use dustbin icon on a vocabulary to delete permanently")';
            $mysqli->query($query);
            $report[] = '"Trash" group has been added to vocabulary groups';                        
        }

        if(!(mysql__select_value($mysqli, 'select dtg_ID FROM defDetailTypeGroups WHERE dtg_Name="Trash"')>0)){
$query = 'INSERT INTO defDetailTypeGroups (dtg_Name,dtg_Order,dtg_Description) '
.'VALUES ("Trash",255,"Drag base fields here to hide them, use dustbin icon on a field to delete permanently")';        
            $mysqli->query($query);
            $report[] = '"Trash" group has been added to field groups';                        
        }
        
   
        return $report;
}

//
//
//
function fillTermsLinks( $mysqli ){
  
    $report = array();
    
            $query = 'SELECT sys_dbRegisteredID from sysIdentification';
            $db_regid = mysql__select_value($mysqli, $query);
    
    
            $mysqli->query('INSERT INTO defTermsLinks (trl_ParentID, trl_TermID) '
            .'SELECT trm_ParentTermID, trm_ID FROM defTerms WHERE trm_ParentTermID>0');
    
    $report[] = 'Terms links are filled';
    

            //clear individual term selection for relationtype (this is the only field (#6))     
            $query = 'UPDATE defDetailTypes SET dty_JsonTermIDTree="" WHERE dty_Type="relationtype"'; //for dty_ID=6
            $mysqli->query($query);
            
            $vocab_group = 7;//
            $is_first = true;
            
            //converts custom-selected term tree to vocab with references
            $query = 'SELECT dty_Name,dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, dty_ID, dty_Type, dty_OriginatingDBID, dty_IDInOriginatingDB FROM '
                     .'defDetailTypes WHERE  dty_Type="enum" or dty_Type="relmarker"'; //except relationtype which is one dty_ID=6
        
            $res = $mysqli->query($query);
            while (($row = $res->fetch_row())) {
                //if the only numeric - assume this is vocabulary
                if(@$row[1]>0 && is_numeric(@$row[1])){
                    continue;
                }
            
                if($is_first){
                    $report[] = 'Create vocabularies with references for "custom selections terms"';
                    $is_first = false;
                }    
                
                $domain = $row[4]=='enum'?'enum':'relation';
                $name = $row[0].' - selection';
                
                $cnt = mysql__select_value($mysqli, "SELECT count(trm_ID) FROM defTerms WHERE trm_Label LIKE '".$name."%'");
                if($cnt>0){
                   $name = $name . '  ' . $cnt; 
                }

                $report[] = $row[3].'  '.$name;   
                
                //{"11":{"518":{},"519":{}},"94":{},"95":{},"3260":{"3115":{"3100":{}}}}

                $terms = json_decode(@$row[1], true);
                if($terms){
                    
                    $values = array(
                                'trm_Label'=>$name,
                                'trm_Domain'=>$domain,
                                'trm_VocabularyGroupID'=>$vocab_group);
                    
                    $id_orig = 0;
                    if($row[5]==3){  //dty_OriginatingDBID
                        if($row[6]==1079) $id_orig = 6255;  //dty_IDInOriginatingDB
                        else if($row[6]==1080) $id_orig = 6256;
                        else if($row[6]==1087) $id_orig = 6257;
                        else if($row[6]==1088) $id_orig = 6258;
                        
                        if($id_orig>0){
                            $values['trm_OriginatingDBID'] = 2;
                            $values['trm_IDInOriginatingDB'] = $id_orig;  
                        } 
                    }
                    
                    //add new vocabulary
                    $vocab_id = mysql__insertupdate($mysqli, 'defTerms', 'trm', $values);
                    
                    if($db_regid>0 && $id_orig==0){
                        $values = array();
                        $values['trm_ID'] = $vocab_id;
                        $values['trm_OriginatingDBID'] = $db_regid;
                        $values['trm_IDInOriginatingDB'] = $vocab_id;  
                        mysql__insertupdate($mysqli, 'defTerms', 'trm', $values);
                    }
                                
                        
                    //parent->term_id
                    $terms_links = _prepare_terms($vocab_id, $terms);
                    
                    foreach ($terms_links as $line){
                        $mysqli->query('INSERT INTO defTermsLinks(trl_ParentID, trl_TermID) VALUES('.$line[0].','.$line[1].')');    
                    }

                    //dty_TermIDTreeNonSelectableIDs=dty_JsonTermIDTree, 
                    $query = 'UPDATE defDetailTypes SET dty_JsonTermIDTree='
                                .$vocab_id.' WHERE dty_ID='.$row[3];
                    $mysqli->query($query);

                }else{
                    $report[] = 'Set vocabulary manually. Term tree not defined or corrupted: '.@$row[1];   
                }
            }
   
    return $report;
}

// {"11":{"518":{},"519":{}},"94":{},"95":{},"3260":{"3115":{"3100":{}}}}
function _prepare_terms($parent_id, $terms){
    $res = array();       
    foreach($terms as $trm_ID=>$children){
        array_push($res, array($parent_id, $trm_ID));
        if($children && count($children)>0){
            $res2 = _prepare_terms($trm_ID, $children);
            $res = array_merge($res, $res2);
        }
    }
    return $res;
}
?>
