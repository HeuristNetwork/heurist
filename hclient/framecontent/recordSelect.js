/**
* Class to search and select record 
* It can be converted to widget later?
* 
* @param rectype_set - allowed record types for search, otherwise all rectypes will be used
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

function hRecordSelect(rectype_set) {
    var _className = "RecordSelect",
    _version   = "0.4",
    
    input_search, recordList, selectRectype;
    
    
    function _init(rectype_set){
        
                //init buttons
                var btn_search_stop = $('#btn_search_stop')
                    .css({'width':'6em'})
                    .button({label: top.HR("Stop"), icons: {
                        secondary: "ui-icon-cancel"
                    }})
                    .click(function(e) {
                            //top.HAPI4.SearchMgr.doStop();
                           
                        });
                var btn_search_start = $('#btn_search_start')
                    //.css({'width':'6em'})
                    .button({label: top.HR("Start search"), text:false, icons: {
                        secondary: "ui-icon-search"
                    }})
                    .click(function(e) {
                            //top.HAPI4.SearchMgr.doStop();
                             _startSearch();
                        });
                        
                var btn_add_record = $('#btn_add_record')
                    .css({'width':'11.9em'})
                    .button({label: top.HR("Add Record"), icons: {
                        primary: "ui-icon-plus"
                    }})
                    .click(function(e) {
                            //top.HAPI4.SearchMgr.doStop();
                            alert('Add rec');
                        });
        
                input_search = $('#input_search')
                    .on('keypress',
                    function(e){
                        var code = (e.keyCode ? e.keyCode : e.which);
                            if (code == 13) {
                                top.HEURIST4.util.stopEvent(e);
                                e.preventDefault();
                                 _startSearch();
                            }
                    });
                    
                selectRectype = $('#sel_rectypes')
                .on('change',
                    function(e){
                        _startSearch();
                    }
                );
                selectRectype.empty();
                top.HEURIST4.ui.createRectypeSelect(selectRectype.get(0), rectype_set, 
                    rectype_set?null:top.HR('Any Record Type'));
                          
                //init record list          
                recordList = $('#recordList')
                        .resultList({eventbased: false, 
                                       showmenu: false, 
                                    multiselect: false,
                                       onselect: function(event, selected_recs){
                                           if(selected_recs && selected_recs.length()>0)
                                                window.close(selected_recs);
                                    }});     
        
    }

    function _startSearch(){
        
            var qstr = '';
            if(selectRectype.val()!=''){
                qstr = 't:'+selectRectype.val();
            }
            if(input_search.val()!=''){
                qstr = ' title:'+input_search.val();
            }
            
        
                        var request = { q: qstr,
                                        w: 'a',
                                        limit: 100000,
                                        needall: 1,
                                        detail: 'ids',
                                        
                                        id: Math.round(new Date().getTime() + (Math.random() * 100))};
                                        //source: this.element.attr('id') };

                        //that.loadanimation(true);

                        top.HAPI4.RecordMgr.search(request, function( response ){

                            //that.loadanimation(false);

                            if(response.status == top.HAPI4.ResponseStatus.OK){

                                $(recordList).resultList('updateResultSet', new hRecordSet(response.data), request);

                            }else{
                                top.HEURIST4.msg.showMsgErr(response);
                            }

                        });

    }

    function _stopSearch(){
        
    }

    function _addRecord(){
        
    }
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    _init(rectype_set);
    return that;  //returns object
}
    