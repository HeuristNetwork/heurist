/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
There are 3 approaches for localization in proejct
1) Context help (popups), long snippets of html (for example empty message for result list), explanatory messages, inline help texts 
See /context_help folder. Localizations are in /ru, /fr …. subfolders
2) Translations for database definitions. Need to provide translations for labels and help text per field. See for example /hsapi/entity/defRecTypes_ru.json
3) Translations in js code and html snippets. 
3.1) Text in js code to be localized should be wrapped in function window.hWin.HR(‘keyword’)
3.2) Element in html snippet should have css class “slocale”. For example <span class=””slocale”>Keyword</locale>

Text to be localized may be either plain text such as “Open”, “Close”, “Database manager” or (if text is long or could be ambiguated in different widgets) some keyword (for example resultList_viewmode or error_message_need_login).

All these pairs are defined in localization.js. To translate to different language please copy localization.js tp localization_[lang code].js. and translate values for all pairs.
*/        


var regional = {};
regional['en'] = {
    language: 'English',
    Databases: 'Databases',
    
    //common words
    'Close': '',
    'Settings': '',
    'Help': '',
    'Error': '',
    'records': '',
    'loading': '',
    'preparing': '',
    'explanations': '',
    'Show data': '',
    'Actions': '',
    'Embed': '',
    'Clear': '',
    'Warning': '',
    'Cancel': '',
    'Record Info': '',
    'Add': '',
    'Assign': '',
    'Apply': '',
    'Abort': '',
    'Define': '',
    'Edit': '',
    'Delete': '',
    'Create': '',
    'Confirm': '',
    'Proceed': '',
    'Continue': '',
    'Go': '',
    'Yes': '',
    'OK': '',
    'No': '',
    'Save': '',
    'Save data': '',
    'Ignore and close': '',
    'Drop data changes': '',
    'Manage': '',
    'Select': '',
    'Configure': '',
    
    'Show context help': '',
    'Heurist context help': '',
    'Sorry context help was not found': '',
    
    //main menu 
    'Admin': '',
    'Design': '',
    'Populate': '',
    'Explore': '',
    'Publish': '',
    'Profile': '',
    
    add_new_record: 'New', //in main menu - header
    add_new_record2: 'New', //in main menu - prefix add new record
    
    //explore menu
    'Filters': '',
    'Recent': '',
    'All': '',
    'Entities': '',
    'Saved filters': '',
    'Build': '',
    'Filter builder': '',
    'Facets builder': '',
    'Save filter': '',
    'Advanced': '',
    'Rules': '',
    'Favourites': '',
    
    //admin menu
    'Database': '',
        menu_database_browse: 'Open',
        menu_database_create: 'New',
        menu_database_clone: 'Clone',
        menu_database_delete: 'Delete',
        menu_database_clear: 'Clear',
        menu_database_rollback: 'Rollback',
        menu_database_register: 'Register',
        menu_database_properties: 'Properties',
        
        menu_database_browse_hint:'Open and login to another Heurist database - current database remains open',
        menu_database_create_hint:'Create a new database on the current server - essential structure elements are populated automatically',
        menu_database_clone_hint:'Clones an identical database from the currrent database with all data, users, attached files, templates etc.',
        menu_database_delete_hint:'Delete the current database completely - cannot be undone, although data is copied to a backup which could be reloaded by a system administrator',
        menu_database_clear_hint:'Clear all data (records, values, attached files) from the current database. Database structure - record types, fields, terms, constraints - is unaffected',
        menu_database_rollback_hint:'Selectively roll back the data in the database to a specific date and time',
        menu_database_register_hint:'Register this database with the Heurist Reference Index - this makes the structure (but not data) available for import by other databases',
        menu_database_properties_hint:'Edit the internal metadata describing the database and set some global behaviours. Recommended to provide a self-documenting database',
    
    'Manage Users': '',
        menu_profile_groups: 'Workgroups',
        menu_profile_users: 'Users',
        menu_profile_import: 'Import user',
        menu_profile_groups_hint: 'List of groups you are member of',
        menu_profile_users_hint: 'Add and edit database users, including authorization of new users. Use Manage Workgroups to allocate users to workgroups and define roles within workgroups',
        menu_profile_import_hint: 'Import users one at a time from another database on the system, including user name, password and email address - users are NOT assigned to workgroups',
    'Utilities': '',
        menu_profile_files: 'Manage files',
        menu_profile_preferences: 'My preferences',
        menu_structure_verify: 'Verify integrity',
        menu_structure_duplicates: 'Find duplicate records',
        menu_manage_rectitles: 'Rebuild record titles',
        menu_interact_log: 'Interaction log',
        menu_profile_files_hint: 'Manage uploaded files and registered remote media resources',
        menu_profile_preferences_hint: 'Display preferences relating to behaviours related to this database',
        menu_structure_verify_hint: 'Find errors in database structure (invalid record type, field and term codes) and records with incorrect structure or inconsistent values (invalid pointer, missing data etc)',
        menu_structure_duplicates_hint: 'Fuzzy search to identify records which might contain duplicate data',
        menu_manage_rectitles_hint: 'Rebuilds the constructed record titles listed in search results, for all records',
        menu_interact_log_hint: 'Download the interaction log',
    'Server Manager': '',
        menu_admin_server: 'Manage databases',
        menu_admin_server_hint: 'Manage databases',

    //design menu
    'Modify': '',
        menu_structure_rectypes: 'Record types',
        menu_structure_vocabterms: 'Vocabularies',
        menu_structure_fieldtypes: 'Base fields',
        menu_structure_import: 'Browse templates',
        menu_structure_workflowstages: 'Workflow stages',
        menu_lookup_config: 'External lookups',
        menu_structure_summary: 'Visualise',
        menu_structure_refresh: 'Refresh memory',
    
        menu_structure_rectypes_hint: 'Add new and modify existing record types',
        menu_structure_vocabterms_hint: 'Add new and modify Vocabularies &amp; Terms',
        menu_structure_fieldtypes_hint: 'Browse and edit the base field definitions referenced by record types (often shared by multiple record types)',
        menu_structure_import_hint: 'Selectively import record types, fields, terms and connected record types from other Heurist databases',
        menu_structure_summary_hint: 'Visualise the internal connections between record types in the database and add connections (record pointers and relationship markers) between them',
        menu_structure_refresh_hint: 'Clear and reload Heurist\'s internal working memory in your browser. Use this to correct dropdowns etc. if recent additions and changes do not show.',

    'Setup': '',
        menu_manage_dashboards: 'Shortcuts bar',
        menu_manage_archive: 'Archive package',
        menu_manage_dashboards_hint: 'Defines an editable list of shortcuts to functions to be displayed on a toolbar at startup unless turned off',
        menu_manage_archive_hint: 'Writes all the data in the database as SQL and XML files, plus all attached files, schema and documentation, to a ZIP file which you can download from a hyperlink',
    
    'Download': '',
        menu_manage_structure_asxml: 'Structure (XML)',
        menu_manage_structure_assql: 'Structure (Text)',
        menu_manage_structure_asxml_hint: 'Lists the record type and field definitions in XML format (HML - Heurist Markup Language)',
        menu_manage_structure_assql_hint: 'Lists the record type and field definitions in an SQL-related computer-readable form (deprecated 2014)',

    //populate menu
    'Upload Files': '',
        menu_import_csv: 'Delimited text / CSV',
        menu_import_zotero: 'Zotero bibliography sync',
        menu_import_xml: 'Heurist XML/JSon',
        menu_import_kml: 'KML',
        menu_import_get_template: 'Download template (XML)',
        menu_import_csv_hint: 'Import data from delimited text file. Supports matching, record creation, record update.',
        menu_import_zotero_hint: 'Synchronise with a Zotero web library _ new records are added to Heurist, existing records updated',
        menu_import_xml_hint: 'Import records from another database',
        menu_import_kml_hint: 'Import KML files (geographic data in WKT can be imported from CSV &amp; tab delimited files)',
        menu_import_get_template_hint: 'Get XML template that describes database structure', 
    'Media Files': '',
        menu_files_upload: 'Upload media files',
        menu_files_index: 'Index media files',
        menu_files_upload_hint: 'Upload multiple files and/or large files to scratch space or image directories, delete and rename uploaded files',
        menu_files_index_hint: 'Index files on the server and create multimedia records for them (reads and creates FieldHelper manifests)',
        
    //publish menu
    'Website': '',
        menu_cms_create: 'Create',
        menu_cms_edit: 'Edit',
        menu_cms_view: 'View',
        menu_cms_embed: 'Standalone web page',
        menu_cms_create_hint: 'Create a new CMS website, stored in this database, with widgets to access data in the database',
        menu_cms_edit_hint: 'Edit a CMS website defined in this database',
        menu_cms_view_hint: 'View a CMS website defined in this database',
        menu_cms_embed_hint: 'Create or edit a standalone web page in this database. May contain Heurist widgets. Provides code to embed page in an external iframe.',
        
        'Website Editor': '',
        'Sitewide Settings': '',
        'Current Page': '',
        'Page Editor': '',
        
    'Standalone web page': '',
        menu_cms_create_page: 'Create',
        menu_cms_edit_page: 'Edit',
        menu_cms_view_page: 'View',
        
    'Export': '',
        menu_export_csv: 'CSV',
        menu_export_hml_resultset: 'XML',
        menu_export_json: 'JSON',
        menu_export_geojson: 'GeoJSON',
        menu_export_kml: 'KML',
        menu_export_gephi: 'GEPHI',
        menu_export_iiif: 'IIIF',
        menu_export_csv_hint: 'Export records as delimited text (comma/tab), applying record type',
        menu_export_hml_resultset_hint: 'Generate HML (Heurist XML format) for current set of search results (current query + expansion)',
        menu_export_json_hint: 'Generate JSON for current set of search results (current query)',
        menu_export_geojson_hint: 'Generate GeoJSON_T for current set of search results (current query)',
        menu_export_kml_hint: 'Generate KML for current set of search results (current query + expansion)',
        menu_export_gephi_hint: 'Generate GEPHI for current set of search results (current query + expansion)',
        menu_export_iiif_hnt: 'Generate IIIF manifest for current set of search results',
        
    'Safeguard':'',

    //My profile menu
    menu_profile_tags: 'Manage tags',
    menu_profile_reminders: 'Manage reminders',
    menu_profile_info: 'My user info',
    menu_profile_logout: 'Log out',
    menu_profile_tags_hint: 'Delete, combine, rename your personal and workgroup tags',
    menu_profile_reminders_hint: 'View and delete automatic notification emails sent from the records you have bookmarked',
    menu_profile_info_hint: 'User personal information',
    menu_profile_logout_hint: '',
        
    //HELP menu
    HELP: '',
        menu_help_online: 'Online help',
        menu_help_website: 'Heurist Network website',
        menu_help_online_hint: 'Comprehensive online help for the Heurist system',
        menu_help_website_hint: 'Heurist Network website - a source for a wide range of information, services and contacts', 
    CONTACT: '',
        menu_help_bugreport: 'Bug report/feature request',
        menu_help_emailteam: 'Heurist Team',
        menu_help_emailadmin: 'System Administrator',
        menu_help_acknowledgements: 'Acknowledgements',
        menu_help_about: 'About',
        menu_help_bugreport_hint: 'Send email to the Heurist team such as additional bug information, comments or new feature requests',
        menu_help_emailteam_hint: '',
        menu_help_emailadmin_hint: 'Send an email to the System Adminstrator for this installaton of Heurist - typically for probelms with this installation',
        menu_help_acknowledgements_hint: '',
        menu_help_about_hint: 'Version, Credits, Copyright, License',
    
    menu_subset_set: 'Set as subset',
    menu_subset_set_hint: 'Make the current filter the active subset to which all subsequent actions are applied',
    'Click to revert to whole database': '',
    'SUBSET ACTIVE': '',
    'Current subset': '',
    
    //END main menu
    
    //resultList --------------------
    
    'Selected': '',
        menu_selected_select_all: 'Select all',
        menu_selected_select_all_hint: 'Select All on page',
        menu_selected_select_none: 'Select none',
        menu_selected_select_none_hint: 'Clear selection',
        menu_selected_select_show: 'Show as search',
        menu_selected_select_show_hint: 'Launch selected records in a new search window',
        menu_selected_tag: 'Tag',
        menu_selected_tag_hint: 'Select one or more records, then click to add or remove tags',
        menu_selected_rate: 'Rate',
        menu_selected_rate_hint: 'Select one or more records, then click to set ratings',
        menu_selected_bookmark: 'Bookmark',
        menu_selected_bookmark_hint: 'Select one or more records, then click to bookmark',
        menu_selected_unbookmark: 'Unbookmark',
        menu_selected_unbookmark_hint: 'Select one or more records, then click to remove bookmarks',
        menu_selected_merge: 'Merge',
        menu_selected_merge_hint: 'Select one or more records, then click to identify/fix duplicates',
        menu_selected_delete: 'Delete',
        menu_selected_delete_hint: 'Select one or more records, then click to delete',
    'Collected': '',
    'Collect': '',
        menu_collected_add: 'Add',
        menu_collected_add_hint: 'Select one or more records, then click to add to the collection',
        menu_collected_del: 'Remove',
        menu_collected_del_hint: 'Select one or more records, then click to remove records from the collection',
        menu_collected_clear: 'Clear All',
        menu_collected_clear_hint: 'Empty records from the collection',
        menu_collected_save: 'Save as ...',
        menu_collected_save_hint: 'Save current set of records in collection',
        menu_collected_show: 'Show as search',
        menu_collected_show_hint: 'Show current set of records in collection',
            collection_limit: 'The number of selected records is above the limit in ',
            collection_select_hint:  'Please select at least one record to add to collection basket',
            collection_select_hint2: 'Please select at least one record to remove from collection basket',
            collection_url_hint: 'Collections are saved as a list of record IDs. The URL generated by this collection would exceed the maximum allowable URL length by of 2083 characters. Please save the current collection as a saved search (which allows a much larger number of records), or add fewer records.',
        
    'Recode': '',
        menu_selected_value_add: 'Add Field Value',
        menu_selected_value_add_hint: 'Add field value to filtered records',
        menu_selected_value_replace: 'Replace Field Value',
        menu_selected_value_replace_hint: 'Replace field value found in filtered records',
        menu_selected_value_delete: 'Delete Field Value',
        menu_selected_value_delete_hint: 'Delete field value found in filtered records',
        menu_selected_add_link: 'Relate: Link',
        menu_selected_add_link_hint: 'Add new link or create new relationship between records',
        menu_selected_rectype_change: 'Change record types',
        menu_selected_rectype_change_hint: 'Change record types for filtered records',
        menu_selected_extract_pdf: 'Extract text from PDF files',
        menu_selected_extract_pdf_hint: 'Extract text from PDF (experimental)',
    'Share': '',
        menu_selected_notify: 'Notify (email)',
        menu_selected_notify_hint: 'Select one or more records, then click to send notification',
        menu_selected_email: 'Send email',
        menu_selected_email_hint: 'Send an email message to the individuals or organisations described in the selected record',
        menu_selected_ownership: 'Ownership / visibility',
        menu_selected_ownership_hint: 'Select one or more records, then click to set workgroup ownership and visibility',
    'Reorder': '',
    menu_reorder_hint: 'Allows manual reordering of the current results and saving as a fixed list of ordered records',
    menu_reorder_title: 'Drag records up and down to position, then Save order to save as a fixed list in this order',
    menu_reorder_save: 'Save Order',
    
    //
    resultList_select_record: 'Please select at least one record for action',
    resultList_select_record2: 'Please select at least two records to identify/merge duplicate records',
    resultList_select_limit: 'The number of selected records is above the limit in ',
    resultList_noresult: 'No results found. Please modify search/filter to return at least one result record.',
    resultList_empty_remark: 'No entries match the filter criteria (entries may exist but may not have been made visible to the public or to your user profile)',
    resultList_private_record: 'This record is not publicly visible - user must be logged in to see it',
    'private - hidden from non-owners': '',
    'visible to any logged-in user': '',
    'pending (viewable by anyone, changes pending)': '',
    'public (viewable by anyone)': '',
    'show selected only': '',
    'Single lines': '',
    'Single lines with icon': '',
    'Small images': '',
    'Large image': '',
    'Record contents': '',
    resultList_view_content_hint1: 'This warning is triggered if there are more than 10 records',
    resultList_view_content_hint2: 'You have selected',
    resultList_view_content_hint3: 'records. This display mode loads complete information for each record and will take a long time to load and display all of these data.',
    resultList_action_edit: 'Click to edit record',
    resultList_action_edit2: 'Click to edit record (opens in new tab)',
    resultList_action_view: 'Click to view record (opens in popup)',
    resultList_action_view2: 'Click to view external link (opens in new window)',
    resultList_action_map: 'Click to show/hide on map',
    resultList_action_dataset: 'Download dataset',
    resultList_action_embded: 'Click to embed',
    resultList_action_delete: 'Click to delete',
    
    resultList_action_delete_hint: 'Delete map document. Associated map layers and data sources retain',
    'Password reminder': '',
    resultList_reorder_list_changed: 'Reorder list has been changed.',
    //END resultList
    
    //search - filters
    'Filter': '',
    'Filtered Result': '',
    'Save Filter': '',
    save_filter_hint: 'Save the current filter and rules as a link in the navigation tree',
     
    //edit 
    Warn_Lost_Data: 'You have made changes to the data. Click "Save" otherwise all changes will be lost.',     
    Warn_Lost_Data_On_Structure_Edit: 'Click "Save data" to save the data entered, "Drop data changes" to abandon modifications.<br>Structure changes are saved automatically - they are not affected by your choice.',
    
    //manage db definitions    
    manageDefRectypes_edit_fields: 'Edit fields',
    manageDefRectypes_edit_fields_hint: 'This will open a blank record of this type in structure modification mode.<br>Tip: You can switch on structure modification mode at any time while entering data to make instant structural changes',            
    manageDefRectypes_new_hint: 'Before defining new record (entity) types we suggest importing suitable '+
                    'definitions from templates (Heurist databases registered in the Heurist clearinghouse). '+
                    'Those with registration IDs less than 1000 are templates curated by the Heurist team. '
                    +'<br><br>'
    +'This is particularly important for BIBLIOGRAPHIC record types - the definitions in template #6 (Bibliographic definitions) are ' 
    +'optimally normalised and ensure compatibility with bibliographic functions such as Zotero synchronisation, Harvard format and inter-database compatibility.'                
                    +'<br><br>Use main menu:  Design > Browse templates',
    manageDefRectypes_delete_stop: '<p>Record type <b>[rtyName]</b> is referenced by the following fields:</p>'
                + '[FieldList]'
                +'<p>Please remove these fields altogether, or click the links above <br>to modify base field (will affect all record types which use it).</p>',
    manageDefRectypes_delete_warn: 'Are you sure you wish to delete this record type?',
    manageDefRectypes_duplicate_warn: 'Do you really want to duplicate record type ',
    'Click to add new': '',
    'Click to launch search for': '',
    'Click to edit record type': '',
    'Click to hide in lists': '',
    'Click to show in lists': '',
    'Duplicate record type': '',
    'List of fields': '',
    manageDefRectypes_reserved: 'This is a reserved record type common to all Heurist databases. It cannot be deleted.',
    manageDefRectypes_hasrecs: 'To allow deletion, use Explore > Entities to find and delete all records.',
    manageDefRectypes_delete: 'Click to delete this record type',
    manageDefRectypes_referenced: 'This record type is referenced. Click to show references',
    manageDefRectypes_longrequest: 'This is taking a long time, it is possible the database is not reachable at [url], will keep trying for another 10 seconds',
    'Filter by Group': '',
    'Show All': '',
    'Sort by': '',
    'All Find': '',
    'Not finding the record type you require?': '',
    'Define new record type': '',
    'Find by name, ID or concept code': '',
    'Show record types for all groups': '',
    'All Groups': '',
    'All record type groups': '',
    'Edit Title Mask': '',
                    
    //my preferences
    'Define Symbology': '',                
    'Define Heurist Theme': '',
    'Configure Interface': '',
    'Mark columns to be visible. Drag to re-order': '',
    
    //record add dialog    
    'Add Record': '',
    'Record addition settings': '',
    'Permission settings': '',
    'Save Settings': '',
    'Add Record in New Window': '',
    'Type of record to add': '',
    'Get Parameters': '',
    'Select record type for record to be added': '',
    'select record type': '',
    add_record_settings_hint: 'This settings will be applied and remembered when you select a record type from the list',

    //record actions dialogs
    'Processed records': '',
    recordAction_select_lbl: 'Records scope:',
    recordAction_select_hint: 'please select the records to be affected …',    
        'All records': '',
        'Selected results set (count=': '',
        'Current results set (count=': '',
        'only:': '',
    'Add or Remove Tags for Records': '',
        'Add tags': '',
        'Remove tags': '',
        'Bookmark': '',
        recordTag_hint0: 'Select tags to be added to bookmarks for chosen URLs<br>',
        recordTag_hint1: 'Select tags to be added to bookmarks for chosen record scope<br>',
        recordTag_hint2: 'Select tags to be added or removed for chosen record scope<br>',
        recordTag_hint3: 'Matching tags are shown as you type. Click on a listed tag to add it.<br>Unrecognised tags are added automatically as user-specific tags <br>(group tags must be added explicitly by a group administrator). Tags may contain spaces.',
        'No tags were affected': '',
        'Bookmarks added': '',
        'Tags added': '',
        'Tags removed': '',
    'Unbookmark selected records': '',    
        recordUnbookmark_hint: 'Select the scope of records with bookmarks to be removed.<br>'
            +'Any personal tags for these records will be detached <br>'
            +'These operation ONLY removes the bookmark from your resources, <br>'
            +'it does not delete the record entries<br>',
        'Remove Bookmarks': '',
        'Bookmarks removed': '',
    'Set Record Rating': '',
        'Set Rating': '',
        'Please specify rating value': '',
        'Rating updated': '',
        'No Rating': '',
    'Delete Records': '',
    
    //SERVER SIDE ERROR MESSAGES
    'Record type not defined or wrong': '',
    
    //Client side error message
    Error_Title: 'Heurist',
    Error_Empty_Message: 'No error message was supplied, please report to Heurist developers.',
    Error_Report_Code: 'Report this code to Heurist team',
    Error_Report_Team: 'If this error occurs repeatedly, please contact your system administrator or email us (support at HeuristNetwork dot org) and describe the circumstances under which it occurs so that we/they can find a solution',
    Error_Wrong_Request: 'The value, number and/or set of request parameters is not valid.',
    Error_System_Config: 'May result from a network outage, or because the system is not properly configured. If the problem persists, please report to your system administrator',
    Error_Json_Parse: 'Cannot parse server response',
    
//---------------------- END OF TRANSLATION 2021-10-19
//===================    
    
    'Design database': 'Design database',
    'Import data': 'Import data',
    'Please log in':'Please Log in or Register to use all features of Heurist.',
    'Session expired': 'It appears you are not logged in or your session has expired. Please reload the page to log in again',
    'Please contact to register':'Please contact database owner to register and use all features of Heurist.',
    'My Searches':'My Filters',
    'My Bookmarks':'My Bookmarks',

    'Password_Reset':'Your password has been reset. You should receive an email shortly with your new password.',

    'Error_Password_Reset':'Cannot complete operation.',
    'Error_Mail_Recovery':'Your password recovery email cannot be sent as the smtp mail system has not been properly installed on this server. Please ask your system administrator to correct the installation.',
    'Error_Mail_Registration':'Your registration info is added to database. However, it is not possible to approve it since registration email cannot be sent.',
    'Error_Mail_Approvement':'Cannot send registration approval email - please contact Heurist developers.',
    'Error_Connection_Reset':'Timeout on response from Heurist server.<br><br>'
    +'This may be due to an internet outage (the most common source), or due to server load or to requesting too large a result set, or a query that fails to resolve. '
    +'If the problem persists, please email a bug report to the Heurist team so we can investigate.',

    'New_Function_Conversion':'This function has not yet been converted from Heurist version 4 to version 5 (2018). ',
    
    'New_Function_Contact_Team':'If you need this function please send an email to heuristnetwork dot org or '
    +'click on the Bug report/feature request entry in the Help menu - we may already have an alpha version '
    +'that you can use or can prioritise development.',
    
    //OLD VERSION    No response from server. This may be due to too many simultaneous requests or a coding problem. Please report to Heurist developers if this reoccurs.',

    'mailto_fail': 'You have not set an email handler for mailto: links. '
    +'<br/>Please set this in your  browser (it is normally in the content settings under Privacy).',

    //titles
    'add_record':'Add new record',
    'add_detail':'Add field value',
    'replace_detail':'Replace field value',
    'delete_detail':'Delete field value',
    'rectype_change':'Change record (entity) type',
    'ownership':'Change record access and ownership',
    'add_link':'Add new link or create a relationship between records',
    'extract_pdf':'Extract text from PDF',

    //helps
    'record_action_add_record':' ',
    'record_action_add_detail':'This function adds a new value for a specific field to the set of records '
    +'selected in the dropdown. Existing values are unaffected. Addition will add values even if this '
    +'exceeds the valid repeat count (eg. more than one value for single-value fields) - check the database '
    +'afterwards for validity using Verify > Verify integrity.',
    
    'record_action_replace_detail':'This function replaces a specified value in a particular field for the '
    +'set of records selected in the dropdown. Other fields / values are not affected.',
    
    'record_action_delete_detail':'This function deletes a specified value in a particular field for the '
    +'set of records selected in the dropdown. Other fields / values are not affected. '
    +'Deletion will remove values including the last value in a required field - check the database '
    +'afterwards for validity using Verify > Verify integrity.',
    
    'record_action_rectype_change':('This function changes the record (entity) type of the set of records '
    +'selected in the dropdown. All records selected will be converted to the new type.'
        +'<br><br>'
        +'<b>Warning</b>: It is highly likely that the data recorded for many or all of these records will '
        +'not satisfy the conditions set for the record type to which they are converted. '
        +'<br><br>'
        +'Please use Verify > Verify integrity to locate and correct any invalid records generated by this process.'),    
    'record_action_ownership':'&nbsp;',
    'record_action_extract_pdf':('This function extracts text (up to 64,000 characters)  from any PDF files attached to a record and places the extracted text in the field specified (by default "Extracted text" (2-652), if defined). Bad characters encountered are ignored.'
        +'<br><br>'
+'If there is more than one PDF file, the text is placed in repeated values of the field. '
        +'<br><br>'
+'Text is only extracted if the (corresponding value) of the field is empty to avoid overwriting any text entered manually.'),
    
    //reports
    'record_action_passed': 'Records passed to process',
    'record_action_noaccess': 'Non-editable records found (may be due to permissions)',
    'record_action_processed': 'Records processed',
    'record_action_undefined_add_detail': 'Records with undefined fields',
    'record_action_undefined_replace_detail': 'Skipped due to no matching',
    'record_action_undefined_delete_detail': 'Skipped due to no matching',
    'record_action_limited_add_detail': 'Skipped due to exceeding repeat values limit',
    'record_action_limited_delete_detail': 'Skipped because required field cannot be deleted',
    
    'record_action_undefined_extract_pdf': 'Records with no associated pdf',
    'record_action_parseexception_extract_pdf': 'Records with locked PDFs (extraction forbidden)',
    'record_action_parseempty_extract_pdf': 'Records with no text to extract (eg. images)',
    'record_action_limited_extract_pdf': 'Records with field already populated',
    
    'record_action_errors': 'Errors',

    'thumbs3':'preview',
    
    'Collected':'Collect',
    'Shared':'Share',

};

