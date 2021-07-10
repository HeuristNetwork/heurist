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

var regional = {};
regional['en'] = {
    language: 'English',
    Databases: 'Databases',
    
    add_new_record: 'New', //in main menu - header
    add_new_record2: 'New', //in main menu - prefix add new record

    //common words
    'Close': '',

    
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
        menu_database_register_hint:'Register this database with the Heurist Master Index - this makes the structure (but not data) available for import by other databases',
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
        menu_profile_preferences: 'My Preferences',
        menu_structure_verify: 'Verify integrity',
        menu_structure_duplicates: 'Find duplicate records',
        menu_manage_rectitles: 'Rebuild record titles',
        menu_profile_files_hint: 'Manage uploaded files and registered remote media resources',
        menu_profile_preferences_hint: 'Display preferences relating to behaviours related to this database',
        menu_structure_verify_hint: 'Find errors in database structure (invalid record type, field and term codes) and records with incorrect structure or inconsistent values (invalid pointer, missing data etc)',
        menu_structure_duplicates_hint: 'Fuzzy search to identify records which might contain duplicate data',
        menu_manage_rectitles_hint: 'Rebuilds the constructed record titles listed in search results, for all records',
    'Server Manager': '',
        menu_admin_server: 'Manage databases',
        menu_admin_server_hint: 'Manage databases',

    //design menu
    'Modify': '',
        menu_structure_rectypes: 'Record Types',
        menu_structure_vocabterms: 'Vocabularies',
        menu_structure_fieldtypes: 'Base Fields',
        menu_structure_import: 'Browse templates',
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
        
    'Export': '',
        menu_export_csv: 'CSV',
        menu_export_hml_resultset: 'XML',
        menu_export_json: 'JSON',
        menu_export_geojson: 'GeoJSON',
        menu_export_kml: 'KML',
        menu_export_gephi: 'GEPHI',
        menu_export_csv_hint: 'Export records as delimited text (comma/tab), applying record type',
        menu_export_hml_resultset_hint: 'Generate HML (Heurist XML format) for current set of search results (current query + expansion)',
        menu_export_json_hint: 'Generate JSON for current set of search results (current query)',
        menu_export_geojson_hint: 'Generate GeoJSON_T for current set of search results (current query)',
        menu_export_kml_hint: 'Generate KML for current set of search results (current query + expansion)',
        menu_export_gephi_hint: 'Generate GEPHI for current set of search results (current query + expansion)',
        
    'Safeguard':'',



    
    'Design database': 'Design database',
    'Import data': 'Import data',
    'Please log in':'Please Log in or Register to use all features of Heurist.',
    'Session expired': 'It appears you are not logged in or your session has expired. Please reload the page to log in again',
    'Please contact to register':'Please contact database owner to register and use all features of Heurist.',
    'My Searches':'My Filters',
    'My Bookmarks':'My Bookmarks',

    'Error_Title': 'Heurist',
    'Error_Empty_Message':'No error message was supplied, please report to Heurist developers.',

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


regional['de'] = {
    language: 'Deutsch',
    'Databases': 'Datenbanken',
    'Design database': 'Constructdatenbanke',
    'Import_data': 'Data importieren',
    'Help': 'Hilfe',
    'About': 'Ueber'
};

regional['fr'] = {
    language: 'French',
    'Databases': 'Bases de données',
    'Design database': 'Structurer la base',
    'Import data': 'Importer données',
    'Files in situ': 'Fichiers in situ',
    'Report bug': 'Signaler un bug',
    'Help': 'Aide',
    'About': 'Au sujet de ...',
    'Confirmation':'Confirmation',
    'Info':'Informations',

    'Register': 'S\'inscrire',
    'Registration': 'Inscription',
    'Login': 'Login',
    'Please log in': 'Veuillez log in',
    'Please contact to register':'Veuillez nous contacter pour s\'inscrire',
    'Log out' : 'Logout',
    'Cancel': 'Annuler',
    'Save': 'Sauvegarder',
    'Close': 'Fermer',
    'Assign': 'Assigner',
    'Add': 'Ajouter',
    'Delete': 'Supprimer',
    'Create': 'Créer',
    'Manage': 'Gérer',
    'Sort': 'Trier',
    'Search': 'Réchercher',
    'Search result': 'Resultats de récherche',
    'Merge': 'Fusionner',
    'Rate': 'Evaluer',
    'Relate to': 'Relier avec',
    'Access': 'Acces',
    'Notify': 'Notification',
    'Embed / link': 'Intégrer',
    'Export': 'Exporter',
    'Import': 'Importer',


    'Username': 'Nom d\'utilisateur',
    'Password': 'Mot de passe',
    'Remember me': 'Se souvenir de moi',
    'Forgotten your password?': 'Mot de passe oublié?',
    'Click here to reset it': 'Cliquer ici pour le changer',
    'Enter your username OR email address below and a new password will be emailed to you.':'Entrer votre nom d\'utilisateur ci-dessous et un nouveau mot de passe sera envoyé par mel',
    'Reset password': 'Changer mot de passe',
    'Email to':'Envoyer mel à',
    'User information has been saved successfully':'Informations sur l\'utilisateur ont étés sauvegardées',

    'Database': 'Base de données',
    'Select database': 'Selectioner un base de données',
    'My User Info' : 'Mes informations d\'utilisateur',
    'My Groups' : 'Мes groupes de travail',
    'Manage Tags' : 'Gérer marquers',
    'Tags' : 'Marquers',
    'Manage Files': 'Gérer fichiers',
    'Manage Reminders' : 'Gérer notifications',
    'Set Ratings' : 'Changer évaluations',
    'No Rating': 'Sans évaluation',

    'Name': 'Nom',
    'Description': 'Déscriptif',
    'Size': 'Taille',
    'Type': 'Type',

    'search': 'Rechercher',
    'all records': 'Toutes les enregistrements',
    'my bookmarks': 'Mes marques-pages',
    'in current': 'actuelles',
    'search option': 'Option de récherche',
    'search assistant': 'assistant de récherche',
    'Any record type': 'N\'importe quel type d\'enregistrement ',
    'Any field type': 'N\'importe quel type de champs',
    'Record type': 'Type d\'enregistrement',
    'Field': 'Champs',
    'Contains': 'Contient',
    'Is': 'Est',
    'Sort By': 'Trier par',
    'Sort Ascending': 'Trier ordre croissant',
    'fields':'champs',

    'add': 'ajouter',
    'Add New Record' : 'Ajouter Nouvelle Enregistrement',
    'tags': 'marquers',
    'share': 'partager',
    'more': 'davantage',
    'edit': 'éditer',
    'options': 'options',

    'by name': 'par nom',
    'by usage': 'par fréquence d\'utilisation',
    'by date': 'par date',
    'by size': 'par taille',
    'by type': 'par type d\'enregistrement',
    'marked': 'marqué',

    'list':'liste',
    'icons':'icones',
    'thumbs':'onglets',
    'thumbs3':'onglets2',

    'first':'premier',
    'previous':'précédent',
    'next':'prochaine',
    'last':'dernier',
    'records per request':'enregistrements par requête',

    'Manage tags': 'Gérer marquers',
    'Create new tag': 'Créer nouvelle marquer',
    'Add Tag': 'Ajouter marquer',
    'Edit Tag': 'Editer marquer',
    'Define new tag that replaces old ones': 'Définir nouvelle marquer qui remplace l\'ancienne',
    'Length of tag must be more than 2.': 'Longueur de marquer doit être au dela de deux charactères',
    'Assign selected tags': 'Assigner marquers selectionées',
    'Personal Tags':'Marquers personnelles',
    'Delete selected tags': 'Supprimer marquers selectionées',

    // * TO DO *  complete translation into French

    'Manage files': 'Управление файлами',
    'Upload/register new file': 'Загрузить или зарегистрировать файл',
    'Add File': 'Добавить файл',
    'Edit File': 'Редактировать файл',
    'Personal Files':'Личные файлы',
    'Delete selected files': 'Удалить отмеченные файлы',

    'Profile': 'Профиль',
    'Admin': 'Админ',

    'Preferences' : 'Настройки',
    'Layout configuration file': 'Файл конфигурации интерфейса',
    'Language':'Язык',
    'Theme':'Тема/стили',
    'Open edit in new window':'Редактировать в новом окне',
    'When exiting record edit, prompt for tags if no tags have been set':'Перед сохранением предлагать определить ярлыки',
    'Reload page to apply new settings?':'Перезагрузить страницу чтобы увидеть изменения?',

    'Click to save current search':'Сохранить поиск как ссылку',
    'My Bookmarks': 'Мои закладки',
    'My Searches': 'Мои запросы',
    'Predefined searches': 'Рекомендуемые запросы',
    'Recent changes': 'Свежие записи',
    'All (date order)': 'Все (сорт по дате)',
    'Edit saved search': 'Редактировать поиск',
    'Delete saved search': 'Удалить сохраненный поиск',
    'Manage Saved Searches': 'Управление сохр.поисками',
    'Create new saved search': 'Создать поиск',

    'Database Summary': 'Сводка базы данных',
    'RuleSets': 'Зависимости',


    'Delete? Please confirm': 'Удалить? Подвтердите',

    'select record': 'выберите запись',

    'Leave blank for no change': 'Оставьте пустым чтобы не менять пароль',
    'Passwords are different': 'Пароли различны',
    'Wrong email format': 'Неправильный формат адреса почты',
    'Wrong user name format': 'Недопустимы формат для имени пользовтеля',
    'Wrong password format': 'Недопустимый формат для пароля',
    'Missing required fields': 'Не опрделены обязательные поля',
    'length must be between': 'длина должна быть между',
    'required field': 'обязательное поле'
};

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
    'Login': 'Login',
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

regional['ru'] = {
    language: 'Русский',
    'Databases': 'Базы данных',
    'Design database': 'Настройка БД',
    'Import data': 'Импорт данных',
    'Files in situ': 'Из директории на сервере',
    'Report bug': 'Отчет об ошибке',
    'Help': 'Помощь',
    'About': 'О системе...',
    'Confirmation':'Подтверждение',
    'Info':'Информация',

    'Register': 'Регистрация',
    'Registration': 'Регистрация',
    'Login': 'Войти',
    'Please log in': 'Зарегистрируйтесь, чтобы использовать все возможности Heurist',
    'Please contact to register':'Для получения регистрации свяжитесь с администратором',
    'Log out' : 'Выйти',
    'Cancel': 'Отмена',
    'Save': 'Сохранить',
    'Close': 'Закрыть',
    'Assign': 'Присвоить',
    'Add': 'Добавить',
    'Delete': 'Удалить',
    'Create': 'Создать',
    'Manage': 'Управление',
    'Sort': 'Порядок',
    'Search': 'Поиск',
    'Search result': 'Результаты поиска',
    'Merge': 'Объеденить',
    'Rate': 'Оценить',
    'Relate to': 'Связать с',
    'Access': 'Доступ',
    'Notify': 'Сообщить',
    'Embed / link': 'Код ссылки',
    'Export': 'Экспорт',
    'Import': 'Импорт',


    'Username': 'Имя пользователя',
    'Password': 'Пароль',
    'Remember me': 'Запомнить меня',
    'Forgotten your password?': 'Забыли пароль?',
    'Click here to reset it': 'Нажмите тут чтобы восстановить',
    'Enter your username OR email address below and a new password will be emailed to you.':'Введите имя пользователя или адрес эл.почты и новый пароль будет послан Вам',
    'Reset password': 'Восстановить пароль',
    'Email to':'Контакт',
    'User information has been saved successfully':'Информация о пользователе сохранена',

    'Database': 'База Данных',
    'Select database': 'Выберите базу данных',
    'My User Info' : 'Мои данные',
    'My Groups' : 'Мои группы',
    'Manage Tags' : 'Ярлыки',
    'Tags' : 'Ярлыки',
    'Manage Files': 'Файлы',
    'Manage Reminders' : 'Напоминания',
    'Set Ratings' : 'Присвоить оценку',
    'No Rating': 'Нет оценки',

    'Name': 'Название',
    'Description': 'Описание',
    'Size': 'Размер',
    'Type': 'Тип',

    'search': 'искать',
    'all records': 'среди всех записей',
    'my bookmarks': 'среди закладок',
    'in current': 'в текущем',
    'search option': 'варианты поиска',
    'search assistant': 'помощник',
    'Any record type': 'Любой тип записи',
    'Any field type': 'Любое тип поля',
    'Record type': 'Тип записи',
    'Field': 'Поле',
    'Contains': 'Содержит',
    'Is': 'Значение',
    'Sort By': 'Сорт',
    'Sort Ascending': 'По убыванию',
    'fields':'поля',

    'add': 'добавить',
    'Add New Record' : 'Добавить запись',
    'tags': 'ярлыки',
    'share': 'доступ',
    'more': 'ещё',
    'edit': 'править',
    'options': 'опции',

    'by name': 'по имени',
    'by usage': 'по использованию',
    'by date': 'по дате',
    'by size': 'по размеру',
    'by type': 'по типу',
    'marked': 'отмеченные',

    'list':'список',
    'icons':'иконки',
    'thumbs':'миниатюры',
    'thumbs3':'превью',

    'first':'первая',
    'previous':'предыдущая',
    'next':'следующая',
    'last':'последняя',
    'records per request':'записей в запросе',

    'Manage tags': 'Управление ярлыками',
    'Create new tag': 'Создать ярлык',
    'Add Tag': 'Создать ярлык',
    'Edit Tag': 'Редактировать ярлык',
    'Define new tag that replaces old ones': 'Определите новый ярлык который заменит старые',
    'Length of tag must be more than 2.': 'Ярлык должен быть больше чем два символа',
    'Assign selected tags': 'Присвоить отмеченные ярлыки',
    'Personal Tags':'Личные ярлыки',
    'Delete selected tags': 'Удалить отмеченные ярлыки',



    'Preferences' : 'Настройки',
    'Layout configuration file': 'Файл конфигурации интерфейса',
    'Language':'Язык',
    'Theme':'Тема/стили',
    'Open edit in new window':'Редактировать в новом окне',
    'When exiting record edit, prompt for tags if no tags have been set':'Перед сохранением предлагать определить ярлыки',
    'Reload page to apply new settings?':'Перезагрузить страницу чтобы увидеть изменения?',

    'Click to save current search':'Сохранить поиск как ссылку',
    'My Bookmarks': 'Мои закладки',
    'My Searches': 'Мои запросы',
    'Predefined searches': 'Рекомендуемые запросы',
    'Recent changes': 'Свежие записи',
    'All records': 'Все записи',
    'All (date order)': 'Все (сорт по дате)',
    'Edit saved search': 'Редактировать поиск',
    'Delete saved search': 'Удалить сохраненный поиск',
    'Manage Saved Searches': 'Управление сохр.поисками',
    'Create new saved search': 'Создать поиск',

    'Database Summary': 'Сводка базы данных',
    'RuleSets': 'Зависимости',


    'Delete? Please confirm': 'Удалить? Подвтердите',

    'select record': 'выберите запись',

    'Leave blank for no change': 'Оставьте пустым чтобы не менять пароль',
    'Passwords are different': 'Пароли различны',
    'Wrong email format': 'Неправильный формат адреса почты',
    'Wrong user name format': 'Недопустимы формат для имени пользовтеля',
    'Wrong password format': 'Недопустимый формат для пароля',
    'Missing required fields': 'Не опрделены обязательные поля',
    'length must be between': 'длина должна быть между',
    'required field': 'обязательное поле',
    
    //main menu ========================  2021-07-08
    'Admin': 'Админ',
    'Design': 'Дизайн',
    'Populate': 'Наполнение',
    'Explore': 'Искать',
    'Publish': 'Публикация',
    'Profile': 'Профиль',
    
    add_new_record: 'Новая запись', //header for New record
    add_new_record2: 'Ввод', // prefix before rectype name
    'Add record': 'Добавить запись',
    
    //explore menu
    'Filters': 'Фильтры',
    'Recent': 'Недавние',
    'All': 'Все',
    'Entities': 'Типы записей',
    'Saved filters': 'Каталог фильтров',
    'Build': 'Создать',
    'Filter builder': 'Условия поиска',
    'Facets builder': 'Многогранный поиск',
    'Save filter': 'Добавить фильтр',
    'Advanced': 'Дополнительно',
    'Rules': 'Каскадный поиск',
    
    //admin menu
    'Database': 'База данных',
        'Open': 'Открыть',
        'New': 'Новая',
        'Clone': 'Клонировать',
        'Delete': 'Удалить',
        'Clear': 'Очистить',
        'Rollback': 'Восстановить',
        'Register': 'Зарегестрировать',
        'Properties': 'Свойства',
        hint_db_open:'Найти и открыть другую базу Heurist в новом окне',
        hint_db_new:'Созадть новую базу данных - все основные элементы структуры будут добавлены автоматически',
        hint_db_clone:'Создать копию текущей базы со всеми данными, загружеными файлами, шаблонами итп',
        hint_db_register:'Зарегистировать эту базу в Heurist Master Index - это сделает доступным импорт элементов структуры (но не данных) в других базах',
        hint_db_properties:'Редактировать описание базы данных и определить некторые глобальные установить . Recommended to provide a self-documenting database',
        hint_db_delete:'Delete the current database completely - cannot be undone, although data is copied to a backup which could be reloaded by a system administrator',
        hint_db_clear:'Clear all data (records, values, attached files) from the current database. Database structure - record types, fields, terms, constraints - is unaffected',
        hint_db_rollback:'Selectively roll back the data in the database to a specific date and time',
        
        
    'Manage Users': 'Пользователи',
        'Workgroups': 'Группы',
        'Users': 'Пользователи',
        'Import user': 'Импорт',
    'Utilities': 'Утилиты',
        'Verify integrity': 'Проверка данных',
        'Manage files': 'Управление файлами',
            'Upload/register new file': 'Загрузить или зарегистрировать файл',
            'Add File': 'Добавить файл',
            'Edit File': 'Редактировать файл',
            'Personal Files':'Личные файлы',
            'Delete selected files': 'Удалить отмеченные файлы',
        'Rebuild record titles': 'Обновить заголовки',
        'Find duplicate records': 'Найти повторения',
    'Server Manager': 'Управление сервером',
        'Manage databases': 'Базы данных',
        '': '',
        '': '',
        '': '',
        '': '',
    '': '',
    '': '',
    '': '',
    
    
};


