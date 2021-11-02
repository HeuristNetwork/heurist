/*
* editCMS.js - loads websiteRecord.php in edit mode
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
// layoutMgr - global variable defined in hLayoutMgr
// home_page_record_id

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
        
        _layout_content,   // JSON config 
        _layout_container; // main-content with CMS content

    var default_palette_class = 'ui-heurist-publish';
        
    var is_edit_widget_open; //??
    var delay_onmove = 0;
    
    var current_edit_mode = 'page'; //or website
    
    var keep_expanded_nodes = []; //in_website_menu
        
    var RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
    RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
    DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
    DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
    //     DT_CMS_THEME = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_THEME'],
    DT_NAME       = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
    DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'],
    DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];

    var body = $(this.document).find('body');
    var dim = {h:body.innerHeight(), w:body.innerWidth()};
    dim.h = (window.hWin?window.hWin.innerHeight:window.innerHeight);
    
    
    function _startCMS(_options){
        
        options = _options;
        //
        // add edit layout - top most ent_wrapper becomes 
        //
        if($(this.document).find('.editStructure').length==0){
            
                var new_ele = $('<div class="ui-layout-center"></div>');//.prependTo(body);
                                             
                //body.children().shift();
                body.children().appendTo(new_ele);
                
                new_ele.appendTo(body);
            
                var east_panel = $('<div class="ui-layout-east">'      
                        +'<div class="ent_wrapper editStructure">' 
                        +'<div class="ent_header" style="padding:10px 20px 4px 0px;border-bottom:1px solid lightgray">' //instruction and close button
                            +'<span style="display:inline-block;width:32px;height:24px;font-size:29px;margin:0px;cursor:pointer" class="bnt-cms-hidepanel ui-icon ui-icon-carat-2-e"/>'
                            
                            /*
                            +'<span style="font-style:italic;display:inline-block">Drag to reposition<br>'
                            +'Select or <span class="ui-icon ui-icon-gear" style="font-size: small;"/> to modify</span>&nbsp;&nbsp;&nbsp;'
                            */
                            
                            +'<div class="toolbarWebSite"  style="float:right;">'
                            
                                +'<button title="Edit website properties" class="btn-website-edit"/>'
                                +'<button  title="Add top level menu" class="btn-website-addpage"/>'
                                +'<span style="display:inline-block;width:20px">&nbsp;</span>'
                                //+'<button title="Show website/main menu strucuture" class="btn-website-menu"/>'
                                +'<button  title="Exit/Close content editor" class="bnt-cms-exit"/>'
                            
                            +'</div>'

                            +'<div class="toolbarPage"  style="float:right;">'
                            
                                +'<button title="Save changes for current page" class="btn-page-save ui-button-action">Save Page</button>'
                                +'<button title="Discard all changed and restore old version of page" class="btn-page-restore">Discard</button>'
                                +'<span style="display:inline-block;width:20px">&nbsp;</span>'
                                +'<button title="Show website/main menu strucuture" class="bnt-website-menu"/>'
                                +'<button  title="Exit/Close content editor" class="bnt-cms-exit"/>'
                            
                            +'</div>'
                            
                        +'</div>'
                        
                        +'<div class="treeWebSite ent_content_full" style="padding:10px;display:none"/>' //treeview - edit website menu
                        +'<div class="treePage ent_content_full" style="padding:10px;"/>' //treeview - edit page
                        +'<div class="propertyView ent_content_full" style="padding:10px;display:none"/>' //edit properties for element
                        
