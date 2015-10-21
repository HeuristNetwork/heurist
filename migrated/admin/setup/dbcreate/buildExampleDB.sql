-- buildExampleDB.sql: SQL file to create an example Heurist database as a starter/seed database
-- Note that the standard starter database is hdb_Heurist_Sandpit which is used tas a starting poitn for new
-- databsae creation and cannot be deleted through Heurist

-- ---------------------------------------------------------------------------------------------

-- The rest of this file is a MySQLDump dump of a basic Heurist_Core_Definitions database
-- which is used as a starting point for new users. Login and email addresss needs to be set as part of
-- the configuration as this will be needed by the system administrator to approve new users

-- *** IMPORTANT *** You MUST use -R or --routines so that stored fuinctions are added to the database

--  mysqldump -u... -p.... -R hdb_Heurist_Core_Definitions > hdb_Heurist_Core_Definitions.sql

--  IMPORTANT: MAKE SURE THE DATABASE IS CONFIGURED TO ALLOW REGISTRATION IN sysIdentification
--             Remove any unwanted users (sysUGrps) and unwanted user-group connections
--             (sysUGrpLinks )- only (1,2,1,admin) is required

-- ---------------------------------------------------------------------------------------------

-- INSERT BELOW FROM MySQLDump ...


-- MySQL dump 10.13  Distrib 5.1.73, for redhat-linux-gnu (x86_64)
--
-- Host: localhost    Database: hdb_H3CoreDefinitions
-- ------------------------------------------------------
-- Server version    5.1.73

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Records` (
  `rec_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'The primary record ID, also called, historically, bib_id',
  `rec_URL` varchar(2000) DEFAULT NULL COMMENT 'The primary URL pointed to by this record (particularly for Internet bookmarks)',
  `rec_Added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Date and time record added',
  `rec_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the record was modified',
  `rec_Title` varchar(1023) NOT NULL default '' COMMENT 'Composite (constructed) title of the record, used for display and search',
  `rec_ScratchPad` text COMMENT 'Scratchpad, mainly for text captured with bookmarklet',
  `rec_RecTypeID` smallint(5) unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
  `rec_AddedByUGrpID` smallint(5) unsigned DEFAULT NULL COMMENT 'ID of the user who created the record',
  `rec_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Whether added by an import (value 1) or by manual entry (value 0)',
  `rec_Popularity` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Calculated popularity rating for sorting order, set by cron job',
  `rec_FlagTemporary` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a partially created record before fully populated',
  `rec_OwnerUGrpID` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'User group which owns this record, 0 = everyone',
  `rec_NonOwnerVisibility` enum('viewable','hidden','public','pending') NOT NULL DEFAULT 'viewable' COMMENT 'Defines if record visible outside owning user group(s) or to anyone',
  `rec_URLLastVerified` datetime DEFAULT NULL COMMENT 'Last date time when URL was verified as contactable',
  `rec_URLErrorMessage` varchar(255) DEFAULT NULL COMMENT 'Error returned by URL checking script for bad/inaccessible URLs',
  `rec_URLExtensionForMimeType` varchar(10) DEFAULT NULL COMMENT 'A mime type extension for multimedia files pointed to DIRECTLY by the record URL',
  `rec_Hash` varchar(60) DEFAULT NULL COMMENT 'A composite truncated metaphones + numeric values hash of significant fields',
  PRIMARY KEY (`rec_ID`),
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Records`
--

