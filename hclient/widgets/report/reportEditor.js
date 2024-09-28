/**
* reportEditor.js - edit smarty report temaplate
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.reportEditor", $.heurist.baseAction, {

    // default options
    options: {
        height: 640,
        width:  1000,
        title:  'Edit Report Template',
        default_palette_class: 'ui-heurist-populate',
        actionName: 'reportEditor',
        path: 'widgets/report/',
        is_snippet_editor: false,
        
        keep_instance: true,
        template: null
    },
    
    usrPreferences:{
            insertForm_width:300, 
            insertForm_closed:false, 
            testForm_width:400, 
            testForm_closed:false,
            width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
            height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95
    },
    
    _keepTemplateValue:'',
    codeEditor: null,
    _currentTemplate: null,
    
    _create: function() {
        this._super();
        this.options.height = this.usrPreferences.height;
        this.options.width = this.usrPreferences.width;
        
        let that = this;
        this.options.beforeClose = function(){
            return that._beforeClose();
        };

    }, //end _create
    
    _init: function() {
        
        this._super();
        
        if( this._is_inited ){
            this._loadTemplate();
        }
    },
   
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){
        
        let res  = this._super();
        if(!res) return false;
        
        this.editSmarty = this._$('.editSmarty');
        
        let that = this;
        
                let layout_opts =  {
                    applyDefaultStyles: true,
                    maskContents: true,
                    //togglerContent_open:    '&nbsp;',
                    //togglerContent_closed:  '&nbsp;',
                    west:{
                        size: this.usrPreferences.insertForm_width,
                        maxWidth:500,
                        minWidth:300,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:80,  //height of toggler button
                        initHidden: false,   //show structure list at once 
                        initClosed: this.usrPreferences.insertForm_closed,
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.insertForm',   
                        onopen_start : function( ){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-west');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.westTogglerVertical').hide();
                        },
                        onclose_end : function( ){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-west');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.westTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.westTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 westTogglerVertical" style="width:200px;margin-top:220px;">Insert Patterns / Variables</span>').appendTo(tog);
                            }
                        },
                        onresize_end: function(){
                            //
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-e"></div>',
                    },
                    east:{
                        size: this.usrPreferences.testForm_width,
                        minWidth:400,
                        maxWidth:800,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:40,  //height of toggler button
                        initClosed: this.usrPreferences.testForm_closed,
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.testForm',   
                        onopen_start : function(){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-east');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.eastTogglerVertical').hide();
                        },
                        onclose_end : function(){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-east');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.eastTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.eastTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 eastTogglerVertical" style="width:200px;">Test Area</span>').appendTo(tog);
                            }
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-w"></div>',
                    },
                    center:{
                        minWidth:400,
                        contentSelector: '.editForm',
                        //pane_name, pane_element, pane_state, pane_options, layout_name
                        onresize_end : function(){
                            //that.handleTabsResize();                            
                        }    
                    }
                };

        this.editSmarty.layout(layout_opts); //.addClass('ui-heurist-bg-light')
        
        this._on(this._$('.closeInsertForm'), {click:()=>{this.editSmarty.layout().close("west");}});
        this._on(this._$('.closeTestForm'), {click:()=>{this.editSmarty.layout().close("east");}});
        
        //init Insert Pattern controls
        let rtSelect = this._$('#rectype_selector');
        let $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), true );
        this._on($rec_select,{change: this._loadRecordTypeTreeView});
        
        this._on(this._$('#btnInsertPattern').button(), {click:this._insertPattern});

        //init editor (load codeMirror)
        this._loadTemplate();
        
        
        //init test panel
        this._on(this._$('.btnStartTest').button({icons: { primary: 'ui-icon-circle-arrow-s'}}), 
            {click:()=>{this._doTest();}});
        
        
        
        return true;
    },
    
    //
    //
    //
    _doTest:function(){
        
        if(!(window.hWin.HAPI4.currentRecordset?.length()>0)){
            window.hWin.HEURIST4.msg.showMsgFlash('Perform search to get record set to test against');
            return;
        }
        
        let template_body = this.codeEditor.getValue();

        if(!(template_body?.length>10)){
            window.hWin.HEURIST4.msg.showMsgFlash('Nothing to execute. Define report code');
            return;            
        }
        
        
        let replevel = document.getElementById('cbErrorReportLevel').value;
        if(replevel<0) {
            document.getElementById('cbErrorReportLevel').value = 0;
            replevel = 0;
        }
        let debug_limit = document.getElementById('cbDebugReportLimit').value;
        
        if(debug_limit<0){
            debug_limit = 2000;
        }
        let recset = {recIDs:window.hWin.HAPI4.currentRecordset.getIds().slice(0, debug_limit-1)};

        let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php";
        let request = {db:window.hWin.HAPI4.database, 
                       replevel: replevel,
                       template_body:template_body,
//                       limit: debug_limit,  
                       recordset:JSON.stringify(recset)}
        
        let that = this;
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._$('.testForm'));
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    if(response.message){ //error in php code
                        that._updateReps( response.message );    
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);                       
                    }
                    
                }else{
                    that._updateReps( response );
                }
            }, 'auto');
        
        
    },
    
    //
    //
    //
    _updateReps: function(context) {
            if(context == 'NAN' || context == 'INF' || context == 'NULL'){
                context = 'No value';
            }
            let txt = (context && context.message)?context.message:context
            
            let iframe = this._$('.testForm').find('iframe')[0];
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write( txt );
            iframe.contentWindow.document.close();
    },

    //
    //
    //
    _loadTemplate: function(){    

        if(this._currentTemplate!=this.options.template){
            this._currentTemplate = this.options.template;

            let that = this;
            window.hWin.HAPI4.SystemMgr.reportAction({mode:'get', template:this._currentTemplate}, 
                function(response){
                    that._initEditor(response.message);
            });

        }
    },

    
    _initEditor: function(content){
    
//console.log(content);

        let that = this;
        
        if(this.codeEditor==null){
            
            //!window.hWin.HEURIST4.util.isFunction($(document.body)['CodeMirror'])
            if(typeof CodeMirror !== 'function' )
            {
                
                let path = window.hWin.HAPI4.baseURL + 'external/codemirror-5.61.0/';
                let scripts = [ 
                                'lib/codemirror.js',
                                'mode/xml/xml.js',
                                'mode/javascript/javascript.js',
                                'mode/css/css.js',
                                'mode/htmlmixed/htmlmixed.js',
                                'mode/smarty/smarty.js',
                                'mode/smartymixed/smartymixed.js'
                ];
                
                $.getMultiScripts2(scripts, path)
                .then(function() {  //OK! widget script js has been loaded
                    that._initEditor( content );
                }).catch(function(error) {
                    window.hWin.HEURIST4.msg.showMsg_ScriptFail();
                });
                
                return;
            }

                this._$('.editForm').empty().css({overflow:'none',padding:0});
            
                this.codeEditor = CodeMirror( this._$('.editForm')[0], {
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
                            that._insertAtCursor('');
                        }
                    },
                    onFocus:function(){},
                    onBlur:function(){}
                });
        }//codeMirror init

        let using_default = false;
        if(window.hWin.HEURIST4.util.isempty(content)){
            //if(this._currentTemplate!=this.options.template){
            //      this._loadTemplate();
            //      return;
            //}
            
            content = "{ }";
            using_default = true;
        }

        this.codeEditor.setValue(content);
        this._keepTemplateValue = content;

        setTimeout(function(){
            $('div.CodeMirror').css('height','100%').show();
            $('div.CodeMirror .CodeMirror-scroll').css('padding-top', '5px');
            that.codeEditor.refresh();
            //that._keepTemplateValue = that.codeEditor.getValue();

            if(using_default){
                that.codeEditor.setCursor({line: 0, char: 0});
            }
        },1000);
    },
    
    
    _insertPattern: function(){
        
        let pattern_id = Number(this._$('#selInsertPattern').val());
    
        this._closeInsertPopup();
        
        let _text = '';

        // Update these patterns in synch with pulldown in showReps.html
        switch(pattern_id) {

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
                let that = this;        
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                    title: 'Select type and other parameters for new record',
                    height: 520, width: 540,
                    get_params_only: true,
                    onClose: function(context){
                        if(context && !window.hWin.HEURIST4.util.isempty(context.RecAddLink)){
                            _text = '\n<a href="'+context.RecAddLink+'&guest_data=1" target="_blank">Add Record</a>\n';
                            that._insertAtCursor(_text); // insert text into editor
                        }
                    },
                    default_palette_class: 'ui-heurist-publish'                                        
                    }
                );    
                
                return;
                
            default:
                _text = 'It appears that this choice has not been implemented. Please ask the Heurist team to add the required pattern';
        }

        this._insertAtCursor(_text); // insert text into editor
        
    },

    _loadRecordTypeTreeView: function(){
        
        let rty_ID = this._$('#rectype_selector').val();

        //load treeview
        let treediv = this._$('.rtt-tree');
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

        if(this.options.is_snippet_editor){
            //hide root - record type title
            treedata = treedata[0];
        }
        
        let that = this;

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
                        if(that.options.is_snippet_editor){
                            that._insertSelectedVars2(data.node, 0, false, 0);
                        }else{
                            //insert-popup
                            that._showInsertPopup2( data.node, ele );
                        }
                    }else{
                        that._closeInsertPopup();
                        
                        if(ele.text()=='repeat'){
                            that._insertSelectedVars2( data.node, 1, false );
                        }else if(ele.text()=='if'){
                            that._insertSelectedVars2( data.node, 0, true );
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
        
        
    },
    
    _insertAtCursor: function(myValue){

        
        //for codemirror
        let crs = this.codeEditor.getCursor();
        //calculate required indent
        let l_no = crs.line;
        let line = "";
        let indent = 0;

        while (line=="" && l_no>0){
            line = this.codeEditor.getLine(l_no);

            l_no--;
            if(line=="") continue;

            indent = CodeMirror.countColumn(line, null, this.codeEditor.getOption("tabSize"));

            if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                indent = indent + 2;
            }
        }

        let off = new Array(indent + 1).join(' ');

        myValue = "\n" + myValue;
        myValue = myValue.replace(/\n/g, "\n"+off);

        this.codeEditor.replaceSelection(myValue);

        if(myValue.indexOf("{if")>=0 || myValue.indexOf("{foreach")>=0){
            crs.line = crs.line+2;
            crs.ch = indent + 2;
            //crs.ch = 0;
        }else{
            crs = this.codeEditor.getCursor();
        }

        this.codeEditor.setCursor(crs);
        let that = this;
        setTimeout(function(){that.codeEditor.focus();},200);
        
    },
    
    _closeInsertPopup: function(){
        
    },
    
    _insertSelectedVars2: function(){
        
    },
    
    _showInsertPopup2: function(){
        
    },
    
    isModified: function(){
        return (this._keepTemplateValue && this._keepTemplateValue!=this.codeEditor.getValue());  
    },
    

    _beforeClose: function() {
        if(this.isModified()){
        
            window.hWin.HEURIST4.msg.showMsgOnExit(window.hWin.HR('Warn_Lost_Data'),
                ()=>{this.closeDialog();}, //save
                ()=>{this._keepTemplateValue=false; this.closeDialog();}); //ignore and close
           
            //"Template was changed. Are you sure you wish to exit and lose all modifications?!!!";
            return false;
        }else{
            return true;
        }
    },
    

    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        
        res[0].text = window.hWin.HR('Close');
        
        res[1].text = window.hWin.HR('Save');
        res[1].disabled = null;
        
        res.splice(1,0,{text:window.hWin.HR('Save As'),
                    id:'btnDoAction2',
                    class:'ui-button-action',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction(); 
                    }}
                    );
        
        return res;
    },

    //
    //
    //
    doAction: function(){
            
        let request = {};

        let that = this;

        //save preferences in session
        /*
        window.hWin.HAPI4.SystemMgr.save_prefs(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){


                }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);      
                }
            });
        */    
    }
        
});

