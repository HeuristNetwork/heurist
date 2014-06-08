<?php

    /**
    * getDBStructureAsXML.php: returns database definitions (rectypes, details etc.) as XML (HML)
    * 
    * @param includeUgrps=1 will output user and group information in addition to definitions
    * @param approvedDefsOnly=1 will only output Reserved and Approved definitions
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2   
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    //    require_once(HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'/common/connect/applyCredentials.php');
    //    require_once(HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'/common/php/dbMySqlWrappers.php');

    header("Content-Type: application/xml");

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');



    // Normally jsut outputs definitions, this will include users/groups
    $includeUgrps=@$_REQUEST["includeUgrps"];    // returns null if not set

    $approvedDefsOnly=@$_REQUEST["approvedDefsOnly"];    // returns null if not set
    // TO DO: filter for reserved and approved definitions only if this is set


    // Deals with all the database connections stuff

    mysql_connection_select(DATABASE);
    if(mysql_error()) {
        die("Could not get database structure from given database source: MySQL error - unable to connect to database.");
    }

    // * IMPORTANT *
    // UPDATE THE FOLLOWING WHEN DATABASE FORMAT IS CHANGED:
    // Version info in common/config/initialise.php
    // admin/setup/dbcreate/blankDBStructure.sql - dump structure of hdb_H3CoreDefinitions database and insert where indicated in file
    // admin/setup/dbcreate/blankDBStructureDefinitionsOnly.sql - copy blankDBStructure.sql, delete non-definition tables for temp db creation speed
    // admin/setup/dbcreate/coreDefinitions.txt (get this from the admin interface lsiting in exchange format)
    // admin/setup/dbcreate/coreDefinitionsHuNI.txt (get this from the admin interface lsiting in exchange format)
    // admin/setup/dbcreate/coreDefinitionsFAIMS.txt (get this from the admin interface lsiting in exchange format)

    // List of fields in the include files in admin/structure/crosswalk, used for getDBStructure and insertions
    // print statements at the end of getDBStructure/getDBStructureAsXXX.php, must match the include files

    print '<?xml version="1.0" encoding="UTF-8"?>';
    print "\n<hml_structure>";    

    // TODO: ADD OTHER XML HEADER INFORMATION *************

    // File headers to explain what the listing represents and for version checking
    print "\n<!--Heurist Definitions Exchange File, generated: ".date("d M Y @ H:i")."-->";
    print "\n<HeuristBaseURL>" . HEURIST_BASE_URL. "</HeuristBaseURL>";
    print "\n<HeuristDBName>" . HEURIST_DBNAME . "</HeuristDBName>";
    print "\n<HeuristProgVersion>".HEURIST_VERSION."</HeuristProgVersion>";

    // *** MOST IMPORTANT ***
    // ** Check this on structure import to make sure versions match **
    // However use of XML tags allows import even if structure has evolved
    print "\n<HeuristDBVersion>".HEURIST_DBVERSION."</HeuristDBVersion>"; 


    // Now output each of the definition tables as data for an insert statement. 

    // ------------------------------------------------------------------------------------------
    // defRecTypeGroups

    print "\n<RecTypeGroups>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defRecTypeGroupsFields.inc'; // sets value of $flds
    $query = "select $flds from defRecTypeGroups";
    $res = mysql_query($query);
    $fmt = 'defRecTypeGroups';   // update format if fields added
    print "\n<!-- $flds -->";
    print "<rtg>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</rtg>";
    print "</RecTypeGroups>";

    // ------------------------------------------------------------------------------------------
    // defDetailTypeGroups

    print "\n<DetailTypeGroups>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defDetailTypeGroupsFields.inc'; // sets value of $flds
    $query = "select $flds from defDetailTypeGroups";
    $res = mysql_query($query);
    $fmt = 'defDetailTypeGroups'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<dtg>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</dtg>";
    print "</DetailTypeGroups>";

    // ------------------------------------------------------------------------------------------
    // Detail Type ONTOLOGIES

    print "\n<Ontologies>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defOntologiesFields.inc'; // sets value of $flds
    $query = "select $flds from defOntologies";
    $res = mysql_query($query);
    $fmt = 'defOntologies'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<ont>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</ont>";
    print "</Ontologies>";

    // ------------------------------------------------------------------------------------------
    // Detail Type TERMS

    print "\n<Terms>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defTermsFields.inc'; // sets value of $flds
    $query = "select $flds from defTerms";
    $res = mysql_query($query);
    $fmt = 'defTerms';  // update format if fields added
    print "\n<!-- $flds -->";
    print "<trm>";
    while ($row = mysql_fetch_assoc($res)) {   @print_row($row, $fmt, $flds); }
    print "</trm>";
    print "</Terms>";


    // ------------------------------------------------------------------------------------------
    // RECORD TYPES (this will be repeated for each of the tables)

    print "\n<RecordTypes>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defRecTypesFields.inc'; // sets value of $flds
    $query = "select $flds from defRecTypes";
    $res = mysql_query($query);
    $fmt = 'defRecTypes'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<rty>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</rty>";
    print "</RecordTypes>";


    // ------------------------------------------------------------------------------------------
    // DETAIL TYPES

    print "\n<DetailTypes>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defDetailTypesFields.inc'; // sets value of $flds
    $query = "select $flds from defDetailTypes";
    $res = mysql_query($query);
    $fmt = 'defDetailTypes';  // update format if fields added
    print "\n<!-- $flds -->";
    print "<dty>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</dty>";
    print "</DetailTypes>";


    // ------------------------------------------------------------------------------------------
    // RECORD STRUCTURE

    print "\n<RecordStructures>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defRecStructureFields.inc'; // sets value of $flds
    $query = "select $flds from defRecStructure";
    $res = mysql_query($query);
    $fmt = 'defRecStructure'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<rst>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</rst>";
    print "</RecordStructures>";

    // ------------------------------------------------------------------------------------------
    // RELATIONSHIP CONSTRAINTS

    print "\n<RelationConstraints>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defRelationshipConstraintsFields.inc'; // sets value of $flds
    $query = "select $flds from defRelationshipConstraints";
    $res = mysql_query($query);
    $fmt = 'defRelationshipConstraints'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<rcs>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</rcs>";
    print "</RelationConstraints>";

    // ------------------------------------------------------------------------------------------
    // defFileExtToMimetype

    print "\n<FileExtToMimeTypes>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defFileExtToMimetypeFields.inc'; // sets value of $flds
    $query = "select $flds from defFileExtToMimetype";
    $res = mysql_query($query);
    $fmt = 'defFileExtToMimetype'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<fxm>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</fxm>";
    print "</FileExtToMimeTypes>";

    // ------------------------------------------------------------------------------------------
    // defTranslations

    print "\n<Translations>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defTranslationsFields.inc'; // sets value of $flds
    $query = "select $flds from defTranslations where trn_Source in
    ('rty_Name', 'dty_Name', 'ont_ShortName', 'vcb_Name', 'trm_Label', 'rst_DisplayName', 'rtg_Name')";
    // filters to only definition (not data) translations - add others as required
    $res = mysql_query($query);
    $fmt = 'defTranslations'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<trn>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</trn>";
    print "</Translations>";

    // ------------------------------------------------------------------------------------------
    // defCalcFunctions

    print "\n<CalcFunctions>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defCalcFunctionsFields.inc'; // sets value of $flds
    $query = "select $flds from defCalcFunctions";
    $res = mysql_query($query);
    $fmt = 'defCalcFunctions'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<cfn>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</cfn>";
    print "</CalcFunctions>";

    // ------------------------------------------------------------------------------------------
    // defCrosswalk

    print "\n<Crosswalks>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defCrosswalkFields.inc'; // sets value of $flds
    $query = "select $flds from defCrosswalk";
    $res = mysql_query($query);
    $fmt = 'defCrosswalk'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<crw>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</crw>";
    print "</Crosswalks>";

    // ------------------------------------------------------------------------------------------
    // defLanguages

    print "\n<Languages>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defLanguagesFields.inc'; // sets value of $flds
    $query = "select $flds from defLanguages";
    $res = mysql_query($query);
    $fmt = 'defLanguages';  // update format if fields added
    print "\n<!-- $flds -->";
    print "<lng>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</lng>";
    print "</Languages>";

    // ------------------------------------------------------------------------------------------
    // defURLPrefixes

    print "\n<URLPrefixes>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/defURLPrefixesFields.inc'; // sets value of $flds
    $query = "select $flds from defURLPrefixes";
    $res = mysql_query($query);
    $fmt = 'defURLPrefixes';  // update format if fields added
    print "\n<!-- $flds -->";
    print "<urp>";
    while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt, $flds); }
    print "</urp>";
    print "</URLPrefixes>";

    // ------------------------------------------------------------------------------------------
    // Output the following only if parameter switch set and user is an admin

    if (!$includeUgrps) {
        print "\n<!-- User and group information not requested -->";
        print "\n</hml_structure>";
        return;
    }

    if (! is_admin()) {
        print "\n<!-- You do not have sufficient privileges to list users and groups -->";
        print "\n</hml_structure>";
        return;
    }
    // ------------------------------------------------------------------------------------------
    // sysUGrps

    print "\n<UserGroups>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/sysUGrpsFields.inc'; // sets value of $flds
    $query = "select $flds from sysUGrps";
    $res = mysql_query($query);
    $fmt = 'sysUGrps'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<ugr>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt, $flds); }
    print "</ugr>";
    print "</UserGroups>";

    // ------------------------------------------------------------------------------------------
    // sysUsrGrpLinks

    print "\n<UserGroupLinks>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/sysUsrGrpLinksFields.inc'; // sets value of $flds
    $query = "select $flds from sysUsrGrpLinks";
    $res = mysql_query($query);
    $fmt = 'sysUsrGrpLinks'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<ugl>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt, $flds); }
    print "</ugl>";
    print "</UserGroupLinks>";

    // ------------------------------------------------------------------------------------------
    // usrHyperlinkFilters

    print "\n<UserHyperlinkFilters>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/usrHyperlinkFiltersFields.inc'; // sets value of $flds
    $query = "select $flds from usrHyperlinkFilters";
    $res = mysql_query($query);
    $fmt = 'usrHyperlinkFilters'; // update format if fields added
    print "\n<!-- $flds -->";
    print "<hyf>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt, $flds); }
    print "</hyf>";
    print "</UserHyperlinkFilters>";

    // ------------------------------------------------------------------------------------------
    // usrTags

    print "\n<UserTags>";
    include HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH.'admin/structure/crosswalk/usrTagsFields.inc'; // sets value of $flds
    $query = "select $flds from UsrTags";
    $res = mysql_query($query);
    $fmt = 'UsrTags';  // update format if fields added
    print "\n<!-- $flds -->";
    print "<tag>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt, $flds); }
    print "</tag>";
    print "</UserTags>";



    print '\n</hml_structure>'; // end of file 


    // --------------------------------------------------------------------------------------

    function html_escape($s) {
        // TODO: 6/6/14 - we used mysql_real_escape_string because we were producing SQL. Now we are producing XML, do we need it?
        return(htmlspecialchars(mysql_real_escape_string($s)));
    } // html_escape

    function print_row($row,$fmt,$flds) {

        // Prints a formatted representation of the data retreived for one row in the query
        // Make sure that the query you passed in generates the fields you want to print
        // Specify fields with $row[fieldname] or $row['fieldname'] (in most cases the quotes
        // are unecessary although perhaps syntactically proper)

        // *** If the order in these prints does not corresponmd with the order in the include files listing fields,
        // data will load in the wrong fields and the crosswalking function will fail unpredictably ***

        switch ($fmt) {  // select the output formatting according to the table

            case 'defRecTypeGroups': // Data from record type classes table
                $rtg_Name = html_escape($row['rtg_Name']);
                $rtg_Description = html_escape($row['rtg_Description']);
                print "<rtg_ID>$row[rtg_ID]</rtg_ID>".
                "<rtg_Name>$rtg_Name</rtg_Name>".
                "<rtg_Domain>$row[rtg_Domain]</rtg_Domain>".
                "<rtg_Order>$row[rtg_Order]</rtg_Order>".
                "<rtg_Description>$rtg_Description</rtg_Description>";
                break;

            case 'defDetailTypeGroups': // Data from detail type classes table
                $dtg_Name = html_escape($row['dtg_Name']);
                $dtg_Description = html_escape($row['dtg_Description']);
                print "<dtg_ID>$row[dtg_ID]</dtg_ID>".
                "<dtg_Name>$dtg_Name</dtg_Name>".
                "<dtg_Order>$row[dtg_Order]</dtg_Order>".
                "<dtg_Description>$dtg_Description</dtg_Description>";
                break;

            case 'defRecTypes': // Data from the defRecTypes table
                if($row['rty_ID'] != 0) {
                    $rty_Name = html_escape($row['rty_Name']); // escapes RTF-8 characters
                    $rty_Description = html_escape($row['rty_Description']);
                    $rty_TitleMask = html_escape($row['rty_TitleMask']);
                    $rty_CanonicalTitleMask = html_escape($row['rty_CanonicalTitleMask']);
                    $rty_Plural = html_escape($row['rty_Plural']);
                    $rty_NameInOriginatingDB = html_escape($row['rty_NameInOriginatingDB']);
                    $rty_RecTypeGroupID = html_escape($row['rty_RecTypeGroupID']);
                    $rty_ReferenceURL = html_escape($row['rty_ReferenceURL']);
                    $rty_AlternativeRecEditor = html_escape($row['rty_AlternativeRecEditor']);
                    print "<rty_ID>$row[rty_ID]</rty_ID>".
                    "<rty_Name>$rty_Name</rty_Name>".
                    "<rty_OrderInGroup>$row[rty_OrderInGroup]</rty_OrderInGroup>".
                    "<rty_Description>$rty_Description</rty_Description>".
                    "<rty_TitleMask>$rty_TitleMask</rty_TitleMask>".
                    "<rty_CanonicalTitleMask>$rty_CanonicalTitleMask</rty_CanonicalTitleMask>".
                    "<rty_Plural>$rty_Plural</rty_Plural><rty_Status>$row[rty_Status]</rty_Status>".
                    "<rty_OriginatingDBID>$row[rty_OriginatingDBID]</rty_OriginatingDBID>".
                    "<rty_NameInOriginatingDB>$rty_NameInOriginatingDB</rty_NameInOriginatingDB>".
                    "<rty_IDInOriginatingDB>$row[rty_IDInOriginatingDB]</rty_IDInOriginatingDB>".
                    "<rty_NonOwnerVisibility>$row[rty_NonOwnerVisibility]</rty_NonOwnerVisibility>".
                    "<rty_ShowInLists>$row[rty_ShowInLists]</rty_ShowInLists>".
                    "<rty_RecTypeGroupID>$rty_RecTypeGroupID</rty_RecTypeGroupID>".
                    "<rty_RecTypeModelIDs>$row[rty_RecTypeModelIDs]</rty_RecTypeModelIDs>".
                    "<rty_FlagAsFieldset>$row[rty_FlagAsFieldset]</rty_FlagAsFieldset>".
                    "<rty_ReferenceURL>$rty_ReferenceURL</rty_ReferenceURL>".
                    "<rty_AlternativeRecEditor>$rty_AlternativeRecEditor</rty_AlternativeRecEditor>".
                    "<rty_Type>$row[rty_Type]</rty_Type>".
                    "<rty_ShowURLOnEditForm>$row[rty_ShowURLOnEditForm]</rty_ShowURLOnEditForm>".
                    "<rty_ShowDescriptionOnEditForm>$row[rty_ShowDescriptionOnEditForm]".
                    "</rty_ShowDescriptionOnEditForm><rty_LocallyModified>$row[rty_LocallyModified]".
                    "</rty_LocallyModified>";
                }
                break;

            case 'defDetailTypes': // Data from the recDetails table
                $dty_Name = html_escape($row['dty_Name']);
                $dty_Documentation = html_escape($row['dty_Documentation']);
                $dty_HelpText = html_escape($row['dty_HelpText']);
                $dty_ExtendedDescription = html_escape($row['dty_ExtendedDescription']);
                $dty_NameInOriginatingDB = html_escape($row['dty_NameInOriginatingDB']);
                $dty_EntryMask = html_escape($row['dty_EntryMask']);
                $dty_JsonTermIDTree = html_escape($row['dty_JsonTermIDTree']);
                $dty_TermIDTreeNonSelectableIDs = html_escape($row['dty_TermIDTreeNonSelectableIDs']);
                $dty_PtrTargetRectypeIDs = html_escape($row['dty_PtrTargetRectypeIDs']);
                print "<dty_ID>$row[dty_ID]</dty_ID>".
                "<dty_Name>$dty_Name</dty_Name>".
                "<dty_Documentation>$dty_Documentation</dty_Documentation>".
                "<dty_Type>$row[dty_Type]</dty_Type>".
                "<dty_HelpText>$dty_HelpText</dty_HelpText>".
                "<dty_ExtendedDescription>$dty_ExtendedDescription</dty_ExtendedDescription>".
                "<dty_EntryMask>$dty_EntryMask</dty_EntryMask>".
                "<dty_Status>$row[dty_Status]</dty_Status>".
                "<dty_OriginatingDBID>$row[dty_OriginatingDBID]</dty_OriginatingDBID>".
                "<dty_NameInOriginatingDB>$dty_NameInOriginatingDB</dty_NameInOriginatingDB>".
                "<dty_IDInOriginatingDB>$row[dty_IDInOriginatingDB]</dty_IDInOriginatingDB>".
                "<dty_DetailTypeGroupID>$row[dty_DetailTypeGroupID]</dty_DetailTypeGroupID>".
                "<dty_OrderInGroup>$row[dty_OrderInGroup]</dty_OrderInGroup>".
                "<dty_JsonTermIDTree>$dty_JsonTermIDTree</dty_JsonTermIDTree>".
                "<dty_TermIDTreeNonSelectableIDs>$dty_TermIDTreeNonSelectableIDs</dty_TermIDTreeNonSelectableIDs>".
                "<dty_PtrTargetRectypeIDs>$dty_PtrTargetRectypeIDs</dty_PtrTargetRectypeIDs>".
                "<dty_FieldSetRecTypeID>$row[dty_FieldSetRecTypeID]</dty_FieldSetRecTypeID>".
                "<dty_ShowInLists>$row[dty_ShowInLists]</dty_ShowInLists>".
                "<dty_NonOwnerVisibility>$row[dty_NonOwnerVisibility]</dty_NonOwnerVisibility>".
                "<dty_LocallyModified>$row[dty_LocallyModified]</dty_LocallyModified>";
                break;

            case 'defRecStructure': // Data from the defRecStructure table
                $rst_DisplayName = html_escape($row['rst_DisplayName']);
                $rst_DisplayHelpText = html_escape($row['rst_DisplayHelpText']);
                $rst_DisplayExtendedDescription = html_escape($row['rst_DisplayExtendedDescription']);
                $rst_DefaultValue = html_escape($row['rst_DefaultValue']);
                $rst_FilteredJsonTermIDTree = html_escape($row['rst_FilteredJsonTermIDTree']);
                $rst_TermIDTreeNonSelectableIDs = html_escape($row['rst_TermIDTreeNonSelectableIDs']);
                $rst_PtrFilteredIDs = html_escape($row['rst_PtrFilteredIDs']);
                print "<rst_ID>$row[rst_ID]</rst_ID>".
                "<rst_RecTypeID>$row[rst_RecTypeID]</rst_RecTypeID>".
                "<rst_DetailTypeID>$row[rst_DetailTypeID]</rst_DetailTypeID>".
                "<rst_DisplayName>$rst_DisplayName</rst_DisplayName>".
                "<rst_DisplayHelpText>$rst_DisplayHelpText</rst_DisplayHelpText>".
                "<rst_DisplayExtendedDescription>$rst_DisplayExtendedDescription</rst_DisplayExtendedDescription>".
                "<rst_DisplayOrder>$row[rst_DisplayOrder]</rst_DisplayOrder>".
                "<rst_DisplayWidth>$row[rst_DisplayWidth]</rst_DisplayWidth>".
                "<rst_DefaultValue>$rst_DefaultValue</rst_DefaultValue>".
                "<rst_RecordMatchOrder>$row[rst_RecordMatchOrder]</rst_RecordMatchOrder>".
                "<rst_CalcFunctionID>$row[rst_CalcFunctionID]</rst_CalcFunctionID>".
                "<rst_RequirementType>$row[rst_RequirementType]</rst_RequirementType>".
                "<rst_NonOwnerVisibility>$row[rst_NonOwnerVisibility]</rst_NonOwnerVisibility>".
                "<rst_Status>$row[rst_Status]</rst_Status>".
                "<rst_MayModify>$row[rst_MayModify]</rst_MayModify>".
                "<rst_OriginatingDBID>$row[rst_OriginatingDBID]</rst_OriginatingDBID>".
                "<rst_IDInOriginatingDB>$row[rst_IDInOriginatingDB]</rst_IDInOriginatingDB>".
                "<rst_MaxValues>$row[rst_MaxValues]</rst_MaxValues>".
                "<rst_MinValues>$row[rst_MinValues]</rst_MinValues>".
                "<rst_DisplayDetailTypeGroupID>$row[rst_DisplayDetailTypeGroupID]</rst_DisplayDetailTypeGroupID>".
                "<rst_FilteredJsonTermIDTree>$rst_FilteredJsonTermIDTree</rst_FilteredJsonTermIDTree>".
                "<rst_PtrFilteredIDs>$rst_PtrFilteredIDs</rst_PtrFilteredIDs>".
                "<rst_OrderForThumbnailGeneration>$row[rst_OrderForThumbnailGeneration]</rst_OrderForThumbnailGeneration>".
                "<rst_TermIDTreeNonSelectableIDs>$rst_TermIDTreeNonSelectableIDs</rst_TermIDTreeNonSelectableIDs>".
                "<rst_LocallyModified>$row[rst_LocallyModified]</rst_LocallyModified>";
                break;

            case 'defTerms': // Data from the rec_details_lookup table
                $trm_Label = htmlspecialchars(html_escape($row['trm_Label']));
                $trm_Description = html_escape($row['trm_Description']);
                $trm_NameInOriginatingDB = htmlspecialchars(html_escape($row['trm_NameInOriginatingDB']));
                print "<trm_ID>$row[trm_ID]</trm_ID>".
                "<trm_Label>$trm_Label</trm_Label>".
                "<trm_InverseTermId>$row[trm_InverseTermId]</trm_InverseTermId>".
                "<trm_Description>$trm_Description</trm_Description>".
                "<trm_Status>$row[trm_Status]</trm_Status>".
                "<trm_OriginatingDBID>$row[trm_OriginatingDBID]</trm_OriginatingDBID>".
                "<trm_NameInOriginatingDB>$trm_NameInOriginatingDB</trm_NameInOriginatingDB>".
                "<trm_IDInOriginatingDB>$row[trm_IDInOriginatingDB]</trm_IDInOriginatingDB>".
                "<trm_AddedByImport>$row[trm_AddedByImport]</trm_AddedByImport>".
                "<trm_IsLocalExtension>$row[trm_IsLocalExtension]</trm_IsLocalExtension>".
                "<trm_Domain>$row[trm_Domain]</trm_Domain>".
                "<trm_OntID>$row[trm_OntID]</trm_OntID>".
                "<trm_ChildCount>$row[trm_ChildCount]</trm_ChildCount>".
                "<trm_ParentTermID>$row[trm_ParentTermID]</trm_ParentTermID>".
                "<trm_Depth>$row[trm_Depth]</trm_Depth>".
                "<trm_Modified>$row[trm_Modified]</trm_Modified>".
                "<trm_LocallyModified>$row[trm_LocallyModified]</trm_LocallyModified>".
                "<trm_Code>$row[trm_Code]</trm_Code>";
                // WARNING! This needs to be updated in sync with new db structure to be added for DB Version 1.2.0 for FAIMS compatibility
                // '$row[trm_ReferenceURL]','$row[trm_IllustrationURL]'),"; // for db version 1.2.0 @ 1/10/13
                break;

            case 'defOntologies': // Data from Ontologies table
                $ont_ShortName = html_escape($row['ont_ShortName']);
                $ont_FullName = html_escape($row['ont_FullName']);
                $ont_Description = html_escape($row['ont_Description']);
                $ont_RefURI = html_escape($row['ont_RefURI']);
                $ont_NameInOriginatingDB = html_escape($row['ont_NameInOriginatingDB']);
                print "<ont_ID>$row[ont_ID]</ont_ID>".
                "<ont_ShortName>$ont_ShortName</ont_ShortName>".
                "<ont_FullName>$ont_FullName</ont_FullName>".
                "<ont_Description>$ont_Description</ont_Description>".
                "<ont_RefURI>$ont_RefURI</ont_RefURI>".
                "<ont_Status>$row[ont_Status]</ont_Status>".
                "<ont_OriginatingDBID>$row[ont_OriginatingDBID]</ont_OriginatingDBID>".
                "<ont_NameInOriginatingDB>$ont_NameInOriginatingDB</ont_NameInOriginatingDB>".
                "<ont_IDInOriginatingDB>$row[ont_IDInOriginatingDB]</ont_IDInOriginatingDB>".
                "<ont_Order>$row[ont_Order]</ont_Order>".
                "<ont_LocallyModified>$row[ont_LocallyModified]</ont_LocallyModified>";
                break;

            case 'defRelationshipConstraints': // Data from relationship constraints table
                $rcs_Description = html_escape($row['rcs_Description']);
                print "<rcs_ID>row[rcs_ID]</rcs_ID>".
                "<rcs_SourceRectypeID>$row[rcs_SourceRectypeID]</rcs_SourceRectypeID>".
                "<rcs_TargetRectypeID>$row[rcs_TargetRectypeID]</rcs_TargetRectypeID>".
                "<rcs_Description>$rcs_Description</rcs_Description>".
                "<rcs_RelationshipsLimit>$row[rcs_RelationshipsLimit]</rcs_RelationshipsLimit>".
                "<rcs_Status>$row[rcs_Status]</rcs_Status>".
                "<rcs_OriginatingDB>$row[rcs_OriginatingDB]</rcs_OriginatingDB>".
                "<rcs_IDInOriginatingDB>$row[rcs_IDInOriginatingDB]</rcs_IDInOriginatingDB>".
                "<rcs_TermID>$row[rcs_TermID]</rcs_TermID>".
                "<rcs_TermLimit>$row[rcs_TermLimit]</rcs_TermLimit>".
                "<rcs_LocallyModified>$row[rcs_LocallyModified]</rcs_LocallyModified>";
                break;

            case 'defFileExtToMimetype': // Data from field extension to mimetype table
                $fxm_Extension = html_escape($row['fxm_Extension']);
                $fxm_MimeType = html_escape($row['fxm_MimeType']);
                $fxm_IconFileName = html_escape($row['fxm_IconFileName']);
                $fxm_FiletypeName = html_escape($row['fxm_FiletypeName']);
                $fxm_ImagePlaceholder = html_escape($row['fxm_ImagePlaceholder']);
                print "<fxm_Extension>$fxm_Extension</fxm_Extension>".
                "<fxm_MimeType>$fxm_MimeType</fxm_MimeType>".
                "<fxm_OpenNewWindow>$row[fxm_OpenNewWindow]</fxm_OpenNewWindow>".
                "<fxm_IconFileName>$fxm_IconFileName</fxm_IconFileName>".
                "<fxm_FiletypeName>$fxm_FiletypeName</fxm_FiletypeName>".
                "<fxm_ImagePlaceholder>$fxm_ImagePlaceholder</fxm_ImagePlaceholder>";
                break;

            case 'defTranslations':
                $trn_Translation = html_escape($row['trn_Translation']);
                print "<trn_ID>$row[trn_ID]</trn_ID>".
                "<trn_Source>$row[trn_Source]</trn_Source>".
                "<trn_Code>$row[trn_Code]</trn_Code>".
                "<trn_LanguageCode3>$row[trn_LanguageCode3]</trn_LanguageCode3>".
                "<trn_Translation>$trn_Translation</trn_Translation>";
                break;

            case 'defCalcFunctions':
                $cfn_FunctionSpecification = html_escape($row['cfn_FunctionSpecification']);
                print "<cfn_ID>$row[cfn_ID]</cfn_ID>".
                "<cfn_Domain>$row[cfn_Domain]</cfn_Domain>".
                "<cfn_FunctionSpecification>$cfn_FunctionSpecification</cfn_FunctionSpecification>";
                break;

            case 'defCrosswalk':
                print "<crw_ID>$row[crw_ID]</crw_ID>".
                "<crw_SourcedbID>$row[crw_SourcedbID]</crw_SourcedbID>".
                "<crw_SourceCode>$row[crw_SourceCode]</crw_SourceCode>".
                "<crw_DefType>$row[crw_DefType]</crw_DefType>".
                "<crw_LocalCode>$row[crw_LocalCode]</crw_LocalCode>".
                "<crw_Modified>$row[crw_Modified]</crw_Modified>";
                break;

            case 'defLanguages':
                $lng_Name = html_escape($row['lng_Name']);
                $lng_Notes = html_escape($row['lng_Notes']);
                print "<NISOZ3953>$row[NISOZ3953]</NISOZ3953>".
                "<lng_ISO639>$row[lng_ISO639]</lng_ISO639>".
                "<lng_Name>$lng_Name</lng_Name>".
                "<lng_Notes>$lng_Notes</lng_Notes>";
                break;

            case 'defURLPrefixes':
                $urp_Prefix = html_escape($row['urp_Prefix']);
                print "<urp_ID>$row[urp_ID]</urp_ID>".
                "<urp_Prefix>$urp_Prefix</urp_Prefix>";
                break;

                // Note: these have not yet (Sep 2011) been implemented in buildCrosswalks.php as they are not really needed

            case 'sysUGrps': // User details - data from sysUGrps table
                $ugr_Name = html_escape($row['ugr_Name']);
                $ugr_LongName = html_escape($row['ugr_LongName']);
                $ugr_Description = html_escape($row['ugr_Description']);
                $ugr_Password = html_escape($row['ugr_Password']);
                $ugr_eMail = html_escape($row['ugr_eMail']);
                $ugr_FirstName = html_escape($row['ugr_FirstName']);
                $ugr_LastName = html_escape($row['ugr_LastName']);
                $ugr_Departement = html_escape($row['ugr_Departement']);
                $ugr_Organisation = html_escape($row['ugr_Organisation']);
                $ugr_City = html_escape($row['ugr_City']);
                $ugr_State = html_escape($row['ugr_State']);
                $ugr_Postcode = html_escape($row['ugr_Postcode']);
                $ugr_Interests = html_escape($row['ugr_Interests']);
                $ugr_IncomingEmailAddresses = html_escape($row['ugr_IncomingEmailAddresses']);
                $ugr_TargetEmailAddresses = html_escape($row['ugr_TargetEmailAddresses']);
                $ugr_URLs = html_escape($row['ugr_URLs']);
                print "<ugr_ID>$row[ugr_ID]</ugr_ID>".
                "<ugr_Type>$row[ugr_Type]</ugr_Type>".
                "<ugr_Name>$ugr_Name</ugr_Name>".
                "<ugr_LongName>$ugr_LongName</ugr_LongName>".
                "<ugr_Description>$ugr_Description</ugr_Description>".
                "<ugr_Password>$ugr_Password</ugr_Password>".
                "<ugr_eMail>$ugr_eMail</ugr_eMail>".
                "<ugr_FirstName>$ugr_FirstName</ugr_FirstName>".
                "<ugr_LastName>$ugr_LastName</ugr_LastName>".
                "<ugr_Department>$ugr_Department</ugr_Department>".
                "<ugr_Organisation>$ugr_Organisation</ugr_Organisation>".
                "<ugr_City>$ugr_City</ugr_City>".
                "<ugr_State>$ugr_State</ugr_State>".
                "<ugr_Postcode>$ugr_Postcode</ugr_Postcode>".
                "<ugr_Interests>$ugr_Interests</ugr_Interests>".
                "<ugr_Enabled>$row[ugr_Enabled]</ugr_Enabled>".
                "<ugr_LastLoginTime>$row[ugr_LastLoginTime]</ugr_LastLoginTime>".
                "<ugr_MinHyperlinkWords>$row[ugr_MinHyperlinkWords]</ugr_MinHyperlinkWords>".
                "<ugr_LoginCount>$row[ugr_LoginCount]</ugr_LoginCount>".
                "<ugr_IsModelUser>$row[ugr_IsModelUser]</ugr_IsModelUser>".
                "<ugr_IncomingEmailAddresses>$ugr_IncomingEmailAddresses</ugr_IncomingEmailAddresses>".
                "<ugr_TargetEmailAddresses>$ugr_TargetEmailAddresses</ugr_TargetEmailAddresses>".
                "<ugr_URLs>$ugr_URLs</ugr_URLs>".
                "<ugr_FlagJT>$row[ugr_FlagJT]</ugr_FlagJT>";
                break;

            case 'sysUsrGrpLinks': // user's membership and role in groups'
                print "<ugl_ID>$row[ugl_ID]</ugl_ID>".
                "<ugl_UserID>$row[ugl_UserID]</ugl_UserID>".
                "<ugl_GroupID>$row[ugl_GroupID]</ugl_GroupID>".
                "<ugl_Role>$row[ugl_Role]</ugl_Role>";
                break;

            case 'usrHyperlinkFilters': // User's hyperlink filter strings'
                $hyf_String = html_escape($row['hyf_String']);
                print "<hyf_String>$hyf_String</hyf_String>".
                "<hyf_UGrpID>$row[hyf_UGrpID]</hyf_UGrpID>";
                break;

            case 'UsrTags': // User's tagging values'
                $tag_Text = html_escape($row['tag_Text']);
                $tag_Description = html_escape($row['tag_Description']);
                print "<tag_ID>$row[tag_ID]</tag_ID>".
                "<tag_UGrpID>$row[tag_UGrpID]</tag_UGrpID>".
                "<tag_Text>$tag_Text</tag_Text>".
                "<tag_Description>$tag_Description</tag_Description>".
                "<tag_AddedByImport>$row[tag_AddedByImport]</tag_AddedByImport>";
                break;

                // Additional case statements here for additional tables if required

        } // end Switch

        print "\n";

    } // end function print_row
?>
