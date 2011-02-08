
 -- populateBlankDB.sql: SQL file to create Heurist structures in a blank MySQL database
 -- @author Ian Johnson 11/1/2011
 -- @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 -- @link: http://HeuristScholar.org
 -- @license http://www.gnu.org/licenses/gpl-3.0.txt
 -- @package Heurist academic knowledge management system
 -- @todo
 
 \W -- warnings to standard out

-- --------------------------------------------------------

-- TO DO: After creating the structure from this file we need to:
--
--        1. create referential constraints with AddReferentialConstraints.sql
--        2. add stored procedures from AddProceduresTriggers.sql
--           TO DO: review these files to make sure they run cleanly on new DB
--        3. import content (database definitions) from
--           HeuristScholar.org/?db=hdb_reference and if that is offline, read
--           the same info from fallbackDefinitionsIfOffline.txt

-- ------------------------------------------------------------------------------------
-- The remainder of this file is simply a PHPMyAdmin EXPORT of H3 database structure
-- ------------------------------------------------------------------------------------

-- REMAINDER OF FILE FROM EXPORT 8TH Feb 2011, hdb_sandpit2

-- 
-- Table structure for table 'Records'
-- 

CREATE TABLE Records (
  rec_ID int(10) unsigned NOT NULL auto_increment COMMENT 'The primary record ID, also called, historically, bib_id',
  rec_URL varchar(2000) default NULL COMMENT 'The primary URL pointed to by this record (particularly for Internet bookmarks)',
  rec_Added datetime default '0000-00-00 00:00:00' COMMENT 'Date and time record added',
  rec_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time the record was modified',
  rec_Title varchar(1023) NOT NULL COMMENT 'Composite (constructed) title of the record, used for display and search',
  rec_ScratchPad text COMMENT 'Scratchpad, mainly for text captured with bookmarklet',
  rec_RecTypeID smallint(5) unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
  rec_AddedByUGrpID smallint(5) unsigned NOT NULL COMMENT 'ID of the user who created the record',
  rec_AddedByImport tinyint(1) NOT NULL default '0' COMMENT 'Whether added by an import (value 1) or by manual entry (value 0)',
  rec_Popularity int(11) NOT NULL default '0' COMMENT 'Calculated popularity rating for sorting order, set by cron job',
  rec_FlagTemporary tinyint(1) default '0' COMMENT 'Flags a partially created record before fully populated',
  rec_OwnerUGrpID smallint(5) unsigned NOT NULL default '1' COMMENT 'User group which owns this record, 1 = everyone',
  rec_NonOwnerVisibility enum('Viewable','Hidden') default 'Viewable' COMMENT 'Defines if record visible outside owning user group(s)',
  rec_URLLastVerified datetime default NULL COMMENT 'Last date time when URL was verified as contactable',
  rec_URLErrorMessage varchar(255) default NULL COMMENT 'Error returned by URL checking script for bad/inaccessible URLs',
  rec_Hash varchar(60) default NULL COMMENT 'A composite truncated metaphones + numeric values hash of significant fields',
  PRIMARY KEY  (rec_ID),
  KEY rec_URLKey (rec_URL(64)),
  KEY rec_TitleKey (rec_Title(64)),
  KEY rec_RecTypeKey (rec_RecTypeID),
  KEY rec_ModifiedKey (rec_Modified),
  KEY rec_UGrpIDKey (rec_OwnerUGrpID),
  KEY rec_HashKey (rec_Hash(40)),
  KEY rec_AddedByUGrpID (rec_AddedByUGrpID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Table structure for table 'archiveDetails'
-- 

CREATE TABLE archiveDetails (
  ard_RecID int(11) NOT NULL COMMENT 'Record ID to which these details apply',
  ard_ID int(11) NOT NULL COMMENT 'The detail ID (Details.dtl_ID) for this detail, not necessary to archive',
  ard_Ver int(10) unsigned NOT NULL default '0' COMMENT 'Version number for this detail, probably tied to record archive',
  ard_DetailTypeID smallint(6) default NULL COMMENT 'Detailtype for this detail',
  ard_ValueAsText mediumtext COMMENT 'Value of this detail',
  ard_UploadedFileID int(11) default NULL COMMENT 'File id for this detail',
  ard_Geo geometry default NULL COMMENT 'Geometry for this detail',
  KEY ard_record_id_key (ard_RecID),
  KEY ard_id_key (ard_ID),
  KEY ard_ver_key (ard_Ver)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Records past versions of detail records to allow rollback';

-- --------------------------------------------------------

-- 
-- Table structure for table 'archiveRecords'
-- 

CREATE TABLE archiveRecords (
  arec_ID int(11) unsigned NOT NULL COMMENT 'The record ID for this record',
  arec_Ver int(10) unsigned NOT NULL auto_increment COMMENT 'The version number for this record',
  arec_UGrpID int(11) default NULL COMMENT 'The user id of the user who deleted? the record',
  arec_Date datetime default NULL COMMENT 'The datestamp of this version',
  arec_URL mediumtext COMMENT 'The URL recorded in this version of the record',
  arec_Title varchar(255) default NULL COMMENT 'The title of this version of the record',
  arec_ScratchPad mediumtext COMMENT 'The scratchpad from this version of the record',
  arec_RecTypeID smallint(5) unsigned default NULL COMMENT 'The record type for this version of the record',
  PRIMARY KEY  (arec_ID,arec_Ver)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Records past versions of records to allow rollback';

-- --------------------------------------------------------

-- 
-- Table structure for table 'archiveTagCreations'
-- 

CREATE TABLE archiveTagCreations (
  atc_ID int(11) NOT NULL COMMENT 'Primary key for tag creations',
  atc_UGrpID smallint(5) unsigned NOT NULL COMMENT 'Creator (user id) or owner of this tag',
  atc_Name varchar(64) NOT NULL COMMENT 'The name/label of the tag',
  atc_Created datetime default NULL COMMENT 'Date of creation of the tag',
  atc_CreatorID int(11) default NULL COMMENT 'Creator (user id) or owner of this tag'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Records the creation of user/workgroup tags to allow rollbac';

-- --------------------------------------------------------

-- 
-- Table structure for table 'archiveTagDeletions'
-- 

CREATE TABLE archiveTagDeletions (
  atd_ID int(11) NOT NULL COMMENT 'Primary key for tag deletions',
  atd_UGrpID smallint(5) unsigned NOT NULL COMMENT 'Creator (user id) or owner of this tag',
  atd_Name varchar(64) NOT NULL COMMENT 'The name/label of the tag',
  atd_Deleted datetime default NULL COMMENT 'Date of deletion of the tag',
  atd_DeletorID int(11) default NULL COMMENT 'Deletor (user id) of this tag'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Records the deletion of user/workgroup tags to allow rollbac';

-- --------------------------------------------------------

-- 
-- Table structure for table 'archiveTagLinkCreations'
-- 

CREATE TABLE archiveTagLinkCreations (
  atlc_ID int(10) unsigned NOT NULL COMMENT 'Primary key for record-tag links creation',
  atlc_BookmarkID int(10) unsigned NOT NULL COMMENT 'ID of bookmark which is being tagged',
  atlc_TagID int(10) unsigned NOT NULL COMMENT 'ID of tag being linked to record/bookmark',
  atlc_RecID int(11) NOT NULL COMMENT 'ID of record which is being tagged',
  atlc_Created datetime default NULL COMMENT 'Date of creation of the record-tag link',
  atlc_CreatorID int(11) default NULL COMMENT 'Creator (user id) or owner of this record-tag link'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Records the creation of record-tag links to allow rollback';

-- --------------------------------------------------------

-- 
-- Table structure for table 'archiveTagLinkDeletions'
-- 

CREATE TABLE archiveTagLinkDeletions (
  atld_ID int(10) unsigned NOT NULL COMMENT 'Primary key for record-tag links deletion',
  atld_BookmarkID int(10) unsigned NOT NULL COMMENT 'ID of bookmark which is being tagged',
  atld_TagID int(10) unsigned NOT NULL COMMENT 'ID of tag being linked to record/bookmark',
  atld_RecID int(11) NOT NULL COMMENT 'ID of record which is being tagged',
  atld_Deleted datetime default NULL COMMENT 'Date of deletion of the record-tag link',
  atld_DeletorID int(11) default NULL COMMENT 'Deletor (user id) of this record-tag link'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Records the deletion of record-tag links to allow rollback';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defCrosswalk'
-- 

CREATE TABLE defCrosswalk (
  crw_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key',
  crw_SourcedbID smallint(5) unsigned NOT NULL COMMENT 'The Heurist reference ID of the database containing the definition being mapped',
  crw_SourceCode mediumint(8) unsigned NOT NULL COMMENT 'The code of the definition in the source database',
  crw_DefType enum('rectype','constraint','detailtype','recstructure','ontology','vocabulary','term') NOT NULL COMMENT 'The type of code being mapped between the source and this database',
  crw_LocalCode mediumint(8) unsigned NOT NULL COMMENT 'The corresponding code in the local database',
  crw_OrigindbID smallint(5) unsigned default '0' COMMENT 'Database in which this definition originated, passed on with definition',
  crw_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date when this mapping added or modified',
  PRIMARY KEY  (crw_ID),
  UNIQUE KEY crw_composite_key (crw_SourcedbID,crw_DefType,crw_LocalCode)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Map the codes used in this Heurist DB to codes used in other';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defDetailTypeGroups'
-- 

CREATE TABLE defDetailTypeGroups (
  dtg_ID tinyint(3) unsigned NOT NULL auto_increment COMMENT 'Primary ID - Code for detail type groups',
  dtg_Name varchar(64) NOT NULL COMMENT 'Descriptive heading to be displayed for each group of details (fields)',
  dtg_Description varchar(255) NOT NULL COMMENT 'General description fo this group of detail (field) types',
  dtg_Order tinyint(3) unsigned zerofill NOT NULL default '255' COMMENT 'Ordering of detail type groups eg. on the edit screen',
  PRIMARY KEY  (dtg_ID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Groups detail types for display in separate sections of edit';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defDetailTypes'
-- 

CREATE TABLE defDetailTypes (
  dty_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Code for the detail type (field) - may vary between Heurist DBs',
  dty_Name varchar(255) NOT NULL COMMENT 'The canonical (standard) name of the detail type',
  dty_Description varchar(5000) NOT NULL default 'Please provide a description ...' COMMENT 'A description of the detail type, what it means, how defined',
  dty_Type enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','resource','float','file','geo','separator') NOT NULL COMMENT 'The field type/value type of the detail type',
  dty_FunctionalGroup tinyint(3) unsigned NOT NULL default '1' COMMENT 'The section of the edit form in which this detail will occur',
  dty_OverrideGroup tinyint(3) unsigned default NULL COMMENT 'Allowsdetail to be allocated to a different group for a specific record type',
  dty_VisibleToEndUser tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if this detail type shoudl be displayed in end-user interface',
  dty_Prompt varchar(255) NOT NULL COMMENT 'The default prompt displayed to the user',
  dty_Help varchar(5000) NOT NULL default 'Please provide a help text ...' COMMENT 'The default extended text describing this detail type',
  dty_Status enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  dty_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this detail type originated, 0 or local db code = locally',
  dty_PtrTargetRectypeIDs varchar(250) default NULL COMMENT 'CSVlist of target Rectype IDs, null = any',
  dty_EnumVocabIDs varchar(255) default NULL COMMENT 'Vocabularies to use for an enum detail, may be further constrained in defRecStructure',
  dty_EnumTermIDs varchar(255) default NULL COMMENT 'Additional terms to use for an enum detail, may be further constrained in defRecStructure',
  dty_NameInOriginatingDB varchar(63) NOT NULL default '' COMMENT 'Name in database where this detail type originated',
  dty_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'Code in database where this detail typeoriginated',
  PRIMARY KEY  (dty_ID),
  UNIQUE KEY dty_NameKey (dty_Name),
  KEY dty_TypeKey (dty_Type),
  KEY dty_FunctionalGroup (dty_FunctionalGroup)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='The detail types (fields) which can be attached to records';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defFileExtToMimetype'
-- 

CREATE TABLE defFileExtToMimetype (
  fxm_Extension varchar(10) NOT NULL COMMENT 'The file extension, indicates mimetype, icon and some beahviours',
  fxm_MimeType varchar(100) NOT NULL COMMENT 'The standard mime type string',
  fxm_OpenNewWindow tinyint(1) NOT NULL default '0' COMMENT 'Flag if a new window should be opened to display this mimetype',
  fxm_IconFileName varchar(31) default NULL COMMENT 'Filename of the icon file for this mimetype (shared by several)',
  fxm_FiletypeName varchar(31) default NULL COMMENT 'A textual name for the file type represented by the extension',
  fxm_ImagePlaceholder varchar(64) default NULL COMMENT 'Thumbnail size representation for display, generate from fxm_FiletypeName',
  PRIMARY KEY  (fxm_Extension)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Converts extensions to mimetypes and provides icons and mime';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defOntologies'
-- 

CREATE TABLE defOntologies (
  ont_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Ontology code, primary key',
  ont_ShortName varchar(128) NOT NULL COMMENT 'The commonly used acronym or short name of the ontology',
  ont_FullName varchar(128) NOT NULL COMMENT 'The commonly used full name of the ontology',
  ont_Description varchar(1000) default NULL COMMENT 'An optional descriptuion of the domain, origina and aims of the ontology',
  ont_RefURI varchar(250) default NULL COMMENT 'The URI to a definition of the ontology',
  ont_Status enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  ont_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this ontology originated, 0 = locally',
  ont_NameInOriginatingDB varchar(64) NOT NULL default '' COMMENT 'Name used in database where this ontology originated',
  ont_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'ID used in database where this ontology originated',
  ont_added date default NULL COMMENT 'Date of addition of this ontology record',
  ont_modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this ontology record',
  PRIMARY KEY  (ont_ID),
  UNIQUE KEY ont_ShortName (ont_ShortName)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='A table of references to different ontologies used by Heuris';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRecStructure'
-- 

CREATE TABLE defRecStructure (
  rst_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Primary key for the record structures table',
  rst_RecTypeID smallint(5) unsigned NOT NULL COMMENT 'The record type to which this detail is allocated',
  rst_DetailTypeID smallint(5) unsigned NOT NULL COMMENT 'The detail type required (or otherwise) by this record type',
  rst_DisplayName varchar(255) default NULL COMMENT 'Display name for this dtl type in this rectype, null=use name in defDetailtype table',
  rst_DisplayDescription varchar(1000) default NULL COMMENT 'The extended description to be displayed for this detail type in this record type',
  rst_DisplayPrompt varchar(100) default NULL COMMENT 'The user prompt to be displayed for this detail type in this record type',
  rst_DisplayHelp varchar(255) default NULL COMMENT 'The help text to be displayed for this detail type in this record type',
  rst_FunctionalGroupOverride tinyint(3) unsigned default NULL COMMENT 'If set, locates the detail in specified DetailTypeGroup instead of the one specified in dty_FunctionalGroup',
  rst_DisplayOrder smallint(5) unsigned NOT NULL default '999' COMMENT 'A sort order for display of this detail type in the record edit form',
  rst_DisplayWidth tinyint(3) unsigned default '30' COMMENT 'The field width displayed for this detail type in this record type',
  rst_DefaultValue varchar(64) default NULL COMMENT 'The default value for this detail type for this record type',
  rst_RecordMatchOrder tinyint(1) unsigned NOT NULL default '0' COMMENT 'Indicates order of significance in detecting duplicate records, 1 = highest',
  rst_RequirementType enum('Required','Recommended','Optional','Forbidden') NOT NULL default 'Optional',
  rst_Status enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rst_MayModify enum('Locked','Discouraged','Open') NOT NULL default 'Open' COMMENT 'Extent to which detail may be modified within this record structure',
  rst_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this record structure originated, 0 or local db code = locally',
  rst_MaxValues tinyint(3) unsigned NOT NULL default '1' COMMENT 'Maximum number of values per record for this detail, 0 = unlimited ',
  rst_MinValues tinyint(3) unsigned NOT NULL default '1' COMMENT 'If required, minimum number of values for this detail per record',
  rst_EnumConstraintIDs varchar(250) default NULL COMMENT 'enum detail type only: CSV list of allowed terms from within the Vocab defined in defDetailType',
  rst_PtrConstraintIDs varchar(100) default NULL COMMENT 'Pointer detail types only: CSV list of allowed rectypes within list defined by defDetailType',
  rst_ThumbnailFromDetailTypeID smallint(5) unsigned default '0' COMMENT 'Generate thumbnail from given multimedia record pointer detail, 0 = from record URL target',
  rst_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'Code in database where this record type structure originated',
  PRIMARY KEY  (rst_ID),
  UNIQUE KEY rst_CompositeKey (rst_RecTypeID,rst_DetailTypeID),
  KEY defRecStructure_ibfk_2 (rst_DetailTypeID)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='The record details (fields) required for each record type';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRecTypeGroups'
-- 

CREATE TABLE defRecTypeGroups (
  rtg_ID tinyint(2) unsigned NOT NULL auto_increment COMMENT 'Record type group ID referenced in defRectypes',
  rtg_Name varchar(40) NOT NULL COMMENT 'Name for this group of record types',
  rtg_Order tinyint(3) unsigned zerofill NOT NULL default '255' COMMENT 'Ordering of record type groups within pulldown lsits',
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
  rty_OrderInGroup tinyint(3) unsigned NOT NULL default '0' COMMENT 'Used to logically order the bibliographic record types',
  rty_Description varchar(5000) NOT NULL COMMENT 'Description of this record type',
  rty_RecTypeGroupID tinyint(3) unsigned NOT NULL default '1' COMMENT 'Display group in Rectype pulldowns, startign with bibliographic',
  rty_TitleMask varchar(500) NOT NULL default '[title]' COMMENT 'Mask to build a composite title by combining field values',
  rty_CanonicalTitleMask varchar(500) default '160' COMMENT 'Version of the mask converted to detail codes for processing',
  rty_Plural varchar(63) default NULL COMMENT 'Plural form of the record type name, manually entered',
  rty_Status enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rty_VisibleToEndUser tinyint(1) unsigned NOT NULL default '1' COMMENT 'Flags if record type is to be shown in lists, 1=yes, 2=no',
  rty_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this record type originated, 0 or local db code = locally',
  rty_ReferenceURL varchar(250) default NULL COMMENT 'A reference URL describing/defining the record type',
  rty_NameInOriginatingDB varchar(63) NOT NULL COMMENT 'Name in database where this record type originated',
  rty_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'Code in database where this record type originated',
  PRIMARY KEY  (rty_ID),
  UNIQUE KEY rty_Name (rty_Name)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Defines record types, which corresponds with a set of detail';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defRelationshipConstraints'
-- 

CREATE TABLE defRelationshipConstraints (
  rcs_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'Record-detailtype constraint table primary key',
  rcs_DetailtypeID smallint(5) unsigned default NULL COMMENT 'RelMarker detail type to be constrained, 200 = enum for vanilla relationship',
  rcs_SourceRectypeID smallint(5) unsigned default NULL COMMENT 'Source record type for this constraint, 0 = all types',
  rcs_TargetRectypeID smallint(5) unsigned default NULL COMMENT 'Target record type pointed to by relationship record, 0 = any type',
  rcs_VocabSubset varchar(1024) collate utf8_unicode_ci default NULL COMMENT 'CVSList of Term IDs to use from Vocab, ignored if term not in vocab',
  rcs_VocabID smallint(5) unsigned NOT NULL default '1' COMMENT 'Vocab to use, msut be in the vocabs defined for this source-target pair',
  rcs_Description varchar(1000) collate utf8_unicode_ci default 'Please describe ...',
  rcs_Order tinyint(3) unsigned default NULL COMMENT 'Ordering value to allow controlling the display order of the vocabularies',
  rcs_RelationshipsLimit tinyint(1) unsigned NOT NULL default '0' COMMENT '0 = no limit; 1, 2 ... = maximum # of relations per record',
  rcs_Status enum('Reserved','Approved','Pending','Open') collate utf8_unicode_ci NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rcs_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this constraint originated, 0 or local db code = locally',
  rcs_TermLimit tinyint(3) unsigned NOT NULL default '0' COMMENT '0 = No limit, 1, 2 = max # of relations which can use each term',
  rcs_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'Code in database where this constraint originated',
  PRIMARY KEY  (rcs_ID),
  KEY rcs_VocabID (rcs_VocabID),
  KEY rcs_DetailtypeID_2 (rcs_DetailtypeID),
  KEY rcs_TargetRectypeID_2 (rcs_TargetRectypeID),
  KEY rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_DetailtypeID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Constrain target-rectype/vocabularies/values for a pointer d';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defTerms'
-- 

CREATE TABLE defTerms (
  trm_ID int(11) unsigned NOT NULL auto_increment COMMENT 'Primary key, the term code used in the detail record',
  trm_Label varchar(63) NOT NULL COMMENT 'Text label for the term, cannot be blank',
  trm_InverseTermId mediumint(8) unsigned default NULL COMMENT 'ID for the inverse value (relationships), null if no inverse',
  trm_VocabID smallint(5) unsigned NOT NULL COMMENT 'The vocabulary to which this term belongs',
  trm_Description varchar(500) default NULL COMMENT 'A description/gloss on the meaning of the term',
  trm_Status enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  trm_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this detail type originated, 0 = local db code = locally',
  trm_AddedByImport tinyint(1) unsigned default '0' COMMENT 'Set to 1 if term added by an import, otherwise 0',
  trm_LocalExtension tinyint(10) unsigned NOT NULL default '0' COMMENT 'Flag that this value not in the externally referenced vocabulary',
  trm_NameInOriginatingDB varchar(63) NOT NULL default '' COMMENT 'Name in database where this record type originated',
  trm_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'Code in database where this  term originated',
  PRIMARY KEY  (trm_ID),
  UNIQUE KEY trm_composite_key (trm_VocabID,trm_Label),
  UNIQUE KEY trm_InverseTermIDKey (trm_InverseTermId)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Terms by detail type and the vocabulary they belong to';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defTranslations'
-- 

CREATE TABLE defTranslations (
  trn_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key of defTranslations table',
  trn_Source enum('rty_Name','dty_Name','ont_ShortName','vcb_Name','trm_Label','rst_DisplayName','rtg_Name') NOT NULL COMMENT 'The table/column to be translated (unique names identify source)',
  trn_Code smallint(5) unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
  trn_Language enum('Arabic','French','Italian','Spanish','Portuguese','German','Greek','Turkish','Russian','Swedish','Finish','Latvian','Estonian','Hungarian','Czech','Polish','Serbian','Croatian','Swahili','Chinese','Indonesian','Hindi','Japanese','Malaysian','Korean','Vietnamese','Thai','Cambodian') NOT NULL COMMENT 'The translation language for this record',
  trn_Translation varchar(250) NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
  PRIMARY KEY  (trn_ID),
  UNIQUE KEY trn_SourceCodeLanguageKey (trn_Source,trn_Code,trn_Language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Translation table into multiple languages for all translatab';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defURLPrefixes'
-- 

CREATE TABLE defURLPrefixes (
  urp_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'ID which will be stored as proxy for the URL prefix',
  urp_Prefix varchar(250) NOT NULL COMMENT 'URL prefix which is prepended to record URLs',
  PRIMARY KEY  (urp_ID),
  UNIQUE KEY urp_PrefixKey (urp_Prefix)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Common URL prefixes allowing single-point change of URL for ';

-- --------------------------------------------------------

-- 
-- Table structure for table 'defVocabularies'
-- 

CREATE TABLE defVocabularies (
  vcb_ID smallint(5) unsigned NOT NULL COMMENT 'Vocabulary primary ID, referenced in terms and constraints',
  vcb_Name varchar(64) NOT NULL COMMENT 'Name of the vocabulary',
  vcb_Description varchar(1000) NOT NULL default 'Please insert a description of this vocabulary' COMMENT 'A description of the purpose and nature of the vocabulary',
  vcb_RefURL varchar(250) default NULL COMMENT 'reference URL for the vocabulary definition',
  vcb_Added date default NULL COMMENT 'Date of addition of this vocabulary record',
  vcb_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this vocabulary record',
  vcb_Status enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  vcb_OriginatingDBID smallint(5) unsigned NOT NULL default '0' COMMENT 'Database where this record vocabulary originated, 0 = local db code = locally',
  vcb_OntID smallint(5) unsigned NOT NULL default '0' COMMENT 'Ontology from which this vocabulary originated, 0 = locally defined ontology ',
  vcb_Domain enum('RelationshipTypes','EnumValues') NOT NULL default 'EnumValues' COMMENT 'Indicates whetthjer vocab is used for relationship types or ordinary enum fields',
  vcb_NameInOriginatingDB varchar(63) NOT NULL default '' COMMENT 'Name in database where this vocabulary originated',
  vcb_IDInOriginatingDB smallint(5) unsigned NOT NULL default '0' COMMENT 'Code in database where this vocabulary originated',
  PRIMARY KEY  (vcb_ID),
  UNIQUE KEY vcb_Name (vcb_Name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Controlled vocabularies to which terms belong';

-- --------------------------------------------------------

-- 
-- Table structure for table 'recDetails'
-- 

CREATE TABLE recDetails (
  dtl_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key for record detail (field) values table',
  dtl_RecID int(10) unsigned NOT NULL COMMENT 'The record to which this detail (field) applies',
  dtl_DetailTypeID smallint(5) unsigned NOT NULL COMMENT 'The detail type code identifying the type definition of data',
  dtl_Value text COMMENT 'The value of the detail as text (used for all except files and geometry)',
  dtl_AddedByImport tinyint(1) NOT NULL default '0' COMMENT 'Set 1 if added by an import, set 0 if added by user during data entry',
  dtl_UploadedFileID mediumint(8) unsigned default NULL COMMENT 'The numeric code = filename of an uploaded file ',
  dtl_Geo geometry default NULL COMMENT 'A geometry (spatial) object',
  dtl_ValShortened varchar(31) NOT NULL COMMENT 'Truncated version of the textual value without spaces',
  PRIMARY KEY  (dtl_ID),
  KEY dtl_DetailtypeIDkey (dtl_DetailTypeID),
  KEY dtl_RecIDKey (dtl_RecID),
  KEY dtl_ValShortenedKey (dtl_ValShortened),
  KEY dtl_ValueAsTextKey (dtl_Value(64)),
  KEY dtl_UploadedFileIDKey (dtl_UploadedFileID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='The detail (field) values for each record - public data';

-- --------------------------------------------------------

-- 
-- Table structure for table 'recForwarding'
-- 

CREATE TABLE recForwarding (
  rfw_OldRecID int(10) unsigned NOT NULL COMMENT 'The deleted record which will be redirected to another',
  rfw_NewRecID int(10) unsigned NOT NULL COMMENT 'The new record to which this ID will be forwarded',
  PRIMARY KEY  (rfw_OldRecID),
  KEY rfw_NewRecID (rfw_NewRecID)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Allows referer routine to redirect certain calls to a replac';

-- --------------------------------------------------------

-- 
-- Table structure for table 'recRelationshipsCache'
-- 

CREATE TABLE recRelationshipsCache (
  rrc_RecID int(10) unsigned NOT NULL COMMENT 'Record ID of a relationships record linking source and target records',
  rrc_SourceRecID int(10) unsigned NOT NULL COMMENT 'Pointer to source record for this relationship',
  rrc_TargetRecID int(10) unsigned NOT NULL COMMENT 'Pointer to target record for this relationship',
  PRIMARY KEY  (rrc_RecID),
  KEY rrc_sourcePtrKey (rrc_SourceRecID),
  KEY rrc_TargetPtrKey (rrc_TargetRecID)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='A cache for record relationship pointers to speed access';

-- --------------------------------------------------------

-- 
-- Table structure for table 'recSimilarButNotDupes'
-- 

CREATE TABLE recSimilarButNotDupes (
  snd_SimRecsList varchar(3000) NOT NULL COMMENT 'A comma separated list of record IDs which are similar but not identical',
  KEY snd_SimRecsList (snd_SimRecsList(13))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Used in dedupe. Sets of IDs which are not dupes. Index is of';

-- --------------------------------------------------------

-- 
-- Table structure for table 'recThreadedComments'
-- 

CREATE TABLE recThreadedComments (
  cmt_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Comment ID, primary key for comments',
  cmt_Text varchar(5000) NOT NULL COMMENT 'Text of comment',
  cmt_OwnerUgrpID smallint(5) unsigned NOT NULL COMMENT 'User ID of user making comment',
  cmt_Added datetime default '0000-00-00 00:00:00' COMMENT 'Date and time of creation of comment',
  cmt_ParentCmtID int(11) unsigned default NULL COMMENT 'Parent comment of this comment',
  cmt_Deleted tinyint(1) NOT NULL default '0' COMMENT 'Flag deleted comments',
  cmt_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of comment',
  cmt_RecID int(10) unsigned NOT NULL COMMENT 'Record ID to which this comment applies, required',
  PRIMARY KEY  (cmt_ID),
  KEY recThreadedComments_ibfk_1 (cmt_OwnerUgrpID),
  KEY recThreadedComments_ibfk_2 (cmt_ParentCmtID),
  KEY recThreadedComments_ibfk_4 (cmt_RecID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Threaded comments for each record';

-- --------------------------------------------------------

-- 
-- Table structure for table 'recUploadedFiles'
-- 

CREATE TABLE recUploadedFiles (
  ulf_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'A unique numeric file ID used as filename to store the data on disk',
  ulf_OrigFileName varchar(255) NOT NULL COMMENT 'The original name of the file uploaded',
  ulf_UploaderUGrpID smallint(5) unsigned NOT NULL COMMENT 'The user who uploaded the file',
  ulf_Added datetime default '0000-00-00 00:00:00' COMMENT 'The date and time the file was uploaded',
  ulf_ObfuscatedFileID varchar(40) default NULL COMMENT 'SHA-1 hash of ulf_ID and random number to block sequential file harvesting',
  ulf_Thumbnail blob COMMENT 'Cached autogenerated thumbnail for common image formats',
  ulf_Description text COMMENT 'A user-entered textual description of the file or image contents',
  ulf_AddedByImport tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flag whether added by import = 1 or manual editing = 0',
  ulf_MimeExt varchar(6) default NULL COMMENT 'Extension of the file, used to look up in mimetype table',
  ulf_FileSizeKB int(10) unsigned default NULL COMMENT 'File size in Kbytes calculated at upload',
  temp varchar(64) default NULL,
  PRIMARY KEY  (ulf_ID),
  KEY ulf_ObfuscatedFileIDKey (ulf_ObfuscatedFileID),
  KEY ulf_UploaderUGrpID (ulf_UploaderUGrpID),
  KEY ulf_MimeExt (ulf_MimeExt),
  KEY ulf_DescriptionKey (ulf_Description(100))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Index to uploaded files linked from records';

-- --------------------------------------------------------

-- 
-- Table structure for table 'sysIdentification'
-- 

CREATE TABLE sysIdentification (
  sys_ID tinyint(3) unsigned NOT NULL COMMENT 'Only 1 record should exist in this table',
  sys_dbRegisteredID int(10) unsigned NOT NULL default '0' COMMENT 'Allocated by HeuristScholar.org, 0 indicates not yet registered',
  sys_dbVersion tinyint(3) unsigned NOT NULL default '3' COMMENT 'Major version for the database structure',
  sys_dbSubVersion tinyint(3) unsigned NOT NULL default '0' COMMENT 'Sub version',
  sys_dbSubSubVersion tinyint(3) unsigned NOT NULL default '0' COMMENT 'Sub-sub version',
  sys_eMailImapServer varchar(100) collate utf8_unicode_ci default NULL COMMENT 'Email server intermediary for record creation via email',
  sys_eMailImapPort varchar(5) collate utf8_unicode_ci default NULL COMMENT 'port for imap email server',
  sys_eMailImapProtocol varchar(5) collate utf8_unicode_ci default NULL COMMENT 'protocol for imap email server',
  sys_eMailImapUsername varchar(50) collate utf8_unicode_ci default NULL COMMENT 'user name for imap email server',
  sys_eMailImapPassword varchar(20) collate utf8_unicode_ci default NULL COMMENT 'password for imap email server',
  sys_UGrpsDatabase varchar(64) collate utf8_unicode_ci default NULL COMMENT 'Name of SQL database containing user tables, null = use internal users/groups tables',
  sys_OwnerGroupID int(11) unsigned NOT NULL default '1' COMMENT 'User group which owns this database, 2 by default',
  sys_RestrictAccessToOwnerGroup tinyint(3) unsigned NOT NULL default '0' COMMENT 'If set, only members of the owner group can gain access',
  sys_dbName varchar(64) collate utf8_unicode_ci NOT NULL COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  sys_dbOwner varchar(250) collate utf8_unicode_ci NOT NULL COMMENT 'Information on the owner of the database, may be a URL reference',
  sys_dbRights varchar(1000) collate utf8_unicode_ci NOT NULL COMMENT 'A statement of ownership and copyright for this database and content',
  sys_dbDescription varchar(1000) collate utf8_unicode_ci default NULL COMMENT 'A longer description of the content of this database',
  sys_SyncDefsWithDb varchar(64) collate utf8_unicode_ci default NULL COMMENT 'The name of the database with which local definitions are to be synchronised',
  sys_URLCheckFlag tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags whether system should send out requests to URLs to test for validity',
  sys_UploadDirectory varchar(128) collate utf8_unicode_ci default NULL COMMENT 'Full directory path for uploaded files (absolute, starts with / or drive:)',
  sys_MediaFolders varchar(10000) collate utf8_unicode_ci default NULL COMMENT 'Additional comm-sep directories which can contain files indexed in database',
  sys_AllowRegistration tinyint(1) unsigned NOT NULL default '1' COMMENT 'If set, people can apply for registration through web-based form',
  sys_NewRecOwnerGrpID smallint(5) unsigned NOT NULL default '0' COMMENT 'Group which by default owns new records - allow override per user',
  sys_NewRecAccess enum('Viewable','Hidden') collate utf8_unicode_ci NOT NULL default 'Viewable' COMMENT 'Default visibility/editability for new records - allow override per user',
  PRIMARY KEY  (sys_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Identification/version for this Heurist database (single rec';

-- --------------------------------------------------------

-- 
-- Table structure for table 'sysTableLastUpdated'
-- 

CREATE TABLE sysTableLastUpdated (
  tlu_TableName varchar(40) NOT NULL COMMENT 'Name of table for which we are recording time of last update',
  tlu_DateStamp datetime default NULL COMMENT 'Date and time of last update of table',
  tlu_CommonObj tinyint(1) unsigned NOT NULL COMMENT 'Indicates tables which contain data defs required in common-obj',
  PRIMARY KEY  (tlu_TableName)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Datestamp, determines if updates since definitions loaded in';

-- --------------------------------------------------------

-- 
-- Table structure for table 'sysUGrps'
-- 

CREATE TABLE sysUGrps (
  ugr_ID smallint(5) unsigned NOT NULL auto_increment COMMENT 'User or group ID, used wherever a user or group is to be identified',
  ugr_Type enum('User','Workgroup','Ugradclass') NOT NULL default 'User' COMMENT 'User or workgroup, special workgroup types also supported',
  ugr_Name varchar(64) NOT NULL COMMENT 'The unique user/login/group name, user name defaults to email address',
  ugr_LongName varchar(128) default NULL COMMENT 'An optional longer descriptive name for a user group',
  ugr_Description varchar(1000) default NULL COMMENT 'Extended description of a user group displayed on homepage',
  ugr_Password varchar(32) NOT NULL COMMENT 'Encrypted password string',
  ugr_eMail varchar(64) NOT NULL COMMENT 'Contact email address of the user/group',
  ugr_FirstName varchar(30) default NULL COMMENT 'Person''s first name, only for Users, not Workgroups',
  ugr_LastName varchar(40) default NULL COMMENT 'Person''s last name, only for Users, not Workgroups',
  ugr_Department varchar(120) default NULL,
  ugr_Organisation varchar(120) default NULL,
  ugr_City varchar(40) default NULL,
  ugr_State varchar(20) default NULL,
  ugr_Postcode varchar(20) default NULL,
  ugr_Interests varchar(255) default NULL COMMENT 'List of research interests, only for Users, not Workgroups',
  ugr_Enabled enum('Y','N') NOT NULL default 'N' COMMENT 'Flags if user can use Heurist, normally needs authorising by admin',
  ugr_LastLoginTime datetime default NULL COMMENT 'Date and time of last login (but user may stay logged in)',
  ugr_MinHyperlinkWords tinyint(4) NOT NULL default '3' COMMENT 'Filter hyperlink strings with less than this word count on hyperlink import ',
  ugr_LoginCount int(11) NOT NULL default '0' COMMENT 'Number of times user haslogged in',
  ugr_IsModelUser tinyint(1) NOT NULL default '0' COMMENT '1 indicates model user = domain profile',
  ugr_IncomingEmailAddresses varchar(512) default NULL COMMENT 'Comma-sep list of incoming email addresses from which to archive emails',
  ugr_URLs varchar(1000) default NULL COMMENT 'URL(s) of group or personal website(s), comma separated',
  ugr_FlagJT int(1) NOT NULL default '0' COMMENT 'Flag to enable in Jobtrack/Worktrack application',
  PRIMARY KEY  (ugr_ID),
  UNIQUE KEY ugr_NameKey (ugr_Name),
  UNIQUE KEY ugr_eMailKey (ugr_eMail)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Contains User and Workgroup records, distinguished by ugr_Ty';

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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrBookmarks'
-- 

CREATE TABLE usrBookmarks (
  bkm_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key, ID for bookmarks',
  bkm_Added datetime default NULL,
  bkm_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification',
  bkm_UGrpID smallint(5) unsigned NOT NULL COMMENT 'Owner of the bookmark - all bookmarks must be owned',
  bkm_PwdReminder varchar(250) default NULL COMMENT 'Password reminder field or short notes about access',
  bkm_RecID int(10) unsigned NOT NULL COMMENT 'The record to which this bookmark applies, must be set',
  bkm_Rating tinyint(3) unsigned NOT NULL default '0' COMMENT 'Five point rating for interest/quality/content',
  bkm_AddedByImport tinyint(1) NOT NULL default '0' COMMENT 'Set to 1 if bookmark added by import, 0 if added by data entry',
  bkm_ZoteroID int(10) unsigned default NULL COMMENT 'Records your Zotero ID for this record for synchronisation with Zotero',
  PRIMARY KEY  (bkm_ID),
  UNIQUE KEY bkm_RecID (bkm_RecID,bkm_UGrpID),
  KEY bkm_UGrpIDKey (bkm_UGrpID),
  KEY bkm_ModifiedKey (bkm_Modified)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Bookmark = personal data relating to a record, one for each ';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrHyperlinkFilters'
-- 

CREATE TABLE usrHyperlinkFilters (
  hyf_String varchar(64) NOT NULL COMMENT 'Hyperlink string to be ignored when encountered in hyperlink import',
  hyf_UGrpID smallint(5) unsigned NOT NULL COMMENT 'User for which this string is to be ignored',
  UNIQUE KEY hyf_CompositeKey (hyf_String,hyf_UGrpID),
  KEY usrHyperlinkFilter_ibfk_1 (hyf_UGrpID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Configure hyperlink import to ignore common link strings';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrRecTagLinks'
-- 

CREATE TABLE usrRecTagLinks (
  rtl_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary link table key, one tag linked to one record',
  rtl_TagID mediumint(8) unsigned NOT NULL COMMENT 'The tag being linked to the record/bookmark',
  rtl_Order tinyint(3) unsigned NOT NULL default '0' COMMENT 'Ordering of tags within the current record/bookmark',
  rtl_AddedByImport tinyint(1) NOT NULL default '0' COMMENT '0 indicates added while editing, 1 indicates added during import',
  rtl_RecID int(10) unsigned NOT NULL COMMENT 'The record to which the tag is linked, should always be set',
  PRIMARY KEY  (rtl_ID),
  UNIQUE KEY rtl_composite_key (rtl_RecID,rtl_TagID),
  KEY rtl_TagIDKey (rtl_TagID)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Link table connecting tags to records';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrRecentRecords'
-- 

CREATE TABLE usrRecentRecords (
  rre_ID int(10) unsigned NOT NULL auto_increment,
  rre_UGrpID smallint(5) unsigned NOT NULL COMMENT 'ID of user who used the record',
  rre_RecID int(10) unsigned NOT NULL COMMENT 'ID of recently used record',
  rre_Time datetime NOT NULL COMMENT 'Datetime of use of records searched for with pointer field',
  PRIMARY KEY  (rre_ID),
  KEY rre_CompositeKey (rre_UGrpID,rre_RecID),
  KEY usrRecentRecords_ibfk_2 (rre_RecID)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrReminders'
-- 

CREATE TABLE usrReminders (
  rem_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Reminder primary key',
  rem_RecID int(10) unsigned NOT NULL COMMENT 'Record about which this reminder is sent, must refer to existing',
  rem_OwnerUGrpID smallint(5) unsigned NOT NULL COMMENT 'Owner of the reminder, the person who created it',
  rem_ToWorkgroupID smallint(5) unsigned default NULL COMMENT 'The workgroup to which the reminder should be sent',
  rem_ToUserID smallint(5) unsigned default NULL COMMENT 'The individual user to whom the reminder should be sent',
  rem_ToEmail varchar(255) default NULL COMMENT 'The individual email address(es) to which the reminder should be sent',
  rem_Message varchar(1000) default NULL COMMENT 'The message to be attached to the reminder, optional',
  rem_StartDate date NOT NULL default '1970-01-01' COMMENT 'The first (or only) date for sending the reminder',
  rem_Freq enum('once','daily','weekly','monthly','annually') NOT NULL default 'once' COMMENT 'The frequency of sending reminders',
  rem_Nonce varchar(31) default NULL COMMENT 'Random number hash for reminders',
  PRIMARY KEY  (rem_ID),
  KEY usrReminders_ibfk_1 (rem_RecID),
  KEY usrReminders_ibfk_2 (rem_OwnerUGrpID),
  KEY usrReminders_ibfk_3 (rem_ToWorkgroupID),
  KEY usrReminders_ibfk_4 (rem_ToUserID)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Reminders attached to records and recipients, with start dat';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrRemindersBlockList'
-- 

CREATE TABLE usrRemindersBlockList (
  rbl_ID mediumint(8) unsigned NOT NULL auto_increment,
  rbl_RemID mediumint(8) unsigned NOT NULL COMMENT 'Reminder ID to be blocked',
  rbl_UGrpID smallint(5) unsigned NOT NULL COMMENT 'User who does not wish to receive this reminder',
  PRIMARY KEY  (rbl_ID),
  UNIQUE KEY rbl_composite_key (rbl_RemID,rbl_UGrpID),
  KEY rbl_UGrpID (rbl_UGrpID)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='Allows user to block resending of specific reminders to them';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrSavedSearches'
-- 

CREATE TABLE usrSavedSearches (
  svs_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Saved search ID, used in publishing, primary key',
  svs_Name varchar(30) NOT NULL COMMENT 'The display name for this saved search',
  svs_Added date NOT NULL default '0000-00-00' COMMENT 'Date and time saves search added',
  svs_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time saves search last modified',
  svs_Query text NOT NULL COMMENT 'The text of the saved search - added to search URL',
  svs_UGrpID smallint(5) unsigned NOT NULL COMMENT 'The creator/owner or workgroup for the saved search',
  svs_publish_args varchar(250) default '' COMMENT 'Name of XSL to lock in this publish format, blank = any XSL OK',
  PRIMARY KEY  (svs_ID),
  KEY svs_UGrpID (svs_UGrpID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Saved searches for personal/usergroup use and for publishing';

-- --------------------------------------------------------

-- 
-- Table structure for table 'usrTags'
-- 

CREATE TABLE usrTags (
  tag_ID mediumint(8) unsigned NOT NULL auto_increment,
  tag_UGrpID smallint(5) unsigned NOT NULL COMMENT 'User or workgroup id of tag owner',
  tag_Text varchar(64) NOT NULL COMMENT 'The value (text) of the tag provided by the user or workgroup administrator',
  tag_Description varchar(250) default NULL COMMENT 'Description of the concept to which this tag is attached, optional',
  tag_AddedByImport tinyint(1) NOT NULL default '0' COMMENT 'Flag as to whether this tag was added by an import (1) or by editing (0)',
  PRIMARY KEY  (tag_ID),
  UNIQUE KEY tag_composite_key (tag_Text,tag_UGrpID),
  KEY tag_UGrpIDKey (tag_UGrpID),
  KEY tag_TextKey (tag_Text)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Personal and user group tags (formerly keywords)';

-- --------------------------------------------------------

-- 
-- Table structure for table 'woot_ChunkPermissions'
-- 

CREATE TABLE woot_ChunkPermissions (
  wprm_ChunkID int(11) NOT NULL COMMENT 'ID of chunk for which permission is specified, may be repeated',
  wprm_UGrpID smallint(6) NOT NULL COMMENT 'User with specified right to this chunk',
  wprm_group_id smallint(6) NOT NULL COMMENT 'User groups with specified right to this chunk',
  wprm_Type enum('RW','RO') NOT NULL COMMENT 'Read-write or read-only permission for this chunk/user/wg',
  wprm_CreatorID smallint(6) NOT NULL COMMENT 'Creator of the permission (= user ID ???? <check>)',
  wprm_Created datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY wprm_chunk_composite_key (wprm_ChunkID,wprm_UGrpID,wprm_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Permissions value for individual woot chunks';

-- --------------------------------------------------------

-- 
-- Table structure for table 'woot_Chunks'
-- 

CREATE TABLE woot_Chunks (
  chunk_ID int(11) NOT NULL auto_increment COMMENT 'Primary ID for a version of the text chunks making up a woot entry (page)',
  chunk_WootID int(11) NOT NULL COMMENT 'The ID of the woot entry (page) to which this chunk belongs',
  chunk_InsertOrder int(11) NOT NULL COMMENT 'Order of chunk within woot.',
  chunk_Ver int(11) NOT NULL COMMENT 'A version code for the chunk, incremented when edited',
  chunk_IsLatest tinyint(1) NOT NULL COMMENT 'Presumably flags whether this is the latest version of the chunk',
  chunk_DisplayOrder int(11) NOT NULL COMMENT 'The order number of the chunk within the woot entry (page)',
  chunk_Text text COMMENT 'The actual XHTML content of the chunk',
  chunk_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the chunk',
  chunk_OwnerID int(11) default NULL COMMENT 'Owner/creator (user ID) of the chunk',
  chunk_Deleted tinyint(1) NOT NULL COMMENT 'Deletion marker for this chunk',
  chunk_EditorID int(11) NOT NULL COMMENT 'Editor (user ID) of the chunk - presumably the last person to edit',
  PRIMARY KEY  (chunk_ID),
  UNIQUE KEY chunk_composite_key (chunk_WootID,chunk_InsertOrder,chunk_Ver),
  KEY chunk_is_latest_key (chunk_IsLatest)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Table structure for table 'woot_RecPermissions'
-- 

CREATE TABLE woot_RecPermissions (
  wrprm_WootID int(11) NOT NULL COMMENT 'ID of the woot entry to which this permission applies, may be repeated',
  wrprm_UGrpID int(11) NOT NULL COMMENT 'User ID to which this permission is being granted',
  wrprm_group_id int(11) NOT NULL COMMENT 'User group ID to which this permission is being granted',
  wrprm_Type enum('RW','RO') NOT NULL COMMENT 'Type of permission being granted - read only or read-write',
  wrprm_CreatorID int(11) NOT NULL COMMENT 'Creator of the permission',
  wrprm_Created datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY wrprm_composite_key (wrprm_WootID,wrprm_UGrpID,wrprm_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Overall permissions for the woot record (entry/page)';

-- --------------------------------------------------------

-- 
-- Table structure for table 'woots'
-- 

CREATE TABLE woots (
  woot_ID int(11) NOT NULL auto_increment COMMENT 'Primary ID of a woot record/entry/page',
  woot_Title varchar(8191) default NULL COMMENT 'Name of the woot page, unique identifier of the woot page',
  woot_Created datetime default NULL COMMENT 'Date and time of creation of the woot record/entry/page',
  woot_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the woot record/entry/page',
  woot_Ver int(11) NOT NULL COMMENT 'Version of the woot record/entry/page, presumably incremented on edit',
  woot_CreatorID int(11) default NULL COMMENT 'Creator (user id) of the woot',
  PRIMARY KEY  (woot_ID),
  UNIQUE KEY woot_title_key (woot_Title(200))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Woot records (entries, pages) are linked to a set of XHTML c';

-- 
-- Constraints for dumped tables
-- 

-- 
-- Constraints for table `Records`
-- 
ALTER TABLE `Records`
  ADD CONSTRAINT Records_ibfk_1 FOREIGN KEY (rec_RecTypeID) REFERENCES defRecTypes (rty_ID),
ALTER TABLE `Records`
  ADD CONSTRAINT Records_ibfk_1 FOREIGN KEY (rec_RecTypeID) REFERENCES defRecTypes (rty_ID),  ADD CONSTRAINT Records_ibfk_2 FOREIGN KEY (rec_AddedByUGrpID) REFERENCES sysUGrps (ugr_ID),
ALTER TABLE `Records`
  ADD CONSTRAINT Records_ibfk_1 FOREIGN KEY (rec_RecTypeID) REFERENCES defRecTypes (rty_ID),  ADD CONSTRAINT Records_ibfk_2 FOREIGN KEY (rec_AddedByUGrpID) REFERENCES sysUGrps (ugr_ID),  ADD CONSTRAINT Records_ibfk_3 FOREIGN KEY (rec_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID);

-- 
-- Constraints for table `defDetailTypes`
-- 
ALTER TABLE `defDetailTypes`
  ADD CONSTRAINT defDetailTypes_ibfk_1 FOREIGN KEY (dty_FunctionalGroup) REFERENCES defDetailTypeGroups (dtg_ID),
ALTER TABLE `defDetailTypes`
  ADD CONSTRAINT defDetailTypes_ibfk_1 FOREIGN KEY (dty_FunctionalGroup) REFERENCES defDetailTypeGroups (dtg_ID),  ADD CONSTRAINT defDetailTypes_ibfk_2 FOREIGN KEY (dty_FunctionalGroup) REFERENCES defDetailTypeGroups (dtg_ID) ON UPDATE CASCADE;

-- 
-- Constraints for table `defRecStructure`
-- 
ALTER TABLE `defRecStructure`
  ADD CONSTRAINT defRecStructure_ibfk_2 FOREIGN KEY (rst_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE CASCADE,
ALTER TABLE `defRecStructure`
  ADD CONSTRAINT defRecStructure_ibfk_2 FOREIGN KEY (rst_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRecStructure_ibfk_3 FOREIGN KEY (rst_RecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `defRelationshipConstraints`
-- 
ALTER TABLE `defRelationshipConstraints`
  ADD CONSTRAINT defRelationshipConstraints_ibfk_1 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID),
ALTER TABLE `defRelationshipConstraints`
  ADD CONSTRAINT defRelationshipConstraints_ibfk_1 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_2 FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID),
ALTER TABLE `defRelationshipConstraints`
  ADD CONSTRAINT defRelationshipConstraints_ibfk_1 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_2 FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_3 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE CASCADE,
ALTER TABLE `defRelationshipConstraints`
  ADD CONSTRAINT defRelationshipConstraints_ibfk_1 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_2 FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_3 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRelationshipConstraints_ibfk_4 FOREIGN KEY (rcs_SourceRectypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE,
ALTER TABLE `defRelationshipConstraints`
  ADD CONSTRAINT defRelationshipConstraints_ibfk_1 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_2 FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_3 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRelationshipConstraints_ibfk_4 FOREIGN KEY (rcs_SourceRectypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRelationshipConstraints_ibfk_5 FOREIGN KEY (rcs_TargetRectypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE,
ALTER TABLE `defRelationshipConstraints`
  ADD CONSTRAINT defRelationshipConstraints_ibfk_1 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_2 FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID),  ADD CONSTRAINT defRelationshipConstraints_ibfk_3 FOREIGN KEY (rcs_DetailtypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRelationshipConstraints_ibfk_4 FOREIGN KEY (rcs_SourceRectypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRelationshipConstraints_ibfk_5 FOREIGN KEY (rcs_TargetRectypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE,  ADD CONSTRAINT defRelationshipConstraints_ibfk_6 FOREIGN KEY (rcs_TargetRectypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `defTerms`
-- 
ALTER TABLE `defTerms`
  ADD CONSTRAINT defTerms_ibfk_1 FOREIGN KEY (trm_VocabID) REFERENCES defVocabularies (vcb_ID),
ALTER TABLE `defTerms`
  ADD CONSTRAINT defTerms_ibfk_1 FOREIGN KEY (trm_VocabID) REFERENCES defVocabularies (vcb_ID),  ADD CONSTRAINT defTerms_ibfk_2 FOREIGN KEY (trm_VocabID) REFERENCES defVocabularies (vcb_ID);

-- 
-- Constraints for table `recDetails`
-- 
ALTER TABLE `recDetails`
  ADD CONSTRAINT recDetails_ibfk_2 FOREIGN KEY (dtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,
ALTER TABLE `recDetails`
  ADD CONSTRAINT recDetails_ibfk_2 FOREIGN KEY (dtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,  ADD CONSTRAINT recDetails_ibfk_3 FOREIGN KEY (dtl_DetailTypeID) REFERENCES defDetailTypes (dty_ID),
ALTER TABLE `recDetails`
  ADD CONSTRAINT recDetails_ibfk_2 FOREIGN KEY (dtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,  ADD CONSTRAINT recDetails_ibfk_3 FOREIGN KEY (dtl_DetailTypeID) REFERENCES defDetailTypes (dty_ID),  ADD CONSTRAINT recDetails_ibfk_4 FOREIGN KEY (dtl_UploadedFileID) REFERENCES recUploadedFiles (ulf_ID);

-- 
-- Constraints for table `recThreadedComments`
-- 
ALTER TABLE `recThreadedComments`
  ADD CONSTRAINT recThreadedComments_ibfk_1 FOREIGN KEY (cmt_OwnerUgrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
ALTER TABLE `recThreadedComments`
  ADD CONSTRAINT recThreadedComments_ibfk_1 FOREIGN KEY (cmt_OwnerUgrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,  ADD CONSTRAINT recThreadedComments_ibfk_2 FOREIGN KEY (cmt_ParentCmtID) REFERENCES recThreadedComments (cmt_ID) ON DELETE CASCADE ON UPDATE CASCADE,
ALTER TABLE `recThreadedComments`
  ADD CONSTRAINT recThreadedComments_ibfk_1 FOREIGN KEY (cmt_OwnerUgrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,  ADD CONSTRAINT recThreadedComments_ibfk_2 FOREIGN KEY (cmt_ParentCmtID) REFERENCES recThreadedComments (cmt_ID) ON DELETE CASCADE ON UPDATE CASCADE,  ADD CONSTRAINT recThreadedComments_ibfk_4 FOREIGN KEY (cmt_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `usrBookmarks`
-- 
ALTER TABLE `usrBookmarks`
  ADD CONSTRAINT usrBookmarks_ibfk_1 FOREIGN KEY (bkm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
ALTER TABLE `usrBookmarks`
  ADD CONSTRAINT usrBookmarks_ibfk_1 FOREIGN KEY (bkm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrBookmarks_ibfk_2 FOREIGN KEY (bkm_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `usrHyperlinkFilters`
-- 
ALTER TABLE `usrHyperlinkFilters`
  ADD CONSTRAINT usrHyperlinkFilters_ibfk_1 FOREIGN KEY (hyf_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `usrRecTagLinks`
-- 
ALTER TABLE `usrRecTagLinks`
  ADD CONSTRAINT usrRecTagLinks_ibfk_1 FOREIGN KEY (rtl_TagID) REFERENCES usrTags (tag_ID) ON DELETE CASCADE,
ALTER TABLE `usrRecTagLinks`
  ADD CONSTRAINT usrRecTagLinks_ibfk_1 FOREIGN KEY (rtl_TagID) REFERENCES usrTags (tag_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrRecTagLinks_ibfk_2 FOREIGN KEY (rtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `usrRecentRecords`
-- 
ALTER TABLE `usrRecentRecords`
  ADD CONSTRAINT usrRecentRecords_ibfk_1 FOREIGN KEY (rre_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
ALTER TABLE `usrRecentRecords`
  ADD CONSTRAINT usrRecentRecords_ibfk_1 FOREIGN KEY (rre_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrRecentRecords_ibfk_2 FOREIGN KEY (rre_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- 
-- Constraints for table `usrReminders`
-- 
ALTER TABLE `usrReminders`
  ADD CONSTRAINT usrReminders_ibfk_1 FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,
ALTER TABLE `usrReminders`
  ADD CONSTRAINT usrReminders_ibfk_1 FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrReminders_ibfk_2 FOREIGN KEY (rem_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
ALTER TABLE `usrReminders`
  ADD CONSTRAINT usrReminders_ibfk_1 FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrReminders_ibfk_2 FOREIGN KEY (rem_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrReminders_ibfk_3 FOREIGN KEY (rem_ToWorkgroupID) REFERENCES sysUGrps (ugr_ID) ON DELETE SET NULL,
ALTER TABLE `usrReminders`
  ADD CONSTRAINT usrReminders_ibfk_1 FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrReminders_ibfk_2 FOREIGN KEY (rem_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,  ADD CONSTRAINT usrReminders_ibfk_3 FOREIGN KEY (rem_ToWorkgroupID) REFERENCES sysUGrps (ugr_ID) ON DELETE SET NULL,  ADD CONSTRAINT usrReminders_ibfk_4 FOREIGN KEY (rem_ToUserID) REFERENCES sysUGrps (ugr_ID) ON DELETE SET NULL;

-- 
-- Constraints for table `usrSavedSearches`
-- 
ALTER TABLE `usrSavedSearches`
  ADD CONSTRAINT usrSavedSearches_ibfk_1 FOREIGN KEY (svs_UGrpID) REFERENCES sysUGrps (ugr_ID);

-- 
-- Constraints for table `usrTags`
-- 
ALTER TABLE `usrTags`
  ADD CONSTRAINT usrTags_ibfk_1 FOREIGN KEY (tag_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- 
-- Procedures
-- 
-- DELIMITER $$
-- 
CREATE DEFINER=root@localhost PROCEDURE set_all_hhash()
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
                                                         B.rec_ID=dtl_ValueAsText and
                                                        B.rec_Hhash is null and
                                                         rst_RecTypeID=A.rec_RecType and
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
    end$$

-- 
-- DELIMITER ;
-- 
-- Functions
-- 

CREATE DEFINER=root@localhost FUNCTION hhash(recID int) RETURNS varchar(4095) CHARSET utf8
    READS SQL DATA
    DETERMINISTIC
begin
        declare rectype int;
        declare non_resource_fields varchar(4095);
        declare resource_fields varchar(4095);

         select rec_RecType into rectype from Records where rec_ID = recID;

         select group_concat(liposuction(upper(dtl_ValueAsText)) order by dty_ID, upper(dtl_ValueAsText) separator ';')
            into non_resource_fields
            from Details, Records, defDetailTypes, defRecStructure
            where dtl_RecID=rec_ID and
                    dtl_DetailTypeID=dty_ID and
                     rec_RecType=rst_RecTypeID and
                    rst_DetailTypeID=dty_ID and
                    rst_RecordMatchOrder and
                    dty_Type != "resource" and
                    rec_ID = recID;

        select group_concat(DST.rec_Hhash order by dty_ID, dty_ID, DST.rec_Hhash separator '$^')
            into resource_fields
            from Details, Records SRC, defDetailTypes, defRecStructure, Records DST
            where dtl_RecID=SRC.rec_ID and
                    dtl_DetailTypeID=dty_ID and
                     SRC.rec_RecType=rst_RecTypeID and
                    rst_DetailTypeID=dty_ID and
                    rst_RequirementType = 'Required' and
                    dty_Type = "resource" and
                     dtl_ValueAsText = DST.rec_ID and
                    dtl_RecID=recID;

        return concat(ifnull(rectype,'N'), ':',

        if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''),

        if(resource_fields is not null and resource_fields != '', concat('^', resource_fields, '$'), ''));
    end

CREATE DEFINER=root@localhost FUNCTION simple_hash(recID int) RETURNS varchar(4095) CHARSET utf8
    READS SQL DATA
    DETERMINISTIC
begin
        declare rectype int;
        declare non_resource_fields varchar(4095);
        declare author_fields varchar(4095);
         select rec_RecType into rectype from Records where rec_ID = recID;
         select group_concat(liposuction(upper(dtl_ValueAsText)) order by dty_ID, upper(dtl_ValueAsText) separator ';')
            into non_resource_fields
            from Details, Records, defDetailTypes, defRecStructure
            where dtl_RecID=rec_ID and
                    dtl_DetailTypeID=dty_ID and
                     rec_RecType=rst_RecTypeID and
                    rst_DetailTypeID=dty_ID and
                    rst_RecordMatchOrder and
                    dty_Type != "resource" and
                    rec_ID = recID;
        return concat(ifnull(rectype,'N'), ':',
        if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''));
    end

