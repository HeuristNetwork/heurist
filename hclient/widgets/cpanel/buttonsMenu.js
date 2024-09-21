/**
* buttonsMenu.js - dropdown menu grouped in buttons
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

/**
 * jQuery UI Widget: heurist.buttonsMenu
 * 
 * This widget creates a horizontal menu with buttons and optional submenus. It includes customizable styles, content loading from external sources, and menu item action handling.
 * 
 * @options
 *   @param {String} menu_class - Additional CSS class for the menu container (default: null).
 *   @param {String} menuContent - HTML snippet to populate the menu content. If undefined, the element's HTML is used (default: null).
 *   @param {String} menuContentFile - URL to an HTML snippet file with the menu structure (default: null).
 *   @param {Function} manuActionHandler - Callback function for menu item actions (default: null).
 * 
 * @properties
 *   @property {Object} divMainMenuItems - Stores the main `<ul>` container for menu items.
 *   @property {Array} menuBtns - Stores references to the top-level menu buttons.
 *   @property {Array} menuSubs - Stores submenus linked to the top-level buttons.
 * 
 * @methods
 *   _create() - Initializes the widget and sets up the menu.
 *   _refresh() - Placeholder for refreshing the widget (currently unused).
 *   _destroy() - Cleans up the widget and removes all menu buttons and submenus.
 *   _initMenu(callback) - Builds the menu and submenus based on options. Calls the provided callback when complete.
 *   menuActionHandler(event, ui) - Handles menu item clicks and triggers the appropriate action.
 *   _initActionItem(idx, item) - Initializes each submenu action item.
 */
