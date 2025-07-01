/**
 * @file editCMS_SiteMenu.js
 * @brief Manages the website structure and navigation menu configuration within the CMS editor.
 * @fileOverview This file defines the 'editCMS_SiteMenu' function, which is responsible for
 *               rendering and managing the website's navigation menu tree in the CMS editor. It allows
 *               users to view the site structure, add new menu items (pages), edit existing ones (rename, change record properties),
 *               reorder menu items via drag-and-drop, and delete menu entries. It interacts with the main
 *               CMS editor (editCMS2) for page loading and refreshing the main menu display.
 * @project     Heurist academic knowledge management system
 * @package hclient\widgets\cms
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

/**
 * Initializes and manages the website structure/menu editor within the provided container.
 * It uses Fancytree to display and interact with the website's menu hierarchy.
 *
 * @param {jQuery} $container - The jQuery object representing the container element where the site menu tree will be rendered.
 * @param {Object} editCMS2 - An instance of the main CMS editor (editCMS2.js), used for callbacks and accessing shared data.
 * @returns {Object} An object with public methods for controlling the site menu editor.
 *
 * @property {string} _className - Internal class name identifier.
 * @property {number} RT_CMS_MENU - Record Type ID for CMS Menu.
 * @property {number} DT_NAME - Detail Type ID for Name.
 * @property {number} DT_EXTENDED_DESCRIPTION - Detail Type ID for Extended Description.
 * @property {number} DT_CMS_TOP_MENU - Detail Type ID for CMS Top Menu (links from CMS Home to menu items).
 * @property {number} DT_CMS_MENU - Detail Type ID for CMS Menu (links from menu items to sub-menu items).
 * @property {number} DT_CMS_PAGETITLE - Detail Type ID for CMS Page Title visibility.
 * @property {number} DT_CMS_PAGETYPE - Detail Type ID for CMS Page Type.
 * @property {boolean} isVersion3 - Flag to indicate if it's operating in a Heurist v3 like environment (based on editCMS2 properties).
 * @property {number} home_page_record_id - The record ID of the website's home page.
 */
