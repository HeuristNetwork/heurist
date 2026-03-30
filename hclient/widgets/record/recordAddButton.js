/**
* @file recordAddButton.js
* @brief Provides a button widget for adding new records, typically used within CMS contexts.
* @fileOverview This file defines the `recordAddButton` widget. It creates a simple button that, when
* clicked, opens the record editing interface to add a new record. The widget can be configured with
* default parameters for the new record, such as record type, owner, visibility, and tags. This is
* often used to embed 'Add Record' functionality directly into specific parts of a Heurist-driven
* website or application.
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @class recordAddButton
 * @memberof Widgets.Records
 * @description jQuery widget that creates a button to add a new record.
 * This widget is typically used to embed a button in a page (e.g., CMS)
 * that allows users to quickly add a new Heurist record.
 * Default parameters for the new record can be pre-configured.
 *
 * @param {object} options - Configuration options for the widget.
 */
$.widget( "heurist.recordAddButton",{

    /**
     * @memberof Widgets.Records.recordAddButton
     * @type {object}
     * @property {?string} add_record_label - The label for the button. If not provided, it defaults to "Add [Record Type Name]" or "Add Record".
     * @property {number} [RecTypeID=0] - The ID of the record type for the new record.
     * @property {number} [OwnerUGrpID=0] - The ID of the user or group to own the new record. Defaults to current user if 0.
     * @property {?string} NonOwnerVisibility - The visibility setting for non-owners (e.g., 'public', 'viewable', 'hidden').
     * @property {?string} RecTags - Comma-separated string of tags to be applied to the new record.
     * @property {?string} NonOwnerVisibilityGroups - Comma-separated string of group IDs for 'hidden' visibility if `NonOwnerVisibility` is 'hidden'.
     * @property {?string} search_realm - An identifier for a search realm. If provided, an event `ON_CUSTOM_EVENT` with `restartSearch:true`
     *                                    and this `search_realm` is triggered after a record is successfully added and selected,
     *                                    presumably to refresh a search result list related to this button.
     */
    options: {
        add_record_label: null,
        
        RecTypeID: 0,
        OwnerUGrpID: 0,
        NonOwnerVisibility: null,
        RecTags: null,
        NonOwnerVisibilityGroups: null
    },
    
    /**
     * @function _init
     * @memberof Widgets.Records.recordAddButton
     * @private
     * @description Initializes the recordAddButton widget.
     * Creates a button element, sets its label based on options or record type,
     * and attaches a click event handler to open the record edit dialog for a new record
     * with the pre-configured options. It also sets up a trigger to refresh a search
     * if `options.search_realm` is defined.
     */
    _init:function(){

        if(!this._verifyScripts()){ // ensure neceesary editing scripts are loaded
            return;
        }

        let ele = $('<button>').appendTo(this.element);
        
        let c2 = this.element.parent().attr('style');
        ele.attr('style',c2);
        this.element.parent().css({border:'none',background:'none'});
        
        if(!this.options.add_record_label){

            if(this.options.RecTypeID>0 && $Db.rty(this.options.RecTypeID,'rty_Name')){
                this.options.add_record_label = 'Add '+window.hWin.HEURIST4.util.htmlEscape($Db.rty(this.options.RecTypeID,'rty_Name'));
            }else{
                this.options.add_record_label = 'Add Record';
            }
        }       
        ele.button({label:this.options.add_record_label});
        
        let that = this;
        
        this._on(ele, {
            click: function(e){
                window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:this.options,
                
                    selectOnSave:true,
                    onselect:function(event, data){
                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {restartSearch:true,
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                            
                        }                
                    }
                });
            }
        });
    },

    /**
     * @function _verifyScripts
     * @memberof Widgets.Records.recordAddButton
     * @private
     * @description Loads the necessary scripts for editing records.
     * Checks and loads any missing scripts from the following:
     *  jquery.fileupload.js, wellknown.js, ui-tabs-paging.js, evol.colorpicker.js,
     *  editing2.js, editing_inputs.js, editing_exts.js,
     *  temporalObjectLibrary.js and utils_geo.js
     */
    _verifyScripts: function(){

        let scripts = [];
        let editingBase = `${window.hWin.HAPI4.baseURL}hclient/widgets/editing/`;
        let coreBase = `${window.hWin.HAPI4.baseURL}hclient/core/`;
        let externalBase = `${window.hWin.HAPI4.baseURL}external/`;

        // External
        if(typeof $.blueimp?.fileupload === 'undefined'){ // File upload handler
            scripts.push(`${externalBase}jquery-file-upload/js/jquery.fileupload.js`);
        }
        if(typeof parseWKT === 'undefined'){ // WKT parser
            scripts.push(`${externalBase}js/wellknown.js`);
        }
        if(typeof $.ui.tabs.prototype.paging === 'undefined'){ // UI Tabs paging
            scripts.push(`${externalBase}jquery.widgets/ui.tabs.paging.js`);
        }
        if(typeof $.evol?.colorpicker === 'undefined'){ // Colorpicker
            scripts.push(`${externalBase}jquery.widgets/evol.colorpicker.js`);
            $.getStyles(`${externalBase}jquery.widgets/evol.colorpicker.css`);
        }

        // Core + Utils
        if(typeof TDate === 'undefined'){ // Date values
            scripts.push(`${coreBase}temporalObjectLibrary.js`);
        }
        if(typeof window.hWin.HEURIST4.geo === 'undefined'){ // Geospatial values
            scripts.push(`${coreBase}utils_geo.js`);
        }

        // Editing
        if(typeof HEditing === 'undefined'){
            scripts.push(`${editingBase}editing2.js`);
        }
        if(typeof $.heurist.editing_input === 'undefined'){
            scripts.push(`${editingBase}editing_input.js`);
        }
        if(typeof openSearchMenu === 'undefined'){
            scripts.push(`${editingBase}editing_exts.js`);
        }

        if(scripts.length > 0){
            $.getMultiScripts(scripts)
                .then(() => this._init())
                .catch((e) => window.hWin.HEURIST4.msg.showMsg_ScriptFail());
        }

        return scripts.length === 0;
    }
});
