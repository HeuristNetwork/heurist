/*
* Copyright (C) 2005-2016 University of Sydney
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
* DB_index.sql: SQL file to create and fill central index database for current server
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/


-- create Heurist_DBs_index database
-- create index tables - sysIdentifications and sysAdmins
-- recreate triggers for all databases
-- fill central index for all databases

-- create Heurist_DBs_index database
CREATE DATABASE IF NOT EXISTS Heurist_DBs_index;
use Heurist_DBs_index;

-- create index tables - sysIdentifications and sysAdmins

CREATE TABLE IF NOT EXISTS sysIdentifications
(
  sys_Database varchar(100) NOT NULL,
  sys_ID tinyint(1) unsigned NOT NULL default '1',
  sys_dbRegisteredID int(10) unsigned NOT NULL default '0' COMMENT 'Allocated by Heurist master index, 0 indicates not yet registered',
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
  sys_OwnerGroupID smallint(5) unsigned NOT NULL default '1' COMMENT 'Workgroup which owns/administers this database, 1 by default',
  sys_dbName varchar(63) NOT NULL default 'Please enter a DB name ...' COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  sys_dbOwner varchar(250) default NULL COMMENT 'Information on the owner of the database, may be a URL reference',
  sys_dbRights varchar(1000) NOT NULL default 'Please define ownership and rights here ...' COMMENT 'A statement of ownership and copyright for this database and content',
  sys_dbDescription varchar(1000) default NULL COMMENT 'A longer description of the content of this database',
  sys_SyncDefsWithDB varchar(1000) default NULL COMMENT 'One or more Zotero library name,userID,groupID,key combinations separated by pipe symbols, for synchronisation of Zotero libraries',
  sys_AutoIncludeFieldSetIDs varchar(63) default '0' COMMENT 'CSV list of fieldsets which are included in all rectypes',
  sys_RestrictAccessToOwnerGroup tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, database may only be accessed by members of owners group',
  sys_URLCheckFlag tinyint(1) unsigned NOT NULL default '0' COMMENT 'Flags whether system should send out requests to URLs to test for validity',
  sys_UploadDirectory varchar(128) default NULL COMMENT 'NOT USED: Absolute directory path for uploaded files (blank = use default from installation)',
  sys_hmlOutputDirectory varchar(255) default NULL COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory',
  sys_htmlOutputDirectory varchar(255) default NULL COMMENT 'Directory in which to write html representation of published records, default to html within upload directory',
  sys_NewRecOwnerGrpID smallint(5) unsigned NOT NULL default '0' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  sys_NewRecAccess enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  sys_SetPublicToPendingOnEdit tinyint(1) unsigned NOT NULL default '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''',
  sys_ConstraintDefaultBehavior enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL default 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  sys_AllowRegistration tinyint(1) unsigned NOT NULL default '0' COMMENT 'If set, people can apply for registration through web-based form',
  sys_MediaFolders varchar(10000) default NULL COMMENT 'Additional comma-sep directories which can contain files indexed in database',
  sys_MediaExtensions varchar(1024) default 'jpg,png,gif,tif,tiff,wmv,doc,docx,xls,xlsx,txt,rtf,xml,xsl,xslt,mpg,mpeg,mov,mp3,mp4,qt,wmd,avi,kml,sid,ecw,mp3,mid,midi,evo,csv,tab,wav,cda,wmz,wms,aif,aiff' COMMENT 'The file extensions to be harvested from the MediaFolders directories',
  PRIMARY KEY  (sys_Database)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='';


CREATE TABLE IF NOT EXISTS sysUsers
(
  sus_ID mediumint(8) unsigned NOT NULL auto_increment,
  sus_Email varchar(100) NOT NULL COMMENT 'Contact email address',
  sus_Database varchar(100) NOT NULL COMMENT 'Database name',
  sus_Role enum('owner','admin','member') NOT NULL default 'member' COMMENT 'The role of user in the database - member, admin, owner',
  PRIMARY KEY  (sus_ID),
  KEY sus_EmailX (sus_Email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='';

-- procedures to fill tables in index database

DROP PROCEDURE IF EXISTS sp_ExecSQL;
DROP PROCEDURE IF EXISTS sp_RunAllDatabases;
DELIMITER $$
 
CREATE PROCEDURE sp_ExecSQL(exp varchar(2000))
BEGIN
    
    SET @s = exp;
    PREPARE stmt FROM @s;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;    

END$$
    
CREATE PROCEDURE sp_RunAllDatabases()
BEGIN
    DECLARE bDone INT DEFAULT 0;
    DECLARE db_name CHAR(100);
    DECLARE curs CURSOR FOR select `schema_name` from `information_schema`.`schemata` where `schema_name` like 'hdb_%';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET bDone = 1;

    OPEN curs;

    SET bDone = 0;
    REPEAT
        FETCH curs INTO db_name;
        
     SET @s = concat("SELECT sys_ID FROM `",db_name,"`.`sysIdentification` WHERE (sys_ID=1 OR sys_ID=0) into @sys_ID;");
     PREPARE stmt FROM @s;
     EXECUTE stmt;
     DEALLOCATE PREPARE stmt;    

-- database is valid and sysIdentification table exists    
    IF (SELECT @sys_ID) >=0 THEN

       call sp_ExecSQL(concat('delete from `Heurist_DBs_index`.`sysIdentifications` where `sys_Database`="',db_name,'"'));
       call sp_ExecSQL(concat('insert into `Heurist_DBs_index`.`sysIdentifications` select "',db_name,'" as dbName, s.* from `',db_name,'`.`sysIdentification` as s;'));

-- update users
       call sp_ExecSQL(concat('delete from `Heurist_DBs_index`.`sysUsers` where `sus_Database`="',db_name,'" AND sus_ID>0'));
       call sp_ExecSQL(concat("insert into `Heurist_DBs_index`.`sysUsers` (sus_Email, sus_Database, sus_Role) ",
       "select ugr_Email, '",db_name,"' as dbaname, IF(ugr_ID=2,'owner',COALESCE(ugl_Role,'member')) as role from `",db_name,"`.sysUGrps ",
        "LEFT JOIN `",db_name,"`.sysUsrGrpLinks on ugr_ID=ugl_UserID and ugl_GroupID=1 and ugl_Role='admin' where ugr_Type='user'"));  
   
    END IF;
    
    UNTIL bDone END REPEAT;

    CLOSE curs;
END $$
DELIMITER ;

-- disabled
-- see centralIndex.php
-- CALL sp_RunAllDatabases();

DROP PROCEDURE IF EXISTS sp_RunAllDatabases;
DROP PROCEDURE IF EXISTS sp_ExecSQL;