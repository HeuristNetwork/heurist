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
        menu_structure_duplicates_hint: 'Ungeneue Suche für Datensätze, die doppelte Daten enthalten könnten.',
        menu_manage_rectitles_hint: 'Die konstruierte Titeln der Datensätze wieder aufbauen.',
    'Server Manager': 'Server-Administrator*in',
        menu_admin_server: 'Datenbanken verwalten',
        menu_admin_server_hint: 'Datenbanken verwalten (Administrator*in Passwort nötig)',

    //design menu
    'Modify': 'Ändern',
        menu_structure_rectypes: 'Datensatztypen',
        menu_structure_vocabterms: 'Kontrollierter Wortschätze',
        menu_structure_fieldtypes: 'Grundfelder',
        menu_structure_import: 'Schablone stöbern',
        menu_lookup_config: 'Externe Lookups',
        menu_structure_summary: 'Visualisieren',
        menu_structure_refresh: 'Speicher auffrischen',
    
        menu_structure_rectypes_hint: 'Neue Datensatztypen hinzufügen oder aktuelle Datensatztypen ändern.',
        menu_structure_vocabterms_hint: 'Neue kontrollierte Wortschätze hinzufügen oder aktuelle Wortschätze ändern.',
        menu_structure_fieldtypes_hint: 'Grundfelderdefinitionen stöbern, hinzufügen oder ändern. Grundfelder (z.B. \'Name / Title\' oder \'Start Date\' sind häuflich von vielend Datensatztypen gemeinsam genutzt).',
        menu_structure_import_hint: 'Datensatztypen, Grundfelder und Wortschätze von anderen Heurist-Datenbanken importieren.',
        menu_structure_summary_hint: 'Beziehungen zwischen Datensatztypen visualisieren, um Struktur der Datenbank tiefer zu verstehen.',
        menu_structure_refresh_hint: 'Speicher des Heurists abräumen und auffrischen. Dieses könnte Ihnen hilfen wenn neuliche Änderungen an der Datenbankstruktur nicht angezeigt werden.',

    'Setup': 'Setup',
        menu_manage_dashboards: 'Verknüpfungsleiste',
        menu_manage_archive: 'Archivpaket',
        menu_manage_dashboards_hint: 'Eine benutzerdefinierte Liste mit Verknüpfungen oben auf dem Bildschirm erstellen.',
        menu_manage_archive_hint: 'Eine vollständige Sicherung Ihrer Datenbanks in einer einzigen ZIP-Datei herunterladen, das enthält hochgeladete Datei, alle Datensätze und die Struktur der Datenbank in SQL- und XML-Formaten.',
    
    'Download': 'Herunterladen',
        menu_manage_structure_asxml: 'Struktur (XML)',
        menu_manage_structure_assql: 'Struktur (Text)',
        menu_manage_structure_asxml_hint: 'Eine Darstellung der Datenbankstruktur im XML-Format herunterladen.',
        menu_manage_structure_assql_hint: 'Eine Darstellung der Datenbankstruktur in einem maschinenlesbar Textformat herunterladen (veraltet 2014).',

    //populate menu
    'Upload Files': 'Datei hochladen',
        menu_import_csv: 'Delimited text / CSV',
        menu_import_zotero: 'Zotero-Bibliothek synchronisieren',
        menu_import_xml: 'Heurist XML/JSon',
        menu_import_kml: 'KML',
        menu_import_get_template: 'Schablone herunterladen (XML)',
        menu_import_csv_hint: 'Daten aus einer Kalkulationstabelle im Textformat importieren. Datensätze werden mit bestehenden Datensätzen abgeblichen, und entsprechend hinzugefügt oder aktualisiert.',
        menu_import_zotero_hint: 'Mit einer Zotero-Bibliothek synchronisieren. Datensätze in der Zotero-Bibliothek werden mit Datensätzen in der Datenbank abgeglichen, und entsprechend hinzugefügt oder aktualisiert.',
        menu_import_xml_hint: 'Datensätze von einer andere Heurist-Datenbank importieren (im Heurist-XML-Format (HML)).',
        menu_import_kml_hint: 'Geographische Daten im KML-Format importieren (Daten in WKT-Format mithilfe des CSV-Importers importiert werden können.)',
        menu_import_get_template_hint: 'XML-Schablone der Datenbank herunterladen, um XML/HML-Importe vorzubereiten.',
    'Media Files': 'Mediadatein',
        menu_files_upload: 'Mediadatein hochladen',
        menu_files_index: 'Mediadatein indizieren',
        menu_files_upload_hint: 'Mediadatein massenweise hochladen, oder zuvor hochgeladene Datein aktuellisiern und verwalten.',
        menu_files_index_hint: 'Für jede neue hochgeladene Mediadatei einen \'Digital Media File\'-Datensatz in der Datenbank generieren.',
        
    //publish menu
    'Website': 'Website',
        menu_cms_create: 'Erstellen',
        menu_cms_edit: 'Bearbeiten (Legacy-Schnittstelle)',
        menu_cms_edit_new: 'Bearbeiten (Neue-Schnittstelle)',
        menu_cms_view: 'Ansehen',
        menu_cms_embed: 'Eigenständige Webseite',
        menu_cms_create_hint: 'Eine neue CMS-Website erstellen. Die Website werden Daten von der Datenbank mithilfe Heurist-Widgets zugreifen können.',
        menu_cms_edit_hint: 'Eine bestehende Website in der Datenbank bearbeiten.',
        menu_cms_view_hint: 'Eine bestehende Website in der Datenbank ansehen.',
        menu_cms_embed_hint: 'Eine eigenständige Webseite erstellen oder bearbeiten. Eigenständige Webseiten können einfach in anderen Websiten (z.B. Wordpress-Siten) eingebettet werden.',
        
        'Website Editor': 'Websiteeditor',
        'Sitewide Settings': 'Einstellungen für die gesamte Website',
        'Current Page': 'Aktuelle Seite',
        'Page Editor': 'Seiteeditor',
        
    'Standalone page': 'Eigenständige Webseite',
        menu_cms_create_page: 'Erstellen',
        menu_cms_edit_page: 'Bearbeiten',
        menu_cms_view_page: 'Ansehen',
        
    'Export': 'Export',
        menu_export_csv: 'CSV',
        menu_export_hml_resultset: 'XML',
        menu_export_json: 'JSON',
        menu_export_geojson: 'GeoJSON',
        menu_export_kml: 'KML',
        menu_export_gephi: 'GEPHI',
        menu_export_iiif: 'IIIF',
        menu_export_csv_hint: 'Datensätze in CSV-Datei exportieren, um es z.B. in einem Tabellenkalkulationsprogramm zu öffnen.',
        menu_export_hml_resultset_hint: 'XML/HML-Datei für aktuelle Ergebnissatz generieren.',
        menu_export_json_hint: 'JSON-Datei für aktuelle Ergebnissatz generieren.',
        menu_export_geojson_hint: 'GeoJSON-Datei für aktuelle Ergebnissatz generieren.',
        menu_export_kml_hint: 'KML-Datei für aktuelle Ergebnissatz generieren.',
        menu_export_gephi_hint: 'gphx-Datei für aktuelle Ergebnissatz generieren, um die Ergebnisse im Gephi-Programm zu analysieren und visalisieren.',
        menu_export_iiif_hnt: 'IIIF-Manifest für aktuelle Ergebnissatz generieren.',
        
    'Safeguard':'Sicherung',

    //My profile menu
    menu_profile_tags: 'Tags verwalten',
    menu_profile_reminders: 'Erinnerungen verwalten',
    menu_profile_info: 'Meine Nutzerdaten',
    menu_profile_logout: 'Ausloggen',
    menu_profile_tags_hint: 'Personal- und Werkgruppe-Tags hinzufügen, ändern oder löschen.',
    menu_profile_reminders_hint: 'Benachrichtigungs-E-Mails für mit Lesezeichen versehenen Datensätzen einrichten oder deaktivieren.',
    menu_profile_info_hint: 'Nutzerdaten wie Nutzername, Passwort usw.',
    menu_profile_logout_hint: 'Ausloggen',
        
    //HELP menu
    HELP: 'Hilfe',
        menu_help_online: 'Online-Hilfesystem',
        menu_help_website: 'Website des Heuristnetzwerk',
        menu_help_online_hint: 'Benutzerhandbuch für die Heurist-Schnittstelle',
        menu_help_website_hint: 'Zentrale Webseite für den Heuristnetzwerk, die Informationen über Support-Services, Veranstaltungen, Projekte und Benutzer des Heuristsystems enthält.',
    CONTACT: 'Kontakt',
        menu_help_bugreport: 'Fehlerbericht/Featureanfrage',
        menu_help_emailteam: 'Heurist Team',
        menu_help_emailadmin: 'Systemadministrator',
        menu_help_acknowledgements: 'Bestätigungen',
        menu_help_about: 'About',
        menu_help_bugreport_hint: 'Das Heurist-Team über Fehler oder gewünschte Funktionen benachrichtigen',
        menu_help_emailteam_hint: 'An das Heurist-Team bei allgemeinen Fragen sich wenden.',
        menu_help_emailadmin_hint: 'Das Systemadministrator kontakten.',
        menu_help_acknowledgements_hint: '',
        menu_help_about_hint: 'Version, Credits, Copyright, Lizenz',
    
    menu_subset_set: 'Als Teilmenge Setzen',
    menu_subset_set_hint: 'Der aktuelle Ergebnissatz als Teilmenge setzen, um alle künftige Aktionen nur auf diese Teilmenge anzuwenden.',
    'Click to revert to whole database': 'Zurück zur gesamten Datenbank',
    'SUBSET ACTIVE': 'Teilmenge aktive',
    'Current subset': 'Aktuelle Teilmenge',
    
    //END main menu
    
    //resultList --------------------
    
    'Selected': 'Ausgewählt',
        menu_selected_select_all: 'Alles auswählen',
        menu_selected_select_all_hint: 'Alle Datensätze an der Seite auswählen.', 
        menu_selected_select_none: 'Keines auswählen',
        menu_selected_select_none_hint: 'Die Auswahl abräumen',
        menu_selected_select_show: 'Als Suchergenis Anzeigen',
        menu_selected_select_show_hint: 'Die aktuelle Auswahl als Suchergenis in einer neuer Browser-Tab anzeigen.',
        menu_selected_tag: 'Tag',
        menu_selected_tag_hint: 'Datensätze in der aktuelle Auswahl taggen.',
        menu_selected_rate: 'Bewerten',
        menu_selected_rate_hint: 'Datensätze in der aktuelle Auswahl bewerten.',
        menu_selected_bookmark: 'Bookmarken',
        menu_selected_bookmark_hint: 'Datensätze in der aktuelle Auswahl bookmarken.',
        menu_selected_unbookmark: 'Entbookmarken',
        menu_selected_unbookmark_hint: 'Datensätzen in der aktuelle Auswahl die Lesezeichen entfernen.',
        menu_selected_merge: 'Zusammenführen',
        menu_selected_merge_hint: 'Die ausgewählte Datensätze zusammenführen.',
        menu_selected_delete: 'Löschen',
        menu_selected_delete_hint: 'Die ausgewählte Datensätze löschen.',
    'Collected': 'Gesammelt',
    'Collect': 'Sammeln',
        menu_collected_add: 'Hinzufügen',
        menu_collected_add_hint: 'Die ausgewählte Datensätze der Sammlung hinzufügen.',
        menu_collected_del: 'Entfernen',
        menu_collected_del_hint: 'Die ausgewählte Datensätze aus der Sammlung entfernen.',
        menu_collected_clear: 'Alle Datensätze entfernen',
        menu_collected_clear_hint: 'Die Sammlung abräumen.',
        menu_collected_save: 'Speichern als Filter',
        menu_collected_save_hint: 'Die Sammlung as Gespeicherte Filter speichern, um es einfach in der Zukunft zu wiederzubauen.',
        menu_collected_show: 'Als Suchergebis anzeigen',
        menu_collected_show_hint: 'Alle Datensätze in der aktuelle Sammlung asl Suchergebnisse in einer neuer Browser-Tab anzeigen.',
            collection_limit: 'Zu viele Datensätze in der Sammlung. Die höchste erlebte Zahl der gesammelte Datensätze ist ',
            collection_select_hint:  'Bitte wählen Sie mindestens ein Datensatz aus, um ihn der Sammlung hinzufügen.',
            collection_select_hint2: 'Bitte wählen Sie mindestens ein Datensatz aus, um ihn aus der Sammlung zu entfernen.',
            collection_url_hint: 'Zu viele Datensätze in der Sammlung, um die als Suchergebnisse anzuzeigen. Bitte speichern Sie die Sammmlung stattdessen als Gespeicherte Filter.',
        
    'Recode': 'Bearbeiten',
        menu_selected_value_add: 'Wert zu einem Feld zuweisen',
        menu_selected_value_add_hint: 'Einen bestimmten Wert zu einem bestimmten Feld in mehreren Datensätzen zuweisen.',
        menu_selected_value_replace: 'Wert in einem Feld ersetzen',
        menu_selected_value_replace_hint: 'Den vorhandenen Wert eines bestimmtes Feldes in mehreren Datensätzen ersetzen.',
        menu_selected_value_delete: 'West aus einem Feld löschen',
        menu_selected_value_delete_hint: 'Die Daten aus einem bestimmten Feld in mehreren Datensätzen löschen.',
        menu_selected_add_link: 'Bezeihung schaffen',
        menu_selected_add_link_hint: 'Eine Bezeihung zwischen ausgewählte Datensätze schaffen',
        menu_selected_rectype_change: 'Datensatztyp tauschen',
        menu_selected_rectype_change_hint: 'Die Datensatztypen ausgewählte Datensätze tauschen (z.B. Place -> Building)',
        menu_selected_extract_pdf: 'Text aus pdf extrahieren',
        menu_selected_extract_pdf_hint: 'Text aus angehängte pdf-Datein extrahieren und als Textdaten in die entsprechenden Datensätze einfügen (experimentelle).',
    'Share': 'Teilen',
        menu_selected_notify: 'Benachrichtigen (E-Mail)',
        menu_selected_notify_hint: 'Eine*n Benutzer*in über einige ausgewählte Datensätze per E-Mail benachrichtigen.',
        menu_selected_email: 'E-Mail senden',
        menu_selected_email_hint: 'Einen E-Mail zu den Menschen senden, die im Datensätze erfasst sind.',
        menu_selected_ownership: 'Eigentum / Sichtbarkeit',
        menu_selected_ownership_hint: 'Die Eigentum oder Sichtbarkeit der ausgewählten Datensätze ändern.',
    'Reorder': 'Umordnen',
    menu_reorder_hint: 'Die Datensätze manuell neu anordnen, um als einen Ergebnissatz mit festen Reihenfolge zu speichern.',
    menu_reorder_title: 'Ziehen Sie die Datensätze nach oben und unten, um die neu anzuordnen.',
    menu_reorder_save: 'Neue Reihenfolge speichern',
    
    //
    resultList_select_record: 'Bitte wählen Sie mindestens einen Datensatz.',
    resultList_select_record2: 'Bitte wählen Sie mindestens zwei Datensätze, um die zusammenzuführen.',
    resultList_select_limit: 'Zu viele ausgewählte Datensätze. Die größtmögliche Anzahl von ausgewählte Datensätze ist ',
    resultList_noresult: 'Keine Ergebnisse. Bitte versuchen Sie es mit einem anderen Filter.',
    resultList_empty_remark: 'Keine Ergebnisse. Es könnte Datensätze geben, die für die Öffentlichkeit oder Ihr Benutzerprofil nicht sichtbar sind.',
    resultList_private_record: 'Dieser Datensatz ist nicht für die Öffentlichtkeit sichtbar. Die*er Benutzer*in soll eingeloggt sein, um den zu sehen.',
    'private - hidden from non-owners': 'Privat – nur für die*en Eigentümer*in sichtbar.',
    'visible to any logged-in user': 'für jede*n eigeloggte*n Benutzer*in sichtbar',
    'pending (viewable by anyone, changes pending)': 'anstehend (für irgendwen sichtbar, Änderungen anstehend)',
    'public (viewable by anyone)': 'für irgendwen sichtbar',
    'show selected only': 'nur die ausgewählte Datensätze anzeigen',
    'Single lines': 'Datensätze in einzelnen Seilen anzeigen.',
    'Single lines with icon': 'Datensätze in einzelnen Seilen mit Symbolen anzeigen.',
    'Small images': 'Datensätze als kleine Vorschaubilder anzeigen.',
    'Large image': 'Datensätze als große Vorschaubilder anzeigen.',
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