/**
*  Apply faceted search
* TODO: Check that this is what it does and that it is not jsut an old version
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

/* Explanation of faceted search

There are two types of queries: 1) to search facet values 2) to search results

Examples
1) No levels:   

search results: t:10 f:1:"XXX" - search persons by name
facet search:   f:1  where t:10 + other current queries

2) One level:

search results: t:10 linked_to:5-61 [t:5 f:1:"XXX"] - search persons where multimedia name is XXX
facet search:   f:1  where t:5 linkedfrom:10-61 [other current queries(parent query)]

3) Two levels

search results: t:10 linked_to:5-61 [t:5 linked_to:4-15 [t:4 f:1:"XXX"]] - search persons where multimedia has copyright of organization with name is XXX
facet search:   f:1 where t:4 linkedfrom:5-15 [t:5 linkedfrom:10-61 [other current queries for person]]   - find organization who is copyright of multimedia that belong to person


Thus, our definition has the followig structure
rectype - main record type to search
domain
facets:[ [
code:  10:61:5:15:4:1  to easy init and edit    rt:ft:rt:ft:rt:ft  if link is unconstrained it will be empty  61::15
title: "Author Name < Multimedia"
id:  1  - field type id 
type:  "freetext"  - field type
levels: [t:4 linkedfrom:5-15, t:5 linkedfrom:10-61]   (the last query in this array is ommitted - it will be current query)

search - main query to search results
            [linked_to:5-61, t:5 linked_to:4-15, t:4 f:1]    (the last query in the top most parent )

currentvalue:
history:  - to keep facet values (to avoid redundat search)

],
the simple (no level) facet
[
id: 1
code: 10:1 
title: "Family Name"
type:  "freetext"
levels: []
search: [t:10 f:1] 
orderby: count|null
groupby
]

]


NOTE - to make search for facet value faster we may try to omit current search in query and search for entire database
  
facet search general parameters are the same to saved search plus several special

domain
rules
ui_title - title in user interface
ui_viewmode - result list viewmode
title_hierarchy - show hierarchy in facet header
sup_filter - suplementary filter that is set in design time
add_filter - additional filter that can be set in run time 
add_filter_original - original search string for add_filter if it is json
search_on_reset - search for empty form (on reset and on init)

viewport - collapse facet to limit count of items

rectypes[0] 
*/

/*
requires:
editing_input
*/
$.widget( "heurist.search_faceted", {

    _MIN_DROPDOWN_CONTENT: 50,//0, //min number in dropdown selector, otherwise facet values are displayed in explicit list
    _FT_INPUT: 0,  //direct search input
    _FT_SELECT: 1, //slider for numeric and date  (for freetext it is considered as _FT_LIST)
    _FT_LIST: 2,
    _FT_COLUMN: 3,

    
    // default options
    options: {
        // callbacks
        params: {},
        ispreview: false,
        showclosebutton: true,
        onclose: null,
        search_realm: null,
        preliminary_filter:null
    },

    cached_counts:[], //stored results of get_facets by stringified query index
    _input_fields:{},
    _request_id: 0, //keep unique id to avoid redraw facets for old requests
    _first_query:[], //query for all empty values
    _isInited: true,
    _current_query: null,
    
    _currentRecordset:null,

    // the widget's constructor
    _create: function() {

        var that = this;
        
        if(!$.isFunction($('body')['editing_input'])){
            $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/editing/editing_input.js', function() {
                that._create();
            });
            return;
        }
        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this.div_header = $( "<div>" ).appendTo( this.element );
        
        if(!this.options.ispreview){                       //padding-top:1.4em;
            this.div_title = $('<div>')
            .css({padding:'0.5em 0em 0.5em 1em','font-size':'1.4em','font-weight':'bold',color:'rgb(142, 169, 185)','max-width':'90%'})
//style='text-align:left;margin:4px 0 0 0!important;padding-left:1em;width:auto, max-width:90%'></h3")
                    .addClass('truncate').appendTo( this.div_header );
        }

        //"font-size":"0.7em",
        this.div_toolbar = $( "<div>" ).css({"float":"right","padding-top":"0.3em","padding-right":"0.6em"})
                .appendTo( this.div_header );

        this.btn_submit = $( "<button>", { text: window.hWin.HR("Submit") })
        .appendTo( this.div_toolbar )
        .button();
        
        this.btn_reset = $( "<button>", {title:window.hWin.HR("Clear all fields / Reset all the filters to their initial states") })
        .appendTo( this.div_toolbar )
        .button({label: window.hWin.HR("RESET FILTERS"), icon: 'ui-icon-arrowreturnthick-1-w', iconPosition:'end' }).hide();
        
        this.btn_save = $( "<button>", { text: window.hWin.HR("Save state") })
        .appendTo( this.div_toolbar )
        .button().hide(); //@todo

        this.btn_close = $( "<button>", { 
                    title:window.hWin.HR("Close this facet search and return to the list of saved searches") })
        .appendTo( this.div_toolbar )
        .button({icon: "ui-icon-close", iconPosition:'end', label:window.hWin.HR("Close")});

        this.btn_close.find('.ui-icon-close').css({'font-size': '1.3em', right: 0});
        
        this._on( this.btn_submit, { click: "doSearch" });
        this._on( this.btn_reset, { click: "doResetAll" });
        this._on( this.btn_save, { click: "doSaveSearch" });
        this._on( this.btn_close, { click: "doClose" });


        this.facets_list_container = $( "<div>" )
        .css({"top":((this.div_title)?'6em':'2em'),"bottom":0,"left":'1em',"right":'0.5em',"position":"absolute"}) //was top 3.6
        .appendTo( this.element );

        this.facets_list = $( "<div>" )
        .css({"overflow-y":"auto","overflow-x":"hidden","height":"100%"}) //"font-size":"0.9em",
        .appendTo( this.facets_list_container );

        var current_query_request_id;
        
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, 
        
        function(e, data) {
            
            if(data && that.options.search_realm && that.options.search_realm!=data.search_realm) return;
            if(data.reset) return; //ignore

            if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
            
                    if(data){
                        if(data.source==that.element.attr('id') ){   //search from this widget
                              current_query_request_id = data.id;
                        }else{
                            //search from outside - close this widget
                            that._trigger( "onclose");
                            //that.doClose();
                        }
                    }
                
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                
                var recset = data.recordset;
                if(recset && recset.queryid()==current_query_request_id) {
                      //search from this widget
                      that._currentRecordset = recset;
                      that._isInited = false;
                      that._recalculateFacets(-1);       
                }         
            }
        });

        //this._refresh();
        this.doReset();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        if(key=='add_filter'){
            this.options.params.add_filter = value;
        }else if(key=='add_filter_original'){
            this.options.params.add_filter_original = value;
        }
    },
    
    _setOptions: function( options ) {
        this._superApply( arguments );
        if(window.hWin.HEURIST4.util.isnull(options['add_filter'])){
            this.cached_counts = [];
            //this._refresh();
            this.doReset();
        }
    },

    _refreshTitle: function(){    
        if(this.div_title) {
            if(this.options.params.ui_title){
                new_title = this.options.params.ui_title;
            }else{
                var svsID = this.options.query_name;
                if(svsID > 0){
                    
                    if (window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                                window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID])
                    {
                         new_title = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID][0];//_NAME];                
                    }else{
                        
                        window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                                    that._refreshTitle();
                                }
                        });
                    }
                    
                }else{
                    new_title = svsID;
                }
            }
            if(window.hWin.HEURIST4.util.isnull(new_title)) new_title='';
            this.div_title.html(new_title);
        }
    },
    
    /* 
    * private function    - NOT USED
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
         var facets = this.options.params.facets;
         var hasHistory = false, facet_index, len = facets?facets.length:0;
         for (facet_index=0;facet_index<len;facet_index++){
              if( !window.hWin.HEURIST4.util.isempty(facets[facet_index].history) ){
                  hasHistory = true;
                  break;
              }
         }
        
        this._refreshTitle();
                            
        if(this.options.ispreview){
            this.btn_save.hide(); 
            this.btn_close.hide(); 
        }else{
            if(hasHistory) {
                //if(this.div_title) this.div_title.css('width','45%');
                this.btn_reset.show()   
                //this.btn_save.show();  //@todo
            }else{
                //if(this.div_title) this.div_title.css({'width':'auto', 'max-width':'90%'});
    
                this.btn_reset.hide()   
                this.btn_save.hide(); 
            }
            
            if(this.options.showclosebutton){
                this.btn_close.show(); 
            }else{
                this.btn_close.hide(); 
            }
        }

        this.doRender();
        //ART2907 this.doSearch();
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        $(this.document).off( window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART );
        
        // remove generated elements
        if(this.div_title) this.div_title.remove();
        this.cached_counts = null;
        this.btn_submit.remove();
        this.btn_close.remove();
        this.btn_save.remove();
        this.btn_reset.remove();
        this.div_toolbar.remove();

        this.facets_list.remove();
        this.facets_list_container.remove();
    }

    //Methods specific for this widget---------------------------------

    //
    // crerate lists of queries to search facet values
    // create main JSON query
    //
    ,_initFacetQueries: function(){
       
       var that = this, mainquery = [];
       
       $.each(this.options.params.facets, function(index, field){
       
           //value is defined - it will be used to create query
           if( !window.hWin.HEURIST4.util.isnull(field['var']) && field['code']){
               //create new query and add new parameter
               var code = field['code'];
//console.log(code);               
               
               code = code.split(':');
               
               field['id']   = code[code.length-1];
               field['rtid'] = code[code.length-2];
               if(field['isfacet']!=that._FT_INPUT){  //not direct input
               
                       //create query to search facet values
                       function __crt( idx ){
                           var res = null;
                           if(idx>0){  //this is relation or link
                               
                                res = [];
                               
                                var pref = '';
                                var qp = {};
                                
                                qp['t'] = code[idx];
                                res.push(qp);
                                
                                
                                var fld = code[idx-1]; //link field
                                if(fld.indexOf('lf')==0){
                                    pref = 'linked_to';    
                                }else if(fld.indexOf('lt')==0){
                                    pref = 'linkedfrom';    
                                }else if(fld.indexOf('rf')==0){
                                    pref = 'related_to';    
                                    //pref = 'links';
                                }else if(fld.indexOf('rt')==0){
                                    pref = 'relatedfrom';    
                                    //pref = 'links';
                                }
                                
                                qp = {};
                                qp[pref+':'+fld.substr(2)] = __crt(idx-2);    
                                res.push(qp);
                           }else{ //this is simple field
                               res = '$IDS'; //{'ids':'$IDS}'};
                           }
                           return res;
                       }
                       
                       /*if(code.length-2 == 0){
                           field['facet'] = {ids:'$IDS'};
                       }else{}*/
                       field['facet'] = __crt( code.length-2 );
                       
