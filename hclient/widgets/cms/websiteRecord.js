/**
* websiteRecord.js - Editor wrapper for Heurist CMS pages. 
* 
* It inits direct and wyswyg editors over CMS pages
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

var cmsEditing;

function onPageInit(success){   

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
        return;
    }
    
    
    cmsEditing = new hCmsEditing();
    
}   

function loadPageContent(pageid){
    cmsEditing.loadPageById(pageid);
}


/*
workflow
_init - #main-menu navigation wizard onselect -> __iniLoadPageById warn for changes -> _hideEditor -> __loadPageById
*/ 


// 
//  tinymce-body - textarea container with data - it becomes visible when editor is ON
//  .tox-edit-area (.mce-edit-area v3) > iframe - real editor
//  btn_inline_editor3 button invokes direct editor
//  btn_inline_editor button invokes wyswyg editor _editPageContent
//
//  last_save_content - page content before it was loaded into tinymce

    
function hCmsEditing(_options) {
    var _className = "CmsEditing",
        _version   = "0.4";
        
    var doc_body = $(document).find('body'); //.'body'

    var main_content = doc_body.find('#main-content');

    var main_menu = doc_body.find('#main-menu');
    var main_header = doc_body.find('#main-header');
    
    var home_pageid = main_content.attr('data-homepageid'),
        init_pageid = main_content.attr('data-initid'),
        is_viewonly = (main_content.attr('data-viewonly')==1),
        current_pageid = home_pageid,
        was_modified = false, //was modified and saved - on close need to reinit widgets
        last_save_content = null;
    var inlineEditorConfig;
    var is_edit_widget_open = false;

    var is_header_editor = false;
    var header_content_raw = null;
    var header_content_generated = true;
    
    var is_footer_editor = false;
    var footer_content_raw = null;
    var footer_content_generated = true;
    
    var original_editor_content = '';
    var LayoutMgr = new hLayout(); //to avoid interferene with  window.hWin.HAPI4.LayoutMgr  
    
    var codeEditor = null; //codemirror

    
    // define tinymce configuration
    // init main menu with listener __iniLoadPageById
    // init main-logo and editor buttons
    // call __iniLoadPageById with main page id
    function _init(_options) {
             
        //window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
        main_content.show();
        main_menu.hide();
        
        //cfg_widgets is from layout_defaults=.js 
        LayoutMgr.init(cfg_widgets, null);
        
        inlineEditorConfig = {
            selector: '.tinymce-body',
            //fixed_toolbar_container: '#main-header',
            menubar: false,
            inline: false,
            branding: false,
            elementpath: false,
            //statusbar: true,
            resize: false,
            //autoresize_on_init: false,
            //height: 300, //'100%',  //they said that this is entire height (including toolbar) alas it sets height of iframe only
            //max_height: 300,
            relative_urls : false,
            remove_script_host : false,
            convert_urls : true,            
            
            entity_encoding:'raw',
            inline_styles: true,
            content_style: "body {font-family: Helvetica,Arial,sans-serif;}",

            plugins: [
                'advlist autolink lists link image media preview', //anchor charmap print 
                'searchreplace visualblocks code fullscreen',
                'media table  paste help noneditable contextmenu textcolor'  
    //since v5 they are built in to the core: contextmenu textcolor
            ],      
            //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
            toolbar: ['formatselect | bold italic forecolor backcolor  | customHeuristMedia link | align | bullist numlist outdent indent | table | removeformat | help | customAddWidget customAddTemplate customSaveButton customCloseButton' ],  
            content_css: [
                '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
                //,'//www.tinymce.com/css/codepen.`min.css'
            ],                    
            powerpaste_word_import: 'clean',
            powerpaste_html_import: 'clean',
            
            setup:function(editor) {

                /* it does not work properly
                editor.on('change', function(e) {
                    was_modified = true;  
                });
                */
                editor.on('init', function(e) {
                    if(!is_header_editor){
//                        last_save_content = __getEditorContent();//keep value to restore
//console.log('keep on setip '+last_save_content);                        
                    }
                    // adds widget-design-header and widget-options and event handlers for edit/remove links
                    __initWidgetEditLinks(null);
                    
                    //adjust height
                    var itop = 68;
                    if($('.mce-top-area').length>0 && $('.mce-top-area').height()>0){
                        itop = $('.mce-top-area').height();   
                    }else if($('.tox-toolbar-overlord').length>0 && $('.tox-toolbar-overlord').height()>0){
                        itop = $('.tox-toolbar-overlord').height();
                    }
                    
                    
                    
                    var sheight = $('.tinymce-body').height() - itop;
                    sheight = ($('.tinymce-body').height() - itop<=100)?'90%':(sheight+'px');
                    $('.mce-edit-area > iframe').height( sheight );
                    $('.tox-edit-area > iframe').height( sheight );
                    $('.tox-edit-area').height( sheight );
                    
console.log( sheight );                    
                });
                
                editor.ui.registry.addButton('customHeuristMedia', {
                      icon: 'image',
                      text: 'Add Media',
                      onAction: function (_) {  //since v5 onAction in v4 onclick
                            __addHeuristMedia();
                      }
                    });
                
                
                editor.ui.registry.addButton('customAddWidget', { //since v5 .ui.registry
                      icon: 'plus',
                      text: 'Widget',
                      tooltip: 'Add database widget',
                      onAction: function (_) {  //since v5 onAction
                            __addEditWidget();
                      }
                    });

                editor.ui.registry.addButton('customAddTemplate', { //since v5 .ui.registry
                      icon: 'embed-page', //paste-text
                      text: 'Template',
                      tooltip: 'Insert Template',
                      onAction: function (_) {  //since v5 onAction
                         __insertTemplate();   
                      }
                    });
                                
                editor.ui.registry.addButton('customSaveButton', { //since v5 .ui.registry
                      icon: 'save',
                      text: 'Save',
                      onAction: function (_) {  //since v5 onAction in v4 onclick
                            __saveChanges(false);
                      }
                    });            

                editor.ui.registry.addButton('customCloseButton', {
                      icon: 'checkmark',
                      text: 'Done',
                      onAction: function (_) {
                          __iniLoadPageById(0); //reload current page
                      }
                    });            
                
            }

        };//end inlineEditorConfig
        

        // Open 'Website Design' editor
        $("#main-logo, #main-title").click(function(event){
            window.hWin.HEURIST4.ui.openRecordEdit(home_pageid, null,
            {selectOnSave:true, edit_obstacle: false, onselect: 
                function(event, res){
                    if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                        // Need to refresh page content and header
                        location.reload();
                    }
                }
            });
        });
        
        setTimeout(function(){
            __alignButtons();
            
            var bg_color = main_header.css('background');

            header_content_raw = main_header.html();
            
            //init main menu in header
            var topmenu = main_menu; //$('#main-menu');
            if(topmenu.length>0){
                topmenu.attr('data-heurist-app-id','heurist_Navigation');
                header_content_generated = (topmenu.attr('data-generated')==1);
                topmenu.attr('data-generated', 0).show();

                topmenu.hide();
            
                LayoutMgr.appInitFromContainer( document, topmenu.parent(),
                    {heurist_Navigation:{menu_recIDs:home_pageid
                    , use_next_level:true
                    , orientation:'horizontal'
                    , onmenuselect:__iniLoadPageById    //load page on select menu item
                    //aftermenuselect: afterPageLoad,  //function in header websiteRecord.php
                    , toplevel_css:{background:'none'} //bg_color  //'rgba(112,146,190,0.7)' ,color:'white','margin-right':'24px'
                    }} ); 
                
                topmenu.show();
            }
            
            //init home page content
            __iniLoadPageById( init_pageid>0?init_pageid:home_pageid );

            
            footer_content_raw = $('#page-footer > .page-footer-content').html();
            footer_content_generated = (footer_content_raw=='');
//console.log('foter:'+footer_content_raw);

            $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
            
            //window.hWin.HEURIST4.msg.sendCoverallToBack();
            
        },500);

        var itop = main_header.height();
            
        $('<input id="edit_mode" type="hidden"/>').appendTo(main_content.parent());
            
        //$('<a href="#" id="btn_refresh_menu" style="display:none;font-size:1.2em;font-weight:bold;color:blue;">refresh menu</a>').click( __reloadMainMenu );
        
        $('<textarea class="tinymce-body" style="position:absolute;left:0;right:2px;top:0;bottom:0;display:none"></textarea>')
            .appendTo(main_content.parent());

        //codemirror container
        $('<div id="codemirror-body" style="position:absolute;left:0;right:2px;top:0;bottom:0;display:none;border:lightblue 1px dotted"></div>')
            .hide()
            .appendTo(main_content.parent());

        
        $('<a href="#" id="btn_inline_editor">Edit page content</a>')
                .appendTo(doc_body).addClass('ui-front cms-button') //was body > .ent_wrapper:first
                .click( _editPageContent )
                .show();

        //switch to direct edit OR save direct edit                        
        $('<a href="#" id="btn_inline_editor3">source</a>')
                .appendTo(doc_body).addClass('ui-front cms-button')
                .click( _editPageSource )
                .show();
            
            
                $('<a href="#" id="btn_inline_editor4"></a>') //edit page settings
                .appendTo(doc_body).addClass('cms-button')
                .click(_editPageRecord)
                .show();

        $('<a href="#" id="btn_inline_editor5">Cancel</a>')
            .appendTo(doc_body)
            .addClass('ui-front cms-button')
            .click(function (event) {
                __hideEditor();
//console.log(original_editor_content);
                //restore original
                $('.tinymce-body').val(original_editor_content);
                
                window.hWin.HEURIST4.util.stopEvent(event);
                return false;
            })
            .show();

    }//_init  
    
    
    //
    // init codemirror editor
    //
    function _initCodeEditor(content) {
        
        if(codeEditor==null){

                codeEditor = CodeMirror(document.getElementById('codemirror-body'), {
                    mode           : "htmlmixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : false,
                    matchBrackets  : true,
                    smartIndent    : true,
                    /*extraKeys: {
                        "Enter": function(e){
                            insertAtCursor(null, "");
                        }
                    },*/
                    onFocus:function(){},
                    onBlur:function(){}
                });
        }
        
        
        //preformat - break lines for co
        var ele = $('<div>').html(content);
        $.each(ele.find('div[data-heurist-app-id]'),function(i,el){
            var s = $(el).text();
            s = "\n"+s.replace(/,/g, ", \n");
            $(el).text(s);
        });
        content = ele.html();
        
        if(window.hWin.HEURIST4.util.isempty(content)) content = ' ';
        
        codeEditor.setValue(content);
        $('#codemirror-body').show();

        //autoformat
        setTimeout(function(){
                    $('div.CodeMirror').css('height','100%').show();
                    
                    var totalLines = codeEditor.lineCount();  
                    codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
                    codeEditor.scrollTo(0,0);
                    codeEditor.setCursor(0,0); //clear selection
                    
                    codeEditor.focus()
                    //setTimeout(function(){;},200);
                },500);
    }
    
            
    //
    // menu listener - init page switch - warns for  changes and hides editor
    //            
    function __iniLoadPageById( pageid ){                    
     
       var edited_content = __getEditorContent();
     
       var is_changed = (edited_content!=null);
       if(is_changed){
           
           edited_content = edited_content.replace(/(\r\n|\n|\r|\&nbsp;)/gm, "");
       
//console.log(edited_content);       
//console.log(last_save_content.replace(/(\r\n|\n|\r|\&nbsp;)/gm, ""));       

           if(is_header_editor){
                is_changed = is_changed && header_content_raw.replace(/(\r\n|\n|\r|\&nbsp;)/gm, "") != edited_content;
           }else if(is_footer_editor){
                is_changed = is_changed && footer_content_raw.replace(/(\r\n|\n|\r|\&nbsp;)/gm, "") != edited_content;
           }else{
                is_changed = is_changed && last_save_content.replace(/(\r\n|\n|\r|\&nbsp;)/gm, "") != edited_content;
           }
       }
       
       if( is_changed ){ //was_modified
            
            var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(
                '<br>Web page content has been modified',
                    {'Save':function(){__saveChanges( true, pageid );
                        var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                        $dlg2.dialog('close');},
                        'Cancel':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');},
                        'Abandon changes':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');__hideEditor(pageid);}},
                                'Content modified');
            //$dlg2.parents('.ui-dialog').css('font-size','1.2em');    
            
        }else{
            __hideEditor( pageid ); //hide current editor and loads new page
        }
        
    }
            
    //
    // loads content of pageid  
    //
    function __loadPageById( pageid ){
        
        if(pageid>0){
            window.hWin.HEURIST4.msg.bringCoverallToFront(doc_body.find('.ent_wrapper'));
            
            current_pageid = pageid;

            //main_content = doc_body.find('#main-content'); DEBUG
            //main_content.html('LOADED '+pageid);return; DEBUG

            main_content.empty();
            main_content.load(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&field=1&recid='+pageid, function()
                {
                        var show_page_title = false;
                        var pagetitle = main_content.find('h2.webpageheading');
                        if(pagetitle.length>0)
                        {
                            
                            if(pageid==home_pageid){
                                //pagetitle.empty().hide();
                            } 
                                  
                            if(pagetitle.attr('date-empty')==1){
                                pagetitle.attr('date-empty',0);
                                
                                if(pageid==home_pageid){
                                    window.hWin.HEURIST4.msg.showMsgFlash('Home page is empty. First menu item will be loaded',3000);
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgDlg(
                                        'This menu item does not have associated page content.'
                                        +'<br>It will not be selectable in the website. '
                                        +'<br>We recommend this for parent menus.',null,null,
                                        {my:'left top', at:'left+200 top+100', of: doc_body.find('#main-content-container')});    
                                }
                            }
                            
                            var title_container = doc_body.find('#main-pagetitle');
                            if(title_container.length>0){
                                //move page title to header - visibility is set in websiteRecord
                                title_container.empty().show();
                                pagetitle.detach().appendTo(title_container);
                                show_page_title = pagetitle.is(':visible');
                            }
                        }
                        if(main_header.length>0 && doc_body.find('#main-content-container').length>0){
                            main_header.height(show_page_title?180:144);
                            doc_body.find('#main-content-container').css({top:show_page_title?190:152});
                        }
                        
                        
                        //assign content to editor
                        $('.tinymce-body').val(main_content.html());
                        //init widgets 
                        
                        LayoutMgr.appInitFromContainer( null, main_content ); //was document, "#main-content"
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        
                        __alignButtons();
                        
                        //find all link elements
                        main_content.find('a').each(function(i,link){
                            
                            var href = $(link).attr('href');
                            if(href && href!='#'){
                                if( (href.indexOf(window.hWin.HAPI4.baseURL)===0 || href[0] == '?')
                                    && window.hWin.HEURIST4.util.getUrlParameter('db',href) == window.hWin.HAPI4.database
                                    && window.hWin.HEURIST4.util.getUrlParameter('id',href) == home_page_record_id)
                                {
                                    var pageid = window.hWin.HEURIST4.util.getUrlParameter('pageid',href);
                                    if(pageid>0){
                                        $(link).attr('data-pageid', pageid);
                                        $(link).on({click:function(e){
                                            var pageid = $(e.target).attr('data-pageid');
                                            __iniLoadPageById(pageid);
                                            window.hWin.HEURIST4.util.stopEvent(e);
                                        }});
                                        
                                    }
                                }
                            }
                            
                        });
                        
                        
                        
                        //@todo does not properly work here svs_list('instance') returns undefined
                        //afterPageLoad( document,  pageid );
                });
        }
        
        //__alignButtons();
        
    }        
    
    //
    // returns text content from tinymce editor    
    // without .widget-design-header and .widget-options
    //
    function __getEditorContent()
    {
        var newval = null;
        if(tinymce && tinymce.activeEditor){
            newval = tinymce.activeEditor.getContent();
            var nodes = $.parseHTML(newval);
            if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
                (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
            { 
                //remove the only tag
                newval = nodes[0].textContent;
            }
           
            //remove .widget-design-header and .widget-options
            var ele = $('<div>').html(newval);
            ele.find('div[data-heurist-app-id]').each(function(i,item){
                $(item).text( $(item).find('.widget-options').text() );
            });
            newval = ele.html();
        }
        return newval;        
    }
    
    //
    //
    //
    function _isDirectEditMode(){
        
        return ($('#btn_inline_editor3').text()=='Save');
    }
         
    //
    // need_close - close editor and reinit page preview
    //
    function __saveChanges( need_close, new_pageid ){
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(doc_body.find('.ent_wrapper'));

        var newval = '';
        if(_isDirectEditMode()){
           //save as is
           //newval = $('.tinymce-body').val();
           newval = codeEditor.getValue();
            
        }else{
           newval = __getEditorContent(); 
        }
        
        //send data to server
        var request;
        
        if(is_header_editor){
            
            request = {a: 'addreplace',
                    recIDs: home_pageid,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'],
                    rVal: newval};
                    
        }else if(is_footer_editor){

            request = {a: 'addreplace',
                    recIDs: home_pageid,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER'],
                    rVal: newval};
            
        }else{
            if(window.hWin.HEURIST4.util.isempty(newval)){
                request = {a: 'delete',
                        recIDs: current_pageid,
                        dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']};
            }else{
                request = {a: 'replace',
                        recIDs: current_pageid,
                        dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                        rVal: newval,
                        needSplit: true};
            }
            
        }
                                        
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){
                    if(response.data.errors==1){
                        var errs = response.data.errors_list;
                        var errMsg = errs[Object.keys(errs)[0]];
                        window.hWin.HEURIST4.msg.showMsgErr( errMsg );
                    }else
                    if(response.data.noaccess==1){
                        window.hWin.HEURIST4.msg.showMsgErr('It appears you have not enough rights to edit this record');
                        
                    }else{

                        window.hWin.HEURIST4.msg.showMsgFlash('saved');
                        
                        if(is_header_editor){
                            header_content_raw = newval;
                            
                        }else if(is_footer_editor){
                            footer_content_raw = newval;
                            
                        }else{
                            last_save_content = newval;
//console.log('was saved '+last_save_content);                        
                            was_modified = true;     
                        }
                        
                        if($.isFunction(need_close)){
                            need_close.call();
                        }else if (need_close===true){
                            __hideEditor( new_pageid );    
                        }
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                window.hWin.HEURIST4.msg.sendCoverallToBack();
        });                                        
    }
            
    //
    // new_pageid if zero restore last saved content
    // otherwise load contents of new_pageid page 
    //                    
    function __hideEditor( new_pageid ){
            
            if(!_isDirectEditMode()){ //detach
                tinymce.remove('.tinymce-body'); //detach
            }
            $('#btn_inline_editor').show();
            $('#btn_inline_editor3').show();
            $('#btn_inline_editor4').show();
            $('#btn_inline_editor5').hide();
            $('#btn_inline_editor').text('Edit page content');
            $('#btn_inline_editor3').text('source');
            
            if(is_header_editor){
                
                
                main_header.html(header_content_raw);
                
                if(main_menu.length==0){
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        '<p>Warning: You have not specified the main menu in your html using '
                        +'&lt;div id="main menu"&gt;.</p>'
                        +'<p>The main menu defined in the CMS_Home record will therefore not be displayed '
                        +'(you may have specified another menu with the menu widget).</p>');
                }
                
                //reinit widgtets in header
                LayoutMgr.appInitFromContainer( null, main_header, //was document, "#main-header",
                    {heurist_Navigation:{menu_recIDs:home_pageid
                    , use_next_level:true
                    , orientation:'horizontal'
                    , onmenuselect:__iniLoadPageById
                    //, toplevel_css:{background:bg_color}
                }} );                 
                //restore 
//console.log('restore 1 '+last_save_content);                        
                $('.tinymce-body').val(last_save_content); 
                last_save_content = null;
            }else if(is_footer_editor){
                
                $('.page-footer-content').html(footer_content_raw);
                
                $('.tinymce-body').val(last_save_content); 
                last_save_content = null;
            }
            is_footer_editor = false;
            is_header_editor = false;
            
            if(new_pageid>0){
                __loadPageById( new_pageid );   
            }else{
                
                if(original_editor_content!=null){
                    //$('.tinymce-body').val(original_editor_content);
                }
                //assign last saved content
                if( last_save_content != null ){ 
//console.log('resore 2 ' + last_save_content);                    
                    $('.tinymce-body').val(last_save_content); 
                    
                    if(was_modified){
                        main_content.html(last_save_content);
                        LayoutMgr.appInitFromContainer( null, main_content); //was document, "#main-content" );
                    }
                }
            }
            
            last_save_content = null;    
            was_modified = false;    
            $('.tinymce-body').hide();
            $('#codemirror-body').hide();
            
            main_content.show();
            main_content.parent().css('overflow-y','auto');
            $('#edit_mode').val(0).click();

            //exit edit mode
            if($.isFunction(that.onEditPageContent))that.onEditPageContent(true);
    }
    
    //
    //
    //
    function __alignButtons(){
        
        if(is_viewonly){
            $('#btn_inline_editor').hide();
            $('#btn_inline_editor3').hide();
            $('#btn_inline_editor4').hide();
        }else{
            
            var ele_header = main_header;
            
            if(ele_header.length==0){
                
                var tp = 5;
                var lp = main_content.width();
                $('#btn_inline_editor').css({position:'absolute',
                            top:tp, left:lp-190}).show();
                $('#btn_inline_editor3').css({position:'absolute',
                            top:tp,left:lp-40}).show();
                $('#btn_inline_editor5').css({position:'absolute',
                    top:tp,left:lp-340}).hide();
                    
                $('textarea.tinymce-body').css('top',30);
                $('#codemirror-body').css('top',30);
            
            }else{
                
                var tp = main_header.height()-(main_header.height()==180?15:-10);
                var lp = main_header.width();
                $('#btn_inline_editor').css({position:'absolute',
                            top:tp,left:lp-190}).show();
                $('#btn_inline_editor3').css({position:'absolute',
                            top:tp,left:lp-40}).show();
                $('#btn_inline_editor5').css({position:'absolute',
                    top:tp,left:lp-340}).hide();
                
                //$('#btn_inline_editor').position({my:'right top',at:'right-90 top-15',of:main_content}).show();
                //$('#btn_inline_editor3').position({my:'right top',at:'right-40 top-15',of:main_content}).show();
                
                var ele = doc_body.find('#main-pagetitle > h2');
                if(ele.length>0){
                    var pos = {my:'left top', at:'right+20 top+2', of: doc_body.find('#main-pagetitle > h2')};   
                    $('#btn_inline_editor4').position(pos).show();
                }
                
                
            }
            
        }
    }    
    
    //
    // adds widget-design-header and widget-options
    // assigns events for edit/remove links on widget placeholder in tinymce editor
    //
    function __initWidgetEditLinks(widgetid){

            var eles; 
            
            // adds widget-design-header and widget-options
            if(widgetid==null){
                eles = tinymce.activeEditor.dom.select('div[data-heurist-app-id]');
            }else{
                eles = tinymce.activeEditor.dom.get( widgetid );
                eles = [eles];
            }
            
            $(eles).each(function(idx, ele){
                ele = $(ele);
                if (ele.find('div.widget-design-header').length==0){
                    
                    var opts = ele.text(); 
                    ele.empty().append(
                '<div style="padding:10px;" class="widget-design-header"><img src="'
                +window.hWin.HAPI4.baseURL+'hclient/assets/heurist_logo_35x35.png" height="22" style="vertical-align:middle">&nbsp;<b>'
                + ele.attr('data-heurist-app-id').substring(8)
                + '</b><a href="#" class="edit" style="padding:0 10px" title="Click to edit">edit</a>&nbsp;&nbsp;'
                + '<a href="#" class="remove">remove</a></div>'
                + '<span class="widget-options" style="font-style:italic;display:none">'+opts+'</span>');
                    
                }
            });
           
            if(widgetid==null){
                //all
                eles = tinymce.activeEditor.dom.select('.widget-design-header'); //div.
                
                //$(tinymce.activeEditor.dom.select('.initTabs')).tabs();
                
            }else{
                eles = tinymce.activeEditor.dom.get( widgetid );
                eles = [eles];
            }

            if($.isFunction(that.onEditPageContent)){
                that.onEditPageContent(false, tinymce.activeEditor.dom.select('.widget-design-header'));
            }                
            $(eles).each(function(idx, ele){
                
                if ($(ele).hasClass('initTabs')) $(ele).tabs();
                
                $(ele).find('a.edit').click(function(event){
                    var wid = $(event.target).parents('.mceNonEditable').attr('id');
                    window.hWin.HEURIST4.util.stopEvent(event);
                    __addEditWidget( wid );                    
                }).dblclick(function(e){ 
                   e.preventDefault();
                });
                $(ele).find('a.remove').click(function(event){  
                    window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',function(){
                        var wid = $(event.target).parents('.mceNonEditable').attr('id');
                        tinymce.activeEditor.dom.remove( wid );
                        if($.isFunction(that.onEditPageContent)) that.onEditPageContent(false, wid, 'remove');    
                    });
                    window.hWin.HEURIST4.util.stopEvent(event);
                });
                
            })
    }
    
    //
    // browse for heurist uploaded/registered files/resources and add player link
    //         
    function __addHeuristMedia(){

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

                        //always add media as reference to production version of heurist code (not dev version)
                        var thumbURL = window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database
                        +"&thumb="+recordset.fld(record,'ulf_ObfuscatedFileID');

                        var playerTag = recordset.fld(record,'ulf_PlayerTag');

                        tinymce.activeEditor.insertContent( playerTag );
                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    }

    //
    // 1. Shows dialog with list of templates 
    // 2. Loads template
    // 3. Execute template script to replace template variables
    // 4. Adds to content
    // 5. Init edit/remove links
    //
    function __addTemplate(template_name){
    

        // 1. Shows dialog with list of templates 
        var sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.html';
        var sURL2 = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.js';

        // 2. Loads template
        var ele = $('<div>').attr('data-template-temp',1).appendTo(doc_body).hide()
        ele.load(sURL+'?t='+window.hWin.HEURIST4.util.random(),
        function(){

        var t_style = ele.find('style').text(); //style will be added to page css
        
        ele.on('oncomplete', function(event, data){
            
                // 4. Adds to tinymce
                var content = ele.html();
                tinymce.activeEditor.insertContent(content);
                // 5. Init edit/remove links
                __initWidgetEditLinks();
                
                //scroll to pos
                if(data && data.widgetid){
                    $(tinymce.activeEditor.getBody()).find('#'+data.widgetid).get(0).scrollIntoView();    
                }
                
                ele.empty().remove(); //remove temp div
                
        });
                        
        if(template_name=='blog'){
            // 3. Execute template script to replace template variables, adds filters and smarty templates
            try{
                $.getScript(sURL2, function(){
                    //console.log('getScript');                
                });
            }catch(e){
                alert('Error in template script');
            }
        }else{
            var widgetid = (template_name=='discover')?ele.find('div[data-heurist-app-id="heurist_SearchTree"]'):0;
            
            ele.trigger('oncomplete',{widgetid:widgetid});
        }

        }); //on template load
        
        //$.get("http://www.mypage.com", function( my_var ) {
            // my_var contains whatever that request returned
        //});
    }
    
    //
    // defines widget to be inserted (heurist-app-id) and its options (heurist-app-options)
    //      
    //select widget in list
    //select size
    //define search realm,  init query or savedsearch id
    //select specific options
    //returns div
    function __addEditWidget( widgetid_edit ){
        
        var $dlg, buttons = {};
        
        if(is_edit_widget_open) return;
        is_edit_widget_open = true;
            
        function __prepareVal(val){
            if(val==='false'){
                val = false;
            }else if(val==='true'){
                val = true;
            }
            return val;
        }

        //
        // from UI
        //
        function __prepareWidgetDiv( widgetid, widget_old ){
            //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();

            var sel = $dlg.find('#widgetName');
            var widget_name = sel.val();
            var widget_title = sel.find('option:selected').attr('data-name'); //not used
            var widgetCss = $dlg.find('#widgetCss').val();
            var groupContent = '';
            
            var opts = {};
            opts['__widget_name'] = '========================== '+widget_name.toUpperCase().substring(8)+' ==========================';
            
            if(widget_name=='heurist_Map'){
                
                    var layout_params = {};
                    //parameters for controls
                    layout_params['notimeline'] = !$dlg.find("#use_timeline").is(':checked');
                    layout_params['nocluster'] = !$dlg.find("#use_cluster").is(':checked');
                    layout_params['editstyle'] = $dlg.find("#editstyle").is(':checked');
                    layout_params['map_rollover'] = $dlg.find("#map_rollover").is(':checked');
                    //@todo select basemap from selector
                    //layout_params['basemap']
                    
                    var ctrls = [];
                    $dlg.find('input[name="controls"]:checked').each(
                        function(idx,item){ctrls.push($(item).val());}
                    );
                    if(ctrls.length>0) layout_params['controls'] = ctrls.join(',');
                    if(ctrls.indexOf('legend')>=0){
                        ctrls = [];
                        $dlg.find('input[name="legend"]:checked').each(
                            function(idx,item){
                                var is_exp = $dlg.find('input[name="legend_exp"][value="'+$(item).val()+'"]').is(':checked')?'':'-'
                                ctrls.push(is_exp+$(item).val());
                            }
                        );
                        if(ctrls.length>0){
                            layout_params['legend'] = ctrls.join(',');    
                            if(!$dlg.find('input[name="legend_exp2"]').is(':checked')){
                                layout_params['legend'] += ',off';    
                            }
                            var w = $dlg.find('input[name="legend_width"]').val();
                            if(w>100 && w<600){
                                layout_params['legend'] += (','+w);
                            }
                        } 
                        
                    }
                    layout_params['published'] = 1;
                    layout_params['template'] = $dlg.find('select[name="map_template"]').val();
                    layout_params['basemap'] = $dlg.find('input[name="map_basemap"]').val();
                
                    opts['layout_params'] = layout_params;
                    opts['leaflet'] = true;
                    
                    var mapdoc_id = $dlg.find('select[name="mapdocument"]').val();
                    if(mapdoc_id>0) opts['mapdocument'] = mapdoc_id;
                    
            }else
            if (widget_name=='heurist_Groups'){
                
                //find all inputs with class = tabs
                var header = '<ul>';
                var cont = $dlg.find('div.'+widget_name);
                var tabs = [];
                var index = 1;
                cont.find('input.tabs').each(function(idx, item){
                    var title = $(item).val().trim();
                    if(title!=''){
                        var tab_id = widgetid+'-tab'+index;
                        header = header + '<li><a href="#'+tab_id+'">'+title+'</a></li>';
                        
                        var content = 'Edit content for tab '+tab_id;
                        
                        if(!window.hWin.HEURIST4.util.isempty(widget_old)){
                            tab_id_old = widget_old+'-tab'+index;
                            var tabdiv = tinymce.activeEditor.dom.get('#'+tab_id_old);
                            if(tabdiv){
                                content = $(tabdiv).html();
                            }
                        }
                        
                        index++;
                        
                        groupContent = groupContent + '<div id="'+tab_id+'" class="mceEditable">'
                            +content+'</div>';
                        
                        tabs.push({title:title, description:''});    
                    }
                });
                opts['tabs'] = tabs;
                groupContent = header + '</ul>' + groupContent;
                    
                opts['groups_mode'] = __prepareVal( cont.find('input[name="groups_mode"]:checked').val());
                
                
                //for source code need to generate <ul> to visualize header in edit mode
                //and add content divs
                
                
                    
            }
            else{
                
                var cont = $dlg.find('div.'+widget_name);
                
                if(widget_name=='heurist_SearchTree'){
                    cont.find('input[name="allowed_UGrpID"]').val( 
                            cont.find('#allowed_UGrpID').editing_input('getValues') );
                    cont.find('input[name="allowed_svsIDs"]').val( 
                            cont.find('#allowed_svsIDs').editing_input('getValues') );
                    cont.find('input[name="init_svsID"]').val( 
                            cont.find('#init_svsID').editing_input('getValues') );
                }else if(widget_name=='heurist_Navigation'){
                    var menu_recIDs = cont.find('#menu_recIDs').editing_input('getValues');
                    if(window.hWin.HEURIST4.util.isempty(menu_recIDs) || 
                      ($.isArray(menu_recIDs)&& (menu_recIDs.length==0||window.hWin.HEURIST4.util.isempty(menu_recIDs[0]))))
                    {
                        window.hWin.HEURIST4.msg.showMsgErr('Please set at least one top level menu item');                     
                        return false;   
                    }
                    cont.find('input[name="menu_recIDs"]').val( menu_recIDs );
                }
            
                //find input elements and fill opts with values
                cont.find('input').each(function(idx, item){
                    item = $(item);
                    if(item.attr('name')){
                        if(item.attr('type')=='checkbox'){
                            opts[item.attr('name')] = item.is(':checked');    
                        }else if(item.attr('type')=='radio'){
                            if(item.is(':checked')) opts[item.attr('name')] = __prepareVal(item.val());    
                        }else if(item.val()!=''){
                            opts[item.attr('name')] = __prepareVal(item.val());    
                        }
                    }
                });
                $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                    item = $(item);
                    opts[item.attr('name')] = item.val();    
                });
                
                if(widget_name=='heurist_resultListExt'){
                    opts['template'] = $dlg.find('select[name="rep_template"]').val();
                    opts['reload_for_recordset'] = true;
                    opts['url'] = 'viewers/smarty/showReps.php?publish=1&debug=0&template='
                        +encodeURIComponent(opts['template'])+'&[query]';
                }else if(widget_name=='heurist_resultList'){
                    opts['show_toolbar'] = opts['show_counter'] || opts['show_viewmode'];
                    if(window.hWin.HEURIST4.util.isempty(opts['recordview_onselect'])){
                        opts['recordview_onselect']  = 'inline'; //default value    
                    } 
                }else if(widget_name=='heurist_resultListDataTable'){
                    
                    opts['dataTableParams'] = $dlg.find('#dataTableParams').val();
                }
                
                var selval = opts.searchTreeMode;
                if(window.hWin.HEURIST4.util.isempty(opts.allowed_UGrpID)){ //groups are not defined
                        
                    if(selval==1){
                          window.hWin.HEURIST4.msg.showMsgErr('For "tree" mode you have to select groups to be displayed');
                          return false;
                    }else if (window.hWin.HEURIST4.util.isempty(opts.allowed_svsIDs) && selval==0) { //individual filters are not defined
                          window.hWin.HEURIST4.msg.showMsgErr('For "button" mode you must select either workgroups or filters individually');
                          return false;
                    }
                }
                
            }
          
