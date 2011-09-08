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

-- ***** Database version: 3.1  @ 4/8/2011 ******

-- phpMyAdmin SQL Dump
-- version 2.9.0.2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 06, 2011 at 07:46 PM
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
  dty_Type enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker') NOT NULL COMMENT 'The value-type of this detail type, what sort of data is stored',
  dty_HelpText varchar(255) NOT NULL default 'Please provide a short explanation for the user ...' COMMENT 'The default help text displayed to the user under the field',
  dty_ExtendedDescription varchar(5000) default 'Please provide an extended description for display on rollover ...' COMMENT 'Extended text describing this detail type, for display in rollover',
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
  dty_NonOwnerVisibility enum('hidden','viewable','public') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in ALL record types (overrides rst_VisibleOutsideGroup)',
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
  rst_NonOwnerVisibility enum('hidden','viewable','public') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in a specified record type',
  rst_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rst_MayModify enum('locked','discouraged','open') NOT NULL default 'open' COMMENT 'Extent to which detail may be modified within this record structure',
  rst_OriginatingDBID mediumint(8) unsigned default NULL COMMENT 'Database where this record structure element originated, 0 = locally',
  rst_IDInOriginatingDB smallint(5) unsigned default NULL COMMENT 'ID used in database where this record structure element originated',
  rst_MaxValues tinyint(3) unsigned NOT NULL default '0' COMMENT 'Maximum number of values per record for this detail, 0 = unlimited',
  rst_MinValues tinyint(3) unsigned NOT NULL default '0' COMMENT 'If required, minimum number of values per record for this detail',
  rst_DisplayDetailTypeGroupID tinyint(3) unsigned default NULL COMMENT 'If set, places detail in specified group instead of according to dty_DetailTypeGroup',
  rst_FilteredJsonTermIDTree varchar(500) default NULL COMMENT 'JSON encoded tree of allowed terms, subset of those defined in defDetailType',
  rst_PtrFilteredIDs varchar(250) default NULL COMMENT 'Allowed Rectypes (CSV) within list defined by defDetailType (for pointer details)',
  rst_OrderForThumbnailGeneration tinyint(3) unsigned default NULL COMMENT 'Priority order of fields to use in generating thumbnail, null = do not use',
  rst_TermIDTreeNonSelectableIDs varchar(255) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  PRIMARY KEY  (rst_ID),
  UNIQUE KEY rst_composite (rst_RecTypeID,rst_DetailTypeID),
  KEY rst_DetailTypeID (rst_DetailTypeID),
  KEY rst_DetailTypeID_2 (rst_DetailTypeID)
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
  rty_NonOwnerVisibility enum('hidden','viewable','public') NOT NULL default 'viewable' COMMENT 'Allows blanket restriction of visibility of a particular record type',
  rty_ShowInLists tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if record type is to be shown in end-user interface, 1=yes',
  rty_RecTypeGroupID tinyint(3) unsigned NOT NULL default '1' COMMENT 'Record type group to which this record type belongs',
  rty_RecTypeModelIDs varchar(63) default NULL COMMENT 'The model group(s) to which this rectype belongs, comma sep. list',
  rty_FlagAsFieldset tinyint(1) unsigned NOT NULL default '0' COMMENT '0 = full record type, 1 = Fieldset = set of fields to include in other rectypes',
  rty_ReferenceURL varchar(250) default NULL COMMENT 'A reference URL describing/defining the record type',
  rty_AlternativeRecEditor varchar(63) default NULL COMMENT 'Name or URL of alternative record editor function to be used for this rectype',
  rty_Type enum('normal','relationship','dummy') NOT NULL default 'normal' COMMENT 'Use to flag special record types to trigger special functions',
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
  PRIMARY KEY  (rcs_ID),
  UNIQUE KEY rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermID),
  KEY rcs_TermID (rcs_TermID),
  KEY rcs_TargetRectypeID (rcs_TargetRectypeID),
  KEY rcs_SourceRectypeID (rcs_SourceRectypeID),
  KEY rcs_TargetRectypeID_2 (rcs_TargetRectypeID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Constrain target-rectype/vocabularies/values for a pointer d';

-- --------------------------------------------------------

--
-- Table structure for table 'defTerms'
--

CREATE TABLE defTerms (
  trm_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key, the term code used in the detail record',
  trm_Label varchar(63) NOT NULL COMMENT 'Text label for the term, cannot be blank',
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
  PRIMARY KEY  (urp_ID),
  UNIQUE KEY urp_Prefix (urp_Prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Common URL prefixes allowing single-point change of URL for ';

--
-- Table structure for table 'sysIdentification'
--

CREATE TABLE sysIdentification (
  sys_ID tinyint(1) unsigned NOT NULL default '1' COMMENT 'Only 1 record should exist in this table',
  sys_dbRegisteredID int(10) unsigned NOT NULL default '0' COMMENT 'Allocated by HeuristScholar.org, 0 indicates not yet registered',
  sys_dbVersion tinyint(3) unsigned NOT NULL default '0' COMMENT 'Major version for the database structure',
  sys_dbSubVersion tinyint(3) unsigned NOT NULL default '0' COMMENT 'Sub version',
  sys_dbSubSubVersion tinyint(3) unsigned NOT NULL default '0' COMMENT 'Sub-sub version',
  sys_eMailImapServer varchar(100) default NULL COMMENT 'Email server intermediary for record creation via email',
  sys_eMailImapPort varchar(5) default NULL COMMENT 'port for imap email server',
  sys_eMailImapProtocol varchar(5) default NULL COMMENT 'protocol for imap email server',
  sys_eMailImapUsername varchar(50) default NULL COMMENT 'user name for imap email server',
  sys_eMailImapPassword varchar(40) default NULL COMMENT 'password for imap email server',
  sys_IncomingEmailAddresses varchar(4000) default NULL COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins',
  sys_TargetEmailAddresses varchar(255) default NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses',
  sys_UGrpsDatabase varchar(63) default NULL COMMENT 'Full name of SQL database containing user tables, null = use internal users/groups tables',
  sys_OwnerGroupID smallint(5) unsigned NOT NULL default '1' COMMENT 'User group which owns/administers this database, 1 by default',
  sys_dbName varchar(63) NOT NULL default 'Please enter a DB name ...' COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  sys_dbOwner varchar(250) default NULL COMMENT 'Information on the owner of the database, may be a URL reference',
  sys_dbRights varchar(1000) NOT NULL default 'Please define ownership and rights here ...' COMMENT 'A statement of ownership and copyright for this database and content',
  sys_dbDescription varchar(1000) default NULL COMMENT 'A longer description of the content of this database',
  sys_SyncDefsWithDB varchar(63) default NULL COMMENT 'The name of the SQL database with which local definitions are to be synchronised',
  sys_AutoIncludeFieldSetIDs varchar(63) default '0' COMMENT 'CSV list of fieldsets which are included in all rectypes',
  sys_RestrictAccessToOwnerGroup tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, database may only be accessed by members of owners group',
  sys_URLCheckFlag tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags whether system should send out requests to URLs to test for validity',
  sys_UploadDirectory varchar(128) default NULL COMMENT 'Absolute directory path for uploaded files (blank = use default from installation)',
  sys_hmlOutputDirectory varchar(255) default NULL COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory',
  sys_htmlOutputDirectory varchar(255) default NULL COMMENT 'Directory in which to write html representation of published records, default to html within upload directory',
  sys_NewRecOwnerGrpID smallint(5) unsigned NOT NULL default '0' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  sys_NewRecAccess enum('viewable','hidden','public') NOT NULL default 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  sys_ConstraintDefaultBehavior enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL default 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  sys_AllowRegistration tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, people can apply for registration through web-based form',
  sys_MediaFolders varchar(10000) default NULL COMMENT 'Additional comma-sep directories which can contain files indexed in database',
  PRIMARY KEY  (sys_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Identification/version for this Heurist database (single rec';

-- --------------------------------------------------------

--
-- Table structure for table 'sysUGrps'
--

CREATE TABLE sysUGrps (
  ugr_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'User or group ID, used wherever a user or group is to be identified',
  ugr_Type enum('user','workgroup','ugradclass') NOT NULL default 'user' COMMENT 'User or workgroup, special workgroup types also supported',
  ugr_Name varchar(63) NOT NULL COMMENT 'The unique user/login/group name, user name defaults to email address',
  ugr_LongName varchar(128) default NULL COMMENT 'An optional longer descriptive name for a user group',
  ugr_Description varchar(1000) default NULL COMMENT 'Extended description of a user group displayed on homepage',
  ugr_Password varchar(40) NOT NULL COMMENT 'Encrypted password string',
  ugr_eMail varchar(100) NOT NULL COMMENT 'Contact email address of the user/group',
  ugr_FirstName varchar(40) default NULL COMMENT 'Person''s first name, only for Users, not Workgroups',
  ugr_LastName varchar(63) default NULL COMMENT 'Person''s last name, only for Users, not Workgroups',
  ugr_Department varchar(120) default NULL,
  ugr_Organisation varchar(120) default NULL,
  ugr_City varchar(63) default NULL,
  ugr_State varchar(40) default NULL,
  ugr_Postcode varchar(20) default NULL,
  ugr_Interests varchar(255) default NULL COMMENT 'List of research interests, only for Users, not Workgroups',
  ugr_Enabled enum('y','n') NOT NULL default 'y' COMMENT 'Flags if user can use Heurist, normally needs authorising by admin',
  ugr_LastLoginTime datetime default NULL COMMENT 'Date and time of last login (but user may stay logged in)',
  ugr_MinHyperlinkWords tinyint(3) unsigned NOT NULL default '3' COMMENT 'Filter hyperlink strings with less than this word count on hyperlink import ',
  ugr_LoginCount int(10) unsigned NOT NULL default '0' COMMENT 'Number of times user haslogged in',
  ugr_IsModelUser tinyint(1) unsigned NOT NULL default '0' COMMENT '1 indicates model user = domain profile',
  ugr_IncomingEmailAddresses varchar(4000) default NULL COMMENT 'Comma-sep list of incoming email addresses from which to archive emails',
  ugr_TargetEmailAddresses varchar(255) default NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also sys_TargetEmailAddresses',
  ugr_URLs varchar(2000) default NULL COMMENT 'URL(s) of group or personal website(s), comma separated',
  ugr_FlagJT int(1) unsigned NOT NULL default '0' COMMENT 'Flag to enable in Jobtrack/Worktrack application',
  PRIMARY KEY  (ugr_ID),
  UNIQUE KEY ugr_Name (ugr_Name),
  UNIQUE KEY ugr_eMail (ugr_eMail)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Users/Groups diff. by ugr_Type. May defer to similar table i';

-- --------------------------------------------------------

--
-- Table structure for table 'sysUsrGrpLinks'
--

CREATE TABLE sysUsrGrpLinks (
  ugl_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key for user-to-group membership',
  ugl_UserID smallint(5) unsigned NOT NULL COMMENT 'The user being assigned to a group',
  ugl_GroupID smallint(5) unsigned NOT NULL COMMENT 'The group to which this user is being assigned',
  ugl_Role enum('admin','member') NOT NULL default 'member' COMMENT 'The role of this user in the group - member, admin',
  PRIMARY KEY  (ugl_ID),
  UNIQUE KEY ugl_CompositeKey (ugl_UserID,ugl_GroupID),
  KEY ugl_GroupID (ugl_GroupID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ';

-- --------------------------------------------------------


--
-- Table structure for table 'usrHyperlinkFilters'
--

CREATE TABLE usrHyperlinkFilters (
  hyf_String varchar(63) NOT NULL COMMENT 'Hyperlink string to be ignored when encountered in hyperlink import',
  hyf_UGrpID smallint(5) unsigned NOT NULL COMMENT 'User for which this string is to be ignored',
  UNIQUE KEY hyf_CompositeKey (hyf_String,hyf_UGrpID),
  KEY hyf_UGrpID (hyf_UGrpID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Configure hyperlink import to ignore common link strings';

-- --------------------------------------------------------

--
-- Table structure for table 'usrRecTagLinks'
--

-- --------------------------------------------------------

--
-- Table structure for table 'usrTags'
--

CREATE TABLE usrTags (
  tag_ID mediumint(8) unsigned NOT NULL auto_increment,
  tag_UGrpID smallint(5) unsigned NOT NULL COMMENT 'User or workgroup id of tag owner',
  tag_Text varchar(63) NOT NULL COMMENT 'The value (text) of the tag provided by the user or workgroup administrator',
  tag_Description varchar(250) default NULL COMMENT 'Description of the concept to which this tag is attached, optional',
  tag_AddedByImport tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag as to whether this tag was added by an import (1) or by editing (0)',
  PRIMARY KEY  (tag_ID),
  UNIQUE KEY tag_composite_key (tag_Text,tag_UGrpID),
  KEY tag_UGrpID (tag_UGrpID),
  KEY tag_Text (tag_Text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Personal and user group tags (formerly keywords)';

