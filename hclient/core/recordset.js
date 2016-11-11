/**
* Storage of records and its definitions (fields and structure)
* 
* requires temporalOBjectLibrary.js to validate and convert date for timeline
* 
* @see recordSearch in db_recsearch.php
* @param initdata
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


function hRecordSet(initdata) {
    var _className = "hRecordSet",
    _version   = "0.4";

    var total_count = 0,   //number of records in query  - not match to  length()
    queryid = null, //unique query id
    offset = 0,
    //limit = 1000, use length()
    fields = [],       //array of field names
    records = null,      //list of records objects {recID:[], ....}
    order = [], //array of record IDs in specified order
    mainset = null, //array of record IDs that belong to main result set (without applied rules)
    
    rectypes = [],      // unique list of record types with counts
    structures = null,  //record structure definitions for all rectypes in this record set
    relationship = null; //relationship records within this recordset
    
    var _progress = null,
        _isMapEnabled = false,
        _request = null;
    
    /**
    * Initialization
    */
    function _init(response) {

        if(response){

            that.entityName = response.entityName;           
            queryid = response.queryid;
            total_count = Number(response.count);
            offset = Number(response.offset);
            
            if( !$.isEmptyObject(response.mainset) ){
                mainset = response.mainset;
            }
            
            if( !$.isEmptyObject(response['fields']) ){
                fields = response.fields;
                rectypes = response.rectypes;
                structures = response.structures;
                records = response.records;  //$.isArray(records)
                order = response.order;
                relationship = response.relationship;
                
                _isMapEnabled = response.mapenabled;
                //@todo - merging
            }else{
                records = [];
                order = response.records;
                if(response.rectypes) rectypes = response.rectypes;
                _isMapEnabled = false;
            }
        }
        
    }

    /**
    * Converts recordSet to key:title array (for selector)
    */
    function _makeKeyValueArray(namefield){
        
        var ids, record, key, title;
        
        var result = [];
        
        for(idx in records){
            if(idx)
            {
                record = records[idx];
                
                key       = record[0],
                title     = _getFieldValue(record, namefield),
                
                result.push({key:key, title:title});
            }
        }        
        return result;
    }    

    /**
    * Converts recordSet to OS Timemap dataset
    * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only
        * 2 - rec_Shape only
    */
    function _toTimemap(dataset_name, filter_rt, iconColor, geoType){

        var aitems = [], titems = [];
        var item, titem, shape, idx, 
            min_date = Number.MAX_VALUE, 
            max_date = Number.MIN_VALUE;
        var mapenabled = 0,
            timeenabled = 0;
            
        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');    
            
        dataset_name = dataset_name || "main";
        iconColor = iconColor || 'rgb(255, 0, 0)'; //'#f00';
         
        var tot = 0;
        
        for(idx in records){
            if(idx)
            {
                var record = records[idx];
                var recTypeID   = Number(_getFieldValue(record, 'rec_RecTypeID'));
                
                if(filter_rt && recTypeID!=filter_rt) continue;

                var
                recID       = _getFieldValue(record, 'rec_ID'),
                recName     = _getFieldValue(record, 'rec_Title'),

                startDate   = _getFieldValue(record, 'dtl_StartDate'),
                endDate     = _getFieldValue(record, 'dtl_EndDate'),
                description = _getFieldValue(record, 'dtl_Description'),
                type        = _getFieldValue(record, 'dtl_GeoType'),  //take first part of dtl_Geo field - "p wkt"
                wkt         = _getFieldValue(record, 'dtl_Geo'),  
                recThumb    = _getFieldValue(record, 'rec_ThumbnailURL'),
                recShape    = _getFieldValue(record, 'rec_Shape'),  //additional shapes
                
                
                iconId      = _getFieldValue(record, 'rec_Icon');  //used if icon differ from rectype icon
                if(!iconId) iconId = recTypeID; //by default 
                
                var html_thumb = '';
                if(recThumb){                             //class="timeline-event-bubble-image" 
                    html_thumb = '<img src="'+recThumb+'" style="float:left;padding-bottom:5px;padding-right:5px;">'; 
                    //'<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
                }

                //need to verify date and convert from                        
                 var dres = window.hWin.HEURIST4.util.parseDates(startDate, endDate);
                 if(dres){
                        
                        if(timeenabled<MAXITEMS){
                     
                            titem = {
                                id: dataset_name+'-'+recID, //unique id
                                group: dataset_name,
                                content: 
                                '<img src="'+window.hWin.HAPI4.iconBaseURL + iconId + 
                                           '.png"  align="absmiddle" style="padding-right:3px;"/>&nbsp;<span>'+recName+'</span>',
                                //'<span>'+recName+'</span>',
                                title: recName,
                                start: dres[0],
                                recID:recID
                            }
                            if(dres[1] && dres[0]!=dres[1]){
                                titem['end'] = dres[1];
                            }else{
                                titem['type'] = 'point';
                            }
                            titems.push(titem);
                        
                        }
                    
                        timeenabled++;
                        
                    //}
                }
                
                var shapes = (recShape && geoType!=1)?recShape:[];
                
                if(geoType!=2){
                    var main_shape = window.hWin.HEURIST4.util.parseCoordinates(type, wkt, 0);
                    if(main_shape){ //main shape
                        shapes.push(main_shape);
                    }
                }else{
                    recID = recID + "_link";
                }
                
                
                        item = {
                            title: recName,
                            start: (startDate || ''),
                            end: (endDate && endDate!=startDate)?endDate:'',
                            placemarks:[],
                            options:{

                                description: description,
                                //url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
                                //link: record.url,  //for timeline popup
                                recid: recID,
                                bkmid: _getFieldValue(record, 'bkm_ID'),
                                rectype: recTypeID,
                                iconId: iconId,
                                title: recName,
                                
                                eventIconImage: iconId + 'm.png',
                                icon: window.hWin.HAPI4.iconBaseURL + iconId + 'm.png&color='+encodeURIComponent(iconColor),
                                
                                //color on dataset level works once only - timemap bug
                                color: iconColor,
                                lineColor: iconColor,
                                fillColor: iconColor,
                                
                                start: (startDate || ''),
                                end: (endDate && endDate!=startDate)?endDate:'',
                                
                                URL:   _getFieldValue(record, 'rec_URL'),
                                thumb: html_thumb,
                                
                                info: _getFieldValue(record, 'rec_Info'),  //prepared content for map bubble or URL to script
                                
                                places: null
                                
                                //,infoHTML: (infoHTML || ''),
                            }
                        };  
                                          
                if(shapes.length>0){
                    if(mapenabled<MAXITEMS){
                        item.placemarks = shapes;
                        item.options.places = shapes;
                    }
                    mapenabled++;
                }
                if(geoType!=2 || shapes.length>0){
                    aitems.push(item);
                }

                tot++;
        }}
      
        
        var dataset = 
            {
                id: dataset_name, 
                title: dataset_name, 
                type: "basic",
                timeenabled: timeenabled,
                color: iconColor,
                visible: true, 
                mapenabled: mapenabled,
                options: { items: aitems },
                timeline:{ items:titems } //, start: min_date  ,end: max_date  }
            };

//console.log('mapitems: '+aitems.length+' of '+mapenabled+'  time:'+titems.length+' of '+timeenabled);            
            
        return dataset;
    }//end _toTimemap


   
    // some important id for record and detail types in local values
    var RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'], //1
        DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'], //5
        DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'], //6
        DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE'], //7
        DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'],     //9
        DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE'], //10
        DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE'], //11
        DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'], //3
        DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']; //28
        
   
    // find relation records of given type for recID
    // 1. search all relationship records
    // 2. check target or source fields
    // 3. check record type
    //
    // returns array of objects  {relation:recID, related:recTarget, relrt:relRecTypeID}
    //
    function _getRelationRecords(forRecID, forRecTypeID){
        var idx, relations = [];
        
        for(idx in relationship){
            if(idx)
            {
                var record = relationship[idx];
                var recID = _getFieldValue(record, 'rec_ID');
                var recTypeID   = _getFieldValue(record, 'rec_RecTypeID'),
                    recTarget, recSource, relRecTypeID; 
                    
                if(recTypeID == RT_RELATION){
                    
                    recTarget = _getFieldValue(record, DT_TARGET_RESOURCE);
                    recSource = _getFieldValue(record, DT_PRIMARY_RESOURCE);
                
                    if(recTarget==forRecID){
                        
                          if(records[recSource]){
                        
                              relRecTypeID = _getFieldValue(records[recSource], 'rec_RecTypeID');
                              
                              if(forRecTypeID && forRecTypeID != relRecTypeID) {
                                  continue;
                              }
                            
                              relations.push({relation:recID, related:recSource, relrt:relRecTypeID});
                          
                          }
                          
                    }else if(recSource==forRecID){
                        
                          if(records[recTarget]){
                        
                              relRecTypeID = _getFieldValue(records[recTarget], 'rec_RecTypeID');
                            
                              if(forRecTypeID && forRecTypeID != relRecTypeID) {
                                  continue;
                              }
                            
                              relations.push({relation:recID, related:recTarget, relrt:relRecTypeID});
                          
                          }
                    }
                }
            }
        }
        
        return relations;
    
    }
    
    /**
    * Returns field value by fieldname
    * @todo - obtain fieldtype codes from server side
    */
    function _getFieldValue(record, fldname){

        if(isnull(record)){
            return null;
        }
        
        //this is field type ID  or field name (nominal for most common fields)
        var d = record['d'];
        if(d){   
            //if fieldname is numeric index of starts with dtl_
            if(!isNaN(Number(fldname)) || fldname.indexOf("dtl_")==0){  //@todo - search detail by its code
            
                if(!isNaN(Number(fldname))){ //dt code
                    if(d[fldname] && d[fldname][0]){
                        return d[fldname][0];
                    }
                }else if(fldname=="dtl_StartDate"){
                    if(d[DT_START_DATE] && d[DT_START_DATE][0]){
                        return d[DT_START_DATE][0];
                    }else if(d[DT_DATE]){
                        return d[DT_DATE][0];
                    }
                }else if(fldname=="dtl_EndDate"){
                    return _getFieldValue(record, DT_END_DATE);
                    //if(d[11] && d[11][0]){ return d[11][0]; }
                }else if(fldname=="dtl_Description"){
                    return _getFieldValue(record, DT_SHORT_SUMMARY);
                    //if(d[3] && d[3][0]){return d[3][0];}
                    
                }else if(fldname.indexOf("dtl_Geo")==0 && d[DT_GEO_OBJECT] && d[DT_GEO_OBJECT][0]){
                    var g = d[DT_GEO_OBJECT][0].split(' ');

                    if(fldname=="dtl_Geo"){
                        g.shift()
                        return g.join(' ');
                    }else{
                        return g[0];  ////take first part of dtl_Geo field - "p wkt"
                    }
                }
                
                return null;
            }
        }

        //either take value by index or by name
        // record can be either array or object
        var idx = $.inArray(fldname, fields);
        if(idx>-1){
            return record[idx];
        }else{
            return isnull(record[fldname])?null:record[fldname]; //return null;
        }
    }
    
    function isnull(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    }
    
    function _setFieldValue(record, fldname, newvalue){

        if(!isNaN(Number(fldname))){  //@todo - search detail by its code
            var d = record['d'];
            if(!d){
                record['d'] = {};
            }
            record['d'][fldname] = [newvalue];
        }else {

            var idx = $.inArray(fldname, fields);
            if(idx>-1){
                record[idx] = newvalue;
            }else{
                record[fldname] = newvalue;
            }
        }
    }
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
        entityName:'',

        /**
        * Returns field value by fieldname for given record
        */
        fld: function(record, fldname){
            return _getFieldValue(record, fldname);
        },
        
        setFld: function(record, fldname, value){
            _setFieldValue(record, fldname, value);  
        },

        /**
        * returns record by id
        * 
        * @param recID
        */
        getById: function(recID){
            return records[recID];
        },
        
        /**
        * returns all record ids from recordset
        * 
        * @returns {Array}
        */
        getIds: function( limit ){
            
            if(limit>0){
                return order.slice(0, limit);
            }
            
            return order;
        },
        
        //get ids for given array of records
        getIds2: function( recs, limit ){
            
            var aitems = [];
            var recID;
            if(limit>0){
                for(recID in recs)
                    if(recID){
                        aitems.push(recID);
                        if(aitems.length>limit) break
                    }
            }else{
                aitems = Object.keys(recs);
                /*
                for(recID in recs)
                    if(recID){
                        aitems.push(recID);
                    }*/
            }

                
            return aitems;
        },
        
        
        /*
        getIdsChunked: function(chunk){
            var res = [];
            var aitems = [];
            var recID;
            for(recID in records)
                if(recID){
                    aitems.push(recID);
                    if(aitems.length==chunk){
                        res.push(aitems);
                        aitems = [];
                    }
                }
            if(aitems.length>0){
                res.push(aitems);
            }
            return res;
        },*/

        getBookmarkIds: function(){
            var aitems = [];
            var recID, bkmID;
            for(recID in records)
            if(recID){
                bkmID = _getFieldValue(records[recID], 'bkm_ID');
                if(bkmID>0) aitems.push(bkmID);
            }
            return aitems;
        },
        
        /**
        * Returns recordSet with the same field and structure definitions
        * 
        * @param  _records - list of objects/records
        * 
        * @returns {hRecordSet}
        */
        getSubSet: function(_records, _order){
            
            if(_records==null){
                _records = {};
            }
            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_order)){
                _order = that.getIds2(_records);    
            }
            
            return new hRecordSet({
                queryid: queryid,
                count: _order.length,
                total_count: _order.length,
                offset: 0,
                fields: fields,
                rectypes: rectypes,
                structures: structures,
                records: _records,
                order: _order
            });
        },

        //
        //
        //
        getSubSetByIds: function(rec_ids){
            var _records = {};
            //find all records
            
            if($.isEmptyObject(fields)) return null;
            
            var recID;
            if(Object.keys(records).length<rec_ids.length){

                for(recID in records)
                    if(recID && rec_ids.indexOf(recID)>-1) {
                        _records[recID] = records[recID];
                    }

            }else{
                var idx;
                for(idx=0; idx<rec_ids.length; idx++)
                {
                    recID = rec_ids[idx];
                    if(records[recID]){
                        _records[recID] = records[recID];    
                    }
                }

            }
            
            return this.getSubSet(_records);
        },
        
        //
        //  returns subset by rerquest/filter
        //
        getSubSetByRequest: function(request, structure){
            
            var _records = {}, _order=[];
            
            if(fields==null || $.isEmptyObject(fields)) return null;
            if(request==null || $.isEmptyObject(request)) return this;
            
            function __getDataType(fieldname){
                var idx;
                for (idx in structure){
                    if(structure[idx]['dtID']==fieldname){
                          return structure[idx]['dtFields']['dty_Type'];
                    }
                }
                return null;
            }
            
            var recID, fieldName, dataTypes={};
            //remove empty fields from request
            for (fieldName in request) {
                if (request.hasOwnProperty(fieldName) ){
                    if(window.hWin.HEURIST4.util.isempty(request[fieldName])) {
                        delete request[fieldName];    
                    }else{
                        //find data type
                        dataTypes[fieldName] = __getDataType(fieldName);
                        if(dataTypes[fieldName]=='freetext' || dataTypes[fieldName]=='blocktext'){
                            request[fieldName] = request[fieldName].toLowerCase();
                        }
                    }
                }
            }            

            if($.isEmptyObject(request)) return this; //return all

            //search
            for(recID in records){
                var record = records[recID];
                var isOK = true;
                for(fieldName in request){
                    if(request.hasOwnProperty(fieldName)){
                        if(dataTypes[fieldName]=='freetext' || dataTypes[fieldName]=='blocktext'){
                            
                            if(this.fld(record,fieldName).toLowerCase().indexOf(request[fieldName])<0){
                                isOK = false;
                                break;                            
                            }
                            
                        }else if(this.fld(record,fieldName)!=request[fieldName]){
                            isOK = false;
                            break;                            
                        }
                    }
                }
                if(isOK){
                    _records[recID] = record;    
                    _order.push(recID);
                }
            }
            
            return this.getSubSet(_records, _order);
        },
        
        //
        // take records from given recordset
        //
        fillHeader: function( recordset2 ){
            
            if(recordset2==null){
                return;
            }
            
            if($.isEmptyObject(fields)) fields = recordset2.getFields();
            if(!$.isEmptyObject(rectypes)) {
                rectypes2 = recordset2.getRectypes();
                if(!$.isEmptyObject(rectypes2)) {
                    jQuery.merge( rectypes2, rectypes );
                    rectypes = jQuery.unique( rectypes2 );
                }
            }else{
                rectypes = recordset2.getRectypes();
            }    
            //structures = response.structures;
            
            var records2 = recordset2.getRecords();
            var order2 = recordset2.getOrder();
            var idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //todo - check that this id is in order
                if(recid){ //&& records2[recid]){ 
                    records[recid] = records2[recid];
                }
            }
            
        },
        
        /**
        * Returns new recordSet that is the join from current and given recordset
        */
        doUnite: function(recordset2){
            if(recordset2==null){
                return that;
            }
            
            //join records
            var records2 = recordset2.getRecords();
            var order2 = recordset2.getOrder();
            
            var order_new = order, records_new = records, idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //for (recid in records2){
                if(recid && !records[recid]){
                    records_new[recid] = records2[recid];
                    order_new.push(recid);
                }
            }
            //join structures
            /* var structures2 = recordset2.getRecords();
            @todo
            for (rt_id in structures){
                if(rt_id && !structures2[rt_id]){
                    structures2[rt_id] = structures[rt_id];
                }
            }
            typedefs, names, pluralNames
            */
            
            
            /*var fields2 = recordset2.getFields();
            jQuery.merge( fields2, fields );
            fields2 = jQuery.unique( fields2 );*/

            var rectypes2 = recordset2.getRectypes();
            jQuery.merge( rectypes2, rectypes );
            rectypes2 = jQuery.unique( rectypes2 );
            
            var relationship = recordset2.getRelationship();
            jQuery.merge( relationship2, relationship );
            relationship2 = jQuery.unique( relationship2 );
            
            return new hRecordSet({
                queryid: queryid,
                count: total_count, //keep from original
                offset: 0,
                fields: fields,
                rectypes: rectypes2,
                structures: structures,
                records: records_new,
                order: order_new,
                relationship: relationship2
            });
        },
        
        /**
        * Returns the actual number of records 
        * 
        * @type Number
        */
        length: function(){
            //return Object.keys(records)
            return order.length; //$(records).length;
        },

        /**
        * get count of all records for current request
        */
        count_total: function(){
            return total_count;
        },

        offset: function(){
            return offset;
        },
        
        queryid:function(){
            return queryid;
        },

        /**
        * Get all records s
        */
        getRecords: function(){
            return records;
        },
        
        getOrder: function(){
            return order;
        },

        /**
        * Returns first record from recordSet
        */
        getFirstRecord: function(){
            
            if(order.length>0){
                return records[order[0]];
            }
            return null;
        },
        
        getLastRecord: function(){
            if(order.length>0){
                return records[order.length-1];
            }
            return null;
        },

        /**
        * record structure definitions for all rectypes in this record set
        * optional - may be empty
        */
        getStructures: function(){
            return structures;
        },
        
        getRectypes: function(){
            return rectypes;
        },
        
        getFields: function(){
            return fields;
        },
        
        //  
        //  list of record ids that belong to main request (search)
        //  since some records may belong to results of rules requests (linked, related records) 
        //  or relationship records
        //
        getMainSet: function(){
            if( !$.isEmptyObject(mainset) ){
                return mainset;
            }else{
                return order;
            }
        },

        setMainSet: function(_mainset){
            if( !$.isEmptyObject(_mainset) ){
                mainset = _mainset;
            }else{
                mainset = null;
            }
        },
        
        //
        // flag that marks if recordset has detail data for mapping and timeline
        //
        isMapEnabled: function(){
            return _isMapEnabled;
        },
        
        setMapEnabled: function(){
            _isMapEnabled = true;
        },

        //
        // keep search request that results this recordset
        //
        setRequest: function(request){
            _request = request;
        },
        
        getRequest: function(){
            return _request;
        },
        
        /* record - hRecord or rectypeID
        getRecordStructure: function(record){

        //record type
        var rectypeID = null;

        if (record || typeof(record) == "object" )
        {
        rectypeID = _getFieldValue(record, 'rty_RecTypeID');
        }else{
        rectypeID =  record;
        }

        if(rectypeID && structures[rectypeID]){
        return structures[rectypeID];
        }else{
        return null;
        }

        },
        
        
        preprocessForDigitalHarlem: function(){
          _preprocessForDigitalHarlem();  
        },
        */ 
        /**
        * Converts recordSet to OS Timemap dataset
        * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only
        * 2 - rec_Shape only
        */
        toTimemap: function(dataset_name, filter_rt, iconColor, geoType){
            return _toTimemap(dataset_name, filter_rt, iconColor, geoType);
        },
        
        setProgressInfo: function(data){
            _progress = data;
        },

        getProgressInfo: function(){
            return _progress;
        },
        
        getRelationRecords: function(forRecID, forRecTypeID){
            return _getRelationRecords(forRecID, forRecTypeID);
        },
        
        getRelationship: function(){
            return relationship;
        },
        
        makeKeyValueArray:function(titlefield){
            return _makeKeyValueArray(titlefield);
        },
        
        removeRecord:function(recID){
            delete records[recID];           //@todo check how it affect select_multi
            var idx = order.indexOf(recID);
            if(idx>=0){
                order.splice(idx,1);
                total_count = total_count-1;
            }
        },

        addRecord:function(recID, record){
            var idx = order.indexOf(recID);
            if(idx<0){ //add new
                records[recID] = [];
                records[recID][fields.length-1] = undefined;
                order.push(recID);
                total_count = total_count+1;
            }
            this.setRecord(recID, record);
        },
        
        setRecord:function(recID, record){
            var idx = order.indexOf(recID);
            if(idx>=0){
                
                if($.isPlainObject(record)){
                    var fldname;
                    for (fldname in record) {
                        if (record.hasOwnProperty(fldname) ){
                            _setFieldValue(records[recID], fldname, record[fldname]);    
                        }
                    }
                }else if($.isArray(record)){
                    records[recID] = record;
                } 
                
                
                
                
            }
        },
        
        //
        //returns data as JSON array for fancytree
        // fieldTitle, fieldLink - fields for key, title and hierarchy link
        //
        getTreeViewData:function(fieldTitle, fieldLink){
            
            /*
            source: [
    {title: "Node 1", key: "1"},
    {title: "Folder 2", key: "2", folder: true, children: [
      {title: "Node 2.1", key: "3", myOwnAttr: "abc"},
      {title: "Node 2.2", key: "4"}
    ]}
  ]
            */
            
            //find vocabs only
            var recID, vocabs = [];
            for(recID in records){
                var record = records[recID];
                var id = this.fld(record, fieldLink);
                if(!window.hWin.HEURIST4.util.isempty(id) && id>0 && $.inArray(recID, vocabs)<0) { //vocabs.indexOf(id)<0){
                    vocabs.push(id);
                }
            }
            
            function __addChilds(that, parentId){
                var recID, res = [];
                for(recID in records){
                    var record = records[recID];
                    
                    var id = that.fld(record, fieldLink);
                    if(window.hWin.HEURIST4.util.isempty(id) || id==0) id = null;
                    
                    if(parentId==id){
                        var node = {title: that.fld(record,fieldTitle), key: recID};
                        if($.inArray(recID, vocabs)>-1){
                            var children = __addChilds( that, recID );
                            if(children.length>0){
                                node['children'] = children;
                                node['folder'] = true;
                            }
                        }
                        res.push( node );
                    }
                }
                return res;
            }
            
            var res = __addChilds(this, null);
 /*           
            for(recID in records){
                var record = records[recID];
                
                var node = {title: this.fld(record,fieldTitle), key: this.fld(record,fieldId)};
                
                var parentId = this.fld(record, fieldLink);
                
                
                if(window.hWin.HEURIST4.util.isempty(parentId)){
                   res.push(node); //root
                   refs[recID] = [res.length-1];
                }else{
                    //find parent
                    var parentNodeRef = refs[recID];
                    var parentNode;
                    if(!parentNodeRef){
                        parentNode = {title:'', key:parentId, children:[]};
                        res.push(node); //root
                        refs[recID] = [res.length-1];
                    }else{
                        parentNode = res[]
                    }
                    if(!parentNode.children){
                        parentNode.children = [];
                    }
                    parentNode.children.push(node);
                }
            
            }//for
*/            
            refs = null;
            return res;
        }
    }

    _init(initdata);
    return that;  //returns object
}
