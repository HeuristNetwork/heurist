/**
* recordDelete.js - delete selected records
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

$.widget( "heurist.recordDelete", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 340,
        width:  640,
        modal:  true,
        init_scope: 'selected',
        title:  'Delete Records',
        htmlContent: 'recordDelete.html',
        helpContent: 'recordDelete.html' //in context_help folder
    },
    
    header_div:null,
    recordList:null,

    //
    //
    //
    _initControls:function(){
        
        var cnt_selected = this._currentRecordsetSelIds.length;
        
        this.header_div = this.element.find('#div_header').css({'line-height':'21px'});

        //search for linked counts        
        this._onLinkedCount();
            
        if(cnt_selected > 8){
            this.element.find('#div_1').show();
        }            
        
        if (window.hWin.HAPI4.is_admin()) {
            this.element.find('#div_2').show();
            this.element.find('#div_2 > a').attr('href',
                             window.hWin.HAPI4.baseURL+'admin/verification/combineDuplicateRecords.php?db='
                             + window.hWin.HAPI4.database
                             +'&bib_ids=' + this._currentRecordsetSelIds.join(','));
        } else {
            this.element.find('#div_3').show();
        }
        
        //hide scope selector
        this.element.find('#div_fieldset').hide();
        
        this.recordList = this.element.find('.recordList');
        
        //init record list
        this.recordList
                    .resultList({
                       eventbased: false, 
                       isapplication: false, //do not listen global event
                       multiselect: true,
                       select_mode: 'select_multi',
                       view_mode: 'list',
                       
                       pagesize:1000,
                       empty_remark: '<div style="padding:1em 0 1em 0">No records selected for deletion</div>',
                       show_toolbar: false,
                       /*
                       searchfull: function(arr_ids, pageno, callback){
                           that._recordListGetFullData(arr_ids, pageno, callback);
                       },//this._recordListGetFullData,    //search function 
                       renderer: function(recordset, record){ 
                                return that._recordListItemRenderer(recordset, record);  //custom render for particular entity type
                            },
                       */     
                                });     

        this._on( this.recordList, {
                        "resultlistonselect": function(event, selected_recs){
                                    /*this.selectedRecords(selected_recs);
                                    
                                    if (this.options.edit_mode=='inline'){
                                            this._onActionListener(event, {action:'edit'}); //default action of selection
                                    }*/
                                },
                        //"resultlistonaction": this._onActionListener        
                        });
        
        
        var scope = this._currentRecordset.getSubSetByIds(this._currentRecordsetSelIds);
        
        this.recordList.resultList('updateResultSet', scope, null); //render
        
        //adjust height
        var that = this;
        setTimeout(function(){
            var h = that.header_div[0].scrollHeight;
            if(h>33){
                that.header_div.css({height: h});
                that.recordList.css({top: (h-1)});
            }
                              },300);
        return this._super();
    },
    
    _onLinkedCount:function(){
        
            var cnt_selected = this._currentRecordsetSelIds.length;
            var cnt_target = 0;
            var cnt_source = 0;
            var merged_ids = this._currentRecordsetSelIds;
            
        //$merged_ids = array_unique(array_merge($res['reverse']['target'], $res['reverse']['source']), SORT_NUMERIC);
        
        if(cnt_source>0){

            var ele = this.element.find('#div_4').show();
            
            if(cnt_selected==1){
                msg = 'This record is';
            }else if(cnt_target==1){
                msg = 'One of '+cnt_selected+' selected records is';
            }else if(cnt_target<cnt_selected){
                msg = cnt_target+' records of '+cnt_selected+' selected records are';
            }else{
                msg = 'These '+cnt_selected+' selected records are';
            }
            msg = msg
            +' pointed to by '+cnt_source+' other records. If you delete '+(cnt_target==1?'it':'them')
            +' you will leave '+((cnt_source>1)?'these records':'this record')+' with invalid data.<br>'
            +'Invalid records can be found through the Verify > Verify integrity function.';
        
            ele.find('p').first().html(msg);
                        
            ele.find('a').attr('href',
                window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&tab=connections&q=ids:'+merged_ids.join(','));
            
        }
    },
    
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordScope) this.selectRecordScope.remove();
        if(this.recordList) this.recordList.remove();
    },
    
    
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR('Delete Records');
        return res;
    },    
    
    //
    //
    //
    doAction: function( isconfirm ){

        var scope_val = this.selectRecordScope.val();
        
        if (scope_val=='')  return;
        
        var that = this;       
            
        if(isconfirm!==true){
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<span class="ui-icon ui-icon-alert" style="display:inline-block">&nbsp;</span>&nbsp;'
                +'Please confirm that you really wish to delete the selected records, <br/>along with all associated bookmarks?', 
            function(){
                    that.doAction(true);
                    },'Confirm');
            return;
        }            
            
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), true );
            
            var scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
        
            var request = {
                'request_id' : window.hWin.HEURIST4.util.random(),
                'ids'  : scope.join(',')
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                                                              
                
            window.hWin.HAPI4.RecordMgr.remove(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.deleted>0);
                            
                            that.closeDialog();
                            
                            var msg = 'Processed : '+response.data.processed + ' record'
                                + (response.data.processed>1?'s':'') +'. Deleted: '
                                + response.data.deleted  + ' record'
                                + (response.data.deleted>1?'s':'');
                                
                           if(response.data.bkmk_count>0){
                               msg += ('<br><br>Bookmark'+(response.data.bkmk_count>1?'s':'')
                                            +' removed: '+response.data.bkmk_count);
                           }
                           if(response.data.rels_count>0){
                               msg += ('<br><br>Relationship'+(response.data.rels_count>1?'s':'')
                                        +' removed: '+response.data.rels_count);
                           }
                           if(response.data.noaccess>0){
                               msg += ('<br><br>Not enough rights for '+response.data.noaccess+
                                        ' record' + (response.data.noaccess>1?'s':''));
                           }     
                            
                            //window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            window.hWin.HEURIST4.msg.showMsgDlg(msg);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

