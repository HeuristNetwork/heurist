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
* UpgradeDB_3.0_3.1.sql: SQL file to update Heurist database between indicated versions
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


-- VERSION  3.0  (6 April 2011) ---->  Version 3.1  (July 2011?)

-- Source version: 1.0.0
-- Target version: 1.1.0
-- Safety rating: SAFE

-- Description: Early set of upgrades in mid 2011, has been applied to all known active databases
-- (possibly excluding a small number of inactive legacy databases corrupted by Steve's unmanaged change of internal IDs in approx. June 2011)
-- This file is maintained for historic information only, formerly named UpgradeDB_to_1.0.0.sql

    -- Add new value 'public'
    Alter table sysIdentification
        CHANGE  sys_NewRecAccess  sys_NewRecAccess
        ENUM(  'viewable',  'hidden',  'public', 'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Default visibility for new records - allow override per user';
    ALTER TABLE Records
        CHANGE rec_NonOwnerVisibility rec_NonOwnerVisibility
        ENUM(  'viewable',  'hidden',  'public', 'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Defines if record visible outside owning Workgroup(s)';

    ALTER TABLE  sysIdentification
        ADD COLUMN sys_IncomingEmailAddresses VARCHAR( 4000 ) NULL
        COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins'
        AFTER  sys_eMailImapPassword,
        ADD COLUMN sys_hmlOutputDirectory VARCHAR(255) NULL
        COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory'
        AFTER sys_UploadDirectory,
        ADD COLUMN sys_htmlOutputDirectory VARCHAR(255) NULL
        COMMENT 'Directory in which to write html representation of published records, default to html within upload directory'
        AFTER sys_hmlOutputDirectory,
        ADD COLUMN sys_TargetEmailAddresses VARCHAR(255) NULL
        COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses'
        AFTER sys_IncomingEmailAddresses;

   ALTER TABLE sysUGrps
        ADD COLUMN ugr_TargetEmailAddresses VARCHAR(255) NULL
        COMMENT 'Comma-sep list for selecting target for sending records as data, see also sys_TargetEmailAddresses'
        AFTER ugr_IncomingEmailAddresses;


  CREATE TABLE IF NOT EXISTS sysDocumentation (
        doc_id tinyint unsigned auto_increment,
        doc_text text Comment "Relevant documentation as text",
   PRIMARY KEY  (doc_id) )
        Comment 'Descriptive information about this database and its function';

  -- Make this single value so it can be linked to the rectype groups table
   ALTER TABLE defRecTypes
        CHANGE rty_RecTypeGroupIDs rty_RecTypeGroupID TINYINT(3) Unsigned NOT NULL DEFAULT '1'
        COMMENT 'Record type group to which this record type belongs',
      ADD COLUMN rty_RecTypeModelIDs VARCHAR(63) NULL
      COMMENT  'The model group(s) to which this rectype belongs, comma sep. list'
      AFTER `rty_RecTypeGroupID` ;

  Alter table recUploadedFiles
        ADD COLUMN ulf_ExternalFileReference VARCHAR(1000) NULL
        COMMENT 'URI of an external file, which may or may not be cached locally',
        add ulf_PreferredSource ENUM('local','external') NOT NULL DEFAULT 'local'
        COMMENT 'Preferred source of file if both local file and external reference set';

  ALTER TABLE defRelationshipConstraints
      DROP INDEX rcs_CompositeKey,
      ADD UNIQUE rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermID);

  ALTER TABLE defRelationshipConstraints
        CHANGE rcs_TermLimit rcs_TermLimit TINYINT(2) UNSIGNED NULL DEFAULT NULL
        COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used';

   -- not used anywhere in the code
   ALTER TABLE defRelationshipConstraints
        CHANGE rcs_RelationshipsLimit rcs_RelationshipsLimit TINYINT(3) UNSIGNED NULL DEFAULT NULL
        COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet';


-- Add PENDING status to existing non owner visibilites -
-- 12/9/11: these have been applied to H3CoreDefinitions and to sandpit5

  ALTER TABLE Records
        CHANGE rec_NonOwnerVisibility rec_NonOwnerVisibility
        ENUM('viewable','hidden','public','pending') NOT NULL DEFAULT 'viewable'
        COMMENT 'Defines if record visible outside owning Workgroup(s) or to anyone';

  ALTER TABLE  `sysIdentification`
        CHANGE  `sys_NewRecAccess`  `sys_NewRecAccess`
        ENUM(  'viewable',  'hidden',  'public',  'pending' ) NOT NULL
        DEFAULT  'viewable'
        COMMENT  'Default visibility for new records - allow override per user';
  ALTER TABLE  `sysIdentification`
        ADD COLUMN `sys_SetPublicToPendingOnEdit` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
        COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''' AFTER  `sys_NewRecAccess` ;

  ALTER TABLE  `defDetailTypes`
        CHANGE  `dty_NonOwnerVisibility`  `dty_NonOwnerVisibility`
        ENUM(  'hidden',  'viewable',  'public',  'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Allows restriction of visibility of a particular field in ALL record types (overrides rst_VisibleOutsideGroup)';

  ALTER TABLE  `defRecStructure`
        CHANGE  `rst_NonOwnerVisibility`  `rst_NonOwnerVisibility`
        ENUM(  'hidden',  'viewable',  'public',  'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Allows restriction of visibility of a particular field in a specified record type';

  ALTER TABLE  `defRecTypes`

        CHANGE  `rty_NonOwnerVisibility`  `rty_NonOwnerVisibility`
        ENUM(  'hidden',  'viewable',  'public',  'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Allows blanket restriction of visibility of a particular record type';

-- Add modification timestamp to all definitioonal and some data tables (others already have
-- them) to allow calculation of last date of modification through max(xxx_Modified)

  ALTER TABLE  `defCalcFunctions` 
    ADD COLUMN `cfn_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defDetailTypeGroups` 
    ADD COLUMN `dtg_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defDetailTypes` 
    ADD COLUMN `dty_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defFileExtToMimetype` 
    ADD COLUMN `fxm_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defLanguages` 
    ADD COLUMN `lng_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defOntologies` 
    ADD COLUMN `ont_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRecStructure` 
    ADD COLUMN `rst_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRecTypeGroups` 
    ADD COLUMN `rtg_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRecTypes` ADD  `rty_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRelationshipConstraints` 
    ADD COLUMN `rcs_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defTerms` 
    ADD COLUMN `trm_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defTranslations` 
    ADD COLUMN `trn_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defURLPrefixes` 
    ADD COLUMN `urp_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `recDetails` 
    ADD COLUMN `dtl_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record detail, used to get last updated date for table';

  Alter TABLE  `sysUGrps` 
    ADD COLUMN ugr_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `usrTags` 
    ADD COLUMN `tag_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `usrReminders` 
    ADD COLUMN `rem_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

-- 25/9/11

  ALTER TABLE  `defOntologies` 
    ADD COLUMN `ont_locallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defRecStructure` 
    ADD COLUMN `rst_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defRecTypes` 
    ADD COLUMN `rty_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defRelationshipConstraints` 
    ADD COLUMN `rcs_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defTerms` 
    ADD COLUMN `trm_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defDetailTypes` 
    ADD COLUMN `dty_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

Alter table defDetailTypeGroups
  change dtg_Order dtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists';
Alter table defRecTypeGroups
  change rtg_Order rtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists';
Alter TABLE recUploadedFiles
  change ulf_UploaderUGrpID ulf_UploaderUGrpID smallint(5) unsigned default NULL COMMENT 'The user who uploaded the file',
  ADD COLUMN ulf_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
     COMMENT 'The date of last modification of the file description record, automatic update';

update sysIdentification set sys_dbVersion=1,  sys_dbSubVersion=0, sys_dbSubSubVersion=0;




