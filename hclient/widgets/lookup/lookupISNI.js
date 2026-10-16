/**
* lookupISNI.js - Search ISNI records from isni.oclc.org 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/**
 * Widget for ISNI (International Standard Name Identifier) lookup.
 * Inherits from `$.heurist.lookupBase`.
 *
 * This widget provides a specialized interface for searching ISNI records.
 * It includes specific UI controls for BnF search parameters and settings, such as record dump options.
 *
 * @widget heurist.lookupISNI
 * @augments heurist.lookupBase
 */
$.widget( "heurist.lookupISNI", $.heurist.lookupBase, {

    // default options
    options: {
    
        height: 700,
        width:  800,

        title:  "ISNI lookup",

        htmlContent: 'lookupISNI.html',
    },

    /**
     * The base URL for the external lookup service.
     * @memberof heurist.lookupISNI
     * @instance
     * @type {string}
     */
    baseURL: 'https://isni.oclc.org/sru/DB=1.2/',

    /**
     * The name of the external service.
     * @memberof heurist.lookupISNI
     * @instance
     * @type {string}
     */
    serviceName: 'isni',

    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @private
     * @override
     * @returns {void} Calls `this._super()`.
     */
    _initControls: function(){

        // Extra field styling
        this._$('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'});
        this._$('.isni_form_field').css({display:'inline-block', 'margin-top': '7.5px'});

        let $select = this._$('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];

        this.$Hui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, {useHtmlSelect: false});

        this._on(this._$('input[name="dump_field"]'), {
            change: function(){
                let opt = this._$('input[name="dump_field"]:checked').val();
                this.$H.setDisabled(this._$('#rty_flds'), opt == 'rec_ScratchPad');
            }
        });

        return this._super();
    },

    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _setupSettings: function(){

        let options = this.options.mapping?.options;
        let need_save = false;

        if(this.$H.isempty(options)){
            options = {dump_record: true, dump_field: 'rec_ScratchPad'};
            need_save = true;
        }

        if(!this.$H.isempty(options['dump_record'])){
            this._$('input[name="dump_record"]').prop('checked', options['dump_field']);
        }

        if(!this.$H.isempty(options['dump_field'])){
            const selected = options['dump_field'];

            if(selected === 'rec_ScratchPad'){
                this._$('input[name="dump_field"][value="rec_ScratchPad"]').prop('checked', true);
            }else{
                this._$('input[name="dump_field"][value="dty_ID"]').prop('checked', true);
                this._$('#rty_flds').val(selected);

                if(this._$('#rty_flds').hSelect('instance') !== undefined){
                    this._$('#rty_flds').hSelect('refresh');
                }
            }

            this.$H.setDisabled(this._$('#rty_flds'), selected == 'rec_ScratchPad');
        }

        if(need_save){
            this._saveExtraSettings();
        }
    },

    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @private
     * @override
     * @param {Object|boolean|null} [settings=false] - If `true` or an object, settings are gathered/used.
     *                                                If `null`, parent might save defaults or just close.
     *                                                If `false` (default), settings are gathered.
     * @param {boolean} [close_dlg=false] - Whether to close the dialog after saving.
     * @returns {void}
     */
    _saveExtraSettings: function(settings = false, close_dlg = false){

        const rec_dump_settings = this._getRecDumpSetting();

        // Original code uses `settings !== null`. If settings is explicitly false, it should still gather.
        // Assuming settings=false means gather, settings=null means do not gather and let parent handle.
        // For safety, aligning with original logic: if settings is not strictly null, then prepare them.
        if(settings !== null){
            settings = {
                dump_record: rec_dump_settings[0],
                dump_field: rec_dump_settings[1]
            };
        }

        this._super(settings, close_dlg);
    },
    
    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The complete record set.
     * @param {Array} record - The individual record (row) to be rendered.
     * @returns {string} The HTML string for the rendered record, generated by the parent's `_rendererResultList`.
     */
    _rendererResultList: function(recordset, record){

        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            if(Array.isArray(s)){
                s = s.length > 1 ? s.join('; ') : s[0];
            }

            let title = s;

            if(fldname == 'isni_uri'){
                s = `<a href="${s}" target="_blank"> view here </a>`;
                title = 'View authoritative record';
            }

            if(width > 0){
                let paddingRight = fldname === 'names' ? '10px' : '0px';
                s = `<div style="display:inline-block;width:${width}ex;padding-right:${paddingRight}" class="truncate" title="${title}">${s}</div>`;
            }

            return s;
        }

        // Generic details, not completely necessary
        const recID = fld('rec_ID');
        const rectypeID = fld('rec_RecTypeID');
        const recIcon = this.HAPI.iconBaseURL + rectypeID;
        const html_thumb = `<div class="recTypeThumb" style="background-image: url(&quot;${this.HAPI.iconBaseURL}${rectypeID}&version=thumb&quot;);"></div>`;

        const recTitle = fld('names', 75) + fld('titles', 50) + fld('isni_uri', 12);

        let html = `<div class="recordDiv" id="rd${recID}" recid="${recID}" rectype="${rectypeID}">
            ${html_thumb}
            <div class="recordIcons">
                <img src="${this.HAPI.baseURL}hclient/assets/16x16.gif" class="rt-icon" style="background-image: url(&quot;${recIcon}&quot;);"/>
            </div>
            ${recTitle}
        </div>`;

        return html;
    },

    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){
        this._super('isni_url');
    },

    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        let maxRecords = this._$('#rec_limit').val(); // Limit number of returned records
        let params = {
            version: '1.1',
            operation: 'searchRetrieve',
            recordSchema: 'isni-b',
            maximumRecords: !maxRecords || maxRecords <= 0 ? 20 : maxRecords, // Max records from API (default: 100)
            startRecord: 1 // Starting point for batch searches (API default: 1)
        };

        // Filter for any text input fields (excluding type inputs) that have a value
        let has_filter = this._$('input.text:not(type)').filter((idx, input) => {
            return !this.$H.isempty($(input).val());
        });

        // Check that something has been entered
        if(has_filter.length == 0){
            this.$Hmsg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url (CQL query)
        let query = '';
        let last_logic = ''; // Stores the last boolean operator used

        // Check which input fields have values
        let isniHasValue = this._$('#inpt_name').val() != '';
        let nameHasValue = this._$('#inpt_name').val() != '';
        let orcidHasValue = this._$('#inpt_orcid').val() != '';

        // Build query from each input field that has a value
        // Each field has a value input, a link type selector (=, <, >, <>, <=, >=, any, all, extact),
        // and a logic operator selector (AND, OR, NOT)

        // Any phrase (pica.aph)
        if(this._$('#inpt_any').val()!=''){
            last_logic = ` ${this._$('#inpt_any_logic').val()} `;
            query += `pica.aph ${this._$('#inpt_any_link').val()} "${this._$('#inpt_any').val()}"${last_logic}`;
        }

        // ISNI field (pica.isn)
        if(isniHasValue){
            last_logic = ` ${this._$('#inpt_isni_logic').val()} `;
            query += `pica.isn ${this._$('#inpt_isni_link').val()} "${this._$('#inpt_isni').val()}"${last_logic}`;
        }

        // Name field (pica.na)
        if(nameHasValue){
            last_logic = ` ${this._$('#inpt_name_logic').val()} `;
            query += `pica.na ${this._$('#inpt_name_link').val()} "${this._$('#inpt_name').val()}"${last_logic}`;
        }

        // ORC ID field (pica.orcid)
        if(orcidHasValue){
            last_logic = ` ${this._$('#inpt_orcid_logic').val()} `;
            query += `pica.orcid ${this._$('#inpt_orcid_link').val()} "${this._$('#inpt_orcid').val()}"${last_logic}`;
        }

        if(this._$('#sort_results').val() != ''){
            params['sortKeys'] = this._mapSortKey();
        }

        // Remove last trailing logic operator if it exists
        if(!this.$H.isempty(last_logic)){
            let regex = new RegExp(`${last_logic}$`);
            query = query.replace(regex, '');
        }

        params['query'] = query; // Add the constructed query to SRU parameters

        // Call the parent's _doSearch method with prepared SRU parameters and additional author_codes for server-side processing
        this._super(params);
    },

    _mapSortKey: function(){

        let sortKey = '';
        let sortBy = this._$('#sort_results').val();

        switch(sortBy){

            case 'relevance':
                sortKey = 'RLV,pica,0,,';
                break;

            case 'creation':
                sortKey = 'date,dc,0,,';
                break;

            case 'title':
                sortKey = 'title,dc,1,,';
                break;

            case 'creator':
                sortKey = 'creator,dc,1,,';
                break;

            default:
                sortKey = 'none';
                break;
        }
        return sortKey;
    },
    
    /**
     * @memberof heurist.lookupISNI
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _onSearchResult: function(json_data){

        let maxRecords = this._$('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = this.$H.isJSON(json_data);

        if(json_data?.numberOfRecords === 0){
            json_data.result = [];
        }else if(!Array.isArray(json_data?.result)){
            this._super(false);
            return;
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        // the fields used here are defined within /heurist/hserv/controller/LookupConfigs.json where "service" = isni
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);

        // Parse json to Record Set
        let i = 1;
        for(const record of json_data.result){

            let recID = i++;
            let values = [recID, this.options.mapping.rty_ID];

            // Add current record details, field by field
            for(const fld_Name of map_flds){
                values.push(record[fld_Name]);
            }

            res_orders.push(recID);
            res_records[recID] = values;
        }

        this.checkResultSize(json_data.numberOfRecords, maxRecords);

        let res = json_data.numberOfRecords === 0 ? null : false;
        res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : res;
        this._super(res);
    }
});