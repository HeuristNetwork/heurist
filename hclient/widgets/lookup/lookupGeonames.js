/**
* lookupGeonames.js - Base widgt for Geoname lookups
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

$.widget("heurist.lookupGeonames", $.heurist.lookupBase, {

    options: {

        height: 520,
        width: 800,

        title: 'Lookup values Postal codes for Heurist record',

        htmlContent: ''
    },

    _is_postal_codes: false,

    _country_vocab_id: 0,

    _init: function(){

        this._is_postal_codes = this.options.mapping.service == 'postalCodeSearch';

        this.options.htmlContent = this._is_postal_codes ? 'lookupGN_postalCode.html' : 'lookupGN.html';

        return this._super();
    },

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

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Locations Found</div>'
        });

        return this._super();
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     * 
     * To trigger record pointer selection/creation popup, value must equal [dty_ID, default_searching_value]
     * 
     * Include a url to an external record that will appear in the record pointer guiding popup, add 'ext_url' to res
     *  the value must be the complete html (i.e. anchor tag with href and target attributes set)
     *  e.g. res['ext_url'] = '<a href="www.geonames.com" target="_blank">Link to Example</a>'
     * 
     * Param: None
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let link_field = this._is_postal_codes ? 'googlemap_link' : 'geoname_link';

        let res = {};
        res['ext_url'] = recset.fld(record, link_field);
        res = this.prepareValues(recset, record, res, {check_term_codes: this._country_vocab_id});

        // Pass mapped values and close dialog
        this.closingAction(res);
    },

    _doSearch: function(sURL, xml_Response = false){

        let that = this;

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let request = {service:sURL, serviceType:'geonames', is_XML: xml_Response};
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
    }
});