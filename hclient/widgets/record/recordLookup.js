/**
* recordLookup.js - Lookup values in third-party web service for Heurist record 
* 
*   It consists of search form and result list to select one or several values of record
* 
*       descendants of this widget 
*   1) perform search on external third-part web service
*   2) render result in our resultList (custom renderer)
*   3) map external results with our field details (see options.mapping)
*   4) either returns these mapped fields (to edit record form) 
*       or trigger addition of new record with selected values
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

$.widget( "heurist.recordLookup", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  'Lookup values for Heurist record',
        
        htmlContent: 'recordLookup.html',
        helpContent: 'recordLookup.html', //in context_help folder
        
        mapping:null, //configuration from sys_ExternalReferenceLookups
               
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;
            
/*
"placename":"Manly",
"anps id":"43347",
"state":"NSW",
"LGA":"MANLY",
"Latitude":"-33.79833333333333",
"Longitude":"151.28444444444443",
"Original Data Source":"State Records (TLCM)",
"flag":"Uses GDA94 Coordinates instead of WGS84",
"description":"A suburb about 4 km S by E of Brookvale and about 6 km N by E of Vaucluse.  Boundaries shown on map marked GNB 3641"

[{"type":"Feature","id":"857","properties":{"rec_ID":"857"....},"geometry":{"type":"Point","coordinates":[48.671137,46.998197]}},
*/    
        
        this.element.find('fieldset > div > .header').css({width:'80px','min-width':'80px'})
        
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
               empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>',
               renderer: this._rendererResultList
               /*
               searchfull: function(arr_ids, pageno, callback){
                   that._recordListGetFullData(arr_ids, pageno, callback);
               rendererHeader: this.options.show_list_header ?function(){
                        return that._recordListHeaderRenderer();  //custom header for list mode (table header)
                        }:null
               */        
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
    
    //
    //
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
    //
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
                title = 'View tclmap record';
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
    //
    //
    _getActionButtons: function(){
        var res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Select');
        //res[1].disabled = null;
        return res;
    },

    //
    // Either perform search or select entry in resultList and triggers addition of new record
    //
    doAction: function(){

            //detect selection
            var sel = this.recordList.resultList('getSelected', false);
            
            if(sel && sel.length() == 1){
                
                if(this.options.add_new_record){
                    //create new record 
                    //window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
                    //this._addNewRecord(this.options.rectype_for_new_record, sel);                     
                }else{
                    //pass mapped values and close dialog
                    this._context_on_close = sel;
                    this._as_dialog.dialog('close');
                }
                
            }
        
    },
    
    //
    // create search url
    // perform search
    //
    _doSearch: function(){
        
        var sURL;
        if(this.options.mapping.service=='tlcmap'){
            sURL = 'http://tlcmap.org/ghap/search?format=csv&paging=100';
            
        }else if(this.options.mapping.service=='tlcmap_old'){
            sURL = 'http://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?format=json&paging=100';  
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Name of service not defined...', 500);
            return;
        }

        if(this.element.find('#inpt_name').val()=='' && this.element.find('#inpt_anps_id').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Define name or ANPS ID...', 500);
            return;
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        
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
    
    //
    //
    //
    _onSearchResult: function(geojson_data){
        
        this.recordList.show();
       
        var is_wrong_data = true;
                        
        if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){
            
            var res_records = {}, res_orders = [];
            /*      
            var DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'],
                DT_ORIGINAL_RECORD_ID = window.hWin.HAPI4.sysinfo['dbconst']['DT_ORIGINAL_RECORD_ID'],
                DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                DT_SHORT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_NAME'],
                DT_ADMIN_UNIT = window.hWin.HAPI4.sysinfo['dbconst']['DT_ADMIN_UNIT'],
                DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
            */
                
            var DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
            
            var fields = ['rec_ID','rec_RecTypeID'];
            var map_flds = Object.keys(this.options.mapping.fields);
            fields = fields.concat(map_flds);
            fields = fields.concat('recordLink');
            
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
                    
                    var val = feature[ map_flds[k][0] ];
                    
                    for(var m=1; m<map_flds[k].length; m++){
                        if(val && !window.hWin.HEURIST4.util.isnull( val[ map_flds[k][m] ])){
                            val = val[ map_flds[k][m] ];
                        }
                    }      
                    
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
                    values.push(val);    
                }

                // https://maps.google.com/?q=lat,lng or https://www.google.com/maps/search/?api=1&query=lat,lng
                values.push('https://tlcmap.org/ghap/search?id=' + feature['properties']['id']);

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
    },
    
    //
    // 
    //
    _addNewRecord: function (record_type, field_values){
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();
    }  
});

