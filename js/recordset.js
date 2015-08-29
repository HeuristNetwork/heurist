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
    records = null,      //list of records objects {idx:[], ....}
    order = [], //array of record IDs in specified order
    
    rectypes = [],      // unique list of record types
    structures = null;  //record structure definitions for all rectypes in this record set

    /**
    * Initialization
    */
    function _init(response) {

        if(response){
        
        queryid = response.queryid;
        total_count = Number(response.count);
        offset = Number(response.offset);
        fields = response.fields;
        rectypes = response.rectypes;
        records = response.records;  //$.isArray(records)
        structures = response.structures;
        order = response.order;
        //@todo - merging
        
        }
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
    function _toTimemap(dataset_name, filter_rt){

        var aitems = [], titems = [];
        var item, titem, shape, idx, 
            min_date = Number.MAX_VALUE, 
            max_date = Number.MIN_VALUE;
        var mapenabled = 0,
            timeenabled = 0;
            
        dataset_name = dataset_name || "main";
         
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
                type        = _getFieldValue(record, 'dtl_GeoType'),
                wkt         = _getFieldValue(record, 'dtl_Geo'),
                recThumb    = _getFieldValue(record, 'rec_ThumbnailURL'),
                
                iconId      = _getFieldValue(record, 'rec_Icon');
                if(!iconId) iconId = recTypeID; //by default 
                
                var html_thumb = '';
                if(recThumb){                             //class="timeline-event-bubble-image" 
                    html_thumb = '<img src="'+recThumb+'" style="float:left;padding-bottom:5px;padding-right:5px;">'; 
                    //'<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
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
                        icon: top.HAPI4.iconBaseURL + iconId + 'm.png',
                        
                        start: (startDate || ''),
                        end: (endDate && endDate!=startDate)?endDate:'',
                        
                        URL:   _getFieldValue(record, 'rec_URL'),
                        thumb: html_thumb,
                        
                        info: _getFieldValue(record, 'rec_Info')
                        
                        //,infoHTML: (infoHTML || ''),
                    }
                };
                
                //need to verify date and convert from                        
                 var dres = _parseDates(startDate, endDate);
                 if(dres){
                        
                        titem = {
                            id: recID,
                            group: dataset_name,
                            content: '<img src="'+top.HAPI4.iconBaseURL + iconId + '.png"  align="absmiddle"/>&nbsp;<span>'+recName+'</span>',
                            title: recName,
                            start: dres[0],
                        }
                        if(dres[1] && dres[0]!=dres[1]){
                            titem['end'] = dres[1];
                        }else{
                            titem['type'] = 'point';
                        }
                        
                        
                    
                         timeenabled++;
                         titems.push(titem);
                         
                    //}
                }
                

                shape = _parseCoordinates(type, wkt, 0);
                if(shape){
                    item.placemarks.push(shape);
                    item.options.places = [shape];
                    mapenabled++;
                }else{
                    item.options.places = [];
                }

                aitems.push(item);
                
                tot++;
        }}

        var dataset = [
            {
                id: dataset_name, 
                type: "basic",
                timeenabled: timeenabled,
                mapenabled: mapenabled,
                options: { items: aitems },
                timeline:{ items:titems } //, start: min_date  ,end: max_date  }
            }
        ];

        return dataset;
    }//end _toTimemap

   // @todo change temporal to moment.js for conversion
   function _parseDates(start, end){
         if(window['Temporal'] && start){   
                //Temporal.isValidFormat(start)){
                
                            // for VISJS timeline
                            function __forVis(dt){
                                if(dt){
                                    var res = dt.toString("yyyy-MM-ddTHH:mm:ssz");
                                    /*if(res.indexOf("-",1)<0){
                                        res = res+"-01-01";
                                        if(res.indexOf("-")==0){
                                            //res = res 
                                        }
                                    }*/
                                    return res;
                                }else{
                                    return "";
                                }
                                
                            }    
                
                
                            try{
                                var temporal;
                                if(start && start.search(/VER=/)){
                                    temporal = new Temporal(start);
                                    if(temporal){
                                        var dt = temporal.getTDate('PDB');  //probable begin
                                        if(!dt) dt = temporal.getTDate('TPQ');
                                        
                                        if(dt){ //this is range - find end date
                                            var dt2 = temporal.getTDate('PDE'); //probable end
                                            if(!dt2) dt2 = temporal.getTDate('TAQ');
                                            end = __forVis(dt2);
                                        }else{
                                            dt = temporal.getTDate('DAT');  //simple date
                                        }
                                        
                                        if(dt){
                                            start = __forVis(dt);
                                        }else{
                                            return null;
                                        }
                                    }
                                }
                                if(start!="" && end && end.search(/VER=/)){
                                    temporal = new Temporal(end);
                                    if(temporal){
                                        var dt = temporal.getTDate('PDE'); //probable end
                                        if(!dt) dt = temporal.getTDate('TAQ');
                                        if(!dt) dt = temporal.getTDate('DAT');
                                        end = __forVis(dt);
                                    }
                                }
                            }catch(e){
                                return null;
                            }
                            return [start, end];
         }
         return null;
   }
   
   
    
    
    //@todo - obtain codes from server side
    var RT_ADDRESS = 12,
        RT_EVENT = 14,
        RT_PERSON = 10,
        RT_RELATION = 1;
        DT_TARGET_RESOURCE = 5,
        DT_RELATION_TYPE = 6,
        DT_PRIMARY_RESOURCE = 7,
        DT_TITLE = 1,
        DT_NOTES = 3,
        DT_DESCRIPTION = 4,
        DT_DATE = 9,
        DT_STARTDATE = 10,
        DT_ENDDATE = 11,
        DT_GEO = 28,
        DT_EVENT_TYPE = 74,
        DT_EVENT_TIME = 75, 
        DT_EVENT_TIME_END = 76;
        
    var DH_RECORDTYPE = 13;
    
    //
    // special preparation for Digital Harlem
    //
    // need to change address records
    //  1) find relation master type (event or person)
    //  2) compose description
    //  3) change icon
    function _preprocessForDigitalHarlem(){
        
        //NOTE: address may relates to the same person several times (residency in separate periods of time) 
        // this version (as well as old DH) does not support it - it shows the last relation only!
        
        
        function __addpart(pref, val){
              if(top.HEURIST4.util.isempty(val)){
                  return '';
              }else{
                  return pref+val;
              }
        }
        function __addterm(pref, val, domain){
            
            if(top.HEURIST4.util.isempty(domain)){
                domain = 'enum';
            }
            if(!top.HEURIST4.util.isempty(val)){
                val = top.HEURIST4.terms.termsByDomainLookup[domain][val];
                if(top.HEURIST4.util.isArrayNotEmpty(val)){
                    val = val[0];
                }
            }
            return __addpart(pref, val);
        }
        
        
        //1. find address records
        var idx, i, j, relrec, related;
        
        for(idx in records){
            if(idx)
            {
                var record = records[idx];
                
                var recID = _getFieldValue(record, 'rec_ID');
                var recTypeID   = Number(_getFieldValue(record, 'rec_RecTypeID'));
                if(recTypeID == RT_ADDRESS){
                    
                    var shtml = '';
                    
                    //2. find relation records
                    var rels = _getRelationRecords(recID, null);
                    
                    if(rels.length<1){
                        //this is just address
                        _setFieldValue(record, 'rec_Icon', 'term4326' );
                        shtml = '<div style="text-align:left"><p><b>'+_getFieldValue(record, 'rec_Title')+'</b></p>'
                                        + __addpart('<b>Cooment:</b><br/>', _getFieldValue(record, DT_NOTES) )
                                        + '<br/><br/>'                                  
                                        + '<a href="javascript:void(0)" class="moredetail" onclick="">More Detail</a>'  //parent.popupcontrol('show','individualpopup.php?IV_ID=862');
                                        + '</div>'
                        _setFieldValue(record, 'rec_Info', shtml);
                        _setFieldValue(record, 'rec_RecTypeID', DH_RECORDTYPE);
                        continue;
                    }
                    
                    
                    for(i=0; i<rels.length; i++){
                        //3. if event try to find related persons
                        if(rels[i]['relrt'] == RT_EVENT){
                            var rels2 = _getRelationRecords(recID, RT_PERSON);
                            if(rels2.length<1){
                                //3a. this is event->address 

                                var rel_event = records[rels[i]['related']];
                                var relrec = records[rels[i]['relation']];
                                var uniqid = rels[i]['relation']+' '+rels[i]['related']+' ';
                            
                                _setFieldValue(relrec, 'rec_RecTypeID', DH_RECORDTYPE);
                                _setFieldValue(relrec, DT_STARTDATE, _getFieldValue(rel_event, 'dtl_StartDate'));
                                _setFieldValue(relrec, DT_ENDDATE, _getFieldValue(rel_event, DT_ENDDATE));          
                                _setFieldValue(relrec, 'rec_Icon', 'term'+_getFieldValue(rel_event, DT_EVENT_TYPE) );
                                _setFieldValue(relrec, DT_GEO, _getFieldValue(record, DT_GEO));          
                                
                                var event_desc = _getFieldValue(rel_event, DT_DESCRIPTION);
                                event_desc = (top.HEURIST4.util.isempty(desc))?'':desc+'<br/><br/>';
                                
                                var evt_datetime = __addpart('', _getFieldValue(rel_event, 'dtl_StartDate'))+
                                                   __addterm(' <b> Time: </b> ', _getFieldValue(rel_event, DT_EVENT_TIME) )+
                                                   __addpart(' to ', _getFieldValue(rel_event, DT_ENDDATE))+
                                                   __addterm(' <b> Time: </b> ', _getFieldValue(rel_event, DT_EVENT_TIME_END) );
                                
                                evt_datetime = (top.HEURIST4.util.isempty(evt_datetime))?'':'<b>Date: </b>' + evt_datetime;
                                
                                var evt_title = _getFieldValue(rel_event, DT_TITLE);

                                shtml = '<div style="text-align:left"><p><b>'+evt_title+'</b></p>'
                                        + '<b>'+ __addterm('', _getFieldValue(rel_event, DT_EVENT_TYPE) )+'</b><br/><br/>'                                  
                                        + 'Location of the event here at ' + _getFieldValue(record, 'rec_Title')+'<br/><br/>'
                                        + event_desc
                                        + evt_datetime +'<br/><br/>' + uniqid
                                        + '<a href="javascript:void(0)" class="moredetail" onclick="">More Detail</a>'  //parent.popupcontrol('show','individualpopup.php?IV_ID=862');
                                        + '</div>'
                                _setFieldValue(relrec, 'rec_Info', shtml);
                                _setFieldValue(relrec, 'rec_Title', uniqid+evt_title );
                                
                            }else{
                                //3b. this is address->event->person
                             
                                var rel_event = records[rels[i]['related']];    //event
                                var relrec = records[rels[i]['relation']];   //event->address
                                
                                var evt_address_reltype =_getFieldValue(relrec, DT_RELATION_TYPE);
                                if(evt_address_reltype==4536){
                                    evt_address_reltype = ' in which the crime scene was here at this address '
                                }else{
                                    evt_address_reltype = __addterm(' which ', evt_address_reltype, 'relation' );    
                                }
                                
                                var evt_address_desc = __addpart('', _getFieldValue(relrec, DT_DESCRIPTION));

                                for(j=0; j<rels2.length; j++){
                                
                                relrec = records[rels2[j]['relation']];   //event->person
                                rel_person = records[rels2[j]['related']];   //person
                                var uniqid = rels2[j]['relation']+' '+rels2[j]['related']+' ';
                            
                                _setFieldValue(relrec, 'rec_RecTypeID', DH_RECORDTYPE);
                                _setFieldValue(relrec, DT_STARTDATE, _getFieldValue(rel_event, 'dtl_StartDate'));
                                _setFieldValue(relrec, DT_ENDDATE, _getFieldValue(rel_event, DT_ENDDATE));          
                                //_setFieldValue(relrec, DT_DESCRIPTION, _getFieldValue(relrec, DT_DESCRIPTION));       
                                _setFieldValue(relrec, 'rec_Icon', 'term'+_getFieldValue(rel_event, DT_EVENT_TYPE) );
                                _setFieldValue(relrec, DT_GEO, _getFieldValue(record, DT_GEO));          
                                
                                var desc = _getFieldValue(relrec, DT_DESCRIPTION);
                                desc = (top.HEURIST4.util.isempty(desc))?'':desc+'<br/><br/>';
                                
                                var evt_datetime = __addpart('', _getFieldValue(rel_event, 'dtl_StartDate'))+
                                                   __addterm(' <b> Time: </b> ', _getFieldValue(rel_event, DT_EVENT_TIME) )+
                                                   __addpart(' to ', _getFieldValue(rel_event, DT_ENDDATE))+
                                                   __addterm(' <b> Time: </b> ', _getFieldValue(rel_event, DT_EVENT_TIME_END) );
                                
                                
                                
                                
                                shtml = '<div style="text-align:left"><p><b>'+_getFieldValue(rel_person, 'rec_Title')+'</b></p>'
                                        + '<b>'+ __addterm('', _getFieldValue(relrec, DT_RELATION_TYPE), 'relation' )+'</b>'
                                        + ' in the event: <b>'+_getFieldValue(rel_event, DT_TITLE)+'</b>'
                                        + evt_address_reltype
                                        + _getFieldValue(record, 'rec_Title') + '<br/>' 
                                        + evt_address_desc  + '<br/><br/>' 
                                        + evt_datetime +'<br/><br/>'  + uniqid                                 
                                        + '<a href="javascript:void(0)" class="moredetail" onclick="">More Detail</a>'  //parent.popupcontrol('show','individualpopup.php?IV_ID=862');
                                        + '</div>'
                                _setFieldValue(relrec, 'rec_Info', shtml);
                                _setFieldValue(relrec, 'rec_Title', 'pea'+uniqid + _getFieldValue(relrec, 'rec_Title'));
                                
                                }//for  rel_person
                            }
                            
                        }else if(rels[i]['relrt'] == RT_PERSON){
                                //3c. this is person->address
                                
                                var relrec = records[rels[i]['relation']];
                                var rel_person = records[rels[i]['related']];
                                var uniqid = rels[i]['relation']+' '+rels[i]['related']+' ';
                            
                                _setFieldValue(relrec, 'rec_RecTypeID', DH_RECORDTYPE);
                                _setFieldValue(relrec, DT_STARTDATE, _getFieldValue(relrec, 'dtl_StartDate'));
                                _setFieldValue(relrec, DT_ENDDATE, _getFieldValue(relrec, DT_ENDDATE));          
                                //_setFieldValue(relrec, DT_DESCRIPTION, _getFieldValue(relrec, DT_DESCRIPTION));       
                                _setFieldValue(relrec, 'rec_Icon', 'term'+_getFieldValue(relrec, DT_RELATION_TYPE) );
                                _setFieldValue(relrec, DT_ENDDATE, _getFieldValue(relrec, DT_ENDDATE));          
                                _setFieldValue(relrec, DT_GEO, _getFieldValue(record, DT_GEO));          
                                
                                var desc = _getFieldValue(relrec, DT_DESCRIPTION);
                                desc = (top.HEURIST4.util.isempty(desc))?'':desc+'<br/><br/>';
                                
                                var evt_datetime = __addpart('', _getFieldValue(relrec, 'dtl_StartDate'))+
                                                   __addpart(' to ', _getFieldValue(relrec, DT_ENDDATE));
                                
                                evt_datetime = (top.HEURIST4.util.isempty(evt_datetime))?'':('<b>Date: </b>' + evt_datetime +'<br/><br/>');
                                
                                
                                shtml = '<div style="text-align:left"><p><b>'+_getFieldValue(rel_person, 'rec_Title')+'</b></p>'
                                        + '<b>'+ __addterm('', _getFieldValue(relrec, DT_RELATION_TYPE), 'relation' ) +'</b><br/><br/>'                                  
                                        + 'At this address ' + _getFieldValue(record, 'rec_Title')+'<br/><br/>'
                                        + desc
                                        + evt_datetime            + uniqid
                                        + '<a href="javascript:void(0)" class="moredetail" onclick="">More Detail</a>'  //parent.popupcontrol('show','individualpopup.php?IV_ID=862');
                                        + '</div>'
                                _setFieldValue(relrec, 'rec_Info', shtml);
                                _setFieldValue(relrec, 'rec_Title', 'pa'+uniqid + _getFieldValue(relrec, 'rec_Title'));
                        }
                    }
                    
                    
                }
            }
        }
    }

    function _getRelationRecords(forRecID, forRecTypeID){
        var idx, relations = [];
        
        for(idx in records){
            if(idx)
            {
                var record = records[idx];
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
    * @todo - obtain codes from server side
    */
    function _getFieldValue(record, fldname){

        if(!isNaN(Number(fldname)) || fldname.indexOf("dtl_")==0){  //@todo - search detail by its code
            var d = record['d'];
            if(d){
                if(!isNaN(Number(fldname))){ //dt code
                    if(d[fldname] && d[fldname][0]){
                        return d[fldname][0];
                    }
                }else if(fldname=="dtl_StartDate"){
                    if(d[10] && d[10][0]){
                        return d[10][0];
                    }else if(d[9]){
                        return d[9][0];
                    }
                }else if(fldname=="dtl_EndDate"){
                    return _getFieldValue(record, 11);
                    //if(d[11] && d[11][0]){ return d[11][0]; }
                }else if(fldname=="dtl_Description"){
                    return _getFieldValue(record, 3);
                    //if(d[3] && d[3][0]){return d[3][0];}
                    
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
            return record[fldname]?record[fldname]:null; //return null;
        }
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
            
            if(limit>0){
                return order.slice(0, 1999);
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
        getSubSet: function(_records){
            if(_records==null){
                _records = {};
            }
            var ord = that.getIds2(_records);
            
            return new hRecordSet({
                queryid: queryid,
                count: ord.length, //$(_records).length,
                offset: 0,
                fields: fields,
                rectypes: rectypes,
                structures: structures,
                records: _records,
                order: ord
            });
        },

        getSubSetByIds: function(rec_ids){
            var _records = {};
            //find all records
            
            if(!($.isArray(rec_ids) && rec_ids.length>0) ) return null;
            
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
            var order2 = recordset2.getOrder();
            
            var order_new = order, records_new = records, idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //for (recid in records2){
                if(recid){ //&& !records[recid]){
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
            
            return new hRecordSet({
                queryid: queryid,
                count: total_count, //keep from original
                offset: 0,
                fields: fields,
                rectypes: rectypes2,
                structures: structures,
                records: records_new,
                order: order_new
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
            /*var recid;
            for (recid in records){
                if(recid){
                    return records[recid];
                }
            }*/
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
        
        
        preprocessForDigitalHarlem: function(){
          _preprocessForDigitalHarlem();  
        },

        /**
        * Converts recordSet to OS Timemap dataset
        */
        toTimemap: function(dataset_name, filter_rt){
            return _toTimemap(dataset_name, filter_rt);
        }

    }

    _init(initdata);
    return that;  //returns object
}
