/**
* @file recordBookmark.js
* @brief Provides a widget to remove bookmarks and detach associated personal tags for a scope of records.
* @fileOverview This file defines the `recordBookmark` widget. It allows users to select a scope of
* records (e.g., currently selected records, all records in the current view) and remove their
* bookmarks. The operation also implies the detachment of any personal tags associated with these
* bookmarks for the current user. The widget presents a confirmation and then calls the Heurist API
* to perform the unbookmarking action.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @class heurist.recordBookmark
 * @augments $.heurist.recordAction
 * @description jQuery widget for removing bookmarks and detaching personal tags from a selection of records.
 * This widget allows users to unbookmark records based on a selected scope (e.g., selected records,
 * current search results). The action removes the bookmark entry from `usrBookmarks` and,
 * as a consequence, any personal tags linked through that bookmark for the user.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=300] - The height of the dialog.
 * @param {number} [options.width=540] - The width of the dialog.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.init_scope='selected'] - Initial scope for record selection.
 * @param {string} [options.title='Unbookmark selected records'] - Title of the dialog.
 * @param {string} [options.htmlContent='recordAction.html'] - Default HTML content file (as this widget doesn't have its own specific HTML file listed).
 */
$.widget( "heurist.recordBookmark", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordBookmark
     * @type {object}
     * @property {number} [height=300] - Dialog height.
     * @property {number} [width=540] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [init_scope='selected'] - Initial record scope for the action.
     * @property {string} [title='Unbookmark selected records'] - Dialog title.
     */
    options: {
    
        height: 300,
        width:  540,
        modal:  true,
        init_scope: 'selected',
        title:  'Unbookmark selected records'
    },
    
    /**
     * @function _initControls
     * @memberof heurist.recordBookmark
     * @private
     * @description Initializes controls after HTML content is loaded.
     * Sets a help message specific to unbookmarking and updates the action button label.
     * Calls the parent widget's `_initControls` method.
     */
    _initControls:function(){
        
        this._$('#div_header')
            .css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html(window.hWin.HR('recordUnbookmark_hint'));
        
        this.element.parents('.ui-dialog').find('.btnDoAction').attr('label', window.hWin.HR('Remove Bookmarks'));
        
        return this._super();
    },
    
    /**
     * @function doAction
     * @memberof heurist.recordBookmark
     * @private
     * @description Performs the unbookmarking action.
     * Determines the scope of records to be affected based on the `selectRecordScope` selection.
     * Constructs a batch request to the `EntityMgr` for the `usrBookmarks` entity with `mode: 'unbookmark'`.
     * Displays a success message with the count of removed bookmarks or an error message.
     * Sets `_context_on_close` based on whether any bookmarks were deleted.
     */
    doAction: function(){

            let scope_val = this.selectRecordScope.val();
            if(scope_val=='') return;
            
            let scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
        
            let request = {
                'a'          : 'batch',
                'entity'     : 'usrBookmarks',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'mode'       : 'unbookmark',
                'bkm_RecID'  : scope
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                let that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.deleted>0);
                            
                            that.closeDialog();
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(
                                window.hWin.HR('Bookmarks removed')+' '+response.data.deleted, 1000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

