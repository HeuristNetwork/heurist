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

        //if true - default set of actions, if array - specified set of actions, false or empty array - hide selector or buttons
        action_select: false, //may be true,false or array [add,edit,delete,merge,select all|none,import,] 
        action_buttons: true, //may be true,false or array
        list_header: false,
        
        isdialog: false,  //show in dialog
        dialogcleanup: true, // remove dialog div on close
        
        // callbacks
        onselect:null  //selection complete callback
    },
    
    //overwrite it in create method
    _entityName: 'Entity',
    _entityNames: 'Entities',
    
    _empty_remark: '', //move to options??
    
    _default_sel_actions:[{key:'edit', title:'Edit'},
                          {key:'delete', title:'Delete'},
                          {key:'merge', title:'Merge'}],
    _default_btn_actions:[{key:'add', title:'Add New'}],

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
        
        if(this.options.action_select==true){
            this.options.action_select = this._default_sel_actions;
        }
        if(this.options.action_buttons==true){
            this.options.action_buttons = this._default_btn_actions;
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
                    ?'<div style="padding:1em 0 1em 0">'+this._empty_remark+'</div>'
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
            

        var ishelp_on = top.HAPI4.get_prefs('help_on')==1;
        $('.heurist-helper1').css('display',ishelp_on?'block':'none');

        this.searchRecord = $('<div>')
            .addClass('ent_header')
            .css({padding:'0.2em'})
            .appendTo(this.wrapper);
        
        //extend ===========    
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
    //
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
             top.HEURIST4.msg.showMsgFlash(s);  
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
        //TO EXTEND        
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
                width:  options['width']?options['width']:740,
                modal:  (options['modal']!==true),
                title: options['title']
                            ?options['title']
                            :( (options['select_mode']=='manager'
                                    ?top.HR('Manage')
                                    :top.HR('Select')) + 
                                (options['select_mode']=='select_multi'
                                    ?this._entityNames
                                    :this._entityName) ),
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
    }
    
});