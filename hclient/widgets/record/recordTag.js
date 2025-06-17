/**
* @file recordTag.js
* @brief Assigns or detaches tags from records.
* @fileOverview This file defines the `recordTag` widget. It allows users to assign tags to, or
* remove tags from, a selected scope of records. It can also be used in a mode to simply select
* tags (e.g., for bookmarking with specific tags). The widget embeds the `usrTags` entity
* dialog/widget for tag selection and management.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @widget heurist.recordTag
 * @extends $.heurist.recordAction
 * @description jQuery widget for assigning or removing tags from records, or selecting tags.
 * This widget provides UI for users to select tags (using an embedded `usrTags` dialog)
 * and then either assign these tags to a scope of records, remove them, or simply
 * return the selected tags (e.g., for bookmarking operations).
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=500] - The height of the dialog.
 * @param {number} [options.width=700] - The width of the dialog.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.init_scope='selected'] - Initial scope for record selection.
 * @param {string} [options.title='Add or Remove Tags for Records'] - Title for the dialog.
 * @param {string|boolean} [options.helpContent='recordTags'] - Help content identifier or URL.
 * @param {Array<string>} [options.scope_types=['selected', 'collected', 'current']] - Available record scope types.
 * @param {string} [options.groups='all'] - Tag groups to display (e.g., 'all', 'personal', 'grouponly', or list of IDs). Passed to the embedded tag selection widget.
 * @param {Array<string>|string} [options.modes=['assign','remove']] - Operation modes. Can be 'assign', 'remove', 'bookmark', or 'bookmark_url'.
 *        'bookmark_url' implies only tag selection without direct action. 'bookmark' implies assigning tags and bookmarking.
 */
