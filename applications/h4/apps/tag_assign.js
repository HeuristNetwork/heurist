/**
* Assign/remove tags to selected
*
* requires utils.js
*/
$.widget( "heurist.tag_assign", {

  // default options
  options: {
      isdialog: false, //show in dialog or embedded

      current_UGrpID: null,
      // we take tags from top.HAPI.currentUser.usr_Tags - array of tags in form [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]

      record_ids: null //array of record ids the tag selection will be applied for
  },

  // the constructor
  _create: function() {

    top.HAPI.currentUser.usr_Tags = {}; //clear all  
     
    var that = this;

    this.wcontainer = $("<div>");

    if(this.options.isdialog){

        this.wcontainer
                .css({overflow: 'none !important', width:'100% !important'})
                .appendTo(this.element);

        this.element.dialog({
                                autoOpen: false,
                                height: 620,
                                width: 400,
                                modal: true,
                                title: top.HR("Manage Tags"),
                                resizeStop: function( event, ui ) {
                                    that.wcontainer.css('width','100%');
                                },
                                  buttons: [
                                    {text:top.HR('Delete'),
                                     title: top.HR("Delete selected tags"),
                                     disabled: 'disabled',
                                     class: 'tags-actions',
                                     click: function() {
                                            that._deleteTag();
                                    }},
                                    {text:top.HR('Merge'),
                                     title: top.HR("Merge selected tags"),
                                     disabled: 'disabled',
                                     class: 'tags-actions',
                                     click: function() {
                                            that._mergeTag();
                                    }},
                                    {text:top.HR('Create'),
                                     title: top.HR("Create new tag"),
                                     click: function() {
                                      that._editTag();
                                    }},
                                    {text:top.HR('Close'), click: function() {
                                      $( this ).dialog( "close" );
                                    }}
                                  ]
                            });

    }else{
        this.wcontainer.addClass('ui-widget-content ui-corner-all').css('padding','0.4em').appendTo( this.element );
    }

    //----------------------------------------
    // user group selector
    this.select_ugrp = $( "<select>", {width:'96%'} )
            .addClass("text ui-widget-content ui-corner-all")
            .appendTo( this.wcontainer );
    this._on( this.select_ugrp, {
      change: function(event) {
         //load tags for this group
         var val = event.target.value; //ugrID
         if(this.options.current_UGrpID==val) return;

         //var that = this;

         if(top.HAPI.currentUser.usr_Tags && top.HAPI.currentUser.usr_Tags[val]){  //already found
             this.options.current_UGrpID = val;
             this._renderTags();
         }else{
             top.HAPI.RecordMgr.tag_get({UGrpID:val, recIDs:this.options.record_ids},
                function(response) {
                    if(response.status == top.HAPI.ResponseStatus.OK){
                        that.options.current_UGrpID = val;
                        if(!top.HAPI.currentUser.usr_Tags){
                            top.HAPI.currentUser.usr_Tags = {};
                        }
                        top.HAPI.currentUser.usr_Tags[val] = response.data;
                        that._renderTags();
                    }else{
                        top.HEURIST.util.showMsgErr(response);
                    }
                });
         }
      }
    });


    //----------------------------------------
    this.input_search = $( "<input>", {width:'96%'} )
            .addClass("text ui-widget-content ui-corner-all")
            .appendTo( this.wcontainer );

    this._on( this.input_search, {
      keyup: function(event) {
          //filter tags
          var tagdivs = $(this.element).find('.recordTitle');
          tagdivs.each(function(i,e){   
                var s = $(event.target).val();
                $(e).parent().css('display', (s=='' || e.innerHTML.indexOf(s)>=0)?'block':'none');
          });
      }
    });
            
    //----------------------------------------
    this.div_content = $( "<div>" )
        .css({'overflow-y':'auto','padding':'0.4em','max-height':'400px'})
        //.css({'left':0,'right':0,'overflow-y':'auto','padding':'0.4em','position':'absolute','top':'2em','bottom':'2em'})
        .html('list of tags')
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.wcontainer );

    //-----------------------------------------

    this.edit_dialog = null;

    if(!this.options.isdialog)
    {
        this.div_toolbar = $( "<div>" )
                    .css({'width': '100%', height:'2.4em'})
                    .appendTo( this.wcontainer );

        this.btn_add = $( "<button>", {
                        text: top.HR("Create"),
                        title: top.HR("Create new tag")
                })
                .appendTo( this.div_toolbar )
                .button();

        this._on( this.btn_add, { click: "_editTag" } );

        this.btn_manage = $( "<button>", {
                        id: 'manageTags',
                        text: top.HR("Manage"),
                        title: top.HR("Manage tags")
                })
                .appendTo( this.div_toolbar )
                .button();

        this._on( this.btn_manage, { click: "_manageTags" } );
        
        this.btn_assign = $( "<button>", {
                        id: 'assignTags',
                        disabled: 'disabled',
                        text: top.HR("Assign"),
                        title: top.HR("Assign selected tags")
                })
                .appendTo( this.div_toolbar )
                .button();

        this._on( this.btn_assign, { click: "_assignTags" } );
        
    }

    // list of groups for current user
    var selObj = this.select_ugrp.get(0);
    top.HEURIST.util.createUserGroupsSelect(selObj, top.HAPI.currentUser.usr_GroupsList,
            [{key:top.HAPI.currentUser.ugr_ID, title:top.HR('Personal')}],
         function(){
                that.select_ugrp.val(top.HAPI.currentUser.ugr_ID);
                that.select_ugrp.change();
         });

  }, //end _create

  _setOption: function( key, value ) {
      this._super( key, value );

      if(key=='record_ids'){
            top.HAPI.currentUser.usr_Tags = {}; //clear all  
            this.options.current_UGrpID = null;
            this.select_ugrp.change();
      }
  },  
  /* private function */
  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    this.input_search.remove();
    this.select_ugrp.remove();

    if(this.edit_dialog){
        this.edit_dialog.remove();
    }

    if(this.div_toolbar){
        this.btn_manage.remove();
        this.btn_assign.remove();
        this.btn_add.remove();
        this.div_toolbar.remove();
    }

    this.wcontainer.remove();
  },

  // [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]
  _renderTags: function(){

       if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            //this._off( $allrecs, "click");
            this.div_content.empty();  //clear
       }

       if(top.HAPI.currentUser.usr_Tags && top.HAPI.currentUser.usr_Tags[this.options.current_UGrpID])
       {
               var that = this;
               var tags = top.HAPI.currentUser.usr_Tags[this.options.current_UGrpID];

               var tagID;
               for(tagID in tags) {
                 if(tagID){

                    var tag = tags[tagID];

                    $tagdiv = $(document.createElement('div'));

                    $tagdiv
                        .addClass('list recordDiv')
                        .attr('id', 'tag-'+tagID )
                        .attr('tagID', tagID )
                        .appendTo(this.div_content);

                    $('<input>')
                            .attr('type','checkbox')
                            .attr('tagID', tagID )
                            .attr('usage', tag[2])
                            .attr('checked', (that.options.isdialog && tag[2])>0?true:false )
                            .addClass('recordIcons')
                            .css('margin','0.4em')
                            .click(function(){
                                
                                if(that.options.isdialog){                                
                                    var checkboxes = $(that.element).find('input:checked'); 
                                    var btns = $('.tags-actions');
                                    if(checkboxes.length>0){
                                        btns.removeAttr('disabled');
                                        btns.removeClass('ui-button-disabled ui-state-disabled');
                                    }else{
                                        btns.addClass('ui-button-disabled ui-state-disabled');
                                        btns.attr('disabled','disabled');
                                    }
                                } else {
                                    var btn = $('#assignTags');
                                    //find checkbox that has usage>0 and unchecked 
                                    // and vs  usage==0 and checked
                                    var t_added = $(that.element).find('input[type="checkbox"][usage="0"]:checked');
                                    var t_removed = $(that.element).find('input[type="checkbox"][usage!="0"]:not(:checked)');
                                    if(t_added.length>0 || t_removed.length>0){
                                        btn.removeAttr('disabled');
                                        btn.removeClass('ui-button-disabled ui-state-disabled');
                                    }else{
                                        btn.addClass('ui-button-disabled ui-state-disabled');
                                        btn.attr('disabled','disabled');
                                    }
                                    
                                }
                                //alert(this.checked);
                            })
                            //.css({'display':'inline-block', 'margin-right':'0.2em'})
                            .appendTo($tagdiv);
                            

                    $('<div>',{
                            title: tag[1]
                        })
                        .css('display','inline-block')
                        .addClass('recordTitle')
                        .css({'margin':'0.4em', 'height':'1.4em'})
                        .html( tag[0] )
                        .appendTo($tagdiv);

                    //count - usage    
                    $('<div>')
                            //.addClass('recordIcons')
                            .css({'margin':'0.4em', 'height':'1.4em', 'position':'absolute','right':'60px'})
                            .css('display','inline-block')
                            .html( tag[2] )
                            .appendTo($tagdiv);
                        
                    if(this.options.isdialog){

                        
                         //$tagdiv.find('.recordTitle').css('right','36px');

                         $tagdiv
                          .append( $('<div>')
                              .addClass('edit-delete-buttons')
                              .css('margin','0.4em 1.2em')
                              .append( $('<div>', { tagID:tagID, title: top.HR('Edit tag') })
                                    .button({icons: {primary: "ui-icon-pencil"}, text:false})
                                    .click(function( event ) {
                                        that._editTag( $(this).attr('tagID') );
                                    }) )
                              .append($('<div>',{ tagID:tagID, title: top.HR('Delete tag') })
                                    .button({icons: {primary: "ui-icon-close"}, text:false})
                                    .click(function( event ) {
                                        that._deleteTag( $(this).attr('tagID') );
                                    }) )
                          );
                    }


                 }
               }

               /*$allrecs = this.div_content.find('.recordDiv');
               this._on( $allrecs, {
                        click: this._recordDivOnClick
               });*/
       }

  },

  /**
  * Remove given tag
  *
  * @param tagID
  */
  _deleteTag: function(tagID){

        var tagIDs = [];
        if(tagID){     
                var tag = top.HAPI.currentUser.usr_Tags[this.options.current_UGrpID][tagID];
                if(!tag) return;
                tagIDs.push(tagID);
        }else{
                var checkboxes = $(this.element).find('input:checked'); 
                checkboxes.each(function(i,e){ tagIDs.push($(e).attr('tagID')); });
        }
        if(tagIDs.length<1) return;
        
        var request = {ids: tagIDs.join(',')};
        var that = this;
        
        top.HEURIST.util.showMsgDlg("Delete? Please confirm", function(){
        
                    top.HAPI.RecordMgr.tag_delete(request,
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){

                                $.each(tagIDs, function(i,e){
                                    //remove from UI
                                    $('#tag-'+e).remove();
                                    //remove from
                                    delete top.HAPI.currentUser.usr_Tags[that.options.current_UGrpID][e];
                                });

                            }else{
                                top.HEURIST.util.showMsgErr(response);
                            }
                        }

                    );
        }, "Confirmation");
  },

 /**
  * Assign values to UI input controls
  */
  _fromDataToUI: function(tagID){

      var $dlg = this.edit_dialog;
      if($dlg){
            $dlg.find('.messages').empty();

            var tag_id = $dlg.find('#tag_ID');
            var tag_name = $dlg.find('#tag_Text');
            var tag_desc = $dlg.find('#tag_Description');

            var isEdit = (parseInt(tagID)>0);

            if(isEdit){
               var tag = top.HAPI.currentUser.usr_Tags[this.options.current_UGrpID][tagID];
               tag_id.val(tagID);
               tag_name.val(tag[0]);
               tag_desc.val(tag[1]);
            }else{ //add new saved search
                $dlg.find('input').val('');
                tag_name.val(this.input_search.val());
            }
      }
  },

  /**
  * Show dialogue to add/edit tag
  */
  _editTag: function(tagID){

    if(  this.edit_dialog==null )
    {
        var that = this;
        var $dlg = this.edit_dialog = $( "<div>" ).appendTo( this.element );

        //load edit dialogue
        $dlg.load("apps/tag_edit.html", function(){

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                 $(this).html(top.HR($(this).html()));
            })

            //-----------------

            var allFields = $dlg.find('input');

            that._fromDataToUI(tagID);

            function __doSave(){

                  allFields.removeClass( "ui-state-error" );

                  var message = $dlg.find('.messages');
                  var bValid = top.HEURIST.util.checkLength( $dlg.find('#tag_Text'), "Name", message, 2, 25 );

                  if(bValid){

                    var tag_id = $dlg.find('#tag_ID').val();
                    var tag_text = $dlg.find('#tag_Text').val();
                    var tag_desc = $dlg.find('#tag_Description').val();

                    var request = {tag_Text: tag_text,
                            tag_Description: tag_desc,
                            tag_UGrpID: that.options.current_UGrpID};

                    var isEdit = ( parseInt(tag_id) > 0 );

                    if(isEdit){
                        request.tag_ID = tag_id;
                    }

                    //get hapi and save tag
                    top.HAPI.RecordMgr.tag_save(request,
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){

                                var tagID = response.data;

                                if(!top.HAPI.currentUser.usr_Tags){
                                    top.HAPI.currentUser.usr_Tags = {};
                                }
                                top.HAPI.currentUser.usr_Tags[that.options.current_UGrpID][tagID] = [tag_text, tag_desc];

                                $dlg.dialog( "close" );
                                that._renderTags();
                            }else{
                                message.addClass( "ui-state-highlight" );
                                message.text(response.message);
                            }
                        }

                    );
                  }
            }

            allFields.on("keypress",function(event){
                  var code = (event.keyCode ? event.keyCode : event.which);
                  if (code == 13) {
                      __doSave();
                  }
            });


            $dlg.dialog({
              autoOpen: false,
              height: 240,
              width: 350,
              modal: true,
              resizable: false,
              title: top.HR('Edit tag'),
              buttons: [
                {text:top.HR('Save'), click: __doSave},
                {text:top.HR('Cancel'), click: function() {
                  $( this ).dialog( "close" );
                }}
              ],
              close: function() {
                allFields.val( "" ).removeClass( "ui-state-error" );
              }
            });

            $dlg.dialog("open");
            $dlg.zIndex(991);

        });
    }else{
        //show dialogue
        this._fromDataToUI(tagID);
        this.edit_dialog.dialog("open");
        this.edit_dialog.zIndex(991);
    }

  },

  /**
  * Open itself as modal dialogue to manage all tags
  */
  _manageTags: function(){
    showManageTags();
  },

  /**
  * Assign tags to selected records
  */
  _assignTags: function(){
    
        //find checkbox that has usage>0 and unchecked 
        // and vs  usage==0 and checked
        var t_added = $(that.element).find('input[type="checkbox"][usage="0"]:checked');
        var t_removed = $(that.element).find('input[type="checkbox"][usage!="0"]:not(:checked)');
        
        if(t_added.length>0 || t_removed.length>0)
        {
        
            var toassign = [];
            t_added.each(function(i,e){ toassign.push($(e).attr('tagID')); });
            var toremove = [];
            t_removed.each(function(i,e){ toremove.push($(e).attr('tagID')); });
            
             top.HAPI.RecordMgr.tag_set({assign: toassign, remove: toremove, UGrpID:val, recIDs:this.options.record_ids},
                function(response) {
                    if(response.status == top.HAPI.ResponseStatus.OK){
                        that.hide();
                    }else{
                        top.HEURIST.util.showMsgErr(response);
                    }
                });
        
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

function showManageTags(){
    
       var manage_tags = $('#heurist-tags-dialog');

       if(manage_tags.length<1){

            manage_tags = $('<div id="heurist-tags-dialog">')
                    .appendTo( $('body') )
                    .tag_assign({ isdialog:true });
       }

       manage_tags.tag_assign( "show" );
}
