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
* DBUpgrade_1.x.x_to_1.x.x.sql: SQL file to update Heurist database format between indicated versions 
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

-- additional upgrades to future versions can be added as new files but should not be processed 
-- until the software is updated to expect them


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
    ADD  `trm_ReferenceURL` VARCHAR( 250 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL 
    COMMENT 'The URL to a semantic definition or web page describing the term' 
    AFTER  `trm_Code`;
     
    ALTER TABLE  `defTerms` 
    ADD  `trm_IllustrationURL` VARCHAR( 250 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL 
    COMMENT 'The URL to a picture or other resource illustrating the term. If blank, look for trm_ID.jpg/gif/png in term_images directory' 
    AFTER  `trm_Code` ;