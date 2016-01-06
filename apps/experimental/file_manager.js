/**
* Assign/remove tags on selected records, upload files, requires utils.js
* TODO: Correct description above
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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


$.widget( "heurist.file_manager", {

    // default options
    options: {
        isdialog: false, //show in dialog or embedded

        current_mediatype: null,
        // we take tags from top.HAPI4.currentUser.usr_Files - array of tags in form [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]

        record_ids: null, //array of record ids the file selection will be applied for

        current_order: 0,  // order by name  - index corresponds to order in response data array

        view_mode: 'list', // list|thumbnails

        current_filter: '',

        header:[]
    },

    // the constructor
    _create: function() {

        top.HAPI4.currentUser.usr_Files = {}; // {type:[]; type2:[]}

        var that = this;

        this.wcontainer = $('<div id="file-wcontainer">');

        if(this.options.isdialog){

            this.wcontainer
            .css({overflow: 'none !important', width:'100% !important'})
            .appendTo(this.element);

            this.element.dialog({
                autoOpen: false,
                height: 640,
                width: 800,
                modal: true,
                title: top.HR("Manage files"),
                resizeStop: function( event, ui ) {
                    that.wcontainer.css('width','100%');
                },
                buttons: [
                    {text:top.HR('Delete'),
                        title: top.HR("Delete selected tags"),
                        disabled: 'disabled',
                        class: 'recs-actions',
                        click: function() {
                            that._deleteFile();
                    }},
                    {text:top.HR('Add'),
                        title: top.HR("Upload/register new file"),
                        click: function() {
                            that._editFile();
                    }},
                    {text:top.HR('Close'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            });

        }else{
            this.wcontainer.addClass('ui-widget-content ui-corner-all').css('padding','0.4em').appendTo( this.element );
        }

        //---------------------------------------- HEADER

        //<option value='all'>"+top.HR("all")+"</option>
        this.div_toolbar = $('<div id="file-toolbar">').css({'width':'100%'}).appendTo( this.wcontainer );

        this.lbl_message1 = $( "<label>").css({'padding-right':'5px'})
        .html(top.HR('Type'))
        .appendTo( this.div_toolbar );

        // file/media type selector
        this.select_mediatype = $( "<select><option value='image'>"+
            top.HR("image")+"</option><option value='video'>"+
            top.HR("video")+"</option><option value='audio'>"+
            top.HR("audio")+"</option><option value='text/html'>"+
            top.HR("text/html")+"</option><option value='document'>"+
            top.HR("document")+"</option><option value='flash'>"+
            top.HR("flash")+"</option><option value='xml'>"+
            top.HR("xml")+"</option></select>", {'width':'100px'} )
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.div_toolbar );

        this._on( this.select_mediatype, {
            change: function(event) {

                var val = event.target.value; //ugrID
                if(this.options.current_mediatype==val) return; //the same

                //var that = this;

                if(top.HAPI4.currentUser.usr_Files && top.HAPI4.currentUser.usr_Files[val]){  //already found
                    this.options.current_mediatype = val;
                    this._renderItems();
                }else{
                    top.HAPI4.RecordMgr.file_get({recIDs:this.options.record_ids, mediaType:val},
                        function(response) {
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                that.options.current_mediatype = val;
                                if(!top.HAPI4.currentUser.usr_Files){
                                    top.HAPI4.currentUser.usr_Files = {};
                                }

                                //top.HAPI4.currentUser.usr_Files[val] = response.data;

                                that.options.header = response.data[0];
                                var files = [];
                                var idx;
                                for(idx in response.data) {
                                    if(idx>0){
                                        files.push(response.data[idx]);
                                    }
                                }
                                top.HAPI4.currentUser.usr_Files[val] = files;


                                that._renderItems();
                            }else{
                                top.HEURIST4.msg.showMsgErr(response);
                            }
                    });
                }
            }
        });


        //---------------------------------------- SEARCH

        //this.search_div = $( "<div>").css({ width:'36%', 'display':'inline-block' }).appendTo( this.div_toolbar );

        this.lbl_message2 = $( "<label>").css({'padding-right':'5px', 'padding-left':'5px'})
        .html(top.HR('Search'))
        .appendTo( this.div_toolbar );

        this.input_search = $( "<input>", {width:'25%'} )
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.div_toolbar );

        this._on( this.input_search, {
            keyup: function(event) {
                //filter tags
                this._applyFilter($(event.target).val());
            }
        });

        //---------------------------------------- ORDER
        //this.sort_div = $( "<div>").css({ 'float':'right' }).appendTo( this.div_toolbar );

        this.lbl_message3 = $( "<label>").css({'padding-right':'5px', 'padding-left':'5px'})
        .html(top.HR('Sort'))
        .appendTo( this.div_toolbar );

        //order index is correspond to index in data array
        this.select_order = $( "<select><option value='1'>"+
            top.HR("by name")+"</option><option value='5'>"+
            top.HR("by size")+"</option><option value='6'>"+
            top.HR("by usage")+"</option><option value='7'>"+
            top.HR("by date")+"</option><option value='8'>"+
            top.HR("marked")+"</option></select>", {'width':'80px'} )
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.div_toolbar );

        this._on( this.select_order, {
            change: function(event) {
                var val = Number(event.target.value); //order
                this.options.current_order = val;
                this._renderItems();
            }
        });

        //---------------------------------------- VIEW MODE
        this.btn_view = $( "<button>", {text: "view"} )
        .css('float','right')
        .css('width', '10em')
        .appendTo( this.div_toolbar )
        .button({icons: {
            secondary: "ui-icon-triangle-1-s"
            },text:true});

        this.menu_view = $('<ul>'+
            '<li id="menu-view-list"><a href="#">'+top.HR('list')+'</a></li>'+
            '<li id="menu-view-thumbs"><a href="#">'+top.HR('thumbs')+'</a></li>'+
            '</ul>')
        .addClass('menu-or-popup')
        .css('position','absolute')
        .appendTo( this.document.find('body') )
        .menu({
            select: function( event, ui ) {
                var mode = ui.item.attr('id');
                mode = mode.substr(10);
                that._applyViewMode(mode);
        }})
        .hide();

        this._on( this.btn_view, {
            click: function(e) {
                $('.menu-or-popup').hide(); //hide other
                var menu_view = $( this.menu_view )
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_view });
                $( document ).one( "click", function() {  menu_view.hide(); });
                return false;
            }
        });



        //----------------------------------------
        this.div_content = $( "<div id='file-list'>" )
        .css({'left':0,'right':0,'overflow-y':'auto','padding':'0.2em','position':'absolute','top':'5em','bottom':'2em'})
        .html('list of files')
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.wcontainer );

        //-----------------------------------------

        this.edit_dialog = null;

        if(!this.options.isdialog)
        {
            /* @todo - perhaps to use this dialog in edit record ??????
            this.div_toolbar = $( "<div>" )
            .css({'width': '100%', height:'2.4em'})
            .appendTo( this.wcontainer );

            this.btn_add = $( "<button>", {
            text: top.HR("Add File"),
            title: top.HR("Upload/register new file")
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
            */
        }

    }, //end _create

    _setOption: function( key, value ) {
        this._super( key, value );

        if(key=='record_ids'){
            this._reloadFiles();
        }
    },

    _reloadFiles: function(mediaType){
        if(mediaType){
            top.HAPI4.currentUser.usr_Files[mediaType] = null;
        }else{
            top.HAPI4.currentUser.usr_Files = {}; //clear all
        }
        this.options.current_mediatype = null;
        this.select_mediatype.change();
    },

    /* private function */
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.input_search.remove();
        this.select_mediatype.remove();
        this.select_order.remove();
        this.div_toolbar.remove();
        //this.sort_div.remove();
        //this.search_div.remove();


        if(this.edit_dialog){
            this.edit_dialog.remove();
        }
        /* @todo for non dialog mode  - id need it ?????
        if(this.div_toolbar){ //non dialog mode
        this.btn_manage.remove();
        this.btn_assign.remove();
        this.btn_add.remove();
        this.div_toolbar.remove();
        }
        */

        this.wcontainer.remove();
    },

    //
    _renderItems: function(){

        if(this.div_content){
            var $allrecs = this.div_content.find('.recordDiv');
            //this._off( $allrecs, "click");
            this.div_content.empty();  //clear
        }

        /*var btn = $('#assignTags');
        btn.addClass('ui-button-disabled ui-state-disabled');
        btn.attr('disabled','disabled');       */


        if(top.HAPI4.currentUser.usr_Files && top.HAPI4.currentUser.usr_Files[this.options.current_mediatype])
        {
            //convert from object to array
            var that = this;
            var recfiles = top.HAPI4.currentUser.usr_Files[this.options.current_mediatype];

            recfiles.sort(function (a,b){
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

            var i, recID;
            for(i=0; i<recfiles.length; ++i) {

                var recfile = recfiles[i];

                function fld(fldname){
                    if(that.options.header && that.options.header.indexOf(fldname)>=0){
                        var idx_fld = that.options.header.indexOf(fldname);
                        return recfile[idx_fld];
                    }else{
                        return "";
                    }
                }

                $recdiv = $(document.createElement('div'));

                recID = fld('ulf_ID');

                $recdiv
                .addClass('recordDiv')
                .attr('id', 'rd'+recID )
                .attr('recID', recID )
                //.attr('title', 'Select to view, Ctrl-or Shift- for multiple select')
                //.on("click", that._recordDivOnClick )
                .appendTo(this.div_content);


                var obf_recID = fld('ulf_ObfuscatedFileID');
                if(obf_recID){
                    $(document.createElement('div'))
                    .addClass('recTypeThumb')
                    .css({ 'background-image':'url('+ top.HAPI4.basePathV4+'/file.php?db=' + top.HAPI4.database + '&thumb='+obf_recID + ')', 'opacity': 1 })
                    .appendTo($recdiv);
                }else{
                    //@todo - thumbnail and icons for all mediatype
                    $(document.createElement('div'))
                    .addClass('recTypeThumb')
                    .css('background-image', 'url('+ top.HAPI4.basePathV4 + 'hclient/assets/75x75.gif' )    //+ 'thumb/th_' + rectypeID + '.png)')
                    .appendTo($recdiv);
                }

                /*
                $('<input>')
                .attr('type','checkbox')
                .attr('tagID', tagID )
                .attr('usage', tag[3])
                .attr('checked', tag[5] )  //?false:true ))
                .addClass('recordIcons')
                .css('margin','0.4em')
                .click(function(event){

                top.HAPI4.currentUser.usr_Files[that.options.current_mediatype][$(this).attr('tagID')][5] = event.target.checked;
                //event.target.keepmark = event.target.checked;

                if(that.options.isdialog){  //tag management
                var checkboxes = $(that.element).find('input:checked');
                var btns = $('.recs-actions');
                if(checkboxes.length>0){
                btns.removeAttr('disabled');
                btns.removeClass('ui-button-disabled ui-state-disabled');
                }else{
                btns.addClass('ui-button-disabled ui-state-disabled');
                btns.attr('disabled','disabled');
                }
                }
                //alert(this.checked);
                })
                //.css({'display':'inline-block', 'margin-right':'0.2em'})
                .appendTo($tagdiv);
                */

                $('<div>',{
                    title: fld('ulf_OrigFileName')
                })
                //.css('display','inline-block')
                .addClass('recordTitle')
                .css({'margin':'0.4em', 'height':'1.4em'})
                .html( fld('ulf_OrigFileName') )
                .appendTo($recdiv);

                /*
                if(this.options.isdialog){
                //$tagdiv.find('.recordTitle').css('right','36px');

                $tagdiv
                .append( $('<div>')
                .addClass('edit-delete-buttons')
                .css('margin','0.4em 1.2em')
                .append( $('<div>', { tagID:tagID, title: top.HR('Edit tag') })
                .button({icons: {primary: "ui-icon-pencil"}, text:false})
                .click(function( event ) {
                that._editFile( $(this).attr('tagID') );
                }) )
                .append($('<div>',{ tagID:tagID, title: top.HR('Delete tag') })
                .button({icons: {primary: "ui-icon-close"}, text:false})
                .click(function( event ) {
                that._deleteFile( $(this).attr('tagID') );
                }) )
                );
                }
                */


            }//for

            /*$allrecs = this.div_content.find('.recordDiv');
            this._on( $allrecs, {
            click: this._recordDivOnClick
            });*/
            this._applyViewMode();
        }

    },

    _applyViewMode: function(newmode){

        //var $allrecs = this.div_content.find('.recordDiv');
        if(newmode){
            var oldmode = this.options.view_mode;
            this.options.view_mode = newmode;
            //this.option("view_mode", newmode);
            this.div_content.removeClass(oldmode)
        }else{
            newmode = this.options.view_mode;
        }
        this.div_content.addClass(newmode);

        this.btn_view.button( "option", "label", top.HR(newmode));

        this._applyFilter(null);
    },

    _applyFilter: function(sfilter){

        if(sfilter!=null){
            this.options.current_filter = sfilter;
        }else{
            sfilter = this.options.current_filter;
        }
        var sblock = this.options.view_mode=='list'?'block':'inline-block';
        var tagdivs = $(this.element).find('.recordTitle');
        tagdivs.each(function(i,e){
            $(e).parent().css('display', (sfilter=='' || e.innerHTML.indexOf(sfilter)>=0) ?sblock :'none');
        });
    },


    /**
    * Remove given tag
    *
    * @param tagID
    */
    _deleteFile: function(fileID){

        var tagIDs = [];
        if(fileID){
            var tag = top.HAPI4.currentUser.usr_Files[this.options.current_mediatype][tagID];
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
                            delete top.HAPI4.currentUser.usr_Files[that.options.current_mediatype][e];
                        });

                    }else{
                        top.HEURIST4.msg.showMsgErr(response);
                    }
                }

            );
            }, "Confirmation");
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
                var tag = top.HAPI4.currentUser.usr_Files[this.options.current_mediatype][tagID];
                tag_id.val(tagID);
                tag_name.val(tag[0]);
                tag_desc.val(tag[1]);
            }else{ //add new
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
            $dlg.load("apps/tag_edit.html", function(){

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
                            tag_UGrpID: that.options.current_mediatype};

                        var isEdit = ( parseInt(tag_id) > 0 );

                        if(isEdit){
                            request.tag_ID = tag_id;
                        }

                        //get hapi and save tag
                        top.HAPI4.RecordMgr.tag_save(request,
                            function(response){
                                if(response.status == top.HAPI4.ResponseStatus.OK){

                                    var tagID = response.data;

                                    if(!top.HAPI4.currentUser.usr_Files){
                                        top.HAPI4.currentUser.usr_Files = {};
                                    }
                                    if(!top.HAPI4.currentUser.usr_Files[that.options.current_mediatype]){
                                        top.HAPI4.currentUser.usr_Files[that.options.current_mediatype] = {};
                                    }

                                    if(isEdit){
                                        var oldtag = top.HAPI4.currentUser.usr_Files[that.options.current_mediatype][tagID];
                                        top.HAPI4.currentUser.usr_Files[that.options.current_mediatype][tagID] = [tag_text, tag_desc, new Date(), oldtag[3], tagID, oldtag[5]];
                                    }else{
                                        top.HAPI4.currentUser.usr_Files[that.options.current_mediatype][tagID] = [tag_text, tag_desc, new Date(), 0, tagID, 0];
                                    }

                                    if(!top.HEURIST4.util.isnull(tag_ids)){
                                        //send request to replace selected tags with new one
                                        var request = {ids: tag_ids,
                                            new_id: tagID,
                                            UGrpID: that.options.current_mediatype};

                                        top.HAPI4.RecordMgr.tag_replace(request, function(response){
                                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                                $dlg.dialog( "close" );

                                                that._reloadFiles(that.options.current_mediatype);

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
    * NOT USED - to delete
    * Open itself as modal dialogue to manage all tags
    */
    _manageFiles: function(){
        this.element.hide();
        showManageFiles();
    },

    /**
    * Assign tags to selected records
    */
    _assignTags: function(){

        //find checkbox that has usage>0 and unchecked
        // and vs  usage==0 and checked
        var t_added = $(this.element).find('input[type="checkbox"][usage="0"]:checked');
        var t_removed = $(this.element).find('input[type="checkbox"][usage!="0"]:not(:checked)');

        if ((t_added.length>0 || t_removed.length>0) && this.options.current_mediatype)
        {

            var toassign = [];
            t_added.each(function(i,e){ toassign.push($(e).attr('tagID')); });
            var toremove = [];
            t_removed.each(function(i,e){ toremove.push($(e).attr('tagID')); });
            var that = this;

            top.HAPI4.RecordMgr.tag_set({assign: toassign, remove: toremove, UGrpID:this.options.current_mediatype, recIDs:this.options.record_ids},
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
            this._reloadFiles();
            this.element.dialog("open");
        }else{
            //fill selected value this.element
        }
    }


});

function showManageFiles(){

    var manage_dlg = $('#heurist-file-dialog');

    if(manage_dlg.length<1){

        manage_dlg = $('<div id="heurist-file-dialog">')
        .appendTo( $('body') )
        .file_manager({ isdialog:true });
    }

    manage_dlg.file_manager( "show" );
}
