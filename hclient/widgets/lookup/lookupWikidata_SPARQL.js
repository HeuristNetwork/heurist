/**
* lookupWikidata_SPARQL.js - search WikiData via SPARQL queries
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget("heurist.lookupWikidata_SPARQL", $.heurist.lookupBase, {

    options: {
        htmlContent: 'lookupWikidata_SPARQL.html'
    },

    baseURL: 'https://query.wikidata.org/sparql?', // external url base
    serviceName: 'wikidata_SPARQL', // service name

    _fields: {},
    result_fields: [],
    url_field: '',

    _initControls: function(){

        let $select = this._$('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];
        let sel_options = {
            useHtmlSelect: false
        };
        this.$Hui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, sel_options);

        if(typeof EditorCodeMirror === 'function'){
            this.codeEditor = new EditorCodeMirror(this._$('textarea#sparql-input'), {mode: 'sparql', lineNumbers: true});
            this.codeEditor.showEditor();
        }

        this._on(this._$('textarea#label_mapping'), {
            click: () => { this._getFieldMapping(); }
        });

        return this._super();
    },

    _setupSettings: function(){

        let options = $.extend({}, {
            dump_record: false,
            dump_field: "rec_ScratchPad",
            SPARQL_field_map: {}
        }, this.options.mapping?.options);

        this._$('#dump_record').prop('checked', options.dump_record);

        let $dumpField = this._$('#rty_flds');
        if(options.dump_field == 'rec_ScratchPad'){
            this._$('input[name="dump_field"][value="rec_ScratchPad"]').prop('checked', true);
            $dumpField.val('');
        }else{
            $dumpField.val(options.dump_field);
        }

        this._fields = Array.isArray(options.SPARQL_field_map) ? options.SPARQL_field_map[0] : options.SPARQL_field_map;
        this._fields = this._fields ?? {};
        this._$('textarea#label_mapping').val(JSON.stringify(this._fields));
    },

    _saveExtraSettings: function(settings = false, close_dlg = false){

        if(settings !== null){

            const rec_dump_settings = this._getRecDumpSetting();

            settings = {
                SPARQL_field_map: this._fields,
                dump_record: rec_dump_settings[0],
                dump_field: rec_dump_settings[1]
            };
        }

        this._super(settings, close_dlg);
    },

    // @todo: remove from here and lookupBnF, move to lookupBase
    _getRecDumpSetting: function(){

        const get_recdump = this.element.find('input[name="dump_record"]').is(':checked');
        let recdump_fld = '';
        
        if(get_recdump){

            recdump_fld = this.element.find('input[name="dump_field"]:checked').val();
            if(recdump_fld === 'dty_ID'){
                recdump_fld = this.element.find('#rty_flds').val();
            }
        }

        return [ get_recdump, recdump_fld ];
    },

    _rendererResultList: function(recordset, record){

        let width = this._as_dialog.width() / this.result_fields.length;
        width = width - 15 < 50 ? 50 : width - 15;

        const rec_ID = recordset.fld(record, 'rec_ID');

        let row = `<div class="recordDiv" id="rd${rec_ID}" recid="${rec_ID}">`;

        for(const field of this.result_fields){

            let value = recordset.fld(record, field);

            if(/^\w{2,3}:/.exec(value)){
                let parts = value.split(':');
                parts.shift();
                value = parts.join(':');
            }

            row += `<div class="truncate" style="width: ${width}px; display: inline-block;" title="${value}">${value}</div>`;
        }

        return `${row}</div>`;
    },

    _getFieldMapping: function(closingAction = false){

        let fillDropdown = (selects) => {

            const rtyID = this.options.mapping.rty_ID;

            selects.each((idx, select) => {

                let value = select.getAttribute('data-value');

                this.$Hui.createRectypeDetailSelect(select, rtyID,
                    ['freetext', 'blocktext', 'term', 'resource', 'relmarker', 'geo'], null,
                    {useHtmlSelect: false, selectedValue: value}
                );
            });
        };

        let setupAutoFill = (inputs) => {

            this._on(inputs, {
                focus: (event) => {

                    let $input = $(event.target);

                    if(this.result_fields.length == 0 || $input.val() !== ''){
                        return;
                    }

                    let mapped_fields = [];
                    $dlg.find('input[type="text"]').each((idx, input) => {
                        if(this.$H.isempty(input.value)){
                            return;
                        }
                        mapped_fields.push(input.value);
                    });

                    let missing_fields = this.result_fields.filter((field) => !mapped_fields.includes(field));
                    let missing_list = missing_fields.reduce(
                        (list, field) => {
                            let row = `<div style="padding: 5px; width: 14em; max-width: 14em; cursor: pointer; border-bottom: 1px solid black;" class="suggestion truncate" title="${field}">${field}</div>`;
                            return `${list}${row}`
                        }, '');

                    let $suggestions = $('<div>', {
                        style: 'height: 10em; max-height: 10em; width: 16em; overflow-y: auto; position: absolute; background-color: #e0dfe0; padding: 5px; border-bottom: 1px solid black;',
                        class: 'suggestion_list',
                        html: `List of result fields not mapped:<br>${missing_list}`
                    });

                    $dlg.find('.suggestion_list').remove();
                    $dlg.append($suggestions);

                    $suggestions.position({
                        my: 'left top', at: 'left bottom', of: $input
                    });

                    this._on($suggestions.find('.suggestion'), {
                        click: (event) => { console.log($(event.target), $(event.target).text());
                            let title = $(event.target).text().trim();
                            $input.val(title);
                            $suggestions.remove();
                        }
                    });
                },
                blur: () => {
                    setTimeout(() => { if($dlg.length > 0){ $dlg.find('.suggestion_list').remove();} }, 1500)
                }
            });
        };

        let setupRemoveRow = (buttons) => {

            this._on(buttons, {
                click: (event) => {

                    let $btn = $(event.target);
                    if($btn.closest('.sparql_field_row').length == 0){
                        return;
                    }

                    $btn.closest('.sparql_field_row').remove();
                }
            })
        };

        let $dlg;
        let content = '<div style="padding: 10px;">';

        for(const fld_id in this._fields){
            content += `<div style="padding: 10px 5px;" class="sparql_field_row">
                <input type="text" value="${fld_id}" size="25" style="margin-right: 15px; padding: 3.5px;" class="input ui-widget-content">
                <span style="padding-right: 1em; font-size: 1.5em; cursor: default;">&rArr;</span>
                <select class="sparql_field_select" data-value="${this._fields[fld_id]}"></select>
                <span style="margin-left: 0.75em; cursor: pointer;" class="ui-icon ui-icon-close" title="Remove mapping"></span>
            </div>`;
        }

        content += '<div class="sparql_field_add" style="cursor: pointer; display: inline-block;"><span class="ui-icon ui-icon-plus"></span> Add new field</div>';

        content += '</div>';

        let btns = {};
        btns[window.hWin.HR('Update mapping')] = () => {

            this._fields = {};

            $.each($dlg.find('.sparql_field_row'), (idx, row) => {

                row = $(row);

                let field_name = row.find('input').val();
                let field_dty = row.find('select').val();

                if(this.$H.isempty(field_name) || this.$H.isempty(field_dty)){
                    return;
                }

                this._fields[field_name] = field_dty;
            });

            this._$('textarea#label_mapping').val(JSON.stringify(this._fields));

            $dlg.dialog('close');

            if(closingAction){
                this.doAction(true);
            }
        };
        btns[window.hWin.HR('Cancel')] = () => {
            $dlg.dialog('close');
        };

        $dlg = this.$Hmsg.showMsgDlg(content, btns,
            {title: 'SPARQL field mappings', yes: window.hWin.HR('Update mapping'), no: window.hWin.HR('Cancel')},
            {dialogId: 'SPARQL_mappings', default_palette_class: 'ui-heurist-design', width: 600, height: 600}
        );

        this._on($dlg.find('.sparql_field_add'), {
            click: () => {

                let $div = $('<div>', {
                    style: 'padding: 10px 5px;',
                    class: 'sparql_field_row',
                    html: `<input type="text" size="25" style="margin-right: 15px; padding: 3.5px;" class="input ui-widget-content">
                    <span style="padding-right: 1em; font-size: 1.5em; cursor: default;">&rArr;</span>
                    <select class="sparql_field_select"></select>
                    <span style="margin-left: 0.75em;" class="ui-icon ui-icon-close"></span>`
                }).insertBefore($dlg.find('.sparql_field_add'));

                fillDropdown($div.find('.sparql_field_select'));
                setupAutoFill($div.find('input[type="text"]'));
                setupRemoveRow($div.find('.ui-icon-close'));
            }
        });

        fillDropdown($dlg.find('.sparql_field_select'));
        setupAutoFill($dlg.find('input[type="text"]'));
        setupRemoveRow($dlg.find('.ui-icon-close'));
    },

    doAction: function(skipFieldMapping = true){
        
        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent());

        if(skipFieldMapping && typeof this._fields === 'object' && Object.keys(this._fields).length === 0){

            this.$Hmsg.showMsgFlash('Please map at least one field to return...', 3000);
            skipFieldMapping = false;
        }

        if(!skipFieldMapping){
            this._getFieldMapping(true);
            return;
        }

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};

        if(!this.$H.isempty(this.url_field) && recset.getFields().indexOf(this.url_field) !== -1){
            res['ext_url'] = recset.fld(record, this.url_field);
        }

        this.options.mapping.fields = this._fields;

        res = this.prepareValues(recset, record, res);

        // Add record dump
        if(this.options.mapping?.options?.dump_record){

            let dump_field = this.options.mapping.options.dump_field;
            record.shift(); // remove placeholder ID

            if(dump_field === 'rec_ScratchPad'){
                res['rec_ScratchPad'] = record;
            }else if(Object.hasOwn(res, dump_field)){
                res[dump_field].push(record);
            }else{
                res[dump_field] = [record];
            }
        }

        this.closingAction(res);
    },

    _doSearch: function(){

        let sparql = this._$('textarea#sparql-input').val().trim();

        if(this.$H.isempty(sparql)){
            this.$Hmsg.showMsgFlash('Please enter something to query...', 3000);
            return;
        }else if(sparql.toLowerCase().indexOf('select') === -1){
            this.$Hmsg.showMsgFlash('No fields will be returned, please add a "SELECT" section', 3000);
            return;
        }

        this.url_field = '';

        this._super({query: sparql});
    },

    _onSearchResult: function(response){
        
        if(response.data.results.bindings.length === 0){
            this._super(false);
            return;
        }

        this.result_fields = response.data.head.vars;

        let fields = ['rec_ID', ...this.result_fields];
        let records = {};
        let order = [];

        for(const rec_ID in response.data.results.bindings){

            let record = [rec_ID];
            let wikidata_record = response.data.results.bindings[rec_ID];

            for(const field of this.result_fields){

                if(this.url_field === '' && wikidata_record[field]['type'] === 'uri'){
                    this.url_field = field;
                }else if(wikidata_record[field]['type'] === 'literal' && Object.hasOwn(wikidata_record[field], 'xml:lang')){
                    let language = window.hWin.HAPI4.getLangCode3(wikidata_record[field]['xml:lang'], 'MUL');
                    wikidata_record[field]['value'] = language !== 'MUL' ? `${language}:${wikidata_record[field]['value']}` : wikidata_record[field]['value'];
                }

                record.push(wikidata_record[field]['value']);
            }

            records[rec_ID] = record;
            order.push(rec_ID);
        }

        let res = order.length > 0 ? {fields: fields, order: order, records: records} : false;
        this._super(res);
    }
});