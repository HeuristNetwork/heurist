/**
* Assign rating dialog, applied for selected list of bookmarked records (records that have tags)
* 
* This widget is dynamically loaded and used in rec_actions (More\Rate menu)
* 
* TODO: "Requires utils.js" - does it?
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


$.widget( "heurist.tag_rating", {

    // default options
    options: {
        isdialog: false, //show in dialog or embedded
        record_ids: null //array of record ids the tag selection will be applied for
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
                resizable: false,
                height: 220,
                width: 250,
                modal: true,
                title: window.hWin.HR("Set Ratings"),
                buttons: [
                    {text:window.hWin.HR('Assign'),
                        title: window.hWin.HR("Set Ratings"),
                        class: 'tags-actions',
                        click: function() {
                            that._assignRating();
                    }},
                    {text:window.hWin.HR('Close'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            });

        }else{  //not used in popup (non dialog) mode
            this.wcontainer.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'200px'}).appendTo( this.element );
        }

        //---------------------------------------- CONTENT

        $('<table>'+
                '<tr><td><input type="radio" value="0" name="r" id="r0" checked="checked"></td><td><label for="r0">'+window.hWin.HR("No Rating")+'</label></td></tr>'+
                '<tr><td><input type="radio" value="1" name="r" id="r1"></td><td><label for="r1" class="bookmarked" style="width:14px;display: block;"></label></td></tr>'+
                '<tr><td><input type="radio" value="2" name="r" id="r2"></td><td><label for="r2" class="bookmarked" style="width:24px;display: block;"></label></td></tr>'+
                '<tr><td><input type="radio" value="3" name="r" id="r3"></td><td><label for="r3" class="bookmarked" style="width:38px;display: block;"></label></td></tr>'+
                '<tr><td><input type="radio" value="4" name="r" id="r4"></td><td><label for="r4" class="bookmarked" style="width:50px;display: block;"></label></td></tr>'+
                '<tr><td><input type="radio" value="5" name="r" id="r5"></td><td><label for="r5" class="bookmarked" style="width:64px;display: block;"></label></td></tr>'+
        '</table>').css({'font-size':'0.9em'}).appendTo( this.wcontainer );
        
        
        //---------------------------------------- BUTTONS for non-dialog mode
        if(!this.options.isdialog)
        {
            this.div_toolbar = $( "<div>" )
            .css({'width': '100%', height:'2.4em'})
            .appendTo( this.wcontainer );

            this.btn_assign = $( "<button>", {
                id: 'assignTags',
                //disabled: 'disabled',
                text: window.hWin.HR("Assign"),
                title: window.hWin.HR("Set Ratings")
            })
            .appendTo( this.div_toolbar )
            .button();

            this._on( this.btn_assign, { click: "_assignRating" } );

        }
    }, //end _create

    /* private function */
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        if(this.div_toolbar){
            this.btn_assign.remove();
            this.div_toolbar.remove();
        }

        this.wcontainer.remove();
    },

    /**
    * Assign tags to selected records
    */
    _assignTags: function(){

        //find checkbox that has usage>0 and unchecked 
        // and vs  usage==0 and checked
        var t_added = $(this.element).find('input[type="checkbox"][usage="0"]:checked');
        var t_removed = $(this.element).find('input[type="checkbox"][usage!="0"]:not(:checked)');

        if ((t_added.length>0 || t_removed.length>0) && this.options.current_GrpID)
        {

            var toassign = [];
            t_added.each(function(i,e){ toassign.push($(e).attr('tagID')); });
            var toremove = [];
            t_removed.each(function(i,e){ toremove.push($(e).attr('tagID')); });
            var that = this;

            window.hWin.HAPI4.RecordMgr.tag_set({assign: toassign, remove: toremove, UGrpID:this.options.current_GrpID, recIDs:this.options.record_ids},
                function(response) {
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        that.element.hide();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }
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
function showRatingTags(){

    var manage_dlg = $('#heurist-tags-rating-dialog');

    if(manage_dlg.length<1){

        manage_dlg = $('<div id="heurist-tags-rating-dialog">')
        .appendTo( $('body') )
        .tag_rating({ isdialog:true });
    }

    manage_dlg.tag_rating( "show" );
}
