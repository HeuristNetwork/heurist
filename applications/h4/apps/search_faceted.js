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
      this.doRender();
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

                        var cv = currentvalue;
                        if(type=="freetext"){
                             cv = cv.query;
                        }
                    
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
               full_query = facets[0][0].query.split(' ')[0];
           }
           
           return full_query;
  }

  ,doRender: function(){
      
       var listdiv = this.facets_list;
       listdiv.empty();
       
       var facets = this.options.params.facets;

       if(top.HEURIST.util.isArrayNotEmpty(facets)){
           
           var detailtypes = top.HEURIST.detailtypes.typedefs;
           var facet_index, i, len = facets.length;
           
           //debug
           $('<div>').html(this.getQuery()).appendTo(listdiv);
            
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
                var $facet_header = $("<div>").addClass('ui-widget-header ui-corner-top').appendTo($facetdiv);
                var $facet_values = $("<div>", {"id":"fv-"+facet_index}).appendTo($facetdiv);
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
                    
                         var term = top.HEURIST.util.getChildrenTerms(type, allTerms, disabledTerms, currentvalue);

                         if(!top.HEURIST.util.isnull(term.id)){ //not first level
                         
                                var that = this;
                                function __getParent(cterm, $before){
                                    
                                    cterm = (cterm.parent) ?cterm.parent:{ id:null, text:top.HR('all') };    
                                    
                                    var f_link = that._createTermLink(facet_index, cterm);
                                    
                                    var $span = $("<span>").css('display','inline-block').append(f_link).append($('<span class="ui-icon ui-icon-carat-1-e" />').css('display','inline-block'));
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

                         //create links for child terms                         
                         for (i=0;i<term.children.length;i++){
                                var cterm = term.children[i];
                                var f_link = this._createTermLink(facet_index, cterm);
                                $("<div>").append(f_link).appendTo($facet_values);
                         }
                    }
                    
                }else {
                    
                   //$facet_values.html(type);
                   if(top.HEURIST.util.isnull(currentvalue)){
                   
                       var rectypes = query.split(' ')[0].trim().substr(2);
                       var that = this;
                       
                           window.HAPI.RecordMgr.get_facets({rt:rectypes, dt:fieldid, type:type, facet_index:facet_index}, function(response){
                        if(response.status == top.HAPI.ResponseStatus.OK){
                            
                             for (i=0;i<response.data.length;i++){
                                    var cterm = response.data[i];
                                    var facet_index = parseInt(response.facet_index); 
                                    if(facet_index>=0){
                                        var f_link = that._createFacetLink(facet_index, {text:cterm[0], query:cterm[0]+'%', count:cterm[1]});
                                        $("<div>").append(f_link).appendTo($("#fv-"+facet_index));
                                    }
                             }
                                
                            
                        }else{
                            top.HEURIST.util.showMsgDlg(response.message);
                        }
                       });
                   }else{
                       
                       var cterm = { text:top.HR('all'), query:null, count:0 };    
                       var f_link = this._createFacetLink(facet_index, cterm);
                       $("<span>").css('display','inline-block').append(f_link).append($('<span class="ui-icon ui-icon-carat-1-e" />').css('display','inline-block')).appendTo($facet_header);
                       $("<span>",{'title':currentvalue.query }).css({'display':'inline-block'}).append(currentvalue.text).appendTo($facet_header);
                       
                   }
                   
                   
                }
                
           }
       }
        
  }
  
  ,_createTermLink : function(facet_index, cterm){
      
            var f_link = $("<a>",{href:'#', facet_idx:facet_index, facet_value:cterm.id}).text(cterm.text);
         
            this._on( f_link, {
                click: function(event) { 
                  var link = $(event.target);
                  var facet_index = Number(link.attr('facet_idx'));
                  var term_id = link.attr('facet_value');                  
                  if(top.HEURIST.util.isempty(term_id)) term_id = null;
                  //change to new 
                  this.options.params.facets[facet_index][0].currentvalue = term_id;
                  this._refresh();
                  this.doSearch();
                  
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
                  //change to new 
                  this._refresh();
                  this.doSearch();
                  
                  return false;
                }
            });
            
            return f_link;
  }
  
  ,doSearch : function(){
      
      var qsearch = this.getQuery();
      var qname = this.options.query_name;
      var domain = this.options.params.domain;
      
      //this.options.searchdetails
      var request = {q: qsearch, w: domain, f: "map", orig:'saved', qname:qname};
      //get hapi and perform search
      top.HAPI.RecordMgr.search(request, $(this.document));
      
  }
  
  
});