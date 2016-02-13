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

        if(top.HAPI4.get_prefs('layout_theme')!='heurist'){
            this.div_logo.button();
        }


        $("<div>").css({'font-size':'0.8em', 'text-align':'center', 'padding-top':'32px', 'width':'100%'}).text("v"+top.HAPI4.sysinfo.version).appendTo( this.div_logo );

        // bind click events
        this._on( this.div_logo, {
            click: function(){

                var init_search = top.HEURIST.displayPreferences['defaultSearch'];
                if(!top.HEURIST4.util.isempty(init_search)){
                    var request = {q: init_search, w: 'a', detail: 'detail', source:'init' };
                    top.HAPI4.SearchMgr.doSearch( this, request );
                }else{
                    $( "#heurist-about" ).dialog("open");
                }
            }
        });

        this.div_dbname = $( "<div>").css({'float':'left', 'padding-left':'2em', 'padding-top':'2em', 'text-align':'center' }).appendTo(this.element);


        $("<div>").css({'font-size':'1.3em', 'font-weight':'bold', 'padding-left':'22px', 'margin-left':'50px',
            'background-position': 'left center',
            'background-repeat': 'no-repeat',
            'background-image': 'url("'+top.HAPI4.basePathV4+'hclient/assets/database.png")' })
            .text(top.HAPI4.database).appendTo( this.div_dbname );

        /*$("<span>")
            .addClass('ui-icon ui-icon-database') //    .css({'margin-left':'50px'})
            .appendTo( this.div_dbname );

        $("<span>").text(top.HAPI4.database)
            .css({'font-size':'1.3em', 'font-weight':'bold', 'padding-left':'22px'})
            .appendTo( this.div_dbname );*/


        // MAIN MENU-----------------------------------------------------

        this.divMainMenu = $( "<div>")
        //.css({'float':'right', 'padding-right':'2em', 'padding-top':'0.2em', 'text-align':'right', 'width':'40em' })
        //.css({'position':'absolute', 'right':0, 'padding-right':'2em', 'padding-top':'0.2em', 'text-align':'right', 'width':'40em' })  //two rows
        .css({'position':'absolute', 'right':0, 'padding-right':'2em', 'padding-top':'1.5em', 'text-align':'right' })  //one rows
        .addClass('logged-in-only')
        .appendTo(this.element);

        var logout = $('<a>',{text: 'log out', href:'#' })
        .css({'font-size':'1.1em', 'padding-top':'0.5em', 'padding-left':'0.5em', 'float':'right'})
        .appendTo(this.divMainMenu);
        this._on(logout, { click: this.logout });

        this.divCurrentUser = $( "<div>",{'id':'divCurrentUser'})
        .css({'font-size':'1.1em', 'padding-top':'0.5em', 'float':'right'})
        .appendTo(this.divMainMenu);


        this.divMainMenuItems = $('<ul>')
        .addClass('horizontalmenu')
        .css({'float':'left', 'padding-right':'2em'})
        .appendTo( this.divMainMenu );

        this.divProfileItems = $( "<ul>")
        .css('float','right')
        .addClass('horizontalmenu')
        .appendTo( this.divMainMenu );



        this._initMenu('Profile', this.divProfileItems);
        this._initMenu('Database');
        this._initMenu('Import');
        this._initMenu('Export');
        this._initMenu('Help');
        this.divMainMenuItems.menu();
        this.divProfileItems.menu();


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
                text: top.HR("go to database administration")
            })
            .css('width','160px')
            .appendTo( this.div_BottomRow )
            .button()
            .click(function( event ) {
                var url = top.HAPI4.basePathV3 + "admin/adminMenu.php?db=" + top.HAPI4.database;
                window.open(url, "_blank");
            });
        }
        if(this.options.btn_visible_newrecord){
            this.btn_add_record = $( "<button>", {
                text: top.HR("add new record")
            })
            .css('float','right')
            .addClass('logged-in-only')
            .appendTo( this.div_BottomRow )
            .button({icons: {
                primary: "ui-icon-circle-plus"
            }})
            .click( function(){ that._addNewRecord(); });
        }


        $(this.document).on(top.HAPI4.Event.ON_REC_SELECT + ' ' + top.HAPI4.Event.ON_REC_SELECTON_REC_SEARCHSTART,
            function(e, data) {
                if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){
                    if(data) {
                        var query_request = data;

                        that._current_query_string = '&w='+query_request.w;
                        if(!top.HEURIST4.util.isempty(query_request.q)){
                            that._current_query_string = that._current_query_string
                            + '&q=' + encodeURIComponent(query_request.q);
                        }
                    }
                }else{
                    if(data){
                        data = data.selection?data.selection:data;
                    }
                    that._selectionRecordIDs = top.HAPI4.getSelection(data, true);

                }
        });

        $(this.document).on(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT, function(e, data) {
            that._refresh();
        });


        setTimeout(function(){
            var ishelp_on = top.HAPI4.get_prefs('help_on');
            that._toggleHelp(ishelp_on);
            },2000);

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

        if(top.HAPI4.is_logged()){
            this.divCurrentUser.html( top.HAPI4.currentUser.ugr_FullName );
            //': <a href="../../common/connect/login.php?logout=1&amp;db='+top.HAPI4.database+'">log&nbsp;out</a>');

            $(this.element).find('.logged-in-only').show();
            $(this.element).find('.logged-out-only').hide();

            this.menu_Help.find('.logged-in-only').show();

        }else{
            //not logged -guest
            $(this.element).find('.logged-in-only').hide();
            $(this.element).find('.logged-out-only').show();


            this.divCurrentUser.empty();
        }

    },


    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(this.document).off(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT);
        $(this.document).off(top.HAPI4.Event.ON_REC_SELECT + ' ' + top.HAPI4.Event.ON_REC_SELECTON_REC_SEARCHSTART);

        // remove generated elements
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
            .position({my: "left top", at: "left bottom", of: parent });
            //$( document ).one( "click", function() { menu.hide(); });
            return false;
        };

        var link;

        if(name=='Database_lo'){
            link = $('<a>',{
                text: 'Open database',
                href: top.HAPI4.basePathV3 + 'common/connect/getListOfDatabases.php?popup=1&v=4&db=' + top.HAPI4.database,
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
        }else{
            link = $('<a>',{
                text: top.HR((name=='Help_lo'?'Help':name)), href:'#'
            });
        }

        this['btn_'+name] = $('<li>').append(link)
        .appendTo( parentdiv?parentdiv:this.divMainMenuItems );

        // Load content for all menus except Database when user is logged out
        if(name!='Database_lo'){

            this['menu_'+name] = $('<ul>')
            .load(top.HAPI4.basePathV4+'hclient/widgets/topmenu/mainMenu'+
             (name=='Help_lo'?'Help':name)+'.html?t='+(new Date().getTime()), function(){
                that['menu_'+name].addClass('menu-or-popup')
                .css({'position':'absolute', 'padding':'5px'})
                .appendTo( that.document.find('body') )
                //.addClass('ui-menu-divider-heurist')
                .menu({select: function(event, ui){ that._menuActionHandler(event, ui.item.attr('id')); return false; }});

                if(top.HAPI4.is_logged()){
                    that['menu_'+name].find('.logged-in-only').show();
                }else{
                    that['menu_'+name].find('.logged-in-only').hide();
                }


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

        top.HEURIST4.msg.showDialog(url, options);

        event.preventDefault();
        return false;
    },



    //init listeners for auto-popup links
    _initLinks: function(menu){

        var that = this;

        menu.find('[name="auto-popup"]').each(function(){
            var ele = $(this);
            var href = ele.attr('href');
            if(!top.HEURIST4.util.isempty(href)){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + top.HAPI4.database;

                if(ele.hasClass('h3link')){
                    href = window.HAPI4.basePathV3 + href;
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
          //top.HEURIST4.util.checkProtocolSupport(el.data("href"));
          var t;
          $(window).blur(function() {
                // The browser apparently responded, so stop the timeout.
                clearTimeout(t);
          });

          t = setTimeout(function() {
                // The browser did not respond after 500ms, so open an alternative URL.
                top.HEURIST4.msg.showMsgErr('mailto_fail');
          }, 500);
        });

    },

    _menuActionHandler: function(event, action){

        var that = this;
        var p = false;
        if(action == "menu-profile-preferences"){
            this._editPreferences(); p=true;
        }else if(action == "menu-database-refresh"){
            this._refreshLists(); p=true;
        }else if(action == "menu-export-hml-0"){ // Result set
            this.exportHML(true,false,false); p=true; // isAll, includeRelated, ishuni
        }else if(action == "menu-export-hml-1"){ //selected
            this.exportHML(false,false,false); p=true;
        }else if(action == "menu-export-hml-2"){ // selected
            this.exportHML(true,true,false); p=true;
        }else if(action == "menu-export-hml-3"){ // selected + related
            this.exportHML(true,false,true); p=true;
        }else if(action == "menu-export-kml"){
            this.exportKML(true); p=true;
        }else if(action == "menu-export-rss"){
            this.exportFeed('rss'); p=true;
        }else if(action == "menu-export-atom"){
            this.exportFeed('atom'); p=true;
        }else if(action == "menu-help-inline"){

            var ishelp_on = (top.HAPI4.get_prefs('help_on')=='1')?'0':'1';
            top.HAPI4.currentUser['ugr_Preferences']['help_on'] = ishelp_on;

            this._toggleHelp(ishelp_on); p=true;

        }else if(action == "menu-help-tipofday"){
            showTipOfTheDay(false); p=true;
        }

        if( p ){
            event.preventDefault();
        }
    },



    _addNewRecord: function(){


        var url = top.HAPI4.basePathV3+ "records/add/addRecordPopup.php?db=" + top.HAPI4.database;

        top.HEURIST4.msg.showDialog(url, { height:550, width:700, title:'Add Record',
            callback:function(responce) {
                /*
                var sURL = top.HAPI4.basePathV3 + "common/php/reloadCommonInfo.php";
                top.HEURIST.util.getJsonData(
                sURL,
                function(responce){
                if(responce){
                top.HEURIST.rectypes.usageCount = responce;
                top.HEURIST.search.createUsedRectypeSelector(true);
                }
                },
                "db="+_db+"&action=usageCount");
                */
            }
        });

    },



    exportHML: function(isAll, includeRelated, ishuni){

        var q = "",
        layoutString,rtFilter,relFilter,ptrFilter,
        depth = 0;

        if(isAll){

            if(!top.HEURIST4.util.isnull(top.HEURIST4.current_query_request)){
                q = encodeURIComponent(top.HEURIST4.current_query_request.q);
                if(!top.HEURIST4.util.isempty(top.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(top.HEURIST4.current_query_request.rules);
                }
            }

        }else{    //selected only

            if (!top.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                top.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
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

            var url = top.HAPI4.basePathV3 + "export/xml/flathml.php?"+
            "w=all"+
            "&a=1"+
            "&depth="+depth +
            "&q=" + q +
            /*(layoutString ? "&" + layoutString : "") +
            (selFilter ? "&" + selFilter : "") +
            (rtFilter ? "&" + rtFilter : "") +
            (relFilter ? "&" + relFilter : "") +
            (ptrFilter ? "&" + ptrFilter : "") +*/
            "&db=" + top.HAPI4.database +
            (ishuni?'&file=1':'');

            window.open(url, '_blank');
        }

        return false;
    },



    exportKML: function(isAll){

        var q = "";
        if(isAll){

            q = top.HEURIST4.util.composeHeuristQuery2(top.HEURIST4.current_query_request);

            if(q=='?'){
                top.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
                return;
            }


        }else{

            if (!top.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                top.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");
        }

        if(q!=''){
            var url = top.HAPI4.basePathV3 + "export/xml/kml.php" + q + "&a=1&depth=1&db=" + top.HAPI4.database;
            window.open(url, '_blank');
        }

        return false;
    },



    exportFeed: function(mode){

        if(!top.HEURIST4.util.isnull(top.HEURIST4.current_query_request)){
            var q = encodeURIComponent(top.HEURIST4.current_query_request.q);

            if(!top.HEURIST4.util.isempty(q)){
                var w = top.HEURIST4.current_query_request.w;
                if(top.HEURIST4.util.isempty(w)) w = 'a';
                if(mode=='rss') {
                    mode = '';
                }else{
                    mode = '&feed='+mode;
                }
                var rules = '';
                if(!top.HEURIST4.util.isempty(top.HEURIST4.current_query_request.rules)){
                    rules = '&rules=' + encodeURIComponent(top.HEURIST4.current_query_request.rules);
                }


                var url = top.HAPI4.basePathV3 + 'export/xml/feed.php?&q=' + q + '&w=' + w + '&db=' + top.HAPI4.database + mode + rules;
                window.open(url, '_blank');
            }
        }
    },



    /**
    * Reload database structure image on client side
    */
    _refreshLists: function(){

        top.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
            if(response.status == top.HAPI4.ResponseStatus.OK){
                top.HEURIST4.rectypes = response.data.rectypes;
                top.HEURIST4.terms = response.data.terms;
                top.HEURIST4.detailtypes = response.data.detailtypes;

                if(top.HEURIST && top.HEURIST.rectypes){
                    top.HEURIST.util.reloadStrcuture();
                }else{
                    top.HEURIST4.msg.showMsgDlg('Database structure definitions in memory have been refreshed.<br>'+
                        'You may need to reload pages to see changes.');
                }

            }
        });

    },



    /**
    * Open Edit Preferences dialog (@todo: ? move into separate file?)
    */
    _editPreferences: function()
    {
        var that = this;

        var $dlg = $("#heurist-dialog").addClass('ui-heurist-bg-light');
        $dlg.empty();

        $dlg.load(top.HAPI4.basePathV4+"hclient/widgets/profile/profile_preferences.html?t="+(new Date().time), function(){

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                $(this).html(top.HR($(this).html()));
            })
            $dlg.find('.header').css({'min-width':'300px', 'width':'300px'});

            //fill list of languages
            //fill list of layouts
            initProfilePreferences();

            //assign values to form fields from top.HAPI4.currentUser['ugr_Preferences']
            var prefs = top.HAPI4.currentUser['ugr_Preferences'];
            var allFields = $dlg.find('input,select');

            var currentTheme = prefs['layout_theme'];
            var themeSwitcher = $("#layout_theme").themeswitcher(
                {initialText: currentTheme.charAt(0).toUpperCase() + currentTheme.slice(1),
                currentTheme:currentTheme,
                onSelect: function(){
                    currentTheme = this.currentTheme;
            }});

            //form prefs to ui
            allFields.each(function(){
                if(prefs[this.id] || top.HEURIST.displayPreferences[this.id]){
                    if($(this).hasClass('h3pref')){

                        var val = top.HEURIST.displayPreferences[this.id];
                        if(this.type=="checkbox"){
                            this.checked = (val=="true");
                        }else{
                            $(this).val(val);
                        }

                    }else if(this.type=="checkbox"){
                        this.checked = (prefs[this.id]=="1" || prefs[this.id]=="true")
                    }else{
                        $(this).val(prefs[this.id]);
                    }
                };
            });



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
                top.HAPI4.SystemMgr.save_prefs(request,
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){

                            var prefs = top.HAPI4.currentUser['ugr_Preferences'];
                            var ask_reload = (prefs['layout_language'] != request['layout_language'] ||
                                prefs['layout_theme'] != request['layout_theme'] ||
                                prefs['layout_id'] != request['layout_id']);

                            //check help toggler and bookmark search - show/hide
                            if(prefs['help_on'] != request['help_on']){
                                that._toggleHelp(request['help_on']);
                            }
                            if(prefs['bookmarks_on'] != request['bookmarks_on']){
                                $('.heurist-bookmark-search').css('display',
                                    (request['bookmarks_on']=='1')?'block':'none');
                            }


                            top.HAPI4.currentUser['ugr_Preferences'] = request; //wrong since request can have only part of peferences!!!!!
                            /*allFields.each(function(){
                            top.HAPI4.currentUser['ugr_Preferences'][this.id] = $(this).val();
                            });*/

                            $dlg.dialog( "close" );

                            if(ask_reload){
                                top.HEURIST4.msg.showMsgDlg('Reload page to apply new settings?',
                                    function(){
                                        window.location.reload();
                                    }, 'Confirmation');
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
                title: top.HR("Preferences"),
                buttons: [
                    {text:top.HR('Save'), click: __doSave},
                    {text:top.HR('Cancel'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            })
        });

    }



    , _toggleHelp: function(ishelp_on){

        ishelp_on = (ishelp_on=='1');

        if(ishelp_on){
            $('.heurist-helper1').css('display','block');
            $('.heurist-helper2').css('visibility','visible');
        }else{
            $('.heurist-helper1').css('display','none');
            $('.heurist-helper2').css('visibility','hiddden');
        }

        var a1 = $("#menu-help-inline").find('a');
        a1.attr('title', (ishelp_on?'Hide':'Show')+' inline help');
        a1.html('Inline help - turn '+(ishelp_on?'Off':'On'));

    }



    // the same in profile.js
    // TODO: use an include instead of repeating logout function in this file and profile.js
    , logout: function(){

        var that = this;

        top.HAPI4.SystemMgr.logout(
            function(response){
                if(response.status == top.HAPI4.ResponseStatus.OK){
                    top.HAPI4.setCurrentUser(null);
                    $(that.document).trigger(top.HAPI4.Event.LOGOUT);
                    that._refresh();
                }else{
                    top.HEURIST4.msg.showMsgErr(response);
                }
            }
        );

    }
});
