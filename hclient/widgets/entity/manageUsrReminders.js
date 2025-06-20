/**
* @file manageUsrReminders.js
* @brief Manages User Reminder entities.
* @fileOverview Provides a UI for users to manage their personal reminders. This includes creating, listing, editing, and deleting reminders, which can be associated with records or be standalone.
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
 * @widget heurist.manageUsrReminders
 * @brief Widget for managing User Reminders.
 * @extends $.heurist.manageEntity
 * @description This widget provides an interface for users to manage their personal reminders.
 * It can operate in 'editonly' mode to directly edit a reminder (often associated with a specific record)
 * or in a list mode to manage multiple reminders.
 *
 * @property {string} default_palette_class Default CSS class for theming, typically 'ui-heurist-admin'.
 * @property {boolean} [use_cache=false] If true, client-side caching might be used for data.
 * @property {string} edit_mode Controls how editing is handled. Can be 'editonly' (directly opens editor for a specific or new reminder)
 *                              or 'popup' (opens editor in a dialog when managing a list).
 * @property {string} select_mode Influences selection behavior, e.g., 'manager' for standard list management.
 * @property {string} layout_mode Defines the overall layout. If `edit_mode` is 'editonly', this is also set to 'editonly'.
 * @property {number} width Default width of the widget, conditionally set (e.g., to 790 if 'editonly').
 * @property {number} height Default height of the widget, conditionally set (e.g., to 600 if 'editonly' and not otherwise specified).
 * @property {?function} beforeClose Custom function to call before closing the dialog, conditionally set to an empty function in 'editonly' mode.
 * @property {boolean} [list_header=false] If true, shows a header for the record list (when not in 'editonly' mode).
 * @property {?number} rem_RecID The ID of the Heurist record that this reminder is associated with. Used to load or create the correct reminder in 'editonly' mode.
 */
