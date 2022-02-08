/**
* recordNotify.js - send email about set of records
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

$.widget( "heurist.recordNotify", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 500,
        width:  700,
        modal:  true,
        init_scope: 'selected',
        title:  'Notification',
        helpContent: ''  //'usrTags.html'
    },
    
    _reminderWidgetContainer:null,
    _reminderWidget:null,
    

    _initControls:function(){
        
        this.element.find('#div_header')
            .css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html('Share these records with other users via email<br>'
            +'Notification includes a URL which will open the list of records<br>'
            +'in a Heurist search, from which they can be bookmarked<br>');
        
        this._reminderWidgetContainer = $('<div>').addClass('ent_wrapper').css({'top':'120px'}).appendTo( this.element );
        
        var that = this;
        
        this._reminderWidget = window.hWin.HEURIST4.ui.showEntityDialog('usrReminders', {
                isdialog: false,
                container: this._reminderWidgetContainer,
                edit_mode: 'editonly',
                onInitFinished: function(){
                    that._reminderWidgetContainer.find('.heurist-helper1').each(function(idx,item){
                        $(item).html($(item).html().replace('reminder', "notification") );
                    });
                    that._reminderWidgetContainer.find('fieldset').last().hide(); //hide When
                    that._reminderWidgetContainer.find('.ent_footer.editForm-toolbar').hide(); // hide Toolbar                    
                }
        });
        
        
        //align scope selector and edit form
        this.element.find('#div_fieldset').css({'padding':'10px 0px'});
        this.element.find('#div_fieldset .header').css({'padding':'0 24px 0 0'});
        
        return this._super();
    },
    
    _destroy: function() {
        this._super();
        if(this._reminderWidget) this._reminderWidget.remove();
    },
    
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR('Notify');
        return res;
    },
    
    //
    //
    //
    doAction: function(){

        var scope_val = this.selectRecordScope.val();
        if(scope_val=='')    return;
        
        var editForm = $(this._reminderWidgetContainer).manageUsrReminders('instance');
        var fields = editForm._getValidatedValues();//this._reminderWidget.manageUsrReminders('_getValidatedValues'); 
        if(fields==null) return; //validation failed

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
            'entity'     : 'usrReminders',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : fields,
            'rec_IDs'    : scope                     
            };
            
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
            
            var that = this;                                                
            var dlged = editForm._getEditDialog();
            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgFlash('Notification '+window.hWin.HR('has been sent'));
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                        
    },

  
});

