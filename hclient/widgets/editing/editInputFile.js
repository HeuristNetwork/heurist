/**
* editInputGeo.js widget for input controls on edit form
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

$.widget( "heurist.editInputFile", $.heurist.editInputBase, {

    // default options
    options: {
    },

    _input_img: null,
    _newvalue: '',
    
    select_imagelib_dlg: null,
    progress_dlg: null,
    
    entity_image_already_uploaded: false,
    linkedImgInput: null, // invisible textbox that holds icon/thumbnail value
    linkedImgContainer: null, // visible div container displaying icon/thumbnail

    _gicon: null,
    
    _create: function() {
        
            var that = this;
            
            this._super();
        
            var $inputdiv = this.element;
            this._newvalue = this._value;
            
            if(!this._newvalue) this._newvalue = '';
          
            $inputdiv.uniqueId();
            
            //set input as file and hide
            var $input = $( '<input type="file">' )
            .addClass('text ui-widget-content ui-corner-all')
            .change(function(){
                    that.onChange();
            })
            .hide()
            .appendTo( $inputdiv );

            this._input = $input;
                
            var fileHandle = null; //to support file upload cancel
    
            this.options.showclear_button = (this.configMode.hideclear!=1);
            
            if(!this.configMode.version) this.configMode.version = 'thumb';
    
            //url for thumb
            var urlThumb = window.hWin.HAPI4.getImageUrl(this.configMode.entity, 
                                            this.options.recID, this.configMode.version, 1);
            var dt = new Date();
            urlThumb = urlThumb+'&ts='+dt.getTime();
            
            $input.css({'padding-left':'30px'});
            this._gicon = $('<span class="ui-icon ui-icon-folder-open"></span>')
                    .css({position: 'absolute', margin: '5px 0px 0px 8px'}).insertBefore( $input ); 
            
            var sz = 0;
            if(this.options.dtID=='rty_Thumb'){
                sz = 64;
            }else if(this.options.dtID=='rty_Icon'){
                sz = 16;
            }
                        
            //container for image
            this._input_img = $('<div tabindex="0" contenteditable class="image_input fileupload ui-widget-content ui-corner-all" style="border:dashed blue 2px;">'
                + '<img src="'+urlThumb+'" class="image_input" style="'+(sz>0?('width:'+sz+'px;'):'')+'">'
                + '</div>').appendTo( $inputdiv );                
            if(this.configMode.entity=='recUploadedFiles'){
               this._input_img.css({'min-height':'320px','min-width':'320px'});
               this._input_img.find('img').css({'max-height':'320px','max-width':'320px'});
            }
                         
            window.hWin.HAPI4.checkImage(this.configMode.entity, this.options.recID, 
                this.configMode.version,
                function(response){
                      if(response.data=='ok'){
                          that.entity_image_already_uploaded = true;
                      }
            });
                        
            //change parent div style - to allow special style for image selector
            if(that.configMode.css){
                that.element.css(that.configMode.css);
            }
            
            //library browser and explicit file upload buttons
            if(that.configMode.use_assets){
                
                var ele = $('<div style="display:inline-block;vertical-align:top;padding-left:4px" class="file-options-container" />')
                    .appendTo( $inputdiv );                            

                $('<a href="#" title="Select from a library of images"><span class="ui-icon ui-icon-grid"></span>Library</a>')
                    .click(function(){that.openIconLibrary()}).appendTo( ele );

                $('<br><br>').appendTo( ele );

                $('<a href="#" title="or upload a new image"><span class="ui-icon ui-icon-folder-open"></span><span class="upload-file-text">Upload file</span></a>')
                    .click(function(){ $input.click() }).appendTo( ele );
            }
                     
            //temp file name  it will be renamed on server to recID.png on save
            var newfilename = '~'+window.hWin.HEURIST4.util.random();

            //crate progress dialog
            this.progress_dlg = $('<div title="File Upload"><div class="progress-label">Starting upload...</div>'
            +'<div class="progressbar" style="margin-top: 20px;"></div>'
            +'<div style="padding-top:4px;text-align:center"><div class="cancelButton">Cancel upload</div></div></div>')
            .hide().appendTo( $inputdiv );
            
            let $progress_bar  = this.progress_dlg.find('.progressbar');
            let $progressLabel = this.progress_dlg.find('.progress-label');
            let $cancelButton  = this.progress_dlg.find('.cancelButton');

            this.select_imagelib_dlg = $('<div/>').hide().appendTo( $inputdiv );//css({'display':'inline-block'}).

            $progress_bar.progressbar({
                value: false,
                change: function() {
                    $progressLabel.text( "Current Progress: " + $progress_bar.progressbar( "value" ) + "%" );
                },
                complete: function() {
                    $progressLabel.html( "Upload Complete!<br>processing on server, this may take up to a minute" );
                    $cancelButton.hide().off('click');
                }
            });

            // Setup abort button
            $cancelButton.button();
            this._on($cancelButton, {
                click: function(){

                    if(fileHandle && fileHandle.abort){
                        fileHandle.message = 'File upload was aborted';
                        fileHandle.abort();
                    }

                    //fileHandle = true;
                }
            });
                        
        var max_file_size = Math.min(window.hWin.HAPI4.sysinfo['max_post_size'], window.hWin.HAPI4.sysinfo['max_file_size']);

        var fileupload_opts = {
    url: window.hWin.HAPI4.baseURL + 'hserv/controller/fileUpload.php',
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value:this.configMode.entity},
                {name:'version', value:this.configMode.version},
                {name:'maxsize', value:this.configMode.size}, //dimension
                {name:'registerAtOnce', value:this.configMode.registerAtOnce},
                {name:'recID', value:that.options.recID}, //need to verify permissions
                {name:'newfilename', value:newfilename }], //unique temp name
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
    //autoUpload: true,
    //multipart: (window.hWin.HAPI4.sysinfo['is_file_multipart_upload']==1),
    //to check file size on client side
    max_file_size: max_file_size,
    sequentialUploads: true,
    dataType: 'json',
    pasteZone: this._input_img,
    dropZone: this._input_img,
    
    add: function (e, data) {
        if (e.isDefaultPrevented()) {
            return false;
        }

        if(window.hWin.HAPI4.sysinfo['is_file_multipart_upload']!=1 && 
            data.files && data.files.length>0 && data.files[0].size>max_file_size)
        {
                data.message = `The upload size of ${data.files[0].size} bytes exceeds the limit of ${max_file_size}`
                +` bytes.<br><br>If you need to upload larger files please contact the system administrator ${window.hWin.HAPI4.sysinfo.sysadmin_email}`;

                data.abort();
                
        }else if (data.autoUpload || (data.autoUpload !== false &&
                $(this).fileupload('option', 'autoUpload'))) 
        {
            fileHandle = data;
            data.process().done(function () {
                data.submit();
            });
        }

    },
    submit: function (e, data) { //start upload
    
        that.progress_dlg = that.progress_dlg.dialog({
            autoOpen: false,
            modal: true,
            closeOnEscape: false,
            resizable: false,
            buttons: []
          });                        
        that.progress_dlg.dialog('open'); 
        that.progress_dlg.parent().find('.ui-dialog-titlebar-close').hide();
    },
    done: function (e, response) {
        
            //hide progress bar
            that.progress_dlg.dialog( "close" );
        
            if(response.result){//????
                response = response.result;
            }
            if(response.status==window.hWin.ResponseStatus.OK){
                var data = response.data;

                $.each(data.files, function (index, file) {
                    if(file.error){ //it is not possible we should cought it on server side - just in case
                        that._input_img.find('img').prop('src', '');
                        if(that.linkedImgContainer !== null){
                            that.linkedImgContainer.find('img').prop('src', '');
                        }

                        window.hWin.HEURIST4.msg.showMsgErr(file.error);
                    }else{

                        if(file.ulf_ID>0){ //file is registered at once and it returns ulf_ID
                            that._newvalue = file.ulf_ID;
                            if(that.linkedImgInput !== null){
//A13                                that.newvalues[that.linkedImgInput.attr('id')] = file.ulf_ID;
                            }
                        }else{
                            
                            //var urlThumb = window.hWin.HAPI4.getImageUrl(that.configMode.entity, 
                            //            newfilename+'.png', 'thumb', 1);
                            var urlThumb =
                            (that.configMode.entity=='recUploadedFiles'
                                ?file.url
                                :file[(that.configMode.version=='icon')?'iconUrl':'thumbnailUrl'])
                                +'?'+(new Date()).getTime();
                            
                            // file.thumbnailUrl - is correct but inaccessible for heurist server
                            // we get image via fileGet.php
                            that._input_img.find('img').prop('src', '');
                            that._input_img.find('img').prop('src', urlThumb);
                            
                            if(that.configMode.entity=='recUploadedFiles'){
                                that._newvalue = file;
                            }else{
                                that._newvalue = newfilename;  //keep only tempname
                            }
                        }
                        $input.attr('title', file.name);
                        that.onChange();//need call it manually since onchange event is redifined by fileupload widget
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);// .message
            }
            var inpt = this;
            that._input_img.off('click');
            that._input_img.on({click: function(){
                        $(inpt).trigger('click');
            }});
    },
    fail: function(e, data){

        if(that.progress_dlg.dialog('instance')){
            that.progress_dlg.dialog("close");   
        }
        
        if(!window.hWin.HEURIST4.util.isnull(fileHandle) && fileHandle.message){ // was aborted by user
            window.hWin.HEURIST4.msg.showMsgFlash(fileHandle.message, 3000);
        }else if( data.message ) {
            window.hWin.HEURIST4.msg.showMsgErr( data );
        }else {
            
            var msg = 'An unknown error occurred while attempting to upload your file.'
            
            if(data._response && data._response.jqXHR) {
                if(data._response.jqXHR.responseJSON){
                    msg = data._response.jqXHR.responseJSON;    
                }else if(data._response.jqXHR.responseText){
                    msg = data._response.jqXHR.responseText;    
                    let k = msg.indexOf('<p class="heurist-message">');
                    if(k>0){
                        msg = msg.substring(k);   
                    }
                }
            }
            
            window.hWin.HEURIST4.msg.showMsgErr(msg);
        }

        fileHandle = null;
    },
    progressall: function (e, data) { //@todo to implement
        var progress = parseInt(data.loaded / data.total * 100, 10);
        //$('#progress .bar').css('width',progress + '%');
        $progress_bar.progressbar( "value", progress );        
    }                            
                        };      
                        
    if(window.hWin.HAPI4.sysinfo['is_file_multipart_upload']==1){
        fileupload_opts['multipart'] = true;
        fileupload_opts['maxChunkSize'] = 10485760; //10M
    }
        
    var isTiledImage = that.configMode.tiledImageStack ||
                        (that.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                        && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
    if(isTiledImage){
        fileupload_opts['formData'].push({name:'tiledImageStack', value:1});
        fileupload_opts['formData'].push({name: 'acceptFileTypes', value:'zip|mbtiles'});
        
        $input.attr('accept','.zip, .mbtiles');
    }                
       
                        //init upload widget
                        this._input.fileupload( fileupload_opts );
                
                        //init click handlers
                        //this._on( $btn_fileselect_dialog, { click: function(){ this._input_img.click(); } } );
                        this._on(this._input_img,{click: function(e){ //find('a')
                            this._input.click(); //open file browse
                        }});
                    
        
    },
    
    _destroy: function() {
        
        if(this._input && this._input.fileupload('instance')!==undefined) this._input.fileupload('destroy');        
        if(this._input_img) this._input_img.remove();
        if(this._gicon) this._gicon.remove();
        if(this.progress_dlg) this.progress_dlg.remove();

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
    getValue: function(){
        return this._newvalue;    
    },
    
    clearValue: function(){
        
        this._newvalue = '';    
        if(this._input){
            //this._input.val('');   
        }
        
        this._$('img.image_input').prop('src','');

        if(this.linkedImgInput !== null){
            this.linkedImgInput.val('');
//A13            this.newvalues[that.linkedImgInput.attr('id')] = '';
            this.linkedImgInput.removeAttr('data-value');
        }
        if(this.linkedImgContainer !== null){
            this.linkedImgContainer.find('img').prop('src', '');
        }
        
    },
    
    /**
    * 
    */
    findAndAssignTitle: function(value){
        window.hWin.HEURIST4.ui.setValueAndWidth(this._input, value?value:'');
    },    
    
    //
    //
    //
    openIconLibrary: function(){                                 
        
        if(!(this.detailType=='file' && this.configMode.use_assets)) return;
        
        var that = this;
        
        this.select_imagelib_dlg.selectFile({
                source: 'assets'+(that.options.dtID=='rty_Icon'?'16':''), 
                extensions: 'png,svg',
                //size: 64, default value
                onselect:function(res){
            if(res){
                that._input_img.find('img').prop('src', res.url);
                that._newvalue = res.path;  //$input
                that.onChange(); 
                
                
                //HARDCODED!!!! sync icon or thumb to defRecTypes
                if(res.path.indexOf('setup/iconLibrary/')>0){
                    //sync paired value
                    var tosync = '', repl, toval;
                    if(that.options.dtID=='rty_Thumb'){ tosync = 'rty_Icon'; repl='64'; toval='16';}
                    else if(that.options.dtID=='rty_Icon'){tosync = 'rty_Thumb'; repl='16'; toval='64';}
               
                    if(tosync!=''){
                        
                        var ele = that.options.editing.getFieldByName(tosync);
                        if(ele){
                            var s_path = res.path;
                            var s_url  = res.url;
                            if(s_path.indexOf('icons8-')>0){
                                s_path = s_path.replace('-'+repl+'.png','-'+toval+'.png')
                                s_url = s_url.replace('-'+repl+'.png','-'+toval+'.png')
                            }
                            
                            var s_path2 = s_path.replace(repl,toval)
                            var s_url2 = s_url.replace(repl,toval)
                            
                            if(that.linkedImgContainer !== null && that.linkedImgInput !== null)
                            {
                                if(ele){
                                    ele.editing_input('setValue', s_path2 );
                                    ele.hide();
                                } 
                                
                                that.linkedImgInput.val( s_path2 );
                                that.linkedImgContainer.find('img').prop('src', s_url2 );
                            }else if(ele && ele.find('.image_input').length > 0){// elements in correct location
                                ele.find('.image_input').find('img').prop('src', s_url2); 
                            }                                

                        }
                    }
                }
                
            }
        }, assets:that.configMode.use_assets, size:that.configMode.size});
    },
    
    //
    // Link two image fields together, to perform actions (e.g. add, change, remove) on both fields, mostly for icon and thumbnail fields
    //
    linkIconThumbnailFields: function($img_container, $img_input){
        this.linkedImgContainer = $img_container;
        this.linkedImgInput = $img_input;
    },
    
    
});