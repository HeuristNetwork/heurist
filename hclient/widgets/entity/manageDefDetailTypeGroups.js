/**
* manageDefDetailTypeGroups.js - main widget mo manage defDetailTypeGroups
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


$.widget( "heurist.manageDefDetailTypeGroups", $.heurist.manageEntity, {
    
    
    _entityName:'defDetailTypeGroups',
    btnAddRecord:null,
    btnApplyOrder:null,
    
    _init: function() {

        this.options.layout_mode = 'short';
        this.options.use_cache = true;
        if(this.options.select_mode!='manager'){
            this.options.edit_mode = 'none';
            this.options.width = 300;
        }else{
            this.options.edit_mode = 'inline';    
            this.options.width = 890;
        }
        
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

        var that = this;
        
        this.recordList.resultList('option', 'show_toolbar', false);
        if(this.options.edit_mode = 'inline'){
            this.recordList.resultList('option', 'sortable', true);
            this.recordList.resultList('option', 'onSortStop', function(){
                if(that.btnApplyOrder) that.btnApplyOrder.show()
            });
        }

       //---------    EDITOR PANEL - DEFINE ACTION BUTTONS
       //if actions allowed - add div for edit form - it may be shown as right-hand panel or in modal popup
       if(this.options.edit_mode!='none'){
            
               //define add button on left side
               this.btnAddRecord = this._defineActionButton({key:'add', label:'Add New Group', title:'', icon:'ui-icon-plus'}, 
                        this.searchForm, 'full', {float:'left'});

               this.btnApplyOrder = this._defineActionButton({key:'save-order', label:'Save Order', title:'', icon:null}, 
                        this.searchForm, 'full', {float:'right'});
       
               this.btnApplyOrder.hide();

               /*if(this.options.edit_mode=='inline'){            
                    //define delete on right side
                    this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'},
                        this.editFormToolbar,'full',{float:'right'});
               }*/
       }

        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that._cachedRecordset = response.getSubSetByRequest({'sort:dtg_Order':1}, null);
                that.recordList.resultList('updateResultSet', that._cachedRecordset);
                
                
                //init inline editor at once
                if(that.options.edit_mode=='inline'){
                    var new_recID = that._getField2(that.options.entity.keyField); 
                    if(new_recID>0){
                        that.addEditRecord(new_recID);
                    }
                }
            });
            
        return true;
    },    
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        var recID   = fld('dtg_ID');
        var recTitle = fld2('dtg_ID','4em')+fld2('dtg_Name');
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em">';
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div><div class="recordTitle">';
        }else{
            html = html + '<div>';
        }
        
        html = html + fld2('dtg_Name') + '<div style="position:absolute;right:4px;top:6px">'+fld('dtg_FieldCount')+'</div></div>';
        
        if(this.options.edit_mode=='popup'){
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil'}, null,'icon_text')
            + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'}, null,'icon_text');
             /*
            + '<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
            + '</div>&nbsp;&nbsp;'
            
             
            + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
            + '</div>';
            */
        }
        

        return html+'</div>';
        
    },

    updateRecordList: function( event, data ){
        //this._super(event, data);
        if (data){
            if(this.options.use_cache){
                this._cachedRecordset = data.recordset;
                //there is no filter feature in this form - thus, show list directly
            }
            this.recordList.resultList('updateResultSet', data.recordset, data.request);
        }
    },
    
    onEditFormChange: function( changed_element ){
        
        this._super();
        
        if(this._currentEditID == -1){
            this.btnAddRecord.hide();
        }else{
            this.btnAddRecord.show();
        }
        
    },
    
    _deleteAndClose: function(){    
            if(this._getField('dtg_FieldCount')>0){
                window.hWin.HEURIST4.msg.showMsgFlash('Can\'t remove non empty group');  
                return;                
            }
            
            this._super();
    },
    
    _onActionListener: function(event, action){

        this._super(event, action);

        if(action=='save-order'){


            var recordset = this.recordList.resultList('getRecordSet');
            //assign new value for dtg_Order and save on server side
            var rec_order = recordset.getOrder();
            var idx = 0, len = rec_order.length;
            var fields = [];
            for(; (idx<len); idx++) {
                var record = recordset.getById(rec_order[idx]);
                var oldval = recordset.fld(record, 'dtg_Order');
                var newval = String(idx+1).lpad(0,3);
                if(oldval!=newval){
                    recordset.setFld(record, 'dtg_Order', newval);        
                    fields.push({"dtg_ID":rec_order[idx], "dtg_Order":newval});
                }
            }
            if(fields.length>0){

                var request = {
                    'a'          : 'save',
                    'entity'     : this._entityName,
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields                     
                };

                var that = this;                                                
                //that.loadanimation(true);
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            //that._afterSaveEventHandler( recID, fields );
                            that.btnApplyOrder.hide();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                });

            }else{
                this.btnApplyOrder.hide();    
            }

        }

    },
    
    
});
