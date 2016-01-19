/**
* Record manager - Main
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


$.widget( "heurist.manageRecord", {

    // default options
    options: {
        isdialog: false,
        select_mode:'single', //'none','multi'
        rectype_set: null,        
        // callbacks
        onselect:null  //selection complete - close dialog if select_mode!='none'
    },

    _selection:null,
    
    // the widget's constructor
    _create: function() {

        // prevent double click to select text
        this.element.disableSelection();
        
        this._refresh();

    }, //end _create
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        
        if(this.options.isdialog){
            this._initDialog();
        }
        
        this.element
            .css({'font-size':'0.9em', 'min-width':  620})
            .addClass('ui-heurist-bg-light');

        
//        this.element.assClass('ui-heurist-bg-light');        
        
        //init record list
        this.recordList = $('<div>').css({position: 'absolute', top:'6em', bottom:'1px', left:0, right:'1px'})
            .appendTo(this.element)
            .resultList({
               eventbased: false, 
               isapplication: false, //do not listent global events
               showmenu: false, 
               multiselect: (this.options.select_mode=='multi'),
               //onselect: function(event, selected_recs){this.selection(selected_recs);},
               empty_remark: 
                    (this.options.select_mode!='none')
                    ?'<div style="padding:1em 0 1em 0">Please use the search field above to locate relevant records (partial string match on title)</div>'
           +'<div>If there are no suitable records, you may create a new record which is automatically selected as the target.</div>'
                    :''    
                        });     

        this._on( this.recordList, {
                "resultlistonselect": function(event, selected_recs){
                            this.selection(selected_recs);
                        }
                });
            

        // init search header
        this.searchRecord = $('<div>').css({height: '6em', padding:'0.2em'})
            .appendTo(this.element)
            .searchRecord({
                add_new_record: true,
                rectype_set: this.options.rectype_set,
                
                //onresult: this.updateRecordList   
            })
            
        this._on( this.searchRecord, {
                "searchrecordonresult": this.updateRecordList
                });
            
        
        
        var ishelp_on = top.HAPI4.get_prefs('help_on')==1;
        $('.heurist-helper1').css('display',ishelp_on?'block':'none');
        
        
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    //----------------------
    //
    //
    //
    _initDialog: function(){

            var options = this.options,
                btn_array = [],
                    that = this;
        
            if(options['select_mode']=='multi'){
                btn_array.push({text:top.HR('Select'),
                        title: top.HR("Select marked records"),
                        click: function() {
                                    that._trigger( "onselect", null, that.selection() );
                                    that._closeDialog();
                                    //$( this ).dialog( "close" );
                                    //that.element.remove();
                                  }
                               });
            }
            btn_array.push({text:top.HR('Close'), 
                    click: function() {
                        that._closeDialog();
                    }});
                
            this.element.dialog({
                autoOpen: false ,
                height: options['height']?options['height']:400,
                width:  options['width']?options['width']:720,
                modal:  (options['modal']!==true),
                title: options['title']
                            ?options['title']
                            :(options['select_mode']=='multi')?top.HR("Select Record"):top.HR("Manage Records"),
                resizeStop: function( event, ui ) {//fix bug
                
                    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                    
                
                    //setTimeout(function(){
                    // that.element.css({overflow: 'none !important','width':'100%'});},400);   
                    //that.recordList.css({'width':'100%'});
                },
                buttons: btn_array
            });        
        
    },
    
    //
    // public methods
    //
    popupDialog: function(){
        if(this.options.isdialog){
            this.element.dialog("open");
        }
    },
    
    //
    //
    //
    _closeDialog: function(){
        if(this.options.isdialog){
            this.element.dialog("close");
            this.element.remove();
        }
    },

    //
    // value - RecordSet
    //    
    selection: function(value){
        
        if(top.HEURIST4.util.isnull(value)){
            //getter
            return this._selection;
        }else{
            //setter
            this._selection = value;
            
            if(this.options.select_mode=='single'){
                this._trigger( "onselect", null, value );
                this._closeDialog();
            }
        }
        
    },
    
    //
    //
    //
    updateRecordList: function( event, data ){
        if (data){
            this.recordList.resultList('updateResultSet', data.recordset, data.request);
        }
    }
    
});

//
// Show as dialog
//
function showManageRecord( options ){

    var manage_dlg = $('#heurist-records-dialog');

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageRecord( options );
    }

    manage_dlg.manageRecord( 'popupDialog' );
}
