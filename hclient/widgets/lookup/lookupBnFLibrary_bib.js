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

$.widget( "heurist.lookupBnFLibrary_bib", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 700,
        width:  800,
        modal:  true,
        
        title:  "Search the Bibliothèque nationale de France's bibliographic records",
        
        htmlContent: 'lookupBnFLibrary_bib.html',
        helpContent: null, //in context_help folder

        mapping: null, //configuration from record_lookup_config.json

        add_new_record: false, //if true it creates new record on selection
        
        pagesize: 20 // result list's number of records per page
    },
    
    recordList: null,

    action_timeout: null, // timeout for processing doAction

    tabs_container: null, // tabs container

    _forceClose: false, // skip saving additional mapping and close dialog

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        var that = this;

        // Extra field styling
        this.element.find('.header.recommended').css({width:'100px', 'min-width':'100px', display: 'inline-block'});
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '7.5px'});

        // Action button styling
        this.element.find('#btnStartSearch').addClass("ui-button-action");

        // Prepare result list options
        this.options.resultList = $.extend(this.options.resultList, 
        {
               recordDivEvenClass: 'recordDiv_blue',
               eventbased: false,  //do not listent global events

               multiselect: false, // allow only one record to be selected
               select_mode: 'select_single', // only accept one record for selection

               selectbutton_label: 'select!!', // not used

               view_mode: 'list', // result list viewing mode [list, icon, thumb]
               show_viewmode:false,
               
               entityName: this._entityName,
               
               pagesize: this.options.pagesize, // number of records to display per page, 
               empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>', // For empty results
               renderer: this._rendererResultList // Record render function, is called on resultList updateResultSet
        });                

        // Init record list
        this.recordList = this.element.find('.div_result');

        this.recordList.resultList( this.options.resultList );
        this.recordList.resultList('option', 'pagesize', this.options.pagesize); // so the pagesize doesn't get set to a different value

        // Init select & double click events for result list
        this._on( this.recordList, {        
            "resultlistonselect": function(event, selected_recs){
                window.hWin.HEURIST4.util.setDisabled( 
                    this.element.parents('.ui-dialog').find('#btnDoAction'), 
                    (selected_recs && selected_recs.length()!=1));
            },
            "resultlistondblclick": function(event, selected_recs){
                if(selected_recs && selected_recs.length()==1){
                    this.doAction();                                
                }
            }
        });        

        // Handling for 'Search' button        
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });

        // For capturing the 'Enter' key while typing
        this._on(this.element.find('input'),{
            'keypress':this.startSearchOnEnterPress
        });

        let $select = this.element.find('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];
        let sel_options = {
            'useHtmlSelect': false
        };
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, sel_options);

        this._on(this.element.find('input[name="dump_field"]'), {
            'change': function(){
                let opt = this.element.find('input[name="dump_field"]:checked').val();
                window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), opt == 'rec_ScratchPad');
            }
        });

        this._on(this.element.find('#save-settings').button(), {
            'click': this._saveExtraSettings
        });

        // Setup settings tab
        this._setupSettings();

        this._as_dialog.on('dialogbeforeclose', function(e){
            if(!that._forceClose){
                that._forceClose = true;
                that._saveExtraSettings(true);
                return false;
            }
        });

        this.tabs_container = this.element.find('#tabs-cont').tabs();
        this.element.find('#inpt_any').focus();

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
                'author_codes': '',
                'dump_record': true,
                'dump_field': 'rec_ScratchPad'
            };

            need_save = true;
        }

        if(!window.hWin.HEURIST4.util.isempty(options['author_codes'])){
            this.element.find('#author-codes').text(options['author_codes']);
            //this.element.find('#contributor-codes').text(options['contributor_codes']);
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
            this._saveExtraSettings();
        }
    },

    /**
     * Get listed author codes
     */
    _getRoleCodes: function(){

        let author_codes = this.element.find('#author-codes').text();
        let contributor_codes = '';//this.element.find('#contributor-codes').text()
        const regex = /\d+/g;

        if(regex.test(author_codes)){
            let parts = author_codes.match(regex);
            author_codes = parts.join(',');
        }else{
            author_codes = '';
        }

        if(regex.test(contributor_codes)){
            let parts = contributor_codes.match(regex);
            contributor_codes = parts.join(',');
        }else{
            contributor_codes = '';
        }

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
     * @param {boolean} close_dlg - whether to close the dialog after saving 
     */
    _saveExtraSettings: function(close_dlg = false){

        var that = this;
        let services = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);
        const codes = this._getRoleCodes();
        const rec_dump_settings = this._getRecDumpSetting();

        if(services !== false){
            services[this.options.mapping.service_id]['options'] = { 
                'author_codes': codes[0], //'contributor_codes': codes[1]
                'dump_record': rec_dump_settings[0],
                'dump_field': rec_dump_settings[1]
            };

            var fields = {
                'sys_ID': 1,
                'sys_ExternalReferenceLookups': JSON.stringify(services)
            };
    
            // Update sysIdentification record
            var request = {
                'a': 'save',
                'entity': 'sysIdentification',
                'request_id': window.hWin.HEURIST4.util.random(),
                'isfull': 0,
                'fields': fields
            };
    
            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
    
                if(response.status == window.hWin.ResponseStatus.OK){
                    window.hWin.HAPI4.sysinfo['service_config'] = window.hWin.HEURIST4.util.cloneJSON(services); // update global copy
                    if(close_dlg === true){
                        that._as_dialog.dialog('close');
                    }else{
                        that.options.mapping = window.hWin.HEURIST4.util.cloneJSON(services[that.options.mapping.service_id]);
                        window.hWin.HEURIST4.msg.showMsgFlash('Extra lookup settings saved...', 3000);
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        }
    },
    
    /**
     * Function handler for pressing the enter button while focused on input element
     * 
     * Param:
     *  e (event trigger)
     */
    startSearchOnEnterPress: function(e){
        
        var code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }

    },
    
    /**
     * Result list rendering function called for each record
     * 
     * Param:
     *  recordset (hRecordSet) => Heurist Record Set
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

            var s = recordset.fld(record, fldname);

            if(fldname == 'author'){

                let contributor = recordset.fld(record, 'contributor');
                if(contributor !== undefined){
                    s = !s ? contributor : {...contributor, ...s};
                }

                if(!s || s == ''){ 
                    return '<div style="display:inline-block;width:'+width+'ex" class="truncate"">No provided creator</div>';
                }

                var creator_val = '';

                for(var idx in s){

                    var cur_string = '';
                    var cur_obj = s[idx];

                    if($.isPlainObject(cur_obj)){
                        if(cur_obj.hasOwnProperty('firstname') && cur_obj['firstname'] != ''){
                            cur_string = cur_obj['firstname'];
                        }
                        if(cur_obj.hasOwnProperty('surname') && cur_obj['surname'] != ''){
                            cur_string = (cur_string != '') ? cur_obj['surname'] + ', ' + cur_string : cur_obj['surname'];
                        }
                        if(cur_obj.hasOwnProperty('active') && cur_obj['active'] != ''){
                            cur_string += ' (' + cur_obj['active'] + ')';
                        }

                        if(cur_string == ''){
                            Object.values(cur_obj);
                        }
                    }else{
                        cur_string = cur_obj;
                    }

                    if(!cur_string || $.isArray(cur_string) || $.isPlainObject(cur_string)){
                        creator_val += 'Missing author; ';
                    }else{
                        creator_val += cur_string + '; ';
                    }
                }

                s = creator_val;
            }else if(fldname == 'publisher'){

                if(!s || s == ''){ 
                    return '<div style="display:inline-block;width:'+width+'ex" class="truncate"">No provided publisher</div>';
                }

                var pub_val = '';

                for(var idx in s){

                    var cur_string = '';
                    var cur_obj = s[idx];

                    if($.isPlainObject(cur_obj)){
                        if(cur_obj.hasOwnProperty('name') && cur_obj['name'] != ''){
                            cur_string = cur_obj['name'];
                        }
                        if(cur_obj.hasOwnProperty('location') && cur_obj['location'] != '' && cur_string == ''){
                            cur_string = cur_obj['location'];
                        }

                        if(cur_string == ''){
                            Object.values(cur_obj);
                        }
                    }else{
                        cur_string = cur_obj;
                    }

                    if(!cur_string || $.isArray(cur_string) || $.isPlainObject(cur_string)){
                        pub_val += 'Missing publisher; ';
                    }else{
                        pub_val += cur_string + '; ';
                    }
                }

                s = pub_val;
            }else if(window.hWin.HEURIST4.util.isArray(s) && s.length > 1){
                s = window.hWin.HEURIST4.util.htmlEscape(s.join('; '));
            }else if(window.hWin.HEURIST4.util.isArray(s) && s.length == 1){
                s = window.hWin.HEURIST4.util.htmlEscape(s[0]?s[0]:'');
            }else{
                s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');
            }

            title = s;

            if(fldname == 'biburl'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View bibliographic record';
            }
            
            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }
            return s;
        }

        // Generic details, not completely necessary
        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;' + window.hWin.HAPI4.iconBaseURL + rectypeID + '&version=thumb&quot;);"></div>';

        var recTitle = fld('author', 25) + fld('publisher', 20) + fld('date', 10) + fld('title', 70) + fld('biburl', 12); 

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectypeID+'">'
            + html_thumb
                + '<div class="recordIcons">'
                +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/>' 
                + '</div>'
                +  recTitle
            + '</div>';
        return html;
    },

    /**
     * Initial dialog buttons on bottom bar, _getActionButtons() under recordAction.js
     */
    _getActionButtons: function(){
        var res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Select');
        return res;
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     * 
     * To trigger record pointer selection/creation popup, value must equal [dty_ID, default_searching_value]
     * 
     * Include a url to an external record that will appear in the record pointer guiding popup, add 'ext_url' to res
     *  the value must be the complete html (i.e. anchor tag with href and target attributes set)
     *  e.g. res['ext_url'] = '<a href="www.google.com" target="_blank">Link to Google</a>'
     * 
     * Param: None
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        var that = this;
        var field_name, val; // in case timeout completes 

        this.action_timeout = setTimeout(function(){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            var dty_id = that.options.mapping.fields[field_name];

            if(window.hWin.HEURIST4.util.isArray(val) || window.hWin.HEURIST4.util.isObject(val)){
                val = JSON.stringify(val);
            }

            window.hWin.HEURIST4.msg.showMsgErr(
                'An error has occurred with mapping values to their respective fields,<br>'
                + 'please report this by using the bug reporter under Help at the top right of the main screen or,<br>'
                + 'via email directly to support@heuristnetwork.org so we can fix this quickly.<br><br>'
                + 'Invalid field details:<br>'
                + 'Response field - "' + field_name + '"<br>'
                + 'Record field - "' + $Db.rst(that.options.mapping.rty_ID, dty_id, 'rst_DisplayName') + '" (<em>' + $Db.dty(dty_id, 'dty_Type') + '</em>)<br>'
                + 'Value to insert - "' + val + '"<br>'
            );
        }, 20000); // set timeout to 20 seconds

        // get selected recordset
        var recset = this.recordList.resultList('getSelected', false);

        if(recset && recset.length() == 1){

            var res = {};
            var rec = recset.getFirstRecord(); // get selected record

            var map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

            if(this.options.mapping.options.dump_record == true){
                res['BnF_ID'] = recset.fld(rec, 'BnF_ID'); // add BnF ID
            }
            res['ext_url'] = recset.fld(rec, 'biburl'); // add BnF URL

            // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
            for(var k=0; k<map_flds.length; k++){

                field_name = map_flds[k];
                var dty_ID = this.options.mapping.fields[field_name];
                val = recset.fld(rec, field_name);
                var field_type = $Db.dty(dty_ID, 'dty_Type');

                if(val != null && dty_ID != ''){

                    // Convert to array
                    if(window.hWin.HEURIST4.util.isObject(val)){
                        obj_keys = Object.keys(val);
                        val = Object.values(val);
                    }else if(!window.hWin.HEURIST4.util.isArray(val)){
                        val = window.hWin.HEURIST4.util.isnull(val) ? '' : val;
                        val = [val];
                    }

                    if(field_name == 'author' || field_name == 'contributor'){ // special treatment for author field

                        for(var i = 0; i < val.length; i++){

                            let value = '';
                            let search = '';
                            let role = '';
                            var cur_val = val[i];

                            if($.isPlainObject(cur_val)){
                                if(cur_val['firstname']){
                                    value = cur_val['firstname'];
                                }
                                if(cur_val['surname']){
                                    value = (value != '') ? value + ' ' + cur_val['surname'] : cur_val['surname'];
                                }
                                search = value;
                                if(cur_val['active']){
                                    value = (value != '') ? value + ' [' + cur_val['active'] + ']' : 'No Name, years active: ' + cur_val['active'];
                                }
                                if(cur_val['id']){
                                    value = (value != '') ? value + ' (id: ' + cur_val['id'] + ')' : 'id: ' + cur_val['id'];
                                }
                                if(cur_val['role']){
                                    role = (value != '') ? cur_val['role'] : '';
                                }
                            }else{
                                value = cur_val;
                                search = cur_val;
                            }

                            if(value != '' && !$.isArray(value) && !$.isPlainObject(value)){
                                if(field_type == 'resource'){
                                    val[i] = {'value': value, 'search': search};
                                }else if(field_type == 'relmarker'){
                                    val[i] = {'value': value, 'search': search, 'relation': role};
                                }else{
                                    val[i] = value;
                                }
                            }
                        }

                        if(!res[dty_ID]){
                            res[dty_ID] = [];
                        }
                        res[dty_ID] = res[dty_ID].concat(val);

                        continue;
                    }else if(field_name == 'publisher'){

                        for(var i = 0; i < val.length; i++){

                            let value = '';
                            let search = '';
                            var cur_val = val[i];

                            if($.isPlainObject(cur_val)){
                                if(cur_val['name']){
                                    value = cur_val['name'];
                                    search = value;
                                }
                                if(cur_val['location']){
                                    value = (value != '') ? value + ' ' + cur_val['location'] : cur_val['location'];
                                    search = (search != '') ? search : value;
                                }
                            }else{
                                completed_val = cur_val;
                            }

                            if(value != '' && !$.isArray(value) && !$.isPlainObject(value)){

                                if(field_type == 'resource'){
                                    val[i] = {'value': value, 'search': search};
                                }else if(field_type == 'relmarker'){
                                    val[i] = {'value': value, 'search': search, 'relation': ''};
                                }else{
                                    val[i] = value;
                                }
                            }
                        }

                        if(!res[dty_ID]){
                            res[dty_ID] = [];
                        }
                        res[dty_ID] = res[dty_ID].concat(val);

                        continue;
                    }else if(field_name == 'language'){ // handle if language equals '###'

                        for(var i = 0; i < val.length; i++){
                            if(val[i] == '###' || val[i] == 'und'){
                                val[i] = 'unknown';
                            }
                        }
                    }

                    if(field_type == 'enum'){ // Match term labels with val, need to return the term's id to properly save its value

                        if(window.hWin.HEURIST4.util.isObject(val)){ 
                            val = Object.values(val);
                        }else if(!window.hWin.HEURIST4.util.isArray(val)){
                            val = [val];
                        }

                        var vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
                        var term_Ids = $Db.trm_TreeData(vocab_ID, 'set');

                        for(var i=0; i<val.length; i++){

                            if(!Number.isInteger(+val[i])){
                                continue;
                            }

                            for(let j = 0; j < term_Ids.length; j++){
                                let trm_code = $Db.trm(term_Ids[j], 'trm_Code');
                                if(trm_code == val[i]){
                                    val[i] = term_Ids[j];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Check that val and id are valid, add to response object
                if(dty_ID > 0 && val){

                    if(!res[dty_ID]){
                        res[dty_ID] = [];
                    }

                    if(window.hWin.HEURIST4.util.isObject(val)){
                        res[dty_ID] = res[dty_ID].concat(Object.values(val));
                    }else{
                        res[dty_ID] = res[dty_ID].concat(val);    
                    }
                }
            }

            this.closingAction(res);
        }
    },


    /**
     * Perform final actions before exiting popup
     * 
     * Param:
     *  dlg_reponse (json) => mapped values to fields
     */
    closingAction: function(dlg_response, action = 'save'){

        var that = this;

        clearTimeout(this.action_timeout); // clear timeout

        if(window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        window.hWin.HEURIST4.msg.sendCoverallToBack();

        // Pass mapped values back and close dialog
        this._context_on_close = dlg_response;
        this._as_dialog.dialog('close');
    },
    
    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){

        var that = this;

        /**
         * recordSchema: XML structure for record details (changing this will require changes to the php code in record_lookup.php)
         * maximumRecords: maximum number of records returned from the search (api default: 100)
         * startRecord: starting point, complete searches in batches (api default: 1)
         * query: encoded string enclosed in brackets (at minimum, the spaces MUST be encoded)
         */

        //var recordType = $('#inpt_doctype').val(); // which record type is requested
        var maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        var sURL = 'https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&maximumRecords='+maxRecords+'&startRecord=1&recordSchema=unimarcxchange'; // base URL

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && this.element.find('#inpt_title').val()=='' 
            && this.element.find('#inpt_author').val()=='' && this.element.find('#inpt_recordid').val()==''){

            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        var query = '(';

        /** 
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the bibliographic fields can be added here (fields starting with 'bib.')
         * if you wish to query authority records (fields starting with 'aut.'), we suggest the alternative BnF lookup available (lookupBnFLibrary_aut)
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * 
         * also separating each field query is a boolean logic [and, or, not]
         */

        var titleHasValue = this.element.find('#inpt_title').val() != '';
        var authorHasValue = this.element.find('#inpt_author').val() != '';
        var recidHasValue = this.element.find('#inpt_recordid').val() != '';

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            query += 'bib.anywhere '+ this.element.find('#inpt_any_link').val() +' "' + this.element.find('#inpt_any').val() + '"';

            if(titleHasValue || authorHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_any_logic').val() + ' ';
            }
        }

        // work title field
        if(titleHasValue){

            query += 'bib.title '+ this.element.find('#inpt_title_link').val() +' "' + this.element.find('#inpt_title').val() + '"';

            if(authorHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_title_logic').val() + ' ';
            }
        }

        // author field
        if(authorHasValue){

            query += 'bib.author '+ this.element.find('#inpt_author_link').val() +' "' + this.element.find('#inpt_author').val() + '"';

            if(recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_author_logic').val() + ' ';
            }
        }

        // record id field
        if(recidHasValue){
            query += 'bib.recordid '+ this.element.find('#inpt_recordid_link').val() +' "' + this.element.find('#inpt_recordid').val() + '"';
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

            sURL += '&query=' + query;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element); // show loading cover

        const codes = this._getRoleCodes();

        // for record_lookup.php
        var request = {
            service: sURL, // request url
            serviceType: 'bnflibrary_bib', // requesting service, otherwise no
            author_codes: codes[0]
            //, contributor_codes: codes[1]
        };

        // calls /heurist/hsapi/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            if(window.hWin.HEURIST4.util.isJSON(response)){ 

                if(response.result != null){ // Search result
                    that._onSearchResult(response);
                }else if(response.status && response.status != window.hWin.ResponseStatus.OK){ // Error return
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{ // No results
                    that.recordList.show();
                    that.recordList.resultList('updateResultSet', null);
                }
            }
        });
    },
    
    /**
     * Prepare json for displaying via the Heuirst resultList widget
     * 
     * Param:
     *  json_data (json) => search response
     */
    _onSearchResult: function(json_data){

        this.recordList.show();

        var is_wrong_data = true;

        var maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if (json_data) {
            
            if(!json_data.result) return false;

            var res_records = {}, res_orders = [];

            // Prepare fields for mapping
            // the fields used here are defined within /heurist/hsapi/controller/record_lookup_config.json where "service" = bnfLibrary
            var fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
            var map_flds = Object.keys(this.options.mapping.fields);
            fields = fields.concat(map_flds);

            if(this.options.mapping.options.dump_record == true){
                fields = fields.concat('BnF_ID');
            }
            
            // Parse json to Record Set
            var i=0;
            for(;i<json_data.result.length;i++){

                var record = json_data.result[i];                
                var recID = i+1;
                var values = [recID, this.options.mapping.rty_ID];

                // Add current record details, field by field
                for(var k=0; k<map_flds.length; k++){

                    // With the current setup for API search, the 'Rights' field is no longer sent
                    if(map_flds[k]=='rights'){
                        values.push(null);
                    }else{ // just add field details
                        values.push(record[map_flds[k]]);
                    }
                }

                if(this.options.mapping.options.dump_record == true){
                    values.push(record['BnF_ID']);
                }

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){

                //res_records = this.removeDupAuthors(fields.indexOf('author'), res_records);

                // Create the record set for the resultList
                var res_recordset = new hRecordSet({
                    count: res_orders.length,
                    offset: 0,
                    fields: fields,
                    rectypes: [this.options.mapping.rty_ID],
                    records: res_records,
                    order: res_orders,
                    mapenabled: true
                });
                this.recordList.resultList('updateResultSet', res_recordset);            
                is_wrong_data = false;
            }

            if(json_data.numberOfRecords > maxRecords){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    "There are " + json_data.numberOfRecords + " records satisfying these criteria, only the first "+ maxRecords +" are shown.<br>Please narrow your search.",
                );
            }
        }

        if(is_wrong_data){
            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr('Service did not return data in an appropriate format');
        }else{
            this.tabs_container.tabs('option', 'active', 1); // switch to results tab
        }
    },

    //
    removeDupAuthors: function(author_key, records){

        if(records == null || !window.hWin.HEURIST4.util.isObject(records) || window.hWin.HEURIST4.util.isempty(author_key)){
            return records;
        }

        for(var i in records){

            var author_details = records[i][author_key];

            if(author_details && author_details.length > 1){

                records[i][author_key] = author_details.filter(function(val, idx, arr){

                    return idx === arr.findIndex(function(obj){

                        if(val.id && obj.id){
                            return val.id == obj.id;
                        }else if(val.firstname && val.surname && obj.firstname && obj.surname){
                            return val.firstname == obj.firstname && val.surname == obj.surname;
                        }
                    });
                });
            }
        }

        return records;
    }
});