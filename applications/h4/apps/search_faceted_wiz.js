/**
*  Wizard to define new faceted search
*  Steps:
*  1. Options
*  2. Select rectypes (use rectype_manager)
*  3. Select fields (recTitle, numeric, date, terms, pointers, relationships)
*  4. Define ranges for date and numeric fields
*  5. Preview
*  6. Save into database
*/
$.widget( "heurist.search_faceted_wiz", {

  // default options
  options: {
      svsID: null,
      domain: null, // bookmark|all or usergroup ID
      params: {},
      onsave: null
  },
  //params:
  // domain
  // isadvanced
  // rectype_as_facets
  // fieldtypes:[] //allowed field types besides enum amd resource
  //  rectypes:[]
  //  facets:[[{ title:node.title, type: freetext|enum|integer|relmarker, query: "t:id f:id", fieldid: "f:id", currentvalue:{text:label, query:value} }, ]  
  
  
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
                                width: 500,
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
                                    {text:top.HR('Next'), id:'btnNext',
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
    $("<div>").append($("<h4>").html(top.HR("Options"))).appendTo(this.step0);
    $("<div>",{id:'facets_options'}).appendTo(this.step0);
    this.step_panels.push(this.step0);
    
    
    //select rectypes
    this.step1 = $("<div>")
                .css({'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("Select rectypes that will be used in search"))).appendTo(this.step1);
                //.css({overflow: 'none !important', width:'100% !important'})
    this.step1.rectype_manager({ isdialog:false, isselector:true });
                
    this.step_panels.push(this.step1);

    //select field types                
    this.step2 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").html(top.HR("Select fields that act as facet")).appendTo(this.step2);
    $("<div>",{id:'field_treeview'}).appendTo(this.step2);
    this.step_panels.push(this.step2);

    //ranges
    this.step3 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("Define ranges for numeric and date facets"))).appendTo(this.step3);
    $("<div>",{id:'facets_list'}).appendTo(this.step3);
    this.step_panels.push(this.step3);

    //preview
    this.step4 = $("<div>")
                .css({overflow: 'none !important', width:'100% !important', 'display':'none'})
                .appendTo(this.element);
    $("<div>").append($("<h4>").html(top.HR("Preview"))).appendTo(this.step4);
    $("<div>",{id:'facets_preview'}).css('top','3.2em').appendTo(this.step4);
    this.step_panels.push(this.step4);
      
    this._refresh();

  }, //end _create

  // Any time the widget is called with no arguments or with only an option hash, 
  // the widget is initialized; this includes when the widget is created.
  _init: function() {
  },
  
  //Called whenever the option() method is called
  //Overriding this is useful if you can defer processor-intensive changes for multiple option change
  /*
  _setOptions: function( options ) {
  },*/
  
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
    this.step1.hide();
    this.step2.hide();
    this.step3.hide();
    this.step4.hide();
    this.navigateWizard(NaN); //init for 0 step
  }
  
  , navigateWizard: function(nav){
      //@todo - validate
      
      var newstep = 0;
      if(isNaN(nav)){
          this.step = NaN;
      }else{
          newstep = this.step + nav;
      }
      
      if(newstep<0){
            newstep = 0; 
      }else if(newstep>4){
            if(newstep==5){
                //save into database
                this._doSaveSearch();
                return;
            }
            newstep = 4;  
      } else{
           $("#btnNext").button('option', 'label', top.HR('Next'));
      }
      if(this.step != newstep){
          
          if(isNaN(this.step) && newstep==0){ //select record types
          
              var that = this;
              var $dlg = this.step0.find("#facets_options");
              if($dlg.html()==''){
                  $dlg.load("apps/svs_edit_faceted.html?t=9", function(){
                            that._initStep0_options();
                  });
              }else{
                  this._initStep0_options();
              }

          }else if(this.step==0 && newstep==1){ //select record types 
              
              this.options.params.isadvanced = this.step0.find("#opt_mode_advanced").is(":checked");
              this.options.params.rectype_as_facets = this.step0.find("#opt_rectype_as_facets").is(":checked");
              
              if(this.options.params.isadvanced){

                this.step1.rectype_manager({selection:this.options.params.rectypes});                  
                
              }else{
                  this.step = 1; 
                  newstep = 2; //skip step
              }
          }
          
          if(this.step==1 && newstep==2){ //select field types
          
              var rectypeIds = null;
              if(this.options.params.isadvanced){
                    rectypeIds = this.step1.rectype_manager("option","selection");
              }else{
                    rectypeIds = [this.step0.find("#opt_rectypes").val()];
              }
              //mandatory
              if(!top.HEURIST.util.isArrayNotEmpty(rectypeIds)){
                   top.HEURIST.util.showMsgDlg(top.HR("Select record type"));
                   
                   this.step = (this.options.params.isadvanced)?1:0;
                   return;
              }else{
                  this.step0.hide();
              }
                
              //load field types
              allowed = ['enum'];
              var $dlg = this.step0.find("#facets_options");
              if($dlg.find("#opt_use_freetext").is(":checked")){
                   allowed.push('freetext');
              }
              if($dlg.find("#opt_use_date").is(":checked")){
                  allowed.push('year');
                  allowed.push('date');
              }
              if($dlg.find("#opt_use_numeric").is(":checked")){
                  allowed.push('integer');
                  allowed.push('float');
              }
              this.options.params.fieldtypes = allowed;
              
              //load list of field types
              this._initStep2_FieldTreeView(rectypeIds);
                   
          }  if(this.step==2 && newstep==3){  //set ranges
              
              if(!this._initStep3_FacetsRanges()){
                  return;
              }
              
          }else if(this.step==3 && newstep==4){ //preview
              
              this._initStep4_FacetsPreview();
              $("#btnNext").button('option', 'label', top.HR('Save'));
              
          }
          
          if(this.step==2 && newstep==1 && !this.options.params.isadvanced){
              newstep = 0;
          }
          
          this._showStep(newstep);
      }
      
  }
  
  , _showStep :function(newstep){
      
            if(this.step>=0) this.step_panels[this.step].css('display','none');
            this.step = newstep;
            this.step_panels[this.step].css('display','block');
  }

  
  /**
  * Assign values to UI input controls
  */
  , _initStep0_options: function( ){

      var $dlg = this.step0;
      if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            
            var opt_numeric = $dlg.find('#opt_use_numeric');
            var opt_date = $dlg.find('#opt_use_date');
            var opt_text = $dlg.find('#opt_use_freetext');
            var opt_rectypes = $dlg.find("#opt_rectypes").get(0);
            var opt_mode = $dlg.find("input[name='opt_mode']");

            var opt_mode_simple = $dlg.find("#opt_mode_simple");
            var opt_mode_advanced = $dlg.find("#opt_mode_advanced");
            
            if($(opt_rectypes).is(':empty')){
                top.HEURIST.util.createRectypeSelect( opt_rectypes, null, null);

             this._on( opt_mode, {
                click: function(e){
                        if($(e.target).val()=="true"){
                            $dlg.find('.simple-mode').hide();
                            $dlg.find('.advanced-mode').show();
                        }else{
                            $dlg.find('.simple-mode').show();
                            $dlg.find('.advanced-mode').hide();
                            if(this.options.params.rectypes) $(opt_rectypes).val(this.options.params.rectypes[0]);
                        }
                }
                }); 
                
            }
            
            var svsID = this.options.svsID;

            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
               var svs = top.HAPI.currentUser.usr_SavedSearch[svsID];
               svs_id.val(svsID);
               svs_name.val(svs[0]);
               this.options.params = $.parseJSON(svs[1]);
               this.options.domain = this.options.params.domain;

               svs_ugrid.val(svs[2]==top.HAPI.currentUser.ugr_ID ?this.options.domain:svs[2]);
               svs_ugrid.parent().hide();

               var ft = this.options.params.fieldtypes;
               opt_numeric.attr( "checked", ft.indexOf('integer') );
               opt_date.attr( "checked", ft.indexOf('date') );
               opt_text.attr( "checked", ft.indexOf('freetext') );
               
               this.step0.find("#opt_rectype_as_facets").attr( "checked", this.options.params.rectype_as_facets );
               
               opt_mode_advanced.attr("checked", this.options.params.isadvanced );
               opt_mode_advanced.click();
            
            }else{ //add new saved search

                svs_id.val('');
                svs_name.val('');

                //fill with list of user groups in case non bookmark search
                var selObj = svs_ugrid.get(0); //select element
                top.HEURIST.util.createUserGroupsSelect(selObj, top.HAPI.currentUser.usr_GroupsList,
                            [{key:'bookmark', title:top.HR('My Bookmarks')}, {key:'all', title:top.HR('All Records')}],
                            function(){
                                svs_ugrid.val(top.HAPI.currentUser.ugr_ID);
                            });
                svs_ugrid.val(this.options.domain);
                svs_ugrid.parent().show();

                opt_numeric.attr( "checked", true );
                opt_date.attr( "checked", true );
                opt_text.attr( "checked", true );
                
                opt_mode_simple.attr("checked", (this.options.params.isadvanced) );
                opt_mode_simple.click();
            }
      }
  }
  
  // 2d step - init fieldtreeview
  , _initStep2_FieldTreeView: function(rectypeIds){
      
       if(top.HEURIST.util.isArrayNotEmpty(rectypeIds)){
           /*if(!this.options.params.rectypes || 
              !($(rectypeIds).not(this.options.params.rectypes).length == 0 && 
                $(this.options.params.rectypes).not(rectypeIds).length == 0))*/
           {
               
               this.options.params.rectypes = rectypeIds;
               var treediv = $(this.step2).find('#field_treeview');
              
                window.HAPI.SystemMgr.get_defs({rectypes: this.options.params.rectypes.join() , mode:4, fieldtypes:this.options.params.fieldtypes.join() }, function(response){
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
                                selectMode: 3,  // hierarchical multi-selection    
                                source: response.data.rectypes,
                                beforeSelect: function(event, data){
                                    // A node is about to be selected: prevent this, for folder-nodes:
                                    if( data.node.hasChildren() ){
                                        return false;
                                    }
                                },                                
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
  , _initStep3_FacetsRanges: function() {
    
              var listdiv = $(this.step3).find("#facets_list");
              listdiv.empty();
      
              var facets = [];
              var k, len = this.options.params.rectypes.length;
              var isOneRoot = (this.options.params.rectypes.length==1);
              //if more than one root rectype each of them acts as facet
              if(this.options.params.rectype_as_facets && len>1){
                  facets.push([{ title:top.HR('Record types'), type:"rectype", query:"t" }]);
                   /*for (k=0;k<len;k++){
                        var rtID = this.options.params.rectypes[k];
                        facets.push([{ title:top.HEURIST.rectypes.names[rtID], type:"rectype", query:"t:"+rtID, fieldid:rtID }]);
                   }*/
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
                                //root rectypes will be added only once - when full_query is creating
                                // q = "t:"+parent.key+" "+q; 
                            }
                            return [{ title:(node.data.name?node.data.name:node.title), type:node.data.type, query: q, fieldid:node.key }]; 
                      }
                  }else{
                      fieldnode = parent;
                  }
                  
                  var q = node.key;
                  if(q=="recTitle") q = "title"
                  else if(q=="recModified") q = "modified"
                  else if(type=="relmarker") q = "f:relmarker";
                  if(fieldnode.data.rt_ids){ //constrained
                        q = "t:"+fieldnode.data.rt_ids+" "+q; 
                  }
                  res.push( { title:(node.data.name?node.data.name:node.title), type:node.data.type, query:q, fieldid:node.key });    
                  
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
                            facets.push( facet ); // { id:node.key, title:node.title, query: squery, fieldid } );
                       }
                  }
                  
                  //draw list
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
  
  //4. show facet search preview
  ,_initStep4_FacetsPreview: function(){
      
       this._defineDomain();

       var listdiv = $(this.step4).find("#facets_preview");
       
        var noptions= { query_name:"test", params:this.options.params, ispreview: true}
        
        if(listdiv.html()==''){ //not created yet
            listdiv.search_faceted( noptions );                    
        }else{
            listdiv.search_faceted('option', noptions ); //assign new parameters
        }
       
  }
  
  ,_defineDomain: function(){
      
            var svs_ugrid = this.step0.find('#svs_UGrpID');
            var svs_ugrid = svs_ugrid.val();
            if(parseInt(svs_ugrid)>0){
                this.options.params.domain = 'all';    
            }else{
                this.options.params.domain = svs_ugrid;    
            }
  }
  
  //
  // save into database
  //
  ,_doSaveSearch:function(){
      
            var $dlg = this.step0;

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var message = $dlg.find('.messages');
            
            var allFields = $dlg.find('input');
            allFields.removeClass( "ui-state-error" );

            var bValid = top.HEURIST.util.checkLength( svs_name, "Name", message, 3, 25 );
            if(!bValid){
                this._showStep(0);
                return false;
            }
            
            var bValid = top.HEURIST.util.isArrayNotEmpty(this.options.params.facets);
            if(!bValid){
                this._showStep(2);
                return false;
            }
            
                    var svs_ugrid = svs_ugrid.val();
                    if(parseInt(svs_ugrid)>0){
                        this.options.params.domain = 'all';    
                    }else{
                        this.options.params.domain = svs_ugrid;    
                        svs_ugrid = top.HAPI.currentUser.ugr_ID;
                    }

                    var request = {svs_Name: svs_name.val(),
                                      svs_Query: JSON.stringify(this.options.params),   //$.toJSON
                                        svs_UGrpID: svs_ugrid,
                                          domain:this.options.params.domain};

                    var isEdit = ( parseInt(svs_id.val()) > 0 );

                    if(isEdit){
                        request.svs_ID = svs_id.val();
                    }

                    var that = this;
                    //
                    top.HAPI.SystemMgr.ssearch_save(request,
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){

                                var svsID = response.data;

                                if(!top.HAPI.currentUser.usr_SavedSearch){
                                    top.HAPI.currentUser.usr_SavedSearch = {};
                                }

                                top.HAPI.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];
                                
                                request.new_svs_ID = svsID;
                                
                                that._trigger( "onsave", null, request );
                                
                                that.element.dialog("close");

                            }else{
                                top.HEURIST.util.redirectToError(response.message);
                            }
                        }

                    );
      
                      
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
                        .appendTo( $('body') );
                manage_dlg.search_faceted_wiz( params );
           }else{
                manage_dlg.search_faceted_wiz('option', params);
           }

           manage_dlg.search_faceted_wiz( "show" );
           
    }
}
