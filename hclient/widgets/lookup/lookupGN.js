/**
* lookupGN.js - GeoNames lookup service
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupGN_postakCode.html)
*   2) Performs an api call to the Geoname service using the User's input, displaying the results within a Heurist result list
*   3) map external results with our field details (see options.mapping) and returns the mapped results to the record edit form
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.lookupGN", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  'Lookup values Postal codes for Heurist record',
        
        htmlContent: 'lookupGN.html',
        //helpContent: 'lookupGN.html', //in context_help folder
        
        mapping:null, //configuration from sys_ExternalReferenceLookups
               
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,
    _country_vocab_id: 0,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        //fill countries dropdown
        var ele = this.element.find('#inpt_country');
        this._country_vocab_id = $Db.getLocalID('trm','2-509');
        window.hWin.HEURIST4.ui.createTermSelect(ele.get(0), {vocab_id:this._country_vocab_id,topOptions:'select...',useHtmlSelect:false});

        if(ele.hSelect('instance') != 'undefined'){
            ele.hSelect('widget').css({'max-width':'30em'});
        }
        
        this.element.find('#search_container > div > div > .header').css({width:'70px','min-width':'70px', display: 'inline-block'});

        this.element.find('#btn_container').position({my: 'left center', at: 'right center', of: '#search_container'});

        this.options.resultList = $.extend(this.options.resultList, 
        {
               recordDivEvenClass: 'recordDiv_blue',
               eventbased: false,  //do not listent global events

               multiselect: false, //(this.options.select_mode!='select_single'), 

               select_mode: 'select_single', //this.options.select_mode,
               selectbutton_label: 'select!!', //this.options.selectbutton_label, for multiselect
               
               view_mode: 'list',
               show_viewmode:false,
               
               entityName: this._entityName,
               //view_mode: this.options.view_mode?this.options.view_mode:null,
               
               pagesize:(this.options.pagesize>0) ?this.options.pagesize: 9999999999999,
               empty_remark: '<div style="padding:1em 0 1em 0">No Locations Found</div>',
               renderer: this._rendererResultList      
        });                

        //init record list
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList( this.options.resultList );     
        
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
                //,"resultlistonaction": this._onActionListener        
                });
        
        
        
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });
        
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
        
        function fld(fldname, width){

            var s = recordset.fld(record, fldname);
            s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');

            var title = s;

            if(fldname == 'recordLink'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View geoname record';
            }

            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }
            return s;
        }

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID'); 

        var recTitle = fld('name', 40) + fld('adminName1', 20) + fld('countryCode', 6) + fld('fcodeName', 40) + fld('fclName', 20) + fld('recordLink', 12);

        var recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;

        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
                + window.hWin.HAPI4.iconBaseURL + rectypeID + '&version=thumb&quot;);"></div>';

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
        //res[1].disabled = null;
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

        //detect selection
        var recset = this.recordList.resultList('getSelected', false);
        
        if(recset && recset.length() == 1){
            
            var res = {};
            var rec = recset.getFirstRecord();
            
            var map_flds = Object.keys(this.options.mapping.fields);
            
            for(var k=0; k<map_flds.length; k++){
                var dty_ID = this.options.mapping.fields[map_flds[k]];
                var val = recset.fld(rec, map_flds[k]);
                
                if(map_flds[k]=='countryCode' && this._country_vocab_id>0){
                    val = $Db.getTermByCode(this._country_vocab_id, val);
                }
                
                if(dty_ID>0 && val){
                    res[dty_ID] = val;    
                }
            }

            //pass mapped values and close dialog
            this._context_on_close = res;
            this._as_dialog.dialog('close');
        }        
    },
    
    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){
        
        if(this.element.find('#inpt_placename').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a geoname to perform search', 1000);
            return;
        }

        var sURL = 'http://api.geonames.org/searchJSON?username=osmakov';

        if(this.element.find('#inpt_placename').val()!=''){
            sURL += '&name=' + this.element.find('#inpt_placename').val();
        }
        if(this.element.find('#inpt_country').val()!=''){

            var term_label = $Db.trm(this.element.find('#inpt_country').val(), 'trm_Label');
            var _countryCode = $Db.trm(this.element.find('#inpt_country').val(), 'trm_Code');

            if(_countryCode == ''){
                
                switch (term_label) {
                    case 'Iran':
                        _countryCode = 'IR';
                        break;
                    case 'Kyrgistan': // Kyrgzstan
                        _countryCode = 'KG';
                        break;
                    case 'Syria':
                        _countryCode = 'SY';
                        break;
                    case 'Taiwan':
                        _countryCode = 'TW';
                        break;
                    case 'UAE':
                        _countryCode = 'AE';
                        break;
                    case 'UK':
                        _countryCode = 'GB';
                        break;
                    case 'USA':
                        _countryCode = 'US';
                        break;
                    case 'Vietnam':
                        _countryCode = 'VN';
                        break;
                    default:
                        break;
                }
            }

            if(_countryCode != ''){
                sURL += '&country=' + _countryCode; 
            }
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        var that = this;
        var request = {service:sURL, serviceType:'geonames'};             
        //loading as geojson  - see controller record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request,
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response){
                    if(response.status && response.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._onSearchResult(response);
                    }
                }
            }
        );
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

            var res_records = {}, res_orders = [];

            var DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
            if(DT_GEO_OBJECT>0 && !this.options.mapping.fields['location']){
                this.options.mapping.fields['location'] = DT_GEO_OBJECT;
            }

            var fields = ['rec_ID', 'rec_RecTypeID'];
            var map_flds = Object.keys(this.options.mapping.fields);

            fields = fields.concat(map_flds);
            fields = fields.concat('recordLink');

            if(!json_data.geonames) json_data.geonames = json_data;
            
            //parse json
            var i=0;
            var data = json_data.geonames; console.log(data);
            for(;i<data.length;i++){
                var feature = data[i];
                
                var recID = i+1;
                
                var val;
                var values = [recID, this.options.mapping.rty_ID];
                
                for(var k=0; k<map_flds.length; k++){
                    
                    if(map_flds[k]=='location'){
                        if(feature[ 'lng' ] && feature[ 'lat' ]){
                            val = 'p POINT('+feature[ 'lng' ]+' '+feature[ 'lat' ]+')';
                        }else{
                            val = '';
                        }
                    }else{
                        val = feature[ map_flds[k] ];
                    }
                        
                    values.push(val);    
                }

                // Push additional information, GeoName: www.geonames.org/geoname_rec_id/
                values.push('https://www.geonames.org/' + feature['geonameId'] + '/');

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){        
                var res_recordset = new hRecordSet({
                    count: res_orders.length,
                    offset: 0,
                    fields: fields,
                    rectypes: [this.options.mapping.rty_ID],
                    records: res_records,
                    order: res_orders,
                    mapenabled: true //???
                });              
                
                this.recordList.resultList('updateResultSet', res_recordset);            
                is_wrong_data = false;
            }
        }
       
        if(is_wrong_data){

            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr('Service did not return data in an appropriate format');
        }
    }
});