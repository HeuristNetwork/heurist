/**
*  Wizard to define new faceted search
*  Steps:
*  1. Select rectypes (use rectype_manager)
*  2. Select fields (recTitle, numeric, date, terms, pointers, relationships)
*  3. Options
*  4. Save into database
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
    
    this.div_toolbar = $( "<div>" ).css({"font-size":"0.7em","height":"2.4em","text-align":"center"}).appendTo( this.element );

    this.btn_reset = $( "<button>", { text: top.HR("Reset") })
            .appendTo( this.div_toolbar )
            .button();
            
    this.btn_save = $( "<button>", { text: top.HR("Save") })
            .appendTo( this.div_toolbar )
            .button();

    this.btn_close = $( "<button>", { text: top.HR("Close") })
            .appendTo( this.div_toolbar )
            .button();

    this.div_toolbar.buttonset();
    
    this._on( this.btn_reset, { click: "doReset" });
    this._on( this.btn_save, { click: "doSaveSearch" });
    this._on( this.btn_close, { click: "doClose" });

    
    this.facets_list_container = $( "<div>" )
            .css({"top":"3.2em","bottom":0,"left":0,"right":4,"position":"absolute"})
            .appendTo( this.element );
    
    this.facets_list = $( "<div>" )
             .css({"font-size":"0.9em","overflow-y":"auto","height":"100%"})
             .appendTo( this.facets_list_container );
    

      
    this._refresh();

  }, //end _create

  // Any time the widget is called with no arguments or with only an option hash, 
  // the widget is initialized; this includes when the widget is created.
  _init: function() {
  },
  
  _setOptions: function( options ) {
        this._superApply( arguments );
        this.cached_counts = [];
        this._refresh();
  },
  
  /* 
  * private function 
  * show/hide buttons depends on current login status
  */
  _refresh: function(){
      
      if(this.options.ispreview){
         this.btn_save.hide(); 
         this.btn_close.hide(); 
      }else{
         this.btn_save.show(); 
         this.btn_close.show(); 
      }
      
      this.doRender();
      this.doSearch();
  },
  // 
  // custom, widget-specific, cleanup.
  _destroy: function() {
    // remove generated elements
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

       if(top.HEURIST.util.isArrayNotEmpty(facets)){
           var facet_index, len = facets.length;
           
           for (facet_index=0;facet_index<len;facet_index++){
                facets[facet_index][0].currentvalue = null;
           }
           this._refresh();
       }
  }
  
  ,doSaveSearch: function(){
        alert("@todo");
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
               
                if(!top.HEURIST.util.isnull(currentvalue)){

                        var cv = currentvalue.query;
                    
                        var this_query = '';
                        var len2 = facets[facet_index].length;
                        for (i=0;i<len2;i++){
                            
                            if(i==0){
                                this_query = facets[facet_index][i].query+':'+cv;
                            }else if (i==len2-1){
                                this_query = facets[facet_index][i].query+':"('+this_query+')"';
                            }else{
                                this_query = facets[facet_index][i].query+':('+this_query+')';
                            }
                        }
                        
                        facets[facet_index][0].comb_query = this_query;
                         
                        full_query = full_query + ' ' + this_query;                 
                }
           }
           
           if(this.options.params.rectypes.length==1 || 
             (this.options.params.rectype_as_facets && top.HEURIST.util.isnull(facets[0][0].currentvalue))) {
           //if(full_query=='' && len>0){
               full_query = "t:"+this.options.params.rectypes.join(",")+' '+full_query; //facets[0][facets[0].length-1].query.split(' ')[0];
           }
           
           return full_query;
  }
  

  ,doRender: function(){
      
       var listdiv = this.facets_list;
       listdiv.empty();
       
       var facets = this.options.params.facets;

       if(top.HEURIST.util.isArrayNotEmpty(facets)){
           
           var detailtypes = top.HEURIST.detailtypes.typedefs;
           var facet_index, i, len2 = facets.length;
           var current_query = this.getQuery();
           var facet_requests = [];
           var colors = ["#A2A272","#9E65B3","#AC9EA0","#C57152","#87AE94",
                                   "#9184EC","#AD6676","#EEA179",//"#EDEFF0",
                         "#D7A4D9","#CDD0D3","#ED9366","#CBDCDA", //"#C1F7C6",
                         "#435E53","#472A71","#754E48","#6F354A","#3D635A",
                         "#C7CC92","#DCB0D9","#CDA1AF","#F2BFA6","#AFCFA3"];
           
           //debug - current query
           $('<div>').html(current_query).appendTo(listdiv);
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
                var currentvalue = facets[facet_index][0].currentvalue;
                
                //add ui                
                var $facetdiv = $('<div>').appendTo(listdiv);
                var $facet_header = $("<h4>")
                        .addClass('ui-corner-top')   // ui-header ui-state-active                                                        Math.floor(Math.random() / colors.length)
                        .css({"padding":"0.5em 0.5em 0.5em 0.7em","margin-top":"2px","color":"#FFF",
                            "background-color":colors[clr_index]}).appendTo($facetdiv);
                            
                clr_index++;
                if(clr_index>=colors.length){ clr_index=0;}
                            
                var $facet_values = $("<div>", {"id":"fv-"+facet_index})
                         .addClass('ui-widget-content ui-corner-bottom')
                         .css({"padding":"0.5em 0.5em 0.5em 0.7em","min-height":"20px"})
                         .css('background','url('+top.HAPI.basePath+'assets/loading-animation-white20.gif) no-repeat center center')
                         .appendTo($facetdiv);
                $facet_header.html( title );
                
                //var queries = facets[facet_index][i].query;
                if(type=="enum"){ //} || type=="relationtype"){
                    //find first level of terms
                
                    
                    //top.HEURIST.detailtypes
                    var dtID = fieldid.substring(2);
                    
                    if(Number(dtID)>0 && detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']]=='enum'){
                         //fill terms
                         var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                         disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];
                    
                         var term = top.HEURIST.util.getChildrenTerms(type, allTerms, disabledTerms, currentvalue?currentvalue.termid:null );

                         if(!top.HEURIST.util.isnull(term.id)){ //not first level
                         
                                var that = this;
                                function __getParent(cterm, $before){
                                    
                                    cterm = (cterm.parent) ?cterm.parent:{ id:null, text:top.HR('all'), termssearch:[] };    
                                    
                                    var f_link = that._createTermLink(facet_index, {id:cterm.id, text:cterm.text, query:cterm.termssearch.join(",")});
                                    
                                    var $span = $("<span>").css('display','inline-block').append(f_link)
                                            .append($('<span class="ui-icon ui-icon-carat-1-e" />')
                                            .css({'display':'inline-block','height':'13px'}));
                                    $span.insertBefore($before);
                                    //$span.before($before);
                                    
                                    if(cterm.id){
                                        __getParent(cterm, $span)
                                    }
                                }
                                
                                var $before = $("<span>",{'title':term.termssearch.join(",")}).css({'display':'inline-block'}).append(term.text);
                                $before.appendTo($facet_values);
                                __getParent(term, $before);
                         }

                         
                         /*if(!top.HEURIST.util.isnull(term.id)){ //not first level
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
                   if(top.HEURIST.util.isnull(currentvalue)){
                   
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

                   }else{
                       $facet_values.css('background','none');
                       
                       var cterm = { text:top.HR('all'), 
                                query:null, 
                                count:0 };    
                       var f_link = this._createFacetLink(facet_index, cterm);
                       $("<span>").css('display','inline-block').append(f_link)
                                .append($('<span class="ui-icon ui-icon-carat-1-e" />')
                                .css({'display':'inline-block','height':'13px'}))
                                .appendTo($facet_values);
                       $("<span>",{'title':currentvalue.query }).css({'display':'inline-block'}).append(currentvalue.text).appendTo($facet_values);
                       
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
       if(top.HEURIST.util.isArrayNotEmpty(requests)) {
           
            var request = requests.shift();  
            
            var term = request.term;
            request.term = null;
            var that = this;
            
            function __onResponse(response){
                
                                if(response.status == top.HAPI.ResponseStatus.OK){

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
                                                    $("<div>").css({"display":"inline-block","min-width":"90px","padding-right":"6px"}).append(f_link).appendTo($facet_values);
                                                }
                                         }
                                    }else if(response.type=="rectype"){
                                        
                                        for (i=0;i<response.data.length;i++){
                                            var cterm = response.data[i];
                                            
                                            if(facet_index>=0){
                                                var rtID = cterm[0];
                                                var f_link = that._createFacetLink(facet_index, 
                                                        {text:top.HEURIST.rectypes.names[rtID], query:rtID, count:cterm[1]});
                                                $("<div>").css({"display":"inline-block","padding-right":"6px"}).append(f_link).appendTo($facet_values);
                                            }
                                        }
                                        
                                    }else if(response.type=="float" || response.type=="integer"){
                                        
                                        for (i=0;i<response.data.length;i++){
                                            var cterm = response.data[i];
                                            
                                            if(facet_index>=0){
                                                var f_link = that._createFacetLink(facet_index, {text:cterm[0], query: cterm[0].replace(' ~ ','<>') , count:cterm[1]});
                                                $("<div>").css({"display":"inline-block","padding-right":"6px"}).append(f_link).appendTo($facet_values);
                                            }
                                        }
                                     
                                    }else{
                                        for (i=0;i<response.data.length;i++){
                                            var cterm = response.data[i];
                                            
                                            if(facet_index>=0){
                                                var f_link = that._createFacetLink(facet_index, {text:cterm[0], query:cterm[0]+'%', count:cterm[1]});
                                                $("<div>").css({"display":"inline-block","padding-right":"6px"}).append(f_link).appendTo($facet_values);
                                            }
                                        }
                                    }
                                    
                                    if($facet_values.is(':empty')){
                                        $("<span>").text(top.HR('no values')).css({'font-style':'italic'}).appendTo($facet_values);
                                    }
                                    
                                    that._getFacets(requests);
                             
                                }else{
                                    top.HEURIST.util.showMsgErr(response.message);
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
            
            window.HAPI.RecordMgr.get_facets(request, __onResponse);            
        
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
                  if(top.HEURIST.util.isempty(value)){
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
      
            var f_link = $("<a>",{href:'#', facet_idx:facet_index, facet_value:cterm.query, facet_label:cterm.text}).addClass("facet_link")
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
                  if(top.HEURIST.util.isempty(value)){
                      this.options.params.facets[facet_index][0].currentvalue = null;
                  }else{
                      this.options.params.facets[facet_index][0].currentvalue = {text:label, query:value};    
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
          var request = {q: qsearch, w: domain, f: "map", orig:'saved', qname:qname};
          //get hapi and perform search
          top.HAPI.RecordMgr.search(request, $(this.document));
      
      }
      
  }
  
  
});