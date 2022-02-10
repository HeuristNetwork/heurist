/**
* lookupTLC.js - Lookup values in third-party web service for Heurist record 
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupTLC.html)
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

$.widget( "heurist.lookupTLC", $.heurist.recordAction, {

    // dialog options, the default values and other available options can be found in hclient/widget/record/recordAction.js
    options: {

        height: 520,
        width:  800,
        modal:  true,

        title:  'Lookup values for Heurist record', // dialog title
        
        htmlContent: 'lookupTLC.html', // in hclient/widgets/lookup folder
        helpContent: 'lookupTLC.html', // in context_help folder

        mapping: null, //configuration from record_lookup_config.json (DB Location: sysIdentification.sys_ExternalReferenceLookups)
               
        add_new_record: false  //if true it creates new record on selection
    },
    
    recordList: null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        this.element.find('fieldset > div > .header').css({width:'80px','min-width':'80px'});

        // prepare record list options
        this.options.resultList = $.extend(this.options.resultList, 
        {
            recordDivEvenClass: 'recordDiv_blue', // for alternating colours
            eventbased: false,  // do not listent global events

            multiselect: false, // (this.options.select_mode!='select_single'), 

            select_mode: 'select_single', // or select_multi, this.options.select_mode
            selectbutton_label: 'select!!', // button for confirming selected rows, multiselect

            view_mode: 'list', // initial view mode
            show_viewmode: false, // show view mode options

            entityName: this._entityName,

            pagesize:(this.options.pagesize>0) ? this.options.pagesize : 50, // number of records on each 'page'
            empty_remark: '<div style="padding:1em 0 1em 0">No records found</div>', // alternative to an empty space when there is no result
            renderer: this._rendererResultList // how the records are displayed
        });                

        // init record list widget, found at hclient/widget/viewers/resultList.js (additional options can be found there too)
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList( this.options.resultList );     
        
        // adding standard event listeners for the record list
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

        // adding additional event listeners
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });

        this._on(this.element.find('input'),{
            'keypress':this.startSearchOnEnterPress
        });
        
        return this._super();
    },
    
    //
    // Event handler - 'Enter' key press
    // Start search
    //
    startSearchOnEnterPress: function(e){
        
        var code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }
    },
    
    
    //
    // Record Renderer
    // Defines how a record is rendered within the list, customised via the recTitle variable within
    // Field names are those found within this.options.mapping.fields
    //
    _rendererResultList: function(recordset, record){

        function fld(fldname, width){

            var s = recordset.fld(record, fldname);

            if(fldname == 'properties.LGA'){ 
                s = s.lga; 
            }

            s = s?s:'';
            var title = s;

            if(fldname == 'recordLink'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View tlcmap record';
            }

            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }
            return s;
        }

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');

        var recTitle = fld('properties.placename',40) + fld('properties.LGA', 25) + fld('properties.state', 6) + fld('properties.description', 65) + fld('recordLink', 12); 

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

    //    
    // Get and customise dialog buttons
    // By default there are two 'Go' and 'Cancel', these can be edited or more buttons can be added
    //
    _getActionButtons: function(){
        var res = this._super(); // setups and retrieves default dialog buttons

        /*
            res[0] => Cancel/Close: Closes dialog
            res[1] => Go/Select: Calls doAction
        */

        res[1].text = window.hWin.HR('Select');

        return res;
    },

    //
    // Either return mapped fields to record or create a new record using the mapped fields
    //
    doAction: function(){

        // retrieve selected record/s
        var sel = this.recordList.resultList('getSelected', false);

        if(sel && sel.length() == 1){

            if(this.options.add_new_record){

                //create new record 
                window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

                var rectype_id = (!window.hWin.HEURIST4.util.isempty(this.options.rectype_for_new_record)) ? this.options.rectype_for_new_record : this.options.mapping.rty_ID;

                this._addNewRecord(rectype_id, sel);                     
            }else{

                //pass mapped values and close dialog
                this._context_on_close = sel;
                this._as_dialog.dialog('close');
            }
        }
    },
    
    //
    // create search url
    // perform search and call result handler
    //
    _doSearch: function(){
        
        var sURL;

        // get base url
        if(this.options.mapping.service=='tlcmap'){
            sURL = 'http://tlcmap.org/ghap/search?format=csv&paging=100';
        }else if(this.options.mapping.service=='tlcmap_old'){
            sURL = 'http://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?format=json&paging=100';  
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
            sURL = sURL + '&' 
            + (this.element.find('#inpt_exact').is(':checked')?'name':'fuzzyname')
            + '=' + encodeURIComponent(this.element.find('#inpt_name').val());
        }
        if(this.element.find('#inpt_anps_id').val()!=''){
            sURL = sURL + '&anps_id=' + this.element.find('#inpt_anps_id').val();
        }
        if(this.element.find('#inpt_lga').val()!=''){
            sURL = sURL + '&lga=' + encodeURIComponent(this.element.find('#inpt_lga').val());
        }
        if(this.element.find('#inpt_state').val()!=''){
            sURL = sURL + '&state=' + this.element.find('#inpt_state').val();
        }

        var that = this;
        var request = {service:sURL, serviceType:'tlcmap'};             

        // performing request - see controller hsapi/controller/record_lookup.php for service external lookups
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
    
    //
    // Process response to request from _doSearch
    // Create records to display within the record list and to map back to Heurist record
    //
    _onSearchResult: function(geojson_data){
        
        this.recordList.show();
       
        var is_wrong_data = true;
                        
        if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){ // validate response as GeoJSON
            
            var res_records = {}, res_orders = [];

            var DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];

            // Retrieve fields for records, any additional fields that are not part of the new record (e.g. url to original record) can be added here
            var fields = ['rec_ID','rec_RecTypeID'];
            var map_flds = Object.keys(this.options.mapping.fields);
            fields = fields.concat(map_flds);
            fields = fields.concat('recordLink');
            
            for(var k=0; k<map_flds.length; k++){
                map_flds[k] = map_flds[k].split('.'); 
            }
            
            if(!geojson_data.features) geojson_data.features = geojson_data;
            
            // Parse GeoJSON, special handling can be done here (e.g. creating Geo Objects for Geospatial fields)
            var i=0;
            for(;i<geojson_data.features.length;i++){
                var feature = geojson_data.features[i];
                
                var recID = i+1;
                
                var hasGeo = false;
                var values = [recID, this.options.mapping.rty_ID];
                for(var k=0; k<map_flds.length; k++){
                    
                    var val = feature[ map_flds[k][0] ];
                    
                    for(var m=1; m<map_flds[k].length; m++){
                        if(val && !window.hWin.HEURIST4.util.isnull( val[ map_flds[k][m] ])){
                            val = val[ map_flds[k][m] ];
                        }
                    }      
                    
                    // Special handling for Geo Objects
                    if(DT_GEO_OBJECT == this.options.mapping.fields[map_flds[k]]){
                        if(!window.hWin.HEURIST4.util.isempty(val)){
                            val = {"type": "Feature", "geometry": val};
                            var wkt = stringifyMultiWKT(val);    
                            if(window.hWin.HEURIST4.util.isempty(wkt)){
                                val = '';
                            }else{
                                //@todo the same code mapDraw.php:134
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
                    values.push(val); // push value into record
                }

                // creating the additional recordLink field
                values.push('https://tlcmap.org/ghap/search?id=' + feature['properties']['id']);

                if(hasGeo){

                    // add record into recordset
                    res_orders.push(recID);
                    res_records[recID] = values;    
                }
            }

            if(res_orders.length>0){

                // initialise recordset
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
    },
    
    //
    // Create new records with provided field values
    //
    _addNewRecord: function (rectype_id, field_values){
        
        var that = this;

        var request = {
            a: 'save',
            ID: 0,
            RecTypeID: rectype_id,
            details: field_values
        };

        window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            if(response.status == window.hWin.ResponseStatus.OK){
                // ... Complete final tasks, then
                that._as_dialog.dialog('close'); // close dialog
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }  
});

