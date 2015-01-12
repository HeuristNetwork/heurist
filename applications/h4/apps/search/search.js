/**
* Main search function
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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


$.widget( "heurist.search", {

    // default options
    options: {
        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        isrectype: false,  // show rectype selector
        rectype_set: null, // comma separated list of rectypes, if not defined - all rectypes

        isapplication:true,  // send and recieve the global events

        searchdetails: "map", //level of search results  map - with details, structure - with detail and structure

        has_paginator: false,
        limit: 1000,
        
        islinkmode: true, //show buttons or links

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
        
        this.div_search = $('<div>').css('width','100%').appendTo( this.element );
        this.div_progress = $('<div>').css('width','100%').appendTo( this.element ).hide();

        
        this.input_search = $( "<input>", {width: this.options.has_paginator?'30%':'50%'} )
        .css({'margin-right':'0.2em'})
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.div_search );

        this.div_search_as_guest = $('<span>')
        .css('display', 'inline-black')
        .addClass('logged-out-only')
        .appendTo( this.div_search );

        this.btn_search_as_guest = $( "<button>", {
            text: top.HR("search")
        })
        .appendTo( this.div_search_as_guest )
        .button({icons: {
            //primary: "ui-icon-search"
        }});

        this.div_search_as_user = $('<span>')
        .addClass('logged-in-only')
        .appendTo( this.div_search );

        if(this.options.islinkmode){
            
            this.div_search_links = $('<div>').css('width','100%').appendTo( this.div_search );
            
            var link = $('<a>',{
                text: 'Quick', href:'#'
            }).css('padding-right','1em').appendTo(this.div_search_links);
            this._on( link, {  click: this.showSearchAssistant });
            
            link = $('<a>',{
                text: 'Advanced', href:'#'
            }).css('padding-right','1em').appendTo(this.div_search_links);
            this._on( link, {  click: function(){
            
                //call H3 search builder
                var q = "", 
                    that = this;
                if(!Hul.isnull(this.query_request) && !Hul.isempty(this.query_request.q)){
                    q ="&q=" + encodeURIComponent(this.query_request.q);
                }
                var url = top.HAPI4.basePathOld+ "search/queryBuilderPopup.php?db=" + top.HAPI4.database + q;
                
                Hul.showDialog(url, { callback: 
                    function(res){
                        if(!Hul.isempty(res)) {
                                that.input_search.val(res);
                                that._doSearch();
                        }
                    }});            
            }});
            
            $('<a>',{
                text: 'Syntax', 
                href:  top.HAPI4.basePathOld+'context_help/advanced_search.html', target:'_blank'
            }).css('padding-right','1em').appendTo(this.div_search_links);
            
            link = $('<a>',{
                text: 'Rule Sets', href:'#'
            }).css('padding-right','1em').appendTo(this.div_search_links);
            this._on( link, {  click: function(){
                var  app = appGetWidgetByName('search_links_tree');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).search_links_tree('editSavedSearch', 'rules'); //call public method editRules
                }
            }});
            
            link = $('<a>',{
                text: 'Save As', href:'#'
            }).appendTo(this.div_search_links);
            this._on( link, {  click: function(){
                var  app = appGetWidgetByName('search_links_tree');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).search_links_tree('editSavedSearch', 'saved'); //call public method editRules
                }
            }});
           
            
        }else{
            
            this.btn_search_assistant = $( "<button>", {
                id: 'btn_search_assistant', 
                text: top.HR("search assistant")
            })
            .appendTo( this.div_search_as_user )
            .button({icons: {
                primary: "ui-icon-lightbulb"
                }, text:false});
                
            //show quick search assistant
            this._on( this.btn_search_assistant, {  click: this.showSearchAssistant });
        }

        this.btn_search_as_user = $( "<button>", {         
            text: top.HR("search")
        })
        .css('width', '14em')
        .appendTo( this.div_search_as_user )
        .button({icons: {
            //primary: "ui-icon-search"
        }});

        this.btn_search_domain = $( "<button>", {
            text: top.HR("search option")
        })
        .appendTo( this.div_search_as_user )
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
            click: "_doSearch"
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
            
            this.limit = top.HEURIST.displayPreferences['results-per-page'];  //top.HAPI4.get_prefs('search_limit');
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
                                        top.HEURIST4.util.showMsgErr(response.message);
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
            .css({'width':'50%', 'height':'1.6em', 'display':'inline-block', 'margin-right':'0.2em'})  //, 'height':'22px',  'margin':'0px !important'})
            .progressbar().appendTo(this.div_progress);
            
        this.progress_lbl = $("<div>").css({'position':'absolute','margin-left':'22%', 'top': '4px', 'text-shadow': '1px 1px 0 #fff' })
            .appendTo(this.progress_bar);     
            //font-weight: bold;
       
        //move to search.js         
        this.btn_stop_search = $( "<button>", {text: "Stop"} )
              .css({'display':'inline-block', 'margin-bottom':'15px'})
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

        if(top.HAPI4.currentUser.ugr_ID>0){
            $(this.element).find('.logged-in-only').show(); //.css('visibility','visible');
            //$(this.element).find('.logged-in-only').css('visibility','visible');

            //$('.logged-out-only').css('visibility','hidden');
            $(this.element).find('span.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
            ///$(this.element).find('.logged-in-only').css('visibility','hidden');
            //$('.logged-out-only').css('visibility','visible');
            $(this.element).find('span.logged-out-only').show();
        }

        this.btn_search_as_user.button( "option", "label", this._getSearchDomainLabel(this.options.search_domain));
        if(this.btn_search_limit) this.btn_search_limit.button( "option", "label", this.limit );

        if(this.select_rectype){
            this.select_rectype.empty();
            top.HEURIST4.util.createRectypeSelect(this.select_rectype.get(0), this.options.rectype_set, !this.options.rectype_set);
        }
    },
    
    /**
    * it is called only for the very first search request (except all other: incremental and rules)
    */
    _onSearchStart: function(){
        
            this.div_search.hide();
            this.div_progress.show();
            
            
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
        
            if(data!=null && top.HEURIST4.util.isempty(data.topids)){ //topids not defined - this is not rules request
             
                 top.HEURIST4.current_query_request = data;  //the only place where this values is assigned  
                 that.query_request = data; //keep for search in current result
                 
                 if(data.source!=that.element.attr('id') ){
                    top.HAPI4.currentRecordset = null;
                    that.input_search.val(data.q);
                    that.options.search_domain = data.w;
                    that._refresh();
                 }
                 
                 if(top.HAPI4.currentRecordset==null){
                    that._onSearchStart(); 
                 }
            }
            
        }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){ //get new chunk of data from server
            
                if(that.query_request!=null &&  data.queryid()==that.query_request.id) {  
                    
                    //save increment into current rules.results
                    var records_ids = data.getIds()
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
                            
                        }
                    }
                    
                    that._renderProgress();
                    if(!that._doSearchIncrement()){//load next chunk
                        that._searchCompleted();
                    } 
                }
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES){
                
                
                if(data){
                    //create flat rule array
                    that._doApplyRules(data); //indexes are rest inside this function
                  
                    //if rules were applied before - need to remove all records except original and re-render
                    if(!top.HEURIST.util.isempty(that._rules) && that._rules[0].results.length>0){
                         
                         //keep json (to possitble save as saved searches)
                         that.query_request.rules = data;
                        
                         //remove results of other rules and re-render the original set of records only                        
                         var rec_ids_level0 = [];
                         
                         var idx;
                         that._rules[0].results = that._rules[0].results;
                         for(idx=0; idx<that._rules[0].results.length; idx++){
                            rec_ids_level0 = rec_ids_level0.concat(that._rules[0].results[idx]);
                         }
                         
                         //var recordset_level0 
                         top.HAPI4.currentRecordset = top.HAPI4.currentRecordset.getSubSetByIds(rec_ids_level0);

                         that._rule_index = -2;
                         that._res_index = 0;
                         
                         //fake result searh event
                         $(that.document).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ null ]);  //global app event
                         $(that.document).trigger(top.HAPI4.Event.ON_REC_SEARCHRESULT, [ top.HAPI4.currentRecordset ]);  //global app event
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
        if(value=='b' || value=='bookmark') { lbl = 'Search (Bookmarked)'; }
        else if(value=='r') { lbl = 'recently added'; } //not implemented
            else if(value=='s') { lbl = 'recently selected'; } //not implemented
                else if(value=='c') { lbl = 'Search (in current)'; } //todo
                    else { lbl = 'Search'; this.options.search_domain='a';}
        return lbl;
    },

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

            var request = {q: qsearch, w: this.options.search_domain, f: this.options.searchdetails
                            , source:this.element.attr('id') };

            //that._trigger( "onsearch"); //this widget event
            //that._trigger( "onresult", null, resdata ); //this widget event

            top.HAPI4.currentRecordset = null;
            //this._onSearchStart();
            
            //perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));
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

                    var popup = $( this.search_assistant )
                    .show()
                    .position({my: "right top+3", at: "right bottom", of: this.input_search });
                    //.position({my: "right top", at: "right bottom", of: this.btn_search_assistant });

                    function _hidethispopup(event) {
                        if($(event.target).closest(popup).length==0 && $(event.target).attr('id')!='menu-search-quick-link'){
                            popup.hide();
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
        .addClass('text ui-corner-all ui-widget-content')  // menu-or-popup
        .zIndex(9999)
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .hide();

        var that = this;                
        //load template            
        $dlg.load("apps/search_quick.html?t="+(new Date().getTime()), function(){

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
            that.showSearchAssistant(); //that.btn_search_assistant.click();
        });

    }

    // recalculate search query value
    ,calcShowSimpleSearch: function (e) {

        var q = this.search_assistant.find("#sa_rectype").val(); if(q) q = "t:"+q;
        var fld = this.search_assistant.find("#sa_fieldtype").val(); if(fld) fld = "f:"+fld+":";
        var ctn = this.search_assistant.find("#fld_enum").is(':visible') ?this.search_assistant.find("#sa_termvalue").val() 
        :this.search_assistant.find("#sa_fieldvalue").val();    

        var asc = ($("#sa_sortasc:checked").length > 0 ? "" : "-");
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
        $(this.document).off(top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SEARCHRESULT+' '+top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES);
        
        // remove generated elements
        this.btn_search_as_guest.remove();
        this.btn_search_as_user.remove();
        this.btn_search_domain.remove();
        if(this.btn_search_assistant) this.btn_search_assistant.remove();
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
                    this.query_request.increment = true;
                    this.query_request.o = new_offset;
                    this.query_request.source = this.element.attr('id');
                    top.HAPI4.RecordMgr.search(this.query_request, $(this.document));
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
                     this.query_request.increment = true;
                     this.query_request.o = 0;
                     this.query_request.source = this.element.attr('id');
                     top.HAPI4.RecordMgr.search(this.query_request, $(this.document));
                     return true;
                
            }
        }

        return false;
    }
    
    , _searchCompleted: function(){
        
         this.div_progress.hide();
         this.div_search.show();
        
         if(top.HAPI4.currentRecordset && top.HAPI4.currentRecordset.length()>0)
             $(this.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ top.HAPI4.currentRecordset ]); //global app event
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
            
            var tot = top.HAPI4.currentRecordset.count_total();
            
            if(curr_offset>tot) curr_offset = tot;
            
            if(this._rule_index<1){  //this is main request
                    
                    //search in progress
                     this.progress_lbl.html( (curr_offset==tot)?tot:curr_offset+'~'+tot );
                     
                     this.progress_bar.progressbar( "value", curr_offset/tot*100 );
                     //this.progress_bar.html( $('<div>').css({'background-color':'blue', 'width': curr_offset/tot*100+'%'}) );
            
                     if(curr_offset==tot) {
                           //this.div_progress.delay(500).hide();
                     }
                     
            }else{ //this is rule request
            
                     if( this._rule_index < this._rules.length){
            
                        var res_steps_count = this._rules[this._rules[this._rule_index].parent].results.length;
                
                        this.progress_lbl.html( (curr_offset==tot?tot:curr_offset+'~'+tot)+' of '+
                            (this._res_index+1)+'~'+res_steps_count);
                            
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
    

});
