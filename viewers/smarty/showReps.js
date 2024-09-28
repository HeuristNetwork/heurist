/**
* showReps.js : insertion of various forms of text into smarty editor
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global CodeMirror */

/**
* SmartyEditor - 
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0509
*/

function ShowReps( is_snippet_editor ) {

    const _className = "ShowReps";
    let _originalFileName,

    _needListRefresh = false, //if true - reload list of templates after editor exit
    _keepTemplateValue,
    _needSelection = false,
    _sQueryMode = "all",

    needReload = true,
    codeEditor = null,

    _currentRecordset = null,
    _currentQuery = null,

    _add_variable_dlg = null,
    
    _is_snippet_editor = (is_snippet_editor===true),
    
    _facet_value = null;
    
    
    let top_repcontainer = '36px';
    
    /**
    *  show the list of available reports
    *  #todo - filter based on record types in result set
    */
    function _updateTemplatesList(context) {


        let sel = document.getElementById('selTemplates'),
        option,
        keepSelIndex = sel.selectedIndex,
        _currentTemplate = window.hWin.HAPI4.get_prefs('viewerCurrentTemplate');

        /*celear selection list
        while (sel.length>0){
            sel.remove(0);
        }

        if(context && context.length>0){

            for (i in context){
                if(i!==undefined){

                    window.hWin.HEURIST4.ui.addoption(sel, context[i].filename, context[i].name);
                    
                    if(keepSelIndex<0 && _currentTemplate==context[i].filename){
                        keepSelIndex = sel.length-1;
                    }
                }
            } // for

            sel.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;

            if(sel.selectedIndex>=0){
                _reload(context[sel.selectedIndex].filename);
            }
        }
        */


        let all_templates = []        
        if(context && context.length>0){

            for (let i=0; i<context.length; i++){
                    all_templates.push({key:context[i].filename, title:context[i].name});
                    
                    if(keepSelIndex<0 && _currentTemplate==context[i].filename){
                        keepSelIndex = sel.length-1;
                    }
            } // for


            let sel2 = window.hWin.HEURIST4.ui.createSelector(sel, all_templates);
            sel2.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;
            
            window.hWin.HEURIST4.ui.initHSelect(sel2, false);
            
            if(sel.selectedIndex>=0){
                //_reload(context[sel2.selectedIndex].filename);
            }
        }
        
        _setLayout(true, false);
    }

    /**
    * Inserts template output into container
    */
    function _updateReps(context) {

        window.hWin.HEURIST4.msg.sendCoverallToBack();

        if(context == 'NAN' || context == 'INF' || context == 'NULL'){
            context = 'No value';
        }
        
        if(_is_snippet_editor){
            
            document.getElementById('snippet_output').innerHTML = context;
            
        }else{
            
            let txt = (context && context.message)?context.message:context
            
            let iframe = document.getElementById("rep_container_frame");
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write( txt );
            iframe.contentWindow.document.close();
            
            //document.getElementById('rep_container').innerHTML = context;

            _needSelection = (txt && txt.indexOf("Select records to see template output")>0);
            
        }
    }


    /**
    * Initialization
    *
    * Reads GET parameters and requests for map data from server
    */
    function _init() {
        
        _sQueryMode = "all";
        $('#cbUseAllRecords1').hide();
        $('#cbUseAllRecords2').hide();

        if(!_is_snippet_editor){
            _reload_templates();    
        }
        

        window.onbeforeunload = _onbeforeunload;
        
        //aftert load show viewer only
        //_setLayout(true, false); it is called in _updateTemplatesList
        _onResize(this.innerWidth);
    }

    /**
    * loads list of templates
    */
    function _reload_templates(){
        
        let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
        let callback = _updateTemplatesList;
        let request = {mode:'list', db:window.hWin.HAPI4.database};
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
    }


    function _getSelectedTemplate(){

        let sel = document.getElementById('selTemplates');
        if(window.hWin.HEURIST4.util.isnull(sel) || window.hWin.HEURIST4.util.isnull(sel.options) 
            || sel.options.length===0) { return null; }
            
        if(sel.selectedIndex<0 || !sel.options[sel.selectedIndex]){
            
            sel.selectedIndex = 0;
            $(sel).hSelect("refresh"); 
        }

        return sel.options[sel.selectedIndex].value; // by default first entry        
    }

    /**
    *
    */
    function _getQueryAndTemplate(template_file, isencode){

        let squery = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest( _currentQuery, true );

        if(window.hWin.HEURIST4.util.isempty(squery) ||  (squery.indexOf("&q=")<0) || 
            (squery.indexOf("&q=") == squery.length-3)) {
                
            if(_sQueryMode=="selected"){
                _updateReps("<div class='wrap'><div id='errorMsg'><span>No Records Selected</span></div></div>");
            }else{
                _updateReps('<span style="color:#ff0000;font-weight:bold">Select saved search or apply a filter to see report output</span>');
            }

            return null;

        }else{

            if(window.hWin.HEURIST4.util.isnull(template_file)){
                template_file = _getSelectedTemplate();
            }

            if(isencode){
                squery = 'hquery='+encodeURIComponent(squery);
            }

            squery = squery + '&template='+template_file;
        }

        return squery;
    }

    /**
    * Executes the template with the given query
    */
    function _reload(template_file) {

        let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php";
        let request = null;
        let session_id = Math.round((new Date()).getTime()/1000);

        if(_currentRecordset!=null){
            //new approach to support H4
            if(window.hWin.HEURIST4.util.isnull(template_file)){
                template_file = _getSelectedTemplate();
            }
            if(window.hWin.HEURIST4.util.isnull(template_file)){
                return;
            }
            
            //limit to  records  smarty-output-limit
            let recset;
            if(_currentRecordset.recordCount>0){
                let limit = window.hWin.HAPI4.get_prefs_def('smarty-output-limit',50);
                if(limit>2000) limit = 2000;
                recset = {recIDs:_currentRecordset.recIDs.slice(0, limit-1), recordCount:limit , resultCount:limit};
            }else{
                recset = _currentRecordset;
            }

            request = {db:window.hWin.HAPI4.database, 
                       template:template_file, 
                       recordset:JSON.stringify(recset), 
                       session:session_id};

        }else{
            return; //use global recordset only
            //squery = _getQueryAndTemplate(template_file, false); //NOT USED
        }

        if(_facet_value){
            request['facet_val'] = _facet_value;
        }

        if(request!=null){

            window.hWin.HEURIST4.msg.bringCoverallToFront($(document).find('body')); //this frame

            _showProgress( session_id );
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response){
                _hideProgress();
                if(response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    if(response.message){ //error in php code
                        _updateReps( response.message );    
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);                       
                    }
                    
                }else{
                    _updateReps( response );
                }
            }, 'auto');
        }
    }

    //
    //
    //
    function _showProgress( session_id ){

        if(that.progressInterval>0){ //!(session_id>0)) { //clear previous interval requests
             _hideProgress();
             //return;
        }
       
        let progressCounter = 0;        
        let progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        $('#toolbardiv').hide();
        $('#progressbar_div').show();
        $('body').css('cursor','progress');
        
        $('#progress_stop').button().on({click: function() {
            
            let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                _hideProgress();
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    console.error(response);                   
                }
            });
        } }, 'text');
        
        let pbar = $('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        //pbar.progressbar('value', 0);
        /*{
              value: false,
              change: function() {
                progressLabel.text( progressbar.progressbar( "value" ) + "%" );
              },
              complete: function() {
                progressLabel.text( "Complete!" );
              }
        });*/
        
        that.progressInterval = setInterval(function(){ 

            let request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                
                if(!response || response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    _hideProgress();
                }else{
                    
                    let resp = response?response.split(','):[0,0];
                    
                    if(resp && resp[0]){
                        if(progressCounter>0){
                            if(resp[1]>0){
                                let val = resp[0]*100/resp[1];
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
                    }else{
                        _hideProgress();
                    }
                    
                    
                    progressCounter++;
                    
                }
            },'text');
          
        
        }, 1000);                
        
    }
    
    function _hideProgress(){
        
        
        $('body').css('cursor','auto');
        
        if(that.progressInterval!=null){
            
            clearInterval(that.progressInterval);
            that.progressInterval = null;
        }
        $('#progressbar_div').hide();
        $('#toolbardiv').show();
        
    }

    function _onbeforeunload() {
        if(_iseditor && _keepTemplateValue && _keepTemplateValue!=codeEditor.getValue()){
            return "Template was changed. Are you sure you wish to exit and lose all modifications?!!!";
        }
        _hideProgress();
    }



    /**
    * Creates new template from the given query
    *
    * isLoadGenerated - new template
    */
    function _generateTemplate(name, isLoadGenerated) {

        function __onGenerateTemplate(context){

            if(window.hWin.HEURIST4.util.isnull(context)){
                return;
            }
            if(context===false){
                window.hWin.HEURIST4.msg.showMsgErr({message: 'No template generated', error_title: 'No template'});
                return;
            }

            if(isLoadGenerated){

                    let text = [


                        '<html>',
                        '{* This is a very simple Smarty report template which you can edit into something more sophisticated.',
                        '   Enter html for web pages or simple text for plain text formats. Use tree on right to insert fields',
                        '   and templates for commonly used patterns. Use <!-- --> for output of html comments.',
                        '',
                        '{* Text like this, enclosed in matching braces + asterisk, is a comment. We suggest you start by removing',
                        ' our comments and adding your own - plentiful comments will help with ongoing maintenance of your templates.*}',
                        '',
                        '',
                        '<h2>Title for report</h2> {* Text here appears at start of report *}',
                        '<hr>',
                        '',
                        '{*------------------------------------------------------------*}',
                        '{foreach $results as $r} {* Start records loop, do not remove *}',
                        '{$r = $heurist->getRecord($r)}',
                        '{*------------------------------------------------------------*}',
                        '',
                        '',
                        '  {* We STRONGLY advise visiting the Help link above - it will show you how to *}',
                        '  {* use this function, as well as access all its sophisticated capabilities. *}',
                        '',
                        '',
                        '  {* Put the data you want output for each record here - insert the *}',
                        '  {* fields using the tree of record types and fields on the right. *}',
                        '  {* Use the pulldown of templates to insert commonly used patterns.*}',
                        '',
                        '  {* Examples - delete and replace with the fields you want to output: *}',
                        '     {$r.recID}  {* the unique record ID *}',
                        '     {$r.f1}     {* the name / title field - may or may not be present *}  ',
                        '',
                        '',
                        '<br> {* line break between each record *}',
                        '',
                        '{*------------------------------------------------------------*}',
                        '{/foreach} {* end records loop, do not remove *}',
                        '{*------------------------------------------------------------*}',
                        '',
                        '<hr>',
                        '<h2>End of report</h2> {* Text here appears at end of report *} ',
                        '</html>'];


                    let k;
                    let res = "";
                    for (k=0;k<text.length;k++){
                        res += text[k] + "\n"; // + text.substr(k+2);
                    }

                    document.getElementById("edTemplateName").innerHTML = name;
                    _initEditor(res);
            }

            _setLayout(true, true);
            
            _loadRecordTypeTreeView();

            _doExecuteFromEditor(); //execute at once
        }
        

        if(isLoadGenerated){
            __onGenerateTemplate([]);
        }
        
        let rtSelect = $('#rectype_selector').css('max-width','150px');
        let $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), true );
        $rec_select.change(function(event){
            _loadRecordTypeTreeView();
        });
    }



    function _initEditor(content) {
        if(codeEditor==null){

                codeEditor = CodeMirror(document.getElementById("templateCode"), {
                    mode           : "smartymixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : true,
                    smartyVersion  : 3,
                    matchBrackets  : true,
                    smartIndent    : true,
                    extraKeys: {
                        "Enter": function(e){
                            insertAtCursor('');
                        }
                    },
                    onFocus:function(){},
                    onBlur:function(){}
                });
        }

        let using_default = false;
        if(window.hWin.HEURIST4.util.isempty(content)){
            content = "{ }";
            using_default = true;
        }

        codeEditor.setValue(content);

        setTimeout(function(){
            $('div.CodeMirror').css('height','100%').show();
            $('div.CodeMirror .CodeMirror-scroll').css('padding-top', '5px');
            codeEditor.refresh();
            _keepTemplateValue = codeEditor.getValue();

            if(using_default){
                codeEditor.setCursor({line: 0, char: 0});
            }
        },1000);
    }



    function _initEditorMode(template_file, template_body){

        document.getElementById("edTemplateName").innerHTML = template_file;
        _initEditor(template_body);
        _setLayout(true, true);
    }



    /**
    * Creates new template from the given query
    */
    function _showEditor(template_file) {

        function _onGetTemplate(context){
            _initEditorMode(template_file, context);
        }

        let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";

        _originalFileName = template_file;

        let request = {mode:'get', db:window.hWin.HAPI4.database, template:template_file};

        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
        function(obj) {
            if (obj  &&  obj.error) {
                window.hWin.HEURIST4.msg.showMsgErr({message: obj.error, error_title: 'Failed to create template'});
            }else{
                _onGetTemplate(obj);
            }
        }, 'auto'
        );

        _generateTemplate(template_file, null);
    }


    /**
    * Convert template to global
    */
    function _doExportTemplate() {

        let istest = false;
        
        let dbId = Number(window.hWin.HAPI4.sysinfo['db_registeredid']);
        if(dbId > 0){

            let template_file = $('#selTemplates').val();
            if( window.hWin.HEURIST4.util.isempty(template_file) ) return;
            
            let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";

            let request = { mode:'serve',db:window.hWin.HAPI4.database, dir:0, template:template_file };

            if(istest){
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null,
                 function(obj) {
                    if (obj  &&  obj.error) {
                        window.hWin.HEURIST4.msg.showMsgErr({message: obj.error, error_title: 'Failed to create template'});
                    }else{
                        _updateReps('<xmp>'+obj+'</xmp>');
                    }
                 },'auto');
            }else{
                let squery = 'db='+window.hWin.HAPI4.database+'&mode=serve&dir=0&template='+template_file;
                //template.gpl
                window.hWin.HEURIST4.util.downloadURL(baseurl+'?'+squery);
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Database must be registered to allow translation of local template to global template.',
                error_title: 'Cannot convert to global template'
            });
        }
    }

    /**
    * Save formula for calculated field
    * 
    * @param mode - 2 save and close, 0 - just close
    * @param unconditionally
    * 
    * @returns {Object}
    */
    function _operationSnippetEditor(mode) {

        if(mode===0){ //for close

            if(_keepTemplateValue!=codeEditor.getValue()){ 

                let $dlgm = window.hWin.HEURIST4.msg.showMsgDlg(
                    'Formula was changed. Are you sure you wish to exit and lose all modifications?',
                    {'Save': function() {
                        _operationSnippetEditor(2);
                        $dlgm.dialog( 'close' );
                        },
                        'Discard': function() {  //exit without save
                            window.close();
                            $dlgm.dialog( 'close' );
                        },
                        'Cancel':function() {
                            $dlgm.dialog( 'close' );
                        }
                    },
                    'Warning');
            }else{
                window.close();
            }

        }else if(mode==2){
            window.close( codeEditor.getValue() );
        }
        
    }

    /**
    * Close editor
    * @param mode 0 - just close, 1 - save as (not close),  2 - save, close and execute, 3 - delete and close
    */
    function _operationEditor(mode, unconditionally) {

        if(_is_snippet_editor){
            _operationSnippetEditor(mode);
            return;
        }
        
        if(mode>0){

            let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
            let request = {db: window.hWin.HAPI4.database};
            let template_file = null;

            if(mode<3)
            { //save
                template_file = jQuery.trim(document.getElementById("edTemplateName").innerHTML);

                if(mode==1){ //save as - get new name
                
                    if(unconditionally){
                    
                        document.getElementById("edTemplateName").textContent = unconditionally;    
                        template_file = unconditionally;    
                    }else{
                        window.hWin.HEURIST4.msg.showPrompt('Please enter template name', function(tmp_name){
                            if(!window.hWin.HEURIST4.util.isempty(tmp_name)){
                                 _operationEditor(mode, tmp_name)
                            }
                        }, {title:'Save template as',yes:'Save as',no:"Cancel"});
                        return;
                    }
                }

                let template_body = codeEditor.getValue();// document.getElementById("edTemplateBody").value;
                if(template_body && template_body.length>10){
                    request['mode'] = 'save';
                    request['template'] = template_file;
                    request['template_body'] = encodeURIComponent(template_body);

                    _keepTemplateValue = template_body;
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'The template body is suspiciously short. No operation performed.',
                        error_title: 'No action'
                    });
                    request = null;
                }
            }
            else if (mode===3 && _originalFileName!=="") //delete template
            { //delete

                if(unconditionally===true){
                    request['mode'] = 'delete';
                    request['template'] = _originalFileName;
                    
                    _originalFileName = null;
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'Are you sure you wish to delete template "'+_originalFileName+'"?', 
                            function(){ _operationEditor(mode, true) }, 
                        {title:'Warning',yes:'Proceed',no:'Cancel'});        
                    return;
                }
            }

            if(request!=null){

                let modeRef = mode;
                let alwin;

                function __onOperEnd(context){
                    
                    $('*').css('cursor', 'default');
                    window.hWin.HEURIST4.msg.sendCoverallToBack();

                    if(!window.hWin.HEURIST4.util.isnull(context))
                    {
                        let mode = context.ok;
                        if(modeRef===3){ //delete
                            //todo!!!! - remove template from the list and clear editor
                            _reload_templates();
                        }else if(template_file!=null){
                            _originalFileName = template_file;//_onGetTemplate(obj);

                            window.hWin.HEURIST4.msg.showMsgFlash('Template "'+template_file+'" has been saved', 1000);
                            
                        }

                        if(modeRef===1 || modeRef===3){
                            _needListRefresh = true;
                        }
                        if(modeRef===3){ //for close or delete
                            _setLayout(true, false);
                        }
                        if(modeRef===2){
                            //reload and execute the template
                            _reload(template_file);
                        }
                    }
                }
                
                $('*').css('cursor', 'progress');
                window.hWin.HEURIST4.msg.bringCoverallToFront($(document).find('body'));

                 window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __onOperEnd, 'auto');
            }
        }

        if(mode===0){ //for close

            if(_keepTemplateValue!=codeEditor.getValue()){ //.get("edTemplateBody").value){

                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'Template was changed. Are you sure you wish to exit and lose all modifications?',
                        {'Save': function() {
                            _operationEditor(2);
                            let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg.dialog( 'close' );},
                            'Discard': function() {
                                _setLayout(true, false);
                                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                $dlg.dialog( 'close' );},
                            'Cancel':function() {
                                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                $dlg.dialog( 'close' );
                            }
                        },
                        'Warning');

            }else{
                _setLayout(true, false);
            }

        }
    }



    /**
    * Executes the template from editor
    *
    * isdebug = 1-yes
    */
    function _doExecuteFromEditor() {

        let replevel = document.getElementById('cbErrorReportLevel').value;
        if(replevel<0) {
            document.getElementById('cbErrorReportLevel').value = 0;
            replevel = 0;
        }
        let debug_limit = document.getElementById('cbDebugReportLimit').value;


        /*
        if(document.getElementById('cbDebug').checked){
        replevel = 1;
        }else if (document.getElementById('cbError').checked){
        replevel = 2;
        }*/

        let template_body = codeEditor.getValue();

        if(template_body && template_body.length>10){


            window.hWin.HEURIST4.msg.bringCoverallToFront($(document).find('body'));
            
            let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php";

            let request = {};


            request['replevel'] = replevel;
            request['template_body'] = template_body;
            
            if(_is_snippet_editor){
                let rec_ID = $('#listRecords').val();
                if(!(rec_ID>0)){
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Select record to test on',
                        error_title: 'Missing record'
                    });
                    return;
                }
                request['publish'] = 4;
                request['recordset'] = {records:[$('#listRecords').val()], reccount:1};
                
            }else{
                if(_currentRecordset!=null){
                    request['recordset'] = JSON.stringify(_currentRecordset);
                }else{
                    request = window.hWin.HEURIST4.util.cloneJSON(_currentQuery);
                }

                if(debug_limit>0){
                    request['limit'] = debug_limit;
                }
                
            }
            
            _hideProgress();

            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                function(context) {
                    //_hideProgress();
                    _updateReps(context);
                }, 'auto');

        }else{
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Nothing to execute',
                error_title: 'No action'
            });
        }
    }



    let layout = null, _isviewer, _iseditor, _kept_width=-1;

    /**
    * onclick handler that solves the Safari issue of not responding to onclick
    *
    * @param ele
    */
    function clickworkaround(ele)
    {
        if(navigator.userAgent.indexOf('Safari')>0){
            //Safari so create a click event and dispatch it
            let event = document.createEvent("HTMLEvents");
            event.initEvent("click", true, true);
            ele.dispatchEvent(event);
        }else{
            // direct dispatch
            ele.click();
        }
    }

    /**
    * Special layour for edit small smarty template for calculated fields and record title masks (in future)
    * 
    */
    function _initSnippetEditor(template_body, rty_IDs, onChangeEvent)
    {
        
        _is_snippet_editor = true;
        
        _initEditorMode('', template_body);
        
        _keepTemplateValue = template_body;
        
 
        let mylayout = $('#layout_container').layout();
        mylayout.sizePane('north', '100%');
        mylayout.hide('center');
        $('.ui-layout-resizer').hide();

        $('.actionButtons').css({right: '0px', left: '280px', top: '65%', padding: '10px'});
        $('#templateTree').css({'bottom':'0px', top:'0px'});
        $('.rtt-tree').css({top:'60px'});
        $('#edTemplateName').parent().hide();
        $('#selInsertPattern').parent().hide();
        $('#btnSaveAs').hide();
        $('#btnSave').attr('title','');
        $('#lblFormula').show();
        
        if(onChangeEvent){
            $('.rtt-tree').css({top:'60px'});
            $('#btnSaveAs').parent().hide();
            $('#templateCode').css({bottom: '35%', margin: '10px', top: '45px'});
            $('#divHelpLink').hide();
            $('#templateTree').css({'padding-top':'0px'});
            $('#rectype_selector').parent().css({'margin-top':'0px'});
        }else{
            $('#lblFormula, #lblHelp').css({top:'20px'});
            $('.rtt-tree').css({top:'85px'});
            $('#templateCode').css({bottom: '30%', margin: '10px', top: '65px'});
            $('.actionButtons').css('top', '70%');
        }
        
        
        $('<label>'+window.hWin.HR('Record to test')+': </label>').insertBefore($('#divDebugReportLimit'));
        $('<select id="listRecords">').css('max-width','260px').insertBefore($('#divDebugReportLimit'));
        $('#divDebugReportLimit').hide();

        $('<div id="snippet_output">')
            .css({border: '1px solid blue',height:'40px',widht:'100%','margin-top':'10px',overflow:'auto',padding:'10px'})
            .appendTo($('.actionButtons'));
        
        let rtSelect = $('#rectype_selector').css('max-width','150px');
        let $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), rty_IDs, 
                (rty_IDs && rty_IDs.length==1)?null:window.hWin.HR('select record type'), true );
        if(rty_IDs && rty_IDs.length==1){
            _loadRecordTypeTreeView();
            _loadTestRecords()
        }else{
            $rec_select.change(function(event){
                _loadRecordTypeTreeView();
                _loadTestRecords();
            }); 
        }
        
        //_doExecuteFromEditor
        if(typeof onChangeEvent === 'function'){
            codeEditor.on('change', onChangeEvent);
        }
        
    }
    
    //
    // Load limited list of records of giveb record types (to test template)
    //
    function _loadTestRecords()
    {
        let rty_ID = $('#rectype_selector').val();
        //load list of records for testing 
        if(rty_ID>0){
        let request = {q: 't:'+rty_ID, w: 'all', detail:'header', limit:100 };
         
            window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( recordset )
            {
                if(recordset!=null){
                    
                    // it returns several record of given record type to apply tests
                    //fill list of records
                    let sel = $('#listRecords')[0];
                    //clear selection list
                    while (sel.length>1){
                        sel.remove(1);
                    }

                    let recs = recordset.getRecords();
                    for(let rec_ID in recs) 
                    if(rec_ID>0){
                        window.hWin.HEURIST4.ui.addoption(sel, rec_ID, 
                            window.hWin.HEURIST4.util.stripTags(recordset.fld(recs[rec_ID], 'rec_Title')));
                    }

                    sel.selectedIndex = 0;
                    
                }
            });
        }
    }
    
    /**
    * change visibility
    */
    function _setLayout(isviewer, iseditor){

        if(_isviewer===isviewer && _iseditor===iseditor ||
            (!isviewer && !iseditor) )
        {
            return;
        }

        if(_iseditor!=iseditor){
            //find in parent
            let restLayout = top.document.getElementById("resetPanels_Smarty"+(iseditor?"On":"Off"));
            if(restLayout){
                clickworkaround(restLayout);
            }
        }

        _isviewer=isviewer;
        _iseditor=iseditor;

        
        document.getElementById("toolbardiv").style.display = (iseditor) ?"none" :"block";
        document.getElementById("rep_container").style.top = (iseditor) ?"0px" :top_repcontainer;
        //document.getElementById("editorcontainer").style.display = (iseditor) ?"block" :"none";
        
        let layout_opts = {
            applyDefaultStyles: true,
            maskContents: true,
            north:{
                minHeight:200
            }
        };
        //north - editor
        //center - preview
        
        if(isviewer && iseditor){
            layout_opts.north__minHeight = 200;
        }
        

        let mylayout = $('#layout_container').layout(layout_opts);
        
        if(iseditor){
            mylayout.show('north');    
            let dh =  this.innerHeight;
            mylayout.sizePane('north', dh*0.7);
        }else{
            mylayout.hide('north');    
        }
        
        if(_is_snippet_editor) return;
        
        
        //reload templates list
        if(_needListRefresh && !iseditor){
            _needListRefresh = false;
            _reload_templates();
        }

        let container_ele = $(window.hWin.document).find('div[layout_id="FAP2"]');
        
        //resize global cardinal layout
        if( window.hWin.HAPI4.LayoutMgr && window.hWin.HAPI4.LayoutMgr.cardinalPanel){
            if(iseditor){
                _kept_width = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['east','outerWidth'], container_ele );
                window.hWin.HAPI4.LayoutMgr.cardinalPanel('close', 'west');
                
                //maximize width
                window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', 
                        ['east', (top?top.innerWidth:window.innerWidth)-150 ], container_ele);  
                _doExecuteFromEditor();
            }else if(isviewer){
                if(_kept_width>0)
                    window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane', ['east', _kept_width], container_ele);  //restore width
                    
                window.hWin.HAPI4.LayoutMgr.cardinalPanel('open', 'west');
                
                let sel = document.getElementById('selTemplates');
                if(sel.selectedIndex>=0){
                    let template_file = sel.options[sel.selectedIndex].value;
                    _reload(template_file);
                }
            }
        }
    }


    //
    //
    //
    function _loadRecordTypeTreeView(){
      
        let rty_ID = $('#rectype_selector').val();

        //load treeview
        let treediv = $('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        if(!(rty_ID>0)){
            treediv.text('Please select a record type from the pulldown above');
            return;
        }
        
        treediv.empty();
        
        //generate treedata from rectype structure
        let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, rty_ID, 
                        ['ID','title','typeid','typename','modified','url','tags','all','parent_link'] );

        treedata[0].expanded = true; //first expanded

        if(_is_snippet_editor){
            //hide root - record type title
            treedata = treedata[0];
        }

        treediv.fancytree({
            checkbox: false,
            selectMode: 1,  // single
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){
                    return false;
                }
            },
            lazyLoad: function(event, data){
                let node = data.node;
                let parentcode = node.data.code; 
                let rectypes = node.data.rt_ids;

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, 
                    rectypes, ['ID','title','typeid','typename','modified','url','tags','all'], parentcode );

                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
            },
            select: function(e, data) {
            },
            click: function(e, data){

                if(data.node.data.type == 'separator'){
                    return false;
                }

                let ele = $(e.originalEvent.target);
                if(ele.is('a')){
                    
                    if(ele.text()=='insert'){
                        if(_is_snippet_editor){
                            _insertSelectedVars2(data.node, 0, false, 0);
                        }else{
                            //insert-popup
                            _showInsertPopup2( data.node, ele );
                        }
                    }else{
                        _closeInsertPopup();
                        
                        if(ele.text()=='repeat'){
                            _insertSelectedVars2( data.node, 1, false );
                        }else if(ele.text()=='if'){
                            _insertSelectedVars2( data.node, 0, true );
                        }
                    }
                }
                
                /*
                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    data.node.setExpanded(!data.node.isExpanded());
                    //treediv.find('.fancytree-expander').hide();

                }else if( data.node.lazy) {
                    data.node.setExpanded( true );
                }
                */
            },
            renderNode: function(event, data) {
                // Optionally tweak data.node.span
                let node = data.node;

                let $span = $(node.span);
                let new_title = node.title;//debug + '('+node.data.code+'  key='+node.key+  ')';

                if(data.node.data.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }else if(node.data.type!='enum' && node.data.is_rec_fields == null && node.data.is_generic_fields == null){
                    let op = '';
                    if(node.data.type=='resource' || node.title=='Relationship'){ //resource (record pointer)
                        op = 'repeat';
                    }else if(node.children){
                        op = 'if';
                    }else{
                        op = 'insert';
                    }
                    if(op){
                        new_title = new_title + ' (<a href="#">'+op+'</a>)'; 
                    }
                }

                if(data.node.parent && data.node.parent.data.type == 'resource' || data.node.parent.data.type == 'relmarker'){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }
                
                $span.find("> span.fancytree-title").html(new_title);
            }            
        });
        
    }

    //
    // NEW if for root rectypes
    //
    function _insertRectypeIf2(_nodep, parent, rectypeId){
        
        let _remark = '{* ' + _getRemark(_nodep) + ' *}';
        
        return '{if ($'+parent+'.recTypeID=="'+rectypeId+'")}'+_remark+ ' \n  \n{/if}'+ _remark +' \n';  

    }    
    
    //
    // NEW
    //    
    function _addIfOperator2(_nodep, varname, language_handle = '', file_handle = ''){
        let _remark = '{* ' + _getRemark(_nodep) + ' *}';
        let inner_val = language_handle !== '' ? language_handle : "{$"+varname+"}";
        inner_val = file_handle !== '' ? file_handle : inner_val;
        return "\n{if ($"+varname+")}"+_remark+"\n\n   "+inner_val+" \n\n{/if}\n"+_remark+" {* you can also add {/else} before {/if}} *}\n";
    }
    //
    // NEW
    //
    function _addMagicLoopOperator2(_nodep, varname, language_handle = '', file_handle = ''){
        
        let _remark = '{* ' + _getRemark(_nodep) + ' *}';
        
        let codes = varname.split('.');
        let field = codes[codes.length-1];
        
        
        let loopname = (_nodep.data.type=='enum')?'ptrloop':'valueloop';
        let getrecord = (_nodep.data.type=='resource')? ('{$'+field+'=$heurist->getRecord($'+field+')}') :'';

        if(!window.hWin.HEURIST4.util.isempty(language_handle)){
            language_handle = '\n\t' + language_handle.replace('replace_id', field) + '\n';
        }
        if(!window.hWin.HEURIST4.util.isempty(file_handle)){
            file_handle = '\n\t' + file_handle.replace('replace_id', field) + '\n';
        }
        
        if(codes[1]=='Relationship'){
            insertGetRelatedRecords();
            
            return '{foreach $r.Relationships as $Relationship name='+loopname+'}'+_remark +'\n\n{/foreach}'+_remark;
            
        }else{
            return '{foreach $'+varname+'s as $'+field+' name='+loopname+'}'+_remark
                    +'\n\t'+getrecord+'\n'  //' {* '+_remark + '*}'
                    + language_handle
                    + file_handle
                    +'\n{/foreach} '+_remark;
        }

    }
    //
    //
    //
    function _getRemark(_nodep){

        let s = _nodep.title;
        let key = _nodep.key;

        if(key=='label' || key=='term' || key=='code' || key=='conceptid' || key=='internalid' || key=='desc'){
            s = _nodep.parent.title + '.' + s;
        }

        s =  window.hWin.HEURIST4.util.stripTags(s);
        if(_nodep.parent && _nodep.parent.data.codes ){ //!_nodep.parent.isRootNode()
            s = window.hWin.HEURIST4.util.stripTags(_nodep.parent.title) + ' >> ' + s;
        }
        return s;
    }
    
    //
    // NEW
    //
    function _addVariable2(_nodep, varname, insertMode, language_handle = '', file_handle = ''){
        
        let res= '';
        
        let remark = _getRemark(_nodep);

        if(insertMode==0){ //variable only

            let inner_val = language_handle !== '' ? language_handle : "{$"+varname+"}";
            inner_val = file_handle !== '' ? file_handle : inner_val;
            res = inner_val + " {*" +  remark + "*}";

        }else if (insertMode==1){ //label+field

            res = _nodep.title+": {$"+varname+"}";  //not used

        }else if(_nodep){ // insert with 'wrap' fumction which provides URL and image handling
            let dtype = _nodep.data.type;
            res = '{wrap var=$'+varname;
            if(!(_nodep.data.code && _nodep.data.code.indexOf('Relationship')==0))
            {
                if(window.hWin.HEURIST4.util.isempty(dtype) || _nodep.key === 'recURL'){
                    res = res + ' dt="url"';
                }else if(dtype === 'geo'){
                    res = res + '_originalvalue dt="'+dtype+'"';
                }else if(dtype === 'date'){
                    res = res + '_originalvalue dt="date" mode="0" calendar="native"';
                    
                    remark = remark+' mode: 0-simple,1-full,2-all fields; calendar: native,gregorian,both';
                    
                }else if(dtype === 'file'){
                    res = res + '_originalvalue dt="'+dtype+'"';
                    res = res + ' width="300" height="auto" auto_play="0" show_artwork="0"';
                }
            }
            res = res +'}{*' +  remark + '*}';
        }

        return (res+((insertMode==0)?' ':'\n'));
    }


    /*
    * insertPattern: inserts a pattern/template/example for different actions into the editor text
    *
    */
    function _insertPattern(pattern) {

        _closeInsertPopup();
        
        let _text = '';
        let textedit = document.getElementById("edTemplateBody");

        if(!pattern){
            pattern = parseInt(document.getElementById("selInsertPattern").value);
        }

        // Update these patterns in synch with pulldown in showReps.html
        switch(pattern) {

            case 1: // Heading for record type
                _text= "{* Section heading *} \n" +
                "\n{* Make sure your search results are sorted by record type. \n" +
                "   Move the following instruction near the top of the file: {$lastRecordType = 0}\n" +
                "   Modify the sorting variable and the test according to your needs.*} \n\n" +

                "{if $lastRecordType != $r.recTypeID} {$lastRecordType = $r.recTypeID}\n" +
                "      <hr> \n" +
                "      <p/> \n" +
                "      <h1>{$r.recTypeName}</h1> {* Replace this with whatever you want as a heading *} \n" +
                "{/if} {* end of section heading *} " +
                "\n\n";
                break;

            case 2: // simple table
                _text='\n\n{* Put narrow specified-width columns at the start and any long text columns at the end *} \n' +
                '<table style="text-align:left;margin-left:20px;margin-top:2px;" border="0" cellpadding="2"> \n' +
                '   <tr> \n' +
                '      <td style="width: 50px"> {$r.recID}    </td> \n' +
                '      <td style="width:400px"> {$r.recTitle} </td> \n' +
                '      <td style=" "> </td> \n' +
                '      <td style=" "> </td> \n' +
                '      <td style=" "> </td> \n' +
                '   </tr> \n' +
                '</table>' +
                '\n\n';
                break;

            case 3: // information on first element of a loop
                _text='\n\n{* Information before first element of a loop (nothing output if loop is empty). \n' +
                '   Place this before the fields output in the loop. Replace \'valueloop\' with the name of the loop. *}\n\n' +
                '{if $smarty.foreach.valueloop.first}\n' +
                ' \n' +
                ' {* Add the information you want output before the first iteration here *}}\n' +
                ' \n' +
                '{/if}' +
                '\n\n';
                break;

            case 4: // information on first element of a loop
                _text='\n\n{* Information after last element of a loop (nothing output if loop is empty). \n' +
                '   Place this after the fields output in the loop. Replace \'valueloop\' with the name of the loop. *}\n' +
                '{if $smarty.foreach.valueloop.last}\n' +
                ' \n' +
                ' {* Add the information you want output after the last iternation here *}}\n' +
                ' \n' +
                '{/if}' +
                '\n\n';
                break;

            case 5: // using a div to control spacing
                _text=  '\n\n{* You can use style= on divs, spans, table rows and cells etc. to control spacing *} \n' +
                '<div style="padding-top:5px; margin-left:10px;"> \n' +
                '   {* Put content here *} \n' +
                '</div>' +
                '\n\n';
                break;

            case 6: //
                _text='\n\n   TO DO   ' +
                ' content to add here ' +
                '\n\n';
                break;


            case 99: // outer records loop
                _text=  '\n\n{*------------------------------------------------------------*} \n' +
                '{foreach $results as $r} {* Start records loop, do not remove *} \n' +
                '{$r = $heurist->getRecord($r)}\n'+
                '{*------------------------------------------------------------*} \n' +
                ' \n\n' +
                '  {* put the data you want output for each record here - insert the *} \n' +
                '  {* fields using the tree of record types and fields on the right *} \n' +
                ' \n' +
                '<br> {* line break between each record *} \n' +
                ' \n' +
                '{*------------------------------------------------------------*} \n' +
                '{/foreach} {* end records loop, do not remove *} \n' +
                '{*------------------------------------------------------------*} ' +
                '\n\n';
                break;

            case 98: // add record link
                
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                    title: 'Select type and other parameters for new record',
                    height: 520, width: 540,
                    get_params_only: true,
                    onClose: function(context){
                        if(context && !window.hWin.HEURIST4.util.isempty(context.RecAddLink)){
                            _text = '\n<a href="'+context.RecAddLink+'&guest_data=1" target="_blank">Add Record</a>\n';
                            insertAtCursor(_text); // insert text into editor
                        }
                    },
                    default_palette_class: 'ui-heurist-publish'                                        
                    }
                );    
                
                return;
                
            default:
                _text = 'It appears that this choice has not been implemented. Please ask the Heurist team to add the required pattern';
        }

        insertAtCursor(_text); // insert text into editor

    } // _insertPattern



    let insertPopupID, insert_ID;
    

    //
    // Hide insert variable popup
    //
    function _closeInsertPopup(){
        if(_add_variable_dlg && _add_variable_dlg.dialog('instance')){
            _add_variable_dlg.dialog('close');
        }
    }

    //
    // NEW
    //
    function _showInsertPopup2( _nodep, elt ){

        // show hide         
        let no_loop = (_nodep.data.type=='enum' || _nodep.key.indexOf('rec_')==0 || 
                    (_nodep.data.code && _nodep.data.code.indexOf('Relationship')==0));
        let show_languages = _nodep.key=='term' || _nodep.key=='desc';
        let show_file_data = _nodep.data.type=='file';
        let h;
        if(no_loop){
            h = 260;
        }else{
            h = 360;
        }

        let field_name = _nodep.data.name;
        if(window.hWin.HEURIST4.util.isempty(field_name)){
            let codes = _nodep.data.code.split(':');

            if(codes.length >= 3){
                let rtyid = codes[codes.length-3];
                let dtyid = codes[codes.length-2];

                field_name = $Db.rst(rtyid, dtyid, 'rst_DisplayName');
            }
        }
        if(window.hWin.HEURIST4.util.isempty(field_name)){
            field_name = 'field';
        }
        
        if(_add_variable_dlg && _add_variable_dlg.dialog('instance')){
            _add_variable_dlg.dialog('close');
        }
        
        function __on_add(event){

            let $ele = $(event.target);
            if($ele.is('strong')){
                $ele = $ele.parent();
            }

            let $dlg2 = $ele.parents('.ui-dialog-content');
            let insertMode = $dlg2.find("#selInsertMode").val();
            let language = $dlg2.find('#selLanguage').val();
            let file_data = $dlg2.find('#selFileData').val();
            
            let bid = $ele.attr('id');
            
            let inloop = (bid=='btn_insert_loop')?1:(bid.indexOf('_loop')>0?2:0);
            
            _insertSelectedVars2(_nodep, inloop, bid.indexOf('_if')>0, insertMode, language, file_data);
            //_add_variable_dlg.dialog('close');
        }
        
        // init buttons
        let $ele_popup = $('#insert-popup');
        $ele_popup.find('#btn_insert_var').attr('onclick',null).button()
            .off('click')
            .click(__on_add);
        $ele_popup.find('#btn_insert_if').attr('onclick',null).button()
            .off('click')
            .click(__on_add);
            
        $ele_popup.find('#btn_insert_loop').attr('onclick',null).button()
            .off('click')
            .click(__on_add);
        $ele_popup.find('#btn_insert_loop_var').attr('onclick',null).button()
            .off('click')
            .click(__on_add);
        $ele_popup.find('#btn_insert_loop_if').attr('onclick',null).button()
            .off('click')
            .click(__on_add);
            
        $ele_popup.find('#selInsertModifiers').attr('onchange',null)
            .off('change')
            .on('change', function __on_add(){
        
                let $dlg2 = $(event.target).parents('.ui-dialog-content');
                let sel = $dlg2.find("#selInsertModifiers")
                let modname = sel.val();

                if(modname !== ''){
                    insertAtCursor("|"+modname);
                }

                sel.val('');
            });

        let $langSel = $ele_popup.find('#selLanguage');
        if($langSel.find('option').length == 1){ // fill select with available languages

            let lang_opts = window.hWin.HEURIST4.ui.createLanguageSelect();
            $langSel.html($langSel.html() + lang_opts);
        }
        $langSel.val(''); // reset
        h = !show_languages && !show_file_data ? h - 10 : h;
        
        _add_variable_dlg = window.hWin.HEURIST4.msg.showElementAsDialog(   
            {element: $ele_popup[0],
            modal: false,
            width:450,
            height:h,
            resizable: false,
            title:`Insert ${field_name}`,
            buttons:null,
            open: null,
            beforeClose:null,
            close:function(){
                return true; //remove
            },
            position:{my:'top left',at:'bottom left', of: elt},
            borderless: false,
            default_palette_class:null});

        let grid_temp_cols = (!show_languages && !show_file_data ? '' : '75px ') + '130px 180px'

        _add_variable_dlg.find('.insert-field-grid').css({'display': 'grid', 'grid-template-columns': '100%'});
        _add_variable_dlg.find('.insert-field-grid > div:not(.header)').css({'display': 'grid', 'grid-template-columns': grid_temp_cols, 'margin': '5px 0'});
        _add_variable_dlg.find('.insert-field-grid > div.header').css({'display': 'grid', 'grid-template-columns': grid_temp_cols, 'margin': '15px 0 5px'});

        _add_variable_dlg.find('button').css({
            'padding': '0px', 
            'width': '100px', 
            'height': '25px'
        });
        _add_variable_dlg.find('button').not('#btn_insert_var, #btn_insert_loop_var').css('margin-left', '10px');
        _add_variable_dlg.find('#btn_insert_var, #btn_insert_loop_var').css('width', '110px');

        if(no_loop){
            _add_variable_dlg.find('.ins_isloop').hide();
        }else{
            _add_variable_dlg.find('.ins_isloop').show();
        }

        if(show_languages){

            _add_variable_dlg.find('.language_row, .empty_ele').show();
            _add_variable_dlg.find('.file_row').hide();
        }else if(show_file_data){

            _add_variable_dlg.find('.language_row').hide();
            _add_variable_dlg.find('.file_row, .empty_ele').show();
        }else{

            _add_variable_dlg.find('.language_row, .file_row, .empty_ele').hide();
        }
    }
    
    //
    // NEW
    //
    function _insertSelectedVars2( _nodep, inloop, isif, _insertMode, language_code, file_field ){

        let textedit = document.getElementById('edTemplateBody'),
        _text = "",
        _inloop = inloop,
        _varname = '',
        rectypeId = 0,
        key = '',
        _getrec = '',
        language_handle = '',
        file_handle = '';
        
        if(_nodep){
            
            key = _nodep.key;
/*            
code:  rt:dtid   like   10:lt134:12:ids3
key 

id            : "r.f15.f26.term"
labelonly     : "Term"
parent_full_id: "r.f15.f26"
parent_id     : "f26"
this_id       : "term"          

  
*/

                
                _varname = '';
                
                    let codes = _nodep.data.code;
                    if(!codes) codes = key;
                    
                    let prefix = 'r';
                    
                        codes = codes.split(':');
                        
                        if(key.indexOf('rec_')===0){
                            _varname = key.replace('_','');
                        }
                        
                        if(codes[0]=='Relationship'){ //_nodep.data.type == 'relationship'){
                            insertGetRelatedRecords();
                            if(_varname!='') {
                                if(inloop!=1) inloop = 2; //Relationship will be without prefix $r
                            }else if(codes[1]){
                                _varname = codes[1];
                            }
                            
                            _varname = codes[0]+(_varname!=''?('.'+_varname):'');
                        }else{

                            let offset = 3;
                            let lastcode = codes[codes.length-1];
                                                
                            if(_nodep.data.type == 'rectype'){
                                rectypeId = _nodep.data.rtyID_local;
                                _varname = '';
                            }else if(key.indexOf('rec_')!==0)
                            {
                                if(key=='label' || key=='term' || key=='code' || key=='conceptid' || key=='internalid' || key=='desc'){ //terms
                                    if( inloop!=1 ){
                                        _varname = ('.'+key);
                                    }
                                    offset = 4;
                                    lastcode = codes[codes.length-2];
                                }else if (lastcode.indexOf('lt')==0) {
                                    lastcode = lastcode.substring(2);
                                }
                                _varname = 'f'+lastcode+_varname;    
                            }
/*
0: "5"   rt
1: "lt15"   -5
2: "10"  rt
3: "lt240"  -3
4: "48"  rt
5: "title"

0: "5"
1: "lt15"  -4
2: "10"
3: "263"
4: "Term"
*/                            
                            if(codes.length>3){ //second level (isif && codes.length==2) || 
                                
                                let parent_key = '';
                                let pkeys = [];
                                while(codes.length-offset>0){
                                    let pkey = codes[codes.length-offset];
                                    if(pkey.indexOf('lt')==0){ //resource
                                        pkey = 'f'+pkey.substring(2);
                                    }else{
                                        pkey = 'f'+pkey;
                                    }
                                    offset = offset + 2;
                                    //prefix = prefix + '.' + pkey;
                                    
                                    pkeys.unshift(pkey);
                                    
                                    if(!parent_key) parent_key = pkey;
                                    if(pkeys.length==2) break;
                                }
                                if(pkeys.length<2) pkeys.unshift(prefix);
                                prefix = pkeys.join('.');
                                //prefix = prefix + '.' + pkey;
                                //prefix = parent_key; 
                                
                                if( inloop<2 ){
                                    
                                    //r.
                                    _getrec = '{$' + parent_key + '=$heurist->getRecord($'+prefix+')}\n';
                                    let _getrec2 = '{$' + parent_key + '=$heurist->getRecord($'+parent_key+')}\n';
                                    //find if above cursor code already has such line             
                                    if(findAboveCursor(_getrec) || findAboveCursor(_getrec2)) {
                                            _getrec = '';
                                    }
                                    
                                    //_getrec = _getrec+''+_getrec2;
                                    
                                    
                                    _varname = parent_key +  (_varname?('.' + _varname):'');
                                }
                                prefix = '';
                            }
                        }
                    
                    // 0 - outside loop
                    // 1 - insert loop operator
                    // 2 - in loop
                    if( inloop<2 ){
                        _varname = prefix + ((prefix && _varname)?'.':'') + _varname;

                        if(language_code && language_code != '' && (key == 'term' || key == 'desc')){

                            let id_fld = _varname.replace(`.${key}`, '.id');
                            let fld = (inloop==1) ? 'replace_id.id' : id_fld;
                            let trm_fld = key == 'term' ? 'label' : 'desc';

                            language_handle = `{$translated_label = $heurist->getTranslation("trm", $${fld}, "${trm_fld}", "${language_code}")} {* Get translated label *}\n\n`
                                + (inloop==1 ? '\n\t' : '') + `{$translated_label} {* Print translated label *}`;
                        }else if(file_field && _nodep.data.type == 'file'){

                            let fld = (inloop==1) ? 'replace_id' : _varname;
                            file_handle = `{$file_details = $${fld}_originalvalue|file_data:${file_field}} {* Get the requested field *}\n\n`
                                + (inloop==1 ? '\n\t' : '') + `{$file_details} {* Print the field *}`;
                        }
                    }
                    
                    _nodep.data.varname = _varname;
                    //_nodep.data.key = _varname;
                
            
            let cursorIndent = 0;
            
            if( inloop==1 ){
                
                //** _getrec = '';
                _text = _addMagicLoopOperator2(_nodep, _varname, language_handle, file_handle);
                
            }else if(isif){
                
                if(rectypeId>0){
                    _text = _insertRectypeIf2(_nodep, _varname, rectypeId);
                    cursorIndent = -7;
                }else{
                    _text = _addIfOperator2(_nodep, _varname, language_handle, file_handle);    
                }
                
                
            }else{
                _text = _addVariable2(_nodep, _varname, _insertMode, language_handle, file_handle);
            }
        
        
            if(_text!=='')    {
                _text = _getrec + _text;
                insertAtCursor(_text);
            }
        }
    }



    function ucwords (str) {
        return (str + '').replace(/(?:^([a-z\u00E0-\u00FC]))|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
            return $1.toUpperCase();
        });
    }

    //
    // returns false if token not found in current and lines until first "if" or "for" above
    //
    function findAboveCursor(token) {
        
        //for codemirror
        let crs = codeEditor.getCursor();
        //calculate required indent
        let l_no = crs.line;
        let line = "";
        
        token = token.trim();
        
        while (l_no>0){
            line = codeEditor.getLine(l_no);
            l_no--;
            if(line.trim()=='') continue;

            if(line.indexOf(token)>=0){
                return true;   
            }
        
            if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                return false;   
            }
        }
        
        return false;   
    }
    
    //
    //
    //
    function insertGetRelatedRecords(){
        
        //find main loop and {$r = $heurist->getRecord($r)}
        let l_count = codeEditor.lineCount();
        let l_no = 0, k = -1;
            
        while (l_no<l_count){
            let line = codeEditor.getLine(l_no);
            if(line.indexOf('$heurist->getRelatedRecords($r)}')>0){
                return;//already inserted
            }
            l_no++;
        }
        
        l_no = 0;    
        while (l_no<l_count){
            let line = codeEditor.getLine(l_no);
            k = line.indexOf('$heurist->getRecord($r)}');
            if(k>=0){
                
                let s = '\n{$r.Relationships = $heurist->getRelatedRecords($r)}\n'+
                '{$Relationship = (count($r.Relationships)>0)?$r.Relationships[0]:array()}\n';
                
                codeEditor.replaceRange(s, {line:l_no, ch:k+24}, {line:l_no, ch:k+24});
                
                break;
            }
            l_no++;
        }
    }
    

    /**
    * Inserts into code mirror editor at cursor position
    */
    function insertAtCursor(myValue) {

        //for codemirror
        let crs = codeEditor.getCursor();
        //calculate required indent
        let l_no = crs.line;
        let line = "";
        let indent = 0;

        while (line=="" && l_no>0){
            line = codeEditor.getLine(l_no);

            l_no--;
            if(line=="") continue;

            indent = CodeMirror.countColumn(line, null, codeEditor.getOption("tabSize"));

            if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                indent = indent + 2;
            }
        }

        let off = new Array(indent + 1).join(' ');

        myValue = "\n" + myValue;
        myValue = myValue.replace(/\n/g, "\n"+off);

        codeEditor.replaceSelection(myValue);

        if(myValue.indexOf("{if")>=0 || myValue.indexOf("{foreach")>=0){
            crs.line = crs.line+2;
            crs.ch = indent + 2;
            //crs.ch = 0;
        }else{
            crs = codeEditor.getCursor();
        }

        codeEditor.setCursor(crs);
        setTimeout(function(){codeEditor.focus();},200);

    }



    /*
    // Inserts text into textarea at cursor
    // Not used however it may be useful in future
    // utility function - move to utils ?
    //
    function insertAtCursor_fortextarea(myField, myValue, isApplyBreaks, cursorIndent) {

        let scrollTop = myField.scrollTop;

        //IE support
        if (document.selection) {
            myField.focus();
            sel = document.selection.createRange();
            sel.text = myValue;
        }
        //MOZILLA/NETSCAPE support
        else if (myField.selectionStart || myField.selectionStart == '0') {
            let startPos = myField.selectionStart;
            let endPos = myField.selectionEnd;
            myField.value = myField.value.substring(0, startPos)
            + myValue
            + myField.value.substring(endPos, myField.value.length);

            myField.selectionStart = endPos + myValue.length + cursorIndent;
            myField.selectionEnd = endPos + myValue.length + cursorIndent;

        } else {
            myField.value += myValue;
        }


        if(isApplyBreaks){
            myField = ApplyLineBreaks(myField, myField.value);
        }

        myField.scrollTop = scrollTop;

        setTimeout(function() {myField.focus(); }, 500);
    }

    //
    // apply line breaks
    //
    function ApplyLineBreaks(oTextarea, text) {

        if (oTextarea.wrap) {
            oTextarea.setAttribute("wrap", "off");
        }
        else {
            oTextarea.setAttribute("wrap", "off");
            let newArea = oTextarea.cloneNode(true);
            newArea.value = text; //oTextarea.value;
            oTextarea.parentNode.replaceChild(newArea, oTextarea);
            oTextarea = newArea;
        }

        let strRawValue = text;// oTextarea.value;
        oTextarea.value = "";
        let k = text.indexOf("\\n");
        while (k>=0){
            oTextarea.value += text.substr(0, k) + "\n"; // + text.substr(k+2);
            text = text.substr(k+2);
            k = text.indexOf("\\n");
        }
        oTextarea.value += text;
        oTextarea.setAttribute("wrap", "");

        return oTextarea;
    }
    */


    //
    //
    //
    function _insertModifier(modname){
        insertAtCursor("|"+modname);
    }

    function _onResize(newwidth){

        if(newwidth>0){
            let newval = newwidth>605?'36px':'75px';
            if(top_repcontainer!=newval){
                top_repcontainer = newval;
                if(!_iseditor){
                    document.getElementById("rep_container").style.top = top_repcontainer;
                }
            }
        }
    }
    
    //
    // save current output to file (not used)
    //            
    function _saveOutput(){
        if( _currentQuery )
        {
            let template_file = $('#selTemplates').val(); //current template
            if(window.hWin.HEURIST4.util.isempty(template_file)) { return; }

            let squery = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest( _currentQuery, true );
            squery = squery.replace('"','%22');
            
            let surl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php?"+
                squery + '&publish=2&debug=0&output=heurist_report.html&template='+template_file;
                
            $("#btnRepSave").hide();    
            window.hWin.HEURIST4.util.downloadURL(surl, function(){
                $("#btnRepSave").show();
            });
        }
    }
    
    function _onReportPublish(){

        let template_file = $('#selTemplates').val();
        if(window.hWin.HEURIST4.util.isempty(template_file)) return;
        
        let mode = window.hWin.HAPI4.get_prefs('showSelectedOnlyOnMapAndSmarty'); //not used
        let squery = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest( _currentQuery, true );

        let q = 'hquery='+encodeURIComponent(squery)+'&template='+template_file;
        
        
        let params = {mode:'smarty'};
        params.url_schedule = window.hWin.HAPI4.baseURL + "export/publish/manageReports.html?"
                                    + q + "&db="+window.hWin.HAPI4.database;

        params.url = window.hWin.HAPI4.baseURL + "viewers/smarty/?"+ //showReps.php
            squery.replace('"','%22') + '&publish=1&debug=0&template='+encodeURIComponent(template_file);
        
        
        window.hWin.HEURIST4.ui.showPublishDialog( params );
        
        
    }

    
    


    //public members
    let that = {

        
    
        progressInterval: null,    

        /*setQuery: function(q_all, q_sel, q_main){
        if(q_all) squery_all = q_all;
        squery_sel = q_sel;
        squery_main = q_main;
        },*/

        processTemplate: function (template_file){
            _reload(template_file);
        },


        // recordset is JSON array   {"resultCount":23,"recordCount":23,"recIDs":[8005,11272,8599.....]}
        assignRecordsetAndQuery: function(recordset, query_request, facet_value = null){
            _currentRecordset = recordset;
            _currentQuery = query_request;
            _facet_value = facet_value;
        },

        isNeedSelection: function(){
            return _needSelection;
        },

        getQueryMode: function(){
            return _sQueryMode;
        },

        //NOT USED
        setQueryMode: function(val){
            let isChanged = _sQueryMode != val;
            _sQueryMode = val;
            


            if(document.getElementById('cbUseAllRecords1')){
                document.getElementById('cbUseAllRecords1').value = val;
            }
            if(document.getElementById('cbUseAllRecords2')){
                document.getElementById('cbUseAllRecords2').value = val;
            }

            if (isChanged && needReload) {
                _reload(null);
            }
        },

        generateTemplate:  function (name){
            if(_needSelection){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Please select some records to allow generation of the template.',
                    error_title: 'Missing selected record(s)'
                });
            }else{
                _needListRefresh = true;
                _generateTemplate(name, true);
            }
        },

        showEditor:  function (template_file, needRefresh){
            if(_needSelection){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Please select some records in the search results before editing the template.',
                    error_title: 'Missing selected record(s)'
                });
            }else{
                _needListRefresh = (needRefresh===true);
                _showEditor(template_file);
            }
        },
        
        onReportPublish: function(){
            _onReportPublish();
        },

        initEditorMode: function(template_file, template_body){
            _initEditorMode(template_file, template_body);
        },
        
        initSnippetEditor: function(template_body, rty_IDs, onChangeEvent){
            _initSnippetEditor(template_body, rty_IDs, onChangeEvent);    
        },

        operationEditor:  function (action){
            _operationEditor(action);
        },

        doExecuteFromEditor: function(){
            _doExecuteFromEditor();
        },

        doExportTemplate: function(){
            _doExportTemplate();
        },

        saveOutput: function(){
            _saveOutput();
        },

        baseURL:  function (){
            return window.hWin.HAPI4.baseURL;
        },

        originalFileName:  function (val){
            _originalFileName = val;
            //_setOrigName(val);
        },

        onResize: function(newwidth){
            _onResize(newwidth);
        },

        insertPattern: function(){ 
            _insertPattern();
        },
        
        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        }
    };

    // init on load
    _init();
    return that;
}

/*
$(function(){
* this swallows backspace keys on any non-input element.
* stops backspace -> back
let rx = /INPUT|SELECT|TEXTAREA/i;

$(document).bind("keydown keypress", function(e){
if( e.which == 8 ){ // 8 == backspace
if(!rx.test(e.target.tagName) || e.target.disabled || e.target.readOnly ){
e.preventDefault();
}
}
});
});
*/
