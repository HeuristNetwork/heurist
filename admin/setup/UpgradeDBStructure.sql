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
* UpgradeDBStructure.sql: SQL file to update Heurist database between indicated versions 
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/


-- NOTE: This file may contain multiple upgrades, but only the ones applicable to the current database version
-- and the desired database version (as specified in the software) should be processed; additional upgrades to
-- future versions can be added as new version codes but should not be processed until the software is updated
-- to expect them

-- -------------------------------------------------------------------------------------------------

-- Start of upgrade section, specify start and end version

#START

-- Everything between lines containing #START and #END (capitalise and eliminate spaces first)
-- is the SQL to upgrade the database between two (sub) versions

-- Source version: 1.1.0 
-- Target version: 1.2.0
-- Safety rating: SAFE
-- Description: Add Certainty rating and Annotation text to every key-value (detail) pair

    -- Addition of certainty and annotation fields for compatibility with FAIMS
    -- This is also a potentially very useful function for us
    ALTER TABLE `recDetails` 
    ADD `dtl_Certainty` DECIMAL( 3, 2 ) NOT NULL DEFAULT '1.0' 
    COMMENT 'A certainty value for this observation in the range 0 to 1, where 1 indicates complete certainty' 
    AFTER `dtl_Modified` ;
    
    ALTER TABLE `recDetails` ADD `dtl_Annotation` VARCHAR( 250 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL 
    COMMENT'A short note / annotation about this specific data value - may enlarge for example on the reasons for the certainty value' 
    AFTER `dtl_Certainty` ;
    
    -- Provision for an image or PDF or external URL to define or illustrate the term
    ALTER TABLE  `defTerms` 
    ADD  `trm_IllustrationURL` VARCHAR( 250 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL 
    COMMENT 'The URL to a picture or other resource illustrating the term. If blank, look for trm_ID.jpg/gif/png in term_images directory' 
    AFTER  `trm_Code` ;
      
#END  -- End of upgrade section 1.1.0 > 1.2.0 


-- -------------------------------------------------------------------------------------------------

#START

-- Source version: 1.2.0 
-- Target version: 1.3.0
-- Safety rating: SAFE
-- Description: Add table to hold user-defined analysis functions

    -- Addition of 
    CREATE TABLE usrAnalyses (
      uan_ID mediumint(8) unsigned NOT NULL auto_increment 
    COMMENT 'Analysis or derivation rule ID, primary key',
      uan_UGrpID smallint(5) unsigned NOT NULL 
    COMMENT 'The creator/owner or workgroup for the analysis or derivation rule,  0 = everyone',
    uan_Type ENUM('derived_value', 'descriptive_crosstab') NOT NULL 
    COMMENT 'The type of derivation or analysis saved in this record', 
     uan_Name varchar(60) NOT NULL 
    COMMENT 'The display name for this analysis of derivation rule',
      uan_Description varchar(250) default NULL 
    COMMENT 'A description of this particular analysis eg. what it is useful for or how it was conceived',
      uan_FilterRules text COMMENT 'The filter rules which define the set of records to be assembled to run the derivation or analysis',
      uan_Method text 
    COMMENT 'The specifications of the derivation or analysis to be performed, including parameters such as field choices and naming',
    uan_MethodFilePath VARCHAR( 250 ) NULL 
    COMMENT 'The local path to additional code or parameters to be used by this derivation or analysis',
      uan_ExtMethodURL text 
    COMMENT 'An external URL which may be used to obtain additional analysis code or parameters',
      uan_DisplaySettings text 
    COMMENT 'Specifies the way that the output will be formatted, eg. which values to display, layout and so forth',
      uan_DocumentationURL text 
    COMMENT 'An external URL providing further information about the analysis',
      uan_Added date NOT NULL default '0000-00-00' 
    COMMENT 'Date and time analysis added',
      uan_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP 
    COMMENT 'Date and time analysis last modified',
      PRIMARY KEY  (uan_ID),
      KEY uan_UGrpID (uan_UGrpID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 
    COMMENT='Generic table for storing field derivation rules and analysis functions';
    
#END  -- End of upgrade section


-- -------------------------------------------------------------------------------------------------

-- TEMPLATE FOR FURTHER UPGRADES

-- #START

-- Everything between lines containing #START and #END (capitalise and eliminate spaces first)
-- is the SQL to upgrade the database between two (sub) versions

-- Source version: 1.?.0 
-- Target version: 1.?.0
-- Safety rating: SAFE
-- Description: ???????

    
-- Add stuff here as required    
    
    
-- #END  -- End of upgrade section

-- -------------------------------------------------------------------------------------------------


-- POTENTIAL CODE FOR USE IN UPGRADE

-- enter update statements for the required version upgrade in updateDBVersion below
-- don't forget to modify
--    the test for the current version
--    the update of dbVersion, dbDubVersion and dbSubSubVersion

DROP function IF EXISTS `doTestIf`;
DROP Procedure IF EXISTS `UpdateDBVersion`;

DELIMITER //

CREATE function doTestIf() RETURNS varchar(3)
     READS SQL DATA
     DETERMINISTIC
    BEGIN
        declare dbVer varchar(3);
        declare dbSubVer varchar(3);
        select sys_dbVersion into dbVer from sysIdentification where 1;
        select sys_dbSubVersion into dbSubVer from sysIdentification where 1;
        IF(dbVer="3") and (dbSubVer="0") THEN
          CALL UpdateDBVersion;
          RETURN "OK";
        ELSE
            RETURN "Bad"";
        END IF;
    END//

Create Procedure UpdateDBVersion
    Begin

    -- *** Required update statements between the two specified versions go here ***


END//

  DELIMITER ;


SELECT doTestIf();

DROP function IF EXISTS `doTestIf`;
DROP Procedure IF EXISTS `UpdateDBVersion`;


