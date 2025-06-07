/**
* HMenu - widget for page navigation, actions and saved filters links
* 
* Content:
* Content of the menu widget can be defined via the widget property form in the CMS editor which provinces a treeview that consists of menu and submenu items. Each menu item refers to a “CMS page” record, a Saved filter (usrSavedSearches) or an Action (sysDashboard). The Submenu (folder) structure implies no difference in the pages, it exists solely to create a hierarchy within the menu. Technically the CMS page and saved filter can be defined via an action.
* * An alternative (advanced) way is to define an html snippet with buttons and/or links with one of  the attributes: data-heurist-action, data-heurist-pageid or data-heurist-search.  
* 
* Appearance/Presentation:
* If the content is defined via json or html, snippet elements have attributes that define their role (eg. data-heurist-role="menu-dropdown"). It is possible to define the appearance of the menu via the widget property form. Menus can be vertical, horizontal or treeview. They can be bootstrap or jquery (tbd). They can be collapsable.
* 
* Interaction:
* On menu selection, the widget executes the specified action, loads the web page or starts the saved filter. It also triggers the ON_ACTION event. HMenu has a built-in HFilter widget. It handles Saved Filters. 
* 
* If Saved Filter has entries to be defined by the website visitor (faceted search), HMenu opens the Filter form. The appearance of this form is similar to HRecordView for HRecordList. It can be inline (over menu), in a floating popup, in a modal dialog, in an offcanvas (side slide panel).  If the publisher prefers to specify their own HForm, it can be connected to HMenu via the search group.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import '../HBase/HBaseWidget.js';
import '../HFilter/HFilter.js';

$.widget( 'heurist.HMenu', $.heurist.HBaseWidget, {

    // default options
    options: {
        resourcePath: 'hclient/widgets/HMenu/HMenu',
        
        //array of record ids or json array 
        /* {title: - label
            icon:  - optional icon image
            pageId - cms record id
            action - OR action
            actionParams
            
            menuFormat,
            menuIcon
            
            children: []
           }
        */
        menuItems: null, 
        
        viewMode: 'horizontal', // none, horizontal or vertical buttonsMenu, treeview    
        styleMode: 'links',     // link,pills, buttons(?), jquery
        expandLevels: 0,        // for treeview
        
        viewFilterMode: 'inline',
        searchDomain: null, 
        
        customActionHandler: null,  // replacement of default event handler via ActionHandler
        onBeforeAction: null,
        onActionComplete: null,    // invoked in ActionHandler after action execution
        
        isEditMode: false
    },
    
    _needLoadContent: false,
    _needLoadCss: true,
    
    _menuData: null, //json array with list of actions 
    
    _fancytreeOptionsEdit: null,    
    filterView: null,

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
        
//console.log(this.options, this._fancytreeOptionsEdit);        
        
        if(this.options.viewMode=='treeview'){
            
            let fancytreeOptions =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: this._menuData,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                //renderNode: null,
                //extensions:[],
                activate: function(event, data) { 
                    if(data.node.data.pageId>0){
                        that.executeAction( 'data-heurist-pageid', {pageId:data.node.data.pageId});
                    }else if(data.node.data.action){
                        that.executeAction( data.node.data.action, data.node.data.actionParams );    
                    }
                }
            };

            if(this._fancytreeOptionsEdit){
                fancytreeOptions = $.extend(fancytreeOptions, this._fancytreeOptionsEdit);
            }
            
            this.element.fancytree(fancytreeOptions).addClass('tree-cms');
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
        
        //init action event listener
        this._on( this._$('a[data-heurist-search]'), {click : this.menuActionHandler }); //system actions
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
            if(!(ele.attr('data-heurist-action') || ele.attr('data-heurist-pageid') || ele.attr('data-heurist-search'))){
                //must have data-heurist-action or data-heurist-pageid attribute
                let ele2 = ele.parents('[data-heurist-action]');// If a span inside a button is clicked
                if(ele2.length==0){
                    ele = ele.parents('[data-heurist-pageid]');
                }else if(ele2.length==0){
                    ele = ele.parents('[data-heurist-search]');
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
        if(action_id){
            opts = ele.attr('data-heurist-actionParams');
            const opts2 = window.hWin.HEURIST4.util.isJSON(opts);
            if(opts2) opts = opts2;
        }else if(ele.attr('data-heurist-search')>0) {
            
            action_id = 'search-saved-filter';
            opts = {svsID:ele.attr('data-heurist-search')};
        }else{
            //open webpage with give id
            action_id = ele.attr('data-heurist-pageid');
            if(action_id>0){
                opts.pageId = action_id;
                action_id = 'data-heurist-pageid';
            }else{
                return; //no action
            }
        }
        this._$('a.nav-link').removeClass('active');
        ele.addClass('active');
        
        this.executeAction(action_id, opts);    
    },
     
    /*
    *
    */
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
            
            if(action_id=='search-saved-filter'){
                // execute saved search
                this.executeSavedSearch( opts );
            }else{
                // default action handler
                this.HAPI.actionHandler.executeActionById(action_id, opts);
            }
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
            
            /* TBD reload if menuItems param has been changed
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
console.log( that._menuData );                
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
                
                let pageURL = '#';
                if(menuItems[i].pageId>0){
                    let opts = {mode:this.options.isEditMode?'edit':'', 
                            websiteid:this.options.siteId, 
                            pageid:menuItems[i].pageId, lang:this.options.language};
                    pageURL = window.hWin.HEURIST4.ui.getCmsLink(opts);
                }
                
                
                if(lvl==0){
                    res += '<li class="nav-item'+itemClass+'"><a '+cssColor+' class="nav-link '+txtColor+'"';
                }else{
                    res += '<li><a class="dropdown-item '+txtColor+'"';
                }
                if(menuItems[i].pageId>0){
                    res = res + ` data-heurist-pageid="${menuItems[i].pageId}"`
                }else if(menuItems[i].action=='search-saved-filter'){
                    
                    res = res + ` data-heurist-search="${menuItems[i].actionParams}"`;
                    
                }else if(menuItems[i].action){
                    res = res + ` data-heurist-action="${menuItems[i].action}"`
                    if(menuItems[i].actionParams){
                        res = res + ` data-heurist-actionParams="${menuItems[i].actionParams}"`
                    }
                }
                
                res = res + ` href="${pageURL}">${menuTitle}</a></li>`;
            }
        }

        res += '</ul>';
        
        if(lvl==0 && this.options.viewMode=='horizontal'){
            //res += '</navbar>';
        }
        
        return res;
    },
    
    /*
    *
    */
    executeSavedSearch: function(opts){
        
        if(window.hWin.HEURIST4.util.isPositiveInt(opts)){
            opts = {svsID:options};
        }                              
        
        if(!this.filterView){
            this.filterView = $('<div>').appendTo(this.element);
        }
        
        if(this.filterView.HFilter('instance')){
            this.filterView.HFilter('doSearchByID', opts.svsID);
        }else{
            this.filterView.HFilter({svsID: opts.svsID,
                                        viewMode: this.options.viewFilterMode, 
                                        keepInstance: true,
                                        searchDomain: this.options.searchDomain});
        }
    }
    
});
