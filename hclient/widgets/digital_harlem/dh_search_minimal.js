/**
*
* dh_search_minimal.js (Digital Harlem) : Special search class (based on search_minimal.js Minimal search)
*
* Replaces standard search in top.HAPI4.SearchMgr
*
* 1) it adds special parameters to search request:
*       getrelrecs - get relationship records
*       details - return only required fields
* 2) And performs processing on the result set
*
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @note        Completely revised for Heurist version 4
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

function hSearchMinimalDigitalHarlem() {
    var _className = "SearchMinimalDigitalHarlem",
    _version   = "0.4";


    _owner_doc = null,
    _owner_element_id = null;

    /**
    * Initialization
    */
    function _init( ) {
    }

    // search with callback - used in map_overlay for addQueryLayer
    function _doSearchWithCallback( request, callback ){

        request.getrelrecs = 1;
        request.detail = 'detail'; //'5,6,7,9,10,11,28,74';  //@todo - take codes from magic numbers

        top.HEURIST4._time_debug = new Date().getTime() / 1000;

        console.log('Start search');

        top.HAPI4.RecordMgr.search(request,
            function(response) {
                if(response.status != top.HAPI4.ResponseStatus.OK){
                    callback( null );
                    top.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                console.log('get result '+ ( new Date().getTime() / 1000 - top.HEURIST4._time_debug ) );
                top.HEURIST4._time_debug = new Date().getTime() / 1000;

                var original_recordset = new hRecordSet(response.data);
                var final_recordset = _prepareResultSet( original_recordset );

                console.log('prepared '+ ( new Date().getTime() / 1000 - top.HEURIST4._time_debug) );
                top.HEURIST4._time_debug = new Date().getTime() / 1000;

                callback( final_recordset, original_recordset );

                // 7. trigger creation of the separate tab t display result set
                //@todo - change to special DH events

                /*
                if(!top.HEURIST4.util.isnull(originator.document)){
                $(originator.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ final_recordset ]);  //gloal app event
                }*/

        });

    }



    // search with event triggers
    //
    // actually for Digital Harlem we do not use triggers
    // search is performed from two origins
    // from dh_search and when we add result to map / or from saved mapdocument (see _doSearchWithCallback)
    //
    // 1. search main request  if result > 1000 warning message and exit
    // 2. search rules (applyRules) with relationship records
    // 3. select addresses and search for geo fields
    // 4. select events and search for date fields
    // 5. assign to address records  date, title, eventtype (from event) or date, title, role from place role
    //              and record id from main request (person or event)
    // 6. final resultset: exclude all recordtypes but addresses
    // 7. trigger creation of the separate tab t display result set
    //

    //this version of search  is IN USE
    function _doSearch( originator, request ){

        var app = top.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
        if(app && app.widget){

            if(originator && !top.HEURIST4.util.isnull(originator.document)){
                $(originator.document).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event
            }

            // switch to map tab
            top.HAPI4.LayoutMgr.putAppOnTop('app_timemap');


            //add new layer with given name
            var params = {id:'main',
                title: 'Current query',
                query: request,
                color: 'rgb(255,0,0)', //'#ff0000',
                callback: function( final_recordset, original_recordset ){
                    //that.res_div_progress.hide();
                    if(originator && !top.HEURIST4.util.isnull(originator.document) ) { // && recordset.length()>0){
                        //global app event - in dh faceted search listens it
                        $(originator.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ original_recordset ]);

                        //call dh_search to show add button
                        var app1 = top.HAPI4.LayoutMgr.appGetWidgetByName('dh_search');
                        if(app1 && app1.widget){
                            $(app1.widget).dh_search('updateResultSet', final_recordset);
                        }

                        /*call result list to fill with results
                        var app2 = top.HAPI4.LayoutMgr.appGetWidgetByName('resultList');
                        if(app2 && app2.widget){
                        $(app2.widget).resultList('updateResultSet', final_recordset);
                        }*/

                    }
                }
            };

            $(app.widget).app_timemap('addQueryLayer', params);  //it will call  _doSearchWithCallback
        }


    }



    // this version searches main query only (without rules and relationships) and then updateResultSet in dh_search.js
    // Ian does not want it (b/c it does not properly render the result set)
    function _doSearch_2steps( originator, request ){

        if(request==null) return;

        if( top.HEURIST4.util.isnull(request.id) ) { //unique id for request
            request.id = Math.round(new Date().getTime() + (Math.random() * 100));
        }

        //keep rules for further search
        var keepRequest = top.HEURIST4.util.cloneJSON(request);

        request.limit = 2000;
        request.needall = 1;

        request.detail = 'ids';
        request.rules = null;

        if(originator && !top.HEURIST4.util.isnull(originator.document)){
            $(originator.document).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event
        }

        // 1. search main request  if result > 1000 warning message and exit
        top.HAPI4.RecordMgr.search(request,
            function(response) {

                if(response.status != top.HAPI4.ResponseStatus.OK){
                    top.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                var recordset = new hRecordSet(response.data);

                var app = top.HAPI4.LayoutMgr.appGetWidgetByName('dh_search');  //top.HAPI4.LayoutMgr.appGetWidgetById('ha51');
                if(app && app.widget){
                    $(app.widget).dh_search('updateResultSet', recordset, keepRequest);
                }

                if(originator && !top.HEURIST4.util.isnull(originator.document) && recordset.length()>0){
                    $(originator.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ recordset ]); //global app event
                }


            }
        );

    }



    //@todo - obtain codes from magic numbers server side
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
    DT_EVENT_TIME_END = 76,
    DT_EVENT_ORDER = 94; //Order of addresses within a serial event, eg. a march or multi-stage crime

    var TERM_MAIN_CRIME_LOCATION = 4536,
    TERM_EVENT_PRIME_LOCATION = 4534,
    TERM_ROLE_RESIDENT = 4527,
    TERM_PATH = 4537;


    var DH_RECORDTYPE = 13,
    DH_LINKS = 9999999;  //special record type



    // 5. assign to address records  date, title, eventtype (from event) or date, title, role from place role
    //              and record id from main request (person or event)
    // 6. final resultset: exclude all recordtypes but addresses
    //
    // links mapping
    // 7. for events - find main crime location and connect it with other locations
    //                 if DT_EVENT_ORDER field >0 create polyline
    //
    // 8. for person - find address that is residence and add link to other addresses within the same timeframe
    //
    function _prepareResultSet( recordset ){

        var res_records = {}, res_orders = [];

        //1. find address records
        var idx, i, j, relrec, related;

        var records = recordset.getRecords();
        var relationships = recordset.getRelationship();

        var links = {}; //   PersonID:{ primary: pnt   secondary: [pnt,pnt,....]}
        var linkID = 1000000000;

        for(idx in records){
            if(idx)
            {
                var record = records[idx];

                var recID = recordset.fld(record, 'rec_ID');
                var recTypeID   = Number(recordset.fld(record, 'rec_RecTypeID'));
                if(recTypeID == RT_ADDRESS){

                    var shtml = '';

                    //2. find relation records
                    var rels = recordset.getRelationRecords(recID, null);

                    if(rels.length<1){
                        //this is just address
                        recordset.setFld(record, 'rec_Icon', 'term4326' );
                        recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //?????
                        recordset.setFld(record, 'rec_Info',
                            top.HAPI4.basePathV4 + "hclient/widgets/digital_harlem/dh_popup.php?db="+top.HAPI4.database+"&recID="+recID);

                    }else{

                        for(i=0; i<rels.length; i++){
                            //3. if event try to find related persons
                            if(rels[i]['relrt'] == RT_EVENT){

                                var eventID = rels[i]['related'];
                                //var relrec = relationships[rels[i]['relation']];
                                var rel_event = records[ eventID ];
                                //var rec_ID_event = recordset.fld(rel_event, 'rec_ID')

                                recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //?????
                                recordset.setFld(record, 'rec_Icon',   'term'+recordset.fld(rel_event, DT_EVENT_TYPE) );
                                recordset.setFld(record, 'rec_Title',  recordset.fld(rel_event, 'rec_Title') );
                                recordset.setFld(record, DT_STARTDATE, recordset.fld(rel_event, 'dtl_StartDate' ) );
                                recordset.setFld(record, DT_ENDDATE,   recordset.fld(rel_event, DT_ENDDATE) );

                                //find persons that relates to this event

                                var rels2 = recordset.getRelationRecords(eventID, RT_PERSON);
                                if(rels2.length<1){
                                    //3a. this is event->address
                                    recordset.setFld(record, 'rec_Info',
                                        top.HAPI4.basePathV4 + "hclient/widgets/digital_harlem/dh_popup.php?db="+top.HAPI4.database
                                        +"&recID="+eventID+"&addrID="+recID);

                                    //add links for this person
                                    if(!links[ eventID ]){
                                        links[ eventID ] = {primary:[], secondary:[], is_event:true, path:[] };
                                    }
                                    //verify: is it main crime location
                                    var relrec = relationships[rels[i]['relation']];
                                    var relation_type = recordset.fld(relrec, DT_RELATION_TYPE);
                                    var order = recordset.fld(relrec, DT_EVENT_ORDER);

                                    if ((relation_type==TERM_MAIN_CRIME_LOCATION  ||
                                        relation_type==TERM_EVENT_PRIME_LOCATION)
                                        && (links[ eventID ].primary.length<1) ){  //4527 4536
                                        links[ eventID ].primary.push( recID );
                                    }else{
                                        links[ eventID ].secondary.push( recID );
                                    }

                                    if(order>0){
                                        links[ eventID ].path.push({order:order, recID:recID});
                                    }


                                }else{
                                    //3b. this is address->event->person
                                    for(j=0; j<rels2.length; j++){
                                        var personID = rels2[j]['related'];
                                        var rel_person = records[ personID ];

                                        recordset.setFld(record, 'rec_Info',
                                            top.HAPI4.basePathV4 + "hclient/widgets/digital_harlem/dh_popup.php?db="+top.HAPI4.database+"&recID="+
                                            personID+"&addrID="+recID+"&eventID="+eventID );

                                        //add links for this person
                                        if(!links[ personID ]){
                                            links[ personID ] = {primary:[], secondary:[]};
                                        }
                                        //events are always secondary for person links (primary is residency)
                                        links[ personID ].secondary.push( recID );
                                    }//for persons
                                }

                            }else if(rels[i]['relrt'] == RT_PERSON){
                                //3c. this is person->address
                                var relrec = relationships[rels[i]['relation']];
                                var personID = rels[i]['related'];
                                var rel_person = records[ personID ];

                                var relation_type = recordset.fld(relrec, DT_RELATION_TYPE);

                                recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //?????
                                recordset.setFld(record, 'rec_Icon',     'term'+relation_type );

                                var recInfoUrl = top.HAPI4.basePathV4 + "hclient/widgets/digital_harlem/dh_popup.php?db="+top.HAPI4.database+"&recID="+
                                recordset.fld(rel_person, 'rec_ID')+"&addrID="+recID;
                                recordset.setFld(record, 'rec_Info', recInfoUrl);
                                recordset.setFld(record, 'rec_InfoFull', recInfoUrl+'&full=1');

                                recordset.setFld(record, 'rec_Title',  recordset.fld(relrec, 'rec_Title') );
                                recordset.setFld(record, DT_STARTDATE, recordset.fld(relrec, 'dtl_StartDate' ) );
                                recordset.setFld(record, DT_ENDDATE,   recordset.fld(relrec, DT_ENDDATE) );

                                //add links for this person
                                if(!links[ personID ]){
                                    links[ personID ] = {primary:[], secondary:[]};
                                }
                                //verify: is it residence
                                if(relation_type==TERM_ROLE_RESIDENT){  //4527 4536
                                    links[ personID ].primary.push( recID );
                                }else{
                                    links[ personID ].secondary.push( recID );
                                }

                            }
                        }

                    }

                    res_records[recID] = record;
                    res_orders.push(recID);
                }
            }
        }



        // add links
        function __isIntersects(r1start, r1end, r2start, r2end)
        {
            if(r2end){
                return (r1start == r2start) || (r1start > r2start ? r1start <= r2end : r2start <= r1end);
            }else{
                if(!r1end || r1end == r1start){
                    r1end = r1start + ' 24:00';
                    r1start = r1start + ' 00:00';
                }
                return (r2start>=r1start && r2start<=r1end);
            }
        }

        var recID, i, j, mainAddr, is_event;
        for(recID in links){
            if (links.hasOwnProperty(recID)) //person ID
            {
                is_event = links[recID].is_event;

                if(is_event && links[ recID ].path.length>1){
                    var path = links[ recID ].path;
                    var vertices = [];
                    path.sort( function(a, b){return (a.order<b.order)?-1:1; } );

                    for(i=0; i<path.length; i++){
                        var pathAddr = res_records[ path[i].recID ];
                        var type     = recordset.fld(pathAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                        var wkt      = recordset.fld(pathAddr, 'dtl_Geo');
                        var pnt      = top.HEURIST4.util.parseCoordinates(type, wkt, 0);
                        if(pnt!=null){
                            vertices.push(pnt.point);
                        }
                    }

                    if(vertices.length>1){
                        // add path shape to first pathAddr (start parade for example)
                        var pathAddr = res_records[ path[0].recID ];
                        recordset.setFld(pathAddr, 'rec_Shape', [{polyline:vertices}] );
                        continue;
                    }
                }

                for(i=0; i<links[recID].primary.length; i++){

                    mainAddr = res_records[ links[recID].primary[i] ]; //record id
                    var d_start1 = recordset.fld(mainAddr, 'dtl_StartDate' );
                    var d_end1   = recordset.fld(mainAddr, DT_ENDDATE);
                    var type     = recordset.fld(mainAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                    var wkt      = recordset.fld(mainAddr, 'dtl_Geo');
                    var pnt1     = top.HEURIST4.util.parseCoordinates(type, wkt, 0);

                    if(pnt1==null) continue;

                    var shapes = [];

                    for(j=0; j<links[recID].secondary.length; j++){

                        secAddr = res_records[ links[recID].secondary[j] ]; //record id
                        var d_start2 = recordset.fld(secAddr, 'dtl_StartDate' );
                        var d_end2   = recordset.fld(secAddr, DT_ENDDATE);

                        if(is_event || __isIntersects(d_start1, d_end1, d_start2, d_end2)){
                            type     = recordset.fld(secAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                            wkt      = recordset.fld(secAddr, 'dtl_Geo');
                            var pnt2     = top.HEURIST4.util.parseCoordinates(type, wkt, 0);
                            if(pnt2!=null){
                                shapes.push( {polyline:[ pnt1.point, pnt2.point ]} );

                                /* old way  add new record to result set
                                var newrec = {"2":linkID, "4":DH_LINKS, "rec_Info":false, "rec_Shape":{polyline:[ pnt1.point, pnt2.point ]} };
                                res_records[linkID] = newrec;
                                res_orders.push(linkID);
                                linkID++;
                                */
                            }
                        }
                    }

                    if(shapes.length>0)
                        recordset.setFld(mainAddr, 'rec_Shape', shapes );
                }
            }
        }



        return new hRecordSet({
            count: res_orders.length,
            offset:0,
            fields: recordset.getFields(),
            rectypes: [DH_RECORDTYPE],
            records: res_records,
            order: res_orders,
            mapenabled: true
        });
    }



    /**
    *
    *
    */
    function _searchCompleted( is_terminate ){

    }



    //
    // apply rules to existed result set
    //
    function _doApplyRules( originator, rules ){
        /*
        if(top.HAPI4.currentRecordset && top.HEURIST4.util.isArrayNotEmpty(top.HAPI4.currentRecordset.getMainSet())){

        var request = { q: 'ids:'+top.HAPI4.currentRecordset.getMainSet().join(','),
        rules: rules,
        w: _query_request?_query_request.w:'a'};

        _doSearch( originator, request );
        return true;
        }else{
        return false;
        }*/
        return false;
    }



    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        doSearchWithCallback: function(request, callback){
            _doSearchWithCallback(request, callback);
        },

        // originator - widget that initiated the search
        doSearch:function( originator, request ){
            _doSearch( originator, request );
        },

        // apply rules to existing result set
        doApplyRules:function( originator, request ){
            return _doApplyRules( originator, request );
        },

        doStop: function(){
            _searchCompleted( true );
        }

    }

    _init();
    return that;  //returns object
}