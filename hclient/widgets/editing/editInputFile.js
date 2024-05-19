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

    _inputs: {}, //file upload
    _inputs_img: {}, //visible box with image
    _newvalues: {},
    
    _main_version:'',
    
    select_imagelib_dlg: null, //dialog ot select icon or thumb form assets folder (image library)
    
    progress_dlg: null, //progress popup/dialog
    fileHandle: null, //progress data
    
    entity_image_already_uploaded: false,
    
    _gicon: null,
    
    _create: function() {
        
            var that = this;
            
            this._super();
        
            var $inputdiv = this.element;
            $inputdiv.uniqueId();

            if(!this.configMode.versions){
                this.configMode.versions = {'thumb':100};  
            }
            
            this._main_version = '';
            this._newvalues = {};
            this._inputs_img ={}
            this._inputs = {};

            this._eachVersion(version=>{    
                
                //hidden file upload input
                this._inputs[version] = $( '<input type="file">' )
                        .attr('data-version',version)
                        .hide().appendTo( $inputdiv );
                        
                this._on( this._input, { change:this.onChange });
                
                this._newvalues[version] = this.options.recID;

                //url for image
                var urlImage = window.hWin.HAPI4.getImageUrl(this.configMode.entity, 
                                                this.options.recID, version, 1);
                var dt = new Date();
                urlImage = urlImage+'&ts='+dt.getTime();
                
                var sz = this.configMode.versions[version];
                if(!(sz>0)) sz = 64;

                //visible image
                this._inputs_img[version] = $('<div tabindex="0" contenteditable class="image_input fileupload ui-widget-content ui-corner-all" '
                +'style="margin-right:20px;border:dashed blue 2px;min-width:'+(sz+20)+'px;min-height:'+(sz+20)+'px;">'
                + '<img src="'+urlImage+'" class="image_input" style="width:'+sz+'px;">'
                + '</div>').appendTo( $inputdiv );    
                
                if(this._main_version=='') this._main_version = version;
                
                this._initFileUpload(version);
            });
            
            if(this.configMode.entity=='recUploadedFiles'){
                this._inputs_img[this._main_version].css({'min-height':'320px','min-width':'320px'});
                this._inputs_img[this._main_version].find('img').css({'max-height':'320px','max-width':'320px'});
            }
            
            //if(this._newvalue=='') this._newvalue = this.options.recID; 
    
            /*            
            window.hWin.HAPI4.checkImage(this.configMode.entity, this.options.recID, 
                this.configMode.version,
                function(response){
                      if(response.data=='ok'){
                          that.entity_image_already_uploaded = true;
                      }
            });
            */
                        
            //change parent div style - to allow special style for image selector
            if(that.configMode.css){
                that.element.css(that.configMode.css);
            }
            
            //library browser and explicit file upload buttons
            if(that.configMode.use_assets){
                
                var linksContainer = $('<div style="display:inline-block;vertical-align:top;padding-left:4px;padding-top:4px;margin-right:20px;" class="file-options-container" />')
                    .insertBefore( this._inputs_img[this._main_version] );                            

                var selLib = $('<a href="#" title="Select from a library of images"><span class="ui-icon ui-icon-grid"></span>Library</a>')
                    .appendTo( linksContainer );
                this._on( selLib, { click: this.openIconLibrary });

                $('<br><br>').appendTo( linksContainer );

                var selFile = $('<a href="#" title="or upload a new image"><span class="ui-icon ui-icon-folder-open"></span><span class="upload-file-text">Upload file</span></a>')
                            .appendTo( linksContainer );
                this._on( selFile, { click:()=>this._inputs[this._main_version].trigger('click') }); //for the first one
                
                this.select_imagelib_dlg = $('<div>').hide().appendTo( $inputdiv );//css({'display':'inline-block'}).
            }
                     
            //crate progress dialog
            this.progress_dlg = $('<div title="File Upload"><div class="progress-label">Starting upload...</div>'
            +'<div class="progressbar" style="margin-top: 20px;"></div>'
            +'<div style="padding-top:4px;text-align:center"><div class="cancelButton">Cancel upload</div></div></div>')
            .hide().appendTo( $inputdiv );
            
            let $progress_bar  = this.progress_dlg.find('.progressbar');
            let $progressLabel = this.progress_dlg.find('.progress-label');
            let $cancelButton  = this.progress_dlg.find('.cancelButton');

            //init
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

            that.fileHandle = null; //to support file upload cancel
    
            // Setup abort button
            $cancelButton.button();
            this._on($cancelButton, {
                click: function(){

                    if(this.fileHandle && this.fileHandle.abort){
                        this.fileHandle.message = 'File upload was aborted';
                        this.fileHandle.abort();
                    }
                    //this.fileHandle = true;
                }
            });
            
                
            //this._gicon = $('<span class="ui-icon ui-icon-folder-open"></span>')
            //$input.css({'padding-left':'30px'});
            //.css({position: 'absolute', margin: '5px 0px 0px 8px'}).insertBefore( $input ); 
            
    },
    
    //
    //
    //
    _initFileUpload: function(version){
       
        var that = this;
        
        var $input = this._inputs[version];
        var $img_zone = this._inputs_img[version];
        
        var size = this.configMode.size>0?this.configMode.size:this.configMode.versions[version];
        
        var max_file_size = Math.min(window.hWin.HAPI4.sysinfo['max_post_size'], window.hWin.HAPI4.sysinfo['max_file_size']);

        //temp file name  it will be renamed on server to recID.png on save
        var newfilename = '~'+window.hWin.HEURIST4.util.random();
        
        var fileupload_opts = {
    url: window.hWin.HAPI4.baseURL + 'hserv/controller/fileUpload.php',
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value: this.configMode.entity},
                {name:'version', value: version},
                {name:'maxsize', value: size}, //dimension
                {name:'registerAtOnce', value: this.configMode.registerAtOnce},
                {name:'recID', value:that.options.recID}, //need to verify permissions
                {name:'newfilename', value:newfilename }], //unique temp name
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
    //autoUpload: true,
    //multipart: (window.hWin.HAPI4.sysinfo['is_file_multipart_upload']==1),
    //to check file size on client side
    max_file_size: max_file_size,
    sequentialUploads: true,
    dataType: 'json',
    pasteZone: $img_zone,
    dropZone: $img_zone,
    
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
            that.fileHandle = data;
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
                        that.clearValue(version);
                        window.hWin.HEURIST4.msg.showMsgErr(file.error);
                    }else{

                        if(file.ulf_ID>0){ //file is registered at once and it returns ulf_ID
                            that.setValue(version, file.ulf_ID, '');
                        }else{
                            
                            var urlThumb =
                            (that.configMode.entity=='recUploadedFiles'
                                ?file.url
                                :file[(version=='icon')?'iconUrl':'thumbnailUrl'])
                                +'?'+(new Date()).getTime();

                            var newvalue =
                                (that.configMode.entity=='recUploadedFiles')
                                ?file
                                :newfilename;  //keep only tempname
                            
                            // file.thumbnailUrl - is correct but inaccessible for heurist server
                            // we get image via fileGet.php
                            that.setValue(version, newvalue, urlThumb);
                        }
                        $input.attr('title', file.name);
                        that.onChange();//need call it manually since onchange event is redifined by fileupload widget
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);// .message
            }
            var inpt = this;
            $img_zone.off('click');
            $img_zone.on({click: function(){
                        $(inpt).trigger('click');
            }});
    },
    fail: function(e, data){

        if(that.progress_dlg.dialog('instance')){
            that.progress_dlg.dialog("close");   
        }
        
        if(!window.hWin.HEURIST4.util.isnull(that.fileHandle) && that.fileHandle.message){ // was aborted by user
            window.hWin.HEURIST4.msg.showMsgFlash(that.fileHandle.message, 3000);
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

        that.fileHandle = null;
    },
    progressall: function (e, data) { //@todo to implement
        var progress = parseInt(data.loaded / data.total * 100, 10);
        
        let $progress_bar  = that.progress_dlg.find('.progressbar');
        $progress_bar.progressbar( "value", progress );        
    }                            
                        };   //end fileupload_opts   
                        
        if(window.hWin.HAPI4.sysinfo['is_file_multipart_upload']==1){
            fileupload_opts['multipart'] = true;
            fileupload_opts['maxChunkSize'] = 10485760; //10M
        }
            
        var isTiledImage = this.configMode.tiledImageStack ||
                            (that.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                            && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
        if(isTiledImage){
            fileupload_opts['formData'].push({name:'tiledImageStack', value:1});
            fileupload_opts['formData'].push({name: 'acceptFileTypes', value:'zip|mbtiles'});
            
            $input.attr('accept','.zip, .mbtiles');
        }                
       
        //init upload widget
        $input.fileupload( fileupload_opts );

        //init click handlers
        this._on($img_zone,{click: function(e){ 
                $input.trigger('click'); //open file browse
        }});
        
    },
    
    //
    //
    //
    _destroy: function() {

        this._eachVersion(version=>{
            if(this._inputs[version].fileupload('instance')!==undefined) this._inputs[version].fileupload('destroy');        
            this._inputs[version].remove();
            if(this._inputs_img[version]) this._inputs_img[version].remove();
        });

        if(this._gicon) this._gicon.remove();
        if(this.progress_dlg) this.progress_dlg.remove();
        if(this.select_imagelib_dlg) this.select_imagelib_dlg.remove();
    },
    
    _eachVersion: function(callback){
        
        for (var version in this.configMode.versions) {
            if (!this.configMode.versions.hasOwnProperty(version)) continue;
            callback(version);   
        }
    },


    /**
    * 
    */
    setWidth: function(dwidth){
    },
    
    setValue: function(version, val, url){
        this._newvalues[version] = val;    
        this._inputs_img[version].find('img').prop('src', url);    
    },

    getValue: function(){
        
        if(Object.keys(this._newvalues).length==1 && this._main_version!=''){
            return this._newvalues[this._main_version];
        }else{
            return window.hWin.HEURIST4.util.cloneJSON(this._newvalues);
        }
        
    },
    
    /**
    * put your comment there...
    */
    clearValue: function( version_clear ){
            this._eachVersion(version=>{
                if(!version_clear || version==version_clear){
                    this._newvalues[version] = 'delete';
                    this._inputs_img[version].find('img').attr('src','');
                    //default image
                    var urlImage = window.hWin.HAPI4.getImageUrl(this.configMode.entity, 
                                                0, version, 1);
                    this._inputs_img[version].find('img').attr('src',urlImage);
                }
            });
    },
    
    //
    //
    //
    openIconLibrary: function(){                                 
        
        if(!this.configMode.use_assets) return;
        
        var that = this;
        
        var size = this.configMode.versions[this._main_version];
        
        this.select_imagelib_dlg.selectFile({
                source: 'assets'+size, 
                extensions: 'png,svg',
                //size: 64, default value
                onselect:function(res){
            if(res){

                that.setValue(that._main_version, res.path, res.url);
                that.onChange(); 
                
                //HARDCODED!!!! sync icon or thumb for defRecTypes
                if(res.path.indexOf('setup/iconLibrary/')>0){
                    //sync paired value
                    var tosync = '', repl, toval;
                    if(that._main_version=='icon'){tosync = 'thumb'; repl='16'; toval='64';}
                    else if(that._main_version=='thumb'){ tosync = 'icon'; repl='64'; toval='16';}
               
                    if(tosync!='' && that.configMode.versions[tosync])
                    {
                            var s_path = res.path;
                            var s_url  = res.url;
                            if(s_path.indexOf('icons8-')>0){
                                s_path = s_path.replace('-'+repl+'.png','-'+toval+'.png')
                                s_url = s_url.replace('-'+repl+'.png','-'+toval+'.png')
                            }
                            
                            var s_path2 = s_path.replace(repl,toval)
                            var s_url2 = s_url.replace(repl,toval)
                            
                            that.setValue(tosync, s_path2, s_url2);
                    }
               }
                
            }
        }, assets:that.configMode.use_assets, size:size});
    },
});