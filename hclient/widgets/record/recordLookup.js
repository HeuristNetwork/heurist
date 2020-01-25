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
* @copyright   (C) 2005-2019 University of Sydney
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
        
        mapping:null, //maps external fields to heurist field details
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;
        
        //put this configuration to settings folder
        if(!this.options.mapping)
            this.options.mapping = {
              rty_ID: 12,//'3-1009'
              service: 'tlcmap',
              label: 'Lookup in AGHP',
              fields:{ 
                  'properties.name': 1, //'2-1',  
                  'geometry': 28, //'2-28',
                  //'properties.id': 26, //'2-26', //original id
                  'properties.id': 26,   // 2-581  //external id
                  'state': 234, //'2-234',
                  'LGA': 2, //'2-2',
                  'description': 4 //'2-4'
              }    
            };
        
        this.element.find('fieldset > div > .header').css({width:'80px','min-width':'80px'})
        
        this.options.resultList = $.extend(this.options.resultList, 
        {
               recordDiv_class: 'recordDiv_blue',
               eventbased: false, 
               isapplication: false, //do not listent global events @todo merge with eventbased
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
            s = s?s:'';
            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate">'+s+'</div>';
            }
            return s;
        }
        
        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var recTitle = fld('properties.name',40); 
        
        recTitle = recTitle + fld('LGA',15)+fld('state',6)+fld('description',80); 
        
        var recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID + '.png';
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
                + window.hWin.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png&quot;);"></div>';
                
                

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectypeID+'">'
            + html_thumb
            
                + '<div class="recordIcons">'
                +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/>' 
                + '</div>'
            
                //+ '<div class="recordTitle" style="left:30px;right:2px">'
                    +  recTitle
                //+ '</div>'
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
        
        if(this.element.find('#inpt_name').val()=='' && this.element.find('#inpt_anps_id').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Define name ot ANPS ID...', 500);
            return;
        }
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        var sURL = 'http://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?format=json&paging=100';
        
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
        var request = {service:sURL};             
        //loading as geojson
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
        /*
        var that = this;
        
        window.hWin.HEURIST4.util.sendRequest(sURL, null, this,
        function(response)
        {
             window.hWin.HEURIST4.msg.sendCoverallToBack();
             
             if(response && response.status == window.hWin.ResponseStatus.UNKNOWN_ERROR)
             {
                  window.hWin.HEURIST4.msg.showMsgErr(response);
                 
             }else{
                 that.element.find('#div_fieldset').hide();
                 that.element.find('#div_result').text(response).show();
             }
        },'json');
        */
    },
    
    _onSearchResult: function(geojson_data){
        
        this.recordList.show();
                        
       if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){
            
            var res_records = {}, res_orders = [];
            /*
            var fields = [
"bkm_ID","bkm_UGrpID","rec_ID","rec_URL","rec_RecTypeID","rec_Title","rec_OwnerUGrpID",
"rec_NonOwnerVisibility","rec_Modified","bkm_PwdReminder","rec_ThumbnailURL"];
      
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
            
            for(var k=0; k<map_flds.length; k++){
                map_flds[k] = map_flds[k].split('.'); 
            }
            
            //parse json
            var i=0;
            for(;i<geojson_data.features.length;i++){
                var feature = geojson_data.features[i];
                
                var recID = i+1;
                res_orders.push(recID);
                
                var values = [recID, this.options.mapping.rty_ID];
                for(var k=0; k<map_flds.length; k++){
                    
                    var val = feature[ map_flds[k][0] ];
                    
                    for(var m=1; m<map_flds[k].length; m++){
                        if(val && val[ map_flds[k][m] ]){
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
                            }
                        }
                    }
                        
                    values.push(val);    
                }
                res_records[recID] = values;
                

                /*
                var header = {0:null,1:null,2:'recID',3:'',4:this.options.mapping.rty_ID,
                   5:'TITLE',6:0,7:'viewable',8:'',9:null,10:null};
                header[2] = recID;
                header[4] = this.options.mapping.rty_ID;
                header[5] = feature.properties.name;
                
                var details = {};
                details[DT_NAME] = [feature.properties.name];
                details[DT_ORIGINAL_RECORD_ID] = [feature.properties.id];
                details[DT_SHORT_NAME] = [feature.LGA];
                details[DT_ADMIN_UNIT] = [feature.state];
                details[DT_EXTENDED_DESCRIPTION] = [feature.description];
                details[DT_GEO_OBJECT] = [feature.geometry];
                
                res_records[recID] = header;
                res_records[recID]['d'] = details;
                */
            }
            
/*
              'geometry': '2-28',
              'properties.name': '2-1',  
              'properties.id': '2-26', //original id
              'state': '2-234',
              'LGA': '2-2',
              'description': '2-4'
*/            
            
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
       }else{
            //ele.text('ERROR '+geojson_data);                    
            this.recordList.resultList('updateResultSet', null);            
       }
    },

    
    //
    // 
    //
    _addNewRecord: function (record_type, field_values){
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();
    }
    
        
});

