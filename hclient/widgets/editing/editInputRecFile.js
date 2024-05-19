/**
* editInputRecFile.js widget for input controls on edit form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
import "./editInputBase.js";

$.widget( "heurist.editInputRecFile", $.heurist.editInputBase, {

    // default options
    options: {
    },
    
    _input_img: null,
    _gicon: null,
    
    /* Handler Variables */
    _isClicked: 0,  // Number of image clicks, one = freeze image inline, two = enlarge/srink
    _hideTimer:0,
    _showTimer:0,
    
    _create: function() {
        
            var that = this;
            
            this._super();
        
            var $inputdiv = this.element;
            $inputdiv.uniqueId();
            
            var $input = $( "<input>")
            .addClass('text ui-widget-content ui-corner-all')
            .css('width',200)
            .appendTo( $inputdiv );
            
            window.hWin.HEURIST4.ui.disableAutoFill( $input );
            
            this._input = $input;
            
            this._on( this._input, { change:this.onChange });
            
  
                var select_return_mode = 'recordset';

                /* File IDs, needed for processes below */
                var f_id = this._newvalue.ulf_ID;
                var f_nonce = this._newvalue.ulf_ObfuscatedFileID;

                var $clear_container = $('<span id="btn_clear_container"></span>').appendTo( $inputdiv );
                
                $input.css({'padding-left':'30px', cursor:'hand'});
                //folder icon in the begining of field
                this._gicon = $('<span class="ui-icon ui-icon-folder-open"></span>')
                    .css({position: 'absolute', margin: '5px 0px 0px 8px', cursor:'hand'}).insertBefore( $input ); 
                
                /* Image and Player (enalrged image) container */
                this._input_img = $('<br><div class="image_input ui-widget-content ui-corner-all thumb_image" style="margin:5px 0px;border:none;background:transparent;">'
                + '<img id="img'+f_id+'" class="image_input" style="max-width:none;">'
                + '<div id="player'+f_id+'" style="min-height:100px;min-width:200px;display:none;"></div>'
                + '</div>')
                .appendTo( $inputdiv )
                .hide();

                /* Record Type help text for Record Editor */
                var $small_text = $('<br><div class="smallText" style="display:block;color:gray;font-size:smaller;">'
                    + 'Click image to freeze in place</div>')
                .clone()
                .insertAfter( $clear_container )
                .hide();

                /* urls for downloading and loading the thumbnail */
                var dwnld_link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&debug=1&download=1&file='+f_nonce;
                var url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+f_nonce+'&mode=tag&origin=recview'; 

                /* Anchors (download and show thumbnail) container */
                var $dwnld_anchor = $('<br><div class="download_link" style="font-size: smaller;">'
                    + '<a id="lnk'+f_id+'" href="#" oncontextmenu="return false;" style="display:none;padding-right:5px;text-decoration:underline;color:blue"'
                    + '>show thumbnail</a>'
                    + '<a id="dwn'+f_id+'" href="'+window.hWin.HEURIST4.util.htmlEscape(dwnld_link)+'" target="_surf" class="external-link image_tool'
                        + '"style="display:inline-block;text-decoration:underline;color:blue" title="Download image"><span class="ui-icon ui-icon-download" />download</a>'
                    + '</div>')
                .clone()
                .appendTo( $inputdiv )
                .hide();
                
                // Edit file's metadata
                var $edit_details = $('<span class="ui-icon ui-icon-pencil edit_metadata" title="Edit image metadata" style="cursor: pointer;padding-left:5px;">')
                .insertBefore($clear_container);
                this._on($edit_details, {
                    click: function(event){

                        let popup_opts = {
                            isdialog: true, 
                            select_mode: 'manager',
                            edit_mode: 'editonly',
                            rec_ID: f_id,
                            default_palette_class: 'ui-heurist-populate',
                            width: 950,
                            onClose: function(recset){

                                // update external reference, if necessary
                                if(window.hWin.HEURIST4.util.isRecordSet(recset)){

                                    let record = recset.getFirstRecord();

                                    let newvalue = {
                                        ulf_ID: recset.fld(record,'ulf_ID'),
                                        ulf_ExternalFileReference: recset.fld(record,'ulf_ExternalFileReference'),
                                        ulf_OrigFileName: recset.fld(record,'ulf_OrigFileName'),
                                        ulf_MimeExt: recset.fld(record,'fxm_MimeType'),
                                        ulf_ObfuscatedFileID: recset.fld(record,'ulf_ObfuscatedFileID')
                                    };

                                    that._newvalue = newvalue;
                                    that.findAndAssignTitle(newvalue);
                                }
                            }
                        };

                        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_opts);
                    }
                });
                if(!f_id || f_id < 0){
                    $edit_details.hide();
                }
                
                /* Change Handler */
                this._on($input,{change:function(event){
                    
                    /* new file values */
                    var val = that._newvalue;

                    if(window.hWin.HEURIST4.util.isempty(val) || !(val.ulf_ID >0)){
                        $input.val('');
                    }else{
                        var n_id = val['ulf_ID'];
                        var n_nonce = val['ulf_ObfuscatedFileID'];
                        var n_dwnld_link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&debug=2&download=1&file='+n_nonce;
                        var n_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+n_nonce+'&mode=tag&origin=recview';
                    
                        if(f_id != n_id){// If the image has been changed from original/or has been newly added

                            this._$('a#lnk'+f_id).attr({'id':'lnk'+n_id});
                            this._$('a#dwn'+f_id).attr({'id':'dwn'+n_id, 'href':n_dwnld_link});
                            this._$('div#player'+f_id).attr({'id':'player'+n_id});
                            this._$('img#img'+f_id).attr({'id':'img'+n_id});
                            let $edit_metadata = this._$('.edit_metadata');
                            /*
                            f_id = n_id;
                            f_nonce = n_nonce;
                            dwnld_link = n_dwnld_link;
                            url = n_url;                                                             
                            */
                            if(!n_id || n_id < 1){
                                $edit_metadata.hide();
                            }else{
                                $edit_metadata.show();
                            }
                        }
                        
                    }
                    
                    //clear thumb rollover
                    if(window.hWin.HEURIST4.util.isempty($input.val())){
                        that._input_img.find('img').attr('src','');
                    }

                    that.onChange(); 
                } });
                
                this._on($input,{mouseover: this._showImagePreview});
                this._on(this._input_img,{mouseover: this._showImagePreview}); //mouseover

                this._on($input, {mouseout:this._hideImagePreview});
                this._on(this._input_img, {mouseout:this._hideImagePreview});

                /* Source has loaded */
                function __after_image_load(){
                    setTimeout(() => {

                        let $img = that._input_img.find('img');
                        let $close_icon = $inputdiv.find('.ui-icon-window-close');

                        $close_icon.css('left', $img.outerWidth(true) + 10);
                    }, 500);
                };

                /* Thumbnail's click handler */
                this._on(this._input_img, {click:function(event){

                    var elem = event.target;
                    
                    if($(elem).hasClass('ui-icon-window-close')){
                        return;
                    }

                    if (this._isClicked==0 && !$inputdiv.find('div.smallText').hasClass('invalidImg')){
                        this._isClicked=1;
                        
                        that._off(this._input_img,'mouseout');

                        $inputdiv.find('div.smallText').hide(); // Hide image help text

                        $dwnld_anchor = $($(elem.parentNode.parentNode).find('div.download_link')); // Find the download anchors  !!!!!!!
                        
                        $dwnld_anchor.show();
                        $inputdiv.find('.ui-icon-window-close').show();

                        if ($dwnld_anchor.find('a#dwnundefined')){  // Need to ensure the links are setup
                            $dwnld_anchor.find('a#dwnundefined').attr({'id':'dwn'+f_id, 'href':dwnld_link});
                            $dwnld_anchor.find('a#lnkundefined').attr({'id':'lnk'+f_id, 'onClick':'window.hWin.HEURIST4.ui.hidePlayer('+f_id+', this.parentNode)'})
                        }

                        this._input_img.css('cursor', 'zoom-in');

                        if(this._input_img.find('img')[0].complete){
                            __after_image_load();
                        }else{
                            this._input_img.find('img')[0].addEventListener('load', __after_image_load);
                        }

                        window.hWin.HAPI4.save_pref('imageRecordEditor', 1);
                    }
                    else if (this._isClicked==1) {

                        /* Enlarge Image, display player */
                        if ($(elem.parentNode).hasClass("thumb_image")) {
                            $(elem.parentNode.parentNode).find('.hideTumbnail').hide();
                            $inputdiv.find('.ui-icon-window-close').hide();

                            this._input_img.css('cursor', 'zoom-out');

                            window.hWin.HEURIST4.ui.showPlayer(elem, elem.parentNode, f_id, url);
                        }
                        else {  // Srink Image, display thumbnail
                            $(this._input_img[1].parentNode).find('.hideTumbnail').show();
                            $inputdiv.find('.ui-icon-window-close').show();

                            this._input_img.css('cursor', 'zoom-in');
                        }
                    }
                }}); 

                // for closing inline image when 'frozen'
                var $hide_thumb = $('<span class="hideTumbnail" style="padding-right:10px;color:gray;cursor:pointer;" title="Hide image thumbnail">'
                                + 'close</span>').prependTo( $($dwnld_anchor[1]) ).show();
                // Alternative button for closing inline image
                var $alt_close = $('<span class="ui-icon ui-icon-window-close" title="Hide image display (image shows on rollover of the field)"'
                    + ' style="display: none;cursor: pointer;">&nbsp;</span>').appendTo( this._input_img[1] ); // .filter('div')

                this._on($hide_thumb.add($alt_close), {
                    click:function(event){

                        this._isClicked = 0;

                        that._on($input, {mouseout:this._hideImagePreview});
                        that._on(this._input_img, {mouseout:this._hideImagePreview});

                            $dwnld_anchor.hide();
                            $inputdiv.find('.ui-icon-window-close').hide();

                            if($inputdiv.find('div.smallText').find('div.smallText').hasClass('invalidImg')){
                            this._input_img.hide().css('cursor', '');
                        }else{
                            this._input_img.hide().css('cursor', 'pointer');
                        }
                    }
                });

                /* Show Thumbnail handler */
                $('#lnk'+f_id).on("click", function(event){
                    window.hWin.HEURIST4.ui.hidePlayer(f_id, event.target.parentNode.parentNode.parentNode); //!!!!!!!
                    
                    $(event.target.parentNode.parentNode).find('.hideTumbnail').show();
                });
                
                var $mirador_link = $('<a href="#" data-id="'+f_nonce+'" class="miradorViewer_link" style="color: blue;" title="Open in Mirador">'
                    +'<span class="ui-icon ui-icon-mirador" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    +'filter: invert(35%) sepia(91%) saturate(792%) hue-rotate(174deg) brightness(96%) contrast(89%);'
                    +'"></span>&nbsp;Mirador</a>').appendTo( $dwnld_anchor ).hide();
                    
                this._on($mirador_link, {click:function(event){
                    var obf_recID;
                    var ele = $(event.target)

                    if(!ele.attr('data-id')){
                        ele = ele.parents('[data-id]');
                    }
                    var obf_recID = ele.attr('data-id');
                    var is_manifest = (ele.attr('data-manifest')==1);

                    var url =  window.hWin.HAPI4.baseURL
                    + 'hclient/widgets/viewers/miradorViewer.php?db=' 
                    +  window.hWin.HAPI4.database
                    //A13 + '&recID=' + that.options.recID
                    + '&' + (is_manifest?'iiif':'iiif_image') + '=' + obf_recID;

                    if(true){
                        //borderless:true, 
                        window.hWin.HEURIST4.msg.showDialog(url, 
                            {dialogid:'mirador-viewer',
                                //resizable:false, draggable: false, 
                                //maximize:true, 
                                default_palette_class: 'ui-heurist-explore',
                                width:'90%',height:'95%',
                                allowfullscreen:true,'padding-content':'0px'});   

                        var $dlg = $(window.hWin?window.hWin.document:document).find('body #mirador-viewer');

                        $dlg.parent().css('top','50px');
                    }else{
                        window.open(url, '_blank');        
                    }                      

                    //data-id
                }});

                /* Check User Preferences, displays thumbnail inline by default if set */
                if (window.hWin.HAPI4.get_prefs_def('imageRecordEditor', 0)!=0 && this._newvalue.ulf_ID) {

                    this._input_img.show();
                    $dwnld_anchor.show();

                    $inputdiv.find('.ui-icon-window-close').show();

                    this._input_img.css('cursor', 'zoom-in');

                    $input.off("mouseout");

                    if(this._input_img.find('img')[0].complete){
                        __after_image_load();
                    }else{
                        this._input_img.find('img')[0].addEventListener('load', __after_image_load);
                    }

                    this._isClicked=1;
                }

                var __show_select_dialog = null;
                 
                var isTiledImage = this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                    && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'];
                 
                var popup_options = {
                    isdialog: true,
                    select_mode: 'select_single',
                    additionMode: isTiledImage?'tiled':'any',  //AAAA
                    edit_addrecordfirst: true, //show editor at once
                    select_return_mode:select_return_mode, //ids or recordset(for files)
                    filter_group_selected:null,
                    filter_groups: this.configMode.filter_group,
                    default_palette_class: 'ui-heurist-populate',
                    onselect:function(event, data){

                    if(data){
                        
                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                var recordset = data.selection;
                                var record = recordset.getFirstRecord();
                                
                                var newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                ulf_MimeExt: recordset.fld(record,'fxm_MimeType'),
                                                ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};
                                
                                that._newvalue = newvalue;
                                that.findAndAssignTitle(newvalue);
                            }
                        
                    }//data

                    }
                };//popup_options

                that.findAndAssignTitle(this._newvalue);

                __show_select_dialog = function(event){
                    
                        if(that.is_disabled) return;

                        event.preventDefault();
                        
                        var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                            {width: null,  //null triggers default width within particular widget
                            height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
            
                        popup_options.width = usrPreferences.width;
                        popup_options.height = usrPreferences.height;
                        var sels = this._newvalue;
                        if(!sels && this.options.values && this.options.values[0]){
                             sels = this.options.values[0];    //take selected value from options
                        } 

                        if($.isPlainObject(sels)){
                            popup_options.selection_on_init = sels;
                        }else if(!window.hWin.HEURIST4.util.isempty(sels)){
                            popup_options.selection_on_init = sels.split(',');
                        } else {
                            popup_options.selection_on_init = null;    
                        }                                                                                       
                        //init dialog to select related uploaded files
                        window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options);
                }
                
                if(__show_select_dialog!=null){
                    //no more buttons this._on( $btn_rec_search_dialog, { click: __show_select_dialog } );
                    this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
                    this._on( this._gicon, { click: __show_select_dialog } );
                }
                
    },
    
    _destroy: function() {
        
       if(this._gicon) this._gicon.remove();
       if(this._input_img) this._input_img.remove();
       if(this._input){
             if(this._input.fileupload('instance')!==undefined) this._input.fileupload('destroy');        
             this._input.remove();
       }
       
    },


    /**
    * 
    */
    setWidth: function(dwidth){
        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            this._input.css('width', dwidth);
        }
    },
    
    /**
    * put your comment there...
    */
    clearValue: function(){
        
        this._$('.thumb_image').hide();
        this._$('.fullSize').hide();
        this._$('.download_link').hide();
        this._$('#player'+this._newvalue.ulf_ID).hide();
        this._$(".smallText").text("Click image to freeze in place").css({
            "font-size": "smaller", 
            "color": "grey", 
            "position": "", 
            "bottom": ""
        });                        
        
        this._on(this._input, {mouseout:this._hideImagePreview});
        this._on(this._input_img, {mouseout:this._hideImagePreview});
        
        
        this._newvalue = '';    
        if(this._input){
            this._input.val('');   
        }
    },
    
    /**
    * 
    */
    findAndAssignTitle: function(value){
        
            if(!value){   //empty value
                window.hWin.HEURIST4.ui.setValueAndWidth(this._input, '');
                return;
            }

            var that = this;
            
            if($.isPlainObject(value) && value.ulf_ObfuscatedFileID){

                var rec_Title = value.ulf_ExternalFileReference;
                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                    rec_Title = value.ulf_OrigFileName;
                }
                window.hWin.HEURIST4.ui.setValueAndWidth(this._input, rec_Title, 10);

                //url for thumb
                if(!window.hWin.HEURIST4.util.isempty(value['ulf_ExternalFileReference']) && value.ulf_MimeExt == 'youtube'){ // retrieve youtube thumbnail

                    var youtube_id = window.hWin.HEURIST4.util.get_youtube_id(value.ulf_ExternalFileReference);

                    if(youtube_id){

                        this._$('.image_input > img').attr('src', 'https://img.youtube.com/vi/'+ youtube_id +'/default.jpg');
                        this._$('.smallText').text("Click image to freeze in place").css({
                            "font-size": "smaller", 
                            "color": "grey", 
                            "position": "", 
                            "top": ""
                        })
                        .removeClass('invalidImg');

                        this._newvalue = value;
                    }else{

                        this._$('.image_input > img').removeAttr('src');
                        this._$('.smallText').text("Unable to retrieve youtube thumbnail").css({
                            "font-size": "larger", 
                            "color": "black", 
                            "position": "relative", 
                            "top": "60px"
                        })
                        .addClass('invalidImg');

                        this._$('.hideTumbnail').trigger('click');
                    }

                    this._input.trigger('change');
                }else{ // check if image that can be rendered

                    window.hWin.HAPI4.checkImage("Records", value["ulf_ObfuscatedFileID"], null, function(response) {

                        if(response.data && response.status == window.hWin.ResponseStatus.OK) {
                            
                            that._input.attr('data-mimetype', response.data.mimetype);
                            
                            if(response.data.mimetype && response.data.mimetype.indexOf('image/')===0)
                            {
                                that._$('.image_input > img').attr('src',
                                    window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                                        value.ulf_ObfuscatedFileID);
                                        
                                if(response.data.width > 0 && response.data.height > 0) {

                                    that._$('.smallText').text('Click image to freeze in place').css({
                                        "font-size": "smaller", 
                                        "color": "grey", 
                                        "position": "", 
                                        "top": ""
                                    })
                                    .removeClass('invalidImg');

                                    that._newvalue = value;
                                }else{

                                    that._$('.image_input > img').removeAttr('src');
                                    that._$(".smallText").text("This file cannot be rendered").css({
                                        "font-size": "larger", 
                                        "color": "black", 
                                        "position": "relative", 
                                        "top": "60px"
                                    })
                                    .addClass('invalidImg');

                                    that._$('.hideTumbnail').trigger('click');
                                    that._$('.hideTumbnail').hide();
                                }
                                
                            }else{
                                that._$('.image_input').hide();
                                that._$('.hideTumbnail').hide();
                            }
                            
                            var mirador_link = that._$('.miradorViewer_link');
                            var mimetype = response.data.mimetype;
                            if(response.data.original_name.indexOf('_iiif')===0){
                                
                                if(response.data.original_name=='_iiif'){
                                    mirador_link.attr('data-manifest', '1');    
                                }
                                
                                mirador_link.show();
                            }else
                            if(mimetype.indexOf('image/')===0 || (
                                    (mimetype.indexOf('video/')===0 || mimetype.indexOf('audio/')===0) &&
                                 ( mimetype.indexOf('youtube')<0 && 
                                   mimetype.indexOf('vimeo')<0 && 
                                   mimetype.indexOf('soundcloud')<0)) ){
                                   
                                mirador_link.show();
                            }else{
                                mirador_link.hide();           
                            }
                            
                            
                            that._input.trigger('change');

                        }
                    });
                }
            }else{
                 //call server for file details
                 var recid = ($.isPlainObject(value))?value.ulf_ID :value;
                 if(recid>0){
                     
                     var request = {};
                        request['recID']  = recid;
                        request['a']          = 'search'; //action
                        request['details']    = 'list';
                        request['entity']     = 'recUploadedFiles';
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    var recordset = new hRecordSet(response.data);
                                    var record = recordset.getFirstRecord();

                                    if(record){
                                        var newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                    ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                    ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                    ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};

                                        that._newvalue = newvalue;
                                        that.findAndAssignTitle(newvalue);
                                    }
                                }
                            });
                 }
            }
        
    },
    
    /* Input element's hover handler */
    _showImagePreview: function(event){

        var imgAvailable = !window.hWin.HEURIST4.util.isempty(this._input_img.find('img').attr('src'));
        var invalidURL = this._$('div.smallText').hasClass('invalidImg');

        if((imgAvailable || invalidURL) && this._isClicked == 0){
            if (this._hideTimer) {
                window.clearTimeout(this._hideTimer);
                this._hideTimer = 0;
            }
            
            if(this._input_img.is(':visible')){
                this._input_img.stop(true, true).show();    
            }else{
                if(this._showTimer==0){
                    var that  = this;
                    this._showTimer = window.setTimeout(function(){
                        that._input_img.show();
                        that._$('div.smallText').show();
                        that._showTimer = 0;
                    },500);
                }
            }
        }
    },

    /* Input element's mouse out handler, attached and dettached depending on user preferences */
    _hideImagePreview: function(event)
    {
        if (this._showTimer) {
            window.clearTimeout(this._showTimer);
            this._showTimer = 0;
        }

        if(this._input_img.is(':visible')){
            
            //var ele = $(event.target);
            var ele = event.toElement || event.relatedTarget;
            ele = $(ele);
            if(ele.hasClass('image_input') || ele.parent().hasClass('image_input')){
                return;
            }

            var that = this;                                    
            this._hideTimer = window.setTimeout(function(){
                if(that._isClicked==0){
                    that._input_img.fadeOut(1000);
                    that._$('div.smallText').hide(1000);
                }
            }, 500);
        }
    },
    
});