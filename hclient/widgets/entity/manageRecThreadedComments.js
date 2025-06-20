/**
* @file manageRecThreadedComments.js
* @brief Manages threaded comments for records.
* @fileOverview Provides a UI for displaying, adding, and managing threaded comments associated with a Heurist record. It typically integrates with the record viewing or editing interface.
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



//
// there is no search, select mode for reminders - only edit
//
/**
 * @widget heurist.manageRecThreadedComments
 * @brief Widget for managing threaded comments.
 * @extends $.heurist.manageEntity
 * @property {boolean} [use_cache=false] Whether to use client-side caching. Set to false by default.
 * @property {string} [edit_mode='popup'] Editing mode. Can be 'editonly' or 'popup'.
 * @property {string} [select_mode='manager'] Selection mode. If 'editonly', this is set to 'manager'.
 * @property {string} [layout_mode='editonly'] Layout mode. If 'editonly', this is set.
 * @property {number} [width=790] Width of the widget, especially in 'editonly' mode.
 * @property {number} [height=600] Height of the widget, especially in 'editonly' mode.
 * @property {boolean} [list_header=true] Whether to show the header for the comments list.
 * @property {?number} rec_ID The ID of the record these comments are associated with. (Implicitly used via cmt_RecID)
 * @property {?number} cmt_ID The ID of a specific comment, used when `edit_mode` is 'editonly' to load that comment.
 * @property {?number} cmt_RecID The ID of the record these comments belong to. Used when adding a new comment in 'editonly' mode.
 * @property {?number} cmt_ParentCmtID The ID of the parent comment if this is a reply. Used when adding a new comment in 'editonly' mode.
 * @property {?string} cmt_Type The type of comment (though not explicitly used in provided code, it's a common pattern).
 * @property {boolean} [allow_add=true] Whether to allow adding new comments. (Implicit, UI for adding is present)
 * @property {boolean} [allow_edit=true] Whether to allow editing existing comments. (Implicit, edit actions are present)
 * @property {boolean} [allow_delete=true] Whether to allow deleting comments. (Implicit, delete actions are present)
 */
$.widget( "heurist.manageRecThreadedComments", $.heurist.manageEntity, {
   
    _entityName:'recThreadedComments',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    /**
     * @brief Initializes the widget.
     * @memberof heurist.manageRecThreadedComments
     * @override
     * @description Sets `use_cache` to false. Adjusts `edit_mode`, `select_mode`, `layout_mode`,
     * `width`, and `height` based on the initial `edit_mode`. Calls parent's `_init`.
     */
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
    
    /**
     * @brief Initializes the controls for the widget.
     * @memberof heurist.manageRecThreadedComments
     * @override
     * @description Calls parent's `_initControls`.
     * If `edit_mode` is 'editonly':
     *  - Loads a specific comment for editing if `options.cmt_ID` is provided.
     *  - Otherwise, prepares to add a new comment (using `options.cmt_RecID` and `options.cmt_ParentCmtID`).
     * If not 'editonly':
     *  - Initializes the search form and result list for comments.
     * @returns {boolean} False if the parent's `_initControls` fails, otherwise true.
     */
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        if(this.options.edit_mode=='editonly'){
            //load comment for edit
            if(this.options.cmt_ID>0){
                    let request = {};
                    request['cmt_ID']  = this.options.cmt_ID;
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
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

    /**
     * @brief Saves the comment and closes the edit form/dialog.
     * @memberof heurist.manageRecThreadedComments
     * @override
     * @param {?object} fields Field values to save. If null, values are retrieved from the edit form.
     * @param {?string} afteraction Action to perform after saving (not explicitly used in this override).
     * @description Ensures `cmt_RecID` is set if in 'editonly' mode and a `cmt_RecID` was provided in options.
     * Calls the parent's `_saveEditAndClose`.
     */
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.cmt_RecID>0){
            let ele2 = this._editing.getFieldByName('cmt_RecID');
            ele2.editing_input('setValue', this.options.cmt_RecID );
        }
        
        
        this._super();
    },
    
    /**
     * @brief Handles events after a comment is saved.
     * @memberof heurist.manageRecThreadedComments
     * @override
     * @param {number} recID The ID of the saved comment.
     * @param {object} fields The saved field values.
     * @description Calls parent's handler. If in 'editonly' mode, closes the dialog.
     */
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        if(this.options.edit_mode=='editonly'){
            this.closeDialog();
        }
    },

    /**
     * @brief Renders the header for the comments list.
     * @memberof heurist.manageRecThreadedComments
     * @override
     * @returns {string} HTML string for the list header.
     */
    _recordListHeaderRenderer:function(){
        
        function __cell(colname, width){
          return '<div style="padding:6px 0 0 4px;display:table-cell;width:'+width+'ex">'+colname+'</div>';            
        }
        
        return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
                    +__cell('Record title',18)+
                    +__cell('Modiied',8)+__cell('Text',40)+__cell('',12);
    },
    
    /**
     * @brief Renders a single comment item in the list.
     * @memberof heurist.manageRecThreadedComments
     * @override
     * @param {HRecordSet} recordset The recordset containing the comment data.
     * @param {object} record The comment record object to render.
     * @returns {string} HTML string representing the list item.
     * @description Displays comment record title, modified date, and text.
     * Includes edit, view thread, and delete buttons if in manager/popup mode.
     */
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="truncate" style="padding:6px 0 0 4px;display:table-cell;'+swidth+'">'
                    +fld(fldname)+'</div>';
        }
        
        let recID   = fld('cmt_ID');
        
        let html = '<div class="recordDiv" style="display:table-row;height:3em" id="rd'+recID+'" recid="'+recID+'">'
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
