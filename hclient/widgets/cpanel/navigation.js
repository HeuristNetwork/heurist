/**
* navigation.js - Menu widget
* 
* Menu based on RT_CMS_MENU records it is used for CMS.
*
* @todo - replace with HMenu
* 
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\cpanel
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
 * jQuery UI Widget: heurist.navigation
 *
 * This widget creates a navigation menu based on Heurist RT_CMS_MENU records.
 * It is primarily used for Content Management System (CMS) navigation.
 * The menu can be horizontal, vertical, or a tree view.
 * It handles fetching menu data, rendering the menu, and actions upon menu item selection.
 *
 * @namespace heurist.navigation
 * @property {object} options - Configuration options for the widget.
 * @property {Array<number|string>} options.menu_recIDs - Array of record IDs for the top-level menu items.
 * @property {boolean} options.main_menu - If true, searches for RT_CMS_HOME as the root of the menu (default: false).
 * @property {string} options.orientation - Orientation of the menu: 'horizontal', 'vertical', or 'treeview' (default: 'horizontal').
 * @property {string} options.target - Target for menu actions: 'inline' (loads content into '#page-content' or '#main-content'),
 *                                     'popup', or a specific element ID (default: 'inline').
 * @property {boolean} options.use_next_level - If true and the top level consists of a single entry, uses the next level of menus as the top (default: false).
 * @property {?Function} options.onmenuselect - Callback function triggered when a menu item is selected.
 *                                            Primarily for CMS edit mode. Passes `page_id` as an argument. (default: null).
 * @property {boolean} options.selectable_if_submenu - If true, a menu item with a submenu is still selectable by default (default: true).
 * @property {?Function} options.aftermenuselect - Callback function triggered after a menu item action (like loading content) is completed.
 *                                               Passes `document` and `page_id` as arguments. (default: null).
 * @property {?object} options.toplevel_css - CSS object to apply to top-level menu items (default: null).
 * @property {number} options.expand_levels - Number of levels to initially expand in 'treeview' orientation (default: 0).
 * @property {?Function} options.onInitComplete - Callback function triggered after the menu is fully initialized.
 *                                                Passes `first_not_empty_page_id` as an argument. (default: null).
 * @property {string} options.language - Language code for menu item text (e.g., 'en', 'fr'). 'def' uses the default language (default: 'def').
 * @property {?object} options.supp_options - Supplementary options to pass when initializing page content after load (default: null).
 *
 * @property {?HRecordSet} menuData - Stores the Heurist record set containing the menu data once fetched.
 * @property {object} pageStyles - Stores CSS styles associated with menu page IDs. `{[page_id]: cssObject}`.
 * @property {object} pageStyles_original - Stores original jQuery cloned elements to restore styles when navigating away. `{[target_selector]: jQueryElement}`.
 * @property {object} ids_cached_entries - Caches generated HTML or tree node data for menu items to avoid redundant processing. `{[page_id]: htmlStringOrNodeObject}`.
 * @property {object} ids_menu_entries - Stores an object mapping parent page IDs to an array of their child menu item IDs. Used for recursion detection. `{[page_id]: [child_page_id, ...]}`.
 * @property {Array<number|string>} ids_recurred - Stores page IDs that were detected as part of a recursive menu structure.
 * @property {object} menu_item_urls - Stores external URLs associated with menu items. `{[page_id]: urlString}`.
 * @property {number|string} first_not_empty_page_id - The ID of the first menu item encountered that has content.
 * @property {string} _current_query_string - Internal property, seems unused. TODO: Verify and remove if unused.
 * @property {?jQuery} divMainMenu - jQuery object for the main menu container div, if not treeview.
 * @property {?jQuery} divMainMenuItems - jQuery object for the `<ul>` element containing main menu items, if not treeview.
 */
