/**
* lookupBnFLibrary_bib.js - Searching the BnF Library's bibliographic records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupBnFLibrary_bib.html), and
*   2) Performs an api call to the BnF Library's Search API using the User's input, displaying the results within a Heurist result list
* 
* After record selection, the user:
*   Is required to select or create a record for each record pointer field, with a value
*   Is required to enter or correct any terms for each enumerated field, with a value 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
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

$.widget( "heurist.lookupBnFLibrary_bib", $.heurist.lookupBase, {

    // default options
    options: {
    
        height: 700,
        width:  800,
        
        title:  "Search the Bibliothèque nationale de France's bibliographic records",
        
        htmlContent: 'lookupBnFLibrary_bib.html'
    },

    _forceClose: false, // skip saving additional mapping and close dialog

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        // Extra field styling
        this.element.find('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'});
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '7.5px'});

        let $select = this.element.find('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];
        let sel_options = {
            useHtmlSelect: false
        };
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, sel_options);

        this._on(this.element.find('input[name="dump_field"]'), {
            change: function(){
                let opt = this.element.find('input[name="dump_field"]:checked').val();
                window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), opt == 'rec_ScratchPad');
            }
        });

        return this._super();
    },

    /**
     * Set up additional settings tab
     */
    _setupSettings: function(){

        let options = this.options.mapping?.options;
        let need_save = false;

        if(!options || window.hWin.HEURIST4.util.isempty(options)){
            options = {
                author_codes: '',
                dump_record: true,
                dump_field: 'rec_ScratchPad'
            };

            need_save = true;
        }

        if(!window.hWin.HEURIST4.util.isempty(options['author_codes'])){
            this.element.find('#author-codes').text(options['author_codes']);
           
        }

        if(!window.hWin.HEURIST4.util.isempty(options['dump_record'])){
            this.element.find('input[name="dump_record"]').prop('checked', options['dump_field']);
        }

        if(!window.hWin.HEURIST4.util.isempty(options['dump_field'])){
            const selected = options['dump_field'];

            if(selected === 'rec_ScratchPad'){
                this.element.find('input[name="dump_field"][value="rec_ScratchPad"]').prop('checked', true);
            }else{
                this.element.find('input[name="dump_field"][value="dty_ID"]').prop('checked', true);
                this.element.find('#rty_flds').val(selected);

                if(this.element.find('#rty_flds').hSelect('instance') !== undefined){
                    this.element.find('#rty_flds').hSelect('refresh');
                }
            }

            window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), selected == 'rec_ScratchPad');
        }

        if(need_save){
            this._saveExtraSettings(true, false);
        }
    },

    /**
     * Get listed author codes
     */
    _getRoleCodes: function(){

        let author_codes = this.element.find('#author_codes').text();
        let contributor_codes = '';//this.element.find('#contributor-codes').text()
        const regex = /\d+/;

        author_codes = regex.test(author_codes) ? author_codes.match(regex).join(',') : '';

        return [ author_codes, contributor_codes ];
    },

    /**
     * Get record dump settings
     */
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

    /**
     * Save extra settings
     * @param {boolean} settings - whether to get settings for saving
     * @param {boolean} close_dlg - whether to close the dialog after saving 
     */
    _saveExtraSettings: function(settings = true, close_dlg = false){

        const codes = this._getRoleCodes();
        const rec_dump_settings = this._getRecDumpSetting();

        if(settings === null){
            settings = {
                author_codes: codes[0],
                dump_record: rec_dump_settings[0],
                dump_field: rec_dump_settings[1]
            };
        }

        this._super(settings, close_dlg);
    },

    /**
     * Result list rendering function called for each record
     * 
     * Param:
     *  recordset (HRecordSet) => Heurist Record Set
     *  record (json) => Current Record being rendered
     * 
     * Return: html
     */
    _rendererResultList: function(recordset, record){

        /**
         * Get field details for displaying
         * 
         * Param:
         *  fldname (string) => mapping field name
         *  width (int) => width for field
         * 
         * Return: html
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            if(fldname == 'author'){

                let contributor = recordset.fld(record, 'contributor');
                if(contributor !== undefined){
                    s = !s ? contributor : {...contributor, ...s};
                }

                if(!s || s == ''){ 
                    return `<div style="display:inline-block;width:${width}ex" class="truncate">No provided creator</div>`;
                }

                let creator_val = '';

                for(let idx in s){

                    let cur_obj = s[idx];
                    let cur_string = cur_obj;

                    if(window.hWin.HEURIST4.util.isObject(cur_obj)){
                        if(Object.hasOwn(cur_obj, 'firstname') && cur_obj['firstname'] != ''){
                            cur_string = cur_obj['firstname'];
                        }
                        if(Object.hasOwn(cur_obj, 'surname') && cur_obj['surname'] != ''){
                            cur_string = cur_string != '' ? `${cur_obj['surname']}, ${cur_string}` : cur_obj['surname'];
                        }
                        if(Object.hasOwn(cur_obj, 'active') && cur_obj['active'] != ''){
                            cur_string += ` (${cur_obj['active']})`;
                        }
                    }

                    creator_val += !cur_string || Array.isArray(cur_string) || window.hWin.HEURIST4.util.isObject(cur_string) 
                                    ? 'Missing author; ' : `${cur_string}; `;
                }

                s = creator_val;
            }else if(fldname == 'publisher'){

                if(!s || s == ''){ 
                    return `<div style="display:inline-block;width:${width}ex" class="truncate">No provided publisher</div>`;
                }

                let pub_val = '';

                for(let idx in s){

                    let cur_obj = s[idx];
                    let cur_string = cur_obj;

                    if(window.hWin.HEURIST4.util.isObject(cur_obj)){
                        if(Object.hasOwn(cur_obj,'name') && cur_obj['name'] != ''){
                            cur_string = cur_obj['name'];
                        }
                        if(Object.hasOwn(cur_obj,'location') && cur_obj['location'] != '' && cur_string == ''){
                            cur_string = cur_obj['location'];
                        }
                    }

                    pub_val += !cur_string || Array.isArray(cur_string) || window.hWin.HEURIST4.util.isObject(cur_string) 
                                ? 'Missing author; ' : `${cur_string}; `;
                }

                s = pub_val;
            }else{
                s = Array.isArray(s) ? s.join('; ') : s;
                s = window.hWin.HEURIST4.util.htmlEscape(s || '');
            }

            let title = s;

            if(fldname == 'biburl'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View bibliographic record';
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }
        
        const recTitle = fld('author', 25) + fld('publisher', 20) + fld('date', 10) + fld('title', 70) + fld('biburl', 12); 
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     * 
     * To trigger record pointer selection/creation popup, value must equal [dty_ID, default_searching_value]
     * 
     * Include a url to an external record that will appear in the record pointer guiding popup, add 'ext_url' to res
     *  the value must be the complete html (i.e. anchor tag with href and target attributes set)
     *  e.g. res['ext_url'] = '<a href="www.example.com" target="_blank">Link to Example</a>'
     * 
     * Param: None
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        let that = this;
        let field_name, val; // in case timeout completes 

        // get selected recordset
        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        this.action_timeout = setTimeout(function(){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            let dty_id = that.options.mapping.fields[field_name];

            if(Array.isArray(val) || window.hWin.HEURIST4.util.isObject(val)){
                val = JSON.stringify(val);
            }

            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'An error has occurred with mapping values to their respective fields,<br>'
                        + 'please report this by using the bug reporter under Help at the top right of the main screen or,<br>'
                        + 'via email directly to support@heuristnetwork.org so we can fix this quickly.<br><br>'
                        + 'Invalid field details:<br>'
                        + `Response field - "${field_name}"<br>`
                        + `Record field - "${$Db.rst(that.options.mapping.rty_ID, dty_id, 'rst_DisplayName')}" (<em>${$Db.dty(dty_id, 'dty_Type')}</em>)<br>`
                        + `Value to insert - "${val}"<br>`,
                error_title: 'Saving selection canceled',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
        }, 20000); // set timeout to 20 seconds

        let res = {};
        let map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        res['BnF_ID'] = recset.fld(record, 'BnF_ID'); // add BnF ID
        res['ext_url'] = recset.fld(record, 'biburl'); // add BnF URL

        // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
        for(const fld_Name of map_flds){

            field_name = fld_Name;
            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            val = recset.fld(record, fld_Name);
            let field_type = $Db.dty(dty_ID, 'dty_Type');

            if(window.hWin.HEURIST4.util.isObject(val)){
                val = Object.values(val).filter((value) => !window.hWin.HEURIST4.util.isempty(value));
            }else if(!Array.isArray(val)){
                val = window.hWin.HEURIST4.util.isempty(val) ? '' : val;
                val = [val];
            }

            if(window.hWin.HEURIST4.util.isempty(val)){
                continue;
            }

            switch(fld_Name){
                case 'author': // special treatment for author fields
                case 'contributor':
                    
                    for(const idx in val){

                        
                        const cur_val = val[idx];
                        let is_object = window.hWin.HEURIST4.util.isObject(cur_val);
                        
                        let value = is_object ? cur_val : '';
                        let search = is_object ? cur_val : '';
                        let role = '';

                        if(is_object){
                            if(Object.hasOwn(cur_val, 'firstname')){
                                value = cur_val['firstname'];
                            }
                            if(Object.hasOwn(cur_val, 'surname')){
                                value = (value != '') ? `${value} ${cur_val['surname']}` : cur_val['surname'];
                            }
                            search = value;
                            if(Object.hasOwn(cur_val, 'active')){
                                value = (value != '') ? `${value} [${cur_val['active']}]` : `No Name, years active: ${cur_val['active']}`;
                            }
                            if(Object.hasOwn(cur_val, 'id')){
                                value = (value != '') ? `${value} (id: ${cur_val['id']})` : `id: ${cur_val['id']}`;
                            }
                            if(Object.hasOwn(cur_val, 'role')){
                                role = (value != '') ? cur_val['role'] : '';
                            }
                        }

                        if(window.hWin.HEURIST4.util.isempty(value) || Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                            continue;
                        }

                        if(field_type == 'resource'){
                            val[idx] = {value: value, search: search};
                        }else if(field_type == 'relmarker'){
                            val[idx] = {value: value, search: search, relation: role};
                        }else{
                            val[idx] = value;
                        }
                    }

                    break;
                case 'publisher':

                    for(const idx in val){

                        let value = '';
                        let search = '';
                        const cur_val = val[idx];

                        if(window.hWin.HEURIST4.util.isObject(cur_val)){
                            if(Object.hasOwn(cur_val, 'name')){
                                value = cur_val['name'];
                                search = value;
                            }
                            if(Object.hasOwn(cur_val, 'location')){
                                value = (value != '') ? `${value} ${cur_val['location']}` : cur_val['location'];
                                search = (search != '') ? search : value;
                            }
                        }

                        if(window.hWin.HEURIST4.util.isempty(value) || Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                            continue;
                        }

                        if(field_type == 'resource'){
                            val[idx] = {'value': value, 'search': search};
                        }else if(field_type == 'relmarker'){
                            val[idx] = {'value': value, 'search': search, 'relation': ''};
                        }else{
                            val[idx] = value;
                        }
                    }

                    break;
                case 'language': // handle if language equals '###'

                    for(const idx in val){
                        val[idx] = val[idx] == '###' || val[idx] == 'und' ? 'unknown' : val[idx];
                    }

                    break;

                default:
                    break;
            }

            // Match term labels with val, need to return the term's id to properly save its value
            if(field_type == 'enum'){
                val = this._getTermByCode(null, dty_ID, val);
            }

            // Check that val and id are valid, add to response object
            if(!window.hWin.HEURIST4.util.isempty(val)){

                if(!Object.hasOwn(res, dty_ID)){
                    res[dty_ID] = [];
                }
                res[dty_ID] = res[dty_ID].concat(val);
            }
        }

        this.closingAction(res);
    },

    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){

        let that = this;

        /**
         * recordSchema: XML structure for record details (changing this will require changes to the php code in record_lookup.php)
         * maximumRecords: maximum number of records returned from the search (api default: 100)
         * startRecord: starting point, complete searches in batches (api default: 1)
         * query: encoded string enclosed in brackets (at minimum, the spaces MUST be encoded)
         */

        //let recordType = $('#inpt_doctype').val(); // which record type is requested
        let maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        let sURL = `https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&maximumRecords=${maxRecords}&startRecord=1&recordSchema=unimarcxchange`; // base URL

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && this.element.find('#inpt_title').val()=='' 
            && this.element.find('#inpt_author').val()=='' && this.element.find('#inpt_recordid').val()==''){

            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        let query = '(';

        /** 
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the bibliographic fields can be added here (fields starting with 'bib.')
         * if you wish to query authority records (fields starting with 'aut.'), we suggest the alternative BnF lookup available (lookupBnFLibrary_aut)
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * 
         * also separating each field query is a boolean logic [and, or, not]
         */

        let titleHasValue = this.element.find('#inpt_title').val() != '';
        let authorHasValue = this.element.find('#inpt_author').val() != '';
        let recidHasValue = this.element.find('#inpt_recordid').val() != '';

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            query += `bib.anywhere ${this.element.find('#inpt_any_link').val()} "${this.element.find('#inpt_any').val()}"`;

            if(titleHasValue || authorHasValue || recidHasValue){ // add combination logic
                query += ` ${this.element.find('#inpt_any_logic').val()} `;
            }
        }

        // work title field
        if(titleHasValue){

            query += `bib.title ${this.element.find('#inpt_title_link').val()} "${this.element.find('#inpt_title').val()}"`;

            if(authorHasValue || recidHasValue){ // add combination logic
                query += ` ${this.element.find('#inpt_title_logic').val()} `;
            }
        }

        // author field
        if(authorHasValue){

            query += `bib.author ${this.element.find('#inpt_author_link').val()} "${this.element.find('#inpt_author').val()}"`;

            if(recidHasValue){ // add combination logic
                query += ` ${this.element.find('#inpt_author_logic').val()} `;
            }
        }

        // record id field
        if(recidHasValue){
            query += `bib.recordid ${this.element.find('#inpt_recordid_link').val()} "${this.element.find('#inpt_recordid').val()}"`;
            // no combination logic as record id is the last field
        }

        /* requested record type
        if(recordType!=''){

            if(query.length != 1){ // add combination logic (and, or, not)
                query += ' and ';
            }
            query += 'bib.doctype all "' + recordType + '"'; 
        }*/

        // Close off and encode query portion, then add to request url
        if(query.length != 1){

            query += ')';
            query = encodeURIComponent(query);

            sURL += `&query=${query}`;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element); // show loading cover

        const codes = this._getRoleCodes();

        // for record_lookup.php
        let request = {
            service: sURL, // request url
            serviceType: 'bnflibrary_bib', // requesting service, otherwise no
            author_codes: codes[0]
            //, contributor_codes: codes[1]
        };

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){ // Error return
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._onSearchResult(response);
        });
    },

    /**
     * Prepare json for displaying via the Heuirst resultList widget
     *
     * @param {json} json_data - search response
     */
    _onSearchResult: function(json_data){

        let maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if(!json_data){
            this._super(false);
        }

        if(!json_data.result) return false;

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        // the fields used here are defined within /heurist/hserv/controller/record_lookup_config.json where "service" = bnfLibrary
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);

        if(this.options.mapping.options.dump_record == true){
            fields = fields.concat('BnF_ID');
        }

        // Parse json to Record Set
        let i = 1;
        for(const record of json_data.result){

            let recID = i++;
            let values = [recID, this.options.mapping.rty_ID];

            // Add current record details, field by field
            for(const fld_Name of map_flds){

                // With the current setup for API search, the 'Rights' field is no longer sent
                if(fld_Name == 'rights'){
                    values.push(null);
                }else{ // just add field details
                    values.push(record[fld_Name]);
                }
            }

            if(this.options.mapping.options.dump_record == true){
                values.push(record['BnF_ID']);
            }

            res_orders.push(recID);
            res_records[recID] = values;
        }

        if(json_data.numberOfRecords > maxRecords){
            window.hWin.HEURIST4.msg.showMsgDlg(
                `There are ${json_data.numberOfRecords} records satisfying these criteria, only the first ${maxRecords} are shown.<br>Please narrow your search.`
            );
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});