/**
* lookupTLC.js - Lookup values in third-party web service for Heurist record 
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupTLC.html)
*   2) Performs an api call to the Geoname service using the User's input, displaying the results within a Heurist result list
*   3) map external results with our field details (see options.mapping) and returns the mapped results to the record edit form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global stringifyMultiWKT */

$.widget( "heurist.lookupTLC", $.heurist.lookupBase, {

    // dialog options, the default values and other available options can be found in hclient/widget/record/recordAction.js
    options: {

        height: 520,
        width:  800,

        title:  'Lookup values for Heurist record', // dialog title

        htmlContent: 'lookupTLC.html', // in hclient/widgets/lookup folder
        helpContent: 'lookupTLC.html' // in context_help folder
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        this.element.find('fieldset > div > .header').css({width:'80px','min-width':'80px'});

        // adding additional event listeners
        this._on(this.element.find('#btnStartSearch').button(),{
            click: this._doSearch
        });

        return this._super();
    },

    //
    // Record Renderer
    // Defines how a record is rendered within the list, customised via the recTitle variable within
    // Field names are those found within this.options.mapping.fields
    //
    _rendererResultList: function(recordset, record){

        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            if(fldname == 'properties.LGA'){ 
                s = s.lga; 
            }

            s = s ? s : '';
            let title = s;

            if(fldname == 'recordLink'){
                s = `<a href="${s}" target="_blank"> view here </a>`;
                title = 'View tlcmap record';
            }

            if(width>0){
                s = `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
            }
            return s;
        }

        const recTitle = fld('properties.placename',40) + fld('properties.LGA', 25) + fld('properties.state', 6) + fld('properties.description', 65) + fld('recordLink', 12); 
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    //
    // Either return mapped fields to record or create a new record using the mapped fields
    //
    doAction: function(){

        // retrieve selected record/s
        let sel = this.recordList.resultList('getSelected', false);
        if(!sel || sel.length() != 1){
            return;
        }

        if(this.options.add_new_record){

            //create new record 
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

            let rectype_id = (!window.hWin.HEURIST4.util.isempty(this.options.rectype_for_new_record)) ? this.options.rectype_for_new_record : this.options.mapping.rty_ID;

            this._addNewRecord(rectype_id, sel);                     
        }else{
            //pass mapped values and close dialog
            this.closingAction(sel);
        }
    },
    
    //
    // create search url
    // perform search and call result handler
    //
    _doSearch: function(){
        
        let sURL;

        // get base url
        if(this.options.mapping.service=='tlcmap'){
            sURL = 'https://tlcmap.org/ghap/search?format=json&paging=100';
        }else if(this.options.mapping.service=='tlcmap_old'){
            sURL = 'https://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?format=json&paging=100';  
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Name of service not defined...', 500);
            return;
        }

        // check for input
        if(this.element.find('#inpt_name').val()=='' && this.element.find('#inpt_anps_id').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Define name or ANPS ID...', 500);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // cover screen

        // add input to request url
        if(this.element.find('#inpt_name').val()!=''){
            sURL += `&${this.element.find('#inpt_exact').is(':checked')?'name':'fuzzyname'}=${encodeURIComponent(this.element.find('#inpt_name').val())}`;
        }
        if(this.element.find('#inpt_anps_id').val()!=''){
            sURL += `&anps_id=${this.element.find('#inpt_anps_id').val()}`;
        }
        if(this.element.find('#inpt_lga').val()!=''){
            sURL += `&lga=${encodeURIComponent(this.element.find('#inpt_lga').val())}`;
        }
        if(this.element.find('#inpt_state').val()!=''){
            sURL += `&state=${this.element.find('#inpt_state').val()}`;
        }

        let that = this;
        let request = {service:sURL, serviceType: 'tlcmap'};             

        // performing request - see controller hserv/controller/record_lookup.php for service external lookups
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request,
            function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack(); // remove cover

                if(response){
                    // check response
                    if(response.status && response.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._onSearchResult(response); // begin processing
                    }
                }
            }
        );
    },

    /**
     * Prepare json for displaying via the Heuirst resultList widget
     *
     * @param {json} json_data - search response
     */
    _onSearchResult: function(geojson_data){

        if(!window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){
            this._super(false);
        }

        let res_records = {}, res_orders = [];

        let DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];

        // Retrieve fields for records, any additional fields that are not part of the new record (e.g. url to original record) can be added here
        let fields = ['rec_ID','rec_RecTypeID'];
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);
        fields = fields.concat('recordLink');

        for(const idx in map_flds){
            map_flds[idx] = map_flds[idx].split('.'); 
        }

        if(!geojson_data.features) geojson_data.features = geojson_data;

        // Parse GeoJSON, special handling can be done here (e.g. creating Geo Objects for Geospatial fields)
        let i = 0;
        for(const feature of geojson_data.features){

            let recID = i++;

            let hasGeo = false;
            let values = [recID, this.options.mapping.rty_ID];
            for(const fld_Name of map_flds){

                let val = feature[ fld_Name[0] ];

                for(const fld_Part of fld_Name){
                    if(val && !window.hWin.HEURIST4.util.isnull( val[ fld_Part ])){
                        val = val[fld_Part];
                    }
                }      

                // Special handling for Geo Objects
                if(DT_GEO_OBJECT == this.options.mapping.fields[fld_Name]){
                    if(!window.hWin.HEURIST4.util.isempty(val)){
                        val = {"type": "Feature", "geometry": val};
                        let wkt = stringifyMultiWKT(val);    
                        if(window.hWin.HEURIST4.util.isempty(wkt)){
                            val = '';
                        }else{
                            //@todo the same code mapDraw.php:134
                            let typeCode = 'm';
                            if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
                                if(wkt.indexOf('LINESTRING')>=0){
                                    typeCode = 'l';
                                }else if(wkt.indexOf('POLYGON')>=0){
                                    typeCode = 'pl';
                                }else {
                                    typeCode = 'p';
                                }
                            }
                            val = `${typeCode} ${wkt}`;
                            hasGeo = true;
                        }
                    }
                }
                values.push(val); // push value into record
            }

            // creating the additional recordLink field
            values.push(`https://tlcmap.org/ghap/search?id=${feature['properties']['id']}`);

            if(hasGeo){
                // Add record into recordset
                res_orders.push(recID);
                res_records[recID] = values;    
            }
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});

