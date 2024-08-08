/**
* mainMenu.js : Top Main Menu panel
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

/*

    Main menu is list of Heurist operations. They are grouped in several sections:
    Admin, Database, Export, Import, Help, Profile, Management etc. Each menu entry defined as <li> element
    with the following attributes:
    data-user-admin-status - accessibility according to user level (2 db owner, 1 - db admin, 0 - logged in, -1 - all)
    data-logaction  - log tag 
    data-icon - icon in menu
    data-container - target element where to load dialog/form
    
    Action is defined by "id" attribute (like id="menu-database-clone")
    
    Menu Title and Hint are taken from localization files via id (dashes are replace to underscores: menu_database_clone).
    If there is not localised version it takes title and hint from <li><a>
    
    Main menu can be visible as standard horizontal menu (as in previous layout) or can be hidden. 
    Even if it is hidden, this widget is main hadler for execution of operation via methods: menuActionById or menuActionHandler.
    Other widgets, dialogs and functions (for example: menu v6, dashboard, export menu) calls Heurist actions via this widget.
    
    For example, the new menu groups the actions in different groups and in different order, however it uses menu actions id and 
    calls this widget methods to execute an operation.

    menuGetAllActions - returns array of {key:id,title:topmenu>name}
    menuActionById - finds and executes menu entry by id
    menuActionHandler - main event handler
    menuGetActionLink - returns link 
*/

/* global initProfilePreferences,doRegister */ 

