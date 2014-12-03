/**
* Storage of records and its definitions (fields and structure)
* 
* @see recordSearch in db_recsearch.php
* @param initdata
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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
    records = null,      //list of records objects {recId:[], ....}
    rectypes = [],      // unique list of record types
    structures = null;  //record structure definitions for all rectypes in this record set

    /**
    * Initialization
    */
    function _init(response) {

        queryid = response.queryid;
        total_count = Number(response.count);
        offset = Number(response.offset);
        fields = response.fields;
        rectypes = response.rectypes;
        records = response.records;  //$.isArray(records)
        structures = response.structures;
        //@todo - merging
    }

    /**
    * convert wkt to
    * format - 0 timemap, 1 google
    * 
    * @todo 2 - kml 
    * @todo 3 - OpenLayers 
    */
    function _parseCoordinates(type, wkt, format) {

        if(type==1 && typeof google.maps.LatLng != "function") {
            return null;
        }

        var matches = null;

        switch (type) {
            case "p":
            case "point":
                matches = wkt.match(/POINT\((\S+)\s+(\S+)\)/i);
                break;
            case "r":  //rectangle
            case "rect":
                matches = wkt.match(/POLYGON\(\((\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*\S+\s+\S+\)\)/i);
                break;

            case "c":  //circle
            case "circle":
                matches = wkt.match(/LINESTRING\((\S+)\s+(\S+),\s*(\S+)\s+\S+,\s*\S+\s+\S+,\s*\S+\s+\S+\)/i);
                break;

            case "l":  //polyline
            case "polyline":
            case "path":
                matches = wkt.match(/LINESTRING\((.+)\)/i);
                if (matches){
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
                }
                break;

            case "pl": //polygon
            case "polygon":
                matches = wkt.match(/POLYGON\(\((.+)\)\)/i);
                if (matches) {
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
                }
                break;
        }


        var bounds, southWest, northEast,
        shape  = null,
        points = []; //google points

        if(matches && matches.length>0){

            switch (type) {
                case "p":
                case "point":

                    if(format==0){
                        shape = { point:{lat: parseFloat(matches[2]), lon:parseFloat(matches[1]) } };
                    }else{
                        point = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));

                        bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(point.lat() - 0.5, point.lng() - 0.5),
                            new google.maps.LatLng(point.lat() + 0.5, point.lng() + 0.5));
                        points.push(point);
                    }

                    break;

                case "r":  //rectangle
                case "rect":

                    if(matches.length<6){
                        matches.push(matches[3]);
                        matches.push(matches[4]);
                    }

                    var x0 = parseFloat(matches[0]);
                    var y0 = parseFloat(matches[2]);
                    var x1 = parseFloat(matches[5]);
                    var y1 = parseFloat(matches[6]);

                    if(format==0){
                        shape  = [
                            {lat: y0, lon: x0},
                            {lat: y0, lon: x1},
                            {lat: y1, lon: x1},
                            {lat: y1, lon: x0},
                        ];

                        shape = {polygon:shape};
                    }else{

                        southWest = new google.maps.LatLng(y0, x0);
                        northEast = new google.maps.LatLng(y1, x1);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);

                        points.push(southWest, new google.maps.LatLng(y0, x1), northEast, new google.maps.LatLng(y1, x0));
                    }

                    break;

                case "c":  //circle
                case "circle":  //circle

                    if(format==0){

                        var x0 = parseFloat(matches[1]);
                        var y0 = parseFloat(matches[2]);
                        var radius = parseFloat(matches[3]) - parseFloat(matches[1]);

                        shape = [];
                        for (var i=0; i <= 40; ++i) {
                            var x = x0 + radius * Math.cos(i * 2*Math.PI / 40);
                            var y = y0 + radius * Math.sin(i * 2*Math.PI / 40);
                            shape.push({lat: y, lon: x});
                        }
                        shape = {polygon:shape};

                    }else{
                        /* ARTEM TODO
                        var centre = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
                        var oncircle = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[3]));
                        setstartMarker(centre);
                        createcircle(oncircle);

                        //bounds = circle.getBounds();
                        */
                    }

                    break;

                case "l":  ///polyline
                case "path":
                case "polyline":

                case "pl": //polygon
                case "polygon":

                    shape = [];

                    var j;
                    var minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
                    for (j=0; j < matches.length; ++j) {
                        var match_matches = matches[j].match(/(\S+)\s+(\S+)(?:,|$)/);

                        var point = {lat:parseFloat(match_matches[2]), lon:parseFloat(match_matches[1])};

                        if(format==0){
                            shape.push(point);
                        }else{
                            points.push(new google.maps.LatLng(points.lat, points.lon));
                        }

                        if (point.lat < minLat) minLat = point.lat;
                        if (point.lat > maxLat) maxLat = point.lat;
                        if (point.lon < minLng) minLng = point.lon;
                        if (point.lon > maxLng) maxLng = point.lon;
                    }

                    if(format==0){
                        shape = (type=="l" || type=="polyline")?{polyline:shape}:{polygon:shape};
                    }else{
                        southWest = new google.maps.LatLng(minLat, minLng);
                        northEast = new google.maps.LatLng(maxLat, maxLng);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);
                    }
            }

        }

        return (format==0)?shape:{bounds:bounds, points:points};
    }//end parseCoordinates

    /**
    * Converts recordSet to OS Timemap dataset
    */
    function _toTimemap(){

        var aitems = [];
        var recID, item, shape;
        for(recID in records){
            if(recID)
            {
                var record = records[recID];

                var
                recName     = _getFieldValue(record, 'rec_Title'),
                recTypeID   = _getFieldValue(record, 'rec_RecTypeID'),

                startDate   = _getFieldValue(record, 'dtl_StartDate'),
                endDate     = _getFieldValue(record, 'dtl_EndDate'),
                description = _getFieldValue(record, 'dtl_Description'),
                type        = _getFieldValue(record, 'dtl_GeoType'),
                wkt         = _getFieldValue(record, 'dtl_Geo');

                item = {
                    start: (startDate || ''),
                    end: (endDate && endDate!=startDate)?endDate:'',
                    placemarks:[],
                    title: recName,
                    options:{

                        description: description,
                        //url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
                        //link: record.url,  //for timeline popup
                        recid: recID,
                        rectype: recTypeID,
                        //thumb: record.thumb_url,
                        icon: top.HAPI4.iconBaseURL + recTypeID + '.png',
                        start: (startDate || ''),
                        end: (endDate && endDate!=startDate)?endDate:''
                        //,infoHTML: (infoHTML || ''),
                    }
                };

                shape = _parseCoordinates(type, wkt, 0);
                if(shape){
                    item.placemarks.push(shape);
                }

                aitems.push(item);
        }}

        var dataset = [
            {
                type: "basic",
                options: { items: aitems }
            }
        ];

        return dataset;
    }//end _toTimemap

    /**
    * Returns field value by fieldname
    */
    function _getFieldValue(record, fldname){

        if(!isNaN(Number(fldname)) || fldname.indexOf("dtl_")==0){  //@todo - search detail by its code
            var d = record['d'];
            if(d){
                if(!isNaN(Number(fldname))){ //dt code
                    return d[fldname];
                }else if(fldname=="dtl_StartDate"){
                    if(d[10] && d[10][0]){
                        return d[10][0];
                    }else if(d[9]){
                        return d[9][0];
                    }
                }else if(fldname=="dtl_EndDate"){
                    if(d[11] && d[11][0]){
                        return d[11][0];
                    }
                }else if(fldname.indexOf("dtl_Geo")==0 && d[28] && d[28][0]){
                    var g = d[28][0].split(' ');

                    if(fldname=="dtl_Geo"){
                        g.shift()
                        return g.join(' ');
                    }else{
                        return g[0];
                    }
                }
            }
            return null;
        }

        var idx = $.inArray(fldname, fields);
        if(idx>-1){
            return record[idx];
        }else{
            return null;
        }
    }

    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},


        /**
        * Returns field value by fieldname for given record
        */
        fld: function(record, fldname){
            return _getFieldValue(record, fldname);
        },

        /**
        * returns record by id
        * 
        * @param recID
        */
        getById: function(recID){

            if (true || $.inArray(recID, records)) {
                return records[recID];
            }
            /*
            var i;
            for(i=0; i<records.length; i++){
            if(this.fld(records[i], 'rec_ID') == recID){
            return records[i];
            }
            }
            */
            return null;

        },
        
        /**
        * returns all record ids from recordset
        * 
        * @returns {Array}
        */
        getIds: function( limit ){
            var aitems = [];
            var recID;
            if(limit>0){
                for(recID in records)
                    if(recID){
                        aitems.push(recID);
                        if(aitems.length>limit) break
                    }
            }else{
                for(recID in records)
                    if(recID){
                        aitems.push(recID);
                    }
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
        getSubSet: function(_records){
            if(_records==null){
                _records = {};
            }
            return new hRecordSet({
                count: Object.keys(records).length, //$(_records).length,
                offset: 0,
                fields: fields,
                rectypes: rectypes,
                structures: structures,
                records: _records
            });
        },

        getSubSetByIds: function(rec_ids){
            var _records = {};
            //find all records
            
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
        
        /**
        * Returns new recordSet that is the join from current and given recordset
        */
        doUnite: function(recordset2){
            if(recordset2==null){
                return that;
            }
            
            //join records
            var records2 = recordset2.getRecords();
            var records_new = records;
            for (recid in records2){
                if(recid){ //&& !records[recid]){
                    records_new[recid] = records2[recid];
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
            
            return new hRecordSet({
                queryid: queryid,
                count: total_count, //keep from original
                offset: 0,
                fields: fields,
                rectypes: rectypes2,
                structures: structures,
                records: records_new
            });
        },
        
        /**
        * Returns the actual number of records 
        * 
        * @type Number
        */
        length: function(){
            return Object.keys(records).length; //$(records).length;
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

        /**
        * Returns first record from recordSet
        */
        getFirstRecord: function(){
            var recid;
            for (recid in records){
                if(recid){
                    return records[recid];
                }
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

        },*/

        /**
        * Converts recordSet to OS Timemap dataset
        */
        toTimemap: function(){
            return _toTimemap();
        }

    }

    _init(initdata);
    return that;  //returns object
}
