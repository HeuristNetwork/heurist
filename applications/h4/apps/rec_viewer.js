/**
* View record
* it loads data gradually : public, private, relation, links
*/
$.widget( "heurist.rec_viewer", {

  // default options
  options: {
    recID: null,
    recdata: null
  },

  // the constructor
  _create: function() {

    var that = this;

    this.div_toolbar = $( "<div>" )
            .css({'width': '100%'})
            .appendTo( this.element )
            .hide();
    
    this.action_buttons = $('<div>')
            .css('display','inline-block')
            .rec_actions({actionbuttons: "tags,share,more"})
            .appendTo(this.div_toolbar);
    
    this.btn_edit = $( "<button>", {text: top.HR('edit')} )
            .css('float','right')
            .appendTo( this.div_toolbar )
            .button({icons: {
                        primarty: "ui-icon-pencil"
                    },text:true});    
                    
    this._on( this.btn_edit, {
        click: function() {
              if(!top.HEURIST.editing){
                    top.HEURIST.editing = new hEditing();
              }
              top.HEURIST.editing.edit(this.options.recdata);
        }
    });
                    
                    
    
    this.div_content = $( "<div>" )
        .css({'left':0,'right':0,'overflow-y':'auto','padding':'0.2em','position':'absolute','top':'5em','bottom':0})
        .appendTo( this.element ).hide();

    this.lbl_message = $( "<label>" )
        .html('select record')
        .appendTo( this.element ).hide();

    //-----------------------
    $(this.document).on(top.HAPI.Event.ON_REC_SELECT,
        function(e, data) {

            var _recID;
            if( (typeof data.isA == "function") && data.isA("hRecordSet") ){
                if(data.length()>0){
                    var _recdata = data;
                    var _rec = _recdata.getFirstRecord();
                    _recID = _recdata.fld(_rec, 'rec_ID'); //_rec[2];
                    that.options.recID = _recID;
                    that.options.recdata = _recdata;
                    that._refresh();
                    
                    that.action_buttons.rec_actions('option','record_ids',[_recID]);
                    //that.option("recdata", _recdata);
                }
            /*
            }else if( top.HEURIST.util.isArray(data) ) {
                _recID = data[0];
            }else {
                _recID = data;
            */
            }


            //that.options("recID", _recID);
        });

    this._refresh();

    this.element.on("myOnShowEvent", function(event){
        if( event.target.id == that.element.attr('id')){
            that._refresh();
        }
    });

  }, //end _create

/*
  _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
  },
*/  

  /* private function */
  _refresh: function(){

         if ( this.element.is(':visible') ) {

          if(this.options.recdata == null){
              
              this.div_toolbar.hide();
              this.div_content.hide();
              this.lbl_message.show();

          }else{
              this.lbl_message.hide();
              this.div_toolbar.show();
              this.div_content.show();

              //reload tags for selected record
              if(this.recIDloaded!=this.options.recID){
                    this.recIDloaded = this.options.recID;
                    this.div_content.empty();
                    //alert('show '+this.options.recID);
                    this._renderHeader(); 

                    var that = this;
                    that.options.user_Tags = {}; //reset
                    //load all tags for current user and this groups with usagecount for current record
                    top.HAPI.RecordMgr.tag_get({recIDs:this.recIDloaded, UGrpID:'all'},
                    function(response) {
                        if(response.status == top.HAPI.ResponseStatus.OK){
                            for(uGrpID in response.data) {
                                if(uGrpID){
                                      that.options.user_Tags[uGrpID] = response.data[uGrpID];  
                                }
                            }
                            that._renderTags();
                        }else{
                            top.HEURIST.util.showMsgErr(response);
                        }
                    });
                  
                  
/* dynamic load of required js                  
                  var that = this;
                  if($.isFunction($('body').editing_input)){
                    this._renderHeader();
                  }else{
                    $.getScript(top.HAPI.basePath+'js/editing_input.js', function(){ 
                        $.getScript(top.HAPI.basePath+'apps/rec_search.js',
                        function(){ 
                            $.getScript(top.HAPI.basePath+'apps/rec_relation.js',
                                function(){ 
                                    that._renderHeader(); 
                                });
                        });
                    } );
                  }
*/                  
              }
          }

      }

  },

  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    this.element.off("myOnShowEvent");
    $(this.document).off(top.HAPI.Event.ON_REC_SELECT);

    this.lbl_message.remove();
    
    this.btn_edit.remove();

    this.action_buttons.remove();
    this.div_toolbar.remove();
    this.div_content.remove();
  },
  
  _renderTags: function() {
      
      var $fieldset = $("<fieldset>").css('font-size','0.9em').appendTo(this.div_content);
      
      //if(top.HAPI.currentUser.usr_Tags)
      if(this.options.user_Tags)
      {
          
            var groups = top.HAPI.currentUser.usr_GroupsList
            
            //groups.unshift(34);
            groups[top.HAPI.currentUser.ugr_ID] = [ "admin", top.HR('Personal Tags')];

            for (var idx in groups)
            {
                if(idx){
                    var groupID = idx;
                    var groupName = groups[idx][1];
                    
                    var tags = this.options.user_Tags[groupID]; //top.HAPI.currentUser.usr_Tags[groupID];
                    var tags_list = "";
                    
                    var tagID;
                    for(tagID in tags) {
                        var tag = tags[tagID];
                        if(tag && tag[2]>0){
                            tags_list = tags_list + "<a href='#' "+(top.HEURIST.util.isempty(tag[1])?"":"title='"+tag[1]+"'")+">"+tag[0]+"</a> ";
                        }
                    }
                    
                    if(tags_list)
                    {
                        var $d = $("<div>").appendTo($fieldset);
                        $( "<div>")
                            .addClass('header')
                            .css('width','150px')
                            .html('<label>'+groupName+'</label>')
                            .appendTo( $d );
                        $( "<div>")
                            .addClass('input-cell')
                            .html(tags_list)
                            .appendTo( $d );
                    }
                    
                }
            }
      }
      
  },

  _renderHeader: function(){

        var recID = this.options.recID;
        var recdata = this.options.recdata;
        var record = recdata.getFirstRecord();
        var rectypes = recdata.getStructures();
        
        var rectypeID = recdata.fld(record, 'rec_RecTypeID');
        if(!rectypes || rectypes.length==0){
            rectypes = top.HEURIST.rectypes;
        }

        var rfrs = rectypes.typedefs[rectypeID].dtFields;
        var fi = rectypes.typedefs.dtFieldNamesToIndex;

        //header: rectype and title
        var $header = $('<div>')
                .css({'padding':'0.4em', 'border-bottom':'solid 1px #6A7C99'})
                //.addClass('ui-widget-header ui-corner-all')
                .appendTo(this.div_content);

        $('<h2>' + recdata.fld(record, 'rec_Title') + '</h2>')
                .appendTo($header);
                
        $('<div>')
            .append( $('<img>',{
                    src:  top.HAPI.basePath+'assets/16x16.gif',
                    title: '@todo rectypeTitle'.htmlEscape()
                })
                .css({'background-image':'url('+ top.HAPI.iconBaseURL + rectypeID + '.png)','margin-right':'0.4em'}))
            .append('<span>'+(rectypes ?rectypes.names[rectypeID]: 'rectypes not defined')+'</span>') 
            .appendTo($header);          
          
        //media content   @todo
      
      
        var order = rectypes.dtDisplayOrder[rectypeID];
        if(order){      

            // main fields
            var i, l = order.length;
            
            var $fieldset = $("<fieldset>").css('font-size','0.9em').appendTo(this.div_content);
      
            for (i = 0; i < l; ++i) {
                var dtID = order[i];
                if (values=='' ||
                    rfrs[dtID][fi['rst_RequirementType']] == 'forbidden' ||
                   (top.HAPI.has_access(  recdata.fld(record, 'rec_OwnerUGrpID') )<0 &&
                    rfrs[dtID][fi['rst_NonOwnerVisibility']] == 'hidden' )) //@todo: server not return hidden details for non-owner 
                {
                        continue;
                }

                var values = recdata.fld(record, dtID);
                
                if( (rfrs[dtID][fi['dty_Type']])=="separator" || !values) continue;
                
                var isempty = true;
                $.each(values, function(idx,value){ 
                        if(!top.HEURIST.util.isempty(value)){ isempty=false; return false; } 
                } );
                if(isempty) continue;
                

                $("<div>").editing_input(
                          {
                            recID: recID,
                            rectypeID: rectypeID,
                            dtID: dtID,
                            rectypes: rectypes,
                            values: values,
                            readonly: true
                          })
                          .appendTo($fieldset);

            }                
        }//order

  }

});
