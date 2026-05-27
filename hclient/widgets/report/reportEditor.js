/**
* @file reportEditor.js
* @brief Provides a widget for editing Smarty report templates.
* @fileOverview Provides a widget for editing Smarty report templates. This widget allows users to create, modify, and test Smarty templates
*
* used for generating reports within the Heurist system. It integrates
* CodeMirror for template editing and provides tools for inserting
* template variables and patterns, as well as a test environment
* to preview the report output with actual data.
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/* global CodeMirror */

/**
 * @widget heurist.reportEditor
 * @augments $.heurist.baseAction
 * @description
 * jQuery UI widget for editing Smarty report templates.
 * Provides an interface with a CodeMirror editor, tools for inserting
 * template variables and patterns, and a test environment.
 */
$.widget( "heurist.reportEditor", $.heurist.baseAction, {

    /**
     * @memberof heurist.reportEditor
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {number} options.height - Default height of the editor dialog.
     * @property {number} options.width - Default width of the editor dialog.
     * @property {string} options.title - Default title of the editor dialog.
     * @property {string} options.default_palette_class - CSS class for the default palette.
     * @property {string} options.actionName - Name of the action.
     * @property {string} options.htmlContent - HTML file for the widget's content.
     * @property {string} options.path - Path to the widget's resources.
     * @property {?number} options.rty_ID - Record Type ID.
     * @property {boolean} options.listAllRecTypes - Flag to list all record types.
     * @property {boolean} options.keep_instance - Flag to keep the widget instance.
     * @property {?string} options.template - The name of the template to load.
     * @property {?function} options.onChange - Callback function triggered on editor content change.
     */
    options: {
        height: 640,
        width:  1000,
        title:  'Edit Report Template',
        default_palette_class: 'ui-heurist-populate',
        actionName: 'reportEditor',
        htmlContent: 'reportEditor.html',
        path: 'widgets/report/',

        isWidgetTemplate: false,
        isCalcFieldTemplate: false,
        
        rty_ID:null, 
        listAllRecTypes: false,
        
        keep_instance: true,
        template: null,  //path to smarty tpl
        template_body: null, // template text 
        template_css: null,  // css file to be added to html output
        
        onChange: null
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @property {Object} usrPreferences - User-specific preferences for layout.
     * @property {number} usrPreferences.insertForm_width - Width of the insert form panel.
     * @property {boolean} usrPreferences.insertForm_closed - Initial state of the insert form panel.
     * @property {number} usrPreferences.testForm_width - Width of the test form panel.
     * @property {boolean} usrPreferences.testForm_closed - Initial state of the test form panel.
     * @property {number} usrPreferences.width - User-preferred width of the dialog.
     * @property {number} usrPreferences.height - User-preferred height of the dialog.
     */
    usrPreferences:{
            insertForm_width:300, 
            insertForm_closed:false, 
            testForm_width:400, 
            testForm_closed:false,
            width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
            height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @property {string} _keepTemplateValue - Stores the initial template content to check for modifications.
     */
    _keepTemplateValue:'',
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @property {?Object} codeEditor - CodeMirror editor instance.
     */
    codeEditor: null,
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @property {string} _currentTemplate - Name of the currently loaded template.
     */
    _currentTemplate: '',
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @property {?jQuery} _addVariableDlg - Dialog for inserting variables.
     */
    _addVariableDlg: null,
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @property {?jQuery} _tempForm - Temporary form used for testing templates.
     */
    _tempForm: null,
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @property {?string} _last_disabled_message - Stores the last warning message about disabled functions.
     */
    _last_disabled_message: null,

    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @property {boolean} is_snippet_editor - Flag indicating if it's a snippet editor.
     */
     is_snippet_editor: false,
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Widget creation method. Initializes options and sets up beforeClose behavior.
     */
    _create: function() {
        this._super();
        
        this.is_snippet_editor = this.options.isWidgetTemplate || this.options.isCalcFieldTemplate;
        
        if(this.options.isCalcFieldTemplate){
            this.options.width  = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.7
            this.options.height = (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.7
           
        }else{
            this.options.height = this.usrPreferences.height;
            this.options.width = this.usrPreferences.width;
        }
        
        let that = this;
        this.options.beforeClose = function(){
            return that._beforeClose();
        };

    }, //end _create
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Widget initialization method. Loads the template if already initialized.
     */
    _init: function() {
        
        this._super();
        
        if( this._is_inited ){
            this._loadTemplate();
        }
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Widget destruction method. Removes the temporary test form.
     */
    _destroy: function() {
        if(this._tempForm){
            this._tempForm.remove();
        }
    },

   
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Initializes controls after HTML content is loaded. Sets up layout, CodeMirror, and event handlers.
     * @returns {boolean} False if superclass initialization fails, otherwise true.
     */
    _initControls: function(){
        
        let res  = this._super();
        if(!res) return false;
        
        this.editSmarty = this._$('.editSmarty');
        
        let that = this;
        
                let layout_opts =  {
                    applyDefaultStyles: true,
                    maskContents: true,
                    enableCursorHotkey: false,
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
        let $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), 
                                        this.options.listAllRecTypes ? null : this.options.rty_ID,
                                        this.options.rty_ID>0?null:window.hWin.HR('select record type'), true );
        this._on($rec_select,{change: function(){
           this._loadRecordTypeTreeView();
           const rty_ID = this._$('#rectype_selector').val();
           this._loadTestRecords( rty_ID );
           
           if(rty_ID>0){
                //this._$('#btnInsertPattern').addClass('ui-button-action');
                this._$('#btnInsertFields').addClass('ui-button-action').show();
           }else{
                //this._$('#btnInsertPattern').removeClass('ui-button-action');
                this._$('#btnInsertFields').removeClass('ui-button-action').hide();
           }
        }});
        if(!this.options.rty_ID && this.is_snippet_editor){
            rtSelect.val(rtSelect.find('option').get(1).value);
            rtSelect.trigger('change');
        }else if(this.options.rty_ID){
            rtSelect.val(this.options.rty_ID).trigger('change');
        }
        
      
        this._on(this._$('#btnInsertPattern').button(), {click:this._insertPattern});
        this._on(this._$('#btnInsertFields').button(), {click:()=>this._insertFields(0)});
        
        this._on(this._$('#fsw_showreverse'), {
            click: (e)=>{
                this.showHideReverse();
            }});
        
        
        this._on(this._$('#selectAll'), {
            click: (e)=>{
                let treediv = this._$('#field_treeview');

                let check_status = $(e.target).is(":checked");

                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    let tree = $.ui.fancytree.getTree(treediv);
                    tree.visit(function(node){
                        if(!node.hasChildren() && node.type != "relmarker" && node.type != "resource" && node.type != "separator"
                            && (node.getLevel()==2 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                        ){    
                            node.setSelected(check_status);
                        }
                    });
                }
            }
        });        

        //init test panel
        this._on(this._$('.btnStartTest').button({icon: 'ui-icon-circle-arrow-s'}), 
            {click:()=>{this._doTest();}});
        

        if(this.is_snippet_editor){
            this._$('.editForm').css({top:'90px'});
            this._$('.insertForm > .ent_content_full').css({top:'50px'});
            this._$('.hide-for-snippet').hide();
            this._$('.show-for-snippet').show();
            
            this._loadRecordTypeTreeView();
            this._loadTestRecords();
        }
        
        if(this.options.isCalcFieldTemplate){ 
            //snippet for calculation field
            this._initEditor(this.options.template_body);
        }else{
            //template for widget
            this._$('.editForm').css({top:'0px'});
            //init editor (load codeMirror)
            this._loadTemplate();
        }        

        
        
        return true;
    },
  
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Executes the report test. Prepares request, submits it, and displays results.
     */
    _doTest:function(){

        let template_body = this.codeEditor.getValue();

        const expectedLength = this.options.isCalcFieldTemplate ? 2 : 10;
        if(template_body?.length <= expectedLength){
            window.hWin.HEURIST4.msg.showMsgFlash('Nothing to execute. Define code');
            return;            
        }

        let recset;
        let request = {db:window.hWin.HAPI4.database, 
                       action: 'execute', 
                       recordset: 1,
                       template_body:1};

        if(this.is_snippet_editor){
                let rec_ID = this._$('#listRecords').val();
                if(!window.hWin.HEURIST4.util.isPositiveInt(rec_ID)){
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Select record to test on',
                        error_title: 'Missing record'
                    });
                    return;
                }
                request['publish'] = this.options.template?0:4;
                recset = {records:[rec_ID], reccount:1}; //JSON.stringify(
                
        }else if(window.hWin.HAPI4.currentRecordset?.length()==0){
            window.hWin.HEURIST4.msg.showMsgFlash('Perform search to get record set to test against');
            return;
        }else{
        
            let debug_limit = document.getElementById('cbDebugReportLimit').value;
            if(debug_limit<0){
                debug_limit = 2000;
            }
            
            recset = {recIDs:window.hWin.HAPI4.currentRecordset.getIds().slice(0, debug_limit-1)};
        }

        let replevel = document.getElementById('cbErrorReportLevel').value;
        if(replevel<0) {
            document.getElementById('cbErrorReportLevel').value = 0;
            replevel = 0;
        }
        request['replevel'] = replevel;
        if(this.options.isWidgetTemplate){
            request['testwidget'] = 1;
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._$('.testForm'));
        
        let inputs = '';
        for (let [key, value] of Object.entries(request)) {
            inputs += `<input type="hidden" name="${key}" value="${value}"/>`;
        }       
        
        if(this._tempForm){
            this._tempForm.empty();
        }else{
            const url = window.hWin.HAPI4.baseURL+'hserv/controller/index.php';
            this._tempForm = $(`<form target="test_container_frame" action="${url}" method="post"></form>`)
                .appendTo(this.element);
                
            this._on(this._$('#test_container_frame'),{load:()=>{
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                this._showWarningAboutDisabledFunction();
            }});
        }

        this._tempForm.html(inputs);
        this._tempForm.find('input[name="recordset"]').val(JSON.stringify(recset));
        this._tempForm.find('input[name="template_body"]').val(template_body);
        this._tempForm.trigger('submit');
        
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Shows a warning if a disabled PHP function is used in the template.
     */
    _showWarningAboutDisabledFunction: function(){    
        
        let txt = this._$('#test_container_frame')[0].contentDocument.body.innerHTML;
        
        if(this._last_disabled_message != txt && 
            window.hWin.HEURIST4.msg.showWarningAboutDisabledFunction(txt)){
            this._last_disabled_message = txt;
        }
        
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Loads the Smarty template content into the editor.
     *              If it's a snippet editor, it uses `options.template_body`.
     *              Otherwise, it fetches the template from the server.
     */
    _loadTemplate: function(){    
        
        if(this.options.isCalcFieldTemplate){
            //for calculation field
            this._initEditor(this.options.template_body);
        }else
        // null means new template
        if(this._currentTemplate!=this.options.template){
            
            window.hWin.HAPI4.SystemMgr.user_log('cms_EditSmarty');            
            
            this._currentTemplate = this.options.template;

            let that = this;
            window.hWin.HAPI4.SystemMgr.reportAction({action:'get', template:this.options.basedOn?this.options.basedOn:this._currentTemplate}, 
                function(response){
                    that._initEditor(response.message);
            });
           
            this.changeTitle();
        }
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Changes the title of the editor dialog.
     * @param {string} [new_title] - The new title. If not provided, a default title is generated.
     */
    changeTitle: function( new_title ){
        if(!new_title){
           new_title = window.hWin.HR('Edit')+': '+
                    (this._currentTemplate?this._currentTemplate:'new template');
        }
        this._super(new_title);
    },
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Initializes the CodeMirror editor with the given content.
     *              Loads CodeMirror library if not already loaded.
     * @param {string} content - The template content to load into the editor.
     */
    _initEditor: function(content){
    
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
                $.getStyles(`${path}lib/codemirror.css`);

                $.getMultiScriptsSequental(scripts, path)
                .then(function() {  //OK! widget script js has been loaded
                    that._initEditor( content );
                }).catch(function(error) {
                    window.hWin.HEURIST4.msg.showMsg_ScriptFail();
                });
                
                return;
            }

                this._$('.editForm').empty().css({padding:0});
            
                this.codeEditor = CodeMirror( this._$('.editForm')[0], {
                    mode           : "smartymixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : true,
                    smartyVersion  : 5,
                    matchBrackets  : true,
                    smartIndent    : true,
                    extraKeys: {
                        "Enter": function(e){
                            that._insertAtCursor('');
                        }
                    }
                    //onFocus:function(){},
                    //onBlur:function(){},
                });
                
                if(window.hWin.HEURIST4.util.isFunction(this.options.onChange)){
                    this.codeEditor.on('change', (args) => { 
                        if(that.isModified()){
                            that.options.onChange(that.codeEditor.getValue());
                        }
                    } )    
                }
                
                
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
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Generates a remark (comment) string for a Fancytree node.
     * @param {Object} _nodep - The node object from Fancytree.
     * @returns {string} The remark string.
     */
    _getRemark: function(_nodep){

        let s = _nodep.title;
        let key = _nodep.key;

        if(key=='label' || key=='term' || key=='code' || key=='conceptid' || key=='internalid' || key=='desc'){
            s = _nodep.parent.title + '.' + s;
        }

        s =  window.hWin.HEURIST4.util.stripTags(s);
        if(_nodep.parent?.data.codes ){ //!_nodep.parent.isRootNode()
            s = window.hWin.HEURIST4.util.stripTags(_nodep.parent.title) + ' >> ' + s;
        }
        return s;
    },

    _getRemarkForLinkedFrom: function(seg, sourceRectypeId){
        let name = $Db.rty(sourceRectypeId, 'rty_Name') || ('Record ' + sourceRectypeId);
        return name;
    },    
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Inserts a predefined Smarty pattern into the editor based on user selection.
     */
    _insertPattern: function(){
        
        let pattern_id = Number(this._$('#selInsertPattern').val());
    
        this._closeInsertPopup();
        
        let _text = '';
        let that = this;        

        // Update these patterns in synch with pulldown 
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

    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Loads the Fancytree for selecting record types and fields.
     */
    _loadRecordTypeTreeView: function(){
        
        let rty_ID = this._$('#rectype_selector').val();

        //load treeview
        let treedivContainer = this._$('.rtt-tree');
        let treedivPlaceholder = this._$('.rtt-tree-placeholder');
        
        let treediv = this._$('#field_treeview');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        if(!window.hWin.HEURIST4.util.isPositiveInt(rty_ID)){
            treedivPlaceholder.show();
            treedivContainer.hide();
            return;
        }
        treedivPlaceholder.hide();
        treedivContainer.show();
        treediv.empty();
        
        let node_order = treedivContainer.find('[name="tree_order"]:checked').val();
        //generate treedata from rectype structure
        let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, rty_ID, 
                        ['ID','title','typeid','typename','modified','url','tags','all','parent_link'], null, node_order );

        treedata[0].expanded = true; //first expanded

        if(this.is_snippet_editor){
            //hide root - record type title
            treedata = treedata[0];
            treedivContainer.css('top','100px');
        }
        
        let that = this;

        treediv.fancytree({
            //oldd checkbox: false,
            //oldd selectMode: 1,  // single
                checkbox: true,
                selectMode: 3,  // hierarchical multi-selection
            
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
                
                let node_order = treedivContainer.find('[name="tree_order"]:checked').val();

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, 
                    rectypes, ['ID','title','typeid','typename','modified','url','tags','all'], parentcode, node_order );

                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
                expand: (e, data)=>{
                    this.showHideReverse();
                },
                loadChildren: (e, data)=>{
                    setTimeout(function(){
                        that.showHideReverse();   
                    },500);
                },
            select: function(e, data) {
            },
            click: function(e, data){
                
                if(data.node.type == 'separator'){
                    return false;
                }

                let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                if(isExpander){
                    return;
                }

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    data.node.setExpanded(!data.node.isExpanded());
                }else if( data.node.lazy) {
                    data.node.setExpanded( true );
                }                
            },
            renderNode: function(event, data) {
              
                let order = treedivContainer.find('[name="tree_order"]:checked').val();

                if(data.node.data.is_generic_fields) { // hide blue arrow for generic fields
                    $(data.node.span.childNodes[1]).hide();
                }else if(data.node.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators

                    if(order == 1){
                        $(data.node.li).addClass('fancytree-hidden');
                    }
                }else if(data.node.type == 'enum'){ // TODO - Move to CSS for general use when field colours are set out
                    $(data.node.span.childNodes[3]).css('color', '#871F78');
                }else if(data.node.type == 'date'){ // TODO - Move to CSS for general use when field colours are set out
                    $(data.node.span.childNodes[3]).css('color', 'darkgreen');
                }
                
                if(data.node.hasChildren() || data.node.type == 'resource'){
                   //data.node.type == 'enum' || data.node.type == 'resource' || data.node.type == 'relationship' 
                   $(data.node.span.childNodes[1]).hide(); //hide checkbox
                }
            
            }            
        });
        
        
    },
    
    
    showHideReverse: function(){
        
        let treediv = this._$('#field_treeview');

        if(treediv.fancytree('instance')){
        
            let tree = $.ui.fancytree.getTree(treediv);
            let showrev = this._$('#fsw_showreverse').is(":checked");

            tree.visit(function(node){

                if(node.data.isparent==1){ // always show parent entities
                    $(node.li).removeClass('fancytree-hidden');
                }else if(node.data.isreverse==1){

                    if(showrev===true){
                        $(node.li).removeClass('fancytree-hidden');
                    }else{
                        $(node.li).addClass('fancytree-hidden');
                    }
                }
            });
        }
    },      
    
    /**
     * @memberof heurist.reportEditor
     * @instance
     * @private
     * @description Inserts text at the current cursor position in the CodeMirror editor, maintaining indentation.
     * @param {string} myValue - The text to insert.
     */
    _insertAtCursor: function(myValue, supressCursorPos){

        
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

        if(!supressCursorPos && (myValue.indexOf("{if")>=0 || myValue.indexOf("{foreach")>=0)){
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
    
    /**
    * @memberof heurist.reportEditor
    * @instance
    * @private
    * @description Closes the insert variable/pattern popup dialog if it's open.
    */
    _closeInsertPopup: function(){
        if(this._addVariableDlg?.dialog('instance')){
            this._addVariableDlg.dialog('close');
        }
    },

    /**
    * @memberof heurist.reportEditor
    * @instance
    * @description Checks if the template content has been modified since it was last loaded or saved.
    * @returns {boolean} True if modified, false otherwise.
    */
    isModified: function(){
        return (this._keepTemplateValue && this._keepTemplateValue!=this.codeEditor.getValue());  
    },

    /**
    * @memberof heurist.reportEditor
    * @instance
    * @private
    * @description Handles the beforeClose event of the dialog. Prompts the user to save if there are modifications.
    * @returns {boolean} False if there are unsaved changes and the user chooses to cancel closing, true otherwise.
    */
    _beforeClose: function() {
        if(this.isModified()){

            const isSaveAs = this.options.isWidgetTemplate && this.options.template.indexOf('def/')===0;

            window.hWin.HEURIST4.msg.showMsgOnExit(window.hWin.HR('Warn_Lost_Data'),
                ()=>{this.doAction(isSaveAs, true);}, //save
                ()=>{this._keepTemplateValue=false; this.closeDialog();}); //ignore and close

            return false;
        }else{
            return true;
        }
    },

    /**
    * @memberof heurist.reportEditor
    * @instance
    * @private
    * @description Gets the action buttons for the dialog (Close, Save, Save As).
    * @returns {Array<Object>} Array of button definition objects.
    */
    _getActionButtons: function(){
        let res = this._super();

        let that = this;

        res[0].text = window.hWin.HR('Close');

        res[1].text = window.hWin.HR('Save');
        if(this.options.isWidgetTemplate && this.options.template.indexOf('def/')==0){
            res[1].disabled = true;
        }else{
            res[1].disabled = null;
        }

        if(!this.options.isCalcFieldTemplate)
        {
            res.splice(1,0,{text:window.hWin.HR('Save As'),
                class:'ui-button-action btnDoAction2',
                css:{'float':'right'},  
                click: function() { 
                    that.doAction(true); 
                }}
            );
        }

        if(this.options.isWidgetTemplate){

            res.splice(2,0,{text:window.hWin.HR('Delete'),
                class:'ui-button-action btnDoAction3',
                css:{'float':'left','margin-right':'150px'},  
                click: function() { 
                    that._onTemplateDelete(); 
                }}
            );

            if(this.options.template.indexOf('def/')<0){
                res[2].disabled = null;
            }else{
                res[2].disabled = true;
            }
        }

        return res;
    },

    /**
    * @memberof heurist.reportEditor
    * @instance
    * @description Saves the current template. Handles "Save As" functionality and prompts for a name if needed.
    * @param {boolean} [is_save_as=false] - If true, prompts for a new template name.
    * @param {boolean} [need_close=false] - If true, closes the dialog after saving.
    */
    doAction: function(is_save_as, need_close){

        let that = this;

        if(this.options.isCalcFieldTemplate)
        {
            //snippet for calculation field
            if(this.isModified()){
                this._context_on_close = this.codeEditor.getValue();    
            }
            this._keepTemplateValue=false;
            this.closeDialog();
            return;    
        }

        if(!this._currentTemplate || is_save_as){

            setTimeout(()=>{    
                    window.hWin.HEURIST4.msg.showPrompt('Please enter template name', function(tmp_name){
                        if(!window.hWin.HEURIST4.util.isempty(tmp_name)){
                            that._currentTemplate = tmp_name;
                            that._context_on_close = true; //to update list in parent window
                            that.doAction(false);
                        }
                        }, {title:'Save template as',yes:'Save as',no:"Cancel"});
                }, is_save_as?10:500);
            return;
        }

        window.hWin.HAPI4.SystemMgr.reportAction({action:'save', 
            template: this._currentTemplate, 
            template_body: this.codeEditor.getValue()
            }, 
            function(response){
                if (response.status == window.hWin.ResponseStatus.OK) {
                    that._keepTemplateValue = that.codeEditor.getValue()
                    that.changeTitle();
                    window.hWin.HEURIST4.msg.showMsgFlash('Report template has been saved');
                    if(need_close){
                        that.closeDialog();
                    }else{
                        window.hWin.HEURIST4.util.setDisabled( that.element.parents('.ui-dialog').find('.btnDoAction'), false); 
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });

    },

    /**
    * @memberof heurist.reportEditor
    * @instance
    * @private
    * @description Loads a list of records of a given record type for testing the template (snippet editor only).
    * @param {?number} rty_ID - The Record Type ID. If not provided, uses `this.options.rty_ID`.
    */
    _loadTestRecords: function( rty_ID )
    {
        if(!this.is_snippet_editor){
            return;
        }

        let selector = this._$('#listRecords')[0];
        selector.innerHTML = '';
        //load list of records for testing 
        rty_ID = rty_ID??this.options.rty_ID
        if(rty_ID>0){

            const server_request = {
                q: 't:'+rty_ID,
                restapi: 1,
                columns: ['rec_ID', 'rec_Title'],
                limit:10,
                zip: 1,
                format:'json'};


            //search for record type
            window.hWin.HAPI4.RecordMgr.search_new(server_request, function(response){

                if(window.hWin.HEURIST4.util.isJSON(response)) {
                    let options = [];
                    response.records.forEach((item) => {
                        let rec_Title = window.hWin.HEURIST4.util.stripTags(item.rec_Title);
                        rec_Title = rec_Title.length > 60 ? `${rec_Title.slice(0, 60)}...` : rec_Title;
                        options.push({ key: item.rec_ID, title: rec_Title });
                    });
                    window.hWin.HEURIST4.ui.createSelector(selector, options);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });            
        }
    },

    //
    //
    //
    _onTemplateDelete: function(unconditionally) {

        let that = this;

        if(!this.options.template || this.options.template.indexOf('def/')===0){
            return;
        }

        if(unconditionally===true){

            window.hWin.HAPI4.SystemMgr.reportAction({action:'delete', template:this.options.template}, 
                function(response){
                    if (response.status == window.hWin.ResponseStatus.OK) {
                        that._context_on_close = true;
                        that.closeDialog();                        
                    } else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete template "'+this.options.template+'"?', 
                function(){ that._onTemplateDelete(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },

    //
    // new method for fields addition
    //
    _insertFields: function(index){
        //1. get selected fields
        let tree = $.ui.fancytree.getTree( this._$('#field_treeview') );
        let fieldIds = tree.getSelectedNodes(false);
        len = fieldIds.length;

        if(index>=len) return;
        if(!index) index = 0;

        //2. popup in loop
        let _nodep =  fieldIds[index];      //FancytreeNode
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(_nodep.children)){  //ignore top levels selection
            this._insertFields(index+1);
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

        this._closeInsertPopup();

        let btns = [
            {text:window.hWin.HR('Insert'),
                id:'btnStartInsert',
                click: ()=>{

                const insertAll = this._addVariableDlg.find('#insAll').is(':checked');

                const addLoop = this._addVariableDlg.find('#insRepeat').is(':checked');
                const ifnull = this._addVariableDlg.find('#insIfNull').is(':checked');
                const addCaption = this._addVariableDlg.find('#insCaption').is(':checked');
                const addRemark = this._addVariableDlg.find('#insRemark').is(':checked');
                const addWrap = this._addVariableDlg.find('#insWrap').is(':checked');
                const useOwnLoop = !insertAll; // || this._addVariableDlg.find('#insOwnLoop').is(':checked');

                this._closeInsertPopup();

                if(insertAll){
                    this._insertSelectedVars(index, addLoop, ifnull, addCaption, addRemark, addWrap, useOwnLoop);
                    return;
                }

                for(let k=index; k<len; k++){
                    let _nodep = fieldIds[k];
                    _nodep.setSelected(false);
                    $(_nodep.li).css('color','#B5FF00');
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(_nodep.children)){
                        continue;
                    }

                    this._insertSelectedVars(_nodep, addLoop, ifnull, addCaption, addRemark, addWrap, useOwnLoop);
                    this._insertFields(index);
                    break;
                }
            }
            },
            {text:window.hWin.HR('Cancel'),
                click: ()=>{
                    this._closeInsertPopup()
                    this._insertFields(index+1);
                }
            },
            {text:window.hWin.HR('Cancel All'),
                click: ()=>{
                    this._closeInsertPopup()
                }
            }
        ];            


        this._addVariableDlg = window.hWin.HEURIST4.msg.showElementAsDialog(   
            {element: this._$('#insert-popup2')[0],
                modal: true,
                width:400,
                height: 300,
                resizable: false,
                title:`Inserting '${field_name}'`,
                buttons:btns,
                open: (event, ui)=>{
                $(event.target).find('#fieldName').text(field_name);
                let insAll = $(event.target).find('#insAll')
                insAll.off('click');
                insAll.on({click:()=>{
                    const newLabel = insAll.is(':checked')?'Insert All':'Insert';
                    $(event.target).parent().find('#btnStartInsert').button("option", "label", newLabel);


            }});
            },
            beforeClose:null,
            close:function(){
                return true; //remove
            },
            borderless: false,
            default_palette_class:'ui-heurist-populate'});



    },    

    //========================
    _insertSelectedVars: function(_nodep, addLoop, ifnull, addCaption, addRemark, addWrap, useOwnLoop){

        // single insert mode
        if(useOwnLoop){
            const snippet = this._buildSmartySnippetForNode(
                _nodep,
                addLoop,
                ifnull,
                addCaption,
                addRemark,
                addWrap,
                0,
                'r'
            );

            if(snippet){
                this._insertAtCursor(snippet, true);
            }
            return;
        }

        // grouped insert-all mode
        const tree = $.ui.fancytree.getTree(this._$('#field_treeview'));
        let selected = tree.getSelectedNodes(false).filter(
            n => !window.hWin.HEURIST4.util.isArrayNotEmpty(n.children)
        );

        let startIndex = Number.isInteger(_nodep) ? _nodep : parseInt(_nodep, 10);
        if(Number.isNaN(startIndex)) startIndex = 0;
        if(startIndex > 0){
            selected = selected.slice(startIndex);
        }

        if(selected.length === 0) return;

        let snippets = [];
        let i = 0;
        while(i < selected.length){
            const rootNode = selected[i];
            const parsed = this._parseNodeCode(rootNode);

            let snippet = '';
            let j = i + 1;

            if(parsed?.rootRectypeId === 'Relationship'){
                const group = [rootNode];

                while(j < selected.length){
                    const p2 = this._parseNodeCode(selected[j]);
                    if(!p2 || p2.rootRectypeId !== 'Relationship') break;
                    group.push(selected[j]);
                    j++;
                }

                snippet = this._buildGroupedRelationshipSnippet(
                    group,
                    ifnull,
                    addCaption,
                    addRemark
                );

            }else{
                const group = [rootNode];
                const rootBranchKey = this._getBranchKey(rootNode);

                while(j < selected.length){
                    const p2 = this._parseNodeCode(selected[j]);
                    if(!p2 || p2.rootRectypeId === 'Relationship') break;
                    if(this._getBranchKey(selected[j]) !== rootBranchKey) break;
                    group.push(selected[j]);
                    j++;
                }

                if(group.length > 1){
                    snippet = this._buildGroupedSmartySnippet(
                        group,
                        addLoop,
                        ifnull,
                        addCaption,
                        addRemark,
                        addWrap
                    );
                }else{
                    snippet = this._buildSmartySnippetForNode(
                        rootNode,
                        addLoop,
                        ifnull,
                        addCaption,
                        addRemark,
                        addWrap,
                        0,
                        'r'
                    );
                }
            }

            if(snippet){
                snippets.push(snippet);
            }

            i = j;
        }
        
        for(i=0; i < selected.length; i++){
            selected[i].setSelected(false);
            $(selected[i].li).css('color','#B5FF00');
        }


        if(snippets.length > 0){
            this._insertAtCursor(snippets.join('\n'), true);
        }
    },


    /**
    * Build grouped snippet for selected nodes sharing common path prefixes
    */
    _buildGroupedSmartySnippet: function(nodes, addLoop, ifnull, addCaption, addRemark, addWrap){

        if(!nodes || nodes.length === 0) return '';

        const tree = this._buildSelectionTree(nodes);
        if(!tree) return '';

        const rootVar = 'r';
        return this._renderSelectionTree(tree, {
            addLoop,
            ifnull,
            addCaption,
            addRemark,
            addWrap,
            indent: 0,
            parentVar: rootVar,
            loopDepth: 0
        });
    },


/**
 * Build single-path snippet
 */
_buildSmartySnippetForNode: function(_nodep, addLoop, ifnull, addCaption, addRemark, addWrap, indent, parentVar){

    const code = _nodep?.data?.code || '';
    if(!code) return '';
    
    const parsed = this._parseNodeCode(_nodep);
    if(!parsed || !parsed.segments || parsed.segments.length === 0) return '';

    // Relationship special case
    if(parsed.rootRectypeId === 'Relationship'){
        const leaf = parsed.segments[0];
        let res = this._renderRelationshipsInit();

        res += '{foreach $r.Relationships as $Relationship name=relations}';
        if(addRemark){
            const remark = this._getRemark(_nodep);
            if(remark) res += ' {* ' + remark + ' *}';
        }
        res += '\n';

        res += this._renderRelationshipLeafExpression({
            node: _nodep,
            leaf,
            ifnull,
            addCaption,
            addRemark,
            indent: 1
        });

        res += '{/foreach}\n';
        return res;
    }    
    
    let snippet = '';
    let currentVar = parentVar || 'r';
    let currentRectype = parsed.rootRectypeId;
    let pad = this._indent(indent);
    let loopDepth = 0;

    for(let i = 0; i < parsed.segments.length - 1; i++){
        const seg = parsed.segments[i];

        if(seg.kind === 'resource'){
            const fieldId = seg.fieldId;
            const linkedRectypeId = seg.targetRectypeId;
            const isRepeatable = this._isRepeatableField(currentRectype, fieldId);
            const loopVar = 'f' + fieldId;
            const loopName = 'valueloop' + (loopDepth ? (loopDepth + 1) : '');

            if(addLoop && isRepeatable){
                snippet += pad + '{foreach $' + currentVar + '.f' + fieldId + 's as $' + loopVar + ' name=' + loopName + '}';
                if(addRemark){
                    const remark = this._getRemarkForResource(seg, linkedRectypeId, true);
                    if(remark) snippet += ' {* ' + remark + ' *}';
                }
                snippet += '\n';

                pad = this._indent(indent + 1 + loopDepth);
                snippet += pad + '{$' + loopVar + '=$heurist->getRecord($' + loopVar + ')}';
                if(addRemark){
                    snippet += ' {* get record by record id *}';
                }
                snippet += '\n';

                currentVar = loopVar;
                currentRectype = linkedRectypeId;
                loopDepth++;
            }else{
                snippet += pad + '{$' + loopVar + '=$heurist->getRecord($' + currentVar + '.f' + fieldId + ')}';
                if(addRemark){
                    snippet += ' {* get record by record id *}';
                }
                snippet += '\n';

                currentVar = loopVar;
                currentRectype = linkedRectypeId;
            }
            continue;
        }

        if(seg.kind === 'linked_from'){
            const fieldId = seg.fieldId;
            const sourceRectypeId = seg.sourceRectypeId;
            const listVar = this._getLinkedFromVarName(sourceRectypeId, fieldId, true);
            const itemVar = this._getLinkedFromVarName(sourceRectypeId, fieldId, false);
            const loopName = 'valueloop' + (loopDepth ? (loopDepth + 1) : '');

            snippet += pad + '{$' + listVar + ' = $heurist->getLinkedFromRecords($' + currentVar + ', ' + sourceRectypeId + ', ' + fieldId + ')}\n';
            snippet += pad + '{foreach $' + listVar + ' as $' + itemVar + ' name=' + loopName + '}';
            if(addRemark){
                const remark = this._getRemarkForLinkedFrom(seg, sourceRectypeId);
                if(remark) snippet += ' {* ' + remark + ' *}';
            }
            snippet += '\n';

            pad = this._indent(indent + 1 + loopDepth);
            snippet += pad + '{$' + itemVar + '=$heurist->getRecord($' + itemVar + ')}';
            if(addRemark){
                snippet += ' {* get record by record id *}';
            }
            snippet += '\n';

            currentVar = itemVar;
            currentRectype = sourceRectypeId;
            loopDepth++;
            continue;
        }
    }

    const leaf = parsed.segments[parsed.segments.length - 1];
    const leafNode = this._renderLeafExpression({
        node: _nodep,
        leaf,
        currentVar,
        currentRectype,
        addLoop,
        ifnull,
        addCaption,
        addRemark,
        addWrap,
        indent: indent + loopDepth
    });

    snippet += leafNode;

    while(loopDepth > 0){
        snippet += this._indent(indent + loopDepth - 1) + '{/foreach}\n';
        loopDepth--;
    }

    return snippet;
},

/**
 * Build prefix tree from selected nodes
 */
_buildSelectionTree: function(nodes){

    if(!nodes || nodes.length === 0) return null;

    const root = {
        kind: 'root',
        rectypeId: null,
        children: []
    };

    for(const node of nodes){
        const parsed = this._parseNodeCode(node);
        if(!parsed || !parsed.segments?.length) continue;

        if(root.rectypeId == null){
            root.rectypeId = parsed.rootRectypeId;
        }

        let cursor = root;

        for(let i = 0; i < parsed.segments.length; i++){
            const seg = parsed.segments[i];
            const isLeaf = i === parsed.segments.length - 1;

            let child = cursor.children.find(c =>
                c.kind === seg.kind &&
                c.fieldId === seg.fieldId &&
                c.targetRectypeId === seg.targetRectypeId &&
                c.sourceRectypeId === seg.sourceRectypeId &&
                c.headerKey === seg.headerKey &&
                c.headerAlias === seg.headerAlias &&
                c.propName === seg.propName &&
                (
                    seg.kind !== 'term'
                    || c.fieldId === seg.fieldId
                )
            );

            if(!child){
                child = {
                    ...seg,
                    subfields: seg.kind === 'term' ? [{
                        subfield: seg.subfield,
                        title: isLeaf ? node.title : null,
                        nodeRef: isLeaf ? node : null
                    }] : null,
                    title: isLeaf ? node.title : null,
                    nodeRef: isLeaf ? node : null,
                    children: []
                };
                cursor.children.push(child);
            }else if(isLeaf){

                if(seg.kind === 'term'){
                    if(!child.subfields) child.subfields = [];

                    const exists = child.subfields.some(s => s.subfield === seg.subfield);
                    if(!exists){
                        child.subfields.push({
                            subfield: seg.subfield,
                            title: node.title,
                            nodeRef: node
                        });
                    }
                }else{
                    child.title = node.title;
                    child.nodeRef = node;
                }
            }

            cursor = child;
        }
    }

    return root;
},


/**
 * Render grouped tree recursively
 */
_renderSelectionTree: function(tree, opts){

    const { addLoop, ifnull, addCaption, addRemark, addWrap } = opts;
    let { indent, parentVar, loopDepth } = opts;

    if(!tree || !tree.children || tree.children.length === 0) return '';

    let res = '';
    let currentRectype = tree.rectypeId;

    for(const child of tree.children){

        if(child.kind === 'resource'){
            const fieldId = child.fieldId;
            const linkedRectypeId = child.targetRectypeId;
            const isRepeatable = this._isRepeatableField(currentRectype, fieldId);
            const varname = 'f' + fieldId;
            const pad = this._indent(indent);
            let openedLoop = false;

            if(addLoop && isRepeatable){
                const loopName = 'valueloop' + (loopDepth ? (loopDepth + 1) : '');
                res += pad + '{foreach $' + parentVar + '.f' + fieldId + 's as $' + varname + ' name=' + loopName + '}';
                if(addRemark){
                    const remark = this._getRemarkForResource(child, linkedRectypeId, true);
                    if(remark) res += ' {* ' + remark + ' *}';
                }
                res += '\n';

                res += this._indent(indent + 1) + '{$' + varname + '=$heurist->getRecord($' + varname + ')}';
                if(addRemark){
                    res += ' {* get record by record id *}';
                }
                res += '\n';

                openedLoop = true;
            }else{
                res += pad + '{$' + varname + '=$heurist->getRecord($' + parentVar + '.f' + fieldId + ')}';
                if(addRemark){
                    res += ' {* get record by record id *}';
                }
                res += '\n';
            }

            res += this._renderSelectionTree({
                ...child,
                rectypeId: linkedRectypeId
            }, {
                addLoop,
                ifnull,
                addCaption,
                addRemark,
                addWrap,
                indent: indent + (openedLoop ? 1 : 0),
                parentVar: varname,
                loopDepth: loopDepth + (openedLoop ? 1 : 0)
            });

            if(openedLoop){
                res += pad + '{/foreach}\n';
            }
            continue;
        }

        if(child.kind === 'linked_from'){
            const fieldId = child.fieldId;
            const sourceRectypeId = child.sourceRectypeId;
            const listVar = this._getLinkedFromVarName(sourceRectypeId, fieldId, true);
            const itemVar = this._getLinkedFromVarName(sourceRectypeId, fieldId, false);
            const pad = this._indent(indent);
            const loopName = 'valueloop' + (loopDepth ? (loopDepth + 1) : '');

            res += pad + '{$' + listVar + ' = $heurist->getLinkedFromRecords($' + parentVar + ', ' + sourceRectypeId + ', ' + fieldId + ')}\n';
            res += pad + '{foreach $' + listVar + ' as $' + itemVar + ' name=' + loopName + '}';
            if(addRemark){
                const remark = this._getRemarkForLinkedFrom(child, sourceRectypeId);
                if(remark) res += ' {* ' + remark + ' *}';
            }
            res += '\n';

            res += this._indent(indent + 1) + '{$' + itemVar + '=$heurist->getRecord($' + itemVar + ')}';
            if(addRemark){
                res += ' {* get record by record id *}';
            }
            res += '\n';

            res += this._renderSelectionTree({
                ...child,
                rectypeId: sourceRectypeId
            }, {
                addLoop,
                ifnull,
                addCaption,
                addRemark,
                addWrap,
                indent: indent + 1,
                parentVar: itemVar,
                loopDepth: loopDepth + 1
            });

            res += pad + '{/foreach}\n';
            continue;
        }

        if(child.kind === 'term' && child.subfields && child.subfields.length > 1){

            const fieldId = child.fieldId;
            const isRepeatable = this._isRepeatableField(currentRectype, fieldId);
            const varname = 'f' + fieldId;
            const pad = this._indent(indent);

            const baseRemark =
                child.subfields?.[0]?.nodeRef?.parent?.title ||
                child.subfields?.[0]?.nodeRef?.parent?.data?.name ||
                child.nodeRef?.parent?.title ||
                child.nodeRef?.parent?.data?.name ||
                '';

            if(addLoop && isRepeatable){
                const loopName = 'valueloop' + (loopDepth ? (loopDepth + 1) : '');

                res += pad + '{foreach $' + parentVar + '.f' + fieldId + 's as $' + varname + ' name=' + loopName + '}';
                if(addRemark && baseRemark){
                    res += ' {* ' + baseRemark + ' *}';
                }
                res += '\n';

                for(const sub of child.subfields){
                    res += this._renderLeafExpression({
                        node: sub.nodeRef || child.nodeRef,
                        leaf: { kind: 'term', fieldId, subfield: sub.subfield },
                        currentVar: varname,
                        currentRectype,
                        addLoop: false,
                        ifnull,
                        addCaption,
                        addRemark,
                        addWrap,
                        indent: indent + 1
                    });
                }

                res += pad + '{/foreach}\n';
            }else{
                for(const sub of child.subfields){
                    res += this._renderLeafExpression({
                        node: sub.nodeRef || child.nodeRef,
                        leaf: { kind: 'term', fieldId, subfield: sub.subfield },
                        currentVar: parentVar,
                        currentRectype,
                        addLoop: false,
                        ifnull,
                        addCaption,
                        addRemark,
                        addWrap,
                        indent
                    });
                }
            }

            continue;
        }

        res += this._renderLeafExpression({ 
            node: child.nodeRef, 
            leaf: child, 
            currentVar: parentVar, 
            currentRectype, 
            addLoop, 
            ifnull, 
            addCaption, 
            addRemark, 
            addWrap,
            indent
        });
    }

    return res;
},


/**
 * Render final field/header/term leaf
 */
_renderLeafExpression: function(cfg){

    const {
        node,
        leaf,
        currentVar,
        currentRectype,
        addLoop,
        ifnull,
        addCaption,
        addRemark,
        addWrap,
        indent
    } = cfg;

    if(!leaf) return '';

    const pad = this._indent(indent);
    let res = '';
    let expr = '';
    let cond = '';
    let varname = currentVar;
    let localVar = null;
    let inLoop = false;
    let dtype = node?.type || leaf.type || '';
    let remark = addRemark ? this._getRemark(node || leaf.nodeRef || leaf) : '';

    if(leaf.kind === 'header'){
        const headerName = this._headerSmartyName(leaf.headerKey);
        expr = '$' + currentVar + '.' + headerName;
        cond = expr;

    }else if(leaf.kind === 'field'){
        const isRepeatable = this._isRepeatableField(currentRectype, leaf.fieldId);

        if(addLoop && isRepeatable){
            localVar = 'f' + leaf.fieldId;
            const loopName = 'valueloop' + ((indent > 0) ? (indent + 1) : '');
            res += pad + '{foreach $' + currentVar + '.f' + leaf.fieldId + 's as $' + localVar + ' name=' + loopName + '}';
            if(addRemark && remark){
                res += ' {* ' + remark + ' *}';
            }
            res += '\n';
            inLoop = true;
            varname = localVar;
            expr = '$' + localVar;
            cond = '$' + localVar;
        }else{
            expr = '$' + currentVar + '.f' + leaf.fieldId;
            cond = expr;
        }

    }else if(leaf.kind === 'term'){
        const isRepeatable = this._isRepeatableField(currentRectype, leaf.fieldId);

        if(addLoop && isRepeatable){
            localVar = 'f' + leaf.fieldId;
            const loopName = 'valueloop' + ((indent > 0) ? (indent + 1) : '');
            res += pad + '{foreach $' + currentVar + '.f' + leaf.fieldId + 's as $' + localVar + ' name=' + loopName + '}';
            if(addRemark && remark){
                res += ' {* ' + remark + ' *}';
            }
            res += '\n';
            inLoop = true;
            varname = localVar;
            expr = '$' + localVar + '.' + leaf.subfield;
            cond = expr;
        }else{
            // grouped term rendering inside an existing enum loop uses $f19.term, not $f19.f19.term
            if(currentVar === ('f' + leaf.fieldId)){
                expr = '$' + currentVar + '.' + leaf.subfield;
            }else{
                expr = '$' + currentVar + '.f' + leaf.fieldId + '.' + leaf.subfield;
            }
            cond = expr;
        }
    }

    let linePad = inLoop ? this._indent(indent + 1) : pad;
    let line = '';

    if(addCaption){
        line += this._escapeSmartyText((node?.title || leaf.title || 'Value') + ': ');
    }

    if(addWrap && this._shouldUseWrap(node || leaf)){
        line += this._buildWrapExpression(node || leaf, expr, inLoop);
    }else{
        line += '{' + expr + '}';
    }

    if(addRemark && remark){
        line += ' {* ' + remark + ' *}';
    }

    if(ifnull && cond){
        res += linePad + '{if ' + cond + '}\n';
        res += this._indent((inLoop ? indent + 2 : indent + 1)) + line + '\n';
        res += linePad + '{/if}\n';
    }else{
        res += linePad + line + '\n';
    }

    if(inLoop){
        res += pad + '{/foreach}\n';
    }

    return res;
},

_parseNodeCode: function(_nodep){

    const code = _nodep?.data?.code || '';
    const key = _nodep?.key || '';

    // Relationship special source must be handled FIRST
    if(code && code.indexOf('Relationship:') === 0){
        const relKey = code.substring('Relationship:'.length);
        const relKeyNorm = String(relKey || '').trim().toLowerCase();

        let leaf;

        if(/^\d+$/.test(relKey)){
            leaf = {
                kind: 'relationship_field',
                fieldId: relKey
            };
        }else if(key && key.indexOf('rec_') === 0){
            leaf = {
                kind: 'relationship_header',
                headerKey: key
            };
        }else if(relKey.indexOf('rec_') === 0){
            leaf = {
                kind: 'relationship_header',
                headerKey: relKey
            };
        }else if([
            'title', 'rectitle',
            'url', 'recurl',
            'id', 'ids', 'recid',
            'typeid', 'rectypeid',
            'type', 'typename', 'rectypename',
            'modified', 'recmodified',
            'tag', 'tags', 'rectags'
        ].includes(relKeyNorm)){
            leaf = {
                kind: 'relationship_header_alias',
                headerAlias: relKey
            };
        }else{
            leaf = {
                kind: 'relationship_prop',
                propName: relKey
            };
        }

        return {
            rootRectypeId: 'Relationship',
            segments: [leaf]
        };
    }

    if(!code) return null;

    const parts = code.split(':');
    if(parts.length < 2) return null;

    const rootRectypeId = parts[0];
    const segments = [];
    let i = 1;

    // normal record header fields:
    // preserve nested path from code, but take final header identity from key
    if(key && key.indexOf('rec_') === 0){
        while(i < parts.length - 1){
            const part = parts[i];

            if(part && part.indexOf('lt') === 0){
                const fieldId = part.substring(2);
                const targetRectypeId = parts[i + 1];
                segments.push({
                    kind: 'resource',
                    fieldId,
                    targetRectypeId
                });
                i += 2;
                continue;
            }

            if(part && part.indexOf('lf') === 0){
                const fieldId = part.substring(2);
                const sourceRectypeId = parts[i + 1];
                segments.push({
                    kind: 'linked_from',
                    fieldId,
                    sourceRectypeId
                });
                i += 2;
                continue;
            }

            break;
        }

        segments.push({
            kind: 'header',
            headerKey: key
        });

        return {
            rootRectypeId,
            segments
        };
    }

    i = 1;
    while(i < parts.length){
        const part = parts[i];

        if(part && part.indexOf('lt') === 0){
            const fieldId = part.substring(2);
            const targetRectypeId = parts[i + 1];
            segments.push({
                kind: 'resource',
                fieldId,
                targetRectypeId
            });
            i += 2;
            continue;
        }

        if(part && part.indexOf('lf') === 0){
            const fieldId = part.substring(2);
            const sourceRectypeId = parts[i + 1];
            segments.push({
                kind: 'linked_from',
                fieldId,
                sourceRectypeId
            });
            i += 2;
            continue;
        }

        if(i === parts.length - 2 && this._isTermSubfield(parts[i + 1])){
            segments.push({
                kind: 'term',
                fieldId: part,
                subfield: parts[i + 1],
                type: _nodep?.type || ''
            });
            i += 2;
            continue;
        }

        segments.push({
            kind: 'field',
            fieldId: part,
            type: _nodep?.type || ''
        });
        i++;
    }

    return {
        rootRectypeId,
        segments
    };
},

_isTermSubfield: function(name){
    return ['label', 'term', 'code', 'conceptid', 'internalid', 'desc'].includes(name);
},

_headerSmartyName: function(headerKey){
    const map = {
        'rec_ID': 'recID',
        'rec_RecTypeID': 'recTypeID',
        'rec_Title': 'recTitle',
        'rec_URL': 'recURL',
        'rec_Modified': 'recModified',
        'rec_Tags': 'rec_Tags'
    };

    if(map[headerKey]) return map[headerKey];

    const tail = headerKey.substring(4);
    return 'rec' + tail.replace(/_([a-zA-Z])/g, (m, ch) => ch.toUpperCase());
},

_isRepeatableField: function(rectypeId, fieldId){
    return Number.parseInt($Db.rst(rectypeId, fieldId, 'rst_MaxValues')) !== 1;
},

_sameBranchRoot: function(n1, n2){

    if(!n1 || !n2 || !n1.data || !n2.data) return false;

    const c1 = n1.data.code || '';
    const c2 = n2.data.code || '';

    if(!c1 || !c2) return false;
    if(c1.indexOf('Relationship:') === 0 || c2.indexOf('Relationship:') === 0) return false;

    const p1 = c1.split(':');
    const p2 = c2.split(':');

    if(p1[0] !== p2[0]) return false;

    const len = Math.min(p1.length, p2.length);
    let i = 0;
    for(; i < len; i++){
        if(p1[i] !== p2[i]) break;
    }

    // group only when there is a shared branch beyond root rectype
    return i > 1;
},

_shouldUseWrap: function(_nodep, leaf){
    const dtype = _nodep?.type || leaf?.type || '';
    const key = _nodep?.key || '';
    const headerKey = leaf?.headerKey || '';

    return (
        dtype === 'geo' ||
        dtype === 'file' ||
        dtype === 'date' ||
        key === 'rec_URL' ||
        headerKey === 'rec_URL'
    );
},

_buildWrapExpression: function(_nodep, expr, inLoop){

    let res = '';
    let dtype = _nodep?.type || '';
    let key = _nodep?.key || '';

    res += '{wrap var=' + expr;

    if(!(_nodep?.data?.code && _nodep.data.code.indexOf('Relationship') === 0)){
        const origvalue = inLoop ? '' : '_originalvalue';

        if(_nodep.parent?.type !== 'enum' && (window.hWin.HEURIST4.util.isempty(dtype) || key === 'rec_URL')){
            res += ' dt="url"';
        }else if(dtype === 'geo'){
            res += origvalue + ' dt="geo"';
        }else if(dtype === 'date'){
            res += origvalue + ' dt="date" mode="0" calendar="native"';
        }else if(dtype === 'file'){
            res += origvalue + ' dt="file" width="300" height="auto" auto_play="0" show_artwork="0"';
        }
    }

    res += '}';
    return res;
},


_getRemarkForResource: function(seg, linkedRectypeId, isLoop){
    let name = $Db.rty(linkedRectypeId, 'rty_Name') || ('Record ' + linkedRectypeId);
    return name;
},


_indent: function(level){
    return '    '.repeat(Math.max(0, level || 0));
},


_escapeSmartyText: function(text){
    return String(text || '').replace(/\{/g, '&#123;').replace(/\}/g, '&#125;');
},


_renderRelationshipsInit: function(){
    return '{if !isset($r.Relationships)}\n'
        + '{$r.Relationships = $heurist->getRelatedRecords($r)}\n'
        + '{/if}\n';
},

_renderRelationshipLeafExpression: function(cfg){

    const {
        node,
        leaf,
        ifnull,
        addCaption,
        addRemark,
        indent
    } = cfg;

    const pad = this._indent(indent);
    const title = node?.title || node?.data?.name || 'Relationship';
    const remark = addRemark ? this._getRemark(node) : '';

    let expr = '';

    if(leaf.kind === 'relationship_header'){
        const mapped = this._headerSmartyName(leaf.headerKey);
        expr = mapped ? ('$Relationship.' + mapped) : '';

    }else if(leaf.kind === 'relationship_header_alias'){
        const mapped = this._relationshipHeaderSmartyName(leaf.headerAlias);
        expr = mapped ? ('$Relationship.' + mapped) : '';

    }else if(leaf.kind === 'relationship_prop'){
        expr = leaf.propName ? ('$Relationship.' + leaf.propName) : '';

    }else if(leaf.kind === 'relationship_field'){
        expr = leaf.fieldId ? ('$Relationship.relationRecord.f' + leaf.fieldId) : '';
    }

    if(!expr){
        return '';
    }

    let line = '';
    if(addCaption){
        line += this._escapeSmartyText(title + ': ');
    }
    line += '{' + expr + '}';
    if(addRemark && remark){
        line += ' {* ' + remark + ' *}';
    }

    let res = '';
    if(ifnull){
        res += pad + '{if ' + expr + '}\n';
        res += this._indent(indent + 1) + line + '\n';
        res += pad + '{/if}\n';
    }else{
        res += pad + line + '\n';
    }

    return res;
}, 

_buildGroupedRelationshipSnippet: function(nodes, ifnull, addCaption, addRemark){

    if(!nodes || nodes.length === 0) return '';

    let res = this._renderRelationshipsInit();
    res += '{foreach $r.Relationships as $Relationship name=relations}\n';

    for(const node of nodes){
        const parsed = this._parseNodeCode(node);
        if(!parsed || parsed.rootRectypeId !== 'Relationship' || !parsed.segments?.[0]) continue;

        res += this._renderRelationshipLeafExpression({
            node,
            leaf: parsed.segments[0],
            ifnull,
            addCaption,
            addRemark,
            indent: 1
        });
    }

    res += '{/foreach}\n';
    return res;
},  

_relationshipHeaderSmartyName: function(name){
    if(!name) return '';

    const key = String(name).trim().toLowerCase();

    const map = {
        'title': 'recTitle',
        'rectitle': 'recTitle',

        'url': 'recURL',
        'recurl': 'recURL',

        'id': 'recID',
        'ids': 'recID',
        'recid': 'recID',

        'typeid': 'recTypeID',
        'rectypeid': 'recTypeID',

        'type': 'recTypeName',
        'typename': 'recTypeName',
        'rectypename': 'recTypeName',

        'modified': 'recModified',
        'recmodified': 'recModified',

        'tag': 'rec_Tags',
        'tags': 'rec_Tags',
        'rectags': 'rec_Tags'
    };

    return map[key] || '';
},

_getGroupKey: function(node){
    const parsed = this._parseNodeCode(node);
    if(!parsed || !parsed.segments?.length) return '';

    if(parsed.rootRectypeId === 'Relationship'){
        return 'Relationship';
    }

    const parts = [parsed.rootRectypeId];

    for(let i = 0; i < parsed.segments.length; i++){
        const seg = parsed.segments[i];

        if(seg.kind === 'resource'){
            parts.push('lt' + seg.fieldId, seg.targetRectypeId);
            continue;
        }

        if(seg.kind === 'field'){
            parts.push('f' + seg.fieldId);
            break;
        }

        if(seg.kind === 'term'){
            // group all subfields of the same enum field together
            parts.push('f' + seg.fieldId);
            break;
        }

        if(seg.kind === 'header'){
            parts.push('header:' + seg.headerKey);
            break;
        }

        if(seg.kind === 'relationship_field'){
            parts.push('relfield:' + seg.fieldId);
            break;
        }

        if(seg.kind === 'relationship_header'){
            parts.push('relheader:' + seg.headerKey);
            break;
        }

        if(seg.kind === 'relationship_header_alias'){
            parts.push('relheaderalias:' + String(seg.headerAlias).toLowerCase());
            break;
        }

        if(seg.kind === 'relationship_prop'){
            parts.push('relprop:' + seg.propName);
            break;
        }
    }

    return parts.join(':');
},

_getBranchKey: function(node){
    const parsed = this._parseNodeCode(node);
    if(!parsed || !parsed.segments?.length) return '';

    if(parsed.rootRectypeId === 'Relationship'){
        return 'Relationship';
    }

    const parts = [parsed.rootRectypeId];

    for(const seg of parsed.segments){
        if(seg.kind === 'resource'){
            parts.push('lt' + seg.fieldId, seg.targetRectypeId);
        }else if(seg.kind === 'linked_from'){
            parts.push('lf' + seg.fieldId, seg.sourceRectypeId);
        }else{
            break; // stop before header/field/term leaf
        }
    }

    return parts.join(':');
},

_getLinkedFromVarName: function(rectypeId, fieldId, plural){
    const base = 'lf_t' + rectypeId + '_f' + fieldId;
    return plural ? (base + 's') : base;
},

});

