/**
* slidersMenu.js : side menu with sections as popup sliders
* 
* It loads slidersMenuXxx.html for every section
* They took icons, titles and rollovers in core/actions.json via window.hWin.HAPI4.actionHandler
* This object handles all actions via executeActionById method
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
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

/* global HSvsEdit */ // Keep this, HSvsEdit is used.

/**
 * jQuery UI Widget: heurist.slidersMenu
 *
 * This widget creates a side menu with sections that expand as popup sliders.
 * It loads HTML content for each section (e.g., design, populate, explore) and initializes actions
 * based on `core/actions.json` via `window.hWin.HAPI4.actionHandler`.
 * The menu handles user interactions, manages different states (expanded, collapsed, locked),
 * and integrates with other Heurist components like saved searches (SVS) and faceted search.
 *
 * @namespace heurist.slidersMenu
 * @property {object} options - Configuration options for the widget. Currently empty.
 *
 * @property {Array<string>} sections - Defines the names of the menu sections (e.g., 'design', 'populate').
 * @property {object} menues - Stores jQuery objects for each section's menu panel. `{[sectionName]: jQueryElement}`.
 * @property {object} containers - Stores jQuery objects for each section's main content container. `{[sectionName]: jQueryElement}`.
 * @property {object} introductions - Stores jQuery objects for each section's introductory/help panel. `{[sectionName]: jQueryElement}`.
 * @property {number} _myTimeoutId - Timeout ID for delaying main menu panel collapse.
 * @property {number} _myTimeoutId2 - Timeout ID for delaying explore popup menu close.
 * @property {number} _myTimeoutId3 - Timeout ID for delaying explore popup menu show.
 * @property {number} _myTimeoutId5 - Timeout ID for preventing main menu expansion (e.g., after search).
 * @property {number} _delayOnCollapseMainMenu - Delay in ms for collapsing the main menu.
 * @property {number} _delayOnCollapse_ExploreMenu - Delay in ms for collapsing the explore menu popup.
 * @property {number} _delayOnShow_ExploreMenu - Delay in ms for showing the explore menu popup.
 * @property {number} _delayOnShow_AddRecordMenu - Delay in ms for showing the 'Add Record' related popup.
 * @property {number} _widthMenu - Width in pixels of the expanded main menu.
 * @property {boolean} _is_prevent_expand_mainmenu - Flag to temporarily prevent main menu expansion.
 * @property {boolean} _explorer_menu_locked - Flag indicating if the explore menu popup is locked open (e.g., due to an open selectmenu).
 * @property {?string} _active_section - Name of the currently active/visible main section.
 * @property {?string} _current_explore_action - Name of the currently active action within the explore popup (e.g., 'searchBuilder').
 * @property {?jQuery} divMainMenu - jQuery object for the main collapsible side menu container.
 * @property {?object} currentSearch - Stores the last search query object, used for "Save Filter".
 * @property {boolean} reset_svs_edit - Flag to indicate if the saved search edit dialog should be reset.
 * @property {?jQuery} svs_list - jQuery object for the saved searches list widget instance.
 * @property {?jQuery} coverAll - jQuery object for an overlay div used to cover the page content when menus are active.
 * @property {?jQuery} menues_explore_popup - jQuery object for the popup panel associated with the explore menu.
 * @property {?jQuery} menues_explore_gap - jQuery object for a small gap element, possibly for styling or event handling.
 * @property {?jQuery} search_faceted - jQuery object for the faceted search container.
 * @property {?HSvsEdit} edit_svs_dialog - Instance of the HSvsEdit class for managing saved search editing.
 * @property {number} _left_position - Base left position for the main menu (collapsed state), adjusted for language (e.g., German).
 * @property {boolean} _show_quick_tips - Flag to control showing quick tips on first interaction with explore menu.
 * @property {object} _menu_colours - Defines background colors for different sections. `{[sectionName]: colorString}`.
 */