$.widget( "heurist.mainMenu", {

    // default options
    options: {
        host_logo:null,
        login_inforced: true,
        is_h6style: false
    },
    
    menues:{},
    cms_home_records_count:0,
    cms_home_private_records_ids:0,
    sMsgCmsPrivate:'',

    _current_query_string:'',
    
    _initial_search_already_executed: false,
    _rendered_db_overview: false,
    _retrieved_notifications: false,

    version_message: null, // container for message about available alpha/stable version

    // the widget's constructor
    _create: function() {

        let that = this;
        
        this.options.is_h6style = (window.hWin.HAPI4.sysinfo['layout']=='H6Default');

        this.element.css({'height':'100%'}).addClass('ui-heurist-header2')
            .disableSelection();// prevent double click to select text

        this.div_logo = $( "<div>")
        .addClass('logo')   //width was 198
        .css({'width':'150px', 'float':'left', 'margin':'6px 10px', cursor:'pointer'}) //'height':'56px', 
        .attr('title', 'Click to reload page and return to the default search for your database')
        .appendTo( this.element );

        if(window.hWin.HAPI4.get_prefs('layout_theme')!='heurist'){
            this.div_logo.button();
        }

        //validate server side version  - compare version of code in server where main index database and this server version
        let res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.version_new, window.hWin.HAPI4.sysinfo['version']);   
        let sUpdate = '';
        let mr = 45;
        if(res==-2){ // -2=newer code on server
            mr = 55;
            sUpdate = '&nbsp;<span class="ui-icon ui-icon-alert" style="width:16px;display:inline-block;vertical-align: middle;cursor:pointer">';
        }

        this.div_version = $("<div>")                                               
            .html('<span>v'+window.hWin.HAPI4.sysinfo.version+sUpdate+'</span>')
            .appendTo( this.div_logo );
        if(this.options.is_h6style){
            this.div_version.css({'font-size':'0.5em', color:'#DAD0E4', 'text-align':'right', 
                'padding-top':'12px', 'margin-right':'-'+mr+'px'});
                
        }else{
            this.div_version.css({'font-size':'0.6em', 'text-align':'center', 'margin-left': '85px', 'padding-top':'12px', 
                'padding-left':'20px', 'width':'100%'});
        }
        // bind click events
        this._on( this.div_logo, {
            click: function(event){
                if($(event.target).is('span.ui-icon-alert')){
                    window.hWin.HEURIST4.msg.showMsgDlg(                    
                    "Your server is running Heurist version "+window.hWin.HAPI4.sysinfo['version']+" The current stable version of Heurist (version "
                    +window.hWin.HAPI4.sysinfo.version_new+") is available from <a target=_blank href='https://github.com/HeuristNetwork/heurist'>GitHub</a> or "
                    +"<a target=_blank href='https://HeuristNetwork.org'>HeuristNetwork.org</a>. We recommend updating your copy of the software if the sub-version has changed "
                    +"(or better still with any change of version).<br><br>"
                    +"Heurist is copyright (C) 2005-2023 The University of Sydney and available as Open Source software under the GNU-GPL licence. "
                    +"Beta versions of the software with new features may also be available at the GitHub repository or linked from the HeuristNetwork home page.");
                }else{
                    //reload without query string
                    document.location = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
                    //.reload();    
                }
            }
        });


        this.divProfileMenu = $( "<div>")
        .css({'float':'right', 'margin-top':'1em', 'font-size':'1.1em'})  //one rows 'padding-right':'2em', 
        //.css({'position':'absolute', 'right':10, 'padding-right':'2em', 'padding-top':'1em' })  //one rows
        //.addClass('logged-in-only')
        .appendTo(this.element);

        //dashboard button                
        this.btn_dashboard = $('<div>').button({label:'Open dashboard'})
        .css({'float':'right', margin:'1.1em', 'font-size':'0.9em'})
        .addClass('ui-heurist-header2')
        .appendTo( this.element )
        .click(
            function(){
                that.btn_dashboard.hide();

                let prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_on_startup:0, show_as_ribbon:0});
                prefs['show_on_startup'] = 1;
                window.hWin.HAPI4.save_pref('prefs_sysDashboard', prefs);     
                
                that.menuActionById('menu-manage-dashboards');
            }
        ); 

        
        this.div_dbname = $( "<div>")
            //.css({'float':'right', 'margin-top':'1.2em', 'padding-right':'2em' })
            .css({'float':'left', 'margin-top':'0.9em', 'margin-left':'5em'})
            .appendTo(this.element);
            
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.sysinfo.dbrecent)){
            
            this.div_dbname.css({
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")'});
            
            let wasCtrl = false;
            let selObj = window.hWin.HEURIST4.ui.createSelector(null, window.hWin.HAPI4.sysinfo.dbrecent);        
            $(selObj).css({'font-size':'1em', 'font-weight':'bold','border':'none', outline:0,
                           'min-width':'150px', 'margin-left':'25px', })
            .on('click', function(event){
                wasCtrl = event.shiftKey;
            })
            .on('change', function(event){
                if(window.hWin.HAPI4.database!=$(event.target).val()){
                    let url =  window.hWin.HAPI4.baseURL+'?db='+$(event.target).val();
                    $(event.target).val(window.hWin.HAPI4.database);
                    if(wasCtrl){
                        location.href = url;
                    }else{
                        window.open(url, '_blank');
                    }
                }
                $(event.target).blur();//remove focus
            })
            .addClass('ui-heurist-header2')
            .uniqueId()
            .val( window.hWin.HAPI4.database ).appendTo( this.div_dbname );
        }else{
            
            $("<div>").css({'font-size':'1em', 'font-weight':'bold', 'padding-left':'22px', 'margin-left':'50px',
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")' })
            .text(window.hWin.HAPI4.database).appendTo( this.div_dbname );
            
        }

        /*$("<span>")
            .addClass('ui-icon ui-icon-database') //    .css({'margin-left':'50px'})
            .appendTo( this.div_dbname );

        $("<span>").text(window.hWin.HAPI4.database)
            .css({'font-size':'1.3em', 'font-weight':'bold', 'padding-left':'22px'})
            .appendTo( this.div_dbname );*/
            
        // MAIN MENU-----------------------------------------------------
        let he = this.element.height();

        this.divMainMenu = $( "<div>")
            .css({'position':'absolute', 'left':24, bottom:he/8, 'text-align':'left'})  //one rows
            //.addClass('logged-in-only')
            .appendTo(this.element);
        
        this.divMainMenuItems = $('<ul>')
                .addClass('horizontalmenu')
                .css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em', 'font-size':'0.9em'})
                .appendTo( this.divMainMenu );

        /* new entityfeatures*/
        this.divProfileItems = $( "<ul>")
                .css('float','right')
                .addClass('horizontalmenu')
                .appendTo( this.divProfileMenu );
        
        //check if menu among allowed topis  - they can be set via layout 
        function __include(topic){
             return (!that.options.topics || that.options.topics.indexOf(topic.toLowerCase())>=0);
        }
        if(this.options.is_h6style){
            if(__include('Help')) this._initMenu('Help', -1, this.divProfileItems);
        }
        if(__include('profile')) this._initMenu('Profile', -1, this.divProfileItems);

        if(__include('Database')) this._initMenu('Database', -1);            
        if(__include('Structure')) this._initMenu('Structure', 0, null, 3); //3 means hidden
        if(__include('Verify')) this._initMenu('Verify', 0);
        if(__include('Import')) this._initMenu('Import', 0);
        if(__include('Website')) this._initMenu('Website', 0);
        if(__include('Export')) {
            this._initMenu('Export', 2, null, 3); //invisible in main menu   
            //this.menues['btn_Export'].hide();
        }
            
        if(__include('Management')) this._initMenu('Management', 0);
        
        if(__include('Admin')) this._initMenu('Admin', 0, null, 0);
        
        if(__include('FAIMS')) this._initMenu('FAIMS', 1, null, 1);
        
        if(!this.options.is_h6style){
            if(__include('Help')) this._initMenu('Help', -1);    
        }
        
        
        this.divMainMenuItems.menu();
        this.divProfileItems.menu().removeClass('ui-menu-icons');
            
        // Dashboard - shortcuts ---------------------------------------
        if(this.options.is_h6style){
/*            
            //show dashboard as a ribbon
            this.divShortcuts = $( "<div>")            
                .css({'position':'absolute', left:0, right:0, height:'36px', bottom:-5})
                .appendTo(this.element)
                .manageSysDashboard({is_iconlist_mode:true});
*/                
            this.divMainMenu.hide();
        }
            

        //host logo and link -----------------------------------    
        if(window.hWin.HAPI4.sysinfo.host_logo){
            
            $('<div style="height:40px;background:none;padding-left:4px;float:right;color:white">'
                +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                +'" target="_blank" style="text-decoration: none;color:white;">'
                        +'<span>hosted by: </span>'
                        +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                        +'" height="40" align="center"></a></div>')
            .appendTo( this.divMainMenu );
        }
        
        // LISTENERS --------------------------------------------------
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART,
            function(e, data) {
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                    if(data && !data.search_realm) {
                        let query_request = data;

                        that._current_query_string = '&w='+query_request.w;
                        if(!window.hWin.HEURIST4.util.isempty(query_request.q)){
                            that._current_query_string = that._current_query_string
                            + '&q=' + encodeURIComponent(query_request.q);
                        }
                    }
                }
        });

        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS
            +' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {
            that._refresh();
        });

        this._refresh();

    }, //end _create

    _getCountWebPageRecords: function(callback){
    
        let RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
        DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
        let query_search_pages = {t:RT_CMS_MENU}
        query_search_pages['f:'+DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];
    
        let request = {q:query_search_pages, w: 'a', detail: 'count', source:'_getCountWebPageRecords' };
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                callback.call(this, response.data.count);
            }
        });
        
    },
    
     //
     //
     //   
    _getCountWebSiteRecords: function(callback){
        let RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        if(RT_CMS_HOME>0){
            let request = {
                    'a'       : 'counts',
                    'entity'  : 'defRecTypes',
                    'mode'    : 'cms_record_count',
                    'ugr_ID'  : window.hWin.HAPI4.currentUser['ugr_ID'],
                    //'rty_ID'  : RT_CMS_HOME
                    };
            let that = this;        
                                    
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.cms_home_records_count = response.data['all'];
                        let aPriv = response.data['private'];

                        if(aPriv.length>0){
                            let cnt_home = response.data['private_home'];
                            let cnt_menu = response.data['private_menu'];
                            let s1 = '';                          
                            if(cnt_home>0){
                                if(cnt_home==1){
                                    that.cms_home_private_records_ids = response.data['private_home_ids'][0];
                                    s1 = '<p>Note: CMS website record is non-public. This website is not visible to the public.</p>';        
                                }else{
                                    s1 = '<p>Note: There are '+cnt_home+' non-public CMS website records. These websites are not visible to the public.</p>';
                                }
                            }            
                            
                            that.sMsgCmsPrivate = 
                            '<div style="margin-top:10px;padding:4px">'
+s1                            
+((cnt_menu>0)?('<p>Warning: There are '+cnt_menu+' non-public CMS menu/page records. Database login is required to see these pages in the website.'):'')                            
                            
                            /*+'This database has '+aPriv.length
                            +' CMS records which are hidden from public view - '
                            +'parts of your website(s) will not be visible to visitors '
                            +'who are not logged in to the database'*/
                            
                            +'<br><br>'
                            +'<a target="_blank" href="'+window.hWin.HAPI4.baseURL
                            +'?db='+window.hWin.HAPI4.database+'&q=ids:'+aPriv.join(',')+'">Click here</a>'
                            +' to view these records and set their visibility '
                            +'to Public (use Share > Ownership/Visibility)';
                        }else{
                            that.sMsgCmsPrivate = '';
                        }
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback(that);
                    }
                });
        }else{
            this.cms_home_records_count = 0;
            this.cms_home_private_records_ids = 0;
            if(window.hWin.HEURIST4.util.isFunction(callback)) callback(this);
        }   
    },
    

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

    },


    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },



    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        let that = this;

        /*
        if(window.hWin.HAPI4.has_access()){
            $(this.element).find('.logged-in-only').show();
            $(this.element).find('.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
            $(this.element).find('.logged-out-only').show();
        }*/
        
        function ___set_menu_item_visibility(idx, item, is_showhide){

                let lvl_user = $(item).attr('data-user-admin-status'); //level of access by workgroup membership

                let user_req_permissions = $(item).attr('data-user-permissions');
                
                let lvl_exp = $(item).attr('data-user-experience-level');  //level by ui experience
                
                let is_visible = true;
                
                item = $(item).is('li')?$(item):$(item).parent();
                let elink = $(item).find('a');
                
                if(lvl_user>=0){  //2 database owner, 1 - memeber of Database admin
                    //@todo lvl_user=1 is_admin
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
        for (let key in this.menues){
            let menu = this.menues[key];
            if(menu.is('li')){
                ___set_menu_item_visibility(0, menu, true);  //top level menu - show/hide               
            }else{
                $(menu).find('li,a').each(___set_menu_item_visibility); //enable/disbale dropdown items
            }
        }
            
            


        /* new
        if(window.hWin.HAPI4.is_admin()){
            this.menu_Profile.find('.admin-only').show();
        }else{
            this.menu_Profile.find('.admin-only').hide();    
        }
        */
        
        $(this.element).find('.usrFullName').text(window.hWin.HAPI4.currentUser.ugr_FullName);

        if(window.hWin.HAPI4.sysinfo.db_has_active_dashboard>0 && !this.options.is_h6style){
            this.btn_dashboard.show();  
        }else{
            this.btn_dashboard.hide();  
        }
        
        if(this.options.login_inforced && !window.hWin.HAPI4.has_access()){
            this.doLogin();
        }else {
            this._show_version_message();
            this._performInitialSearch();
            this._getUserNotifications();

            // Setup user favourite filters, give mainMenu time to load completely
            setTimeout(function(){ $('.ui-menu6').mainMenu6('populateFavouriteFilters'); }, 2000);

            let query = window.hWin.location.search.substring(1);
            let vars = query.split('&');
            //!window.hWin.HEURIST4.util.getUrlParameter('nometadatadisplay', window.hWin.location.search) 
            if( vars && vars.length==1
				&& window.hWin.HAPI4.sysinfo['db_total_records']>0){
                // Wait a bit for the main menu to be initialised
                setTimeout(function(){
                    $('.ui-menu6').mainMenu6('showDatabaseOverview');
                    that._rendered_db_overview = true;
                }, 1000);
            }
        }

        //that._dashboardVisibility( false );
    },


    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART);
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
        
        this.div_logo.remove();
        this.divMainMenu.remove();
        if(this.divShortcuts) this.divShortcuts.remove();
        /* remove generated elements
        this.btn_Admin.remove();
        this.btn_Profile.remove();
        this.menu_Profile.remove();
        //this.menu_Profile3.remove();
        this.btn_Database.remove();
        this.menu_Database.remove();
        this.btn_Import.remove();
        this.menu_Import.remove();
        this.btn_Export.remove();
        this.menu_Export.remove();
        this.btn_Help.remove();
        this.menu_Help.remove();
        */
    },

    
    //
    // menu element is <li> (button) for horizontal meny and drop-down that is loaded from html
    // access_level =   -1 no verification
        //  0 logged (DEFAULT)
        //  groupid  - admin of group  
        //  1 - db admin (admin of group #1)
        //  2 - db owner
    //
    // exp_level = 3 hidden, 0 expert, 1 advance, 2 begginner
    // user_permissions = 'add' can create records, 'delete' can delete records, 'add delete' can do both
    //
    _initMenu: function(name, access_level, parentdiv, exp_level, user_permissions){

        let that = this;
        let myTimeoutId = -1;
        
        //show hide function
        let _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 800);
            //$( ele ).delay(800).hide();
        },
        _show = function(ele, parent) {
            clearTimeout(myTimeoutId);
            
            $('.menu-or-popup').hide(); //hide other
            let menu = $( ele ).show()
            
            menu.position({my: 'left-2 top', at: 'left bottom', of: parent, collision: 'none' });
            
            menu.position().top = parent.position().top + parent.height();
            
            
            return false;
        };

        let link;

        if(name=='Profile'){
            
            //profile menu header consists of two labels: Settings and Current user name
                
            link = $('<a href="#">'
            +(this.options.is_h6style?'':'<span style="display:inline-block;padding-right:20px">'+window.hWin.HR('Settings')+'</span>') 
            +'<div class="ui-icon ui-icon-user" style="display:inline-block;font-size:12px;line-height:16px;vertical-align:bottom;"></div>'
            +'&nbsp;&nbsp;<div class="usrFullName" style="display:inline-block">'
            +window.hWin.HAPI4.currentUser.ugr_FullName
            +'</div><div style="position:relative;" class="ui-icon ui-icon-carat-1-s"></div></a>');
                                                                                                           
        }else if(name=='Help' && this.options.is_h6style){
            
            link = $('<a href="#">'
            +(this.options.is_h6style?'':'<span style="display:inline-block;padding-right:20px">'+window.hWin.HR('Settings')+'</span>') 
            +'<div class="ui-icon ui-icon-circle-b-help" style="display:inline-block;font-size:11px;line-height:16px;vertical-align:bottom;"></div>'
            +'&nbsp;&nbsp;<div style="display:inline-block">'+ window.hWin.HR(name)
            +'</div><div style="position:relative;" class="ui-icon ui-icon-carat-1-s"></div></a>');
            
        }else{
            link = $('<a>',{
                text: window.hWin.HR(name), href:'#'
            });
            
            /*if(name=='Help'){
                link.css({'padding-top': '4px'});    
            }*/            
        }
        
        this.menues['btn_'+name] = $('<li>')
            .css({'padding-right':'1em'})
            .append(link)
            .appendTo( parentdiv?parentdiv:this.divMainMenuItems );
        if(access_level>=0){
            this.menues['btn_'+name].attr('data-user-admin-status', access_level);
        }    
        if(exp_level>=0){
            this.menues['btn_'+name].attr('data-user-experience-level', exp_level);
        }
        if(!window.hWin.HEURIST4.util.isempty(user_permissions)){
            this.menues['btn_'+name].attr('data-user-permissions', user_permissions);
        }
        
        // Load content for all menus except Database when user is logged out
        let section = 'menu_'+name;            

        this.menues[section] = $('<ul>')
        .load(
            window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu'+name+'.html',
          function(){    //add ?t=+(new Date().getTime()) to avoid cache in devtime
        
            let content = that.menues[section].find('ul');
            if(content.length>0){
                that.menues[section].html(content.html());
            }
          
            window.hWin.HAPI4.HRA(that.menues[section]);
          
            that.menues[section].find('.list-menu-only').hide();
         
            that.menues[section].addClass('menu-or-popup')
            .css({'position':'absolute', 'padding':'5px'})
            .appendTo( that.document.find('body') )
            //.addClass('ui-menu-divider-heurist')
            .menu({select: function(event, ui){ 
                    that.menuActionHandler(event, ui.item.find('a'));
                    return false; 
            }});
            
            that.menues[section].find('a').each(function(idx,item){
                item = $(item);
                if(item.attr('data-ext')!=1){
                    let href = item.attr('href');
                    if(href!='#' && !window.hWin.HEURIST4.util.isempty(href)){
                        item.attr('href','#')
                        if(href.length>1 && href[0]!='#'){
                            item.attr('data-link', href);
                        }
                    }
                }
                //localization   (without id - divider)
                if(item.attr('id') && item.attr('id').indexOf('menu-')==0){
                    let title = window.hWin.HR( item.attr('id') );
                    if(title) item.text( title );
                    title = window.hWin.HR( item.attr('id')+'-hint' );
                    if(title) item.attr('title', title );
                }
            });

            if(name == 'Help'){ // update sysadmin email
                that.menues[section].find('a#menu-help-emailadmin').attr('href', `mailto:${window.hWin.HAPI4.sysinfo.sysadmin_email}`);
            }
        })
        .hide();


        this._on( this.menues['btn_'+name], {
            mouseenter : function(){_show(this.menues['menu_'+name], this.menues['btn_'+name])},
            mouseleave : function(){_hide(this.menues['menu_'+name])}
        });
        this._on( this.menues['menu_'+name], {
            mouseenter : function(){_show(this.menues['menu_'+name], this.menues['btn_'+name])},
            mouseleave : function(){_hide(this.menues['menu_'+name])}
        });

    },
    
    //
    //
    //
    menuGetActionLink: function(menu_entry_id){

        for (let key in this.menues){
            let menu = this.menues[key];
            let link = $(menu).find('a[id="'+menu_entry_id+'"]');
            if(link.length>0){
                link.attr('data-parent',key);
                return link;
            }
        }
        return null;
    },
    
    //
    // returns all menu entries as array - used in dropdown command selector in dashboard editor
    //
    menuGetAllActions: function(){
        
        let res = [];    
            
        for (let key in this.menues){
            let menu = this.menues[key];
            let links = $(menu).find('a');
            $.each(links, function(idx, link){
                link = $(link);
                if(link.attr('id').indexOf('menu-')==0){
                    res.push({key:link.attr('id'), title: ( key.substring(5)+' > '+link.text().trim() ) });
                }
            });
        }
        
        return res;
    },

    //
    // finds and executes menu entry by link id
    // 
    menuActionById: function(menu_entry_id, dialog_options){
        
        if( !window.hWin.HEURIST4.util.isempty(menu_entry_id) ){
        
            for (let key in this.menues){
                let menu = this.menues[key];
                let ele = $(menu).find('#'+menu_entry_id);
                if(ele.length>0 && ele.is('a')){
                    this.menuActionHandler(null, ele, dialog_options);            
                    break;
                }
            }
            
        }
    },
    
    // dialog_options - not used
    // parameters from dialog are taken from data- attributes of li element in menu html
    //
    menuActionHandler: function(event, item, dialog_options){
        
        let that = this;
        
        if($(item).attr('data-ext')==1){
          return;  
        } 
        
        let action = item.attr('id');
        let action_log = item.attr('data-logaction');
        let action_level = item.attr('data-user-admin-status');
        let action_member_level = item.attr('data-user-member-status');
        let action_passworded = item.attr('data-pwd');
        let action_container = item.attr('data-container');
        let action_user_permissions = item.attr('data-user-permissions')
        
        if(!action_passworded && !window.hWin.HAPI4.has_access(2)) action_passworded = item.attr('data-pwd-nonowner');
        let href = item.attr('data-link');
        let target = item.attr('target');
        let entity_dialog_options = {},  //parameters for h6 entity dialog in container
            popup_dialog_options = {};   //parameters for h6 iframe in container
        
        if(this.options.is_h6style && (dialog_options || action_container)){
            
            let container, menu_container;
            if(dialog_options && dialog_options['container']){
                container = dialog_options['container'];
            }else if(action_container){
                let section = action_container;
                //activate specified menu and container
                $('.ui-menu6').mainMenu6('switchContainer', section, true);
                container = $('.ui-menu6 > .ui-menu6-widgets.ui-heurist-'+section);
                container.removeClass('ui-suppress-border-and-shadow');
                menu_container = $('.ui-menu6 > .ui-menu6-section.ui-heurist-'+section); //need for publish/cms
                //highlight required item in menu
                menu_container.find('li').removeClass('ui-state-active');
                menu_container.find('li[data-action="'+action+'"]').addClass('ui-state-active');
                
            }
            let pos = null;
            if(dialog_options && dialog_options['position']){
                pos = dialog_options['position'];
            }
            
            // for entity show dialog inline in target container
            entity_dialog_options = {isdialog: false, 
                                     innerTitle: true,
                                     isFrontUI: true, //inline in main screen
                                     menu_container: menu_container,
                                     container: container};
                                     
            // for popup dialog show on postion over container (notdragable)
            popup_dialog_options = {
                innerTitle: true,
                is_h6style: true,
                isdialog: false,
                resizable: false,
                draggable: false,
                menu_container: menu_container,
                container: container,
                position: pos,
                maximize: true
            }
            
            if(dialog_options && dialog_options['record_id']>0){
                popup_dialog_options.record_id = dialog_options['record_id'];
            }
                                                 
        }
        
        //  -1 no verification
        //  0 logged (DEFAULT)
        //  groupid  - admin of group  
        //  1 - db admin (admin of group #1)
        //  2 - db owner
        let requiredLevel = (action_level==-1 || action_level>=0) ?action_level :0;
        
        if(action_member_level>0){
            requiredLevel = requiredLevel + ';' + action_member_level;  
        } 
        
        window.hWin.HAPI4.SystemMgr.verify_credentials(
            function( entered_password ){
            
            if(action_log){
                window.hWin.HAPI4.SystemMgr.user_log(action_log);
            }
            
        if(action == "menu-database-browse"){

                let options = $.extend(entity_dialog_options, {
                    select_mode:'select_single',
                    onselect:function(event, data){

                        if(data && data.selection && data.selection.length==1){
                            let db = data.selection[0];
                            if(db.indexOf('hdb_')===0) db = db.substr(4);
                            window.open( window.hWin.HAPI4.baseURL + '?db=' + db, '_blank');
                        }
                    }
                });

                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);

        }
        else if(action == "menu-database-create"){
        
            popup_dialog_options.title = window.hWin.HR('Create New Database');
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbCreate', popup_dialog_options);
        
        }
        else if(action == "menu-database-restore"){

            popup_dialog_options.title = window.hWin.HR('Restore Database');
            popup_dialog_options.entered_password = entered_password;
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbRestore', popup_dialog_options);
        
        }
        else if(action == "menu-database-delete"){
        
            popup_dialog_options.title = window.hWin.HR('Delete Database');
            popup_dialog_options.entered_password = entered_password;
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbDelete', popup_dialog_options);
        
        }
        else if(action == "menu-database-clear"){
        
            popup_dialog_options.title = window.hWin.HR('Clear Database');
            popup_dialog_options.entered_password = entered_password;
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbClear', popup_dialog_options);
        
        }
        else if(action == "menu-database-rename"){
        
            popup_dialog_options.title = window.hWin.HR('Rename Database');
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbRename', popup_dialog_options);
            
        } 
        else if(action == "menu-database-clone"){
        
            popup_dialog_options.title = window.hWin.HR('Clone Database');
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbClone', popup_dialog_options);
        
        }  
        else if(action == "menu-database-register"){
        
            popup_dialog_options.title = window.hWin.HR('Register database');
            popup_dialog_options.entered_password = entered_password;
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbRegister', popup_dialog_options);
        
        }
        else if(action == "menu-database-verify"){
        
            popup_dialog_options.title = window.hWin.HR('Verify Database Integrity');
            window.hWin.HEURIST4.ui.showRecordActionDialog('dbVerify', popup_dialog_options);
        
        }
        else if(action == "menu-cms-create"){

            window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    const RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
                          RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];

                    that._getCountWebSiteRecords(function(){

                        let sMsg = '';

                        if(RT_CMS_HOME>0 && that.cms_home_records_count>0){

                            sMsg = 'You already have '+
                            ((that.cms_home_records_count==1)?'a website'
                                :(that.cms_home_records_count+' websites'))+
                            '. Are you sure you want to create an additional site?';

                            if(that.sMsgCmsPrivate!=''){
                                sMsg = sMsg + that.sMsgCmsPrivate;    
                            }

                        }else if(RT_CMS_HOME > 0 && RT_CMS_MENU > 0){
                            sMsg = 'Are you sure you want to create a site?';
                        }else{
                            // construct missing part of msg
                            let missing = RT_CMS_HOME > 0 ? 'CMS_Home (Concept-ID: 99-51)' : '';
                            missing = RT_CMS_MENU > 0 && missing == '' ? 'CMS Menu-Page (Concept-ID: 99-52)' : RT_CMS_MENU > 0 ? missing + ' and CMS Menu-Page (Concept-ID: 99-52)' : missing;
                            missing += (RT_CMS_HOME <= 0 && RT_CMS_MENU <= 0 ? ' record types' : ' record type');

                            window.hWin.HEURIST4.msg.showMsgErr(
                                'Your database is missing the ' + missing + '.<br>'
                                +'These record types can be downloaded from the Heurist_Bibliographic database (# 6) using Design > Browse templates.<br>'
                                +'You will need to refresh your window after downloading the record type(s) for the additions to take affect.'
                            );
                            return;
                        }

                        sMsg = sMsg 
                        + '<p>Check the box if you wish to keep your website private '
                        + '<br><input type="checkbox"> hide website (can be changed later)</p>';

                        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg,
                            function(){ 
                                let chb = $dlg.find('input[type="checkbox"]');
                                let is_private = chb.is(':checked');
                                
                                popup_dialog_options.record_id = -1;
                                popup_dialog_options.webpage_private = is_private;
                                
                                window.hWin.HEURIST4.ui.showEditCMSDialog(popup_dialog_options); 
                            },
                            window.hWin.HR('New website'),
                            {default_palette_class: 'ui-heurist-publish'});

                        }
                    );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });

        }
        else if(action == "menu-cms-create-page"){
            
            window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    that._create_WebPage( popup_dialog_options );    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });

        }
        else if(action == 'menu-cms-edit-page' || action == 'menu-cms-view-page'){

            if(popup_dialog_options.record_id>0){
                window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );                    
            }else{
                that._getCountWebPageRecords(function( count ){
                    if(count>0){ 
                        //select
                        that._select_WebPage( (action == 'menu-cms-view-page'), popup_dialog_options );    
                    }else{

                        window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, function(response){

                            if(response.status == window.hWin.ResponseStatus.OK){
                                //create
                                that._create_WebPage( popup_dialog_options );
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                    }
                });
            }

        }
        else if(action == 'menu-cms-edit'){

                const RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
                
                if(popup_dialog_options.record_id>0){
                    
                        window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );                    
                    
                }else{
                

                    that._getCountWebSiteRecords(function(){
                        if(RT_CMS_HOME>0 && that.cms_home_records_count>0){
                            
                            if(that.sMsgCmsPrivate!=''){
                                let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(that.sMsgCmsPrivate,
                                   {Continue:function(){ 
                                        $dlg.dialog('close'); 
                                        that._select_CMS_Home( false, popup_dialog_options ); 
                                   }},
                                   'Non-public website records',
                                   {default_palette_class: 'ui-heurist-publish'});
                            }else{
                                that._select_CMS_Home( false, popup_dialog_options );    
                            }
                            
                        }else{
                            popup_dialog_options.record_id = -1;
                            window.hWin.HEURIST4.msg.showMsgDlg(
                                    'New website will be created. Continue?',
                                    function(){
                                        window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, function(response){

                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options ); 
                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                            }
                                        });
                                    },
                                    null,
                                    {default_palette_class: 'ui-heurist-publish'});
                        }
                    });
                
                }

        }
        else if(action == "menu-cms-view"){

                const RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
                that._getCountWebSiteRecords(function(){
                    if(RT_CMS_HOME>0 && that.cms_home_records_count>0){
                        
                        if(that.sMsgCmsPrivate!=''){
                            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(that.sMsgCmsPrivate,
                               {Continue:function(){ $dlg.dialog('close'); that._select_CMS_Home( true, popup_dialog_options ); }},
                               'Non-public website records',
                               {default_palette_class: 'ui-heurist-publish'});
                        }else{
                            that._select_CMS_Home( true, popup_dialog_options );
                        }
                        
                    }else if( window.hWin.HAPI4.is_admin() ){
                        popup_dialog_options.record_id = -1;
                        window.hWin.HEURIST4.msg.showMsgDlg(
                                'New website will be created. Continue?',
                                function(){ 
                                    window.hWin.HAPI4.SystemMgr.check_allow_cms({a:'check_allow_cms'}, function(response){

                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options ); 
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    });
                                },
                                null,
                                {default_palette_class: 'ui-heurist-publish'});
                    }else{
                        window.hWin.HEURIST4.msg.showMsgFlash('No websites defined',2000);
                    }
                });

        }
        else if(action == "menu-cms-embed"){ //create new standalone webpage

            window.hWin.HEURIST4.ui.showRecordActionDialog('embedDialog', 
                                {cms_popup_dialog_options:popup_dialog_options, path: 'widgets/cms/',title:'Web Page' });
            
        }
        else if(action == "menu-database-properties"){

            window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification', entity_dialog_options);
            
        }
        else if(action == "menu-lookup-config"){

            popup_dialog_options['classes'] = {"ui-dialog": "ui-heurist-design", "ui-dialog-titlebar": "ui-heurist-design"};
            popup_dialog_options['service_config'] = window.hWin.HAPI4.sysinfo['service_config'];
            popup_dialog_options['title'] = "Lookup service configuration";
            popup_dialog_options['path'] = 'widgets/lookup/';

            window.hWin.HEURIST4.ui.showRecordActionDialog('lookupConfig', popup_dialog_options);

        }
        else if(action == "menu-repository-config"){

            popup_dialog_options['classes'] = {"ui-dialog": "ui-heurist-design", "ui-dialog-titlebar": "ui-heurist-design"};
            popup_dialog_options['service_config'] = window.hWin.HAPI4.sysinfo['repository_config'];
            popup_dialog_options['title'] = "Repository service configuration";
            popup_dialog_options['path'] = 'widgets/admin/';

            window.hWin.HEURIST4.ui.showRecordActionDialog('repositoryConfig', popup_dialog_options);

        }
        else if(action == "menu-database-rollback"){

            window.hWin.HEURIST4.msg.showMsgDlg('Although rollback data has been recorded, '
                                    + 'there is currently no end-user interface way of rolling '
                                    + 'back the database. <br><br>'+window.hWin.HR('New_Function_Contact_Team'));

        }
        else if(action == "menu-structure-rectypes"){

            window.hWin.HEURIST4.defRecTypes_calls = 0;
            
            window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', entity_dialog_options);
                                    
        }
        else if(action == "menu-structure-fieldtypes"){

            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', entity_dialog_options);
                                    
        }
        else if(action == "menu-structure-workflowstages"){

            window.hWin.HEURIST4.ui.showEntityDialog('sysWorkflowRules', entity_dialog_options);
                                    
        }
        else if(action == "menu-structure-vocabterms"){

            window.hWin.HEURIST4.ui.showEntityDialog('defTerms', entity_dialog_options);
                                    
        }
        else if(action == "menu-structure-import" || action == "menu-structure-import-express"){

            let opts = {};
            if(action == "menu-structure-import-express"){
                opts['source_database_id'] = 3;    
                opts['title'] = 'Import structural definitions into current database from Heurist Reference Set';
            }
            
            if(popup_dialog_options){

                opts = $.extend(popup_dialog_options, opts);
                $(opts.container).empty();
                opts.container.importStructure( opts );
                
            }else{
                
                opts['isdialog'] = true;
                
                let manage_dlg = $('<div id="heurist-dialog-importRectypes-'+window.hWin.HEURIST4.util.random()+'">')
                    .appendTo( $('body') )
                    .importStructure( opts );
                
            }
            
        }
        else if(action == "menu-structure-mimetypes"){
            
                window.hWin.HEURIST4.ui.showEntityDialog('defFileExtToMimetype',
                                                {edit_mode:'inline', width:900});

        }
        else if(action == "menu-structure-refresh"){
            
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, window.hWin.document);
                                                
        }
        else if(action == "menu-records-archive"){
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordArchive');
        
        }
        else if(action == "menu-structure-duplicates"){
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordFindDuplicates',popup_dialog_options);
        
        }
        else if(action == "menu-export-csv"){
            
            popup_dialog_options.format = 'csv';
            that._exportRecords(popup_dialog_options);

        }
        else if(action == "menu-export-hml-resultset"){
            
            popup_dialog_options.format = 'xml';
            that._exportRecords(popup_dialog_options);
            
        }
        else if(action == "menu-export-hml-multifile"){
            //that._exportRecords({format:'hml', multifile:true});
        }
        else if(action == "menu-export-json"){ 
            
            popup_dialog_options.format = 'json';
            that._exportRecords(popup_dialog_options);
            
        }
        else if(action == "menu-export-geojson"){ 
            
            popup_dialog_options.format = 'geojson';
            that._exportRecords(popup_dialog_options);
            
        }
        else if(action == "menu-export-gephi"){    
            
            popup_dialog_options.format = 'gephi';
            that._exportRecords(popup_dialog_options);
            
        }
        else if(action == "menu-export-kml"){
            
            popup_dialog_options.format = 'kml';
            that._exportRecords(popup_dialog_options);
            
        }
        else if(action == "menu-export-iiif"){
            
            popup_dialog_options.format = 'iiif';
            that._exportRecords(popup_dialog_options);
            
        }
        else if(action == "menu-import-add-record"){
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');
            
        }
        else if(action == "menu-import-email" || 
           action == "menu-faims-import" || action == "menu-faims-export"
           ){

            window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('New_Function_Conversion')
                    + '<br><br>'+window.hWin.HR('New_Function_Contact_Team'));
                 
                 
        }
        else if(action == "menu-import-get-template"){
            that._generateHeuristTemplate();
        }
        else if(action == 'menu-extract-pdf'){
            
            //this menu should not be in main menu. IJ request
            let app = window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_resultList');
            if(app && app.widget){
                $(app.widget).resultList('callResultListMenu', 'menu-selected-extract-pdf'); //call method
            }
            
        }
        else if(action == 'menu-subset-set'){
            
            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
            if(widget){

                if(that._rendered_db_overview){
                    $('.ui-menu6').mainMenu6('hideDatabaseOverview');
                }

                widget.resultList('callResultListMenu', 'menu-subset-set'); //call method
            }
            
        }
        else if(action == "menu-manage-dashboards"){
           
           entity_dialog_options['is_iconlist_mode'] = false;
           entity_dialog_options['isViewMode'] = false;
           entity_dialog_options['onClose'] = function(){
                            setTimeout('$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)',1000);
                        }; 
            
           window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard', entity_dialog_options);
        
        }
        else if(action == "menu-help-online"){
            
            window.open( window.hWin.HAPI4.sysinfo.referenceServerURL+'?db=Heurist_Help_System&website', target );
            
        }
        else if(action == "menu-help-bugreport"){
            
           window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport');
        }else 
        if(action == "menu-profile-tags"){
            window.hWin.HEURIST4.ui.showEntityDialog('usrTags');
        }else 
        if(action == "menu-profile-reminders"){
            window.hWin.HEURIST4.ui.showEntityDialog('usrReminders');
        }else 
        if(action == "menu-profile-files"){
            //{width:950}
            window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles',entity_dialog_options);
        }else 
        if(action == "menu-profile-groups"){
            window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', entity_dialog_options);
        }else 
        if(action == "menu-profile-info"){
            window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', 
                {edit_mode:'editonly', rec_ID: window.hWin.HAPI4.currentUser['ugr_ID']});
        }else 
        if(action == "menu-profile-users"){ //for admin only
            window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', entity_dialog_options);
        }else 
        if(action == "menu-profile-preferences"){
            that._editPreferences( popup_dialog_options );
        }else
        if(action == "menu-profile-import"){  //for admin only
            that._importUsers( entity_dialog_options );
        }else
        if(action == "menu-profile-logout"){ 
            that.logout();
        }else 
        if(action == "menu-database-refresh"){
            that._refreshLists( true );
        }else if(action == "menu-admin-server"){
            that._showAdminServer({entered_password:entered_password});
        }else if(action == "menu-help-quick-tips"){
            $('.ui-menu6').mainMenu6('showQuickTips');
        }else if(action == "menu-manage-rectitles"){

            let dlg_options = $.extend(popup_dialog_options, {});
            dlg_options['title'] = item.attr('data-header') ? item.attr('data-header') : 'Rebuild record titles';

            that._rebuildRecordTitles(dlg_options);
        }else
        if(!window.hWin.HEURIST4.util.isempty(href) && href!='#'){
            
            
            if(href.indexOf('mailto:')==0){
                  /*var t;
                  $(window).blur(function() {
                        // The browser apparently responded, so stop the timeout.
                        clearTimeout(t);
                  });

                  t = setTimeout(function() {
                        // The browser did not respond after 500ms, so open an alternative URL.
                        window.hWin.HEURIST4.msg.showMsgErr('mailto_fail');
                  }, 500);
                  window.open( href );*/
                  
                  let win = window.open(href, 'emailWindow');
                  if (win && win.open && !win.closed) win.close();                  
                  return;
            }
            
    
            if(!(href.indexOf('http://')==0 || href.indexOf('https://')==0)){
                href = window.hWin.HAPI4.baseURL + href + (href.indexOf('?')>=0?'&':'?') + 'db=' + window.hWin.HAPI4.database;        
            }
            
            if(href.indexOf(window.hWin.HAPI4.baseURL) == 0){
                if(!window.hWin.HEURIST4.util.isempty(entered_password)){
                     href =  href + '&pwd=' + entered_password;
                }
                if(that.options.is_h6style){
                     href =  href + '&ll=H6Default&t='+((new Date()).getTime());
                }
            }
            
            if(!window.hWin.HEURIST4.util.isempty(target)){
                window.open( href, target);    
            }else{
                
                let options = {};
                const size_type = item.attr('data-size');
                let dlg_title = item.attr('data-header');
                if(!dlg_title) dlg_title = item.text();
                let dlg_help = item.attr('data-help'); //name of context file from context_help folder

                const size_w = item.attr('data-dialog-width');
                const size_h = item.attr('data-dialog-height');

                if(size_w>0 && size_h>0){
                    options = {width:size_w, height:size_h};
                }else
                if(size_type=='large'){
                    options = {width:1400, height:800};
                }else if(size_type=='portrait'){
                    options = {width:650, height:800};    
                }else if(size_type=='medium'){
                    options = {width:1200, height:640};
                }else{
                    options = {width:760, height:450};
                }
                if(!window.hWin.HEURIST4.util.isempty(dlg_title)){
                    options['title'] = dlg_title;
                }           
                if(!window.hWin.HEURIST4.util.isempty(dlg_help)){
                    options['context_help'] = window.hWin.HRes(dlg_help)+' #content';

                    if(action == 'menu-structure-summary'){
                        options['show_help_on_init'] = false;
                    }else{
                        options['show_help_on_init'] = true;
                    }
                }
                
                //var position = { my: "center", at: "center", of: window.hWin };
                let maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
                if(options['width']>maxw) options['width'] = maxw*0.95;
                let maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
                if(options['height']>maxh) options['height'] = maxh*0.95;
                
                if (item.hasClass('upload_files')) {
                    //beforeClose
                    options['afterclose'] = function( event, ui ) {

                            if(window.hWin.HEURIST4.filesWereUploaded){
                                
                                let buttons = {};
                                buttons[window.hWin.HR('OK')]  = function() {
                                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                    $dlg.dialog( "close" );
                                    that.menuActionById('menu-index-files');
                                };                                 
                                
                                window.hWin.HEURIST4.msg.showMsgDlg('The files you have uploaded will not appear as records in the database'
                                +' until you run Import > index multimedia This function will open when you click OK.',buttons);
                            }
                    }
                }

                options = $.extend(popup_dialog_options, options);
                
                window.hWin.HEURIST4.msg.showDialog( href, options );    
            } 
            
        }
        
        
        },
            requiredLevel, //needed level of credentials any, logged (by default), admin of group, db admin, db owner
            action_passworded,    //this is type of password, if it is not set action is to allowed - otherwise need to enter password
            null,
            action_user_permissions
        );

        if(event) window.hWin.HEURIST4.util.stopEvent(event);
    },
    
    //
    // similar in resultListMenu
    //
    isResultSetEmpty: function(){
        let recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsgDlg('No results found. '
            +'Please modify search/filter to return at least one result record.');
            return true;
        }else{
            return false;
        }
    },
    
    //
    //
    //
    _showAdminServer: function( popup_options ){
        
        let that = this;
        
        if(!popup_options) popup_options = {};

        let $dlg = (popup_options.container)
                        ?popup_options.container
                        :$("#heurist-dialog").addClass('ui-heurist-bg-light');
        $dlg.empty();

        $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/admin/manage_server.html?t="+(new Date().time), function(){
           
            
            $dlg.find('li').css({padding:'10px 0px'});
            
            $.each($dlg.find('a'), function(i,item){
                
                let href = $(item).attr('href');
                
                if(!(href.indexOf('http://')==0 || href.indexOf('https://')==0)){
                    href = window.hWin.HAPI4.baseURL + href;// + (href.indexOf('?')>=0?'&':'?') + 'db=' + window.hWin.HAPI4.database;        
                }
                
                //popup_options.entered_password = '1234567';
                /*
                if(!window.hWin.HEURIST4.util.isempty(popup_options.entered_password)){
                         href =  href + '&pwd=' + popup_options.entered_password;
                }
                */
                $(item).attr('href', href);

            });

            that._on($dlg.find('a'),{click:function(event){
                    let surl = $(event.target).attr('href');
                    
                    
                    if(popup_options.entered_password){
                        $('#mainForm').find('input[name="pwd"]').val(popup_options.entered_password);   
                    }
                    $('#mainForm').find('input[name="db"]').val(window.hWin.HAPI4.database);
                    $('#mainForm').attr('action',surl);
                    $('#mainForm').submit();

                    //window.open( surl, '_blank'); 
                    window.hWin.HEURIST4.util.stopEvent(event);   
                    return false;
                }});
                
                
                if(popup_options.container){
                    
                    $dlg.find('.ui-heurist-header').html(window.hWin.HR('Server manager'));
                    
                    $dlg.find('.ui-dialog-buttonpane').show();
                    $dlg.find('.btn-cancel').button().on({click:function(){
                            popup_options.container.hide();
                    }});
                    
                }else{

                    $dlg.dialog({
                        autoOpen: true,
                        height: 640,
                        width: 600,
                        modal: true,
                        resizable: false,
                        draggable: true,
                        title: window.hWin.HR("Server manager"),
                        buttons: [
                            {text:window.hWin.HR('Close'), click: function() {
                                $( this ).dialog( "close" );
                            }}
                        ]
                    });
                }
            
            
        });

    },


    /**
    * Open Edit Preferences dialog (@todo: ? move into separate file?)
    */
    _editPreferences: function( popup_options )
    {
        let that = this;
        
        if(!popup_options) popup_options = {};

        let $dlg = (popup_options.container)  //if ther is container - this is not a popup
                        ?popup_options.container
                        :$("#heurist-dialog").addClass('ui-heurist-bg-light');
        $dlg.empty();

        $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/profile/profile_preferences.html?t="+(new Date().time), function(){

            
            //find all labels and apply localization
            $dlg.find('label').each(function(){
                $(this).html(window.hWin.HR($(this).html()));
            })
            $dlg.find('.header').css({'min-width':'300px', 'width':'300px'});

            //fill list of languages
            //fill list of layouts
            initProfilePreferences();

            //assign values to form fields from window.hWin.HAPI4.currentUser['ugr_Preferences']
            let prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];
            
            let allFields = $dlg.find('input,select');

            let currentTheme = prefs['layout_theme'];
            /* @todo later
            var themeSwitcher = $("#layout_theme").themeswitcher(
                {initialText: currentTheme.charAt(0).toUpperCase() + currentTheme.slice(1),
                currentTheme:currentTheme,
                onSelect: function(){
                    currentTheme = this.currentTheme;
            }});
            */

            //default
            prefs['userCompetencyLevel'] = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
            prefs['userFontSize'] = window.hWin.HAPI4.get_prefs_def('userFontSize', 12);
            prefs['searchQueryInBrowser'] = window.hWin.HAPI4.get_prefs_def('searchQueryInBrowser', 1);
            prefs['mapcluster_on'] = window.hWin.HAPI4.get_prefs_def('mapcluster_on', 1);
            prefs['mapcluster_zoom'] = window.hWin.HAPI4.get_prefs_def('mapcluster_zoom', 12);
            prefs['entity_btn_on'] = window.hWin.HAPI4.get_prefs_def('entity_btn_on', 1);
            
            let map_controls = window.hWin.HAPI4.get_prefs_def('mapcontrols', 'bookmark,geocoder,selector,print,publish');
            map_controls = map_controls.split(',');
            prefs['mctrl_bookmark'] = 0;prefs['mctrl_geocoder'] = 0;
            prefs['mctrl_selector'] = 0;prefs['mctrl_print'] = 0;
            prefs['mctrl_publish'] = 0;
            for(let i=0;i<map_controls.length;i++){
                prefs['mctrl_'+map_controls[i]] = 1;
            }

            // Map popup record view
            window.hWin.HEURIST4.ui.createTemplateSelector( $dlg.find('#map_template'), 
                [{key:'',title:'Standard map popup template'},
                 {key:'standard',title:'Standard record info (in popup)'},
                 {key:'none',title:'Disable popup'}
                 ],
                 window.hWin.HAPI4.get_prefs_def('map_template', null));

            // Main record view
            window.hWin.HEURIST4.ui.createTemplateSelector( $dlg.find('#main_recview'), [{key:'default',title:'Standard record view'}],
                            window.hWin.HAPI4.get_prefs_def('main_recview', 'default'));

            //from prefs to ui
            allFields.each(function(){
                if(prefs[this.id]){
                    if(this.type=="checkbox"){
                        this.checked = (prefs[this.id]=="1" || prefs[this.id]=="true")
                    }else{
                        $(this).val(prefs[this.id]);
                    }
                };
            });
            
            //change font size example
            $dlg.find('#userFontSizeExample')
                .css('font-size', prefs['userFontSize']+'px')
                .position({
                    my: 'left+15 center',
                    at: 'right center',
                    of: $dlg.find('#userFontSize')
                });

            $dlg.find('#userFontSize').on('change', function(){ 
                let size = $dlg.find('#userFontSize').val();
                $dlg.find('#userFontSizeExample')
                    .css('font-size', size+'px')
                    .position({
                        my: 'left+15 center',
                        at: 'right center',
                        of: $dlg.find('#userFontSize')
                    });
            });

            //custom/user heurist theme
            let custom_theme_div = $dlg.find('#custom_theme_div');
            
            let $btn_edit_clear2 = $('<span>')
            .addClass("smallbutton ui-icon ui-icon-circlesmall-close")
            .attr('tabindex', '-1')
            .attr('title', 'Reset default color settings')
            .appendTo( custom_theme_div )
            .css({'line-height': '20px',cursor:'pointer',
                outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'})
                .on( { click: function(){ window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                    function(){$dlg.find('#custom_theme').val('');}); }});
                
            let $btn_edit_switcher2 = $( '<span>open editor</span>', {title: 'Open theme editor'})
                .addClass('smallbutton')
                .css({'line-height': '20px',cursor:'pointer','text-decoration':'underline'})
                .appendTo( custom_theme_div );
                
                
            function __openThemeDialog(){
                    let current_val = window.hWin.HEURIST4.util.isJSON( $dlg.find('#custom_theme').val() );
                    if(!current_val) current_val = {};
                    window.hWin.HEURIST4.ui.showEditThemeDialog(current_val, false, function(new_value){
                        $dlg.find('#custom_theme').val(JSON.stringify(new_value));
                    });
            }
                
            $dlg.find('#custom_theme').attr('readonly','readonly').on({ click: __openThemeDialog });
            $btn_edit_switcher2.on( { click: __openThemeDialog });
            
            
            //map symbology editor            
            window.hWin.HEURIST4.ui.initEditSymbologyControl($dlg.find('#map_default_style'));
            window.hWin.HEURIST4.ui.initEditSymbologyControl($dlg.find('#map_select_style'));
            
            
            let ele = $dlg.find('#mapcluster_on');
            $dlg.find('#mapcluster_grid').on('change', function(){ ele.prop('checked', true)});
            $dlg.find('#mapcluster_count').on('change', function(){ ele.prop('checked', true)});
            $dlg.find('#mapcluster_zoom').on('change', function(){ ele.prop('checked', true)});

            //save to preferences
            function __doSave(){

                let request = {};
                let val;
                let map_controls = [];

                allFields.each(function(){
                    if(this.type=="checkbox"){
                        if(this.id.indexOf('mctrl_')<0){
                            request[this.id] = this.checked?"1":"0";
                        }else if(this.checked){
                            map_controls.push(this.value);
                        }
                    }else{
                        request[this.id] = $(this).val();
                    }
                });
                request['mapcontrols'] = map_controls.length==0?'none':map_controls.join(',');
                
                request.layout_theme = currentTheme; //themeSwitcher.getSelected();//    getCurrentTheme();
                //$('#layout_theme').themeswitcher.
                
                //save preferences in session
                window.hWin.HAPI4.SystemMgr.save_prefs(request,
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            let prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];
                            
                            let ask_reload = (prefs['layout_language'] != request['layout_language'] ||
                                //prefs['layout_theme'] != request['layout_theme'] ||
                                prefs['layout_id'] != request['layout_id']);
                                
                            let reload_color_css = (prefs['custom_theme'] != request['custom_theme']);

                            let reload_map = (prefs['mapcluster_grid'] != request['mapcluster_grid'] ||    
                                prefs['mapcluster_on'] != request['mapcluster_on'] || 
                                prefs['search_detail_limit'] != request['search_detail_limit'] ||
                                prefs['mapcluster_count'] != request['mapcluster_count'] ||   
                                prefs['mapcluster_zoom'] != request['mapcluster_zoom'] ||
                                prefs['deriveMapLocation'] != request['deriveMapLocation'] || 
                                prefs['map_rollover'] != request['map_rollover'] || 
                                prefs['mapcontrols'] != request['mapcontrols']);
                                
                            //check help toggler and bookmark search - show/hide
                            window.hWin.HEURIST4.ui.applyCompetencyLevel(request['userCompetencyLevel']);
                            
                            if(prefs['bookmarks_on'] != request['bookmarks_on']){
                                $('.heurist-bookmark-search').css('display',
                                    (request['bookmarks_on']=='1')?'inline-block':'none');
                            }
                            if(prefs['entity_btn_on'] != request['entity_btn_on']){
                                let is_vis = (request['entity_btn_on']=='1');
                                $('.heurist-entity-filter-buttons').css({'visibility':
                                    is_vis?'visible':'hidden',
                                    'height':is_vis?'auto':'10px'});
                            }
                            
                            $.each(request, function(key,value){
                                window.hWin.HAPI4.currentUser['ugr_Preferences'][key] = value;    
                            });

                            //window.hWin.HAPI4.currentUser['ugr_Preferences'] = request; //wrong since request can have only part of peferences!!!!!
                            /*allFields.each(function(){
                            window.hWin.HAPI4.currentUser['ugr_Preferences'][this.id] = $(this).val();
                            });*/

                            if(popup_options.container){    
                                popup_options.container.hide();
                            }else{
                                $dlg.dialog( "close" );
                            }

                            if(ask_reload){
                                window.hWin.HEURIST4.msg.showMsgFlash('Reloading page to apply new settings', 2000);
                                setTimeout(function(){
                                        window.location.reload();
                                    },2100);

                                /*window.hWin.HEURIST4.msg.showMsgDlg('Reload page to apply new settings?',
                                    function(){
                                        window.location.reload();
                                    }, 'Confirmation');*/
                            }else {
                            
                                if(reload_map){
                                    //reload map frame forcefully
                                    let app = window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_Map2');
                                    if(app && app.widget){
                                        $(app.widget).app_timemap('reloadMapFrame'); //call method
                                    }
                                }
                                
                                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                                
                                //reload color scheme
                                if(reload_color_css){
                                    $('head').find('#heurist_color_theme')
                                        .load( window.hWin.HAPI4.baseURL 
                                        + 'hclient/framecontent/initPageTheme.php?db='
                                        + window.hWin.HAPI4.database);
                                }
                                
                                window.hWin.HEURIST4.msg.showMsgFlash('Preferences are saved');
                            }

                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);      
                        }
                    }
                );
            }
            
            
            if(popup_options.container){
                
                $dlg.find('.ui-heurist-header').html('Personal preferences (for this database)');
                
                $dlg.find('.ui-dialog-buttonpane').show();
                $dlg.find('.btn-ok').button().on({click:__doSave});
                $dlg.find('.btn-cancel').button().on({click:function(){
                        popup_options.container.hide();
                }});
                
            }else{

                $dlg.dialog({
                    autoOpen: true,
                    height: 'auto', //600,
                    width: 'auto', //800,
                    modal: true,
                    resizable: false,
                    draggable: true,
                    title: window.hWin.HR("Preferences"),
                    buttons: [
                        {text:window.hWin.HR('Save'), click: __doSave},
                        {text:window.hWin.HR('Cancel'), click: function() {
                            $( this ).dialog( "close" );
                        }}
                    ]
                });
            }
        });

    } //end _editPreferences

    
    , _importUsers: function ( entity_dialog_options ){
        
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
                                                var s = '';
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
    
    
    },


    // @todo - move to editCMS_Records
    //
    //
    _create_WebPage: function( popup_dialog_options )
    {
        let $dlg = window.hWin.HEURIST4.msg.showPrompt(
            window.hWin.HR('Name for new page')+':',
            function(value){ 

                popup_dialog_options.record_id = -2;
                popup_dialog_options.webpage_title = value;

                if(window.hWin.HEURIST4.util.isempty(value)){

                    window.hWin.HEURIST4.msg.showMsgFlash('Specify name',1000);

                }else{
                    //$dlg
                    //create new web page
                    window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );
                }
            },
            {title: window.hWin.HR('New standalone web page'), yes: window.hWin.HR('Create'), no: window.hWin.HR('Cancel')},
            {default_palette_class: 'ui-heurist-publish'});
    },
    
    //@todo - move to editCMS_Records
    //
    //
    _select_WebPage: function ( is_view_mode, popup_dialog_options ){
        
        let RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
        DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
        let query_search_pages = {t:RT_CMS_MENU, sort:'-id'};
        query_search_pages['f:'+DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];
        
        let that = this;
        
        let popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: window.hWin.HR('Select Web page'),
                        fixed_search: query_search_pages, // RT_CMS_MENU,
                        parententity: 0,
                        
                        layout_mode: 'listonly',
                        width:500, height:400,
                        default_palette_class: 'ui-heurist-publish',
                        
                        resultList:{
                            show_toolbar: false,
                            view_mode:'icons',
                            searchfull:null,
                            //search_realm: 'x',
                            //search_initial: , 
                            renderer:function(recordset, record){
                                let recID = recordset.fld(record, 'rec_ID')
                                let recTitle = recordset.fld(record, 'rec_Title'); 
                                let recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
                                let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'+recTitle_strip_all+'</div>';
                                return html;
                            }
                        },
                        
                        onselect:function(event, data){
                                 if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    let recordset = data.selection;
                                    let rec_ID = recordset.getOrder()[0];
                                    
                                    if(is_view_mode){
                                        that._openCMS(rec_ID);
                                    }else{
                                        popup_dialog_options.record_id = rec_ID;
                                        window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );    
                                    }
                                    
                                 }
                        }
        };//popup_options
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
        
        
    },
    
    //@todo - move to editCMS_Records
    // show popup with list of web home records - on select either view or edit
    //                
    _select_CMS_Home: function ( is_view_mode, popup_dialog_options ){
        
        let RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        if(!(RT_CMS_HOME>0)){
            window.hWin.HEURIST4.msg.showMsgDlg('This function is still in development. You will need record types '
                +'99-51 and 99-52 which will be made available as part of Heurist_Reference_Set. '
                +'You may contact support@HeuristNetwork.org if you want to test out this function prior to release.');
            return;
        }
        
        if(this.cms_home_records_count==1){ 

            if(is_view_mode){
                
                this._openCMS( this.cms_home_private_records_ids );
            }else{
                popup_dialog_options.record_id = 0;
                window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options ); //load the only entry at once
            }
            return;
        }
        
        let query_search_sites = {t:RT_CMS_HOME, sort:'-id'};
        
        let that = this;
        
        let popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: window.hWin.HR('Select Website'),
                        fixed_search: query_search_sites,
                        //rectype_set: RT_CMS_HOME,
                        parententity: 0,
                        
                        layout_mode: 'listonly',
                        width:500, height:400,
                        default_palette_class: 'ui-heurist-publish',
                        
                        resultList:{
                            show_toolbar: false,
                            view_mode:'icons',
                            searchfull:null,
                            renderer:function(recordset, record){
                                let recID = recordset.fld(record, 'rec_ID')
                                let recTitle = recordset.fld(record, 'rec_Title'); 
                                let recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
                                let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'+recTitle_strip_all+'</div>';
                                return html;
                            }
                        },
                        
                        onselect:function(event, data){
                                 if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    let recordset = data.selection;
                                    let rec_ID = recordset.getOrder()[0];
                                    
                                    if(is_view_mode){
                                        that._openCMS(rec_ID);
                                    }else{
                                        popup_dialog_options.record_id = rec_ID;
                                        window.hWin.HEURIST4.ui.showEditCMSDialog( popup_dialog_options );    
                                    }
                                    
                                 }
                        }
        };//popup_options
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
        
    }, 
    
    //@todo - move to editCMS_Records
    // force_production_version
    // 
    _openCMS: function(rec_ID, force_production_version){
        
        let url = window.hWin.HAPI4.baseURL;
        
        if(force_production_version===true){

            //replace devlopment folder to production one (ie h6-ij to heurist)
            url = url.split('/');
            let i = url.length-1;
            while(i>0 && url[i]=='') i--;
            url[i]='heurist';
            url = url.join('/');
        
        }else if(force_production_version!==false 
            && window.hWin.HAPI4.installDir && !window.hWin.HAPI4.installDir.endsWith('/heurist/')){
        
            let that = this;    
            let buttons = {};
            buttons[window.hWin.HR('Current (development) version')]  = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" );
                that._openCMS(rec_ID, false);
            };                                 
            buttons[window.hWin.HR('Production version')]  = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" );
                that._openCMS(rec_ID, true);
            };                                 
            
            window.hWin.HEURIST4.msg.showMsgDlg('<p>You are currently running a development version of Heurist.</p>' 
+'<p>Reply "Current (development) version" to use the development version for previewing your site, but please do not publish this URL.</p>' 
+'<p>Reply "Production version" to obtain the URL for public dissemination, which will load the production version of Heurist.</p>' 
,buttons,null,{default_palette_class:'ui-heurist-publish'});
            
            return;
        }
        
        url = url+'?db='+window.hWin.HAPI4.database+'&website';
        if(rec_ID>0){
            url = url + '&id='+rec_ID;
        }
                                                    
        window.open(url, '_blank');
        
    },
    
    //------------------------ LOGIN / LOGOUT --------------------

    //
    //
    //
    logout: function(){

        let that = this;

        window.hWin.HAPI4.SystemMgr.logout(
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    that._initial_search_already_executed = false;
                    window.hWin.HAPI4.setCurrentUser(null);
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                    //that._refresh();
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );

    },
    
    //
    // show login popup dialog if not logged in
    // if login fails show list of databases
    //
    doLogin: function(){
        
        let isforced = this.options.login_inforced;
        let that = this;
        window.hWin.HEURIST4.ui.checkAndLogin( isforced, function(is_logged)
            { 
                if(is_logged) {

                    $(that.element).find('.usrFullName').text(window.hWin.HAPI4.currentUser.ugr_FullName);

                    that._show_version_message();
                    that._performInitialSearch();
                    that._getUserNotifications();

                    $('.ui-menu6').mainMenu6('populateFavouriteFilters'); // show user's favourite filters

                    let query = window.hWin.location.search.substring(1);
                    let vars = query.split('&');
                    //!window.hWin.HEURIST4.util.getUrlParameter('nometadatadisplay', window.hWin.location.search) 
                    if( vars && vars.length==1
						&& window.hWin.HAPI4.sysinfo['db_total_records']>0){
                        // Wait a bit for the main menu to be initialised
                        setTimeout(function(){
                            $('.ui-menu6').mainMenu6('showDatabaseOverview');
                            that._rendered_db_overview = true;
                        }, 1000);
                    }
                } else if(that.options.login_inforced){
                    window.hWin.location  = window.HAPI4.baseURL
                }
            }); 
    },

    //
    // Check if the Database Overview has been rendered
    //
    hasOverviewRendered: function(){
        return this._rendered_db_overview;
    },
    
    //
    //
    //
    _performInitialSearch: function(){
    
        if(this._initial_search_already_executed){
            this._dashboardVisibility( false );
            return;  
        } 
        
        this._initial_search_already_executed = true;
        
        let lt = window.hWin.HAPI4.sysinfo['layout']; 
        //if(window.hWin.HEURIST4.util.getUrlParameter('cms')){
            
        
        let cms_record_id = window.hWin.HEURIST4.util.getUrlParameter('cms', window.hWin.location.search);
        let cmd = window.hWin.HEURIST4.util.getUrlParameter('cmd', window.hWin.location.search);
        if(cms_record_id>0 || !window.hWin.HEURIST4.util.isempty(cmd)){
            //ignore initial search of some menu command is called from url or need to open cms editor
            
            //this.menuActionById('menu-cms-edit', {record_id:cms_record_id});
            //window.hWin.HEURIST4.ui.showEditCMSDialog( cms_record_id );    

        }else 
        if(!window.hWin.HAPI4.is_publish_mode){

                if(window.hWin.HAPI4.sysinfo['db_total_records']>0){      
                    
                    let request = {};

                    if(window.hWin.HAPI4.postparams && window.hWin.HAPI4.postparams['q']){
                        request = window.hWin.HAPI4.postparams;
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
                        request = {q: init_search, w: qdomain}
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

                }
                this._dashboardVisibility( true ); //after login
            }
    },

    //
    //
    //    
    _dashboardVisibility: function(is_startup){

        if (!window.hWin.HAPI4.is_publish_mode
            && (window.hWin.HAPI4.sysinfo.db_has_active_dashboard>0))
        {
            
                let remove_ribbon = true;
               //show dashboard
               let prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_on_startup:0, show_as_ribbon:0});
               if(prefs.show_on_startup==1){
                    if(prefs.show_as_ribbon==1){ //    && lt!='H5Default'
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
                    }else{
                        if(is_startup)
                            window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard'); //show as poup
                    }
               }
               
               if(remove_ribbon && this.divShortcuts){
                     this.divShortcuts.remove();    
                     this.divShortcuts = null;  
               } 
               
               let that = this;
               //this._adjustHeight();
               setTimeout( function(){ that._adjustHeight(); },is_startup?1000:10)
               
        }
        
    },
    
    //
    //
    //
    _adjustHeight: function(){

        let ele = this.element.parents('#layout_panes');
        if(ele){
            let h = 50; //3em;
            
            if(this.divMainMenu.is(':visible')) h = h + 22;
            if(this.divShortcuts){
                this.divMainMenu.css('bottom',40);
                h = h + 42;   
            }else{
                this.divMainMenu.css('bottom',5);
            }
            
            ele.children('#north_pane').height(h);
            ele.children('#center_pane').css({top: h});
            
            if($('.ui-layout-container').length>0){
                //$('.ui-layout-pane').css({'height':'auto'});
                let layout = $('.ui-layout-container').layout();
                layout.resizeAll();
            }
            
        }
        
    },

    //
    //
    //
    _doRegister: function(){
        /*
        if(false && !window.hWin.HEURIST4.util.isFunction(doRegister)){  // already loaded in index.php
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_login.js', this._doRegister );
        }else{}
        */
        doRegister();
        
    },
    
    //------------------------ EXPORT ------------------------------------------
    //
    // opts: {format, isAll, includeRelated, multifile, save_as_file}
    //
    _exportRecords: function(popup_dialog_options){ // isAll = resultset, false = current selection only
    
    
        // for publish export actions    
        if(popup_dialog_options.container){
           popup_dialog_options.need_reload = true; 
           popup_dialog_options.onClose = function() { 
               popup_dialog_options.container.hide() 
           };
           
           popup_dialog_options.menu_container.find('ul').show();
           popup_dialog_options.menu_container.find('ul.for_web_site').hide();
           popup_dialog_options.menu_container.find('ul.for_web_page').hide();
           popup_dialog_options.menu_container.find('span.ui-icon-circle-b-close').hide();
           popup_dialog_options.menu_container.find('span.ui-icon-circle-b-help').show();
        }
        if(this.isResultSetEmpty()) return false;
    
        let action = 'recordExport'+(popup_dialog_options.format=='csv'?'CSV':'');
        window.hWin.HEURIST4.ui.showRecordActionDialog(action, popup_dialog_options);
    
/*
        var that = this;
    
        var q = "",
        layoutString,rtFilter,relFilter,ptrFilter;
        
        var isEntireDb = false;
        
        opts.isAll = (opts.isAll!==false);
        opts.multifile = (opts.multifile===true);

        if(opts.isAll){

            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                
                q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
                
                isEntireDb = (window.hWin.HAPI4.currentRecordset && 
                    window.hWin.HAPI4.currentRecordset.length()==window.hWin.HAPI4.sysinfo.db_total_records);
                    
            }

        }else{    //selected only

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");

        }

        if(q!=''){
            
            var script; 
            var params = '';
            if(true || $('#followPointers').is(':checked')){

                if(isEntireDb){
                    params =  'depth=0';
                }else {
                    if(opts.questionResolved!==true){
                        var $expdlg = window.hWin.HEURIST4.msg.showMsgDlg(
'<p>The records you are exporting may contain pointers to other records which are not in your current results set. These records may additionally point to other records.</p>'                
//+'<p>Heurist follows the chain of related records, which will be included in the XML or JSON output. The total number of records exported will therefore exceed the results count indicated.</p>'
//+'<p>To disable this feature and export current result only uncheck "Follow pointers"</p>'
+'<p style="padding:20px 0"><label><input type="radio" name="links" value="direct" style="float:left;margin-right:8px;" checked/>Follow pointers and relationship markers in records <b>(recommended)</b></label>'
+'<br><br><label><input type="radio" name="links" value="direct_links" style="float:left;margin-right:8px;"/>Follow only pointers, ignore relationship markers <warning about losing relationships></label>'
+'<br><br><label><input type="radio" name="links" value="none" style="float:left;margin-right:8px;"/>Don\'t follow pointers or relationship markers (you will lose any data which is referenced by pointer fields in the exported records)</label>'
+'<br><br><label><input type="radio" name="links" value="all" style="float:left;margin-right:8px;"/>Follow ALL connections including reverse pointers" (warning: any commonly used connection, such as to Places, will result in a near-total dump of the database)</label></p>'
                        , function(){ 
                            
                            var val = $expdlg.find('input[name="links"]:checked').val();
                            
                            opts.linksMode = val;
                            opts.questionResolved=true; 
                            that._exportRecords( opts ); 
                        },
                        {
                            yes: 'Proceed',
                            no: 'Cancel'
                        });
                        
                        return;
                    }
                    params =  'depth=all';
                }
                
            }
            params =  params + (opts.linksMode?('&linkmode='+opts.linksMode):'');  
            
            if(opts.format=='hml'){
                script = 'export/xml/flathml.php';                
                
                //multifile is for HuNI  
                params =  params + (opts.multifile?'&multifile=1':'');  
               
            }else{
                script = 'hserv/controller/record_output.php';
                params = params + '&format='+opts.format+'&defs=0&extended='+($('#extendedJSON').is(':checked')?2:1);
                
                if(opts.format=='gephi' && $('#limitGEPHI').is(':checked')){
                    params = params + '&limit=1000';    
                }
            }
            
            if(opts.save_as_file===true){          
                params = params + '&file=1'; //save as file
            }
                

            var url = window.hWin.HAPI4.baseURL + script + 
            q + 
            "&a=1"+
            "&db=" + window.hWin.HAPI4.database
            +'&'+params;

            window.open(url, '_blank');
        }
*/
        return false;
    },
     
    //
    // create popup to guide user for creating a template file w/ rectypes
    //
    _generateHeuristTemplate: function(){

        let $dlg;

        let content = "<div style='display: table'>"
                        + "<div style='margin-bottom: 10px'>"
                            + "<div class='header' style='display: table-cell;max-width: 125px; min-width: 125px;'>Download template: </div>"
                            + "<div style='display: table-cell'>"
                                + "<label for='template-xml'><input name='template-type' id='template-xml' type='radio' value='xml' checked='checked'> XML</label>"
                                + "<label for='template-json'><input name='template-type' id='template-json' type='radio' value='json'> JSON</label>"
                            + "</div>"
                        + "</div>"

                        + "<div>"
                            + "<div class='header' style='display: table-cell;max-width: 125px; min-width: 125px;'>Record types: </div>"
                            + "<div style='display: table-cell'>"
                                + "<label for='rectypes-all'><input id='rectypes-all' type='checkbox' checked='checked'> All Rectypes</label>"
                                + "<div style='margin: 5px 0px'>or</div>"
                                + "<button id='rectypes-select'>Select record types</button>"
                                + "<div style='margin: 10px 0px'>Selected Record Types: </div>"
                                + "<div "
                                    + "style='min-width:165px;max-width:165px;max-height:200px;min-height:200px;border: black solid 1px;padding:5px;margin-top:5px;overflow-y:auto;'"
                                    + " id='rectypes-list' data-ids=''>"
                                        + "<span style='display: inline-block;margin: 5px 0px;'> None </span>"
                                + "</div>"
                            + "</div>"
                        + "</div>"
                    + "</div>";

        let btns = {};
        btns['Download'] = function(){

            let template_type = $dlg.find('input[name="template-type"]:checked').attr('id');
            let rectype_ids = $dlg.find('div#rectypes-list').attr('data-ids');
            let is_all_rectypes = $dlg.find('input#rectypes-all').is(':checked');

            if(is_all_rectypes) { rectype_ids = 'y'; } // get all rectypes

            if(rectype_ids == null){
                window.hWin.HEURIST4.msg.showMsgFlash('Please select some record types...', 2000);
                return;
            }

            if(template_type == 'template-xml'){

                window.hWin.HEURIST4.util.downloadURL(window.hWin.HAPI4.baseURL
                        +'export/xml/flathml.php?file=1&'
                        +'rectype_templates='+ rectype_ids
                        +'&db='+window.hWin.HAPI4.database);
            }else if(template_type == 'template-json'){

                window.hWin.HEURIST4.util.downloadURL(window.hWin.HAPI4.baseURL
                    +'export/json/recordTemplate.php?'
                    +'rectype_ids='+ rectype_ids
                    +'&db='+window.hWin.HAPI4.database);
            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Please select what type of template you want...', 2000);
            }

            window.hWin.HEURIST4.msg.showMsgFlash('Downloading File...', 3000);
            $dlg.dialog('close');
        };
        btns['Close'] = function(){
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, 
            {title: 'Download XML or JSON template', yes: 'Download', no: 'Close'},
            {default_palette_class: 'ui-heurist-publish', dialogId: 'template_popup', width: 400, height: 500});

        $dlg.find('button#rectypes-select').button();

        $dlg.find('button#rectypes-select, div#rectypes-list').on('click', function(){

            let $selected_rectypes = $dlg.find('div#rectypes-list');

            let popup_options = {
                select_mode: 'select_multi',
                edit_mode: 'popup',
                isdialog: true,
                width: 440,
                title: 'Select record types',
                selection_on_init: $selected_rectypes.attr('data-ids').split(','),
                default_palette_class: 'ui-heurist-publish',

                onselect:function(event, data){

                    let ids = data.selection;

                    if(ids != null && window.hWin.HEURIST4.util.isArrayNotEmpty(ids)){

                        $selected_rectypes.attr('data-ids', data.selection.join(',')).text('');

                        for(let i = 0; i < ids.length; i++){

                            let name = $Db.rty(ids[i], 'rty_Name');

                            $selected_rectypes.append(
                                '<span class="truncate" style="display: inline-block;width: 155px; max-width: 155px;margin: 2.5px 0px" title="'+ name +'">'
                                    + name +
                                '</span>');

                            if((i+1) != ids.length){
                                $selected_rectypes.append('<br>');
                            }
                        }
                    }else{
                        $selected_rectypes.attr('data-ids', '').text('<span style="display: inline-block;margin: 5px 0px;"> None </span>');
                    }
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_options);
        });

        $dlg.find('input#rectypes-all').on('change', function(event){
            window.hWin.HEURIST4.util.setDisabled($dlg.find('button#rectypes-select, div#rectypes-list'), $(event.target).is(':checked'));
        }).trigger('change');

        return false;
    },

    //
    // Display message next to DB name, about available stable/alpha version
    //
    _show_version_message: function(){

        const that = this;

        if(this.version_message){
            return;
        }

        this.version_message = true;

        let is_alpha = window.hWin.HAPI4.baseURL.match(/h\d+-alpha|alpha/);
        let suggestion_txt = '';
        let styling = {float:'left', 'margin-left':'25px', width:'360px', 'font-size':'0.85em', cursor:'default'};

        if(!is_alpha){ // need to check that an alpha version is available on this server
            window.hWin.HAPI4.SystemMgr.check_for_alpha({a:'check_for_alpha'}, function(response){ 
                
                if(window.hWin.HEURIST4.util.isempty(response.data)){
                    return;
                }

                suggestion_txt = `<a style="cursor: pointer;text-decoration: underline;" href="${response.data + location.search}" id="lnk_change">`
                               + `Use the latest (alpha) version</a> (recommended)`;

                styling['margin-top'] = '1.2em';

                that.version_message = $("<div>")
                    .css(styling)
                    .insertAfter(that.div_dbname)
                    .html(suggestion_txt);
            });
        }else{ // currently on alpha

            suggestion_txt = 'This is the latest (alpha) version. If you are blocked by a new bug you can switch to the '
                + '<a style="cursor: pointer;text-decoration: underline;" href="#" id="lnk_change">standard version</a>'
                + ' PLEASE REPORT BUGS.';

            styling['margin-top'] = '0.9em';

            this.version_message = $("<div>")
                .css(styling)
                .insertAfter(this.div_dbname)
                .html(suggestion_txt);

            this._on(this.version_message.find('#lnk_change'), {
                click: () => {

                    let $dlg;
                    let msg = 'If you encounter <span style="text-decoration: underline">any</span> bug, we ask that you report it with the <a href="#" id="msg_bug_rpt">bug reporter</a>'
                        + ' - bugs are generally<br>'
                        + 'fixed within a day or so.<br><br>'
                        + 'We recommend that you use the alpha version unless you encounter a newly introduced bug<br>'
                        + 'which blocks your work, in which case you can revert to the standard (/heurist/) version.<br><br>'
                        + 'We recommend that you return to using the alpha version as soon as the bug is fixed (you<br>'
                        + 'should receive an adivce email, otherwise switch back in a couple of days).';

                        let btns = {};
                        btns['Continue using alpha version'] = () => { 
                            $dlg.dialog('close'); 
                        };
                        btns['Report bug and switch to standard version'] = () => {
                            $dlg.dialog('close');
                            window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport', {
                                onClose: () => {
                                    location.href = window.hWin.HAPI4.baseURL_pro + location.search;
                                }
                            });
                        };

                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Thanks for helping to test Heurist'}, {default_palette_class: 'ui-heurist-admin'});

                    $dlg.find('#msg_bug_rpt').on('click', function(){ // same action as proceed button
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport', {
                            onClose: () => {
                                location.href = window.hWin.HAPI4.baseURL_pro + location.search;
                            }
                        });
                    });
                }
            });
        }
    },

    /**
     * Disply notifications about certain features / functions to the user
     * Or, open the bug reporter monthly
     * 
     * @returns none
     */
    _getUserNotifications: function(){

        if(this._retrieved_notifications){ return; }

        const that = this;

        this._retrieved_notifications = true;

        let request = {
            a: 'get_user_notifications'
        };

        window.hWin.HAPI4.SystemMgr.get_user_notifications(request, function(response){

            if(window.hWin.HEURIST4.util.isempty(response.data) || response.status != window.hWin.ResponseStatus.OK){
                return;
            }

            let notifications = response.data;

            if(Object.keys(notifications).length == 1 && notifications['bug_report']){
                that.menuActionById('menu-help-bugreport');
                return;
            }
        });
    },

    /**
     * Popup to select record types to be rebuilt, or rebuild all
     * 
     * @param popup_options - popup/dialog options from menuActionHandler
     * 
     * @return none
     */
    _rebuildRecordTitles: function(popup_options){

        let $dlg, selected_rectypes = [];
        let base_url = `${window.hWin.HAPI4.baseURL}admin/verification/longOperationInit.php?type=titles&db=${window.hWin.HAPI4.database}`;

        let msg = "Please select which options to use for rebuilding record titles:<br><br>"
                + "<div>"
                    + "<label for='rectypes-all'><input id='rectypes-all' type='checkbox' checked='checked'> All record titles</label>"
                    + "<div style='margin: 5px 0px'>or</div>"
                    + "<button id='rectypes-select'>Select record types</button>"
                    + "<div style='margin: 10px 0px'>Selected Record Types: </div>"
                    + "<div "
                        + "style='min-width:165px;max-width:165px;max-height:200px;min-height:200px;border: black solid 1px;padding:5px;margin-top:5px;overflow-y:auto;'"
                        + " id='rectypes-list' data-ids=''>"
                            + "<span style='display: inline-block;margin: 5px 0px;'> None </span>"
                    + "</div>"
                + "</div>";

        let btn = {};
        btn[window.HR('Proceed')] = () => {

            let all_rectypes = $dlg.find('#rectypes-all').is(':checked');
            if(!all_rectypes && selected_rectypes.length == 0){
                return;
            }

            const rectypes = !all_rectypes ? `&recTypeIDs=${selected_rectypes.join(',')}` : '';

            $dlg.dialog('close');

            window.hWin.HEURIST4.msg.showDialog( `${base_url}${rectypes}`, popup_options );
        };
        btn[window.HR('Close')] = () => {
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btn, 
            {title: 'Rebuild record titles', yes: window.HR('Proceed'), no: window.HR('Close')}, 
            {default_palette_class: 'ui-heurist-admin', dialogId: 'rebuild_titles', width: 400, height: 450}
        );

        $dlg.find('button#rectypes-select').button();

        $dlg.find('button#rectypes-select, div#rectypes-list').on('click', function(){

            let $selected_rectypes = $dlg.find('div#rectypes-list');

            let popup_options = {
                select_mode: 'select_multi',
                edit_mode: 'popup',
                isdialog: true,
                width: 440,
                title: 'Select record types',
                selection_on_init: selected_rectypes,
                default_palette_class: 'ui-heurist-publish',

                onselect:function(event, data){

                    const ids = data.selection;

                    if(ids != null && window.hWin.HEURIST4.util.isArrayNotEmpty(ids)){

                        selected_rectypes = ids;
                        $selected_rectypes.text('');

                        for(let i = 0; i < ids.length; i++){

                            const name = $Db.rty(ids[i], 'rty_Name');

                            $selected_rectypes.append(
                                '<span class="truncate" style="display: inline-block;width: 155px; max-width: 155px;margin: 2.5px 0px; cursor: default;" title="'+ name +'">'
                                    + name +
                                '</span>');

                            if((i+1) != ids.length){
                                $selected_rectypes.append('<br>');
                            }
                        }
                    }else{
                        selected_rectypes = [];
                        $selected_rectypes.text('<span style="display: inline-block;margin: 5px 0px;"> None </span>');
                    }
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_options);
        });

        $dlg.find('input#rectypes-all').on('change', function(event){
            window.hWin.HEURIST4.util.setDisabled($dlg.find('button#rectypes-select, div#rectypes-list'), $(event.target).is(':checked'));
        }).trigger('change');
    }
});
