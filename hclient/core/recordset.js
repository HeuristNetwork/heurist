/**
 * @file recordset.js
 * @brief Defines the HRecordSet factory for managing collections of Heurist records.
 * @fileOverview This file provides the HRecordSet factory function, which creates objects for storing
 * and managing sets of Heurist records. An HRecordSet instance holds record data, field definitions,
 * record type information, and structural metadata. It offers methods for accessing records and their
 * fields, sorting, filtering (getSubSetByRequest), creating subsets, converting to GeoJSON, and
 * managing relationships between records within the set. It's a fundamental component for handling
 * data retrieved from server searches or other operations. It may require `temporalObjectLibrary.js`
 * for date validation and timeline conversions.
 * @see db_recsearch.php recordSearch
 * @see editing_input.js
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/* global parseWKT, $Db */ // Added $Db based on usage

/**
 * Factory function for creating HRecordSet objects.
 * HRecordSet instances store and manage collections of records, including their data,
 * field definitions, and structural metadata.
 *
 * @constructor HRecordSet
 * @param {Object|Array} initdata - Initialization data for the recordset.
 *        If an array, it's assumed to be a list of records.
 *        If an object, it should conform to the expected server response structure for a search result,
 *        containing properties like `entityName`, `count`, `offset`, `fields`, `records`, `order`, etc.
 * @returns {Object} An HRecordSet instance with methods for accessing and manipulating the record collection.
 */