$.widget( "heurist.buttonsMenu", {

    // default options
    options: {
        menu_class:null,
        
        //if content is not defined here, it takes this.element.html()
        menuContent: null, // HTML snippet with ul/li menu items
        //DISABLED menuContentFile: null, // HTML snippet file with menu
        manuActionHandler: null // Callback for handling menu actions
    },
    
    divMainMenuItems: null, // Parent UL
    menuBtns: [], // Array of menu buttons
    menuSubs: [], // Array of submenu elements  

    /**
     * _create
     * 
     * Initializes the widget, setting up the menu's DOM structure and handling.
     * It fetches menu content if needed and prepares the buttons and submenus.
     */
    _create: function() {

        let that = this;

        this.element
        .css('font-size', '1.2em')
        .disableSelection();// prevent double click to select text
        
        this._initMenu(()=>{
            
            if(that.divMainMenuItems==null){
                return;
            }
            
            // Initialize the jQuery UI menu
            that.divMainMenuItems.menu();

            // Style the menu items
            that.divMainMenuItems.find('li').css({'padding':'0 3px 3px','text-align':'center'}); // center, place gap and setting width
            that.divMainMenuItems.find('li.autowidth').css('width','auto');

            // Apply menu_class if provided
            if(that.options.menu_class!=null){
                that.element.addClass( that.options.menu_class );   
            }else{
                that.divMainMenuItems.find('.ui-menu-item > a').addClass('ui-widget-content');    
            }
           
           // Style the right-hand icons in menu items
            that.divMainMenuItems.children('li').children('a').children('.ui-icon-right').css({right: '2px', left:'unset'});

            that._refresh();
        });

    }, //end _create

    /**
     * _refresh
     * 
     * Placeholder method for refreshing the widget.
     * Can be implemented later to reflect current level of credentials 
     */    
    _refresh: function() {
        // No content to refresh for now
    },
    
    /**
     * _destroy
     * 
     * Cleans up the widget by removing all menu buttons and submenus.
     * Empties the `menuBtns` and `menuSubs` arrays.
     */
    _destroy: function() {
        
        this.menuBtns.forEach((item)=>{
           $(item).remove();
        });
        this.menuSubs.forEach((item)=>{
           $(item).remove();
        });
        this.menuBtns = [];
        this.menuSubs = [];

    },

    /**
     * _initMenu
     * 
     * Initializes the menu structure and loads content if provided.
     * Builds the main menu and submenus from either static HTML or a content file.
     * @param {Function} callback - Called when the menu initialization is complete.
     */
    _initMenu: function(callback){

        let that = this;
        let myTimeoutId = -1;

        // Function to hide submenus after a timeout
        let _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 1);
        };

        // Function to show submenus and position them correctly
        let _show = function(ele, parent) {
            clearTimeout(myTimeoutId);

            $('.menu-or-popup').hide(); //hide other
            $( ele )
            .show()
            .position({my: "left top", at: "left bottom", of: parent, collision:'none' });
            return false;
        };

        // If menuContentFile is specified, load the menu from an external file
        /* DISABLED
        if(this.options.menuContentFile){
            $.get(window.hWin.HAPI4.baseURL+this.options.menuContentFile,
                function(response){
                    that.options.menuContent = response;
                    that._initMenu(callback); // Reinitialize after loading content
            });
            return;
        }*/

        let top_levels;
        
        // Determine the top-level UL elements to build the menu from
        if (this.options.menuContent) {
            top_levels = $(this.options.menuContent).find('ul'); // From menuContent
        } else {
            top_levels = this.element.find('ul'); // From existing element HTML
        }
        

        if(top_levels.length==0){
            console.error('menu content is not defined');
            return;
        }
            
        this.element.empty(); // Clear existing content
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.element);

        for (let tul of top_levels) {
            // Initialize each top-level button and submenu
            
            let top_level = $(tul);
            let menuName = this._createMenuButton(top_level);
            
            // Initialize submenu if present
            let submenu = top_level.find('li');
            
            if(submenu.length==0){ //without children
                
                this._on( this.menuBtns[menuName], {
                    click : this.menuActionHandler });
                this.menuBtns[menuName].addClass('autowidth');
                
            }else{
                
                $.each(submenu, this._initActionItem);

                this.menuSubs[menuName] = top_level.hide();

                this.menuSubs[menuName].addClass('menu-or-popup')
                .css('position','absolute')
                .appendTo( that.document.find('body') )
                //.addClass('ui-menu-divider-heurist')
                .menu({
                    icons: { submenu: "ui-icon-circle-triangle-e" },
                    select: function(event, ui){ that.menuActionHandler(event, ui) } });

                /* not tested
                if(window.hWin.HAPI4.has_access()){
                    this.menuSubs[menuName].find('.logged-in-only').show();
                }else{
                    this.menuSubs[menuName].find('.logged-in-only').hide();
                }

                this.menuSubs[menuName].find('li[data-user-experience-level]').each(function(){
                    if(usr_exp_level > $(this).data('exp-level')){  //data-competency
                        $(this).hide();    
                    }else{
                        $(this).show();    
                    }
                });

                //localization                
                this.menuSubs[menuName].find('li[id^="menu-"]').each(function(){
                    let menu_id = $(this).attr('id');
                    let item = $(this).find('a');
                    const label = window.hWin.HR( menu_id );
                    if(label!=menu_id) item.text(label);
                    const hint = window.hWin.HR( menu_id+'-hint');
                    if(hint!=(menu_id+'-hint')){
                        item.attr('title',hint);    
                    }
                });
                */

                this.menuSubs[menuName].find('li').css('padding-left',0);

                this._on( this.menuBtns[menuName], {
                    mouseenter : function(){_show(this.menuSubs[menuName], this.menuBtns[menuName])},
                    mouseleave : function(){_hide(this.menuSubs[menuName])}
                });
                this._on( this.menuSubs[menuName], {
                    mouseenter : function(){_show(this.menuSubs[menuName], this.menuBtns[menuName])},
                    mouseleave : function(){_hide(this.menuSubs[menuName])}
                });
            }
        }//for
            
        callback.call(); //init completed
    },
    
    /**
    * Helper _createMenuButton
    */
    _createMenuButton: function(top_level){
        let menuID = top_level.attr('id') ? `data-action="${top_level.attr('id')}"` : '';

        const menuName = window.hWin.HR(top_level.attr('title'));
        let menuLabel = window.hWin.HR(top_level.attr('data-label')) || menuName;
            
        let menuCss =  top_level.attr('style');
        menuCss = menuCss?` style="${menuCss}"`:'';
            
        let linkCss =  top_level.attr('link-style');
        if(!linkCss) {linkCss = '';}
        
        const menuTitle =  window.hWin.HR(top_level.attr('title'));
        
        let right_padding = '2px';
        let icon_left = top_level.attr('data-icon-left');
        if(icon_left){
            icon_left = `<span class="ui-icon ${icon_left}"></span>`;
            right_padding = '22px';
        }else{
            icon_left = '';
        }
        
        let icon_righ = top_level.attr('data-icon');
        if(!icon_righ){
            icon_righ = 'ui-icon-carat-d';    
        }
        icon_righ = (icon_righ!='none')?`<span class="ui-icon-right ui-icon ${icon_righ}"></span>`:'';

        let link = $(`<a ${menuID} href="#" style="padding:2px 22px 2px ${right_padding} !important;${linkCss}" title="${menuTitle}">${icon_left}<span>${menuLabel}</span>${icon_righ}</a>`);
        
        
        this.menuBtns[menuName] = $('<li'+menuCss+'>').append(link).appendTo( this.divMainMenuItems ); //adds to ul
        
        return menuName;
    },
    

    /**
     * menuActionHandler
     * 
     * Callback function triggered when a menu item is clicked or selected.
     * It retrieves the `data-action` attribute or the element's ID and passes it to the provided action handler.
     * 
     * @param {Event} event - The click event object.
     * @param {Object} ui - The UI object containing the selected item.
     */
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
        
        let action_id = ele.attr('data-action');
        if(!action_id){
            action_id = ele.attr('id');
        }
        // Call user-defined action handler
        if(this.options.manuActionHandler){
            this.options.manuActionHandler.call(this, action_id);
        }
        
        return false; 
    },
    
    /**
     * _initActionItem
     * 
     * Initializes individual submenu items by setting their attributes and event handlers.
     * It appends an anchor (`<a>`) to each item with the appropriate label, icon, and hint.
     * 
     * @param {Number} idx - Index of the submenu item.
     * @param {HTMLElement} item - The submenu item element.
     */
    _initActionItem: function(idx, item){
                        
        item = $(item);
        let action_id = item.attr('data-action');
        
        if( !action_id ){
            return
        }
            
        let action = window.hWin.HAPI4.actionHandler.findActionById(action_id);
            
        if(!action){
            return;   
        }
                
        let action_label = window.hWin.HR( action_id ); 
        if(!action_label){ //localized version not found
            action_label = action.text;
        }
        let action_hint = window.hWin.HR( action_id+'-hint' ); 
        if(!action_hint){ //localized version not foind
            action_hint = action.title;
        }

        $(`<a data-action="${action_id}" href="#" title="${action_hint}">${action_label}</a>`)
                .appendTo(item);
    }
    
});
