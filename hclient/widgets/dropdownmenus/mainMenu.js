/**
* mainMenu.js : Top Main Menu panel
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
        btn_visible_designer:false,
        btn_visible_newrecord:false
        // callbacks
    },

    _selectionRecordIDs:null,
    _current_query_string:'',

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element.css('height','100%').addClass('ui-heurist-header2')
        //
        // prevent double click to select text
        .disableSelection();

        this.div_logo = $( "<div>")
        .addClass('logo')
        .css({'width':'198px', 'height':'56px', 'float':'left', 'margin-top':'6px'})
        .appendTo( this.element );

        if(window.hWin.HAPI4.get_prefs('layout_theme')!='heurist'){
            this.div_logo.button();
        }

        var res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.version_new, window.hWin.HAPI4.sysinfo['version']);   
        var sUpdate = '';
        if(res==-2){ // -2=newer code on server
            sUpdate = '&nbsp;<span class="ui-icon ui-icon-alert" style="width:16px;display:inline-block;vertical-align: middle;cursor:pointer">';
        }

        $("<div>")
            .css({'font-size':'0.8em', 'text-align':'center', 'margin-left': '100px', 'padding-top':'16px', 'width':'100%'})
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
                /*var init_search = top.HEURIST.displayPreferences['defaultSearch'];
                if(!window.hWin.HEURIST4.util.isempty(init_search)){
                    var request = {q: init_search, w: 'a', detail: 'detail', source:'init' };
                    window.hWin.HAPI4.SearchMgr.doSearch( this, request );
                }*/
            }
        });

        this.div_dbname = $( "<div>")
            .css({'float':'left', 'padding-left':'2em', 'margin-top':'1.6em', 'text-align':'center' })
            .appendTo(this.element);
            
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.sysinfo.dbrecent)){
            
            this.div_dbname.css({
                'margin-left':'50px',
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")'});
            
            var wasCtrl = false;
            var selObj = window.hWin.HEURIST4.ui.createSelector(null, window.hWin.HAPI4.sysinfo.dbrecent);        
            $(selObj).css({'font-size':'1.3em', 'font-weight':'bold','border':'none', 'min-width':'150px' })
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
            
            $("<div>").css({'font-size':'1.3em', 'font-weight':'bold', 'padding-left':'22px', 'margin-left':'50px',
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

        this.divMainMenu = $( "<div>")
        //.css({'float':'right', 'padding-right':'2em', 'padding-top':'0.2em', 'text-align':'right', 'width':'40em' })
        //.css({'position':'absolute', 'right':0, 'padding-right':'2em', 'padding-top':'0.2em', 'text-align':'right', 'width':'40em' })  //two rows
        .css({'position':'absolute', 'right':0, 'padding-right':'2em', 'padding-top':'1.5em', 'text-align':'right' })  //one rows
        .addClass('logged-in-only')
        .appendTo(this.element);

        /* moved to Profile menu
        var logout = $('<a>',{text: 'log out', href:'#' })
        .css({'font-size':'1.1em', 'padding-top':'0.5em', 'padding-left':'0.5em', 'float':'right'})
        .appendTo(this.divMainMenu);
        this._on(logout, { click: this.logout });

        //current user name is displayed as Profile menu header
        this.divCurrentUser = $( "<div>",{'id':'divCurrentUser'})
        .css({'font-size':'1.1em', 'padding-top':'0.5em', 'float':'right'})
        .appendTo(this.divMainMenu);
        */


        this.divMainMenuItems = $('<ul>')
        .addClass('horizontalmenu')
        .css({'float':'left', 'padding-right':'2em'})
        .appendTo( this.divMainMenu );

        this.divProfileItems3 = $( "<ul>").css('float','right').addClass('horizontalmenu').appendTo( this.divMainMenu );
        this._initMenu('ProfileH3', this.divProfileItems3);

        /* new entityfeatures
        this.divProfileItems = $( "<ul>").css('float','right').addClass('horizontalmenu').appendTo( this.divMainMenu );
        this._initMenu('Profile', this.divProfileItems);
        */
        
        if(window.hWin.HAPI4.sysinfo['layout']=='original'){
            this._initMenu('Database');
            this._initMenu('Import');
            this._initMenu('Export');
        }
        this._initMenu('Help');
        
        this.divMainMenuItems.menu();
        //new this.divProfileItems.menu();
        this.divProfileItems3.menu();
        
        this.divProfileItems3.find('.ui-menu-item').css({'padding':'0em !important'});


        this.divMainMenuItems_lo = $('<ul>')
        .addClass('horizontalmenu')
        .addClass('logged-out-only')
        .css({'float':'right'})
        .appendTo( this.element );

        this._initMenu('Database_lo', this.divMainMenuItems_lo); //logout case
        this._initMenu('Help_lo', this.divMainMenuItems_lo);
        this.menu_Help_lo.find('.logged-in-only').hide();
        this.divMainMenuItems_lo.menu();


        // SECOND LINE --------------------------------------------------
        if(this.options.btn_visible_designer){
            this.div_BottomRow = $("<div>").css({ 'position':'absolute', top:46, left: '2px', right:0, 'padding-right': '2em' }).appendTo( this.element );

            this.btn_switch_to_design = $( "<button>", {
                text: window.hWin.HR("go to database administration")
            })
            .css('width','160px')
            .appendTo( this.div_BottomRow )
            .button()
            .click(function( event ) {
                var url = window.hWin.HAPI4.baseURL + "admin/adminMenuStandalone.php?db=" + window.hWin.HAPI4.database;
                window.open(url, "_blank");
            });
        }
        if(this.options.btn_visible_newrecord){
            this.btn_add_record = $( "<button>", {
                text: window.hWin.HR("add new record")
            })
            .css('float','right')
            .addClass('logged-in-only')
            .appendTo( this.div_BottomRow )
            .button({icons: {
                primary: "ui-icon-circle-plus"
            }})
            .click( function(){window.hWin.HAPI4.SystemMgr.verify_credentials(that._addNewRecord);} );
        }


        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SELECT + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECTON_REC_SEARCHSTART,
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
                }else{
                    if(data){
                        data = data.selection?data.selection:data;
                    }
                    that._selectionRecordIDs = window.hWin.HAPI4.getSelection(data, true);

                }
        });

        $(this.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS, function(e, data) {
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

        if(window.hWin.HAPI4.has_access()){
            //this.divCurrentUser.html( window.hWin.HAPI4.currentUser.ugr_FullName );
            //': <a href="../../common/connect/login.php?logout=1&amp;db='+window.hWin.HAPI4.database+'">log&nbsp;out</a>');

            $(this.element).find('.logged-in-only').show();
            $(this.element).find('.logged-out-only').hide();

            this.menu_Help.find('.logged-in-only').show();

        }else{
            //not logged -guest
            $(this.element).find('.logged-in-only').hide();
            $(this.element).find('.logged-out-only').show();
            //this.divCurrentUser.empty();
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
    },


    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(this.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS);
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SELECT + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECTON_REC_SEARCHSTART);

        // remove generated elements
        this.btn_Admin.remove();
        this.btn_Profile.remove();
        //new this.menu_Profile.remove();
        this.menu_Profile3.remove();
        this.btn_Database.remove();
        this.menu_Database.remove();
        this.btn_Import.remove();
        this.menu_Import.remove();
        this.btn_Export.remove();
        this.menu_Export.remove();
        this.btn_Help.remove();
        this.menu_Help.remove();

        this.btn_Database_lo.remove();
        this.btn_Help_lo.remove();
        this.menu_Help_lo.remove();

    },

    _initMenu: function(name, parentdiv){

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
            .position({my: "center top", at: "center bottom", of: parent });
            //$( document ).one( "click", function() { menu.hide(); });
            return false;
        };

        var link;

        if(name=='Database_lo'){
            link = $('<a>',{
                text: 'Open database',
                href: window.hWin.HAPI4.baseURL + 'common/connect/getListOfDatabases.php?popup=1&v=4&db=' 
                        + window.hWin.HAPI4.database,
                target: '_blank'
            });

            this._on(link, {
                click:
                function(event){
                    $(menu).hide()
                    that._onPopupLink(event);
                }
                //that._onPopupLink
            });

        //}else if(name=='Profile'){

        }else if(name=='ProfileH3'){

            link = $('<a href="#"><span style="display:inline-block;padding-right:20px">Settings</span>' 
            +'<div class="ui-icon-user" style="display:inline-block;font-size:16px;width:16px;line-height:10px;vertical-align:bottom;"></div>'
            +'&nbsp;<div class="usrFullName" style="display:inline-block">'
            +window.hWin.HAPI4.currentUser.ugr_FullName
            +'</div><div style="position:relative;" class="ui-icon ui-icon-carat-1-s"></div></a>');
                                                                                                           
        }else{
            link = $('<a>',{
                text: window.hWin.HR((name=='Help_lo'?'Help':name)), href:'#'
            });
            
            /*if(name=='Help'){
                link.css({'padding-top': '4px'});    
            }*/            
        }
        
        this['btn_'+name] = $('<li>').append(link)
        .appendTo( parentdiv?parentdiv:this.divMainMenuItems );

        
        // Load content for all menus except Database when user is logged out
        if(name!='Database_lo'){
            
            var name2 = (name=='Help_lo'?'Help':name);

            this['menu_'+name] = $('<ul>')
            .load(
                window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/mainMenu'+name2+'.html',
              function(){    //add ?t=+(new Date().getTime()) to avoid cache in devtime
             
                that['menu_'+name].find('.list-menu-only').hide();
             
                that['menu_'+name].addClass('menu-or-popup')
                .css({'position':'absolute', 'padding':'5px'})
                .appendTo( that.document.find('body') )
                //.addClass('ui-menu-divider-heurist')
                .menu({select: function(event, ui){ 
                        that._menuActionHandler(event, ui.item.attr('id'), ui.item.attr('data-logaction'));
                        return false; 
                }});

                that._refresh();
                
                that._initLinks(that['menu_'+name]);
            })
            //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
            .hide();

        }

        this._on( this['btn_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });
        this._on( this['menu_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });

    },


    //
    //
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
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(){window.hWin.HEURIST4.msg.showDialog(url, options);});
        }else{
            window.hWin.HEURIST4.msg.showDialog(url, options);
        }        

        event.preventDefault();
        return false;
    },


    //init listeners for auto-popup links
    _initLinks: function(menu){

        var that = this;

        menu.find('[name="auto-popup"]').each(function(){
            var ele = $(this);
            var href = ele.attr('href');
            if(!window.hWin.HEURIST4.util.isempty(href)){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + window.hWin.HAPI4.database;

                if(ele.hasClass('h3link')){
                    href = window.HAPI4.baseURL + href;
                    //h3link class on menus implies location of older (vsn 3) code
                }
                
                ele.attr('href', href);
                that._on(ele, {
                    click:
                    //that._onPopupLink
                    function(event){
                        $(menu).hide()
                        that._onPopupLink(event);
                    }

                    //
                });
            }
        });

        menu.find("a[href^='mailto']").click(function(e)
        {
          var el = $(this);
          //window.hWin.HEURIST4.util.checkProtocolSupport(el.data("href"));
          var t;
          $(window).blur(function() {
                // The browser apparently responded, so stop the timeout.
                clearTimeout(t);
          });

          t = setTimeout(function() {
                // The browser did not respond after 500ms, so open an alternative URL.
                window.hWin.HEURIST4.msg.showMsgErr('mailto_fail');
          }, 500);
        });

    },

    _menuActionHandler: function(event, action, action_log){
        
        var that = this;
        
        if(action_log){
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }
        
        var requiredLevel = 0;
        
        window.hWin.HAPI4.SystemMgr.verify_credentials(
        function(){
        
        if(action == "menu-help-bugreport"){
            
           window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport');
        }else 
        if(action == "menu-profile-preferences"){
            that._editPreferences();
        }else 
        if(action == "menu-profile-tags"){
            window.hWin.HEURIST4.ui.showEntityDialog('usrTags');
        }else 
        if(action == "menu-profile-reminders"){
            window.hWin.HEURIST4.ui.showEntityDialog('usrReminders');
        }else 
        if(action == "menu-profile-files"){
            window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles');
        }else 
        if(action == "menu-profile-groups"){
            window.hWin.HEURIST4.ui.showEntityDialog('sysGroups');
        }else 
        if(action == "menu-profile-info"){
            window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', 
                {edit_mode:'editonly', usr_ID: window.hWin.HAPI4.currentUser['ugr_ID']});
        }else 
        if(action == "menu-profile-users"){ //for admin only
            window.hWin.HEURIST4.ui.showEntityDialog('sysUsers');
        }else 
        if(action == "menu-profile-import"){  //for admin only
            that._importUsers();
        }else if(action == "menu-database-refresh"){
            that._refreshLists( true );
            
        }else if(action == "menu-import-csv"){ // Result set
            that.importCSV();
        }else if(action == "menu-export-hml-resultset"){ // Current resultset, including rule-based expansion if applied
            that.exportHML(true,false,false); // isAll, includeRelated, multifile
        }else if(action == "menu-export-hml-selected"){ //Currently selected records only
            that.exportHML(false,false,false);
        }else if(action == "menu-export-hml-plusrelated"){ // Current resulktset with any related records
            that.exportHML(true,true,false);
        }else if(action == "menu-export-hml-multifile"){ // selected + related
            that.exportHML(true,false,true);
        }else if(action == "menu-export-kml"){
            that.exportKML(true);
        }else if(action == "menu-export-rss"){
            that.exportFeed('rss');
        }else if(action == "menu-export-atom"){
            that.exportFeed('atom');
        }else if(action == "menu-help-tipofday"){
            showTipOfTheDay(false);
        }
        
        },
        requiredLevel //needed level of credentials any, logged (by default), admin of group, db admin, db owner
        );

        //event.preventDefault();
        
    },


    importCSV: function(){
        
           var url = window.hWin.HAPI4.baseURL + "hclient/framecontent/import/importRecordsCSV.php?db="+ window.hWin.HAPI4.database;
           
           var body = $(this.document).find('body');
           var dim = {h:body.innerHeight(), w:body.innerWidth()};
           
           window.hWin.HEURIST4.msg.showDialog(url, {    
                title: 'Import Records from CSV/TSV',
                height: dim.h-5,
                width: dim.w-5,
                'context_help':window.hWin.HAPI4.baseURL+'context_help/importRecordsCSV.html #content'
                //callback: _import_complete
            });
    },

    _addNewRecord: function(){


        var url = window.hWin.HAPI4.baseURL+ "records/add/addRecordPopup.php?db=" + window.hWin.HAPI4.database;

        window.hWin.HEURIST4.msg.showDialog(url, { height:550, width:700, title:'Add Record',
            callback:function(response) {
                /*
                var sURL = window.hWin.HAPI4.baseURL + "common/php/reloadCommonInfo.php";
                top.HEURIST.util.getJsonData(
                sURL,
                function(response){
                if(response){
                top.HEURIST.rectypes.usageCount = response;
                top.HEURIST.search.createUsedRectypeSelector(true);
                }
                },
                "db="+_db+"&action=usageCount");
                */
            }
        });                   

    },



    exportHML: function(isAll, includeRelated, multifile){

        var q = "",
        layoutString,rtFilter,relFilter,ptrFilter,
        depth = 0;

        if(isAll){

            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
            }

        }else{    //selected only

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "ids:"+this._selectionRecordIDs.join(",");

        }

        if (includeRelated){

            depth = 1;

            /* TODO: includeRelated + depth=1 has been commented out with no explanation. Why? Delete?
            var rtFilter = top.HEURIST.search.getPushDownFilter('rectype');
            if (rtFilter[0] > depth){ // if filter max depth is greater than depth -> adjust depth
            depth = rtFilter[0];
            }
            rtFilter = rtFilter[1];
            var relFilter = top.HEURIST.search.getPushDownFilter('reltype');
            if (relFilter[0] > depth){
            depth = relFilter[0];
            }
            relFilter = relFilter[1];
            var ptrFilter = top.HEURIST.search.getPushDownFilter('ptrtype');
            if (ptrFilter[0] > depth){
            depth = ptrFilter[0];
            }
            ptrFilter = ptrFilter[1];
            var layoutString = top.HEURIST.search.getLayoutString();
            if (layoutString[0] > depth){
            depth = layoutString[0];
            }
            layoutString = layoutString[1];
            var selFilter = top.HEURIST.search.getSelectedString();
            if (selFilter[0] > depth){
            depth = selFilter[0];
            }
            selFilter = selFilter[1];
            */
        }

        if(q!=''){

            var url = window.hWin.HAPI4.baseURL + "export/xml/flathml.php?"+
            "w=all"+
            "&a=1"+
            "&depth="+depth +
            "&q=" + q +
            /*(layoutString ? "&" + layoutString : "") +
            (selFilter ? "&" + selFilter : "") +
            (rtFilter ? "&" + rtFilter : "") +
            (relFilter ? "&" + relFilter : "") +
            (ptrFilter ? "&" + ptrFilter : "") +*/
            "&db=" + window.hWin.HAPI4.database +
            (multifile?'&file=1':'');

            window.open(url, '_blank');
        }

        return false;
    },



    exportKML: function(isAll){

        var q = "";
        if(isAll){

            q = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);

            if(q=='?'){
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
                return;
            }


        }else{

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");
        }

        if(q!=''){
            var url = window.hWin.HAPI4.baseURL + "export/xml/kml.php" + q + "&a=1&depth=1&db=" + window.hWin.HAPI4.database;
            window.open(url, '_blank');
        }

        return false;
    },



    exportFeed: function(mode){

        if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
            var q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);

            if(!window.hWin.HEURIST4.util.isempty(q)){
                var w = window.hWin.HEURIST4.current_query_request.w;
                if(window.hWin.HEURIST4.util.isempty(w)) w = 'a';
                if(mode=='rss') {
                    mode = '';
                }else{
                    mode = '&feed='+mode;
                }
                var rules = '';
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    rules = '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }


                var url = window.hWin.HAPI4.baseURL + 'export/xml/feed.php?&q=' + q + '&w=' + w + '&db=' + window.hWin.HAPI4.database + mode + rules;
                window.open(url, '_blank');
            }
        }
    },



    /**
    * Reload database structure image on client side
    */
    _refreshLists: function( is_message ){
        window.hWin.HAPI4.SystemMgr.get_defs_all( is_message, this.document);
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
            var themeSwitcher = $("#layout_theme").themeswitcher(
                {initialText: currentTheme.charAt(0).toUpperCase() + currentTheme.slice(1),
                currentTheme:currentTheme,
                onSelect: function(){
                    currentTheme = this.currentTheme;
            }});

            //from prefs to ui
            allFields.each(function(){
                if(prefs[this.id] || (top.HEURIST && top.HEURIST.displayPreferences[this.id])){
                    if($(this).hasClass('h3pref')){

                        if(top.HEURIST){
                            var val = top.HEURIST.displayPreferences[this.id];
                            if(this.type=="checkbox"){
                                this.checked = (val=="true");
                            }else{
                                $(this).val(val);
                            }
                        }

                    }else if(this.type=="checkbox"){
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
                var h3pref = [], h3pref_val = [], val;

                allFields.each(function(){
                    if($(this).hasClass('h3pref')){
                        if(this.type=="checkbox"){
                            val = this.checked?"true":"false";
                        }else{
                            val = $(this).val();
                        }
                        h3pref.push(this.id);
                        h3pref_val.push(val);
                    }else if(this.type=="checkbox"){
                        request[this.id] = this.checked?"1":"0";
                    }else{
                        request[this.id] = $(this).val();
                    }
                });
                request.layout_theme = currentTheme; //themeSwitcher.getSelected();//    getCurrentTheme();
                //$('#layout_theme').themeswitcher.

                //save Vsn 3 preferences
                if(top.HEURIST && top.HEURIST.util){
                    top.HEURIST.util.setDisplayPreference(h3pref, h3pref_val);
                }
                
                //save preferences in session
                window.hWin.HAPI4.SystemMgr.save_prefs(request,
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                            var prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];
                            var ask_reload = (prefs['layout_language'] != request['layout_language'] ||
                                prefs['layout_theme'] != request['layout_theme'] ||
                                prefs['layout_id'] != request['layout_id']);

                            var reload_map = (prefs['mapcluster_grid'] != request['mapcluster_grid'] ||    
                                prefs['mapcluster_on'] != request['mapcluster_on'] || 
                                prefs['search_detail_limit'] != request['search_detail_limit'] ||
                                prefs['mapcluster_count'] != request['mapcluster_count'] ||   
                                prefs['mapcluster_zoom'] != request['mapcluster_zoom']);
                                
                            //check help toggler and bookmark search - show/hide
                            window.hWin.HEURIST4.ui.applyCompetencyLevel(request['userCompetencyLevel']);
                            
                            if(prefs['bookmarks_on'] != request['bookmarks_on']){
                                $('.heurist-bookmark-search').css('display',
                                    (request['bookmarks_on']=='1')?'inline-block':'none');
                            }


                            window.hWin.HAPI4.currentUser['ugr_Preferences'] = request; //wrong since request can have only part of peferences!!!!!
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
                            }else if(reload_map){
                                //reload map frame forcefully
                                var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
                                if(app && app.widget){
                                    $(app.widget).app_timemap('reloadMapFrame'); //call method
                                }
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
                height: 580,
                width: 800,
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
                                                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
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
                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                    window.hWin.HAPI4.setCurrentUser(null);
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                    that._refresh();
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );

    }
});
