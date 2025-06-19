/**
* @file manageUsrBookmarks.js
* @brief Manages User Bookmark entities.
* @fileOverview Provides a UI for users to manage their personal bookmarks of records or other items within Heurist. This includes creating, listing, editing (e.g., notes), and deleting bookmarks.
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//
// there is no search, select mode for bookmarks - only edit
//
/**
 * @class heurist.manageUsrBookmarks
 * @brief Widget for managing User Bookmarks.
 * @augments $.heurist.manageEntity
 * @description This widget provides an interface for users to manage their personal bookmarks.
 * It typically operates in 'editonly' mode to directly edit a bookmark associated with a specific record.
 *
 * @property {string} default_palette_class Default CSS class for theming, typically 'ui-heurist-admin'.
 * @property {string} edit_mode Set to 'editonly', as the widget is designed to edit a specific bookmark.
 * @property {string} select_mode Set to 'manager'. In conjunction with 'editonly', this means no list selection is presented.
 * @property {string} layout_mode Set to 'editonly', reinforcing that only the editing interface is shown.
 * @property {number} width Default width of the widget, set to 620 pixels.
 * @property {number} height Default height of the widget, typically 410 pixels if not otherwise specified.
 * @property {?number} bkm_RecID The ID of the Heurist record that this bookmark pertains to. This is a key option used to load or create the correct bookmark.
 */
$.widget( "heurist.manageUsrBookmarks", $.heurist.manageEntity, {
   
    _entityName:'usrBookmarks',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageUsrBookmarks
     * Sets default options for palette class, edit mode (to 'editonly'), dimensions,
     * and other configurations specific to managing a single user bookmark.
     */
    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-admin';   
        }
        
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 620;
        if(!(this.options.height>0)) this.options.height = 410;
        

        this._super();
    },
    
    //  
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageUsrBookmarks
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * Since the widget is 'editonly', this method directly attempts to load an existing
     * bookmark for the given `options.bkm_RecID`. If found, it opens the edit form for that
     * bookmark. If not found, it opens a new bookmark form.
     */
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        //load bookmark for given record id
        if(this.options.bkm_RecID>0){
                let request = {};
                request['bkm_RecID']  = this.options.bkm_RecID;
                request['a']          = 'search'; //action
                request['entity']     = this._entityName;
                request['details']    = 'full';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                let that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            let recset = new HRecordSet(response.data);
                            if(recset.length()>0){
                                that.updateRecordList(null, {recordset:recset});
                                that.addEditRecord( recset.getOrder()[0] );
                            }
                            else {
                                //nothing found - add new bookmark
                                that.addEditRecord(-1);
                            }                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            that.closeEditDialog();
                        }
                    });        
                    
        }else{
            this.addEditRecord(-1);
        }
        
        return true;
    },
    
    /**
     * @brief Customizes the buttons for the edit dialog.
     * @override
     * @memberof heurist.manageUsrBookmarks
     * @returns {Array<object>} An array of button definition objects.
     * Retrieves the default buttons from the parent widget and then modifies the "Remove"
     * button's text to "Delete bookmark".
     */
    _getEditDialogButtons: function(){
        let btns = this._super();
        
        for(let idx in btns){
            if(btns[idx].id=='btnRecRemove'){
                btns[idx].text = window.hWin.HR('Delete bookmark');
                /*btns[idx].click = function(){
                        window.hWin.HEURIST4.msg.showMsgDlg(
            'Are you sure you wish to delete this bookmark?', function(){
                that._deleteAndClose();
                }, {title:'Warning',yes:'Proceed',no:'Cancel'});        
                }*/
                break;
            }
        }
        
        return btns;
    },
    
//----------------------------------------------------------------------------------    
    /**
     * @brief Handles the deletion of a bookmark, with a confirmation prompt.
     * @override
     * @memberof heurist.manageUsrBookmarks
     * @param {boolean} [unconditionally=false] If true, deletes without confirmation.
     * If `unconditionally` is false (the default), it shows a confirmation dialog
     * asking "Are you sure you wish to delete this bookmark?". If confirmed, or if
     * `unconditionally` is true, it calls the parent's `_deleteAndClose` method.
     */
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this bookmark?', function(){ that._deleteAndClose(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },

    /**
     * @brief Saves the bookmark.
     * @override
     * @memberof heurist.manageUsrBookmarks
     * @param {?object} fields Field values to save. If null, values are retrieved from the form.
     * @param {string|function} afteraction Action to perform after saving.
     * Ensures that `bkm_RecID` (the ID of the record being bookmarked) is set from
     * `this.options.bkm_RecID` before calling the parent's `_saveEditAndClose` method.
     */
    _saveEditAndClose: function( fields, afteraction ){
        
        let ele2 = this._editing.getFieldByName('bkm_RecID');
        ele2.editing_input('setValue', this.options.bkm_RecID );
        
        this._super(); // Calls parent _saveEditAndClose, which will handle fields and afteraction
    },
    
    
    /**
     * @brief Handles events after a bookmark is saved.
     * @override
     * @memberof heurist.manageUsrBookmarks
     * @param {number} recID The ID of the saved bookmark.
     * @param {object} fields The saved field values.
     * Calls the parent's `_afterSaveEventHandler`.
     * Closes the dialog forcefully (to avoid warnings, as it's 'editonly').
     */
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        this.closeDialog(true); //force to avoid warning
    },
    
    
});
