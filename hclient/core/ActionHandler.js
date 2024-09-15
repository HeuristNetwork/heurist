/**
*  appInitAll - main function which initialises everything
*
*  @see ext/layout
*  @see layout_defaults.js - configuration file
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


class ActionHandler {
    
    constructor(actions) {
        this.actions = actions;  // Store the actions from the JSON
        if(!Array.isArray(actions)){
            this.actions = this.loadActionsFromFile();
        }
    }
    
    // Method to fetch and load actions from a remote JSON file
    async loadActionsFromFile() {
        try {
            const url = window.hWin.HAPI4.baseURL + 'hclient/core/actions.json';
            const response = await fetch(url);
            // or $.getJSON(url, function(res){  this.actions =  res;  });

            if (!response.ok) {
                throw new Error(`Failed to load actions from ${url}: ${response.statusText}`);
            }

            this.actions = await response.json();
            //console.log("Actions loaded successfully:", this.actions);

        } catch (error) {
            console.error("Error loading actions:", error);
        }
    }
    

    // Method to get all available actions
    getActions() {
        console.log("Available actions:");
        this.actions.forEach(action => {
            console.log(`ID: ${action.id}, Text: ${action.text}`);
        });
    }

    // Method to find an action by id
    findActionById(id) {
        return this.actions.find(action => action.id === id);
    }

    // Method to execute an action by id
    executeActionById(id, dialog_options) {
        const action = this.findActionById(id);
        if (!action) {
            console.log(`Action with ID "${id}" not found.`);            
            return;
        }
        
        let adata = action?.data;
    
        // Check if the action is disabled
        if (adata?.ext == 1) {
            return;
        }
        
        if(!dialog_options){
            dialog_options = {};
        }
        
        dialog_options.verification_passed = true; //bypass
        
        if(!dialog_options?.verification_passed && adata){
            
            // is passworded
            let action_passworded = adata?.pwd;
            if (!action_passworded && !window.hWin.HAPI4.has_access(2)) {
                action_passworded = adata['pwd-nonowner'];
            }
            
            // requires admin level in certain group
            let action_admin_level = adata['user-admin-status']; 
            // requires memeberlevel in certain group
            let action_member_level = adata['user-memebr-status']; 
            // certain actions: add delete edit
            let action_user_permissions = adata['user-permissions']; 
            
            // Determine the required level of access
            let requiredLevel = (action_level == -1 || action_level >= 0) ? action_level : 0;
            
            
            if(action_passworded || requiredLevel>0){ 

                    if (action_member_level > 0) {
                        requiredLevel += ';' + action_member_level;
                    }
                
                    window.hWin.HAPI4.SystemMgr.verify_credentials(entered_password=>{
                        dialog_options.entered_password = entered_password;
                        dialog_options.verification_passed = true;
                        this.executeActionById(id, dialog_options);              
                    },
                    requiredLevel, action_passworded, null, action_user_permissions);
                    
                    return;
            }
        }//verification_passed
       
        let actionid = action?.id;
        let action_log = adata?.logaction; 
        let action_container = adata?.container; //target container
        
        if (action_log) {
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }        
        
        // Prepare entity and popup dialog options
        let container, menu_container;
        
        if (dialog_options?.container) {
            container = dialog_options['container'];  //selector
        } else if (action_container) {
            let section = action_container;
            // find global widget
            // Activate the specified menu and container
            $('.ui-menu6').mainMenu6('switchContainer', section, true);
            
            container = $('.ui-menu6 > .ui-menu6-widgets.ui-heurist-'+section);
            container.removeClass('ui-suppress-border-and-shadow');
            
            menu_container = $('.ui-menu6 > .ui-menu6-section.ui-heurist-' + section);
            menu_container.find('li').removeClass('ui-state-active');
            menu_container.find('li[data-action="' + actionid + '"]').addClass('ui-state-active');
        }

        let pos = dialog_options?.position || null;

        let entity_dialog_options = {
                isdialog: !container,
                innerTitle: true,
                isFrontUI: true,
                menu_container: menu_container,
                container: container
            };

        let popup_dialog_options = {
                title: window.hWin.HR(actionid+'-title'),
                innerTitle: true,
                is_h6style: true,
                isdialog: !container,
                resizable: false,
                draggable: false,
                menu_container: menu_container,
                container: container,
                position: pos,
                maximize: true,
            };

            if (dialog_options?.record_id > 0) {
                popup_dialog_options.record_id = dialog_options['record_id'];
            }
        
        popup_dialog_options = $.extend(dialog_options, popup_dialog_options);


//Create New Database
//Restore Database            
//Delete Database    
//Clear Database
//Rename Database    
//Clone Database 
//Register database 
//Verify Database Integrity  

        let is_supported = true;
        
        switch (actionid) {
            //action dialogs
            case "menu-database-create":
            case "menu-database-restore":
            case "menu-database-delete":
            case "menu-database-clear":
            case "menu-database-rename":
            case "menu-database-clone":
            case "menu-database-register":
            case "menu-database-verify":
            
                const s = actionid.substr(actionid.lastIndexOf('-')+1);
                const actionName = 'db'+s.charAt(0).toUpperCase() + s.slice(1);;
                window.hWin.HEURIST4.ui.showRecordActionDialog(actionName, popup_dialog_options);
                break;
            
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
                popup_dialog_options['path'] = 'widgets/admin/';

                window.hWin.HEURIST4.ui.showRecordActionDialog('repositoryConfig', popup_dialog_options);
                break;
                
            case "menu-records-archive":  // find invocation
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordArchive');
                break;
            case "menu-import-add-record": // hidden action at the moment (for dashboard)
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');
                break;
            case "menu-structure-duplicates":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordFindDuplicates', popup_dialog_options);
                break;
                
            case "menu-structure-refresh":
            
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, window.hWin.document);
                break;

            case "menu-database-properties":
            case "menu-structure-rectypes":
            case "menu-structure-fieldtypes":
            case "menu-structure-vocabterms":
            case "menu-structure-workflowstages":
            case "menu-structure-mimetypes":
            
                window.hWin.HEURIST4.ui.showEntityDialog(adata.entity, entity_dialog_options);
                break;
                
            case "menu-database-browse":
                let options = $.extend(entity_dialog_options, {
                    select_mode: 'select_single',
                    onselect: function(event, data) {
                        if (data && data.selection && data.selection.length === 1) {
                            let db = data.selection[0];
                            if (db.indexOf('hdb_') === 0) db = db.substr(4);
                            window.open(window.hWin.HAPI4.baseURL + '?db=' + db, '_blank');
                        }
                    }
                });
                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);
                break;

                
            case "menu-structure-import":

                window.hWin.HEURIST4.ui.showWdigetDialog('importStructure', popup_dialog_options);
                break;
                
            //case "menu-cms-create":
            //    //this._handleCMSCreate(popup_dialog_options);
                break;

           

            default:
                // Handle the case where the action is a link or needs to open a dialog
                const href = action.href;// action?.href;
                const target = action?.target;
                
                if (!window.hWin.HEURIST4.util.isempty(href) && href !== '#') {
                    this._handleHrefAction(action, href, target, popup_dialog_options);
                }else{
                    is_supported = false;
                }
                break;
        }
  
        if(is_supported){
            console.log("Action executed successfully:", actionid);
        }else{
            console.log("Action not supportede:", actionid);
        }
      
        return is_supported;
    }
    
    
    // Helper method to handle link-based actions
    _handleHrefAction(action, href, target, popup_dialog_options) {
        if (href.indexOf('mailto:') === 0) {
            window.open(href, 'emailWindow');
            return;
        }

        if (!(href.indexOf('http://') === 0 || href.indexOf('https://') === 0)) {
            href = window.hWin.HAPI4.baseURL + href + (href.indexOf('?') >= 0 ? '&' : '?') + 'db=' + window.hWin.HAPI4.database;
        }

        if (target) {
            window.open(href, target);
        } else {
            let options = $.extend(popup_dialog_options, { width: 800, height: 600 });
            window.hWin.HEURIST4.msg.showDialog(href, options);
        }
    }
    
}

/*
// Instantiate the class
const actioHandler = new ActionHandler(actions);

// Load and display available actions
actioHandler.loadActions();

// Execute an action by its ID
actioHandler.executeActionById("menu-structure-refresh");  // Test by ID

// Execute another action
actioHandler.executeActionById("menu-interact-log");
*/