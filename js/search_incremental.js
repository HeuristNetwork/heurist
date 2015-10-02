/**
* Incremental search 
* main request result is returned by chunks
* then rules use parents results (by 1000) and return results by chunks also
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

function hSearchIncremental() {
     var _className = "SearchIncremental",
         _version   = "0.4";

    var _rule_index = -1, // current index
        _res_index =  -1, // current index in result array (chunked by 1000)
        _rules = [],       // flat array of rules for current query request

    /* format

         rules:{   {parent: index,  // index to top level
                    level: level,
                    query: },

         results: { root:[], ruleindex:[  ],  ruleindex:[  ] }


         requestXXX - index in rules array?  OR query string?

            ids:[[1...1000],[1001....2000],....],     - level0
            request1:{ ids:[],                          level1
                    request2:{},                        level2
                    request2:{ids:[],
                            request3:{} } },            level3

    */

        _query_request = null,  //current search request - need for "AND" search in current result set
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
        //_progressCallback = progressCallback;
        
        _onSearchStart( request );
    }
    
    //incremental search and rules
    //@todo - do not apply rules unless main search is finished
    //@todo - if search in progress and new rules are applied - stop this search and remove all linked data (only main search remains)
    function _doSearchIncrement(){

        if ( _query_request ) {

            _query_request.source = _owner_element_id;


            var new_offset = Number(_query_request.o);
            new_offset = (new_offset>0?new_offset:0) + Number(_query_request.l);

            var total_count_of_curr_request = (top.HAPI4.currentRecordset!=null)?top.HAPI4.currentRecordset.count_total():0;

            if(new_offset< total_count_of_curr_request){ //search for next chunk of data within current request
                    _query_request.o = new_offset;
                    _query_request.source = _owner_element_id;
                    top.HAPI4.RecordMgr.search(_query_request, _onSearchResult);//$(_owner_doc));   //search for next chunk
                    return true;
            }else{

         // original rule array
         // rules:[ {query:query, levels:[]}, ....  ]

         // we create flat array to allow smooth loop
         // rules:[ {query:query, results:[], parent:index},  ]


                     // _rule_index;  current rule
                     // _res_index;   curent index in result array (from previous level)

                     var current_parent_ids;
                     var current_rule;

                     while (true){ //while find rule with filled parent's resultset

                         if(_rule_index<1){
                             _rule_index = 1; //start with index 1 - since zero is main resultset
                             _res_index = 0;
                         }else{
                             _res_index++;  //goto next 1000 records
                         }

                         if(_rule_index>=_rules.length){
                             _rule_index = -1; //reset
                             _res_index = -1;
                             
                             return false; //this is the end
                         }

                         current_rule = _rules[_rule_index];
                         //results from parent level
                         current_parent_ids = _rules[current_rule.parent].results;

                         
                         //if we access the end of result set - got to next rule
                         if(_res_index >= current_parent_ids.length)
                         {
                            _res_index = -1;
                            _rule_index++;

                         }else{
                            break;
                         }
                     }


                     //create request
                     _query_request.q = current_rule.query;
                     _query_request.topids = current_parent_ids[_res_index].join(','); //list of record ids of parent resultset
                     _query_request.o = 0;
                     _query_request.source = _owner_element_id;

                     _query_request.getrelrecs = (top.HAPI4.sysinfo['layout']=='DigitalHarlem')?1:0; //@todo more elegant way: include request for relationship records into rule

                     top.HAPI4.RecordMgr.search(_query_request, _onSearchResult); //search rules
                     return true;

            }
        }

        return false;
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
    
    function _progressInfo(){
        
            var progress_text = '';
            var progress_value = 0
            
            // show current records range and total count
            if(top.HAPI4.currentRecordset && top.HAPI4.currentRecordset.length()>0){

                var s = '';

                if(_rule_index>0){ //this search by rules
                    s = 'Rules: '+_rule_index+' of '+(_rules.length-1)+'  ';
                }
                s = s + 'Total: ' + top.HAPI4.currentRecordset.length();


                var curr_offset = Number(_query_request.o);
                curr_offset = (curr_offset>0?curr_offset:0) + Number(_query_request.l);

                var tot = top.HAPI4.currentRecordset.count_total();  //count of records in current request

                if(curr_offset>tot) curr_offset = tot;

                if(_rule_index<1){  //this is main request

                        //search in progress
                        progress_text = '<span style="margin-left:180px">'+ ((curr_offset==tot)?tot:'Loading '+curr_offset+' of '+tot) + '</span>' ;

                        progress_value = curr_offset/tot*100;

                }else{ //this is rule request

                         if( _rule_index < _rules.length){

                            //count of chunks of previous result set = number of queries
                            var res_steps_count = _rules[_rules[_rule_index].parent].results.length;

                            s = "Applying rule set ";
                            if(_rules.length>2) //more than 1 rule
                               s  = s + _rule_index+' of '+(_rules.length-1);
                            s = s + '. ';
                            var alts = s;

                            //show how many queries left
                            var queries_togo = res_steps_count - (_res_index+1);
                            if(queries_togo>0)
                                s = s + queries_togo + ' quer' + (queries_togo>1?'ies':'y') + ' to go. ';
                            //count the total amount of loaded records for this rule
                            var cnt_loaded = 0;
                            $.each(_rules[_rule_index].results, function(index, value){ cnt_loaded = cnt_loaded + value.length; });
                            s = s + 'Loaded '+cnt_loaded;

                            //alternative css('margin-left','10px')
                            progress_text = alts + "Loaded " + (curr_offset==tot ?tot:curr_offset+' of '+tot)+' for query '+  (_res_index+1)+' of '+res_steps_count;

                            progress_value = _res_index/res_steps_count*100 + curr_offset/tot*100/res_steps_count;
                         }

                }

            return {text:progress_text, value:progress_value};
        }
        
        
    }
    
    /**
    * Rules may be applied at once (part of query request) or at any time later
    *
    * 1. At first we have to create flat rule array
    */
    function _prepareRules( rules_tree ){

         // original rule array
         // rules:[ {query:query, levels:[]}, ....  ]

         // we create flat array to allow smooth loop
         // rules:[ {query:query, results:[], parent:index},  ]

        var flat_rules = [ { results:[] } ];

        function __createFlatRulesArray(r_tree, parent_index){
            var i;
            for (i=0;i<r_tree.length;i++){
                var rule = { query: r_tree[i].query, results:[], parent:parent_index };
                
                flat_rules.push(rule);
                __createFlatRulesArray(r_tree[i].levels, flat_rules.length-1);
            }
        }

        //it may be json
        if(!top.HEURIST.util.isempty(rules_tree) && !$.isArray(rules_tree)){
             rules_tree = $.parseJSON(rules_tree);
        }

        __createFlatRulesArray(rules_tree, 0);

        //assign zero level - top most query
        if(top.HAPI4.currentRecordset!=null){  //aplying rules to existing set

            //result for zero level retains
            flat_rules[0].results = _rules[0].results;

            _rule_index = 0; // current index
        }else{
            _rule_index = -1;
        }
        _res_index = 0; // current index in result array (chunked by 1000)

        _rules = flat_rules;

    }
    
    //
    //
    //
    function _onSearchStart( request ){
    
            if(request==null) return;

            if(top.HEURIST4.util.isnull(request.id)){ //unique id for request
                request.id = Math.round(new Date().getTime() + (Math.random() * 100));
                
            }
            if(!top.HEURIST4.util.isnull(_owner_doc)){
                $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event  
            }
        
            if(top.HEURIST4.util.isempty(request.topids)){ //topids not defined - this is not rules request

                 top.HEURIST4.current_query_request = jQuery.extend(true, {}, request); //the only place where this values is assigned - it is used in mainMenu.js
                 _query_request = request; //keep for search in current result

                 top.HAPI4.currentRecordset = null;

                 if(request.q!=''){
                     
                        //reset counters and storages
                        _rule_index = -1; // current index
                        _res_index =  -1; // current index in result array (chunked by 1000)
                        _rules = [];      // flat array of rules for current query request

                        if( _query_request.rules!=null ){
                            //create flat rule array
                            _prepareRules(_query_request.rules);
                        }
                        
                 }
            }
        
            //reset rules parameter - since we search incrementally from client side
            request.rules = null;
            //perform search
            top.HAPI4.RecordMgr.search(request, _onSearchResult); //$(_owner_doc)); 
        
    }
    
    //
    // apply rules to existed result set
    //
    function _doApplyRules( originator, rules ){
        
                if(rules){

                    //create flat rule array
                    _prepareRules( rules ); //indexes are rest inside this function

                    //if rules were applied before - need to remove all records except original set and re-render
                    if(!top.HEURIST.util.isempty(_rules) && _rules[0].results.length>0){

                         //keep json (to possitble save as saved searches)
                         that.query_request.rules = rules;

                         //remove results of other rules and re-render the original set of records only
                         var rec_ids_level0 = [];
                         var idx;
                         _rules[0].results = _rules[0].results;
                         for(idx=0; idx<_rules[0].results.length; idx++){
                            rec_ids_level0 = rec_ids_level0.concat(_rules[0].results[idx]);
                         }

                         //var recordset_level0 - only main set remains all result from rules are removed
                         top.HAPI4.currentRecordset = top.HAPI4.currentRecordset.getSubSetByIds(rec_ids_level0);

                         _rule_index = -2;
                         _res_index = 0;

                         /* @todo - move to 
                         this.div_search.css('display','none');
                         this.div_progress.width(this.div_search.width());
                         this.div_progress.css('display','inline-block');
                         */
                         
                         //fake result search event
                         if(!top.HEURIST4.util.isnull(_owner_doc)){
                            $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCHSTART, [ null ]);  //global app event to clear views
                            $(_owner_doc).trigger(top.HAPI4.Event.ON_REC_SEARCHRESULT, [ top.HAPI4.currentRecordset ]);  //global app event
                         }
                         if(!_doSearchIncrement()){//start search rules
                            _searchCompleted( false );
                         }
                         
                    } else if(!top.HEURIST.util.isempty(_rules)){
                        return false;
                    }
                }
                return true;
    }
    
    //
    // callback function for search request 
    //
    function _onSearchResult(response){
            var resdata = null;
            if(response.status == top.HAPI4.ResponseStatus.OK){
                resdata = new hRecordSet(response.data);


                if(_query_request!=null && resdata.queryid()==_query_request.id) {

                    //save increment into current rules.results
                    var records_ids = Hul.cloneJSON(resdata.getIds());
                    if(records_ids.length>0){
                        // rules:[ {query:query, results:[], parent:index},  ]
                        if(_rule_index==-2){
                            _rule_index=0;
                        }else{
                            var ruleindex = _rule_index;
                            if(ruleindex<0){
                                ruleindex = 0; //root/main search
                            }
                            if(Hul.isempty(_rules)){
                                _rules = [{results:[]}];
                            }
                            //git main result set
                            if(ruleindex==1 && !top.HEURIST4.util.isArrayNotEmpty(_rules[ruleindex].results) ){
                                top.HAPI4.currentRecordset.setMainSet( Hul.cloneJSON(top.HAPI4.currentRecordset.getIds()) );
                            }
                            
                            _rules[ruleindex].results.push(records_ids);

                            //unite
                            if(top.HAPI4.currentRecordset==null){
                                top.HAPI4.currentRecordset = resdata;
                            }else{
                                //unite record sets
                                top.HAPI4.currentRecordset = top.HAPI4.currentRecordset.doUnite(resdata);
                            }
                            top.HAPI4.currentRecordsetByLevels = _rules; //contains main result set and rules result sets

                        }
                    }

                    resdata.setProgressInfo( _progressInfo() );

                    if(!_doSearchIncrement()){//load next chunk or start search rules
                        _searchCompleted( false );
                    }

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