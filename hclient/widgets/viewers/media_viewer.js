/**
* View data for one record: loads data progressively in order shared, private, relationships, links
*
* Requires hclient/widgets/rec_actions.js (must be preloaded)
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

        //$(this.mediacontent).yoxview("unload");
        //$(this.mediacontent).yoxview("update");
        this.mediacontent.empty();
        
        if($.isArray(this.options.rec_Files))
        {

            for (var idx in this.options.rec_Files){
                if(idx>=0){  //skip first
                    var file = this.options.rec_Files[idx];

                    var obf_recID = file[0];
                    var mimeType = file[1]; //($.isArray(file) ?file[2] :file ) ;

                    var needplayer = mimeType && !(mimeType.indexOf('video')<0 && mimeType.indexOf('audio')<0);
                    var fileURL = window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database //+ (needplayer?'&player=1':'')
                                 + '&file='+obf_recID;
                    var thumbURL = window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + '&thumb='+obf_recID
                    
                    if(mimeType && mimeType.indexOf('video')===0){
                        
                         if(mimeType=='video/youtube'){
                            
                             $('<iframe width="640" height="360" src="'
                                    +fileURL
                                    +'" frameborder="0" allowfullscreen></iframe>').appendTo( this.mediacontent );
                             
                            //fileURL = 'https://youtu.be/48en7Bl_zCc'; 
                         }else if(mimeType=='video/mp4' || mimeType=='video/webm' || mimeType=='video/ogg'){
                             
                            

                            $('<video width="640" height="360"  controls="controls">'
                                +'<source type="'+mimeType+'" src="'+fileURL+'" />'
                                +'I\'m sorry; your browser doesn\'t support HTML5 video in WebM with VP8/VP9 or MP4 with H.264.'
                                +'</video>').appendTo( this.mediacontent );
                         }else{
                            var playerURL = window.hWin.HAPI4.baseURL + 'ext/mediaelement/flashmediaelement.swf';
                            $('<object width="640" height="360" type="application/x-shockwave-flash" data="'+playerURL+'">'
                                    +'<param name="movie" value="'+playerURL+'" />'
                                    +'<param name="flashvars" value="controls=true&file='+fileURL+'" />'
                                    +'<img src="'+thumbURL+'" width="320" height="240" title="No video playback capabilities" />'
                                +'</object>').appendTo( this.mediacontent );
                             
                         }
                        
                        //$( "<iframe>").attr('src', fileURL)
                        //    .css({xxxoverflow: 'none !important', width:'100% !important'}).appendTo( this.mediacontent );
                    }else if(mimeType && mimeType.indexOf('audio')===0){

                            $('<audio controls="controls">'
                                +'<source type="'+mimeType+'" src="'+fileURL+'" />'
                                +'</audio>').appendTo( this.mediacontent );
                        
                    }else{
                        var $alink = $("<a>",{href: fileURL, target:"yoxview" })
                            .appendTo($("<div>").css({height:'auto','display':'inline-block'}).appendTo(this.mediacontent));
                        $("<img>", {height:200, src: thumbURL, title:title}).appendTo($alink);
                    }
                }
            }

            this.mediacontent.show();

            /*if($.isFunction(this.mediacontent.yoxview)){
            $(this.mediacontent).yoxview("update");
            }else{

            }  */
            if($.isFunction(yoxview)){
                $(this.mediacontent).yoxview({ skin: "top_menu", allowedUrls: /\?db=(?:\w+)&file=(?:\w+)$/i});
            }
            // /\/redirects\/file_download.php\?db=(?:\w+)&id=(?:\w+)$/i});
        }
    }


});
