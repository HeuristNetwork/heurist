/**
*  ActionHandler
*
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
    
    constructor(arg) {
        if(Array.isArray(arg)){
            this.actions = actions;
        }else{
            const baseURL = arg?arg:window.hWin.HAPI4.baseURL;    
            const url = baseURL + 'hclient/core/actions.json';
            this.actions = this.loadActionsFromFile(url);
        }
    }
    
    // Method to fetch and load actions from a remote JSON file
    async loadActionsFromFile(url) {
        try {
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
        
        //temp bypass dialog_options.verification_passed = true;
        
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
            let requiredLevel = (action_admin_level == -1 || action_admin_level >= 0) ? action_admin_level : 0;
            
            
            if(action_passworded || requiredLevel>0){ 

                    if (action_member_level > 0) {
                        requiredLevel += ';' + action_member_level;
                    }
                
                    window.hWin.HAPI4.SystemMgr.verify_credentials((entered_password)=>{
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
        
        if (actionid.indexOf('menu-cms')==0){
            if(!this.cmsManager){
                this.cmsManager = new CmsManager();
            }
            this.cmsManager.executeAction(actionid);
            return true;
        }
        
        
        // Prepare entity and popup dialog options
        let container, menu_container;
        
        if (dialog_options?.container) {
            container = dialog_options['container'];  //selector
        } else if (action_container) {
            let section = action_container;
            // find global widget
            // Activate the specified menu and container
            $('.ui-menu6').slidersMenu('switchContainer', section, true);
            
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
        //caption for dialog/panel
        if(window.hWin.HR(actionid+'-header')!=actionid+'-header'){
            popup_dialog_options.title = window.hWin.HR(adata.header?adata.header:action.text);
        }
        
        popup_dialog_options = $.extend(dialog_options, popup_dialog_options);


        let is_supported = true;
        let contentURL;
        
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
                const actionName = 'db'+s.capitalize();
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
                
            case "menu-records-archive":  // not used
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordArchive');
                break;
            case "menu-import-add-record": // hidden action at the moment (for dashboard)
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');
                break;
            case "menu-structure-duplicates":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordFindDuplicates', popup_dialog_options);
                break;
            case "menu-import-get-template":
                popup_dialog_options['path'] = 'widgets/entity/popups/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('rectypeTemplate', popup_dialog_options);
                break;
                
            case "menu-structure-refresh":
            
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, window.hWin.document);
                break;

            case "menu-profile-info":
                entity_dialog_options['edit_mode'] = 'editonly';
                entity_dialog_options['rec_ID'] = window.hWin.HAPI4.user_id();
                
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
                window.hWin.HEURIST4.ui.showEntityDialog(adata.entity, entity_dialog_options);
                break;
            case "menu-manage-dashboards":
                entity_dialog_options['isViewMode'] = false;
                entity_dialog_options['is_iconlist_mode'] = false;
                entity_dialog_options['onClose'] = function(){
                    setTimeout('$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)',1000);
                }; 
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
                
            case "menu-profile-preferences":
                popup_dialog_options['path'] = 'widgets/profile/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('profilePreferences', popup_dialog_options);
                break;
            case "menu-profile-import":
                this._importUsers( entity_dialog_options ); //for admin only
                break;
            case "menu-profile-logout":
                window.hWin.HAPI4.SystemMgr.logout();
                break;
            case "menu-admin-server":                                       
                popup_dialog_options['path'] = 'widgets/admin/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('manageServer', popup_dialog_options);
                break;
/* NOT USED. At the moment it rebuilds titles for entire database or per rty after titlemask edit
            case "menu-manage-rectitles":                                       
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordsTitles', popup_dialog_options);
                break;
*/

            case "menu-help-quick-tips":
                contentURL = window.hWin.HAPI4.baseURL+'context_help/quickTips.html';
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'Tips', {isPopupDlg:true, width:500, height:500});
                break;
           
            case "menu-help-acknowledgements":
            
                contentURL = window.hWin.HAPI4.baseURL+'context_help/acknowledgementsHeurist.html';
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'Acknowledgements', {isPopupDlg:true, width:500, height:500});
                break;

            case "menu-help-about":

                contentURL = window.hWin.HAPI4.baseURL+'context_help/aboutHeurist.html';
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'About', {isPopupDlg:true, width:500, height:390,
                    open: function( event, ui ) {
                        let $dlg = window.hWin.HEURIST4.msg.getPopupDlg();
                        $dlg.find('.version').text('version '+window.hWin.HAPI4.sysinfo['version']);
                        
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
            
                action.href = window.hWin.HAPI4.sysinfo.referenceServerURL+'?db=Heurist_Help_System&website';
                

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
            
            if(!popup_dialog_options.title){
                popup_dialog_options.title = action.text;
            }
            let options = $.extend(popup_dialog_options, { width: 800, height: 600 });
            
/*                if (item.hasClass('upload_files')) {
                    //beforeClose
                    options['afterclose'] = function( event, ui ) {

                            if(window.hWin.HEURIST4.filesWereUploaded){
                                
                                let buttons = {};
                                buttons[window.hWin.HR('OK')]  = function() {
                                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                    $dlg.dialog( "close" );
                                        
                                    that.actionHandler.executeActionById('menu-index-files');
                                };                                 
                                
                                window.hWin.HEURIST4.msg.showMsgDlg('The files you have uploaded will not appear as records in the database'
                                +' until you run Import > index multimedia This function will open when you click OK.',buttons);
                            }
                    }
                }
*/
            
            
            window.hWin.HEURIST4.msg.showDialog(href, options);
        }
    }
    
    //
    // @todo - fix layeours for select user and groups (disable edit,delete)
    //
    _importUsers( entity_dialog_options ){
        
        if(!entity_dialog_options) entity_dialog_options = {};
        
        let options = $.extend(entity_dialog_options, {
            subtitle: 'Step 1. Select database with users to be imported',
            title: 'Import users', 
            select_mode: 'select_single',
            pagesize: 300,
            edit_mode: 'none',
            use_cache: true,
            except_current: true,
            keep_visible_on_selection: true,
            onselect:function(event, data){
                if(data && data.selection && data.selection.length>0){
                        let selected_database = data.selection[0].substr(4);
                        
                        let options2 = $.extend(entity_dialog_options, {
                            subtitle: 'Step 2. Select users in '+selected_database+' to be imported',
                            title: 'Import users', 
                            database: selected_database,
                            select_mode: 'select_multi',
                            edit_mode: 'none',
                            keep_visible_on_selection: true,
                            onselect:function(event, data){
                                if(data && data.selection &&  data.selection.length>0){
                                    let selected_users = data.selection;

                                    let options3 = $.extend(entity_dialog_options, {
                                        subtitle: 'Step 3. Allocate imported users to work groups',
                                        title: 'Import users', 
                                        select_mode: 'select_roles',
                                        selectbutton_label: 'Allocate roles',
                                        sort_type_int: 'recent',
                                        edit_mode: 'none',
                                        keep_visible_on_selection: false,
                                        onselect:function(event, data){
                                            if(data && !$.isEmptyObject(data.selection)){
                                                //selection is array of object
                                                // [grp_id:role, ....]
                                                /*
                                                let s = '';
                                                for(grp_id in data.selection)
                                                if(grp_id>0 && data.selection[grp_id]){
                                                    s = s + grp_id+':'+data.selection[grp_id]+',';
                                                }
                                                if(s!='')
                                                    alert( selected_database+'  '+selected_users.join(',')
                                                        +' '+s);  
                                                */        
                                                        
                                            let request = {};
                                            request['a']         = 'action';
                                            request['entity']    = 'sysUsers';
                                            request['roles']     = data.selection;
                                            request['userIDs']   = selected_users;
                                            request['sourceDB']  = selected_database;
                                            request['request_id'] = window.hWin.HEURIST4.util.random();

                                            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                                function(response){             
                                                    if(response.status == window.hWin.ResponseStatus.OK){
                                                        window.hWin.HEURIST4.msg.showMsgDlg(response.data);      
                                                    }else{
                                                        window.hWin.HEURIST4.msg.showMsgErr(response);      
                                                    }
                                            });
                                                        
                                            }
                                        }
                                    });              
                                    
                                    window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options3);
                                }
                            }
                        });
                        
                        
                        window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options2);
                }
            }
        });    
    
        window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);
    }    
    
}