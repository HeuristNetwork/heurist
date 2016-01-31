/**
* manageSysUsers.js - main widget mo manage sys users
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
        // manager - all selection ui (buttons, checkboxes, label with number of sel.records) is hidden
        //        highlight in list works as usual and selected records are used in actions
        // select_single - in list only one item can be highlighted, in dialog mode it will be closed
        // select_multi - several items can be highlighted, chekboxes are visible in list, onselect works only if button prerssed
        select_mode: 'manager', //'select_single','select_multi','manager'

        selectbutton_label: 'Select Marked Users',
        //initial filter by title and subset of groups to search
        filter_title: null,
        filter_group_selected:null,
        filter_groups: null,

        page_size: 50,

        //if true - default set of actions, if array - specified set of actions, false or empty array - hide selector or buttons
        action_select: false, //may be true,false or array [add,edit,delete,merge,select all|none,import,] 
        action_buttons: true, //may be true,false or array
        
        isdialog: false,  //show in dialog
        dialogcleanup: true, // remove dialog div on close
        
        // callbacks
        onselect:null  //selection complete callback
    },
    
    _default_sel_actions:[{key:'edit', title:'Edit'},
                      {key:'delete', title:'Delete'},
                      {key:'merge', title:'Merge'},
                      {key:'import', title:'Import'}],
    _default_btn_actions:[{key:'add', title:'Create New User'}],

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
        
        this.wrapper = $('<div>')
            .addClass('ent_wrapper ui-heurist-bg-light')
            .css('min-width','700px')
            .appendTo(this.element);
            
        
        var that = this;
        
        //init record list
        this.recordList = $('<div>')
            .addClass('ent_content_full')
            //.css({position: 'absolute', top:'6em', bottom:'1px', left:0, right:'1px'})
            .appendTo(this.wrapper)
            .resultList({
               eventbased: false, 
               isapplication: false, //do not listent global events @todo merge with eventbased
               showmenu: false,      //@todo - replace to action_select and action_buttons
               multiselect: (this.options.select_mode!='select_single'), //@todo replace to select_mode

               select_mode: this.options.select_mode,
               selectbutton_label: this.options.selectbutton_label,
               
               action_select: this.options.action_select==true
                                        ?this._default_sel_actions
                                        :this.options.action_select, 
               action_buttons: this.options.action_buttons==true
                                        ?this._default_btn_actions
                                        :this.options.action_buttons,
               
               empty_remark: 
                    (this.options.select_mode!='manager')
                    ?'<div style="padding:1em 0 1em 0">Please use the search field above to locate relevant user (partial string match on name)</div>'
                    :'',
               searchfull: function(arr_ids, pageno, callback){
                   that._searchFullData(arr_ids, pageno, callback);
               },//this._searchFullData,    //search function 
               renderer:   this._rendererListItem   //custom render for particular entity type
                        });     

        this._on( this.recordList, {
                "resultlistonselect": function(event, selected_recs){
                            this.selection(selected_recs);
                        }
                });
        this._on( this.recordList, {
                "resultlistonaction": function(event, action){
                        if(action=='select-and-close'){
                             this._selectAndClose();
                        } else {
                            var recID = 0;
                            if(action && action.action){
                               recID =  action.recID;
                               action = action.action;
                            }
                            
                             var s = 'User clicked action "'+action+'" for ';
                             if(recID>0){
                                 s = s + 'rec# '+recID;
                             }else if(top.HEURIST4.util.isRecordSet(this._selection) && this._selection.length()>0){
                                 s = s + this._selection.length() + ' selected record';
                             }else{
                                 s = 'Nothing selected';
                             }
                             top.HEURIST4.msg.showMsgFlash(s);  
                        }
                    }
                });
            

        // init search header
        this.searchRecord = $('<div>')
            .addClass('ent_header')
            .css({padding:'0.2em'})
            .appendTo(this.wrapper)
            .searchSysUsers(this.options);
            
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
        this.searchRecord.remove();
        this.recordList.remove();
        this.wrapper.remove();
        this._selection = null;
    },
    
    //----------------------
    //
    //
    //
    _rendererListItem:function(recordset, record){
        
        function fld(fldname){
            return top.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!top.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+top.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('ugr_ID');
        var rectype = fld('ugr_Type');
        var isEnabled = (fld('ugr_Enabled')=='y');
        
        var recTitle = fld2('ugr_Name','10em')+
        '<div class="item" style="width:25em">'+fld('ugr_FirstName')+' '+fld('ugr_LastName')+'</div>'+fld2('ugr_Organisation')+fld2('ugl_Role');
        
        
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
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+top.HAPI4.basePathV4+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+recIcon+'&quot;);">'   //class="rt-icon" 
        +     '<span class="ui-icon ui-icon-flag" style="color:'+(isEnabled?'#ff8844':'#dddddd')+';display:inline;left:4px">&nbsp;&nbsp;</span>'           
        + '</div>'
        + '<div class="recordTitle">'
        +     recTitle
        + '</div>'
        + '<div title="Click to edit user" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        + '<div title="Click to delete user" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>'
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
                'ugr_ID'     : arr_ids,
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        if(this.searchRecord){
            var selectGroup = this.searchRecord.find('#sel_group');
            if(selectGroup.val()!=''){
                    request['ugr_Type'] = 'user';
                    request['ugl_GroupID'] = selectGroup.val();
            }
        }
        
        top.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    //
    //
    //
    _initDialog: function(){

            var options = this.options,
                btn_array = [],
                    that = this;
        
            //dialog buttons 
            if(options['select_mode']=='select_multi'){ 
                btn_array.push({text:top.HR( options['selectbutton_label'] ),
                        click: function() { that._selectAndClose(); }}); 
            }
            btn_array.push({text:top.HR('Close'), 
                    click: function() { that._closeDialog(); }}); 
                    
                
            var $dlg = this.element.dialog({
                autoOpen: false ,
                height: options['height']?options['height']:400,
                width:  options['width']?options['width']:720,
                modal:  (options['modal']!==true),
                title: options['title']
                            ?options['title']
                            :((options['select_mode']=='manager')
                                    ?top.HR("Manage Users")
                                    :top.HR("Select User"+(options['select_mode']=='select_multi'?'s':'') )),
                resizeStop: function( event, ui ) {//fix bug
                
                    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                    
                
                    //setTimeout(function(){
                    // that.element.css({overflow: 'none !important','width':'100%'});},400);   
                    //that.recordList.css({'width':'100%'});
                },
                
                close: function(event, ui){
                       if(options['dialogcleanup']){
                           $dlg.remove();
                       }
                            
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
            this.element.dialog('close');
        }
    },

    _selectAndClose: function(){
        
        if(top.HEURIST4.util.isRecordSet(this._selection)){
            top.HAPI4.save_pref('recent_Users', this._selection.getIds(25), 25);      
            this._trigger( "onselect", null, {selection:this._selection.getIds()});
        }else{        
            this._trigger( "onselect", null, null );
        }
        this._closeDialog();
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
            
            
            if(this.options.select_mode=='select_single'){
                this._selectAndClose();
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
