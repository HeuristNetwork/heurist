/**
*
* dh_search_minimal.js (Digital Harlem) : Special search class (based on hRecordSearch Minimal search)
*
* Replaces standard search in window.hWin.HAPI4.RecordSearch
*
* 1) it adds special parameters to search request:
*       getrelrecs - get relationship records
*       details - return only required fields
* 2) And performs processing on the result set
*
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

    //
    // search with callback - used in map_overlay for addQueryLayer
    // this saerch does not trigger global events
    //
    function _doSearchWithCallback( request, callback ){

        request.getrelrecs = 1;
        request.detail = 'detail'; //'5,6,7,9,10,11,28,74';  //@todo - take codes from magic numbers

        window.hWin.HEURIST4._time_debug = new Date().getTime() / 1000;

//console.log('Start search');

        window.hWin.HAPI4.RecordMgr.search(request,
            function(response) {
                if(response.status != window.hWin.ResponseStatus.OK){
                    callback( null );
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

//console.log('get result '+ ( new Date().getTime() / 1000 - window.hWin.HEURIST4._time_debug ) );
                window.hWin.HEURIST4._time_debug = new Date().getTime() / 1000;

                var primary_rt = request.primary_rt;
                var original_recordset = new hRecordSet(response.data);
                var final_recordset = _prepareResultSet( original_recordset, primary_rt );

//console.log('prepared '+ ( new Date().getTime() / 1000 - window.hWin.HEURIST4._time_debug) );
                window.hWin.HEURIST4._time_debug = new Date().getTime() / 1000;

                callback( final_recordset, original_recordset );

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

        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
        if(app && app.widget){

            if(originator && !window.hWin.HEURIST4.util.isnull(originator.document)){
                $(originator.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event
            }

            // switch to map tab
            window.hWin.HAPI4.LayoutMgr.putAppOnTop('app_timemap');

            //add new layer with given name
            var params = {id:'main',
                title: 'Current query',
                query: request,
                color: '#ff0000',
                callback: function( final_recordset, original_recordset ){
                    //that.res_div_progress.hide();
                    if(originator && !window.hWin.HEURIST4.util.isnull(originator.document) ) { // && recordset.length()>0){
                        //global app event - in dh faceted search listens it
                        $(originator.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {recordset:original_recordset});

                        //call dh_search to show add button
                        var app1 = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('dh_search');
                        if(app1 && app1.widget){
                            $(app1.widget).dh_search('updateResultSet', final_recordset);
                        }

                    }
                }
            };

            $(app.widget).app_timemap('addQueryLayer', params);  //it will call  _doSearchWithCallback
        }


    }


    // NOT USED
    // this version searches main query only (without rules and relationships) and then updateResultSet in dh_search.js
    // Ian does not want it (b/c it does not properly render the result set)
    function _doSearch_2steps( originator, request ){

        if(request==null) return;

        if( window.hWin.HEURIST4.util.isnull(request.id) ) { //unique id for request
            request.id = window.hWin.HEURIST4.util.random();
        }

        //keep rules for further search
        var keepRequest = window.hWin.HEURIST4.util.cloneJSON(request);

        request.limit = 2000;
        request.needall = 1;

        request.detail = 'ids';
        request.rules = null;

        if(originator && !window.hWin.HEURIST4.util.isnull(originator.document)){
            $(originator.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event
        }

        // 1. search main request  if result > 1000 warning message and exit
        window.hWin.HAPI4.RecordMgr.search(request,
            function(response) {

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                var recordset = new hRecordSet(response.data);

                var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('dh_search');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_Map');
                if(app && app.widget){
                    $(app.widget).dh_search('updateResultSet', recordset, keepRequest);
                }

                if(originator && !window.hWin.HEURIST4.util.isnull(originator.document) && recordset.length()>0){
                    $(originator.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {recordset:recordset}); //global app event
                }


            }
        );

    }

    //@todo - obtain codes from magic numbers server side
    var RT_ADDRESS = 12,
    RT_EVENT = 14,
    RT_PERSON = 10,
    RT_PLACE_FUNCTION = 16,
    RT_GEOPLACE = 27,
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
    DT_GEO = 28,  //window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']; //28
    DT_EVENT_TYPE = 74,
    DT_EVENT_TIME = 75,
    DT_EVENT_TIME_END = 76,
    DT_EVENT_ORDER = 94; //Order of addresses within a serial event, eg. a march or multi-stage crime
    DT_USAGE_TYPE = 89; //usage type terms for place functions
    DT_ADDRESS = 90; //address of place function

    //constants for relation types
    var TERM_MAIN_CRIME_LOCATION = 4536, //primary event location
    TERM_EVENT_PRIME_LOCATION = 4533, //happened entirely at
    TERM_ROLE_RESIDENT = 4527,
    TERM_PATH = 4537;

    //constants for special Digital Harlem record types - to display them in different layers
    var DH_RECORDTYPE = 99913, //special record type to display Digital Harlem entities on map
        DH_RECORDTYPE_SECONDARY = 99914, //special record type to display secondary events (FOR EVENTS search ONLY)
        DH_RECORDTYPE_RESIDENCES = 99915; //special record type to display residential addresses (FOR EVENTS search ONLY)



    // 5. assign to address records  date, title, eventtype (from event) or date, title, role from place role
    //              and record id from main request (person or event)
    // 6. final resultset: exclude all recordtypes but addresses
    //
    // links mapping
    // 7. for events - find main crime location and connect it with other locations
    //                 if DT_EVENT_ORDER field >0 create polyline/path
    //
    // 8. for person - find address that is residence and add link to other addresses within the same timeframe
    //                 if residence timeframe not defined we assume  it covers all the time
    //
    function _prepareResultSet( recordset, primary_rt ){

        var res_records = {}, res_orders = [];

        //1. find address records
        var idx, i, j, relrec, related;

        var records = recordset.getRecords();
        var relationships = recordset.getRelationship();

        var links = {}; //   PersonID:{ primary: pnt   secondary: [pnt,pnt,....]}
        var linkID = 1000000000;

        if(!primary_rt){
            //take primary type from first record
            var rec = recordset.getFirstRecord();
            primary_rt = Number(recordset.fld(rec, 'rec_RecTypeID'));
            if(primary_rt==RT_GEOPLACE){
                  primary_rt = RT_ADDRESS;
            }
        } 
//console.log('primary '+primary_rt);

        var is_Riot = '';
        if(window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem1935'){
            is_Riot = '&riot=1';
        }
        
        
        for(idx in records){
            if(idx)
            {
                var record = records[idx];
                var recID = recordset.fld(record, 'rec_ID');
                var recTypeID = Number(recordset.fld(record, 'rec_RecTypeID'));
                
                var record_result = null;
                
                if(recTypeID == RT_GEOPLACE){
                   
                    recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); 
                    res_records[recID] = record;
                    res_orders.push(recID);
                    
                }else if(primary_rt==RT_PLACE_FUNCTION){
                     
                     if(recTypeID == RT_PLACE_FUNCTION){
                            
                        //find address and assign geo
                        var addrID = recordset.fld(record, DT_ADDRESS);
                        var record_address = recordset.getById(addrID);
                           
                        if(record_address){   
                            //this is just address
                            recordset.setFld(record, 'rec_Icon', 'term4326' ); //icon - house
                            recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //special record type to distiguish 
                            recordset.setFld(record, 'rec_Info',
                                window.hWin.HAPI4.baseURL + "hclient/widgets/digital_harlem/dh_popup.php?db="
                                    +window.hWin.HAPI4.database+"&recID="+recID+"&addrID="+addrID+is_Riot);

                            var addr_geo = recordset.fld(record_address, DT_GEO);
                            if( window.hWin.HEURIST4.util.isempty(addr_geo)) {
                                //exclude addresses without geo
                                console.log('address '+addrID+' has no geo');
                                continue;
                            }else{
                                recordset.setFld(record, DT_GEO, addr_geo);
                            }
                            //console.log('address '+recID+' '+addr_geo);
                        
                            record_result = record;        
                            res_records[recID] = record_result;
                            res_orders.push(recID);
                        }
                           
                     }     
                            
                }else if(recTypeID == RT_ADDRESS){

                    var shtml = '';
                    //2. find relation records
                    var rels = recordset.getRelationRecords(recID, null);

                    if(rels.length<1){ // no relations
                    
                        //this is just address
                        recordset.setFld(record, 'rec_Icon', 'term4326' );
                        recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //special record type to distiguish 
                        recordset.setFld(record, 'rec_Info',
                            window.hWin.HAPI4.baseURL + "hclient/widgets/digital_harlem/dh_popup.php?db="
                                +window.hWin.HAPI4.database+"&recID="+recID+is_Riot);

                        var addr_geo = recordset.fld(record, DT_GEO);
                        if( window.hWin.HEURIST4.util.isempty(addr_geo)) {
                            //exclude addresses without geo
                            console.log('address '+recID+' has no geo');
                            continue;
                        }
                        //console.log('address '+recID+' '+addr_geo);
                    
                        record_result = record;        
                        res_records[recID] = record_result;
                        res_orders.push(recID);
                        
                    }else{
                        
                        if(primary_rt==RT_EVENT){

                            var events_per_address = [];    
                            
                            for(i=0; i<rels.length; i++){ //all records related to addresses
                                //3. if event try to find related persons
                                if(rels[i]['relrt'] == RT_EVENT){

                                    var eventID = rels[i]['related'];
                                    var rel_event = records[ eventID ];
                                    
                                    var recID_res = rels[i]['relation'];
                                    record_result = relationships[recID_res];
                                   
                                    /* before we addded event info to address record. however, we may have many events per address
                                    thus, now we use relationship record and 
                                    add coordinates from address and other data from event record 
                                    and put it this relationship record with new type (DH_RECORDTYPE)in final prepared result set
                                    */
                                    
                                    //assign to event data into record_result and change its rectype
                                    recordset.setFld(record_result, 'rec_RecTypeID', DH_RECORDTYPE); //change record type 
                                    recordset.setFld(record_result, 'rec_Icon',  'term'+recordset.fld(rel_event, DT_EVENT_TYPE) );
                                    recordset.transFld(record_result, rel_event, 'rec_Title' );
                                    recordset.transFld(record_result, rel_event, DT_STARTDATE );
                                    recordset.transFld(record_result, rel_event, DT_ENDDATE );
                                    
                                    //
                                    recordset.setFld(record_result, 'rec_Info',
                                            window.hWin.HAPI4.baseURL + "hclient/widgets/digital_harlem/dh_popup.php?db="
                                            +window.hWin.HAPI4.database
                                            +"&recID="+eventID+"&addrID="+recID+is_Riot);

                                    //assign geo from address
                                    if(!recordset.transFld(record_result, record, DT_GEO, true )){
                                        console.log('address '+recID+' has no geo');
                                        continue;
                                    }
                                    
                                    //events_per_address.join(',')
                                    //events_per_address.push(eventID);
                                    
                                    //add empty links for this event
                                    if(!links[ eventID ]){
                                        links[ eventID ] = {primary:[], secondary:[], residence:[], is_event:true, path:[] };
                                    }
                                            
                                    //verify: is it main crime location?
                                    var relation_type = recordset.fld(record_result, DT_RELATION_TYPE);
                                    var order = Number(recordset.fld(record_result, DT_EVENT_ORDER));

                                    //Happened Entirely at 4533
                                    //Happened Primarily at 4536
                                    if ((relation_type==TERM_MAIN_CRIME_LOCATION  ||
                                        relation_type==TERM_EVENT_PRIME_LOCATION)
                                        && (links[ eventID ].primary.length<1) ){  //4533 4536

                                        links[ eventID ].primary.push( recID_res );
                                        recordset.setFld(record_result, 'rec_RecTypeID', DH_RECORDTYPE); 
                                    }else{
                                        //todo - find primary event
                                        
                                        links[ eventID ].secondary.push( recID_res );
                                        recordset.setFld(record_result, 'rec_RecTypeID', DH_RECORDTYPE_SECONDARY);
                                    }

                                    if(order>0){ //if order is defined current address is node of path
                                        links[ eventID ].path.push({order:order, recID:recID_res});
                                    }

                                    res_records[recID_res] = record_result;
                                    res_orders.push(recID_res);
                                    
                                    //find persons that relates to this event
                                    //do not find person in case this is event search
                                    /*
                                    var rels2 = recordset.getRelationRecords(eventID, RT_PERSON);

                                    if(rels2.length>0){ //we have involved related person
                                        //3b. this is address->event->person
                                        for(j=0; j<rels2.length; j++){
                                            var personID = rels2[j]['related'];
                                            if(links[ eventID ].residence.indexOf(personID)<0){
                                                links[ eventID ].residence.push( personID );
                                            }
                                        }//for persons
                                    }
                                    */
                                }else if(rels[i]['relrt']==RT_PERSON) {
                                    
                                    var recID_res = rels[i]['relation'];
                                    record_result = relationships[recID_res];
                                    
                                    var personID = rels[i]['related'];
                                    var rel_person = records[ personID ];
                                    var relation_type = recordset.fld(record_result, DT_RELATION_TYPE);

                                    //change rectype to residence of this person
                                    recordset.setFld(record_result, 'rec_RecTypeID', DH_RECORDTYPE_RESIDENCES); //change record type 
                                    //role at this address: resident (primary)
                                    recordset.setFld(record_result, 'rec_Icon',     'term'+relation_type );   

                                    var recInfoUrl = window.hWin.HAPI4.baseURL + "hclient/widgets/digital_harlem/dh_popup.php?db="
                                                    +window.hWin.HAPI4.database+"&recID="+
                                                    personID+"&addrID="+recID; //+"&eventID="+eventID

                                    recordset.setFld(record_result, 'rec_Info', recInfoUrl);
                                    recordset.setFld(record_result, 'rec_InfoFull', recInfoUrl+'&full=1'+is_Riot);

                                    //recordset.setFld(record_result, 'rec_Title',  recordset.fld(relrec, 'rec_Title') );
                                    //recordset.setFld(record_result, DT_STARTDATE, recordset.fld(relrec, 'dtl_StartDate' ) );
                                    //recordset.setFld(record_result, DT_ENDDATE,   recordset.fld(relrec, DT_ENDDATE) );

                                    //assign geo from address
                                    var addr_geodata = recordset.fld(record, DT_GEO);
                                    if(addr_geodata){
                                        recordset.setFld(record_result, DT_GEO, addr_geodata);
                                    }else{
                                        console.log('address '+recID+' has no geo');
                                        continue;
                                    }
                                    
                                    var rels2 = recordset.getRelationRecords(personID, RT_EVENT);
                                    if(rels2.length>0){ //events this person is involded into
                                        //3b. this is address->event->person
                                        for(j=0; j<rels2.length; j++){
                                            var eventID = rels2[j]['related'];
                                            if(!links[ eventID ]){
                                                links[ eventID ] = {primary:[], secondary:[], residence:[], is_event:true, path:[] };
                                            }
                                            if(links[ eventID ].residence.indexOf(recID_res)<0){
                                                links[ eventID ].residence.push( recID_res );
                                            }
                                        }//for events
                                    }
                                    
                                    res_records[recID_res] = record_result;
                                    res_orders.push(recID_res);
                                }
                            }//for all records related to address


                            
                        } else if(primary_rt==RT_PERSON) {

                            for(i=0; i<rels.length; i++){ //all records related to addresses
                                if(rels[i]['relrt']==RT_PERSON){
                                    
                                    var recID_res = rels[i]['relation'];
                                    record_result = relationships[recID_res];
                                    
                                    var personID = rels[i]['related'];
                                    var rel_person = records[ personID ];
                                    var relation_type = recordset.fld(record_result, DT_RELATION_TYPE);
                                    
                                    if(relation_type>4500 && relation_type<4513){ //juidical person roles
                                        continue; //exclude these relations from result set
                                    }

                                    recordset.setFld(record_result, 'rec_RecTypeID', DH_RECORDTYPE);
                                    recordset.setFld(record_result, 'rec_Icon', 'term'+relation_type );   //role at this address: resident (primary)

                                    var recInfoUrl = window.hWin.HAPI4.baseURL 
                                        + "hclient/widgets/digital_harlem/dh_popup.php?db="+window.hWin.HAPI4.database
                                        +"&recID="+recordset.fld(rel_person, 'rec_ID')+"&addrID="+recID;
                                        
                                    recordset.setFld(record_result, 'rec_Info', recInfoUrl);
                                    recordset.setFld(record_result, 'rec_InfoFull', recInfoUrl+'&full=1'+is_Riot);

                                    //assign geo from address
                                    var addr_geodata = recordset.fld(record, DT_GEO);
                                    if(addr_geodata){
                                        recordset.setFld(record_result, DT_GEO, addr_geodata);
                                    }else{
                                        console.log('address '+recID+' has no geo');
                                        continue;
                                    }
                                    
                                    //add links for this person
                                    if(!links[ personID ]){
                                        links[ personID ] = {primary:[], secondary:[]};
                                    }
                                    //verify: is it residence
                                    if(relation_type==TERM_ROLE_RESIDENT){  //4527
                                        links[ personID ].primary.push( recID_res );
                                    }else{
                                        links[ personID ].secondary.push( recID_res );
                                    }
                                    
                                    res_records[recID_res] = record_result;
                                    res_orders.push(recID_res);
                                
                                }else { //this is event
                                    
                                    var recID_res = rels[i]['relation'];
                                    record_result = relationships[recID_res];
                                    
                                    var eventID = rels[i]['related'];
                                    //var relrec = relationships[rels[i]['relation']];
                                    var rel_event = records[ eventID ];

                                    //assign to address record values from event record (rel_event) and change its rectype
                                    recordset.setFld(record_result, 'rec_RecTypeID', DH_RECORDTYPE); //change record type 
                                    recordset.setFld(record_result, 'rec_Icon',   'term'+recordset.fld(rel_event, DT_EVENT_TYPE) );
                                    recordset.setFld(record_result, 'rec_Title',  recordset.fld(rel_event, 'rec_Title') );
                                    recordset.setFld(record_result, DT_STARTDATE, recordset.fld(rel_event, 'dtl_StartDate' ) );
                                    recordset.setFld(record_result, DT_ENDDATE,   recordset.fld(rel_event, DT_ENDDATE) );
                                    recordset.setFld(record_result, 'rec_Info',
                                            window.hWin.HAPI4.baseURL + "hclient/widgets/digital_harlem/dh_popup.php?db="
                                            +window.hWin.HAPI4.database
                                            +"&recID="+eventID+"&addrID="+recID+is_Riot);

                                    //assign geo from address
                                    var addr_geodata = recordset.fld(record, DT_GEO);
                                    if(addr_geodata){
                                        recordset.setFld(record_result, DT_GEO, addr_geodata);
                                    }else{
                                        console.log('address '+recID+' has no geo');
                                        continue;
                                    }
                                            
                                    //find persons involved into this event
                                    var rels2 = recordset.getRelationRecords(eventID, RT_PERSON); 
                                    if(rels2.length>0){
                                        for(j=0; j<rels2.length; j++){
                                            var personID = rels2[j]['related'];
                                            if(!links[ personID ]){
                                                links[ personID ] = {primary:[], secondary:[]};
                                            }
                                            if(links[ personID ].secondary.indexOf(recID_res)<0){
                                                links[ personID ].secondary.push( recID_res );
                                            }
                                        }
                                    }
                                    
                                    res_records[recID_res] = record_result;
                                    res_orders.push(recID_res);
                                }
                            }//for
                        }

                    }//if address
                    
                    //res_records[recID] = record;
                    //res_orders.push(recID);
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
            if (links.hasOwnProperty(recID))  //person of event ID
            {
                is_event = links[recID].is_event;

                if(is_event && links[ recID ].path.length>1){
                    //this is part of event path
                    
                    var path = links[ recID ].path;
                    var vertices = [];
                    path.sort( function(a, b){return (a.order<b.order)?-1:1; } );

                    for(i=0; i<path.length; i++){
                        var path_recID = path[i].recID;
                        var pathAddr = res_records[ path_recID ];
                        var type     = recordset.fld(pathAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                        var wkt      = recordset.fld(pathAddr, 'dtl_Geo');
                        var pnt      = window.hWin.HEURIST4.geo.parseCoordinates(type, wkt, 0);
                        if(pnt!=null){
                            vertices.push(pnt.point);
                        }
                    }

                    if(vertices.length>1){
                        // add path shape to first pathAddr (start parade for example)
                        var pathAddr = res_records[ path[0].recID ];
                        recordset.setFld(pathAddr, 'rec_Shape', [{polyline:vertices}] );
                        links[ recID ].primary = [];
                        links[ recID ].secondary = [];
                        
                        pathAddr = res_records[ path[path.length-1].recID ];
                        if(pathAddr) recordset.setFld(pathAddr, 'rec_Icon', 'term0' );
                        
                        for(i=1; i<path.length-1; i++){
                            var path_recID = path[i].recID
                            delete res_records[path_recID];
                            var idx = res_orders.indexOf(path_recID);
                            if(idx>=0)  res_orders.splice(idx,1);
                        }
                        continue;
                    }
                }//event path
                

                //change secondary events icon to dot
                //!IMPORTANT this address may be used by other event! - to CORRECT!!!!!
                if(is_event && links[recID].primary.length>0){
                    for(j=0; j<links[recID].secondary.length; j++){
                        secAddr = res_records[ links[recID].secondary[j] ]; //record id
                        recordset.setFld(secAddr, 'rec_Icon', 'term0' );
                    }
                }
                

                var already_linked_secondary = [], already_linked_residence = [];
                var link_all_orphans = false;
                do{
                                
                for(i=0; i<links[recID].primary.length; i++){

                    mainAddr = res_records[ links[recID].primary[i] ]; //record id
                    var d_start1 = recordset.fld(mainAddr, 'dtl_StartDate' );
                    var d_end1   = recordset.fld(mainAddr, DT_ENDDATE);
                    var type     = recordset.fld(mainAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                    var wkt      = recordset.fld(mainAddr, 'dtl_Geo');
                    var pnt1     = window.hWin.HEURIST4.geo.parseCoordinates(type, wkt, 0);

                    if(pnt1==null) continue;  //no geo data - skip

                    //var shapes = [];

                    if(links[recID].residence){
                    for(j=0; j<links[recID].residence.length; j++){

                        if(link_all_orphans && already_linked_residence.indexOf(links[recID].residence[j])>-1) {
                            continue;   
                        }
                        
                        residenceAddr = res_records[ links[recID].residence[j] ]; //record id
                        var d_start2 = recordset.fld(residenceAddr, 'dtl_StartDate' );
                        var d_end2   = recordset.fld(residenceAddr, DT_ENDDATE);

                        if(link_all_orphans || __isIntersects(d_start1, d_end1, d_start2, d_end2)){
                            type     = recordset.fld(residenceAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                            wkt      = recordset.fld(residenceAddr, 'dtl_Geo');
                            var pnt2     = window.hWin.HEURIST4.geo.parseCoordinates(type, wkt, 0);
                            if(pnt2!=null){
                                var shapes = recordset.fld(residenceAddr, 'rec_Shape');
                                if(!shapes) shapes=[];
                                shapes.push( {polyline:[ pnt1.point, pnt2.point ]} );
                                recordset.setFld(residenceAddr, 'rec_Shape', shapes );
                            }
                            already_linked_residence.push( links[recID].residence[j] );
                        }
                    
                    }
                    }

                    //For events: link primary event to secondary envent
                    //For persons: link residence to his other addresses and events
                    for(j=0; j<links[recID].secondary.length; j++){

                        if(link_all_orphans && already_linked_secondary.indexOf(links[recID].secondary[j])>-1) {
                            continue;   
                        }
                        
                        secAddr = res_records[ links[recID].secondary[j] ]; //record id
                        var d_start2 = recordset.fld(secAddr, 'dtl_StartDate' );
                        var d_end2   = recordset.fld(secAddr, DT_ENDDATE);

                        //for event primary always linked with secondary
                        //for person - primary is residence and it must link to secondary within same timeframe
                        //             if timeframe for primary is not defined it always links to any secondary
                        
                        if(link_all_orphans || is_event || __isIntersects(d_start1, d_end1, d_start2, d_end2)){
                            type     = recordset.fld(secAddr, 'dtl_GeoType');  //take first part of dtl_Geo field - "p wkt"
                            wkt      = recordset.fld(secAddr, 'dtl_Geo');
                            var pnt2     = window.hWin.HEURIST4.geo.parseCoordinates(type, wkt, 0);
                            if(pnt2!=null){
                                if((pnt1.point.lat == pnt2.point.lat && pnt1.point.lon == pnt2.point.lon)){
                                    // do not draw secondary if it has the same coordinates as primary - to avoid clutering on map
                                }else{
                                    var shapes = recordset.fld(secAddr, 'rec_Shape');
                                    if(!shapes) shapes=[];
                                    shapes.push( {polyline:[ pnt1.point, pnt2.point ]} );
                                    recordset.setFld(secAddr, 'rec_Shape', shapes );                                    
                                    //recordset.setFld(secAddr, 'rec_Shape', [{polyline:[ pnt1.point, pnt2.point ]}] );
                                }

                            }
                            already_linked_secondary.push( links[recID].secondary[j] );
                        }
                    }

                    //now shape is added to secondary or residence 
                    //if(shapes.length>0) recordset.setFld(mainAddr, 'rec_Shape', shapes );
                   
                    if(link_all_orphans) break; //link orphans for first primary location     
                }//for primary
                
                    //add all non linked secondary to first primary location
                    if(link_all_orphans){
                        break; //only once
                    }else{ 
                        link_all_orphans = true;
                    }
                
                }while(link_all_orphans);
                
                
                // to avoid clutering on map 
                // hide person's residence icon if there are links from it 
                /* now it can be shown/hid by checkbox
                if(!is_event){
                    for(i=0; i<links[recID].primary.length; i++){
                        mainAddr = res_records[ links[recID].primary[i] ]; //record id
                        var shapes = recordset.fld(mainAddr, 'rec_Shape' ); 
                        if( window.hWin.HEURIST4.util.isArrayNotEmpty(shapes) ){
                            recordset.setFld(mainAddr, 'rec_Icon', 'term00' );
                        }
                    }
                }
                */
                
            }
        }



        return new hRecordSet({
            count: res_orders.length,
            offset:0,
            fields: recordset.getFields(),
            rectypes: [DH_RECORDTYPE, DH_RECORDTYPE_SECONDARY],
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
    function _doApplyRules( originator, rules, rulesonly ){
        /*
        if(window.hWin.HAPI4.currentRecordset && window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.currentRecordset.getMainSet())){

        var request = { q: 'ids:'+window.hWin.HAPI4.currentRecordset.getMainSet().join(','),
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
        doApplyRules:function( originator, rules, rulesonly ){
            return _doApplyRules( originator, rules, rulesonly );
        },

        doStop: function(){
            _searchCompleted( true );
        }

    }

    _init();
    return that;  //returns object
}