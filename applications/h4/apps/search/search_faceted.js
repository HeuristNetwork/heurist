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



NOTE - to make search fo facet value faster we may try to omit current search in query and search for entire database
            
*/

$.widget( "heurist.search_faceted", {

    // default options
    options: {
        // callbacks
        params: {},
        ispreview: false,
        onclose: null
    },

    cached_counts:[],

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   
        
        if(!this.options.ispreview){
            this.div_title = $("<div style='text-align:center;height:1.8em'></div").appendTo( this.element );
        }

        this.div_toolbar = $( "<div>" ).css({"font-size":"0.7em","height":"2.4em","text-align":"center"}).appendTo( this.element );

        this.btn_reset = $( "<button>", { text: top.HR("Initial state") })
        .appendTo( this.div_toolbar )
        .button();

        this.btn_save = $( "<button>", { text: top.HR("Save state") })
        .appendTo( this.div_toolbar )
        .button();

        this.btn_close = $( "<button>", { text: top.HR("Close") })
        .appendTo( this.div_toolbar )
        .button({icons: {secondary: "ui-icon-close"}});

        //Ian this.div_toolbar.buttonset();

        this._on( this.btn_reset, { click: "doReset" });
        this._on( this.btn_save, { click: "doSaveSearch" });
        this._on( this.btn_close, { click: "doClose" });


        this.facets_list_container = $( "<div>" )
        .css({"top":"3.6em","bottom":0,"left":0,"right":4,"position":"absolute"})
        .appendTo( this.element );

        this.facets_list = $( "<div>" )
        .css({"font-size":"0.9em","overflow-y":"auto","height":"100%"})
        .appendTo( this.facets_list_container );



        //this._refresh();
        this.doReset();

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
                this.btn_save.show(); 
            }else{
                this.btn_reset.hide()   
                this.btn_save.hide(); 
            }
            
            this.btn_close.show(); 
        }

        this.doRender();
        this.doSearch();
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        if(this.div_title) this.div_title.renove();
        this.cached_counts = null;
        this.btn_close.remove();
        this.btn_save.remove();
        this.btn_reset.remove();
        this.div_toolbar.remove();

        this.facets_list.remove();
        this.facets_list_container.remove();
    }

    //Methods specific for this widget---------------------------------

    //reset current search 
    ,doReset: function(){

        var facets = this.options.params.facets;

        if(top.HEURIST4.util.isArrayNotEmpty(facets)){
            var facet_index, len = facets.length;

            for (facet_index=0;facet_index<len;facet_index++){
                facets[facet_index][0].currentvalue = null;
                facets[facet_index][0].history = [];
            }
            this._refresh();
        }
    }

    ,doSaveSearch: function(){
        //alert("@todo");
    }

    ,doClose: function(){
        this._trigger( "onclose");
    }

    ,getQuery: function(){

        var full_query = '';

        // by field types     
        var facets = this.options.params.facets;
        var facet_index, i, len = facets.length;

        for (facet_index=0;facet_index<len;facet_index++){
            var currentvalue = facets[facet_index][0].currentvalue;
            var type = facets[facet_index][0].type;

            if(!top.HEURIST4.util.isnull(currentvalue)){

                var cv = currentvalue.query;

                var this_query = '';
                var len2 = facets[facet_index].length;
                for (i=0;i<len2;i++){

                    if(i==0){
                        this_query = facets[facet_index][i].query+':"'+cv.split(" ").join("+")+'"';  //workaround until understand how to regex F:("AA BB CC") on server side
                        //}else if (i==len2-1){
                        //    this_query = facets[facet_index][i].query+':"('+this_query+')"';
                    }else{
                        this_query = facets[facet_index][i].query+':('+this_query+')';
                    }
                }

                facets[facet_index][0].comb_query = this_query;

                full_query = full_query + ' ' + this_query;                 
            }
        }

        if(this.options.params.rectypes.length==1 || 
            (this.options.params.rectype_as_facets && top.HEURIST4.util.isnull(facets[0][0].currentvalue))) {
            //if(full_query=='' && len>0){
            full_query = "t:"+this.options.params.rectypes.join(",")+' '+full_query; //facets[0][facets[0].length-1].query.split(' ')[0];
        }

        return full_query;
    }


    ,doRender: function(){

        var listdiv = this.facets_list;
        listdiv.empty();

        var facets = this.options.params.facets;

        if(top.HEURIST4.util.isArrayNotEmpty(facets)){

            var detailtypes = top.HEURIST4.detailtypes.typedefs;
            var facet_index, i, len2 = facets.length;
            var current_query = this.getQuery();
            var facet_requests = [];
            /* Multicoloured version
            var colors = ["#A2A272","#9E65B3","#AC9EA0","#C57152","#87AE94",
            "#9184EC","#AD6676","#EEA179",//"#EDEFF0",
            "#D7A4D9","#CDD0D3","#ED9366","#CBDCDA", //"#C1F7C6",
            "#435E53","#472A71","#754E48","#6F354A","#3D635A",
            "#C7CC92","#DCB0D9","#CDA1AF","#F2BFA6","#AFCFA3"];
            */
            /* Plain colour version TODO: Need to make this a configurable setting not hardcoded*/
            $color="#A2A272";
            var colors = [$color,$color,$color,$color,$color,
                $color,$color,$color,//"#EDEFF0",
                $color,$color,$color,$color, //"#C1F7C6",
                $color,$color,$color,$color,$color,
                $color,$color,$color,$color,$color];

            //debug - current query
            //DENUG $('<div>').html(current_query).appendTo(listdiv);
            var clr_index = 0;

            for (facet_index=0;facet_index<len2;facet_index++){

                //get title
                var title = '';
                var queries = [];
                for (i=0;i<facets[facet_index].length;i++){
                    title = "<div class='truncate'>"+facets[facet_index][i].title+'</div> '+title;      
                }
                var type = facets[facet_index][0].type;  //deepest value
                var fieldid = facets[facet_index][0].fieldid;
                var query = facets[facet_index][0].query;
                var currentvalue = facets[facet_index][0].currentvalue;    //current query

                //add ui                
                var $facetdiv = $('<div>').appendTo(listdiv);
                var $facet_header = $("<h4>")    // ui-state-default ui-accordion-header-active ui-state-active ui-accordion-header
                .addClass('ui-corner-top ui-header ui-accordion-header ui-state-active')   // ui-header ui-state-active                                                        Math.floor(Math.random() / colors.length)
                .css({"padding":"0.5em 0.5em 0.5em 0.7em","margin-top":"2px"}).appendTo($facetdiv); //,"color":"#FFF","background-color":colors[clr_index]

                clr_index++;
                if(clr_index>=colors.length){ clr_index=0;}

                var $facet_values = $("<div>", {"id":"fv-"+facet_index})
                .addClass('ui-widget-content ui-corner-bottom')
                .css({"padding":"0.5em 0.5em 0.5em 0.7em","min-height":"20px", "border-top": "0 none"})
                .css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white20.gif) no-repeat center center')
                .appendTo($facetdiv);
                $facet_header.html( title );

                //var queries = facets[facet_index][i].query;
                if(type=="enum"){ //} || type=="relationtype"){
                    //find first level of terms


                    //top.HEURIST4.detailtypes
                    var dtID = fieldid.substring(2);

                    if(Number(dtID)>0 && detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']]=='enum'){
                        //fill terms
                        var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                        disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];

                        var term = top.HEURIST4.util.getChildrenTerms(type, allTerms, disabledTerms, currentvalue?currentvalue.termid:null );

                        if(!top.HEURIST4.util.isnull(term.id)){ //not first level

                            var that = this;
                            function __renderParent(cterm, $before){

                                cterm = (cterm.parent) ?cterm.parent:{ id:null, text:top.HR('all'), termssearch:[] };    

                                var f_link = that._createTermLink(facet_index, {id:cterm.id, text:cterm.text, query:cterm.termssearch.join(",")});

                                var $span = $("<span>").css('display','inline-block').append(f_link)
                                .append($('<span class="ui-icon ui-icon-carat-1-e" />')
                                    .css({'display':'inline-block','height':'13px'}));
                                $span.insertBefore($before);
                                //$span.before($before);

                                if(cterm.id){
                                    __renderParent(cterm, $span)
                                }
                            }

                            var $before = $("<span>",{'title':term.termssearch.join(",")}).css({'display':'inline-block'}).append(term.text);
                            $before.appendTo($facet_values);
                            __renderParent(term, $before);
                        }


                        /*if(!top.HEURIST4.util.isnull(term.id)){ //not first level
                        var termssearch = term.termssearch;
                        title = title + " <br>search:"+term.termssearch.join(",");
                        }*/

                        if(term.children.length>0){

                            var prms = { q:current_query, w:this.options.params.domain, type:type, facet_index:facet_index, term:term };
                            var len = facets[facet_index].length;
                            prms.level0 = facets[facet_index][len-1].fieldid; //top level fieldid  f:XXX
                            if(len>1){
                                prms.level1 = facets[facet_index][len-2].query;
                                if(len>2){
                                    prms.level2 = facets[facet_index][len-3].query;
                                }
                            }

                            facet_requests.push(prms);
                        }else{
                            $facet_values.css('background','none');
                        }
                    }

                    //}else if(type=="rectype"){

                }else {

                    //$facet_values.html(type);
                    /*
                    if(top.HEURIST4.util.isnull(currentvalue)){

                    var prms = { q:current_query, w:this.options.params.domain, type:type, facet_index:facet_index};
                    var len = facets[facet_index].length;
                    prms.level0 = facets[facet_index][len-1].fieldid; //top level fieldid  f:XXX
                    if(len>1){
                    prms.level1 = facets[facet_index][len-2].query;
                    if(len>2){
                    prms.level2 = facets[facet_index][len-3].query;
                    }
                    }
                    if(type=="rectype"){
                    prms.level0 = "rectype";
                    }else{
                    prms.dt = facets[facet_index][len-1].fieldid;
                    }

                    facet_requests.push(prms);
                    */    
                    var steps = 0;
                    var k, len = facets[facet_index][0].history.length;    

                    if(!top.HEURIST4.util.isnull(currentvalue)){
                        $facet_values.css('background','none');

                        /*  WORKING!!!
                        var cterm = { text:top.HR('all'), 
                        query:null, 
                        count:0 };    
                        var f_link = this._createFacetLink(facet_index, cterm);
                        $("<span>").css('display','inline-block').append(f_link)
                        .append($('<span class="ui-icon ui-icon-carat-1-e" />')
                        .css({'display':'inline-block','height':'13px'}))
                        .appendTo($facet_values);
                        $("<span>",{'title':currentvalue.query }).css({'display':'inline-block'}).append(currentvalue.text).appendTo($facet_values);
                        */
                        /*
                        var that = this;
                        function __renderParent2(cvalue, $before){

                        steps++;
                        cvalue = (cvalue.parent) ?cvalue.parent:{ parent:null, text:top.HR('all'), query:null };    
                        var f_link = that._createFacetLink(facet_index, cvalue);

                        var $span = $("<span>").css('display','inline-block').append(f_link)
                        .append($('<span class="ui-icon ui-icon-carat-1-e" />')
                        .css({'display':'inline-block','height':'13px'}));
                        $span.insertBefore($before);

                        if(cvalue.parent){
                        __renderParent2(cvalue.parent, $span)
                        }
                        }

                        var $before = $("<span>",{'title':currentvalue.query}).css({'display':'inline-block'}).append(currentvalue.text);
                        $before.appendTo($facet_values);
                        __renderParent2(currentvalue, $before);
                        $facet_values.append($("<br>"));
                        */     
                        for (k=0;k<len;k++){
                            var cvalue = facets[facet_index][0].history[k];
                            var f_link = this._createFacetLink(facet_index, cvalue);
                            var $span = $("<span>").css('display','inline-block').append(f_link).appendTo($facet_values);
                            if(k<len){
                                $span.append($('<span class="ui-icon ui-icon-carat-1-e" />').css({'display':'inline-block','height':'13px'}));
                            }
                        }
                        //$("<span>",{'title':currentvalue.query }).css({'display':'inline-block'}).append(currentvalue.text).appendTo($facet_values);
                    }

                    if( (type=="rectype" && len==0) || len<3 ){ // top.HEURIST4.util.isnull(currentvalue)){
                        var prms = { q:current_query, w:this.options.params.domain, type:type, facet_index:facet_index, step:len-1};
                        var len = facets[facet_index].length;
                        prms.level0 = facets[facet_index][len-1].fieldid; //top level fieldid  f:XXX
                        if(len>1){
                            prms.level1 = facets[facet_index][len-2].query;
                            if(len>2){
                                prms.level2 = facets[facet_index][len-3].query;
                            }
                        }
                        if(type=="rectype"){
                            prms.level0 = "rectype";
                        }else{
                            prms.dt = facets[facet_index][len-1].fieldid;
                        }

                        facet_requests.push(prms);
                    }


                }

            }   //for facet_index

            this._getFacets(facet_requests);
        }

    }

    //
    // workaround: jQuery ajax does not properly in the loop - success callback does not work often   
    //
    ,_getFacets: function(requests){
        if(top.HEURIST4.util.isArrayNotEmpty(requests)) {

            var request = requests.shift();  

            var term = request.term;
            request.term = null;
            var that = this;

            function __onResponse(response){

                if(response.status == top.HAPI4.ResponseStatus.OK){

                    that.cached_counts.push(response);

                    var facet_index = parseInt(response.facet_index); 
                    var j,i;
                    var $facet_values = $("#fv-"+facet_index);
                    $facet_values.css('background','none');

                    if(response.type=="enum"){

                        var terms_usage = response.data; //0-id, 1-cnt

                        var terms_cnt = {};

                        for (j=0; j<terms_usage.length; j++){
                            //var termid = terms_usage[j].shift();
                            terms_cnt[terms_usage[j][0]] = terms_usage[j][1];
                        }

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
                                var f_link = that._createTermLink(facet_index, {id:cterm.id, text:cterm.text, query:cterm.termssearch.join(","), count:cnt});
                                $("<div>").css({"display":"inline-block","min-width":"90px","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            }
                        }
                    }else if(response.type=="rectype"){

                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            if(facet_index>=0){
                                var rtID = cterm[0];
                                var f_link = that._createFacetLink(facet_index, 
                                    {text:top.HEURIST4.rectypes.names[rtID], query:rtID, count:cterm[1]});
                                $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            }
                        }

                    }else if(response.type=="float" || response.type=="integer" || response.type=="date" || response.type=="year"){

                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            if(facet_index>=0){
                                var f_link = that._createFacetLink(facet_index, {text:cterm[0], query: cterm[2] , count:cterm[1]});
                                $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                            }
                        }

                    }else{
                        for (i=0;i<response.data.length;i++){
                            var cterm = response.data[i];

                            if(facet_index>=0){
                                var f_link = that._createFacetLink(facet_index, {text:cterm[0], query:cterm[2], count:cterm[1]});
                                $("<div>").css({"display":"inline-block","padding":"0 3px"}).append(f_link).appendTo($facet_values);
                                if(i>50){
                                    $("<div>").css({"display":"inline-block","padding":"0 3px"}).html('more '+(response.data.length-i)+' results').appendTo($facet_values);
                                    break;       
                                }
                            }
                        }
                    }

                    if($facet_values.is(':empty')){
                        $("<span>").text(top.HR('no values')).css({'font-style':'italic'}).appendTo($facet_values);
                    }

                    that._getFacets(requests);

                }else{
                    top.HEURIST4.util.showMsgErr(response.message);
                }
            };            

            //try to find in cache
            for (var k=0; k<this.cached_counts.length; k++){
                if( parseInt(this.cached_counts[k].facet_index) == request.facet_index && 
                    this.cached_counts[k].q == request.q && this.cached_counts[k].dt == request.dt){
                    __onResponse(this.cached_counts[k]);
                    return;
                }
            }

            window.HAPI4.RecordMgr.get_facets(request, __onResponse);            

        }    
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

    ,doSearch : function(){

        if(!this.options.ispreview){

            var qsearch = this.getQuery();
            var qname = this.options.query_name;
            var domain = this.options.params.domain;

            //this.options.searchdetails
            var request = {q: qsearch, w: domain, f: "map", source:this.element.attr('id'), qname:qname};
            //get hapi and perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));

        }

    }


});