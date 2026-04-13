/**
* @file recordAdd.js
* @brief Provides a widget for adding new records, with options for setting default parameters like record type, ownership, and access.
* @fileOverview This file defines the `recordAdd` widget. It allows users to add new records to the
* Heurist system. The widget can operate in two modes: a simple list for selecting a record type to
* add, or an expanded view (based on `recordAccess`) to pre-define parameters like ownership, access
* rights, and tags for the new record. It also allows saving these parameters as user preferences for
* future record additions.
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
 * @class recordAdd
 * @augments {recordAction}
 * @memberof Widgets.Records
 * 
 * @description jQuery widget for adding new records.
 * This widget provides UI for selecting a record type and optionally setting
 * default ownership, access permissions, and tags for the new record.
 * It can display as a simple list of record types or an expanded dialog
 * for more detailed configuration.
 *
 * @param {object} options - Configuration options for the widget.
 */
$.widget( "heurist.recordAdd", $.heurist.recordAccess, {

    /**
     * @memberof Widgets.Records.recordAdd
     * @type {object}
     * @property {boolean} [is_h6style=false] - Whether to use H6 styling (custom layout).
     * @property {number} [width=520] - The width of the dialog in expanded mode.
     * @property {number} [height=800] - The height of the dialog in expanded mode.
     * @property {string} [title='Add Record'] - Title of the dialog.
     * @property {object} [currentRecordset={}] - Stub for recordset compatibility.
     * @property {number} [currentRecType=0] - The ID of the currently selected record type.
     * @property {?string} currentRecTags - Comma-separated string of tags for the new record.
     * @property {string} [scope_types='none'] - Scope types, usually 'none'.
     * @property {boolean} [isExpanded=false] - If true, shows expanded dialog; else, shows list.
     * @property {boolean} [allowExpanded=true] - Whether expanded view is allowed.
     * @property {boolean} [get_params_only=false] - If true, only gathers parameters.
     * @property {number} [RecTypeID] - Initial Record Type ID.
     * @property {number} [OwnerUGrpID] - Initial Owner User Group ID.
     * @property {string} [NonOwnerVisibility] - Initial Non-Owner Visibility.
     * @property {string} [RecTags] - Initial Record Tags.
     * @property {string} [NonOwnerVisibilityGroups] - Initial Non-Owner Visibility Groups.
     */
    options: {
        is_h6style: false,
        width: 520,
        height: 800,
        title:  'Add Record', //'Record addition settings'
        
        currentRecordset: {},  //stub
        currentRecType: 0,
        currentRecTags: null,
        scope_types: 'none',
        currentDefaultValues: {},
        
        isExpanded: false,  //false - show list, true - show preferences dialog
        
        allowExpanded: true,
        get_params_only: false
    },
    
    /**
     * @member {?jQuery} rectype_list
     * @memberof Widgets.Records.recordAdd
     * @description jQuery object for the `<ul>` element that lists record types when `is_h6style` is true and not expanded.
     */
    rectype_list:null,
    /**
     * @member {?jQuery} _toolbar
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description jQuery object for the toolbar containing action buttons in `is_h6style`.
     */
    _toolbar: null,
    /**
     * @member {?jQuery} _innerTitle
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description jQuery object for the title bar div in `is_h6style`.
     */
    _innerTitle: null,
    /**
     * @member {?jQuery} closeBtn
     * @memberof Widgets.Records.recordAdd
     * @description jQuery object for the close button in `is_h6style`.
     */
    closeBtn: null,
    /**
     * @member {?jQuery} expandBtn
     * @memberof Widgets.Records.recordAdd
     * @description jQuery object for the expand/settings button in `is_h6style`.
     */
    expandBtn: null,

    /**
     * @function _initControls
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description Initializes the controls for the recordAdd widget.
     * Sets up UI elements based on options (e.g., `is_h6style`, `get_params_only`).
     * Populates record type selector, loads user preferences for default add parameters,
     * and sets up event handlers for various UI interactions.
     * @returns {boolean|undefined} Returns `false` if current user is not available, otherwise true.
     */
    _initControls:function(){
        
        if(!window.hWin.HAPI4.currentUser){
            return;
        }

        if(this.options.RecTypeID>0){

            this.options.currentRecType =  this.options.RecTypeID;
            this.options.currentOwner = this.options.OwnerUGrpID;
            this.options.currentAccess = this.options.NonOwnerVisibility;
            this.options.currentRecTags = this.options.RecTags;
            this.options.currentAccessGroups = this.options.NonOwnerVisibilityGroups;
            this.options.currentDefaultValues = this.options.defaultValues;

        }else if(this.options.currentRecType == 0){

            //take from current user preferences
            let add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
            if(!Array.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                add_rec_prefs = [0, 0, 'viewable', '', '', {}]; // rt, owner, access, tags, visibility, default values  (default to Everyone)
            }

            if(add_rec_prefs.length < 5){ // visibility groups
                add_rec_prefs.push('');
            }
            if(add_rec_prefs.length < 6){ // default value groups
                add_rec_prefs.push({});
            }

            if(!this.options.get_params_only){
                this.options.currentRecType =  add_rec_prefs[0];
            }

            this.options.currentOwner = add_rec_prefs[1];
            this.options.currentAccess = add_rec_prefs[2];
            this.options.currentRecTags = add_rec_prefs[3];
            this.options.currentAccessGroups = add_rec_prefs[4];
            this.options.currentDefaultValues = add_rec_prefs[5];
        }

        let $dlg = this.element.children('fieldset');

        if(this.options.is_h6style){
            //add title 
            
            $dlg.css({top:'36px',bottom:'2px','overflow-y':'auto',position:'absolute',width:'auto', margin: '0px','font-size':'0.9em'}).hide();

            //titlebar            
            this._innerTitle = $('<div class="ui-heurist-header" style="top:0px;">'+this.options.title+'</div>')
            .insertBefore($dlg); // menu-text
            //'<span class="ui-icon ui-icon-gear" style="cursor:pointer;float:right;margin:0px 6px">Define parameters</span>                

            this.closeBtn = $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:window.hWin.HR('Close')}) 
            .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
            .addClass('ui-fade-color')
            .insertBefore($dlg);
            this._on(this.closeBtn, {click:function(){
                this.closeDialog();
            }});


            //toolbar for control buttons                    
            this._toolbar = $('<div><div class="ui-dialog-buttonset" style="text-align:right">'
                +'<button id="btnAddRecord"/>'
                +'<button id="btnAddRecordInNewWin"/>'
                +'<button id="btnSavePreferences"/>'
                +'</div></div>')
            .addClass('ent_footer ui-heurist-header')
            .hide()    
            .css({'height':'36px','padding':'4px 20px 0px'}).insertAfter($dlg);


            this._on(this._$('#btnAddRecord').button({label: window.hWin.HR('Add Record').toUpperCase() })
                .addClass('ui-button-action')
                .show(), {click:this.doAction});
            this._on(this._$('#btnAddRecordInNewWin').button({icon:'ui-icon-extlink', 
                label:window.hWin.HR('Add Record in New Window'), showLabel:false })
                .css({margin:'0 24px 0 4px'}) //background:'revert', 
                .show(), {click:this.doAction});
            this._on(this._$('#btnSavePreferences').button({label: window.hWin.HR('Save Settings').toUpperCase() })
                .show(), {click:this.doAction});


            this.expandBtn = $('<button>').button({icon:'ui-icon-gear',label: window.hWin.HR('Permission settings')})
            .addClass('ui-heurist-btn-header1')
            .css({position:'absolute',top:'43px',left:6}) 
            .insertBefore($dlg);

            this._on(this.expandBtn, {click: function(e){ 
                this.doExpand( this.rectype_list.is(':visible') );
            }});

            //list of rectypes
            this.rectype_list = $('<ul class="heurist-selectmenu" '
                +' style="position:absolute;top:56px;left:0;right:0;bottom:1px;padding:0px;'
                +'font-size:smaller;overflow-y:auto;list-style-type:none"></ul>')
            .insertBefore($dlg);


        }

        //add and init record type selector
        $('<div id="div_sel_rectype" style="padding: 0.2em;" class="input">'
            + '<div class="header_narrow" style="padding: 0px 16px 0px 0px;">'
            + window.hWin.HR('Type of record to add') +':</div>'
            + '<select id="sel_recordtype" style="width:40ex;max-width:30em"></select>'

            //+'<div id="btnAddRecord" style="font-size:0.9em;display:none;margin:0 30px"></div>'
            //+'<div id="btnAddRecordInNewWin" style="font-size:0.9em;display:none;"></div>'
            +'</div>').prependTo( $dlg ); //<hr style="margin:5px"/>

        $('<div class="heurist-helper3" style="padding:10px 0;display:block">'
        + window.hWin.HR('add_record_settings_hint') +'</div>')
        .prependTo( $dlg );


        this._fillSelectRecordTypes( this.options.currentRecType );

        if(this.options.get_params_only===true){
           
            this._$('#btnAddRecordInNewWin').hide();
        }
       
        if(this.options.allowExpanded){
            
            this._$('#div_more_options').show();
            this._on(this._$('#btn_more_options'),{click:function(){
                this._$('#div_sel_tags').css('display','block');
                this._$('#div_add_link').show();
                this._$('#div_more_options').hide();
                this.element.parent().height('auto');

                this._adjustHeight();
            }});
            
            window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                isdialog : false,
                container: $('#div_sel_tags2'),
                select_mode:'select_multi', 
                layout_mode: '<div class="recordList"/>',
                list_mode: 'compact', //special option for tags
                selection_ids: [], //already selected tags
                select_return_mode:'recordset', //ids by default
                onselect:function(event, data){
                    if(data && data.selection){
                        that.options.currentRecTags = data.astext;
                        that._onRecordScopeChange();
                    }
                }
            });


            let that = this;
            $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
            +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                function(e, data) { 
                    if(e.type==window.hWin.HAPI4.Event.ON_CREDENTIALS 
                        || !data || data.type=='rtg' || data.type=='rty')
                    {
                        that._fillSelectRecordTypes(that.options.currentRecType);    
                    }
            });

            this._setupDefaultValue();
        }

        //let res = this._super();
        this.fillAccessControls()
        this._$('#sel_record_scope').parent().hide();
        
        if(this.options.is_h6style){
            this.doExpand( this.options.isExpanded );
        }else{
            this._super(); // to use a normal dialog
        }
        
        return true;
    },

    _setupDefaultValue: function(){

        let $defFields = this._$('#div_add_def_values');
        let $btnAddDefField = this._$('#btn_add_def_value').button({icon: 'ui-icon-plus'});
        this._on($btnAddDefField, {
            click: () => this._handleNewDefaultValue('')
        });

        const rtyID = this.options.currentRecType;
        if(!window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
            return;
        }

        const widthStyle = 'width: 10em; max-width: 10em; display: inline-block;';
        for(let dtyID in this.options.currentDefaultValues){
            
            const rstField = $Db.rst(rtyID, dtyID);
            let values = this.options.currentDefaultValues[dtyID];
            if(!rstField || values.length === 0){
                continue;
            }

            const listed = `"${values.join('","')}"`;
            $('<div>', {
                'data-id': dtyID,
                title: `${rstField['rst_DisplayName']} => ${listed}`,
                html: `<span class="truncate" style="${widthStyle}">${rstField['rst_DisplayName']}</span>
                <span style="width: 3em; display: inline-block;">&rArr;</span>
                <span class="fieldValues truncate" style="${widthStyle}">${listed}</span>
                <span class="ui-icon ui-icon-pencil" class="padding: 0.2em 0.5em;" title="Edit the default values for ${rstField['rst_DisplayName']}"></span>
                <span class="ui-icon ui-icon-close" class="padding: 0.2em 0.5em;" title="Remove all default values for ${rstField['rst_DisplayName']}"></span>`
            }).appendTo($defFields);
        }

        this._on($defFields.find('.ui-icon-pencil').button(), {
            click: (event) => {
                let dtyID = $(event.target).parent().attr('data-id');
                if(!window.hWin.HEURIST4.util.isPositiveInt(dtyID)){
                    return;
                }
                this._handleNewDefaultValue(dtyID);
            }
        });
        this._on($defFields.find('.ui-icon-close').button(), {
            click: (event) => {
                let $parent = $(event.target).parent();
                let dtyID = $parent.attr('data-id');
                if(!window.hWin.HEURIST4.util.isPositiveInt(dtyID) || !Object.hasOwn(this.options.currentDefaultValues, dtyID)){
                    return;
                }
                delete this.options.currentDefaultValues[dtyID];
                $parent.remove();
            }
        });
    },

    _updateDefaultValue: function(dtyID){

        let $divValue = this._$(`#div_add_def_values`);
        const widthStyle = 'width: 10em; max-width: 10em; display: inline-block;';

        const rtyID = this.options.currentRecType;
        if(!window.hWin.HEURIST4.util.isPositiveInt(dtyID) || !window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
            return;
        }

        const rstField = $Db.rst(rtyID, dtyID);
        let values = this.options.currentDefaultValues[dtyID];
        if(!values || values.length === 0){

            delete this.options.currentDefaultValues[dtyID];
            $divValue.find(`div[data-id="${dtyID}"]`).remove();

            this._onRecordScopeChange();

            return;
        }
        const listed = `"${values.join('","')}"`;

        let $div = $divValue.find(`div[data-id="${dtyID}"]`);
        if($div.length === 1){ // update existing field

            $div.attr('title', `${rstField['rst_DisplayName']} => ${listed}`);
            $div.find('.fieldValues').text(listed);
            return;
        }

        // add new field
        $div = $('<div>', {
            'data-id': dtyID,
            title: `${rstField['rst_DisplayName']} => ${listed}`,
            html: `<span class="truncate" style="${widthStyle}">${rstField['rst_DisplayName']}</span>
            <span style="width: 3em; display: inline-block;">&rArr;</span>
            <span class="fieldValues truncate" style="${widthStyle}">${listed}</span>
            <span class="ui-icon ui-icon-pencil" class="padding: 0.2em 0.5em;" title="Edit the default values for ${rstField['rst_DisplayName']}"></span>
            <span class="ui-icon ui-icon-close" class="padding: 0.2em 0.5em;" title="Remove all default values for ${rstField['rst_DisplayName']}"></span>`
        }).appendTo($divValue);

        this._on($div.find('.ui-icon-pencil').button(), {
            click: () => this._handleNewDefaultValue(dtyID)
        });
        this._on($div.find('.ui-icon-close').button(), {
            click: (event) => {
                delete this.options.currentDefaultValues[dtyID];
                $(event.target).parent().remove();
            }
        });

        this._onRecordScopeChange();
    },

    _handleNewDefaultValue: function(originalDTYID = ''){

        const rtyID = this.options.currentRecType;
        if(!window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
            window.hWin.HEURIST4.msg.showMsgFlash('Select a record type first...', 3000);
            return;
        }

        let $dlg;
        let content = `
        <div>
            <span style="display: inline-block; width: 6em; text-align: right; margin-right: 2em;">Record field:</span>
            <div id="dtyField" style="display: inline-block;"><select id="sel_dtyField"></select></div><br><br>
            <span style="display: inline-block; width: 8em; vertical-align: top;">Default value:</span>
            <div id="deffield_Value" style="display: inline-block;"></div>
        </div>`;

        let btns = {};
        let label = !window.hWin.HEURIST4.util.isPositiveInt(originalDTYID) ? 'Add new default values' : 'Update default values';
        btns[window.hWin.HR(label)] = () => {

            const dtyID = $dlg.find('#sel_dtyField').val();
            if(!window.hWin.HEURIST4.util.isPositiveInt(dtyID)){
                window.hWin.HEURIST4.msg.showMsgFlash('Select a record field...', 3000);
                return;
            }

            const $inputs = $dlg.find('#deffield_Value input, #deffield_Value select');

            let values = [];

            const type = $Db.dty(dtyID, 'dty_Type');
            $inputs.each((idx, input) => {

                let value = input.value;
                if(window.hWin.HEURIST4.util.isempty(value)){
                    return;
                }

                let tag = input.tagName.toLowerCase();
                if(type === 'resource' || type === 'file' || type === 'enum' && tag !== 'select'){
                    values = value.split(',').map((x) => +x);
                    return;
                }

                values.push(value);
            });

            if(values.length === 0){
                window.hWin.HEURIST4.msg.showMsgFlash('Define a default value to save...', 3000);
                return;
            }

            if(originalDTYID != dtyID){
                this.options.currentDefaultValues = [];
                this._updateDefaultValue(originalDTYID);
            }

            if(!Object.hasOwn(this.options.currentDefaultValues, dtyID)){
                this.options.currentDefaultValues[dtyID] = values;
            }else{
                this.options.currentDefaultValues[dtyID].push(...values);
            }

            this._updateDefaultValue(dtyID);

            $dlg.dialog('close');
        };
        btns[window.hWin.HR('Cancel')] = () => {
            $dlg.dialog('close');
        };

        let labels = {title: `Assign default values for ${$Db.rty(rtyID, 'rty_Name')}`};

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, labels, {
            default_palette_class: 'ui-heurist-explore',
            dialogId: 'add-default-value-for-field',
            width: '35em',
            position: {
                my: 'center',
                at: 'center',
                of: this._$('#div_fieldset')
            }
        });console.log(this._$('#div_fieldset'));

        let $selField = $dlg.find('#sel_dtyField');
        let $divValue = $dlg.find('#deffield_Value');

        window.hWin.HEURIST4.ui.createRectypeDetailSelect($selField.get(0), rtyID,
            ['freetext','blocktext','enum','date','float','year','integer','resource','file'], [{key: '', title: 'Select a field...'}], //,'geo'
            {
                useHtmlSelect: false,
                selectedValue: originalDTYID,
                eventHandlers: {
                    onSelectMenu: () => {

                        const dtyID = $selField.val();
                        if(!window.hWin.HEURIST4.util.isPositiveInt(dtyID)){
                            return;
                        }

                        this._handleDefaultValueField(dtyID, $divValue);
                    }
                }
            }
        );

        if(window.hWin.HEURIST4.util.isPositiveInt(originalDTYID)){
            this._handleDefaultValueField(originalDTYID, $divValue);
        }
    },

    _handleDefaultValueField: function(dtyID, container){

        const rtyID = this.options.currentRecType;
        if(!container instanceof jQuery || container.length === 0 || !window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
            return;
        }

        let multiselect = Number.parseInt($Db.rst(10, 19, 'rst_MaxValues')) !== 1;

        let handleTerm = (initialValue) => {

            let $input = null;
            let vocabID = $Db.dty(dtyID, 'dty_JsonTermIDTree');
            initialValue = window.hWin.HEURIST4.util.isPositiveInt(initialValue) ? initialValue : '';
            if(vocabID){

                let $select = $('<select>').appendTo(container);
                let options = {
                    vocab_id: vocabID, defaultTermID: initialValue, topOptions: [{key: '', title: 'Select a term...'}], supressTermCode: true, useHtmlSelect: false
                };
                window.hWin.HEURIST4.ui.createTermSelect($select.get(0), options);

                $input = $select;
            }else{
                $input = $('<input>').appendTo(container);
            }

            return $input;
        };

        let handleEntitySelector = (type, initialValue) => {

            const rtyIDs = $Db.dty(dtyID, 'dty_PtrTargetRectypeIDs');
            const vocabID = $Db.dty(dtyID, 'dty_JsonTermIDTree');
            let $input = $('<input>', {readonly: true, type: 'text', class: 'text ui-widget-content ui-corner-all', value: initialValue, style: 'cursor: pointer;'}).uniqueId().appendTo(container);
            this._on($input, {
                click: () => {

                    let popup_options = {
                        isdialog: true,
                        select_mode: multiselect ? 'select_multi' : 'select_single',
                        select_return_mode: 'ids',
                        edit_addrecordfirst: false,
                        selection_on_init: multiselect ? $input.val().join(',') : [],
                        onselect: (event, data) => {

                            if(!data){
                                return;
                            }

                            let recIDs = window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection) ? data.selection : [];
                            $input.val(recIDs.join(','));
                        }
                    };//popup_options

                    if(type === 'records'){
                        popup_options['rectype_set'] = rtyIDs;
                    }else if(type === 'defTerms'){
                        popup_options['initial_filter'] = vocabID;
                        popup_options['hide_searchForm'] = true;
                    }

                    window.hWin.HEURIST4.ui.showEntityDialog(type, popup_options);
                }
            });

            let flavourText = type === 'records' ? 'records' : 'files';
            flavourText = type === 'defTerms' ? 'terms' : flavourText;
            $('<span>', {text: `Click to edit the selected ${flavourText}`, class: 'heurist-helper2'}).appendTo(container);

            return $input;
        };

        const dtyType = $Db.dty(dtyID, 'dty_Type');

        let addInput = (initialValue) => {

            let $input = null;
            switch(dtyType){

                case 'float':
                case 'year':
                case 'integer':
                    $input = $('<input>', {type: 'number', step: '1', style: 'width: 15em; display: block;', value: initialValue}).uniqueId().appendTo(container);
                    break;

                case 'date':
                    $input = $('<input>', {type: 'date', style: 'display: block;', value: initialValue}).uniqueId().appendTo(container);
                    break;

                case 'enum':
                    if(multiselect){
                        $input = handleEntitySelector('defTerms', initialValue);
                    }else{
                        $input = handleTerm(initialValue);
                    }
                    break;

                case 'resource':
                    $input = handleEntitySelector('records', initialValue);
                    break;

                case 'file':
                    $input = handleEntitySelector('recUploadedFiles', initialValue);
                    break;

                default:
                    $input = $('<input>', {type: 'text', class: 'text ui-widget-content ui-corner-all', style: 'width: 20em; display: block;', value: initialValue}).uniqueId().appendTo(container);
                    break;
            }

            if(dtyType === 'enum' || dtyType === 'resource' || dtyType === 'file'){
                return;
            }

            if(container.find('input').length === 1 && multiselect){

                let $firstInput = container.find('input');
                let $repeater = $('<button>', {
                    title: 'Add new input field',
                    class: 'ui-icon ui-icon-circlesmall-plus',
                    style: 'cursor: pointer; background: transparent; border: none; font-size: 16px;'
                }).appendTo(container);

                this._on($repeater, {
                    click: () => {

                        let $inputs = container.find('input');
                        let allowNewInput = true;
                        $inputs.each((idx, input) => {
                            if(window.hWin.HEURIST4.util.isempty(input.value)){
                                allowNewInput = false;
                                return false;
                            }
                        });

                        if(allowNewInput){
                            addInput();
                        }
                    }
                });

                $repeater.position({
                    my: 'right-5 center',
                    at: 'left center',
                    of: $firstInput
                });
            }

            if(container.find('input').length > 1){

                let $clear = $('<span>', {
                    title: 'Remove input field',
                    class: 'ui-icon ui-icon-circlesmall-close',
                    style: 'cursor: pointer; font-size: 16px;'
                }).appendTo(container);
    
                this._on($clear, {
                    click: () => {
                        $input.remove();
                        $clear.remove();
                    }
                });
    
                $clear.position({
                    my: 'left+10 center',
                    at: 'right center',
                    of: $input
                });console.log($input, $clear, container);
            }
        };

        container.empty();

        if(dtyType === 'resource' || dtyType === 'file' || dtyType === 'enum'){
            addInput(this.options.currentDefaultValues[dtyID]);
            return;
        }

        if(!Object.hasOwn(this.options.currentDefaultValues, dtyID)){
            addInput();
            return;
        }

        let values = this.options.currentDefaultValues[dtyID];
        for(let idx = 0; idx < values.length; idx ++){
            addInput(values[idx]);
        }
    },
    
    /**
     * @function _destroy
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description Cleans up the widget. Unbinds event listeners for structure and credential changes.
     * Calls the parent widget's `_destroy` method.
     */
    _destroy: function() {
       
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                            +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS);
        //window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
        //window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_CREDENTIALS);        
        return this._super();
    },
    
    /**
     * @function _adjustHeight
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description Adjusts the height of the 'add link' text area based on available space in the dialog.
     * This is typically called when expanding options or when the dialog layout changes.
     */
    _adjustHeight:function(){
        let ele = this._$('#txt_add_link');
        let t1 = ele.offset().top;
        let ele2 = this._$('.ent_footer');
        if(ele2.length>0){
            let t2 = ele2.offset().top;
            if(t2>0){
                let h = Math.max(t2-t1-40,40);
                ele.height(h);                
            }
        }
    },
    
    /**
     * @function doExpand
     * @memberof Widgets.Records.recordAdd
     * @description Toggles the widget's view between a simple list of record types and an expanded
     * dialog for setting record creation preferences (ownership, access, tags).
     * This is primarily used when `options.is_h6style` is true.
     * @param {boolean} is_expand - If true, switches to the expanded preferences dialog.
     *                           If false, switches to the simple list of record types.
     */
    doExpand: function(is_expand){

        if(!this._toolbar) return;
        
        let $dlg = this.element.children('fieldset');
        
        if(is_expand){ //show preferences dialog
            
            this._toolbar.show();
            this.rectype_list.hide();
            this.expandBtn.hide();
            this.closeBtn.show();
            $dlg.css('bottom','40px').show(); //space to show button toolbar
            this.element.parent().width(500);
           
            this._innerTitle.text(window.hWin.HR('Record addition settings'));

        
            if(this.options.allowExpanded){
                this._$('#div_sel_tags').css('display','block');
                this._$('#div_def_values').css('display','block');
                this._$('#div_add_link').show();
                this._$('#div_more_options').hide();
                this.element.parent().height('auto');
                this._adjustHeight();
            }else{
                this._$('.add_record').hide();
                this.element.parent().height(450);
            }
            
            let add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
            if(Array.isArray(add_rec_prefs) && add_rec_prefs.length>0){
                let pref_rectype = add_rec_prefs[0];
                this._$('#sel_recordtype').val(pref_rectype).hSelect('refresh');
            }
        }else{
             //show record type list
            
            this._toolbar.hide();
            this.rectype_list.show();
            this.expandBtn.show();
            this.closeBtn.hide();
            $dlg.css('bottom','2px').hide();
            this.element.parent().width(200).height('auto');
           
            this._innerTitle.text(window.hWin.HR('Add Record'));
        }    
                
        
    },

    /**
     * @function _getActionButtons
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description Gets the action buttons for the dialog. Overrides the parent method
     * to change the text of the main action button based on `options.get_params_only`.
     * @returns {Array<object>} An array of button definition objects for the dialog.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR(this.options.get_params_only?'Get Parameters':'Add Record');
        return res;
    },    

    /**
     * @function getSelectedParameters
     * @memberof Widgets.Records.recordAdd
     * @description Retrieves and validates the selected parameters for adding a record,
     * including record type, ownership, and access settings.
     * Overrides the parent method to include selection of the record type.
     * @param {boolean} showWarning - If true, displays a warning message if the record type is not selected.
     * @returns {boolean} True if all required parameters (including record type) are selected, false otherwise.
     */
    getSelectedParameters: function( showWarning ){
        
        let rtSelect = this._$('#sel_recordtype');
        if(rtSelect.val()>0){
            if(this._super( showWarning )){
                this.options.currentRecType = rtSelect.val();
                return true;
            }
        }else if ( showWarning ) {
            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Select record type for record to be added'));            
        }
        return false;
    },
    
    /**
     * @function doAction
     * @memberof Widgets.Records.recordAdd
     * @description Performs the primary action of the widget based on the current settings and the button clicked.
     * If `options.get_params_only` is true, it gathers the parameters and sets them in `_context_on_close`.
     * Otherwise, it saves the selected parameters as user preferences and either opens the record edit
     * form for a new record or opens the new record form in a new window, depending on which button triggered the action.
     * It also handles the 'Save Settings' action.
     * @param {Event} [event] - The click event object, used to determine which button was pressed.
     */
    doAction: function(event){
        
        if (!this.getSelectedParameters(true))  return;
        
        let new_record_params = {
            'RecTypeID': this.options.currentRecType,
            'OwnerUGrpID': this.options.currentOwner,
            'NonOwnerVisibility': this.options.currentAccess,
            'NonOwnerVisibilityGroups':this.options.currentAccessGroups,
            'details': this.options.currentDefaultValues
        };
                
        if(this.options.get_params_only==true){
            //return values as context
            new_record_params.RecTags = this.options.currentRecTags;
            new_record_params.RecAddLink = this._onRecordScopeChange();
            
            this._context_on_close =  new_record_params;
        }else{
            
            let add_rec_prefs = [this.options.currentRecType, this.options.currentOwner, this.options.currentAccess, 
                        this.options.currentRecTags, this.options.currentAccessGroups, this.options.currentDefaultValues];

            window.hWin.HAPI4.save_pref('record-add-defaults', add_rec_prefs);

            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, 
                    {origin:'recordAdd', preferences:add_rec_prefs});

            let action = null;
            if(event){
                let ele = $(event.target);
                
                if(ele.is('button')){
                    action = ele.attr('id');
                }else{
                    action = ele.parent('button').attr('id');
                }
            }

            if(action=='btnAddRecordInNewWin'){
                let url = this._onRecordScopeChange();
               window.open(url, '_blank');
            }else if(action!='btnSavePreferences'){
                window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:new_record_params});    
            }
        }
               
        this.closeDialog(); 
    },

    /**
     * @function _fillSelectRecordTypes
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description Populates the record type selector dropdown (`#sel_recordtype`).
     * It also populates the `rectype_list` (ul element for `is_h6style`) if it exists.
     * Sets up event handlers for selection changes.
     * @param {number|string} value - The initial value (RecTypeID) to select in the dropdown.
     * @returns {jQuery} The jQuery object for the initialized hSelect record type selector.
     */
    _fillSelectRecordTypes: function( value ) {
        let rtSelect = this._$('#sel_recordtype');
        rtSelect.empty();
        
        let ele = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
        
        let that = this;
        
        ele.hSelect({change: function(event, data){
            let selval = data.item.value;
            rtSelect.val(selval);
            that._onRecordScopeChange();
        }});
        
        if(value>0){
            $(ele).val(value).hSelect("refresh"); 
        }
        
        if(this.rectype_list){
            
            this.rectype_list.empty();
            
            $.each(rtSelect.find('option'),function(i,item){
                if(i>0){
                    let sdis = $(item).attr('value')>0
                        ?' data-id="'+$(item).attr('value')+'" '
                        :(' class="ui-heurist-title truncate" style="font-size:1.0em;padding:6px;font-weight:bold"'); 
                            //+(i>1?'border-top:1px solid gray;margin-top:6px;':'')+'"');  // ui-state-disabled
                        
                    $('<li'+sdis
                        +'>'+$(item).text()+'</li>')
                        .appendTo(that.rectype_list);
                    
                }
            });
            
            this.rectype_list.find('li[data-id]').css({padding: '4px 4px 2px 20px', 
                cursor: 'pointer', border: 'none'        
            }).addClass('truncate');
    
            /*
            $.each(ele.hSelect('menuWidget').find('li'),function(i,item){
                if(i>0){
                    item = $(item).clone();
                    if(!item.hasClass('ui-state-disabled')){
                        item.css({cursor:'pointer'});  
                        //item.find('div').css({'border':'none !important'});  
                    } 
                    item.appendTo(that.rectype_list);
                }
            });
            */
            
//.not('.ui-state-disabled')
            this._on(this.rectype_list.find('li[data-id]'), {
              mouseover: function(e){ $(e.target).addClass('ui-state-active'); },
              mouseout: function(e){ $(e.target).removeClass('ui-state-active'); },
              click: function(e){ 
                  let rty_ID = $(e.target).attr('data-id'); 
                  
                  //$(e.target).text(); //send to parent

                  let prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                  if(!Array.isArray(prefs) || prefs.length<4){
                        prefs = [rty_ID, 0, 'viewable', '']; //default to everyone
                  }else{
                        prefs[0] = rty_ID; 
                  }
                  if(!this.options.get_params_only){
                      window.hWin.HAPI4.save_pref('record-add-defaults', prefs);
                      window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'recordAdd'});
                      
                      window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                        {new_record_params:{RecTypeID:rty_ID}});
                  }
                  this.closeDialog();
              }
            } );            

           
        }
        /*
        $.each(rtSelect.find('option'),function(i, item){
            item = $(item);
            $('<li data-id="'+item.attr('entity-id')+'" style="font-size:smaller;padding:6px">'
                +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                    + '" class="rt-icon" style="vertical-align:middle;background-image: url(&quot;'+item.attr('icon-url')+ '&quot;);"/>'
                //+'<img src="'+item.attr('icon-url')+'"/>'
                +'<span class="menu-text">'+item.text()+'</span>'
                +'<span style="float:right;">'+item.attr('rt-count')+'</span>'
               +'</li>').appendTo(cont);    
        });
        */
        return ele;
    },
    
    
    /**
     * @function _onRecordScopeChange
     * @memberof Widgets.Records.recordAdd
     * @private
     * @description Handles changes that affect the state of action buttons (e.g., Add Record, Add in New Window).
     * It enables/disables these buttons based on whether valid parameters are selected.
     * It also constructs and displays a URL for adding a record with the currently selected parameters
     * in the `#txt_add_link` text area. This method is an override.
     * @returns {string} The constructed URL for adding a new record with current parameters. Returns an empty string if parameters are invalid.
     */
    _onRecordScopeChange: function () 
    {
        let isdisabled = !this.getSelectedParameters( false );

        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this._$('#btnAddRecordInNewWin'), isdisabled);
        window.hWin.HEURIST4.util.setDisabled( this._$('#btnAddRecord'), isdisabled);
        
        let url = '';
        
        if(!isdisabled){
            
            url = window.hWin.HAPI4.baseURL_pro+'hclient/framecontent/recordEdit.php?db='+window.hWin.HAPI4.database
            +'&rec_rectype=' + this.options.currentRecType
            +'&rec_owner='+this.options.currentOwner
            +'&rec_visibility='+this.options.currentAccess;
            
            if( !window.hWin.HEURIST4.util.isempty( this.options.currentAccessGroups )){
                url = url + '&visgroups='+this.options.currentAccessGroups;    
            }
            
            if( !window.hWin.HEURIST4.util.isempty( this.options.currentRecTags)){
                if(Array.isArray(this.options.currentRecTags) && this.options.currentRecTags.length>0){
                    this.options.currentRecTags = this.options.currentRecTags.join(',');
                }
                //encodeuricomponent
                url = url + '&tag='+this.options.currentRecTags;    
            }

            if(Object.keys(this.options.currentDefaultValues).length > 0){
                url += `&f=${JSON.stringify(this.options.currentDefaultValues)}`;
            }
        }
        $('#txt_add_link').val(url);
        
        return url;
        
    }
    
  
});
