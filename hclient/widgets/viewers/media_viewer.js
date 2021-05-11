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


$.widget( "heurist.media_viewer", {

    // default options
    options: {
        rec_Files: null
//        recdata: null
    },

    // the constructor
    _create: function() {
        this.mediacontent = this.element;
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
    }


    ,_renderFiles: function(title){

        this.mediacontent.empty();
        
        if($.isArray(this.options.rec_Files))
        {

            for (var idx in this.options.rec_Files){
                if(idx>=0){  //skip first
                    var file = this.options.rec_Files[idx];

                    var obf_recID = file[0];
                    var mimeType = file[1]; //($.isArray(file) ?file[2] :file ) ;

                    var fileURL = window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database //+ (needplayer?'&player=1':'')
                                 + '&file='+obf_recID;
                    
//console.log(mimeType);                    
                    if(mimeType && mimeType.indexOf('image')===0){
                        
                        var thumbURL = window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + '&thumb='+obf_recID
//                        var $alink = $("<a>",{href: fileURL, target:'_blank'})
                        var $alink = $("<a>",{href: fileURL, 'data-fancybox':'fb-images'})
                            .appendTo($("<div>").css({height:'auto','display':'inline-block','data-caption':title})
                            .appendTo(this.mediacontent));
                            
                            
                        $("<img>", {height:200, src: thumbURL, title:title}).appendTo($alink);
                        
                    }else if(mimeType && mimeType=='application/pdf'){

                        var fileURL_forembed = window.hWin.HAPI4.baseURL
                                + 'hsapi/controller/file_download.php?db=' 
                                + window.hWin.HAPI4.database + '&embedplayer=1&file='+obf_recID;
                                
                        /* inline html  */
                        $('<div style="display:none;width:80%;height:90%" id="pdf-viewer">'
                                + '<object width="100%" height="100%" name="plugin" data="'
                                + fileURL_forembed
                                + '" type="application/pdf"></object></div>').appendTo(this.mediacontent);
                                
                        var $alink = $("<a>",{href:'javascript:;', 'data-src':'#pdf-viewer', 'data-fancybox':'fb-images'})
                            .appendTo($("<div>").css({height:'auto','display':'inline-block','data-caption':title})
                            .appendTo(this.mediacontent));
                        
                        /*in iframe
                        
                        fileURL = fileURL + '&mode=tag';
                        
                        var $alink = $("<a>",{href: fileURL, 'data-type':'iframe', 'data-fancybox':'fb-images'})
                            .appendTo($("<div>").css({height:'auto','display':'inline-block','data-caption':title})
                            .appendTo(this.mediacontent));
                        */    
                            
                        var thumbURL = window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + '&thumb='+obf_recID
                        $("<img>", {height:200, src: thumbURL, title:title}).appendTo($alink);
                        
                    }else{
                        fileURL = fileURL + '&mode=tag';
                        $('<div>').load( fileURL )
                          .appendTo(this.mediacontent);
                    }
                }
            }

            this.mediacontent.show();

            
            if($.fancybox && $.isFunction($.fancybox)){
                    $.fancybox({parentEl:this.mediacontent.parent().attr('id'), 
                                selector : 'a[data-fancybox="fb-images"]', 
                                loop:true});
/*                
                    var container_id = this.mediacontent.attr('id');
                    $.fancybox({
                                selector : '#'+container_id+' > a[data-fancybox="fb-images"]', 
                                loop:true});
*/                                
            }
            
            // /\/redirects\/file_download.php\?db=(?:\w+)&id=(?:\w+)$/i});
        }
    }


});
