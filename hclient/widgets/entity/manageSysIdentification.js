/**
* @file manageSysIdentification.js
* @brief Manages System Identification and Access Control settings.
* @fileOverview Provides a UI for administrators to configure system identification, authentication methods (e.g., LDAP, Shibboleth), IP whitelisting/blacklisting, and other access control mechanisms.
* @project     Heurist academic knowledge management system
*
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
 * @augments $.heurist.manageEntity
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

        if(!this.options.isdialog && $('.ui-menu6').length > 0){
            $('.ui-menu6').slidersMenu('manageSwitchHandler', 'design', this.options.entity.entityName, () => this.defaultBeforeClose());
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
        $ele.editing_input('setValue', [1 & status], true);

        $ele = this._editing.getFieldByName('sys_AllowUserImportAtLogin');
        $ele.editing_input('setValue', [2 & status], true);

        this._setupLanguages();
        this._setupRegisteredMetadata();
    },

    /**
     * @brief If this database is registered and the Heurist Reference Index metadata has been
     * synced locally (DBMetadata.xml, via the nightly admin/utilities/downloadDBMetadata.php cron),
     * replaces the editable Display name / Rights / Owner / Description fields with a read-only,
     * human-readable listing of that synced metadata, plus a link to edit it on the Reference Index
     * and a note that local changes take a day to appear.
     * Falls back to the normal editable fields if not registered or not yet synced.
     * @memberof heurist.manageSysIdentification
     */
    _setupRegisteredMetadata: function(){

        const regID = window.hWin.HAPI4.sysinfo['db_registeredid'];
        if(!window.hWin.HEURIST4.util.isPositiveInt(regID)){
            return; // not registered - editable fields (sys_dbName/Rights/Owner/Description) stand as-is
        }

        let that = this;

        window.hWin.HAPI4.callserver('usr_info', {a: 'get_db_metadata'}, function(response){

            if(!response || !response.exists || !window.hWin.HEURIST4.util.isArrayNotEmpty(response.fields)){
                return; // not yet synced (first sync happens the night after registration) - leave editable fields
            }

            const fieldsToReplace = ['sys_dbName', 'sys_dbRights', 'sys_dbOwner', 'sys_dbDescription'];
            let $anchor = null;

            fieldsToReplace.forEach(function(fname){
                let $fld = that._editing.getFieldByName(fname);
                if($fld && $fld.length>0){
                    if(!$anchor){ $anchor = $fld.closest('tr, .detailfield'); }
                    $fld.closest('tr, .detailfield').hide();
                }
            });

            const editURL = window.hWin.HAPI4.sysinfo['referenceServerURL']
                +'?fmt=edit&recID='+regID
                +'&db='+window.hWin.HAPI4.sysinfo.referenceServerIndexDatabase;

            let html = '<div class="synced-metadata" style="padding:0.5em 1em;border:1px solid #ddd;border-radius:4px;margin:0.5em 0;">'
                + '<div style="font-weight:bold;margin-bottom:0.5em;">'
                + window.hWin.HR('Registered database metadata (Heurist Reference Index)') + '</div>';

            response.fields.forEach(function(f){
                html += '<div style="margin-bottom:0.4em;"><span style="font-weight:bold;">'
                    + window.hWin.HEURIST4.util.htmlEscape(f.label) + ':</span> '
                    + window.hWin.HEURIST4.util.htmlEscape(f.value) + '</div>';
            });

            html += '<div style="margin-top:0.5em;">'
                + '<a class="dbLink" target="_blank" href="' + editURL + '">'
                + window.hWin.HR('Edit this metadata on the Heurist Reference Index') + '</a></div>';

            html += '<div style="margin-top:0.3em;font-style:italic;color:#777;">'
                + window.hWin.HR('Changes made in the Heurist reference index will appear here the next day')
                + '</div>';

            if(response.modified){
                html += '<div style="margin-top:0.3em;font-size:0.85em;color:#999;">'
                    + window.hWin.HR('Last synced') + ': ' + response.modified + '</div>';
            }

            html += '</div>';

            if($anchor && $anchor.length>0){
                $(html).insertBefore($anchor.first());
            }else{
                that.editForm.prepend(html);
            }
        });
    },

    /**
     * @brief Prepares the database languages field after the edit form has is initialised
     * @memberof heurist.manageSysIdentification
     */
    _setupLanguages: async function(){

        let $languages = this._editing.getFieldByName('sys_CommonLanguages');
        let commonLanguages = window.hWin.HAPI4.sysinfo.common_languages;
        commonLanguages = Object.keys(commonLanguages).join(',');
        let allLanguages = [];

        if(!window.hWin.HAPI4.allLanguages){
            // Retrieve complete list of languages
            try{

                let response = await fetch(`${window.hWin.HAPI4.baseURL}/hclient/assets/language-codes-active-list.json`);

                if(!response.ok){
                    throw new Error(`Failed to retrieve language codes from assets directory, status: ${response.status}`);
                }

                allLanguages = await response.json();

                window.hWin.HAPI4.allLanguages = allLanguages;

            }catch(error){
                console.error(error.message);
            }
        }else{
            allLanguages = window.hWin.HAPI4.allLanguages;
        }

        let languageOpts = [];
        $languages.editing_input('setValue', commonLanguages, true);
        if(allLanguages.length === 0 && window.hWin.HEURIST4.util.isempty(commonLanguages)){
            return;
        }else if(allLanguages.length > 0){

            for(let idx = 0; idx < allLanguages.length; idx ++){

                let lang = allLanguages[idx];
                const ar3 = lang.a3.toUpperCase();

                if(!Object.hasOwn(window.hWin.HAPI4.sysinfo.common_languages, ar3)){
                    languageOpts.push({key: ar3, title: lang.name, idx: idx});
                }
            }
        }

        const editLink = '<span style="text-decoration: underline; padding-left: 10px;"><span class="ui-icon ui-icon-pencil" style="padding-right: 5px;"></span>Edit list</span>';

        $languages.find('input').prop('readonly', true);
        $languages.find('span.btn_input_clear').replaceWith(editLink);

        $languages.attr('title', window.hWin.HR('Click to edit field')).css('cursor', 'pointer');
        this._on($languages, {
            click: () => {

                let $dlg;
                let records = {};
                let fields = ['AR3', 'name'];
                let order = [];
                for(const ar3 in window.hWin.HAPI4.sysinfo.common_languages){

                    if(!Object.hasOwn(window.hWin.HAPI4.sysinfo.common_languages, ar3)){
                        continue;
                    }

                    records[ar3] = [ar3, window.hWin.HAPI4.sysinfo.common_languages[ar3]['name']];
                    order.push(ar3);
                }
                let recordset = new HRecordSet({
                    count: order.length,
                    offset: 0,
                    fields: fields,
                    rectypes: [1],
                    records: records,
                    order: order
                });

                let content = `<div>
                    <div style="width: 35em;">
                        This is the list and order of languages that will appear when adding 
                        translations for record data and also the allowed languages for any CMS 
                        website
                    </div>
                    <div style='padding: 15px 10px 10px;'>
                        <select id='sel_AddLanguage' style='max-width: 20em; vertical-align: middle; margin-right: 10px;'></select>
                        <button id='btn_AddLanguage' style='padding: .3em; font-size: .5em; margin-top: 0px;' title='Add selected language to list'>Add language</button>
                    </div>
                    <div style='height: 55em; width: 30em;'>
                        <div id='rl_Languages' class='ent_content_full' style='top: 9em;'></div>
                    </div>
                </div>`;

                let btn = {};
                btn[window.hWin.HR('Save')] = () => {

                    // Update database settings with new list of languages
                    let recset = $dlg.find('#rl_Languages').resultList('getRecordSet');

                    let request = {
                        entity: this._entityName,
                        a: 'batch',
                        languages: recset.getOrder()
                    };

                    window.hWin.HEURIST4.msg.bringCoverallToFront();

                    window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                        window.hWin.HEURIST4.msg.sendCoverallToBack();

                        if(response.status !== window.hWin.ResponseStatus.OK){
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            return;
                        }

                        $languages.editing_input('setValue', Object.keys(response.data).join(','), false);
                        window.hWin.HAPI4.sysinfo.common_languages = response.data;

                        $dlg.dialog('close');
                    });
                };

                let labels = {title: 'Manage database languages', ok: window.hWin.HR('Save')};

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btn, labels, {default_palette_class: 'ui-heurist-design', dialogId: 'database-languages'});

                // Initialise Languages result list
                $dlg.find('#rl_Languages').resultList({
                    eventbased: false,
                    multiselect: false,
                    select_mode: 'none',
                    view_mode: 'list',
                    show_viewmode: false,
                    pagesize: 100,
                    entityName: 'Languages',
                    empty_remark: '<div style="padding:1em 0 1em 0">No languages selected</div>',
                    show_toolbar:false,
                    sortable: true,
                    renderer: (recset, record) => {

                        const recID = recset.fld(record, 'AR3');
                        const label = recset.fld(record, 'name');

                        let btn = this._defineActionButton(
                            {key: 'delete', label: 'Remove language', title: '', icon: 'ui-icon-delete', class: 'rec_actions_button'}, null, 'icon_text', 'display: inline-block; cursor: pointer;'
                        );

                        const label_css = 'display: inline-block; width: 35em; max-width: 35em;';

                        return `<div class="recordDiv" recid="${recID}"><div title='${label}' class='truncate' style='${label_css}'>${label}</div>${btn}</div>`;
                    },
                    sortable_opts: {
                        axis: 'y'
                    }
                });

                // Add handler for 'Remove' buttons
                this._on('#rl_Languages', {
                    resultlistonpagerender: () => {
                        this._on($dlg.find('#rl_Languages [data-key="delete"]'), {
                            click: (event) => {

                                const ar3 = $(event.target).closest('[recid]').attr('recid');

                                let recset = $dlg.find('#rl_Languages').resultList('getRecordSet');
                                recset.removeRecord(ar3);
                                $dlg.find('#rl_Languages').resultList('updateResultSet', recset);
                            }
                        });
                    }
                });

                $dlg.find('#rl_Languages').resultList('updateResultSet', recordset);

                window.hWin.HEURIST4.ui.fillSelector($dlg.find('#sel_AddLanguage').get(0), languageOpts);

                // Add handling for adding a new language to list
                this._on($dlg.find('#btn_AddLanguage').button({icon: 'ui-icon-plus'}), {
                    click: () => {

                        const ar3 = $dlg.find('#sel_AddLanguage').val();
                        const idx = languageOpts.findIndex((lang) => lang.key === ar3);

                        if(idx > 0){

                            let language = languageOpts[idx];
                            language = allLanguages[language.idx];

                            let recset = $dlg.find('#rl_Languages').resultList('getRecordSet');
                            recset.addRecord(ar3, [ar3, language['name']]);
                            $dlg.find('#rl_Languages').resultList('updateResultSet', recset);
                        }
                    }
                });
            }
        });
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
        
        let lookupServices = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);
        if(lookupServices){ // Valid value
            fields['sys_ExternalReferenceLookups'] = JSON.stringify(lookupServices);
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

    closeDialog: function(){
        this._super();
        if(!this.options.isdialog && $('.ui-menu6').length > 0){
            $('.ui-menu6').slidersMenu('manageSwitchHandler', 'remove', this.options.entity.entityName);
        }
    }
    
});
