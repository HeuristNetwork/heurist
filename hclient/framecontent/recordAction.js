/**
* Class to perform action on set of recordfs in popup dialog
* 
* @param action_type - name of action - used to access help, widget name and method on server side
* @returns {Object}
* @see  hclient/framecontent/record for widgets
* @see  migrated/search/actions
* @see  record_action_help_xxxx in localization.js for description and help
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


function hRecordAction(_action_type) {
    var _className = "RecordAction",
    _version   = "0.4",
    
    selectRecordScope, action_type;
    
    
/*
        header that describes the action
        selector of records: all, selected, by record type
        widget to enter data
        request to server
        results
            given
            processed
            rejected (rights)
            error
*/    
    function _init(){
        
        //fill header with description        
        $('#div_header').html(top.HR('record_action_'+action_type));
        
        //fill selector for records: all, selected, by record type
        selectRecordScope = $('#sel_record_scope')
        .on('change',
            function(e){
                _onRecordScopeChange();
            }
        );
        _fillSelectRecordScope();      
    }

    //
    //
    //
    function _fillSelectRecordScope(){
        
      selectRecordScope.empty();
      if(!top.HAPI4.currentRecordset) return;
      
      var selScope = selectRecordScope.get(0);
        
        //add result count option default
      var opt = new Option("Current results set (count="+ top.HAPI4.currentRecordset.length()+")", "All");
      selScope.appendChild(opt);
      //selected count option
      if ( top.HAPI4.currentRecordsetSelection &&  top.HAPI4.currentRecordsetSelection.length > 0) {
        opt = new Option("Selected results set (count=" + top.HAPI4.currentRecordsetSelection.length+")", "Selected");
        selScope.appendChild(opt);
      }
      //find all types for result and add option for each with counts.
      var rectype_Ids = top.HAPI4.currentRecordset.getRectypes();
      
      for (var rty in rectype_Ids){
            if(rty>=0){
                rty = rectype_Ids[rty];
                opt = new Option(top.HEURIST4.rectypes.pluralNames[rty], rty);
                selScope.appendChild(opt);
            }
      }
      _onRecordScopeChange();
    }
    
    //
    //
    //
    function _onRecordScopeChange() {
        
        if(action_type=='add_detail'){
            $('#div_sel_fieldtype').show();
            _fillSelectFieldTypes();
        }
        
    }
    
    //
    // fill all field type selectors
    //
    function _fillSelectFieldTypes() {
        
        var scope_type = selectRecordScope.val();
            
        var rtyIDs = [], dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
        var rtys = {};
        var i,j,recID,rty,rtyName,dty,dtyName,fieldName,opt;
            
            
        //get record types
        if(scope_type=="All"){
            rtyIDs = top.HAPI4.currentRecordset.getRectypes();
        }else if(scope_type=="Selected"){
            rtyIDs = []; 
            
            //loop all selected records
            for(i in top.HAPI4.currentRecordsetSelection){

                var rty_total_count = top.HAPI4.currentRecordset.getRectypes().length;
                var recID = top.HAPI4.currentRecordsetSelection[i];
                var record  = top.HAPI4.currentRecordset.getById(recID) ;
                rty = top.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');
                
                if (!rtys[rty]){
                    rtys[rty] = 1;
                    rtyIDs.push(rty);
                    if(rtyIDs.length==rty_total_count) break;
                }
            }
            
        }else{
            rtyIDs = [scope_type];
        }
        
        var dtidx = top.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'];
        var fieldSelect = $('#sel_fieldtype');
        fieldSelect.empty();
        fieldSelect = fieldSelect.get(0);

        //for all rectypes find all fields as Detail names sorted
        for (i in rtyIDs) {
            rty = rtyIDs[i];
            rtyName = top.HEURIST4.rectypes.names[rty];
            for (dty in top.HEURIST4.rectypes.typedefs[rty].dtFields) {
              if(top.HEURIST4.detailtypes.typedefs[dty].commonFields[dtidx] in {"separator":1,"relmarker":1}){  //skip
                continue;
              }
              dtyName = top.HEURIST4.detailtypes.names[dty];
              if (!dtys[dtyName]){
                dtys[dtyName] = [];
                dtyNameToID[dtyName] = dty;
                dtyNameToRty[dty] = rty;
                dtyNames.push(dtyName);
              }
              fieldName = rtyName + "." + top.HEURIST4.rectypes.typedefs[rty].dtFields[dty][0];
              dtys[dtyName].push(fieldName);
            }
        }
        if (dtyNames.length >0) {
            dtyNames.sort();
          //add option for DetailType enabled followed by all Rectype.Fieldname options disabled
            for (i in dtyNames) {
              dtyName = dtyNames[i];
              var dtyID = dtyNameToID[dtyName];
              opt = new Option(dtyName, dtyNameToRty[dtyID]+'-'+dtyID);
              fieldSelect.appendChild(opt);
              //sort RectypeName.FieldName
              dtys[dtyName].sort();
              for (j in dtys[dtyName]){
                fieldName = dtys[dtyName][j];
                opt = new Option(".  ."+fieldName, "");
                opt.innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"+fieldName;
                opt.disabled = "disabled";
                fieldSelect.appendChild(opt);
              }
            }
        }else{
            opt = new Option("no suitable fields", "");
            fieldSelect.appendChild(opt);
        }
        fieldSelect.onchange = _createInputElement;
        _createInputElement();
    }
    
    //
    // crete editing_input element for selected field type
    //
    function _createInputElement(){
        
                   var dtID = $('#sel_fieldtype').val().split('-');
                   var dtidx = top.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'];
                   var rectypeID = dtID[0];
                   dtID = dtID[1];
        
        //top.HEURIST4.util.cloneObj(
                   var dtFields = top.HEURIST4.rectypes.typedefs[rectypeID].dtFields[dtID];
                   var fi = top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
                   
                   dtFields[fi['rst_DisplayName']] = 'Value to be added';
                   dtFields[fi['rst_RequirementType']] = 'optional';
                   dtFields[fi['rst_MaxValues']] = 1;
                   dtFields[fi['rst_DisplayWidth']] = 0;
        
                   var ed_options = {
                                recID: -1,
                                dtID: dtID,
                                //rectypeID: rectypeID,
                                rectypes: top.HEURIST4.rectypes,
                                values: '',
                                readonly: false,
                                //title:  harchy + "<span style='font-weight:bold'>" + field['title'] + "</span>",
                                showclear_button: false,
                                //detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
                                dtFields:dtFields
                                
                        };
                        
                   var $fieldset = $('#div_widget>fieldset');
        
                   $("<div>").editing_input(ed_options).appendTo($fieldset);
    }
    


    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    action_type = _action_type;
    _init();
    return that;  //returns object
}
    