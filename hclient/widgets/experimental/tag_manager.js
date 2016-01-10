/**
* Manage user's tags, showing usage and allowing deletion and merging of tags
*
* This widget is dynamically loaded and used in rec_actions and profile
*
* TODO: "Requires utils.js" - does it?
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.tag_manager", {

    // default options
    options: {
        isdialog: false, //show in dialog or embedded

        current_GrpID: null,
        // we take tags from top.HAPI4.currentUser.usr_Tags - array of tags in form [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]

        record_ids: null, //array of record ids the tag selection will be applied for

        current_order: 0  // order by name
    },

    // the constructor
    _create: function() {

        top.HAPI4.currentUser.usr_Tags = {}; //clear all

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
                title: top.HR("Manage Tags"),
                resizeStop: function( event, ui ) {
                    //that.wcontainer.css('width','100%');
                    //.css({clear:'both'})
                    that.element.css({overflow: 'none !important','width':'100%'}); //,'height': that.element.parent().css('height')-90});
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
            this.wcontainer.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'500px'}).appendTo( this.element );
        }

        //---------------------------------------- HEADER
        // Workgroup selector
        this.select_grp = $( "<select>", {width:'96%'} )
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.wcontainer );
        this._on( this.select_grp, {
            change: function(event) {
                //load tags for this group
                var val = event.target.value; //ugrID
                if(this.options.current_GrpID==val) return;

                //var that = this;

                if(top.HAPI4.currentUser.usr_Tags && top.HAPI4.currentUser.usr_Tags[val]){  //already found
                    this.options.current_GrpID = val;
                    this._renderItems();
                }else{
                    top.HAPI4.RecordMgr.tag_get({UGrpID:val, recIDs:this.options.record_ids},
                        function(response) {
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                that.options.current_GrpID = val;
                                if(!top.HAPI4.currentUser.usr_Tags){
                                    top.HAPI4.currentUser.usr_Tags = {};
                                }
                                top.HAPI4.currentUser.usr_Tags[val] = response.data[val];
                                that._renderItems();
                            }else{
                                top.HEURIST4.msg.showMsgErr(response);
                            }
                    });
                }
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
                    var s = $(event.target).val();
                    $(e).parent().css('display', (s=='' || e.innerHTML.indexOf(s)>=0)?'block':'none');
                });
            }
        });

        //---------------------------------------- ORDER
        this.sort_div = $( "<div>").css({ 'float':'right' }).appendTo( this.wcontainer );

        this.lbl_message = $( "<label>").css({'padding-right':'5px'})
        .html(top.HR('Sort'))
        .appendTo( this.sort_div );

        this.select_order = $( "<select><option value='0'>"+
            top.HR("by name")+"</option><option value='3'>"+
            top.HR("by usage")+"</option><option value='2'>"+
            top.HR("by date")+"</option><option value='5'>"+
            top.HR("marked")+"</option></select>", {'width':'80px'} )

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
            css1 =  {'overflow-y':'auto','padding':'0.4em','width':'100%','height':'400px'};
        }
        this.div_content = $( "<div>" )
        .addClass('list')
        .css(css1)
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
                //disabled: 'disabled',
                text: top.HR("Assign"),
                title: top.HR("Assign selected tags")
            })
            .appendTo( this.div_toolbar )
            .button();

            this._on( this.btn_assign, { click: "_assignTags" } );

        }

        // list of groups for current user
        var selObj = this.select_grp.get(0);
        top.HEURIST4.util.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
            [{key:top.HAPI4.currentUser.ugr_ID, title:top.HR('Personal Tags')}],
            function(){
                that.select_grp.val(top.HAPI4.currentUser.ugr_ID);
                that.select_grp.change();
        });

    }, //end _create

    _setOption: function( key, value ) {
        this._super( key, value );

        if(key=='record_ids'){
            this._reloadTags();
        }
    },

    _reloadTags: function(uGrpID){
        if(uGrpID){
            top.HAPI4.currentUser.usr_Tags[uGrpID] = null;
        }else{
            top.HAPI4.currentUser.usr_Tags = {}; //clear all
        }
        this.options.current_GrpID = null;
        this.select_grp.change();
    },

    /* private function */
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.input_search.remove();
        this.select_grp.remove();
        this.select_order.remove();
        this.sort_div.remove();
        this.search_div.remove();


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
    _renderItems: function(){

        if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            //this._off( $allrecs, "click");
            this.div_content.empty();  //clear
        }

        /*var btn = $('#assignTags');
        btn.addClass('ui-button-disabled ui-state-disabled');
        btn.attr('disabled','disabled');       */


        if(top.HAPI4.currentUser.usr_Tags && top.HAPI4.currentUser.usr_Tags[this.options.current_GrpID])
        {
            var that = this;
            var tags2 = top.HAPI4.currentUser.usr_Tags[this.options.current_GrpID];
            var tagID;
            var tags = [];
            for(tagID in tags2) {
                if(tagID){
                    var tag = tags2[tagID];
                    if(tag.length<5){
                        tag.push(tagID);
                        tag.push( !(that.options.isdialog || tag[3]<1) );
                    }
                    tags.push(tag);
                }
            }

            tags.sort(function (a,b){
                var val = that.options.current_order;
                /*if(val==5){
                return 0;
                }else */
                if(val==0){
                    return a[val]<b[val]?-1:1;
                }else{
                    return a[val]<b[val]?1:-1;
                }
            });

            var i;
            for(i=0; i<tags.length; ++i) {

                var tag = tags[i];
                tagID = tag[4];

                $tagdiv = $(document.createElement('div'));

                $tagdiv
                .addClass('recordDiv')
                .attr('id', 'tag-'+tagID )
                .attr('tagID', tagID )
                .appendTo(this.div_content);

                $('<input>')
                .attr('type','checkbox')
                .attr('tagID', tagID )
                .attr('usage', tag[3])
                .attr('checked', tag[5] )  //?false:true ))
                .addClass('recordIcons')
                .css('margin','0.4em')
                .click(function(event){

                    top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID][$(this).attr('tagID')][5] = event.target.checked;
                    //event.target.keepmark = event.target.checked;

                    if(that.options.isdialog){  //tag management
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
                        /*if(t_added.length>0 || t_removed.length>0){
                        btn.removeAttr('disabled');
                        btn.removeClass('ui-button-disabled ui-state-disabled');
                        }else{
                        btn.addClass('ui-button-disabled ui-state-disabled');
                        btn.attr('disabled','disabled');
                        }*/

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
                //.css({'margin':'0.4em', 'height':'1.4em'})
                .html( tag[0] )
                .appendTo($tagdiv);

                //count - usage
                $('<div>')
                //.addClass('recordIcons')
                .css({ 'position':'absolute','right':'60px'}) //'margin':'0.4em', 'height':'1.4em',
                .css('display','inline-block')
                .html( tag[3] )
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


            }//for

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
            var tag = top.HAPI4.currentUser.usr_Tags[this.options.current_GrpID][tagID];
            if(!tag) return;
            tagIDs.push(tagID);
        }else{
            var checkboxes = $(this.element).find('input:checked');
            checkboxes.each(function(i,e){ tagIDs.push($(e).attr('tagID')); });
        }
        if(tagIDs.length<1) return;

        var request = {ids: tagIDs.join(',')};
        var that = this;

        top.HEURIST4.msg.showMsgDlg(top.HR("Delete? Please confirm"), function(){

            top.HAPI4.RecordMgr.tag_delete(request,
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){

                        $.each(tagIDs, function(i,e){
                            //remove from UI
                            $('#tag-'+e).remove();
                            //remove from
                            delete top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID][e];
                        });

                    }else{
                        top.HEURIST4.msg.showMsgErr(response);
                    }
                }

            );
            }, "Confirmation");
    },

    // show edit dialog
    _mergeTag: function(){

        var tagIDs = [];

        var checkboxes = $(this.element).find('input:checked');
        checkboxes.each(function(i,e){ tagIDs.push($(e).attr('tagID')); });

        if(tagIDs.length<1) return;

        this._editTag( null, tagIDs.join(",") );

    },


    /**
    * Assign values to UI input controls
    */
    _fromDataToUI: function(tagID, tagIDs){

        var $dlg = this.edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            var tag_id = $dlg.find('#tag_ID');
            var tag_name = $dlg.find('#tag_Text');
            var tag_desc = $dlg.find('#tag_Description');

            var isEdit = (parseInt(tagID)>0);

            if(isEdit){
                var tag = top.HAPI4.currentUser.usr_Tags[this.options.current_GrpID][tagID];
                tag_id.val(tagID);
                tag_name.val(tag[0]);
                tag_desc.val(tag[1]);
            }else{ //add new saved search
                $dlg.find('input').val('');  //clear all
                tag_name.val(this.input_search.val());
            }

            var tag_ids = $dlg.find('#tag_IDs_toreplace');
            tag_ids.val(tagIDs);
        }
    },

    /**
    * Show dialogue to add/edit tag
    *
    * if tagIDs is defined - replace old tags in this list to new one
    */
    _editTag: function(tagID, tagIDs){

        var sTitle = top.HR(top.HEURIST4.util.isnull(tagIDs)
            ?(top.HEURIST4.util.isempty(tagID)?"Add Tag":"Edit Tag")
            :"Define new tag that replaces old ones");

        if(  this.edit_dialog==null )
        {
            var that = this;
            var $dlg = this.edit_dialog = $( "<div>" ).appendTo( this.element );

            //load edit dialogue
            $dlg.load(top.HAPI4.basePathV4+"hclient/widgets/tag_edit.html", function(){

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(top.HR($(this).html()));
                })

                //-----------------

                var allFields = $dlg.find('input');

                that._fromDataToUI(tagID, tagIDs);

                function __doSave(){

                    allFields.removeClass( "ui-state-error" );

                    var message = $dlg.find('.messages');
                    var bValid = top.HEURIST4.msg.checkLength( $dlg.find('#tag_Text'), "Name", message, 2, 25 );

                    if(bValid){

                        var tag_id = $dlg.find('#tag_ID').val();
                        var tag_text = $dlg.find('#tag_Text').val();
                        var tag_desc = $dlg.find('#tag_Description').val();
                        var tag_ids = $dlg.find('#tag_IDs_toreplace').val();

                        var request = {tag_Text: tag_text,
                            tag_Description: tag_desc,
                            tag_UGrpID: that.options.current_GrpID};

                        var isEdit = ( parseInt(tag_id) > 0 );

                        if(isEdit){
                            request.tag_ID = tag_id;
                        }

                        //get hapi and save tag
                        top.HAPI4.RecordMgr.tag_save(request,
                            function(response){
                                if(response.status == top.HAPI4.ResponseStatus.OK){

                                    var tagID = response.data;

                                    if(!top.HAPI4.currentUser.usr_Tags){
                                        top.HAPI4.currentUser.usr_Tags = {};
                                    }
                                    if(!top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID]){
                                        top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID] = {};
                                    }

                                    if(isEdit){
                                        var oldtag = top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID][tagID];
                                        top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID][tagID] = [tag_text, tag_desc, new Date(), oldtag[3], tagID, oldtag[5]];
                                    }else{
                                        top.HAPI4.currentUser.usr_Tags[that.options.current_GrpID][tagID] = [tag_text, tag_desc, new Date(), 0, tagID, 0];
                                    }

                                    if(!top.HEURIST4.util.isnull(tag_ids)){
                                        //send request to replace selected tags with new one
                                        var request = {ids: tag_ids,
                                            new_id: tagID,
                                            UGrpID: that.options.current_GrpID};

                                        top.HAPI4.RecordMgr.tag_replace(request, function(response){
                                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                                $dlg.dialog( "close" );

                                                that._reloadTags(that.options.current_GrpID);

                                                //that._renderItems();
                                            }else{
                                                message.addClass( "ui-state-highlight" );
                                                message.text(response.message);
                                            }
                                        });

                                    }else{
                                        $dlg.dialog( "close" );
                                        that._renderItems();
                                    }


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
                    title: sTitle,
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

            var message = this.edit_dialog.find('.messages');
            message.removeClass( "ui-state-highlight" );
            message.text("");

            //show dialogue
            this._fromDataToUI(tagID, tagIDs);
            this.edit_dialog.dialog( "option", "title", sTitle )
            this.edit_dialog.dialog("open");
            this.edit_dialog.zIndex(991);
        }

    },

    /**
    * Open itself as modal dialogue to manage all tags
    */
    _manageTags: function(){
        this.element.hide();
        showManageTags();
    },

    /**
    * Assign tags to selected records
    */
    _assignTags: function(){

        //find checkbox that has usage>0 and unchecked
        // and vs  usage==0 and checked
        var t_added = $(this.element).find('input[type="checkbox"][usage="0"]:checked');
        var t_removed = $(this.element).find('input[type="checkbox"][usage!="0"]:not(:checked)');

        if ((t_added.length>0 || t_removed.length>0) && this.options.current_GrpID)
        {

            var toassign = [];
            t_added.each(function(i,e){ toassign.push($(e).attr('tagID')); });
            var toremove = [];
            t_removed.each(function(i,e){ toremove.push($(e).attr('tagID')); });
            var that = this;

            top.HAPI4.RecordMgr.tag_set({assign: toassign, remove: toremove, UGrpID:this.options.current_GrpID, recIDs:this.options.record_ids},
                function(response) {
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        that.element.hide();
                    }else{
                        top.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }
    },

    show: function(){
        if(this.options.isdialog){
            this._reloadTags();
            this.element.dialog("open");
        }else{
            //fill selected value this.element
        }
    }


});

// Show as dialog
function showManageTags(){

    var manage_dlg = $('#heurist-tags-dialog');

    if(manage_dlg.length<1){

        manage_dlg = $('<div id="heurist-tags-dialog">')
        .appendTo( $('body') )
        .tag_manager({ isdialog:true });
    }

    manage_dlg.tag_manager( "show" );
}
