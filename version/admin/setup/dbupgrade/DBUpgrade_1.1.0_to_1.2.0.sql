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
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

-- additional upgrades to future versions can be added as new files but should not be processed
-- until the software is updated to expect them


-- Source version: 1.1.0
-- Target version: 1.2.0
-- Safety rating: SAFE
-- Description: Add Child record functions, Add Certainty rating and Annotation text to every key-value (detail) pair

-- Extend sys_SyncDefsWithDB from 63 --> 1000 to hold up to about 20 Zotero keys
    ALTER TABLE `sysIdentification`
        Change `sys_SyncDefsWithDB` `sys_SyncDefsWithDB` Varchar( 1000 ) NULL DEFAULT ''
        COMMENT 'One or more Zotero library name,userID,groupID,key combinations separated by pipe symbols, for synchronisation of Zotero libraries';

-- Extend svs_Name from 30 --> 128
    ALTER TABLE `usrSavedSearches` 
        CHANGE COLUMN `svs_Name` `svs_Name` VARCHAR(128) NOT NULL COMMENT 'The display name for this saved search' ;
    
-- ---------------------------------------------------------------------------------------------------------------------------------
-- Addition of certainty and annotation fields for alignment with NeCTAR/LIEF FAIMS project
-- This is also a potentially very useful function for broader use
    ALTER TABLE `recDetails` ADD `dtl_Certainty` DECIMAL( 3, 2 ) NOT NULL DEFAULT '1.0' 
    COMMENT 'A certainty value for this observation in the range 0 to 1, where 1 indicates complete certainty';

    ALTER TABLE `recDetails` ADD `dtl_Annotation` VARCHAR( 250 ) NULL
    COMMENT'A short note / annotation about this specific data value - may enlarge for example on the reasons for the certainty value';

-- ---------------------------------------------------------------------------------------------------------------------------------    
-- Default behaviour is not to show these, for compatibility with DB version 1.1.0 and before
    ALTER TABLE `defRecStructure` ADD `rst_ShowDetailCertainty` TinyInt(1) NOT NULL  default 0
        COMMENT 'When editing the field, allow editng of the dtl_Certainty value (off by default)' 
        after `rst_TermIDTreeNonSelectableIDs`;

    ALTER TABLE `defRecStructure` ADD `rst_ShowDetailAnnotation` TinyInt(1) unsigned NOT NULL default 0
        COMMENT 'When editing the field, allow editng of the dtl_Annotation value (off by default)'
        after `rst_ShowDetailCertainty`;
    
-- It would of course be better to have a proper plugin architecture for calculated fields, but this provides a good stopgap
-- method for combining fields eg. to create a unique item identifier for hierarchichally organised entities such as excavated artefacts
    ALTER TABLE `defRecStructure`  ADD `rst_CalcFieldMask` Varchar(250) NULL
        COMMENT 'A mask string along the lines of the title mask allowing a composite field to be generated from other fields in the record' 
        after `rst_CalcFunctionID`;
    -- Note: should be able to use the title mask routines to manage content of the CalcFieldmask, and both can be extended together
    -- eg. refomatting dates, truncating strings
    
    ALTER TABLE `defRecStructure`  ADD `rst_NumericLargestValueUsed` INTEGER NULL
        COMMENT 'For numeric fields, Null = no auto increment, 0 or more indicates largest value used so far. Set to 0 to switch on incrementing'
        after `rst_ShowDetailAnnotation`;

    ALTER TABLE `defRecStructure` ADD `rst_DisplayHeight` TinyInt(2) unsigned NOT NULL default 3
        COMMENT 'The field height for this detail type in this record type, only relevant for memo fields' 
        AFTER `rst_DisplayWidth`;
    
    ALTER TABLE `defRecStructure` ADD `rst_EntryMask` Varchar(250) NULL
        COMMENT 'Data entry mask, use to control decimals on numeric values, content of text fields etc. for this record type - future implementation Aug 2017'
        after `rst_NumericLargestValueUsed`;
    
    ALTER TABLE `defRecStructure` ADD COLUMN `rst_CreateChildIfRecPtr` TINYINT(1) DEFAULT 0 
        COMMENT 'For pointer fields, flags that new records created from this field should be marked as children of the creating record' AFTER `rst_PtrFilteredIDs`;
        
    ALTER TABLE `defRecStructure` ADD COLUMN `rst_PointerMode` enum('addorbrowse','addonly','browseonly') DEFAULT 'addorbrowse' COMMENT 'When adding record pointer values, default or null = show both add and browse, otherwise only allow add or only allow browse-for-existing' AFTER `rst_CreateChildIfRecPtr`;

    ALTER TABLE `defRecStructure` ADD COLUMN `rst_PointerBrowseFilter` varchar(255)  DEFAULT NULL COMMENT 'When adding record pointer values, defines a Heurist filter to restrict the list of target records browsed' AFTER `rst_PointerMode`;    

    ALTER TABLE `defRecStructure` ADD COLUMN `rst_InitialRepeats` TINYINT(1) DEFAULT 1 
        COMMENT 'Number of repeat values to be displayed for this field when a new record is first displayed' 
        AFTER `rst_MinValues`;     

-- ---------------------------------------------------------------------------------------------------------------------------------    
-- Provision for an image or PDF or external URL to define or illustrate the term
    ALTER TABLE  `defTerms`
        ADD  `trm_SemanticReferenceURL` VARCHAR( 250 ) NULL
        COMMENT 'The URL to a semantic definition or web page describing the term';

    ALTER TABLE  `defTerms`
        ADD  `trm_IllustrationURL` VARCHAR( 250 ) NULL
        COMMENT 'The URL to a picture or other resource illustrating the term. If blank, look for trm_ID.jpg/gif/png in term_images directory';

-- ---------------------------------------------------------------------------------------------------------------------------------    
    -- Provision for an external semantic URL to define the base field type
    ALTER TABLE  `defDetailTypes`
        ADD  `dty_SemanticReferenceURL` VARCHAR( 250 ) NULL
        COMMENT 'The URL to a semantic definition or web page describing the base field type';

-- ---------------------------------------------------------------------------------------------------------------------------------    
    ALTER TABLE `usrBookmarks` ADD `bkm_Notes` TEXT COMMENT 'Personal notes';

    ALTER TABLE `sysUGrps` ADD `ugr_NavigationTree` TEXT COMMENT 'JSON array that describes treeview for filters';
    
    
-- ---------------------------------------------------------------------------------------------------------------------------------    
    UPDATE `sysIdentification` SET sys_dbVersion=1, sys_dbSubVersion=2, sys_dbSubSubVersion=0 WHERE sys_ID!=null;
    
