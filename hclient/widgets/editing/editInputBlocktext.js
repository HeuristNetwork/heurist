/**
* Widget for input controls on edit form
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
import "./editInputBase.js";

$.widget( "heurist.editInputBlocktext", $.heurist.editInputBase, {

    // default options
    options: {
        force_displayheight: null   //height in rows
    },
    
    _create: function() {
        
            this._super();
        
            var $inputdiv = this.element;
            
            $inputdiv.uniqueId();
            
            var $input = $( "<textarea>",{rows:2}) //min number of lines
            .val(this._value)
            .addClass('text ui-widget-content ui-corner-all')
            .css({'overflow-x':'hidden'})
            .keydown(function(e){
                if (e.keyCode == 65 && e.ctrlKey) {
                    e.target.select();
                }    
            })
            .keyup(function(){that.onChange();})
            .change(function(){that.onChange();})
            .appendTo( $inputdiv );
            
            this._input = $input;
            
            var that = this;

            //IJ 2021-09-09 - from now dheight is max height in lines - otherwise the height is auto
            function __adjustTextareaHeight(){
                $input.attr('rows', 2);
                var dheight = that.f('rst_DisplayHeight');  //max height 
                var lht = parseInt($input.css('lineHeight'),10); 
                if(!(lht>0)) lht = parseInt($input.css('font-size')); //*1.3
                
                var cnt = ($input.prop('scrollHeight') / lht).toFixed(); //visible number of lines
                if(cnt>0){
                    if(cnt>dheight && dheight>2){
                        $input.attr('rows', dheight);    
                    }else{
                        $input.attr('rows', cnt);        
                    }
                }
            }
            
            //count number of lines
            if(!this.options.force_displayheight){
                setTimeout(__adjustTextareaHeight, 1000);
            }else{
                $input.attr('rows', this.options.force_displayheight);
            }
            
            if(this.configMode && this.configMode['thematicmap']){ //-----------------------------------------------

                    var $btn_edit_switcher = $( '<span>themes editor</span>', {title: 'Open thematic maps editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                        .addClass('smallbutton btn_add_term')
                        .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                    
                    this._on( $btn_edit_switcher, { click: function(){
                        
                            var current_val = window.hWin.HEURIST4.util.isJSON($input.val());
                            if(!current_val) current_val = [];
                            window.hWin.HEURIST4.ui.showRecordActionDialog(
                            'thematicMapping',
                            {maplayer_query: this.configMode['thematicmap']===true?null:this.configMode['thematicmap'], //query from map layer
                            thematic_mapping: current_val,
                                onClose: function(context){
                                    if(context){
                                        var newval = window.hWin.HEURIST4.util.isJSON(context);
                                        newval = (!newval)?'':JSON.stringify(newval);
                                        $input.val(newval);
                                        that.onChange();
                                    }
                                }}                     
                            );
                    }});
            }else
            if( this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']
            //&& this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']
            && this.options.dtID > 0)
            {
                
                var eid = $inputdiv.attr('id')+'_editor';
                
                //hidden textarea for tinymce editor
                var $editor = $( "<textarea>")
                .attr("id", eid)
                //.addClass('text ui-widget-content ui-corner-all')
                .css({'overflow':'auto',display:'flex',resize:'both'})
                .appendTo( $('<div>').css({'display':'inline-block'}).appendTo($inputdiv) );
                $editor.parent().hide();

                //hidden textarea for codemirror editor
                var codeEditor = null;
                if(typeof EditorCodeMirror !== 'undefined'){
                    codeEditor = new EditorCodeMirror($input);
                }
                
                var $btn_edit_switcher;

                if(this.options.recordset && this.options.recordset.entityName == 'Records'){

                    var $clear_container = $('<span id="btn_clear_container"></span>').appendTo( $inputdiv );

                    $btn_edit_switcher = $('<div class="editor_switcher">').appendTo( $inputdiv );

                    $('<span>text</span>')
                        .attr('title', 'plain text or source, showing markup')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'text-decoration': 'underline'})
                        .appendTo($btn_edit_switcher);

                    $('<span>wysiwyg</span>')
                        .attr('title', 'rendering of the text, taken as html')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'margin-left': '10px'})
                        .appendTo($btn_edit_switcher);

                    if(codeEditor){
                        $('<span>codeeditor</span>')
                            .attr('title', 'direct edit html in code editor')
                            .addClass('smallbutton')
                            .css({cursor: 'pointer', 'margin-left': '10px'})
                            .appendTo($btn_edit_switcher);
                            

                        /*DEBUG  
                        var btn_debug = $('<span>debug</span>')
                            .addClass('smallbutton')
                            .css({cursor: 'pointer', 'margin-left': '10px'})
                            .appendTo($btn_edit_switcher);
                            
                        this._on( btn_debug, {       
                            click:function(event){
                            
                            if(!window.hWin.layoutMgr){
                                hLayoutMgr(); //init global var layoutMgr
                            }
                                    
                            //cfg_widgets is from layout_defaults.js
                            window.hWin.layoutMgr.convertJSONtoHTML(that.getValues()[0]);
                        }});
                        */
                            
                    }
                        
                    $('<span>table</span>')
                        .attr('title', 'treats the text as a table/spreadsheet and opens a lightweight spreadsheet editor')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'margin-left': '10px'})
                        .hide() // currently un-available
                        .appendTo($btn_edit_switcher);
                }else{
                    $btn_edit_switcher = $( '<span>wysiwyg</span>', {title: 'Show/hide Rich text editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')      btn_add_term
                        .addClass('smallbutton')
                        .css({'line-height': '20px','vertical-align':'top', cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                }

                function __openRecordLink(node){

                    const org_href = $(node).attr('href');
                    let href = '';

                    if(org_href.indexOf('/') !== -1){ // check if href is in the format of record_id/custom_report

                        let parts = org_href.split('/');

                        if(parts.length == 2 && window.hWin.HEURIST4.util.isNumber(parts[0]) && parts[0] > 0){
                            href = `${window.hWin.HAPI4.baseURL}viewers/smarty/showReps.php?publish=1&db=${window.hWin.HAPI4.database}&q=ids:${parts[0]}&template=${parts[1]}`
                        }
                    }else 
                    if(window.hWin.HEURIST4.util.isNumber(org_href) && org_href > 0){ // check if href is just the record id
                        href = `${window.hWin.HAPI4.baseURL}?recID=${org_href}&fmt=html&db=${window.hWin.HAPI4.database}`;
                    }

                    if(!window.hWin.HEURIST4.util.isempty(href)){ // use different url
                        $(node).attr('href', href);
                        setTimeout((ele, org_href) => { $(ele).attr('href', org_href); }, 500, node, org_href);
                    }
                }

                function __showEditor(is_manual){
                    
                    if(typeof tinymce === 'undefined') return false; //not loaded yet

                    if(!Object.hasOwn(window.hWin.HAPI4.dbSettings, 'TinyMCE_formats')){ // retrieve custom formatting

                        window.hWin.HAPI4.SystemMgr.get_tinymce_formats({a: 'get_tinymce_formats'}, function(response){

                            if(response.status != window.hWin.ResponseStatus.OK){

                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = {};
                            }else if(!window.hWin.HEURIST4.util.isObject(response.data)){
                                window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = {};
                            }else{
                                window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = response.data;
                            }

                            __showEditor(is_manual);
                        });

                        return;
                    }

                    var eid = '#'+$inputdiv.attr('id')+'_editor';

                    $(eid).parent().css({display:'inline-block'}); //.height($input.height()+100)
                    //to show all toolbar buttons - minimum 768
                    $(eid).width(Math.max(768, $input.width())).height($input.height()).val($input.val()); 

                    let custom_formatting = window.hWin.HAPI4.dbSettings.TinyMCE_formats;

                    let style_formats = Object.hasOwn(custom_formatting, 'style_formats') && custom_formatting.style_formats.length > 0 
                                            ? [ { title: 'Custom styles', items: custom_formatting.style_formats } ] : [];

                    if(Object.hasOwn(custom_formatting, 'block_formats') && custom_formatting.block_formats.length > 0){
                        style_formats.push({ title: 'Custom blocks', items: custom_formatting.block_formats });
                    }

                    let is_grayed = $input.hasClass('grayed') ? 'background: rgb(233 233 233) !important' : '';
                    
                                          
                        /*
                        "webfonts":{
                            "LinuxLibertine":"@import url('settings/linlibertine-webfont.css');"
                        }
                        */
                    let font_family = 'Helvetica,Arial,sans-serif';
                    let webfonts = '';
                    if(Object.hasOwn(custom_formatting, 'webfonts')){
                        let fams = Object.keys(custom_formatting.webfonts);
                        for(let i=0; i<fams.length; i++){
                            if(Object.hasOwn(custom_formatting.webfonts, fams[i])){
                                webfonts = webfonts + custom_formatting.webfonts[fams[i]];
                                font_family = fams[i];
                            }
                        }
                    }
                    
                    let custom_webfonts = `${webfonts} body { font-size: 8pt; font-family: ${font_family}; ${is_grayed} }`;

                    tinymce.init({
                        //target: $editor, 
                        //selector: '#'+$inputdiv.attr('id'),
                        selector: eid,
                        menubar: false,
                        inline: false,
                        branding: false,
                        elementpath: false,
                        statusbar: true,        
                        resize: 'both', 

                        //relative_urls : false,
                        //remove_script_host : false,
                        //convert_urls : true, 
                        
                        relative_urls : true,
                        remove_script_host: false,
                        //document_base_url : window.hWin.HAPI4.baseURL,
                        urlconverter_callback : 'tinymceURLConverter',

                        entity_encoding:'raw',
                        inline_styles: true,    
                        content_style: `${custom_webfonts} ${custom_formatting.content_style}`,
                        
                        min_height: ($input.height()+110),
                        max_height: ($input.height()+110),
                        autoresize_bottom_margin: 10,
                        autoresize_on_init: false,
                        image_caption: true,
                        
                        setup:function(editor) {

                            if(editor.ui){
                                // ----- Custom buttons -----
                                // Insert Heurist media
                                editor.ui.registry.addButton('customHeuristMedia', {
                                    icon: 'image',
                                    text: 'Media',
                                    onAction: function (_) {  //since v5 onAction in v4 onclick
                                        that._addHeuristMedia();
                                    }
                                });
                                // Insert figcaption to image/figure
                                editor.ui.registry.addButton('customAddFigCaption', {
                                    icon: 'comment',
                                    text: 'Caption',
                                    tooltip: 'Add caption to current media',
                                    onAction: function (_) {
                                        that._addMediaCaption();
                                    },
                                    onSetup: function (button) {

                                        const activateButton = function(e){

                                            //let is_disabled = e.element.nodeName.toLowerCase() !== 'img'; // is image element
                                            button.setDisabled(e.element.nodeName.toLowerCase() !== 'img');
                                        };

                                        editor.on('NodeChange', activateButton);
                                        return function(button){
                                            editor.off('NodeChange', activateButton);
                                        }
                                    }
                                });
                                // Insert link to Heurist record
                                editor.ui.registry.addButton('customHeuristLink', {
                                    icon: 'link',
                                    text: 'Record',
                                    tooltip: 'Add link to Heurist record',
                                    onAction: function (_) {  //since v5 onAction in v4 onclick
                                        selectRecord(null, function(recordset){
                                            
                                            let record = recordset.getFirstRecord();
                                            const record_id = recordset.fld(record,'rec_ID');
                                            let href = `${record_id}_${window.hWin.HEURIST4.util.random()}`;
                                            tinymce.activeEditor.execCommand('mceInsertLink', false, href);
                                            
                                            let $link = $(tinymce.activeEditor.selection.getNode());
                                            if(!$link.is('a')){
                                                $link = $link.find(`a[href="${href}"]`);
                                            }
                                            if($link.length == 0){
                                                $link = $(tinymce.activeEditor.contentDocument).find(`a[href="${href}"]`);
                                            }

                                            $link.attr('href', record_id).attr('data-mce-href', record_id);

                                            // Customise link's target and whether to open in default rec viewer or custom report
                                            let $dlg;
                                            let msg = `Inserting a link to ${recordset.fld(record,'rec_Title')}<br><br>`
                                                    + 'Open record in: <select id="a_recview"></select><br><br>'
                                                    + 'Open link as: <select id="a_target"><option value="_blank">New tab</option><option value="_self">Within window</option><option value="_popup">Within popup</option></select><br>';

                                            let btns = {};
                                            btns[window.HR('Insert')] = function(){

                                                let target = $dlg.find('#a_target').val();
                                                let template = $dlg.find('#a_recview').val();

                                                $link.attr('target', target);

                                                if(!window.hWin.HEURIST4.util.isempty(template)){

                                                    let new_href = record_id + '/' + template;

                                                    $link.attr('href', new_href).attr('data-mce-href', new_href);
                                                }

                                                if(!$link.text().match(/\w/)){ // if content is empty, replace with href
                                                    $link.text($link.attr('href'));
                                                }

                                                $dlg.dialog('close');
                                            };
                                            btns[window.HR('Cancel')] = function(){ $dlg.dialog('close'); }

                                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Inserting link to Heurist record', ok: window.HR('Insert link'), cancel: window.HR('Cancel')}, 
                                                {default_palette_class: 'ui-heurist-populate'});

                                            window.hWin.HEURIST4.ui.createTemplateSelector($dlg.find('#a_recview'), [{key: '', title: 'Default record viewer'}]);

                                        });
                                    }
                                });
                                // Insert horizontal rule
                                editor.ui.registry.addButton('customHRtag', {
                                    text: '&lt;hr&gt;',
                                    onAction: function (_) {
                                        tinymce.activeEditor.insertContent( '<hr>' );
                                    }
                                });
                                // Clear text formatting - to replace the original icon
                                editor.ui.registry.addIcon('clear-formatting', `<img style="padding-left: 5px;" src="${window.hWin.HAPI4.baseURL}hclient/assets/clear_formatting.svg" />`)
                                editor.ui.registry.addButton('customClear', {
                                    text: '',
                                    icon: 'clear-formatting',
                                    tooltip: 'Clear formatting',
                                    onAction: function (_) {
                                        tinymce.activeEditor.execCommand('RemoveFormat');
                                    }
                                });
                            }else{
                                editor.addButton('customHeuristMedia', {
                                    icon: 'image',
                                    text: 'Media',
                                    onclick: function (_) {  //since v5 onAction in v4 onclick
                                        that._addHeuristMedia();
                                    }
                                });
                            }

                            // ----- Event handlers -----
                            let has_initd = false, is_blur = false;
                            editor.on('init', function(e) {
                                let $container = $(editor.editorContainer);

                                if($container.parents('.editForm').length == 1){
                                    let max_w = $container.parents('.editForm').width(); 
                                    if($container.width() > max_w - 200){
                                        $container.css('width', (max_w - 245) + 'px');
                                    }
                                }

                                has_initd = true;
                            });

                            editor.on('change', function(e) {

                                var newval = editor.getContent();
                                var nodes = $.parseHTML(newval);
                                if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
                                    (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
                                { 
                                    //remove the only tag
                                    $input.val(nodes[0].textContent);
                                }else{
                                    $input.val(newval);     
                                }

                                // check if editor is 'expanded'
                                if(editor.settings.max_height != null){
                                    editor.settings.max_height = null;
                                    tinymce.activeEditor.execCommand('mceAutoResize');
                                }

                                //$input.val( ed.getContent() );
                                that.onChange();
                            });

                            editor.on('focus', (e) => { // expand text area
                                editor.settings.max_height = null;
                                tinymce.activeEditor.execCommand('mceAutoResize');
                            });

                            editor.on('blur', (e) => { // collapse text area
                                is_blur = true;
                                editor.settings.max_height = editor.settings.min_height;
                                editor.settings.autoresize_min_height = null;
                                tinymce.activeEditor.execCommand('mceAutoResize');
                            });

                            editor.on('ResizeContent', (e) => {
                                if(is_blur){
                                    is_blur = false;
                                }else if(has_initd){
                                    editor.settings.max_height = null;
                                    editor.settings.autoresize_min_height = $(editor.container).height();
                                }
                            });

                            // Catch links opening
                            editor.on('contextmenu', (e) => {
                                setTimeout(() => {

                                    $(document).find('.tox-menu [title="Open link"]').on('click', function(e){

                                        let node = tinymce.activeEditor.selection.getNode();

                                        __openRecordLink(node);
                                    });

                                }, 500);
                            });

                            editor.on('click', (e) => {

                                let node = tinymce.activeEditor.selection.getNode();

                                if((e.ctrlKey || e.metaKey) && node.tagName == 'A'){
                                    __openRecordLink(node);
                                }
                            });
                        },
                        init_instance_callback: function(editor){
                            let html = '<span class="tox-tbtn__select-label">URL</span>';
                            $(editor.container).find('.tox-tbtn[title="Insert/edit link"]').append(html);

                            $(editor.container).find('.tox-split-button[title="Background color"]').attr('title', 'Highlight text');
                        },
                        plugins: [ //contextmenu, textcolor since v5 in core
                            'advlist autolink lists link image preview ', //anchor charmap print 
                            'searchreplace visualblocks code fullscreen',
                            'media table paste help autoresize'  //insertdatetime  wordcount
                        ],      
                        //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
                        toolbar: ['styleselect | fontselect fontsizeselect | bold italic forecolor backcolor customClear customHRtag | customHeuristMedia customAddFigCaption customHeuristLink link | align | bullist numlist outdent indent | table | help'],
                        formats: custom_formatting.formats,
                        style_formats_merge: true,
                        style_formats: style_formats,
                        //block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre;Quotation=blockquote',
                        content_css: [
                            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
                            //,'//www.tinymce.com/css/codepen.min.css'
                        ]
                    });
                    $input.hide();

                    if($btn_edit_switcher.is('span')){
                        $btn_edit_switcher.text('text'); 
                    }else{
                        cur_action = 'wysiwyg';
                        $btn_edit_switcher.find('span').css('text-decoration', '');
                        $btn_edit_switcher.find('span:contains("wysiwyg")').css('text-decoration', 'underline');
                    }
                    
                    return true;
                } // _showEditor()

                // RT_ indicates the record types affected, DT_ indicates the fields affected
                // DT_EXTENDED_DESCRIPTION (field concept 2-4) is the page content or header/footer content
                var isCMS_content = (( 
                         this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'] ||
                         this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']) &&
                        (this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'] || 
                         this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'] || 
                         this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER']));

                var cur_action = 'text', cms_div_prompt = null, cms_label_edit_prompt = null;

                if( isCMS_content ){
                    
                    cur_action = '';
                    
                    var fstatus = '';
                    var fname = 'Page content';
                    
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'] &&
                       this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER']){
                        fname = 'Custom header';
                        fstatus = (window.hWin.HEURIST4.util.isempty(this._value))
                            ?'No custom header defined'
                            :'Delete html from this field to use default page header.';
                    }
                    
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'] &&
                       this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER']){
                        fname = 'Custom footer';
                        fstatus = (window.hWin.HEURIST4.util.isempty(this._value))
                            ?'No custom footer defined'
                            :'Delete html from this field to use default page footer.';
                    }
                                
                    // Only show this for the CMS Home record type and home page content            
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']
                        && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']){
                            fstatus = 'Leave this field blank if you wish the first menu entry to load automatically on startup.';    
                    }
                    
                    // Only show this message for CONTENT fields (of home page or menu pages) which can be directly edited in the CMS editor 
                    cms_div_prompt = $('<div style="line-height:20px;display:inline-block;"><b>Please edit the content of the '
                                + fname
                                + ' field in the CMS editor.<br>'
                                + fstatus+'</b></div>')
                                .insertBefore($input);
                    $input.hide();
                    
                    $('<br>').insertBefore($btn_edit_switcher);

                    cms_label_edit_prompt = $('<span>Advanced users: edit source as </span>')
                        .css({'line-height': '20px'}).addClass('smallbutton')
                        .insertBefore( $btn_edit_switcher );
                    $btn_edit_switcher.css({display:'inline-block'});
                                        
                    var $cms_dialog = window.hWin.HEURIST4.msg.getPopupDlg();
                    if($cms_dialog.find('.main_cms').length>0){ 
                        //opened from cms editor
                        //$btn_edit_switcher.hide();
                    }else{
                        //see manageRecords for event handler
                        cms_div_prompt.find('span')
                            .css({cursor:'pointer','text-decoration':'underline'})
                            .attr('data-cms-edit', 1)
                            .attr('data-cms-field', this.options.dtID)
                            .attr('title','Edit website content in the website editor');   
                            
                    }
                }
                
                if($btn_edit_switcher.is('div')){

                    this._on($btn_edit_switcher.find('span'), { 
                        click: function(event){
                            
                            if(cms_label_edit_prompt){
                               cms_label_edit_prompt.hide(); 
                               cms_div_prompt.hide(); 
                            }

                            var sel_action = $(event.target).text();
                            sel_action = sel_action == 'codeeditor' && !codeEditor ? 'text' : sel_action;
                            if(cur_action == sel_action) return;
                            
                            $btn_edit_switcher.find('span').css('text-decoration', '');
                            $(event.target).css('text-decoration', 'underline');

                            var eid = '#'+$inputdiv.attr('id')+'_editor';

                            //hide previous
                            if(cur_action=='wysiwyg'){
                                tinymce.remove(eid);
                                $(eid).parent().hide();
                            }else if(cur_action=='codeeditor'){
                                codeEditor.hideEditor();
                            }
                            //show now
                            if(sel_action == 'codeeditor'){
                                codeEditor.showEditor();
                            }if(sel_action == 'wysiwyg'){
                                __showEditor(true); //show tinymce editor
                            }else if(sel_action == 'text'){
                                $input.show();
                                __adjustTextareaHeight();
                            }
                           
                            cur_action = sel_action;
                        }
                    });

                }else{
                    
                    this._on( $btn_edit_switcher, { 
                        click: function(){
                            var eid = '#'+$inputdiv.attr('id')+'_editor';                    
                            if($input.is(':visible')){
                                if (__showEditor(true)) //show tinymce editor
                                    $btn_edit_switcher.text('text');
                            }else{
                                $btn_edit_switcher.text('wysiwyg');
                                $input.show();
                                tinymce.remove(eid);
                                $(eid).parent().hide();
                                __adjustTextareaHeight();
                            }
                        }
                    });
                }

                //what is visible initially
                if( !isCMS_content && this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_KML'] ) {
                    var nodes = $.parseHTML(this._value);
                    if(nodes && (nodes.length>1 || (nodes[0] && nodes[0].nodeName!='#text'))){ //if it has html - show editor at once
                        setTimeout(__showEditor, 1200); 
                    }
                }
                
            } 
        
    },
    
    _destroy: function() {
        
            var eid = '#'+this.element.attr('id')+'_editor';
            //tinymce.remove('#'+input.attr('id')); 
            tinymce.remove(eid);
            $(eid).parent().remove(); //remove editor element
            //$(eid).remove(); 

            eid = '#'+this.element.attr('id')+'_codemirror';
            $(eid).parent().remove(); //remove editor element
    
    },

    /**
    *  browse for heurist uploaded/registered files/resources and add player link        
    */
    _addHeuristMedia: function(){

        var that = this;

        var popup_options = {
            isdialog: true,
            select_mode: 'select_single',
            edit_addrecordfirst: false, //show editor atonce
            selectOnSave: true,
            select_return_mode:'recordset', //ids or recordset(for files)
            filter_group_selected:null,
            //filter_groups: this.configMode.filter_group,
            onselect:function(event, data){

                if(data){

                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        var recordset = data.selection;
                        var record = recordset.getFirstRecord();

                        var thumbURL = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                        +"&thumb="+recordset.fld(record,'ulf_ObfuscatedFileID');

                        var playerTag = recordset.fld(record,'ulf_PlayerTag');

                        that._addMediaCaption(playerTag);

                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    },

    /**
    *  Add caption to media
    */
    _addMediaCaption: function(content = null){

        let is_insert = false;
        let node = null;

        if(content){
            is_insert = true;
        }else{

            node = tinymce.activeEditor.selection.getNode();
            if(node.parentNode.nodeName.toLowerCase() == 'figure'){ // insert new figcaption
                node = document.createElement('figcaption');
                tinymce.activeEditor.selection.getNode().parentNode.appendChild(node);
            }else{ // replace selected content with new wrapper
                node = null;
            }

            content = tinymce.activeEditor.selection.getContent();
        }

        let $dlg;
        let msg = 'Enter a caption below, if you want one:<br><br>'
            + '<textarea rows="6" cols="65" id="figcap"></textarea>';
        
        let btns = {};
        btns[window.HR('Add caption')] = () => {
            let caption = $dlg.find('#figcap').val();

            if(caption){

                if(node != null){
                    node.innerText = caption;
                    return;
                }
                content = '<figure>'+ content +'<figcaption>'+ caption +'</figcaption></figure>';

                if(is_insert){
                    tinymce.activeEditor.insertContent( content );
                }else{
                    tinymce.activeEditor.selection.setContent( content );
                }
            }

            $dlg.dialog('close');
        };
        btns[window.HR('No caption')] = () => {
            if(is_insert){
                tinymce.activeEditor.insertContent( content );
            }
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, 
            {title: 'Adding caption to media', yes: window.HR('Add caption'), no: window.HR('No caption')}, 
            {default_palette_class: 'ui-heurist-populate'}
        );
    },

    /**
    * 
    */
    setWidth: function(dwidth){

        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            
            this._input.css('width', dwidth);
        }else{
              //if the size is greater than zero
              var nw = (this.detailType=='integer' || this.detailType=='float')?40:120;
              if (parseFloat( dwidth ) > 0){ 
                  nw = Math.round( 3+Number(dwidth) );
                    //Math.round(2 + Math.min(120, Number(dwidth))) + "ex";
              }
              this._input.css({'min-width':nw+'ex','width':nw+'ex'}); //was *4/3
        }
    },
    
    /**
    * put your comment there...
    */
    getValue: function(){
        var res = this._input?this._input.val().trim():'';  
        return res;
    },
    
    clearValue: function(){
        if(this._input){
            this._input.val('');   
            //@todo - swith to plain text editor
        }
    }
    
    
});