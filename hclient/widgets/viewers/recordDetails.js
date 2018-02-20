/**
* View data for one record: loads data progressively in order shared, private, relationships, links
*
* TODO: rec_viewer.js and recordDetails.js are pretty much identical. Combine, or at least use includes
*
* NO Requires hclient/widgets/rec_actions.js (must be preloaded)
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


$.widget( "heurist.recordDetails", {

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

        if(false){
            /* EXEREMENTAL
            this.action_buttons = $('<div>')
            .css('display','inline-block')
            .rec_actions({actionbuttons: "tags,share,more"})
            .appendTo(this.div_toolbar);
            */
        }

        this.btn_edit = $( "<button>", {text: window.hWin.HR('edit')} )
        .css('float','right')
        .appendTo( this.div_toolbar )
        .button({icons: {
            primarty: "ui-icon-pencil"
            },text:true});

        this._on( this.btn_edit, {
            click: function() {
                if(!window.hWin.HEURIST4.editing){
                    window.hWin.HEURIST4.editing = new hEditing();
                }
                window.hWin.HEURIST4.editing.edit(this.options.recdata);
            }
        });



        this.div_content = $( "<div>" )
        .css({'left':0,'right':0,'overflow-y':'auto','padding':'0.2em','position':'absolute','top':'5em','bottom':0})
        .appendTo( this.element ).hide();

        this.lbl_message = $( "<label>" )
        .html(window.hWin.HR('select record'))
        .appendTo( this.element ).hide();

        //----------------------- listener for global event
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SELECT,
            function(e, data) {

                var _recID = null,
                _recdata = null;

                if(data) data = data.selection;
                var res = window.hWin.HAPI4.getSelection(data, false);
                if(res!=null && res.length()>0){
                    _recdata = _recdata.getFirstRecord();
                    _recID  = _recdata.fld(_rec, 'rec_ID'); //_rec[2];
                }

                that.options.recID = _recID;
                that.options.recdata = _recdata;
                that._refresh();
                //TEMP if(that.action_buttons) that.action_buttons.rec_actions('option','record_ids', (_recID!=null)?[_recID]:[] );


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
                if(this.recIDloaded != this.options.recID){
                    this.recIDloaded = this.options.recID;

                    //alert('show '+this.options.recID);
                    this._renderHeader();

                    var that = this;

                    // call for tags - on response - draw tags
                    that.options.user_Tags = {}; //reset
                    //load all tags for current user and this groups with usagecount for current record
                    /* todo replace to entity
                    window.hWin.HAPI4.RecordMgr.tag_get({recIDs:this.recIDloaded, UGrpID:'all'},
                        function(response) {
                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                if(that.options.recID == response.data['recIDs']){ //not outdated

                                    for(uGrpID in response.data) {
                                        if(uGrpID && window.hWin.HEURIST4.util.isNumber(uGrpID)){
                                            that.options.user_Tags[uGrpID] = response.data[uGrpID];
                                        }
                                    }
                                    that._renderTags();
                                }

                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                    });
                    */

                    /* dynamic load of required js
                    var that = this;
                    if($.isFunction($('body').editing_input)){
                    this._renderHeader();
                    }else{
                    $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/editing/editing_input.js', function(){
                    $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/rec_search.js',
                    function(){
                    $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/rec_relation.js',
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
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SELECT);

        this.lbl_message.remove();

        this.btn_edit.remove();

        if(this.action_buttons) this.action_buttons.remove();
        this.div_toolbar.remove();
        this.mediacontent.remove();
        this.div_content.remove();
    }

    ,_renderTags: function() {

        var $fieldset = $("<fieldset>").css('font-size','0.9em').appendTo(this.div_content);

        //if(window.hWin.HAPI4.currentUser.usr_Tags)
        if(this.options.user_Tags)
        {

            //groups.unshift(34);
            this._renderTagsForGroup(window.hWin.HAPI4.currentUser.ugr_ID, window.hWin.HR('Personal Tags') );

            var groups = window.hWin.HAPI4.currentUser.ugr_Groups;
            for (var groupID in groups)
            if(groupID>0){
                var groupName = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                this._renderTagsForGroup(groupID, groupName);
            }
        }

    }

    ,_renderTagsForGroup: function(groupID, groupName){

        var tags = this.options.user_Tags[groupID]; //window.hWin.HAPI4.currentUser.usr_Tags[groupID];
        var tags_list = "";

        var tagID;
        for(tagID in tags) {
            var tag = tags[tagID];
            if(tag && tag[3]>0){ //usage
                tags_list = tags_list + "<a href='#' "+(window.hWin.HEURIST4.util.isempty(tag[1])?"":"title='"+tag[1]+"'")+">"+tag[0]+"</a> ";
            }
        }

        var $fieldset = $("<fieldset>").css('font-size','0.9em').appendTo(this.div_content);

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

    ,_renderFiles: function(title){

        //$(this.mediacontent).yoxview("unload");
        //$(this.mediacontent).yoxview("update");
        //this.mediacontent.empty();

        if(this.options.rec_Files)
        {
            var recID = this.options.recID;

            for (var idx in this.options.rec_Files){
                if(idx>=0){  //skip first
                    var file = this.options.rec_Files[idx];

                    var obf_recID = file[0];
                    var file_param = file[1]; //($.isArray(file) ?file[2] :file ) ;

                    var needplayer = !(file_param.indexOf('video')<0 && file_param.indexOf('audio')<0);

                    // <a href="images/large/01.jpg"><img src="images/thumbnails/01.jpg" alt="First" title="The first image" /></a>
                    // <a href="http://dynamic.xkcd.com/random/comic/?width=880" target="yoxview"><img src="../images/items/thumbnails/xkcd.jpg" alt="XKCD" title="Random XKCD comic" /></a>

                    var $alink = $("<a>",{href: window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + (needplayer?'&player=1':'') + '&file='+obf_recID, target:"yoxview" })
                    .appendTo($("<div>").css({height:'auto','display':'inline-block'}).appendTo(this.mediacontent));
                    $("<img>", {src: window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + '&thumb='+obf_recID, title:title}).appendTo($alink);


                }
            }

            this.mediacontent.show();

            /*if($.isFunction(this.mediacontent.yoxview)){
            $(this.mediacontent).yoxview("update");
            }else{

            }  */
            $(this.mediacontent).yoxview({ skin: "top_menu", allowedUrls: /\?db=(?:\w+)&file=(?:\w+)$/i});
            // /\/redirects\/file_download.php\?db=(?:\w+)&id=(?:\w+)$/i});
        }
    }

    ,_renderHeader: function(){

        var recID = this.options.recID;
        var recdata = this.options.recdata;
        var record = recdata.getFirstRecord();
        var rectypes = recdata.getStructures();
        var rec_title = recdata.fld(record, 'rec_Title');
        var that = this;

        var rectypeID = recdata.fld(record, 'rec_RecTypeID');
        if(!rectypes || rectypes.length==0){
            rectypes = window.hWin.HEURIST4.rectypes;
        }

        var rfrs = rectypes.typedefs[rectypeID].dtFields;
        var fi = rectypes.typedefs.dtFieldNamesToIndex;

        this.div_content.empty();

        //header: rectype and title
        var $header = $('<div>')
        .css({'padding':'0.4em', 'border-bottom':'solid 1px #6A7C99'})
        //.addClass('ui-widget-header ui-corner-all')
        .appendTo(this.div_content);

        $('<h2>' + rec_title + '</h2>')
        .appendTo($header);

        $('<div>')
        .append( $('<img>',{
            src:  window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif',
            title: '@todo rectypeTitle'.htmlEscape()
            })
            .css({'background-image':'url('+ window.hWin.HAPI4.iconBaseURL + rectypeID + '.png)','margin-right':'0.4em'}))
        .append('<span>'+(rectypes ?rectypes.names[rectypeID]: 'rectypes not defined')+'</span>')
        .appendTo($header);

        // media content - populated in renderFiles
        this.mediacontent = $("<div>",{id:"mediarec"+recID}).css({'width':'100%','text-align':'center','height':'auto'}).addClass("thumbnails").appendTo(this.div_content); //.hide();

        this.options.rec_Files = [];

        // list of fields
        var order = rectypes.dtDisplayOrder[rectypeID];
        if(order){

            // main fields
            var i, l = order.length;

            var $fieldset = $("<fieldset>").css('font-size','0.9em').appendTo(this.div_content);

            for (i = 0; i < l; ++i) {
                var dtID = order[i];
                if (values=='' ||
                    rfrs[dtID][fi['rst_RequirementType']] == 'forbidden' ||
                    ( !window.hWin.HAPI4.has_access(  recdata.fld(record, 'rec_OwnerUGrpID') ) &&
                        rfrs[dtID][fi['rst_NonOwnerVisibility']] == 'hidden' )) //@todo: server not return hidden details for non-owner
                {
                    continue;
                }


                var values = recdata.fld(record, dtID);

                if( (rfrs[dtID][fi['dty_Type']])=="separator" || !values) continue;

                var isempty = true;
                $.each(values, function(idx,value){
                    if(!window.hWin.HEURIST4.util.isempty(value)){ isempty=false; return false; }
                } );
                if(isempty) continue;

                if(rfrs[dtID][fi['dty_Type']] == 'file'){
                    $.each(values, function(idx,value){
                        if(!window.hWin.HEURIST4.util.isempty(value)){
                            that.options.rec_Files.push(value)
                        }
                    } );
                    continue; //hide files
                }

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

        this._renderFiles(rec_title);
    }

});
