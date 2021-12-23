/*
* editCMS2.js - CMS editor
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

group:

    if parent root, tab or pane - group is ent_wrapper

widget:
    has predefined min-height,min-width
    if parent tab, flex or pane - has class ent_wrapper (absolute 100%)


*/

var editCMS_instance2 = null;
// global variables
//  layoutMgr - global variable defined in hLayoutMgr
//  page_cache
//  home_page_record_id
//  current_page_id
//  isCMS_InHeuristUI

//
// options: record_id, content, container
//
function editCMS2(){

    var _className = "EditCMS2";

    var _lockDefaultEdit = false;
    
    var _panel_treePage,     // panel with treeview for current page 
        _panel_treeWebSite,  // panel with tree menu - website structure
        _panel_propertyView, // panel with selected element properties
        _toolbar_WebSite,
        _toolbar_Page,
        _tabControl,
        
        _layout_content,   // JSON config 
        _layout_container; // main-content with CMS content

    var default_palette_class = 'ui-heurist-publish';
        
    var page_was_modified = false;
    var delay_onmove = 0, __timeout = 0;
    var isWebPage = false;
    
    var current_edit_mode = 'page', //or website
        _editCMS_SiteMenu = null; 
        
    var _keep_EditPanelWidth = 0;  
    
    var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
    
    //     DT_CMS_THEME = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_THEME'],
    DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
    DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'],
    DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];

    var body = $(this.document).find('body');
    var dim = {h:body.innerHeight(), w:body.innerWidth()};
    dim.h = (window.hWin?window.hWin.innerHeight:window.innerHeight);
    
    var options;
    
    //
    // returns false if new page is not loaded (previous page has been modified and not saved
    //
    function _startCMS(_options){
        
        if (_warningOnExit( function(){_startCMS(_options);} )) return;                           
        
        options = _options;
        options.editor_pos = 'west'; //or east
            
        //
        // add edit layout - top most ent_wrapper becomes 
        //
        if($(this.document).find('.editStructure').length==0){
            
            
                isWebPage = ($('body').find('#main-menu').length == 0);
console.log('>>>>'+isWebPage);                
            
                var new_ele = $('<div class="ui-layout-center"></div>');//.prependTo(body);
                                             
                //body.children().shift();
                body.children().appendTo(new_ele);
                
                new_ele.appendTo(body);
                
                var editor_panel = $('<div class="ui-layout-'+options.editor_pos+'">'      
                        +'<div class="ent_wrapper editStructure" id="tabsEditCMS">' 
                            +'<ul style="margin-'+(options.editor_pos=='west'?'right':'left')+':40px"><li><a href="#treeWebSite">Menu</a></li><li><a href="#treePage">Page</a></li></ul>'
                            
                            +'<span style="position:absolute;top:22px;width:32px;height:24px;font-size:29px;cursor:pointer;'+(options.editor_pos=='west'?'right:5px':'')+'" '
                            +'class="bnt-cms-hidepanel ui-icon ui-icon-carat-2-'+(options.editor_pos=='west'?'w':'e')+'"/>'
                            
                            +'<div id="treeWebSite" style="top:43px;background-color:rgb(135,205,118);" class="ent_wrapper">'
                                +'<div class="toolbarWebSite ent_header" style="height:85px;padding-top:15px;">'
                                
                                    +'<a href="#" class="btn-website-edit" style="padding-left:20px">'
                                        +'<span class="ui-icon ui-icon-pencil"/>&nbsp;Configure website layout</a>'
                                    +'<span style="display:inline-block;padding:7px 0 0 40px" class="heurist-helper1" '
                                        +'title="Select menu item and Dblclick (or F2) to edit menu title in place. Drag and drop to reorder menu">'
                                        +'Drag menu items to re-order</span><br>'
                                    +'<span style="display:inline-block;padding:7px 0 7px 40px" class="heurist-helper1">'
                                        +'Click to edit the page</span>'
                                        
                                    +'<div style="border-top:1px solid gray;padding:10px 8px">'                                            
                                    +'<a href="#" title="Edit website home page" '
                                        +'class="btn-website-homepage" style="text-decoration:none;">'
                                        +'<span class="ui-icon ui-icon-home"/>&nbsp;Home page</a>'
                                    //+'<button  title="Add top level menu" class="btn-website-addpage"/>'
                                    +'</div>'     
                                        
                                +'</div>'
                                
                                +'<div class="treeWebSite ent_content_full" style="top:95px;padding:0px 10px;"/>' //treeview - edit website menu
                            +'</div>'
                            +'<div id="treePage" style="font-size:1.2em;top:43px" class="ent_wrapper">'
                            
                                +'<div class="treePageHeader ent_header" style="height:85px;font-size:0.8em">'
                                    
                                    +(isWebPage
                                    ?('<div style="padding:20px"><a href="#" class="btn-website-edit">'
                                        +'<span class="ui-icon ui-icon-pencil"/>&nbsp;Configure webpage</a></div>')
                                    :'<h2 class="truncate"></h2>')
                                    +'<span style="display:inline-block;" class="heurist-helper1">'
                                        +'Drag elements to re-order</span><br>'
                                    +'<span style="display:inline-block;padding:7px 0px" class="heurist-helper1">'
                                        +'Click to edit</span>'
                                        
                                +'</div>'
                            
                                +'<div class="treePage ent_content" style="top:95px;padding:10px;border-top:1px solid gray;border-bottom:1px solid gray"/>' //treeview - edit page
                                +'<div class="propertyView ent_content" style="top:45px;padding:10px 0px;border-bottom:1px solid gray;display:none"/>' //edit properties for element border-top:1px solid gray;
                                
                                +'<div class="toolbarPage ent_footer" style="padding:10px;font-size:0.9em;text-align:center;">'
                                    +'<button title="Discard all changed and restore old version of page" class="btn-page-restore">Discard</button>'
                                    + '<button title="Save changes for current page" class="btn-page-save ui-button-action">Save</button>'
                                    + (true!==isCMS_InHeuristUI
                                        ?'<button title="Exit/Close content editor" class="bnt-cms-exit">Close</button>'
                                        :'')
                                +'</div>'
                            +'</div>'
                        +'</div></div>').appendTo(body);
            
            
                    var layout_opts =  {
                        applyDefaultStyles: true,
                        maskContents:       true,  //alows resize over iframe
                        //togglerContent_open:    '&nbsp;',
                        //togglerContent_closed:  '&nbsp;',
                        center:{
                            minWidth:400,
                            contentSelector: '.ent_wrapper', //@todo !!!! for particule template ent_wrapper can be missed
                            //pane_name, pane_element, pane_state, pane_options, layout_name
                            onresize_end : function(){
                                //that.handleTabsResize();                            
                            }    
                        }
                    };
                    
                    layout_opts[options.editor_pos] = {
                            size: 230, //@todo this.usrPreferences.structure_width,
                            maxWidth:800,
                            minWidth:230,
                            spacing_open:6,
                            spacing_closed:40,  
                            togglerAlign_open:'center',
                            //togglerAlign_closed:'top',
                            togglerAlign_closed:16,   //top position   
                            togglerLength_closed:80,  //height of toggler button
                            initHidden: false, //!this.options.edit_structure,   //show structure list at once 
                            initClosed: false, //!this.options.edit_structure && (this.usrPreferences.structure_closed!=0),
                            slidable:false,  //otherwise it will be over center and autoclose
                            contentSelector: '.editStructure',   
                            onopen_start : function( ){ 
                                var tog = body.find('.ui-layout-toggler-'+options.editor_pos);
                                tog.removeClass('prominent-cardinal-toggler togglerVertical');
                                tog.find('.heurist-helper2.'+options.editor_pos+'TogglerVertical').hide();
                            },
                            onclose_end : function( ){ 
                                var tog = body.find('.ui-layout-toggler-'+options.editor_pos);
                                tog.addClass('prominent-cardinal-toggler togglerVertical');

                                if(tog.find('.heurist-helper2.'+options.editor_pos+'TogglerVertical').length > 0){
                                    tog.find('.heurist-helper2.'+options.editor_pos+'TogglerVertical').show();
                                }else{

                                    var margin = (options.editor_pos=='west') ? 'margin-top:270px;' : '';
                                    $('<span class="heurist-helper2 '+options.editor_pos+'TogglerVertical" style="width:270px;'+margin+'">Page structure and styling</span>').appendTo(tog);
                                }
                            },
                            togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-'+(options.editor_pos=='west'?'w':'e')+'"></div>',
                            togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-'+(options.editor_pos=='west'?'e':'w')+'"></div>',
                        };

                    body.layout(layout_opts); //.addClass('ui-heurist-bg-light')

                    editor_panel.find('.btn-page-save').button().css({'border-radius':'4px','margin-right':'5px'}).click(_saveLayoutCfg)
                    editor_panel.find('.btn-page-restore').button().css({'border-radius':'4px','margin-right':'5px'}).click(
                        function(){
                            _startCMS({record_id:options.record_id, container:'#main-content', content:null});
                        }
                    );
                    
                    
                    editor_panel.find('.btn-website-homepage').click(_editHomePage);
                    editor_panel.find('.btn-website-edit').click(_editHomePageRecord);
                    editor_panel.find('.btn-website-addpage').button({icon:'ui-icon-plus'}).click(_addNewRootMenu);
                    
                    editor_panel.find('.bnt-website-menu').button({icon:'ui-icon-menu'}).click(_showWebSiteMenu);
                    
                    editor_panel.find('.bnt-cms-hidepanel').click(function(){ body.layout().close(options.editor_pos); } );
                    editor_panel.find('.bnt-cms-exit').button().css({'border-radius':'4px'}).click(_closeCMS); //{icon:'ui-icon-close'}
                    
                    _panel_propertyView = body.find('.propertyView');
                    _panel_treeWebSite = body.find('.treeWebSite');
                    
                    _toolbar_WebSite = body.find('.toolbarWebSite');
                    _toolbar_Page = body.find('.toolbarPage');
                    
                    _tabControl = body.find('#tabsEditCMS').tabs({activate: function( event, ui ){
                        _switchMode();
                        //ui.newTab
                    }})
                    .addClass('ui-heurist-publish');
                    
                    if(isWebPage){
                        _tabControl.find('.ui-tabs-tab[aria-controls="treeWebSite"]').hide();
                    }

                    if(true){ // this.usrPreferences.structure_closed==0, only if panel is closed by default

                        var tog = body.find('.ui-layout-toggler-'+options.editor_pos);
                        tog.addClass('prominent-cardinal-toggler togglerVertical');

                        if(tog.find('.heurist-helper2.'+options.editor_pos+'TogglerVertical').length > 0){
                            tog.find('.heurist-helper2.'+options.editor_pos+'TogglerVertical').show();
                        }else{

                            var margin = (options.editor_pos=='west') ? 'margin-top:270px;' : '';
                            $('<span class="heurist-helper2 '+options.editor_pos+'TogglerVertical" style="width:270px;'+margin+'">Page structure and styling</span>').appendTo(tog);
                        }
                    }
        }
        
        
        body.layout().show(options.editor_pos, true );
        
        //load content for current page from DT_EXTENDED_DESCRIPTION
        if(!options){
            options.record_id = 0;
            options.container = '#main-content';
            options.content = null;
        }
        
        _layout_container = body.find(options.container);
        
        if(options.content)
        {
           _layout_content = options.content[DT_EXTENDED_DESCRIPTION];
           _initPage();
            
        }else if (options.record_id>0 ){
            
            current_page_id = options.record_id;
            
            _layout_content = page_cache[options.record_id][DT_EXTENDED_DESCRIPTION];
            
            /*load by page_record_id
                var surl = window.hWin.HAPI4.baseURL+'?db='
                    +window.hWin.HAPI4.database+'&field='+DT_EXTENDED_DESCRIPTION+'&recid='+options.record_id;
                $.get( surl,  
                function(res){
                    options.content = res;
                    _layout_content = res;
                    _initPage();
                });
            */
        }
        _initPage();
    }    
    
    //
    // Edit home page content
    //
    function _editHomePage(){

        if(_warningOnExit( _editHomePage )) return;                           
        
        _switchMode('page');        
        loadPageContent( home_page_record_id );
    }
    
    
    //
    // Edit home page record
    //
    function _editHomePageRecord(){

        if(_warningOnExit( _editHomePageRecord )) return;                           
        
        //edit menu item
        window.hWin.HEURIST4.ui.openRecordEdit(home_page_record_id, null,
            {selectOnSave:true, 
                edit_obstacle: false, 
                onClose: function(){ 
                    //parent_span.find('.svs-contextmenu4').hide();
                },
                onselect:function(event, data){
                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        //reload entire site
                        if(_editCMS_SiteMenu) _editCMS_SiteMenu.refreshWebsite();
                    }
        }});
    }
    
    //
    //
    //
    function _addNewRootMenu(){
        if(_editCMS_SiteMenu) {
            _editCMS_SiteMenu.selectMenuRecord(home_page_record_id);
        }
    }
    
    
    //
    //
    //
    function _warningOnExit(callback){
        
        if(page_was_modified){
        
            var $dlg;
            var _buttons = [
                {text:window.hWin.HR('Save'), 
                    click: function(){_saveLayoutCfg(callback);$dlg.dialog('close');}
                },
                {text:window.hWin.HR('Discard'), 
                    click: function(){page_was_modified = false; $dlg.dialog('close'); if($.isFunction(callback)) callback.call(this);}
                },
                {text:window.hWin.HR('Cancel'), 
                    click: function(){$dlg.dialog('close');}
                }
            ];            
            
            var sMsg = window.hWin.HR('Page has been modified');
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, _buttons, {title:sMsg});   

            return true;     
        }else{
            return false;     
        }
        
    }
    
    //
    // 1. close control panel
    // 2. reload content
    //
    function _closeCMS(){

        if(_warningOnExit( _closeCMS )) return;
        
        // 1. close control panel
        body.layout().hide(options.editor_pos); // .show(options.editor_pos, false );
        
        //2. reload content
        layoutMgr.setEditMode(false);
        layoutMgr.layoutInit(_layout_content, _layout_container, {rec_ID:home_page_record_id});
        
        if($.isFunction(options.close)){
            options.close.call();
        }
    }
    
    //
    // load page structure into tree and init layout
    //
    function _initPage( supress_conversion ){
        
        if(tinymce) tinymce.remove('.tinymce-body'); //detach
        
        if(layoutMgr){
            layoutMgr.setEditMode(true);
        }else {
            return;
        }
        
        var opts = {};
        if(page_cache[options.record_id]) opts = {page_name:page_cache[options.record_id][DT_NAME]};
        opts.rec_ID = home_page_record_id;
        
        if(supress_conversion!==true && typeof _layout_content === 'string' &&
            _layout_content.indexOf('data-heurist-app-id')>0){ //old format with some widgets
/* 
                var $dlg_pce = null;

                var btns = [
                    {text:window.hWin.HR('Proceed'),
                        click: function() { 
                            
                            $dlg_pce.dialog('close'); 
                            
                            var res = layoutMgr.convertOldCmsFormat(_layout_content, _layout_container);
                            if(res!==false){
                                _layout_content = res;
                                var sMsg = '<p>Conversion complete</p>'
                                window.hWin.HEURIST4.msg.showMsgDlg(sMsg);
                            }
                            
                            _initPage(true);
                        }
                    },
                    {text:window.hWin.HR('Cancel'),
                        click: function() { 
                            _initPage(true);
                            $dlg_pce.dialog('close'); 
                    }}
                ];            
 
            
                //trying to convert old format to new one - to json
                $dlg_pce = window.hWin.HEURIST4.msg.showMsgDlg(
'<p>Heurist\'s CMS editor has been upgraded to a new system which is both much easier and much more powerful than the original editor and requires an entirely new data format. Heurist will convert most pages automatically to the new editor.</p>'
 
+'<p>Unfortunately if this page uses complex formatting which we cannot be sure of converting correctly through this automatic process. Please email support at HeuristNetwork dot org with the name of your database and we will update the page manually to the new format.</p>'
 
+'<p>In the meantime you can continue to edit the page using the old web page editor, but please note this editor will be DISCONTINUED at the end of February 2022.</p>',btns,'New website editor'); 
                 return;
*/       

                            var res = layoutMgr.convertOldCmsFormat(_layout_content, _layout_container);
                            if(res!==false){
                                _layout_content = res;
                                
var sMsg = '<p>Heurist\'s CMS editor has been upgraded to a new system which is both much easier and much more powerful than the original editor and requires an entirely new data format. Heurist converts pages automatically to the new editor.</p>'
+'<p>If this page uses complex formatting which we cannot be sure of converting correctly through this automatic process.</p>'
+'<p>If you think this conversion is very different from your original, DO NOT hit SAVE, and open the page instead in the old web page editor (<b>Edit page content</b> or <b>Edit html source</b> links in the Publish menu) and get in touch with us (support at HeuristNetwork dot org) for help with conversion.</p>'
+'<p>Please note the old editor will be DISCONTINUED at the end of February 2022, and we may not have time to help you at the last moment, so please contact us immediately.</p>'
                                
                                window.hWin.HEURIST4.msg.showMsgDlg(sMsg);
                            }
             
        }
        
        
        var res = layoutMgr.layoutInit(_layout_content, _layout_container, opts);
        
        if(res===false){
            window.hWin.HEURIST4.msg.showMsgFlash('Old format. Edit in Heurist interface', 3000);
            //clear treeview
            _initTreePage([]);
        }else{
            _layout_content = res;
            _initTreePage(_layout_content);
        }
        body.find('.treePageHeader > h2').text( options.record_id==home_page_record_id ? window.hWin.HR('Home Page') :opts.page_name );
        
        if(_editCMS_SiteMenu) _editCMS_SiteMenu.highlightCurrentPage();
    }

    //
    // 1) init editor
    // 2) init hover toolbar - DnD,Edit Properties,Insert Sibling
    //
    function _initTinyMCE(){
      
        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();

        var inlineConfig = {
            selector: '.tinymce-body',
            menubar: false,
            inline: true,
            
            branding: false,
            elementpath: false,

            relative_urls : false,
            remove_script_host : false,
            convert_urls : true,            

            entity_encoding:'raw',
            inline_styles: true,
            content_style: "body {font-family: Helvetica,Arial,sans-serif;}",
            
            plugins: [
                'advlist autolink lists link image media preview', //anchor charmap print 
                'searchreplace visualblocks code fullscreen',
                'media table  paste help noneditable '   //contextmenu textcolor - in core for v5
            ],      

            toolbar: ['formatselect | bold italic forecolor backcolor  | customHeuristMedia link | align | bullist numlist outdent indent | table | removeformat | help' ],  

            content_css: [
                '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
            ],                    
            
            //valid_elements: 'p[style],strong,em,span[style],a[href],ul,ol,li',
            //valid_styles: {'*': 'font-size,font-family,color,text-decoration,text-align'},
            powerpaste_word_import: 'clean',
            powerpaste_html_import: 'clean',
            
            setup:function(editor) {

                editor.on('focus', function (e) {
                    if(current_edit_mode=='page'){
                        _layout_container.find('.lid-actionmenu[data-lid]').hide();
                        _layout_container.find('div[data-lid]').removeClass('cms-element-active');                        
                        _layout_container.find('.cms-element-overlay').css('visibility','hidden');
                    }else{
                        //prevent 
                        //window.hWin.HEURIST4.util.stopEvent(e);
                        //return false;
                    }
                });
                
                editor.on('blur', function (e) {
                        var key = tinymce.activeEditor.id.substr(3);
                        //update in _layout_content
                        var l_cfg = layoutMgr.layoutContentFindElement(_layout_content, key);
                        var new_content = tinymce.activeEditor.getContent();
                        
                        page_was_modified = (page_was_modified || l_cfg.content!=new_content);
                        
                        l_cfg.content = new_content;
                        
                });
           
                editor.ui.registry.addButton('customHeuristMedia', {
                      icon: 'image',
                      text: 'Add Media',
                      onAction: function (_) {  //since v5 onAction in v4 onclick
                            __addHeuristMedia();
                      }
                    });
                
            }
        };
        
        tinymce.init(inlineConfig);
        
        // find all dragable elements - text and widgets
        _layout_container.find('div.editable').each(function(i, item){
            var ele_ID = $(item).attr('data-lid');
             //left:2px;top:2px;
            _defineActionIcons(item, ele_ID, 'position:absolute;z-index:999;');   //left:2px;top:2px;         
        });
        
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
    // converts layout JSON content to treeview data
    //
    function _initTreePage( treeData ){
        
        if(_panel_treePage){
            
            _panel_treePage.fancytree('getTree').reload(treeData);
            
        }else{
        
        //init treeview
        var fancytree_options =
        {
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            focusOnSelect:true,
            source: treeData,
            quicksearch: true,
            
            click: function(event, data){

                var ele = $(event.target);
                if(!ele.hasClass('ui-icon')){
                    if(data.node.isActive()){
                        window.hWin.HEURIST4.util.stopEvent(event);
                        ///  that._saveEditAndClose(null, 'close'); //close editor on second click
                    }
                    if(data.node.key>0){
                        body.layout().open(options.editor_pos);
                        _layoutEditElement(data.node.key);
                    }
                
                }
            }
            //,activate: function(event, data) { }
        };

        
        fancytree_options['extensions'] = ["dnd"]; //, "filter", "edit"
        fancytree_options['dnd'] = {
                autoExpandMS: 400,
                preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
                preventVoidMoves: true, // Prevent moving nodes 'before self', etc.
                dragStart: function(node, data) {

//console.log(node.data.type);
                    
                    var is_root = node.getParent().isRootNode();
                    var is_cardinal = (node.data.type=='north' || node.data.type=='south' || 
                               node.data.type=='east' || node.data.type=='west' || node.data.type=='center');
                    
                    return !(is_root || is_cardinal);
                },
                dragEnter: function(node, data) {
 //console.log('enter '+node.data.type);                   
                    if(node.data.type=='cardinal'){
                        return false;
                    }else{
                        return (node.folder) ?true :["before", "after"];
                    }
                },
                dragDrop: function(node, data) {
                    // data.otherNode - dragging node
                    // node - target
                    var is_root = node.getParent().isRootNode();
                    var is_cardinal = (node.data.type=='north' || node.data.type=='south' || 
                               node.data.type=='east' || node.data.type=='west' || node.data.type=='center');
                    var hitMode = (is_root || is_cardinal)?'child' :data.hitMode;                    
                    
                    data.otherNode.moveTo(node, hitMode);    
                    //change layout content and redraw page
                    _layoutChangeParent(data.otherNode.key);
                }
            };

            _panel_treePage = body.find('.treePage').addClass('tree-rts')
                                .fancytree(fancytree_options); //was recordList

        }
        
        _switchMode(current_edit_mode);//, false);
        
    }
    
    function _hidePropertyView(){
    
        _layout_container.find('div[data-lid]').removeClass('cms-element-editing headline marching-ants marching');                        
        
        if(_keep_EditPanelWidth>0){
            body.layout().sizePane('west', _keep_EditPanelWidth);    
        }
        _keep_EditPanelWidth = 0;

        body.find('.treePageHeader > .heurist-helper1').show();
        body.find('.btn-page-restore').show();
        body.find('.btn-page-save').show();
        
        _panel_propertyView.hide();
        _panel_treePage.show();

    }

    //
    // mode - website or page
    //
    function _switchMode( mode, init_tinymce )
    {
    
        if(!mode){
            if(_tabControl.tabs('option','active')==0){
                mode='website';           
            }else{
                mode='page';
            }
        }else{
            var activePage = (mode=='page')?1:0;
            if(_tabControl.tabs('option','active')!=activePage){
                _tabControl.tabs({active:activePage});
                return;    
            }
        }
        
        current_edit_mode = mode;
        
        if(mode=='page'){
            
            _hidePropertyView();
            
            _toolbar_Page.show();
            _toolbar_WebSite.hide();
        
            //_layout_container.find('div.editable').addClass('tinymce-body');
            //tinymce.init({inline:true});
            if(init_tinymce!==false){
                _panel_treePage.fancytree('getTree').visit(function(node){
                    node.setSelected(false); //reset
                    node.setExpanded(true);
                });            
                _updateActionIcons(500);//it inits tinyMCE also
            } //_initTinyMCE();
            
        }else{
                    
            _hidePropertyView();
            
            _toolbar_Page.hide();
            _toolbar_WebSite.show();

            //remove highlights
            _layout_container.find('.lid-actionmenu[data-lid]').hide();
            _layout_container.find('div[data-lid]').removeClass('cms-element-active');                        
            _layout_container.find('.cms-element-overlay').css('visibility','hidden');            

            
            if(tinymce) tinymce.remove('.tinymce-body');
            
            //load website menu treeview
            if(!_editCMS_SiteMenu)
            _editCMS_SiteMenu = editCMS_SiteMenu( _panel_treeWebSite, that );
            
            
            //tinymce.init({inline:false});
            //_layout_container.find('div.editable').removeClass('tinymce-body');
        }
        
    }

    //
    // add and init action icons for page structure treeview
    //
    function _updateActionIcons(delay){ 

        if(!(delay>0)) delay = 1;

        setTimeout(function(){
            $.each( _panel_treePage.find('.fancytree-node'), function( idx, item ){
                //var ele_ID = ele.parents('li:first').find('span[data-lid]').attr('data-lid');
                var ele_ID = $(item).find('span[data-lid]').attr('data-lid');

                _defineActionIcons(item, ele_ID, 'position:absolute;right:8px;margin-top:1px;');
            });

            _initTinyMCE();
            }, delay);
    }

    //
    // for treeview on mouse over toolbar
    // item - either fancytree node or div.editable in container
    // ele_ID - element key 
    //
    function _defineActionIcons(item, ele_ID, style_pos){ 
        if($(item).find('.lid-actionmenu').length==0){ //no one defined

            if(!$(item).hasClass('fancytree-hide')){       
                $(item).css('display','block');   
            }

            ele_ID = ''+ele_ID;
            var node = _panel_treePage.fancytree('getTree').getNodeByKey(ele_ID);

            if(node==null){
                console.log('DEBUG: ONLY '+ele_ID);
                return;
            }
            var is_intreeview = $(item).hasClass('fancytree-node');

            var is_folder = node.folder;  //$(item).hasClass('fancytree-folder'); 
            var is_root = node.getParent().isRootNode();
            var is_cardinal = (node.data.type=='north' || node.data.type=='south' || 
                node.data.type=='east' || node.data.type=='west' || node.data.type=='center');

            var actionspan = '<div class="lid-actionmenu mceNonEditable" '
            +' style="'+style_pos+';display:none;color:black;background: rgba(201, 194, 249, 1) !important;'
            +'font-size:'+(is_intreeview?'12px;right:13px':'16px')+';font-weight:normal;text-transform:none;cursor:pointer" data-lid="'+ele_ID+'">' 
            //+ ele_ID
            + (is_intreeview?'<span class="ui-icon ui-icon-menu" style="width:20px"></span>'
                            :'<span class="ui-icon ui-icon-gear" style="width:20px"></span>')
            + (true || is_root || is_cardinal?'':
                ('<span data-action="drag" style="'+(false && is_intreeview?'':'display:block;')+'padding:4px" title="Drag to reposition">' //
                    + '<span class="ui-icon ui-icon-arrow-4" style="font-weight:normal"/>Drag</span>'))               
            + '<span data-action="edit" style="'+(false && is_intreeview?'':'display:block;')+'padding:4px" title="Edit style and properties">'
            +'<span class="ui-icon ui-icon-pencil"/>Style</span>';               

            //hide element for cardinal and delete for its panes                     
            if(node.data.type!='cardinal'){
                actionspan += '<span data-action="element" style="'+(false && is_intreeview?'':'display:block;')+'padding:4px"><span class="ui-icon ui-icon-plus" title="Add a new element/widget"/>Insert</span>';
            }
            if(!(is_root || is_cardinal)){
                actionspan += ('<span data-action="delete" style="'+(false && is_intreeview?'':'display:block;')+'padding:4px"><span class="ui-icon ui-icon-close" title="'
                    +'Remove element from layout"/>Delete</span>');
            }


            actionspan += '</div>';
            actionspan = $( actionspan );

            if(is_intreeview){   //in treeview
                actionspan.appendTo(item);
                
                actionspan.find('span[data-action]').hide();
                actionspan.find('span.ui-icon-menu').click(function(event){
                    var ele = $(event.target);
                    window.hWin.HEURIST4.util.stopEvent(event);
                    ele.hide();
                    ele.parent().find('span[data-action]').show();
                });
                
            }else{ 

                //$('<div>').addClass('cms-element-overlay').attr('data-lid',ele_ID).insertAfter(item);

                actionspan.insertAfter(item); //in main-content

                actionspan.find('span[data-action]').hide();
                actionspan.find('span.ui-icon-gear').click(function(event){
                    var ele = $(event.target);
                    window.hWin.HEURIST4.util.stopEvent(event);
                    ele.hide();
                    ele.parent().find('span[data-action]').show();
                });

                //actionspan.appendTo(body);    
                //actionspan.position({ my: "left top", at: "left top", of: $(item) })
            }

            actionspan.find('span[data-action]').click(function(event){
                var ele = $(event.target);

                window.hWin.HEURIST4.util.stopEvent(event);

                _lockDefaultEdit = true;
                //timeout need to activate current node    
                setTimeout(function(){
                    _lockDefaultEdit = false;

                    var ele_ID = ele.parents('.lid-actionmenu').attr('data-lid');

                    //console.log('selected '+ele_ID);                        
                    //if(!(ele_ID>0)) return;
                    _layout_container.find('.lid-actionmenu[data-lid='+ele_ID+']').hide();

                    var action = ele.attr('data-action');
                    if(!action) action = ele.parent().attr('data-action');
                    if(action=='element'){

                        //add new element or widget
                        editCMS_SelectElement(function(selected_element, selected_name){
                            _layoutInsertElement(ele_ID, selected_element, selected_name);    
                        })

                    }else if(action=='edit'){

                        //add new group/separator
                        body.layout().open(options.editor_pos);
                        _layoutEditElement(ele_ID);

                    }else if(action=='delete'){
                        //different actions for separator and field
                        var node = _panel_treePage.fancytree('getTree').getNodeByKey(''+ele_ID);
                        $(node.li).find('.fancytree-node:first').addClass('fancytree-active');
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Are you sure you wish to delete element "'+node.title+'"?', 
                        function(){ _layoutRemoveElement(ele_ID); }, 
                            {title:'Warning',yes:'Proceed',no:'Cancel'},
                            {default_palette_class: default_palette_class});        

                    }
                    },100); 

                return false;
            });

            /*
            $('<span class="ui-icon ui-icon-pencil"></span>')                                                                
            .click(function(event){ 
            //tree.contextmenu("open", $(event.target) ); 

            ).appendTo(actionspan);
            */

            //hide gear icon and overlay on mouse exit
            function _onmouseexit(event){
                var node;
                if($(event.target).hasClass('editable')){
                    node =  $(event.target);
                    _layout_container.find('.lid-actionmenu[data-lid='+node.attr('data-lid')+']').hide();
                    _layout_container.find('div[data-lid]').removeClass('cms-element-active');
                    if(!_panel_propertyView.is(':visible'))
                        _layout_container.find('.cms-element-overlay').css('visibility','hidden');
                    /*
                    if(__timeout==0){
                    __timeout = setTimeout(function(){$('.cms-element-overlay').css('visibility','hidden');},500);  
                    }
                    */ 
                }else{
                    //in tree
                    if($(event.target).is('li')){
                        node = $(event.target).find('.fancytree-node');
                    }else if($(event.target).hasClass('fancytree-node')){
                        node =  $(event.target);
                    }else{
                        //hide icon for parent 
                        node = $(event.target).parents('.fancytree-node:first');
                        if(node) node = $(node[0]);
                    }
                    if(node){
                        var ele = node.find('.lid-actionmenu'); //$(event.target).children('.lid-actionmenu');
                        ele.find('span[data-action]').hide();
                        ele.find('span.ui-icon-menu').show();
                        ele.hide();//css('visibility','hidden');
                        
                        //remove heighlight
                        _layout_container.find('div[data-lid]').removeClass('cms-element-active');
                        if(!_panel_propertyView.is(':visible'))
                            _layout_container.find('.cms-element-overlay').css('visibility','hidden');
                    }
                }
            }               

            $(item).hover ( // mousemove  mouseover
                function(event){

                    if (current_edit_mode != 'page') return;

                    var node;

                    if(__timeout>0) clearTimeout(__timeout);
                    __timeout = 0;

                    if($(event.target).parents('div.lid-actionmenu').length>0){
                        if(delay_onmove>0) clearTimeout(delay_onmove);
                        delay_onmove = 0;
                        return;
                    }

                    var is_in_page = ($(event.target).hasClass('editable') || $(event.target).parents('div.editable:first').length>0);

                    if( is_in_page ){
                        //div.editable in container 

                        if($(event.target).hasClass('editable')){
                            //console.log('itself');                              
                            node = $(event.target);
                        }else{
                            //console.log('parent');                              
                            node = $(event.target).parents('div.editable:first');
                        } 

                        //tinymce is active - do not show toolbar
                        if(_layout_container.find('div.mce-edit-focus').length>0){  //node.hasClass('mce-edit-focus')){
                            return;   
                        }

                        //node =  $(event.target);

                        _layout_container.find('.lid-actionmenu').hide(); //find other
                        var ele = _layout_container.find('.lid-actionmenu[data-lid='+node.attr('data-lid')+']');

                        var parent = node.parents('div.ui-layout-pane:first');
                        if(parent.length==0 || parent.parents('div[data-lid]').length==0){
                            parent = _layout_container;  
                        }
                        /*

                        var pos = window.hWin.HEURIST4.ui.getMousePos(event);
                        var x = pos[0] - parent.offset().left - parseInt(parent.css('padding'));
                        var y = pos[1] - parent.offset().top - parseInt(parent.css('padding'));

                        var r = parent.offset().left+parent.width();
                        //console.log(x+'+'+ele.width()+' pos='+pos[0]+'   r='+r +'  left='+parent.offset().left);                          
                        if(x+220>r){
                        x = r - 220; //ele.width();
                        }
                        if(ele.is(':visible')){

                        if(delay_onmove>0) clearTimeout(delay_onmove);
                        delay_onmove = 0;
                        delay_onmove = setTimeout(function(){
                        ele.css({ top:y+'px', left:x+'px'});
                        },500);

                        }else{
                        ele.css({ top:y+'px', left:x+'px'});    
                        ele.show();      
                        }
                        */

                        var pos = node.position();
                        //console.log(pos.top + '  ' + (pos.top+parent.offset().top));                          
                        ele.find('span[data-action]').hide();  
                        ele.find('span.ui-icon-gear').show();  
                        ele.css({top:(pos.top<0?0:pos.top)+2+'px',left:(pos.left<0?0:pos.left)+2+'px'});
                        ele.show();

                    }else {
                        //treeview node

                        if($(event.target).hasClass('fancytree-node')){
                            node =  $(event.target);
                        }else{
                            node = $(event.target).parents('.fancytree-node:first');
                        }
                        if(node){
                            node = $(node).find('.lid-actionmenu');
                            node.css('display','inline-block');//.css('visibility','visible');
                        }
                    }

                    if(node){
                        //highlight in preview/page
                        var ele_ID = $(node).attr('data-lid');


                        //highlight in treeview
                        if(is_in_page){
                            node = _panel_treePage.fancytree('getTree').getNodeByKey(ele_ID);
                            node.setActive(true);

                            _layout_container.find('div[id^="hl-"]').removeClass('cms-element-active');
                            _layout_container.find('div#hl-'+ele_ID).addClass('cms-element-active');

                        }else                            
                        { //separate overlay div - visible when mouse over tree
                            _panel_treePage.find('.fancytree-active').removeClass('fancytree-active');
                            _showOverlayForElement(ele_ID);
                        }

                    }

                },
                //mouseleave handler
                _onmouseexit
            );  

            /*                            
            $(item).mouseleave(

            );
            */
        }
    }

    //
    //
    //
    function _showOverlayForElement( ele_ID ){
        if(ele_ID>0){
            var cms_ele = _layout_container.find('div#hl-'+ele_ID);
            
            if(cms_ele.hasClass('cms-element-editing')) return;
            
            var pos = cms_ele.offset(); //realtive to document
            var pos2 = _layout_container.offset();
            var overlay_ele = $('.cms-element-overlay');//_layout_container.find('.cms-element-overlay[data-lid="'+ele_ID+'"]')
            if(overlay_ele.length==0){
                overlay_ele = $('<div>').addClass('cms-element-overlay').appendTo(_layout_container); //attr('data-lid',ele_ID).insertAfter
            }
            overlay_ele.attr('data-lid',ele_ID)
            .css({top:((pos.top-pos2.top)+'px'),
                left:((pos.left-pos2.left)+'px'),width:cms_ele.width(),height:cms_ele.height()});
            overlay_ele.css('visibility','visible');
        }
    }


    //
    // remove element
    // it prevents deletion of non-empty group
    //
    function _layoutRemoveElement(ele_id){

        var tree = _panel_treePage.fancytree('getTree');
        var node = tree.getNodeByKey(''+ele_id);
        var parentnode = node.getParent();
        var parent_container, parent_children, parent_element;
        
        
        if(parentnode.isRootNode()){
            //can not remove root element
            window.hWin.HEURIST4.msg.showMsgFlash('It is not possible remove root element');
            return;    
            
            //parent_children = _layout_content; 
            //parent_container = _layout_container;
        }else{
            
            
            /*
            if(parentnode.folder && parentnode.countChildren()==1){
                window.hWin.HEURIST4.msg.showMsgFlash('It is not possible remove the last element in group. Remove the entire group');
                return;    
            }
            */
            
            //remove child
            parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
            parent_children = parent_element.children;
            parent_container = _layout_container.find('#hl-'+parentnode.key);//div[data-lid="
            
        }

        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();
        
        //find index in _layout_content
        var idx = -1;
        for(var i=0; i<parent_children.length; i++){
          if(parent_children[i].key==ele_id){
              idx = i;
              break;
          }   
        }        
        //from json
        parent_children.splice(idx, 1); //remove from children

        //from tree
        node.remove();
        
        //recreate
        if(parent_element && parent_element.type=='accordion'){
            layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            layoutMgr.layoutInitTabs(parent_element, parent_container)
        }else{
            layoutMgr.layoutInit(parent_children, parent_container, {rec_ID:home_page_record_id}); 
        }
        
        page_was_modified = true;
        
        _updateActionIcons(200); //it inits tinyMCE also
        
    }
    
    //
    // Reflects changes in tree
    //
    function _layoutChangeParent(ele_id){

        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();
        
        var affected_element = layoutMgr.layoutContentFindElement(_layout_content, ele_id);
        

        var oldparent = layoutMgr.layoutContentFindParent(_layout_content, ele_id);
        var parent_children;
        
        //remove from old parent -----------
        if(oldparent=='root'){
            parent_children = _layout_content;
        }else{
            parent_children = oldparent.children;
        }
        var idx = -1;
        for(var i=0; i<parent_children.length; i++){
          if(parent_children[i].key==ele_id){
              idx = i;
              break;
          }   
        }        
        parent_children.splice(idx, 1); //remove from children
        
        //add to new parent  ---------------
        var tree = _panel_treePage.fancytree('getTree');
        var node = tree.getNodeByKey(''+ele_id);
        var prevnode = node.getPrevSibling();
        var parentnode = node.getParent();
        var parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
        parent_children = parent_element.children;
        
        if(prevnode==null){
            idx = 0;
        }else{
            for(var i=0; i<parent_children.length; i++){
              if(parent_children[i].key==prevnode.key){
                  idx = i+1;
                  break;
              }   
            }        
        }
        if(idx==parent_children.length){
            parent_children.push(affected_element);
        }else{
            parent_children.splice(idx, 0, affected_element);    
        }
        
        //redraw page
        layoutMgr.layoutInit(_layout_content, _layout_container, {rec_ID:home_page_record_id});
        _updateActionIcons(200); //it inits tinyMCE also
        
        page_was_modified = true;
    }
    
    //
    // Opens element/widget property editor  (editCMS_ElementCfg/WidgetCfg)
    // 1. css properties
    // 2  flexbox properties
    // 3. widget properties
    //
    function _layoutEditElement(ele_id){
    
/*        
    var editFields = [                
        {"dtID": "name",
            "dtFields":{
                "dty_Type":"freetext",
                "rst_DisplayName":"Name:",
        }},
        
        {
        "groupHeader": "Styles",
        "groupTitleVisible": true,
        "groupType": "accordion",
            "children":[
            {"dtID": "background",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Background Color:",
                    "rst_DisplayHelpText": "Background color for element",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#e0dfe0"
            }},
            {"dtID": "background-image",
                "dtFields":{
                    "dty_Type":"file",
                    "rst_DisplayName": "Background Image:",
                    "rst_DisplayHelpText": "Background image",
                    "rst_DefaultValue": ""
            }},
            
            {"dtID": "border-color",
                "dtFields":{
                    "dty_Type":"freetext",
                    "rst_DisplayName": "Border Color:",
                    "rst_DisplayHelpText": "Border color for element",
                    "rst_FieldConfig":{"colorpicker":"colorpicker"},
                    "rst_DefaultValue": "#eeeeee"
            }},
            {"dtID": "border-width",
                "dtFields":{
                    "dty_Type":"integer",
                    "rst_DisplayName": "Border width:",
                    "rst_DisplayHelpText": "Border width in pixels"
            }},
            {"dtID": "border-radius",
            "dtFields":{
                "dty_Type":"integer",
                "rst_DisplayName": "Corner radius:",
                "rst_DisplayHelpText": "Value from 0 to 16",
                "rst_DefaultValue": "0"
            }}
        ]}];
*/        
      
        //1. show div with properties over treeview
        _panel_treePage.hide();
        body.find('.treePageHeader > .heurist-helper1').hide();
        body.find('.btn-page-restore').hide();
        body.find('.btn-page-save').hide();
        _panel_propertyView.show();
        _layout_container.find('.cms-element-overlay').css('visibility','hidden'); //hide overlay above editing element
        _layout_container.find('div[data-lid]').removeClass('cms-element-active');                        
        _layout_container.find('div[id="hl-'+ele_id+'"]').addClass('cms-element-editing headline marching-ants marching');
        
        if(body.layout().state['west']['outerWidth']<400){
            _keep_EditPanelWidth = body.layout().state['west']['outerWidth'];
            body.layout().sizePane('west', 400);    
        }
        
        var element_cfg = layoutMgr.layoutContentFindElement(_layout_content, ele_id);  //json
        
        var is_cardinal = (element_cfg.type=='north' || element_cfg.type=='south' || 
                element_cfg.type=='east' || element_cfg.type=='west' || element_cfg.type=='center');
            
        if(is_cardinal){
             //find parent
             var node = _panel_treePage.fancytree('getTree').getNodeByKey(''+ele_id);
             var parentnode = node.getParent();
             ele_id = parentnode.key;
             element_cfg = layoutMgr.layoutContentFindElement(_layout_content, ele_id);
        }
        
        //show overlay for editing element
        _showOverlayForElement( ele_id );
        
        editCMS_ElementCfg(element_cfg, _layout_container, _panel_propertyView, function(new_cfg){

                    //save
                    if(new_cfg){
                        
                        layoutMgr.layoutContentSaveElement(_layout_content, new_cfg);

                        //update treeview                    
                        var node = _panel_treePage.fancytree('getTree').getNodeByKey(''+new_cfg.key);
                        node.setTitle(new_cfg.title);
                        _defineActionIcons($(node.li).find('span.fancytree-node:first'), new_cfg.key, 
                                    'position:absolute;right:8px;padding:2px;margin-top:0px;');
                                    
                        if(new_cfg.type=='cardinal'){
                            //recreate cardinal layout
                            layoutMgr.layoutInitCardinal(new_cfg, _layout_container);
                        }
                        
                        page_was_modified = true;            
                    }
                    //close
                    _hidePropertyView();
                    
                } );
    }
    
    
    //
    // Add text element or widget
    // 1. Find parent element for "ele_id"
    // 2. Add json to _layout_content
    // 3. Add element to _layout_container
    // 4. Update treeview
    //
    // @todo - store templates as json text 
    function _layoutInsertElement(ele_id, widget_id, widget_name){
        
        //border: 1px dotted gray; border-radius: 4px;margin: 4px;
        
        var new_ele = {name:'Text', type:'text', css:{'border':'1px dotted gray','border-radius':'4px','margin':'4px'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
        
        if(widget_id=='group'){
            new_ele = {name:'Group', type:'group', css:{'border':'1px dotted gray','border-radius':'4px','margin':'4px'}, children:[ new_ele ]};
        }else if(widget_id=='tabs'){
            
            new_ele = {name:'TabControl', type:'tabs', css:{}, children:[ 
                {name:'Tab 1', type:'group', css:{}, children:[ new_ele ]},
                {name:'Tab 2', type:'group', css:{}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
            ]};
        }else if(widget_id=='accordion'){    
            new_ele = {name:'Accordion', type:'accordion', css:{}, children:[ 
                {name:'Panel 1', type:'group', css:{}, children:[ new_ele ]}
            ]};
        }else if(widget_id=='cardinal'){    
            
            new_ele = {name:'Cardinal', type:'cardinal', css:{position:'relative',
                        'min-height':'300px','min-width':'300px',
                        'height':'500px','width':'800px',flex:'0 1 auto'},  //,'width':'100%'
            children:[
            {name:'Center', type:'center', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'North', type:'north', options:{size:80}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'South', type:'south', options:{size:80}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'West', type:'west', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'East', type:'east', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
            ]};
          
        }else if(widget_id.indexOf('heurist_')===0){
            
            //btn_visible_newrecord, btn_entity_filter, search_button_label, search_input_label
            new_ele = {appid:widget_id, name:widget_name, css:{}, options:{}};
            
        }
        else if(widget_id=='group_2'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[
                    {name:'Column 1', type:'group', css:{flex:'1 1 auto'}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
                    {name:'Column 2', type:'group', css:{flex:'1 1 auto'}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
                ]
            };
            
        }
        else if(widget_id=='text_media'){
            
            new_ele = {name:'Media and text', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[
                {name:'Media', type:'text', css:{flex:'0 1 auto'}, content:"<p><img src=\"https://heuristplus.sydney.edu.au/heurist/hclient/assets/v6/logo.png\" width=\"300\"</p>"},
                {name:'Text', type:'text', css:{flex:'1 1 auto'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"}
                ]
            };
            
        }
        else if(widget_id=='text_banner'){

            var imgs = [
 'https://images.unsplash.com/photo-1524623243236-187b50e18f9f?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1228&q=80',
 'https://images.unsplash.com/photo-1494500764479-0c8f2919a3d8?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1170&q=80',
 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1171&q=80',
 'https://images.unsplash.com/photo-1529998274859-64a3872a3706?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1170&q=80',
 'https://images.unsplash.com/40/whtXWmDGTTuddi1ncK5v_IMG_0097.jpg?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1171&q=80'];
 
            var k = Math.floor(Math.random() * 5);
            
            new_ele = {name:'2 columns', type:'group', 
                    css:{display:'flex', 'justify-content':'center', 'align-items': 'center', 'min-height':'300px',
                    'background-image': 'url('+imgs[k]+')', 'background-size':'auto',  'background-repeat': 'no-repeat'},
                children:[
                    new_ele
                ]
            };
            
            
        }
        else if(widget_id=='text_2'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            var child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
            new_ele.children.push(child);
            
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            
        }
        else if(widget_id=='text_3'){
            
            new_ele = {name:'3 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            var child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Lorem ipsum dolor sit amet ...</p>"};
            new_ele.children.push(child);
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 3';
            new_ele.children.push(child);

        }
        else if(widget_id.indexOf('tpl_')==0){
            
            _prepareTemplate(ele_id, widget_id);
            return;
        }
        
        _layoutInsertElement_continue(ele_id, new_ele);
    }       
    
    //
    //
    //    
    function _layoutInsertElement_continue(ele_id, new_element_json){

        var tree = _panel_treePage.fancytree('getTree');
        var parentnode = tree.getNodeByKey(ele_id);
        var parent_container, parent_children, parent_element;

        tinymce.remove('.tinymce-body'); //detach
        _layout_container.find('.lid-actionmenu').remove();

        if(parentnode.folder){
            //add child
            /*
            if(parentnode.parent){
            //insert after visible element
            var l_cfg = layoutMgr.layoutContentFindElement(_layout_content, parentnode.parent.key);
            parent_container = body.find('div[data-lid="'+parentnode.parent.key+'"]');
            parent_children = l_cfg.children;
            }else{
            parent_container = '#main-content';
            parent_children = _layout_content;
            }
            */
            parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.key);
            parent_container = _layout_container.find('#hl-'+parentnode.key); //div[data-lid="
            parent_children = parent_element.children;

        }else{
            //add sibling
            if(parentnode.parent.isRootNode()){
                parent_element = null;
                parent_container = _layout_container;
                parent_children = _layout_content;
            }else{
                parent_element = layoutMgr.layoutContentFindElement(_layout_content, parentnode.parent.key);
                parent_container = _layout_container.find('#hl-'+parentnode.parent.key);
                parent_children = parent_element.children;
            }
        }


        parent_children.push(new_element_json);
        layoutMgr.layoutInitKey(parent_children, parent_children.length-1);

        //recreate
        if(parent_element && parent_element.type=='accordion'){
            layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            layoutMgr.layoutInitTabs(parent_element, parent_container)
            //layoutMgr.layoutInit(_layout_content, _layout_container);    
        }else{
            layoutMgr.layoutInit(parent_children, parent_container, {rec_ID:home_page_record_id});
        }   


        //update tree
        if(parentnode.folder){
            parentnode.addChildren(new_element_json);    
            //parentnode.addNode(new_element_json);
        }else{
            var beforenode = parentnode.getNextSibling();
            parentnode = parentnode.getParent();
            parentnode.addChildren(new_element_json, beforenode);    
            //parentnode.addNode(new_element_json, 'after');
        }

        setTimeout(function(){
            parentnode.visit(function(node){
                node.setExpanded(true);
            });
            _updateActionIcons(200);
            },300);

        page_was_modified = true;
        /*        
        [{name:'Layout', type:'group', //or cardinal
        children:[
        {name:'Text', type:'text', css:{}, content:'<p>Text element</p>'}
        ] 
        }];        
        */        
    }

    //
    //
    //
    function _prepareTemplate(ele_id, template_name){
    
        if(template_name.indexOf('tpl_')==0){
            template_name = template_name.substring(4);
        }
        
        // 1. load template files
        var sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.json';

        // 2. Loads template json
        $.getJSON(sURL, 
        function( new_element_json ){
                        
            if(template_name=='blog'){
                
                try{
                
                var sURL2 = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.js';
                // 3. Execute template script to replace template variables, adds filters and smarty templates
                    $.getScript(sURL2, function(data, textStatus, jqxhr){ //it will trigger oncomplete
                          //console.log( data ); // Data returned
                          //console.log( textStatus ); // Success
                          //console.log( jqxhr.status ); // 200
                          //console.log( "Load was performed." );
                          _prepareTemplateBlog(ele_id, new_element_json, _layoutInsertElement_continue);
                          
                          
                    }).fail(function( jqxhr, settings, exception ) {
                        console.log( 'Error in template script: '+exception );
                    });
                    
                }catch(e){
                    alert('Error in template script');
                }
            }else{
                
                _layoutInsertElement_continue( ele_id, new_element_json );
            }

        }); //on template json load
        
    }
    
    
    //
    //  Save page configuration _layout_content) into RT_CMS_MENU record 
    //
    function _saveLayoutCfg( callback ){
        
        if(!(options.record_id>0)) return;
        
        window.hWin.HEURIST4.msg.bringCoverallToFront();
        
        var newval = window.hWin.HEURIST4.util.cloneJSON(_layout_content);
        
        //remove keys and titles
        function __cleanLayout(items){
            
            for(var i=0; i<items.length; i++){
                items[i].key = null;
                delete items[i].key;
                items[i].title = null;
                delete items[i].title;
                if(items[i].children){
                    __cleanLayout(items[i].children);    
                }
            }
        }
        __cleanLayout(newval);
        
        var newname = newval[0].name;
        
        // if page consist one group and one text - save only content of this text
        // it allows edit content in standard record edit
        if(newval[0].children && newval[0].children.length==1 && newval[0].children[0].type=='text'){
            newval = newval[0].children[0].content;
        }else{
            newval = JSON.stringify(newval);    
        }
        
        var request = {a: 'addreplace',
                        recIDs: options.record_id,
                        dtyID: DT_EXTENDED_DESCRIPTION,
                        rVal: newval,
                        needSplit: true};
        
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == hWin.ResponseStatus.OK){
                    if(response.data.errors==1){
                        var errs = response.data.errors_list;
                        var errMsg = errs[Object.keys(errs)[0]];
                        window.hWin.HEURIST4.msg.showMsgErr( errMsg );
                    }else
                    if(response.data.noaccess==1){
                        window.hWin.HEURIST4.msg.showMsgErr('It appears you do not have enough rights (logout/in to refresh) to edit this record');
                        
                    }else{
                        page_was_modified = false;
                        page_cache[options.record_id][DT_EXTENDED_DESCRIPTION] = newval; //update in cache
                        
                        //window.hWin.HEURIST4.msg.showMsgFlash('saved');

                        if(_editCMS_SiteMenu && newname!=page_cache[options.record_id][DT_NAME]) {
                            body.find('.treePageHeader > h2').text( newname ); 
                            _editCMS_SiteMenu.renameMenuEntry(options.record_id, newname);
                        }
                        
                        if($.isFunction(callback)) callback.call(this);
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });         
    }
    
    //
    //
    //
    function _showWebSiteMenu(){
        _switchMode('website');
    }

    //public members
    var that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        layoutInsertElement: function(insert_ele_id, selected_element){
            _layoutInsertElement(insert_ele_id, selected_element);
        },
        
        startCMS: function(_options){
            return _startCMS( _options );    
        },
        
        closeCMS: function(){
            _closeCMS();            
        },
        
        switchMode: function(mode){
            _switchMode(mode);            
        },
        
        warningOnExit: function(callback){
            return _warningOnExit(callback)
        },
        
        resetModified: function(){
            page_was_modified = false;    
        }
    }
    
    return that;
}
   