function editCMS_SiteMenu( $container, editCMS2 ){

    const _className = 'editCMS_SiteMenu';
    
    const RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
        DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
        DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
        DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
        DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
        DT_CMS_PAGETITLE   = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],
        DT_CMS_PAGETYPE   = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
        
    let isVersion3 = false;
    let home_page_record_id;

    /**
     * Initializes the site menu editor by calling _initControls.
     * @private
     */
    function _init(){

        /*not used as dialog 
        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                class:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Apply'), 
                class:'ui-button-action btnDoAction',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        var config = _getValues();
                        main_callback.call(this, config);
                        $dlg.dialog( "close" );    
        }}];
        */
        
        /*
        $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/cms/editCMS_ElementCfg.html',
                _initControls
            );
        */
        
        _initControls();
    }
    
    /**
     * Gets the current page ID from the main editCMS2 instance or the global window object.
     * @private
     * @returns {number|undefined} The ID of the current page being edited.
     */
    function _currentPageId(){
        if(isVersion3){
            return editCMS2.page_id;
        }else{
            return window.hWin.current_page_id;            
        }
    }
    
    /**
     * Initializes or reloads the Fancytree instance for the website menu.
     * Sets up tree options, including drag-and-drop for reordering, and node rendering.
     * @private
     */
    function _initControls(){

        let tree_element = $container;        
        
        //get treedata from main menu
        let treedata;
        if(window.hWin.HEURIST4.util.isJSON(editCMS2.menuContentJSON)){
            isVersion3 = true;
            treedata = editCMS2.menuContentJSON;
            home_page_record_id = editCMS2.website_id;

            //stub
            if(!window.hWin.page_cache) window.hWin.page_cache = {};

        }else{
            home_page_record_id = window.hWin.home_page_record_id
            treedata = $('#main-menu > div[widgetid="heurist_Navigation"]').navigation('getMenuContent','treeview');
        }
        
        //add node for home page
/*
0:
children: (3) [{…}, {…}, {…}]
expanded: false
has_access: true
key: "3"
page_id: "3"
page_showtitle: 1
page_target: null
parent_id: 7
title: "Overview"
*/
        if(tree_element.fancytree('instance')){
            
            let tree = $.ui.fancytree.getTree( tree_element );

            //keep_expanded_nodes
            let keep_expanded_nodes = [];
            tree.visit(function(node){
                    if(node.isExpanded()){
                        keep_expanded_nodes.push(node.key)
                    }});
            
            tree.reload(treedata);
            
            tree.visit(function(node){
                    if(keep_expanded_nodes.indexOf(node.key)>=0){
                        node.setExpanded(true);
                    }
                    node.setSelected((node.data.page_id==_currentPageId()));
            });

        }else{

            let fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: treedata,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                renderNode: function(event, data) {
                    
                        let item = data.node;
                        _defineActionIcons( item );
                    
                },
                extensions:["edit", "dnd"],
                dnd:{
                    preventVoidMoves: true,
                    preventRecursiveMoves: true,
                    autoExpandMS: 400,
                    dragStart: function(node, data) {
                        return true; //data.has_access;
                    },
                    dragEnter: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        return true;
                    },
                    dragDrop: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        let source_parent = data.otherNode.parent.data.page_id;
                        if(!(source_parent>0))
                            source_parent = home_page_record_id;

                        data.otherNode.moveTo(node, data.hitMode);

                        let target_parent = data.otherNode.parent.data.page_id;
                        if(!(target_parent>0))
                            target_parent = home_page_record_id;
                        data.otherNode.data.parent_id = target_parent;

                        let request = {actions:[]};
                        if(source_parent!=target_parent){
                            //remove from source
                            request.actions.push(
                                {a: 'delete',
                                    recIDs: source_parent,
                                    dtyID: source_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                    sVal:data.otherNode.data.page_id}); 

                        }
                       
                        //change order in target
                        
                        //at first - remove all current children
                        request.actions.push(
                            {a: 'delete',
                                recIDs: target_parent,
                                dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU});

                        //add children in new order        
                        for (let i=0; i<data.otherNode.parent.children.length; i++){

                            let menu_node = data.otherNode.parent.children[i];
                            request.actions.push(
                                {a: 'add',
                                    recIDs: target_parent,
                                    dtyID: target_parent==home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                    val:menu_node.data.page_id}                                                   
                            );
                        }                    

                        //window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.parents('.ui-dialog')); 
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                            
                            if(response.status == window.hWin.ResponseStatus.OK){
                                window.hWin.HEURIST4.msg.showMsgFlash('saved');
                                //reload main menu
                                _refreshMainMenu( false ); //after DnD
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });             

                    }
                },
                activate: function(event, data) { 
                    //loads another page
                    let page_id = data.node.data.page_id;

                    if(page_id>0){
                        _refreshCurrentPage(page_id);
                        data.node.setActive( false );

                        editCMS2.switchMode('page');

                        if(!isVersion3){
                            $('#main-menu > div[widgetid="heurist_Navigation"]').navigation('highlightTopItem', data.node.key);
                        }
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
                            let new_name = data.input.val();
                            _renameMenuEntry(data.node.data.page_id, new_name, function(){
                                
                            });
                        }else{
                            $(data.node.span).removeClass("pending");    
                        }
                    }
                }
            };

            tree_element.fancytree(fancytree_options).addClass('tree-cms');
            
            let tree = $.ui.fancytree.getTree( tree_element );
            tree.visit(function(node){
                node.setExpanded(true);
            });            
            
        }        
        
        setTimeout(_highlightCurrentPage, 1000);
    }  
   
   
    /**
     * Defines and attaches action icons (add, edit, delete) to a menu item in the Fancytree.
     * @private
     * @param {Fancytree.FancytreeNode} item - The Fancytree node representing a menu item.
     */
    function _defineActionIcons(item)
    {
        
        let tree_element = $container;
        
        let item_li = $(item.li), 
        menu_id = item.data.page_id,

        is_top = (item.data.parent_id==home_page_record_id);

        if($(item).find('.svs-contextmenu3').length==0){

            let parent_span = item_li.children('span.fancytree-node');

            //add,edit menu,edit page,remove
            let actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-parentid="'
                +item.data.parent_id+'" data-menuid="'+menu_id+'">'
                //since 12-05 +'<span class="ui-icon ui-icon-structure" title="Edit page"></span>'
                +'<span class="ui-icon ui-icon-plus" title="Add new page/menu item"></span>'
                +'<span class="ui-icon ui-icon-pencil" title="Edit menu record"></span>'
                //+'<span class="ui-icon ui-icon-document" title="Edit page record"></span>'
                +'<span class="ui-icon ui-icon-trash" '
                +'" title="Remove menu entry from website"></span>'
                +'</div>').appendTo(parent_span);

            $('<div class="svs-contextmenu4"></div>').appendTo(parent_span); //progress icon

            actionspan.find('.ui-icon').on('click', function(event){
                let ele = $(event.target);
                window.hWin.HEURIST4.util.stopEvent(event);
                
                let parent_span = ele.parents('span.fancytree-node');
                
                function __in_progress(){
                    parent_span.find('.svs-contextmenu4').show();
                    parent_span.find('.svs-contextmenu3').hide();
                }

                //timeout need to activate current node    
                setTimeout(function(){                         
                    let ele2 = ele.parents('.svs-contextmenu3');
                    let menuid = ele2.attr('data-menuid');
                    let parent_id = ele2.attr('data-parentid');

                    if(ele.hasClass('ui-icon-plus')){ //add new menu to 

                        _selectMenuRecord(menuid);

                    }else if(ele.hasClass('ui-icon-pencil')){ //edit menu record

                        function __editPageRecord(record_id){
                            __in_progress();
                            //edit menu item
                            window.hWin.HEURIST4.ui.openRecordEdit(record_id, null,
                                {selectOnSave:true,
                                    onClose: function(){ 
                                        parent_span.find('.svs-contextmenu4').hide();
                                    },
                                    onselect:function(event, data){
                                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                            
                                            let recordset = data.selection;
                                            let page_id = recordset.getOrder()[0];
                                            window.hWin.page_cache[page_id] = null; //remove from cache
                                            delete window.hWin.page_cache[page_id];
                                            
                                            if(page_id == _currentPageId()){
                                                _refreshCurrentPage(page_id);
                                            }

                                            // Update website tree and in site menu
                                            let new_name = recordset.fld(recordset.getFirstRecord(), DT_NAME);
                                            let refresh_menus = false;

                                            if(window.hWin.page_cache[page_id]) window.hWin.page_cache[page_id][DT_NAME] = new_name;

                                            // Update tree nodes
                                            $.ui.fancytree.getTree( $container ).visit((node) => {

                                                let old_name = node.title;
                                                if(node.data.page_id == page_id){

                                                    if(old_name == new_name){ return false; } // name wasn't updated

                                                    node.setTitle( new_name );
                                                    _defineActionIcons(node);
                                                    refresh_menus = true;
                                                }

                                            });

                                            if(refresh_menus){ _refreshMainMenu(false); } // update menu
                                        }
                                    }
                                }
                            );
                        }
                        
                        if( (menuid == _currentPageId())
                            && editCMS2.warningOnExit(function(){ __editPageRecord(menuid) }))
                        {                                    
                                return;
                        }else{
                                __editPageRecord(menuid);
                        }

                    }
                    else if(ele.hasClass('ui-icon-structure')){ //not used - now any click on tree opens edit page

                        editCMS2.switchMode('page');
                        //open page structure 
                        if( menuid != _currentPageId() ){
                            _refreshCurrentPage( menuid );
                        }

                    }else 
                        if(ele.hasClass('ui-icon-trash')){    //remove menu entry

                            function __doRemove(){
                                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                let isDelete = $dlg.find('#del_menu').is(':checked');
                                $dlg.dialog( "close" );

                                let to_del = [];
                                item.visit(function(node){
                                    to_del.push(node.data.page_id);
                                },true);

                                if(!isDelete){ // Check if the menu and related records are to be deleted, or just removed
                                    to_del = null;
                                }
                                
                                if(!isVersion3){
                                    editCMS2.resetModified();
                                }
                                
                                _removeMenuEntry(parent_id, menuid, to_del, function(){
                                    item.remove();    
                                    
                                    //after deletion select home page
                                    _refreshMainMenu( false, home_page_record_id); //after delete
                                });
                            }

                            let menu_title = ele.parents('.fancytree-node').find('.fancytree-title')[0].innerText; // Get menu title
                            
                            let buttons = {};
                            buttons[window.hWin.HR('Remove menu entry and sub-menus (if any)')]  = function() {
                                __doRemove();
                            };
                            buttons[window.hWin.HR('Cancel')]  = function() {
                                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
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
                let node;
                if($(event.target).is('li')){
                    node = $(event.target).find('.fancytree-node');
                }else if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    //hide icon for parent 
                    node = $(event.target).parents('.fancytree-node');
                    if(node) node = $(node[0]);
                }
                let ele = node.find('.svs-contextmenu3');
                ele.hide();
            }               

            function _onmouseenter(event){
                let node;
                if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    node = $(event.target).parents('.fancytree-node');
                }
                if(! ($(node).hasClass('fancytree-loading') || $(node).find('.svs-contextmenu4').is(':visible')) ){
                    let ele = $(node).find('.svs-contextmenu3');
                    ele.css({'display':'inline-block'});
                }
            }

            $(parent_span).on('mouseenter',
                _onmouseenter
            ).on('mouseleave',
                _onmouseexit
            );                                                  
            
        }
    } //end _defineActionIcons

    
    /**
     * Renames a menu entry (CMS Menu record) by updating its DT_NAME detail.
     * Refreshes the tree and main menu upon success.
     * @private
     * @param {number} rec_id - The record ID of the menu item to rename.
     * @param {string} newvalue - The new name for the menu item.
     * @param {function} [callback] - Optional callback function to execute after renaming.
     */
    function _renameMenuEntry(rec_id, newvalue, callback){

        let request = {a: 'replace',
            recIDs: rec_id,
            dtyID:  DT_NAME,
            rVal:    newvalue};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                //refresh treeview
                if($container.fancytree('instance')){                                 
                    let node = $.ui.fancytree.getTree( $container ).getNodeByKey(''+rec_id);
                    if(node){
                        $(node.span).removeClass("pending");
                        node.setTitle( newvalue ); 
                        _defineActionIcons( node );   
                    }
                }                                
                if(window.hWin.page_cache[rec_id]) window.hWin.page_cache[rec_id][DT_NAME] = newvalue;
                _refreshMainMenu( false ); //after Rename   
                
                
                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }
    
    /**
     * Highlights the currently active page in the site menu tree.
     * @private
     */
    function _highlightCurrentPage(){
        
        if(_currentPageId()==home_page_record_id){
            $('.btn-website-homepage').css({'text-decoration':'underline'});
        }else
        if( $container.fancytree('instance')){
                let tree = $.ui.fancytree.getTree( $container );
                
                $('.btn-website-homepage').css({'text-decoration':'none'});
                
                tree.visit(function(node){
                    if(node.data.page_id==_currentPageId()){
                        $(node.li).find('.fancytree-title').css({'text-decoration':'underline'});    
                    }else{
                        $(node.li).find('.fancytree-title').css({'text-decoration':'none'});
                    }
                });            
        }
    }

    /**
     * Creates a new CMS Menu record, typically based on a template, and then adds it to the menu structure.
     * @private
     * @param {number} parent_id - The record ID of the parent menu item (or home page ID for top-level items).
     * @param {string} pageName - The name for the new page/menu item.
     * @param {string} templateName - The name of the template to use for the page content (e.g., 'default', 'blog').
     * @param {function(number):void} [callback] - Optional callback function, receives the new page ID. Defaults to refreshing the main menu.
     * @param {jQuery} [$dlg_element] - Optional jQuery dialog element to close after creation.
     */
    function _createMenuRecord(parent_id, pageName, templateName, callback, $dlg_element){
        
        if($dlg_element && $dlg_element.dialog('instance') !== undefined){
            $dlg_element.dialog( "close" );
        }
                
        let details = {};
        details['t:'+DT_NAME] = [ pageName ];
        details['t:'+DT_CMS_PAGETYPE] = [ window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_MENUITEM'] ];
        if(DT_CMS_PAGETITLE>0 && window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO']){
            details['t:'+DT_CMS_PAGETITLE] = [ window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'] ];
        }

        //if callback is not defined, it is _refreshMainMenu
        if(!window.hWin.HEURIST4.util.isFunction(callback)){
                callback = function(new_page_id){
                    _refreshMainMenu(true, new_page_id); //after addition of new page
                };
        }

        function ___continue_addition(pageContent){
            
            if(typeof pageContent !== 'string'){
                pageContent = JSON.stringify(pageContent);
            }
            
            let details = {};
            details['t:'+DT_NAME] = [ pageName ];
            details['t:'+DT_CMS_PAGETYPE] = [ window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_MENUITEM'] ];
            if(DT_CMS_PAGETITLE>0 && window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO']){
                details['t:'+DT_CMS_PAGETITLE] = [ window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'] ];
            }                
            details['t:'+DT_EXTENDED_DESCRIPTION] = [ pageContent ];
            //add new record
            let request = {a: 'save', 
                ID:0, //new record
                RecTypeID: RT_CMS_MENU,
                details: details };     

            window.hWin.HAPI4.RecordMgr.saveRecord(request, 
                function(response){
                    let  success = (response.status == window.hWin.ResponseStatus.OK);
                    if(success){
                        let newMenuId = response.data;
                        if(newMenuId > 0){
                            _addMenuEntry(parent_id, newMenuId, callback)
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        } //___continue_addition        
        
        if(templateName=='landing' || templateName=='about'){ //special page templates for  v3
        
            let request = {website:home_page_record_id, raw:1, ver:3, webtemplate:templateName};
            
            window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
            
                if(response?.message){
                    ___continue_addition( response?.message );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: `Template ${templateName} not found`,
                        error_title: 'Failed to load template'
                    });
                }
            });
        }else{

            let sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+templateName+'.json';
            $.getJSON(sURL, (templateJSON)=>{
                
                    if(templateName=='blog'){
                        window.hWin.HAPI4.layoutMgr.prepareTemplate(templateJSON, ___continue_addition);
                    }else{
                        ___continue_addition( templateJSON );
                    }
                  
            });
            
        }

    }

    /**
     * Opens a simple dialog to get a name and select a content template for a new page.
     * Calls _createMenuRecord upon submission.
     * @private
     * @param {number} parent_id - The parent menu item ID.
     * @param {function(number):void} callback - Callback for _createMenuRecord.
     */
    function _defineMenuRecordSimple(parent_id, callback){
        
        let $dlg;
        
        let buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Select'), 
                id:'btnSelect',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
                    _defineMenuRecord(parent_id, callback);
            }},
            {text:window.hWin.HR('Insert'), 
                id:'btnDoAction',
                class:'ui-button-action',
                disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                    _createMenuRecord(parent_id, $dlg.find('#pageName').val(), $dlg.find('#pageContent').val(), callback, $('#cms-add-widget-popup'));
                }
            }
        ];
    
        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
            +"hclient/widgets/cms/editCMS_AddPage.html?t="+(new Date().getTime()), 
            buttons, window.hWin.HR('Define new web page'), 
            {  container:'cms-add-widget-popup',
                default_palette_class: 'ui-heurist-publish',
                width: 600,
                height: 250
                , close: function(){
                    $dlg.dialog('destroy');       
                    $dlg.remove();
                }
                , open: function(){
                    $dlg.find('#pageName').on('keyup', function(e){
                        window.hWin.HEURIST4.util.setDisabled($dlg.parent().find('#btnDoAction'), $(e.taget).val()=='');
                    } );
                }
        });
        
    }
    
    /**
     * Opens a record selector dialog to allow the user to select an existing CMS Menu record
     * or create a new one to be added to the site menu.
     * @private
     * @param {number} parent_id - The record ID of the parent menu item.
     * @param {function(number):void} callback - Callback function passed to _addMenuEntry, receiving the new page ID.
     */
    function _defineMenuRecord(parent_id, callback)
    {
        let popup_options = {
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
                    let recordset = data.selection;
                    let record = recordset.getFirstRecord();
                    let menu_id = recordset.fld(record,'rec_ID');

                    _addMenuEntry(parent_id, menu_id, callback)
                }
            }
        };//popup_options


        let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
            {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        popup_options.width = Math.max(usrPreferences.width,710);
        popup_options.height = usrPreferences.height;

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }

    
    
    /**
     * Initiates the process of adding a new menu item. It first checks for unsaved changes
     * in the main editor and then calls _defineMenuRecordSimple to show the selection/creation dialog.
     * @private
     * @param {number} parent_id - The record ID of the parent menu item under which the new item will be added.
     * @param {function(number):void} [callback] - Optional callback function, ultimately for _addMenuEntry.
     */
    function _selectMenuRecord(parent_id, callback){
        
        if(editCMS2.warningOnExit(function(){ _selectMenuRecord(parent_id, callback) })) return;
        
        if(!callback){
                callback = function(new_page_id){
                        _refreshMainMenu(true, new_page_id); //after addition of new page
                };
        }

        _defineMenuRecordSimple(parent_id, callback);
        
    }
        
    /**
     * Adds an existing CMS Menu record (`menu_id`) as a child to another menu item (`parent_id`)
     * or as a top-level item if `parent_id` is the home page ID.
     * Updates the database by adding a detail to the parent record.
     * @private
     * @param {number} parent_id - The record ID of the parent menu item or home page.
     * @param {number} menu_id - The record ID of the menu item to add.
     * @param {function(number):void} [callback] - Optional callback, receives `menu_id`.
     */
    function _addMenuEntry(parent_id, menu_id, callback){

        let request = {a: 'add',
            recIDs: parent_id,
            dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            val:    menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                //refresh treeview
                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call( this, menu_id );
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }

    /**
     * Removes a menu entry from its parent. Optionally deletes the menu record and its children.
     * @private
     * @param {number} parent_id - The record ID of the parent menu item.
     * @param {number} menu_id - The record ID of the menu item to remove.
     * @param {Array<number>|null} records_to_del - An array of record IDs to delete (menu item and its descendants). If null, only removes from menu.
     * @param {function} [callback] - Optional callback function after removal.
     */
    function _removeMenuEntry(parent_id, menu_id, records_to_del, callback){

        //delete detail from parent menu
        let request = {a: 'delete',
            recIDs: parent_id,
            dtyID:  (parent_id==home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            sVal:   menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                if(records_to_del && records_to_del.length>0){

                    //delete children 
                    window.hWin.HAPI4.RecordMgr.remove({ids:records_to_del},
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //refresh treeview
                                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }      
                    );

                }else{
                    //refresh treeview
                    if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                }
            }else{                                                     
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }
    
    /**
     * Refreshes the main navigation menu display in the CMS.
     * For Heurist v3, it calls `editCMS2.loadWebSite`. Otherwise, it calls the global `window.hWin.initMainMenu`.
     * @private
     * @param {boolean} [need_refresh_tree=false] - If true, also re-initializes the site menu tree control.
     * @param {number} [new_page_id] - Optional page ID to focus or load after refreshing.
     */
    function _refreshMainMenu( need_refresh_tree, new_page_id ){
        
        if(isVersion3){
            editCMS2.loadWebSite(new_page_id); //reload entire website
            /*
            if(new_page_id>0){
            }else{
                //reload header only
            }
            */
        }else{
            //call global function from websiteScriptAndStyles
            window.hWin.initMainMenu( function(){
                if(need_refresh_tree!==false){
                    _initControls();
                }
                _refreshCurrentPage(new_page_id);
            });  
        }
    }

    
    /**
     * Reloads the content for the current page or a specified page ID in the main CMS editor.
     * @private
     * @param {number} [page_id] - The ID of the page to refresh. Defaults to the current page.
     */
    function _refreshCurrentPage(page_id){

        if(!window.hWin.HEURIST4.util.isPositiveInt(page_id)) page_id = _currentPageId();
        
        if(isVersion3){
            editCMS2.loadPageContent(page_id);
        }else{
            //call global function from websiteScriptAndStyles
            window.hWin.loadPageContent(page_id); 
        }
        
    
    }

    /**
     * Reloads the entire website editor interface.
     * For Heurist v3, calls `editCMS2.loadWebSite`. Otherwise, reloads the window.
     * @private
     */
    function _refreshWebsite(){
        if(isVersion3){
            editCMS2.loadWebSite();
        }else{
            window.hWin.location.reload();
        }
    }

    /**
     * Gets the parent page ID for a given page ID from the Fancytree data.
     * @private
     * @param {number|string} page_id - The ID of the page whose parent is sought.
     * @returns {number|string} The parent page ID, or the home page ID if it's a top-level item or not found.
     */
    function _getParentPage(page_id){

        if(window.hWin.HEURIST4.util.isempty(page_id) || page_id <= 0 || home_page_record_id == page_id){
            return page_id;
        }

        let tree = $.ui.fancytree.getTree( $container );
        let page_node = tree.getNodeByKey(''+page_id);
        let parent_id = home_page_record_id;

        if(page_node == null){
            tree.visit((node) => {
                if(node.data.page_id == page_id){
                    parent_id = node.data.parent_id; //node.parent.data.page_id
                    return false;
                }
            });
        }else{
            parent_id = page_node.data.parent_id; //page_node.parent.data.page_id
        }

        return parent_id;
    }
        

    //public members
    let that = {

        /**
         * Gets the class name of this editor instance.
         * @returns {string} The class name.
         * @public
         */
        getClass: function () {
            return _className;
        },

        /**
         * Checks if the instance is of a given class name.
         * @param {string} strClass - The class name to check against.
         * @returns {boolean} True if it is an instance of the class, false otherwise.
         * @public
         */
        isA: function (strClass) {
            return (strClass === _className);
        },
        
        /**
         * Public method to highlight the current page in the menu tree.
         * @public
         */
        highlightCurrentPage: function(){
            _highlightCurrentPage();
        },
        
        /**
         * Public method to initiate adding a new menu record.
         * @param {number} parent_id - The parent menu item ID.
         * @param {function(number):void} [callback] - Optional callback.
         * @public
         */
        selectMenuRecord: function(parent_id, callback){
            _selectMenuRecord(parent_id, callback);
        },
        
        /**
         * Public method to rename a menu entry.
         * @param {number} rec_id - Record ID of the menu item.
         * @param {string} newvalue - New name for the menu item.
         * @param {function} [callback] - Optional callback.
         * @public
         */
        renameMenuEntry: function (rec_id, newvalue, callback){
            _renameMenuEntry(rec_id, newvalue, callback);
        },
        
        /**
         * Public method to refresh the entire website editor.
         * @public
         */
        refreshWebsite: function(){
            _refreshWebsite();
        },

        /**
         * Public method to initialize/re-initialize the menu tree controls.
         * @public
         */
        initControls: function(){
            _initControls();
        },

        /**
         * Public method to create a new menu record.
         * @param {number} parent_id - Parent menu item ID.
         * @param {string} page_name - Name for the new page.
         * @param {string} template_name - Template to use for the page content.
         * @param {function(number):void} [callback] - Optional callback.
         * @public
         */
        createMenuRecord: function(parent_id, page_name, template_name, callback){
            _createMenuRecord(parent_id, page_name, template_name, callback);
        },

        /**
         * Public method to get the parent page ID.
         * @param {number|string} page_id - The ID of the page.
         * @returns {number|string} The parent page ID.
         * @public
         */
        getParentPage: function(page_id){
            return _getParentPage(page_id);
        }

    }

    _init();
    
    return that;
}



