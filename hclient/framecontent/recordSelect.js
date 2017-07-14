/**
* NOT USED ANYMORE _ TO REMOVE
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
                var btn_search_start = $('#btn_search_start')
                    //.css({'width':'6em'})
                    .button({label: window.hWin.HR("Start search"), text:false, icons: {
                        secondary: "ui-icon-search"
                    }})
                    .click(function(e) {
                            //window.hWin.HAPI4.SearchMgr.doStop();
                             _startSearch();
                        });
                        
                var btn_add_record = $('#btn_add_record')
                    .css({'min-width':'11.9em'})
                    .button({label: window.hWin.HR("Add Record"), icons: {
                        primary: "ui-icon-plus"
                    }})
                    .click(function(e) {
                                alert('To be implemented');
                                /*
                                var recTypeId =  $('#sel_rectypes').val();                     
                                var title = "Add new record "+$("#sel_rectypes option:selected" ).text();

                                var url = window.hWin.HAPI4.baseURL 
                                    + 'records/add/formAddRecordPopup.html?fromadd=new_bib&rectype='
                                    + recTypeId
                                    + '&title='+$('#input_search').val()
                                    + '&db='+ window.hWin.HAPI4.database
                                    + '&t=' + (new Date).getMilliseconds();

                                window.hWin.HEURIST4.msg.showDialog(url, {height:600, width:800,
                                    title: title,
                                    class:'ui-heurist-bg-light',
                                    callback: function(arguments){
                                        if(arguments && arguments.length>2){
                                            var recID = arguments[3];
console.log(arguments);                                        
                                            if( recID>0 ){
                                                window.close([recID]);
                                            }
                                        }
                                    }
                                } ); 
                                */                   
                        });
        
                input_search = $('#input_search')
                    .on('keypress',
                    function(e){
                        var code = (e.keyCode ? e.keyCode : e.which);
                            if (code == 13) {
                                window.hWin.HEURIST4.util.stopEvent(e);
                                e.preventDefault();
                                 _startSearch();
                            }
                    });
                    
                selectRectype = $('#sel_rectypes')
                .on('change',
                    function(e){
                        if(selectRectype.val()>0){
                            lbl = window.hWin.HR('Add')+' '+$("#sel_rectypes option:selected" ).text();
                        }else{
                            lbl = window.hWin.HR("Add Record");
                        }
                        
                        btn_add_record.button('option','label',lbl);
                        _startSearch();
                    }
                );
                selectRectype.empty();
                window.hWin.HEURIST4.ui.createRectypeSelect(selectRectype.get(0), rectype_set, 
                    rectype_set?null:window.hWin.HR('Any Record Type'));
                          
                //init record list          
                recordList = $('#recordList')
                        .resultList({eventbased: false, 
                                    show_toolbar: false, 
                                    multiselect: false,
                                    isapplication: false,
                                       onselect: function(event, selected_recs){
                                           if(selected_recs && selected_recs.length()>0)
                                                window.close(selected_recs);
                                    },
                                   empty_remark: '<div style="padding:1em 0 1em 0">Please use the search field above to locate relevant records (partial string match on title)</div>'
                                   +'<div>If there are no suitable records, you may create a new record which is automatically selected as the target.</div>'
                        });     
        
                var ishelp_on = window.hWin.HAPI4.get_prefs('help_on')==1;
                $('.heurist-helper1').css('display',ishelp_on?'block':'none');
        
                //force search if rectype_set is defined
                if(selectRectype.val()>0){
                    selectRectype.change();
                }

        
        
    }

    function _startSearch(){
        
            var qstr = '';
            if(selectRectype.val()!=''){
                qstr = 't:'+selectRectype.val();
            }
            if(input_search.val()!=''){
                qstr = ' title:'+input_search.val();
            }
            
            //noothing defined
            if(qstr==''){
                $(recordList).resultList('updateResultSet', new hRecordSet());
            }else{
            
        
                        var request = { q: qstr,
                                        w: 'a',
                                        limit: 100000,
                                        needall: 1,
                                        detail: 'ids',
                                        
                                        id: window.hWin.HEURIST4.util.random()
                                        //source: this.element.attr('id') 
                                       };

                        //that.loadanimation(true);

                        window.hWin.HAPI4.RecordMgr.search(request, function( response ){

                            //that.loadanimation(false);

                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                $(recordList).resultList('updateResultSet', new hRecordSet(response.data), request);

                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }

                        });
            }
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
    