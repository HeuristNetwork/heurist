<?php

// crosswalk_builder.php  - gets definitiosn from a specified installation of Heurist
// Processes them agaisnt local definitions, allows the administrator to import definitions
// and stores equivalences in the crosswalks_definition table.
// Ian Johnson 3 March 2010

require_once(dirname(__FILE__).'/../../common/connect/cred.php');

if (!is_logged_in()) {
	    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	    return;
        }

// Requires admin user, access to definitions through get_definitions is open
if (! is_admin()) {
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1>Log out</a></p></body></html>";
    return;
}

require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../common/T1000/.ht_stdefs');


// Deals with all the database connections stuff

    mysql_connection_db_select(DATABASE);

$version = 1; // Definitions exchange format version number. Update in get_definitions and here


 // Query reference.heuristscholar.org to find the URL of the installation you want to use as source

 // TO WRITE

 // Steve: for the moment jsut use heuristscholar.org/admin/get_definitions.php


// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)

// Steve: SEND THIS TO mysqL

DROP TABLE IF EXISTS `temp_rec_types`;
CREATE TABLE IF NOT EXISTS `temp_rec_types` (
  `rty_ID` tinyint(3) unsigned NOT NULL,
  `rty_Name` varchar(63) NOT NULL,
  `rty_OrderInGroup` tinyint(3) unsigned NOT NULL default '0',
  `rty_Description` blob,
  `rty_RecTypeGroupID` tinyint(1) default NULL,
  `rty_TitleMask` varchar(255) default NULL,
  `rty_CanonicalTitleMask` varchar(255) default NULL,
  `rty_Plural` varchar(63) default NULL,
  PRIMARY KEY  (`rty_ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

INSERT INTO `temp_rec_types` (`rty_ID`, `rty_Name`, `rty_OrderInGroup`, `rty_Description`, `rty_RecTypeGroupID`, `rty_TitleMask`, `rty_CanonicalTitleMask`, `rty_Plural`) VALUES

// Steve: Parse the stream from get_definitions until you get to a line with    > Start
// then send the stream to MySQL until you get to a line with   > End
// Need to send a closing semicolon



// ------------------------------------------------------------------------------------------
// RECORD DETAIL TYPES

// Steve: SEND THIS TO mysqL

DROP TABLE IF EXISTS `temp_rec_detail_types`;
CREATE TABLE IF NOT EXISTS `temp_rec_detail_types` (
 `dty_ID` smallint(6) NOT NULL,
 `dty_Name` varchar(255) default NULL,
 `dty_Description` text,
 `dty_Type` enum('freetext','blocktext','integer','date','year','person lookup','boolean','enum','resource','float','file','geo','separator') default NULL,
 `dty_Prompt` varchar(255) default NULL,
 `dty_Help` text,
  `dty_PtrConstraints` smallint(6) default NULL,
  PRIMARY KEY  (`dty_ID`),
  UNIQUE KEY `bdt_name` (`dty_Name`),
  KEY `bdt_type` (`dty_Type`)
  ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

INSERT INTO `temp_rec_detail_types` (`dty_ID`, `dty_Name`, `dty_Description`, `dty_Type`,`dty_Prompt`, `dty_Help`, `dty_PtrConstraints`) VALUES

// Steve: Parse the stream from get_definitions until you get to a line with    > Start
// then send the stream to MySQL until you get to a line with   > End
// Need to send a closing semicolon



// ------------------------------------------------------------------------------------------
// RECORD DETAIL REQUIREMENTS

// Steve: SEND THIS TO mysqL

DROP TABLE IF EXISTS `temp_rec_detail_requirements`;
CREATE TABLE IF NOT EXISTS `temp_rec_detail_requirements` (
  `rdr_id` smallint(6) NOT NULL,
  `rdr_rec_type` smallint(5) unsigned NOT NULL default '0',
  `rdr_rdt_id` smallint(6) NOT NULL default '0',
  `rdr_required` enum('Y','R','O','X') NOT NULL default 'O',
  `rdr_name` varchar(255) default NULL,
  `rdr_description` text,
  `rdr_prompt` text,
  `rdr_help` varchar(255) default NULL,
  `rdr_repeatable` tinyint(1) NOT NULL default '0',
  `rdr_order` smallint(6) default NULL,
  `rdr_size` smallint(6) default NULL,
  `rdr_default` varchar(255) default NULL,
  `rdr_match` tinyint(1) NOT NULL,
  PRIMARY KEY  (`rdr_id`),
  UNIQUE KEY `bdr_reftype` (`rdr_rec_type`,`rdr_rdt_id`)
  ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

  INSERT INTO `temp_rec_detail_requirements` (`rdr_id`, `rdr_rec_type`, `rdr_rdt_id`, `rdr_required`, `rdr_name`, `rdr_description`, `rdr_prompt`, `rdr_help`, `rdr_repeatable`, `rdr_order`, `rdr_size`, `rdr_default`, `rdr_match`) VALUES

// Steve: Parse the stream from get_definitions until you get to a line with    > Start
// then send the stream to MySQL until you get to a line with   > End
// Need to send a closing semicolon



// ------------------------------------------------------------------------------------------
// RECORD DETAIL LOOKUPS

// Steve: SEND THIS TO mysqL

DROP TABLE IF EXISTS `temp_rec_detail_lookups`;
CREATE TABLE IF NOT EXISTS `temp_rec_detail_lookups` (
  `rdl_id` smallint(6) NOT NULL auto_increment,
  `rdl_rdt_id` smallint(6) default NULL,
  `rdl_value` varchar(63) default NULL,
  `rdl_related_rdl_id` smallint(6) default NULL,
  PRIMARY KEY  (`rdl_id`),
  UNIQUE KEY `bdl_bdt_id` (`rdl_rdt_id`,`rdl_value`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

INSERT INTO `temp_rec_detail_lookups` (`rdl_id`, `rdl_rdt_id`, `rdl_value`, `rdl_related_rdl_id`) VALUES

// Steve: Parse the stream from get_definitions until you get to a line with    > Start
// then send the stream to MySQL until you get to a line with   > End
// Need to send a closing semicolon


                                                                                                                               // ------------------------------------------------------------------------------------------
// ------------------------------------------------------------------------------------------
// ONTOLOGIES

// Steve: SEND THIS TO mysqL

DROP TABLE IF EXISTS `temp_vocabularies`;
CREATE TABLE IF NOT EXISTS `temp_vocabularies` (
  `vcb_ID` smallint(6) unsigned NOT NULL,
  `vcb_Name` varchar(64) character set utf8 NOT NULL,
  `vcb_Description` text character set utf8,
  `vcb_RefURL` varchar(128) character set utf8 default NULL,
  `vcb_Added` date default NULL,
  `vcb_Modified` date default NULL,
  PRIMARY KEY  (`vcb_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `temp_vocabularies` (`vcb_ID`, `vcb_Name`, `vcb_Description`, `vcb_RefURL`, `vcb_Added`, `vcb_Modified`) VALUES

// Steve: Parse the stream from get_definitions until you get to a line with    > Start
// then send the stream to MySQL until you get to a line with   > End
// Need to send a closing semicolon



// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

// Steve: SEND THIS TO mysqL

DROP TABLE IF EXISTS `temp_rel_constraints`;
CREATE TABLE IF NOT EXISTS `temp_rel_constraints` (
  `rcon_id` smallint(6) unsigned NOT NULL,
  `rcon_rdt_id` smallint(6) unsigned NOT NULL default '0',
  `rcon_source_rt_id` smallint(6) unsigned NOT NULL default '0',
  `rcon_target_rt_id` smallint(6) unsigned NOT NULL default '0',
  `rcon_rdl_ids` varchar(255) character set utf8 default NULL,
  `rcon_ont_id` smallint(6) unsigned NOT NULL,
  `rcon_description` text character set utf8,
  PRIMARY KEY  (`rcon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `temp_rel_constraints` (`rcon_id`, `rcon_rdt_id`, `rcon_source_rt_id`, `rcon_target_rt_id`, `rcon_rdl_ids`, `rcon_ont_id`, `rcon_description`) VALUES

// Steve: Parse the stream from get_definitions until you get to a line with    > Start
// then send the stream to MySQL until you get to a line with   > End
// Need to send a closing semicolon


// INTERFACE TO ALLOW ADDITION OF DEFINTIONS TO THE CURRENT HEURIST INSTALLATION
// AND UPDATE OF CROSSWALK_DEFINITIONS TABLE

// TO WRITE

// TIDY UP TEMPORARY FILES

// TO WRITE


?>

// END OF FILE
