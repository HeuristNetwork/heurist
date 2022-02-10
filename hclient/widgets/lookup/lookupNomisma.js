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

$.widget( "heurist.lookupNomisma", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 720,
        width:  500,
        modal:  true,
        
        title:  'Search Nomisma database of coins and currency via several options',
        
        htmlContent: 'lookupNomisma.html',
        helpContent: null, //in context_help folder
        
        mapping: null, //configuration from sys_ExternalReferenceLookups
               
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({'min-width':'65px', display: 'inline-block'});
        //this.element.find('#btn_container').position({my: 'left top', at: 'right top', of: '#search_container'});

        // Action button styling
        this.element.find('#btnMintSearch, #btnHoardsSearch, #btnFindspotsSearch, #btnRdfSearch').addClass("ui-button-action");
        this.element.find('#btnRdfSearch').hide();

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
               empty_remark: '<div style="padding:1em 0 1em 0">No Works Found</div>', // For empty results
               renderer: this._rendererResultList // Record render function, is called on resultList updateResultSet
        });                

        // Init record list
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList( this.options.resultList );     
        
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
        this._on(this.element.find('#btnMintSearch, #btnHoardsSearch, #btnFindspotsSearch').button(),{
            'click':this._doSearch
        });

        return this._super();
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

            var s

            if(fldname == 'dates'){
                s = recordset.fld(record, 'when.timespans.start') + ' to ' + recordset.fld(record, 'when.timespans.end') 
                        + ' (end date: ' + recordset.fld(record, 'properties.closing_date') + ')';
            }else{
                s = recordset.fld(record, fldname);
            }

            s = s?s:'';
            var title = s;

            if(fldname == 'properties.gazetteer_uri'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View nomisma record';
            }else if(fldname == 'properties.count'){
                s = '(count: ' + s + ')';
            }

            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }
            return s;
        }

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var recTitle = '';

        if(fld('properties.type') == 'hoard'){
            recTitle = fld('properties.type', 10) + fld('label', 30) + fld('dates', 35) + fld('properties.gazetteer_uri', 10);
        }else{
            recTitle = fld('properties.type', 10) + fld('properties.gazetteer_label', 30) + fld('properties.count', 15) + fld('properties.gazetteer_uri', 10); 
        }

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
        return res;
    },

    //
    // Either perform search or select entry in resultList and triggers addition of new record
    //
    doAction: function(){

        //detect selection
        var sel = this.recordList.resultList('getSelected', false);
        
        if(sel && sel.length() == 1){

            //pass mapped values and close dialog
            this._context_on_close = sel;
            this._as_dialog.dialog('close');
        }
    },
    
    //
    // create search url
    // perform search
    //
    _doSearch: function(event){

        var that = this;
        var search_type = $(event.target).val();

        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Enter value to search...', 500);
            return;
        }

        var sURL = '';

        if(search_type == 'mint'){
            sURL = 'http://nomisma.org/apis/getMints?id=';
        }else if(search_type == 'hoard'){
            sURL = 'http://nomisma.org/apis/getHoards?id=';
        }else if(search_type == 'findspots'){
            sURL = 'http://nomisma.org/apis/getFindspots?id=';
        }else{
            return;
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        sURL += this.element.find('#inpt_any').val();

        var request = {service:sURL, serviceType:'nomisma', 'search_type': search_type};

        // calls /heurist/hsapi/controller/record_lookup.php
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
    
    //
    //
    //
    _onSearchResult: function(geojson_data){
        
        this.recordList.show();
       
        var is_wrong_data = true;
                        
        if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){
            
            var res_records = {}, res_orders = [];
                
            var DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
            
            var fields = ['rec_ID','rec_RecTypeID'];
            var map_flds = Object.keys(this.options.mapping.fields);
            fields = fields.concat(map_flds);
            
            for(var k=0; k<map_flds.length; k++){
                map_flds[k] = map_flds[k].split('.'); 
            }
            
            if(!geojson_data.features) geojson_data.features = geojson_data;
            
            //parse json
            var i=0;
            for(;i<geojson_data.features.length;i++){
                var feature = geojson_data.features[i];
                
                var recID = i+1;
                
                var hasGeo = false;
                var values = [recID, this.options.mapping.rty_ID];
                for(var k=0; k<map_flds.length; k++){

                    var val = feature[map_flds[k][0]];

                    if(map_flds[k][0] == 'when' && val){

                        if(map_flds[k][2] == 'start' && val['timespans']){
                            val = val['timespans'][0]['start'];
                        }else if(map_flds[k][2] == 'end' && val['timespans']){
                            val = val['timespans'][0]['end'];
                        }
                    }

                    for(var m=1; m<map_flds[k].length; m++){
                        if(val && !window.hWin.HEURIST4.util.isnull( val[ map_flds[k][m] ])){
                            val = val[ map_flds[k][m] ];
                        }else if(map_flds[k][m] == 'count'){
                            val = 0;
                        }
                    }      
                    
                    if(DT_GEO_OBJECT == this.options.mapping.fields[map_flds[k]]){
                        if(!window.hWin.HEURIST4.util.isempty(val)){
                            val = {"type": "Feature", "geometry": val};
                            var wkt = stringifyMultiWKT(val);    
                            if(window.hWin.HEURIST4.util.isempty(wkt)){
                                val = '';
                            }else{

                                var typeCode = 'm';
                                if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
                                    if(wkt.indexOf('LINESTRING')>=0){
                                        typeCode = 'l';
                                    }else if(wkt.indexOf('POLYGON')>=0){
                                        typeCode = 'pl';
                                    }else {
                                        typeCode = 'p';
                                    }
                                }
                                val = typeCode+' '+wkt;
                                hasGeo = true;
                            }
                        }
                    }
                    values.push(val);    
                }

                if(hasGeo){
                    res_orders.push(recID);
                    res_records[recID] = values;    
                }
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

