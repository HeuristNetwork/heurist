-- create_heurist_system_db.sql: SQL file to create Heurist system database (hdb_HeuristSystem)
-- Ian Johnson 11/1/2011

\W -- warnings to standard out

-- ------------------------------------------------------------------------------

Create Database If Not exists hdb_HeuristSystem;

Use hdb_HeuristSystem;

CREATE TABLE IF NOT EXISTS sysDocumentation (
   doc_id tinyint unsigned auto_increment,
   doc_text text Comment "Relevant documentation as text",
   PRIMARY KEY  (doc_id) )
   Comment 'Descriptive infromation about this database and its function';

  INSERT INTO sysDocumentation(doc_id,doc_text)
     Values (1,"DO NOT DELETE: This database contains common information across all databases for this installation (codebase) of Heurist, including shared user and group tables");

-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sysIdentification (
-- Note: This is a stripped down version of the same structure in a standard Heurist database, and could be used to provide default values
-- across all databases on an installation. Currently it only holds system version information.
  sys_ID tinyint(3) unsigned NOT NULL
    COMMENT 'Only 1 record should exist in this table',
  sys_dbName varchar(64) collate utf8_unicode_ci NOT NULL
    COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  sys_dbRegisteredID int(10) unsigned NOT NULL default '0'
    COMMENT 'Allocated by HeuristScholar.org, 0 indicates not yet registered',
  sys_dbVersion tinyint(3) unsigned NOT NULL
    COMMENT 'Major version for the database structure',
  sys_dbSubVersion tinyint(3) unsigned NOT NULL default '0'
    COMMENT 'Sub version',
  sys_dbSubSubVersion tinyint(3) unsigned NOT NULL default '0'
    COMMENT 'Sub-sub version',
  sys_OwnerGroupId int(11) unsigned NOT NULL default '1'
    COMMENT 'User group which owns this database, 1 by default',
  PRIMARY KEY  (sys_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
  COMMENT='Version information and owner group for this installation';

 INSERT INTO sysIdentification(sys_ID,sys_dbName,sys_dbVersion,sys_dbSubVersion,sys_dbSubSubVersion,sys_OwnerGroupId)
  VALUES (1,'HEURIST SYSTEM DATABASE - TEMPLATE DATA AND USER/GROUPS DATA',3,0,0,1);
  -- 0 is everyone, 1 is the owning admins group, 2 is default dbAdmin user

-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sysUGrps (
  ugr_ID smallint(5) unsigned NOT NULL auto_increment
    COMMENT 'User or group ID, used wherever a user or group is to be identified',
  ugr_Type enum('user','workgroup','ugradclass') NOT NULL default 'user'
    COMMENT 'User or workgroup, special workgroup types also supported',
  ugr_Name varchar(64) NOT NULL
    COMMENT 'The unique user/login/group name, user name defaults to email address',
  ugr_LongName varchar(128) default NULL
    COMMENT 'An optional longer descriptive name for a user group',
  ugr_Description varchar(1000) default NULL
    COMMENT 'Extended description of a user group displayed on homepage',
  ugr_Password varchar(32) NOT NULL
    COMMENT 'Encrypted password string',
  ugr_eMail varchar(64) NOT NULL
    COMMENT 'Contact email address of the user/group',
  ugr_FirstName varchar(30) default NULL
    COMMENT 'Person''s first name, only for Users, not Workgroups',
  ugr_LastName varchar(40) default NULL
    COMMENT 'Person''s last name, only for Users, not Workgroups',
  ugr_Department varchar(120) default NULL,
  ugr_Organisation varchar(120) default NULL,
  ugr_City varchar(40) default NULL,
  ugr_State varchar(20) default NULL,
  ugr_Postcode varchar(20) default NULL,
  ugr_Interests varchar(255) default NULL
    COMMENT 'List of research interests, only for Users, not Workgroups',
  ugr_Enabled enum('y','n') NOT NULL default 'n'
    COMMENT 'Flags if user can use Heurist, normally needs authorising by admin',
  ugr_LastLoginTime datetime default NULL
    COMMENT 'Date and time of last login (but user may stay logged in)',
  ugr_MinHyperlinkWords tinyint(4) NOT NULL default '3'
    COMMENT 'Filter hyperlink strings with less than this word count on hyperlink import ',
  ugr_LoginCount int(11) NOT NULL default '0'
    COMMENT 'Number of times user haslogged in',
  ugr_IsModelUser tinyint(1) NOT NULL default '0'
    COMMENT '1 indicates model user = domain profile',
  ugr_IncomingEmailAddresses varchar(512) default NULL
    COMMENT 'Comma-sep list of incoming email addresses from which to archive emails',
  ugr_URLs varchar(1000) default NULL
    COMMENT 'URL(s) of group or personal website(s), comma separated',
  ugr_FlagJT int(1) NOT NULL default '0'
    COMMENT 'Flag to enable in Jobtrack/Worktrack application',
  PRIMARY KEY  (ugr_ID),
  UNIQUE KEY ugr_NameKey (ugr_Name),
  UNIQUE KEY ugr_eMailKey (ugr_eMail)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8
  COMMENT='Contains User and Workgroup records, distinguished by ugr_Ty' AUTO_INCREMENT=0 ;

INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Type,ugr_Password,ugr_eMail)
 VALUES (1,'Database owners',
 'Group 1 owns new databases by default',
 'workgroup','PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=1');

INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Type,ugr_Password,ugr_eMail)
 VALUES (2,'dbAdmin','User 2 is default owner/adminstration group for all databases',
 'user','cfefBRSMX8ggU','EMAIL NOT SET FOR ID=2');  -- password is 'none'

-- ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sysUsrGrpLinks (
  ugl_ID mediumint(8) unsigned NOT NULL auto_increment COMMENT 'Primary key for user-to-group membership',
  ugl_UserID smallint(5) unsigned NOT NULL COMMENT 'The user being assigned to a group',
  ugl_GroupID smallint(5) unsigned NOT NULL COMMENT 'The group to which this user is being assigned',
  ugl_Role enum('admin','member') NOT NULL default 'member' COMMENT 'The role of this user in the group - member, admin',
  PRIMARY KEY  (ugl_ID),
  UNIQUE KEY ugl_CompositeKey (ugl_UserID,ugl_GroupID),
  KEY ugl_GroupID (ugl_GroupID)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='Identifies groups to which a user belongs and their role in ' AUTO_INCREMENT=0;

-- Insert a row to define the link between group 1 (owners) and user 2 (DBadmin)
INSERT INTO sysUsrGrpLinks (ugl_UserID,ugl_GroupID,ugl_Role) VALUES (2,1,'admin');

