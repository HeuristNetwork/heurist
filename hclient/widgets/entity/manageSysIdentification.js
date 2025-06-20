/**
* @file manageSysIdentification.js
* @brief Manages System Identification and Access Control settings.
* @fileOverview Provides a UI for administrators to configure system identification, authentication methods (e.g., LDAP, Shibboleth), IP whitelisting/blacklisting, and other access control mechanisms.
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



/**
 * @widget heurist.manageSysIdentification
 * @brief Widget for System Identification and Access Control.
 * @extends $.heurist.manageEntity
 * @description This widget provides an interface for administrators to configure
 * system-wide identification, authentication, and access control settings.
 * It operates in 'editonly' mode, directly loading the single system identification record for editing.
 *
 * @property {string} default_palette_class Default CSS class for theming, set to 'ui-heurist-design'.
 * @property {string} edit_mode Set to 'editonly', as this widget edits a single, specific system record.
 * @property {string} select_mode Set to 'manager'. In conjunction with 'editonly', this means no list selection is presented.
 * @property {string} layout_mode Set to 'editonly', reinforcing that only the editing interface for the system record is shown.
 * @property {number} width Default width of the widget, set to 1020 pixels.
 * @property {number} height Default height of the widget, set to 800 pixels.
 * @property {boolean} use_cache If true, client-side caching might be used for data; set to true.
 */
