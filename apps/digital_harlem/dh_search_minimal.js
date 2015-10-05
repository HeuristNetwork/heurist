/**
* Special search class for Digital Harlem (based on search_minimal.js Minimal search)
* it replaces standard search in top.HAPI4.SearchMgr
* 
* 1) it adds special parameters to search request:
*       getrelrecs - get relationship records
*       details - return only required fields
* 2) And performs processing on the result set
* 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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
        request.detail = '5,6,7,9,10,11,28,74';

        top.HAPI4.RecordMgr.search(request,
            function(response) {
                if(response.status != top.HAPI4.ResponseStatus.OK){
                    top.HEURIST4.util.showMsgErr(response);
                    return;
                }

                var final_recordset = _prepareResultSet( new hRecordSet(response.data) );
                
                callback( final_recordset );
                
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
    function _doSearch( originator, request ){
        
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
                    top.HEURIST4.util.showMsgErr(response);
                    return;
                }

                var recordset = new hRecordSet(response.data);
                
                var app = appGetWidgetByName('dh_search');  //appGetWidgetById('ha51'); 
                if(app && app.widget){
                        $(app.widget).dh_search('updateResultSet', recordset, keepRequest);
                }
                
                if(originator && !top.HEURIST4.util.isnull(originator.document) && recordset.length()>0){
                    $(originator.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ recordset ]); //global app event  
                }
                
                
            }
            );
        
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
        
    var DH_RECORDTYPE = 13
        
    // 5. assign to address records  date, title, eventtype (from event) or date, title, role from place role
    //              and record id from main request (person or event)
    // 6. final resultset: exclude all recordtypes but addresses
    function _prepareResultSet( recordset ){
       
        var res_records = {}, res_orders = [];
        
        //1. find address records
        var idx, i, j, relrec, related;
        
        var records = recordset.getRecords();
        var relationships = recordset.getRelationship();
        
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
                        recordset.setFld(record, 'rec_PopupUrl', 
                            top.HAPI4.basePathOld + "apps/digital_harlem/dh_popup.php?db="+top.HAPI4.database+"&recID="+recID);
                        
                    }else{
                    
                    
                        for(i=0; i<rels.length; i++){
                            //3. if event try to find related persons
                            if(rels[i]['relrt'] == RT_EVENT){
                                
                                //var relrec = relationships[rels[i]['relation']];
                                var rel_event = records[rels[i]['related']];
                                var rec_ID_event = recordset.fld(rel_event, 'rec_ID')

                                recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //?????
                                recordset.setFld(record, 'rec_Icon',   'term'+recordset.fld(rel_event, DT_EVENT_TYPE) );
                                recordset.setFld(record, 'rec_Title',  recordset.fld(rel_event, 'rec_Title') );
                                recordset.setFld(record, DT_STARTDATE, recordset.fld(rel_event, 'dtl_StartDate' ) );
                                recordset.setFld(record, DT_ENDDATE,   recordset.fld(rel_event, DT_ENDDATE) );
                                
                                //find persons that relates to this event
                                
                                var rels2 = recordset.getRelationRecords(rec_ID_event, RT_PERSON);
                                if(rels2.length<1){
                                    //3a. this is event->address 
                                    recordset.setFld(record, 'rec_PopupUrl', 
                                    top.HAPI4.basePathOld + "apps/digital_harlem/dh_popup.php?db="+top.HAPI4.database
                                            +"&recID="+rec_ID_event);
                                                            
                                }else{
                                    //3b. this is address->event->person
                                    var rel_person = records[rels[i]['related']];
                                    
                                    recordset.setFld(record, 'rec_PopupUrl', 
                                    top.HAPI4.basePathOld + "apps/digital_harlem/dh_popup.php?db="+top.HAPI4.database+"&recID="+
                                            recordset.fld(rel_person, 'rec_ID'));
                                }
                                
                            }else if(rels[i]['relrt'] == RT_PERSON){
                                    //3c. this is person->address
                                var relrec = relationships[rels[i]['relation']];
                                var rel_person = records[rels[i]['related']];

                                recordset.setFld(record, 'rec_RecTypeID', DH_RECORDTYPE); //?????
                                recordset.setFld(record, 'rec_Icon',     'term'+recordset.fld(relrec, DT_RELATION_TYPE) );
                                recordset.setFld(record, 'rec_PopupUrl', 
                                    top.HAPI4.basePathOld + "apps/digital_harlem/dh_popup.php?db="+top.HAPI4.database+"&recID="+
                                                            recordset.fld(rel_person, 'rec_ID'));
                                                            
                                recordset.setFld(record, 'rec_Title',  recordset.fld(relrec, 'rec_Title') );
                                recordset.setFld(record, DT_STARTDATE, recordset.fld(relrec, 'dtl_StartDate'    ) );
                                recordset.setFld(record, DT_ENDDATE,   recordset.fld(relrec, DT_ENDDATE) );
                                
                            }
                        }
                    
                    }
                    
                    res_records[recID] = record;
                    res_orders.push(recID);
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