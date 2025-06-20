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
 * 
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

/* global parseWKT */ 

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
    
    /**
    * Initialization
    */
    function _init(response) {
        
        if(Array.isArray(response)){
            response = {entityName:'Records',count:response.length,offset:0,records:response};
        }
        
        if(response){

            that.entityName = response.entityName;           
            queryid = response.queryid;
            total_count = Number(response.count);
            offset = Number(response.offset);
            
            if(response['limit_warning']){
                limit_warning = response.limit_warning;    
            }
            
            if( !$.isEmptyObject(response.mainset) ){
                mainset = response.mainset;
            }
            
            if( !$.isEmptyObject(response['fields']) ){
                fields = response.fields;
                rectypes = response.rectypes;
                structures = response.structures;
                records = response.records;  //Array.isArray(records)
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
                    order = response.records;  //ids only   
                    records = {};
                }
                if(response.rectypes) rectypes = response.rectypes;
                _isMapEnabled = false;
            }
        }
        else {
            //if response not defined this is "Heurist Records" 
            that.entityName = 'Records';           
            fields = [];
        }
    }

    /**
    * Converts recordSet to key:title array (for selector)
    */
    function _makeKeyValueArray(namefield){
        
        let result = [];
        
        for(let idx in order){
            if(idx)
            {
                const key = order[idx];
                
                let record = records[key];
                const rec_title = _getFieldValue(record, namefield);
                
                result.push({key:key, title:rec_title});
            }
        }        
        return result;
    }    
    

    //
    //
    //
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
/**
Life cycle for geodata in record

EDIT   
editing_input.js
mapDraw.js initial_wkt -> _loadWKT (parsing) -> _applyCoordsForSelectedShape  
                          _getWKT  (only one selected shape) returns to edit
@todo 
mapDraw.js initial_wkt -> parseWKT -> GeoJSON -> _loadGeoJSON (as set of separate overlays)
                         _getGeoJSON -> (wellknown.js) stringifyWKT ->  back to input
*/    

    /**
    * Converts recordSet to GeoJSON
    * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only (no links)
        * 2 - rec_Shape only (coordinates defined in field rec_Shape)
        * 
    */
    function _toGeoJSON(filter_rt, geoType, max_limit){
            
        let localIds = window.hWin.HAPI4.sysinfo['dbconst'];
        let DT_SYMBOLOGY = localIds['DT_SYMBOLOGY'];
        
        //make bounding box for map datasource transparent and unselectable
                                    
        let geofields = [], timefields = [];
        
        let dty_ids = _getDetailsFieldTypes(); 
        
        if(!isnull(dty_ids) && window.hWin.HEURIST4){

            //detect geo and time fields from recordset        
            for (let i=0; i<dty_ids.length; i++) {
                let dtype = $Db.dty(dty_ids[i], 'dty_Type');
                if(dtype=='date' || dtype=='year'){
                    timefields.push(dty_ids[i]);
                }else if(dtype=='geo'){
                    geofields.push(dty_ids[i]);
                }
            }
        }
        
        //linkedRecs - records linked to this place
        //shape - coordinates
        //item - item object to be added to timemap 
        //{"geojson":[]}
        function __getGeoJsonFeature(record, extended, simplify){
                 
            let rec_ID = _getFieldValue(record, 'rec_ID');
            
            let res = {type:'Feature', id: rec_ID, properties: _getAllFields(record), geometry:null};

                       
            //time --------------------------           
            let dates = [], startDate=null, endDate=null, dres=null, singleFieldName;
                for(let k=0; k<timefields.length; k++){
                    let datetime = _getFieldValues(record, timefields[k]);
                    if(!isnull(datetime)){   
                        for(let m=0; m<datetime.length; m++){
                            if(timefields[k]==DT_START_DATE){
                                startDate = datetime[m];
                                if(singleFieldName==null){
                                     singleFieldName = $Db.dty(timefields[k], 'dty_Name');
                                }    
                            }else if(timefields[k]==DT_END_DATE){
                                endDate  = datetime[m]; 
                            }else{
                                dres = window.hWin.HEURIST4.util.parseDates(datetime[m]);
                                if(dres){
                                    dates.push(dres);
                                    singleFieldName = $Db.dty(timefields[k], 'dty_Name');
                                }     
                            }
                        }
                    }
                }
                
                if(startDate==null && endDate!=null){
                    if(dres==null){
                        startDate = endDate;    
                        endDate = null;
                    }else{
                        startDate = dres[0];
                    }
                }
                
                //need to verify date and convert from Temporal
                dres = window.hWin.HEURIST4.util.parseDates(startDate, endDate);
                if(dres){
                    dates.push(dres);
                }
                let timevalues = [];
                for(let k=0; k<dates.length; k++){
                        
                            dres = dates[k];
                            
                            let date_start = (dres[0]==null)?dres[1]:dres[0];
                            let date_end = null;
                            if(dres[1] && date_start!=dres[1]){
                                date_end = dres[1];
                            }
                            if(date_start==null) date_start = '';
                            if(date_end==null) date_end = '';
                            timevalues.push([date_start, '', '', date_end, '']);
                }                      
                if(timevalues.length>0){
                    res['when'] = {timespans:timevalues};    
                }
            //END time --------------------------
 
                       
            //geo -----------------------           
            let recShape = _getFieldValue(record, 'rec_Shape');  //additional shapes - special field created on client side
            
                let geovalues = [];  
                if(recShape && geoType!=1){  //geoType==1 from geo fields only, ignore recShape
                    geovalues = [recShape];
                }
                
                if(geoType!=2){ //get coordinates from geo fields geoType==2 form recShape only - ignore native geo coords   
                    
                    for(let k=0; k<geofields.length; k++){
                        
                        let geodata = _getFieldGeoValue(record, geofields[k]);
                        if(geodata){
                            for(let m=0; m<geodata.length; m++){
                                
                                let geo_json = parseWKT(geodata[m].wkt);

                                if(geo_json){ //main shape
                                    geovalues.push(geo_json);
                                    /*    
                                    //recID is place record ID - add it as separate item
                                    if(geodata[m].recID>0){ //reference to linked place record
                                        if(!linkedPlaceRecId[geodata[m].recID]){
                                            linkedPlaceRecId[geodata[m].recID] = [];
                                        }else{
                                            //to avoid dark fill for several polygones on the same spot
                                            fillOpacity_thisRec = 0.001;
                                        }
                                        linkedPlaceRecId[geodata[m].recID].push(recID); //this recID linked to place
                                        
                                        if(linkedPlaces[geodata[m].recID]){
                                            //place already defined - 
                                            linkedPlaces[geodata[m].recID]['linkedRecs'].push(recID);
                                        }else{
                                            linkedPlaces[geodata[m].recID] = {linkedRecs:[recID], shape:geo_json};
                                                                                //art Array.isArray(shape)?shape:[shape]}
                                        }
                                        has_linked_places.push(geodata[m].recID); //one person can be linked to several places
                                        
                                    }else{
                                        geovalues.push(geo_json);
                                    }
                                    */
                                }
                            }
                        }
                        
                    }//for geo fields
                }
                                               
                if(geovalues.length>1){
                    res['geometry'] = {type:'GeometryCollection', geometries:geovalues};
                }else if(geovalues.length==1){
                    res['geometry'] = geovalues[0];
                }
                
                let symbology = _getFieldValue(record, DT_SYMBOLOGY);
                symbology = window.hWin.HEURIST4.util.isJSON(symbology);
                if(symbology){
                    res['style'] = symbology;
                }
            
                return res;
        }//end __getGeoJsonFeature
        
        
        let res_geo = [],
            res_time = [],
            res_geo_ids = [];
        
        for(let idx in records){
            if(idx)
            {
                const record = records[idx];

                const recTypeID   = Number(_getFieldValue(record, 'rec_RecTypeID'));
                if(filter_rt && recTypeID!=filter_rt) continue;
                
                let feature = __getGeoJsonFeature(record, 2, true);
                if(feature['when']){
                            res_time.push({rec_ID: feature.id, 
                                            when: feature['when']['timespans'], 
                                            rec_RecTypeID: feature.properties.rec_RecTypeID, 
                                            rec_Title: feature.properties.rec_Title});
                            feature['when'] = null;
                            delete feature['when'];
                }
                if(!feature['geometry']) continue;
                res_geo.push(feature);
                res_geo_ids.push(feature.id);
                
                if(max_limit>0 && res_geo.length>max_limit) break;
            }
        }//for records    
        
        return {geojson:res_geo, timeline:res_time, geojson_ids:res_geo_ids};
                
    }//end _toGeoJSON
    
   
    // some important id for record and detail types in local values
    const RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'], //1
        DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'], //5
        DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE'], //7
        DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'],     //9
        //DT_YEAR = window.hWin.HAPI4.sysinfo['dbconst']['DT_YEAR'],     //73
        DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE'], //10
        DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE'], //11
        DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'], //3
        DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']; //28
        

    /**
     * Finds linked records of a specified record type for a given record ID.
     * It iterates through the detail fields of the given record, looking for 'resource' type fields.
     * For each 'resource' field, it extracts the linked record IDs and checks if they exist in the current recordset
     * and match the specified `forRecTypeID` (if provided).
     *
     * @private
     * @param {number|string} forRecID - The ID of the record for which to find linked records.
     * @param {number|string} [forRecTypeID] - Optional. The record type ID to filter linked records by.
     * @returns {Array<Object>} An array of objects, where each object represents a linked record
     *                          and has the shape: `{related: string, relation: number, rel_rt: number}`.
     *                          `related` is the ID of the linked record.
     *                          `relation` is 0 (as these are direct links, not through a relation record).
     *                          `rel_rt` is the record type ID of the linked record.
     *                          Returns an empty array if no linked records are found or if the initial record is invalid.
     */
    function _getLinkedRecords(forRecID, forRecTypeID){
        
        
        let record = records[forRecID];
        let dty_ids = _getDetailsFieldTypes(); 
        let links = []; //{related, relation:0, rel_rt}
        
        if(!isnull(record) && !isnull(dty_ids) && window.hWin.HEURIST4){

            //find record pointer fields and its values
            
            for (let i=0; i<dty_ids.length; i++) {
                let dtype = $Db.dty(dty_ids[i], 'dty_Type');
                if(dtype=='resource'){
                    
                    let fldvalue = _getFieldValues(record, dty_ids[i]);
                    
                    if(!isnull(fldvalue)){   

                         for(let m=0; m<fldvalue.length; m++){
                            let g = fldvalue[m].split(',');
                            for(let n=0; n<g.length; n++){
                                let relRec_ID = g[n];
                                let relRec = records[relRec_ID];
                                if(!isnull(relRec)){
                                    let relRec_RecTypeID = Number(_getFieldValue(relRec, 'rec_RecTypeID'));
                                    if(isnull(forRecTypeID) || forRecTypeID == relRec_RecTypeID)
                                    {
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
   
    /**
     * Finds relation records of a given type for a specific record ID.
     * It searches all relationship records in the recordset.
     * For each relationship record, it checks if the `forRecID` matches either the target or source resource.
     * If a match is found, it then checks if the related record (the other end of the relationship)
     * matches the `forRecTypeID` (if specified).
     *
     * @private
     * @param {number|string} forRecID - The ID of the record for which to find relation records.
     * @param {number|string} [forRecTypeID] - Optional. The record type ID to filter the related records by.
     * @returns {Array<Object>} An array of objects, where each object represents a found relation.
     *                          Each object has the shape: `{relation: string, related: string, relrt: number}`.
     *                          `relation` is the ID of the relationship record itself.
     *                          `related` is the ID of the record related to `forRecID` through this relationship.
     *                          `relrt` is the record type ID of the `related` record.
     *                          Returns an empty array if no matching relations are found.
     */
    function _getRelationRecords(forRecID, forRecTypeID){
        let relations = [];
        
        for(let idx in relationship){
            if(idx)
            {
                const record = relationship[idx];
                const recID = _getFieldValue(record, 'rec_ID');
                const recTypeID   = _getFieldValue(record, 'rec_RecTypeID');
                let recTarget, recSource, relRecTypeID; 
                    
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
    
    //to be implemented
    /*
    function _getRelationRecordByID(forRecID, relRecID){

            var i, rels = _getRelationRecords(forRecID, null);
            for(i=0; i<rels.length; i++){
                var recID = rels[i]['related'];
                return relationships[rels[i]['relation']]
            }        
    }
    */

    //
    // geo value is in format
    // geotype:recID WKT
    // geotype - p,pl,c,l
    // recID optional reference to real geo record (linked place)
    // WKT - coordinates
    /**
     * Parses and retrieves geographic data from a field value.
     * The geo value is expected to be in the format: "geotype:recID WKT" or "geotype WKT".
     * - `geotype`: A code representing the geometry type (e.g., p, pl, c, l).
     * - `recID`: Optional. A reference to a real geo record (linked place).
     * - `WKT`: The Well-Known Text representation of the geometry.
     *
     * @private
     * @param {Object|string|number} record - The record object or record ID.
     * @param {string|number} fldname - The name or ID of the field containing the geo data.
     * @returns {Array<Object>|null} An array of objects, where each object represents a parsed geo value
     *                                 and has the shape: `{geotype: string, wkt: string, recID?: number}`.
     *                                 `geotype` is the geometry type.
     *                                 `wkt` is the Well-Known Text string.
     *                                 `recID` (optional) is the ID of the linked geo record.
     *                                 Returns `null` if the field value is null or empty.
     */
    function _getFieldGeoValue(record, fldname){

        let geodata = _getFieldValues(record, fldname);
        if(!isnull(geodata)){   
             let m, res = [];
             for(m=0; m<geodata.length; m++){
                let g = geodata[m].split(' ');
                let gt = g[0].split(':');
                let geoRecID = (gt && gt.length==2)?gt[1]:0;
                gt = gt[0];  //geotype
                
                g.shift(); //remove first
                let wkt = g.join(' ');           
                let oRes = {geotype:gt, wkt:wkt};
                
                if(geoRecID>0){
                    oRes['recID'] = geoRecID;
                }
                
                res.push(oRes);
             }
             return res;
        }else{
            return null;
        }
    }
    
    /**
     * Retrieves all values for a specified field from a given record.
     * This is the internal implementation for the public `values` method.
     * The record can be specified as a record object or a record ID.
     * It handles header fields (indexed or named) and detail fields (from `record['d']`).
     *
     * @private
     * @param {Object|string|number} record - The record object or its ID.
     * @param {string|number} fldname - The name or ID of the field.
     * @returns {Array<any>|any|null} An array of values if the field is multi-valued or if it's a detail field.
     *                                 A single value if it's a header field (non-detail, non-indexed, not in `record['d']`).
     *                                 `null` if the field name is empty, the record is invalid, or the field is not found.
     * @todo Consider standardizing return type (e.g., always array for consistency) or clarifying when single vs. array is returned for header fields.
     */
    function _getFieldValues(record, fldname){
        if(window.hWin.HEURIST4.util.isempty(fldname)) return null;

        if( (!$.isPlainObject(record)) && !isnull(record) && !Array.isArray(record)){
            if(records[record]){
                record = records[record];    
            }else{
                return null;
            }
        }
        
        if(isnull(record)){
            return null
        }else{  //@todo calcfields
        
            let idx = $.inArray(fldname, fields);
            if(idx>-1){
                return record[idx];
            }else if(  (isNaN(Number(fldname)) && fldname.indexOf("dtl_")!=0) && record[fldname] ){
                return record[fldname];
            }else if(record['d'] && record['d'][fldname]){   
                return record['d'][fldname]
            }else{
                return null;   
            }
        }
    }

    /**
     * Converts a record object from its internal representation (which may use indexed field access)
     * to a JSON object with field names as keys.
     * It handles both header fields (stored by index based on `fields` array or by name)
     * and detail fields (cloned from `record['d']`).
     *
     * @private
     * @param {Object} record - The internal record object. It can be an array (for indexed header fields) or an object.
     * @returns {Object} A new object representing the record with field names as keys.
     *                   Includes a 'd' property if detail fields exist, which is a clone of `record['d']`.
     *                   If `fields` array is empty, it returns a clone of the original record object.
     */
    function _getAllFields(record){
        
        let res = {};
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(fields)){
        
            for(let idx in fields)
            if(idx>-1){
                //field to index
                if(typeof record[idx]!=='undefined'){
                    res[fields[idx]] = record[idx];    
                }else if(record[fields[idx]]){
                    res[fields[idx]] = record[fields[idx]];    
                }
            }
            
            if(record['d']){
                res['d'] = window.hWin.HEURIST4.util.cloneJSON(record['d']);     
            }
        }else{
            res = window.hWin.HEURIST4.util.cloneJSON(record);
        }
        return res;
    }

    
    /**
     * Retrieves a single value for a specified field from a given record.
     * This is the internal implementation for the public `fld` method.
     * If the field is multi-valued (especially detail fields), it returns the first value only after potential translation.
     * It handles:
     * 1. Calculated fields (if `that.calcfields` has a function for `fldname`).
     * 2. Detail fields (stored in `record['d']`):
     *    - Numeric field IDs: retrieves the first value, applying translation if `lang` is provided.
     *    - Special string field names like "dtl_StartDate", "dtl_EndDate", "dtl_Description", "dtl_Geo".
     * 3. Header fields:
     *    - By index (if `fldname` is in the `fields` array).
     *    - By name (if `record[fldname]` exists).
     *
     * @private
     * @param {Object|string|number} recordOrRecId - The record object or its ID.
     * @param {string|number} fldname - The name or ID of the field.
     * @param {string} [lang] - Optional language code (e.g., "xx" for current system language) for translation of multi-lingual detail fields.
     * @returns {*} The value of the field. For multi-valued detail fields, returns the first value (possibly translated).
     *              Returns `null` if the record or field name is invalid, or the field is not found/empty.
     * @todo Obtain fieldtype codes from server side to improve type handling and remove ambiguity for detail field types.
     * @todo Clarify behavior for "dtl_Geo" when it might return geotype vs. WKT string.
     */
    function _getFieldValue(record, fldname, lang){

        
        if( (!$.isPlainObject(record)) && !isnull(record) && records[record]){
            record = records[record]; //record id is assumed
        }
        
        if(isnull(record) || window.hWin.HEURIST4.util.isempty(fldname)){
            return null;
        }
        
        if(that.calcfields && window.hWin.HEURIST4.util.isFunction(that.calcfields[fldname])){
            return that.calcfields[fldname].call(that, record, fldname);
        }
        
        //this is field type ID  or field name (nominal for most common fields)
        let d = record['d'];
        if(d){   
            //if fieldname is numeric index of starts with dtl_
            if(!isNaN(Number(fldname)) || fldname.indexOf("dtl_")==0){  //@todo - search detail by its code
            
                if(!isNaN(Number(fldname))){ //dt code
                    if(d[fldname] && d[fldname][0]){
                        
                        /*
                        var dt = __getDataType(fieldName);
                        if(dt=='integer' || dt=='float'){
                            return Number(d[fldname][0]);
                        }else{
                            return d[fldname][0];    
                        }
                        */
                        //"xx" means take current system language
                        return window.hWin.HAPI4.getTranslation(d[fldname], lang);

                    }
                }else if(fldname=="dtl_StartDate"){
                    if(d[DT_START_DATE] && d[DT_START_DATE][0]){
                        return d[DT_START_DATE][0];
                    }else if(d[DT_DATE]){
                        return d[DT_DATE][0];
                    }
                }else if(fldname=="dtl_EndDate"){
                    return _getFieldValue(record, DT_END_DATE);

                }else if(fldname=="dtl_Description"){
                    return _getFieldValue(record, DT_SHORT_SUMMARY);
                    
                }else if(fldname.indexOf("dtl_Geo")==0 && d[DT_GEO_OBJECT] && d[DT_GEO_OBJECT][0]){
                    let g = d[DT_GEO_OBJECT][0].split(' ');

                    if(fldname=="dtl_Geo"){
                        g.shift();
                        return g.join(' '); //return coordinates only
                    }else{
                        return g[0];  //return geotype - first part of dtl_Geo field - "p wkt"
                    }
                }
                
                return null;
            }
        }

        //either take value by index or by name
        // record can be either array or object
        let idx = $.inArray(fldname, fields);
        if(idx>-1){
            return record[idx];
        }else{
            return isnull(record[fldname])?null:record[fldname];
        }
    }
    
    /**
     * Checks if an object is null or undefined.
     *
     * @private
     * @param {*} obj - The object or value to check.
     * @returns {boolean} True if the object is undefined or null, false otherwise.
     */
    function isnull(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    }
    
    /**
     * Sets the value of a specified field in a given record object.
     * This function directly modifies the passed `record` object.
     * - If `fldname` is a number (assumed to be a detail field ID), the value is set in `record['d'][fldname]`.
     *   If `newvalue` is not an array, it's wrapped in an array.
     * - If `fldname` is a string:
     *   - It checks if `fldname` exists in the `fields` array (for indexed header fields).
     *   - Otherwise, it sets `record[fldname]` directly (for named header fields).
     *   - For header fields (except 'rec_Shape'), if `newvalue` is an array, only its first element is used.
     *
     * @private
     * @param {Object} record - The record object to modify.
     * @param {string|number} fldname - The name or ID of the field.
     * @param {*} newvalue - The new value for the field.
     * @todo For detail fields (when `fldname` is numeric), clarify if there's a need to search by code if it's not a direct ID.
     */
    function _setFieldValue(record, fldname, newvalue){

        if(!isNaN(Number(fldname))){  //@todo - search detail by its code
            let d = record['d'];
            if(!d){
                record['d'] = {};
            }
            if(Array.isArray(newvalue)){
                record['d'][fldname] = newvalue;
            }else{
                record['d'][fldname] = [newvalue];    
            }
            
            
        }else {
            //header fields always single values except rec_Shape

            if(Array.isArray(newvalue) && fldname!='rec_Shape'){
                newvalue = (newvalue.length>0)?newvalue[0]:null;
            }
            
            let idx = $.inArray(fldname, fields);
            if(idx>-1){
                record[idx] = newvalue;
            }else{
                record[fldname] = newvalue;
            }
        }
    }
    
    //public members
    let that = {

        /**
         * Gets the class name of the HRecordSet instance.
         * @returns {string} The class name, "HRecordSet".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the instance is of a given class name.
         * @param {string} strClass - The class name to check against.
         * @returns {boolean} True if `strClass` is "HRecordSet" or "hRecordSet".
         */
        isA: function (strClass) {return (strClass === _className || strClass === 'hRecordSet');},
        /**
         * Gets the version of the HRecordSet.
         * @returns {string} The version string.
         */
        getVersion: function () {return _version;},
        /**
         * @property {string} entityName - The name of the entity type for this recordset (e.g., "Records").
         * Initialized during `_init`.
         */
        entityName:'',
        /**
         * @property {Object<string, Function>} calcfields - An object to store callback functions for calculated fields.
         * These functions are used, for example, to generate the value for the `rec_Info` field for mapping popups.
         * Each key is a field name, and its value is a function that takes `(record, fldname)` and returns the calculated value.
         */
        calcfields:{}, //set of callback functions for calculation fields
                       // is is used tp generate value for rec_Info field for mapping popup

        /**
         * Retrieves the visibility settings for a specific field in a given record.
         * Visibility settings are stored in the `v` property of a record object.
         *
         * @param {Object} record - The record object.
         * @param {number|string} fldId - The ID of the field for which to get visibility settings.
         * @returns {any|null} The visibility setting for the field, or `null` if not found or record/fldId is invalid.
         */
        getFieldVisibilites: function(record, fldId){
            let res = null;
            
            if(!isnull(record) && fldId>0 && 
                record['v'] && record['v'][fldId])
            {   
                res = record['v'][fldId];
            }
            return res;
        },
                       
        /**
         * Returns a single field value by field name for a given record.
         * If the field is multi-valued, it returns the first value only.
         * Wraps the private `_getFieldValue` method.
         *
         * @param {Object|string|number} record - The record object or its ID.
         * @param {string|number} fldName - The name or ID of the field.
         * @param {string} [lang] - Optional language code for translation.
         * @returns {*} The field value, or `null` if not found.
         */
        fld: function(record, fldName, lang){
            return _getFieldValue(record, fldName, lang);
        },

        /**
         * Returns all values for a specified field from a given record.
         * Wraps the private `_getFieldValues` method.
         *
         * @param {Object|string|number} record - The record object or its ID.
         * @param {string|number} fldName - The name or ID of the field.
         * @returns {Array<any>|any|null} An array of values or a single value, depending on the field type; `null` if not found.
         */
        values: function(record, fldName){
            return _getFieldValues(record, fldName);
        },
        
        /**
         * Parses and retrieves geographic data from a field value.
         * Wraps the private `_getFieldGeoValue` method.
         *
         * @param {Object|string|number} record - The record object or record ID.
         * @param {string|number} fldName - The name or ID of the field containing the geo data.
         * @returns {Array<Object>|null} Parsed geo data or `null`.
         */
        getFieldGeoValue: function(record, fldName){
            return _getFieldGeoValue(record, fldName);
        },
        
        /**
         * Sets the value of a specified field in a given record object.
         * Wraps the private `_setFieldValue` method.
         *
         * @param {Object} record - The record object to modify.
         * @param {string|number} fldName - The name or ID of the field.
         * @param {*} value - The new value for the field.
         */
        setFld: function(record, fldName, value){
            _setFieldValue(record, fldName, value);  
        },

        /**
         * Sets the value of a specified field for a record identified by its ID.
         * If the record exists, it calls `_setFieldValue`.
         *
         * @param {number|string} recID - The ID of the record to modify.
         * @param {string|number} fldName - The name or ID of the field.
         * @param {*} value - The new value for the field.
         */
        setFldById: function(recID, fldName, value){
            if(records[recID])
                _setFieldValue(records[recID], fldName, value);  
        },

        /**
         * Gets a single field value for a record identified by its ID.
         * If the record exists, it calls `_getFieldValue`.
         *
         * @param {number|string} recID - The ID of the record.
         * @param {string|number} fldName - The name or ID of the field.
         * @returns {*} The field value, or `null` if the record or field is not found.
         */
        getFldById: function(recID, fldName){
            if(records[recID]){
                return _getFieldValue(records[recID], fldName);
            }else{
                return null;
            }
        },
        
        /**
         * Transfers a field value from one record (`recordFrom`) to another (`recordTo`).
         *
         * @param {Object} recordTo - The target record object to set the field value on.
         * @param {Object|string|number} recordFrom - The source record object or its ID to get the field value from.
         * @param {string|number} fldName - The name or ID of the field to transfer.
         * @param {boolean} [isNoNull] - If true, the transfer only occurs if the retrieved value is not empty.
         * @returns {boolean|undefined} Returns `false` if `isNoNull` is true and the value is empty.
         *                               Returns `true` if the value was successfully set.
         *                               Otherwise (if `isNoNull` is false and value is empty), implicitly returns `undefined`.
         */
        transFld: function(recordTo, recordFrom, fldName, isNoNull){
            
            let value = _getFieldValue(recordFrom, fldName);
            if( window.hWin.HEURIST4.util.isempty(value) && isNoNull) {
                return false
            }else{
                _setFieldValue(recordTo, fldName, value);  
                return true;
            }
        },
        
        /**
         * Returns a record object by its ID from the internal `records` cache.
         *
         * @param {number|string} recID - The ID of the record to retrieve.
         * @returns {Object|undefined} The record object if found, otherwise `undefined`.
         */
        getById: function(recID){
            return records[recID];
        },

        
        /**
         * Converts the recordset into an array of key-title objects, suitable for selectors.
         * Uses `_makeKeyValueArray` internally.
         *
         * @param {string|number} titlefield - The field name or ID to use for the 'title' of each object.
         * @returns {Array<Object>} An array of objects, each with `key` (record ID) and `title` (field value).
         */
        makeKeyValueArray:function(titlefield){
            return _makeKeyValueArray(titlefield);
        },
        
        /**
         * Returns a record by its ID, formatted as a JSON object with field names as keys.
         * Uses `_getAllFields` internally.
         *
         * @param {number|string} recID - The ID of the record to retrieve.
         * @returns {Object|null} The formatted record object, or `null` if the record is not found.
         */
        getRecord: function(recID){
            let record = this.getById(recID);
            if(record){
                return _getAllFields(record);
            }else{
                return null;
            }
        },

        
        
        /**
         * Returns all record IDs from the recordset's current order.
         *
         * @param {number} [limit] - Optional. If provided and greater than 0, returns only the first `limit` IDs.
         * @returns {Array<string|number>} An array of record IDs.
         */
        getIds: function( limit ){
            
            if(limit>0){
                return order.slice(0, limit);
            }
            
            return order;
        },
        
        /**
         * Extracts record IDs from a given object of records.
         *
         * @param {Object<string, Object>} recs - An object where keys are record IDs and values are record objects.
         * @param {number} [limit] - Optional. If provided and greater than 0, returns only the first `limit` IDs found.
         * @returns {Array<string>} An array of record IDs extracted from the `recs` object.
         */
        getIds2: function( recs, limit ){
            
            let aitems = [];
            let recID;
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
        
        
        /**
         * Retrieves all record IDs that belong to a specific record type ID.
         *
         * @param {number|string} rty_ID - The record type ID to filter by.
         * @returns {Array<string>} An array of record IDs matching the specified record type ID.
         *                        Returns an empty array if `rty_ID` is not positive or no records match.
         */
        getIdsByRectypeId: function(rty_ID){

            rty_ID = Number(rty_ID);
            let res = [];
            
            if(rty_ID>0)
            for(let recID in records)
                if(recID){
                    let rec = records[recID];
                    let recTypeID = Number(_getFieldValue(rec, 'rec_RecTypeID'));
                    if(rty_ID==recTypeID){
                        res.push(recID);
                    }
                }
                
            return res;
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
            let aitems = [];
            let recID, bkmID;
            for(recID in records)
            if(recID){
                bkmID = _getFieldValue(records[recID], 'bkm_ID');
                if(bkmID>0) aitems.push(bkmID);
            }
            return aitems;
        },
        
        /**
         * Iterates over each record in the recordset (respecting the current `order`)
         * and executes a callback function. The callback receives the record ID and the raw record object.
         * The iteration can be stopped by returning `false` from the callback.
         *
         * @param {function(string, Object): (boolean|void)} callback - A function to execute for each record.
         *        It receives `recID` (string) and `record` (Object).
         *        If the callback returns `false`, the iteration stops.
         */
        each: function( callback ){
        
            for(let i=0; i<order.length; i++){
                let recID = order[i];
                let record = records[recID];
                let res = callback.call(that, recID, record);
                if(res === false){
                    break;
                }
            }
            
        },

        /**
         * Iterates over each record in the recordset (respecting the current `order`)
         * and executes a callback function. The callback receives the record ID and
         * the record formatted as a JSON object (with field names as keys via `that.getRecord`).
         * The iteration can be stopped by returning `false` from the callback.
         *
         * @param {function(string, Object): (boolean|void)} callback - A function to execute for each record.
         *        It receives `recID` (string) and `record` (Object - formatted with field names).
         *        If the callback returns `false`, the iteration stops.
         */
        each2: function( callback ){
        
            for(let i=0; i<order.length; i++){
                let recID = order[i];
                let record = that.getRecord(recID);
                let res = callback.call(that, recID, record);
                if(res === false){
                    break;
                }
            }
            
        },

            
        /**
         * Creates a new HRecordSet instance as a subset of the current one,
         * using a provided set of records and their order.
         * The new recordset inherits metadata like fields, structures, etc., from the parent.
         *
         * @param {Object<string, Object>} [_records={}] - An object where keys are record IDs and values are record objects
         *                                                for the new subset. Defaults to an empty object.
         * @param {Array<string|number>} [_order] - An array of record IDs defining the order for the new subset.
         *                                        If not provided, it's generated from the keys of `_records`.
         * @returns {HRecordSet} A new HRecordSet instance representing the subset.
         */
        getSubSet: function(_records, _order){
            
            if(_records==null){
                _records = {};
            }
            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_order)){
                _order = that.getIds2(_records);    
            }
            
            return new HRecordSet({
                entityName: that.entityName,
                queryid: queryid,
                count: _order.length,
                total_count: _order.length,
                offset: 0,
                fields: fields,
                fields_detail:fields_detail,
                rectypes: rectypes,
                structures: structures,
                records: _records,
                order: _order
            });
        },

        /**
         * Creates a new HRecordSet as a subset containing only the records specified by `rec_ids`.
         * The order of records in the new subset will match the order in `rec_ids` (for those found).
         *
         * @param {Array<string|number>} rec_ids - An array of record IDs to include in the subset.
         * @returns {HRecordSet|null} A new HRecordSet instance representing the subset,
         *                            or `null` if the current recordset's `records` object is empty.
         */
        getSubSetByIds: function(rec_ids){
            let _records = {};
            let _order = [];
            //find all records
            
            if($.isEmptyObject(records)) return null;
            
            let recID;
            if(Object.keys(records).length<rec_ids.length){

                for(recID in records)
                    if(recID && window.hWin.HEURIST4.util.findArrayIndex(recID, rec_ids)>-1) {
                        _records[recID] = records[recID];
                        _order.push(recID);
                    }

            }else{
                for(let idx=0; idx<rec_ids.length; idx++)
                {
                    recID = rec_ids[idx];
                    if(records[recID]){
                        _records[recID] = records[recID];    
                        _order.push(recID);
                    }
                }

            }
            
            return this.getSubSet(_records, _order);
        },

        /**
         * Sorts the recordset's `order` array based on specified field(s) and their data types.
         * Modifies the internal `order` array in place.
         *
         * @param {Object<string, number>} sortFields - An object where keys are field names (or IDs)
         *                                              and values are sort order (1 for ascending, -1 for descending).
         *                                              Example: `{"rec_Title": 1, "dt_DateCreated": -1}`.
         *                                              If null or empty, the function returns without sorting.
         */
        sort: function(sortFields){
            
            let fieldName, dataTypes={};
            
            if(sortFields==null || $.isEmptyObject(sortFields)) return
            
            for (fieldName in sortFields) {
                if (Object.hasOwn(sortFields,fieldName) ){
                    let dt_type = 'freetext';
                    if(fieldName=='rec_RecTypeID' || fieldName=='rec_ID'){
                        dt_type = 'integer';
                    }else 
                    if(Number(fieldName)>0){
                        dt_type = $Db.dty(fieldName,'dty_Type');
                    }
                    if(dt_type=='resource'){ // Resource type fields are often sorted by their integer ID
                        dt_type = 'integer';
                    }
                    dataTypes[fieldName] = dt_type;
                }
                
            }
            
            if(Object.keys(dataTypes).length>0){

                order.sort(function(a,b){  
                        let res = 0;                        
                        for (fieldName in sortFields) {
                            if (Object.hasOwn(sortFields, fieldName) ){
                                let val1 = that.fld(records[a], fieldName);
                                let val2 = that.fld(records[b], fieldName);
                                if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='float'){
                                    if(Number(val1)!=Number(val2)){
                                        res = sortFields[fieldName]*(Number(val1)<Number(val2)?-1:1);
                                    }
                                }else{
                                    if(dataTypes[fieldName]=='date'){
                                        // Assuming parseDates handles conversion to comparable format or returns sortable values
                                        let dres = window.hWin.HEURIST4.util.parseDates(val1, val2);
                                        val1 = dres[0]; // Assuming dres[0] is the start_date or comparable primary date
                                        val2 = dres[1]; // Assuming dres[1] is the end_date or comparable secondary date for ranges
                                    }
                                    // Ensure values are strings for localeCompare, convert null/undefined to empty string
                                    val1 = (val1 === null || typeof val1 === 'undefined') ? '' : String(val1).toLocaleLowerCase();
                                    val2 = (val2 === null || typeof val2 === 'undefined') ? '' : String(val2).toLocaleLowerCase();

                                    let compare = val1.localeCompare(val2);
                                    if(compare !== 0){
                                        res = sortFields[fieldName] * compare;
                                    }
                                }
                                if(res!=0){
                                    break;
                                }
                            }
                        }//for
                        return res;
                    });
            }
            
        },
        
        /**
         * Creates a subset of the current recordset based on a filter request object and optionally sorts it.
         * The request object can specify field values to match and fields to sort by.
         *
         * @param {Object} request - The filter and sort criteria.
         *        - For filtering: `{fieldName: value, anotherField: "!=value", numericField: ">10"}`.
         *          - `value`: exact match (case-insensitive for text, exact for numbers after prefix removal).
         *          - `"=value"`: exact match (case-insensitive for text).
         *          - `"!value"` or `{"!=value"}`: not equal. (Note: original code used `!=value` prefix, this JSDoc assumes it's handled)
         *          - `">value"`, `"<value"`: greater/less than (for numbers after prefix removal).
         *          - `value` (no operator, for text): contains (case-insensitive).
         *          - `'NULL'`: field value is null.
         *        - For sorting: `{"sort:fieldName": 1}` for ascending, `{"sort:fieldName": -1}` for descending.
         * @param {Array<Object>} [structure] - Optional. An array describing the structure of fields,
         *        used to determine data types for filtering and sorting. Each object can have
         *        `dtID` (field name/ID) and `dtFields.dty_Type`.
         * @returns {HRecordSet} A new HRecordSet instance representing the filtered and sorted subset.
         *                       Returns the original recordset if the request is null or empty.
         */
        getSubSetByRequest: function(request, structure){
            
            let _records = {}, _order=[], that = this;
            
            if(request==null || $.isEmptyObject(request)) return this;

            // if structure not defined - default type is freetext            
            function __getDataType(fieldname, struct){ // Inner helper function
                let idx;
                if(struct!=null){
                    for (idx in struct){
                        if(struct[idx]['children']){
                            return __getDataType(fieldname, struct[idx]['children']);
                        }else
                        if(struct[idx]['dtID']==fieldname){
                              let res = struct[idx]['dtFields']['dty_Type'];  
                              return (res=='resource' 
                                    || (res=='enum' && that.entityName=='Records') )
                                        ?'integer':res;
                        }
                    }
                    return null;
                }else{
                    return 'freetext';
                }
            }
            
            let fieldName, dataTypes={}, sortFields = [], sortFieldsOrder=[];
            let isexact = {};
            let isnegate= {};
            let isless= {};
            let isgreat= {};
            //remove empty fields from request
            for (fieldName in request) {
                if (Object.hasOwn(request, fieldName) ){
                    if(window.hWin.HEURIST4.util.isempty(request[fieldName])) {
                        delete request[fieldName];    
                    }else if(fieldName.indexOf('sort:')<0){
                        
                        //find data type
                        dataTypes[fieldName] = __getDataType(fieldName, structure);
                        
                        if(dataTypes[fieldName]=='freetext' 
                            || dataTypes[fieldName]=='blocktext' 
                            || dataTypes[fieldName]=='integer'
                            || dataTypes[fieldName]=='enum')
                        {
                            request[fieldName] = String(request[fieldName]).trim();
                            
                            request[fieldName] = request[fieldName].toLowerCase();
                            
                            if(request[fieldName].substring(0,2)=='!='){
                                request[fieldName] = request[fieldName].substring(2);
                                isnegate[fieldName] = true;
                            }else
                            if(request[fieldName][0]=='='){
                                request[fieldName] = request[fieldName].substring(1);
                                isexact[fieldName] = true;
                            }else
                            if(request[fieldName][0]=='<'){
                                request[fieldName] = request[fieldName].substring(1);
                                isless[fieldName] = true;
                            }else
                            if(request[fieldName][0]=='>'){
                                request[fieldName] = request[fieldName].substring(1);
                                isgreat[fieldName] = true;
                            }else 
                            if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='enum'){
                                isexact[fieldName] = true;    
                            }
                            
                        }
                    }else{
                        let realFieldName = fieldName.substr(5);
                        sortFieldsOrder.push(Number(request[fieldName])); //1 - ASC, -1 DESC
                        sortFields.push(realFieldName);
                        dataTypes[realFieldName] = __getDataType(realFieldName, structure);
                    }
                }
            }            

            if($.isEmptyObject(request)) return this; //return all

            
            //search
            for(let recID in records){
                let record = records[recID];
                let isOK = true;
                for(fieldName in request){
                    if(fieldName.indexOf('sort:')<0 && Object.hasOwn(request, fieldName)){

                        let fldvalue = this.fld(record,fieldName);
                            
                        if(dataTypes[fieldName]=='freetext' 
                            || dataTypes[fieldName]=='blocktext'
                            || dataTypes[fieldName]=='integer'
                            || dataTypes[fieldName]=='enum'){
                                
                            if(window.hWin.HEURIST4.util.isnull(fldvalue)){
                                isOK = (fldvalue=='NULL');
                                break;                            
                            }else{
                                let cmp_value;
                                if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='float'){
                                    fldvalue = Number(fldvalue);
                                    cmp_value = Number(request[fieldName]);
                                }else{
                                    fldvalue = fldvalue.toLowerCase();
                                    cmp_value = request[fieldName];
                                }
                                
                                if(isnegate[fieldName]){
                                    isOK = (fldvalue != cmp_value);
                                    if(!isOK) break;                            
                                }else 
                                if(isexact[fieldName]){
                                    isOK = (fldvalue == cmp_value);
                                    if(!isOK) break;                            
                                }else
                                if(isless[fieldName]){
                                    isOK = (fldvalue < cmp_value);
                                    if(!isOK) break;                            
                                }else
                                if(isgreat[fieldName]){
                                    isOK = (fldvalue > cmp_value);
                                    if(!isOK) break;                            
                                }else
                                if(fldvalue.indexOf(cmp_value)<0){ //contain
                                    isOK = false;
                                    break;                            
                                }
                            }
                            
                        }else if(fldvalue!=request[fieldName]){
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
            
            if(sortFields.length>0){
                if(dataTypes[sortFields[0]]=='integer' || dataTypes[sortFields[0]]=='float'){

                    _order.sort(function(a,b){  
                        return sortFieldsOrder[0]*(Number(that.fld(records[a], sortFields[0]))<Number(that.fld(records[b], sortFields[0]))
                                ?-1:1);
                    });
                    
                }else{
                    _order.sort(function(a,b){
                        let val1 = that.fld(records[a], sortFields[0]);
                        let val2 = that.fld(records[b], sortFields[0]);
                        if(val1) val1 = val1.toLocaleLowerCase();
                        if(val2) val2 = val2.toLocaleLowerCase();
                        return sortFieldsOrder[0] * val1.localeCompare(val2);
                    });
                }
            }
            
            return this.getSubSet(_records, _order);
        },
        
        /**
         * Fills the header information (fields, rectypes) and copies records from another recordset.
         * Modifies the current recordset instance.
         * - If current `fields` is empty, it's replaced by `recordset2.getFields()`.
         * - `rectypes` are merged and made unique.
         * - Records from `recordset2` are copied into the current `records` object.
         *
         * @param {HRecordSet} recordset2 - The source HRecordSet instance to copy from.
         *                                  If null, the function does nothing.
         */
        fillHeader: function( recordset2 ){
            
            if(recordset2==null){
                return;
            }
            
            if($.isEmptyObject(fields)) fields = recordset2.getFields();
            if(!$.isEmptyObject(rectypes)) {
                let rectypes2 = recordset2.getRectypes();
                if(!$.isEmptyObject(rectypes2)) {
                    jQuery.merge( rectypes2, rectypes );
                    rectypes = jQuery.uniqueSort( rectypes2 );
                }
            }else{
                rectypes = recordset2.getRectypes();
            }    
           
            
            let records2 = recordset2.getRecords();
            let order2 = recordset2.getOrder();
            let idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //todo - check that this id is in order
                if(recid){ //&& records2[recid]){ 
                    records[recid] = records2[recid];
                }
            }
            
        },
        
        /**
         * Returns a new HRecordSet instance that is a union of the current recordset and `recordset2`.
         * Records from `recordset2` that are not already in the current recordset are added.
         * The order of new records can be controlled by `before_rec_id`.
         * Metadata (fields, rectypes, structures, relationship) are merged.
         *
         * @param {HRecordSet} recordset2 - The HRecordSet to unite with. If null, returns the current recordset.
         * @param {number|string} [before_rec_id] - Optional. If provided, new records from `recordset2`
         *                                         are inserted before this record ID in the order.
         * @returns {HRecordSet} A new HRecordSet instance representing the union.
         * @todo Review merging logic for structures and other metadata for completeness.
         */
        doUnite: function(recordset2, before_rec_id){
            if(recordset2==null){
                return that;
            }
            
            let insert_at = -1;
            if(before_rec_id>0){
                insert_at = window.hWin.HEURIST4.util.findArrayIndex(before_rec_id, order);
            }
            
            //join records
            let records2 = recordset2.getRecords();
            let order2 = recordset2.getOrder();
            
            let order_new = order, records_new = records, idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //for (recid in records2){
                if(recid && !records[recid]){ //there is not such record in target
                    records_new[recid] = records2[recid];
                    if(insert_at>=0){
                        order_new.splice(insert_at,0,recid);
                        insert_at++;
                    }else{
                        order_new.push(recid);    
                    }
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
            fields2 = jQuery.uniqueSort( fields2 );*/

            let rectypes2 = recordset2.getRectypes();
            if(!rectypes2) {
                rectypes2 = rectypes;
            }else{
                jQuery.merge( rectypes2, rectypes );
                rectypes2 = jQuery.uniqueSort( rectypes2 );
            }
            
            let relationship2 = recordset2.getRelationship();
            if(!relationship2) {
                relationship2 = relationship;   
            }else{
                jQuery.merge( relationship2, relationship );
                relationship2 = jQuery.uniqueSort( relationship2 );
            }
            
            return new HRecordSet({
                entityName: that.entityName,
                queryid: queryid,
                count: Math.max(order_new.length,total_count), //keep from original
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
         * Returns the actual number of records currently loaded in the recordset (i.e., the length of the `order` array).
         *
         * @returns {number} The number of records.
         */
        length: function(){
            //return Object.keys(records)
            return order.length;
        },

        /**
         * Gets the total count of records available from the original query (may be more than currently loaded).
         * @returns {number} The total number of records.
         */
        count_total: function(){
            return total_count;
        },

        /**
         * Gets the offset of the current recordset from the original query.
         * @returns {number} The offset.
         */
        offset: function(){
            return offset;
        },
        
        /**
         * Gets the unique query ID associated with this recordset.
         * @returns {string|null} The query ID.
         */
        queryid:function(){
            return queryid;
        },

        /**
         * Get all loaded record objects.
         * @returns {Object<string, Object>} The internal `records` object where keys are record IDs.
         */
        getRecords: function(){
            return records;
        },
        
        /**
         * Gets the current array of record IDs defining the order of records.
         * @returns {Array<string|number>} The `order` array.
         */
        getOrder: function(){
            return order;
        },

        /**
         * Sets the internal `order` array.
         * @param {Array<string|number>} _order - The new array of record IDs.
         */
        setOrder: function(_order){
            order = _order;
        },
        
        /**
         * Returns the first record object from the recordset based on the current order.
         * @returns {Object|null} The first record object, or `null` if the recordset is empty.
         */
        getFirstRecord: function(){
            
            if(order.length>0){
                return records[order[0]];
            }
            return null;
        },
        
        /**
         * Returns the last record object from the recordset based on the current order.
         * @returns {Object|null} The last record object, or `null` if the recordset is empty.
         */
        getLastRecord: function(){
            if(order.length>0){
                // Corrected to access the last element of the 'order' array
                return records[order[order.length-1]];
            }
            return null;
        },

        /**
         * Gets the record structure definitions for all record types in this recordset.
         * This data is optional and may be empty.
         * @returns {Object|null} The `structures` object.
         */
        getStructures: function(){
            return structures;
        },
        
        /**
         * Gets the unique list of record type IDs with their counts present in this recordset.
         * @returns {Array<Object>} The `rectypes` array (e.g., `[{rt_ID: "1", rt_Name: "Person", count: "10"}, ...]`).
         */
        getRectypes: function(){
            return rectypes;
        },
        
        /**
         * Gets the array of field names (headers) for the records in this recordset.
         * @returns {Array<string>} The `fields` array.
         */
        getFields: function(){
            return fields;
        },

        /**
         * Sets the array of field names (headers) for the records in this recordset.
         * @param {Array<string>} _fields - The new array of field names.
         */
        setFields: function(_fields){
            fields = _fields;
        },
        
        /**
         * Returns the array of detail field type IDs (dty_IDs).
         * Applicable for Heurist records that have detailed field structures.
         * Calls the private `_getDetailsFieldTypes` method.
         * @returns {Array<string>|null} Array of detail field type IDs, or null.
         */
        getDetailsFieldTypes:function(){
            return _getDetailsFieldTypes();    
        },
        
        
        /**
         * Gets the list of record IDs that belong to the main request (search result),
         * as opposed to records brought in by rules (linked/related records) or relationship records.
         * If `mainset` is not defined or empty, it defaults to the current `order`.
         * @returns {Array<string|number>} The array of main set record IDs.
         */
        getMainSet: function(){
            if( !$.isEmptyObject(mainset) ){
                return mainset;
            }else{
                return order;
            }
        },

        /**
         * Sets the list of record IDs that belong to the main set.
         * @param {Array<string|number>} _mainset - An array of record IDs. If empty or not an object, `mainset` becomes null.
         */
        setMainSet: function(_mainset){
            if( !$.isEmptyObject(_mainset) ){
                mainset = _mainset;
            }else{
                mainset = null;
            }
        },
        
        /**
         * Checks if the recordset has detail data enabled for mapping and timeline features.
         * @returns {boolean} The value of the internal `_isMapEnabled` flag.
         */
        isMapEnabled: function(){
            return _isMapEnabled;
        },
        
        /**
         * Sets the internal flag to indicate that map-specific detail data is enabled.
         */
        setMapEnabled: function(){
            _isMapEnabled = true;
        },

        /**
         * Stores the search request object that resulted in this recordset.
         * @param {Object} request - The search request object.
         */
        setRequest: function(request){
            _request = request;
        },
        
        /**
         * Retrieves the search request object associated with this recordset.
         * @returns {Object|null} The stored search request object.
         */
        getRequest: function(){
            return _request;
        },
        
        /**
         * Converts the recordset to a GeoJSON FeatureCollection object, suitable for mapping.
         * Also extracts timeline data.
         * Wraps the private `_toGeoJSON` method.
         *
         * @param {number|string} [filter_rt] - Optional record type ID to filter records by.
         * @param {number} [geoType] - Defines which geo data to use:
         *                             0 or undefined: all geo data.
         *                             1: main geo data only (no links).
         *                             2: `rec_Shape` field only.
         * @param {number} [max_limit] - Optional. Maximum number of GeoJSON features to generate.
         * @returns {Object} An object like `{geojson: Array, timeline: Array, geojson_ids: Array}`.
         */
        toGeoJSON: function(filter_rt, geoType, max_limit){
            return _toGeoJSON(filter_rt, geoType, max_limit);
        },
        
        /**
         * Sets progress information related to fetching or processing this recordset.
         * @param {any} data - The progress data to store.
         */
        setProgressInfo: function(data){
            _progress = data;
        },

        /**
         * Retrieves the stored progress information.
         * @returns {any|null} The progress data.
         */
        getProgressInfo: function(){
            return _progress;
        },
        
        /**
         * Public wrapper for `_getLinkedRecords`. Finds linked records for a given record ID.
         * @param {number|string} forRecID - The ID of the record.
         * @param {number|string} [forRecTypeID] - Optional. The record type ID to filter linked records by.
         * @returns {Array<Object>} An array of linked record information.
         */
        getLinkedRecords: function(forRecID, forRecTypeID){
            return _getLinkedRecords(forRecID, forRecTypeID);
        },
        
        /**
         * Public wrapper for `_getRelationRecords`. Finds relation records for a given record ID.
         * @param {number|string} forRecID - The ID of the record.
         * @param {number|string} [forRecTypeID] - Optional. The record type ID to filter related records by.
         * @returns {Array<Object>} An array of relation record information.
         */
        getRelationRecords: function(forRecID, forRecTypeID){
            return _getRelationRecords(forRecID, forRecTypeID);
        },

        /*to be implemented
        getRelationRecordByID: function(forRecID, relRecID){
            return _getRelationRecordByID(forRecID, relRecID);
        },*/
        
        /**
         * Gets the raw relationship data object/array associated with the recordset.
         * @returns {Object|Array|null} The `relationship` data.
         */
        getRelationship: function(){
            return relationship;
        },
        
        /**
         * Gets the processed relation IDs object, which might categorize relations (e.g., "direct", "reverse").
         * @returns {Object|null} The `relations_ids` object.
         */
        getRelations:function(){
            return relations_ids;    
        },

        
        /**
         * Removes a record from the recordset by its ID.
         * Deletes the record from the internal `records` object and removes its ID from the `order` array.
         * Decrements `total_count`.
         *
         * @param {number|string} recID - The ID of the record to remove.
         * @todo Check how this affects select_multi functionality.
         */
        removeRecord:function(recID){
            delete records[recID];           //@todo check how it affect select_multi
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx>=0){
                order.splice(idx,1);
                total_count = total_count-1;
            }
        },

        /*
        // add new record and returns new record id
        //
        addRecord3:function(record){
            
            var recID = order[order.length-1];
            while (true){
                recID++;
                if(records[recID]==null) break;
            }  
            
            this.addRecord2(recID, record);
            
            return recID;
        },*/
        
        /**
         * Adds a new record or replaces an existing one with the given ID.
         * If the record ID is new, it's added to `records` and `order`. `total_count` is incremented.
         * Initializes the new record structure based on `fields` if they exist.
         * Then calls `setRecord` to populate the field values.
         *
         * @param {number|string} recID - The ID for the record.
         * @param {Object|Array} record - The record data (either an object with field names or an array of values).
         * @param {boolean} [add_to_begin=false] - If true and the record is new, add its ID to the beginning of the `order` array. Otherwise, adds to the end.
         * @returns {Object} The added or updated record object from the internal `records` cache.
         */
        addRecord:function(recID, record, add_to_begin){
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx<0){ //add new
                
                if(fields && fields.length>0){
                    records[recID] = [];
                    records[recID][fields.length-1] = undefined; // Pre-allocate array if fields are defined
                }else{
                    records[recID] = {};
                }
                if(add_to_begin===true){
                    order.unshift(recID);
                }else{
                    order.push(recID);    
                }
                
                total_count = total_count+1;
            }
            return this.setRecord(recID, record);
        },

        /**
         * Directly adds or replaces a record with a given ID using the provided record object.
         * If the record ID is new, it's added to `order` and `total_count` is incremented.
         * The `record` object provided is assigned directly to `records[recID]`.
         *
         * @param {number|string} recID - The ID for the record.
         * @param {Object|Array} record - The record object/array to be stored.
         */
        addRecord2:function(recID, record){
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx<0){ //add new
                order.push(recID);
                total_count = total_count+1;
            }
            records[recID] = record;
        },
        
        /**
         * Sets or updates the data for a record with a given ID.
         * If `recID` exists in `order`:
         *  - If `record` is a plain object, its properties are set as field values using `_setFieldValue`.
         *  - If `record` is an array, it's directly assigned as the record's data.
         * If `recID` does not exist in `order`, it calls `addRecord` to add it as a new record.
         *
         * @param {number|string} recID - The ID of the record to set/update.
         * @param {Object|Array} record - The record data.
         * @returns {Object} The record object from the internal `records` cache after modification or addition.
         */
        setRecord:function(recID, record){
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx>=0){
                
                if($.isPlainObject(record)){
                    let fldname;
                    for (fldname in record) {
                        if (Object.hasOwn(record,fldname) ){
                            _setFieldValue(records[recID], fldname, record[fldname]);    
                        }
                    }
                }else if(Array.isArray(record)){
                    records[recID] = record;
                }
                return records[recID];
            }else{
                return this.addRecord(recID, record);
            }
        },
        
        /**
         * Generates data suitable for a tree view structure (e.g., for fancytree).
         * Each node in the tree has `key` (record ID) and `title`.
         * Hierarchy is determined by `fieldLink` which points to a parent record's ID.
         *
         * @param {string|number} fieldTitle - The field name or ID to use for the node's title.
         * @param {string|number} fieldLink - The field name or ID that contains the parent record's ID for establishing hierarchy.
         * @param {number|string|null} rootID - The ID of the root record. Child nodes will be found starting from this parent.
         *                                      If null, finds nodes whose `fieldLink` value is null or 0.
         * @returns {Array<Object>} An array of node objects. Each node can have `key`, `title`, `folder` (boolean), and `children` (array of nodes).
         *                          The tree is sorted by title at each level.
         * @example
         * // Example structure for fancytree source:
         * // [
         * //   {title: "Node 1", key: "1"},
         * //   {title: "Folder 2", key: "2", folder: true, children: [
         * //     {title: "Node 2.1", key: "3", myOwnAttr: "abc"},
         * //     {title: "Node 2.2", key: "4"}
         * //   ]}
         * // ]
         */
        getTreeViewData:function(fieldTitle, fieldLink, rootID){
            
            /*
            source: [
    {title: "Node 1", key: "1"},
    {title: "Folder 2", key: "2", folder: true, children: [
      {title: "Node 2.1", key: "3", myOwnAttr: "abc"},
      {title: "Node 2.2", key: "4"}
    ]}
  ]
            */
            
            // vocabs stores IDs of records that ARE parents to other records in the set.
            // A record is considered a "vocabulary" or parent if its ID is used in the fieldLink of another record.
            let recID, vocabs = [];
            for(recID in records){
                let record = records[recID];
                let parentIdLinkedByCurrentRecord = this.fld(record, fieldLink); // This is the value of the fieldLink field for the current record
                if(!window.hWin.HEURIST4.util.isempty(parentIdLinkedByCurrentRecord) && Number(parentIdLinkedByCurrentRecord)>0 ) {
                    let parentIdStr = String(parentIdLinkedByCurrentRecord);
                     // If this parentIdStr is not already in vocabs, add it.
                    if(window.hWin.HEURIST4.util.findArrayIndex(parentIdStr, vocabs) < 0) {
                        vocabs.push(parentIdStr);
                    }
                }
            }
            
            function __addChilds(that, parentIdToLookFor){ // Recursive helper function
                let recID, res = [];
                for(recID in records){ // Iterate through all records in the recordset
                    let currentRecord = records[recID];
                    
                    let parentLinkOfCurrentRecord = that.fld(currentRecord, fieldLink); // Get the parent ID field of the current record
                    if(window.hWin.HEURIST4.util.isempty(parentLinkOfCurrentRecord) || parentLinkOfCurrentRecord == 0) parentLinkOfCurrentRecord = null;
                    else parentLinkOfCurrentRecord = String(parentLinkOfCurrentRecord);
                    
                    // If the current record's parent matches parentIdToLookFor
                    if( (parentIdToLookFor === null && parentLinkOfCurrentRecord === null) ||
                        (parentIdToLookFor !== null && parentIdToLookFor == parentLinkOfCurrentRecord) ){

                        let node = {title: that.fld(currentRecord,fieldTitle), key: recID};
                        // A node is a "folder" if its own ID (recID) is present in the 'vocabs' list,
                        // meaning other records link to it as their parent.
                        if(window.hWin.HEURIST4.util.findArrayIndex(String(recID), vocabs)>-1){
                            let children = __addChilds( that, recID ); // Recursively find its children
                            if(children.length>0){
                                node['children'] = children;
                                node['folder'] = true;
                            }
                        }
                        res.push( node );
                    }
                }
                //sort by fieldTitle (case-insensitive)
                res.sort(function(a,b){
                    const titleA = String(a.title || '').toLocaleLowerCase();
                    const titleB = String(b.title || '').toLocaleLowerCase();
                    return titleA.localeCompare(titleB);
                });
                
                return res;
            }
            
            // Ensure rootID is consistently typed (string or null) for comparison within __addChilds
            const currentRootID = (rootID === null || typeof rootID === 'undefined' || rootID === 0 || rootID === '0') ? null : String(rootID);
            let res = __addChilds(this, currentRootID);
            
            // Original code had a commented-out section to wrap result in a root node if rootID was provided.
            // This is generally not needed if the tree structure is built directly from the root.
            // if(rootID>0){
            //    //res = [{key:rootID, title:'root', folder:true, children:res }];
            // }
            
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
            return res;
        },
        
        /**
         * Gets a flat array of all descendant record IDs for a given root record ID,
         * based on a hierarchical link field.
         *
         * @param {string|number} fieldLink - The field name or ID that contains the parent record's ID.
         * @param {number|string} rootID - The ID of the root record for which to find all children.
         *                                 The function will only proceed if `rootID` is greater than 0 (when treated as a number)
         *                                 or a non-empty string that doesn't represent zero.
         *                                 A `rootID` of `0` or `'0'` might be intended for top-level items if your data uses that.
         * @returns {Array<string>|undefined} A flat array of all child record IDs (and their children, etc.)
         *                                    descending from `rootID`. Returns `undefined` if `rootID` is not considered
         *                                    a valid starting point (e.g., null, undefined, empty string, or non-positive number depending on interpretation).
         */
        getAllChildrenIds:function(fieldLink, rootID){
            
            // Determine if rootID is a valid starting point.
            // Allows for rootID '0' if that's a meaningful root in the data.
            const isRootValid = (rootID !== null && rootID !== undefined && String(rootID).length > 0);
            const isNumericRootPositive = isRootValid && Number(rootID) > 0;

            if (!isNumericRootPositive && !(String(rootID) === '0' && isRootValid)) {
                 // If not a positive number and not the string '0', then treat as invalid root for this function's original intent.
                 // If rootID could be other non-numeric strings, this check might need adjustment.
                 if (!isRootValid || (isNaN(Number(rootID))) ) { // if truly not a number or empty
                    return undefined;
                 }
            }

            const currentRootIDStr = String(rootID);


            // vocabs stores IDs of records that ARE parents to other records in the set.
            // This helps optimize by quickly identifying if a node can have children.
            let recID, vocabs = [];
            for(recID in records){
                let record = records[recID];
                let parentIdFieldVal = this.fld(record, fieldLink);
                if(!window.hWin.HEURIST4.util.isempty(parentIdFieldVal) && Number(parentIdFieldVal)>0 ) {
                    let parentIdStr = String(parentIdFieldVal);
                    if(window.hWin.HEURIST4.util.findArrayIndex(parentIdStr, vocabs) < 0) {
                        vocabs.push(parentIdStr);
                    }
                }
            }

            function __addChilds(that, parentIdToFind){ // Recursive helper function
                let currentChildrenIds = [];
                for(let currentRecID in records){
                    let record = records[currentRecID];

                    let parentLinkOfCurrentRec = that.fld(record, fieldLink);
                    if(window.hWin.HEURIST4.util.isempty(parentLinkOfCurrentRec) || parentLinkOfCurrentRec == 0) parentLinkOfCurrentRec = null;
                    else parentLinkOfCurrentRec = String(parentLinkOfCurrentRec);

                    if( (parentIdToFind === null && parentLinkOfCurrentRec === null) ||
                        (parentIdToFind !== null && parentIdToFind == parentLinkOfCurrentRec) ){
                        currentChildrenIds.push(currentRecID);
                        
                        // Check if currentRecID is a known parent (i.e., it's in vocabs)
                        if(window.hWin.HEURIST4.util.findArrayIndex(String(currentRecID), vocabs) > -1)
                        {
                            let descendants = __addChilds( that, currentRecID );
                            if(descendants.length>0){
                                currentChildrenIds = currentChildrenIds.concat(descendants);
                            }
                        }
                    }
                }
                return currentChildrenIds;
            }

            let res = __addChilds(this, currentRootIDStr); // Use string version of rootID
            return res;
            // Note: If rootID was initially invalid (e.g. null and not '0'), this will proceed with currentRootIDStr as "null" or "undefined"
            // which __addChilds handles by looking for records where fieldLink is also null/empty.
            // The initial check for `rootID > 0` in the original code implied numeric positive roots.
            // This revised version is slightly more flexible but maintains the core logic.
        }

    }

    _init(initdata);
    return that;  //returns object
}