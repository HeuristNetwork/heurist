/**
 * @file resultListMenu.js
 * @brief Manages and displays a context-sensitive menu for actions related to a result list.
 * @fileOverview
 * This file defines the `heurist.resultListMenu` jQuery UI widget. This widget is responsible
 * for creating and managing a set of dropdown menus that provide actions applicable to a list
 * of records (typically a `heurist.resultList`). The menu items can be context-sensitive,
 * for example, depending on user permissions, selection state, or the number of collected items.
 * Actions include operations on selected records (e.g., tagging, deleting, batch editing),
 * managing collected items (e.g., adding from selection, clearing, saving), and other
 * data manipulation tasks like recoding or sharing. The widget listens to global Heurist events
 * to update its state and the availability of menu items.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/**
 * @widget heurist.resultListMenu
 * @description A widget that creates and manages a set of context-sensitive dropdown menus
 * for performing actions on records from a result list. Actions can include operations on
 * selected records, collected items, and general data manipulations.
 *
 * @example
 * $('#myMenuContainer').resultListMenu({
 *     resultList: $('#myResultListWidget'), // Reference to the associated result list
 *     is_h6style: true // Apply H6 styling for menu items
 * });
 */
$.widget( "heurist.resultListMenu", {

    /**
     * @typedef {object} heurist.resultListMenu.options
     * @description Options for configuring the resultListMenu widget.
     * @property {boolean} [is_h6style=false]
     *  If true, applies styling to the menu items to resemble H6 headings, making them larger.
     *  Otherwise, a default, slightly smaller font size is used.
     * @property {string|null} [menu_class=null]
     *  An optional CSS class to be added to the main element of the widget for custom styling.
     * @property {jQuery|null} [resultList=null]
     *  A jQuery object representing the `heurist.resultList` widget to which this menu is related.
     *  This is used to access options like `search_realm` and to trigger actions on the result list.
     * @property {string|null} [search_realm=null]
     *  The search realm associated with this menu. If a `resultList` is provided,
     *  this option is typically derived from the `resultList`'s `search_realm`. Used for
     *  filtering global events.
     */
    options: {
        is_h6style: false,
        // callbacks
        menu_class:null,
        resultList: null,  //reference to parent resultList
        search_realm: null
    },

    /**
     * @property {object} _query_request
     * @private
     * @description Stores the current query request object associated with the result list.
     * This is typically updated by the `ON_REC_SEARCHSTART` event and used when actions
     * like reloading the search are performed.
     */
    _query_request: {},   //keep current query request
    /**
     * @property {heurist.HRecordSet|null} _selection
     * @private
     * @description Stores the current set of selected records (as an HRecordSet or similar object)
     * from the associated result list. This is updated by the `ON_REC_SELECT` event and used
     * by actions that operate on selected items.
     */
    _selection: null,     //current set of selected records (not just ids)

    /**
     * @function _create
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Initializes the widget. Sets up the main menu container and initializes
     * individual menu sections (Selected, Collected, Recode, Shared, Reorder) by calling `_initMenu`.
     * Applies styling based on `options.is_h6style` and `options.menu_class`.
     * Binds listeners to global Heurist events (credentials, search start, collection updates, selection changes)
     * to refresh the menu state and update internal properties like `_query_request` and `_selection`.
     * Triggers an initial refresh and an update of the collection display.
     */
    _create: function() {

        let that = this;

        this.element
        .css('font-size', this.options.is_h6style?'1.2em':'1.3em')
        // prevent double click to select text
        .disableSelection();
        
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu')
            //.css({'dispaly':'table-row'})
            .appendTo(this.element);

        this._initMenu('Selected');
        this._initMenu('Collected','Collect');
        this._initMenu('Recode');
        this._initMenu('Shared','Share');
        if(this.options.resultList){
            this._initMenu('Reorder');  
            this.options.search_realm = this.options.resultList.resultList('option', 'search_realm');
        } 
       
       
        this.divMainMenuItems.menu();

        this.divMainMenuItems.find('li').css({'padding':'0 3px 3px', 'width':'100px', 'text-align':'center'}); // center, place gap and setting width
        
        if(this.options.menu_class!=null){
             this.element.addClass( this.options.menu_class );   
        }else{
            this.divMainMenuItems.find('.ui-menu-item > a').addClass('ui-widget-content');    
        }

       
        this.divMainMenuItems.children('li').children('a').children('.ui-icon').css({right: '2px', left:'unset'});
       
        
        //-----------------------     listener of global events
        let sevents = window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
                 +window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+' '
                 +window.hWin.HAPI4.Event.ON_REC_COLLECT+' '
                 +window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(window.hWin.document).on(sevents, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS){

                that._refresh();

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(data && !data.reset && that._isSameRealm(data)) {
                    that._query_request = jQuery.extend({}, data); //keep current query request
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                that.collectionRender( data.collection );
                
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                    if(data.reset){
                        that._selection = null;
                    }else{
                        that._selection = window.hWin.HAPI4.getSelection(data.selection, false);
                    }
                    window.hWin.HAPI4.currentRecordsetSelection = that.getSelectionIds();
                }
            }
           
        });

        this._refresh();

        //get collection
        window.hWin.HEURIST4.collection.collectionUpdate();

    }, //end _create

    /**
     * @function _isSameRealm
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Checks if the widget's current search realm matches the realm from incoming event data.
     * This is used to ensure the widget only responds to events relevant to its configured context.
     * An empty or null realm on either side is considered a match for broader compatibility.
     * @param {object} data Event data, expected to have a `search_realm` property.
     * @returns {boolean} True if the realms are considered the same, false otherwise.
     */
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    
    /**
     * @function _init
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Post-creation initialization. Currently empty, but can be used for tasks
     * that need to run after the widget is created and DOM elements are in place.
     */
    _init: function() {

    },

    /**
     * @function _setOptions
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Called when options are set on the widget. Uses `_superApply` to call the base
     * widget's method, ensuring proper option handling.
     * @param {object} options An object containing option key-value pairs to set.
     */
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /**
     * @function _refresh
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Refreshes the visibility and enabled state of menu items based on user login status
     * and administrative privileges. Shows or hides menu items marked with `logged-in-only` class
     * and enables/disables items based on `data-user-admin-status` attributes.
     */
    _refresh: function(){

        if(window.hWin.HAPI4.has_access()){

            this.menu_Selected.find('.logged-in-only').show();
            this.menu_Collected.find('.logged-in-only').show();
            this.btn_Recode.show();
            this.btn_Shared.show();
            this.btn_Reorder.show();

            this['menu_Recode'].find('.logged-in-only:not([data-user-experience-level])').show();
           
            
            function ___set_menu_item_visibility(i,item){
                item = $(item);
                let lvl_user =  item.attr('data-user-admin-status');
                let is_visible = window.hWin.HAPI4.has_access(lvl_user);
                let elink = item.find('a');
                if(is_visible){
                    window.hWin.HEURIST4.util.setDisabled(elink, false);
                    item.attr('title', '');
                }else{
                    window.hWin.HEURIST4.util.setDisabled(elink, true);
                    
                    item.attr('title', 'Only for '
                    + (item.attr('data-user-admin-status')==2?'the database owner':'database managers')
                    + ' allowed');
                }
            }
            
            this['menu_Recode'].find('li[data-user-admin-status]').each(___set_menu_item_visibility);
            this['menu_Shared'].find('li[data-user-admin-status]').each(___set_menu_item_visibility);
            
            
        }else{
           
            this.menu_Selected.find('.logged-in-only').hide();
            this.menu_Collected.find('.logged-in-only').hide();
            this.btn_Recode.hide();
            this.btn_Shared.hide();
            this.btn_Reorder.hide();
			
            this['menu_Recode'].find('.logged-in-only:not([data-user-experience-level])').hide();			
        }
    },

    /**
     * @function _destroy
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Cleans up the widget upon removal. Unbinds global event listeners and removes
     * all DOM elements created by the widget (menu buttons and their associated dropdown menus).
     */
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
            +window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+' '
            +window.hWin.HAPI4.Event.ON_REC_COLLECT+' '
            +window.hWin.HAPI4.Event.ON_REC_SELECT); 

        // remove generated elements
        if(this.btn_Search){ // btn_Search is not initialized in _create, this check prevents errors.
            this.btn_Search.remove();
            this.menu_Search.remove();
        }
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Recode.remove();
        this.menu_Recode.remove();
        this.btn_Shared.remove();
        this.menu_Shared.remove();
        if (this.btn_Reorder) this.btn_Reorder.remove(); // Check if Reorder button exists
        this.divMainMenuItems.remove();
    },

    /**
     * @function _initMenu
     * @memberof heurist.resultListMenu
     * @instance
     * @private
     * @description Initializes a top-level menu button and its associated dropdown menu.
     * Creates the button, loads its dropdown content from an HTML file (e.g., `resultListMenuSelected.html`),
     * sets up hover and click handlers for showing/hiding the dropdown, and initializes the
     * dropdown as a jQuery UI menu. Handles localization of menu item text and tooltips.
     * @param {string} name The base name for the menu (e.g., "Selected", "Collected"). Used to
     * generate element IDs and load the corresponding HTML file.
     * @param {string} [menu_label] Optional label for the menu button. If not provided, `name` is used.
     * @param {number} [competency_level] Optional competency level for the menu button. If provided,
     * the button may be hidden based on the user's competency level settings.
     */
    _initMenu: function(name, menu_label, competency_level){

        let that = this;
        let myTimeoutId = -1;

        //show hide functions
        let _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 1);
           
        };
        
        let _show = function(ele, parent) {
            clearTimeout(myTimeoutId);

            $('.menu-or-popup').hide(); //hide other
            let menu = $( ele )
            //.css('width', this.btn_user.width())
            .show()
            .position({my: "left top", at: "left bottom", of: parent, collision:'none' });
           

            return false;
        };

        let link = $('<a href="#"'
                +(this.options.is_h6style?' style="padding-right:22px !important"':'')
                +'>'+window.hWin.HR(menu_label?menu_label:name)+'</a>')
       
        
        if(this.options.is_h6style){
           
            if(name=='Reorder'){
                $('<span class="ui-icon ui-icon-signal">').css({'transform':'rotate(90deg)'}).appendTo(link);  //caret-1-s
            }else{
                $('<span class="ui-icon ui-icon-carat-d">').appendTo(link);  //caret-1-s
            }
        }
        

        if(name=='Collected'){
            this.menu_Collected_link = link;
        }

        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
        
        
        let usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        
        if(competency_level>=0){
            this['btn_'+name].addClass('heurist-competency'+competency_level);    
            if(usr_exp_level>competency_level){
                this['btn_'+name].hide();    
            }
        }

        if(name=='Reorder'){
            
            this['btn_'+name].attr('title', window.hWin.HR('menu_reorder_hint'));
            
            
            this._on( this['btn_'+name], {
                click : function(){
                    if(!this.isResultSetEmpty()){
                       $(this.options.resultList).resultList('setOrderAndSaveAsFilter'); 
                    }
                }
            });
            
        }else{
        
            this['menu_'+name] = $('<ul>')                               //add to avoid cache in devtime '?t='+(new Date().getTime())
            .load(window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/resultListMenu'+name+'.html', function(){  
                
                let content = that['menu_'+name].find('ul');
                if(content.length>0){
                    that['menu_'+name].html(content.html());
                }
                
                that['menu_'+name].addClass('menu-or-popup')
                .css('position','absolute')
                .appendTo( that.document.find('body') )
                //.addClass('ui-menu-divider-heurist')
                .menu({
                    icons: { submenu: "ui-icon-circle-triangle-e" },
                    select: function(event, ui){ 
                    event.preventDefault(); 
                    that.menuActionHandler(ui.item.attr('id')); 
                    return false; }});

                if(window.hWin.HAPI4.has_access()){
                    that['menu_'+name].find('.logged-in-only').show();
                }else{
                    that['menu_'+name].find('.logged-in-only').hide();
                }
                
                that['menu_'+name].find('li[data-user-experience-level]').each(function(){
                    if(usr_exp_level > $(this).data('exp-level')){
                        $(this).hide();    
                    }else{
                        $(this).show();    
                    }
                });

                //localization                
                that['menu_'+name].find('li[id^="menu-"]').each(function(){
                    let menu_id = $(this).attr('id');
                    let item = $(this).find('a');
                    item.text(window.hWin.HR( menu_id ));
                    const hint = window.hWin.HR( menu_id+'-hint');
                    if(hint!=(menu_id+'-hint')){
                        item.attr('title',hint);    
                    }
                });
                
                that['menu_'+name].find('li').css('padding-left',0);
                
                
            })
            //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
            .hide();

            //{select: that.menuActionHandler}
            


            this._on( this['btn_'+name], {
                mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                mouseleave : function(){_hide(this['menu_'+name])}
            });
            this._on( this['menu_'+name], {
                mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                mouseleave : function(){_hide(this['menu_'+name])}
            });

        }
        
    },

    /**
     * @function menuActionHandler
     * @memberof heurist.resultListMenu
     * @instance
     * @description Handles click events on individual menu items within the dropdowns.
     * Dispatches to appropriate actions based on the menu item's ID.
     * Actions include selection manipulation (select all/none, show selection),
     * record operations (tagging, bookmarking, deleting, ownership, batch editing),
     * collection management (add, remove, clear, show, save), and subset operations.
     * @param {string} action The ID of the clicked menu item, used to determine the action.
     */
    menuActionHandler: function(action){

        let that = this;

        if(action == "menu-selected-select-all"){

            this.selectAll();

        }else if(action == "menu-selected-select-none"){

            this.selectNone();

        }else if(action == "menu-selected-select-insearch"){

            this.selectShow();

        }else if(action == "menu-selected-select-show"){  //show selection as separate search

            this.selectShowNewTab();

        }else if(action == "menu-selected-merge"){  //show add relation dialog

            this.fixDuplicatesPopup();

        }else if(action == "menu-selected-tag" || action == "menu-selected-bookmark" || action == "menu-selected-wgtags"){
           
            if(this.isResultSetEmpty()) return;
            
            let opts = {
                width:700,
                groups: (action == "menu-selected-bookmark")?'personal':'all',
                onClose:
                   function( context ){
                       if(context){
                           //refresh search page
                           that.reloadSearch(); //@todo reloadPage                   
                       }
                   }
            };
            if(action == "menu-selected-bookmark"){
                opts['modes'] = ['bookmark'];
            }
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordTag', opts);

        }else if(action == "menu-selected-unbookmark"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordBookmark', {onClose:
                   function( context ){
                       if(context){
                           //refresh search page
                           that.reloadSearch(); //@todo reloadPage                   
                       }
                   }
            });

        }else if(action == "menu-selected-rate"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordRate');

        }else if(action == "menu-selected-delete"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HAPI4.currentRecordsetSelection = this.getSelectionIds( window.hWin.HR('resultList_select_record') );
            if(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.currentRecordsetSelection)) return;

            window.hWin.HAPI4.SystemMgr.verify_credentials(() => {

                window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {onClose:
                    function( context ){
                        if(context){
                            // refresh search
                            that.reloadSearch();
                        }
                    }
                });

            }, 0, null, null, 'delete');

        }else if(action == "menu-selected-email") {

            if(this.isResultSetEmpty()) return;
            
            this.openEmailForm();

        }else if(action == "menu-selected-ownership"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {height:450, width:540, show_modes: true,
                onClose: function( context ){
                    if(context){
                       //@todo refresh page
                       that.reloadSearch();
                    }
                }
            });

        }else if(action == "menu-selected-notify"){
            
            if(this.isResultSetEmpty()) return;

            window.hWin.HEURIST4.ui.showRecordActionDialog('recordNotify');

        }else if(action == "menu-selected-value-add"){

            this.detailBatchEditPopup('add_detail');

        }else if(action == "menu-selected-value-replace"){

            this.detailBatchEditPopup('replace_detail');

        }else if(action == "menu-selected-url-to-file"){

            this.detailBatchEditPopup('url_to_file');
            
        }else if(action == "menu-selected-local-to-repository"){

            this.detailBatchEditPopup('local_to_repository');

        }else if(action == "menu-selected-reset-thumbs"){

            this.detailBatchEditPopup('reset_thumbs', function(){
                that.reloadSearch();
            });
            
        }else if(action == "menu-selected-iiif-thumbs"){

            this.detailBatchEditPopup('iiif_thumbs');

        }else if(action == "menu-selected-case-conversion"){

            this.detailBatchEditPopup('case_conversion');

        }else if(action == "menu-selected-increment"){

            this.detailBatchEditPopup('increment');
            
        }else if(action == "menu-selected-nl2br"){

            this.detailBatchEditPopup('nl2br');

            
        }else if(action == "menu-selected-translation"){

            
            if(!window.hWin.HAPI4.sysinfo.api_Translator){
                window.hWin.HEURIST4.msg.showMsg(
                    '<span style="display:inline-block;margin-top:10px;">'
                                        + 'To enable automatic translation please ask your system administrator to<br>'
                                        + 'add a Deepl free or paid account API key to Heurist configuration'
                                     + '</span>');                
            }else{
                this.detailBatchEditPopup('translation');    
            }

        }else if(action == "menu-selected-value-delete"){

            this.detailBatchEditPopup('delete_detail');

        }else if(action == "menu-selected-add-link"){

            if(this.isResultSetEmpty()) return;
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink');
            
        }else if(action == "menu-selected-add-link-match"){

            if(this.isResultSetEmpty()) return;
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLinkMatch');
            
        }else if(action == "menu-selected-extract-pdf"){

            this.detailBatchEditPopup('extract_pdf');
            
        }else if(action == "menu-selected-rectype-change"){

            this.detailBatchEditPopup('rectype_change');

        }else if(action == "menu-collected-add"){

            window.hWin.HEURIST4.collection.collectionAdd(null, this._selection);
            this.selectNone();

        }else if(action == "menu-collected-del"){

            window.hWin.HEURIST4.collection.collectionDel(null, this._selection);
            this.selectNone();

        }else if(action == "menu-collected-clear"){

            window.hWin.HEURIST4.collection.collectionClear();

        }else if(action == "menu-collected-show"){

            this.options.resultList.resultList('displayCollection', true);

        }else if(action == "menu-collected-tab"){

            window.hWin.HEURIST4.collection.collectionShow();

        }else if(action == "menu-collected-save"){

            window.hWin.HEURIST4.collection.collectionSave();
        
        }
