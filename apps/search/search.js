/**
* Builds and manages display of the main Heurist page - search and visualisation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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

        isrectype: false,  // show rectype selector
        rectype_set: null, // comma separated list of rectypes, if not defined - all rectypes

        isapplication:true,  // send and recieve the global events

        searchdetails: "map", //level of search results  map - with details, structure - with detail and structure

        isloginforced:true,
        has_paginator: false,
        btn_visible_dbstructure: false,

        limit: 1000,

        islinkmode: false, //show buttons or links
        btn_visible_newrecord: true,

        // callbacks
        onsearch: null,  //on start search
        onresult: null   //on search result
    },

    _total_count_of_curr_request: 0, //total count for current request (main and rules)

    _rule_index: -1, // current index
    _res_index:  -1, // current index in result array (chunked by 1000)
    _rules:[],       // flat array of rules for current query request

    /* format

         rules:{   {parent: index,  // index to top level
                    level: level,
                    query: },

         results: { root:[], ruleindex:[  ],  ruleindex:[  ] }


         requestXXX - index in rules array?  OR query string?

            ids:[[1...1000],[1001....2000],....],     - level0
            request1:{ ids:[],                          level1
                    request2:{},                        level2
                    request2:{ids:[],
                            request3:{} } },            level3

    */

    query_request: null,  //current search request - need for "AND" search in current result set

    // the constructor
    _create: function() {

        var that = this;
        this.element.css({'height':'100%', 'min-width':'1100px'}).addClass('ui-heurist-header1');


        //var css_valign = {'position': 'relative', 'top': '50%', 'transform': 'translateY(-50%)',
        //          '-webkit-transform': 'translateY(-50%)', '-ms-transform': 'translateY(-50%)'};
        
        var sz_search = '500px',
            sz_input = '350px',
            sz_search_padding = '0';
        

        var div_left_visible = (!this.options.isloginforced || this.options.btn_visible_dbstructure);

        if(div_left_visible)
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

        // Database summary button
        if(this.options.btn_visible_dbstructure){
            this.btn_databasesummary = $( "<button>", {
                text: top.HR("Database Summary")
            })
            .css('width','160px')
            .addClass('logged-in-only')
            .addClass('ui-heurist-btn-header1')
            .appendTo(div_left)
            .button()
            .click( function(){ that._showDbSummary(); });
        } // database summary

        }else{ // lefthand - navigation - panel not visible
            sz_search = '600px';
            sz_input = '450px';
            sz_search_padding = '50px';
        }


        // Search functions container
        //'height':'100%',
        this.div_search   = $('<div>').css({'padding-top':'1.8em', 'padding-left':sz_search_padding, 'min-width':sz_search, 'float':'left'}).appendTo( this.element );

        // Loading progress bar, initially hidden
        this.div_progress = $('<div>').css({'padding-top':'1.8em', 'padding-left':sz_search_padding, 'min-width':sz_search, 'float':'left'}).appendTo( this.element ).hide();

        // Add record button
        if(this.options.btn_visible_newrecord){
            this.btn_add_record = $( "<button>", {
                text: top.HR("Add Record")
            })
            .css('width','160px')
            .addClass('logged-in-only')
            .addClass('ui-heurist-btn-header1')
            .appendTo(
                  $('<span>')
                        .css({'padding':'1.8em 6em 0 3em','float':'left'}) //,'display':'inline-block'  'width':this.options.leftmargin,
                        .appendTo( this.element )
            )
            .button({icons: {
                primary: 'ui-icon-plusthick' //"ui-icon-circle-plus"
            }})
            .click( function(){ that._addNewRecord(); });

        } // add record button       
        
        
        this.div_search_header = $('<div>')
        .addClass('div-table-cell')
        //.css('padding-top','0.4em')
        .appendTo( this.div_search );
        
        $( "<label>" ).text(top.HR("Filter criteria"))
        .css({'font-weight':'bold','font-size':'1.2em','padding-right':'1em','vertical-align': 'top', 'line-height':'20px'})
        .appendTo( this.div_search_header );

        
        /*
        $( "<button>", {
                text: top.HR("Show syntax and examples of the Heurist query language")
            })
            .addClass('ui-heurist-btn-header1-fa')
            .appendTo( this.div_search_header )
            .button({icons: {
                primary: 'icon-info-sign'
            }, text:false})
            .css({'margin-right':'0.2em'}) //, 'background-position':'240px 224px !important'})
            .click( function(){ 
                window.open(top.HAPI4.basePathOld+'context_help/advanced_search.html','_blank');
            }); 
        */    
            
        var link = $('<a>',{href:'#'})
            .html('<img src="'+top.HAPI4.basePath+'assets/info.png" width="20" height="20" title="Show syntax and examples of the Heurist query language" />')
            .css('padding-right','1em')
            .appendTo(this.div_search_header);
            this._on( link, {  click: function(){ 
                window.open(top.HAPI4.basePathOld+'context_help/advanced_search.html','_blank');
            } });


        this.div_search_input = $('<div>')
            .addClass('div-table-cell')
            .appendTo( this.div_search );            
            
        this.input_search = $( "<textarea>" )   //, {'rows':1}this.options.has_paginator?'30%':'50%'} )                  
        .css({'margin-right':'0.2em', 'max-width':sz_input, 'width':sz_input, 'height':'1.4em' }) //,  , 'width':'30%', 'max-width':'500px'}) 
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo(  this.div_search_input );
        
        var menu_h = top.HEURIST4.util.em(1); 
        

        this.input_search.data('x', this.input_search.outerWidth());
        this.input_search.data('y', this.input_search.outerHeight());


        this.input_search.mouseup(function () {
                var $this = $(this);
                if ($this.outerWidth() != $this.data('x') || $this.outerHeight() != $this.data('y')) {
                        //alert($this.outerWidth() + ' - ' + $this.data('x') + '\n' + $this.outerHeight() + ' - ' + $this.data('y'));
                       if($this.outerHeight()<25){
                            that.div_search.css('padding-top','1.8em');
                            $this.height(23);
                       }else{
                            if($this.outerHeight()> that.element.height()-menu_h-8){    //, 'max-height': (this.element.height()-12)+':px'
                                $this.height(that.element.height()-menu_h-10);
                                pt = '2px';
                            }else{
                                //parseFloat(that.div_search.css('padding-top'))
                                pt =  (that.element.height() - $this.height())/2 - menu_h;
                            }
                            that.div_search.css('padding-top', pt );
                       }
                }
                // set new height/width
                $this.data('x', $this.outerWidth());
                $this.data('y', $this.outerHeight());
        }) // this.input_search.mouseup

        //
        // search buttons - may be Search or Bookmarks according to settings and whether logged in
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
        //.css({'position':'absolute'})
        .appendTo( this.div_search );

        this.btn_search_as_user = $( "<button>", {
            text: top.HR("filter")
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

        //set input search textarea same height as button
        this.input_search.height( this.btn_search_as_guest.height() - 2 );


        //
        // search function links below filter expression field
        //
        if(this.options.islinkmode){

            //$('<br>').appendTo( this.div_search );
            this.div_search_links = $('<div>').css({'text-align':'right','width':sz_input,'padding-top':'0.3em'}) //, 'width':'30%', 'max-width':'500px' })
                    .appendTo(  this.div_search_input );

            var link = $('<a>',{
                text: 'Quick', href:'#'
            }).appendTo(this.div_search_links);
            this._on( link, {  click: this.showSearchAssistant });

            link = $('<a>',{
                text: 'Advanced', href:'#'
            }).appendTo(this.div_search_links);
            this._on( link, {  click: this._showAdvancedAssistant });


            $('<a>',{
                text: 'Syntax',
                href:  top.HAPI4.basePathOld+'context_help/advanced_search.html', target:'_blank'
            }).appendTo(this.div_search_links);

            link = $('<a>',{ text: 'Rule Set', href:'#' })
                    .addClass('logged-in-only')
                    .appendTo(this.div_search_links);
            this._on( link, {  click: function(){
                var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).svs_list('editSavedSearch', 'rules'); //call public method 
                }
            }});

            link = $('<a>',{ text: 'Save As ', href:'#' })
                   .addClass('logged-in-only')
                   .appendTo(this.div_search_links);
            this._on( link, {  click: function(){
                var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method 
                }
            }});

            //set color of links as tet in button
            this.div_search_links.find('a').css({ 'text-decoration':'none', 'font-size':'0.9em',
                'padding-right':'1em' ,'color':this.btn_search_as_user.css('color')}); // 'color':  this.div_search_links.find('.ui-widget-content').css('color') });

        } // link buttons under filter expression field

        else

        { // Quick search assistant = dropdown search builder

            
            /*
            var btn_assistant = $( "<button>", {
                text: top.HR("Build a Heurist filter using a form-driven approach (simple and advanced options)")
            })
            .addClass('ui-heurist-btn-header1-fa')
            .insertBefore( this.btn_search_as_user )
            .button({icons: {
                primary: 'icon-magic'
            }, text:false})
            .css('margin-right','0.2em');
            //.click( that.showSearchAssistant );              
            
            this._on( btn_assistant, {click: this.showSearchAssistant});

            $( "<button>", {
                text: top.HR("Save the current filter and rules as a link in the navigation tree in the left panel")
            })
            .addClass('ui-heurist-btn-header1-fa')
            .insertBefore( this.btn_search_as_user )
            .button({icons: {
                primary: 'icon-save'
            }, text:false})
            .css('margin-right','0.2em')
            .click( function(){ 
                var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method 
                }                
            });     */
            
                this.div_buttons = $('<div>')
                    .addClass('div-table-cell logged-in-only')
                    //.css({'padding-top':'0.4em'})
                    .insertBefore( this.div_search_as_guest );
                
                
                var link = $('<a>',{href:'#'})
                .html('<img src="'+top.HAPI4.basePath+'assets/magicwand.png" width="20" title="'+
                        top.HR('Build a Heurist filter using a form-driven approach (simple and advanced options)')+'" />')
                .css({'padding-right':'1em','padding-left':'1em'})
                .appendTo( this.div_buttons );
                this._on( link, {  click: this.showSearchAssistant });

                link = $('<a>',{href:'#'})
                .html('<img src="'+top.HAPI4.basePath+'assets/savefloppy.png" width="20" title="'+
                        top.HR('Save the current filter and rules as a link in the navigation tree in the left panel')+'" />')
                .css('padding-right','1em')
                .appendTo( this.div_buttons );
                this._on( link, {  click: function(){ 
                    var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
                    if(app && app.widget){
                        $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method 
                    }                
                } });
                        

            //show quick search assistant
            //this._on( this.btn_search_assistant, {  click: this.showSearchAssistant });

        } // quick search assistant


        this.search_assistant = null;