$.widget( "heurist.manageSysIdentification", $.heurist.manageEntity, {
    
    _entityName:'sysIdentification',
    
    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageSysIdentification
     * Sets default options for palette class, edit mode (to 'editonly'), dimensions,
     * and other configurations specific to managing system identification settings.
     */
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 1020;
        this.options.height = 800;
        this.options.use_cache = true;
        
        this._super();
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageSysIdentification
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * Fetches the system identification data (expected to be a single record) and
     * then calls `addEditRecord` to display it in the form, as this widget is 'editonly'.
     * It also sets up a 'mouseleave' event handler on the widget's main element
     * to potentially trigger `defaultBeforeClose` if the window loses focus,
     * unless the target is a button (e.g., from a select popup).
     */
    _initControls: function() {

        if(!this._super()){
            return false;
        }

        let that = this;
        

        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                that._cachedRecordset = response;
                that.updateRecordList(null, {recordset:response});
                that.addEditRecord( response.getOrder()[0] );
            });
        
        if(!this.options.isdialog){
            let fele = this.element.find('.ent_wrapper:first');
            $(fele).on("mouseleave", function(e){
                if($(e.target).is('button')){ return; } // for Rectype Select popup
                
                setTimeout(function(){ // Determine if user has switched tabs/minimised window
                    if(document.hasFocus()){
                        that.defaultBeforeClose();
                    }
                }, 200);
            });
        }
            
        return true;
    }, 
    
    /**
     * @brief Customizes the buttons for the edit dialog.
     * @override
     * @memberof heurist.manageSysIdentification
     * @returns {Array<object>} An array of button definition objects.
     * Retrieves the default buttons from the parent widget and then removes the "Remove"
     * button, as the system identification record should not be deleted.
     */
    _getEditDialogButtons: function(){
        let btns = this._super();
        
        for(let idx in btns){
            if(btns[idx].id=='btnRecRemove'){
                //remove this button -    
                btns.splice(idx,1);
                break;
            }
        }
        
        return btns;
    },
    
    /**
     * @brief Performs actions after the edit form is initialized.
     * @override
     * @memberof heurist.manageSysIdentification
     * Calls the parent `_afterInitEditForm`.
     * Customizes the appearance of form field labels (wider).
     * Sets up the file uploader's paste zone for the entire form.
     * Hides the 'sys_URLCheckFlag' field if the user is not a super admin (access level < 2).
     * Initializes 'sys_AllowRegistration' and 'sys_AllowUserImportAtLogin' fields based on
     * the bitmask value of 'sys_AllowRegistration' from the loaded record.
     * Resets the modified flag of the editing widget.
     */
    _afterInitEditForm: function(){

        const record = this._cachedRecordset.getFirstRecord();

        //make labels in edit form wider
        this.editForm.find('.header').css({'min-width':'250px','width':'250px', 'font-size': '0.9em'});
        
        this._super();
        
        //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
        let ele = this.editForm.find('input[type=file]');  //this._as_dialog.find
        if(ele.length>0){
            ele.fileupload('option','pasteZone', this.editForm);
        }

        if(!window.hWin.HAPI4.has_access(2)){
            this._editing.getFieldByName('sys_URLCheckFlag').hide();
        }

        // Set allow registration and allow import user
        let status = this._cachedRecordset.fld(record, 'sys_AllowRegistration');
        let $ele = this._editing.getFieldByName('sys_AllowRegistration');
        $ele.editing_input('setValue', [1 & status]);
        
        $ele = this._editing.getFieldByName('sys_AllowUserImportAtLogin');
        $ele.editing_input('setValue', [2 & status]);

        this._editing.setModified(0);
    },
	
    /**
     * @brief Saves the system identification settings and handles follow-up actions.
     * @override
     * @memberof heurist.manageSysIdentification
     * @param {?object} fields Field values to save. If null, values are retrieved from the form.
     * @param {string|function} afterAction Action to perform after saving (e.g., 'close', callback).
     * @param {string|function} [onErrorAction] Action if an error occurs.
     * Prepares field data before saving:
     * - Combines `sys_AllowRegistration` and `sys_AllowUserImportAtLogin` into the `sys_AllowRegistration` bitmask.
     * - Validates and formats `sys_SyncDefsWithDB` (Zotero key).
     * - Stringifies `sys_ExternalReferenceLookups` from `HAPI4.sysinfo['service_config']`.
     * Calls the parent `_saveEditAndClose` to perform the actual save operation.
     * Removes 'mouseleave' handler if not in dialog mode.
     */
    _saveEditAndClose: function( fields, afterAction, onErrorAction ){

        let that = this;

        if(!this.options.isdialog){
            let fele = this.element.find('.ent_wrapper:first');
            $(fele).off("mouseleave");
        }

        if(!fields){
            fields = this._getValidatedValues();
        }

        if(!window.hWin.HAPI4.has_access(2)){ // reset value, just in case
            that._cachedRecordset.each2((i, values) => { fields['sys_URLCheckFlag'] = values['sys_URLCheckFlag'] });
        }

        if(Object.hasOwn(fields, 'sys_AllowUserImportAtLogin')){
            let allow_reg = Object.hasOwn(fields, 'sys_AllowRegistration') ? fields['sys_AllowRegistration'] : 0;
            fields['sys_AllowRegistration'] = allow_reg | fields['sys_AllowUserImportAtLogin'];
            
            delete fields['sys_AllowUserImportAtLogin'];
        }

        if(!window.hWin.HEURIST4.util.isempty(fields['sys_SyncDefsWithDB'])){
            
            let z_key = fields['sys_SyncDefsWithDB'].split(',');

            if(z_key.length != 4){

                let btn = {};
                btn[window.hWin.HR('OK')] = function(){
                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                    $dlg.dialog('close');

                    if(!that.options.isdialog){
                        let fele = this.element.find('.ent_wrapper:first');
                        $(fele).on("mouseleave", function(){ that.defaultBeforeClose(); });
                    }
                };

                window.hWin.HEURIST4.msg.showMsgDlg('Zotero web library key(s) requires 4 fields as specified in the help text.<br>'
                        + 'Either UserID or GroupID needs to be blank (represented by ,,)', btn
                        , {title:'Invalid Zotero Web Library Key', ok:'OK'});

                return;
            }
        }
        
        let lookup_external_service = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);
        if(lookup_external_service){ // Valid value
            fields['sys_ExternalReferenceLookups'] = JSON.stringify(lookup_external_service);
        }else{ // Invalid value / None
            fields['sys_ExternalReferenceLookups'] = JSON.stringify({});
        }

        this._super(fields, afterAction, onErrorAction);
    },	
    
    _afterSaveEventHandler: function( recID, fields ){
        this._super( recID, fields );
        
        let that = this;
        
        //reload local sysinfo
        window.hWin.HAPI4.SystemMgr.sys_info(function(){
            that.closeDialog(true); //force to avoid warning    
            
            //close populate section
            $('.ui-menu6').slidersMenu('closeContainer', 'populate');

            
        });
        
        
        
        
    },
    
});
