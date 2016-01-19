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


$.widget( "heurist.manageSysUsers", {

    // default options
    options: {
        isdialog: false,
        select_mode:'single', //'none','multi'
        groups_set: null,        
        // callbacks
        onselect:null  //selection complete - close dialog if select_mode!='none'
    },

    _selection:null,
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
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
                    ?'<div style="padding:1em 0 1em 0">Please use the search field above to locate relevant user (partial string match on name)</div>'
                    :'',
               searchfull: this._searchFullData,
               renderer:   this._rendererListItem
                        });     

        this._on( this.recordList, {
                "resultlistonselect": function(event, selected_recs){
                            this.selection(selected_recs);
                        }
                });
            

        // init search header
        this.searchRecord = $('<div>').css({height: '6em', padding:'0.2em'})
            .appendTo(this.element)
            .searchSysUsers({
                add_new_record: true,
                groups_set: this.options.groups_set,
                
                //onresult: this.updateRecordList   
            })
            
        this._on( this.searchRecord, {
                "searchsysusersonresult": this.updateRecordList
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
    _rendererListItem:function(recordset, record){
        
        function fld(fldname){
            return recordset.fld(record, fldname);
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('ugr_ID');
        var rectype = fld('ugr_Type');
        var isEnabled = (fld('ugr_Enabled')=='y');
        
        var recTitle = top.HEURIST4.util.htmlEscape(fld('ugr_Name'));
        var recIcon = top.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/' + rectype + '.png';


        var html_thumb = '';
        if(fld('ugr_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ fld('ugr_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ 
                top.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/thumb/' + rectype + '.png&quot;);"></div>';
        }

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectype+'">'
        + html_thumb
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+top.HAPI4.basePathV4+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);">'
        +     '<img src="'+top.HAPI4.basePathV4+'hclient/assets/16x16.gif" class="'+(isEnabled?'bookmarked':'unbookmarked')+'">'
        + '</div>'
        + '<div title="'+recTitle+'" class="recordTitle">'
        +     recTitle
        + '</div>'
        + '<div title="Click to edit user" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        //+ ' onclick={event.preventDefault(); window.open("'+(top.HAPI4.basePathV3+'records/edit/editRecord.html?db='+top.HAPI4.database+'&recID='+recID)+'", "_new");} >'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        /*+ '<div title="Click to view record (opens in popup)" '
        + '   class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + '   role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'*/
        + '</div>';


        return html;
        
    },

    //
    //
    //
    _searchFullData:function(arr_ids, pageno, callback){
        
        var request = {
                'a'          : 'search',
                'entity'     : 'sysUGrps',
                'details'    : 'list',
                'request_id' : pageno,
                'ugr_ID'     : arr_ids
        };
        
        top.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    //
    //
    //
    _initDialog: function(){

            var options = this.options,
                btn_array = [],
                    that = this;
        
            if(options['select_mode']=='multi'){
                btn_array.push({text:top.HR('Select'),
                        title: top.HR("Select marked users"),
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
                            :(options['select_mode']=='multi')?top.HR("Select User"):top.HR("Manage Users"),
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
function showManageSysUsers( options ){

    var manage_dlg = $('#heurist-records-dialog');

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageSysUsers( options );
    }

    manage_dlg.manageSysUsers( 'popupDialog' );
}
