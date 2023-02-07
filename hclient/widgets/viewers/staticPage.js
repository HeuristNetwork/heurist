/**
* Content of options.url to be loaded either in iframe or in widget element
* Url may have [layout] and [dbname] parameters
* 
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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


$.widget( "heurist.staticPage", {

    // default options
    options: {
        title: '',
        url:null,
        isframe: false
    },

    _loaded_url:null,

    // the constructor
    _create: function() {

        var that = this;

        this.div_content = $('<div>').css({width:'100%', height:'100%'})  //.css('overflow','auto')
        /*.css({
        position:'absolute', top:(this.options.title==''?0:'2.5em'), bottom:0, left:0, right:0,
        'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center'})*/
        .appendTo( this.element );

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });

        
        $(this.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS, function(e, data) {
            that._loaded_url = null; //reload on login-logout
            that._refresh();
        });
        
        that._refresh();
        //$(this.document).on(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, function(e, data) {});

    }, //end _create


    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },
    /*
    _setOption: function( key, value ) {
    if(key=='url'){
    value = window.hWin.HAPI4.baseURL + value;
    }else if (key=='title'){
    var id = this.element.attr('id');
    $(".header"+id).html(value);
    $('a[href="#'+id+'"]').html(value);
    }

    this._super( key, value );
    this._refresh();
    },*/

    /* private function */
    _refresh: function(){

        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }

        //refesh if element is visible only - otherwise it costs much resources
        if(!this.element.is(':visible') || window.hWin.HEURIST4.util.isempty(this.options.url)) return;

        //if(this.dosframe.attr('src')!==this.options.url){
        if(this._loaded_url!==this.options.url){

            var url = this.options.url.replace("[dbname]",  window.hWin.HAPI4.database);
            url = url.replace("[layout]",  window.hWin.HAPI4.sysinfo['layout']);
            if(this.options.url.indexOf('http://')<0 && this.options.url.indexOf('https://')<0){
                this.options.url = window.hWin.HAPI4.baseURL + url;
            }

            
            if(this.options.isframe){
                if(!this.dosframe){
                    var that = this;
                    this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( this.div_content );
                    this.dosframe.on('load', function(){
                        that.loadanimation(false);
                    })
                }
                this.loadanimation(true);
                this.dosframe.attr('src', this.options.url);
            }else{
                //var that=this;
                $(this.div_content).load(this.options.url); //, function(){ that.loadanimation(false); });
            }
            this._loaded_url = this.options.url;
            //
        }

    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        //$(this.document).off(window.hWin.HAPI4.Event.ON_SYSTEM_INITED);

        // remove generated elements
        //this.dosframe.remove();
        this.div_content.remove();
    },

    loadanimation: function(show){
        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },

});
