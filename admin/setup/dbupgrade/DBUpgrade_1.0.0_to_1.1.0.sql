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

DROP PROCEDURE IF EXISTS sp_ChangeTable;
DROP PROCEDURE IF EXISTS sp_AlterTable;
DROP PROCEDURE IF EXISTS sp_ExecSQL;
DELIMITER $$

CREATE PROCEDURE sp_ExecSQL(exp varchar(2000))
BEGIN
    
    SET @s = exp;
    PREPARE stmt FROM @s;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;    

END$$

CREATE PROCEDURE sp_AlterTable(IN tab varchar(100), IN col varchar(100), IN exp varchar(1000))
BEGIN
     IF (SELECT COUNT(column_name)
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE table_name = tab
              AND table_schema = DATABASE()
              AND column_name = col
        ) = 0 THEN 
        SET @s = concat("ALTER TABLE `",tab,"` ADD COLUMN `",col,"` ",exp,";");
        CALL sp_ExecSQL(@s);
    END IF;
END$$ 

CREATE PROCEDURE sp_ChangeTable(IN tab varchar(100), IN col varchar(100), IN exp varchar(1000))
BEGIN
     IF (SELECT COUNT(column_name)
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE table_name = tab
              AND table_schema = DATABASE()
              AND column_name = col
        ) = 1 THEN 
        SET @s = concat("ALTER TABLE `",tab,"` CHANGE COLUMN `",col,"` ",exp,";");
        CALL sp_ExecSQL(@s);
    END IF;
END$$
 
DELIMITER ;

    -- Add new value 'public'
    ALTER TABLE Records
        CHANGE rec_NonOwnerVisibility rec_NonOwnerVisibility
        ENUM(  'viewable',  'hidden',  'public', 'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Defines if record visible outside owning Workgroup(s)';
        
    ALTER TABLE sysIdentification
        CHANGE  sys_NewRecAccess  sys_NewRecAccess
        ENUM(  'viewable',  'hidden',  'public', 'pending' ) NOT NULL DEFAULT  'viewable'
        COMMENT  'Default visibility for new records - allow override per user';

    CALL sp_AlterTable("sysIdentification","sys_IncomingEmailAddresses",
"VARCHAR( 4000 ) NULL COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins' AFTER  sys_eMailImapPassword");
    CALL sp_AlterTable("sysIdentification","sys_hmlOutputDirectory",
"VARCHAR( 255 ) NULL COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory' AFTER  sys_UploadDirectory");
    CALL sp_AlterTable("sysIdentification","sys_htmlOutputDirectory",
"VARCHAR( 255 ) NULL COMMENT 'Directory in which to write html representation of published records, default to html within upload directory'   AFTER  sys_hmlOutputDirectory");
    CALL sp_AlterTable("sysIdentification","sys_TargetEmailAddresses",
"VARCHAR( 255 ) NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses' AFTER  sys_IncomingEmailAddresses");
    CALL sp_AlterTable("sysIdentification","sys_SetPublicToPendingOnEdit",
"TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''' AFTER  `sys_NewRecAccess`");
  

    CALL sp_AlterTable("sysUGrps","ugr_TargetEmailAddresses",
"VARCHAR( 255 ) NULL COMMENT 'Comma-sep list for selecting target for sending records as data' AFTER  ugr_IncomingEmailAddresses");


  CREATE TABLE IF NOT EXISTS sysDocumentation (
        doc_id tinyint unsigned auto_increment,
        doc_text text Comment "Relevant documentation as text",
   PRIMARY KEY  (doc_id) )
        Comment 'Descriptive information about this database and its function';

  -- Make this single value so it can be linked to the rectype groups table
    CALL  sp_ChangeTable("defRecTypes","rty_RecTypeGroupIDs","rty_RecTypeGroupID TINYINT(3) Unsigned NOT NULL DEFAULT '1'
        COMMENT 'Record type group to which this record type belongs'");
        
    CALL sp_AlterTable("defRecTypes", "rty_RecTypeModelIDs",
"VARCHAR( 63 ) NULL COMMENT 'The model group(s) to which this rectype belongs, comma sep. list' AFTER rty_RecTypeGroupID");

    CALL sp_AlterTable("recUploadedFiles", "ulf_ExternalFileReference",
"VARCHAR(1000) NULL COMMENT 'URI of an external file, which may or may not be cached locally'");
    CALL sp_AlterTable("recUploadedFiles", "ulf_PreferredSource",
"ENUM('local','external') NOT NULL DEFAULT 'local' COMMENT 'Preferred source of file if both local file and external reference set'");

  ALTER TABLE defRelationshipConstraints
      DROP INDEX rcs_CompositeKey,
      ADD UNIQUE rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermID);

  ALTER TABLE defRelationshipConstraints
        CHANGE rcs_TermLimit rcs_TermLimit TINYINT(2) UNSIGNED NULL DEFAULT NULL
        COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used';

   -- not used anywhere in the code
  CALL sp_AlterTable("defRelationshipConstraints","rcs_RelationshipsLimit",
  "TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet'");


-- Add PENDING status to existing non owner visibilites -
-- 12/9/11: these have been applied to H3CoreDefinitions and to sandpit5

  ALTER TABLE Records
        CHANGE rec_NonOwnerVisibility rec_NonOwnerVisibility
        ENUM('viewable','hidden','public','pending') NOT NULL DEFAULT 'viewable'
        COMMENT 'Defines if record visible outside owning Workgroup(s) or to anyone';

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

CALL sp_AlterTable("defCalcFunctions", "cfn_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");

CALL sp_AlterTable("defDetailTypeGroups", "dtg_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defDetailTypes", "dty_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defFileExtToMimetype", "fxm_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defLanguages", "lng_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defOntologies", "ont_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defRecStructure", "rst_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defRecTypeGroups", "rtg_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defRecTypes", "rty_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defRelationshipConstraints", "rcs_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");

CALL sp_AlterTable("defTerms", "trm_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defTranslations", "trn_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("defURLPrefixes", "urp_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("recDetails", "dtl_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("sysUGrps", "ugr_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("usrTags", "tag_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("usrReminders", "rem_Modified","TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table'");
CALL sp_AlterTable("recUploadedFiles", "ulf_Modified","timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
     COMMENT 'The date of last modification of the file description record, automatic update'");

-- 25/9/11

CALL sp_AlterTable("defOntologies", "ont_locallyModified","TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source'");
CALL sp_AlterTable("defRecStructure", "rst_LocallyModified","TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source'");
CALL sp_AlterTable("defRecTypes", "rty_LocallyModified","TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source'");
CALL sp_AlterTable("defRelationshipConstraints", "rcs_LocallyModified","TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source'");
CALL sp_AlterTable("defTerms", "trm_LocallyModified","TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source'");
CALL sp_AlterTable("defDetailTypes", "dty_LocallyModified","TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'
  COMMENT 'Flags a definition element which has been modified relative to the original source'");

ALTER TABLE defDetailTypeGroups
  CHANGE dtg_Order dtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists';
ALTER TABLE defRecTypeGroups
  CHANGE rtg_Order rtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists';
ALTER TABLE recUploadedFiles
  CHANGE ulf_UploaderUGrpID ulf_UploaderUGrpID smallint(5) unsigned default NULL COMMENT 'The user who uploaded the file';
  
update sysIdentification set sys_dbVersion=1,  sys_dbSubVersion=1, sys_dbSubSubVersion=0;

DROP PROCEDURE IF EXISTS sp_ChangeTable;
DROP PROCEDURE IF EXISTS sp_AlterTable;
DROP PROCEDURE IF EXISTS sp_ExecSQL;



