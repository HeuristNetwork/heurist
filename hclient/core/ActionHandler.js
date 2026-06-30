/**
 * @file ActionHandler.js
 * @brief Manages lists of menu actions, loads them from JSON, and executes them.
 * @fileOverview The ActionHandler class is responsible for managing a list of actions, typically used for menus within the Heurist system.
 * It can load these actions from a JSON file or be initialized with an array of action objects.
 * Key functionalities include executing actions by their ID, handling verifications (like password prompts),
 * managing actions that open URLs or dialogs, and orchestrating complex operations such as importing users
 * or performing database management tasks. It integrates with other parts of the HAPI (Heurist API)
 * and UI components to provide a cohesive user experience for invoking system commands.
 * 
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

/* global CmsManager */

/**
 * Class: ActionHandler
 * 
 * The ActionHandler class is responsible for managing a list of actions (menu actions) fetched from a remote JSON file or provided directly as an array. 
 * It offers methods for executing actions based on their ID and performing verification tasks where necessary.
 */
class ActionHandler {
    
    
    // Flags loading of a simple guided tour based on https://introjs.com/
    isGuidedTourLoaded = false;

    /**
     * Initializes the ActionHandler instance.
     * If `arg` is an array, it sets the `actions` property directly.
     * Otherwise, it initializes `actions` to null, expecting `loadActionsFromFile` to be called.
     *
     * @param {(Array<Object>|String|undefined)} arg - Can be an array of action objects,
     * a string URL to fetch actions from, or undefined (in which case a default URL will be used later or actions loaded manually).
     */
    constructor(arg) {
        if(Array.isArray(arg)){
            this.actions = arg;
        } else {
            this.actions = null;
        }
    }
    
    /**
     * Fetches actions from the given URL (or a default URL if not provided) in JSON format and sets the `actions` property.
     *
     * @async
     * @param {string} [url] - The URL to fetch actions from. Defaults to `hclient/core/actions.json` relative to `window.hWin.HAPI4.baseURL`.
     * @returns {Promise<void>} A promise that resolves when actions are loaded and set, or rejects on error.
     * @throws {Error} If the fetch operation fails or the response is not OK.
     */
    async loadActionsFromFile(url) {
        try {
            const path = url || (window.hWin.HAPI4.baseURL + 'hclient/core/actions.json');
            const response = await fetch(path);
            if (!response.ok) {
                throw new Error(`Failed to load actions from ${url}: ${response.statusText}`);
            }
            this.actions = await response.json();
        } catch (error) {
            console.error("Error loading actions:", error);
        }
    }
    
    /**
     * Returns the currently loaded actions.
     *
     * @returns {Array<Object>|null} The array of action objects, or null if actions haven't been loaded.
     */
    getActions() {
        return this.actions;
        /*console.log("Available actions:");
        this.actions.forEach(action => {
            console.log(`ID: ${action.id}, Text: ${action.text}`);
        });*/
    }

    /**
     * Finds and returns an action object by its ID.
     *
     * @param {string} id - The ID of the action to find.
     * @returns {Object|undefined} The action object if found, otherwise `undefined`.
     */
    findActionById(id) {
        return this.actions?this.actions.find(action => action.id === id):null;
    }

    /**
     * Handles the verification process for an action, typically involving password and/or permission checks.
     * If verification is required and not yet passed, it initiates the verification flow.
     * Upon successful verification, it re-executes the original action with `verification_passed` set to true.
     *
     * @private
     * @param {Object} action - The action object being executed.
     * @param {Object} dialog_options - Options for the dialog, potentially modified during verification.
     * @returns {boolean} Returns `true` if verification was initiated (halting further execution of the current call),
     * or `false` if no verification was needed or if action data is missing.
     */
    #handleVerification(action, dialog_options) {
        
        let adata = action.data;            
        
        if(!adata){
            return false;
        }
        
        // Handle password and permission verification
        let action_passworded = adata.pwd;
        if (!action_passworded && !window.hWin.HAPI4.has_access(2)) { // is datbase owner
            action_passworded = adata['pwd-nonowner'];
        }
        
        let action_admin_level = adata['user-admin-status'];
        let action_member_level = adata['user_member_status'];
        let action_user_permissions = adata['user-permissions'];
        const associationMembershipContext = adata['is_association_member']==1?action.id:null; 
        let requiredLevel = (action_admin_level == -1 || action_admin_level >= 0) ? action_admin_level : 0;
        
        if(!(action_passworded || requiredLevel > 0 || associationMembershipContext)){ 
            return false;                
        }
        
