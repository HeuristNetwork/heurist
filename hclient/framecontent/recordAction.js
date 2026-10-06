/**
* recordAction.js - record batch actions dialogue 
* 
* Handles batch actions on records: change record type, add,update or delete details
* 
* @todo - converts to widget based on HBaseView
* 
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       5.0
*/

/**
 * Constructor for the hRecordAction object.
 * This object manages the UI and logic for performing batch actions on records,
 * such as adding, replacing, or deleting details, changing record types,
 * and other specialized actions like case conversion or file operations.
 *
 * @param {string} _action_type - The type of action to perform (e.g., 'add_detail', 'replace_detail',
 *                                'delete_detail', 'rectype_change', 'extract_pdf', 'url_to_file',
 *                                'local_to_repository', 'case_conversion', 'nl2br', 'translation', 
 *                                'reset_thumbs', 'increment' ,'iiif_thumbs').
 *                                This determines the UI and server-side handling.
 * @param {string|number} [_scope_type] - The initial scope of records to act upon.
 *                                     Can be a string like 'All', 'Current', 'Selected', 'Collected',
 *                                     or a numeric record type ID (rtyID) to target records of a specific type.
 * @param {number} [_field_type] - The initial field type ID (dtyID) to be modified, if applicable to the action.
 * @param {*} [_field_value] - An initial value for the field, if applicable. (Currently seems unused in init).
 * @returns {object} An instance of hRecordAction with public methods.
 */