//console.log(opts);          
            
            opts['init_at_once'] = true;
            opts['search_realm'] = $dlg.find('input[name="search_realm"]').val();
            
            var widget_options = JSON.stringify(opts);
            
            /*var widget_options = '';//JSON.stringify(opts);
            for(var key in opts){
                widget_options = widget_options+key+':'+opts[key]+';'                
            }*/
            
            var vals = widgetCss.split(';')
            var sh = '';
            for (var i=0; i<vals.length; i++){
                var vs = vals[i].split(':');
                if(vs && vs.length==2){
                    vs[0] = vs[0].trim();
                    if(vs[0]=='width' || vs[0]=='height' ){
                       sh = sh+' '+vals[i];
                    }
                }
            }
            
/*            
            var content = '<div id="'+widgetid+'" class="mceNonEditable'
                + ((widget_name=='heurist_Groups')?' initTabs':'') +'" '
                +' data-heurist-app-id="'+widget_name
                + '" style="'+ widgetCss+'" '
                + '>'
                + '<div style="padding:10px;" class="widget-design-header"><img src="'
                +window.hWin.HAPI4.baseURL+'hclient/assets/h4_icon_35x35.png" height="22" style="vertical-align:middle">&nbsp;<b>'
                +widget_title
                + '</b><a href="#" class="edit" style="padding:0 10px" title="Click to edit">edit</a>&nbsp;&nbsp;'
                + '<a href="#" class="remove">remove</a> '+sh+' </div>'//+widgetCss
                + ' <span class="widget-options" style="font-style:italic;display:none">'+widget_options+'</span>'
                + groupContent + '</div>';
*/                

            var content = '';
            

            var content = content = 
                '<div data-heurist-app-id="'+widget_name+'" '
                + ' style="'+ widgetCss+'" '
                + ' class="mceNonEditable" id="'+widgetid+'">'
                + widget_options +  '</div>';
  
                
            return content; 
        }//__prepareWidgetDiv
        
        //
        // to UI
        //
        function __restoreValuesInUI( widgetid ){
            //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
            var ele = $(tinymce.activeEditor.dom.get( widgetid ));
            
            var widget_name = ele.attr('data-heurist-app-id');
            $dlg.find('#widgetName').val( widget_name ); //selector
            if(ele  && ele[0].dataset)
                var s = ele[0].dataset.mceStyle;
                s = s.replace(/;/g, ";\n");
                $dlg.find('#widgetCss').val( s );
            
            
            var cfgele = ele.find('span.widget-options:first');
            if(cfgele.length==0) cfgele = ele.find('span'); //backward capability
            var opts = window.hWin.HEURIST4.util.isJSON(cfgele.text());
            
            if(opts!==false){

                $dlg.find('input[name="search_realm"]').val(opts.search_realm);    
            
                if(widget_name=='heurist_Map'){
                    
                    if(opts.layout_params){
                        $dlg.find("#use_timeline").prop('checked', !opts.layout_params.notimeline);    
                        $dlg.find("#map_rollover").prop('checked', !opts.layout_params.map_rollover);    
                        $dlg.find("#use_cluster").prop('checked', !opts.layout_params.nocluster);    
                        $dlg.find("#editstyle").prop('checked', opts.layout_params.editstyle);    
                        var ctrls = (opts.layout_params.controls)?opts.layout_params.controls.split(','):[];
                        $dlg.find('input[name="controls"]').each(
                            function(idx,item){$(item).prop('checked',ctrls.indexOf($(item).val())>=0);}
                        );
                        var legend = (opts.layout_params.legend)?opts.layout_params.legend.split(','):[];
                        if(legend.length>0){
                            
                            $dlg.find('input[name="legend_exp2"]').prop('checked',legend.indexOf('off')<0);
                            
                            $.each(legend, function(i, val){
                                if(parseInt(val)>0){
                                    $dlg.find('input[name="legend_width"]').val(val);        
                                }else if(val!='off'){
                                    var is_exp = (val[0]!='-')
                                    if (!is_exp) legend[i] = val.substring(1);
                                    $dlg.find('input[name="legend_exp"][value="'+val+'"]').prop('checked',is_exp);
                                }
                            });
                            $dlg.find('input[name="legend"]').each(
                                function(idx,item){
                                    $(item).prop('checked',legend.indexOf($(item).val())>=0);
                                }
                            );
                        }
                        if(opts.layout_params['template']){
                            $dlg.find('select[name="map_template"]').attr('data-template', opts.layout_params['template']);        
                        }
                        if(opts.layout_params['basemap']){
                            $dlg.find('input[name="map_basemap"]').val(opts.layout_params['basemap']);        
                        }
                        
                    }
                    if(opts['mapdocument']>0){
                        $dlg.find('select[name="mapdocument"]').attr('data-mapdocument', opts['mapdocument']);        
                    }

                }else if (widget_name=='heurist_Groups'){
                    
                    $dlg.find('input[name="groups_mode"][value="'+opts['groups_mode']+'"]').prop('checked',true);
                    __addTabs(opts['tabs']);
                    //cont.find('.ui-icon-circle-b-minus').click
                    
                    
                }else{
                
                    $dlg.find('div.'+widget_name+' input').each(function(idx, item){
                        item = $(item);
                        if(item.attr('name')){
                            if(item.attr('type')=='checkbox'){
                                item.prop('checked', opts[item.attr('name')]===true || opts[item.attr('name')]=='true');
                            }else if(item.attr('type')=='radio'){
                                item.prop('checked', item.val()== String(opts[item.attr('name')]));
                            }else {  //if(item.val()!=''){
                                item.val( opts[item.attr('name')] );
                            }
                        }
                    });
                    $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                        item = $(item);
                        item.val( opts[item.attr('name')] );
                    });
                    if(widget_name=='heurist_resultListExt'){
                        if(opts['template']){
                            $dlg.find('select[name="rep_template"]').attr('data-template', opts['template']);        
                        }
                    }else if(widget_name=='heurist_resultList'){
                        if(opts['rendererExpandDetails']){
                            $dlg.find('select[name="rendererExpandDetails"]').attr('data-template', opts['rendererExpandDetails']);        
                        }
                    }else if(widget_name=='heurist_resultListDataTable'){
                        $dlg.find('#dataTableParams').val(opts['dataTableParams']);
                    }
                    
                    
                    
                }
                
            }
        } //end __restoreValuesInUI
        
        // add input for tabs in config dialog
        //
        function __addTabs( tabs ){
                    if(!tabs || tabs.length==0){
                        tabs = [{title:'Tab1'}];
                    }
                    var cont = $dlg.find('.heurist_Groups');
                    for(var i=1;i<cont.length;i++){
                        $(cont[i]).remove();    
                    }
                    
                    $.each(tabs, function(idx, item){
                        
                        cont = $('<div  class="heurist_Groups">').appendTo($dlg.find('fieldset'))
                        
                        $('<div class="header"><label>'+((idx==0)?'Tabs':'&nbsp;')+'</label></div>').appendTo(cont);
                        $('<input value="'+item.title+'" class="tabs"/>').appendTo(cont);
                        
                        //'<span class="ui-icon ui-icon-circle-b-minus" style="visibility:hidden"></span>'
                    });
                    cont = $('<div>').addClass('heurist_Groups').appendTo($dlg.find('fieldset'))
                    $('<div class="header">&nbsp;</div>').appendTo(cont);
                    $('<span>').button({icon:'ui-icon-circle-b-plus', label:'Add new tab/group'})   // class="ui-button-icon ui-icon ui-icon-circle-b-plus tabs"></span>')
                        .click(function(event){
                            $('<div  class="heurist_Groups"><div class="header tabs">&nbsp;</div><input value="" class="tabs"/></div>')
                                .insertBefore($(event.target).parents('.heurist_Groups'));
                        })
                        .appendTo(cont);
        }
        
        
        var is_addition = window.hWin.HEURIST4.util.isempty(widgetid_edit);        
                
        buttons[window.hWin.HR(is_addition ?'Add':'Save')]  = function() {
            
                    var widgetid = 'mywidget_'+window.hWin.HEURIST4.util.random();
                    var  content = __prepareWidgetDiv( widgetid, widgetid_edit );            
                    
                    if(content!==false){
                        
                        if(!is_addition){  //edit
                            /*  old way it does not activeate noneditable
                            var ele = $(content).appendTo(doc_body);
                            tinymce.activeEditor.dom.replace( ele[0], tinymce.activeEditor.dom.get(widgetid_edit) );
                            */
                            
                            tinymce.activeEditor.selection.select( tinymce.activeEditor.dom.get(widgetid_edit) );
                            tinymce.activeEditor.insertContent(content);
                            
                        }else{  //add
                            tinymce.activeEditor.insertContent(content);
                        }
                        __initWidgetEditLinks(widgetid); //activate edit/remove links
                
                        //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                        $dlg.dialog( "close" );
                    }
                    
                }; 
                
        buttons[window.hWin.HR('Cancel')]  = function() {
                    //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                    $dlg.dialog( "close" );
                };
        
      
        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/cms/editCMS_AddWidget.html?t="+(new Date().getTime()), 
                buttons, 'Add Heurist Widget to your Web Page', 
        {  container:'cms-add-widget-popup',
           default_palette_class: 'ui-heurist-publish',
           width:750,
           close: function(){
                is_edit_widget_open = false;
                $dlg.dialog('destroy');       
                $dlg.remove();
           },
           open: function(){
               is_edit_widget_open = true;

               //init elements on dialog open
               var $selectWidget = $dlg.find('#widgetName');
               if(!window.hWin.HEURIST4.util.isempty(widgetid_edit)){
                   // fill values for edit
                   __restoreValuesInUI(widgetid_edit);
               }
               var is_initial_set = ($dlg.find('#widgetCss').val()=='');
               
               window.hWin.HEURIST4.ui.initHSelect($selectWidget[0], false);
               $selectWidget.on({change:function( event ){
                   var val = $(event.target).val();
                   $dlg.find('div[class^="heurist_"]').hide();    
                   var dele = $dlg.find('div.'+val+'');
                   dele.show();
                   
                   if(is_initial_set){
                       s = 'background:white;\nposition:relative;\n';

                       if(val=='heurist_resultListCollection'){
                            s = s + 'border:0px;'
                       }else if(val=='heurist_Search'){
                            s = s + 'border:0px solid gray;'
                            s = s + '\nheight:50px;\nwidth:400px;';        
                       }else if(val=='heurist_recordAddButton'){
                            s = s + 'border:1px solid gray;\ncolor:black;'
                            s = s + '\nheight:26px;\nwidth:120px;';        
                       }else {
                           s = s + 'border:1px solid gray;'
                           if(val=='heurist_Navigation'){
                                s = s + '\nheight:50px;\nwidth:100%;';        
                           }else if(val=='heurist_SearchTree'){
                                s = s + '\nheight:100%;\nwidth:300px;';        
                           }else{
                                s = s + '\nheight:600px;\nwidth:100%';        
                           }
                       }
                       $dlg.find('#widgetCss').val(s);
                       
                       if(val=='heurist_Groups'){
                            __addTabs();
                       }
                   }
                   
                   if(val=='heurist_Navigation'){
                       
                       var rval = dele.find('input[name="menu_recIDs"]').val();
                       rval =  rval?rval.split(','):[];
                       
                       var ele = dele.find('#menu_recIDs');
                       
                       if(!ele.editing_input('instance')){
                           
                           
                           
                            var ed_options = {
                                recID: -1,                                                                                       
                                dtID: ele.attr('id'), //'group_selector',
                                //show_header: false,
                                values: rval,
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:0,
                                    rst_DisplayName: 'Top level menu items', rst_DisplayHelpText:'',
                                    rst_PtrFilteredIDs: [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
                                              window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']],
                                    rst_FieldConfig: {entity:'records', csv:false}
                                }
                            };

                            ele.editing_input(ed_options);
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                       }
                       //ele.editing_input('setValue', rval);
                       
                       dele.find('select[name="orientation"]')
                       .change(function(e){
                           
                           var is_horiz = ($(e.target).val()=='horizontal');

                           if($(e.target).val()=='treeview'){
                               $dlg.find('#expandLevels').show();
                           }else{
                               $dlg.find('#expandLevels').hide();
                           }
                           
                           var vals = $dlg.find('#widgetCss').val().split(';');
                           for (var i=0; i<vals.length; i++){
                               var vs = vals[i].split(':');
                               if(vs && vs.length==2){
                                   vs[0] = vs[0].trim();
                                   if(vs[0]=='width'){
                                       vals[i] = ('\nwidth: '+(is_horiz?'100%':'200px'));
                                   }else if(vs[0]=='height'){
                                       vals[i] = ('\nheight: '+(is_horiz?'50px':'300px'));
                                   }
                               }
                           }

                           $dlg.find('#widgetCss').val( vals.join(';'));
                       });
                       
                       

                   }else 
                   if (val=='heurist_recordAddButton'){
                        
                        var ele = dele.find('button[name="add_record_cfg"]');
                        
                        if(!ele.button('instance')){
                        
                            ele.button().click(
                                function(){
                                    window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                                        title: 'Select record type',
                                        height: 520,
                                        get_params_only: true,
                                        onClose: function(context){
                                            if(context && context.RecTypeID>0){
                                                dele.find('input[name="RecTypeID"]').val(context.RecTypeID);
                                                dele.find('input[name="OwnerUGrpID"]').val(context.OwnerUGrpID);
                                                dele.find('input[name="NonOwnerVisibility"]').val(context.NonOwnerVisibility);
                                            }
                                                
                                        },
                                        default_palette_class: 'ui-heurist-publish'                                        
                                    }
                                    );    
                                }
                            );
                        }
                
                   }else
                   if(val=='heurist_SearchTree'){
                       
                       var ele_rb = dele.find('input[name="searchTreeMode"]')
                        .change(function(e){
                            
                            var selval = __getSearchTreeMode();
                            
                            if(selval==0){
                                //buttons
                                dele.find('#allowed_UGrpID').css('display','table-row');
                                dele.find('#allowed_svsIDs').css('display','table-row');
                                dele.find('#allowed_UGrpID').editing_input('setDisabled', false);
                                dele.find('#allowed_svsIDs').editing_input('setDisabled', false);
                            }else if(selval==1){
                                //tree
                                dele.find('#allowed_UGrpID').css('display','table-row');
                                dele.find('#allowed_svsIDs').css('display','none');
                                dele.find('#allowed_UGrpID').editing_input('setDisabled', false);
                                dele.find('#allowed_svsIDs').editing_input('setDisabled', true);
                                dele.find('input[name="allowed_svsIDs"]').val('');
                            }else{
                                //full
                                dele.find('#allowed_UGrpID').css('display','none');
                                dele.find('#allowed_svsIDs').css('display','none');
                                dele.find('#allowed_UGrpID').editing_input('setDisabled', true);
                                dele.find('#allowed_svsIDs').editing_input('setDisabled', true);
                                dele.find('input[name="allowed_UGrpID"]').val('');
                                dele.find('input[name="allowed_svsIDs"]').val('');
                            }
                            
                        });
                       var selval = __getSearchTreeMode();
                       if(window.hWin.HEURIST4.util.isempty(selval)
                            || !(selval==0 || selval==1 || selval==2)){
                           selval = 0;
                           dele.find('#heurist_SearchTreeMode1').prop('checked',true);
                       }
                       
                       
                       //visible for buttons and tree mode
                       var ele = dele.find('#allowed_UGrpID');
                       if(!ele.editing_input('instance')){
                           
                            var init_val = dele.find('input[name="allowed_UGrpID"]').val();
                            if(init_val=='' && selval!=2 && dele.find('input[name="allowed_svsIDs"]').val()==''){
                                init_val==5;//web search  by default
                            }
                            
                            var ed_options = {
                                recID: -1,
                                dtID: ele.attr('id'), 
                                values: [init_val], 
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:1,
                                    rst_DisplayName: 'EITHER Show all filters in these workgroups', 
                                    rst_DisplayHelpText:'',
                                    rst_FieldConfig: {entity:'sysGroups', csv:true}
                                },
                                change:function(){
                                    dele.find('#allowed_svsIDs').editing_input('setValue','');
                                    dele.find('#init_svsID').editing_input('setValue','');
                                    __restFilterForInitSearch();
                                }
                            };

                            ele.editing_input(ed_options);
                            //ele.editing_input('setValue','5'); 
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                           
                       }
                       
                       //visible for buttons mode only
                       ele = dele.find('#allowed_svsIDs');
                       if(!ele.editing_input('instance')){
                           
                            var ed_options = {
                                recID: -1,
                                dtID: ele.attr('id'), 
                                values: [dele.find('input[name="allowed_svsIDs"]').val()],
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:1,
                                    rst_DisplayName: 'OR Choose specific filters', rst_DisplayHelpText:'',
                                    rst_FieldConfig: {entity:'usrSavedSearches', csv:true}
                                },
                                change:function(){
                                    dele.find('#allowed_UGrpID').editing_input('setValue','');
                                    dele.find('#init_svsID').editing_input('setValue','');
                                    __restFilterForInitSearch();
                                }
                            };

                            ele.editing_input(ed_options);
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                           
                       }
                       
                       ele = dele.find('#init_svsID');
                       if(!ele.editing_input('instance')){
                           
                            var ed_options = {
                                recID: -1,
                                dtID: ele.attr('id'), 
                                values: [dele.find('input[name="init_svsID"]').val()],
                                readonly: false,
                                showclear_button: true,
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:1,
                                    rst_DisplayName: 'Trigger this filter on page load', rst_DisplayHelpText:'',
                                    rst_FieldConfig: {entity:'usrSavedSearches', csv:false} 
                                }
                            };


                            ele.editing_input(ed_options);
                            ele.parent().css('display','block');
                            ele.find('.header').css({'width':'150px','text-align':'right'});
                       
                       }
                       
                       
                       function __getSearchTreeMode(){
                            var ele_rb = dele.find('input[name="searchTreeMode"]')
                            var selval = '';
                            $.each(ele_rb, function(idx, item){
                                item = $(item);
                                if(item.is(':checked')){
                                    selval = item.prop('value');
                                    return false;
                                }
                            });
                            return selval
                       }
                       
                       function __restFilterForInitSearch(){
                           
                           var ifilter = null; 
                           var val = dele.find('#allowed_svsIDs').editing_input('getValues');
                           // dele.find('input[name="allowed_svsIDs"]').val();
                           if(!window.hWin.HEURIST4.util.isempty(val) && val[0]!=''){
                               if($.isArray(val)) val = val.join(',');
                               ifilter = {svs_ID:val};
                           }else{
                               val = dele.find('#allowed_UGrpID').editing_input('getValues');
                               //dele.find('input[name="allowed_UGrpID"]').val();
                               if(!window.hWin.HEURIST4.util.isempty(val) && val[0]!=''){
                                    if($.isArray(val)) val = val.join(',');
                                    ifilter = {svs_UGrpID:val};
                               }
                           }
                           

                           ele.editing_input({
                                dtFields:{
                                    dty_Type:"resource", rst_MaxValues:1,
                                    rst_DisplayName: 'Initial filter', rst_DisplayHelpText:'',
                                    rst_FieldConfig: {entity:'usrSavedSearches', csv:false,
                                        initial_filter:ifilter, search_form_visible:(ifilter==null)    
                                    } 
                                }
                           }); 
                       }
                       
                       __restFilterForInitSearch();
                       
                       ele_rb.change();

                       
                   }else
                   if(val=='heurist_Map' && 
                    dele.find('select[name="mapdocument"]').find('options').length==0){
                       
                        var $selectMapDoc = dele.find('select[name="mapdocument"]');
                       //fill list of mapdpcuments
                        var request = {
                                    q: 't:'+window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'],w: 'a',
                                    detail: 'header',
                                    source: 'cms_edit'};
                        //perform filter        
                        window.hWin.HAPI4.RecordMgr.search(request,
                            function(response){
                                
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
                                    var resdata = new hRecordSet(response.data);
                                    var idx, records = resdata.getRecords(), opts = [{key:'',title:'none'}];
                                    for(idx in records){
                                        if(idx)
                                        {
                                            var record = records[idx];
                                            opts.push({key:resdata.fld(record, 'rec_ID'), title:resdata.fld(record, 'rec_Title')});
                                        }
                                    }//for
                                    
                                    window.hWin.HEURIST4.ui.fillSelector($selectMapDoc[0], opts);
                                    if($selectMapDoc.attr('data-mapdocument')>0){
                                        $selectMapDoc.val( $selectMapDoc.attr('data-mapdocument') );
                                    }
                                    window.hWin.HEURIST4.ui.initHSelect($selectMapDoc[0], false);
                                    
                                }else {
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }

                            }
                        );  
                        
                        var $select2 = dele.find('select[name="map_template"]'); 
                        
                        window.hWin.HEURIST4.ui.createTemplateSelector( $select2
                                           ,[{key:'',title:'Standard map popup template'},{key:'none',title:'Disable popup'}], $select2.attr('data-template'));
                        

                       
                   }else if(val=='heurist_resultListExt' && 
                    dele.find('select[name="rep_template"]').find('options').length==0){
                       
                        var $select3 = dele.find('select[name="rep_template"]'); 
                        
                        window.hWin.HEURIST4.ui.createTemplateSelector( $select3 
                                           ,null, $select3.attr('data-template'));

                   
                   }else if(val=='heurist_resultList' && 
                    dele.find('select[name="rendererExpandDetails"]').find('options').length==0){

                        var $select4 = dele.find('select[name="rendererExpandDetails"]'); 
                        
                        window.hWin.HEURIST4.ui.createTemplateSelector( $select4
                                           ,[{key:'',title:'Standard record view template'}], $select4.attr('data-template'));
                       
                   }
                   
                   
               }}).change();
               
               if(!window.hWin.HEURIST4.util.isempty(widgetid_edit)){
                   window.hWin.HEURIST4.util.setDisabled($selectWidget[0], true);
               }
               
               
           }  //end open event
        });
        
        
    }
    
    //
    //
    //
    function __insertTemplate(){

        var $dlg;

        if(is_edit_widget_open) return;
        is_edit_widget_open = true;
        
        var selected_template = null;
        
        var templates = {
                def:{name:'Default', author:'Heurist team',
                    description:'Simple placeholder'},
                discover:{name:'Discover (filters/results/map)', author:'Unknown author',
                    description:'Filters, result list and mapping'},            
                blog:{name:'Blog posts', author:'Heurist team',
                    description:'List of blog post with filter'}
                };

        var buttons= [
                 {text:window.hWin.HR('Cancel'), 
                    id:'btnCancel',
                    css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                    click: function() { 
                        $dlg.dialog( "close" );
                    }},
                 {text:window.hWin.HR('Insert'), 
                    id:'btnDoAction',
                    class:'ui-button-action',
                    disabled:'disabled',
                    css:{'float':'right'}, 
                    click: function() { 
                        if(selected_template){
                            __addTemplate(selected_template);
                            $dlg.dialog( "close" );    
                        }
                    }}];
      
        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/cms/editCMS_SelectTemplate.html?t="+(new Date().getTime()), 
                buttons, 'Select Template to insert to your Web Page', 
        {  container:'cms-add-widget-popup',
           default_palette_class: 'ui-heurist-publish',
           width: 400,
           height: 430,
           close: function(){
                is_edit_widget_open = false;
                $dlg.dialog('destroy');       
                $dlg.remove();
           },
           open: function(){
                is_edit_widget_open = true;
                
                //load list of templates and init selector
                $dlg.find('#templates').mouseover(function(e){
                    window.hWin.HEURIST4.util.setDisabled( $dlg.parents('.ui-dialog').find('#btnDoAction'), false );
                    var t_name = $(e.target).val();
                    selected_template  = t_name;
                    //$dlg.find('.template_author').html(templates[t_name]['author']);
                    $dlg.find('.template_description').html(templates[t_name]['description']);
                });
               
           }
        });
        
    }
 
    //
    // opens record editor for current page
    //
    function _editPageRecord(){
                    //edit page
                    window.hWin.HEURIST4.ui.openRecordEdit(current_pageid, null,
                        {selectOnSave:true,
                            onselect:function(event, data){
                                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                        __iniLoadPageById( current_pageid );
                                }
                    }});
    }    
    
    //
    //
    //
    function _resetHeaderContent( is_force ){
        
        if(is_force!==true){
            window.hWin.HEURIST4.msg.showMsgDlg(
'<p>Content for custom header is going to be deleted. Default header will be added to this website</p>'
+'<p>Remove cutom header?</p>',            
                function(){
                    _resetHeaderContent(true);    
                });
                return;
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(doc_body.find('.ent_wrapper'));        
        var request = {a: 'delete',
                    recIDs: home_pageid,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER']};
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){
                    if(response.data.noaccess==1){
                        window.hWin.HEURIST4.msg.showMsgErr('It appears you have not enough rights to edit this record');
                    }else{
                        window.hWin.HEURIST4.msg.showMsgFlash('cleared');
                        //refresh
                        location.reload();
                        //__iniLoadPageById( home_pageid );
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                window.hWin.HEURIST4.msg.sendCoverallToBack();
        });                                        
        
        
    }
    
    //
    // opens tinymce for website header
    //
    function _editHeaderContent(){
        
        if(header_content_generated){
            window.hWin.HEURIST4.msg.showMsgDlg(
'<p>If you decide to use a custom page header, you must define all elements for the page header (title, logo etc) in the html. '
+'Fields from the CMS_Home record can be referenced (see the default header html which is inserted automatically).</p>'
+'<p>You can reset the page header to auto generation simply by deleting all the html in Source view or clearing '
+'the "Custom page header (html)" field in the CMS_Home record.</p>' 
+'<p>Create custom page header?</p>',            
                function(){
                    header_content_generated = false;
                    _editHeaderContent();    
                });
                return;
        }
        
        $('#btn_inline_editor').hide();
        $('#btn_inline_editor3').hide();
        $('#btn_inline_editor4').hide();
        main_content.parent().css('overflow-y','hidden');
        main_content.hide();
        $('#edit_mode').val(1).click();//to disable left panel
        
        last_save_content = $('.tinymce-body').val();
        $('.tinymce-body').val(header_content_raw);
        
        $('.tinymce-body').show();
        
        is_header_editor = true;
        
        tinymce.init(inlineEditorConfig);
    }


    //
    // opens tinymce for website footer
    //
    function _editFooterContent(){
        
        if(footer_content_generated){
            window.hWin.HEURIST4.msg.showMsgDlg(
'<p>If you decide to use a custom page footer, you must take into account that right part of footer is reserverd for host/siteowner information</p>'
+'<p>To change styles for footer use #page-footer id in "Custom CSS" field</p>' 
+'<p>Create custom page footer?</p>',            
                function(){
                    footer_content_generated = false;
                    _editFooterContent();    
                });
                return;
        }
        
        $('#btn_inline_editor').hide();
        $('#btn_inline_editor3').hide();
        $('#btn_inline_editor4').hide();
        main_content.parent().css('overflow-y','hidden');
        main_content.hide();
        $('#edit_mode').val(1).click();//to disable left panel
        
        last_save_content = $('.tinymce-body').val();
        $('.tinymce-body').val(footer_content_raw);
        
        $('.tinymce-body').show();
        
        is_footer_editor = true;
        
        tinymce.init(inlineEditorConfig);
    }

    
    //
    // opens tinymce for cotent editor
    //
    function _editPageContent(event){
        
        is_header_editor = false;

        $('#btn_inline_editor').hide();
        $('#btn_inline_editor3').hide();
        $('#btn_inline_editor4').hide();
        $('#btn_inline_editor5').hide();
        $('#btn_inline_editor').text('Edit page content');
        $('#btn_inline_editor3').text('source');

        main_content.parent().css('overflow-y','hidden');
        main_content.hide();
        $('#edit_mode').val(1).click();//to disable left panel
        //original_editor_content 
        last_save_content = $('.tinymce-body').val();
        //console.log('_editPageContent '+last_save_content);                

        $('.tinymce-body').show();
        tinymce.init(inlineEditorConfig);

        /*setTimeout(function(){
        $('.mce-tinymce').css({position:'absolute',
        top:140,  //
        bottom:20 //main_content.css('bottom')
        });    
        },500);*/

        window.hWin.HEURIST4.util.stopEvent(event);
        return false;
                    
    }        
    
    //
    // direct edit 
    //
    function _editPageSource( event ){
        
        if(_isDirectEditMode()){
            //save changes
            __saveChanges( true );
        }else{
            //switch to direct editor
            
            $('#btn_inline_editor4').hide();
            $('#btn_inline_editor').text('wyswyg'); //change "Edit page content" to "wyswyg"
            $('#btn_inline_editor3').text('Save');  //change label from "source" to "Save"
            $('#btn_inline_editor5').show();
            
            main_content.parent().css('overflow-y','hidden');
            main_content.hide();
            
            original_editor_content = $('.tinymce-body').val();

            //$('.tinymce-body').show();
            _initCodeEditor(original_editor_content);
            
        }
        window.hWin.HEURIST4.util.stopEvent(event);
        return false;
    }    
    
    var allow_close_dialog = false;
    //
    //
    //
    function _onEditorExit( callback ){
        
        if(!allow_close_dialog)
        {

            var edited_content = __getEditorContent();

            var is_changed = (edited_content!=null);
            if(is_header_editor){
                is_changed = is_changed && header_content_raw != edited_content;
            }else{
                is_changed = is_changed && last_save_content != edited_content;
            }

            if( is_changed ){ //was_modified

                var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(
                    '<br>Web page content has been modified',
                    {'Save':function(){
                            allow_close_dialog = true;
                            __saveChanges( callback );
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');},
                    'Cancel':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');},
                    'Abandon changes':function(){
                            allow_close_dialog = true;
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');
                            callback.call();
                    }},
                    'Content modified');
                //$dlg2.parents('.ui-dialog').css('font-size','1.2em');    
                return false;  //wait not close
            }
            
        }
        callback.call(this, false);
        return true; //ok to close
    }
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        //assign values only based on old structure
        editPageRecord: function(){
            _editPageRecord();
        },
        
        editPageContent: function(){
            _editPageContent();
        },

        editPageSource: function(){
            _editPageSource();
        },
        
        editHeaderContent: function(){
            _editHeaderContent();
        },

        editFooterContent: function(){
            _editFooterContent();
        },
        
        resetHeaderContent: function (){
            _resetHeaderContent();    
        },
       
        onEditorExit: function( callback ){
            return _onEditorExit( callback );
        },
        
        editWidget: function (wid) {
            __addEditWidget(wid);
        },

        addTemplate: function (wid) {
            __addTemplate(wid);
        },

        
        loadPageById: function (pageid) {
            __iniLoadPageById(pageid)
        },
        
        //callback on tinymce opening
        onEditPageContent: null
 
    }
 
    _init(_options);
    return that;  //returns object
 
}