function HRecordSet(initdata) {
    const _className = "HRecordSet",
    _version   = "0.4";

    let total_count = 0,   //number of records in query  - not match to  length()
    queryid = null, //unique query id
    offset = 0,
    //limit = 1000, use length()
    fields = [],       //array of field names
    fields_detail = [], //array of fieldtypes ids in details 
    records = {},      //list of records objects {recID:[], ....}
    order = [], //array of record IDs in specified order
    mainset = null, //array of record IDs that belong to main result set (without applied rules)
    
    rectypes = [],      // unique list of record types with counts
    structures = null,  //record structure definitions for all rectypes in this record set
    relationship = null, //relationship records within this recordset
    relations_ids = null,  //this is object "direct","reverse","headers"

    limit_warning = null;
    
    let _progress = null,
        _isMapEnabled = false,
        _request = null;
    
    // Internal helper function - JSDoc intentionally omitted
    function _init(response) {
        if(Array.isArray(response)){
            response = {entityName:'Records',count:response.length,offset:0,records:response};
        }
        if(response){
            that.entityName = response.entityName;           
            queryid = response.queryid;
            total_count = Number(response.count);
            offset = Number(response.offset);
            if(response['limit_warning']){ limit_warning = response.limit_warning; }
            if( !$.isEmptyObject(response.mainset) ){ mainset = response.mainset; }
            if( !$.isEmptyObject(response['fields']) ){
                fields = response.fields;
                rectypes = response.rectypes;
                structures = response.structures;
                records = response.records;
                order = Array.isArray(response.order)?response.order:[response.order];
                relationship = response.relationship;
                relations_ids = response.relations;
                fields_detail = response.fields_detail;
                _isMapEnabled = response.mapenabled;
            }else{
                if(response.order){
                    order = Array.isArray(response.order)?response.order:[response.order];    
                    records = response.records?response.records:{};   
                }else{
                    order = response.records;
                    records = {};
                }
                if(response.rectypes) rectypes = response.rectypes;
                _isMapEnabled = false;
            }
        }
        else {
            that.entityName = 'Records';           
            fields = [];
        }
    }

    // Internal helper function - JSDoc intentionally omitted
    function _makeKeyValueArray(namefield){
        let result = [];
        for(let idx in order){
            if(Object.hasOwnProperty.call(order, idx)) // Ensure idx is own property
            {
                const key = order[idx];
                let record = records[key];
                const rec_title = _getFieldValue(record, namefield);
                result.push({key:key, title:rec_title});
            }
        }        
        return result;
    }    
    
    // Internal helper function - JSDoc intentionally omitted
    function _getDetailsFieldTypes(){
        let dty_ids = null;
        if(fields_detail){
              dty_ids = fields_detail;
        }else{
            if(order.length>0){     
                let rec = records[order[0]];
                if(!isnull(rec) && rec['d']){
                    dty_ids = Object.keys(rec['d']);
                }
            }
        }
        return dty_ids;
    }

    // Internal helper function - JSDoc intentionally omitted
    function _toGeoJSON(filter_rt, geoType, max_limit){
        let localIds = window.hWin.HAPI4.sysinfo['dbconst'];
        let DT_SYMBOLOGY = localIds['DT_SYMBOLOGY'];
        let geofields = [], timefields = [];
        let dty_ids = _getDetailsFieldTypes(); 
        if(!isnull(dty_ids) && window.hWin.HEURIST4 && typeof $Db !== 'undefined' && $Db){ // Added $Db check
            for (let i=0; i<dty_ids.length; i++) {
                let dtype = $Db.dty(dty_ids[i], 'dty_Type');
                if(dtype=='date' || dtype=='year'){ timefields.push(dty_ids[i]); }
                else if(dtype=='geo'){ geofields.push(dty_ids[i]); }
            }
        }
        function __getGeoJsonFeature(record, extended, simplify){ // Nested helper
            let rec_ID = _getFieldValue(record, 'rec_ID');
            let res = {type:'Feature', id: rec_ID, properties: _getAllFields(record), geometry:null};
            let dates = [], startDate=null, endDate=null, dres=null, singleFieldName;
            for(let k=0; k<timefields.length; k++){
                let datetime = _getFieldValues(record, timefields[k]);
                if(!isnull(datetime)){
                    for(let m=0; m<datetime.length; m++){
                        if(timefields[k]==DT_START_DATE){ startDate = datetime[m]; if(singleFieldName==null){ singleFieldName = $Db.dty(timefields[k], 'dty_Name'); } }
                        else if(timefields[k]==DT_END_DATE){ endDate  = datetime[m]; }
                        else{ dres = window.hWin.HEURIST4.util.parseDates(datetime[m]); if(dres){ dates.push(dres); singleFieldName = $Db.dty(timefields[k], 'dty_Name'); } }
                    }
                }
            }
            if(startDate==null && endDate!=null){ if(dres==null){ startDate = endDate; endDate = null; }else{ startDate = dres[0];} }
            dres = window.hWin.HEURIST4.util.parseDates(startDate, endDate); if(dres){ dates.push(dres); }
            let timevalues = [];
            for(let k=0; k<dates.length; k++){
                dres = dates[k];
                let date_start = (dres[0]==null)?dres[1]:dres[0]; let date_end = null;
                if(dres[1] && date_start!=dres[1]){ date_end = dres[1]; }
                if(date_start==null) date_start = ''; if(date_end==null) date_end = '';
                timevalues.push([date_start, '', '', date_end, '']);
            }
            if(timevalues.length>0){ res['when'] = {timespans:timevalues}; }
            let recShape = _getFieldValue(record, 'rec_Shape');
            let geovalues = [];
            if(recShape && geoType!=1){ geovalues = [recShape]; }
            if(geoType!=2){
                for(let k=0; k<geofields.length; k++){
                    let geodata = _getFieldGeoValue(record, geofields[k]);
                    if(geodata){
                        for(let m=0; m<geodata.length; m++){
                            let geo_json = parseWKT(geodata[m].wkt);
                            if(geo_json){ geovalues.push(geo_json); }
                        }
                    }
                }
            }
            if(geovalues.length>1){ res['geometry'] = {type:'GeometryCollection', geometries:geovalues}; }
            else if(geovalues.length==1){ res['geometry'] = geovalues[0]; }
            let symbology = _getFieldValue(record, DT_SYMBOLOGY);
            symbology = window.hWin.HEURIST4.util.isJSON(symbology);
            if(symbology){ res['style'] = symbology; }
            return res;
        }
        let res_geo = [], res_time = [], res_geo_ids = [];
        for(let idx in records){
            if(Object.hasOwnProperty.call(records, idx)) { // Ensure idx is own property
                const record = records[idx];
                const recTypeID = Number(_getFieldValue(record, 'rec_RecTypeID'));
                if(filter_rt && recTypeID!=filter_rt) continue;
                let feature = __getGeoJsonFeature(record, 2, true);
                if(feature['when']){
                    res_time.push({rec_ID: feature.id, when: feature['when']['timespans'], rec_RecTypeID: feature.properties.rec_RecTypeID, rec_Title: feature.properties.rec_Title});
                    feature['when'] = null; delete feature['when'];
                }
                if(!feature['geometry']) continue;
                res_geo.push(feature); res_geo_ids.push(feature.id);
                if(max_limit>0 && res_geo.length>max_limit) break;
            }
        }
        return {geojson:res_geo, timeline:res_time, geojson_ids:res_geo_ids};
    }
   
    const RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'];
    const DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'];
    const DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE'];
    const DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'];
    const DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE'];
    const DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE'];
    const DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'];
    const DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];

    // Internal helper function - JSDoc intentionally omitted
    function _getLinkedRecords(forRecID, forRecTypeID){
        let record = records[forRecID];
        let dty_ids = _getDetailsFieldTypes(); 
        let links = [];
        if(!isnull(record) && !isnull(dty_ids) && window.hWin.HEURIST4 && typeof $Db !== 'undefined' && $Db){ // Added $Db check
            for (let i=0; i<dty_ids.length; i++) {
                let dtype = $Db.dty(dty_ids[i], 'dty_Type');
                if(dtype=='resource'){
                    let fldvalue = _getFieldValues(record, dty_ids[i]);
                    if(!isnull(fldvalue)){   
                         for(let m=0; m<fldvalue.length; m++){
                            let g = String(fldvalue[m]).split(','); // Ensure fldvalue[m] is string
                            for(let n=0; n<g.length; n++){
                                let relRec_ID = g[n];
                                let relRec = records[relRec_ID];
                                if(!isnull(relRec)){
                                    let relRec_RecTypeID = Number(_getFieldValue(relRec, 'rec_RecTypeID'));
                                    if(isnull(forRecTypeID) || forRecTypeID == relRec_RecTypeID) {
                                        links.push({related:relRec_ID, relation:0, rel_rt:relRec_RecTypeID}); 
                                    }
                                }
                            }
                         }
                    }
                }
            }
        }
        return links;        
    }
   
    // Internal helper function - JSDoc intentionally omitted
    function _getRelationRecords(forRecID, forRecTypeID){
        let relations = [];
        for(let idx in relationship){
            if(Object.hasOwnProperty.call(relationship, idx)) { // Ensure idx is own property
                const record = relationship[idx];
                const recID = _getFieldValue(record, 'rec_ID');
                const recTypeID = _getFieldValue(record, 'rec_RecTypeID');
                let recTarget, recSource, relRecTypeID; 
                if(recTypeID == RT_RELATION){
                    recTarget = _getFieldValue(record, DT_TARGET_RESOURCE);
                    recSource = _getFieldValue(record, DT_PRIMARY_RESOURCE);
                    if(recTarget==forRecID){
                          if(records[recSource]){
                              relRecTypeID = _getFieldValue(records[recSource], 'rec_RecTypeID');
                              if(forRecTypeID && forRecTypeID != relRecTypeID) { continue; }
                              relations.push({relation:recID, related:recSource, relrt:relRecTypeID});
                          }
                    }else if(recSource==forRecID){
                          if(records[recTarget]){
                              relRecTypeID = _getFieldValue(records[recTarget], 'rec_RecTypeID');
                              if(forRecTypeID && forRecTypeID != relRecTypeID) { continue; }
                              relations.push({relation:recID, related:recTarget, relrt:relRecTypeID});
                          }
                    }
                }
            }
        }
        return relations;
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _getFieldGeoValue(record, fldname){
        let geodata = _getFieldValues(record, fldname);
        if(!isnull(geodata)){   
             let m, res = [];
             for(m=0; m<geodata.length; m++){
                let g = String(geodata[m]).split(' '); // Ensure geodata[m] is string
                let gt_parts = g[0].split(':'); // Renamed to avoid conflict
                let geoRecID = (gt_parts && gt_parts.length==2)?gt_parts[1]:0;
                let gt_val = gt_parts[0];  //geotype // Renamed
                g.shift();
                let wkt = g.join(' ');           
                let oRes = {geotype:gt_val, wkt:wkt};
                if(geoRecID>0){ oRes['recID'] = geoRecID; }
                res.push(oRes);
             }
             return res;
        }else{ return null; }
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _getFieldValues(record, fldname){
        if(window.hWin.HEURIST4.util.isempty(fldname)) return null;
        if( (!$.isPlainObject(record)) && !isnull(record) && !Array.isArray(record)){
            if(records[record]){ record = records[record]; }
            else{ return null; }
        }
        if(isnull(record)){ return null; }
        else{
            let idx = $.inArray(fldname, fields);
            if(idx>-1){ return record[idx]; }
            else if( (isNaN(Number(fldname)) && fldname.indexOf("dtl_")!=0) && record[fldname] ){ return record[fldname]; }
            else if(record['d'] && record['d'][fldname]){ return record['d'][fldname]; }
            else{ return null; }
        }
    }

    // Internal helper function - JSDoc intentionally omitted
    function _getAllFields(record){
        let res = {};
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(fields)){
            for(let idx in fields) {
                if(Object.hasOwnProperty.call(fields, idx)) { // Ensure idx is own property
                    if(typeof record[idx]!=='undefined'){ res[fields[idx]] = record[idx]; }
                    else if(record[fields[idx]]){ res[fields[idx]] = record[fields[idx]]; }
                }
            }
            if(record['d']){ res['d'] = window.hWin.HEURIST4.util.cloneJSON(record['d']); }
        }else{ res = window.hWin.HEURIST4.util.cloneJSON(record); }
        return res;
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function isnull(obj){ // This is a local utility, not prefixed with _ but still internal to HRecordSet
        return ( (typeof obj==="undefined") || (obj===null));
    }
    
    // Internal helper function - JSDoc intentionally omitted
    function _setFieldValue(record, fldname, newvalue){
        if(!isNaN(Number(fldname))){
            let d = record['d'];
            if(!d){ record['d'] = {}; }
            if(Array.isArray(newvalue)){ record['d'][fldname] = newvalue; }
            else{ record['d'][fldname] = [newvalue]; }
        }else {
            if(Array.isArray(newvalue) && fldname!='rec_Shape'){ newvalue = (newvalue.length>0)?newvalue[0]:null; }
            let idx = $.inArray(fldname, fields);
            if(idx>-1){ record[idx] = newvalue; }
            else{ record[fldname] = newvalue; }
        }
    }
    
    //public members
    let that = {
        /** @returns {string} */ getClass: function () {return _className;},
        /** @param {string} strClass @returns {boolean} */ isA: function (strClass) {return (strClass === _className || strClass === 'hRecordSet');},
        /** @returns {string} */ getVersion: function () {return _version;},
        entityName:'',
        calcfields:{},
        getFieldVisibilites: function(record, fldId){
            let res = null;
            if(!isnull(record) && fldId>0 && record['v'] && record['v'][fldId]) { res = record['v'][fldId]; }
            return res;
        },
        fld: function(record, fldName, lang){ return _getFieldValue(record, fldName, lang); },
        values: function(record, fldName){ return _getFieldValues(record, fldName); },
        getFieldGeoValue: function(record, fldName){ return _getFieldGeoValue(record, fldName); },
        setFld: function(record, fldName, value){ _setFieldValue(record, fldName, value); },
        setFldById: function(recID, fldName, value){ if(records[recID]) _setFieldValue(records[recID], fldName, value); },
        getFldById: function(recID, fldName){ if(records[recID]){ return _getFieldValue(records[recID], fldName); } else { return null; } },
        transFld: function(recordTo, recordFrom, fldName, isNoNull){
            let value = _getFieldValue(recordFrom, fldName);
            if( window.hWin.HEURIST4.util.isempty(value) && isNoNull) { return false; }
            else{ _setFieldValue(recordTo, fldName, value); return true; }
        },
        getById: function(recID){ return records[recID]; },
        makeKeyValueArray:function(titlefield){ return _makeKeyValueArray(titlefield); },
        getRecord: function(recID){ let record = this.getById(recID); if(record){ return _getAllFields(record); } else { return null; } },
        getIds: function( limit ){ if(limit>0){ return order.slice(0, limit); } return order; },
        getIds2: function( recs, limit ){
            let aitems = []; let recID;
            if(limit>0){ for(recID in recs) if(Object.hasOwnProperty.call(recs, recID) && recID){ aitems.push(recID); if(aitems.length>limit) break; } }
            else{ aitems = Object.keys(recs); }
            return aitems;
        },
        getIdsByRectypeId: function(rty_ID){
            rty_ID = Number(rty_ID); let res = [];
            if(rty_ID>0) for(let recID in records) if(Object.hasOwnProperty.call(records, recID) && recID){
                let rec = records[recID]; let recTypeID = Number(_getFieldValue(rec, 'rec_RecTypeID'));
                if(rty_ID==recTypeID){ res.push(recID); }
            }
            return res;
        },
        getBookmarkIds: function(){
            let aitems = []; let recID, bkmID;
            for(recID in records) if(Object.hasOwnProperty.call(records, recID) && recID){
                bkmID = _getFieldValue(records[recID], 'bkm_ID');
                if(bkmID>0) aitems.push(bkmID);
            }
            return aitems;
        },
        each: function( callback ){
            for(let i=0; i<order.length; i++){
                let recID = order[i]; let record = records[recID];
                let res = callback.call(that, recID, record);
                if(res === false){ break; }
            }
        },
        each2: function( callback ){
            for(let i=0; i<order.length; i++){
                let recID = order[i]; let record = that.getRecord(recID);
                let res = callback.call(that, recID, record);
                if(res === false){ break; }
            }
        },
        getSubSet: function(_records_subset, _order_subset){ // Renamed params
            if(_records_subset==null){ _records_subset = {}; }
            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_order_subset)){ _order_subset = that.getIds2(_records_subset); }
            return new HRecordSet({ entityName: that.entityName, queryid: queryid, count: _order_subset.length, total_count: _order_subset.length, offset: 0, fields: fields, fields_detail:fields_detail, rectypes: rectypes, structures: structures, records: _records_subset, order: _order_subset });
        },
        getSubSetByIds: function(rec_ids){
            let _records_subset = {}; let _order_subset = []; // Renamed
            if($.isEmptyObject(records)) return null;
            let recID_subset; // Renamed
            if(Object.keys(records).length<rec_ids.length){
                for(recID_subset in records) if(Object.hasOwnProperty.call(records, recID_subset) && recID_subset && window.hWin.HEURIST4.util.findArrayIndex(recID_subset, rec_ids)>-1) { _records_subset[recID_subset] = records[recID_subset]; _order_subset.push(recID_subset); }
            }else{
                for(let idx=0; idx<rec_ids.length; idx++) { recID_subset = rec_ids[idx]; if(records[recID_subset]){ _records_subset[recID_subset] = records[recID_subset]; _order_subset.push(recID_subset); } }
            }
            return this.getSubSet(_records_subset, _order_subset);
        },
        sort: function(sortFields){
            let fieldName, dataTypes={};
            if(sortFields==null || $.isEmptyObject(sortFields)) return;
            for (fieldName in sortFields) {
                if (Object.hasOwn(sortFields,fieldName) ){
                    let dt_type = 'freetext';
                    if(fieldName=='rec_RecTypeID' || fieldName=='rec_ID'){ dt_type = 'integer'; }
                    else if(Number(fieldName)>0 && typeof $Db !== 'undefined' && $Db){ dt_type = $Db.dty(fieldName,'dty_Type'); } // Added $Db check
                    if(dt_type=='resource'){ dt_type = 'integer'; }
                    dataTypes[fieldName] = dt_type;
                }
            }
            if(Object.keys(dataTypes).length>0){
                order.sort(function(a,b){  
                    let res = 0;
                    for (fieldName in sortFields) {
                        if (Object.hasOwn(sortFields, fieldName) ){
                            let val1 = that.fld(records[a], fieldName); let val2 = that.fld(records[b], fieldName);
                            if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='float'){
                                if(Number(val1)!=Number(val2)){ res = sortFields[fieldName]*(Number(val1)<Number(val2)?-1:1); }
                            }else{
                                if(dataTypes[fieldName]=='date'){
                                    let dres_sort = window.hWin.HEURIST4.util.parseDates(val1, val2); // Renamed
                                    val1 = dres_sort[0]; val2 = dres_sort[1];
                                }
                                val1 = (val1 === null || typeof val1 === 'undefined') ? '' : String(val1).toLocaleLowerCase();
                                val2 = (val2 === null || typeof val2 === 'undefined') ? '' : String(val2).toLocaleLowerCase();
                                let compare = val1.localeCompare(val2);
                                if(compare !== 0){ res = sortFields[fieldName] * compare; }
                            }
                            if(res!=0){ break; }
                        }
                    }
                    return res;
                });
            }
        },
        getSubSetByRequest: function(request, structure){
            let _records_subset_req = {}, _order_req=[], that_req = this; // Renamed
            if(request==null || $.isEmptyObject(request)) return this;
            function __getDataType(fieldname, struct){
                let idx_type; // Renamed
                if(struct!=null){
                    for (idx_type in struct){ if(Object.hasOwnProperty.call(struct, idx_type)){
                        if(struct[idx_type]['children']){ return __getDataType(fieldname, struct[idx_type]['children']); }
                        else if(struct[idx_type]['dtID']==fieldname){
                              let res_type = struct[idx_type]['dtFields']['dty_Type'];  // Renamed
                              return (res_type=='resource' || (res_type=='enum' && that_req.entityName=='Records') ) ?'integer':res_type;
                        }}} return null;
                }else{ return 'freetext'; }
            }
            let fieldName_req, dataTypes_req={}, sortFields_req = [], sortFieldsOrder_req=[]; // Renamed
            let isexact_req = {}; let isnegate_req = {}; let isless_req = {}; let isgreat_req = {}; // Renamed
            for (fieldName_req in request) {
                if (Object.hasOwn(request, fieldName_req) ){
                    if(window.hWin.HEURIST4.util.isempty(request[fieldName_req])) { delete request[fieldName_req]; }
                    else if(fieldName_req.indexOf('sort:')<0){
                        dataTypes_req[fieldName_req] = __getDataType(fieldName_req, structure);
                        if(dataTypes_req[fieldName_req]=='freetext' || dataTypes_req[fieldName_req]=='blocktext' || dataTypes_req[fieldName_req]=='integer' || dataTypes_req[fieldName_req]=='enum') {
                            request[fieldName_req] = String(request[fieldName_req]).trim().toLowerCase();
                            if(request[fieldName_req].substring(0,2)=='!='){ request[fieldName_req] = request[fieldName_req].substring(2); isnegate_req[fieldName_req] = true; }
                            else if(request[fieldName_req][0]=='='){ request[fieldName_req] = request[fieldName_req].substring(1); isexact_req[fieldName_req] = true; }
                            else if(request[fieldName_req][0]=='<'){ request[fieldName_req] = request[fieldName_req].substring(1); isless_req[fieldName_req] = true; }
                            else if(request[fieldName_req][0]=='>'){ request[fieldName_req] = request[fieldName_req].substring(1); isgreat_req[fieldName_req] = true; }
                            else if(dataTypes_req[fieldName_req]=='integer' || dataTypes_req[fieldName_req]=='enum'){ isexact_req[fieldName_req] = true; }
                        }
                    }else{
                        let realFieldName_req = fieldName_req.substr(5); // Renamed
                        sortFieldsOrder_req.push(Number(request[fieldName_req]));
                        sortFields_req.push(realFieldName_req);
                        dataTypes_req[realFieldName_req] = __getDataType(realFieldName_req, structure);
                    }
                }
            }            
            if($.isEmptyObject(request)) return this;
            for(let recID_req in records){ if(Object.hasOwnProperty.call(records, recID_req)){ // Renamed
                let record_req = records[recID_req]; let isOK_req = true; // Renamed
                for(fieldName_req in request){ if(Object.hasOwnProperty.call(request, fieldName_req)){
                    if(fieldName_req.indexOf('sort:')<0){
                        let fldvalue_req = this.fld(record_req,fieldName_req); // Renamed
                        if(dataTypes_req[fieldName_req]=='freetext' || dataTypes_req[fieldName_req]=='blocktext' || dataTypes_req[fieldName_req]=='integer' || dataTypes_req[fieldName_req]=='enum'){
                            if(window.hWin.HEURIST4.util.isnull(fldvalue_req)){ isOK_req = (request[fieldName_req]=='NULL'); break; } // Compare with request value
                            else{
                                let cmp_value_req; // Renamed
                                if(dataTypes_req[fieldName_req]=='integer' || dataTypes_req[fieldName_req]=='float'){ fldvalue_req = Number(fldvalue_req); cmp_value_req = Number(request[fieldName_req]); }
                                else{ fldvalue_req = String(fldvalue_req).toLowerCase(); cmp_value_req = request[fieldName_req]; } // Ensure fldvalue is string
                                if(isnegate_req[fieldName_req]){ isOK_req = (fldvalue_req != cmp_value_req); if(!isOK_req) break; }
                                else if(isexact_req[fieldName_req]){ isOK_req = (fldvalue_req == cmp_value_req); if(!isOK_req) break; }
                                else if(isless_req[fieldName_req]){ isOK_req = (fldvalue_req < cmp_value_req); if(!isOK_req) break; }
                                else if(isgreat_req[fieldName_req]){ isOK_req = (fldvalue_req > cmp_value_req); if(!isOK_req) break; }
                                else if(String(fldvalue_req).indexOf(cmp_value_req)<0){ isOK_req = false; break; } // Ensure fldvalue is string
                            }
                        }else if(fldvalue_req!=request[fieldName_req]){ isOK_req = false; break; }
                    }}}
                if(isOK_req){ _records_subset_req[recID_req] = record_req; _order_req.push(recID_req); }
            }}
            if(sortFields_req.length>0){
                if(dataTypes_req[sortFields_req[0]]=='integer' || dataTypes_req[sortFields_req[0]]=='float'){
                    _order_req.sort(function(a,b){ return sortFieldsOrder_req[0]*(Number(that_req.fld(records[a], sortFields_req[0]))<Number(that_req.fld(records[b], sortFields_req[0])) ?-1:1); });
                }else{
                    _order_req.sort(function(a,b){
                        let val1_sort = that_req.fld(records[a], sortFields_req[0]); let val2_sort = that_req.fld(records[b], sortFields_req[0]); // Renamed
                        if(val1_sort) val1_sort = String(val1_sort).toLocaleLowerCase(); else val1_sort = ""; // Handle null/undefined
                        if(val2_sort) val2_sort = String(val2_sort).toLocaleLowerCase(); else val2_sort = ""; // Handle null/undefined
                        return sortFieldsOrder_req[0] * val1_sort.localeCompare(val2_sort);
                    });
                }
            }
            return this.getSubSet(_records_subset_req, _order_req);
        },
        fillHeader: function( recordset2 ){
            if(recordset2==null){ return; }
            if($.isEmptyObject(fields)) fields = recordset2.getFields();
            let rectypes2_fill = recordset2.getRectypes(); // Renamed
            if(!$.isEmptyObject(rectypes)) {
                if(!$.isEmptyObject(rectypes2_fill)) { jQuery.merge( rectypes2_fill, rectypes ); rectypes = jQuery.uniqueSort( rectypes2_fill );}
            }else{ rectypes = rectypes2_fill; }
            let records2_fill = recordset2.getRecords(); let order2_fill = recordset2.getOrder(); // Renamed
            let idx_fill, recid_fill; // Renamed
            for (idx_fill=0;idx_fill<order2_fill.length;idx_fill++){
                recid_fill = order2_fill[idx_fill];
                if(recid_fill){ records[recid_fill] = records2_fill[recid_fill]; }
            }
        },
        doUnite: function(recordset2, before_rec_id){
            if(recordset2==null){ return that; }
            let insert_at = -1; if(before_rec_id>0){ insert_at = window.hWin.HEURIST4.util.findArrayIndex(before_rec_id, order); }
            let records2_unite = recordset2.getRecords(); let order2_unite = recordset2.getOrder(); // Renamed
            let order_new_unite = [...order], records_new_unite = {...records}, idx_unite, recid_unite; // Renamed, deep copy for records_new
            for (idx_unite=0;idx_unite<order2_unite.length;idx_unite++){
                recid_unite = order2_unite[idx_unite];
                if(recid_unite && !records[recid_unite]){
                    records_new_unite[recid_unite] = records2_unite[recid_unite];
                    if(insert_at>=0){ order_new_unite.splice(insert_at,0,recid_unite); insert_at++; }
                    else{ order_new_unite.push(recid_unite); }
                }
            }
            let rectypes2_unite = recordset2.getRectypes(); // Renamed
            if(!rectypes2_unite) { rectypes2_unite = rectypes; }
            else{ jQuery.merge( rectypes2_unite, rectypes ); rectypes2_unite = jQuery.uniqueSort( rectypes2_unite ); }
            let relationship2_unite = recordset2.getRelationship(); // Renamed
            if(!relationship2_unite) { relationship2_unite = relationship; }
            else{ jQuery.merge( relationship2_unite, relationship ); relationship2_unite = jQuery.uniqueSort( relationship2_unite ); }
            return new HRecordSet({ entityName: that.entityName, queryid: queryid, count: Math.max(order_new_unite.length,total_count), offset: 0, fields: fields, rectypes: rectypes2_unite, structures: structures, records: records_new_unite, order: order_new_unite, relationship: relationship2_unite });
        },
        /** @returns {number} */ length: function(){ return order.length; },
        /** @returns {number} */ count_total: function(){ return total_count; },
        /** @returns {number} */ offset: function(){ return offset; },
        /** @returns {string|null} */ queryid:function(){ return queryid; },
        /** @returns {Object<string, Object>} */ getRecords: function(){ return records; },
        /** @returns {Array<string|number>} */ getOrder: function(){ return order; },
        /** @param {Array<string|number>} _order_set */ setOrder: function(_order_set){ order = _order_set; }, // Renamed
        /** @returns {Object|null} */ getFirstRecord: function(){ if(order.length>0){ return records[order[0]]; } return null; },
        /** @returns {Object|null} */ getLastRecord: function(){ if(order.length>0){ return records[order[order.length-1]]; } return null; },
        /** @returns {Object|null} */ getStructures: function(){ return structures; },
        /** @returns {Array<Object>} */ getRectypes: function(){ return rectypes; },
        /** @returns {Array<string>} */ getFields: function(){ return fields; },
        /** @param {Array<string>} _fields_set */ setFields: function(_fields_set){ fields = _fields_set; }, // Renamed
        /** @returns {Array<string>|null} */ getDetailsFieldTypes:function(){ return _getDetailsFieldTypes(); },
        /** @returns {Array<string|number>} */ getMainSet: function(){ if( !$.isEmptyObject(mainset) ){ return mainset; }else{ return order; } },
        /** @param {Array<string|number>} _mainset_param */ setMainSet: function(_mainset_param){ if( !$.isEmptyObject(_mainset_param) ){ mainset = _mainset_param; }else{ mainset = null; } }, // Renamed
        /** @returns {boolean} */ isMapEnabled: function(){ return _isMapEnabled; },
        setMapEnabled: function(){ _isMapEnabled = true; },
        /** @param {Object} request_param */ setRequest: function(request_param){ _request = request_param; }, // Renamed
        /** @returns {Object|null} */ getRequest: function(){ return _request; },
        toGeoJSON: function(filter_rt, geoType, max_limit){ return _toGeoJSON(filter_rt, geoType, max_limit); },
        /** @param {*} data_param */ setProgressInfo: function(data_param){ _progress = data_param; }, // Renamed
        /** @returns {*|null} */ getProgressInfo: function(){ return _progress; },
        getLinkedRecords: function(forRecID, forRecTypeID){ return _getLinkedRecords(forRecID, forRecTypeID); },
        getRelationRecords: function(forRecID, forRecTypeID){ return _getRelationRecords(forRecID, forRecTypeID); },
        /** @returns {Object|Array|null} */ getRelationship: function(){ return relationship; },
        /** @returns {Object|null} */ getRelations:function(){ return relations_ids; },
        removeRecord:function(recID){
            delete records[recID];
            let idx_remove = window.hWin.HEURIST4.util.findArrayIndex(recID, order); // Renamed
            if(idx_remove>=0){ order.splice(idx_remove,1); total_count = total_count-1; }
        },
        addRecord:function(recID, record, add_to_begin){
            let idx_add = window.hWin.HEURIST4.util.findArrayIndex(recID, order); // Renamed
            if(idx_add<0){
                if(fields && fields.length>0){ records[recID] = []; records[recID][fields.length-1] = undefined; }
                else{ records[recID] = {}; }
                if(add_to_begin===true){ order.unshift(recID); }
                else{ order.push(recID); }
                total_count = total_count+1;
            }
            return this.setRecord(recID, record);
        },
        addRecord2:function(recID, record){
            let idx_add2 = window.hWin.HEURIST4.util.findArrayIndex(recID, order); // Renamed
            if(idx_add2<0){ order.push(recID); total_count = total_count+1; }
            records[recID] = record;
        },
        setRecord:function(recID, record){
            let idx_set = window.hWin.HEURIST4.util.findArrayIndex(recID, order); // Renamed
            if(idx_set>=0){
                if($.isPlainObject(record)){
                    let fldname_set; // Renamed
                    for (fldname_set in record) { if (Object.hasOwn(record,fldname_set) ){ _setFieldValue(records[recID], fldname_set, record[fldname_set]); } }
                }else if(Array.isArray(record)){ records[recID] = record; }
                return records[recID];
            }else{ return this.addRecord(recID, record); }
        },
        getTreeViewData:function(fieldTitle, fieldLink, rootID){
            let recID_tree, vocabs_tree = []; // Renamed
            for(recID_tree in records){ if(Object.hasOwnProperty.call(records, recID_tree)){
                let record_tree = records[recID_tree]; // Renamed
                let parentIdLinkedByCurrentRecord_tree = this.fld(record_tree, fieldLink); // Renamed
                if(!window.hWin.HEURIST4.util.isempty(parentIdLinkedByCurrentRecord_tree) && Number(parentIdLinkedByCurrentRecord_tree)>0 ) {
                    let parentIdStr_tree = String(parentIdLinkedByCurrentRecord_tree); // Renamed
                    if(window.hWin.HEURIST4.util.findArrayIndex(parentIdStr_tree, vocabs_tree) < 0) { vocabs_tree.push(parentIdStr_tree); }
                }
            }}
            function __addChilds(that_child, parentIdToLookFor_child){ // Renamed params
                let recID_child, res_child = []; // Renamed
                for(recID_child in records){ if(Object.hasOwnProperty.call(records, recID_child)){
                    let currentRecord_child = records[recID_child]; // Renamed
                    let parentLinkOfCurrentRecord_child = that_child.fld(currentRecord_child, fieldLink); // Renamed
                    if(window.hWin.HEURIST4.util.isempty(parentLinkOfCurrentRecord_child) || parentLinkOfCurrentRecord_child == 0) parentLinkOfCurrentRecord_child = null;
                    else parentLinkOfCurrentRecord_child = String(parentLinkOfCurrentRecord_child);
                    if( (parentIdToLookFor_child === null && parentLinkOfCurrentRecord_child === null) || (parentIdToLookFor_child !== null && parentIdToLookFor_child == parentLinkOfCurrentRecord_child) ){
                        let node_child = {title: that_child.fld(currentRecord_child,fieldTitle), key: recID_child}; // Renamed
                        if(window.hWin.HEURIST4.util.findArrayIndex(String(recID_child), vocabs_tree)>-1){
                            let children_child = __addChilds( that_child, recID_child ); // Renamed
                            if(children_child.length>0){ node_child['children'] = children_child; node_child['folder'] = true; }
                        }
                        res_child.push( node_child );
                    }
                }}
                res_child.sort(function(a,b){
                    const titleA_child = String(a.title || '').toLocaleLowerCase(); const titleB_child = String(b.title || '').toLocaleLowerCase(); // Renamed
                    return titleA_child.localeCompare(titleB_child);
                });
                return res_child;
            }
            const currentRootID_tree = (rootID === null || typeof rootID === 'undefined' || rootID === 0 || rootID === '0') ? null : String(rootID); // Renamed
            let res_tree = __addChilds(this, currentRootID_tree); // Renamed
            return res_tree;
        },
        getAllChildrenIds:function(fieldLink, rootID){
            const isRootValid_child = (rootID !== null && rootID !== undefined && String(rootID).length > 0); // Renamed
            const isNumericRootPositive_child = isRootValid_child && Number(rootID) > 0; // Renamed
            if (!isNumericRootPositive_child && !(String(rootID) === '0' && isRootValid_child)) {
                 if (!isRootValid_child || (isNaN(Number(rootID))) ) { return undefined; }
            }
            const currentRootIDStr_child = String(rootID); // Renamed
            let recID_child_all, vocabs_child_all = []; // Renamed
            for(recID_child_all in records){ if(Object.hasOwnProperty.call(records, recID_child_all)){
                let record_child_all = records[recID_child_all]; // Renamed
                let parentIdFieldVal_child_all = this.fld(record_child_all, fieldLink); // Renamed
                if(!window.hWin.HEURIST4.util.isempty(parentIdFieldVal_child_all) && Number(parentIdFieldVal_child_all)>0 ) {
                    let parentIdStr_child_all = String(parentIdFieldVal_child_all); // Renamed
                    if(window.hWin.HEURIST4.util.findArrayIndex(parentIdStr_child_all, vocabs_child_all) < 0) { vocabs_child_all.push(parentIdStr_child_all); }
                }
            }}
            function __addChilds_all(that_child_all, parentIdToFind_child_all){ // Renamed params
                let currentChildrenIds_all = []; // Renamed
                for(let currentRecID_child_all in records){ if(Object.hasOwnProperty.call(records, currentRecID_child_all)){ // Renamed
                    let record_curr_child_all = records[currentRecID_child_all]; // Renamed
                    let parentLinkOfCurrentRec_child_all = that_child_all.fld(record_curr_child_all, fieldLink); // Renamed
                    if(window.hWin.HEURIST4.util.isempty(parentLinkOfCurrentRec_child_all) || parentLinkOfCurrentRec_child_all == 0) parentLinkOfCurrentRec_child_all = null;
                    else parentLinkOfCurrentRec_child_all = String(parentLinkOfCurrentRec_child_all);
                    if( (parentIdToFind_child_all === null && parentLinkOfCurrentRec_child_all === null) || (parentIdToFind_child_all !== null && parentIdToFind_child_all == parentLinkOfCurrentRec_child_all) ){
                        currentChildrenIds_all.push(currentRecID_child_all);
                        if(window.hWin.HEURIST4.util.findArrayIndex(String(currentRecID_child_all), vocabs_child_all) > -1) {
                            let descendants_all = __addChilds_all( that_child_all, currentRecID_child_all ); // Renamed
                            if(descendants_all.length>0){ currentChildrenIds_all = currentChildrenIds_all.concat(descendants_all); }
                        }
                    }
                }}
                return currentChildrenIds_all;
            }
            let res_all_children = __addChilds_all(this, currentRootIDStr_child); // Renamed
            return res_all_children;
        }
    }
    _init(initdata);
    return that;
}
