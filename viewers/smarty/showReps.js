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

    _variables, //object from server - record type tree
    _varsTree, //treeview object
    _needListRefresh = false, //if true - reload list of templates after editor exit
    _keepTemplateValue,
    _needSelection = false,
    _sQueryMode = "all",
    mySimpleDialog,
    needReload = true,
    codeEditor = null,
    infoMessageBox,
    _currentRecordset = null,
    _currentQuery = null,
    
    embed_dialog = null,
    mylayout = null;
    
    var top_repcontainer = '36px';
    
    var progressInterval = null;


    var handleYes = function() {
        _operationEditor(2);
        this.hide();
    };
    var handleNo = function() {
        _setLayout(true, false);
        this.hide();
    };

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

        infoMessageBox.hide();

        var iframe = document.getElementById("rep_container_frame");//document.getElementById("rep_container");
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(context);
        iframe.contentWindow.document.close();
        //div_rep.innerHTML = context;

        _needSelection = (context && context.indexOf("Select records to see template output")>0);
    }


    /**
    * Initialization
    *
    * Reads GET parameters and requests for map data from server
    */
    function _init() {
        _setLayout(true, false); //aftert load show viewer only

        if(true){
            _sQueryMode = "all";
            $('#cbUseAllRecords1').hide();
            $('#cbUseAllRecords2').hide();
        }else{
            _sQueryMode = window.hWin.HAPI4.get_prefs('showSelectedOnlyOnMapAndSmarty');
            document.getElementById('cbUseAllRecords2').value = _sQueryMode;
            document.getElementById('cbUseAllRecords1').value = _sQueryMode;
        }

        window.hSmarty.insertPattern = _insertPattern;

        _reload_templates();

        infoMessageBox  =
        new YAHOO.widget.SimpleDialog("simpledialog2",
            { width: "350px",
                fixedcenter: true,
                modal: false,
                visible: false,
                draggable: false,
                close: false,
                text: "some text"
        } );
        infoMessageBox.render(document.body);

        mySimpleDialog =
        new YAHOO.widget.SimpleDialog("simpledialog1",
            { width: "350px",
                fixedcenter: true,
                modal: true,
                visible: false,
                draggable: false,
                close: true,
                header: 'Warning!',
                text: "some text",
                icon: YAHOO.widget.SimpleDialog.WARNING,
                buttons: [
                    { text: "Save", handler: handleYes, isDefault:true },
                    { text:"Discard", handler: handleNo}
                ]
        } );
        mySimpleDialog.render(document.body);

        window.onbeforeunload = _onbeforeunload;
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

            //infoMessageBox.setBody("Execute template '"+template_file+"'. Please wait");
            infoMessageBox.setBody("<img src='../../common/images/loading-animation-black.gif'>");
            infoMessageBox.show();

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

                    //ApplyLineBreaks
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

            _variables = context;

            /* fille selection box with the list of rectypes
            var sel = document.getElementById("selRectype");
            //celear selection list
            while (sel.length>0){
            sel.remove(0);
            }

            var i;
            for (i in _variables){
            if(i!==undefined){

            option = document.createElement("option");
            option.text = _variables[i].name; //name of rectype
            option.value = _variables[i].id; //id of rectype
            try {
            // for IE earlier than version 8
            sel.add(option, sel.options[null]);
            }catch (ex2){
            sel.add(option,null);
            }
            }
            } // for

            sel.selectedIndex = 0;
            */
            _fillTreeView();

            _setLayout(true, true);

            _doExecuteFromEditor(); //execute at once
        }
        
        function __onRectypeTree(context){
            if(window.hWin.HEURIST4.util.isnull(context)){
                return;
            }
            _variables = context;
            _fillTreeView();
        }

        if(isLoadGenerated){
            __onGenerateTemplate([]);
        }

        
        
        var rtSelect = $('#rectype_selector').css('max-width','150px');
        var $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), true );
        $rec_select.change(function(event){
                    var selel = $(event.target).val();
                    if(selel>0){
                        var baseurl = window.hWin.HAPI4.baseURL + "common/php/recordTypeTree.php";
                        var request = {db:window.hWin.HAPI4.database, mode:'list', 'for':'smarty', rty_id:selel};
                        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __onRectypeTree);
                    }
        });
        /*$rec_select.hSelect.on({change: 
        function(event, data){
               var selval = data.item.value;
               $('#rectype_selector').val(selval);    
               
                if(selval>0){
                    var baseurl = window.hWin.HAPI4.baseURL + "common/php/recordTypeTree.php";
                    var request = {db:window.hWin.HAPI4.database, mode:'list', 'for':'smarty', rty_id:selval};
                    window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __onRectypeTree);
                }
        
         }
         });
        */
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
                            insertAtCursor(null, "");
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
    function _operationEditor(mode) {

        if(mode>0){

            var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
            var request = {db: window.hWin.HAPI4.database};
            template_file = null;

            if(mode<3)
            { //save
                template_file = jQuery.trim(document.getElementById("edTemplateName").innerHTML);

                if(mode==1){ //save as - get new name
                    template_file = jQuery.trim(prompt("Please enter new template name", template_file));
                    if (window.hWin.HEURIST4.util.isempty(template_file)){
                        return;
                    }
                    document.getElementById("edTemplateName").innerHTML = template_file;
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
                var r=confirm("Are you sure you wish to delete template '"+_originalFileName+"'?");

                if (r==true){
                    
                    request['mode'] = 'delete';
                    request['template'] = _originalFileName;
                    
                    _originalFileName = null;
                }else{
                    return;
                }
            }

            if(request!=null){

                var modeRef = mode;
                var alwin;

                function __onOperEnd(context){
                    
                    $('*').css('cursor', 'default');

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
                infoMessageBox.setBody("<img src='../../common/images/loading-animation-black.gif'>");
                infoMessageBox.show();
                
                 window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __onOperEnd, 'auto');
            }
        }

        if(mode===0){ //for close

            if(_keepTemplateValue!=codeEditor.getValue()){ //.get("edTemplateBody").value){

                /*var myButtons = [
                { text: "Save", handler: handleYes, isDefault:true },
                { text:"Discard", handler: handleNo}
                ];
                mySimpleDialog.cfg.queueProperty("buttons", myButtons);*/

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

            /*
            var r = true;
            if(_keepTemplateValue!=document.getElementById("edTemplateBody").value){
            r=confirm("Template was changed. Are you sure you wish to exit and lose all modifications?");
            }
            if (r==true){
            _setLayout(true, false);
            }
            */
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
            
            infoMessageBox.setBody("<img src='../../common/images/loading-animation-black.gif'>");
            infoMessageBox.show();
            
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
        document.getElementById("editorcontainer").style.display = (iseditor) ?"block" :"none";

        
        var units;
        if(isviewer && iseditor){
            
        //var body = $(document).find('body'); //this frame
        //var dim = {h:body.innerHeight(), w:body.innerWidth()};
            dh =  this.innerHeight;
            
            units = [
                { position: 'top', header: 'Editor', height: dh*0.7,
                    resize: true, body: 'editorcontainer', gutter:'5px', useShim: true, collapse: true},
                { position: 'center', body: 'viewercontainer', height: dh*0.3}
            ];
        }else if(isviewer){
            units = [
                { position: 'center', body: 'viewercontainer'}
            ];
        }else if(iseditor){
            units = [
                { position: 'center', body: 'editorcontainer'}
            ];
        }
        

        //
        //document.getElementById("layout").style.top = (iseditor) ?"0" :"25";

        //var el = document.getElementById('layout');
        layout = null;
        layout = new YAHOO.widget.Layout({
                units: units
        });
        
        /*layout = new YAHOO.widget.Layout('layout', {
        units: units
        });*/

        layout.render();
        layout.resize(true);


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



    function _selectAllChildren(parentNode) {
        var len = parentNode.children?parentNode.children.length:0;
        if( len > 0) {
            var index = 0;
            while(index < len) { // While it has children, select them and look if they have children too
                var child = parentNode.children[index];
                child.highlight();
                /*child.highlightState = parentNode.highlightState;
                if(parentNode.highlightState===1){
                child.highlight(); //mark the checkbox
                }else{
                child.unhighlight()
                }*/
                if(child.children.length > 0) {
                    _selectAllChildren(child.data);
                }
                index++;
            }
        }
    }



    /**
    * Finds node by term id
    */
    function _findNodeById(varid) {

        //internal
        function __doSearchById(node){
            return (node.data.id==varid);
        }

        var nodes = _varsTree.getNodesBy(__doSearchById);

        if(nodes){
            var node = nodes[0];
            return node;
        }else{
            return null
        }
    }



    //
    //
    //
    function _markAllChildren(varid){
        var node = _findNodeById(varid);
        if(node){
            _selectAllChildren(node);
        }
    }



    //
    //
    //
    function _clearAllSeelectionInTree(){
        //function for each node in _varsTree - removes highlight
        function __resetSelection(node){
            node.unhighlight();
            return false;
        }
        //loop all nodes of tree
        _varsTree.getNodesBy(__resetSelection);
        _varsTree.render();
    }



    /**
    *	Fills the given treeview with the list of variables
    * 	varnames  - contains vars - flat array and tree - tree array
    */
    function _fillTreeView (tv, varnames) {

        //create treeview
        if(window.hWin.HEURIST4.util.isnull(_varsTree)){
            _varsTree = new YAHOO.widget.TreeView("varsTree");
            _varsTree.singleNodeHighlight = true;
            _varsTree.selectable = false;
            _varsTree.subscribe("clickEvent",
                function() { // On click, select the term, and add it to the selected terms tree
                    this.onEventToggleHighlight.apply(this,arguments);
                    //var parentNode = arguments[0].node;
                    //_selectAllChildren(parentNode);
            });
        }


        var first_node = null;

        //internal function
        // parent_single - true if recrod pointer or enum is not repeatable field type
        //
        function __createChildren(parentNode, rectypeTree, parent_id, parent_full,
                         parent_single, grandparent_single) { // Recursively get all children
            //  __createChildren(topLayerNode, _variables[i], "r", prefix_id+".r");

            var term,
            childNode,
            child, id;

            var rectype_id = rectypeTree.rt_id;

            for(id in rectypeTree)
            {
                if(! (window.hWin.HEURIST4.util.isnull(id) || id=='rt_id' || id=='rt_name' || id=='termfield_name' ) ){

                    var label = null;

                    //cases
                    // common fields like recID, recTitle
                    // detail fields  fNNN: name
                    // detail fields ENUMERATION fNNN:array(termfield_name
                    // unconstained pointers fNNN:array(rt_name
                    // multi-contrained pointers fNNN:array(array(rt_id  - need another recursive loop

                    term = {};//new Object();
                    term.id = parent_full+"."+id; //fullid;
                    term.parent_full_id = parent_full;
                    term.parent_id = parent_id;
                    term.this_id = id;
                    term.label = '<div style="padding-left:10px;">'; //???arVars[0];

                    child = rectypeTree[id];

                    var is_record = ((typeof(child) == "object") &&
                        Object.keys(child).length > 0);
                        
                    var is_remark = (id=='remark');    

                    var is_multiconstrained = false;
                    var is_single = true; //non repeatable field

                    var vartype = term.this_id.substring(0,1);
                    var dtid = term.this_id.substring(1);

                    if(vartype=='f' && window.hWin.HEURIST4.util.isNumber(dtid) ){
                        term.dtype = $Db.dty(dtid, 'dty_Type');

                            var maxval = $Db.rst(rectype_id, dtid, 'rst_MaxValues');
                            is_single = (Number(maxval)===1);
                        
                    }else if (term.this_id=="Relationship") {
                        is_single = false;
                    }else if(term.this_id=='recTags'){
                        //is_single = false;
                    }


                    if(!is_record){ //simple - leaf - field or term id,label,code

                        label = child;
                        
                        if(is_remark){
                            term.label = term.label + '<i>' + label + '</i></div>';
                        }else
                        if(parent_single){   //parent_id=="r"){ // || parent_id.indexOf("r")==0){
                        
                            if(grandparent_single){
                        
                                term.label = term.label + label +
                                '&nbsp;<span class="insert-popup">(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.showInsertPopup(\''+
                                term.id+'\', 0, this)">insert</a>)</span>'+
                                '<span class="insert-intree">'+
                                '&nbsp;(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.insertSelectedVars(\''+
                                term.id+'\', false, false)">insert</a>'+
                                '&nbsp;<a href="javascript:void(0)" title="Insert IF operator for this variable" onClick="showReps.insertSelectedVars(\''+
                                term.id+'\', true, true)">if</a>)</span></div>';
                            } else {
                                
                                term.label = term.label + label +
                                '&nbsp;<span class="insert-popup">(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.showInsertPopup(\''+
                                term.id+'\', 2, this)">insert</a>)</span></div>';
                                
                            }
                        }else{
                            term.label = term.label + label +
                            '&nbsp;<span class="insert-popup">(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.showInsertPopup(\''+
                            term.id+'\', 1, this)">insert</a>)</span>'+
                            '<span class="insert-intree">'+
                            '&nbsp;(<a href="javascript:void(0)" title="Insert variable in repeat (without parent prefix)" onClick="showReps.insertSelectedVars(\''+
                            term.id+'\', true, false)">in</a>'+
                            '&nbsp;<a href="javascript:void(0)" title="Insert IF operator for variable in repeat (without parent prefix)" '+
                            'onClick="showReps.insertSelectedVars(\''+term.id+'\', true, true)">if</a>'+
                            '&nbsp;&nbsp;<a href="javascript:void(0)" title="Insert variable with parent prefix. To use outside the repeat" '+
                            'onClick="showReps.insertSelectedVars(\''+term.id+'\', false, false)">out</a>'+
                            '&nbsp;<a href="javascript:void(0)" title="Insert IF operator for variable with parent prefix. To use outside the repeat" '+
                            'onClick="showReps.insertSelectedVars(\''+term.id+'\', false, true)">if</a>)</span></div>';
                        }

                    }else{

                        if(child['termfield_name']) {
                            label = child['termfield_name'];
                            term.label = term.label + label; //<b> + '</b>';
                        }else{
                            if ( typeof(child[0]) == "string" ) {
                                is_multiconstrained = true;
                                label = child[0];
                            }else{
                                label = child['rt_name'];
                            }
                            term.label = term.label + '<b><i>' + label + '</i></b>';
                        }


                        if(!is_single){
                            term.label = term.label + '&nbsp;(<a href="javascript:void(0)" '+
                            'title="Insert FOREACH operator for this resource" onClick="showReps.insertSelectedVarsAsLoop(\''+term.id+'\')">repeat</a>)';
                        }
                        term.label = term.label + '</div>';
                    }

                    term.labelonly = label;

                    childNode = new YAHOO.widget.TextNode(term, parentNode, false); // Create the node
                    childNode.enableHighlight = false;

                    if(first_node==null) first_node = childNode;

                    if( is_multiconstrained ){

                        var k;
                        for(k=1; k<child.length; k++){

                            var rt_term = {};//new Object();
                            rt_term.id = term.id+"."+child[k].rt_id;  //record type

                            var _varname;
                            if(is_single){
                                _varname = term.parent_id+"."+term.id;
                            }else{
                                _varname = term.id;
                            }

                            rt_term.label =  '<div style="padding-left:10px;"><b>' + (child[k].rt_name?child[k].rt_name:child[k].rt_id+' name N/A') +
                            '</b>&nbsp;(<span '+
                            'title="Insert IF operator for this record type. It will allow to avoid an error if this type is missing in the result set" '+
                            'onClick="showReps.insertRectypeIf(\''+term.id+'\',' + child[k].rt_id +
                            ', \'' + (child[k].rt_name?child[k].rt_name.replace("'", "\\'"):'') + '\')">if</span>)';

                            //'onClick="showReps.insertRectypeIf(\''+term.this_id+'\', \'' + child[k].rt_name.replace("'", "\\'") + '\')">if</a>)';

                            rt_term.label =  rt_term.label + '</div>';

                            rt_term.href = "javascript:void(0)";

                            var rectypeNode = new YAHOO.widget.TextNode(rt_term, childNode, false);

                            __createChildren(rectypeNode, child[k], term.this_id, term.id, is_single, parent_single);
                        }

                    }else if( is_record ){ //next recursion
                        __createChildren(childNode, child, term.this_id, term.id, is_single, parent_single);
                    }
                }
            }//for
        }//__createChildren
        //end internal function



        //fill treeview with content
        var i, termid, term,
        tv = _varsTree,
        tv_parent = tv.getRoot(),
        varid,
        varnames;

        //clear treeview
        tv.removeChildren(tv_parent);

        //    _variables
        //   {rt_id: , rt_name, recID, recTitle .....
        //                  fNNN:'name',
        //                  fNNN:array(termfield_name: , id, code:  )
        //                  fNNN:array(rt_name: , recID ...... ) //unconstrained pointer
        //                  fNNN:array(rt_id: , rt_name, recID, recTitle ... ) //constrined pointer
        //


        for (i=0; i<_variables.length; i++){ //root elements - all rectypes in search result

            varnames = _variables[i];  // && _variables[i].id===recTypeID

            term = {};//new Object();
            term.id = _variables[i].rt_id;  //record type
            term.this_id = 'r';
            term.parent_id = null;
                                                                 
            term.label =  '<div style="padding-left:10px;"><b>' + _variables[i].rt_name +
            '</b>'+
            '&nbsp;(<span '+
            'title="Insert IF operator for this record type. It will allow to avoid an error if this type is missing in the result set" '+
            'onClick="showReps.insertRectypeIf(\'r\', ' + _variables[i].rt_id + ', \'' + _variables[i].rt_name.replace("'", "\\'") + '\')">if</span>)';
            
            //'onClick="showReps.insertRectypeIf(\'r\', \'' + _variables[i].rt_name.replace("'", "\\'") + '\')">if</a>)';

            term.label =  term.label + '</div>';

            term.href = "javascript:void(0)";

            var topLayerNode = new YAHOO.widget.TextNode(term, tv_parent, false); // Create the node

            __createChildren(topLayerNode, _variables[i], 'r', 'r', true, false);

            $('#varsTree').find('.ygtvlabel').css('margin-left',0);
        }//for  _variables

        //TODO tv.subscribe("labelClick", _onNodeClick);
        //tv.singleNodeHighlight = true;
        //TODO tv.subscribe("clickEvent", tv.onEventToggleHighlight);

        tv.render();
        if(first_node){
            first_node.focus();
            first_node.toggle();
        }
        $('.ygtvlabel').css('margin-left',0); //otherwise ff renders wrong
    }



    function _addIfOperator(nodedata, varname){
        //var varname = nodedata.id; //was prefix+nodedata.this_id
        var parname = _getVariableName(nodedata.parent_full_id);
        if(parname!=""){
            parname = parname + " >> ";
        }
        var remark = "{* "+ parname + _getVariableName(nodedata.id) + " *}"; //was this_id
        return "\n{if ($"+varname+")}"+remark+"\n\n   {$"+varname+"} \n\n{/if}\n"+remark+" {* you can also add {/else} before {/if}} *}\n";
    }

    //
    // add loop opeartor for for multi-value field
    //
    function _addMagicLoopOperator(_nodep, varname){
        
        var gp_node = _findNodeById(_nodep.parent_full_id);
        if(gp_node && gp_node.data){
            
            var parent_name = _getVariableName(_nodep.parent_full_id);
            var var_name = (parent_name + '>>' +_getVariableName(_nodep.id)).trim(); //was this_id
            
            return '{foreach $'+gp_node.data.parent_id+'.'+_nodep.parent_id+'s as $'+_nodep.parent_id+' name=valueloop}{*  multi-value field loop *}'
                    //+'\n\t'+_addVariable(_nodep, varname)
                    +'\n\t{$'+_nodep.parent_id+"."+_nodep.this_id+'} {* '
                            +var_name+' Note: $'+gp_node.data.parent_id+'.'+_nodep.parent_id+'.'+_nodep.this_id+' outputs first term only *}'
                    +'\n{/foreach}';
        }else{
            return _addVariable(_nodep, varname);
        }

    }
    //
    //
    //
    function _addVariable(nodedata, varname){
        var res= "",
        insertMode = $("#selInsertMode").val();

        //var varname = nodedata.id; //was prefix+nodedata.this_id
        var parname = _getVariableName(nodedata.parent_full_id);
        if(parname!=""){
            parname = parname + " >> ";
        }

        var remark = (parname + _getVariableName(nodedata.id)).trim(); //was this_id


        if(insertMode==0){ //variable only

            res = "{$"+varname+"}{*" +  remark + "*}";

        }else if (insertMode==1){ //label+field

            res = nodedata.labelonly+": {$"+varname+"}";

        }else{ // insert with 'wrap' fumction which provides URL and image handling
            var dtype = nodedata.dtype;
            res = '{wrap var=$'+varname;
            if(window.hWin.HEURIST4.util.isempty(dtype) || nodedata.this_id === 'recURL'){
                res = res + ' dt="url"';
            }else if(dtype === 'geo'){
                res = res + '_originalvalue dt="'+dtype+'"';
            }else if(dtype === 'file'){
                res = res + '_originalvalue dt="'+dtype+'"';
                res = res + ' width="300" height="auto" auto_play="0" show_artwork="0"';
            }else{
            }
            res = res +'}{*' +  remark + '*}';
        }

        return (res+((insertMode==0)?' ':'\n'));
    }



    //
    // root loop
    // TODO: This function no longer needed as root loop is simply one of the patterns inserted by next function
    //
    function _insertRootForEach(){
        var textedit = document.getElementById("edTemplateBody");
        var _text = '{foreach $results as $r}\n{$r = $heurist->getRecord($r)}\n\n  {if ($r.recOrder==0)}\n    \n  '+
        '{elseif ($r.recOrder==count($results)-1)}\n    \n  {else}\n  \n{/if}\n{/foreach}\n';      //{* INSERT YOUR CODE HERE *}

        insertAtCursor(textedit, _text, false, -12);
    }
    //



    /*
    * insertPattern: inserts a pattern/template/example for different actions into the editor text
    *
    */
    function _insertPattern(pattern) {

        if(insertPopupID){
            $(insertPopupID).dialog('close');
            insertPopupID = null;
        }

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

        insertAtCursor(null, _text, false, 0); // insert text into text buffer

    } // _insertPattern



    //
    // if for root rectypes
    //
    function _insertRectypeIf(parent, rectypeId, rectypeName){

        var textedit = document.getElementById("edTemplateBody");

        //var _text = '{if ($'+parent+'.recTypeName=="'+rectypeName+'")}\n\t\n{/if}\n';

        var _text = '{if ($'+parent+'.recTypeID=="'+rectypeId+'")}{* '+rectypeName+ ' *}\n  \n{/if}{* '+rectypeName+ ' *}\n';  //{* INSERT YOUR CODE HERE *}


        insertAtCursor(textedit, _text, false, -7);
    }



    var insertPopupID, insert_ID;
    
    // 
    // isloop_level - insert var in loop (trim parents)
    //  0 - no loop, 1 one level (parent is repeatable), 2 levels (grandparent is repreatable)
    //
    function _showInsertPopup( varid, isloop_level, elt ){

        window.hSmarty.insertSelectedVarAsLoop = _insertSelectedVarsAsLoop;
        window.hSmarty.insertVar = isloop_level==2?_insertSelectedVars_GP_repeatable:_insertSelectedVars;
        window.hSmarty.insertModifier = _insertModifier;

        if(isloop_level>0){
            $(".ins_isloop").show();
        }else{
            $(".ins_isloop").hide();
        }


        function __shownewpopup(){

            var ele = document.getElementById("insert-popup");

            //var pos = top.HEURIST.getPosition(elt);
            var scroll = document.getElementById("treeContainer").scrollTop;
            insert_ID = varid;

            var node = _findNodeById(varid);
            var title;
            if(node){
                title = ucwords(node.data.labelonly);
            }else{
                title = 'variable';
            }
            document.getElementById("insert-popup-header").innerHTML = 'Inserting: <b>'+title+'</b>';


            //show jquery dialog
            insertPopupID = $(ele).dialog({
                    autoOpen: true,
                    height: 320,
                    width: 400,
                    modal: false,
                    title: 'Insert field, test or pattern',
                    position: { my: "right top", at: "left bottom", of: $(elt) }
                });

        }//__shownewpopup


        if(insertPopupID){
            $(insertPopupID).dialog('close');
            insertPopupID = null;
        }
        __shownewpopup();

    }

    //
    // inserts selected variables
    // for special case: parent is not repeatable, grandparent is
    //
    function _insertSelectedVars_GP_repeatable(  varid, inloop, isif  ){
        
        _insertSelectedVars( varid, inloop?2:0, isif )
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
                    insertAtCursor(textedit, _text, false, 0);
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
            insertAtCursor(textedit, _text, false, 0);
        }
    }



    //
    function _insertSelectedVarsAsLoop( varid ){

        var textedit = document.getElementById("edTemplateBody"),
        insertMode = document.getElementById("selInsertMode").value,
        _text = "",
        _prefix = "";

        if(varid==null){
            
            insertPopupID = null;
            varid = insert_ID;
        }

        var _nodep = _findNodeById(varid);
        if(_nodep){
            var arr_name = (_nodep.data.this_id==="r") ?"results" : _nodep.data.parent_id+'.'+_nodep.data.this_id+'s';
            var item_name = (_nodep.data.this_id==="r") ?"r" : _nodep.data.this_id;
            var remark = "{* "+_getVariableName(_nodep.data.id)+" *}";
            var loopname = (_nodep.data.dtype=='enum')?'ptrloop':'valueloop';
            var getrecord = (_nodep.data.dtype=='resource')? ('{$'+item_name+'=$heurist->getRecord($'+item_name+')}') :'';
            
            if(_nodep.data.id=='r.Relationship'){
                insertGetRelatedRecords();
            }

            _text = "{foreach $"+arr_name+" as $"+item_name+" name="+loopname+"}"+remark+"\n  "+getrecord+"\n  "
            +"\n{/foreach}"+remark+"\n";
        }

        if(_text!=="")    {
            insertAtCursor(textedit, _text, false, -12);
        }

    }



    function ucwords (str) {
        return (str + '').replace(/^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
            return $1.toUpperCase();
        });
    }



    function _getVariableName(id){
        if(!window.hWin.HEURIST4.util.isempty(id)){

            if (id=="r"){
                return "";
            }else if (id=="Relationship") {
                return id;
            }else{

                var node = _findNodeById(id);
                if(node){
                    return ucwords(node.data.labelonly);
                }else{
                    return "notfound";
                }
            }

        }else{
            return "";
        }
    }



    function _getVariableName_old(id){
        if(!window.hWin.HEURIST4.util.isempty(id)){

            if (id=="r"){
                return "";
            }else if( id=="Relationship" || !window.hWin.HEURIST4.util.isNumber(id.substring(1)) ) {
                return id;
            }else{

                var type = id.substring(0,1);
                id  = id.substring(1);

                if(type=="r"){
                    return ucwords($Db.rty(id, 'rty_Name'));
                }else{
                    return ucwords($Db.dty(id, 'dty_Name'));
                }
            }

        }else{
            return "";
        }
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
                '{$r.Relationship = (count($r.Relationships)>0)?$r.Relationships[0]:array()}\n';
                
                codeEditor.replaceRange(s, {line:l_no, ch:k+24}, {line:l_no, ch:k+24});
                
                break;
            }
            l_no++;
        }
    }
    

    /**
    * myField, isApplyBreaks, cursorIndent - not used
    * TODO: What do the parameters do?
    */
    function insertAtCursor(myField, myValue, isApplyBreaks, cursorIndent) {

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



    //
    // utility function - move to utils ?
    // TODO: What is the difference btween this and the one above. What do the parameters do?
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
        /*
        var nEmptyWidth = oTextarea.scrollWidth;
        var nLastWrappingIndex = -1;
        for (var i = 0; i < strRawValue.length; i++) {
        var curChar = strRawValue.charAt(i);
        if (curChar == ' ' || curChar == '-' || curChar == '+')
        nLastWrappingIndex = i;
        oTextarea.value += curChar;
        if (oTextarea.scrollWidth > nEmptyWidth) {
        var buffer = "";
        if (nLastWrappingIndex >= 0) {
        for (var j = nLastWrappingIndex + 1; j < i; j++)
        buffer += strRawValue.charAt(j);
        nLastWrappingIndex = -1;
        }
        buffer += curChar;
        oTextarea.value = oTextarea.value.substr(0, oTextarea.value.length - buffer.length);
        oTextarea.value += "\n" + buffer;
        }
        }
        */
        oTextarea.setAttribute("wrap", "");

        return oTextarea;
    }



    //
    //
    //
    function _insertModifier(modname){

        insertAtCursor(null, "|"+modname, null, null);

        /*
        var textedit = document.getElementById("edTemplateBody");

        if (textedit.selectionStart || textedit.selectionStart == '0') {

        //1. detect that cursor inside the variable or wrap function {$ |  }  or {wrap  }
        var startPos = textedit.selectionStart;
        var endPos = textedit.selectionEnd;

        //find last { occurence before endPos
        var k = -1,
        pos1;

        do{
        pos1 = k;
        k = textedit.value.indexOf("{$", k+1);
        }while (k>=0 && k<endPos);

        if(pos1<0){
        window.hWin.HEURIST4.msg.showMsgErr('Place cursor inside variable entry: between {}');
        return;
        }

        var pos2 = textedit.value.indexOf("}", pos1);
        if(pos2<endPos){
        window.hWin.HEURIST4.msg.showMsgErr('Place cursor inside variable entry: between {}');
        return;
        }

        //2. insert modifier name in the end of {}
        textedit.value = textedit.value.substring(0, pos2)
        + "|"+modname
        + textedit.value.substring(pos2, textedit.value.length);
        }
        */
    }

    function _onResize(newwidth){

        var newval = newwidth>605?'36px':'60px';

        if(top_repcontainer!=newval){
            top_repcontainer = newval;
            if(!_iseditor){
                document.getElementById("rep_container").style.top = top_repcontainer;
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
        
        
/*
        if(!embed_dialog){
            embed_dialog = $('#smarty_publish_dialg');
            embed_dialog.load(window.hWin.HAPI4.baseURL+'viewers/smarty/repMenu.html', 
            function(){
                    _onReportPublish();
            });
            return;
        }
        
        
        //init and fill ui

        embed_dialog.find("#linkOpenInNewWindow").attr('href', surl);

        embed_dialog.find("#code-textbox1").val(surl);
        embed_dialog.find("#code-textbox2").val(
        '<script type="text/javascript" src="'+surl+'&mode=js"><'+'/script>'+
        '<noscript>'+
        '<iframe width="80%" height="70%" frameborder="0" src="'+surl+'">'+
        '</iframe>'+
        '</noscript>');
        
        var $dlg_pce;
        
        embed_dialog.find("#btnSetupSchedule").button().click(function(event){
            
                        $dlg_pce.dialog('close');
                        
                        $(event.target).off('click');

                        var q = 'hquery='+encodeURIComponent(squery)+'&template='+template_file;
                        var sURL = window.hWin.HAPI4.baseURL + "export/publish/manageReports.html?"
                                    + q + "&db="+window.hWin.HAPI4.database;

                        var body = $(window.hWin.document).find('body');
                        var dim = {h:body.innerHeight(), w:body.innerWidth()};

                        window.hWin.HEURIST4.msg.showDialog(sURL, 
                            {   "close-on-blur": false,
                                "no-resize": false,
                                height: 480,
                                width: dim.w*0.9,
                                callback: null
                        });
            
        });

        $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
                element: embed_dialog[0],
                height: 430,
                width: 600,
                title: window.hWin.HR('Publish report')
        });        
*/        
        
        
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

        //inserts selected variables inside the loop
        insertSelectedVarsAsLoop:function(varid){
            _insertSelectedVarsAsLoop(varid);
        },

        showInsertPopup:function(varid, isloop_level, elt){
            _showInsertPopup(varid, isloop_level, elt);
            return false;
        },

        //inserts selected variables
        insertSelectedVars:function(varid, inloop, isif){
            _insertSelectedVars(varid, inloop, isif);
        },

        insertRectypeIf:function(parent, rectypeId, rectypeName){
            _insertRectypeIf(parent, rectypeId, rectypeName);
        },
        insertRootForEach:function(){
            _insertRootForEach();
        },
        //clear all marked checkbox in variable tree
        clearAllSeelectionInTree:function(){
            _clearAllSeelectionInTree();
        },

        // mark - unmark child nodes
        markAllChildren:function(varid){
            _markAllChildren(varid);
        },

        insertModifier:function(modname){
            _insertModifier(modname);
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
