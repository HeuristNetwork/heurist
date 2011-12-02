 -- buildExampleDB.sql: SQL file to create an example Heurist database as a starter/seed database
 -- @author Ian Johnson 11/1/2011
 -- @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 -- @link: http://HeuristScholar.org
 -- @license http://www.gnu.org/licenses/gpl-3.0.txt
 -- @package Heurist academic knowledge management system
 -- @todo

\W -- warnings to standard out, OK for cammand line but not for phpMyAdmin

-- --------------------------------------------------------

-- The rest of this file is a MySQLDump dump of hdb_H3ExampleDB
-- Login is guest+guest

-- ---------------------------------------------------------

-- MySQL dump 10.11
--
-- Host: localhost    Database: hdb_H3ExampleDB
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
  CONSTRAINT `fk_rec_RecTypeID` FOREIGN KEY (`rec_RecTypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rec_AddedByUGrpID` FOREIGN KEY (`rec_AddedByUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rec_OwnerUGrpID` FOREIGN KEY (`rec_OwnerUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON UPDATE CASCADE
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

				where dtl_DetailTypeID=4 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
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
INSERT INTO `defDetailTypeGroups` VALUES (1,'Common fields',001,'The commonest details (fields) shared across many record types','2011-10-12 09:05:18'),(8,'Organisational',002,'','2011-10-12 09:05:18'),(99,'System',002,'','2011-10-12 09:05:18');
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
  `dty_Type` enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker') NOT NULL COMMENT 'The value-type of this detail type, what sort of data is stored',
  `dty_HelpText` varchar(255) NOT NULL default 'Please provide a short explanation for the user ...' COMMENT 'The default help text displayed to the user under the field',
  `dty_ExtendedDescription` varchar(5000) default 'Please provide an extended description for display on rollover ...' COMMENT 'Extended text describing this detail type, for display in rollover',
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
) ENGINE=InnoDB AUTO_INCREMENT=312 DEFAULT CHARSET=utf8 COMMENT='The detail types (fields) which can be attached to records';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defDetailTypes`
--

LOCK TABLES `defDetailTypes` WRITE;
/*!40000 ALTER TABLE `defDetailTypes` DISABLE KEYS */;
INSERT INTO `defDetailTypes` VALUES (1,'Name or title',' ','freetext','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','','reserved',2,'Name or title',1,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(2,'Start date','','date','Start Date','','reserved',2,'Start date',2,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(3,'End date','','date','End Date','','reserved',2,'End date',3,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(4,'Target record pointer','Unconstrained pointer to the target linked record for a relationship.','resource','The target record in a relationship record or other record referencing anotehr record with an unconstrained pointer','','reserved',2,'Target record pointer',4,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(5,'Relationship type','','relationtype','RelationType','','reserved',2,'Relationship type',5,99,0,'{\"3001\":{\"3108\":{}},\"3406\":{\"3083\":{},\"3084\":{},\"3087\":{},\"3088\":{},\"3089\":{},\"3090\":{},\"3092\":{},\"3094\":{},\"3095\":{},\"3099\":{},\"3100\":{},\"3103\":{},\"3104\":{},\"3105\":{}},\"3407\":{\"3006\":{},\"3009\":{},\"3013\":{},\"3014\":{},\"3015\":{},\"3016\":{},\"3017\":{},\"3018\":{},\"3025\":{},\"3026\":{},\"3027\":{},\"3028\":{},\"3029\":{},\"3030\":{},\"3031\":{},\"3032\":{},\"3033\":{},\"3034\":{},\"3041\":{},\"3042\":{},\"3043\":{},\"3044\":{},\"3045\":{},\"3046\":{},\"3047\":{},\"3048\":{},\"3049\":{},\"3050\":{},\"3051\":{},\"3052\":{},\"3055\":{},\"3056\":{},\"3059\":{},\"3060\":{},\"3070\":{},\"3071\":{}},\"3408\":{\"3004\":{},\"3021\":{},\"3022\":{},\"3039\":{},\"3040\":{},\"3053\":{},\"3054\":{},\"3067\":{},\"3072\":{},\"3073\":{},\"3074\":{},\"3075\":{},\"3076\":{},\"3077\":{},\"3078\":{},\"3079\":{},\"3106\":{},\"3107\":{}},\"3409\":{\"3005\":{},\"3011\":{},\"3012\":{},\"3091\":{}},\"3410\":{\"3019\":{},\"3020\":{},\"3023\":{},\"3024\":{},\"3035\":{},\"3036\":{},\"3037\":{},\"3038\":{},\"3057\":{},\"3058\":{},\"3061\":{},\"3062\":{},\"3063\":{},\"3064\":{},\"3065\":{},\"3066\":{},\"3068\":{},\"3069\":{},\"3101\":{},\"3102\":{}}}','[\"3001\",\"3406\",\"3407\",\"3408\",\"3409\",\"3410\"]','',0,1,'viewable','2011-10-12 09:05:19',0),(7,'Source record pointer','Unconstrained pointer to the source linked record for a relationship.','resource','Pointer to the record which is being linked to a target record (often by a relationship record)','','reserved',2,'Source record pointer',7,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(8,'File resource','','file','An uploaded file or a reference to a file through a URI ','','reserved',2,'File resource',8,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(9,'Thumbnail image','','file','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','','reserved',2,'Thumbnail image',9,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(11,'Geospatial object','','geo','A geospatial object providing a mappable location for the record','','reserved',2,'Geospatial object',11,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(12,'Short summary','','blocktext','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','','reserved',2,'Short summary',12,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(13,'Pointer to commentary','','resource','A pointer to a commentary/interpretation record (for spatial, temporal or general information)','','approved',2,'Pointer to commentary',13,99,0,'','','10',0,1,'viewable','2011-10-12 09:05:19',0),(16,'Date','','date','Enter a date either as a simple calendar date or though the temporal object popup (for complex/uncertain dates)','','reserved',2,'Date',16,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(19,'Creator (author etc.)','','resource','The person or organisation who created the resource','May include authors, artists, organisations that sponsored a resource etc.','reserved',2,'Creator (author etc.)',19,1,0,'','','4,20',0,1,'viewable','2011-10-12 09:05:19',0),(23,'Pointer to Organisation','','resource','A pointer field which is constrained to point to an Organisation record','','approved',2,'Organisation Reference',23,8,0,'','','4',0,1,'viewable','2011-10-12 09:05:19',0),(26,'Honorific','','enum','Title or honorific (Dr, Mrs, Ms, Professor, Sir etc.)','','approved',2,'Honorific',26,8,0,'{\"3263\":{\"152\":{},\"153\":{},\"154\":{},\"155\":{},\"156\":{},\"157\":{}}}','[\"3263\"]','',0,1,'viewable','2011-10-12 09:05:19',0),(32,'Contact details or URL','','freetext','Contact details, either as text or a URL to eg. a phonebook record or search','','approved',2,'Contact details or URL',32,8,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(33,'Given names','','freetext','Given names','','approved',2,'Given names',33,8,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(34,'Birth date','','date','Birth date','','approved',2,'Birth date',34,8,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(39,'Gender','','enum','Gender','','approved',2,'Gender',39,8,0,'{\"3400\":{\"629\":{},\"630\":{},\"3437\":{},\"3438\":{}}}','[\"3400\"]','',0,1,'viewable','2011-10-12 09:05:19',0),(46,'Unique public identifier','','freetext','A public identifier such as the NLA party identifier (Australia) or other national/international system','','approved',2,'Unique public identifier',46,8,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(56,'Pointer to Person','','resource','Pointers to person','A pointer field constrained to point to a Person record','approved',2,'Person Reference',56,8,0,'','','20',0,1,'viewable','2011-10-12 09:05:19',0),(78,'Transcriber of interpretation','','resource','Pointer to the person who transcribes or summarises interpretation information ','','approved',2,'Transcriber of interpretation',78,99,0,'','','4,20',0,1,'viewable','2011-10-12 09:05:19',0),(79,'Resource Identifier','','resource','Resource Identifier (unconstrained record pointer)','','approved',2,'Resource Identifier',79,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(80,'Email address','','freetext','Email address','','reserved',2,'Email address',80,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(82,'Email of sender','','freetext','Email of sender','','reserved',2,'Email of sender',82,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(83,'Email of recipients','','freetext','Email of recipients','','reserved',2,'Email of recipients',83,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(105,'Country','','enum','Country','','reserved',2,'Country',105,1,0,'{\"3278\":{\"170\":{},\"171\":{},\"172\":{},\"173\":{},\"174\":{},\"176\":{},\"177\":{},\"178\":{},\"180\":{},\"181\":{},\"183\":{},\"184\":{},\"289\":{},\"290\":{},\"291\":{},\"292\":{},\"293\":{},\"294\":{},\"295\":{},\"296\":{},\"297\":{},\"298\":{},\"299\":{},\"300\":{},\"301\":{},\"302\":{},\"308\":{},\"310\":{},\"311\":{},\"313\":{},\"321\":{},\"322\":{},\"323\":{},\"324\":{},\"325\":{},\"327\":{},\"336\":{},\"346\":{},\"394\":{},\"395\":{},\"396\":{},\"397\":{},\"398\":{},\"399\":{},\"400\":{},\"401\":{},\"402\":{},\"403\":{},\"404\":{},\"405\":{},\"406\":{},\"407\":{},\"408\":{},\"409\":{},\"410\":{},\"411\":{},\"412\":{},\"413\":{},\"414\":{},\"415\":{},\"416\":{},\"417\":{},\"418\":{},\"419\":{},\"420\":{},\"421\":{},\"422\":{},\"423\":{},\"424\":{},\"425\":{},\"426\":{},\"427\":{},\"428\":{},\"429\":{},\"430\":{},\"431\":{},\"432\":{},\"433\":{},\"434\":{},\"435\":{},\"436\":{},\"437\":{},\"438\":{},\"439\":{},\"440\":{},\"441\":{},\"442\":{},\"443\":{},\"444\":{},\"445\":{},\"446\":{},\"447\":{},\"448\":{},\"449\":{},\"450\":{},\"451\":{},\"452\":{},\"453\":{},\"454\":{},\"455\":{},\"456\":{},\"457\":{},\"458\":{},\"459\":{},\"460\":{},\"461\":{},\"462\":{},\"463\":{},\"464\":{},\"465\":{},\"466\":{},\"467\":{},\"468\":{},\"469\":{},\"470\":{},\"471\":{},\"472\":{},\"473\":{},\"474\":{},\"475\":{},\"476\":{},\"477\":{},\"478\":{},\"479\":{},\"480\":{},\"481\":{},\"482\":{},\"483\":{},\"484\":{},\"485\":{},\"486\":{},\"487\":{},\"488\":{},\"489\":{},\"490\":{},\"491\":{},\"492\":{},\"493\":{},\"494\":{},\"495\":{},\"496\":{},\"497\":{},\"498\":{},\"499\":{},\"500\":{},\"501\":{},\"502\":{},\"503\":{},\"504\":{},\"505\":{},\"506\":{},\"507\":{},\"508\":{},\"509\":{},\"510\":{},\"511\":{},\"512\":{},\"513\":{},\"514\":{},\"515\":{},\"516\":{},\"517\":{},\"518\":{},\"519\":{},\"520\":{},\"521\":{},\"522\":{},\"523\":{},\"524\":{},\"525\":{},\"526\":{},\"527\":{},\"528\":{},\"529\":{},\"530\":{},\"531\":{},\"532\":{},\"533\":{},\"534\":{},\"535\":{},\"536\":{},\"537\":{},\"538\":{},\"539\":{},\"540\":{},\"541\":{},\"542\":{},\"543\":{},\"544\":{},\"545\":{},\"546\":{},\"547\":{},\"548\":{},\"549\":{},\"550\":{},\"551\":{},\"552\":{},\"553\":{},\"554\":{},\"555\":{},\"556\":{},\"557\":{},\"558\":{},\"559\":{},\"560\":{},\"561\":{},\"562\":{},\"563\":{},\"564\":{},\"565\":{},\"566\":{},\"567\":{},\"568\":{},\"569\":{},\"570\":{},\"571\":{},\"572\":{},\"573\":{},\"574\":{},\"575\":{},\"576\":{},\"577\":{},\"578\":{},\"579\":{},\"580\":{},\"581\":{},\"582\":{},\"583\":{},\"584\":{},\"585\":{},\"586\":{},\"587\":{},\"588\":{},\"589\":{},\"590\":{},\"591\":{},\"592\":{},\"593\":{},\"594\":{},\"595\":{},\"596\":{},\"597\":{},\"598\":{},\"599\":{},\"600\":{},\"601\":{},\"602\":{}}}','[\"3278\"]','',0,1,'viewable','2011-10-12 09:05:19',0),(179,'Short Name','Please document the nature of this detail type (field)) ...','freetext','Short Name or acronym','','open',2,'Name',179,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0),(203,'Organisation type','Please document the nature of this detail type (field)) ...','enum','Organisation type','','open',2,'Organisation type',203,1,0,'{\"17\":{},\"212\":{},\"210\":{},\"14\":{},\"15\":{},\"19\":{},\"209\":{},\"18\":{},\"12\":{},\"208\":{},\"20\":{},\"16\":{},\"21\":{},\"13\":{},\"211\":{},\"213\":{},\"11\":{\"3415\":{},\"3416\":{}}}','[\"11\"]','',0,1,'viewable','2011-10-12 09:05:19',0),(311,'Copyright information','Please document the nature of this detail type (field)) ...','blocktext','Copyright information','','reserved',2,'Copyright information',311,1,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0);
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
INSERT INTO `defFileExtToMimetype` VALUES ('16','',0,'','','','2011-10-12 09:05:19'),('183','',0,'','','','2011-10-12 09:05:19'),('ai','application/postscript',0,'','','','2011-10-12 09:05:19'),('aif','audio/x-aiff',0,'movie.gif','AIFF audio','','2011-10-12 09:05:19'),('aifc','audio/x-aiff',0,'movie.gif','AIFF audio','','2011-10-12 09:05:19'),('aiff','audio/x-aiff',0,'movie.gif','AIFF audio','','2011-10-12 09:05:19'),('ama','',0,'','','','2011-10-12 09:05:19'),('asc','text/plain',1,'txt.gif','Plain text','','2011-10-12 09:05:19'),('au','audio/basic',0,'movie.gif','AU audio','','2011-10-12 09:05:19'),('avi','video/x-msvideo',0,'movie.gif','AVI video','','2011-10-12 09:05:19'),('bcpio','application/x-bcpio',0,'','','','2011-10-12 09:05:19'),('bin','application/octet-stream',0,'','BinHex','','2011-10-12 09:05:19'),('bmp','image/bmp',1,'image.gif','BMP image','','2011-10-12 09:05:19'),('c','',0,'','','','2011-10-12 09:05:19'),('cda','',0,'','','','2011-10-12 09:05:19'),('cdf','application/x-netcdf',0,'','','','2011-10-12 09:05:19'),('cgm','image/cgm',0,'image.gif','','','2011-10-12 09:05:19'),('class','application/octet-stream',0,'','Java','','2011-10-12 09:05:19'),('cpio','application/x-cpio',0,'','','','2011-10-12 09:05:19'),('cpt','application/mac-compactpro',0,'','','','2011-10-12 09:05:19'),('csh','application/x-csh',0,'','','','2011-10-12 09:05:19'),('css','text/css',1,'','CSS stylesheet','','2011-10-12 09:05:19'),('csv','',0,'','','','2011-10-12 09:05:19'),('dat','',0,'','','','2011-10-12 09:05:19'),('db','',0,'','','','2011-10-12 09:05:19'),('dcr','application/x-director',0,'','','','2011-10-12 09:05:19'),('dir','application/x-director',0,'','','','2011-10-12 09:05:19'),('djv','image/vnd.djvu',1,'image.gif','','','2011-10-12 09:05:19'),('djvu','image/vnd.djvu',1,'image.gif','','','2011-10-12 09:05:19'),('dll','application/octet-stream',0,'','Windows system','','2011-10-12 09:05:19'),('dmg','application/octet-stream',0,'','','','2011-10-12 09:05:19'),('dms','application/octet-stream',0,'','','','2011-10-12 09:05:19'),('doc','application/msword',0,'doc.gif','MS Word','','2011-10-12 09:05:19'),('docx','',0,'','','','2011-10-12 09:05:19'),('dot','',0,'','','','2011-10-12 09:05:19'),('Dr_Elaine_','',0,'','','','2011-10-12 09:05:19'),('dtd','application/xml-dtd',1,'','','','2011-10-12 09:05:19'),('dvi','application/x-dvi',0,'','','','2011-10-12 09:05:19'),('dxr','application/x-director',0,'','','','2011-10-12 09:05:19'),('eml','',0,'','','','2011-10-12 09:05:19'),('enz','',0,'','','','2011-10-12 09:05:19'),('eps','application/postscript',0,'','PostScript','','2011-10-12 09:05:19'),('etx','text/x-setext',0,'','','','2011-10-12 09:05:19'),('exe','application/octet-stream',0,'','Windows executable','','2011-10-12 09:05:19'),('ez','application/andrew-inset',0,'','','','2011-10-12 09:05:19'),('flv','',0,'','','','2011-10-12 09:05:19'),('gif','image/gif',1,'image.gif','GIF image','','2011-10-12 09:05:19'),('gra','',0,'','','','2011-10-12 09:05:19'),('gram','application/srgs',0,'','','','2011-10-12 09:05:19'),('grxml','application/srgs+xml',1,'','','','2011-10-12 09:05:19'),('gtar','application/x-gtar',0,'','','','2011-10-12 09:05:19'),('hdf','application/x-hdf',0,'','','','2011-10-12 09:05:19'),('hqx','application/mac-binhex40',0,'','BinHex compressed','','2011-10-12 09:05:19'),('htm','text/html',1,'html.gif','HTML source','','2011-10-12 09:05:19'),('html','text/html',1,'html.gif','HTML source','','2011-10-12 09:05:19'),('ice','x-conference/x-cooltalk',0,'','','','2011-10-12 09:05:19'),('ico','image/x-icon',0,'image.gif','','','2011-10-12 09:05:19'),('ics','text/calendar',0,'','','','2011-10-12 09:05:19'),('ief','image/ief',0,'image.gif','','','2011-10-12 09:05:19'),('ifb','text/calendar',0,'','','','2011-10-12 09:05:19'),('iges','model/iges',0,'','','','2011-10-12 09:05:19'),('igs','model/iges',0,'','','','2011-10-12 09:05:19'),('ini','',0,'','','','2011-10-12 09:05:19'),('jar','',0,'','','','2011-10-12 09:05:19'),('jpe','image/jpeg',1,'image.gif','JPEG image','','2011-10-12 09:05:19'),('jpeg','image/jpeg',1,'image.gif','JPEG image','','2011-10-12 09:05:19'),('jpg','image/jpeg',1,'image.gif','JPEG image','','2011-10-12 09:05:19'),('js','application/x-javascript',0,'','JavaScript','','2011-10-12 09:05:19'),('kar','audio/midi',0,'movie.gif','','','2011-10-12 09:05:19'),('kml','',0,'','','','2011-10-12 09:05:19'),('kmz','',0,'','','','2011-10-12 09:05:19'),('latex','application/x-latex',0,'','LaTeX source','','2011-10-12 09:05:19'),('lha','application/octet-stream',0,'','LHA compressed','','2011-10-12 09:05:19'),('lnk','',0,'','','','2011-10-12 09:05:19'),('lzh','application/octet-stream',0,'','LZH compressed','','2011-10-12 09:05:19'),('m3u','audio/x-mpegurl',0,'movie.gif','','','2011-10-12 09:05:19'),('m4u','video/vnd.mpegurl',0,'movie.gif','','','2011-10-12 09:05:19'),('man','application/x-troff-man',0,'','','','2011-10-12 09:05:19'),('mathml','application/mathml+xml',1,'','MathML','','2011-10-12 09:05:19'),('me','application/x-troff-me',0,'','','','2011-10-12 09:05:19'),('mesh','model/mesh',0,'','','','2011-10-12 09:05:19'),('mid','audio/midi',0,'movie.gif','MIDI audio','','2011-10-12 09:05:19'),('midi','audio/midi',0,'movie.gif','MIDI audio','','2011-10-12 09:05:19'),('mif','application/vnd.mif',0,'','','','2011-10-12 09:05:19'),('mov','video/quicktime',0,'movie.gif','QuickTime video','','2011-10-12 09:05:19'),('movie','video/x-sgi-movie',0,'movie.gif','','','2011-10-12 09:05:19'),('mp2','audio/mpeg',0,'movie.gif','MPEG audio','','2011-10-12 09:05:19'),('mp3','audio/mpeg',0,'movie.gif','MP3 audio','','2011-10-12 09:05:19'),('mpe','video/mpeg',0,'movie.gif','MPEG video','','2011-10-12 09:05:19'),('mpeg','video/mpeg',0,'movie.gif','MPEG video','','2011-10-12 09:05:19'),('mpg','video/mpeg',0,'movie.gif','MPEG video','','2011-10-12 09:05:19'),('mpga','audio/mpeg',0,'movie.gif','MPEG audio','','2011-10-12 09:05:19'),('ms','application/x-troff-ms',0,'','','','2011-10-12 09:05:19'),('msh','model/mesh',0,'','','','2011-10-12 09:05:19'),('mxd','',0,'','','','2011-10-12 09:05:19'),('mxu','video/vnd.mpegurl',0,'movie.gif','','','2011-10-12 09:05:19'),('nc','application/x-netcdf',0,'','','','2011-10-12 09:05:19'),('oda','application/oda',0,'','','','2011-10-12 09:05:19'),('odt','',0,'','','','2011-10-12 09:05:19'),('ogg','application/ogg',0,'','Ogg Vorbis','','2011-10-12 09:05:19'),('one','',0,'','','','2011-10-12 09:05:19'),('owl','',0,'','','','2011-10-12 09:05:19'),('ozf2','',0,'','','','2011-10-12 09:05:19'),('pbm','image/x-portable-bitmap',1,'image.gif','','','2011-10-12 09:05:19'),('pdb','chemical/x-pdb',0,'','','','2011-10-12 09:05:19'),('pdf','application/pdf',1,'pdf.gif','Adobe Acrobat','','2011-10-12 09:05:19'),('pgm','image/x-portable-graymap',1,'image.gif','','','2011-10-12 09:05:19'),('pgn','application/x-chess-pgn',0,'','','','2011-10-12 09:05:19'),('Photos Mas','',0,'','','','2011-10-12 09:05:19'),('pl','',0,'','','','2011-10-12 09:05:19'),('png','image/png',1,'image.gif','PNG image','','2011-10-12 09:05:19'),('pnm','image/x-portable-anymap',1,'image.gif','','','2011-10-12 09:05:19'),('ppm','image/x-portable-pixmap',1,'image.gif','','','2011-10-12 09:05:19'),('pps','',0,'','','','2011-10-12 09:05:19'),('ppt','application/vnd.ms-powerpoint',0,'','MS Powerpoint','','2011-10-12 09:05:19'),('pptx','',0,'','','','2011-10-12 09:05:19'),('ps','application/postscript',0,'','PostScript','','2011-10-12 09:05:19'),('psd','',0,'','','','2011-10-12 09:05:19'),('pyramid to','',0,'','','','2011-10-12 09:05:19'),('qt','video/quicktime',0,'movie.gif','QuickTime video','','2011-10-12 09:05:19'),('ra','audio/x-pn-realaudio',0,'movie.gif','RealAudio','','2011-10-12 09:05:19'),('ram','audio/x-pn-realaudio',0,'movie.gif','RealAudio','','2011-10-12 09:05:19'),('ras','image/x-cmu-raster',1,'image.gif','','','2011-10-12 09:05:19'),('rdf','application/rdf+xml',1,'','Mozilla extension','','2011-10-12 09:05:19'),('References','',0,'','','','2011-10-12 09:05:19'),('rgb','image/x-rgb',0,'image.gif','','','2011-10-12 09:05:19'),('rm','application/vnd.rn-realmedia',0,'','RealAudio','','2011-10-12 09:05:19'),('roff','application/x-troff',0,'','','','2011-10-12 09:05:19'),('rtf','text/rtf',1,'','Rich text','','2011-10-12 09:05:19'),('rtx','text/richtext',1,'','','','2011-10-12 09:05:19'),('sgm','text/sgml',1,'','SGML source','','2011-10-12 09:05:19'),('sgml','text/sgml',1,'','SGML source','','2011-10-12 09:05:19'),('sh','application/x-sh',0,'','','','2011-10-12 09:05:19'),('shar','application/x-shar',0,'','','','2011-10-12 09:05:19'),('shp','',0,'','','','2011-10-12 09:05:19'),('shs','',0,'','','','2011-10-12 09:05:19'),('silo','model/mesh',0,'','','','2011-10-12 09:05:19'),('sit','application/x-stuffit',0,'','StuffIt compressed','','2011-10-12 09:05:19'),('skd','application/x-koan',0,'','','','2011-10-12 09:05:19'),('skm','application/x-koan',0,'','','','2011-10-12 09:05:19'),('skp','application/x-koan',0,'','','','2011-10-12 09:05:19'),('skt','application/x-koan',0,'','','','2011-10-12 09:05:19'),('smi','application/smil',0,'','','','2011-10-12 09:05:19'),('smil','application/smil',0,'','','','2011-10-12 09:05:19'),('snd','audio/basic',0,'movie.gif','SND audio','','2011-10-12 09:05:19'),('so','application/octet-stream',0,'','','','2011-10-12 09:05:19'),('spl','application/x-futuresplash',0,'','','','2011-10-12 09:05:19'),('sql','',0,'','','','2011-10-12 09:05:19'),('src','application/x-wais-source',0,'','','','2011-10-12 09:05:19'),('sv4cpio','application/x-sv4cpio',0,'','','','2011-10-12 09:05:19'),('sv4crc','application/x-sv4crc',0,'','','','2011-10-12 09:05:19'),('svg','image/svg+xml',1,'image.gif','SVG graphics','','2011-10-12 09:05:19'),('swf','application/x-shockwave-flash',0,'','Adobe Shockwave','','2011-10-12 09:05:19'),('t','application/x-troff',0,'','','','2011-10-12 09:05:19'),('tar','application/x-tar',0,'','TAR archive','','2011-10-12 09:05:19'),('tcl','application/x-tcl',0,'','','','2011-10-12 09:05:19'),('Teotihuaca','',0,'','','','2011-10-12 09:05:19'),('tex','application/x-tex',0,'','TeX source','','2011-10-12 09:05:19'),('texi','application/x-texinfo',0,'','','','2011-10-12 09:05:19'),('texinfo','application/x-texinfo',0,'','','','2011-10-12 09:05:19'),('the rocks ','',0,'','','','2011-10-12 09:05:19'),('tif','image/tiff',0,'image.gif','TIFF image','','2011-10-12 09:05:19'),('tiff','image/tiff',0,'image.gif','TIFF image','','2011-10-12 09:05:19'),('tr','application/x-troff',0,'','','','2011-10-12 09:05:19'),('tsv','text/tab-separated-values',1,'','','','2011-10-12 09:05:19'),('ttf','',0,'','','','2011-10-12 09:05:19'),('txt','text/plain',1,'txt.gif','Plain text','','2011-10-12 09:05:19'),('ustar','application/x-ustar',0,'','','','2011-10-12 09:05:19'),('vcd','application/x-cdlink',0,'','','','2011-10-12 09:05:19'),('VOB','',0,'','','','2011-10-12 09:05:19'),('vrml','model/vrml',1,'','','','2011-10-12 09:05:19'),('vsd','',0,'','','','2011-10-12 09:05:19'),('vxml','application/voicexml+xml',1,'','','','2011-10-12 09:05:19'),('wav','audio/x-wav',0,'movie.gif','WAV audio','','2011-10-12 09:05:19'),('wbmp','image/vnd.wap.wbmp',1,'image.gif','','','2011-10-12 09:05:19'),('wbxml','application/vnd.wap.wbxml',1,'','','','2011-10-12 09:05:19'),('wma','',0,'','','','2011-10-12 09:05:19'),('WMF','',0,'','','','2011-10-12 09:05:19'),('wml','text/vnd.wap.wml',0,'','','','2011-10-12 09:05:19'),('wmlc','application/vnd.wap.wmlc',0,'','','','2011-10-12 09:05:19'),('wmls','text/vnd.wap.wmlscript',0,'','','','2011-10-12 09:05:19'),('wmlsc','application/vnd.wap.wmlscriptc',0,'','','','2011-10-12 09:05:19'),('wmv','',0,'','','','2011-10-12 09:05:19'),('wrl','model/vrml',1,'','','','2011-10-12 09:05:19'),('xbm','image/x-xbitmap',0,'image.gif','','','2011-10-12 09:05:19'),('xht','application/xhtml+xml',1,'','','','2011-10-12 09:05:19'),('xhtml','application/xhtml+xml',1,'','XHTML','','2011-10-12 09:05:19'),('xls','application/vnd.ms-excel',0,'','MS Excel spreadsheet','','2011-10-12 09:05:19'),('xlsx','',0,'','','','2011-10-12 09:05:19'),('xml','application/xml',1,'','XML','','2011-10-12 09:05:19'),('xpm','image/x-xpixmap',0,'image.gif','','','2011-10-12 09:05:19'),('xsl','application/xml',1,'','XSL stylesheet','','2011-10-12 09:05:19'),('xslt','application/xslt+xml',1,'','','','2011-10-12 09:05:19'),('xul','application/vnd.mozilla.xul+xml',1,'','','','2011-10-12 09:05:19'),('xwd','image/x-xwindowdump',0,'image.gif','','','2011-10-12 09:05:19'),('xyz','chemical/x-xyz',0,'','','','2011-10-12 09:05:19'),('zip','application/zip',0,'zip.gif','ZIP compressed','','2011-10-12 09:05:19');
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
INSERT INTO `defLanguages` VALUES ('ARA','AR','Arabic',NULL,'2011-10-12 09:05:14'),('CAM','KM','Khmer',NULL,'2011-10-12 09:05:14'),('CHI','ZH','Chinese',NULL,'2011-10-12 09:05:14'),('CZE','CS','Czech',NULL,'2011-10-12 09:05:14'),('DAN','DA','Danish',NULL,'2011-10-12 09:05:14'),('DUT','NL','Dutch',NULL,'2011-10-12 09:05:14'),('ENG','EN','English',NULL,'2011-10-12 09:05:14'),('EST','ET','Estonian',NULL,'2011-10-12 09:05:14'),('FIN','FI','Finish',NULL,'2011-10-12 09:05:14'),('FRE','FR','French',NULL,'2011-10-12 09:05:14'),('GER','DE','German',NULL,'2011-10-12 09:05:14'),('GRE','EL','Greek',NULL,'2011-10-12 09:05:14'),('HEB','HE','Hebrew',NULL,'2011-10-12 09:05:14'),('HIN','HI','Hindi',NULL,'2011-10-12 09:05:14'),('HUN','HU','Hungarian',NULL,'2011-10-12 09:05:14'),('IND','ID','Indonesian',NULL,'2011-10-12 09:05:14'),('ITA','IT','Italian',NULL,'2011-10-12 09:05:14'),('JPN','JA','Japanese',NULL,'2011-10-12 09:05:14'),('KOR','KO','Korean',NULL,'2011-10-12 09:05:14'),('LAV','LV','Latvian',NULL,'2011-10-12 09:05:14'),('MAL','MS','Malay',NULL,'2011-10-12 09:05:14'),('NOR','NO','Norwegian',NULL,'2011-10-12 09:05:14'),('POL','PL','Polish',NULL,'2011-10-12 09:05:14'),('POR','PT','Portuguese',NULL,'2011-10-12 09:05:14'),('RUS','RU','Russian',NULL,'2011-10-12 09:05:14'),('SCC','HR','Croatian',NULL,'2011-10-12 09:05:14'),('SCR','SR','Serbian',NULL,'2011-10-12 09:05:14'),('SLO','SK','Slovak',NULL,'2011-10-12 09:05:14'),('SPA','ES','Spanish',NULL,'2011-10-12 09:05:14'),('SWA','SW','Swahili',NULL,'2011-10-12 09:05:14'),('SWE','SV','Swedish',NULL,'2011-10-12 09:05:14'),('THA','TH','Thai',NULL,'2011-10-12 09:05:14'),('TUR','TR','Turkish',NULL,'2011-10-12 09:05:14'),('UKR','UK','Ukranian',NULL,'2011-10-12 09:05:14'),('VIE','VI','Vietnamese',NULL,'2011-10-12 09:05:14'),('YID','YI','Yiddish',NULL,'2011-10-12 09:05:14');
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
INSERT INTO `defOntologies` VALUES (1,'local','Null ontology','An empty ontology which can be complemented','','open',2,'Null ontology',1,255,'2011-10-12 09:05:18',0),(2,'DC','Dublin Core','','http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=52142','open',2,'Dublin Core',2,255,'2011-10-12 09:05:18',0),(3,'CIDOC-CRM','CIDOC-CRM','','http://www.iso.org/iso/iso_catalogue/catalogue_tc/catalogue_detail.htm?csnumber=34424','open',2,'CIDOC-CRM',3,255,'2011-10-12 09:05:18',0);
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
  `rst_MaxValues` tinyint(3) unsigned NOT NULL default '0' COMMENT 'Maximum number of values per record for this detail, 0 = unlimited',
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
  CONSTRAINT `fk_rst_RecTypeID` FOREIGN KEY (`rst_RecTypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rst_DetailtypeID` FOREIGN KEY (`rst_DetailTypeID`) REFERENCES `defDetailTypes` (`dty_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8 COMMENT='The record details (fields) required for each record type';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defRecStructure`
--

LOCK TABLES `defRecStructure` WRITE;
/*!40000 ALTER TABLE `defRecStructure` DISABLE KEYS */;
INSERT INTO `defRecStructure` VALUES (1,1,1,'Relationship title','A title describing the relationship','',000,70,'',0,0,'optional','viewable','reserved','open',2,1,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(2,1,2,'Start date/time','Dates only required if relationship is to be timestamped ','',006,0,'',0,0,'recommended','viewable','reserved','open',2,2,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(3,1,3,'End date / time','Enter end date only if relationship expires','',007,0,'',0,0,'recommended','viewable','reserved','open',2,3,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(4,1,4,'Related (target) record','The target record in Heurist to which the relationship points','',003,0,'',1,0,'required','viewable','reserved','open',2,4,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(5,2,1,'Page Title','Insert an informative title, understandable to others, even if the page itself does not have one','',000,60,'',1,0,'required','viewable','reserved','open',2,5,1,1,0,'','',0,'','2011-10-12 09:05:19',0),(8,2,16,'Date accessed','The date the web resource was accessed','',002,10,'',0,0,'optional','viewable','reserved','open',2,8,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(10,2,9,'Thumbnail Image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',003,40,'',0,0,'recommended','viewable','reserved','open',2,10,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(12,2,12,'Short Summary','Short summary eg. 100 words, for use in annotated listsx','',001,60,'',0,0,'recommended','viewable','reserved','open',2,12,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(16,3,1,'Title','A title for the note - should be fairly explanatory of the content','',000,80,'',1,0,'required','viewable','reserved','open',2,16,1,1,0,'','',0,'','2011-10-12 09:05:19',0),(17,3,16,'Date','Specific date to which notes apply (if applicable)','',002,10,'today',0,0,'recommended','viewable','reserved','open',2,17,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(19,3,8,'Associated Files','Upload any associated files here','',004,40,'',0,0,'optional','viewable','reserved','open',2,19,0,0,0,'','',0,'','2011-10-12 09:05:19',1),(20,3,9,'Thumbnail image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',003,40,'',0,0,'optional','viewable','reserved','open',2,20,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(23,3,12,'Short summary','Short summary eg. 100 words, for use in annotated lists','',001,60,'',0,0,'recommended','viewable','reserved','open',2,23,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(31,5,19,'Creator(s)','The creator(s) - photographer, recordist etc. - of the media item','',004,30,'',0,0,'recommended','viewable','reserved','open',2,31,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(32,5,1,'Title','A succinct descriptive title of the media item','',000,40,'',1,0,'required','viewable','reserved','open',2,32,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(33,5,2,'Date','Date of creation or publication','',005,30,'',0,0,'recommended','viewable','reserved','open',2,33,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(38,5,8,'Media file','Browse to media file and upload - recommended max size 5 MBytes','',001,60,'',0,0,'required','viewable','reserved','open',2,38,1,1,0,'','',0,'','2011-10-12 09:05:19',0),(39,5,9,'Thumbnail image','Thumbnail image, up to 400 wide, for display 100 - 200 wide in lists and detail pages','',002,40,'',0,0,'recommended','viewable','reserved','open',2,39,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(41,5,11,'Location (mappable)','Geographic object(s) describing the location as lines, polygons or other shapes','',006,40,'',0,0,'optional','viewable','approved','open',2,41,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(61,8,1,'Title','A title for the blog entry, date will be added automatically','',160,80,'',1,0,'required','viewable','reserved','open',2,61,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(64,8,9,'Thumbnail','Thumbnail image, recommended 200 pixels maximum dimension','',223,30,'',0,0,'recommended','viewable','reserved','open',2,64,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(65,8,11,'Location (mappable)','Location or extent of this blog entry, can be rendered on the map','',230,30,'',0,0,'recommended','viewable','reserved','open',2,65,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(87,10,19,'Author(s)','Person(s) making this interpretation (original author, use transcriber if further interpreted)','',002,30,'',1,0,'recommended','viewable','reserved','open',2,87,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(88,10,1,'Title','A title for this interpretation, excluding author, date','',001,50,'',1,0,'required','viewable','reserved','open',2,88,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(89,10,16,'Validity date (year)','Date of validity of this interpretation','',004,10,'today',0,0,'required','viewable','reserved','open',2,89,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(91,10,12,'Short summary','Short sumamry of the interpretation, use the WYSIWYG text in the TEXT or PERSONAL tabs for longer description','',005,50,'',0,0,'recommended','viewable','reserved','open',2,91,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(92,10,78,'Transcriber(s)','Person(s) transcribing/further interpreting the original interpretation','',003,30,'',0,0,'optional','viewable','reserved','open',2,92,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(93,10,79,'References','Bibliographic or other records in Heurist which support or relate to this interpretation','',005,80,'',0,0,'recommended','viewable','reserved','open',2,93,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(94,11,1,'Subject','Subject/title of the email','Subject line of email',160,30,'No subject line provided',1,0,'required','viewable','reserved','open',2,94,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(95,11,16,'Date originally sent','Date of the original email (date of sending of a ccd email or original date of a forwarded email)','Date of original sending',166,30,'',0,0,'required','viewable','reserved','open',2,95,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(96,11,8,'Attachments','Files attached to the email','Attached files',800,50,'',0,0,'optional','viewable','reserved','open',2,96,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(97,11,80,'Email of sender','Email address from which this email forwarded or ccd','Address of owner (forwarder or sender)',521,50,'',0,0,'required','viewable','reserved','open',2,97,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(99,11,82,'Email of originator','Address of original sender (if applicable, that is for forwarded emails)','Address of original sender (where different)',650,50,'',0,0,'optional','viewable','reserved','open',2,99,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(100,11,83,'Email of recipients','Email addresses of all recipients of the email','Emails of all recipients of the email ',651,30,'',0,0,'optional','viewable','reserved','open',2,100,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(224,20,32,'Contact info or URL','The URL of a staff directory page such as a university phone book','',007,60,'',0,0,'optional','viewable','open','open',2,224,0,0,0,'','',0,'','2011-10-12 09:05:19',0),(225,20,33,'Given name(s)','The given name or names of the person','',002,20,'',1,0,'required','viewable','reserved','open',2,225,1,1,0,'','',0,'','2011-10-12 09:05:19',0),(226,20,34,'Birth date','Date of birth','',003,30,'',0,0,'optional','viewable','reserved','open',2,226,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(228,20,12,'Short description','Short summary eg. 100 words, for use in annotated lists','',005,60,'',0,0,'optional','viewable','reserved','open',2,228,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(232,20,39,'Gender','Gender of the person','',004,30,'',0,0,'optional','viewable','reserved','open',2,232,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(239,20,46,'Unique public identifier','eg. NLA party identifier (Australia) or other national person identifier code','',008,30,'',0,0,'optional','viewable','reserved','open',2,239,1,0,0,'','',0,'','2011-10-12 09:05:19',0),(240,20,1,'Family name','Title','',001,60,'',0,0,'required','viewable','reserved','open',2,240,1,1,1,'','',0,'','2011-10-12 09:05:19',0),(241,20,80,'Email address','Email address','',006,60,'',0,0,'optional','viewable','reserved','open',2,241,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(265,1,5,'Relationship type','RelationType','',001,60,'',0,0,'optional','viewable','reserved','open',2,265,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(266,1,7,'Primary record','Primary resource','',002,60,'',0,0,'optional','viewable','reserved','open',2,266,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(267,1,13,'Interpretation/commentary','A pointer to a commentary/interpreation record (for spatial, temporal or general information)','',005,60,'',0,0,'optional','viewable','approved','open',2,267,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(268,1,8,'File resource','An uploaded file or a reference to a file through a URI ','',010,60,'',0,0,'optional','viewable','open','open',2,268,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(269,1,9,'Thumbnail image','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',008,60,'',0,0,'optional','viewable','open','open',2,269,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(270,1,11,'Geospatial object','A geospatial object providing a mappable location for the record','',009,60,'',0,0,'optional','viewable','open','open',2,270,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(271,1,12,'Short summary','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',004,60,'',0,0,'optional','viewable','reserved','open',2,271,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(311,4,1,'Name of Organisation','The main name or title for the object. Title of a work, family name of person, name of organisation etc.','',000,60,'',0,0,'required','viewable','reserved','open',2,311,1,1,1,'','',0,'','2011-10-12 09:05:19',0),(312,4,2,'Start year','Start Year','',001,60,'',0,0,'optional','viewable','open','open',2,312,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(313,4,3,'End year','End Year','',002,60,'',0,0,'optional','viewable','open','open',2,313,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(314,4,9,'Thumbnail','An image of approx. 200 pixels wide used to represent the record in search results and other compact listings','',009,60,'',0,0,'recommended','viewable','open','open',2,314,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(315,4,11,'Geographic Location','A geospatial object providing a mappable location for the record','',007,60,'',0,0,'optional','viewable','open','open',2,315,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(318,4,105,'Country','Country','',006,60,'',0,0,'optional','viewable','open','open',2,318,0,0,1,'','',0,'','2011-10-12 09:05:19',0),(327,4,179,'Short name or acronym','Name','Please provide an extended description for display on rollover ...',003,60,'',0,0,'optional','viewable','open','open',2,327,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(329,4,203,'Organisation type','Organisation type','Please provide an extended description for display on rollover ...',004,60,'',0,0,'optional','viewable','open','open',2,329,0,0,1,'','',0,'','2011-10-12 09:05:19',0),(330,4,8,'Related files','An uploaded file or a reference to a file through a URI ','',010,60,'',0,0,'optional','viewable','open','open',2,330,0,0,1,'','',0,'','2011-10-12 09:05:19',0),(332,4,12,'Short description','Short summary, typically used in annotated listings, information popups and so forth. Aim for 100 - 200 words.','',011,60,'',0,0,'recommended','viewable','open','open',2,332,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(370,11,12,'Short summary','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',652,60,'',0,0,'optional','viewable','reserved','open',2,370,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(371,5,12,'Short summary','Short summary, typically used in annotated lsitings, information popups and so forth. Aim for 100 - 200 words.','',005,60,'',0,0,'optional','viewable','reserved','open',2,371,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(373,5,311,'Copyright information','Copyright information','',007,60,'',0,0,'optional','viewable','reserved','open',2,373,1,0,1,'','',0,'','2011-10-12 09:05:19',0),(374,20,26,'Honorific','Title or honorific (Dr, Mrs, Ms, Professor, Sir etc.)','',007,60,'',0,0,'optional','viewable','open','open',2,374,1,0,1,'','',0,'','2011-10-12 09:05:19',0);
/*!40000 ALTER TABLE `defRecStructure` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @SAVE_SQL_MODE=@@SQL_MODE*/;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecStructure_last_insert` AFTER INSERT ON `defRecStructure` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `defRecStructure_last_update` AFTER UPDATE ON `defRecStructure` FOR EACH ROW update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure" */;;

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
INSERT INTO `defRecTypeGroups` VALUES (1,'Common record types','functionalgroup',001,'The commonest generic record types present in most databases','2011-10-12 09:05:18'),(3,'Core functions','functionalgroup',099,'Record types of general use, marked Reserved if required by the system','2011-10-12 09:05:18');
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
  `rty_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  `rty_LocallyModified` tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (`rty_ID`),
  UNIQUE KEY `rty_Name` (`rty_Name`),
  KEY `rty_RecTypeGroupID` (`rty_RecTypeGroupID`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COMMENT='Defines record types, which corresponds with a set of detail';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defRecTypes`
--

LOCK TABLES `defRecTypes` WRITE;
/*!40000 ALTER TABLE `defRecTypes` DISABLE KEYS */;
INSERT INTO `defRecTypes` VALUES (1,'Record relationship',30,'A relationship of a defined type between two records in the database, may include a time range over which the relationship is valid, and notes about the relationship','[Relationship title] ([Primary resource] [Relationship Type] [Resource])','[1] ([7] [5] [4])','Record relationships','reserved',2,'Record relationship',1,'viewable',1,3,NULL,1,'','','relationship','2011-10-12 09:05:18',0),(2,'Web site / page',1,'A web site URL, typically a specific page (often the home page)','[Page title]','[1]','Web site / pages','reserved',2,'Web site / page',2,'viewable',1,1,NULL,0,'','','normal','2011-10-12 09:05:18',0),(3,'Notes',2,'A simple record type for taking notes','[Title]','[1]','Notes','reserved',2,'Notes',3,'viewable',1,1,NULL,0,'','','normal','2011-10-12 09:05:18',1),(4,'Organisation',0,'Organisations (companies, universities, granting bodies etc.)','[Short name or acronym]: [Name of Organisation]','[179]: [1]','Organisations','reserved',2,'Organisation',4,'viewable',1,1,NULL,0,'','','normal','2011-10-12 09:05:18',1),(5,'Digital media (image, sound, video)',0,'Digital media files - typically images, sound and video - uploaded to the database (may also be externally referenced)','[Title]','[1]','Digital media items','reserved',2,'Digital media item',5,'viewable',1,3,NULL,0,'','','normal','2011-10-12 09:05:18',0),(8,'Blog post',0,'Blog post used to construct blogs, content in the WYSIWYG text field','[Title]','[1]','Blog entrys','reserved',2,'Blog post',8,'viewable',1,3,NULL,0,'','','normal','2011-10-12 09:05:18',0),(10,'Interpretation',0,'Metadata about a date, spatial extent or other interpretation of information','[Title] - by [Author(s).recTitle] [Date] transcribed by [Transcriber(s).recTitle]','[1] - by [19.1] [1] transcribed by [78.1]','Interpretations','reserved',2,'Interpretation',10,'viewable',1,1,NULL,0,'','','normal','2011-10-12 09:05:18',0),(11,'Email',0,'An email including content and metadata, normally derived from an email forwarded or ccd to the database\'s linked IMAP server from a user\'s account','[Subject] from: [Email of sender] on: [Date originally sent]','[1] from: [80] on: [16]','Emails','reserved',2,'Email',11,'viewable',0,3,NULL,0,'','','normal','2011-10-12 09:05:18',1),(20,'Person',33,'The canonical record for a person, may be expanded with additional information as required.','[Family name], [Given names] ([Birth Date])','[1] [33] ([34])','Persons','reserved',2,'Person',20,'viewable',1,1,NULL,0,'','','normal','2011-10-12 09:05:18',0);
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
  CONSTRAINT `fk_rcs_TermID` FOREIGN KEY (`rcs_TermID`) REFERENCES `defTerms` (`trm_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcs_SourceRecTypeID` FOREIGN KEY (`rcs_SourceRectypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcs_TargetRecTypeID` FOREIGN KEY (`rcs_TargetRectypeID`) REFERENCES `defRecTypes` (`rty_ID`) ON DELETE CASCADE ON UPDATE CASCADE
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
  `trm_Label` varchar(63) NOT NULL COMMENT 'Text label for the term, cannot be blank',
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
  PRIMARY KEY  (`trm_ID`),
  KEY `trm_ParentTermIDKey` (`trm_ParentTermID`),
  KEY `trm_InverseTermIDKey` (`trm_InverseTermId`),
  CONSTRAINT `fk_trm_ParentTermID` FOREIGN KEY (`trm_ParentTermID`) REFERENCES `defTerms` (`trm_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trm_InverseTermId` FOREIGN KEY (`trm_InverseTermId`) REFERENCES `defTerms` (`trm_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3444 DEFAULT CHARSET=utf8 COMMENT='Terms by detail type and the vocabulary they belong to';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `defTerms`
--

LOCK TABLES `defTerms` WRITE;
/*!40000 ALTER TABLE `defTerms` DISABLE KEYS */;
INSERT INTO `defTerms` VALUES (11,'University',0,'','open',2,'University',11,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(12,'Funding body',0,'','open',2,'Funding body',12,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(13,'Network',0,'','open',2,'Network',13,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(14,'Data service',0,'','open',2,'Data service',14,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(15,'Department',0,'','open',2,'Department',15,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(16,'Laboratory',0,'','open',2,'Laboratory',16,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(17,'Centre',0,'','open',2,'Centre',17,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(18,'Foundation',0,'','open',2,'Foundation',18,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(19,'Ecole Superieure',0,'','open',2,'Ecole Superieure',19,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(20,'Institute',0,'','open',2,'Institute',20,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(21,'Museum',0,'','open',2,'Museum',21,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(22,'Archaeology',0,'','open',2,'Archaeology',22,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(23,'Anthropology',0,'','open',2,'Anthropology',23,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(24,'History',0,'','open',2,'History',24,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(25,'Theatre Studies',0,'','open',2,'Theatre Studies',25,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(26,'Classics',0,'','open',2,'Classics',26,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(27,'Philosophy',0,'','open',2,'Philosophy',27,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(28,'Literature',0,'','open',2,'Literature',28,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(29,'Linguistics',0,'','open',2,'Linguistics',29,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(30,'Languages',0,'','open',2,'Languages',30,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(31,'Computer Science',0,'','open',2,'Computer Science',31,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(32,'Information Communication Technology',0,'','open',2,'Information Communication Technology',32,0,0,'enum',0,0,3206,1,'2011-10-12 09:05:18',0),(33,'Government - Research',0,'','open',2,'Government - Research',33,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(34,'Government - Infrastructure',0,'','open',2,'Government - Infrastructure',34,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(35,'Government - Special Inititative',0,'','open',2,'Government - Special Inititative',35,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(36,'Foundation - General',0,'','open',2,'Foundation - General',36,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(37,'Foundation - Specific program',0,'','open',2,'Foundation - Specific program',37,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(38,'Military',0,'','open',2,'Military',38,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(49,'Cultural',0,'','open',2,'Cultural',49,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(50,'Economic',0,'','open',2,'Economic',50,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(51,'Military',0,'','open',2,'Military',51,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(52,'Politcal',0,'','open',2,'Politcal',52,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(53,'Religious',0,'','open',2,'Religious',53,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(54,'Social',0,'','open',2,'Social',54,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(56,'Biological',0,'','open',2,'Biological',56,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(57,'Geological',0,'','open',2,'Geological',57,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(58,'Astronomical',0,'','open',2,'Astronomical',58,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(59,'Technological',0,'','open',2,'Technological',59,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(60,'Biographical',0,'','open',2,'Biographical',60,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(61,'Journey',0,'','open',2,'Journey',61,0,0,'enum',0,0,3213,1,'2011-10-12 09:05:18',0),(92,'Lecture',0,'','open',2,'Lecture',92,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(93,'Seminar',0,'','open',2,'Seminar',93,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(94,'Workshop',0,'','open',2,'Workshop',94,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(95,'Masterclass',0,'','open',2,'Masterclass',95,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(96,'Public lecture',0,'','open',2,'Public lecture',96,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(97,'Recital',0,'','open',2,'Recital',97,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(98,'Book launch',0,'','open',2,'Book launch',98,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(99,'Exhibition opening',0,'','open',2,'Exhibition opening',99,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(100,'Exhibition',0,'','open',2,'Exhibition',100,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(101,'Round table',0,'','open',2,'Round table',101,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(102,'Small project < 20 hours',0,'','open',2,'Small project < 20 hours',102,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(103,'Semester, 1 person',0,'','open',2,'Semester, 1 person',103,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(104,'Semester, group',0,'','open',2,'Semester, group',104,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(105,'Honours, 1 semester',0,'','open',2,'Honours, 1 semester',105,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(106,'Masters, 2 semesters',0,'','open',2,'Masters, 2 semesters',106,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(107,'MPhil or equiv.',0,'','open',2,'MPhil or equiv.',107,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(108,'PhD',0,'','open',2,'PhD',108,0,0,'enum',0,0,3236,1,'2011-10-12 09:05:18',0),(109,'BA Hons.',0,'','open',2,'BA Hons.',109,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(110,'MA',0,'','open',2,'MA',110,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(111,'MSc',0,'','open',2,'MSc',111,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(112,'PhD',0,'','open',2,'PhD',112,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(113,'Doctorat 3eme cycle',0,'','open',2,'Doctorat 3eme cycle',113,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(114,'Doctorat d\'etat',0,'','open',2,'Doctorat d\'etat',114,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(115,'DES',0,'','open',2,'DES',115,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(116,'MPhil',0,'','open',2,'MPhil',116,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(117,'Other',0,'','open',2,'Other',117,0,0,'enum',0,0,3244,1,'2011-10-12 09:05:18',0),(127,'Teaching staff',0,'','open',2,'Teaching staff',127,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(128,'Research staff',0,'','open',2,'Research staff',128,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(129,'Research assistant',0,'','open',2,'Research assistant',129,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(130,'Administrative staff',0,'','open',2,'Administrative staff',130,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(131,'Professional staff',0,'','open',2,'Professional staff',131,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(132,'Postgraduate student',0,'','open',2,'Postgraduate student',132,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(133,'Honours student',0,'','open',2,'Honours student',133,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(134,'Research associate/adjunct',0,'','open',2,'Research associate/adjunct',134,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(135,'Chief Investigator',0,'','open',2,'Chief Investigator',135,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(136,'Partner Investigator',0,'','open',2,'Partner Investigator',136,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(137,'Client',0,'','open',2,'Client',137,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(138,'Very Small <= $5K',0,'','open',2,'Very Small <= $5K',138,0,0,'enum',0,0,3253,1,'2011-10-12 09:05:18',0),(139,'Small <= $25K',0,'','open',2,'Small <= $25K',139,0,0,'enum',0,0,3253,1,'2011-10-12 09:05:18',0),(140,'Medium $25K - $100K',0,'','open',2,'Medium $25K - $100K',140,0,0,'enum',0,0,3253,1,'2011-10-12 09:05:18',0),(141,'Large $100K - $500K',0,'','open',2,'Large $100K - $500K',141,0,0,'enum',0,0,3253,1,'2011-10-12 09:05:18',0),(142,'Very Large > $500K',0,'','open',2,'Very Large > $500K',142,0,0,'enum',0,0,3253,1,'2011-10-12 09:05:18',0),(152,'Mr',0,'','open',2,'Mr',152,0,0,'enum',0,0,3263,1,'2011-10-12 09:05:18',0),(153,'Mrs',0,'','open',2,'Mrs',153,0,0,'enum',0,0,3263,1,'2011-10-12 09:05:18',0),(154,'Ms',0,'','open',2,'Ms',154,0,0,'enum',0,0,3263,1,'2011-10-12 09:05:18',0),(155,'Dr',0,'','open',2,'Dr',155,0,0,'enum',0,0,3263,1,'2011-10-12 09:05:18',0),(156,'A/Prof.',0,'','open',2,'A/Prof.',156,0,0,'enum',0,0,3263,1,'2011-10-12 09:05:18',0),(157,'Prof.',0,'','open',2,'Prof.',157,0,0,'enum',0,0,3263,1,'2011-10-12 09:05:18',0),(158,'Chair',0,'','open',2,'Chair',158,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(160,'Honours coordinator',0,'','open',2,'Honours coordinator',160,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(161,'Postgraduate coordinator',0,'','open',2,'Postgraduate coordinator',161,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(162,'System administrator',0,'','open',2,'System administrator',162,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(163,'Historical figure',0,'','open',2,'Historical figure',163,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(165,'BCE',0,'','open',2,'BCE',165,0,0,'enum',0,0,3273,1,'2011-10-12 09:05:18',0),(166,'CE',0,'','open',2,'CE',166,0,0,'enum',0,0,3273,1,'2011-10-12 09:05:18',0),(167,'BP',0,'','open',2,'BP',167,0,0,'enum',0,0,3273,1,'2011-10-12 09:05:18',0),(168,'Unknown',0,'','open',2,'Unknown',168,0,0,'enum',0,0,3273,1,'2011-10-12 09:05:18',0),(170,'Australia',0,'','open',2,'Australia',170,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(171,'China',0,'','open',2,'China',171,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(172,'France',0,'','open',2,'France',172,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(173,'Greece',0,'','open',2,'Greece',173,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(174,'Italy',0,'','open',2,'Italy',174,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(176,'Spain',0,'','open',2,'Spain',176,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(177,'Cambodia',0,'','open',2,'Cambodia',177,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(178,'Vietnam',0,'','open',2,'Vietnam',178,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(180,'Thailand',0,'','open',2,'Thailand',180,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(181,'New Zealand',0,'','open',2,'New Zealand',181,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(183,'UK',0,'','open',2,'UK',183,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(184,'USA',0,'','open',2,'USA',184,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(185,'Conference',0,'','open',2,'Conference',185,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(186,'Colloquium',0,'','open',2,'Colloquium',186,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(187,'Meeting',0,'','open',2,'Meeting',187,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(188,'Committee meeting',0,'','open',2,'Committee meeting',188,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(196,'WGS84 Decimal Lat-Long',0,'','open',2,'WGS84 Decimal Lat-Long',196,0,0,'enum',0,0,3288,1,'2011-10-12 09:05:18',0),(197,'UTM (WGS84)',0,'','open',2,'UTM (WGS84)',197,0,0,'enum',0,0,3288,1,'2011-10-12 09:05:18',0),(198,'Planar (local)',0,'','open',2,'Planar (local)',198,0,0,'enum',0,0,3288,1,'2011-10-12 09:05:18',0),(199,'Unknown',0,'','open',2,'Unknown',199,0,0,'enum',0,0,3288,1,'2011-10-12 09:05:18',0),(208,'Government department',0,'','open',2,'Government department',208,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(209,'Federal government',0,'','open',2,'Federal government',209,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(210,'Company',0,'','open',2,'Company',210,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(211,'NGO',0,'','open',2,'NGO',211,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(212,'Charity',0,'','open',2,'Charity',212,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(213,'Other - ask admin to define',0,'','open',2,'Other - ask admin to define',213,0,0,'enum',0,0,3204,1,'2011-10-12 09:05:18',0),(222,'Symposium',0,'','open',2,'Symposium',222,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(223,'Australia',0,'','open',2,'Australia',223,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(225,'S, E & SE Asia',0,'','open',2,'S, E & SE Asia',225,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(226,'C & W Asia',0,'','open',2,'C & W Asia',226,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(227,'Europe/Mediterranean',0,'','open',2,'Europe/Mediterranean',227,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(228,'North America',0,'','open',2,'North America',228,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(229,'S & C America',0,'','open',2,'S & C America',229,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(230,'Worldwide',0,'','open',2,'Worldwide',230,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(231,'Non-regional',0,'','open',2,'Non-regional',231,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(250,'Opening or launch',0,'','open',2,'Opening or launch',250,0,0,'enum',0,0,3233,1,'2011-10-12 09:05:18',0),(251,'Visitor',0,'','open',2,'Visitor',251,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(252,'Tutor',0,'','open',2,'Tutor',252,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(253,'Volunteer',0,'','open',2,'Volunteer',253,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(269,'Multi-regional',0,'','open',2,'Multi-regional',269,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(275,'Vice Chairperson',0,'','open',2,'Vice Chairperson',275,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(276,'Treasurer',0,'','open',2,'Treasurer',276,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(277,'Secretary',0,'','open',2,'Secretary',277,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(278,'Public Officer',0,'','open',2,'Public Officer',278,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(279,'Member',0,'','open',2,'Member',279,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(280,'Manager',0,'','open',2,'Manager',280,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(282,'Senior Linguist ',0,'','open',2,'Senior Linguist ',282,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(283,'CDEP language Worker',0,'','open',2,'CDEP language Worker',283,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(284,'Trainee',0,'','open',2,'Trainee',284,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(285,'Facilitator',0,'','open',2,'Facilitator',285,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(287,'Language Worker',0,'','open',2,'Language Worker',287,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(288,'Anthropologist',0,'','open',2,'Anthropologist',288,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(289,'Cyprus',0,'','open',2,'Cyprus',289,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(290,'Jordan',0,'','open',2,'Jordan',290,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(291,'UAE',0,'','open',2,'UAE',291,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(292,'Uzbekistan',0,'','open',2,'Uzbekistan',292,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(293,'Kyrgistan',0,'','open',2,'Kyrgistan',293,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(294,'Dubai',0,'','open',2,'Dubai',294,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(295,'Israel',0,'','open',2,'Israel',295,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(296,'Iran',0,'','open',2,'Iran',296,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(297,'Iraq',0,'','open',2,'Iraq',297,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(298,'Syria',0,'','open',2,'Syria',298,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(299,'Turkey',0,'','open',2,'Turkey',299,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(300,'Myanmar',0,'','open',2,'Myanmar',300,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(301,'India',0,'','open',2,'India',301,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(302,'Singapore',0,'','open',2,'Singapore',302,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(303,'Alumnus',0,'','open',2,'Alumnus',303,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(305,'Intern',0,'','open',2,'Intern',305,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(306,'Course Coordinator',0,'','open',2,'Course Coordinator',306,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(308,'Norway',0,'','open',2,'Norway',308,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(310,'Netherlands',0,'','open',2,'Netherlands',310,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(311,'South Africa',0,'','open',2,'South Africa',311,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(313,'Canada',0,'','open',2,'Canada',313,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(321,'Ireland',0,'','open',2,'Ireland',321,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(322,'Germany',0,'','open',2,'Germany',322,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(323,'Hungary',0,'','open',2,'Hungary',323,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(324,'Belgium',0,'','open',2,'Belgium',324,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(325,'Brazil',0,'','open',2,'Brazil',325,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(327,'Sweden',0,'','open',2,'Sweden',327,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(328,'Linguist',0,'','open',2,'Linguist',328,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(329,'Language Specialist',0,'','open',2,'Language Specialist',329,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(330,'Consultant',0,'','open',2,'Consultant',330,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(331,'Link Up Case Worker ',0,'','open',2,'Link Up Case Worker ',331,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(332,'Media Manager',0,'','open',2,'Media Manager',332,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(333,'Graphic Designer',0,'','open',2,'Graphic Designer',333,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(334,'Cultural Awareness Trainer',0,'','open',2,'Cultural Awareness Trainer',334,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(335,'Special Projects',0,'','open',2,'Special Projects',335,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(336,'Denmark',0,'','open',2,'Denmark',336,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(342,'Buddhism',0,'','open',2,'Buddhism',342,0,0,'enum',0,0,3335,1,'2011-10-12 09:05:18',0),(343,'Hinduism',0,'','open',2,'Hinduism',343,0,0,'enum',0,0,3335,1,'2011-10-12 09:05:18',0),(346,'Taiwan',0,'','open',2,'Taiwan',346,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(364,'0-1 m',0,'','open',2,'0-1 m',364,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(365,'1-10 m',0,'','open',2,'1-10 m',365,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(366,'10-100m',0,'','open',2,'10-100m',366,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(367,'100-1000m',0,'','open',2,'100-1000m',367,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(368,'1000-10000 m',0,'','open',2,'1000-10000 m',368,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(394,'Afghanistan',0,'','open',2,'Afghanistan',394,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(395,'Aland Islands',0,'','open',2,'Aland Islands',395,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(396,'Albania',0,'','open',2,'Albania',396,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(397,'Algeria',0,'','open',2,'Algeria',397,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(398,'American Samoa',0,'','open',2,'American Samoa',398,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(399,'Andorra',0,'','open',2,'Andorra',399,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(400,'Angola',0,'','open',2,'Angola',400,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(401,'Anguilla',0,'','open',2,'Anguilla',401,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(402,'Antarctica',0,'','open',2,'Antarctica',402,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(403,'Antigua and Barbuda',0,'','open',2,'Antigua and Barbuda',403,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(404,'Argentina',0,'','open',2,'Argentina',404,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(405,'Armenia',0,'','open',2,'Armenia',405,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(406,'Aruba',0,'','open',2,'Aruba',406,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(407,'Austria',0,'','open',2,'Austria',407,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(408,'Azerbaijan',0,'','open',2,'Azerbaijan',408,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(409,'Bahamas',0,'','open',2,'Bahamas',409,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(410,'Bahrain',0,'','open',2,'Bahrain',410,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(411,'Bangladesh',0,'','open',2,'Bangladesh',411,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(412,'Barbados',0,'','open',2,'Barbados',412,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(413,'Belarus',0,'','open',2,'Belarus',413,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(414,'Belize',0,'','open',2,'Belize',414,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(415,'Benin',0,'','open',2,'Benin',415,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(416,'Bermuda',0,'','open',2,'Bermuda',416,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(417,'Bhutan',0,'','open',2,'Bhutan',417,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(418,'Bolivia',0,'','open',2,'Bolivia',418,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(419,'Bosnia and Herzegovina',0,'','open',2,'Bosnia and Herzegovina',419,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(420,'Botswana',0,'','open',2,'Botswana',420,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(421,'Bouvet Island',0,'','open',2,'Bouvet Island',421,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(422,'British Indian Ocean Territory',0,'','open',2,'British Indian Ocean Territory',422,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(423,'Brunei Darussalam',0,'','open',2,'Brunei Darussalam',423,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(424,'Bulgaria',0,'','open',2,'Bulgaria',424,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(425,'Burkina Faso',0,'','open',2,'Burkina Faso',425,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(426,'Burundi',0,'','open',2,'Burundi',426,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(427,'Cameroon',0,'','open',2,'Cameroon',427,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(428,'Cape Verde',0,'','open',2,'Cape Verde',428,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(429,'Cayman Islands',0,'','open',2,'Cayman Islands',429,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(430,'Central African Republic',0,'','open',2,'Central African Republic',430,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(431,'Chad',0,'','open',2,'Chad',431,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(432,'Chile',0,'','open',2,'Chile',432,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(433,'Christmas Island',0,'','open',2,'Christmas Island',433,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(434,'Cocos (Keeling) Islands',0,'','open',2,'Cocos (Keeling) Islands',434,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(435,'Colombia',0,'','open',2,'Colombia',435,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(436,'Comoros',0,'','open',2,'Comoros',436,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(437,'Congo',0,'','open',2,'Congo',437,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(438,'Congo, Democratic Republic of the',0,'','open',2,'Congo, Democratic Republic of the',438,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(439,'Cook Islands',0,'','open',2,'Cook Islands',439,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(440,'Costa Rica',0,'','open',2,'Costa Rica',440,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(441,'C?te d\'Ivoire',0,'','open',2,'C?te d\'Ivoire',441,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(442,'Croatia',0,'','open',2,'Croatia',442,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(443,'Cuba',0,'','open',2,'Cuba',443,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(444,'Czech Republic',0,'','open',2,'Czech Republic',444,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(445,'Djibouti',0,'','open',2,'Djibouti',445,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(446,'Dominica',0,'','open',2,'Dominica',446,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(447,'Dominican Republic',0,'','open',2,'Dominican Republic',447,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(448,'Ecuador',0,'','open',2,'Ecuador',448,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(449,'Egypt',0,'','open',2,'Egypt',449,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(450,'El Salvador',0,'','open',2,'El Salvador',450,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(451,'Equatorial Guinea',0,'','open',2,'Equatorial Guinea',451,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(452,'Eritrea',0,'','open',2,'Eritrea',452,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(453,'Estonia',0,'','open',2,'Estonia',453,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(454,'Ethiopia',0,'','open',2,'Ethiopia',454,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(455,'Falkland Islands (malvinas)',0,'','open',2,'Falkland Islands (malvinas)',455,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(456,'Faroe Islands',0,'','open',2,'Faroe Islands',456,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(457,'Fiji',0,'','open',2,'Fiji',457,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(458,'Finland',0,'','open',2,'Finland',458,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(459,'French Guiana',0,'','open',2,'French Guiana',459,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(460,'French Polynesia',0,'','open',2,'French Polynesia',460,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(461,'French Southern Territories',0,'','open',2,'French Southern Territories',461,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(462,'Gabon',0,'','open',2,'Gabon',462,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(463,'Gambia',0,'','open',2,'Gambia',463,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(464,'Georgia',0,'','open',2,'Georgia',464,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(465,'Ghana',0,'','open',2,'Ghana',465,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(466,'Gibraltar',0,'','open',2,'Gibraltar',466,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(467,'Greenland',0,'','open',2,'Greenland',467,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(468,'Grenada',0,'','open',2,'Grenada',468,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(469,'Guadeloupe',0,'','open',2,'Guadeloupe',469,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(470,'Guam',0,'','open',2,'Guam',470,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(471,'Guatemala',0,'','open',2,'Guatemala',471,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(472,'Guernsey',0,'','open',2,'Guernsey',472,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(473,'Guinea',0,'','open',2,'Guinea',473,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(474,'Guinea-Bissau',0,'','open',2,'Guinea-Bissau',474,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(475,'Guyana',0,'','open',2,'Guyana',475,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(476,'Haiti',0,'','open',2,'Haiti',476,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(477,'Heard Island and Mcdonald Islands',0,'','open',2,'Heard Island and Mcdonald Islands',477,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(478,'Holy See (Vatican City State)',0,'','open',2,'Holy See (Vatican City State)',478,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(479,'Honduras',0,'','open',2,'Honduras',479,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(480,'Hong Kong',0,'','open',2,'Hong Kong',480,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(481,'Iceland',0,'','open',2,'Iceland',481,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(482,'Indonesia',0,'','open',2,'Indonesia',482,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(483,'Isle of Man',0,'','open',2,'Isle of Man',483,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(484,'Jamaica',0,'','open',2,'Jamaica',484,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(485,'Japan',0,'','open',2,'Japan',485,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(486,'Jersey',0,'','open',2,'Jersey',486,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(487,'Kazakhstan',0,'','open',2,'Kazakhstan',487,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(488,'Kenya',0,'','open',2,'Kenya',488,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(489,'Kiribati',0,'','open',2,'Kiribati',489,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(490,'Korea, Democratic People\'s Republic of',0,'','open',2,'Korea, Democratic People\'s Republic of',490,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(491,'Korea, Republic of',0,'','open',2,'Korea, Republic of',491,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(492,'Kuwait',0,'','open',2,'Kuwait',492,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(493,'Lao People\'s Democratic Republic',0,'','open',2,'Lao People\'s Democratic Republic',493,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(494,'Latvia',0,'','open',2,'Latvia',494,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(495,'Lebanon',0,'','open',2,'Lebanon',495,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(496,'Lesotho',0,'','open',2,'Lesotho',496,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(497,'Liberia',0,'','open',2,'Liberia',497,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(498,'Libyan Arab Jamahiriya',0,'','open',2,'Libyan Arab Jamahiriya',498,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(499,'Liechtenstein',0,'','open',2,'Liechtenstein',499,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(500,'Lithuania',0,'','open',2,'Lithuania',500,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(501,'Luxembourg',0,'','open',2,'Luxembourg',501,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(502,'Macao',0,'','open',2,'Macao',502,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(503,'Macedonia, Former Yugoslav Republic of',0,'','open',2,'Macedonia, Former Yugoslav Republic of',503,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(504,'Madagascar',0,'','open',2,'Madagascar',504,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(505,'Malawi',0,'','open',2,'Malawi',505,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(506,'Malaysia',0,'','open',2,'Malaysia',506,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(507,'Maldives',0,'','open',2,'Maldives',507,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(508,'Mali',0,'','open',2,'Mali',508,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(509,'Malta',0,'','open',2,'Malta',509,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(510,'Marshall Islands',0,'','open',2,'Marshall Islands',510,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(511,'Martinique',0,'','open',2,'Martinique',511,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(512,'Mauritania',0,'','open',2,'Mauritania',512,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(513,'Mauritius',0,'','open',2,'Mauritius',513,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(514,'Mayotte',0,'','open',2,'Mayotte',514,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(515,'Mexico',0,'','open',2,'Mexico',515,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(516,'Micronesia, Federated States of',0,'','open',2,'Micronesia, Federated States of',516,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(517,'Moldova, Republic of',0,'','open',2,'Moldova, Republic of',517,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(518,'Monaco',0,'','open',2,'Monaco',518,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(519,'Mongolia',0,'','open',2,'Mongolia',519,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(520,'Montenegro',0,'','open',2,'Montenegro',520,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(521,'Montserrat',0,'','open',2,'Montserrat',521,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(522,'Morocco',0,'','open',2,'Morocco',522,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(523,'Mozambique',0,'','open',2,'Mozambique',523,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(524,'Namibia',0,'','open',2,'Namibia',524,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(525,'Nauru',0,'','open',2,'Nauru',525,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(526,'Nepal',0,'','open',2,'Nepal',526,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(527,'Netherlands Antilles',0,'','open',2,'Netherlands Antilles',527,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(528,'New Caledonia',0,'','open',2,'New Caledonia',528,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(529,'Nicaragua',0,'','open',2,'Nicaragua',529,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(530,'Niger',0,'','open',2,'Niger',530,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(531,'Nigeria',0,'','open',2,'Nigeria',531,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(532,'Niue',0,'','open',2,'Niue',532,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(533,'Norfolk Island',0,'','open',2,'Norfolk Island',533,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(534,'Northern Mariana Islands',0,'','open',2,'Northern Mariana Islands',534,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(535,'Oman',0,'','open',2,'Oman',535,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(536,'Pakistan',0,'','open',2,'Pakistan',536,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(537,'Palau',0,'','open',2,'Palau',537,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(538,'Palestinian Territory, Occupied',0,'','open',2,'Palestinian Territory, Occupied',538,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(539,'Panama',0,'','open',2,'Panama',539,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(540,'Papua New Guinea',0,'','open',2,'Papua New Guinea',540,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(541,'Paraguay',0,'','open',2,'Paraguay',541,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(542,'Peru',0,'','open',2,'Peru',542,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(543,'Philippines',0,'','open',2,'Philippines',543,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(544,'Pitcairn',0,'','open',2,'Pitcairn',544,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(545,'Poland',0,'','open',2,'Poland',545,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(546,'Portugal',0,'','open',2,'Portugal',546,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(547,'Puerto Rico',0,'','open',2,'Puerto Rico',547,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(548,'Qatar',0,'','open',2,'Qatar',548,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(549,'Reunion',0,'','open',2,'Reunion',549,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(550,'Romania',0,'','open',2,'Romania',550,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(551,'Russian Federation',0,'','open',2,'Russian Federation',551,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(552,'Rwanda',0,'','open',2,'Rwanda',552,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(553,'Saint Barth?lemy',0,'','open',2,'Saint Barth?lemy',553,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(554,'Saint Helena',0,'','open',2,'Saint Helena',554,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(555,'Saint Kitts and Nevis',0,'','open',2,'Saint Kitts and Nevis',555,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(556,'Saint Lucia',0,'','open',2,'Saint Lucia',556,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(557,'Saint Martin',0,'','open',2,'Saint Martin',557,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(558,'Saint Pierre and Miquelon',0,'','open',2,'Saint Pierre and Miquelon',558,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(559,'Saint Vincent and the Grenadines',0,'','open',2,'Saint Vincent and the Grenadines',559,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(560,'Samoa',0,'','open',2,'Samoa',560,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(561,'San Marino',0,'','open',2,'San Marino',561,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(562,'Sao Tome and Principe',0,'','open',2,'Sao Tome and Principe',562,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(563,'Saudi Arabia',0,'','open',2,'Saudi Arabia',563,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(564,'Senegal',0,'','open',2,'Senegal',564,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(565,'Serbia',0,'','open',2,'Serbia',565,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(566,'Seychelles',0,'','open',2,'Seychelles',566,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(567,'Sierra Leone',0,'','open',2,'Sierra Leone',567,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(568,'Slovakia',0,'','open',2,'Slovakia',568,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(569,'Slovenia',0,'','open',2,'Slovenia',569,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(570,'Solomon Islands',0,'','open',2,'Solomon Islands',570,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(571,'Somalia',0,'','open',2,'Somalia',571,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(572,'South Georgia and the South Sandwich Islands',0,'','open',2,'South Georgia and the South Sandwich Islands',572,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(573,'Sri Lanka',0,'','open',2,'Sri Lanka',573,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(574,'Sudan',0,'','open',2,'Sudan',574,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(575,'Suriname',0,'','open',2,'Suriname',575,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(576,'Svalbard and Jan Mayen',0,'','open',2,'Svalbard and Jan Mayen',576,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(577,'Swaziland',0,'','open',2,'Swaziland',577,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(578,'Switzerland',0,'','open',2,'Switzerland',578,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(579,'Tajikistan',0,'','open',2,'Tajikistan',579,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(580,'Tanzania, United Republic of',0,'','open',2,'Tanzania, United Republic of',580,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(581,'Timor-Leste',0,'','open',2,'Timor-Leste',581,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(582,'Togo',0,'','open',2,'Togo',582,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(583,'Tokelau',0,'','open',2,'Tokelau',583,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(584,'Tonga',0,'','open',2,'Tonga',584,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(585,'Trinidad and Tobago',0,'','open',2,'Trinidad and Tobago',585,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(586,'Tunisia',0,'','open',2,'Tunisia',586,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(587,'Turkmenistan',0,'','open',2,'Turkmenistan',587,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(588,'Turks and Caicos Islands',0,'','open',2,'Turks and Caicos Islands',588,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(589,'Tuvalu',0,'','open',2,'Tuvalu',589,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(590,'Uganda',0,'','open',2,'Uganda',590,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(591,'Ukraine',0,'','open',2,'Ukraine',591,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(592,'United States Minor Outlying Islands',0,'','open',2,'United States Minor Outlying Islands',592,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(593,'Uruguay',0,'','open',2,'Uruguay',593,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(594,'Vanuatu',0,'','open',2,'Vanuatu',594,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(595,'Venezuela',0,'','open',2,'Venezuela',595,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(596,'Virgin Islands, British',0,'','open',2,'Virgin Islands, British',596,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(597,'Virgin Islands, U.S.',0,'','open',2,'Virgin Islands, U.S.',597,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(598,'Wallis and Futuna',0,'','open',2,'Wallis and Futuna',598,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(599,'Western Sahara',0,'','open',2,'Western Sahara',599,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(600,'Yemen',0,'','open',2,'Yemen',600,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(601,'Zambia',0,'','open',2,'Zambia',601,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(602,'Zimbabwe',0,'','open',2,'Zimbabwe',602,0,0,'enum',0,0,3278,1,'2011-10-12 09:05:18',0),(609,'Postdoctoral Fellow',0,'','open',2,'Postdoctoral Fellow',609,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(629,'Male',0,'','open',2,'Male',629,0,0,'enum',0,0,3400,1,'2011-10-12 09:05:18',0),(630,'Female',0,'','open',2,'Female',630,0,0,'enum',0,0,3400,1,'2011-10-12 09:05:18',0),(643,'Recorder',0,'','open',2,'Recorder',643,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(644,'Singer',0,'','open',2,'Singer',644,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(645,'Musician',0,'','open',2,'Musician',645,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(646,'Witness',0,'','open',2,'Witness',646,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(647,'Speaker',0,'','open',2,'Speaker',647,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(755,'BBSRC',0,'','open',2,'BBSRC',755,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(756,'HEIF 1',0,'','open',2,'HEIF 1',756,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(757,'HEIF 2',0,'','open',2,'HEIF 2',757,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(758,'HEIF 3',0,'','open',2,'HEIF 3',758,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(759,'MOMENTA',0,'','open',2,'MOMENTA',759,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(760,'Olympics',0,'','open',2,'Olympics',760,0,0,'enum',0,0,3207,1,'2011-10-12 09:05:18',0),(1200,'Student',0,'','open',2,'Student',1200,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1394,'Collector',0,'','open',2,'Collector',1394,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1395,'Commentator',0,'','open',2,'Commentator',1395,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1396,'Composer',0,'','open',2,'Composer',1396,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1397,'Dancer',0,'','open',2,'Dancer',1397,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1398,'Director',0,'','open',2,'Director',1398,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1399,'Instrumentalist',0,'','open',2,'Instrumentalist',1399,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1400,'Interviewer',0,'','open',2,'Interviewer',1400,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1401,'Photographer',0,'','open',2,'Photographer',1401,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1402,'Recording engineer',0,'','open',2,'Recording engineer',1402,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1403,'Research team head',0,'','open',2,'Research team head',1403,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1404,'Research team member',0,'','open',2,'Research team member',1404,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1405,'Storyteller',0,'','open',2,'Storyteller',1405,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1406,'Transcriber',0,'','open',2,'Transcriber',1406,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1407,'Videographer',0,'','open',2,'Videographer',1407,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1430,'Conceptor',0,'','open',2,'Conceptor',1430,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1431,'Data inputter',0,'','open',2,'Data inputter',1431,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1432,'Interviewee',0,'','open',2,'Interviewee',1432,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1433,'Metadata contact',0,'','open',2,'Metadata contact',1433,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1434,'Narrator',0,'','open',2,'Narrator',1434,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1435,'Translator',0,'','open',2,'Translator',1435,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1470,'Emeritus Professor/Adjunct',0,'','open',2,'Emeritus Professor/Adjunct',1470,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1511,'Middle East',0,'','open',2,'Middle East',1511,0,0,'enum',0,0,3261,1,'2011-10-12 09:05:18',0),(1610,'0.01-0.05km',0,'','open',2,'0.01-0.05km',1610,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1611,'0.05-0.1km',0,'','open',2,'0.05-0.1km',1611,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1612,'0.1-0.5km',0,'','open',2,'0.1-0.5km',1612,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1613,'0.5-1km',0,'','open',2,'0.5-1km',1613,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1614,'1-5km',0,'','open',2,'1-5km',1614,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1615,'5-10km',0,'','open',2,'5-10km',1615,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1616,'10-50km',0,'','open',2,'10-50km',1616,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1617,'50-100km',0,'','open',2,'50-100km',1617,0,0,'enum',0,0,3339,1,'2011-10-12 09:05:18',0),(1649,'Adjunct lecturer',0,'','open',2,'Adjunct lecturer',1649,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(1674,'APAI Research Student',0,'','open',2,'APAI Research Student',1674,0,0,'enum',0,0,3256,1,'2011-10-12 09:05:18',0),(2501,'English (EN, ENG)',0,'','reserved',2,'English (EN, ENG)',2501,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2502,'Arabic (AR, ARA)',0,'','reserved',2,'Arabic (AR, ARA)',2502,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2503,'Yiddish (YI, YID)',0,'','reserved',2,'Yiddish (YI, YID)',2503,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2504,'Hebrew (HE, HEB)',0,'','reserved',2,'Hebrew (HE, HEB)',2504,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2505,'French (FR, FRE)',0,'','reserved',2,'French (FR, FRE)',2505,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2506,'Italian (IT, ITA)',0,'','reserved',2,'Italian (IT, ITA)',2506,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2507,'Spanish (ES, SPA)',0,'','reserved',2,'Spanish (ES, SPA)',2507,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2508,'Dutch (NL, DUTENG)',0,'','reserved',2,'Dutch (NL, DUTENG)',2508,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2509,'Danish (DA, DAN)',0,'','reserved',2,'Danish (DA, DAN)',2509,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2510,'Norwegian (NO, NOR)',0,'','reserved',2,'Norwegian (NO, NOR)',2510,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2511,'Portuguese} (PT, POR)',0,'','reserved',2,'Portuguese} (PT, POR)',2511,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2512,'German (DE, GER)',0,'','reserved',2,'German (DE, GER)',2512,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2513,'Greek (EL, GRE)',0,'','reserved',2,'Greek (EL, GRE)',2513,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2514,'Turkish (TR, TUR)',0,'','reserved',2,'Turkish (TR, TUR)',2514,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2515,'Russian (RU, RUS)',0,'','reserved',2,'Russian (RU, RUS)',2515,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2516,'Ukrainian (UK, UKR)',0,'','reserved',2,'Ukrainian (UK, UKR)',2516,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2517,'Swedish (SV, SWE)',0,'','reserved',2,'Swedish (SV, SWE)',2517,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2518,'Finish (FI, FIN)',0,'','reserved',2,'Finish (FI, FIN)',2518,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2519,'Latvian (LV, LAV)',0,'','reserved',2,'Latvian (LV, LAV)',2519,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2520,'Estonian (ET, EST)',0,'','reserved',2,'Estonian (ET, EST)',2520,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2521,'Hungarian (HU, HUN)',0,'','reserved',2,'Hungarian (HU, HUN)',2521,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2522,'Czech (CS, CZE)',0,'','reserved',2,'Czech (CS, CZE)',2522,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2523,'Polish (PL, ENG)',0,'','reserved',2,'Polish (PL, ENG)',2523,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2524,'Slovak (EN, POL)',0,'','reserved',2,'Slovak (EN, POL)',2524,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2525,'Serbian (EN, SCR)',0,'','reserved',2,'Serbian (EN, SCR)',2525,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2526,'Croatian (HR, SCC)',0,'','reserved',2,'Croatian (HR, SCC)',2526,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2527,'Swahili (SW, SWA)',0,'','reserved',2,'Swahili (SW, SWA)',2527,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2528,'Chinese (ZH, CHI)',0,'','reserved',2,'Chinese (ZH, CHI)',2528,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2529,'Indonesian (ID, IND)',0,'','reserved',2,'Indonesian (ID, IND)',2529,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2530,'Hindi (HI, HIN)',0,'','reserved',2,'Hindi (HI, HIN)',2530,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2531,'Japanese (JA, JPN)',0,'','reserved',2,'Japanese (JA, JPN)',2531,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2532,'Malay (MS, MAL)',0,'','reserved',2,'Malay (MS, MAL)',2532,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2533,'Korean (KO, KOR)',0,'','reserved',2,'Korean (KO, KOR)',2533,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2534,'Vietnamese (VI, VIE)',0,'','reserved',2,'Vietnamese (VI, VIE)',2534,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2535,'Thai (TH, THA)',0,'','reserved',2,'Thai (TH, THA)',2535,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(2536,'Khmer (KM, CAM)',0,'','reserved',2,'Khmer (KM, CAM)',2536,0,0,'enum',0,0,3000,1,'2011-10-12 09:05:18',0),(3000,'Language [vocab]',0,'Common languages vocabulary','reserved',2,'Language [vocab]',3000,0,0,'enum',0,0,0,0,'2011-10-12 09:05:18',0),(3001,'Generic',0,'Vocabulary derived from RelationType in Heurist vsn 2','open',2,'Generic',3001,0,0,'relation',0,121,0,0,'2011-10-12 09:05:18',0),(3002,'Abuts',3081,'','open',2,'Abuts',3002,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3003,'BondsWith',3003,'','open',2,'BondsWith',3003,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0),(3004,'IsCausedBy',3005,'','open',2,'IsCausedBy',3004,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3005,'Causes',3004,'','open',2,'Causes',3005,0,0,'relation',0,0,3409,1,'2011-10-12 09:05:18',0),(3006,'CollaboratesWith',3006,'','open',2,'CollaboratesWith',3006,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3007,'IsPartOf',3008,'','open',2,'IsPartOf',3007,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0),(3008,'Contains',3007,'','open',2,'Contains',3008,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0),(3009,'CooperatesWith',3009,'','open',2,'CooperatesWith',3009,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3010,'Cuts',3093,'','open',2,'Cuts',3010,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3011,'StartsAfter',3012,'','open',2,'StartsAfter',3011,0,0,'relation',0,0,3409,1,'2011-10-12 09:05:18',0),(3012,'EndBefore',3011,'','open',2,'EndBefore',3012,0,0,'relation',0,0,3409,1,'2011-10-12 09:05:18',0),(3013,'IsFundedBy',3014,'','open',2,'IsFundedBy',3013,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3014,'Funds',3013,'','open',2,'Funds',3014,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3015,'IsAssociateDirectorOf',3016,'','open',2,'IsAssociateDirectorOf',3015,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3016,'HasAssociateDirector',3015,'','open',2,'HasAssociateDirector',3016,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3017,'IsAssociatePartnerIn',3018,'','open',2,'IsAssociatePartnerIn',3017,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3018,'HasAssociatePartner',3017,'','open',2,'HasAssociatePartner',3018,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3019,'IsAuthorOf',3020,'','open',2,'IsAuthorOf',3019,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3020,'HasAuthor',3019,'','open',2,'HasAuthor',3020,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3021,'HasNarrowerTerm',3022,'','open',2,'HasNarrowerTerm',3021,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3022,'HasBroaderTerm',3021,'','open',2,'HasBroaderTerm',3022,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3023,'IsCoAuthorOf',3024,'','open',2,'IsCoAuthorOf',3023,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3024,'HasCoAuthor',3023,'','open',2,'HasCoAuthor',3024,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3025,'IsCoConvenerOf',3026,'','open',2,'IsCoConvenerOf',3025,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3026,'HasCoConvenor',3025,'','open',2,'HasCoConvenor',3026,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3027,'isCommanderOf',3028,'','open',2,'isCommanderOf',3027,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3028,'hasCommander',3027,'','open',2,'hasCommander',3028,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3029,'isContributorOf',3030,'','open',2,'isContributorOf',3029,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3030,'hasContributor',3029,'','open',2,'hasContributor',3030,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3031,'IsConvenerOf',3032,'','open',2,'IsConvenerOf',3031,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3032,'HasConvenor',3031,'','open',2,'HasConvenor',3032,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3033,'IsDirectorOf',3034,'','open',2,'IsDirectorOf',3033,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3034,'HasDirector',3033,'','open',2,'HasDirector',3034,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3035,'IsEditionOf',3036,'','open',2,'IsEditionOf',3035,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3036,'HasEdition',3035,'','open',2,'HasEdition',3036,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3037,'hasMap',3038,'','open',2,'hasMap',3037,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3038,'hasEntry',3037,'','open',2,'hasEntry',3038,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3039,'isExternalLinkOf',3040,'','open',2,'isExternalLinkOf',3039,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3040,'hasExternalLink',3039,'','open',2,'hasExternalLink',3040,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3041,'IsFounderOf',3042,'','open',2,'IsFounderOf',3041,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3042,'HasFounder',3041,'','open',2,'HasFounder',3042,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3043,'IsHostOf',3044,'','open',2,'IsHostOf',3043,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3044,'HasHost',3043,'','open',2,'HasHost',3044,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3045,'IsLeadPartner',3046,'','open',2,'IsLeadPartner',3045,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3046,'HasLeadPartner',3045,'','open',2,'HasLeadPartner',3046,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3047,'IsManagerOf',3048,'','open',2,'IsManagerOf',3047,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3048,'HasManager',3047,'','open',2,'HasManager',3048,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3049,'IsMemberOf',3050,'','open',2,'IsMemberOf',3049,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3050,'HasMember',3049,'','open',2,'HasMember',3050,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3051,'IsMilestoneOf',3052,'','open',2,'IsMilestoneOf',3051,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3052,'HasMilestone',3051,'','open',2,'HasMilestone',3052,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3053,'HasPreviousPart',3054,'','open',2,'HasPreviousPart',3053,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3054,'HasNextPart',3053,'','open',2,'HasNextPart',3054,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3055,'IsNodeDirectorOf',3056,'','open',2,'IsNodeDirectorOf',3055,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3056,'HasNodeDirector',3055,'','open',2,'HasNodeDirector',3056,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3057,'IsPageImageOf',3058,'','open',2,'IsPageImageOf',3057,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3058,'HasPageImage',3057,'','open',2,'HasPageImage',3058,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3059,'IsPartnerIn',3060,'','open',2,'IsPartnerIn',3059,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3060,'HasPartner',3059,'','open',2,'HasPartner',3060,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3061,'IsPhotographOf',3062,'','open',2,'IsPhotographOf',3061,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3062,'HasPhotograph',3061,'','open',2,'HasPhotograph',3062,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3063,'isPrimarySubjectOf',3064,'','open',2,'isPrimarySubjectOf',3063,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3064,'hasPrimarySubject',3063,'','open',2,'hasPrimarySubject',3064,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3065,'isReferenceOf',3066,'','open',2,'isReferenceOf',3065,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3066,'hasReference',3065,'','open',2,'hasReference',3066,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3067,'hasRelatedTerm',3067,'','open',2,'hasRelatedTerm',3067,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3068,'isResourceOf',3069,'','open',2,'isResourceOf',3068,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3069,'hasResource',3068,'','open',2,'hasResource',3069,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3070,'IsSpeakerAt',3071,'','open',2,'IsSpeakerAt',3070,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3071,'HasSpeaker',3070,'','open',2,'HasSpeaker',3071,0,0,'relation',0,0,3407,1,'2011-10-12 09:05:18',0),(3072,'isSubjectOf',3073,'','open',2,'isSubjectOf',3072,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3073,'hasSubject',3072,'','open',2,'hasSubject',3073,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3074,'IsSubNodeOf',3075,'','open',2,'IsSubNodeOf',3074,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3075,'HasSubNode',3074,'','open',2,'HasSubNode',3075,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3076,'IsTranscriptionOf',3077,'','open',2,'IsTranscriptionOf',3076,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3077,'HasTranscription',3076,'','open',2,'HasTranscription',3077,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3078,'IsInText',3079,'','open',2,'IsInText',3078,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3079,'isAbout',3078,'','open',2,'isAbout',3079,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3080,'IsAbove',3085,'','open',2,'IsAbove',3080,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3081,'IsAbuttedBy',3002,'','open',2,'IsAbuttedBy',3081,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3082,'IsAdjacentTo',3082,'','open',2,'IsAdjacentTo',3082,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0),(3083,'IsNeiceOrNephewOf',3084,'','open',2,'IsNeiceOrNephewOf',3083,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3084,'IsAuntOrUncleOf',3083,'','open',2,'IsAuntOrUncleOf',3084,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3085,'IsBelow',3080,'','open',2,'IsBelow',3085,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3087,'IsBrotherOf',3442,'','open',2,'IsBrotherOf',3087,0,0,'relation',0,0,3442,1,'2011-10-12 09:05:18',0),(3088,'IsBrotherOrSisterInLaw',3088,'','open',2,'IsBrotherOrSisterInLaw',3088,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3089,'IsParentOf',3090,'','open',2,'IsParentOf',3089,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3090,'IsChildOf',3089,'','open',2,'IsChildOf',3090,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3091,'IsContemporaryWith',3091,'','open',2,'IsContemporaryWith',3091,0,0,'relation',0,0,3409,1,'2011-10-12 09:05:18',0),(3092,'IsCousinOf',3092,'','open',2,'IsCousinOf',3092,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3093,'IsCutBy',3010,'','open',2,'IsCutBy',3093,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3094,'IsDaughterOrSonInLaw',3105,'','open',2,'IsDaughterOrSonInLaw',3094,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3095,'IsFatherOf',3090,'','open',2,'IsFatherOf',3095,0,0,'relation',0,0,3089,1,'2011-10-12 09:05:18',0),(3096,'IsFilledBy',3443,'','open',2,'IsFilledBy',3096,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',1),(3097,'WasFoundAt',3098,'','open',2,'WasFoundAt',3097,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0),(3098,'IsFindSiteOf',3097,'','open',2,'IsFindSiteOf',3098,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0),(3099,'IsGrandfatherOf',3440,'','open',2,'IsGrandfatherOf',3099,0,0,'relation',0,0,3439,1,'2011-10-12 09:05:18',0),(3100,'IsGrandmotherOf',3440,'','open',2,'IsGrandmotherOf',3100,0,0,'relation',0,0,3439,1,'2011-10-12 09:05:18',0),(3101,'isOf',3102,'','open',2,'isOf',3101,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3102,'IsInMM',3101,'','open',2,'IsInMM',3102,0,0,'relation',0,0,3410,1,'2011-10-12 09:05:18',0),(3103,'IsMarriedTo',3103,'','open',2,'IsMarriedTo',3103,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3104,'IsMotherOf',3090,'','open',2,'IsMotherOf',3104,0,0,'relation',0,0,3089,1,'2011-10-12 09:05:18',1),(3105,'IsMotherOrFatherInLaw',3094,'','open',2,'IsMotherOrFatherInLaw',3105,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3106,'Owns',3107,'','open',2,'Owns',3106,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3107,'IsOwnedBy',3106,'','open',2,'IsOwnedBy',3107,0,0,'relation',0,0,3408,1,'2011-10-12 09:05:18',0),(3108,'IsRelatedTo',3108,'','open',2,'IsRelatedTo',3108,0,0,'relation',0,0,3001,1,'2011-10-12 09:05:18',0),(3204,'Organisation type [vocab]',0,'Vocabulary derived from Organisation type in Heurist vsn 2','open',2,'Organisation type [vocab]',3204,0,0,'enum',0,33,0,0,'2011-10-12 09:05:18',0),(3206,'Discipline [vocab]',0,'Vocabulary derived from Discipline in Heurist vsn 2','open',2,'Discipline [vocab]',3206,0,0,'enum',0,11,0,0,'2011-10-12 09:05:18',0),(3207,'Funding Type [vocab]',0,'Vocabulary derived from Funding Type in Heurist vsn 2','open',2,'Funding Type [vocab]',3207,0,0,'enum',0,12,0,0,'2011-10-12 09:05:18',0),(3213,'EventDomain [vocab]',0,'Vocabulary derived from EventDomain in Heurist vsn 2','open',2,'EventDomain [vocab]',3213,0,0,'enum',0,13,0,0,'2011-10-12 09:05:18',0),(3233,'Event type [vocab]',0,'Vocabulary derived from Event type in Heurist vsn 2','open',2,'Event type [vocab]',3233,0,0,'enum',0,19,0,0,'2011-10-12 09:05:18',0),(3236,'Project scope [vocab]',0,'Vocabulary derived from Project scope in Heurist vsn 2','open',2,'Project scope [vocab]',3236,0,0,'enum',0,7,0,0,'2011-10-12 09:05:18',0),(3244,'Thesis type [vocab]',0,'Vocabulary derived from Thesis type in Heurist vsn 2','open',2,'Thesis type [vocab]',3244,0,0,'enum',0,9,0,0,'2011-10-12 09:05:18',0),(3253,'Funding bracket [vocab]',0,'Vocabulary derived from Funding bracket in Heurist vsn 2','open',2,'Funding bracket [vocab]',3253,0,0,'enum',0,6,0,0,'2011-10-12 09:05:18',0),(3256,'Person Role [vocab]',0,'Vocabulary derived from Person Role in Heurist vsn 2','open',2,'Person Role [vocab]',3256,0,0,'enum',0,76,0,0,'2011-10-12 09:05:18',0),(3261,'Geographic region [vocab]',0,'Vocabulary derived from Geographic region in Heurist vsn 2','open',2,'Geographic region [vocab]',3261,0,0,'enum',0,10,0,0,'2011-10-12 09:05:18',0),(3263,'Honorific [vocab]',0,'Vocabulary derived from Honorific in Heurist vsn 2','open',2,'Honorific [vocab]',3263,0,0,'enum',0,103,0,0,'2011-10-12 09:05:18',0),(3273,'Date system [vocab]',0,'Vocabulary derived from Date system in Heurist vsn 2','open',2,'Date system [vocab]',3273,0,0,'enum',0,4,0,0,'2011-10-12 09:05:18',0),(3278,'Country [vocab]',0,'Vocabulary derived from Country in Heurist vsn 2','open',2,'Country [vocab]',3278,0,0,'enum',0,127,0,0,'2011-10-12 09:05:18',0),(3288,'Coordinate System [vocab]',0,'Vocabulary derived from Coordinate System in Heurist vsn 2','open',2,'Coordinate System [vocab]',3288,0,0,'enum',0,4,0,0,'2011-10-12 09:05:18',0),(3335,'Religion [vocab]',0,'Vocabulary derived from Religion in Heurist vsn 2','open',2,'Religion [vocab]',3335,0,0,'enum',0,4,0,0,'2011-10-12 09:05:18',0),(3339,'Spatial Accuracy [vocab]',0,'Vocabulary derived from Spatial Accuracy in Heurist vsn 2','open',2,'Spatial Accuracy [vocab]',3339,0,0,'enum',0,13,0,0,'2011-10-12 09:05:18',0),(3400,'Gender [vocab]',0,'Vocabulary derived from Gender in Heurist vsn 2','open',2,'Gender [vocab]',3400,0,0,'enum',0,2,0,0,'2011-10-12 09:05:18',0),(3405,'Stratigraphic',0,'','open',2,'Stratigraphic',3405,0,0,'relation',0,0,0,1,'2011-10-12 09:05:18',0),(3406,'Family',0,'','open',2,'Family',3406,0,0,'relation',0,0,0,1,'2011-10-12 09:05:18',0),(3407,'Organisational',0,'','open',2,'Organisational',3407,0,0,'relation',0,0,0,1,'2011-10-12 09:05:18',0),(3408,'Containment',0,'','open',2,'Containment',3408,0,0,'relation',0,0,0,1,'2011-10-12 09:05:18',0),(3409,'Temporal',0,'','open',2,'Temporal',3409,0,0,'relation',0,0,0,1,'2011-10-12 09:05:18',0),(3410,'Document',0,'','open',2,'Document',3410,0,0,'relation',0,0,0,1,'2011-10-12 09:05:18',0),(3411,'Christianity',0,'','open',2,'Christianity',3411,0,0,'enum',0,0,3335,1,'2011-10-12 09:05:18',0),(3412,'Islam',0,'','open',2,'Islam',3412,0,0,'enum',0,0,3335,1,'2011-10-12 09:05:18',0),(3413,'Catholic',0,'','open',2,'Catholic',3413,0,0,'enum',0,0,3411,1,'2011-10-12 09:05:18',0),(3414,'Protestant',0,'','open',2,'Protestant',3414,0,0,'enum',0,0,3411,1,'2011-10-12 09:05:18',0),(3415,'Private',0,'','open',2,'Private',3415,0,0,'enum',0,0,11,1,'2011-10-12 09:05:18',0),(3416,'Public',0,'','open',2,'Public',3416,0,0,'enum',0,0,11,1,'2011-10-12 09:05:18',0),(3417,'default symbology colour [vocab]',0,'Vocabulary derived from default symbology colour in Heurist vsn 2','open',2,'default symbology colour [vocab]',3417,0,0,'enum',0,7,0,0,'2011-10-12 09:05:18',0),(3418,'blue',0,'','open',2,'blue',3418,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3419,'green',0,'','open',2,'green',3419,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3420,'ltblue',0,'','open',2,'ltblue',3420,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3421,'orange',0,'','open',2,'orange',3421,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3422,'purple',0,'','open',2,'purple',3422,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3423,'red',0,'','open',2,'red',3423,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3424,'yellow',0,'','open',2,'yellow',3424,0,0,'enum',0,0,3417,1,'2011-10-12 09:05:18',0),(3425,'Colour',0,'','open',2,'Colour',3425,0,0,'enum',0,0,0,1,'2011-10-12 09:05:18',0),(3426,'Red',0,'','open',2,'Red',3426,0,0,'enum',0,0,3425,1,'2011-10-12 09:05:18',0),(3427,'Green',0,'','open',2,'Green',3427,0,0,'enum',0,0,3425,1,'2011-10-12 09:05:18',0),(3428,'Blue',0,'','open',2,'Blue',3428,0,0,'enum',0,0,3425,1,'2011-10-12 09:05:18',0),(3429,'File schema',0,'A file format/schema such as TEI, DocBook, PDF, Bitmap eg for use in annotation','open',2,'File schema',3429,0,0,'enum',0,0,0,1,'2011-10-12 09:05:18',0),(3430,'Text',0,'','open',2,'Text',3430,0,0,'enum',0,0,3429,1,'2011-10-12 09:05:18',0),(3431,'Bitmap',0,'','open',2,'Bitmap',3431,0,0,'enum',0,0,3429,1,'2011-10-12 09:05:18',0),(3432,'PDF',0,'','open',2,'PDF',3432,0,0,'enum',0,0,3430,1,'2011-10-12 09:05:18',0),(3433,'XML',0,'','open',2,'XML',3433,0,0,'enum',0,0,3430,1,'2011-10-12 09:05:18',0),(3434,'TEI',0,'','open',2,'TEI',3434,0,0,'enum',0,0,3433,1,'2011-10-12 09:05:18',0),(3435,'DocBook',0,'','open',2,'DocBook',3435,0,0,'enum',0,0,3433,1,'2011-10-12 09:05:18',0),(3436,'atest',0,'','open',2,'atest',3436,0,0,'enum',0,0,0,1,'2011-10-12 09:05:18',0),(3437,'Other',0,'','open',2,'Other',3437,0,0,'enum',0,0,3400,1,'2011-10-12 09:05:18',0),(3438,'Unknown',0,'','open',2,'Unknown',3438,0,0,'enum',0,0,3400,1,'2011-10-12 09:05:18',0),(3439,'IsGrandparentOf',3440,'','open',2,'IsGrandparentOf',3439,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3440,'IsGrandchildOf',3439,'','open',2,'IsGrandchildOf',3440,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3441,'IsSisterOf',3442,'','open',2,'IsSisterOf',3441,0,0,'relation',0,0,3442,1,'2011-10-12 09:05:18',0),(3442,'IsSiblingOf',3442,'','open',2,'IsSiblingOf',3442,0,0,'relation',0,0,3406,1,'2011-10-12 09:05:18',0),(3443,'Fills',3096,'','open',2,'Fills',3443,0,0,'relation',0,0,3405,1,'2011-10-12 09:05:18',0);
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
  CONSTRAINT `fk_dtl_RecID` FOREIGN KEY (`dtl_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dtl_DetailTypeID` FOREIGN KEY (`dtl_DetailTypeID`) REFERENCES `defDetailTypes` (`dty_ID`) ON UPDATE CASCADE,
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
		if NEW.dtl_DetailTypeID=4 then
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
		if NEW.dtl_DetailTypeID=4 then
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
  ulf_FilePath varchar(1024) default NULL COMMENT 'The path where the uploaded file is stored',
  ulf_FileName varchar(512) default NULL COMMENT 'The filename for the uploaded file',
  PRIMARY KEY  (`ulf_ID`),
  KEY `ulf_ObfuscatedFileIDKey` (`ulf_ObfuscatedFileID`),
  KEY `ulf_Description` (`ulf_Description`(100)),
  KEY `ulf_UploaderUGrpID` (`ulf_UploaderUGrpID`),
  KEY `ulf_MimeExt` (`ulf_MimeExt`),
  CONSTRAINT `fk_ulf_UploaderUGrpID` FOREIGN KEY (`ulf_UploaderUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ulf_MimeExt` FOREIGN KEY (`ulf_MimeExt`) REFERENCES `defFileExtToMimetype` (`fxm_Extension`) ON UPDATE CASCADE
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
  `sys_NewRecOwnerGrpID` smallint(5) unsigned NOT NULL default '0' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  `sys_NewRecAccess` enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  `sys_SetPublicToPendingOnEdit` tinyint(1) unsigned NOT NULL default '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''',
  `sys_ConstraintDefaultBehavior` enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL default 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  `sys_AllowRegistration` tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, people can apply for registration through web-based form',
  `sys_MediaFolders` varchar(10000) default NULL COMMENT 'Additional comma-sep directories which can contain files indexed in database',
  PRIMARY KEY  (`sys_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Identification/version for this Heurist database (single rec';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysIdentification`
--

LOCK TABLES `sysIdentification` WRITE;
/*!40000 ALTER TABLE `sysIdentification` DISABLE KEYS */;
INSERT INTO `sysIdentification` VALUES (1,0,1,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'Please enter a DB name ...',NULL,'Please define ownership and rights here ...',NULL,NULL,'0',0,0,NULL,NULL,NULL,0,'viewable',0,'locktypetotype',0,NULL);
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
INSERT INTO `sysTableLastUpdated` VALUES ('defCalcFunctions','0000-00-00 00:00:00',1),('defCrosswalk','0000-00-00 00:00:00',1),('defDetailTypeGroups','2011-10-12 20:05:18',1),('defDetailTypes','2011-10-12 20:05:19',1),('defFileExtToMimetype','0000-00-00 00:00:00',1),('defLanguages','0000-00-00 00:00:00',1),('defOntologies','0000-00-00 00:00:00',1),('defRecStructure','2011-10-12 20:05:19',1),('defRecTypeGroups','2011-10-12 20:05:18',1),('defRecTypes','2011-10-12 20:05:18',1),('defRelationshipConstraints','0000-00-00 00:00:00',1),('defTerms','2011-10-12 20:05:18',1),('defTranslations','0000-00-00 00:00:00',1),('defURLPrefixes','0000-00-00 00:00:00',1),('sysDBNameCache','0000-00-00 00:00:00',1),('sysIdentification','0000-00-00 00:00:00',1),('sysUGrps','2011-10-12 20:08:09',1),('sysUsrGrpLinks','0000-00-00 00:00:00',1),('usrHyperlinkFilters','0000-00-00 00:00:00',1),('usrTags','0000-00-00 00:00:00',1);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='Users/Groups diff. by ugr_Type. May defer to similar table i';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysUGrps`
--

LOCK TABLES `sysUGrps` WRITE;
/*!40000 ALTER TABLE `sysUGrps` DISABLE KEYS */;
INSERT INTO `sysUGrps` VALUES (0,'workgroup','Everyone','Group 0 represents all logged in users. DO NOT DELETE.',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=0','every','user',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2011-10-12 09:05:14'),(1,'workgroup','Database owners','Group 1 owns databases by default. DO NOT DELETE.',NULL,'PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=1','db','owners',NULL,NULL,NULL,NULL,NULL,NULL,'y',NULL,3,0,0,NULL,NULL,NULL,0,'2011-10-12 09:05:13'),(2,'user','guest','Guest User and Owner/Adminstrator',NULL,'b1oUCBYVkfUxQ','guest@null','Guest','User','None','None','','','','... ','y',NULL,3,0,0,NULL,NULL,NULL,0,'2011-10-12 09:08:09');
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
  CONSTRAINT `fk_ugl_UserID` FOREIGN KEY (`ugl_UserID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ugl_GroupID` FOREIGN KEY (`ugl_GroupID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ';
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `sysUsrGrpLinks`
--

LOCK TABLES `sysUsrGrpLinks` WRITE;
/*!40000 ALTER TABLE `sysUsrGrpLinks` DISABLE KEYS */;
INSERT INTO `sysUsrGrpLinks` VALUES (1,2,1,'admin');
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
  CONSTRAINT `fk_bkm_UGrpID` FOREIGN KEY (`bkm_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bkm_RecID` FOREIGN KEY (`bkm_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
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
  CONSTRAINT `fk_rtl_TagID` FOREIGN KEY (`rtl_TagID`) REFERENCES `usrTags` (`tag_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rtl_RecID` FOREIGN KEY (`rtl_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
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
  `rre_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'ID of user who used the record',
  `rre_RecID` int(10) unsigned NOT NULL COMMENT 'ID of recently used record',
  `rre_Time` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Timestamp of use of records, notably those searched for with pointer field',
  UNIQUE KEY `rre_composite` (`rre_UGrpID`,`rre_RecID`),
  KEY `rre_RecID` (`rre_RecID`),
  CONSTRAINT `fk_rre_UGrpID` FOREIGN KEY (`rre_UGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rre_RecID` FOREIGN KEY (`rre_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `usrRecentRecords`
--

LOCK TABLES `usrRecentRecords` WRITE;
/*!40000 ALTER TABLE `usrRecentRecords` DISABLE KEYS */;
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
  CONSTRAINT `fk_rem_RecID` FOREIGN KEY (`rem_RecID`) REFERENCES `Records` (`rec_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_OwnerUGrpID` FOREIGN KEY (`rem_OwnerUGrpID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_ToWorkgroupID` FOREIGN KEY (`rem_ToWorkgroupID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rem_ToUserID` FOREIGN KEY (`rem_ToUserID`) REFERENCES `sysUGrps` (`ugr_ID`) ON DELETE CASCADE ON UPDATE CASCADE
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2011-10-12  9:10:25
