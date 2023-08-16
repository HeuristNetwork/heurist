/**
* lookup_Template.js - Lookup values in third-party web service for Heurist record 
* 
*   It consists of search form and result list to select one or several values of record
* 
*       descendants of this widget 
*   1) perform search on external third-part web service (see manageRecords.js _setupExternalLookups)
*   2) render result in our resultList (custom record renderer, found at hclient/widget/viewers/resultList.js)
*   3) map external results with our field details (see options.mapping)
*   4) either returns these mapped fields (to edit record form) 
*       or trigger addition of new record with selected values
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

$.widget( "heurist.lookup_Template", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  "Template lookup",
        
        htmlContent: 'lookup_Template.html',
        helpContent: null, //in context_help folder

        mapping: null, //configuration from record_lookup_config.json
               
        add_new_record: false, //if true it creates new record on selection
        
        pagesize: 20 // result list's number of records per page
    },
    
    recordList:null,

    added_terms: false,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        var that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'100px', 'min-width':'100px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left top', at: 'right top', of: '#search_container'});
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
        this.recordList = this.element.find('#div_result');
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

            if(fldname == 'author'){ // special handling for author details

                if(!s){ 
                    return '<div style="display:inline-block;width:'+width+'ex" class="truncate"">No provided creator</div>';
                }

                var creator_val = '';

                for(var idx in s){

                    var cur_string = '';
                    var cur_obj = s[idx];

                    if(cur_obj.hasOwnProperty('firstname') && cur_obj['firstname'] != ''){
                        cur_string = cur_obj['firstname'];
                    }
                    if(cur_obj.hasOwnProperty('surname') && cur_obj['surname'] != ''){
                        cur_string = (cur_string != '') ? cur_obj['surname'] + ', ' + cur_string : cur_obj['surname'];
                    }
                    if(cur_obj.hasOwnProperty('active') && cur_obj['active'] != ''){
                        cur_string += ' (' + cur_obj['active'] + ')';
                    }
                    if(!cur_string){
                        creator_val += 'Missing author; ';
                    }else{
                        creator_val += cur_string + '; ';
                    }
                }

                s = creator_val;
            }else if(window.hWin.HEURIST4.util.isArray(s)){
                s = window.hWin.HEURIST4.util.htmlEscape(s.join('; '));
            }else if(window.hWin.HEURIST4.util.isObject(s)){

            	var display_val = '';

            	for(var key in s){

                    if(display_val != ''){
                        display_val += ', ';
                    }
                    display_val += s[key];
                }

                s = display_val;
            }else{
                s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');
            }

            title = s;

            if(fldname == 'biburl'){ // create anchor tag for link to external record
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

        var recTitle = fld('author', 50) + fld('date', 7) + fld('title', 75) + fld('biburl', 12); 

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

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        var that = this;

        this.added_terms = false;

        // get selected recordset
        var recset = this.recordList.resultList('getSelected', false);

        if(recset && recset.length() == 1){

            var res = {};
            var rec = recset.getFirstRecord(); // get selected record

            var map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

            var new_terms = []; // array of terms that need to be created, [vocab id, detail id, value]

            // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
            for(var k=0; k<map_flds.length; k++){

                var dty_ID = this.options.mapping.fields[map_flds[k]];
                var val = recset.fld(rec, map_flds[k]);
                var field_type = $Db.dty(dty_ID, 'dty_Type');

                if(val != null){

                    if(field_type == 'resource' && !res['ext_url']){
                        res['ext_url'] = recset.fld(rec, 'biburl');
                    }

                    var val_isArray = window.hWin.HEURIST4.util.isArray(val);
                    var val_isObject = window.hWin.HEURIST4.util.isObject(val);

                    var search_val = '';

                    if(map_flds[k] == 'author'){ // special scenario for author field

                        for(var i = 0; i < val.length; i++){

                            search_val = '';

                            var cur_val = val[i];

                            if(cur_val['firstname']){
                                search_val = cur_val['firstname'];
                            }
                            if(cur_val['surname']){
                                search_val = (search_val != '') ? search_val + ' ' + cur_val['surname'] : cur_val['surname'];
                            }
                            if(cur_val['active']){
                                search_val = (search_val != '') ? search_val + ' [' + cur_val['active'] + ']' : 'No Name, years active: ' + cur_val['active'];
                            }
                            if(cur_val['id']){
                                search_val = (search_val != '') ? search_val + ' (id: ' + cur_val['id'] + ')' : 'No Name, id: ' + cur_val['id'];
                            }

                            if(search_val != ''){

                                if(!res[dty_ID]){
                                    res[dty_ID] = [];
                                }

                                res[dty_ID].push(search_val);
                            }
                        }

                        continue;
                    }

                    // Match term labels with val, need to return the term's id to properly save its value
                    if(field_type == 'enum'){

                        if(val_isObject){ 

                            if(Object.keys(val).length > 1){ // should not be a term, or alternative handling required
                                continue;
                            }else{ // take first option
                                val = val[Object.keys(val)[0]];
                            }
                        }

                        var vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
                        var term_Ids = $Db.trm_TreeData(vocab_ID, 'set');

                        var term_found = false;

                        for(var i=0; i<term_Ids.length; i++){

                            var trm_Label = $Db.trm(term_Ids[i], 'trm_Label').toLowerCase();

                            if(val_isArray){ // multiple values, Language usually has two values and Type only has one

                                for(var j = 0; j < val.length; j++){

                                    if(val[j].toLowerCase() == trm_Label){

                                        val = term_Ids[i];
                                        term_found = true;
                                        break;
                                    }
                                }
                            }else if(val){ // In case of one single value
                                
                                if(val.toLowerCase() == trm_Label){

                                    val = term_Ids[i];
                                    term_found = true;
                                    break;
                                }
                            }

                            if(term_found){
                                break;
                            }
                        }

                        // Check if a value was found, if not prepare for creating new term
                        if(!term_found){
                            
                            if(val_isArray){
                                new_terms.push([vocab_ID, dty_ID, val[0]]); 
                            }else{
                                new_terms.push([vocab_ID, dty_ID, val]);
                            }
                        }
                    }else if(field_type == 'resource'){ // prepare search string for user to select/create a record

                        search_val = '';

                        if(val_isObject){

                            for(var key in val){

                                if(search_val != ''){
                                    search_val += ', ';
                                }
                                search_val += val[key];
                            }
                        }else if(val_isArray){
                            search_val = val[key].join(', ');
                        }else{
                            search_val = val;
                        }

                        val = search_val;                        
                    }
                }

                // Check that val and id are valid, add to response object
                if(dty_ID>0 && val){

                    if(!res[dty_ID]){
                        res[dty_ID] = [];
                    }

                    if(window.hWin.HEURIST4.util.isObject(val)){

                        var complete_val = '';
                        for(var key in val){
                            complete_val += val[key] + ', ';
                        }

                        res[dty_ID].push(complete_val.slice(0, -2));
                    }else if(field_type != 'resource' && window.hWin.HEURIST4.util.isArray(val)){
                        res[dty_ID].push(val.join(', '));
                    }else{
                        res[dty_ID].push(val);    
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
    closingAction: function(dlg_response){

        var that = this;

        if(window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        this.toggleCover('');

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

        // Construct base url for external request
        var sURL = 'http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=intermarcxchange&maximumRecords=20&startRecord=1'; // base URL for BnF request

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        var query = '(';

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            query += 'bib.anywhere all "' + this.element.find('#inpt_any').val() + '"';
        }

        // Close off and encode query portion, then add to request url
        if(query.length != 1){

            query += ')';
            query = encodeURIComponent(query);

            sURL += '&query=' + query;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // show loading cover

        // for record_lookup.php
        var request = {
            service: sURL, // request url
            serviceType: 'bnflibrary_bib' // requesting service, otherwise the request will result in an error
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
        /* 
         * Alternatively, you can search Heurist records by a call to window.hWin.HAPI4.RecordMgr.search(request, callback),
         *  where here, request = { q: Heurist query, w: a|b (all or bookmarked), f: format to return, db: which Heurist DB to query }
         * (hclient/core/hapi.js, search: function(request, callback))
         * Examples of this can be found in the MPCE and LRC18C lookups
         */
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
                    }else if(map_flds[k] == 'date' && record['publisher'] && record['publisher'][map_flds[k]]){ // Strip non-Numeric chars from dates
                        values.push(record['publisher'][map_flds[k]].replace(/\D/g, ''));
                    }else{ // just add field details
                        values.push(record[map_flds[k]]);
                    }
                }

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){

                res_records = this.removeDupAuthors(fields.indexOf('author'), res_records); // just removing duplicates

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

    //
    // Just to remove duplicates from the author field
    //
    removeDupAuthors: function(author_key, records){

        if(records == null || !window.hWin.HEURIST4.util.isObject(records) || window.hWin.HEURIST4.util.isempty(author_key)){
            return records;
        }

        for(var i in records){

            var author_details = records[i][author_key];

            if(author_details){

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
    },
});