$.widget( "heurist.navigation", {

    options: {
       menu_recIDs:[],
       main_menu: false,
       orientation: 'horizontal',
       target: 'inline',
       use_next_level: false,
       onmenuselect: null,
       selectable_if_submenu: true,
       aftermenuselect: null,
       toplevel_css:null,
       expand_levels:0,
       onInitComplete: null,
       language: 'def',
       supp_options: null
    },

    menuData: null,

    pageStyles:{},
    pageStyles_original:{},

    ids_cached_entries: {},
    ids_menu_entries: {},
    ids_recurred: [],

    menu_item_urls: {},

    first_not_empty_page_id:0,

    /**
     * The widget's constructor. Initializes the menu element, sets up styles,
     * and prepares for either Fancytree (for 'treeview' orientation) or jQuery UI Menu.
     * Calls `reloadMenuData` to fetch and build the menu.
     * This method is called by jQuery UI when the widget is created.
     * @memberof heurist.navigation
     * @private
     */
    _create: function() {

        let that = this;

        if(!this.options.language) this.options.language = 'def'; // "xx" means use current language

        if(this.element.parent().attr('data-heurist-app-id') || this.element.attr('data-heurist-app-id')){
            //this is CMS publication - take bg from parent
            if(this.element.parent().attr('data-heurist-app-id')){
                this.element.parent().css({'background':'none','border':'none'});
            }

        }else{
            this.element.css('height','100%');
            if(this.element.parents('.main-header').length>0){
                this.element.addClass('ui-heurist-header2');
            }
        }

        this.element.disableSelection();// prevent double click to select text

        if(this.options.orientation=='treeview'){

            let fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: null,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                renderNode: null,
                extensions:[],
                activate: function(event, data) { 
                    //main entry point to start edit rts field - open formlet
                    if(data.node.data.page_id>0){
                        that._onMenuItemAction(data.node.data);    
                    }
                }
            };

            this.element.fancytree(fancytree_options).addClass('tree-cms');

        }else{
            
            this.divMainMenu = $("<div>").appendTo(this.element);
            
            // MAIN MENU-----------------------------------------------------
            this.divMainMenuItems = $('<ul>').attr('data-level',0)
                    //.css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em'})
                    .appendTo( this.divMainMenu );
                    
            if(this.options.orientation=='horizontal'){
                this.divMainMenuItems.addClass('horizontalmenu');
            }
        }

        
        this.reloadMenuData();

    },

    /**
     * Reloads the menu data from the server based on `this.options.menu_recIDs` or `this.options.main_menu`.
     * It makes an AJAX request to fetch CMS menu records.
     * On success, it populates `this.menuData` with an `HRecordSet` and calls `_onGetMenuData` to render the menu.
     * On failure, it displays an error message.
     * @memberof heurist.navigation
     */
    reloadMenuData:function(){

        //find menu contents by top level ids
        let ids = this.options.menu_recIDs;
        if(ids==null){
            this.options.menu_recIDs = [];
            ids = '';
        } else {
            if(Array.isArray(ids)) {ids = ids.join(',');}
            else if(window.hWin.HEURIST4.util.isNumber(ids)){
                this.options.menu_recIDs = [ids];
            }else{
                this.options.menu_recIDs = ids.split(',');
            }
        }

        //retrieve menu content from server side
        let request = {ids:ids, a:'cms_menu', main_menu: this.options.main_menu?1:0 };
        let that = this;


        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                that.menuData = new HRecordSet(response.data);
                that._onGetMenuData();
            }else{
                let errorTarget = that.divMainMenu ? that.divMainMenu : that.element;
                $('<p class="ui-state-error">Can\'t init menu: '+response.message+'</p>').appendTo(errorTarget);

            }
        });
    },

    /**
     * Checks if a given record ID corresponds to a valid menu item in the current `menuData`.
     * @memberof heurist.navigation
     * @param {number|string} rec_id - The record ID to check.
     * @returns {boolean} True if it's a valid menu item, false otherwise.
     */
    isMenuItem: function(rec_id){

        if(this.menuData && rec_id){
            return !window.hWin.HEURIST4.util.isnull(this.menuData.getById(rec_id));
        }else{
            return false;
        }

    },

    /**
     * Recursively generates the menu structure (HTML string or Fancytree node list).
     * It processes `menuitems` (an array of record IDs) for a given `parent_id` and `lvl` (level).
     * It uses `this.menuData` as the source of information.
     * Handles different orientations ('treeview', 'horizontal', 'vertical', 'list').
     * Detects and flags recursive menu structures.
     * Caches generated menu items in `this.ids_cached_entries`.
     * Remark: The condition `if(!lvl>0)` might be confusing; `if(lvl === undefined || lvl === null || lvl <= 0)` or `if(!lvl || lvl <= 0)` would be clearer.
     * @memberof heurist.navigation
     * @param {?string} orientation - The desired orientation ('treeview', 'horizontal', 'vertical', 'list'). Defaults to `this.options.orientation`.
     * @param {string|number} parent_id - The ID of the parent menu item. '0' for top level.
     * @param {Array<number|string>} menuitems - Array of record IDs for the current level of menu items. Defaults to `this.options.menu_recIDs`.
     * @param {?number} lvl - The current nesting level of the menu. Defaults to 0.
     * @returns {string|Array<object>} HTML string for jQuery menu, or an array of Fancytree node objects for 'treeview'/'list'.
     */
    getMenuContent: function(orientation, parent_id, menuitems, lvl){

        if(window.hWin.HEURIST4.util.isnull(parent_id)) parent_id = '0';
        if(window.hWin.HEURIST4.util.isnull(orientation)) orientation = this.options.orientation;
        if(window.hWin.HEURIST4.util.isnull(menuitems)) menuitems = this.options.menu_recIDs; //top menu items
        if(!lvl || lvl == 0){
            lvl = 0;
            //to avoid recursion
            this.ids_menu_entries = {};
            this.ids_cached_entries = {};
            this.ids_recurred = [];
        }

        let resdata = this.menuData;
        parent_id = ''+parent_id;


        let DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
            DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
            DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
            DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
            DT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
            DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'],
            DT_CMS_TARGET = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TARGET'],//target element on page or popup
            DT_CMS_PAGETITLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],//show page title above content
            DT_CMS_TOPMENUSELECTABLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOPMENUSELECTABLE'],//top menu selectable, if a submenu is available
            DT_THUMBNAIL = window.hWin.HAPI4.sysinfo['dbconst']['DT_THUMBNAIL'],
            DT_CMS_MENU_FORMAT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU_FORMAT'],
            
            TERM_NO = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'], //$Db.getLocalID('trm','2-531'),
            TERM_NO_old = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO_OLD'],

            TRM_NAME_ONLY = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NAME_ONLY'],
            TRM_ICON_ONLY = window.hWin.HAPI4.sysinfo['dbconst']['TRM_ICON_ONLY'];

        
        let res = (orientation=='list')?[]:'';
        let resitems = [];

        //submenu selectable is taken from home page
        if(parent_id==0 && menuitems.length==1){ //home page
            let record = resdata.getById(menuitems[0]);
            let selectable = resdata.fld(record, DT_CMS_TOPMENUSELECTABLE);
            if(selectable!==null){
                this.options.selectable_if_submenu = (selectable!==TERM_NO && selectable!==TERM_NO_old);
            }
        }

        for(let i=0; i<menuitems.length; i++)
        {
            
            let record = resdata.getById(menuitems[i]);
            
            if(!record) continue; //record may be non-public or deleted
            
            let page_id = menuitems[i];

            if(Object.hasOwn(this.ids_menu_entries, page_id) && this.ids_menu_entries[page_id].length > 0){ // check recursive references

                let parent_ids = parent_id.split(',');
                /*if(parent_ids.length > 0){
                    parent_ids.filter((id) => this.ids_menu_entries[page_id].indexOf(id));
                }*/
                if(parent_ids.includes(page_id)){
                    this.ids_recurred.push(page_id);
                    continue;
                }
            }

            if(Object.hasOwn(this.ids_cached_entries, page_id)){ // retrieve cached menu item, available

                // first update parent
                let menu_value = this.ids_cached_entries[page_id];

                if(orientation == 'treeview'){

                    function _updateChildNodes(menu_items, new_id){

                        for(let i = 0; i < menu_items.length; i++){
                            menu_items[i]['parent_id'] = ''+new_id;
                            menu_items[i]['key'] = new_id + ',' + menu_items[i]['page_id'];

                            if(menu_items[i]['children'] && menu_items[i]['children'].length > 0){
                                menu_items[i]['children'] = _updateChildNodes(menu_items[i]['children'], menu_items[i]['key']);
                            }
                        }

                        return menu_items;
                    }

                    menu_value['parent_id'] = parent_id;
                    menu_value['key'] = parent_id + ',' + page_id;

                    if(menu_value['children'] && menu_value['children'].length > 0){
                        menu_value['children'] = _updateChildNodes(menu_value['children'], menu_value['key']);
                    }

                    resitems.push(menu_value);
                }else if(orientation != 'list'){

                    let old_parents = menu_value.match(/data-parentid="([\d,]+)"/g);
                    const parent_id_length = parent_id.split(',').length;
                    for(let cur_parent of old_parents){

                        let old_parent = cur_parent.match(/[\d,]+/)[0].split(',');
                        let new_parent = parent_id;

                        if(old_parent.length >= parent_id_length){
                            old_parent = old_parent.slice(parent_id_length);
                            new_parent += (old_parent.length > 0 ? ',' + old_parent.join(',') : '');
                        }

                        menu_value = menu_value.replace(cur_parent, `data-parentid="${new_parent}"`);
                    }

                    res = res + menu_value;
                }else if(orientation == 'list'){
                   
                    continue;
                }

            }else{
            
                let menuName = resdata.fld(record, DT_NAME, this.options.language);
                let menuTitle = resdata.fld(record, DT_SHORT_SUMMARY, this.options.language);
                let menuIcon = resdata.fld(record, DT_THUMBNAIL);

                let menuFormat = resdata.fld(record, DT_CMS_MENU_FORMAT);

                if(Array.isArray(menuIcon)){ // remove empty indexes
                    menuIcon = menuIcon.filter((icon) => icon?.length>4);//!window.hWin.HEURIST4.util.isempty(icon);
                }

                //target and position
                let pageTarget = resdata.fld(record, DT_CMS_TARGET);
                let pageStyle = resdata.fld(record, DT_CMS_CSS);
                let showTitle = resdata.fld(record, DT_CMS_PAGETITLE); 
                
                showTitle = (showTitle!==TERM_NO && showTitle!==TERM_NO_old);
                
                let hasContent = !window.hWin.HEURIST4.util.isempty(resdata.fld(record, DT_EXTENDED_DESCRIPTION));

                if(!(this.first_not_empty_page_id>0) && hasContent){
                    this.first_not_empty_page_id = page_id;
                }

                if(pageStyle){
                    this.pageStyles[page_id] = window.hWin.HEURIST4.util.cssToJson(pageStyle);    
                }
                 
                this.ids_menu_entries[page_id] = [];
                let $res = null;

                if(orientation=='treeview'){
                    $res = {};  
                    $res['key'] = parent_id + ',' + page_id; // set unique key
                    $res['title'] = menuName;
                    $res['parent_id'] = parent_id; //reference to parent menu(or home)
                    $res['page_id'] = page_id;
                    $res['page_showtitle'] = showTitle?1:0;
                    $res['page_target'] = (this.options.target=='popup')?'popup':pageTarget;
                    $res['expanded'] = (this.options.expand_levels>0 || lvl<this.options.expand_levels); 
                    $res['has_access'] = (window.hWin.HAPI4.is_admin() 
                                || window.hWin.HAPI4.is_member(resdata.fld(record,'rec_OwnerUGrpID')));
                                       
                    resitems.push($res);

                }else if(orientation=='list'){
                    
                    $res = {key:page_id, title:window.hWin.HEURIST4.util.htmlEscape(menuName) };

                    res.push($res);
                    
                }else{

                    let iconOnly = false;
                    let nameOnly = false;
                    let iconStyle = 'height:16px;width:16px;vertical-align:text-bottom;';

                    if(menuFormat){
                        iconOnly = menuFormat == TRM_ICON_ONLY;
                        nameOnly = menuFormat == TRM_NAME_ONLY;
                    }
                    iconOnly = iconOnly && !nameOnly && window.hWin.HEURIST4.util.isArrayNotEmpty(menuIcon);
                    
                    if(menuName && menuName.indexOf('<a') !== -1 && menuName.indexOf('</a>') !== -1){

                        let $temp_ele = $('<span>', {style: 'display:none'}).html(menuName);
                        let $a = $temp_ele.find('a[href]:first');

                        if($a.length > 0){

                            let link = $a.attr('href');
                            link = !link.match(/^\w+:\/\//) ? `https://${link}` : link;

                            let is_link = link.match(/^https?|^ftps?|^mailto/);
                            
                            this.menu_item_urls[page_id] = !is_link ? null : link;
                            menuName = !is_link ? menuName : $a.text();
                        }
                    }

                    menuName = window.hWin.HEURIST4.util.htmlEscape(menuName);
                    menuName = !window.hWin.HEURIST4.util.isempty(menuName) ? menuName.replace('&amp;', '&') : menuName;
                    menuName = iconOnly ? `<span style="display:none;">${menuName}</span>` : menuName;

                    menuTitle = window.hWin.HEURIST4.util.htmlEscape(menuTitle);
                    menuTitle = !window.hWin.HEURIST4.util.isempty(menuTitle) ? menuTitle.replace('&amp;', '&') : menuTitle;

                    iconStyle += !iconOnly ? 'padding-right:4px;' : '';

                    $res = '<li><a href="#" style="padding:2px 1em;'
                            +(hasContent?'':'cursor:default;')
                            +(iconOnly?'width:20px;':'')
                            +'" data-pageid="'+ page_id + '" data-parentid="'+ parent_id +'"'
                            + (pageTarget?' data-target="' + pageTarget +'"':'')
                            + ' title="'+menuTitle+'">'

                            + (!nameOnly && menuIcon?('<span><img src="'+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&thumb='+menuIcon+'" '
                                +`style="${iconStyle}"></span>`):'')
                            + menuName+'</a>';
                    res = res + $res;
                }
                    
                let subres = '';
                let submenu = resdata.values(record, DT_CMS_MENU);
                if(!submenu){
                    submenu = resdata.values(record, DT_CMS_TOP_MENU);
                }
                //has submenu
                if(submenu){
                    if(!Array.isArray(submenu)) submenu = submenu.split(',');
                    
                    if(submenu.length>0){ 

                        this.ids_menu_entries[page_id] = submenu;

                        //next level
                        let submenu_parent_id = parent_id != 0 ? parent_id + ',' + page_id : page_id;
                        subres = this.getMenuContent(orientation, submenu_parent_id, submenu, lvl+1);
                        
                        if(orientation=='treeview'){
                            
                            $res['children'] = subres;
                            
                        }else if(orientation=='list'){
                           
                            res = res.concat(subres);
                            
                        } else if(subres!='') {
                            
                            res = res + '<ul style="min-width:200px"' 
                                      + (lvl==0?' class="level-1"':'') + '>'+subres+'</ul>';

                            $res = $res + '<ul style="min-width:200px"' 
                                        + (lvl==0?' class="level-1"':'') + '>'+subres+'</ul>';
                        }
                    }
                }
                
                if(orientation!='list' && orientation!='treeview'){
                    res = res + '</li>';
                }

                this.ids_cached_entries[page_id] = $res;
                
                //if parent has the only child use next level - (for top menu only)
                if(lvl==0 && menuitems.length==1 && this.options.use_next_level){
                        return subres;    
                }
                
            
            }
        }//for
        
        return (orientation=='treeview') ?resitems :res;
        
    },
    
    /**
     * Callback function executed after menu data is successfully fetched by `reloadMenuData`.
     * It resets internal caches related to menu structure and recursion detection.
     * Calls `getMenuContent` to generate the menu structure.
     * If recursion is detected, it shows an error message.
     * Initializes either Fancytree (for 'treeview') or jQuery UI Menu with the generated content.
     * Applies custom CSS and event handlers for jQuery UI Menu.
     * Calls `options.onInitComplete` callback if provided.
     * Remark: The dialogId 'dialog-common-messages222' in the recursion error message seems specific and might be a leftover.
     * @memberof heurist.navigation
     * @private
     */
    _onGetMenuData:function(){

        //reset
        this.ids_menu_entries = {};
        this.ids_cached_entries = {};
        this.ids_recurred = [];
        this.first_not_empty_page_id = 0;

        //get either treedata or html for jquery menu
        let menu_content = this.getMenuContent(null, 0, this.options.menu_recIDs, 0);
        let DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];

        if(this.ids_recurred.length>0 && window.hWin.HAPI4.has_access()){
            let s = [];
            for(let i=0;i<this.ids_recurred.length;i++){
                s.push(this.ids_recurred[i]+' '
                    +this.menuData.fld(this.menuData.getById(this.ids_recurred[i]), DT_NAME));
            }
            window.hWin.HEURIST4.msg.showMsgDlg('Some menu items are recursive references to a menu containing themselves. <br>'
            +'Such a structure is not permissible for obvious reasons. Ask website author to fix this issue. <div style="margin: 10px 0px">'
            +(s.join('<br>'))
            +'</div>If you are the author, simply edit the CMS Home record through the website editor (Site tab, then the Edit website layout/properties button), and delete duplicates (this will not delete the page content, only the extra reference to the menu entry)'
            +'<p>If you can\'t fix this problem yourself, please send a bug report and we will take care of it.</p>'
            ,null,null,{dialogId:'dialog-common-messages222',removeOnClose:true}); // Remark: dialogId is specific.

        }


        if(this.options.orientation=='treeview'){

            let tree = $.ui.fancytree.getTree( this.element );
            if (tree) { // Ensure tree instance exists
                tree.reload( menu_content );
            }
            this.element.find('.ui-fancytree').show();

        }else{

            // Ensure divMainMenuItems exists before appending
            if (!this.divMainMenuItems) {
                this.divMainMenu = $("<div>").appendTo(this.element);
                this.divMainMenuItems = $('<ul>').attr('data-level',0).appendTo( this.divMainMenu );
                if(this.options.orientation=='horizontal'){
                    this.divMainMenuItems.addClass('horizontalmenu');
                }
            }
            this.divMainMenuItems.empty().append(menu_content);


            let opts = {};
            if(this.options.orientation=='horizontal'){
                opts = { position:{ my: "left top", at: "left bottom" },
                        focus: function( event, ui ){

                   if(!$(ui.item).parent().hasClass('horizontalmenu')){
                        //indent for submenu
                        let ele = $(ui.item).children('ul.ui-menu');
                        if(ele.length>0){
                            setTimeout(function() { ele.css({top:'0px',  left:'200px'}); }, 300);
                        }
                   }else {
                        //show below
                        let ele = $(ui.item).children('ul.ui-menu');
                        if(ele.length>0){
                            setTimeout(function() { ele.css({top:'29px',  left:'0px'}); }, 500);
                        }
                   }
                }};

            }


            opts['icons'] = {submenu: "ui-icon-carat-1-e" };
            //init jquery menu widget
            this.divMainMenuItems.menu( opts );

          //prevents default jquery delay
          this.divMainMenuItems.children('li.ui-menu-item')
            .on( "mouseenter", function(event) {
                    event.preventDefault();
                    $(this).children('.ui-menu').show();
                } )
            .on( "mouseleave", function(event) {
                    event.preventDefault();
                    $(this).find('.ui-menu').hide();
                } );

            if(this.options.toplevel_css!==null){
                this.divMainMenuItems.children('li.ui-menu-item').children('a').css(this.options.toplevel_css);
            }

            if(this.options.orientation=='horizontal'){
                this.divMainMenuItems.children('li.ui-menu-item').children('a').find('span.ui-menu-icon').hide();
            }

            //
            // if onmenuselect function define it is used for action
            // otherwise it loads content to page_target (#main-content by default)
            //
            this._on(this.divMainMenuItems.find('a').addClass('truncate'),{click:this._onMenuClickEvent});
        }


        if(window.hWin.HEURIST4.util.isFunction(this.options.onInitComplete)){
            this.options.onInitComplete.call(this, this.first_not_empty_page_id);
        }



    }, //end _onGetMenuData

    /**
     * Handles click events on menu items (for non-treeview orientations).
     * It extracts page ID and target information from the clicked anchor's data attributes.
     * Determines if the item is selectable based on content presence and submenu status.
     * If an external URL is associated with the item, it opens it in a new tab.
     * Otherwise, if selectable and has content (or an `onmenuselect` callback is defined),
     * it calls `highlightTopItem` and `_onMenuItemAction`.
     * @memberof heurist.navigation
     * @private
     * @param {Event} event - The jQuery click event object.
     */
    _onMenuClickEvent: function(event){

        let $target = $(event.target);

        window.hWin.HEURIST4.util.stopEvent(event);

        if($target.is('span') || $target.is('img')){
            $target = $target.parents('[role="menuitem"]');
        }

        let data = {
            page_id: $target.attr('data-pageid'),
            page_target: $target.attr('data-target')
        };

        //hide submenu
        $target.parents('.ui-menu[data-level!=0]').hide();

        const record = this.menuData.getRecord(data.page_id);
        const DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
              DT_CMS_PAGETITLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],
              TERM_NO = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'],
              TERM_NO_old = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO_OLD'];

        // show page title
        let showTitle = this.menuData.fld(record, DT_CMS_PAGETITLE);
        data.page_showtitle = (showTitle!==TERM_NO && showTitle!==TERM_NO_old);
        // page has content
        data.hasContent = !window.hWin.HEURIST4.util.isempty(this.menuData.fld(record, DT_EXTENDED_DESCRIPTION));

        // menu is selectable
        let is_selectable = this.menuData.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOPMENUSELECTABLE']);
        is_selectable = data.hasContent &&
                        is_selectable !== TERM_NO && is_selectable !== TERM_NO_old &&
                        this.options.selectable_if_submenu;

        if(Object.hasOwn(this.menu_item_urls, data.page_id) &&
            !window.hWin.HEURIST4.util.isempty(this.menu_item_urls[data.page_id])){ // open url in new window

            window.open(this.menu_item_urls[data.page_id], '_blank', 'noopener');
            return;
        }else if(!is_selectable && $target.parent().find('ul').length != 0){ // stop click if a submenu exists
            return;
        }
        if(!data.hasContent && !window.hWin.HEURIST4.util.isFunction(this.options.onmenuselect)){
            //no action if content is not defined

        }else if(data.page_id>0){

            let page_id_path = data.page_id; // Use a different variable name for the path
            if($target.attr('data-parentid')){
                page_id_path = $target.attr('data-parentid') + ',' + page_id_path;
            }

            //highlight top most menu
            this.highlightTopItem(page_id_path);

            this._onMenuItemAction(data);

        }

    },

    /**
     * Highlights the top-most parent menu item for a given page ID path.
     * It removes the 'selected' class from all menu items and adds it to the
     * appropriate top-level item. Also collapses other menu branches.
     * @memberof heurist.navigation
     * @param {string} page_id_path - A comma-separated string representing the path of page IDs from parent to child.
     */
    highlightTopItem: function(page_id_path){

        if (!this.divMainMenuItems) return; // Guard against missing menu items container

        //dim all
        this.divMainMenuItems.find('a').trigger('mouseout').removeClass('selected');

        // find item
        let $ele = null;
        if(typeof page_id_path === 'string' && page_id_path.indexOf(',') > 0){

            let page_ids = page_id_path.split(',');
            let target_page_id = page_ids.pop(); // The actual page ID
            let parent_id_path = page_ids.join(','); // The parent path for specificity

            $ele = this.element.find(`a[data-pageid="${target_page_id}"][data-parentid="${parent_id_path}"]`).parents('.ui-menu-item');
        }else if(page_id_path && page_id_path > 0){ // Should be a string if it contains ',', otherwise a number/string page_id

            $ele = this.element.find('a[data-pageid="'+page_id_path+'"]');
            $ele = $ele.parents('.ui-menu-item');
        }

        if($ele && $ele.length>0){
            // The last element in $ele is the top-most li.ui-menu-item in its branch.
            // We want to highlight its child 'a' tag.
            $($ele[$ele.length-1].firstChild).addClass('selected');
            setTimeout(() => {
                    if(this.divMainMenuItems.menu('instance')) // Check if menu instance exists
                        this.divMainMenuItems.menu('collapseAll');
            }, 1000);
        }
    },

    /**
     * Performs the action associated with a selected menu item.
     * If `options.onmenuselect` is defined, it calls it with the `page_id`.
     * Otherwise, it loads the content of the selected page.
     * Content can be loaded into a popup dialog or an inline target element
     * (e.g., '#main-content', '#page-content', or a custom target from `data.page_target`).
     * Handles applying page-specific CSS and restoring original styles.
     * Triggers `onexitpage` event on the target element before loading new content if the event is bound.
     * After content is loaded, it initializes layout and calls `options.aftermenuselect`.
     * @memberof heurist.navigation
     * @private
     * @param {object} data - An object containing details about the selected menu item.
     * @param {string|number} data.page_id - The ID of the selected page/menu item.
     * @param {?string} data.page_target - The target element or 'popup' for content loading.
     * @param {boolean} data.page_showtitle - Whether to show the page title.
     * @param {boolean} data.hasContent - Whether the page has content.
     */
    _onMenuItemAction: function(data){

        let that = this;

        if(window.hWin.HEURIST4.util.isFunction(that.options.onmenuselect)){

            this.options.onmenuselect( data.page_id );

        }else{

            // redirected to websiteRecord.php
            // with field=1 it loads DT_EXTENDED_DESCRIPTION
            let page_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
            +'&field=1&recid='+data.page_id;

            let pageCss = that.pageStyles[data.page_id];

            if(data.page_target=='popup' || this.options.target=='popup'){


                let opts =  {  container:'cms-popup-'+window.hWin.HEURIST4.util.random(),
                    close: function(){
                        if ($dlg && $dlg.dialog('instance')) { // Check if dialog exists and is initialized
                            $dlg.dialog('destroy');
                        }
                        if ($dlg) $dlg.remove();
                    },
                    open: function(){

                        let pagetitle = $dlg.find('h2.webpageheading');
                        if(pagetitle.length>0){ //find title - this is first children

                            if(!data.page_showtitle){
                                pagetitle.hide();
                            }
                        }

                        window.hWin.HAPI4.LayoutMgr.appInitFromContainer2( $dlg );
                    }
                };

                let dlg_css = null;
                if(pageCss){

                    if(pageCss['position']){
                        let val = window.hWin.HEURIST4.util.isJSON(pageCss['position']);
                        if(val==false){
                            delete pageCss['position'];
                        }else{
                            pageCss['position'] = val;
                        }
                    }
                    opts = $.extend(opts, pageCss);

                    dlg_css = window.hWin.HEURIST4.util.cloneJSON(pageCss);
                    if(dlg_css['width']) delete dlg_css['width'];
                    if(dlg_css['height']) delete dlg_css['height'];

                }else{
                    opts['width']= 750;
                }


                let $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(page_url, null,
                    'Heurist', opts, dlg_css);

                if(dlg_css && $dlg){ // Ensure $dlg exists
                    $dlg.css(dlg_css);
                }


            }
            else{

                let page_target_selector = '#main-content'; // Renamed to avoid confusion

                if(this.options.target=='inline_page_content'){
                    page_target_selector = '#page-content';
                }else if(!window.hWin.HEURIST4.util.isempty(data.page_target)) {
                    page_target_selector = data.page_target;
                }

                //load page content to page_target element
                if(page_target_selector.startsWith('#')) page_target_selector = '#'+page_target_selector;


                let continue_load_page = function() {
                    let $page_target_element = $(page_target_selector); // Get jQuery object once

                    if(pageCss && Object.keys(pageCss).length>0){
                        if(!that.pageStyles_original[page_target_selector]){ //keep to restore
                            that.pageStyles_original[page_target_selector] = $page_target_element.clone();

                        }
                        $page_target_element.css(pageCss);
                    }else if(that.pageStyles_original[page_target_selector]){ //restore

                        $page_target_element.replaceWith(that.pageStyles_original[page_target_selector]);
                        // After replacing, the original $page_target_element reference is stale.
                        // Re-select it if needed for further operations within this function,
                        // or ensure operations happen on the new element from pageStyles_original.
                        // For now, subsequent operations are on the potentially new element.
                        $page_target_element = $(page_target_selector); // Re-query if necessary for footer logic
                    }

                    let page_footer = $page_target_element.find('#page-footer');
                    if(page_footer.length>0) page_footer.detach();

                    const DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                    DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];

                    const server_request = {
                        q: 'ids:'+data.page_id,
                        restapi: 1,
                        columns:
                        ['rec_ID', DT_NAME, DT_EXTENDED_DESCRIPTION],
                        zip: 1,
                        format:'json'};

                    //perform search see record_output.php
                    window.hWin.HAPI4.RecordMgr.search_new(server_request,
                        function(response){

                            if(window.hWin.HEURIST4.util.isJSON(response)) {
                                if(response['records'] && response['records'].length>0){
                                    let res = response['records'][0]['details'];
                                    let keys = Object.keys(res);
                                    for(let idx in keys){
                                        let key = keys[idx];
                                        res[key] = res[key][ Object.keys(res[key])[0] ];
                                    }

                                    if(page_footer.length>0){
                                        page_footer.appendTo( $page_target_element ); // Use the potentially updated reference
                                        $page_target_element.css({'min-height':$page_target_element.parent().height()-page_footer.height()-10 });
                                    }

                                    window.hWin.HAPI4.layoutMgr.layoutInit( res[DT_EXTENDED_DESCRIPTION], $page_target_element, that.options.supp_options );

                                    if(window.hWin.HEURIST4.util.isFunction(that.options.aftermenuselect)){
                                        that.options.aftermenuselect( document, data.page_id );
                                    }
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr({
                                        message: `Web Page not found (record #${data.page_id})`,
                                        error_title: 'Failed to load page'
                                    });
                                }
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });


                };

                //before load we trigger function
                let $page_target_element_for_event = $(page_target_selector);
                let event_assigned = false;

                if ($page_target_element_for_event.length > 0) { // Check if target element exists
                    $.each($._data( $page_target_element_for_event[0], "events"), function(eventname, event) {
                        if(eventname=='onexitpage'){
                            event_assigned = true;
                            return false; // break the loop
                        }
                    });
                }

                if(event_assigned){
                    $page_target_element_for_event.trigger( "onexitpage", continue_load_page );
                }else{
                    continue_load_page();
                }
            }
        }
    },


    /**
     * Placeholder for the _init method.
     * Remark: In jQuery UI widgets, `_create` is the main initialization method.
     * This `_init` method is part of the widget factory's lifecycle but typically not overridden
     * unless there's a specific need to hook into re-initialization calls.
     * Currently, it's empty and doesn't perform any operations.
     * @memberof heurist.navigation
     * @private
     */
    _init: function() {
        // This is called on widget creation and on subsequent calls without arguments.
        // _create() is the primary constructor.
    },

   /**
    * Handles option changes for the widget.
    * This method is called by jQuery UI when `option()` is called on the widget.
    * It calls `_superApply` to apply the changed options.
    * @memberof heurist.navigation
    * @private
    */
   _setOptions: function( ) {
        this._superApply( arguments );
        // Potentially call _refresh() or other methods if options change requires UI update.
   },


   /**
    * Placeholder for a refresh method.
    * Remark: This method is currently empty and does not perform any actions.
    * It could be implemented to update the widget based on external changes or option modifications.
    * @memberof heurist.navigation
    * @private
    */
   _refresh: function(){
        // This method could be used to redraw or update the menu if needed.
        // For example, if menuData could change dynamically after initialization.
   },

   /**
    * Cleans up the widget when it is destroyed.
    * Removes the main menu container (`divMainMenu`) if it exists.
    * This method is called by jQuery UI when the widget is destroyed.
    * @memberof heurist.navigation
    * @private
    */
   _destroy: function() {
        if(this.divMainMenu) {
            this.divMainMenu.remove();
            this.divMainMenu = null; // Clear reference
        }
        if (this.element.data('fancytree')) { // Destroy Fancytree instance if it exists
            this.element.fancytree('destroy');
        }
   },

   /**
    * Retrieves the ID of the first menu item found that has content.
    * This value is populated during the menu generation process.
    * @memberof heurist.navigation
    * @returns {number|string} The record ID of the first contentful page, or 0 if none found.
    */
   getFirstPageWithContent: function(){
        return this.first_not_empty_page_id;
   }

});
