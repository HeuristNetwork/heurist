/**
* View data for one record
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
        rec_Files: null, //array of objects {id ,mimeType,filename,extrernal}
        openInPopup: true, //show video in popup
        showLink: false, // show link to open full view in new tab or download
        baseURL: null,
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
        
        this._renderFiles();
    }, //end _create

    _setOptions: function() {
        this._superApply( arguments );
        this._refresh();
    },
    
    _refresh: function(){
        this._renderFiles();                  
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        this.mediacontent.empty();
//        $(document).unbind('click.fb-start');
//console.log('_destroy');
    }


    ,_renderFiles: function(title){

        this.mediacontent.empty();
        
        if($.isArray(this.options.rec_Files))
        {

            for (var idx in this.options.rec_Files){
                if(idx>=0){  //skip first
                    var file = this.options.rec_Files[idx];
                    
                    var obf_recID, mimeType, filetitle = '', filename = null, external_url= null;
                    
                    if($.isPlainObject(file)){
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
                            .attr('data-id', obf_recID)
                            .appendTo($alink);

                    
                    if(filename === '_iiif'){
                        
                        external_url =  this.options.baseURL + "hclient/widgets/viewers/miradorViewer.php?db=" 
                                 +  this.options.database
                                 + '&manifest='+obf_recID;
                        
                        this._on($alink,{click:function(e){
                             
      var obf_recID = $(e.target).attr('data-id');
      var url =  this.options.baseURL + "hclient/widgets/viewers/miradorViewer.php?db=" +  this.options.database
                                 + '&manifest='+obf_recID;                            
                                 
//console.log(url)                                 
      if(window.hWin && window.hWin.HEURIST4){
            //borderless:true, 
            window.hWin.HEURIST4.msg.showDialog(url, {dialogid:'mirador-viewer',
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
                                
                        /* inline html  */
                        $('<div style="display:none;width:80%;height:90%" id="pdf-viewer">'
                                + '<object width="100%" height="100%" name="plugin" data="'
                                + fileURL_forembed
                                + '" type="application/pdf"></object></div>').appendTo(this.mediacontent);

                        $alink.attr('href','javascript:;').attr('data-src','#pdf-viewer').attr('data-myfancybox','fb-images');
                        /*        
                        var $alink = $("<a>",{href:'javascript:;', 'data-src':'#pdf-viewer', 'data-myfancybox':'fb-images'})
                            .appendTo($("<div>").css({height:'auto','display':'inline-block','data-caption':title})
                            .appendTo(this.mediacontent));
                        */    
                        
                        /*in iframe
                        
                        fileURL = fileURL + '&mode=tag';
                        
                        var $alink = $("<a>",{href: fileURL, 'data-type':'iframe', 'data-myfancybox':'fb-images'})
                            .appendTo($("<div>").css({height:'auto','display':'inline-block','data-caption':title})
                            .appendTo(this.mediacontent));
                        */    
                        
                    }else if(mimeType=='application/pdf' || mimeType.indexOf('audio/')===0 || mimeType.indexOf('video/')===0){

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
                }
                
                if(this.options.showLink){
                    $('<br>').appendTo(this.mediacontent);
                    if(external_url || filename === '_iiif' || filename === '_remote') //filename === '_iiif' || filename === '_remote' || filename.indexOf('_tiled')===0 || 
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

            var fancy_opts = { selectorParentEl: $('body'), 
                                selector : 'a[data-myfancybox="fb-images"]', 
                                loop:true};
            
            if(window.hWin.HAPI4 && window.hWin.HAPI4.fancybox){ // && $.isFunction($.fancybox)
//console.log('>>>> '+$.isFunction($.fancybox)+'  '+$.isFunction($.fn.fancybox));                
                    //$(window.hWin.document)
                    $('body').unbind('click.fb-start');
                    window.hWin.HAPI4.fancybox( fancy_opts );
/*                
                    var container_id = this.mediacontent.attr('id');
                    $.fancybox({
                                selector : '#'+container_id+' > a[data-myfancybox="fb-images"]', 
                                loop:true});
*/                                
            }else if ($.isFunction($.fn.fancybox)){
                    $('body').unbind('click.fb-start');
                    $.fn.fancybox( fancy_opts );
            }
            
            // /\/redirects\/file_download.php\?db=(?:\w+)&id=(?:\w+)$/i});
        }
    }


});
