/**
*  Apply faceted search
* TODO: Check that this is what it does and that it is not jsut an old version
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

/*
main methods
    _initFacetQueries - creates facet searches (counts and values for particular facets) and main query
    _fillQueryWithValues - fille queries with values
    doSearch - performs main search
    _recalculateFacets - search for facet values as soon as main search finished
    _redrawFacets - called from _recalculateFacets then call _recalculateFacets for next facet

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
//the simple (no level) facet
[
id: 1
code: 10:1 
title: "Family Name"
type:  "freetext"
levels: []
search: [t:10 f:1] 
orderby: count|null
groupby
],
//multi field facet ???
[
id: [1,2]
code: [10:1,10:2]
title: "Family Name"
type:  "freetext"
levels: []
search: [t:10 f:1] 
orderby: count|null
groupby
],
]


--------------
NOTE - to make search for facet value faster we may try to omit current search in query and search for entire database

---------------
TOP PARAMETERS for entire search
facet search general parameters are the same to saved search plus several special

domain
rules
rulesonly
ui_title - title in user interface
ui_viewmode - result list viewmode
title_hierarchy - show hierarchy in facet header
sup_filter - suplementary (preliminary) filter that is set in design time
add_filter - additional filter that can be set in run time (via input field - search everything)
add_filter_original - original search string for add_filter if it is json  - NO USED
spatial_filter  - spatial filter (optional)
search_on_reset - search for empty form (on reset and on init)

ui_prelim_filter_toggle   - allow toggle on/off "sup_filter"
ui_prelim_filter_toggle_mode - direct or reverse mode (0|1)
ui_prelim_filter_toggle_label - label on UI

ui_spatial_filter - show spatial filter
ui_spatial_filter_label
ui_spatial_filter_initial - initial spatial search
ui_spatial_filter_init - apply spatial search at once

ui_additional_filter - show search everything input (for add_filter)
ui_additional_filter_label

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
    _FT_LIST: 2,    //list view mode  
    _FT_COLUMN: 3,  //wrapped list view mode

    
    // default options
    options: {
        is_h6style: true,
        params: {},
        ispreview: false,
        showclosebutton: true,
        showresetbutton: true,
        search_realm: null,
        preliminary_filter:null,
        svs_ID: null,
        onclose: null,// callback
        is_publication: false
    },
    

    cached_counts:[], //stored results of get_facets by stringified query index
    _input_fields:{},
    _request_id: 0, //keep unique id to avoid redraw facets for old requests
    _first_query:[], //query for all empty values
    _isInited: true,
    _current_query: null,
    _hasLocation: null, //has primary rt geo fields or linked location - for spatial search
    
    _currentRecordset:null,
    
    _use_sup_filter:null, 
    
    _use_multifield: false, //HIE - search for several fields per facet
    
    ui_spatial_filter_image:null,
    
    _terminateFacetCalculation: false, //stop flag

    _date_range_dialog: null,
    _date_range_dialog_instance: null,
    
    // the widget's constructor
    _create: function() {
        
        if(this.element.parents('.mceNonEditable').length>0){
            this.options.is_publication = true;
        }
        
        this._use_multifield = window.hWin.HAPI4.database=='johns_hamburg' &&
                window.hWin.HEURIST4.util.findArrayIndex(this.options.svs_ID,[20,21,22,23,24,28,30,31])>=0;

        var that = this;
        
        if(!$.isFunction($('body')['editing_input'])){
            $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/editing/editing_input.js', function() {
                that._create();
            });
            return;
        }
        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this.div_header = $( "<div>" ).css({height: 'auto',
            position: 'absolute', left: 0, right: 0}).appendTo( this.element );
        
        if(!this.options.ispreview){     
        
            if(this.options.is_h6style && !this.options.is_publication){
                
                this.div_title = $('<div class="ui-heurist-header truncate" '
                    +'style="position:relative;width:180px;padding:10px;font-size: 0.9em;">')
                    .appendTo( this.div_header );
                
            }else{
                              //padding-top:1.4em;
                this.div_title = $('<div>')
                .css({padding:'0.4em 0.2em 0.2em 1em','font-size':'1.4em','font-weight':'bold','max-width':'90%'})
    //style='text-align:left;margin:4px 0 0 0!important;padding-left:1em;width:auto, max-width:90%'></h3")
                        .addClass('truncate svs-header').appendTo( this.div_header );
            }
        }
        
        this.refreshSubsetSign();
        
        //"font-size":"0.7em",
        this.div_toolbar = $( "<div>" ).css({'font-size': '0.9em',"float":"right",
                    "padding-top":"0px","padding-right":"18px"})
                .appendTo( this.div_header );

                
        this.btn_submit = $( "<button>", { text: window.hWin.HR("Submit") })
        .appendTo( this.div_toolbar )
        .button();
        
        this.btn_reset = $( "<button>", {title:window.hWin.HR("Clear all fields / Reset all the filters to their initial states") })
        .appendTo( this.div_toolbar )
        .button({label: window.hWin.HR("Reset all"), icon: 'ui-icon-arrowreturnthick-1-w', iconPosition:'end' }).hide();
        
        this.btn_save = $( "<button>", { text: window.hWin.HR("Save state") })
        .appendTo( this.div_toolbar )
        .button().hide(); //@todo

        
        var lbl = this.options.params.ui_exit_button_label
                        ?this.options.params.ui_exit_button_label
                        :window.hWin.HR(this.options.is_h6style?'Close':'Show all available searches');
        
        this.btn_close = $( "<button>", { 
                    title:window.hWin.HR("Close this facet search and return to the list of saved searches") })
        .css({"z-index":"100"})
        .appendTo( this.div_toolbar )
        .button({icon: "ui-icon-close", iconPosition:'end', label:lbl}); //was Close

        this.btn_close.find('.ui-icon-close').css({right: 0}); //'font-size': '1.3em', 
        
        if(this.options.params.ui_exit_button===false) this.options.showclosebutton = false;
        
        this.btn_terminate = $( "<button>").appendTo( this.div_toolbar )
        .button({icon: "ui-icon-cancel", iconPosition:'end', label:'Interrupt'}).hide();
        
        
        this._on( this.btn_submit, { click: "doSearch" });
        this._on( this.btn_reset, { click: "doResetAll" });
        this._on( this.btn_save, { click: "doSaveSearch" });
        this._on( this.btn_close, { click: "doClose" });
        this._on( this.btn_terminate, { click: function(){ this._terminateFacetCalculation = true; }});

        
        this.facets_list_container = $( "<div>" )
        .css({"top":((this.div_title)?'6em':'2em'),"bottom":0,"position":"absolute"}) //was top 3.6
        .appendTo( this.element );
        
        if(this.options.is_h6style && !this.options.is_publication){
            this.facets_list_container.css({left:0,right:0,'font-size':'0.9em'});    
        }else{
            this.facets_list_container.css({left:0,right:0});     //{left:'1em',right:'0.5em'}
        }

        this.facets_list = $( "<div>" )
        .addClass('svs-list-container')
        .css({"overflow-y":"auto","overflow-x":"hidden","height":"100%"}) //"font-size":"0.9em",
        .appendTo( this.facets_list_container );

        var current_query_request_id;
        
        //was this.document
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            +' '+window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE+' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
        
        function(e, data) {
            
            if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){

                var w = that.element.width();
                
                that.element.find('div.facet-item > a > span.truncate').width(w-100); //was 80
                
                if(that.btn_reset) that.btn_reset.button({showLabel:(w>250)});
                that.btn_close.button({showLabel:(w>250)});
                  
            }else {

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
                          that.refreshSubsetSign();
                          
                          if($.isFunction(that.options.params.callback_on_search_finish) && recset){
                              that.options.params.callback_on_search_finish.call(this, recset.count_total());
                          }
                    }         
                }else if(e.type == window.hWin.HAPI4.Event.ON_CUSTOM_EVENT && data){
                    
                    if(data.userWorkSetUpdated){
                        that.refreshSubsetSign();
                    }
                    if(data.closeFacetedSearch){
                        that._trigger( "onclose" );    
                    }
                    
                }
            }
        });
        
        //apply spacial filter at once
        if(that.options.params.ui_spatial_filter_initial && that.options.params.ui_spatial_filter_init){
               that.options.params.spatial_filter = that.options.params.ui_spatial_filter_initial;
               //__setUiSpatialFilter( that.options.params.ui_spatial_filter_initial, null);
        }
        
        setTimeout(function(){that._adjustSearchDivTop();},500);
        
        //this._refresh();
        this.doReset();
    }, //end _create

    //
    //
    //
    _adjustSearchDivTop: function(){
        if(this.facets_list_container && this.div_header){
            var iAdd = 4;
            if(this.options.params.ui_spatial_filter){
                iAdd = -25;    
            }
            this.facets_list_container.css({top: this.div_header.height()+iAdd});
        }
    },
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        if(key=='add_filter'){
            this.options.params.add_filter = value;
        }else if(key=='spatial_filter'){
            this.options.params.spatial_filter = value;
        }else if(key=='add_filter_original'){
            this.options.params.add_filter_original = value;
        }
    },
    
    _setOptions: function( options ) {
        this._superApply( arguments );
        this._hasLocation = null;
        if(window.hWin.HEURIST4.util.isnull(options['add_filter']) && window.hWin.HEURIST4.util.isnull(options['spatial_filter'])){
            this.cached_counts = [];
            //this._refresh();
            this.doReset();
        }
    },

    _refreshTitle: function(){    
        var new_title = '';
        if(this.div_title) {
            
            if(this.options.params.ui_title){ //from settings
                new_title = this.options.params.ui_title;
            }else{
                var svsID = this.options.query_name;
                if(svsID > 0){
                    
                    if (window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                                window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID])
                    {
                         new_title = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID][0];//_NAME];                
                    }else if(window.hWin.HAPI4.has_access()){
                        var that = this;
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
            else if(this.options.is_h6style && !this.options.is_publication){
                new_title = '<span style="font-size:smaller">'
                +'<span class="ui-icon ui-icon-filter" style="width:16px;height:16px;background-size:contain;"/>filter: </span>'
                +new_title;
            }
            

            this.div_title.html(new_title);
        }
    },
    
    /* 
    * private function    - NOT USED
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
        this._refreshTitle();
        this._refreshButtons();
                            
        
        this.doRender();
        //ART2907 this.doSearch();
    },
    
    _refreshButtons: function(){
        
        if(this.options.ispreview){
            this.btn_save.hide(); 
            this.btn_close.hide(); 
        }else{
            
             var facets = this.options.params.facets;
             var hasHistory = false, facet_index, len = facets?facets.length:0;
             for (facet_index=0;facet_index<len;facet_index++){
                  if( !window.hWin.HEURIST4.util.isempty(facets[facet_index].history) ){
                      hasHistory = true;
                      break;
                  }
             }
            
            
            if(hasHistory && !this.options.params.ui_spatial_filter) {
                //if(this.div_title) this.div_title.css('width','45%');
                if(this.options.showresetbutton && this.btn_reset){
                    this.btn_reset.show()   
                }
                //this.btn_save.show();  //@todo
            }else{
                //if(this.div_title) this.div_title.css({'width':'auto', 'max-width':'90%'});
    
                if(this.btn_reset) this.btn_reset.hide()   
                this.btn_save.hide(); 
            }
            
            if(this.options.showclosebutton){
                this.btn_close.show(); 
            }else{
                this.btn_close.hide(); 
            }
        }
        
    },
    
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(this.document).off( window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            +' '+window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
            +' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT );

        // remove generated elements
        if(this.div_title) this.div_title.remove();
        this.cached_counts = [];
        this.btn_submit.remove();
        this.btn_close.remove();
        this.btn_save.remove();
        if(this.btn_reset) this.btn_reset.remove();
        this.div_toolbar.remove();

        this.facets_list.remove();
        this.facets_list_container.remove();
    }

    //Methods specific for this widget---------------------------------

    //
    // 1. create lists of queries to search facet values
    // 2. create main JSON query
    //
    ,_initFacetQueries: function(){

        var that = this, mainquery = [];

        $.each(this.options.params.facets, function(index, field){

            //value is defined - it will be used to create query
            if( !window.hWin.HEURIST4.util.isnull(field['var']) && field['code']){
                //create new query and add new parameter
                var code = field['code'];

                code = code.split(':');

                var dtid = code[code.length-1];
                var linktype = dtid.substr(0,2);
                if(linktype=='lt' || linktype=='lf' || linktype=='rt' || linktype=='rf'){
                    //unconstrained link
                    code.push('0');         //!!!!!!!!
                    code.push('title');
                }

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

                            if(code[idx]>0){ //if 0 - unconstrained
                                qp['t'] = code[idx];
                                res.push(qp);
                            }

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

                    //first level can be multi rectype
                    //add recordtype
                    if(rtid>0 ||  rtid.indexOf(',')>0){  //AA!!  ||  rtid.indexOf(',')>0
                        curr_level = __checkEntry(curr_level,"t",rtid);
                    }
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

                        var rtid_linked = code[j+2];  //linked record type, if null or 0 - unconstrained
                        key  = slink+rtid_linked+":"+dtid.substr(2); //rtid need to distinguish links/relations for various recordtypes
                        val = [];
                    }else{
                        //multifield search for datetime
                        if(dtid==9 && that._use_multifield){
                            dtid = '9,10,11';
                        }else if(dtid==1  && that._use_multifield){ //for name
                            dtid = '1,18,231,304';
                        }

                        key = "f:"+dtid
                        val = "$X"+field['var']; 
                    }
                    curr_level = __checkEntry(curr_level, key, val);


                    j=j+2;
                }//while               

            }
        });

        this.options.params['q'] = mainquery;
    }

    ,doResetAll: function(){
        //this.options.params.spatial_filter = null;
        this.options.params.add_filter = null;
        this.options.params.add_filter_original = null;
        this.doReset();
    }
    //
    // reset current search 
    // recreate facet elements/ or form inputs
    //
    ,doReset: function(){

        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ 
            {reset:true, 
             search_realm:this.options.search_realm, 
             primary_rt:9999, 
             ispreview: this.options.ispreview
            } ]);  //global app event to clear views
        
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
        
        this._current_query = window.hWin.HEURIST4.util.mergeHeuristQuery(
                            (this._use_sup_filter)?this.options.params.sup_filter:'', 
                            //this.options.params.add_filter,
                            this._prepareSpatial(this.options.params.spatial_filter));
        
        //this._current_query = null;
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

        if(this.btn_reset) this.btn_reset.hide()   
        this.btn_save.hide(); 
       
       
       var that = this;
       that._input_fields = {};
       
       //this._use_sup_filter = true;
       //add toggle for supplementary filter
       if(this.options.params.sup_filter){
           if(this.options.params.ui_prelim_filter_toggle){
               
               if(this._use_sup_filter==null){
                    this._use_sup_filter = (that.options.params.ui_prelim_filter_toggle_mode==0);
               }
               
               var lbl = that.options.params.ui_prelim_filter_toggle_label
                            ?that.options.params.ui_prelim_filter_toggle_label
                            :window.hWin.HR('Apply preliminary filter');
               
               var ele = $("<div>").html(
                            '<h4 style="margin:0;"><div class="input-cell" style="display:block;">'
                                +'<input type="checkbox" '+(((this.options.params.ui_prelim_filter_toggle_mode==0 && this._use_sup_filter)
                                || (this.options.params.ui_prelim_filter_toggle_mode!=0 && !this._use_sup_filter))
                                ?'checked':'')+'/>'                            
                                +lbl
                            +'</h4>').css({'border-bottom': '1px solid lightgray'}).appendTo($fieldset);
                            
               this._on( ele.find('input[type="checkbox"]'), { change:                         
               function(event){
                   
                   if(that.options.params.ui_prelim_filter_toggle_mode==0){
                        that._use_sup_filter = $(event.target).is(':checked');    
                   }else{
                        that._use_sup_filter = !$(event.target).is(':checked');                   
                   }
                   
                   that.doSearch();
               }});             
           }else{
               this._use_sup_filter = true;
           }
       }
       
       if(this.options.params.ui_spatial_filter){
           
           var lbl = that.options.params.ui_spatial_filter_label
                        ?that.options.params.ui_spatial_filter_label
                        :window.hWin.HR('Map Search');
                        
           var ele = $("<div>").html(
           '<div class="header" title="" style="vertical-align: top; display: block; width: 100%; padding: 5px;">'
                +'<h4 style="display:inline-block;margin:0;">'+lbl+'</h4></div>'
                +'<div style="padding-left:21px;display:inline-block;width:100%" class="spatial_filter">'  //padding:5px 0 20px 21px;
                    +'<div style="float:left;max-height:120px;">' //class="input-div" 
                    +'<img class="map_snapshot" style="display:none;width:150"/>'
                    +'</div>'
                    +'<div style="display:inline-block;">'
                        +'<button title="Click this button to set and apply spatial search limits" class="ui-button ui-corner-all ui-widget define_spatial" style="height:20px">'
                            +'<span class="ui-button-icon ui-icon ui-icon-globe"></span>'
                            +'&nbsp;<span class="ui-button-text" style="font-size:11px">Define</span></button>'
                        +'<button title="Click this button to reset spatial search limits" class="smallbutton ui-button ui-corner-all ui-widget reset_spatial  ui-button-icon-only" style="display:none;">' // float: right;margin-right: 40px;
                            +'<span class="ui-button-icon ui-icon ui-icon-arrowreturnthick-1-w"></span>'
                            +'<span class="ui-button-text"> </span></button>'                    
                    +'</div>'    
                    +'</div>').css({'border-bottom': '1px solid lightgray','margin-right':'10px',
                                    'margin-bottom':'25px', 'padding-bottom':'5px'}).appendTo($fieldset);
                    
           var btn_reset2 = $( "<button>", {title:window.hWin.HR("Clear all fields / Reset all the filters to their initial states") })
           .css({float:'right','margin-top':'10px','margin-right':'2px'})
           .appendTo( ele )
           .button({label: window.hWin.HR("Reset all"), icon: 'ui-icon-arrowreturnthick-1-w', iconPosition:'end' });
           this._on( btn_reset2, { click: "doResetAll" });

           if(this.btn_reset){
               this.btn_reset.hide();
               //this.btn_reset = null;
           }

           this._on( ele.find('button.reset_spatial'), { click:                         
           function(event){
               __setUiSpatialFilter(null, null);
                //ele.find('input').val('');  
                /*that.element.find('.map_snapshot').attr('src',null).hide();
                that.element.find('.define_spatial').show();
                that.element.find('.reset_spatial').hide();
                that.options.params.spatial_filter = null;
                that.ui_spatial_filter_image = null;
                */
                that.doSearch();
           }});
           
           function __setUiSpatialFilter(wkt, imagedata){

                if( wkt ){
                    if(imagedata){
                        that.ui_spatial_filter_image = imagedata;
                        //that.element.find('.spatial_filter').css({'min-height':'120px'});
                        that.element.find('.map_snapshot').attr('src',imagedata).show();
                        that.element.find('.define_spatial').hide();
                    }else{
                        that.ui_spatial_filter_image = null;
                        //that.element.find('.spatial_filter').css({'min-height':'20px'});
                        that.element.find('.map_snapshot').attr('src',null).hide();
                        that.element.find('.define_spatial').show();
                    }
                    that.element.find('.reset_spatial').show();
                    
                    that.options.params.spatial_filter = (wkt['geo']) ?wkt :{geo:wkt}; 
                }else{
                    that.element.find('.map_snapshot').attr('src',null).hide();
                    that.element.find('.define_spatial').show();
                    that.element.find('.reset_spatial').hide();
                    that.options.params.spatial_filter = null;
                    that.ui_spatial_filter_image = null;
                }
           }
           
           this._on( [ele.find('button.define_spatial')[0],that.element.find('.map_snapshot')[0]],
            { click:                         
           function(event){
                
                //open map digitizer - returns WKT rectangle 
                var rect_wkt = that.options.params.spatial_filter
                        ?that.options.params.spatial_filter
                        :that.options.params.ui_spatial_filter_initial;
                
                if(rect_wkt && rect_wkt['geo']){
                    rect_wkt = rect_wkt['geo'];
                }
                var url = window.hWin.HAPI4.baseURL 
                +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                var wkt_params = {wkt: rect_wkt, geofilter:true, need_screenshot:true};

                window.hWin.HEURIST4.msg.showDialog(url, {height:'540', width:'600',
                    window: window.hWin,  //opener is top most heurist window
                    dialogid: 'map_digitizer_filter_dialog',
                    params: wkt_params,
                    title: window.hWin.HR('Heurist spatial search'),
                    class:'ui-heurist-bg-light',
                    callback: function(location){
                        if( !window.hWin.HEURIST4.util.isempty(location) ){
                            __setUiSpatialFilter(location.wkt, location.imgData);
                            that.doSearch();
                        }
                    }
                } );
           }});   
           
           if(that.options.params.ui_spatial_filter_init && !that.options.params.spatial_filter){
               that.options.params.spatial_filter = that.options.params.ui_spatial_filter_initial;
               that.options.params.ui_spatial_filter_init = false;
           }
           
           __setUiSpatialFilter(that.options.params.spatial_filter, that.ui_spatial_filter_image);
           
       }

       if(this.options.params.ui_additional_filter){
           
           var lbl = that.options.params.ui_additional_filter_label
                        ?that.options.params.ui_additional_filter_label
                        :window.hWin.HR('Search everything');
                        
                        
           var w = that.element.width();
           if(!(w>0) || w<200) w = 200;
           w = {'width':(w-65)+'px','max-width':(w-65)+'px','min-width':'auto'};
           
           var ele = $("<div>").html(
           '<div class="header" title="" style="vertical-align: top; display: block; width: 100%; padding: 5px;">'
                +'<h4 style="display:inline-block;margin:0;">'+lbl+'</h4></div>'
                +'<div style=" padding:5px 0 20px 21px;display: block;">'
                    +'<div class="input-div" style="display: inline-block;padding:0px">'
                    +'<input class="ui-widget-content ui-corner-all" autocomplete="disabled" autocorrect="off" autocapitalize="none" spellcheck="false" style="width: 150px;">'
                    +'</div><button title="To clear previous search click the RESET button" class="smallbutton ui-button ui-corner-all ui-widget ui-button-icon-only">'
                        +'<span class="ui-button-icon ui-icon ui-icon-search"></span><span class="ui-button-icon-space"> </span></button>'
                    +'</div>').css({'border-bottom': '1px solid lightgray','margin-bottom':'10px'}).appendTo($fieldset);
           
           ele.find('input').css(w);             
                        
           /*ele.find(".start-search") class="input-cell"
                        .button({icons: {primary: "ui-icon-search"}, text:false})
                        .attr('title', window.hWin.HR('Start search'))
                        .css({'height':'16px', 'width':'16px'})*/
                        
           this._on( ele.find('button'), { click:                         
           function(event){
               that.options.params.add_filter = ele.find('input').val();
               //$(event.target).parents('.input-cell').find('input').val();  

               that.doSearch();
           }});   
           
           this._on( ele.find('input'), {
                            keypress:
                            function(e){
                                var code = (e.keyCode ? e.keyCode : e.which);
                                if (code == 13) {
                                    that.options.params.add_filter = $(e.target).val();
                                    window.hWin.HEURIST4.util.stopEvent(e);
                                    e.preventDefault();
                                    that.doSearch();
                                }
                            }});
           
                     

       }
       
       
       $.each(this.options.params.facets, function(idx, field){
       
           //content_id+"_"+
           
          var codes = field['code'].split(':');
          var j = 0;
          var harchy = [];
          while (j<codes.length){
               harchy.push($Db.rty(codes[j],'rty_Name'));
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
                 var rtid = field['rtid'];
                 if(rtid.indexOf(',')>0){  //if multirectype use only first one
                        rtid = rtid.split(',')[0];
                 }
                 
                 var ed_options = {
                                varid: field['var'],  //content_id+"_"+
                                recID: -1,
                                rectypeID: rtid,
                                dtID: field['id'],
                                
                                values: [''],
                                readonly: false,
                                title:  (that.options.params.title_hierarchy?harchy:'')
                                        + "<span style='font-weight:bold'>" + field['title'] + "</span>",
                                detailtype: field['type'],  //overwrite detail type from db (for example freetext instead of memo)
                                showclear_button: false,
                                showedit_button: false,
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
                    
                    inpt.find('.input-cell > .input-div').css({'display':'inline-block',padding:0}); // !important
                    inpt.find('.input-cell').css('display','block');
                    
                    //since it takes default width from field definitions
                    //force width for direct input and selectors to 150px 
                    var w = that.element.width();
                    if(!(w>0) || w<200) w = 200;
                    inpt.find('input').removeClass('text').css({'width':(w-65)+'px','max-width':(w-65)+'px','min-width':'auto'});
                    inpt.find('select').removeClass('text').css({'width':(w-45)+'px','min-width': (w-45)+'px'}); // was 30
                    
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
            
            //trigget empty fake event to update messge in result list
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, 
                [ {empty_remark:'<div style="padding:10px;"><h3><span class="ui-icon ui-icon-arrowthick-1-w"></span>'
                +'Please select from facets on left</h3></div>', search_realm:this.options.search_realm} ]);
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
    // 1. substitute Xn variable in query array with value from input form
    // 2. remove branch in query if all variables are empty (except root)
    //
    //  facet_index_do_not_touch - $Xn will be relaced with $FACET_VALUE - to use on server side for count calculations
    // 
    ,_fillQueryWithValues: function( q, facet_index_do_not_touch ){
        
        var _inputs = this._input_fields;
        var that = this;
        var isbranch_empty = true;
            
        $(q).each(function(idx, predicate){ //loop through all predicates of main query 
        // [{"f:1":$X680},....]
            
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
                                         var val = sel[0];
                                         var search_all_words = false;

                                         if(val.length>2 && val[0]=='"' && val[val.length-1]=='"'){
                                            val = val.substring(1,val.length-1);
                                         }else if(!window.hWin.HEURIST4.util.isempty(val) && val.indexOf(' ')>0){
                                            search_all_words = true;
                                         }
                                         
                                         facets[facet_index].selectedvalue = {value:val};

                                         //search for ANY word
                                         if(search_all_words){
                                             var values = val.split(' ');
                                             var predicates = [];
                                             for (var i=0; i<values.length; i++){
                                                 var pre = {};
                                                 if(window.hWin.HEURIST4.util.isempty(values[i])
                                                    || values[i].toLowerCase() == 'or' 
                                                    || values[i].toLowerCase() == 'and'){
                                                     
                                                 }else{
                                                     pre[key] = values[i];
                                                     predicates.push(pre);
                                                 }
                                             }
                                             if(predicates.length>1){
                                                 q.push({"any":predicates});
                                                 isbranch_empty = false;
                                                 delete predicate[key];
                                                 continue;
                                             }
                                         }
                                         
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
    
    //
    //
    //
    ,doSearch: function(){

//console.log('dosearchj');
//console.log( this.options.params.q );

            var query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q ); //clone 
            var isform_empty = this._fillQueryWithValues(query);

//console.log('form is empty '+isform_empty);                
//console.log( query );
            
            if(isform_empty && 
                window.hWin.HEURIST4.util.isempty(this.options.params.add_filter) && 
                window.hWin.HEURIST4.util.isempty(this.options.params.spatial_filter) &&
                !this.options.params.search_on_reset){
                
                if(true){
                    //clear main result set
                    
                    this.doReset();
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr('Define at least one search criterion');
                }
                return;
            }else if(!this.options.ispreview && this.options.showresetbutton && !this.options.params.ui_spatial_filter){
                //this.div_title.css('width','45%');
                if(this.btn_reset) this.btn_reset.show()   
                //@todo this.btn_save.show(); 
            }
            
            var div_facets = this.facets_list.find(".facets");
            if(div_facets.length>0)  div_facets.empty();
            
            
            var search_any_filter = window.hWin.HEURIST4.util.isJSON(this.options.params.add_filter);
            if(search_any_filter==false){
                if(this.options.params.add_filter){
                    //check that this is not old search format
                    var s = this.options.params.add_filter;
                    var colon_pos = s.indexOf(':');
                    if(colon_pos>0){
                        //var pred_type = s.substring(0,colon_pos).toLowerCase();
                        //if([].indexOf(pred_type)>=0){
                            search_any_filter = s;
                    }
                        
                    if(!search_any_filter) search_any_filter = {f:s};
                }else{
                    search_any_filter = '';
                }
            }

            //this approach adds supplemntary(preliminary) filter to every request 
            //it works however 
            //1) it requires that this filter must be a valid json  - FIXED
            //2) it makes whole request heavier
            //adds additional/supplementary and spatial filters
            this._current_query = window.hWin.HEURIST4.util.mergeHeuristQuery(query, 
                            (this._use_sup_filter)?this.options.params.sup_filter:'', 
                            search_any_filter,
                            this._prepareSpatial(this.options.params.spatial_filter));
            
            
//{"f:10":"1934-12-31T23:59:59.999Z<>1935-12-31T23:59:59.999Z"}            
            if(this.options.params.sort_order){
                
                this._current_query.push({sortby:this.options.params.sort_order});
            
            }else if(window.hWin.HAPI4.database=='johns_hamburg' &&
                //special order by date fields 
                window.hWin.HEURIST4.util.findArrayIndex(this.options.svs_ID,[21,23,24])>=0){
                    
                this._current_query.push({sortby:'hie'});
                
            }else {
                this._current_query.push({sortby:'t'});
            }
        
//console.log( 'start search' );        
//console.log( this._current_query );

            
            var request = { q: this._current_query, 
                            w: this.options.params.domain, 
                            detail: 'ids', 
                            source:this.element.attr('id'), 
                            qname: this.options.query_name,
                            rules: this.options.params.rules,
                            rulesonly: this.options.params.rulesonly,
                            viewmode: this.options.params.ui_viewmode,
                            //to keep info what is primary record type in final recordset
                            primary_rt: this.options.params.rectypes[0],
                            ispreview: this.options.ispreview,
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
            
                this._terminateFacetCalculation = false;
                this.btn_terminate.show();
                if(this.btn_reset) this.btn_reset.hide()    
                this.btn_close.hide();

                var div_facets = this.facets_list.find(".facets");
                if(div_facets.length>0)
                    div_facets.empty()
                    .css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white20.gif) no-repeat center center');
        }
        if(this._terminateFacetCalculation){
            field_index  = this.options.params.facets.length;
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
                        break;
                }
                
                var subs_value = null; //either initial query OR rectype+current result set
                
                if(this._isInited || (field.multisel && field.selectedvalue!=null)){
                    //replace with current query   - @todo check for empty 
                    subs_value = window.hWin.HEURIST4.util.mergeHeuristQuery(this._first_query, 
                                    (this._use_sup_filter)?this.options.params.sup_filter:'',
                                    this.options.params.add_filter,
                                    this._prepareSpatial(this.options.params.spatial_filter));
                    
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
     
                var query, needcount = 2;
                if( (typeof field['facet'] === 'string') && (field['facet'] == '$IDS') ){ //this is field form target record type
                
                    if(this._isInited){
                        //replace with current query   - @todo check for empty 
                        query = this._first_query;

                        //add additional/supplementary filter
                        query = window.hWin.HEURIST4.util.mergeHeuristQuery(query, 
                                        (this._use_sup_filter)?this.options.params.sup_filter:'',   //suplementary filter defined in wiz
                                        this.options.params.add_filter,
                                        this._prepareSpatial(this.options.params.spatial_filter));  //dynaminc addition filter

                        
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
                                                 window.hWin.HEURIST4.util.cloneJSON(this.options.params.q).splice(1) ); //remove t:XX
                
                //var count_query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q );
                this._fillQueryWithValues( count_query, i );
                        
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
                    
                    vocabulary_id = $Db.dty(field['id'], 'dty_JsonTermIDTree');    

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
        
                var fieldid = field['id'];
                if(fieldid==9 && that._use_multifield){
                    fieldid = '9,10,11';
                }else if(fieldid==1  && that._use_multifield){
                    fieldid = '1,18,231,304';
                }

//console.log(query);                
//console.log(count_query);                
                
                var request = {q: query, count_query:count_query, w: 'a', a:'getfacets',
                                     facet_index: i, 
                                     field:  fieldid,
                                     type:   field['type'],
                                     step:   step_level,
                                     facet_type: field['isfacet'], //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
                                     facet_groupby: field['groupby'], //by first char for freetext, by year for dates, by level for enum
                                     vocabulary_id: vocabulary_id, //special case for firstlevel group - got it from field definitions
                                     needcount: needcount,         
                                     qname:this.options.query_name,
                                     request_id:this._request_id,
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
                

                window.HAPI4.RecordMgr.get_facets(request, function(response){ 
                    
                    //ignore results of passed sequence
                    if(response.request_id != that._request_id){
                        
                        if(response.status != window.hWin.ResponseStatus.OK){
                            console.log('ERROR in get_facets');
                            console.log(response.message);
                        }
                        return;
                    }
                    that._redrawFacets(response, true) 
                });            
                                
                break;
            }

        }
        
        
        if(i  >= this.options.params.facets.length){
            this.btn_terminate.hide();
            this._refreshButtons();
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

                    
                    if(keep_cache && response.count_query){
                        response.count_query = window.hWin.HEURIST4.util.hashString(JSON.stringify(response.count_query));
                        this.cached_counts.push(response);
                    }
                    
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
                        $facet_values = $('<div>').addClass('facets').appendTo( $(dd[0]) );
                        //AAA strange padding .css({'padding':'4px 0px 10px 5px'})
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
                            field.history.push({title:window.hWin.HR('all'), value:null});
                        }
                        field.history.push(field.selectedvalue);
                    }
                    
                    //
                    //draw show more/less toggler for long lists
                    //
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
                        
                        var is_first_level = false;
                        if(!field['step0_vals']){
                            //keep all terms with values for first level
                            field['step0_vals'] = {};  
                            is_first_level = true;
                        } 
                        
                        //enumeration
                        var dtID = field['id'];  
                        var vocab_id = $Db.dty(dtID, 'dty_JsonTermIDTree');    
                                              
if(!(vocab_id>0)){
    console.log('Field '+dtID+' not found!!!!');
    console.log(field);
    //search next facet
    this._recalculateFacets( facet_index );
    return;
}
                        var term = $Db.trm_TreeData(vocab_id, 'tree');                     
                        term = {key: null, title: "all", children: term};
                        //field.selectedvalue = {title:label, value:value, step:step};                    
                        
                        //
                        // verify that term is in response - take count from response
                        //
                        function __checkTerm(term){
                                var j;
                                for (j=0; j<response.data.length; j++){
                                    if(response.data[j][0] == term.key){
                                        return {title:term.title, 
                                             value:term.key, count:parseInt(response.data[j][1])};
                                        //var f_link = that._createFacetLink(facet_index, {title:term.title, value:term.key, count:response.data[j][1]} );
                                        //return $("<div>").css({"display":"block","padding":"0 5px"}).append(f_link).appendTo($container);
                                    }
                                }
                                return null; //no entries
                        }
                        
                        var terms_drawn = 0; //it counts all terms as plain list 
                        
                        //{key:null, title:window.hWin.HR('all'), children:termtree}
                        //draw terms and all its parents    
                        //2 - inline list, 3 - column list
                        function __drawTerm(term, level, $container, field){
                            
                                //draw itslef - draw children
                                if(term.value){
                                    
                                        if((field['isfacet']==that._FT_COLUMN) || (field['isfacet']==that._FT_LIST)){                          //LIST          
                                            var display_mode = (field['isfacet']==that._FT_COLUMN)?'block':'inline-block';
                                            f_link = that._createFacetLink( facet_index, 
                                                term,
                                                //{title:term.title, value:term.value, count:term.count}, 
                                                display_mode );
                                            
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
                                        //SELECTOR/DROPDOWN
                                            that._createOption( facet_index, level, {title:term.title, 
                                                value:term.value, 
                                                count:term.count} ).appendTo($container);
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
                        // returns counts for itself and children
                        //
                        function __calcTerm(term, level, groupby){
                            
                            var res_count = 0;
                            term.supress_count_draw = false;
                            
                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(term.children)){ //is root or has children

                                //find total count for this term and its children
                                var k, ch_cnt=0;
                                if(term.children)
                                for (k=0; k<term.children.length; k++){
                                    var cnt = __calcTerm(term.children[k], level+1, groupby);
                                    if(cnt>0){
                                        res_count = res_count + cnt;    
                                        ch_cnt++;
                                    }
                                }
                                
                                term.supress_count_draw = (ch_cnt==1);
                                
                                //
                                // some of children have counts 
                                // creates
                                //
                                //old way
                                /*
                                var term_value = null;
                                if(res_count>0){ 
                                    
                                    if(term.termssearch){
                                        if(term.termssearch.indexOf(term.key)<0){
                                            term.termssearch.push(term.key);
                                        }
                                        term_value = term.termssearch; //.join(",");
                                    }else{
                                        term_value = term.key;
                                    }
                                    if(!window.hWin.HEURIST4.util.isempty(term_value) || 
                                        !window.hWin.HEURIST4.util.isnull(field.selectedvalue)){                               
                                    
                                        term.value = term_value;
                                        term.count = 0;
                                        res_count++;
                                    
                                    }
                                }
                                */ 
                                
                                //note: sometimes value may be equal to header
                                var headerData = __checkTerm(term);
                                
                                
                                if(headerData!=null){//this term itself has counts
                                
                                        //search for this term only
                                        //term.value_0 = '='+headerData.value;
                                        //term.count_0 = headerData.count;

                                        //search for this term and all its children
                                        
                                        if(res_count>0){
                                            term.children.unshift({title:'unspecified', count:headerData.count, value:'='+headerData.value});
                                            term.value = term.termssearch?term.termssearch:term.key;
                                            term.count = res_count + headerData.count;  
                                        } else {
                                            term.value = '='+headerData.value;
                                            term.count = headerData.count;
                                        }

                                        //res_count++;
                                }else{
                                        //term.value_0 = null;
                                        //term.count_0 = 0;
                                        var val = term.termssearch ?term.termssearch :term.key;
                                        if(res_count>0){
                                            term.value = val;
                                            term.count = res_count;
                                            
                                            if(is_first_level && val && field['multisel']){
                                                //keep counts for level 0 - to show all terms for multisel mode
                                                field['step0_vals'][val] = 1;
                                            }
                                            
                                        }else{
                                            if(!is_first_level && field['step0_vals'][val]>0){
                                                term.value = val;
                                            }else{
                                                term.value = null;
                                            }
                                            
                                            term.count = 0;
                                        }
                                    
                                }
                                
                                
                            }
                            else {
                                //no children
                                var termData =__checkTerm(term);
                                if(termData!=null){
                                    //leave
                                    term.value = termData.value;
                                    term.count = termData.count;
                                    res_count = 1; 
                                    
                                    if(is_first_level && field['multisel']){
                                        //keep counts for level 0 - to show all terms for multisel mode
                                        field['step0_vals'][term.value] = 1;
                                    }
                                    
                                }else{
                                    if(!is_first_level && field['step0_vals'][term.key]>0){
                                        term.value = term.key;
                                    }else{
                                        term.value = null;
                                    }
                                    term.count = 0;
                                }
                            }
                            
                            return term.count; //res_count;
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
                                __drawTerm(term, 0, $facet_values, field); //term is a tree for vocabulary
                                
                                //show viewport collapse/exand control
                                if(this.options.params.viewport<terms_drawn){
                                    var d_mode = field['isfacet']==this._FT_COLUMN ? 'block':'inline-block'; 
                                    __drawToggler($facet_values, d_mode);
                                }
                                
                        }else{
                            //as dropdown
                            var w = that.element.width();
                            if(!(w>0) || w<200) w = 200;
                            var $sel = $('<select>').css({"font-size": "0.6em !important", "width":(w-45)+'px' }); // was 30
                                $sel.appendTo( $("<div>").css({"display":"inline-block","padding":"0 5px"}).appendTo($facet_values) );
                                
                                that._createOption( facet_index, 0, {title:window.hWin.HR('select...'), value:null, count:0} ).appendTo($sel);
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
                                var f_link = this._createTermLink(facet_index, {id:cterm.id, title:cterm.title, query:cterm.termssearch.join(","), count:cnt});
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
                                    {title:$Db.rty(rtID,'rty_Name'), query:rtID, count:cterm[1]}, 'inline-block');
                                $("<div>").css({"display":"inline-block","padding":"0 3px"})
                                  .addClass('facet-item')
                                  .append(f_link).appendTo($facet_values);
                            }
                        }

                    }else 
                    if ((field['type']=="float" || field['type']=="integer" 
                        || field['type']=="date" || field['type']=="year") && field['isfacet']==this._FT_SELECT)
                    {  //add slider
                    
                        $input_div.find('.input-cell').css('padding-bottom','20px');
                    
                        $facet_values.parent().css({'display':'block'});
                        //AAA strange padding ,'padding-left':'1em','padding-right':'2em'
                        //'width':'90%', $facet_values.css({'width':'100%','padding':'1em'});

                        var cterm = response.data[0];
                        
                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                                    var f_link = this._createFacetLink(facet_index, {title:'', value:null, step:0}, 'inline-block');
                                    $('<span>').css({'display':'inline-block','vertical-align':'middle','margin-left':'0px'}) //-15px
                                        .append(f_link).appendTo($facet_values);
                        }
                        var sl_count = (cterm && cterm.length==3)?cterm[2]:0;
                        
                        if(field.selectedvalue){ //currently selected value - some range was already set
                                if($.isNumeric(field.selectedvalue.value) ||  field.selectedvalue.value.indexOf('<>')<0  ){
                                    cterm = [field.selectedvalue.value, field.selectedvalue.value];
                                }else{
                                    cterm = field.selectedvalue.value.split('<>');
                                }
                        }
                        
                        var mmin  = cterm[0];
                        var mmax  = cterm[1];
                        var daymsec = 86400000; //24*60*60*1000;   1day
                 
//console.log(cterm[0]+'   '+cterm[1]);
                        
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
                            
                        }else if(!field.selectedvalue && cterm[0]==cterm[1]){ //range was not set and initial
                            
                            //show the only date without slider
                            s = temporalSimplifyDate(cterm[0]);
                            
                            $("<span>").html(s 
                                    + ((sl_count>0) ?'<span class="badge" style="float: right;">'+sl_count+'</span>':''))
                                   .appendTo($facet_values); 
                            
                        }else{
                            
                            if(isNaN(field.mmin0) || isNaN(field.mmax0)){
                                //on first request set limits
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
                                        min = __dateToString(min);
                                        max = __dateToString(max);
                                    }else{
                                        min = __roundNumericLabel(min);
                                        max = __roundNumericLabel(max);
                                    }
                                    that.element.find( "#facet_range"+facet_index )
                                        .html('<a href="#" class="link2">'+min
                                            +'</a> - <a href="#" class="link2">'+max+'</a>' 
                                            + ((cnt>0)?
                                            '<span class="badge" style="float: right;">'+cnt+'</span>':''));
                                }
                            }
                            
                            function __dateToString(val){
                                try{
                                   var tDate = new TDate((new Date(val)).toISOString());
                                   val = tDate.toString(date_format);
                                   //val = (new Date(val)).format(date_format);
                                   //val = moment(val).format(date_format);
                                }catch(err) {
                                   val = ""; 
                                }
                                return val;
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

                                __onSlideStartSearch(min, max);
                            }
                            
                            //
                            function __onSlideStartSearch( min, max ){
                                
                                var field = that.options.params.facets[facet_index];
                                
                                if(field['type']=="date"){
                                    try{
                                        //year must be four digit
                                        //min = (new TDate(min)).toString();
                                        //max = (new TDate(max)).toString(); 
                                        
                                        var tDate = new TDate((new Date(min)).toISOString());
                                        min = tDate.toString();
                                        
                                            tDate = new TDate((new Date(max)).toISOString());
                                        max = tDate.toString();
                                        
                                        //min = (new Date(min)).toISOString();
                                        //max = (new Date(max)).toISOString(); 
                                    }catch(err) {
                                       window.hWin.HEURIST4.msg.showMsgFlash('Unrecognized date format');
                                    }
                                }

                                var value = (min==max)?min :min + '<>' + max; //search in between
                                
//console.log('start search  '+value);                                
                                
                                if(window.hWin.HEURIST4.util.isempty(value)){
                                    value = '';
                                    field.selectedvalue = null;
                                }else{
                                    field.selectedvalue = {title:'???', value:value, step:1};                    
                                }
                                
                                that.doSearch();
                            }
                            
                            function __onDateRangeDialogClose() {
                                        
                                var startDate = that._date_range_dialog.find('#date-start').editing_input('getValues')[0];
                                var endDate = that._date_range_dialog.find('#date-end').editing_input('getValues')[0];
                                
                                __onSlideStartSearch(startDate, endDate);
                            
                                if(that._date_range_dialog_instance && 
                                   that._date_range_dialog_instance.dialog('instance')){
                                   that._date_range_dialog_instance.dialog( 'close' );
                                }
                            }
                            
                            function __showDateRangeDialog(event){
                                
                                if(!that._date_range_dialog){
                                    
                                    that._date_range_dialog = $(
                                    '<div><div style="padding:10px 0 5px 35px;" id="date-range"></div>'                      
                                    +'<fieldset class="narrow"><div id="date-start"/>'
                                    +'<div id="date-end"/>'
                                    +'</fieldset></div>')
                                    .hide()
                                    .appendTo(this.element);
                                    
                                    var dtFields = {};
                                    dtFields['rst_DisplayName'] = 'Date start';
                                    dtFields['rst_RequirementType'] = 'optional';
                                    dtFields['rst_MaxValues'] = 1;
                                    dtFields['rst_DisplayWidth'] = 20; 
                                    dtFields['dty_Type'] = 'date';
                                    
                                    var ed_options = {
                                        recID: -1,
                                        dtID: 'dStart',
                                        //readonly: false,
                                        showclear_button: false,
                                        dtFields:dtFields
                                    };

                                    that._date_range_dialog.find('#date-start').editing_input(ed_options);
                                    
                                    dtFields['rst_DisplayName'] = 'Date end';
                                    ed_options['dtID'] = 'dEnd';
                                    that._date_range_dialog.find('#date-end').editing_input(ed_options);
                                    
                                    that._date_range_dialog.find('.editint-inout-repeat-button').hide();
                                 
                                }
                                
                                that._date_range_dialog.find('#date-start').editing_input('setValue', 
                                        temporalSimplifyDate(cterm[0])); //__dateToString(mmin)
                                that._date_range_dialog.find('#date-end').editing_input('setValue', 
                                        temporalSimplifyDate(cterm[1]));
                                that._date_range_dialog.find('#date-range')
                                    .text('Range '+__dateToString(field.mmin0)+' - '+__dateToString(field.mmax0));
                                
                                var buttons = {};
                                buttons[window.hWin.HR('Apply')]  = __onDateRangeDialogClose;
                                
                                //window.hWin.HEURIST4.msg.showMsgDlg('Define data range <>',
                                that._date_range_dialog_instance = window.hWin.HEURIST4.msg.showElementAsDialog(
                                {
                                   element: that._date_range_dialog[0], 
                                   close: function(){
                                        //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();      
                                   },
                                   buttons: buttons,
                                   title:'Define selection range',
                                   resizable: false,
                                   width:280,
                                   height:190,
                                   position:{my:'bottom left',at:'top left',of:$(event.target)} 
                                });
                            }
                        
                            var w = that.element.width();
                            var flbl = $("<div>",{id:"facet_range"+facet_index})
                                        .css({display: 'inline-block', 'padding': '0 0 1em 16px', width:(w-40)})
                                        .appendTo($facet_values);
                                        
                            if(field['type']=="date"){
                                flbl.css({cursor:'pointer'});
                                that._on(flbl,{click: __showDateRangeDialog});
                            }
                                
//console.log(mmin, delta, field.mmin0);                                
//console.log('recreate slider  '+facet_index);

                            var ele2 = $('<div><span class="ui-icon ui-icon-triangle-1-w-stop" '
                                +'style="cursor:pointer;font-size:smaller;float:left;color:gray"/>'
                            +'<div style="height:0.4em;margin:2px 0px 0px 2px;float:left;width:'+(w-62)+'px"/>'
                            +'<span class="ui-icon ui-icon-triangle-1-e-stop" '
                                +'style="cursor:pointer;font-size:smaller;float:left;color:gray"/></div>'
                            ).appendTo($facet_values);
                                        
                            //$("<div>", {facet_index:facet_index})
                            // .css({'height':'0.4em',margin:'1px 15px'})
                            //.appendTo($facet_values);
                            var slider = ele2.find('div')
                                .attr('facet_index',facet_index)
                                .slider({
                                      range: true,
                                      min: (mmin-delta<field.mmin0)?field.mmin0:(mmin-delta),  //field.mmin0
                                      max: (mmax+delta>field.mmax0)?field.mmax0:(mmax+delta),
                                      values: [ mmin, mmax ],
                                      slide: __updateSliderLabel,
                                      stop: __onSlideStop,
                                      create: function(){
                                          $(this).find('.ui-slider-handle').css({width:'4px',background:'black'});
                                      }
                                    });
                                    
                            that._on( ele2.find('span.ui-icon-triangle-1-w-stop'),
                                 {click: function(){
                                     __onSlideStartSearch(field.mmin0, mmax);
                                 }});
                            that._on( ele2.find('span.ui-icon-triangle-1-e-stop'),
                                 {click: function(){
                                     __onSlideStartSearch(mmin, field.mmax0);
                                 }});

                                 
                            if(mmin==field.mmin0){
                                ele2.find('span.ui-icon-triangle-1-w-stop').css('visibility','hidden');
                            }                                    
                            if(mmax==field.mmax0){
                                ele2.find('span.ui-icon-triangle-1-e-stop').css('visibility','hidden');
                            }                                    
                            
                                 
                            //show initial values
                            __updateSliderLabel(mmin, mmax, sl_count);
                            
                        }
                        }
                    }
                    else if(field['type']=='enum' && field['groupby']=='firstlevel' 
                                && !window.hWin.HEURIST4.util.isnull(field['selectedvalue'])){
                        
                        var cterm = field.selectedvalue;
                        var f_link = this._createFacetLink(facet_index, {title:cterm.title, value:cterm.value, count:'reset'}, 'block');
                        
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
                                    $span.text(cvalue.title).appendTo($facet_values);
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
                                cterm[0] = $Db.getTermValue(cterm[0], false);    
                            }

                            var f_link = this._createFacetLink(facet_index, {title:cterm[0], value:cterm[2], count:cterm[1]}, display_mode);
                            
                            //@todo draw first level for groupby firs tchar always inline
                            var step_level = (field['groupby']=='firstchar' && field['selectedvalue'])
                                                ?field['selectedvalue'].step:0;
                                                                                                      
                            var ditem = $("<div>").css({'display':(i>this.options.params.viewport-1?'none':display_mode),"padding":"0 3px"})
                                                .addClass('facet-item')
                                                .append(f_link).appendTo($facet_values);
                                                
                            if(i>this.options.params.viewport-1){
                                 ditem.addClass('in-viewport');
                            }                    
                            if(i>2000){  //was 250
                                $("<div>").css({"display":"none","padding":"0 3px"})
                                 .addClass('in-viewport')
                                 .html('still more...( '+(response.data.length-i)+' results )').appendTo($facet_values);
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
        
        var lbl = cterm.title; //(new Array( indent + 1 ).join( " . " )) + 
        if(cterm.count>0){
            lbl =  lbl + " ("+cterm.count+")";
        } 

        var f_link = $("<option>",{facet_index:facet_index, facet_value:cterm.value, facet_label:lbl, step:step})
                            //.css('padding-left', ((indent-1)*2)+'em' )
                            .text(lbl).addClass("facet_link")
        f_link.attr('depth',indent-1);
        
        return f_link;
    }
    
    , _onTermSelect: function(event){
        
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
                    field.selectedvalue = {title:label, value:value, step:step};                    
                }
                
                if(prevvalue!=value){
                    this.doSearch();
                }
    }

    // cterm - {title, value, count}
    ,_createFacetLink: function(facet_index, cterm, display_mode){

        var field = this.options.params.facets[facet_index];
        //var step = cterm.step;
        var hist = field.history;
        if(!hist) hist = [];
        var step = hist.length+1;
        var iscurrent = false;
        
        var currval = field.selectedvalue?field.selectedvalue.value:null;
        
        var f_link = $("<a>",{href:'#', facet_index:facet_index, 
                        facet_value: (cterm.count=='reset')?'':cterm.value, 
                        facet_label: cterm.title, 
                        step:step})
                    .addClass("facet_link")
        
        //----
        var f_link_content;
        
        if(window.hWin.HEURIST4.util.isempty(cterm.value)){
            f_link_content = $("<span>").addClass("ui-icon ui-icon-arrowreturnthick-1-w")
                .css({'font-size':'0.9em','height':'10px'}); //AAA ,'margin-left':'-15px'    
        }else{
            f_link_content = $("<span>").text(cterm.title);
            
            if(display_mode=='block'){         

                var top_parent = this.element.parents('.mceNonEditable');
                if(this.options.is_publication){ //this is web publication  top_parent.length>0
                    f_link_content.css('width',top_parent.width()*0.6).addClass('truncate');
                }else{
                    f_link_content.css('width',this.element.width()-100).addClass('truncate');    //was 80 this.facets_list_container.width()*0.6
                }
            
            
                f_link_content.attr('title', cterm.title);
            }
            
            if(!window.hWin.HEURIST4.util.isempty(currval)){
                
                if(field.multisel){
                    iscurrent = (window.hWin.HEURIST4.util.findArrayIndex(cterm.value, currval.split(','))>=0);
                }else{
                    iscurrent = (currval == cterm.value);    
                }
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
            var txt = '';
            if(cterm.count=='reset'){
                txt = 'X';
            }else if(cterm.count>0 && cterm.supress_count_draw!==true){
                    txt = cterm.count;
            }
            
            if(txt!=''){
            
                var dcount = $('<span>').addClass('badge').text(txt);
                
                if(display_mode=='inline-block'){
                     dcount.appendTo(f_link).appendTo(f_link_content);
                }else{
                     dcount.appendTo(f_link).css({float:'right'});
                }
            }
        }
        
        if( field.multisel || !iscurrent || cterm.count=='reset'){ 

        var that = this;

        this._on( f_link, {
            click: function(event) { 

                var link = $(event.target).parents('.facet_link');
                var facet_index = Number(link.attr('facet_index'));
                var value = link.attr('facet_value');                  
                var label = link.attr('facet_label');                  
                var step = link.attr('step');
                
                var field = this.options.params.facets[facet_index];
                
                if(window.hWin.HEURIST4.util.isempty(value)){
                    value = '';
                    field.selectedvalue = null;
                }else if(field.multisel && field.selectedvalue!=null){
                    
                    var vals = field.selectedvalue.value.split(',');
                    var k = window.hWin.HEURIST4.util.findArrayIndex(value, vals);
                    if(k<0){ //add
                        vals.push(value);
                    }else{ //remove
                        vals.splice(k,1);
                    }
                    if(value.length==0){
                        field.selectedvalue = null;
                    }else{
                        field.selectedvalue.value = vals.join(',');    
                    }
                }else{
                    field.selectedvalue = {title:label, value:value, step:step};                    
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

        var rtid = field['rtid'];
        if(rtid.indexOf(',')>0){
            rtid = rtid.split(',')[0];
        }

        var ed_options = {
            varid: field['var'],  //content_id+"_"+
            recID: -1,
            rectypeID: rtid,
            dtID: field['id'],
            
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


    },
    
    //
    // if main record type has linked or related place rectype search by location
    // if main field has geo field search by this field
    // otherwise ignore spatial search 
    //
    _prepareSpatial: function(wkt){
       
       if(!window.hWin.HEURIST4.util.isempty(wkt))
       {
           if(this._hasLocation==null){
                this._hasLocation = 'none';   
   
                var primary_rt = this.options.params.rectypes[0];
                
                if(window.hWin.HEURIST4.dbs.hasFields(primary_rt, 'geo', null)){
                    this._hasLocation = 'yes';
                }else{
                    var RT_PLACE  = window.hWin.HAPI4.sysinfo['dbconst']['RT_PLACE'];
                    
                    
                    var linked_rt = window.hWin.HEURIST4.dbs.getLinkedRecordTypes(primary_rt, null, true);
                    if ( window.hWin.HEURIST4.util.findArrayIndex(RT_PLACE, linked_rt['linkedto'])>=0 )
                    {
                        this._hasLocation = 'linkedto';   
                    }else 
                    if ( window.hWin.HEURIST4.util.findArrayIndex(RT_PLACE, linked_rt['relatedto'])>=0 )
                    {
                        this._hasLocation = 'relatedto';
                    }
                }
                
                if(this._hasLocation == 'none'){
                    window.hWin.HEURIST4.msg.showMsgFlash('There is no spatial data to filter on. Please ask the owner of the filter to hide the spatial component.',4000);
                }
           }
           
           if(this._hasLocation=='yes'){ 
                return wkt;           
           }else if(this._hasLocation!='none'){
               var res = {};
               res[this._hasLocation] = wkt;
               return res;
           }           
       }
        
       return null; 
    },
    
    //
    // info message in the header to indicate that user work set is active
    //
    refreshSubsetSign: function(){
        
        if(this.div_header){

            var container = this.div_header.find('div.subset-active-div');
            
            if(container.length==0){
                var ele = $('<div>').addClass('subset-active-div').css({'padding-left':'1.3em','padding-top':'4px'}) //css({'padding':'0.1em 0em 0.5em 1em'})
                      .appendTo(this.div_header);
            }
            container.find('span').remove();
            //var s = '<span style="position:absolute;right:10px;top:10px;font-size:0.6em;">';    
         
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                
                $('<span style="padding:0.3em 1em;background:white;color:red;vertical-align:sub;font-size: 11px;font-weight: bold;"'
                  +' title="'+window.hWin.HAPI4.sysinfo.db_workset_count+' records"'
                  +'>SUBSET ACTIVE n='+window.hWin.HAPI4.sysinfo.db_workset_count+'</span>')
                    .appendTo(container);
            }
            this._adjustSearchDivTop();
        }
        
    },
    
    
    

    
});