$.widget( "heurist.slidersMenu", {

    // default options
    options: {
        // No options defined by default.
    },

    sections: ['design','populate','explore','publish','admin'],

    menues:{}, //section menu - div with menu actions
    containers:{}, //operation containers (next to section menu)
    introductions:{}, //context help containers

    _myTimeoutId: 0,  //delay on collapse main menu (_expandMainMenuPanel/_collapseMainMenuPanel)

    _myTimeoutId2: 0, //delay on close explore popup menu
    _myTimeoutId3: 0, //delay on show explore popup menu

    _myTimeoutId5: 0, //delay on prevent expand main menu (after search)

    _delayOnCollapseMainMenu: 800,
    _delayOnCollapse_ExploreMenu: 600,

    _delayOnShow_ExploreMenu: 500,
    _delayOnShow_AddRecordMenu: 500,

    _widthMenu: 170, //left menu

    _is_prevent_expand_mainmenu: false,
    _explorer_menu_locked: false,
    _active_section: null,
    _current_explore_action: null,

    divMainMenu: null,  //main div

    currentSearch: null,
    reset_svs_edit: true,

    svs_list: null,

    coverAll: null,

    menues_explore_popup: null,  //popup next to explore menu (filters)
    menues_explore_gap: null,
    search_faceted: null,
    edit_svs_dialog: null,

    _left_position: 91, //normal width for most languages, for German it is 115

    _show_quick_tips: false, // popup quick tips on next moving to the explore menu

    _menu_colours: {
        admin: '#676E80',
        design: '#523365',
        populate: '#307D96',
        explore: '#4477B9',
        publish: '#627E5D'
    },

    /**
     * The widget's constructor. Initializes the main menu element, loads its HTML structure,
     * sets up sections, binds event listeners for menu interactions, and handles initial state
     * based on URL parameters (e.g., 'welcome').
     * This method is called by jQuery UI when the widget is created.
     * @memberof heurist.slidersMenu
     * @private
     */
    _create: function() {

        let that = this;

        //make it wider for Deutsch
        this._left_position = ('de'== window.hWin.HAPI4.getLocale())?115:91;


        this.element.addClass('ui-menu6')
        .addClass('selectmenu-parent')
        .disableSelection();// prevent double click to select text

        this.coverAll = $('<div>').addClass('coverall-div-bare')
            .css({'background-color': '#000', opacity: '0.6',zIndex:102,
            filter: 'progid:DXImageTransform.Microsoft.Alpha(opacity=60)'})
            .hide()
            .appendTo( this.element );

        this.divMainMenu = $('<div>')
        .addClass('slidersMenu')
        .css({position:'absolute',width: (this._left_position+'px'),
                top:'2px',left:'0px',bottom:'4px',
                cursor:'pointer','z-index':104})
        .appendTo( this.element );

        this.divMainMenu.load(
            window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/slidersMenu.html',
            function(){

                window.hWin.HAPI4.HRA(that.divMainMenu);

                //init all menues
                $.each(that.sections, function(i, section){
                    that._loadSectionMenu(section);
                    that._initIntroductory(section);
                });

                //explore menu in main(left) menu  -  quicklinks
                that._on(that.divMainMenu.children('.ui-heurist-quicklinks').find('li.menu-explore, div.menu-explore'),
                {
                    mouseenter: that._expandMainMenuPanel,
                    mouseleave: that._collapseMainMenuPanel,
                });
                that._on(that.divMainMenu.children('.ui-heurist-quicklinks'),{
                    mouseleave: that._collapseMainMenuPanel,
                });
                
                that._on(that.divMainMenu.find('.ui-heurist-header'),{
                    click: that._openSectionMenu
                });
                
                const urlParams = new URLSearchParams(window.hWin.location.search);
                
                if(urlParams.has('welcome')){ //window.hWin.HEURIST4.util.getUrlParameter('welcome')
                    //open explore by default, or "design" if db is empty
                    that._active_section = 'explore';
                    that.switchContainer( 'design' );
                    that._loadStartHints();
                    that._show_quick_tips = true;
                }else{
                    that.switchContainer( 'explore' );
                }

                //init menu items in ui-heurist-quicklinks
                //init ui-heurist-quicklinks menu items
                that._on(that.divMainMenu.find('.menu-explore'),{
                    mouseenter: that._mousein_ExploreMenu,
                    mouseleave: function(e){
                        if($(e.target).parent('#filter_by_groups').length==0){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            
                            if (!this._isCurrentActionFilter()) { //close on mouse exit
                            
                                //this._resetCloseTimers();//reset
                                this._myTimeoutId2 = setTimeout(function(){
                                            that._closeExploreMenuPopup();
                                        },  this._delayOnCollapse_ExploreMenu); //600
                            }
                        }
                    }
                });
                that._on(that.divMainMenu.find('li[data-action-popup="recordAddSettings"]'), {
                    click: that._mousein_ExploreMenu
                });
                that._on(that.divMainMenu.find('#filter_by_groups'),{ //, #filter_by_groups
                    mouseenter: that._mousein_ExploreMenu,
                    mouseleave: function(e){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            //this._resetCloseTimers();//reset
                            this._myTimeoutId2 = setTimeout(function(){
                                        that._closeExploreMenuPopup();
                                    },  this._delayOnCollapse_ExploreMenu); //600
                    }
                });
                that._on(that.menues['explore'].find('li[data-action-popup="databaseOverview"]'), {
                    click: that.showDatabaseOverview
                });
                
                //forcefully hide coverAll on click
                that._on(that.coverAll, {
                    click: function(){
                            that._closeExploreMenuPopup();
                            that._collapseMainMenuPanel(true);
                    }
                });
                
                that._on(window.hWin.document, { //that.element
                    click: function(e){
                        //get current filter dialog
                        let ele = $('.save-filter-dialog:visible')
                        if(ele.length>0){
                                if (ele.parents('.ui-menu')) return;
                            
                                let prnt = ele.parent();
                                if(prnt.hasClass('ui-dialog') || prnt.hasClass('ui-menu6-section')){
                                    ele = prnt;
                                }
                                let x = e.pageX;
                                let y = e.pageY;
                                let x1 = $(ele).offset().left;
                                let y1 = $(ele).offset().top;
                                
                                if(x>0 && y>0){
                                if(x<x1 || x>x1+$(ele).width() ||
                                   y<y1 || y>y1+$(ele).height())
                                {
                                   that._closeExploreMenuPopup();
                                }
                                }
                        }                        
                    }
                });
                    
/*                
                that._on(that.divMainMenu.find('.menu-explore[data-action-onclick="svsAdd"]'), 
                {click: function(e){
                    that.addSavedSearch();
                }});
*/                
        });
        
        //find all saved searches for current user
        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){  
            window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    }
            });
        }
        
       
        
        //fix bug for tinymce popups - it lost focus if it is called from dialog
        $(window.hWin.document).on('focusin', function(e) {
            if ($(e.target).closest(".tox-tinymce-aux").length) {
                e.stopImmediatePropagation();
            }
        });

        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                +' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT
                +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS, 
            function(e, data) {
                
                if(e.type == window.hWin.HAPI4.Event.ON_CUSTOM_EVENT){
                    if(data?.userWorkSetUpdated){
                            that._refreshSubsetSign();
                    }
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                    
                    that._onSearchStart(data);
                    
                }
                else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    that._onSearchFinish(data);
                    
                }
                else if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE || e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS){
                    
                    that._onPreferencesChange(e, data);

                }
                else if(!data || data.type != 'ulf'){  //ON_STRUCTURE_CHANGE
                    //refresh list of rectypes after structure edit
                    that._updateDefaultAddRectype();
                    window.hWin.HEURIST4.browseRecordCache = {};
                    window.hWin.HEURIST4.browseRecordTargets = {};
                }
        });
        
    }, //end _create

    /**
     * Handles changes to preferences or credentials.
     * Updates the default "Add Record" type display and repopulates favorite filters if necessary.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} e - The jQuery event object.
     * @param {object} [data] - Data associated with the event.
     * @param {string} [data.origin] - Origin of the preference change (e.g., 'recordAdd').
     * @param {Array} [data.preferences] - New preferences, if origin is 'recordAdd'.
     * @param {boolean} [data.refresh_favourites] - Flag to force refresh of favorite filters.
     */
    _onPreferencesChange: function (e, data){

        if(data?.origin=='recordAdd'){
            this._updateDefaultAddRectype( data.preferences );
        }else{
            this._updateDefaultAddRectype();
        }
        if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS || data?.refresh_favourites){
            this.populateFavouriteFilters();
        }
    },

    /**
     * Handles the completion of a record search.
     * Hides the cover-all overlay and updates the "Save Filter" button state based on search results.
     * Refreshes the subset indicator.
     * @memberof heurist.slidersMenu
     * @private
     * @param {object} [data] - Data associated with the search finish event.
     * @param {object} [data.request] - The original search request.
     * @param {boolean} [data.request.ispreview] - If the search was a preview.
     * @param {boolean} [data.request.increment] - If the search was incremental.
     * @param {string} [data.request.search_realm] - The realm of the search.
     * @param {HRecordSet} [data.recordset] - The recordset returned by the search.
     */
    _onSearchFinish: function (data){

        if(data?.request && (data.request.ispreview || data.request.increment || data.request.search_realm)) return;

        this.coverAll.hide();
        // window.hWin.HAPI4.currentRecordset is the same as data.recordset
        if(data.recordset && data.recordset.length()>0){
            this._updateSaveFilterButton(2);
        }else{
            this._updateSaveFilterButton(0);
        }

        this._refreshSubsetSign();
    },

    /**
     * Handles the start of a record search.
     * Updates the current search query for "Save Filter" functionality and updates the button state.
     * May switch the view to the 'explore' container.
     * @memberof heurist.slidersMenu
     * @private
     * @param {object} [data] - Data associated with the search start event.
     * @param {boolean} [data.ispreview] - If the search is a preview.
     * @param {boolean} [data.increment] - If the search is incremental.
     * @param {string} [data.search_realm] - The realm of the search.
     * @param {boolean} [data.no_menu_switch] - If true, prevents automatic switching to the explore menu.
     * @param {boolean} [data.reset] - If true, resets the current search.
     */
    _onSearchStart: function (data){
        //not need to check realm since this widget the only per instance
        if(data?.ispreview || data?.increment || data?.search_realm) return;

        // Check whether to block auto switch to explore menu
        let move_to_explore = !data.no_menu_switch;
        if(Object.hasOwn(data, 'no_menu_switch')){
            delete data.no_menu_switch;
            if (window.hWin.HEURIST4.current_query_request) { // Ensure current_query_request exists
                delete window.hWin.HEURIST4.current_query_request.no_menu_switch;
            }
        }

        this.reset_svs_edit = true;
        if(!data?.reset){
            //keep current search for "Save Filter"
            this.currentSearch = window.hWin.HEURIST4.util.cloneJSON(data);
            this._updateSaveFilterButton(1);

            if(move_to_explore){
                this.switchContainer('explore');
                this._mouseout_SectionMenu(); // Call without event to close other sections
                this._collapseMainMenuPanel(true, 1000);
            }

        }else if(data?.reset){
            this.currentSearch = null;
            this._updateSaveFilterButton(0);
        }
    },

    /**
     * Checks if the current action in the explore menu is related to filtering.
     * @memberof heurist.slidersMenu
     * @private
     * @returns {boolean} True if a filter-related action is active, false otherwise.
     */
    _isCurrentActionFilter: function(){
            return (this._current_explore_action=='searchBuilder' ||
                    this._current_explore_action=='svsAdd' ||
                    this._current_explore_action=='svsAddFaceted' );
    },

    /**
     * Updates the state and appearance of the "Save Filter" button.
     * @memberof heurist.slidersMenu
     * @private
     * @param {number} mode - The state to set:
     *                        0: Disabled (no active search or results).
     *                        1: In progress (search is running).
     *                        2: Ready to save (search complete with results, button pulsates).
     */
    _updateSaveFilterButton: function( mode ){

        let btn = this.divMainMenu.find('.menu-explore[data-action-popup="svsAdd"]');

        if(mode==0){ //disabled
            btn.find('span.ui-icon')
                .removeClass('ui-icon-loading-status-lines rotate')
                .addClass('ui-icon-filter-plus');
            window.hWin.HEURIST4.util.setDisabled(btn, true);

        }else if(mode==1){ //search in progress
            btn.find('span.ui-icon')
                .removeClass('ui-icon-filter-plus')
                .addClass('ui-icon-loading-status-lines rotate');
            window.hWin.HEURIST4.util.setDisabled(btn, true); // Typically disabled during search
        }else{ // mode == 2
            window.hWin.HEURIST4.util.setDisabled(btn, false);
            btn.find('span.ui-icon')
                .removeClass('ui-icon-loading-status-lines rotate')
                .addClass('ui-icon-filter-plus');

            let that = this;
            // Ensure effect is applied only if element is visible and effect method exists
            if (btn.is(':visible') && typeof btn.effect === 'function') {
                 btn.effect( 'pulsate', null, 4000, function(){
                    // Ensure divMainMenu exists before accessing its width
                    let paddingVal = that.divMainMenu && (that.divMainMenu.width() === that._widthMenu) ? 16 : 30;
                    btn.css({'padding':'6px 2px 6px ' + paddingVal + 'px'});
                });
            }
        }
    },

    /**
     * Updates the label and behavior of the "Add Record" link/button based on default record type preferences.
     * Also manages visibility of the bookmarks section in saved filters.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Array<number|string>} [preferences] - Optional array of preferences, where the first element is the default record type ID.
     *                                  If not provided, preferences are fetched using `HAPI4.get_prefs`.
     */
    _updateDefaultAddRectype: function( preferences ){

        //show/hide bookmarks section in saved filters list
        let bm_on = (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1');
        let ele_bookmark = this.divMainMenu.find('.menu-explore[data-action-popup="svs_list"][data-id="bookmark"]');
        if(bm_on) ele_bookmark.show();
        else ele_bookmark.hide();

        let prefs = preferences || window.hWin.HAPI4.get_prefs('record-add-defaults');
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(prefs)){
            return;
        }

            let rty_ID = prefs[0];
            let rty_Name = $Db.rty(rty_ID,'rty_Name'); // Cache for repeated use

            let ele = this.element.find('li[data-action-popup="recordAdd"]');

            if(ele.length > 0){
                if(rty_ID > 0 && rty_Name){
                    ele.find('.newrec-text').html(window.hWin.HR('add_new_record2')).css('font-size', '');
                    ele.find('.rectype-name').html('[<i>'+window.hWin.HEURIST4.util.htmlEscape(rty_Name)+'</i>]');
                    ele.css('width', ''); // Reset width

                    ele.attr('data-id', rty_ID);
                    ele.attr('title', 'New ' + window.hWin.HEURIST4.util.htmlEscape(rty_Name) + ' record');
                    this._off(ele, 'click'); // Remove previous handler before adding new
                    this._on(ele, {click: function(e){
                        let clicked_ele = $(e.target).is('li')?$(e.target):$(e.target).parents('li');
                        let current_rty_ID = clicked_ele.attr('data-id');

                        this._collapseMainMenuPanel(true);
                        setTimeout(function() { // Use function for setTimeout for better practice
                            window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:{RecTypeID: current_rty_ID}});
                        },200);
                    }});
                }else{
                    ele.find('.newrec-text').text('New record').css('font-size', '10px');
                    ele.find('.rectype-name').text('');
                    ele.css('width', '85px');

                    ele.attr('data-id','');
                    this._off(ele, 'click'); // Ensure no handler if no valid rty_ID
                }
            }
            // Update for 'populate' section menu as well, if it exists
            if (this.menues['populate']) {
                ele = this.menues['populate'].find('li[data-action-popup="recordAdd"]');
                if(ele.length > 0){
                    if(rty_ID > 0 && rty_Name){
                        ele.find('span.menu-text').html(`New <i>${window.hWin.HEURIST4.util.htmlEscape(rty_Name)}</i>`);
                    }else{
                        ele.find('span.menu-text').html(`New record`);
                    }
                }
            }
    },

    /**
     * Placeholder for a refresh method.
     * Remark: This method is currently empty and does not perform any actions.
     * @memberof heurist.slidersMenu
     * @private
     */
    _refresh: function(){
        // Could be used to re-evaluate menu item visibility or states if needed.
    },

    /**
     * Cleans up the widget when it is destroyed.
     * Removes the main menu element, associated dialogs (saved search, faceted search),
     * and unbinds global event listeners.
     * This method is called by jQuery UI when the widget is destroyed.
     * @memberof heurist.slidersMenu
     * @private
     */
    _destroy: function() {

        if (this.divMainMenu) this.divMainMenu.remove();

        if(this.edit_svs_dialog && typeof this.edit_svs_dialog.remove === 'function') { // Assuming HSvsEdit might have a remove/destroy method
             // this.edit_svs_dialog.remove(); // Or .destroy()
        } else if (this.edit_svs_dialog && this.edit_svs_dialog.dialog_obj && typeof this.edit_svs_dialog.dialog_obj.remove === 'function') {
            this.edit_svs_dialog.dialog_obj.remove(); // If it's a jQuery dialog wrapper
        }
        this.edit_svs_dialog = null;


        if(this.search_faceted && typeof this.search_faceted.remove === 'function') this.search_faceted.remove();
        this.search_faceted = null;

        // Remove all section menus and containers
        $.each(this.sections, (i, section) => {
            if (this.menues[section]) {
                this.menues[section].remove();
                this.menues[section] = null;
            }
            if (this.containers[section]) {
                this.containers[section].remove();
                this.containers[section] = null;
            }
            if (this.introductions[section]) {
                this.introductions[section].remove();
                this.introductions[section] = null;
            }
        });
        if (this.menues_explore_popup) this.menues_explore_popup.remove();
        if (this.menues_explore_gap) this.menues_explore_gap.remove();
        if (this.coverAll) this.coverAll.remove();


        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                +' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT // Added ON_CUSTOM_EVENT as it was in _create
                +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS);
        $(window.hWin.document).off('focusin'); // Remove focusin handler
    },

    /**
     * Checks if the explore menu popup should remain open (locked).
     * This can happen if a selectmenu within it is open, a tag selector is visible,
     * or a modal dialog overlay is active.
     * @memberof heurist.slidersMenu
     * @private
     * @returns {boolean} True if the explore menu is considered locked, false otherwise.
     */
    _isExplorerMenu_locked: function(){

        return (this._explorer_menu_locked
                || this.element.find('.ui-selectmenu-open').length>0 // check within this widget instance
                || $('.list_div').is(':visible')      //tag selector dropdown (global check)
                || $('.ui-widget-overlay.ui-front').is(':visible')   //some modal dialog is open (global check)
                );
    },

    /**
     * Collapses the main menu panel (quick links) to its narrow state.
     * Can be instant or animated. Can be forced to prevent re-expansion temporarily.
     * Hides the coverAll overlay and the explore menu gap.
     * Resets styles of quick links and section headers.
     * @memberof heurist.slidersMenu
     * @private
     * @param {boolean} [is_instant=false] - If true, collapse instantly; otherwise, animate.
     * @param {number} [is_forcefully=0] - If greater than 0, prevents main menu expansion for this duration (ms)
     *                                     and collapses instantly if `_myTimeoutId` was set.
     */
    _collapseMainMenuPanel: function(is_instant, is_forcefully) {

        let that = this;
        if(is_forcefully>0){
            this._is_prevent_expand_mainmenu = true;
            // Clear any existing timeout for _is_prevent_expand_mainmenu
            if (this._myTimeoutId5) clearTimeout(this._myTimeoutId5);
            this._myTimeoutId5 = setTimeout(function() { that._is_prevent_expand_mainmenu = false; },is_forcefully);
        }else if(this._isExplorerMenu_locked() ){
            return;
        }

        if(is_instant && this._myTimeoutId>0){
            clearTimeout(this._myTimeoutId);
            this._myTimeoutId = 0; // Ensure it's reset
        }
        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;

        // Clear existing timeout for collapsing, to prevent multiple queued collapses
        if (this._myTimeoutId) clearTimeout(this._myTimeoutId);

        this._myTimeoutId = setTimeout(function() {
            that._myTimeoutId = 0;

            if (that.coverAll) that.coverAll.hide(); // Check if elements exist

            if(that.menues_explore_gap) that.menues_explore_gap.hide();

            if (!that.divMainMenu) return; // Guard if main menu doesn't exist

            that.divMainMenu.find('li.menu-explore, div.menu-explore').css('background','none'); // remove leftover highlight
            that.divMainMenu.find('.rectype-name').css({'width': '80px', 'max-width': '80px', 'margin-left': '10px'});

            that.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]').css('padding', '6px 20px 6px 0px');
            that.divMainMenu.find('.menu-explore[data-action-popup="recordAddSettings"]').css({padding:'6px 2px 6px 0px', width: '85px'});

            that.divMainMenu.find('.ui-heurist-quicklinks').css({'text-align':'center'});

            $.each(that.divMainMenu.find('.section-head'), function(i,ele){
                ele = $(ele);
                ele.css({'padding-left': ele.attr('data-pl')+'px'}); // Assumes data-pl is always set
            });

            that.divMainMenu.find('#svs_list').hide();

            if(that.divMainMenu.width()>that._left_position) {
                that.divMainMenu.stop().effect('size',  { to: { width: that._left_position } },
                    is_instant===true?10:300, function(){
                    if (that.divMainMenu) that.divMainMenu.css({bottom:'4px',height:'auto'}); // Check again
                    that._closeExploreMenuPopup();
                });
            } else if (is_instant) { // If already collapsed or collapsing instantly
                 that._closeExploreMenuPopup();
            }


            if (that._active_section && that.menues[that._active_section])
            {
                that.menues[that._active_section].css({left:(that._left_position+5)});
            }

            that._switch_SvsList( 0 );

        }, is_instant===true?10:this._delayOnCollapseMainMenu);
    },

    /**
     * Expands the main menu panel (quick links) to its wider state.
     * Shows the coverAll overlay. Adjusts styles of quick links and section headers.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} e - The mouseenter event object.
     */
    _expandMainMenuPanel: function(e) {

        if(this._is_prevent_expand_mainmenu) return;

        clearTimeout(this._myTimeoutId); //terminate collapse
        this._myTimeoutId = 0;

        if (!this.divMainMenu) return; // Guard
        if(this.divMainMenu.width()==this._widthMenu) return; // already expanded

        if (this.coverAll) this.coverAll.show(); // Check if element exists

        let that = this;
        this._mouseout_SectionMenu(); // Call without event
        this.divMainMenu.stop().effect('size',  { to: { width: that._widthMenu } }, 300,
            function(){
                if (!that.divMainMenu) return; // Guard inside callback
                that.divMainMenu.find('ul').css({'padding-right':'0px'});
                that.divMainMenu.find('.ui-heurist-quicklinks').css({'text-align':'left'});
                that.divMainMenu.find('.section-head').css({'padding-left':'12px'});

                that.divMainMenu.find('.rectype-name').css({'width': '', 'max-width': '', 'margin-left': '0px'});

                that.divMainMenu.css({bottom:'4px',height:'auto'});

                that.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]').css('padding', '6px 2px 6px 16px');
                that.divMainMenu.find('.menu-explore[data-action-popup="recordAddSettings"]').css({padding:'6px 2px 6px 16px', width: ''});

                that.divMainMenu.find('#filter_by_groups').hide();
                that._switch_SvsList( 1 );

                if (that._active_section && that.menues[that._active_section] &&
                    !window.hWin.HEURIST4.ui.isVisible(that.containers[that._active_section]) )
                {
                    that.menues[that._active_section].css({left:that._widthMenu+5});
                }
            });
    },

    /**
     * Handles mouseout events from section menus or the explore menu popup.
     * Clears timers and potentially closes menus if not locked.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} [e] - The mouseleave event object. If not provided, assumes a programmatic close.
     */
    _mouseout_SectionMenu: function(e) {

        if( this._isExplorerMenu_locked() ) return;

        let that = this;

        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;

        function __closeAllsectionMenu() {

            let section_name = that._getSectionName(e); // Can be null if e is undefined
            if(that._active_section !== section_name) // If different or section_name is null
            {
                $.each(that.sections, function(i, section){
                    if(that._active_section !== section){ // Close all *other* inactive sections
                        that._closeSectionMenu(section);
                    }
                });
                that._closeExploreMenuPopup(); // Always close explore popup if we are here
            }
        }

        if(e){ // Called from a mouse event
            this._resetCloseTimers();
            if (!this._isCurrentActionFilter()){
                 if (this._myTimeoutId2) clearTimeout(this._myTimeoutId2);
                this._myTimeoutId2 = setTimeout(function(){
                                                that._closeExploreMenuPopup();
                                                that._collapseMainMenuPanel(); // This will also call _closeExploreMenuPopup
                                        },  this._delayOnCollapse_ExploreMenu);
            }
        }else{ // Called programmatically
            __closeAllsectionMenu();
        }
    },

    /**
     * Resets (clears) timeouts related to closing the explore menu popup and collapsing the main menu.
     * @memberof heurist.slidersMenu
     * @private
     */
    _resetCloseTimers: function(){
        if (this._myTimeoutId2) clearTimeout(this._myTimeoutId2); this._myTimeoutId2 = 0;
        if (this._myTimeoutId) clearTimeout(this._myTimeoutId); this._myTimeoutId = 0;
    },

    /**
     * Handles mouseenter events on explore menu items (in quick links or section menu).
     * If not locked, it resets close timers, highlights the item, and potentially shows
     * the explore menu popup via `show_ExploreMenu` if the item has a `data-action-popup`.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} e - The mouseenter event object.
     */
    _mousein_ExploreMenu: function(e) {

        if( this._isExplorerMenu_locked() ) return;
        this._explorer_menu_locked = false; // Reset lock specific to this interaction path

        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;

        this._resetCloseTimers();

        if (this.divMainMenu) this.divMainMenu.find('li.menu-explore, div.menu-explore').css('background','none');

        const target = $(e.target);
        const ele = target.is('li, div.menu-explore') ? target : target.parents('li, div.menu-explore').first(); // Ensure only one parent
        let hasAction = ele?.attr('data-action-popup');

        if (target.attr('id') === 'filter_by_groups' || hasAction === 'search_recent' || hasAction === 'databaseOverview') {
            return;
        }

        if (ele?.parents('.ui-heurist-quicklinks').length > 0) {
            ele.css('background', 'aliceblue');
        }

        if (target.parents('.ui-heurist-quicklinks').length==0) { // If event is from section menu, not quick links
            this._collapseMainMenuPanel(true); // close main menu instantly
        }

        if(hasAction){
            this.show_ExploreMenu(e);
        } else {
            let that = this;
             if (this._myTimeoutId2) clearTimeout(this._myTimeoutId2);
            this._myTimeoutId2 = setTimeout(function(){
                                        that._closeExploreMenuPopup();
                                    },  this._delayOnCollapse_ExploreMenu);
        }
    },

    /**
     * Gets the appropriate delay for showing a menu, based on the action name.
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} action_name - The name of the action (e.g., 'recordAdd').
     * @param {jQuery} [menu_item] - The jQuery object for the menu item, used to check data-id for 'recordAdd'.
     * @returns {number} The delay in milliseconds.
     */
    _getDelay: function getDelay(action_name, menu_item) {
        return action_name === 'recordAdd' && menu_item?.attr('data-id') > 0
            ? this._delayOnShow_AddRecordMenu
            : this._delayOnShow_ExploreMenu;
    },

    /**
     * Gets the menu item element from a mouse event.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} e - The mouse event object.
     * @param {string} [action_name] - If provided, implies menu_item is already known or not needed from event.
     * @returns {?jQuery} The menu item jQuery element, or null.
     */
    _getMenuItem: function (e, action_name) {
        if (!action_name) { // Only get from event if action_name is not pre-determined
            const target = $(e.target);
            return target.is('li, div.menu-explore') ? target : target.parents('li, div.menu-explore').first();
        }
        return null;
    },

    /**
     * Shows a popup panel associated with an explore menu action (e.g., search builder, saved filters).
     * Determines the action, calculates position, initializes content if needed, and displays the panel.
     * @memberof heurist.slidersMenu
     * @param {Event} e - The mouse event that triggered this action.
     * @param {string} [action_name] - The specific action to show. If not provided, it's derived from `e.target`.
     * @param {object} [position] - Optional explicit position for the popup. `{top: number, left: number}`.
     */
    show_ExploreMenu: function(e, action_name, position) {

        let menu_item = this._getMenuItem(e, action_name); // menu_item can be null if action_name is provided

        action_name = menu_item?.attr('data-action-popup') || action_name;

        if(this._current_explore_action==action_name && this.menues_explore_popup && this.menues_explore_popup.is(':visible')) return; // Avoid re-opening if already current and visible

        const that = this;
        let expandRecordAddSetting = false;
        if (action_name === 'recordAddSettings') {
            action_name = 'recordAdd';
            expandRecordAddSetting = e.type === 'click'; // Only expand if it was a click
        }


        const delay = this._getDelay(action_name, menu_item);

        if (!this.menues_explore_popup) return; // Guard against missing popup element

        if(action_name != 'recordAdd'){
            this.menues_explore_popup
                    .removeClass('ui-heurist-populate record-addition')
                    .addClass('ui-heurist-explore');
        }

        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;

        this._myTimeoutId3 = setTimeout(
        function(){
            if (!that.menues_explore_popup) return; // Guard inside timeout

            that._current_explore_action = action_name;
            that.hideDatabaseOverview();

            let cont = that.menues_explore_popup.find('#'+action_name);
            if(cont.length==0){
                cont = $('<div id="'+action_name+'" class="explore-widgets">').appendTo(that.menues_explore_popup);
            }

            that.menues_explore_popup.find('.explore-widgets').finish().hide();

            if(action_name!='svsAdd'){
                that.closeSavedSearch();
            }
            if(action_name!='svsAddFaceted'){
                that.closeFacetedWizard();
            }

            let { explore_top, explore_left, explore_height, explore_width } = that._getMenuPosition(menu_item, action_name, position);

            if(action_name=='svsAdd'){
                that._closeExploreMenuPopup(); // Close generic popup before opening specific dialog
                that.addSavedSearch( 'saved', false, explore_left, explore_top );
                return;
            }
            else if(action_name=='svsAddFaceted'){
                that._closeExploreMenuPopup();
                that.addSavedSearch( 'faceted', false, explore_left ); // top_position not used by this path currently
                return;
            }

            that._handleActionInit(action_name, cont, expandRecordAddSetting);

            that.menues_explore_popup.css({
                left: explore_left,
                top: explore_top,
                height: explore_height,
                width: expandRecordAddSetting?'500px':explore_width, // Width might need adjustment based on content
                'z-index': 103, // Ensure it's above main menu but potentially below modal dialogs
                overflow: action_name === 'searchBuilder' ? 'hidden' : 'hidden auto', // scroll for builder can be an issue
            }).show();

            // Adjust if it overflows the viewport horizontally
            if (that.element && that.menues_explore_popup.offset() && that.menues_explore_popup.outerWidth()) { // Check existence
                let right_edge = that.menues_explore_popup.offset().left + that.menues_explore_popup.outerWidth();
                if(right_edge > that.element.innerWidth()){
                        explore_left = Math.max(0, that.element.innerWidth() - that.menues_explore_popup.outerWidth());
                        that.menues_explore_popup.css({ left: explore_left });
                }
            }

            cont.fadeIn(delay+200, function(){ // Use a slightly longer fadeIn to ensure visibility
                let current_action_id = $(this).attr('id'); // Use a different var name
                if (that.menues_explore_popup) { // Check again
                    that.menues_explore_popup.find('.explore-widgets[id!="'+current_action_id+'"]').hide();
                }
                if(current_action_id=='searchByEntity' && $(this).data('heuristSearchByEntity')){ // Check instance
                    $(this).searchByEntity('refreshOnShow');
                }
            });

        }, delay);

    },

    /**
     * Calculates the position for the explore menu popup.
     * @memberof heurist.slidersMenu
     * @private
     * @param {jQuery} [menu_item] - The menu item that triggered the popup.
     * @param {string} action_name - The name of the action for which the popup is shown.
     * @param {object} [position] - Explicit position override `{top: number, left: number}`.
     * @returns {object} Position object: `{ explore_top, explore_left, explore_height, explore_width }`.
     */
    _getMenuPosition: function(menu_item, action_name, position){

        let explore_left = (this.divMainMenu && (this.divMainMenu.width()>this._left_position)?this._widthMenu:this._left_position)+4;
        let explore_top = '2px'; // Default as string for CSS
        let explore_height = 'auto';
        let explore_width = '300px'; // Default width

        if (action_name === 'searchBuilder') {
            explore_height = 450; // Fixed height for search builder
            explore_width = '850px';
        }

        if(position){ // Explicit position provided
            explore_top = position.top;
            // explore_height = position.left; // This seems like a typo, height should be height. Assuming it meant to use position.height or it's a fixed value.
                                          // For now, will let the default or action_name specific height take precedence.
            return { explore_top, explore_left, explore_height, explore_width };
        }

        const qlinks_cnt = menu_item ? menu_item.parents('.ui-heurist-quicklinks').length : -1;
        if (qlinks_cnt === 0 && this._active_section &&
                (this._active_section === 'explore' || this._active_section === 'populate'))
        { // Triggered from section menu item when explore or populate is active
            explore_left = this._left_position + 211; // Position next to section menu
        } else if (qlinks_cnt === 1) { // Triggered from quick links
            explore_left = this._widthMenu + 4; // Position next to expanded main menu
        }


        if (action_name === 'searchBuilder') {
            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
            if(widget && widget.length > 0 && widget.is(':visible')){ // Ensure widget exists and is visible
                explore_top = widget.position().top + 100;
            }else if(menu_item && menu_item.length > 0){ // Fallback to menu item's position
                explore_top = menu_item.offset().top;
            } else {
                 explore_top = 50; // Default if no reference point
            }

            if(this.element.innerHeight()>0 && (explore_top + explore_height > this.element.innerHeight())){
                explore_top = Math.max(0, this.element.innerHeight() - explore_height - 10); // 10px buffer
            }
        }

        explore_top = Math.max(0, parseFloat(explore_top)); // Ensure top is not negative

        return { explore_top: explore_top + 'px', explore_left, explore_height, explore_width };
    },

    /**
     * Initializes the content/widget for a specific action within the explore menu popup.
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} action_name - The name of the action to initialize (e.g., 'searchByEntity', 'searchBuilder').
     * @param {jQuery} cont - The jQuery container element for this action's widget.
     * @param {boolean} expandRecordAddSetting - Specific flag for 'recordAdd' action to show settings expanded.
     */
    _handleActionInit: function(action_name, cont, expandRecordAddSetting){

            let that = this;

            if(action_name=='searchByEntity'){
                if(!cont.data('heuristSearchByEntity')) { // Check instance using .data()
                    cont.searchByEntity({use_combined_select:true,
                        // mouseover: function(){that._resetCloseTimers()}, // NOT USED currently
                        onClose: function() {
                                that.switchContainer('explore');
                        },
                        menu_locked: function(is_locked, is_mouseleave){
                            if(!is_mouseleave){ // only act if not due to mouseleave from the locking element itself
                                that._resetCloseTimers();
                                that._explorer_menu_locked = is_locked;
                            }
                        }
                    });
                }
            }
            else if(action_name=='searchBuilder'){
                if(!cont.data('heuristSearchBuilder')){
                    this.search_builder = cont.searchBuilder({ // Store instance if needed elsewhere
                        is_h6style: true,
                        onClose: function() { that._closeExploreMenuPopup(); },
                        menu_locked: function(is_locked, is_mouseleave){
                            if(!is_mouseleave){
                                that._resetCloseTimers();
                                if(is_locked=='delay'){ // Special case for searchBuilder datepicker
                                    if (that.coverAll) that.coverAll.show();
                                    that._delayOnCollapse_ExploreMenu = 2000; // Longer delay
                                }else{
                                    that._explorer_menu_locked = is_locked;
                                }
                            }
                    }  });

                    cont.addClass('save-filter-dialog'); // Mark this container
                }else{
                    cont.searchBuilder('refreshRectypeMenu'); // Refresh if instance exists
                }
            }
            else if(action_name=='search_filters' || action_name=='search_rules'){ //list of saved filters
                that._init_SvsList(cont, (action_name=='search_rules')?2:1); // mode 2 for rules, 1 for filters
                if (that.menues_explore_popup) that.menues_explore_popup.css({bottom:'4px'});
            }
            else if(action_name=='recordAdd'){
                if (that.menues_explore_popup) {
                    that.menues_explore_popup
                        .css({bottom:'4px'})
                        .removeClass('ui-heurist-explore').addClass('ui-heurist-populate record-addition');
                }

                if(!cont.data('heuristRecordAdd')){
                    cont.recordAdd({
                        is_h6style: true,
                        innerTitle: true, // Assuming this means title within the popup
                        onClose: function() {
                            that._closeExploreMenuPopup();
                        },
                        isExpanded: expandRecordAddSetting,
                        mouseover: function() { that._resetCloseTimers(); }, // Keep popup open on mouseover
                        menu_locked: function(is_locked, is_mouseleave){
                            if(!is_mouseleave){
                                that._resetCloseTimers();
                                that._explorer_menu_locked = is_locked;
                            }
                    }  });
                }else{
                    cont.recordAdd('doExpand', expandRecordAddSetting); // Call method on existing instance
                }
            }//endif
    },

    /**
     * Populates the list of user's favourite filters in the explore menu.
     * Fetches saved searches if not already available. Handles adding, removing, and reordering of favourites.
     * @memberof heurist.slidersMenu
     * @param {?Array<Array<string>>} [favourite_filters] - Optional array of current favourite filters.
     *                                                Each inner array: `[filter_id, filter_name]`.
     *                                                If null, fetches from preferences.
     * @param {boolean} [resize_only=false] - If true, only adjusts the container height without repopulating.
     */
    populateFavouriteFilters: function(favourite_filters, resize_only = false){

        const that = this;
        // Ensure explore menu and its favourite container are loaded
        const exploreMenuExists = this.menues?.explore;
        const favContainerExists = exploreMenuExists && this.menues.explore.find('ul.favourite-filters').length !== 0;
        const savedSearchesAvailable = window.hWin.HAPI4.currentUser.usr_SavedSearch;

        if(!favContainerExists){ // If explore menu or its ul.favourite-filters isn't ready
            setTimeout(function(){ that.populateFavouriteFilters(favourite_filters, resize_only); }, 1000);
            return;
        }
        if(!savedSearchesAvailable){ // If saved searches aren't loaded yet
            window.hWin.HAPI4.SystemMgr.ssearch_get(null, (response) => {
                if(response.status == window.hWin.ResponseStatus.OK){
                    window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    that.populateFavouriteFilters(favourite_filters, resize_only); // Retry population
                }
            });
            return; // Exit until searches are loaded
        }

        let $favourite_container = this.menues.explore.find('ul.favourite-filters');
        // This guard was already present, but kept for safety.
        if($favourite_container.length==0){ // Should not happen due to favContainerExists check
            return;
        }

        function updateHeight(height){
            $favourite_container.css('height', `${Math.max(0, height)}px`); // Ensure non-negative height
        }

        const helpTextElement = this.menues.explore.find('.favour-help'); // Cache selector

        if(resize_only){
            let cont_height = this.menues.explore.height() - $favourite_container.position().top - 60;
            updateHeight(cont_height);
            return;
        }

        $favourite_container.empty(); // Clear list instead of $favourite_container.find('li').remove();
        helpTextElement.show();

        let cont_height = this.menues.explore.height() - $favourite_container.position().top;
        updateHeight(cont_height);

        // Adjust height if scrollbar appears
        if(this.menues.explore[0].clientHeight < this.menues.explore[0].scrollHeight){
            cont_height -= (this.menues.explore[0].scrollHeight - this.menues.explore[0].clientHeight) + 40;
            updateHeight(cont_height);
        }

        favourite_filters = favourite_filters ?? window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);

        if(favourite_filters.length === 0 || favourite_filters[0] === ''){
             helpTextElement.show(); // Ensure help text is shown if no favorites
            return;
        }

        if(!this.svs_list){ // Ensure SVS list widget is initialized if needed for search execution later
            this.getSvsList(); // This initializes this.svs_list if not already done
        }

        let valid_favourites = []; // Store validated and updated favourites

        for(const filter_tuple of favourite_filters){
            if(window.hWin.HEURIST4.util.isempty(filter_tuple) || !Object.hasOwn(window.hWin.HAPI4.currentUser.usr_SavedSearch, filter_tuple[0])){
                // remove missing/invalid filters by not adding them to valid_favourites
                continue;
            }

            let current_filter_id = filter_tuple[0];
            let current_filter_name = window.hWin.HAPI4.currentUser.usr_SavedSearch[current_filter_id][0];
            current_filter_name = typeof current_filter_name !== 'string' ? filter_tuple[1] : current_filter_name; // Fallback to stored name if needed

            valid_favourites.push([current_filter_id, current_filter_name]); // Add to the list of valid ones

            let $remove_btn = $('<span>', {
                class: 'smallbutton ui-icon ui-icon-redo', // Consider a more intuitive icon like ui-icon-close or ui-icon-trash
                style: 'display: none; float: right; margin-top: 1px; color: black',
                title: 'Remove filter from favourites',
                'data-fid': current_filter_id
            });

            let $txt = $('<span>', {
                class: 'truncate',
                style: 'font-size: 12px; display: inline-block; max-width: 85%; vertical-align: text-top',
                title: current_filter_name,
                text: current_filter_name
            });

            $('<li>', {
                class: 'fancytree-node', // Class suggests potential fancytree integration/styling, but it's a simple list here
                style: 'padding: 6px; cursor: pointer;',
                'data-fid': current_filter_id
            })
            .append($txt)
            .append($remove_btn)
            .appendTo($favourite_container);
        }

        // Save the cleaned list of favourites back to preferences
        window.hWin.HAPI4.save_pref('favourite_filters', valid_favourites.length > 0 ? valid_favourites : ['']);


        if($favourite_container.find('li').length === 0){
            helpTextElement.show();
            // Recalculate height if all items were removed
            cont_height = this.menues.explore.height() - $favourite_container.position().top - 60;
            updateHeight(cont_height);
            return;
        } else {
            helpTextElement.hide();
        }


        let block_filter = false; // Flag to prevent click action during sort

        this._on($favourite_container.find('li'), { // Use _on for automatic cleanup on widget destroy
            click: function(event){
                if(block_filter) { return; }

                let $ele = $(event.target);
                let $li_target = $ele.is('li.fancytree-node') ? $ele : $ele.closest('li.fancytree-node');
                let id = $li_target.attr('data-fid');

                if($ele.hasClass('smallbutton')){ // Clicked on remove button
                     // Remove from current list (valid_favourites is the source of truth for prefs)
                    let updated_favs_after_remove = valid_favourites.filter(f => f[0] !== id);
                     window.hWin.HAPI4.save_pref('favourite_filters', updated_favs_after_remove.length > 0 ? updated_favs_after_remove : ['']);
                    that.populateFavouriteFilters(updated_favs_after_remove); // Repopulate to reflect change and update UI
                } else if ($li_target.length > 0) { // Clicked on the list item itself
                    that.hideDatabaseOverview();
                    if (that.svs_list) { // Ensure svs_list is initialized
                        that.svs_list.svs_list('doSearchByID', id, $li_target.find('span.truncate').text());
                    }
                }
            },
            mouseenter: function(event){ // Changed from mouseover for better event handling on parent
                $(this).find('span.smallbutton').show();
            },
            mouseleave: function(event){ // Changed from mouseout
                $(this).find('span.smallbutton').hide();
            }
        });

        if ($.fn.sortable) { // Check if sortable is available
            $favourite_container.sortable({
                start: function(event, ui){
                    block_filter = true;
                },
                stop: function(event, ui){
                    let new_order = [];
                    $favourite_container.find('li').each(function(idx, ele){
                        let $ele = $(ele);
                        new_order.push([$ele.attr('data-fid'), $ele.find('span.truncate').text()]);
                    });
                    window.hWin.HAPI4.save_pref('favourite_filters', new_order);
                    valid_favourites = new_order; // Update local source of truth
                    setTimeout(function(){ block_filter = false; }, 100); // Shorter delay
                }
            });
        }

        // Recalculate height after populating
        cont_height = this.menues.explore.height() - $favourite_container.position().top - 60;
        updateHeight(cont_height);

    },


    /**
     * Initializes the Saved Searches List (svs_list) widget within a given container.
     * @memberof heurist.slidersMenu
     * @private
     * @param {jQuery} cont - The jQuery container element for the svs_list widget.
     * @param {number} mode - Filter mode for the list (0: all, 1: filters only, 2: rules only).
     * @returns {jQuery} The container element `cont` (now initialized as svs_list).
     */
    _init_SvsList: function(cont, mode){

        if(!cont.data('heuristSvs_list')){ // Check instance using .data()
            let that = this;

            cont.svs_list({
                is_h6style: true,
                hide_header: false, // Initially show header, might be changed later by other logic
                container_width: 300, // Assuming this is desired width for the popup
                filter_by_type: mode,
                onClose: function(noptions) {
                    if(noptions==null){ // Closed without selecting/acting
                        that._onCloseSearchFaceted(); // If faceted search was related
                    }else{ // Options provided, likely to open faceted search
                        noptions.onclose = function(){ that._onCloseSearchFaceted(); };
                        noptions.is_h6style = true;
                        noptions.maximize = true; // Or handle as per actual requirements

                        if (that.search_faceted) { // Check if search_faceted container exists
                            that.search_faceted.show();
                            // Consider resizing layout if this affects overall page structure
                            // e.g., that.containers['explore'].layout().resizeAll();
                            if(that.search_faceted.data('heuristSearch_faceted')){
                                that.search_faceted.search_faceted('option', noptions );
                            }else{
                                that.search_faceted.search_faceted( noptions );
                            }
                        }

                        that._closeExploreMenuPopup();
                        that._collapseMainMenuPanel(true);
                    }
                },
                menu_locked: function(is_locked, is_mouseleave){
                    if(!is_mouseleave){
                        that._resetCloseTimers();
                        that._explorer_menu_locked = is_locked;
                    }
                },
                handle_favourites: function(filter_id, filter_name, is_drop=false){
                    let hasChanged = false;
                    let cur_favs = window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);

                    let fav_idx = cur_favs.findIndex(filter => filter[0] == filter_id);

                    if(fav_idx === -1){ // Not in favourites, add it
                        if(cur_favs[0] === ''){ cur_favs = []; } // Initialize if it was empty string marker
                        cur_favs.push([filter_id, filter_name]);
                        hasChanged = true;
                    } else if (!is_drop) { // In favourites and not a drop (implies toggle off)
                        cur_favs.splice(fav_idx, 1);
                        if(cur_favs.length === 0) { cur_favs = ['']; } // Reset to empty marker if list becomes empty
                        hasChanged = true;
                    }
                    // If is_drop is true and it's already a favourite, do nothing.

                    if(hasChanged){
                        window.hWin.HAPI4.save_pref('favourite_filters', cur_favs);
                        that.populateFavouriteFilters(cur_favs); // Refresh the visual list
                    }
                }
            });

            this._on(cont,{mouseenter: this._resetCloseTimers}); // Keep popup open on mouseenter

        }else{
            cont.svs_list('option', 'filter_by_type', mode); // Update existing instance
        }

        this.svs_list = cont; // Store reference to the widget container
        return cont;
    },

    /**
     * Switches the context or display mode of the Saved Searches List.
     * Remark: This function currently has its logic commented out with `return;//2020-12-15`.
     * It seems intended to move or re-parent the SVS list between the explore menu and quick links.
     * @memberof heurist.slidersMenu
     * @private
     * @param {number} mode - 0 for display in `menues['explore']`, 1 for display in `ui-heurist-quicklinks`.
     */
    _switch_SvsList: function( mode ){
        return;//2020-12-15 - This function is currently disabled.
        // Original logic would handle moving svs_list display.
    },


    /**
     * Gets or initializes the Saved Searches List (svs_list) widget for general use.
     * Ensures the widget is created in the explore menu popup if not already present.
     * @memberof heurist.slidersMenu
     * @returns {jQuery} The jQuery object for the svs_list widget container.
     */
    getSvsList: function(){
        if (!this.menues_explore_popup) return $(); // Return empty jQuery if popup not ready

        let cont = this.menues_explore_popup.find('#search_filters');
        if(cont.length==0){
            cont = $('<div id="search_filters" class="explore-widgets">').appendTo(this.menues_explore_popup);
        }
        return this._init_SvsList(cont, 1); // Default mode 1 (filters only)
    },

    /**
     * Handles closing the faceted search interface.
     * If faceted search is visible, triggers a reset search event and hides the faceted search panel.
     * @memberof heurist.slidersMenu
     * @private
     */
    _onCloseSearchFaceted: function(){
        if(this.search_faceted && window.hWin.HEURIST4.ui.isVisible( this.search_faceted )){
            // Trigger a global event to clear views related to the faceted search
            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [
                {reset:true, search_realm:this.options.search_realm} // Assuming options.search_realm is relevant
            ]);
            this.search_faceted.hide();
        }
    },

    /**
     * Closes the explore menu popup.
     * Hides the popup, resets related state variables (_current_explore_action, _explorer_menu_locked),
     * and ensures associated dialogs (saved search, faceted wizard) are closed.
     * Hides the coverAll overlay.
     * @memberof heurist.slidersMenu
     * @private
     */
    _closeExploreMenuPopup: function(){
        if(this.menues_explore_popup){
            this.menues_explore_popup.hide();
            this._current_explore_action = null;
            this.closeSavedSearch();    // Ensure these are idempotent or check visibility
            this.closeFacetedWizard();
        }
        this._explorer_menu_locked = false;
        this._delayOnCollapse_ExploreMenu = 600; // Restore default delay
        if (this.coverAll) this.coverAll.hide();
    },

    /**
     * Closes a specific section's menu panel and its associated explore popup if it's the explore section.
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} section - The name of the section to close.
     */
    _closeSectionMenu: function( section ){
        if(this.menues[section]) {
            this.menues[section].css({'z-index':0}).hide(); // Reset z-index and hide
        }
        if(this.menues_explore_gap){ // This seems to be a general gap element
            this.menues_explore_gap.hide();
        }
        if(section=='explore'){
            this._closeExploreMenuPopup();
        }
    },

    /**
     * Determines the section name from a mouse event, typically by checking classes of the event target or its parents.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} [e] - The jQuery mouse event object.
     * @returns {?string} The name of the section, or null if not determinable.
     */
    _getSectionName: function(e){
        let section_name = null;
        if(e && e.target){ // Ensure e and e.target exist
            let ele;
            const $target = $(e.target);
            if($target.hasClass('ui-heurist-header') || $target.hasClass('ui-heurist-quicklinks')){
                ele = $target;
            }else{
                ele = $target.closest('.ui-heurist-quicklinks, .ui-heurist-header'); // Use closest for efficiency
            }

            if(ele.length>0){
                for(const section of this.sections) { // Use for...of for array iteration
                    if(ele.hasClass('ui-heurist-'+section)){
                        section_name = section;
                        break; // Exit loop once found
                    }
                }
            }
        }
        return section_name;
    },


    /**
     * Handles opening a section menu when its header is clicked.
     * Switches the main container to the selected section and collapses the main menu panel.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} e - The click event object.
     */
    _openSectionMenu: function(e){
        let section = this._getSectionName(e);
        if (!section) return; // Do nothing if section cannot be determined

        if(section=='explore' && this._active_section==section){
            this._onCloseSearchFaceted(); // Specific behavior for re-clicking explore
        }
        this.switchContainer( section );
        this._collapseMainMenuPanel(true, 200); // Collapse main menu, prevent re-expand for 200ms
    },

    /**
     * Loads the HTML content for a specific section's menu and main container.
     * Initializes the explore section with special handling (popup, gap, faceted search).
     * Initializes the main content area for the 'explore' section using LayoutMgr.
     * After loading HTML, calls `_initSectionMenuExplore` or `_initSectionMenu`.
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} section - The name of the section to load.
     */
    _loadSectionMenu: function( section ){
        let that = this; // Cache 'this'

        this.menues[section] = $('<div>')
            .addClass('ui-menu6-section ui-heurist-'+section)
            .css({width:'200px'})
            .appendTo( this.element );

        this.containers[section] = $('<div>')
            .addClass('ui-menu6-widgets ui-menu6-container ui-heurist-'+section)
            .appendTo( this.element );

        this.containers[section].css('left',(this._left_position+211)+'px');

        if(section=='explore'){
            this.menues_explore_popup = $('<div>')
                    .addClass('ui-menu6-section ui-heurist-explore') // Should match styling of other section menus
                    .css({width:'200px'}) // Default width, might be overridden by content
                    .hide()
                    .appendTo( this.element );

            this._on(this.menues_explore_popup,{
                mouseenter: function(e){ // Changed from mouseover
                    that._resetCloseTimers();
                },
                mouseleave: function(e){ // Changed from mouseout
                    // Only trigger mouseout for section menu if explore or populate is active,
                    // otherwise, it's a general mouseout from the popup that should lead to main menu collapse.
                    if(that._active_section=='explore' || that._active_section=='populate'){
                        that._mouseout_SectionMenu(e);
                    }else{
                        that._collapseMainMenuPanel(); // General collapse if not in explore/populate context
                    }
                }
            });

            this.menues_explore_gap = $('<div>')
                    .css({'width':'4px', position:'absolute', opacity: '0.8', 'z-index':103, left:this._widthMenu+'px'})
                    .hide()
                    .appendTo( this.element );

            this.search_faceted = $('<div>')
                    .addClass('ui-menu6-container ui-heurist-explore') // Similar to other containers
                    .css({left:(this._left_position+5)+'px', width:'200px', 'z-index':102}) // Positioned near main menu
                    .hide()
                    .appendTo( this.element );

            if (this.containers[section]) this.containers[section].show(); // Show explore container by default
            if (this.menues[section]) this.menues[section].show(); // Show explore section menu by default

            // Initialize explore container's content (e.g., SearchAnalyze3 layout)
            if (this.containers[section] && window.hWin.HAPI4.LayoutMgr) {
                 window.hWin.HAPI4.LayoutMgr.appInitAll('SearchAnalyze3', this.containers[section] );
            }
        }

        this.menues[section].load(
                window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/slidersMenu'+section.capitalize()+'.html',
                function(){ // Callback after HTML is loaded
                    window.hWin.HAPI4.HRA( that.menues[section] ); // Apply Heurist Resource Addressing

                    if(section=='explore'){
                        that._initSectionMenuExplore();
                    }else{
                        that._initSectionMenu(section);
                    }
                });
    },

    /**
     * Initializes specific behaviors for the 'explore' section menu after its HTML is loaded.
     * Sets up mouse event handlers for explore menu items and initializes related UI elements like
     * the "Add Record" default type display and section image.
     * @memberof heurist.slidersMenu
     * @private
     */
    _initSectionMenuExplore: function(){
            let that = this;
            if (!this.menues['explore']) return; // Guard

            this._on(this.menues['explore'].find('.menu-explore'),{ // Items within the explore section menu itself
                mouseenter: this._mousein_ExploreMenu,
                mouseleave: function(e){ // Changed from mouseout
                        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;
                        // this._resetCloseTimers(); // Don't reset here, _mouseout_SectionMenu will handle it
                        this._myTimeoutId2 = setTimeout(function(){
                                    that._closeExploreMenuPopup();
                                },  this._delayOnCollapse_ExploreMenu);
                }
            });
            this._on(this.menues['explore'].find('li[data-action-popup="recordAddSettings"]'), {
                click: this._mousein_ExploreMenu // Re-use mousein logic for click on settings
            });
            this._on(this.menues['explore'].find('li[data-action-popup="databaseOverview"]'), {
                click: this.showDatabaseOverview
            });

            this._updateDefaultAddRectype(); // Set initial state for "Add Record"

            let exp_img = this.element.find('img[data-src="gs_explore_cb.png"]').first(); // More specific selector
            if (exp_img.length > 0) {
                exp_img.attr('src', window.hWin.HAPI4.baseURL+'hclient/assets/v6/' + exp_img.attr('data-src'));
            }

            this._on(this.element.find('li[data-action-popup="search_recent"]'),{ // Assuming this is in main menu quicklinks
                click: function(event){
                    this.hideDatabaseOverview();
                    let q = '?w=a&q=sortby:-m';
                    let qname = 'All records';
                    if(!$(event.currentTarget).attr('data-search-all')){ // Use currentTarget
                         q = q + ' after:"1 week ago"';
                         qname = 'Recent changes';
                    }
                    let request = window.hWin.HEURIST4.query.parseHeuristQuery(q);
                    request.qname = qname;
                    window.hWin.HAPI4.RecordSearch.doSearch( this.element, request ); // Pass widget element as context
                }
            });

            this._switch_SvsList( 0 ); // Initialize SVS list for explore section context
            this._initSectionMenu( 'explore' ); // Common initialization for actions
    },

    /**
     * Initializes menu items within a given section after its HTML is loaded.
     * It finds `<li>` elements with `data-action` attributes, retrieves action details
     * (icon, label, hint) from `actionHandler`, and populates the list items.
     * Sets up click handlers to execute actions. Special handling for 'publish' and 'populate' sections.
     * Initializes accordions if present.
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} section - The name of the section to initialize.
     */
    _initSectionMenu: function( section ){
        if (!this.menues[section]) return; // Guard

        const that = this; // Cache this for callbacks

        $.each(this.menues[section].find('li[data-action]'),
            function(i, item_dom_element){ // Use a more descriptive var name
                let item = $(item_dom_element);
                let action_id = item.attr('data-action');
                if( !action_id ){
                    return; // Skip if no action_id
                }

                let action = window.hWin.HAPI4.actionHandler.findActionById(action_id);
                item.addClass('fancytree-node'); // Common styling/behavior class

                if(action==null){
                     // Optionally log an error or handle missing actions
                    item.append(`<span class="menu-text truncate" style="max-width: 109px; color: red;">Missing: ${action_id}</span>`);
                    return;
                }

                let action_icon = action.data?.icon || 'ui-icon-bullet'; // Default icon
                let action_label = window.hWin.HR( action_id ) || action.text || action_id; // Fallback for label
                let action_hint = window.hWin.HR( action_id+'-hint' ) || action.title || action_label; // Fallback for hint

                $('<span class="ui-icon '+action_icon+'"></span>'
                 +'<span class="menu-text truncate" style="max-width: 109px;">'+action_label+'</span>')
                .appendTo(item);
                item.attr('title', action_hint); // Set title/hint


                if(action_id=='menu-import-get-template'){
                    item.find('.ui-icon').addClass('ui-icon-gear'); // Specific icon override
                    item.css({'font-size':'10px', padding:'0 0 0 25px','margin-top':'-1px', 'margin-left': '0.25em'});
                }else{
                    item.css({'font-size':'smaller', padding:'6px'});
                    if(action_id=='menu-statistics-cms' && !(window.hWin.HAPI4.sysinfo.matomo_siteid>0)){
                        window.hWin.HEURIST4.util.setDisabled(item, true);
                    }
                }
            });

        let $recAddSettings = this.menues[section].find('li[data-action-popup="recordAddSettings"]');
        if($recAddSettings.length > 0){
            $recAddSettings.css({'font-size':'10px', padding:'0 0 0 30px','margin-top':'-1px'});
        }

        // Style header icons within the section menu
        let sectionHeaderIcons = this.menues[section].find('.ui-heurist-title .ui-icon'); // More specific selector
        if (sectionHeaderIcons.length > 0) {
            sectionHeaderIcons.addClass('ui-heurist-title').css({cursor:'pointer'}); // Assuming this is for styling
        }


        this.menues[section].find('.ui-icon-circle-b-help').css({cursor:'pointer'}); // Ensure help icon is clickable
        this._on(this.menues[section].find('.ui-icon-circle-b-help'),
            {click: this._loadIntroductoryGuide}); // Bind help guide loader

        this._on(this.menues[section].find('li[data-action]'),{click:function(e){
            let li = $(e.currentTarget); // Use currentTarget for delegated events
            // if(!li.is('li')) li = li.parents('li'); // Not needed with currentTarget on li

            if(li.attr('data-action')=='menu-admin-server'){ // Special handling for server admin item
                if (that.menues[section]) that.menues[section].find('li').removeClass('ui-state-active');
                li.addClass('ui-state-active');
            }

            if(section=='design' && that.containers[section]){
                    $(that.containers[section])
                        .css({left:(that._left_position+211)+'px',right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'});
            }
            window.hWin.HAPI4.actionHandler.executeActionById(li.attr('data-action'));
        }});

        if(section=='publish' && this.containers['publish']){ // Ensure container exists
            this._on(this.menues[section].find('li[data-cms-action]'),{click:function(e){
                let li = $(e.currentTarget);
                if (that.menues[section]) that.menues[section].find('li').removeClass('ui-state-active');
                li.addClass('ui-state-active');

                let btn = that.containers['publish'].find('#' + li.attr('data-cms-action'));
                if(btn.length>0) btn.trigger('click');
            }});
        } else if (section=='populate'){
            this._updateDefaultAddRectype(); // Ensure "Add Record" is up-to-date
            let ele = this.menues['populate'].find('li[data-action-popup="recordAdd"], li[data-action-popup="recordAddSettings"]');
            this._on(ele,{
                mouseenter: function(e_mousein){ // Rename event param
                    that._resetCloseTimers();
                    that.show_ExploreMenu(e_mousein);
                },
                mouseleave: function(e_mouseout){ // Rename event param
                    clearTimeout(that._myTimeoutId3); that._myTimeoutId3 = 0;
                    that._resetCloseTimers(); // Should be called before setting new timeout
                    that._myTimeoutId2 = setTimeout(function(){
                        that._closeExploreMenuPopup();
                    },  that._delayOnCollapse_ExploreMenu);
                }
            });
            // Click on settings should also behave like mousein for showing the menu
            this._on(this.menues['populate'].find('li[data-action-popup="recordAddSettings"]'), {
                click: function(e_click){ // Rename event param
                    that._resetCloseTimers(); // Ensure timers are cleared before showing
                    that.show_ExploreMenu(e_click);
                }
            });
        }

        // Initialize accordions if present
        if(this.menues[section].find('ul.accordionContainer').length > 0 && $.fn.accordion){
            this.menues[section].find('ul.accordionContainer').accordion({
                header: '.accordionHeader',
                collapsible: true,
                active: false, // Start collapsed
                heightStyle: "content", // Adjust height to content
                beforeActivate: function(event, ui){
                    if (ui.newPanel && ui.newPanel.length > 0) { // Check if newPanel exists
                        $(ui.newPanel).css({
                            'background': 'none',
                            'border': 'none',
                            // 'height': '', // Let heightStyle handle it
                            'font-size': '14px' // Ensure consistent font size
                        });
                    }
                }
            });
            this.menues[section].find('li.accordionHeader').css('padding', '0px 0px 8px'); // Consistent padding
        }
    },


    /**
     * Closes the currently active section (hides its menu and container) and prepares for a new section.
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} section - The name of the new section that will become active.
     * @param {boolean} [force_show=false] - If true, forces the container of the new section to show,
     *                                       even if it's empty.
     */
    _closeActiveSection:function(section, force_show){
            this._closeExploreMenuPopup(); // Always ensure explore popup is closed
            this._onCloseSearchFaceted();  // And faceted search

            if(this._active_section && this.menues[this._active_section])
            {
                if (this.containers[this._active_section]) this.containers[this._active_section].hide();
                this.menues[this._active_section].hide();
                this.element.removeClass('ui-heurist-'+this._active_section+'-fade');
                if (this.menues_explore_gap) this.menues_explore_gap.removeClass('ui-heurist-'+this._active_section+'-fade');
            }
            this._current_explore_action = null; // Reset explore action when section changes
            this._active_section = section;

            if(this.menues[section]){
                this.menues[section].css('z-index',101).show(); // Bring new section menu to front
            }

            // Show new section's container or introduction panel
            if(force_show || (this.containers[section] && !this.containers[section].is(':empty'))){
                if (this.containers[section]) this.containers[section].show();
            }else if(this.introductions && this.introductions[section]){ // Check introductions object itself
                this.introductions[section].css('left', (this._left_position+211)+'px').show();
            }

            this.element.addClass('ui-heurist-'+section+'-fade');
            if(this.menues_explore_gap){
                this.menues_explore_gap.addClass('ui-heurist-'+section+'-fade');
            }
    },

    /**
     * Switches the visible main section and its associated content container.
     * Hides introductory texts, closes the previously active section, and shows the new one.
     * If switching to 'explore', ensures layout is resized and quick tips are handled.
     * @memberof heurist.slidersMenu
     * @param {string} section - The name of the section to switch to.
     * @param {boolean} [force_show=false] - If true, forces the container of the new section to show.
     */
    switchContainer: function( section, force_show ){
        // Hide all introduction panels first
        if (this.introductions) {
            $.each(this.introductions, function(i, item){ if(item) $(item).hide(); });
        }

        let that = this;
        if(that._active_section !== section ){
            that._closeActiveSection( section, force_show );
        }else if(force_show || section=='explore'){ // If already active, but forced or it's explore
            if (that.containers[section]) that.containers[section].show();
        }else{
            return; // No change needed
        }

        if(section !== 'explore' || !that.containers[section]) { // Further actions only for explore or if container exists
            return;
        }

        // For explore section, ensure layout is resized
        if(that.containers[section].hasClass('ui-layout-container') && that.containers[section].data('layout')){
             that.containers[section].layout().resizeAll();
        }

        that._switch_SvsList( 0 ); // Set SVS list to explore context
        this.hideDatabaseOverview();

        if(this._show_quick_tips){
            this._show_quick_tips = false; // Show only once
            window.hWin.HAPI4.actionHandler.executeActionById('menu-help-quick-tips');
        }
        this.populateFavouriteFilters(null, true); // resize favourite filters section
    },

    //-----------------------------------------------------------------
    // SAVED FILTERS
    //-----------------------------------------------------------------

    /**
     * Closes the "Save Filter" dialog if it's open.
     * @memberof heurist.slidersMenu
     */
    closeSavedSearch: function(){
        if(this.edit_svs_dialog && typeof this.edit_svs_dialog.closeEditDialog === 'function'){
            this.edit_svs_dialog.closeEditDialog();
        }
    },

    /**
     * Closes the faceted search wizard dialog if it's open.
     * @memberof heurist.slidersMenu
     */
    closeFacetedWizard: function(){
        let faceted_search_wiz = $('#heurist-search-faceted-dialog'); // Global selector, might be fragile
        if(faceted_search_wiz && faceted_search_wiz.length>0 && faceted_search_wiz.data('uiDialog')){ // Check if it's a dialog
            faceted_search_wiz.dialog('close');
        }
    },

    /**
     * Opens the dialog to add or edit a saved filter or faceted search.
     * Uses the HSvsEdit component.
     * @memberof heurist.slidersMenu
     * @param {string} mode - 'saved' for regular saved filter, 'faceted' for faceted search wizard.
     * @param {boolean} [is_modal=true] - Whether the dialog should be modal.
     * @param {number} [left_position] - Optional left position for the dialog.
     * @param {number} [top_position] - Optional top position for the dialog.
     */
    addSavedSearch: function( mode, is_modal, left_position, top_position ){
        let that = this;

        if(this.edit_svs_dialog==null){
            this.edit_svs_dialog = new HSvsEdit();
        }

        if( !window.hWin.HEURIST4.util.isPositiveInt(left_position) ){
            left_position = (this.divMainMenu && (this.divMainMenu.width()>this._left_position)?this._widthMenu:this._left_position) + 4;
            if(this._active_section=='explore'){
                left_position = this._left_position + 211;
            }
        }
        if( !window.hWin.HEURIST4.util.isPositiveInt(top_position) ){
            top_position = 40; // Default top
        }

        is_modal = (is_modal!==false); // Default to true if not specified or null

        this._delayOnCollapse_ExploreMenu = 2000; // Increase delay while this dialog is up

        this.edit_svs_dialog.showSavedFilterEditDialog( mode, null, null, this.currentSearch , false,
            { my: `left+${left_position} top+${top_position}`, at: 'left top', of: this.divMainMenu || this.element }, // Fallback 'of'
            function(){  //after save callback
                window.hWin.HAPI4.currentUser.usr_SavedSearch = null; // Force reload of saved searches
                if(that.svs_list && that.svs_list.data('heuristSvs_list')){
                    that.svs_list.svs_list('option','hide_header',true); // Trigger refresh mechanism in svs_list
                    that.svs_list.svs_list('option','hide_header',false); // TODO: Review if this is the best way to refresh svs_list
                }
            },
            is_modal,
            true, // is_h6style
            function(is_locked, is_mouseleave){  //menu_locked callback from dialog
                if(is_locked=='close'){ // Dialog is closing
                    if (that.coverAll) that.coverAll.hide();
                    that._delayOnCollapse_ExploreMenu = 600; // Restore default delay
                }else if(is_mouseleave){ // Mouse left the dialog but it might still be open (e.g. datepicker)
                    that._resetCloseTimers(); // Allow main menu to collapse if mouse moves away
                }else { // Dialog is requesting lock
                    that._resetCloseTimers();
                    if(is_locked=='delay'){ // Special case for datepickers etc.
                        // if (that.coverAll) that.coverAll.show(); // Might be too intrusive
                        that._delayOnCollapse_ExploreMenu = 2000; // Keep menu open longer
                    }else{
                        that._explorer_menu_locked = is_locked; // Standard lock
                    }
                }
            },
            that.reset_svs_edit // Pass reset flag
        );
        that.reset_svs_edit = false; // Reset flag after use
    },

    /**
     * Initializes a generic help popup div.
     * Remark: This method and the `helper_div` property seem to be unused in the current widget logic.
     * @memberof heurist.slidersMenu
     */
    initHelpDiv: function(){
        // This div is created but not shown or populated by other methods in this widget.
        // It might be a leftover or intended for future use.
        this.helper_div = $('<div>').addClass('ui-helper-popup').hide().appendTo(this.element);

        let _innerTitle = $('<div>').addClass('ui-heurist-header').appendTo(this.helper_div);
        $('<span>').text('Help').appendTo(_innerTitle); // Default title
        let btn = $('<button>')
                    .button({icon:'ui-icon-closethick',showLabel:false, label:'Close'})
                    .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                    .appendTo(_innerTitle);

        this._on( btn, {click : function(){
                    if (this.helper_div) this.helper_div.hide();
        }});
        $('<div>').css({top:38}).addClass('ent_wrapper').appendTo(this.helper_div);
    },

    /**
     * Refreshes the display of the "current subset" indicator in the explore menu.
     * Shows the count of records in the workset and provides a button to clear the subset.
     * @memberof heurist.slidersMenu
     * @private
     */
    _refreshSubsetSign: function(){
            if (!this.menues['explore']) return; // Ensure explore menu is initialized

            let container = this.menues['explore'].find('li[data-action="menu-subset-set"]');
            if (container.length === 0) return; // Ensure the specific li exists

            let ele = container.find('span.subset-info');
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                if(ele.length==0){
                    ele = $('<span class="subset-info"><span '
                        +'style="display:inline-block;color:red;font-size:smaller;padding-left:22px"></span>'
                        +'<span class="ui-icon ui-icon-arrowrefresh-1-w clear_subset" style="font-size:0.7em;color:black;" '
                        +'title="'+window.hWin.HR('Click to revert to whole database')+'">'+
                        '</span></span>')
                        .appendTo(container);

                    this._on(ele.find('span.clear_subset').css('cursor','pointer'),
                        {click: function(e){
                            window.hWin.HEURIST4.util.stopEvent(e); // Prevent event bubbling
                            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                            if(widget && widget.data('heuristResultList')){ // Check instance
                                widget.resultList('callResultListMenu', 'menu-subset-clear');
                            }
                        }});
                }
                ele.find('span:first').html(window.hWin.HR('Current subset')
                        +' n&nbsp;&nbsp;=&nbsp;&nbsp;'+window.hWin.HAPI4.sysinfo.db_workset_count);
                ele.show();
            }else if(ele.length>0){
                ele.hide();
            }
    },

    /**
     * Initializes the introductory panel for a given section.
     * Creates a div with a section-specific image and a link to "Startup hints".
     * @memberof heurist.slidersMenu
     * @private
     * @param {string} section - The name of the section for which to create the intro panel.
     */
    _initIntroductory: function( section ){
        if(!this.introductions[section]){ // Create only if not exists
            let sname;
            if(section=='populate'){
                sname = 'Populate';
            }else{
                sname = section.charAt(0).toUpperCase()+section.slice(1); // More robust capitalization
            }

            // Construct HTML using jQuery for better readability and safety
            let $introBox = $('<div>', { class: 'gs-box', style: 'margin:10px;max-width:500px;height:100px;cursor:pointer' })
                .append($('<div>', { style: 'display:inline-block' })
                    .append($('<img>', { width: 110, height: 60, alt: '', src: `${window.hWin.HAPI4.baseURL}hclient/assets/v6/gs_${section}.png` }))
                )
                .append($('<span>', { class: 'ui-heurist-title header', id: 'start-hints', style: 'display: inline-block; font-weight: normal;padding-left:20px;cursor: pointer' })
                    .append($('<span>', { class: 'ui-icon ui-icon-help' }))
                    .append('&nbsp;Startup hints')
                )
                .append($('<div>', { class: 'ui-heurist-title', style: 'font-size: large !important;width: 80px;padding-top: 6px;' }).text(sname));

            this.introductions[section] = $('<div>').append($introBox)
                .addClass(`ui-menu6-container ui-heurist-${section}`) // Removed 'AAA'+this._left_position as its purpose is unclear
                .css({'background':'none'}) // Should be transparent to show main background
                .appendTo( this.element );

            this._on(this.introductions[section].find('#start-hints'),{click:this._loadStartHints});
        }
    },


    /**
     * Shows the database overview page within the 'explore' section's container.
     * Loads HTML content for the overview, populates it with database metadata,
     * and sets up click handlers for editing if the user has permissions.
     * @memberof heurist.slidersMenu
     */
    showDatabaseOverview: function(){
        let that = this;
        let editingProperties = false; // Flag to prevent re-entry while edit dialog is open

        if(this._active_section != 'explore'){
            this.switchContainer('explore'); // Ensure explore section is active
        }
        if (!this.containers['explore']) return; // Guard

        function openDBProperties(event){ // Inner helper function
            if($(event.target).is('a') || editingProperties){ // Don't interfere with link clicks or if already editing
                return;
            }
            editingProperties = true;
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(is_verified){ // Check credentials
                if (!is_verified) { editingProperties = false; return; }
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification', {
                    beforeClose: function(){ // Using beforeClose to ensure it runs before dialog is fully gone
                        editingProperties = false;
                        that.showDatabaseOverview(); // Refresh overview after closing dialog
                    }
                });
            }, 1); // Assuming 1 means check for admin/owner level
        }

        function changeFuncAccess($ele){ // $ele is the container of the overview
            // DB Logo container
            let $thumb_container = $ele.find('div#db-thumb') // Scope selectors to $ele
                .css({'display': 'block', 'margin-left': 'auto', 'margin-right': 'auto'})
                .off('click'); // Clear previous handlers

            $ele.find('#title_cont, #owner_cont, #rights_cont').off('click');
            $ele.find('div#description, button#btnEdit').off('click');

            if(window.hWin.HAPI4.has_access(1)){ // User has DB admin or owner access
                $ele.find('h3#title, span#owner, span#rights').parent().css('cursor', 'pointer');
                $ele.find('#title_cont, #owner_cont, #rights_cont').one('click', openDBProperties);
                $ele.find('div#description').css({'cursor': 'pointer', 'white-space': 'pre-wrap'}).on('click', openDBProperties);
                $ele.find('button#btnEdit').prop('disabled', false).button({label: 'Edit Metadata'})
                    .addClass('ui-button-action').css({'display': 'inline-block'}).on('click', openDBProperties);
                $thumb_container.on('click', openDBProperties).css('cursor', 'pointer');
            }else{ // No access
                $ele.find('h3#title, span#owner, span#rights').parent().css({'cursor': 'default', 'resize': 'none'});
                $ele.find('button#btnEdit').hide().prop('disabled', true);
                $thumb_container.css('cursor', 'default');
            }
        }

        function changeStyles($ele){ // $ele is the container
            let owner = $ele.find('span#owner');
            let rights = $ele.find('span#rights');
            let desc = $ele.find('div#description');

            // Helper to apply styles based on content presence
            const applyConditionalStyle = ($elem, defaultText, hasContentStyle, noContentStyle) => {
                if(!window.hWin.HEURIST4.util.isempty($elem.html()) && $elem.html() != defaultText){
                    $elem.parent().css(hasContentStyle);
                }else{
                    $elem.parent().css(noContentStyle);
                }
            };
            const hasContentCss = {'background': 'white', 'border': 'none', 'width': 'auto'};
            const noContentCss = {'background': '#F4F2F4', 'border': '1px solid gray', 'width': '90%'};

            applyConditionalStyle(owner, 'Database Ownership', hasContentCss, noContentCss);
            applyConditionalStyle(rights, 'Database Rights', hasContentCss, noContentCss);

            if(!window.hWin.HEURIST4.util.isempty(desc.html()) && desc.html() != 'Database Description'){
                desc.css({'background': 'white', 'border': 'none', 'height': 'auto'});
            }else{
                desc.css({'background': '#F4F2F4', 'border': '1px solid gray', 'height': '150px'});
            }
        }

        function updateDetails($ele){ // $ele is the container
            let $thumb_container = $ele.find('div#db-thumb')
                .empty() // Use empty() instead of text('') to remove child img elements
                .css({'border': 'none', 'background': 'none'}); // Reset styles

            let thumb_url = window.hWin.HAPI4.getImageUrl('sysIdentification', 1, 'thumb', 1);
            thumb_url += '&ts=' + new Date().getTime(); // Cache buster
            $('<img>', { src: thumb_url, class: 'image_input' }).appendTo($thumb_container);

            window.hWin.HAPI4.checkImage('sysIdentification', 1, 'thumb',
                function(response){
                    if(response.status != 'ok' || response.data != 'ok'){
                        $thumb_container.find('img').remove();
                        $thumb_container.text('No Logo')
                            .css({'border': '1px solid gray', 'background': '#F4F2F4', 'display': 'flex',
                                  'justify-content': 'center', 'align-items': 'center', 'width': 'fit-content', 'color': 'gray'});
                    }
                }
            );

            window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, function(entityResponse){
                if(!window.hWin.HEURIST4.util.isempty(entityResponse)){
                    let record = entityResponse.getFirstRecord();
                    // Field indices seem to be hardcoded, ensure they are correct for sysIdentification
                    let name = record[14], ownership = record[15], rights = record[16], desc_text = record[17];

                    $ele.find('h3#title').text((!window.hWin.HEURIST4.util.isempty(name) && name != 'Please enter a DB name ...') ? name : 'Database Title');
                    $ele.find('span#owner').html((!window.hWin.HEURIST4.util.isempty(ownership)) ? "Owner: " + ownership : 'Database Ownership');
                    $ele.find('span#rights').html((!window.hWin.HEURIST4.util.isempty(rights) && rights != 'Please define ownership and rights here ...') ? "Rights: " + rights : 'Database Rights');
                    $ele.find('div#description').html((!window.hWin.HEURIST4.util.isempty(desc_text)) ? desc_text : 'Database Description');
                }
                changeStyles($ele);
            });
        }

        let $overview_container = this.containers['explore'].find('div#db_overview');
        if($overview_container.length>0){
            $overview_container.css('z-index', '10').show(); // Ensure it's on top and visible
            updateDetails($overview_container);
            changeFuncAccess($overview_container);
            return;
        }

        let $ele = $('<div id="db_overview" class="ent_wrapper" style="background: white;">')
            .css('z-index', '10') // Ensure it's above other explore content
            .appendTo(this.containers['explore']);

        $ele.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/database_overview.html',
            function(){ // Callback after HTML is loaded into $ele
                $ele.find('div.mock-header') // Scope to $ele
                    .css({'font-weight': 'bold', /* ... other styles ... */ 'cursor': 'pointer'})
                    .on('click', function(e_click){ // Use _on if possible, or ensure manual cleanup in _destroy
                        let option = $(e_click.currentTarget).is('img') ? 'explore' : $(e_click.currentTarget).attr('id');
                        if(window.hWin.HEURIST4.util.isempty(option)){
                             option = $(e_click.currentTarget).parent().attr('id');
                        }
                        $ele.hide();
                        if(option != 'explore'){ // Assuming IDs are like 'Design', 'Populate'
                            that.switchContainer(option.charAt(0).toLowerCase() + option.slice(1));
                        }
                    });

                let $explore_img = $ele.find('img#explore-img');
                if ($explore_img.length > 0) {
                    $explore_img.attr('src', window.hWin.HAPI4.baseURL+'hclient/assets/v6/' + $explore_img.attr('data-src'));
                }

                $ele.find('div.add-new').css({/* ... */ 'cursor': 'pointer'})
                    .on('click', function(){
                        if (that.divMainMenu) that.divMainMenu.find('li.menu-explore[data-action-popup="recordAdd"]').trigger('mouseenter');
                    });

                $ele.find('span.flavour-text').css({'display': 'inline-block', 'margin-left': '10px'});

                $ele.find('div#quick_tips').css({/* ... */ cursor: 'pointer'})
                    .on('click', function(){
                        window.hWin.HAPI4.actionHandler.executeActionById('menu-help-quick-tips');
                    });

                let entity_container = $ele.find('ul#entity-usage').css({'list-style-type': 'none', /* ... */});
                let options = { /* ... */ ancor: entity_container }; // Ensure ancor is correctly passed if needed by createRectypeSelectNew
                let entities_usage = window.hWin.HEURIST4.ui.createRectypeSelectNew(null, options);

                if (entities_usage && entities_usage.find) { // Check if entities_usage is a jQuery object
                    entities_usage.find('option').each(function(idx, item){
                        if(idx >= 11) return false; // Max 10 items
                        let $opt = $(item);
                        let count = $opt.attr('rt-count') !== undefined ? $opt.attr('rt-count') : '';
                        $('<li data-id="'+$opt.attr('entity-id')+'" style="font-size:smaller;padding:4px 0px 2px 0px">'
                            +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif" class="rt-icon" style="vertical-align:bottom;background-image: url(\''+$opt.attr('icon-url')+ '\');"/>'
                            +'<div class="menu-text truncate" style="max-width:130px;display:inline-block;" title="Search for '+$opt.text()+' records">'+$opt.text()+'</div>'
                            +'<span style="float:right;min-width:20px;margin-left:10px;">'+count+'</span></li>')
                        .appendTo(entity_container);
                    });
                }


                entity_container.find('li[data-id]').on('click', function(e_click_li){
                    let $li = $(e_click_li.currentTarget);
                    let rectype_id = $li.attr('data-id');
                    if(rectype_id > 0){
                        let rty_plural = $Db.rty(rectype_id, 'rty_Plural') || ('RecType ' + rectype_id);
                        let request = { q: 't:'+rectype_id, w: 'a', qname: rty_plural, detail: 'ids' };
                        window.hWin.HAPI4.RecordSearch.doSearch( that.element, request );
                        that.switchContainer('explore'); // Switch to explore to show results
                    }
                });
                updateDetails($ele);
                changeFuncAccess($ele);
            }
        );
    },

    /**
     * Hides the database overview page if it is currently visible.
     * @memberof heurist.slidersMenu
     */
    hideDatabaseOverview: function(){
        if(this.containers && this.containers['explore'] && this.containers['explore'].find('div#db_overview').length > 0){
            this.containers['explore'].find('div#db_overview').hide();
        }
    },


    /**
     * Loads the introductory guide content for the currently active section.
     * Replaces the content of the section's introduction panel with detailed guide HTML.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} e - The click event object that triggered this action.
     */
    _loadIntroductoryGuide: function(e){ // e is passed but not used
        let section = this._active_section;
        if (!section || !this.introductions || !this.introductions[section]) return;

        // Unbind previous click to prevent multiple loads if guide is re-opened
        this._off(this.introductions[section].find('.gs-box'),'click');

        let that = this;
        this.introductions[section]
                .load(window.hWin.HAPI4.baseURL+'startup/gettingStarted.html div.gs-box.ui-heurist-'+section,
                function(){ // Callback after loading gettingStarted.html fragment
                    let $introSection = that.introductions[section].find('div.gs-box.ui-heurist-'+section);
                    if ($introSection.length === 0) return; // Fragment not found

                    $introSection.find('img').each(function(i,img_dom){
                        let img = $(img_dom);
                        img.attr('src',window.hWin.HAPI4.baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                    });

                    // Re-add startup hints link, now within the loaded content
                    $introSection
                        .prepend( '<span class="ui-heurist-title header" id="start-hints" style="padding-top:57px;font-weight:normal;padding-left:20px;cursor:pointer">'
                                   +'<span class="ui-icon ui-icon-help"></span>&nbsp;Startup hints</span>' )
                        .on('click', '#start-hints', function() { that._loadStartHints(null); }); // Bind to new #start-hints

                    $introSection.css({position:'absolute', left:'10px', right:'10px', top:'10px', 'min-width':'700px', margin:0}).show();
                    $introSection.find('> div:first').css('margin','23px 0'); // Style first child div
                    $introSection.find('.ui-heurist-title.header').css({position:'absolute', left:'160px', top:'40px', right:'400px', 'max-width':'540px'});

                    // Load more specific content for the section
                    $('<div class="gs-box">') // New container for detailed content
                        .css({position:'absolute', left:10, right:10, top:180, bottom:10, 'min-width':400, overflow: 'auto'})
                        .load( window.hWin.HRes('menu_'+section)+' #content' ) // HRes likely resolves to a URL
                        .appendTo( $introSection ); // Append to the loaded section fragment
                })
                .css({left:(that._left_position+211)+'px',right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'})
                .show();

        if (this.containers[section]) this.containers[section].hide(); // Hide main content container
    },

    /**
     * Loads the "Startup Hints" / Welcome content into the currently active section's introduction panel.
     * @memberof heurist.slidersMenu
     * @private
     * @param {Event} [e] - The click event object (optional, not directly used but signifies user action).
     */
    _loadStartHints: function(e){ // e is passed but not used
        let section = this._active_section;
        if (!section || !this.introductions || !this.introductions[section]) return;

        // Prevent re-binding if called multiple times
        this._off(this.introductions[section].find('#start-hints'),'click');
        this.introductions[section].find('#start-hints').hide(); // Hide the "Startup hints" link itself

        let that = this;
        this.introductions[section]
                .load(window.hWin.HAPI4.baseURL+'startup/gettingStarted.html div.gs-box.ui-heurist-'+section,
                function(){ // Callback after loading the base structure for the section's hints
                    let $introSectionContent = that.introductions[section].find('div.gs-box.ui-heurist-'+section);
                    if ($introSectionContent.length === 0) return;

                    $introSectionContent.find('img').each(function(i,img_dom){
						let img = $(img_dom);
						img.attr('src',window.hWin.HAPI4.baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                    });

                    $introSectionContent.css({position:'absolute', left:10, right:10, top:10, 'min-width':700, margin:0}).show();
                    $introSectionContent.find('> div:first').css('margin','23px 0');
                    $introSectionContent.find('.ui-heurist-title.header').css({position:'absolute', left:160, top:40, right:400, 'max-width':'540px'});

                    // Load the actual welcome.html content
                    $('<div class="gs-box">') // New inner container for welcome.html
						.css({position:'absolute', left:10, right:10, top:180, bottom:10, 'min-width':400, overflow: 'auto'})
						.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/welcome.html', function(){
							// This callback is after welcome.html is loaded
							let url = `${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}`;
							$(this).find('.bookmark-url').html(`<a href="#">${url}</a>`).on('click', function(click_event){ // Scope find to $(this)
								window.hWin.HEURIST4.util.stopEvent(click_event);
								window.hWin.HEURIST4.msg.showMsgFlash('Press Ctrl+D to bookmark this page',1000);
								return false;
							});
                            if (that._menu_colours && that._menu_colours[section]) { // Check existence
                                $(this).find('.ui-icon-bookmark').css('color', that._menu_colours[section]);
                            }
						})
						.appendTo( $introSectionContent ); // Append to the section-specific gs-box
                })
                .css({left: ((that._left_position+211)+'px'),
                      right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'})
                .show();
        if (this.containers[section]) this.containers[section].hide(); // Hide main content for the section
    },

    /**
     * Empties and hides the main content container for a given section.
     * @memberof heurist.slidersMenu
     * @param {string} section - The name of the section whose container should be closed.
     */
    closeContainer: function(section){
        if(this.containers && this.containers[section]){
            this.containers[section].empty().hide();
        }
    }
});
