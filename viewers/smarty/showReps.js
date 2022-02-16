/**
* showReps.js : insertion of various forms of text into smarty editor
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


//aliases
/**
* UserEditor - class for pop-up edit group
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0509
*/

function ShowReps() {

    var _className = "ShowReps",
    _originalFileName,

    _needListRefresh = false, //if true - reload list of templates after editor exit
    _keepTemplateValue,
    _needSelection = false,
    _sQueryMode = "all",

    needReload = true,
    codeEditor = null,

    _currentRecordset = null,
    _currentQuery = null,

    _add_variable_dlg = null;
    
    
    var top_repcontainer = '36px';
    
    var progressInterval = null;

    /**
    *  show the list of available reports
    *  #todo - filter based on record types in result set
    */
    function _updateTemplatesList(context) {


        var sel = document.getElementById('selTemplates'),
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


        var all_templates = []        
        if(context && context.length>0){

            for (var i=0; i<context.length; i++){
                    all_templates.push({key:context[i].filename, title:context[i].name});
                    
                    if(keepSelIndex<0 && _currentTemplate==context[i].filename){
                        keepSelIndex = sel.length-1;
                    }
            } // for


            var sel2 = window.hWin.HEURIST4.ui.createSelector(sel, all_templates);
            sel2.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;
            
            window.hWin.HEURIST4.ui.initHSelect(sel2, false);
            
            if(sel.selectedIndex>=0){
                _reload(context[sel2.selectedIndex].filename);
            }
        }
        
        _setLayout(true, false);
    }

    /**
    * Inserts template output into container
    */
    function _updateReps(context) {

        window.hWin.HEURIST4.msg.sendCoverallToBack();

        var iframe = document.getElementById("rep_container_frame");
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(context);
        iframe.contentWindow.document.close();
        
        //document.getElementById('rep_container').innerHTML = context;

        _needSelection = (context && context.indexOf("Select records to see template output")>0);
    }


    /**
    * Initialization
    *
    * Reads GET parameters and requests for map data from server
    */
    function _init() {
        
        if(true){
            _sQueryMode = "all";
            $('#cbUseAllRecords1').hide();
            $('#cbUseAllRecords2').hide();
        }else{
            _sQueryMode = window.hWin.HAPI4.get_prefs('showSelectedOnlyOnMapAndSmarty');
            document.getElementById('cbUseAllRecords2').value = _sQueryMode;
            document.getElementById('cbUseAllRecords1').value = _sQueryMode;
        }

        _reload_templates();


        window.onbeforeunload = _onbeforeunload;
        
        //aftert load show viewer only
        //_setLayout(true, false); it is called in _updateTemplatesList
        _onResize(this.innerWidth);
    }

    /**
    * loads list of templates
    */
    function _reload_templates(){
        
        var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
        var callback = _updateTemplatesList;
        var request = {mode:'list', db:window.hWin.HAPI4.database};
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
    }


    function _getSelectedTemplate(){

        var sel = document.getElementById('selTemplates');
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

        var squery = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest( _currentQuery, true );

        if(window.hWin.HEURIST4.util.isempty(squery) ||  (squery.indexOf("&q=")<0) || 
            (squery.indexOf("&q=") == squery.length-3)) {
                
            if(_sQueryMode=="selected"){
                _updateReps("<div class='wrap'><div id='errorMsg'><span>No Records Selected</span></div></div>");
            }else{
                _updateReps("<b><font color='#ff0000'>Select saved search or apply a filter to see report output</font></b>");
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

        var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php";
        var request = null;
        var session_id = Math.round((new Date()).getTime()/1000);

        if(_currentRecordset!=null){
            //new approach to support H4
            if(window.hWin.HEURIST4.util.isnull(template_file)){
                template_file = _getSelectedTemplate();
            }
            if(window.hWin.HEURIST4.util.isnull(template_file)){
                return;
            }
            //if(_currentRecordset['recIDs']) _currentRecordset = _currentRecordset['recIDs'];

            request = {db:window.hWin.HAPI4.database, template:template_file, recordset:JSON.stringify(_currentRecordset), session:session_id};

        }else{
            return; //use global recordset only
            squery = _getQueryAndTemplate(template_file, false); //NOT USED
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

        if(!(session_id>0)) {
             _hideProgress();
             return;
        }
       
        var progressCounter = 0;        
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        $('#toolbardiv').hide();
        $('#progressbar_div').show();
        $('body').css('cursor','progress');
        
        $('#progress_stop').button().on({click: function() {
            
            var request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                _hideProgress();
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    console.log(response);                   
                }
            });
        } }, 'text');
        
        var pbar = $('#progressbar');
        var progressLabel = pbar.find('.progress-label').text('');
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
        
        progressInterval = setInterval(function(){ 
            
            var request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                
//console.log(response);                
                if(!response || response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    _hideProgress();
                    //console.log(response+'  '+session_id);                   
                }else{
                    
                    var resp = response?response.split(','):[0,0];
                    
                    if(resp && resp[0]){
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
        
        if(progressInterval!=null){
            
            clearInterval(progressInterval);
            progressInterval = null;
        }
        $('#progressbar_div').hide();
        $('#toolbardiv').show();
        
    }

    function _onbeforeunload() {
        if(_iseditor && _keepTemplateValue && _keepTemplateValue!=codeEditor.getValue()){
            return "Template was changed. Are you sure you wish to exit and lose all modifications?!!!";
        }
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
                window.hWin.HEURIST4.msg.showMsgErr('No template generated');
                return;
            }

            if(isLoadGenerated){

                    var text = [


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
                        '<br/> {* line break between each record *}',
                        '',
                        '{*------------------------------------------------------------*}',
                        '{/foreach} {* end records loop, do not remove *}',
                        '{*------------------------------------------------------------*}',
                        '',
                        '<hr/>',
                        '<h2>End of report</h2> {* Text here appears at end of report *} ',
                        '</html>'];


                    var k;
                    var res = "";
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
        
        var rtSelect = $('#rectype_selector').css('max-width','150px');
        var $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), true );
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

        codeEditor.setValue(content);

        setTimeout(function(){
                    $('div.CodeMirror').css('height','100%').show();
                    codeEditor.refresh();
                    _keepTemplateValue = codeEditor.getValue();
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

        var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";

        _originalFileName = template_file;

        var request = {mode:'get', db:window.hWin.HAPI4.database, template:template_file};

        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
        function(obj) {
            if (obj  &&  obj.error) {
                window.hWin.HEURIST4.msg.showMsgErr(obj.error);
            }else{
                _onGetTemplate(obj);
            }
        }, 'auto'
        );

        _generateTemplate(template_file, null, false);
    }


    /**
    * Convert template to global
    */
    function _doExportTemplate() {

        var istest = false;
        
        var dbId = Number(window.hWin.HAPI4.sysinfo['db_registeredid']);
        if(dbId > 0){

            var template_file = $('#selTemplates').val();
            if( window.hWin.HEURIST4.util.isempty(template_file) ) return;
            
            var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";

            var request = { mode:'serve',db:window.hWin.HAPI4.database, dir:0, template:template_file };

            if(istest){
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null,
                 function(obj) {
                    if (obj  &&  obj.error) {
                        window.hWin.HEURIST4.msg.showMsgErr(obj.error);
                    }else{
                        _updateReps('<xmp>'+obj+'</xmp>');
                    }
                 },'auto');
            }else{
                var squery = 'db='+window.hWin.HAPI4.database+'&mode=serve&dir=0&template='+template_file;
                //template.gpl
                window.hWin.HEURIST4.util.downloadURL(baseurl+'?'+squery);
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr('Database must be registered to allow translation of local template to global template.');
        }
    }



    /**
    * Close editor
    * @param mode 0 - just close, 1 - save as (not close),  2 - save, close and execute, 3 - delete and close
    */
    function _operationEditor(mode, unconditionally) {

        if(mode>0){

            var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
            var request = {db: window.hWin.HAPI4.database};
            template_file = null;

            if(mode<3)
            { //save
                template_file = jQuery.trim(document.getElementById("edTemplateName").innerHTML);

                if(mode==1){ //save as - get new name
                
                    if(unconditionally){
                    
                        document.getElementById("edTemplateName").innerHTML = unconditionally;    
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

                var template_body = codeEditor.getValue();// document.getElementById("edTemplateBody").value;
                if(template_body && template_body.length>10){
                    request['mode'] = 'save';
                    request['template'] = template_file;
                    request['template_body'] = template_body;

                    _keepTemplateValue = template_body;
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr('The template body is suspiciously short. No operation performed.');
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

                var modeRef = mode;
                var alwin;

                function __onOperEnd(context){
                    
                    $('*').css('cursor', 'default');
                    window.hWin.HEURIST4.msg.sendCoverallToBack();

                    if(!window.hWin.HEURIST4.util.isnull(context))
                    {
                        var mode = context.ok;
                        //if(mode==="delete"){
                        if(modeRef===3){ //delete
                            //todo!!!! - remove template from the list and clear editor
                            _reload_templates();
                        }else if(template_file!=null){
                            _originalFileName = template_file;//_onGetTemplate(obj);

                            window.hWin.HEURIST4.msg.showMsgFlash("Template '"+template_file+"' has been saved", 1000);
                            
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
                            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg.dialog( 'close' );},
                            'Discard': function() {
                                _setLayout(true, false);
                                var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                $dlg.dialog( 'close' );},
                            'Cancel':function() {
                                var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
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

        var replevel = document.getElementById('cbErrorReportLevel').value;
        if(replevel<0) {
            document.getElementById('cbErrorReportLevel').value = 0;
            replevel = 0;
        }
        var debug_limit = document.getElementById('cbDebugReportLimit').value;


        /*
        if(document.getElementById('cbDebug').checked){
        replevel = 1;
        }else if (document.getElementById('cbError').checked){
        replevel = 2;
        }*/

        var template_body = codeEditor.getValue();

        if(template_body && template_body.length>10){


            var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php";

            var request = {};

            if(_currentRecordset!=null){
                //if(_currentRecordset['recIDs']) _currentRecordset = _currentRecordset['recIDs'];
                request['recordset'] = JSON.stringify(_currentRecordset);
            }else{
                request = window.hWin.HEURIST4.util.cloneJSON(_currentQuery);
            }

            if(debug_limit>0){
                request['limit'] = debug_limit;
            }

            request['replevel'] = replevel;
            request['template_body'] = template_body;
            
            window.hWin.HEURIST4.msg.bringCoverallToFront($(document).find('body'));
            
            _showProgress();

            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                function(context) {
                    _hideProgress();
                    _updateReps(context);
                }, 'auto');

        }else{
            window.hWin.HEURIST4.msg.showMsgErr('Nothing to execute');
        }
    }



    var layout = null, _isviewer, _iseditor, _kept_width=-1;

    /**
    * onclick handler that solves the Safari issue of not responding to onclick
    *
    * @param ele
    */
    function clickworkaround(ele)
    {
        if(navigator.userAgent.indexOf('Safari')>0){
            //Safari so create a click event and dispatch it
            var event = document.createEvent("HTMLEvents");
            event.initEvent("click", true, true);
            ele.dispatchEvent(event);
        }else{
            // direct dispatch
            ele.click();
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
            var restLayout = top.document.getElementById("resetPanels_Smarty"+(iseditor?"On":"Off"));
            if(restLayout){
                clickworkaround(restLayout);
            }
        }

        _isviewer=isviewer;
        _iseditor=iseditor;

        
        document.getElementById("toolbardiv").style.display = (iseditor) ?"none" :"block";
        document.getElementById("rep_container").style.top = (iseditor) ?"0px" :top_repcontainer;
        //document.getElementById("editorcontainer").style.display = (iseditor) ?"block" :"none";
        
        var layout_opts = {
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
        

        var mylayout = $('#layout_container').layout(layout_opts);
        
        if(iseditor){
            mylayout.show('north');    
            var dh =  this.innerHeight;
            mylayout.sizePane('north', dh*0.7);
        }else{
            mylayout.hide('north');    
        }
        
        
        
        //reload templates list
        if(_needListRefresh && !iseditor){
            _needListRefresh = false;
            _reload_templates();
        }

        var container_ele = $(window.hWin.document).find('div[layout_id="FAP2"]');
        
        //resize global cardinal layout
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
            
            var sel = document.getElementById('selTemplates');
            if(sel.selectedIndex>=0){
                var template_file = sel.options[sel.selectedIndex].value;
                _reload(template_file);
            }
        }
    }


    //
    //
    //
    function _loadRecordTypeTreeView(){
      
        var rty_ID = $('#rectype_selector').val();

        //load treeview
        var treediv = $('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        if(!(rty_ID>0)){
            treediv.text('Please select a record type from the pulldown above');
            return;
        }
        
        treediv.empty();
        
        //generate treedata from rectype structure
        var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, rty_ID, 
                        ['ID','title','typeid','typename','modified','url','tags','all'] );

        treedata[0].expanded = true; //first expanded


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
                var node = data.node;
                var parentcode = node.data.code; 
                var rectypes = node.data.rt_ids;

                var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, 
                    rectypes, ['ID','title','typeid','typename','modified','url','tags','all'], parentcode );
                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                    //that._assignSelectedFields();
                    },500);
            },
            select: function(e, data) {
            },
            click: function(e, data){
                
                var ele = $(e.originalEvent.target);
                if(ele.is('a')){
                    
                    if(ele.text()=='insert'){
                        //insert-popup
                        _showInsertPopup2( data.node, ele );
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
                var node = data.node;
                if(true){
                    var $span = $(node.span);
                    var new_title = node.title;//debug + '('+node.data.code+'  key='+node.key+  ')';
                    
                    
                    if(node.data.type!='enum'){
                        var op = '';
                        if(node.data.type=='resource' || node.title=='Relationship'){ //resource
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
                    
                    $span.find("> span.fancytree-title").html(new_title);
                }
            }            
        });
        
    }

    //
    // NEW if for root rectypes
    //
    function _insertRectypeIf2(_nodep, parent, rectypeId){
        
        var _remark = '{* ' + _getRemark(_nodep) + ' *}';
        
        return '{if ($'+parent+'.recTypeID=="'+rectypeId+'")}'+_remark+ ' \n  \n{/if}'+ _remark +' \n';  

    }    
    
    //
    // NEW
    //    
    function _addIfOperator2(_nodep, varname){
        var _remark = '{* ' + _getRemark(_nodep) + ' *}';
        return "\n{if ($"+varname+")}"+_remark+"\n\n   {$"+varname+"} \n\n{/if}\n"+_remark+" {* you can also add {/else} before {/if}} *}\n";
    }
    //
    // NEW
    //
    function _addMagicLoopOperator2(_nodep, varname){
        
            var _remark = '{* ' + _getRemark(_nodep) + ' *}';
            
            var codes = varname.split('.');
            var field = codes[codes.length-1];
            
            
            var loopname = (_nodep.data.type=='enum')?'ptrloop':'valueloop';
            var getrecord = (_nodep.data.type=='resource')? ('{$'+field+'=$heurist->getRecord($'+field+')}') :'';
            
            if(codes[1]=='Relationship'){
                insertGetRelatedRecords();
                
                return '{foreach $r.Relationships as $Relationship name='+loopname+'}'+_remark +'\n\n{/foreach}'+_remark;
                
            }else{
                return '{foreach $'+varname+'s as $'+field+' name='+loopname+'}'+_remark
                        +'\n\t'+getrecord+'\n'  //' {* '+_remark + '*}'
                        +'\n{/foreach} '+_remark;
            }
            
    }
    //
    //
    //
    function _getRemark(_nodep){

        var s = _nodep.title;
        var key = _nodep.key;

        if(key=='term' || key=='code' || key=='conceptid' || key=='internalid'){
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
    function _addVariable2(_nodep, varname, insertMode){
        
        var res= '';
        
        var remark = _getRemark(_nodep);

        if(insertMode==0){ //variable only

            res = "{$"+varname+"}{*" +  remark + "*}";

        }else if (insertMode==1){ //label+field

            res = _nodep.title+": {$"+varname+"}";  //not used

        }else if(_nodep){ // insert with 'wrap' fumction which provides URL and image handling
            var dtype = _nodep.data.type;
            res = '{wrap var=$'+varname;
            if(!(_nodep.data.code && _nodep.data.code.indexOf('Relationship')==0))
            {
                if(window.hWin.HEURIST4.util.isempty(dtype) || _nodep.key === 'recURL'){
                    res = res + ' dt="url"';
                }else if(dtype === 'ge  o'){
                    res = res + '_originalvalue dt="'+dtype+'"';
                }else if(dtype === 'file'){
                    res = res + '_originalvalue dt="'+dtype+'"';
                    res = res + ' width="300" height="auto" auto_play="0" show_artwork="0"';
                }else{
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
        
        var _text = '';
        var textedit = document.getElementById("edTemplateBody");

        //pattern = document.getElementById("selInsertPattern").value;

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
                '<br/> {* line break between each record *} \n' +
                ' \n' +
                '{*------------------------------------------------------------*} \n' +
                '{/foreach} {* end records loop, do not remove *} \n' +
                '{*------------------------------------------------------------*} ' +
                '\n\n';
                break;

            default:
                _text = 'It appears that this choice has not been implemented. Please ask the Heurist team to add the required pattern';
        }

        insertAtCursor(_text); // insert text into editor

    } // _insertPattern



    var insertPopupID, insert_ID;
    

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
        
        // init buttons
        

        // show hide         
        var no_loop = (_nodep.data.type=='enum' || _nodep.key.indexOf('rec_')==0 || 
                    (_nodep.data.code && _nodep.data.code.indexOf('Relationship')==0))
        if(no_loop){
            h = 250;
        }else{
            h = 350;
        }
        
        if(_add_variable_dlg && _add_variable_dlg.dialog('instance')){
            _add_variable_dlg.dialog('close');
        }
        
        function __on_add(event){
            var $dlg2 = $(event.target).parents('.ui-dialog-content');
            var insertMode = $dlg2.find("#selInsertMode").val();
            
            var bid = $(event.target).attr('id');
            
            var inloop = (bid=='btn_insert_loop')?1:(bid.indexOf('_loop')>0?2:0);
            
            _insertSelectedVars2(_nodep, inloop, bid.indexOf('_if')>0, insertMode);
            //_add_variable_dlg.dialog('close');
        }
        
        $ele_popup = $('#insert-popup');
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
            .change(function __on_add(){
        
                var $dlg2 = $(event.target).parents('.ui-dialog-content');
                var sel = $dlg2.find("#selInsertModifiers")
                var modname = sel.val();
                sel.val('');
                insertAtCursor("|"+modname);        
            });
        
        
        _add_variable_dlg = window.hWin.HEURIST4.msg.showElementAsDialog(   
            {element: $ele_popup[0],
            modal: false,
            width:450,
            height:h,
            resizable: false,
            title:'Insert field',
            buttons:null,
            open: null,
            beforeClose:null,
            close:function(){
                return true; //remove
            },
            position:{my:'top left',at:'bottom left', of: elt},
            borderless: false,
            default_palette_class:null});
    
        if(no_loop){
                _add_variable_dlg.find('.ins_isloop').hide();
        }else{
                _add_variable_dlg.find('.ins_isloop').show();
        }
    }
    
    
    
    //
    // NEW
    //
    function _insertSelectedVars2( _nodep, inloop, isif, _insertMode ){

        var textedit = document.getElementById('edTemplateBody'),
        _text = "",
        _inloop = inloop,
        _varname = '',
        rectypeId = 0,
        key = '',
        _getrec = '';
        
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
/*
            if(inloop_level==2){
                
                var gp_node = _findNodeById(_nodep.parent_full_id);
                if(gp_node && gp_node.data){
                    _varname = gp_node.data.parent_id+'.'+_nodep.parent_id+'.'+_nodep.this_id;
                }else{
                    _varname = _nodep.parent_id+"."+_nodep.this_id;
                }
                
            }else  inloop_level==1
*/            
            if (true) {
                
                _varname = '';
                
                if(false && _nodep.data.varname){
                    _varname = _nodep.data.varname;
                }else
                {
                    var codes = _nodep.data.code;
                    if(!codes) codes = key;
                    
                    var prefix = 'r';
                    
                    if(true){
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

                            var offset = 3;
                            var lastcode = codes[codes.length-1];
                                                
                            if(_nodep.data.type == 'rectype'){
                                rectypeId = _nodep.data.rtyID_local;
                                _varname = '';
                            }else if(key.indexOf('rec_')!==0)
                            {
                                if(key=='term' || key=='code' || key=='conceptid' || key=='internalid'){ //terms
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
                                
                                var parent_key = '';
                                var pkeys = [];
                                while(codes.length-offset>0){
                                    var pkey = codes[codes.length-offset];
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
                                    var _getrec2 = '{$' + parent_key + '=$heurist->getRecord($'+parent_key+')}\n';
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
                    }else{
                        _varname = key;
                    }
                    
                    // 0 - outside loop
                    // 1 - insert loop operator
                    // 2 - in loop
                    if( inloop<2 ){
                        _varname = prefix + ((prefix && _varname)?'.':'') + _varname;
                    }
                    
                    _nodep.data.varname = _varname;
                    //_nodep.data.key = _varname;
                }
                
            }//true

            
            var cursorIndent = 0;
            
            if( inloop==1 ){
                
                //** _getrec = '';
                _text = _addMagicLoopOperator2(_nodep, _varname);
                
            }else if(isif){
                
                if(rectypeId>0){
                    _text = _insertRectypeIf2(_nodep, _varname, rectypeId);
                    cursorIndent = -7;
                }else{
                    _text = _addIfOperator2(_nodep, _varname);    
                }
                
                
            }else{
                _text = _addVariable2(_nodep, _varname, _insertMode);
            }
        
        
            if(_text!=='')    {
                _text = _getrec + _text;
                insertAtCursor(_text);
            }
        }
    }
    
    //
    // inloop_level = 0 no loop
    //              = 1 parent is repeatable
    //              = 2 grandparent is repeatable
    //
    function _insertSelectedVars( varid, inloop, isif ){
        
        var inloop_level=2;
        if(inloop!=2){
            inloop_level = (inloop===true)?1:0;
        }

        if(insertPopupID){
            $(insertPopupID).dialog('close');
            insertPopupID = null;
        }

        if(varid==null){
            //top.HEURIST.util.closePopup(insertPopupID);
            insertPopupID = null;
            varid = insert_ID;
        }

        var textedit = document.getElementById("edTemplateBody"),
        _text = "",
        _varid = varid,
        _inloop = inloop,
        _varname = "",
        _getrec = '';

        var _nodep = _findNodeById(varid);

        if(_nodep){

            _nodep = _nodep.data;
/*            
id            : "r.f15.f26.term"
labelonly     : "Term"
parent_full_id: "r.f15.f26"
parent_id     : "f26"
this_id       : "term"            
*/
            if(inloop_level==2){
                
                var gp_node = _findNodeById(_nodep.parent_full_id);
                if(gp_node && gp_node.data){
                    _varname = gp_node.data.parent_id+'.'+_nodep.parent_id+'.'+_nodep.this_id;
                }else{
                    _varname = _nodep.parent_id+"."+_nodep.this_id;
                }
                
            }else if(inloop_level==1){
                
                _varname = _nodep.parent_id+"."+_nodep.this_id;
                
                //2016-03-22 IJ wants to insert loop instead of var. AO - I am strictly against this inconsistency!
                if(_nodep.this_id=='term' && !isif){
                    
                    _text = _addMagicLoopOperator(_nodep, _varname);
                    insertAtCursor(_text);
                    return;

                }else{
                    _varname = _nodep.parent_id+"."+_nodep.this_id;
                }
                
            }else{
                
                var gp_node = _findNodeById(_nodep.parent_full_id);
                if(gp_node && gp_node.data && gp_node.data.dtype=='resource'){
                    _getrec = '{$'+_nodep.parent_full_id+
                                    '=$heurist->getRecord($'+_nodep.parent_full_id+')}\n';
                    //find if above cursor code already has such line             
                    if(findAboveCursor(_getrec)) {
                        _getrec = '';
                    }
                }
                
                if(_nodep.parent_full_id=='r.Relationship'){
                    insertGetRelatedRecords();
                }

                
                _varname = _nodep.id;
            }


            if(isif){
                _text = _text + _getrec + _addIfOperator(_nodep, _varname);
            }else{
                _text = _text + _getrec + _addVariable(_nodep, _varname);
            }

            // for loop we also add the variable in the loop
            /*if(_inloop){
            _varname = _nodep.parent_id+"."+_nodep.this_id;
            _text = _text + _addVariable(_nodep, _varname);
            }*/

        }

        if(_text!=="")    {
            insertAtCursor(_text);
        }
    }



    function ucwords (str) {
        return (str + '').replace(/^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
            return $1.toUpperCase();
        });
    }

    //
    // returns false if token not found in current and lines until first "if" or "for" above
    //
    function findAboveCursor(token) {
        
        //for codemirror
        var crs = codeEditor.getCursor();
        //calculate required indent
        var l_no = crs.line;
        var line = "";
        
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
        var l_count = codeEditor.lineCount();
            l_no = 0, k = -1;
        while (l_no<l_count){
            line = codeEditor.getLine(l_no);
            if(line.indexOf('$heurist->getRelatedRecords($r)}')>0){
                return;//already inserted
            }
            l_no++;
        }
        
        l_no = 0;    
        while (l_no<l_count){
            line = codeEditor.getLine(l_no);
            k = line.indexOf('$heurist->getRecord($r)}');
            if(k>=0){
                
                var s = '\n{$r.Relationships = $heurist->getRelatedRecords($r)}\n'+
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
            var crs = codeEditor.getCursor();
            //calculate required indent
            var l_no = crs.line;
            var line = "";
            var indent = 0;

            while (line=="" && l_no>0){
                line = codeEditor.getLine(l_no);

                l_no--;
                if(line=="") continue;

                indent = CodeMirror.countColumn(line, null, codeEditor.getOption("tabSize"));

                if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                    indent = indent + 2;
                }
            }

            var off = new Array(indent + 1).join(' ');

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

        var scrollTop = myField.scrollTop;

        //IE support
        if (document.selection) {
            myField.focus();
            sel = document.selection.createRange();
            sel.text = myValue;
        }
        //MOZILLA/NETSCAPE support
        else if (myField.selectionStart || myField.selectionStart == '0') {
            var startPos = myField.selectionStart;
            var endPos = myField.selectionEnd;
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
            var newArea = oTextarea.cloneNode(true);
            newArea.value = text; //oTextarea.value;
            oTextarea.parentNode.replaceChild(newArea, oTextarea);
            oTextarea = newArea;
        }

        var strRawValue = text;// oTextarea.value;
        oTextarea.value = "";
        var k = text.indexOf("\\n");
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
            var newval = newwidth>605?'36px':'75px';
            if(top_repcontainer!=newval){
                top_repcontainer = newval;
                if(!_iseditor){
                    document.getElementById("rep_container").style.top = top_repcontainer;
                }
            }
        }
    }
    
    //
    // save current output to file
    //            
    function _saveOutput(){
        if( _currentQuery )
        {
            var template_file = $('#selTemplates').val(); //current template
            if(window.hWin.HEURIST4.util.isempty(template_file)) { return; }

            var squery = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest( _currentQuery, true );
            squery = squery.replace('"','%22');
            
            var surl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php?"+
                squery + '&publish=2&debug=0&output=heurist_report.html&template='+template_file;
                
            $("#btnRepSave").hide();    
            window.hWin.HEURIST4.util.downloadURL(surl, function(){
                $("#btnRepSave").show();
            });
        }
    }
    
    function _onReportPublish(){

        var template_file = $('#selTemplates').val();
        if(window.hWin.HEURIST4.util.isempty(template_file)) return;
        
        var mode = window.hWin.HAPI4.get_prefs('showSelectedOnlyOnMapAndSmarty'); //not used
        var squery = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest( _currentQuery, true );

        var q = 'hquery='+encodeURIComponent(squery)+'&template='+template_file;
        
        
        var params = {mode:'smarty'};
        params.url_schedule = window.hWin.HAPI4.baseURL + "export/publish/manageReports.html?"
                                    + q + "&db="+window.hWin.HAPI4.database;

        params.url = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php?"+
            squery.replace('"','%22') + '&publish=1&debug=0&template='+encodeURIComponent(template_file);
        
        
        window.hWin.HEURIST4.ui.showPublishDialog( params );
        
        
    }

    
    


    //public members
    var that = {

        /*setQuery: function(q_all, q_sel, q_main){
        if(q_all) squery_all = q_all;
        squery_sel = q_sel;
        squery_main = q_main;
        },*/

        processTemplate: function (template_file){
            _reload(template_file);
        },


        // recordset is JSON array   {"resultCount":23,"recordCount":23,"recIDs":[8005,11272,8599.....]}
        assignRecordsetAndQuery: function(recordset, query_request){
            _currentRecordset = recordset;
            _currentQuery = query_request;
        },

        isNeedSelection: function(){
            return _needSelection;
        },

        getQueryMode: function(){
            return _sQueryMode;
        },

        //NOT USED
        setQueryMode: function(val){
            var isChanged = _sQueryMode != val;
            _sQueryMode = val;
            //Hul.setDisplayPreference("showSelectedOnlyOnMapAndSmarty", _sQueryMode);


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
                window.hWin.HEURIST4.msg.showMsgErr('Please select some records to allow generation of the template.');
            }else{
                _needListRefresh = true;
                _generateTemplate(name, true);
            }
        },

        showEditor:  function (template_file, needRefresh){
            if(_needSelection){
                window.hWin.HEURIST4.msg.showMsgErr('Please select some records in the search results before editing the template.');
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

        //inserts selected variables
        insertSelectedVars:function(varid, inloop, isif){
            _insertSelectedVars(varid, inloop, isif);
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
var rx = /INPUT|SELECT|TEXTAREA/i;

$(document).bind("keydown keypress", function(e){
if( e.which == 8 ){ // 8 == backspace
if(!rx.test(e.target.tagName) || e.target.disabled || e.target.readOnly ){
e.preventDefault();
}
}
});
});
*/
