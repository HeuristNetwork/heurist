/**
* Minimal search
* returns only ids
* rules are searched on server side
* returns only first 2000 records
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

function hSearchMinimal() {
     var _className = "SearchMinimal",
         _version   = "0.4";


        _query_request = null,
        _owner_doc = null,
        _owner_element_id = null,
        _progressCallback = null;
         
         
         
    /**
    * Initialization
    */
    function _init( ) {
    }
    
    function _doSearch( originator, request ){
        
        if(originator){
            if(originator.document){
                _owner_doc = originator.document;
                _owner_element_id = originator.element.attr('id');
            }else{
                _owner_doc = originator;
                _owner_element_id = 'main_doc';
            }
        }else{
            _owner_doc = null;
            _owner_element_id = null;
        }
        
        _onSearchStart( request );
    }
    
    //
    //
    //
    function _onSearchStart( request ){
    
            if(request==null) return;

            if( top.HEURIST4.util.isnull(request.id) ) { //unique id for request
                request.id = Math.round(new Date().getTime() + (Math.random() * 100));
            }
            
            request.source = _owner_element_id;
            request.limit = 100000; //or nochunk=1 ?
            request.nochunk = 1;
            request.idonly = 1;
            
            if(!top.HEURIST4.util.isnull(_owner_doc)){
                $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event  
            }
        
            //clone - to use mainMenu.js
            top.HEURIST4.current_query_request = jQuery.extend(true, {}, request); //the only place where this values is assigned - it is used in mainMenu.js
            _query_request = request; //keep for search in current result

             top.HAPI4.currentRecordset = null;
             top.HAPI4.currentRecordsetByLevels = null; //to remove - not used anymore
             
             //perform search
             top.HAPI4.RecordMgr.search(request, _onSearchResult);
        
    }
    
    //
    // callback function for search request 
    //
    function _onSearchResult(response){

            var resdata = null;
            if(response.status == top.HAPI4.ResponseStatus.OK){

                if(_query_request!=null && response.data.queryid==_query_request.id) {

                    resdata = new hRecordSet(response.data);
                    
                    resdata.setRequest( top.HEURIST4.util.cloneJSON(_query_request) );
                    
                    top.HAPI4.currentRecordset = resdata;

                    _searchCompleted( false );
                }

            }else{

                top.HEURIST4.util.showMsgErr(response);

                if(!top.HEURIST4.util.isnull(_owner_doc)){ 
                    $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, null );   
                }
            }
            if(!top.HEURIST4.util.isnull(_owner_doc)){
                $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCHRESULT, [ resdata ]);  //gloal app event
            }    
    }
    

    /**
    * 
    * 
    */
    function _searchCompleted( is_terminate ){

            if(_query_request!=null && is_terminate){
                _query_request.id = Math.round(new Date().getTime() + (Math.random() * 100));
            }
            
            if(!top.HEURIST4.util.isnull(_owner_doc)){ 
                if(top.HAPI4.currentRecordset && top.HAPI4.currentRecordset.length()>0)
                    $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, [ top.HAPI4.currentRecordset ]); //global app event
                else{
                    $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCH_FINISH, null); //global app event
                }
            }
    }

    //
    // apply rules to existed result set
    //
    function _doApplyRules( originator, rules ){
        
        if(top.HAPI4.currentRecordset && top.HEURIST4.util.isArrayNotEmpty(top.HAPI4.currentRecordset.getMainSet())){
        
            var request = { q: 'ids:'+top.HAPI4.currentRecordset.getMainSet().join(','),
                            rules: rules,
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

    _init( );
    return that;  //returns object
}