/*
* HLayoutMgr.js - web page generator based on json configuration
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
/* global prepareTemplateBlog, layoutMgr */


/*
* HLayoutMgr.js - web page generator based on JSON configuration
*/

class HLayoutMgr {
    
    pnl_counter;
    body;
    isEditMode = false;
    _supp_options = {};
    _main_layout_cfg = null;
    
    
  /**
   * Initializes the HLayoutMgr instance.
   * Sets up initial properties for managing layout generation.
   */
  constructor() {
    /** @property {number} pnl_counter - A counter to generate unique keys for layout elements. */
    this.pnl_counter = 1;
    /** @property {jQuery} body - jQuery object representing the document body. */
    this.body = $(document).find("body");
    /** @property {boolean} isEditMode - Flag indicating if the layout manager is in edit mode. */
    this.isEditMode = false;
    /** @property {Object} _supp_options - Supplementary options for widget initialization, often passed from CMS context. */
    this._supp_options = {};
    /** @property {Array<Object>|null} _main_layout_cfg - Stores the top-level layout configuration, especially in edit mode. */
    this._main_layout_cfg = null;
  }

  /**
   * Assigns a unique key, title, and folder status to a layout element if it doesn't already have a key.
   * The key is generated using an internal counter (`this.pnl_counter`).
   *
   * @private
   * @param {Array<Object>} layout - The array of layout configuration objects.
   * @param {number} i - The index of the current element in the `layout` array.
   * @returns {void} Modifies the `layout[i]` object in place.
   */
  #layoutInitKey(layout, i) {
    if (!layout[i].key) {
      layout[i].key = this.pnl_counter;
      // Title is often used for tree views or editable labels in a CMS editor
      layout[i].title = `<span data-lid="${this.pnl_counter}">${layout[i].name}</span>`;
      layout[i].folder = layout[i].children?.length > 0; // Mark as folder if it has children
      this.pnl_counter++;
    }
  }
  
  /**
   * Initializes layout elements from existing HTML content.
   * It searches for elements with `data-heurist-widget` or `data-heurist-app-id` attributes
   * and initializes them as Heurist widgets.
   *
   * @private
   * @param {jQuery|string} container - The jQuery object or selector for the container whose HTML content needs to be processed.
   * @returns {void}
   */
  #layoutInitFromHTML(container){

      container = $(container);

      //find all elements with data-heurist-widget
      $.each(container.find('[data-heurist-widget]'), (idx, ele) => {
          ele = $(ele);
          
          let widget_cfg = window.hWin.HEURIST4.util.isJSON(ele.attr('data-heurist-widget'));
          
          if(!widget_cfg){
              widget_cfg = window.hWin.HEURIST4.util.isJSON(ele.text());
              if(!widget_cfg){
                    widget_cfg = {};
              }
              widget_cfg.appid = ele.attr('data-heurist-widget');
          }
          
          if(widget_cfg && widget_cfg.appid){
               if(!widget_cfg.options){
                   widget_cfg = {appid:widget_cfg.appid, options:widget_cfg};
               }
               widget_cfg.key = this.pnl_counter;
               this.pnl_counter++;
               ele.attr('data-hid', widget_cfg.key);
               this.#layoutInitWidget(widget_cfg, ele);
          }
      });
      
      //find all elements with data-heurist-app-id
      if (container.find('[data-heurist-app-id]').length>0) {
            //old format v1: html with some widgets
            window.hWin.HAPI4.LayoutMgr.appInitFromContainer(null, container, this._supp_options);
      }

  }
  
  /**
   * Initializes or generates HTML structure from a JSON layout configuration.
   * This is a core recursive function that processes the layout array and
   * delegates to specific handlers based on element type (group, text, widget, tabs, etc.).
   *
   * @private
   * @param {Array<Object>|string} layout - The JSON layout configuration (array of objects) or a string to be treated as simple text content.
   * @param {jQuery|HTMLElement|null} container - The parent jQuery element or HTMLElement where the generated HTML should be appended.
   *                                            If null, a new div is created.
   * @param {boolean} [forStorage=false] - If true, generates HTML suitable for storage (e.g., in a database),
   *                                     which might include more metadata as attributes. If false, generates live DOM elements.
   * @param {boolean} [isFirstLevel=false] - True if this is the first (top-level) call to the function,
   *                                       used for special handling like page naming or preserving top-level config.
   * @returns {Array<Object>|string|false} If `forStorage` is true, returns the HTML string.
   *                                       If `forStorage` is false, returns the processed layout JSON array (or false if input was invalid HTML).
   *                                       Modifies the `container` by appending new DOM elements if not `forStorage`.
   */
  #layoutInitFromJSON(layout, container, forStorage, isFirstLevel) {
    if (container == null) {
      container = document.createElement("div"); // Default container if none provided
    }
    container = $(container);

    if (layout == null) {
      layout = container.text();
    }

    container.empty();

    const res = layout!==null && window.hWin.HEURIST4.util.isJSON(layout);
    if (res === false) 
    {
        if (forStorage) {
            return layout;
        } else if (typeof layout === "string" && layout.indexOf("data-heurist-app-id") > 0) {
            
            //old format v1: html with some widgets
            container.html(layout);
            window.hWin.HAPI4.LayoutMgr.appInitFromContainer(null, container, this._supp_options);
            return false;
        }

        layout = [
            {
                name: "Page",
                type: "group",
                children: [{ name: "Content", type: "text", css: {}, content: layout }],
            },
        ];
    } else {
        layout = res; //json array
    }

    if (!Array.isArray(layout)) {
      layout = [layout];
    }

    if (isFirstLevel === true) {
      if (this._supp_options.page_name) {
        layout[0].name = "Page";
      }
      if (this._supp_options.keep_top_config && this.isEditMode) {
        this._main_layout_cfg = layout;
      }
    }

    for (let i = 0; i < layout.length; i++) {
      this.#layoutInitKey(layout, i);

      const ele = layout[i];
      switch (ele.type) {
        case "cardinal":
          this.#layoutInitCardinal(ele, container, forStorage);
          break;
        case "tabs":
          this.#layoutInitTabs(ele, container, forStorage);
          break;
        case "accordion":
          this.#layoutInitAccordion(ele, container, forStorage);
          break;
        default:
          if (ele.children && ele.children.length > 0) {
            this.#layoutInitGroup(ele, container, forStorage);
          } else if ((ele.type && ele.type.indexOf("text") === 0) || ele.content) {
            this.#layoutInitText(ele, container, forStorage);
          } else if (ele.type === "widget" || ele.appid) {
            this.#layoutAddWidget(ele, container, forStorage);
          }
      }
    }

    if (forStorage) {
      return container.html();
    } else {
      if (isFirstLevel && this._supp_options && !this._supp_options.heurist_isJsAllowed) {
//remove all javascript event attributes
        this.#layoutSanitize(container);
      }
      return layout;
    }
  }

  /**
   * Recursively sanitizes a container and its children by removing all "on*" (event handler) attributes.
   * This is a security measure to prevent XSS attacks when content might be user-generated.
   *
   * @private
   * @param {jQuery} container - The jQuery object representing the container to sanitize.
   * @returns {void} Modifies the DOM elements in place.
   */
  #layoutSanitize(container) {
    $.each(container.children(), (idx, ele) => {
      ele = $(ele);
      this.#layoutSanitize(ele); // Recursive call for children
    });

    const ele2 = container.get(0); // Get the raw DOM element
    if (ele2 && ele2.attributes) {
        for (let i = ele2.attributes.length - 1; i >= 0; i--) { // Iterate backwards as attributes list might change
            if (ele2.attributes[i].name.indexOf("on") === 0) {
                ele2.removeAttribute(ele2.attributes[i].name);
            }
        }
    }
  }

  /**
   * Creates a new div element based on the layout configuration.
   * Assigns ID, data attributes (data-hid, data-cms-name, data-cms-type), and classes.
   * Handles specific logic for `dom_id` if it starts with "cms-tabs-", ensuring uniqueness.
   *
   * @private
   * @param {Object} layout - The layout configuration object for the element.
   *                          Expected properties: `dom_id`, `key`, `name`, `type`, `appid` (optional), `classes` (optional).
   * @param {string} [classes=''] - Additional CSS classes to add to the created div.
   * @param {boolean} [forStorage=false] - If true, creates a div string with more data attributes for storage purposes.
   *                                     Otherwise, creates a live jQuery DOM element.
   * @returns {jQuery} The created jQuery div element.
   */
  #layoutCreateDiv(layout, classes, forStorage) {
    if (layout.dom_id && layout.dom_id.indexOf("cms-tabs-") === 0) {
      // id is reassigned on every page reload for tab-like structures to maintain stability
      layout.dom_id = `cms-tabs-${layout.key}`;
    }

    let $d;

    if (forStorage) {
      // Attributes for storage:
      // key - unique id within edit session (assigned every time layout is recreated in edit mode)
      // dom_id - unique HTML id
      // name - data-cms-name
      // type - data-cms-type
      // css - (applied separately or stored in JSON)
      // classes - additional classes
      $d = $(
        `<div id="${layout.dom_id || ''}" data-cms-name="${layout.name || ''}" data-cms-type="${layout.type || ''}"></div>`
      );
    } else {
      $d = $(document.createElement("div"));

      if (!layout.dom_id) {
        let uid = "" + window.hWin.HEURIST4.util.random();
        // Ensure generated dom_id is unique
        do {
          layout.dom_id = layout.appid
            ? `cms-widget-${uid}` // Prefix for widget containers
            : `cms-content-${uid}`; // Prefix for general content containers
        } while (this.body.find(`#${layout.dom_id}`).length > 0);
      }

      $d.attr("id", layout.dom_id).attr("data-hid", layout.key); // data-hid links to the layout key

      if (classes) {
        $d.addClass(classes);
      }
    }

    if (layout.classes) {
      $d.addClass(layout.classes);
    }

    return $d;
  }

  /**
   * Initializes a group element. A group is a container for other layout elements.
   * Creates a div for the group, applies CSS, and then recursively calls `#layoutInitFromJSON`
   * for its children.
   *
   * @private
   * @param {Object} layout - The layout configuration object for the group. Must have a `children` array.
   * @param {jQuery} container - The parent jQuery element where this group div will be appended.
   * @param {boolean} [forStorage=false] - Passed to `#layoutCreateDiv` and `#layoutInitFromJSON`.
   * @returns {void}
   */
  #layoutInitGroup(layout, container, forStorage) {
    const $d = this.#layoutCreateDiv(layout, "cms-element brick", forStorage);
    $d.appendTo(container);

    if (!layout.css) layout.css = {};
    if (layout.css && !$.isEmptyObject(layout.css)) {
      $d.css(layout.css);
    }

    this.#layoutInitFromJSON(layout.children, $d, forStorage);
  }

  /**
   * Initializes a text element. Creates a div, applies CSS, and sets its HTML content.
   * Handles multilingual content if present in the layout configuration (e.g., `contenten`, `contentfr`).
   *
   * @private
   * @param {Object} layout - The layout configuration object for the text element. Expected to have `content` or `content<lang>` properties.
   * @param {jQuery} container - The parent jQuery element where this text div will be appended.
   * @param {boolean} [forStorage=false] - If true, structures multilingual content within separate divs with `data-lang`.
   *                                     Otherwise, selects content based on `this._supp_options.lang`.
   * @returns {void}
   */
  #layoutInitText(layout, container, forStorage) {
    const $d = this.#layoutCreateDiv(
      layout,
      "editable tinymce-body cms-element brick", // Classes for styling and rich text editor integration
      forStorage
    );
    $d.appendTo(container);

    if (!layout.css) layout.css = {};
    if (layout.css && !$.isEmptyObject(layout.css)) {
      $d.css(layout.css);
    }

    let content = "content"; // Default content property name
    if (forStorage) {
      // Find all content properties (e.g., content, contenten, contentfr)
      const aLangs = Object.keys(layout).filter((key) =>
        key.indexOf("content") === 0
      );

      if (aLangs.length > 1) { // Multiple languages found
        aLangs.forEach((langKey) => {
          const lang_code = langKey.substring(7) || "def"; // Extract lang code (e.g., "en", "fr", or "def" for default)
          $(
            // Create a div for each language
            `<div style="${ // Use style attribute for initial visibility if forStorage
              lang_code === "def" ? "" : "display:none"
            }" data-lang="${lang_code}">${layout[langKey]}</div>`
          ).appendTo($d);
        });
      } else if (aLangs.length === 1) { // Single content property
        $d.html(layout[aLangs[0]]);
      } else {
        $d.html(''); // No content property found
      }
    } else { // Not for storage, rendering live
      if (this._supp_options["lang"]) {
        const lang = window.hWin.HAPI4.getLangCode3(
          this._supp_options["lang"],
          "def" // Default to 'def' if specified lang not found
        );
        if (layout[content + lang]) { // Check if 'content<lang>' exists
          content = content + lang;
        }
        $d.attr("data-lang", lang); // Set data-lang attribute on the live element
      }
      $d.html(layout[content] || ''); // Set HTML content, use empty string if undefined
    }
  }
  
  /**
   * Adds and initializes a widget specified in the layout configuration.
   * It creates a container div for the widget, applies CSS, and then calls `#layoutInitWidget`
   * to initialize the widget's JavaScript component.
   *
   * @private
   * @param {Object} layout - The layout configuration object for the widget. Must have `appid` and optionally `options` and `css`.
   * @param {jQuery} container - The parent jQuery element where the widget div will be appended or replaced.
   * @param {boolean} [forStorage=false] - If true, prepares the div for storage (not fully implemented in this snippet, primarily affects `#layoutCreateDiv`).
   *                                     When false (default), initializes the widget live.
   * @returns {void}
   */
 #layoutAddWidget(layout, container, forStorage){ // Note: forStorage is not used in this specific method but is part of the pattern

        let $d = this.#layoutCreateDiv(layout, 'editable heurist-widget cms-element brick');

        //remove previous one
        let old_widget = container.find('div[data-hid='+layout.key+']');
        if(old_widget.length>0){
            $d.insertBefore(old_widget);
            old_widget.remove();
        }else{
            $d.appendTo(container);    
        }
        
        
        if(!layout.css){
            layout.css  = {};    
            layout.css['minHeight'] = '100px';
           
        } 
        if(!layout.css['position']) layout.css['position'] = 'relative';
        
        //default values for various widgets
        /*
        if(layout.appid=='heurist_Map' ||  layout.appid=='heurist_SearchTree' || 
           layout.appid=='heurist_resultList' || layout.appid=='heurist_resultListExt'){
        }
        
        if(layout.appid=='heurist_Search'){
            if(layout.css['display']!='flex'){
               
            }
            if(!layout.css['width']){
               
            }
        }else if(layout.appid=='heurist_Map'){
            if(!layout.css['height']){
               
            }
        }*/

        
        //default min-height position depends on widget
        let app = this.#getWidgetById(layout.appid);
        if(app.minw>0 && !layout.css['minWidth']){
            layout.css['minWidth'] = app.minw;
        }
        if(app.minh>0 && !layout.css['minHeight']){
            layout.css['minHeight'] = app.minh;
        }

        if(layout.css && !$.isEmptyObject(layout)){
            
            $d.removeAttr('style');
            $d.css( layout.css );    
        }
        
        this.#layoutInitWidget(layout, container.find('div[data-hid='+layout.key+']'));

    }
    
    /**
     * Retrieves widget definition/description from the global `cfg_widgets` array.
     * This configuration object typically contains the widget's name, path to its JavaScript file,
     * default options, and other metadata.
     *
     * @private
     * @param {string} id - The ID of the widget to find (e.g., "heurist_SearchInput").
     * @returns {Object|null} The widget configuration object if found, otherwise null.
     */
    #getWidgetById(id){

        let i;
        for(i=0; i<window.hWin.cfg_widgets.length; i++){
            if(window.hWin.cfg_widgets[i].id==id){
                return window.hWin.cfg_widgets[i];
            }
        }
        return null;
    }
    
    /**
     * Initializes a specific widget on a given container element.
     * It retrieves the widget's configuration, merges options (including supplementary options),
     * and then dynamically loads the widget's script if not already loaded,
     * finally calling the widget's initialization function (jQuery plugin).
     *
     * @private
     * @param {Object} layout - The layout configuration object for this widget instance.
     *                          Must contain `appid` and optionally `options`.
     * @param {jQuery} container - The jQuery element that will host the widget.
     * @returns {void}
     */
    #layoutInitWidget(layout, container){

        const app = this.#getWidgetById(layout.appid); // Find in global widget configurations (appid is e.g., heurist_Search)

        if(!layout.options) layout.options = {};
        
        // Special default options for certain widgets
        if(layout.appid=='heurist_Map'){
            layout.options['leaflet'] = true;
            layout.options['init_at_once'] = true;
        }
        
        // Merge supplementary options if provided for this specific appid
        if(this._supp_options[layout.appid]){
            layout.options = $.extend(true, {}, layout.options, this._supp_options[layout.appid]); // Deep extend
            
            if(layout.appid=='heurist_Navigation'){
                // Keep all supplementary options separately for Navigation as they might be required for page init
                layout.options['supp_options'] = this._supp_options;
            }
        }
        
        // Set language for the widget if specified in supplementary options
        if(this._supp_options['lang']){
            // 'xx' means it will use current language resolved by HAPI4
            layout.options['language'] = window.hWin.HAPI4.getLangCode3(this._supp_options['lang'],'def');    
        }
        
        if (app && app.script && app.widgetname) { // widgetname is the jQuery plugin function name to initialize the widget

            if(window.hWin.HEURIST4.util.isFunction(container[app.widgetname])){ // Check if widget script (plugin) is already loaded
                try {
                    container[app.widgetname]( layout.options );   // Call the widget's initialization function
                    container.attr('data-widgetname',app.widgetname); // Mark container with widget name
                } catch (e) {
                    console.error(`Error initializing widget ${app.widgetname} immediately:`, e, layout.options);
                     window.hWin.HEURIST4.msg.showMsgErr({
                        message: `Error initializing widget ${app.widgetname}: ${e.message}. Check console for details.`,
                        error_title: 'Widget Initialization Error',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }
            }else{
                // Widget script not yet loaded, load it dynamically
                $.getScript( window.hWin.HAPI4.baseURL + app.script) // Appending timestamp '?t=' removed as it's usually handled by server cache settings
                    .done(() => {
                        if(window.hWin.HEURIST4.util.isFunction(container[app.widgetname])){
                             try {
                                container[app.widgetname]( layout.options );   // Call function after script loads
                                container.attr('data-widgetname',app.widgetname);
                             } catch (e) {
                                console.error(`Error initializing widget ${app.widgetname} after script load:`, e, layout.options);
                                 window.hWin.HEURIST4.msg.showMsgErr({
                                    message: `Error initializing widget ${app.widgetname} after script load: ${e.message}. Check console.`,
                                    error_title: 'Widget Initialization Error',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                             }
                        }else{
                            console.error(`Widget function ${app.widgetname} not found after loading script ${app.script}.`);
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: `Widget function ${app.widgetname} not found after loading script ${app.script}. Verify your configuration.`,
                                error_title: 'Widget Loading Failed',
                                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                            });
                        }
                    })
                    .fail((jqxhr, settings, exception) => {
                        console.error(`Failed to load script ${app.script}:`, exception);
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Failed to load script for widget ${app.widgetname} (${app.script}). Error: ${exception}.`,
                            error_title: 'Script Loading Failed',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    });
            }
        } else if (app) {
            console.warn(`Widget configuration for ${layout.appid} is missing 'script' or 'widgetname'.`, app);
        } else {
            console.warn(`Widget with appid "${layout.appid}" not found in cfg_widgets.`);
        }
    }
  
  /**
   * Initializes a cardinal layout (e.g., N, S, E, W, Center panes).
   * It uses the jQuery UI Layout plugin to create resizable and collapsible panes.
   * The configuration for each pane (size, resizable, min/max size, initial state)
   * is derived from the `layout.children` objects.
   *
   * @private
   * @param {Object} layout - The layout configuration object for the cardinal layout.
   *                          It should have a `children` array, where each child represents a pane (north, south, etc.).
   * @param {jQuery} container - The parent jQuery element where the cardinal layout structure will be built.
   * @param {boolean} [forStorage=false] - If true, generates HTML structure for storage.
   *                                     Otherwise, initializes the jQuery UI Layout plugin.
   * @returns {void}
   */
  #layoutInitCardinal(layout, container, forStorage){
      
        let $d, $parent;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove(); //remove itself
        }
        
        //create parent div
        $parent = this.#layoutCreateDiv(layout, '', forStorage);
        
        if( layout.css && !$.isEmptyObject(layout.css) ){
            $parent.css( layout.css );
        }
        
        $parent.appendTo(container);
        
        
        let layout_opts = {applyDefaultStyles: true, maskContents: true};
    
        for(let i=0; i<layout.children.length; i++){
            
            this.#layoutInitKey(layout.children, i);
            
            let lpane = layout.children[i];
            let pos = lpane.type;
            
            let opts = lpane.options;
            if(!opts) opts = {};
            
            if(!$.isEmptyObject(opts)){
            
                if(opts.init){
                    layout_opts[pos+'__initHidden'] = (opts.init=='hidden');
                    layout_opts[pos+'__initClosed'] = (opts.init=='closed');
                }
                
                if(opts.size){
                    layout_opts[pos+'__size'] = opts.size;
                }
                if(window.hWin.HEURIST4.util.isnull(opts.resizable) || opts.resizable ){
                    if(opts.minSize){
                        layout_opts[pos+'__minSize'] = opts.minSize;
                    }
                    if(opts.maxSize){
                        layout_opts[pos+'__maxSize'] = opts.maxSize;
                    }
                    layout_opts[pos+'__resizable'] = true;
                }else{
                    layout_opts[pos+'__spacing_open'] = 0;
                    layout_opts[pos+'__resizable'] = false;
                }
            }
            
            let $d2;

            if(forStorage){
                
                $d2 = this.#layoutCreateDiv( layout.children[i], '', forStorage )
            
                if(!$.isEmptyObject(layout.children[i].options)){
//console.log('assign css ', layout.children[i].options);                    
                    $d2.attr('data-cms-options',JSON.stringify(layout.children[i].options));
                }
            
                $d2.appendTo($parent);
            }else{
                //create cardinal div
                $d = $(document.createElement('div'));
            
                $d.addClass('ui-layout-'+pos)
                  .appendTo($parent);

                if(layout.children[i].children.length>1){
                  
                    lpane.dom_id = 'cms-tabs-'+lpane.key;
                    //@todo additional container for children>1        
                    layout_opts[pos+'__contentSelector'] = '#'+lpane.dom_id;
                    
                    $d2 = this.#layoutCreateDiv(lpane, 'ui-layout-content2');  
                    $d2.appendTo($d);
                
                }else if(layout.children[i].children.length>0){
                    
                    let dom_id = layout.children[i].children[0].dom_id;
                    if(!dom_id){
                        dom_id = 'cms-tabs-'+lpane.key;
                        layout.children[i].children[0].dom_id = dom_id;
                    }
                    if(!layout.children[i].children[0].classes){
                        layout.children[i].children[0].classes = '';
                    }
                    layout.children[i].children[0].classes += ' ui-layout-content2';
                    
                    layout_opts[pos+'__contentSelector'] =  '#'+dom_id;
                    $d2 = $d;
                }     
            }
                    
            //init                    
            this.#layoutInitFromJSON(layout.children[i].children, $d2, forStorage);
                    
        }//for
    
        if(!forStorage){
            // Initialize the jQuery UI Layout plugin on the parent container
            $parent.layout( layout_opts );
        }
        
      
      
  }
  
  /**
   * Initializes a tabbed interface.
   * Creates the necessary HTML structure (ul for tab headers, divs for tab panels)
   * and then initializes the jQuery UI Tabs widget.
   * Content for each tab panel is generated by recursively calling `#layoutInitFromJSON`.
   *
   * @private
   * @param {Object} layout - The layout configuration for the tabs. It must have a `children` array,
   *                          where each child represents a tab (panel). Each child should have `name` (for tab title)
   *                          and `children` (for tab content).
   * @param {jQuery} container - The parent jQuery element where the tabs structure will be built.
   * @param {boolean} [forStorage=false] - If true, generates HTML structure for storage.
   *                                     Otherwise, initializes the jQuery UI Tabs widget.
   * @returns {void}
   */
  #layoutInitTabs(layout, container, forStorage){
        
        
        let $d;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
        
        //create parent div
        $d = this.#layoutCreateDiv(layout, '', forStorage);
        
        if (!layout.css) layout.css = {};
        if (layout.css && !$.isEmptyObject(layout.css)) {
            $d.css(layout.css);
        }        
        
        $d.appendTo(container);
          
        if($d.parent().hasClass('layout-content')){
            $d.addClass('ent_wrapper');    
        }

        //tab panels    
        this.#layoutInitFromJSON(layout.children, $d, forStorage);
               
        if(!forStorage) {
            //tab header
            $d = this.body.find('#'+layout.dom_id);
            let groupTabHeader = $('<ul>').prependTo($d);
            
            for(let i=0; i<layout.children.length; i++){
          
                //.addClass('edit-form-tab')
                $('<li>').html('<a href="#'+layout.children[i].dom_id
                                    +'"><span style="font-weight:bold">'
                                    +layout.children[i].name+'</span></a>')
                            .appendTo(groupTabHeader);
            }
            
            $d.tabs();
        }
    }
    
  /**
   * Initializes an accordion interface.
   * Creates the necessary HTML structure (h3 for headers, divs for panels)
   * and then initializes the jQuery UI Accordion widget.
   * Content for each accordion panel is generated by recursively calling `#layoutInitFromJSON`.
   *
   * @private
   * @param {Object} layout - The layout configuration for the accordion. It must have a `children` array,
   *                          where each child represents an accordion panel. Each child should have `name` (for header)
   *                          and `children` (for panel content).
   * @param {jQuery} container - The parent jQuery element where the accordion structure will be built.
   * @param {boolean} [forStorage=false] - If true, generates HTML structure for storage.
   *                                     Otherwise, initializes the jQuery UI Accordion widget.
   * @returns {void}
   */
    #layoutInitAccordion(layout, container, forStorage){
       
        let $d;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
            
        //create parent div
        $d = this.#layoutCreateDiv(layout, '', forStorage);
        
        $d.appendTo(container);
       
        //accordion panels    
        this.#layoutInitFromJSON(layout.children, $d, forStorage);
        
        if(!forStorage){
       
        //accordion headers
        for(let i=0; i<layout.children.length; i++){
      
            $d = this.body.find('#'+layout.children[i].dom_id);
            
            $('<h3>').html( layout.children[i].name )
                     .insertBefore($d);
            
        }
        
        $d = this.body.find('#'+layout.dom_id);
        $d.accordion({heightStyle: "content", 
                      active:false,
                //active:(currGroupType == 'expanded')?0:false,
                      collapsible: true });
                      
        }
    }
    
  /**
   * Recursively searches for a layout element configuration within a nested layout structure
   * using the element's unique `key`.
   *
   * @private
   * @param {Array<Object>|Object} content - The layout configuration structure (or a part of it) to search within.
   *                                        Can be an array of elements or a single element object (which should have a `children` property).
   * @param {string|number} ele_key - The key of the element to find.
   * @returns {Object|null} The configuration object of the found element, or `null` if not found.
   */
    #layoutContentFindElement(content, ele_key){

        if(!Array.isArray(content)){
            if(content && content.children && content.children.length>0){ // Check if content itself is an object with children
                return this.#layoutContentFindElement(content.children, ele_key);    
            }else{
                return null; // Not an array and no children to search
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                let res = this.#layoutContentFindElement(content[i].children, ele_key);    
                if(res) return res;
            }
        }
        return null; // Not found
    }
    
  /**
   * Recursively searches for the first widget configuration within a nested layout structure
   * that matches the given `widget_name` (appid).
   *
   * @private
   * @param {Array<Object>|Object} content - The layout configuration structure to search within.
   * @param {string} widget_name - The `appid` of the widget to find (e.g., "heurist_SearchInput").
   * @returns {Object|null} The configuration object of the found widget, or `null` if not found.
   */
    #layoutContentFindWidget(content, widget_name){
        
        if(!Array.isArray(content)){
            if(content && content.children && content.children.length>0){
                return this.#layoutContentFindWidget(content.children, widget_name);    
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].appid == widget_name){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                let res = this.#layoutContentFindWidget(content[i].children, widget_name);    
                if(res) return res;
            }
        }
        return null; // Not found
    }

  /**
   * Recursively finds all widget configurations within a nested layout structure.
   *
   * @private
   * @param {Array<Object>|Object} content - The layout configuration structure to search within.
   * @returns {Array<Object>} An array of all found widget configuration objects. Returns an empty array if none are found.
   */
    #layoutContentFindAllWidget(content){

        let res = [];
        
        if(!Array.isArray(content)){
            if(content && content.children && content.children.length>0){
                let res2 =  this.#layoutContentFindAllWidget(content.children);    
                if(res2 && res2.length > 0) res = res.concat(res2); // Ensure res2 is not null and has items
            }else{
                return res; // Return empty array if no children or not an array
            }
        } else { // Is an array
            for(let i=0; i<content.length; i++){
                if(content[i].appid){ // It's a widget
                    res.push(content[i]);
                } else if(content[i].children && content[i].children.length>0){ // It has children, recurse
                    let res2 = this.#layoutContentFindAllWidget(content[i].children);
                    if(res2 && res2.length > 0) res = res.concat(res2);
                }
            }
        }
        return res;
    }
    
  /**
   * Finds the most frequently used "search_realm" ID among all widgets in the given layout content.
   * This can be used to determine a primary or default realm for a page.
   *
   * @private
   * @param {Array<Object>|Object} content - The layout configuration structure to analyze.
   * @returns {string} The `search_realm` ID that appears most often. Returns an empty string if no realms are found or applicable.
   */
    #layoutContentFindMainRealm(content){
        let realm_counts = {}; // Use object for counts: {realmId: count}
        let widgets = this.#layoutContentFindAllWidget(content);

        for(let i=0; i<widgets.length; i++){
            // Ensure options exist and search_realm is present and not part of a search_page definition
            if(widgets[i].options && !widgets[i].options.search_page && widgets[i].options.search_realm){
                const realm = widgets[i].options.search_realm;
                realm_counts[realm] = (realm_counts[realm] || 0) + 1;
            }
        }

        let max_usage = 0; 
        let main_realm = '';
        const realms = Object.keys(realm_counts);
        for(let i=0; i<realms.length; i++){
            if(realm_counts[realms[i]] > max_usage){
                max_usage = realm_counts[realms[i]];
                main_realm = realms[i];
            }
        }
        return main_realm;
    }

  /**
   * Finds the parent configuration object of an element with the given `ele_key`
   * within a nested layout structure.
   *
   * @private
   * @param {Array<Object>|Object} parent_content - The layout configuration of the potential parent or an array of elements to start searching from.
   *                                              If an array, `parent_content` itself is considered the children list of a conceptual 'root'.
   * @param {string|number} ele_key - The key of the element whose parent is to be found.
   * @returns {Object|string|false} The parent configuration object if found. Returns 'root' if the element is at the top level of an initial array.
   *                                Returns `false` if the element or its parent is not found.
   */
    #layoutContentFindParent(parent_content, ele_key){
        
        let children;
        let current_parent_node;

        if(Array.isArray(parent_content)){
            children = parent_content;
            current_parent_node = 'root'; // Conceptual parent if starting with an array
        }else if (parent_content && parent_content.children){
            children = parent_content.children;
            current_parent_node = parent_content;
        } else {
            return false; // Not an array and not an object with children
        }
        
        if (!children) return false;

        for(let i=0; i<children.length; i++){
            if(children[i].key == ele_key){
                return  current_parent_node; // Found the element, return its direct parent
            }else if(children[i].children && children[i].children.length>0){
                let res = this.#layoutContentFindParent(children[i], ele_key); // Recurse into children
                if(res) return res; // If found in deeper levels, propagate the result
            }
        }
        return false; // Not found in this branch
    }
    
  /**
   * Replaces an element's configuration in a nested layout structure.
   * It finds the element by its `key` and updates it with `new_cfg`.
   * If the `new_cfg` is a text type, it specifically updates the `content` property.
   *
   * @private
   * @param {Array<Object>} content_array - The array of layout configurations to search and modify.
   * @param {Object} new_cfg - The new configuration object for the element. Must include a `key`.
   * @returns {boolean} True if the element was found and updated, false otherwise.
   */
    #layoutContentSaveElement(content_array, new_cfg){ // Expects content_array to be an array
            
        if (!Array.isArray(content_array)) return false;

        let ele_key = new_cfg.key;
        
        for(let i=0; i<content_array.length; i++){
            if(content_array[i].key == ele_key){
                // Special handling for text type, preserving other properties if only content changes
                if(new_cfg.type && new_cfg.type.indexOf('text') === 0 && content_array[i].type === new_cfg.type){
                   content_array[i].content = new_cfg.content;
                   // Potentially merge other properties from new_cfg if needed, e.g., css, classes
                   if (new_cfg.css) content_array[i].css = new_cfg.css;
                   if (new_cfg.classes) content_array[i].classes = new_cfg.classes;
                   // and other relevant fields
                } else {
                   content_array[i] = new_cfg; // Replace the whole configuration
                }
                return true;
            }else if(content_array[i].children && content_array[i].children.length>0){
                // Pass the children array directly for recursive call
                if (this.#layoutContentSaveElement(content_array[i].children, new_cfg)){
                    return true;
                }
            }
        }
        return false; // Element not found
    }
    
  /**
   * Prepares a layout template, typically by processing specific template types like 'blog'.
   * For 'blog' templates, it might involve finding a specific widget (e.g., 'heurist_SearchTree'),
   * and if certain conditions are met (like `init_svsID=='????'`), it loads and executes an
   * external script (e.g., `blog.js`) to modify the layout and then calls the provided callback.
   *
   * @private
   * @param {Object} layout - The layout configuration object, which might have a `template` property.
   * @param {function(Object): void} callback - A callback function to be executed after template processing.
   *                                          It receives the (potentially modified) layout's primary content/child.
   * @returns {boolean|undefined} Returns `true` if an asynchronous script load was initiated for template processing.
   *                              Otherwise, `undefined`.
   */
    #prepareTemplate(layout, callback){ 
       
        if(layout.template=='default'){
           // For default template, directly call back with the first child (or main content part)
           if (layout.children && layout.children.length > 0) {
               callback.call(this, layout.children[0]);
           } else {
               callback.call(this, layout); // Or layout itself if no children
           }
            
        }else if(layout.template=='blog'){
            
           let ele = this.#layoutContentFindWidget(layout, 'heurist_SearchTree');
           // Condition to trigger dynamic blog template processing
           if (ele && ele.options && ele.options.init_svsID=='????') {
                layout.template = null; // Mark template as processed or prevent re-processing

                try{
                    let sURL2 = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/blog.js';
                    // Execute template script to replace template variables, add filters, smarty templates, etc.
                    $.getScript(sURL2)
                        .done((script, textStatus) => { // Script loaded successfully
                            // Assuming prepareTemplateBlog is globally available after script load
                            if (typeof prepareTemplateBlog === 'function') {
                                prepareTemplateBlog(layout, callback); // Function from blog.js
                            } else {
                                console.error('prepareTemplateBlog function not found after loading blog.js');
                                callback.call(this, layout.children && layout.children.length > 0 ? layout.children[0] : layout); // Fallback
                            }
                        })
                        .fail((jqxhr, settings, exception) => {
                            console.error( 'Error loading template script (blog.js): '+exception );
                            callback.call(this, layout.children && layout.children.length > 0 ? layout.children[0] : layout); // Fallback
                        });
                    
                    return true; // Indicates asynchronous operation started
                } catch(e) { // Catch synchronous errors during setup
                    alert('Error in blog template script setup: ' + e.message);
                    callback.call(this, layout.children && layout.children.length > 0 ? layout.children[0] : layout); // Fallback
                }
           } else {
                // If conditions for blog processing not met, treat as default or do nothing specific
                if (layout.children && layout.children.length > 0) {
                   callback.call(this, layout.children[0]);
                } else {
                   callback.call(this, layout);
                }
           }
        } else {
             // Unknown template or no template, treat as default
            if (layout.children && layout.children.length > 0) {
               callback.call(this, layout.children[0]);
            } else {
               callback.call(this, layout);
            }
        }
    }
        
    
  /**
   * Converts an older HTML-based CMS format (Heurist v1 style) into the current JSON layout structure.
   * It iterates through the children of the given container, identifying widgets (`data-heurist-app-id`)
   * and plain HTML content, then constructs a corresponding JSON object.
   *
   * @private
   * @param {jQuery} container - The jQuery object containing the old format HTML.
   * @param {number} lvl - The current depth/level of recursion, used for naming elements.
   * @returns {Array<Object>} An array of JSON layout configuration objects derived from the HTML.
   *                          At level 0, this is wrapped in a root object: `{name: "Name of this page", type: "group", children: [...]}`.
   */
    #convertOldCmsFormat(container, lvl){ // `this` context needs to be correct if calling other instance methods

        let res = [];
        const self = this; // To ensure 'this' inside $.each refers to HLayoutMgr instance

        $.each(container.children(), function(idx, ele){ // `this` inside this function is the DOM element `ele`
            ele = $(ele); // Ensure ele is a jQuery object

            let child_config;

            if(ele.attr('data-heurist-app-id')){
                // This is a widget
                let opts_json = ele.text();
                let opts = window.hWin.HEURIST4.util.isJSON(opts_json);
                if (opts === false && opts_json.trim() !== '') { // If not valid JSON but not empty, log error or handle
                    console.warn('Invalid JSON for widget options:', opts_json, ele);
                    opts = {}; // Default to empty options
                } else if (opts === false) {
                    opts = {}; // Also default if empty or only whitespace
                }


                child_config = {
                    appid: ele.attr('data-heurist-app-id'),
                    options: opts
                };

                if(opts && opts.__widget_name){ // Check opts exists
                    child_config.name = opts.__widget_name.replace(/=/g,'').trim(); // ReplaceAll if available, or use replace with global regex
                }
                if(!child_config.name) child_config.name = "Widget "+lvl+'.'+idx;

            } else if(ele.find('div[data-heurist-app-id]').length === 0){ // No widgets among children, treat as text content
                let tag = ele[0].nodeName;
                // Preserve the outer tag for simple HTML content
                let s = '<' + tag.toLowerCase() + '>' + ele.html() + '</' + tag.toLowerCase() + '>';

                child_config = {
                    name: "Content "+lvl+'.'+idx,
                    type: "text",
                    content: s
                };
            } else { // Contains other elements, possibly nested widgets or structure
                child_config = {
                    name: "Group "+lvl+'.'+idx,
                    type: "group",
                    folder: true,
                    children: self.#convertOldCmsFormat(ele, lvl+1) // Use self here
                };
            }

            if(child_config){
                if(ele.attr('style')){
                    const css_obj = self.#css2json(ele.attr('style')); // Use self here
                    if(!$.isEmptyObject(css_obj)) child_config['css'] = css_obj;
                }
                if(ele.attr('class')) { // Preserve classes
                    child_config['classes'] = ele.attr('class');
                }
                res.push(child_config);
            }
        });

        if(lvl === 0){ // Wrap the result in a top-level page group if at the root
            return [{name: "Name of this page", type: "group", folder: true, children: res }];
        }

        return res;
    }

    
    // Design notes for #convertHTMLtoJSON:
    // 1. Save result of CMS edit as human-readable HTML:
    //    - `<div id="cms-content-23" data-cms-name="Page" data-cms-type="text|group|accordion|tabs|cardinal|app" css="[style-string]"> content </div>`
    //    - `<div id="cms-widget-51" data-cms-name="Menu"  data-cms-type="app" css="[style-string]"> {options_json_string} </div>`
    // 2. Convert HTML to JSON (for editing):
    //    - id => dom_id
    //    - data-cms-name => name
    //    - data-cms-type => type
    //    - style (from css attribute or style tag) => css (object)
    //    - folder: true if it has children
    //    - children | options | content
    //    - appid (for type 'app')
    // 3. Init layout from HTML (similar to from JSON) - if no complex types (accordion, tabs, cardinal, app), load "as is".
    // 4. CMS editor for header and footer:
    //    - Create HTML content like Group + MainMenu.

  /**
   * Converts HTML (potentially representing a CMS layout) into a JSON layout configuration.
   * This is the reverse of generating HTML from JSON. It parses elements with `data-cms-type`
   * attributes and their content/children to reconstruct the JSON structure.
   * Handles multilingual content stored in `data-lang` attributes.
   *
   * @private
   * @param {jQuery|HTMLElement} element_input - The HTML element (or jQuery object) to convert.
   * @param {number} lvl - The current depth/level of recursion.
   * @returns {Object|Array<Object>} A JSON layout configuration object or an array of them.
   */
    #convertHTMLtoJSON(element_input, lvl){ // Renamed 'ele' to 'element_input' to avoid conflict
        
        const $ele = $(element_input); // Ensure it's a jQuery object

        let res_config;

        if($ele.length > 1){ // If $ele is a collection of sibling elements
            // This case happens if a container with multiple top-level layout items is passed,
            // or if ele.children() is passed recursively.
            if($ele.find('[data-cms-type]').length > 0 || $ele.filter('[data-lang]').length > 0 || $ele.find('[data-lang]').length > 0) {
                res_config = [];
                $ele.each((i, item_dom) => { // Iterate over DOM elements in jQuery collection
                    res_config.push(this.#convertHTMLtoJSON(item_dom, lvl)); // Process each item
                });
                return res_config;
            } else {
                 // If no recognized cms-type or lang, treat as simple HTML content block (might be part of a text element)
                return { content: $ele.html() }; // This might need adjustment based on how it's called
            }
        }
        
        // Processing a single element from here
        if(!$ele.attr('data-cms-type')){ // No 'data-cms-type' attribute on this specific element
            if(lvl === 0){ // If at the root level and no type, wrap in a default Page/Content structure
                res_config = [{
                    name:'Page', type:'group',
                    children:[
                        {name:'Content', type:'text', css:{}, content: $ele.html()} // Assume current element's HTML is the content
                    ]
                }];
            } else { // If deeper and no type, it's likely content for a typed parent
                res_config = {}; // Initialize as an empty object to be filled with content
            }
            
            // Handle multilingual content if present as children with data-lang
            let translations = $ele.children('[data-lang]');
            if(translations.length > 0){
                translations.each((i,item_dom)=>{
                    const item = $(item_dom);
                    res_config['content' + (item.attr('data-lang') || 'def')] = item.html();
                });
            } else { // Single language content or content directly in the element
                if($ele.attr('data-lang') && $ele.attr('data-lang') !== 'def'){
                    res_config['content' + $ele.attr('data-lang')] = $ele.html();
                } else {
                    res_config.content = $ele.html();
                }
            }
        } else { // Element has 'data-cms-type'
            res_config = {
                dom_id: $ele.attr('id'),
                name: $ele.attr('data-cms-name'),
                type: $ele.attr('data-cms-type')
            };
                   
            if($ele.attr('style')){
                // For cardinal layout panes, options are stored in 'data-cms-options'
                if(['north', 'south', 'west', 'east', 'center'].includes(res_config.type) && $ele.attr('data-cms-options')){
                    let cardinal_opts = window.hWin.HEURIST4.util.isJSON($ele.attr('data-cms-options'));
                    if(cardinal_opts){
                        res_config['options'] = cardinal_opts;
                    }
                } else { // For other types, style attribute is parsed into css object
                    res_config['css'] = this.#css2json($ele.attr('style'));
                }
            }
            if($ele.attr('class')){ // Preserve classes
                res_config['classes'] = $ele.attr('class');
            }
                   
            if(res_config.type === 'app'){ // Widget type
                let opts_json = $ele.text(); // Widget options are stored as JSON string in the element's text content
                let opts = window.hWin.HEURIST4.util.isJSON(opts_json);
                if (opts === false && opts_json.trim() !== '') {
                     console.warn('Invalid JSON for widget options during HTMLtoJSON:', opts_json, $ele);
                     opts = { appid: $ele.attr('data-cms-name') }; // Fallback, appid might be in data-cms-name or need other source
                } else if (opts === false) {
                    opts = { appid: $ele.attr('data-cms-name') }; // Fallback
                }
                res_config.options = opts;
                res_config.appid = opts.appid || $ele.attr('data-cms-name'); // Ensure appid is set
            } else { // Non-widget, potentially a container type
                let children_elements = $ele.children(); // Get all children first
                let cms_children_elements = children_elements.filter('[data-cms-type]'); // Children that are CMS elements

                if(cms_children_elements.length > 0){ // Has structured CMS children
                    res_config.children = [];
                    cms_children_elements.each((i,item_dom)=>{
                        res_config.children.push(this.#convertHTMLtoJSON(item_dom, lvl+1));
                    });
                    res_config.folder = true;
                } else if (children_elements.filter('[data-lang]').length > 0) { // Has children for multilingual content
                     // This case should be handled by the logic block for !data-cms-type if children are for text content
                     // For now, assume it's text content if no cms-type children
                    let text_content_parts = this.#convertHTMLtoJSON(children_elements, lvl + 1); // this will be an object with content<lang>
                    Object.assign(res_config, text_content_parts);

                } else { // No CMS children, treat remaining HTML as content for this element (e.g. a text block)
                    res_config.content = $ele.html(); // This might grab serialized children if any
                }
            }
        }
        return res_config;
    }
    
  /**
   * Converts a CSS style string into a JavaScript object.
   * E.g., "color: red; font-size: 12px;" becomes `{color: "red", "font-size": "12px"}`.
   *
   * @private
   * @param {string | CSSStyleDeclaration} css - The CSS style string or a CSSStyleDeclaration object.
   * @returns {Object} A JavaScript object representing the CSS styles.
   */
    #css2json(css) {
        let s = {};
        if (!css) return s;
        if (css instanceof CSSStyleDeclaration) { // If it's a style object
            for (let i = 0; i < css.length; i++) { // Iterate by index
                const propName = css[i];
                if (propName.toLowerCase) { // Ensure property name is a string
                    s[propName.toLowerCase()] = css.getPropertyValue(propName);
                }        
            }
        } else if (typeof css == "string") { // If it's a string
            css = css.split(";"); // Split by semicolon
            for (let i in css) {
                let rule = css[i].trim();
                if (rule) {
                    let parts = rule.split(":");
                    if (parts.length >= 2) {
                        let key = parts[0].trim().toLowerCase();
                        let value = parts.slice(1).join(':').trim(); // Join back in case value had colons
                        s[key] = value;
                    }
                }
            }
        }
        return s;
    }    

  /**
   * Converts a JSON layout configuration to an HTML string suitable for storage or transmission.
   * This method is intended for serialization and does not initialize widgets.
   * It internally uses `#layoutInitFromJSON` with `forStorage=true` and then
   * potentially converts this HTML back to JSON for verification or logging (current implementation detail).
   *
   * @private
   * @param {Array<Object>} content - The JSON layout configuration array.
   * @returns {string} The generated HTML string representing the layout.
   * @memberof HLayoutMgr
   * @description Marked as "NEW" in source.
   */
    #convertJSONtoHTML(content){
        
        // From JSON to HTML string for storage
        // console.log("Input JSON to #convertJSONtoHTML:", content);
        
        const html_output = this.#layoutInitFromJSON(content, null, true, true);
        // console.log("Output HTML from #layoutInitFromJSON(forStorage=true):", html_output);
        
        // The following conversion back to JSON seems to be for debugging or internal verification
        // and might not be part of the primary goal of this function (JSON -> HTML string).
        // If the goal is purely JSON to HTML string, this part can be removed.
        // const json_verification = this.#convertHTMLtoJSON(html_output, 0);
        // console.log("Verification JSON from #convertHTMLtoJSON:", json_verification);
        
        return html_output; // Return the HTML string
    }
      
  

  //============================================================================

  // Public methods

  /**
  * Reinitializes a tabbed layout section. Typically used in an editing context
  * when the structure of tabs needs to be redrawn.
  *
  * @param {Object} layout - The layout configuration object for the tabs.
  * @param {jQuery} container - The jQuery container element for the tabs.
  * @returns {void}
  */
  layoutInitTabs(layout, container) {
    this.#layoutInitTabs(layout, container, false); // Assuming live initialization, not for storage
  }

  /**
  * Reinitializes an accordion layout section. Used in editing contexts.
  *
  * @param {Object} layout - The layout configuration object for the accordion.
  * @param {jQuery} container - The jQuery container element for the accordion.
  * @returns {void}
  */
  layoutInitAccordion(layout, container) {
    this.#layoutInitAccordion(layout, container, false); // Assuming live initialization
  }

  /**
  * Reinitializes a cardinal layout section (e.g., N, S, E, W panes). Used in editing contexts.
  *
  * @param {Object} layout - The layout configuration for the cardinal layout.
  * @param {jQuery} container - The jQuery container element for the cardinal layout.
  * @returns {void}
  */
  layoutInitCardinal(layout, container) {
    this.#layoutInitCardinal(layout, container, false); // Assuming live initialization
  }
  
  /**
  * Converts layout from an older Heurist v1 HTML format to the current JSON configuration.
  * The input HTML string is placed into the container, then parsed.
  *
  * @param {string} layout_html - The HTML string in the old v1 format.
  * @param {jQuery|string} container_selector - jQuery object or selector for a temporary container to parse the HTML.
  * @returns {Array<Object>} The converted layout configuration in JSON format.
  */
  convertOldCmsFormat(layout_html, container_selector) {
    const container = $(container_selector);
    container.empty(); // Clear the container before adding new HTML
    container.html(layout_html); // Insert the old format HTML
    return this.#convertOldCmsFormat(container, 0); // Start conversion from level 0
  }
  
  /**
  * Public version of `#layoutInitKey`. Assigns a unique key, title, and folder status
  * to a layout element if it doesn't already have a key.
  *
  * @param {Array<Object>} layout - The array of layout configuration objects.
  * @param {number} i - The index of the current element in the `layout` array.
  * @returns {void} Modifies the `layout[i]` object in place.
  */
  layoutInitKey(layout, i) {
    this.#layoutInitKey(layout, i);
  }

  /**
   * Public access to `#layoutAddWidget`. Adds and initializes a widget.
   * @param {Object} layout - Widget layout configuration.
   * @param {jQuery} container - Parent container.
   * @param {boolean} [forStorage=false] - If true, prepares for storage.
   * @description This method is marked as "not used" in the original source.
   *              Consider if it's deprecated or if its use cases are covered elsewhere.
   *              If truly unused, it could be marked `@deprecated`.
   */
  layoutAddWidget(layout, container, forStorage = false) {
    // The 'forStorage' parameter was missing in the original public signature. Added for consistency if it were to be used.
    // However, given it's "not used", its signature and utility should be reviewed if it's to be activated.
    this.#layoutAddWidget(layout, container, forStorage);
  }

  /**
  * Public access to `#layoutContentFindElement`. Finds an element's configuration by its internal key.
  *
  * @param {Array<Object>|Object} layout_cfg - The layout configuration to search within.
  * @param {string|number} ele_key - The key of the element to find.
  * @returns {Object|null} The found element's configuration or null.
  */
  layoutContentFindElement(layout_cfg, ele_key) {
    return this.#layoutContentFindElement(layout_cfg, ele_key);
  }

  /**
   * Public access to `#layoutContentFindParent`. Finds the parent configuration of an element by its key.
   *
   * @param {Array<Object>|Object} parent_content - The layout configuration of the potential parent or an array to start searching from.
   * @param {string|number} ele_key - The key of the element whose parent is to be found.
   * @returns {Object|string|false} The parent configuration object, 'root', or false.
   */
  layoutContentFindParent(parent_content, ele_key) {
    return this.#layoutContentFindParent(parent_content, ele_key);
  }

  /**
  * Public access to `#layoutContentFindWidget`. Finds a widget's configuration by its `appid`.
  *
  * @param {Array<Object>|Object} layout_cfg - The layout configuration to search within.
  * @param {string} widget_name - The `appid` of the widget.
  * @returns {Object|null} The found widget's configuration or null.
  */
  layoutContentFindWidget(layout_cfg, widget_name) {
    return this.#layoutContentFindWidget(layout_cfg, widget_name);
  }

  /**
  * Public access to `#layoutContentFindMainRealm`. Finds the most used `search_realm` ID.
  *
  * @param {Array<Object>|Object} layout_cfg - The layout configuration to analyze.
  * @returns {string} The most common `search_realm` ID.
  */
  layoutContentFindMainRealm(layout_cfg) {
    return this.#layoutContentFindMainRealm(layout_cfg);
  }

  /**
  * Public access to `#layoutContentSaveElement`. Updates a layout configuration with new values for a specific element.
  *
  * @param {Array<Object>} layout_cfg - The (top-level) layout configuration array to modify.
  * @param {Object} new_cfg - The new configuration for the element (must include `key`).
  * @returns {boolean} True if successful, false otherwise.
  */
  layoutContentSaveElement(layout_cfg, new_cfg) {
    // Assumes _main_layout_cfg is the intended target if layout_cfg is not the actual array of elements.
    // For broader usability, it should operate on the passed layout_cfg directly if it's an array.
    // If _main_layout_cfg is indeed the global state to modify, this needs clarity.
    // Assuming layout_cfg is the array to modify:
    if (Array.isArray(layout_cfg)) {
        return this.#layoutContentSaveElement(layout_cfg, new_cfg);
    } else if (this._main_layout_cfg && Array.isArray(this._main_layout_cfg)) {
        // Fallback or intended use of _main_layout_cfg if applicable
        return this.#layoutContentSaveElement(this._main_layout_cfg, new_cfg);
    }
    console.warn("layoutContentSaveElement called with non-array layout_cfg and no valid _main_layout_cfg.");
    return false;
  }

  /**
   * Sets the edit mode for the layout manager.
   * @param {boolean} newmode - True to enable edit mode, false to disable.
   * @returns {void}
   */
  setEditMode(newmode) {
    this.isEditMode = newmode;
  }

  /**
  * Public access to `#prepareTemplate`. Processes a layout template, potentially loading external scripts.
  *
  * @param {Object} layout - The layout configuration object with a `template` property.
  * @param {function(Object): void} callback - Callback executed after template processing.
  * @returns {boolean|undefined} True if an async script load was initiated, otherwise undefined.
  */
  prepareTemplate(layout, callback) {
    return this.#prepareTemplate(layout, callback); // Added return
  }

  /**
  * Checks if all Heurist widgets within the document body have completed their initialization.
  * It iterates over elements with the class `heurist-widget` and checks their `init_completed` option.
  *
  * @returns {boolean} True if all widgets are initialized, false otherwise.
  */
  layoutCheckWidgets() {
    const widgets = this.body.find("div.heurist-widget");
    let are_all_widgets_inited = true;

    $.each(widgets, (i, item_dom) => {
      const item = $(item_dom);
      const widgetname = item.attr("data-widgetname");
      if (widgetname) {
        // Check if the widget plugin exists and has an instance
        if (typeof item[widgetname] === 'function' && item[widgetname]("instance")) {
          const is_inited = item[widgetname]("option", "init_completed");
          if (is_inited === false) { // Explicitly check for false
            are_all_widgets_inited = false;
            return false; // Exit $.each loop early
          }
        } else {
          // Widget plugin not found or not initialized, means it's not completed
          are_all_widgets_inited = false;
          return false; // Exit $.each loop early
        }
      }
      // If no widgetname attribute, it might not be a standardly initialized widget, skip or handle as needed.
    });
    return are_all_widgets_inited;
  }

  /**
  * Public access to `#convertJSONtoHTML`. Converts a JSON layout configuration to an HTML string.
  * Marked as "NEW" in the source. This is for serializing the layout.
  *
  * @param {Array<Object>} content - The JSON layout configuration.
  * @returns {string} The generated HTML string.
  */
  convertJSONtoHTML(content) {
    return this.#convertJSONtoHTML(content);
  }
  
  
  /**
  * Finds a predefined layout configuration by its ID from a global `cfg_layouts` array.
  *
  * @param {string} id - The ID of the layout to retrieve. Case-insensitive.
  * @returns {Object|null} The layout configuration object if found, otherwise null.
  * @throws {ReferenceError} If `cfg_layouts` is not defined in the global scope.
  */
  layoutGetById(id){
        if(typeof window.hWin.cfg_layouts === 'undefined'){
            // console.error("cfg_layouts is not defined. Cannot find layout by ID.");
            throw new ReferenceError("cfg_layouts is not defined. Cannot find layout by ID.");
            // return null; // Or throw error
        }
        if(id){
            id = String(id).toLowerCase(); // Ensure id is a string before toLowerCase
            for(let i=0; i<window.hWin.cfg_layouts.length; i++){
                if(window.hWin.cfg_layouts[i].id && String(window.hWin.cfg_layouts[i].id).toLowerCase()==id){ // Ensure cfg_layouts[i].id exists
                    return window.hWin.cfg_layouts[i];
                }
            }
        }
        return null;
  }    


  /**
  * Main method to initialize a layout.
  * It determines whether to initialize from JSON or from existing HTML content.
  *
  * @param {Array<Object>|string|null} layout - The layout configuration.
  *                                            Can be a JSON array/object, an HTML string, or null.
  *                                            If null or HTML string, it attempts to initialize from container's content or the provided HTML.
  * @param {jQuery|string} container_selector - The main container element (jQuery object or selector) for the layout.
  * @param {Object} [supp_options={}] - Supplementary options for widget initialization, passed down through the process.
  * @returns {Array<Object>|string|false|void} Depends on the internal calls.
  *                                            If from JSON and not for storage, returns processed layout JSON.
  *                                            If from HTML, behavior is defined by `#layoutInitFromHTML`.
  *                                            May return `false` on certain parsing failures.
  */
  layoutInit(layout, container_selector, supp_options)
  {
    const container = $(container_selector);
//console.log("layoutInit called with:", layout, container, supp_options);
    this._supp_options = supp_options || {};
  
    // Main content initialization
    if(layout && window.hWin.HEURIST4.util.isJSON(layout)){ // If layout is a valid JSON string or object/array
        return this.#layoutInitFromJSON(layout, container, false, true); // Init from JSON, live rendering, top level
    }else{ // Layout is not JSON, or is null/undefined
        if(typeof layout === 'string' && layout.trim() !== ''){ // If layout is a non-empty HTML string
            container.html(layout); // Populate container with this HTML
        }
        // Then, (or if layout was null/empty) initialize from the HTML now in the container
        // #layoutInitFromHTML itself doesn't explicitly return a value useful here.
        // It modifies the DOM and initializes widgets.
        this.#layoutInitFromHTML(container);
        return; // Explicitly return void as #layoutInitFromHTML doesn't have a meaningful return for this context.
    }
  }
  
  /**
   * Initializes layout from a JSON configuration.
   * This is a public wrapper for `#layoutInitFromJSON` for live rendering.
   *
   * @param {Array<Object>|string} layout - The JSON layout configuration.
   * @param {jQuery|string} container_selector - The container element or selector.
   * @param {Object} [supp_options={}] - Supplementary options.
   * @returns {Array<Object>|string|false} Result from `#layoutInitFromJSON`.
   */
  layoutInitFromJSON(layout, container_selector, supp_options)
  {
    const container = $(container_selector);
    this._supp_options = supp_options || {};
    return this.#layoutInitFromJSON(layout, container, false, true); // Live rendering, top level
  }
  
  /**
   * Initializes layout from existing HTML content in a container.
   * This is a public wrapper for `#layoutInitFromHTML`.
   *
   * @param {jQuery|string} container_selector - The container element or selector.
   * @param {Object} [supp_options={}] - Supplementary options.
   * @returns {void}
   */
  layoutInitFromHTML(container_selector, supp_options)
  {
    const container = $(container_selector);
    this._supp_options = supp_options || {};
    this.#layoutInitFromHTML(container); // Doesn't have a meaningful return value for assignment
  }
  
}