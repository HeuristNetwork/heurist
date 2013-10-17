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
* buildExampleDB.sql: SQL file to create an example Heurist database as a starter/seed database
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

\W -- warnings to standard out, OK for cammand line but not for phpMyAdmin

-- --------------------------------------------------------

-- The rest of this file is a MySQLDump dump of hdb_H3ExampleDB
-- Login is guest+guest

-- *** IMPORTANT *** You MUST use -R or --routines so that stored fuinctions are added to the database

--  mysqldump -u... -p.... -R hdb_H3ExampleDB > hdb_H3ExampleDB.sql

-- ---------------------------------------------------------

-- MySQL dump 10.11
--
-- Host: localhost    Database: hdb_H3ExampleDB
-- ------------------------------------------------------
-- Server version    5.0.51-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Records`
--

DROP TABLE IF EXISTS `Records`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Records` (
  `rec_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'The primary record ID, also called, historically, bib_id',
  `rec_URL` varchar(2000) default NULL COMMENT 'The primary URL pointed to by this record (particularly for Internet bookmarks)',
  `rec_Added` datetime NOT NULL default '0000-00-00 00:00:00' COMMENT 'Date and time record added',
  `rec_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time the record was modified',
  `rec_Title` varchar(1023) NOT NULL COMMENT 'Composite (constructed) title of the record, used for display and search',
  `rec_ScratchPad` text COMMENT 'Scratchpad, mainly for text captured with bookmarklet',
  `rec_RecTypeID` smallint(5) unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
  `rec_AddedByUGrpID` smallint(5) unsigned default NULL COMMENT 'ID of the user who created the record',
  `rec_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Whether added by an import (value 1) or by manual entry (value 0)',
  `rec_Popularity` int(10) unsigned NOT NULL default '0' COMMENT 'Calculated popularity rating for sorting order, set by cron job',
  `rec_FlagTemporary` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a partially created record before fully populated',
  `rec_OwnerUGrpID` smallint(5) unsigned NOT NULL default '0' COMMENT 'User group which owns this record, 0 = everyone',
  `rec_NonOwnerVisibility` enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Defines if record visible outside owning user group(s) or to anyone',
  `rec_URLLastVerified` datetime default NULL COMMENT 'Last date time when URL was verified as contactable',
  `rec_URLErrorMessage` varchar(255) default NULL COMMENT 'Error returned by URL checking script for bad/inaccessible URLs',
  `rec_URLExtensionForMimeType` varchar(10) default NULL COMMENT 'A mime type extension for multimedia files pointed to DIRECTLY by the record URL',
  `rec_Hash` varchar(60) default NULL COMMENT 'A composite truncated metaphones + numeric values hash of significant fields',
  PRIMARY KEY  (`rec_ID`),
  KEY `rec_URL` (`rec_URL`(63)),
  KEY `rec_Title` (`rec_Title`(63)),
  KEY `rec_RecTypeID` (`rec_RecTypeID`),
  KEY `rec_Modified` (`rec_Modified`),
  KEY `rec_OwnerUGrpID` (`rec_OwnerUGrpID`),
  KEY `rec_Hash` (`rec_Hash`(40)),
  KEY `rec_AddedByUGrpID` (`rec_AddedByUGrpID`),
  CONSTRAINT `fk_rec_AddedByUGrpID` FOREIGN KEY (`rec_AddedByUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rec_OwnerUGrpID` FOREIGN KEY (`rec_OwnerUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rec_RecTypeID` FOREIGN KEY (`rec_RecTypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `Records`
--

LOCK TABLES `Records` WRITE;
/*!40000 ALTER TABLE `Records` DISABLE KEYS */;
/*!40000 ALTER TABLE `Records` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `insert_record_trigger` AFTER INSERT ON `Records` FOR EACH ROW begin
    
    
    insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
                                values (@logged_in_user_id, NEW.rec_ID, now());
    set @rec_id := last_insert_id(NEW.rec_ID);
        if NEW.rec_RecTypeID = 1 then
            
            insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,NEW.rec_ID,NEW.rec_ID);
        end if;
    end */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `update_record_trigger` BEFORE UPDATE ON `Records` FOR EACH ROW begin
        if @suppress_update_trigger is null then
            set @rec_version := last_insert_id();
        end if;
        if NEW.rec_URL != OLD.rec_URL OR NEW.rec_URL is null then
            set NEW.rec_URLLastVerified := NULL;
        end if;
    end */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `usrRecentRecords_updater` AFTER UPDATE ON `Records` FOR EACH ROW begin
        declare srcRecID integer;
        declare trgRecID integer;
        if @suppress_update_trigger is null then
            insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
                values (@logged_in_user_id, NEW.rec_ID, now())
                on duplicate key update rre_Time = now();
        end if;
        
        
        if NEW.rec_RecTypeID = 1 AND NOT OLD.rec_RecTypeID = 1 then
            select dtl_Value into srcRecID
                from recDetails
                
                where dtl_DetailTypeID=7 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
            if srcRecID is null then
                set srcRecID = NEW.rec_ID;
            end if;
            select dtl_Value into trgRecID
                from recDetails
                
                where dtl_DetailTypeID=5 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
            if trgRecID is null then
                set trgRecID = NEW.rec_ID;
            end if;
            insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,srcRecID,trgRecID);
        end if;
        
    if OLD.rec_RecTypeID = 1 AND NOT NEW.rec_RecTypeID = 1 then
            delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
        end if;
    end */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `delete_record_trigger` AFTER DELETE ON `Records` FOR EACH ROW begin
        set @rec_version := last_insert_id();
    
        if OLD.rec_RecTypeID = 1 then
            delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
        end if;
    end */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defCalcFunctions`
--

DROP TABLE IF EXISTS `defCalcFunctions`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defCalcFunctions` (
  `cfn_ID` smallint(3) unsigned NOT NULL auto_increment COMMENT 'Primary key of defCalcFunctions table',
  `cfn_Domain` enum('calcfieldstring','pluginphp') NOT NULL default 'calcfieldstring' COMMENT 'Domain of application of this function specification',
  `cfn_FunctionSpecification` text NOT NULL COMMENT 'A function or chain of functions, or some PHP plugin code',
  `cfn_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`cfn_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Specifications for generating calculated fields, plugins and';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defCalcFunctions`
--

LOCK TABLES `defCalcFunctions` WRITE;
/*!40000 ALTER TABLE `defCalcFunctions` DISABLE KEYS */;
/*!40000 ALTER TABLE `defCalcFunctions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defCrosswalk`
--

DROP TABLE IF EXISTS `defCrosswalk`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defCrosswalk` (
  `crw_ID` mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key',
  `crw_SourcedbID` mediumint(8) unsigned NOT NULL COMMENT 'The Heurist reference ID of the database containing the definition being mapped',
  `crw_SourceCode` mediumint(8) unsigned NOT NULL COMMENT 'The code of the definition in the source database',
  `crw_DefType` enum('rectype','constraint','detailtype','recstructure','ontology','vocabulary','term') NOT NULL COMMENT 'The type of code being mapped between the source and this database',
  `crw_LocalCode` mediumint(8) unsigned NOT NULL COMMENT 'The corresponding code in the local database',
  `crw_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date when this mapping added or modified',
  PRIMARY KEY  (`crw_ID`),
  UNIQUE KEY `crw_composite` (`crw_SourcedbID`,`crw_DefType`,`crw_LocalCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Map the codes used in this Heurist DB to codes used in other';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defCrosswalk`
--

LOCK TABLES `defCrosswalk` WRITE;
/*!40000 ALTER TABLE `defCrosswalk` DISABLE KEYS */;
/*!40000 ALTER TABLE `defCrosswalk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defDetailTypeGroups`
--

DROP TABLE IF EXISTS `defDetailTypeGroups`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defDetailTypeGroups` (
  `dtg_ID` tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Primary ID - Code for detail type groups',
  `dtg_Name` varchar(63) NOT NULL COMMENT 'Descriptive heading to be displayed for each group of details (fields)',
  `dtg_Order` tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists',
  `dtg_Description` varchar(255) NOT NULL COMMENT 'General description fo this group of detail (field) types',
  `dtg_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`dtg_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8 COMMENT='Groups detail types for display in separate sections of edit';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defDetailTypeGroups`
--

LOCK TABLES `defDetailTypeGroups` WRITE;
/*!40000 ALTER TABLE `defDetailTypeGroups` DISABLE KEYS */;
INSERT INTO `defDetailTypeGroups` VALUES (1,'Common fields',001,'The commonest details (fields) shared across many record types','2012-09-27 03:52:36'),(8,'Organisational',002,'Field types used for people and organisations','2012-09-27 03:52:36'),(99,'System',002,'Field types used by the system - generally reserved','2012-09-27 03:52:36');
/*!40000 ALTER TABLE `defDetailTypeGroups` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defDetailTypeGroups_insert` AFTER INSERT ON `defDetailTypeGroups` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defDetailTypeGroups_update` AFTER UPDATE ON `defDetailTypeGroups` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defDetailTypeGroups_delete` AFTER DELETE ON `defDetailTypeGroups` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defDetailTypes`
--

DROP TABLE IF EXISTS `defDetailTypes`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defDetailTypes` (
  `dty_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'Code for the detail type (field) - may vary between Heurist DBs',
  `dty_Name` varchar(255) NOT NULL COMMENT 'The canonical (standard) name of the detail type, used as default in edit form',
  `dty_Documentation` varchar(5000) default 'Please document the nature of this detail type (field)) ...' COMMENT 'Documentation of the detail type, what it means, how defined',
  `dty_Type` enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude') NOT NULL COMMENT 'The value-type of this detail type, what sort of data is stored',
  `dty_HelpText` varchar(255) NOT NULL default 'Please provide a short explanation for the user ...' COMMENT 'The default help text displayed to the user under the field',
  `dty_ExtendedDescription` varchar(5000) default 'Please provide an extended description for display on rollover ...' COMMENT 'Extended text describing this detail type, for display in rollover',
  `dty_EntryMask` text COMMENT 'Data entry mask, use to control decimals ion numeric values, content of text fields etc.',
  `dty_Status` enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `dty_OriginatingDBID` mediumint(8) unsigned default NULL COMMENT 'Database where this detail type originated, 0 = locally',
  `dty_NameInOriginatingDB` varchar(255) default NULL COMMENT 'Name used in database where this detail type originated',
  `dty_IDInOriginatingDB` smallint(5) unsigned default NULL COMMENT 'ID used in database where this detail type originated',
  `dty_DetailTypeGroupID` tinyint(3) unsigned NOT NULL default '1' COMMENT 'The general role of this detail allowing differentiated lists of detail types',
  `dty_OrderInGroup` tinyint(3) unsigned default '0' COMMENT 'The display order of DetailType within group, alphabetic if equal values',
  `dty_JsonTermIDTree` varchar(5000) default NULL COMMENT 'Tree of Term IDs to show for this field (display-only header terms set in HeaderTermIDs)',
  `dty_TermIDTreeNonSelectableIDs` varchar(1000) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  `dty_PtrTargetRectypeIDs` varchar(63) default NULL COMMENT 'CSVlist of target Rectype IDs, null = any',
  `dty_FieldSetRectypeID` smallint(5) unsigned default NULL COMMENT 'For a FieldSetMarker, the record type to be inserted as a fieldset',
  `dty_ShowInLists` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if detail type is to be shown in end-user interface, 1=yes',
  `dty_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in ALL record types (overrides rst_VisibleOutsideGroup)',
  `dty_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `dty_LocallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (`dty_ID`),
  UNIQUE KEY `dty_Name` (`dty_Name`),
  KEY `dty_Type` (`dty_Type`),
  KEY `dty_DetailTypeGroupID` (`dty_DetailTypeGroupID`),
  CONSTRAINT `fk_dty_DetailTypeGroupID_1` FOREIGN KEY (`dty_DetailTypeGroupID`) REFERENCES `defDetailTypeGroups` (`dtg_ID`),
  CONSTRAINT `fk_dty_DetailTypeGroupID_2` FOREIGN KEY (`dty_DetailTypeGroupID`) REFERENCES `defDetailTypeGroups` (`dtg_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8 COMMENT='The detail types (fields) which can be attached to records';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defDetailTypes`
--

LOCK TABLES `defDetailTypes` WRITE;
/*!40000 ALTER TABLE `defDetailTypes` DISABLE KEYS */;
INSERT INTO `defDetailTypes` VALUES (1,'Name','Name or phrase used to identify the represented object/entity','freetext','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Name',1,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(2,'Short name','Short or familiar name, phrase or acronym used to identify the represented object/entity','freetext','Short name or acronym','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Short Name',2,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(3,'Short summary','Short summary or description of the object/entity','blocktext','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Short summary',3,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(4,'Extended description','Extended description of the object/entity','blocktext','Extended description','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Extended description',4,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(5,'Target record pointer','Unconstrained pointer to the target linked record for a relationship.','resource','The target record in a relationship record or other record referencing another record with an unconstrained pointer','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Target record pointer',5,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(6,'Relationship type','Term or term phrase representing the conceptual relationship between entities','relationtype','Relationship Type','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Relationship type',6,99,0,'{\"3112\":{\"3197\":{},\"3022\":{},\"3190\":{},\"3040\":{},\"3199\":{},\"3021\":{},\"3054\":{},\"3191\":{},\"3053\":{},\"3067\":{},\"3073\":{},\"3075\":{},\"3200\":{},\"3077\":{},\"3079\":{},\"3192\":{},\"3004\":{},\"3039\":{},\"3078\":{},\"3193\":{},\"3107\":{},\"3198\":{},\"3187\":{},\"3194\":{},\"3072\":{},\"3074\":{},\"3195\":{},\"3196\":{},\"3076\":{},\"3106\":{},\"3201\":{}},\"3123\":{\"3207\":{},\"3208\":{},\"3209\":{},\"3210\":{},\"3211\":{},\"3212\":{},\"3213\":{},\"3167\":{},\"3166\":{},\"3168\":{},\"3169\":{},\"3165\":{},\"3163\":{},\"3164\":{}},\"3114\":{\"3203\":{},\"3020\":{},\"3024\":{},\"3224\":{},\"3036\":{},\"3038\":{},\"3217\":{},\"3181\":{},\"3037\":{},\"3225\":{},\"3058\":{},\"3062\":{},\"3064\":{},\"3066\":{},\"3226\":{},\"3069\":{},\"3204\":{},\"3202\":{},\"3161\":{},\"3019\":{},\"3023\":{},\"3176\":{},\"3035\":{},\"3162\":{},\"3129\":{},\"3102\":{},\"3175\":{},\"3101\":{},\"3057\":{},\"3061\":{},\"3063\":{},\"3065\":{},\"3068\":{},\"3160\":{},\"3159\":{},\"3174\":{}},\"3110\":{\"3084\":{},\"3088\":{},\"3090\":{\"3188\":{},\"3189\":{}},\"3092\":{},\"3094\":{},\"3133\":{},\"3116\":{\"3243\":{},\"3242\":{}},\"3115\":{\"3099\":{},\"3100\":{}},\"3103\":{},\"3105\":{},\"3083\":{},\"3089\":{\"3095\":{},\"3104\":{}},\"3118\":{\"3087\":{},\"3117\":{}}},\"3001\":{\"3170\":{},\"3108\":{},\"3173\":{},\"3172\":{},\"3171\":{},\"3177\":{}},\"3122\":{\"3214\":{},\"3215\":{},\"3216\":{},\"3158\":{},\"3156\":{},\"3157\":{}},\"3111\":{\"3006\":{},\"3009\":{},\"3014\":{},\"3206\":{},\"3184\":{},\"3016\":{},\"3018\":{},\"3026\":{},\"3028\":{},\"3030\":{},\"3032\":{},\"3179\":{},\"3034\":{},\"3042\":{},\"3044\":{},\"3046\":{},\"3048\":{},\"3050\":{},\"3052\":{},\"3056\":{},\"3186\":{},\"3183\":{},\"3060\":{},\"3185\":{},\"3180\":{},\"3071\":{},\"3178\":{},\"3126\":{},\"3205\":{},\"3015\":{},\"3017\":{},\"3025\":{},\"3027\":{},\"3029\":{},\"3031\":{},\"3127\":{},\"3033\":{},\"3041\":{},\"3013\":{},\"3043\":{},\"3045\":{},\"3047\":{},\"3049\":{},\"3182\":{},\"3130\":{},\"3051\":{},\"3055\":{},\"3134\":{},\"3131\":{},\"3059\":{},\"3132\":{},\"3128\":{},\"3070\":{},\"3124\":{},\"3125\":{}},\"3120\":{\"3218\":{},\"3219\":{},\"3220\":{},\"3221\":{},\"3222\":{},\"3223\":{},\"3137\":{},\"3140\":{},\"3136\":{},\"3139\":{},\"3135\":{},\"3138\":{}},\"3109\":{\"3002\":{},\"3003\":{},\"3008\":{},\"3010\":{},\"3119\":{},\"3080\":{},\"3081\":{},\"3082\":{},\"3085\":{},\"3093\":{},\"3096\":{},\"3098\":{},\"3007\":{},\"3097\":{}},\"3113\":{\"3005\":{},\"3012\":{},\"3091\":{},\"3011\":{}},\"3121\":{\"3227\":{},\"3228\":{},\"3229\":{},\"3230\":{},\"3231\":{},\"3232\":{},\"3233\":{},\"3234\":{},\"3235\":{},\"3236\":{},\"3237\":{},\"3238\":{},\"3239\":{},\"3240\":{},\"3241\":{},\"3149\":{},\"3146\":{},\"3148\":{},\"3143\":{},\"3142\":{},\"3147\":{},\"3151\":{},\"3150\":{},\"3153\":{},\"3152\":{},\"3154\":{},\"3141\":{},\"3144\":{},\"3145\":{},\"3155\":{}}}','[\"3112\",\"3123\",\"3114\",\"3110\",\"3001\",\"3122\",\"3111\",\"3120\",\"3109\",\"3113\",\"3121\"]',NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(7,'Source record pointer','Unconstrained pointer to the source linked record for a relationship.','resource','Pointer to the record which is being linked to a target record (often by a relationship record)','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Source record pointer',7,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(8,'Pointer to commentary','Record pointer constrained to commentary records','resource','A pointer to a commentary/interpretation record (for spatial, temporal or general information)','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Pointer to commentary',8,99,0,NULL,NULL,'8',NULL,1,'viewable','2012-09-27 03:52:36',0),(9,'Date','Generic date','date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Date',9,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(10,'Start date','Date representing the beginning of real/conceptual object/entity ','date','Start Date','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Start date',10,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(11,'End date','Date representing the ending of real/conceptual object/entity','date','End Date','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'End date',11,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(12,'Heurist Query String','Heurist Query String','freetext','A string representing a valid Heurist query.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Query String',12,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(13,'Record pointer','General record pointer','resource','Resource Identifier (unconstrained record pointer)','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Record pointer',13,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(14,'Transcriber of interpretation','Record pointer constrained to person or organisation records','resource','Pointer to the person who transcribes or summarises interpretation information ','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Transcriber of interpretation',14,99,0,NULL,NULL,'4,10',NULL,1,'viewable','2012-09-27 03:52:36',0),(15,'Creator - author, organisation, artist etc.','Record pointer constrained to person or organisation records','resource','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',NULL,'reserved',2,'Creator - author, artist etc.',15,1,0,NULL,NULL,'4,10',NULL,1,'viewable','2012-09-27 03:52:36',0),(16,'Pointer to Person','Record pointer constrained to person records','resource','Pointers to person','A pointer field constrained to point to a Person record',NULL,'approved',2,'Pointer to Person',16,8,0,NULL,NULL,'10',NULL,1,'viewable','2012-09-27 03:52:36',0),(17,'Contact details or URL','Please document the nature of this detail type (field)) ...','freetext','Contact details, either as text or a URL to eg. a phonebook record or search','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Contact details or URL',17,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(18,'Given names','Name or name list additionally given to the represented object/entity ','freetext','Given names','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Given names',18,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(19,'Honorific','Prefix for the object\'s name representing status and/or achievement','enum','Title or honorific (Dr, Mrs, Ms, Professor, Sir etc.)','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Honorific',19,8,0,'{\"97\":{},\"96\":{},\"529\":{},\"93\":{},\"94\":{},\"95\":{},\"98\":{}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(20,'Gender','Gender type','enum','Gender','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Gender',20,8,0,'{\"415\":{},\"414\":{},\"527\":{},\"528\":{}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(21,'Pointer to Organisation','Record pointer constrained to organisation records','resource','A pointer field which is constrained to point to an Organisation record','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Pointer to Organisation',21,8,0,NULL,NULL,'4',NULL,1,'viewable','2012-09-27 03:52:36',0),(22,'Organisation type','Term categorising the organisation object type','enum','Organisation type','Please provide an extended description for display on rollover ...',NULL,'open',2,'Organisation type',22,8,0,'{\"17\":{},\"132\":{},\"130\":{},\"14\":{},\"15\":{},\"19\":{},\"129\":{},\"18\":{},\"12\":{},\"128\":{},\"20\":{},\"16\":{},\"21\":{},\"13\":{},\"131\":{},\"133\":{},\"11\":{\"518\":{},\"519\":{}}}','[\"11\"]',NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(23,'Email address','Generic email address associated with the object/entity','freetext','Email address','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Email address',23,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(24,'Email of sender','Email address associated with the sender of an email object/entity','freetext','Email of sender','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Email of sender',24,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(25,'Email of recipients','Email address(es) associated with the recipient(s) of an email object/entity','freetext','Email of recipients','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Email of recipients',25,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(26,'Country','Term identifying the country associated with the object/entity','enum','Country','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Country',26,1,0,'{\"204\":{},\"205\":{},\"206\":{},\"207\":{},\"208\":{},\"209\":{},\"210\":{},\"211\":{},\"212\":{},\"213\":{},\"214\":{},\"215\":{},\"216\":{},\"108\":{},\"217\":{},\"218\":{},\"219\":{},\"220\":{},\"221\":{},\"222\":{},\"223\":{},\"184\":{},\"224\":{},\"225\":{},\"226\":{},\"227\":{},\"228\":{},\"229\":{},\"230\":{},\"231\":{},\"185\":{},\"232\":{},\"233\":{},\"234\":{},\"235\":{},\"236\":{},\"251\":{},\"114\":{},\"237\":{},\"180\":{},\"238\":{},\"239\":{},\"240\":{},\"241\":{},\"242\":{},\"109\":{},\"243\":{},\"244\":{},\"245\":{},\"246\":{},\"247\":{},\"248\":{},\"249\":{},\"250\":{},\"252\":{},\"253\":{},\"160\":{},\"254\":{},\"195\":{},\"255\":{},\"256\":{},\"257\":{},\"165\":{},\"258\":{},\"259\":{},\"260\":{},\"261\":{},\"262\":{},\"263\":{},\"264\":{},\"265\":{},\"266\":{},\"267\":{},\"268\":{},\"110\":{},\"269\":{},\"270\":{},\"271\":{},\"272\":{},\"273\":{},\"274\":{},\"182\":{},\"275\":{},\"276\":{},\"111\":{},\"277\":{},\"278\":{},\"279\":{},\"280\":{},\"281\":{},\"282\":{},\"283\":{},\"284\":{},\"285\":{},\"286\":{},\"287\":{},\"288\":{},\"289\":{},\"290\":{},\"183\":{},\"291\":{},\"172\":{},\"292\":{},\"167\":{},\"168\":{},\"181\":{},\"293\":{},\"166\":{},\"112\":{},\"294\":{},\"295\":{},\"296\":{},\"161\":{},\"297\":{},\"298\":{},\"299\":{},\"300\":{},\"301\":{},\"302\":{},\"164\":{},\"303\":{},\"304\":{},\"305\":{},\"306\":{},\"307\":{},\"308\":{},\"309\":{},\"310\":{},\"311\":{},\"312\":{},\"313\":{},\"314\":{},\"315\":{},\"316\":{},\"317\":{},\"318\":{},\"319\":{},\"320\":{},\"321\":{},\"322\":{},\"323\":{},\"324\":{},\"325\":{},\"326\":{},\"327\":{},\"328\":{},\"329\":{},\"330\":{},\"331\":{},\"332\":{},\"333\":{},\"171\":{},\"334\":{},\"335\":{},\"336\":{},\"178\":{},\"337\":{},\"338\":{},\"117\":{},\"339\":{},\"340\":{},\"341\":{},\"342\":{},\"343\":{},\"344\":{},\"177\":{},\"345\":{},\"346\":{},\"347\":{},\"348\":{},\"349\":{},\"350\":{},\"351\":{},\"352\":{},\"353\":{},\"354\":{},\"355\":{},\"356\":{},\"357\":{},\"358\":{},\"359\":{},\"360\":{},\"361\":{},\"362\":{},\"363\":{},\"364\":{},\"365\":{},\"366\":{},\"367\":{},\"368\":{},\"369\":{},\"370\":{},\"371\":{},\"372\":{},\"373\":{},\"374\":{},\"375\":{},\"376\":{},\"377\":{},\"173\":{},\"378\":{},\"379\":{},\"380\":{},\"381\":{},\"179\":{},\"382\":{},\"113\":{},\"383\":{},\"384\":{},\"385\":{},\"386\":{},\"387\":{},\"186\":{},\"388\":{},\"169\":{},\"198\":{},\"389\":{},\"390\":{},\"116\":{},\"391\":{},\"392\":{},\"393\":{},\"394\":{},\"395\":{},\"396\":{},\"170\":{},\"397\":{},\"398\":{},\"399\":{},\"162\":{},\"400\":{},\"118\":{},\"401\":{},\"402\":{},\"403\":{},\"119\":{},\"163\":{},\"404\":{},\"405\":{},\"115\":{},\"406\":{},\"407\":{},\"408\":{},\"409\":{},\"410\":{},\"411\":{},\"412\":{}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(27,'Place name','Term  or phrase identifying the location associated with the object/entity','freetext','The name of a place/location','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Place name',27,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(28,'Geospatial object','Geospatial shape associated with the object/entity','geo','A geospatial object providing a mappable location for the record','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Geospatial object',28,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(29,'Mime Type','Please document the nature of this detail type (field)) ...','enum','Mime Type','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Mime Type',29,99,0,'{\"534\":{},\"535\":{},\"536\":{},\"537\":{},\"538\":{},\"539\":{},\"540\":{},\"541\":{},\"542\":{}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(30,'Tiled image type','Please document the nature of this detail type (field)) ...','enum','The type (map or image) of the tiled image.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Map image layer type',30,99,0,'{\"544\":{},\"545\":{}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(31,'Image tiling schema','Please document the nature of this detail type (field)) ...','enum','Image tiling schema','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Map image layer tiling schema',31,99,0,'{\"547\":{},\"548\":{},\"549\":{},\"550\":{}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(32,'Minimum zoom level','Please document the nature of this detail type (field)) ...','integer','Minimum zoom level','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Minimum zoom level',32,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(33,'Maximum zoom level','Please document the nature of this detail type (field)) ...','integer','Maximum zoom level','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Maximum zoom level',33,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(34,'Service URL','Please document the nature of this detail type (field)) ...','freetext','Service URL','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Service URL',34,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(35,'Copyright information','Please document the nature of this detail type (field)) ...','blocktext','Copyright information','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Copyright information',35,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(36,'Original ID','Please document the nature of this detail type (field)) ...','freetext','The original ID of the record in a source database from which these data were imported','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Original ID',36,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(37,'Unique public identifier','Please document the nature of this detail type (field)) ...','freetext','A public identifier such as the NLA party identifier (Australia) or other national/international system','Please provide an extended description for display on rollover ...',NULL,'approved',2,'Unique public identifier',37,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(38,'File resource','Please document the nature of this detail type (field)) ...','file','An uploaded file or a reference to a file through a URI','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'File resource',38,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(39,'Thumbnail image','Please document the nature of this detail type (field)) ...','file','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Thumbnail image',39,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(40,'Heurist filter string','Heurist Filter String','freetext','A string representing a valid Heurist filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]}','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Filter string',40,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(41,'File Type','Please document the nature of this detail type (field)) ...','enum','Term identifying the file format','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'File Type',41,1,0,'{\"127\":{},\"522\":{},\"521\":{\"523\":{},\"699\":{},\"524\":{\"526\":{},\"525\":{\"704\":{},\"705\":{}},\"700\":{\"701\":{},\"702\":{},\"703\":{}}}}}',NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(42,'Annotated Resource','Please document the nature of this detail type (field)) ...','resource','Pointer to the resource being annotated','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Annotated Resource',42,99,0,NULL,NULL,'11,5,2,13',NULL,1,'viewable','2012-09-27 03:52:36',0),(43,'Annotation Range','Please document the nature of this detail type (field)) ...','freetext','An encoded string defining the area of the annotated document being annotated','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Annotated Range',43,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(44,'Start Word','Please document the nature of this detail type (field)) ...','integer','Start Word','Please provide an extended description for display on rollover ...',NULL,'open',2,'Start Word',44,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(45,'End Word','Please document the nature of this detail type (field)) ...','integer','End Word','Please provide an extended description for display on rollover ...',NULL,'open',2,'End Word',45,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(46,'Start Element','Please document the nature of this detail type (field)) ...','freetext','Start Element','Please provide an extended description for display on rollover ...',NULL,'open',2,'Start Element',46,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(47,'End Element','Please document the nature of this detail type (field)) ...','freetext','End Element','Please provide an extended description for display on rollover ...',NULL,'open',2,'End Element',47,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(48,'Heurist Layout String','Please document the nature of this detail type (field)) ...','freetext','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Heurist Layout String',48,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(49,'Version Number','Please document the nature of this detail type (field)) ...','freetext','Numeric string representing a version, typically a squence of numbers separated by full stop.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Version Number',49,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(50,'Transform Pointer','Please document the nature of this detail type (field)) ...','resource','Pointer to a transform or pipeline transform record','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Transform Pointer',50,99,0,NULL,NULL,'14,17',NULL,1,'viewable','2012-09-27 03:52:36',0),(51,'Property (N:V)','This is a generic property field for extending a records properties by adding a name:value pair. Note the colon used as a separator.','freetext','Attribute expressed as a name:value pair','Please enter property name foloowed by : followed by the value of the property',NULL,'open',2,'Property (N:V)',51,99,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-27 03:52:36',0),(52,'Tool Type','Please document the nature of this detail type (field)) ...','enum','Please provide a short explanation for the user ...','Please provide an extended description for display on rollover ...',NULL,'open',2,'Tool Type',52,99,0,'{\"706\":{\"707\":{\"708\":{\"709\":{}},\"710\":{\"711\":{},\"712\":{},\"713\":{}},\"714\":{\"715\":{}},\"716\":{\"717\":{},\"718\":{},\"719\":{}}}}}','[\"706\"]',NULL,NULL,1,'viewable','2012-09-27 03:52:36',1),(53,'Record Type','Please document the nature of this detail type (field)) ...','freetext','Record Type Concept-ID','Enter the origin dbID-recTypeID hyphenated id pair.',NULL,'approved',2,'Record Type',53,99,0,'','','',NULL,1,'viewable','2012-09-27 03:52:36',0),(54,'Detail Type','Please document the nature of this detail type (field)) ...','freetext','Detail Type Concept-ID','Enter the origin dbID-detailTypeID hyphenated id pair.',NULL,'approved',2,'Detail Type',54,99,0,'','','',NULL,1,'viewable','2012-09-27 03:52:36',0),(55,'Application Command','Please document the nature of this detail type (field)) ...','freetext','Application Command','Enter the text for the command to be executed when the tool is invoked in the application',NULL,'open',2,'Application Command',55,99,0,'','','',NULL,1,'viewable','2012-09-27 03:52:36',0),(56,'Colour','Please document the nature of this detail type (field)) ...','enum','Colour','',NULL,'open',2,'Colour',56,99,0,'{\"551\":{\"552\":{},\"553\":{},\"554\":{},\"555\":{},\"556\":{},\"557\":{},\"558\":{},\"559\":{},\"560\":{},\"561\":{},\"562\":{},\"563\":{},\"564\":{},\"565\":{},\"566\":{},\"567\":{},\"568\":{},\"569\":{},\"570\":{},\"571\":{},\"572\":{},\"573\":{},\"574\":{},\"575\":{},\"576\":{},\"578\":{},\"577\":{},\"579\":{},\"580\":{},\"581\":{},\"582\":{},\"583\":{},\"584\":{},\"585\":{},\"586\":{},\"587\":{},\"588\":{},\"589\":{},\"590\":{},\"591\":{},\"592\":{},\"593\":{},\"594\":{},\"595\":{},\"596\":{},\"597\":{},\"598\":{},\"599\":{},\"600\":{},\"601\":{},\"602\":{},\"603\":{},\"604\":{},\"605\":{},\"607\":{},\"608\":{},\"606\":{},\"609\":{},\"610\":{},\"611\":{},\"612\":{},\"613\":{},\"614\":{},\"615\":{},\"616\":{},\"617\":{},\"618\":{},\"619\":{},\"620\":{},\"621\":{},\"622\":{},\"623\":{},\"625\":{},\"624\":{},\"626\":{},\"627\":{},\"628\":{},\"629\":{},\"630\":{},\"631\":{},\"632\":{},\"633\":{},\"634\":{},\"635\":{},\"636\":{},\"637\":{},\"638\":{},\"639\":{},\"640\":{},\"641\":{},\"642\":{},\"643\":{},\"644\":{},\"645\":{},\"646\":{},\"647\":{},\"648\":{},\"649\":{},\"650\":{},\"651\":{},\"652\":{},\"653\":{},\"654\":{},\"655\":{},\"656\":{},\"657\":{},\"658\":{},\"659\":{},\"660\":{},\"661\":{},\"662\":{},\"663\":{},\"664\":{},\"665\":{},\"666\":{},\"667\":{},\"668\":{},\"669\":{},\"670\":{},\"671\":{},\"672\":{},\"673\":{},\"674\":{},\"675\":{},\"676\":{},\"677\":{},\"678\":{},\"679\":{},\"680\":{},\"681\":{},\"682\":{},\"683\":{},\"684\":{},\"685\":{},\"686\":{},\"687\":{},\"688\":{},\"689\":{},\"690\":{},\"691\":{},\"692\":{},\"693\":{},\"694\":{},\"695\":{},\"696\":{},\"697\":{},\"698\":{}}}','[\"551\"]','',NULL,1,'viewable','2012-09-27 03:52:36',0);
/*!40000 ALTER TABLE `defDetailTypes` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defDetailTypes_last_insert` AFTER INSERT ON `defDetailTypes` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defDetailTypes_last_update` AFTER UPDATE ON `defDetailTypes` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defDetailTypes_delete` AFTER DELETE ON `defDetailTypes` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defFileExtToMimetype`
--

DROP TABLE IF EXISTS `defFileExtToMimetype`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defFileExtToMimetype` (
  `fxm_Extension` varchar(10) NOT NULL COMMENT 'The file extension, indicates mimetype, icon and some beahviours',
  `fxm_MimeType` varchar(100) NOT NULL COMMENT 'The standard mime type string',
  `fxm_OpenNewWindow` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag if a new window should be opened to display this mimetype',
  `fxm_IconFileName` varchar(31) default NULL COMMENT 'Filename of the icon file for this mimetype (shared by several)',
  `fxm_FiletypeName` varchar(31) default NULL COMMENT 'A textual name for the file type represented by the extension',
  `fxm_ImagePlaceholder` varchar(63) default NULL COMMENT 'Thumbnail size representation for display, generate from fxm_FiletypeName',
  `fxm_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`fxm_Extension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Converts extensions to mimetypes and provides icons and mime';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defFileExtToMimetype`
--

LOCK TABLES `defFileExtToMimetype` WRITE;
/*!40000 ALTER TABLE `defFileExtToMimetype` DISABLE KEYS */;
INSERT INTO `defFileExtToMimetype` VALUES ('16','',0,'','','','2012-09-27 03:52:36'),('183','',0,'','','','2012-09-27 03:52:36'),('ai','application/postscript',0,'','','','2012-09-27 03:52:36'),('aif','audio/x-aiff',0,'movie.gif','AIFF audio','','2012-09-27 03:52:36'),('aifc','audio/x-aiff',0,'movie.gif','AIFF audio','','2012-09-27 03:52:36'),('aiff','audio/x-aiff',0,'movie.gif','AIFF audio','','2012-09-27 03:52:36'),('ama','',0,'','','','2012-09-27 03:52:36'),('asc','text/plain',1,'txt.gif','Plain text','','2012-09-27 03:52:36'),('au','audio/basic',0,'movie.gif','AU audio','','2012-09-27 03:52:36'),('avi','video/x-msvideo',0,'movie.gif','AVI video','','2012-09-27 03:52:36'),('bcpio','application/x-bcpio',0,'','','','2012-09-27 03:52:36'),('bin','application/octet-stream',0,'','BinHex','','2012-09-27 03:52:36'),('bmp','image/bmp',1,'image.gif','BMP image','','2012-09-27 03:52:36'),('c','',0,'','','','2012-09-27 03:52:36'),('cda','',0,'','','','2012-09-27 03:52:36'),('cdf','application/x-netcdf',0,'','','','2012-09-27 03:52:36'),('cgm','image/cgm',0,'image.gif','','','2012-09-27 03:52:36'),('class','application/octet-stream',0,'','Java','','2012-09-27 03:52:36'),('cpio','application/x-cpio',0,'','','','2012-09-27 03:52:36'),('cpt','application/mac-compactpro',0,'','','','2012-09-27 03:52:36'),('csh','application/x-csh',0,'','','','2012-09-27 03:52:36'),('css','text/css',1,'','CSS stylesheet','','2012-09-27 03:52:36'),('csv','',0,'','','','2012-09-27 03:52:36'),('dat','',0,'','','','2012-09-27 03:52:36'),('db','',0,'','','','2012-09-27 03:52:36'),('dcr','application/x-director',0,'','','','2012-09-27 03:52:36'),('dir','application/x-director',0,'','','','2012-09-27 03:52:36'),('djv','image/vnd.djvu',1,'image.gif','','','2012-09-27 03:52:36'),('djvu','image/vnd.djvu',1,'image.gif','','','2012-09-27 03:52:36'),('dll','application/octet-stream',0,'','Windows system','','2012-09-27 03:52:36'),('dmg','application/octet-stream',0,'','','','2012-09-27 03:52:36'),('dms','application/octet-stream',0,'','','','2012-09-27 03:52:36'),('doc','application/msword',0,'doc.gif','MS Word','','2012-09-27 03:52:36'),('docx','',0,'','','','2012-09-27 03:52:36'),('dot','',0,'','','','2012-09-27 03:52:36'),('Dr_Elaine_','',0,'','','','2012-09-27 03:52:36'),('dtd','application/xml-dtd',1,'','','','2012-09-27 03:52:36'),('dvi','application/x-dvi',0,'','','','2012-09-27 03:52:36'),('dxr','application/x-director',0,'','','','2012-09-27 03:52:36'),('eml','',0,'','','','2012-09-27 03:52:36'),('enz','',0,'','','','2012-09-27 03:52:36'),('eps','application/postscript',0,'','PostScript','','2012-09-27 03:52:36'),('etx','text/x-setext',0,'','','','2012-09-27 03:52:36'),('exe','application/octet-stream',0,'','Windows executable','','2012-09-27 03:52:36'),('ez','application/andrew-inset',0,'','','','2012-09-27 03:52:36'),('flv','',0,'','','','2012-09-27 03:52:36'),('gif','image/gif',1,'image.gif','GIF image','','2012-09-27 03:52:36'),('gra','',0,'','','','2012-09-27 03:52:36'),('gram','application/srgs',0,'','','','2012-09-27 03:52:36'),('grxml','application/srgs+xml',1,'','','','2012-09-27 03:52:36'),('gtar','application/x-gtar',0,'','','','2012-09-27 03:52:36'),('hdf','application/x-hdf',0,'','','','2012-09-27 03:52:36'),('hqx','application/mac-binhex40',0,'','BinHex compressed','','2012-09-27 03:52:36'),('htm','text/html',1,'html.gif','HTML source','','2012-09-27 03:52:36'),('html','text/html',1,'html.gif','HTML source','','2012-09-27 03:52:36'),('ice','x-conference/x-cooltalk',0,'','','','2012-09-27 03:52:36'),('ico','image/x-icon',0,'image.gif','','','2012-09-27 03:52:36'),('ics','text/calendar',0,'','','','2012-09-27 03:52:36'),('ief','image/ief',0,'image.gif','','','2012-09-27 03:52:36'),('ifb','text/calendar',0,'','','','2012-09-27 03:52:36'),('iges','model/iges',0,'','','','2012-09-27 03:52:36'),('igs','model/iges',0,'','','','2012-09-27 03:52:36'),('ini','',0,'','','','2012-09-27 03:52:36'),('jar','',0,'','','','2012-09-27 03:52:36'),('jpe','image/jpeg',1,'image.gif','JPEG image','','2012-09-27 03:52:36'),('jpeg','image/jpeg',1,'image.gif','JPEG image','','2012-09-27 03:52:36'),('jpg','image/jpeg',1,'image.gif','JPEG image','','2012-09-27 03:52:36'),('js','application/x-javascript',0,'','JavaScript','','2012-09-27 03:52:36'),('kar','audio/midi',0,'movie.gif','','','2012-09-27 03:52:36'),('kml','',0,'','','','2012-09-27 03:52:36'),('kmz','',0,'','','','2012-09-27 03:52:36'),('latex','application/x-latex',0,'','LaTeX source','','2012-09-27 03:52:36'),('lha','application/octet-stream',0,'','LHA compressed','','2012-09-27 03:52:36'),('lnk','',0,'','','','2012-09-27 03:52:36'),('lzh','application/octet-stream',0,'','LZH compressed','','2012-09-27 03:52:36'),('m3u','audio/x-mpegurl',0,'movie.gif','','','2012-09-27 03:52:36'),('m4u','video/vnd.mpegurl',0,'movie.gif','','','2012-09-27 03:52:36'),('man','application/x-troff-man',0,'','','','2012-09-27 03:52:36'),('mathml','application/mathml+xml',1,'','MathML','','2012-09-27 03:52:36'),('me','application/x-troff-me',0,'','','','2012-09-27 03:52:36'),('mesh','model/mesh',0,'','','','2012-09-27 03:52:36'),('mid','audio/midi',0,'movie.gif','MIDI audio','','2012-09-27 03:52:36'),('midi','audio/midi',0,'movie.gif','MIDI audio','','2012-09-27 03:52:36'),('mif','application/vnd.mif',0,'','','','2012-09-27 03:52:36'),('mov','video/quicktime',0,'movie.gif','QuickTime video','','2012-09-27 03:52:36'),('movie','video/x-sgi-movie',0,'movie.gif','','','2012-09-27 03:52:36'),('mp2','audio/mpeg',0,'movie.gif','MPEG audio','','2012-09-27 03:52:36'),('mp3','audio/mpeg',0,'movie.gif','MP3 audio','','2012-09-27 03:52:36'),('mpe','video/mpeg',0,'movie.gif','MPEG video','','2012-09-27 03:52:36'),('mpeg','video/mpeg',0,'movie.gif','MPEG video','','2012-09-27 03:52:36'),('mpg','video/mpeg',0,'movie.gif','MPEG video','','2012-09-27 03:52:36'),('mpga','audio/mpeg',0,'movie.gif','MPEG audio','','2012-09-27 03:52:36'),('ms','application/x-troff-ms',0,'','','','2012-09-27 03:52:36'),('msh','model/mesh',0,'','','','2012-09-27 03:52:36'),('mxd','',0,'','','','2012-09-27 03:52:36'),('mxu','video/vnd.mpegurl',0,'movie.gif','','','2012-09-27 03:52:36'),('nc','application/x-netcdf',0,'','','','2012-09-27 03:52:36'),('oda','application/oda',0,'','','','2012-09-27 03:52:36'),('odt','',0,'','','','2012-09-27 03:52:36'),('ogg','application/ogg',0,'','Ogg Vorbis','','2012-09-27 03:52:36'),('one','',0,'','','','2012-09-27 03:52:36'),('owl','',0,'','','','2012-09-27 03:52:36'),('ozf2','',0,'','','','2012-09-27 03:52:36'),('pbm','image/x-portable-bitmap',1,'image.gif','','','2012-09-27 03:52:36'),('pdb','chemical/x-pdb',0,'','','','2012-09-27 03:52:36'),('pdf','application/pdf',1,'pdf.gif','Adobe Acrobat','','2012-09-27 03:52:36'),('pgm','image/x-portable-graymap',1,'image.gif','','','2012-09-27 03:52:36'),('pgn','application/x-chess-pgn',0,'','','','2012-09-27 03:52:36'),('Photos Mas','',0,'','','','2012-09-27 03:52:36'),('pl','',0,'','','','2012-09-27 03:52:36'),('png','image/png',1,'image.gif','PNG image','','2012-09-27 03:52:36'),('pnm','image/x-portable-anymap',1,'image.gif','','','2012-09-27 03:52:36'),('ppm','image/x-portable-pixmap',1,'image.gif','','','2012-09-27 03:52:36'),('pps','',0,'','','','2012-09-27 03:52:36'),('ppt','application/vnd.ms-powerpoint',0,'','MS Powerpoint','','2012-09-27 03:52:36'),('pptx','',0,'','','','2012-09-27 03:52:36'),('ps','application/postscript',0,'','PostScript','','2012-09-27 03:52:36'),('psd','',0,'','','','2012-09-27 03:52:36'),('pyramid to','',0,'','','','2012-09-27 03:52:36'),('qt','video/quicktime',0,'movie.gif','QuickTime video','','2012-09-27 03:52:36'),('ra','audio/x-pn-realaudio',0,'movie.gif','RealAudio','','2012-09-27 03:52:36'),('ram','audio/x-pn-realaudio',0,'movie.gif','RealAudio','','2012-09-27 03:52:36'),('ras','image/x-cmu-raster',1,'image.gif','','','2012-09-27 03:52:36'),('rdf','application/rdf+xml',1,'','Mozilla extension','','2012-09-27 03:52:36'),('References','',0,'','','','2012-09-27 03:52:36'),('rgb','image/x-rgb',0,'image.gif','','','2012-09-27 03:52:36'),('rm','application/vnd.rn-realmedia',0,'','RealAudio','','2012-09-27 03:52:36'),('roff','application/x-troff',0,'','','','2012-09-27 03:52:36'),('rtf','text/rtf',1,'','Rich text','','2012-09-27 03:52:36'),('rtx','text/richtext',1,'','','','2012-09-27 03:52:36'),('sgm','text/sgml',1,'','SGML source','','2012-09-27 03:52:36'),('sgml','text/sgml',1,'','SGML source','','2012-09-27 03:52:36'),('sh','application/x-sh',0,'','','','2012-09-27 03:52:36'),('shar','application/x-shar',0,'','','','2012-09-27 03:52:36'),('shp','',0,'','','','2012-09-27 03:52:36'),('shs','',0,'','','','2012-09-27 03:52:36'),('silo','model/mesh',0,'','','','2012-09-27 03:52:36'),('sit','application/x-stuffit',0,'','StuffIt compressed','','2012-09-27 03:52:36'),('skd','application/x-koan',0,'','','','2012-09-27 03:52:36'),('skm','application/x-koan',0,'','','','2012-09-27 03:52:36'),('skp','application/x-koan',0,'','','','2012-09-27 03:52:36'),('skt','application/x-koan',0,'','','','2012-09-27 03:52:36'),('smi','application/smil',0,'','','','2012-09-27 03:52:36'),('smil','application/smil',0,'','','','2012-09-27 03:52:36'),('snd','audio/basic',0,'movie.gif','SND audio','','2012-09-27 03:52:36'),('so','application/octet-stream',0,'','','','2012-09-27 03:52:36'),('spl','application/x-futuresplash',0,'','','','2012-09-27 03:52:36'),('sql','',0,'','','','2012-09-27 03:52:36'),('src','application/x-wais-source',0,'','','','2012-09-27 03:52:36'),('sv4cpio','application/x-sv4cpio',0,'','','','2012-09-27 03:52:36'),('sv4crc','application/x-sv4crc',0,'','','','2012-09-27 03:52:36'),('svg','image/svg+xml',1,'image.gif','SVG graphics','','2012-09-27 03:52:36'),('swf','application/x-shockwave-flash',0,'','Adobe Shockwave','','2012-09-27 03:52:36'),('t','application/x-troff',0,'','','','2012-09-27 03:52:36'),('tar','application/x-tar',0,'','TAR archive','','2012-09-27 03:52:36'),('tcl','application/x-tcl',0,'','','','2012-09-27 03:52:36'),('Teotihuaca','',0,'','','','2012-09-27 03:52:36'),('tex','application/x-tex',0,'','TeX source','','2012-09-27 03:52:36'),('texi','application/x-texinfo',0,'','','','2012-09-27 03:52:36'),('texinfo','application/x-texinfo',0,'','','','2012-09-27 03:52:36'),('the rocks ','',0,'','','','2012-09-27 03:52:36'),('tif','image/tiff',0,'image.gif','TIFF image','','2012-09-27 03:52:36'),('tiff','image/tiff',0,'image.gif','TIFF image','','2012-09-27 03:52:36'),('tr','application/x-troff',0,'','','','2012-09-27 03:52:36'),('tsv','text/tab-separated-values',1,'','','','2012-09-27 03:52:36'),('ttf','',0,'','','','2012-09-27 03:52:36'),('txt','text/plain',1,'txt.gif','Plain text','','2012-09-27 03:52:36'),('ustar','application/x-ustar',0,'','','','2012-09-27 03:52:36'),('vcd','application/x-cdlink',0,'','','','2012-09-27 03:52:36'),('VOB','',0,'','','','2012-09-27 03:52:36'),('vrml','model/vrml',1,'','','','2012-09-27 03:52:36'),('vsd','',0,'','','','2012-09-27 03:52:36'),('vxml','application/voicexml+xml',1,'','','','2012-09-27 03:52:36'),('wav','audio/x-wav',0,'movie.gif','WAV audio','','2012-09-27 03:52:36'),('wbmp','image/vnd.wap.wbmp',1,'image.gif','','','2012-09-27 03:52:36'),('wbxml','application/vnd.wap.wbxml',1,'','','','2012-09-27 03:52:36'),('wma','',0,'','','','2012-09-27 03:52:36'),('WMF','',0,'','','','2012-09-27 03:52:36'),('wml','text/vnd.wap.wml',0,'','','','2012-09-27 03:52:36'),('wmlc','application/vnd.wap.wmlc',0,'','','','2012-09-27 03:52:36'),('wmls','text/vnd.wap.wmlscript',0,'','','','2012-09-27 03:52:36'),('wmlsc','application/vnd.wap.wmlscriptc',0,'','','','2012-09-27 03:52:36'),('wmv','',0,'','','','2012-09-27 03:52:36'),('wrl','model/vrml',1,'','','','2012-09-27 03:52:36'),('xbm','image/x-xbitmap',0,'image.gif','','','2012-09-27 03:52:36'),('xht','application/xhtml+xml',1,'','','','2012-09-27 03:52:36'),('xhtml','application/xhtml+xml',1,'','XHTML','','2012-09-27 03:52:36'),('xls','application/vnd.ms-excel',0,'','MS Excel spreadsheet','','2012-09-27 03:52:36'),('xlsx','',0,'','','','2012-09-27 03:52:36'),('xml','application/xml',1,'','XML','','2012-09-27 03:52:36'),('xpm','image/x-xpixmap',0,'image.gif','','','2012-09-27 03:52:36'),('xsl','application/xml',1,'','XSL stylesheet','','2012-09-27 03:52:36'),('xslt','application/xslt+xml',1,'','','','2012-09-27 03:52:36'),('xul','application/vnd.mozilla.xul+xml',1,'','','','2012-09-27 03:52:36'),('xwd','image/x-xwindowdump',0,'image.gif','','','2012-09-27 03:52:36'),('xyz','chemical/x-xyz',0,'','','','2012-09-27 03:52:36'),('zip','application/zip',0,'zip.gif','ZIP compressed','','2012-09-27 03:52:36');
/*!40000 ALTER TABLE `defFileExtToMimetype` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defLanguages`
--

DROP TABLE IF EXISTS `defLanguages`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defLanguages` (
  `lng_NISOZ3953` char(3) NOT NULL COMMENT 'Three character NISO Z39.53 language code',
  `lng_ISO639` char(2) NOT NULL COMMENT 'Two character ISO639 language code',
  `lng_Name` varchar(63) NOT NULL COMMENT 'Language name, generally accepted name (normally English terminology)',
  `lng_Notes` varchar(1000) default NULL COMMENT 'URL reference to, or notes on the definition of the language',
  `lng_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`lng_NISOZ3953`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Language list including optional standard language codes';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defLanguages`
--

LOCK TABLES `defLanguages` WRITE;
/*!40000 ALTER TABLE `defLanguages` DISABLE KEYS */;
INSERT INTO `defLanguages` VALUES ('ARA','AR','Arabic',NULL,'2012-09-27 03:52:31'),('CAM','KM','Khmer',NULL,'2012-09-27 03:52:31'),('CHI','ZH','Chinese',NULL,'2012-09-27 03:52:31'),('CZE','CS','Czech',NULL,'2012-09-27 03:52:31'),('DAN','DA','Danish',NULL,'2012-09-27 03:52:31'),('DUT','NL','Dutch',NULL,'2012-09-27 03:52:31'),('ENG','EN','English',NULL,'2012-09-27 03:52:31'),('EST','ET','Estonian',NULL,'2012-09-27 03:52:31'),('FIN','FI','Finish',NULL,'2012-09-27 03:52:31'),('FRE','FR','French',NULL,'2012-09-27 03:52:31'),('GER','DE','German',NULL,'2012-09-27 03:52:31'),('GRE','EL','Greek',NULL,'2012-09-27 03:52:31'),('HEB','HE','Hebrew',NULL,'2012-09-27 03:52:31'),('HIN','HI','Hindi',NULL,'2012-09-27 03:52:31'),('HUN','HU','Hungarian',NULL,'2012-09-27 03:52:31'),('IND','ID','Indonesian',NULL,'2012-09-27 03:52:31'),('ITA','IT','Italian',NULL,'2012-09-27 03:52:31'),('JPN','JA','Japanese',NULL,'2012-09-27 03:52:31'),('KOR','KO','Korean',NULL,'2012-09-27 03:52:31'),('LAV','LV','Latvian',NULL,'2012-09-27 03:52:31'),('MAL','MS','Malay',NULL,'2012-09-27 03:52:31'),('NOR','NO','Norwegian',NULL,'2012-09-27 03:52:31'),('POL','PL','Polish',NULL,'2012-09-27 03:52:31'),('POR','PT','Portuguese',NULL,'2012-09-27 03:52:31'),('RUS','RU','Russian',NULL,'2012-09-27 03:52:31'),('SCC','HR','Croatian',NULL,'2012-09-27 03:52:31'),('SCR','SR','Serbian',NULL,'2012-09-27 03:52:31'),('SLO','SK','Slovak',NULL,'2012-09-27 03:52:31'),('SPA','ES','Spanish',NULL,'2012-09-27 03:52:31'),('SWA','SW','Swahili',NULL,'2012-09-27 03:52:31'),('SWE','SV','Swedish',NULL,'2012-09-27 03:52:31'),('THA','TH','Thai',NULL,'2012-09-27 03:52:31'),('TUR','TR','Turkish',NULL,'2012-09-27 03:52:31'),('UKR','UK','Ukranian',NULL,'2012-09-27 03:52:31'),('VIE','VI','Vietnamese',NULL,'2012-09-27 03:52:31'),('YID','YI','Yiddish',NULL,'2012-09-27 03:52:31');
/*!40000 ALTER TABLE `defLanguages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defOntologies`
--

DROP TABLE IF EXISTS `defOntologies`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defOntologies` (
  `ont_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'Ontology code, primary key',
  `ont_ShortName` varchar(63) NOT NULL COMMENT 'The commonly used acronym or short name of the ontology',
  `ont_FullName` varchar(255) NOT NULL COMMENT 'The commonly used full name of the ontology',
  `ont_Description` varchar(1000) default NULL COMMENT 'An optional descriptuion of the domain, origina and aims of the ontology',
  `ont_RefURI` varchar(250) default NULL COMMENT 'The URI to a definition of the ontology',
  `ont_Status` enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `ont_OriginatingDBID` mediumint(8) unsigned default NULL COMMENT 'Database where this ontology originated, 0 = locally',
  `ont_NameInOriginatingDB` varchar(63) default NULL COMMENT 'Name used in database where this ontology originated',
  `ont_IDInOriginatingDB` smallint(5) unsigned default NULL COMMENT 'ID used in database where this ontology originated',
  `ont_Order` tinyint(3) unsigned zerofill NOT NULL default '255' COMMENT 'Ordering value to define alternate display order in interface',
  `ont_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `ont_locallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (`ont_ID`),
  UNIQUE KEY `ont_ShortName` (`ont_ShortName`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='A table of references to different ontologies used by Heuris';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defOntologies`
--

LOCK TABLES `defOntologies` WRITE;
/*!40000 ALTER TABLE `defOntologies` DISABLE KEYS */;
INSERT INTO `defOntologies` VALUES (1,'local','Null ontology','An empty ontology which can be complemented','','open',2,'Null ontology',1,255,'2012-09-27 03:52:36',0),(2,'DC','Dublin Core','','http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=52142','open',2,'Dublin Core',2,255,'2012-09-27 03:52:36',0),(3,'CIDOC-CRM','CIDOC-CRM','','http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=34424','open',2,'CIDOC-CRM',3,255,'2012-09-27 03:52:36',0);
/*!40000 ALTER TABLE `defOntologies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defRecStructure`
--

DROP TABLE IF EXISTS `defRecStructure`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defRecStructure` (
  `rst_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'Primary key for the record structures table',
  `rst_RecTypeID` smallint(5) unsigned NOT NULL COMMENT 'The record type to which this detail is allocated, 0 = all rectypes',
  `rst_DetailTypeID` smallint(5) unsigned NOT NULL COMMENT 'Detail type for this field or, if MSB set, FieldSet code + 32767',
  `rst_DisplayName` varchar(255) NOT NULL default 'Please enter a prompt ...' COMMENT 'Display name for this dtl type in this rectype, autofill with dty_Name',
  `rst_DisplayHelpText` varchar(255) default NULL COMMENT 'The user help text to be displayed for this detail type for this record type',
  `rst_DisplayExtendedDescription` varchar(5000) default NULL COMMENT 'The rollover text to be displayed for this detail type for this record type',
  `rst_DisplayOrder` smallint(3) unsigned zerofill NOT NULL default '999' COMMENT 'A sort order for display of this detail type in the record edit form',
  `rst_DisplayWidth` tinyint(3) unsigned NOT NULL default '50' COMMENT 'The field width displayed for this detail type in this record type',
  `rst_DefaultValue` varchar(63) default NULL COMMENT 'The default value for this detail type for this record type',
  `rst_RecordMatchOrder` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Indicates order of significance in detecting duplicate records, 1 = highest',
  `rst_CalcFunctionID` tinyint(3) unsigned default NULL COMMENT 'FK to table of function specifications for calculating string values',
  `rst_RequirementType` enum('required','recommended','optional','forbidden') NOT NULL default 'optional',
  `rst_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in a specified record type',
  `rst_Status` enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `rst_MayModify` enum('locked','discouraged','open') NOT NULL default 'open' COMMENT 'Extent to which detail may be modified within this record structure',
  `rst_OriginatingDBID` mediumint(8) unsigned default NULL COMMENT 'Database where this record structure element originated, 0 = locally',
  `rst_IDInOriginatingDB` smallint(5) unsigned default NULL COMMENT 'ID used in database where this record structure element originated',
  `rst_MaxValues` tinyint(3) unsigned default NULL COMMENT 'Maximum number of values per record for this detail, NULL = unlimited, 0 = not allowed',
  `rst_MinValues` tinyint(3) unsigned NOT NULL default '0' COMMENT 'If required, minimum number of values per record for this detail',
  `rst_DisplayDetailTypeGroupID` tinyint(3) unsigned default NULL COMMENT 'If set, places detail in specified group instead of according to dty_DetailTypeGroup',
  `rst_FilteredJsonTermIDTree` varchar(500) default NULL COMMENT 'JSON encoded tree of allowed terms, subset of those defined in defDetailType',
  `rst_PtrFilteredIDs` varchar(250) default NULL COMMENT 'Allowed Rectypes (CSV) within list defined by defDetailType (for pointer details)',
  `rst_OrderForThumbnailGeneration` tinyint(3) unsigned default NULL COMMENT 'Priority order of fields to use in generating thumbnail, null = do not use',
  `rst_TermIDTreeNonSelectableIDs` varchar(255) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  `rst_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rst_LocallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (`rst_ID`),
  UNIQUE KEY `rst_composite` (`rst_RecTypeID`,`rst_DetailTypeID`),
  KEY `rst_DetailTypeID` (`rst_DetailTypeID`),
  KEY `fk_rst_DetailtypeID` (`rst_DetailTypeID`),
  CONSTRAINT `fk_rst_DetailtypeID` FOREIGN KEY (`rst_DetailTypeID`) REFERENCES `defDetailTypes` (`dty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rst_RecTypeID` FOREIGN KEY (`rst_RecTypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8 COMMENT='The record details (fields) required for each record type';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defRecStructure`
--

LOCK TABLES `defRecStructure` WRITE;
/*!40000 ALTER TABLE `defRecStructure` DISABLE KEYS */;
INSERT INTO `defRecStructure` VALUES (1,1,7,'Source record','Primary resource','',000,60,NULL,0,NULL,'required','viewable','reserved','open',2,1,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(2,1,6,'Relationship type','RelationType','',001,60,NULL,0,NULL,'required','viewable','reserved','open',2,2,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(3,1,5,'Target record','The target record in Heurist to which the relationship points','',002,60,NULL,1,NULL,'required','viewable','reserved','open',2,3,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(4,1,1,'Name of relationship','A succinct descriptive title for the relationship','',005,70,NULL,0,NULL,'optional','viewable','reserved','open',2,4,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(5,1,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',006,60,NULL,0,NULL,'optional','viewable','reserved','open',2,5,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(6,1,10,'Start date/time','Dates only required if relationship is to be timestamped ','',003,30,NULL,0,NULL,'recommended','viewable','reserved','open',2,6,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(7,1,11,'End date/time','Enter end date only if relationship expires','',004,30,NULL,0,NULL,'recommended','viewable','reserved','open',2,7,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(8,1,8,'Interpretation/commentary','A pointer to a commentary/interpreation record (for spatial, temporal or general information)','',007,60,NULL,0,NULL,'optional','viewable','approved','open',2,8,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(9,1,28,'Location (mappable)','A geospatial object providing a mappable location for the record','',009,60,NULL,0,NULL,'optional','viewable','open','open',2,9,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(10,1,38,'File resource','An uploaded file or a reference to a file through a URI ','',010,60,NULL,0,NULL,'optional','viewable','open','open',2,10,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(11,1,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',008,60,NULL,0,NULL,'optional','viewable','open','open',2,11,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(12,2,1,'Page Title','A succinct descriptive title for the page, even if the page itself does not have one','',000,60,NULL,1,NULL,'required','viewable','reserved','open',2,12,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(13,2,3,'Short Summary','Short summary eg. 100 words, for use in annotated listsx','',001,60,NULL,0,NULL,'recommended','viewable','reserved','open',2,13,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(14,2,39,'Thumbnail Image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',002,40,NULL,0,NULL,'recommended','viewable','reserved','open',2,14,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(15,2,9,'Date accessed','The date the web resource was accessed','',003,10,NULL,0,NULL,'optional','viewable','reserved','open',2,15,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(16,3,1,'Title','A succinct descriptive title for the note','',000,80,NULL,1,NULL,'required','viewable','reserved','open',2,16,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(17,3,3,'Note','Short summary eg. 100 words, for use in annotated lists','',001,60,NULL,0,NULL,'recommended','viewable','reserved','open',2,17,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(18,3,9,'Date','Specific date to which notes apply (if applicable)','',002,60,'today',0,NULL,'recommended','viewable','reserved','open',2,18,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(19,3,38,'Associated Files','Upload any associated files here','',004,40,NULL,0,NULL,'optional','viewable','reserved','open',2,19,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(20,3,39,'Thumbnail image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',003,40,NULL,0,NULL,'optional','viewable','reserved','open',2,20,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(21,4,1,'Name of organisation','Full title of the organisation - do not abbreviate','',000,60,NULL,1,NULL,'required','viewable','reserved','open',2,21,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(22,4,2,'Short name','Name','Please provide an extended description for display on rollover ...',005,60,NULL,2,NULL,'optional','viewable','open','open',2,22,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(23,4,22,'Organisation type','Organisation type','Please provide an extended description for display on rollover ...',006,60,NULL,0,NULL,'optional','viewable','open','open',2,23,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(24,4,3,'Description','Short description of organisation, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',001,60,NULL,0,NULL,'recommended','viewable','open','open',2,24,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(25,4,10,'Start date','Date organisation was founded','',003,30,NULL,0,NULL,'optional','viewable','open','open',2,25,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(26,4,11,'End date','Date the organisation closed','',004,30,NULL,0,NULL,'optional','viewable','open','open',2,26,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(27,4,27,'Location','Name of location where organisation is located','',007,60,NULL,0,NULL,'optional','viewable','open','open',2,27,NULL,0,NULL,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(28,4,26,'Country','Country','',008,60,NULL,0,NULL,'optional','viewable','open','open',2,28,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(29,4,28,'Location (mappable)','A geospatial object providing a mappable location for the record','',009,60,NULL,0,NULL,'optional','viewable','open','open',2,29,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(30,4,39,'Thumbnail/Logo','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',002,60,NULL,0,NULL,'recommended','viewable','open','open',2,30,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(31,4,38,'Related files','An uploaded file or a reference to a file through a URI ','',010,60,NULL,0,NULL,'optional','viewable','open','open',2,31,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(32,5,1,'Title of digital item','A succinct descriptive title of the media item','',000,40,NULL,1,NULL,'required','viewable','reserved','open',2,32,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(33,5,38,'Media file','Browse to media file and upload - recommended max size 5 MBytes','',005,60,NULL,2,NULL,'required','viewable','reserved','open',2,33,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(34,5,3,'Decription','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',003,60,NULL,0,NULL,'optional','viewable','reserved','open',2,34,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(35,5,15,'Creator(s)','The creator(s) - photographer, recordist etc. - of the media item','',001,30,NULL,0,NULL,'recommended','viewable','reserved','open',2,35,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(36,5,10,'Start date','Date of creation or publication','',002,30,NULL,0,NULL,'recommended','viewable','reserved','open',2,36,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(37,5,39,'Thumbnail image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',004,40,NULL,0,NULL,'recommended','viewable','reserved','open',2,37,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(38,5,28,'Location (mappable)','Geographic object(s) describing the location as lines, polygons or other shapes','',006,40,NULL,0,NULL,'optional','viewable','approved','open',2,38,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(39,5,35,'Copyright information','Copyright information','',007,60,NULL,0,NULL,'optional','viewable','reserved','open',2,39,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(40,6,1,'Name','The collection name.','',000,40,'Enter collection name',1,NULL,'required','viewable','open','open',2,40,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(41,6,13,'Record Pointer','Resource Identifier (unconstrained record pointer)','',001,60,NULL,0,NULL,'optional','viewable','reserved','open',2,41,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(42,6,12,'Query String','A string representing a valid Heurist query.','Please provide an extended description for display on rollover ...',002,40,NULL,0,NULL,'optional','viewable','open','open',2,42,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(43,6,40,'Graph filter string','A string representing a valid Heurist graph filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]} relfilter={\"level\":[\"relTermID1\",\"relTermID2\"]} ptrfilter={\"level\":[\"ptrDtyID1\",\"ptrDtyID2\"]} )','Please provide an extended description for display on rollover ...',003,40,NULL,0,NULL,'optional','viewable','open','open',2,43,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(44,6,48,'Default View Layout','A formatted string that can be interpretted by the Heurist Interface as specific layout. See Help for specifics.','',004,40,NULL,0,NULL,'optional','viewable','open','open',2,44,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(45,7,1,'Title of post','A title for the blog entry, date will be added automatically','',160,80,NULL,1,NULL,'required','viewable','reserved','open',2,45,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(46,7,39,'Thumbnail image','Thumbnail image, recommended 200 pixels maximum dimension','',223,30,NULL,0,NULL,'recommended','viewable','reserved','open',2,46,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(47,7,28,'Location (mappable)','Location or extent of this blog entry, can be rendered on the map','',230,30,NULL,0,NULL,'recommended','viewable','reserved','open',2,47,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(48,8,1,'Title of interpretation','A title for this interpretation, excluding author, date','',000,50,NULL,1,NULL,'required','viewable','reserved','open',2,48,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(49,8,10,'Validity date','Date of validity of this interpretation','',003,30,'today',0,NULL,'required','viewable','reserved','open',2,49,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(50,8,3,'Description','Short sumamry of the interpretation, use the WYSIWYG text in the TEXT or PERSONAL tabs for longer description','',004,50,NULL,0,NULL,'recommended','viewable','reserved','open',2,50,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(51,8,15,'Author(s)','Person(s) making this interpretation (original author, use transcriber if further interpreted)','',001,30,NULL,1,NULL,'recommended','viewable','reserved','open',2,51,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(52,8,14,'Transcriber(s)','Person(s) transcribing/further interpreting the original interpretation','',002,30,NULL,0,NULL,'optional','viewable','reserved','open',2,52,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(53,8,13,'References','Bibliographic or other records in Heurist which support or relate to this interpretation','',005,80,NULL,0,NULL,'recommended','viewable','reserved','open',2,53,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(54,9,23,'Email of sender','Email address from which this email forwarded or ccd','Address of owner (forwarder or sender)',000,50,NULL,2,NULL,'required','viewable','reserved','open',2,54,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(55,9,1,'Subject','Subject/title of the email','Subject line of email',001,30,'No subject line provided',1,NULL,'required','viewable','reserved','open',2,55,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(56,9,9,'Date originally sent','Date of the original email (date of sending of a ccd email or original date of a forwarded email)','Date of original sending',002,30,NULL,0,NULL,'required','viewable','reserved','open',2,56,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(57,9,24,'Email of originator','Address of original sender (if applicable, that is for forwarded emails)','Address of original sender (where different)',003,50,NULL,0,NULL,'optional','viewable','reserved','open',2,57,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(58,9,25,'Email of recipients','Email addresses of all recipients of the email','Emails of all recipients of the email ',004,30,NULL,0,NULL,'optional','viewable','reserved','open',2,58,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(59,9,3,'Email Body','Content of the body/text of the email','',005,60,NULL,0,NULL,'optional','viewable','reserved','open',2,59,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(60,9,38,'Attachments','Files attached to the email','Attached files',006,50,NULL,0,NULL,'optional','viewable','reserved','open',2,60,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(61,10,1,'Family name','Family name of person','',001,60,NULL,1,NULL,'recommended','viewable','reserved','open',2,61,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(62,10,18,'Given name(s)','The given name or names of the person','',002,20,NULL,1,NULL,'recommended','viewable','reserved','open',2,62,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(63,10,19,'Honorific','Title or grade - Prof, Dr, Sir etc., recommend omitting Mr, Mrs or Ms','',000,60,NULL,1,NULL,'optional','viewable','reserved','open',2,63,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(64,10,10,'Birth date','Date of Birth','',003,30,NULL,2,NULL,'optional','viewable','reserved','open',2,64,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(65,10,27,'Birth place','Name of location where person was born','',004,30,NULL,2,NULL,'optional','viewable','reserved','open',2,65,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(66,10,20,'Gender','Gender of the person','',005,30,NULL,0,NULL,'optional','viewable','reserved','open',2,66,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(67,10,3,'Short description','Short summary eg. 100 words, for use in annotated lists','',006,60,NULL,0,NULL,'optional','viewable','reserved','open',2,67,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(68,10,23,'Email address','Email address','',007,60,NULL,0,NULL,'optional','viewable','reserved','open',2,68,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(69,10,17,'Contact info or URL','The URL of a staff directory page such as a university phone book','',008,60,NULL,0,NULL,'optional','viewable','reserved','open',2,69,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(70,10,37,'Unique public identifier','eg. NLA party identifier (Australia) or other national person identifier code','',009,30,NULL,0,NULL,'optional','viewable','reserved','open',2,70,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(71,10,11,'Date of death','Date of death','Please provide an extended description for display on rollover ...',010,15,NULL,0,NULL,'optional','viewable','open','open',2,71,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(72,11,1,'Name of tiled image','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,30,NULL,1,NULL,'required','viewable','open','open',2,72,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(73,11,34,'Source URL','Base Service URL','',001,30,NULL,1,NULL,'required','viewable','open','open',2,73,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(74,11,31,'Tiling schema','Image tiling schema','',003,30,'548',2,NULL,'required','viewable','open','open',2,74,1,1,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(75,11,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',004,30,NULL,0,NULL,'optional','viewable','open','open',2,75,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(76,11,29,'Mime Type','Type of images used for tiles','',005,30,'537',0,NULL,'optional','viewable','open','open',2,76,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(77,11,32,'Minimum zoom level','Minimum zoom level','',006,30,'1',0,NULL,'optional','viewable','open','open',2,77,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(78,11,33,'Maximum zoom level','Maximum zoom level','',007,30,'19',0,NULL,'optional','viewable','open','open',2,78,1,0,0,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(79,11,30,'Tiled image type','The type (map or image) of the tiled image.','Please provide an extended description for display on rollover ...',002,60,'545',0,NULL,'required','viewable','reserved','open',2,79,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(80,12,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,NULL,1,NULL,'required','viewable','open','open',2,80,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(81,12,40,'Filter String','A string representing a valid Heurist filter string','Please provide an extended description for display on rollover ...',001,40,NULL,2,NULL,'optional','viewable','open','open',2,81,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(82,12,49,'Filter Format Version','Numeric string representing a version, typically a squence of numbers separated by full stop.','Please provide an extended description for display on rollover ...',002,40,NULL,0,NULL,'optional','viewable','open','open',2,82,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(83,13,1,'Title of document','Full title of the document - do not abbreviate','Please provide an extended description for display on rollover ...',000,40,NULL,1,NULL,'recommended','viewable','open','open',2,83,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(84,13,38,'File resource','An uploaded file or a reference to a file through a URI','Please provide an extended description for display on rollover ...',001,60,NULL,1,NULL,'required','viewable','open','open',2,84,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(85,13,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','Please provide an extended description for display on rollover ...',002,40,NULL,0,NULL,'recommended','viewable','open','open',2,85,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(86,13,15,'Author(s)','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',003,60,NULL,0,NULL,'optional','viewable','open','open',2,86,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(87,13,35,'Copyright information','Copyright information','Please provide an extended description for display on rollover ...',004,40,NULL,0,NULL,'optional','viewable','open','open',2,87,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(88,13,41,'File Type','Term identifying the file format','',005,60,'3923',0,NULL,'optional','viewable','open','open',2,88,1,0,1,'{\"524\":{\"526\":{},\"525\":{\"704\":{},\"705\":{}},\"700\":{\"701\":{},\"702\":{},\"703\":{}}}}',NULL,NULL,NULL,'2012-09-27 03:52:36',0),(89,14,38,'Template File','An uploaded file or a reference to a file through a URI. Multiple entries applied sequentially','Please provide an extended description for display on rollover ...',001,60,NULL,0,NULL,'required','viewable','reserved','open',2,89,NULL,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(90,14,41,'Transformation Type','Term identifying the transformation file format','',002,60,'3918',2,NULL,'required','viewable','open','open',2,90,1,1,1,'{\"699\":{},\"700\":{\"701\":{},\"702\":{},\"703\":{}}}',NULL,NULL,NULL,'2012-09-27 03:52:36',0),(91,14,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,NULL,1,NULL,'required','viewable','open','open',2,91,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(92,14,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','Please provide an extended description for display on rollover ...',004,40,'Enter a brief description of the transform.',0,NULL,'recommended','viewable','reserved','open',2,92,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(93,14,15,'Author','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',005,60,NULL,0,NULL,'optional','viewable','open','open',2,93,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(94,14,35,'Copyright information','Copyright information','Please provide an extended description for display on rollover ...',006,40,NULL,0,NULL,'optional','viewable','open','open',2,94,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(95,14,4,'Transform','Transformation text','Please provide an extended description for display on rollover ...',003,60,NULL,0,NULL,'optional','viewable','reserved','open',2,95,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(96,15,1,'Name of annotation','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,60,NULL,1,NULL,'recommended','viewable','approved','open',2,96,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(97,15,42,'Annotated Resource','Pointer to the resource being annotated','Please provide an extended description for display on rollover ...',001,60,NULL,0,NULL,'optional','viewable','open','open',2,97,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(98,15,43,'Annotation Range','An encoded string defining the area of the annotated document being annotated','Please provide an extended description for display on rollover ...',002,40,NULL,2,NULL,'optional','viewable','open','open',2,98,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(99,15,5,'Related record','The record which annotates the Annotated document.','',004,60,NULL,0,NULL,'optional','viewable','approved','open',2,99,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(100,15,12,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',003,60,NULL,0,NULL,'recommended','viewable','approved','open',2,100,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(101,15,44,'Start Word','Start Word','',005,60,NULL,0,NULL,'optional','viewable','approved','open',2,101,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(102,15,45,'End Word','End Word','',008,60,NULL,0,NULL,'optional','viewable','approved','open',2,102,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(103,15,46,'Start Element','Start Element','',007,60,NULL,0,NULL,'optional','viewable','approved','open',2,103,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(104,15,47,'End Element','End Element','',009,60,NULL,0,NULL,'optional','viewable','approved','open',2,104,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(105,15,52,'Annotation Type','Type of annotation - purpose of annotation - can be associated with tool','Please provide an extended description for display on rollover ...',001,0,'',0,NULL,'optional','viewable','open','open',2,105,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(106,15,3,'Text','Text of the annotation - comment or text to use in tool','',002,40,'',0,NULL,'optional','viewable','open','open',2,106,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(107,16,1,'Layout Name','The main name or title for the layout.','Please provide an extended description for display on rollover ...',000,40,NULL,1,NULL,'required','viewable','reserved','open',2,107,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(108,16,48,'Heurist Layout String','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Please provide an extended description for display on rollover ...',001,40,NULL,2,NULL,'required','viewable','reserved','open',2,108,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(109,16,49,'Version Number','Numeric string representing a version, typically a squence of numbers separated by full stop.','Please provide an extended description for display on rollover ...',002,40,'1.0',0,NULL,'required','viewable','reserved','open',2,109,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(110,17,1,'Name of Pipeline','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,NULL,1,NULL,'required','viewable','reserved','open',2,110,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(111,17,50,'Transformation Pointer','Pointer to a transformation or pipeline record','',001,60,NULL,0,NULL,'required','viewable','reserved','open',2,111,NULL,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(112,18,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,NULL,0,NULL,'recommended','viewable','open','open',2,112,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(113,18,51,'Property (N:V)','Attribute expressed as a name:value pair','',001,40,NULL,0,NULL,'recommended','viewable','open','open',2,113,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(114,19,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'optional','viewable','open','open',2,114,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',9),(115,19,39,'Icon','An image of approx. 200 pixels wide used on the button for the command','',001,0,'',0,NULL,'optional','viewable','open','open',2,115,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(116,19,50,'Transform Pointer','Pointer to a transform or pipeline transform record','',003,0,'',0,NULL,'optional','viewable','open','open',2,116,NULL,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(117,19,56,'Colour','Colour','',002,0,'',0,NULL,'optional','viewable','open','open',2,117,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(118,19,53,'Record Type','Record Type Concept-ID','Enter the origin dbID-recTypeID hyphenated id pair.',004,40,'',0,NULL,'optional','viewable','open','open',2,118,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',9),(119,19,54,'Detail Type','Detail Type Concept-ID','Enter the origin dbID-detailTypeID hyphenated id pair.',005,40,'',0,NULL,'optional','viewable','open','open',2,119,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',9),(120,19,55,'Application Command','Application Command','Enter the text for the command to be executed when the tool is invoked in the application',007,40,'',0,NULL,'optional','viewable','open','open',2,120,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(121,19,52,'Detail Value','Value to map to the detail type','Please provide an extended description for display on rollover ...',006,0,'709',0,NULL,'optional','viewable','open','open',2,121,1,0,1,'{\"707\":{\"708\":{\"709\":{}},\"710\":{\"711\":{},\"712\":{},\"713\":{}},\"714\":{\"715\":{}},\"716\":{\"717\":{},\"718\":{},\"719\":{}}}}',NULL,NULL,'[\"707\"]','2012-09-27 03:52:36',0),(122,21,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,NULL,1,NULL,'required','viewable','open','open',2,122,1,1,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(123,21,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','Please provide an extended description for display on rollover ...',002,60,NULL,0,NULL,'optional','viewable','open','open',2,123,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(124,21,3,'Short summary','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','Please provide an extended description for display on rollover ...',001,80,NULL,0,NULL,'optional','viewable','open','open',2,124,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(125,21,9,'Date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','Please provide an extended description for display on rollover ...',003,30,NULL,0,NULL,'optional','viewable','open','open',2,125,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(126,21,15,'Creator - author, artist etc.','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',004,60,NULL,0,NULL,'optional','viewable','open','open',2,126,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0),(127,21,35,'Copyright information','Copyright information','Please provide an extended description for display on rollover ...',005,40,NULL,0,NULL,'optional','viewable','open','open',2,127,1,0,1,NULL,NULL,NULL,NULL,'2012-09-27 03:52:36',0);
/*!40000 ALTER TABLE `defRecStructure` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecStructure_last_insert` AFTER INSERT ON `defRecStructure` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecStructure_last_update` AFTER UPDATE ON `defRecStructure` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecStructure_last_delete` AFTER DELETE ON `defRecStructure` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defRecTypeGroups`
--

DROP TABLE IF EXISTS `defRecTypeGroups`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defRecTypeGroups` (
  `rtg_ID` tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Record type group ID referenced in defRectypes',
  `rtg_Name` varchar(40) NOT NULL COMMENT 'Name for this group of record types, shown as heading in lists',
  `rtg_Domain` enum('functionalgroup','modelview') NOT NULL default 'functionalgroup' COMMENT 'Functional group (rectype has only one) or a Model/View group',
  `rtg_Order` tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists',
  `rtg_Description` varchar(250) default NULL COMMENT 'A description of the record type group and its purpose',
  `rtg_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`rtg_ID`),
  UNIQUE KEY `rtg_Name` (`rtg_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='Grouping mechanism for record types in pulldowns';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defRecTypeGroups`
--

LOCK TABLES `defRecTypeGroups` WRITE;
/*!40000 ALTER TABLE `defRecTypeGroups` DISABLE KEYS */;
INSERT INTO `defRecTypeGroups` VALUES (1,'Common record types','functionalgroup',001,'The commonest generic record types present in most databases','2012-09-27 03:52:35'),(3,'Core functions','functionalgroup',099,'Record types of general use, marked Reserved if required by the system','2012-09-27 03:52:35');
/*!40000 ALTER TABLE `defRecTypeGroups` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecTypeGroups_insert` AFTER INSERT ON `defRecTypeGroups` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecTypeGroups_update` AFTER UPDATE ON `defRecTypeGroups` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecTypeGroups_delete` AFTER DELETE ON `defRecTypeGroups` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defRecTypes`
--

DROP TABLE IF EXISTS `defRecTypes`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defRecTypes` (
  `rty_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'Record type code, widely used to reference record types, primary key',
  `rty_Name` varchar(63) NOT NULL COMMENT 'The name which is used to describe this record (object) type',
  `rty_OrderInGroup` tinyint(3) unsigned default '0' COMMENT 'Ordering within record type display groups for pulldowns',
  `rty_Description` varchar(5000) NOT NULL COMMENT 'Description of this record type',
  `rty_TitleMask` varchar(500) NOT NULL default '[title]' COMMENT 'Mask to build a composite title by combining field values',
  `rty_CanonicalTitleMask` varchar(500) default '160' COMMENT 'Version of the mask converted to detail codes for processing',
  `rty_Plural` varchar(63) default NULL COMMENT 'Plural form of the record type name, manually entered',
  `rty_Status` enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `rty_OriginatingDBID` mediumint(8) unsigned default NULL COMMENT 'Database where this record type originated, 0 = locally',
  `rty_NameInOriginatingDB` varchar(63) default NULL COMMENT 'Name used in database where this record type originated',
  `rty_IDInOriginatingDB` smallint(5) unsigned default NULL COMMENT 'ID in database where this record type originated',
  `rty_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows blanket restriction of visibility of a particular record type',
  `rty_ShowInLists` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if record type is to be shown in end-user interface, 1=yes',
  `rty_RecTypeGroupID` tinyint(3) unsigned NOT NULL default '1' COMMENT 'Record type group to which this record type belongs',
  `rty_RecTypeModelIDs` varchar(63) default NULL COMMENT 'The model group(s) to which this rectype belongs, comma sep. list',
  `rty_FlagAsFieldset` tinyint(1) unsigned NOT NULL default '0' COMMENT '0 = full record type, 1 = Fieldset = set of fields to include in other rectypes',
  `rty_ReferenceURL` varchar(250) default NULL COMMENT 'A reference URL describing/defining the record type',
  `rty_AlternativeRecEditor` varchar(63) default NULL COMMENT 'Name or URL of alternative record editor function to be used for this rectype',
  `rty_Type` enum('normal','relationship','dummy') NOT NULL default 'normal' COMMENT 'Use to flag special record types to trigger special functions',
  `rty_ShowURLOnEditForm` tinyint(1) NOT NULL default '1' COMMENT 'Determines whether special URL field is shown at the top of the edit form',
  `rty_ShowDescriptionOnEditForm` tinyint(1) NOT NULL default '1' COMMENT 'Determines whether the record type description field is shown at the top of the edit form',
  `rty_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rty_LocallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (`rty_ID`),
  UNIQUE KEY `rty_Name` (`rty_Name`),
  KEY `rty_RecTypeGroupID` (`rty_RecTypeGroupID`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8 COMMENT='Defines record types, which corresponds with a set of detail';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defRecTypes`
--

LOCK TABLES `defRecTypes` WRITE;
/*!40000 ALTER TABLE `defRecTypes` DISABLE KEYS */;
INSERT INTO `defRecTypes` VALUES (1,'Record relationship',30,'A relationship of a defined type between two records in the database, may include a time range over which the relationship is valid, and notes about the relationship','[Name of relationship] ([Source record.RecTitle] [Relationship Type] [Target record.RecTitle])','[1] ([7.RecTitle] [6] [5.RecTitle])','Record relationships','reserved',2,'Record relationship',1,'viewable',1,3,NULL,1,NULL,NULL,'relationship',1,1,'2012-09-27 03:52:36',0),(2,'Web site / page',1,'A web site URL, typically a specific page (often the home page)','[Page title]','[1]','Web site / pages','reserved',2,'Web site / page',2,'viewable',1,1,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(3,'Notes',2,'A simple record type for taking notes','[Title]','[1]','Notes','reserved',2,'Notes',3,'viewable',1,1,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(4,'Organisation',0,'Organisations (companies, universities, granting bodies etc.)','[Name of Organisation]','[1]','Organisations','reserved',2,'Organisation',4,'viewable',1,1,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(5,'Digital media item',0,'Digital media files - typically images, sounds and videos - uploaded to the database (may also be externally referenced)','[Title of digital item]','[1]','Digital media items','reserved',2,'Digital media file',5,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(6,'Record Collection',0,'A record which represents a dynamic and/or static group of records.','[Name]','[1]','Record Collections','reserved',2,'Record Collection',6,'viewable',1,3,NULL,0,NULL,NULL,'normal',0,1,'2012-09-27 03:52:36',0),(7,'Blog post',0,'Blog post used to construct blogs, content in the WYSIWYG text field','[Title of post]','[1]','Blog posts','reserved',2,'Blog post',7,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(8,'Interpretation',0,'Metadata about a date, spatial extent or other interpretation of information','[Title of interpretation] - by [Author(s).recTitle] [Date] transcribed by [Transcriber(s).recTitle]','[1] - by [15.RecTitle] [1] transcribed by [14.RecTitle]','Interpretations','reserved',2,'Interpretation',8,'viewable',1,1,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(9,'Email',0,'An email including content and metadata, normally derived from an email forwarded or ccd to the database\'s linked IMAP server from a user\'s account','[Subject] from: [Email of sender] on: [Date originally sent]','[1] from: [24] on: [9]','Emails','reserved',2,'Email',9,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(10,'Person',33,'The canonical record for a person, may be expanded with additional information as required.','[Family name], [Given names] ([Birth Date])','[1] [18] ([10])','Persons','reserved',2,'Person',10,'viewable',1,1,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(11,'Tiled Image',0,'A tiled image set for display as a zoomable layer on e.g. a Google map','[Name of tiled image]','[1]','Tiled Images','reserved',2,'Map image layer',11,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(12,'Heurist Filter',0,'A record which represents a filtering of query results and their related records.','[Name]','[1]','Heurist Filters','reserved',2,'Filter',12,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(13,'XML Document',0,'Any XML format document.','[Title of document]','[1]','XML Documents','reserved',2,'XML Document',13,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(14,'Transform',0,'Formatted text used by a processor to transform input information','[Name]','[1]','Transforms','reserved',2,'Transformation',14,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(15,'Annotation',0,'A reference to part of a document','[Name of annotation] ([Annotated Resource.RecTitle]) ref:([Annotation Range])','[1] ([42.1]) ref:([43])','Annotations','reserved',2,'Annotation',15,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(16,'Heurist Layout',0,'Record which contains information which captures or resets the layout of the Heurist interface.','[Layout Name]','[1]','Heurist Layouts','reserved',2,'Heurist Layout',16,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(17,'Pipeline Transform',0,'A record which is an ordered list of transformation records.','[Name of Pipeline]','[1]','Pipeline Transforms','reserved',2,'Pipeline Transform',17,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(18,'Object',0,'Object is an entity that can have any number of properties and a name.','[Name]','[1]','Objects','open',2,'Object',18,'viewable',1,1,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0),(19,'Tool',0,'This is a configuration type record type that describes a particular annotation tool with its icon, name, term and colour.','[Name]','[1]','Tools','open',2,'Tool',19,'viewable',1,3,NULL,0,'','','normal',1,1,'2012-09-27 03:52:36',0),(21,'Aggregation',0,'A record which provides the root of an aggregation of records (typically records which share some common trait which is not part of their attributes, such as \'nice photos\' or bibliographic entries required for a paper)','[Name]','[1]','Aggregations','reserved',2,'Aggregation',21,'viewable',1,3,NULL,0,NULL,NULL,'normal',1,1,'2012-09-27 03:52:36',0);
/*!40000 ALTER TABLE `defRecTypes` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecTypes_last_insert` AFTER INSERT ON `defRecTypes` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecTypes_last_update` AFTER UPDATE ON `defRecTypes` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecTypes_delete` AFTER DELETE ON `defRecTypes` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defRelationshipConstraints`
--

DROP TABLE IF EXISTS `defRelationshipConstraints`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defRelationshipConstraints` (
  `rcs_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'Record-detailtype constraint table primary key',
  `rcs_SourceRectypeID` smallint(5) unsigned default NULL COMMENT 'Source record type for this constraint, Null = all types',
  `rcs_TargetRectypeID` smallint(5) unsigned default NULL COMMENT 'Target record type pointed to by relationship record, Null = all types',
  `rcs_Description` varchar(1000) default 'Please describe ...',
  `rcs_RelationshipsLimit` tinyint(3) unsigned default NULL COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet',
  `rcs_Status` enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `rcs_OriginatingDBID` mediumint(8) unsigned NOT NULL default '0' COMMENT 'Database where this constraint originated, 0 or local db code = locally',
  `rcs_IDInOriginatingDB` smallint(5) unsigned default '0' COMMENT 'Code used in database where this constraint originated',
  `rcs_TermID` int(10) unsigned default NULL COMMENT 'The ID of a term to be constrained, applies to descendants unless they have more specific',
  `rcs_TermLimit` tinyint(2) unsigned default NULL COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used',
  `rcs_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rcs_LocallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (`rcs_ID`),
  UNIQUE KEY `rcs_CompositeKey` (`rcs_SourceRectypeID`,`rcs_TargetRectypeID`,`rcs_TermID`),
  KEY `rcs_TermID` (`rcs_TermID`),
  KEY `rcs_TargetRectypeID` (`rcs_TargetRectypeID`),
  KEY `fk_rcs_SourceRecTypeID` (`rcs_SourceRectypeID`),
  KEY `fk_rcs_TargetRecTypeID` (`rcs_TargetRectypeID`),
  CONSTRAINT `fk_rcs_SourceRecTypeID` FOREIGN KEY (`rcs_SourceRectypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcs_TargetRecTypeID` FOREIGN KEY (`rcs_TargetRectypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcs_TermID` FOREIGN KEY (`rcs_TermID`) REFERENCES `defTerms` (`trm_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Constrain target-rectype/vocabularies/values for a pointer d';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defRelationshipConstraints`
--

LOCK TABLES `defRelationshipConstraints` WRITE;
/*!40000 ALTER TABLE `defRelationshipConstraints` DISABLE KEYS */;
/*!40000 ALTER TABLE `defRelationshipConstraints` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRelationshipConstraints_last_insert` AFTER INSERT ON `defRelationshipConstraints` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRelationshipConstraints_last_update` AFTER UPDATE ON `defRelationshipConstraints` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRelationshipConstraints_last_delete` AFTER DELETE ON `defRelationshipConstraints` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defTerms`
--

DROP TABLE IF EXISTS `defTerms`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defTerms` (
  `trm_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key, the term code used in the detail record',
  `trm_Label` varchar(500) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank',
  `trm_InverseTermId` int(10) unsigned default NULL COMMENT 'ID for the inverse value (relationships), null if no inverse',
  `trm_Description` varchar(1000) default NULL COMMENT 'A description/gloss on the meaning of the term',
  `trm_Status` enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `trm_OriginatingDBID` mediumint(8) unsigned default NULL COMMENT 'Database where this detail type originated, 0 = locally',
  `trm_NameInOriginatingDB` varchar(63) default NULL COMMENT 'Name (label) for this term in originating database',
  `trm_IDInOriginatingDB` mediumint(8) unsigned default NULL COMMENT 'ID used in database where this  term originated',
  `trm_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Set to 1 if term added by an import, otherwise 0',
  `trm_IsLocalExtension` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag that this value not in the externally referenced vocabulary',
  `trm_Domain` enum('enum','relation') NOT NULL default 'enum' COMMENT 'Define the usage of the term',
  `trm_OntID` smallint(5) unsigned NOT NULL default '0' COMMENT 'Ontology from which this vocabulary originated, 0 = locally defined ontology',
  `trm_ChildCount` tinyint(3) NOT NULL default '0' COMMENT 'Stores the count of children, updated whenever children are added/removed',
  `trm_ParentTermID` int(10) unsigned default NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  `trm_Depth` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Depth of term in the term tree, should always be 1+parent depth',
  `trm_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `trm_LocallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  `trm_Code` varchar(100) default NULL COMMENT 'Optional code eg. alphanumeric code which may be required for import or export',
  PRIMARY KEY  (`trm_ID`),
  KEY `trm_ParentTermIDKey` (`trm_ParentTermID`),
  KEY `trm_InverseTermIDKey` (`trm_InverseTermId`),
  CONSTRAINT `fk_trm_InverseTermId` FOREIGN KEY (`trm_InverseTermId`) REFERENCES `defTerms` (`trm_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trm_ParentTermID` FOREIGN KEY (`trm_ParentTermID`) REFERENCES `defTerms` (`trm_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3244 DEFAULT CHARSET=utf8 COMMENT='Terms by detail type and the vocabulary they belong to';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defTerms`
--

LOCK TABLES `defTerms` WRITE;
/*!40000 ALTER TABLE `defTerms` DISABLE KEYS */;
INSERT INTO `defTerms` VALUES (11,'University',0,'','open',2,'University',11,0,0,'enum',0,2,497,1,'2012-09-27 03:52:36',0,NULL),(12,'Funding body',0,'','open',2,'Funding body',12,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(13,'Network',0,'','open',2,'Network',13,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(14,'Data service',0,'','open',2,'Data service',14,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(15,'Department',0,'','open',2,'Department',15,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(16,'Laboratory',0,'','open',2,'Laboratory',16,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(17,'Centre',0,'','open',2,'Centre',17,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(18,'Foundation',0,'','open',2,'Foundation',18,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(19,'Ecole Superieure',0,'','open',2,'Ecole Superieure',19,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(20,'Institute',0,'','open',2,'Institute',20,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(21,'Museum',0,'','open',2,'Museum',21,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(22,'Archaeology',0,'','open',2,'Archaeology',22,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(23,'Anthropology',0,'','open',2,'Anthropology',23,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(24,'History',0,'','open',2,'History',24,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(25,'Theatre Studies',0,'','open',2,'Theatre Studies',25,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(26,'Classics',0,'','open',2,'Classics',26,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(27,'Philosophy',0,'','open',2,'Philosophy',27,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(28,'Literature',0,'','open',2,'Literature',28,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(29,'Linguistics',0,'','open',2,'Linguistics',29,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(30,'Languages',0,'','open',2,'Languages',30,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(31,'Computer Science',0,'','open',2,'Computer Science',31,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(32,'Information Communication Technology',0,'','open',2,'Information Communication Technology',32,0,0,'enum',0,0,498,1,'2012-09-27 03:52:36',0,NULL),(33,'Government - Research',0,'','open',2,'Government - Research',33,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(34,'Government - Infrastructure',0,'','open',2,'Government - Infrastructure',34,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(35,'Government - Special Inititative',0,'','open',2,'Government - Special Inititative',35,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(36,'Foundation - General',0,'','open',2,'Foundation - General',36,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(37,'Foundation - Specific program',0,'','open',2,'Foundation - Specific program',37,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(38,'Military',0,'','open',2,'Military',38,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(39,'Cultural',0,'','open',2,'Cultural',39,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(40,'Economic',0,'','open',2,'Economic',40,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(41,'Military',0,'','open',2,'Military',41,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(42,'Politcal',0,'','open',2,'Politcal',42,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(43,'Religious',0,'','open',2,'Religious',43,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(44,'Social',0,'','open',2,'Social',44,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(45,'Biological',0,'','open',2,'Biological',45,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(46,'Geological',0,'','open',2,'Geological',46,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(47,'Astronomical',0,'','open',2,'Astronomical',47,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(48,'Technological',0,'','open',2,'Technological',48,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(49,'Biographical',0,'','open',2,'Biographical',49,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(50,'Journey',0,'','open',2,'Journey',50,0,0,'enum',0,0,500,1,'2012-09-27 03:52:36',0,NULL),(51,'Lecture',0,'','open',2,'Lecture',51,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(52,'Seminar',0,'','open',2,'Seminar',52,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(53,'Workshop',0,'','open',2,'Workshop',53,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(54,'Masterclass',0,'','open',2,'Masterclass',54,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(55,'Public lecture',0,'','open',2,'Public lecture',55,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(56,'Recital',0,'','open',2,'Recital',56,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(57,'Book launch',0,'','open',2,'Book launch',57,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(58,'Exhibition opening',0,'','open',2,'Exhibition opening',58,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(59,'Exhibition',0,'','open',2,'Exhibition',59,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(60,'Round table',0,'','open',2,'Round table',60,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(61,'Small project < 20 hours',0,'','open',2,'Small project < 20 hours',61,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(62,'Semester, 1 person',0,'','open',2,'Semester, 1 person',62,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(63,'Semester, group',0,'','open',2,'Semester, group',63,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(64,'Honours, 1 semester',0,'','open',2,'Honours, 1 semester',64,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(65,'Masters, 2 semesters',0,'','open',2,'Masters, 2 semesters',65,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(66,'MPhil or equiv.',0,'','open',2,'MPhil or equiv.',66,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(67,'PhD',0,'','open',2,'PhD',67,0,0,'enum',0,0,502,1,'2012-09-27 03:52:36',0,NULL),(68,'BA Hons.',0,'','open',2,'BA Hons.',68,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(69,'MA',0,'','open',2,'MA',69,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(70,'MSc',0,'','open',2,'MSc',70,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(71,'PhD',0,'','open',2,'PhD',71,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(72,'Doctorat 3eme cycle',0,'','open',2,'Doctorat 3eme cycle',72,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(73,'Doctorat d\'etat',0,'','open',2,'Doctorat d\'etat',73,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(74,'DES',0,'','open',2,'DES',74,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(75,'MPhil',0,'','open',2,'MPhil',75,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(76,'Other',0,'','open',2,'Other',76,0,0,'enum',0,0,503,1,'2012-09-27 03:52:36',0,NULL),(77,'Teaching staff',0,'','open',2,'Teaching staff',77,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(78,'Research staff',0,'','open',2,'Research staff',78,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(79,'Research assistant',0,'','open',2,'Research assistant',79,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(80,'Administrative staff',0,'','open',2,'Administrative staff',80,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(81,'Professional staff',0,'','open',2,'Professional staff',81,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(82,'Postgraduate student',0,'','open',2,'Postgraduate student',82,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(83,'Honours student',0,'','open',2,'Honours student',83,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(84,'Research associate/adjunct',0,'','open',2,'Research associate/adjunct',84,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(85,'Chief Investigator',0,'','open',2,'Chief Investigator',85,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(86,'Partner Investigator',0,'','open',2,'Partner Investigator',86,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(87,'Client',0,'','open',2,'Client',87,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(88,'Very Small <= $5K',0,'','open',2,'Very Small <= $5K',88,0,0,'enum',0,0,504,1,'2012-09-27 03:52:36',0,NULL),(89,'Small <= $25K',0,'','open',2,'Small <= $25K',89,0,0,'enum',0,0,504,1,'2012-09-27 03:52:36',0,NULL),(90,'Medium $25K - $100K',0,'','open',2,'Medium $25K - $100K',90,0,0,'enum',0,0,504,1,'2012-09-27 03:52:36',0,NULL),(91,'Large $100K - $500K',0,'','open',2,'Large $100K - $500K',91,0,0,'enum',0,0,504,1,'2012-09-27 03:52:36',0,NULL),(92,'Very Large > $500K',0,'','open',2,'Very Large > $500K',92,0,0,'enum',0,0,504,1,'2012-09-27 03:52:36',0,NULL),(93,'Mr',0,'','open',2,'Mr',93,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(94,'Mrs',0,'','open',2,'Mrs',94,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(95,'Ms',0,'','open',2,'Ms',95,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(96,'Dr',0,'','open',2,'Dr',96,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(97,'A/Prof.',0,'','open',2,'A/Prof.',97,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(98,'Prof.',0,'','open',2,'Prof.',98,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(99,'Chair',0,'','open',2,'Chair',99,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(100,'Honours coordinator',0,'','open',2,'Honours coordinator',100,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(101,'Postgraduate coordinator',0,'','open',2,'Postgraduate coordinator',101,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(102,'System administrator',0,'','open',2,'System administrator',102,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(103,'Historical figure',0,'','open',2,'Historical figure',103,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(104,'BCE',0,'','open',2,'BCE',104,0,0,'enum',0,0,508,1,'2012-09-27 03:52:36',0,NULL),(105,'CE',0,'','open',2,'CE',105,0,0,'enum',0,0,508,1,'2012-09-27 03:52:36',0,NULL),(106,'BP',0,'','open',2,'BP',106,0,0,'enum',0,0,508,1,'2012-09-27 03:52:36',0,NULL),(107,'Unknown',0,'','open',2,'Unknown',107,0,0,'enum',0,0,508,1,'2012-09-27 03:52:36',0,NULL),(108,'Australia',0,'','open',2,'Australia',108,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(109,'China',0,'','open',2,'China',109,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(110,'France',0,'','open',2,'France',110,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(111,'Greece',0,'','open',2,'Greece',111,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(112,'Italy',0,'','open',2,'Italy',112,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(113,'Spain',0,'','open',2,'Spain',113,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(114,'Cambodia',0,'','open',2,'Cambodia',114,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(115,'Vietnam',0,'','open',2,'Vietnam',115,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(116,'Thailand',0,'','open',2,'Thailand',116,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(117,'New Zealand',0,'','open',2,'New Zealand',117,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(118,'UK',0,'','open',2,'UK',118,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(119,'USA',0,'','open',2,'USA',119,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(120,'Conference',0,'','open',2,'Conference',120,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(121,'Colloquium',0,'','open',2,'Colloquium',121,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(122,'Meeting',0,'','open',2,'Meeting',122,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(123,'Committee meeting',0,'','open',2,'Committee meeting',123,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(124,'WGS84 Decimal Lat-Long',0,'','open',2,'WGS84 Decimal Lat-Long',124,0,0,'enum',0,0,510,1,'2012-09-27 03:52:36',0,NULL),(125,'UTM (WGS84)',0,'','open',2,'UTM (WGS84)',125,0,0,'enum',0,0,510,1,'2012-09-27 03:52:36',0,NULL),(126,'Planar (local)',0,'','open',2,'Planar (local)',126,0,0,'enum',0,0,510,1,'2012-09-27 03:52:36',0,NULL),(127,'Unknown',0,'','open',2,'Unknown',127,0,0,'enum',0,0,510,1,'2012-09-27 03:52:36',0,NULL),(128,'Government department',0,'','open',2,'Government department',128,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(129,'Federal government',0,'','open',2,'Federal government',129,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(130,'Company',0,'','open',2,'Company',130,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(131,'NGO',0,'','open',2,'NGO',131,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(132,'Charity',0,'','open',2,'Charity',132,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(133,'Other - ask admin to define',0,'','open',2,'Other - ask admin to define',133,0,0,'enum',0,0,497,1,'2012-09-27 03:52:36',0,NULL),(134,'Symposium',0,'','open',2,'Symposium',134,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(135,'Australia',0,'','open',2,'Australia',135,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(136,'S, E & SE Asia',0,'','open',2,'S, E & SE Asia',136,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(137,'C & W Asia',0,'','open',2,'C & W Asia',137,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(138,'Europe/Mediterranean',0,'','open',2,'Europe/Mediterranean',138,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(139,'North America',0,'','open',2,'North America',139,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(140,'S & C America',0,'','open',2,'S & C America',140,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(141,'Worldwide',0,'','open',2,'Worldwide',141,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(142,'Non-regional',0,'','open',2,'Non-regional',142,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(143,'Opening or launch',0,'','open',2,'Opening or launch',143,0,0,'enum',0,0,501,1,'2012-09-27 03:52:36',0,NULL),(144,'Visitor',0,'','open',2,'Visitor',144,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(145,'Tutor',0,'','open',2,'Tutor',145,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(146,'Volunteer',0,'','open',2,'Volunteer',146,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(147,'Multi-regional',0,'','open',2,'Multi-regional',147,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(148,'Vice Chairperson',0,'','open',2,'Vice Chairperson',148,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(149,'Treasurer',0,'','open',2,'Treasurer',149,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(150,'Secretary',0,'','open',2,'Secretary',150,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(151,'Public Officer',0,'','open',2,'Public Officer',151,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(152,'Member',0,'','open',2,'Member',152,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(153,'Manager',0,'','open',2,'Manager',153,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(154,'Senior Linguist ',0,'','open',2,'Senior Linguist ',154,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(155,'CDEP language Worker',0,'','open',2,'CDEP language Worker',155,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(156,'Trainee',0,'','open',2,'Trainee',156,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(157,'Facilitator',0,'','open',2,'Facilitator',157,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(158,'Language Worker',0,'','open',2,'Language Worker',158,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(159,'Anthropologist',0,'','open',2,'Anthropologist',159,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(160,'Cyprus',0,'','open',2,'Cyprus',160,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(161,'Jordan',0,'','open',2,'Jordan',161,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(162,'UAE',0,'','open',2,'UAE',162,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(163,'Uzbekistan',0,'','open',2,'Uzbekistan',163,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(164,'Kyrgistan',0,'','open',2,'Kyrgistan',164,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(165,'Dubai',0,'','open',2,'Dubai',165,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(166,'Israel',0,'','open',2,'Israel',166,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(167,'Iran',0,'','open',2,'Iran',167,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(168,'Iraq',0,'','open',2,'Iraq',168,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(169,'Syria',0,'','open',2,'Syria',169,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(170,'Turkey',0,'','open',2,'Turkey',170,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(171,'Myanmar',0,'','open',2,'Myanmar',171,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(172,'India',0,'','open',2,'India',172,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(173,'Singapore',0,'','open',2,'Singapore',173,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(174,'Alumnus',0,'','open',2,'Alumnus',174,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(175,'Intern',0,'','open',2,'Intern',175,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(176,'Course Coordinator',0,'','open',2,'Course Coordinator',176,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(177,'Norway',0,'','open',2,'Norway',177,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(178,'Netherlands',0,'','open',2,'Netherlands',178,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(179,'South Africa',0,'','open',2,'South Africa',179,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(180,'Canada',0,'','open',2,'Canada',180,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(181,'Ireland',0,'','open',2,'Ireland',181,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(182,'Germany',0,'','open',2,'Germany',182,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(183,'Hungary',0,'','open',2,'Hungary',183,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(184,'Belgium',0,'','open',2,'Belgium',184,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(185,'Brazil',0,'','open',2,'Brazil',185,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(186,'Sweden',0,'','open',2,'Sweden',186,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(187,'Linguist',0,'','open',2,'Linguist',187,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(188,'Language Specialist',0,'','open',2,'Language Specialist',188,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(189,'Consultant',0,'','open',2,'Consultant',189,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(190,'Link Up Case Worker ',0,'','open',2,'Link Up Case Worker ',190,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(191,'Media Manager',0,'','open',2,'Media Manager',191,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(192,'Graphic Designer',0,'','open',2,'Graphic Designer',192,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(193,'Cultural Awareness Trainer',0,'','open',2,'Cultural Awareness Trainer',193,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(194,'Special Projects',0,'','open',2,'Special Projects',194,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(195,'Denmark',0,'','open',2,'Denmark',195,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(196,'Buddhism',0,'','open',2,'Buddhism',196,0,0,'enum',0,0,511,1,'2012-09-27 03:52:36',0,NULL),(197,'Hinduism',0,'','open',2,'Hinduism',197,0,0,'enum',0,0,511,1,'2012-09-27 03:52:36',0,NULL),(198,'Taiwan',0,'','open',2,'Taiwan',198,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(199,'0-1 m',0,'','open',2,'0-1 m',199,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(200,'1-10 m',0,'','open',2,'1-10 m',200,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(201,'10-100m',0,'','open',2,'10-100m',201,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(202,'100-1000m',0,'','open',2,'100-1000m',202,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(203,'1000-10000 m',0,'','open',2,'1000-10000 m',203,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(204,'Afghanistan',0,'','open',2,'Afghanistan',204,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(205,'Aland Islands',0,'','open',2,'Aland Islands',205,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(206,'Albania',0,'','open',2,'Albania',206,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(207,'Algeria',0,'','open',2,'Algeria',207,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(208,'American Samoa',0,'','open',2,'American Samoa',208,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(209,'Andorra',0,'','open',2,'Andorra',209,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(210,'Angola',0,'','open',2,'Angola',210,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(211,'Anguilla',0,'','open',2,'Anguilla',211,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(212,'Antarctica',0,'','open',2,'Antarctica',212,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(213,'Antigua and Barbuda',0,'','open',2,'Antigua and Barbuda',213,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(214,'Argentina',0,'','open',2,'Argentina',214,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(215,'Armenia',0,'','open',2,'Armenia',215,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(216,'Aruba',0,'','open',2,'Aruba',216,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(217,'Austria',0,'','open',2,'Austria',217,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(218,'Azerbaijan',0,'','open',2,'Azerbaijan',218,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(219,'Bahamas',0,'','open',2,'Bahamas',219,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(220,'Bahrain',0,'','open',2,'Bahrain',220,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(221,'Bangladesh',0,'','open',2,'Bangladesh',221,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(222,'Barbados',0,'','open',2,'Barbados',222,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(223,'Belarus',0,'','open',2,'Belarus',223,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(224,'Belize',0,'','open',2,'Belize',224,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(225,'Benin',0,'','open',2,'Benin',225,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(226,'Bermuda',0,'','open',2,'Bermuda',226,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(227,'Bhutan',0,'','open',2,'Bhutan',227,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(228,'Bolivia',0,'','open',2,'Bolivia',228,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(229,'Bosnia and Herzegovina',0,'','open',2,'Bosnia and Herzegovina',229,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(230,'Botswana',0,'','open',2,'Botswana',230,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(231,'Bouvet Island',0,'','open',2,'Bouvet Island',231,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(232,'British Indian Ocean Territory',0,'','open',2,'British Indian Ocean Territory',232,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(233,'Brunei Darussalam',0,'','open',2,'Brunei Darussalam',233,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(234,'Bulgaria',0,'','open',2,'Bulgaria',234,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(235,'Burkina Faso',0,'','open',2,'Burkina Faso',235,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(236,'Burundi',0,'','open',2,'Burundi',236,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(237,'Cameroon',0,'','open',2,'Cameroon',237,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(238,'Cape Verde',0,'','open',2,'Cape Verde',238,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(239,'Cayman Islands',0,'','open',2,'Cayman Islands',239,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(240,'Central African Republic',0,'','open',2,'Central African Republic',240,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(241,'Chad',0,'','open',2,'Chad',241,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(242,'Chile',0,'','open',2,'Chile',242,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(243,'Christmas Island',0,'','open',2,'Christmas Island',243,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(244,'Cocos (Keeling) Islands',0,'','open',2,'Cocos (Keeling) Islands',244,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(245,'Colombia',0,'','open',2,'Colombia',245,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(246,'Comoros',0,'','open',2,'Comoros',246,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(247,'Congo',0,'','open',2,'Congo',247,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(248,'Congo, Democratic Republic of the',0,'','open',2,'Congo, Democratic Republic of the',248,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(249,'Cook Islands',0,'','open',2,'Cook Islands',249,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(250,'Costa Rica',0,'','open',2,'Costa Rica',250,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(251,'C?te d\'Ivoire',0,'','open',2,'C?te d\'Ivoire',251,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(252,'Croatia',0,'','open',2,'Croatia',252,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(253,'Cuba',0,'','open',2,'Cuba',253,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(254,'Czech Republic',0,'','open',2,'Czech Republic',254,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(255,'Djibouti',0,'','open',2,'Djibouti',255,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(256,'Dominica',0,'','open',2,'Dominica',256,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(257,'Dominican Republic',0,'','open',2,'Dominican Republic',257,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(258,'Ecuador',0,'','open',2,'Ecuador',258,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(259,'Egypt',0,'','open',2,'Egypt',259,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(260,'El Salvador',0,'','open',2,'El Salvador',260,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(261,'Equatorial Guinea',0,'','open',2,'Equatorial Guinea',261,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(262,'Eritrea',0,'','open',2,'Eritrea',262,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(263,'Estonia',0,'','open',2,'Estonia',263,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(264,'Ethiopia',0,'','open',2,'Ethiopia',264,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(265,'Falkland Islands (malvinas)',0,'','open',2,'Falkland Islands (malvinas)',265,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(266,'Faroe Islands',0,'','open',2,'Faroe Islands',266,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(267,'Fiji',0,'','open',2,'Fiji',267,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(268,'Finland',0,'','open',2,'Finland',268,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(269,'French Guiana',0,'','open',2,'French Guiana',269,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(270,'French Polynesia',0,'','open',2,'French Polynesia',270,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(271,'French Southern Territories',0,'','open',2,'French Southern Territories',271,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(272,'Gabon',0,'','open',2,'Gabon',272,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(273,'Gambia',0,'','open',2,'Gambia',273,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(274,'Georgia',0,'','open',2,'Georgia',274,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(275,'Ghana',0,'','open',2,'Ghana',275,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(276,'Gibraltar',0,'','open',2,'Gibraltar',276,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(277,'Greenland',0,'','open',2,'Greenland',277,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(278,'Grenada',0,'','open',2,'Grenada',278,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(279,'Guadeloupe',0,'','open',2,'Guadeloupe',279,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(280,'Guam',0,'','open',2,'Guam',280,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(281,'Guatemala',0,'','open',2,'Guatemala',281,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(282,'Guernsey',0,'','open',2,'Guernsey',282,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(283,'Guinea',0,'','open',2,'Guinea',283,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(284,'Guinea-Bissau',0,'','open',2,'Guinea-Bissau',284,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(285,'Guyana',0,'','open',2,'Guyana',285,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(286,'Haiti',0,'','open',2,'Haiti',286,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(287,'Heard Island and Mcdonald Islands',0,'','open',2,'Heard Island and Mcdonald Islands',287,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(288,'Holy See (Vatican City State)',0,'','open',2,'Holy See (Vatican City State)',288,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(289,'Honduras',0,'','open',2,'Honduras',289,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(290,'Hong Kong',0,'','open',2,'Hong Kong',290,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(291,'Iceland',0,'','open',2,'Iceland',291,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(292,'Indonesia',0,'','open',2,'Indonesia',292,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(293,'Isle of Man',0,'','open',2,'Isle of Man',293,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(294,'Jamaica',0,'','open',2,'Jamaica',294,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(295,'Japan',0,'','open',2,'Japan',295,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(296,'Jersey',0,'','open',2,'Jersey',296,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(297,'Kazakhstan',0,'','open',2,'Kazakhstan',297,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(298,'Kenya',0,'','open',2,'Kenya',298,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(299,'Kiribati',0,'','open',2,'Kiribati',299,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(300,'Korea, Democratic People\'s Republic of',0,'','open',2,'Korea, Democratic People\'s Republic of',300,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(301,'Korea, Republic of',0,'','open',2,'Korea, Republic of',301,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(302,'Kuwait',0,'','open',2,'Kuwait',302,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(303,'Lao People\'s Democratic Republic',0,'','open',2,'Lao People\'s Democratic Republic',303,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(304,'Latvia',0,'','open',2,'Latvia',304,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(305,'Lebanon',0,'','open',2,'Lebanon',305,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(306,'Lesotho',0,'','open',2,'Lesotho',306,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(307,'Liberia',0,'','open',2,'Liberia',307,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(308,'Libyan Arab Jamahiriya',0,'','open',2,'Libyan Arab Jamahiriya',308,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(309,'Liechtenstein',0,'','open',2,'Liechtenstein',309,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(310,'Lithuania',0,'','open',2,'Lithuania',310,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(311,'Luxembourg',0,'','open',2,'Luxembourg',311,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(312,'Macao',0,'','open',2,'Macao',312,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(313,'Macedonia, Former Yugoslav Republic of',0,'','open',2,'Macedonia, Former Yugoslav Republic of',313,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(314,'Madagascar',0,'','open',2,'Madagascar',314,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(315,'Malawi',0,'','open',2,'Malawi',315,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(316,'Malaysia',0,'','open',2,'Malaysia',316,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(317,'Maldives',0,'','open',2,'Maldives',317,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(318,'Mali',0,'','open',2,'Mali',318,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(319,'Malta',0,'','open',2,'Malta',319,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(320,'Marshall Islands',0,'','open',2,'Marshall Islands',320,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(321,'Martinique',0,'','open',2,'Martinique',321,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(322,'Mauritania',0,'','open',2,'Mauritania',322,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(323,'Mauritius',0,'','open',2,'Mauritius',323,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(324,'Mayotte',0,'','open',2,'Mayotte',324,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(325,'Mexico',0,'','open',2,'Mexico',325,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(326,'Micronesia, Federated States of',0,'','open',2,'Micronesia, Federated States of',326,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(327,'Moldova, Republic of',0,'','open',2,'Moldova, Republic of',327,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(328,'Monaco',0,'','open',2,'Monaco',328,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(329,'Mongolia',0,'','open',2,'Mongolia',329,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(330,'Montenegro',0,'','open',2,'Montenegro',330,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(331,'Montserrat',0,'','open',2,'Montserrat',331,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(332,'Morocco',0,'','open',2,'Morocco',332,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(333,'Mozambique',0,'','open',2,'Mozambique',333,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(334,'Namibia',0,'','open',2,'Namibia',334,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(335,'Nauru',0,'','open',2,'Nauru',335,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(336,'Nepal',0,'','open',2,'Nepal',336,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(337,'Netherlands Antilles',0,'','open',2,'Netherlands Antilles',337,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(338,'New Caledonia',0,'','open',2,'New Caledonia',338,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(339,'Nicaragua',0,'','open',2,'Nicaragua',339,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(340,'Niger',0,'','open',2,'Niger',340,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(341,'Nigeria',0,'','open',2,'Nigeria',341,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(342,'Niue',0,'','open',2,'Niue',342,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(343,'Norfolk Island',0,'','open',2,'Norfolk Island',343,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(344,'Northern Mariana Islands',0,'','open',2,'Northern Mariana Islands',344,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(345,'Oman',0,'','open',2,'Oman',345,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(346,'Pakistan',0,'','open',2,'Pakistan',346,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(347,'Palau',0,'','open',2,'Palau',347,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(348,'Palestinian Territory, Occupied',0,'','open',2,'Palestinian Territory, Occupied',348,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(349,'Panama',0,'','open',2,'Panama',349,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(350,'Papua New Guinea',0,'','open',2,'Papua New Guinea',350,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(351,'Paraguay',0,'','open',2,'Paraguay',351,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(352,'Peru',0,'','open',2,'Peru',352,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(353,'Philippines',0,'','open',2,'Philippines',353,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(354,'Pitcairn',0,'','open',2,'Pitcairn',354,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(355,'Poland',0,'','open',2,'Poland',355,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(356,'Portugal',0,'','open',2,'Portugal',356,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(357,'Puerto Rico',0,'','open',2,'Puerto Rico',357,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(358,'Qatar',0,'','open',2,'Qatar',358,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(359,'Reunion',0,'','open',2,'Reunion',359,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(360,'Romania',0,'','open',2,'Romania',360,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(361,'Russian Federation',0,'','open',2,'Russian Federation',361,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(362,'Rwanda',0,'','open',2,'Rwanda',362,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(363,'Saint Barth?lemy',0,'','open',2,'Saint Barth?lemy',363,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(364,'Saint Helena',0,'','open',2,'Saint Helena',364,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(365,'Saint Kitts and Nevis',0,'','open',2,'Saint Kitts and Nevis',365,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(366,'Saint Lucia',0,'','open',2,'Saint Lucia',366,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(367,'Saint Martin',0,'','open',2,'Saint Martin',367,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(368,'Saint Pierre and Miquelon',0,'','open',2,'Saint Pierre and Miquelon',368,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(369,'Saint Vincent and the Grenadines',0,'','open',2,'Saint Vincent and the Grenadines',369,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(370,'Samoa',0,'','open',2,'Samoa',370,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(371,'San Marino',0,'','open',2,'San Marino',371,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(372,'Sao Tome and Principe',0,'','open',2,'Sao Tome and Principe',372,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(373,'Saudi Arabia',0,'','open',2,'Saudi Arabia',373,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(374,'Senegal',0,'','open',2,'Senegal',374,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(375,'Serbia',0,'','open',2,'Serbia',375,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(376,'Seychelles',0,'','open',2,'Seychelles',376,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(377,'Sierra Leone',0,'','open',2,'Sierra Leone',377,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(378,'Slovakia',0,'','open',2,'Slovakia',378,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(379,'Slovenia',0,'','open',2,'Slovenia',379,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(380,'Solomon Islands',0,'','open',2,'Solomon Islands',380,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(381,'Somalia',0,'','open',2,'Somalia',381,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(382,'South Georgia and the South Sandwich Islands',0,'','open',2,'South Georgia and the South Sandwich Islands',382,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(383,'Sri Lanka',0,'','open',2,'Sri Lanka',383,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(384,'Sudan',0,'','open',2,'Sudan',384,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(385,'Suriname',0,'','open',2,'Suriname',385,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(386,'Svalbard and Jan Mayen',0,'','open',2,'Svalbard and Jan Mayen',386,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(387,'Swaziland',0,'','open',2,'Swaziland',387,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(388,'Switzerland',0,'','open',2,'Switzerland',388,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(389,'Tajikistan',0,'','open',2,'Tajikistan',389,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(390,'Tanzania, United Republic of',0,'','open',2,'Tanzania, United Republic of',390,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(391,'Timor-Leste',0,'','open',2,'Timor-Leste',391,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(392,'Togo',0,'','open',2,'Togo',392,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(393,'Tokelau',0,'','open',2,'Tokelau',393,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(394,'Tonga',0,'','open',2,'Tonga',394,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(395,'Trinidad and Tobago',0,'','open',2,'Trinidad and Tobago',395,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(396,'Tunisia',0,'','open',2,'Tunisia',396,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(397,'Turkmenistan',0,'','open',2,'Turkmenistan',397,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(398,'Turks and Caicos Islands',0,'','open',2,'Turks and Caicos Islands',398,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(399,'Tuvalu',0,'','open',2,'Tuvalu',399,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(400,'Uganda',0,'','open',2,'Uganda',400,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(401,'Ukraine',0,'','open',2,'Ukraine',401,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(402,'United States Minor Outlying Islands',0,'','open',2,'United States Minor Outlying Islands',402,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(403,'Uruguay',0,'','open',2,'Uruguay',403,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(404,'Vanuatu',0,'','open',2,'Vanuatu',404,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(405,'Venezuela',0,'','open',2,'Venezuela',405,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(406,'Virgin Islands, British',0,'','open',2,'Virgin Islands, British',406,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(407,'Virgin Islands, U.S.',0,'','open',2,'Virgin Islands, U.S.',407,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(408,'Wallis and Futuna',0,'','open',2,'Wallis and Futuna',408,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(409,'Western Sahara',0,'','open',2,'Western Sahara',409,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(410,'Yemen',0,'','open',2,'Yemen',410,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(411,'Zambia',0,'','open',2,'Zambia',411,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(412,'Zimbabwe',0,'','open',2,'Zimbabwe',412,0,0,'enum',0,0,509,1,'2012-09-27 03:52:36',0,NULL),(413,'Postdoctoral Fellow',0,'','open',2,'Postdoctoral Fellow',413,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(414,'Male',0,'','open',2,'Male',414,0,0,'enum',0,0,513,1,'2012-09-27 03:52:36',0,NULL),(415,'Female',0,'','open',2,'Female',415,0,0,'enum',0,0,513,1,'2012-09-27 03:52:36',0,NULL),(416,'Recorder',0,'','open',2,'Recorder',416,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(417,'Singer',0,'','open',2,'Singer',417,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(418,'Musician',0,'','open',2,'Musician',418,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(419,'Witness',0,'','open',2,'Witness',419,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(420,'Speaker',0,'','open',2,'Speaker',420,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(421,'BBSRC',0,'','open',2,'BBSRC',421,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(422,'HEIF 1',0,'','open',2,'HEIF 1',422,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(423,'HEIF 2',0,'','open',2,'HEIF 2',423,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(424,'HEIF 3',0,'','open',2,'HEIF 3',424,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(425,'MOMENTA',0,'','open',2,'MOMENTA',425,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(426,'Olympics',0,'','open',2,'Olympics',426,0,0,'enum',0,0,499,1,'2012-09-27 03:52:36',0,NULL),(427,'Student',0,'','open',2,'Student',427,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(428,'Collector',0,'','open',2,'Collector',428,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(429,'Commentator',0,'','open',2,'Commentator',429,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(430,'Composer',0,'','open',2,'Composer',430,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(431,'Dancer',0,'','open',2,'Dancer',431,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(432,'Director',0,'','open',2,'Director',432,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(433,'Instrumentalist',0,'','open',2,'Instrumentalist',433,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(434,'Interviewer',0,'','open',2,'Interviewer',434,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(435,'Photographer',0,'','open',2,'Photographer',435,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(436,'Recording engineer',0,'','open',2,'Recording engineer',436,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(437,'Research team head',0,'','open',2,'Research team head',437,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(438,'Research team member',0,'','open',2,'Research team member',438,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(439,'Storyteller',0,'','open',2,'Storyteller',439,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(440,'Transcriber',0,'','open',2,'Transcriber',440,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(441,'Videographer',0,'','open',2,'Videographer',441,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(442,'Conceptor',0,'','open',2,'Conceptor',442,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(443,'Data inputter',0,'','open',2,'Data inputter',443,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(444,'Interviewee',0,'','open',2,'Interviewee',444,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(445,'Metadata contact',0,'','open',2,'Metadata contact',445,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(446,'Narrator',0,'','open',2,'Narrator',446,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(447,'Translator',0,'','open',2,'Translator',447,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(448,'Emeritus Professor/Adjunct',0,'','open',2,'Emeritus Professor/Adjunct',448,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(449,'Middle East',0,'','open',2,'Middle East',449,0,0,'enum',0,0,506,1,'2012-09-27 03:52:36',0,NULL),(450,'0.01-0.05km',0,'','open',2,'0.01-0.05km',450,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(451,'0.05-0.1km',0,'','open',2,'0.05-0.1km',451,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(452,'0.1-0.5km',0,'','open',2,'0.1-0.5km',452,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(453,'0.5-1km',0,'','open',2,'0.5-1km',453,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(454,'1-5km',0,'','open',2,'1-5km',454,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(455,'5-10km',0,'','open',2,'5-10km',455,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(456,'10-50km',0,'','open',2,'10-50km',456,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(457,'50-100km',0,'','open',2,'50-100km',457,0,0,'enum',0,0,512,1,'2012-09-27 03:52:36',0,NULL),(458,'Adjunct lecturer',0,'','open',2,'Adjunct lecturer',458,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(459,'APAI Research Student',0,'','open',2,'APAI Research Student',459,0,0,'enum',0,0,505,1,'2012-09-27 03:52:36',0,NULL),(460,'English (EN, ENG)',0,'','reserved',2,'English (EN, ENG)',460,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(461,'Arabic (AR, ARA)',0,'','reserved',2,'Arabic (AR, ARA)',461,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(462,'Yiddish (YI, YID)',0,'','reserved',2,'Yiddish (YI, YID)',462,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(463,'Hebrew (HE, HEB)',0,'','reserved',2,'Hebrew (HE, HEB)',463,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(464,'French (FR, FRE)',0,'','reserved',2,'French (FR, FRE)',464,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(465,'Italian (IT, ITA)',0,'','reserved',2,'Italian (IT, ITA)',465,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(466,'Spanish (ES, SPA)',0,'','reserved',2,'Spanish (ES, SPA)',466,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(467,'Dutch (NL, DUTENG)',0,'','reserved',2,'Dutch (NL, DUTENG)',467,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(468,'Danish (DA, DAN)',0,'','reserved',2,'Danish (DA, DAN)',468,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(469,'Norwegian (NO, NOR)',0,'','reserved',2,'Norwegian (NO, NOR)',469,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(470,'Portuguese} (PT, POR)',0,'','reserved',2,'Portuguese} (PT, POR)',470,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(471,'German (DE, GER)',0,'','reserved',2,'German (DE, GER)',471,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(472,'Greek (EL, GRE)',0,'','reserved',2,'Greek (EL, GRE)',472,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(473,'Turkish (TR, TUR)',0,'','reserved',2,'Turkish (TR, TUR)',473,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(474,'Russian (RU, RUS)',0,'','reserved',2,'Russian (RU, RUS)',474,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(475,'Ukrainian (UK, UKR)',0,'','reserved',2,'Ukrainian (UK, UKR)',475,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(476,'Swedish (SV, SWE)',0,'','reserved',2,'Swedish (SV, SWE)',476,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(477,'Finish (FI, FIN)',0,'','reserved',2,'Finish (FI, FIN)',477,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(478,'Latvian (LV, LAV)',0,'','reserved',2,'Latvian (LV, LAV)',478,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(479,'Estonian (ET, EST)',0,'','reserved',2,'Estonian (ET, EST)',479,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(480,'Hungarian (HU, HUN)',0,'','reserved',2,'Hungarian (HU, HUN)',480,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(481,'Czech (CS, CZE)',0,'','reserved',2,'Czech (CS, CZE)',481,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(482,'Polish (PL, ENG)',0,'','reserved',2,'Polish (PL, ENG)',482,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(483,'Slovak (EN, POL)',0,'','reserved',2,'Slovak (EN, POL)',483,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(484,'Serbian (EN, SCR)',0,'','reserved',2,'Serbian (EN, SCR)',484,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(485,'Croatian (HR, SCC)',0,'','reserved',2,'Croatian (HR, SCC)',485,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(486,'Swahili (SW, SWA)',0,'','reserved',2,'Swahili (SW, SWA)',486,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(487,'Chinese (ZH, CHI)',0,'','reserved',2,'Chinese (ZH, CHI)',487,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(488,'Indonesian (ID, IND)',0,'','reserved',2,'Indonesian (ID, IND)',488,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(489,'Hindi (HI, HIN)',0,'','reserved',2,'Hindi (HI, HIN)',489,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(490,'Japanese (JA, JPN)',0,'','reserved',2,'Japanese (JA, JPN)',490,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(491,'Malay (MS, MAL)',0,'','reserved',2,'Malay (MS, MAL)',491,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(492,'Korean (KO, KOR)',0,'','reserved',2,'Korean (KO, KOR)',492,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(493,'Vietnamese (VI, VIE)',0,'','reserved',2,'Vietnamese (VI, VIE)',493,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(494,'Thai (TH, THA)',0,'','reserved',2,'Thai (TH, THA)',494,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(495,'Khmer (KM, CAM)',0,'','reserved',2,'Khmer (KM, CAM)',495,0,0,'enum',0,0,496,1,'2012-09-27 03:52:36',0,NULL),(496,'Language',0,'Common languages vocabulary','reserved',2,'Language',496,0,0,'enum',0,36,0,0,'2012-09-27 03:52:36',0,NULL),(497,'Organisation type',0,'','open',2,'Organisation type',497,0,0,'enum',0,17,0,0,'2012-09-27 03:52:36',0,NULL),(498,'Discipline',0,'','open',2,'Discipline',498,0,0,'enum',0,11,0,0,'2012-09-27 03:52:36',0,NULL),(499,'Funding Type',0,'','open',2,'Funding Type',499,0,0,'enum',0,12,0,0,'2012-09-27 03:52:36',0,NULL),(500,'EventDomain',0,'','open',2,'EventDomain',500,0,0,'enum',0,12,0,0,'2012-09-27 03:52:36',0,NULL),(501,'Event type',0,'','open',2,'Event type',501,0,0,'enum',0,16,0,0,'2012-09-27 03:52:36',0,NULL),(502,'Project scope',0,'','open',2,'Project scope',502,0,0,'enum',0,7,0,0,'2012-09-27 03:52:36',0,NULL),(503,'Thesis type',0,'','open',2,'Thesis type',503,0,0,'enum',0,9,0,0,'2012-09-27 03:52:36',0,NULL),(504,'Funding bracket',0,'','open',2,'Funding bracket',504,0,0,'enum',0,5,0,0,'2012-09-27 03:52:36',0,NULL),(505,'Person Role',0,'','open',2,'Person Role',505,0,0,'enum',0,72,0,0,'2012-09-27 03:52:36',0,NULL),(506,'Geographic region',0,'','open',2,'Geographic region',506,0,0,'enum',0,10,0,0,'2012-09-27 03:52:36',0,NULL),(507,'Honorific',0,'','open',2,'Honorific',507,0,0,'enum',0,7,0,0,'2012-09-27 03:52:36',0,NULL),(508,'Date system',0,'','open',2,'Date system',508,0,0,'enum',0,4,0,0,'2012-09-27 03:52:36',0,NULL),(509,'Country',0,'','open',2,'Country',509,0,0,'enum',0,127,0,0,'2012-09-27 03:52:36',0,NULL),(510,'Coordinate System',0,'','open',2,'Coordinate System',510,0,0,'enum',0,4,0,0,'2012-09-27 03:52:36',0,NULL),(511,'Religion',0,'','open',2,'Religion',511,0,0,'enum',0,4,0,0,'2012-09-27 03:52:36',0,NULL),(512,'Spatial Accuracy',0,'','open',2,'Spatial Accuracy',512,0,0,'enum',0,13,0,0,'2012-09-27 03:52:36',0,NULL),(513,'Gender',0,'','open',2,'Gender',513,0,0,'enum',0,4,0,0,'2012-09-27 03:52:36',0,NULL),(514,'Christianity',0,'','open',2,'Christianity',514,0,0,'enum',0,2,511,1,'2012-09-27 03:52:36',0,NULL),(515,'Islam',0,'','open',2,'Islam',515,0,0,'enum',0,0,511,1,'2012-09-27 03:52:36',0,NULL),(516,'Catholic',0,'','open',2,'Catholic',516,0,0,'enum',0,0,514,2,'2012-09-27 03:52:36',0,NULL),(517,'Protestant',0,'','open',2,'Protestant',517,0,0,'enum',0,0,514,2,'2012-09-27 03:52:36',0,NULL),(518,'Private',0,'','open',2,'Private',518,0,0,'enum',0,0,11,2,'2012-09-27 03:52:36',0,NULL),(519,'Public',0,'','open',2,'Public',519,0,0,'enum',0,0,11,2,'2012-09-27 03:52:36',0,NULL),(520,'File schema',0,'A file format/schema such as TEI, DocBook, PDF, Bitmap eg for use in annotation','open',2,'File schema',520,0,0,'enum',0,2,0,0,'2012-09-27 03:52:36',0,NULL),(521,'Text',0,'','open',2,'Text',521,0,0,'enum',0,3,520,1,'2012-09-27 03:52:36',0,NULL),(522,'Bitmap',0,'','open',2,'Bitmap',522,0,0,'enum',0,0,520,1,'2012-09-27 03:52:36',0,NULL),(523,'PDF',0,'','open',2,'PDF',523,0,0,'enum',0,0,521,2,'2012-09-27 03:52:36',0,NULL),(524,'XML',0,'','open',2,'XML',524,0,0,'enum',0,3,521,2,'2012-09-27 03:52:36',0,NULL),(525,'TEI',0,'','open',2,'TEI',525,0,0,'enum',0,2,524,3,'2012-09-27 03:52:36',0,NULL),(526,'DocBook',0,'','open',2,'DocBook',526,0,0,'enum',0,0,524,3,'2012-09-27 03:52:36',0,NULL),(527,'Other',0,'','open',2,'Other',527,0,0,'enum',0,0,513,1,'2012-09-27 03:52:36',0,NULL),(528,'Unknown',0,'','open',2,'Unknown',528,0,0,'enum',0,0,513,1,'2012-09-27 03:52:36',0,NULL),(529,'Lord',0,'','open',2,'Lord',529,0,0,'enum',0,0,507,1,'2012-09-27 03:52:36',0,NULL),(530,'Flag',0,'A limited option pseudo-Boolean vocabulary ','open',2,'Flag',530,1,0,'enum',0,2,0,0,'2012-09-27 03:52:36',0,NULL),(531,'No',0,'Negative response','open',2,'No',531,1,0,'enum',0,0,530,1,'2012-09-27 03:52:36',0,NULL),(532,'Yes',0,'Affirmative response','open',2,'Yes',532,1,0,'enum',0,0,530,1,'2012-09-27 03:52:36',0,NULL),(533,'Mime Type',0,'','open',2,'Mime Type',533,1,0,'enum',0,9,0,0,'2012-09-27 03:52:36',0,NULL),(534,'image/bmp',0,'','open',2,'image/bmp',534,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(535,'image/ermapper',0,'','open',2,'image/ermapper',535,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(536,'image/gif',0,'','open',2,'image/gif',536,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(537,'image/jpeg',0,'','open',2,'image/jpeg',537,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(538,'image/jpeg2000',0,'','open',2,'image/jpeg2000',538,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(539,'image/mrsid',0,'','open',2,'image/mrsid',539,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(540,'image/png',0,'','open',2,'image/png',540,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(541,'image/tiff',0,'','open',2,'image/tiff',541,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(542,'image/zoomify',0,'','open',2,'image/zoomify',542,1,0,'enum',0,0,533,1,'2012-09-27 03:52:36',0,NULL),(543,'Tiled image type',0,'','open',2,'Map image layer type',543,1,0,'enum',0,2,0,0,'2012-09-27 03:52:36',1,NULL),(544,'image',0,'','open',2,'image',544,1,0,'enum',0,0,543,1,'2012-09-27 03:52:36',0,NULL),(545,'map',0,'','open',2,'map',545,1,0,'enum',0,0,543,1,'2012-09-27 03:52:36',0,NULL),(546,'Image tiling schema',0,'','open',2,'Map image layer tiling schema',546,1,0,'enum',0,4,0,0,'2012-09-27 03:52:36',1,NULL),(547,'gmapimage',0,'','open',2,'gmapimage',547,1,0,'enum',0,0,546,1,'2012-09-27 03:52:36',0,NULL),(548,'maptiler',0,'','open',2,'maptiler',548,1,0,'enum',0,0,546,1,'2012-09-27 03:52:36',0,NULL),(549,'virtual earth',0,'','open',2,'virtual earth',549,1,0,'enum',0,0,546,1,'2012-09-27 03:52:36',0,NULL),(550,'zoomify',0,'','open',2,'zoomify',550,1,0,'enum',0,0,546,1,'2012-09-27 03:52:36',0,NULL),(551,'Colour',0,'','open',2,'Colour',551,0,0,'enum',0,127,0,0,'2012-09-27 03:52:36',0,NULL),(552,'AliceBlue',0,'','open',2,'AliceBlue',552,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(553,'AntiqueWhite',0,'','open',2,'AntiqueWhite',553,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(554,'Aqua',0,'','open',2,'Aqua',554,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(555,'Aquamarine',0,'','open',2,'Aquamarine',555,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(556,'Azure',0,'','open',2,'Azure',556,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(557,'Beige',0,'','open',2,'Beige',557,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(558,'Bisque',0,'','open',2,'Bisque',558,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(559,'Black',0,'','open',2,'Black',559,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(560,'BlanchedAlmond',0,'','open',2,'BlanchedAlmond',560,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(561,'Blue',0,'','open',2,'Blue',561,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(562,'BlueViolet',0,'','open',2,'BlueViolet',562,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(563,'Brown',0,'','open',2,'Brown',563,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(564,'BurlyWood',0,'','open',2,'BurlyWood',564,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(565,'CadetBlue',0,'','open',2,'CadetBlue',565,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(566,'Chartreuse',0,'','open',2,'Chartreuse',566,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(567,'Chocolate',0,'','open',2,'Chocolate',567,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(568,'Coral',0,'','open',2,'Coral',568,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(569,'CornflowerBlue',0,'','open',2,'CornflowerBlue',569,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(570,'Cornsilk',0,'','open',2,'Cornsilk',570,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(571,'Crimson',0,'','open',2,'Crimson',571,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(572,'Cyan',0,'','open',2,'Cyan',572,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(573,'DarkBlue',0,'','open',2,'DarkBlue',573,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(574,'DarkCyan',0,'','open',2,'DarkCyan',574,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(575,'DarkGoldenRod',0,'','open',2,'DarkGoldenRod',575,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(576,'DarkGray',0,'','open',2,'DarkGray',576,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(577,'DarkGrey',0,'','open',2,'DarkGrey',577,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(578,'DarkGreen',0,'','open',2,'DarkGreen',578,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(579,'DarkKhaki',0,'','open',2,'DarkKhaki',579,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(580,'DarkMagenta',0,'','open',2,'DarkMagenta',580,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(581,'DarkOliveGreen',0,'','open',2,'DarkOliveGreen',581,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(582,'Darkorange',0,'','open',2,'Darkorange',582,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(583,'DarkOrchid',0,'','open',2,'DarkOrchid',583,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(584,'DarkRed',0,'','open',2,'DarkRed',584,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(585,'DarkSalmon',0,'','open',2,'DarkSalmon',585,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(586,'DarkSeaGreen',0,'','open',2,'DarkSeaGreen',586,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(587,'DarkSlateBlue',0,'','open',2,'DarkSlateBlue',587,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(588,'DarkSlateGray',0,'','open',2,'DarkSlateGray',588,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(589,'DarkSlateGrey',0,'','open',2,'DarkSlateGrey',589,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(590,'DarkTurquoise',0,'','open',2,'DarkTurquoise',590,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(591,'DarkViolet',0,'','open',2,'DarkViolet',591,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(592,'DeepPink',0,'','open',2,'DeepPink',592,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(593,'DeepSkyBlue',0,'','open',2,'DeepSkyBlue',593,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(594,'DimGray',0,'','open',2,'DimGray',594,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(595,'DimGrey',0,'','open',2,'DimGrey',595,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(596,'DodgerBlue',0,'','open',2,'DodgerBlue',596,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(597,'FireBrick',0,'','open',2,'FireBrick',597,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(598,'FloralWhite',0,'','open',2,'FloralWhite',598,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(599,'ForestGreen',0,'','open',2,'ForestGreen',599,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(600,'Fuchsia',0,'','open',2,'Fuchsia',600,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(601,'Gainsboro',0,'','open',2,'Gainsboro',601,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(602,'GhostWhite',0,'','open',2,'GhostWhite',602,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(603,'Gold',0,'','open',2,'Gold',603,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(604,'GoldenRod',0,'','open',2,'GoldenRod',604,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(605,'Gray',0,'','open',2,'Gray',605,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(606,'Grey',0,'','open',2,'Grey',606,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(607,'Green',0,'','open',2,'Green',607,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(608,'GreenYellow',0,'','open',2,'GreenYellow',608,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(609,'HoneyDew',0,'','open',2,'HoneyDew',609,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(610,'HotPink',0,'','open',2,'HotPink',610,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(611,'IndianRed',0,'','open',2,'IndianRed',611,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(612,'Indigo',0,'','open',2,'Indigo',612,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(613,'Ivory',0,'','open',2,'Ivory',613,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(614,'Khaki',0,'','open',2,'Khaki',614,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(615,'Lavender',0,'','open',2,'Lavender',615,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(616,'LavenderBlush',0,'','open',2,'LavenderBlush',616,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(617,'LawnGreen',0,'','open',2,'LawnGreen',617,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(618,'LemonChiffon',0,'','open',2,'LemonChiffon',618,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(619,'LightBlue',0,'','open',2,'LightBlue',619,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(620,'LightCoral',0,'','open',2,'LightCoral',620,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(621,'LightCyan',0,'','open',2,'LightCyan',621,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(622,'LightGoldenRodYellow',0,'','open',2,'LightGoldenRodYellow',622,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(623,'LightGray',0,'','open',2,'LightGray',623,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(624,'LightGrey',0,'','open',2,'LightGrey',624,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(625,'LightGreen',0,'','open',2,'LightGreen',625,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(626,'LightPink',0,'','open',2,'LightPink',626,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(627,'LightSalmon',0,'','open',2,'LightSalmon',627,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(628,'LightSeaGreen',0,'','open',2,'LightSeaGreen',628,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(629,'LightSkyBlue',0,'','open',2,'LightSkyBlue',629,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(630,'LightSlateGray',0,'','open',2,'LightSlateGray',630,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(631,'LightSlateGrey',0,'','open',2,'LightSlateGrey',631,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(632,'LightSteelBlue',0,'','open',2,'LightSteelBlue',632,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(633,'LightYellow',0,'','open',2,'LightYellow',633,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(634,'Lime',0,'','open',2,'Lime',634,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(635,'LimeGreen',0,'','open',2,'LimeGreen',635,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(636,'Linen',0,'','open',2,'Linen',636,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(637,'Magenta',0,'','open',2,'Magenta',637,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(638,'Maroon',0,'','open',2,'Maroon',638,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(639,'MediumAquaMarine',0,'','open',2,'MediumAquaMarine',639,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(640,'MediumBlue',0,'','open',2,'MediumBlue',640,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(641,'MediumOrchid',0,'','open',2,'MediumOrchid',641,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(642,'MediumPurple',0,'','open',2,'MediumPurple',642,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(643,'MediumSeaGreen',0,'','open',2,'MediumSeaGreen',643,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(644,'MediumSlateBlue',0,'','open',2,'MediumSlateBlue',644,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(645,'MediumSpringGreen',0,'','open',2,'MediumSpringGreen',645,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(646,'MediumTurquoise',0,'','open',2,'MediumTurquoise',646,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(647,'MediumVioletRed',0,'','open',2,'MediumVioletRed',647,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(648,'MidnightBlue',0,'','open',2,'MidnightBlue',648,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(649,'MintCream',0,'','open',2,'MintCream',649,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(650,'MistyRose',0,'','open',2,'MistyRose',650,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(651,'Moccasin',0,'','open',2,'Moccasin',651,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(652,'NavajoWhite',0,'','open',2,'NavajoWhite',652,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(653,'Navy',0,'','open',2,'Navy',653,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(654,'OldLace',0,'','open',2,'OldLace',654,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(655,'Olive',0,'','open',2,'Olive',655,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(656,'OliveDrab',0,'','open',2,'OliveDrab',656,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(657,'Orange',0,'','open',2,'Orange',657,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(658,'OrangeRed',0,'','open',2,'OrangeRed',658,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(659,'Orchid',0,'','open',2,'Orchid',659,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(660,'PaleGoldenRod',0,'','open',2,'PaleGoldenRod',660,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(661,'PaleGreen',0,'','open',2,'PaleGreen',661,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(662,'PaleTurquoise',0,'','open',2,'PaleTurquoise',662,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(663,'PaleVioletRed',0,'','open',2,'PaleVioletRed',663,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(664,'PapayaWhip',0,'','open',2,'PapayaWhip',664,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(665,'PeachPuff',0,'','open',2,'PeachPuff',665,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(666,'Peru',0,'','open',2,'Peru',666,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(667,'Pink',0,'','open',2,'Pink',667,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(668,'Plum',0,'','open',2,'Plum',668,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(669,'PowderBlue',0,'','open',2,'PowderBlue',669,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(670,'Purple',0,'','open',2,'Purple',670,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(671,'Red',0,'','open',2,'Red',671,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(672,'RosyBrown',0,'','open',2,'RosyBrown',672,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(673,'RoyalBlue',0,'','open',2,'RoyalBlue',673,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(674,'SaddleBrown',0,'','open',2,'SaddleBrown',674,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(675,'Salmon',0,'','open',2,'Salmon',675,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(676,'SandyBrown',0,'','open',2,'SandyBrown',676,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(677,'SeaGreen',0,'','open',2,'SeaGreen',677,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(678,'SeaShell',0,'','open',2,'SeaShell',678,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(679,'Sienna',0,'','open',2,'Sienna',679,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(680,'Silver',0,'','open',2,'Silver',680,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(681,'SkyBlue',0,'','open',2,'SkyBlue',681,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(682,'SlateBlue',0,'','open',2,'SlateBlue',682,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(683,'SlateGray',0,'','open',2,'SlateGray',683,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(684,'SlateGrey',0,'','open',2,'SlateGrey',684,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(685,'Snow',0,'','open',2,'Snow',685,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(686,'SpringGreen',0,'','open',2,'SpringGreen',686,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(687,'SteelBlue',0,'','open',2,'SteelBlue',687,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(688,'Tan',0,'','open',2,'Tan',688,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(689,'Teal',0,'','open',2,'Teal',689,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(690,'Thistle',0,'','open',2,'Thistle',690,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(691,'Tomato',0,'','open',2,'Tomato',691,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(692,'Turquoise',0,'','open',2,'Turquoise',692,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(693,'Violet',0,'','open',2,'Violet',693,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(694,'Wheat',0,'','open',2,'Wheat',694,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(695,'White',0,'','open',2,'White',695,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(696,'WhiteSmoke',0,'','open',2,'WhiteSmoke',696,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(697,'Yellow',0,'','open',2,'Yellow',697,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(698,'YellowGreen',0,'','open',2,'YellowGreen',698,1,0,'enum',0,0,551,1,'2012-09-27 03:52:36',0,NULL),(699,'Smarty',0,'','open',2,'Smarty',699,0,0,'enum',0,0,521,2,'2012-09-27 03:52:36',0,NULL),(700,'XSLT',0,'','open',2,'XSLT',700,0,0,'enum',0,3,524,3,'2012-09-27 03:52:36',0,NULL),(701,'XSLT 1.0',0,'','open',2,'XSLT 1.0',701,0,0,'enum',0,0,700,4,'2012-09-27 03:52:36',0,NULL),(702,'XSLT 1.1',0,'','open',2,'XSLT 1.1',702,0,0,'enum',0,0,700,4,'2012-09-27 03:52:36',0,NULL),(703,'XSLT 2.0',0,'','open',2,'XSLT 2.0',703,0,0,'enum',0,0,700,4,'2012-09-27 03:52:36',0,NULL),(704,'TEI P4',0,'','open',2,'TEI P4',704,0,0,'enum',0,0,525,4,'2012-09-27 03:52:36',0,NULL),(705,'TEI P5',0,'','open',2,'TEI P5',705,0,0,'enum',0,0,525,4,'2012-09-27 03:52:36',0,NULL),(706,'Tool Type',NULL,'Terms that represent a type of tool or action','open',2,'Tool Type',706,0,0,'enum',0,0,NULL,1,'2012-09-27 03:52:36',0,NULL),(707,'Annotation Type',NULL,'Type of annotation','open',2,'Annotation Type',707,0,0,'enum',0,0,706,1,'2012-09-27 03:52:36',0,NULL),(708,'Commentary',NULL,'Commentary subtypes','open',2,'Commentary',708,0,0,'enum',0,0,707,1,'2012-09-27 03:52:36',0,NULL),(709,'Comment',NULL,'Comment','open',2,'Comment',709,0,0,'enum',0,0,708,1,'2012-09-27 03:52:36',0,NULL),(710,'Entity',NULL,'Entity subtype','open',2,'Entity',710,0,0,'enum',0,0,707,1,'2012-09-27 03:52:36',0,NULL),(711,'Event',NULL,'Event','open',2,'Event',711,0,0,'enum',0,0,710,1,'2012-09-27 03:52:36',0,NULL),(712,'Person',NULL,'Person','open',2,'Person',712,0,0,'enum',0,0,710,1,'2012-09-27 03:52:36',0,NULL),(713,'Place',NULL,'Place','open',2,'Place',713,0,0,'enum',0,0,710,1,'2012-09-27 03:52:36',0,NULL),(714,'Literary Analysis',2,'Literary Analysis subtype','open',NULL,'Literary Analysis',741,0,0,'enum',0,0,707,1,'2012-09-27 03:52:36',0,NULL),(715,'Interpretation',2,'Interpretation','open',NULL,'Interpretation',715,0,0,'enum',0,0,714,1,'2012-09-27 03:52:36',0,NULL),(716,'Proof reading',2,'Proof reading subtype','open',NULL,'Proof reading',716,0,0,'enum',0,0,707,1,'2012-09-27 03:52:36',0,NULL),(717,'Addition',2,'Addition','open',NULL,'Addition',717,0,0,'enum',0,0,716,1,'2012-09-27 03:52:36',0,NULL),(718,'Change',2,'Change','open',NULL,'Change',718,0,0,'enum',0,0,716,1,'2012-09-27 03:52:36',0,NULL),(719,'Delete',2,'Delete','open',NULL,'Delete',719,0,0,'enum',0,0,716,1,'2012-09-27 03:52:36',0,NULL),(3001,'Generic',0,'','open',2,'Generic',3001,0,0,'relation',0,6,0,0,'2012-09-27 03:52:36',0,NULL),(3002,'Abuts',3081,'','open',2,'Abuts',3002,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3003,'BondsWith',3003,'','open',2,'BondsWith',3003,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3004,'IsCausedBy',3005,'','open',2,'IsCausedBy',3004,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3005,'Causes',3004,'','open',2,'Causes',3005,0,0,'relation',0,0,3113,1,'2012-09-27 03:52:36',0,NULL),(3006,'CollaboratesWith',3006,'','open',2,'CollaboratesWith',3006,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3007,'IsPartOf',3008,'','open',2,'IsPartOf',3007,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3008,'Contains',3007,'','open',2,'Contains',3008,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3009,'CooperatesWith',3009,'','open',2,'CooperatesWith',3009,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3010,'Cuts',3093,'','open',2,'Cuts',3010,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3011,'StartsAfter',3012,'','open',2,'StartsAfter',3011,0,0,'relation',0,0,3113,1,'2012-09-27 03:52:36',0,NULL),(3012,'EndsBefore',3011,'','open',2,'EndsBefore',3012,0,0,'relation',0,0,3113,1,'2012-09-27 03:52:36',0,NULL),(3013,'IsFundedBy',3014,'','open',2,'IsFundedBy',3013,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3014,'Funds',3013,'','open',2,'Funds',3014,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3015,'IsAssociateDirectorOf',3016,'','open',2,'IsAssociateDirectorOf',3015,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3016,'HasAssociateDirector',3015,'','open',2,'HasAssociateDirector',3016,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3017,'IsAssociatePartnerIn',3018,'','open',2,'IsAssociatePartnerIn',3017,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3018,'HasAssociatePartner',3017,'','open',2,'HasAssociatePartner',3018,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3019,'IsAuthorOf',3020,'','open',2,'IsAuthorOf',3019,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3020,'HasAuthor',3019,'','open',2,'HasAuthor',3020,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3021,'HasNarrowerTerm',3022,'','open',2,'HasNarrowerTerm',3021,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',1,NULL),(3022,'HasBroaderTerm',3021,'','open',2,'HasBroaderTerm',3022,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',1,NULL),(3023,'IsCoAuthorOf',3024,'','open',2,'IsCoAuthorOf',3023,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3024,'HasCoAuthor',3023,'','open',2,'HasCoAuthor',3024,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3025,'IsCoConvenerOf',3026,'','open',2,'IsCoConvenerOf',3025,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3026,'HasCoConvenor',3025,'','open',2,'HasCoConvenor',3026,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3027,'IsCommanderOf',3028,'','open',2,'IsCommanderOf',3027,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3028,'HasCommander',3027,'','open',2,'HasCommander',3028,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3029,'IsContributorOf',3030,'','open',2,'IsContributorOf',3029,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3030,'HasContributor',3029,'','open',2,'HasContributor',3030,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3031,'IsConvenerOf',3032,'','open',2,'IsConvenerOf',3031,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3032,'HasConvenor',3031,'','open',2,'HasConvenor',3032,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3033,'IsDirectorOf',3034,'','open',2,'IsDirectorOf',3033,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3034,'HasDirector',3033,'','open',2,'HasDirector',3034,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3035,'IsEditionOf',3036,'','open',2,'IsEditionOf',3035,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3036,'HasEdition',3035,'','open',2,'HasEdition',3036,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3037,'HasMap',3038,'','open',2,'HasMap',3037,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3038,'HasEntry',3037,'','open',2,'HasEntry',3038,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3039,'IsExternalLinkOf',3040,'','open',2,'IsExternalLinkOf',3039,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3040,'HasExternalLink',3039,'','open',2,'HasExternalLink',3040,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',1,NULL),(3041,'IsFounderOf',3042,'','open',2,'IsFounderOf',3041,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3042,'HasFounder',3041,'','open',2,'HasFounder',3042,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3043,'IsHostOf',3044,'','open',2,'IsHostOf',3043,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3044,'HasHost',3043,'','open',2,'HasHost',3044,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3045,'IsLeadPartner',3046,'','open',2,'IsLeadPartner',3045,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3046,'HasLeadPartner',3045,'','open',2,'HasLeadPartner',3046,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3047,'IsManagerOf',3048,'','open',2,'IsManagerOf',3047,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3048,'HasManager',3047,'','open',2,'HasManager',3048,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3049,'IsMemberOf',3050,'','open',2,'IsMemberOf',3049,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3050,'HasMember',3049,'','open',2,'HasMember',3050,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3051,'IsMilestoneOf',3052,'','open',2,'IsMilestoneOf',3051,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3052,'HasMilestone',3051,'','open',2,'HasMilestone',3052,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3053,'HasPreviousPart',3054,'','open',2,'HasPreviousPart',3053,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3054,'HasNextPart',3053,'','open',2,'HasNextPart',3054,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3055,'IsNodeDirectorOf',3056,'','open',2,'IsNodeDirectorOf',3055,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3056,'HasNodeDirector',3055,'','open',2,'HasNodeDirector',3056,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3057,'IsPageImageOf',3058,'','open',2,'IsPageImageOf',3057,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3058,'HasPageImage',3057,'','open',2,'HasPageImage',3058,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3059,'IsPartnerIn',3060,'','open',2,'IsPartnerIn',3059,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3060,'HasPartner',3059,'','open',2,'HasPartner',3060,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3061,'IsPhotographOf',3062,'','open',2,'IsPhotographOf',3061,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3062,'HasPhotograph',3061,'','open',2,'HasPhotograph',3062,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3063,'IsPrimarySubjectOf',3064,'','open',2,'IsPrimarySubjectOf',3063,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3064,'HasPrimarySubject',3063,'','open',2,'HasPrimarySubject',3064,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3065,'IsReferenceOf',3066,'','open',2,'IsReferenceOf',3065,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3066,'HasReference',3065,'','open',2,'HasReference',3066,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3067,'HasRelatedTerm',3067,'','open',2,'HasRelatedTerm',3067,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3068,'IsResourceOf',3069,'','open',2,'IsResourceOf',3068,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3069,'HasResource',3068,'','open',2,'HasResource',3069,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3070,'IsSpeakerAt',3071,'','open',2,'IsSpeakerAt',3070,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3071,'HasSpeaker',3070,'','open',2,'HasSpeaker',3071,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3072,'IsSubjectOf',3073,'','open',2,'IsSubjectOf',3072,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3073,'HasSubject',3072,'','open',2,'HasSubject',3073,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3074,'IsSubNodeOf',3075,'','open',2,'IsSubNodeOf',3074,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3075,'HasSubNode',3074,'','open',2,'HasSubNode',3075,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3076,'IsTranscriptionOf',3077,'','open',2,'IsTranscriptionOf',3076,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3077,'HasTranscription',3076,'','open',2,'HasTranscription',3077,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3078,'IsInText',3079,'','open',2,'IsInText',3078,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3079,'IsAbout',3078,'','open',2,'IsAbout',3079,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3080,'IsAbove',3085,'','open',2,'IsAbove',3080,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3081,'IsAbuttedBy',3002,'','open',2,'IsAbuttedBy',3081,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3082,'IsAdjacentTo',3082,'','open',2,'IsAdjacentTo',3082,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3083,'IsNeiceOrNephewOf',3084,'','open',2,'IsNeiceOrNephewOf',3083,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3084,'IsAuntOrUncleOf',3083,'','open',2,'IsAuntOrUncleOf',3084,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3085,'IsBelow',3080,'','open',2,'IsBelow',3085,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3087,'IsBrotherOf',3442,'','open',2,'IsBrotherOf',3087,0,0,'relation',0,0,3118,2,'2012-09-27 03:52:36',0,NULL),(3088,'IsBrotherOrSisterInLaw',3088,'','open',2,'IsBrotherOrSisterInLaw',3088,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3089,'IsParentOf',3090,'','open',2,'IsParentOf',3089,0,0,'relation',0,2,3110,1,'2012-09-27 03:52:36',0,NULL),(3090,'IsChildOf',3089,'','open',2,'IsChildOf',3090,0,0,'relation',0,2,3110,1,'2012-09-27 03:52:36',0,NULL),(3091,'IsContemporaryWith',3091,'','open',2,'IsContemporaryWith',3091,0,0,'relation',0,0,3113,1,'2012-09-27 03:52:36',0,NULL),(3092,'IsCousinOf',3092,'','open',2,'IsCousinOf',3092,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3093,'IsCutBy',3010,'','open',2,'IsCutBy',3093,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3094,'IsDaughterOrSonInLaw',3105,'','open',2,'IsDaughterOrSonInLaw',3094,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3095,'IsFatherOf',3090,'','open',2,'IsFatherOf',3095,0,0,'relation',0,0,3089,2,'2012-09-27 03:52:36',0,NULL),(3096,'IsFilledBy',3443,'','open',2,'IsFilledBy',3096,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3097,'WasFoundAt',3098,'','open',2,'WasFoundAt',3097,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3098,'IsFindSiteOf',3097,'','open',2,'IsFindSiteOf',3098,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3099,'IsGrandFatherOf',3116,'','open',2,'IsGrandfatherOf',3099,0,0,'relation',0,0,3115,2,'2012-09-27 03:52:36',1,NULL),(3100,'IsGrandMotherOf',3116,'','open',2,'IsGrandmotherOf',3100,0,0,'relation',0,0,3115,2,'2012-09-27 03:52:36',1,NULL),(3101,'IsOf',3102,'','open',2,'IsOf',3101,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3102,'IsIn',3101,'','open',2,'IsIn',3102,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3103,'IsMarriedTo',3103,'','open',2,'IsMarriedTo',3103,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3104,'IsMotherOf',3090,'','open',2,'IsMotherOf',3104,0,0,'relation',0,0,3089,2,'2012-09-27 03:52:36',0,NULL),(3105,'IsMotherOrFatherInLaw',3094,'','open',2,'IsMotherOrFatherInLaw',3105,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3106,'Owns',3107,'','open',2,'Owns',3106,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3107,'IsOwnedBy',3106,'','open',2,'IsOwnedBy',3107,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3108,'IsRelatedTo',3108,'','open',2,'IsRelatedTo',3108,0,0,'relation',0,0,3001,1,'2012-09-27 03:52:36',0,NULL),(3109,'Stratigraphic',0,'','open',2,'Stratigraphic',3109,0,0,'relation',0,14,0,0,'2012-09-27 03:52:36',0,NULL),(3110,'Family',0,'','open',2,'Family',3110,0,0,'relation',0,13,0,0,'2012-09-27 03:52:36',0,NULL),(3111,'Organisational/Roles',0,'','open',2,'Organisational/Roles',3111,0,0,'relation',0,55,0,0,'2012-09-27 03:52:36',0,NULL),(3112,'Containment',0,'','open',2,'Containment',3112,0,0,'relation',0,31,0,0,'2012-09-27 03:52:36',0,NULL),(3113,'Temporal',0,'','open',2,'Temporal',3113,0,0,'relation',0,4,0,0,'2012-09-27 03:52:36',0,NULL),(3114,'Document',0,'','open',2,'Document',3114,0,0,'relation',0,36,0,0,'2012-09-27 03:52:36',0,NULL),(3115,'IsGrandParentOf',3116,'','open',2,'IsGrandparentOf',3115,0,0,'relation',0,2,3110,1,'2012-09-27 03:52:36',1,NULL),(3116,'IsGrandChildOf',3115,'','open',2,'IsGrandchildOf',3116,0,0,'relation',0,2,3110,1,'2012-09-27 03:52:36',1,NULL),(3117,'IsSisterOf',3118,'','open',2,'IsSisterOf',3117,0,0,'relation',0,0,3118,2,'2012-09-27 03:52:36',0,NULL),(3118,'IsSiblingOf',3118,'','open',2,'IsSiblingOf',3118,0,0,'relation',0,2,3110,1,'2012-09-27 03:52:36',0,NULL),(3119,'Fills',3096,'','open',2,'Fills',3119,0,0,'relation',0,0,3109,1,'2012-09-27 03:52:36',0,NULL),(3120,'Reconfigurations',0,'','reserved',2,'Reconfigurations',3120,0,0,'relation',0,12,0,0,'2012-09-27 03:52:36',0,NULL),(3121,'Versions',0,'','reserved',2,'Versions',3121,0,0,'relation',0,30,0,0,'2012-09-27 03:52:36',0,NULL),(3122,'Media',0,'','reserved',2,'Media',3122,0,0,'relation',0,6,0,0,'2012-09-27 03:52:36',0,NULL),(3123,'Derivatives',0,'','reserved',2,'Derivatives',3123,0,0,'relation',0,14,0,0,'2012-09-27 03:52:36',0,NULL),(3124,'IsTranslatorOf',3178,'Added from LORE','open',2,'IsTranslatorOf',3124,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3125,'PermissionGrantedBy',3206,'Added from LORE','open',2,'PermissionGrantedBy',3125,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3126,'IsAcquaintanceOf',3126,'Added from LORE','open',2,'IsAcquaintanceOf',3126,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3127,'IsCustodianOf',3179,'Added from LORE','open',2,'IsCustodianOf',3127,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3128,'IsPublisherOf',3180,'Added from LORE','open',2,'IsPublisherOf',3128,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3129,'IsIllustratorOf',3181,'Added from LORE','open',2,'IsIllustratorOf',3129,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3130,'IsMentorOf',3182,'Added from LORE','open',2,'IsMentorOf',3130,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3131,'IsParticipantIn',3183,'Added from LORE','open',2,'IsParticipantIn',3131,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3132,'IsProducerOf',3185,'Added from LORE','open',2,'IsProducerOf',3132,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3133,'IsFamilyMemberOf',3133,'Added from LORE','open',2,'IsFamilyMemberOf',3133,0,0,'relation',0,0,3110,1,'2012-09-27 03:52:36',0,NULL),(3134,'IsOwnedBy',3186,'Added from LORE','open',2,'IsOwnedBy',3134,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3135,'IsRepresentationOf',3222,'Added from LORE','open',2,'IsRepresentationOf',3135,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3136,'IsPerformanceOf',3220,'Added from LORE','open',2,'IsPerformanceOf',3136,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3137,'IsDramatizationOf',3218,'Added from LORE','open',2,'IsDramatizationOf',3137,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3138,'IsScreenplayOf',3223,'Added from LORE','open',2,'IsScreenplayOf',3138,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3139,'IsReconfigurationOf',3221,'Added from LORE','open',2,'IsReconfigurationOf',3139,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3140,'IsNovelizationOf',3219,'Added from LORE','open',2,'IsNovelizationOf',3140,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3141,'IsSummaryOf',3238,'Added from LORE','open',2,'IsSummaryOf',3141,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3142,'IsDigestOf',3231,'Added from LORE','open',2,'IsDigestOf',3142,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3143,'IsCopyOf',3230,'Added from LORE','open',2,'IsCopyOf',3143,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3144,'IsTranscriptOf',3239,'Added from LORE','open',2,'IsTranscriptOf',3144,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3145,'IsTranslationOf',3240,'Added from LORE','open',2,'IsTranslationOf',3145,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3146,'IsAdaptationOf',3228,'Added from LORE','open',2,'IsAdaptationOf',3146,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3147,'IsEditionOf',3232,'Added from LORE','open',2,'IsEditionOf',3147,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3148,'IsAnnotatedEditionOf',3229,'Added from LORE','open',2,'IsAnnotatedEditionOf',3148,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3149,'IsAbridgedEditionOf',3227,'Added from LORE','open',2,'IsAbridgedEditionOf',3149,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3150,'IsIllustratedEditionOf',3234,'Added from LORE','open',2,'IsIllustratedEditionOf',3150,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3151,'IsExpurgatedEditionOf',3233,'Added from LORE','open',2,'IsExpurgatedEditionOf',3151,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3152,'IsReproductionOf',3236,'Added from LORE','open',2,'IsReproductionOf',3152,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3153,'IsRealizationOf',3235,'Added from LORE','open',2,'IsRealizationOf',3153,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3154,'IsRevisionOf',3237,'Added from LORE','open',2,'IsRevisionOf',3154,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3155,'IsVersionOf',3241,'Added from LORE','open',2,'IsVersionOf',3155,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3156,'IsImageOf',3215,'Added from LORE','open',2,'IsImageOf',3156,0,0,'relation',0,0,3122,1,'2012-09-27 03:52:36',0,NULL),(3157,'IsVideoOf',3216,'Added from LORE','open',2,'IsVideoOf',3157,0,0,'relation',0,0,3122,1,'2012-09-27 03:52:36',0,NULL),(3158,'IsAudioOf',3214,'Added from LORE','open',2,'IsAudioOf',3158,0,0,'relation',0,0,3122,1,'2012-09-27 03:52:36',0,NULL),(3159,'IsTableOfContentsOf',3202,'Added from LORE','open',2,'IsTableOfContentsOf',3159,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3160,'IsSupplementOf',3204,'Added from LORE','open',2,'IsSupplementOf',3160,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3161,'IsAbstractOf',3203,'Added from LORE','open',2,'IsAbstractOf',3161,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3162,'IsGlossaryOf',3217,'Added from LORE','open',2,'IsGlossaryOf',3162,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3163,'IsParodyOf',3212,'Added from LORE','reserved',2,'IsParodyOf',3163,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3164,'IsPreviewOf',3213,'Added from LORE','reserved',2,'IsPreviewOf',3164,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3165,'IsLimitationOf',3211,'Added from LORE','reserved',2,'IsLimitationOf',3165,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3166,'IsCriticismOf',3208,'Added from LORE','reserved',2,'IsCriticismOf',3166,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3167,'IsAnnotationOn',3207,'Added from LORE','reserved',2,'IsAnnotationOn',3167,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3168,'IsDerivedFrom',3209,'Added from LORE','reserved',2,'IsDerivedFrom',3168,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3169,'IsExcerptFrom',3210,'Added from LORE','reserved',2,'IsExcerptFrom',3169,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3170,'has_location',0,'','reserved',2,'has_location',3170,0,0,'relation',0,0,3001,1,'2012-09-27 03:52:36',1,NULL),(3171,'is_realized_through',0,'','reserved',2,'is_realized_through',3171,0,0,'relation',0,0,3001,1,'2012-09-27 03:52:36',0,NULL),(3172,'is_exemplification_of',0,'','reserved',2,'is_exemplification_of',3172,0,0,'relation',0,0,3001,1,'2012-09-27 03:52:36',0,NULL),(3173,'is_embodiment',0,'','reserved',2,'is_embodiment',3173,0,0,'relation',0,0,3001,1,'2012-09-27 03:52:36',0,NULL),(3174,'PapersRelatingTo',0,'Added from LORE','open',2,'PapersRelatingTo',3174,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3175,'IsMetadataFor',3225,'Added from LORE','open',2,'IsMetadataFor',3175,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3176,'IsDescriptionOf',3224,'Added from LORE','open',2,'IsDescriptionOf',3176,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3177,'is_subject_of',0,'','reserved',2,'is_subject_of',3177,0,0,'relation',0,0,3001,1,'2012-09-27 03:52:36',0,NULL),(3178,'HasTranslator',3124,'Added from LORE','open',2,'HasTranslator',3178,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3179,'HasCustodian',3127,'Added from LORE','open',2,'HasCustodian',3179,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3180,'HasPublisher',3128,'Added from LORE','open',2,'HasPublisher',3180,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3181,'HasIllustrator',3129,'Added from LORE','open',2,'HasIllustrator',3181,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3182,'IsMentoredBy',3130,'Added from LORE','open',2,'IsMentoredBy',3182,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3183,'HasParticipant',3131,'Added from LORE','open',2,'HasParticipant',3183,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3184,'HasAgent',0,'Added from LORE','open',2,'HasAgent',3184,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3185,'HasProducer',3132,'Added from LORE','open',2,'HasProducer',3185,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3186,'HasOwner',3134,'Added from LORE','open',2,'HasOwner',3186,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3187,'IsPartOf',3191,'','reserved',2,'isPartOf',3187,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',1,NULL),(3188,'IsDaughterOf',3089,'','open',2,'IsDaughterOf',3188,0,0,'relation',0,0,3090,2,'2012-09-27 03:52:36',0,NULL),(3189,'IsSonOf',3089,'','open',2,'IsSonOf',3189,0,0,'relation',0,0,3090,2,'2012-09-27 03:52:36',0,NULL),(3190,'HasConstituents',3197,'','open',2,'HasConstituents',3190,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3191,'HasPart',3187,'','open',2,'HasPart',3191,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3192,'IsAggregateOf',3198,'','open',2,'IsAggregateOf',3192,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3193,'IsMemberOf',3199,'','open',2,'IsMemberOf',3193,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3194,'IsSequelOf',0,'','open',2,'IsSequelIo',3194,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3195,'IsSubsetOf',3200,'','open',2,'IsSubsetOf',3195,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3196,'IsSucessorOf',3201,'Added from LORE','open',2,'IsSucessorOf',3196,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3197,'Constituents',3191,'','open',2,'Constituents',3197,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3198,'IsPart',3192,'','open',2,'IsPart',3198,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3199,'HasMember',3193,'','open',2,'HasMember',3199,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3200,'HasSubset',3195,'','open',2,'HasSubset',3200,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3201,'Succeeds',3196,'','open',2,'Succeeds',3201,0,0,'relation',0,0,3112,1,'2012-09-27 03:52:36',0,NULL),(3202,'HasTableOfContents',3159,'','open',2,'HasTableOfContents',3202,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3203,'HasAbstract',3161,'','open',2,'HasAbstract',3203,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3204,'HasSupplement',3160,'','open',2,'HasSupplement',3204,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3205,'IsAgentOf',3184,'','open',2,'IsAgentOf',3205,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3206,'GrantsPermissionTo',3125,'','open',2,'GrantsPermissionTo',3206,0,0,'relation',0,0,3111,1,'2012-09-27 03:52:36',0,NULL),(3207,'HasAnnotation',3167,'','reserved',2,'HasAnnotation',3207,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3208,'HasCriticism',3166,'','reserved',2,'HasCriticism',3208,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3209,'HasDerived',3168,'','reserved',2,'HasDerived',3209,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3210,'HasExcerpt',3169,'','reserved',2,'HasExcerpt',3210,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3211,'HasLimitation',3165,'','reserved',2,'HasLimitation',3211,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3212,'HasParody',3163,'','reserved',2,'HasParody',3212,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3213,'HasPreview',3164,'','reserved',2,'HasPreview',3213,0,0,'relation',0,0,3123,1,'2012-09-27 03:52:36',0,NULL),(3214,'HasAudio',3158,'','open',2,'HasAudio',3214,0,0,'relation',0,0,3122,1,'2012-09-27 03:52:36',0,NULL),(3215,'HasImage',3156,'','reserved',2,'HasImage',3215,0,0,'relation',0,0,3122,1,'2012-09-27 03:52:36',0,NULL),(3216,'HasVideo',3157,'','reserved',2,'HasVideo',3216,0,0,'relation',0,0,3122,1,'2012-09-27 03:52:36',0,NULL),(3217,'HasGlossary',3162,'','open',2,'HasGlossary',3217,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3218,'HasDramatization',3137,'','reserved',2,'HasDramatization',3218,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3219,'HasNovelization',3140,'','open',2,'HasNovelization',3219,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3220,'HasPerformance',3136,'','open',2,'HasPerformance',3220,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3221,'HasReconfiguration',3139,'','open',2,'HasReconfiguration',3221,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3222,'HasRepresentation',3135,'','open',2,'HasRepresentation',3222,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3223,'HasScreenplay',3138,'','open',2,'HasScreenplay',3223,0,0,'relation',0,0,3120,1,'2012-09-27 03:52:36',0,NULL),(3224,'HasDescription',3176,'','open',2,'HasDescription',3224,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3225,'HasMetadata',3175,'','open',2,'HasMetadata',3225,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3226,'HasRelatedPapers',3174,'','open',2,'HasRelatedPapers',3226,0,0,'relation',0,0,3114,1,'2012-09-27 03:52:36',0,NULL),(3227,'HasAbridgedEdition',3149,'','reserved',2,'HasAbridgedEdition',3227,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3228,'HasAdaptation',3146,'','reserved',2,'HasAdaptation',3228,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3229,'HasAnnotatedEdition',3148,'','reserved',2,'HasAnnotatedEdition',3229,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3230,'HasCopy',3143,'','reserved',2,'HasCopy',3230,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3231,'HasDigest',3142,'','reserved',2,'HasDigest',3231,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3232,'HasEdition',3147,'','reserved',2,'HasEdition',3232,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3233,'HasExpurgatedEdition',3151,'','reserved',2,'HasExpurgatedEdition',3233,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3234,'HasIllustratedEdition',3150,'','reserved',2,'HasIllustratedEdition',3234,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3235,'HasRealization',3153,'','reserved',2,'HasRealization',3235,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3236,'HasReproduction',3152,'','reserved',2,'HasReproduction',3236,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3237,'HasRevision',3154,'','reserved',2,'HasRevision',3237,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3238,'HasSummary',3141,'','reserved',2,'HasSummary',3238,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3239,'HasTranscript',3144,'','reserved',2,'HasTranscript',3239,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3240,'HasTranslation',3145,'','reserved',2,'HasTranslation',3240,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3241,'HasVersion',3155,'','reserved',2,'HasVersion',3241,0,0,'relation',0,0,3121,1,'2012-09-27 03:52:36',0,NULL),(3242,'IsGrandSonOf',3115,'','open',2,'IsGrandSonOf',3242,0,0,'relation',0,0,3116,2,'2012-09-27 03:52:36',1,NULL),(3243,'IsGrandDaughterOf',3115,'','open',2,'IsGrandDaughterOf',3243,0,0,'relation',0,0,3116,2,'2012-09-27 03:52:36',1,NULL);
/*!40000 ALTER TABLE `defTerms` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defTerms_last_insert` AFTER INSERT ON `defTerms` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defTerms_last_update` AFTER UPDATE ON `defTerms` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defTerms_last_delete` AFTER DELETE ON `defTerms` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `defTranslations`
--

DROP TABLE IF EXISTS `defTranslations`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defTranslations` (
  `trn_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key of defTranslations table',
  `trn_Source` enum('rty_Name','dty_Name','ont_ShortName','vcb_Name','trm_Label','rst_DisplayName','rtg_Name','dtl_Value') NOT NULL COMMENT 'The table/column to be translated (unique names identify source)',
  `trn_Code` smallint(5) unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
  `trn_LanguageCode3` char(3) NOT NULL COMMENT 'The translation language code (NISO 3 character) for this record',
  `trn_Translation` varchar(63) NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
  `trn_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`trn_ID`),
  UNIQUE KEY `trn_composite` (`trn_Source`,`trn_Code`,`trn_LanguageCode3`),
  KEY `trn_LanguageCode3` (`trn_LanguageCode3`),
  CONSTRAINT `fk_trn_LanguageCode3` FOREIGN KEY (`trn_LanguageCode3`) REFERENCES `defLanguages` (`lng_NISOZ3953`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Translation table into multiple languages for all translatab';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defTranslations`
--

LOCK TABLES `defTranslations` WRITE;
/*!40000 ALTER TABLE `defTranslations` DISABLE KEYS */;
/*!40000 ALTER TABLE `defTranslations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defURLPrefixes`
--

DROP TABLE IF EXISTS `defURLPrefixes`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `defURLPrefixes` (
  `urp_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'ID which will be stored as proxy for the URL prefix',
  `urp_Prefix` varchar(250) NOT NULL COMMENT 'URL prefix which is prepended to record URLs',
  `urp_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`urp_ID`),
  UNIQUE KEY `urp_Prefix` (`urp_Prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Common URL prefixes allowing single-point change of URL for ';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defURLPrefixes`
--

LOCK TABLES `defURLPrefixes` WRITE;
/*!40000 ALTER TABLE `defURLPrefixes` DISABLE KEYS */;
/*!40000 ALTER TABLE `defURLPrefixes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recDetails`
--

DROP TABLE IF EXISTS `recDetails`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `recDetails` (
  `dtl_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key for record detail (field) values table',
  `dtl_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which this detail (field) applies',
  `dtl_DetailTypeID` smallint(5) unsigned NOT NULL COMMENT 'The detail type code identifying the type definition of data',
  `dtl_Value` text COMMENT 'The value of the detail as text (used for all except files and geometry)',
  `dtl_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Set 1 if added by an import, set 0 if added by user during data entry',
  `dtl_UploadedFileID` mediumint(8) unsigned default NULL COMMENT 'The numeric code = filename of an uploaded file ',
  `dtl_Geo` geometry default NULL COMMENT 'A geometry (spatial) object',
  `dtl_ValShortened` varchar(31) NOT NULL COMMENT 'Truncated version of the textual value without spaces',
  `dtl_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record detail, used to get last updated date for table',
  PRIMARY KEY  (`dtl_ID`),
  KEY `dtl_DetailtypeIDkey` (`dtl_DetailTypeID`),
  KEY `dtl_RecIDKey` (`dtl_RecID`),
  KEY `dtl_ValShortenedKey` (`dtl_ValShortened`),
  KEY `dtl_ValueKey` (`dtl_Value`(63)),
  KEY `dtl_UploadedFileIDKey` (`dtl_UploadedFileID`),
  CONSTRAINT `fk_dtl_DetailTypeID` FOREIGN KEY (`dtl_DetailTypeID`) REFERENCES `defDetailTypes` (`dty_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_dtl_RecID` FOREIGN KEY (`dtl_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dtl_UploadedFileID` FOREIGN KEY (`dtl_UploadedFileID`) REFERENCES `recUploadedFiles` (`ulf_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The detail (field) values for each record - public data';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `recDetails`
--

LOCK TABLES `recDetails` WRITE;
/*!40000 ALTER TABLE `recDetails` DISABLE KEYS */;
/*!40000 ALTER TABLE `recDetails` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `insert_Details_precis_trigger` BEFORE INSERT ON `recDetails` FOR EACH ROW begin set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), ''); end */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `insert_Details_trigger` AFTER INSERT ON `recDetails` FOR EACH ROW begin
        if NEW.dtl_DetailTypeID=5 then 
            update recRelationshipsCache
            
                set rrc_TargetRecID = NEW.dtl_Value
                where rrc_RecID=NEW.dtl_RecID;
        elseif NEW.dtl_DetailTypeID=7 then 
            update recRelationshipsCache
            
                set rrc_SourceRecID = NEW.dtl_Value
                where rrc_RecID=NEW.dtl_RecID;
        end if;
    end */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `pre_update_Details_trigger` BEFORE UPDATE ON `recDetails` FOR EACH ROW begin
        if asbinary(NEW.dtl_Geo)=asbinary(OLD.dtl_Geo) then
            set NEW.dtl_Geo = OLD.dtl_Geo;
        end if;
        set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), '');
    end */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `update_Details_trigger` AFTER UPDATE ON `recDetails` FOR EACH ROW begin
        if NEW.dtl_DetailTypeID=5 then 
            update recRelationshipsCache
            
                set rrc_TargetRecID = NEW.dtl_Value
                where rrc_RecID=NEW.dtl_RecID;
        elseif NEW.dtl_DetailTypeID=7 then 
        update recRelationshipsCache
                set rrc_SourceRecID = NEW.dtl_Value
            
                where rrc_RecID=NEW.dtl_RecID;
        end if;
        
    end */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `recForwarding`
--

DROP TABLE IF EXISTS `recForwarding`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `recForwarding` (
  `rfw_OldRecID` int(10) unsigned NOT NULL COMMENT 'The deleted record which will be redirected to another',
  `rfw_NewRecID` int(10) unsigned NOT NULL COMMENT 'The new record to which this ID will be forwarded',
  PRIMARY KEY  (`rfw_OldRecID`),
  KEY `rfw_NewRecID` (`rfw_NewRecID`),
  CONSTRAINT `fk_rfw_NewRecID` FOREIGN KEY (`rfw_NewRecID`) REFERENCES `Records` (`rec_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Allows referer routine to redirect certain calls to a replac';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `recForwarding`
--

LOCK TABLES `recForwarding` WRITE;
/*!40000 ALTER TABLE `recForwarding` DISABLE KEYS */;
/*!40000 ALTER TABLE `recForwarding` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recRelationshipsCache`
--

DROP TABLE IF EXISTS `recRelationshipsCache`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `recRelationshipsCache` (
  `rrc_RecID` int(10) unsigned NOT NULL COMMENT 'Record ID of a relationships record linking source and target records',
  `rrc_SourceRecID` int(10) unsigned NOT NULL COMMENT 'Pointer to source record for this relationship',
  `rrc_TargetRecID` int(10) unsigned NOT NULL COMMENT 'Pointer to target record for this relationship',
  PRIMARY KEY  (`rrc_RecID`),
  KEY `rrc_sourcePtrKey` (`rrc_SourceRecID`),
  KEY `rrc_TargetPtrKey` (`rrc_TargetRecID`),
  CONSTRAINT `fk_rrc_SourceRecID` FOREIGN KEY (`rrc_SourceRecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rrc_TargetRecID` FOREIGN KEY (`rrc_TargetRecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='A cache for record relationship pointers to speed access';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `recRelationshipsCache`
--

LOCK TABLES `recRelationshipsCache` WRITE;
/*!40000 ALTER TABLE `recRelationshipsCache` DISABLE KEYS */;
/*!40000 ALTER TABLE `recRelationshipsCache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recSimilarButNotDupes`
--

DROP TABLE IF EXISTS `recSimilarButNotDupes`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `recSimilarButNotDupes` (
  `snd_SimRecsList` varchar(16000) NOT NULL COMMENT 'A comma separated list of record IDs which are similar but not identical',
  KEY `snd_SimRecsList` (`snd_SimRecsList`(13))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Used in dedupe. Sets of IDs which are not dupes. Index is of';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `recSimilarButNotDupes`
--

LOCK TABLES `recSimilarButNotDupes` WRITE;
/*!40000 ALTER TABLE `recSimilarButNotDupes` DISABLE KEYS */;
/*!40000 ALTER TABLE `recSimilarButNotDupes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recThreadedComments`
--

DROP TABLE IF EXISTS `recThreadedComments`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `recThreadedComments` (
  `cmt_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Comment ID, primary key for comments',
  `cmt_Text` varchar(5000) NOT NULL COMMENT 'Text of comment',
  `cmt_OwnerUgrpID` smallint(5) unsigned NOT NULL COMMENT 'User ID of user making comment',
  `cmt_Added` datetime NOT NULL default '0000-00-00 00:00:00' COMMENT 'Date and time of creation of comment',
  `cmt_ParentCmtID` int(10) unsigned default NULL COMMENT 'Parent comment of this comment',
  `cmt_Deleted` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag deleted comments',
  `cmt_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of comment',
  `cmt_RecID` int(10) unsigned NOT NULL COMMENT 'Record ID to which this comment applies, required',
  PRIMARY KEY  (`cmt_ID`),
  KEY `cmt_OwnerUgrpID` (`cmt_OwnerUgrpID`),
  KEY `cmt_ParentCmtID` (`cmt_ParentCmtID`),
  KEY `cmt_RecID` (`cmt_RecID`),
  CONSTRAINT `fk_cmt_OwnerUgrpID` FOREIGN KEY (`cmt_OwnerUgrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cmt_ParentCmtID` FOREIGN KEY (`cmt_ParentCmtID`) REFERENCES `recThreadedComments` (`cmt_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cmt_RecID` FOREIGN KEY (`cmt_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Threaded comments for each record';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `recThreadedComments`
--

LOCK TABLES `recThreadedComments` WRITE;
/*!40000 ALTER TABLE `recThreadedComments` DISABLE KEYS */;
/*!40000 ALTER TABLE `recThreadedComments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recUploadedFiles`
--

DROP TABLE IF EXISTS `recUploadedFiles`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `recUploadedFiles` (
  `ulf_ID` mediumint(8) unsigned NOT NULL auto_increment COMMENT 'A unique numeric file ID used as filename to store the data on disk and should be different ids if shared',
  `ulf_OrigFileName` varchar(255) NOT NULL COMMENT 'The original name of the file uploaded',
  `ulf_UploaderUGrpID` smallint(5) unsigned default NULL COMMENT 'The user who uploaded the file',
  `ulf_Added` datetime NOT NULL default '0000-00-00 00:00:00' COMMENT 'The date and time the file was uploaded',
  `ulf_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date of last modification of the file description record, automatic update',
  `ulf_ObfuscatedFileID` varchar(40) default NULL COMMENT 'SHA-1 hash of ulf_ID and random number to block sequential file harvesting',
  `ulf_ExternalFileReference` varchar(1000) default NULL COMMENT 'URI of an external file, which may or may not be cached locally',
  `ulf_PreferredSource` enum('local','external') NOT NULL default 'local' COMMENT 'Preferred source of file if both local file and external reference set',
  `ulf_Thumbnail` blob COMMENT 'Cached autogenerated thumbnail for common image formats',
  `ulf_Description` text COMMENT 'A user-entered textual description of the file or image contents',
  `ulf_MimeExt` varchar(10) default NULL COMMENT 'Extension of the file, used to look up in mimetype table',
  `ulf_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag whether added by import = 1 or manual editing = 0',
  `ulf_FileSizeKB` int(10) unsigned default NULL COMMENT 'File size in Kbytes calculated at upload',
  `ulf_FilePath` varchar(1024) default NULL COMMENT 'The path where the uploaded file is stored',
  `ulf_FileName` varchar(512) default NULL COMMENT 'The filename for the uploaded file',
  `ulf_Parameters` text COMMENT 'Parameters including source (flickr,youtube...), default player etc. used to determine special processing',
  PRIMARY KEY  (`ulf_ID`),
  KEY `ulf_ObfuscatedFileIDKey` (`ulf_ObfuscatedFileID`),
  KEY `ulf_Description` (`ulf_Description`(100)),
  KEY `ulf_UploaderUGrpID` (`ulf_UploaderUGrpID`),
  KEY `ulf_MimeExt` (`ulf_MimeExt`),
  CONSTRAINT `fk_ulf_MimeExt` FOREIGN KEY (`ulf_MimeExt`) REFERENCES `defFileExtToMimetype` (`fxm_Extension`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ulf_UploaderUGrpID` FOREIGN KEY (`ulf_UploaderUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Index to uploaded files linked from records';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `recUploadedFiles`
--

LOCK TABLES `recUploadedFiles` WRITE;
/*!40000 ALTER TABLE `recUploadedFiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `recUploadedFiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysArchive`
--

DROP TABLE IF EXISTS `sysArchive`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysArchive` (
  `arc_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key of archive table',
  `arc_Table` enum('rec','cfn','crw','dtg','dty','fxm','ont','rst','rtg','rty','rcs','trm','trn','urp','vcb','dtl','rfw','rrc','snd','cmt','ulf','sys','lck','tlu','ugr','ugl','bkm','hyf','rtl','rre','rem','rbl','svs','tag','wprm','chunk','wrprm','woot') NOT NULL COMMENT 'Identification of the MySQL table in which a record is being modified',
  `arc_PriKey` int(10) unsigned NOT NULL COMMENT 'Primary key of the MySQL record in the table being modified',
  `arc_ChangedByUGrpID` smallint(5) unsigned NOT NULL COMMENT 'User who is logged in and modifying this data',
  `arc_OwnerUGrpID` smallint(5) unsigned default NULL COMMENT 'Owner of the data being modified (if applicable eg. records, bookmarks, tags)',
  `arc_RecID` int(10) unsigned default NULL COMMENT 'Heurist record id (if applicable, eg. for records, bookmarks, tag links)',
  `arc_TimeOfChange` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Timestamp of the modification',
  `arc_DataBeforeChange` blob COMMENT 'A representation of the data in the MySQL record before the mod, may be a diff',
  `arc_ContentType` enum('del','raw','zraw','diff','zdiff') NOT NULL default 'raw' COMMENT 'Format of the data stored, del=deleted, raw=text dump, Diff=delta, Z=zipped indicates ',
  PRIMARY KEY  (`arc_ID`),
  KEY `arc_Table` (`arc_Table`,`arc_ChangedByUGrpID`,`arc_OwnerUGrpID`,`arc_RecID`,`arc_TimeOfChange`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='An archive of all (or most) changes in the database to allow';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysArchive`
--

LOCK TABLES `sysArchive` WRITE;
/*!40000 ALTER TABLE `sysArchive` DISABLE KEYS */;
/*!40000 ALTER TABLE `sysArchive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysDBNameCache`
--

DROP TABLE IF EXISTS `sysDBNameCache`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysDBNameCache` (
  `dnc_ID` int(10) unsigned NOT NULL COMMENT 'Heurist DB ID for a registered Heurist database',
  `dnc_dbName` varchar(63) NOT NULL COMMENT 'Name of the database (from sys_DBName or Heurist index database)',
  `dnc_TimeStamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date when name of database last read from Heurist index or the database',
  `dnc_URL` varchar(128) default NULL COMMENT 'Root path to this installation and database',
  PRIMARY KEY  (`dnc_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Local db name cache for display of origin and source DBs in ';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysDBNameCache`
--

LOCK TABLES `sysDBNameCache` WRITE;
/*!40000 ALTER TABLE `sysDBNameCache` DISABLE KEYS */;
/*!40000 ALTER TABLE `sysDBNameCache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysDocumentation`
--

DROP TABLE IF EXISTS `sysDocumentation`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysDocumentation` (
  `doc_id` tinyint(3) unsigned NOT NULL auto_increment,
  `doc_text` text COMMENT 'Relevant documentation as text',
  PRIMARY KEY  (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Descriptive infORmation about this database and its function';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysDocumentation`
--

LOCK TABLES `sysDocumentation` WRITE;
/*!40000 ALTER TABLE `sysDocumentation` DISABLE KEYS */;
/*!40000 ALTER TABLE `sysDocumentation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysIdentification`
--

DROP TABLE IF EXISTS `sysIdentification`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysIdentification` (
  `sys_ID` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Only 1 record should exist in this table',
  `sys_dbRegisteredID` int(10) unsigned NOT NULL default '0' COMMENT 'Allocated by HeuristScholar.org, 0 indicates not yet registered',
  `sys_dbVersion` tinyint(3) unsigned NOT NULL default '0' COMMENT 'Major version for the database structure',
  `sys_dbSubVersion` tinyint(3) unsigned NOT NULL default '0' COMMENT 'Sub version',
  `sys_dbSubSubVersion` tinyint(3) unsigned NOT NULL default '0' COMMENT 'Sub-sub version',
  `sys_eMailImapServer` varchar(100) default NULL COMMENT 'Email server intermediary for record creation via email',
  `sys_eMailImapPort` varchar(5) default NULL COMMENT 'port for imap email server',
  `sys_eMailImapProtocol` varchar(5) default NULL COMMENT 'protocol for imap email server',
  `sys_eMailImapUsername` varchar(50) default NULL COMMENT 'user name for imap email server',
  `sys_eMailImapPassword` varchar(40) default NULL COMMENT 'password for imap email server',
  `sys_IncomingEmailAddresses` varchar(4000) default NULL COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins',
  `sys_TargetEmailAddresses` varchar(255) default NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses',
  `sys_UGrpsDatabase` varchar(63) default NULL COMMENT 'Full name of SQL database containing user tables, null = use internal users/groups tables',
  `sys_OwnerGroupID` smallint(5) unsigned NOT NULL default '1' COMMENT 'User group which owns/administers this database, 1 by default',
  `sys_dbName` varchar(63) NOT NULL default 'Please enter a DB name ...' COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  `sys_dbOwner` varchar(250) default NULL COMMENT 'Information on the owner of the database, may be a URL reference',
  `sys_dbRights` varchar(1000) NOT NULL default 'Please define ownership and rights here ...' COMMENT 'A statement of ownership and copyright for this database and content',
  `sys_dbDescription` varchar(1000) default NULL COMMENT 'A longer description of the content of this database',
  `sys_SyncDefsWithDB` varchar(63) default NULL COMMENT 'The name of the SQL database with which local definitions are to be synchronised',
  `sys_AutoIncludeFieldSetIDs` varchar(63) default '0' COMMENT 'CSV list of fieldsets which are included in all rectypes',
  `sys_RestrictAccessToOwnerGroup` tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, database may only be accessed by members of owners group',
  `sys_URLCheckFlag` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags whether system should send out requests to URLs to test for validity',
  `sys_UploadDirectory` varchar(128) default NULL COMMENT 'Absolute directory path for uploaded files (blank = use default from installation)',
  `sys_hmlOutputDirectory` varchar(255) default NULL COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory',
  `sys_htmlOutputDirectory` varchar(255) default NULL COMMENT 'Directory in which to write html representation of published records, default to html within upload directory',
  `sys_NewRecOwnerGrpID` smallint(5) unsigned NOT NULL default '1' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  `sys_NewRecAccess` enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  `sys_SetPublicToPendingOnEdit` tinyint(1) unsigned NOT NULL default '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''',
  `sys_ConstraintDefaultBehavior` enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL default 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  `sys_AllowRegistration` tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, people can apply for registration through web-based form',
  `sys_MediaFolders` varchar(10000) default NULL COMMENT 'Additional comma-sep directories which can contain files indexed in database',
  `sys_MediaExtensions` varchar(1024) default 'jpg,png,gif,tif,tiff,wmv,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff' COMMENT 'The file extensions to be harvested from the MediaFolders directories',
  PRIMARY KEY  (`sys_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Identification/version for this Heurist database (single rec';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysIdentification`
--

LOCK TABLES `sysIdentification` WRITE;
/*!40000 ALTER TABLE `sysIdentification` DISABLE KEYS */;
INSERT INTO `sysIdentification` VALUES (1,0,1,1,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'An empty database top act as a starting point for Heurist','','C-C Share Alike','',NULL,'0',0,0,NULL,'/var/www/htdocs/HEURIST_FILESTORE/vin_startingpoint/hml-output','/var/www/htdocs/HEURIST_FILESTORE/vin_startingpoint/html-output',0,'viewable',0,'locktypetotype',1,NULL,'jpg,png,gif,tif,tiff,wmv,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff');
/*!40000 ALTER TABLE `sysIdentification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysLocks`
--

DROP TABLE IF EXISTS `sysLocks`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysLocks` (
  `lck_Action` enum('buildcrosswalks','editdefinitions','errorscan','buildtempdb') NOT NULL COMMENT 'Type of action being carried out',
  `lck_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'Set to user ID to lock specified function',
  `lck_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Date and time when the action was commenced, use to drop old locks',
  PRIMARY KEY  (`lck_Action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Provides token system for selected administrative actions';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysLocks`
--

LOCK TABLES `sysLocks` WRITE;
/*!40000 ALTER TABLE `sysLocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `sysLocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysTableLastUpdated`
--

DROP TABLE IF EXISTS `sysTableLastUpdated`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysTableLastUpdated` (
  `tlu_TableName` varchar(40) NOT NULL COMMENT 'Name of table for which we are recording time of last update',
  `tlu_DateStamp` datetime NOT NULL default '0000-00-00 00:00:00' COMMENT 'Date and time of last update of table',
  `tlu_CommonObj` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Indicates tables which contain data defs required in common-obj',
  PRIMARY KEY  (`tlu_TableName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Datestamp, determines if updates since definitions loaded in';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysTableLastUpdated`
--

LOCK TABLES `sysTableLastUpdated` WRITE;
/*!40000 ALTER TABLE `sysTableLastUpdated` DISABLE KEYS */;
INSERT INTO `sysTableLastUpdated` VALUES ('defCalcFunctions','0000-00-00 00:00:00',1),('defCrosswalk','0000-00-00 00:00:00',1),('defDetailTypeGroups','2012-09-27 13:52:36',1),('defDetailTypes','2012-09-27 13:52:36',1),('defFileExtToMimetype','0000-00-00 00:00:00',1),('defLanguages','0000-00-00 00:00:00',1),('defOntologies','0000-00-00 00:00:00',1),('defRecStructure','2012-09-27 13:52:36',1),('defRecTypeGroups','2012-09-27 13:52:35',1),('defRecTypes','2012-09-27 13:52:36',1),('defRelationshipConstraints','0000-00-00 00:00:00',1),('defTerms','2012-09-27 13:52:36',1),('defTranslations','0000-00-00 00:00:00',1),('defURLPrefixes','0000-00-00 00:00:00',1),('sysDBNameCache','0000-00-00 00:00:00',1),('sysIdentification','0000-00-00 00:00:00',1),('sysUGrps','2013-09-26 15:26:03',1),('sysUsrGrpLinks','2013-04-15 07:35:47',1),('usrHyperlinkFilters','0000-00-00 00:00:00',1),('usrTags','0000-00-00 00:00:00',1);
/*!40000 ALTER TABLE `sysTableLastUpdated` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysUGrps`
--

DROP TABLE IF EXISTS `sysUGrps`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysUGrps` (
  `ugr_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'User or group ID, used wherever a user or group is to be identified',
  `ugr_Type` enum('user','workgroup','ugradclass') NOT NULL default 'user' COMMENT 'User or workgroup, special workgroup types also supported',
  `ugr_Name` varchar(63) NOT NULL COMMENT 'The unique user/login/group name, user name defaults to email address',
  `ugr_LongName` varchar(128) default NULL COMMENT 'An optional longer descriptive name for a user group',
  `ugr_Description` varchar(1000) default NULL COMMENT 'Extended description of a user group displayed on homepage',
  `ugr_Password` varchar(40) NOT NULL COMMENT 'Encrypted password string',
  `ugr_eMail` varchar(100) NOT NULL COMMENT 'Contact email address of the user/group',
  `ugr_FirstName` varchar(40) default NULL COMMENT 'Person''s first name, only for Users, not Workgroups',
  `ugr_LastName` varchar(63) default NULL COMMENT 'Person''s last name, only for Users, not Workgroups',
  `ugr_Department` varchar(120) default NULL,
  `ugr_Organisation` varchar(120) default NULL,
  `ugr_City` varchar(63) default NULL,
  `ugr_State` varchar(40) default NULL,
  `ugr_Postcode` varchar(20) default NULL,
  `ugr_Interests` varchar(255) default NULL COMMENT 'List of research interests, only for Users, not Workgroups',
  `ugr_Enabled` enum('y','n') NOT NULL default 'y' COMMENT 'Flags if user can use Heurist, normally needs authorising by admin',
  `ugr_LastLoginTime` datetime default NULL COMMENT 'Date and time of last login (but user may stay logged in)',
  `ugr_MinHyperlinkWords` tinyint(3) unsigned NOT NULL default '3' COMMENT 'Filter hyperlink strings with less than this word count on hyperlink import ',
  `ugr_LoginCount` int(10) unsigned NOT NULL default '0' COMMENT 'Number of times user haslogged in',
  `ugr_IsModelUser` tinyint(1) unsigned NOT NULL default '0' COMMENT '1 indicates model user = domain profile',
  `ugr_IncomingEmailAddresses` varchar(4000) default NULL COMMENT 'Comma-sep list of incoming email addresses from which to archive emails',
  `ugr_TargetEmailAddresses` varchar(255) default NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also sys_TargetEmailAddresses',
  `ugr_URLs` varchar(2000) default NULL COMMENT 'URL(s) of group or personal website(s), comma separated',
  `ugr_FlagJT` int(1) unsigned NOT NULL default '0' COMMENT 'Flag to enable in Jobtrack/Worktrack application',
  `ugr_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`ugr_ID`),
  UNIQUE KEY `ugr_Name` (`ugr_Name`),
  UNIQUE KEY `ugr_eMail` (`ugr_eMail`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COMMENT='Users/Groups diff. by ugr_Type. May defer to similar table i';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysUGrps`
--

LOCK TABLES `sysUGrps` WRITE;
/*!40000 ALTER TABLE `sysUGrps` DISABLE KEYS */;
INSERT INTO `sysUGrps` VALUES (0,'workgroup','Everyone','Group 0 represents all logged in users. DO NOT DELETE.',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=0','every','user',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2012-09-27 03:52:30'),(1,'workgroup','Database owners','Group 1 owns databases by default. DO NOT DELETE.',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=1','db','owners',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2012-09-27 03:52:30'),(2,'user','admin','',NULL,'fsPZnsdpMIWNQ','admin@xyz.corp','Admin','Istrater','Archaeology','University of Sydney','','','','Knowledge Management','y','2013-04-15 07:40:54',3,8,0,NULL,NULL,NULL,0,'2013-04-14 21:40:54'),(3,'workgroup','Other users','Another group, can be used eg. for guests',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=3','other','users',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2012-09-27 03:52:30'),(6,'user','guest',NULL,'','NwD.1JAiinjzE','guest@null','guest','guest','','USyd','','','','N/A','y','2013-09-26 15:26:03',3,4,0,'undefined',NULL,'',0,'2013-09-26 05:26:03');
/*!40000 ALTER TABLE `sysUGrps` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUGrps_last_insert` AFTER INSERT ON `sysUGrps` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUGrps_last_update` AFTER UPDATE ON `sysUGrps` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `sysUsrGrpLinks`
--

DROP TABLE IF EXISTS `sysUsrGrpLinks`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sysUsrGrpLinks` (
  `ugl_ID` mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key for user-to-group membership',
  `ugl_UserID` smallint(5) unsigned NOT NULL COMMENT 'The user being assigned to a group',
  `ugl_GroupID` smallint(5) unsigned NOT NULL COMMENT 'The group to which this user is being assigned',
  `ugl_Role` enum('admin','member') NOT NULL default 'member' COMMENT 'The role of this user in the group - member, admin',
  PRIMARY KEY  (`ugl_ID`),
  UNIQUE KEY `ugl_CompositeKey` (`ugl_UserID`,`ugl_GroupID`),
  KEY `ugl_GroupID` (`ugl_GroupID`),
  CONSTRAINT `fk_ugl_GroupID` FOREIGN KEY (`ugl_GroupID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ugl_UserID` FOREIGN KEY (`ugl_UserID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysUsrGrpLinks`
--

LOCK TABLES `sysUsrGrpLinks` WRITE;
/*!40000 ALTER TABLE `sysUsrGrpLinks` DISABLE KEYS */;
INSERT INTO `sysUsrGrpLinks` VALUES (1,2,1,'admin'),(2,2,3,'admin'),(5,6,3,'member');
/*!40000 ALTER TABLE `sysUsrGrpLinks` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUsrGrpLinks_last_insert` AFTER INSERT ON `sysUsrGrpLinks` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUsrGrpLinks_last_update` AFTER UPDATE ON `sysUsrGrpLinks` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks" */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `usrBookmarks`
--

DROP TABLE IF EXISTS `usrBookmarks`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrBookmarks` (
  `bkm_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key, ID for bookmarks',
  `bkm_Added` datetime NOT NULL COMMENT 'Date and time of addition of bookmark',
  `bkm_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification',
  `bkm_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'Owner of the bookmark - all bookmarks must be owned',
  `bkm_PwdReminder` varchar(250) default NULL COMMENT 'Password reminder field or short notes about access',
  `bkm_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which this bookmark applies, must be set',
  `bkm_Rating` tinyint(3) unsigned NOT NULL default '0' COMMENT 'Five point rating for interest/quality/content',
  `bkm_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Set to 1 if bookmark added by import, 0 if added by data entry',
  `bkm_ZoteroID` int(10) unsigned default NULL COMMENT 'Records your Zotero ID for this record for synchronisation with Zotero',
  PRIMARY KEY  (`bkm_ID`),
  UNIQUE KEY `bkm_RecID` (`bkm_RecID`,`bkm_UGrpID`),
  KEY `bkm_UGrpID` (`bkm_UGrpID`),
  KEY `bkm_Modified` (`bkm_Modified`),
  CONSTRAINT `fk_bkm_RecID` FOREIGN KEY (`bkm_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bkm_UGrpID` FOREIGN KEY (`bkm_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bookmark = personal data relating to a record, one for each ';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrBookmarks`
--

LOCK TABLES `usrBookmarks` WRITE;
/*!40000 ALTER TABLE `usrBookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrBookmarks` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `usrBookmarks_update` BEFORE UPDATE ON `usrBookmarks` FOR EACH ROW set NEW.bkm_Modified = now() */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@SAVE_SQL_MODE*/;

--
-- Table structure for table `usrHyperlinkFilters`
--

DROP TABLE IF EXISTS `usrHyperlinkFilters`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrHyperlinkFilters` (
  `hyf_String` varchar(63) NOT NULL COMMENT 'Hyperlink string to be ignored when encountered in hyperlink import',
  `hyf_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'User for which this string is to be ignored',
  UNIQUE KEY `hyf_CompositeKey` (`hyf_String`,`hyf_UGrpID`),
  KEY `hyf_UGrpID` (`hyf_UGrpID`),
  CONSTRAINT `fk_hyf_UGrpID` FOREIGN KEY (`hyf_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Configure hyperlink import to ignore common link strings';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrHyperlinkFilters`
--

LOCK TABLES `usrHyperlinkFilters` WRITE;
/*!40000 ALTER TABLE `usrHyperlinkFilters` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrHyperlinkFilters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrRecTagLinks`
--

DROP TABLE IF EXISTS `usrRecTagLinks`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrRecTagLinks` (
  `rtl_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary link table key, one tag linked to one record',
  `rtl_TagID` mediumint(8) unsigned NOT NULL COMMENT 'The tag being linked to the record/bookmark',
  `rtl_Order` tinyint(3) unsigned zerofill NOT NULL default '255' COMMENT 'Ordering of tags within the current record/bookmark',
  `rtl_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT '0 indicates added while editing, 1 indicates added during import',
  `rtl_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which the tag is linked, should always be set',
  PRIMARY KEY  (`rtl_ID`),
  UNIQUE KEY `rtl_composite_key` (`rtl_RecID`,`rtl_TagID`),
  KEY `rtl_TagIDKey` (`rtl_TagID`),
  CONSTRAINT `fk_rtl_RecID` FOREIGN KEY (`rtl_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rtl_TagID` FOREIGN KEY (`rtl_TagID`) REFERENCES `usrTags` (`tag_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Link table connecting tags to records';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrRecTagLinks`
--

LOCK TABLES `usrRecTagLinks` WRITE;
/*!40000 ALTER TABLE `usrRecTagLinks` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrRecTagLinks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrRecentRecords`
--

DROP TABLE IF EXISTS `usrRecentRecords`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrRecentRecords` (
  `rre_UGrpID` smallint(5) unsigned default NULL COMMENT 'ID of user who used the record',
  `rre_RecID` int(10) unsigned NOT NULL COMMENT 'ID of recently used record',
  `rre_Time` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Timestamp of use of records, notably those searched for with pointer field',
  UNIQUE KEY `rre_composite` (`rre_UGrpID`,`rre_RecID`),
  KEY `rre_RecID` (`rre_RecID`),
  CONSTRAINT `fk_rre_RecID` FOREIGN KEY (`rre_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rre_UGrpID` FOREIGN KEY (`rre_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrRecentRecords`
--

LOCK TABLES `usrRecentRecords` WRITE;
/*!40000 ALTER TABLE `usrRecentRecords` DISABLE KEYS */;
INSERT INTO `usrRecentRecords` VALUES (6,1,'2013-09-26 05:26:54');
/*!40000 ALTER TABLE `usrRecentRecords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrReminders`
--

DROP TABLE IF EXISTS `usrReminders`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrReminders` (
  `rem_ID` mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Unique reminder ID',
  `rem_RecID` int(10) unsigned NOT NULL COMMENT 'Record about which this reminder is sent, must refer to existing',
  `rem_OwnerUGrpID` smallint(5) unsigned NOT NULL COMMENT 'Owner of the reminder, the person who created it',
  `rem_ToWorkgroupID` smallint(5) unsigned default NULL COMMENT 'The workgroup to which the reminder should be sent',
  `rem_ToUserID` smallint(5) unsigned default NULL COMMENT 'The individual user to whom the reminder should be sent',
  `rem_ToEmail` varchar(255) default NULL COMMENT 'The individual email address(es) to which the reminder should be sent',
  `rem_Message` varchar(1000) default NULL COMMENT 'The message to be attached to the reminder, optional',
  `rem_StartDate` date NOT NULL default '1970-01-01' COMMENT 'The first (or only) date for sending the reminder',
  `rem_Freq` enum('once','daily','weekly','monthly','annually') NOT NULL default 'once' COMMENT 'The frequency of sending reminders',
  `rem_Nonce` varchar(31) default NULL COMMENT 'Random number hash for reminders',
  `rem_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`rem_ID`),
  KEY `rem_RecID` (`rem_RecID`),
  KEY `rem_OwnerUGrpID` (`rem_OwnerUGrpID`),
  KEY `rem_ToWorkgroupID` (`rem_ToWorkgroupID`),
  KEY `rem_ToUserID` (`rem_ToUserID`),
  CONSTRAINT `fk_rem_OwnerUGrpID` FOREIGN KEY (`rem_OwnerUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_RecID` FOREIGN KEY (`rem_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_ToUserID` FOREIGN KEY (`rem_ToUserID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_ToWorkgroupID` FOREIGN KEY (`rem_ToWorkgroupID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Reminders attached to records and recipients, with start dat';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrReminders`
--

LOCK TABLES `usrReminders` WRITE;
/*!40000 ALTER TABLE `usrReminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrReminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrRemindersBlockList`
--

DROP TABLE IF EXISTS `usrRemindersBlockList`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrRemindersBlockList` (
  `rbl_RemID` mediumint(8) unsigned NOT NULL COMMENT 'Reminder ID to be blocked',
  `rbl_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'User who does not wish to receive this reminder',
  UNIQUE KEY `rbl_composite_key` (`rbl_RemID`,`rbl_UGrpID`),
  KEY `rbl_UGrpID` (`rbl_UGrpID`),
  CONSTRAINT `fk_rbl_RemID` FOREIGN KEY (`rbl_RemID`) REFERENCES `usrReminders` (`rem_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rbl_UGrpID` FOREIGN KEY (`rbl_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Allows user to block resending of specific reminders to them';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrRemindersBlockList`
--

LOCK TABLES `usrRemindersBlockList` WRITE;
/*!40000 ALTER TABLE `usrRemindersBlockList` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrRemindersBlockList` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrSavedSearches`
--

DROP TABLE IF EXISTS `usrSavedSearches`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrSavedSearches` (
  `svs_ID` mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Saved search ID, used in publishing, primary key',
  `svs_Name` varchar(30) NOT NULL COMMENT 'The display name for this saved search',
  `svs_Added` date NOT NULL default '0000-00-00' COMMENT 'Date and time saves search added',
  `svs_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time saves search last modified',
  `svs_Query` text NOT NULL COMMENT 'The text of the saved search - added to search URL',
  `svs_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'The creator/owner or workgroup for the saved search',
  `svs_ExclusiveXSL` varchar(250) default '' COMMENT 'Name of XSL to which to lock this publish format, blank = any XSL OK',
  PRIMARY KEY  (`svs_ID`),
  KEY `svs_UGrpID` (`svs_UGrpID`),
  CONSTRAINT `fk_svs_UGrpID` FOREIGN KEY (`svs_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Saved searches for personal/usergroup use and for publishing';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrSavedSearches`
--

LOCK TABLES `usrSavedSearches` WRITE;
/*!40000 ALTER TABLE `usrSavedSearches` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrSavedSearches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrTags`
--

DROP TABLE IF EXISTS `usrTags`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `usrTags` (
  `tag_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `tag_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'User or workgroup id of tag owner',
  `tag_Text` varchar(63) NOT NULL COMMENT 'The value (text) of the tag provided by the user or workgroup administrator',
  `tag_Description` varchar(250) default NULL COMMENT 'Description of the concept to which this tag is attached, optional',
  `tag_AddedByImport` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag as to whether this tag was added by an import (1) or by editing (0)',
  `tag_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT ' Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`tag_ID`),
  UNIQUE KEY `tag_composite_key` (`tag_Text`,`tag_UGrpID`),
  KEY `tag_UGrpID` (`tag_UGrpID`),
  KEY `tag_Text` (`tag_Text`),
  CONSTRAINT `fk_tag_UGrpID` FOREIGN KEY (`tag_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Personal and user group tags (formerly keywords)';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrTags`
--

LOCK TABLES `usrTags` WRITE;
/*!40000 ALTER TABLE `usrTags` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrTags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woot_ChunkPermissions`
--

DROP TABLE IF EXISTS `woot_ChunkPermissions`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `woot_ChunkPermissions` (
  `wprm_ChunkID` int(11) NOT NULL COMMENT 'ID of chunk for which permission is specified, may be repeated',
  `wprm_UGrpID` smallint(6) NOT NULL COMMENT 'User with specified right to this chunk',
  `wprm_GroupID` smallint(6) NOT NULL COMMENT 'User groups with specified right to this chunk',
  `wprm_Type` enum('RW','RO') NOT NULL COMMENT 'Read-write or read-only permission for this chunk/user/wg',
  `wprm_CreatorID` smallint(6) NOT NULL COMMENT 'Creator of the permission (= user ID ???? <check>)',
  `wprm_Created` datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY `wprm_chunk_composite_key` (`wprm_ChunkID`,`wprm_UGrpID`,`wprm_GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Permissions value for individual woot chunks';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `woot_ChunkPermissions`
--

LOCK TABLES `woot_ChunkPermissions` WRITE;
/*!40000 ALTER TABLE `woot_ChunkPermissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `woot_ChunkPermissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woot_Chunks`
--

DROP TABLE IF EXISTS `woot_Chunks`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `woot_Chunks` (
  `chunk_ID` int(11) NOT NULL auto_increment COMMENT 'Primary ID for a version of the text chunks making up a woot entry (page)',
  `chunk_WootID` int(11) NOT NULL COMMENT 'The ID of the woot entry (page) to which this chunk belongs',
  `chunk_InsertOrder` int(11) NOT NULL COMMENT 'Order of chunk within woot.',
  `chunk_Version` int(11) NOT NULL COMMENT 'A version code for the chunk, incremented when edited',
  `chunk_IsLatest` tinyint(1) NOT NULL COMMENT 'Presumably flags whether this is the latest version of the chunk',
  `chunk_DisplayOrder` int(11) NOT NULL COMMENT 'The order number of the chunk within the woot entry (page)',
  `chunk_Text` text COMMENT 'The actual XHTML content of the chunk',
  `chunk_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the chunk',
  `chunk_OwnerID` int(11) default NULL COMMENT 'Owner/creator (user ID) of the chunk',
  `chunk_Deleted` tinyint(1) NOT NULL COMMENT 'Deletion marker for this chunk',
  `chunk_EditorID` int(11) NOT NULL COMMENT 'Editor (user ID) of the chunk - presumably the last person to edit',
  PRIMARY KEY  (`chunk_ID`),
  UNIQUE KEY `chunk_composite_key` (`chunk_WootID`,`chunk_InsertOrder`,`chunk_Version`),
  KEY `chunk_is_latest_key` (`chunk_IsLatest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `woot_Chunks`
--

LOCK TABLES `woot_Chunks` WRITE;
/*!40000 ALTER TABLE `woot_Chunks` DISABLE KEYS */;
/*!40000 ALTER TABLE `woot_Chunks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woot_RecPermissions`
--

DROP TABLE IF EXISTS `woot_RecPermissions`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `woot_RecPermissions` (
  `wrprm_WootID` int(11) NOT NULL COMMENT 'ID of the woot entry to which this permission applies, may be repeated',
  `wrprm_UGrpID` int(11) NOT NULL COMMENT 'User ID to which this permission is being granted',
  `wrprm_GroupID` int(11) NOT NULL COMMENT 'User group ID to which this permission is being granted',
  `wrprm_Type` enum('RW','RO') NOT NULL COMMENT 'Type of permission being granted - read only or read-write',
  `wrprm_CreatorID` int(11) NOT NULL COMMENT 'Creator of the permission',
  `wrprm_Created` datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY `wrprm_composite_key` (`wrprm_WootID`,`wrprm_UGrpID`,`wrprm_GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Overall permissions for the woot record (entry/page)';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `woot_RecPermissions`
--

LOCK TABLES `woot_RecPermissions` WRITE;
/*!40000 ALTER TABLE `woot_RecPermissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `woot_RecPermissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woots`
--

DROP TABLE IF EXISTS `woots`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `woots` (
  `woot_ID` int(11) NOT NULL auto_increment COMMENT 'Primary ID of a woot record/entry/page',
  `woot_Title` varchar(8191) default NULL COMMENT 'Name of the woot page, unique identifier of the woot page',
  `woot_Created` datetime default NULL COMMENT 'Date and time of creation of the woot record/entry/page',
  `woot_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the woot record/entry/page',
  `woot_Version` int(11) NOT NULL COMMENT 'Version of the woot record/entry/page, presumably incremented on edit',
  `woot_CreatorID` int(11) default NULL COMMENT 'Creator (user id) of the woot',
  PRIMARY KEY  (`woot_ID`),
  UNIQUE KEY `woot_title_key` (`woot_Title`(200))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Woot records (entries, pages) are linked to a set of XHTML c';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `woots`
--

LOCK TABLES `woots` WRITE;
/*!40000 ALTER TABLE `woots` DISABLE KEYS */;
/*!40000 ALTER TABLE `woots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'hdb_H3ExampleDB'
--
DELIMITER ;;
/*!50003 DROP FUNCTION IF EXISTS `getTemporalDateString` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `getTemporalDateString`(strDate varchar(4095)) RETURNS varchar(4095) CHARSET utf8
    DETERMINISTIC
begin
            declare temporalType char;
            declare iBegin integer;
            declare iEnd integer;
            declare dateString varchar(4095);
        
        set @iBegin := LOCATE('TYP=',strDate);
        if iBegin = 0 THEN
            RETURN strDate;
        else
            set @iBegin := @iBegin + 4;
        end if;
        set @temporalType = SUBSTRING(strDate,@iBegin,1);
        CASE @temporalType
            WHEN 's' THEN 
                begin
                    set @iBegin := INSTR(strDate,'DAT=');
                    if @iBegin = 0 THEN 
                        RETURN '';
                    else
                        set @iBegin := @iBegin + 4;
                    end if;
                    set @iEnd := LOCATE('|', strDate, @iBegin);
                    if @iEnd = 0 THEN 
                        begin
                            set @dateString =  substring(strDate,@iBegin);
                        end;
                    else    
                        begin
                            set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
                        end;
                    end if;
                end;
            WHEN 'f' THEN 
                begin
                    set @iBegin := INSTR(strDate,'DAT=');
                    if @iBegin = 0 THEN 
                        RETURN '';
                    else
                        set @iBegin := @iBegin + 4;
                    end if;
                    set @iEnd := LOCATE('|', strDate, @iBegin);
                    if @iEnd = 0 THEN 
                        begin
                            set @dateString =  substring(strDate,@iBegin);
                        end;
                    else    
                        begin
                            set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
                        end;
                    end if;
                end;
            WHEN 'c' THEN 
                begin
                    set @iBegin := INSTR(strDate,'BPD=');
                    if @iBegin = 0 THEN 
                        set @iBegin := INSTR(strDate,'BCE=');
                    end if;
                    if @iBegin = 0 THEN 
                        RETURN '';
                    else
                        set @iBegin := @iBegin + 4;
                    end if;
                    set @iEnd := LOCATE('|', strDate, @iBegin);
                    if @iEnd = 0 THEN 
                        begin
                            set @dateString =  substring(strDate,@iBegin);
                        end;
                    else    
                        begin
                            set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
                        end;
                    end if;
                end;
            WHEN 'p' THEN 
                begin
                    set @iBegin := INSTR(strDate,'TPQ=');
                    if @iBegin = 0 THEN 
                        set @iBegin := INSTR(strDate,'PDB=');
                    end if;
                    if @iBegin = 0 THEN 
                        set @iBegin := INSTR(strDate,'PDE=');
                    end if;
                    if @iBegin = 0 THEN 
                        set @iBegin := INSTR(strDate,'TAQ=');
                    end if;
                    if @iBegin = 0 THEN 
                        RETURN '';
                    else
                        set @iBegin := @iBegin + 4;
                    end if;
                    set @iEnd := LOCATE('|', strDate, @iBegin);
                    if @iEnd = 0 THEN 
                        begin
                            set @dateString =  substring(strDate,@iBegin);
                        end;
                    else    
                        begin
                            set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
                        end;
                    end if;
                end;
            ELSE
                set @dateString = strDate;
        END CASE;
        return @dateString;
    end */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP FUNCTION IF EXISTS `hhash` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `hhash`(recID int) RETURNS varchar(4095) CHARSET utf8
    READS SQL DATA
    DETERMINISTIC
begin
        declare rectype int;
        declare non_resource_fields varchar(4095);
        declare resource_fields varchar(4095);
        select rec_RecTypeID into rectype from Records where rec_ID = recID;
        select group_concat(liposuction(upper(dtl_Value)) order by dty_ID, upper(dtl_Value) separator ';')
            into non_resource_fields
            from Details, Records, defDetailTypes, defRecStructure
            where dtl_RecID=rec_ID and
                    dtl_DetailTypeID=dty_ID and
                    rec_RecTypeID=rst_RecTypeID and
                    rst_DetailTypeID=dty_ID and
                    rst_RecordMatchOrder and
                    dty_Type != "resource" and
                    rec_ID = recID;
        select group_concat(DST.rec_Hhash order by dty_ID, dty_ID, DST.rec_Hhash separator '$^')
            into resource_fields
            from Details, Records SRC, defDetailTypes, defRecStructure, Records DST
            where dtl_RecID=SRC.rec_ID and
                    dtl_DetailTypeID=dty_ID and
                    SRC.rec_RecTypeID=rst_RecTypeID and
                    rst_DetailTypeID=dty_ID and
                    rst_RequirementType = 'required' and
                    dty_Type = "resource" and
                    dtl_Value = DST.rec_ID and
                    dtl_RecID=recID;
        return concat(ifnull(rectype,'n'), ':',
        if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''),
        if(resource_fields is not null and resource_fields != '', concat('^', resource_fields, '$'), ''));
    end */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP FUNCTION IF EXISTS `NEW_LEVENSHTEIN` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `NEW_LEVENSHTEIN`(s1 VARCHAR(255) CHARACTER SET utf8, s2 VARCHAR(255) CHARACTER SET utf8) RETURNS int(11)
    DETERMINISTIC
BEGIN
    DECLARE s1_len, s2_len, i, j, c, c_temp, cost INT;
    DECLARE s1_char CHAR CHARACTER SET utf8;
    
    DECLARE cv0, cv1 VARBINARY(256);
    SET s1_len = CHAR_LENGTH(s1),
        s2_len = CHAR_LENGTH(s2),
        cv1 = 0x00,
        j = 1,
        i = 1,
        c = 0;
    IF (s1 = s2) THEN
      RETURN (0);
    ELSEIF (s1_len = 0) THEN
      RETURN (s2_len);
    ELSEIF (s2_len = 0) THEN
      RETURN (s1_len);
    END IF;
    WHILE (j <= s2_len) DO
      SET cv1 = CONCAT(cv1, CHAR(j)),
          j = j + 1;
    END WHILE;
    WHILE (i <= s1_len) DO
      SET s1_char = SUBSTRING(s1, i, 1),
          c = i,
          cv0 = CHAR(i),
          j = 1;
      WHILE (j <= s2_len) DO
        SET c = c + 1,
            cost = IF(s1_char = SUBSTRING(s2, j, 1), 0, 1);
        SET c_temp = ORD(SUBSTRING(cv1, j, 1)) + cost;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;
        SET c_temp = ORD(SUBSTRING(cv1, j+1, 1)) + 1;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;
        SET cv0 = CONCAT(cv0, CHAR(c)),
            j = j + 1;
      END WHILE;
      SET cv1 = cv0,
          i = i + 1;
    END WHILE;
   RETURN (c);
  END */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP FUNCTION IF EXISTS `NEW_LIPOSUCTION` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `NEW_LIPOSUCTION`(s VARCHAR(20480) CHARACTER SET utf8) RETURNS varchar(20480) CHARSET utf8
    DETERMINISTIC
BEGIN
    DECLARE i, len INT;
    DECLARE c CHAR CHARACTER SET utf8;
    DECLARE s2 VARCHAR(20480) CHARACTER SET utf8;
    IF (s IS NULL) THEN
       RETURN (NULL);
    END IF;
    SET i = 1,
        len = CHAR_LENGTH(s),
        s2 = '';
    WHILE (i <= len) DO
        SET c = SUBSTRING(s, i, 1);
        IF (ORD(c) > 32 && LOCATE(c, '!\"#%&\'();<=>?[\\]*+,-./:^_{|}~') = 0) THEN
          SET s2 = CONCAT(s2, c);
        END IF;
        SET i = i + 1;
    END WHILE;
    RETURN (s2);
  END */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP FUNCTION IF EXISTS `simple_hash` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `simple_hash`(recID int) RETURNS varchar(4095) CHARSET utf8
    READS SQL DATA
    DETERMINISTIC
begin
        declare rectype int;
        declare non_resource_fields varchar(4095);
        declare author_fields varchar(4095);
        select rec_RecTypeID into rectype from Records where rec_ID = recID;
        select group_concat(liposuction(upper(dtl_Value)) order by dty_ID, upper(dtl_Value) separator ';')
            into non_resource_fields
            from Details, Records, defDetailTypes, defRecStructure
            where dtl_RecID=rec_ID and
                    dtl_DetailTypeID=dty_ID and
                    rec_RecTypeID=rst_RecTypeID and
                    rst_DetailTypeID=dty_ID and
                    rst_RecordMatchOrder and
                    dty_Type != "resource" and
                    rec_ID = recID;
        return concat(ifnull(rectype,'n'), ':',
        if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''));
    end */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP PROCEDURE IF EXISTS `set_all_hhash` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 PROCEDURE `set_all_hhash`()
begin
        create temporary table t (rec_ID int);
        repeat
            begin
            truncate t;
            insert into t select rec_ID
                            from Records A
                            where A.rec_Hhash is null
                                and not exists (select *
                                                from Details, defDetailTypes, defRecStructure, Records B
                                                where dtl_RecID=A.rec_ID and
                                                        dtl_DetailTypeID=dty_ID and
                                                        dty_Type="resource" and
                                                        B.rec_ID=dtl_Value and
                                                        B.rec_Hhash is null and
                                                        rst_RecTypeID=A.rec_RecTypeID and
                                                        rst_DetailTypeID=dty_ID and
                                                        rst_RequirementType="Required");
            set @tcount := row_count();
            update Records
                set rec_Hhash = hhash(rec_ID)
                where rec_ID in (select * from t);
            end;
            until @tcount = 0
        end repeat;
        drop table t;
    end */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
DELIMITER ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-10-17  1:42:36
