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


$.widget( "heurist.manageDefVocabularyGroups", $.heurist.manageEntity, {
    
    //options.selection_on_init - initial selection
    
    _entityName:'defVocabularyGroups',
    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';

        this.options.innerTitle = false;
        
        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        this.options.use_cache = true;
        
        if(this.options.select_mode!='manager'){
            this.options.edit_mode = 'none';
            this.options.width = 300;
        }
        this.options.edit_width = 550;
        
        this._super();
        
        if(this.options.select_mode!='manager'){
            //hide form 
            this.editForm.parent().hide();
            this.recordList.parent().css('width','100%');
        }
        
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }
        
        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly( $Db.vcg() );
                    /*this.options.rec_ID>0
                    ?$Db.vcg().getSubSetByIds([this.options.rec_ID])
                    :null );*/
            return;
        }

        var that = this;
        
        this.recordList.resultList({
                show_toolbar:false,
                sortable: true,
                empty_remark: 'No vocabulary groups. Add new group',
                onSortStop: function(){
                    that._onActionListener(null, 'save-order');
                    //that._toolbar.find('#btnApplyOrder').show();
                },
                droppable: function(){
                    
                    that.recordList.find('.recordDiv')  //.recordDiv, ,.recordDiv>.item
                        .droppable({
                            //accept: '.rt_draggable',
                            scope: 'vcg_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                var trg = $(event.target).hasClass('recordDiv')
                                            ?$(event.target)
                                            :$(event.target).parents('.recordDiv');
                                            
                    var trm_ID = $(ui.draggable).parent().attr('recid');
                    var vcg_ID = trg.attr('recid');
                            if(trm_ID>0 && vcg_ID>0 && that.options.reference_vocab_manger){
                                
                                    var params = {trm_ID:trm_ID, trm_VocabularyGroupID:vcg_ID};
                                
                                    that.options.reference_vocab_manger
                                        .manageDefTerms('changeVocabularyGroup',params);
                            }
                        }});
                }
        });

        //specify add new/save order buttons above record list
        var btn_array = [
            {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add'),
                  css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, id:'btnAddButton',
                  click: function() { that._onActionListener(null, 'add'); }},

            {text:window.hWin.HR('Save Order'),
                  css:{'margin-right':'0.5em','float':'right',display:'none'}, id:'btnApplyOrder',
                  click: function() { that._onActionListener(null, 'save-order'); }}];

        this._toolbar = this.searchForm;
        this.searchForm.css({'padding-top': this.options.isFrontUI?'8px':'4px'}).empty();
        //this._defineActionButton2(btn_array[1], this.searchForm);
        
        $('<h3 style="margin:0;padding:0 8px;vertical-align: middle;width:100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;">Vocabularies editor</h3>'
            +'<div class="action-buttons" style="height:40px;background:white;padding:10px 8px;">'
            +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Groups</h4></div>')
        .appendTo( this.searchForm );
        
        this.searchForm.css({padding:'6px 0 0 0'});
        this.recordList.css({ top:80});
        
        this._defineActionButton2(btn_array[0], this.searchForm.find('.action-buttons'));
        
        this._loadData();

        if(this.options.select_mode == 'manager'){

            this._on(this.recordList.find('.div-result-list-content'), {'scroll': function(event){

                var $ele = $(event.target);

                if($ele.scrollLeft() !== 0){
                    $ele.scrollLeft(0);
                }
            }});
        }

        return true;
    },    
    
    //
    //
    //
    _loadData: function(){
        this.updateRecordList(null, {recordset:$Db.vcg()});
        this.selectRecordInRecordset( this.options.selection_on_init );
        this.options.selection_on_init = null;
            
/*
        var that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});
                that.selectRecordInRecordset();
            });
*/        
    },
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        
        var recID   = recordset.fld(record, 'vcg_ID');
        var recName = recordset.fld(record, 'vcg_Name');
        
        var html = '<div class="recordDiv white-borderless" id="rd'+recID+'" recid="'+recID+'">'; // style="height:1.3em"
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div>';//<div class="recordTitle">';
        }else{
            //html = html + '<div>';
        }
        
        if(recName=='Trash'){
            html = html + '<div style="display:table-cell;vertical-align: middle;"><span class="ui-icon ui-icon-trash"></span></div>';
        }
        
        html = html + '<div class="item truncate" '
                + 'style="display:table-cell;min-width:170px;max-width:170px;font-weight:bold;font-size:14px;padding:6px">'
                + window.hWin.HEURIST4.util.htmlEscape(recName)+'</div>'; 
        
        if(recName!='Trash'){
            if(this.options.edit_mode=='popup'){
                html = html
                + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil', class:'rec_actions_button'}, 
                                null,'icon_text','padding-top:10px');
            }
            
            html = html 
                    + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-delete', class:'rec_actions_button'}, 
                                null,'icon_text');
        }
        html = html + '<div class="selection_pointer" style="display:table-cell">'
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
                
                if(this.it_was_insert){
                    this._onActionListener(null, 'save-order');
                    this.selectRecordInRecordset(); //select first
                }
                    
        }
    
        this._triggerRefresh('vcg', recID);    
        
    },

    //
    //
    //
    _afterDeleteEvenHandler: function( recID ){
        this._super( recID );
        this._triggerRefresh('vcg', recID);    
        //select first
        this.selectRecordInRecordset();
    },
    
    //
    // cant remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){

        if(false){
            window.hWin.HEURIST4.msg.showMsgFlash('Can\'t remove non empty group');  
            return;                
        }
    
        if(unconditionally===true){
            this._super(); 
            
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this vocabulary group?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
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
            window.hWin.HEURIST4.dbs.applyOrder(recordset, 'vcg', function(res){
                that._toolbar.find('#btnApplyOrder').hide();
            });

        }

    },
    
/*    
    //
    // extend dialog button bar
    //    
    _initEditForm_step3: function(recID){
        
        if(this._toolbar){
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            this._toolbar.find('#btnRecDelete').show();
        }
        
        this._super(recID);
    },
    
    onEditFormChange: function( changed_element ){
        this._super(changed_element);
            
        if(this._toolbar){
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnRecDelete').css('display', 
                    (isChanged?'none':'block'));
        }
            
    },
*/  
   
});
