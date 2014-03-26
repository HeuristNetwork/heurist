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
            .css({"top":"2.4em","bottom":0,"left":0,"right":4,"position":"absolute"})
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
      
           var facets = this.options.params.facets;
           var facet_index, i, len = facets.length;
           var full_query = '';
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
           if(full_query=='' && len>0){
               full_query = facets[0][facets[0].length-1].query.split(' ')[0];
           }
           
           return full_query;
  }
  
  ,getQueryForCount: function(facet){

            var this_query = '';
            var len2 = facet.length;
            for (i=0;i<len2;i++){
                
                if(i==0){
                    this_query = facet[i].query; //+':'+cv;
                }else if (i==len2-1){
                    this_query = facet[i].query+':"('+this_query+')"';
                }else{
                    this_query = facet[i].query+':('+this_query+')';
                }
            }
            return this_query; 
      
  }

  ,doRender: function(){
      
       var listdiv = this.facets_list;
       listdiv.empty();
       
       var facets = this.options.params.facets;

       if(top.HEURIST.util.isArrayNotEmpty(facets)){
           
           var detailtypes = top.HEURIST.detailtypes.typedefs;
           var facet_index, i, len = facets.length;
           var current_query = this.getQuery();
           var facet_requests = [];
           
           //debug
           $('<div>').html(current_query).appendTo(listdiv);
            
           for (facet_index=0;facet_index<len;facet_index++){
               
                //get title
                var title = '';
                var queries = [];
                for (i=0;i<facets[facet_index].length;i++){
                       title = title + ' ' + facets[facet_index][i].title;      
                }
                var type = facets[facet_index][0].type;
                var fieldid = facets[facet_index][0].fieldid;
                var query = facets[facet_index][0].query;
                var currentvalue = facets[facet_index][0].currentvalue;

                //add ui                
                var $facetdiv = $('<div>').appendTo(listdiv);
                var $facet_header = $("<h3>")
                        .addClass('ui-widget-header ui-state-active ui-corner-top')
                        .css({"padding":"0.5em 0.5em 0.5em 0.7em","margin-top":"2px"}).appendTo($facetdiv);
                var $facet_values = $("<div>", {"id":"fv-"+facet_index})
                         .addClass('ui-widget-content ui-corner-bottom')
                         .css({"padding":"0.5em 0.5em 0.5em 0.7em"}).appendTo($facetdiv);
                $facet_header.html( title+"<br />" );
                
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
                                $before.appendTo($facet_header);
                                __getParent(term, $before);
                         }

                         
                         /*if(!top.HEURIST.util.isnull(term.id)){ //not first level
                            var termssearch = term.termssearch;
                            title = title + " <br>search:"+term.termssearch.join(",");
                         }*/

                         if(term.children.length>0){
                             
                               var prms = { q:current_query, w:this.options.params.domain, type:type, facet_index:facet_index, term:term};
                               if(facets[facet_index].length>1){
                                    prms.resource = facets[facet_index][0].query;  // t:5 f:25
                               }
                               prms.dt = facets[facet_index][facets[facet_index].length-1].fieldid;
                             
                               facet_requests.push(prms);
                         }
                    }
                    
                }else {
                    
         
                   //$facet_values.html(type);
                   if(top.HEURIST.util.isnull(currentvalue)){
                   
                       var prms = { q:current_query, w:this.options.params.domain, type:type, facet_index:facet_index};
                       if(facets[facet_index].length>1){
                            prms.resource = facets[facet_index][0].query;  // t:5 f:25
                       }
                       prms.dt = facets[facet_index][facets[facet_index].length-1].fieldid;
                       
                       facet_requests.push(prms);

                   }else{
                       
                       var cterm = { text:top.HR('all'), query:null, count:0 };    
                       var f_link = this._createFacetLink(facet_index, cterm);
                       $("<span>").css('display','inline-block').append(f_link)
                                .append($('<span class="ui-icon ui-icon-carat-1-e" />')
                                .css({'display':'inline-block','height':'13px'}))
                                .appendTo($facet_header);
                       $("<span>",{'title':currentvalue.query }).css({'display':'inline-block'}).append(currentvalue.text).appendTo($facet_header);
                       
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
                            window.HAPI.RecordMgr.get_facets(request, function(response){
                                if(response.status == top.HAPI.ResponseStatus.OK){
                                    
                                    if(request.type=="enum"){
                                        
                                        var terms_usage = response.data; //0-id, 1-cnt
                                        var facet_index = parseInt(response.facet_index); 
                                        var j,i;
                                        
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
                                                    $("<div>").append(f_link).appendTo($("#fv-"+facet_index));
                                                }
                                         }
                                     
                                    }else{
                                        for (i=0;i<response.data.length;i++){
                                            var cterm = response.data[i];
                                            var facet_index = parseInt(response.facet_index); 
                                            if(facet_index>=0){
                                                var f_link = that._createFacetLink(facet_index, {text:cterm[0], query:cterm[0]+'%', count:cterm[1]});
                                                $("<div>").css({"display":"inline-block","padding-right":"6px"}).append(f_link).appendTo($("#fv-"+facet_index));
                                            }
                                        }
                                    }
                                    that._getFacets(requests);
                             
                                }else{
                                    top.HEURIST.util.showMsgDlg(response.message);
                                }
                               });            
        
       }    
  }
  
  /**
  * cterm {query: text: count:}
  */
  ,_createTermLink : function(facet_index, cterm){
      
            var f_link = $("<a>",{href:'#', facet_idx:facet_index, facet_value:cterm.query, facet_label:cterm.text, termid:cterm.id}).text(cterm.text+(cterm.count>0?" ("+cterm.count+")":""));
         
            this._on( f_link, {
                click: function(event) { 
                  var link = $(event.target);
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
      
            var f_link = $("<a>",{href:'#', facet_idx:facet_index, facet_value:cterm.query, facet_label:cterm.text }).text(cterm.text+(cterm.count>0?" ("+cterm.count+")":""));
         
            this._on( f_link, {
                click: function(event) { 
                  
                  var link = $(event.target);
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