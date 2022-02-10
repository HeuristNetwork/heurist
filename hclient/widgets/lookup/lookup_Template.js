/**
* lookup_Template.js - Lookup values in third-party web service for Heurist record 
* 
*   It consists of search form and result list to select one or several values of record
* 
*       descendants of this widget 
*   1) perform search on external third-part web service
*   2) render result in our resultList (custom record renderer, found at hclient/widget/viewers/resultList.js)
*   3) map external results with our field details (see options.mapping)
*   4) either returns these mapped fields (to edit record form) 
*       or trigger addition of new record with selected values
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

            if(window.hWin.HEURIST4.util.isArray(s)){
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

                    var val_isArray = window.hWin.HEURIST4.util.isArray(val);
                    var val_isObject = window.hWin.HEURIST4.util.isObject(val);

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

                        var search_val = '';

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

                        if(!res['ext_url']){
                            res['ext_url'] = '<a href="'+ recset.fld(rec, 'biburl') +'" target="_blank">View BnF Record</a>'
                        }
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

            if(new_terms.length > 0){
                this.closingAction(res, 'term', new_terms); // user prepares term, checks if entered term exists (creates a new term, if it doesn't exist)
            }else{
                this.closingAction(res, 'save');
            }
        }
    },


    /**
     * Perform final actions (e.g. create new terms for enum fields) before exiting popup
     * 
     * Param:
     *  dlg_reponse (json) => mapped values to fields
     *  action (string) => which action to perform ('save', 'recpointer', 'term')
     *  new_terms (array) => contains basic data used to help users create/correct terms ([0 => vocab_ID, 1 => dty_ID, 2 => retrieved term label])
     */
    closingAction: function(dlg_response, action = 'save', new_terms){

        var that = this;

        if(window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        if(action == 'save'){ // send response and close dialog

            if(this.added_terms){ // update terms cache, if new terms have been created

                window.hWin.HAPI4.EntityMgr.refreshEntityData(['defTerms'], function(success){

                    if(success){

                        that.added_terms = false;
                        that.closingAction(dlg_response, 'save', null);
                    }
                });
            }else{

                // Pass mapped values back and close dialog
                this._context_on_close = dlg_response;
                this._as_dialog.dialog('close');
            }
        }else if(new_terms && new_terms.length > 0){ // create new terms / rename returned term, user input

            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
            var cur_details = new_terms.shift();

            var $dlg;

            // Term Dlg - Content
            var msg = 'You are creating a new term for the <strong>' + $Db.rst(this.options.mapping.rty_ID, cur_details[1], 'rst_DisplayName') + '</strong> field.<br><br>'
                    + 'New Term: <input type="text" id="new_term_label" value="'+ cur_details[2] +'"> <br><br>'
                    + 'Please correct the term above, as required, before clicking Insert term<br>(you can type an existing term or a correction)';

            // Term Dlg - Button
            var btn = {};
            btn['Insert term'] = function(){

                var new_label = $dlg.find('input#new_term_label').val();
                var term_Ids = $Db.trm_TreeData(cur_details[0], 'set');
                var savedTerm = false;

                // Check if entered term exists within vocab
                for(var i=0; i<term_Ids.length; i++){

                    var trm_Label = $Db.trm(term_Ids[i], 'trm_Label').toLowerCase();

                    if(new_label.toLowerCase() == trm_Label){

                        dlg_response[cur_details[1]] = term_Ids[i];
                        $dlg.dialog('close');
                        savedTerm = true;

                        break;
                    }
                }

                if(savedTerm){

                    if(new_terms.length <= 0){
                        action = 'save';
                    }
                    that.closingAction(dlg_response, action, new_terms);
                }else{ // Create new term

                    var request = {
                        'a': 'save',
                        'entity': 'defTerms',
                        'request_id': window.hWin.HEURIST4.util.random(),
                        'fields': {'trm_ID': -1, 'trm_Label': new_label, 'trm_ParentTermID': cur_details[0]},
                        'isfull': 0
                    };

                    window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                        if(response.status == window.hWin.ResponseStatus.OK){

                            dlg_response[cur_details[1]] = response.data[0]; // response.data[0] == new term id

                            if(new_terms.length <= 0){
                                action = 'save';
                            }

                            that.added_terms = true; // for determining if we need to update the cached terms, a new term has been added so an update is needed

                            $dlg.dialog('close');
                            that.closingAction(dlg_response, action, new_terms);
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                }
            };

            // Create dlg
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btn, {title: 'Unknown term', yes: 'Insert term'}, {default_palette_class: 'ui-heurist-design'});
        }else{ // Error request
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
    }
});