$.widget( "heurist.recordTag", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordTag
     * @type {object}
     * @property {number} [height=500] - Dialog height.
     * @property {number} [width=700] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [init_scope='selected'] - Initial record scope.
     * @property {string} [title='Add or Remove Tags for Records'] - Dialog title.
     * @property {string|boolean} [helpContent='recordTags'] - Help content identifier.
     * @property {Array<string>} [scope_types=['selected', 'collected', 'current']] - Available record scopes.
     * @property {string} [groups='all'] - Tag groups for selection.
     * @property {Array<string>|string} [modes=['assign','remove']] - Operation modes: 'assign', 'remove', 'bookmark', 'bookmark_url'.
     */
    options: {
    
        height: 500,
        width:  700,
        modal:  true,
        init_scope: 'selected',
        title:  'Add or Remove Tags for Records',
        helpContent: 'recordTags',
        scope_types: ['selected', 'collected', 'current'],
        groups: 'all',
        modes: ['assign','remove']       //bookmark=assign bookmark_url - just selection of tags - no real action
    },
    
    /**
     * @member {Array<string|number>} _tags_selection
     * @memberof heurist.recordTag
     * @private
     * @description Array of selected tag IDs or a recordset of tags, depending on `select_return_mode` of the embedded tag widget.
     */
    _tags_selection:[], //selected tags
    /**
     * @member {?jQuery} _tagSelectionWidget
     * @memberof heurist.recordTag
     * @private
     * @description jQuery object for the div that contains the embedded `usrTags` selection widget.
     */
    _tagSelectionWidget:null, 

    /**
     * @function _initControls
     * @memberof heurist.recordTag
     * @private
     * @description Initializes controls. Sets a header message based on `options.modes`.
     * Embeds and initializes the `usrTags` entity dialog for tag selection.
     * Calls parent's `_initControls`.
     * @returns {boolean|undefined} Value returned by parent's `_initControls`.
     */
    _initControls:function(){
        
        this.options.helpContent = 'recordTags';
        
        let sMsg;
        if(this.options.modes=='bookmark_url'){
            this._$('#div_fieldset').hide();
            sMsg = window.hWin.HR('recordTag_hint0');
        }else if (this.options.modes=='bookmark') { 
            sMsg = window.hWin.HR('recordTag_hint1');
            this.options.helpContent = 'recordBookmark';
        }else{
            sMsg = window.hWin.HR('recordTag_hint2');;
        }   
        sMsg = sMsg + window.hWin.HR('recordTag_hint3');
        
        this._$('#div_header')
            //.css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html(sMsg);
        
        this._tagSelectionWidget = $('<div>').css({'width':'100%', padding: '0.2em'}).appendTo( this.element );
        
        let that = this;
        
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
        
        let res = this._super();
        
        //'width':106,'min-width':96,
        this._$('fieldset > div > .header').css({'padding':'0 16 0 0'});
        
        return res;
    },
    
    /**
     * @function _destroy
     * @memberof heurist.recordTag
     * @private
     * @description Cleans up the widget. Removes the embedded `_tagSelectionWidget`.
     * Calls parent's `_destroy`.
     */
    _destroy: function() {
        this._super();
        if(this._tagSelectionWidget) this._tagSelectionWidget.remove();
    },
    
    /**
     * @function _getActionButtons
     * @memberof heurist.recordTag
     * @private
     * @description Gets action buttons for the dialog, configured based on `options.modes`.
     * May include 'Add tags', 'Remove tags', or 'Bookmark' buttons.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        
        let that = this;
        
        
        if(this.options.modes.indexOf('bookmark_url')>=0){
            res[1].text = window.hWin.HR('Bookmark');
            res[1].click = function() { 
                            that.doTagSelection();                        
                        };
        }else if(this.options.modes=='bookmark'){
            
            res[1].text = window.hWin.HR('Bookmark');
            res[1].click = function() { 
                            that.doAction('assign');
                        };
            
        }else if(this.options.modes.indexOf('remove')>=0){
            res[1].text = window.hWin.HR('Remove tags');
        }else{
            res.pop(); //remove last
        }
            
        
        if(this.options.modes.indexOf('assign')>=0)
            res.push({text:window.hWin.HR('Add tags'),
                    disabled:'disabled',
                    css:{'float':'right'},
                    class: 'ui-button-action btnDoAction2',
                    click: function() { 
                            that.doAction('assign'); 
                    }});
        return res;
    },

    /**
     * @function doTagSelection
     * @memberof heurist.recordTag
     * @private
     * @description Handles the action for 'bookmark_url' mode. Sets `_context_on_close`
     * with the `_tags_selection` and closes the dialog. This is for returning selected tags
     * to a calling context.
     */
    doTagSelection: function(){
        this._context_on_close = this._tags_selection;
        this.closeDialog();
    },
    /**
     * @function doAction
     * @memberof heurist.recordTag
     * @private
     * @description Performs tag assignment or removal.
     * Determines record scope and selected tags. Validates that tags are selected.
     * Constructs a batch request to `EntityMgr` for the `usrTags` entity with the specified `mode` ('assign' or 'remove').
     * Displays results (processed records, tags added/removed, bookmarks added).
     * @param {string} mode - The action mode, either 'assign' or 'remove'.
     */
    doAction: function(mode){
        
            let scope_val = this.selectRecordScope.val();
            if(scope_val=='') return;
            
            if(mode!='assign') mode = 'remove';

            
            if(window.hWin.HEURIST4.util.isempty(this._tags_selection)){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: `Need to select tags to ${mode}`,
                    error_title: 'Missing tags',
                    status: window.hWin.ResponseStatus.INVALID_REQUEST
                });
                return;
            }
            
            let scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else if(scope_val == 'collected'){
                scope = this._currentRecordsetColIds;
            }else{ //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
            
        
            let request = {
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
                
            let that = this;                                                
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.res_bookmarks>0);
                            
                            that.closeDialog();
                            
                            let msg = window.hWin.HR('Processed records')+': '+response.data.processed + '<br>';
                             
                             if(response.data.added==0 && response.data.removed==0) {
                                 msg += window.hWin.HR('No tags were affected');
                             }else{
                                 if(response.data.added>0){
                                     msg += window.hWin.HR('Tags added')+': '+response.data.added + '<br>';

                                     if(response.data.bookmarks>0){
                                        msg += window.hWin.HR('Bookmarks added')+': '+response.data.bookmarks;
                                     }
                                     
                                 }else if(response.data.removed>0){
                                     msg += (window.hWin.HR('Tags removed')+': '+response.data.removed);
                                 }
                             } 
                                
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },

    /**
     * @function _onRecordScopeChange
     * @memberof heurist.recordTag
     * @private
     * @description Handles changes in record scope or tag selection.
     * Enables/disables action buttons based on whether a valid scope and tags are selected.
     * Overrides parent's method.
     */
    _onRecordScopeChange: function () 
    {
        let isdisabled = (this.options.modes.indexOf('bookmark_url')<0 && this.selectRecordScope.val()=='') 
                        || !(this._tags_selection.length>0);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction2'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );

    },

  
}); 

