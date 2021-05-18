/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* DBUpgrade_1.x.x_to_1.x.x.sql: SQL file to update Heurist database format between indicated versions 
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.7
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

-- ------------------------------------------------------------------------------

-- ****** IMPORTANT *******

-- This is a holding pen for ideas about the next structure upgrade. 
-- These changes might or might not be made, or might be made in modified form

-- ------------------------------------------------------------------------------

-- additional upgrades to future versions can be added as new files but should not be processed 
-- until the software is updated to expect them


-- Source version: 1.2.0 
-- Target version: 1.3.0
-- Safety rating: SAFE
-- Description: Add tables: usrRecPermissions, sysDashboard, usrWorkingSubsets, defVocabularyGroups, defTermsLinks. Remove: sysTableLastUpdated

CREATE TABLE IF NOT EXISTS `usrRecPermissions` (
      `rcp_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary table key',
      `rcp_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'ID of group',
      `rcp_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which permission is linked',
      `rcp_Level` enum('view','edit') NOT NULL default 'view' COMMENT 'Level of permission',
      PRIMARY KEY  (rcp_ID)
) ENGINE=InnoDB COMMENT='Permissions for groups to records';

-- 

CREATE TABLE IF NOT EXISTS sysDashboard (
   dsh_ID tinyint(3) unsigned NOT NULL auto_increment,
   dsh_Order smallint COMMENT 'Used to define the order in which the dashboard entries are shown',
   dsh_Label varchar(64) COMMENT 'The short text which will describe this function in the shortcuts',
   dsh_Description varchar(1024) COMMENT 'A longer text giving more information about this function to show as a description below the label or as a rollover',
   dsh_Enabled enum('y','n') NOT NULL default 'y' COMMENT 'Allows unused functions to be retained so they can be switched back on',
   dsh_ShowIfNoRecords enum('y','n') NOT NULL default 'y' COMMENT 'Deteremines whether the function will be shown on the dashboard if there are no records in the database (eg. no point in showing searches if nothing to search)',
   dsh_CommandToRun varchar(64) COMMENT 'Name of commonly used functions',
   dsh_Parameters varchar(250) COMMENT 'Parameters to pass to the command eg the record type to create',
   PRIMARY KEY  (dsh_ID)
) ENGINE=InnoDB COMMENT='Defines an editable list of shortcuts to functions to be displayed on a popup dashboard at startup unless turned off';

--

CREATE TABLE IF NOT EXISTS usrWorkingSubsets ( 
   wss_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Unique ID for the working subsets table',
   wss_RecID int(10) unsigned NOT NULL COMMENT 'ID of a Record to be included in the working subset for a specific user',
   wss_OwnerUGrpID smallint(5) unsigned NOT NULL COMMENT 'Person to whose working subset this Record ID is assigned',
   PRIMARY KEY  (wss_ID),
  .'KEY wss_RecID (wss_RecID),
  .'KEY wss_OwnerUGrpID (wss_OwnerUGrpID)
 ) ENGINE=InnoDB COMMENT='Lists a set of Records to be included in a working subset for a user. Working susbset is an initial filter on all filter actions.';
 
--

CREATE TABLE IF NOT EXISTS defVocabularyGroups (
  vcg_ID tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Vocabulary group ID referenced in vocabs editor',
  vcg_Name varchar(40) NOT NULL COMMENT 'Name for this group of vocabularies, shown as heading in lists',
  vcg_Domain enum('enum','relation') NOT NULL default 'enum' COMMENT 'Normal vocabularies are termed enum, relational are for relationship types but can also be used as normal vocabularies',
  vcg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of vocabulary groups within pulldown lists',
  vcg_Description varchar(250) default NULL COMMENT 'A description of the vocabulary group and its purpose',
  vcg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this vocabulary group record, used to get last updated date for table',
  PRIMARY KEY  (vcg_ID),
  UNIQUE KEY vcg_Name (vcg_Name)
) ENGINE=InnoDB COMMENT='Grouping mechanism for vocabularies in vocabularies/terms editor';

INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("User-defined");
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Semantic web");
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Place");
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("People,  events, biography");
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Bibliographic, copyright")');
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Spatial");
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Categorisation and flags");
INSERT INTO defVocabularyGroups (vcg_Name) VALUES ("Internal");
INSERT INTO defVocabularyGroups (vcg_Name,vcg_Domain) VALUES ("RELATIONSHIPS","relation");

--

ALTER TABLE `defTerms` ADD COLUMN  IF NOT EXISTS trm_VocabularyGroupID smallint(5) unsigned NULL default '1' COMMENT 'Vocabulary group to which this term belongs, if a top level term (vocabulary)';

--

CREATE TABLE defTermsLinks (
  trl_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key for vocablary-terms hierarchy',
  trl_ParentID smallint(5) unsigned NOT NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  trl_TermID smallint(5) unsigned NOT NULL COMMENT 'Term identificator',
  PRIMARY KEY  (trl_ID),
  UNIQUE KEY trl_CompositeKey (trl_ParentID,trl_TermID)
) ENGINE=InnoDB COMMENT='Identifies hierarchy of vocabularies and terms';


INSERT INTO defTermsLinks (trl_ParentID, trl_TermID) SELECT trm_ParentTermID, trm_ID FROM defTerms WHERE trm_ParentTermID>0;

--

DROP TRIGGER IF EXISTS defTerms_last_insert;
DROP TRIGGER IF EXISTS defTerms_last_update;
DROP TRIGGER IF EXISTS defTerms_last_delete;

DELIMITER $$

CREATE DEFINER=CURRENT_USER TRIGGER `defTerms_last_insert` AFTER INSERT ON `defTerms` FOR EACH ROW
            begin
                if NEW.trm_ParentTermID > 0 then
                    insert into defTermsLinks (trl_ParentID,trl_TermID)
                            values (NEW.trm_ParentTermID, NEW.trm_ID);
                end if;
            end$$  
            

CREATE DEFINER=CURRENT_USER TRIGGER `defTerms_last_update` AFTER UPDATE ON `defTerms`
            FOR EACH ROW
            begin
                if NEW.trm_ParentTermID != OLD.trm_ParentTermID then
                    update defTermsLinks SET trl_ParentID=NEW.trm_ParentTermID
                        where trl_ParentID=OLD.trm_ParentTermID and trl_TermID=NEW.trm_ID;
                end if;
            end$$
            
CREATE DEFINER=CURRENT_USER  TRIGGER `defTerms_last_delete` AFTER DELETE ON `defTerms` FOR EACH ROW
            begin
                delete ignore from defTermsLinks where trl_TermID=OLD.trm_ID || trl_ParentID=OLD.trm_ID;
            end$$            

DELIMITER ;

--

DROP TRIGGER IF EXISTS sysUGrps_last_insert;
DROP TRIGGER IF EXISTS sysUGrps_last_update;
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_insert;
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_update;
DROP TRIGGER IF EXISTS defDetailTypes_last_insert;
DROP TRIGGER IF EXISTS defDetailTypes_last_update;
DROP TRIGGER IF EXISTS defDetailTypes_delete;
DROP TRIGGER IF EXISTS defRecTypes_last_insert;
DROP TRIGGER IF EXISTS defRecTypes_last_update;
DROP TRIGGER IF EXISTS defRecTypes_delete;
DROP TRIGGER IF EXISTS defRecStructure_last_insert;
DROP TRIGGER IF EXISTS defRecStructure_last_update;
DROP TRIGGER IF EXISTS defRecStructure_last_delete;
DROP TRIGGER IF EXISTS defRelationshipConstraints_last_insert;
DROP TRIGGER IF EXISTS defRelationshipConstraints_last_update;
DROP TRIGGER IF EXISTS defRelationshipConstraints_last_delete;
DROP TRIGGER IF EXISTS defRecTypeGroups_insert;
DROP TRIGGER IF EXISTS defRecTypeGroups_update;
DROP TRIGGER IF EXISTS defRecTypeGroups_delete;
DROP TRIGGER IF EXISTS defDetailTypeGroups_insert;
DROP TRIGGER IF EXISTS defDetailTypeGroups_update;
DROP TRIGGER IF EXISTS defDetailTypeGroups_delete;
DROP TABLE IF EXISTS sysTableLastUpdated;  


