/**
* Widget to specify and add relationship record
* Used in editing_input and as popup for selected records in rec_list
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

$.widget( "heurist.rec_relation", {

    // default options
    options: {
        isdialog: false, //show in dialog or embedded
        islabels: true,  //show label

        rectype_set: null, //constraints for target record types
        reltype_set: null, //contraints for relation types
        reltype_headers: null, //subset of headers for relation type pulldown

        source_ids: null, // array of record ids - sources of relationship to be defined

        reltype_value: null,
        target_recid: null,

        onselect: null
    },

    // the constructor
    _create: function() {

        var that = this;

        this.wcontainer = $("<div>");

        if(this.options.isdialog){

            this.wcontainer
            .css({overflow: 'none !important', width:'100% !important'})
            .appendTo($("body"))
            .dialog({
                autoOpen: false,
                height: 120,
                width: 640,
                modal: true,
                title: "Add Relationship",
                resizeStop: function( event, ui ) {
                    that.wcontainer.css('width','100%');
                }
            });
        }else{
            this.wcontainer.appendTo( this.element );
        }

        if(this.options.islabels){
            $( "<label>Relation type:&nbsp</label>").appendTo( this.wcontainer );
        }

        this.w_reltype = $( "<select>" )
        .css('margin-right','0.4em')
        .addClass('text ui-widget-content ui-corner-all')
        .css('width','auto')
        .appendTo( this.wcontainer );

        this._recreateSelector();


        if(this.options.islabels){
            $( "<label>Related record:&nbsp;</label>").appendTo( this.wcontainer );
        }

        this.w_recpointer = $( "<input>" )
        .addClass('text ui-widget-content ui-corner-all')
        .appendTo( this.wcontainer );

        //create record search widget to search target record
        this.rec_search_dialog = this.element.rec_search({
            isdialog: true,
            search_domain: 'a'
        });

        this.btn_rec_search_dialog = $( "<button>", {title: "Click to search target record"})
        .addClass("smallbutton")
        .appendTo( this.wcontainer )
        .button({icons:{primary: "ui-icon-link"},text:false});

        var that = this;

        function __show_select_dialog(event){
            event.preventDefault();

            that.rec_search_dialog.rec_search("option",{
                rectype_set: that.options.rectype_set, //constraints

                onselect: function(event, recordset){

                    if(recordset && recordset.length()>0){
                        var record = recordset.getFirstRecord();
                        that.w_recpointer.val(recordset.fld(record,'rec_Title'));
                        that.options.target_recid = recordset.fld(record,'rec_ID');
                    }

                }
            });

            that.rec_search_dialog.rec_search( "show" );
        };

        this._on( this.btn_rec_search_dialog, { click: __show_select_dialog } );
        this._on( this.w_recpointer, { keypress: __show_select_dialog, click: __show_select_dialog } );


        this.btn_add_relationship = $( "<button>", {text:"Add", title: "Create relationship"})
        .css('float','right')
        .appendTo( this.wcontainer )
        .button();

        this._on(  this.btn_add_relationship, { click: "doCreate" } );

        this._refresh();

    }, //end _create

    /* private function */
    _refresh: function(){
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.rec_search_dialog.remove();
        this.btn_rec_search_dialog.remove();
        this.btn_add_relationship.remove();

        this.w_reltype.remove();
        this.w_recpointer.remove();
        this.wcontainer.remove();
    },

    _recreateSelector: function(){

        this.w_reltype.empty();

        var allTerms = this.options.reltype_set;
        var headerTerms = this.options.reltype_headers;

        window.hWin.HEURIST4.ui.createTermSelect(this.w_reltype.get(0),
            {vocab_id:allTerms, defaultTermID:this.options.reltype_value, topOptions:false});
    },

    doCreate: function(){

        this.w_reltype.removeClass( "ui-state-error" );
        this.w_recpointer.removeClass( "ui-state-error" );

        if(!this.w_reltype.val()){
            this.w_reltype.addClass( "ui-state-error" );
            return;
        }
        if(!this.options.target_recid){
            this.w_recpointer.addClass( "ui-state-error" );
            return;
        }


        this.options.reltype_value = this.w_reltype.val();
        var that = this;

        //hapi save record call

        //returns saved relationship record
        //send it back

        that._trigger( "onselect", null, this.w_reltype.text()+' '+this.w_recpointer.val() ); //@todo!!! return relation record

        if(that.options.isdialog){
            that.wcontainer.dialog("close");
        }

    },

    show: function(){
        if(this.options.isdialog){
            this.wcontainer.dialog("open");
        }else{
            //fill selected value this.element
        }
    }

});
