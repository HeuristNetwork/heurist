/**
* lookupGN_postalCode.js - GeoNames postalCode DB lookup service
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupGN_postakCode.html)
*   2) Performs an api call to the Geoname service's Postalcode DB using the User's input, displaying the results within a Heurist result list
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

$.widget("heurist.lookupGN_postalCode", $.heurist.lookupBase, {

    // default options
    options: {

        height: 520,
        width:  800,

        title:  'Lookup values Postal codes for Heurist record',

        htmlContent: 'lookupGN_postalCode.html',
    },

    _country_vocab_id: 0,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        // Fill countries dropdown
        let ele = this.element.find('#inpt_country');
        this._country_vocab_id = $Db.getLocalID('trm','2-509');
        if(this._country_vocab_id > 0){
            window.hWin.HEURIST4.ui.createTermSelect(ele.get(0), {vocab_id:this._country_vocab_id,topOptions:'select...',useHtmlSelect:false});
        }

        if(ele.hSelect('instance') != 'undefined'){
            ele.hSelect('widget').css({'max-width':'30em'});
        }
        
        this.element.find('#search_container > div > div > .header').css({width:'80px','min-width':'80px', display: 'inline-block'});

        this.element.find('#btn_container').position({my: 'left center', at: 'right center', of: '#search_container'});

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Locations Found</div>'
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

            let s = recordset.fld(record, fldname);
            s = window.hWin.HEURIST4.util.htmlEscape(s || '');

            let title = s;

            if(fldname == 'googlemap_link'){
                s = `<a href="${s}" target="_blank" rel="noopener"> google maps </a>`;
                title = 'View location via Google Maps';
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }

        let recTitle = fld('postalcode', 10) + fld('placeName', 40) + fld('adminName2', 30) + fld('adminName1', 30) + fld('countryCode', 6);
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
     *  e.g. res['ext_url'] = '<a href="www.google.com" target="_blank">Link to Google</a>'
     * 
     * Param: None
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};
        res['googlemap_link'] = recset.fld(record, '');
        res = this.prepareValues(recset, record, res, {check_term_codes: this._country_vocab_id});

        // Pass mapped values and close dialog
        this.closingAction(res);
    },
    
    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){
        
        if(this.element.find('#inpt_postalcode').val()=='' && this.element.find('#inpt_placename').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a geoname or postal code to perform search', 1000);
            return;
        }

        let sURL = 'http'+'://api.geonames.org/postalCodeLookupJSON?';

        if(this.element.find('#inpt_postalcode').val()!=''){
            sURL += `&postalcode=${this.element.find('#inpt_postalcode').val()}`; 
        }
        if(this.element.find('#inpt_placename').val()!=''){
            sURL += `&placename=${encodeURIComponent(this.element.find('#inpt_placename').val())}`;
        }
        if(this.element.find('#inpt_country').val()!=''){
            let _countryCode = this._getCountryCode(this.element.find('#inpt_country').val());
            _countryCode += _countryCode ? `&country=${_countryCode}` : '';
        }
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let that = this;
        let request = {service:sURL, serviceType:'geonames'};             
        //loading as geojson  - see controller record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request,
            function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
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
    _onSearchResult: function(json_data){

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if(!json_data){
            this._super(false);
        }

        let res_records = {}, res_orders = [];

        let DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
        if(DT_GEO_OBJECT>0 && !this.options.mapping.fields['location']){
            this.options.mapping.fields['location'] = DT_GEO_OBJECT;
        }

        let fields = ['rec_ID', 'rec_RecTypeID'];
        let map_flds = Object.keys(this.options.mapping.fields);

        fields = fields.concat(map_flds);
        fields = fields.concat('googlemap_link');

        if(!json_data.postalcodes) json_data.postalcodes = json_data;

        //parse json
        let i = 1;
        let data = json_data.postalcodes;

        for(const idx in data){

            let feature = data[idx];

            let recID = i++;

            let val;
            let values = [recID, this.options.mapping.rty_ID];

            for(const fld_Name of map_flds){

                val = feature[fld_Name];

                if(fld_Name == 'location'){
                    val = feature['lng'] && feature['lat'] 
                        ? `p POINT(${feature['lng']} ${feature['lat']})`
                        : '';
                }

                values.push(val);    
            }

            // https://maps.google.com/?q=lat,lng or https://www.google.com/maps/search/?api=1&query=lat,lng
            values.push(`https://www.google.com/maps/search/?api=1&query=${feature['lat']},${feature['lng']}`);

            res_orders.push(recID);
            res_records[recID] = values;
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});