        if (action_member_level > 0) {
            requiredLevel += ';' + action_member_level;
        }
        
        window.hWin.HAPI4.SystemMgr.verify_credentials((entered_password) => {
            dialog_options.entered_password = entered_password;
            dialog_options.verification_passed = true;
            this.executeActionById(action.id, dialog_options); // Re-execute action after successful verification
        },
        requiredLevel, action_passworded, null, action_user_permissions, associationMembershipContext);
            
        return true; // Indicates verification was initiated
    }    
    
    /**
     * Handles actions that involve opening a URL (e.g., hyperlink, mailto).
     * It constructs the final URL, handles special cases like 'menu-help-emailadmin',
     * and opens the URL in a new window/tab or a dialog.
     *
     * @private
     * @param {Object} action - The action object, containing href, target, id, and text.
     * @param {Object} popup_dialog_options - Options for displaying the content in a dialog if not opening in a new tab/window.
     * @returns {boolean} Returns `true` if the href action was handled, `false` otherwise (e.g. empty href).
     */
    #handleHrefAction(action, popup_dialog_options) {

        let href = action.href;
        const target = action?.target;

        if (action.id === 'menu-help-emailadmin') {
            href = `mailto:${window.hWin.HAPI4.sysinfo.sysadmin_email}`;
        } else if (window.hWin.HEURIST4.util.isempty(href) || href == '#') {
            return false;
        }

        if (href.startsWith('mailto:')) {
            window.open(href, 'emailWindow');
            return true; // Assuming it was handled
        }

        if (!(href.startsWith('http://') || href.startsWith('https://'))) {
            href = window.hWin.HAPI4.baseURL + href + (href.indexOf('?') >= 0 ? '&' : '?') + 'db=' + window.hWin.HAPI4.database;
        }

        if (target) {
            window.open(href, target);
        } else {
            if (!popup_dialog_options.title) {
                popup_dialog_options.title = action.text;
            }
            let options = $.extend(popup_dialog_options, { width: 800, height: 600 });

            window.hWin.HEURIST4.msg.showDialog(href, options);
        }
        return true;
    }

    /**
     * Prepares and standardizes dialog options for an action.
     * It determines the container for the dialog, sets the title (checking for translations),
     * and merges action-specific data with provided dialog_options.
     *
     * @private
     * @param {Object} action - The action object, containing id, data, and text.
     * @param {Object} dialog_options - Initial dialog options provided to `executeActionById`.
     * @returns {Object} The fully prepared popup dialog options.
     */
    #prepareDialogOptions(action, dialog_options){        
         let actionid = action.id;
         let adata = action.data;
         let action_container = adata?.container;

         let container, menu_container;

         if (dialog_options.container) {
             container = dialog_options['container'];
         } else if (action_container) {
             let section = action_container;
             
             $('.ui-menu6').slidersMenu('switchContainer', section, true);
             container = $('.ui-menu6 > .ui-menu6-widgets.ui-heurist-'+section);
             container.removeClass('ui-suppress-border-and-shadow');

             menu_container = $('.ui-menu6 > .ui-menu6-section.ui-heurist-' + section);
             menu_container.find('li').removeClass('ui-state-active');
             menu_container.find('li[data-action="' + actionid + '"]').addClass('ui-state-active');
         }

         let popup_dialog_options = {
             isdialog: !container,
             innerTitle: true,
             menu_container: menu_container,
             container: container,

             isFrontUI: true,
             is_h6style: true,
             resizable: false,
             draggable: false,
             position: dialog_options.position || null,
             maximize: true,
             ...dialog_options
         };

         const title_key = actionid+'-header';
         if (window.hWin.HR(title_key) == title_key) { //translation not found
             popup_dialog_options.title = window.hWin.HR(adata?.header || action.text);
         }else{
             popup_dialog_options.title = window.hWin.HR(title_key);
         }
         
         return popup_dialog_options;
    }
    
    /**
     * Handles the multi-step process of importing users from another database.
     * Step 1: Select the source database.
     * Step 2: Select users from the source database.
     * Step 3: Allocate selected users to work groups in the current database.
     * @todo TO BE REMOVED TO SEPARATE CLASS
     * @param {Object} [entity_dialog_options] - Initial configuration options for the entity dialogs used in the import process.
     * Defaults to an empty object if not provided. These options can be extended internally for each step.
     * @returns {void}
     */
    importUsers(entity_dialog_options) {
        if (!entity_dialog_options) entity_dialog_options = {};
        
        let that = this;
        let auto_select = '';
        
        let options = $.extend(entity_dialog_options, {
            subtitle: 'Step 1. Select database with users to be imported',
            title: 'Import users', 
            select_mode: 'select_single',
            pagesize: 300,
            edit_mode: 'none',
            use_cache: true,
            except_current: true,
            keep_visible_on_selection: true,
            onselect: function(event, data){
                if (!data?.selection || data.selection.length == 0) {
                    return;
                }

                let selected_database = data.selection[0];
                if(selected_database.indexOf(window.hWin.HAPI4.sysinfo.database_prefix) === 0){
                    selected_database = selected_database.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
                }

                let options2 = $.extend(entity_dialog_options, {
                    subtitle: `Step 2. Select users in ${selected_database} to be imported`,
                    title: 'Import users', 
                    database: selected_database,
                    select_mode: 'select_multi',
                    edit_mode: 'none',
                    keep_visible_on_selection: true,
                    onInitFinished: function(){
                        setTimeout((mngUsers, rec_id) => { mngUsers.recordList.find(`[recid="${rec_id}"]`).trigger('click'); }, 500, this, auto_select);
                        auto_select = '';
                    },
                    onselect: function(event, data){

                        if (!data?.selection || data.selection.length == 0) {
                            return;
                        }

                        let selected_users = data.selection;
                        let options3 = $.extend(entity_dialog_options, {
                            subtitle: 'Step 3. Allocate imported users to work groups',
                            title: 'Import users', 
                            select_mode: 'select_roles',
                            selectbutton_label: 'Allocate roles',
                            sort_type_int: 'recent',
                            edit_mode: 'none',
                            keep_visible_on_selection: false,
                            onselect: function(event, data){
                                that.importUsersComplete(data, selected_users, selected_database);
                            }
                        });

                        window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options3);
                    }
                });

                if(!window.hWin.HEURIST4.util.isempty(data.email)){
                    
                    let request = {
                        a: 'search',
                        entity: 'sysUsers',
                        details: 'id',
                        ugr_eMail: data.email,
                        db: selected_database
                    };

                    window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                        if(response.status === window.hWin.ResponseStatus.OK && response.data.reccount === 1){
                            auto_select = response.data.records[0];
                        }

                        window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options2);
                    });

                }else{
                    window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options2);
                }

            }
            
        });
        window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);
    }    
    
    /**
     * Completes the user import process after users and roles have been selected.
     * It sends a request to the server to add the selected users from the source database
     * to the specified roles in the current database.
     * Displays a success or error message based on the server response.
     *
     * @private
     * @param {Object} data - Data from the role selection dialog, containing `data.selection` (selected roles).
     * @param {Array<string>} selected_users - An array of user IDs to be imported.
     * @param {string} selected_database - The name of the database from which users are being imported.
     * @returns {void}
     */ 
    importUsersComplete(data, selected_users, selected_database){

        if (!data || $.isEmptyObject(data.selection)){
            return;
        }

        let request = {
            a: 'action',
            entity: 'sysUsers',
            roles: data.selection,
            userIDs: selected_users,
            sourceDB: selected_database
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
            if (response.status == window.hWin.ResponseStatus.OK) {
                window.hWin.HEURIST4.msg.showMsgDlg(response.data);
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
        
    }
    
    /**
     * Executes an action based on its ID.
     * This method finds the action, handles any necessary verification (like password prompts),
     * prepares dialog options, and then delegates to specific handlers or opens URLs
     * based on the action's configuration.
     *
     * @param {string} id - The ID of the action to execute.
     * @param {Object} [dialog_options] - Optional parameters for dialog customization. Defaults to an empty object if not provided.
     * These options can include `verification_passed` to bypass verification if already done.
     * @returns {boolean} Returns `true` if the action was found and an attempt was made to handle it (even if handling later fails or is asynchronous).
     * Returns `false` if the action with the given ID is not found or if the action is marked as disabled (`ext == 1`).
     */
    executeActionById(id, dialog_options) {
        const action = this.findActionById(id);
        
        if (!action) {
            console.warn(`Action with ID "${id}" not found.`);
            return false;
        }
        
        let adata = action.data;
    
        // If action is disabled (external/extension action not implemented), return early
        if (adata?.ext == 1) {
            console.log(`Action with ID "${id}" is marked as 'ext' and is not handled.`);
            return false;
        }
        
        if(!dialog_options){
            dialog_options = {};
        }

        // If verification is required and not already passed, #handleVerification will initiate it and return true.
        // In that case, current execution should stop, as verification callback will re-trigger executeActionById.        
        if(!dialog_options?.verification_passed && 
            this.#handleVerification(action, dialog_options))
        {
            return true;
        }

        let actionid = action.id;
        let action_log = adata?.logaction; 
        
        if (action_log) {
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }
        
        if (actionid == 'data-heurist-pageid') {
            
            if(window.hWin.webSite){
                window.hWin.webSite.loadPage( dialog_options );
            }else{
                window.hWin.HEURIST4.msg.showMsgErr('Web Page can not be loaded. CMS is not inited for this instance of Heurist');
            }
            return true;
        }
        
        if (actionid.indexOf('menu-cms') == 0) {
            if (!this.cmsManager) {
                this.cmsManager = new CmsManager();
            }
            this.cmsManager.executeAction(actionid);
            return true;
        }

        let popup_dialog_options = this.#prepareDialogOptions(action, dialog_options);
        
        let is_supported = true;
        let contentURL;
        
        switch (actionid) {
            case "search-saved-filter":
            
            
                break;
            case "menu-database-create":
            case "menu-database-restore":
            case "menu-database-delete":
            case "menu-database-clear":
            case "menu-database-rename":
            case "menu-database-clone":
            case "menu-database-register":
            case "menu-database-verify":
            case "menu-database-verifyURLs":{
                const s = actionid.slice(actionid.lastIndexOf('-') + 1);
                const actionName = 'db' + s.charAt(0).toUpperCase() + s.slice(1);
                window.hWin.HEURIST4.ui.showRecordActionDialog(actionName, popup_dialog_options);
                break;
            }
            case "menu-lookup-config":
                popup_dialog_options['classes'] = {"ui-dialog": "ui-heurist-design", "ui-dialog-titlebar": "ui-heurist-design"};
                popup_dialog_options['service_config'] = window.hWin.HAPI4.sysinfo['service_config'];
                popup_dialog_options['title'] = window.hWin.HR('Lookup service configuration');
                popup_dialog_options['path'] = 'widgets/lookup/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('lookupConfig', popup_dialog_options);
                break;
            case "menu-repository-config":
                popup_dialog_options['classes'] = {"ui-dialog": "ui-heurist-design", "ui-dialog-titlebar": "ui-heurist-design"};
                popup_dialog_options['service_config'] = window.hWin.HAPI4.sysinfo['repository_config'];
                popup_dialog_options['title'] = window.hWin.HR('Repository service configuration');
                popup_dialog_options['path'] = 'widgets/repository/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('repositoryConfig', popup_dialog_options);
                break;
            case "menu-statistics-cms":{
                let d = new Date();
                d.setDate(d.getDate() - 1);
                let yesterday = d.toISOString().split('T')[0];
                let url = `https://${window.hWin.HAPI4.sysinfo.matomo_url}/index.php?module=CoreHome&action=index&idSite=${window.hWin.HAPI4.sysinfo.matomo_siteid}&period=day&date=yesterday&updated=1#?period=week&date=${yesterday}&segment=pageUrl%3D%40%2F${window.hWin.HAPI4.database}&idSite=1&category=Dashboard_Dashboard&subcategory=1`;
                window.open(url, "_blank");
                break;
            }
            case "menu-files-index":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordUploadedFilesIndex', popup_dialog_options);
                break;
            case "menu-files-annotations":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordImportIIIF', popup_dialog_options);
                break;
            case "menu-records-archive": // not used
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordArchive');
                break;
            case "menu-import-add-record": // hidden action at the moment (for dashboard)
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');
                break;
            case "menu-structure-duplicates":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordFindDuplicates', popup_dialog_options);
                break;
            case "menu-import-get-template":
            case "menu-manage-structure-asxml":
                popup_dialog_options['path'] = 'widgets/entity/popups/';
                popup_dialog_options['format'] = actionid == 'menu-import-get-template' ? 'xml|json' : 'xml-structure';
                window.hWin.HEURIST4.ui.showRecordActionDialog('rectypeTemplate', popup_dialog_options);
                break;
            case "menu-structure-refresh":
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null);
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, window.hWin.document);
                break;

            case "menu-clear-rec-cache":
                window.hWin.HAPI4.RecordMgr.clearRecordViewCache();
                break;
                
            case "menu-profile-admin":{
            
                let url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database;
                window.open(url)
                break;
            } 
            case "menu-profile-info":
                popup_dialog_options['edit_mode'] = 'editonly';
                popup_dialog_options['rec_ID'] = window.hWin.HAPI4.user_id();
                 // fall through
            case "menu-database-properties":
            case "menu-structure-rectypes":
            case "menu-structure-fieldtypes":
            case "menu-structure-vocabterms":
            case "menu-structure-workflowstages":
            case "menu-structure-mimetypes":
            case "menu-help-bugreport":
            case "menu-profile-tags":
            case "menu-profile-reminders":
            case "menu-profile-files":
            case "menu-profile-groups":
            case "menu-profile-users":
                window.hWin.HEURIST4.ui.showEntityDialog(adata.entity, popup_dialog_options);
                break;
            case "menu-manage-dashboards":
                popup_dialog_options['isViewMode'] = false;
                popup_dialog_options['is_iconlist_mode'] = false;
                popup_dialog_options['onClose'] = function(){
                    setTimeout('$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)',1000);
                }; 
                window.hWin.HEURIST4.ui.showEntityDialog(adata.entity, popup_dialog_options);
                break;
            case "menu-database-browse":
                popup_dialog_options['select_mode'] = 'select_single';
                popup_dialog_options['onselect'] = function(event, data) {
                        if (data?.selection && data.selection.length === 1) {
                            let db = data.selection[0];
                            if(db.indexOf(window.hWin.HAPI4.sysinfo.database_prefix)==0){
                                db = db.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
                            }
                            window.open(window.hWin.HAPI4.baseURL + '?db=' + db, '_blank');
                        }
                    };
                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', popup_dialog_options);
                break;
            case "menu-structure-import":
                window.hWin.HEURIST4.ui.showWidgetDialog('importStructure', popup_dialog_options);
                break;
            case "menu-profile-preferences":
                popup_dialog_options['path'] = 'widgets/profile/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('profilePreferences', popup_dialog_options);
                break;
            case "menu-profile-import":
                this.importUsers( popup_dialog_options );
                break;
            case "menu-profile-login":
                window.hWin.HEURIST4.ui.checkAndLogin( false ); 
                break;
            case "menu-profile-logout":
                window.hWin.HAPI4.SystemMgr.logout();
                break;
            case "menu-admin-server":                                       
                popup_dialog_options['path'] = 'widgets/admin/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('manageServer', popup_dialog_options);
                break;
            case "menu-help-quick-tips":
                contentURL = window.hWin.HRes('quickTips');
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'Tips', {isPopupDlg:true, width:500, height:500});
                break;
            case "menu-help-tour":
                
                
                // A simple guided tour based on https://introjs.com/ which pops up a series of windows attached 
                // to elements of the interface. Configure the tour in /documentation/guidedIntroTour.js
                if(this.isGuidedTourLoaded && typeof startIntroTour === 'function'){
                  
                    startIntroTour()
                    //startDriverTour(); // This is an alternative tour, better features but seems unstable
    
                }else if(!this.isGuidedTourLoaded){
                    
                    this.isGuidedTourLoaded = true;
                    let that = this;
                    $.getScript(window.hWin.HAPI4.baseURL+'documentation/guidedIntroTour.js', function(){
                            startIntroTour();
                    }); 
                }
                
                break;
            case "menu-magic-tool":
                window.hWin.HEURIST4.msg.showMsg(window.hWin.HR('New_Function_Contact_Team'));
                break;
            case "menu-subset-set":{ //see menu Explore
                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                if(widget){
                    widget.resultList('callResultListMenu', 'menu-subset-set');
                }
                break;
            }
            case "menu-help-acknowledgements":
                contentURL = window.hWin.HRes('acknowledgementsHeurist');
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'Acknowledgements', {isPopupDlg:true, width:500, height:500});
                break;
            case "menu-help-about":
                contentURL = window.hWin.HRes('aboutHeurist');
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'About', {isPopupDlg:true, width:500, height:390,
                    open: function( event, ui ) {
                        let $dlg = window.hWin.HEURIST4.msg.getPopupDlg();
                        $dlg.find('.version').text('version '+window.hWin.HAPI4.sysinfo['version']+' (2026-06-30 20:00)');
                        
                        if(window.hWin.HAPI4.sysinfo.host_logo){
                            $('<div style="height:40px;padding-left:4px;float:right"><a href="'
                                +(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                                +'" target="_blank" style="text-decoration:none;color:black;">'
                                +'<label>at: &nbsp;</label>'
                                +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo+'" height="35" align="center"></a></div>')
                            .appendTo($dlg.find('div.host_info'));
                        }                       
                    }                
                });
                break;
            case "menu-help-online":
                action.href = window.hWin.HAPI4.sysinfo.referenceServerURL+'?website&db='+window.hWin.HAPI4.sysinfo.referenceServerHelpDatabase;
                 // fall through
            default:
                is_supported = this.#handleHrefAction(action, popup_dialog_options);
                break;
        }
        return is_supported;
    }
}
