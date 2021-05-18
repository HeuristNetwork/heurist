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
    'required field': 'verplicht veld'


};