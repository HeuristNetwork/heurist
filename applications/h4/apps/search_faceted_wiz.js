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
      params: {} //description of faceted search
    
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
    
    //select rectypes
    this.step1 = $("<div>").appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("1. Select rectypes that will be used in search"))).appendTo(this.step1);
                //.css({overflow: 'none !important', width:'100% !important'})
    this.step1.rectype_manager({ isdialog:false, isselector:true });
                
    this.step_panels.push(this.step1);

    //select field types                
    this.step2 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").html(top.HR("2. Select fields that act as facet")).appendTo(this.step2);
    $("<div>",{id:'field_treeview'}).appendTo(this.step2);
    this.step_panels.push(this.step2);

    //options
    this.step3 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("3. Define ranges for numeric and date facets"))).appendTo(this.step3);
    this.step_panels.push(this.step3);

    //ranges
    this.step4 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("4. Options"))).appendTo(this.step4);
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
      }else if(newstep>3){
            newstep = 3;  
      }  
      if(this.step != newstep){
          
          if(this.step==0 && newstep==1){
              //load field types
              var rectypeIds = this.step1.rectype_manager("option","selection");
              if(rectypeIds.length<1){
                   
                   top.HEURIST.util.showMsgDlg(top.HR("Select record type"));
                   return;
              }
              //load list of field types
              this.initFieldTreeView(rectypeIds);
          }else if(this.step==1 && newstep==2){

              var tree = this.find('#field_treeview').fancytree("getTree");
              
              var fieldIds = tree.getSelectedNodes(false);
              
          }
          
            this.step_panels[this.step].css('display','none');
            this.step = newstep;
            this.step_panels[this.step].css('display','block');
      }
      
  }

  , initFieldTreeView: function(rectypeIds){
      
       if(rectypeIds && rectypeIds.length>0){
           /*if(!this.options.params.rectypes || 
              !($(rectypeIds).not(this.options.params.rectypes).length == 0 && 
                $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
           {
               
               this.options.params.rectypes = rectypeIds;
               var treediv = this.find('#field_treeview');
              
                window.HAPI.SystemMgr.get_defs({rectypes: this.options.params.rectypes.join() , mode:4}, function(response){
                    if(response.status == top.HAPI.ResponseStatus.OK){
                        
                        //@todo - mark selected
                        if(!treediv.is(':empty')){
                            treediv.fancytree("destroy");
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
