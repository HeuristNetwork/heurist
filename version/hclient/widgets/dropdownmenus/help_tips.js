/**
* help_tips.js: used to pop up initial tip (when empty database) on how to get started, plus additional tips
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


$.widget( "heurist.help_tips", {

    // default options
    options: {
        isdialog: true, //show in dialog or embedded

        current_tip: 0  //
    },
    
    last_tip_idx:-1,

    // the constructor
    _create: function() {

        var that = this;

        this.wcontainer = $("<div>");

        if(this.options.isdialog){

            this.wcontainer
            .css({overflow: 'none !important', width:'100% !important'})
            .appendTo(this.element);

            this.element.css({overflow: 'none !important'})

            this.element.dialog({
                autoOpen: false,
                height: 650,
                width: 930,
                modal: true,
                title: window.hWin.HR("Welcome to Heurist"), //Tip of the day
                resizeStop: function( event, ui ) {
                    //that.wcontainer.css('width','100%');
                    //.css({clear:'both'})
                    that.element.css({overflow: 'none !important','width':'100%'}); //,'height': that.element.parent().css('height')-90});
                },
                buttons: [
                    {text:window.hWin.HR('Previous'),
                        id: 'btnTipPrev',
                        title: window.hWin.HR("Show previous tip"),
                        class: 'tags-actions',
                        click: function() {
                            that._previous();
                    }},
                    {text:window.hWin.HR('Next'),
                        id: 'btnTipNext',
                        title: window.hWin.HR("Show next tip"),
                        class: 'tags-actions',
                        click: function() {
                            that._next();
                    }},
                    {text:window.hWin.HR('Close'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            });

        }else{
            this.wcontainer.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'500px'}).appendTo( this.element );
        }

        this._setOption("current_tip", parseInt(window.hWin.HAPI4.get_prefs('help_tip')));

        this._refresh();

    }, //end _create

    /* private function */
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.wcontainer.remove();
    },

    //
    _previous: function(){
         if(this.options.current_tip>1)
            this._setOption("current_tip", this.options.current_tip-1);
    },

    _next: function(){
         this._setOption("current_tip", this.options.current_tip+1);
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        if(key=='current_tip'){
            this._refresh();
        }
    },

    _refresh: function(){

        var idx = this.options.current_tip;

        if(window.hWin.HEURIST4.util.isnull(idx) || isNaN(idx) || idx<0) idx = 1;

        var that = this;

        this.wcontainer.load("context_help/tips/tip"+idx+".html?t="+window.hWin.HEURIST4.util.random() , function(response, status, xhr){
            if(status=="error"){
                if(idx!=1){
                    that._setOption("current_tip", 1); //reset to 1
                }else{
                    //Hul
                }
            }else{
                that.options.current_tip = idx;
                window.hWin.HAPI4.save_pref('help_tip_show', idx);
                window.hWin.HAPI4.save_pref('help_tip_last', (new Date()));
                
                window.hWin.HEURIST4.util.setDisabled($.find('#btnTipPrev'), (idx==1));
                if(that.last_tip_idx>0){
                    if(idx+1==that.last_tip_idx){
                        window.hWin.HEURIST4.util.setDisabled($.find('#btnTipNext'), true);
                    }else{
                        window.hWin.HEURIST4.util.setDisabled($.find('#btnTipNext'), false);
                    }
                    return;
                }
                
                $.ajax( {
                    url: window.hWin.HAPI4.baseURL + 'hsapi/utilities/fileGet.php?check=context_help/tips/tip'+(idx+1)+'.html',
                    })
                    .error(function() {
                    })
                    .success(function(response, textStatus, jqXHR){
                        if(response=='ok'){
                            window.hWin.HEURIST4.util.setDisabled($.find('#btnTipNext'), false);    
                        }else{
                            that.last_tip_idx = idx+1;
                            window.hWin.HEURIST4.util.setDisabled($.find('#btnTipNext'), true);
                        }
                    });                
                
            }
        });


    },

    show: function(){
        if(this.options.isdialog){
            this.element.dialog("open");
            this.element.parent().addClass('ui-dialog-heurist');
        }else{
            //fill selected value this.element
        }
    }

});

/**
* Show Tip of the Day/Getting strated dialog
*
* @param verify
*/
function showTipOfTheDay(verify){

    if(verify){
        var showtip = window.hWin.HAPI4.get_prefs('help_tip_show');
        if(window.hWin.HEURIST4.util.isnull(showtip) || showtip){

            var lastshown = window.hWin.HAPI4.get_prefs('help_tip_last');
            if(!window.hWin.HEURIST4.util.isnull(lastshown) && (new Date()).getHours() - (new Date(lastshown)).getHours() < 24){
                return;
            }

        }else{
            return;
        }

    }

    var manage_dlg = $('#heurist-help-tips');

    if(manage_dlg.length<1){

        manage_dlg = $('<div id="heurist-help-tips">')
        .appendTo( $('body') )
        .help_tips({ isdialog:true });
    }

    manage_dlg.help_tips( "show" );
}
