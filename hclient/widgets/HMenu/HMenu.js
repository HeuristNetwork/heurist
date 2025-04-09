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

$.widget( 'heurist.HMenu', $.heurist.HBaseWidget, {

    // default options
    options: {
        resourcePath: 'hclient/widgets/HMenu/HMenu',
        
        viewMode: 'horizontal', // none, horizontal or vertical buttonsMenu, tree    
        //styleMode: 'links',     // pills, jquery
        
        customActionHandler: null,  // replacement of default event handler via ActionHandler
        onBeforeAction: null,
        onActionComplete: null    // invoked in ActionHandler after action execution
    },
    
    _needLoadContent: false,
    _needLoadCss: true,
    
    _actionHandler: null,

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

        this._actionHandler = this.HAPI.actionHandler;
       
        if(this.options.viewMode=='horizontal'){
                // move it to HMenu
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
        }
        
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
        if(this.options.onActionComplete){
            opts.callback = this.options.onActionComplete;
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onBeforeAction)){
            const is_locked = this.options.onBeforeAction.call(this, action_id, opts);
            if(is_locked){
                return;
            }
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
        
    }
    
});
