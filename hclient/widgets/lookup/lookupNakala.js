/**
* lookupNakala.js - Search Nakala public records
* 
*   It consists of search form and result list to select one or several values of record
*   1) perform a search request on Nakala's API (api.nakala.fr/search)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
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

$.widget( "heurist.lookupNakala", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 700,
        width:  850,
        modal:  true,
        
        title:  "Search the publically available Nakala records",
        
        htmlContent: 'lookupNakala.html',
        helpContent: null, //in context_help folder

        mapping: null, //configuration from record_lookup_config.json
        
        pagesize: 20 // result list's number of records per page
    },

    recordList: null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        var that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'120px', 'min-width':'120px', display: 'inline-block'});
        this.element.find('#search_container > div > div > .header.optional').css({width:'60px', 'min-width':'60px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left bottom', at: 'right bottom', of: '#search_container'});
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

        var request = {
            serviceType: 'nakala_get_metadata' // file types used by Nakala
        };
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

            data = window.hWin.HEURIST4.util.isJSON(data);

            if(data.status && data.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(data);
                return;
            }

            let $select = that.element.find('#inpt_type');
            if(data.hasOwnProperty('types')){
                $.each(data['types'], (code, label) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], code, label);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_type"]').hide();
            }

            $select = that.element.find('#inpt_license');
            if(data.hasOwnProperty('licenses')){
                $.each(data['licenses'], (idx, license) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], license, license);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_license"]').hide();
            }

            $select = that.element.find('#inpt_year');
            if(data.hasOwnProperty('years')){
                $.each(data['years'], (idx, year) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], year, year);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_year"]').hide();
            }
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

            if(window.hWin.HEURIST4.util.isempty(s) && s !== ''){
                s = '';
            }

            if(window.hWin.HEURIST4.util.isArray(s)){
                s = s.join('; ');
            }else if(window.hWin.HEURIST4.util.isObject(s)){
            	s = Object.values(s).join('; ');
            }

            title = window.hWin.HEURIST4.util.htmlEscape(s ? s : '');

            if(fldname == 'rec_url'){ // create anchor tag for link to external record
                s = '<a href="' + s + '" target="_blank"> view record </a>';
                title = 'View Nakala record';
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

        var recTitle = fld('author', 40) + fld('date', 12) + fld('title', 85) + fld('rec_url', 12); 

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
    doAction: function(recset){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        var that = this;

        if(!recset){
            // get selected recordset
            recset = this.recordList.resultList('getSelected', false);
        }

        if(recset && recset.length() == 1){

            var res = {};
            var rec = recset.getFirstRecord(); // get selected record

            var map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

            // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
            for(var k=0; k<map_flds.length; k++){

                var dty_ID = this.options.mapping.fields[map_flds[k]];
                var val = recset.fld(rec, map_flds[k]);
                var field_type = $Db.dty(dty_ID, 'dty_Type');

                if(val != null){

                    if(field_type == 'resource' && !res['ext_url']){
                        res['ext_url'] = recset.fld(rec, 'rec_url');
                    }

                    // Match term labels with val, need to return the term's id to properly save its value
                    if(field_type == 'enum'){

                        if(window.hWin.HEURIST4.util.isObject(val)){ 
                            val = Object.values(val);
                        }

                        var vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
                        var term_Ids = $Db.trm_TreeData(vocab_ID, 'set');

                        for(var i=0; i<term_Ids.length; i++){

                            var trm_Label = $Db.trm(term_Ids[i], 'trm_Label').toLowerCase();

                            if(window.hWin.HEURIST4.util.isArray(val)){ // multiple values

                                for(var j = 0; j < val.length; j++){

                                    if(val[j].toLowerCase() == trm_Label){
                                        val[j] = term_Ids[i];
                                    }
                                }
                            }else if(val){ // In case of one single value
                                
                                if(val.toLowerCase() == trm_Label){
                                    val = term_Ids[i];
                                }
                            }
                        }
                    }
                }

                // Check that val and id are valid, add to response object
                if(dty_ID>0 && val){

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
    closingAction: function(dlg_response){

        var that = this;

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

        // Construct base url for external request
        var sURL = 'https://api.nakala.fr/search?q='; // base URL for Nakala request
        var filter_query = 'scope=datas'; // no collections
        
        // Construct query portion of url
        // any field
        if(this.element.find('#inpt_any').val()!=''){
            sURL += encodeURIComponent(this.element.find('#inpt_any').val());
        }

        if(this.element.find('#inpt_license').val() != 'all'){
            filter_query += ';license=' + this.element.find('#inpt_license').val();
        }
        if(this.element.find('#inpt_year').val() != 'all'){

            let years = this.element.find('#inpt_year').val();
            if(years.length > 4){
                if(years.indexOf(',') === -1 && years.indexOf(' ') === -1){
                    years = years.replace(/.{4}/g, '$&,');
                }
                if(years.indexOf(',') === -1){
                    years = years.replaceAll(' ', ',');
                }
                if(years.indexOf(', ') !== -1){
                    years = years.replaceAll(', ', ',');
                }
            }
            filter_query += ';year=' + years;
        }
        if(this.element.find('#inpt_type').val() != 'all'){

            let type = this.element.find('#inpt_type').val();

            if(type.indexOf('http') === -1){
                type = 'http://purl.org/coar/resource_type/' + type;
            }

            filter_query += ';type=' + type;
        }

        if(filter_query != ''){
            sURL += '&fq=' + encodeURIComponent(filter_query);
        }

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && filter_query == ''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search field or select a filter...', 1000);
            return;
        }

        let maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;
        sURL += '&size=' + maxRecords;

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // show loading cover

        // for record_lookup.php
        var request = {
            service: sURL, // request url
            serviceType: 'nakala_search' // requesting service, otherwise the request will result in an error
        };

        // calls /heurist/hsapi/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            if(window.hWin.HEURIST4.util.isJSON(response)){

                if(response.records != null && response.count > 0){ // Search result
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

        if (json_data && json_data.records && json_data.records.length > 0) {

            var res_records = {}, res_orders = [];

            // Prepare fields for mapping
            var fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
            var map_flds = Object.keys(this.options.mapping.fields).concat('rec_url');
            fields = fields.concat(map_flds);

            // Parse json to Record Set
            var i=0;
            for(const recID in json_data.records){

                var record = json_data.records[recID];
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

            if(json_data.count > maxRecords){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    "There are " + json_data.count + " records satisfying these criteria, only the first "+ maxRecords +" are shown.<br>Please narrow your search.",
                );
            }
        }

        if(is_wrong_data){
            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr('Service did not return data in an appropriate format');
        }
    }
});