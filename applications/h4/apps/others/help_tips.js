/**
* Tips of the day
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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
                height: 505,
                width: 705,
                modal: true,
                title: top.HR("Tip of the day"),
                resizeStop: function( event, ui ) {
                    //that.wcontainer.css('width','100%');
                    //.css({clear:'both'})
                    that.element.css({overflow: 'none !important','width':'100%'}); //,'height': that.element.parent().css('height')-90});
                },
                buttons: [
                    {text:top.HR('Previous'),
                        title: top.HR("Show previous tip"),
                        class: 'tags-actions',
                        click: function() {
                            that._previous();
                    }},
                    {text:top.HR('Next'),
                        title: top.HR("Show next tip"),
                        class: 'tags-actions',
                        click: function() {
                            that._next();
                    }},
                    {text:top.HR('Close'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            });

        }else{
            this.wcontainer.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'500px'}).appendTo( this.element );
        }
        
        this._setOption("current_tip", parseInt(top.HAPI4.get_prefs('help_tip')));
        
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
        
        if(top.HEURIST4.util.isnull(idx) || idx<0) idx = 1;
        
        var that = this;
        
        this.wcontainer.load("help/tip"+idx+".html?t="+(new Date().time) , function(response, status, xhr){
            if(status=="error"){
                if(idx!=1){
                    that._setOption("current_tip", 1); //reset to 1
                }else{
                    //Hul
                }
            }else{
                that.options.current_tip = idx;
                top.HAPI4.SystemMgr.save_prefs({'help_tip':idx, 'help_tip_last':(new Date()) });
            }                                        
        });                                                                
        
        
    },
    
    show: function(){
        if(this.options.isdialog){
            this.element.dialog("open");
        }else{
            //fill selected value this.element
        }
    }    

});

// Show as dialog
function showTipOfTheDay(verify){
    
    if(verify){
        var showtip = top.HAPI4.get_prefs('help_tip_show');
        if(top.HEURIST4.util.isnull(showtip) || showtip){
            
            var lastshown = top.HAPI4.get_prefs('help_tip_last');
            if(!top.HEURIST4.util.isnull(lastshown) && (new Date()).getHours() - (new Date(lastshown)).getHours() < 24){
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
