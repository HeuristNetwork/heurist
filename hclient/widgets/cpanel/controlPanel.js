/**
* @file controlPanel.js
* @brief main UI widget for admin interface
* @fileOverview 
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
* @class controlPanel
* @memberof Widgets.Navigation
* @description This widget creates a control panel for the Heurist application. It handles menu creation, login actions,
* version checks, and user notifications. It also initializes the main `actionHandler` object for the application.
*
* @property {object} options - Configuration options for the widget.
*/
$.widget( "heurist.controlPanel", {

/*
    Main menu is list of Heurist operations. They are grouped in several sections:
    Admin, Database, Export, Import, Help, Profile, Management etc. 
    
    Each menu entry defined in action.json
    with the following attributes:
    id:  unique id of action  (like "id":"menu-database-clone")
    data:{
        user-admin-status - accessibility according to user level (2 db owner, 1 - db admin, 0 - logged in, -1 - all)
        logaction  - log tag 
        icon - icon in menu
        container - target element where to load dialog/form
        header  - title for container/popup dialog
        }
    text - default label in menu    
    title - default hint (mouseover) for menu item
    
    Localized versions for menu label and hint, dialog header are taken from localization files
    menu-database-clone#
    menu-database-clone-hint#
    menu-database-clone-header#
*/

    /**
    * @memberof Widgets.Navigation.controlPanel
    * @type {object}
    * @property {?string} host_logo - Path to the host logo (default: null). Not currently used.
    * @property {boolean} login_inforced - If true, forces the user to log in (default: true).
    */
    options: {
        host_logo:null, // TODO: This option is not currently used in the widget code.
        login_inforced: true,
    },

    /** 
    * @memberof Widgets.Navigation.controlPanel
    * @property {object} menues - Stores references to various menu instances created within the control panel. */
    menues:{}, // Stores references to menu widgets
    /**
     * @memberof Widgets.Navigation.controlPanel
     *  @property {?object} actionHandler - Reference to `window.hWin.HAPI4.actionHandler`. Handles actions related to menu items and user actions.*/
    actionHandler: null,

    //flags
    _initial_search_already_executed: false,
    _retrieved_notifications: false,

    /** 
     * @memberof Widgets.Navigation.controlPanel
     * @property {?(jQuery|boolean)} version_message - Stores the jQuery element for the version message or `true` if initialized.*/
    version_message: null, // container for message about available alpha/stable version

    /**
     * Initializes the control panel widget.
     * Loads the HTML content for the panel from a predefined URL and then calls `_initControls`
     * to set up the interactive elements once the content is loaded.
     * This method is called by jQuery UI when the widget is created.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
    _init: function() {

        let that = this;

        this.actionHandler = window.hWin.HAPI4.actionHandler;

        const url = window.hWin.HAPI4.baseURL
                        +'hclient/widgets/cpanel/controlPanel.html?t='
                        +window.hWin.HEURIST4.util.random();

        // Load HTML content into the widget
        this.element.load(url,
            function(response, status, xhr){
                that._need_load_content = false; // TODO: This property is set but not declared or used elsewhere.
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }else{
                    that._initControls();
                }
            });

    },

    /**
     * Sets up the visual and interactive controls for the control panel after its HTML content has been loaded.
     * This includes setting up the logo, version information, database selection dropdown,
     * and the main profile/help menu. It also binds necessary event listeners.
     * 
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
    _initControls:function(){

        let that = this;

        // Set the basic CSS for the control panel
        this.element.css('height', '100%')
            .disableSelection();// prevent double click to select text

        this.div_logo = $('div.logo');

        //validate server side version  - compare version of code in server where main index database and this server version
        let res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.version_new, window.hWin.HAPI4.sysinfo['version']);
        let sUpdate = '';
        let mr = 45;
        if(res==-2){ // -2=newer code on server
            mr = 55;
            sUpdate = '&nbsp;<span class="ui-icon ui-icon-alert" style="width:16px;display:inline-block;vertical-align: middle;cursor:pointer"></span>';
        }

        this.div_logo.find('div.version')
            .css('margin-right','-'+mr+'px')
            .html('<span>v'+window.hWin.HAPI4.sysinfo.version+sUpdate+'</span>');

        // Bind click events for version alert and reload actions
        this._on( this.div_logo, {
            click: function(event){
                if($(event.target).is('span.ui-icon-alert')){
                    window.hWin.HEURIST4.msg.showMsgDlg(
                    "Your server is running Heurist version "+window.hWin.HAPI4.sysinfo['version']+" The current stable version of Heurist (version "
                    +window.hWin.HAPI4.sysinfo.version_new+") is available from <a target=_blank href='https://github.com/HeuristNetwork/heurist'>GitHub</a> or "
                    +"<a target=_blank href='https://HeuristNetwork.org'>HeuristNetwork.org</a>. We recommend updating your copy of the software if the sub-version has changed "
                    +"(or better still with any change of version).<br><br>"
                    +"Heurist is copyright (C) 2005-2024 The University of Sydney and available as Open Source software under the GNU-GPL licence. "
                    +"Beta versions of the software with new features may also be available at the GitHub repository or linked from the HeuristNetwork home page.");
                }else{
                    //reload without query string
                    document.location = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
                    //.reload();
                }
            }
        });

        //change bg color for remote database
        this.element.addClass('ui-heurist-header2');
        if(window.hWin.HAPI4.sysinfo.database_hostname){
            this.element.css({'background-color':'rgb(128, 0, 0)'});    
        }
        
        // current and last databases dropdown
        this.div_dbname = this.element.find('div.dblist-container');

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.sysinfo.dbrecent)){

            this.div_dbname.css({
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")'});

            let wasCtrl = false;
            let selObj = window.hWin.HEURIST4.ui.createSelector(null, window.hWin.HAPI4.sysinfo.dbrecent);
            $(selObj).css({'font-size':'1em', 'font-weight':'bold','border':'none', outline: 'none',
                           'min-width':'150px', 'margin-left':'25px', })
            .on('click', function(event){
                wasCtrl = event.shiftKey;
            })
            .on('change', function(event){
                const dbname  = $(event.target).val();

                let currentDb = window.hWin.HAPI4.database;
                if(window.hWin.HAPI4.sysinfo.database_hostcode){
                    currentDb = window.hWin.HAPI4.sysinfo.database_hostcode+'-'+currentDb;
                }
                
                if(currentDb!=dbname){
                    //window.hWin.HAPI4.sysinfo.database_hostname
                    //window.hWin.HAPI4.sysinfo.database_hostcode
                    let url =  window.hWin.HAPI4.baseURL+'?db='+dbname;
                    $(event.target).val(window.hWin.HAPI4.database);
                    if(wasCtrl){
                        location.href = url;
                    }else{
                        window.open(url, '_blank');
                    }
                }
                $(event.target).trigger('blur');//remove focus
            })
            .addClass('ui-heurist-header2')
            .uniqueId()
            .appendTo( this.div_dbname );

            let currentDb = window.hWin.HAPI4.database;
            if(window.hWin.HAPI4.sysinfo.database_hostcode){
                currentDb = window.hWin.HAPI4.sysinfo.database_hostcode+'-'+currentDb;
            }
            $(selObj).val(currentDb);
            
        }else{

            $("<div>").css({'font-size':'1em', 'font-weight':'bold', 'padding-left':'22px', 'margin-left':'50px',
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")' })
            .text(window.hWin.HAPI4.database).appendTo( this.div_dbname );

        }

        // MAIN MENU-----------------------------------------------------
        this.divProfileMenu = this.element.find('div.menu-container');

        this.divProfileMenu.addClass('horizontalmenu');

        this.divProfileMenu.buttonsMenu({
            /*
           menuContent:
                    '<div>'                                                                          //margin-left:150px
                    +'<ul title="Help" link-style="width:auto;background:none;border:none;" style="" data-icon-left="ui-icon-circle-b-help">'
                    +'<li data-action="menu-help-online"/>'
                    +'<li data-action="menu-help-quick-tips"/>'
                    +'<li data-action="menu-help-website"/>'
                    +'<li data-action="menu-help-roadmap"/>'
                    +'<li data-action="menu-help-devhist"/>'
                    +'<li>---------------</li>'
                    +'<li data-action="menu-help-bugreport"/>'
                    +'<li data-action="menu-help-emailteam"/>'
                    +'<li data-action="menu-help-emailadmin"/>'
                    +'<li data-action="menu-help-acknowledgements"/>'
                    +'<li data-action="menu-help-about"/>'
                    +'</ul>'
                    +'<ul title="Profile" link-style="width:auto;background:none;border:none;" style="margin-left:30px" data-icon-left="ui-icon-user">'
                    +'<li data-action="menu-profile-preferences"/>'
                    +'<li data-action="menu-profile-tags"/>'
                    +'<li data-action="menu-profile-reminders"/>'
                    +'<li>---------------</li>'
                    +'<li data-action="menu-profile-info"/>'
                    +'<li data-action="menu-profile-groups"/>'
                    +'<li data-action="menu-profile-users"/>'
                    +'<li data-action="menu-profile-import"/>'
                    +'<li data-action="menu-profile-logout"/>'
                    +'</ul>'

           ,*/
           manuActionHandler:function(action){
                that.actionHandler.executeActionById(action);
           }
        });

        this._showVersionMessage();

        // LISTENERS --------------------------------------------------
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS
            +' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {
            that._refresh();
        });

        this._refresh();

    }, //end _initControls

