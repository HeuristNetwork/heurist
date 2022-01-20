/**
* recordLookupBnFLibrary.js - Searching the BnF Library's bibliographic records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (recordLookupBnFLibrary.html), and
*   2) Performs an api call to the BnF Library's Search API using the User's input, displaying the results within a Heurist result list
* 
* NOTE: This external lookup currently attempts to return record ids for resources (record pointers) via creator ids and full names,
*        it does attempt to match vocab/term ids for Type and Language.
*        Aside from this, every detail is returned as a string, some returning multiple values (can be a French and English version).
*        We've also tried to remove duplicated values
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2021 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.recordLookupBnFLibrary", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  "Search the Bibliothèque nationale de France's bibliographic records",
        
        htmlContent: 'recordLookupBnFLibrary.html',
        helpContent: null, //in context_help folder
        
        mapping: null, //configuration from record_lookup_config.json (DB Field: sys_ExternalReferenceLookups)
               
        add_new_record: false, //if true it creates new record on selection
        
        pagesize: 20 // result list's number of records per page
    },
    
    recordList:null,

    new_rec_fields: [],
    amb_rec_fields: [],

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'65px', 'min-width':'65px', display: 'inline-block'});
        this.element.find('#separate_fields > .header.recommended').css({width:'65px', 'min-width':'65px'});
        this.element.find('#btn_container').position({my: 'left top', at: 'right top', of: '#search_container'});
        this.element.find('#rec_count_field').css({'display': 'inline-block', 'float': 'right'}).position({my: 'left top', at: 'left bottom', of: '#btn_container'});
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
               
               pagesize: this.options.pagesize, // number of records to display per page
               empty_remark: '<div style="padding:1em 0 1em 0">No Works Found</div>', // For empty results
               renderer: this._rendererResultList // Record render function, is called on resultList updateResultSet
        });                

        // Init record list
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList( this.options.resultList );     
        
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

        return this._super();
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

            if(fldname == 'creator'){

                var obj = s;
                var creator_val = '';

                if(!s){ return creator_val; }

                if(s.hasOwnProperty('firstname') && s['firstname'] != ''){
                    creator_val = s['firstname'];
                }
                if(s.hasOwnProperty('surname') && s['surname'] != ''){
                    creator_val = (creator_val != '') ? s['surname'] + ', ' + creator_val : s['surname'];
                }
                if(s.hasOwnProperty('active') && s['active'] != ''){
                    creator_val += ' (' + s['active'] + ')';
                }
                if(!creator_val){
                    creator_val = 'Missing creator';
                }

                s = recordset.fld(record, 'contributor');
                if(s){

                    var contrib_val = '';
                    var hasFirstName = false;
                    if(s.hasOwnProperty('firstname') && s['firstname'] != ''){
                        contrib_val = '; ' + s['firstname'];
                        hasFirstName = true;
                    }
                    if(s.hasOwnProperty('surname') && s['surname'] != ''){
                        contrib_val = (hasFirstName) ? '; ' + s['surname'] + ', ' + s['firstname'] : '; ' + s['surname'];
                    }
                    if(s.hasOwnProperty('active') && s['active'] != ''){
                        contrib_val += ' (' + s['active'] + ')';
                    }

                    if(contrib_val != ''){
                        creator_val += contrib_val;
                    }
                }

                s = creator_val;
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

        var recTitle = fld('creator', 50) + fld('date', 7) + fld('title', 75) + fld('biburl', 12); 

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
     * 
     * Param: None
     */
    doAction: function(){

        var that = this;

        // get selected recordset
        var recset = this.recordList.resultList('getSelected', false);

        if(recset && recset.length() == 1){

            var res = {};
            var rec = recset.getFirstRecord();

            var map_flds = Object.keys(this.options.mapping.fields); // mapped fields

            var recpointer_dtyids = []; // store dty_ids for record pointer fields, to replace the value with record ids
            var recpointer_rtyids = []; // store related record type ids for record pointer fields, to send with request
            var recpointer_searchvals = []; // store search value for finding record pointer field's record id, to send with request

            // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
            for(var k=0; k<map_flds.length; k++){

                var dty_ID = this.options.mapping.fields[map_flds[k]];
                var val = recset.fld(rec, map_flds[k]);
                var field_type = $Db.dty(dty_ID, 'dty_Type');

                if(val != null){

                    // Match term labels with val, need to return the term's id to properly save its value
                    if(field_type == 'enum'){

                        var vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
                        var term_Ids = $Db.trm_TreeData(vocab_ID, 'set');

                        if(map_flds[k] == 'language' && val == 'und' || val == 'XX'){ // No language
                            val = null; // or $Db.getTermByLabel(vocab_ID, 'None'), depends on situation
                        }else{

                            var matching_terms = [];
                            for(var i=0; i<term_Ids.length; i++){

                                var trm_Label = $Db.trm(term_Ids[i], 'trm_Label').toLowerCase();
                                var val_isArray = window.hWin.HEURIST4.util.isArray(val);
                                var term_found = false;

                                if(val_isArray){ // multiple values, Language usually has two values and Type will have two or three values
                                    
                                    for(var j = 0; j < val.length; j++){

                                        if(val[j].toLowerCase() == trm_Label){

                                            val = term_Ids[i];
                                            term_found = true;
                                            break;
                                        }else if(trm_Label.includes(val[j].toLowerCase())){
                                            matching_terms.push(term_Ids[i]);
                                        }
                                    }
                                }else if(!val_isArray){ // In case of one single value
                                    
                                    if(val.toLowerCase() == trm_Label){

                                        val = term_Ids[i];
                                        term_found = true;
                                        break;
                                    }else if(trm_Label.includes(val.toLowerCase())){
                                        matching_terms.push(term_Ids[i]);
                                    }
                                }

                                if(term_found){
                                    break;
                                }
                            }

                            if(!Number.isInteger(val) && matching_terms.length > 0){ // check if an id was found, otherwise take first match
                                val = matching_terms[0];
                            }else{ // Unable to find match
                                val = null;
                            }
                        }
                    }else if(field_type == 'resource'){ // need to match the value(s) with a record, this is by far the most problematic

                        var ptr_Ids = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs'); // record type ids (listed as "1,2,3,...")
                        
                        recpointer_dtyids.push([dty_ID, map_flds[k]]);
                        recpointer_rtyids.push(ptr_Ids);

                        if(map_flds[k] == 'creator' || map_flds[k] == 'contributor'){

                            if(val['id']){
                                recpointer_searchvals.push(val['id']);
                            }else{

                                var searching = {};

                                if(val['surname']){
                                    searching['surname'] = val['surname'];
                                }
                                if(val['firstname']){
                                    searching['firstname'] = val['firstname'];
                                }

                                if(!$.isEmptyObject(searching)){
                                    recpointer_searchvals.push(searching);
                                }
                            }
                        }else{
                            recpointer_searchvals.push(val);
                        }
                    }
                }

                if(dty_ID>0 && val && field_type != 'resource'){

                    if(window.hWin.HEURIST4.util.isObject(val)){

                        res[dty_ID] = '';
                        for(var key in val){
                            res[dty_ID] += val[key] + ', ';
                        }

                        res[dty_ID] = res[dty_ID].slice(0, -2);
                    }else if(window.hWin.HEURIST4.util.isArray(val)){
                        res[dty_ID] = val.join(', ');
                    }else{
                        res[dty_ID] = val;    
                    }
                }
            }

            // Retrieve record ids for record pointer fields, or create new records with information
            if(recpointer_rtyids.length > 0){

                var request = {
                    recIds: recpointer_rtyids,
                    searchValues: recpointer_searchvals,
                    a: 'getrecordids'
                };

                var extraAction = 'save';

                // Match record pointers here, or earlier in another function if a server call is needed
                window.hWin.HAPI4.RecordMgr.get_record_ids(request, function(response){

                    var rec_ids = response.data;

                    var ambiguous_records = {};

                    var new_records = {};
                    new_records['records'] = {};

                    // Assign record ids from response to mapped fields
                    for(var i = 0; i < rec_ids.length; i++){
                        
                        if(rec_ids[i] != null && !window.hWin.HEURIST4.util.isArray(rec_ids[i]) && rec_ids[i] > 0){ // One record found

                            if(res[recpointer_dtyids[i][0]] == null){
                                res[recpointer_dtyids[i][0]] = rec_ids[i];
                            }else if(!window.hWin.HEURIST4.util.isArray(res[recpointer_dtyids[i][0]])){
                                res[recpointer_dtyids[i][0]] = [res[recpointer_dtyids[i][0]], rec_ids[i]];
                            }else{
                                res[recpointer_dtyids[i][0]].push(rec_ids[i]);
                            }
                        }else if(window.hWin.HEURIST4.util.isArray(rec_ids[i])){ // Multiple records found

                            extraAction = 'ambiguous';
                            ambiguous_records[recpointer_dtyids[i][0]] = rec_ids[i];

                            that.amb_rec_fields.push(recpointer_dtyids[i][1]);
                        }else{ // add new record to first rectype and assign new record id to field for sending back

                            extraAction = (extraAction != 'ambiguous') ? 'create' : extraAction;

                            var rty_id = recpointer_rtyids[i].split(',')[0]; // use first rectype id listed
                            // Alternatively, use the local id of another rectype
                            // rty_id = $Db.getLocalID('rty', rty_concept_id);

                            var fields = {}; // { basefield id: value, ... }

                            // To retrieve the local ids using the field's concept id
                            // var local_id = $Db.getLocalID('dty', concept_id);

                            var dty_rec = recset.fld(rec, recpointer_dtyids[i][1]);

                            if(dty_rec){
                                if(recpointer_dtyids[i][1] == 'creator' || recpointer_dtyids[i][1] == 'contributor'){

                                    var givenname_fld = $Db.getLocalID('dty', '2-18'); // Give name, freetext
                                    var surname_fld = $Db.getLocalID('dty', '2-1'); // Name / Title, freetext
                                    var id_fld = $Db.getLocalID('dty', '2-37'); //  Unique public identifier, or 2-936 External ID, both freetext

                                    if(givenname_fld && dty_rec['firstname']){
                                        fields[givenname_fld] = dty_rec['firstname'];
                                    }
                                    if(surname_fld && dty_rec['surname']){
                                        fields[surname_fld] = dty_rec['surname'];
                                    }
                                    if(id_fld && dty_rec['id']){
                                        fields[id_fld] = dty_rec['id'];
                                    }
                                }else if(recpointer_dtyids[i][1] == 'publisher'){

                                    var publisher_name_fld = $Db.getLocalID('dty', '2-1'); // Name / Title, freetext
                                    var publisher_location_fld = $Db.getLocalID('dty', '2-27'); // Place name, freetext
                                    // using place name here as the publisher location can be a country or a city name, possible more not found yet

                                    if(publisher_name_fld && dty_rec['name']){
                                        fields[publisher_name_fld] = dty_rec['name'];
                                    }
                                    if(publisher_location_fld && dty_rec['location']){
                                        fields[publisher_location_fld] = dty_rec['location'];
                                    }
                                }

                                if(!$.isEmptyObject(fields)){

                                    var record_details = {
                                        ID: 0,
                                        RecTypeID: rty_id,
                                        no_validation: true, // ignore check for values in required fields
                                        details: fields
                                    };

                                    var key = recpointer_dtyids[i][0];

                                    if(new_records['records'][key] == null){
                                        new_records['records'][key] = record_details;
                                    }else{
                                        new_records['records'][key+'_'+recpointer_dtyids[i][0]] = record_details;
                                    }
                                }else{
                                    res[recpointer_dtyids[i][0]] = null;
                                }
                            }else{
                                res[recpointer_dtyids[i][0]] = null;
                            }

                            that.new_rec_fields.push(recpointer_dtyids[i][1]);
                        }
                    } 

                    that.closingAction(res, extraAction, new_records, ambiguous_records);
                });
            }else{
                this.closingAction(res, 'save');
            }

        }
    },

    closingAction: function(dlg_response, action = 'save', new_records = null, ambiguous_records = null){

        var that = this;

        if(window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        if(action == 'save'){
            // send response and close dialog

            // Pass mapped values back and close dialog
            this._context_on_close = dlg_response;
            this._as_dialog.dialog('close');
        }else if(action == 'ambiguous' && !window.hWin.HEURIST4.util.isempty(ambiguous_records)){

            var cur_key = Object.keys(ambiguous_records)[0]; 

            var popup_options = {
                select_mode: 'select_single', // or 'select_multi'
                select_return_mode: 'ids', //or 'recordset'
                edit_mode: 'popup',
                selectOnSave: true, // true = select popup will be closed after add/edit is completed
                title: 'Select a record for the ' + this.amb_rec_fields.shift() + ' field',
                rectype_set: ambiguous_records[cur_key][1].split(','), // record type ID
                pointer_mode: 'browseonly', // options = both, addonly or browseonly
                pointer_filter: 'ids:' + ambiguous_records[cur_key][0], // initial filter on record lookup, default = none
                parententity: 0,

                width: 700,
                height: 600,

                // Hide search form
                onInitFinished: function(){
                    this.searchForm.hide();
                },

                // onselect Handler for pop-up
                onselect:function(event, data){
                    if(!window.hWin.HEURIST4.util.isempty(data.selection[0])){

                        // save to dlg_response
                        dlg_response[cur_key] = data.selection[0];

                        delete ambiguous_records[cur_key];

                        if(Object.keys(ambiguous_records).length < 1){
                            action = (!window.hWin.HEURIST4.util.isempty(new_records) && !$.isEmptyObject(new_records['records'])) ? 'create' : 'save';
                        }

                        that.closingAction(dlg_response, action, new_records, ambiguous_records);
                    }
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options); // Display popup

        }else if(action == 'create' && !window.hWin.HEURIST4.util.isempty(new_records) && !$.isEmptyObject(new_records['records'])){
            // create + save new records

            window.hWin.HEURIST4.msg.showMsgFlash('Creating new record(s) for:<br><br>' + this.new_rec_fields.join('<br>'));

            window.hWin.HAPI4.RecordMgr.batchSaveRecords(new_records, function(response){
                                        
                if(response && response.status == window.hWin.ResponseStatus.OK){

                    var recids = response.data;

                    for(var key in recids){

                        key = key.replace(/\D/g, '');

                        if(dlg_response[key] == null){
                            dlg_response[key] = recids[key];
                        }else if(!window.hWin.HEURIST4.util.isArray(dlg_response[key])){
                            dlg_response[key] = [dlg_response[key], recids[key]];
                        }else{
                            dlg_response[key].push(recids[key]);
                        }
                    }

                    that.closingAction(dlg_response, 'save');
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }
            });
        }else{ // close dialog
            window.hWin.HEURIST4.msg.showMsgErr('Unable to close dialog');
            return;
        }
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

        var maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        //var sURL = 'http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=dublincore&maximumRecords='+maxRecords+'&startRecord=1'; // base URL

        var sURL = 'http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&maximumRecords='+maxRecords+'&startRecord=1';

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && this.element.find('#inpt_title').val()=='' 
            && this.element.find('#inpt_author').val()=='' && this.element.find('#inpt_isbn').val()==''
            && this.element.find('#inpt_subject').val()=='' && this.element.find('#inpt_date').val()==''){

            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        var query = '(';

        /** 
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the bibliographic fields can be added here (fields starting with 'bib.')
         * if you wish to query authority records (fields starting with 'aut.') a separate query and additional php handling will need to be setup
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * for this scenario we have placed an 'all' at every instance
         * 
         * also separating each field query is a boolean logic [and, or, not]
         * for this scenario we have used 'and'
         */

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            query += 'bib.anywhere all "' + this.element.find('#inpt_any').val() + '"';
        }

        // work title field
        if(this.element.find('#inpt_title').val()!=''){

            if(query.length != 1){ // add combination logic (and, or, not)
                query += ' and ';
            }
            query += 'bib.title all "' + this.element.find('#inpt_title').val() + '"';
        }

        // author field
        if(this.element.find('#inpt_author').val()!=''){

            if(query.length != 1){ // add combination logic (and, or, not)
                query += ' and ';
            }
            query += 'bib.author all "' + this.element.find('#inpt_author').val() + '"';
        }

        // date field
        if(this.element.find('#inpt_date').val()!=''){

            if(query.length != 1){ // add combination logic (and, or, not)
                query += ' and ';
            }
            query += 'bib.date all "' + this.element.find('#inpt_date').val() + '"';
        }

        // subject field
        if(this.element.find('#inpt_subject').val()!=''){

            if(query.length != 1){ // add combination logic (and, or, not)
                query += ' and ';
            }
            query += 'bib.subject all "' + this.element.find('#inpt_subject').val() + '"';
        }

        // isbn field
        if(this.element.find('#inpt_isbn').val()!=''){

            if(query.length != 1){ // add combination logic (and, or, not)
                query += ' and ';
            }
            query += 'bib.isbn all "' + this.element.find('#inpt_isbn').val() + '"';
        }

        // Close off and encode query portion, then add to request url
        if(query.length != 1){

            query += ')';
            query = encodeURIComponent(query);

            sURL += '&query=' + query;
        }

        this.toggleCover('Searching...'); // show loading cover

        // for record_lookup.php
        var request = {
            service: sURL, // request url
            serviceType: 'bnflibrary' // requesting service, otherwise no
        };

        // calls /heurist/hsapi/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            that.toggleCover(''); // hide loading cover

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

        var maxRecords = $('#rec_limit').val(); // limit number of returned records
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
                    }else if(map_flds[k]=='description' || map_flds[k]=='subject'){
                        
                        if(window.hWin.HEURIST4.util.isArray(record[map_flds[k]])){
                            values.push(record[map_flds[k]].join(', '));
                        }else if(window.hWin.HEURIST4.util.isObject(record[map_flds[k]])){

                            var str_value = '';
                            for(var key in record[map_flds[k]]){
                                str_value += record[map_flds[k]][key];
                            }

                            values.push(str_value);
                        }else{
                            values.push(record[map_flds[k]]);
                        }
                    }else if(map_flds[k] == 'publisher'){

                        if(record[map_flds[k]]['location'] && record[map_flds[k]]['location'][0] == '['){
                            record[map_flds[k]]['location'] = record[map_flds[k]]['location'].slice(1, -1);
                        }
                        values.push(record[map_flds[k]]);
                    }else if(map_flds[k] == 'date'){
                        values.push(record[map_flds[k]].replace(/\D/g, '')); // Strip non-Numeric chars
                    }else{ // just add field details
                        values.push(record[map_flds[k]]);
                    }
                }

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){
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
        }
    },

    // Simple coverall used during API search request, covers entire dialog popup
    toggleCover: function(text=''){

        var ele = this._as_dialog.parent().find('div.coverall-div');

        if(ele.length > 0){

            if(ele.is(':visible')){
                ele.hide();
            }else{
                ele.show();
            }
        }else{
            var ele_parent = this._as_dialog.parent();

            ele = $('<div>').addClass('coverall-div').css('zIndex', 60000)
                            .append('<span style="left:30px;top:45px;position:absolute;color:white;font-size:20px;">'+ text +'</span>')
                            .appendTo(ele_parent);
        }

    }
});