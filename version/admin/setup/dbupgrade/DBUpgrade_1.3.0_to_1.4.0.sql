/*
* Copyright (C) 2005-2020 University of Sydney
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
* DBUpgrade_1.x.x_to_1.x.x.sql: SQL file to update Heurist database format between indicated versions 
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.7
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

-- ------------------------------------------------------------------------------

-- ****** IMPORTANT *******

-- This is a holding pen for ideas about the next structure upgrade. 
-- These changes might or might not be made, or might be made in modified form

-- ------------------------------------------------------------------------------

-- additional upgrades to future versions can be added as new files but should not be processed 
-- until the software is updated to expect them


-- Source version: 1.3.0 
-- Target version: 1.4.0
-- Safety rating: SAFE
-- Description: Add table: usrAnalyses

-- Unique composite key indexing (by record type), needed by FAIMS among others, on the way to replacing Tom's fuzzy matching methods
-- which were more bibliogeraphy-oruiented and never terribly successful, with a clear-cut unique key system by record type

-- Aug 2017 The problem here is that using a unique key will invalidate record addition until such time as a unique key comgination is specified
-- it is probably better to use Elastic search indexes to determine whether there is duplication. tbd. 

    ALTER TABLE Records
    Add rec_KeyfieldsComposite VARCHAR(64) NULL
    COMMENT 'Contains a composite field constructed from record details flagged for indexing by rst_UseInUniqueIndex';

    // see notes above  ALTER TABLE Records
    //                 ADD UNIQUE KEY rec_UniqueRectypeKeyfields (rec_RecTypeID,rec_KeyfieldsComposite);

    ALTER TABLE defRecStructure
    ADD rst_UseInUniqueIndex TINYINT NOT NULL DEFAULT 0
    COMMENT 'Indicates whether field is to be used in the composite index which controls record uniqueness by record type';

-- Store user profile information for use in H4 and greater framework
    ALTER TABLE  `sysUGrps`
    ADD  `ugr_UsrPreferences` TEXT NULL
    COMMENT 'JSon array containing user profile available across machines. If blank, profile is specific to local session';

    
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
      uan_Added date NOT NULL default '1000-01-01' 
    COMMENT 'Date and time analysis added',
      uan_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP 
    COMMENT 'Date and time analysis last modified',
      PRIMARY KEY  (uan_ID),
      KEY uan_UGrpID (uan_UGrpID)
    ) ENGINE=InnoDBs
    COMMENT='Generic table for storing field derivation rules and analysis functions';