// TODO: messages above, particularly the default startup message, have been substnatially modified/improved 6/11/17, 
//       so translated versions require update.

regional['nl'] = {
    language: 'Nederlands',
    'Databases': 'Databases',
    'Design database': 'Database vormgeving',
    'Import data': 'Gegevens importeren',
    'Files in situ': 'Bestanden in situ',
    'Report bug': 'Rapporteer fout',
    'Help': 'Hulp',
    'About': 'Over',
    'Confirmation':'Bevestiging',
    'Info':'Informatie',

    'Register': 'Registreer',
    'Registration': 'Registratie',
    'Heurist Login': 'Heurist Login',
    'Please log in': 'Registreer of log in om de volledige functionaliteit van Heurist te gebruiken',
    'Please contact to register':'Neem contact op met de database-eigenaar om toegang te krijgen',
    'Log out' : 'Uitloggen',
    'Cancel': 'Annuleren',
    'Save': 'Opslaan',
    'Close': 'Sluiten',
    'Assign': 'Toewijzen',
    'Add': 'Toevoegen',
    'Delete': 'Verwijderen',
    'Create': 'Maak',
    'Manage': 'Beheer',
    'Sort': 'Sorteer',
    'Search': 'Zoek',
    'Search result': 'Zoek resultaten',
    'Merge': 'Samenvoegen',
    'Rate': 'Beoordeel',
    'Relate to': 'Relateer met',
    'Access': 'Toegang',
    'Notify': 'Aankondiging',
    'Embed / link': 'Embed link',
    'Export': 'Exporteer',
    'Import': 'Importeer',

    'Username': 'Gebruikersnaam',
    'Password': 'Wachtwoord',
    'Remember me': 'Onthoud me',
    'Forgotten your password?': 'Wachtwoord vergeten?',
    'Click here to reset it': 'Klik hier om hem te resetten',
    'Enter your username OR email address below and a new password will be emailed to you.':'Voer je gebruikersnaam OF emailadres in om een nieuw wachtwoord toegestuurd te krijgen.',
    'Reset password': 'Reset wachtwoord',
    'Email to':'Email naar',
    'User information has been saved successfully':'Gebruikers informatie is succesvol opgeslagen',

    'Select database': 'Selecteer een database',
    'My User Info' : 'Mijn gebruikers info',
    'My Groups' : 'Mijn groepen',
    'Manage Tags' : 'Beheer labels',
    'Tags' : 'Labels',
    'Manage Files': 'Beheer bestanden',
    'Manage Reminders' : 'Beheer herinneringen',
    'Set Ratings' : 'Stel beoordeling in',
    'No Rating': 'Geen beoordelingen',

    'Name': 'Naam',
    'Description': 'Beschrijving',
    'Size': 'Grootte',
    'Type': 'Type',

    'search': 'zoek',
    'all records': 'alle gegevens',
    'my bookmarks': 'mijn bladwijzers',
    'in current': 'in huidige',
    'search option': 'zoek optie',
    'search assistant': 'zoek hulpje',
    'Any record type': 'Alle gegeven types',
    'Any field type': 'Alle veld types',
    'Record type': 'Gegeven type',
    'Field': 'Veld',
    'Contains': 'Bevat',
    'Is': 'is',
    'Sort By': 'Sorteer volgens',
    'Sort Ascending': 'Sorteer oplopend',
    'fields':'velden',

    'add': 'voeg toe',
    'Add New Record' : 'Voeg nieuwe gegevens toe',
    'tags': 'labels',
    'share': 'deel',
    'more': 'meer',
    'edit': 'bewerk',
    'options': 'opties',

    'by name': 'bij naam',
    'by usage': 'bij gebruik',
    'by date': 'bij datum',
    'by size': 'bij grootte',
    'by type': 'bij type',
    'marked': 'markeer',

    'list':'lijst',
    'icons':'iconen',
    'thumbs':'miniaturen',
    'thumbs3':'miniaturen 2',

    'first':'eerste',
    'previous':'vorige',
    'next':'volgende',
    'last':'laatste',
    'records per request':'gegevens per aanvraag',

    'Manage tags': 'Beheer labels',
    'Create new tag': 'Maak een nieuw label',
    'Add Tag': 'Voeg label toe',
    'Edit Tag': 'Bewerk label',
    'Define new tag that replaces old ones': 'Definieer een nieuw label dat de oude vervangt',
    'Length of tag must be more than 2.': 'Een label moet minstens 2 karakters bevatten',
    'Assign selected tags': 'Wijs geselecteerde labels toe',
    'Personal Tags':'Persoonlijke labels',
    'Delete selected tags': 'Verwijder geselecteerde labels',

    'Manage files': 'Beheer bestanden',
    'Upload/register new file': 'Upload/registreer nieuw bestand',
    'Add File': 'Voeg bestand toe',
    'Edit File': 'Bewerk bestand',
    'Personal Files':'Persoonlijke bestanden',
    'Delete selected files': 'Verwijder geselecteerde bestanden',

    'Preferences' : 'Voorkeuren',
    'Layout configuration file': 'Lay-out configuratie bestand',
    'Language':'Taal',
    'Theme':'Thema',
    'Open edit in new window':'Open in een nieuw venster',
    'When exiting record edit, prompt for tags if no tags have been set':'Vraag om nieuwe labels wanneer het bewerken van gegevens geannuleerd wordt, en er nog geen labels ingesteld zijn',
    'Reload page to apply new settings?':'Pagina herladen om de nieuwe instellingen in gebruik te nemen?',

    'Click to save current search':'Klik om de huidige zoekopdracht op te slaan',
    'My Bookmarks': 'Mijn bladwijzers',
    'All Records': 'Alle gegevens',
    'Predefined searches': 'Vooraf ingestelde zoekopdrachten',
    'Recent changes': 'Recente wijzigingen',
    'All (date order)': 'Alles (gesorteerd op datum)',
    'Edit saved search': 'Bewerk opgeslagen zoekopdracht',
    'Delete saved search': 'Verwijder opgeslagen zoekopdracht',
    'Manage Saved Searches': 'Beheer opgeslagen zoekopdrachten',
    'Create new saved search': 'Sla een nieuwe zoekopdracht op',

    'Delete? Please confirm': 'Verwijderen? Bevestig alstublieft',

    'select record': 'Selecteer gegevens',

    'Leave blank for no change': 'Laat leeg voor geen verandering',
    'Passwords are different': 'Wachtwoorden zijn verschillend',
    'Wrong email format': 'Verkeerde email indeling',
    'Wrong user name format': 'Verkeerde gebruikersnaam indeling',
    'Wrong password format': 'Verkeerde wachtwoord indeling',
    'Missing required fields': 'Verplichte velden zijn niet ingevuld',
    'length must be between': 'lengte moet tussen',
    'required field': 'verplicht veld',
    
};


