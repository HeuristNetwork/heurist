/**
* Search wrapper for hRecordMgr.search. 
* It executes this method either callback or global events.
* It allows to load entire set of records (incremental search by chunks has been disabled)
* 
* It searches for record IDS only. Rules are searched on server side
* 
* Three main methods
* 
* doSearchWithCallback - result is passed to provided callback function
*
* doSearch - before search it trigers global event ON_REC_SEARCHSTART
*            on finish ON_REC_SEARCHFINISH and pass result as event data
*            besides it keeps result in HAPI4.currentRecordset
* 
* doApplyRules - applies rules to existing result set (HAPI4.currentRecordset)
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

function hRecordSearch() {
     var _className = "hRecordSearch",
         _version   = "0.4";


        _query_request = null,
        _owner_doc = null; //to trigger ON_REC_SEARCHSTART and ON_REC_SEARCHFINISH
         
         
         
    /**
    * Initialization
    */
    function _init( ) {
    }
    
    //
    // standalone search with callback (without event trigger)
    //
    function _doSearchWithCallback( request, callback ){
        
        window.hWin.HAPI4.RecordMgr.search(request,
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    if(response.data  && response.data.memory_warning){
                           window.hWin.HEURIST4.msg.showMsgErr(response.data.memory_warning); 
                    }
                    
                    callback( hRecordSet(response.data) );
                }else{
                    callback( null );
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
        
    }

    //
    // search with event triggers 
    //   
    function _doSearch( originator, request ){
        
            var owner_element_id, owner_doc;
        
            if(originator){
                if(originator.document){
                    owner_doc = originator.document;
                    owner_element_id = originator.element?originator.element.attr('id'):'main_doc';
                }else{
                    owner_doc = originator;
                    owner_element_id = 'main_doc';
                }
            }else{
                owner_doc = null;
                owner_element_id = null;
            }
    
            if(request==null) return;

            if( window.hWin.HEURIST4.util.isnull(request.id) ) { //unique id for request
                request.id = window.hWin.HEURIST4.util.random();
            }
            
            request.source = owner_element_id;
            request.limit = 100000; 
            request.needall = 1;
            request.detail = 'ids';

            if(_query_request==null){
                _query_request = {};
                _owner_doc = {};
            }
            _query_request[request.id] = request; //keep for search in current result
            _owner_doc[request.id] = owner_doc;
        
        
            //window.hWin.HEURIST4.current_query_request,  window.hWin.HAPI4.currentRecordset !!!!! @todo get rid these global vars 
            // they are used in old parts: smarty, diagram
            
            //clone - to use mainMenu.js
            if(window.hWin.HEURIST4.util.isempty(request.search_realm)){
                window.hWin.HEURIST4.current_query_request = jQuery.extend(true, {}, request); //the only place where this values is assigned - it is used in mainMenu.js
            }

            window.hWin.HAPI4.currentRecordset = null;
            if(!window.hWin.HEURIST4.util.isnull(owner_doc)){
                
                /*$(_owner_doc)[0].dispatchEvent(new CustomEvent("start_search", {
                  bubbles: true,
                  detail: 'some data'
                }));*/

                $(owner_doc).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event  
            }

            //perform search
            window.hWin.HAPI4.RecordMgr.search(request, function(response){
                    _onSearchResult(response);   
            });
        
    }
    
    //
    // callback function for search request 
    //
    function _onSearchResult(response){

            var recordset = null;
            if(_query_request!=null && _query_request[response.queryid]) {
                
                var qid = response.queryid;
                var qr = window.hWin.HEURIST4.util.cloneJSON(_query_request[qid]);

                if(response.status == window.hWin.ResponseStatus.OK){

                        if(response.data  && response.data.memory_warning){
                               window.hWin.HEURIST4.msg.showMsgErr(response.data.memory_warning); 
                        }
                        
                        recordset = new hRecordSet(response.data);
                        recordset.setRequest( qr  );

                }else{
                    //error - trigger event with empty resultset
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
                if(window.hWin.HEURIST4.util.isempty(qr.search_realm)){
                    window.hWin.HAPI4.currentRecordset = recordset;
                }
                
                if(!window.hWin.HEURIST4.util.isnull(_owner_doc[qid])){ 
                    //global app event
                    $(_owner_doc[qid]).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH,   //_searchCompleted
                                {search_realm: qr.search_realm, 
                                 recordset: recordset,      //result 
                                 request: qr,
                                 query: qr.q}); //orig query
                }
                
                delete _query_request[qid];
                delete _owner_doc[qid];
            }
            
    }
    

    /**
    * 
    */
    function _searchTerminate(){
        _query_request = null;
        _owner_doc = null;
    }

    //
    // apply rules to existed result set
    //
    function _doApplyRules( originator, rules, rulesonly, search_realm ){
        
        if(window.hWin.HAPI4.currentRecordset && window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.currentRecordset.getOrder())){
        
            var request = { apply_rules:true, //do not include search in browser and search input
                            q: 'ids:'+window.hWin.HAPI4.currentRecordset.getOrder().join(','),
                            rules: rules,
                            rulesonly: rulesonly,
                            search_realm: search_realm,
                            w: _query_request?_query_request.w:'a'};
        
            _doSearch( originator, request );
            return true;
        }else{
            return false;
        }
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
        doApplyRules:function( originator, rules, rulesonly, search_realm ){
            return _doApplyRules( originator, rules, rulesonly, search_realm );
        },
        
        doStop: function(){
            _searchTerminate();
        }
        
    }

    _init( );
    return that;  //returns object
}