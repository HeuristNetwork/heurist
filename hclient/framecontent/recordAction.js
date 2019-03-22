/**
* Class to perform action on set of records in popup dialog
*
* @param action_type - name of action - used to access help, widget name and method on server side
* @returns {Object}
* @see  hclient/framecontent/record for widgets
* @see  migrated/search/actions
* @see  record_action_help_xxxx in localization.js for description and help

IT USES
    window.hWin.HAPI4.currentRecordset
    window.hWin.HAPI4.currentRecordsetSelection


*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

/*

1) record detail batch update
2) record type change

*/
function hRecordAction(_action_type, _scope_type, _field_type, _field_value) {
    var _className = "RecordAction",
    _version   = "0.4",
    session_id,
    progressInterval,

    selectRecordScope, allSelectedRectypes;

    var action_type = _action_type,
        init_scope_type = _scope_type,
        init_field_type = _field_type,
        init_field_value = _field_value;


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
        $('#div_header').html(window.hWin.HR('record_action_'+action_type));
        
        var btn_start_action = $('#btn-ok').button({label:window.hWin.HR('Go')});
        
        
        selectRecordScope = $('#sel_record_scope')
        .on('change',
            function(e){
                _onRecordScopeChange();
            }
        );
        btn_start_action.addClass('ui-state-disabled'); //.click(_startAction);        
        
        _fillSelectRecordScope();
        
        $('#btn-cancel').button({label:window.hWin.HR('Cancel')}).click(function(){window.close();});

        
        //global listener - it does not work
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
                +window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {
                    
              if(!data || data.origin!='recordAction'){
                  _fillOwnership();
              }
        });
        //if(!data || data.origin!='recordAction'){
        //}
    }

    //
    // fill selector with scope options - all (currentRecordset), selected (currentRecordsetSelection), by record type 
    //
    function _fillSelectRecordScope(){

        selectRecordScope.empty();


        if(!window.hWin.HAPI4.currentRecordset){
            //debug
            window.hWin.HAPI4.currentRecordset = new hRecordSet({count: "1",offset: 0,reccount: 1,records: [1069], rectypes:[25]});
            //return;
        }

        var opt, selScope = selectRecordScope.get(0);

        opt = new Option("please select the records to be affected …", "");
        selScope.appendChild(opt);
        
        var is_initscope_empty = window.hWin.HEURIST4.util.isempty(init_scope_type);
        var inititally_selected = '';
        
        if(init_scope_type=='all'){
            opt = new Option("All records", "All");
            selScope.appendChild(opt);
            inititally_selected = 'All';
        }else if(init_scope_type>0 && window.hWin.HEURIST4.rectypes.pluralNames[init_scope_type]){
            opt = new Option(window.hWin.HEURIST4.rectypes.pluralNames[init_scope_type], init_scope_type);
            selScope.appendChild(opt);
            inititally_selected = init_scope_type;
        }else{
            
            //selected count option
            if((is_initscope_empty || init_scope_type=='selected') &&
               (window.hWin.HAPI4.currentRecordsetSelection &&  window.hWin.HAPI4.currentRecordsetSelection.length > 0)) {
                opt = new Option("Selected results set (count=" + window.hWin.HAPI4.currentRecordsetSelection.length+")", "Selected");
                selScope.appendChild(opt);
                inititally_selected = 'Selected';
            }
            if(is_initscope_empty || init_scope_type=='current'){
                //add result count option default
                opt = new Option("Current results set (count="+ window.hWin.HAPI4.currentRecordset.length()+")", "Current");
                selScope.appendChild(opt);
                inititally_selected = 'Current';
            }
            if(is_initscope_empty){
                //find all types for result and add option for each with counts.
                var rectype_Ids = window.hWin.HAPI4.currentRecordset.getRectypes();

                for (var rty in rectype_Ids){
                    if(rty>=0){
                        rty = rectype_Ids[rty];
                        opt = new Option('only: '+window.hWin.HEURIST4.rectypes.pluralNames[rty], rty);
                        selScope.appendChild(opt);
                    }
                }
            }
        }
        //$(selScope)
        selectRecordScope.val(inititally_selected);
        
        _onRecordScopeChange();
        
        if(action_type=='rectype_change'){
            $('#div_sel_rectype').show();
            _fillSelectRecordTypes();
        }
    }

    //
    // scope selector listener
    //
    function _onRecordScopeChange() {
        
        var isdisabled = (selectRecordScope.val()=='');
        //window.hWin.HEURIST4.util.setDisabled($('#btn-ok'), isdisabled);
        var ele = $('#btn-ok');
        ele.off('click');
        if(isdisabled){
            ele.addClass('ui-state-disabled');
        }else{
            ele.removeClass('ui-state-disabled');
            ele.click(_startAction);
        }

        switch(action_type) {
            case 'add_detail':
            case 'replace_detail':
            case 'delete_detail':
            case 'extract_pdf':
                $('#div_sel_fieldtype').show();
                _fillSelectFieldTypes();
                break;
            default:
                $('#div_sel_fieldtype').hide();
/*                
            case 'rectype_change':
                $('#div_sel_rectype').show();
                _fillSelectRecordTypes();
                break;
*/                
        }

    }

    //
    // record type selector for change record type action
    // 
    function _fillSelectRecordTypes() {
        var rtSelect = $('#sel_recordtype');
        rtSelect.empty();
        return window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
    }
    //
    // fill all field type selectors
    //
    function _fillSelectFieldTypes() {

        var fieldSelect = $('#sel_fieldtype').get(0);
        if(init_scope_type>0){
            window.hWin.HEURIST4.ui.createSelector(fieldSelect, {key:init_scope_type, title:window.hWin.HEURIST4.detailtypes.names[init_scope_type]});
        }else{

            var scope_type = selectRecordScope.val();
        
            var rtyIDs = [], dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            var rtys = {};
            var i,j,recID,rty,rtyName,dty,dtyName,fieldName,opt;

            //get record types
            if(scope_type=="All"){
                rtyIDs = null; //show all details
            }else if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else if(scope_type=="Selected"){
                rtyIDs = [];

                //loop all selected records
                for(i in window.hWin.HAPI4.currentRecordsetSelection){

                    var rty_total_count = window.hWin.HAPI4.currentRecordset.getRectypes().length;
                    var recID = window.hWin.HAPI4.currentRecordsetSelection[i];
                    var record  = window.hWin.HAPI4.currentRecordset.getById(recID) ;
                    rty = window.hWin.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');

                    if (!rtys[rty]){
                        rtys[rty] = 1;
                        rtyIDs.push(rty);
                        if(rtyIDs.length==rty_total_count) break;
                    }
                }

                allSelectedRectypes = rtyIDs;

            }else{
                rtyIDs = [scope_type];
            }

            var allowed = Object.keys(window.hWin.HEURIST4.detailtypes.lookups);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
            allowed.splice(allowed.indexOf("geo"),1);
            allowed.splice(allowed.indexOf("file"),1);
            
            var initially_selected = 0;
            
            
            if(action_type=='extract_pdf'){
                allowed = ['blocktext'];    
                initially_selected = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTRACTED_TEXT'];
            }

            window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect, rtyIDs, allowed, null, {selected_value:initially_selected});
        }
        
        fieldSelect.onchange = _createInputElements;
        _createInputElements();
    }
    
    //
    // crete editing_input element for selected field type
    //
    function _createInputElements(){

        var $fieldset = $('#div_widget>fieldset');
        $fieldset.empty();

        if(action_type=='add_detail'){
            _createInputElement('fld-1', window.hWin.HR('Value to be added'));
        }else if(action_type=='replace_detail'){                              
            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<span></span><div class="header" style="padding-left: 16px;">'
                +'<label for="cb_replace_all">Replace all values</label></div>'
                +'<input id="cb_replace_all" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 4px 0">'
                +'</div>').change(function(){
                                                            
                    if($(event.target).is(':checked')){
                        $('#fld-1').hide();    
                    }else{
                        $('#fld-1').show();    
                    }
                    
                }).appendTo($fieldset);
            
            _createInputElement('fld-1', window.hWin.HR('Value to find'));
            _createInputElement('fld-2', window.hWin.HR('Replace with'));
        }else if(action_type=='delete_detail'){

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header" style="padding-left: 16px;">'
                +'<label for="cb_remove_all">Remove all occurrences</label></div>'
                +'<input id="cb_remove_all" type="checkbox" class="text ui-widget-content ui-corner-all">'
                +'</div>').appendTo($fieldset);

            _createInputElement('fld-1', window.hWin.HR('Remove value matching'));

            $('#cb_remove_all').change(function(){ $(this).is(':checked')?$('#fld-1').hide():$('#fld-1').show();  });
            $('.editint-inout-repeat-button').hide();
            
        }else if(action_type=='merge_delete_detail'){ //@todo
            _createInputElement('fld-1', window.hWin.HR('Value to remove'), init_field_value);
            _createInputElement('fld-2', window.hWin.HR('Or repalce it with'));
        }

    }

    //
    // 
    //
    function _createInputElement(input_id, input_label, init_value){

        var $fieldset = $('#div_widget>fieldset');

        var dtID = $('#sel_fieldtype').val();//

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;

        var dtidx = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'];

        var scope_type = selectRecordScope.val();
        if(Number(scope_type)>0){
            rectypeID = Number(scope_type)
        }else{
            var i, rtyIDs
            if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else{
                rtyIDs = allSelectedRectypes;
            }
            for (i in rtyIDs){
                if(window.hWin.HEURIST4.rectypes.typedefs[rtyIDs[i]].dtFields[dtID]){
                    rectypeID = rtyIDs[i];
                    break;
                }
            }
        }

        //window.hWin.HEURIST4.util.cloneObj(
        var dtFields = window.hWin.HEURIST4.util.cloneJSON(window.hWin.HEURIST4.rectypes.typedefs[rectypeID].dtFields[dtID]);
        var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;

        dtFields[fi['rst_DisplayName']] = input_label;
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_MaxValues']] = 1;
        dtFields[fi['rst_DisplayWidth']] = 50; //@todo set 50 for freetext and resource
        //dtFields[fi['rst_DisplayWidth']] = 50;
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';

        var ed_options = {
            recID: -1,
            dtID: dtID,
            //rectypeID: rectypeID,
            rectypes: window.hWin.HEURIST4.rectypes,
            values: init_value,
            readonly: false,

            showclear_button: false,
            //input_width: '350px',
            //detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields

        };

        $("<div>").attr('id',input_id).editing_input(ed_options).appendTo($fieldset);
    }

    //
    //
    //
    function getFieldValue(input_id) {
        var sel = $('#'+input_id).editing_input('getValues');
        if(sel && sel.length>0){
            return sel[0];
        }else{
            return null;
        }
    }

    // 
    // 
    //
    function _startAction(){
        
        if(window.hWin.HEURIST4.util.isempty(selectRecordScope.val())){
            alert('Select records scope to be affected');
            return;
        }

        if ($('#div_result').is(':visible')){
            $('#div_result').hide();
            $('#div_parameters').show();
            $('#btn-ok').button('option','label',window.hWin.HR('Go'));
            //to reseet all selectors 
            selectRecordScope.val('').change();
            return;
        }

        var request = { tag: $('#cb_add_tags').is(':checked')?1:0 };

        if(action_type!='rectype_change'){

            var dtyID = $('#sel_fieldtype').val();
            if(window.hWin.HEURIST4.util.isempty(dtyID) && action_type!='extract_pdf') {
                alert('Field is not defined');
                return;
            }

            request['dtyID'] = dtyID;

            if(action_type=='add_detail'){
                request['a'] = 'add';
                request['val'] = getFieldValue('fld-1');
                if(window.hWin.HEURIST4.util.isempty(request['val'])){
                    alert('Define value to add');
                    return;
                }

            }else if(action_type=='replace_detail'){

                request['a'] = 'replace';

                if(!$('#cb_replace_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        alert('Define value to search');
                        return;
                    }
                }
                request['rVal'] = getFieldValue('fld-2');
                if(window.hWin.HEURIST4.util.isempty(request['rVal'])){
                    alert('Define value to replace');
                    return;
                }

            }else if(action_type=='delete_detail'){

                request['a'] = 'delete';
                if(!$('#cb_remove_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        alert('Define value to delete');
                        return;
                    }
                }
            }else if(action_type=='extract_pdf'){
                
                request['a'] = action_type;
            }

        }

        var scope_type = selectRecordScope.val();

        if(scope_type=="Selected"){
            scope = window.hWin.HAPI4.currentRecordsetSelection;
        }else{
            scope = window.hWin.HAPI4.currentRecordset.getIds();
            if(scope_type!="Current"){
                request['rtyID'] = scope_type;
            }
        }
        request['recIDs'] = scope.join(',');

        if(action_type=='rectype_change'){
            
            var rtyID = $('#sel_recordtype').val();
            if(!(rtyID>0)){
                alert('Select new record type');
                return;
            }
            
            if(request['rtyID']==rtyID){
                alert('Selected and new record types are the same');
                return;
            }
            
            request['a'] = 'rectype_change';
            request['rtyID_new'] = rtyID;
          
            window.hWin.HEURIST4.msg.showMsgDlg(
                'You are about to convert '
                + (request['rtyID']>0 ?('"'+window.hWin.HEURIST4.rectypes.names[request['rtyID']]+'"'):request['recIDs'].length)
                +' records from their original record (entity) type into "'
                + window.hWin.HEURIST4.rectypes.names[rtyID] 
                + '" records.  This can result in invalid data for these records.<br><br>Are you sure?',
                function(){_startAction_continue(request);},
                 {title:'Warning',yes:'Proceed',no:'Cancel'});
            
        }else{
            _startAction_continue(request)
        }
    }
        
    /*
    * recIDs - list of records IDS to be processed
    * rtyID - optional filter by record type
    * dtyID  - detail field to be added
    * for add: val, geo or ulfID
    * for replace: sVal - search value, rVal - replace value
    * for delete:  sVal - search value
    * for rectype change rtyID_new - new record type
    * tag 0|1  - add system tag to mark processed records
    */
     function _startAction_continue(request)
     {   

        // show hourglass/wait icon
        $('body > div:not(.loading)').hide();
        $('.loading').show();
        
        //request['DBGSESSID'] = '425944380594800002;d=1,p=0,c=07';
        if(request['a']=='extract_pdf'){
            session_id = Math.round((new Date()).getTime()/1000);
            request['session'] = session_id;
            _showProgress( session_id );
        }

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            
            _hideProgress();
            
            $('body > div:not(.loading)').show();
            $('body > #ui-datepicker-div').hide();
            $('.loading').hide();

            var  success = (response.status == window.hWin.ResponseStatus.OK);
            if(success){
                $('#div_parameters').hide();
                
                /*
                $('select').each(function(idx, item){
                   if($(item).hSelect('instance')!==undefined) $(item).hSelect('destroy'); //destroy all hSelects 
                });
                */
                $('.ui-selectmenu-menu').remove();

                $('#div_result').empty();

                response = response['data'];
 
                /*
                *       passed - count of given rec ids
                *       noaccess - no rights to edit
                *       processed - success
                _tag   _tag_error
                *       undefined - value not found (no assosiated pdf files)
                *       limited - skipped
                *       errors     - sql error on search or updata
                errors_list
                */
                var sResult = '';
                for(key in response){
                    if(key && key.indexOf('_')<0 && response[key]>0){
                        //main report entry
                        var lbl_key = 'record_action_'+key;
                        var lbl = window.hWin.HR(lbl_key);
                        if(lbl==lbl_key){
                            //not found - try to find specified for particular action
                            lbl = window.hWin.HR(lbl_key+'_'+action_type);
                        }
                        var tag_link = '';
                        if(response[key+'_tag']){
                            tag_link = '<span><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=tag:"'+response[key+'_tag']+'"')+
                            '" target="_blank">view</a></span>';
                            
                        }else if(response[key+'_tag_error']){
                            tag_link = '<span>'+response[key+'_tag_error']['message']+'</span>';
                            
                        }else if(key=="processed"){
                            
                            tag_link = '<span><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=sortby:-m after:"5 minutes ago"')+
                            '" target="_blank">view recent changes</a></span>';
                        }else if(key=="parseexception"){
                            
                            //tag_link = '<span><a href="#" onclick="">'+'view details</a></span>';
                        }
                        
                        sResult = sResult + '<div style="padding:4px"><span>'+lbl+'</span><span>&nbsp;&nbsp;'
                        +response[key]+'</span>'
                        +tag_link+'</div>';
                        
                        function __report_details(key){
                            var recids = Object.keys(response[key]);
                            var sResult = '';
                            if(recids && recids.length>0){
                                sResult += '<div style="max-height:230px;overflow-y:auto;background-color:#ffcccc">';
                                for(var recid in response[key]){
                                    sResult += (recid+': '+response[key][recid] + '<br><br>');   
                                }
                                sResult += '</div>';   
                            }
                            return sResult;
                        }
                        
                        if(key=='errors' && response['errors_list']){
                            sResult += __report_details('errors_list');
                        }else if(key=='parseexception' && response['parseexception_list']){
                            sResult += __report_details('parseexception_list');
                        }else if(key=='parseempty' && response['parseempty_list']){
                            sResult += __report_details('parseempty_list');
                        }
                    }
                }
                
