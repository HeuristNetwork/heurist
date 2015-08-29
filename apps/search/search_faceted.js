/**
*  Apply faceted search
* TODO: Check that this is what it does and that it is not jsut an old version
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
]

]


NOTE - to make search for facet value faster we may try to omit current search in query and search for entire database
            
*/



$.widget( "heurist.search_faceted", {

    _MIN_DROPDOWN_CONTENT: 50, //min number in dropdown selector, otherwise facet values are displayed in explicit list

    // default options
    options: {
        // callbacks
        params: {},
        ispreview: false,
        onclose: null
    },

    cached_counts:[],
    _input_fields:{},
    _request_id: 0,
    _first_query:[], //query for all empty values
    _isInited: true,
    _isAllFacets: false, // if first field has isfacet=true - consider all fields as facets
    _current_query: null,

    // the widget's constructor
    _create: function() {

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this.div_header = $( "<div>" ).appendTo( this.element );
        
        if(!this.options.ispreview){
            this.div_title = $("<div style='text-align:left;padding-top:1.4em;padding-left:1em;display:inline-block;width:70%'></div").addClass('truncate').appendTo( this.div_header );
        }

        this.div_toolbar = $( "<div>" ).css({"font-size":"0.7em","float":"right","padding-top":"1.8em","padding-right":"0.6em"}).appendTo( this.div_header );

        this.btn_submit = $( "<button>", { text: top.HR("Submit") })
        .appendTo( this.div_toolbar )
        .button();
        
        this.btn_reset = $( "<button>", { text: top.HR("Initial state") })
        .appendTo( this.div_toolbar )
        .button().hide();

        this.btn_save = $( "<button>", { text: top.HR("Save state") })
        .appendTo( this.div_toolbar )
        .button().hide(); //@todo

        this.btn_close = $( "<button>", { text: top.HR("Close") })
        .appendTo( this.div_toolbar )
        .button({icons: {secondary: "ui-icon-close"}});

        //Ian this.div_toolbar.buttonset();

        this._on( this.btn_submit, { click: "doSearch" });
        this._on( this.btn_reset, { click: "doReset" });
        this._on( this.btn_save, { click: "doSaveSearch" });
        this._on( this.btn_close, { click: "doClose" });


        this.facets_list_container = $( "<div>" )
        .css({"top":"3.6em","bottom":0,"left":'1em',"right":'0.5em',"position":"absolute"})
        .appendTo( this.element );

        this.facets_list = $( "<div>" )
        .css({"overflow-y":"auto","overflow-x":"hidden","height":"100%"}) //"font-size":"0.9em",
        .appendTo( this.facets_list_container );

        //this._refresh();
        this.doReset();
        
        var that = this;
        
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCH_FINISH, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){
                //if(data && data.queryid() == that._request_id){
                if(data && data.source==that.element.attr('id') ){   //search from this widget
                      that._isInited = false;
                      that._recalculateFacets(-1);       
                }         
            }
        });

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    _setOptions: function( options ) {
        this._superApply( arguments );
        this.cached_counts = [];
        //this._refresh();
        this.doReset();
    },

    /* 
    * private function    - NOT USED
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
         var facets = this.options.params.facets;
         var hasHistory = false, facet_index, len = facets?facets.length:0;
         for (facet_index=0;facet_index<len;facet_index++){
              if( !top.HEURIST4.util.isempty(facets[facet_index].history) ){
                  hasHistory = true;
                  break;
              }
         }
        

        if(this.div_title) this.div_title.html("<b>"+this.options.query_name+"</b>");

        if(this.options.ispreview){
            this.btn_save.hide(); 
            this.btn_close.hide(); 
        }else{
            
            if(hasHistory) {
                if(this.div_title) this.div_title.css('width','45%');
                this.btn_reset.show()   
                //this.btn_save.show();  //@todo
            }else{
                if(this.div_title) this.div_title.css('width','70%');
                this.btn_reset.hide()   
                this.btn_save.hide(); 
            }
            
            this.btn_close.show(); 
        }

        this.doRender();
        //ART2907 this.doSearch();
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        $(this.document).off( top.HAPI4.Event.ON_REC_SEARCH_FINISH );
        
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
       
           if( !top.HEURIST4.util.isnull(field['var']) && field['code'] && (that._isAllFacets || field['isfacet'])){
               //create new query and add new parameter
               var code = field['code'];
               code = code.split(':');
               
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
               
               
               
               function __checkEntry(qarr, key, val){
                    var len0 = qarr.length, notfound = true, isarray;
                    
                    for (var i=0;i<len0;i++){
                        if(! top.HEURIST4.util.isnull( qarr[i][key] ) ){ //such key already exsits
                            
                            if(top.HEURIST4.util.isArray(qarr[i][key])){ //next level
                                return qarr[i][key];   
                            }else if(qarr[0][key]==val){ //already exists
                                return qarr;
                            }
                        }
                    }

                    var predicat = {};
                    predicat[key] = val;
                    qarr.push(predicat);
                    
                    if(top.HEURIST4.util.isArray(val)){
                        return val;
                    }else{
                         return qarr;
                    }
               }            
               
               /*if(code.length-2 == 0){
                   field['facet'] = {ids:'$IDS'};
               }else{}*/
               field['facet'] = __crt( code.length-2 );
               field['id']   = code[code.length-1];
               field['rtid'] = code[code.length-2];
               
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
                    
                        j=j+2;
                    }               
               
           }
       });
     
       this.options.params['qa'] = mainquery;
   }
    
    //
    // reset current search 
    // recreate facet elements/ or form inputs
    //
    ,doReset: function(){

        var facets = this.options.params.facets;

        if(top.HEURIST4.util.isArrayNotEmpty(facets)){
            var facet_index, len = facets.length;

            for (facet_index=0;facet_index<len;facet_index++){
                facets[facet_index].history = [];
                facets[facet_index].selectedvalue = null;
            }
            //this._refresh();
            this._isAllFacets = facets[0]['isfacet'];
        }
        
        
       this._current_query = null;
       // create list of queries to search facet values 
       this._initFacetQueries();
        
       
       this.facets_list.empty();
       
       var $fieldset = $("<fieldset>").css({'font-size':'0.9em','background-color':'white'}).addClass('fieldset_search').appendTo(this.facets_list);


       if(this._isAllFacets){
            $fieldset.css({'padding':'0'});
            this.btn_submit.hide();
       }
       
        if(this.options.ispreview){
            this.btn_close.hide(); 
        }else{
            this.btn_close.show(); 
        }
        if(this.div_title) {
            this.div_title.html("<b>"+this.options.query_name+"</b>");
            this.div_title.css('width','70%');    
        }
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
               harchy.push(top.HEURIST4.rectypes.names[codes[j]]);
               j = j + 2;
          }
          
          harchy = "<span>"+ harchy.join(" &gt; ") + "</span><br/>&nbsp;&nbsp;&nbsp;";
           
           var ed_options = {
                        varid: field['var'],  //content_id+"_"+
                        recID: -1,
                        rectypeID: field['rtid'],
                        dtID: field['id'],
                        rectypes: top.HEURIST4.rectypes,
                        values: '',
                        readonly: false,
                        title:   harchy + "<span style='font-weight:bold'>" + field['title'] + "</span>",
                        showclear_button: false,
                        detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
                };
                
           if(isNaN(Number(field['id']))){
               ed_options['dtFields'] = {
                   dty_Type: field['type'],
                   rst_RequirementType: 'optional',
                   rst_MaxValues: 1,
                   rst_DisplayWidth:0
               };
           }

           if(!top.HEURIST4.util.isnull(field['var'])){
               
             if( field['code'] && (that._isAllFacets || field['isfacet']) ){
                    
                    //inpt.find('.input-div').hide();
                    //inpt.find('.header').css({'background-color': 'lightgray', 'padding': '5px', 'width': '100%'});
                    //inpt.find('.editint-inout-repeat-button').hide();
                    
                    $("<div>",{id: "fv_"+field['var'] }).html(
                        '<div class="header" style="width: 100%; background-color: lightgray; padding: 5px; width:100%">'+
                            '<label for="input0_1">'+ed_options.title+'</label>'+
                        '</div>'+
                        '<div class="input-cell"></div>').appendTo($fieldset);
                    
             }else{

                var inpt = $("<div>",{id: "fv_"+field['var'] }).editing_input(   //this is our widget for edit given fieldtype value
                        ed_options
                    );

                inpt.appendTo($fieldset);
                that._input_fields['$X'+field['var']] = inpt;
             }
           }
       });
       
       this._isInited = true;
       //get empty query
       this._first_query = top.HEURIST4.util.cloneJSON( this.options.params.qa ); //clone 
       this._fillQueryWithValues( this._first_query );
       this._recalculateFacets(-1);
    }

    ,doSaveSearch: function(){
        //alert("@todo");
    }

    ,doClose: function(){
        this._trigger( "onclose");
    }


    ,doRender: function(){


    }


    //
    // 1. substiture Xn variable in query array with value from input form
    // 2. remove branch in query if all variables are empty (except root)
    //
    ,_fillQueryWithValues: function( q ){
        
        var _inputs = this._input_fields;
        var that = this;
        var isbranch_empty = true;
            
        $(q).each(function(idx, predicate){
            
            $.each(predicate, function(key,val)
            {
                if( $.isArray(val) ) { //|| $.isPlainObject(val) ){
                     var is_empty = that._fillQueryWithValues(val);
                     isbranch_empty = isbranch_empty && is_empty;
                     
                     if(is_empty){
                        //remove entire branch if none of variables are defined
                        delete predicate[key];  
                     }
                }else{
                    if(typeof val === 'string' && val.indexOf('$X')===0){
                        
            
                        if(that._isAllFacets){
                            
                            var facets = that.options.params.facets;
                            var facet_index, len = facets.length;
                            for (facet_index=0;facet_index<len;facet_index++){
                                if(facets[facet_index]["var"] == val.substr(2)){
                                    var selval = facets[facet_index].selectedvalue;
                                    if(selval && !top.HEURIST4.util.isempty(selval.value)){
                                        predicate[key] = selval.value;
                                        isbranch_empty = false;
                                    }else{
                                        delete predicate[key];  
                                    }
                                    break;
                                }
                            }
                        }else{
                        
                            //find input widget and get its value
                            var vals = $(_inputs[val]).editing_input('getValues');
                            if(vals && vals.length>0 && !top.HEURIST4.util.isempty(vals[0])){
                                predicate[key] = vals[0];
                                isbranch_empty = false;
                            }else{
                                delete predicate[key];  
                                //predicate[key] = '';
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

            var query = top.HEURIST4.util.cloneJSON( this.options.params.qa ); //clone 
            var isform_empty = this._fillQueryWithValues(query);
            
            if(isform_empty){
                if(this._isAllFacets){
                    //clear main result set
                    
                    this.doReset();
                }else{
                    top.HEURIST4.util.showMsgErr('Define at least one search criterion');
                }
                return;
            }else if(!this.options.ispreview){
                this.div_title.css('width','45%');
                this.btn_reset.show()   
                //@todo this.btn_save.show(); 
            }
            
            
            
            //this._request_id =  Math.round(new Date().getTime() + (Math.random() * 100));
            this._current_query = query;
            
            var request = { q: query, w: this.options.params.domain, 
                            f: 'map', 
                            source:this.element.attr('id'), 
                            qname:this.options.query_name
                            //id: this._request_id 
                            }; //, facets: facets
            
            //perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));
            
            //perform search for facet values
            //that._recalculateFacets(content_id);
    }
    
    //
    // perform search for facet values and redraw facet fields
    // @todo query - current query - if resultset > 1000, use query
    // _recalculateFacets (call server) -> as callback _redrawFacets -> _recalculateFacets (next facet)
    //
    , _recalculateFacets: function(field_index){
        
        //return;
        
        // this._currentquery
        // this._resultset
        if(isNaN(field_index) || field_index<0){
                field_index = -1;  
            
                var div_facets = this.facets_list.find(".facets");
                if(div_facets.length>0)
                    div_facets.empty()
                    .css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white20.gif) no-repeat center center');
        } 

        var that = this;
        
        var i = field_index;
        for(;i< this.options.params.facets.length; i++)
        {
            var field = this.options.params.facets[i];
            if(i>field_index && (that._isAllFacets || field['isfacet']) && field['facet']){
                
                var subs_value = null;
                
                if(this._isInited){
                    //replace with current query   - @todo check for empty 
                    subs_value =  this._first_query;
                }else{
                    //replace with list of ids
                    subs_value = top.HAPI4.currentRecordset.getIds().join(',');
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
                        query =  this._first_query;
                    }else{
                        //replace with list of ids
                        query = {ids:top.HAPI4.currentRecordset.getIds().join(',')};
                    }
                
                    needcount = 1;
                    
                }else{
                    query = top.HEURIST4.util.cloneJSON(field['facet']); //clone 
                    
                    //change $IDS for current set of target record type
                    __fillQuery(query);                
                    
                    //add other parameters for rectype of this facet
                    if(this._current_query){
                        var copyquery = top.HEURIST4.util.cloneJSON(this._current_query); 
                        var otherparams = __getOtherParameters(copyquery, field['rtid']);
                        if(null!=otherparams){
                             query = query.concat(otherparams);
                        }
                    }
                    
                }
                        
                
                var step_level = field['selectedvalue']?field['selectedvalue'].step:0;
                
                var request = {qa: query, w: 'a', a:'getfacets_new',
                                     facet_index: i, 
                                     field:  field['id'],
                                     type:   field['type'],
                                     step:   step_level,
                                     needcount: needcount,
                                     source:this.element.attr('id') }; //, facets: facets


            /* @TODO try to find in cache
            for (var k=0; k<this.cached_counts.length; k++){
                if( parseInt(this.cached_counts[k].facet_index) == request.facet_index && 
                    this.cached_counts[k].q == request.q && this.cached_counts[k].dt == request.dt){
                    __onResponse(this.cached_counts[k]);
                    return;
                }
            }*/
                window.HAPI4.RecordMgr.get_facets(request, function(response){ that._redrawFacets(response) });            
                                
                break;
            }
        }
        
    }
    
    , _redrawFacets: function( response ) {
        
                if(response.status == top.HAPI4.ResponseStatus.OK){

                    //@TODO this.cached_counts.push(response);
                    var allTerms = top.HEURIST4.terms;
                    var detailtypes = top.HEURIST4.detailtypes.typedefs;
                    
                    var facet_index = parseInt(response.facet_index); 
                    
                    var field = this.options.params.facets[facet_index];
                    
                    var j,i;
                    var $input_div = $(this.element).find("#fv_"+field['var']);
                    //$facet_values.css('background','none');

                    //create fasets container if it does not exists
                    var $facet_values = $input_div.find('.facets');
                    if( $facet_values.length < 1 ){
                        var dd = $input_div.find('.input-cell');
                        $facet_values = $('<div>').addClass('facets').css({'padding':'4px 0 10px'}).appendTo( $(dd[0]) );
                    }
                    $facet_values.css('background','none');
                    
                    //add current value to history
                    if(top.HEURIST4.util.isnull(field.selectedvalue)){ //reset history
                        field.history = []; 
                    }else{
                        //replace/add for current step and remove that a bigger
                        if(!field.history || field.history.length==0){
                            field.history = [];
                            field.history.push({text:top.HR('all'), value:null});
                        }else{
                            field.history = field.history.slice(0, field.selectedvalue.step);
                        }
                        field.history.push(field.selectedvalue);
                    }
                    
                    
                    var that = this;
                    
                    if( field['type']=="enum" ){

                        
                        var dtID = field['id'];                        
                        //enumeration
                        var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                        disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];
                        
                        var term = top.HEURIST4.util.getChildrenTerms('enum', allTerms, disabledTerms, null ); //get entire tree
                        
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
                        
                        //{id:null, text:top.HR('all'), children:termtree}
                        //draw terms and all its parents    
                        function __drawTerm(term, level, $container, as_list){
                            
                                //draw itslef - draw children
                                if(term.value){
                                    
                                        if(as_list){                                    
                                            f_link = that._createFacetLink( facet_index, {text:term.text, value:term.value, count:term.count} );
                                            $("<div>").css({"display":"block","padding":"0 "+(level*5)+"px"})
                                                    .append(f_link)
                                                    .appendTo($container);
                                        }else{
                                            that._createOption( facet_index, level, {text:term.text, value:term.value, count:term.count} ).appendTo($container);
                                        }
                                }
                                if(term.children)
                                for (var k=0; k<term.children.length; k++){
                                    __drawTerm(term.children[k], level+1, $container, as_list);
                                }
                        }//__drawTerm
                        
                        //
                        //calc number of terms with values
                        //
                        function __calcTerm(term, level){
                            
                            var res_count = 0;
                            
                            if(top.HEURIST4.util.isArrayNotEmpty(term.children)){ //is root or has children

                                var k;
                                if(term.children)
                                for (k=0; k<term.children.length; k++){
                                    var cnt = __calcTerm(term.children[k], level+1);
                                    res_count = res_count + cnt;
                                }
                                
                                //sometimes value my equal to header
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
                                    
                                    if(!top.HEURIST4.util.isempty(term_value) || !top.HEURIST4.util.isnull(field.selectedvalue)){                               
                                    
                                        term.value = term_value;
                                        term.count = 0;
                                        res_count++;
                                    
                                    } 
                                }
                                
                            }else {
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
                        var tot_cnt = __calcTerm(term, 0, null);
                        var as_list = (tot_cnt < that._MIN_DROPDOWN_CONTENT);


                        if(top.HEURIST4.util.isArrayNotEmpty(field.history)){
                            var $span = $('<span>').css('display','block');
                            var f_link = this._createFacetLink(facet_index, term);
                            $span.append(f_link).appendTo($facet_values);
                        }                        
                        
                        if(as_list){
                                __drawTerm(term, 0, $facet_values, true);
                        }else{
                                var $sel = $('<select>').css({"font-size": "0.6em !important", "width":"180px"});
                                $sel.appendTo( $("<div>").css({"display":"block","padding":"0 5px"}).appendTo($facet_values) );
                                $sel.change(function(event){ that._onTermSelect(event); });
                                __drawTerm(term, 0, $sel, false);
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
                                    {text:top.HEURIST4.rectypes.names[rtID], query:rtID, count:cterm[1]});
                                $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            }
                        }

                    }else 
                    if(field['type']=="float" || field['type']=="integer" || field['type']=="date" || field['type']=="year"){  //add slider
                    
                        console.log(facet_index);
                    
                        //$facet_values.parent().css({'width':'100%'});
                        $facet_values.css({'width':'100%','padding':'1em'});

                        var cterm = response.data[0];
                        
                        var mmin = cterm[0];
                        var mmax = cterm[1];
                        
                        if(top.HEURIST4.util.isArrayNotEmpty(field.history)){
                                    var cvalue = field.history[0];
                                    cvalue.value = null;
                                    var f_link = this._createFacetLink(facet_index, cvalue);
                                    $('<span>').css('display','block').append(f_link).appendTo($facet_values);
                        }
                        
                        if(!(top.HEURIST4.util.isempty(mmin) || top.HEURIST4.util.isempty(mmax))){
                            
                            if(field['type']=="date"){
                                mmin = mmin.replace(' ','T');
                                mmax = mmax.replace(' ','T');
                                mmin = Date.parse(mmin); 
                                mmax = Date.parse(mmax); 
                                //find date interval for proper formating
                                var delta = mmax-mmin;
                                var date_format = "yyyy-mm-dd HH:MM:ss";
                                var daymsec = 24*60*60*1000;
                                
                                if(delta>3*365*daymsec){ //3 years
                                    date_format = "yyyy";
                                }else if(delta>365*daymsec){ //6 month
                                    date_format = "yyyy-mm";
                                }else if(delta>daymsec){ //1 day
                                    date_format = "yyyy-mm-dd";
                                }
                            
                                
                            }
                            
                        if(mmin==mmax){
                            $("<span>").text(cterm[0]).css({'font-style':'italic', 'padding-left':'10px'}).appendTo($facet_values);
                        }else 
                        if(!(isNaN(mmin) || isNaN(mmax))){
                            
                            
                            function __updateSliderLabel() {
                                      
                                if(arguments && arguments.length==2){
                                    var min, max;      
                                    if(isNaN(arguments[0])){
                                        min = arguments[1].values[ 0 ];
                                        max = arguments[1].values[ 1 ];
                                    }else{
                                        min = arguments[0];
                                        max = arguments[1];
                                    }
                                    if(field['type']=="date"){
                                        min = (new Date(min)).format(date_format);  //toISOString(); 
                                        max = (new Date(max)).format(date_format);
                                    }
                                    $( "#facet_range"+facet_index )
                                        .text( min + " - " + max );
                                }
                            }
                            
                            //preapre value to be sent to server and start search
                            function __onSlideStop( event, ui){

                                var min = ui.values[ 0 ];
                                var max = ui.values[ 1 ];

                                var field = that.options.params.facets[facet_index];
                                
                                if(field['type']=="date"){
                                    min = (new Date(min)).toISOString(); 
                                    max = (new Date(max)).toISOString(); 
                                }

                                var value = (min==max)?min :min + '<>' + max; //search in between
                                
                                if(top.HEURIST4.util.isempty(value)){
                                    value = '';
                                    field.selectedvalue = null;
                                }else{
                                    field.selectedvalue = {text:'???', value:value, step:0};                    
                                }
                                
                                that.doSearch();
                            }
                        
                            var flbl = $("<label>",{id:"facet_range"+facet_index}).appendTo($facet_values);
                            var slider = $("<div>",{facet_index:facet_index})
                                .slider({
                                      range: true,
                                      min: mmin,
                                      max: mmax,
                                      values: [ mmin, mmax ],
                                      slide: __updateSliderLabel,
                                      stop: __onSlideStop
                                    })
                                    .css({'position':'absolute','left':'1em','right':'2em'})
                            .appendTo($facet_values);

                            __updateSliderLabel(mmin, mmax);
                            
                        }
                        }
                    }
                    else{
                        
                        $facet_values.css('padding-left','5px');
                        
                        //draw history
                        if(top.HEURIST4.util.isArrayNotEmpty(field.history)){

                            var k, len = field.history.length;
                            for (k=0;k<1;k++){
                                var cvalue = field.history[k];
                                
                                var $span = $('<span>').css('display','block'); //was inline
                                if(k==len-1){ //last one
                                    $span.text(cvalue.text).appendTo($facet_values);
                                    //$span.append($('<br>'));
                                }else{
                                    var f_link = this._createFacetLink(facet_index, cvalue);
                                    $span.append(f_link).appendTo($facet_values);
                                    //$span.append($('<span class="ui-icon ui-icon-carat-1-e" />').css({'display':'inline-block','height':'13px'}));
                                }
                            }
                        }

                        
                        
                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            var f_link = this._createFacetLink(facet_index, {text:cterm[0], value:cterm[2], count:cterm[1]});
                            $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            if(i>50){
                                 $("<div>").css({"display":"inline-block","padding":"0 3px"}).html('more '+(response.data.length-i)+' results').appendTo($facet_values);
                                 break;       
                            }
                        }
                    }

                    if($facet_values.is(':empty')){
                        $("<span>").text(top.HR('no values')).css({'font-style':'italic', 'padding-left':'10px'}).appendTo($facet_values);
                    }

                    //search next facet
                    this._recalculateFacets( facet_index );

                }else{
                    top.HEURIST4.util.showMsgErr(response);
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
                            .css('padding-left', ((indent-1)*2)+'em' ).text(lbl).addClass("facet_link")
        
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
                
                if(top.HEURIST4.util.isempty(value)){
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
    ,_createFacetLink : function(facet_index, cterm){

        var field = this.options.params.facets[facet_index];
        //var step = cterm.step;
        var hist = field.history;
        if(!hist) hist = [];
        var step = hist.length+1;
        var iscurrent = false;
        
        var currval = field.selectedvalue?field.selectedvalue.value:null;
        
        var f_link = $("<a>",{href:'#', facet_index:facet_index, facet_value:cterm.value, facet_label:cterm.text, step:step}).addClass("facet_link")
        
        if(top.HEURIST4.util.isempty(cterm.value)){
            $("<span>").addClass("ui-icon ui-icon-arrowreturnthick-1-w").appendTo(f_link);    
        }else{
            var f_link_content = $("<span>").text(cterm.text).appendTo(f_link);    
            
            if(!top.HEURIST4.util.isempty(currval)){
                iscurrent = (currval == cterm.value);
                if(iscurrent || 
                    (currval.length==2 && 
                     currval.substr(1,1)=='%' && currval.substr(0,1)==cterm.value.substr(0,1)) )
                {
                     f_link_content.css({ 'font-weight': 'bold', 'font-size':'1.1em', 'font-style':'normal' })   
                }
            
            }
        }
        
        if(cterm.count>0){
            $("<span>").text(" ("+cterm.count+")").appendTo(f_link);
        }
        
        if(!iscurrent){ 

        var that = this;

        this._on( f_link, {
            click: function(event) { 

                var link = $(event.target).parent();
                var facet_index = Number(link.attr('facet_index'));
                var value = link.attr('facet_value');                  
                var label = link.attr('facet_label');                  
                var step = link.attr('step');
                
                var field = this.options.params.facets[facet_index];
                
                if(top.HEURIST4.util.isempty(value)){
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
    }




});