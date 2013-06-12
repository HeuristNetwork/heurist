CREATE DATABASE  IF NOT EXISTS `hdb_dos_0` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;
USE `hdb_dos_0`;
-- MySQL dump 10.13  Distrib 5.1.40, for Win32 (ia32)
--
-- Host: localhost    Database: hdb_dos_0
-- ------------------------------------------------------
-- Server version	5.0.51-log

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
-- Not dumping tablespaces as no INFORMATION_SCHEMA.FILES table on this server
--

--
-- Table structure for table `defTranslations`
--

DROP TABLE IF EXISTS `defTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defTranslations`
--

LOCK TABLES `defTranslations` WRITE;
/*!40000 ALTER TABLE `defTranslations` DISABLE KEYS */;
/*!40000 ALTER TABLE `defTranslations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defOntologies`
--

DROP TABLE IF EXISTS `defOntologies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defOntologies`
--

LOCK TABLES `defOntologies` WRITE;
/*!40000 ALTER TABLE `defOntologies` DISABLE KEYS */;
INSERT INTO `defOntologies` VALUES (1,'local','Null ontology','An empty ontology which can be complemented','','open',2,'Null ontology',1,255,'2013-02-19 22:01:07',0),(2,'DC','Dublin Core','','http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=52142','open',2,'Dublin Core',2,255,'2013-02-19 22:01:07',0),(3,'CIDOC-CRM','CIDOC-CRM','','http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=34424','open',2,'CIDOC-CRM',3,255,'2013-02-19 22:01:07',0);
/*!40000 ALTER TABLE `defOntologies` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Table structure for table `recThreadedComments`
--

DROP TABLE IF EXISTS `recThreadedComments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recThreadedComments`
--

LOCK TABLES `recThreadedComments` WRITE;
/*!40000 ALTER TABLE `recThreadedComments` DISABLE KEYS */;
/*!40000 ALTER TABLE `recThreadedComments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defRecStructure`
--

DROP TABLE IF EXISTS `defRecStructure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1548 DEFAULT CHARSET=utf8 COMMENT='The record details (fields) required for each record type';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRecStructure`
--

LOCK TABLES `defRecStructure` WRITE;
/*!40000 ALTER TABLE `defRecStructure` DISABLE KEYS */;
INSERT INTO `defRecStructure` VALUES (1,1,7,'Source record','Primary resource','',000,60,'',0,0,'required','viewable','approved','open',2,1,1,1,1,'','',0,'','2013-01-09 00:26:57',1),(2,1,6,'Relationship type','RelationType','',001,60,'',0,0,'required','viewable','approved','open',2,2,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(3,1,5,'Target record','The target record in Heurist to which the relationship points','',002,60,'',1,0,'required','viewable','approved','open',2,3,1,1,0,'','',0,'','2013-01-09 00:26:57',0),(4,1,1,'Title for relationship','A succinct descriptive title for the relationship','',005,60,'',0,0,'optional','viewable','approved','open',2,4,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(5,1,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',006,60,'',0,0,'optional','viewable','approved','open',2,5,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(6,1,10,'Start date/time','Dates only required if relationship is to be timestamped ','',003,10,'',0,0,'recommended','viewable','approved','open',2,6,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(7,1,11,'End date/time','Enter end date only if relationship expires','',004,10,'',0,0,'recommended','viewable','approved','open',2,7,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(8,1,8,'Interpretation/commentary','A pointer to a commentary/interpreation record (for spatial, temporal or general information)','',008,60,'',0,0,'optional','viewable','approved','open',2,8,1,0,1,'','',0,'','2013-01-11 21:34:05',1),(9,1,28,'Location (mappable)','A geospatial object providing a mappable location for the record','',010,60,'',0,0,'optional','viewable','approved','open',2,9,1,0,1,'','',0,'','2013-01-11 21:34:02',1),(10,1,38,'File resource','An uploaded file or a reference to a file through a URI ','',011,60,'',0,0,'optional','viewable','approved','open',2,10,0,0,1,'','',0,'','2013-01-11 21:34:02',1),(11,1,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',009,60,'',0,0,'optional','viewable','approved','open',2,11,1,0,1,'','',0,'','2013-01-11 21:34:01',1),(12,2,1,'Page Title','A succinct descriptive title for the page, even if the page itself does not have one','',000,60,'',1,0,'required','viewable','reserved','open',2,12,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(13,2,3,'Short Summary','Short summary eg. 100 words, for use in annotated lists','',001,60,'',0,0,'recommended','viewable','approved','open',2,13,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(14,2,39,'Thumbnail Image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages. Generated automatically on creation using bookmarklet','',003,40,'',0,0,'recommended','viewable','approved','open',2,14,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(15,2,9,'Date accessed','The date the web resource was accessed','',002,10,'today',0,0,'recommended','viewable','approved','open',2,15,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(16,3,1,'Title','A succinct descriptive title for the note','',000,60,'',1,0,'required','viewable','reserved','open',2,16,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(17,3,3,'Short note / summary','A short note or a summary of a longer note eg. 100 words, for use in annotated lists','',002,60,'',0,0,'required','viewable','approved','open',2,17,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(18,3,9,'Date','Specific date to which the note applies (if applicable)','',001,10,'today',0,0,'recommended','viewable','approved','open',2,18,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(19,3,38,'Related Files','Upload any associated files here. Consider using a separate multimedia records (next field) to allow more detailed documentation','',008,40,'',0,0,'optional','viewable','approved','open',2,19,0,0,0,'','',0,'','2013-01-09 00:26:57',1),(20,3,39,'Thumbnail image','A thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',006,40,'',0,0,'optional','viewable','approved','open',2,20,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(21,4,1,'Name of organisation','Full title of the organisation - do not abbreviate','',000,40,'',1,0,'required','viewable','reserved','open',2,21,1,1,1,'','',0,'','2013-01-09 00:26:57',1),(22,4,2,'Short name / acronym','Name','Please provide an extended description for display on rollover ...',001,20,'',2,0,'optional','viewable','open','open',2,22,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(23,4,22,'Organisation type','Organisation type','Please provide an extended description for display on rollover ...',002,60,'',0,0,'recommended','viewable','open','open',2,23,0,0,1,'','',0,'','2013-01-09 00:26:57',1),(24,4,3,'Short description','Short description of organisation, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',003,40,'',0,0,'recommended','viewable','open','open',2,24,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(25,4,10,'Start date','Date organisation was founded','',008,10,'',0,0,'optional','viewable','open','open',2,25,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(26,4,11,'End date','Date the organisation closed','',009,10,'',0,0,'optional','viewable','open','open',2,26,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(27,4,27,'Location','Name of place - city, town, locality - where organisation is located','',011,30,'',0,0,'optional','viewable','open','open',2,27,0,0,0,'','',0,'','2013-01-09 00:26:57',1),(28,4,26,'Country','Country','',010,60,'',0,0,'optional','viewable','open','open',2,28,0,0,1,'','',0,'','2013-01-09 00:26:57',1),(29,4,28,'Location (mappable)','A geospatial object providing a mappable location for the record','',012,60,'',0,0,'optional','viewable','open','open',2,29,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(30,4,39,'Thumbnail or Logo','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',004,60,'',0,0,'recommended','viewable','open','open',2,30,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(31,4,38,'Related files','An uploaded file or a reference to a file through a URI ','',014,60,'',0,0,'optional','viewable','open','open',2,31,0,0,1,'','',0,'','2013-01-09 00:26:57',1),(32,5,1,'Title','A succinct descriptive title of the media item','',000,40,'',1,0,'required','viewable','reserved','open',2,32,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(33,5,38,'Media file','Browse to media file and upload - recommended max size 5 MBytes','',005,60,'',2,0,'required','viewable','reserved','open',2,33,1,1,0,'','',0,'','2013-01-11 20:56:58',1),(34,5,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',008,60,'',0,0,'recommended','viewable','approved','open',2,34,1,0,1,'','',0,'','2013-01-11 21:00:45',1),(35,5,15,'Creator(s)','The creator(s) - photographer, recordist etc. - of the media item','',001,30,'',0,0,'recommended','viewable','approved','open',2,35,0,0,0,'','',0,'','2013-01-09 00:26:57',1),(36,5,10,'Start Date','Date of creation or publication','',003,10,'today',0,0,'recommended','viewable','approved','open',2,36,1,0,0,'','',0,'','2013-01-11 20:57:04',1),(37,5,39,'Thumbnail image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',006,40,'',0,0,'recommended','viewable','approved','open',2,37,1,0,0,'','',0,'','2013-01-11 20:56:58',1),(38,5,28,'Location (mappable)','Geographic object(s) describing the location as lines, polygons or other shapes','',007,40,'',0,0,'optional','viewable','approved','open',2,38,1,0,0,'','',0,'','2013-01-11 21:00:45',1),(39,5,35,'Copyright information','Copyright information (copyright statement or URL)','',011,60,'',0,0,'recommended','viewable','approved','open',2,39,1,0,1,'','',0,'','2013-01-11 21:00:45',1),(40,21,1,'Name','The collection name.','',000,40,'Enter collection name',1,0,'required','viewable','open','open',2,40,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(41,21,13,'Record Pointer','Resource Identifier (unconstrained record pointer)','',001,60,'',0,0,'optional','viewable','approved','open',2,41,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(42,21,12,'Query String','A string representing a valid Heurist query.','Please provide an extended description for display on rollover ...',002,40,'',0,0,'optional','viewable','open','open',2,42,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(43,21,40,'Graph filter string','A string representing a valid Heurist graph filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]} relfilter={\"level\":[\"relTermID1\",\"relTermID2\"]} ptrfilter={\"level\":[\"ptrDtyID1\",\"ptrDtyID2\"]} )','Please provide an extended description for display on rollover ...',003,40,'',0,0,'optional','viewable','open','open',2,43,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(44,21,48,'Default View Layout','A formatted string that can be interpretted by the Heurist Interface as specific layout. See Help for specifics.','',004,40,'',0,0,'optional','viewable','open','open',2,44,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(45,7,1,'Title of post','A title for the blog entry, date will be added automatically','',000,80,'',1,0,'required','viewable','reserved','open',2,45,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(46,7,39,'Thumbnail image','Thumbnail image, recommended 200 pixels maximum dimension','',001,30,'',0,0,'recommended','viewable','reserved','open',2,46,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(47,7,28,'Location (mappable)','Location or extent of this blog entry, can be rendered on the map','',002,30,'',0,0,'recommended','viewable','approved','open',2,47,0,0,0,'','',0,'','2013-01-09 00:26:57',1),(48,8,1,'Summary title','A title for this interpretation, excluding author, date','',000,50,'',1,0,'required','viewable','approved','open',2,48,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(49,8,10,'Validity date','Date of validity of this interpretation','',003,10,'today',0,0,'required','viewable','approved','open',2,49,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(50,8,3,'Text of annotation','Short sumamry of the interpretation, use the WYSIWYG text in the TEXT or PERSONAL tabs for longer description','',004,50,'',0,0,'required','viewable','approved','open',2,50,1,1,0,'','',0,'','2013-01-09 00:26:57',1),(51,8,15,'Author(s)','Person(s) making this interpretation (original author, use transcriber if further interpreted)','',001,30,'',1,0,'recommended','viewable','approved','open',2,51,0,0,0,'','',0,'','2013-01-09 00:26:57',0),(52,8,14,'Transcriber(s)','Person(s) transcribing/further interpreting the original interpretation','',002,30,'',0,0,'optional','viewable','approved','open',2,52,0,0,0,'','',0,'','2013-01-09 00:26:57',0),(53,8,13,'References','Bibliographic or other records in Heurist which support or relate to this interpretation','',005,80,'',0,0,'recommended','viewable','approved','open',2,53,0,0,0,'','',0,'','2013-01-09 00:26:57',0),(54,9,23,'Email of sender','Email address from which this email forwarded or ccd','Address of owner (forwarder or sender)',000,50,'',2,0,'required','viewable','approved','open',2,54,1,0,0,'','',0,'','2013-01-09 00:26:57',0),(55,9,1,'Subject','Subject/title of the email','Subject line of email',001,30,'No subject line provided',1,0,'required','viewable','approved','open',2,55,1,0,0,'','',0,'','2013-01-09 00:26:57',0),(56,9,9,'Date originally sent','Date of the original email (date of sending of a ccd email or original date of a forwarded email)','Date of original sending',002,30,'',0,0,'required','viewable','approved','open',2,56,1,0,0,'','',0,'','2013-01-09 00:26:57',0),(57,9,24,'Email of originator','Address of original sender (if applicable, that is for forwarded emails)','Address of original sender (where different)',003,50,'',0,0,'optional','viewable','approved','open',2,57,1,0,0,'','',0,'','2013-01-09 00:26:57',0),(58,9,25,'Email of recipients','Email addresses of all recipients of the email','Emails of all recipients of the email ',004,30,'',0,0,'optional','viewable','approved','open',2,58,0,0,0,'','',0,'','2013-01-09 00:26:57',0),(59,9,3,'Email Body','Content of the body/text of the email','',005,60,'',0,0,'optional','viewable','approved','open',2,59,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(60,9,38,'Attachments','Files attached to the email','Attached files',006,50,'',0,0,'optional','viewable','approved','open',2,60,0,0,0,'','',0,'','2013-01-09 00:26:57',0),(61,10,1,'Family name','Family name of person','',001,40,'',1,0,'required','viewable','reserved','open',2,61,1,1,1,'','',0,'','2013-01-09 00:26:57',1),(62,10,18,'Given name(s)','The given name or names of the person','',002,40,'',1,0,'recommended','viewable','approved','open',2,62,0,0,0,'','',0,'','2013-01-09 00:26:57',1),(63,10,19,'Honorific','Title or grade - Prof, Dr, Sir etc., recommend omitting Mr, Mrs or Ms','',000,60,'',1,0,'optional','viewable','approved','open',2,63,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(64,10,10,'Birth date','Date of Birth (year or year-month or year-month-day)','',011,10,'',2,0,'optional','viewable','approved','open',2,64,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(65,10,27,'Birth place','Name of location where person was born, use town or city and country eg. Penzance, UK','',012,30,'',2,0,'optional','viewable','approved','open',2,65,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(66,10,20,'Gender','Gender of the person','',003,30,'',0,0,'optional','viewable','approved','open',2,66,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(67,10,3,'Short description','Short description of the person eg. 100 words, for use in annotated lists/web pages','',004,60,'',0,0,'recommended','viewable','approved','open',2,67,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(68,10,23,'Email address','Email address(es) of the person, enter one address only on each line','',007,60,'',0,0,'optional','viewable','approved','open',2,68,0,0,1,'','',0,'','2013-01-09 00:26:57',1),(69,10,17,'Contact info or URL','Contact information - it is best to use the URL of a staff directory page to avoid the need to update contact details','',008,60,'',0,0,'optional','viewable','approved','open',2,69,0,0,0,'','',0,'','2013-01-09 00:26:57',1),(70,10,37,'Unique public identifier','eg. NLA party identifier (Australia) or other national person identifier code','',009,30,'',0,0,'optional','viewable','approved','open',2,70,1,0,0,'','',0,'','2013-01-09 00:26:57',1),(71,10,11,'Date of death','Date of death (year, or year-month or year-month-day)','Please provide an extended description for display on rollover ...',013,10,'',0,0,'optional','viewable','approved','open',2,71,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(72,11,1,'Name of tiled image','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,30,'',1,0,'required','viewable','open','open',2,72,1,1,0,'','',0,'','2013-01-09 00:26:57',0),(73,11,34,'Service URL','Base Service URL','',008,30,'',1,0,'required','viewable','open','open',2,73,1,1,0,'','',0,'','2013-01-11 20:53:45',1),(74,11,31,'Tiling schema','Image tiling schema','',010,30,'548',2,0,'required','viewable','open','open',2,74,1,1,0,'','',0,'','2013-01-11 20:53:45',1),(75,11,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',005,30,'',0,0,'optional','viewable','open','open',2,75,1,0,1,'','',0,'','2013-01-11 20:48:29',1),(76,11,29,'Mime Type','Type of images used for tiles','',011,30,'537',0,0,'optional','viewable','open','open',2,76,1,0,0,'','',0,'','2013-01-11 20:53:45',1),(77,11,32,'Minimum zoom level','Minimum zoom level','',012,30,'1',0,0,'optional','viewable','open','open',2,77,1,0,0,'','',0,'','2013-01-11 20:53:45',1),(78,11,33,'Maximum zoom level','Maximum zoom level','',013,30,'19',0,0,'optional','viewable','open','open',2,78,1,0,0,'','',0,'','2013-01-11 20:53:45',1),(79,11,30,'Tiled image type','The type (map or image) of the tiled image.','Please provide an extended description for display on rollover ...',009,60,'545',0,0,'required','viewable','approved','open',2,79,1,1,1,'','',0,'','2013-01-11 20:53:45',1),(80,12,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,'',1,0,'required','viewable','open','open',2,80,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(81,12,40,'Filter String','A string representing a valid Heurist filter string','Please provide an extended description for display on rollover ...',001,40,'',2,0,'optional','viewable','open','open',2,81,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(82,12,49,'Filter Format Version','Numeric string representing a version, typically a squence of numbers separated by full stop.','Please provide an extended description for display on rollover ...',002,40,'',0,0,'optional','viewable','open','open',2,82,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(83,13,1,'Title of document','Full title of the document - do not abbreviate','Please provide an extended description for display on rollover ...',000,40,'',1,0,'recommended','viewable','open','open',2,83,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(84,13,38,'File resource','An uploaded file or a reference to a file through a URI','Please provide an extended description for display on rollover ...',001,60,'',1,0,'required','viewable','open','open',2,84,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(85,13,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','Please provide an extended description for display on rollover ...',003,40,'',0,0,'recommended','viewable','open','open',2,85,1,0,1,'','',0,'','2013-01-11 21:38:56',1),(86,13,15,'Author(s)','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',005,60,'',0,0,'optional','viewable','open','open',2,86,0,0,1,'','',0,'','2013-01-11 21:38:56',1),(87,13,35,'Copyright information','Copyright information','Please provide an extended description for display on rollover ...',006,40,'',0,0,'optional','viewable','open','open',2,87,1,0,1,'','',0,'','2013-01-11 21:38:56',1),(88,13,41,'File Type','Term identifying the file format','',002,60,'3923',0,0,'optional','viewable','open','open',2,88,1,0,1,'{\"524\":{\"526\":{},\"525\":{\"704\":{},\"705\":{}},\"700\":{\"701\":{},\"702\":{},\"703\":{}}}}','',0,'','2013-01-11 21:38:56',1),(89,14,38,'Template File','An uploaded file or a reference to a file through a URI. Multiple entries applied sequentially','Please provide an extended description for display on rollover ...',001,60,'',0,0,'required','viewable','approved','open',2,89,0,1,1,'','',0,'','2013-01-09 00:26:57',0),(90,14,41,'Transformation Type','Term identifying the transformation file format','',002,60,'3918',2,0,'required','viewable','open','open',2,90,1,1,1,'{\"699\":{},\"700\":{\"701\":{},\"702\":{},\"703\":{}}}','',0,'','2013-01-09 00:26:57',0),(91,14,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,'',1,0,'required','viewable','open','open',2,91,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(92,14,3,'Description','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','Please provide an extended description for display on rollover ...',004,40,'Enter a brief description of the transform.',0,0,'recommended','viewable','approved','open',2,92,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(93,14,15,'Author','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',005,60,'',0,0,'optional','viewable','open','open',2,93,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(94,14,35,'Copyright information','Copyright information','Please provide an extended description for display on rollover ...',006,40,'',0,0,'optional','viewable','open','open',2,94,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(95,14,4,'Transform','Transformation text','Please provide an extended description for display on rollover ...',003,60,'',0,0,'optional','viewable','approved','open',2,95,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(96,15,1,'Name of annotation','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,60,'',1,0,'required','viewable','approved','open',2,96,1,1,1,'','',0,'','2013-01-11 21:29:13',1),(97,15,42,'Annotated Resource','Pointer to the resource being annotated','Please provide an extended description for display on rollover ...',004,60,'',0,0,'optional','viewable','open','open',2,97,1,0,1,'','',0,'','2013-01-11 21:25:47',1),(98,15,43,'Annotation Range','An encoded string defining the area of the annotated document being annotated','Please provide an extended description for display on rollover ...',005,40,'',2,0,'optional','viewable','open','open',2,98,1,0,1,'','',0,'','2013-01-11 21:25:47',1),(101,15,44,'Start Word','Start Word','',008,60,'',0,0,'optional','viewable','approved','open',2,101,1,0,1,'','',0,'','2013-01-11 21:27:42',1),(102,15,45,'End Word','End Word','',010,60,'',0,0,'optional','viewable','approved','open',2,102,1,0,1,'','',0,'','2013-01-11 21:27:42',1),(103,15,46,'Start Element','Start Element','',009,60,'',0,0,'optional','viewable','approved','open',2,103,1,0,1,'','',0,'','2013-01-11 21:27:42',1),(104,15,47,'End Element','End Element','',011,60,'',0,0,'optional','viewable','approved','open',2,104,1,0,1,'','',0,'','2013-01-11 21:27:42',1),(105,16,1,'Layout Name','The main name or title for the layout.','Please provide an extended description for display on rollover ...',000,40,'',1,0,'required','viewable','approved','open',2,105,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(106,16,48,'Heurist Layout String','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Please provide an extended description for display on rollover ...',001,40,'',2,0,'required','viewable','approved','open',2,106,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(107,16,49,'Version Number','Numeric string representing a version, typically a squence of numbers separated by full stop.','Please provide an extended description for display on rollover ...',002,40,'1.0',0,0,'required','viewable','approved','open',2,107,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(108,17,1,'Name of Pipeline','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,'',1,0,'required','viewable','approved','open',2,108,1,1,1,'','',0,'','2013-01-09 00:26:57',0),(109,17,50,'Transformation Pointer','Pointer to a transformation or pipeline record','',001,60,'',0,0,'required','viewable','approved','open',2,109,0,1,1,'','',0,'','2013-01-09 00:26:57',0),(110,18,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,'',0,0,'recommended','viewable','open','open',2,110,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(111,18,51,'Property (N:V)','Attribute expressed as a name:value pair','',001,40,'',0,0,'recommended','viewable','open','open',2,111,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1375,6,1,'Aggregation Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','Please provide an extended description for display on rollover ...',000,40,'',1,0,'required','viewable','open','open',2,1375,1,1,1,'','',0,'','2013-01-09 00:26:57',1),(1376,6,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','Please provide an extended description for display on rollover ...',011,60,'',0,0,'optional','viewable','open','open',2,1376,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(1377,6,3,'Short summary','Notes about the aggregation, normally 100 - 200 words max. so usable in lists','Please provide an extended description for display on rollover ...',007,80,'',0,0,'optional','viewable','open','open',2,1377,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(1379,6,15,'Creator - author, artist etc.','The person(s) or organisation(s) who created this record','May include authors, artists, organisations that sponsored a resource etc.',008,60,'',0,0,'optional','viewable','open','open',2,1379,0,0,1,'','',0,'','2013-01-09 00:26:57',1),(1380,6,35,'Copyright information','Copyright information, either a URL to a copyrigth statement or text describing the copyright','Please provide an extended description for display on rollover ...',009,40,'',0,0,'optional','viewable','open','open',2,1380,1,0,1,'','',0,'','2013-01-09 00:26:57',1),(1381,15,52,'Annotation Type','Type of annotation - purpose of annotation - can be associated with tool','Please provide an extended description for display on rollover ...',001,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1382,15,3,'Text','Text of the annotation - comment or text to use in tool','',002,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1383,19,1,'Name','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1384,19,39,'Icon','An image of approx. 200 pixels wide used on the button for the command','',001,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1385,19,50,'Transform Pointer','Pointer to a transform or pipeline transform record','',003,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1386,19,56,'Colour','Colour','',002,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1387,19,53,'Record Type','Record Type Concept-ID','Enter the origin dbID-recTypeID hyphenated id pair.',004,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1388,19,54,'Detail Type','Detail Type Concept-ID','Enter the origin dbID-detailTypeID hyphenated id pair.',005,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1389,19,55,'Application Command','Application Command','Enter the text for the command to be executed when the tool is invoked in the application',007,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1390,19,52,'Detail Value','Value to map to the detail type','Please provide an extended description for display on rollover ...',006,0,'3247',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1391,3,4,'Extended note','An extended version of the note and/or further detail','',004,60,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1392,10,57,'Life','Another separator field','',010,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1393,10,58,'Contact info','Another separator field','',006,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1394,2,4,'Extended description','A more extensive description, often derived from text on the web page itself','',007,60,'',0,0,'optional','viewable','approved','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1395,7,3,'Short summary','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',004,60,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1397,7,15,'Creator','The person or organisation who created the record/resource','May include authors, artists, organisations that sponsored a resource etc.',005,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1398,7,27,'Place name(s0','The name of a place/location(s) associated with the blog post','',006,40,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1399,7,57,'Optional information','Another separator field','',003,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1400,6,13,'Record pointer','One or more record pointers to records which are to be included in this aggregation','A highly generic detail utility',002,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1401,6,12,'Heurist Query String','A string representing a valid Heurist query (a query that you might enter in the search box on the home page)','Please provide an extended description for display on rollover ...',003,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1402,6,40,'Heurist Filter String','A string representing a valid Heurist filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]}','Allows the saving of rules that work on a result set. Rather than just saying \"give me all the related records of record x\" one could say \"give me all the related people of record x\" and save this in a detail for reuse.',005,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1403,6,48,'Heurist Layout String','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Used to pre-configure the Heurist environment. Usually accompanying a query and/or a filter. The combination of these details enables the full current Heurist environment to be stored and/or sent to other users',004,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1404,6,57,'Aggregation fields','A heading for contact information','',001,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1405,6,58,'Descriptive fields','A heading for the life history section of a person record','',006,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1406,10,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',005,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1407,4,57,'Other info','A heading for contact information','',013,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1408,4,58,'Description','A heading for the life history section of a person record','',005,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1409,4,4,'Extended description','Extended description','',007,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1410,4,17,'Contact details or URL','Contact details, either as text or a URL to eg. a phonebook record or search','',006,40,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1411,2,21,'Organisation(s) concerned','The organisation which owns the web site or which the website is about','',008,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1412,2,26,'Country(ies)','The country where the web site\'s owners/subjects is/are based','',005,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1413,2,28,'Mappable Location','A mappable location for the owners or subjects of the web site, where this can be identified - can be a point or an area of coverage','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage',006,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1414,2,57,'Optional description','A heading for contact information','',004,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1415,2,16,'Person(s) concerned','The person who owns the web site or whom the website is about','',009,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1416,2,38,'Related files','An uploaded file or a reference to a file through a URI, with further information eg. saved copy of the page or downloaded content','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible',010,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1417,3,61,'Multimedia >','Points to a multimedia record - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','',007,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1418,3,15,'Third party author >','The person or organisation who created the note (where different from the person who created the record)','May include authors, artists, organisations that sponsored a resource etc.',005,0,'',0,0,'optional','viewable','open','open',0,0,0,0,1,'','',0,'','2013-01-09 00:26:57',0),(1419,3,57,'Additional info','A heading for contact information','',003,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1420,4,61,'Multimedia >','Points to a multimedia record - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','',015,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',0),(1421,5,41,'File Type','Term identifying the file format','',024,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1422,5,62,'File name','The name of the file, excluding path, including extension','',022,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1423,5,63,'File path','The full path to the file','',021,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1424,5,64,'File extension','The (normally three letter) file extension','',023,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1425,5,65,'File recording device','The device used to collect files, may be generic \'image\' or more specific eg. camera make and model','',020,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1426,5,66,'File duration secs','The duration of a media object, such as sound or video, in seconds','',026,15,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1427,5,67,'File size bytes','The size of the file in bytes','',025,15,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1428,5,68,'MD5 checksum','The calculated MD5 checksum for the file','',027,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:14',0),(1429,5,57,'Technical information','A heading for contact information','',019,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-11 21:02:18',0),(1437,22,38,'Related file','An uploaded file or a reference to a file through a URI','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible',007,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1438,22,41,'File Type','Term identifying the file format','',006,0,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1439,22,62,'File name','The name of the file, excluding path, including extension','',005,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1440,22,63,'File path','The full path to the file','',004,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1441,22,64,'File extension','The (normally three letter) file extension','',003,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1442,22,65,'File recording device','The device used to collect files, may be generic \'image\' or more specific eg. camera make and model','',002,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1443,22,66,'File duration secs','The duration of a media object, such as sound or video, in seconds','',001,15,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1444,22,67,'File size bytes','The size of the file in bytes','',000,15,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1445,22,68,'MD5 checksum','The calculated MD5 checksum for the file','',008,40,'',0,0,'optional','viewable','open','open',0,0,1,0,1,'','',0,'','2013-01-09 00:26:57',9),(1446,23,1,'Title of KML','A succinct descriptive name for the KML record','',000,30,'',1,0,'required','viewable','open','open',3,2159,1,1,0,'','',0,'','2013-01-11 20:31:37',1),(1447,23,3,'Description','Short description of file','',001,30,'',0,0,'optional','viewable','open','open',3,2161,1,0,0,'','',0,'','2013-01-09 04:52:41',1),(1448,23,15,'Creator(s)','Who created the KML file or snippet','',002,30,'',0,0,'optional','viewable','open','open',3,2162,0,0,0,'','',0,'','2013-01-09 04:52:41',1),(1449,23,38,'File resource','Associated KML file','',004,30,'',0,0,'optional','viewable','open','open',3,2160,1,0,0,'','',0,'','2013-01-09 04:52:41',1),(1450,23,72,'KML','KML snippet - (select object in Google Earth - copy and paste here)','',003,30,'',0,0,'optional','viewable','open','open',3,2163,1,0,0,'','',0,'','2013-01-09 04:52:41',1),(1451,23,73,'default symbology colour','Default colour to display items','',005,30,'red',0,0,'optional','viewable','open','open',3,2164,1,0,0,'','',0,'','2013-01-09 04:52:41',1),(1452,24,1,'Name / Title','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'required','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-10 02:31:41',9),(1454,24,3,'Short summary','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',001,60,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 20:17:09',1),(1455,24,39,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',003,60,'',0,NULL,'optional','viewable','open','open',1,NULL,1,0,NULL,NULL,NULL,NULL,'','2013-01-11 20:16:44',1),(1457,24,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',004,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-01-11 20:16:44',0),(1459,24,17,'Contact details or URL','Contact details, either as text or a URL to eg. a phonebook record or search','',005,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:16:44',0),(1460,24,74,'Contributor type','Contributor type','',002,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:17:09',0),(1461,25,1,'Name / Title','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'required','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-10 02:43:20',9),(1463,25,3,'Short summary','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',002,60,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-10 02:53:18',1),(1466,25,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',003,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-10 02:53:18',0),(1467,25,20,'Sex','Sex of a person (or other living creature)','',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:18:56',0),(1468,25,61,'Multimedia >','Points to a multimedia record - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','',005,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:18:56',0),(1469,25,75,'Entity type','Entity type','',001,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-10 02:53:18',0),(1470,26,1,'Name / Title','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'required','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-10 03:05:25',9),(1472,26,3,'Short summary','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',005,60,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 20:28:01',1),(1474,26,28,'Geospatial object','A geospatial object providing a mappable location for the record - can be used for any type of record with spatial location','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage',007,40,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 20:28:36',1),(1475,26,9,'Date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','',001,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-10 03:10:42',9),(1476,27,1,'Name / Title','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'required','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 21:06:06',1),(1478,27,3,'Short summary','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',002,60,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 21:05:51',1),(1481,24,92,'Attribution','Attribution','',006,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:16:44',0),(1482,25,89,'Status','Internal status','',006,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:18:45',9),(1483,26,10,'Start date','Start Date (may also include time and/or have fuzzy limits)','Note that this detail describes a temporal object and can be used to describe dates that have a level of uncertainty',002,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:23:43',0),(1484,26,11,'End date','End Date (may also include time and/or have fuzzy limits)','Please provide an extended description for display on rollover ...',003,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:23:46',0),(1485,26,97,'Factoid target','Factoid target','',004,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:28:01',0),(1486,26,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',006,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:28:29',0),(1487,26,88,'Factoid Role','Factoid Role','',011,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:29:57',0),(1488,26,85,'Factoid type','Factoid type','',008,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:29:55',0),(1489,26,86,'Entity target','Entity target','',009,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:29:57',0),(1490,26,87,'Entity source','Entity source','',010,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:29:57',0),(1491,28,1,'Name / Title','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'required','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 20:32:41',9),(1493,28,3,'Short summary','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',001,60,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 20:34:40',1),(1495,28,28,'Geospatial object','A geospatial object providing a mappable location for the record - can be used for any type of record with spatial location','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage',003,40,'',0,NULL,'recommended','viewable','open','open',1,NULL,1,1,NULL,NULL,NULL,NULL,NULL,'2013-01-11 20:35:36',1),(1496,28,90,'Contributor pointer','Contributor pointer','',004,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:34:40',0),(1497,28,91,'KML pointer','KML pointer','',005,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:34:40',0),(1498,28,92,'Attribution','Attribution','',006,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:34:40',0),(1499,28,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',002,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:34:40',0),(1500,28,93,'Map Image pointer','Map Image pointer','',007,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:35:59',9),(1501,11,9,'Date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','',002,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:45:20',0),(1502,11,11,'End date','End Date (may also include time and/or have fuzzy limits)','Please provide an extended description for display on rollover ...',004,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:45:20',0),(1503,11,10,'Start date','Start Date (may also include time and/or have fuzzy limits)','Note that this detail describes a temporal object and can be used to describe dates that have a level of uncertainty',003,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:45:20',0),(1504,11,2,'Short name','Short name or acronym, use this for common shortened names or acronyms, eg. ARC for Australian Research Council','',001,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:45:20',0),(1505,11,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',006,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:48:29',0),(1506,11,78,'Language','Language','',015,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:53:45',0),(1507,11,38,'Related file','An uploaded file or a reference to a file through a URI','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible',007,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:53:44',0),(1508,11,82,'Source URL','Source URL. More info URL','',014,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-01-11 20:53:45',0),(1509,11,83,'Creator','Creator','',016,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-01-11 20:53:35',0),(1510,11,84,'Contributor Item ID','Contributor own id for media item','',017,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:53:35',0),(1511,11,90,'Contributor pointer','Contributor pointer','',018,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:54:04',9),(1512,5,11,'End date','End Date (may also include time and/or have fuzzy limits)','Please provide an extended description for display on rollover ...',004,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:56:58',0),(1513,5,9,'Date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','',002,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 20:56:58',0),(1514,5,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',009,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:00:45',0),(1515,5,78,'Language','Language','',010,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:00:45',0),(1516,5,29,'Mime Type','Mime Type','',012,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:00:45',0),(1517,5,84,'Contributor Item ID','Contributor own id for media item','',013,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:00:45',0),(1518,5,89,'Status','Internal status','',016,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:01:19',0),(1519,5,90,'Contributor pointer','Contributor pointer','',015,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:01:19',0),(1520,5,82,'Item Source URL','Source URL','',014,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:01:19',0),(1521,5,83,'Creator','Creator','',017,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:02:38',0),(1522,5,94,'Rights','Rights','',018,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:02:38',0),(1523,27,95,'Role type','Role type','',003,0,'',0,NULL,'required','viewable','open','open',NULL,NULL,1,1,1,NULL,NULL,NULL,'','2013-01-11 21:06:21',0),(1524,27,2,'Short name','Short name or acronym, use this for common shortened names or acronyms, eg. ARC for Australian Research Council','',001,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:06:06',0),(1525,13,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',004,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:56',0),(1528,15,4,'Extended description','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','',003,80,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:25:47',0),(1529,15,98,'TEI Document','TEI Document Reference','',007,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:27:42',0),(1530,15,96,'DOS Annotation type','Annotation type','',012,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,'','2013-01-11 21:28:20',0),(1531,15,89,'Status','Internal status','',013,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:28:52',9),(1533,13,9,'Date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','',007,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:56',0),(1534,13,10,'Start date','Start Date (may also include time and/or have fuzzy limits)','Note that this detail describes a temporal object and can be used to describe dates that have a level of uncertainty',008,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:56',0),(1535,13,11,'End date','End Date (may also include time and/or have fuzzy limits)','Please provide an extended description for display on rollover ...',009,15,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:56',0),(1536,13,76,'Publisher','Publisher','',010,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1537,13,77,'Version','Version','',011,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1538,13,78,'Language','Language','',012,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1539,13,79,'Tracking system ID','Tracking system ID','',013,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1540,13,80,'Source','Source','',014,40,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1541,13,81,'WordML file','WordML file','',015,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1542,13,29,'Mime Type','Mime Type','',016,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1543,13,89,'Status','Internal status','',017,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1544,13,90,'Contributor pointer','Contributor pointer','',018,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1545,13,94,'Rights','Rights','',019,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-11 21:38:25',9),(1546,29,1,'Name / Title','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,40,'',0,NULL,'required','viewable','open','open',NULL,NULL,1,1,1,NULL,NULL,NULL,'','2013-01-11 21:40:38',0),(1547,15,13,'Record pointer (unconstrained)','A pointer to any type of record, use apprporiately constrained pointers for preference','',014,0,'',0,NULL,'optional','viewable','open','open',NULL,NULL,1,0,1,NULL,NULL,NULL,NULL,'2013-01-12 21:36:47',9);
/*!40000 ALTER TABLE `defRecStructure` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woot_Chunks`
--

DROP TABLE IF EXISTS `woot_Chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `woot_Chunks`
--

LOCK TABLES `woot_Chunks` WRITE;
/*!40000 ALTER TABLE `woot_Chunks` DISABLE KEYS */;
/*!40000 ALTER TABLE `woot_Chunks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysTableLastUpdated`
--

DROP TABLE IF EXISTS `sysTableLastUpdated`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysTableLastUpdated` (
  `tlu_TableName` varchar(40) NOT NULL COMMENT 'Name of table for which we are recording time of last update',
  `tlu_DateStamp` datetime NOT NULL default '0000-00-00 00:00:00' COMMENT 'Date and time of last update of table',
  `tlu_CommonObj` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Indicates tables which contain data defs required in common-obj',
  PRIMARY KEY  (`tlu_TableName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Datestamp, determines if updates since definitions loaded in';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysTableLastUpdated`
--

LOCK TABLES `sysTableLastUpdated` WRITE;
/*!40000 ALTER TABLE `sysTableLastUpdated` DISABLE KEYS */;
INSERT INTO `sysTableLastUpdated` VALUES ('defCalcFunctions','0000-00-00 00:00:00',1),('defCrosswalk','0000-00-00 00:00:00',1),('defDetailTypeGroups','2013-02-20 09:01:07',1),('defDetailTypes','2013-02-20 09:01:07',1),('defFileExtToMimetype','0000-00-00 00:00:00',1),('defLanguages','0000-00-00 00:00:00',1),('defOntologies','0000-00-00 00:00:00',1),('defRecStructure','2013-02-20 09:01:07',1),('defRecTypeGroups','2013-02-20 09:01:06',1),('defRecTypes','2013-02-20 09:01:07',1),('defRelationshipConstraints','0000-00-00 00:00:00',1),('defTerms','2013-02-20 09:01:07',1),('defTranslations','0000-00-00 00:00:00',1),('defURLPrefixes','0000-00-00 00:00:00',1),('sysDBNameCache','0000-00-00 00:00:00',1),('sysIdentification','0000-00-00 00:00:00',1),('sysUGrps','2013-02-21 06:38:42',1),('sysUsrGrpLinks','2013-02-21 06:38:42',1),('usrHyperlinkFilters','0000-00-00 00:00:00',1),('usrTags','0000-00-00 00:00:00',1);
/*!40000 ALTER TABLE `sysTableLastUpdated` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defCalcFunctions`
--

DROP TABLE IF EXISTS `defCalcFunctions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defCalcFunctions` (
  `cfn_ID` smallint(3) unsigned NOT NULL auto_increment COMMENT 'Primary key of defCalcFunctions table',
  `cfn_Domain` enum('calcfieldstring','pluginphp') NOT NULL default 'calcfieldstring' COMMENT 'Domain of application of this function specification',
  `cfn_FunctionSpecification` text NOT NULL COMMENT 'A function or chain of functions, or some PHP plugin code',
  `cfn_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`cfn_ID`)
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
-- Table structure for table `sysArchive`
--

DROP TABLE IF EXISTS `sysArchive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysArchive`
--

LOCK TABLES `sysArchive` WRITE;
/*!40000 ALTER TABLE `sysArchive` DISABLE KEYS */;
/*!40000 ALTER TABLE `sysArchive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defURLPrefixes`
--

DROP TABLE IF EXISTS `defURLPrefixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defURLPrefixes` (
  `urp_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'ID which will be stored as proxy for the URL prefix',
  `urp_Prefix` varchar(250) NOT NULL COMMENT 'URL prefix which is prepended to record URLs',
  `urp_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`urp_ID`),
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
-- Table structure for table `defCrosswalk`
--

DROP TABLE IF EXISTS `defCrosswalk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defCrosswalk`
--

LOCK TABLES `defCrosswalk` WRITE;
/*!40000 ALTER TABLE `defCrosswalk` DISABLE KEYS */;
/*!40000 ALTER TABLE `defCrosswalk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defFileExtToMimetype`
--

DROP TABLE IF EXISTS `defFileExtToMimetype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defFileExtToMimetype`
--

LOCK TABLES `defFileExtToMimetype` WRITE;
/*!40000 ALTER TABLE `defFileExtToMimetype` DISABLE KEYS */;
INSERT INTO `defFileExtToMimetype` VALUES ('ai','application/postscript',0,'','Adobe Illustrator Drawing','','2013-01-09 00:26:57'),('aif','audio/x-aiff',0,'movie.gif','AIFF audio','','2013-01-09 00:26:57'),('aifc','audio/x-aiff',0,'movie.gif','AIFF audio','','2013-01-09 00:26:57'),('aiff','audio/x-aiff',0,'movie.gif','AIFF audio','','2013-01-09 00:26:57'),('asc','text/plain',1,'txt.gif','Plain text','','2013-01-09 00:26:57'),('au','audio/basic',0,'movie.gif','AU audio','','2013-01-09 00:26:57'),('avi','video/x-msvideo',0,'movie.gif','AVI video','','2013-01-09 00:26:57'),('bcpio','application/x-bcpio',0,'','Binary CPIO Archive','','2013-01-09 00:26:57'),('bin','application/octet-stream',0,'','BinHex','','2013-01-09 00:26:57'),('bmp','image/bmp',1,'image.gif','BMP image','','2013-01-09 00:26:57'),('c','text/plain',0,'','C Source Code','','2013-01-09 00:26:57'),('cda','application/x-cdf',0,'','CD Audio Track','','2013-01-09 00:26:57'),('cdf','application/octet-stream',0,'','Affymetrix Chip Description Fil','','2013-01-09 00:26:57'),('cgm','image/cgm',0,'image.gif','Computer Graphics Metafile','','2013-01-09 00:26:57'),('class','application/octet-stream',0,'','Java Class','','2013-01-09 00:26:57'),('cpio','application/x-cpio',0,'','CPIO Archive','','2013-01-09 00:26:57'),('cpt','image/x-cpt',0,'','Corel Photo-Paint Image','','2013-01-09 00:26:57'),('csh','application/x-photoshop',0,'','Corel Photo-Paint Image','','2013-01-09 00:26:57'),('css','text/css',1,'','Cascading Style Sheets','','2013-01-09 00:26:57'),('csv','text/comma-separated-values',0,'','Comma-Separated Values','','2013-01-09 00:26:57'),('dat','application/octet-stream',0,'','General Data File','','2013-01-09 00:26:57'),('db','application/octet-stream',0,'','General Database File','','2013-01-09 00:26:57'),('dcr','application/x-director',0,'','Delphi Component Resource','','2013-01-09 00:26:57'),('dir','application/x-director',0,'','Macromedia Director Movie','','2013-01-09 00:26:57'),('djvu','image/vnd.djvu',1,'image.gif','DjVu Scanned Image','','2013-01-09 00:26:57'),('dll','application/octet-stream',0,'','Windows system','','2013-01-09 00:26:57'),('dmg','application/octet-stream',0,'','Mac OS X Disk Image','','2013-01-09 00:26:57'),('dms','application/octet-stream',0,'','Amiga Diskmasher Disk Image','','2013-01-09 00:26:57'),('doc','application/msword',0,'doc.gif','Microsoft Word Document','','2013-01-09 00:26:57'),('docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',0,'docx.gif','Microsoft Word Document','','2013-01-09 00:26:57'),('dot','application/msword',0,'dot.gif','Microsoft Word Document Templat','','2013-01-09 00:26:57'),('dtd','application/xml-dtd',1,'','Document Type Definition','','2013-01-09 00:26:57'),('dvi','application/x-dvi',0,'','Device Independent File','','2013-01-09 00:26:57'),('dxr','application/vnd.dxr',0,'','Macromedia Director Protected M','','2013-01-09 00:26:57'),('eml','message/rfc822',0,'','Microsoft Outlook Express E-Mai','','2013-01-09 00:26:57'),('enz','application/x-endnote-connect',0,'','Endnote','','2013-01-09 00:26:57'),('eps','image/x-eps',0,'','Encapsulated PostScript Image','','2013-01-09 00:26:57'),('etx','text/x-setext',0,'','Setext','','2013-01-09 00:26:57'),('exe','application/octet-stream',0,'','Windows executable','','2013-01-09 00:26:57'),('flv','video/x-flv',0,'','Flash Video File','','2013-01-09 00:26:57'),('gif','image/gif',1,'image.gif','GIF image','','2013-01-09 00:26:57'),('gra','application/x-msgraph',0,'','Microsoft Graph Chart','','2013-01-09 00:26:57'),('gram','application/srgs',0,'','','','2013-01-09 00:26:57'),('grxml','application/srgs+xml',1,'','','','2013-01-09 00:26:57'),('gtar','application/x-gtar',0,'','','','2013-01-09 00:26:57'),('hdf','application/x-hdf',0,'','Hierarchical Data Format File','','2013-01-09 00:26:57'),('hqx','application/mac-binhex40',0,'','BinHex compressed','','2013-01-09 00:26:57'),('htm','text/html',1,'html.gif','HTML source','','2013-01-09 00:26:57'),('html','text/html',1,'html.gif','HTML source','','2013-01-09 00:26:57'),('ice','x-conference/x-cooltalk',0,'','ICEOWS Archive','','2013-01-09 00:26:57'),('ico','image/x-icon',0,'image.gif','Windows Icon','','2013-01-09 00:26:57'),('ics','text/calendar',0,'','Swiftview Imaging Command Set','','2013-01-09 00:26:57'),('ief','image/ief',0,'image.gif','','','2013-01-09 00:26:57'),('ifb','text/calendar',0,'','','','2013-01-09 00:26:57'),('iges','application/iges',0,'','IGES Drawing','','2013-01-09 00:26:57'),('igs','application/iges',0,'','IGES Drawing','','2013-01-09 00:26:57'),('ini','text/plain',0,'','Initialization File','','2013-01-09 00:26:57'),('jar','application/java-archive',0,'','Java Archive','','2013-01-09 00:26:57'),('jpe','image/jpeg',1,'image.gif','JPEG image','','2013-01-09 00:26:57'),('jpeg','image/jpeg',1,'image.gif','JPEG image','','2013-01-09 00:26:57'),('jpg','image/jpeg',1,'image.gif','JPEG image','','2013-01-09 00:26:57'),('js','application/x-javascript',0,'','JavaScript','','2013-01-09 00:26:57'),('kar','audio/x-midi',0,'movie.gif','MIDI Karaoke Song','','2013-01-09 00:26:57'),('kml','application/vnd.google-earth.kml+xml',0,'','Keyhole Markup Language File','','2013-01-09 00:26:57'),('kmz','application/vnd.google-earth.kmz',0,'','Compressed KML File','','2013-01-09 00:26:57'),('latex','application/x-latex',0,'','LaTeX source','','2013-01-09 00:26:57'),('lha','application/octet-stream',0,'','LHA compressed','','2013-01-09 00:26:57'),('lnk','application/x-ms-shortcut',0,'','Windows Shortcut','','2013-01-09 00:26:57'),('lzh','application/octet-stream',0,'','LZH compressed','','2013-01-09 00:26:57'),('m3u','audio/x-mpegurl',0,'movie.gif','M3U Playlist File','','2013-01-09 00:26:57'),('m4u','video/vnd.mpegurl',0,'movie.gif','M4U Playlist File','','2013-01-09 00:26:57'),('man','application/x-troff-man',0,'','','','2013-01-09 00:26:57'),('mathml','application/mathml+xml',1,'','MathML','','2013-01-09 00:26:57'),('me','application/x-troff-me',0,'','Readme Text','','2013-01-09 00:26:57'),('mesh','model/mesh',0,'','','','2013-01-09 00:26:57'),('mid','audio/midi',0,'movie.gif','MIDI audio','','2013-01-09 00:26:57'),('midi','audio/midi',0,'movie.gif','MIDI audio','','2013-01-09 00:26:57'),('mif','application/vnd.mif',0,'','Adobe FrameMaker Interchange Fo','','2013-01-09 00:26:57'),('mov','video/quicktime',0,'movie.gif','QuickTime Movie','','2013-01-09 00:26:57'),('movie','video/x-sgi-movie',0,'movie.gif','QuickTime Movie','','2013-01-09 00:26:57'),('mp2','audio/mpeg',0,'movie.gif','MPEG audio','','2013-01-09 00:26:57'),('mp3','audio/mpeg',0,'movie.gif','MP3 audio','','2013-01-09 00:26:57'),('mpe','video/mpeg',0,'movie.gif','MPEG video','','2013-01-09 00:26:57'),('mpeg','video/mpeg',0,'movie.gif','MPEG video','','2013-01-09 00:26:57'),('mpg','video/mpeg',0,'movie.gif','MPEG video','','2013-01-09 00:26:57'),('mpga','audio/mpeg',0,'movie.gif','MPEG audio','','2013-01-09 00:26:57'),('ms','application/x-troff-ms',0,'','','','2013-01-09 00:26:57'),('msh','model/mesh',0,'','','','2013-01-09 00:26:57'),('mxd','application/octet-stream',0,'','ArcMAP Document','','2013-01-09 00:26:57'),('mxu','video/vnd.mpegurl',0,'movie.gif','','','2013-01-09 00:26:57'),('nc','application/x-netcdf',0,'','','','2013-01-09 00:26:57'),('oda','application/oda',0,'','','','2013-01-09 00:26:57'),('odt','application/vnd.oasis.opendocument.text',0,'','AIBO ODA File','','2013-01-09 00:26:57'),('ogg','application/ogg',0,'','Ogg Vorbis','','2013-01-09 00:26:57'),('one','application/onenote',0,'','Microsoft OneNote Document','','2013-01-09 00:26:57'),('pbm','image/x-portable-bitmap',1,'image.gif','','','2013-01-09 00:26:57'),('pdb','chemical/x-pdb',0,'','','','2013-01-09 00:26:57'),('pdf','application/pdf',1,'pdf.gif','Adobe Acrobat','','2013-01-09 00:26:57'),('pgm','image/x-portable-graymap',1,'image.gif','Portable Bitmap Image','','2013-01-09 00:26:57'),('pgn','application/x-chess-pgn',0,'','Portable Graymap Image','','2013-01-09 00:26:57'),('pl','application/x-perl',0,'','Perl Script','','2013-01-09 00:26:57'),('png','image/png',1,'image.gif','PNG image','','2013-01-09 00:26:57'),('pnm','image/x-portable-anymap',1,'image.gif','Portable Anymap Image','','2013-01-09 00:26:57'),('ppm','image/x-portable-pixmap',1,'image.gif','Portable Pixmap Image','','2013-01-09 00:26:57'),('pps','application/mspowerpoint',0,'','Microsoft PowerPoint Slideshow','','2013-01-09 00:26:57'),('ppt','application/vnd.ms-powerpoint',0,'','Microsoft PowerPoint Presentati','','2013-01-09 00:26:57'),('pptx','application/vnd.openxmlformats',0,'','Microsoft PowerPoint Presentati','','2013-01-09 00:26:57'),('ps','application/postscript',0,'','PostScript','','2013-01-09 00:26:57'),('psd','image/vnd.adobe.photoshop',0,'','Adobe Photoshop Image','','2013-01-09 00:26:57'),('qt','video/quicktime',0,'movie.gif','QuickTime video','','2013-01-09 00:26:57'),('ra','audio/x-pn-realaudio',0,'movie.gif','RealAudio','','2013-01-09 00:26:57'),('ram','audio/x-pn-realaudio',0,'movie.gif','RealAudio','','2013-01-09 00:26:57'),('ras','image/x-ras',1,'image.gif','Sun Raster Image','','2013-01-09 00:26:57'),('rdf','application/rdf+xml',1,'','Mozilla extension','','2013-01-09 00:26:57'),('rgb','image/x-rgb',0,'image.gif','','','2013-01-09 00:26:57'),('rm','application/vnd.rn-realmedia',0,'','RealAudio','','2013-01-09 00:26:57'),('rtf','text/rtf',1,'','Rich text','','2013-01-09 00:26:57'),('rtx','text/richtext',1,'','','','2013-01-09 00:26:57'),('sgm','text/sgml',1,'','Standard Generalized Markup Lan','','2013-01-09 00:26:57'),('sgml','text/sgml',1,'','Standard Generalized Markup Lan','','2013-01-09 00:26:57'),('sh','application/x-sh',0,'','Unix Shell Script','','2013-01-09 00:26:57'),('shar','application/x-shar',0,'','Unix Shell Archive','','2013-01-09 00:26:57'),('shp','application/octet-stream',0,'','ArcView Shape File','','2013-01-09 00:26:57'),('shs','application/octet-stream',0,'','Shell Scrap File','','2013-01-09 00:26:57'),('silo','model/mesh',0,'','','','2013-01-09 00:26:57'),('sit','application/x-stuffit',0,'','StuffIt compressed','','2013-01-09 00:26:57'),('skd','application/x-koan',0,'','','','2013-01-09 00:26:57'),('skm','video/x-skm',0,'','SKM Video','','2013-01-09 00:26:57'),('skp','application/vnd.sketchup.skp',0,'','SketchUp Document','','2013-01-09 00:26:57'),('skt','application/x-koan',0,'','','','2013-01-09 00:26:57'),('smi','application/x-smil',0,'','Synchronized Multimedia Integra','','2013-01-09 00:26:57'),('smil','application/x-smil',0,'','Synchronized Multimedia Integra','','2013-01-09 00:26:57'),('snd','audio/basic',0,'movie.gif','SND audio','','2013-01-09 00:26:57'),('so','application/octet-stream',0,'','Unix Shared Library','','2013-01-09 00:26:57'),('spl','application/x-futuresplash',0,'','FutureSplash Animator Document','','2013-01-09 00:26:57'),('sql','text/plain',0,'','SQL Script','','2013-01-09 00:26:57'),('src','text/plain',0,'','General Source Code File','','2013-01-09 00:26:57'),('sv4cpio','application/x-sv4cpio',0,'','System V Release 4 CPIO Archive','','2013-01-09 00:26:57'),('sv4crc','application/x-sv4crc',0,'','System V Release 4 CPIO Checksu','','2013-01-09 00:26:57'),('svg','image/svg+xml',1,'image.gif','SVG graphics','','2013-01-09 00:26:57'),('swf','application/x-shockwave-flash',0,'','Adobe Shockwave','','2013-01-09 00:26:57'),('t','application/x-troff',0,'','','','2013-01-09 00:26:57'),('tar','application/x-tar',0,'','TAR archive','','2013-01-09 00:26:57'),('tcl','text/x-tcl',0,'','TCL Script','','2013-01-09 00:26:57'),('tex','application/x-latex',0,'','LaTeX Document','','2013-01-09 00:26:57'),('texi','application/x-texinfo',0,'','GNU Texinfo Document','','2013-01-09 00:26:57'),('texinfo','application/x-texinfo',0,'','GNU Texinfo Document','','2013-01-09 00:26:57'),('tif','image/tiff',0,'image.gif','TIFF image','','2013-01-09 00:26:57'),('tiff','image/tiff',0,'image.gif','TIFF image','','2013-01-09 00:26:57'),('tr','text/plain',0,'','TCL Trace Output','','2013-01-09 00:26:57'),('tsv','text/tab-separated-values',1,'','Tab-Separated Values','','2013-01-09 00:26:57'),('ttf','application/x-font-ttf',0,'','TrueType Font','','2013-01-09 00:26:57'),('txt','text/plain',1,'txt.gif','Plain text','','2013-01-09 00:26:57'),('ustar','application/x-ustar',0,'','','','2013-01-09 00:26:57'),('vcd','application/octet-stream',0,'','Virtual CD Image','','2013-01-09 00:26:57'),('vob','application/octet-stream',0,'','DVD Video Object','','2013-01-09 00:26:57'),('vrml','model/vrml',1,'','VRML Document','','2013-01-09 00:26:57'),('vsd','application/vnd.visio',0,'','Microsoft Visio Diagram','','2013-01-09 00:26:57'),('vxml','application/voicexml+xml',1,'','','','2013-01-09 00:26:57'),('wav','audio/x-wav',0,'movie.gif','WAV audio','','2013-01-09 00:26:57'),('wbmp','image/vnd.wap.wbmp',1,'image.gif','Wireless Bitmap Image','','2013-01-09 00:26:57'),('wbxml','application/vnd.wap.wbxml',1,'','','','2013-01-09 00:26:57'),('wma','audio/x-ms-wma',0,'','Windows Media Audio','','2013-01-09 00:26:57'),('wmf','image/x-wmf',0,'','Windows Metafile','','2013-01-09 00:26:57'),('wml','text/vnd.wap.wml',0,'','Wireless Markup Language Docume','','2013-01-09 00:26:57'),('wmlc','application/vnd.wap.wmlc',0,'','','','2013-01-09 00:26:57'),('wmls','text/vnd.wap.wmlscript',0,'','','','2013-01-09 00:26:57'),('wmlsc','application/vnd.wap.wmlscriptc',0,'','','','2013-01-09 00:26:57'),('wmv','video/x-ms-wmv',0,'','Windows Media Video','','2013-01-09 00:26:57'),('wrl','model/vrml',1,'','','','2013-01-09 00:26:57'),('xbm','image/x-xbitmap',0,'image.gif','X Bitmap Image','','2013-01-09 00:26:57'),('xht','application/xhtml+xml',1,'','XHTML Document','','2013-01-09 00:26:57'),('xhtml','application/xhtml+xml',1,'','XHTML Document','','2013-01-09 00:26:57'),('xls','application/vnd.ms-excel',0,'','Microsoft Excel Worksheet','','2013-01-09 00:26:57'),('xlsx','application/vnd.openxmlformats',0,'','Microsoft Excel Worksheet','','2013-01-09 00:26:57'),('xml','application/xml',1,'','XML','','2013-01-09 00:26:57'),('xpm','image/x-xpixmap',0,'image.gif','X Pixmap','','2013-01-09 00:26:57'),('xsl','text/xml',1,'','Extensible Stylesheet Language ','','2013-01-09 00:26:57'),('xslt','text/xslt',1,'','Extensible Stylesheet Language ','','2013-01-09 00:26:57'),('xul','application/vnd.mozilla.xul+xml',1,'','XML User Interface Language Fil','','2013-01-09 00:26:57'),('xwd','image/x-xwindowdump',0,'image.gif','X Window Dump','','2013-01-09 00:26:57'),('xyz','application/octet-stream',0,'','Tinker Cartesian Coordinates','','2013-01-09 00:26:57'),('zip','application/zip',0,'zip.gif','ZIP compressed','','2013-01-09 00:26:57');
/*!40000 ALTER TABLE `defFileExtToMimetype` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Records`
--

DROP TABLE IF EXISTS `Records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Records`
--

LOCK TABLES `Records` WRITE;
/*!40000 ALTER TABLE `Records` DISABLE KEYS */;
/*!40000 ALTER TABLE `Records` ENABLE KEYS */;
UNLOCK TABLES;
--
-- WARNING: old server version. The following dump may be incomplete.
--
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
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `update_record_trigger` BEFORE UPDATE ON `Records` FOR EACH ROW begin
		if @suppress_update_trigger is null then
			set @rec_version := last_insert_id();
		end if;
		if NEW.rec_URL != OLD.rec_URL OR NEW.rec_URL is null then
			set NEW.rec_URLLastVerified := NULL;
		end if;
	end */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
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
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `delete_record_trigger` AFTER DELETE ON `Records` FOR EACH ROW begin
		set @rec_version := last_insert_id();
	
		if OLD.rec_RecTypeID = 1 then
			delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
		end if;
	end */;;
DELIMITER ;

--
-- Table structure for table `recUploadedFiles`
--

DROP TABLE IF EXISTS `recUploadedFiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recUploadedFiles`
--

LOCK TABLES `recUploadedFiles` WRITE;
/*!40000 ALTER TABLE `recUploadedFiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `recUploadedFiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysUGrps`
--

DROP TABLE IF EXISTS `sysUGrps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8 COMMENT='Users/Groups diff. by ugr_Type. May defer to similar table i';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysUGrps`
--

LOCK TABLES `sysUGrps` WRITE;
/*!40000 ALTER TABLE `sysUGrps` DISABLE KEYS */;
INSERT INTO `sysUGrps` VALUES (0,'workgroup','Everyone','Group 0 represents all logged in users. DO NOT DELETE.',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=0','every','user',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2013-02-19 22:00:57'),(1,'workgroup','Database Managers','Group 1 owns databases by default. DO NOT DELETE.','This workgroup contains the administrators of the database. The first user (user #2) has special status as the master user. They cannot be deleted','PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=1','db','owners',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2013-02-19 22:00:57'),(2,'user','admin','',NULL,'0v3AYMbUTI4vQ','artem.osmakov@sydney.edu.au','Artem','Osmakov','Arts eResearch','University of Sydney','','','','na','y','2013-02-20 09:17:37',3,1,0,NULL,NULL,NULL,0,'2013-02-19 22:17:37'),(3,'workgroup','Other users','Another group, can be used eg. for guests',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=3','other','users',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2013-02-19 22:00:57'),(4,'user','debug','debug user',NULL,'6RAQw1ULVIvIA','info@heuristscholar.org','debug','user','Arts eResearch','University of Sydney',NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2013-02-19 22:00:58'),(1000,'user','Johnson','Ian Johnson',NULL,'V74MGtjGpIkS2','ian.johnson@sydney.edu.au','Ian','Johnson',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2013-02-20 19:38:42');
/*!40000 ALTER TABLE `sysUGrps` ENABLE KEYS */;
UNLOCK TABLES;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUGrps_last_insert` AFTER INSERT ON `sysUGrps` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps" */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUGrps_last_update` AFTER UPDATE ON `sysUGrps` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps" */;;
DELIMITER ;

--
-- Table structure for table `defDetailTypes`
--

DROP TABLE IF EXISTS `defDetailTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8 COMMENT='The detail types (fields) which can be attached to records';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defDetailTypes`
--

LOCK TABLES `defDetailTypes` WRITE;
/*!40000 ALTER TABLE `defDetailTypes` DISABLE KEYS */;
INSERT INTO `defDetailTypes` VALUES (1,'Name / Title','Name or phrase used to identify the represented object/entity','freetext','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','','','reserved',2,'Name',1,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(2,'Short name','Short or familiar name, phrase or acronym used to identify the represented object/entity','freetext','Short name or acronym, use this for common shortened names or acronyms, eg. ARC for Australian Research Council','','','approved',2,'Short Name',2,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(3,'Short summary','Short summary or description of the object/entity','blocktext','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','','','reserved',2,'Short summary',3,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(4,'Extended description','Extended description of the object/entity','blocktext','An extended description, use this where text will be limited and does not require any formatting, otherwise use the WYSIWYG text tab to enter formatted text','','','approved',2,'Extended description',4,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(5,'Target record pointer','Unconstrained pointer to the target linked record for a relationship.','resource','The target record in a relationship record or other record referencing another record with an unconstrained pointer','Please provide an extended description for display on rollover ...','','reserved',2,'Target record pointer',5,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(6,'Relationship type','Term or term phrase representing the conceptual relationship between entities','relationtype','This is a special field used in relationship records, and should not be used elsewhere','This is a special field used in relationship records, and should not be used elsewhere, as it may lead to data which is not properly rendered','','reserved',2,'Relationship type',6,99,0,'{\"3112\":{\"3197\":{},\"3022\":{},\"3190\":{},\"3040\":{},\"3199\":{},\"3021\":{},\"3054\":{},\"3191\":{},\"3053\":{},\"3067\":{},\"3073\":{},\"3075\":{},\"3200\":{},\"3077\":{},\"3079\":{},\"3192\":{},\"3004\":{},\"3039\":{},\"3078\":{},\"3193\":{},\"3107\":{},\"3198\":{},\"3187\":{},\"3194\":{},\"3072\":{},\"3074\":{},\"3195\":{},\"3196\":{},\"3076\":{},\"3106\":{},\"3201\":{}},\"3123\":{\"3207\":{},\"3208\":{},\"3209\":{},\"3210\":{},\"3211\":{},\"3212\":{},\"3213\":{},\"3167\":{},\"3166\":{},\"3168\":{},\"3169\":{},\"3165\":{},\"3163\":{},\"3164\":{}},\"3114\":{\"3203\":{},\"3020\":{},\"3024\":{},\"3224\":{},\"3036\":{},\"3038\":{},\"3217\":{},\"3181\":{},\"3037\":{},\"3225\":{},\"3058\":{},\"3062\":{},\"3064\":{},\"3066\":{},\"3226\":{},\"3069\":{},\"3204\":{},\"3202\":{},\"3161\":{},\"3019\":{},\"3023\":{},\"3176\":{},\"3035\":{},\"3162\":{},\"3129\":{},\"3102\":{},\"3175\":{},\"3101\":{},\"3057\":{},\"3061\":{},\"3063\":{},\"3065\":{},\"3068\":{},\"3160\":{},\"3159\":{},\"3174\":{}},\"3110\":{\"3084\":{},\"3088\":{},\"3090\":{\"3188\":{},\"3189\":{}},\"3092\":{},\"3094\":{},\"3133\":{},\"3116\":{\"3243\":{},\"3242\":{}},\"3115\":{\"3099\":{},\"3100\":{}},\"3103\":{},\"3105\":{},\"3083\":{},\"3089\":{\"3095\":{},\"3104\":{}},\"3118\":{\"3087\":{},\"3117\":{}}},\"3001\":{\"3170\":{},\"3108\":{},\"3173\":{},\"3172\":{},\"3171\":{},\"3177\":{}},\"3122\":{\"3214\":{},\"3215\":{},\"3216\":{},\"3158\":{},\"3156\":{},\"3157\":{}},\"3111\":{\"3006\":{},\"3009\":{},\"3014\":{},\"3206\":{},\"3184\":{},\"3016\":{},\"3018\":{},\"3026\":{},\"3028\":{},\"3030\":{},\"3032\":{},\"3179\":{},\"3034\":{},\"3042\":{},\"3044\":{},\"3046\":{},\"3048\":{},\"3050\":{},\"3052\":{},\"3056\":{},\"3186\":{},\"3183\":{},\"3060\":{},\"3185\":{},\"3180\":{},\"3071\":{},\"3178\":{},\"3126\":{},\"3205\":{},\"3015\":{},\"3017\":{},\"3025\":{},\"3027\":{},\"3029\":{},\"3031\":{},\"3127\":{},\"3033\":{},\"3041\":{},\"3013\":{},\"3043\":{},\"3045\":{},\"3047\":{},\"3049\":{},\"3182\":{},\"3130\":{},\"3051\":{},\"3055\":{},\"3134\":{},\"3131\":{},\"3059\":{},\"3132\":{},\"3128\":{},\"3070\":{},\"3124\":{},\"3125\":{}},\"3120\":{\"3218\":{},\"3219\":{},\"3220\":{},\"3221\":{},\"3222\":{},\"3223\":{},\"3137\":{},\"3140\":{},\"3136\":{},\"3139\":{},\"3135\":{},\"3138\":{}},\"3109\":{\"3002\":{},\"3003\":{},\"3008\":{},\"3010\":{},\"3119\":{},\"3080\":{},\"3081\":{},\"3082\":{},\"3085\":{},\"3093\":{},\"3096\":{},\"3098\":{},\"3007\":{},\"3097\":{}},\"3113\":{\"3005\":{},\"3012\":{},\"3091\":{},\"3011\":{}},\"3121\":{\"3227\":{},\"3228\":{},\"3229\":{},\"3230\":{},\"3231\":{},\"3232\":{},\"3233\":{},\"3234\":{},\"3235\":{},\"3236\":{},\"3237\":{},\"3238\":{},\"3239\":{},\"3240\":{},\"3241\":{},\"3149\":{},\"3146\":{},\"3148\":{},\"3143\":{},\"3142\":{},\"3147\":{},\"3151\":{},\"3150\":{},\"3153\":{},\"3152\":{},\"3154\":{},\"3141\":{},\"3144\":{},\"3145\":{},\"3155\":{}}}','[\"3112\",\"3123\",\"3114\",\"3110\",\"3001\",\"3122\",\"3111\",\"3120\",\"3109\",\"3113\",\"3121\"]','',0,0,'viewable','2013-01-09 00:26:57',1),(7,'Source record pointer','Unconstrained pointer to the source linked record for a relationship.','resource','Pointer to the record which is being linked to a target record (often by a relationship record)','Fundamentally important detail used in record relationships that identifies one of the partner records in a relationship. Must be used in conjunction with relationship type and target record pointer to create a valid relationship','','reserved',2,'Source record pointer',7,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(8,'Comments on relationship','Record pointer constrained to commentary records','resource','A pointer to a commentary/interpretation record (for spatial, temporal or general information)','This detail acknowledges that a given piece of data may have many commentaries or interpretations.','','reserved',2,'Pointer to commentary',8,99,0,'','','8',0,0,'viewable','2013-01-09 00:26:57',1),(9,'Date','Generic date','date','Enter a date either as a simple calendar date or through the temporal object popup (for complex/uncertain dates)','','','reserved',2,'Date',9,101,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(10,'Start date','Date representing the beginning of real/conceptual object/entity ','date','Start Date (may also include time and/or have fuzzy limits)','Note that this detail describes a temporal object and can be used to describe dates that have a level of uncertainty','','reserved',2,'Start date',10,101,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(11,'End date','Date representing the ending of real/conceptual object/entity','date','End Date (may also include time and/or have fuzzy limits)','Please provide an extended description for display on rollover ...','','reserved',2,'End date',11,101,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(12,'Heurist Query String','Heurist Query String','freetext','A string representing a valid Heurist query.','Please provide an extended description for display on rollover ...','','reserved',2,'Query String',12,102,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(13,'Record pointer (unconstrained)','General record pointer','resource','A pointer to any type of record, use apprporiately constrained pointers for preference','','','approved',2,'Record pointer',13,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(14,'Transcriber >','Record pointer constrained to person or organisation records','resource','Pointer to the person who transcribes or summarises interpretation information ','','','approved',2,'Transcriber of interpretation',14,8,0,'','','4,10',0,1,'viewable','2013-01-09 00:26:57',1),(15,'Author or Creator >','Record pointer constrained to person or organisation records','resource','The person or organisation who created the record (author, organisation, artist etc.)','May include authors, artists, organisations that sponsored a resource etc.','','reserved',2,'Creator - author, artist etc.',15,8,0,'','','4,10',0,1,'viewable','2013-01-09 00:26:57',1),(16,'Person >','Record pointer constrained to person records','resource','Pointer to a person record (use Author/Creator field type where organisations as well as people may apply)','','','approved',2,'Pointer to Person',16,8,0,'','','10',0,1,'viewable','2013-01-09 00:26:57',1),(17,'Contact details or URL','Please document the nature of this detail type (field)) ...','freetext','Contact details, either as text or a URL to eg. a phonebook record or search','','','approved',2,'Contact details or URL',17,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(18,'Given names','Name or name list additionally given to the represented object/entity ','freetext','Given names','','','approved',2,'Given names',18,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(19,'Honorific','Prefix for the object\'s name representing status and/or achievement','enum','Title or honorific (Dr, Mrs, Ms, Professor, Sir etc.)','','','approved',2,'Honorific',19,8,0,'{\"97\":{},\"96\":{},\"529\":{},\"93\":{},\"94\":{},\"95\":{},\"98\":{}}','','',0,1,'viewable','2013-01-09 00:26:57',1),(20,'Sex','Gender type','enum','Sex of a person (or other living creature)','','','approved',2,'Gender',20,8,0,'{\"415\":{},\"414\":{},\"527\":{},\"528\":{}}','','',0,1,'viewable','2013-01-09 00:26:57',1),(21,'Organisation >','Record pointer constrained to organisation records','resource','A legally defined organisation or group of people identifying as an organisation','','','approved',2,'Pointer to Organisation',21,8,0,'','','4',0,1,'viewable','2013-01-09 00:26:57',1),(22,'Organisation type','Term categorising the organisation object type','enum','Organisation type','','','open',2,'Organisation type',22,8,0,'{\"17\":{},\"132\":{},\"130\":{},\"14\":{},\"15\":{},\"19\":{},\"129\":{},\"18\":{},\"12\":{},\"128\":{},\"20\":{},\"16\":{},\"21\":{},\"13\":{},\"131\":{},\"133\":{},\"11\":{\"518\":{},\"519\":{}}}','[\"11\"]','',0,1,'viewable','2013-01-09 00:26:57',1),(23,'Email address','Generic email address associated with the object/entity','freetext','Email address','','','approved',2,'Email address',23,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(24,'Email of sender','Email address associated with the sender of an email object/entity','freetext','Email of sender, used by the Email record type to store information about an imported email','Used in correspondence records to establish the email of the sender','','approved',2,'Email of sender',24,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(25,'Email of recipients','Email address(es) associated with the recipient(s) of an email object/entity','freetext','Email of recipients, used by the Email record type to store information about an imported email','Used in correspondence records to establish who the email is being sent to','','approved',2,'Email of recipients',25,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(26,'Country','Term identifying the country associated with the object/entity','enum','Country. The list is prepopulated, but can be extended, and includes 2 and 3 letter country codes','','','approved',2,'Country',26,101,0,'{\"204\":{},\"205\":{},\"206\":{},\"207\":{},\"208\":{},\"209\":{},\"210\":{},\"211\":{},\"212\":{},\"213\":{},\"214\":{},\"215\":{},\"216\":{},\"108\":{},\"217\":{},\"218\":{},\"219\":{},\"220\":{},\"221\":{},\"222\":{},\"223\":{},\"184\":{},\"224\":{},\"225\":{},\"226\":{},\"227\":{},\"228\":{},\"229\":{},\"230\":{},\"231\":{},\"185\":{},\"232\":{},\"233\":{},\"234\":{},\"235\":{},\"236\":{},\"251\":{},\"114\":{},\"237\":{},\"180\":{},\"238\":{},\"239\":{},\"240\":{},\"241\":{},\"242\":{},\"109\":{},\"243\":{},\"244\":{},\"245\":{},\"246\":{},\"247\":{},\"248\":{},\"249\":{},\"250\":{},\"252\":{},\"253\":{},\"160\":{},\"254\":{},\"195\":{},\"255\":{},\"256\":{},\"257\":{},\"165\":{},\"258\":{},\"259\":{},\"260\":{},\"261\":{},\"262\":{},\"263\":{},\"264\":{},\"265\":{},\"266\":{},\"267\":{},\"268\":{},\"110\":{},\"269\":{},\"270\":{},\"271\":{},\"272\":{},\"273\":{},\"274\":{},\"182\":{},\"275\":{},\"276\":{},\"111\":{},\"277\":{},\"278\":{},\"279\":{},\"280\":{},\"281\":{},\"282\":{},\"283\":{},\"284\":{},\"285\":{},\"286\":{},\"287\":{},\"288\":{},\"289\":{},\"290\":{},\"183\":{},\"291\":{},\"172\":{},\"292\":{},\"167\":{},\"168\":{},\"181\":{},\"293\":{},\"166\":{},\"112\":{},\"294\":{},\"295\":{},\"296\":{},\"161\":{},\"297\":{},\"298\":{},\"299\":{},\"300\":{},\"301\":{},\"302\":{},\"164\":{},\"303\":{},\"304\":{},\"305\":{},\"306\":{},\"307\":{},\"308\":{},\"309\":{},\"310\":{},\"311\":{},\"312\":{},\"313\":{},\"314\":{},\"315\":{},\"316\":{},\"317\":{},\"318\":{},\"319\":{},\"320\":{},\"321\":{},\"322\":{},\"323\":{},\"324\":{},\"325\":{},\"326\":{},\"327\":{},\"328\":{},\"329\":{},\"330\":{},\"331\":{},\"332\":{},\"333\":{},\"171\":{},\"334\":{},\"335\":{},\"336\":{},\"178\":{},\"337\":{},\"338\":{},\"117\":{},\"339\":{},\"340\":{},\"341\":{},\"342\":{},\"343\":{},\"344\":{},\"177\":{},\"345\":{},\"346\":{},\"347\":{},\"348\":{},\"349\":{},\"350\":{},\"351\":{},\"352\":{},\"353\":{},\"354\":{},\"355\":{},\"356\":{},\"357\":{},\"358\":{},\"359\":{},\"360\":{},\"361\":{},\"362\":{},\"363\":{},\"364\":{},\"365\":{},\"366\":{},\"367\":{},\"368\":{},\"369\":{},\"370\":{},\"371\":{},\"372\":{},\"373\":{},\"374\":{},\"375\":{},\"376\":{},\"377\":{},\"173\":{},\"378\":{},\"379\":{},\"380\":{},\"381\":{},\"179\":{},\"382\":{},\"113\":{},\"383\":{},\"384\":{},\"385\":{},\"386\":{},\"387\":{},\"186\":{},\"388\":{},\"169\":{},\"198\":{},\"389\":{},\"390\":{},\"116\":{},\"391\":{},\"392\":{},\"393\":{},\"394\":{},\"395\":{},\"396\":{},\"170\":{},\"397\":{},\"398\":{},\"399\":{},\"162\":{},\"400\":{},\"118\":{},\"401\":{},\"402\":{},\"403\":{},\"119\":{},\"163\":{},\"404\":{},\"405\":{},\"115\":{},\"406\":{},\"407\":{},\"408\":{},\"409\":{},\"410\":{},\"411\":{},\"412\":{}}','','',0,1,'viewable','2013-01-09 00:26:57',1),(27,'Place name','Term or phrase identifying the location associated with the object/entity','freetext','The name of a place or location, eg. city, town, suburb, village, locality','','','approved',2,'Place name',27,101,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(28,'Geospatial object','Geospatial shape associated with the object/entity','geo','A geospatial object providing a mappable location for the record - can be used for any type of record with spatial location','Used to store standard geographical information. Can be a point, line or polygon. This detail can be thought of as an encapsulation of various sub details - e.g. latitude and longitude - which would otherwise need to be managed in separate details and therefore be more challenging to manage','','reserved',2,'Geospatial object',28,101,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(29,'Mime Type','Please document the nature of this detail type (field)) ...','enum','Mime Type','','','approved',2,'Mime Type',29,106,0,'{\"534\":{},\"535\":{},\"536\":{},\"537\":{},\"538\":{},\"539\":{},\"540\":{},\"541\":{},\"542\":{}}','','',0,1,'viewable','2013-01-09 00:26:57',1),(30,'Tiled image type','Please document the nature of this detail type (field)) ...','enum','The type (map or image) of the tiled image.','Detail used to enable a common record type to be used for both tiled maps and high res images','','approved',2,'Map image layer type',30,106,0,'{\"544\":{},\"545\":{}}','','',0,0,'viewable','2013-01-09 00:26:57',1),(31,'Image tiling schema','Please document the nature of this detail type (field)) ...','enum','Image tiling schema','Used to enable proper display of tilled image sets such as historic maps and other large images. There are various schema available all of which require different handling in map and image viewer displays.','','approved',2,'Map image layer tiling schema',31,106,0,'{\"547\":{},\"548\":{},\"549\":{},\"550\":{}}','','',0,0,'viewable','2013-01-09 00:26:57',1),(32,'Minimum zoom level','Please document the nature of this detail type (field)) ...','integer','Minimum zoom level','Used in conjunction with tiled images and maps. A correctly set detail of this type will prevent users from zooming past where map data is available','','approved',2,'Minimum zoom level',32,106,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(33,'Maximum zoom level','Please document the nature of this detail type (field)) ...','integer','Maximum zoom level','Used in conjunction with tiled images and maps. A correctly set detail of this type will prevent users from zooming past where map data is available','','approved',2,'Maximum zoom level',33,106,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(34,'Service URL','Please document the nature of this detail type (field)) ...','freetext','Service URL','Used to identify an external service','','approved',2,'Service URL',34,109,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(35,'Copyright information','Please document the nature of this detail type (field)) ...','blocktext','Copyright statement or a URI leading to a copyright statement. Consider using Creative Commons categories.','Can be used with Creative Commons license types','','approved',2,'Copyright information',35,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(36,'Original ID','Please document the nature of this detail type (field)) ...','freetext','The original ID of the record in a source database from which these data were imported','Used to retain a reference to an original data set where a given record is imported from. This detail type is essential when imports complex relational data into Heurist','','reserved',2,'Original ID',36,99,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(37,'Unique public identifier','Please document the nature of this detail type (field)) ...','freetext','A public identifier such as the NLA party identifier (Australia) or other national/international system','','','approved',2,'Unique public identifier',37,8,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(38,'Related file','Please document the nature of this detail type (field)) ...','file','An uploaded file or a reference to a file through a URI','A single detail of this type can be used to reference a file uploaded into Heurist or available via URL. Note that currently the URL must be publicly accessible','','reserved',2,'File resource',38,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(39,'Thumbnail image','Please document the nature of this detail type (field)) ...','file','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','','','reserved',2,'Thumbnail image',39,1,0,'','','',0,1,'viewable','2013-01-09 00:26:57',1),(40,'Heurist Filter String','Heurist Filter String','freetext','A string representing a valid Heurist filter (rtfilter={\"level\":[\"rtID1\",\"rtID2\"]}','Allows the saving of rules that work on a result set. Rather than just saying \"give me all the related records of record x\" one could say \"give me all the related people of record x\" and save this in a detail for reuse.','','reserved',2,'Filter string',40,102,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(41,'File Type','Please document the nature of this detail type (field)) ...','enum','Term identifying the file format','','','approved',2,'File Type',41,105,0,'{\"3273\":{\"3276\":{},\"3277\":{},\"3278\":{},\"3279\":{}},\"3274\":{\"3280\":{},\"3281\":{}},\"3275\":{\"3282\":{},\"3283\":{},\"3284\":{}}}','[]','',0,1,'viewable','2013-01-09 00:26:57',1),(42,'Annotated Resource','Please document the nature of this detail type (field)) ...','resource','Pointer to the resource being annotated','Detail used with annotation records to describe that which is being annotated. In most cases this detail will be combined with specific location information specifying a part of the resource - e.g. text element and word offset','','approved',2,'Annotated Resource',42,107,0,'','','11,5,2,13',0,0,'viewable','2013-01-09 00:26:57',1),(43,'Annotation Range','Please document the nature of this detail type (field)) ...','freetext','An encoded string defining the area of the annotated document being annotated','Used with the Annotated resource detail to establish the location within a resource that is the source of the annotation','','approved',2,'Annotated Range',43,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(44,'Start Word','Please document the nature of this detail type (field)) ...','integer','Start Word','Please provide an extended description for display on rollover ...','','approved',2,'Start Word',44,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(45,'End Word','Please document the nature of this detail type (field)) ...','integer','End Word','Used to establish the end of an annotation - usually within the context of an end element detail','','approved',2,'End Word',45,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(46,'Start Element','Please document the nature of this detail type (field)) ...','freetext','Start Element','Used in text annotation to determine the beginning element. Should be used in conjunction with an annotated resource pointer and may be accompanied by a start word detail','','approved',2,'Start Element',46,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(47,'End Element','Please document the nature of this detail type (field)) ...','freetext','End Element','Used in text annotations to describe the text element where an annotation starts. Often used in combination with a word offset','','approved',2,'End Element',47,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(48,'Heurist Layout String','Please document the nature of this detail type (field)) ...','freetext','A formatted string that can be interpretted by the Heurist Interface as specific layout.','Used to pre-configure the Heurist environment. Usually accompanying a query and/or a filter. The combination of these details enables the full current Heurist environment to be stored and/or sent to other users','','reserved',2,'Heurist Layout String',48,102,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(49,'Version Number','Please document the nature of this detail type (field)) ...','freetext','Numeric string representing a version, typically a squence of numbers separated by full stop.','','','approved',2,'Version Number',49,109,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(50,'Transform Pointer','Please document the nature of this detail type (field)) ...','resource','Pointer to a transform or pipeline transform record','','','approved',2,'Transform Pointer',50,109,0,'','','14,17',0,0,'viewable','2013-01-09 00:26:57',1),(51,'Property (N:V)','This is a generic property field for extending a records properties by adding a name:value pair. Note the colon used as a separator.','freetext','Attribute expressed as a name:value pair','A highly generic detail utility','','approved',2,'Property (N:V)',51,109,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(52,'Tool Type','Please document the nature of this detail type (field)) ...','enum','Please provide a short explanation for the user ...','Please provide an extended description for display on rollover ...','','approved',2,'Tool Type',52,107,0,'{\"3244\":{\"3245\":{\"3246\":{\"3247\":{}},\"3248\":{\"3249\":{},\"3250\":{},\"3251\":{}},\"3252\":{\"3253\":{}},\"3254\":{\"3255\":{},\"3256\":{},\"3257\":{}}}}}','[\"3244\"]','',0,0,'viewable','2013-01-09 00:26:57',1),(53,'Record Type','Please document the nature of this detail type (field)) ...','freetext','Record Type Concept-ID','Enter the origin dbID-recTypeID hyphenated id pair.','','approved',2,'Record Type',53,109,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(54,'Detail Type','Please document the nature of this detail type (field)) ...','freetext','Detail Type Concept-ID','Enter the origin dbID-detailTypeID hyphenated id pair.','','approved',2,'Detail Type',54,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(55,'Application Command','Please document the nature of this detail type (field)) ...','freetext','Application Command','Enter the text for the command to be executed when the tool is invoked in the application','','approved',2,'Application Command',55,107,0,'','','',0,0,'viewable','2013-01-09 00:26:57',1),(56,'Colour','Please document the nature of this detail type (field)) ...','enum','Colour','','','approved',2,'Colour',56,107,0,'{\"551\":{\"552\":{},\"553\":{},\"554\":{},\"555\":{},\"556\":{},\"557\":{},\"558\":{},\"559\":{},\"560\":{},\"561\":{},\"562\":{},\"563\":{},\"564\":{},\"565\":{},\"566\":{},\"567\":{},\"568\":{},\"569\":{},\"570\":{},\"571\":{},\"572\":{},\"573\":{},\"574\":{},\"575\":{},\"576\":{},\"578\":{},\"577\":{},\"579\":{},\"580\":{},\"581\":{},\"582\":{},\"583\":{},\"584\":{},\"585\":{},\"586\":{},\"587\":{},\"588\":{},\"589\":{},\"590\":{},\"591\":{},\"592\":{},\"593\":{},\"594\":{},\"595\":{},\"596\":{},\"597\":{},\"598\":{},\"599\":{},\"600\":{},\"601\":{},\"602\":{},\"603\":{},\"604\":{},\"605\":{},\"607\":{},\"608\":{},\"606\":{},\"609\":{},\"610\":{},\"611\":{},\"612\":{},\"613\":{},\"614\":{},\"615\":{},\"616\":{},\"617\":{},\"618\":{},\"619\":{},\"620\":{},\"621\":{},\"622\":{},\"623\":{},\"625\":{},\"624\":{},\"626\":{},\"627\":{},\"628\":{},\"629\":{},\"630\":{},\"631\":{},\"632\":{},\"633\":{},\"634\":{},\"635\":{},\"636\":{},\"637\":{},\"638\":{},\"639\":{},\"640\":{},\"641\":{},\"642\":{},\"643\":{},\"644\":{},\"645\":{},\"646\":{},\"647\":{},\"648\":{},\"649\":{},\"650\":{},\"651\":{},\"652\":{},\"653\":{},\"654\":{},\"655\":{},\"656\":{},\"657\":{},\"658\":{},\"659\":{},\"660\":{},\"661\":{},\"662\":{},\"663\":{},\"664\":{},\"665\":{},\"666\":{},\"667\":{},\"668\":{},\"669\":{},\"670\":{},\"671\":{},\"672\":{},\"673\":{},\"674\":{},\"675\":{},\"676\":{},\"677\":{},\"678\":{},\"679\":{},\"680\":{},\"681\":{},\"682\":{},\"683\":{},\"684\":{},\"685\":{},\"686\":{},\"687\":{},\"688\":{},\"689\":{},\"690\":{},\"691\":{},\"692\":{},\"693\":{},\"694\":{},\"695\":{},\"696\":{},\"697\":{},\"698\":{}}}','[\"551\"]','',0,0,'viewable','2013-01-09 00:26:57',1),(57,'Header 1','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','','','open',0,'',0,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',0),(58,'Header 2','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','','','open',0,'',0,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',0),(61,'Multimedia >','Please document the nature of this detail type (field)) ...','resource','Points to a multimedia record - use this in preference to a file field if you want to record additional metadata about the media file or use it in multiple records ','','','reserved',2,'',61,1,0,'','','5',0,1,'viewable','2013-01-18 01:03:23',0),(62,'File name','Please document the nature of this detail type (field)) ...','freetext','The name of the file, excluding path, including extension','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(63,'File path','Please document the nature of this detail type (field)) ...','freetext','The full path to the file','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(64,'File extension','Please document the nature of this detail type (field)) ...','freetext','The (normally three letter) file extension','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(65,'File recording device','Please document the nature of this detail type (field)) ...','freetext','The device used to collect files, may be generic \'image\' or more specific eg. camera make and model','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(66,'File duration secs','Please document the nature of this detail type (field)) ...','float','The duration of a media object, such as sound or video, in seconds','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(67,'File size bytes','Please document the nature of this detail type (field)) ...','float','The size of the file in bytes','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(68,'MD5 checksum','Please document the nature of this detail type (field)) ...','freetext','The calculated MD5 checksum for the file','','','open',0,'',0,105,0,'','','',0,1,'viewable','2013-01-09 00:26:57',0),(69,'Header 3','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','','','open',0,'',0,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',0),(70,'Header 4','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','','','open',0,'',0,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',0),(71,'Header 5','Please document the nature of this detail type (field)) ...','separator','Please rename to an appropriate heading within each record structure','','','open',0,'',0,99,0,'','','',0,0,'viewable','2013-01-09 00:26:57',0),(72,'KML as text','','blocktext','A block of KML text which can be rendered in maps ','','','pending',3,'KML',1036,110,0,'','','',0,1,'viewable','2013-01-09 04:52:41',0),(73,'Default colour','','enum','Default symbology colour for rendering this map layer','','','pending',3,'default symbology colour',1037,110,0,'{\"561\":{},\"607\":{},\"671\":{}}','','',0,1,'viewable','2013-01-09 04:52:41',0),(74,'Contributor type','Please document the nature of this detail type (field)) ...','enum','Contributor type','',NULL,'open',NULL,NULL,NULL,111,0,'3285','','',NULL,1,'viewable','2013-01-10 02:42:57',0),(75,'Entity type','Please document the nature of this detail type (field)) ...','enum','Entity type','',NULL,'open',NULL,NULL,NULL,111,0,'3290','','',NULL,1,'viewable','2013-01-10 02:53:12',0),(76,'Publisher','Please document the nature of this detail type (field)) ...','freetext','Publisher','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 01:28:26',0),(77,'Version','Please document the nature of this detail type (field)) ...','freetext','Version','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 08:44:50',0),(78,'Language','Please document the nature of this detail type (field)) ...','freetext','Language','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 08:52:09',0),(79,'Tracking system ID','Please document the nature of this detail type (field)) ...','freetext','Tracking system ID','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 08:56:55',0),(80,'Source','Please document the nature of this detail type (field)) ...','freetext','Source','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 08:59:09',0),(81,'WordML file','Please document the nature of this detail type (field)) ...','file','WordML file','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 09:01:05',0),(82,'Item Source URL','Please document the nature of this detail type (field)) ...','freetext','Source URL','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 09:06:41',0),(83,'Creator','Please document the nature of this detail type (field)) ...','freetext','Creator','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 09:10:27',0),(84,'Contributor Item ID','Please document the nature of this detail type (field)) ...','freetext','Contributor own id for media item','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 09:11:22',0),(85,'Factoid type','Please document the nature of this detail type (field)) ...','enum','Factoid type','',NULL,'open',NULL,NULL,NULL,111,0,'3306','','',NULL,1,'viewable','2013-01-11 09:19:41',0),(86,'Entity target','Please document the nature of this detail type (field)) ...','resource','Entity target','',NULL,'open',NULL,NULL,NULL,111,0,'','','25',NULL,1,'viewable','2013-01-11 09:21:15',0),(87,'Entity source','Please document the nature of this detail type (field)) ...','resource','Entity source','',NULL,'open',NULL,NULL,NULL,111,0,'','','25',NULL,1,'viewable','2013-01-11 09:21:34',0),(88,'Factoid Role','Please document the nature of this detail type (field)) ...','resource','Factoid Role','',NULL,'open',NULL,NULL,NULL,111,0,'','','27',NULL,1,'viewable','2013-01-11 09:24:17',0),(89,'Status','Please document the nature of this detail type (field)) ...','enum','Internal status','',NULL,'open',NULL,NULL,NULL,111,0,'3315','','',NULL,1,'viewable','2013-01-11 09:27:57',0),(90,'Contributor pointer','Please document the nature of this detail type (field)) ...','resource','Contributor pointer','',NULL,'open',NULL,NULL,NULL,111,0,'','','24',NULL,1,'viewable','2013-01-11 09:28:58',0),(91,'KML pointer','Please document the nature of this detail type (field)) ...','resource','KML pointer','',NULL,'open',NULL,NULL,NULL,111,0,'','','23',NULL,1,'viewable','2013-01-11 09:31:23',0),(92,'Attribution','Please document the nature of this detail type (field)) ...','freetext','Attribution','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 09:43:40',0),(93,'Map Image pointer','Please document the nature of this detail type (field)) ...','resource','Map Image pointer','',NULL,'open',NULL,NULL,NULL,111,0,'','','11',NULL,1,'viewable','2013-01-11 09:44:36',0),(94,'Rights','Please document the nature of this detail type (field)) ...','enum','Rights','',NULL,'open',NULL,NULL,NULL,111,0,'3318','','',NULL,1,'viewable','2013-01-11 09:46:38',0),(95,'Role type','Please document the nature of this detail type (field)) ...','enum','Role type','',NULL,'open',NULL,NULL,NULL,111,0,'3321','','',NULL,1,'viewable','2013-01-11 09:49:28',0),(96,'Annotation type','Please document the nature of this detail type (field)) ...','enum','Annotation type','',NULL,'open',NULL,NULL,NULL,111,0,'3328','','',NULL,1,'viewable','2013-01-11 09:51:23',0),(97,'Factoid target','Please document the nature of this detail type (field)) ...','freetext','Factoid target','',NULL,'open',NULL,NULL,NULL,111,0,'','','',NULL,1,'viewable','2013-01-11 20:27:08',0),(98,'TEI Document','Please document the nature of this detail type (field)) ...','resource','TEI Document Reference','',NULL,'open',NULL,NULL,NULL,111,0,'','','13',NULL,1,'viewable','2013-01-11 21:27:30',0);
/*!40000 ALTER TABLE `defDetailTypes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `woots`
--

DROP TABLE IF EXISTS `woots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `woots`
--

LOCK TABLES `woots` WRITE;
/*!40000 ALTER TABLE `woots` DISABLE KEYS */;
/*!40000 ALTER TABLE `woots` ENABLE KEYS */;
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
  `lng_Notes` varchar(1000) default NULL COMMENT 'URL reference to, or notes on the definition of the language',
  `lng_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`lng_NISOZ3953`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Language list including optional standard language codes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defLanguages`
--

LOCK TABLES `defLanguages` WRITE;
/*!40000 ALTER TABLE `defLanguages` DISABLE KEYS */;
INSERT INTO `defLanguages` VALUES ('ARA','AR','Arabic',NULL,'2013-02-19 22:00:58'),('CAM','KM','Khmer',NULL,'2013-02-19 22:00:58'),('CHI','ZH','Chinese',NULL,'2013-02-19 22:00:58'),('CZE','CS','Czech',NULL,'2013-02-19 22:00:58'),('DAN','DA','Danish',NULL,'2013-02-19 22:00:58'),('DUT','NL','Dutch',NULL,'2013-02-19 22:00:58'),('ENG','EN','English',NULL,'2013-02-19 22:00:58'),('EST','ET','Estonian',NULL,'2013-02-19 22:00:58'),('FIN','FI','Finish',NULL,'2013-02-19 22:00:58'),('FRE','FR','French',NULL,'2013-02-19 22:00:58'),('GER','DE','German',NULL,'2013-02-19 22:00:58'),('GRE','EL','Greek',NULL,'2013-02-19 22:00:58'),('HEB','HE','Hebrew',NULL,'2013-02-19 22:00:58'),('HIN','HI','Hindi',NULL,'2013-02-19 22:00:58'),('HUN','HU','Hungarian',NULL,'2013-02-19 22:00:58'),('IND','ID','Indonesian',NULL,'2013-02-19 22:00:58'),('ITA','IT','Italian',NULL,'2013-02-19 22:00:58'),('JPN','JA','Japanese',NULL,'2013-02-19 22:00:58'),('KOR','KO','Korean',NULL,'2013-02-19 22:00:58'),('LAV','LV','Latvian',NULL,'2013-02-19 22:00:58'),('MAL','MS','Malay',NULL,'2013-02-19 22:00:58'),('NOR','NO','Norwegian',NULL,'2013-02-19 22:00:58'),('POL','PL','Polish',NULL,'2013-02-19 22:00:58'),('POR','PT','Portuguese',NULL,'2013-02-19 22:00:58'),('RUS','RU','Russian',NULL,'2013-02-19 22:00:58'),('SCC','HR','Croatian',NULL,'2013-02-19 22:00:58'),('SCR','SR','Serbian',NULL,'2013-02-19 22:00:58'),('SLO','SK','Slovak',NULL,'2013-02-19 22:00:58'),('SPA','ES','Spanish',NULL,'2013-02-19 22:00:58'),('SWA','SW','Swahili',NULL,'2013-02-19 22:00:58'),('SWE','SV','Swedish',NULL,'2013-02-19 22:00:58'),('THA','TH','Thai',NULL,'2013-02-19 22:00:58'),('TUR','TR','Turkish',NULL,'2013-02-19 22:00:58'),('UKR','UK','Ukranian',NULL,'2013-02-19 22:00:58'),('VIE','VI','Vietnamese',NULL,'2013-02-19 22:00:58'),('YID','YI','Yiddish',NULL,'2013-02-19 22:00:58');
/*!40000 ALTER TABLE `defLanguages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrRecTagLinks`
--

DROP TABLE IF EXISTS `usrRecTagLinks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrRecTagLinks`
--

LOCK TABLES `usrRecTagLinks` WRITE;
/*!40000 ALTER TABLE `usrRecTagLinks` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrRecTagLinks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrBookmarks`
--

DROP TABLE IF EXISTS `usrBookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrBookmarks`
--

LOCK TABLES `usrBookmarks` WRITE;
/*!40000 ALTER TABLE `usrBookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrBookmarks` ENABLE KEYS */;
UNLOCK TABLES;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `usrBookmarks_update` BEFORE UPDATE ON `usrBookmarks` FOR EACH ROW set NEW.bkm_Modified = now() */;;
DELIMITER ;

--
-- Table structure for table `usrReminders`
--

DROP TABLE IF EXISTS `usrReminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrReminders`
--

LOCK TABLES `usrReminders` WRITE;
/*!40000 ALTER TABLE `usrReminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrReminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sysIdentification`
--

DROP TABLE IF EXISTS `sysIdentification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `sys_NewRecOwnerGrpID` smallint(5) unsigned NOT NULL default '0' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  `sys_NewRecAccess` enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  `sys_SetPublicToPendingOnEdit` tinyint(1) unsigned NOT NULL default '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''',
  `sys_ConstraintDefaultBehavior` enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL default 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  `sys_AllowRegistration` tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, people can apply for registration through web-based form',
  `sys_MediaFolders` varchar(10000) default NULL COMMENT 'Additional comma-sep directories which can contain files indexed in database',
  `sys_MediaExtensions` varchar(1024) default 'jpg,png,gif,tif,tiff,wmv,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff' COMMENT 'The file extensions to be harvested from the MediaFolders directories',
  PRIMARY KEY  (`sys_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Identification/version for this Heurist database (single rec';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysIdentification`
--

LOCK TABLES `sysIdentification` WRITE;
/*!40000 ALTER TABLE `sysIdentification` DISABLE KEYS */;
INSERT INTO `sysIdentification` VALUES (1,0,1,1,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'Please enter a DB name ...',NULL,'Please define ownership and rights here ...',NULL,NULL,'0',0,0,NULL,'/var/www/htdocs/HEURIST_FILESTORE/dos_0/hml-output','/var/www/htdocs/HEURIST_FILESTORE/dos_0/html-output',0,'viewable',0,'locktypetotype',0,NULL,'jpg,png,gif,tif,tiff,wmv,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff');
/*!40000 ALTER TABLE `sysIdentification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recDetails`
--

DROP TABLE IF EXISTS `recDetails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recDetails`
--

LOCK TABLES `recDetails` WRITE;
/*!40000 ALTER TABLE `recDetails` DISABLE KEYS */;
/*!40000 ALTER TABLE `recDetails` ENABLE KEYS */;
UNLOCK TABLES;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `insert_Details_precis_trigger` BEFORE INSERT ON `recDetails` FOR EACH ROW begin set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), ''); end */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
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
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `pre_update_Details_trigger` BEFORE UPDATE ON `recDetails` FOR EACH ROW begin
		if asbinary(NEW.dtl_Geo)=asbinary(OLD.dtl_Geo) then
			set NEW.dtl_Geo = OLD.dtl_Geo;
		end if;
		set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), '');
	end */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
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

--
-- Table structure for table `defRecTypes`
--

DROP TABLE IF EXISTS `defRecTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8 COMMENT='Defines record types, which corresponds with a set of detail';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRecTypes`
--

LOCK TABLES `defRecTypes` WRITE;
/*!40000 ALTER TABLE `defRecTypes` DISABLE KEYS */;
INSERT INTO `defRecTypes` VALUES (1,'Record relationship',30,'A relationship of a defined type between two records in the database, may include a time range over which the relationship is valid, and notes about the relationship','[Name of relationship] ([Source record.RecTitle] [Relationship Type] [Target record.RecTitle])','[1] ([7.RecTitle] [6] [5.RecTitle])','Record relationships','reserved',2,'Record relationship',1,'viewable',0,4,'',1,'','','relationship',1,1,'2013-01-09 00:26:57',1),(2,'Web site / page',1,'A web site URL, typically a specific page (often the home page)','[Page title]','[1]','Web site / pages','reserved',2,'Web site / page',2,'viewable',1,11,'',0,'','','normal',1,1,'2013-01-11 20:02:46',1),(3,'Notes',2,'A simple record type for taking notes','[Title]','[1]','Notes','approved',2,'Notes',3,'viewable',1,1,'',0,'','','normal',0,1,'2013-01-09 00:26:57',1),(4,'Organisation',0,'Organisations (companies, universities, granting bodies etc.)','[Name of Organisation]','[1]','Organisations','approved',2,'Organisation',4,'viewable',1,1,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(5,'Digital media item',0,'Digital media files - typically image, sound, video - uploaded to the database or external reference','[Title]','[1]','Digital media items','reserved',2,'Digital media file',5,'viewable',1,11,'',0,'','','normal',1,1,'2013-01-11 20:55:30',1),(6,'Aggregation',0,'A record which describes a static or dynamic collection of records and their filtering and layout, or acts as a root to which other records point, or both','[Name]','[1]','Aggregations','reserved',2,'Aggregation',6,'viewable',1,5,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(7,'Blog post',0,'Blog post used to construct blogs, the text of the post is stored in the WYSIWYG text field','[Title of post]','[1]','Blog posts','reserved',2,'Blog post',7,'viewable',1,1,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(8,'Interpretive annotation',0,'Metadata about a date, spatial extent or other interpretation of information','[Title of interpretation] - by [Author(s).recTitle] [Date] transcribed by [Transcriber(s).recTitle]','[1] - by [15.recTitle] [9] transcribed by [14.recTitle]','Interpretive annotations','approved',2,'Interpretation',8,'viewable',1,5,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(9,'Email',0,'An email including content and metadata, often derived from an email forwarded or ccd to the database\'s linked IMAP server from a user\'s account','[Subject] from: [Email of sender] on: [Date originally sent]','[1] from: [24] on: [9]','Emails','reserved',2,'Email',9,'viewable',0,4,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(10,'Person',33,'A standard record for a person, may be expanded with additional information as required.','[Family name], [Given names] ([Birth Date])','[1], [18] ([10])','Persons','reserved',2,'Person',10,'viewable',1,1,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(11,'Tiled Image',0,'A tiled image set for display as a zoomable layer on e.g. a Google map','[Name of tiled image]','[1]','Tiled Images','approved',2,'Map image layer',11,'viewable',1,11,'',0,'','','normal',1,1,'2013-01-11 20:37:38',1),(12,'Heurist Filter',0,'A record which represents a filtering of query results and their related records.','[Name]','[1]','Heurist Filters','approved',2,'Filter',12,'viewable',0,9,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(13,'XML Document',0,'Any XML format document.','[Title of document]','[1]','XML Documents','approved',2,'XML Document',13,'viewable',1,11,'',0,'','','normal',1,1,'2013-01-11 20:37:41',1),(14,'Transform',0,'Formatted text used by a processor to transform input information','[Name]','[1]','Transforms','approved',2,'Transformation',14,'viewable',1,7,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(15,'Annotation',0,'A reference to part of a document','[Name of annotation] ([Annotated Resource.RecTitle]) ref:([Annotation Range])','[1] ([42.1]) ref:([43])','Annotations','approved',2,'Annotation',15,'viewable',1,7,'',0,'','','normal',1,1,'2013-01-17 08:57:29',1),(16,'Heurist Layout',0,'Record which contains information which captures or resets the layout of the Heurist interface.','[Layout Name]','[1]','Heurist Layouts','approved',2,'Heurist Layout',16,'viewable',0,9,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(17,'Pipeline Transform',0,'A record which is an ordered list of transformation records.','[Name of Pipeline]','[1]','Pipeline Transforms','approved',2,'Pipeline Transform',17,'viewable',1,7,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(18,'AbstractObject',0,'For advanced technical use only: A generic entity which can be configured with arbitrary properties and named','[Name]','[1]','AbstractObjects','approved',2,'Object',18,'viewable',0,9,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(19,'Tool',0,'This is a configuration type record type that describes a particular annotation tool with its icon, name, term and colour.','[Name]','[1]','Tools','approved',2,'Tool',19,'viewable',1,7,'',0,'','','normal',1,1,'2013-01-09 00:26:57',1),(21,'DEPRECATED Aggregation',0,'NOTE: THIS IS A DEPRECATED VERSION. IT HAS BEEN MERGED WITH AGGREGATION (concept code 2-6) IN H3CoreDefinitions 17/10/12','[Name]','[1]','DEPRECATED Aggregation','open',2,'Record Collection',21,'viewable',0,9,'',0,'','','normal',0,1,'2013-01-09 00:26:57',1),(22,'Extra fields container',0,'This is a dummy record type for AeR internal use to move groups of fields to new databases with Import Structure','[Name]','','Extra fields containers','open',0,'',0,'viewable',0,4,'',0,'','','normal',0,1,'2013-01-09 00:26:57',0),(23,'KML',0,'KML file object for use in pointer relations with MAP objects','[Title of KML]','[1]','KMLs','pending',3,'KML',1014,'viewable',1,11,'',0,'','','normal',1,1,'2013-01-11 20:30:34',1),(24,'Contributor',0,'Contributor','[Name / Title]','[1]','Contributors','open',NULL,NULL,NULL,'viewable',1,11,NULL,0,'','','normal',0,1,'2013-01-10 02:42:00',1),(25,'Entity',0,'Entity','[Name / Title]','[1]','Entitys','open',NULL,NULL,NULL,'viewable',1,11,NULL,0,'','','normal',0,1,'2013-01-10 02:45:37',1),(26,'Factoid',0,'Factoid','[Name / Title]','[1]','Factoids','open',NULL,NULL,NULL,'viewable',1,11,NULL,0,'','','normal',0,1,'2013-01-11 20:23:01',1),(27,'Role',0,'Role','[Name / Title]','[1]','Roles','open',NULL,NULL,NULL,'viewable',1,11,NULL,0,'','','normal',0,1,'2013-01-11 21:05:52',1),(28,'Map',0,'Map','[Name / Title]','[1]','Maps','open',NULL,NULL,NULL,'viewable',1,11,NULL,0,'','','normal',0,1,'2013-01-11 20:34:40',1),(29,'Term',0,'Term','record [ID]','','Terms','open',NULL,NULL,NULL,'viewable',1,11,NULL,0,'','','normal',0,1,'2013-01-11 21:40:13',0);
/*!40000 ALTER TABLE `defRecTypes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recForwarding`
--

DROP TABLE IF EXISTS `recForwarding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recForwarding` (
  `rfw_OldRecID` int(10) unsigned NOT NULL COMMENT 'The deleted record which will be redirected to another',
  `rfw_NewRecID` int(10) unsigned NOT NULL COMMENT 'The new record to which this ID will be forwarded',
  PRIMARY KEY  (`rfw_OldRecID`),
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
-- Table structure for table `usrRecentRecords`
--

DROP TABLE IF EXISTS `usrRecentRecords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrRecentRecords` (
  `rre_UGrpID` smallint(5) unsigned default NULL COMMENT 'ID of user who used the record',
  `rre_RecID` int(10) unsigned NOT NULL COMMENT 'ID of recently used record',
  `rre_Time` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Timestamp of use of records, notably those searched for with pointer field',
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
/*!40000 ALTER TABLE `usrRecentRecords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defRecTypeGroups`
--

DROP TABLE IF EXISTS `defRecTypeGroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defRecTypeGroups` (
  `rtg_ID` tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Record type group ID referenced in defRectypes',
  `rtg_Name` varchar(40) NOT NULL COMMENT 'Name for this group of record types, shown as heading in lists',
  `rtg_Domain` enum('functionalgroup','modelview') NOT NULL default 'functionalgroup' COMMENT 'Functional group (rectype has only one) or a Model/View group',
  `rtg_Order` tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists',
  `rtg_Description` varchar(250) default NULL COMMENT 'A description of the record type group and its purpose',
  `rtg_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`rtg_ID`),
  UNIQUE KEY `rtg_Name` (`rtg_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COMMENT='Grouping mechanism for record types in pulldowns';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defRecTypeGroups`
--

LOCK TABLES `defRecTypeGroups` WRITE;
/*!40000 ALTER TABLE `defRecTypeGroups` DISABLE KEYS */;
INSERT INTO `defRecTypeGroups` VALUES (1,'Basic record types','functionalgroup',002,'Common generic record types which will be useful in many databases','2013-01-10 02:30:54'),(4,'System Internals','functionalgroup',007,'Record types used by the Heurist system for specific workflows and functions','2013-01-10 02:30:54'),(5,'More specialised types','functionalgroup',006,'More specialised record types which will be useful for some more advanced research applications','2013-01-10 02:30:54'),(6,'User defined record types','functionalgroup',001,'A group (tab) for defining your own record types - you can create additional groups, rename this one, and move record types between groups','2013-01-10 02:30:54'),(7,'Transform and Annotate','functionalgroup',004,'Record types required for the annotation functions','2013-01-10 02:30:54'),(8,'Additional data types','functionalgroup',005,'Record types used for special functions such as mapping and annotation','2013-01-10 02:30:54'),(9,'System internals2','functionalgroup',008,'Record types used by the Heurist system for specific workflows and functions','2013-01-10 02:30:55'),(10,'Spatial, temporal','functionalgroup',003,'Entities used for spacial and temporal aspects of a domain','2013-01-10 02:30:54'),(11,'DOS','functionalgroup',000,'DOS specific types','2013-01-10 02:30:54');
/*!40000 ALTER TABLE `defRecTypeGroups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrReportSchedule`
--

DROP TABLE IF EXISTS `usrReportSchedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrReportSchedule` (
  `rps_ID` smallint(5) unsigned NOT NULL auto_increment COMMENT 'Primary ID of a report output update specification',
  `rps_Type` enum('smarty') collate utf8_unicode_ci NOT NULL default 'smarty' COMMENT 'The type of report being generated',
  `rps_Title` varchar(64) collate utf8_unicode_ci NOT NULL COMMENT 'A title for this specification for use in selection menus',
  `rps_FilePath` varchar(128) collate utf8_unicode_ci default NULL COMMENT 'The full file path to whic hthe report is to be generated, filestore/dbname/generated-reports if blank',
  `rps_URL` varchar(128) collate utf8_unicode_ci default NULL COMMENT 'The corresponding URL for web access to the directory in which this report is published, to , filestore/dbname/generated-reports if blank',
  `rps_FileName` varchar(64) collate utf8_unicode_ci NOT NULL COMMENT 'The base name of the report being published - wil lbe compelted with file types',
  `rps_HQuery` text collate utf8_unicode_ci NOT NULL COMMENT 'The Heurist query to be used in generating this report',
  `rps_Template` varchar(64) collate utf8_unicode_ci NOT NULL COMMENT 'The name of the template file to be used in generating this report',
  `rps_IntervalMinutes` tinyint(4) default NULL COMMENT 'The interval in minutes between regenerations of the report output, null = never',
  `rps_Added` timestamp NULL default NULL COMMENT 'The date when this specification was added',
  `rps_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date this specification was last updated',
  PRIMARY KEY  (`rps_ID`),
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
-- Table structure for table `sysUsrGrpLinks`
--

DROP TABLE IF EXISTS `sysUsrGrpLinks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sysUsrGrpLinks`
--

LOCK TABLES `sysUsrGrpLinks` WRITE;
/*!40000 ALTER TABLE `sysUsrGrpLinks` DISABLE KEYS */;
INSERT INTO `sysUsrGrpLinks` VALUES (1,2,1,'admin'),(2,2,3,'admin'),(3,4,1,'admin'),(4,1000,1,'admin');
/*!40000 ALTER TABLE `sysUsrGrpLinks` ENABLE KEYS */;
UNLOCK TABLES;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUsrGrpLinks_last_insert` AFTER INSERT ON `sysUsrGrpLinks` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks" */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `sysUsrGrpLinks_last_update` AFTER UPDATE ON `sysUsrGrpLinks` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks" */;;
DELIMITER ;

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
  PRIMARY KEY  (`rrc_RecID`),
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
-- Table structure for table `defRelationshipConstraints`
--

DROP TABLE IF EXISTS `defRelationshipConstraints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `rcs_SourceRecTypeID` (`rcs_SourceRectypeID`),
  KEY `fk_rcs_SourceRecTypeID` (`rcs_SourceRectypeID`),
  KEY `fk_rcs_TargetRecTypeID` (`rcs_TargetRectypeID`),
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
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRelationshipConstraints_last_insert` AFTER INSERT ON `defRelationshipConstraints` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRelationshipConstraints_last_update` AFTER UPDATE ON `defRelationshipConstraints` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;
DELIMITER ;
--
-- WARNING: old server version. The following dump may be incomplete.
--
DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRelationshipConstraints_last_delete` AFTER DELETE ON `defRelationshipConstraints` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints" */;;
DELIMITER ;

--
-- Table structure for table `sysDBNameCache`
--

DROP TABLE IF EXISTS `sysDBNameCache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysDBNameCache` (
  `dnc_ID` int(10) unsigned NOT NULL COMMENT 'Heurist DB ID for a registered Heurist database',
  `dnc_dbName` varchar(63) NOT NULL COMMENT 'Name of the database (from sys_DBName or Heurist index database)',
  `dnc_TimeStamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date when name of database last read from Heurist index or the database',
  `dnc_URL` varchar(128) default NULL COMMENT 'Root path to this installation and database',
  PRIMARY KEY  (`dnc_ID`)
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
-- Table structure for table `sysLocks`
--

DROP TABLE IF EXISTS `sysLocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysLocks` (
  `lck_Action` enum('buildcrosswalks','editdefinitions','errorscan','buildtempdb') NOT NULL COMMENT 'Type of action being carried out',
  `lck_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'Set to user ID to lock specified function',
  `lck_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Date and time when the action was commenced, use to drop old locks',
  PRIMARY KEY  (`lck_Action`)
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
-- Table structure for table `sysDocumentation`
--

DROP TABLE IF EXISTS `sysDocumentation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sysDocumentation` (
  `doc_id` tinyint(3) unsigned NOT NULL auto_increment,
  `doc_text` text COMMENT 'Relevant documentation as text',
  PRIMARY KEY  (`doc_id`)
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
-- Table structure for table `defTerms`
--

DROP TABLE IF EXISTS `defTerms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3348 DEFAULT CHARSET=utf8 COMMENT='Terms by detail type and the vocabulary they belong to';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defTerms`
--

LOCK TABLES `defTerms` WRITE;
/*!40000 ALTER TABLE `defTerms` DISABLE KEYS */;
INSERT INTO `defTerms` VALUES (11,'University',0,'','open',2,'University',11,0,0,'enum',0,2,497,1,'2013-01-09 00:26:57',0,NULL),(12,'Funding body',0,'','open',2,'Funding body',12,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(13,'Network',0,'','open',2,'Network',13,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(14,'Data service',0,'','open',2,'Data service',14,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(15,'Department',0,'','open',2,'Department',15,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(16,'Laboratory',0,'','open',2,'Laboratory',16,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(17,'Centre',0,'','open',2,'Centre',17,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(18,'Foundation',0,'','open',2,'Foundation',18,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(19,'Ecole Superieure',0,'','open',2,'Ecole Superieure',19,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(20,'Institute',0,'','open',2,'Institute',20,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(21,'Museum',0,'','open',2,'Museum',21,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(93,'Mr',0,'','open',2,'Mr',93,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(94,'Mrs',0,'','open',2,'Mrs',94,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(95,'Ms',0,'','open',2,'Ms',95,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(96,'Dr',0,'','open',2,'Dr',96,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(97,'A/Prof.',0,'','open',2,'A/Prof.',97,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(98,'Prof.',0,'','open',2,'Prof.',98,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(104,'BCE',0,'','open',2,'BCE',104,0,0,'enum',0,0,508,1,'2013-01-09 00:26:57',0,NULL),(105,'CE',0,'','open',2,'CE',105,0,0,'enum',0,0,508,1,'2013-01-09 00:26:57',0,NULL),(106,'BP',0,'','open',2,'BP',106,0,0,'enum',0,0,508,1,'2013-01-09 00:26:57',0,NULL),(107,'Unknown',0,'','open',2,'Unknown',107,0,0,'enum',0,0,508,1,'2013-01-09 00:26:57',0,NULL),(108,'Australia',0,'','open',2,'Australia',108,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(109,'China',0,'','open',2,'China',109,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(110,'France',0,'','open',2,'France',110,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(111,'Greece',0,'','open',2,'Greece',111,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(112,'Italy',0,'','open',2,'Italy',112,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(113,'Spain',0,'','open',2,'Spain',113,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(114,'Cambodia',0,'','open',2,'Cambodia',114,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(115,'Vietnam',0,'','open',2,'Vietnam',115,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(116,'Thailand',0,'','open',2,'Thailand',116,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(117,'New Zealand',0,'','open',2,'New Zealand',117,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(118,'UK',0,'','open',2,'UK',118,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(119,'USA',0,'','open',2,'USA',119,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(124,'WGS84 Decimal Lat-Long',0,'','open',2,'WGS84 Decimal Lat-Long',124,0,0,'enum',0,0,510,1,'2013-01-09 00:26:57',0,NULL),(125,'UTM (WGS84)',0,'','open',2,'UTM (WGS84)',125,0,0,'enum',0,0,510,1,'2013-01-09 00:26:57',0,NULL),(126,'Planar (local)',0,'','open',2,'Planar (local)',126,0,0,'enum',0,0,510,1,'2013-01-09 00:26:57',0,NULL),(127,'Unknown',0,'','open',2,'Unknown',127,0,0,'enum',0,0,510,1,'2013-01-09 00:26:57',0,NULL),(128,'Government department',0,'','open',2,'Government department',128,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(129,'Federal government',0,'','open',2,'Federal government',129,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(130,'Company',0,'','open',2,'Company',130,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(131,'NGO',0,'','open',2,'NGO',131,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(132,'Charity',0,'','open',2,'Charity',132,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(133,'Other - ask admin to define',0,'','open',2,'Other - ask admin to define',133,0,0,'enum',0,0,497,1,'2013-01-09 00:26:57',0,NULL),(135,'Australia',0,'','open',2,'Australia',135,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(136,'S, E & SE Asia',0,'','open',2,'S, E & SE Asia',136,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(137,'C & W Asia',0,'','open',2,'C & W Asia',137,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(138,'Europe/Mediterranean',0,'','open',2,'Europe/Mediterranean',138,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(139,'North America',0,'','open',2,'North America',139,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(140,'S & C America',0,'','open',2,'S & C America',140,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(141,'Worldwide',0,'','open',2,'Worldwide',141,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(142,'Non-regional',0,'','open',2,'Non-regional',142,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(147,'Multi-regional',0,'','open',2,'Multi-regional',147,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(160,'Cyprus',0,'','open',2,'Cyprus',160,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(161,'Jordan',0,'','open',2,'Jordan',161,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(162,'UAE',0,'','open',2,'UAE',162,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(163,'Uzbekistan',0,'','open',2,'Uzbekistan',163,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(164,'Kyrgistan',0,'','open',2,'Kyrgistan',164,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(165,'Dubai',0,'','open',2,'Dubai',165,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(166,'Israel',0,'','open',2,'Israel',166,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(167,'Iran',0,'','open',2,'Iran',167,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(168,'Iraq',0,'','open',2,'Iraq',168,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(169,'Syria',0,'','open',2,'Syria',169,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(170,'Turkey',0,'','open',2,'Turkey',170,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(171,'Myanmar',0,'','open',2,'Myanmar',171,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(172,'India',0,'','open',2,'India',172,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(173,'Singapore',0,'','open',2,'Singapore',173,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(177,'Norway',0,'','open',2,'Norway',177,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(178,'Netherlands',0,'','open',2,'Netherlands',178,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(179,'South Africa',0,'','open',2,'South Africa',179,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(180,'Canada',0,'','open',2,'Canada',180,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(181,'Ireland',0,'','open',2,'Ireland',181,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(182,'Germany',0,'','open',2,'Germany',182,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(183,'Hungary',0,'','open',2,'Hungary',183,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(184,'Belgium',0,'','open',2,'Belgium',184,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(185,'Brazil',0,'','open',2,'Brazil',185,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(186,'Sweden',0,'','open',2,'Sweden',186,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(195,'Denmark',0,'','open',2,'Denmark',195,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(196,'Buddhism',0,'','open',2,'Buddhism',196,0,0,'enum',0,0,511,1,'2013-01-09 00:26:57',0,NULL),(197,'Hinduism',0,'','open',2,'Hinduism',197,0,0,'enum',0,0,511,1,'2013-01-09 00:26:57',0,NULL),(198,'Taiwan',0,'','open',2,'Taiwan',198,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(204,'Afghanistan',0,'','open',2,'Afghanistan',204,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(205,'Aland Islands',0,'','open',2,'Aland Islands',205,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(206,'Albania',0,'','open',2,'Albania',206,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(207,'Algeria',0,'','open',2,'Algeria',207,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(208,'American Samoa',0,'','open',2,'American Samoa',208,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(209,'Andorra',0,'','open',2,'Andorra',209,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(210,'Angola',0,'','open',2,'Angola',210,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(211,'Anguilla',0,'','open',2,'Anguilla',211,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(212,'Antarctica',0,'','open',2,'Antarctica',212,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(213,'Antigua and Barbuda',0,'','open',2,'Antigua and Barbuda',213,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(214,'Argentina',0,'','open',2,'Argentina',214,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(215,'Armenia',0,'','open',2,'Armenia',215,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(216,'Aruba',0,'','open',2,'Aruba',216,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(217,'Austria',0,'','open',2,'Austria',217,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(218,'Azerbaijan',0,'','open',2,'Azerbaijan',218,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(219,'Bahamas',0,'','open',2,'Bahamas',219,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(220,'Bahrain',0,'','open',2,'Bahrain',220,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(221,'Bangladesh',0,'','open',2,'Bangladesh',221,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(222,'Barbados',0,'','open',2,'Barbados',222,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(223,'Belarus',0,'','open',2,'Belarus',223,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(224,'Belize',0,'','open',2,'Belize',224,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(225,'Benin',0,'','open',2,'Benin',225,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(226,'Bermuda',0,'','open',2,'Bermuda',226,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(227,'Bhutan',0,'','open',2,'Bhutan',227,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(228,'Bolivia',0,'','open',2,'Bolivia',228,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(229,'Bosnia and Herzegovina',0,'','open',2,'Bosnia and Herzegovina',229,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(230,'Botswana',0,'','open',2,'Botswana',230,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(231,'Bouvet Island',0,'','open',2,'Bouvet Island',231,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(232,'British Indian Ocean Territory',0,'','open',2,'British Indian Ocean Territory',232,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(233,'Brunei Darussalam',0,'','open',2,'Brunei Darussalam',233,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(234,'Bulgaria',0,'','open',2,'Bulgaria',234,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(235,'Burkina Faso',0,'','open',2,'Burkina Faso',235,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(236,'Burundi',0,'','open',2,'Burundi',236,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(237,'Cameroon',0,'','open',2,'Cameroon',237,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(238,'Cape Verde',0,'','open',2,'Cape Verde',238,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(239,'Cayman Islands',0,'','open',2,'Cayman Islands',239,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(240,'Central African Republic',0,'','open',2,'Central African Republic',240,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(241,'Chad',0,'','open',2,'Chad',241,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(242,'Chile',0,'','open',2,'Chile',242,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(243,'Christmas Island',0,'','open',2,'Christmas Island',243,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(244,'Cocos (Keeling) Islands',0,'','open',2,'Cocos (Keeling) Islands',244,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(245,'Colombia',0,'','open',2,'Colombia',245,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(246,'Comoros',0,'','open',2,'Comoros',246,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(247,'Congo',0,'','open',2,'Congo',247,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(248,'Congo, Dem. Rep. of the',0,'','open',2,'Congo, Democratic Republic of the',248,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(249,'Cook Islands',0,'','open',2,'Cook Islands',249,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(250,'Costa Rica',0,'','open',2,'Costa Rica',250,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(251,'C?te d\'Ivoire',0,'','open',2,'C?te d\'Ivoire',251,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(252,'Croatia',0,'','open',2,'Croatia',252,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(253,'Cuba',0,'','open',2,'Cuba',253,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(254,'Czech Republic',0,'','open',2,'Czech Republic',254,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(255,'Djibouti',0,'','open',2,'Djibouti',255,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(256,'Dominica',0,'','open',2,'Dominica',256,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(257,'Dominican Republic',0,'','open',2,'Dominican Republic',257,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(258,'Ecuador',0,'','open',2,'Ecuador',258,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(259,'Egypt',0,'','open',2,'Egypt',259,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(260,'El Salvador',0,'','open',2,'El Salvador',260,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(261,'Equatorial Guinea',0,'','open',2,'Equatorial Guinea',261,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(262,'Eritrea',0,'','open',2,'Eritrea',262,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(263,'Estonia',0,'','open',2,'Estonia',263,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(264,'Ethiopia',0,'','open',2,'Ethiopia',264,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(265,'Falkland Islands (malvinas)',0,'','open',2,'Falkland Islands (malvinas)',265,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(266,'Faroe Islands',0,'','open',2,'Faroe Islands',266,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(267,'Fiji',0,'','open',2,'Fiji',267,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(268,'Finland',0,'','open',2,'Finland',268,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(269,'French Guiana',0,'','open',2,'French Guiana',269,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(270,'French Polynesia',0,'','open',2,'French Polynesia',270,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(271,'French Southern Territories',0,'','open',2,'French Southern Territories',271,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(272,'Gabon',0,'','open',2,'Gabon',272,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(273,'Gambia',0,'','open',2,'Gambia',273,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(274,'Georgia',0,'','open',2,'Georgia',274,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(275,'Ghana',0,'','open',2,'Ghana',275,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(276,'Gibraltar',0,'','open',2,'Gibraltar',276,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(277,'Greenland',0,'','open',2,'Greenland',277,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(278,'Grenada',0,'','open',2,'Grenada',278,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(279,'Guadeloupe',0,'','open',2,'Guadeloupe',279,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(280,'Guam',0,'','open',2,'Guam',280,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(281,'Guatemala',0,'','open',2,'Guatemala',281,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(282,'Guernsey',0,'','open',2,'Guernsey',282,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(283,'Guinea',0,'','open',2,'Guinea',283,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(284,'Guinea-Bissau',0,'','open',2,'Guinea-Bissau',284,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(285,'Guyana',0,'','open',2,'Guyana',285,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(286,'Haiti',0,'','open',2,'Haiti',286,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(287,'Heard & Mcdonald Islands',0,'','open',2,'Heard Island and Mcdonald Islands',287,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(288,'Holy See (Vatican City State)',0,'','open',2,'Holy See (Vatican City State)',288,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(289,'Honduras',0,'','open',2,'Honduras',289,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(290,'Hong Kong',0,'','open',2,'Hong Kong',290,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(291,'Iceland',0,'','open',2,'Iceland',291,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(292,'Indonesia',0,'','open',2,'Indonesia',292,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(293,'Isle of Man',0,'','open',2,'Isle of Man',293,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(294,'Jamaica',0,'','open',2,'Jamaica',294,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(295,'Japan',0,'','open',2,'Japan',295,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(296,'Jersey',0,'','open',2,'Jersey',296,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(297,'Kazakhstan',0,'','open',2,'Kazakhstan',297,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(298,'Kenya',0,'','open',2,'Kenya',298,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(299,'Kiribati',0,'','open',2,'Kiribati',299,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(300,'Korea, Dem. People\'s Rep. of',0,'','open',2,'Korea, Democratic People\'s Republic of',300,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(301,'Korea, Republic of',0,'','open',2,'Korea, Republic of',301,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(302,'Kuwait',0,'','open',2,'Kuwait',302,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(303,'Lao People\'s Dem. Republic',0,'','open',2,'Lao People\'s Democratic Republic',303,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(304,'Latvia',0,'','open',2,'Latvia',304,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(305,'Lebanon',0,'','open',2,'Lebanon',305,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(306,'Lesotho',0,'','open',2,'Lesotho',306,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(307,'Liberia',0,'','open',2,'Liberia',307,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(308,'Libyan Arab Jamahiriya',0,'','open',2,'Libyan Arab Jamahiriya',308,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(309,'Liechtenstein',0,'','open',2,'Liechtenstein',309,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(310,'Lithuania',0,'','open',2,'Lithuania',310,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(311,'Luxembourg',0,'','open',2,'Luxembourg',311,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(312,'Macao',0,'','open',2,'Macao',312,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(313,'Macedonia, Frmr Yugoslav Rep. of',0,'','open',2,'Macedonia, Former Yugoslav Republic of',313,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(314,'Madagascar',0,'','open',2,'Madagascar',314,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(315,'Malawi',0,'','open',2,'Malawi',315,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(316,'Malaysia',0,'','open',2,'Malaysia',316,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(317,'Maldives',0,'','open',2,'Maldives',317,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(318,'Mali',0,'','open',2,'Mali',318,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(319,'Malta',0,'','open',2,'Malta',319,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(320,'Marshall Islands',0,'','open',2,'Marshall Islands',320,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(321,'Martinique',0,'','open',2,'Martinique',321,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(322,'Mauritania',0,'','open',2,'Mauritania',322,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(323,'Mauritius',0,'','open',2,'Mauritius',323,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(324,'Mayotte',0,'','open',2,'Mayotte',324,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(325,'Mexico',0,'','open',2,'Mexico',325,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(326,'Micronesia, Fed. States of',0,'','open',2,'Micronesia, Federated States of',326,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(327,'Moldova, Republic of',0,'','open',2,'Moldova, Republic of',327,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(328,'Monaco',0,'','open',2,'Monaco',328,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(329,'Mongolia',0,'','open',2,'Mongolia',329,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(330,'Montenegro',0,'','open',2,'Montenegro',330,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(331,'Montserrat',0,'','open',2,'Montserrat',331,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(332,'Morocco',0,'','open',2,'Morocco',332,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(333,'Mozambique',0,'','open',2,'Mozambique',333,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(334,'Namibia',0,'','open',2,'Namibia',334,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(335,'Nauru',0,'','open',2,'Nauru',335,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(336,'Nepal',0,'','open',2,'Nepal',336,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(337,'Netherlands Antilles',0,'','open',2,'Netherlands Antilles',337,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(338,'New Caledonia',0,'','open',2,'New Caledonia',338,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(339,'Nicaragua',0,'','open',2,'Nicaragua',339,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(340,'Niger',0,'','open',2,'Niger',340,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(341,'Nigeria',0,'','open',2,'Nigeria',341,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(342,'Niue',0,'','open',2,'Niue',342,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(343,'Norfolk Island',0,'','open',2,'Norfolk Island',343,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(344,'Northern Mariana Islands',0,'','open',2,'Northern Mariana Islands',344,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(345,'Oman',0,'','open',2,'Oman',345,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(346,'Pakistan',0,'','open',2,'Pakistan',346,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(347,'Palau',0,'','open',2,'Palau',347,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(348,'Palestinian Territory, Occupied',0,'','open',2,'Palestinian Territory, Occupied',348,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(349,'Panama',0,'','open',2,'Panama',349,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(350,'Papua New Guinea',0,'','open',2,'Papua New Guinea',350,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(351,'Paraguay',0,'','open',2,'Paraguay',351,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(352,'Peru',0,'','open',2,'Peru',352,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(353,'Philippines',0,'','open',2,'Philippines',353,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(354,'Pitcairn',0,'','open',2,'Pitcairn',354,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(355,'Poland',0,'','open',2,'Poland',355,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(356,'Portugal',0,'','open',2,'Portugal',356,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(357,'Puerto Rico',0,'','open',2,'Puerto Rico',357,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(358,'Qatar',0,'','open',2,'Qatar',358,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(359,'Reunion',0,'','open',2,'Reunion',359,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(360,'Romania',0,'','open',2,'Romania',360,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(361,'Russian Federation',0,'','open',2,'Russian Federation',361,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(362,'Rwanda',0,'','open',2,'Rwanda',362,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(363,'Saint Barth?lemy',0,'','open',2,'Saint Barth?lemy',363,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(364,'Saint Helena',0,'','open',2,'Saint Helena',364,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(365,'Saint Kitts and Nevis',0,'','open',2,'Saint Kitts and Nevis',365,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(366,'Saint Lucia',0,'','open',2,'Saint Lucia',366,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(367,'Saint Martin',0,'','open',2,'Saint Martin',367,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(368,'Saint Pierre and Miquelon',0,'','open',2,'Saint Pierre and Miquelon',368,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(369,'Saint Vincent and the Grenadines',0,'','open',2,'Saint Vincent and the Grenadines',369,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(370,'Samoa',0,'','open',2,'Samoa',370,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(371,'San Marino',0,'','open',2,'San Marino',371,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(372,'Sao Tome and Principe',0,'','open',2,'Sao Tome and Principe',372,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(373,'Saudi Arabia',0,'','open',2,'Saudi Arabia',373,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(374,'Senegal',0,'','open',2,'Senegal',374,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(375,'Serbia',0,'','open',2,'Serbia',375,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(376,'Seychelles',0,'','open',2,'Seychelles',376,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(377,'Sierra Leone',0,'','open',2,'Sierra Leone',377,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(378,'Slovakia',0,'','open',2,'Slovakia',378,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(379,'Slovenia',0,'','open',2,'Slovenia',379,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(380,'Solomon Islands',0,'','open',2,'Solomon Islands',380,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(381,'Somalia',0,'','open',2,'Somalia',381,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(382,'South Georgia & S. Sandwich Ils.',0,'','open',2,'South Georgia and the South Sandwich Islands',382,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(383,'Sri Lanka',0,'','open',2,'Sri Lanka',383,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(384,'Sudan',0,'','open',2,'Sudan',384,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(385,'Suriname',0,'','open',2,'Suriname',385,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(386,'Svalbard and Jan Mayen',0,'','open',2,'Svalbard and Jan Mayen',386,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(387,'Swaziland',0,'','open',2,'Swaziland',387,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(388,'Switzerland',0,'','open',2,'Switzerland',388,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(389,'Tajikistan',0,'','open',2,'Tajikistan',389,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(390,'Tanzania, United Republic of',0,'','open',2,'Tanzania, United Republic of',390,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(391,'Timor-Leste',0,'','open',2,'Timor-Leste',391,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(392,'Togo',0,'','open',2,'Togo',392,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(393,'Tokelau',0,'','open',2,'Tokelau',393,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(394,'Tonga',0,'','open',2,'Tonga',394,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(395,'Trinidad and Tobago',0,'','open',2,'Trinidad and Tobago',395,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(396,'Tunisia',0,'','open',2,'Tunisia',396,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(397,'Turkmenistan',0,'','open',2,'Turkmenistan',397,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(398,'Turks and Caicos Islands',0,'','open',2,'Turks and Caicos Islands',398,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(399,'Tuvalu',0,'','open',2,'Tuvalu',399,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(400,'Uganda',0,'','open',2,'Uganda',400,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(401,'Ukraine',0,'','open',2,'Ukraine',401,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(402,'United States Minor Outlying Islnds',0,'','open',2,'United States Minor Outlying Islands',402,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',1,NULL),(403,'Uruguay',0,'','open',2,'Uruguay',403,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(404,'Vanuatu',0,'','open',2,'Vanuatu',404,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(405,'Venezuela',0,'','open',2,'Venezuela',405,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(406,'Virgin Islands, British',0,'','open',2,'Virgin Islands, British',406,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(407,'Virgin Islands, U.S.',0,'','open',2,'Virgin Islands, U.S.',407,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(408,'Wallis and Futuna',0,'','open',2,'Wallis and Futuna',408,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(409,'Western Sahara',0,'','open',2,'Western Sahara',409,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(410,'Yemen',0,'','open',2,'Yemen',410,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(411,'Zambia',0,'','open',2,'Zambia',411,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(412,'Zimbabwe',0,'','open',2,'Zimbabwe',412,0,0,'enum',0,0,509,1,'2013-01-09 00:26:57',0,NULL),(414,'Male',0,'','open',2,'Male',414,0,0,'enum',0,0,513,1,'2013-01-09 00:26:57',0,NULL),(415,'Female',0,'','open',2,'Female',415,0,0,'enum',0,0,513,1,'2013-01-09 00:26:57',0,NULL),(449,'Middle East',0,'','open',2,'Middle East',449,0,0,'enum',0,0,506,1,'2013-01-09 00:26:57',0,NULL),(460,'English (EN, ENG)',0,'','reserved',2,'English (EN, ENG)',460,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(461,'Arabic (AR, ARA)',0,'','reserved',2,'Arabic (AR, ARA)',461,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(462,'Yiddish (YI, YID)',0,'','reserved',2,'Yiddish (YI, YID)',462,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(463,'Hebrew (HE, HEB)',0,'','reserved',2,'Hebrew (HE, HEB)',463,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(464,'French (FR, FRE)',0,'','reserved',2,'French (FR, FRE)',464,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(465,'Italian (IT, ITA)',0,'','reserved',2,'Italian (IT, ITA)',465,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(466,'Spanish (ES, SPA)',0,'','reserved',2,'Spanish (ES, SPA)',466,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(467,'Dutch (NL, DUTENG)',0,'','reserved',2,'Dutch (NL, DUTENG)',467,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(468,'Danish (DA, DAN)',0,'','reserved',2,'Danish (DA, DAN)',468,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(469,'Norwegian (NO, NOR)',0,'','reserved',2,'Norwegian (NO, NOR)',469,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(470,'Portuguese} (PT, POR)',0,'','reserved',2,'Portuguese} (PT, POR)',470,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(471,'German (DE, GER)',0,'','reserved',2,'German (DE, GER)',471,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(472,'Greek (EL, GRE)',0,'','reserved',2,'Greek (EL, GRE)',472,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(473,'Turkish (TR, TUR)',0,'','reserved',2,'Turkish (TR, TUR)',473,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(474,'Russian (RU, RUS)',0,'','reserved',2,'Russian (RU, RUS)',474,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(475,'Ukrainian (UK, UKR)',0,'','reserved',2,'Ukrainian (UK, UKR)',475,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(476,'Swedish (SV, SWE)',0,'','reserved',2,'Swedish (SV, SWE)',476,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(477,'Finish (FI, FIN)',0,'','reserved',2,'Finish (FI, FIN)',477,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(478,'Latvian (LV, LAV)',0,'','reserved',2,'Latvian (LV, LAV)',478,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(479,'Estonian (ET, EST)',0,'','reserved',2,'Estonian (ET, EST)',479,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(480,'Hungarian (HU, HUN)',0,'','reserved',2,'Hungarian (HU, HUN)',480,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(481,'Czech (CS, CZE)',0,'','reserved',2,'Czech (CS, CZE)',481,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(482,'Polish (PL, ENG)',0,'','reserved',2,'Polish (PL, ENG)',482,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(483,'Slovak (EN, POL)',0,'','reserved',2,'Slovak (EN, POL)',483,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(484,'Serbian (EN, SCR)',0,'','reserved',2,'Serbian (EN, SCR)',484,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(485,'Croatian (HR, SCC)',0,'','reserved',2,'Croatian (HR, SCC)',485,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(486,'Swahili (SW, SWA)',0,'','reserved',2,'Swahili (SW, SWA)',486,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(487,'Chinese (ZH, CHI)',0,'','reserved',2,'Chinese (ZH, CHI)',487,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(488,'Indonesian (ID, IND)',0,'','reserved',2,'Indonesian (ID, IND)',488,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(489,'Hindi (HI, HIN)',0,'','reserved',2,'Hindi (HI, HIN)',489,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(490,'Japanese (JA, JPN)',0,'','reserved',2,'Japanese (JA, JPN)',490,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(491,'Malay (MS, MAL)',0,'','reserved',2,'Malay (MS, MAL)',491,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(492,'Korean (KO, KOR)',0,'','reserved',2,'Korean (KO, KOR)',492,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(493,'Vietnamese (VI, VIE)',0,'','reserved',2,'Vietnamese (VI, VIE)',493,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(494,'Thai (TH, THA)',0,'','reserved',2,'Thai (TH, THA)',494,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(495,'Khmer (KM, CAM)',0,'','reserved',2,'Khmer (KM, CAM)',495,0,0,'enum',0,0,496,1,'2013-01-09 00:26:57',0,NULL),(496,'Language',0,'Common languages vocabulary','reserved',2,'Language',496,0,0,'enum',0,36,0,0,'2013-01-09 00:26:57',0,NULL),(497,'Organisation type',0,'','open',2,'Organisation type',497,0,0,'enum',0,17,0,0,'2013-01-09 00:26:57',0,NULL),(506,'Geographic region',0,'','open',2,'Geographic region',506,0,0,'enum',0,10,0,0,'2013-01-09 00:26:57',0,NULL),(507,'Honorific',0,'','open',2,'Honorific',507,0,0,'enum',0,7,0,0,'2013-01-09 00:26:57',0,NULL),(508,'Date system',0,'','open',2,'Date system',508,0,0,'enum',0,4,0,0,'2013-01-09 00:26:57',0,NULL),(509,'Country',0,'','open',2,'Country',509,0,0,'enum',0,127,0,0,'2013-01-09 00:26:57',0,NULL),(510,'Coordinate System',0,'','open',2,'Coordinate System',510,0,0,'enum',0,4,0,0,'2013-01-09 00:26:57',0,NULL),(511,'Religion',0,'','open',2,'Religion',511,0,0,'enum',0,4,0,0,'2013-01-09 00:26:57',0,NULL),(513,'Gender',0,'','open',2,'Gender',513,0,0,'enum',0,4,0,0,'2013-01-09 00:26:57',0,NULL),(514,'Christianity',0,'','open',2,'Christianity',514,0,0,'enum',0,2,511,1,'2013-01-09 00:26:57',0,NULL),(515,'Islam',0,'','open',2,'Islam',515,0,0,'enum',0,0,511,1,'2013-01-09 00:26:57',0,NULL),(516,'Catholic',0,'','open',2,'Catholic',516,0,0,'enum',0,0,514,2,'2013-01-09 00:26:57',0,NULL),(517,'Protestant',0,'','open',2,'Protestant',517,0,0,'enum',0,0,514,2,'2013-01-09 00:26:57',0,NULL),(518,'Private',0,'','open',2,'Private',518,0,0,'enum',0,0,11,2,'2013-01-09 00:26:57',0,NULL),(519,'Public',0,'','open',2,'Public',519,0,0,'enum',0,0,11,2,'2013-01-09 00:26:57',0,NULL),(520,'File schema',0,'A file format/schema such as TEI, DocBook, PDF, Bitmap eg for use in annotation','open',2,'File schema',520,0,0,'enum',0,2,0,0,'2013-01-09 00:26:57',0,NULL),(521,'Text',0,'','open',2,'Text',521,0,0,'enum',0,3,520,1,'2013-01-09 00:26:57',0,NULL),(522,'Bitmap',0,'','open',2,'Bitmap',522,0,0,'enum',0,0,520,1,'2013-01-09 00:26:57',0,NULL),(523,'PDF',0,'','open',2,'PDF',523,0,0,'enum',0,0,521,2,'2013-01-09 00:26:57',0,NULL),(524,'XML',0,'','open',2,'XML',524,0,0,'enum',0,3,521,2,'2013-01-09 00:26:57',0,NULL),(525,'TEI',0,'','open',2,'TEI',525,0,0,'enum',0,2,524,3,'2013-01-09 00:26:57',0,NULL),(526,'DocBook',0,'','open',2,'DocBook',526,0,0,'enum',0,0,524,3,'2013-01-09 00:26:57',0,NULL),(527,'Other',0,'','open',2,'Other',527,0,0,'enum',0,0,513,1,'2013-01-09 00:26:57',0,NULL),(528,'Unknown',0,'','open',2,'Unknown',528,0,0,'enum',0,0,513,1,'2013-01-09 00:26:57',0,NULL),(529,'Lord',0,'','open',2,'Lord',529,0,0,'enum',0,0,507,1,'2013-01-09 00:26:57',0,NULL),(530,'Flag',0,'A limited option pseudo-Boolean vocabulary ','open',2,'Flag',530,1,0,'enum',0,2,0,0,'2013-01-09 00:26:57',0,NULL),(531,'No',0,'Negative response','open',2,'No',531,1,0,'enum',0,0,530,1,'2013-01-09 00:26:57',0,NULL),(532,'Yes',0,'Affirmative response','open',2,'Yes',532,1,0,'enum',0,0,530,1,'2013-01-09 00:26:57',0,NULL),(533,'Mime Type',0,'','open',2,'Mime Type',533,1,0,'enum',0,9,0,0,'2013-01-09 00:26:57',0,NULL),(534,'image/bmp',0,'','open',2,'image/bmp',534,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(535,'image/ermapper',0,'','open',2,'image/ermapper',535,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(536,'image/gif',0,'','open',2,'image/gif',536,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(537,'image/jpeg',0,'','open',2,'image/jpeg',537,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(538,'image/jpeg2000',0,'','open',2,'image/jpeg2000',538,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(539,'image/mrsid',0,'','open',2,'image/mrsid',539,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(540,'image/png',0,'','open',2,'image/png',540,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(541,'image/tiff',0,'','open',2,'image/tiff',541,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(542,'image/zoomify',0,'','open',2,'image/zoomify',542,1,0,'enum',0,0,533,1,'2013-01-09 00:26:57',0,NULL),(543,'Tiled image type',0,'','open',2,'Map image layer type',543,1,0,'enum',0,2,0,0,'2013-01-09 00:26:57',1,NULL),(544,'image',0,'','open',2,'image',544,1,0,'enum',0,0,543,1,'2013-01-09 00:26:57',0,NULL),(545,'map',0,'','open',2,'map',545,1,0,'enum',0,0,543,1,'2013-01-09 00:26:57',0,NULL),(546,'Image tiling schema',0,'','open',2,'Map image layer tiling schema',546,1,0,'enum',0,4,0,0,'2013-01-09 00:26:57',1,NULL),(547,'gmapimage',0,'','open',2,'gmapimage',547,1,0,'enum',0,0,546,1,'2013-01-09 00:26:57',0,NULL),(548,'maptiler',0,'','open',2,'maptiler',548,1,0,'enum',0,0,546,1,'2013-01-09 00:26:57',0,NULL),(549,'virtual earth',0,'','open',2,'virtual earth',549,1,0,'enum',0,0,546,1,'2013-01-09 00:26:57',0,NULL),(550,'zoomify',0,'','open',2,'zoomify',550,1,0,'enum',0,0,546,1,'2013-01-09 00:26:57',0,NULL),(551,'Colour',0,'','open',2,'Colour',551,0,0,'enum',0,127,0,0,'2013-01-09 00:26:57',0,NULL),(552,'AliceBlue',0,'','open',2,'AliceBlue',552,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(553,'AntiqueWhite',0,'','open',2,'AntiqueWhite',553,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(554,'Aqua',0,'','open',2,'Aqua',554,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(555,'Aquamarine',0,'','open',2,'Aquamarine',555,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(556,'Azure',0,'','open',2,'Azure',556,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(557,'Beige',0,'','open',2,'Beige',557,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(558,'Bisque',0,'','open',2,'Bisque',558,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(559,'Black',0,'','open',2,'Black',559,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(560,'BlanchedAlmond',0,'','open',2,'BlanchedAlmond',560,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(561,'Blue',0,'','open',2,'Blue',561,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(562,'BlueViolet',0,'','open',2,'BlueViolet',562,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(563,'Brown',0,'','open',2,'Brown',563,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(564,'BurlyWood',0,'','open',2,'BurlyWood',564,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(565,'CadetBlue',0,'','open',2,'CadetBlue',565,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(566,'Chartreuse',0,'','open',2,'Chartreuse',566,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(567,'Chocolate',0,'','open',2,'Chocolate',567,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(568,'Coral',0,'','open',2,'Coral',568,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(569,'CornflowerBlue',0,'','open',2,'CornflowerBlue',569,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(570,'Cornsilk',0,'','open',2,'Cornsilk',570,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(571,'Crimson',0,'','open',2,'Crimson',571,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(572,'Cyan',0,'','open',2,'Cyan',572,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(573,'DarkBlue',0,'','open',2,'DarkBlue',573,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(574,'DarkCyan',0,'','open',2,'DarkCyan',574,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(575,'DarkGoldenRod',0,'','open',2,'DarkGoldenRod',575,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(576,'DarkGray',0,'','open',2,'DarkGray',576,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(577,'DarkGrey',0,'','open',2,'DarkGrey',577,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(578,'DarkGreen',0,'','open',2,'DarkGreen',578,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(579,'DarkKhaki',0,'','open',2,'DarkKhaki',579,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(580,'DarkMagenta',0,'','open',2,'DarkMagenta',580,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(581,'DarkOliveGreen',0,'','open',2,'DarkOliveGreen',581,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(582,'Darkorange',0,'','open',2,'Darkorange',582,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(583,'DarkOrchid',0,'','open',2,'DarkOrchid',583,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(584,'DarkRed',0,'','open',2,'DarkRed',584,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(585,'DarkSalmon',0,'','open',2,'DarkSalmon',585,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(586,'DarkSeaGreen',0,'','open',2,'DarkSeaGreen',586,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(587,'DarkSlateBlue',0,'','open',2,'DarkSlateBlue',587,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(588,'DarkSlateGray',0,'','open',2,'DarkSlateGray',588,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(589,'DarkSlateGrey',0,'','open',2,'DarkSlateGrey',589,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(590,'DarkTurquoise',0,'','open',2,'DarkTurquoise',590,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(591,'DarkViolet',0,'','open',2,'DarkViolet',591,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(592,'DeepPink',0,'','open',2,'DeepPink',592,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(593,'DeepSkyBlue',0,'','open',2,'DeepSkyBlue',593,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(594,'DimGray',0,'','open',2,'DimGray',594,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(595,'DimGrey',0,'','open',2,'DimGrey',595,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(596,'DodgerBlue',0,'','open',2,'DodgerBlue',596,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(597,'FireBrick',0,'','open',2,'FireBrick',597,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(598,'FloralWhite',0,'','open',2,'FloralWhite',598,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(599,'ForestGreen',0,'','open',2,'ForestGreen',599,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(600,'Fuchsia',0,'','open',2,'Fuchsia',600,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(601,'Gainsboro',0,'','open',2,'Gainsboro',601,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(602,'GhostWhite',0,'','open',2,'GhostWhite',602,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(603,'Gold',0,'','open',2,'Gold',603,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(604,'GoldenRod',0,'','open',2,'GoldenRod',604,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(605,'Gray',0,'','open',2,'Gray',605,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(606,'Grey',0,'','open',2,'Grey',606,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(607,'Green',0,'','open',2,'Green',607,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(608,'GreenYellow',0,'','open',2,'GreenYellow',608,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(609,'HoneyDew',0,'','open',2,'HoneyDew',609,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(610,'HotPink',0,'','open',2,'HotPink',610,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(611,'IndianRed',0,'','open',2,'IndianRed',611,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(612,'Indigo',0,'','open',2,'Indigo',612,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(613,'Ivory',0,'','open',2,'Ivory',613,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(614,'Khaki',0,'','open',2,'Khaki',614,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(615,'Lavender',0,'','open',2,'Lavender',615,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(616,'LavenderBlush',0,'','open',2,'LavenderBlush',616,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(617,'LawnGreen',0,'','open',2,'LawnGreen',617,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(618,'LemonChiffon',0,'','open',2,'LemonChiffon',618,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(619,'LightBlue',0,'','open',2,'LightBlue',619,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(620,'LightCoral',0,'','open',2,'LightCoral',620,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(621,'LightCyan',0,'','open',2,'LightCyan',621,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(622,'LightGoldenRodYellow',0,'','open',2,'LightGoldenRodYellow',622,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(623,'LightGray',0,'','open',2,'LightGray',623,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(624,'LightGrey',0,'','open',2,'LightGrey',624,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(625,'LightGreen',0,'','open',2,'LightGreen',625,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(626,'LightPink',0,'','open',2,'LightPink',626,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(627,'LightSalmon',0,'','open',2,'LightSalmon',627,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(628,'LightSeaGreen',0,'','open',2,'LightSeaGreen',628,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(629,'LightSkyBlue',0,'','open',2,'LightSkyBlue',629,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(630,'LightSlateGray',0,'','open',2,'LightSlateGray',630,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(631,'LightSlateGrey',0,'','open',2,'LightSlateGrey',631,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(632,'LightSteelBlue',0,'','open',2,'LightSteelBlue',632,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(633,'LightYellow',0,'','open',2,'LightYellow',633,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(634,'Lime',0,'','open',2,'Lime',634,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(635,'LimeGreen',0,'','open',2,'LimeGreen',635,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(636,'Linen',0,'','open',2,'Linen',636,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(637,'Magenta',0,'','open',2,'Magenta',637,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(638,'Maroon',0,'','open',2,'Maroon',638,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(639,'MediumAquaMarine',0,'','open',2,'MediumAquaMarine',639,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(640,'MediumBlue',0,'','open',2,'MediumBlue',640,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(641,'MediumOrchid',0,'','open',2,'MediumOrchid',641,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(642,'MediumPurple',0,'','open',2,'MediumPurple',642,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(643,'MediumSeaGreen',0,'','open',2,'MediumSeaGreen',643,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(644,'MediumSlateBlue',0,'','open',2,'MediumSlateBlue',644,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(645,'MediumSpringGreen',0,'','open',2,'MediumSpringGreen',645,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(646,'MediumTurquoise',0,'','open',2,'MediumTurquoise',646,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(647,'MediumVioletRed',0,'','open',2,'MediumVioletRed',647,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(648,'MidnightBlue',0,'','open',2,'MidnightBlue',648,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(649,'MintCream',0,'','open',2,'MintCream',649,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(650,'MistyRose',0,'','open',2,'MistyRose',650,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(651,'Moccasin',0,'','open',2,'Moccasin',651,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(652,'NavajoWhite',0,'','open',2,'NavajoWhite',652,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(653,'Navy',0,'','open',2,'Navy',653,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(654,'OldLace',0,'','open',2,'OldLace',654,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(655,'Olive',0,'','open',2,'Olive',655,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(656,'OliveDrab',0,'','open',2,'OliveDrab',656,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(657,'Orange',0,'','open',2,'Orange',657,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(658,'OrangeRed',0,'','open',2,'OrangeRed',658,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(659,'Orchid',0,'','open',2,'Orchid',659,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(660,'PaleGoldenRod',0,'','open',2,'PaleGoldenRod',660,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(661,'PaleGreen',0,'','open',2,'PaleGreen',661,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(662,'PaleTurquoise',0,'','open',2,'PaleTurquoise',662,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(663,'PaleVioletRed',0,'','open',2,'PaleVioletRed',663,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(664,'PapayaWhip',0,'','open',2,'PapayaWhip',664,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(665,'PeachPuff',0,'','open',2,'PeachPuff',665,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(666,'Peru',0,'','open',2,'Peru',666,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(667,'Pink',0,'','open',2,'Pink',667,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(668,'Plum',0,'','open',2,'Plum',668,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(669,'PowderBlue',0,'','open',2,'PowderBlue',669,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(670,'Purple',0,'','open',2,'Purple',670,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(671,'Red',0,'','open',2,'Red',671,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(672,'RosyBrown',0,'','open',2,'RosyBrown',672,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(673,'RoyalBlue',0,'','open',2,'RoyalBlue',673,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(674,'SaddleBrown',0,'','open',2,'SaddleBrown',674,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(675,'Salmon',0,'','open',2,'Salmon',675,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(676,'SandyBrown',0,'','open',2,'SandyBrown',676,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(677,'SeaGreen',0,'','open',2,'SeaGreen',677,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(678,'SeaShell',0,'','open',2,'SeaShell',678,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(679,'Sienna',0,'','open',2,'Sienna',679,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(680,'Silver',0,'','open',2,'Silver',680,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(681,'SkyBlue',0,'','open',2,'SkyBlue',681,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(682,'SlateBlue',0,'','open',2,'SlateBlue',682,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(683,'SlateGray',0,'','open',2,'SlateGray',683,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(684,'SlateGrey',0,'','open',2,'SlateGrey',684,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(685,'Snow',0,'','open',2,'Snow',685,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(686,'SpringGreen',0,'','open',2,'SpringGreen',686,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(687,'SteelBlue',0,'','open',2,'SteelBlue',687,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(688,'Tan',0,'','open',2,'Tan',688,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(689,'Teal',0,'','open',2,'Teal',689,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(690,'Thistle',0,'','open',2,'Thistle',690,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(691,'Tomato',0,'','open',2,'Tomato',691,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(692,'Turquoise',0,'','open',2,'Turquoise',692,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(693,'Violet',0,'','open',2,'Violet',693,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(694,'Wheat',0,'','open',2,'Wheat',694,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(695,'White',0,'','open',2,'White',695,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(696,'WhiteSmoke',0,'','open',2,'WhiteSmoke',696,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(697,'Yellow',0,'','open',2,'Yellow',697,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(698,'YellowGreen',0,'','open',2,'YellowGreen',698,1,0,'enum',0,0,551,1,'2013-01-09 00:26:57',0,NULL),(699,'Smarty',0,'','open',2,'Smarty',699,0,0,'enum',0,0,521,2,'2013-01-09 00:26:57',0,NULL),(700,'XSLT',0,'','open',2,'XSLT',700,0,0,'enum',0,3,524,3,'2013-01-09 00:26:57',0,NULL),(701,'XSLT 1.0',0,'','open',2,'XSLT 1.0',701,0,0,'enum',0,0,700,4,'2013-01-09 00:26:57',0,NULL),(702,'XSLT 1.1',0,'','open',2,'XSLT 1.1',702,0,0,'enum',0,0,700,4,'2013-01-09 00:26:57',0,NULL),(703,'XSLT 2.0',0,'','open',2,'XSLT 2.0',703,0,0,'enum',0,0,700,4,'2013-01-09 00:26:57',0,NULL),(704,'TEI P4',0,'','open',2,'TEI P4',704,0,0,'enum',0,0,525,4,'2013-01-09 00:26:57',0,NULL),(705,'TEI P5',0,'','open',2,'TEI P5',705,0,0,'enum',0,0,525,4,'2013-01-09 00:26:57',0,NULL),(3001,'Generic',0,'','open',2,'Generic',3001,0,0,'relation',0,6,0,0,'2013-01-09 00:26:57',0,NULL),(3005,'Causes',0,'','open',2,'Causes',3005,0,0,'relation',0,0,3113,1,'2013-01-09 00:26:57',0,NULL),(3011,'StartsAfter',3012,'','open',2,'StartsAfter',3011,0,0,'relation',0,0,3270,1,'2013-01-09 00:26:57',1,NULL),(3012,'EndsBefore',3011,'','open',2,'EndsBefore',3012,0,0,'relation',0,0,3270,1,'2013-01-09 00:26:57',1,NULL),(3083,'IsNeiceOrNephewOf',3084,'','open',2,'IsNeiceOrNephewOf',3083,0,0,'relation',0,0,3259,1,'2013-01-09 00:26:57',1,NULL),(3084,'IsAuntOrUncleOf',3083,'','open',2,'IsAuntOrUncleOf',3084,0,0,'relation',0,0,3259,1,'2013-01-09 00:26:57',1,NULL),(3087,'IsBrotherOf',3442,'','open',2,'IsBrotherOf',3087,0,0,'relation',0,0,3118,2,'2013-01-09 00:26:57',0,NULL),(3088,'IsBrotherOrSisterInLaw',3088,'','open',2,'IsBrotherOrSisterInLaw',3088,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3089,'IsParentOf',3090,'','open',2,'IsParentOf',3089,0,0,'relation',0,2,3260,1,'2013-01-09 00:26:57',1,NULL),(3090,'IsChildOf',3089,'','open',2,'IsChildOf',3090,0,0,'relation',0,2,3261,1,'2013-01-09 00:26:57',1,NULL),(3091,'IsContemporaryWith',3091,'','open',2,'IsContemporaryWith',3091,0,0,'relation',0,0,3269,1,'2013-01-09 00:26:57',1,NULL),(3092,'IsCousinOf',3092,'','open',2,'IsCousinOf',3092,0,0,'relation',0,0,3259,1,'2013-01-09 00:26:57',1,NULL),(3094,'IsDaughterOrSonInLaw',3105,'','open',2,'IsDaughterOrSonInLaw',3094,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3095,'IsFatherOf',3090,'','open',2,'IsFatherOf',3095,0,0,'relation',0,0,3089,2,'2013-01-09 00:26:57',0,NULL),(3099,'IsGrandFatherOf',3116,'','open',2,'IsGrandfatherOf',3099,0,0,'relation',0,0,3115,2,'2013-01-09 00:26:57',1,NULL),(3100,'IsGrandMotherOf',3116,'','open',2,'IsGrandmotherOf',3100,0,0,'relation',0,0,3115,2,'2013-01-09 00:26:57',1,NULL),(3103,'IsMarriedTo',3103,'','open',2,'IsMarriedTo',3103,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3104,'IsMotherOf',3090,'','open',2,'IsMotherOf',3104,0,0,'relation',0,0,3089,2,'2013-01-09 00:26:57',0,NULL),(3105,'IsMotherOrFatherInLaw',3094,'','open',2,'IsMotherOrFatherInLaw',3105,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3108,'IsRelatedTo',3108,'','open',2,'IsRelatedTo',3108,0,0,'relation',0,0,3001,1,'2013-01-09 00:26:57',0,NULL),(3110,'Family',0,'','open',2,'Family',3110,0,0,'relation',0,13,0,0,'2013-01-09 00:26:57',0,NULL),(3113,'Temporal',0,'','open',2,'Temporal',3113,0,0,'relation',0,4,0,0,'2013-01-09 00:26:57',0,NULL),(3115,'IsGrandParentOf',3116,'','open',2,'IsGrandparentOf',3115,0,0,'relation',0,2,3260,1,'2013-01-09 00:26:57',1,NULL),(3116,'IsGrandChildOf',3115,'','open',2,'IsGrandchildOf',3116,0,0,'relation',0,2,3261,1,'2013-01-09 00:26:57',1,NULL),(3117,'IsSisterOf',3118,'','open',2,'IsSisterOf',3117,0,0,'relation',0,0,3118,2,'2013-01-09 00:26:57',0,NULL),(3118,'IsSiblingOf',3118,'','open',2,'IsSiblingOf',3118,0,0,'relation',0,2,3110,1,'2013-01-09 00:26:57',0,NULL),(3120,'Reconfigurations',0,'','reserved',2,'Reconfigurations',3120,0,0,'relation',0,12,0,0,'2013-01-09 00:26:57',0,NULL),(3121,'Versions',0,'','reserved',2,'Versions',3121,0,0,'relation',0,30,0,0,'2013-01-09 00:26:57',0,NULL),(3122,'Media',0,'','reserved',2,'Media',3122,0,0,'relation',0,6,0,0,'2013-01-09 00:26:57',0,NULL),(3123,'Derivatives',0,'','reserved',2,'Derivatives',3123,0,0,'relation',0,14,0,0,'2013-01-09 00:26:57',0,NULL),(3133,'IsFamilyMemberOf',3133,'Added from LORE','open',2,'IsFamilyMemberOf',3133,0,0,'relation',0,0,3110,1,'2013-01-09 00:26:57',0,NULL),(3135,'IsRepresentationOf',3222,'Added from LORE','open',2,'IsRepresentationOf',3135,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3136,'IsPerformanceOf',3220,'Added from LORE','open',2,'IsPerformanceOf',3136,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3137,'IsDramatizationOf',3218,'Added from LORE','open',2,'IsDramatizationOf',3137,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3138,'IsScreenplayOf',3223,'Added from LORE','open',2,'IsScreenplayOf',3138,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3139,'IsReconfigurationOf',3221,'Added from LORE','open',2,'IsReconfigurationOf',3139,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3140,'IsNovelizationOf',3219,'Added from LORE','open',2,'IsNovelizationOf',3140,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3141,'IsSummaryOf',3238,'Added from LORE','open',2,'IsSummaryOf',3141,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3142,'IsDigestOf',3231,'Added from LORE','open',2,'IsDigestOf',3142,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3143,'IsCopyOf',3230,'Added from LORE','open',2,'IsCopyOf',3143,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3144,'IsTranscriptOf',3239,'Added from LORE','open',2,'IsTranscriptOf',3144,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3145,'IsTranslationOf',3240,'Added from LORE','open',2,'IsTranslationOf',3145,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3146,'IsAdaptationOf',3228,'Added from LORE','open',2,'IsAdaptationOf',3146,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3147,'IsEditionOf',3232,'Added from LORE','open',2,'IsEditionOf',3147,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3148,'IsAnnotatedEditionOf',3229,'Added from LORE','open',2,'IsAnnotatedEditionOf',3148,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3149,'IsAbridgedEditionOf',3227,'Added from LORE','open',2,'IsAbridgedEditionOf',3149,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3150,'IsIllustratedEditionOf',3234,'Added from LORE','open',2,'IsIllustratedEditionOf',3150,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3151,'IsExpurgatedEditionOf',3233,'Added from LORE','open',2,'IsExpurgatedEditionOf',3151,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3152,'IsReproductionOf',3236,'Added from LORE','open',2,'IsReproductionOf',3152,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3153,'IsRealizationOf',3235,'Added from LORE','open',2,'IsRealizationOf',3153,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3154,'IsRevisionOf',3237,'Added from LORE','open',2,'IsRevisionOf',3154,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3155,'IsVersionOf',3241,'Added from LORE','open',2,'IsVersionOf',3155,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3156,'IsImageOf',3215,'Added from LORE','open',2,'IsImageOf',3156,0,0,'relation',0,0,3122,1,'2013-01-09 00:26:57',0,NULL),(3157,'IsVideoOf',3216,'Added from LORE','open',2,'IsVideoOf',3157,0,0,'relation',0,0,3122,1,'2013-01-09 00:26:57',0,NULL),(3158,'IsAudioOf',3214,'Added from LORE','open',2,'IsAudioOf',3158,0,0,'relation',0,0,3122,1,'2013-01-09 00:26:57',0,NULL),(3163,'IsParodyOf',3212,'Added from LORE','reserved',2,'IsParodyOf',3163,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3164,'IsPreviewOf',3213,'Added from LORE','reserved',2,'IsPreviewOf',3164,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3165,'IsLimitationOf',3211,'Added from LORE','reserved',2,'IsLimitationOf',3165,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3166,'IsCriticismOf',3208,'Added from LORE','reserved',2,'IsCriticismOf',3166,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3167,'IsAnnotationOn',3207,'Added from LORE','reserved',2,'IsAnnotationOn',3167,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3168,'IsDerivedFrom',3209,'Added from LORE','reserved',2,'IsDerivedFrom',3168,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3169,'IsExcerptFrom',3210,'Added from LORE','reserved',2,'IsExcerptFrom',3169,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3170,'has_location',0,'','reserved',2,'has_location',3170,0,0,'relation',0,0,3001,1,'2013-01-09 00:26:57',1,NULL),(3171,'is_realized_through',0,'','reserved',2,'is_realized_through',3171,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3172,'is_exemplification_of',0,'','reserved',2,'is_exemplification_of',3172,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3173,'is_embodiment',0,'','reserved',2,'is_embodiment',3173,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3177,'is_subject_of',0,'','reserved',2,'is_subject_of',3177,0,0,'relation',0,0,3258,1,'2013-01-09 00:26:57',1,NULL),(3188,'IsDaughterOf',3089,'','open',2,'IsDaughterOf',3188,0,0,'relation',0,0,3090,2,'2013-01-09 00:26:57',0,NULL),(3189,'IsSonOf',3089,'','open',2,'IsSonOf',3189,0,0,'relation',0,0,3090,2,'2013-01-09 00:26:57',0,NULL),(3207,'HasAnnotation',3167,'','reserved',2,'HasAnnotation',3207,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3208,'HasCriticism',3166,'','reserved',2,'HasCriticism',3208,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3209,'HasDerived',3168,'','reserved',2,'HasDerived',3209,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3210,'HasExcerpt',3169,'','reserved',2,'HasExcerpt',3210,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3211,'HasLimitation',3165,'','reserved',2,'HasLimitation',3211,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3212,'HasParody',3163,'','reserved',2,'HasParody',3212,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3213,'HasPreview',3164,'','reserved',2,'HasPreview',3213,0,0,'relation',0,0,3123,1,'2013-01-09 00:26:57',0,NULL),(3214,'HasAudio',3158,'','open',2,'HasAudio',3214,0,0,'relation',0,0,3122,1,'2013-01-09 00:26:57',0,NULL),(3215,'HasImage',3156,'','reserved',2,'HasImage',3215,0,0,'relation',0,0,3122,1,'2013-01-09 00:26:57',0,NULL),(3216,'HasVideo',3157,'','reserved',2,'HasVideo',3216,0,0,'relation',0,0,3122,1,'2013-01-09 00:26:57',0,NULL),(3218,'HasDramatization',3137,'','reserved',2,'HasDramatization',3218,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3219,'HasNovelization',3140,'','open',2,'HasNovelization',3219,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3220,'HasPerformance',3136,'','open',2,'HasPerformance',3220,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3221,'HasReconfiguration',3139,'','open',2,'HasReconfiguration',3221,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3222,'HasRepresentation',3135,'','open',2,'HasRepresentation',3222,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3223,'HasScreenplay',3138,'','open',2,'HasScreenplay',3223,0,0,'relation',0,0,3120,1,'2013-01-09 00:26:57',0,NULL),(3227,'HasAbridgedEdition',3149,'','reserved',2,'HasAbridgedEdition',3227,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3228,'HasAdaptation',3146,'','reserved',2,'HasAdaptation',3228,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3229,'HasAnnotatedEdition',3148,'','reserved',2,'HasAnnotatedEdition',3229,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3230,'HasCopy',3143,'','reserved',2,'HasCopy',3230,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3231,'HasDigest',3142,'','reserved',2,'HasDigest',3231,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3232,'HasEdition',3147,'','reserved',2,'HasEdition',3232,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3233,'HasExpurgatedEdition',3151,'','reserved',2,'HasExpurgatedEdition',3233,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3234,'HasIllustratedEdition',3150,'','reserved',2,'HasIllustratedEdition',3234,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3235,'HasRealization',3153,'','reserved',2,'HasRealization',3235,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3236,'HasReproduction',3152,'','reserved',2,'HasReproduction',3236,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3237,'HasRevision',3154,'','reserved',2,'HasRevision',3237,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3238,'HasSummary',3141,'','reserved',2,'HasSummary',3238,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3239,'HasTranscript',3144,'','reserved',2,'HasTranscript',3239,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3240,'HasTranslation',3145,'','reserved',2,'HasTranslation',3240,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3241,'HasVersion',3155,'','reserved',2,'HasVersion',3241,0,0,'relation',0,0,3121,1,'2013-01-09 00:26:57',0,NULL),(3242,'IsGrandSonOf',3115,'','open',2,'IsGrandSonOf',3242,0,0,'relation',0,0,3116,2,'2013-01-09 00:26:57',1,NULL),(3243,'IsGrandDaughterOf',3115,'','open',2,'IsGrandDaughterOf',3243,0,0,'relation',0,0,3116,2,'2013-01-09 00:26:57',1,NULL),(3244,'Tool Type',0,'Terms that represent a type of tool or action','open',0,'',0,0,0,'enum',0,0,0,1,'2013-01-09 00:26:57',0,NULL),(3245,'Annotation Type',0,'Type of annotation','open',0,'',0,0,0,'enum',0,0,3244,1,'2013-01-09 00:26:57',0,NULL),(3246,'Commentary',0,'Commentary subtypes','open',0,'',0,0,0,'enum',0,0,3245,1,'2013-01-09 00:26:57',0,NULL),(3247,'Comment',0,'Comment','open',0,'',0,0,0,'enum',0,0,3246,1,'2013-01-09 00:26:57',0,NULL),(3248,'Entity',0,'Entity subtype','open',0,'',0,0,0,'enum',0,0,3245,1,'2013-01-09 00:26:57',0,NULL),(3249,'Event',0,'Event','open',0,'',0,0,0,'enum',0,0,3248,1,'2013-01-09 00:26:57',0,NULL),(3250,'Person',0,'Person','open',0,'',0,0,0,'enum',0,0,3248,1,'2013-01-09 00:26:57',0,NULL),(3251,'Place',0,'Place','open',0,'',0,0,0,'enum',0,0,3248,1,'2013-01-09 00:26:57',0,NULL),(3252,'Literary Analysis',0,'Literary Analysis subtype','open',0,'',0,0,0,'enum',0,0,3245,1,'2013-01-09 00:26:57',0,NULL),(3253,'Interpretation',0,'Interpretation','open',0,'',0,0,0,'enum',0,0,3252,1,'2013-01-09 00:26:57',0,NULL),(3254,'Proof reading',0,'Proof reading subtype','open',0,'',0,0,0,'enum',0,0,3245,1,'2013-01-09 00:26:57',0,NULL),(3255,'Addition',0,'Addition','open',0,'',0,0,0,'enum',0,0,3254,1,'2013-01-09 00:26:57',0,NULL),(3256,'Change',0,'Change','open',0,'',0,0,0,'enum',0,0,3254,1,'2013-01-09 00:26:57',0,NULL),(3257,'Delete',0,'Delete','open',0,'',0,0,0,'enum',0,0,3254,1,'2013-01-09 00:26:57',0,NULL),(3258,'byMarriage',0,'','',0,'',0,0,0,'relation',0,0,3110,1,'2013-01-09 00:26:57',0,NULL),(3259,'Colateral relationships',0,'Your parents siblings and their offspring','',0,'',0,0,0,'relation',0,0,3110,1,'2013-01-09 00:26:57',0,NULL),(3260,'Ancestors',0,'','',0,'',0,0,0,'relation',0,0,3110,1,'2013-01-09 00:26:57',0,NULL),(3261,'Descendants',0,'Children and their children','',0,'',0,0,0,'relation',0,0,3110,1,'2013-01-09 00:26:57',0,NULL),(3262,'Contains',0,'','',0,'',0,0,0,'relation',0,0,3269,1,'2013-01-09 00:26:57',0,NULL),(3263,'IsContainedWithin',0,'','',0,'',0,0,0,'relation',0,0,3269,1,'2013-01-09 00:26:57',0,NULL),(3264,'IsCoterminousWith',0,'','',0,'',0,0,0,'relation',0,0,3269,1,'2013-01-09 00:26:57',0,NULL),(3265,'ImmediatelyPrecedes',0,'','',0,'',0,0,0,'relation',0,0,3270,1,'2013-01-09 00:26:57',0,NULL),(3266,'ImmediatelyFollows',0,'','',0,'',0,0,0,'relation',0,0,3270,1,'2013-01-09 00:26:57',0,NULL),(3267,'OverlapsStartOf',0,'','',0,'',0,0,0,'relation',0,0,3269,1,'2013-01-09 00:26:57',0,NULL),(3268,'OverlapsEndOf',0,'','',0,'',0,0,0,'relation',0,0,3269,1,'2013-01-09 00:26:57',0,NULL),(3269,'Overlap',0,'','',0,'',0,0,0,'relation',0,0,3113,1,'2013-01-09 00:26:57',0,NULL),(3270,'Sequence',0,'','',0,'',0,0,0,'relation',0,0,3113,1,'2013-01-09 00:26:57',0,NULL),(3271,'IsCauseBy',3005,'','',0,'',0,0,0,'relation',0,0,3113,1,'2013-01-09 00:26:57',0,NULL),(3272,'File types',0,'','',0,'',0,0,0,'enum',0,0,0,1,'2013-01-09 00:26:57',0,NULL),(3273,'Image',0,'','',0,'',0,0,0,'enum',0,0,3272,1,'2013-01-09 00:26:57',0,NULL),(3274,'Sound',0,'','',0,'',0,0,0,'enum',0,0,3272,1,'2013-01-09 00:26:57',0,NULL),(3275,'Video',0,'','',0,'',0,0,0,'enum',0,0,3272,1,'2013-01-09 00:26:57',0,NULL),(3276,'JPG',0,'','',0,'',0,0,0,'enum',0,0,3273,1,'2013-01-09 00:26:57',0,NULL),(3277,'GIF',0,'','',0,'',0,0,0,'enum',0,0,3273,1,'2013-01-09 00:26:57',0,NULL),(3278,'PNG',0,'','',0,'',0,0,0,'enum',0,0,3273,1,'2013-01-09 00:26:57',0,NULL),(3279,'TIF',0,'','',0,'',0,0,0,'enum',0,0,3273,1,'2013-01-09 00:26:57',0,NULL),(3280,'WAV',0,'','',0,'',0,0,0,'enum',0,0,3274,1,'2013-01-09 00:26:57',0,NULL),(3281,'MP3',0,'','',0,'',0,0,0,'enum',0,0,3274,1,'2013-01-09 00:26:57',0,NULL),(3282,'MPEG',0,'','',0,'',0,0,0,'enum',0,0,3275,1,'2013-01-09 00:26:57',0,NULL),(3283,'MP4',0,'','',0,'',0,0,0,'enum',0,0,3275,1,'2013-01-09 00:26:57',0,NULL),(3284,'QT',0,'','',0,'',0,0,0,'enum',0,0,3275,1,'2013-01-09 00:26:57',0,NULL),(3285,'Contributor type',NULL,'Contributor type','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-10 02:39:40',0,''),(3286,'author',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3285,1,'2013-01-10 02:41:26',0,''),(3287,'institution',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3285,1,'2013-01-10 02:41:33',0,''),(3288,'public',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3285,1,'2013-01-10 02:41:12',0,''),(3289,'supporter',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3285,1,'2013-01-10 02:41:19',0,''),(3290,'Entity type',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-10 02:51:06',0,''),(3291,'Artefact',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:51:30',0,''),(3292,'Artist',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:51:42',0,''),(3293,'Artwork',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:51:47',0,''),(3294,'Building',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:51:56',0,''),(3295,'Cloth',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:02',0,''),(3296,'Event',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:06',0,''),(3297,'Inscription',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:14',0,''),(3298,'Natural feature',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:23',0,''),(3299,'Object',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:28',0,''),(3300,'Organisation',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:36',0,''),(3301,'Person',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:40',0,''),(3302,'Place',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:44',0,''),(3303,'Rock art',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:49',0,''),(3304,'Statue',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:52:54',0,''),(3305,'Structure',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3290,1,'2013-01-10 02:53:00',0,''),(3306,'Factoid type',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-11 09:18:10',0,''),(3307,'Milestone',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:18:48',0,''),(3308,'Name',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:18:54',0,''),(3309,'Occupation',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:19:00',0,''),(3310,'Position',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:19:05',0,''),(3311,'Property',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:19:12',0,''),(3312,'Relationship',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:19:20',0,''),(3313,'TimePlace',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:19:26',0,''),(3314,'Type',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3306,1,'2013-01-11 09:19:31',0,''),(3315,'DOS Internal Status',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-11 09:27:38',0,''),(3316,'stage',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3315,1,'2013-01-11 09:27:47',0,''),(3317,'deploy',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3315,1,'2013-01-11 09:27:53',0,''),(3318,'Rights',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-11 09:46:05',0,''),(3319,'CC-Generic',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3318,1,'2013-01-11 09:46:23',0,''),(3320,'CC-SA',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3318,1,'2013-01-11 09:46:34',0,''),(3321,'Role type',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-11 09:47:54',0,''),(3322,'Milestone',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3321,1,'2013-01-11 09:48:58',0,''),(3323,'Name',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3321,1,'2013-01-11 09:49:04',0,''),(3324,'Occupation',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3321,1,'2013-01-11 09:49:09',0,''),(3325,'Property',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3321,1,'2013-01-11 09:49:14',0,''),(3326,'Relationship',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3321,1,'2013-01-11 09:49:18',0,''),(3327,'Type',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3321,1,'2013-01-11 09:49:23',0,''),(3328,'Annotation type',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,NULL,1,'2013-01-11 09:51:17',0,''),(3329,'video/x-flv',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,533,1,'2013-01-13 04:21:51',0,''),(3330,'audio/mpeg',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,533,1,'2013-01-13 04:22:16',0,''),(3331,'audio/mp3',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,533,1,'2013-01-13 04:22:35',0,''),(3332,'application/xml',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,533,1,'2013-01-13 04:22:48',0,''),(3333,'Annotation Multimedia',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3328,1,'2013-01-13 04:45:51',0,''),(3334,'Annotation Multimedia',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3328,1,'2013-01-13 04:45:51',0,''),(3335,'Annotation Entity',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3328,1,'2013-01-13 04:46:08',0,''),(3336,'Annotation Text',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3328,1,'2013-01-13 04:46:25',0,''),(3337,'Annotation Gloss',NULL,'','open',NULL,NULL,NULL,0,0,'enum',0,0,3328,1,'2013-01-13 04:46:40',0,''),(3338,'DOS relations',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,NULL,1,'2013-01-13 04:55:42',0,''),(3339,'HasBroaderTerm',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:56:00',0,''),(3340,'HasNarrowerTerm',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:56:12',0,''),(3341,'isAbout',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:56:23',0,''),(3342,'isOf',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:56:33',0,''),(3343,'hasSubject',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:56:45',0,''),(3344,'hasPrimarySubject',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:56:56',0,''),(3345,'IsRelatedTo',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:57:10',0,''),(3346,'hasExternalLink',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:57:24',0,''),(3347,'IsInMM',NULL,'','open',NULL,NULL,NULL,0,0,'relation',0,0,3338,1,'2013-01-13 04:57:36',0,'');
/*!40000 ALTER TABLE `defTerms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defDetailTypeGroups`
--

DROP TABLE IF EXISTS `defDetailTypeGroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `defDetailTypeGroups` (
  `dtg_ID` tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Primary ID - Code for detail type groups',
  `dtg_Name` varchar(63) NOT NULL COMMENT 'Descriptive heading to be displayed for each group of details (fields)',
  `dtg_Order` tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists',
  `dtg_Description` varchar(255) NOT NULL COMMENT 'General description fo this group of detail (field) types',
  `dtg_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (`dtg_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8 COMMENT='Groups detail types for display in separate sections of edit';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defDetailTypeGroups`
--

LOCK TABLES `defDetailTypeGroups` WRITE;
/*!40000 ALTER TABLE `defDetailTypeGroups` DISABLE KEYS */;
INSERT INTO `defDetailTypeGroups` VALUES (1,'Common fields',004,'The commonest field types shared across many record types','2013-01-10 02:42:53'),(8,'People / Organisations',006,'Field types used for people and organisations','2013-01-10 02:42:53'),(99,'System',011,'Field types used by the system - generally reserved','2013-01-10 02:42:54'),(101,'Location and time',008,'Fields for handling time, dates and geospatial data (including image types used for mapping)','2013-01-10 02:42:54'),(102,'Special applications',009,'Fields used by specialised functions such as annotation and transforms','2013-01-10 02:42:54'),(104,'User-defined',002,'Base field types defined by users of this database - you can rename this tab, create additional tabs and move base field types between tabs','2013-01-10 02:42:53'),(105,'File Metadata',010,'File metadata used in processing FieldHelper manifests','2013-01-10 02:42:54'),(106,'Image handling',005,'Field types used by tiled images and other image handling functions','2013-01-10 02:42:53'),(107,'Annotation',003,'Tools and functions associated with annotation','2013-01-10 02:42:53'),(108,'General fields',001,'The commonest field types shared across many record types','2013-01-10 02:42:53'),(109,'Heurist internals',007,'Fields used by specialised functions such as annotation and transforms','2013-01-10 02:42:53'),(110,'Imported',012,'This group contains all detailtypes that were imported from external databases','2013-01-10 02:42:54'),(111,'DOS',000,'DOS fields','2013-01-10 02:42:53');
/*!40000 ALTER TABLE `defDetailTypeGroups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usrSavedSearches`
--

DROP TABLE IF EXISTS `usrSavedSearches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usrTags`
--

LOCK TABLES `usrTags` WRITE;
/*!40000 ALTER TABLE `usrTags` DISABLE KEYS */;
/*!40000 ALTER TABLE `usrTags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'hdb_dos_0'
--
/*!50003 DROP FUNCTION IF EXISTS `getTemporalDateString` */;
--
-- WARNING: old server version. The following dump may be incomplete.
--
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
/*!50003 DROP FUNCTION IF EXISTS `hhash` */;
--
-- WARNING: old server version. The following dump may be incomplete.
--
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 DROP FUNCTION IF EXISTS `simple_hash` */;
--
-- WARNING: old server version. The following dump may be incomplete.
--
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
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 DROP PROCEDURE IF EXISTS `set_all_hhash` */;
--
-- WARNING: old server version. The following dump may be incomplete.
--
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-04-02 16:25:51
