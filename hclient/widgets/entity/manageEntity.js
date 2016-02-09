/**
* manageEntity.js - BASE widget
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
$.widget( "heurist.manageEntity", {

    // default options
    options: {
        // manager - all selection ui (buttons, checkboxes, label with number of sel.records) is hidden
        //        highlight in list works as usual and selected records are used in actions
        // select_single - in list only one item can be highlighted, in dialog mode it will be closed
        // select_multi - several items can be highlighted, chekboxes are visible in list, onselect works only if button prerssed
        select_mode: 'manager', //'select_single','select_multi','manager'

        selectbutton_label: 'Select',
        //initial filter by title and subset of groups to search
        filter_title: null,
        filter_group_selected:null,
        filter_groups: null,

        page_size: 50,

        isdialog: false,  //show in dialog
        dialogcleanup: true, // remove dialog div on close
        height: 400,
        width:  740,
        modal:  true,
        title:  '',
        
        // callbacks
        onselect:null,  //selection complete callback

        //if true - default set of actions, if array - specified set of actions, false or empty array - hide selector or buttons
        action_select: false, //may be true,false or array [add,edit,delete,merge,select all|none,import,] 
        action_buttons: true, //may be true,false or array
        list_header: false,   //show header in list mode (@todo implement)
        
        edit_dialog: true,  //show in right hand form or as popup 
        edit_height:null,
        edit_width :null,
        edit_title :null,
        edit_need_load_fullrecord: false, //if for edit form we need to load full(all data) record
        
        //it either loaded from server side if _entityName defined or defined explicitely on client side
        entity: {}
    },
    
    //system name of entity  - define it to load entity config from server
    _entityName: '', 
    _entityIDfield: '', //to be taken from options.entity
    
    //selected records
    _selection:null,
    //reference to edit form
    _editing:null,
    
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

        
        if(!top.HEURIST4.util.isempty(this._entityName)){
            //entity should be loaded from server
            var that = this;
            top.HAPI4.EntityMgr.getEntityConfig(this._entityName, 
                    function(entity){
                        that.options.entity = entity;
                        that._initControls();
                    });
            return;
        }else{
            //entity defined via options
            this._entityName = this.options.entity['entityName'];
            this._initControls();
        }
    },
      
    //  
    // invoked from _init after load entity config    
    //
    _initControls(){
        
        if(!this._entityName || $.isEmptyObject(this.options.entity)){
            return false;
        }
        
        var that = this;
        
        if(this.options.action_select==true){
            this.options.action_select = this.options.entity.sel_actions;
        }
        if(this.options.action_buttons==true){
            this.options.action_buttons = this.options.entity.btn_actions;
        }
        
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
               
               action_select: this.options.action_select, 
               action_buttons: this.options.action_buttons,
               
               empty_remark: 
                    (this.options.select_mode!='manager')
                    ?'<div style="padding:1em 0 1em 0">'+this.options.entity.empty_remark+'</div>'
                    :'',
               searchfull: function(arr_ids, pageno, callback){
                   that._searchFullData(arr_ids, pageno, callback);
               },//this._searchFullData,    //search function 
               renderer: function(recordset, record){ 
                        return that._rendererListItem(recordset, record);  //custom render for particular entity type
                    },
               rendererHeader: this.options.list_header ?function(){
                        return that._rendererListHeader();  //custom header for list mode (table header)
                        }:null
                       
                        });     

        this._on( this.recordList, {
                "resultlistonselect": function(event, selected_recs){
                            this.selection(selected_recs);
                        }
                });
        this._on( this.recordList, {
                "resultlistonaction": this._rendererListOnAction});
                
                
       //---------   
       //if actions allowed - add div for edit form - it may be shown as right-hand panel or in modal popup
       if(top.HEURIST4.util.isArrayNotEmpty(this.options.action_buttons)
        || top.HEURIST4.util.isArrayNotEmpty(this.options.action_select)){
           
            this.ent_editor = $('<div>')
                .addClass('ent_editor ui-heurist-bg-light')
                .css('min-width', this.options.edit_width?this.options.edit_width:'700px')
                .appendTo(this.element);
       }
            
            
        //--------------------------------------------------------------------    

        var ishelp_on = top.HAPI4.get_prefs('help_on')==1;
        $('.heurist-helper1').css('display',ishelp_on?'block':'none');

        this.searchRecord = $('<div>')
            .addClass('ent_header')
            .css({padding:'0.2em'})
            .appendTo(this.wrapper);
        
        
        return true;
        //place this code in extension ===========    
        /* init search header
        this.searchRecord.searchSysGroups(this.options);
            
        this._on( this.searchRecord, {
                "searchsysgroupsonresult": this.updateRecordList
                });
       */         
        //extend ===========    
        
        
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
    // listener of action button/menu clicks
    //
    _rendererListOnAction:function(event, action){
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
             
             if(action=='add' || action=='edit'){
                    this._addEditRecord(recID);
             }else{
                    top.HEURIST4.msg.showMsgFlash(s);  
             }
        }
    },
    
    //----------------------
    //
    //
    //
    _rendererListAction: function(action, isheader){        
        if(top.HEURIST4.util.isArrayNotEmpty(this.options.action_select)){        
        //if(this.options.select_mode=='manager'){
            var idx = 0;
            for(idx in this.options.action_select){
                var act = this.options.action_select[idx];
                if(action == act.key && !top.HEURIST4.util.isempty(act.icon))
                {
                    if(isheader==true){

                        return '<div title="' + (act.hint?act.hint:'')
                            +'" style="display:inline-block;border-right:1px solid;width:3em">'
                            +act.title+'</div>';
                        
                    }else{
                    
                        return '<div title="'
                            + (act.hint?act.hint:act.title)
                            +'" class="item inlist logged-in-only" '
                            +'style="width:3em" role="button" aria-disabled="false" data-key="'+act.key+'">'
                            +'<span class="ui-icon '+act.icon+'"></span>'
                            + '</div>';
                    }
                }
            }               
        }
        
        return '';
        
    },
        
    _rendererListHeader:function(){
        return '';
    },
    
    _rendererListItem:function(recordset, record){
        //TO EXTEND        
    },

    //
    //
    //
    _searchFullData:function(arr_ids, pageno, callback){

        var request = {
                'a'          : 'search',
                'entity'     : this.options.entity.entityName,
                'details'    : 'list',
                'request_id' : pageno,
                'dty_ID'     : arr_ids,
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
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
        
            //dialog buttons 
            if(options['select_mode']=='select_multi'){ 
                btn_array.push({text:top.HR( options['selectbutton_label'] ),
                        click: function() { that._selectAndClose(); }}); 
            }
            btn_array.push({text:top.HR('Close'), 
                    click: function() { that._closeDialog(); }}); 
                    
                
            var $dlg = this.element.dialog({
                autoOpen: false ,
                height: options['height'],
                width:  options['width'],
                modal:  (options['modal']!==true),
                title: options['title']
                            ?options['title']
                            :( (options['select_mode']=='manager'
                                    ?top.HR('Manage')
                                    :top.HR('Select')) + ' ' +
                                (options['select_mode']=='select_multi'
                                    ?options.entity.entityTitlePlural
                                    :options.entity.entityTitle) ),
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
            //top.HAPI4.save_pref('recent_Users', this._selection.getIds(25), 25);      
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
    },
    
    //  -----------------------------------------------------
    //
    //
    _getValidatedValues: function(){
        //EXTEND if need it
        if(this._editing.validate()){
            return this._editing.getValues(false);    
        }else{
            return null;
        }
    },
    
    //  -----------------------------------------------------
    //
    //
    _saveEditAndClose: function(){

            var fields = this._getValidatedValues(); 
            
            if(fields==null) return; //validation failed
        
            var request = {
                'a'          : 'save',
                'entity'     : this.options.entity.entityName,
                'request_id' : top.HEURIST4.util.random(),
                'fields'    : fields                     
                };
                
                var that = this;                                                
                //that.loadanimation(true);
                top.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){

                            top.HEURIST4.msg.showMsgFlash('ok');
                            if(that.options.edit_dialog){
                                that.ent_editor.dialog('close')
                            }
                            
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },       
    
    //  -----------------------------------------------------
    //
    //
    _initEditForm: function(recID){

        if(!this._editing){
            this._editing = new hEditing(this.ent_editor);
        }

        //fill with values
        if(recID>0){
            if(this.options.edit_need_load_fullrecord){
                
                //get primary key field
                _entityIDfield
                
                var request = {'a': 'search',
                    'entity': this.options.entity.entityName,  //'defDetailTypes'
                    'details': 'full',
                    'request_id': top.HEURIST4.util.random()
                }
                request[this._entityIDfield] = recID;
                
                var that = this;                                                
                
                top.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            var recordset = new hRecordSet(response.data);
                            
                            that._editing.initEditForm(that.options.entity.fields, recordset);
                            that._afterInitEditForm();
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                
                return;    
            
            }else{
                var recordset = this.recordList.resultList('getRecordsById', recID);
                this._editing.initEditForm(this.options.entity.fields, recordset);
            }
        }else{
            this._editing.initEditForm(this.options.entity.fields, null);
        }
        
        this._afterInitEditForm();
    },
    
    //-----
    //  perform some after load modifications (show/hide fields,tabs )
    //
    _afterInitEditForm: function(){
        // to EXTEND 
    },

    //
    //  show edit form in popup dialog or rigth-hand panel
    //
    _addEditRecord: function(recID){
        
        this._initEditForm(recID);
        
        if(this.options.edit_dialog){ //show in popup

            var that = this; 
        
            var btn_array = [{text:top.HR('Save'),
                        click: function() { that._saveEditAndClose(); }},
                              {text:top.HR('Close'), 
                    click: function() { that.ent_editor.dialog('close'); }}]; 
             
            this.ent_editor.dialog({
                autoOpen: true,
                height: this.options['edit_height']?this.options['edit_height']:400,
                width:  this.options['edit_width']?this.options['edit_width']:740,
                modal:  true,
                title: this.options['edit_title']
                            ?this.options['edit_title']
                            :top.HR('Edit') + ' ' +  this.options.entity.entityName,
                resizeStop: function( event, ui ) {//fix bug
                    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                },
                buttons: btn_array
            });        
            

            
        }else{ //show on right-hand panel
            
            
        }
        
        
    }
    
    
});