//        .addClass('heurist-bookmark-search')
//        .css('display', (top.HAPI4.get_prefs('bookmarks_on')=='1')?'block':'none')


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
                    that._doSearch();
                }
            }
        });

        if(this.options.has_paginator){
            if($.isFunction($('body').pagination)){
                this._initPagination();
            }else{
                $.getScript(top.HAPI4.basePath+'apps/pagination.js', function() {
                    if($.isFunction($('body').pagination)){
                        that._initPagination();
                    }else{
                        top.HEURIST4.util.showMsgErr('Widget pagination not loaded!');
                    }
                });
            }
        }else{

            this.limit = top.HAPI4.get_prefs('search_limit'); //top.HEURIST.displayPreferences['results-per-page'];
            if(!this.limit) this.limit = 200;

/*  @TODO - move tp preferences
            this.btn_search_limit = $( "<button>", {
                text: this.limit,
                title: top.HR('records per chunk')
            })
            //.css('width', '4em')
            .appendTo( this.div_search )
            .button({icons: { secondary: "ui-icon-triangle-1-s" }});

            var smenu =
            '<li id="search-limit-100"><a href="#">100</a></li>'+
            '<li id="search-limit-200"><a href="#">200</a></li>'+
            '<li id="search-limit-500"><a href="#">500</a></li>'+
            '<li id="search-limit-1000"><a href="#">1000</a></li>';

            this.menu_search_limit = $('<ul>'+smenu+'</ul>')   //<a href="#">
            .zIndex(9999)
            .css('position','absolute')
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    var newlimit = Number(ui.item.attr('id').substring(13));
                    if(newlimit!=that.limit){

                        top.HAPI4.currentUser['ugr_Preferences']['search_limit'] = newlimit;
                        if(top.HAPI4.is_logged()){
                            //save preference in session
                            top.HAPI4.SystemMgr.save_prefs({'search_limit': newlimit},
                                function(response){
                                    if(response.status != top.HAPI4.ResponseStatus.OK){
                                        top.HEURIST4.util.showMsgErr(response);
                                    }
                                }
                            );
                        }

                        that.limit = newlimit;
                        that.btn_search_limit.button( "option", "label", that.limit );
                        //that._doSearch3(0);
                    }
            }})
            .hide();

            this._on( this.btn_search_limit, {
                click: function() {
                    $('.ui-menu').not('.horizontalmenu').hide(); //hide other
                    var menu = $( this.menu_search_limit )
                    .show()
                    .position({my: "right top", at: "right bottom", of: this.btn_search_limit });
                    $( document ).one( "click", function() { menu.hide(); });
                    return false;
                }
            });
*/


        }


        //-----------------------


        this.progress_bar = $("<div>")
            .css({'width':'450px', 'height':'1.6em', 'display':'inline-block', 'margin-right':'0.2em'})  //, 'height':'22px',  'margin':'0px !important'})
            .progressbar().appendTo(this.div_progress);

        this.progress_lbl = $("<div>").css({'position':'absolute', 'top': '1.6em', 'text-shadow': '1px 1px 0 #fff' })
            .appendTo(this.progress_bar);
            //font-weight: bold;

        // Stop search button which is displayed in place of search button while resultset loads
        this.btn_stop_search = $( "<button>", {text: "Stop"} )
              .css({'display':'inline-block', 'margin-bottom':'15px'})
              .addClass('ui-heurist-btn-header1')
              .appendTo( this.div_progress )
              .button({icons: {
                  secondary: "ui-icon-cancel"
                 },text:true});

        this._on( this.btn_stop_search, {
            click: function(e) {
                if(this.query_request!=null){ //assign new id to search - it prevents further increment search
                    this.query_request.id = Math.round(new Date().getTime() + (Math.random() * 100));
                    this._searchCompleted();
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
                            + ' ' + top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES, function(e, data) { that._onSearchGlobalListener(e, data) } );

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

    _initPagination: function(){
        this.div_paginator = $('<span>')
        .css('display', 'inline-block')
        .appendTo( this.div_search )
        .pagination();
    },

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

        this.btn_search_as_user.button( "option", "label", top.HR(this._getSearchDomainLabel(this.options.search_domain)));
        if(this.btn_search_limit) this.btn_search_limit.button( "option", "label", this.limit );

        if(this.select_rectype){
            this.select_rectype.empty();
            top.HEURIST4.util.createRectypeSelect(this.select_rectype.get(0), this.options.rectype_set, !this.options.rectype_set);
        }
    },
    
    _showAdvancedAssistant: function(){
                //call H3 search builder
                var q = "",
                    that = this;
                if(!Hul.isnull(this.query_request) && !Hul.isempty(this.query_request.q)){
                    q ="&q=" + encodeURIComponent(this.query_request.q);
                }
                var url = top.HAPI4.basePathOld+ "search/queryBuilderPopup.php?db=" + top.HAPI4.database + q;

                Hul.showDialog(url, { width:740, height:540, callback:
                    function(res){
                        if(!Hul.isempty(res)) {
                                that.input_search.val(res);
                                that._doSearch();
                        }
                    }});
    },

    /**
    * it is called only for the very first search request (except all other: incremental and rules)
    */
    _onSearchStart: function(){

            //this.div_search.hide();
            //this.div_progress.show();

            this.div_search.css('display','none');
            this.div_progress.width(this.div_search.width());
            this.div_progress.css('display','inline-block');


            //reset counters and storages
            this._rule_index = -1; // current index
            this._res_index =  -1; // current index in result array (chunked by 1000)
            this._rules = [];      // flat array of rules for current query request

            if( this.query_request.rules!=null ){
                //create flat rule array
                this._doApplyRules(this.query_request.rules);
            }

            this._renderProgress();
    },

    _onSearchGlobalListener: function(e, data){

        var that = this;

        if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

            //data is search query request
            if(data!=null && top.HEURIST4.util.isempty(data.topids)){ //topids not defined - this is not rules request

                 top.HEURIST4.current_query_request = jQuery.extend(true, {}, data); //the only place where this values is assigned
                 that.query_request = data; //keep for search in current result

                 top.HAPI4.currentRecordset = null;
                 top.HAPI4.currentRecordsetByLevels = null;
                 
                 if(data.source!=that.element.attr('id') ){   //search from outside

                    if($.isArray(data.q)){
                        that.input_search.val(JSON.stringify(data.q));
                    }else{
                        that.input_search.val(data.q);
                    }

                    that.options.search_domain = data.w;
                    that._refresh();
                 }

                 if(top.HAPI4.currentRecordset==null && data.q!=''){
                    that._onSearchStart();
                 }
            }


        }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){ //get new chunk of data from server

            if(data){

                    if(that.query_request!=null && data.queryid()==that.query_request.id) {

                    //save increment into current rules.results
                    var records_ids = Hul.cloneJSON(data.getIds());
                    if(records_ids.length>0){
                        // rules:[ {query:query, results:[], parent:index},  ]
                        if(that._rule_index==-2){
                            that._rule_index=0;
                        }else{
                            var ruleindex = that._rule_index;
                            if(ruleindex<0){
                                 ruleindex = 0; //root/main search
                            }
                            if(top.HEURIST4.util.isempty(that._rules)){
                                 that._rules = [{results:[]}];
                            }
                            that._rules[ruleindex].results.push(records_ids);

                            //unite
                            if(top.HAPI4.currentRecordset==null){
                                top.HAPI4.currentRecordset = data;
                            }else{
                                //unite record sets
                                top.HAPI4.currentRecordset = top.HAPI4.currentRecordset.doUnite(data);
                            }
                            top.HAPI4.currentRecordsetByLevels = that._rules; //contains main result set and rules result sets

                        }
                    }

                    that._renderProgress();
                    if(!that._doSearchIncrement()){//load next chunk or start search rules
                        that._searchCompleted();
                    }
                }

            }

        }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){ //get new chunk of data from server
            
                 that.div_progress.css('display','none');
                 that.div_search.css('display','inline-block');

            
        }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES){


                if(data){

                    var rules = data.rules?data.rules:data;

                    //create flat rule array
                    that._doApplyRules(rules); //indexes are rest inside this function

                    //if rules were applied before - need to remove all records except original set and re-render
                    if(!top.HEURIST.util.isempty(that._rules) && that._rules[0].results.length>0){

                         //keep json (to possitble save as saved searches)
                         that.query_request.rules = rules;

                         //remove results of other rules and re-render the original set of records only
                         var rec_ids_level0 = [];
                         var idx;
                         that._rules[0].results = that._rules[0].results;
                         for(idx=0; idx<that._rules[0].results.length; idx++){
                            rec_ids_level0 = rec_ids_level0.concat(that._rules[0].results[idx]);
                         }

                         //var recordset_level0 - only main set remains all result from rules are removed
                         top.HAPI4.currentRecordset = top.HAPI4.currentRecordset.getSubSetByIds(rec_ids_level0);

                         that._rule_index = -2;
                         that._res_index = 0;

                         this.div_search.css('display','none');
                         this.div_progress.width(this.div_search.width());
                         this.div_progress.css('display','inline-block');
                         this._renderProgress();

                         //fake result searh event
                         $(that.document).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ null ]);  //global app event to clear views
                         $(that.document).trigger(top.HAPI4.Event.ON_REC_SEARCHRESULT, [ top.HAPI4.currentRecordset ]);  //global app event
                    } else if(!top.HEURIST.util.isempty(that._rules)){
                        Hul.showMsgFlash('Rule sets require an initial search result as a starting point.', 3000, top.HR('Warning'), data.target);
                    }
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

            if(this.options.search_domain=="c" && !top.HEURIST4.util.isnull(this.query_request)){
                this.options.search_domain = this.query_request.w;
                qsearch = this.query_request.q + ' AND ' + qsearch;
            }

            var request = { q: qsearch, 
                            w: this.options.search_domain, 
                            f: this.options.searchdetails,
                            source:this.element.attr('id') };

            //that._trigger( "onsearch"); //this widget event
            //that._trigger( "onresult", null, resdata ); //this widget event
            //top.HAPI4.currentRecordset = null;
            //this._onSearchStart();

            //perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));    //search from input (manual query definition)
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
        $dlg.load("apps/search/search_quick.html?t="+(new Date().getTime()), function(){


            var search_quick_close = $( "<button>", {
                text: top.HR("close")
            })
            .appendTo( $dlg )
            .addClass('ui-heurist-btn-header1')
            .zIndex(9999)
            .css({'position':'absolute', 'right':4, top:4, width:18, height:18})
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

            top.HEURIST4.util.createRectypeSelect( select_rectype.get(0), null, top.HR('Any record type'));
            //top.HEURIST4.util.createRectypeDetailSelect( select_fieldtype.get(0), 0, null, top.HR('Any record type'));

            var allowed = Object.keys(top.HEURIST4.detailtypes.lookups);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);


            that._on( select_rectype, {
                change: function (event){

                    var rectype = (event)?Number(event.target.value):0;
                    top.HEURIST4.util.createRectypeDetailSelect(select_fieldtype.get(0), rectype, allowed, top.HR('Any field type'), true);

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
                    top.HEURIST4.util.createRectypeDetailSelect(select_sortby.get(0), rectype, allowed, topOptions, false);

                    $("#sa_fieldvalue").val("");
                    $dlg.find("#fld_contain").show();
                    $dlg.find("#fld_enum").hide();
                    this.calcShowSimpleSearch();
                }
            });
            that._on( select_fieldtype, {
                change: function(event){

                    var dtID = Number(event.target.value);

                    var detailtypes = top.HEURIST4.detailtypes.typedefs;

                    if(Number(dtID)>0 && detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']]=='enum'){
                        $dlg.find("#fld_contain").hide();
                        $dlg.find("#fld_enum").show();
                        //fill terms
                        var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                        disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];

                        top.HEURIST4.util.createTermSelectExt(select_terms.get(0), "enum", allTerms, disabledTerms, null, false);
                    }else{
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
        $(this.document).off(top.HAPI4.Event.ON_REC_SEARCHSTART+
            ' '+top.HAPI4.Event.ON_REC_SEARCHRESULT+
            ' '+top.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES);

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
        this.div_progress.remove();
    }

    //incremental search and rules
    //@todo - do not apply rules unless main search is finished
    //@todo - if search in progress and new rules are applied - stop this search and remove all linked data (only main search remains)
    ,_doSearchIncrement: function(){

        if ( this.query_request ) {

            this.query_request.source = this.element.attr('id'); //orig = 'rec_list';


            var new_offset = Number(this.query_request.o);
            new_offset = (new_offset>0?new_offset:0) + Number(this.query_request.l);

            var total_count_of_curr_request = (top.HAPI4.currentRecordset!=null)?top.HAPI4.currentRecordset.count_total():0;

            if(new_offset< total_count_of_curr_request){ //search for next chunk of data within current request
                    this.query_request.increment = true;  //it shows that this is not initial search request
                    this.query_request.o = new_offset;
                    this.query_request.source = this.element.attr('id');
                    top.HAPI4.RecordMgr.search(this.query_request, $(this.document));   //search for next chunk
                    return true;
            }else{

         // original rule array
         // rules:[ {query:query, levels:[]}, ....  ]

         // we create flat array to allow smooth loop
         // rules:[ {query:query, results:[], parent:index},  ]


                     // this._rule_index;  current rule
                     // this._res_index;   curent index in result array (from previous level)

                     var current_parent_ids;
                     var current_rule;

                     while (true){ //while find rule with filled parent's resultset

                         if(this._rule_index<1){
                             this._rule_index = 1; //start with index 1 - since zero is main resultset
                             this._res_index = 0;
                         }else{
                             this._res_index++;  //goto next 1000 records
                         }

                         if(this._rule_index>=this._rules.length){
                             this._rule_index = -1; //reset
                             this._res_index = -1;
                             this._renderProgress();
                             return false; //this is the end
                         }

                         current_rule = this._rules[this._rule_index];
                         //results from parent level
                         current_parent_ids = this._rules[current_rule.parent].results

                         //if we access the end of result set - got to next rule
                         if(this._res_index >= current_parent_ids.length)
                         {
                            this._res_index = -1;
                            this._rule_index++;

                         }else{
                            break;
                         }
                     }


                     //create request
                     this.query_request.q = current_rule.query;
                     this.query_request.topids = current_parent_ids[this._res_index].join(','); //list of record ids of parent resultset
                     this.query_request.increment = true; //it shows that this is not initial search request
                     this.query_request.o = 0;
                     this.query_request.source = this.element.attr('id');
                     top.HAPI4.RecordMgr.search(this.query_request, $(this.document)); //search rules
                     return true;

            }
        }

        return false;
    }

    , _searchCompleted: function(){

         if(top.HAPI4.currentRecordset && top.HAPI4.currentRecordset.length()>0)
            $(this.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ top.HAPI4.currentRecordset ]); //global app event
         else{
            $(this.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, null); //global app event
         }
    }


    /**
    * Rules may be applied at once (part of query request) or at any time later
    *
    * 1. At first we have to create flat rule array
    */
    , _doApplyRules: function(rules_tree){

         // original rule array
         // rules:[ {query:query, levels:[]}, ....  ]

         // we create flat array to allow smooth loop
         // rules:[ {query:query, results:[], parent:index},  ]

        var flat_rules = [ { results:[] } ];

        function __createFlatRulesArray(r_tree, parent_index){
            var i;
            for (i=0;i<r_tree.length;i++){
                var rule = { query: r_tree[i].query, results:[], parent:parent_index }
                flat_rules.push(rule);
                __createFlatRulesArray(r_tree[i].levels, flat_rules.length-1);
            }
        }

        //it may be json
        if(!top.HEURIST.util.isempty(rules_tree) && !$.isArray(rules_tree)){
             rules_tree = $.parseJSON(rules_tree);
        }

        __createFlatRulesArray(rules_tree, 0);

        //assign zero level - top most query
        if(top.HAPI4.currentRecordset!=null){  //aplying rules to existing set

            //result for zero level retains
            flat_rules[0].results = this._rules[0].results;

            this._rule_index = 0; // current index
        }else{
            this._rule_index = -1;
        }
        this._res_index = 0; // current index in result array (chunked by 1000)

        this._rules = flat_rules;

    }


    , _renderProgress: function(){

        // show current records range and total count
        if(top.HAPI4.currentRecordset && top.HAPI4.currentRecordset.length()>0){

            var s = '';

            if(this._rule_index>0){ //this search by rules
                s = 'Rules: '+this._rule_index+' of '+(this._rules.length-1)+'  ';
            }
            s = s + 'Total: ' + top.HAPI4.currentRecordset.length();

            //@todo this.span_info.html( s );
            //this.span_info.show();

            var curr_offset = Number(this.query_request.o);
            curr_offset = (curr_offset>0?curr_offset:0) + Number(this.query_request.l);

            var tot = top.HAPI4.currentRecordset.count_total();  //count of records in current request

            if(curr_offset>tot) curr_offset = tot;

            if(this._rule_index<1){  //this is main request

                    //search in progress
                     this.progress_lbl.css('margin-left','180px').html( (curr_offset==tot)?tot:'Loading '+curr_offset+' of '+tot );

                     this.progress_bar.progressbar( "value", curr_offset/tot*100 );
                     //this.progress_bar.html( $('<div>').css({'background-color':'blue', 'width': curr_offset/tot*100+'%'}) );

                     if(curr_offset==tot) {
                           //this.div_progress.delay(500).hide();
                     }

            }else{ //this is rule request

                     if( this._rule_index < this._rules.length){

                        //count of chunks of previous result set = number of queries
                        var res_steps_count = this._rules[this._rules[this._rule_index].parent].results.length;

                        s = "Applying rule set ";
                        if(this._rules.length>2) //more than 1 rule
                           s  = s + this._rule_index+' of '+(this._rules.length-1);
                        s = s + '. ';
                        var alts = s;

                        //show how many queries left
                        var queries_togo = res_steps_count - (this._res_index+1);
                        if(queries_togo>0)
                            s = s + queries_togo + ' quer' + (queries_togo>1?'ies':'y') + ' to go. ';
                        //count the total amount of loaded records for this rule
                        var cnt_loaded = 0;
                        $.each(this._rules[this._rule_index].results, function(index, value){ cnt_loaded = cnt_loaded + value.length; });
                        s = s + 'Loaded '+cnt_loaded;

                        //alternative
                        alts = alts + "Loaded " + (curr_offset==tot ?tot:curr_offset+' of '+tot)+' for query '+  (this._res_index+1)+' of '+res_steps_count;

                        this.progress_lbl.css('margin-left','10px').html(alts);

                        var progress_value = this._res_index/res_steps_count*100 + curr_offset/tot*100/res_steps_count

                        this.progress_bar.progressbar( "value", progress_value );

                        //this.div_progress.show();
                     }

            }

            /*var offset = this.options.recordset.offset();
            var len   = this.options.recordset.length();
            var total = this.options.recordset.count_total();
            if(total>0){
                this.span_info.show();
                this.span_info.html( (offset+1)+"-"+(offset+len)+"/"+total);
            }else{
                this.span_info.html('');
            }*/
        }else{
            this.progress_lbl.html( '' );
            this.progress_bar.progressbar( "value", 0 );
        }



    }

    , _addNewRecord: function(){


        var url = top.HAPI4.basePathOld+ "records/add/addRecordPopup.php?db=" + top.HAPI4.database;

        Hul.showDialog(url, { height:550, width:700,
                    callback:function(responce) {
/*
                var sURL = top.HEURIST.basePath + "common/php/reloadCommonInfo.php";
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

    , _showDbSummary: function(){

        var url = top.HAPI4.basePath+ "page/databaseSummary.php?popup=1&db=" + top.HAPI4.database;

        var body = this.document.find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};

        Hul.showDialog(url, { height:dim.h*0.8, width:dim.w*0.8} );

    }

    , _doLogin: function(){

        if(doLogin && $.isFunction(doLogin)){  // already loaded in index.php
            doLogin(this.options.isloginforced);
        }else{
            //var that = this;
            $.getScript(top.HAPI4.basePath+'apps/profile/profile_login.js', this._doLogin );
        }


    }

    , _doRegister: function(){

        if(false && !$.isFunction(doLogin)){  // already loaded in index.php
            //var that = this;
            $.getScript(top.HAPI4.basePath+'apps/profile/profile_login.js', this._doRegister );
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
            $.getScript(top.HAPI4.basePath+'apps/profile/profile_edit.js', function() {
                if($.isFunction($('body').profile_edit)){
                    that._doRegister();
                }else{
                    top.HEURIST4.util.showMsgErr('Widget profile edit not loaded!');
                }
            });
        }
        */

    }

});
