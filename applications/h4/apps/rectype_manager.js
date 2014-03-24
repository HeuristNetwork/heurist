/**
* Rectype manager - list of record types by groups
*
* requires utils.js
*/
$.widget( "heurist.rectype_manager", {

  // default options
  options: {
      isdialog: false, //show in dialog or embedded
      isselector: false, //show in checkboxes to select

      selection:[], 
      
      current_GrpID: null,
      // we take tags from top.HAPI.currentUser.usr_Tags - array of tags in form [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]
      current_order: 1  // order by name
  },

  // the constructor
  _create: function() {

    var that = this;

    this.wcontainer = $("<div>");

    if(this.options.isdialog){

        this.wcontainer
                .css({overflow: 'none !important', width:'100% !important'})
                .appendTo(this.element);
                
        this.element.css({overflow: 'none !important'})                

        this.element.dialog({
                                autoOpen: false,
                                height: 620,
                                width: 400,
                                modal: true,
                                title: top.HR(this.options.isselector?"Select Record types":"Manage Record types"),
                                resizeStop: function( event, ui ) {
                                    that.element.css({overflow: 'none !important','width':'100%'});
                                },
                                  buttons: [
                                    {text:top.HR('Select'),
                                     click: function() {
                                      //that._editSavedSearch();
                                    }},
                                    {text:top.HR('Close'), click: function() {
                                      $( this ).dialog( "close" );
                                    }}
                                  ]
                            });

    }else{
        //.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'500px'})
        this.wcontainer.appendTo( this.element );
    }
    
    //---------------------------------------- HEADER
    // user group selector
    this.select_grp = $( "<select>", {width:'96%'} )
            .addClass("text ui-widget-content ui-corner-all")
            .appendTo( this.wcontainer );
    this._on( this.select_grp, {
      change: function(event) {
         //load tags for this group
         var val = event.target.value; //ugrID
         if(this.options.current_GrpID==val) return;  //the same

         this.options.current_GrpID = val;
         this._refresh();
      }
    });
    
    //---------------------------------------- SEARCH
    this.search_div = $( "<div>").css({ width:'36%', 'display':'inline-block' }).appendTo( this.wcontainer );
    
    this.input_search = $( "<input>", {width:'100%'} )
            .addClass("text ui-widget-content ui-corner-all")
            .appendTo( this.search_div );

    this._on( this.input_search, {
      keyup: function(event) {
          //filter tags
          var tagdivs = $(this.element).find('.recordTitle');
          tagdivs.each(function(i,e){   
                var s = $(event.target).val().toLowerCase();
                $(e).parent().css('display', (s=='' || e.innerHTML.toLowerCase().indexOf(s)>=0)?'block':'none');
          });
      }
    });
    
    //---------------------------------------- ORDER
    this.sort_div = $( "<div>").css({ 'float':'right' }).appendTo( this.wcontainer );
    
    this.lbl_message = $( "<label>").css({'padding-right':'5px'})
        .html(top.HR('Sort'))
        .appendTo( this.sort_div );
    
    this.select_order = $( "<select><option value='1'>"+
    top.HR("by name")+"</option><option value='2'>"+
    top.HR("by usage")+"</option><option value='3'>"+
    top.HR("selected")+"</option></select>", {'width':'80px'} )
    
            .addClass("text ui-widget-content ui-corner-all")
            .appendTo( this.sort_div );
    this._on( this.select_order, {
      change: function(event) {
         var val = Number(event.target.value); //order
         this.options.current_order = val;
         this._renderItems();
      }
    });
    

    //----------------------------------------
    var css1;
    if(this.options.isdialog){
      css1 =  {'overflow-y':'auto','padding':'0.4em','top':'80px','bottom':0,'position':'absolute','left':0,'right':0};
    }else{
      css1 =  {'overflow-y':'auto','padding':'0.4em','top':'80px','bottom':0,'position':'absolute','left':0,'right':0};  
    }
    
    this.div_content = $( "<div>" )
        .addClass('list')
        .css(css1)
        .html('list of record types')
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.wcontainer );

    //-----------------------------------------
    
    this._updateGroups();    

  }, //end _create

  /* private function */
  _refresh: function(){
         this._renderItems();
  },

  
  /* private function */
  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    this.select_grp.remove();

    if(this.div_toolbar){
        this.div_toolbar.remove();
    }

    this.wcontainer.remove();
  },

  //fill selector with groups  
  _updateGroups: function(){
      
    var selObj = this.select_grp.get(0);
    top.HEURIST.util.createRectypeGroupSelect( selObj, top.HR('all groups') );
    
    this.select_grp.val( top.HEURIST.rectypes.groups[0].id);
    this.select_grp.change();
    
  },

  //
  _renderItems: function(){

       if(this.div_content){
            //var $allrecs = this.div_content.find('.recordDiv');
            //this._off( $allrecs, "click");
            this.div_content.empty();  //clear
       }

       var rectypes = [],
           rectypeID, name, usage, is_selected;
       var idx_rty_grpid = top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID;

       for (rectypeID in  top.HEURIST.rectypes.names)
       {
            if( rectypeID && (this.options.current_GrpID==0 || this.options.current_GrpID==top.HEURIST.rectypes.typedefs[rectypeID].commonFields[idx_rty_grpid]) ){
                
                name = top.HEURIST.rectypes.names[rectypeID];
                usage = 0; //  top.HEURIST.rectypes.rtUsage[rectypeID];
                is_selected = this.options.selection.indexOf(rectypeID);

                rectypes.push([rectypeID, name, usage, is_selected]);
            }
       }           
            
       var val = this.options.current_order;
       rectypes.sort(function (a,b){
           if(val==1){
                return a[val].toLowerCase()>b[val].toLowerCase()?1:-1;
           }else{
                return a[val]<b[val]?1:-1;
           }
       });               
                
       var that = this;
       var filter_name = this.input_search.val().toLowerCase();
       var i;
       for(i=0; i<rectypes.length; ++i) {
       
                var rt = rectypes[i];
                rectypeID = rt[0];
                name = rt[1];
                usage = rt[2];
                is_selected = rt[3];
           
                $itemdiv = $(document.createElement('div'));

                $itemdiv
                        .addClass('recordDiv')
                        .attr('id', 'rt-'+rectypeID )
                        .css('display', (filter_name=='' || name.toLowerCase().indexOf(filter_name)>=0)?'block':'none')
                        .appendTo(this.div_content);
                        
                        
                if(this.options.isselector){
                    $('<input>')
                            .attr('type','checkbox')
                            .attr('rtID', rectypeID )
                            .attr('checked', (is_selected>=0) )  //?false:true ))
                            .addClass('recordIcons')
                            .css('margin','0.4em')
                            .click(function(event){
                                var rectypeID = $(this).attr('rtID');
                                var idx = that.options.selection.indexOf(rectypeID);
                                if(event.target.checked){
                                    if(idx<0){
                                        that.options.selection.push(rectypeID);
                                    }
                                }else if(idx>=0){
                                    that.options.selection.splice(idx,1);
                                }
                            })
                            .appendTo($itemdiv);
                }
                
                
                $iconsdiv = $(document.createElement('div'))
                    .addClass('recordIcons')
                    .appendTo($itemdiv);

                //record type icon
                $('<img>',{
                        src:  top.HAPI.basePath+'assets/16x16.gif'
                    })
                    .css('background-image', 'url('+ top.HAPI.iconBaseURL + rectypeID + '.png)')
                    .appendTo($iconsdiv);
                                
                $('<div>',{
                            title: name
                        })
                        .css('display','inline-block')
                        .addClass('recordTitle')
                        .css({top:0,'margin':'0.4em', 'height':'1.4em', 'left':'70px'})
                        .html( name  )
                        .appendTo($itemdiv);
                
                //count - usage    
                $('<div>')
                        .css({'margin':'0.4em', 'height':'1.4em', 'position':'absolute','right':'60px'})
                        .css('display','inline-block')
                        .html( usage )
                        .appendTo($itemdiv);
      }           
  },

 
  show: function(){
    if(this.options.isdialog){
        this.element.dialog("open");
    }else{
        //fill selected value this.element
    }
  }

});

function showManageRecordTypes(){
    
       var manage_dlg = $('#heurist-rectype-dialog');

       if(manage_dlg.length<1){

            manage_dlg = $('<div id="heurist-rectype-dialog">')
                    .appendTo( $('body') )
                    .rectype_manager({ isdialog:true });
       }

       manage_dlg.rectype_manager( "show" );
}
