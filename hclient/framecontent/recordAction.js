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
* @copyright   (C) 2005-2020 University of Sydney
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
        }else if(init_scope_type>0 && $Db.rty(init_scope_type,'rty_Plural')){
            opt = new Option($Db.rty(init_scope_type,'rty_Plural'), init_scope_type);
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
                        opt = new Option('only: '+$Db.rty(rty,'rty_Plural'), rty);
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
            window.hWin.HEURIST4.ui.createSelector(fieldSelect, 
                {key:init_scope_type, title: $Db.dty(init_scope_type, 'dty_Name')});
                
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

            var allowed = Object.keys($Db.baseFieldType);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
            //allowed.splice(allowed.indexOf("geo"),1);
            allowed.splice(allowed.indexOf("file"),1);
            
            if(action_type=='extract_pdf'){
                allowed = ['blocktext'];    
            }

            window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect, rtyIDs, allowed, null);
        }
        
        fieldSelect.onchange = _createInputElements;
        _createInputElements();
    }
    
    //
    // create editing_input element for selected field type
    //
    function _createInputElements(){

        var $fieldset = $('#div_widget>fieldset');
        $fieldset.empty();

        if(action_type=='add_detail'){
            _createInputElement('fld-1', window.hWin.HR('Value to be added'));
        }else if(action_type=='replace_detail'){                              
            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label for="cb_replace_all">Replace all values</label></div>'
                +'<input id="cb_replace_all" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').change(function(){


                    if ($(this).is(':checked')){
                        $('#cb_whole_value').parent().hide();   
                        $('#fld-1').hide();
                    }else{
                        $('#cb_whole_value').parent().show();
                        $('#fld-1').show();    
                    }
                                                            
                }).appendTo($fieldset);
                
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label for="cb_whole_value">Replace substring</label></div>'
                +'<input id="cb_whole_value" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
                
            
            _createInputElement('fld-1', window.hWin.HR('Value to find'));
            _createInputElement('fld-2', window.hWin.HR('Replace with'));
            
        }else if(action_type=='delete_detail'){

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label for="cb_remove_all">Remove all occurrences</label></div>'
                +'<input id="cb_remove_all" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label for="cb_whole_value">Remove substring</label></div>'
                +'<input id="cb_whole_value" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            
                
            _createInputElement('fld-1', window.hWin.HR('Remove value matching'));

            
            $('#cb_remove_all').change(function(){ 
                    if ($(this).is(':checked')){
                        $('#cb_whole_value').parent().hide();   
                        $('#fld-1').hide();
                    }else{
                        $('#cb_whole_value').parent().show();
                        $('#fld-1').show();    
                    }
            });
            //$('.editint-inout-repeat-button').hide();
            
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
            //find first rectype with specified dtID
            for (i in rtyIDs){
                if($Db.rst(rtyIDs[i], dtID)){
                    rectypeID = rtyIDs[i];
                    break;
                }
            }
        }
   
