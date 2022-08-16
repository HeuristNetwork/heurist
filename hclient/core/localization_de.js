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
    'Favourites': '',
    
    //admin menu
    'Database': 'Datenbank',
        'menu-database-browse': 'Öffnen',
        'menu-database-create': 'Neu',
        'menu-database-clone': 'Klonen',
        'menu-database-delete': 'Löschen',
        'menu-database-clear': 'Leeren',
        'menu-database-rollback': 'Zurücksetzen',
        'menu-database-register': 'Registrieren',
        'menu-database-properties': 'Eigenschaften',
        
        'menu-database-browse-hint':'Weitere Heurist-Datenbank öffnen und einloggen. Die gegenwärtige Datenbank bleibt offen.',
        'menu-database-create-hint': 'Eine neue Datenbank auf dem aktuellem Server erstellen. Wesentliche Strukturkomponenten werden automatisch erstellt.',
        'menu-database-clone-hint':'Die aktuelle Datenbank mit allen Daten, Dateien, Strukturkomponenten, etc. klonen',
        'menu-database-delete-hint':'Die aktuelle Datenbank löschen, zusammen mit allen Datensätze und Dateien. Die Aktion kann nicht normalerweise zurückgesetzt werden, obwohl ein Sicherung erstellt werden kann.',
        'menu-database-clear-hint':'Alle Daten und Dateien aus der aktuellen Datenbank löschen, aber die Strukturkomponenten erhalten.',
        'menu-database-rollback-hint':'Setzt die Daten in der Datenbank auf ein bestimmtes Datum und eine bestimmte Uhrzeit zurück.',
        'menu-database-register-hint':'Die Datenbank im Heurist Reference Index registrieren. Mit dieser Registrierung wird die Struktur Ihrer Datenbank anderen Nutzern zur Verfügung gestellt. Anderen Nutzern wird erlaub, die Datenbank zu klonen oder als XML/RDF zu exportieren.', 
        'menu-database-properties-hint':'Die Metadaten Ihrer Datenbank bearbeiten und das allgemeine Verhalten des Systems einstellen. Diese Aktion wird empfohlen, um Ihre Datenbank zu dokumentieren.',
    
    'Manage Users': 'Benutzer verwalten',
        'menu-profile-groups': 'Arbeitsgruppen',
        'menu-profile-users': 'Nutzer',
        'menu-profile-import': 'Nutzer importieren',
        'menu-profile-groups-hint': 'Liste der Arbeitsgruppen der Datenbank',
        'menu-profile-users-hint': 'Füge neue Nutzer der Datenbank hinzu und bearbeite vorhandene Nutzer.',
        'menu-profile-import-hint': 'Nutzer von anderen Heurist-Datenbanken importierien, einschließlich ihren Nutzernamen, Passwörter, usw. Importierte Nutzer werden nicht automatisch zu Arbeitsgruppen zugewiesen.',
    'Utilities': 'Dienstprogramme',
        'menu-profile-files': 'Dateien verwalten',
        'menu-profile-preferences': 'Einstellungen',
        'menu-structure-verify': 'Integrität überprüfen',
        'menu-structure-duplicates': 'Doppelte Datensäzte suchen',
        'menu-manage-rectitles': 'Titel der Datensätze wieder zuweisen',
        'menu-manage-calcfields': 'Rebuild calculation fields', //@translate
        'menu-interact-log': 'Interaction log',
        'menu-profile-files-hint': 'Hochgeladene Dateien und externe Medienquellen verwalten.',
        'menu-profile-preferences-hint': 'Einstellungen dieser Datenbank ändern.',
        'menu-structure-verify-hint': 'Fehler in der Datenbankstruktur und individuellen Datensätzen (z.B. falsch formatierte Daten) suchen.',
        'menu-structure-duplicates-hint': 'Ungenaue Suche für Datensätze mit Dopplungen.',
        'menu-manage-rectitles-hint': 'Die rekonstruierten Titel der Datensätze wieder zuweisen.',
        'menu-interact-log-hint': 'Download the interaction log',
    'Server Manager': 'Server-Administrator*in',
        'menu-admin-server': 'Datenbanken verwalten',
        'menu-admin-server-hint': 'Datenbanken verwalten (Administratorpasswort notwendig)',

    //design menu
    'Modify': 'Ändern',
        'menu-structure-rectypes': 'Datentypen',
        'menu-import-csv-rectypes': 'Import from CSV', //@translate
        'menu-structure-vocabterms': 'Vordefinierte Vokabulare',
        'menu-structure-fieldtypes': 'Datenfeld', //double check Grundfelder
        'menu-import-csv-fieldtypes': 'Import from CSV', //@translate
        'menu-structure-import': 'Templates durchsuchen',
        'menu-lookup-config': 'Externe Lookups',
        'menu-structure-summary': 'Visualisieren',
        'menu-structure-refresh': 'Neuladen',
    
        'menu-structure-rectypes-hint': 'Neue Datensatztypen hinzufügen oder aktuelle Datensatztypen ändern.',
        'menu-structure-vocabterms-hint': 'Neues Vokabular hinzufügen oder aktuelles Vokabular ändern.',
        'menu-structure-fieldtypes-hint': 'Datenfelddefinitionen durchsuchen, hinzufügen oder ändern. Datenfelder (z.B. \'Name / Title\' oder \'Start Date\') werden meist von vielen Datentypen gemeinsam genutzt.',
        'menu-structure-import-hint': 'Datentypen, Datenfelder und Vokabulare aus anderen Heurist-Datenbanken importieren.',
        'menu-structure-summary-hint': 'Beziehungen zwischen Datentypen visualisieren, um die Struktur der Datenbank besser zu verstehen.',
        'menu-structure-refresh-hint': 'Speicher leeren und neuladen (wenn kürzlich gemachte Änderungen in der Datenbankstruktur nicht angezeigt werden).',

    'Setup': 'Setup',
        'menu-manage-dashboards': 'Verknüpfungsleiste',
        'menu-manage-archive': 'Archivpaket',
        'menu-manage-dashboards-hint': 'Eine benutzerdefinierte Liste mit Verknüpfungen auf dem Bildschirm erstellen.',
        'menu-manage-archive-hint': 'Eine vollständige Sicherung Ihrer Datenbank in einer einzigen ZIP-Datei herunterladen. Das Paket enthält alle hochgeladenen Dateien, alle Datensätze und die Struktur der Datenbank in SQL- und XML-Formaten.',
    
    'Download': 'Herunterladen',
        'menu-manage-structure-asxml': 'Struktur (XML)',
        'menu-manage-structure-assql': 'Struktur (Text)',
        'menu-manage-structure-asxml-hint': 'Eine Darstellung der Datenbankstruktur im XML-Format herunterladen.',
        'menu-manage-structure-assql-hint': 'Eine Darstellung der Datenbankstruktur in einem maschinenlesbaren Textformat herunterladen (veraltet 2014).',

    //populate menu
    'Upload Files': 'Datei hochladen',
        'menu-import-csv': 'Delimited text / CSV',
        'menu-import-zotero': 'Zotero-Bibliothek synchronisieren',
        'menu-import-xml': 'Heurist XML/JSon',
        'menu-import-kml': 'KML',
        'menu-import-get-template': 'Template herunterladen (XML)',
        'menu-import-csv-hint': 'Tabellierte Daten im Textformat importieren. Datensätze werden mit bestehenden Datensätzen abgeblichen und entsprechend hinzugefügt oder aktualisiert.',
        'menu-import-zotero-hint': 'Mit einer Zotero-Bibliothek synchronisieren. Datensätze in der Zotero-Bibliothek werden mit Datensätzen in der Datenbank abgeglichen, und entsprechend hinzugefügt oder aktualisiert.',
        'menu-import-xml-hint': 'Datensätze von einer andere Heurist-Datenbank importieren (im Heurist-XML-Format (HML)).',
        'menu-import-kml-hint': 'Geographische Daten im KML-Format importieren (Daten in WKT-Format können mithilfe des CSV-Importers importiert werden.)',
        'menu-import-get-template-hint': 'XML-Template der Datenbank herunterladen, um XML/HML-Importe vorzubereiten.',
    'Media Files': 'Mediadateien',
        'menu-files-upload': 'Mediadateien hochladen',
        'menu-files-index': 'Mediadateien indizieren',
        'menu-files-upload-hint': 'Mediadateien im Ganzen hochladen (bulk upload), oder zuvor hochgeladene Dateien aktualisieren und verwalten.',
        'menu-files-index-hint': 'Für jede neue hochgeladene Mediadatei einen \'Digital Media File\'-Datensatz in der Datenbank generieren.',
        
    //publish menu
    'Website': 'Website',
        'menu-cms-create': 'Erstellen',
        'menu-cms-edit': 'Bearbeiten',
        'menu-cms-view': 'Ansehen',
        'menu-cms-embed': 'Eigenständige Webseite',
        'menu-cms-create-hint': 'Eine neue CMS-Website erstellen. Die Website wird auf Daten aus der Datenbank mithilfe des Heurist-Widgets zugreifen können.',
        'menu-cms-edit-hint': 'Eine bestehende Website bearbeiten.',
        'menu-cms-view-hint': 'Eine bestehende Website ansehen.',
        'menu-cms-embed-hint': 'Eine eigenständige Webseite erstellen oder bearbeiten. Eigenständige Webseiten können einfach in anderen Websiten (z.B. Wordpress-Siten) eingebettet werden.',
        
        'Website Editor': 'Website-Editor',
        'Sitewide Settings': 'Einstellungen für die gesamte Website',
        'Current Page': 'Aktuelle Seite',
        'Page Editor': 'Seiten-Editor',
        
    'Standalone web page': 'Eigenständige Webseite',
        'menu-cms-create-page': 'Erstellen',
        'menu-cms-edit-page': 'Bearbeiten',
        'menu-cms-view-page': 'Ansehen',
        
    'Export': 'Export',
        'menu-export-csv': 'CSV',
        'menu-export-hml-resultset': 'XML',
        'menu-export-json': 'JSON',
        'menu-export-geojson': 'GeoJSON',
        'menu-export-kml': 'KML',
        'menu-export-gephi': 'GEPHI',
        'menu-export-iiif': 'IIIF',
        'menu-export-csv-hint': 'Datensätze in CSV-Datei exportieren, um sie z.B. in einem Tabellenkalkulationsprogramm zu öffnen.',
        'menu-export-hml-resultset-hint': 'XML/HML-Datei für aktuelle Abfrage generieren.',
        'menu-export-json-hint': 'JSON-Datei für aktuelle Abfrage generieren.',
        'menu-export-geojson-hint': 'GeoJSON-Datei für aktuelle Abfrage generieren.',
        'menu-export-kml-hint': 'KML-Datei für aktuelle Abfrage generieren.',
        'menu-export-gephi-hint': 'gphx-Datei für aktuelle Abfrage generieren, um die Daten im Gephi-Programm zu analysieren und visalisieren.',
        'menu-export-iiif-hnt': 'IIIF-Manifest für aktuelle Abfrage generieren.',
        
    'Safeguard':'Sicherung',

    //My profile menu
    'menu-profile-tags': 'Tags verwalten',
    'menu-profile-reminders': 'Erinnerungen verwalten',
    'menu-profile-info': 'Meine Nutzerdaten',
    'menu-profile-logout': 'Ausloggen',
    'menu-profile-tags-hint': 'Persönliche oder Arbeitsgruppen-Tags hinzufügen, ändern oder löschen.',
    'menu-profile-reminders-hint': 'Einrichten oder deaktivieren der Benachrichtigungs-E-Mails von Datensätzen, die mit Lesezeichen versehenen worden sind.',
    'menu-profile-info-hint': 'Persönliche Nutzerdaten',
    'menu-profile-logout-hint': 'Ausloggen',
        
    //HELP menu
    HELP: 'Hilfe',
        'menu-help-online': 'Online-Hilfesystem',
        'menu-help-website': 'Website des Heurist-Netzwerk',
        'menu-help-online-hint': 'Benutzerhandbuch für die Heurist-Schnittstelle',
        'menu-help-website-hint': 'Zentrale Webseite für das Heurist-Netzwerk, die Informationen über Support-Services, Veranstaltungen, Projekte und Benutzer des Heuristsystems enthält.',
    CONTACT: 'Kontakt',
        'menu-help-bugreport': 'Fehlerbericht/Anfragen',
        'menu-help-emailteam': 'Heurist Team',
        'menu-help-emailadmin': 'Systemadministrator',
        'menu-help-acknowledgements': 'Danksagung', //maybe Empfangsbestätigung is meant? I have to see it in Heurist to know which meaning it is
        'menu-help-about': 'About',
        'menu-help-bugreport-hint': 'Das Heurist-Team über Fehler oder gewünschte Funktionen benachrichtigen',
        'menu-help-emailteam-hint': 'Bei allgemeinen Fragen sich an das Heurist-Team wenden.',
        'menu-help-emailadmin-hint': 'Den Systemadministrator kontaktieren.',
        'menu-help-acknowledgements-hint': '',
        'menu-help-about-hint': 'Version, Credits, Copyright, Lizenz',
    
    'menu-subset-set': 'Als Teilmenge definieren',
    'menu-subset-set-hint': 'Die aktuelle Abfrage als Teilmenge definieren, um alle künftigen Aktionen nur auf diese Teilmenge anzuwenden.',
    'Click to revert to whole database': 'Zurück zur gesamten Datenbank',
    'SUBSET ACTIVE': 'Teilmenge aktiv',
    'Current subset': 'Aktuelle Teilmenge',
    
    //END main menu
    
    //resultList --------------------
    
    'Selected': 'Ausgewählt',
        'menu-selected-select-all': 'Alles auswählen',
        'menu-selected-select-all-hint': 'Alle Datensätze auf dieser Seite auswählen.', 
        'menu-selected-select-none': 'Keinen Datensatz auswählen',
        'menu-selected-select-none-hint': 'Die Auswahl verwerfen',
        'menu-selected-select-show': 'Als Suchergebnis anzeigen',
        'menu-selected-select-show-hint': 'Die aktuelle Auswahl als Suchergebnis in einem neuen Browser-Tab anzeigen.',
        'menu-selected-tag': 'Tag',
        'menu-selected-tag-hint': 'Datensätze in der aktuellen Auswahl taggen.',
        'menu-selected-rate': 'Bewerten',
        'menu-selected-rate-hint': 'Datensätze in der aktuellen Auswahl bewerten.',
        'menu-selected-bookmark': 'Lesezeichen setzen',
        'menu-selected-bookmark-hint': 'Datensätze in der aktuellen Auswahl bookmarken.',
        'menu-selected-unbookmark': 'Lesezeichen entfernen',
        'menu-selected-unbookmark-hint': 'Die Lesezeichen der Datensätze in der aktuellen Auswahl entfernen.',
        'menu-selected-merge': 'Zusammenführen',
        'menu-selected-merge-hint': 'Die ausgewählten Datensätze zusammenführen.',
        'menu-selected-delete': 'Löschen',
        'menu-selected-delete-hint': 'Die ausgewählten Datensätze löschen.',
    'Collected': 'Gesammelt',
    'Collect': 'Sammeln',
        'menu-collected-add': 'Hinzufügen',
        'menu-collected-add-hint': 'Die ausgewählten Datensätze der Sammlung hinzufügen.',
        'menu-collected-del': 'Leeren',
        'menu-collected-del-hint': 'Die ausgewählten Datensätze aus der Sammlung entfernen.',
        'menu-collected-clear': 'Alle Datensätze leeren',
        'menu-collected-clear-hint': 'Die Inhalte der Sammlung leeren.',
        'menu-collected-save': 'Speichern unter',
        'menu-collected-save-hint': 'Datensätze als Sammlung speichern, um sie zukünftig einfacher aufzurufen.',
        'menu-collected-show': 'Als Suchergebnis anzeigen',
        'menu-collected-show-hint': 'Alle Datensätze in der aktuellen Sammlung als Suchergebnis in einem neuen Browser-Tab anzeigen.',
            collection_limit: 'Zu viele Datensätze in der Sammlung. Die erlaubte Anzahl an der gesammelten Datensätze beträgt höchstens ',
            collection_select_hint:  'Bitte wählen Sie mindestens einen Datensatz aus, um ihn der Sammlung hinzufügen.',
            collection_select_hint2: 'Bitte wählen Sie mindestens einen Datensatz aus, um ihn aus der Sammlung zu entfernen.',
            collection_url_hint: 'Zu viele Datensätze in der Sammlung, um sie als Suchergebnisse anzuzeigen. Bitte speichern Sie die Sammmlung stattdessen als Filter ab.',
        
    'Recode': 'Umkodieren',
        'menu-selected-value-add': 'Wert zu einem Feld zuweisen',
        'menu-selected-value-add-hint': 'Den Datenwert dem Datenfeld in allen ausgewählten Datensätzen zuweisen.',
        'menu-selected-value-replace': 'Datenwert in einem Feld ersetzen',
        'menu-selected-value-replace-hint': 'Den vorhandenen Datenwert eines Datenfeldes in allen ausgewählten Datensätzen ersetzen.',
        'menu-selected-value-delete': 'Den Datenwert aus dem Datenfeld löschen',
        'menu-selected-value-delete-hint': 'Den vorhandenen Datenwert eines Datenfeldes in allen ausgewählten Datensätzen löschen.',
        'menu-selected-add-link': 'Link hinzufügen',
        'menu-selected-add-link-hint': 'Einen Link für die ausgewählten Datensätze hinzufügen',
        'menu-selected-rectype-change': 'Datentyp ändern',
        'menu-selected-rectype-change-hint': 'Die Datentypen für alle ausgewählten Datensätze ändern (z.B. Place -> Building)',
        'menu-selected-extract-pdf': 'Text aus pdf extrahieren',
        'menu-selected-extract-pdf-hint': 'Text aus angehängten pdf-Dateien extrahieren und als Textdaten in die entsprechenden Datensätze einfügen (experimentell).',
    'Share': 'Teilen',
        'menu-selected-notify': 'Benachrichtigen (E-Mail)',
        'menu-selected-notify-hint': 'Eine*n Benutzer*in über ausgewählte Datensätze per E-Mail benachrichtigen.',
        'menu-selected-email': 'E-Mail senden',
        'menu-selected-email-hint': 'Eine E-Mail an alle senden, die im Datensatz erfasst sind.',
        'menu-selected-ownership': 'Eigentum / Sichtbarkeit',
        'menu-selected-ownership-hint': 'Das Eigentum oder die Sichtbarkeit der ausgewählten Datensätze ändern.',
    'Reorder': 'Neu ordnen',
    'menu-reorder-hint': 'Die Datensätze manuell neu anordnen, um sie als ein Suchergebnis mit fester Reihenfolge zu speichern.',
    'menu-reorder-title': 'Ziehen Sie die Datensätze nach oben und unten, um sie neu anzuordnen.',
    'menu-reorder-save': 'Neue Reihenfolge speichern',
    
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
    resultList_reorder_list_changed: 'Reorder list has been changed.', //EN
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
    recordAction_select_lbl: 'Datensatzbereich',
    recordAction_select_hint: 'Bitte wählen Sie die betroffenen Datensätze aus.',
        'All records': 'Alle Datensätze',
        'Selected results set (count=': 'Aktuell ausgewählten Datensätze (Anzahl=',
        'Current results set (count=': 'Alle Datensätze in der aktuellen Abfrage (Anzahl=',
        'only:': 'nur:',
    'Add or Remove Tags for Records': 'Datensätze taggen oder enttaggen',
        'Add tags': 'Taggen',
        'Remove tags': 'Enttaggen',
        'Bookmark': 'Bookmarken',
        recordTag_hint0: 'Tags auswählen, die Lesezeichen der ausgewählten URLs werden hinzugefügt.<br>',
        recordTag_hint1: 'Tags auswählen, die Lesezeichen der Datensätze werden im ausgewählten Bereich hinzugefügt.<br>', 
        recordTag_hint2: 'Tags auswählen, die Datensätze werden im ausgewählten Bereich hinzugefügt.<br>',
        recordTag_hint3: 'Tags werden währed der Eingabe automatisch vervollständigt. Klicken, um hinzuzufügen.'
            +'<br>Neue Tags werden automatisch als \‚User-Specific\‘ erstellt;'
            +'<br>Arbeitsgruppen-Tags sollen von einer*m Gruppeadministrator*in erstellt werden. Tags können Leerzeichen enthalten.',
        'No tags were affected': 'Es sind keine Tags betroffen',
        'Bookmarks added': 'Lesezeichen hinzugefügt.',
        'Tags added': 'Tags hinzugefügt',
        'Tags removed': 'Tags entfernt',
    'Unbookmark selected records': 'Für ausgewählte Datensätze Lesezeichen entfernen',    
        recordUnbookmark_hint: 'Datensätze auswählen, deren Lesezeichen entfernt werden sollen.<br>'
            +'Alle \‚Personal Tags\‘ werden aus den ausgewählten Datensätzen entfernt.<br>'
            +'Diese Aktion entfernt <em>nur</em> die Lesezeichen Ihrer Datensätze;<br>'
            +'sie löscht keine Datensätze.',
        'Remove Bookmarks': 'Lesezeichen entfernen',
        'Bookmarks removed': 'Lesezeichen entfernt',
    'Set Record Rating': 'Datensatz bewerten',
        'Set Rating': 'Bewerten',
        'Please specify rating value': 'Bewertung angeben',
        'Rating updated': 'Bewertung aktualisiert',
        'No Rating': 'Keine Bewertung',
    'Delete Records': 'Datensätze löschen',
    
    //SERVER SIDE ERROR MESSAGES
    'Record type not defined or wrong': 'Falscher oder nicht definierter Datentyp',
    
    //Client side error message
    Error_Title: 'Heurist',
    Error_Empty_Message: 'Keine Fehlermeldung. Bitte benachrichtigen Sie das Heurist-Team über diesen Fehler.',
    Error_Report_Code: 'Diesen Fehlerkode dem Heurist-Team melden.',
    Error_Report_Team: 'Sollte dieser Fehler wiederholt auftreten, benachrichtigen Sie bitte das Heurist-Team und beschreiben Sie die Umstände, unter denen er auftrit.',
    Error_Wrong_Request: 'Wert, Anzahl und/oder die Menge der Anfrageparameter sind ungültig.',
    Error_System_Config: 'Dieser Fehler wird wahrscheinlich von einem Netzwerkausfall oder Systemkonfigurationsfehler verursacht. Bitte melden Sie ihn Ihrem*r Systemadministrator*in.',
    Error_Json_Parse: 'Antwort des Servers ist unbekannt.'
    
//---------------------- END OF TRANSLATION 2021-10-19

};
