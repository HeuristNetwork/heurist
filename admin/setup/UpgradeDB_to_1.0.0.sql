/*
* Copyright (C) 2005-2013 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
 -- UpgradeDB_3.0_3.1.sql: SQL file to update Heurist database between indicated versions
 -- @author Ian Johnson 4/4/2011
 -- @copyright 2005-2011 University of Sydney Digital Innovation Unit.
 -- @link: http://HeuristScholar.org
 -- @license http://www.gnu.org/licenses/gpl-3.0.txt
 -- @package Heurist academic knowledge management system
 -- @todo

 \W -- warnings to standard out

-- IMPORTANT NOTE:
-- ALL changes to database structure should be carried out using this file
-- This file should first check that the database is off the appropriate version
--      and warn if not
-- Any actions should fail if they have already been carried out, so that the
--      script can be run in its entirety even if some updates have already been made
--      Use eg. INSERT IGNORE, if values are being incremented check if they have been etc.


-- VERSION  3.0  (6 April 2011) ---->  Version 3.1  (July 2011?)

-- enter update statements for the required version upgrade in updateDBVersion below
-- don't forget to modify
--    the test for the current version
--    the update of dbVersion, dbDubVersion and dbSubSubVersion

DROP function IF EXISTS `doTestIf`;
DROP Procedure IF EXISTS `UpdateDBVersion`;

DELIMITER //

CREATE function doTestIf() RETURNS varchar(3)
     READS SQL DATA
     DETERMINISTIC
    BEGIN
        declare dbVer varchar(3);
        declare dbSubVer varchar(3);
        select sys_dbVersion into dbVer from sysIdentification where 1;
        select sys_dbSubVersion into dbSubVer from sysIdentification where 1;
        IF(dbVer="3") and (dbSubVer="0") THEN
          CALL UpdateDBVersion;
          RETURN "OK";
        ELSE
            RETURN "Bad"";
        END IF;
    END//

Create Procedure UpdateDBVersion
    Begin

    -- Only run this for the hdev database
    -- UPDATE sysIdentification SET sys_IncomingEmailAddresses='bugs@heuristscholar.org'
    --     WHERE sysIdentification.sys_ID =1 LIMIT 1;

    -- *** Required update statements between the two specified versions go here ***

    -- *** NOTE: sandpit5 and h3dev has been updated with these changes

    update  sysIdentification set sys_dbVersion=1;
    update  sysIdentification set sys_dbSubVersion=0;
    update  sysIdentification set sys_dbSubSubVersion=0;

    -- Add new value 'public'
    Alter table sysIdentification
        CHANGE  sys_NewRecAccess  sys_NewRecAccess
        ENUM(  'viewable',  'hidden',  'public', 'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Default visibility for new records - allow override per user';
    ALTER TABLE Records
        CHANGE rec_NonOwnerVisibility rec_NonOwnerVisibility
        ENUM(  'viewable',  'hidden',  'public', 'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Defines if record visible outside owning user group(s)';

    ALTER TABLE  sysIdentification
        ADD  sys_IncomingEmailAddresses VARCHAR( 4000 ) NULL
        COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins'
        AFTER  sys_eMailImapPassword,
        ADD sys_hmlOutputDirectory VARCHAR(255) NULL
        COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory'
        AFTER sys_UploadDirectory,
        ADD sys_htmlOutputDirectory VARCHAR(255) NULL
        COMMENT 'Directory in which to write html representation of published records, default to html within upload directory'
        AFTER sys_hmlOutputDirectory,
        ADD sys_TargetEmailAddresses VARCHAR(255) NULL
        COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses'
        AFTER sys_IncomingEmailAddresses;

   ALTER TABLE sysUGrps
        ADD ugr_TargetEmailAddresses VARCHAR(255) NULL
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
      ADD rty_RecTypeModelIDs VARCHAR(63) NULL
      COMMENT  'The model group(s) to which this rectype belongs, comma sep. list'
      AFTER `rty_RecTypeGroupID` ;

  Alter table recUploadedFiles
        ADD ulf_ExternalFileReference VARCHAR(1000) NULL
        COMMENT 'URI of an external file, which may or may not be cached locally',
        add ulf_PreferredSource ENUM('local','external') NOT NULL DEFAULT 'local'
        COMMENT 'Preferred source of file if both local file and external reference set';

  ALTER TABLE defRelationshipConstraints
      DROP INDEX rcs_CompositeKey,
      ADD UNIQUE rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermID);

  ALTER TABLE defRelationshipConstraints
        CHANGE rcs_TermLimit rcs_TermLimit TINYINT(2) UNSIGNED NULL DEFAULT NULL
        COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used';

   -- not used anywhere in teh code
   ALTER TABLE defRelationshipConstraints
        CHANGE rcs_RelationshipsLimit rcs_RelationshipsLimit TINYINT(3) UNSIGNED NULL DEFAULT NULL
        COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet';


-- Add PENDING status to existing non owner visibilites -
-- 12/9/11: these have been applied to H3CoreDefinitions and to sandpit5

  ALTER TABLE Records
        CHANGE rec_NonOwnerVisibility rec_NonOwnerVisibility
        ENUM('viewable','hidden','public','pending') NOT NULL DEFAULT 'viewable'
        COMMENT 'Defines if record visible outside owning user group(s) or to anyone';

  ALTER TABLE  `sysIdentification`
        CHANGE  `sys_NewRecAccess`  `sys_NewRecAccess`
        ENUM(  'viewable',  'hidden',  'public',  'pending' ) NOT NULL
        DEFAULT  'viewable'
        COMMENT  'Default visibility for new records - allow override per user';
  ALTER TABLE  `sysIdentification`
        ADD  `sys_SetPublicToPendingOnEdit` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
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

  ALTER TABLE  `defCalcFunctions` ADD  `cfn_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defDetailTypeGroups` ADD  `dtg_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defDetailTypes` ADD  `dty_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defFileExtToMimetype` ADD  `fxm_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defLanguages` ADD  `lng_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defOntologies` ADD  `ont_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRecStructure` ADD  `rst_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRecTypeGroups` ADD  `rtg_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRecTypes` ADD  `rty_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defRelationshipConstraints` ADD  `rcs_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defTerms` ADD  `trm_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defTranslations` ADD  `trn_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `defURLPrefixes` ADD  `urp_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `recDetails` ADD  `dtl_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record detail, used to get last updated date for table';

  Alter TABLE  `sysUGrps` ADD ugr_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `usrTags` ADD  `tag_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

  ALTER TABLE  `usrReminders` ADD  `rem_Modified` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table';

-- 25/9/11

  ALTER TABLE  `defOntologies` ADD  `ont_locallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defRecStructure` ADD  `rst_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defRecTypes` ADD  `rty_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defRelationshipConstraints` ADD  `rcs_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defTerms` ADD  `trm_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

  ALTER TABLE  `defDetailTypes` ADD  `dty_LocallyModified` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source';

Alter table defDetailTypeGroups
  change dtg_Order dtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists';
Alter table defRecTypeGroups
  change rtg_Order rtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists';
Alter TABLE recUploadedFiles
  change ulf_UploaderUGrpID ulf_UploaderUGrpID smallint(5) unsigned default NULL COMMENT 'The user who uploaded the file',
  ADD ulf_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
     COMMENT 'The date of last modification of the file description record, automatic update';


END//

  DELIMITER ;


SELECT doTestIf();

DROP function IF EXISTS `doTestIf`;
DROP Procedure IF EXISTS `UpdateDBVersion`;