$.widget( "heurist.manageUsrReminders", $.heurist.manageEntity, {
   
    _entityName:'usrReminders',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageUsrReminders
     * Sets default options for palette class, caching, edit mode, dimensions, and other
     * configurations. It adapts behavior based on whether `edit_mode` is 'editonly'
     * (for a single reminder) or another mode (for list management).
     */
    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-admin';    
        }
        
        this.options.use_cache = false;
        
        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
            if(!(this.options.height>0)) this.options.height = 600;
            this.options.beforeClose = function(){}; //to supress default warning
        }else{
           this.options.edit_mode = 'popup'; 
           this.options.list_header = true; //show header for resultList
        }

        this._super();
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageUsrReminders
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * If `edit_mode` is 'editonly', it loads a specific reminder based on `options.rem_RecID`
     * or prepares a new reminder form.
     * Otherwise (for list modes), it initializes the search form (`searchUsrReminders`)
     * and configures the record list display.
     */
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        if(this.options.edit_mode=='editonly'){
            //load reminder for given record id
            if(this.options.rem_RecID>0){
                    let request = {};
                    request['rem_RecID']  = this.options.rem_RecID;
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
                                else {
                                    //nothing found - add new reminder
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
        }else{
            this.searchForm.searchUsrReminders(this.options);
            
            
            let iheight = 6;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList('option','show_toolbar',false);
            this.recordList.resultList('option','view_mode','list');

            
            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            this._on( this.searchForm, {
                "searchusrremindersonresult": this.updateRecordList
            });
            
        }

        return true;
    },
    
//----------------------------------------------------------------------------------    
    /**
     * @brief Retrieves and validates form values before saving a reminder.
     * @override
     * @memberof heurist.manageUsrReminders
     * @returns {?object} The validated field values, or null if validation fails.
     * Calls the parent `_getValidatedValues`. Then, it checks if at least one recipient
     * field (`rem_ToWorkgroupID`, `rem_ToUserID`, or `rem_ToEmail`) is filled.
     * If not, it displays an error message and returns null.
     */
    _getValidatedValues: function(){
        
        let fields = this._super();
        
        if(fields!=null){
            //validate that at least on recipient is defined
            if(!(fields['rem_ToWorkgroupID'] || fields['rem_ToUserID'] || fields['rem_ToEmail'])){
                  window.hWin.HEURIST4.msg.showMsgFlash('You have to fill one of recipients field');
                  return null;
            }
        }
        
        return fields;
    },

    /**
     * @brief Saves or sends the reminder and handles follow-up actions.
     * @override
     * @memberof heurist.manageUsrReminders
     * @param {?object} fields Field values to save. If null, values are retrieved from the form.
     * @param {string|function} afteraction Action to perform after saving/sending.
     * If in 'editonly' mode and `options.rem_RecID` is set, ensures `rem_RecID` is included in the saved fields.
     * If the 'rem_IsPeriodic' field is set to 'now' (i.e., send immediately), it calls `_sendReminder`.
     * Otherwise, it calls the parent's `_saveEditAndClose` to save the reminder for later.
     */
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.rem_RecID>0){
            let ele2 = this._editing.getFieldByName('rem_RecID');
            ele2.editing_input('setValue', this.options.rem_RecID );
        }
        
        let ele = this._editing.getFieldByName('rem_IsPeriodic');
        let res = ele.editing_input('getValues'); 
        if(res[0]=='now'){
            
            this._sendReminder();
        
        }else{    
            this._super();
        }
    },
    
    /**
     * @brief Sends a reminder immediately.
     * @memberof heurist.manageUsrReminders
     * Retrieves and validates form values. If valid, it constructs a request with action 'action'
     * for the 'usrReminders' entity and sends it to the server. Displays a success or error message.
     * This is typically used when `rem_IsPeriodic` is 'now'.
     */
    _sendReminder: function(){

        let fields = this._getValidatedValues(); 
        if(fields==null) return; //validation failed
        
        let request = {                                                                                        
            'a'          : 'action',
            'entity'     : this.options.entity.entityName,
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : fields                     
            };
            
            let that = this;                                                
            let dlged = this._getEditDialog();
            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgFlash(that.options.entity.entityTitle+' '+window.hWin.HR('has been sent'));
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        
    },    
    
    /**
     * @brief Handles events after a reminder is saved.
     * @override
     * @memberof heurist.manageUsrReminders
     * @param {number} recID The ID of the saved reminder.
     * @param {object} fieldvalues The saved field values.
     * Calls the parent's `_afterSaveEventHandler`.
     * If in 'editonly' mode, closes the dialog. Otherwise, updates the local recordset
     * and refreshes the list view.
     */
    _afterSaveEventHandler: function( recID, fieldvalues ){
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode=='editonly'){
            this.closeDialog(true);
        }else{
            this.getRecordSet().setRecord(recID, fieldvalues);    
            this.recordList.resultList('refreshPage');  
        }
    },

    /**
     * @brief Handles the deletion of a reminder, with a confirmation prompt.
     * @override
     * @memberof heurist.manageUsrReminders
     * @param {boolean} [unconditionally=false] If true, deletes without confirmation.
     * If `unconditionally` is false (the default), it shows a confirmation dialog.
     * If confirmed, or if `unconditionally` is true, it calls the parent's `_deleteAndClose` method.
     */
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this reminder?', function(){ that._deleteAndClose(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    /**
     * @brief Performs actions after the edit form for a reminder is initialized.
     * @override
     * @memberof heurist.manageUsrReminders
     * Calls the parent's `_afterInitEditForm`.
     * If in 'editonly' mode, it sets up logic for the 'rem_IsPeriodic' field:
     *   - If 'now' (send immediately), it hides frequency/start date fields and changes the save button label to "Send".
     *   - If 'later' (scheduled), it shows frequency/start date fields and sets the save button label to "Save".
     * It also adds mutual exclusivity logic to recipient fields (`rem_ToWorkgroupID`, `rem_ToUserID`, `rem_ToEmail`),
     * ensuring only one can be filled at a time.
     */
    _afterInitEditForm: function(){

        this._super();
    
        let that = this;
        let ele = this._editing.getFieldByName('rem_IsPeriodic');
        
        if(this.options.edit_mode=='editonly'){
        
            //reminder
            let val = this._getField('rem_StartDate');
            
            let isManual = window.hWin.HEURIST4.util.isempty(val) || val=='0000-00-00';
            
            function __onChangeType(){ 
                let ele1 = that._editing.getFieldByName('rem_Freq');
                let ele2 = that._editing.getFieldByName('rem_StartDate');
                
                let btn_save;
                if(that._toolbar){
                    btn_save = that._toolbar.find('.btnRecSave');
                }
                
                let res = ele.editing_input('getValues'); 
                if(res[0]=='now'){
                        ele2.editing_input('setValue', '');
                        ele1.hide();
                        ele2.hide();
                        
                        if(btn_save) btn_save.button('option','label','Send');
                }else{
                        ele1.show();
                        ele2.show();
                        
                        if(btn_save) btn_save.button('option','label','Save');
                }
            }
            
            ele.editing_input('option', 'change', __onChangeType);
            ele.editing_input('setValue', isManual?'now':'later');
            __onChangeType();
        
        }else{
            ele.editing_input('option','readonly',true);
            ele.editing_input('setValue', 'later');
            ele.hide();
        }
        
        let ele1 = this._editing.getFieldByName('rem_ToWorkgroupID');
        let ele2 = this._editing.getFieldByName('rem_ToUserID');
        let ele3 = this._editing.getFieldByName('rem_ToEmail');
        
        
        function __onChange2( ){
           let res = $(this.element).editing_input('getValues')
           if(res[0]!=''){
               let dtID = $(this.element).editing_input('option','dtID');
               if(dtID!='rem_ToWorkgroupID') ele1.editing_input('setValue', '');
               if(dtID!='rem_ToUserID') ele2.editing_input('setValue', '');
               if(dtID!='rem_ToEmail') ele3.editing_input('setValue', '');
           }
        }
    
        ele1.editing_input('option', 'change', __onChange2);
        ele2.editing_input('option', 'change', __onChange2);
        ele3.editing_input('option', 'change', __onChange2);

    
    },

    /**
     * @brief Renders the header for the reminder list.
     * @override
     * @memberof heurist.manageUsrReminders
     * @returns {string} HTML string for the list header.
     * Defines column headers: 'Record title', 'Recipient', 'Freq', 'Date', 'Message'.
     */
    _recordListHeaderRenderer:function(){
        
        function __cell(colname, width){
          //return '<div style="display:table-cell;width:'+width+'ex">'+colname+'</div>';            
          return '<div style="width:'+width+'ex">'+colname+'</div>';            
        }
        
        //return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
        return __cell('Record title',35)+__cell('Recipient',17)+__cell('Freq',7)
                    +__cell('Date',12)+__cell('Message',50);
                    
    },
    
    //----------------------
    /**
     * @brief Renders a single reminder item in the list.
     * @override
     * @memberof heurist.manageUsrReminders
     * @param {HRecordSet} recordset The recordset containing the item.
     * @param {object} record The specific record object for the item to render.
     * @returns {string} HTML string representing the reminder item.
     * Formats the display of a reminder, including its associated record title (if any),
     * recipient, frequency, start date, and message. Includes edit/delete buttons
     * if in 'manager' select mode and 'popup' edit mode.
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
            return '<div class="truncate" style="display:inline-block;'+swidth+'">'
                    +fld(fldname)+'</div>';
        }
        
        //rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,rem_RecTitle
        //rem_ToWorkgroupName
        //rem_ToUserName        
        
        
        let recID   = fld('rem_ID');
        let recipient = fld('rem_ToWorkgroupName');
        if(!recipient) recipient = fld('rem_ToUserName');
        if(!recipient) recipient = fld('rem_ToEmail');
        recipient = '<div class="truncate" style="display:inline-block;width:17ex">'+recipient+'</div>';
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + fld2('rem_RecTitle','35ex') + ' ' + recipient 
                + fld2('rem_Freq','7ex')+fld2('rem_StartDate','14ex')
                + fld2('rem_Message','50ex'); //position:absolute;left:500px;bottom:6px
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html 
                + '<div class="logged-in-only" style="width:60px;display:inline-block">'
                + '<div title="Click to edit reminder" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                +'<div title="Click to delete reminder" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        }
        //<div style="float:right"></div>' + '<div style="float:right"></div>
        
        html = html + '</div>';

        return html;
        
    }    
    
});
