/**
* Media Viewer. It accept list of media in rec_Files or fills it with search_initial
* It may work in 3 modes
* 1) creates thumbnails and opens fancybox on click
* 2) use existing thumbnails with attributes data-id or data-recid 
* 3) opens fancybox on method show
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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


$.widget( "heurist.mediaViewer", {

    // default options
    options: {
        rec_Files: null, //array of objects {rec_ID, (obfuscation_file_)id, mimeType, filename, extrernal}
        search_initial:null, //if rec_Files are not defined - use this search query to retrieve rec_Files
        
        selector: null,  //if defined it does not render thumbnails, it searches for elements that will trigger fancybox
        
        openInPopup: true, //show video in popup
        showLink: false,   // show link to open full view in new tab or download
        
        baseURL: null,  //define when mediaViewer is run outside standard Heurist environment
        database: null
    },

    // the constructor
    _create: function() {
        this.mediacontent = this.element;
        
        if(window.hWin && window.hWin.HAPI4){
            if(!this.options.baseURL){
                this.options.baseURL = window.hWin.HAPI4.baseURL;
            }
            if(!this.options.database){
                this.options.database = window.hWin.HAPI4.database;
            }
        }
        
        this._refresh();
        
    }, //end _create
    
    _setOptions: function() {
        this._superApply( arguments );
        this._refresh();
    },
    
    _refresh: function(){
        
        if(this.options.search_initial)
        {
            
            var request = {
                    q: this.options.search_initial,
                    restapi: 1,
                    zip: 1,
                    extended: 3, 
                    format:'json'};
                        
            var that = this;
                        
            window.hWin.HAPI4.RecordMgr.search_new(request, function(response){
                if(window.hWin.HEURIST4.util.isJSON(response)) {
                   that.options.rec_Files = response['records'];
                   if(that.options.rec_Files && that.options.rec_Files.length>0){
                        that._initControls();    
                   }                
                   
                }else{
                   window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
            
        }else{
            this._initControls();    
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        this.mediacontent.empty();
//        $(document).unbind('click.fb-start');
//console.log('_destroy');
    }

    //
    // init controls
    //    
    ,_initControls: function(){
        
        if(this.options.selector){
           //thumbnails already exist
           this._initThumbnails( this.options.selector );
        }else{
            this._renderThumbnails();
        }
        
    }

    //
    //
    //    
    ,_renderThumbnails: function(title){

        this.mediacontent.empty();
        
        if($.isArray(this.options.rec_Files))
        {

            for (var idx in this.options.rec_Files){
                if(idx>=0){ 
                    var file = this.options.rec_Files[idx];
                    
                    var obf_recID, mimeType, filetitle = '', filename = null, external_url= null, rec_ID=0;
                    
                    if($.isPlainObject(file)){
                        rec_ID = file.rec_ID;
                        obf_recID = file.id;
                        mimeType = file.mimeType;
                        filename = file.filename; //to detect _iiif or _tiled
                        filetitle = file.title;
                        external_url = file.external;
                    }else{
                        obf_recID = file[0];
                        mimeType = file[1]; //($.isArray(file) ?file[2] :file ) ;
                    }
                    if(!filetitle) filetitle = title;
                    if(!mimeType) mimeType = '';

                    var fileURL = this.options.baseURL+'?db=' + this.options.database //+ (needplayer?'&player=1':'')
                                 + '&file='+obf_recID;

                    var thumbURL =  this.options.baseURL+'?db=' +  this.options.database 
                                 + '&thumb='+obf_recID


                    //thumbnail preview
                    var $alink = $('<a>')
                            .attr('data-id', obf_recID)
                            .appendTo($("<div>").css({cursor:'pointer',height:'auto','display':'inline-block','data-caption':filetitle})
                            .appendTo(this.mediacontent));
                        
                    $('<img>', {src: thumbURL, title:filetitle})
                            .css({border: '2px solid #FFF', margin:'5px', 'box-shadow': '0 2px 4px #bbb', width:'200px'})
                            .appendTo($alink);

                    /*
                    if(filename && filename.indexOf('_iiif') === 0){ //manifest

                        var param = 'manifest';
                        if(filename == '_iiif_image'){
                            param = 'q'; //it adds format=iiif in miradorViewer.php
                            obf_recID = 'ids:'+rec_ID;
                            if(rec_ID>0) $alink.attr('data-id', obf_recID);
                        }
                        $alink.attr('data-iiif', param);
                        
                    
                        //for link below thumb                        
                        external_url =  this.options.baseURL 
                                 + "hclient/widgets/viewers/miradorViewer.php?db=" 
                                 +  this.options.database
                                 + '&' + param + '='+obf_recID;
//console.log(external_url);                        
                        //on thumbnail click
                        this._on($alink,{click:function(e){
                            
                              var ele = $(e.target)
                              ele = ele.is('a')?ele:ele.parent();
                              
                              var param  = ele.attr('data-iiif');
                              var obf_recID = ele.attr('data-id');
                              
                              var url =  this.options.baseURL 
                                    + "hclient/widgets/viewers/miradorViewer.php?db=" 
                                    +  this.options.database
                                    + '&' + param + '='+obf_recID;
                                 
                                
                              if(window.hWin && window.hWin.HEURIST4){
                                    //borderless:true, 
                                    window.hWin.HEURIST4.msg.showDialog(url, 
                                        {dialogid:'mirador-viewer',
                                         resizable:false, draggable: false, maximize:true, //width:'100%',height:'100%',
                                         allowfullscreen:true,'padding-content':'0px'});   
                              }else{
                                    window.open(url, '_blank');        
                              }
      
                        }});
                        
                    }else
                    if(mimeType.indexOf('image')===0){
                        
                        $alink.attr('href',external_url?external_url:fileURL).attr('data-myfancybox','fb-images');
                    }else 
                    if(false && mimeType=='application/pdf'){

                        var fileURL_forembed =  this.options.baseURL
                                + 'hsapi/controller/file_download.php?db=' 
                                +  this.options.database + '&embedplayer=1&file='+obf_recID;
                                
                        $('<div style="display:none;width:80%;height:90%" id="pdf-viewer">'
                                + '<object width="100%" height="100%" name="plugin" data="'
                                + fileURL_forembed
                                + '" type="application/pdf"></object></div>').appendTo(this.mediacontent);

                        $alink.attr('href','javascript:;').attr('data-src','#pdf-viewer').attr('data-myfancybox','fb-images');
                    }
                    else if(mimeType=='application/pdf' || mimeType.indexOf('audio/')===0 || mimeType.indexOf('video/')===0){

                        external_url = fileURL  + '&mode=page';
                        fileURL = fileURL  + '&mode=tag&fancybox=1';
                        
                        
                        if(this.options.openInPopup && mimeType.indexOf('audio/')!==0){
                            
                            $alink.attr('href','javascript:;')
                                .attr('data-src', fileURL)
                                .attr('data-type', 'ajax')
                                .attr('data-myfancybox','fb-images');
                            
                            
                        }else{
                            $alink.hide();
                            var ele = $('<div>').css({width:'90%',height:'160px'}).load( fileURL );
                            ele.appendTo(this.mediacontent);
                        }
                    }else{
                        //just thumbnail
                        $alink.css('cursor','default');
                    }
                    */
                }
                
                if(this.options.showLink){
                    $('<br>').appendTo(this.mediacontent);
                    if(external_url || filename === '_iiif' || filename === '_remote')  //@todo check preferred source
                    {
                        if(!external_url) external_url = fileURL; 
                        
                        $('<a>', {href:external_url, target:'_blank'})
                                .text('OPEN IN NEW TAB')
                                .addClass('external-link')
                                .appendTo(this.mediacontent);
                    }else{
                        $('<a>', {href:(fileURL+'&download=1'), target:'_surf'})   //&debug=3
                                .text('DOWNLOAD')
                                .addClass('external-link image_tool')
                                .appendTo(this.mediacontent);
                    }
                    $('<br>').appendTo(this.mediacontent);
                }

                
            }

            this.mediacontent.show();
            
            this._initThumbnails('a[data-id]');

            /*
            var fancy_opts = { selectorParentEl: this.mediacontent, //$('body'), 
                                selector : 'a[data-myfancybox="fb-images"]', 
                                loop:true};
            
            if(window.hWin.HAPI4 && window.hWin.HAPI4.fancybox){ // && $.isFunction($.fancybox)
                    $('body').unbind('click.fb-start');
                    window.hWin.HAPI4.fancybox( fancy_opts );
            }else if ($.isFunction($.fn.fancybox)){
                    $('body').unbind('click.fb-start');
                    $.fn.fancybox( fancy_opts );
            }
            */
            
        }
    }
    
    //
    //
    //
    ,_initThumbnails: function(selector){

        var eles = this.mediacontent.find(selector);
        var that = this;
        
        $.each(eles, function(idx, $alink){
            
            $alink = $($alink);
            var recid = $alink.attr('data-id');

            if(recid)
            for (var idx in that.options.rec_Files){
                if(idx>=0){  //skip first
                    var file = that.options.rec_Files[idx];
                    if(recid && recid==file.rec_ID || recid==file.id){ 
                        //found
                        var rec_ID = file.rec_ID,
                        obf_recID = file.id,
                        mimeType = file.mimeType,
                        filename = file.filename, //to detect _iiif or _tiled
                        filetitle = file.title,
                        external_url = file.external;
                    
                        if(!mimeType) mimeType = '';

                        var fileURL = that.options.baseURL+'?db=' + that.options.database //+ (needplayer?'&player=1':'')
                                     + '&file='+obf_recID;

                        var thumbURL =  that.options.baseURL+'?db=' +  that.options.database 
                                     + '&thumb='+obf_recID
                    
                        if(filename && filename.indexOf('_iiif') === 0){ //manifest

                            var param = 'manifest';
                            if(filename == '_iiif_image'){
                                
                                if(rec_ID>0){
                                    //param = 'q'; //it adds format=iiif in miradorViewer.php
                                    //obf_recID = 'ids:'+rec_ID;
                                    //$alink.attr('data-id', obf_recID);  
                                    param = 'q=ids:'+rec_ID;
                                }else{
                                    param = 'iiif_image='+obf_recID;
                                } 
                            }else{
                                //param = 'manifest='+obf_recID;    
                                param = 'iiif='+obf_recID;    
                            }
                            
                            
                            $alink
                                .css('cursor','pointer')
                                .attr('data-id', obf_recID)                            
                                .attr('data-iiif', param);
                            
                        
                            //for link below thumb                        
                            external_url =  that.options.baseURL 
                                     + "hclient/widgets/viewers/miradorViewer.php?db=" 
                                     +  that.options.database
                                     + '&' + param; // + '='+obf_recID;
    console.log(external_url);                        
                            //on thumbnail click
                            that._on($alink, {click:function(e){
                                
                                  var param, obf_recID;
                                
                                  var ele = $(e.target)
                                  if(ele.attr('data-iiif')){
                                      param  = ele.attr('data-iiif');
                                      obf_recID = ele.attr('data-id');
                                  }else{
                                      ele = ele.parents('[data-iiif]');
                                      param  = ele.attr('data-iiif');
                                      obf_recID = ele.attr('data-id');
                                  }
                                  
                                  var url =  that.options.baseURL 
                                        + "hclient/widgets/viewers/miradorViewer.php?db=" 
                                        +  that.options.database
                                        + '&' + param;// + '='+obf_recID;
                                     
                                    
                                  if(window.hWin && window.hWin.HEURIST4){
                                        //borderless:true, 
                                        window.hWin.HEURIST4.msg.showDialog(url, 
                                            {dialogid:'mirador-viewer',
                                             //resizable:false, draggable: false, 
                                             //maximize:true, 
                                             default_palette_class: 'ui-heurist-explore',
                                             width:'90%',height:'95%',
                                             allowfullscreen:true,'padding-content':'0px'});   
                                             
                                        $dlg = $(window.hWin?window.hWin.document:document).find('body #mirador-viewer');
                                        
                                        $dlg.parent().css('top','50px');
                                  }else{
                                        window.open(url, '_blank');        
                                  }
          
                            }});
                            
                        }else
                        if(mimeType.indexOf('image')===0){
                            $alink.attr('data-href', external_url?external_url:fileURL+'&fancybox=1')
                                  .attr('data-type', 'image')
                                  .attr('data-src', external_url?external_url:fileURL+'&fancybox=1')
                                  .attr('data-myfancybox','fb-images')
                                  .css('cursor','pointer')
                                  .attr('data-thumb', thumbURL);
                            
                            if(file.caption) $alink.attr('data-caption', file.caption);
                            
                        }else
                        if(mimeType=='application/pdf' || mimeType.indexOf('audio/')===0 || mimeType.indexOf('video/')===0){

                            external_url = fileURL  + '&mode=page';
                            //fileURL = fileURL  + '&mode=tag&fancybox=1';
                            
                            if(that.options.selector || (that.options.openInPopup && mimeType.indexOf('audio/')!==0)){
                                
                                fileURL = fileURL  + '&mode=tag&fancybox=1';
                                
                                $alink.attr('data-href','javascript:;')
                                    .attr('data-src', fileURL)
                                    .attr('data-type', 'ajax')
                                    .attr('data-myfancybox','fb-images')
                                    .css('cursor','pointer')
                                    .attr('data-thumb', thumbURL);
                                
                                if(file.caption) $alink.attr('data-caption', file.caption);
                                
                            }else{
                                fileURL = fileURL  + '&mode=tag';
                                $alink.hide();
                                var ele = $('<div>').css({width:'90%',height:'160px'}).load( fileURL );
                                ele.insertAfter($alink);
                            }
                        }                        
                    }
                }
            }//for
            
        });
        
        
        var fancy_opts = { selectorParentEl: this.mediacontent, //$('body'), 
                            selector : '[data-myfancybox="fb-images"]', 
                            loop:true};
        $('body').unbind('click.fb-start');
        //this.mediacontent.off("click.fb-start", '[data-myfancybox="fb-images"]');
        
        if(window.hWin && window.hWin.HAPI4 && window.hWin.HAPI4.fancybox){ 
                window.hWin.HAPI4.fancybox( fancy_opts );
        }else if ($.isFunction($.fn.fancybox)){
                $.fn.fancybox( fancy_opts );
        }
        
    },

    //
    // opens explicitely
    //    
    show: function (){
        this.mediacontent.find(this.options.selector+':first').click();
    },
    
    //
    // removes of event handlers 
    //
    clearAll: function (){

        if(this.options.selector){
            this.mediacontent
                    .off("click.fb-start", '[data-myfancybox="fb-images"]'); //this.options.selector
        }
    }
    


});
