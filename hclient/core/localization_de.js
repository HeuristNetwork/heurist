/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Michael Falk <michaelgfalk@gmail.com>
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

// German localisation
regional['de'] = {
    language: 'Deutsch',
    
    //common words
    'Close': 'Schließen',
    'Settings': 'Einstellungen',
    'Help': 'Hilfe',
    'Error': 'Error',
    'records': 'Datensätze',
    'loading': 'wird geladen',
    'preparing': 'wird vorbereitet',
    'explanations': 'Erklärungen',
    'Show data': 'Daten Anzeigen',
    'Actions': 'Aktionen',
    'Embed': 'Einbetten',
    'Clear': 'Abräumen',
    'Warning': 'Warnung',
    'Cancel': 'Abbrechen',
    'Record Info': 'Datensatzinfo',
    'Add': 'Hinzufügen',
    'Assign': 'Zuordnen',
    'Apply': 'Anwenden',
    'Abort': 'Abbrechen',
    'Define': 'Definieren',
    'Edit': 'Bearbeiten',
    'Delete': 'Löschen',
    'Create': 'Erstellen',
    'Confirm': 'Bestätigung',
    'Proceed': 'Vorgehen',
    'Continue': 'Fortfahren',
    'Go': 'Gehen',
    'Yes': 'Ja',
    'OK': 'Okay',
    'No': 'Nein',
    'Save': 'Speichern',
    'Save data': 'Die Daten Speichern',
    'Ignore and close': 'Änderungen ignoriern und schließen',
    'Drop data changes': 'Ângerungen verwerfen',
    'Manage': 'Verwalten',
    'Select': 'Wählen',
    'Configure': 'Konfigurieren',
    
    'Show context help': 'Kontexthilfe anzeigen',
    'Heurist context help': 'Heurist-Kontexthilfe',
    'Sorry context help was not found': 'Entschuldigung, Kontexthilfe nich gefunden',
    
    //main menu 
    'Admin': 'Administration',
    'Design': 'Design',
    'Populate': 'Füllen',
    'Explore': 'Erkunden',
    'Publish': 'Veröffentlichen',
    'Profile': 'Benutzerprofil',
    
    add_new_record: 'Neu', //in main menu - header
    add_new_record2: 'Neu', //in main menu - prefix add new record
    
    //explore menu
    'Filters': 'Filter',
    'Recent': 'Kürzlich bearbeitet',
    'All': 'Alles',
    'Entities': 'Datensatztypen',
    'Saved filters': 'Gespeicherte Filter',
    'Build': 'Bauen',
    'Filter builder': 'Filterbauer',
    'Facets builder': 'Facettenbauer',
    'Save filter': 'Filter speichern',
    'Advanced': 'Erweiterte',
    'Rules': 'Regeln',
    'Set as subset': 'Als Teilmenge setzen',
    
    //admin menu
    'Database': 'Datenbank',
        menu_database_browse: 'Öffnen',
        menu_database_create: 'Neu',
        menu_database_clone: 'Klonen',
        menu_database_delete: 'Löschen',
        menu_database_clear: 'Abräumen',
        menu_database_rollback: 'Zurücksetzen',
        menu_database_register: 'Registrieren',
        menu_database_properties: 'Eigenschaften',
        
        menu_database_browse_hint:'Öffnen noch eine Heurist-Datenbank und einloggen. Die gegenwärtige Datenbank bleibt offen.',
        menu_database_create_hint: 'Eine neue Datenbank am gegenwärtigen Server erstellen. Wesentliche Strukturkomponenten wird automatisch enthalten.',
        menu_database_clone_hint:'Klonen die gegenwärtigen Datenbank in eine neue Datenbank, mit alle Daten, Datein, Strukturkomponenten, etc.',
        menu_database_delete_hint:'Die aktuelle Datenbank löschen, zusammen mit allen Datensätze und Dateien. Die Aktion kann nicht normalerweise zurückgesetzt werden, obwohl ein Sicherung erstellt werden kann.',
        menu_database_clear_hint:'Alle Daten und Datein aus der aktuellen Datenbank löschen, aber die Strukturkomponenten erhalten.',
        menu_database_rollback_hint:'Setzen die Daten in der Datenbank zum gewissen Datum und Uhrzeit zurück.',
        menu_database_register_hint:'Die Datenbank im Heurist Master Index registrieren. Diese Registierung beteilt die Struktur Ihre Datenbank mit anderen Benutzern, und erlaubt Sie die Datenbank zu Klonen oder als XML/RDF zu exportieren.', 
        menu_database_properties_hint:'Die Metadaten Ihre Datenbank bearbeiten, und einige globalen Verhalten des Systems einstellen. Diese Aktion wird empfehlt um Ihre Datenbank selbstdokumentierend zu machen.',
    
    'Manage Users': 'Benutzer Verwalten',
        menu_profile_groups: 'Arbeitsgruppen',
        menu_profile_users: 'Benutzer',
        menu_profile_import: 'Benutzer importieren',
        menu_profile_groups_hint: 'Liste die Arbeitsgruppen der Datenbank',
        menu_profile_users_hint: 'Füge neue Benutzer die Datenbank hinzu, und vorhandene Benutzer beartbeiten.',
        menu_profile_import_hint: 'Benutzer von anderen Heurist-Datenbanken importierien, einschließlich ihre Nutzername, Passwörter usw. Importierte Benutzer werden nich automatisch zur Arbeitsgruppen zugewiesen.',
    'Utilities': 'Dienstprogramme',
        menu_profile_files: 'Datein verwalten',
        menu_profile_preferences: 'Meine Vorlieben',
        menu_structure_verify: 'Integrität Überprüfen',
        menu_structure_duplicates: 'Doppelte Datensäzte suchen',
        menu_manage_rectitles: 'Titeln der Datensätze wieder aufbauen',
        menu_profile_files_hint: 'Hochgeladene Datein und externe Medienquellen verwalten.',
        menu_profile_preferences_hint: 'Vorlieben dieser Datenbank einstellen.',
        menu_structure_verify_hint: 'Errors in der Datenbankstructur und in der Strukur individuelle Datensätze (z.B. schlechte formatierte Daten) suchen.',
        menu_structure_duplicates_hint: 'Ungeneue Suche für Datensätze die doppelte Daten enthalten könnten.',
        menu_manage_rectitles_hint: 'Die konstruierte Titeln der Datensätze wieder aufbauen.',
    'Server Manager': 'Server-Administrator*in',
        menu_admin_server: 'Datenbanken verwalten',
        menu_admin_server_hint: 'Datenbanken verwalten (Administrator*in Passwort nötig)',

    //design menu
    'Modify': 'Ändern',
        menu_structure_rectypes: 'Datensätztypen',
        menu_structure_vocabterms: 'Kontrollierter Wortschätze',
        menu_structure_fieldtypes: 'Base Fields',
        menu_structure_import: 'Browse templates',
        menu_lookup_config: 'External lookups',
        menu_structure_summary: 'Visualise',
        menu_structure_refresh: 'Refresh memory',
    
        menu_structure_rectypes_hint: 'Add new and modify existing record types',
        menu_structure_vocabterms_hint: 'Add new and modify Vocabularies &amp; Terms',
        menu_structure_fieldtypes_hint: 'Browse and edit the base field definitions referenced by record types (often shared by multiple record types)',
        menu_structure_import_hint: 'Selectively import record types, fields, terms and connected record types from other Heurist databases',
        menu_structure_summary_hint: 'Visualise the internal connections between record types in the database and add connections (record pointers and relationshi markers) between them',
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
        menu_cms_edit: 'Edit (legacy)',
        menu_cms_edit_new: 'Edit (new)',
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
        
    'Standalone page': '',
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
    'Shared': '',
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

};