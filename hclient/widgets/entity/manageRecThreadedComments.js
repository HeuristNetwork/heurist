/**
* manageRecThreadedComments.js - main widget to manage records comments
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//
// there is no search, select mode for reminders - only edit
//
$.widget( "heurist.manageRecThreadedComments", $.heurist.manageEntity, {
   
    _entityName:'recThreadedComments',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        this.options.use_cache = false;
        
        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
            this.options.height = 600;
        }else{
           this.options.edit_mode = 'popup'; 
           this.options.list_header = true; //show header for resultList
        }

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        if(this.options.edit_mode=='editonly'){
            //load comment for edit
            if(this.options.cmt_ID>0){
                    var request = {};
                    request['cmt_ID']  = this.options.cmt_ID;
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
                    request['details']    = 'full';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    
                    var that = this;                                                
                    
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                var recset = new hRecordSet(response.data);
                                if(recset.length()>0){
                                    that.updateRecordList(null, {recordset:recset});
                                    that.addEditRecord( recset.getOrder()[0] );
                                }                            
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                that.closeEditDialog();
                            }
                        });        
                        
            }else{
                // use this.options.cmt_RecID
                // use this.options.cmt_ParentCmtID
                this.addEditRecord(-1);
            }
        }else{
            this.searchForm.searchRecThreadedComments(this.options);
            this.recordList.resultList('option','show_toolbar',false);
            
            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            this._on( this.searchForm, {
                "searchrecthreadedcommentsonresult": this.updateRecordList
            });
            
        }

        return true;
    },
    
//----------------------------------------------------------------------------------    

    //
    //
    //
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.cmt_RecID>0){
            var ele2 = this._editing.getFieldByName('cmt_RecID');
            ele2.editing_input('setValue', this.options.cmt_RecID );
        }
        
        
        this._super();// null, afteraction );
    },
    
    //
    //
    //
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        if(this.options.edit_mode=='editonly'){
            this.closeDialog();
        }
    },

    //
    // header for resultList
    //     
    _recordListHeaderRenderer:function(){
        
        function __cell(colname, width){
          return '<div style="padding:6px 0 0 4px;display:table-cell;width:'+width+'ex">'+colname+'</div>';            
        }
        
        return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
                    +__cell('Record title',18)+
                    +__cell('Modiied',8)+__cell('Text',40)+__cell('',12);
    },
    
    //----------------------
    //
    //  overwrite standard render for resultList
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="truncate" style="padding:6px 0 0 4px;display:table-cell;'+swidth+'">'
                    +fld(fldname)+'</div>';
        }
        
        var recID   = fld('cmt_ID');
        
        var html = '<div class="recordDiv" style="display:table-row;height:3em" id="rd'+recID+'" recid="'+recID+'">'
                + fld2('cmt_RecTitle','20ex') + ' ' 
                + fld2('cmt_Modified','12ex')+fld2('cmt_Text','40ex');
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html 
                + '<div style="display:table-cell;min-width:40px;text-align:right;"><div>'
                + '<div title="Click to edit reminder" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'

                + '<div title="Click to show thread" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="tree">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-structure"></span><span class="ui-button-text"></span>'
                + '</div>'
                
                +'<div title="Click to delete reminder" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div></div>';
        }
        //<div style="float:right"></div>' + '<div style="float:right"></div>
        
        html = html + '</div>';

        return html;
        
    }    
    
});