//console.log('res = '+field['facet']);                       
               }
               
               
               function __checkEntry(qarr, key, val){
                    var len0 = qarr.length, notfound = true, isarray;
                    
                    for (var i=0;i<len0;i++){
                        if(! window.hWin.HEURIST4.util.isnull( qarr[i][key] ) ){ //such key already exsits
                            
                            if(window.hWin.HEURIST4.util.isArray(qarr[i][key])){ //next level
                                return qarr[i][key];   
                            }else if(qarr[0][key]==val){ //already exists
                                return qarr;
                            }
                        }
                    }

                    var predicat = {};
                    predicat[key] = val;
                    qarr.push(predicat);
                    
                    if(window.hWin.HEURIST4.util.isArray(val)){
                        return val;
                    }else{
                         return qarr;
                    }
               }            
               
                    var curr_level = mainquery;     
                    var j = 0;    
                    while(j<code.length){
                        
                        var rtid = code[j];
                        var dtid = code[j+1];
                        
                        //add recordtype
                        curr_level = __checkEntry(curr_level,"t",rtid);
                        
                        var linktype = dtid.substr(0,2);
                        var slink = null;
                        
                        if(linktype=='rt'){
                            slink = "related_to:";
                            //slink = "links:";
                        }else if(linktype=='rf'){
                            slink = "relatedfrom:";
                            //slink = "links:";
                        }else if(linktype=='lt'){
                            slink = "linked_to:";
                        }else if(linktype=='lf'){
                            slink = "linkedfrom:";
                        }

                        var key, val;                         
                        if(slink!=null){

                            var rtid_linked = code[j+2];
                            key  = slink+rtid_linked+":"+dtid.substr(2); //rtid need to distinguish links/relations for various recordtypes
                            val = [];
                        }else{
                            key = "f:"+dtid
                            val = "$X"+field['var']; 
                        }
                        curr_level = __checkEntry(curr_level, key, val);
           
//console.log(curr_level);                     
                        j=j+2;
                    }               
               
           }
       });
     
       this.options.params['q'] = mainquery;
   }

    ,doResetAll: function(){
        this.options.params.add_filter = null;
        this.options.params.add_filter_original = null;
        this.doReset();
    }
    //
    // reset current search 
    // recreate facet elements/ or form inputs
    //
    ,doReset: function(){

        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ {reset:true, search_realm:this.options.search_realm} ]);  //global app event to clear views
        
        var facets = this.options.params.facets;

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(facets)){
            var facet_index, len = facets.length;

            for (facet_index=0;facet_index<len;facet_index++){
                facets[facet_index].history = [];
                facets[facet_index].selectedvalue = null;
                
                //support old format
                if(window.hWin.HEURIST4.util.isnull(facets[facet_index].isfacet) || facets[facet_index].isfacet==true){
                            facets[facet_index].isfacet = this._FT_SELECT;
                }else if (facets[facet_index].isfacet==false){
                            facets[facet_index].isfacet = this._FT_INPUT;
                }
                
                //facets[facet_index].isfacet = facets[facet_index].isfacet || window.hWin.HEURIST4.util.isnull(facets[facet_index].isfacet);
            }
        }
        
        
       this._current_query = null;
       // create list of queries to search facet values 
       this._initFacetQueries();
        
       
       if(this.facets_list) this.facets_list.empty();
       
       var $fieldset = $("<fieldset>").css({'font-size':'0.9em','background-color':'white'})
                    .addClass('fieldset_search').appendTo(this.facets_list);

       //hide submit button will be displayed in case all fields are input fields (not facets)
       if(true){ 
            $fieldset.css({'padding':'0'});
            this.btn_submit.hide();
       }
       
        if(this.options.ispreview || !this.options.showclosebutton){
            this.btn_close.hide(); 
        }else{
            this.btn_close.show(); 
        }
        
        this._refreshTitle();

        this.btn_reset.hide()   
        this.btn_save.hide(); 
       
       
       var that = this;
       that._input_fields = {};
       
       $.each(this.options.params.facets, function(idx, field){
       
           //content_id+"_"+
           
          var codes = field['code'].split(':');
          var j = 0;
          var harchy = [];
          while (j<codes.length){
               harchy.push(window.hWin.HEURIST4.rectypes.names[codes[j]]);
               j = j + 2;
          }
          
          harchy = '<span class="truncate" style="display:inline-block;width:99%;font-weight:normal !important">'
                + harchy.join(" &gt; ") + "</span><br/>&nbsp;&nbsp;&nbsp;";
           
           if(!window.hWin.HEURIST4.util.isnull(field['var']) && field['code'] ){
               
             if(!field['help']) field['help'] = '';
               
             if(field['isfacet']!=that._FT_INPUT){
                    
                    //inpt.find('.input-div').hide();
                    //inpt.find('.header').css({'background-color': 'lightgray', 'padding': '5px', 'width': '100%'});
                    //inpt.find('.editint-inout-repeat-button').hide();
                    
                    $("<div>",{id: "fv_"+field['var'] }).html(      //!!!!
                        '<div class="header" title="'+field['help']+'">'   // style="width: 100%; background-color: lightgray; padding: 5px; width:100%"
                              +(that.options.params.title_hierarchy?harchy:'')
                              +'<h4 style="display:inline-block;margin:0;">'
                              + field['title'] + '</h4>'+  //field['order']+'  '+
                              ((field['help'])?'<span class="bor-tooltip ui-icon ui-icon-circle-help" '
                              +'style="width:17px;height:17px;margin-left:4px;display:inline-block;vertical-align:text-bottom;" title="'
                              +field['help']+'"></span>':'')+
                        '</div>'+
                        '<div class="input-cell" style="display:block;"></div>').appendTo($fieldset);    //width:100%
                  
             }else{
                 //instead of list of links it is possible to allow enter search value directly into input field
                 
                   var ed_options = {
                                varid: field['var'],  //content_id+"_"+
                                recID: -1,
                                rectypeID: field['rtid'],
                                dtID: field['id'],
                                rectypes: window.hWin.HEURIST4.rectypes,
                                values: [''],
                                readonly: false,
                                title:  (that.options.params.title_hierarchy?harchy:'')
                                        + "<span style='font-weight:bold'>" + field['title'] + "</span>",
                                detailtype: field['type'],  //overwrite detail type from db (for example freetext instead of memo)
                                showclear_button: false,
                                suppress_prompts: true,  //supress help, error and required features
                                suppress_repeat: true,
                                is_faceted_search: true
                        };
                        
                   if(isNaN(Number(field['id']))){
                       ed_options['dtFields'] = {
                           dty_Type: field['type'],
                           rst_RequirementType: 'optional',
                           rst_MaxValues: 1,   //non repeatable
                           rst_DisplayWidth:0
                           //rst_DisplayHelpText: field['help']
                       };
                   }

                    var inpt = $("<div>",{id: "fv_"+field['var'] }).editing_input(   //this is our widget for edit given fieldtype value
                            ed_options
                        );

                    inpt.appendTo($fieldset);
                    that._input_fields['$X'+field['var']] = inpt;
                    
                    inpt.find('.header').attr('title', field['help'])
                        .css('display','block')
                        .html('<h4 style="display:inline-block;margin:0;">'+field['title']+'</h4>');
                                                                        
                    //@todo make as event listeneres
                    //assign event listener
                    //var $inputs = inpt.editing_input('getInputs');
                    that._on( inpt.find('input'), {
                            keypress:
                            function(e){
                                var code = (e.keyCode ? e.keyCode : e.which);
                                if (code == 13) {
                                    window.hWin.HEURIST4.util.stopEvent(e);
                                    e.preventDefault();
                                    that.doSearch();
                                }
                            },
                            keyup: function(e){
                                    var btn_reset = inpt.find('.resetbutton');
                                    if($(e.target).val()==''){
                                        btn_reset.css('visibility','hidden');   
                                    }else{
                                        btn_reset.css('visibility','visible');   
                                    }
                            }
                    });
                    that._on( inpt.find('select'), {
                        change: function(e){
                                var btn_reset = inpt.find('.resetbutton');
                                if($(e.target).val()==''){
                                    btn_reset.css('visibility','hidden');   
                                }else{
                                    btn_reset.css('visibility','visible');   
                                }
                                that.doSearch();
                        }});//"doSearch"});                   
                    
                    inpt.find('.input-cell > .input-div').css({'display':'inline-block'}); // !important
                    inpt.find('.input-cell').css('display','block');
                    
                    inpt.find('input').removeClass('text').css({'width':'150px'});
                    inpt.find('select').removeClass('text').css({'width':'150px'});
                    
                    var btn_add = $( "<button>",{title:'To clear previous search click the RESET button'})
                    .addClass("smallbutton")
                    //.css('position','absolute')
                    .insertBefore( inpt.find('.input-cell .heurist-helper1') )
                    .button({icons:{primary: "ui-icon-search"}, text:false})
                    that._on( btn_add, { click: "doSearch" });

                    var btn_clear = $( "<span>")
                    .insertBefore( inpt.find('.input-cell .input-div') )
                    .addClass("ui-icon ui-icon-arrowreturnthick-1-w resetbutton")
                    .css({'display':'inline-block', 'visibility':'hidden', 'font-size':'0.9em', 'vertical-align':'middle'})
                    that._on( btn_clear, { click: function(){
                        inpt.find('input').val('');
                        inpt.find('select').val('');
                        inpt.find('.resetbutton').css('visibility','hidden');
                        that.doSearch();
                    } });

                    
             }
           }
       });
       
       //'background-color': 'lightgray', 
       $fieldset.find('.header').css({width: '100%', padding: '5px', width:'100%'})
       $fieldset.find('.input-cell').css({ 'padding':'5px' });
       
       $fieldset.find('.bor-tooltip').tooltip({
            position: { my: "center bottom", at: "center top-5" },
            /* does not work
            classes: {
                "ui-tooltip": "ui-corner-all tooltip-inner"
            },*/
            tooltipClass:"tooltip-inner",
            hide: { effect: "explode", duration: 500 }
        });
       
       this._isInited = true;
       //get empty query
       this._first_query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q ); //clone 
       this._fillQueryWithValues( this._first_query );
       

       
