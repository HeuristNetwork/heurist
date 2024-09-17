/**
* controlPanel.js : Header panel with logo, main menu and dashboard (optionally)
*                   It inits actionHandler object
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
    
    menuActionHandler - main event handler
*/

$.widget( "heurist.controlPanel", {

    // default options
    options: {
        host_logo:null,
        login_inforced: true,
        is_h6style: true
    },
    
    menues:{},
    actionHandler: null,
    
    //move widgets/cms/manageCms
    cms_home_records_count:0,
    cms_home_private_records_ids:0,
    sMsgCmsPrivate:'',

    //glags    
    _initial_search_already_executed: false,
    _retrieved_notifications: false,

    version_message: null, // container for message about available alpha/stable version
    
    _init: function() {

        let that = this;
        
        this.actionHandler = window.hWin.HAPI4.actionHandler;
        
        const url = window.hWin.HAPI4.baseURL
                        +'hclient/widgets/cpanel/controlPanel.html?t=' 
                        +window.hWin.HEURIST4.util.random()
        
        //load content
        this.element.load(url, 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }else{
                    that._initControls()
                }
            });
        
    },
    
    _initControls:function(){
        
        let that = this;
        
        this.element.css({'height':'100%'}).addClass('ui-heurist-header2')
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
            
        // bind click events
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


        // current and last databases dropdown
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
        this.divMainMenu.hide();                
            
        this.divProfileMenu = $( "<div>")
        .css({'float':'right', 'margin-top':'1em', 'font-size':'1.1em'})  
        .appendTo(this.element);

            
        this.divProfileMenu.buttonsMenu({
           menuContent:
                    '<div>'
                    +'<ul title="Help" class+"horizontalmenu" style="margin-left:150px" data-icon="ui-icon-circle-b-help">'
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
                    +'<ul title="Profile" class+"horizontalmenu" data-icon="ui-icon-user">'
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
           /*
            [{text:'Help', icon:'ui-icon-circle-b-help', items:
                    ['menu-help-online','menu-help-quick-tips','menu-help-website','menu-help-roadmap','menu-help-devhist',
                     '-',   
                     'menu-help-bugreport','menu-help-emailteam','menu-help-emailadmin','menu-help-acknowledgements','menu-help-about']
                },
                {text:'Profile', icon:'ui-icon-user', items:[
                     'menu-profile-preferences','menu-profile-tags','menu-profile-reminders',
                     '-',
                     'menu-profile-info','menu-profile-groups',
                     'menu-profile-users','menu-profile-import','menu-profile-logout'
                ]}]*/
           , 
           manuActionHandler:function(action){
                that.actionHandler.executeActionById('menu-manage-dashboards')
           }
        });
            
/*        
        this.divMainMenuItems = $('<ul>')
                .addClass('horizontalmenu')
                .css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em', 'font-size':'0.9em'})
                .appendTo( this.divMainMenu );
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
        }else{
            if(__include('Help')) this._initMenu('Help', -1);    
        }
        if(__include('profile')) this._initMenu('Profile', -1, this.divProfileItems);
        
        this.divMainMenuItems.menu();
        this.divProfileItems.menu().removeClass('ui-menu-icons');
*/            
        //host logo and link -----------------------------------    
        /*
        window.hWin.HAPI4.sysinfo.host_logo = 'https://t3.ftcdn.net/jpg/03/74/19/26/360_F_374192621_mCSB5FIskwdMEJZou3DuMN8N2Z6IzXqb.jpg'
        window.hWin.HAPI4.sysinfo.host_url = 'https://t3.ftcdn.net'l
        
        if(window.hWin.HAPI4.sysinfo.host_logo){
            
            $('<div style="height:40px;background:none;padding-left:4px;float:right;color:white">'
                +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                +'" target="_blank" style="text-decoration: none;color:white;">'
                        +'<span>hosted by: </span>'
                        +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                        +'" height="40" align="center"></a></div>')
            .appendTo( this.divMainMenu );
        }
        */
        
        // LISTENERS --------------------------------------------------
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
    

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        let that = this;

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
        //loop top level menu
        /* disabled
        for (let key in this.menues){
            let menu = this.menues[key];
            if(menu.is('li')){
                ___set_menu_item_visibility(0, menu, true);  //top level menu - show/hide               
            }else{
                $(menu).find('li,a').each(___set_menu_item_visibility); //enable/disbale dropdown items
            }
        }
        */
        
        // Replace "Profile" label for menu to current user name
        $(this.element).find('.usrFullName').text(window.hWin.HAPI4.currentUser.ugr_FullName);

        if(this.options.login_inforced && !window.hWin.HAPI4.has_access()){
            this.doLogin();
        }else {
            this._show_version_message();
            this._performInitialSearch();
            this._getUserNotifications();
        }
    },

    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART);
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
        
        this.div_logo.remove();
        this.divMainMenu.remove();
        this.divProfileMenu.remove();

        /* remove generated elements
        this.btn_Admin.remove();
        this.btn_Profile.remove();
        this.menu_Profile.remove();
       
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
            window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/mainMenu'+name+'.html',
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
    
    // dialog_options - not used
    // parameters from dialog are taken from data- attributes of li element in menu html
    //
    
    // item is li element and now it action from json
    
    menuActionHandler: function(event, item, dialog_options){
        
        let action = item.attr('id');
        
        if (action.indexOf('menu-cms')<0){
            this.actionHandler.executeActionById(action);
            if(event) window.hWin.HEURIST4.util.stopEvent(event);
            return;
        }
        
        
        let that = this;
        
        if($(item).attr('data-ext')==1){
          return;  
        } 
        
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
                $('.ui-menu6').slidersMenu('switchContainer', section, true);
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
            
        if(action == "menu-cms-create"){

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

                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: `Your database is missing the ${missing}.<br>`
                                        +'These record types can be downloaded from the Heurist_Bibliographic database (# 6) using Design > Browse templates.<br>'
                                        +'You will need to refresh your window after downloading the record type(s) for the additions to take affect.',
                                error_title: 'Missing required record types'
                            });
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
        else if(action == 'menu-extract-pdf'){ //not used
            //this menu should not be in main menu. IJ request
            let app = window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_resultList');
            if(app && app.widget){
                $(app.widget).resultList('callResultListMenu', 'menu-selected-extract-pdf'); //call method
            }
        }
        else if(action == 'menu-subset-set'){  //not used   Set current result as a working subset
            
            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
            if(widget){
                $('.ui-menu6').slidersMenu('hideDatabaseOverview');
                widget.resultList('callResultListMenu', 'menu-subset-set'); //call method
            }
            
        }else 
        if(!window.hWin.HEURIST4.util.isempty(href) && href!='#'){
            
            
            if(href.indexOf('mailto:')==0){
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
                                        
                                    that.actionHandler.executeActionById('menu-index-files');
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

                } else if(that.options.login_inforced){
                    window.hWin.location  = window.HAPI4.baseURL
                }
            }); 
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
            
        
        let cms_record_id = window.hWin.HEURIST4.util.getUrlParameter('cms', window.hWin.location.search);
        let cmd = window.hWin.HEURIST4.util.getUrlParameter('cmd', window.hWin.location.search);
        if(cms_record_id>0 || !window.hWin.HEURIST4.util.isempty(cmd)){
                //ignore initial search of some menu command is called from url or need to open cms editor
            
                //initial parameters 
                //1. open CMS edit
                let cms_record_id = urlParams.get('cms'); 
                if(cms_record_id>0){
                    this.actionHandler.executeActionById('menu-cms-edit',{record_id:cms_record_id});
                }else{
                //2. executes arbitrary command
                    let cmd = urlParams.get('cmd'); 
                    if(cmd){
                        this.actionHandler.executeActionById(cmd);
                    }
                }
            
        }else 
        if(!window.hWin.HAPI4.is_publish_mode && window.hWin.HAPI4.sysinfo['db_total_records']>0){

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

                this._dashboardVisibility( true ); //after login
        }
    },

    //
    //  show/hide dashboard panel
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
              
               setTimeout( function(){ that._adjustHeight(); },is_startup?1000:10)
               
        }
        
    },
    
    //
    //  adjust header and main panel after dashboard visibility on/off
    //
    _adjustHeight: function(){

        let ele = this.element.parents('#layout_panes');
        if(ele){
            let h = 50;
            
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
               
                let layout = $('.ui-layout-container').layout();
                layout.resizeAll();
            }
            
        }
        
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
                window.hWin.HAPI4.actionHandler.executeActionById('menu-help-bugreport');
                return;
            }
        });
    },

});

