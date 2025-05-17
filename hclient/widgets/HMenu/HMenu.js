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
        
        menuItems: null,
        
        viewMode: 'horizontal', // none, horizontal or vertical buttonsMenu, tree    
        styleMode: 'links',     // link,pills, buttons(?), jquery
        expandLevels: 0,        // for treeview
        
        customActionHandler: null,  // replacement of default event handler via ActionHandler
        onBeforeAction: null,
        onActionComplete: null    // invoked in ActionHandler after action execution
    },
    
    _needLoadContent: false,
    _needLoadCss: true,
    
    _actionHandler: null,
    
    _menuData: null, //recordset with 

    _init: function(){
        
        this._super();

        this._actionHandler = this.HAPI.actionHandler;
        
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
                renderNode: null,
                extensions:[],
                activate: function(event, data) { 
//console.log(data.node.data);
                    if(data.node.data.page_id>0){
                        that.executeAction( 'data-heurist-pageid', {pageId:data.node.data.page_id});
                    }
                }
            };

            this.element.fancytree(fancytree_options).addClass('tree-cms');
            //tree.reload( this._menuData );
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
            this.element.append(this.generateMenu( this._menuData, 0));
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
            if(ele.is('span')){
                ele = ele.parent();// If a span inside a button is clicked
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
        }else{
            //defeault action handler
            //this._actionHandler
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
            
            if(newids!=oldids)
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
            if(Array.isArray(ids)) {ids = ids.join(',');}
            else if(window.hWin.HEURIST4.util.isNumber(ids)){
                this.options.menuItems = [ids];
            }else{
                this.options.menuItems = ids.split(',')  
            } 
        }
        
        this.clearContent();

        let that = this;
        
        //retrieve menu content from server side
        let request = {website:1, ver:3, webmenu:this.options.menuItems};
        window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, (response)=>{
            if(response.status == window.hWin.ResponseStatus.OK){
 //console.log(response.data);  
                that._menuData = response.data;
                that._initControls();
            }else{
                $('<p class="ui-state-error">Can\'t init menu: '+response.message+'</p>').appendTo(that.element);
                
            }
        });
        
    },
    
    /*
    *
    */
    generateMenu: function( menuItems, lvl ){
        
        
        let hoverColor = this.element.css('--bs-nav-link-hover-color');
        if(hoverColor){
            hoverColor = ` css="--bs-nav-link-hover-color:${hoverColor}"`;
        }
        //HCmsEditor.getBsClassesAsString(this.element,'text-');
        let classes = Array.from(this.element[0].classList);
        classes = classes.filter(function(value) {
          let res = ['text-'].some(substr => value.startsWith(substr));
          return res; });        
        const txtColor = classes.join(' ');
        
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
            
            const hasSubs = menuItems[i].children?.length>0;
            if(hasSubs){

                
                if(lvl==0){
                    res += '<li class="nav-item dropdown'+itemClass+'"><a '+hoverColor+' class="nav-link dropdown-toggle '+txtColor+'" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">'+menuTitle+'</a>';
                }else{                                                                           
                    res += '<li class="dropdown dropend"><a class="dropdown-item dropdown-toggle '+txtColor+'" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'+menuTitle+'</a>';                    
                }
                
                res += this.generateMenu(menuItems[i].children, lvl+1);
                
                res += '</li>';
                
            }else{
                const pageUrl = this._getPageUrl(menuItems[i].page_id);
                if(lvl==0){
                    res += '<li class="nav-item'+itemClass+'"><a '+hoverColor+' class="nav-link '+txtColor+'"';
                }else{
                    res += '<li><a class="dropdown-item '+txtColor+'"';
                }
                res = res + ` data-heurist-pageid="${menuItems[i].page_id}" href="${pageUrl}">${menuTitle}</a></li>`;
            }
        }

        res += '</ul>';
        
        if(lvl==0 && this.options.viewMode=='horizontal'){
            //res += '</navbar>';
        }
        
        return res;
    },
    
    _getPageUrl: function(pageId){
        
        let url = this.HAPI.baseURL+'?db='+this.HAPI.database
                +'&ver=3&website='+this.options.siteId;
        if(pageId>0){
            url = url+'&pageid='+pageId;
        }
        
        if(this.options.isEditMode){
              url = url + '&edit=1';
        }
        
        return url;
    },

    
});