//@todo? move to main menu
        else if(action == "menu-subset-set"){
            
            if(!window.hWin.HAPI4.currentRecordset ||
                    window.hWin.HAPI4.currentRecordset.length()==0)
            {
                        
                window.hWin.HEURIST4.msg.showMsg(
                '<p>The working subset is created from your current query results. You have no query results, so no subset was created.</p>' 
                +'<p>Please run a query which returns the set of records you wish to treat as the working subset and select this function again.</p>');
                    
            }else if(window.hWin.HAPI4.currentRecordset.length()>window.hWin.HAPI4.sysinfo.db_total_records*0.8){
                
                window.hWin.HEURIST4.msg.showMsg(
                '<p>You are trying to make a subset of everything or nearly everything (>=99%) of records  in the database. This does not make much sense.</p>'  
+'<p>Please apply a filter which returns the subset you wish to work with and select this function again.</p>');
                
            }else {
            
                let scope = window.hWin.HAPI4.currentRecordset.getIds();
                
                window.hWin.HAPI4.SystemMgr.user_wss({ids:scope.join(',')},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HAPI4.sysinfo.db_workset_count = response.data;
                            that.options.resultList.resultList('refreshSubsetSign');
                            window.hWin.HEURIST4.msg.showMsgFlash(response.data+' records has been added to work subset');
                            
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {closeFacetedSearch:true, userWorkSetUpdated:true, 
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response, true);
                        }
                    });
                    
            }
            

        }else if(action == "menu-subset-clear"){

            window.hWin.HAPI4.SystemMgr.user_wss({clear:1},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.sysinfo.db_workset_count = 0;
                        that.options.resultList.resultList('refreshSubsetSign');
                        
                        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {userWorkSetUpdated:true, 
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                });
                
        }
    },

    /**
     * @function reloadSearch
     * @memberof heurist.resultListMenu
     * @instance
     * @description Reloads the current search. If a new query string is provided, it updates
     * the internal `_query_request`. It then triggers a new search using `HAPI4.RecordSearch.doSearch`.
     * @param {string} [query] Optional new query string to execute.
     */
    reloadSearch: function(query){

        if(!window.hWin.HEURIST4.util.isempty(query)){
            this._query_request.q = query;
        }

        this._query_request.id = null;
        this._query_request.source = this.element.attr('id');
        window.hWin.HAPI4.RecordSearch.doSearch( this, this._query_request );
    },
    
    /**
     * @function reloadPage
     * @memberof heurist.resultListMenu
     * @instance
     * @description Placeholder function intended to clean details for the current recordset
     * and force a refresh of the current page. Currently not implemented.
     * @todo Implement page reload logic.
     */
    reloadPage: function(){
    
    },
    
    /**
     * @function getSelectionIds
     * @memberof heurist.resultListMenu
     * @instance
     * @description Retrieves the IDs of the currently selected records.
     * If a message is provided and no records are selected, it displays the message.
     * If a limit is provided and the selection exceeds it, it displays a message.
     * @param {string} [msg] Optional message to display if no records are selected.
     * @param {number} [limit] Optional limit on the number of selected records.
     * @returns {Array<number>|null} An array of selected record IDs, or null if no records are
     * selected (and `msg` was provided) or if a limit was exceeded.
     */
    getSelectionIds: function(msg, limit){

        let recIDs_list = [];
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }

        if (recIDs_list.length == 0 && !window.hWin.HEURIST4.util.isempty(msg)) {
            window.hWin.HEURIST4.msg.showMsg(msg, {default_palette_class:'ui-heurist-explore'});
            return null;
        }else if (limit>0 && recIDs_list.length > limit) {
            window.hWin.HEURIST4.msg.showMsg(window.hWin.HR('resultList_select_record')+limit, {default_palette_class:'ui-heurist-explore'});
        }else{
            return recIDs_list;
        }

    },

    //-------------------------------------- EMAIL FORM -------------------------------
    /**
     * @function openEmailForm
     * @memberof heurist.resultListMenu
     * @instance
     * @description Opens a dialog for sending a bulk email to contacts derived from the
     * currently selected records. Ensures records are selected before proceeding.
     * The selected record IDs are passed to the email form via `window.hWin.HAPI4.selectedRecordIds`.
     */
    openEmailForm: function() {
        // Selection check
        let ids = this.getSelectionIds('resultList_select_record');
        if(window.hWin.HEURIST4.util.isempty(ids)) {
            return;
        }

        // Open URL
        let url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/sendBulkEmail.php?db=" + window.hWin.HAPI4.database;
        window.hWin.HAPI4.selectedRecordIds = ids;  //the only place it is assigned
        window.hWin.HEURIST4.msg.showDialog(url, { width:500, height:600, title: window.hWin.HR('Email information') });

    },

    //-------------------------------------- SELCT ALL, NONE, SHOW -------------------------------

    /**
     * @function selectAll
     * @memberof heurist.resultListMenu
     * @instance
     * @description Selects all records in the current result set context.
     * Updates the internal `_selection` and triggers a global `ON_REC_SELECT` event.
     */
    selectAll: function(){
        this._selection = window.hWin.HAPI4.getSelection('all', false);
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:"all", source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },

    /**
     * @function selectNone
     * @memberof heurist.resultListMenu
     * @instance
     * @description Clears the current selection. Sets `_selection` to null and triggers
     * a global `ON_REC_SELECT` event with a null selection.
     */
    selectNone: function(){
        this._selection = null;
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:null, source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },

    /**
     * @function selectShow
     * @memberof heurist.resultListMenu
     * @instance
     * @description Triggers a global `ON_REC_SELECT` event to show only the currently
     * selected items in the associated result list (by setting `subset_only: true`).
     * This typically filters the result list to display only the `_selection`.
     */
    selectShow: function(){
        if(this._selection!=null){
            let recIDs_list = this._selection.getIds();
            if(recIDs_list.length > 0){
                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                    {selection: recIDs_list, source: this.element.attr('id'), search_realm: this.options.search_realm, subset_only: true});
            }
        }
    },

    /**
     * @function selectShowNewTab
     * @memberof heurist.resultListMenu
     * @instance
     * @description Opens a new browser tab displaying the currently selected records.
     * Constructs a URL with the selected record IDs.
     */
    selectShowNewTab: function(){
        if(this._selection!=null){
            let recIDs_list = this._selection.getIds();
            if (recIDs_list.length > 0) {
                let url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"+recIDs_list.join(',');
                window.open(url, "_blank");
            }
        }
    },

    //-------------------------------------- COLLECTIONS -------------------------------

    /**
     * @function collectionRender
     * @memberof heurist.resultListMenu
     * @instance
     * @description Updates the label of the "Collected" menu button to reflect the current
     * number of items in the collection.
     * @param {Array<number>} _collection An array of record IDs representing the current collection.
     */
    collectionRender: function(_collection) {
        
        this.menu_Collected_link.html( 
                (_collection && _collection.length>0?(window.hWin.HR('Collected')+':'+_collection.length):window.hWin.HR('Collect')) 
                + '<span class="ui-icon ui-icon-carat-d" style="right: 2px; left: unset;">');
    },

    //-------------------------------------- RELATION, MERGE -------------------------------
    /**
     * @function fixDuplicatesPopup
     * @memberof heurist.resultListMenu
     * @instance
     * @description Opens a dialog for merging duplicate records. Requires at least two records
     * to be selected. If prerequisites are met, it opens the `combineDuplicateRecords.php`
     * admin tool in a dialog. Reloads the search upon dialog close if changes were made.
     */
    fixDuplicatesPopup: function(){

        let recIDs_list = this.getSelectionIds(null);
        if(window.hWin.HEURIST4.util.isempty(recIDs_list) || recIDs_list.length<2){
            window.hWin.HEURIST4.msg.showMsg('resultList_select_record2',{default_palette_class:'ui-heurist-explore'});
            return;
        }

        let that = this;
        let url = window.hWin.HAPI4.baseURL 
                    + 'admin/verification/combineDuplicateRecords.php?bib_ids='
                    + recIDs_list.join(",")+"&db=" + window.hWin.HAPI4.database;

        window.hWin.HEURIST4.msg.showDialog(url, {
            width:800, height:550,
            default_palette_class:'ui-heurist-explore',
            title: window.hWin.HR('Combine duplicate records'),
            callback: function(context) {
                that.reloadSearch();
            }
        });

    },

    
    /**
     * @function isResultSetEmpty
     * @memberof heurist.resultListMenu
     * @instance
     * @description Checks if the current result set (globally available via `window.hWin.HAPI4.getSelection("all", true)`)
     * is empty. Displays a message if it is.
     * @returns {boolean} True if the result set is empty, false otherwise.
     */
    isResultSetEmpty: function(){
        let recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsg('resultList_noresult', {default_palette_class:'ui-heurist-explore'});
            return true;
        }else{
            return false;
        }
    },
    

    //------ ADD, REPLACE, DELETE FIELD VALUES, CHANGE RECTYPE -------------------------------

    /**
     * @function detailBatchEditPopup
     * @memberof heurist.resultListMenu
     * @instance
     * @description Opens a dialog for performing various batch edit actions on record details.
     * Ensures that the result set is not empty before proceeding.
     * The specific action is determined by `action_type`.
     * @param {string} action_type The type of batch action to perform (e.g., 'add_detail',
     * 'replace_detail', 'url_to_file', 'rectype_change'). This determines the content/script
     * loaded into the dialog.
     * @param {Function} [callback] Optional callback function to execute after the dialog closes.
     * Default callback may set a flag to refresh tags.
     */
    detailBatchEditPopup: function(action_type, callback) {
        
        if(this.isResultSetEmpty()) return;

        let script_name = 'recordAction';
        
        if(action_type=='add_link'){
            script_name = 'recordAddLink';
            callback = function(context) {
                        if(context!="" && context!=undefined) {
                            let sMsg = (context==true)?'Link created...':context;
                            window.hWin.HEURIST4.msg.showMsgFlash(sMsg, 2000);
                        }
            };            
        /*}else if(action_type=='ownership'){

            var that = this;
            callback = function(context) {
                        if(context!="" && context!=undefined) {
                                that.executeAction( "set_wg_and_vis", context );
                        }
            };*/            
        }else if(!window.hWin.HEURIST4.util.isFunction(callback)){
            callback = function(context){
                window.hWin.HAPI4.NEED_TAG_REFRESH = true; //flag to reload tags in next manageUsrTags invocation
            }
        }
        
        let url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/'+script_name+'.php?'
                +'db='+window.hWin.HAPI4.database+'&action='+action_type;

        let height = action_type == 'case_conversion' ? 750 : 510;
        let width = 900;

        window.hWin.HEURIST4.msg.showDialog(url, {height:height, width:width,
            padding: '0px',
            title: window.hWin.HR(action_type),
            callback: callback,
            default_palette_class:'ui-heurist-explore'} );
    },


});