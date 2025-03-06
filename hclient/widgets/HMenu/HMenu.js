/**
* Menu - menu handler
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( 'heurist.HMenu', {

    //roles in content
    // heurist-role-count
    // heurist-role-pagination
    // heurist-role-viewport
    
    // default options
    options: {
        hapi: null,
        
        path: 'hclient/widgets/HMenu/',
        htmlContent: null, // custom content
        uiLibrary: null,   // 'bootstrap','jqueryui'
    
        //menuTreeJSON: null,
        viewMode: 'bootstrap', // none, horizontal or vertical buttonsMenu, tree    
        menuActionHandler: null,  // replacment of default event handler via ActionHandler
        onBeforeAction: null,
        onActionComplete: null    // invoked in ActionHandler after action execution
    },
    
    $H: window.hWin.HEURIST4.util,
    _$: $, //shorthand for this.element.find

    _init_completed: false,
    
    actionHandler: null,
    

    // the widget's constructor
    _create: function() {
        
        this._$ = selector => this.element.find(selector); //querySelector(selector); 

        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        this.options.templateView = null; 
    
        let that = this;    
        
        if(!this.options.hapi){
            this.options.hapi = window.hWin.HAPI4;    
        }
        
        this.actionHandler = this.options.hapi.actionHandler;

        const isCssLoaded = selectorExists('.dropdown-hover-all');

        if(!isCssLoaded){
            //add widget classes
            let css_url = this.options.hapi.baseURL + this.options.path + 'HMenu.css';
            $.getStyles(css_url);
        }
        
            
        if(!this.$H.isempty(this.options.htmlContent)){ 
            //custom content
            this.element.html(this.options.htmlContent);
        }
        
        this._initControls();
    },
    
    //  
    // invoked from _init after loading of html content
    // adds event listeners 
    //
    _initControls:function(){
        let that = this;
       
        if(this.options.viewMode=='bootstrap'){
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
        
        /*
            data-heurist-recid  - view record
            data-heurist-svs  - start saved filter
            data-heurist-svs-list|add|delete  
        
        */    
        
        this._init_completed = true;
        
    },
    
    
    menuActionHandler: function(event, ui) {

        event.preventDefault(); 
        let ele;
        
        if(ui?.item){
            ele = ui.item;
        }else{
            ele = $(event.target);
            if(ele.is('span')){
                ele = ele.parent();// If a span inside a button is clicked
            }
        }
        
        let opts = {};
        let action_id = ele.attr('data-heurist-action');
        if(!action_id){
            action_id = ele.attr('data-heurist-pageid');
            opts.page_id = action_id;
            action_id = 'data-heurist-pageid';
        }
        if(this.options.onActionComplete){
            opts.callback = this.options.onActionComplete;
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onBeforeAction)){
            const is_locked = this.options.onBeforeAction.call(this, action_id, opts);
            if(is_locked){
                return false;
            }
        }
        
        // Call user-defined action handler
        if(this.options.menuActionHandler){
            this.options.menuActionHandler.call(this, action_id, opts);
        }else{
            this.actionHandler.executeActionById(action_id, opts);
        }
        
        return false; 
    },
    

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        if(!this._init_completed) return;
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        this.clearContent();       
        this._clearPagination();       
    },

    //
    // Removes content
    //
    clearContent: function(){
        
        if(!this._init_completed) return;
        
        //_off all clicks for actions per record cards
        this._off( this.div_content.find(`div[${this.record_id_attr}]`), 'click');
    },
    

    
});