/*                        
                        +'<div class="ent_footer" style="text-align:center;border-top:1px solid lightgray;padding:2px">'
                                +'<div class="btn-page-save ui-button-action">Save Page</div>'
                                +'<div class="btn-page-restore">Discard</div>'
                        +'</div>'
*/                        
                
                                             +'</div></div>').appendTo(body);
            
            
                    var layout_opts =  {
                        applyDefaultStyles: true,
                        maskContents:       true,  //alows resize over iframe
                        //togglerContent_open:    '&nbsp;',
                        //togglerContent_closed:  '&nbsp;',
                        east:{
                            size: 340, //@todo this.usrPreferences.structure_width,
                            maxWidth:800,
                            minWidth:340,
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
                                var tog = body.find('.ui-layout-toggler-east');
                                tog.removeClass('prominent-cardinal-toggler');
                                tog.find('.heurist-helper2').remove();
                            },
                            onclose_end : function( ){ 
                                var tog = body.find('.ui-layout-toggler-east');
                                tog.addClass('prominent-cardinal-toggler');
                                $('<span class="heurist-helper2" style="font-size:9px;">Edit Content</span>').appendTo(tog);
                            },
                            togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                            togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-w"></div>',
                        },
                        center:{
                            minWidth:400,
                            contentSelector: '.ent_wrapper', //@todo !!!! for particule template ent_wrapper can be missed
                            //pane_name, pane_element, pane_state, pane_options, layout_name
                            onresize_end : function(){
                                //that.handleTabsResize();                            
                            }    
                        }
                    };

                    body.layout(layout_opts); //.addClass('ui-heurist-bg-light')

                    east_panel.find('.btn-page-save').button().click(_saveLayoutCfg)
                    east_panel.find('.btn-page-restore').button().click(
                        function(){
                            _startCMS({record_id:options.record_id, container:'#main-content', content:null});
                        }
                    );
                    
                    
                    east_panel.find('.btn-website-edit').button({icon:'ui-icon-pencil'}).click(_editHomePageRecord);
                    east_panel.find('.btn-website-addpage').button({icon:'ui-icon-plus'}).click(_addNewPage);
                    
                    east_panel.find('.bnt-website-menu').button({icon:'ui-icon-menu'}).click(_showWebSiteMenu);
                    
                    east_panel.find('.bnt-cms-hidepanel').click(function(){ body.layout().close('east'); } );
                    east_panel.find('.bnt-cms-exit').button({icon:'ui-icon-close'}).click(_closeCMS);
                    
                    _panel_propertyView = body.find('.propertyView');
                    _panel_treeWebSite = body.find('.treeWebSite');
                    
                    _toolbar_WebSite = body.find('.toolbarWebSite');
                    _toolbar_Page = body.find('.toolbarPage');

                    
        }
        
        
        body.layout().show('east', true );
        
        //load content for current page from DT_EXTENDED_DESCRIPTION
    /*    
        _layout_content =  [{name:'Page', type:'group',
            children:[
                {name:'Text', type:'text', css:{}, content:"<p>Hello World!</p>"}, 
                {name:'Text2', type:'text', css:{}, content:"<p>Goodbye Cruel World!</p>"}, 
            ] 
        }];


        _layout_content =  [{name:'Cardinal', type:'cardinal',
            children:[
                {name:'Center', type:'center',
                    children:[ {name:'Map', type:'text', css:{}, content:"<p>Center!</p>"} ]}, 
                {name:'North', type:'north', size:80,
                    children:[  {name:'Header', type:'text', css:{}, content:"<p>Header!</p>"} ]}, 
                {name:'West', type:'west', 
                    children:[ 
                        {name:'Search', type:'text', css:{}, content:"<p>This is place for search widget!</p>"}
                    ]},
                {name:'East', type:'east', 
                    children:[ 
                        {name:'TabControl', type:'tabs',
                               children:[ 
                                    {name:'Tab1', type:'text', css:{}, content:"<p>Content for tab #1</p>"},
                                    {name:'Accordion', type:'accordion', css:{}, 
                                        children:[
                                            {name:'Tab2.Acc1', type:'text', css:{}, content:"<p>Accordion panel 1</p>"}, 
                                            {name:'Tab2.Acc2', type:'text', css:{}, content:"<p>Accordion panel 2</p>"}, 
                                            {name:'Tab2.Acc3', type:'text', css:{}, content:"<p>Accordion panel 3</p>"}, 
                                            {name:'Tab2.Acc4', type:'text', css:{}, content:"<p>Accordion panel 4</p>"}, 
                                        ] 
                                    },
                                    {name:'Tab3', type:'group',
                                        children:[
                                            {name:'Tab3.Text1', type:'text', css:{}, content:"<p>Hello World on Tab3!</p>"}, 
                                            {name:'Tab3.Text2', type:'text', css:{}, content:"<p>Goodbye Cruel World on Tab3!</p>"}, 
                                        ] 
                                    }
                               ]}
                    ]}
            ] 
        }];

        _layout_content =  [{name:'Cardinal', type:'cardinal',
            children:[
                {name:'Center', type:'center',
                    children:[ {appid:'heurist_resultList', name:'Results', css:{}, options:{}} ]}, 
                {name:'North', type:'north', size:80,
                    children:[ {appid:'heurist_Search', name:'Filter', css:{}, options:{}} ]}, 
                {name:'West', type:'west', 
                    children:[ 
                        {name:'Search', type:'text', css:{}, content:"<p>This is place for search widget!</p>"}
                    ]},
                {name:'East', type:'east', 
                    children:[ 
                        {name:'TabControl', type:'tabs',
                               children:[ 
                                    {name:'Tab1', type:'text', css:{}, content:"<p>Content for tab #1</p>"},
        
        
                                    {name:'Accordion', type:'accordion', css:{}, 
                                        children:[
                                            {name:'Tab2.Acc1', type:'text', css:{}, content:"<p>Accordion panel 1</p>"}, 
                                            {name:'Tab2.Acc2', type:'text', css:{}, content:"<p>Accordion panel 2</p>"}, 
                                            {name:'Tab2.Acc3', type:'text', css:{}, content:"<p>Accordion panel 3</p>"}, 
                                            {name:'Tab2.Acc4', type:'text', css:{}, content:"<p>Accordion panel 4</p>"}, 
                                        ] 
                                    },
                                    {name:'Tab3', type:'group',
                                        children:[
                                            {name:'Tab3.Text1', type:'text', css:{}, content:"<p>Hello World on Tab3!</p>"}, 
                                            {name:'Tab3.Text2', type:'text', css:{}, content:"<p>Goodbye Cruel World on Tab3!</p>"}, 
                                        ] 
                                    }
                               ]}
                    ]}
            ] 
        }];
    */
        
        if(!options){
            options.record_id = 0;
            options.container = '#main-content';
            options.content = null;
        }
        
        _layout_container = body.find(options.container);
        
        if(options.content)
        {
           _layout_content = options.content;
           _initPage();
            
        }else if (options.record_id>0 ){
            //load by page_record_id
                var surl = window.hWin.HAPI4.baseURL+'?db='
                    +window.hWin.HAPI4.database+'&field='+DT_EXTENDED_DESCRIPTION+'&recid='+options.record_id;
                $.get( surl,  
                function(res){
                    options.content = res;
                    _layout_content = res;
                    _initPage();
                });
            
        }
    }    
    
    
    //
    //
    //
    function _editHomePageRecord(record_id){

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
                            _refreshWebsite();
                        }
            }});
    }
    
    //
    //
    //
    function _addNewPage(){
            _selectMenuRecord(home_page_record_id, function( page_id ){
                //was_something_edited = true;
                current_page_id = page_id;
                _refreshMainMenu();
            });
    }
    
    //
    // 1. close control panel
    // 2. reload content
    //
    function _closeCMS(){
        
        // 1. close control panel
        body.layout().hide('east'); // .show('east', false );
        
        //2. reload content
        layoutMgr.setEditMode(false);
        layoutMgr.layoutInit(_layout_content, _layout_container);
        
        if($.isFunction(options.close)){
            options.close.call();
        }
    }
    
    //
    // load page structure into tree and init layout
    //
    function _initPage(){
        
        if(tinymce) tinymce.remove('.tinymce-body'); //detach
        
        layoutMgr.setEditMode(true);
        var res = layoutMgr.layoutInit(_layout_content, _layout_container);
        
        if(res===false){
            window.hWin.HEURIST4.msg.showMsgFlash('Old format. Edit in Heurist interface');
            //clear treeview
            _initTreePage([]);
        }else{
            _layout_content = res;
            _initTreePage(_layout_content);
            
        }
        
        _highlightCurrentPage();
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
                        l_cfg.content = tinymce.activeEditor.getContent();
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
            _defineActionIcons(item, ele_ID, 'position:absolute;padding:2px;z-index:2;');   //left:2px;top:2px;         
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
                }
            },
            activate: function(event, data) { 
                //main entry point to start edit layout element/widget - open formlet or tinymce
                /*if(data.node.key>0){

                    that.selectedRecords([data.node.key]);
                    if(!that._lockDefaultEdit){
                    
                        if(that.options.external_preview){
                            that.options.external_preview.manageRecords('saveQuickWithoutValidation',
                                function(){
                                    that.previewEditor.manageRecords('setDisabledEditForm', true)
                                    that._onActionListener(event, {action:'edit'}); //default action of selection            
                                });
                        }else{
                            that.previewEditor.manageRecords('setDisabledEditForm', true)
                            that._onActionListener(event, {action:'edit'}); //default action of selection            
                        }
                        
                    }
                }*/
            }
        };

        /*
        fancytree_options['extensions'] = ["dnd"]; //, "filter", "edit"
        fancytree_options['dnd'] = {
                autoExpandMS: 400,
                preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
                preventVoidMoves: true, // Prevent moving nodes 'before self', etc.
                dragStart: function(node, data) {
                    return that._dragIsAllowed;
                },
                dragEnter: function(node, data) {
                    return (node.folder) ?true :["before", "after"];
                },
                dragDrop: function(node, data) {
                    
                    data.otherNode.moveTo(node, data.hitMode);    
                    //save treeview in database
                    that._dragIsAllowed = false;
                    
                    if(that.options.external_preview){
                        that.options.external_preview.manageRecords('saveQuickWithoutValidation',
                               function(){ that._saveRtStructureTree() });
                    }else{
                        setTimeout(function(){
                            that._saveRtStructureTree();
                        },500);
                    }
                    
                }
            }; */

            _panel_treePage = body.find('.treePage').addClass('tree-rts')
                                .fancytree(fancytree_options); //was recordList

        }
        
        _switchMode(current_edit_mode, false);
        
        _panel_treePage.fancytree('getTree').visit(function(node){
                node.setSelected(false); //reset
                node.setExpanded(true);
            });            
        _updateActionIcons(500);//it inits tinyMCE also
        
    }

    //
    //
    //
    function _switchMode( mode, init_tinymce )
    {
    
        current_edit_mode = mode;
        
        if(mode=='page'){
            _panel_treePage.show();
            _panel_propertyView.hide();
            _panel_treeWebSite.hide();

            _toolbar_Page.show();
            _toolbar_WebSite.hide();
        
            //_layout_container.find('div.editable').addClass('tinymce-body');
            //tinymce.init({inline:true});
            if(init_tinymce!==false) _initTinyMCE();
            
        }else{
            _panel_treeWebSite.show();
            _panel_treePage.hide();
            _panel_propertyView.hide();
            
            _toolbar_Page.hide();
            _toolbar_WebSite.show();
            
            if(tinymce) tinymce.remove('.tinymce-body');
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

                _defineActionIcons(item, ele_ID, 'position:absolute;right:8px;padding:2px;margin-top:0px;');
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
           if($(item).find('.lid-actionmenu').length==0){
               
               if(!$(item).hasClass('fancytree-hide')){       
                    $(item).css('display','block');   
               }

               ele_ID = ''+ele_ID;
               var node = _panel_treePage.fancytree('getTree').getNodeByKey(ele_ID);

               if(node==null){
                   console.log('DEBUG: ONLY '+ele_ID);
                   return;
               }
               
               var is_folder = node.folder;  //$(item).hasClass('fancytree-folder'); 
               var is_root = node.getParent().isRootNode();
               var is_cardinal = (node.data.type=='north' || node.data.type=='south' || 
                               node.data.type=='east' || node.data.type=='west' || node.data.type=='center');
               
               var actionspan = '<div class="lid-actionmenu mceNonEditable" '
                    +' style="'+style_pos+';display:none;color:black;background:#95A7B7 !important;'
                    +'font-size:9px;font-weight:normal;text-transform:none;cursor:pointer" data-lid="'+ele_ID+'">' + ele_ID
                    + (is_root || is_cardinal?'':
                    ('<span data-action="drag" style="background:lightgreen;padding:4px;font-size:9px;font-weight:normal" title="Drag to reposition">'
                    + '<span class="ui-icon ui-icon-arrow-4" style="font-size:9px;font-weight:normal"/>Drag</span>'))               
                    + '<span data-action="edit" style="background:lightgray;padding:4px;font-size:9px;font-weight:normal" title="Edit style and properties">'
                    +'<span class="ui-icon ui-icon-pencil" style="font-size:9px;font-weight:normal"/>Style</span>';               
                   
               //hide element for cardinal and delete for its panes                     
               if(node.data.type!='cardinal'){
                   actionspan += '<span data-action="element" style="background:#ECF1FB;padding:4px"><span class="ui-icon ui-icon-plus" title="Add a new element/widget" style="font-size:9px;font-weight:normal"/>Element</span>';
               }
               if(!(is_root || is_cardinal)){
                   actionspan += ('<span data-action="delete" style="background:red;padding:4px"><span class="ui-icon ui-icon-close" title="'
                        +'Remove element from layout" style="font-size:9px;font-weight:normal"/>Delete</span>');
               }
               
               
               actionspan += '</div>';
               actionspan = $( actionspan );
               
               var is_intreeview = $(item).hasClass('fancytree-node');
                   
               if(is_intreeview){   //in treeview
                   actionspan.appendTo(item);
               }else{ 
                   actionspan.insertAfter(item); //in main-content
                   //actionspan.appendTo(body);    
                   //actionspan.position({ my: "left top", at: "left top", of: $(item) })
               }
                   
               actionspan.find('span').click(function(event){
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
                            _layoutEditElement(ele_ID);

                        }else if(action=='delete'){
                            //different actions for separator and field
                            window.hWin.HEURIST4.msg.showMsgDlg(
                                'Are you sure you wish to delete this element?', function(){ _layoutRemoveElement(ele_ID); }, 
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

               //hide icons on mouse exit
               function _onmouseexit(event){
                       var node;
                       if($(event.target).hasClass('editable')){
                          node =  $(event.target);
                          _layout_container.find('.lid-actionmenu[data-lid='+node.attr('data-lid')+']').hide();
                          _layout_container.find('div[data-lid]').removeClass('cms-element-active');
                       }else{
                       
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
                               ele.hide();//css('visibility','hidden');
                               //remove heighlight
                               _layout_container.find('div[data-lid]').removeClass('cms-element-active');
                           }
                       }
               }               
               
               $(item).hover ( // mousemove  mouseover
                   function(event){
                       
                       if (current_edit_mode != 'page') return;
                       
                       var node;
                       
                       if($(event.target).parents('div.lid-actionmenu').length>0){
                              if(delay_onmove>0) clearTimeout(delay_onmove);
                              delay_onmove = 0;
                              return;
                       }
                       
                       if( $(event.target).hasClass('editable') || $(event.target).parents('div.editable:first').length>0){
                          //div.editable in container 
                          
                          if($(event.target).hasClass('editable')){
//console.log('itself');                              
                            node = $(event.target);
                          }else{
//console.log('parent');                              
                            node = $(event.target).parents('div.editable:first');
                          } 
                          
                          //tinymce is active - do not sow toolbar
                          if(node.hasClass('mce-edit-focus')){
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
                           //highlight in preview
                           var ele_ID = $(node).attr('data-lid');
                           _layout_container.find('div[id^="hl-"]').removeClass('cms-element-active');
                           if(ele_ID>0)
                           _layout_container.find('div#hl-'+ele_ID).addClass('cms-element-active');
                       }
                       
                   }
               );               
               $(item).mouseleave(
                   _onmouseexit
               );
           }
    }
    
    //
    // remove element
    // it prevents deletion of non-empty group
    //
    function _layoutRemoveElement(ele_id){

        var tree = _panel_treePage.fancytree('getTree');
        var node = tree.getNodeByKey(ele_id);
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
            layoutMgr.layoutInit(parent_children, parent_container); 
        }
        
        _updateActionIcons(200); //it inits timyMCE also
        
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
        _panel_propertyView.show();

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
                                    
                    }
                    //close
                    _panel_treePage.show();
                    _panel_propertyView.hide();
                    
                } );
    }
    
    //
    // Add text element or widget
    // 1. Find parent element for "ele_id"
    // 2. Add json to _layout_content
    // 3. Add element to _layout_container
    // 4. Update treeview
    //
    function _layoutInsertElement(ele_id, widget_id, widget_name){
        
        //border: 1px dotted gray; border-radius: 4px;margin: 4px;
        
        var new_ele = {name:'Text', type:'text', css:{}, content:"<p>edit content here...</p>"};
        
        if(widget_id=='group'){
            new_ele = {name:'Group', type:'group', css:{}, children:[ new_ele ]};
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
            
            new_ele = {name:'Cardinal', type:'cardinal', css:{'min-height':'200px'}, children:[
            {name:'Center', type:'center', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'North', type:'north', options:{size:80}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'South', type:'south', options:{size:80}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'West', type:'west', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
            {name:'East', type:'east', children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
            ]};
          
        }else if(widget_id.indexOf('heurist_')===0){
            
            //btn_visible_newrecord, btn_entity_filter, search_button_label, search_input_label
            new_ele = {appid:widget_id, name:widget_name, css:{}, options:{}};
            
        }else if(widget_id=='group_2'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[
                    {name:'Column 1', type:'group', css:{flex:'1 1 auto'}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]},
                    {name:'Column 2', type:'group', css:{flex:'1 1 auto'}, children:[ window.hWin.HEURIST4.util.cloneJSON(new_ele) ]}
                ]
            };
            
        }else if(widget_id=='text_media'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[
                {name:'Column 1', type:'text', css:{flex:'0 1 auto'}, content:"<p><img src=\"https://heuristplus.sydney.edu.au/heurist/hclient/assets/v6/logo.png\" width=\"300\"</p>"},
                {name:'Column 2', type:'text', css:{flex:'1 1 auto'}, content:"<p>Text. Click to edit</p>"}
                ]
            };
            
        }else if(widget_id=='text_banner'){

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
            
            
        }else if(widget_id=='text_2'){
            
            new_ele = {name:'2 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            var child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Column. Click to edit</p>"};
            new_ele.children.push(child);
            
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            
        }else if(widget_id=='text_3'){
            
            new_ele = {name:'3 columns', type:'group', css:{display:'flex', 'justify-content':'center'},
                children:[]
            };
            
            var child = {name:'Column 1', type:'text', css:{flex:'1 1 auto'}, content:"<p>Column. Click to edit</p>"};
            new_ele.children.push(child);
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 2';
            new_ele.children.push(child);
            child = window.hWin.HEURIST4.util.cloneJSON(child);
            child.name = 'Column 3';
            new_ele.children.push(child);
        }
        
        
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
        
        /*
        if(parentnode.folder){
            //container - add to this container
            parent_children.push(new_ele);
            layoutMgr.layoutInitKey(parent_children, parent_children.length-1);
            parentnode.addNode(new_ele);
            
        }else{
            //text element or widget - add next to this element
            var idx = -1;
            for(var i=0; i<parent_children.length; i++){
              if(parent_children[i].key==ele_id){
                  idx = i;
                  break;
              }   
            }
            if(idx>=0){
                if(idx==0){
                    parent_children.push(new_ele);
                    idx = parent_children.length-1;
                }else{
                    parent_children.splice(idx,0,new_ele);    
                }
                layoutMgr.layoutInitKey(parent_children, idx);
                parentnode.addNode(new_ele, 'after');
            }
        }
        */
        

        parent_children.push(new_ele);
        layoutMgr.layoutInitKey(parent_children, parent_children.length-1);
        
        //recreate
        if(parent_element && parent_element.type=='accordion'){
            layoutMgr.layoutInitAccordion(parent_element, parent_container)
        }else if(parent_element && parent_element.type=='tabs'){
            layoutMgr.layoutInitTabs(parent_element, parent_container)
            //layoutMgr.layoutInit(_layout_content, _layout_container);    
        }else{
            layoutMgr.layoutInit(parent_children, parent_container);
        }   

        
        //update tree
        //var chld = parentnode.getChildren();
        //chld.push(new_ele);
        
        
        
        if(parentnode.folder){
            parentnode.addChildren(new_ele);    
            //parentnode.addNode(new_ele);
        }else{
            var beforenode = parentnode.getNextSibling();
            parentnode = parentnode.getParent();
            parentnode.addChildren(new_ele, beforenode);    
            //parentnode.addNode(new_ele, 'after');
        }
        
        parentnode.visit(function(node){
            node.setExpanded(true);
        });
        
        
        _updateActionIcons(200);

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
    function _saveLayoutCfg(){
        
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
         
        newval = JSON.stringify(newval);
        
        var request = {a: 'addreplace',
                        recIDs: options.record_id,
                        dtyID: DT_EXTENDED_DESCRIPTION,
                        rVal: newval,
                        needSplit: true};
        
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                if(response.status == hWin.ResponseStatus.OK){
                    if(response.data.errors==1){
                        var errs = response.data.errors_list;
                        var errMsg = errs[Object.keys(errs)[0]];
                        window.hWin.HEURIST4.msg.showMsgErr( errMsg );
                    }else
                    if(response.data.noaccess==1){
                        window.hWin.HEURIST4.msg.showMsgErr('It appears you do not have enough rights (logout/in to refresh) to edit this record');
                        
                    }else{
                        page_content[options.record_id] = newval; //update in cache
/*                        
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
*/                        
                    }
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                window.hWin.HEURIST4.msg.sendCoverallToBack();
        });         
    }
    
    //
    //
    //
    function _showWebSiteMenu(){
        
        _switchMode('website');
        
        //load website menu treeview
        var tree_element = _panel_treeWebSite;
        
        //get treedata from main menu
        var treedata = $('#main-menu > div[widgetid="heurist_Navigation"]').navigation('getMenuContent','treeview');

        if(tree_element.fancytree('instance')){
            
            var tree = tree_element.fancytree('getTree');

            //keep_expanded_nodes
            keep_expanded_nodes = [];
            tree.visit(function(node){
                    if(node.isExpanded()){
                        keep_expanded_nodes.push(node.key)
                    }});
            
            tree.reload(treedata);
            
            tree.visit(function(node){
                    if(keep_expanded_nodes.indexOf(node.key)>=0){
                        node.setExpanded(true);
                    }
                    node.setSelected((node.key==current_page_id));
            });

        }else{

            function __defineActionIcons(item)
            {
                var item_li = $(item.li), 
                menu_id = item.key, 

                is_top = (item.data.parent_id==home_page_record_id);

                if($(item).find('.svs-contextmenu3').length==0){

                    var parent_span = item_li.children('span.fancytree-node');

                    //add,edit menu,edit page,remove
                    var actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-parentid="'
                        +item.data.parent_id+'" data-menuid="'+menu_id+'">'
                        +'<span class="ui-icon ui-icon-structure" title="Edit page"></span>'
                        +'<span class="ui-icon ui-icon-plus" title="Add new page/menu item"></span>'
                        +'<span class="ui-icon ui-icon-pencil" title="Edit menu record"></span>'
                        //+'<span class="ui-icon ui-icon-document" title="Edit page record"></span>'
                        +'<span class="ui-icon ui-icon-trash" '
                        +'" title="Remove menu entry from website"></span>'
                        +'</div>').appendTo(parent_span);

                    $('<div class="svs-contextmenu4"/>').appendTo(parent_span); //progress icon

                    actionspan.find('.ui-icon').click(function(event){
                        var ele = $(event.target);
                        window.hWin.HEURIST4.util.stopEvent(event);
                        
                        var parent_span = ele.parents('span.fancytree-node');
                        
                        function __in_progress(){
                            parent_span.find('.svs-contextmenu4').show();
                            parent_span.find('.svs-contextmenu3').hide();
                        }

                        //timeout need to activate current node    
                        setTimeout(function(){                         
                            var ele2 = ele.parents('.svs-contextmenu3');
                            var menuid = ele2.attr('data-menuid');
                            var parent_id = ele2.attr('data-parentid');

                            if(ele.hasClass('ui-icon-plus')){ //add new menu to 

                                _selectMenuRecord(menuid, function(page_id){
                                    //was_something_edited = true;
                                    current_page_id = page_id;
                                    _refreshMainMenu();
                                });

                            }else if(ele.hasClass('ui-icon-pencil')){ //edit menu record

                                __in_progress();
                                //edit menu item
                                window.hWin.HEURIST4.ui.openRecordEdit(menuid, null,
                                    {selectOnSave:true,
                                        onClose: function(){ 
                                            parent_span.find('.svs-contextmenu4').hide();
                                        },
                                        onselect:function(event, data){
                                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                                
                                                var recordset = data.selection;
                                                var page_id = recordset.getOrder()[0];
                                                page_content[page_id] = null; //remove from cache
                                                
                                                if(page_id == current_page_id){
                                                    _refreshCurrentPage(current_page_id);
                                                }
                                            }
                                }});

                            }else if(ele.hasClass('ui-icon-structure')){
                                //open page structure 
                                if( menuid == current_page_id ){
                                    _switchMode('page');
                                        
                                }else{
                                    current_edit_mode = 'page'
                                    _refreshCurrentPage( menu_id );
                                }

                            }else 
                                if(ele.hasClass('ui-icon-trash')){    //remove menu entry

                                    var menu_title = ele.parents('.fancytree-node').find('.fancytree-title')[0].innerText; // Get menu title

                                    function __doRemove(){
                                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                        var isDelete = $dlg.find('#del_menu').is(':checked');
                                        $dlg.dialog( "close" );

                                        var to_del = [];
                                        if(remove_menu_records){
                                            item.visit(function(node){
                                                to_del.push(node.key);
                                                },true);
                                        }

                                        if(!isDelete){ // Check if the menu and related records are to be deleted, or just removed
                                            to_del = null;
                                        }
                                        
                                        _removeMenuEntry(parent_id, menuid, to_del, function(){
                                            item.remove();    
                                            
                                            _refreshMainMenu();
                                        });
                                    }

                                    var buttons = {};
                                    buttons[window.hWin.HR('Remove menu entry and sub-menus (if any)')]  = function() {
                                        remove_menu_records = true;
                                        __doRemove();
                                    };
                                    /*        
                                    buttons[window.hWin.HR('No. Remove menu only and retain records')]  = function() {
                                    remove_menu_records = false;
                                    __doRemove();
                                    };
                                    */
                                    buttons[window.hWin.HR('Cancel')]  = function() {
                                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                        $dlg.dialog( "close" );
                                    };

                                    window.hWin.HEURIST4.msg.showMsgDlg(
                                        'This removes the menu entry from the website, as well as all sub-menus of this menu (if any).<br><br>'
                                        + 'To avoid removing sub-menus, move them out of this menu before removing it.<br><br>'
                                        + '<input type="checkbox" id="del_menu">'
                                        + '<label for="del_menu" style="display: inline-flex;">If you want to delete the actual web pages from the database, not simply remove<br>'
                                        + 'the menu entreis from this website, check this box. Note that this is not reversible.</label>', buttons,
                                        'Remove "'+ menu_title +'" Menu');

                                }

                            },500);
                    });

                    //hide icons on mouse exit
                    function _onmouseexit(event){
                        var node;
                        if($(event.target).is('li')){
                            node = $(event.target).find('.fancytree-node');
                        }else if($(event.target).hasClass('fancytree-node')){
                            node =  $(event.target);
                        }else{
                            //hide icon for parent 
                            node = $(event.target).parents('.fancytree-node');
                            if(node) node = $(node[0]);
                        }
                        var ele = node.find('.svs-contextmenu3');
                        ele.hide();
                    }               

                    $(parent_span).hover(
                        function(event){
                            var node;
                            if($(event.target).hasClass('fancytree-node')){
                                node =  $(event.target);
                            }else{
                                node = $(event.target).parents('.fancytree-node');
                            }
                            if(! ($(node).hasClass('fancytree-loading') || $(node).find('.svs-contextmenu4').is(':visible')) ){
                                var ele = $(node).find('.svs-contextmenu3');
                                ele.css({'display':'inline-block'});//.css('visibility','visible');
                            }
                        }
                    );               
                    $(parent_span).mouseleave(
                        _onmouseexit
                    );
                }
            } //end __defineActionIcons

            var fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: treedata,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                renderNode: function(event, data) {
                    
                    if(true || data.has_access){
                        var item = data.node;
                        __defineActionIcons( item );
                    }
                },
                extensions:["edit", "dnd"],
                dnd:{
                    preventVoidMoves: true,
                    preventRecursiveMoves: true,
                    autoExpandMS: 400,
                    dragStart: function(node, data) {
                        return data.has_access;
                    },
                    dragEnter: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        return true; //node.folder ?['over'] :["before", "after"];
                    },
                    dragDrop: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        var source_parent = data.otherNode.parent.key;//data.otherNode.data.parent_id;
                        if(!(source_parent>0))
                            source_parent = home_page_record_id;

                        data.otherNode.moveTo(node, data.hitMode);

                        var target_parent = data.otherNode.parent.key;
                        if(!(target_parent>0))
                            target_parent = home_page_record_id;
                        data.otherNode.data.parent_id = target_parent;

                        var request = {actions:[]};

                        if(source_parent!=target_parent){
                            //remove from source
                            request.actions.push(
                                {a: 'delete',
                                    recIDs: source_parent,
                                    dtyID: source_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                    sVal:data.otherNode.key});

                        }
                        //return;
                        //change order in target
                        request.actions.push(
                            {a: 'delete',
                                recIDs: target_parent,
                                dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU});

                        for (var i=0; i<data.otherNode.parent.children.length; i++){

                            var menu_node = data.otherNode.parent.children[i];
                            request.actions.push(
                                {a: 'add',
                                    recIDs: target_parent,
                                    dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                    val:menu_node.key}                                                   
                            );
                        }                    

                        //window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.parents('.ui-dialog')); 
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                            //window.hWin.HEURIST4.msg.sendCoverallToBack();
                            if(response.status == hWin.ResponseStatus.OK){
                                was_something_edited = true;
                                window.hWin.HEURIST4.msg.showMsgFlash('saved');
                                //reload main menu
                                _refreshMainMenu();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });                                        

                    }
                },
                activate: function(event, data) { 
                    //loads another page
                    if(data.node.key>0){
                          _refreshCurrentPage(data.node.key);
                    }
                },
                edit:{
                    triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"],
                    close: function(event, data){
                        // Editor was removed
                        if( data.save ) {
                            // Since we started an async request, mark the node as preliminary
                            $(data.node.span).addClass("pending");
                        }
                    },                                    
                    save:function(event, data){
                        if(''!=data.input.val()){
                            var new_name = data.input.val();
                            _renameEntry(data.node.key, new_name, function(){
                                $(data.node.span).removeClass("pending");
                                data.node.setTitle( new_name ); 
                                __defineActionIcons( data.node );   
                            });
                        }else{
                            $(data.node.span).removeClass("pending");    
                        }
                    }
                }
            };

            tree_element.fancytree(fancytree_options).addClass('tree-cms');
            
            tree = tree_element.fancytree('getTree');
            tree.visit(function(node){
                node.setExpanded(true);
            });            
            
        }        
        
        setTimeout(_highlightCurrentPage, 1000);
    }  
    
    function _highlightCurrentPage(){
        
         if( _panel_treeWebSite.fancytree('instance')){
                var tree = _panel_treeWebSite.fancytree('getTree');
                
                tree.visit(function(node){
                    if(node.key==current_page_id){
                        $(node.li).find('.fancytree-title').css({'text-decoration':'underline'});    
                    }else{
                        $(node.li).find('.fancytree-title').css({'text-decoration':'none'});
                    }
                });            
         }
        

            /*
            var node = tree.getNodeByKey(''+current_page_id);
            if(node){
console.log('!!! '+current_page_id);                
               node.setSelected(true); 
               $(node.li).css({'color':'red'});
            }
            */        
    }

    //
    // Select or create new menu item for website
    //
    // Opens record selector popup and adds selected menu given mapdoc or other menu
    //
    function _selectMenuRecord(parent_id, callback){

        var popup_options = {
            select_mode: 'select_single', //select_multi
            select_return_mode: 'recordset',
            edit_mode: 'popup',
            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
            title: window.hWin.HR('Select or create a website menu record'),
            rectype_set: RT_CMS_MENU,
            parententity: 0,
            default_palette_class: 'ui-heurist-publish',

            onselect:function(event, data){
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    var recordset = data.selection;
                    var record = recordset.getFirstRecord();
                    var menu_id = recordset.fld(record,'rec_ID');

                    _addMenuEntry(parent_id, menu_id, callback)
                }
            }
        };//popup_options


        var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
            {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        popup_options.width = Math.max(usrPreferences.width,710);
        popup_options.height = usrPreferences.height;

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }

    //
    // Add new menu(page) menu_id to  parent_id
    //
    function _addMenuEntry(parent_id, menu_id, callback){

        var request = {a: 'add',
            recIDs: parent_id,
            dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            val:    menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == hWin.ResponseStatus.OK){
                //refresh treeview
                if($.isFunction(callback)) callback.call( this, menu_id );
            }else{
                hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }

    //
    //
    //
    function _removeMenuEntry(parent_id, menu_id, records_to_del, callback){

        //delete detail from parent menu
        var request = {a: 'delete',
            recIDs: parent_id,
            dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            sVal:   menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == hWin.ResponseStatus.OK){
                if(records_to_del && records_to_del.length>0){

                    //delete children 
                    window.hWin.HAPI4.RecordMgr.remove({ids:records_to_del},
                        function(response){
                            if(response.status == hWin.ResponseStatus.OK){
                                //refresh treeview
                                if($.isFunction(callback)) callback.call();
                            }else{
                                hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }      
                    );

                }else{
                    //refresh treeview
                    if($.isFunction(callback)) callback.call();
                }
            }else{                                                     
                hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }

    //
    // refresh main menu and reload current page
    //
    function _refreshMainMenu(){
        
       initMainMenu( _refreshCurrentPage );
    }

    
    //
    // reload current (or given page)
    //
    function _refreshCurrentPage(page_id){
        
        if(!(page_id>0)) page_id = current_page_id;
        
        loadPageContent(page_id); //call global function from websiteScriptAndStyles
        
    }

    //
    // reload entire website 
    //
    function _refreshWebsite(){
        
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
            _startCMS( _options );    
        },
        
        closeCMS: function(){
            _closeCMS();            
        }
    }
    
    return that;
}
   