//console.log(response);               

                $('#div_result').html(sResult);
                $('#div_result').css({padding:'10px'}).show();
                $('#btn-ok').button('option','label',window.hWin.HR('New Action'));
                $('#btn-cancel').button('option','label',window.hWin.HR('Close'));

            }else{
                $('#div_result').hide();
                window.hWin.HEURIST4.msg.showMsgErr(response.message);
            }
        });

    }

    //
    // @todo move to utils_msg
    //
    function _showProgress( session_id ){

        var progressCounter = 0;        
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        
        $('body').css('cursor','progress');
        var pbar_div = $('#progressbar_div').show();
        var pbar = $('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        
        var btn = $('#progress_stop');
        if(btn.button("instance")==undefined){
            btn.button().click(function() {
                
                _hideProgress();
                
                var request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        //console.log('TERMINATION', response);                   
                    }
                });
            } );
        }
        
        progressInterval = setInterval(function(){ 
            
            var request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){

                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    _hideProgress();
                }else{
                    
                    var resp = response?response.split(','):[0,0];
                    
                    if(resp && resp[0]>=0){
                        if(progressCounter>0){
                            if(resp[1]>0){
                                var val = resp[0]*100/resp[1];
                                pbar.progressbar( "value", val );
                                progressLabel.text(resp[0]+' of '+resp[1]);
                            }else{
                                progressLabel.text('wait...');
                                //progressLabel.text('');
                            }
                        }else{
                            pbar.progressbar( "value", 0 );
                            progressLabel.text('preparing...');
                        }
                    }else if(progressCounter>3){
                        _hideProgress();
                    }
                    
                    progressCounter++;
                    
                }
            },'text');
          
        
        }, 1000);                
        
    }
    
    function _hideProgress(){
        
        
        $('body').css('cursor','auto');
        
        if(progressInterval!=null){
            
            clearInterval(progressInterval);
            progressInterval = null;
        }
        $('#progressbar_div').hide();
        
    }    
           
    

    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    
    _init();
    return that;  //returns object
}