LOCK TABLES `Records` WRITE;
/*!40000 ALTER TABLE `Records` DISABLE KEYS */;
/*!40000 ALTER TABLE `Records` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `insert_record_trigger`
    AFTER INSERT ON `Records`
    FOR EACH ROW
    begin






    insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
                                values (@logged_in_user_id, NEW.rec_ID, now());
    set @rec_id := last_insert_id(NEW.rec_ID);

        if NEW.rec_RecTypeID = 1 then

            insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,NEW.rec_ID,NEW.rec_ID);
        end if;
    end */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `update_record_trigger`
    BEFORE UPDATE ON `Records`
    FOR EACH ROW
    begin
        if @suppress_update_trigger is null then








            set @rec_version := last_insert_id();

        end if;
        if NEW.rec_URL != OLD.rec_URL OR NEW.rec_URL is null then
            set NEW.rec_URLLastVerified := NULL;
        end if;
    end */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `usrRecentRecords_updater`
    AFTER UPDATE ON `Records`
    FOR EACH ROW
    begin

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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `delete_record_trigger`
    AFTER DELETE ON `Records`
    FOR EACH ROW
    begin




        set @rec_version := last_insert_id();







        if OLD.rec_RecTypeID = 1 then
            delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
        end if;
    end */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defCalcFunctions`
--

DROP TABLE IF EXISTS `defCalcFunctions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defCalcFunctions` (
  `cfn_ID` smallint(3) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of defCalcFunctions table',
  `cfn_Domain` enum('calcfieldstring','pluginphp') NOT NULL DEFAULT 'calcfieldstring' COMMENT 'Domain of application of this function specification',
  `cfn_FunctionSpecification` text COMMENT 'A function or chain of functions, or some PHP plugin code',
  `cfn_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`cfn_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Specifications for generating calculated fields, plugins and';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defCrosswalk` (
  `crw_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `crw_SourcedbID` mediumint(8) unsigned NOT NULL COMMENT 'The Heurist reference ID of the database containing the definition being mapped',
  `crw_SourceCode` mediumint(8) unsigned NOT NULL COMMENT 'The code of the definition in the source database',
  `crw_DefType` enum('rectype','constraint','detailtype','recstructure','ontology','vocabulary','term') NOT NULL COMMENT 'The type of code being mapped between the source and this database',
  `crw_LocalCode` mediumint(8) unsigned NOT NULL COMMENT 'The corresponding code in the local database',
  `crw_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The date when this mapping added or modified',
  PRIMARY KEY (`crw_ID`),
  UNIQUE KEY `crw_composite` (`crw_SourcedbID`,`crw_DefType`,`crw_LocalCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Map the codes used in this Heurist DB to codes used in other';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defDetailTypeGroups` (
  `dtg_ID` tinyint(3) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary ID - Code for detail type groups',
  `dtg_Name` varchar(63) NOT NULL COMMENT 'Descriptive heading to be displayed for each group of details (fields)',
  `dtg_Order` tinyint(3) unsigned zerofill NOT NULL DEFAULT '002' COMMENT 'Ordering of detail type groups within pulldown lists',
  `dtg_Description` varchar(255) NOT NULL COMMENT 'General description fo this group of detail (field) types',
  `dtg_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`dtg_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8 COMMENT='Groups detail types for display in separate sections of edit';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defDetailTypeGroups`
--

LOCK TABLES `defDetailTypeGroups` WRITE;
/*!40000 ALTER TABLE `defDetailTypeGroups` DISABLE KEYS */;
INSERT INTO `defDetailTypeGroups` VALUES (1,'Common fields',000,'The commonest base field types shared across many record types','2013-09-10 04:13:51'),(8,'People / Organisations',001,'Base field types used for people and organisations','2013-09-10 04:13:51'),(99,'System',008,'Field types used by the system - generally reserved','2013-09-10 04:30:39'),(101,'Mapping / location',004,'Base field types for handling mapping and geospatial data (including image types used for mapping)','2013-09-10 04:30:39'),(105,'File Metadata',007,'File metadata used in processing  in situ file indexing and the import of FieldHelper manifests','2013-09-10 04:30:39'),(108,'Events / time',005,'Fields used for events and temporal/dating information','2013-09-10 04:30:39'),(109,'Imported',255,'This group contains all detailtypes that were imported from external databases','2014-05-21 17:57:11');
/*!40000 ALTER TABLE `defDetailTypeGroups` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defDetailTypeGroups_insert`
    AFTER INSERT ON `defDetailTypeGroups`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defDetailTypeGroups_update`
    AFTER UPDATE ON `defDetailTypeGroups`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defDetailTypeGroups_delete`
    AFTER DELETE ON `defDetailTypeGroups`
        FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defDetailTypes`
--

DROP TABLE IF EXISTS `defDetailTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defDetailTypes` (
  `dty_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Code for the detail type (field) - may vary between Heurist DBs',
  `dty_Name` varchar(255) NOT NULL COMMENT 'The canonical (standard) name of the detail type, used as default in edit form',
  `dty_Documentation` varchar(5000) DEFAULT 'Please document the nature of this detail type (field)) ...' COMMENT 'Documentation of the detail type, what it means, how defined',
  `dty_Type` enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude') NOT NULL COMMENT 'The value-type of this detail type, what sort of data is stored',
  `dty_HelpText` varchar(255) NOT NULL DEFAULT 'Please provide a short explanation for the user ...' COMMENT 'The default help text displayed to the user under the field',
  `dty_ExtendedDescription` varchar(5000) DEFAULT 'Please provide an extended description for display on rollover ...' COMMENT 'Extended text describing this detail type, for display in rollover',
  `dty_EntryMask` text COMMENT 'Data entry mask, use to control decimals ion numeric values, content of text fields etc.',
  `dty_Status` enum('reserved','approved','pending','open') NOT NULL DEFAULT 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `dty_OriginatingDBID` mediumint(8) unsigned DEFAULT NULL COMMENT 'Database where this detail type originated, 0 = locally',
  `dty_NameInOriginatingDB` varchar(255) DEFAULT NULL COMMENT 'Name used in database where this detail type originated',
  `dty_IDInOriginatingDB` smallint(5) unsigned DEFAULT NULL COMMENT 'ID used in database where this detail type originated',
  `dty_DetailTypeGroupID` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT 'The general role of this detail allowing differentiated lists of detail types',
  `dty_OrderInGroup` tinyint(3) unsigned DEFAULT '0' COMMENT 'The display order of DetailType within group, alphabetic if equal values',
  `dty_JsonTermIDTree` varchar(5000) DEFAULT NULL COMMENT 'Tree of Term IDs to show for this field (display-only header terms set in HeaderTermIDs)',
  `dty_TermIDTreeNonSelectableIDs` varchar(1000) DEFAULT NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  `dty_PtrTargetRectypeIDs` varchar(63) DEFAULT NULL COMMENT 'CSVlist of target Rectype IDs, null = any',
  `dty_FieldSetRectypeID` smallint(5) unsigned DEFAULT NULL COMMENT 'For a FieldSetMarker, the record type to be inserted as a fieldset',
  `dty_ShowInLists` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Flags if detail type is to be shown in end-user interface, 1=yes',
  `dty_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL DEFAULT 'viewable' COMMENT 'Allows restriction of visibility of a particular field in ALL record types (overrides rst_VisibleOutsideGroup)',
  `dty_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `dty_LocallyModified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY (`dty_ID`),
  UNIQUE KEY `dty_Name` (`dty_Name`),
  KEY `dty_Type` (`dty_Type`),
  KEY `dty_DetailTypeGroupID` (`dty_DetailTypeGroupID`),
  CONSTRAINT `fk_dty_DetailTypeGroupID_1` FOREIGN KEY (`dty_DetailTypeGroupID`) REFERENCES `defDetailTypeGroups` (`dtg_ID`),
  CONSTRAINT `fk_dty_DetailTypeGroupID_2` FOREIGN KEY (`dty_DetailTypeGroupID`) REFERENCES `defDetailTypeGroups` (`dtg_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8 COMMENT='The detail types (fields) which can be attached to records';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defDetailTypes`
--

LOCK TABLES `defDetailTypes` WRITE;
/*!40000 ALTER TABLE `defDetailTypes` DISABLE KEYS */;
INSERT INTO `defDetailTypes` VALUES (1,'Name / Title','Name or phrase used to identify the represented object/entity','freetext','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',NULL,'reserved',2,'Name',1,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-19 01:59:55',1),(2,'Short name','Short or familiar name, phrase or acronym used to identify the represented object/entity','freetext','Short name or acronym, use this for common shortened names or acronyms, eg. ARC for Australian Research Council','',NULL,'approved',2,'Short Name',2,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-19 01:59:23',1),(3,'Short summary','Short summary or description of the object/entity','blocktext','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',NULL,'reserved',2,'Short summary',3,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-15 01:07:35',1),(4,'Extended description','Extended description of the object/entity','blocktext','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',NULL,'approved',2,'Extended description',4,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-19 01:58:41',1),(5,'Target record pointer','Unconstrained pointer to the target linked record for a relationship.','resource','The target record in a relationship record or other record referencing another record with an unconstrained pointer','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Target record pointer',5,99,0,NULL,NULL,NULL,NULL,0,'viewable','2012-09-20 07:33:48',1),(6,'Relationship type','Term or term phrase representing the conceptual relationship between entities','relationtype','This is a special field used in relationship records, and should not be used elsewhere','This is a special field used in relationship records, and should not be used elsewhere, as it may lead to data which is not properly rendered',NULL,'reserved',2,'Relationship type',6,99,0,'{\"3001\":{\"3108\":{},\"3170\":{}},\"3110\":{\"3118\":{\"3087\":{},\"3117\":{}},\"3133\":{},\"3258\":{\"3088\":{},\"3094\":{},\"3103\":{},\"3105\":{}},\"3259\":{\"3083\":{},\"3084\":{},\"3092\":{}},\"3260\":{\"3089\":{\"3095\":{},\"3104\":{}},\"3115\":{\"3099\":{},\"3100\":{}}},\"3261\":{\"3090\":{\"3188\":{},\"3189\":{}},\"3116\":{\"3242\":{},\"3243\":{}}}},\"3113\":{\"3005\":{},\"3269\":{\"3091\":{},\"3262\":{},\"3263\":{},\"3264\":{},\"3267\":{},\"3268\":{}},\"3270\":{\"3011\":{},\"3012\":{},\"3265\":{},\"3266\":{}},\"3271\":{}},\"3288\":{\"3289\":{},\"3290\":{},\"3291\":{}}}','[\"3001\",\"3110\",\"3113\",\"3288\"]',NULL,NULL,0,'viewable','2013-02-27 22:57:46',1),(7,'Source record pointer','Unconstrained pointer to the source linked record for a relationship.','resource','Pointer to the record which is being linked to a target record (often by a relationship record)','Fundamentally important detail used in record relationships that identifies one of the partner records in a relationship. Must be used in conjunction with relationship type and target record pointer to create a valid relationship',NULL,'reserved',2,'Source record pointer',7,99,0,NULL,NULL,NULL,NULL,0,'viewable','2012-09-20 07:34:08',1),(8,'Comments on relationship','Record pointer constrained to commentary records','resource','A pointer to a commentary/interpretation record (for spatial, temporal or general information)','This detail acknowledges that a given piece of data may have many commentaries or interpretations.',NULL,'reserved',2,'Pointer to commentary',8,99,0,NULL,NULL,'8',NULL,0,'viewable','2012-10-19 00:52:59',1),(9,'Date','Generic date','date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','',NULL,'reserved',2,'Date',9,108,0,NULL,NULL,NULL,NULL,1,'viewable','2013-09-10 04:28:39',1),(10,'Start date','Date representing the beginning of real/conceptual object/entity ','date','Start Date (may also include time and/or have fuzzy limits)','Note that this detail describes a temporal object and can be used to describe dates that have a level of uncertainty',NULL,'reserved',2,'Start date',10,108,0,NULL,NULL,NULL,NULL,1,'viewable','2013-09-10 04:28:42',1),(11,'End date','Date representing the ending of real/conceptual object/entity','date','End Date (may also include time and/or have fuzzy limits)','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'End date',11,108,0,NULL,NULL,NULL,NULL,1,'viewable','2013-09-10 04:28:45',1),(12,'Heurist Query String','Heurist Query String','freetext','A string representing a valid Heurist query.','Please provide an extended description for display on rollover ...',NULL,'reserved',2,'Query String',12,99,0,NULL,NULL,NULL,NULL,0,'viewable','2013-09-10 04:25:28',1),(13,'Record pointer (unconstrained) >','General record pointer','resource','A pointer to any type of record, use apprporiately constrained pointers for preference','',NULL,'approved',2,'Record pointer',13,1,0,NULL,NULL,NULL,NULL,1,'viewable','2013-02-27 21:45:42',1),(14,'Transcriber >','Record pointer constrained to person or organisation records','resource','Pointer to the person who transcribes or summarises interpretation information ','',NULL,'approved',2,'Transcriber of interpretation',14,8,0,NULL,NULL,'10',NULL,1,'viewable','2012-12-11 09:35:40',1),(15,'Author or Creator >','Record pointer constrained to person or organisation records','resource','The person or organisation who created the record (author, organisation, artist etc.)','May include authors, artists, organisations that sponsored a resource etc.',NULL,'reserved',2,'Creator - author, artist etc.',15,8,0,NULL,NULL,'4,11',NULL,1,'viewable','2014-07-22 06:02:28',1),(16,'Person >','Record pointer constrained to person records','resource','Pointer to a person record (use Author/Creator field type where organisations as well as people may apply)','',NULL,'reserved',2,'Pointer to Person',16,8,0,NULL,NULL,'11',NULL,1,'viewable','2014-07-22 05:46:37',1),(17,'Contact details or URL','Please document the nature of this detail type (field)) ...','freetext','Contact details, either as text or a URL to eg. a phonebook record or search','',NULL,'approved',2,'Contact details or URL',17,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-06-18 23:04:02',1),(18,'Given names','Name or name list additionally given to the represented object/entity ','freetext','Given names','',NULL,'reserved',2,'Given names',18,8,0,NULL,NULL,NULL,NULL,1,'viewable','2013-02-27 21:48:41',1),(19,'Honorific','Prefix for the object\'s name representing status and/or achievement','enum','Title or honorific (Dr, Mrs, Ms, Professor, Sir etc.)','',NULL,'approved',2,'Honorific',19,8,0,'507',NULL,NULL,NULL,1,'viewable','2013-02-27 22:49:23',1),(20,'Sex','Gender type','enum','Sex of a person (or other living creature)','',NULL,'approved',2,'Gender',20,8,0,'513',NULL,NULL,NULL,1,'viewable','2013-02-27 22:49:39',1),(21,'Organisation >','Record pointer constrained to organisation records','resource','A legally defined organisation or group of people identifying as an organisation','',NULL,'approved',2,'Pointer to Organisation',21,8,0,NULL,NULL,'4',NULL,1,'viewable','2012-10-19 02:06:48',1),(22,'Organisation type','Term categorising the organisation object type','enum','Organisation type','',NULL,'open',2,'Organisation type',22,8,0,'497','',NULL,NULL,1,'viewable','2013-02-27 22:49:48',1),(23,'Email address','Generic email address associated with the object/entity','freetext','Email address','',NULL,'approved',2,'Email address',23,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-09-20 01:45:27',1),(24,'Email of sender','Email address associated with the sender of an email object/entity','freetext','Email of sender, used by the Email record type to store information about an imported email','Used in correspondence records to establish the email of the sender',NULL,'reserved',2,'Email of sender',24,99,0,NULL,NULL,NULL,NULL,0,'viewable','2013-02-27 21:46:55',1),(25,'Email of recipients','Email address(es) associated with the recipient(s) of an email object/entity','freetext','Email of recipients, used by the Email record type to store information about an imported email','Used in correspondence records to establish who the email is being sent to',NULL,'reserved',2,'Email of recipients',25,99,0,NULL,NULL,NULL,NULL,0,'viewable','2013-02-27 21:47:10',1),(26,'Country','Term identifying the country associated with the object/entity','enum','Country. The list is prepopulated, but can be extended, and includes 2 and 3 letter country codes','',NULL,'reserved',2,'Country',26,101,0,'509',NULL,NULL,NULL,1,'viewable','2013-02-27 22:50:04',1),(27,'Place name','Term  or phrase identifying the location associated with the object/entity','freetext','The name of a place or location, eg. city, town, suburb, village, locality','',NULL,'reserved',2,'Place name',27,101,0,NULL,NULL,NULL,NULL,1,'viewable','2013-02-27 21:48:13',1),(28,'Mappable location','Geospatial shape associated with the object/entity','geo','A geospatial object providing a mappable location for the record - can be used for any type of record with spatial location','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage',NULL,'reserved',2,'Geospatial object',28,101,0,NULL,NULL,NULL,NULL,1,'viewable','2012-12-11 09:34:10',1),(35,'Copyright information','Please document the nature of this detail type (field)) ...','blocktext','Copyright statement or a URI leading to a copyright statement. Consider using Creative Commons categories.','Can be used with Creative Commons license types',NULL,'approved',2,'Copyright information',35,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-19 02:10:34',1),(36,'Original ID','Please document the nature of this detail type (field)) ...','freetext','The original ID of the record in a source database from which these data were imported','Used to retain a reference to an original data set where a given record is imported from. This detail type is essential when imports complex relational data into Heurist',NULL,'reserved',2,'Original ID',36,1,0,NULL,NULL,NULL,NULL,1,'viewable','2013-09-10 04:26:38',1),(37,'Unique public identifier','Please document the nature of this detail type (field)) ...','freetext','A public identifier such as the NLA party identifier (Australia) or other national/international system','',NULL,'approved',2,'Unique public identifier',37,8,0,NULL,NULL,NULL,NULL,1,'viewable','2012-06-18 23:05:40',1),(38,'Related file','Please document the nature of this detail type (field)) ...','file','An uploaded file or a reference to a file through a URI','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible',NULL,'reserved',2,'File resource',38,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-19 00:55:43',1),(39,'Thumbnail image','Please document the nature of this detail type (field)) ...','file','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',NULL,'reserved',2,'Thumbnail image',39,1,0,NULL,NULL,NULL,NULL,1,'viewable','2012-10-15 01:08:22',1),(40,'Heurist Filter String','Heurist Filter String','freetext','A string representing a valid Heurist filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]}','Allows the saving of rules that work on a result set. Rather than just saying \"give me all the related records of record x\" one could say \"give me all the related people of record x\" and save this in a detail for reuse.',NULL,'reserved',2,'Filter string',40,99,0,NULL,NULL,NULL,NULL,0,'viewable','2013-09-10 04:25:30',1),(41,'File Type','Please document the nature of this detail type (field)) ...','enum','Term identifying the file format','',NULL,'reserved',2,'File Type',41,105,0,'3272','',NULL,NULL,0,'viewable','2013-02-27 22:48:36',1),(48,'Heurist Layout String','Please document the nature of this detail type (field)) ...','freetext','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Used to pre-configure the Heurist environment. Usually accompanying a query and/or a filter. The combination of these details enables the full current Heurist environment to be stored and/or sent to other users',NULL,'reserved',2,'Heurist Layout String',48,99,0,NULL,NULL,NULL,NULL,0,'viewable','2013-09-10 04:25:47',1),(57,'Header 1','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','',NULL,'open',2,'Header 1',57,99,0,NULL,NULL,NULL,NULL,0,'viewable','2012-11-12 04:24:58',0),(58,'Header 2','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','',NULL,'open',2,'Header 2',58,99,0,NULL,NULL,NULL,NULL,0,'viewable','2012-11-12 04:24:58',0),(61,'Multimedia >','Please document the nature of this detail type (field)) ...','resource','Points to a multimedia record - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','',NULL,'reserved',2,'Multimedia >',61,1,0,'','','5',NULL,1,'viewable','2012-11-12 04:24:59',0),(62,'File name','Please document the nature of this detail type (field)) ...','freetext','The name of the file, excluding path, including extension','',NULL,'reserved',2,'File name',62,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:13',1),(63,'File path','Please document the nature of this detail type (field)) ...','freetext','The full path to the file','',NULL,'reserved',2,'File path',63,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:14',1),(64,'File extension','Please document the nature of this detail type (field)) ...','freetext','The (normally three letter) file extension','',NULL,'reserved',2,'File extension',64,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:15',1),(65,'File recording device','Please document the nature of this detail type (field)) ...','freetext','The device used to collect files, may be generic \'image\' or more specific eg. camera make and model','',NULL,'reserved',2,'File recording device',65,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:15',1),(66,'File duration secs','Please document the nature of this detail type (field)) ...','float','The duration of a media object, such as sound or video, in seconds','',NULL,'reserved',2,'File duration secs',66,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:16',1),(67,'File size bytes','Please document the nature of this detail type (field)) ...','float','The size of the file in bytes','',NULL,'reserved',2,'File size bytes',67,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:16',1),(68,'MD5 checksum','Please document the nature of this detail type (field)) ...','freetext','The calculated MD5 checksum for the file','',NULL,'reserved',2,'MD5 checksum',68,105,0,'','','',NULL,0,'viewable','2013-02-27 06:42:17',1),(69,'Header 3','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','',NULL,'open',2,'Header 3',69,99,0,NULL,NULL,NULL,NULL,0,'viewable','2012-11-12 04:24:59',0),(70,'Header 4','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','',NULL,'open',2,'Header 4',70,99,0,'','','',NULL,0,'viewable','2012-11-12 04:24:59',0),(71,'Header 5','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','',NULL,'open',2,'Header 5',71,99,0,'','','',NULL,0,'viewable','2012-11-12 04:24:59',0),(72,'Parent record >','Please document the nature of this detail type (field)) ...','resource','Pointer which can be created automatically when a record is created as a child through as pointer field','',NULL,'reserved',NULL,NULL,NULL,99,0,'','','',NULL,1,'viewable','2013-09-10 04:38:31',0),(76,'HEADING 1','Please document the nature of this detail type (field)) ...','separator','Headings serve to break the data entry form up into sections','','','open',3,'HEADING 1',1054,99,0,'','','',0,1,'viewable','2013-09-10 04:20:56',1),(77,'URL','Please document the nature of this detail type (field)) ...','freetext','The URL of a resource or web page','','','open',3,'URL',1058,1,0,'','','',0,1,'viewable','2013-09-10 04:18:27',1),(79,'Event type','','enum','Event type','','','open',3,'Event type',1012,108,0,'3305','','',0,1,'viewable','2014-02-28 02:01:16',1),(80,'HEADING 2','Please document the nature of this detail type (field)) ...','separator','Headings serve to break the data entry form up into sections','','','open',3,'HEADING 2',1055,99,0,'','','',0,1,'viewable','2013-09-10 04:20:59',1),(81,'HEADING 3','Please document the nature of this detail type (field)) ...','separator','Headings serve to break the data entry form up into sections','','','open',3,'HEADING 3',1056,99,0,'','','',0,1,'viewable','2013-09-10 04:21:01',1),(105,'Scale','Please document the nature of this detail type (field)) ...','freetext','Scale of map','The scale of the map as expressed on map sheet -determine by measurement if required','','open',3,'Scale',1039,101,0,'','','',0,1,'viewable','2013-09-10 04:16:28',1),(110,'Duration','','freetext','ISO Duration 1Y2M4D format','','','open',3,'Duration',1008,108,0,'','','',0,1,'viewable','2013-09-10 04:28:54',1),(113,'Start commentary >','','resource','A pointer to an interpretation record which provides commentary about the start dating of an event','','','open',3,'Pointer to start commentary',1020,108,0,'','','8',0,1,'viewable','2013-09-10 04:37:26',1),(114,'End commentary >','','resource','A pointer to an interpretation record which provides commentary about the end dating of an event','','','open',3,'Pointer to end commentary',1021,108,0,'','','8',0,1,'viewable','2013-09-10 04:37:55',1),(115,'Duration commentary >','','resource','A pointer to an interpretation record which provides commentary about the duration dating of an event','','','open',3,'Pointer to duration commentary',1022,108,0,'','','8',0,1,'viewable','2013-09-10 04:37:41',1),(116,'Spatial commentary >','','resource','A pointer to an interpretation record which provides commentary about the spatial footprint of an event','','','open',3,'Pointer to spatial commentary',1023,108,0,'','','8',0,1,'viewable','2013-09-10 04:38:09',1),(117,'KML as text','','blocktext','A block of KML text which can be rendered in maps ','','','open',3,'KML',1036,101,0,'','','',0,1,'viewable','2013-09-10 04:17:07',1),(119,'Mime Type','Please document the nature of this detail type (field)) ...','enum','Mime Type','Please provide an extended description for display on rollover ...','','open',2,'Mime Type',29,105,0,'{\"3329\":{},\"3330\":{},\"3331\":{},\"3332\":{},\"3333\":{},\"3334\":{},\"3335\":{},\"3336\":{},\"3337\":{}}','','',0,0,'viewable','2013-09-10 04:24:35',1),(120,'Tiled image type','Please document the nature of this detail type (field)) ...','enum','The type (map or image) of the tiled image.','Please provide an extended description for display on rollover ...','','open',2,'Map image layer type',30,101,0,'{\"3339\":{},\"3340\":{}}','','',0,1,'viewable','2013-09-10 04:17:13',1),(121,'Image tiling schema','Please document the nature of this detail type (field)) ...','enum','Image tiling schema','Please provide an extended description for display on rollover ...','','open',2,'Map image layer tiling schema',31,101,0,'{\"3342\":{},\"3343\":{},\"3344\":{},\"3345\":{}}','','',0,1,'viewable','2013-09-10 04:17:16',1),(122,'Minimum zoom level','Please document the nature of this detail type (field)) ...','integer','Minimum zoom level','Please provide an extended description for display on rollover ...','','open',2,'Minimum zoom level',32,101,0,'','','',0,1,'viewable','2013-09-10 04:17:20',1),(123,'Maximum zoom level','Please document the nature of this detail type (field)) ...','integer','Maximum zoom level','Please provide an extended description for display on rollover ...','','open',2,'Maximum zoom level',33,101,0,'','','',0,1,'viewable','2013-09-10 04:17:23',1),(124,'Service URL','Please document the nature of this detail type (field)) ...','freetext','Service URL','Please provide an extended description for display on rollover ...','','open',2,'Service URL',34,101,0,'','','',0,0,'viewable','2013-09-10 04:25:11',1),(125,'Tiled image >','','resource','Tiled image reference','','','open',3,'Map image layer reference',1043,101,0,'','','',0,1,'viewable','2014-02-28 01:54:51',1),(126,'KML File','','file','KML File','','','open',3,'KML File',1044,101,0,'','','',0,1,'viewable','2013-09-10 04:17:30',1),(131,'HEADING 4','Please document the nature of this detail type (field)) ...','separator','Headings serve to break the data entry form up into sections','','','open',3,'HEADING 4',1060,99,0,'','','',0,1,'viewable','2013-09-10 04:21:06',1),(132,'Title - alternate','','freetext','Title - alternate','','','open',3,'Title - alternate',1009,109,0,'','','',0,1,'viewable','2014-05-21 17:57:04',0),(133,'Place type','Please document the nature of this detail type (field)) ...','enum','The type or category of place','','','open',3,'Place type',1068,109,0,'5039','','',0,1,'viewable','2014-05-21 17:57:04',0),(134,'Location (place)','Please document the nature of this detail type (field)) ...','resource','Place where the oprganisation is located (singular, or head office/main location)','',NULL,'open',NULL,NULL,NULL,1,0,'','','12',NULL,1,'viewable','2014-05-21 17:59:31',0);
/*!40000 ALTER TABLE `defDetailTypes` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defDetailTypes_last_insert`
    AFTER INSERT ON `defDetailTypes`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defDetailTypes_last_update`
    AFTER UPDATE ON `defDetailTypes`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defDetailTypes_delete`
    AFTER DELETE ON `defDetailTypes`
    FOR EACH ROW
            update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defFileExtToMimetype`
--

DROP TABLE IF EXISTS `defFileExtToMimetype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defFileExtToMimetype` (
  `fxm_Extension` varchar(10) NOT NULL COMMENT 'The file extension, indicates mimetype, icon and some beahviours',
  `fxm_MimeType` varchar(100) NOT NULL COMMENT 'The standard mime type string',
  `fxm_OpenNewWindow` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag if a new window should be opened to display this mimetype',
  `fxm_IconFileName` varchar(31) DEFAULT NULL COMMENT 'Filename of the icon file for this mimetype (shared by several)',
  `fxm_FiletypeName` varchar(31) DEFAULT NULL COMMENT 'A textual name for the file type represented by the extension',
  `fxm_ImagePlaceholder` varchar(63) DEFAULT NULL COMMENT 'Thumbnail size representation for display, generate from fxm_FiletypeName',
  `fxm_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`fxm_Extension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Converts extensions to mimetypes and provides icons and mime';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defFileExtToMimetype`
--

LOCK TABLES `defFileExtToMimetype` WRITE;
/*!40000 ALTER TABLE `defFileExtToMimetype` DISABLE KEYS */;
INSERT INTO `defFileExtToMimetype` VALUES ('ai','application/postscript',0,NULL,'Adobe Illustrator Drawing',NULL,'2012-02-29 02:23:40'),('aif','audio/x-aiff',0,'movie.gif','AIFF audio',NULL,'0000-00-00 00:00:00'),('aifc','audio/x-aiff',0,'movie.gif','AIFF audio',NULL,'0000-00-00 00:00:00'),('aiff','audio/x-aiff',0,'movie.gif','AIFF audio',NULL,'0000-00-00 00:00:00'),('asc','text/plain',1,'txt.gif','Plain text',NULL,'0000-00-00 00:00:00'),('au','audio/basic',0,'movie.gif','AU audio',NULL,'0000-00-00 00:00:00'),('avi','video/x-msvideo',0,'movie.gif','AVI video',NULL,'0000-00-00 00:00:00'),('bcpio','application/x-bcpio',0,NULL,'Binary CPIO Archive',NULL,'2012-02-29 02:24:43'),('bin','application/octet-stream',0,NULL,'BinHex',NULL,'0000-00-00 00:00:00'),('bmp','image/bmp',1,'image.gif','BMP image',NULL,'0000-00-00 00:00:00'),('c','text/plain',0,NULL,'C Source Code',NULL,'2012-02-29 02:25:35'),('cda','application/x-cdf',0,NULL,'CD Audio Track',NULL,'2012-02-29 02:25:38'),('cdf','application/octet-stream',0,NULL,'Affymetrix Chip Description Fil',NULL,'2012-02-29 02:26:21'),('cgm','image/cgm',0,'image.gif','Computer Graphics Metafile',NULL,'2012-02-29 02:27:10'),('class','application/octet-stream',0,NULL,'Java Class',NULL,'2012-02-29 02:27:27'),('cpio','application/x-cpio',0,NULL,'CPIO Archive',NULL,'2012-02-29 02:27:54'),('cpt','image/x-cpt',0,NULL,'Corel Photo-Paint Image',NULL,'2012-02-29 02:28:27'),('csh','application/x-photoshop',0,NULL,'Corel Photo-Paint Image',NULL,'2012-02-29 02:29:15'),('css','text/css',1,NULL,'Cascading Style Sheets',NULL,'2012-02-29 02:29:39'),('csv','text/comma-separated-values',0,NULL,'Comma-Separated Values',NULL,'2012-02-29 02:29:58'),('dat','application/octet-stream',0,NULL,'General Data File',NULL,'2012-02-29 02:30:36'),('db','application/octet-stream',0,NULL,'General Database File',NULL,'2012-02-29 02:30:51'),('dcr','application/x-director',0,NULL,'Delphi Component Resource',NULL,'2012-02-29 02:31:14'),('dir','application/x-director',0,NULL,'Macromedia Director Movie',NULL,'2012-02-29 02:31:41'),('djvu','image/vnd.djvu',1,'image.gif','DjVu Scanned Image',NULL,'2012-02-29 02:32:29'),('dll','application/octet-stream',0,NULL,'Windows system',NULL,'0000-00-00 00:00:00'),('dmg','application/octet-stream',0,NULL,'Mac OS X Disk Image',NULL,'2012-02-29 02:32:52'),('dms','application/octet-stream',0,NULL,'Amiga Diskmasher Disk Image',NULL,'2012-02-29 02:33:16'),('doc','application/msword',0,'doc.gif','Microsoft Word Document',NULL,'2012-02-29 02:08:39'),('docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',0,'docx.gif','Microsoft Word Document',NULL,'2012-02-29 02:09:00'),('dot','application/msword',0,'dot.gif','Microsoft Word Document Templat',NULL,'2012-02-29 02:09:29'),('dtd','application/xml-dtd',1,NULL,'Document Type Definition',NULL,'2012-02-29 02:34:00'),('dvi','application/x-dvi',0,NULL,'Device Independent File',NULL,'2012-02-29 02:34:29'),('dxr','application/vnd.dxr',0,NULL,'Macromedia Director Protected M',NULL,'2012-02-29 02:35:11'),('eml','message/rfc822',0,NULL,'Microsoft Outlook Express E-Mai',NULL,'2012-02-29 02:35:28'),('enz','application/x-endnote-connect',0,NULL,'Endnote',NULL,'2012-02-29 02:36:00'),('eps','image/x-eps',0,NULL,'Encapsulated PostScript Image',NULL,'2012-02-29 02:36:34'),('etx','text/x-setext',0,NULL,'Setext',NULL,'2012-02-29 02:37:58'),('exe','application/octet-stream',0,NULL,'Windows executable',NULL,'0000-00-00 00:00:00'),('flv','video/x-flv',0,NULL,'Flash Video File',NULL,'2012-02-29 02:38:40'),('gif','image/gif',1,'image.gif','GIF image',NULL,'0000-00-00 00:00:00'),('gra','application/x-msgraph',0,NULL,'Microsoft Graph Chart',NULL,'2012-02-29 02:39:08'),('gram','application/srgs',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('grxml','application/srgs+xml',1,NULL,NULL,NULL,'0000-00-00 00:00:00'),('gtar','application/x-gtar',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('hdf','application/x-hdf',0,NULL,'Hierarchical Data Format File',NULL,'2012-02-29 02:41:18'),('hqx','application/mac-binhex40',0,NULL,'BinHex compressed',NULL,'0000-00-00 00:00:00'),('htm','text/html',1,'html.gif','HTML source',NULL,'0000-00-00 00:00:00'),('html','text/html',1,'html.gif','HTML source',NULL,'0000-00-00 00:00:00'),('ice','x-conference/x-cooltalk',0,NULL,'ICEOWS Archive',NULL,'2012-02-29 02:42:32'),('ico','image/x-icon',0,'image.gif','Windows Icon',NULL,'2012-02-29 02:44:06'),('ics','text/calendar',0,NULL,'Swiftview Imaging Command Set',NULL,'2012-02-29 02:44:47'),('ief','image/ief',0,'image.gif',NULL,NULL,'0000-00-00 00:00:00'),('ifb','text/calendar',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('iges','application/iges',0,NULL,'IGES Drawing',NULL,'2012-02-29 02:46:33'),('igs','application/iges',0,NULL,'IGES Drawing',NULL,'2012-02-29 02:46:39'),('ini','text/plain',0,NULL,'Initialization File',NULL,'2012-02-29 02:47:08'),('jar','application/java-archive',0,NULL,'Java Archive',NULL,'2012-02-29 02:47:24'),('jpe','image/jpeg',1,'image.gif','JPEG image',NULL,'0000-00-00 00:00:00'),('jpeg','image/jpeg',1,'image.gif','JPEG image',NULL,'0000-00-00 00:00:00'),('jpg','image/jpeg',1,'image.gif','JPEG image',NULL,'0000-00-00 00:00:00'),('js','application/x-javascript',0,NULL,'JavaScript',NULL,'0000-00-00 00:00:00'),('kar','audio/x-midi',0,'movie.gif','MIDI Karaoke Song',NULL,'2012-02-29 02:47:54'),('kml','application/vnd.google-earth.kml+xml',0,NULL,'Keyhole Markup Language File',NULL,'2012-02-29 02:48:40'),('kmz','application/vnd.google-earth.kmz',0,NULL,'Compressed KML File',NULL,'2012-02-29 02:48:43'),('latex','application/x-latex',0,NULL,'LaTeX source',NULL,'0000-00-00 00:00:00'),('lha','application/octet-stream',0,NULL,'LHA compressed',NULL,'0000-00-00 00:00:00'),('lnk','application/x-ms-shortcut',0,NULL,'Windows Shortcut',NULL,'2012-02-29 02:49:20'),('lzh','application/octet-stream',0,NULL,'LZH compressed',NULL,'0000-00-00 00:00:00'),('m3u','audio/x-mpegurl',0,'movie.gif','M3U Playlist File',NULL,'2012-02-29 02:50:01'),('m4u','video/vnd.mpegurl',0,'movie.gif','M4U Playlist File',NULL,'2012-02-29 02:50:22'),('man','application/x-troff-man',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('mathml','application/mathml+xml',1,NULL,'MathML',NULL,'0000-00-00 00:00:00'),('me','application/x-troff-me',0,NULL,'Readme Text',NULL,'2012-02-29 02:51:18'),('mesh','model/mesh',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('mid','audio/midi',0,'movie.gif','MIDI audio',NULL,'0000-00-00 00:00:00'),('midi','audio/midi',0,'movie.gif','MIDI audio',NULL,'0000-00-00 00:00:00'),('mif','application/vnd.mif',0,NULL,'Adobe FrameMaker Interchange Fo',NULL,'2012-02-29 02:52:11'),('mov','video/quicktime',0,'movie.gif','QuickTime Movie',NULL,'2012-02-29 02:52:45'),('movie','video/x-sgi-movie',0,'movie.gif','QuickTime Movie',NULL,'2012-02-29 02:52:48'),('mp2','audio/mpeg',0,'movie.gif','MPEG audio',NULL,'0000-00-00 00:00:00'),('mp3','audio/mpeg',0,'movie.gif','MP3 audio',NULL,'0000-00-00 00:00:00'),('mpe','video/mpeg',0,'movie.gif','MPEG video',NULL,'0000-00-00 00:00:00'),('mpeg','video/mpeg',0,'movie.gif','MPEG video',NULL,'0000-00-00 00:00:00'),('mpg','video/mpeg',0,'movie.gif','MPEG video',NULL,'0000-00-00 00:00:00'),('mpga','audio/mpeg',0,'movie.gif','MPEG audio',NULL,'0000-00-00 00:00:00'),('ms','application/x-troff-ms',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('msh','model/mesh',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('mxd','application/octet-stream',0,NULL,'ArcMAP Document',NULL,'2012-02-29 02:54:06'),('mxu','video/vnd.mpegurl',0,'movie.gif',NULL,NULL,'0000-00-00 00:00:00'),('nc','application/x-netcdf',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('oda','application/oda',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('odt','application/vnd.oasis.opendocument.text',0,NULL,'AIBO ODA File',NULL,'2012-02-29 02:55:23'),('ogg','application/ogg',0,NULL,'Ogg Vorbis',NULL,'0000-00-00 00:00:00'),('one','application/onenote',0,NULL,'Microsoft OneNote Document',NULL,'2012-02-29 02:08:15'),('pbm','image/x-portable-bitmap',1,'image.gif',NULL,NULL,'0000-00-00 00:00:00'),('pdb','chemical/x-pdb',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('pdf','application/pdf',1,'pdf.gif','Adobe Acrobat',NULL,'0000-00-00 00:00:00'),('pgm','image/x-portable-graymap',1,'image.gif','Portable Bitmap Image',NULL,'2012-02-29 02:55:41'),('pgn','application/x-chess-pgn',0,NULL,'Portable Graymap Image',NULL,'2012-02-29 02:56:19'),('pl','application/x-perl',0,NULL,'Perl Script',NULL,'2012-02-29 02:14:32'),('png','image/png',1,'image.gif','PNG image',NULL,'0000-00-00 00:00:00'),('pnm','image/x-portable-anymap',1,'image.gif','Portable Anymap Image',NULL,'2012-02-29 02:57:09'),('ppm','image/x-portable-pixmap',1,'image.gif','Portable Pixmap Image',NULL,'2012-02-29 02:57:11'),('pps','application/mspowerpoint',0,NULL,'Microsoft PowerPoint Slideshow',NULL,'2012-02-29 02:14:16'),('ppt','application/vnd.ms-powerpoint',0,NULL,'Microsoft PowerPoint Presentati',NULL,'2012-02-29 02:11:56'),('pptx','application/vnd.openxmlformats',0,NULL,'Microsoft PowerPoint Presentati',NULL,'2012-02-29 02:12:17'),('ps','application/postscript',0,NULL,'PostScript',NULL,'0000-00-00 00:00:00'),('psd','image/vnd.adobe.photoshop',0,NULL,'Adobe Photoshop Image',NULL,'2012-02-29 02:15:03'),('qt','video/quicktime',0,'movie.gif','QuickTime video',NULL,'0000-00-00 00:00:00'),('ra','audio/x-pn-realaudio',0,'movie.gif','RealAudio',NULL,'0000-00-00 00:00:00'),('ram','audio/x-pn-realaudio',0,'movie.gif','RealAudio',NULL,'0000-00-00 00:00:00'),('ras','image/x-ras',1,'image.gif','Sun Raster Image',NULL,'2012-02-29 02:57:49'),('rdf','application/rdf+xml',1,NULL,'Mozilla extension',NULL,'0000-00-00 00:00:00'),('rgb','image/x-rgb',0,'image.gif',NULL,NULL,'0000-00-00 00:00:00'),('rm','application/vnd.rn-realmedia',0,NULL,'RealAudio',NULL,'0000-00-00 00:00:00'),('rtf','text/rtf',1,NULL,'Rich text',NULL,'0000-00-00 00:00:00'),('rtx','text/richtext',1,NULL,NULL,NULL,'0000-00-00 00:00:00'),('sgm','text/sgml',1,NULL,'Standard Generalized Markup Lan',NULL,'2012-02-29 02:59:31'),('sgml','text/sgml',1,NULL,'Standard Generalized Markup Lan',NULL,'2012-02-29 02:59:34'),('sh','application/x-sh',0,NULL,'Unix Shell Script',NULL,'2012-02-29 03:00:18'),('shar','application/x-shar',0,NULL,'Unix Shell Archive',NULL,'2012-02-29 03:00:22'),('shp','application/octet-stream',0,NULL,'ArcView Shape File',NULL,'2012-02-29 02:15:59'),('shs','application/octet-stream',0,NULL,'Shell Scrap File',NULL,'2012-02-29 02:16:10'),('silo','model/mesh',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('sit','application/x-stuffit',0,NULL,'StuffIt compressed',NULL,'0000-00-00 00:00:00'),('skd','application/x-koan',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('skm','video/x-skm',0,NULL,'SKM Video',NULL,'2012-02-29 03:01:39'),('skp','application/vnd.sketchup.skp',0,NULL,'SketchUp Document',NULL,'2012-02-29 03:02:04'),('skt','application/x-koan',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('smi','application/x-smil',0,NULL,'Synchronized Multimedia Integra',NULL,'2012-02-29 03:03:06'),('smil','application/x-smil',0,NULL,'Synchronized Multimedia Integra',NULL,'2012-02-29 03:03:18'),('snd','audio/basic',0,'movie.gif','SND audio',NULL,'0000-00-00 00:00:00'),('so','application/octet-stream',0,NULL,'Unix Shared Library',NULL,'2012-02-29 03:05:38'),('spl','application/x-futuresplash',0,NULL,'FutureSplash Animator Document',NULL,'2012-02-29 03:05:41'),('sql','text/plain',0,NULL,'SQL Script',NULL,'2012-02-29 02:16:40'),('src','text/plain',0,NULL,'General Source Code File',NULL,'2012-02-29 03:06:17'),('sv4cpio','application/x-sv4cpio',0,NULL,'System V Release 4 CPIO Archive',NULL,'2012-02-29 03:06:45'),('sv4crc','application/x-sv4crc',0,NULL,'System V Release 4 CPIO Checksu',NULL,'2012-02-29 03:07:14'),('svg','image/svg+xml',1,'image.gif','SVG graphics',NULL,'0000-00-00 00:00:00'),('swf','application/x-shockwave-flash',0,NULL,'Adobe Shockwave',NULL,'0000-00-00 00:00:00'),('t','application/x-troff',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('tar','application/x-tar',0,NULL,'TAR archive',NULL,'0000-00-00 00:00:00'),('tcl','text/x-tcl',0,NULL,'TCL Script',NULL,'2012-02-29 03:08:22'),('tex','application/x-latex',0,NULL,'LaTeX Document',NULL,'2012-02-29 03:08:50'),('texi','application/x-texinfo',0,NULL,'GNU Texinfo Document',NULL,'2012-02-29 03:09:00'),('texinfo','application/x-texinfo',0,NULL,'GNU Texinfo Document',NULL,'2012-02-29 03:09:07'),('tif','image/tiff',0,'image.gif','TIFF image',NULL,'0000-00-00 00:00:00'),('tiff','image/tiff',0,'image.gif','TIFF image',NULL,'0000-00-00 00:00:00'),('tr','text/plain',0,NULL,'TCL Trace Output',NULL,'2012-02-29 03:10:22'),('tsv','text/tab-separated-values',1,NULL,'Tab-Separated Values',NULL,'2012-02-29 03:10:24'),('ttf','application/x-font-ttf',0,NULL,'TrueType Font',NULL,'2012-02-29 02:17:15'),('txt','text/plain',1,'txt.gif','Plain text',NULL,'0000-00-00 00:00:00'),('ustar','application/x-ustar',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('vcd','application/octet-stream',0,NULL,'Virtual CD Image',NULL,'2012-02-29 03:11:36'),('vob','application/octet-stream',0,NULL,'DVD Video Object',NULL,'2012-02-29 02:18:02'),('vrml','model/vrml',1,NULL,'VRML Document',NULL,'2012-02-29 03:13:01'),('vsd','application/vnd.visio',0,NULL,'Microsoft Visio Diagram',NULL,'2012-02-29 02:18:40'),('vxml','application/voicexml+xml',1,NULL,NULL,NULL,'0000-00-00 00:00:00'),('wav','audio/x-wav',0,'movie.gif','WAV audio',NULL,'0000-00-00 00:00:00'),('wbmp','image/vnd.wap.wbmp',1,'image.gif','Wireless Bitmap Image',NULL,'2012-02-29 03:14:00'),('wbxml','application/vnd.wap.wbxml',1,NULL,NULL,NULL,'0000-00-00 00:00:00'),('wma','audio/x-ms-wma',0,NULL,'Windows Media Audio',NULL,'2012-02-29 02:19:10'),('wmf','image/x-wmf',0,NULL,'Windows Metafile',NULL,'2012-02-29 02:19:29'),('wml','text/vnd.wap.wml',0,NULL,'Wireless Markup Language Docume',NULL,'2012-02-29 03:14:34'),('wmlc','application/vnd.wap.wmlc',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('wmls','text/vnd.wap.wmlscript',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('wmlsc','application/vnd.wap.wmlscriptc',0,NULL,NULL,NULL,'0000-00-00 00:00:00'),('wmv','video/x-ms-wmv',0,NULL,'Windows Media Video',NULL,'2012-02-29 03:15:47'),('wrl','model/vrml',1,NULL,NULL,NULL,'0000-00-00 00:00:00'),('xbm','image/x-xbitmap',0,'image.gif','X Bitmap Image',NULL,'2012-02-29 03:23:05'),('xht','application/xhtml+xml',1,NULL,'XHTML Document',NULL,'2012-02-29 03:24:06'),('xhtml','application/xhtml+xml',1,NULL,'XHTML Document',NULL,'2012-02-29 03:24:12'),('xls','application/vnd.ms-excel',0,NULL,'Microsoft Excel Worksheet',NULL,'2012-02-29 02:10:02'),('xlsx','application/vnd.openxmlformats',0,NULL,'Microsoft Excel Worksheet',NULL,'2012-02-29 02:10:11'),('xml','application/xml',1,NULL,'XML',NULL,'0000-00-00 00:00:00'),('xpm','image/x-xpixmap',0,'image.gif','X Pixmap',NULL,'2012-02-29 03:24:49'),('xsl','text/xml',1,NULL,'Extensible Stylesheet Language ',NULL,'2012-02-29 03:25:38'),('xslt','text/xslt',1,NULL,'Extensible Stylesheet Language ',NULL,'2012-02-29 03:26:03'),('xul','application/vnd.mozilla.xul+xml',1,NULL,'XML User Interface Language Fil',NULL,'2012-02-29 03:26:31'),('xwd','image/x-xwindowdump',0,'image.gif','X Window Dump',NULL,'2012-02-29 03:27:08'),('xyz','application/octet-stream',0,NULL,'Tinker Cartesian Coordinates',NULL,'2012-02-29 03:27:45'),('zip','application/zip',0,'zip.gif','ZIP compressed',NULL,'0000-00-00 00:00:00');
/*!40000 ALTER TABLE `defFileExtToMimetype` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defLanguages`
--

DROP TABLE IF EXISTS `defLanguages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defLanguages` (
  `lng_NISOZ3953` char(3) NOT NULL COMMENT 'Three character NISO Z39.53 language code',
  `lng_ISO639` char(2) NOT NULL COMMENT 'Two character ISO639 language code',
  `lng_Name` varchar(63) NOT NULL COMMENT 'Language name, generally accepted name (normally English terminology)',
  `lng_Notes` varchar(1000) DEFAULT NULL COMMENT 'URL reference to, or notes on the definition of the language',
  `lng_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`lng_NISOZ3953`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Language list including optional standard language codes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defLanguages`
--

LOCK TABLES `defLanguages` WRITE;
/*!40000 ALTER TABLE `defLanguages` DISABLE KEYS */;
INSERT INTO `defLanguages` VALUES ('ARA','AR','Arabic',NULL,'0000-00-00 00:00:00'),('CAM','KM','Khmer',NULL,'0000-00-00 00:00:00'),('CHI','ZH','Chinese',NULL,'0000-00-00 00:00:00'),('CZE','CS','Czech',NULL,'0000-00-00 00:00:00'),('DAN','DA','Danish',NULL,'0000-00-00 00:00:00'),('DUT','NL','Dutch',NULL,'0000-00-00 00:00:00'),('ENG','EN','English',NULL,'0000-00-00 00:00:00'),('EST','ET','Estonian',NULL,'0000-00-00 00:00:00'),('FIN','FI','Finish',NULL,'0000-00-00 00:00:00'),('FRE','FR','French',NULL,'0000-00-00 00:00:00'),('GER','DE','German',NULL,'0000-00-00 00:00:00'),('GRE','EL','Greek',NULL,'0000-00-00 00:00:00'),('HEB','HE','Hebrew',NULL,'0000-00-00 00:00:00'),('HIN','HI','Hindi',NULL,'0000-00-00 00:00:00'),('HUN','HU','Hungarian',NULL,'0000-00-00 00:00:00'),('IND','ID','Indonesian',NULL,'0000-00-00 00:00:00'),('ITA','IT','Italian',NULL,'0000-00-00 00:00:00'),('JPN','JA','Japanese',NULL,'0000-00-00 00:00:00'),('KOR','KO','Korean',NULL,'0000-00-00 00:00:00'),('LAV','LV','Latvian',NULL,'0000-00-00 00:00:00'),('MAL','MS','Malay',NULL,'0000-00-00 00:00:00'),('NOR','NO','Norwegian',NULL,'0000-00-00 00:00:00'),('POL','PL','Polish',NULL,'0000-00-00 00:00:00'),('POR','PT','Portuguese',NULL,'0000-00-00 00:00:00'),('RUS','RU','Russian',NULL,'0000-00-00 00:00:00'),('SCC','HR','Croatian',NULL,'0000-00-00 00:00:00'),('SCR','SR','Serbian',NULL,'0000-00-00 00:00:00'),('SLO','SK','Slovak',NULL,'0000-00-00 00:00:00'),('SPA','ES','Spanish',NULL,'0000-00-00 00:00:00'),('SWA','SW','Swahili',NULL,'0000-00-00 00:00:00'),('SWE','SV','Swedish',NULL,'0000-00-00 00:00:00'),('THA','TH','Thai',NULL,'0000-00-00 00:00:00'),('TUR','TR','Turkish',NULL,'0000-00-00 00:00:00'),('UKR','UK','Ukranian',NULL,'0000-00-00 00:00:00'),('VIE','VI','Vietnamese',NULL,'0000-00-00 00:00:00'),('YID','YI','Yiddish',NULL,'0000-00-00 00:00:00');
/*!40000 ALTER TABLE `defLanguages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defOntologies`
--

DROP TABLE IF EXISTS `defOntologies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defOntologies` (
  `ont_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Ontology code, primary key',
  `ont_ShortName` varchar(63) NOT NULL COMMENT 'The commonly used acronym or short name of the ontology',
  `ont_FullName` varchar(255) NOT NULL COMMENT 'The commonly used full name of the ontology',
  `ont_Description` varchar(1000) DEFAULT NULL COMMENT 'An optional descriptuion of the domain, origina and aims of the ontology',
  `ont_RefURI` varchar(250) DEFAULT NULL COMMENT 'The URI to a definition of the ontology',
  `ont_Status` enum('reserved','approved','pending','open') NOT NULL DEFAULT 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `ont_OriginatingDBID` mediumint(8) unsigned DEFAULT NULL COMMENT 'Database where this ontology originated, 0 = locally',
  `ont_NameInOriginatingDB` varchar(63) DEFAULT NULL COMMENT 'Name used in database where this ontology originated',
  `ont_IDInOriginatingDB` smallint(5) unsigned DEFAULT NULL COMMENT 'ID used in database where this ontology originated',
  `ont_Order` tinyint(3) unsigned zerofill NOT NULL DEFAULT '255' COMMENT 'Ordering value to define alternate display order in interface',
  `ont_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `ont_locallyModified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY (`ont_ID`),
  UNIQUE KEY `ont_ShortName` (`ont_ShortName`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='A table of references to different ontologies used by Heuris';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defOntologies`
--

LOCK TABLES `defOntologies` WRITE;
/*!40000 ALTER TABLE `defOntologies` DISABLE KEYS */;
INSERT INTO `defOntologies` VALUES (1,'local','Null ontology','An empty ontology which can be complemented','','open',2,'Null ontology',1,255,'0000-00-00 00:00:00',0),(2,'DC','Dublin Core',NULL,'http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=52142','open',2,'Dublin Core',2,255,'0000-00-00 00:00:00',0),(3,'CIDOC-CRM','CIDOC-CRM',NULL,'http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=34424','open',2,'CIDOC-CRM',3,255,'0000-00-00 00:00:00',0);
/*!40000 ALTER TABLE `defOntologies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defRecStructure`
--

DROP TABLE IF EXISTS `defRecStructure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defRecStructure` (
  `rst_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the record structures table',
  `rst_RecTypeID` smallint(5) unsigned NOT NULL COMMENT 'The record type to which this detail is allocated, 0 = all rectypes',
  `rst_DetailTypeID` smallint(5) unsigned NOT NULL COMMENT 'Detail type for this field or, if MSB set, FieldSet code + 32767',
  `rst_DisplayName` varchar(255) NOT NULL DEFAULT 'Please enter a prompt ...' COMMENT 'Display name for this dtl type in this rectype, autofill with dty_Name',
  `rst_DisplayHelpText` varchar(255) DEFAULT NULL COMMENT 'The user help text to be displayed for this detail type for this record type',
  `rst_DisplayExtendedDescription` varchar(5000) DEFAULT NULL COMMENT 'The rollover text to be displayed for this detail type for this record type',
  `rst_DisplayOrder` smallint(3) unsigned zerofill NOT NULL DEFAULT '999' COMMENT 'A sort order for display of this detail type in the record edit form',
  `rst_DisplayWidth` tinyint(3) unsigned NOT NULL DEFAULT '50' COMMENT 'The field width displayed for this detail type in this record type',
  `rst_DefaultValue` varchar(63) DEFAULT NULL COMMENT 'The default value for this detail type for this record type',
  `rst_RecordMatchOrder` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Indicates order of significance in detecting duplicate records, 1 = highest',
  `rst_CalcFunctionID` tinyint(3) unsigned DEFAULT NULL COMMENT 'FK to table of function specifications for calculating string values',
  `rst_RequirementType` enum('required','recommended','optional','forbidden') NOT NULL DEFAULT 'optional',
  `rst_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL DEFAULT 'viewable' COMMENT 'Allows restriction of visibility of a particular field in a specified record type',
  `rst_Status` enum('reserved','approved','pending','open') NOT NULL DEFAULT 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `rst_MayModify` enum('locked','discouraged','open') NOT NULL DEFAULT 'open' COMMENT 'Extent to which detail may be modified within this record structure',
  `rst_OriginatingDBID` mediumint(8) unsigned DEFAULT NULL COMMENT 'Database where this record structure element originated, 0 = locally',
  `rst_IDInOriginatingDB` smallint(5) unsigned DEFAULT NULL COMMENT 'ID used in database where this record structure element originated',
  `rst_MaxValues` tinyint(3) unsigned DEFAULT NULL COMMENT 'Maximum number of values per record for this detail, NULL = unlimited, 0 = not allowed',
  `rst_MinValues` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'If required, minimum number of values per record for this detail',
  `rst_DisplayDetailTypeGroupID` tinyint(3) unsigned DEFAULT NULL COMMENT 'If set, places detail in specified group instead of according to dty_DetailTypeGroup',
  `rst_FilteredJsonTermIDTree` varchar(500) DEFAULT NULL COMMENT 'JSON encoded tree of allowed terms, subset of those defined in defDetailType',
  `rst_PtrFilteredIDs` varchar(250) DEFAULT NULL COMMENT 'Allowed Rectypes (CSV) within list defined by defDetailType (for pointer details)',
  `rst_OrderForThumbnailGeneration` tinyint(3) unsigned DEFAULT NULL COMMENT 'Priority order of fields to use in generating thumbnail, null = do not use',
  `rst_TermIDTreeNonSelectableIDs` varchar(255) DEFAULT NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  `rst_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rst_LocallyModified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY (`rst_ID`),
  UNIQUE KEY `rst_composite` (`rst_RecTypeID`,`rst_DetailTypeID`),
  KEY `rst_DetailTypeID` (`rst_DetailTypeID`),
  CONSTRAINT `fk_rst_DetailtypeID` FOREIGN KEY (`rst_DetailTypeID`) REFERENCES `defDetailTypes` (`dty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rst_RecTypeID` FOREIGN KEY (`rst_RecTypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1761 DEFAULT CHARSET=utf8 COMMENT='The record details (fields) required for each record type';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRecStructure`
--

LOCK TABLES `defRecStructure` WRITE;
/*!40000 ALTER TABLE `defRecStructure` DISABLE KEYS */;
INSERT INTO `defRecStructure` VALUES (1,1,7,'Source record','Primary resource','',000,60,'',0,NULL,'required','viewable','reserved','open',2,1,1,1,1,'',NULL,NULL,'','2014-07-22 05:55:07',1),(2,1,6,'Relationship type','RelationType','',001,60,'',0,NULL,'required','viewable','reserved','open',2,2,1,1,1,NULL,'',NULL,'','2014-07-22 05:55:14',1),(3,1,5,'Target record','The target record in Heurist to which the relationship points','',002,60,'',1,NULL,'required','viewable','reserved','open',2,3,1,1,0,'',NULL,NULL,'','2014-07-22 05:55:20',1),(4,1,1,'Title for relationship','A succinct descriptive title for the relationship','',005,60,'',0,NULL,'optional','viewable','approved','open',2,4,1,0,0,'','',NULL,'','2012-09-20 08:03:39',1),(5,1,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',006,60,NULL,0,NULL,'optional','viewable','approved','open',2,5,1,0,1,NULL,NULL,NULL,NULL,'2012-09-20 01:48:49',0),(6,1,10,'Start date/time','Dates only required if relationship is to be timestamped ','',003,10,'',0,NULL,'recommended','viewable','approved','open',2,6,1,0,0,'','',NULL,'','2012-09-20 01:48:49',1),(7,1,11,'End date/time','Enter end date only if relationship expires','',004,10,'',0,NULL,'recommended','viewable','approved','open',2,7,1,0,0,'','',NULL,'','2012-09-20 01:48:49',1),(8,1,8,'Interpretation/commentary','A pointer to a commentary/interpreation record (for spatial, temporal or general information)','',007,60,'',0,NULL,'optional','viewable','approved','open',2,8,1,0,1,'',NULL,NULL,'','2012-09-20 08:02:55',1),(9,1,28,'Location (mappable)','A geospatial object providing a mappable location for the record','',009,60,'',0,NULL,'optional','viewable','approved','open',2,9,1,0,1,'','',NULL,'','2012-09-20 08:10:19',1),(10,1,38,'File resource','An uploaded file or a reference to a file through a URI ','',010,60,'',0,NULL,'optional','viewable','approved','open',2,10,NULL,0,1,'','',NULL,'','2012-09-20 08:10:23',1),(11,1,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',008,60,'',0,NULL,'optional','viewable','approved','open',2,11,1,0,1,'','',NULL,'','2012-09-20 07:58:20',1),(12,2,1,'Web page title','A succinct descriptive title for the page, even if the page itself does not have one. The page title is harvested automatically by the bookmarklet.','',000,80,'',1,NULL,'required','viewable','reserved','open',2,12,1,1,0,'','',NULL,'','2014-03-11 04:13:54',1),(13,2,3,'Short Summary','A short summary of the page topic or content eg. around 100 words, for use in annotated lists or summaries','',001,80,'',0,NULL,'recommended','viewable','reserved','open',2,13,1,0,0,'','',NULL,'','2013-02-22 03:01:14',1),(14,2,39,'Thumbnail Image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages. Generated automatically on creation using bookmarklet','',003,40,'',0,NULL,'recommended','viewable','approved','open',2,14,1,0,0,'','',NULL,'','2012-10-18 00:53:54',1),(15,2,9,'Date accessed','The date the web page was accessed, for use in bibliographic referencing','',002,10,'today',0,NULL,'recommended','viewable','approved','open',2,15,1,0,0,'','',NULL,'','2013-02-22 02:58:41',1),(16,3,1,'Note title','A succinct descriptive title for the note, will appear in search results and lists','',000,80,'',1,NULL,'required','viewable','reserved','open',2,16,1,1,0,'','',NULL,'','2014-03-11 04:13:28',1),(17,3,3,'Short note / summary','A short note or a summary of a longer note eg. 100 words, for use in annotated lists and reports','',002,80,'',0,NULL,'required','viewable','approved','open',2,17,1,1,0,'','',NULL,'','2013-02-22 03:07:22',1),(18,3,9,'Date','Specific date to which the note applies (if applicable)','',001,10,'today',0,NULL,'required','viewable','approved','open',2,18,1,1,0,'','',NULL,'','2014-03-11 04:12:22',1),(19,3,38,'Related Files','Upload any associated files here. Consider using a separate multimedia records (previous field) to allow more detailed documentation of the file','',007,40,'',0,NULL,'optional','viewable','approved','open',2,19,NULL,0,0,'','',NULL,'','2014-03-11 04:12:43',1),(20,3,39,'Thumbnail image','A thumbnail image, up to 400 wide, for display 100 - 200 wide in search results and lists or reports','',006,40,'',0,NULL,'optional','viewable','approved','open',2,20,1,0,0,'','',NULL,'','2013-02-22 03:08:49',1),(21,4,1,'Name of organisation','Full title of the organisation - do not abbreviate','',000,60,'',1,NULL,'required','viewable','reserved','open',2,21,1,1,1,'','',NULL,'','2013-02-22 03:11:02',1),(22,4,2,'Short name / acronym','The acronym or short name by which the organisation is known eg. NEH, NLA, LC, ARC, Telstra, Apple','Please provide an extended description for display on rollover ...',002,20,'',2,NULL,'recommended','viewable','approved','open',2,22,1,0,1,'','',NULL,'','2014-03-11 04:16:41',1),(23,4,22,'Organisation type','Organisation type','Please provide an extended description for display on rollover ...',001,60,'',0,NULL,'required','viewable','reserved','open',2,23,NULL,1,1,NULL,'',NULL,'','2014-03-11 04:16:41',1),(24,4,3,'Short description','Short description of the organisation, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',003,80,'',0,NULL,'recommended','viewable','approved','open',2,24,1,0,1,'','',NULL,'','2013-02-22 03:22:39',1),(25,4,10,'Start date','Date the organisation was founded','',008,10,'',0,NULL,'optional','viewable','approved','open',2,25,1,0,1,'','',NULL,'','2013-02-22 03:16:35',1),(26,4,11,'End date','Date the organisation was wound up/ closed / ceased to exist','',009,10,'',0,NULL,'optional','viewable','approved','open',2,26,1,0,1,'','',NULL,'','2013-02-22 03:17:03',1),(28,4,26,'Country','The main country in which the organisation is/was based/operated','',013,60,'',0,NULL,'recommended','viewable','approved','open',2,28,1,0,1,NULL,'',NULL,'','2014-05-21 18:02:35',1),(29,4,28,'Location (mappable)','A geospatial object or objects providing a mappable location for the organisation','',011,60,'',0,NULL,'recommended','viewable','approved','open',2,29,1,0,1,'','',NULL,'','2014-05-21 18:01:56',1),(30,4,39,'Thumbnail or Logo','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',004,60,'',0,NULL,'recommended','viewable','approved','open',2,30,1,0,1,'','',NULL,'','2013-02-22 03:14:38',1),(31,4,38,'Related files','Documents adn otehr information related to this organisation, either uploaded or as a reference to a URI','',016,60,'',0,NULL,'optional','viewable','approved','open',2,31,NULL,0,1,'','',NULL,'','2014-05-21 17:59:32',1),(32,5,1,'Media item title','A succinct descriptive title of the media item, generally the title given by the author','',000,80,'',1,NULL,'required','viewable','reserved','open',2,32,1,1,0,'','',NULL,'','2014-03-11 04:19:05',1),(33,5,38,'Media file','Browse to media file and upload - recommended max size 5 MBytes','',001,60,'',2,NULL,'required','viewable','reserved','open',2,33,1,1,0,'','',NULL,'','2013-02-22 03:28:02',1),(34,5,3,'Short description','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',003,80,'',0,NULL,'recommended','viewable','approved','open',2,34,1,0,1,'','',NULL,'','2014-03-11 04:18:38',1),(35,5,15,'Creator(s)','The creator(s) - author, photographer, recordist etc. - of the media item (might be interpreted as the author of the content for photographs of works of art etc.)','',005,30,'',0,NULL,'recommended','viewable','approved','open',2,35,NULL,0,0,'',NULL,NULL,'','2014-03-11 04:18:38',1),(36,5,10,'Date of creation','Date of creation or publication of this media item','',006,10,'today',0,NULL,'recommended','viewable','approved','open',2,36,1,0,0,'','',NULL,'','2014-03-11 04:18:38',1),(37,5,39,'Thumbnail image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in search results, lists and reports','',002,40,'',0,NULL,'recommended','viewable','approved','open',2,37,1,0,0,'','',NULL,'','2014-03-11 04:18:33',1),(38,5,28,'Location (mappable)','Geographic object(s) describing the location as lines, polygons or other shapes','',008,40,'',0,NULL,'optional','viewable','approved','open',2,38,1,0,0,'','',NULL,'','2014-03-11 04:17:47',1),(39,5,35,'Copyright information','Copyright information for this media item (copyright statement or URL)','',007,80,'',0,NULL,'recommended','viewable','approved','open',2,39,1,0,1,'','',NULL,'','2014-03-11 04:17:47',1),(45,7,1,'Title of post','A title for the blog entry, date will be added automatically','',000,80,'',1,NULL,'required','viewable','reserved','open',2,45,1,1,0,'','',NULL,'','2012-10-18 02:35:32',1),(46,7,39,'Thumbnail image','Thumbnail image, recommended 200 pixels maximum dimension','',001,30,'',0,NULL,'recommended','viewable','reserved','open',2,46,1,0,0,'','',NULL,'','2012-10-18 02:35:40',1),(47,7,28,'Location (mappable)','Location or extent of this blog entry, can be rendered on the map','',005,30,'',0,NULL,'recommended','viewable','approved','open',2,47,NULL,0,0,'','',NULL,'','2014-07-22 05:58:18',1),(48,8,1,'Summary title','A title for this interpretation, excluding author, date','',000,50,'',1,NULL,'required','viewable','reserved','open',2,48,1,1,0,'','',NULL,'','2014-03-11 04:23:08',1),(49,8,10,'Validity date','Date of validity of this interpretation','',001,10,'today',0,NULL,'required','viewable','reserved','open',2,49,1,1,0,'','',NULL,'','2014-07-22 06:01:04',1),(50,8,3,'Text of annotation','Short sumamry of the interpretation, use the WYSIWYG text in the TEXT or PERSONAL tabs for longer description','',004,100,'',0,NULL,'required','viewable','reserved','open',2,50,1,1,0,'','',NULL,'','2014-07-22 06:01:42',1),(51,8,15,'Author(s)','Person(s) making this interpretation (original author, use transcriber if further interpreted)','',002,30,'',1,NULL,'recommended','viewable','reserved','open',2,51,NULL,0,0,'',NULL,NULL,'','2014-07-22 06:01:22',1),(52,8,14,'Transcriber(s)','Person(s) transcribing/further interpreting the original interpretation','',003,30,NULL,0,NULL,'optional','viewable','approved','open',2,52,NULL,0,0,NULL,NULL,NULL,NULL,'2014-03-11 04:23:42',1),(53,8,13,'References','Bibliographic or other records in Heurist which support or relate to this interpretation','',005,80,'',0,NULL,'recommended','viewable','approved','open',2,53,NULL,0,0,'',NULL,NULL,'','2014-07-22 06:01:53',1),(54,9,23,'Email of sender','Email address from which this email forwarded or ccd','Address of owner (forwarder or sender)',000,50,'',2,NULL,'required','viewable','reserved','open',2,54,1,1,0,'','',NULL,'','2014-07-22 05:59:12',1),(55,9,1,'Subject','Subject/title of the email','Subject line of email',001,30,'No subject line provided',1,NULL,'required','viewable','reserved','open',2,55,1,1,0,'','',NULL,'','2014-07-22 05:59:22',1),(56,9,9,'Date originally sent','Date of the original email (date of sending of a ccd email or original date of a forwarded email)','Date of original sending',002,30,'',0,NULL,'required','viewable','reserved','open',2,56,1,1,0,'','',NULL,'','2014-07-22 05:59:33',1),(57,9,24,'Email of originator','Address of original sender (if applicable, that is for forwarded emails)','Address of original sender (where different)',003,50,'',0,NULL,'optional','viewable','approved','open',2,57,1,0,0,'','',NULL,'','2014-07-22 06:00:09',1),(58,9,25,'Email of recipients','Email addresses of all recipients of the email','Emails of all recipients of the email ',004,30,'',0,NULL,'optional','viewable','approved','open',2,58,NULL,0,0,'','',NULL,'','2014-07-22 06:00:19',1),(59,9,3,'Email Body','Content of the body/text of the email','',005,60,'',0,NULL,'recommended','viewable','reserved','open',2,59,1,0,1,'','',NULL,'','2014-07-22 06:00:33',1),(60,9,38,'Attachments','Files attached to the email','Attached files',006,50,NULL,0,NULL,'optional','viewable','approved','open',2,60,NULL,0,0,NULL,NULL,NULL,NULL,'2012-09-20 01:48:49',0),(61,10,1,'Family name','Family name of person','',001,40,'',1,NULL,'required','viewable','reserved','open',2,61,1,1,1,'','',NULL,'','2013-02-22 03:48:02',1),(62,10,18,'Given name(s)','The given name or names of the person placed in their normal order','',002,40,'',1,NULL,'recommended','viewable','reserved','open',2,62,1,0,0,'','',NULL,'','2013-02-27 06:44:28',1),(63,10,19,'Honorific','Title or grade - Prof, Dr, Sir etc., recommend omitting Mr, Mrs or Ms','',004,60,'',1,NULL,'optional','viewable','approved','open',2,63,1,0,1,NULL,'',NULL,'','2014-05-15 21:32:18',1),(64,10,10,'Birth date','Date of Birth (year or year-month or year-month-day)','',009,10,'',2,NULL,'optional','viewable','approved','open',2,64,1,0,1,'','',NULL,'','2013-02-22 03:48:02',1),(65,10,27,'Birth place (text)','Name of location where person was born, use town or city and country eg. Penzance, UK','',010,30,'',2,NULL,'optional','viewable','approved','open',2,65,1,0,1,'','',NULL,'','2014-03-11 04:27:58',1),(66,10,20,'Gender','Gender of the person','',005,30,'',0,NULL,'optional','viewable','approved','open',2,66,1,0,0,NULL,'',NULL,'','2014-05-15 21:32:33',1),(67,10,3,'Short description','Short description of the person eg. 100 words, for use in annotated lists/web pages','',006,80,'',0,NULL,'optional','viewable','approved','open',2,67,1,0,0,'','',NULL,'','2014-05-15 21:32:43',1),(68,10,23,'Email address','Email address(es) of the person, enter one address only on each line','',015,60,'',0,NULL,'optional','viewable','approved','open',2,68,NULL,0,1,'','',NULL,'','2014-03-11 04:28:35',1),(69,10,17,'Contact info or URL','Contact information - it is best to use the URL of a staff directory page to avoid the need to update contact details','',016,100,'',0,NULL,'optional','viewable','approved','open',2,69,NULL,0,0,'','',NULL,'','2014-03-11 04:29:52',1),(70,10,37,'Unique public identifier','eg. NLA party identifier (Australia) or other national person identifier code','',017,30,'',0,NULL,'optional','viewable','approved','open',2,70,1,0,0,'','',NULL,'','2014-03-11 04:28:35',1),(71,10,11,'Date of death','Date of death (year, or year-month or year-month-day)','Please provide an extended description for display on rollover ...',013,10,'',0,NULL,'optional','viewable','approved','open',2,71,1,0,1,'','',NULL,'','2014-03-11 04:28:35',1),(1375,6,1,'Aggregation Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,'',1,NULL,'required','viewable','reserved','open',2,1375,1,1,1,'','',NULL,'','2014-03-11 04:20:59',1),(1376,6,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','Please provide an extended description for display on rollover ...',008,60,'',0,NULL,'optional','viewable','open','open',2,1376,1,0,1,'','',NULL,'','2014-03-11 04:22:01',1),(1377,6,3,'Short summary','Notes about the aggregation, normally 100 - 200 words max. so usable in lists','Please provide an extended description for display on rollover ...',001,80,'',0,NULL,'optional','viewable','reserved','open',2,1377,1,0,1,'','',NULL,'','2014-07-22 05:57:38',1),(1379,6,15,'Creator - author, artist etc.','The person(s) or organisation(s) who created this record','May include authors, artists, organisations that sponsored a resource etc.',009,60,'',0,NULL,'optional','viewable','open','open',2,1379,NULL,0,1,'',NULL,NULL,'','2014-03-11 04:22:01',1),(1380,6,35,'Copyright information','Copyright information, either a URL to a copyrigth statement or text describing the copyright','Please provide an extended description for display on rollover ...',010,100,'',0,NULL,'optional','viewable','open','open',2,1380,1,0,1,'','',NULL,'','2014-03-11 04:22:01',1),(1391,3,4,'Extended note','An extended version of the note and/or further detail','',003,100,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:07:16',0),(1392,10,57,'Life','Another separator field','',008,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:48:02',0),(1393,10,58,'Contact info','Another separator field','',014,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-03-11 04:28:35',0),(1394,2,4,'Extended description','A more extensive description, often derived from text on the web page itself','',010,100,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:04:23',0),(1395,7,3,'Short summary','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',003,80,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:58:48',0),(1397,7,15,'Creator(s)','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,'',NULL,NULL,'','2014-07-22 05:58:28',0),(1398,7,27,'Place name(s)','The name of a place/location(s) associated with the blog post','',006,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,'','',NULL,'','2014-07-22 05:58:40',0),(1399,7,57,'Optional information','Another separator field','',002,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:58:18',0),(1400,6,13,'Record pointer','One or more record pointers to records which are to be included in this aggregation','A highly generic detail utility',003,0,'',0,NULL,'optional','viewable','reserved','open',NULL,NULL,NULL,0,1,'',NULL,NULL,'','2014-07-22 05:57:04',0),(1401,6,12,'Heurist Query String','A string representing a valid Heurist query (a query that you might enter in the search box on the home page)','Please provide an extended description for display on rollover ...',004,100,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:57:04',0),(1402,6,40,'Heurist Filter String','A string representing a valid Heurist filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]}','Allows the saving of rules that work on a result set. Rather than just saying \"give me all the related records of record x\" one could say \"give me all the related people of record x\" and save this in a detail for reuse.',006,100,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:57:04',0),(1403,6,48,'Heurist Layout String','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Used to pre-configure the Heurist environment. Usually accompanying a query and/or a filter. The combination of these details enables the full current Heurist environment to be stored and/or sent to other users',005,100,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:57:04',0),(1404,6,57,'Aggregation fields','A heading for contact information','',002,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:57:04',0),(1405,6,58,'Descriptive fields','A heading for the life history section of a person record','',007,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-07-22 05:57:04',0),(1406,10,39,'Thumbnail picture','An image used to represent the person. Typically  ~ 200 pixels wide for display in search results and other compact listings','',007,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,'','',NULL,'','2014-05-15 21:33:00',0),(1407,4,57,'Other information','A heading for contact information','',015,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-05-21 17:59:32',0),(1408,4,58,'Description','A heading for the life history section of a person record','',005,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2012-10-17 02:04:09',0),(1409,4,4,'Extended description','An extended description of the organisation. However more detailed information can be attached as PDF or other documents','',007,100,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:16:16',0),(1410,4,17,'Contact details or URL(s)','Contact details, either as text or a URL to eg. a phonebook record or search','',006,80,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,NULL,0,1,'','',NULL,'','2014-03-11 04:15:39',0),(1411,2,21,'Organisation(s) concerned','The organisation(s) which own the web site or which the website is about','',008,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,'',NULL,NULL,'','2013-02-22 03:04:23',0),(1412,2,26,'Country(ies)','The country where the web site\'s owners/subjects is/are based, where this can be identified','',005,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,NULL,'',NULL,'','2013-02-22 03:03:33',0),(1413,2,28,'Mappable Location','A mappable location for the owners or subjects of the web site, where this can be identified - can be a point or an area of coverage','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage',006,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:03:33',0),(1414,2,57,'Further information','A heading for contact information','',007,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:04:01',0),(1415,2,16,'Person(s) concerned','The person(s) who own the web site or whom the website is about','',009,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,'',NULL,NULL,'','2013-02-22 03:04:23',0),(1416,2,38,'Related files','An uploaded file or a reference to a file through a URI, with further information eg. saved copy of the page or downloaded content or PDF files','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible',011,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,'','',NULL,'','2013-02-22 03:03:27',0),(1417,3,61,'Multimedia >','Multimedia records - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','',008,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,NULL,NULL,NULL,'','2014-03-11 04:12:43',0),(1418,3,15,'Additional author(s)  >','The person or organisation who created the note (where different from the person who created the record)','May include authors, artists, organisations that sponsored a resource etc.',005,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,NULL,0,1,'',NULL,NULL,'','2013-02-22 03:10:01',0),(1419,3,57,'Additional information','A heading for contact information','',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:07:03',0),(1420,4,61,'Multimedia >','Points to a multimedia record - use this in preference to a file field (below) if you want to record additional metadata about the media file or use it in multiple records ','',017,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,NULL,0,1,NULL,NULL,NULL,'','2014-05-21 17:59:32',0),(1421,5,41,'File Type','The type of media file, may be used to determine presentation and playback methods','',014,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,'',NULL,'','2013-03-04 06:36:31',0),(1422,5,62,'File name','The original name of the file, excluding path, including extension','',012,40,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-03-04 06:36:31',0),(1423,5,63,'File path','The full path to the file, created automatically for files imported with the in situ file importer','',011,80,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-03-04 06:36:31',0),(1424,5,64,'File extension','The (normally three letter) file extension of the file','',013,5,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2014-03-11 04:20:10',0),(1425,5,65,'File recording device','The device used to collect files, may be generic \'image\' or more specific eg. camera make and model','',010,40,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-03-04 06:36:31',0),(1426,5,66,'File duration (secs)','The duration of a media object, such as sound or video, in seconds','',016,10,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-03-04 06:36:32',0),(1427,5,67,'File size (bytes)','The size of the file in bytes','',015,10,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-03-04 06:36:32',0),(1428,5,68,'MD5 checksum','The calculated MD5 checksum for the file','',017,40,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-03-04 06:36:32',0),(1429,5,57,'Technical information','A heading for contact information','',009,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-03-04 06:36:31',0),(1430,2,58,'Location','Please rename to an appropriate heading within each record structure','',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:03:41',0),(1431,4,69,'Location information','Please rename to an appropriate heading within each record structure','',010,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2013-02-22 03:23:25',0),(1432,10,69,'Additional information','Please rename to an appropriate heading within each record structure','',003,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-02-21 06:33:36',0),(1433,10,70,'Common information','Please rename to an appropriate heading within each record structure','',000,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-02-22 03:48:28',0),(1434,10,61,'Multimedia >','Points to a multimedia record - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','',019,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,NULL,0,1,NULL,NULL,NULL,'','2014-03-11 04:31:03',0),(1435,10,38,'Related file(s)','An uploaded file or files or a reference to a file through a URI','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible',018,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,NULL,0,1,'','',NULL,'','2014-03-11 04:30:57',0),(1712,5,58,'Attribution','Please rename to an appropriate heading within each record structure','',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-03-11 04:18:38',0),(1713,10,28,'Birth place (mappable)','A geospatial object providing a mappable location for the record - can be used for any type of record with spatial location','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage',012,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,'','',NULL,'','2014-03-11 04:29:25',0),(1714,10,26,'Birth place (country)','Country. The list is prepopulated, but can be extended, and includes 2 and 3 letter country codes','',011,0,'',0,NULL,'optional','viewable','approved','open',NULL,NULL,1,0,1,NULL,'',NULL,'','2014-03-11 04:29:19',0),(1715,11,1,'Family name','Family name of person','',001,40,'',1,NULL,'required','viewable','reserved','open',2,61,1,1,1,'','',NULL,'','2013-02-22 03:48:02',1),(1716,11,3,'Short description','Short description of the person eg. 100 words, for use in annotated lists/web pages','',006,80,'',0,NULL,'optional','viewable','approved','open',2,67,1,0,0,'','',NULL,'','2014-05-15 21:32:43',1),(1717,11,10,'Birth date','Date of Birth (year or year-month or year-month-day)','',003,10,'',2,NULL,'optional','viewable','approved','open',2,64,1,0,1,'','',NULL,'','2014-05-15 21:37:55',1),(1720,11,18,'Given name(s)','The given name or names of the person placed in their normal order','',002,40,'',1,NULL,'recommended','viewable','reserved','open',2,62,1,0,0,'','',NULL,'','2013-02-27 06:44:28',1),(1722,11,20,'Gender','Gender of the person','',005,30,'',0,NULL,'optional','viewable','approved','open',2,66,1,0,0,NULL,'',NULL,'','2014-05-15 21:32:33',1),(1733,11,69,'Additional information','Please rename to an appropriate heading within each record structure','',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,'','',NULL,'','2014-05-15 21:37:55',0),(1734,11,70,'Common information','Please rename to an appropriate heading within each record structure','',000,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-02-22 03:48:28',0),(1746,12,1,'Name of place','Full name of the place - do not abbreviate, do not use hierarchichal naming (put that under country, state etc.)','',000,60,'',0,0,'required','viewable','reserved','open',3,2274,1,1,1,'','',0,'','2014-07-22 05:50:26',1),(1747,12,2,'Name - short','Name - short','',011,20,'',0,0,'optional','viewable','open','open',3,2282,1,0,1,'','',0,'','2014-05-21 17:57:04',1),(1748,12,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words. and so forth. Aim for 100 - 200 words.','',002,80,'',0,0,'optional','viewable','approved','open',3,2280,1,0,1,'','',0,'','2014-07-22 05:51:33',1),(1749,12,4,'Extended description','Extended description','',009,100,'',0,0,'optional','viewable','open','open',3,2281,1,0,1,'','',0,'','2014-05-21 17:57:04',1),(1750,12,10,'Start date','Start Date','',006,15,'',0,0,'optional','viewable','approved','open',3,2275,1,0,1,'','',0,'','2014-07-22 05:52:00',1),(1751,12,11,'End date','End Date','',007,15,'',0,0,'optional','viewable','approved','open',3,2276,1,0,1,'','',0,'','2014-07-22 05:52:08',1),(1752,12,26,'Country','Country','',004,60,'',0,0,'recommended','viewable','approved','open',3,2285,1,0,1,'','',0,'','2014-07-22 05:51:20',1),(1753,12,28,'Location (mappable)','A geospatial object providing a mappable location for the record','',005,60,'',0,0,'recommended','viewable','approved','open',3,2279,1,0,1,'','',0,'','2014-07-22 05:51:43',1),(1754,12,38,'Related file(s)','An uploaded file or a reference to a file through a URI ','',013,60,'',0,0,'optional','viewable','open','open',3,2277,0,0,1,'','',0,'','2014-05-21 17:57:04',1),(1755,12,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',010,60,'',0,0,'optional','viewable','open','open',3,2278,1,0,1,'','',0,'','2014-05-21 17:57:04',1),(1756,12,132,'Name - alternate','Name - alternate','',012,60,'',0,0,'optional','viewable','open','open',3,2284,0,0,1,'','',0,'','2014-05-21 17:57:04',1),(1757,12,76,'Additional','Headings serve to break the data entry form up into sections','',008,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2014-05-21 17:57:04',0),(1758,12,80,'Location/time','Headings serve to break the data entry form up into sections','',003,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2014-05-21 17:57:04',0),(1759,12,133,'Place type','The type or category of place','',001,0,'5043',0,0,'recommended','viewable','reserved','open',0,0,1,0,1,'','',0,'','2014-07-22 05:49:32',0),(1760,4,134,'Location (places)','Place(s) where the oprganisation is located','',012,0,'',0,NULL,'recommended','viewable','approved','open',NULL,NULL,NULL,0,1,NULL,NULL,NULL,'','2014-07-22 05:53:15',0);
/*!40000 ALTER TABLE `defRecStructure` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecStructure_last_insert`
    AFTER INSERT ON `defRecStructure`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecStructure_last_update`
    AFTER UPDATE ON `defRecStructure`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecStructure_last_delete`
    AFTER DELETE ON `defRecStructure`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defRecTypeGroups`
--

DROP TABLE IF EXISTS `defRecTypeGroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defRecTypeGroups` (
  `rtg_ID` tinyint(3) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Record type group ID referenced in defRectypes',
  `rtg_Name` varchar(40) NOT NULL COMMENT 'Name for this group of record types, shown as heading in lists',
  `rtg_Domain` enum('functionalgroup','modelview') NOT NULL DEFAULT 'functionalgroup' COMMENT 'Functional group (rectype has only one) or a Model/View group',
  `rtg_Order` tinyint(3) unsigned zerofill NOT NULL DEFAULT '002' COMMENT 'Ordering of record type groups within pulldown lists',
  `rtg_Description` varchar(250) DEFAULT NULL COMMENT 'A description of the record type group and its purpose',
  `rtg_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`rtg_ID`),
  UNIQUE KEY `rtg_Name` (`rtg_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COMMENT='Grouping mechanism for record types in pulldowns';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRecTypeGroups`
--

LOCK TABLES `defRecTypeGroups` WRITE;
/*!40000 ALTER TABLE `defRecTypeGroups` DISABLE KEYS */;
INSERT INTO `defRecTypeGroups` VALUES (1,'Basic record types','functionalgroup',000,'Common generic record types which will be useful in many databases','2013-09-10 04:13:36'),(4,'System Internals','functionalgroup',005,'Record types used by the Heurist system for specific workflows and functions','2013-09-10 03:40:08'),(6,'My record types','functionalgroup',001,'A group (tab) for defining your own record types - you can delete this tab, create additional groups, rename this one using the button at the end of the tabs, and move record types between groups','2013-09-10 05:23:10');
/*!40000 ALTER TABLE `defRecTypeGroups` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecTypeGroups_insert`
    AFTER INSERT ON `defRecTypeGroups`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecTypeGroups_update`
    AFTER UPDATE ON `defRecTypeGroups`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecTypeGroups_delete`
    AFTER DELETE ON `defRecTypeGroups`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defRecTypes`
--

DROP TABLE IF EXISTS `defRecTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defRecTypes` (
  `rty_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Record type code, widely used to reference record types, primary key',
  `rty_Name` varchar(63) NOT NULL COMMENT 'The name which is used to describe this record (object) type',
  `rty_OrderInGroup` tinyint(3) unsigned DEFAULT '0' COMMENT 'Ordering within record type display groups for pulldowns',
  `rty_Description` varchar(5000) NOT NULL COMMENT 'Description of this record type',
  `rty_TitleMask` varchar(500) NOT NULL DEFAULT '[title]' COMMENT 'Mask to build a composite title by combining field values',
  `rty_CanonicalTitleMask` varchar(500) DEFAULT '160' COMMENT 'Version of the mask converted to detail codes for processing',
  `rty_Plural` varchar(63) DEFAULT NULL COMMENT 'Plural form of the record type name, manually entered',
  `rty_Status` enum('reserved','approved','pending','open') NOT NULL DEFAULT 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `rty_OriginatingDBID` mediumint(8) unsigned DEFAULT NULL COMMENT 'Database where this record type originated, 0 = locally',
  `rty_NameInOriginatingDB` varchar(63) DEFAULT NULL COMMENT 'Name used in database where this record type originated',
  `rty_IDInOriginatingDB` smallint(5) unsigned DEFAULT NULL COMMENT 'ID in database where this record type originated',
  `rty_NonOwnerVisibility` enum('hidden','viewable','public','pending') NOT NULL DEFAULT 'viewable' COMMENT 'Allows blanket restriction of visibility of a particular record type',
  `rty_ShowInLists` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Flags if record type is to be shown in end-user interface, 1=yes',
  `rty_RecTypeGroupID` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT 'Record type group to which this record type belongs',
  `rty_RecTypeModelIDs` varchar(63) DEFAULT NULL COMMENT 'The model group(s) to which this rectype belongs, comma sep. list',
  `rty_FlagAsFieldset` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 = full record type, 1 = Fieldset = set of fields to include in other rectypes',
  `rty_ReferenceURL` varchar(250) DEFAULT NULL COMMENT 'A reference URL describing/defining the record type',
  `rty_AlternativeRecEditor` varchar(63) DEFAULT NULL COMMENT 'Name or URL of alternative record editor function to be used for this rectype',
  `rty_Type` enum('normal','relationship','dummy') NOT NULL DEFAULT 'normal' COMMENT 'Use to flag special record types to trigger special functions',
  `rty_ShowURLOnEditForm` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Determines whether special URL field is shown at the top of the edit form',
  `rty_ShowDescriptionOnEditForm` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Determines whether the record type description field is shown at the top of the edit form',
  `rty_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rty_LocallyModified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY (`rty_ID`),
  UNIQUE KEY `rty_Name` (`rty_Name`),
  KEY `rty_RecTypeGroupID` (`rty_RecTypeGroupID`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COMMENT='Defines record types, which corresponds with a set of detail';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRecTypes`
--

LOCK TABLES `defRecTypes` WRITE;
/*!40000 ALTER TABLE `defRecTypes` DISABLE KEYS */;
INSERT INTO `defRecTypes` VALUES (1,'Record relationship',30,'A relationship of a defined type between two records in the database, may include a time range over which the relationship is valid, and notes about the relationship','[2-7.RecTitle] | [2-6] [2-10] - [2-11] > [2-5.RecTitle]','[1] ([7.RecTitle] [6] [5.RecTitle])','Record relationships','reserved',2,'Record relationship',1,'viewable',0,4,NULL,1,'','','relationship',0,1,'2014-02-21 06:31:32',1),(2,'Web site / page',1,'A web site URL, typically a specific page (may be the home page of a website or specific pages or documents of interest)','[2-1]','[1]','Web site / pages','reserved',2,'Web site / page',2,'viewable',1,1,NULL,0,'','','normal',1,1,'2013-08-27 05:15:04',1),(3,'Notes',2,'A simple record type for taking notes','[2-1]','[1]','Notes','approved',2,'Notes',3,'viewable',1,1,NULL,0,'','','normal',1,1,'2013-08-27 05:15:04',1),(4,'Organisation',0,'Organisations (companies, universities, granting bodies, museums, libraries etc.)','[2-2]: [2-1]','[2]: [1]','Organisations','approved',2,'Organisation',4,'viewable',1,1,NULL,0,'','','normal',1,1,'2013-08-27 05:15:04',1),(5,'Digital media item',0,'Digital media files - typically image, sound, video - uploaded to the database or external reference','[2-1]','[1]','Digital media items','reserved',2,'Digital media file',5,'viewable',1,1,NULL,0,'','','normal',0,1,'2013-08-27 05:15:04',1),(6,'Aggregation',0,'A record which describes a static or dynamic collection of records and their filtering and layout, or acts as a root to which other records point, or both','[2-1]','[1]','Aggregations','reserved',2,'Aggregation',6,'viewable',1,4,NULL,0,'','','normal',0,1,'2014-05-15 21:40:53',1),(7,'Blog post',0,'Blog post used to construct blogs, the text of the post is stored in the WYSIWYG text field','[2-1]','[1]','Blog posts','reserved',2,'Blog post',7,'viewable',0,4,NULL,0,'','','normal',0,1,'2013-08-27 05:15:04',1),(8,'Interpretive annotation',0,'Metadata about a date, spatial extent or other interpretation of information','[2-1] - by [2-15.recTitle] [2-10] transcribed by [2-14.recTitle]','[1] - by [15.recTitle] [10] transcribed by [14.recTitle]','Interpretive annotations','reserved',2,'Interpretation',8,'viewable',1,4,NULL,0,'','','normal',0,1,'2014-05-15 21:40:56',1),(9,'Email',0,'An email including content and metadata, often derived from an email forwarded or ccd to the database\'s linked IMAP server from a user\'s account','[2-1] from: [2-24] on: [2-9]','[1] from: [24] on: [9]','Emails','reserved',2,'Email',9,'viewable',0,4,NULL,0,'','','normal',0,1,'2013-08-27 05:15:04',1),(10,'Person - detailed',33,'A standard record for a person, may be thinned down (eg. removal of modern day contact information) or expanded with additional information as required.','[2-1], [2-18] ([2-19.label]) ([2-10])','[1], [18] ([10])','Persons - detailed','approved',2,'Person',10,'viewable',1,1,NULL,0,'','','normal',0,1,'2014-07-22 05:53:44',1),(11,'Person - minimal',33,'A standard record for a person with minimal information','[2-1], [2-18]','[1], [18] ([10])','Persons - minimal','reserved',2,'Person',10,'viewable',1,1,NULL,0,'','','normal',0,1,'2014-05-15 21:38:30',1),(12,'Place',0,'A place is a humanly constructed definition of space, which may range in scale and sharpness of outline according to use','[2-1]','[1]','Places','approved',3,'Place',1009,'viewable',1,1,'',0,'','','normal',0,1,'2014-05-21 17:57:33',1);
/*!40000 ALTER TABLE `defRecTypes` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecTypes_last_insert`
    AFTER INSERT ON `defRecTypes`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecTypes_last_update`
    AFTER UPDATE ON `defRecTypes`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRecTypes_delete`
    AFTER DELETE ON `defRecTypes`
    FOR EACH ROW
            update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defRelationshipConstraints`
--

DROP TABLE IF EXISTS `defRelationshipConstraints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defRelationshipConstraints` (
  `rcs_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Record-detailtype constraint table primary key',
  `rcs_SourceRectypeID` smallint(5) unsigned DEFAULT NULL COMMENT 'Source record type for this constraint, Null = all types',
  `rcs_TargetRectypeID` smallint(5) unsigned DEFAULT NULL COMMENT 'Target record type pointed to by relationship record, Null = all types',
  `rcs_Description` varchar(1000) DEFAULT 'Please describe ...',
  `rcs_RelationshipsLimit` tinyint(3) unsigned DEFAULT NULL COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet',
  `rcs_Status` enum('reserved','approved','pending','open') NOT NULL DEFAULT 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `rcs_OriginatingDBID` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT 'Database where this constraint originated, 0 or local db code = locally',
  `rcs_IDInOriginatingDB` smallint(5) unsigned DEFAULT '0' COMMENT 'Code used in database where this constraint originated',
  `rcs_TermID` int(10) unsigned DEFAULT NULL COMMENT 'The ID of a term to be constrained, applies to descendants unless they have more specific',
  `rcs_TermLimit` tinyint(2) unsigned DEFAULT NULL COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used',
  `rcs_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rcs_LocallyModified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY (`rcs_ID`),
  UNIQUE KEY `rcs_CompositeKey` (`rcs_SourceRectypeID`,`rcs_TargetRectypeID`,`rcs_TermID`),
  KEY `rcs_TermID` (`rcs_TermID`),
  KEY `rcs_TargetRectypeID` (`rcs_TargetRectypeID`),
  KEY `rcs_SourceRecTypeID` (`rcs_SourceRectypeID`),
  CONSTRAINT `fk_rcs_SourceRecTypeID` FOREIGN KEY (`rcs_SourceRectypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcs_TargetRecTypeID` FOREIGN KEY (`rcs_TargetRectypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcs_TermID` FOREIGN KEY (`rcs_TermID`) REFERENCES `defTerms` (`trm_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Constrain target-rectype/vocabularies/values for a pointer d';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRelationshipConstraints`
--

LOCK TABLES `defRelationshipConstraints` WRITE;
/*!40000 ALTER TABLE `defRelationshipConstraints` DISABLE KEYS */;
/*!40000 ALTER TABLE `defRelationshipConstraints` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRelationshipConstraints_last_insert`
    AFTER INSERT ON `defRelationshipConstraints`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRelationshipConstraints_last_update`
    AFTER UPDATE ON `defRelationshipConstraints`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defRelationshipConstraints_last_delete`
    AFTER DELETE ON `defRelationshipConstraints`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defTerms`
--

DROP TABLE IF EXISTS `defTerms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defTerms` (
  `trm_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key, the term code used in the detail record',
  `trm_Label` varchar(500) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank',
  `trm_InverseTermId` int(10) unsigned DEFAULT NULL COMMENT 'ID for the inverse value (relationships), null if no inverse',
  `trm_Description` varchar(1000) DEFAULT NULL COMMENT 'A description/gloss on the meaning of the term',
  `trm_Status` enum('reserved','approved','pending','open') NOT NULL DEFAULT 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  `trm_OriginatingDBID` mediumint(8) unsigned DEFAULT NULL COMMENT 'Database where this detail type originated, 0 = locally',
  `trm_NameInOriginatingDB` varchar(63) DEFAULT NULL COMMENT 'Name (label) for this term in originating database',
  `trm_IDInOriginatingDB` mediumint(8) unsigned DEFAULT NULL COMMENT 'ID used in database where this  term originated',
  `trm_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Set to 1 if term added by an import, otherwise 0',
  `trm_IsLocalExtension` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag that this value not in the externally referenced vocabulary',
  `trm_Domain` enum('enum','relation') NOT NULL DEFAULT 'enum' COMMENT 'Define the usage of the term',
  `trm_OntID` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Ontology from which this vocabulary originated, 0 = locally defined ontology',
  `trm_ChildCount` tinyint(3) NOT NULL DEFAULT '0' COMMENT 'Stores the count of children, updated whenever children are added/removed',
  `trm_ParentTermID` int(10) unsigned DEFAULT NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  `trm_Depth` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Depth of term in the term tree, should always be 1+parent depth',
  `trm_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `trm_LocallyModified` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  `trm_Code` varchar(100) DEFAULT NULL COMMENT 'Optional code eg. alphanumeric code which may be required for import or export',
  PRIMARY KEY (`trm_ID`),
  KEY `trm_ParentTermIDKey` (`trm_ParentTermID`),
  KEY `trm_InverseTermIDKey` (`trm_InverseTermId`),
  CONSTRAINT `fk_trm_InverseTermId` FOREIGN KEY (`trm_InverseTermId`) REFERENCES `defTerms` (`trm_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trm_ParentTermID` FOREIGN KEY (`trm_ParentTermID`) REFERENCES `defTerms` (`trm_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5044 DEFAULT CHARSET=utf8 COMMENT='Terms by detail type and the vocabulary they belong to';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defTerms`
--

LOCK TABLES `defTerms` WRITE;
/*!40000 ALTER TABLE `defTerms` DISABLE KEYS */;
INSERT INTO `defTerms` VALUES (11,'University',NULL,NULL,'open',2,'University',11,0,0,'enum',0,2,497,1,'2012-06-03 22:18:57',0,NULL),(12,'Funding body',NULL,NULL,'open',2,'Funding body',12,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(13,'Network',NULL,NULL,'open',2,'Network',13,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(14,'Data service',NULL,NULL,'open',2,'Data service',14,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(15,'Department',NULL,NULL,'open',2,'Department',15,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(16,'Laboratory',NULL,NULL,'open',2,'Laboratory',16,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(17,'Centre',NULL,NULL,'open',2,'Centre',17,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(18,'Foundation',NULL,NULL,'open',2,'Foundation',18,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(19,'Ecole Superieure',NULL,NULL,'open',2,'Ecole Superieure',19,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(20,'Institute',NULL,NULL,'open',2,'Institute',20,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(21,'Museum',NULL,NULL,'open',2,'Museum',21,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(93,'Mr',NULL,NULL,'open',2,'Mr',93,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(94,'Mrs',NULL,NULL,'open',2,'Mrs',94,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(95,'Ms',NULL,NULL,'open',2,'Ms',95,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(96,'Dr',NULL,NULL,'open',2,'Dr',96,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(97,'A/Prof.',NULL,NULL,'open',2,'A/Prof.',97,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(98,'Prof.',NULL,NULL,'open',2,'Prof.',98,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(104,'BCE',NULL,NULL,'open',2,'BCE',104,0,0,'enum',0,0,508,1,'2012-06-03 22:18:58',0,NULL),(105,'CE',NULL,NULL,'open',2,'CE',105,0,0,'enum',0,0,508,1,'2012-06-03 22:18:58',0,NULL),(106,'BP',NULL,NULL,'open',2,'BP',106,0,0,'enum',0,0,508,1,'2012-06-03 22:18:58',0,NULL),(107,'Unknown',NULL,NULL,'open',2,'Unknown',107,0,0,'enum',0,0,508,1,'2012-06-03 22:18:58',0,NULL),(108,'Australia',NULL,NULL,'open',2,'Australia',108,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(109,'China',NULL,NULL,'open',2,'China',109,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(110,'France',NULL,NULL,'open',2,'France',110,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(111,'Greece',NULL,NULL,'open',2,'Greece',111,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(112,'Italy',NULL,NULL,'open',2,'Italy',112,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(113,'Spain',NULL,NULL,'open',2,'Spain',113,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(114,'Cambodia',NULL,NULL,'open',2,'Cambodia',114,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(115,'Vietnam',NULL,NULL,'open',2,'Vietnam',115,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(116,'Thailand',NULL,NULL,'open',2,'Thailand',116,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(117,'New Zealand',NULL,NULL,'open',2,'New Zealand',117,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(118,'UK',NULL,NULL,'open',2,'UK',118,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(119,'USA',NULL,NULL,'open',2,'USA',119,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(128,'Government department',NULL,NULL,'open',2,'Government department',128,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(129,'Federal government',NULL,NULL,'open',2,'Federal government',129,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(130,'Company',NULL,NULL,'open',2,'Company',130,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(131,'NGO',NULL,NULL,'open',2,'NGO',131,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(132,'Charity',NULL,NULL,'open',2,'Charity',132,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(133,'Other - ask admin to define',NULL,NULL,'open',2,'Other - ask admin to define',133,0,0,'enum',0,0,497,1,'2012-06-03 22:18:57',0,NULL),(135,'Australia',NULL,NULL,'open',2,'Australia',135,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(136,'S, E & SE Asia',NULL,NULL,'open',2,'S, E & SE Asia',136,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(137,'C & W Asia',NULL,NULL,'open',2,'C & W Asia',137,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(138,'Europe/Mediterranean',NULL,NULL,'open',2,'Europe/Mediterranean',138,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(139,'North America',NULL,NULL,'open',2,'North America',139,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(140,'S & C America',NULL,NULL,'open',2,'S & C America',140,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(141,'Worldwide',NULL,NULL,'open',2,'Worldwide',141,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(142,'Non-regional',NULL,NULL,'open',2,'Non-regional',142,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(147,'Multi-regional',NULL,NULL,'open',2,'Multi-regional',147,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(160,'Cyprus',NULL,NULL,'open',2,'Cyprus',160,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(161,'Jordan',NULL,NULL,'open',2,'Jordan',161,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(162,'UAE',NULL,NULL,'open',2,'UAE',162,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(163,'Uzbekistan',NULL,NULL,'open',2,'Uzbekistan',163,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(164,'Kyrgistan',NULL,NULL,'open',2,'Kyrgistan',164,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(165,'Dubai',NULL,NULL,'open',2,'Dubai',165,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(166,'Israel',NULL,NULL,'open',2,'Israel',166,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(167,'Iran',NULL,NULL,'open',2,'Iran',167,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(168,'Iraq',NULL,NULL,'open',2,'Iraq',168,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(169,'Syria',NULL,NULL,'open',2,'Syria',169,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(170,'Turkey',NULL,NULL,'open',2,'Turkey',170,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(171,'Myanmar',NULL,NULL,'open',2,'Myanmar',171,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(172,'India',NULL,NULL,'open',2,'India',172,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(173,'Singapore',NULL,NULL,'open',2,'Singapore',173,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(177,'Norway',NULL,NULL,'open',2,'Norway',177,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(178,'Netherlands',NULL,NULL,'open',2,'Netherlands',178,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(179,'South Africa',NULL,NULL,'open',2,'South Africa',179,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(180,'Canada',NULL,NULL,'open',2,'Canada',180,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(181,'Ireland',NULL,NULL,'open',2,'Ireland',181,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(182,'Germany',NULL,NULL,'open',2,'Germany',182,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(183,'Hungary',NULL,NULL,'open',2,'Hungary',183,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(184,'Belgium',NULL,NULL,'open',2,'Belgium',184,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(185,'Brazil',NULL,NULL,'open',2,'Brazil',185,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(186,'Sweden',NULL,NULL,'open',2,'Sweden',186,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(195,'Denmark',NULL,NULL,'open',2,'Denmark',195,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(198,'Taiwan',NULL,NULL,'open',2,'Taiwan',198,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(204,'Afghanistan',NULL,NULL,'open',2,'Afghanistan',204,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(205,'Aland Islands',NULL,NULL,'open',2,'Aland Islands',205,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(206,'Albania',NULL,NULL,'open',2,'Albania',206,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(207,'Algeria',NULL,NULL,'open',2,'Algeria',207,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(208,'American Samoa',NULL,NULL,'open',2,'American Samoa',208,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(209,'Andorra',NULL,NULL,'open',2,'Andorra',209,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(210,'Angola',NULL,NULL,'open',2,'Angola',210,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(211,'Anguilla',NULL,NULL,'open',2,'Anguilla',211,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(212,'Antarctica',NULL,NULL,'open',2,'Antarctica',212,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(213,'Antigua and Barbuda',NULL,NULL,'open',2,'Antigua and Barbuda',213,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(214,'Argentina',NULL,NULL,'open',2,'Argentina',214,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(215,'Armenia',NULL,NULL,'open',2,'Armenia',215,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(216,'Aruba',NULL,NULL,'open',2,'Aruba',216,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(217,'Austria',NULL,NULL,'open',2,'Austria',217,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(218,'Azerbaijan',NULL,NULL,'open',2,'Azerbaijan',218,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(219,'Bahamas',NULL,NULL,'open',2,'Bahamas',219,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(220,'Bahrain',NULL,NULL,'open',2,'Bahrain',220,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(221,'Bangladesh',NULL,NULL,'open',2,'Bangladesh',221,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(222,'Barbados',NULL,NULL,'open',2,'Barbados',222,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(223,'Belarus',NULL,NULL,'open',2,'Belarus',223,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(224,'Belize',NULL,NULL,'open',2,'Belize',224,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(225,'Benin',NULL,NULL,'open',2,'Benin',225,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(226,'Bermuda',NULL,NULL,'open',2,'Bermuda',226,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(227,'Bhutan',NULL,NULL,'open',2,'Bhutan',227,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(228,'Bolivia',NULL,NULL,'open',2,'Bolivia',228,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(229,'Bosnia and Herzegovina',NULL,NULL,'open',2,'Bosnia and Herzegovina',229,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(230,'Botswana',NULL,NULL,'open',2,'Botswana',230,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(231,'Bouvet Island',NULL,NULL,'open',2,'Bouvet Island',231,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(232,'British Indian Ocean Territory',NULL,NULL,'open',2,'British Indian Ocean Territory',232,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(233,'Brunei Darussalam',NULL,NULL,'open',2,'Brunei Darussalam',233,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(234,'Bulgaria',NULL,NULL,'open',2,'Bulgaria',234,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(235,'Burkina Faso',NULL,NULL,'open',2,'Burkina Faso',235,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(236,'Burundi',NULL,NULL,'open',2,'Burundi',236,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(237,'Cameroon',NULL,NULL,'open',2,'Cameroon',237,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(238,'Cape Verde',NULL,NULL,'open',2,'Cape Verde',238,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(239,'Cayman Islands',NULL,NULL,'open',2,'Cayman Islands',239,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(240,'Central African Republic',NULL,NULL,'open',2,'Central African Republic',240,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(241,'Chad',NULL,NULL,'open',2,'Chad',241,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(242,'Chile',NULL,NULL,'open',2,'Chile',242,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(243,'Christmas Island',NULL,NULL,'open',2,'Christmas Island',243,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(244,'Cocos (Keeling) Islands',NULL,NULL,'open',2,'Cocos (Keeling) Islands',244,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(245,'Colombia',NULL,NULL,'open',2,'Colombia',245,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(246,'Comoros',NULL,NULL,'open',2,'Comoros',246,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(247,'Congo',NULL,NULL,'open',2,'Congo',247,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(248,'Congo, Dem. Rep. of the',NULL,'','open',2,'Congo, Democratic Republic of the',248,0,0,'enum',0,0,509,1,'2012-10-17 02:51:13',1,''),(249,'Cook Islands',NULL,NULL,'open',2,'Cook Islands',249,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(250,'Costa Rica',NULL,NULL,'open',2,'Costa Rica',250,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(251,'C?te d\'Ivoire',NULL,NULL,'open',2,'C?te d\'Ivoire',251,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(252,'Croatia',NULL,NULL,'open',2,'Croatia',252,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(253,'Cuba',NULL,NULL,'open',2,'Cuba',253,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(254,'Czech Republic',NULL,NULL,'open',2,'Czech Republic',254,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(255,'Djibouti',NULL,NULL,'open',2,'Djibouti',255,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(256,'Dominica',NULL,NULL,'open',2,'Dominica',256,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(257,'Dominican Republic',NULL,NULL,'open',2,'Dominican Republic',257,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(258,'Ecuador',NULL,NULL,'open',2,'Ecuador',258,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(259,'Egypt',NULL,NULL,'open',2,'Egypt',259,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(260,'El Salvador',NULL,NULL,'open',2,'El Salvador',260,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(261,'Equatorial Guinea',NULL,NULL,'open',2,'Equatorial Guinea',261,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(262,'Eritrea',NULL,NULL,'open',2,'Eritrea',262,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(263,'Estonia',NULL,NULL,'open',2,'Estonia',263,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(264,'Ethiopia',NULL,NULL,'open',2,'Ethiopia',264,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(265,'Falkland Islands (malvinas)',NULL,NULL,'open',2,'Falkland Islands (malvinas)',265,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(266,'Faroe Islands',NULL,NULL,'open',2,'Faroe Islands',266,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(267,'Fiji',NULL,NULL,'open',2,'Fiji',267,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(268,'Finland',NULL,NULL,'open',2,'Finland',268,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(269,'French Guiana',NULL,NULL,'open',2,'French Guiana',269,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(270,'French Polynesia',NULL,NULL,'open',2,'French Polynesia',270,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(271,'French Southern Territories',NULL,NULL,'open',2,'French Southern Territories',271,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(272,'Gabon',NULL,NULL,'open',2,'Gabon',272,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(273,'Gambia',NULL,NULL,'open',2,'Gambia',273,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(274,'Georgia',NULL,NULL,'open',2,'Georgia',274,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(275,'Ghana',NULL,NULL,'open',2,'Ghana',275,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(276,'Gibraltar',NULL,NULL,'open',2,'Gibraltar',276,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(277,'Greenland',NULL,NULL,'open',2,'Greenland',277,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(278,'Grenada',NULL,NULL,'open',2,'Grenada',278,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(279,'Guadeloupe',NULL,NULL,'open',2,'Guadeloupe',279,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(280,'Guam',NULL,NULL,'open',2,'Guam',280,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(281,'Guatemala',NULL,NULL,'open',2,'Guatemala',281,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(282,'Guernsey',NULL,NULL,'open',2,'Guernsey',282,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(283,'Guinea',NULL,NULL,'open',2,'Guinea',283,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(284,'Guinea-Bissau',NULL,NULL,'open',2,'Guinea-Bissau',284,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(285,'Guyana',NULL,NULL,'open',2,'Guyana',285,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(286,'Haiti',NULL,NULL,'open',2,'Haiti',286,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(287,'Heard & Mcdonald Islands',NULL,'','open',2,'Heard Island and Mcdonald Islands',287,0,0,'enum',0,0,509,1,'2012-10-17 02:47:19',1,''),(288,'Holy See (Vatican City State)',NULL,NULL,'open',2,'Holy See (Vatican City State)',288,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(289,'Honduras',NULL,NULL,'open',2,'Honduras',289,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(290,'Hong Kong',NULL,NULL,'open',2,'Hong Kong',290,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(291,'Iceland',NULL,NULL,'open',2,'Iceland',291,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(292,'Indonesia',NULL,NULL,'open',2,'Indonesia',292,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(293,'Isle of Man',NULL,NULL,'open',2,'Isle of Man',293,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(294,'Jamaica',NULL,NULL,'open',2,'Jamaica',294,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(295,'Japan',NULL,NULL,'open',2,'Japan',295,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(296,'Jersey',NULL,NULL,'open',2,'Jersey',296,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(297,'Kazakhstan',NULL,NULL,'open',2,'Kazakhstan',297,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(298,'Kenya',NULL,NULL,'open',2,'Kenya',298,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(299,'Kiribati',NULL,NULL,'open',2,'Kiribati',299,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(300,'Korea, Dem. People\'s Rep. of',NULL,'','open',2,'Korea, Democratic People\'s Republic of',300,0,0,'enum',0,0,509,1,'2012-10-17 02:50:53',1,''),(301,'Korea, Republic of',NULL,NULL,'open',2,'Korea, Republic of',301,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(302,'Kuwait',NULL,NULL,'open',2,'Kuwait',302,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(303,'Lao People\'s Dem. Republic',NULL,'','open',2,'Lao People\'s Democratic Republic',303,0,0,'enum',0,0,509,1,'2012-10-17 02:50:42',1,''),(304,'Latvia',NULL,NULL,'open',2,'Latvia',304,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(305,'Lebanon',NULL,NULL,'open',2,'Lebanon',305,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(306,'Lesotho',NULL,NULL,'open',2,'Lesotho',306,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(307,'Liberia',NULL,NULL,'open',2,'Liberia',307,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(308,'Libyan Arab Jamahiriya',NULL,NULL,'open',2,'Libyan Arab Jamahiriya',308,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(309,'Liechtenstein',NULL,NULL,'open',2,'Liechtenstein',309,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(310,'Lithuania',NULL,NULL,'open',2,'Lithuania',310,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(311,'Luxembourg',NULL,NULL,'open',2,'Luxembourg',311,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(312,'Macao',NULL,NULL,'open',2,'Macao',312,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(313,'Macedonia, Frmr Yugoslav Rep. of',NULL,'','open',2,'Macedonia, Former Yugoslav Republic of',313,0,0,'enum',0,0,509,1,'2012-10-17 02:50:28',1,''),(314,'Madagascar',NULL,NULL,'open',2,'Madagascar',314,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(315,'Malawi',NULL,NULL,'open',2,'Malawi',315,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(316,'Malaysia',NULL,NULL,'open',2,'Malaysia',316,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(317,'Maldives',NULL,NULL,'open',2,'Maldives',317,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(318,'Mali',NULL,NULL,'open',2,'Mali',318,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(319,'Malta',NULL,NULL,'open',2,'Malta',319,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(320,'Marshall Islands',NULL,NULL,'open',2,'Marshall Islands',320,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(321,'Martinique',NULL,NULL,'open',2,'Martinique',321,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(322,'Mauritania',NULL,NULL,'open',2,'Mauritania',322,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(323,'Mauritius',NULL,NULL,'open',2,'Mauritius',323,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(324,'Mayotte',NULL,NULL,'open',2,'Mayotte',324,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(325,'Mexico',NULL,NULL,'open',2,'Mexico',325,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(326,'Micronesia, Fed. States of',NULL,'','open',2,'Micronesia, Federated States of',326,0,0,'enum',0,0,509,1,'2012-10-17 02:50:20',1,''),(327,'Moldova, Republic of',NULL,NULL,'open',2,'Moldova, Republic of',327,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(328,'Monaco',NULL,NULL,'open',2,'Monaco',328,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(329,'Mongolia',NULL,NULL,'open',2,'Mongolia',329,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(330,'Montenegro',NULL,NULL,'open',2,'Montenegro',330,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(331,'Montserrat',NULL,NULL,'open',2,'Montserrat',331,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(332,'Morocco',NULL,NULL,'open',2,'Morocco',332,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(333,'Mozambique',NULL,NULL,'open',2,'Mozambique',333,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(334,'Namibia',NULL,NULL,'open',2,'Namibia',334,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(335,'Nauru',NULL,NULL,'open',2,'Nauru',335,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(336,'Nepal',NULL,NULL,'open',2,'Nepal',336,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(337,'Netherlands Antilles',NULL,NULL,'open',2,'Netherlands Antilles',337,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(338,'New Caledonia',NULL,NULL,'open',2,'New Caledonia',338,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(339,'Nicaragua',NULL,NULL,'open',2,'Nicaragua',339,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(340,'Niger',NULL,NULL,'open',2,'Niger',340,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(341,'Nigeria',NULL,NULL,'open',2,'Nigeria',341,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(342,'Niue',NULL,NULL,'open',2,'Niue',342,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(343,'Norfolk Island',NULL,NULL,'open',2,'Norfolk Island',343,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(344,'Northern Mariana Islands',NULL,NULL,'open',2,'Northern Mariana Islands',344,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(345,'Oman',NULL,NULL,'open',2,'Oman',345,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(346,'Pakistan',NULL,NULL,'open',2,'Pakistan',346,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(347,'Palau',NULL,NULL,'open',2,'Palau',347,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(348,'Palestinian Territory, Occupied',NULL,NULL,'open',2,'Palestinian Territory, Occupied',348,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(349,'Panama',NULL,NULL,'open',2,'Panama',349,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(350,'Papua New Guinea',NULL,NULL,'open',2,'Papua New Guinea',350,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(351,'Paraguay',NULL,NULL,'open',2,'Paraguay',351,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(352,'Peru',NULL,NULL,'open',2,'Peru',352,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(353,'Philippines',NULL,NULL,'open',2,'Philippines',353,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(354,'Pitcairn',NULL,NULL,'open',2,'Pitcairn',354,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(355,'Poland',NULL,NULL,'open',2,'Poland',355,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(356,'Portugal',NULL,NULL,'open',2,'Portugal',356,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(357,'Puerto Rico',NULL,NULL,'open',2,'Puerto Rico',357,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(358,'Qatar',NULL,NULL,'open',2,'Qatar',358,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(359,'Reunion',NULL,NULL,'open',2,'Reunion',359,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(360,'Romania',NULL,NULL,'open',2,'Romania',360,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(361,'Russian Federation',NULL,NULL,'open',2,'Russian Federation',361,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(362,'Rwanda',NULL,NULL,'open',2,'Rwanda',362,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(363,'Saint Barth?lemy',NULL,NULL,'open',2,'Saint Barth?lemy',363,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(364,'Saint Helena',NULL,NULL,'open',2,'Saint Helena',364,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(365,'Saint Kitts and Nevis',NULL,NULL,'open',2,'Saint Kitts and Nevis',365,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(366,'Saint Lucia',NULL,NULL,'open',2,'Saint Lucia',366,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(367,'Saint Martin',NULL,NULL,'open',2,'Saint Martin',367,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(368,'Saint Pierre and Miquelon',NULL,NULL,'open',2,'Saint Pierre and Miquelon',368,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(369,'Saint Vincent and the Grenadines',NULL,NULL,'open',2,'Saint Vincent and the Grenadines',369,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(370,'Samoa',NULL,NULL,'open',2,'Samoa',370,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(371,'San Marino',NULL,NULL,'open',2,'San Marino',371,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(372,'Sao Tome and Principe',NULL,NULL,'open',2,'Sao Tome and Principe',372,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(373,'Saudi Arabia',NULL,NULL,'open',2,'Saudi Arabia',373,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(374,'Senegal',NULL,NULL,'open',2,'Senegal',374,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(375,'Serbia',NULL,NULL,'open',2,'Serbia',375,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(376,'Seychelles',NULL,NULL,'open',2,'Seychelles',376,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(377,'Sierra Leone',NULL,NULL,'open',2,'Sierra Leone',377,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(378,'Slovakia',NULL,NULL,'open',2,'Slovakia',378,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(379,'Slovenia',NULL,NULL,'open',2,'Slovenia',379,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(380,'Solomon Islands',NULL,NULL,'open',2,'Solomon Islands',380,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(381,'Somalia',NULL,NULL,'open',2,'Somalia',381,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(382,'South Georgia & S. Sandwich Ils.',NULL,'','open',2,'South Georgia and the South Sandwich Islands',382,0,0,'enum',0,0,509,1,'2012-10-17 02:50:05',1,''),(383,'Sri Lanka',NULL,NULL,'open',2,'Sri Lanka',383,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(384,'Sudan',NULL,NULL,'open',2,'Sudan',384,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(385,'Suriname',NULL,NULL,'open',2,'Suriname',385,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(386,'Svalbard and Jan Mayen',NULL,NULL,'open',2,'Svalbard and Jan Mayen',386,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(387,'Swaziland',NULL,NULL,'open',2,'Swaziland',387,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(388,'Switzerland',NULL,NULL,'open',2,'Switzerland',388,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(389,'Tajikistan',NULL,NULL,'open',2,'Tajikistan',389,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(390,'Tanzania, United Republic of',NULL,NULL,'open',2,'Tanzania, United Republic of',390,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(391,'Timor-Leste',NULL,NULL,'open',2,'Timor-Leste',391,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(392,'Togo',NULL,NULL,'open',2,'Togo',392,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(393,'Tokelau',NULL,NULL,'open',2,'Tokelau',393,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(394,'Tonga',NULL,NULL,'open',2,'Tonga',394,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(395,'Trinidad and Tobago',NULL,NULL,'open',2,'Trinidad and Tobago',395,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(396,'Tunisia',NULL,NULL,'open',2,'Tunisia',396,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(397,'Turkmenistan',NULL,NULL,'open',2,'Turkmenistan',397,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(398,'Turks and Caicos Islands',NULL,NULL,'open',2,'Turks and Caicos Islands',398,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(399,'Tuvalu',NULL,NULL,'open',2,'Tuvalu',399,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(400,'Uganda',NULL,NULL,'open',2,'Uganda',400,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(401,'Ukraine',NULL,NULL,'open',2,'Ukraine',401,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(402,'United States Minor Outlying Islnds',NULL,'','open',2,'United States Minor Outlying Islands',402,0,0,'enum',0,0,509,1,'2012-10-17 02:49:55',1,''),(403,'Uruguay',NULL,NULL,'open',2,'Uruguay',403,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(404,'Vanuatu',NULL,NULL,'open',2,'Vanuatu',404,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(405,'Venezuela',NULL,NULL,'open',2,'Venezuela',405,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(406,'Virgin Islands, British',NULL,NULL,'open',2,'Virgin Islands, British',406,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(407,'Virgin Islands, U.S.',NULL,NULL,'open',2,'Virgin Islands, U.S.',407,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(408,'Wallis and Futuna',NULL,NULL,'open',2,'Wallis and Futuna',408,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(409,'Western Sahara',NULL,NULL,'open',2,'Western Sahara',409,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(410,'Yemen',NULL,NULL,'open',2,'Yemen',410,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(411,'Zambia',NULL,NULL,'open',2,'Zambia',411,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(412,'Zimbabwe',NULL,NULL,'open',2,'Zimbabwe',412,0,0,'enum',0,0,509,1,'2012-06-03 22:18:58',0,NULL),(414,'Male',NULL,NULL,'open',2,'Male',414,0,0,'enum',0,0,513,1,'2012-06-03 22:18:59',0,NULL),(415,'Female',NULL,NULL,'open',2,'Female',415,0,0,'enum',0,0,513,1,'2012-06-03 22:18:59',0,NULL),(449,'Middle East',NULL,NULL,'open',2,'Middle East',449,0,0,'enum',0,0,506,1,'2012-06-03 22:18:58',0,NULL),(460,'English (EN, ENG)',NULL,NULL,'reserved',2,'English (EN, ENG)',460,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(461,'Arabic (AR, ARA)',NULL,NULL,'reserved',2,'Arabic (AR, ARA)',461,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(462,'Yiddish (YI, YID)',NULL,NULL,'reserved',2,'Yiddish (YI, YID)',462,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(463,'Hebrew (HE, HEB)',NULL,NULL,'reserved',2,'Hebrew (HE, HEB)',463,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(464,'French (FR, FRE)',NULL,NULL,'reserved',2,'French (FR, FRE)',464,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(465,'Italian (IT, ITA)',NULL,NULL,'reserved',2,'Italian (IT, ITA)',465,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(466,'Spanish (ES, SPA)',NULL,NULL,'reserved',2,'Spanish (ES, SPA)',466,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(467,'Dutch (NL, DUTENG)',NULL,NULL,'reserved',2,'Dutch (NL, DUTENG)',467,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(468,'Danish (DA, DAN)',NULL,NULL,'reserved',2,'Danish (DA, DAN)',468,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(469,'Norwegian (NO, NOR)',NULL,NULL,'reserved',2,'Norwegian (NO, NOR)',469,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(470,'Portuguese} (PT, POR)',NULL,NULL,'reserved',2,'Portuguese} (PT, POR)',470,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(471,'German (DE, GER)',NULL,NULL,'reserved',2,'German (DE, GER)',471,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(472,'Greek (EL, GRE)',NULL,NULL,'reserved',2,'Greek (EL, GRE)',472,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(473,'Turkish (TR, TUR)',NULL,NULL,'reserved',2,'Turkish (TR, TUR)',473,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(474,'Russian (RU, RUS)',NULL,NULL,'reserved',2,'Russian (RU, RUS)',474,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(475,'Ukrainian (UK, UKR)',NULL,NULL,'reserved',2,'Ukrainian (UK, UKR)',475,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(476,'Swedish (SV, SWE)',NULL,NULL,'reserved',2,'Swedish (SV, SWE)',476,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(477,'Finish (FI, FIN)',NULL,NULL,'reserved',2,'Finish (FI, FIN)',477,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(478,'Latvian (LV, LAV)',NULL,NULL,'reserved',2,'Latvian (LV, LAV)',478,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(479,'Estonian (ET, EST)',NULL,NULL,'reserved',2,'Estonian (ET, EST)',479,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(480,'Hungarian (HU, HUN)',NULL,NULL,'reserved',2,'Hungarian (HU, HUN)',480,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(481,'Czech (CS, CZE)',NULL,NULL,'reserved',2,'Czech (CS, CZE)',481,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(482,'Polish (PL, ENG)',NULL,NULL,'reserved',2,'Polish (PL, ENG)',482,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(483,'Slovak (EN, POL)',NULL,NULL,'reserved',2,'Slovak (EN, POL)',483,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(484,'Serbian (EN, SCR)',NULL,NULL,'reserved',2,'Serbian (EN, SCR)',484,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(485,'Croatian (HR, SCC)',NULL,NULL,'reserved',2,'Croatian (HR, SCC)',485,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(486,'Swahili (SW, SWA)',NULL,NULL,'reserved',2,'Swahili (SW, SWA)',486,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(487,'Chinese (ZH, CHI)',NULL,NULL,'reserved',2,'Chinese (ZH, CHI)',487,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(488,'Indonesian (ID, IND)',NULL,NULL,'reserved',2,'Indonesian (ID, IND)',488,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(489,'Hindi (HI, HIN)',NULL,NULL,'reserved',2,'Hindi (HI, HIN)',489,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(490,'Japanese (JA, JPN)',NULL,NULL,'reserved',2,'Japanese (JA, JPN)',490,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(491,'Malay (MS, MAL)',NULL,NULL,'reserved',2,'Malay (MS, MAL)',491,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(492,'Korean (KO, KOR)',NULL,NULL,'reserved',2,'Korean (KO, KOR)',492,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(493,'Vietnamese (VI, VIE)',NULL,NULL,'reserved',2,'Vietnamese (VI, VIE)',493,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(494,'Thai (TH, THA)',NULL,NULL,'reserved',2,'Thai (TH, THA)',494,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(495,'Khmer (KM, CAM)',NULL,NULL,'reserved',2,'Khmer (KM, CAM)',495,0,0,'enum',0,0,496,1,'2012-06-03 22:18:57',0,NULL),(496,'Language',NULL,'Common languages vocabulary','reserved',2,'Language',496,0,0,'enum',0,36,NULL,0,'2012-06-03 22:18:57',0,NULL),(497,'Organisation type',NULL,NULL,'open',2,'Organisation type',497,0,0,'enum',0,17,NULL,0,'2012-06-03 22:18:57',0,NULL),(506,'Geographic region',NULL,NULL,'open',2,'Geographic region',506,0,0,'enum',0,10,NULL,0,'2012-06-03 22:18:57',0,NULL),(507,'Honorific',NULL,NULL,'open',2,'Honorific',507,0,0,'enum',0,7,NULL,0,'2012-06-03 22:18:57',0,NULL),(508,'Date system',NULL,NULL,'open',2,'Date system',508,0,0,'enum',0,4,NULL,0,'2012-06-03 22:18:57',0,NULL),(509,'Country',NULL,NULL,'open',2,'Country',509,0,0,'enum',0,127,NULL,0,'2012-06-03 22:18:57',0,NULL),(513,'Sex',NULL,'','open',2,'Gender',513,0,0,'enum',0,4,NULL,0,'2013-02-27 23:02:29',1,''),(518,'Private',NULL,NULL,'open',2,'Private',518,0,0,'enum',0,0,11,2,'2012-06-03 22:18:57',0,NULL),(519,'Public',NULL,NULL,'open',2,'Public',519,0,0,'enum',0,0,11,2,'2012-06-03 22:18:57',0,NULL),(527,'Other',NULL,NULL,'open',2,'Other',527,0,0,'enum',0,0,513,1,'2012-06-03 22:18:59',0,NULL),(528,'Unknown',NULL,NULL,'open',2,'Unknown',528,0,0,'enum',0,0,513,1,'2012-06-03 22:18:59',0,NULL),(529,'Lord',NULL,NULL,'open',2,'Lord',529,0,0,'enum',0,0,507,1,'2012-06-03 22:18:58',0,NULL),(530,'Flag',NULL,'A limited option pseudo-Boolean vocabulary ','open',2,'Flag',530,1,0,'enum',0,2,NULL,0,'2012-06-03 22:18:57',0,NULL),(531,'No',NULL,'Negative response','open',2,'No',531,1,0,'enum',0,0,530,1,'2012-06-03 22:18:59',0,NULL),(532,'Yes',NULL,'Affirmative response','open',2,'Yes',532,1,0,'enum',0,0,530,1,'2012-06-03 22:18:59',0,NULL),(3001,'1. Generic',NULL,'','open',2,'Generic',3001,0,0,'relation',0,6,NULL,0,'2013-02-27 22:52:03',1,''),(3005,'Causes',NULL,NULL,'open',2,'Causes',3005,0,0,'relation',0,0,3113,1,'2012-06-03 22:19:00',0,NULL),(3011,'StartsAfter',3012,'','open',2,'StartsAfter',3011,0,0,'relation',0,0,3270,1,'2012-10-15 05:02:56',1,''),(3012,'EndsBefore',3011,'','open',2,'EndsBefore',3012,0,0,'relation',0,0,3270,1,'2012-10-15 05:03:08',1,''),(3083,'IsNeiceOrNephewOf',3084,'','open',2,'IsNeiceOrNephewOf',3083,0,0,'relation',0,0,3259,1,'2012-10-15 04:53:58',1,''),(3084,'IsAuntOrUncleOf',3083,'','open',2,'IsAuntOrUncleOf',3084,0,0,'relation',0,0,3259,1,'2012-10-15 04:54:09',1,''),(3087,'IsBrotherOf',3442,NULL,'open',2,'IsBrotherOf',3087,0,0,'relation',0,0,3118,2,'2012-06-03 22:19:00',0,NULL),(3088,'IsBrotherOrSisterInLaw',3088,'','open',2,'IsBrotherOrSisterInLaw',3088,0,0,'relation',0,0,3258,1,'2012-10-15 04:50:32',1,''),(3089,'IsParentOf',3090,'','open',2,'IsParentOf',3089,0,0,'relation',0,2,3260,1,'2012-10-15 04:55:36',1,''),(3090,'IsChildOf',3089,'','open',2,'IsChildOf',3090,0,0,'relation',0,2,3261,1,'2012-10-15 04:56:13',1,''),(3091,'IsContemporaryWith',3091,'','open',2,'IsContemporaryWith',3091,0,0,'relation',0,0,3269,1,'2012-10-15 05:01:41',1,''),(3092,'IsCousinOf',3092,'','open',2,'IsCousinOf',3092,0,0,'relation',0,0,3259,1,'2012-10-15 04:54:20',1,''),(3094,'IsDaughterOrSonInLaw',3105,'','open',2,'IsDaughterOrSonInLaw',3094,0,0,'relation',0,0,3258,1,'2012-10-15 04:50:09',1,''),(3095,'IsFatherOf',3090,NULL,'open',2,'IsFatherOf',3095,0,0,'relation',0,0,3089,2,'2012-06-03 22:18:59',0,NULL),(3099,'IsGrandFatherOf',3116,NULL,'open',2,'IsGrandfatherOf',3099,0,0,'relation',0,0,3115,2,'2012-06-03 22:18:59',1,NULL),(3100,'IsGrandMotherOf',3116,NULL,'open',2,'IsGrandmotherOf',3100,0,0,'relation',0,0,3115,2,'2012-06-03 22:18:59',1,NULL),(3103,'IsMarriedTo',3103,'','open',2,'IsMarriedTo',3103,0,0,'relation',0,0,3258,1,'2012-10-15 04:49:55',1,''),(3104,'IsMotherOf',3090,NULL,'open',2,'IsMotherOf',3104,0,0,'relation',0,0,3089,2,'2012-06-03 22:18:59',0,NULL),(3105,'IsMotherOrFatherInLaw',3094,'','open',2,'IsMotherOrFatherInLaw',3105,0,0,'relation',0,0,3258,1,'2012-10-15 04:50:21',1,''),(3108,'IsRelatedTo',3108,NULL,'open',2,'IsRelatedTo',3108,0,0,'relation',0,0,3001,1,'2012-06-03 22:18:59',0,NULL),(3110,'4. Family',NULL,'','open',2,'Family',3110,0,0,'relation',0,13,NULL,0,'2013-02-27 22:52:16',1,''),(3113,'3. Temporal',NULL,'','open',2,'Temporal',3113,0,0,'relation',0,4,NULL,0,'2013-02-27 22:52:10',1,''),(3115,'IsGrandParentOf',3116,'','open',2,'IsGrandparentOf',3115,0,0,'relation',0,2,3260,1,'2012-10-15 04:55:47',1,''),(3116,'IsGrandChildOf',3115,'','open',2,'IsGrandchildOf',3116,0,0,'relation',0,2,3261,1,'2012-10-15 04:56:01',1,''),(3117,'IsSisterOf',3118,NULL,'open',2,'IsSisterOf',3117,0,0,'relation',0,0,3118,2,'2012-06-03 22:19:00',0,NULL),(3118,'IsSiblingOf',3118,NULL,'open',2,'IsSiblingOf',3118,0,0,'relation',0,2,3110,1,'2012-06-03 22:18:59',0,NULL),(3133,'IsFamilyMemberOf',3133,'Added from LORE','open',2,'IsFamilyMemberOf',3133,0,0,'relation',0,0,3110,1,'2012-06-03 22:18:59',0,NULL),(3170,'has_location',NULL,NULL,'reserved',2,'has_location',3170,0,0,'relation',0,0,3001,1,'2012-06-03 22:18:59',1,NULL),(3188,'IsDaughterOf',3089,NULL,'open',2,'IsDaughterOf',3188,0,0,'relation',0,0,3090,2,'2012-06-03 22:18:59',0,NULL),(3189,'IsSonOf',3089,NULL,'open',2,'IsSonOf',3189,0,0,'relation',0,0,3090,2,'2012-06-03 22:18:59',0,NULL),(3242,'IsGrandSonOf',3115,NULL,'open',2,'IsGrandSonOf',3242,0,0,'relation',0,0,3116,2,'2012-06-03 22:19:00',1,NULL),(3243,'IsGrandDaughterOf',3115,NULL,'open',2,'IsGrandDaughterOf',3243,0,0,'relation',0,0,3116,2,'2012-06-03 22:19:00',1,NULL),(3258,'byMarriage',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3110,1,'2014-07-23 13:38:32',0,''),(3259,'Colateral relationships',NULL,'Your parents siblings and their offspring','open',NULL,NULL,NULL,0,0,'relation',0,0,3110,1,'2014-07-23 13:38:32',0,''),(3260,'Ancestors',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3110,1,'2014-07-23 13:38:32',0,''),(3261,'Descendants',NULL,'Children and their children','open',NULL,NULL,NULL,0,0,'relation',0,0,3110,1,'2014-07-23 13:38:32',0,''),(3262,'Contains',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3269,1,'2014-07-23 13:38:32',0,''),(3263,'IsContainedWithin',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3269,1,'2014-07-23 13:38:32',0,''),(3264,'IsCoterminousWith',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3269,1,'2014-07-23 13:38:32',0,''),(3265,'ImmediatelyPrecedes',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3270,1,'2014-07-23 13:38:32',0,''),(3266,'ImmediatelyFollows',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3270,1,'2014-07-23 13:38:32',0,''),(3267,'OverlapsStartOf',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3269,1,'2014-07-23 13:38:32',0,''),(3268,'OverlapsEndOf',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3269,1,'2014-07-23 13:38:32',0,''),(3269,'Overlap',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3113,1,'2014-07-23 13:38:32',0,''),(3270,'Sequence',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3113,1,'2014-07-23 13:38:32',0,''),(3271,'IsCausedBy',3005,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3113,1,'2014-07-23 13:38:32',0,''),(3272,'File types',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2014-07-23 13:38:32',0,''),(3273,'Image',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3272,1,'2014-07-23 13:38:32',0,''),(3274,'Sound',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3272,1,'2014-07-23 13:38:32',0,''),(3275,'Video',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3272,1,'2014-07-23 13:38:32',0,''),(3276,'JPG',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3273,1,'2014-07-23 13:38:32',0,''),(3277,'GIF',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3273,1,'2014-07-23 13:38:32',0,''),(3278,'PNG',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3273,1,'2014-07-23 13:38:32',0,''),(3279,'TIF',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3273,1,'2014-07-23 13:38:32',0,''),(3280,'WAV',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3274,1,'2014-07-23 13:38:32',0,''),(3281,'MP3',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3274,1,'2014-07-23 13:38:32',0,''),(3282,'MPEG',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3275,1,'2014-07-23 13:38:32',0,''),(3283,'MP4',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3275,1,'2014-07-23 13:38:32',0,''),(3284,'QT',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3275,1,'2014-07-23 13:38:32',0,''),(3285,'Sir',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,507,1,'2014-07-23 13:38:32',0,''),(3286,'Lady',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,507,1,'2014-07-23 13:38:32',0,''),(3287,'Reverend',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,507,1,'2014-07-23 13:38:32',0,''),(3288,'2. Spatial',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,NULL,1,'2014-07-23 13:38:32',0,''),(3289,'Wholly within',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3288,1,'2014-07-23 13:38:32',0,''),(3290,'Overlaps',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3288,1,'2014-07-23 13:38:32',0,''),(3291,'Wholly outside',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3288,1,'2014-07-23 13:38:32',0,''),(3292,'WebM',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3275,1,'2014-07-23 13:38:32',0,''),(3293,'AVI',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3275,1,'2014-07-23 13:38:32',0,''),(3294,'OGG',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3274,1,'2014-07-23 13:38:32',0,''),(3295,'DNG',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3273,1,'2014-07-23 13:38:32',0,''),(3296,'RAW',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3273,1,'2014-07-23 13:38:32',0,''),(3304,'Publication status (Year â€“ alternate)',NULL,'An alternate statement when Year of publication is not available','open',3,'',5012,1,0,'enum',0,0,NULL,1,'2013-09-10 03:04:53',0,''),(3305,'Event type',NULL,'','open',2,'Event type',501,1,0,'enum',0,0,NULL,0,'2013-09-10 03:04:53',0,''),(3306,'Book launch',NULL,'','open',2,'Book launch',57,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3307,'Colloquium',NULL,'','open',2,'Colloquium',121,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3308,'Committee meeting',NULL,'','open',2,'Committee meeting',123,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3309,'Conference',NULL,'','open',2,'Conference',120,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3310,'Exhibition',NULL,'','open',2,'Exhibition',59,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3311,'Exhibition opening',NULL,'','open',2,'Exhibition opening',58,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3312,'Lecture',NULL,'','open',2,'Lecture',51,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3313,'Masterclass',NULL,'','open',2,'Masterclass',54,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3314,'Meeting',NULL,'','open',2,'Meeting',122,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3315,'Opening or launch',NULL,'','open',2,'Opening or launch',143,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3316,'Public lecture',NULL,'','open',2,'Public lecture',55,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3317,'Recital',NULL,'','open',2,'Recital',56,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3318,'Round table',NULL,'','open',2,'Round table',60,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3319,'Seminar',NULL,'','open',2,'Seminar',52,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3320,'Symposium',NULL,'','open',2,'Symposium',134,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3321,'Workshop',NULL,'','open',2,'Workshop',53,1,0,'enum',0,0,3305,1,'2013-09-10 03:04:53',0,''),(3324,'Colour',NULL,'','open',2,'Colour',551,1,0,'enum',0,0,NULL,0,'2013-09-10 03:04:53',0,''),(3325,'Blue',NULL,'','open',2,'Blue',561,1,0,'enum',0,0,3324,1,'2013-09-10 03:04:53',0,''),(3326,'Green',NULL,'','open',2,'Green',607,1,0,'enum',0,0,3324,1,'2013-09-10 03:04:53',0,''),(3327,'Red',NULL,'','open',2,'Red',671,1,0,'enum',0,0,3324,1,'2013-09-10 03:04:53',0,''),(3328,'Mime Type',NULL,'','open',2,'Mime Type',533,1,0,'enum',0,0,NULL,0,'2013-09-10 03:04:53',0,''),(3329,'image/bmp',NULL,'','open',2,'image/bmp',534,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3330,'image/ermapper',NULL,'','open',2,'image/ermapper',535,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3331,'image/gif',NULL,'','open',2,'image/gif',536,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3332,'image/jpeg',NULL,'','open',2,'image/jpeg',537,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3333,'image/jpeg2000',NULL,'','open',2,'image/jpeg2000',538,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3334,'image/mrsid',NULL,'','open',2,'image/mrsid',539,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3335,'image/png',NULL,'','open',2,'image/png',540,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3336,'image/tiff',NULL,'','open',2,'image/tiff',541,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3337,'image/zoomify',NULL,'','open',2,'image/zoomify',542,1,0,'enum',0,0,3328,1,'2013-09-10 03:04:53',0,''),(3338,'Tiled image type',NULL,'','open',2,'Map image layer type',543,1,0,'enum',0,0,NULL,0,'2013-09-10 03:04:53',1,''),(3339,'image',NULL,'','open',2,'image',544,1,0,'enum',0,0,3338,1,'2013-09-10 03:04:53',0,''),(3340,'map',NULL,'','open',2,'map',545,1,0,'enum',0,0,3338,1,'2013-09-10 03:04:53',0,''),(3341,'Image tiling schema',NULL,'','open',2,'Map image layer tiling schema',546,1,0,'enum',0,0,NULL,0,'2013-09-10 03:04:53',1,''),(3342,'gmapimage',NULL,'','open',2,'gmapimage',547,1,0,'enum',0,0,3341,1,'2013-09-10 03:04:53',0,''),(3343,'maptiler',NULL,'','open',2,'maptiler',548,1,0,'enum',0,0,3341,1,'2013-09-10 03:04:53',0,''),(3344,'virtual earth',NULL,'','open',2,'virtual earth',549,1,0,'enum',0,0,3341,1,'2013-09-10 03:04:53',0,''),(3345,'zoomify',NULL,'','open',2,'zoomify',550,1,0,'enum',0,0,3341,1,'2013-09-10 03:04:53',0,''),(3351,'Thesis type',NULL,'','open',2,'Thesis type',503,1,0,'enum',0,0,NULL,0,'2013-09-10 03:04:53',0,''),(3352,'BA Hons.',NULL,'','open',2,'BA Hons.',68,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3353,'DES',NULL,'','open',2,'DES',74,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3354,'Doctorat 3eme cycle',NULL,'','open',2,'Doctorat 3eme cycle',72,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3355,'Doctorat d\'etat',NULL,'','open',2,'Doctorat d\'etat',73,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3356,'MA',NULL,'','open',2,'MA',69,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3357,'MPhil',NULL,'','open',2,'MPhil',75,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3358,'MSc',NULL,'','open',2,'MSc',70,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3359,'Other',NULL,'','open',2,'Other',76,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(3360,'PhD',NULL,'','open',2,'PhD',71,1,0,'enum',0,0,3351,1,'2013-09-10 03:04:53',0,''),(5013,'No date',NULL,'Date of publication is not known','open',3,'No date',5013,1,0,'enum',0,0,3304,1,'2013-09-10 03:34:13',0,''),(5014,'Forthcoming',NULL,'Work has been accepted but not finalised','open',3,'Forthcoming',5014,1,0,'enum',0,0,3304,1,'2013-09-10 03:34:13',0,''),(5015,'In press',NULL,'Work has been finalised but not published','open',3,'In press',5015,1,0,'enum',0,0,3304,1,'2013-09-10 03:34:13',0,''),(5016,'Early view',NULL,'Work is available prior to formal publication','open',3,'Early view',5016,1,0,'enum',0,0,3304,1,'2013-09-10 03:34:13',0,''),(5017,'5. Bibliographic',NULL,'','open',3,'Bibliographic',5017,1,0,'relation',0,0,NULL,1,'2014-07-23 13:38:32',1,''),(5018,'Edited by',NULL,'','open',3,'Edited by',5018,1,0,'relation',0,0,5017,1,'2014-07-23 13:38:32',0,''),(5021,'Bibliographic terms',NULL,'','open',3,'Bibliographic terms',5021,1,0,'enum',0,0,NULL,1,'2013-09-10 03:34:13',0,''),(5022,'Edited',NULL,'','open',3,'Edited',5022,1,0,'enum',0,0,5021,1,'2013-09-10 03:34:13',0,''),(5023,'Translated by',NULL,'','open',3,'Translated by',5023,1,0,'relation',0,0,5017,1,'2014-07-23 13:38:32',0,''),(5024,'Archival record types',NULL,'','open',3,'Archival record types',5024,1,0,'enum',0,0,NULL,1,'2013-09-10 03:34:13',0,''),(5025,'Manuscript',NULL,'','open',3,'Manuscript',5025,1,0,'enum',0,0,5024,1,'2013-09-10 03:34:13',0,''),(5026,'Letter',NULL,'','open',3,'Letter',5026,1,0,'enum',0,0,5024,1,'2013-09-10 03:34:13',0,''),(5027,'Contributed by',NULL,'','open',3,'Contributed by',5027,1,0,'relation',0,0,5017,1,'2014-07-23 13:38:32',0,''),(5030,'Artwork type',NULL,'','open',3,'Artwork type',5030,1,0,'enum',0,0,NULL,1,'2013-09-10 03:34:13',0,''),(5031,'Sculpture',NULL,'','open',3,'Sculpture',5031,1,0,'enum',0,0,5030,1,'2014-07-23 13:38:32',0,''),(5032,'Painting',NULL,'','open',3,'Painting',5032,1,0,'enum',0,0,5030,1,'2014-07-23 13:38:32',0,''),(5033,'Drawing',NULL,'','open',3,'Drawing',5033,1,0,'enum',0,0,5030,1,'2014-07-23 13:38:32',0,''),(5034,'Engraving',NULL,'','open',3,'Engraving',5034,1,0,'enum',0,0,5030,1,'2014-07-23 13:38:32',0,''),(5035,'Installation',NULL,'','open',3,'Installation',5035,1,0,'enum',0,0,5030,1,'2014-07-23 13:38:32',0,''),(5036,'Performance',NULL,'','open',3,'Performance',5036,1,0,'enum',0,0,5030,1,'2014-07-23 13:38:32',0,''),(5037,'Watercolour',NULL,'','open',3,'Watercolour',5037,1,0,'enum',0,0,5032,1,'2014-07-23 13:38:32',0,''),(5038,'Oil painting',NULL,'','open',3,'Oil painting',5038,1,0,'enum',0,0,5032,1,'2014-07-23 13:38:32',0,''),(5039,'Place type',NULL,'','open',3,'',5039,1,0,'enum',0,0,NULL,1,'2014-02-28 02:21:03',0,''),(5040,'Adminstrative',NULL,'','open',3,'',5040,1,0,'enum',0,0,5039,1,'2014-07-23 13:38:32',0,''),(5041,'Geometric',NULL,'','open',3,'',5041,1,0,'enum',0,0,5039,1,'2014-07-23 13:38:32',0,''),(5042,'Thematic',NULL,'','open',3,'',5042,1,0,'enum',0,0,5039,1,'2014-07-23 13:38:32',0,''),(5043,'Conceptual',NULL,'','open',3,'',5043,1,0,'enum',0,0,5039,1,'2014-07-23 13:38:32',0,'');
/*!40000 ALTER TABLE `defTerms` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defTerms_last_insert`
    AFTER INSERT ON `defTerms`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defTerms_last_update`
    AFTER UPDATE ON `defTerms`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `defTerms_last_delete`
    AFTER DELETE ON `defTerms`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defTranslations`
--

DROP TABLE IF EXISTS `defTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defTranslations` (
  `trn_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of defTranslations table',
  `trn_Source` enum('rty_Name','dty_Name','ont_ShortName','vcb_Name','trm_Label','rst_DisplayName','rtg_Name','dtl_Value') NOT NULL COMMENT 'The table/column to be translated (unique names identify source)',
  `trn_Code` smallint(5) unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
  `trn_LanguageCode3` char(3) NOT NULL COMMENT 'The translation language code (NISO 3 character) for this record',
  `trn_Translation` varchar(63) NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
  `trn_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`trn_ID`),
  UNIQUE KEY `trn_composite` (`trn_Source`,`trn_Code`,`trn_LanguageCode3`),
  KEY `trn_LanguageCode3` (`trn_LanguageCode3`),
  CONSTRAINT `fk_trn_LanguageCode3` FOREIGN KEY (`trn_LanguageCode3`) REFERENCES `defLanguages` (`lng_NISOZ3953`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Translation table into multiple languages for all translatab';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defURLPrefixes` (
  `urp_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID which will be stored as proxy for the URL prefix',
  `urp_Prefix` varchar(250) NOT NULL COMMENT 'URL prefix which is prepended to record URLs',
  `urp_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`urp_ID`),
  UNIQUE KEY `urp_Prefix` (`urp_Prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Common URL prefixes allowing single-point change of URL for ';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recDetails` (
  `dtl_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key for record detail (field) values table',
  `dtl_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which this detail (field) applies',
  `dtl_DetailTypeID` smallint(5) unsigned NOT NULL COMMENT 'The detail type code identifying the type definition of data',
  `dtl_Value` text COMMENT 'The value of the detail as text (used for all except files and geometry)',
  `dtl_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Set 1 if added by an import, set 0 if added by user during data entry',
  `dtl_UploadedFileID` mediumint(8) unsigned DEFAULT NULL COMMENT 'The numeric code = filename of an uploaded file ',
  `dtl_Geo` geometry DEFAULT NULL COMMENT 'A geometry (spatial) object',
  `dtl_ValShortened` varchar(31) NOT NULL default '' COMMENT 'Truncated version of the textual value without spaces',
  `dtl_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record detail, used to get last updated date for table',
  PRIMARY KEY (`dtl_ID`),
  KEY `dtl_DetailtypeIDkey` (`dtl_DetailTypeID`),
  KEY `dtl_RecIDKey` (`dtl_RecID`),
  KEY `dtl_ValShortenedKey` (`dtl_ValShortened`),
  KEY `dtl_ValueKey` (`dtl_Value`(63)),
  KEY `dtl_UploadedFileIDKey` (`dtl_UploadedFileID`),
  CONSTRAINT `fk_dtl_DetailTypeID` FOREIGN KEY (`dtl_DetailTypeID`) REFERENCES `defDetailTypes` (`dty_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_dtl_RecID` FOREIGN KEY (`dtl_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dtl_UploadedFileID` FOREIGN KEY (`dtl_UploadedFileID`) REFERENCES `recUploadedFiles` (`ulf_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The detail (field) values for each record - public data';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recDetails`
--

LOCK TABLES `recDetails` WRITE;
/*!40000 ALTER TABLE `recDetails` DISABLE KEYS */;
/*!40000 ALTER TABLE `recDetails` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `insert_Details_precis_trigger`
    BEFORE INSERT ON `recDetails`
    FOR EACH ROW
        begin set NEW.dtl_ValShortened = ifnull(NEW_LIPOSUCTION(NEW.dtl_Value), ''); end */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `insert_Details_trigger`
    AFTER INSERT ON `recDetails`
    FOR EACH ROW
    begin











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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `pre_update_Details_trigger`
    BEFORE UPDATE ON `recDetails`
    FOR EACH ROW
    begin
        if asbinary(NEW.dtl_Geo)=asbinary(OLD.dtl_Geo) then
            set NEW.dtl_Geo = OLD.dtl_Geo;
        end if;
        set NEW.dtl_ValShortened = ifnull(NEW_LIPOSUCTION(NEW.dtl_Value), '');
    end */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `update_Details_trigger`
    AFTER UPDATE ON `recDetails`
    FOR EACH ROW
    begin











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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `recForwarding`
--

DROP TABLE IF EXISTS `recForwarding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recForwarding` (
  `rfw_OldRecID` int(10) unsigned NOT NULL COMMENT 'The deleted record which will be redirected to another',
  `rfw_NewRecID` int(10) unsigned NOT NULL COMMENT 'The new record to which this ID will be forwarded',
  PRIMARY KEY (`rfw_OldRecID`),
  KEY `rfw_NewRecID` (`rfw_NewRecID`),
  CONSTRAINT `fk_rfw_NewRecID` FOREIGN KEY (`rfw_NewRecID`) REFERENCES `Records` (`rec_ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Allows referer routine to redirect certain calls to a replac';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recRelationshipsCache` (
  `rrc_RecID` int(10) unsigned NOT NULL COMMENT 'Record ID of a relationships record linking source and target records',
  `rrc_SourceRecID` int(10) unsigned NOT NULL COMMENT 'Pointer to source record for this relationship',
  `rrc_TargetRecID` int(10) unsigned NOT NULL COMMENT 'Pointer to target record for this relationship',
  PRIMARY KEY (`rrc_RecID`),
  KEY `rrc_sourcePtrKey` (`rrc_SourceRecID`),
  KEY `rrc_TargetPtrKey` (`rrc_TargetRecID`),
  CONSTRAINT `fk_rrc_SourceRecID` FOREIGN KEY (`rrc_SourceRecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rrc_TargetRecID` FOREIGN KEY (`rrc_TargetRecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='A cache for record relationship pointers to speed access';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recSimilarButNotDupes` (
  `snd_SimRecsList` varchar(16000) NOT NULL COMMENT 'A comma separated list of record IDs which are similar but not identical',
  KEY `snd_SimRecsList` (`snd_SimRecsList`(13))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Used in dedupe. Sets of IDs which are not dupes. Index is of';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recThreadedComments` (
  `cmt_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Comment ID, primary key for comments',
  `cmt_Text` varchar(5000) NOT NULL COMMENT 'Text of comment',
  `cmt_OwnerUgrpID` smallint(5) unsigned NOT NULL COMMENT 'User ID of user making comment',
  `cmt_Added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Date and time of creation of comment',
  `cmt_ParentCmtID` int(10) unsigned DEFAULT NULL COMMENT 'Parent comment of this comment',
  `cmt_Deleted` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag deleted comments',
  `cmt_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time of modification of comment',
  `cmt_RecID` int(10) unsigned NOT NULL COMMENT 'Record ID to which this comment applies, required',
  PRIMARY KEY (`cmt_ID`),
  KEY `cmt_OwnerUgrpID` (`cmt_OwnerUgrpID`),
  KEY `cmt_ParentCmtID` (`cmt_ParentCmtID`),
  KEY `cmt_RecID` (`cmt_RecID`),
  CONSTRAINT `fk_cmt_OwnerUgrpID` FOREIGN KEY (`cmt_OwnerUgrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cmt_ParentCmtID` FOREIGN KEY (`cmt_ParentCmtID`) REFERENCES `recThreadedComments` (`cmt_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cmt_RecID` FOREIGN KEY (`cmt_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Threaded comments for each record';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recUploadedFiles` (
  `ulf_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT 'A unique numeric file ID used as filename to store the data on disk and should be different ids if shared',
  `ulf_OrigFileName` varchar(255) NOT NULL COMMENT 'The original name of the file uploaded',
  `ulf_UploaderUGrpID` smallint(5) unsigned DEFAULT NULL COMMENT 'The user who uploaded the file',
  `ulf_Added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'The date and time the file was uploaded',
  `ulf_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The date of last modification of the file description record, automatic update',
  `ulf_ObfuscatedFileID` varchar(40) DEFAULT NULL COMMENT 'SHA-1 hash of ulf_ID and random number to block sequential file harvesting',
  `ulf_ExternalFileReference` varchar(1000) DEFAULT NULL COMMENT 'URI of an external file, which may or may not be cached locally',
  `ulf_PreferredSource` enum('local','external') NOT NULL DEFAULT 'local' COMMENT 'Preferred source of file if both local file and external reference set',
  `ulf_Thumbnail` blob COMMENT 'Cached autogenerated thumbnail for common image formats',
  `ulf_Description` text COMMENT 'A user-entered textual description of the file or image contents',
  `ulf_MimeExt` varchar(10) DEFAULT NULL COMMENT 'Extension of the file, used to look up in mimetype table',
  `ulf_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag whether added by import = 1 or manual editing = 0',
  `ulf_FileSizeKB` int(10) unsigned DEFAULT NULL COMMENT 'File size in Kbytes calculated at upload',
  `ulf_FilePath` varchar(1024) DEFAULT NULL COMMENT 'The path where the uploaded file is stored',
  `ulf_FileName` varchar(512) DEFAULT NULL COMMENT 'The filename for the uploaded file',
  `ulf_Parameters` text COMMENT 'Parameters including source (flickr,youtube...), default player etc. used to determine special processing',
  PRIMARY KEY (`ulf_ID`),
  KEY `ulf_ObfuscatedFileIDKey` (`ulf_ObfuscatedFileID`),
  KEY `ulf_Description` (`ulf_Description`(100)),
  KEY `ulf_UploaderUGrpID` (`ulf_UploaderUGrpID`),
  KEY `ulf_MimeExt` (`ulf_MimeExt`),
  CONSTRAINT `fk_ulf_MimeExt` FOREIGN KEY (`ulf_MimeExt`) REFERENCES `defFileExtToMimetype` (`fxm_Extension`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ulf_UploaderUGrpID` FOREIGN KEY (`ulf_UploaderUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Index to uploaded files linked from records';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysArchive` (
  `arc_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of archive table',
  `arc_Table` enum('rec','cfn','crw','dtg','dty','fxm','ont','rst','rtg','rty','rcs','trm','trn','urp','vcb','dtl','rfw','rrc','snd','cmt','ulf','sys','lck','tlu','ugr','ugl','bkm','hyf','rtl','rre','rem','rbl','svs','tag','wprm','chunk','wrprm','woot') NOT NULL COMMENT 'Identification of the MySQL table in which a record is being modified',
  `arc_PriKey` int(10) unsigned NOT NULL COMMENT 'Primary key of the MySQL record in the table being modified',
  `arc_ChangedByUGrpID` smallint(5) unsigned NOT NULL COMMENT 'User who is logged in and modifying this data',
  `arc_OwnerUGrpID` smallint(5) unsigned DEFAULT NULL COMMENT 'Owner of the data being modified (if applicable eg. records, bookmarks, tags)',
  `arc_RecID` int(10) unsigned DEFAULT NULL COMMENT 'Heurist record id (if applicable, eg. for records, bookmarks, tag links)',
  `arc_TimeOfChange` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of the modification',
  `arc_DataBeforeChange` blob COMMENT 'A representation of the data in the MySQL record before the mod, may be a diff',
  `arc_ContentType` enum('del','raw','zraw','diff','zdiff') NOT NULL DEFAULT 'raw' COMMENT 'Format of the data stored, del=deleted, raw=text dump, Diff=delta, Z=zipped indicates ',
  PRIMARY KEY (`arc_ID`),
  KEY `arc_Table` (`arc_Table`,`arc_ChangedByUGrpID`,`arc_OwnerUGrpID`,`arc_RecID`,`arc_TimeOfChange`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='An archive of all (or most) changes in the database to allow';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysDBNameCache` (
  `dnc_ID` int(10) unsigned NOT NULL COMMENT 'Heurist DB ID for a registered Heurist database',
  `dnc_dbName` varchar(63) NOT NULL COMMENT 'Name of the database (from sys_DBName or Heurist index database)',
  `dnc_TimeStamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date when name of database last read from Heurist index or the database',
  `dnc_URL` varchar(128) DEFAULT NULL COMMENT 'Root path to this installation and database',
  PRIMARY KEY (`dnc_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Local db name cache for display of origin and source DBs in ';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysDocumentation` (
  `doc_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `doc_text` text COMMENT 'Relevant documentation as text',
  PRIMARY KEY (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Descriptive infORmation about this database and its function';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysIdentification` (
  `sys_ID` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Only 1 record should exist in this table',
  `sys_dbRegisteredID` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Allocated by HeuristScholar.org, 0 indicates not yet registered',
  `sys_dbVersion` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Major version for the database structure',
  `sys_dbSubVersion` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Sub version',
  `sys_dbSubSubVersion` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Sub-sub version',
  `sys_eMailImapServer` varchar(100) DEFAULT NULL COMMENT 'Email server intermediary for record creation via email',
  `sys_eMailImapPort` varchar(5) DEFAULT NULL COMMENT 'port for imap email server',
  `sys_eMailImapProtocol` varchar(5) DEFAULT NULL COMMENT 'protocol for imap email server',
  `sys_eMailImapUsername` varchar(50) DEFAULT NULL COMMENT 'user name for imap email server',
  `sys_eMailImapPassword` varchar(40) DEFAULT NULL COMMENT 'password for imap email server',
  `sys_IncomingEmailAddresses` varchar(4000) DEFAULT NULL COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins',
  `sys_TargetEmailAddresses` varchar(255) DEFAULT NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses',
  `sys_UGrpsDatabase` varchar(63) DEFAULT NULL COMMENT 'Full name of SQL database containing user tables, null = use internal users/groups tables',
  `sys_OwnerGroupID` smallint(5) unsigned NOT NULL DEFAULT '1' COMMENT 'User group which owns/administers this database, 1 by default',
  `sys_dbName` varchar(63) NOT NULL DEFAULT 'Please enter a DB name ...' COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  `sys_dbOwner` varchar(250) DEFAULT NULL COMMENT 'Information on the owner of the database, may be a URL reference',
  `sys_dbRights` varchar(1000) NOT NULL DEFAULT 'Please define ownership and rights here ...' COMMENT 'A statement of ownership and copyright for this database and content',
  `sys_dbDescription` varchar(1000) DEFAULT NULL COMMENT 'A longer description of the content of this database',
  `sys_SyncDefsWithDB` varchar(63) DEFAULT NULL COMMENT 'The name of the SQL database with which local definitions are to be synchronised',
  `sys_AutoIncludeFieldSetIDs` varchar(63) DEFAULT '0' COMMENT 'CSV list of fieldsets which are included in all rectypes',
  `sys_RestrictAccessToOwnerGroup` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'If set, database may only be accessed by members of owners group',
  `sys_URLCheckFlag` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flags whether system should send out requests to URLs to test for validity',
  `sys_UploadDirectory` varchar(128) DEFAULT NULL COMMENT 'Absolute directory path for uploaded files (blank = use default from installation)',
  `sys_hmlOutputDirectory` varchar(255) DEFAULT NULL COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory',
  `sys_htmlOutputDirectory` varchar(255) DEFAULT NULL COMMENT 'Directory in which to write html representation of published records, default to html within upload directory',
  `sys_NewRecOwnerGrpID` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  `sys_NewRecAccess` enum('viewable','hidden','public','pending') NOT NULL DEFAULT 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  `sys_SetPublicToPendingOnEdit` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''',
  `sys_ConstraintDefaultBehavior` enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL DEFAULT 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  `sys_AllowRegistration` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'If set, people can apply for registration through web-based form',
  `sys_MediaFolders` varchar(10000) DEFAULT NULL COMMENT 'Additional comma-sep directories which can contain files indexed in database',
  `sys_MediaExtensions` varchar(1024) DEFAULT 'jpg,png,gif,tif,tiff,wmv,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff' COMMENT 'The file extensions to be harvested from the MediaFolders directories',
  PRIMARY KEY (`sys_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Identification/version for this Heurist database (single rec';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysIdentification`
--

LOCK TABLES `sysIdentification` WRITE;
/*!40000 ALTER TABLE `sysIdentification` DISABLE KEYS */;
INSERT INTO `sysIdentification` VALUES (1,2,1,1,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'Please enter a database name here ...','2','Please define ownership and rights here ...','Based on Heurist core definitions (required and very common record types)',NULL,'0',0,0,NULL,NULL,NULL,0,'viewable',0,'locktypetotype',1,NULL,'jpg,png,gif,tif,tiff,wmv,,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff,');
/*!40000 ALTER TABLE `sysIdentification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysLocks`
--

DROP TABLE IF EXISTS `sysLocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysLocks` (
  `lck_Action` enum('buildcrosswalks','editdefinitions','errorscan','buildtempdb') NOT NULL COMMENT 'Type of action being carried out',
  `lck_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'Set to user ID to lock specified function',
  `lck_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when the action was commenced, use to drop old locks',
  PRIMARY KEY (`lck_Action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Provides token system for selected administrative actions';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysTableLastUpdated` (
  `tlu_TableName` varchar(40) NOT NULL COMMENT 'Name of table for which we are recording time of last update',
  `tlu_DateStamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Date and time of last update of table',
  `tlu_CommonObj` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Indicates tables which contain data defs required in common-obj',
  PRIMARY KEY (`tlu_TableName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Datestamp, determines if updates since definitions loaded in';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysTableLastUpdated`
--

LOCK TABLES `sysTableLastUpdated` WRITE;
/*!40000 ALTER TABLE `sysTableLastUpdated` DISABLE KEYS */;
INSERT INTO `sysTableLastUpdated` VALUES ('defCalcFunctions','0000-00-00 00:00:00',1),('defCrosswalk','0000-00-00 00:00:00',1),('defDetailTypeGroups','2014-05-22 03:57:11',1),('defDetailTypes','2014-07-22 16:02:31',1),('defFileExtToMimetype','0000-00-00 00:00:00',1),('defLanguages','0000-00-00 00:00:00',1),('defOntologies','0000-00-00 00:00:00',1),('defRecStructure','2014-07-22 16:01:53',1),('defRecTypeGroups','2014-01-20 13:31:12',1),('defRecTypes','2014-07-22 16:01:53',1),('defRelationshipConstraints','0000-00-00 00:00:00',1),('defTerms','2014-07-23 23:38:32',1),('defTranslations','0000-00-00 00:00:00',1),('defURLPrefixes','0000-00-00 00:00:00',1),('sysDBNameCache','0000-00-00 00:00:00',1),('sysIdentification','0000-00-00 00:00:00',1),('sysUGrps','2014-07-23 23:26:00',1),('sysUsrGrpLinks','2012-05-17 16:34:52',1),('usrHyperlinkFilters','0000-00-00 00:00:00',1),('usrTags','0000-00-00 00:00:00',1);
/*!40000 ALTER TABLE `sysTableLastUpdated` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysUGrps`
--

DROP TABLE IF EXISTS `sysUGrps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysUGrps` (
  `ugr_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'User or group ID, used wherever a user or group is to be identified',
  `ugr_Type` enum('user','workgroup','ugradclass') NOT NULL DEFAULT 'user' COMMENT 'User or workgroup, special workgroup types also supported',
  `ugr_Name` varchar(63) NOT NULL COMMENT 'The unique user/login/group name, user name defaults to email address',
  `ugr_LongName` varchar(128) DEFAULT NULL COMMENT 'An optional longer descriptive name for a user group',
  `ugr_Description` varchar(1000) DEFAULT NULL COMMENT 'Extended description of a user group displayed on homepage',
  `ugr_Password` varchar(40) NOT NULL COMMENT 'Encrypted password string',
  `ugr_eMail` varchar(100) NOT NULL COMMENT 'Contact email address of the user/group',
  `ugr_FirstName` varchar(40) DEFAULT NULL COMMENT 'Person''s first name, only for Users, not Workgroups',
  `ugr_LastName` varchar(63) DEFAULT NULL COMMENT 'Person''s last name, only for Users, not Workgroups',
  `ugr_Department` varchar(120) DEFAULT NULL,
  `ugr_Organisation` varchar(120) DEFAULT NULL,
  `ugr_City` varchar(63) DEFAULT NULL,
  `ugr_State` varchar(40) DEFAULT NULL,
  `ugr_Postcode` varchar(20) DEFAULT NULL,
  `ugr_Interests` varchar(255) DEFAULT NULL COMMENT 'List of research interests, only for Users, not Workgroups',
  `ugr_Enabled` enum('y','n') NOT NULL DEFAULT 'y' COMMENT 'Flags if user can use Heurist, normally needs authorising by admin',
  `ugr_LastLoginTime` datetime DEFAULT NULL COMMENT 'Date and time of last login (but user may stay logged in)',
  `ugr_MinHyperlinkWords` tinyint(3) unsigned NOT NULL DEFAULT '3' COMMENT 'Filter hyperlink strings with less than this word count on hyperlink import ',
  `ugr_LoginCount` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of times user haslogged in',
  `ugr_IsModelUser` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '1 indicates model user = domain profile',
  `ugr_IncomingEmailAddresses` varchar(4000) DEFAULT NULL COMMENT 'Comma-sep list of incoming email addresses from which to archive emails',
  `ugr_TargetEmailAddresses` varchar(255) DEFAULT NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also sys_TargetEmailAddresses',
  `ugr_URLs` varchar(2000) DEFAULT NULL COMMENT 'URL(s) of group or personal website(s), comma separated',
  `ugr_FlagJT` int(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag to enable in Jobtrack/Worktrack application',
  `ugr_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `ugr_NavigationTree` text default NULL COMMENT 'JSON array that describes treeview for filters',
  PRIMARY KEY (`ugr_ID`),
  UNIQUE KEY `ugr_Name` (`ugr_Name`),
  UNIQUE KEY `ugr_eMail` (`ugr_eMail`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='Users/Groups diff. by ugr_Type. May defer to similar table i';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysUGrps`
--

LOCK TABLES `sysUGrps` WRITE;
/*!40000 ALTER TABLE `sysUGrps` DISABLE KEYS */;
INSERT INTO `sysUGrps` VALUES (0,'workgroup','All users','All users','A dummy workgroup representing all users of the system. There are no user group - user links in the sysUsrGrpLinks table for this group, they are implied','Password not set for this group','none@junk.com','All','Users',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'0000-00-00 00:00:00'),(1,'workgroup','Database owners','Group 1 owns databases by default. DO NOT DELETE.',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=1','db','owners',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'0000-00-00 00:00:00'),(2,'user','not set','',NULL,'','not set','not set','not set','not set','not set','','','','...','y','2014-07-23 23:26:00',3,74,0,NULL,NULL,NULL,0,'2014-07-23 13:26:00');
/*!40000 ALTER TABLE `sysUGrps` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `sysUGrps_last_insert`
    AFTER INSERT ON `sysUGrps`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `sysUGrps_last_update`
    AFTER UPDATE ON `sysUGrps`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `sysUsrGrpLinks`
--

DROP TABLE IF EXISTS `sysUsrGrpLinks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysUsrGrpLinks` (
  `ugl_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key for user-to-group membership',
  `ugl_UserID` smallint(5) unsigned NOT NULL COMMENT 'The user being assigned to a group',
  `ugl_GroupID` smallint(5) unsigned NOT NULL COMMENT 'The group to which this user is being assigned',
  `ugl_Role` enum('admin','member') NOT NULL DEFAULT 'member' COMMENT 'The role of this user in the group - member, admin',
  PRIMARY KEY (`ugl_ID`),
  UNIQUE KEY `ugl_CompositeKey` (`ugl_UserID`,`ugl_GroupID`),
  KEY `ugl_GroupID` (`ugl_GroupID`),
  CONSTRAINT `fk_ugl_GroupID` FOREIGN KEY (`ugl_GroupID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ugl_UserID` FOREIGN KEY (`ugl_UserID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysUsrGrpLinks`
--

LOCK TABLES `sysUsrGrpLinks` WRITE;
/*!40000 ALTER TABLE `sysUsrGrpLinks` DISABLE KEYS */;
INSERT INTO `sysUsrGrpLinks` VALUES (1,2,1,'admin');
/*!40000 ALTER TABLE `sysUsrGrpLinks` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `sysUsrGrpLinks_last_insert`
    AFTER INSERT ON `sysUsrGrpLinks`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `sysUsrGrpLinks_last_update`
    AFTER UPDATE ON `sysUsrGrpLinks`
    FOR EACH ROW
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks" */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `usrBookmarks`
--

DROP TABLE IF EXISTS `usrBookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrBookmarks` (
  `bkm_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key, ID for bookmarks',
  `bkm_Added` datetime NOT NULL COMMENT 'Date and time of addition of bookmark',
  `bkm_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time of modification',
  `bkm_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'Owner of the bookmark - all bookmarks must be owned',
  `bkm_PwdReminder` varchar(250) DEFAULT NULL COMMENT 'Password reminder field or short notes about access',
  `bkm_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which this bookmark applies, must be set',
  `bkm_Rating` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Five point rating for interest/quality/content',
  `bkm_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Set to 1 if bookmark added by import, 0 if added by data entry',
  `bkm_ZoteroID` int(10) unsigned DEFAULT NULL COMMENT 'Records your Zotero ID for this record for synchronisation with Zotero',
  PRIMARY KEY (`bkm_ID`),
  UNIQUE KEY `bkm_RecID` (`bkm_RecID`,`bkm_UGrpID`),
  KEY `bkm_UGrpID` (`bkm_UGrpID`),
  KEY `bkm_Modified` (`bkm_Modified`),
  CONSTRAINT `fk_bkm_RecID` FOREIGN KEY (`bkm_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bkm_UGrpID` FOREIGN KEY (`bkm_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bookmark = personal data relating to a record, one for each ';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrBookmarks`
--

LOCK TABLES `usrBookmarks` WRITE;
/*!40000 ALTER TABLE `usrBookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrBookmarks` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `usrBookmarks_update`
    BEFORE UPDATE ON `usrBookmarks`
    FOR EACH ROW
        set NEW.bkm_Modified = now() */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `usrHyperlinkFilters`
--

DROP TABLE IF EXISTS `usrHyperlinkFilters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrHyperlinkFilters` (
  `hyf_String` varchar(63) NOT NULL COMMENT 'Hyperlink string to be ignored when encountered in hyperlink import',
  `hyf_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'User for which this string is to be ignored',
  UNIQUE KEY `hyf_CompositeKey` (`hyf_String`,`hyf_UGrpID`),
  KEY `hyf_UGrpID` (`hyf_UGrpID`),
  CONSTRAINT `fk_hyf_UGrpID` FOREIGN KEY (`hyf_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Configure hyperlink import to ignore common link strings';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrRecTagLinks` (
  `rtl_ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary link table key, one tag linked to one record',
  `rtl_TagID` mediumint(8) unsigned NOT NULL COMMENT 'The tag being linked to the record/bookmark',
  `rtl_Order` tinyint(3) unsigned zerofill NOT NULL DEFAULT '255' COMMENT 'Ordering of tags within the current record/bookmark',
  `rtl_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 indicates added while editing, 1 indicates added during import',
  `rtl_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which the tag is linked, should always be set',
  PRIMARY KEY (`rtl_ID`),
  UNIQUE KEY `rtl_composite_key` (`rtl_RecID`,`rtl_TagID`),
  KEY `rtl_TagIDKey` (`rtl_TagID`),
  CONSTRAINT `fk_rtl_RecID` FOREIGN KEY (`rtl_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rtl_TagID` FOREIGN KEY (`rtl_TagID`) REFERENCES `usrTags` (`tag_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Link table connecting tags to records';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrRecentRecords` (
  `rre_UGrpID` smallint(5) unsigned DEFAULT NULL COMMENT 'ID of user who used the record',
  `rre_RecID` int(10) unsigned NOT NULL COMMENT 'ID of recently used record',
  `rre_Time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of use of records, notably those searched for with pointer field',
  UNIQUE KEY `rre_composite` (`rre_UGrpID`,`rre_RecID`),
  KEY `rre_RecID` (`rre_RecID`),
  CONSTRAINT `fk_rre_RecID` FOREIGN KEY (`rre_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rre_UGrpID` FOREIGN KEY (`rre_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrRecentRecords`
--

LOCK TABLES `usrRecentRecords` WRITE;
/*!40000 ALTER TABLE `usrRecentRecords` DISABLE KEYS */;
INSERT INTO `usrRecentRecords` VALUES (2,1,'2013-08-27 05:29:14'),(2,2,'2013-08-27 05:43:27'),(2,3,'2013-08-27 05:40:35');
/*!40000 ALTER TABLE `usrRecentRecords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrReminders`
--

DROP TABLE IF EXISTS `usrReminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrReminders` (
  `rem_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique reminder ID',
  `rem_RecID` int(10) unsigned NOT NULL COMMENT 'Record about which this reminder is sent, must refer to existing',
  `rem_OwnerUGrpID` smallint(5) unsigned NOT NULL COMMENT 'Owner of the reminder, the person who created it',
  `rem_ToWorkgroupID` smallint(5) unsigned DEFAULT NULL COMMENT 'The workgroup to which the reminder should be sent',
  `rem_ToUserID` smallint(5) unsigned DEFAULT NULL COMMENT 'The individual user to whom the reminder should be sent',
  `rem_ToEmail` varchar(255) DEFAULT NULL COMMENT 'The individual email address(es) to which the reminder should be sent',
  `rem_Message` varchar(1000) DEFAULT NULL COMMENT 'The message to be attached to the reminder, optional',
  `rem_StartDate` date NOT NULL DEFAULT '1970-01-01' COMMENT 'The first (or only) date for sending the reminder',
  `rem_Freq` enum('once','daily','weekly','monthly','annually') NOT NULL DEFAULT 'once' COMMENT 'The frequency of sending reminders',
  `rem_Nonce` varchar(31) DEFAULT NULL COMMENT 'Random number hash for reminders',
  `rem_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`rem_ID`),
  KEY `rem_RecID` (`rem_RecID`),
  KEY `rem_OwnerUGrpID` (`rem_OwnerUGrpID`),
  KEY `rem_ToWorkgroupID` (`rem_ToWorkgroupID`),
  KEY `rem_ToUserID` (`rem_ToUserID`),
  CONSTRAINT `fk_rem_OwnerUGrpID` FOREIGN KEY (`rem_OwnerUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_RecID` FOREIGN KEY (`rem_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_ToUserID` FOREIGN KEY (`rem_ToUserID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_ToWorkgroupID` FOREIGN KEY (`rem_ToWorkgroupID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Reminders attached to records and recipients, with start dat';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrRemindersBlockList` (
  `rbl_RemID` mediumint(8) unsigned NOT NULL COMMENT 'Reminder ID to be blocked',
  `rbl_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'User who does not wish to receive this reminder',
  UNIQUE KEY `rbl_composite_key` (`rbl_RemID`,`rbl_UGrpID`),
  KEY `rbl_UGrpID` (`rbl_UGrpID`),
  CONSTRAINT `fk_rbl_RemID` FOREIGN KEY (`rbl_RemID`) REFERENCES `usrReminders` (`rem_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rbl_UGrpID` FOREIGN KEY (`rbl_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Allows user to block resending of specific reminders to them';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrRemindersBlockList`
--

LOCK TABLES `usrRemindersBlockList` WRITE;
/*!40000 ALTER TABLE `usrRemindersBlockList` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrRemindersBlockList` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrReportSchedule`
--

DROP TABLE IF EXISTS `usrReportSchedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrReportSchedule` (
  `rps_ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary ID of a report output update specification',
  `rps_Type` enum('smarty') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'smarty' COMMENT 'The type of report being generated',
  `rps_Title` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'A title for this specification for use in selection menus',
  `rps_FilePath` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'The full file path to whic hthe report is to be generated, filestore/dbname/generated-reports if blank',
  `rps_URL` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'The corresponding URL for web access to the directory in which this report is published, to , filestore/dbname/generated-reports if blank',
  `rps_FileName` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'The base name of the report being published - wil lbe compelted with file types',
  `rps_HQuery` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'The Heurist query to be used in generating this report',
  `rps_Template` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'The name of the template file to be used in generating this report',
  `rps_IntervalMinutes` tinyint(4) DEFAULT NULL COMMENT 'The interval in minutes between regenerations of the report output, null = never',
  `rps_Added` timestamp NULL DEFAULT NULL COMMENT 'The date when this specification was added',
  `rps_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The date this specification was last updated',
  PRIMARY KEY (`rps_ID`),
  UNIQUE KEY `rps_path_filename` (`rps_FilePath`,`rps_FileName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Specs for writing out static files from a query plus templat';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrReportSchedule`
--

LOCK TABLES `usrReportSchedule` WRITE;
/*!40000 ALTER TABLE `usrReportSchedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrReportSchedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrSavedSearches`
--

DROP TABLE IF EXISTS `usrSavedSearches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrSavedSearches` (
  `svs_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Saved search ID, used in publishing, primary key',
  `svs_Name` varchar(30) NOT NULL COMMENT 'The display name for this saved search',
  `svs_Added` date NOT NULL DEFAULT '0000-00-00' COMMENT 'Date and time saves search added',
  `svs_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time saves search last modified',
  `svs_Query` text NOT NULL COMMENT 'The text of the saved search - added to search URL',
  `svs_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'The creator/owner or workgroup for the saved search',
  `svs_ExclusiveXSL` varchar(250) DEFAULT '' COMMENT 'Name of XSL to which to lock this publish format, blank = any XSL OK',
  PRIMARY KEY (`svs_ID`),
  KEY `svs_UGrpID` (`svs_UGrpID`),
  CONSTRAINT `fk_svs_UGrpID` FOREIGN KEY (`svs_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Saved searches for personal/usergroup use and for publishing';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrTags` (
  `tag_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `tag_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'User or workgroup id of tag owner',
  `tag_Text` varchar(63) NOT NULL COMMENT 'The value (text) of the tag provided by the user or workgroup administrator',
  `tag_Description` varchar(250) DEFAULT NULL COMMENT 'Description of the concept to which this tag is attached, optional',
  `tag_AddedByImport` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag as to whether this tag was added by an import (1) or by editing (0)',
  `tag_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT ' Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY (`tag_ID`),
  UNIQUE KEY `tag_composite_key` (`tag_Text`,`tag_UGrpID`),
  KEY `tag_UGrpID` (`tag_UGrpID`),
  KEY `tag_Text` (`tag_Text`),
  CONSTRAINT `fk_tag_UGrpID` FOREIGN KEY (`tag_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Personal and user group tags (formerly keywords)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrTags`
--

LOCK TABLES `usrTags` WRITE;
/*!40000 ALTER TABLE `usrTags` DISABLE KEYS */;
INSERT INTO `usrTags` VALUES (1,2,'johnson',NULL,0,'2014-04-13 23:20:01');
/*!40000 ALTER TABLE `usrTags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woot_ChunkPermissions`
--

DROP TABLE IF EXISTS `woot_ChunkPermissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `woot_ChunkPermissions` (
  `wprm_ChunkID` int(11) NOT NULL COMMENT 'ID of chunk for which permission is specified, may be repeated',
  `wprm_UGrpID` smallint(6) NOT NULL COMMENT 'User with specified right to this chunk',
  `wprm_GroupID` smallint(6) NOT NULL COMMENT 'User groups with specified right to this chunk',
  `wprm_Type` enum('RW','RO') NOT NULL COMMENT 'Read-write or read-only permission for this chunk/user/wg',
  `wprm_CreatorID` smallint(6) NOT NULL COMMENT 'Creator of the permission (= user ID ???? <check>)',
  `wprm_Created` datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY `wprm_chunk_composite_key` (`wprm_ChunkID`,`wprm_UGrpID`,`wprm_GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Permissions value for individual woot chunks';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `woot_Chunks` (
  `chunk_ID` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary ID for a version of the text chunks making up a woot entry (page)',
  `chunk_WootID` int(11) NOT NULL COMMENT 'The ID of the woot entry (page) to which this chunk belongs',
  `chunk_InsertOrder` int(11) NOT NULL COMMENT 'Order of chunk within woot.',
  `chunk_Version` int(11) NOT NULL COMMENT 'A version code for the chunk, incremented when edited',
  `chunk_IsLatest` tinyint(1) NOT NULL COMMENT 'Presumably flags whether this is the latest version of the chunk',
  `chunk_DisplayOrder` int(11) NOT NULL COMMENT 'The order number of the chunk within the woot entry (page)',
  `chunk_Text` text COMMENT 'The actual XHTML content of the chunk',
  `chunk_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the chunk',
  `chunk_OwnerID` int(11) DEFAULT NULL COMMENT 'Owner/creator (user ID) of the chunk',
  `chunk_Deleted` tinyint(1) NOT NULL COMMENT 'Deletion marker for this chunk',
  `chunk_EditorID` int(11) NOT NULL COMMENT 'Editor (user ID) of the chunk - presumably the last person to edit',
  PRIMARY KEY (`chunk_ID`),
  UNIQUE KEY `chunk_composite_key` (`chunk_WootID`,`chunk_InsertOrder`,`chunk_Version`),
  KEY `chunk_is_latest_key` (`chunk_IsLatest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `woot_RecPermissions` (
  `wrprm_WootID` int(11) NOT NULL COMMENT 'ID of the woot entry to which this permission applies, may be repeated',
  `wrprm_UGrpID` int(11) NOT NULL COMMENT 'User ID to which this permission is being granted',
  `wrprm_GroupID` int(11) NOT NULL COMMENT 'User group ID to which this permission is being granted',
  `wrprm_Type` enum('RW','RO') NOT NULL COMMENT 'Type of permission being granted - read only or read-write',
  `wrprm_CreatorID` int(11) NOT NULL COMMENT 'Creator of the permission',
  `wrprm_Created` datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY `wrprm_composite_key` (`wrprm_WootID`,`wrprm_UGrpID`,`wrprm_GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Overall permissions for the woot record (entry/page)';
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `woots` (
  `woot_ID` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary ID of a woot record/entry/page',
  `woot_Title` varchar(8191) DEFAULT NULL COMMENT 'Name of the woot page, unique identifier of the woot page',
  `woot_Created` datetime DEFAULT NULL COMMENT 'Date and time of creation of the woot record/entry/page',
  `woot_Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the woot record/entry/page',
  `woot_Version` int(11) NOT NULL COMMENT 'Version of the woot record/entry/page, presumably incremented on edit',
  `woot_CreatorID` int(11) DEFAULT NULL COMMENT 'Creator (user id) of the woot',
  PRIMARY KEY (`woot_ID`),
  UNIQUE KEY `woot_title_key` (`woot_Title`(200))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Woot records (entries, pages) are linked to a set of XHTML c';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `woots`
--

LOCK TABLES `woots` WRITE;
/*!40000 ALTER TABLE `woots` DISABLE KEYS */;
/*!40000 ALTER TABLE `woots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'hdb_H3CoreDefinitions'
--
/*!50003 DROP FUNCTION IF EXISTS `getTemporalDateString` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `hhash` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `hhash`(recID int) RETURNS varchar(4095) CHARSET utf8
    READS SQL DATA
    DETERMINISTIC
begin
        declare rectype int;
        declare non_resource_fields varchar(4095);
        declare resource_fields varchar(4095);

        select rec_RecTypeID into rectype from Records where rec_ID = recID;

        select group_concat(NEW_LIPOSUCTION(upper(dtl_Value)) order by dty_ID, upper(dtl_Value) separator ';')
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `NEW_LEVENSHTEIN` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `NEW_LIPOSUCTION` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `simple_hash` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 FUNCTION `simple_hash`(recID int) RETURNS varchar(4095) CHARSET utf8
    READS SQL DATA
    DETERMINISTIC
begin
        declare rectype int;
        declare non_resource_fields varchar(4095);
        declare author_fields varchar(4095);
        select rec_RecTypeID into rectype from Records where rec_ID = recID;
        select group_concat(NEW_LIPOSUCTION(upper(dtl_Value)) order by dty_ID, upper(dtl_Value) separator ';')
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `set_all_hhash` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-07-23 23:38:57