// TODO: The following large block of commented-out code (formerly function ___set_menu_item_visibility)
// should be reviewed. If it's no longer needed, it should be removed.
// If it's intended for future use, it should be properly integrated or documented.
/*
        function ___set_menu_item_visibility(idx, item, is_showhide){

                let lvl_user = $(item).attr('data-user-admin-status'); //level of access by workgroup membership

                let user_req_permissions = $(item).attr('data-user-permissions');
                
                let lvl_exp = $(item).attr('data-user-experience-level');  //level by ui experience
                
                let is_visible = true;
                
                item = $(item).is('li')?$(item):$(item).parent();
                let elink = $(item).find('a');
                
                if(lvl_user>=0){  //2 database owner, 1 - memeber of Database admin
                    // lvl_user=1 is_admin
                    is_visible = (lvl_exp!=3) && window.hWin.HAPI4.has_access(lvl_user);

                    if(is_visible){
                        window.hWin.HEURIST4.util.setDisabled(elink, false);
                        item.attr('title', '');
                    }else{
                        window.hWin.HEURIST4.util.setDisabled(elink, true);
                        
                        item.attr('title', 'Only '
                        + (item.attr('data-user-admin-status')==2?'the database owner':'database managers')
                        + ' can delete all records / the database');
                    }
                }

                if(!window.hWin.HEURIST4.util.isempty(user_req_permissions) && is_visible){

                    let required = '';
                    let cur_permissions = window.hWin.HAPI4.currentUser.ugr_Permissions;

                    if(user_req_permissions.indexOf('add') !== -1 && !cur_permissions?.add){
                        required = 'create'
                    }
                    if(user_req_permissions.indexOf('delete') !== -1 && !cur_permissions?.delete){
                        required += (required !== '' ? ' and ' : '') + 'delete';
                    }

                    if(required !== ''){

                        window.hWin.HEURIST4.util.setDisabled(elink, true);
                        item.attr('title', `You do not have permission to ${required} records`);

                        is_visible = false;
                    }else{
                        window.hWin.HEURIST4.util.setDisabled(elink, false);
                        item.attr('title', '');
                    }
                }

                if(is_showhide && !is_visible){
                    item.hide();  
                }else{
                    item.show();  
                }
                
                //0 advance, 1-experienced, 2-beginner
                if(lvl_exp>=0 && is_visible){
                    
                        let usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);//beginner by default
                        
                        is_visible = (usr_exp_level<=lvl_exp);
                    
                        if(is_visible){
                            item.show();  
                        }else{
                            item.hide();  
                        }
                }         
        }
        
        //  0 - logged in                 
        //  1 - db admin (admin of group #1)
        //  2 - db owner
        //loop top level menu
        for (let key in this.menues){
            let menu = this.menues[key];
            if(menu.is('li')){
                ___set_menu_item_visibility(0, menu, true);  //top level menu - show/hide               
            }else{
                $(menu).find('li,a').each(___set_menu_item_visibility); //enable/disbale dropdown items
            }
        }
*/    
   
    /**
     * Refreshes the control panel.
     * Updates the profile menu to display the current user's name.
     * If login is enforced and the user is not logged in, it initiates the login process.
     * Otherwise, it shows version messages, performs any initial search tasks, and gets user notifications.
     * This method is called by jQuery UI and internally when user state changes.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
    _refresh: function(){

        // Replace "Profile" label for menu to current user name
        this.divProfileMenu.find('span.ui-icon-user').next().text(window.hWin.HAPI4.currentUser.ugr_FullName);

        if(this.options.login_inforced && !window.hWin.HAPI4.has_access()){
            this.doLogin();
        }else {
            this._performInitialSearch();
            this._getUserNotifications();
        }
    },

    /**
     * Cleans up the control panel widget when it is destroyed.
     * Removes event listeners and DOM elements associated with the control panel.
     * This method is called by jQuery UI when the widget is destroyed.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);

        this.div_logo.remove();
        this.divProfileMenu.remove();
        if (this.divShortcuts) { // Ensure divShortcuts exists before trying to remove
            this.divShortcuts.remove();
            this.divShortcuts = null;
        }
        if (this.version_message && this.version_message instanceof jQuery) { // Ensure version_message is a jQuery object
            this.version_message.remove();
            this.version_message = null;
        }
    },

    /**
     * Initiates the login process.
     * Uses `HEURIST4.ui.checkAndLogin` to display a login dialog.
     * If login is successful, it updates the UI with the user's name and proceeds to show version messages,
     * perform initial search, and get user notifications.
     * If login is not successful and `options.login_inforced` is true, it redirects to the base URL (login page).
     * @memberof Widgets.Navigation.controlPanel
     */
    doLogin: function(){

        let isforced = this.options.login_inforced;
        let that = this;
        window.hWin.HEURIST4.ui.checkAndLogin( isforced, function(is_logged)
            {
                if(is_logged) {
                    $(that.element).find('.usrFullName').text(window.hWin.HAPI4.currentUser.ugr_FullName);

                    // The user name in the menu is updated by _refresh -> this.divProfileMenu.find...
                    that._performInitialSearch();
                    that._getUserNotifications();

                } else if(that.options.login_inforced){
                    window.hWin.location  = window.hWin.HAPI4.baseURL;
                }
            });
    },


    /**
     * Executes an initial search or handles specific actions based on URL parameters upon application startup.
     * This can include executing a command (`cmd`), loading a saved search (`svs`), or performing a general search (`q`).
     * If no specific action is dictated by URL parameters, it may execute a default search or initialize the dashboard.
     * It ensures that this initial action is performed only once.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
    _performInitialSearch: function(){

        if(this._initial_search_already_executed){
            this._dashboardVisibility( false );
            return;
        }

        this._initial_search_already_executed = true;

        let cmd = window.hWin.HEURIST4.util.getUrlParameter('cmd', window.hWin.location.search);

        //ignore initial search of some menu command is called from url
        if(cmd){
            //executes arbitrary command
            this.actionHandler.executeActionById(cmd);
            return;
        }else if(window.hWin.HAPI4.is_publish_mode || window.hWin.HAPI4.sysinfo['db_total_records']==0){
            return;
        }

        let request = {};
        let attempt; // Declared for svsID interval logic

        const svsID = window.hWin.HEURIST4.util.getUrlParameter('svs', window.hWin.location.search);

        if(window.hWin.HAPI4.postparams?.q){
            request = window.hWin.HAPI4.postparams;
        }else if(window.hWin.HEURIST4.util.isPositiveInt(svsID)){
            attempt = 0;
            let interval = setInterval((svsID_param) => { // Renamed svsID to svsID_param to avoid conflict
                if(attempt === 5){
                    clearInterval(interval);
                    return;
                }
                try{
                    let svsWiget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                    svsWiget.svs_list('doSearchByID', svsID_param);
                    clearInterval(interval);
                }catch{
                    attempt++;
                }
            }, 500, svsID);
        }else{
            let init_search = window.hWin.HEURIST4.util.getUrlParameter('q', window.hWin.location.search);
            let qdomain;
            let rules = null;
            if(init_search){
                qdomain = window.hWin.HEURIST4.util.getUrlParameter('w', window.hWin.location.search);
                rules = window.hWin.HEURIST4.util.getUrlParameter('rules', window.hWin.location.search);
            }else{
                init_search = window.hWin.HAPI4.get_prefs('defaultSearch');
            }
            if(!qdomain) qdomain = 'a';
            request = {q: init_search, w: qdomain};
            if(rules) request['rules'] = rules;
        }

        if(!window.hWin.HEURIST4.util.isempty(request['q'])){
            request['f'] = 'map';
            request['source'] = 'init';

            setTimeout(function(){
                window.hWin.HAPI4.RecordSearch.doSearch(window.hWin.document, request);//initial search
                }, 1000);
        }else{
            //trigger search finish to init some widgets
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {recordset:null});
        }

        window.hWin.HAPI4.postparams = null;

        this._dashboardVisibility( true ); //after login

    },

    /**
     * Manages the visibility of the system dashboard.
     * Depending on user preferences (`prefs_sysDashboard`), it can show the dashboard as a popup dialog
     * or as a ribbon of shortcuts at the bottom of the control panel.
     * It also handles the creation and removal of the shortcuts ribbon (`this.divShortcuts`).
     * @memberof Widgets.Navigation.controlPanel
     * @private
     * @param {boolean} is_startup - Flag indicating if this is being called during the initial startup sequence.
     *                               This affects whether a full dashboard popup might be shown.
     */
    _dashboardVisibility: function(is_startup){

        if (window.hWin.HAPI4.is_publish_mode || window.hWin.HAPI4.sysinfo.db_has_active_dashboard==0){
            return;
        }


               let remove_ribbon = true;
               //show dashboard
               let prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_on_startup:0, show_as_ribbon:0});

               if(prefs.show_on_startup==1){
                    if(prefs.show_as_ribbon==1){
                        remove_ribbon = false;
                        if(!this.divShortcuts){
                            this.divShortcuts = $( "<div>")
                                .css({'position':'absolute', left:0, right:-2, height:'36px', bottom:0})
                                .appendTo(this.element)
                                .manageSysDashboard({is_iconlist_mode:true, isViewMode:true});
                        }else{
                            //refresh
                            this.divShortcuts.manageSysDashboard('startSearch');
                        }
                    }else if(is_startup) {
                            window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard'); //show as popup
                    }
               }

               if(remove_ribbon && this.divShortcuts){
                     this.divShortcuts.remove();
                     this.divShortcuts = null;
               }

               let that = this;

               setTimeout( function(){ that._adjustHeight(); },is_startup?1000:10);
    },

    /**
     * Adjusts the height of the layout panes (`#north_pane` and `#center_pane`)
     * based on whether the shortcuts ribbon (`this.divShortcuts`) is visible.
     * It then triggers a resize of the overall layout.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
     _adjustHeight: function(){

        let ele = this.element.parents('#layout_panes');
        if(ele.length > 0){ // Check if 'ele' exists
            let h = 50;

            if(this.divShortcuts){
                h = 100;
            }

            ele.children('#north_pane').height(h);
            ele.children('#center_pane').css({top: h});

            if($('.ui-layout-container').length>0){

                let layout = $('.ui-layout-container').layout();
                if (layout && typeof layout.resizeAll === 'function') { // Check if layout and resizeAll exist
                    layout.resizeAll();
                }
            }

        }

    },

    /**
     * Displays messages next to the database name regarding software versions and bug reporting.
     * - Shows a "Please report bugs" message with a spinning icon.
     * - If not on an alpha version, checks for and suggests switching to an available alpha version.
     * - If on an alpha version, provides information and an option to switch to the standard version,
     *   including a dialog prompting for bug reports before switching.
     * Ensures this setup is performed only once.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     */
    _showVersionMessage: function(){

        const SPIN_INTERVAL = 30000; // how often to spin - 30 seconds (not 5 minutes as per old comment)
        const SPIN_DURATION = 1000; // how long the spin takes - 1 second

        const newestVersion = 7;
        const isAlpha = window.hWin.HAPI4.baseURL.match(/h\d+-alpha|alpha/);
        const isVersion7 = window.hWin.HAPI4.baseURL.match(/heurist|heurist2025/); // @todo: remove once version 7 is standard

        if(this.version_message && this.version_message !== true && this.element.find('#heuristVersionSwapper').length === 0){ // Check if it's already a jQuery object
            return;
        }

        /**
         * Check whether the newest version of heurist is available, e.g. version 7
         * @param {string|int} version - newest version to check for 
         */
        function checkVersion(version = newestVersion){

            window.hWin.HAPI4.SystemMgr.check_for_version({a:'check_for_version', ver: version}, (response) => {

                if(window.hWin.HEURIST4.util.isempty(response.data)){
                    return;
                }

                $('<span>', {
                    title: 'Move to newest alpha version',
                    style: 'flex: 0 0 12em;',
                    html: `<a style="cursor: pointer;text-decoration: underline;" href="${response.data}?db=${window.hWin.HAPI4.database}" id="lnk_Newest">
                    Try version ${version}</a> (compatible)`
                }).appendTo(this.version_message);

                this.version_message.css('width', '55em');
            });
        }

        this.version_message = true; // Mark as initialized to prevent re-entry before elements are created

        let styling = {
            float:'left',
            'font-size': '0.85em',
            cursor:'default',
            'margin-top': '0.9em',
            'margin-left': '2em',
            display: 'flex',
            'align-items': 'center',
            width: '45em'
        };

        this.version_message = $('<div>', {id: 'heuristVersionSwapper'})
            .css(styling)
            .insertAfter(this.div_dbname);

        // Add message about reporting bugs
        let $bugMsg = $('<span>', {
            title: 'Click to submit Heurist ticket',
            style: "color: #FFFF66; cursor: pointer; flex: 0 0 20em;",
            html: '<span class="ui-icon ui-icon-bug" style="float: left;margin: 5px;"></span>Please report bugs here, or suggest improvements. We are responsive'
        }).appendTo(this.version_message);

        this._on($bugMsg, {
            click: () => window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport')
        });

        let $bugIcon = $bugMsg.find('span.ui-icon');
        const bugSpinInterval = setInterval(() => {
            $({deg: 0}).animate({deg: 360}, {
                duration: SPIN_DURATION,
                step: (rotation) => {

                    if($bugIcon.length == 0 || !$bugIcon.is(':visible')){ // Stop if icon is removed or hidden
                        clearInterval(bugSpinInterval);
                        return;
                    }

                    $bugIcon.css('transform', `rotate(${rotation}deg)`);
                }
            });
        }, SPIN_INTERVAL);

        if(!isAlpha){ // need to check that an alpha version is available on this server

            window.hWin.HAPI4.SystemMgr.check_for_version({a:'check_for_version'}, (response) => {

                if(window.hWin.HEURIST4.util.isempty(response.data)){
                    return;
                }

                $('<span>', {
                    title: 'Move to alpha version',
                    style: 'flex: 0 0 22em;',
                    html: `<a style="cursor: pointer;text-decoration: underline;" href="${response.data}?db=${window.hWin.HAPI4.database}" id="lnk_Change">
                    Use the latest (alpha) version</a> (recommended)`
                }).appendTo(this.version_message);

                if(!isVersion7){
                    checkVersion.call(this, newestVersion);
                }
            });
        }else{ // currently on alpha

            const width = navigator.userAgent.indexOf('Firefox') >= 0 ? '24.5' : '24';
            $('<span>', {
                title: 'Go to standard version',
                style: `flex: 0 0 ${width}em; padding-right: 1em;`,
                html: `This is the latest (alpha) version. If you are blocked by a new bug you can switch to the 
                <a style="cursor: pointer;text-decoration: underline;" href="#" id="lnk_Change">standard version</a>`
            }).appendTo(this.version_message);

            this._on(this.version_message.find('#lnk_Change'), {
                click: () => {

                    let $dlg;
                    let msg = 'If you encounter <span style="text-decoration: underline">any</span> bug, we ask that you report it with <a href="#" id="msg_bug_rpt">Create ticket</a>'
                        + ' - bugs are generally<br>'
                        + 'fixed within a day or so.<br><br>'
                        + 'We recommend that you use the alpha version unless you encounter a newly introduced bug<br>'
                        + 'which blocks your work, in which case you can revert to the standard (/heurist/) version.<br><br>'
                        + 'We recommend that you return to using the alpha version as soon as the bug is fixed (you<br>'
                        + 'should receive an advice email, otherwise switch back in a couple of days).';

                    let btns = {};
                    btns['Continue using alpha version'] = () => {
                        $dlg.dialog('close');
                    };
                    btns['Report bug and switch to standard version'] = () => {
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport', {
                            onClose: () => {
                                location.href = `${window.hWin.HAPI4.baseURL_pro}?db=${window.hWin.HAPI4.database}`;
                            }
                        });
                    };

                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Thanks for helping to test Heurist'}, {default_palette_class: 'ui-heurist-admin', dialogId: 'heurist-versions'});

                    $dlg.find('#msg_bug_rpt').on('click', function(){ // same action as proceed button
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport', {
                            onClose: () => {
                                location.href = `${window.hWin.HAPI4.baseURL_pro}?db=${window.hWin.HAPI4.database}`;
                            }
                        });
                    });
                }
            });

            if(!isVersion7){
                checkVersion.call(this, newestVersion);
            }
        }
    },

    /**
     * Retrieves and handles user-specific notifications from the server.
     * Currently, if a 'bug_report' notification is received, it triggers the bug report dialog.
     * Ensures this is done only once per widget instance.
     * @memberof Widgets.Navigation.controlPanel
     * @private
     * @returns {void}
     */
    _getUserNotifications: function(){

        if(this._retrieved_notifications){ return; }

        this._retrieved_notifications = true;

        let request = {
            a: 'get_user_notifications'
        };

        window.hWin.HAPI4.SystemMgr.get_user_notifications(request, function(response){

            if(window.hWin.HEURIST4.util.isempty(response.data) || response.status != window.hWin.ResponseStatus.OK){
                return;
            }

            let notifications = response.data;

            // If the only notification is 'bug_report', open the bug reporter.
            // This implies a periodic prompt for bug reports (e.g., monthly).
            if(Object.keys(notifications).length == 1 && notifications['bug_report']){
                window.hWin.HAPI4.actionHandler.executeActionById('menu-help-bugreport');
            }
        });
    }

});

