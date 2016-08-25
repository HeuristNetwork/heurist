/**
* Builds and manages display of the main Heurist page - search and visualisation
*
* Before this widget was generic, however search on main page became very distinctive and got lot of additional ui comonents.
* Thus, we have the specific search widget and this one remains for main ui
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @note        Completely revised for Heurist version 4
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


$.widget( "heurist.search", {

    // default options
    options: {
        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        isloginforced:true,

        btn_visible_newrecord: true, //show add record button

        // before this widget was generic, however search on main page became very distinctive and got
        // lot of additional ui comonents. thus, we have the specific search widget and this one remains for main ui
        // for dialog mode - remove into separate widget
        isrectype: false,  // show rectype selector
        rectype_set: null, // comma separated list of rectypes, if not defined - all rectypes

        isapplication:true,  // send and recieve the global events
        // callbacks
        onsearch: null,  //on start search
        onresult: null   //on search result
    },

    _total_count_of_curr_request: 0, //total count for current request (main and rules) - NOT USED

    query_request:null,

    // the constructor
    _create: function() {

        var that = this;


        /*if(!$.isFunction( hSearchIncremental )){        //jquery.fancytree-all.min.js
        $.getScript(top.HAPI4.basePathV4+'hclient/core/search_incremental.js', function(){ that._create(); } );
        return;
        }*/
        this.element.css({'height':'6em', 'min-width':'1100px'});
        if(top.HAPI4.sysinfo['layout']!='H4Default'){
            this.element.addClass('ui-heurist-header1');
        }

        //var css_valign = {'position': 'relative', 'top': '50%', 'transform': 'translateY(-50%)',
        //          '-webkit-transform': 'translateY(-50%)', '-ms-transform': 'translateY(-50%)'};

        var sz_search = '500px',
        sz_input = '350px',
        sz_search_padding = '0';


        var div_left_visible = (!this.options.isloginforced || this.options.btn_visible_dbstructure);

        if(false) //div_left_visible)
        {

            // database summary, login and register buttons in navigation panel
            var div_left  = $('<div>')
            .css({'height':'2em','width':'200px', 'padding':'1.8em','float':'left'})
            .appendTo( this.element );

            if(!this.options.isloginforced){
                // Login button if not already logged in
                this.btn_login = $( "<button>", {
                    text: top.HR("Login")
                }) // login button
                .css('width',(top.HAPI4.sysinfo.registration_allowed==1)?'80px':'160px')
                .addClass('logged-out-only')
                .addClass('ui-heurist-btn-header1')
                .appendTo(div_left)
                .button({icons: {
                    primary: 'ui-icon-key'
                }})
                .click( function(){ that._doLogin(); });

                // Register button if the database permits user registration
                if(top.HAPI4.sysinfo.registration_allowed==1){
                    this.btn_register = $( "<button>", {
                        text: top.HR("Register")
                    })
                    .css('width','80px')
                    .addClass('logged-out-only')
                    .addClass('ui-heurist-btn-header1')
                    .appendTo(div_left)
                    .button()
                    .click( function(){ that._doRegister(); });
                } // register button

            } // not bypassing login

        }else{ // lefthand - navigation - panel not visible
            sz_search = '600px';
            sz_input = '450px';
            sz_search_padding = '20px';
        }


        // Search functions container
        //'height':'100%', 'float':'left'   , 'min-width':sz_search
        this.div_search   = $('<div>').css({ 'float':'left', 'padding-left':sz_search_padding}).appendTo( this.element );

               
        
        //header-label
        this.div_search_header = $('<div>')
        .css({'width':'100px','text-align':'right'})
        .addClass('div-table-cell')
        .appendTo( this.div_search );
        $( "<label>" ).text(top.HR("Filter"))
        .css({'font-weight':'bold','font-size':'1.2em','padding-left':'1em','padding-right':'1em','vertical-align': 'top', 'line-height':'20px'})
        .appendTo( this.div_search_header );


        // Search field
        this.div_search_input = $('<div>')
        .addClass('div-table-cell')
        .appendTo( this.div_search );

        this.input_search = $( "<textarea>" )
        .css({'margin-right':'0.2em', 'height':'2.5em', 'max-height':'67px', 'max-width':sz_input, 'min-width':'10em', 'width':sz_input }) 
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo(  this.div_search_input );
        top.HEURIST4.util.setDisabled(this.input_search, true);

        var menu_h = top.HEURIST4.util.em(1);


        this.input_search.data('x', this.input_search.outerWidth());
        this.input_search.data('y', this.input_search.outerHeight());


        this.input_search.mouseup(function () {
            var $this = $(this);
            if ($this.outerWidth() != $this.data('x') || $this.outerHeight() != $this.data('y')) {
                //alert($this.outerWidth() + ' - ' + $this.data('x') + '\n' + $this.outerHeight() + ' - ' + $this.data('y'));
                if($this.outerHeight()<25){
                    //aaa  that.div_search.css('padding-top','1.8em');
                    $this.height(23);
                }else{
                    if($this.outerHeight()> that.element.height()-menu_h-8){    //, 'max-height': (this.element.height()-12)+':px'
                        $this.height(that.element.height()-menu_h-10);
                        pt = '2px';
                    }else{
                        //parseFloat(that.div_search.css('padding-top'))
                        pt =  (that.element.height() - $this.height())/2 - menu_h;
                    }
                    //aaa that.div_search.css('padding-top', pt );
                }
            }
            // set new height/width
            $this.data('x', $this.outerWidth());
            $this.data('y', $this.outerHeight());
        }) // this.input_search.mouseup

        //
        // search/filter buttons - may be Search or Bookmarks according to settings and whether logged in
        //
        this.div_search_as_guest = $('<div>')
        .addClass('div-table-cell logged-out-only')
        .appendTo( this.div_search );

        this.btn_search_as_guest = $( "<button>", {
            text: top.HR("filter")
        })
        .appendTo( this.div_search_as_guest )
        .addClass('ui-heurist-btn-header1')
        .button({icons: {
            secondary: "ui-icon-search"
        }});

        this.div_search_as_user = $('<div>')
        .addClass('div-table-cell logged-in-only')
        .css({'min-width': '150px'})
        .appendTo( this.div_search );

        this.btn_search_as_user = $( "<button>", {
            text: top.HR("filter"), title: "Apply the filter/search in the search field and display results in the central panel below"
        })
        .css({'width':'10em', 'vertical-align':'top'})
        .appendTo( this.div_search_as_user )
        .addClass('ui-heurist-btn-header1')
        .button({icons: {
            secondary: "ui-icon-search"
        }});

        this.btn_search_domain = $( "<button>", {
            text: top.HR("filter option")
        })
        .css({'vertical-align':'top'})
        .appendTo( this.div_search_as_user )
        .addClass('ui-heurist-btn-header1')
        .button({icons: {
            primary: "ui-icon-triangle-1-s"
            }, text:false});


        this.div_search_as_user.buttonset();

        var dset = ((this.options.search_domain_set)?this.options.search_domain_set:'a,b').split(',');//,c,r,s';
        var smenu = "";
        $.each(dset, function(index, value){
            var lbl = that._getSearchDomainLabel(value);
            if(lbl){
                smenu = smenu + '<li id="search-domain-'+value+'"><a href="#">'+top.HR(lbl)+'</a></li>'
            }
        });

        this.menu_search_domain = $('<ul>'+smenu+'</ul>')   //<a href="#">
        .zIndex(9999)
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .menu({
            select: function( event, ui ) {
                var mode =  ui.item.attr('id').substr(14);  //(ui.item.attr('id')=="search-domain-b")?"b":"a";
                that.option("search_domain", mode);
                that._refresh();
        }})
        .hide();

        this._on( this.btn_search_domain, {
            click: function() {
                $('.ui-menu').not('.horizontalmenu').hide(); //hide other
                var menu = $( this.menu_search_domain )
                .css('width', this.div_search_as_user.width())
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_search_domain });
                $( document ).one( "click", function() { menu.hide(); });
                return false;
            }
        });

        
        // Save search popup button
        var div_save_filter = $('<div>').addClass('div-table-cell logged-in-only')
        
        if(top.HAPI4.sysinfo['layout']=='original'){
            div_save_filter.appendTo( this.div_search );
        }else{
            div_save_filter.css({'min-width': '200px'});
            div_save_filter.insertBefore( this.div_search_header );
        }
        

        this.btn_search_save = $( "<button>", {
            text: top.HR("Save Filter"),
            title: top.HR('Save the current filter and rules as a link in the navigation tree in the left panel')
        })
        .css({'min-width': '110px','vertical-align':'top','margin-left': '15px'})
        .addClass('ui-heurist-btn-header1')
        .appendTo(div_save_filter)
        .button({icons: {
            primary: "ui-icon-disk"
        }});

        this._on( this.btn_search_save, {  click: function(){
            top.HAPI4.SystemMgr.is_logged(function(){ 
            var  app = top.HAPI4.LayoutMgr.appGetWidgetByName('svs_list');  //top.HAPI4.LayoutMgr.appGetWidgetById('ha13');
            if(app && app.widget){
                $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method
            }
            });
        } });

        

        // Add record button
        if(this.options.btn_visible_newrecord){

            /* on right hand side
            this.div_add_record = $('<div>')
            .addClass('logged-in-only')
            .css({'float': 'right', 'padding': '23px 23px 0 0'})
            .appendTo( this.element );
            */
            
            this.div_add_record = $('<div>')
            .addClass('div-table-cell logged-in-only')
            .appendTo( this.div_search );


            /* in case we need place it along with other elements
            .addClass('div-table-cell  logged-in-only')
            .css('padding-left','4em')
            .appendTo( this.div_search );*/

            this.btn_add_record = $( "<button>", {
                text: top.HR("Add Record"),
                title: "Click to select a record type and access permissions, and create a new record (entity) in the database"
            })
            .css({'width':'140px','minwidth':'110px','margin-left':'4em'})
            //.addClass('logged-in-only')
            .addClass('ui-heurist-btn-header1')
            .appendTo( this.div_add_record )
            .button({icons: {
                primary: 'ui-icon-plusthick' //"ui-icon-circle-plus"
            }})
            .click( function(){ top.HAPI4.SystemMgr.is_logged(that._addNewRecord); });

            
        } // add record button
        
        
        // Manage structure button
        if(top.HAPI4.sysinfo['layout']=='original'){
            
        this.div_add_record = $('<div>')
            .addClass('div-table-cell logged-in-only')
            .appendTo( this.div_search );

        this.btn_mamage_structure = $( "<button>", {
                text: top.HR("Manage Structure"),
                title: "Add new / modify existing record types - general characteristics, data fields and rules which compose a record"
            })
            .css({'width':'140px','min-width': '120px','margin-left':'3em'})
            //.addClass('logged-in-only')
            .addClass('ui-heurist-btn-header1')
            .appendTo( this.div_add_record )
            .button()
            .click(function(){ 
                top.HAPI4.SystemMgr.is_logged(function(){ 
                    top.HEURIST4.msg.showDialog(window.HAPI4.basePathV3 + 'admin/structure/rectypes/manageRectypes.php?popup=1&db='+top.HAPI4.database,
                    { width:1200, height:600, title:'Manage Structure', 
                      afterclose: function(){top.HAPI4.SystemMgr.get_defs_all( false, that.document)}})
                });
            });
        }    
        

        this.div_buttons = $('<div>')
        .addClass('div-table-cell logged-in-only')
        .css({'text-align': 'center', 'width':'50px'}) // 'width': '56px',
        .insertBefore( this.div_search_as_guest );

        // Quick search builder dropdown form
        var link = $('<button>')
        .button({icons: {
            primary: 'ui-icon-arrowthick-1-s'
            }, text:false,
            label:'Dropdown form for building a simple filter expression',
            title:top.HR('Build a filter expression using a form-driven approach (simple and advanced options)')})
        .addClass('ui-heurist-btn-header1')
        .css({'width':'40px','vertical-align': '-4px'})  //'padding':'0 1.0em',
        .appendTo(this.div_buttons);

        this._on( link, {  click: this.showSearchAssistant });


        // Info button
        this.div_buttons = $('<div>')
        .addClass('div-table-cell')
        .css({'text-align': 'center','width': '20px'}) // ,
        .insertBefore( this.div_search_as_guest );

        var link = $('<a>',{href:'#', title:'Show syntax and examples of the Heurist query/filter language'})
        .css({'padding-right':'1.5em','display':'inline-block'})
        .addClass('ui-icon ui-icon-gear')
        .appendTo(this.div_buttons);
        this._on( link, {  click: function(){
            window.open('context_help/advanced_search.html','_blank');
        } });

        this.search_assistant = null;

        if(this.options.isrectype){

            $("<label>for&nbsp;</label>").appendTo( this.div_search );

            this.select_rectype = $( "<select>" )
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .css('max-width','200px')
            //.val(value)
            .appendTo( this.div_search );
        }

        // bind click events
        this._on( this.btn_search_as_user, {
            click: "_doSearch"
        });

        this._on( this.btn_search_as_guest, {
            click: function(){
                that.option("search_domain", "a");
                that._doSearch();
            }
        });

        this._on( this.input_search, {
            keypress: function(e){
                var code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    top.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    that._doSearch();
                }
            }
        });

        //-----------------------

        //global listeners
        $(this.document).on(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT, function(e, data) {
            that._refresh();
        });
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + top.HAPI4.Event.ON_REC_SEARCHRESULT
            + ' ' + top.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + top.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { that._onSearchGlobalListener(e, data) } );

        this._refresh();

        /* search on load
        if(!top.HEURIST4.util.isnull(top.HEURIST4.query_request)){
        this.input_search.val(top.HEURIST4.query_request.q);
        this.options.search_domain = top.HEURIST4.query_request.w;
        }
        if(!top.HEURIST4.util.isnull(top.HEURIST4.query_request)){
        this._doSearch()
        }*/

    }, //end _create

    /* EXPERIMENTAL
    _initPagination: function(){
    this.div_paginator = $('<span>')
    .css('display', 'inline-block')
    .appendTo( this.div_search )
    .pagination();
    },
    */

    _setOption: function( key, value ) {
        this._super( key, value );

        if(key=='rectype_set' || key=='search_domain'){
            this._refresh();
        }
    },

    /* private function */
    _refresh: function(){

        if(top.HAPI4.is_logged()){
            $(this.element).find('.logged-in-only').show();
            //$(this.element).find('.logged-in-only').css('visibility','visible');
            //$(this.element).find('.logged-out-only').css('visibility','hidden');
            $(this.element).find('.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
            //$(this.element).find('.logged-in-only').css('visibility','hidden');
            //$(this.element).find('.logged-out-only').css('visibility','visible');
            $(this.element).find('.logged-out-only').show();

            if(this.options.isloginforced){
                this._doLogin();
            }
        }

        $(this.element).find('.div-table-cell').height( $(this.element).height() );

        this.btn_search_as_user.button( "option", "label", top.HR(this._getSearchDomainLabel(this.options.search_domain)));


        if(this.select_rectype){
            this.select_rectype.empty();
            top.HEURIST4.ui.createRectypeSelect(this.select_rectype.get(0), this.options.rectype_set, !this.options.rectype_set);
        }
    },

    _showAdvancedAssistant: function(){
        //call Heurist vsn 3 search builder
        var q = "",
        that = this;
        if(this.input_search.val()!='') {
            q ="&q=" + encodeURIComponent(this.input_search.val());
        }else if(!Hul.isnull(this.query_request) && !Hul.isempty(this.query_request.q)){
            q ="&q=" + encodeURIComponent(this.query_request.q);
        }

        var url = top.HAPI4.basePathV3+ "search/queryBuilderPopup.php?db=" + top.HAPI4.database + q;

        top.HEURIST4.msg.showDialog(url, { width:740, height:540, title:'Advanced Search Builder', callback:
            function(res){
                if(!Hul.isempty(res)) {
                    that.input_search.val(res);
                    that._doSearch();
                }
        }});
    },


    _onSearchGlobalListener: function(e, data){

        var that = this;

        if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

            //data is search query request
            //topids not defined - this is not rules request
            if(data!=null && top.HEURIST4.util.isempty(data.topids)){

                //request is from some other widget (outside)
                if(data.source!=that.element.attr('id')){
                    if($.isArray(data.q)){
                        that.input_search.val(JSON.stringify(data.q));
                    }else{
                        that.input_search.val(data.q);
                    }

                    that.options.search_domain = data.w;
                    that._refresh();
                }

                if(top.HEURIST.displayPreferences['searchQueryInBrowser'] == "true"){
                    window.history.pushState("object or string", "Title", location.pathname+'?'+
                        top.HEURIST4.util.composeHeuristQueryFromRequest(data, true) );
                }

            }else if(data==null){
                that.input_search.val('');
            }

            //ART that.div_search.css('display','none');

        }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){ //get new chunk of data from server


        }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){ //search completed

            top.HEURIST4.util.setDisabled(this.input_search, false);
            this.input_search.focus();
            
            //show if there is reulst
            if(top.HAPI4.currentRecordset && top.HAPI4.currentRecordset.length()>0) //
            {
                this.btn_search_save.show();
            }else{
                this.btn_search_save.hide();
            }
            

        }else if(e.type == top.HAPI4.Event.ON_STRUCTURE_CHANGE){
            if(this.search_assistant!=null){
                this.search_assistant.remove();
                this.search_assistant = null;
            }
        }



    },


    /*
    _handleKeyPress: function(e){
    var code = (e.keyCode ? e.keyCode : e.which);
    if (code == 13) {
    this._doSearch();
    }
    },
    */
    _getSearchDomainLabel: function(value){
        var lbl = null;
        if(value=='b' || value=='bookmark') { lbl = 'Bookmarks'; }
        else if(value=='r') { lbl = 'recently added'; } //not implemented
            else if(value=='s') { lbl = 'recently selected'; } //not implemented
                else if(value=='c') { lbl = 'Search (in current)'; } //todo
                    else { lbl = 'Filter'; this.options.search_domain='a';}
        return lbl;
    },

    //
    // search from input - query is defined manually
    //
    _doSearch: function(){

        //if(!top.HEURIST4.util.isempty(search_query)){
        //    this.input_search.val(search_query);
        //}

        var qsearch = this.input_search.val();
        if( this.select_rectype && this.select_rectype.val()){
            qsearch = qsearch + ' t:'+this.select_rectype.val();
        }

        if ( qsearch ) {

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            var that = this;

            if(this.options.search_domain=="c" && !top.HEURIST4.util.isnull(this.query_request)){ //NOT USED
                this.options.search_domain = this.query_request.w;
                qsearch = this.query_request.q + ' AND ' + qsearch;
            }

            var request = { q: qsearch,
                w: this.options.search_domain,
                detail: 'detail',
                source: this.element.attr('id') };

            top.HAPI4.SearchMgr.doSearch( this, request );

        }

    }



    /**
    *  public method
    *
    * @returns {Boolean}
    */
    , showSearchAssistant: function() {
        $('.ui-menu').not('.horizontalmenu').hide(); //hide other
        $('.menu-or-popup').hide(); //hide other

        if(this.search_assistant){ //inited already

            var popup = $( this.search_assistant );
            var inpt = $(this.input_search).offset();
            popup.css({'left': inpt.left, 'top':inpt.top+$(this.input_search).height()+3 });
            //popup.position({my: "left top+3", at: "left bottom", of: this.input_search })

            popup.show( "blind", {}, 500 );


            function _hidethispopup(event) {
                if($(event.target).closest(popup).length==0 && $(event.target).attr('id')!='menu-search-quick-link'){
                    popup
                    //.position({my: "left top+3", at: "left bottom", of: this.input_search })
                    .hide( "blind", {}, 500 );
                }else{
                    $( document ).one( "click", _hidethispopup);
                    //return false;
                }
            }

            $( document ).one( "click", _hidethispopup);//hide itself on click outside
        }else{ //not inited yet
            this._initSearchAssistant();
        }

        return false;
    }

    , _initSearchAssistant: function(){

        var $dlg = this.search_assistant = $( "<div>" )
        .addClass('text ui-corner-all ui-widget-content ui-heurist-bg-light')  // menu-or-popup
        .zIndex(9999)
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .hide();

        var that = this;

        //load template
        $dlg.load(top.HAPI4.basePathV4+"hclient/widgets/search/search_quick.html?t="+(new Date().getTime()), function(){


            var search_quick_close = $( "<button>", {
                text: top.HR("close")
            })
            .appendTo( $dlg )
            .addClass('ui-heurist-btn-header1')
            .zIndex(9999)
            .css({'position':'absolute', 'right':4, top:4, width:16, height:16, 'font-size':'0.8em'})
            .button({icons: {
                primary: "ui-icon-triangle-1-n"
                }, text:false});
            that._on( search_quick_close, {
                click: function(event){
                    $dlg.hide( "blind", {}, 500 );
                }
            });


            var dv = $dlg.find('#btns')
            dv.css({'display':'block !important'});

            var link = $('<a>',{
                text: 'Advanced Search Builder', href:'#'
            })
            .css('font-size','1em')
            .appendTo( dv );
            that._on( link, {  click: function(event){
                $dlg.hide( "blind", {}, 500 );
                that._showAdvancedAssistant();
            } });


            var search_quick_go = $( "<button>", {
                text: top.HR("Go")
            })
            .appendTo( dv )
            .addClass('ui-heurist-btn-header1')
            //.zIndex(9999)
            //.css({'position':'absolute', 'right':4, top:4, width:18, height:18})
            .css('float', 'right')
            .button();
            that._on( search_quick_go, {
                click: function(event){
                    $dlg.hide( "blind", {}, 500 );
                    that._doSearch();
                }
            });

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                $(this).html(top.HR($(this).html()));
            });

            var select_rectype = $("#sa_rectype");
            var select_fieldtype = $("#sa_fieldtype");
            var select_sortby = $("#sa_sortby");
            var select_terms = $("#sa_termvalue");
            var sortasc =  $('#sa_sortasc');
            $dlg.find("#fld_enum").hide();

            top.HEURIST4.ui.createRectypeSelect( select_rectype.get(0), null, top.HR('Any record type'));

            var allowed = Object.keys(top.HEURIST4.detailtypes.lookups);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("geo"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);


            that._on( select_rectype, {
                change: function (event){

                    var rectype = (event)?Number(event.target.value):0;
                    top.HEURIST4.ui.createRectypeDetailSelect(select_fieldtype.get(0), rectype, allowed, top.HR('Any field type'));

                    /*select_sortby.html("<option value=t>"+top.HR("record title")+"</option>"+
                    "<option value=rt>"+top.HR("record type")+"</option>"+
                    "<option value=u>"+top.HR("record URL")+"</option>"+
                    "<option value=m>"+top.HR("date modified")+"</option>"+
                    "<option value=a>"+top.HR("date added")+"</option>"+
                    "<option value=r>"+top.HR("personal rating")+"</option>"+
                    "<option value=p>"+top.HR("popularity")+"</option>");*/
                    var topOptions = [{key:'t', title:top.HR("record title")},
                        {key:'rt', title:top.HR("record type")},
                        {key:'u', title:top.HR("record URL")},
                        {key:'m', title:top.HR("date modified")},
                        {key:'a', title:top.HR("date added")},
                        {key:'r', title:top.HR("personal rating")},
                        {key:'p', title:top.HR("popularity")}];

                    if(Number(rectype)>0){
                        topOptions.push({optgroup:'yes', title:top.HEURIST4.rectypes.names[rectype]+' '+top.HR('fields')});
                        /*
                        var grp = document.createElement("optgroup");
                        grp.label =  top.HEURIST4.rectypes.names[rectype]+' '+top.HR('fields');
                        select_sortby.get(0).appendChild(grp);
                        */
                    }
                    top.HEURIST4.ui.createRectypeDetailSelect(select_sortby.get(0), rectype, allowed, topOptions);

                    $("#sa_fieldvalue").val("");
                    $("#sa_negate").prop("checked",'');
                    $dlg.find("#fld_contain").show();
                    $dlg.find("#fld_enum").hide();
                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_fieldtype, {
                change: function(event){

                    var dtID = Number(event.target.value);

                    var detailtypes = top.HEURIST4.detailtypes.typedefs;
                    var detailType = '';

                    if(Number(dtID)>0){
                        detailType = detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']];
                    }
                    if(detailType=='enum'  || detailType=='relationtype'){
                        $dlg.find("#fld_contain").hide();
                        $dlg.find("#fld_enum").show();
                        //fill terms
                        var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                        disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];

                        top.HEURIST4.ui.createTermSelectExt(select_terms.get(0), detailType, allTerms, disabledTerms, null, false);
                    } else {
                        $dlg.find("#fld_contain").show();
                        $dlg.find("#fld_enum").hide();
                    }

                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_terms, {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_sortby, {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( $("#sa_fieldvalue"), {
                keyup: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( $("#sa_negate"), {
                change: function(event){
                    this.calcShowSimpleSearch();
                }
            });
            that._on( sortasc, {
                click: function(event){
                    //top.HEURIST4.util.stopEvent(event);
                    //sortasc.prop('checked', !sortasc.is(':checked'));
                    this.calcShowSimpleSearch();
                }
            });

            select_rectype.trigger('change');
            that.showSearchAssistant();
        });

    }

    // recalculate search query value
    ,calcShowSimpleSearch: function (e) {

        var q = this.search_assistant.find("#sa_rectype").val(); if(q) q = "t:"+q;
        var fld = this.search_assistant.find("#sa_fieldtype").val(); if(fld) fld = "f:"+fld+":";
        var ctn = this.search_assistant.find("#fld_enum").is(':visible') ?this.search_assistant.find("#sa_termvalue").val()
        :this.search_assistant.find("#sa_fieldvalue").val();
        
        if(this.search_assistant.find("#sa_negate").is(':checked')){
            fld  = '-'+fld;
        }

        var asc = ($("#sa_sortasc").val()==1?"-":'') ; //($("#sa_sortasc:checked").length > 0 ? "" : "-");
        var srt = $("#sa_sortby").val();
        srt = (srt == "t" && asc == "" ? "" : ("sortby:" + asc + (isNaN(srt)?"":"f:") + srt));

        q = (q? (fld?q+" ": q ):"") + (fld?fld: (ctn?" all:":"")) + (ctn? (isNaN(Number(ctn))?'"'+ctn+'"':ctn):"") + (srt? " " + srt : "");
        if(!q){
            q = "sortby:t";
        }


        this.input_search.val(q);

        e = top.HEURIST4.util.stopEvent(e);
    }

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

        $(this.document).off(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT);
        $(this.document).off(top.HAPI4.Event.ON_REC_SEARCHSTART
          + ' ' + top.HAPI4.Event.ON_REC_SEARCHRESULT
          + ' ' + top.HAPI4.Event.ON_REC_SEARCH_FINISH
          + ' ' + top.HAPI4.Event.ON_STRUCTURE_CHANGE);

        // remove generated elements
        //this.btn_search_allonly.remove();  // bookamrks search off
        this.btn_search_as_guest.remove(); // bookamrks search on
        this.btn_search_as_user.remove();  // bookamrks search on
        this.btn_search_domain.remove();
        this.search_assistant.remove();
        this.menu_search_domain.remove();
        this.input_search.remove();
        if(this.select_rectype) this.select_rectype.remove();

        this.div_search_as_user.remove();
        this.div_search_as_guest.remove();

        if(this.div_paginator) this.div_paginator.remove();

        this.div_search.remove();
    }

    , _addNewRecord: function(){


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

    }

    , _doLogin: function(){

        if(doLogin && $.isFunction(doLogin)){  // already loaded in index.php
            doLogin(this.options.isloginforced);
        }else{
            //var that = this;
            $.getScript(top.HAPI4.basePathV4+'hclient/widgets/profile/profile_login.js', this._doLogin );
        }


    }

    , _doRegister: function(){

        if(false && !$.isFunction(doLogin)){  // already loaded in index.php
            //var that = this;
            $.getScript(top.HAPI4.basePathV4+'hclient/widgets/profile/profile_login.js', this._doRegister );
        }else{
            doRegister();
        }

        /*
        if($.isFunction($('body').profile_edit)){

        if(!this.div_profile_edit || this.div_profile_edit.is(':empty') ){
        this.div_profile_edit = $('<div>').appendTo( this.element );
        }
        this.div_profile_edit.profile_edit({'ugr_ID': top.HAPI4.currentUser.ugr_ID});

        }else{
        var that = this;
        $.getScript(top.HAPI4.basePathV4+'hclient/widgets/profile/profile_edit.js', function() {
        if($.isFunction($('body').profile_edit)){
        that._doRegister();
        }else{
        top.HEURIST4.msg.showMsgErr('Widget profile edit not loaded!');
        }
        });
        }
        */

    }

});
