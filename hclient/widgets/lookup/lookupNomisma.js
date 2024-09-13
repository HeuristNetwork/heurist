/**
* lookupNomisma.js - Searching the Nomisma's records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupNomisma.html), and
*   2) Performs an api call to Nomisma's Search API using the User's input, displaying the results within a Heurist result list
* 
* Current Nomisma services supported:
*    - getMints
*    - getHoards
*    - getFindspots
*    - getRdf (currently unavailable)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2021 University of Sydney
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
/* global stringifyMultiWKT */

$.widget( "heurist.lookupNomisma", $.heurist.lookupBase, {

    // default options
    options: {

        height: 720,
        width:  510,

        title:  'Search Nomisma database of coins and currency via several options',

        htmlContent: 'lookupNomisma.html'
    },

    search_button_selector: '#btnMintSearch, #btnHoardsSearch, #btnFindspotsSearch',

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({'min-width':'65px', display: 'inline-block'});

        this.element.find('#btnRdfSearch').hide();

        // Prepare result list options
        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Results Found<br><br>'
                        + 'This result may also be due to a misconfiguration/failed connection to the Nomisma server.<br>'
                        + 'Please advise the Heurist team if this persists with searches which you are sure should return results.</div>' // For empty results
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

        function fld(fldname, width){

            let s

            if(fldname == 'dates'){
                s = `${recordset.fld(record, 'when.timespans.start')} to ${recordset.fld(record, 'when.timespans.end')}` 
                        + ` (end date: ${recordset.fld(record, 'properties.closing_date')})`;
            }else{
                s = recordset.fld(record, fldname);
            }

            s = s?s:'';
            let title = s;

            if(fldname == 'properties.gazetteer_uri'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View nomisma record';
            }else if(fldname == 'properties.count'){
                s = `(count: ${s})`;
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }
        
        let recTitle = '';
        if(fld('properties.type') == 'hoard'){
            recTitle = fld('properties.type', 10) + fld('label', 30) + fld('dates', 35) + fld('properties.gazetteer_uri', 10);
        }else{
            recTitle = fld('properties.type', 10) + fld('properties.gazetteer_label', 30) + fld('properties.count', 15) + fld('properties.gazetteer_uri', 10); 
        }
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    //
    // Either perform search or select entry in resultList and triggers addition of new record
    //
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        this.closingAction(recset);
    },
    
    //
    // create search url
    // perform search
    //
    _doSearch: function(event){

        let that = this;
        let search_type = $(event.target).val();

        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Enter value to search...', 500);
            return;
        }

        let sURL = '';

        switch(search_type){
            case 'mint':
                sURL = 'https://nomisma.org/apis/getMints?id=';
                break;
            case 'hoard':
                sURL = 'https://nomisma.org/apis/getHoards?id=';
                break;
            case 'findspots':
                sURL = 'https://nomisma.org/apis/getFindspots?id=';
                break;
            default:
                return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        sURL += encodeURI(this.element.find('#inpt_any').val());

        let request = {service:sURL, serviceType:'nomisma', 'search_type': search_type};

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request,
            function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){ // Error return
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }
    
                that._onSearchResult(response);
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

        let fields = ['rec_ID','rec_RecTypeID'];
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);

        for(const idx in map_flds){
            map_flds[idx] = map_flds[idx].split('.'); 
        }

        if(!geojson_data.features) geojson_data.features = geojson_data;

        //parse json
        let i = 0;
        for(const feature of geojson_data.features){

            let recID = i++;
            
            let hasGeo = false;
            let values = [recID, this.options.mapping.rty_ID];
            for(const fld_Names of map_flds){

                let val = feature[fld_Names[0]];

                if(fld_Names[0].startsWith('when') && val){

                    if(fld_Names[2].startsWith('start') && val['timespans']){
                        val = val['timespans'][0]['start'];
                    }else if(fld_Names[2].startsWith('end') && val['timespans']){
                        val = val['timespans'][0]['end'];
                    }
                }

                for(const part of fld_Names){
                    if(val && !window.hWin.HEURIST4.util.isnull(val[part])){
                        val = val[part];
                    }else if(part == 'count'){
                        val = 0;
                    }
                }      
                
                if(DT_GEO_OBJECT == this.options.mapping.fields[fld_Names]){ // looking for geospatial values
                    if(!window.hWin.HEURIST4.util.isempty(val)){
                        val = {"type": "Feature", "geometry": val};
                        let wkt = stringifyMultiWKT(val);    
                        if(window.hWin.HEURIST4.util.isempty(wkt)){
                            val = '';
                        }else{

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
                }else{ // not looking for geospatial values
                    hasGeo = true;
                }
                values.push(val);    
            }

            if(hasGeo){
                res_orders.push(recID);
                res_records[recID] = values;    
            }
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});

