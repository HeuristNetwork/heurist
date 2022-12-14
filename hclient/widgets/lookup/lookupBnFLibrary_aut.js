/**
* lookupBnFLibrary_aut.js - Searching the BnF Library's authoritative records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupBnFLibrary_aut.html), and
*   2) Performs an api call to the BnF Library's Search API using the User's input, displaying the results within a Heurist result list
* 
* After record selection, the user:
*   Is required to select or create a record for each record pointer field, with a value
*   Is required to enter or correct any terms for each enumerated field, with a value 
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

$.widget( "heurist.lookupBnFLibrary_aut", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 750,
        width:  530,
        modal:  true,
        
        title:  "Search the Bibliothèque nationale de France's authoritative records",
        
        htmlContent: 'lookupBnFLibrary_aut.html',
        helpContent: null, //in context_help folder

        mapping: null, //configuration from record_lookup_config.json
               
        add_new_record: false, //if true it creates new record on selection
        
        pagesize: 20 // result list's number of records per page
    },
    
    recordList: null,

    tabs_container: null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        var that = this;

        // Extra field styling
        this.element.find('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'}).addClass('truncate');
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '2.5px'});

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

        this.tabs_container = this.element.find('#tabs-cont').tabs();
        this.element.find('#inpt_any').focus();

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

            s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');

            title = s;

            if(fldname == 'auturl'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View authoritative record';
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

        var recTitle = fld('name', 75) + fld('auturl', 12); 

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
     * Param: None
     */
    doAction: function(){

        this.toggleCover('Processing Selection...');

        var that = this;

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
                        res['ext_url'] = recset.fld(rec, 'auturl');
                    }

                    var val_isArray = window.hWin.HEURIST4.util.isArray(val);
                    var val_isObject = window.hWin.HEURIST4.util.isObject(val);

                    // Match term labels with val, need to return the term's id to properly save its value
                    if(field_type == 'enum'){

                        if(val_isObject){ 
                            val = Object.values(val);
                        }else if(!val_isArray){
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

                        // Check if a value was found, if not prepare for creating new term
                        if(!term_found){
                            new_terms.push(val);
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

                            if(complete_val != ''){
                                complete_val += ', ';
                            }
                            complete_val += val[key];
                        }
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

        /**
         * recordSchema: XML structure for record details (changing this will require changes to the php code in record_lookup.php)
         * maximumRecords: maximum number of records returned from the search (api default: 100)
         * startRecord: starting point, complete searches in batches (api default: 1)
         * query: encoded string enclosed in brackets (at minimum, the spaces MUST be encoded)
         */

        //var recordType = $('#inpt_doctype').val(); // which record type is requested
        var maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        var sURL = 'http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=unimarcxchange&maximumRecords='+maxRecords+'&startRecord=1'; // base URL

        var accesspointHasValue = this.element.find('#inpt_accesspoint').val() != '';
        var typeHasValue = this.element.find('#inpt_type').val() != '';
        var isniHasValue = this.element.find('#inpt_isni').val() != '';
        var isnidateHasValue = this.element.find('#inpt_isnidate').val() != '';
        var domainHasValue = this.element.find('#inpt_domain').val() != '';
        var recidHasValue = this.element.find('#inpt_recordid').val() != '';

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && !accesspointHasValue && !typeHasValue && !isniHasValue && !isnidateHasValue && !domainHasValue && !recidHasValue){

            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        var query = '(';

        /** 
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the authoritative fields can be added here (fields starting with 'aut.')
         * if you wish to query bibliographic records (fields starting with 'bib.'), we suggest the alternative BnF lookup available (lookupBnFLibrary_bib)
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * 
         * also separating each field query is a boolean logic [and, or, not]
         */

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            query += 'aut.anywhere '+ this.element.find('#inpt_any_link').val() +' "' + this.element.find('#inpt_any').val() + '"';

            if(accesspointHasValue || typeHasValue || isniHasValue || isnidateHasValue || domainHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_any_logic').val() + ' ';
            }
        }

        // access point field
        if(accesspointHasValue){

            query += 'aut.accesspoint '+ this.element.find('#inpt_accesspoint_link').val() +' "' + this.element.find('#inpt_accesspoint').val() + '"';

            if(typeHasValue || isniHasValue || isnidateHasValue || domainHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_accesspoint_logic').val() + ' ';
            }
        }

        // type field
        if(typeHasValue){

            query += 'aut.type '+ this.element.find('#inpt_type_link').val() +' "' + this.element.find('#inpt_type').val() + '"';

            if(isniHasValue || isnidateHasValue || domainHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_type_logic').val() + ' ';
            }
        }

        // isni field
        if(isniHasValue){

            query += 'aut.isni '+ this.element.find('#inpt_isni_link').val() +' "' + this.element.find('#inpt_isni').val() + '"';

            if(isnidateHasValue || domainHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_isni_logic').val() + ' ';
            }
        }

        // isni date field
        if(isnidateHasValue){

            query += 'aut.isnidate '+ this.element.find('#inpt_isnidate_link').val() +' "' + this.element.find('#inpt_isnidate').val() + '"';

            if(domainHasValue || recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_isnidate_logic').val() + ' ';
            }
        }

        // domain field
        if(domainHasValue){

            query += 'aut.domain '+ this.element.find('#inpt_domain_link').val() +' "' + this.element.find('#inpt_domain').val() + '"';

            if(recidHasValue){ // add combination logic
                query += ' ' + this.element.find('#inpt_domain_logic').val() + ' ';
            }
        }

        // record id field
        if(recidHasValue){
            query += 'aut.recordid '+ this.element.find('#inpt_recordid_link').val() +' "' + this.element.find('#inpt_recordid').val() + '"';
            // no combination logic as record id is the last field
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
            serviceType: 'bnflibrary_aut' // requesting service, otherwise no
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
                    values.push(record[map_flds[k]]);
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
        }else{
            this.tabs_container.tabs('option', 'active', 1); // switch to results tab
        }
    },

    // Simple coverall used during API search request, covers entire dialog
    toggleCover: function(text=''){

        var ele = this._as_dialog.parent().find('div.coverall-div');

        if(ele.length > 0){

            if(ele.is(':visible') && window.hWin.HEURIST4.util.isempty(text)){
                ele.hide();
            }else{

                if(text != ''){
                    ele.find('span').text(text);
                }

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