//console.log('start search with empty form '+this.options.params.search_on_reset);        
        if(this.options.params.search_on_reset || 
           !window.hWin.HEURIST4.util.isempty(this.options.params.add_filter)){
            //search at once - even none facet value provided
            this.doSearch();
        }else{
            this._recalculateFacets(-1);     
        }
       
    }

    ,doSaveSearch: function(){
        //alert("@todo");
    }

    ,doClose: function(){
        //$(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ {reset:true, search_realm:this.options.search_realm} ]);  //global app event to clear views
        this._trigger( "onclose");
    }


    ,doRender: function(){


    }
    
    //
    // return array of pairs - code:value
    //
    ,getFacetsValues: function(){
        
        var _inputs = this._input_fields;
        var res = [];
        
        var facets = this.options.params.facets;
        var facet_index, len = facets.length;
        for (facet_index=0;facet_index<len;facet_index++){

                if(facets[facet_index]['isfacet']==this._FT_INPUT){  //this is direct input
                
                     var val = '$X'+facets[facet_index]["var"];
                     var sel = $(_inputs[val]).editing_input('getValues');
                     if(sel && sel.length>0){
                         facets[facet_index].selectedvalue = {value:sel[0]};
                     }else{
                         facets[facet_index].selectedvalue = null;
                     }
                }
                
                var selval = facets[facet_index].selectedvalue;
                
                if(selval && !window.hWin.HEURIST4.util.isempty(selval.value)){
                    res.push({code:facets[facet_index].code, value:selval.value});
                }

        }//for

        return res;                
    }

    // we have a query (that searches main recordtype) it is created once (onReset)
    // for example {"t": "3"},{"f:1":"$X73105"},{"linked_to:4:15":[{t: "4"},{f:22: "$X47975"}]}
    // $Xn - is facet values to be substituted in this query
    //
    // 1. substiture Xn variable in query array with value from input form
    // 2. remove branch in query if all variables are empty (except root)
    //
    //  facet_index_do_not_touch - $Xn will be relaced with $FACET_VALUE - to use on server side for count calculations
    // 
    ,_fillQueryWithValues: function( q, facet_index_do_not_touch ){
        
        var _inputs = this._input_fields;
        var that = this;
        var isbranch_empty = true;
            
        $(q).each(function(idx, predicate){ //loop through all predicates
            
            $.each(predicate, function(key,val)
            {
                if( $.isArray(val) ) { //|| $.isPlainObject(val) ){
                     var is_empty = that._fillQueryWithValues(val, facet_index_do_not_touch);
                     isbranch_empty = isbranch_empty && is_empty;
                     
                     if(is_empty){
                        //remove entire branch if none of variables are defined
                        delete predicate[key];  
                     }
                }else{
                    if(typeof val === 'string' && val.indexOf('$X')===0){ //replace $xn with facet value
                        
                        //find facet by variable 
                        var facets = that.options.params.facets;
                        var facet_index, len = facets.length;
                        for (facet_index=0;facet_index<len;facet_index++){
                            if(facets[facet_index]["var"] == val.substr(2)){ //find facet by variable

                                if(facets[facet_index]['isfacet']==that._FT_INPUT){  //this is direct input
                                     var sel = $(_inputs[val]).editing_input('getValues');
                                     if(sel && sel.length>0){
                                         facets[facet_index].selectedvalue = {value:sel[0]};
                                     }else{
                                         facets[facet_index].selectedvalue = null;
                                     }
                                }else if(facet_index_do_not_touch==facet_index){ //this is for count calculation query
                                     predicate[key] = '$FACET_VALUE';
                                     isbranch_empty = false;
                                     break;
                                }
                                
                                var selval = facets[facet_index].selectedvalue;
                                
                                if(selval && !window.hWin.HEURIST4.util.isempty(selval.value)){
                                    
                                    if(facets[facet_index].groupby=='decade'){
                                        selval.value = selval.value + '<>' +(Number(selval.value)+10+'-01-01 00:00');
                                    }else if(facets[facet_index].groupby=='century'){
                                        selval.value = selval.value + '<>' +(Number(selval.value)+100+'-01-01 00:00');
                                    }
                                    
                                    predicate[key] = selval.value;
                                    isbranch_empty = false;
                                }else{
                                    delete predicate[key];  
                                }
                                
                                break;
                            }
                        }

                    }
                }
            });
            
        });

        /*$(q).each(function(idx, predicate){
            if(Object.keys(predicate).length==0){
                 q.splice(idx, 1)
            }
        });*/
        
        var  idx = 0
        while (idx<q.length){
            if(Object.keys(q[idx]).length==0){
                 q.splice(idx, 1)
            }else{
                idx++;
            }
        }
        
            
        return isbranch_empty;
    }
    
    
    ,doSearch : function(){

//console.log('dosearchj');
//console.log( this.options.params.q );

            var query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q ); //clone 
            var isform_empty = this._fillQueryWithValues(query);

