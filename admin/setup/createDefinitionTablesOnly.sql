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
 -- createDefinitionTablesOnly.sql: SQL file to create the definition tables for use in crosswalking
 -- @author Ian Johnson 4/8/2011
 -- @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 -- @link: http://HeuristScholar.org
 -- @license http://www.gnu.org/licenses/gpl-3.0.txt
 -- @package Heurist academic knowledge management system
 -- @todo

 -- The rest of this file is a MySQL table creation script for the 10 (eventually more?)
 -- database definition and user/groups tables, stripped of anything superfluous
 -- NOTE: do not include referential integrity as this is only a subet of tables
 -- and we only need the structures

-- defCalcFunctions, defCrosswalk, defDetailTypes,defFileExtToMimetype, defLanguages, defOntologies,
-- defRecStructure, defRecTypeGroups, defRecTypes, defRelationshipConstraints,
-- defTerms, defTranslations, sysIdentification, sysUGrps, sysUsrGrpLinks, usrTags

-- ***** THIS FILE MUST BE UPDATED IF THE DATABASE STRUCTURE IS CHANGED, see version # below
--       Extract the relevant tables from blankDBStructure.sql

-- ***** Database version: 1.1  @ 13/9/2011 ******

-- -------------------------------------------------------------------------------------------------------

-- SQL below copied from blankDBStructure.sql
-- Delete Records table plus everything below defURLPrefixes

-- -------------------------------------------------------------------------------------------------------

-- phpMyAdmin SQL Dump

-- phpMyAdmin SQL Dump
-- version 2.9.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Oct 19, 2012 at 12:14 PM
-- Server version: 5.0.51
-- PHP Version: 5.2.3
-- 
-- Database: 'hdb_H3CoreDefinitions'
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table 'defCalcFunctions'
-- 

