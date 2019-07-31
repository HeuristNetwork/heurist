/**
* mainMenu.js : Top Main Menu panel
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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


$.widget( "heurist.mainMenu", {

    // default options
    options: {
        host_logo:null
    },
    
    menues:{},

    _current_query_string:'',

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element.css({'height':'100%'}).addClass('ui-heurist-header2')
            .disableSelection();// prevent double click to select text

        this.div_logo = $( "<div>")
        .addClass('logo')   //width was 198
        .css({'width':'150px', 'float':'left', 'margin-top':'6px'}) //'height':'56px', 
        .appendTo( this.element );

        if(window.hWin.HAPI4.get_prefs('layout_theme')!='heurist'){
            this.div_logo.button();
        }

        //validate server side version  - compare version of code in server where main index database and this server version
        var res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.version_new, window.hWin.HAPI4.sysinfo['version']);   
        var sUpdate = '';
        if(res==-2){ // -2=newer code on server
            sUpdate = '&nbsp;<span class="ui-icon ui-icon-alert" style="width:16px;display:inline-block;vertical-align: middle;cursor:pointer">';
        }

        $("<div>")                                               
            .css({'font-size':'0.6em', 'text-align':'center', 'margin-left': '85px', 'padding-top':'12px', 'width':'100%'})
            .html('<span style="padding-left:20px">v'+window.hWin.HAPI4.sysinfo.version+sUpdate+'</span>').appendTo( this.div_logo );
            
        // bind click events
        this._on( this.div_logo, {
            click: function(event){
                if($(event.target).is('span.ui-icon-alert')){
                    window.hWin.HEURIST4.msg.showMsgDlg(                    
                    "Your server is running Heurist version "+window.hWin.HAPI4.sysinfo['version']+" The current stable version of Heurist (version "
                    +window.hWin.HAPI4.sysinfo.version_new+") is available from <a target=_blank href='https://github.com/HeuristNetwork/heurist'>GitHub</a> or "
                    +"<a target=_blank href='http://HeuristNetwork.org'>HeuristNetwork.org</a>. We recommend updating your copy of the software if the sub-version has changed "
                    +"(or better still with any change of version).<br/><br/>"
                    +"Heurist is copyright (C) 2007 - 2017 The University of Sydney and available as Open Source software under the GNU-GPL licence. "
                    +"Beta versions of the software with new features may also be available at the GitHub repository or linked from the HeuristNetwork home page.");
                }else{
                    document.location.reload();    
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
            .css({'float':'right', margin:'1.1em', 'font-size':'0.8em'})
            .addClass('ui-heurist-header2')
            .appendTo( this.element )
            .click(
                function(){
                    that.btn_dashboard.hide();
                    
                   var params = 
                        {viewmode: 'thumbs', showonstartup: 1 };
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', params);     
                    
                    
                    window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard',
                        {onClose:function(){
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                        }  });}
            ); 

        
        this.div_dbname = $( "<div>")
            //.css({'float':'right', 'margin-top':'1.2em', 'padding-right':'2em' })
            .css({'float':'left', 'margin-top':'0.8em', 'margin-left':'5em'})
            .appendTo(this.element);
            
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.sysinfo.dbrecent)){
            
            this.div_dbname.css({
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")'});
            
            var wasCtrl = false;
            var selObj = window.hWin.HEURIST4.ui.createSelector(null, window.hWin.HAPI4.sysinfo.dbrecent);        
            $(selObj).css({'font-size':'1em', 'font-weight':'bold','border':'none', outline:0,
                           'min-width':'150px', 'margin-left':'25px', })
            .click(function(event){
                wasCtrl = event.shiftKey;
//console.log('1'+wasCtrl+'  '+event.metaKey);                
            })
            .change(function(event){
                if(window.hWin.HAPI4.database!=$(event.target).val()){
                    var url =  window.hWin.HAPI4.baseURL+'?db='+$(event.target).val();
                    $(event.target).val(window.hWin.HAPI4.database);
    //console.log('2'+wasCtrl);
                    if(wasCtrl){
                        location.href = url;
                    }else{
                        window.open(url, '_blank');
                    }
                }
                $(event.target).blur();//remove focus
            })
            .addClass('ui-heurist-header2')
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
        var he = this.element.height();

        this.divMainMenu = $( "<div>")
            .css({'position':'absolute', 'left':24, bottom:he/8, 'text-align':'left'})  //one rows
            //.addClass('logged-in-only')
            .appendTo(this.element);
        
        this.divMainMenuItems = $('<ul>')
                .addClass('horizontalmenu')
                .css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em', 'font-size':'0.8em'})
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
        
        if(__include('profile')) this._initMenu('Profile', -1, this.divProfileItems);

        if(__include('Database')) this._initMenu('Database', -1);            
        if(__include('Structure')) this._initMenu('Structure', 0);
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
        if(__include('Help')) this._initMenu('Help', -1);
        
        this.divMainMenuItems.menu();
        this.divProfileItems.menu().removeClass('ui-menu-icons');
                        /*if(name=="Profile"){
                    //.removeClass('ui-menu-item')
                    that.divProfileItems.find('.ui-menu-item').css({'padding-left':'0px !important'});//'0em '});            
console.log('>>>>'+that.divProfileItems.find('.ui-menu-item').css('padding-left')        );
                }*/
            

        //host logo and link    
        if(window.hWin.HAPI4.sysinfo.host_logo){
            
            $('<div style="height:40px;background:none;padding-left:4px;float:right;color:white">'
                +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                +'" target="_blank" style="text-decoration: none;color:white;">'
                        +'<label>hosted by: </label>'
                        +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                        +'" height="40" align="center"></a></div>')
            .appendTo( this.divMainMenu );
        }
        
        // LISTENERS --------------------------------------------------
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART,
            function(e, data) {
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                    if(data) {
                        var query_request = data;

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

        /*
        if(window.hWin.HAPI4.has_access()){
            $(this.element).find('.logged-in-only').show();
            $(this.element).find('.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
            $(this.element).find('.logged-out-only').show();
        }*/
        
        function ___set_menu_item_visibility(idx, item, is_showhide){

                var lvl_user = $(item).attr('data-user-admin-status'); //level of access by workgroup membership
                
                var lvl_exp = $(item).attr('data-user-experience-level');  //level by ui experience
                
                var is_visible = true;
                
                var item = $(item).is('li')?$(item):$(item).parent();
                
                if(lvl_user>=0){
                    //@todo lvl_user=1 is_admin
                    is_visible = (lvl_exp!=3) && window.hWin.HAPI4.has_access(lvl_user);
                    var elink = $(item).find('a');
                    if(is_showhide){
                        if(is_visible){
                            item.show();  
                        }else{
                            item.hide();  
                        }
                        
                    }else
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
                
                //0 advance, 1-experienced, 2-beginner
                if(lvl_exp>=0 && is_visible){
                    
                        var usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);//beginner by default
                        
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
        for (var key in this.menues){
            var menu = this.menues[key];
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
        
        //need to update position of carat icon according width of user name
        /*var ele = $('#carat1');
        if(ele.length>0){
            ele.css({'left': (ele.parent().width())+'px'});// (link.width()-16+'px !important')});
        }
        */
        
        
        var cms_record_id = window.hWin.HEURIST4.util.getUrlParameter('cms', window.hWin.location.search);
        if(cms_record_id>0){
            window.hWin.HEURIST4.ui.showEditCMSDialog( cms_record_id );    
        }else
        if(window.hWin.HAPI4.sysinfo.db_has_active_dashboard>0){
            this.btn_dashboard.show();  
        }else{
            this.btn_dashboard.hide();  
        }
    },


    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS);
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART);
        
        this.div_logo.remove();
        this.divMainMenu.remove();
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
    //
    _initMenu: function(name, access_level, parentdiv, exp_level){

        var that = this;
        var myTimeoutId = -1;
        
        //show hide function
        var _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 800);
            //$( ele ).delay(800).hide();
        },
        _show = function(ele, parent) {
            clearTimeout(myTimeoutId);
            
            $('.menu-or-popup').hide(); //hide other
            var menu = $( ele )
            //.css('width', this.btn_user.width())
            .show()
            .position({my: "left-2 top", at: "left bottom", of: parent });
            //$( document ).one( "click", function() { menu.hide(); });
            return false;
        };

        var link;

        if(name=='Profile'){
            
            //profile menu header consists of two labels: Settings and Current user name
            
            link = $('<a href="#"><span style="display:inline-block;padding-right:20px">Settings</span>' 
            +'<div class="ui-icon-user" style="display:inline-block;font-size:16px;width:16px;line-height:10px;vertical-align:bottom;"></div>'
            +'&nbsp;<div class="usrFullName" style="display:inline-block">'
            +window.hWin.HAPI4.currentUser.ugr_FullName
            +'</div><div style="position:relative;" class="ui-icon ui-icon-carat-1-s"></div></a>');
                                                                                                           
        }
        else{
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
        
        // Load content for all menus except Database when user is logged out

        this.menues['menu_'+name] = $('<ul>')
        .load(
            window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu'+name+'.html',
          function(){    //add ?t=+(new Date().getTime()) to avoid cache in devtime
          
            that.menues['menu_'+name].find('.list-menu-only').hide();
         
            that.menues['menu_'+name].addClass('menu-or-popup')
            .css({'position':'absolute', 'padding':'5px'})
            .appendTo( that.document.find('body') )
            //.addClass('ui-menu-divider-heurist')
            .menu({select: function(event, ui){ 
                    that.menuActionHandler(event, ui.item.find('a'));
                    return false; 
            }});
            
            that.menues['menu_'+name].find('a').each(function(idx,item){
                if($(item).attr('data-ext')!=1){
                    var href = $(item).attr('href');
                    if(href!='#' && !window.hWin.HEURIST4.util.isempty(href)){
                        $(item).attr('href','#')
                        $(item).attr('data-link', href);
                    }
                }
            });
            
            that._refresh();
            
         
        })
        //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
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
    // NOT USED
    //
    _onPopupLink: function(event){

        var body = $(this.document).find('body');
        var dim = {h:body.innerHeight   (), w:body.innerWidth()},
        link = $(event.target),
        that = this;

        var options = { title: link.html() };

        if (link.hasClass('small')){
            options.height=dim.h*0.6; options.width=dim.w*0.5;
        }else if (link.hasClass('portrait')){
            options.height=dim.h*0.8; options.width=dim.w*0.55;
            if(options.width<700) options.width = 700;
        }else if (link.hasClass('large')){
            options.height=dim.h*0.8; options.width=dim.w*0.8;
        }else if (link.hasClass('verylarge')){
            options.height = dim.h*0.95;
            options.width  = dim.w*0.95;
        }else if (link.hasClass('fixed')){
            options.height=dim.h*0.8; options.width=800;
        }else if (link.hasClass('fixed2')){
            if(dim.h>700){ options.height=dim.h*0.8;}
            else { options.height=dim.h-40; }
            options.width=800;
        }else if (link.hasClass('landscape')){
            options.height=dim.h*0.5;
            options.width=dim.w*0.8;
        }

        var url = link.attr('href');
        if (link.hasClass('currentquery')) {
            url = url + that._current_query_string
        }
        
        if (link.hasClass('refresh_structure')) {
               options['afterclose'] = this._refreshLists;
        }

        
        if(link && link.attr('data-logaction')){
            window.hWin.HAPI4.SystemMgr.user_log(link.attr('data-logaction'));
        }
        
        if(link && link.attr('data-nologin')!='1'){
            //check if login
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){window.hWin.HEURIST4.msg.showDialog(url, options);});  //not used
        }else{
            window.hWin.HEURIST4.msg.showDialog(url, options);
        }        

        event.preventDefault();
        return false;
    },
    
    //
    // returns all menu entries as array - used in dropdown command selector in dashboard editor
    //
    menuGetAllActions: function(){
        
        var res = [];    
            
        for (var key in this.menues){
            var menu = this.menues[key];
            var links = $(menu).find('a');
            $.each(links, function(idx, link){
                var link = $(link);
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
    menuActionById: function(menu_entry_id){
        
        for (var key in this.menues){
            var menu = this.menues[key];
            var ele = $(menu).find('#'+menu_entry_id);
            if(ele.length>0 && ele.is('a')){
                this.menuActionHandler(null, ele);            
                break;
            }
        }
    },
    //
    //
    //
    menuActionHandler: function(event, item){
        
        var that = this;
        
        if($(item).attr('data-ext')==1){
          console.log('EXIT')
          return;  
        } 
        
        var action = item.attr('id');
        var action_log = item.attr('data-logaction');
        var action_level = item.attr('data-user-admin-status');
        var action_passworded = item.attr('data-pwd');
        if(!action_passworded && !window.hWin.HAPI4.has_access(2)) action_passworded = item.attr('data-pwd-nonowner');
        var href = item.attr('data-link');
        var target = item.attr('target');

        //  -1 no verification
        //  0 logged (DEFAULT)
        //  groupid  - admin of group  
        //  1 - db admin (admin of group #1)
        //  2 - db owner
        var requiredLevel = (action_level==-1 || action_level>=0) ?action_level :0;
        
        window.hWin.HAPI4.SystemMgr.verify_credentials(
            function( entered_password ){
            
            if(action_log){
                window.hWin.HAPI4.SystemMgr.user_log(action_log);
            }
        
        if(action == "menu-database-browse"){
            
                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', {
                    select_mode:'select_single',
                    isdialog: true,
                    //container: $('#frame_container_div'),
                    onselect:function(event, data){

                        if(data && data.selection && data.selection.length==1){
                            var db = data.selection[0];
                            if(db.indexOf('hdb_')===0) db = db.substr(4);
                            window.open( window.hWin.HAPI4.baseURL + '?db=' + db, '_blank');
                        }
                                                
                    }
                });

        }else 
        if(action == "menu-cms-create"){
            
            var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
            if(RT_CMS_HOME>0 && window.hWin.HEURIST4.rectypes.counts
                 && window.hWin.HEURIST4.rectypes.counts[RT_CMS_HOME]>0){
            
                window.hWin.HEURIST4.msg.showMsgDlg(
                    'You already have a website. Are you sure you want to create an additional site?',
                    function(){ window.hWin.HEURIST4.ui.showEditCMSDialog( -1 ); });
            }else{
                window.hWin.HEURIST4.ui.showEditCMSDialog( -1 );
            }
            
        }else 
        if(action == "menu-cms-edit"){
            
            that._select_CMS_Home();
            
        }else 
        if(action == "menu-database-properties"){
            
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification');
        }else
        if(action == "menu-database-rollback"){
           
            window.hWin.HEURIST4.msg.showMsgDlg('Although rollback data has been recorded, '
                    + 'there is currently no end-user interface way of rolling '
                    + 'back the database. <br><br>'+window.hWin.HR('New_Function_Contact_Team'));
        }else 
        if(action == "menu-structure-import" || action == "menu-structure-import-express"){

            var opts = {isdialog: true};
            if(action == "menu-structure-import-express"){
                opts['source_database_id'] = 3;    
                opts['title'] = 'Import structural definitions into current database from Heurist Reference Set';
            }
            
            var manage_dlg = $('<div id="heurist-dialog-importRectypes-'+window.hWin.HEURIST4.util.random()+'">')
                    .appendTo( $('body') )
                    .importStructure( opts );
            
        }else 
        if(action == "menu-structure-mimetypes"){
            
                window.hWin.HEURIST4.ui.showEntityDialog('defFileExtToMimetype',
                                                {edit_mode:'inline', width:900});

        }else 
        if(action == "menu-structure-refresh"){
            
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, window.hWin.document);
                                                
        }else 
        if(action == "menu-export-csv"){
            
            if(that.isResultSetEmpty()) return;
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordExportCSV');
            
            
        }else 
        if(action == "menu-export-hml-resultset" || action == "menu-export-hml-multifile"){
            
            var q = '';
            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
            }
            
            if(q!=''){
            
                var url = window.hWin.HAPI4.baseURL + "export/xml/flathml.php?"+
                "w=all"+
                "&a=1"+
                "&depth=0"+
                "&q=" + q +
                "&db=" + window.hWin.HAPI4.database +
                ( (action == "menu-export-hml-multifile")?'&file=1':'');

                window.open(url, '_blank');
            }
            
        }else 
        if(action == "menu-export-kml"){
            
            var q = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
            if(q=='?'){
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
                return;
            }
            var url = window.hWin.HAPI4.baseURL + "export/xml/kml.php" + q + "&a=1&depth=1&db=" + window.hWin.HAPI4.database;
            window.open(url, '_blank');
            
            
        }else 
        if(action == "menu-import-add-record"){
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');
            
        }else 
        if(action == "menu-import-email" || 
           action == "menu-manage-interdbtransfer" ||
           action == "menu-faims-import" || action == "menu-faims-export"
           ){

            window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('New_Function_Conversion')
                    + '<br><br>'+window.hWin.HR('New_Function_Contact_Team'));
                 
                 
        }else 
        if(action == 'menu-extract-pdf'){
            //this menu should not be in main menu. IJ request
            var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_resultList');
            if(app && app.widget){
                $(app.widget).resultList('callResultListMenu', 'menu-selected-extract-pdf'); //call method
            }
            
        }else 
        if(action == "menu-manage-dashboards"){
            
           window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard',
                        {onClose:function(){
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                        }  });
        
        }else
        if(action == "menu-help-bugreport"){
            
           window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport');
        }else 
        if(action == "menu-profile-tags"){
            window.hWin.HEURIST4.ui.showEntityDialog('usrTags');
        }else 
        if(action == "menu-profile-reminders"){
            window.hWin.HEURIST4.ui.showEntityDialog('usrReminders');
        }else 
        if(action == "menu-profile-files"){
            window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles',{width:950});
        }else 
        if(action == "menu-profile-groups"){
            window.hWin.HEURIST4.ui.showEntityDialog('sysGroups');
        }else 
        if(action == "menu-profile-info"){
            window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', 
                {edit_mode:'editonly', rec_ID: window.hWin.HAPI4.currentUser['ugr_ID']});
        }else 
        if(action == "menu-profile-users"){ //for admin only
            window.hWin.HEURIST4.ui.showEntityDialog('sysUsers');
        }else 
        if(action == "menu-profile-preferences"){
            that._editPreferences();
        }else
        if(action == "menu-profile-import"){  //for admin only
            that._importUsers();
        }else 
        if(action == "menu-database-refresh"){
            that._refreshLists( true );
        }else 
        if(action == "menu-help-tipofday"){
            showTipOfTheDay(false);
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
                  
                  var win = window.open(href, 'emailWindow');
                  if (win && win.open && !win.closed) win.close();                  
                  return;
            }
            
    
            if(!(href.indexOf('http://')==0 || href.indexOf('https://')==0)){
                href = window.hWin.HAPI4.baseURL + href + (href.indexOf('?')>=0?'&':'?') + 'db=' + window.hWin.HAPI4.database;        
            }
            
            if(!window.hWin.HEURIST4.util.isempty(entered_password)){
                 href =  href + '&pwd=' + entered_password;
            }
            
            if(!window.hWin.HEURIST4.util.isempty(target)){
                window.open( href, target);    
            }else{
                
                var options = {};
                var size_type = item.attr('data-size');
                var dlg_title = item.attr('data-header');
                var dlg_help = item.attr('data-help');

                var size_w = item.attr('data-dialog-width');
                var size_h = item.attr('data-dialog-height');

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
                    options['context_help'] = window.hWin.HAPI4.baseURL+'context_help/'+dlg_help+'.html #content';
                }
                
                var position = { my: "center", at: "center", of: window.hWin };
                var maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
                if(options['width']>maxw) options['width'] = maxw*0.95;
                var maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
                if(options['height']>maxh) options['height'] = maxh*0.95;
                
                if (item.hasClass('upload_files')) {
                    //beforeClose
                    options['afterclose'] = function( event, ui ) {

                            if(window.hWin.HEURIST4.filesWereUploaded){
                                
                                var buttons = {};
                                buttons[window.hWin.HR('OK')]  = function() {
                                    var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                    $dlg.dialog( "close" );
                                    that.menuActionById('menu-index-files');
                                };                                 
                                
                                window.hWin.HEURIST4.msg.showMsgDlg('The files you have uploaded will not appear as records in the database'
                                +' until you run Import > index multimedia This function will open when you click OK.',buttons);
                            }
                    }
                }
                
                window.hWin.HEURIST4.msg.showDialog( href, options);    
            }
            
        }
        
        
        },
            requiredLevel, //needed level of credentials any, logged (by default), admin of group, db admin, db owner
            action_passworded    //this is type of password, if it is not set action is to allowed - otherwise need to enter password
        );

        if(event) window.hWin.HEURIST4.util.stopEvent(event);
    },
    
    //
    // similar in resultListMenu
    //
    isResultSetEmpty: function(){
        var recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsgDlg('No results found. '
            +'Please modify search/filter to return at least one result record.');
            return true;
        }else{
            return false;
        }
    },
    


    /**
    * Open Edit Preferences dialog (@todo: ? move into separate file?)
    */
    _editPreferences: function()
    {
        var that = this;

        var $dlg = $("#heurist-dialog").addClass('ui-heurist-bg-light');
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
            var prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];
            
            var allFields = $dlg.find('input,select');

            var currentTheme = prefs['layout_theme'];
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
            prefs['searchQueryInBrowser'] = window.hWin.HAPI4.get_prefs_def('searchQueryInBrowser', 1);
            prefs['mapcluster_on'] = window.hWin.HAPI4.get_prefs_def('mapcluster_on', 1);
            prefs['entity_btn_on'] = window.hWin.HAPI4.get_prefs_def('entity_btn_on', 1);
            
            var map_controls = window.hWin.HAPI4.get_prefs_def('mapcontrols', 'bookmark,geocoder,selector,print,publish');
            map_controls = map_controls.split(',');
            prefs['mctrl_bookmark'] = 0;prefs['mctrl_geocoder'] = 0;
            prefs['mctrl_selector'] = 0;prefs['mctrl_print'] = 0;
            prefs['mctrl_publish'] = 0;
            for(var i=0;i<map_controls.length;i++){
                prefs['mctrl_'+map_controls[i]] = 1;
            }
            
            window.hWin.HEURIST4.ui.createTemplateSelector( $dlg.find('#map_template'), [{key:'',title:'na'}],
                            window.hWin.HAPI4.get_prefs_def('map_template', null));

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
            
            var ele = $dlg.find('#mapcluster_on');
            $dlg.find('#mapcluster_grid').change(function(){ ele.prop('checked', true)});
            $dlg.find('#mapcluster_count').change(function(){ ele.prop('checked', true)});
            $dlg.find('#mapcluster_zoom').change(function(){ ele.prop('checked', true)});

            //save to preferences
            function __doSave(){

                var request = {};
                var val;
                var map_controls = [];

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

                            var prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];
                            
                            var ask_reload = (prefs['layout_language'] != request['layout_language'] ||
                                prefs['layout_theme'] != request['layout_theme'] ||
                                prefs['layout_id'] != request['layout_id']);

                            var reload_map = (prefs['mapcluster_grid'] != request['mapcluster_grid'] ||    
                                prefs['mapcluster_on'] != request['mapcluster_on'] || 
                                prefs['search_detail_limit'] != request['search_detail_limit'] ||
                                prefs['mapcluster_count'] != request['mapcluster_count'] ||   
                                prefs['mapcluster_zoom'] != request['mapcluster_zoom'] ||
                                prefs['deriveMapLocation'] != request['deriveMapLocation'] || 
                                prefs['mapcontrols'] != request['mapcontrols']);
                                
                            //check help toggler and bookmark search - show/hide
                            window.hWin.HEURIST4.ui.applyCompetencyLevel(request['userCompetencyLevel']);
                            
                            if(prefs['bookmarks_on'] != request['bookmarks_on']){
                                $('.heurist-bookmark-search').css('display',
                                    (request['bookmarks_on']=='1')?'inline-block':'none');
                            }
                            if(prefs['entity_btn_on'] != request['entity_btn_on']){
                                var is_vis = (request['entity_btn_on']=='1');
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

                            $dlg.dialog( "close" );

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
                                    var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
                                    if(app && app.widget){
                                        $(app.widget).app_timemap('reloadMapFrame'); //call method
                                    }
                                }
                                
                                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                            }

                        }else{
                            var message = $dlg.find('.messages');
                            message.addClass( "ui-state-highlight" );
                            message.text(response.message);
                        }
                    }
                );
            }

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
            })
        });

    } //end _editPreferences

    
    , _importUsers: function (){

        var options = {
            title: 'Select database with users to be imported',
            select_mode: 'select_single',
            pagesize: 300,
            edit_mode: 'none',
            use_cache: true,
            except_current: true,
            onselect:function(event, data){
                if(data && data.selection && data.selection.length>0){
                        var selected_database = data.selection[0].substr(4);
                        
                        var options2 = {
                            title: 'Select users in '+selected_database+' to be imported',
                            database: selected_database,
                            select_mode: 'select_multi',
                            edit_mode: 'none',
                            onselect:function(event, data){
                                if(data && data.selection &&  data.selection.length>0){
                                    var selected_users = data.selection;

                                    var options3 = {
                                        title: 'Allocate imported users to work groups',
                                        select_mode: 'select_roles',
                                        selectbutton_label: 'Allocate roles',
                                        sort_type_int: 'recent',
                                        edit_mode: 'none',
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
                                                        
                                            var request = {};
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
                                    };              
                                    
                                    window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options3);
                                }
                            }
                        };
                        
                        
                        window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options2);
                }
            }
        };    
    
        window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);
    
    
    }    

    // the same in profile.js
    // TODO: use an include instead of repeating logout function in this file and profile.js
    , logout: function(){

        var that = this;

        window.hWin.HAPI4.SystemMgr.logout(
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    window.hWin.HAPI4.setCurrentUser(null);
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                    that._refresh();
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );

    },
    
    //
    //
    //                
    _select_CMS_Home: function (){
        
        var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        if(!(RT_CMS_HOME>0)){
            window.hWin.HEURIST4.msg.showMsgDlg('This function is still in development. You will need record types '
                +'99-51, 99-52 and 99-53 which will be made available as part of Heurist_Reference_Set. '
                +'You may contact ian.johnson@sydney.edu.au if you want to test out this function prior to release in mid July 2019.');
            return;
        }
        
        if(window.hWin.HEURIST4.rectypes.counts && window.hWin.HEURIST4.rectypes.counts[RT_CMS_HOME]==1){

            window.hWin.HEURIST4.ui.showEditCMSDialog( 0 ); //load the only entry at once
            return;
        }
        
        var popup_options = {
                        select_mode: 'select_single', //select_multi
                        select_return_mode: 'recordset',
                        edit_mode: 'popup',
                        selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                        title: window.hWin.HR('Select or create a website home record'),
                        rectype_set: RT_CMS_HOME,
                        parententity: 0,
                        title: 'Select Website',
                        
                        layout_mode: 'listonly',
                        width:500, height:400,
                        
                        resultList:{
                            show_toolbar: false,
                            view_mode:'list',
                            searchfull:null,
                            renderer:function(recordset, record){
                                var recID = recordset.fld(record, 'rec_ID')
                                var recTitle = recordset.fld(record, 'rec_Title'); 
                                var recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
                                var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'+recTitle_strip_all+'</div>';
                                return html;
                            }
                        },
                        
                        onselect:function(event, data){
                                 if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    var recordset = data.selection;
                                    window.hWin.HEURIST4.ui.showEditCMSDialog( recordset.getOrder()[0] );
                                 }
                        }
        };//popup_options
        
        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
        
    }            
            

});