//console.log( $Db.dty(dtID, 'dty_Type') );        
        var field_type = $Db.dty(dtID, 'dty_Type');
        if(field_type=='geo'){
            
            $('#cb_remove_all').prop('checked',true).addClass('ui-state-disabled');;
            $('#cb_replace_all').prop('checked',true).addClass('ui-state-disabled');;
            $('#fld-1').hide();
           //window.hWin.HEURIST4.util.setDisabled($('#cb_replace_all'), true);
           if(action_type=='delete_detail') return;
        }else{
            $('#cb_remove_all').removeClass('ui-state-disabled');;
            $('#cb_replace_all').removeClass('ui-state-disabled');;
            //window.hWin.HEURIST4.util.setDisabled($('#cb_replace_all'), false);
        }
        
        if(field_type=='freetext' || field_type=='blocktext'){
            $('#cb_whole_value').parent().show();
        }else{
            $('#cb_whole_value').parent().hide();
        }
        

        //window.hWin.HEURIST4.util.cloneObj(
        var dtFields = $Db.rst(rectypeID, dtID);

        dtFields['rst_DisplayName'] = input_label;
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['rst_DisplayWidth'] = 50; 
        dtFields['dty_Type'] = $Db.dty(dtID, 'dty_Type');
        dtFields['rst_PtrFilteredIDs'] = $Db.dty(dtID, 'dty_PtrTargetRectypeIDs')
        dtFields['rst_FilteredJsonTermIDTree'] = $Db.dty(dtID, 'dty_JsonTermIDTree');
        dtFields['dtID'] = dtID;
        
        
        //@todo set 50 for freetext and resource
        //dtFields[fi['rst_DisplayWidth']] = 50;
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';

        var ed_options = {
            recID: -1,
            dtID: dtID,
            values: init_value,
            readonly: false,

            showclear_button: false,
            dtFields:dtFields

        };

        var ele = $("<div>").attr('id',input_id).appendTo($fieldset);
        ele.editing_input(ed_options);

        // special case for selects, menuWidget needs to be moved down closer to the widget element
        if(ele.find('select').length > 0){

            var id = ele.find('select').attr('id');
            var widget_ele, menu_parent;

			// check that the select is supposed to be a hSelect/selectmenu
            if(ele.find('select').hSelect('instance') != undefined){ 

                widget_ele = selObj.hSelect('widget');
                menu_parent = selObj.hSelect('menuWidget').parent();
            }else if($('#'+id+'-button').length > 0){ // .hSelect('instance') and .selectmenu both return undefined, despite the select being a hSelect instance

				if(parent.document && $('#'+id+'-menu', parent.document).length > 0){ // check if current menuWidget can be accessed

					widget_ele = $('#'+id+'-button');
					menu_parent = $('#'+id+'-menu', parent.document).parent();
				}else{

					$('#'+id+'-button').remove();
					
					var selObj = window.hWin.HEURIST4.ui.initHSelect(ele.find('select')[0], false);

					widget_ele = selObj.hSelect('widget');
					menu_parent = selObj.hSelect('menuWidget').parent();
				}
            }

            if(widget_ele && menu_parent){
				widget_ele.on("click", function(e){
                    menu_parent.css('top', widget_ele.offset().top + 54);
                });
            }
        }
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
                    
                    if($('#cb_whole_value').is(':checked')){
                        request['subs'] = 1;
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
                    if($('#cb_whole_value').is(':checked')){
                        request['subs'] = 1;
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
                + (request['rtyID']>0 ?('"'+$Db.rty(request['rtyID'],'rty_Name')+'"'):scope.length)
                +' records from their original record (entity) type into "'
                + $Db.rty(rtyID, 'rty_Name') 
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

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){

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
                            '&nometadatadisplay=true" target="_blank">view</a></span>';
                            
                        }else if(response[key+'_tag_error']){
                            tag_link = '<span>'+response[key+'_tag_error']['message']+'</span>';
                            
                        }else if(key=="processed"){
                            
                            tag_link = '<span><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=sortby:-m after:"5 minutes ago"')+
                            '&nometadatadisplay=true" target="_blank">view recent changes</a></span>';
                        }
                        
                        sResult = sResult + '<div style="padding:4px"><span>'+lbl+'</span><span>&nbsp;&nbsp;'
                        +response[key]+'</span>'
                        +tag_link+'</div>';
                        
                        if(key=='errors' && response['errors_list']){
                            var recids = Object.keys(response['errors_list']);
                            if(recids && recids.length>0){
                                sResult += '<div style="max-height:300;overflow-y:auto;background-color:#ffcccc">';
                                for(key in response['errors_list']){
                                    sResult += (key+': '+response['errors_list'][key] + '<br>');   
                                }
                                sResult += '</div>';   
                            }
                        }
                    }
                }

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


    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    
    _init();
    return that;  //returns object
}
