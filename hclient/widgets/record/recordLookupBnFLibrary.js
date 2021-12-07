/**
* recordLookupBnFLibrary.js - Searching the BnF Library's bibliographic records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (recordLookupBnFLibrary.html), and
*   2) Performs an api call to the BnF Library's Search API using the User's input, displaying the results within a Heurist result list
* 
* NOTE: This external lookup does not currently return ANY record ids for resources (record pointers)/relationship markers,
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
               
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'65px', 'min-width':'65px', display: 'inline-block'});
        this.element.find('#separate_fields > .header.recommended').css({width:'65px', 'min-width':'65px'});
        this.element.find('#btn_container').position({my: 'left center', at: 'right center', of: '#search_container'});
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
               
               pagesize:(this.options.pagesize>0) ? this.options.pagesize : 100, // number of records to display per page
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

            if(window.hWin.HEURIST4.util.isArray(s)){
                s = window.hWin.HEURIST4.util.htmlEscape(s.join('; '));
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

		// Here we construct the details of the result row, 
		// in this case it will show work creator/s, release Year, work title, and a url that links to the bib on the BnF site
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

                        if(map_flds[k] == 'language' && val == 'Sans contenu linguistique'){ // No language
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
                            }
                        }
                    }else if(field_type == 'resource'){ // need to match the value(s) with a record, this is by far the most problematic

                        var ptr_Ids = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs'); // record type ids (listed as "1,2,3,...")
                        
                        recpointer_dtyids.push(dty_ID);
                        recpointer_rtyids.push(ptr_Ids);
                        // Alter string here for searching in database, string's default format: Other Name/s and Last Name, First Name (Starting Year-Ending Year)
                        recpointer_searchvals.push(val);
                    }
                }

                if(dty_ID>0 && val){
                    res[dty_ID] = val;    
                }
            }

            // Pass mapped values back and close dialog
            this._context_on_close = res;
            this._as_dialog.dialog('close');

            /* UNDER DEVELOPMENT - Retrieve record ids for record pointer fields
            if(recpointer_rtyids.length > 0){

                var request = {
                    recIds: recpointer_rtyids,
                    searchValues: recpointer_searchvals,
                    searchByTitleMask: true // whether to compare the search values with the title mask, or each record's details
                };

                // Match record pointers here, or earlier in another function if a server call is needed
                window.hWin.HAPI4.RecordMgr.get_record_ids(request, function(response){
                    console.log(response);

                    var rec_ids = response.data;

                    // Assign record ids from response to mapped fields
                    for(var i = 0; i < recpointer_dtyids.length; i++){
                        
                        if(!window.hWin.HEURIST4.util.isempty(rec_ids[i])){
                            res[recpointer_dtyids[i]] = rec_ids[i];
                        }else{
                            res[recpointer_dtyids[i]] = null;
                        }
                    }

                    // Pass mapped values back and close dialog
                    //that._context_on_close = res;
                    //that._as_dialog.dialog('close');
                });
            }else{

                // Pass mapped values back and close dialog
                //this._context_on_close = res;
                //this._as_dialog.dialog('close');
            }*/

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

        var maxRecords = 100; // limit number of returned records
        var sURL = 'http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=dublincore&maximumRecords='+maxRecords+'&startRecord=1'; // base URL

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
         * for this scenario we have placed an 'all' at every instance, this could be replaced with a dropdown in the input form
         * 
         * also separating each field query is a boolean logic [and, or, not]
         * for this scenario we have used 'and', this could also be replaced with a dropdown in the input form
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
            }else{ // just in case
				window.hWin.HEURIST4.msg.showMsgErr("The system appears to have encountered an issue,<br>"
					+ "please contact the Heurist team if this problem persists.");
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

                    // Special handling for url + isbn (both listed as identifier in json), 
                    // need to push null (into array values) if there are no values for an index otherwise things will be misplaced
                    if(map_flds[k]=='biburl'){
                        if(record['identifier'] != null){

                            // The url is either the only identifier field or the first one
                            if(window.hWin.HEURIST4.util.isArray(record['identifier'])){
                                values.push(record['identifier'][0]);
                            }else{
                                values.push(record['identifier']);
                            }
                        }else{
                            values.push(null);
                        }
                    }else if(map_flds[k]=='isbn'){
                        if(record['identifier'] != null){

                            // The ISBN is only the second identifier, after the url
                            if(window.hWin.HEURIST4.util.isArray(record['identifier']) && record['identifier'].length > 1){
                                values.push(record['identifier'][1]);
                            }else{
                                values.push(null);
                            }
                        }else{
                            values.push(null);
                        }
                    }else if(map_flds[k]=='rights'){
                        // If the value is an array; 0 => French, 1 => English
                        if(record[map_flds[k]] != null){

                            var is_Array = window.hWin.HEURIST4.util.isArray(record[map_flds[k]]);
                            if(is_Array){
                                values.push(record[map_flds[k]][0]);
                            }else if(!window.hWin.HEURIST4.util.isempty(record[map_flds[k]])){
                                values.push(record[map_flds[k]]);
                            }else{
                                values.push(null);
                            }
                        }else{
                            values.push(null);
                        }
                    }else if(map_flds[k]=='description' || map_flds[k]=='subject'){

                        if(record[map_flds[k]] != null){

                            var is_Array = window.hWin.HEURIST4.util.isArray(record[map_flds[k]]);
                            // If the description is an array: 0 => regarding the Work, 1 => Commerical Number, 2 => Commerical Barcode
                            if(is_Array){
                                values.push(record[map_flds[k]].join('. '));
                            }else if(!window.hWin.HEURIST4.util.isempty(record[map_flds[k]])){
                                values.push(record[map_flds[k]]);
                            }else{
                                values.push(null);
                            }
                        }else{
                            values.push(null);
                        }
                    }else{ // just add field details

                        if(window.hWin.HEURIST4.util.isArray(record[map_flds[k]])){ // check and remove duplicates

                            var tracker = {};
                            var filtered_values = [];

                            for(var j = 0; j < record[map_flds[k]].length; j++){

                                var value = record[map_flds[k]][j];

                                if(window.hWin.HEURIST4.util.isempty(value) || window.hWin.HEURIST4.util.isObject(value)){ // Ignore empty values
                                    continue;
                                }

                                if(map_flds[k] == 'creator' || map_flds[k] == 'contributor'){ // Remove contribution specification, e.g. Writer, Speaker
                                    value = value.substring(0, value.indexOf('.'));
                                }

                                if(!tracker[value]){

                                    tracker[value] = true;

                                    filtered_values.push(value);
                                }
                            }

                            if(filtered_values.length > 0){
                                values.push(filtered_values);
                            }else{
                                values.push(null);
                            }
                        }else{

                            if(record[map_flds[k]] != null && (map_flds[k] == 'creator' || map_flds[k] == 'contributor')){ // Remove contribution specification, e.g. Writer, Speaker
                                values.push(record[map_flds[k]].substring(0, record[map_flds[k]].indexOf('.')));
                            }else{
                                values.push(record[map_flds[k]]);
                            }
                        }
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

            if(json_data.numberOfRecords > 100){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    "There are " + json_data.numberOfRecords + " records satisfying these criteria, only the first 100 are shown.<br>Please narrow your search.",
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