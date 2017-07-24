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
                    var file_param = file[1]; //($.isArray(file) ?file[2] :file ) ;

                    var needplayer = file_param && !(file_param.indexOf('video')<0 && file_param.indexOf('audio')<0);

                    // <a href="images/large/01.jpg"><img src="images/thumbnails/01.jpg" alt="First" title="The first image" /></a>
                    // <a href="http://dynamic.xkcd.com/random/comic/?width=880" target="yoxview"><img src="../images/items/thumbnails/xkcd.jpg" alt="XKCD" title="Random XKCD comic" /></a>

                    var $alink = $("<a>",{href: window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + (needplayer?'&player=1':'') + '&file='+obf_recID, target:"yoxview" })
                    .appendTo($("<div>").css({height:'auto','display':'inline-block'}).appendTo(this.mediacontent));
                    $("<img>", {height:150, src: window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database + '&thumb='+obf_recID, title:title}).appendTo($alink);


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


});
