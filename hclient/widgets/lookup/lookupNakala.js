/**
* lookupNakala.js - Search Nakala public records
* 
*   It consists of search form and result list to select one or several values of record
*   1) perform a search request on Nakala's API (api.nakala.fr/search)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
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

$.widget( "heurist.lookupNakala", $.heurist.lookupBase, {

    // default options
    options: {

        height: 700,
        width:  850,

        title:  "Search the publically available Nakala records",

        htmlContent: 'lookupNakala.html'
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        let that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'120px', 'min-width':'120px', display: 'inline-block'});
        this.element.find('#search_container > div > div > .header.optional').css({width:'60px', 'min-width':'60px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left bottom', at: 'right bottom', of: '#search_container'});

        let request = {
            serviceType: 'nakala',
            service: 'nakala_get_metadata' // file types used by Nakala
        };
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

            data = window.hWin.HEURIST4.util.isJSON(data);

            if(data.status && data.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(data);
                return;
            }

            let $select = that.element.find('#inpt_type');
            if(Object.hasOwn(data,'types')){
                $.each(data['types'], (idx, type) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], type[1], type[0]);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_type"]').hide();
            }

            $select = that.element.find('#inpt_license');
            if(Object.hasOwn(data,'licenses')){
                $.each(data['licenses'], (idx, license) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], license, license);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_license"]').hide();
            }

            $select = that.element.find('#inpt_year');
            if(Object.hasOwn(data,'years')){
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

            if(window.hWin.HEURIST4.util.isempty(s) && s !== ''){
                s = '';
            }

            s = window.hWin.HEURIST4.util.isObject(s) ? Object.values(s) : s;
            s = Array.isArray(s) ? s.join('; ') : s;

            let title = window.hWin.HEURIST4.util.htmlEscape(s ? s : '');

            if(fldname == 'rec_url'){ // create anchor tag for link to external record
                s = `<a href="${s}" target="_blank" rel="noopener"> view record </a>`;
                title = 'View Nakala record';
            }
            
            if(width>0){
                s = `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
            }
            return s;
        }

        const recTitle = fld('author', 40) + fld('date', 12) + fld('title', 85) + fld('rec_url', 12);
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

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};
        res['ext_url'] = recset.fld(record, 'rec_url');

        res = this.prepareValues(recset, record, res);

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

        // Construct base url for external request
        let sURL = 'https://api.nakala.fr/search?q='; // base URL for Nakala request
        let filter_query = 'scope=datas'; // no collections
        
        // Construct query portion of url
        // any field
        if(this.element.find('#inpt_any').val()!=''){
            sURL += encodeURIComponent(this.element.find('#inpt_any').val());
        }

        if(this.element.find('#inpt_license').val() != 'all'){
            filter_query += `;license=${this.element.find('#inpt_license').val()}`;
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
            filter_query += `;year=${years}`;
        }
        if(this.element.find('#inpt_type').val() != 'all'){

            let type = this.element.find('#inpt_type').val();

            if(type.indexOf('http') === -1){
                type = `http://purl.org/coar/resource_type/${type}`;
            }

            filter_query += `;type=${type}`;
        }

        if(filter_query != ''){
            sURL += `&fq=${encodeURIComponent(filter_query)}`;
        }

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && filter_query == ''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search field or select a filter...', 1000);
            return;
        }

        let maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;
        sURL += `&size=${maxRecords}`;

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // show loading cover

        // for record_lookup.php
        let request = {
            service: sURL, // request url
            serviceType: 'nakala' // requesting service, otherwise the request will result in an error
        };

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){ // Error return
                window.hWin.HEURIST4.msg.showMsgErr(response);
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

        let maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if(!json_data || !Object.hasOwn('records', json_data) || Object.keys(json_data.records).length == 0){
            this._super(Object.keys(json_data.records).length == 0 ? null : false);
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        let map_flds = Object.keys(this.options.mapping.fields).concat('rec_url');
        fields = fields.concat(map_flds);

        // Parse json to Record Set
        for(const recID in json_data.records){

            let record = json_data.records[recID];
            let values = [recID, this.options.mapping.rty_ID];

            // Add current record details, field by field
            for(const fld_Name of map_flds){
                values.push(record[fld_Name]);
            }

            res_orders.push(recID);
            res_records[recID] = values;
        }

        if(json_data.count > maxRecords){
            window.hWin.HEURIST4.msg.showMsgDlg(
                `There are ${json_data.count} records satisfying these criteria, only the first ${maxRecords} are shown.<br>Please narrow your search.`
            );
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});