//console.log('form is empty '+isform_empty);                
//console.log( query );
            
            if(isform_empty && 
                window.hWin.HEURIST4.util.isempty(this.options.params.add_filter) && 
                !this.options.params.search_on_reset){
                
                if(true){
                    //clear main result set
                    
                    this.doReset();
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr('Define at least one search criterion');
                }
                return;
            }else if(!this.options.ispreview){
                //this.div_title.css('width','45%');
                this.btn_reset.show()   
                //@todo this.btn_save.show(); 
            }
            
            var div_facets = this.facets_list.find(".facets");
            if(div_facets.length>0)  div_facets.empty();

            //this approach adds supplemntary(preliminary) filter to every request 
            //it works however 
            //1) it requires that this filter must be a valid json  - FIXED
            //2) it makes whole request heavier
            //add additional/supplementary filter
            this._current_query = window.hWin.HEURIST4.util.mergeHeuristQuery(query, 
                            this.options.params.sup_filter, this.options.params.add_filter);
            
            
//{"f:10":"1934-12-31T23:59:59.999Z<>1935-12-31T23:59:59.999Z"}            
            this._current_query.push({sortby:'t'});
//console.log( this._current_query );

            
            var request = { q: this._current_query, 
                            w: this.options.params.domain, 
                            detail: 'ids', 
                            source:this.element.attr('id'), 
                            qname: this.options.query_name,
                            rules: this.options.params.rules,
                            viewmode: this.options.params.ui_viewmode,
                            //to keep info what is primary record type in final recordset
                            primary_rt: this.options.params.rectypes[0],
                            search_realm: this.options.search_realm
                            }; //, facets: facets
                            
            if(this.options.ispreview){
                request['limit'] = 1000;    
            }
            
            //perform search
            window.hWin.HAPI4.SearchMgr.doSearch( this, request );
            
            //perform search for facet values
            //that._recalculateFacets(content_id);
    }
    
    //-------------------------------------------------------------------------------
    //
    // called on ON_REC_SEARCH_FINISH
    // perform search for facet values and counts and redraw facet fields
    // @todo query - current query - if resultset > 1000, use query
    // _recalculateFacets (call server) -> as callback _redrawFacets -> _recalculateFacets (next facet)
    //
    , _recalculateFacets: function(field_index){
     
//@todo need to check that the sequence is not called more than once - otherwise we get multiple controls on facets
        
        //return;
        
        // this._currentquery
        // this._resultset
        if(isNaN(field_index) || field_index<0){
                field_index = -1;  
                
                this._request_id =  Math.round(new Date().getTime() + (Math.random() * 100));
            
                var div_facets = this.facets_list.find(".facets");
                if(div_facets.length>0)
                    div_facets.empty()
                    .css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white20.gif) no-repeat center center');
        }
        
        var that = this;
        
        var i = field_index;
        for(;i< this.options.params.facets.length; i++)
        {
            var field = this.options.params.facets[i];
            if(i>field_index && field['isfacet']!=that._FT_INPUT && field['facet']){
                
                if(field['type']=='enum' && field['groupby']=='firstlevel' && 
                                !window.hWin.HEURIST4.util.isnull(field['selectedvalue'])){
                        this._redrawFacets({status:window.hWin.ResponseStatus.OK,  facet_index:i}, false );
                        return;
                }
                
                var subs_value = null;
                
                if(this._isInited){
                    //replace with current query   - @todo check for empty 
                    subs_value = window.hWin.HEURIST4.util.mergeHeuristQuery(this._first_query, 
                                    this.options.params.sup_filter, this.options.params.add_filter);
                }else{
                    //replace with list of ids
                    subs_value = window.hWin.HEURIST4.util.mergeHeuristQuery(this._first_query,
                                    {ids:this._currentRecordset.getMainSet().join(',')});
                }
                
                //
                // substitute $IDS in facet query with list of ids OR current query(todo)
                // 
                function __fillQuery(q){
                            $(q).each(function(idx, predicate){
                                
                                $.each(predicate, function(key,val)
                                {
                                        if( $.isArray(val) || $.isPlainObject(val) ){
                                            __fillQuery(val);
                                         }else if( (typeof val === 'string') && (val == '$IDS') ) {
                                            //substitute with array of ids
                                            predicate[key] = subs_value;
                                         }
                                });                            
                            });
                }

                //get other parameters for given rectype
                function __getOtherParameters(query, rt){
                    
                            var res = null;

                            if($.isArray(query)){
                            
                            $(query).each(function(idx, predicate){
                                
                                $.each(predicate, function(key,val)
                                {
                                        if(key=='t' && val==rt){

                                            query.splice(idx,1);
                                            res = query
                                            return false;
                                            
                                        }else if( $.isArray(val) || $.isPlainObject(val) ){
                                            
                                            res = __getOtherParameters(val, rt);
                                            return false;
                                        }
                                });                            
                            });
                            
                            }
                    
                            return res;
                    
                }
     
//DBG console.log(field);
                
                var query, needcount = 2;
                if( (typeof field['facet'] === 'string') && (field['facet'] == '$IDS') ){ //this is field form target record type
                
                    if(this._isInited){
                        //replace with current query   - @todo check for empty 
                        query = this._first_query;

                        //add additional/supplementary filter
                        query = window.hWin.HEURIST4.util.mergeHeuristQuery(query, 
                                        this.options.params.sup_filter,   //suplementary filter defined in wiz
                                        this.options.params.add_filter);  //dynaminc addition filter

                        
                    }else{
                        //replace with list of ids
                        query = {ids: this._currentRecordset.getMainSet().join(',')};
                    }
                
                    needcount = 1;
                    
                }else{
                    query = window.hWin.HEURIST4.util.cloneJSON(field['facet']); //clone 
                    
                    //change $IDS for current set of target record type
                    __fillQuery(query);                
                    
                    //add other parameters for rectype of this facet
                    if(this._current_query && false){ //2018-01-09 Do not use other parameters since we use IDS
                        var copyquery = window.hWin.HEURIST4.util.cloneJSON(this._current_query); 
                        var otherparams = __getOtherParameters(copyquery, field['rtid']);
                        if(null!=otherparams){
                             query = query.concat(otherparams);
                        }
                    }
                    
                }
                
                //this is query to calculate counts for facet values
                // it is combination of a) currect first query plus ids of result OR first query plus supplementary filters
                // b) facets[i].query  with replacement of $Xn to value
                var count_query = window.hWin.HEURIST4.util.mergeHeuristQuery(subs_value,
                                                 window.hWin.HEURIST4.util.cloneJSON(this.options.params.q).splice(1) );
                
                //var count_query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q );
                this._fillQueryWithValues( count_query, i );
//console.log( 'count_query' );                
//console.log( count_query );   

                        
                /* alas, ian want to get count on every step
                if( (!window.hWin.HEURIST4.util.isnull(field['selectedvalue'])) 
                    && (field['type']=="float" || field['type']=="integer" || field['type']=="date" || field['type']=="year")){  //run only once to get min/max values
                
                       var response = {status:window.hWin.ResponseStatus.OK, facet_index:i, data:[field['selectedvalue']]};
                       that._redrawFacets(response)
                       break;
                }
                */
                
                

                var step_level = field['selectedvalue']?field['selectedvalue'].step:0;
                var vocabulary_id = null;
                if(field['type']=='enum' && field['groupby']=='firstlevel'){
                    
                    var detailtypes = window.hWin.HEURIST4.detailtypes.typedefs;
                    vocabulary_id = detailtypes[field['id']]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']];
                    //it does work for vocabularies only!
                    if(isNaN(Number(vocabulary_id)) || !(vocabulary_id>0)){
                            vocabulary_id = null;
                            field['groupby'] = null;
                    }
                    
                }                
                
                if(field['type']=='freetext'){
                    if(field['isfacet']==this._FT_SELECT){ //backward support
                        field['isfacet'] = this._FT_LIST;
                        field['groupby'] = 'firstchar';
                    }
                    if(!field['groupby']){
                        step_level = 1;
                    }
                }else {
                    step_level = 1; //always full value for this type of facet
                }
        
//DEBUG console.log(query);

                var request = {q: query, count_query:count_query, w: 'a', a:'getfacets',
                                     facet_index: i, 
                                     field:  field['id'],
                                     type:   field['type'],
                                     step:   step_level,
                                     facet_type: field['isfacet'], //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
                                     facet_groupby: field['groupby'], //by first char for freetext, by year for dates, by level for enum
                                     vocabulary_id: vocabulary_id, //special case for firstlevel group - got it from field definitions
                                     needcount: needcount,         
                                     qname:this.options.query_name,
                                     request_id:this._request_id,
                                     //DBGSESSID:'425944380594800002;d=1,p=0,c=07', 
                                     source:this.element.attr('id') }; //, facets: facets

                if(this.options.ispreview){
                    request['limit'] = 1000;    
                }
                                     
                // try to find in cache by facet index and query string
                
                var hashQuery = window.hWin.HEURIST4.util.hashString(JSON.stringify(request.count_query));
                for (var k=0; k<this.cached_counts.length; k++){
                    if( parseInt(this.cached_counts[k].facet_index) == request.facet_index && 
                        this.cached_counts[k].count_query == hashQuery) // && this.cached_counts[k].dt == request.dt)
                    {
                        that._redrawFacets(this.cached_counts[k], false);
                        return;
                    }
                }
                

//DBG console.log(request);                
                
                window.HAPI4.RecordMgr.get_facets(request, function(response){ 
                    if(response.request_id != that._request_id){
                        //ignore results of passed sequence
                        return;
                    }
                    that._redrawFacets(response, true) 
                });            
                                
                break;
            }
        }
        
    }
    
    //
    // draw facet values
    //
    , _redrawFacets: function( response, keep_cache ) {
        
                if(!(this.options.params.viewport>0)){
                    this.options.params.viewport = 5; //default viewport
                }
        
                if(response.status == window.hWin.ResponseStatus.OK){

                    
//DEBUG if(response.dbg_query) console.log(response.dbg_query);
                    
                    if(keep_cache && response.count_query){
                        response.count_query = window.hWin.HEURIST4.util.hashString(JSON.stringify(response.count_query));
                        this.cached_counts.push(response);
                    }
                    
                    var allTerms = window.hWin.HEURIST4.terms;
                    var detailtypes = window.hWin.HEURIST4.detailtypes.typedefs;
                    
                    var facet_index = parseInt(response.facet_index); 
                    
                    var field = this.options.params.facets[facet_index];
                    
                    var j,i;
                    var $input_div = $(this.element).find("#fv_"+field['var']);
                    //$facet_values.css('background','none');

                    //create fasets container if it does not exists
                    var $facet_values = $input_div.find('.facets');
                    if( $facet_values.length < 1 ){
                        var dd = $input_div.find('.input-cell');
                        //'width':'inherit',
                        $facet_values = $('<div>').addClass('facets').css({'padding':'4px 0 10px 10px'}).appendTo( $(dd[0]) );
                    }
                    $facet_values.css('background','none');
                    
                    //add current value to history
                    if(window.hWin.HEURIST4.util.isnull(field.selectedvalue)){ //reset history
                        field.history = []; 
                    }else{
                        //replace/add for current step and remove that a bigger
                        if( window.hWin.HEURIST4.util.isArrayNotEmpty(field.history) ){
                            field.history = field.history.slice(0, field.selectedvalue.step);
                        }else{
                            field.history = [];
                            field.history.push({text:window.hWin.HR('all'), value:null});
                        }
                        field.history.push(field.selectedvalue);
                    }
                    
                    function __drawToggler($facet_values, display_mode){
                        
                        $('<div class="bor-filter-expand bor-toggler">'
                            +'<span class="bor-toggle-show-on" style="display:none"><span class="ui-icon ui-icon-circle-arrow-n"></span><span>&nbsp;show less&nbsp;</span><span class="ui-icon ui-icon-circle-arrow-n bor-toggler-second-arrow"></span></span>'
                            +'<span class="bor-toggle-show-off"><span class="ui-icon ui-icon-circle-arrow-s"></span><span>&nbsp;show more&nbsp;</span><span class="ui-icon ui-icon-circle-arrow-s bor-toggler-second-arrow"></span></span>'
                         +'</div>').click(function(event){ 
                                            var ele = $(event.target).parents('div.bor-toggler');
                                            var mode = ele.attr('data-mode');
                                            if(mode=='on'){
                                                ele.find('.bor-toggle-show-on').hide();
                                                ele.find('.bor-toggle-show-off').show();
                                                d_mode = 'none';
                                                mode = 'off';
                                            }else{
                                                ele.find('.bor-toggle-show-on').show();
                                                ele.find('.bor-toggle-show-off').hide();
                                                d_mode = display_mode;
                                                mode = 'on';
                                            }
                                            
                                            ele.parent().find('div.in-viewport').css('display',d_mode);
                                            ele.attr('data-mode', mode);
                                       })
                         .appendTo($facet_values)
                    }
                    
                    
               
                    var that = this;
                    
                    if(field['type']=='enum' && field['groupby']!='firstlevel'){
                        
                        var dtID = field['id'];  
                                              
if(!detailtypes[dtID]){
    console.log('Field '+dtID+' not found!!!!');
    console.log(field);
    //search next facet
    this._recalculateFacets( facet_index );
    return;
}
                        //enumeration
                        var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                        disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];
                        
                        var term = window.hWin.HEURIST4.ui.getChildrenTerms('enum', allTerms, disabledTerms, null ); //get entire tree
                        
                        //field.selectedvalue = {text:label, value:value, step:step};                    
                        
                        
                        function __checkTerm(term){
                                var j;
                                for (j=0; j<response.data.length; j++){
                                    if(response.data[j][0] == term.id){
                                        return {text:term.text, value:term.id, count:response.data[j][1]};
                                        //var f_link = that._createFacetLink(facet_index, {text:term.text, value:term.id, count:response.data[j][1]} );
                                        //return $("<div>").css({"display":"block","padding":"0 5px"}).append(f_link).appendTo($container);
                                    }
                                }
                                return null; //no entries
                        }
                        
                        var terms_drawn = 0; //it counts all terms as plain list 
                        
                        //{id:null, text:window.hWin.HR('all'), children:termtree}
                        //draw terms and all its parents    
                        //2 - inline list, 3 - column list
                        function __drawTerm(term, level, $container, field){
                            
                                //draw itslef - draw children
                                if(term.value){
                                    
                                        if((field['isfacet']==that._FT_COLUMN) || (field['isfacet']==that._FT_LIST)){                                    
                                            var display_mode = (field['isfacet']==that._FT_COLUMN)?'block':'inline-block';
                                            f_link = that._createFacetLink( facet_index, {text:term.text, value:term.value, count:term.count}, display_mode );
                                            
                                            terms_drawn++;  //global
                                            
                                            var ditem = $("<div>").css({'display':(terms_drawn>that.options.params.viewport?'none':display_mode),
                                                            'padding':"0 5px 0 "+(level*5)+"px"})
                                                    .addClass('facet-item')        
                                                    .append(f_link)
                                                    .appendTo($container);
                                         
                                            if(terms_drawn>that.options.params.viewport){
                                                 ditem.addClass('in-viewport');
                                            }                    
                                            
                                        }else{
                                            that._createOption( facet_index, level, {text:term.text, value:term.value, count:term.count} ).appendTo($container);
                                        }
                                }
                                if(term.children){
                                    //sort by count per level
                                    if(field['orderby']=='count'){
                                        term.children.sort(function(a, b){ 
                                            return (Number(a.count)>Number(b.count))?-1:1;
                                        });
                                    }
                                    
                                    for (var k=0; k<term.children.length; k++){
                                        __drawTerm(term.children[k], level+1, $container, field);
                                    }
                                                   
                                }
                        }//__drawTerm
                        
                        //
                        //calc number of terms with values
                        //
                        function __calcTerm(term, level, groupby){
                            
                            var res_count = 0;
                            
                            
                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(term.children)){ //is root or has children

                                var k;
                                if(term.children)
                                for (k=0; k<term.children.length; k++){
                                    var cnt = __calcTerm(term.children[k], level+1, groupby);
                                    res_count = res_count + cnt;
                                }
                                
                                //note: sometimes value may be equal to header
                                var headerData = __checkTerm(term);
                                if(headerData!=null){
                                        term.value = headerData.value;
                                        term.count = headerData.count;
                                        res_count++;
                                }else if(res_count>0){
                                    
                                    var term_value = '';
                                    if(term.termssearch){
                                        if(term.termssearch.indexOf(term.id)<0){
                                            term.termssearch.push(term.id);
                                        }
                                        term_value = term.termssearch; //.join(",");
                                    }else{
                                        term_value = term.id;
                                    }
                                    
                                    if(!window.hWin.HEURIST4.util.isempty(term_value) || 
                                        !window.hWin.HEURIST4.util.isnull(field.selectedvalue)){                               
                                    
                                        term.value = term_value;
                                        term.count = 0;
                                        res_count++;
                                    
                                    } 
                                }
                                
                            }
                            else {
                                var termData =__checkTerm(term);
                                if(termData!=null){
                                    //leave
                                    term.value = termData.value;
                                    term.count = termData.count;
                                    res_count = 1; 
                                }
                            }
                            
                            return res_count;
                        }//__calcTerms
                        
                        
                       
                        //calculate the total number of terms with value
                        var tot_cnt = __calcTerm(term, 0, field['groupby']);
                        var as_list = (field['isfacet']==this._FT_COLUMN || field['isfacet']==this._FT_LIST);    //is list
                                            //is dropdown but too many entries
//this feature is remarked on 2017-01-26 || (field['isfacet']==2 && tot_cnt > that._MIN_DROPDOWN_CONTENT)); 

                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                            var $span = $('<span>').css({'display':'inline-block','vertical-align':'middle'});
                            var f_link = this._createFacetLink(facet_index, term, 'inline-block');
                            $span.append(f_link).appendTo($facet_values);
                        }                        

                        if (field['isfacet']==this._FT_COLUMN || field['isfacet']==this._FT_LIST) {
                                __drawTerm(term, 0, $facet_values, field);
                                
                                //show viewport collapse/exand control
                                if(this.options.params.viewport<terms_drawn){
                                    var d_mode = field['isfacet']==this._FT_COLUMN ? 'block':'inline-block'; 
                                    __drawToggler($facet_values, d_mode);
                                }
                                
                        }else{
                            //as dropdown
                                var $sel = $('<select>').css({"font-size": "0.6em !important", "width":"180px"});
                                $sel.appendTo( $("<div>").css({"display":"block","padding":"0 5px"}).appendTo($facet_values) );
                                
                                that._createOption( facet_index, 0, {text:window.hWin.HR('select...'), value:null, count:0} ).appendTo($sel);
                                __drawTerm(term, 0, $sel, field);
                                
                                if(field.selectedvalue && field.selectedvalue.value){
                                    var $opt = $sel.find('option[facet_value="'+field.selectedvalue.value+'"]');
                                    $opt.attr('selected',true);
                                }
                                
                                //convert to jquery selectmenu
                                selObj = window.hWin.HEURIST4.ui.initHSelect($sel, false);
                                selObj.hSelect( "menuWidget" ).css({'font-size':'0.9em'});
                                
                                $sel.change(function(event){ that._onTermSelect(event); });
                        }
                        
                        
                        //draw
                            
                        
                       /*sort by term label
                       response.data.sort(function (a,b){ 
                            var _term_a = allTerms['termsByDomainLookup']['enum'][ a[0] ];
                            var _term_b = allTerms['termsByDomainLookup']['enum'][ b[0] ];
                            var label_a = (_term_a)?_term_a[0]:('term#'+a);
                            var label_b = (_term_b)?_term_b[0]:('term#'+b);
                            return label_a<label_b?-1:1; 
                       });*/
                        
                       /* 

                        var terms_usage = response.data; //0-id, 1-cnt
                        var terms_cnt = {};

                        for (j=0; j<response.data.length; j++){
                            //var termid = terms_usage[j].shift();
                            //terms_cnt[terms_usage[j][0]] = terms_usage[j][1];
                            
                            var cterm = response.data[j];
                            var _term = allTerms['termsByDomainLookup']['enum'][ cterm[0] ];
                            var label = (_term)?_term[0]:('term#'+cterm[0]);
                            
                            //$("<div>").css({"display":"inline-block","min-width":"90px","padding":"0 3px"})
                            //        .html( label + ' ('+ cterm[1] +') ' ).appendTo($facet_values);
                            
                            var f_link = this._createFacetLink(facet_index, {text:label, value:cterm[2], count:cterm[1]});
                                    
                            $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            if(i>50){
                                 $("<div>").css({"display":"inline-block","padding":"0 3px"}).html('more '+(response.data.length-i)+' results').appendTo($facet_values);
                                 break;       
                            }
                        }
                        */
                        
                        
                        
                        /*
                        //create links for child terms                         
                        for (i=0;i<term.children.length;i++){
                            var cterm = term.children[i];

                            //calc usage
                            var cnt = 0;
                            for (j=0; j<cterm.termssearch.length; j++){
                                var usg = parseInt(terms_cnt[cterm.termssearch[j]]);
                                if(usg>0){
                                    cnt = cnt + usg;
                                }
                            }
                            if(cnt>0){
                                var f_link = this._createTermLink(facet_index, {id:cterm.id, text:cterm.text, query:cterm.termssearch.join(","), count:cnt});
                                $("<div>").css({"display":"inline-block","min-width":"90px","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            }
                        }
                        */
                        
                        
                    }else 
                    if(field['type']=="rectype"){  //@todo

                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            if(facet_index>=0){
                                var rtID = cterm[0];
                                var f_link = this._createFacetLink(facet_index, 
                                    {text:window.hWin.HEURIST4.rectypes.names[rtID], query:rtID, count:cterm[1]}, 'inline-block');
                                $("<div>").css({"display":"inline-block","padding":"0 3px"})
                                  .addClass('facet-item')
                                  .append(f_link).appendTo($facet_values);
                            }
                        }

                    }else 
                    if ((field['type']=="float" || field['type']=="integer" 
                        || field['type']=="date" || field['type']=="year") && field['isfacet']==this._FT_SELECT)
                    {  //add slider
                    
                        $facet_values.parent().css({'display':'block','padding-left':'1em','padding-right':'2em'});
                        //'width':'90%', $facet_values.css({'width':'100%','padding':'1em'});

                        var cterm = response.data[0];
                        
                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                                    var f_link = this._createFacetLink(facet_index, {test:'',value:null,step:0}, 'inline-block');
                                    $('<span>').css({'display':'inline-block','vertical-align':'middle','margin-left':'-15px'})
                                        .append(f_link).appendTo($facet_values);
                        }
                        var sl_count = (cterm && cterm.length==3)?cterm[2]:0;
                        
                        if(field.selectedvalue){
                                if($.isNumeric(field.selectedvalue.value) ||  field.selectedvalue.value.indexOf('<>')<0  ){
                                    cterm = [field.selectedvalue.value, field.selectedvalue.value];
                                }else{
                                    cterm = field.selectedvalue.value.split('<>');
                                }
                        }
                        
                        var mmin  = cterm[0];
                        var mmax  = cterm[1];
                        var daymsec = 86400000; //24*60*60*1000;   1day
                        
                        if(!(window.hWin.HEURIST4.util.isempty(mmin) || window.hWin.HEURIST4.util.isempty(mmax))){
                            
                            if(field['type']=="date"){
                                if(mmin.indexOf("-00-00")>0){
                                    mmin = mmin.replace("-00-00","-01-01");
                                }
                                if(mmax.indexOf("-00-00")>0){
                                    mmax = mmax.replace("-00-00","-01-01");
                                }
                                
                                mmin = mmin.replace(' ','T');                                                                     
                                mmax = mmax.replace(' ','T');
                                mmin = Date.parse(mmin); 
                                mmax = Date.parse(mmax); 
                                //mmin = moment(mmin).valueOf();//unix offset  
                                //mmax = moment(mmax).valueOf();
                                //find date interval for proper formating
                                var delta = mmax-mmin;
                                var date_format = "dd MMM yyyy HH:mm"; //"YYYY-MM-DD hh:mm:ss";
                                
                                if(delta>3*365*daymsec){ //3 years
                                    date_format = "yyyy";
                                }else if(delta>365*daymsec){ //6 month
                                    date_format = "MMM yyyy";
                                }else if(delta>daymsec){ //1 day
                                    date_format = "dd MMM yyyy";
                                }
                                
                            }else{
                                mmin = Number(mmin);
                                mmax = Number(mmax);
                            }
                            
                            var delta = window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)?(mmax-mmin)/2:0;
                            
                            if(field['type']=="date" && mmax-mmin<daymsec){
                                delta = daymsec;
                            }else if(mmin==mmax){ //years
                                delta = 10;
                            }
                            
                        /*if(mmin==mmax){
                            $("<span>").text(cterm[0]).css({'font-style':'italic', 'padding-left':'10px'}).appendTo($facet_values);
                        }else */
                        if(isNaN(mmin) || isNaN(mmax)){
                            
                            var s = "Server returns invalid "+field['type'];
                            if(isNaN(mmin)&&isNaN(mmax)){
                                s = s + " min and max values: "+cterm[0]+" and "+cterm[1];
                            }else{
                                s = s + " " +(isNaN(mmin)?"min":"max")+" value: "+(isNaN(mmin)?cterm[0]:cterm[1]);
                            }
                           
                           $("<span>").text(s)
                            .css({'font-style':'italic', 'padding-left':'10px'})
                            .appendTo($facet_values); 
                            
                        }else{
                            
                            if(isNaN(field.mmin0) || isNaN(field.mmax0)){
                                field.mmin0 = mmin;
                                field.mmax0 = mmax;
                            }
                            
                            
                            function __roundNumericLabel(val) {
                                var prefix = '';
                                if(val>=10e21){
                                    prefix = 'Z'; //Sextillion
                                    val = val/1e21;
                                }else if(val>=10e18){
                                    prefix = 'E'; //Quintillion
                                    val = val/1e18;
                                }else if(val>=10e15){
                                    prefix = 'P'; //Quadrillion
                                    val = val/1e15;
                                }else if(val>=10e12){
                                    prefix = 'T'; //Trillion  
                                    val = val/1e12;
                                }else if(val>=10e9){
                                    prefix = 'G'; //Billion 
                                    val = val/1e9;
                                }else if(val>=10e6){
                                    prefix = 'M'; //Million
                                    val = val/1e6;
                                }else if(val>=10e3){
                                    prefix = 'k'; //Thousand
                                    val = val/1e3;
                                }
                                if(prefix!=''){
                                    return Math.round(val)+prefix;    
                                }else{
                                    return val;
                                }
                                
                            }
                            
                            function __updateSliderLabel() {
                                      
                                if(arguments && arguments.length>1){
                                    var min, max, cnt;      
                                    if(isNaN(arguments[0])){
                                        min = arguments[1].values[ 0 ];
                                        max = arguments[1].values[ 1 ];
                                        cnt = 0;
                                    }else{
                                        min = arguments[0];
                                        max = arguments[1];
                                        cnt = arguments[2];
                                    }
                                    if(field['type']=="date"){
                                        try{
                                           var tDate = new TDate((new Date(min)).toISOString());
                                           min = tDate.toString(date_format);
                                           //min = (new Date(min)).format(date_format);
                                           //min = moment(min).format(date_format);
                                        }catch(err) {
                                           min = ""; 
                                        }
                                        try{
                                           var tDate = new TDate((new Date(max)).toISOString());
                                           max = tDate.toString(date_format);
                                            //max = (new Date(max)).format(date_format);
                                            //max = moment(max).format(date_format);
                                        }catch(err) {
                                            max = ""; 
                                        }
                                    }else{
                                        min = __roundNumericLabel(min);
                                        max = __roundNumericLabel(max);
                                    }
                                    $( "#facet_range"+facet_index )
                                        .text( min + " - " + max + ((cnt>0)?" ("+cnt+")":"") );
                                }
                            }
                            
                            //preapre value to be sent to server and start search
                            function __onSlideStop( event, ui){

                                var min = ui.values[ 0 ];
                                var max = ui.values[ 1 ];

                                var field = that.options.params.facets[facet_index];
                                
                                if(min<field.mmin0) {
                                    min = field.mmin0;   
                                    slider.slider( "values", 0, min);
                                }
                                if(max>field.mmax0) {
                                    max = field.mmax0;
                                    slider.slider( "values", 1, max);
                                }

                                if(field['type']=="date"){
                                    min = (new Date(min)).toISOString(); 
                                    max = (new Date(max)).toISOString(); 
                                }

                                var value = (min==max)?min :min + '<>' + max; //search in between
                                
                                if(window.hWin.HEURIST4.util.isempty(value)){
                                    value = '';
                                    field.selectedvalue = null;
                                }else{
                                    field.selectedvalue = {text:'???', value:value, step:1};                    
                                }
                                
                                that.doSearch();
                            }
                        
                            var flbl = $("<div>",{id:"facet_range"+facet_index})
                                        .css({'padding-bottom':'1em'})
                                        .appendTo($facet_values);
                            var slider = $("<div>",{facet_index:facet_index})
                                .slider({
                                      range: true,
                                      min: mmin - delta,
                                      max: mmax + delta,
                                      values: [ mmin, mmax ],
                                      slide: __updateSliderLabel,
                                      stop: __onSlideStop,
                                      create: function(){
                                          $(this).find('.ui-slider-handle').css({width:'4px',background:'black'});
                                      }
                                    })
                                .css('height','0.4em')
                            .appendTo($facet_values);

                            //show initial values
                            __updateSliderLabel(mmin, mmax, sl_count);
                            
                        }
                        }
                    }
                    else if(field['type']=='enum' && field['groupby']=='firstlevel' 
                                && !window.hWin.HEURIST4.util.isnull(field['selectedvalue'])){
                        
                        var cterm = field.selectedvalue;
                        var f_link = this._createFacetLink(facet_index, {text:cterm.text, value:cterm.value, count:'reset'}, 'block');
                        
                        var ditem = $("<div>").css({'display':'block',"padding":"0 3px"})
                                                .addClass('facet-item')
                                                .append(f_link).appendTo($facet_values);
                    }
                    else{   //freetext  or enum groupby firstlevel
                        
                        //$facet_values.css('padding-left','5px');
                        
                        //draw history
                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){

                            var k, len = field.history.length;
                            for (k=0;k<1;k++){
                                var cvalue = field.history[k];
                                
                                var $span = $('<span>').css('display','block'); //was inline
                                if(k==len-1){ //last one
                                    $span.text(cvalue.text).appendTo($facet_values);
                                    //$span.append($('<br>'));
                                }else{
                                    var f_link = this._createFacetLink(facet_index, cvalue, 'inline-block');
                                    $span.css({'display':'inline-block','vertical-align':'middle'}).append(f_link).appendTo($facet_values);
                                    //$span.append($('<span class="ui-icon ui-icon-carat-1-e" />').css({'display':'inline-block','height':'13px'}));
                                }
                            }
                        }
                        
                        //sort by count
                        if(field['orderby']=='count'){
                            response.data.sort(function(a, b){ return (Number(a[1])>Number(b[1]))?-1:1;});
                        }
                        
                        var display_mode = (field['isfacet']==this._FT_LIST || (field['groupby']=='firstchar' && step_level==0))
                                                        ?'inline-block':'block';
                        
                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];
                            
                            //for enum get term label w/o code
                            if(field['type']=='enum' && cterm[0]>0){
                                cterm[0] = window.hWin.HEURIST4.ui.getTermValue(cterm[0], false);    
                            }

                            var f_link = this._createFacetLink(facet_index, {text:cterm[0], value:cterm[2], count:cterm[1]}, display_mode);
                            
                            //@todo draw first level for groupby firs tchar always inline
                            var step_level = (field['groupby']=='firstchar' && field['selectedvalue'])
                                                ?field['selectedvalue'].step:0;
                                                                                                      
                            var ditem = $("<div>").css({'display':(i>this.options.params.viewport-1?'none':display_mode),"padding":"0 3px"})
                                                .addClass('facet-item')
                                                .append(f_link).appendTo($facet_values);
                                                
                            if(i>this.options.params.viewport-1){
                                 ditem.addClass('in-viewport');
                            }                    
                            if(i>250){ 
                                 $("<div>").css({"display":"none","padding":"0 3px"}).html('still more...( '+(response.data.length-i)+' results )').appendTo($facet_values);
                                 break;       
                            }
                        }

                        //show viewport collapse/exand control
                        if(this.options.params.viewport<response.data.length){
                            var diff = response.data.length-this.options.params.viewport;   
                            __drawToggler($facet_values, display_mode);
                        }
                    }

                    if($facet_values.is(':empty')){
                        $("<span>").text(window.hWin.HR('no values')).css({'font-style':'italic', 'padding-left':'10px'}).appendTo($facet_values);
                    }

                    //search next facet
                    this._recalculateFacets( facet_index );

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
                
    }            

    ,_createOption : function(facet_index, indent, cterm){
        
        //var step = cterm.step;
        var hist = this.options.params.facets[facet_index].history;
        if(!hist) hist = [];
        var step = hist.length+1;
        
        var lbl = cterm.text; //(new Array( indent + 1 ).join( " . " )) + 
        if(cterm.count>0){
            lbl =  lbl + " ("+cterm.count+")";
        } 

        var f_link = $("<option>",{facet_index:facet_index, facet_value:cterm.value, facet_label:lbl, step:step})
                            //.css('padding-left', ((indent-1)*2)+'em' )
                            .text(lbl).addClass("facet_link")
        f_link.attr('depth',indent-1);
        
        return f_link;
    }
    
    , _onTermSelect:function(event){
        
                var link = $(event.target).find( "option:selected" );
                var facet_index = Number(link.attr('facet_index'));
                var value = link.attr('facet_value');                  
                var label = link.attr('facet_label');                  
                var step = link.attr('step');
                
                var field = this.options.params.facets[facet_index];
                
                var prevvalue = field.selectedvalue?field.selectedvalue.value:null;
                
                if(window.hWin.HEURIST4.util.isempty(value)){
                    value = '';
                    field.selectedvalue = null;
                }else{
                    field.selectedvalue = {text:label, value:value, step:step};                    
                }
                
                if(prevvalue!=value){
                    this.doSearch();
                }
    }

    // cterm - {text, value, count}
    ,_createFacetLink : function(facet_index, cterm, display_mode){

        var field = this.options.params.facets[facet_index];
        //var step = cterm.step;
        var hist = field.history;
        if(!hist) hist = [];
        var step = hist.length+1;
        var iscurrent = false;
        
        var currval = field.selectedvalue?field.selectedvalue.value:null;
        
        var f_link = $("<a>",{href:'#', facet_index:facet_index, 
                        facet_value: (cterm.count=='reset')?'':cterm.value, 
                        facet_label: cterm.text, 
                        step:step})
                    .addClass("facet_link")
        
        //----
        var f_link_content;
        
        if(window.hWin.HEURIST4.util.isempty(cterm.value)){
            f_link_content = $("<span>").addClass("ui-icon ui-icon-arrowreturnthick-1-w")
                .css({'font-size':'0.9em','height':'10px','margin-left':'-15px'});    
        }else{
            f_link_content = $("<span>").text(cterm.text);
            
            if(display_mode=='block'){         
            
                var top_parent = this.element.parents('.mceNonEditable');
                if(top_parent.length>0){ //this is web publication 
                    f_link_content.css('width',top_parent.width()*0.6).addClass('truncate');
                }else{
                    f_link_content.css('width','80%').addClass('truncate');    //was this.facets_list_container.width()*0.6
                }
            
            
                f_link_content.attr('title', cterm.text);
            }
            
            if(!window.hWin.HEURIST4.util.isempty(currval)){
                iscurrent = (currval == cterm.value);
                if(iscurrent) 
                    //do not highlight if initals selected
                    //|| (currval.length==2 &&  currval.substr(1,1)=='%' && currval.substr(0,1)==cterm.value.substr(0,1)) )
                {
                     
                     f_link_content.css({ 'font-weight': 'bold', 'font-size':'1.1em', 'font-style':'normal' });   
                }
            
            }
        }
        f_link_content.appendTo(f_link);
        
        //---
        if(cterm.count=='reset' || cterm.count>0){
            //.css('float','right')
            var dcount = $('<span>').addClass('badge').text(cterm.count=='reset'?'X':cterm.count);
            if(display_mode=='inline-block'){
                 dcount.appendTo(f_link).appendTo(f_link_content);
            }else{
                 dcount.appendTo(f_link).css({float:'right'});
            }
        }
        
        if(!iscurrent || cterm.count=='reset'){ 

        var that = this;

        this._on( f_link, {
            click: function(event) { 

                var link = $(event.target).parent();
                var facet_index = Number(link.attr('facet_index'));
                var value = link.attr('facet_value');                  
                var label = link.attr('facet_label');                  
                var step = link.attr('step');
                
                var field = this.options.params.facets[facet_index];
                
                if(window.hWin.HEURIST4.util.isempty(value)){
                    value = '';
                    field.selectedvalue = null;
                }else{
                    field.selectedvalue = {text:label, value:value, step:step};                    
                }
                
                
                // assign value to edit_inpout - to remove
                //var varid = field['var'];
                //$( this._input_fields[ '$X'+varid ] ).editing_input('setValue', value);
                
                // make link in bold
                //$("#fv_"+varid).find('.facets div a').css('font-weight','normal');
                //$("#fv_"+varid).find('.facet_link').css({'font-weight':'normal', 'backgground-color':'none'}); 
                //link.css({'font-weight':'bold', 'backgground-color':'gray'});
                
                // this._refresh();

                that.doSearch();
                
                return false;
            }
        });

        }
        return f_link;
    },
    
    //
    // instead of list of links it is possible to allow enter search value directly into input field
    //
    _createInputField :function(field_index){

               var field = this.options.params.facets[field_index];
                 
               var ed_options = {
                            varid: field['var'],  //content_id+"_"+
                            recID: -1,
                            rectypeID: field['rtid'],
                            dtID: field['id'],
                            rectypes: window.hWin.HEURIST4.rectypes,
                            values: [''],
                            readonly: false,
                            title:  "<span style='font-weight:bold'>" + field['title'] + "</span>",
                            showclear_button: false,
                            suppress_prompts: true,  //supress help, error and required features
                            suppress_repeat: true,
                            detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
                    };
                    
               if(isNaN(Number(field['id']))){ //field id not defined
                   ed_options['dtFields'] = {
                       dty_Type: field['type'],
                       rst_RequirementType: 'optional',
                       rst_MaxValues: 1,
                       rst_DisplayWidth: 0
                   };
               }
   
                //rst_DefaultValue
                 
                var inpt = $("<div>",{id: "fv_"+field['var'] }).editing_input(   //this is our widget for edit given fieldtype value
                        ed_options
                    );

                inpt.appendTo($fieldset);
                that._input_fields['$X'+field['var']] = inpt;
        
        
    }




});