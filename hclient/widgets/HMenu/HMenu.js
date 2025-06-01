/**
* HMenu - menu handler
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import '../HBase/HBaseWidget.js';
import '../HMenu/HMenuOpts.js';

$.widget( 'heurist.HMenu', $.heurist.HBaseWidget, {

    // default options
    options: {
        resourcePath: 'hclient/widgets/HMenu/HMenu',
        
        //array of record ids or json array 
        /* {title: - label
            icon:  - optional icon image
            page_id - cms record id
            action_id - OR action
            children: []
           }
        */
        menuItems: null, 
        
        viewMode: 'horizontal', // none, horizontal or vertical buttonsMenu, treeview    
        styleMode: 'links',     // link,pills, buttons(?), jquery
        expandLevels: 0,        // for treeview
        
        customActionHandler: null,  // replacement of default event handler via ActionHandler
        onBeforeAction: null,
        onActionComplete: null,    // invoked in ActionHandler after action execution
        
        isEditMode: false,
        onStructureChanged: null

    },
    
    _needLoadContent: false,
    _needLoadCss: true,
    
    _menuData: null, //json array with list of actions 
    
    _selectorInput: null, //hidden input to select/add menu intems in edit mode
    _browseFunction: null, //function that opend menu items selector popup

    _init: function(){
        
        this._super();

        this._events = this.HAPI.Event.ON_CREDENTIALS;
        let that = this;
        $(window.hWin.document).on(this._events, (event, data)=>that.eventHandler(event, data) );
    },

    
    /**
     * Initializes UI controls and event listeners after content is loaded.
     */
    _initControls:function(){
        let that = this;
    
        if(this.options.menuItems && this._menuData==null){
            this.reloadMenuData();
            return;
        }
        
        
        if(this.options.viewMode=='treeview'){
            
            let fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: this._menuData,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                //renderNode: null,
                //extensions:[],
                activate: function(event, data) { 
                    if(data.node.data.page_id>0){
                        that.executeAction( 'data-heurist-pageid', {pageId:data.node.data.page_id});
                    }
                }
            };
            
            if(this.options.isEditMode){
                
                let fancytree_options_edit =
                {
                    renderNode: function(event, data) {
                            let item = data.node;
                            that._defineActionIcons( item );
                    },
                    extensions:["dnd"], //"edit", 
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
                                source_parent = 0; //root

                            data.otherNode.moveTo(node, data.hitMode);

                            let target_parent = data.otherNode.parent.data.page_id;
                            if(!(target_parent>0))
                                target_parent = 0; //root
                            data.otherNode.data.parent_id = target_parent;
                            
                            that._getMenuStructure();
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
//TBD                            _renameMenuEntry(data.node.data.page_id, new_name, function(){});
                            }else{
                                $(data.node.span).removeClass("pending");    
                            }
                        }
                    }
                };
                
                fancytree_options = $.extend(fancytree_options, fancytree_options_edit);                
            }
            

            this.element.fancytree(fancytree_options).addClass('tree-cms');
            if(this.options.expandLevels>0){
                let tree = $.ui.fancytree.getTree( this.element );
                if(this.options.expandLevels==2){
                    tree.expandAll();
                }else{
                    tree.visitRows((node)=>{
                        if(node.getLevel()<2){
                            node.setExpanded(true);
                        }else{
                            return false;
                        }
                    });
                } 
            }
            this._super();
            return;
        }
        
        
        if(this.element.children().length==0){ 
            //if content is not defined - generate it based on record ids
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(this._menuData)){
                this.element.append(this.generateMenu( this._menuData, 0));
            }else{
                this.addErrorMessage();
            }
        }
                
        
        // multi level drop down support
        let $bs = bootstrap;

        const CLASS_NAME = 'has-child-dropdown-show';
        $bs.Dropdown.prototype.toggle = function(_orginal) {
            return function() {
                document.querySelectorAll('.' + CLASS_NAME).forEach(function(e) {
                    e.classList.remove(CLASS_NAME);
                });
                let dd = this._element.closest('.dropdown').parentNode.closest('.dropdown');
                for (; dd && dd !== document; dd = dd.parentNode.closest('.dropdown')) {
                    dd.classList.add(CLASS_NAME);
                }
                return _orginal.call(this);
            }
        }($bs.Dropdown.prototype.toggle);                
                
        document.querySelectorAll('.dropdown').forEach(function(dd) {
            dd.addEventListener('hide.bs.dropdown', function(e) {
                if (this.classList.contains(CLASS_NAME)) {
                    this.classList.remove(CLASS_NAME);
                    e.preventDefault();
                }
                e.stopPropagation(); // do not need pop in multi level mode
            });
        });                
        

        /*
        document.querySelectorAll('.dropdown-hover-all .dropdown').forEach(function(dd) { //.dropdown-hover,  .dropdown-toggle
        dd.addEventListener('mouseenter', function(e) {
        let toggle = e.target.querySelector(':scope>[data-bs-toggle="dropdown"]');
        if (!toggle?.classList.contains('show')) {
        $bs.Dropdown.getOrCreateInstance(toggle).toggle();
        dd.classList.add(CLASS_NAME);
        $bs.Dropdown.clearMenus(e);
        }
        });
        dd.addEventListener('mouseleave', function(e) {
        let toggle = e.target.querySelector(':scope>[data-bs-toggle="dropdown"]');
        if (toggle?.classList.contains('show')) {
        $bs.Dropdown.getOrCreateInstance(toggle).toggle();
        }
        });                
        });        
        */
        //init action event listener
        this._on( this._$('a[data-heurist-action]'), {click : this.menuActionHandler }); //system actions
        this._on( this._$('a[data-heurist-pageid]'), {click : this.menuActionHandler }); //load cms record (web page)
        
        /*  Action attributes for link elements
            data-heurist-recid  - view record
            data-heurist-svs  - start saved filter
            data-heurist-svs-list|add|delete  
            data-heurist-pageid = edit
        
        */    
        
        this._super();
    },
    
    /*
    *
    */
    getUiEle: function(event, ui){
        
        let ele;
        
        if(ui?.item){
            ele = ui.item;
        }else{
            ele = $(event.target);
            if(!(ele.attr('data-heurist-action') || ele.attr('data-heurist-pageid'))){
                //must have data-heurist-action or data-heurist-pageid attribute
                let ele2 = ele.parents('[data-heurist-action]');// If a span inside a button is clicked
                if(ele2.length==0){
                    ele = ele.parents('[data-heurist-pageid]');
                }else{
                    ele = ele2;
                }
            }
        }
        
        return ele;
    },
    
    /**
     * Initializes UI controls and event listeners after content is loaded.
     */
    menuActionHandler: function(event, ui) {

        event.preventDefault(); 
        
        let ele = this.getUiEle(event, ui);
        
        let opts = {};
        let action_id = ele.attr('data-heurist-action');
        if(!action_id){
            //open webpage with give id
            action_id = ele.attr('data-heurist-pageid');
            opts.pageId = action_id;
            action_id = 'data-heurist-pageid';
        }
        this._$('a.nav-link').removeClass('active');
        ele.addClass('active');
        
        this.executeAction(action_id, opts);    
    },
     
    executeAction: function(action_id, opts){

        if(window.hWin.HEURIST4.util.isFunction(this.options.onBeforeAction)){
            const is_locked = this.options.onBeforeAction.call(this, action_id, opts);
            if(is_locked){
                return;
            }
        }

        if(this.options.onActionComplete){
            opts.callback = this.options.onActionComplete;
        }
        
        // Call user-defined action handler
        if(this.options.customActionHandler){
            //custom handler
            this.options.customActionHandler.call(this, action_id, opts);
        }else if (this.HAPI.actionHandler) {
            //defeault action handler
            this.HAPI.actionHandler.executeActionById(action_id, opts);
        }
    },

    /*
    *
    */
    _destroy: function() {
        // remove generated elements
        if(this._events){
            $(this.document).off(this._events);
        }
        
        this.clearContent();    
        this._super();   
    },

    /*
    * Removes content
    */
    clearContent: function(){
        
        if(!this._initCompleted) return;   

        this._off( this._$('a[data-heurist-action]') );
        this._off( this._$('a[data-heurist-pageid]') );
        
        if(this.element.fancytree('instance')){
            let tree = $.ui.fancytree.getTree( this.element );
            tree.destroy();
        }
        this.element.empty();
        
    },
    
    /*
    *
    */
    eventHandler: function(e, data){
        if(e.type == this.HAPI.Event.ON_CREDENTIALS)
        {
            this.onChangeCredentials(data);
        } 
    },
    
    /*
    * Show/hide elements on menu depends on current credentials
    */    
    onChangeCredentials: function(data){
        
    },
    
    /*
    *
    */
    onCloseOptionEditor: function(newOptions){
        if(newOptions){
            
            newOptions = $.extend(window.hWin.HEURIST4.util.cloneJSON($.heurist.HMenu.prototype.options), newOptions);
            
            /*
            let newids = null;
            if (Array.isArray(newOptions.menuItems)){
                newids = newOptions.menuItems.join(',');   
            }else{
                newids = newOptions;
            }
            let oldids = null;
            if (Array.isArray(this.options.menuItems)){
                oldids = this.options.menuItems.join(',');   
            }else{
                oldids = this.options.menuItems;
            }
            */
            //if( isJSON)
            //JSON.stringify(newOptions.menuItems)!=JSON.stringify(this.options.menuItems)
            
            
            if(true) //newids!=oldids
            {
                this._menuData = null;    //reset
            }
            if(this.options.viewMode!=newOptions.viewMode){
                this.clearContent();
            }

            this.element.HMenu(newOptions);
        }
    },
    
    //
    //find menu contents by top level ids    
    //
    reloadMenuData:function(){
        
        //find menu contents by top level ids    
        let ids = this.options.menuItems;
        if(ids==null){
            this.options.menuItems = [];
            ids = '';    
        } else {
            
            
            ids = window.hWin.HEURIST4.util.isJSON(this.options.menuItems);
            if(ids)
            {
                this.options.menuItems = ids;
            }else{
                ids = this.options.menuItems;
                if(Array.isArray(ids)) {ids = ids.join(',');}
                else if(window.hWin.HEURIST4.util.isNumber(ids)){
                    this.options.menuItems = [ids];
                }else{
                    this.options.menuItems = ids.split(',')  
                } 
            }
            
        }
        
        this._menuData = null; //result - full json 
        this.clearContent();

        if(this.options.menuItems.length==0){
            this.addErrorMessage();
            return;
        }
        
        let that = this;
        
        //retrieve menu content from server side
        let request = {website:1, ver:3, webmenu:JSON.stringify(this.options.menuItems), isTree:true, lang:this.options.language};
        window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
            if(response.status == window.hWin.ResponseStatus.OK){
                that._menuData = response.data;
                that._initControls();
            }else{
                this.clearContent();
                this.addErrorMessage(response.message);
            }
        });
        
    },

    /*
    *
    */    
    addErrorMessage: function(message){
        
            if(!message){
                if(window.hWin.HEURIST4.util.isArrayNotEmpty(this._menuData)){
                    message = '';
                }else{
                    message = 'Content not defined';
                }
            }
        
            $(`<p class="ui-state-error">Can't init menu. ${message}</p>`).appendTo(this.element); 
        
    },
    
    /*
    *
    */
    generateMenu: function( menuItems, lvl ){
        
        
        let cssColor = this.element.css('--bs-nav-link-hover-color'); //--bs-link-hover-color-rgb
        if(cssColor){
            cssColor = `--bs-nav-link-hover-color:${cssColor};`;
        }else{
            cssColor = '';
        }
        let txtColor = this.element.css('color');
        if(txtColor){
            cssColor = cssColor + '--bs-nav-link-color:'+txtColor;
            txtColor = '';
        }else{
            //HCmsEditor.getBsClassesAsString(this.element,'text-');
            let classes = Array.from(this.element[0].classList);
            classes = classes.filter(function(value) {
              let res = ['text-'].some(substr => value.startsWith(substr));
              return res; });        
            txtColor = classes.join(' ');
        }
        if(cssColor){
            cssColor = ` style="${cssColor}"`;    
        }
        
        let res = '';
        let itemClass = '';
        if(lvl==0){
            
            let navClasses = 'nav dropdown-hover-all';
            if(this.options.viewMode=='vertical'){
                navClasses += ' flex-column';
            }
            if(this.options.styleMode=='pills'){
                navClasses += ' nav-pills';
                itemClass = ' border rounded mx-1';
            }
            
            /*if(this.options.viewMode=='horizontal'){
                //in case of nabvar
                res = '<navbar class="navbar navbar-expand">';
                navClasses = 'navbar-nav';
            }*/
            
            
            res += '<ul class="'+navClasses+'">';
            
        }else{
            res = '<ul class="dropdown-menu dropdown dropend">';
        }
        
        for(let i=0; i<menuItems.length; i++) {
        
            const menuTitle = menuItems[i].title;
            
            const hasSubs = window.hWin.HEURIST4.util.isArrayNotEmpty(menuItems[i].children);
            if(hasSubs){

                
                if(lvl==0){
                    res += '<li class="nav-item dropdown'+itemClass+'"><a '+cssColor+' class="nav-link dropdown-toggle '+txtColor+'" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">'+menuTitle+'</a>';
                }else{                                                                           
                    res += '<li class="dropdown dropend"><a class="dropdown-item dropdown-toggle '+txtColor+'" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'+menuTitle+'</a>';                    
                }
                
                res += this.generateMenu(menuItems[i].children, lvl+1);
                
                res += '</li>';
                
            }else{
                
                let opts = {mode:this.options.isEditMode?'edit':'', 
                            websiteid:this.options.siteId, 
                            pageid:menuItems[i].page_id, lang:this.options.language};
                const pageURL = window.hWin.HEURIST4.ui.getCmsLink(opts);
                
                
                if(lvl==0){
                    res += '<li class="nav-item'+itemClass+'"><a '+cssColor+' class="nav-link '+txtColor+'"';
                }else{
                    res += '<li><a class="dropdown-item '+txtColor+'"';
                }
                res = res + ` data-heurist-pageid="${menuItems[i].page_id}" href="${pageURL}">${menuTitle}</a></li>`;
            }
        }

        res += '</ul>';
        
        if(lvl==0 && this.options.viewMode=='horizontal'){
            //res += '</navbar>';
        }
        
        return res;
    },
    
    /*
    *  render actions buttons for edit mode
    */
    _defineActionIcons: function(item){
        let tree_element = this.element;
        let that = this;
        
        let item_li = $(item.li);
        let menu_id = item.key;//item.data.page_id;

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
                +'" title="Remove menu entry"></span>'
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

                        that.addMenuEntry(menuid); 

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
                                            let pageId = recordset.getOrder()[0];
                                            
                                            // Update tree
                                            let new_name = recordset.fld(recordset.getFirstRecord(), DT_NAME);
                                            let node = $.ui.fancytree.getTree( that.element ).getNodeByKey( pageId );
                                            if(node.title != new_name){
                                                    node.setTitle( new_name );
                                                    that._defineActionIcons(node);
                                                    that._getMenuStructure();
                                            }
                                        }
                                    }
                                }
                            );
                        }

                        __editPageRecord(menuid);

                    }else 
                        if(ele.hasClass('ui-icon-trash')){    //remove menu entry
                            window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Delete menu entry? Please confirm"),  function(){
                                item.remove();  
                                that._getMenuStructure();  
                            });
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
    }, //end _defineActionIcons

    //
    // refresh main menu and reload current page
    //
    _refreshMainMenu: function ( need_refresh_tree, new_page_id ){
        
        
    },
    
    /*
    *
    */
    _findNodeByPageId: function(pageId){
        
        let resultNode = null;

        $.ui.fancytree.getTree( this.element ).visit((node) => {
            if(node.data.page_id == pageId){
                resultNode = node;
                return false; //found
            }
        });
        
        return resultNode;
    },
    
    /*
    * Converts treeview strucuture to json {id1:{}   }
    */
    _getMenuStructure: function( struct ){
        
        let isRoot = false;
        if(!struct){
            isRoot = true;
            this._menuData = $.ui.fancytree.getTree( this.element ).toDict(true);
            struct = this._menuData.children;
        }                 
        
        let item = {};
        if(struct){
            for(let i=0; i<struct.length; i++){
                
                item[struct[i].key] = {};
                
                if(struct[i].children?.length>0){
                      item[struct[i].key] = this._getMenuStructure(struct[i].children);
                }
                
            }
        }
        
        if(isRoot && window.hWin.HEURIST4.util.isFunction(this.options.onStructureChanged)){
                this.options.onStructureChanged.call(this, item);
        }
        
        return item;
    },
    
    /*
    *
    */
    _verifyUniqueMenuKeys(menuData){
        
        let tree = $.ui.fancytree.getTree( this.element );
        let duplications = [];
        
        for (let idx in menuData){
            
            let item = menuData[idx];
            if(tree.getNodeByKey(item.key)){
                duplications.push(item.key);
            }
            
            if(item.children){
                let dups = this._verifyUniqueMenuKeys(item.children);            
                if(dups.length>0){
                    duplications = duplications.concat(dups);        
                }
            }
        }
        
        return duplications;
    },
    
    /*
    *
    */
    addMenuEntry: function( parentNodeKey ){
        
            if(this._selectorInput!=null){
                this._browseFunction();
                return;
            }

            this._selectorInput = $('<div>').uniqueId();
        
            //constraint
            let rty_IDs = [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU']]; 
            if(window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_ACTION']){
                rty_IDs.push(window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_ACTION']);
            }
            
            let that = this;
        
            const ed_options = {
                recID: -1,                                                                                       
                dtID: this._selectorInput.attr('id'), //'group_selector',
                values: [],
                readonly: false,
                show_header: false,
                showclear_button: true,
                dtFields:{
                    dty_Type:"resource", rst_MaxValues:0,
                    rst_DisplayName: 'Menu items', rst_DisplayHelpText:'',
                    rst_PtrFilteredIDs: rty_IDs,
                    rst_FieldConfig: {entity:'records', csv:false}
                },
                change: ()=>{
                    //result
                    
                    const page_id = that._selectorInput.attr('data-value');
                    
                    const tree = $.ui.fancytree.getTree( that.element );
                    
                    let parentNode = null;
                    if(tree){
                        if(parentNodeKey>0){
                            parentNode = tree.getNodeByKey(parentNodeKey);
                        }
                        if(!parentNode){
                            parentNode = tree.getRootNode();
                            if(window.hWin.HEURIST4.util.isempty(that._menuData)){
                                tree.clear();
                            }
                        }
                    }
                    
                    //retrieve menu content from server side
                    let request = {website:1, ver:3, webmenu:[page_id], isTree:true, lang:that.options.language};
                    window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            if(parentNode==null){ //treeview not inited
                                that._menuData = response.data;
                                that._initControls(); 
                                return;
                            }
                            
                            const dups = that._verifyUniqueMenuKeys(response.data);
                            
                            if(dups.length>0){
                                window.hWin.HEURIST4.msg.showMsgErr('It is not possible to add new menu entry. It is already in menu');    
                            }else{
                                parentNode.addNode( response.data, parentNode.isRootNode() || parentNode.hasChildren()? 'child': 'after' );
                                that._getMenuStructure();
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                    });
                }
            };
            
            this._selectorInput.editing_input(ed_options);
            this._selectorInput.appendTo($('<div>').appendTo(this.element));
            this._selectorInput.parent().hide();
            
            this._browseFunction = browseRecords(this._selectorInput.editing_input('instance'), this._selectorInput, 'Select menu items');
            
            this._browseFunction();
    },
    


/*
1) menu treeview to add/edit/delete (same as site menu)  redundant?
   or just root items (as it is)
   
2) Add - select CMS_page, 
                Filter group or Saved filter, (UsrSavedSearchs entry or CMS Filter record)
                Add Record  (CMS Action record or Dashboard entry)
                View Record 
                Execute Smarty with saved filter 
                External Link (Web bookmark record)
                
3) 
   
Wrokflow/Widget links   
   
                

*/    

    
    
});
