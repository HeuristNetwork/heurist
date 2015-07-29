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

$.widget( "heurist.search_faceted2", {

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

    // the widget's constructor
    _create: function() {

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   
        
        if(!this.options.ispreview){
            this.div_title = $("<div style='text-align:center;height:1.8em'></div").appendTo( this.element );
        }

        this.div_toolbar = $( "<div>" ).css({"font-size":"0.7em","height":"2.4em","text-align":"center"}).appendTo( this.element );

        this.btn_submit = $( "<button>", { text: top.HR("Submit") })
        .appendTo( this.div_toolbar )
        .button();
        
        this.btn_reset = $( "<button>", { text: top.HR("Initial state") })
        .appendTo( this.div_toolbar )
        .button();

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
        .css({"overflow-y":"auto","height":"100%"}) //"font-size":"0.9em",
        .appendTo( this.facets_list_container );

        //this._refresh();
        this.doReset();
        
        var that = this;
        
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCH_FINISH, function(e, data) {

            if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){
                if(data && data.queryid() == that._request_id){
                      that._recalculateFacets();       
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
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
         if(this.div_title) this.div_title.html("<b>"+this.options.query_name+"</b>");

         var facets = this.options.params.facets;
         var hasHistory = false, facet_index, len = facets?facets.length:0;
         for (facet_index=0;facet_index<len;facet_index++){
              if(facets[facet_index][0].currentvalue!=null){
                  hasHistory = true;
                  break;
              }
         }
        

        if(this.options.ispreview){
            this.btn_save.hide(); 
            this.btn_close.hide(); 
        }else{
            
            if(hasHistory) {
                this.btn_reset.show()   
                //this.btn_save.show();  //@todo
            }else{
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
    //
    ,_createFacetQueries: function(){
       
       
       $.each(this.options.params.facets, function(index, field){
       
           if(field['var'] && field['code'] && field['isfacet']){
               //create new query and add new parameter
               var code = field['code'];
               code = code.split(':');
               
               function __crt( idx ){
                   var qp = null;
                   if(idx>0){  //this relation or link
                       
                        var pref = '';
                        qp = {};
                        
                        qp['t'] = code[idx];
                        var fld = code[idx-1]; //link field
                        if(fld.indexOf('lf')==0){
                            pref = 'linked_to';    
                        }else if(fld.indexOf('lt')==0){
                            pref = 'linkedfrom';    
                        }else if(fld.indexOf('rf')==0){
                            pref = 'related_to';    
                        }else if(fld.indexOf('rt')==0){
                            pref = 'relatedfrom';    
                        }
                        qp[pref+':'+fld.substr(2)] = __crt(idx-2);    
                   }else{ //this is simple field
                       qp = '$IDS'; //{'ids':'$IDS}'};
                   }
                   return qp;
               }
               
               field['facet'] = __crt( code.length-2 );
               
           }
       });
       
   }
    
    //reset current search 
    ,doReset: function(){
/* ART2907
        var facets = this.options.params.facets;

        if(top.HEURIST4.util.isArrayNotEmpty(facets)){
            var facet_index, len = facets.length;

            for (facet_index=0;facet_index<len;facet_index++){
                facets[facet_index][0].currentvalue = null;
                facets[facet_index][0].history = [];
            }
            this._refresh();
        }
*/     

       // create list of queries to search facet values 
       this._createFacetQueries();
       
       this.facets_list.empty();
       
       var $fieldset = $("<fieldset>").css({'font-size':'0.9em','background-color':'white'}).addClass('fieldset_search').appendTo(this.facets_list);
       
       var that = this;
       
       that._input_fields = {};
       
       $.each(this.options.params.facets, function(idx, field){
       
           //content_id+"_"+
           
           if(field['var']){
                var inpt = $("<div>",{id: "fv_"+field['var'] }).editing_input(   //this is our widget for edit given fieldtype value
                    {
                        varid: field['var'],  //content_id+"_"+
                        recID: -1,
                        rectypeID: field['rtid'],
                        dtID: field['id'],
                        rectypes: top.HEURIST4.rectypes,
                        values: '',
                        readonly: false,
                        title: field['title'],
                        showclear_button: false,
                        detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
                });

                inpt.appendTo($fieldset);
                that._input_fields['$X'+field['var']] = inpt;
           }
       });
   
    }

    ,doSaveSearch: function(){
        //alert("@todo");
    }

    ,doClose: function(){
        this._trigger( "onclose");
    }


    ,doRender: function(){


    }


    /**
    * cterm {query: text: count:}
    */
    ,_createTermLink : function(facet_index, cterm){

        var f_link = $("<a>",{href:'#', facet_idx:facet_index, facet_value:cterm.query, facet_label:cterm.text, termid:cterm.id}).addClass("facet_link")
        $("<span>").text(cterm.text).appendTo(f_link);
        if(cterm.count>0){
            $("<span>").text(" ("+cterm.count+")").appendTo(f_link);
        } 

        this._on( f_link, {
            click: function(event) { 
                var link = $(event.target).parent();
                var facet_index = Number(link.attr('facet_idx'));
                var value = link.attr('facet_value');
                var label = link.attr('facet_label');                  
                var termid = link.attr('termid');                  
                if(top.HEURIST4.util.isempty(value)){
                    this.options.params.facets[facet_index][0].currentvalue = null;
                } else {
                    this.options.params.facets[facet_index][0].currentvalue = {termid:termid, text:label, query:value}; 
                }

                this._refresh();

                return false;
            }
        });

        return f_link;
    }

    // cterm - {text, query, count}
    ,_createFacetLink : function(facet_index, cterm){

        var step = cterm.step;
        var hist = this.options.params.facets[facet_index][0].history;
        var step = hist.length+1;

        var f_link = $("<a>",{href:'#', facet_index:facet_index, facet_value:cterm.query, facet_label:cterm.text, step:step}).addClass("facet_link")
        $("<span>").text(cterm.text).appendTo(f_link);
        if(cterm.count>0){
            $("<span>").text(" ("+cterm.count+")").appendTo(f_link);
        } 


        this._on( f_link, {
            click: function(event) { 

                var link = $(event.target).parent();
                var facet_index = Number(link.attr('facet_index'));
                var value = link.attr('facet_value');                  
                var label = link.attr('facet_label');                  
                var step = link.attr('step');
                if(top.HEURIST4.util.isempty(value)){
                    this.options.params.facets[facet_index][0].currentvalue = null;
                    this.options.params.facets[facet_index][0].history = [];
                }else{
                    var currentvalue = {text:label, query:value, step:step};

                    //replace/add for current step and remove that a bigger
                    if(hist.length==0){
                        hist.push({text:top.HR('all'), query:null});
                    }else{
                        hist = hist.slice(0,step);
                    }

                    this.options.params.facets[facet_index][0].currentvalue = currentvalue
                    hist.push(currentvalue);    
                }
                //
                this._refresh();

                return false;
            }
        });

        return f_link;
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

            var query = JSON.parse(JSON.stringify( this.options.params.qa )); //clone 
            var isform_empty = this._fillQueryWithValues(query);
            
            if(isform_empty){
                top.HEURIST4.util.showMsgErr('Define at least one search criterion');
                return;
            }
            
            this._request_id =  Math.round(new Date().getTime() + (Math.random() * 100));
            
            this.query_request = request = {qa: query, w: this.options.params.domain, 
                            f: 'map', 
                            source:this.element.attr('id'), 
                            qname:this.options.query_name,
                            id: this._request_id 
                            }; //, facets: facets
            
            //perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));
            
            //perform search for facet values
            //that._recalculateFacets(content_id);
    }
    
    // there are 2 ways - search facet values on the server side in the same time whrn main query is performed
    // or search after completion of main search    
    //
    // perform search for facet values and redraw facet fields
    // query - current query - if resultset > 1000, use query
    //
    //
    , _recalculateFacets: function(field_index){
        
        //return;
        
        // this._currentquery
        // this._resultset
        if(!field_index) field_index = -1;

        var i = field_index;
        for(;i< this.options.params.facets.length; i++)
        {
            var field = this.options.params.facets[i];
            if(i>field_index && field['isfacet'] && field['facet']){
                
                
                /*if(false && this._resultset && this._resultset.count_total()>1000){
                    //replace with current query
        
                }else{
                    //replace with list of ids
                }*/

                
                var query = this._setFacetQuery( field['facet'], top.HAPI4.currentRecordset.getIds() );
                
                var request = {qa: query, w: 'a', a:'getfacets_new',
                                     facet_index: i, 
                                     field:  field['id'],
                                     type:   field['type'],
                                     source:this.element.attr('id') }; //, facets: facets


            /* @TODO try to find in cache
            for (var k=0; k<this.cached_counts.length; k++){
                if( parseInt(this.cached_counts[k].facet_index) == request.facet_index && 
                    this.cached_counts[k].q == request.q && this.cached_counts[k].dt == request.dt){
                    __onResponse(this.cached_counts[k]);
                    return;
                }
            }*/

                window.HAPI4.RecordMgr.get_facets(request, _redrawFacets);            
                                
                break;
            }
        }
        
    }
    
    , _redrawFacets: function( response ) {

                if(response.status == top.HAPI4.ResponseStatus.OK){

                    //@TODO this.cached_counts.push(response);
                    var allTerms = top.HEURIST4.terms;
                    
                    var facet_index = parseInt(response.facet_index); 
                    
                    var field = this.options.params.facets[facet_index];
                    
                    var j,i;
                    var $input_div = $("#fv_"+field['var']);
                    //$facet_values.css('background','none');

                    var $facet_values = $input_div.find('.facets');
                    if( $facet_values.length < 1 ){
                        var dd = $input_div.find('.input-cell');
                        $facet_values = $('<div>').addClass('facets').appendTo( $(dd[0]) );
                    }
                    
                    $facet_values.empty();
                    
                    
                    if(field['type']=="enum"){

                        var terms_usage = response.data; //0-id, 1-cnt
                        
                        var terms_cnt = {};

                        for (j=0; j<terms_usage.length; j++){
                            //var termid = terms_usage[j].shift();
                            terms_cnt[terms_usage[j][0]] = terms_usage[j][1];
                            
                            var cterm = allTerms['termsByDomainLookup']['enum'][terms_usage[j][0]];
                            var label = (cterm)?cterm[0]:('term#'+terms_usage[j][0]);
                            
                            $("<div>").css({"display":"inline-block","min-width":"90px","padding":"0 3px"})
                                    .html( label + ' ('+ terms_usage[j][1] +') ' ).appendTo($facet_values);
                        }

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
                    if(field['type']=="rectype"){

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
                    if(field['type']=="float" || response.type=="integer" || response.type=="date" || response.type=="year"){

                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            if(facet_index>=0){
                                var f_link = this._createFacetLink(facet_index, {text:cterm[0], query: cterm[2] , count:cterm[1]});
                                $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            }
                        }

                    }
                    else{
                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            /*
                            if(facet_index>=0){
                                var f_link = this._createFacetLink(facet_index, {text:cterm[0], query:cterm[2], count:cterm[1]});
                                $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                                if(i>50){
                                    $("<div>").css({"display":"inline-block","padding":"0 3px"}).html('more '+(response.data.length-i)+' results').appendTo($facet_values);
                                    break;       
                                }
                            }*/
                            
                            $("<div>").css({"display":"inline-block","min-width":"90px","padding":"0 3px"})
                                    .html(  cterm[0] + ' ('+ cterm[1] +') ' ).appendTo($facet_values);
                            
                        }
                    }

                    if($facet_values.is(':empty')){
                        $("<span>").text(top.HR('no values')).css({'font-style':'italic'}).appendTo($facet_values);
                    }

                    //search next facet
                    this._recalculateFacets( facet_index );

                }else{
                    top.HEURIST4.util.showMsgErr(response.message);
                }
                
                
    }            



});