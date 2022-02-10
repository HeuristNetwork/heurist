/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Hagen Peukert   <hagen.peukert@uni-hamburg.de>
* @author      Michael Falk    <michaelgfalk@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
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
    'Error': 'Fehler',
    'records': 'Datensätze',
    'loading': 'wird geladen',
    'preparing': 'wird vorbereitet',
    'explanations': 'Erklärungen',
    'Show data': 'Daten anzeigen',
    'Actions': 'Aktionen',
    'Embed': 'Einbinden',
    'Clear': 'Leeren',
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
    'Confirm': 'Bestätigen',
    'Proceed': 'Fortfahren',
    'Continue': 'Weiter',
    'Go': 'Gehen',
    'Yes': 'Ja',
    'OK': 'Okay',
    'No': 'Nein',
    'Save': 'Speichern',
    'Save data': 'Daten speichern',
    'Ignore and close': 'Änderungen ignorieren und schließen',
    'Drop data changes': 'Änderungen verwerfen',
    'Manage': 'Verwalten',
    'Select': 'Wählen',
    'Configure': 'Konfigurieren',
    
    'Show context help': 'Kontexthilfe anzeigen',
    'Heurist context help': 'Heurist-Kontexthilfe',
    'Sorry context help was not found': 'Entschuldigung, Kontexthilfe wurde nicht gefunden',
    
    //main menu 
    'Admin': 'Administration',
    'Design': 'Design',
    'Populate': 'Daten eingeben',//also possible but  not commonly used Befüllen
    'Explore': 'Erkunden',
    'Publish': 'Veröffentlichen',
    'Profile': 'Benutzerprofil',
    
    add_new_record: 'Neu', //in main menu - header
    add_new_record2: 'Neu', //in main menu - prefix add new record
    
    //explore menu
    'Filters': 'Filter',
    'Recent': 'Kürzlich bearbeitet',
    'All': 'Alles',
    'Entities': 'Datentypen',
    'Saved filters': 'Gespeicherte Filter',
    'Build': 'Erstellen',
    'Filter builder': 'Filterbearbeitung',
    'Facets builder': 'Facettenbearbeitung',
    'Save filter': 'Filter speichern',
    'Advanced': 'Erweitert',
    'Rules': 'Regeln',
    'Set as subset': 'Als Teilmenge definieren',
    
    //admin menu
    'Database': 'Datenbank',
        menu_database_browse: 'Öffnen',
        menu_database_create: 'Neu',
        menu_database_clone: 'Klonen',
        menu_database_delete: 'Löschen',
        menu_database_clear: 'Leeren',
        menu_database_rollback: 'Zurücksetzen',
        menu_database_register: 'Registrieren',
        menu_database_properties: 'Eigenschaften',
        
        menu_database_browse_hint:'Weitere Heurist-Datenbank öffnen und einloggen. Die gegenwärtige Datenbank bleibt offen.',
        menu_database_create_hint: 'Eine neue Datenbank auf dem aktuellem Server erstellen. Wesentliche Strukturkomponenten werden automatisch erstellt.',
        menu_database_clone_hint:'Die aktuelle Datenbank mit allen Daten, Dateien, Strukturkomponenten, etc. klonen',
        menu_database_delete_hint:'Die aktuelle Datenbank löschen, zusammen mit allen Datensätze und Dateien. Die Aktion kann nicht normalerweise zurückgesetzt werden, obwohl ein Sicherung erstellt werden kann.',
        menu_database_clear_hint:'Alle Daten und Dateien aus der aktuellen Datenbank löschen, aber die Strukturkomponenten erhalten.',
        menu_database_rollback_hint:'Setzt die Daten in der Datenbank auf ein bestimmtes Datum und eine bestimmte Uhrzeit zurück.',
        menu_database_register_hint:'Die Datenbank im Heurist Master Index registrieren. Mit dieser Registrierung wird die Struktur Ihrer Datenbank anderen Nutzern zur Verfügung gestellt. Anderen Nutzern wird erlaub, die Datenbank zu klonen oder als XML/RDF zu exportieren.', 
        menu_database_properties_hint:'Die Metadaten Ihrer Datenbank bearbeiten und das allgemeine Verhalten des Systems einstellen. Diese Aktion wird empfohlen, um Ihre Datenbank zu dokumentieren.',
    
    'Manage Users': 'Benutzer verwalten',
        menu_profile_groups: 'Arbeitsgruppen',
        menu_profile_users: 'Nutzer',
        menu_profile_import: 'Nutzer importieren',
        menu_profile_groups_hint: 'Liste der Arbeitsgruppen der Datenbank',
        menu_profile_users_hint: 'Füge neue Nutzer der Datenbank hinzu und bearbeite vorhandene Nutzer.',
        menu_profile_import_hint: 'Nutzer von anderen Heurist-Datenbanken importierien, einschließlich ihren Nutzernamen, Passwörter, usw. Importierte Nutzer werden nicht automatisch zu Arbeitsgruppen zugewiesen.',
    'Utilities': 'Dienstprogramme',
        menu_profile_files: 'Dateien verwalten',
        menu_profile_preferences: 'Einstellungen',
        menu_structure_verify: 'Integrität überprüfen',
        menu_structure_duplicates: 'Doppelte Datensäzte suchen',
        menu_manage_rectitles: 'Titel der Datensätze wieder zuweisen',
        menu_profile_files_hint: 'Hochgeladene Dateien und externe Medienquellen verwalten.',
        menu_profile_preferences_hint: 'Einstellungen dieser Datenbank ändern.',
        menu_structure_verify_hint: 'Fehler in der Datenbankstruktur und individuellen Datensätzen (z.B. falsch formatierte Daten) suchen.',
        menu_structure_duplicates_hint: 'Ungenaue Suche für Datensätze mit Dopplungen.',
        menu_manage_rectitles_hint: 'Die rekonstruierten Titel der Datensätze wieder zuweisen.',
    'Server Manager': 'Server-Administrator*in',
        menu_admin_server: 'Datenbanken verwalten',
        menu_admin_server_hint: 'Datenbanken verwalten (Administratorpasswort notwendig)',

    //design menu
    'Modify': 'Ändern',
        menu_structure_rectypes: 'Datentypen',
        menu_structure_vocabterms: 'Vordefinierte Vokabulare',
        menu_structure_fieldtypes: 'Datenfeld', //double check Grundfelder
        menu_structure_import: 'Templates durchsuchen',
        menu_lookup_config: 'Externe Lookups',
        menu_structure_summary: 'Visualisieren',
        menu_structure_refresh: 'Neuladen',
    
        menu_structure_rectypes_hint: 'Neue Datensatztypen hinzufügen oder aktuelle Datensatztypen ändern.',
        menu_structure_vocabterms_hint: 'Neues Vokabular hinzufügen oder aktuelles Vokabular ändern.',
        menu_structure_fieldtypes_hint: 'Datenfelddefinitionen durchsuchen, hinzufügen oder ändern. Datenfelder (z.B. \'Name / Title\' oder \'Start Date\') werden meist von vielen Datentypen gemeinsam genutzt.',
        menu_structure_import_hint: 'Datentypen, Datenfelder und Vokabulare aus anderen Heurist-Datenbanken importieren.',
        menu_structure_summary_hint: 'Beziehungen zwischen Datentypen visualisieren, um die Struktur der Datenbank besser zu verstehen.',
        menu_structure_refresh_hint: 'Speicher leeren und neuladen (wenn kürzlich gemachte Änderungen in der Datenbankstruktur nicht angezeigt werden).',

    'Setup': 'Setup',
        menu_manage_dashboards: 'Verknüpfungsleiste',
        menu_manage_archive: 'Archivpaket',
        menu_manage_dashboards_hint: 'Eine benutzerdefinierte Liste mit Verknüpfungen auf dem Bildschirm erstellen.',
        menu_manage_archive_hint: 'Eine vollständige Sicherung Ihrer Datenbank in einer einzigen ZIP-Datei herunterladen. Das Paket enthält alle hochgeladenen Dateien, alle Datensätze und die Struktur der Datenbank in SQL- und XML-Formaten.',
    
    'Download': 'Herunterladen',
        menu_manage_structure_asxml: 'Struktur (XML)',
        menu_manage_structure_assql: 'Struktur (Text)',
        menu_manage_structure_asxml_hint: 'Eine Darstellung der Datenbankstruktur im XML-Format herunterladen.',
        menu_manage_structure_assql_hint: 'Eine Darstellung der Datenbankstruktur in einem maschinenlesbaren Textformat herunterladen (veraltet 2014).',

    //populate menu
    'Upload Files': 'Datei hochladen',
        menu_import_csv: 'Delimited text / CSV',
        menu_import_zotero: 'Zotero-Bibliothek synchronisieren',
        menu_import_xml: 'Heurist XML/JSon',
        menu_import_kml: 'KML',
        menu_import_get_template: 'Template herunterladen (XML)',
        menu_import_csv_hint: 'Tabellierte Daten im Textformat importieren. Datensätze werden mit bestehenden Datensätzen abgeblichen und entsprechend hinzugefügt oder aktualisiert.',
        menu_import_zotero_hint: 'Mit einer Zotero-Bibliothek synchronisieren. Datensätze in der Zotero-Bibliothek werden mit Datensätzen in der Datenbank abgeglichen, und entsprechend hinzugefügt oder aktualisiert.',
        menu_import_xml_hint: 'Datensätze von einer andere Heurist-Datenbank importieren (im Heurist-XML-Format (HML)).',
        menu_import_kml_hint: 'Geographische Daten im KML-Format importieren (Daten in WKT-Format können mithilfe des CSV-Importers importiert werden.)',
        menu_import_get_template_hint: 'XML-Template der Datenbank herunterladen, um XML/HML-Importe vorzubereiten.',
    'Media Files': 'Mediadateien',
        menu_files_upload: 'Mediadateien hochladen',
        menu_files_index: 'Mediadateien indizieren',
        menu_files_upload_hint: 'Mediadateien im Ganzen hochladen (bulk upload), oder zuvor hochgeladene Dateien aktualisieren und verwalten.',
        menu_files_index_hint: 'Für jede neue hochgeladene Mediadatei einen \'Digital Media File\'-Datensatz in der Datenbank generieren.',
        
    //publish menu
    'Website': 'Website',
        menu_cms_create: 'Erstellen',
        menu_cms_edit: 'Bearbeiten (Legacy-Schnittstelle)',
        menu_cms_edit_new: 'Bearbeiten (Neue-Schnittstelle)',
        menu_cms_view: 'Ansehen',
        menu_cms_embed: 'Eigenständige Webseite',
        menu_cms_create_hint: 'Eine neue CMS-Website erstellen. Die Website wird auf Daten aus der Datenbank mithilfe des Heurist-Widgets zugreifen können.',
        menu_cms_edit_hint: 'Eine bestehende Website bearbeiten.',
        menu_cms_view_hint: 'Eine bestehende Website ansehen.',
        menu_cms_embed_hint: 'Eine eigenständige Webseite erstellen oder bearbeiten. Eigenständige Webseiten können einfach in anderen Websiten (z.B. Wordpress-Siten) eingebettet werden.',
        
        'Website Editor': 'Website-Editor',
        'Sitewide Settings': 'Einstellungen für die gesamte Website',
        'Current Page': 'Aktuelle Seite',
        'Page Editor': 'Seiten-Editor',
        
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
        menu_export_csv_hint: 'Datensätze in CSV-Datei exportieren, um sie z.B. in einem Tabellenkalkulationsprogramm zu öffnen.',
        menu_export_hml_resultset_hint: 'XML/HML-Datei für aktuelle Abfrage generieren.',
        menu_export_json_hint: 'JSON-Datei für aktuelle Abfrage generieren.',
        menu_export_geojson_hint: 'GeoJSON-Datei für aktuelle Abfrage generieren.',
        menu_export_kml_hint: 'KML-Datei für aktuelle Abfrage generieren.',
        menu_export_gephi_hint: 'gphx-Datei für aktuelle Abfrage generieren, um die Daten im Gephi-Programm zu analysieren und visalisieren.',
        menu_export_iiif_hnt: 'IIIF-Manifest für aktuelle Abfrage generieren.',
        
    'Safeguard':'Sicherung',

    //My profile menu
    menu_profile_tags: 'Tags verwalten',
    menu_profile_reminders: 'Erinnerungen verwalten',
    menu_profile_info: 'Meine Nutzerdaten',
    menu_profile_logout: 'Ausloggen',
    menu_profile_tags_hint: 'Persönliche oder Arbeitsgruppen-Tags hinzufügen, ändern oder löschen.',
    menu_profile_reminders_hint: 'Einrichten oder deaktivieren der Benachrichtigungs-E-Mails von Datensätzen, die mit Lesezeichen versehenen worden sind.',
    menu_profile_info_hint: 'Persönliche Nutzerdaten',
    menu_profile_logout_hint: 'Ausloggen',
        
    //HELP menu
    HELP: 'Hilfe',
        menu_help_online: 'Online-Hilfesystem',
        menu_help_website: 'Website des Heurist-Netzwerk',
        menu_help_online_hint: 'Benutzerhandbuch für die Heurist-Schnittstelle',
        menu_help_website_hint: 'Zentrale Webseite für das Heurist-Netzwerk, die Informationen über Support-Services, Veranstaltungen, Projekte und Benutzer des Heuristsystems enthält.',
    CONTACT: 'Kontakt',
        menu_help_bugreport: 'Fehlerbericht/Anfragen',
        menu_help_emailteam: 'Heurist Team',
        menu_help_emailadmin: 'Systemadministrator',
        menu_help_acknowledgements: 'Danksagung', //maybe Empfangsbestätigung is meant? I have to see it in Heurist to know which meaning it is
        menu_help_about: 'About',
        menu_help_bugreport_hint: 'Das Heurist-Team über Fehler oder gewünschte Funktionen benachrichtigen',
        menu_help_emailteam_hint: 'Bei allgemeinen Fragen sich an das Heurist-Team wenden.',
        menu_help_emailadmin_hint: 'Den Systemadministrator kontaktieren.',
        menu_help_acknowledgements_hint: '',
        menu_help_about_hint: 'Version, Credits, Copyright, Lizenz',
    
    menu_subset_set: 'Als Teilmenge definieren',
    menu_subset_set_hint: 'Die aktuelle Abfrage als Teilmenge definieren, um alle künftigen Aktionen nur auf diese Teilmenge anzuwenden.',
    'Click to revert to whole database': 'Zurück zur gesamten Datenbank',
    'SUBSET ACTIVE': 'Teilmenge aktiv',
    'Current subset': 'Aktuelle Teilmenge',
    
    //END main menu
    
    //resultList --------------------
    
    'Selected': 'Ausgewählt',
        menu_selected_select_all: 'Alles auswählen',
        menu_selected_select_all_hint: 'Alle Datensätze auf dieser Seite auswählen.', 
        menu_selected_select_none: 'Keinen Datensatz auswählen',
        menu_selected_select_none_hint: 'Die Auswahl verwerfen',
        menu_selected_select_show: 'Als Suchergebnis anzeigen',
        menu_selected_select_show_hint: 'Die aktuelle Auswahl als Suchergebnis in einem neuen Browser-Tab anzeigen.',
        menu_selected_tag: 'Tag',
        menu_selected_tag_hint: 'Datensätze in der aktuellen Auswahl taggen.',
        menu_selected_rate: 'Bewerten',
        menu_selected_rate_hint: 'Datensätze in der aktuellen Auswahl bewerten.',
        menu_selected_bookmark: 'Lesezeichen setzen',
        menu_selected_bookmark_hint: 'Datensätze in der aktuellen Auswahl bookmarken.',
        menu_selected_unbookmark: 'Lesezeichen entfernen',
        menu_selected_unbookmark_hint: 'Die Lesezeichen der Datensätze in der aktuellen Auswahl entfernen.',
        menu_selected_merge: 'Zusammenführen',
        menu_selected_merge_hint: 'Die ausgewählten Datensätze zusammenführen.',
        menu_selected_delete: 'Löschen',
        menu_selected_delete_hint: 'Die ausgewählten Datensätze löschen.',
    'Collected': 'Gesammelt',
    'Collect': 'Sammeln',
        menu_collected_add: 'Hinzufügen',
        menu_collected_add_hint: 'Die ausgewählten Datensätze der Sammlung hinzufügen.',
        menu_collected_del: 'Leeren',
        menu_collected_del_hint: 'Die ausgewählten Datensätze aus der Sammlung entfernen.',
        menu_collected_clear: 'Alle Datensätze leeren',
        menu_collected_clear_hint: 'Die Inhalte der Sammlung leeren.',
        menu_collected_save: 'Speichern unter',
        menu_collected_save_hint: 'Datensätze als Sammlung speichern, um sie zukünftig einfacher aufzurufen.',
        menu_collected_show: 'Als Suchergebnis anzeigen',
        menu_collected_show_hint: 'Alle Datensätze in der aktuellen Sammlung als Suchergebnis in einem neuen Browser-Tab anzeigen.',
            collection_limit: 'Zu viele Datensätze in der Sammlung. Die erlaubte Anzahl an der gesammelten Datensätze beträgt höchstens ',
            collection_select_hint:  'Bitte wählen Sie mindestens einen Datensatz aus, um ihn der Sammlung hinzufügen.',
            collection_select_hint2: 'Bitte wählen Sie mindestens einen Datensatz aus, um ihn aus der Sammlung zu entfernen.',
            collection_url_hint: 'Zu viele Datensätze in der Sammlung, um sie als Suchergebnisse anzuzeigen. Bitte speichern Sie die Sammmlung stattdessen als Filter ab.',
        
    'Recode': 'Umkodieren',
        menu_selected_value_add: 'Wert zu einem Feld zuweisen',
        menu_selected_value_add_hint: 'Den Datenwert dem Datenfeld in allen ausgewählten Datensätzen zuweisen.',
        menu_selected_value_replace: 'Datenwert in einem Feld ersetzen',
        menu_selected_value_replace_hint: 'Den vorhandenen Datenwert eines Datenfeldes in allen ausgewählten Datensätzen ersetzen.',
        menu_selected_value_delete: 'Den Datenwert aus dem Datenfeld löschen',
        menu_selected_value_delete_hint: 'Den vorhandenen Datenwert eines Datenfeldes in allen ausgewählten Datensätzen löschen.',
        menu_selected_add_link: 'Link hinzufügen',
        menu_selected_add_link_hint: 'Einen Link für die ausgewählten Datensätze hinzufügen',
        menu_selected_rectype_change: 'Datentyp ändern',
        menu_selected_rectype_change_hint: 'Die Datentypen für alle ausgewählten Datensätze ändern (z.B. Place -> Building)',
        menu_selected_extract_pdf: 'Text aus pdf extrahieren',
        menu_selected_extract_pdf_hint: 'Text aus angehängten pdf-Dateien extrahieren und als Textdaten in die entsprechenden Datensätze einfügen (experimentell).',
    'Share': 'Teilen',
        menu_selected_notify: 'Benachrichtigen (E-Mail)',
        menu_selected_notify_hint: 'Eine*n Benutzer*in über ausgewählte Datensätze per E-Mail benachrichtigen.',
        menu_selected_email: 'E-Mail senden',
        menu_selected_email_hint: 'Eine E-Mail an alle senden, die im Datensatz erfasst sind.',
        menu_selected_ownership: 'Eigentum / Sichtbarkeit',
        menu_selected_ownership_hint: 'Das Eigentum oder die Sichtbarkeit der ausgewählten Datensätze ändern.',
    'Reorder': 'Neu ordnen',
    menu_reorder_hint: 'Die Datensätze manuell neu anordnen, um sie als ein Suchergebnis mit fester Reihenfolge zu speichern.',
    menu_reorder_title: 'Ziehen Sie die Datensätze nach oben und unten, um sie neu anzuordnen.',
    menu_reorder_save: 'Neue Reihenfolge speichern',
    
    //
    resultList_select_record: 'Bitte wählen Sie mindestens einen Datensatz.',
    resultList_select_record2: 'Bitte wählen Sie mindestens zwei Datensätze, um diese zusammenzuführen.',
    resultList_select_limit: 'Zu viele ausgewählte Datensätze. Die größtmögliche Anzahl von ausgewählten Datensätze beträgt ',
    resultList_noresult: 'Keine Ergebnisse. Bitte versuchen Sie es mit einem anderen Filter.',
    resultList_empty_remark: 'Keine Ergebnisse. Es könnte Datensätze geben, die für die Öffentlichkeit oder Ihr Benutzerprofil nicht sichtbar sind.',
    resultList_private_record: 'Dieser Datensatz ist nicht für die Öffentlichtkeit sichtbar. Die/der Nutzer*in muss eingeloggt sein, um ihn zu sehen.',
    'private - hidden from non-owners': 'Privat – nur für die/den Eigentümer*in sichtbar.',
    'visible to any logged-in user': 'Für jede*n eigeloggte*n Nutzer*in sichtbar',
    'pending (viewable by anyone, changes pending)': 'Ausstehend (für alle sichtbar, Änderungen ausstehend)',
    'public (viewable by anyone)': 'Für alle sichtbar',
    'show selected only': 'Nur die ausgewählten Datensätze anzeigen',
    'Single lines': 'Datensätze in einzelnen Zeilen anzeigen.',
    'Single lines with icon': 'Datensätze in einzelnen Zeilen mit Symbolen anzeigen.',
    'Small images': 'Datensätze als kleine Vorschaubilder anzeigen.',
    'Large image': 'Datensätze als große Vorschaubilder anzeigen.',
    'Record contents': 'Inhalt des Datensatzes',
    resultList_view_content_hint1: 'Diese Warnung wird bei mehr als 10 Datensätzen ausgegeben',
    resultList_view_content_hint2: 'Sie haben ',
    resultList_view_content_hint3: 'Datensätze ausgewählt. Dieser Ansichtsmodus lädt die vollständigen Inhalte eines jeden Datensatzes und wird längere Zeit zur Darstellung dieser Inhalte in Anspruch nehmen.',
    resultList_action_edit: 'Datensatz bearbeiten',
    resultList_action_edit2: 'Datensatz in neuem Tab bearbeiten',
    resultList_action_view: 'Datensatz im Popup bearbeiten',
    resultList_action_view2: 'Externen Link in neuem Fenster ansehen',
    resultList_action_map: 'Karte anzeigen/verbergen',
    resultList_action_dataset: 'Datensatz herunterladen',
    resultList_action_embded: 'Datensatz einbinden',
    resultList_action_delete: 'Datensatz löschen',
    
    resultList_action_delete_hint: 'Kartendokument löschen; assoziierte Ebenen der Karte und Datenquellen behalten.',
    'Password reminder': 'Passworterinnerung',
    //END resultList
    
    //search - filters
    'Filter': 'Filter',
    'Filtered Result': 'Erweiterter Filter',
    'Save Filter': 'Filter speichern',
    save_filter_hint: 'Aktuellen Filter und Regeln als eine Verlinkung im Navigationsbaum speichern',
     
    //edit 
    Warn_Lost_Data: 'Sie haben Änderungen vorgenommen. Bestätigen Sie mit "Speichern" damit die Änderungen erhalten bleiben.',     
    Warn_Lost_Data_On_Structure_Edit: 'Auf "Daten Speichern" klicken, um die eingegebenen Daten zu speichern, "Änderungen verwerfen", um die gemachten Modifikationen abzulehnen. <br>Änderungen an der Struktur werden automatisch gespeichert - Ihre Auswahl wirkt sich darauf nicht aus.',
    
    //manage db definitions    
    manageDefRectypes_edit_fields: 'Datenfelder editieren',
    manageDefRectypes_edit_fields_hint: 'Diese Aktion wird einen leeren Datensatz dieses Typs im Strukturänderungsmodus öffnen.<br>Tipp: Um bleibende Änderungen an der Struktur vorzunehmen, können Sie den Strukturänderungsmodus jederzeit aktivieren, während Sie Daten eingeben.',            
    manageDefRectypes_new_hint: 'Bevor Sie einen neuen Datentyp definieren, ist es ratsam, '+
                    'eine passende Definition aus den Templates (Heurist Datenbanken, die zentral registriert worden) zu importieren. '+
                    'Die Templates mit einer Registrierungs-ID kleiner 1000 werden vom Heurist Team kuratiert. '
                    +'<br><br>'
    +'Dies ist insbesondere für BIBLIOGRAPHISCHE Datentypen wichtig - die Definitionen aus Template #6 (Bibliographische Definitionen) sind ' 
    +'optimal normalisiert und stellen die Austauschbarkeit mit bibliographischen Funktionen wie der Synchronisierung mit Zotero, dem Harvard Format und zwischen den Datenbanken sicher.'                
                    +'<br><br>Gehe zu Hauptmenü:  Design > Templates durchsuchen',
    manageDefRectypes_delete_stop: '<p>Der Datentyp <b>[rtyName]</b> besteht aus den folgenden Datenfeldern:</p>'
                + '[FieldList]'
                +'<p>Bitte entfernen Sie diese Datenfelder vollständig oder klicken Sie den obigen Link, <br>um die Elementardatenfelder zu ändern (dies wirkt sich auf all Datentypen aus, welche diese Datentypen nutzen).</p>',
    manageDefRectypes_delete_warn: 'Sind Sie sicher, dass Sie diesen Datentyp löschen möchten?',
    manageDefRectypes_duplicate_warn: 'Möchten Sie den folgenden Datentyp wirklich dublizieren ',
    'Click to add new': 'Neu hinzufügen',
    'Click to launch search for': 'Suche aufrufen',
    'Click to edit record type': 'Datentyp editieren',
    'Click to hide in lists': 'Liste verbergen',
    'Click to show in lists': 'Als Liste zeigen',
    'Duplicate record type': 'Datentyp dublizieren',
    'List of fields': 'Liste der Datenfelder',
    manageDefRectypes_reserved: 'Dies ist ein bereits definierter Datentyp, der in allen Heurist-Datenbanken vorhanden ist. Er kann nicht gelöscht werden.',
    manageDefRectypes_hasrecs: 'Um die Löscheinstellungen zu ändern, gehe zu: Erkunden > Datentypen finden und alle Datensätze löschen.',
    manageDefRectypes_delete: 'Löschen dieses Datentyps',
    manageDefRectypes_referenced: 'Dieser Datentyp wird referenziert. Klicken, um Verweise anzuzeigen',
    manageDefRectypes_longrequest: 'Es dauert zu lang. Es ist möglich, dass die Datenbank unter [url] nicht erreichbar ist. Für weitere 10 Sekunden wird Verbindungsaufbau versucht.',
    'Filter by Group': 'Nach Gruppe filtern',
    'Show All': 'Alles anzeigen',
    'Sort by': 'Sortieren nach',
    'All Find': 'Alles suchen',
    'Not finding the record type you require?': 'Nicht das gefunden, wonach Sie gesucht haben?',
    'Define new record type': 'Neuen Datentyp definieren',
    'Find by name, ID or concept code': 'Nach Name, ID oder Begriffskodierung suchen',
    'Show record types for all groups': 'Datentypen aus allen Gruppen anzeigen',
    'All Groups': 'Alle Gruppen',
    'All record type groups': 'Alle Datentypgruppen',
    'Edit Title Mask': 'Titelmaske editieren',
                    
    //my preferences
    'Define Symbology': 'Symbologie definieren',                
    'Define Heurist Theme': 'Heurist Theme definieren',
    'Configure Interface': 'Schnittstelle konfigurieren',
    'Mark columns to be visible. Drag to re-order': 'Spalte makieren für Sichtbarkeit. Rausziehen für neue Anordnung',
    
    //record add dialog    
    'Add Record': 'Datensatz hinzufügen',
    'Record addition settings': 'Erweiterte Datensatzeinstellungen',
    'Permission settings': 'Zugriffseinstellungen',
    'Save Settings': 'Einstellungen speichern',
    'Add Record in New Window': 'Datensatz im neuen Fenster hinzufügen',
    'Type of record to add': 'Datentyp hinzufügen',
    'Get Parameters': 'Parameter beziehen',
    'Select record type for record to be added': 'Datentyp für hinzuzufügenden Datensatz auswählen',
    'select record type': 'Datentyp auswählen',
    add_record_settings_hint: 'Bei Auswahl des Datentyps aus der Liste, werden diese Einstellungen angewendet und hinterlegt.',

    //record actions dialogs
    'Processed records': 'Verarbeitete Datensätze',
    recordAction_select_lbl: 'Umfang der Aktion',
    recordAction_select_hint: 'Bitte wählen Sie die Datensätze aus, die betroffen sein sollen.',
        'All records': 'Alle Datensätze',
        'Selected results set (count=': 'Aktuell ausgewählten Datensätze (Anzahl=',
        'Current results set (count=': 'Alle Datensätze in der aktuellen Abfrage (Anzahl=',
        'only:': 'nur:',
    'Add or Remove Tags for Records': 'Datensätze taggen oder enttaggen',
        'Add tags': 'Taggen',
        'Remove tags': 'Enttaggen',
        'Bookmark': 'Bookmarken',
        recordTag_hint0: 'Tags auswählen, die Lesezeichen der ausgewählten URLs hinzugefügt werden.<br>',
        recordTag_hint1: 'Tags auswählen, die Lesezeichen der Datensätze in der ausgewählten Umfang hinzugefügt werden.<br>', 
        recordTag_hint2: 'Tags auswählen, die allen Datensätzen in der ausgewählte Umfang hinzugefügt werden.<br>',
        recordTag_hint3: 'Tags werden währed der Eingabe automatisch vervollständigt. Klicken um hinzuzufügen.'
            +'<br>Neue Tags sind automatisch als \‚User-Specific\‘ erstellt werden;'
            +'<br>Arbeitsgruppen-Tags soll von einer*m Gruppeadministrator*in erstellt werden. Tags können Leerzeichen enthalten.',
        'No tags were affected': 'Keine Tags wurden betroffen',
        'Bookmarks added': 'Lesezeichen hinzugefügt.',
        'Tags added': 'Tags hinzugefügt',
        'Tags removed': 'Tags entfernt',
    'Unbookmark selected records': 'Ausgewählte Datensätze entbookmarken',    
        recordUnbookmark_hint: 'Datensätze auswählen, deren Lesezeichen entfernt werden.<br>'
            +'Alle \‚Personal Tags\‘ werden aus den ausgewählten Datensätzen entfernt.<br>'
            +'Diese Aktion <em>nur</em> die Lesezeichen Ihrer Datensätze entfernt;<br>'
            +'sie löscht keine Datensätze.',
        'Remove Bookmarks': 'Entbookmarken',
        'Bookmarks removed': 'Entwerfte Lesezeichen',
    'Set Record Rating': 'Datensatz bewerten',
        'Set Rating': 'Bewerten',
        'Please specify rating value': 'Bewertung angeben',
        'Rating updated': 'Bewertung aktualisiert',
        'No Rating': 'Keine Bewertung',
    'Delete Records': 'Datensätze löschen',
    
    //SERVER SIDE ERROR MESSAGES
    'Record type not defined or wrong': 'Es gibt keinen so genannten Datentyp in der Datenbank',
    
    //Client side error message
    Error_Title: 'Heurist',
    Error_Empty_Message: 'Keine Fehlermeldung. Bitte benachrichtigen Sie dem Heurist-Team über diesen Fehler.',
    Error_Report_Code: 'Diese Fehlerkode dem Heurist-Team benachrichtigen.',
    Error_Report_Team: 'Wenn dieser Fehler immer wieder auftritt, bitte benachrichtigen Sie ihn dem Heurist-Team und beschreiben Sie die Umstände, unter denen er auftrit.',
    Error_Wrong_Request: 'Die Anforderungsparameter sind falsh.',
    Error_System_Config: 'Dieser Fehler wird wahrscheinlich von einem Netzwerkausfall oder Systemkonfigurationsfehlr verursacht. Bitte melden Sie ihn Ihrem*r Systemadministrator*in.',
    Error_Json_Parse: 'Serverantwort nicht analyisierbar.'
    
//---------------------- END OF TRANSLATION 2021-10-19

};
