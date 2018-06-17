/**
* recordTag.js - assign, detach tags
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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

$.widget( "heurist.recordTag", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 500,
        width:  540,
        modal:  true,
        init_scope: 'selected',
        title:  'Add or Remove Tags for Records',
        helpContent: 'tags.html'  //'usrTags.html'
    },
    
    _tags_selection:[], //selected tags
    _tagSelectionWidget:null, 

    _initControls:function(){
        
        
        $('#div_header')
            .css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html('Select tags to be added or removed for choosen record scope<br>'
            +'Type tag in input. Tags may contain spaces.<br>'
            +'Matching tags are shown as you type. Click on a listed tag to add it.<br>'
            +'New tags are added automatically and are specific to each user.<br>');
        
        this._tagSelectionWidget = $('<div>').css({'width':'100%', padding: '0.2em'}).appendTo( this.element );
        
        var that = this;
        
        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                refreshtags:true, 
                isdialog: false,
                container: this._tagSelectionWidget,
                select_mode: 'select_multi', 
                layout_mode: '<div class="recordList"/>',
                list_mode: 'compact', //special option for tags
                show_top_n_recent: true, //show top and recent lists
                selection_ids: [], //already selected tags
                select_return_mode:'recordset', //ids by default
                onselect:function(event, data){
                    if(data && data.selection){
                        that._tags_selection = data.selection;
                        that._onRecordScopeChange();
                    }
                }
        });
        
        return this._super();
    },
    
    _destroy: function() {
        this._super();
        if(this._tagSelectionWidget) this._tagSelectionWidget.remove();
    },
    
    _getActionButtons: function(){
        var res = this._super();
        
        res[1].text = window.hWin.HR('Remove tags'),
        
        res.push({text:window.hWin.HR('Add tags'),
                    id:'btnDoAction2',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction('assign'); 
                    }});
        return res;
    },
    
    //
    //
    //
    doAction: function(mode){

            var scope_val = this.selectRecordScope.val();
            if(scope_val=='')    return;
            
            if(!mode) mode = 'remove';
               
            alert(mode);
            
            return;
            
            if(window.hWin.HEURIST4.util.isempty(this._tags_selection)){
                window.hWin.HEURIST4.msg.showMsgErr('Need to select tags to '+mode);
                return;
            }
            
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
                'a'          : 'batch',
                'entity'     : 'usrTags',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'mode'       : mode,
                'tagIDs'  : this._tags_selection,
                'recIDs'  : scope
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
            var that = this;                                                
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.res_bookmarks>0);
                            
                            that.closeDialog();
                            
                            var msg = 'For <b>'+response.data.processed + '</b> processed record'
                                + (response.data.processed>1?'s':'') +'<br>';
                             
                             if(response.data.res_tag_added==0 && response.data.res_tag_removed==0) {
                                 msg += 'No tags were affected';
                             }else{
                                 if(response.data.res_tag_added>0){
                                    msg += (response.data.res_tag_added+' tag'
                                            +(response.data.res_tag_added>1?'s were':' was')+' assigned');
                                 }else if($res_tag_removed>0){
                                    msg += (response.data.res_tag_removed+' tag'
                                            +(response.data.res_tag_removed>1?'s were':' was')+' removed');
                                 }
                             } 
                             if(response.data.res_bookmarks>0){
                                 msg += '<br>'+response.data.res_bookmarks+' bookmark'
                                    +(response.data.res_bookmarks>1?'s were':' was')+' added';
                             }else{
                                 msg += '<br>No bookmarks added';
                             }
                                
                            window.hWin.HEURIST4.msg.showMsgDlg(msg, null, 'Result');
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },

    _onRecordScopeChange: function () 
    {
        var isdisabled = (this.selectRecordScope.val()=='') || !(this._tags_selection.length>0);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction2'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );

    },

  
});