CREATE TABLE defCalcFunctions (
  cfn_ID smallint(3) unsigned NOT NULL auto_increment COMMENT 'Primary key of defCalcFunctions table',
  cfn_Domain enum('calcfieldstring','pluginphp') NOT NULL default 'calcfieldstring' COMMENT 'Domain of application of this function specification',
  cfn_FunctionSpecification text NOT NULL COMMENT 'A function or chain of functions, or some PHP plugin code',
  cfn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (cfn_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Specifications for generating calculated fields, plugins and';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defCrosswalk'
-- 

CREATE TABLE defCrosswalk (
  crw_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key',
  crw_SourcedbID mediumint(8) unsigned NOT NULL COMMENT 'The Heurist reference ID of the database containing the definition being mapped',
  crw_SourceCode mediumint(8) unsigned NOT NULL COMMENT 'The code of the definition in the source database',
  crw_DefType enum('rectype','constraint','detailtype','recstructure','ontology','vocabulary','term') NOT NULL COMMENT 'The type of code being mapped between the source and this database',
  crw_LocalCode mediumint(8) unsigned NOT NULL COMMENT 'The corresponding code in the local database',
  crw_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date when this mapping added or modified',
  PRIMARY KEY  (crw_ID),
  UNIQUE KEY crw_composite (crw_SourcedbID,crw_DefType,crw_LocalCode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Map the codes used in this Heurist DB to codes used in other';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defDetailTypeGroups'
-- 

CREATE TABLE defDetailTypeGroups (
  dtg_ID tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Primary ID - Code for detail type groups',
  dtg_Name varchar(63) NOT NULL COMMENT 'Descriptive heading to be displayed for each group of details (fields)',
  dtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists',
  dtg_Description varchar(255) NOT NULL COMMENT 'General description fo this group of detail (field) types',
  dtg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (dtg_ID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Groups detail types for display in separate sections of edit';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defDetailTypes'
-- 

CREATE TABLE defDetailTypes (
  dty_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Code for the detail type (field) - may vary between Heurist DBs',
  dty_Name varchar(255) NOT NULL COMMENT 'The canonical (standard) name of the detail type, used as default in edit form',
  dty_Documentation varchar(5000) default 'Please document the nature of this detail type (field)) ...' COMMENT 'Documentation of the detail type, what it means, how defined',
  dty_Type enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude') NOT NULL COMMENT 'The value-type of this detail type, what sort of data is stored',
  dty_HelpText varchar(255) NOT NULL default 'Please provide a short explanation for the user ...' COMMENT 'The default help text displayed to the user under the field',
  dty_ExtendedDescription varchar(5000) default 'Please provide an extended description for display on rollover ...' COMMENT 'Extended text describing this detail type, for display in rollover',
  dty_EntryMask text COMMENT 'Data entry mask, use to control decimals ion numeric values, content of text fields etc.',
  dty_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  dty_OriginatingDBID mediumint(8) unsigned default NULL COMMENT 'Database where this detail type originated, 0 = locally',
  dty_NameInOriginatingDB varchar(255) default NULL COMMENT 'Name used in database where this detail type originated',
  dty_IDInOriginatingDB smallint(5) unsigned default NULL COMMENT 'ID used in database where this detail type originated',
  dty_DetailTypeGroupID tinyint(3) unsigned NOT NULL default '1' COMMENT 'The general role of this detail allowing differentiated lists of detail types',
  dty_OrderInGroup tinyint(3) unsigned default '0' COMMENT 'The display order of DetailType within group, alphabetic if equal values',
  dty_JsonTermIDTree varchar(5000) default NULL COMMENT 'Tree of Term IDs to show for this field (display-only header terms set in HeaderTermIDs)',
  dty_TermIDTreeNonSelectableIDs varchar(1000) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  dty_PtrTargetRectypeIDs varchar(63) default NULL COMMENT 'CSVlist of target Rectype IDs, null = any',
  dty_FieldSetRectypeID smallint(5) unsigned default NULL COMMENT 'For a FieldSetMarker, the record type to be inserted as a fieldset',
  dty_ShowInLists tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if detail type is to be shown in end-user interface, 1=yes',
  dty_NonOwnerVisibility enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in ALL record types (overrides rst_VisibleOutsideGroup)',
  dty_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  dty_LocallyModified tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (dty_ID),
  UNIQUE KEY dty_Name (dty_Name),
  KEY dty_Type (dty_Type),
  KEY dty_DetailTypeGroupID (dty_DetailTypeGroupID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='The detail types (fields) which can be attached to records';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defFileExtToMimetype'
-- 

CREATE TABLE defFileExtToMimetype (
  fxm_Extension varchar(10) NOT NULL COMMENT 'The file extension, indicates mimetype, icon and some beahviours',
  fxm_MimeType varchar(100) NOT NULL COMMENT 'The standard mime type string',
  fxm_OpenNewWindow tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag if a new window should be opened to display this mimetype',
  fxm_IconFileName varchar(31) default NULL COMMENT 'Filename of the icon file for this mimetype (shared by several)',
  fxm_FiletypeName varchar(31) default NULL COMMENT 'A textual name for the file type represented by the extension',
  fxm_ImagePlaceholder varchar(63) default NULL COMMENT 'Thumbnail size representation for display, generate from fxm_FiletypeName',
  fxm_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (fxm_Extension)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Converts extensions to mimetypes and provides icons and mime';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defLanguages'
-- 

CREATE TABLE defLanguages (
  lng_NISOZ3953 char(3) NOT NULL COMMENT 'Three character NISO Z39.53 language code',
  lng_ISO639 char(2) NOT NULL COMMENT 'Two character ISO639 language code',
  lng_Name varchar(63) NOT NULL COMMENT 'Language name, generally accepted name (normally English terminology)',
  lng_Notes varchar(1000) default NULL COMMENT 'URL reference to, or notes on the definition of the language',
  lng_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (lng_NISOZ3953)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Language list including optional standard language codes';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defOntologies'
-- 

CREATE TABLE defOntologies (
  ont_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Ontology code, primary key',
  ont_ShortName varchar(63) NOT NULL COMMENT 'The commonly used acronym or short name of the ontology',
  ont_FullName varchar(255) NOT NULL COMMENT 'The commonly used full name of the ontology',
  ont_Description varchar(1000) default NULL COMMENT 'An optional descriptuion of the domain, origina and aims of the ontology',
  ont_RefURI varchar(250) default NULL COMMENT 'The URI to a definition of the ontology',
  ont_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  ont_OriginatingDBID mediumint(8) unsigned default NULL COMMENT 'Database where this ontology originated, 0 = locally',
  ont_NameInOriginatingDB varchar(63) default NULL COMMENT 'Name used in database where this ontology originated',
  ont_IDInOriginatingDB smallint(5) unsigned default NULL COMMENT 'ID used in database where this ontology originated',
  ont_Order tinyint(3) unsigned zerofill NOT NULL default '255' COMMENT 'Ordering value to define alternate display order in interface',
  ont_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  ont_locallyModified tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (ont_ID),
  UNIQUE KEY ont_ShortName (ont_ShortName)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='A table of references to different ontologies used by Heuris';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRecStructure'
-- 

CREATE TABLE defRecStructure (
  rst_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Primary key for the record structures table',
  rst_RecTypeID smallint(5) unsigned NOT NULL COMMENT 'The record type to which this detail is allocated, 0 = all rectypes',
  rst_DetailTypeID smallint(5) unsigned NOT NULL COMMENT 'Detail type for this field or, if MSB set, FieldSet code + 32767',
  rst_DisplayName varchar(255) NOT NULL default 'Please enter a prompt ...' COMMENT 'Display name for this dtl type in this rectype, autofill with dty_Name',
  rst_DisplayHelpText varchar(255) default NULL COMMENT 'The user help text to be displayed for this detail type for this record type',
  rst_DisplayExtendedDescription varchar(5000) default NULL COMMENT 'The rollover text to be displayed for this detail type for this record type',
  rst_DisplayOrder smallint(3) unsigned zerofill NOT NULL default '999' COMMENT 'A sort order for display of this detail type in the record edit form',
  rst_DisplayWidth tinyint(3) unsigned NOT NULL default '50' COMMENT 'The field width displayed for this detail type in this record type',
  rst_DefaultValue varchar(63) default NULL COMMENT 'The default value for this detail type for this record type',
  rst_RecordMatchOrder tinyint(1) unsigned NOT NULL default '0' COMMENT 'Indicates order of significance in detecting duplicate records, 1 = highest',
  rst_CalcFunctionID tinyint(3) unsigned default NULL COMMENT 'FK to table of function specifications for calculating string values',
  rst_RequirementType enum('required','recommended','optional','forbidden') NOT NULL default 'optional',
  rst_NonOwnerVisibility enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in a specified record type',
  rst_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rst_MayModify enum('locked','discouraged','open') NOT NULL default 'open' COMMENT 'Extent to which detail may be modified within this record structure',
  rst_OriginatingDBID mediumint(8) unsigned default NULL COMMENT 'Database where this record structure element originated, 0 = locally',
  rst_IDInOriginatingDB smallint(5) unsigned default NULL COMMENT 'ID used in database where this record structure element originated',
  rst_MaxValues tinyint(3) unsigned default NULL COMMENT 'Maximum number of values per record for this detail, NULL = unlimited, 0 = not allowed',
  rst_MinValues tinyint(3) unsigned NOT NULL default '0' COMMENT 'If required, minimum number of values per record for this detail',
  rst_DisplayDetailTypeGroupID tinyint(3) unsigned default NULL COMMENT 'If set, places detail in specified group instead of according to dty_DetailTypeGroup',
  rst_FilteredJsonTermIDTree varchar(500) default NULL COMMENT 'JSON encoded tree of allowed terms, subset of those defined in defDetailType',
  rst_PtrFilteredIDs varchar(250) default NULL COMMENT 'Allowed Rectypes (CSV) within list defined by defDetailType (for pointer details)',
  rst_OrderForThumbnailGeneration tinyint(3) unsigned default NULL COMMENT 'Priority order of fields to use in generating thumbnail, null = do not use',
  rst_TermIDTreeNonSelectableIDs varchar(255) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  rst_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  rst_LocallyModified tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (rst_ID),
  UNIQUE KEY rst_composite (rst_RecTypeID,rst_DetailTypeID),
  KEY rst_DetailTypeID (rst_DetailTypeID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='The record details (fields) required for each record type';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRecTypeGroups'
-- 

CREATE TABLE defRecTypeGroups (
  rtg_ID tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Record type group ID referenced in defRectypes',
  rtg_Name varchar(40) NOT NULL COMMENT 'Name for this group of record types, shown as heading in lists',
  rtg_Domain enum('functionalgroup','modelview') NOT NULL default 'functionalgroup' COMMENT 'Functional group (rectype has only one) or a Model/View group',
  rtg_Order tinyint(3) unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists',
  rtg_Description varchar(250) default NULL COMMENT 'A description of the record type group and its purpose',
  rtg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (rtg_ID),
  UNIQUE KEY rtg_Name (rtg_Name)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Grouping mechanism for record types in pulldowns';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRecTypes'
-- 

CREATE TABLE defRecTypes (
  rty_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Record type code, widely used to reference record types, primary key',
  rty_Name varchar(63) NOT NULL COMMENT 'The name which is used to describe this record (object) type',
  rty_OrderInGroup tinyint(3) unsigned default '0' COMMENT 'Ordering within record type display groups for pulldowns',
  rty_Description varchar(5000) NOT NULL COMMENT 'Description of this record type',
  rty_TitleMask varchar(500) NOT NULL default '[title]' COMMENT 'Mask to build a composite title by combining field values',
  rty_CanonicalTitleMask varchar(500) default '160' COMMENT 'Version of the mask converted to detail codes for processing',
  rty_Plural varchar(63) default NULL COMMENT 'Plural form of the record type name, manually entered',
  rty_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rty_OriginatingDBID mediumint(8) unsigned default NULL COMMENT 'Database where this record type originated, 0 = locally',
  rty_NameInOriginatingDB varchar(63) default NULL COMMENT 'Name used in database where this record type originated',
  rty_IDInOriginatingDB smallint(5) unsigned default NULL COMMENT 'ID in database where this record type originated',
  rty_NonOwnerVisibility enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows blanket restriction of visibility of a particular record type',
  rty_ShowInLists tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if record type is to be shown in end-user interface, 1=yes',
  rty_RecTypeGroupID tinyint(3) unsigned NOT NULL default '1' COMMENT 'Record type group to which this record type belongs',
  rty_RecTypeModelIDs varchar(63) default NULL COMMENT 'The model group(s) to which this rectype belongs, comma sep. list',
  rty_FlagAsFieldset tinyint(1) unsigned NOT NULL default '0' COMMENT '0 = full record type, 1 = Fieldset = set of fields to include in other rectypes',
  rty_ReferenceURL varchar(250) default NULL COMMENT 'A reference URL describing/defining the record type',
  rty_AlternativeRecEditor varchar(63) default NULL COMMENT 'Name or URL of alternative record editor function to be used for this rectype',
  rty_Type enum('normal','relationship','dummy') NOT NULL default 'normal' COMMENT 'Use to flag special record types to trigger special functions',
  rty_ShowURLOnEditForm tinyint(1) NOT NULL default '1' COMMENT 'Determines whether special URL field is shown at the top of the edit form',
  rty_ShowDescriptionOnEditForm tinyint(1) NOT NULL default '1' COMMENT 'Determines whether the record type description field is shown at the top of the edit form',
  rty_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  rty_LocallyModified tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (rty_ID),
  UNIQUE KEY rty_Name (rty_Name),
  KEY rty_RecTypeGroupID (rty_RecTypeGroupID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Defines record types, which corresponds with a set of detail';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRelationshipConstraints'
-- 

CREATE TABLE defRelationshipConstraints (
  rcs_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Record-detailtype constraint table primary key',
  rcs_SourceRectypeID smallint(5) unsigned default NULL COMMENT 'Source record type for this constraint, Null = all types',
  rcs_TargetRectypeID smallint(5) unsigned default NULL COMMENT 'Target record type pointed to by relationship record, Null = all types',
  rcs_Description varchar(1000) default 'Please describe ...',
  rcs_RelationshipsLimit tinyint(3) unsigned default NULL COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet',
  rcs_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rcs_OriginatingDBID mediumint(8) unsigned NOT NULL default '0' COMMENT 'Database where this constraint originated, 0 or local db code = locally',
  rcs_IDInOriginatingDB smallint(5) unsigned default '0' COMMENT 'Code used in database where this constraint originated',
  rcs_TermID int(10) unsigned default NULL COMMENT 'The ID of a term to be constrained, applies to descendants unless they have more specific',
  rcs_TermLimit tinyint(2) unsigned default NULL COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used',
  rcs_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  rcs_LocallyModified tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (rcs_ID),
  UNIQUE KEY rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermID),
  KEY rcs_TermID (rcs_TermID),
  KEY rcs_TargetRectypeID (rcs_TargetRectypeID),
  KEY rcs_SourceRecTypeID (rcs_SourceRectypeID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Constrain target-rectype/vocabularies/values for a pointer d';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defTerms'
-- 

CREATE TABLE defTerms (
  trm_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key, the term code used in the detail record',
  trm_Label varchar(500) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank',
  trm_InverseTermId int(10) unsigned default NULL COMMENT 'ID for the inverse value (relationships), null if no inverse',
  trm_Description varchar(1000) default NULL COMMENT 'A description/gloss on the meaning of the term',
  trm_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  trm_OriginatingDBID mediumint(8) unsigned default NULL COMMENT 'Database where this detail type originated, 0 = locally',
  trm_NameInOriginatingDB varchar(63) default NULL COMMENT 'Name (label) for this term in originating database',
  trm_IDInOriginatingDB mediumint(8) unsigned default NULL COMMENT 'ID used in database where this  term originated',
  trm_AddedByImport tinyint(1) unsigned NOT NULL default '0' COMMENT 'Set to 1 if term added by an import, otherwise 0',
  trm_IsLocalExtension tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag that this value not in the externally referenced vocabulary',
  trm_Domain enum('enum','relation') NOT NULL default 'enum' COMMENT 'Define the usage of the term',
  trm_OntID smallint(5) unsigned NOT NULL default '0' COMMENT 'Ontology from which this vocabulary originated, 0 = locally defined ontology',
  trm_ChildCount tinyint(3) NOT NULL default '0' COMMENT 'Stores the count of children, updated whenever children are added/removed',
  trm_ParentTermID int(10) unsigned default NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  trm_Depth tinyint(1) unsigned NOT NULL default '1' COMMENT 'Depth of term in the term tree, should always be 1+parent depth',
  trm_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  trm_LocallyModified tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  trm_Code varchar(100) default NULL COMMENT 'Optional code eg. alphanumeric code which may be required for import or export',
  PRIMARY KEY  (trm_ID),
  KEY trm_ParentTermIDKey (trm_ParentTermID),
  KEY trm_InverseTermIDKey (trm_InverseTermId)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Terms by detail type and the vocabulary they belong to';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defTranslations'
-- 

CREATE TABLE defTranslations (
  trn_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key of defTranslations table',
  trn_Source enum('rty_Name','dty_Name','ont_ShortName','vcb_Name','trm_Label','rst_DisplayName','rtg_Name','dtl_Value') NOT NULL COMMENT 'The table/column to be translated (unique names identify source)',
  trn_Code smallint(5) unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
  trn_LanguageCode3 char(3) NOT NULL COMMENT 'The translation language code (NISO 3 character) for this record',
  trn_Translation varchar(63) NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
  trn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (trn_ID),
  UNIQUE KEY trn_composite (trn_Source,trn_Code,trn_LanguageCode3),
  KEY trn_LanguageCode3 (trn_LanguageCode3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Translation table into multiple languages for all translatab';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defURLPrefixes'
-- 

CREATE TABLE defURLPrefixes (
  urp_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'ID which will be stored as proxy for the URL prefix',
  urp_Prefix varchar(250) NOT NULL COMMENT 'URL prefix which is prepended to record URLs',
  urp_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (urp_ID),
  UNIQUE KEY urp_Prefix (urp_Prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Common URL prefixes allowing single-point change of URL for ';