function hRecordAction(_action_type, _scope_type, _field_type, _field_value) {
    const _className = "RecordAction",
    _version   = "0.4";

    let selectRecordScope, allSelectedRectypes,
        iiifAnnotationRtyID = 0,
        iiifThumbsAvailable = true,
        progressSessionId = null,
        progressWidgetActive = false,
        progressOwnerAction = null,
        progressWidgetContainer = null;

    const progressStorageKey = 'heurist-recordAction-progress-' + window.hWin.HAPI4.database;
    const progressThresholds = {
        extract_pdf: 1,
        url_to_file: 1,
        local_to_repository: 1,
        reset_thumbs: 10,
        iiif_thumbs: 1,
        translation: 10,
        rectype_change: 50,
        add_detail: 50,
        replace_detail: 50,
        delete_detail: 50,
        case_conversion: 50,
        nl2br: 50,
        increment: 50
    };

    let action_type = _action_type,
        init_scope_type = _scope_type,
        init_field_type = _field_type,
        init_field_value = _field_value,
        //repositories = ['Nakala'], // list of repositories - RETRIEVED FROM SERVER SIDE
        _allow_empty_replace = false,
        _default_exceptions = [], // array of default exceptions for case conversions
        _check_field_repeat = false; // check if the field to be used is repeatable


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
        
        let btn_start_action = $('#btn-ok').button({label:window.hWin.HR('Go')});
        
        selectRecordScope = $('#sel_record_scope')
        .on('change',
            function(e){
                _onRecordScopeChange();
            }
        );
        btn_start_action.addClass('ui-state-disabled'); //.on('click',_startAction);        
        
        _fillSelectRecordScope();
        
        $('#btn-cancel').button({label:window.hWin.HR('Cancel')}).on('click', function(){window.close();});

        $(window).on('beforeunload.recordAction', function(){
            _stopProgressWidget();
        });

        window.setTimeout(_restoreProgressSession, 0);
    }

    //
    // Retrieve license types from Nakala
    //
    function _popuplateNakalaLicense(){

        let $sel_license = $('#sel_license');

        if($sel_license.attr('data-init') == 'Nakala' && $sel_license.find('option').length > 0){ // already has values
            return;
        }

        let request = {
            serviceType: 'nakala',
            metadata: 'licenses'
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), null, 'Retrieving available licenses...');

        window.hWin.HAPI4.RecordMgr.lookupService(request, (data) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            data = window.hWin.HEURIST4.util.isJSON(data);

            if(data.status && data.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(data);
                window.close();
                return;
            }

            if(data.length > 0){
                $.each(data, (idx, license) => {
                    window.hWin.HEURIST4.ui.addoption($sel_license[0], license, license);
                });

                $sel_license.attr('data-init', 'Nakala');
            }else{
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'An unknown error has occurred while attempting to retrieve the licenses for Nakala records.',
                    error_title: 'Unable to retrieve Nakala licenses',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                window.close();
            }
        });
    }

    //
    // fill selector with scope options - all (currentRecordset), selected (currentRecordsetSelection), by record type 
    //
    function _fillSelectRecordScope(){

        selectRecordScope.empty();

        if(!window.hWin.HAPI4.currentRecordset){
            window.hWin.HAPI4.currentRecordset = new HRecordSet({count:"0",offset: 0,reccount: 1,records:[], rectypes:[]});
        }

        let opt, selScope = selectRecordScope.get(0);

        opt = new Option("please select the records to be affected …", "");
        selScope.appendChild(opt);

        let is_initscope_empty = window.hWin.HEURIST4.util.isempty(init_scope_type);
        let inititally_selected = '';
        
        if(init_scope_type=='all'){
            opt = new Option("All records", "All");
            selScope.appendChild(opt);
            inititally_selected = 'All';
        }else if(init_scope_type>0 && $Db.rty(init_scope_type,'rty_Plural')){
            opt = new Option($Db.rty(init_scope_type,'rty_Plural'), init_scope_type);
            selScope.appendChild(opt);
            inititally_selected = init_scope_type;
        }else{

            if(is_initscope_empty || init_scope_type=='current'){
                //add result count option default
                opt = new Option("Current results set (count="+ window.hWin.HAPI4.currentRecordset.length()+")", "Current");
                selScope.appendChild(opt);
                inititally_selected = 'Current';
            }
            //collected count option
            if((action_type=='rectype_change' || is_initscope_empty || init_scope_type=='collected') &&
                window.hWin.HAPI4?.currentRecordsetCollected?.length > 0)
            {
                opt = new Option("Collected results set (count=" + window.hWin.HAPI4.currentRecordsetCollected.length+")", "Collected");
                selScope.appendChild(opt);
                inititally_selected = 'Collected';
            }
            //selected count option
            if((action_type=='rectype_change' || is_initscope_empty || init_scope_type=='selected') &&
                window.hWin.HAPI4?.currentRecordsetSelection?.length > 0)
            {
                opt = new Option("Selected results set (count=" + window.hWin.HAPI4.currentRecordsetSelection.length+")", "Selected");
                selScope.appendChild(opt);
                inititally_selected = 'Selected';
            }

            if(is_initscope_empty){
                //find all types for result and add option for each with counts.
                let rectype_Ids = window.hWin.HAPI4.currentRecordset.getRectypes();
                rectype_Ids.forEach(rty => {
                        let opt = new Option('only: '+$Db.rty(rty,'rty_Plural'), rty);
                        selScope.appendChild(opt);
                });
            }
        }

        //$(selScope)
        selectRecordScope.val(inititally_selected);
        
        if(action_type=='rectype_change'){
            $('#div_sel_rectype').show();
            _fillSelectRecordTypes();
        }else if(action_type=='reset_thumbs' || action_type=='iiif_thumbs'  || action_type=='increment'){
            $('#cb_add_tags').parent().hide();
        }
        
        if(action_type=='iiif_thumbs'){
            $('#sel_record_scope').parent().hide();

            iiifAnnotationRtyID = Number($Db.getLocalID('rty', '2-109'));
            let currentRectypes = window.hWin.HAPI4.currentRecordset.getRectypes();
            iiifThumbsAvailable = iiifAnnotationRtyID>0 && currentRectypes.includes(iiifAnnotationRtyID);

            if(!iiifThumbsAvailable){
                
                $('#div_header').html($('#div_header').html()+'<br>'+
                '<p style="color:red">The current result set does not contain IIIF Annotation records.</p>');
            }

            let $fieldset = $('#div_widget>fieldset');
            $fieldset.empty();
            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'

                + '<label><input id="cb_iiif_thumbs_missedonly" type="checkbox" name="cb_iiif_thumbs_missedonly" checked class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Create thumbnails for missed only</label><br>'
                
            + '</div>').appendTo($fieldset);
            
        }
        
        _onRecordScopeChange();
    }

    //
    // scope selector listener
    //
    function _onRecordScopeChange() {
        
        let isdisabled = (selectRecordScope.val()==''
            || (action_type=='iiif_thumbs' && !iiifThumbsAvailable));


        let ele = $('#btn-ok');
        ele.off('click');
        if(isdisabled){
            ele.addClass('ui-state-disabled');
        }else{
            ele.removeClass('ui-state-disabled');
            ele.on('click',_startAction);
        }
        switch(action_type) {
            case 'add_detail':
            case 'replace_detail':
            case 'delete_detail':
            case 'extract_pdf':
            case 'url_to_file':
            case 'local_to_repository':
            case 'case_conversion':
            case 'nl2br':
            case 'translation':
            case 'increment':
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
        let rtSelect = $('#sel_recordtype');
        rtSelect.empty();
        return window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
    }
    //
    // fill all field type selectors
    //
    function _fillSelectFieldTypes() {

        let fieldSelect = $('#sel_fieldtype').get(0);
        if(init_scope_type>0){
            window.hWin.HEURIST4.ui.createSelector(fieldSelect, 
                {key:init_scope_type, title: $Db.dty(init_scope_type, 'dty_Name')});
                
        }else{

            let scope_type = selectRecordScope.val();
        
            let rtyIDs = [], dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            let rtys = {};

            //get record types
            if(scope_type=="All"){
                rtyIDs = null; //show all details
            }else if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else if(scope_type=="Selected" || scope_type=="Collected"){
                rtyIDs = [];

                let rec_IDs = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;

                //loop all selected records
                for(const recID of rec_IDs){

                    let rty_total_count = window.hWin.HAPI4.currentRecordset.getRectypes().length;
                    const record  = window.hWin.HAPI4.currentRecordset.getById(recID) ;
                    let rty = window.hWin.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');

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

            let allowed = Object.keys($Db.baseFieldType);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
           
            allowed.splice(allowed.indexOf("file"),1);
            
            if(action_type=='extract_pdf' || action_type=='nl2br'){
                allowed = ['blocktext'];    
            }else if(action_type=='url_to_file' || action_type=='local_to_repository'){
                allowed = ['file'];    
            }else if(action_type=='case_conversion' || action_type=='translation'){
                allowed = ['freetext','blocktext'];
            }else if(action_type=='increment'){
                allowed = ['freetext','integer','float'];
            }

            window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect, rtyIDs, allowed, null);
        }
        
        fieldSelect.onchange = _createInputElements;
        _createInputElements();
    }
    
    //
    // create editing_input element for selected field type
    // create custom input elements specific for particular action
    //
    function _createInputElements(){

        let $fieldset = $('#div_widget>fieldset');
        $fieldset.empty();

        if(action_type=='add_detail'){
            _createInputElement('fld-1', window.hWin.HR('Value to be added'));
        }else if(action_type=='replace_detail'){                              

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_replace_all">Replace all values</label></div>'
                +'<input id="cb_replace_all" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_whole_value">Replace complete value</label></div>'
                +'<input id="cb_whole_value" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_sub_string">Replace substring</label></div>'
                +'<input id="cb_sub_string" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px" checked="checked">'
                +'</div>').appendTo($fieldset);

            $('input[name="replace_type"]').on('change', () => {

                if ($('#cb_replace_all').is(':checked')){
                    $('#cb_add_value').parent().show();
                    $('#fld-1').hide();
                }else{
                    $('#cb_add_value').parent().hide();
                    $('#fld-1').show();    
                }
            });

            $('<div style="padding: 0.2em; width: 100%; display: none;" class="input">'
                +'<div class="header" style="padding-bottom: 10px;">'
                +'<label for="cb_add_value">Insert as new value,<br><span style="font-size: smaller;">if none exist</span></label></div>'
                +'<input id="cb_add_value" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            
            _createInputElement('fld-1', window.hWin.HR('Value to find'));
            _createInputElement('fld-2', window.hWin.HR('Replace with'));
            
        }
        else if(action_type=='delete_detail'){
            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_delete_all">Remove all values</label></div>'
                +'<input id="cb_delete_all" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_sub_string">Remove search string only</label></div>'
                +'<input id="cb_sub_string" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px" checked="checked">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_whole_value">Remove complete value</label></div>'
                +'<input id="cb_whole_value" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            _createInputElement('fld-1', window.hWin.HR('Remove value matching'));

            $('input[name="delete_type"]').on('change', function(){ 
                if ($('#cb_delete_all').is(':checked')){
                    $('#fld-1').hide();
                }else{
                    $('#fld-1').show();    
                }
            });

        }else if(action_type=='url_to_file'){

                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label>URL contains substring</label></div>'
                +'<input id="url_substring" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);            
            
                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label for="cb_match_only">Match file name only</label></div>'
                +'<input id="cb_match_only" type="checkbox" checked class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'<div class="heurist-helper1 style="padding: 0.2em 0px;">Looks for existing uploaded files based solely on name, and uses these rather than fetching a new copy. This will produce unwanted results if the names are re-used eg. in different folders.'
                +'</div></div>').appendTo($fieldset);            
            
        }else if(action_type=='local_to_repository'){ //upload local file to external repository (Nakala)

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_repository">Repository</label></div>'
                + '<select id="sel_repository" style="max-width:30em"><option value="">select a repository...</option></select>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;display: none;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label>Upload Status</label></div>'
                + '<div id="nakala_status_container">'
                + '<label style="display: block; margin: 1em 0px 0.5em;"><input name="nakala_status" value="pending" type="radio" checked="checked" />Private (viewable only by the uploader, will use the API key for Heurist)</label>'
                + '<label style="display: block; margin-bottom: 1em;"><input name="nakala_status" value="published" type="radio" />Public (publishes and mints the file with Nakala)</label></div>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;display: none;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_license">License</label></div>'
                + '<select id="sel_license" style="max-width:30em" data-init="0"></select>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="cb_del_local_file">Delete local file on success </label></div>'
                + '<input id="cb_del_local_file" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">'
                + '<div class="heurist-helper1 style="padding: 0.2em 0px;">Delete locally stored file(s) after successfully uploading to repository</div>'
            + '</div>').appendTo($fieldset);

            
            if($fieldset.find('#sel_repository').length != 0){

                window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list', 'include_test': 1}, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let repositories = window.hWin.HEURIST4.util.isJSON(response.data);
                        
                        //service_id, service_label, usr_ID, usr_Name

                        let $sel_repos = $fieldset.find('#sel_repository');
                        for (let i = 0; i < repositories.length; i++) {
                            let repo = repositories[i];
                            let repo_name = repo[1];
                            let usr_name = repo[3];
                            //usr_name = window.hWin.HAPI4.SystemMgr.getUserNameLocal(repo[2]);    
                            
                            window.hWin.HEURIST4.ui.addoption($sel_repos[0], 
                                    repo[0], 
                                    repo_name+' > '+usr_name);
                        }
                        $sel_repos.on('change', () => {
                            let repo = $sel_repos.val();
                            if(repo.indexOf('nakala')===0 || repo.indexOf('nakala')===1){
                                $('#sel_license').parent().show();
                                $('#nakala_status_container').parent().show();
                                _popuplateNakalaLicense ();
                            }
                        });
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            
            }
        }else if(action_type=='merge_delete_detail'){ //@todo
            _createInputElement('fld-1', window.hWin.HR('Value to remove'), init_field_value);
            _createInputElement('fld-2', window.hWin.HR('Or repalce it with'));
        }else if(action_type=='case_conversion'){

            if($('#case_convert_op').length == 0){ // add extra field
                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                    + '<div class="header" style="padding-right: 16px;"><label>Conversion type:</label></div>'
                    + '<select id="case_convert_op" class="ui-widget-content ui-corner-all">'
                        + '<option value="1">Lowercase, capital at start of field, capitalise after fullstop followed by newline or space</option>'
                        + '<option value="2">Lowercase, capitalise start of each word</option>'
                        + '<option value="3">All lowercase</option>'
                        + '<option value="4">All capitals</option>'
                    + '</select>'
                + '</div>').insertAfter('#div_sel_fieldtype');
            }else{
                $('#case_convert_op').parent().show();
            }

            $('<h3 style="margin: 0px;">Exceptions</h3>'
            + `<div style="font-size: 12px;display: block; padding: 10px 0px;">${window.hWin.HR('case_conversion_add')}</div>`
            + '<div style="display: block; padding: 5px 0px;"> OR '
                + '<input id="uploadWidget" type="file" style="display:none;"><button id="uploadFile">Upload file</button> encoding: '
                + '<select id="except_encode" class="ui-widget-content ui-corner-all"></select>'
            + '</div>'
            + '<div style="display: inline-block;padding: 5px 20px 5px 50px;">'
                + '<div style="display: block;"><strong>Configurable</strong></div>'
                + '<textarea id="except_user" rows="25" cols="40"></textarea>'
            + '</div>'
            + '<div style="display: inline-block;padding: 5px 50px 5px 20px;">'
                + '<div style="display: block;"><strong>Pre-defined</strong> <span style="font-size: 10px">(may be temporarily edited)</span></div>'
                + '<textarea id="except_default" rows="25" cols="40"></textarea>'
            + '</div>').appendTo($fieldset);

            window.hWin.HEURIST4.ui.initHSelect($('#case_convert_op')[0], true);
            window.hWin.HEURIST4.ui.createEncodingSelect($('#except_encode'));

            let $widget_upload = $('#uploadWidget').hide();
            let $btn_upload = $('#uploadFile').button().on('click', function(e){
                $widget_upload.trigger('click'); // trigger file upload
            });
            $widget_upload.fileupload({
                url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php',
                formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                            {name:'entity', value:'temp'}, //to place file into scratch folder
                            {name:'max_file_size', value:1024*1024}], //'1024*1024'
                autoUpload: true,
                sequentialUploads:true,
                dataType: 'json',
                done: function (e, response) {
                    response = response.result;
                    if(response.status==window.hWin.ResponseStatus.OK){
                        let data = response.data;
                        $.each(data.files, function (index, file) {
                            if(file.error){
                                $('#except_user').val(file.error);
                            }else{
                                let url_get = file.deleteUrl.replace('fileUpload.php','fileGet.php')
                                    +'&encoding='+$('#except_encode').val()+'&db='+window.hWin.HAPI4.database;
                                
                                $('#except_user').load(url_get, null);
                            }
                        });
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: 'File upload error', status: response.status});
                    }
                     
                    let inpt = this;
                    $btn_upload.off('click');
                    $btn_upload.on({click: function(){
                        $(inpt).trigger('click');
                    }});                
                }
            });

            if(_default_exceptions.length > 0){
                $('#except_default').val(_default_exceptions.join('\n'));
            }

            $('#div_widget').css('padding-left', '0px');
            
        }else if(action_type=='translation'){
            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_language">'
                + window.hWin.HR('Target language')+'</label></div>'
                + '<select id="sel_language" style="max-width:30em" data-init="0"></select>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><span>Existing translations: </span></div>'
                + '<label><input id="cb_translation_asis" type="radio" name="tr_act" checked class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">as is</label>&nbsp;&nbsp;&nbsp;'
                + '<label><input id="cb_translation_replace" type="radio" name="tr_act" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Replace</label>&nbsp;&nbsp;&nbsp;'
                + '<label><input id="cb_translation_delete" type="radio" name="tr_act" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Delete</label>'
            + '</div>').appendTo($fieldset);
          
            window.hWin.HEURIST4.ui.createLanguageSelect($fieldset.find('#sel_language'), null, null, true);

            _check_field_repeat = true;
            
        }else if(action_type=='increment'){

            $('<div style="padding: 0.2em; width: 100%;" class="input">'

                + '<label><input id="cb_increment_fillgaps" type="radio" name="increment_fillgaps" checked class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Fill gaps in the sequence and then continue from the largest existing value</label><br>'
                + '<label><input type="radio" name="increment_fillgaps" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Ignore gaps and continue from the largest existing value</label>'
                + '<div style="padding: 0.2em; min-width: 600px;" class="input">'
                + '<label for="increment_prefix">Default prefix (if a text field): </label> <input id="increment_prefix" style="max-width:30em"/>'
                + '</div>'
                + '<div style="padding: 0.2em; min-width: 600px;" class="input">'
                + '<label for="increment_digits">Digits in numeric suffix (text fields only): </label> <input id="increment_digits" type="number" min="1" max="8" value="4" style="width:5em"/>'
                + '</div>'
            + '</div>').appendTo($fieldset);

        }
    }

    //
    // 
    //
    function _createInputElement(input_id, input_label, init_value){

        let $fieldset = $('#div_widget>fieldset');

        let dtID = $('#sel_fieldtype').val();//
        
        let rectypeID;

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;

        let scope_type = selectRecordScope.val();
        if(Number(scope_type)>0){
            rectypeID = Number(scope_type)
        }else{
            let i, rtyIDs
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

        if(window.hWin.HEURIST4.util.isempty(rectypeID)) return;

   
        let field_type = $Db.dty(dtID, 'dty_Type');
        if(field_type=='geo'){
            
            $('#cb_delete_all').prop('checked',true).addClass('ui-state-disabled');;
            $('#cb_replace_all').prop('checked',true).addClass('ui-state-disabled');;
            $('#fld-1').hide();
           
           if(action_type=='delete_detail') return;
        }else{
            $('#cb_delete_all').removeClass('ui-state-disabled');;
            $('#cb_replace_all').removeClass('ui-state-disabled');;
            
        }
        
        if(field_type=='freetext' || field_type=='blocktext'){
            $('#cb_sub_string').parent().show();
        }else{
            $('#cb_sub_string').parent().hide();
        }
        

        //window.hWin.HEURIST4.util.cloneObj(
        let dtFields = $Db.rst(rectypeID, dtID);

        dtFields['rst_DisplayName'] = input_label;
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['rst_DisplayWidth'] = 50; 
        dtFields['dty_Type'] = $Db.dty(dtID, 'dty_Type');
        dtFields['rst_PtrFilteredIDs'] = $Db.dty(dtID, 'dty_PtrTargetRectypeIDs')
        dtFields['rst_FilteredJsonTermIDTree'] = $Db.dty(dtID, 'dty_JsonTermIDTree');
        dtFields['dtID'] = dtID;
        
        if($Db.dty(dtID, 'dty_Type') == 'blocktext'){
            dtFields['rst_DisplayWidth'] = 80;
        }

        // Allow DB Admins to modify readonly fields
        let update_maymodify = dtFields['rst_MayModify'] == 'locked' && window.hWin.HAPI4.is_admin();
        dtFields['rst_MayModify'] = (update_maymodify) ? 'open' : dtFields['rst_MayModify'];
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';

        let ed_options = {
            recID: -1,
            dtID: dtID,
            values: init_value,
            readonly: false,

            showclear_button: false,
            dtFields:dtFields,

            force_displayheight: (field_type=='blocktext') ? 10 : null
        };

        let ele = $("<div>").attr('id',input_id).appendTo($fieldset);
        ele.editing_input(ed_options);

        // special case for selects, menuWidget needs to be moved down closer to the widget element
        if(ele.find('select').length > 0){

            let id = ele.find('select').attr('id');
            let widget_ele, menu_parent;

            // check that the select is supposed to be a hSelect/selectmenu
            if(ele.find('select').hSelect('instance') != undefined){ 

                const selObj = ele.find('select');
                widget_ele = selObj.hSelect('widget');
                menu_parent = selObj.hSelect('menuWidget').parent();
            }else if($('#'+id+'-button').length > 0){ // widget exists in current document

                if(parent.document && $('#'+id+'-menu', parent.document).length > 0){ // check if current menuWidget can be accessed

                    widget_ele = $('#'+id+'-button');
                    menu_parent = $('#'+id+'-menu', parent.document).parent();
                }else{

                    $('#'+id+'-button').remove();
                    
                    const selObj = window.hWin.HEURIST4.ui.initHSelect(ele.find('select')[0], false);

                    widget_ele = selObj.hSelect('widget');
                    menu_parent = selObj.hSelect('menuWidget').parent();
                }
            }

            if(widget_ele && menu_parent){
                widget_ele.on("click", function(e){
                    menu_parent.css('top', widget_ele.offset().top + 54);
                });

                widget_ele.css({'font-size': '1em'}); //'width': 'auto', 'max-width': '30em'
            }
        }
    }

    //
    //
    //
    function getFieldValue(input_id) {
        let sel = $('#'+input_id).editing_input('getValues');
        if(sel && sel.length>0){
            return sel[0];
        }else{
            return null;
        }
    }

    function _getStoredProgress(){
        try {
            const value = window.hWin.localStorage.getItem(progressStorageKey);
            return value ? JSON.parse(value) : null;
        } catch (e) {
            return null;
        }
    }

    function _storeProgress(data){
        try {
            window.hWin.localStorage.setItem(progressStorageKey, JSON.stringify(data));
        } catch (e) {
            // Progress still works for the current dialog even when storage is unavailable.
        }
    }

    function _clearStoredProgress(){
        try {
            window.hWin.localStorage.removeItem(progressStorageKey);
        } catch (e) {
        }
    }

    function _stopProgressWidget(){
        if(progressWidgetActive){
            window.hWin.HEURIST4.msg.hideProgress(progressWidgetContainer);
            progressWidgetActive = false;
        }
        if(progressWidgetContainer){
            progressWidgetContainer.hide();
        }
    }

    function _recordCount(request){
        if(!request || !request.recIDs){
            return 0;
        }
        if(request.recIDs === 'ALL'){
            return Number.MAX_SAFE_INTEGER;
        }
        return String(request.recIDs).split(',').filter(Boolean).length;
    }

    function _needsProgress(request){
        const threshold = progressThresholds[action_type] || 50;
        return _recordCount(request) > threshold;
    }

    function _startProgressWidget(sessionId, ownerAction){
        _stopProgressWidget();
        progressSessionId = sessionId;
        progressOwnerAction = ownerAction || action_type;
        $('#div_parameters').hide();
        $('#div_result').hide();
        progressWidgetContainer = $('#div_progress').show();
        window.hWin.HEURIST4.msg.showProgress({
            container: progressWidgetContainer,
            session_id: sessionId,
            interval: 900,
            persistentState: true,
            onAbort: function(api){
                const endpoint = api.options.endpoint;
                window.hWin.HEURIST4.util.sendRequest(
                    endpoint,
                    {terminate:1, t:Date.now(), session:api.getSessionId()},
                    null,
                    function(){
                        api.destroy();
                    },
                    'text'
                );
            },
            onComplete: function(state){
                progressWidgetActive = false;
                if(progressWidgetContainer){
                    progressWidgetContainer.hide();
                }
                if(state && state.status === 'completed' && state.result){
                    if(progressOwnerAction === action_type){
                        _displayActionResult(state.result);
                        _clearStoredProgress();
                    }
                }else if(state && state.status === 'terminated'){
                    _clearStoredProgress();
                    window.hWin.HEURIST4.msg.showMsgFlash('The record batch action was terminated.', 3000);
                }else if(state && state.status === 'error'){
                    _clearStoredProgress();
                    window.hWin.HEURIST4.msg.showMsgErr(state.error || 'The record batch action failed.');
                }
            }
        });
        progressWidgetActive = true;
    }

    function _restoreProgressSession(){
        const stored = _getStoredProgress();
        if(!stored || !stored.session_id){
            return;
        }

        if(stored.action_type && stored.action_type !== action_type){
            action_type = stored.action_type;
            $('#div_header').html(window.hWin.HR('record_action_'+action_type));
            _fillSelectRecordScope();

            let actionLabel = window.hWin.HR('record_action_'+action_type);
            window.hWin.HEURIST4.msg.showMsgFlash(
                'The previous action &quot;'+actionLabel+'&quot; is in progress.',
                2000
            );
        }

        _startProgressWidget(stored.session_id, action_type);
    }

    function _displayActionResult(response){
        $('#div_parameters').hide();
        $('.ui-selectmenu-menu').remove();
        $('#div_result').empty();

        let sResult = '';
        if(action_type==='reset_thumbs'){
            sResult = '<div style="padding:4px">Thumbnail refresh diagnostics v7</div>'
                + '<div style="padding:4px">Files matched: '+(response.filesmatched ?? 'unavailable: server action has not been updated')+'</div>'
                + '<div style="padding:4px">PDF file references matched: '+(response.pdfmatched ?? 'unavailable')+'</div>'
                + '<div style="padding:4px">PDF thumbnails rebuilt: '+(response.pdfrebuilt ?? 0)+'</div>'
                + '<div style="padding:4px">Errors: '+(response.errors ?? 0)+'</div>';
            if(response.filesmatched===0){
                sResult += '<p>No file references were found for these editable records. No thumbnails were rebuilt.</p>';
            }
        }
        for(let key in response){
            if(action_type==='reset_thumbs' && ['filesmatched','pdfmatched','pdfrebuilt'].includes(key)){ continue; }
            if(key && key.indexOf('_')<0 && response[key]>0){
                const lbl_key = 'record_action_'+key;
                let lbl = window.hWin.HR(lbl_key);
                if(lbl==lbl_key){
                    lbl = window.hWin.HR(lbl_key+'_'+action_type);
                }
                if(action_type==='reset_thumbs' && key==='pdfrebuilt'){ lbl = 'PDF thumbnails rebuilt'; }
                let tag_link = '';
                if(response[key+'_tag']){
                    tag_link = '<span><a href="'+
                    encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                        +'&q=tag:"'+response[key+'_tag']+'"')+
                    '" target="_blank">view</a></span>';
                }else if(response[key+'_tag_error']){
                    tag_link = '<span>'+response[key+'_tag_error']['message']+'</span>';
                }else if(key=="processed"){
                    if(action_type!='reset_thumbs'){
                        tag_link = '<span><a href="'+
                        encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                            +'&q=sortby:-m after:"5 minutes ago"')+
                        '" target="_blank">view recent changes</a></span>';
                    }
                }else if(key=='fails' && response['fails_list'] && response['fails_list'].length>0){
                    tag_link = '<span style="background-color:#ffcccc"><a href="'+
                    encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                        +'&q=ids:'+response['fails_list'].join(','))+
                    '" target="_blank">view</a></span>';
                }else if(key == 'limited' && action_type == 'add_detail' && response[key] > 0){
                    tag_link = `<span style="display: block; font-size: 0.9em; padding: 5px 5px;">
                                    For single value fields which were skipped because they already have a value,<br>
                                    use "Recode > Replace field value" to replace all, or selected, existing values with the new value.
                                </span>`;
                }

                sResult += '<div style="padding:4px"><span>'+lbl+'</span><span>&nbsp;&nbsp;'
                    +response[key]+'</span>'+tag_link+'</div>';

                if(key=='errors' && response['errors_list']){
                    const recids = Object.keys(response['errors_list']);
                    if(recids && recids.length>0){
                        sResult += '<div style="background-color:#ffcccc">';
                        for(let key2 in response['errors_list']){
                            let text = response['errors_list'][key2];
                            if(Array.isArray(text)){
                                text = text.join('<br>');
                            }
                            sResult += (key2+': '+ text + '<br>');
                        }
                        sResult += '</div>';
                    }
                }
            }
        }

        // Show deployment help once, collapsed by default. Match the stable
        // renderer exit marker, including older v3 responses. Keep help text
        // static: renderer diagnostics must never be interpolated into it.
        if(action_type==='reset_thumbs' && response.errors_list
                && Object.values(response.errors_list).some(function(error){
                    return /\bcode\s+127\b/i.test(Array.isArray(error) ? error.join(' ') : String(error));
                })){
            sResult += "<details class=\"pdf-thumbnail-admin-help\" style=\"margin-top:18px;max-width:780px\">\n<summary style=\"cursor:pointer;font-weight:bold\">Instructions for server administrator</summary>\n<div class=\"pdf-thumbnail-admin-help-body\" tabindex=\"0\" role=\"region\" aria-label=\"PDF thumbnail administrator instructions\" style=\"padding:8px 12px;line-height:1.5;overflow-wrap:anywhere;box-sizing:border-box\">\n<p>Ask your server administrator to install or repair a PDF renderer and make it available to the PHP process. Exit code 127 normally means that the command, a required delegate, interpreter or runtime dependency could not be found or started. A command may work over SSH but be unavailable to Apache or PHP-FPM because its PATH, permissions or execution environment differ.</p>\n<p><b>1. Recommended: install Poppler.</b> Heurist first tries <code>pdftoppm</code>, supplied by <code>poppler-utils</code>. This renders PDF pages without Ghostscript or ImageMagick PDF-policy changes. Install using the command appropriate to the server:</p>\n<pre style=\"white-space:pre-wrap\"># CentOS 7 / RHEL 7\nsudo yum install poppler-utils\n\n# Rocky Linux / AlmaLinux / current RHEL or Fedora\nsudo dnf install poppler-utils\n\n# Debian / Ubuntu\nsudo apt-get update\nsudo apt-get install poppler-utils\n\n# openSUSE\nsudo zypper install poppler-tools\n\n# Arch Linux\nsudo pacman -S poppler</pre>\n<p><b>CentOS 7: mirrorlist errors or HTTP 404.</b> CentOS 7 reached end of life on 30 June 2024. Its retired mirrors can prevent any package installation. Stop the failed yum command with Ctrl+C. The following commands use a separate temporary repository directory and the official Vault archive for this installation only; existing CentOS, EPEL and Remi repository configuration is not changed. Package signature verification remains enabled.</p>\n<pre style=\"white-space:pre-wrap\">pdf_repo_dir=$(mktemp -d /tmp/heurist-pdf-repos.XXXXXX)\ncat > \"$pdf_repo_dir/CentOS-Vault.repo\" <<'EOF'\n[pdf-vault-base]\nname=CentOS 7.9.2009 Vault Base\nbaseurl=https://vault.centos.org/7.9.2009/os/$basearch/\nenabled=1\ngpgcheck=1\ngpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7\n\n[pdf-vault-updates]\nname=CentOS 7.9.2009 Vault Updates\nbaseurl=https://vault.centos.org/7.9.2009/updates/$basearch/\nenabled=1\ngpgcheck=1\ngpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7\nEOF\nsudo yum --setopt=reposdir=\"$pdf_repo_dir\" --disableplugin=fastestmirror install poppler-utils\npdftoppm -v</pre>\n<p>Run these commands in the same shell. Check that <code>/etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7</code> exists. If the command reports a missing key, obtain and verify the official CentOS signing key; do not bypass signature checks. If Vault itself cannot resolve or HTTPS fails, have the administrator check DNS, outbound access, proxy settings, the system clock and CA certificates. Do not disable TLS verification. After installation, test rendering as the PHP worker below. Vault contains archived packages with no new security fixes; plan migration to a supported OS.</p>\n<p><b>2. Check the actual PHP worker environment.</b> Find the executable and verify that it starts:</p>\n<pre style=\"white-space:pre-wrap\">command -v pdftoppm\n/usr/bin/pdftoppm -v\n\n# Example for an Apache/PHP worker running as apache:\nsudo -u apache /usr/bin/pdftoppm -f 1 -l 1 -singlefile -png -scale-to 200 /path/to/test.pdf /path/to/writable/test-thumbnail</pre>\n<p>Replace <code>apache</code> with the PHP-FPM pool user (often <code>apache</code> or <code>www-data</code>) and replace both example paths. The test creates <code>test-thumbnail.png</code>. The PHP worker must be able to read the PDF, traverse its folders and write the thumbnail folder. A sudo test checks Unix permissions but does not reproduce all PHP-FPM restrictions or SELinux confinement; also test through Heurist.</p>\n<p>Ensure the renderer directory, normally <code>/usr/bin</code>, is in the web PHP process PATH. For PHP-FPM, check the relevant pool's <code>env[PATH]</code> setting, chroot/container mounts and systemd service restrictions. Restart the affected service after configuration changes. Heurist requires <code>proc_open</code> to be callable in the web PHP configuration; check <code>disable_functions</code> and the PHP version (array-based process commands require PHP 7.4 or later). PHP CLI settings can differ from web PHP settings. Check missing shared libraries if an installed executable still will not start.</p>\n<p><b>3. Alternative: ImageMagick with Ghostscript.</b> If Poppler is unavailable, Heurist tries ImageMagick's <code>convert</code>. Install both the renderer and its PDF delegate:</p>\n<pre style=\"white-space:pre-wrap\"># CentOS 7 / RHEL 7\nsudo yum install ImageMagick ghostscript\n\n# Rocky Linux / AlmaLinux / current RHEL or Fedora\nsudo dnf install ImageMagick ghostscript\n\n# Debian / Ubuntu\nsudo apt-get install imagemagick ghostscript\n\n# Inspect availability, delegates and policies:\ncommand -v convert\ncommand -v gs\nconvert -version\ngs --version\nconvert -list delegate\nconvert -list policy\n\n# Render only the first page (quote the page selector):\nconvert -density 72 '/path/to/test.pdf[0]' -background white -alpha remove -alpha off -thumbnail 200x200 /path/to/writable/test-thumbnail.png</pre>\n<p>If ImageMagick 7 provides only <code>magick</code> and no <code>convert</code>, use Poppler for this Heurist version, or install the distribution's supported compatibility command. Do not simply rename the binary. Ghostscript must be executable in the PHP worker's environment as well as the administrator's shell.</p>\n<p><b>4. If ImageMagick reports a PDF security-policy denial.</b> This is a separate problem from exit 127. Use <code>convert -list policy</code> to locate the active policy.xml (commonly under <code>/etc/ImageMagick/</code>, <code>/etc/ImageMagick-6/</code> or <code>/etc/ImageMagick-7/</code>). Prefer Poppler so the PDF restriction can remain in place. If the administrator approves ImageMagick PDF reading, keep ImageMagick and Ghostscript patched and adjust only the relevant PDF coder rule to allow reading:</p>\n<pre style=\"white-space:pre-wrap\">&lt;policy domain=\"coder\" rights=\"read\" pattern=\"PDF\" /&gt;</pre>\n<p>Check for other matching PDF, grouped-coder or delegate restrictions; this single rule may not override every policy. Preserve unrelated restrictions and resource limits. Do not disable the whole security policy or enable all delegates. On CentOS 7, archived versions may lack current security fixes, making Poppler with a server upgrade the preferred route.</p>\n<p><b>5. If rendering still fails.</b> Inspect Apache/PHP-FPM logs and SELinux audit denials, filesystem permissions, available memory/disk space, and encrypted or damaged PDFs. Correct the specific permission or policy issue; do not disable SELinux or use world-writable folders. Re-run <b>Recode &gt; Refresh thumbnails</b> after the server correction. Existing thumbnails are retained when a rebuild fails.</p>\n<p>Reference: <a href=\"https://www.centos.org/centos-linux/\" target=\"_blank\" rel=\"noopener noreferrer\">CentOS lifecycle</a>; <a href=\"https://imagemagick.org/security-policy/\" target=\"_blank\" rel=\"noopener noreferrer\">ImageMagick security policy</a>.</p>\n</div></details>";
        }

        // The popup body has overflow:hidden, so the result must own its scroll.
        // Keep one scrollbar for the entire report, including expanded help.
        // Nested help/error containers must not have their own scroll bounds.
        $('#div_result').html(sResult).css({padding:'10px', overflowY:'auto',
            overflowX:'hidden', maxHeight:'calc(100vh - 90px)', bottom:'3.5em',
            boxSizing:'border-box'}).show();
        $('#btn-ok').button('option','label',window.hWin.HR('New Action'));
        $('#btn-cancel').button('option','label',window.hWin.HR('Close'));
    }

    // 
    //  Main action 
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
            selectRecordScope.val('').trigger('change');
            return;
        }

        let request = { tag: $('#cb_add_tags').is(':checked')?1:0 };

        if(action_type=='reset_thumbs' || action_type=='iiif_thumbs'){
           request['a'] = action_type;
           if(action_type=='iiif_thumbs' && $('#cb_iiif_thumbs_missedonly').is(':checked')){
               request['missedonly'] = 1;
           }
        }else
        if(action_type!='rectype_change'){

            let dtyID = $('#sel_fieldtype').val();
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

                    $('#cb_sub_string').is(':checked') ? request['substr'] = 1 : request['wholeval'] = 1;
                }else{
                    request['insert_new_values'] = $('#cb_add_value').is(':checked') ? 1 : 0;
                }
                request['rVal'] = getFieldValue('fld-2');
                if(!_allow_empty_replace && window.hWin.HEURIST4.util.isempty(request['rVal'])){

                    let msg_part = request['substr'] == 1 ? '(only the search string is deleted)' : '(the whole value is deleted)';
                    let msg = 'You have not defined a replacement value<br><br>'
                            + `Click "${window.hWin.HR('OK')}" to delete the search string ${msg_part}<br>`
                            + `Click "${window.hWin.HR('Cancel')}" if you want to replace the search string with a new string`;

                    window.hWin.HEURIST4.msg.showMsgDlg(msg, 
                        () => {
                            _allow_empty_replace = true;
                            _startAction();
                            return;
                        },
                        {title: window.hWin.HR('Empty replace value'), yes: window.hWin.HR('OK'), no: window.hWin.HR('Cancel')}, 
                        {default_palette_class: 'ui-heurist-explore'}
                    );

                    return;
                }else if(_allow_empty_replace){
                    request['replace_empty'] = 1;
                    _allow_empty_replace = false;
                }
            
            }else if(action_type=='url_to_file'){

                request['a'] = 'url_to_file';

                if($('#cb_match_only').is(':checked')){
                    request['match_only'] = 1;
                }
                let url_substring = $('#url_substring').val();
                if(!window.hWin.HEURIST4.util.isempty(url_substring)){
                    request['url_substring'] = url_substring;
                }
                

            }else if(action_type=='local_to_repository'){

                request['a'] = 'local_to_repository';

                request['repository'] = $('#sel_repository').val();

                if($('#cb_del_local_file').is(':checked')){
                    request['delete_file'] = 1;
                }

                if(request['repository'].indexOf('nakala')===0 || request['repository'].indexOf('nakala')===1){
                    request['license'] = $('#sel_license').val();
                    if(window.hWin.HEURIST4.util.isempty(request['license'])){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a license', 3000);
                        return;
                    }
                    request['status'] = $('[name="nakala_status"]:checked').val();
                }

            }else if(action_type=='delete_detail'){

                request['a'] = 'delete';
                if(!$('#cb_delete_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        alert('Define value to delete');
                        return;
                    }

                    $('#cb_sub_string').is(':checked') ? request['substr'] = 1 : request['wholeval'] = 1;
                }
            }else if(action_type=='extract_pdf' || action_type=='nl2br'){
                
                request['a'] = action_type;
            }else if(action_type=='case_conversion'){

                request['a'] = action_type;

                request['op'] = $('#case_convert_op').val();

                let except = $('#except_user').val();
                except = except.split('\n').join('|');
                except += $('#except_default').val().split('\n').join('|');

                request['except'] = except;
                
            }else if(action_type=='translation'){

                request['a'] = action_type;
            
                request['lang'] = $('#sel_language').val(); 
                
                if($('#cb_translation_delete').is(':checked')){
                        request['delete'] = 1;
                }else 
                if($('#cb_translation_replace').is(':checked')){
                        request['replace'] = 1;
                }
                
            }else if(action_type=='increment'){

                request['a'] = action_type;

                if($('#cb_increment_fillgaps').is(':checked')){
                    request['fillgaps'] = 1;
                }

                const incrementPrefix = $('#increment_prefix').val();
                if(incrementPrefix){
                    request['prefix'] = incrementPrefix;
                }

                const incrementDigits = parseInt($('#increment_digits').val(), 10);
                request['digits'] = incrementDigits>0 ? incrementDigits : 4;
            }

            if(_check_field_repeat && _check_field_repeatability()){
                return;
            }

        }

        let scope_type = selectRecordScope.val();
        let scope;

        if(action_type=='iiif_thumbs'){
            scope_type = iiifAnnotationRtyID;
        }
        
        if(scope_type=="Selected" || scope_type=="Collected"){
            scope = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;
        }else{
            scope = window.hWin.HAPI4.currentRecordset.getIds();
            if(scope_type!="Current"){
                request['rtyID'] = scope_type;
            }
        }
        request['recIDs'] = scope.join(',');


        if(action_type=='rectype_change'){
            
            let rtyID = $('#sel_recordtype').val();
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
        if(_needsProgress(request)){
            const stored = _getStoredProgress();
            if(stored && stored.session_id){
                _restoreProgressSession();
                return;
            }

            progressSessionId = String(window.hWin.HEURIST4.util.random());
            request.session = progressSessionId;
            _storeProgress({
                session_id: progressSessionId,
                action_type: action_type,
                started: Date.now()
            });
            _startProgressWidget(progressSessionId, action_type);
        }else{
            // show hourglass/wait icon
            $('body > div:not(.loading)').hide();
            $('.loading').show();
        }
        
        $('#btn-ok').addClass('ui-state-disabled').off('click')


        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){

            //$('body > div:not(.loading)').show();
            $('body > #ui-datepicker-div').hide();
            $('.loading').hide();
            $('#btn-ok').removeClass('ui-state-disabled').on('click',_startAction);
            
            let success = (response.status == window.hWin.ResponseStatus.OK);
            if(success){
                _stopProgressWidget();
                _clearStoredProgress();
                _displayActionResult(response['data']);
            }else{
                _stopProgressWidget();
                _clearStoredProgress();
                $('#div_result').hide();
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }

            window.hWin.HEURIST4.msg.sendCoverallToBack();
        });

    }

    /**
     * Check if current record type + base field is repeatable; currently for bulk translating
     * 
     * @returns {bool} - true on success, false on failure
     */
    function _check_field_repeatability(){

        let rty_ID = selectRecordScope.val();
        if(!window.hWin.HEURIST4.util.isNumber(rty_ID)){ // multiple rectypes
            return false;
        }

        let dty_ID = $('#sel_fieldtype').val();
        if($Db.rst(rty_ID, dty_ID, 'rst_MaxValues') != 1){
            return false;
        }

        // Warn about repeating fields
        let $dlg = null;
        let msg = `To avoid issues with editing the affected records in the future, we first recommend making the field "${$Db.rst(rty_ID, dty_ID, 'rst_DisplayName')}" repeatable.<br><br>Would you like to make the field repeatable?`;

        let btns = {};

        btns[window.hWin.HR('Yes')] = function(){

            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), null, 'Updating field definition...');

            let fields = {
                'rst_DetailTypeID': dty_ID,
                'rst_RecTypeID': rty_ID,
                'rst_MaxValues': 0
            };

            let request = {
                a: 'save',
                entity: 'defRecStructure',
                fields: fields,
                request_id: window.hWin.HEURIST4.util.random()
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                $dlg.dialog('close');

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                window.hWin.HAPI4.EntityMgr.refreshEntityData('rst', _startAction);
            });
        };

        btns[window.hWin.HR('No, and continue with recode')] = function(){
            $dlg.dialog('close');
            _check_field_repeat = false;
            _startAction();
        };

        btns[window.hWin.HR('Cancel')] = function(){
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Field repeatability', yes: window.hWin.HR('Yes'), no: window.hWin.HR('No, and continue with recode'), cancel: window.hWin.HR('Cancel')}, {default_palette_class: 'ui-heurist-design'});

        return true;
    }

    //public members
    let that = {
    }

    
    _init();
    return that;  //returns object
}