/**
*  Wizard to define new faceted search
*  Steps:
*  1. Select rectypes (use rectype_manager)
*  2. Select fields (recTitle, numeric, date, terms, pointers, relationships)
*  3. Options
*  4. Save into database
*/
$.widget( "heurist.search_faceted_wiz", {

  // default options
  options: {
      params: {}, //description of faceted search

      
  },
  
  step: 0, //current step
  step_panels:[],

   // the widget's constructor
  _create: function() {

    var that = this;

    // prevent double click to select text
    //this.element.disableSelection();

    // Sets up element to apply the ui-state-focus class on focus.
    //this._focusable($element);   
    
    var that = this;
    
    this.element.css({overflow: 'none !important'})
    
    this.element.dialog({
                                autoOpen: false,
                                height: 620,
                                width: 400,
                                modal: true,
                                title: top.HR("Define Faceted Search"),
                                resizeStop: function( event, ui ) {
                                    that.element.css({overflow: 'none !important','width':'100%'});
                                },
                                  buttons: [
                                    {text:top.HR('Back'),
                                     click: function() {
                                        that.navigateWizard(-1);
                                    }},
                                    {text:top.HR('Next'),
                                     click: function() {
                                        that.navigateWizard(1);
                                    }},
                                    {text:top.HR('Close'), click: function() {
                                        var that_dlg = this;
                                        top.HEURIST.util.showMsgDlg(top.HR("Cancel? Please confirm"),
                                               function(){ $( that_dlg ).dialog( "close" ); });
                                    }}
                                  ]
                            });

    //option
    this.step0 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'block'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("1. Options"))).appendTo(this.step0);
    $("<div>",{id:'facets_options'}).appendTo(this.step0);
    this.step_panels.push(this.step0);
    
    
    //select rectypes
    this.step1 = $("<div>")
                .css({'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("2. Select rectypes that will be used in search"))).appendTo(this.step1);
                //.css({overflow: 'none !important', width:'100% !important'})
    this.step1.rectype_manager({ isdialog:false, isselector:true });
                
    this.step_panels.push(this.step1);

    //select field types                
    this.step2 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").html(top.HR("3. Select fields that act as facet")).appendTo(this.step2);
    $("<div>",{id:'field_treeview'}).appendTo(this.step2);
    this.step_panels.push(this.step2);

    //ranges
    this.step3 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("4. Define ranges for numeric and date facets"))).appendTo(this.step3);
    $("<div>",{id:'facets_list'}).appendTo(this.step3);
    this.step_panels.push(this.step3);

    //preview
    this.step4 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("5. Preview"))).appendTo(this.step4);
    $("<div>",{id:'facets_preview'}).appendTo(this.step4);
    this.step_panels.push(this.step4);
      
    this._refresh();

  }, //end _create

  // Any time the widget is called with no arguments or with only an option hash, 
  // the widget is initialized; this includes when the widget is created.
  _init: function() {
  },
  
  //Called whenever the option() method is called
  //Overriding this is useful if you can defer processor-intensive changes for multiple option change
  _setOptions: function( options ) {
  },
  
  /* 
  * private function 
  * show/hide buttons depends on current login status
  */
  _refresh: function(){

  },
  // 
  // custom, widget-specific, cleanup.
  _destroy: function() {
    // remove generated elements
    this.step0.remove();
    this.step1.remove();
    this.step2.remove();
    this.step3.remove();
    this.step4.remove();
  }
  
  ,show: function(){
    this.element.dialog("open");
  }
  
  , navigateWizard: function(nav){
      //@todo - validate
      
      var newstep = this.step + nav;
      if(newstep<0){
            newstep = 0; 
      }else if(newstep>4){
            newstep = 4;  
      }  
      if(this.step != newstep){
          
          if(this.step==1 && newstep==2){ //select record types
              //load field types
              var rectypeIds = this.step1.rectype_manager("option","selection");
              if(!top.HEURIST.util.isArrayNotEmpty(rectypeIds)){
                   
                   top.HEURIST.util.showMsgDlg(top.HR("Select record type"));
                   return;
              }
              //load list of field types
              this.initFieldTreeView(rectypeIds);
                   
          }else if(this.step==2 && newstep==3){  //set ranges
              
              if(!this.initFacetsList()){
                  return;
              }
              
          }else if(this.step==3 && newstep==4){ //preview
              
              this.initFacetsPreview()
          }
          
            this.step_panels[this.step].css('display','none');
            this.step = newstep;
            this.step_panels[this.step].css('display','block');
      }
      
  }

  // 2d step
  , initFieldTreeView: function(rectypeIds){
      
       if(top.HEURIST.util.isArrayNotEmpty(rectypeIds)){
           /*if(!this.options.params.rectypes || 
              !($(rectypeIds).not(this.options.params.rectypes).length == 0 && 
                $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
           {
               
               this.options.params.rectypes = rectypeIds;
               var treediv = $(this.step2).find('#field_treeview');
              
                window.HAPI.SystemMgr.get_defs({rectypes: this.options.params.rectypes.join() , mode:4}, function(response){
                    if(response.status == top.HAPI.ResponseStatus.OK){
                        
                        //@todo - mark selected
                        if(!treediv.is(':empty')){
                            treediv.fancytree("destroy");
                        }
                        
                        if(response.data.rectypes) {
                            response.data.rectypes[0].expanded = true;
                        }
                        
                        //setTimeout(function(){
                        treediv.fancytree({
                    //            extensions: ["select"],
                                checkbox: true,
                                selectMode: 3,
                                source: response.data.rectypes,
                                select: function(e, data) {
                                    /* Get a list of all selected nodes, and convert to a key array:
                                    var selKeys = $.map(data.tree.getSelectedNodes(), function(node){
                                        return node.key;
                                    });
                                    $("#echoSelection3").text(selKeys.join(", "));

                                    // Get a list of all selected TOP nodes
                                    var selRootNodes = data.tree.getSelectedNodes(true);
                                    // ... and convert to a key array:
                                    var selRootKeys = $.map(selRootNodes, function(node){
                                        return node.key;
                                    });
                                    $("#echoSelectionRootKeys3").text(selRootKeys.join(", "));
                                    $("#echoSelectionRoots3").text(selRootNodes.join(", "));
                                    */
                                },
                                dblclick: function(e, data) {
                                    data.node.toggleSelected();
                                },
                                keydown: function(e, data) {
                                    if( e.which === 32 ) {
                                        data.node.toggleSelected();
                                        return false;
                                    }
                                }
                                // The following options are only required, if we have more than one tree on one page:
                    //          initId: "treeData",
                                //cookieId: "fancytree-Cb3",
                                //idPrefix: "fancytree-Cb3-"
                            });                        
                        //},1000);
                        
                    }else{
                        top.HEURIST.util.redirectToError(response.message);
                    }
            });
            
          }
      }
  }
  
  // 3d step
  , initFacetsList: function() {
    
              var listdiv = $(this.step3).find("#facets_list");
              listdiv.empty();
      
              var facets = [];
              var k, len = this.options.params.rectypes.length;
              var isOneRoot = (this.options.params.rectypes.length==1);
              //if more than one root rectype each of them acts as facet
              if(!isOneRoot){
                  /*for (k=0;k<len;k++){
                       var rtID = this.options.params.rectypes[k];
                       facets.push({ title:top.HEURIST.rectypes.names[rtID], type:"rectype", query: "t:"+this.options.params.rectypes[k] });
                   }
                   */
              }
              

              function __get_queries(node){
                  
                  var res = [];
                  
                  var parent = node.parent; //it may rectype of pointer field
                  var fieldnode;
                  
                  if( parent.data.type=="rectype"){
                      
                      fieldnode = parent.parent
                      if(fieldnode.isRoot()){
                            var q = node.key;
                            if(q=="recTitle") q = "title"
                            else if(q=="recModified") q = "modified";
                            
                            if(isOneRoot){
                                q = "t:"+parent.key+" "+q; 
                            }
                            return [{ title:node.title, type:node.data.type, query: q, fieldid:node.key }]; 
                      }
                  }else{
                      fieldnode = parent;
                  }
                  
                  var q = node.key;
                  if(q=="recTitle") q = "title"
                  else if(q=="recModified") q = "modified";
                  if(fieldnode.data.rt_ids){ //constrained
                        q = "t:"+fieldnode.data.rt_ids+" "+q; 
                  }
                  res.push( { title:node.title, type:node.data.type, query:q, fieldid:node.key });    
                  
                  var res2 = __get_queries(fieldnode);
                  for(var i=0; i<res2.length; i++){
                      res.push(res2[i]);
                  }
                  
                  /*
                  if( parent.data.type=="rectype"){
                       fieldnode = parent.parent;
                      //if rectype find parent field and get rectype constraints
                      if(fieldnode.isRoot()){ //this is top most rectype
                            if(isOneRoot){
                                return [{ title:node.title, type:node.data.type, query:"t:"+parent.key+" "+node.key }]; 
                            }else{
                                return [{ title:node.title, type:node.data.type, query:node.key }]; 
                            }
                      } else { //pointer field
                          if(fieldnode.data.rt_ids){ //constrained
                                res.push( { title:node.title, type:node.data.type, query:"t:"+fieldnode.data.rt_ids+" "+node.key }); 
                          }else{
                                res.push( { title:node.title, type:node.data.type, query:node.key });    
                          }
                          res.push(__get_queries(fieldnode));
                      }
                  }else{ //pointer field
                          fieldnode = parent;
                      
                          if(fieldnode.data.rt_ids){ //constrained
                                res.push( { title:node.title, type:node.data.type, query:"t:"+fieldnode.data.rt_ids+" "+node.key }); 
                          }else{
                                res.push( { title:node.title, type:node.data.type, squery:node.key });    
                          }
                          res.push(__get_queries(fieldnode));
                  }
                  */
                  return res;
              }
              
              var tree = $(this.step2).find('#field_treeview').fancytree("getTree");
              var fieldIds = tree.getSelectedNodes(false);
              len = fieldIds.length;              
              
              if(len>0){
              
                  for (k=0;k<len;k++){
                       var node =  fieldIds[k];      //FancytreeNode
                       //name, type, query,  ranges
                       if(!top.HEURIST.util.isArrayNotEmpty(node.children)){  //ignore top levels selection
                            var facet = __get_queries(node);
                            facets.push( facet ); // { id:node.key, title:node.title, query: squery } );
                       }
                  }
                  
                  len = facets.length; 
                  for (k=0;k<len;k++){
                        
                        var s = facets[k][0].type+'  ';
                        var title = '';
                        for (var i=0;i<facets[k].length;i++){
                            
                            title = title + ' ' + facets[k][i].title;
                            
                            s = s + facets[k][i].query;
                            if(i<facets[k].length-1){
                                s = s + "=>";
                            }
                        }
                        listdiv.append($('<div>').html( title + '  ' + s + '<hr />'));
                  }
              
                  this.options.params.facets = facets;  
              
                  return true;
              }else{
                  return false;
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
                  this.initFacetsPreview();
                  return false;
                }
            });
            
            return f_link;
  }
  
  //show facet search preview
  ,initFacetsPreview: function(){

       var listdiv = $(this.step4).find("#facets_preview");
       listdiv.empty();

       if(top.HEURIST.util.isArrayNotEmpty(this.options.params.facets)){
           
           var facets = this.options.params.facets;
           
           var facet_index, i, len = facets.length; 
           for (facet_index=0;facet_index<len;facet_index++){
               
                //get title
                var title = '';
                var queries = [];
                for (i=0;i<facets[facet_index].length;i++){
                       title = title + ' ' + facets[facet_index][i].title;      
                }
                var type = facets[facet_index][0].type;
                var fieldid = facets[facet_index][0].fieldid;

                //add ui                
                var $facetdiv = $('<div>').appendTo(listdiv);
                var $facet_header = $("<div>").addClass('ui-widget-header').appendTo($facetdiv);
                var $facet_values = $("<div>").appendTo($facetdiv);
                $facet_header.html( title+"<br />" );
                
                //var queries = facets[facet_index][i].query;
                if(type=="enum"){ //} || type=="relationtype"){
                    //find first level of terms
                
                    
                    //top.HEURIST.detailtypes
                    var dtID = fieldid.substring(2);
                    var detailtypes = top.HEURIST.detailtypes.typedefs;
                    
                    if(Number(dtID)>0 && detailtypes[dtID].commonFields[detailtypes.fieldNamesToIndex['dty_Type']]=='enum'){
                         //fill terms
                         var allTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree']],
                         disabledTerms = detailtypes[dtID]['commonFields'][detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs']];
                    
                         var term = top.HEURIST.util.getChildrenTerms(type, allTerms, disabledTerms, facets[facet_index][0].currentvalue);

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
                   $facet_values.html(type);
                }
                
           }
       }
  }
  
});

function showSearchFacetedWizard( params ){
    
    if(!$.isFunction($('body').rectype_manager)){
        $.getScript(top.HAPI.basePath+'apps/rectype_manager.js', function(){ showSearchFacetedWizard(params); } );
    }else if(!$.isFunction($('body').fancytree)){

        $.getScript(top.HAPI.basePath+'ext/fancytree/jquery.fancytree-all.min.js', function(){ showSearchFacetedWizard(params); } );
        
    }else{
        
           var manage_dlg = $('#heurist-search-faceted-dialog');

           if(manage_dlg.length<1){

                manage_dlg = $('<div id="heurist-search-faceted-dialog">')
                        .appendTo( $('body') )
                        .search_faceted_wiz({ params:params });
           }

           manage_dlg.search_faceted_wiz( "show" );
    }
}
