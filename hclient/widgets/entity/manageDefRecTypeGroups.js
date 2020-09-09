/**
* manageDefRecTypeGroups.js - main widget mo record type groups
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


$.widget( "heurist.manageDefRecTypeGroups", $.heurist.manageEntity, {
    
    _entityName:'defRecTypeGroups',
    
    _init: function() {

        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        this.options.use_cache = true;
        
        if(this.options.select_mode!='manager'){
            this.options.edit_mode = 'none';
            this.options.width = 300;
        }else if(this.options.edit_mode == 'inline') {
            this.options.width = 890;
        }
        
        this._super();
        
        if(this.options.select_mode!='manager'){
            //hide form 
            this.editForm.parent().hide();
            this.recordList.parent().css('width','100%');
        }

        if(!this.options.innerTitle){
            this.recordList.css('top',0);  
        }        
        
        var that = this;

        //refresh list        
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == 'rtg'))
                {
                    that._loadData();
                }
            });
        
    },
    
    _destroy: function() {
        
       $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        
       this._super(); 
    },
    
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }

        var that = this;
        
        this.recordList.resultList({
                show_toolbar:false,
                sortable: true,
                onSortStop: function(){
                    that._toolbar.find('#btnApplyOrder').show();
                },
                droppable: function(){
                    
                    that.recordList.find('.recordDiv')  //.recordDiv, ,.recordDiv>.item
                        .droppable({
                            //accept: '.rt_draggable',
                            scope: 'rtg_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                var trg = $(event.target).hasClass('recordDiv')
                                            ?$(event.target)
                                            :$(event.target).parents('.recordDiv');
                                            
                    var rty_ID = $(ui.draggable).parent().attr('recid');
                    var rtg_ID = trg.attr('recid');
                    
                            if(rty_ID>0 && rtg_ID>0 && that.options.reference_rt_manger){
                                    
                                    that.options.reference_rt_manger
                                        .manageDefRecTypes('changeRectypeGroup',{rty_ID:rty_ID, rty_RecTypeGroupID:rtg_ID });
                            }
                        }});
                }
        });
        

        if(this.options.innerTitle){
            //specify add new/save order buttons above record list
            var btn_array = [
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add'),
                      css:{'margin-right':'0.5em','float':'right'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save'),
                      css:{'margin-right':'0.5em','float':'right',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }}];

            this._toolbar = this.searchForm;
            this.searchForm.css({'padding-top': '8px'}).empty();
            $('<h4>Record Type Groups</h4>').css({'margin':4, float:'left'}).appendTo(this.searchForm);
            this._defineActionButton2(btn_array[0], this.searchForm);
            this._defineActionButton2(btn_array[1], this.searchForm);
            
        }
        
        that._loadData();
         
        return true;
    },    
    
    //
    //
    //
    _loadData: function(){
        

        this.updateRecordList(null, {recordset:$Db.rtg()});
        this._selectAndEditFirstInRecordset($Db.rtg());
/*
        var that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});
                that._selectAndEditFirstInRecordset(response);
            });
*/        
    },
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="display:table-cell;width:'+col_width+';max-width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        var recID   = recordset.fld(record, 'rtg_ID');
        var recTitle = fld2('rtg_ID','4em')+fld2('rtg_Name');
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em">';
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div>';//<div class="recordTitle">';
        }else{
            //html = html + '<div>';
        }
        
        html = html + fld2('rtg_Name',250);
      
        if(this.options.edit_mode=='popup'){
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil', class:'rec_actions_button'}, 
                            null,'icon_text');
        }
        
        var cnt = 0;//recordset.fld(record, 'rtg_RtCount');
        
        html = html 
                +((cnt>0)
                ?'<div style="display:table-cell;padding:0 4px">'+cnt+'</div>'
                :this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-delete', class:'rec_actions_button'}, 
                            null,'icon_text'))
                + '<div class="selection_pointer" style="display:table-cell">'
                    +'<span class="ui-icon ui-icon-carat-r"></span></div>';
        
        

        return html+'</div>';
        
    },

    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        if(this.options.edit_mode=='editonly'){
            
                this._selection = new hRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._currentEditID = null;
                this._selectAndClose();
        }else{
                this._super( recID, fieldvalues );
        }
    
        this._triggerRefresh('rtg');    
        
    },

    //
    //
    //
    _afterDeleteEvenHandler: function( recID ){
        this._super( recID );
        this._triggerRefresh('rtg');    
    },
    
    //
    // cant remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){

        if(false && this._getField('rtg_RtCount')>0){
            window.hWin.HEURIST4.msg.showMsgFlash('Can\'t remove non empty group');  
            return;                
        }
    
        if(unconditionally===true){
            this._super(); 
            
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this record type group? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    //
    // extend for save order
    //
    _onActionListener: function(event, action){

        var isresolved = this._super(event, action);

        if(!isresolved && action=='save-order'){

            var recordset = this.getRecordSet();
            var that = this;
            window.hWin.HEURIST4.dbs.applyOrder(recordset, 'rtg', function(res){
                that._toolbar.find('#btnApplyOrder').hide();
            });

        }

    },
    
    //
    // extend dialog button bar
    //
    /*    
    _initEditForm_step3: function(recID){
        
        if(this._toolbar){
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            this._toolbar.find('#btnRecDelete').css('display', (recID>0 && this._getField('rtg_RtCount')==0) ?'block':'none');
        }
        
        this._super(recID);
    },
    
    onEditFormChange: function( changed_element ){
        this._super(changed_element);
            
        if(this._toolbar){
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnRecDelete').css('display', (isChanged || this._getField('rtg_RtCount')>0)?'none':'block');
        }
            
    },
    */
  
/*    
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            
            var btns = [       
            //{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
            //    click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                      
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Group'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save Order'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }},
                      
                      
                {text:window.hWin.HR('Close'), 
                      css:{'margin-left':'3em','float':'right'},
                      click: function() { 
                          that.closeDialog(); 
                      }},
                {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                      css:{'margin-left':'0.5em','float':'right',display:'none'},
                      click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                {text:window.hWin.HR('Save'), id:'btnRecSave',
                      accesskey:"S",
                      css:{'font-weight':'bold','float':'right'},
                      class:'ui-button-action',
                      click: function() { that._saveEditAndClose( null, 'none' ); }},
                      
                {text:window.hWin.HR('Delete'), id:'btnRecDelete',
                      css:{'float':'right',display:'none'},
                      click: function() { that._onActionListener(null, 'delete'); }},
                                      
                      ];
        
            return btns;
    },
*/    
});
