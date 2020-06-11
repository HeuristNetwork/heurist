/**
* recordTag.js - assign, detach tags
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

$.widget( "heurist.recordTag", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 500,
        width:  700,
        modal:  true,
        init_scope: 'selected',
        title:  'Add or Remove Tags for Records',
        helpContent: 'tags.html',  //'usrTags.html'
        groups: 'all',
        modes: ['assign','remove']       //bookmark_url - just selection of tags - no real action
    },
    
    _tags_selection:[], //selected tags
    _tagSelectionWidget:null, 

    _initControls:function(){
        
        var sMsg;
        if(this.options.modes=='bookmark_url'){ //selection only - no scope needed
            this.element.find('#div_fieldset').hide();
            sMsg = 'Select tags to be added to bookmarks for chosen URLs<br>';
        }else{
            sMsg = 'Select tags to be added or removed for chosen record scope<br>';
        }   
        sMsg = sMsg 
            //+'Type tag in input. Tags may contain spaces.<br>'
            +'Matching tags are shown as you type. Click on a listed tag to add it.<br>'
            +'Unrecognised tags are added automatically as user-specific tags <br>'
            +'(group tags must be added explicitly by a group administrator). Tags may contain spaces.';
        
        this.element.find('#div_header')
            //.css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html(sMsg);
        
        this._tagSelectionWidget = $('<div>').css({'width':'100%', padding: '0.2em'}).appendTo( this.element );
        
        var that = this;
        
        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                refreshtags:true, 
                isdialog: false,
                container: this._tagSelectionWidget,
                select_mode: 'select_multi', 
                layout_mode: '<div class="recordList"/>',
                list_mode: 'compact', //special option for tags
                groups: this.options.groups, //all,personal,grouponly,or list of ids
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
        
        res = this._super();
        
        //'width':106,'min-width':96,
        this.element.find('fieldset > div > .header').css({'padding':'0 16 0 0'});
        
        return res;
    },
    
    _destroy: function() {
        this._super();
        if(this._tagSelectionWidget) this._tagSelectionWidget.remove();
    },
    
    _getActionButtons: function(){
        var res = this._super();
        
        var that = this;
        
        
        if(this.options.modes.indexOf('bookmark_url')>=0){
            res[1].text = window.hWin.HR('Bookmark');
            res[1].click = function() { 
                        that.doTagSelection();                        
                    };
        }else if(this.options.modes.indexOf('remove')>=0){
            res[1].text = window.hWin.HR('Remove tags');
        }else{
            res.pop(); //remove last
        }
            
        
        if(this.options.modes.indexOf('assign')>=0)
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
    // ony tag selection for bookmark_url (see importHyperlinks)
    //
    doTagSelection: function(){
        this._context_on_close = this._tags_selection;
        this.closeDialog();
    },
    //
    //
    //
    doAction: function(mode){
        
            var scope_val = this.selectRecordScope.val();
            if(scope_val=='') return;
            
            if(mode!='assign') mode = 'remove';

            
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
                'recIDs'  : scope.join(',')
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
            var that = this;                                                
console.log(request);            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.res_bookmarks>0);
                            
                            that.closeDialog();
                            
                            var msg = 'For <b>'+response.data.processed + '</b> processed record'
                                + (response.data.processed>1?'s':'') +'<br>';
                             
                             if(response.data.added==0 && response.data.removed==0) {
                                 msg += 'No tags were affected';
                             }else{
                                 if(response.data.added>0){
                                     msg += (response.data.added+' tag'
                                            +(response.data.added>1?'s were':' was')+' assigned');
                                            
                                     if(response.data.bookmarks>0){
                                         msg += '<br>'+response.data.bookmarks+' bookmark'
                                            +(response.data.bookmarks>1?'s were':' was')+' added';
                                     }else{
                                         msg += '<br>No bookmarks added';
                                     }
                                            
                                 }else if(response.data.removed>0){
                                    msg += (response.data.removed+' tag'
                                            +(response.data.removed>1?'s were':' was')+' removed');
                                 }
                             } 
                                
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },

    _onRecordScopeChange: function () 
    {
        var isdisabled = (this.options.modes.indexOf('bookmark_url')<0 && this.selectRecordScope.val()=='') 
                        || !(this._tags_selection.length>0);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction2'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );

    },

  
});

