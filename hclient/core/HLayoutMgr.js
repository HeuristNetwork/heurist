/**
 * @file HLayoutMgr.js
 * @brief Web page generator based on JSON configuration.
 * @fileOverview The HLayoutMgr class is responsible for dynamically generating and managing web page layouts
 * based on JSON configurations. It handles the initialization of various layout types such as groups, tabs,
 * accordions, and cardinal layouts (multi-pane). It also initializes and manages Heurist widgets embedded
 * within these layouts, loading their scripts if necessary. The manager supports converting older HTML-based
 * layout formats to the new JSON structure and can also render layouts into a human-readable HTML format
 * for storage or editing. It provides utilities for finding elements and widgets within the layout
 * configuration, handling different languages, and preparing layout templates.
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */
/* global cfg_widgets, prepareTemplateBlog, layoutMgr, cfg_layouts */

/**
 * Manages the generation and initialization of web page layouts from JSON configurations.
 * It supports various layout structures like tabs, accordions, cardinal panes, and groups,
 * and handles the embedding and initialization of Heurist widgets within these layouts.
 */
class HLayoutMgr {
    
    pnl_counter;
    body;
    _supp_options = {};
    _isEditMode = false;
    _main_layout_cfg = null;
    
    /**
     * Initializes a new instance of the HLayoutMgr.
     * Sets up initial properties like panel counter, body reference, edit mode status,
     * supplementary options, and main layout configuration.
     */
  constructor() {

    this.pnl_counter = 1;
    this.body = $(document).find("body");
    this._isEditMode = false;
    this._supp_options = {};
    this._main_layout_cfg = null;
  }

  /**
   * Assigns a unique key to a layout element if it doesn't already have one.
   * Also sets a default title and folder status based on children.
   * This key is used internally for managing layout elements.
   *
   * @param {Array<Object>} layout_array - The array of layout configuration objects.
   * @param {number} i - The index of the element in the layout array to process.
   * @returns {void}
   */
  #layoutInitKey(layout, i) {
      
    if(Array.isArray(layout) && i>=0){
        layout = layout[i];
    }  
      
    if (!layout.key) {
      layout.key = this.pnl_counter;
      layout.title = `<span data-lid="${this.pnl_counter}">${layout.name}</span>`;
      layout.folder = layout.children?.length > 0;
      this.pnl_counter++;
    }
  }

  /**
  * Not fully implemented
  * At the moment it 
  * 1) Creates page tree (json) (convertHTMLtoJSON)
  * 2) Inits widgets only
  * 
   * Initializes a layout by parsing existing HTML content within a container.
   * It looks for elements with `data-heurist-widget` or `data-heurist-app-id` attributes
   * to identify and initialize widgets.
   *
   * @param {(jQuery|HTMLElement|string)} container - The container element whose HTML content will be parsed.
   * @param {Object} [supp_options] - Supplementary options for widget parameters.
   * @returns {void} This method primarily has side effects (initializing widgets).
   */  
  layoutInitFromHTML( container, supp_options )
  {

      this._supp_options = supp_options || {};

      container = $(container);
      
      let pageTreeData;
      
      if(this._isEditMode){
            pageTreeData = this.convertHTMLtoJSON(container, 0);
      }

      //****************************
      //find all elements with data-heurist-cms
      $.each(container.find('[data-heurist-cms]'), (idx, ele) => {
          ele = $(ele);
          
          let widget_cfg = this.#convertWidgetHTMLtoJSON(ele);
          if(widget_cfg && widget_cfg.appid){
               //widget_cfg.key = this.pnl_counter;
               //this.pnl_counter++;
               ele.attr('data-hid', widget_cfg.key); //.addClass('cms-element');
               this.#layoutInitWidget(widget_cfg, ele);
          }
      });
      
      //find all elements with data-heurist-app-id
      if (container.find('[data-heurist-app-id]').length>0) {
            //old format v1: html with some widgets
            window.hWin.HAPI4.LayoutMgr.appInitFromContainer(null, container, this._supp_options);
      }
      
      return pageTreeData;
  }
  
  //
  //
  //
  #layoutInitFromJSON(layout, container, forStorage, isFirstLevel) {
    
    if (container == null) {
      container = document.createElement("div");
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

    if (isFirstLevel === true && layout.length>0) {
      if (this._supp_options.page_name) {
        layout[0].name = 'Page';
      }
      if (this._supp_options.keep_top_config && this._isEditMode) {
        this._main_layout_cfg = layout;
      }
      layout[0].isPage = true;
    }

    for (let i = 0; i < layout.length; i++) {
      this.#layoutInitKey(layout, i);

      const ele = layout[i];
      switch (ele.type) {
        case "cardinal":
          this.layoutInitCardinal(ele, container, forStorage);
          break;
        case "tabs":
          this.layoutInitTabs(ele, container, forStorage);
          break;
        case "accordion":
          this.layoutInitAccordion(ele, container, forStorage);
          break;
        default:
          if (ele.children && ele.children.length > 0) {
            this.layoutInitGroup(ele, container, forStorage);
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
      if (isFirstLevel===true && this._supp_options && !this._supp_options.heurist_isJsAllowed) {
//remove all javascript event attributes
        this.#layoutSanitize(container);
      }
      return layout;
    }
  }

  #layoutSanitize(container) {
    
    if(!container || container.length==0){
        return;
    }  
      
    $.each(container.children(), (idx, ele) => {
      ele = $(ele);
      this.#layoutSanitize(ele);
    });

    const ele2 = container.get(0);
    for (let i = 0; i < ele2.attributes.length; i++) {
      if (ele2.attributes[i].name.indexOf("on") === 0) {
        ele2.removeAttribute(ele2.attributes[i].name);
      }
    }
  }

  //  cms-widget - for widgets
  //  cms-group  - for containers: group,flex,cardinal,tabs,accordion
  //  cms-content - for content/text
  //
  #layoutCreateDiv(layout, classes, forStorage) {
      
      if (layout.dom_id && layout.dom_id.indexOf('cms-group-') === 0) {
          //id is reassigned on every page reload
          layout.dom_id = `cms-group-${layout.key}`;
      }

      let $d;

      if (forStorage) {
          //attributes
          // key - unique id withing edit session - it is assigned every time layout recreated in edit mode   
          // dom_id - unique html id                                                                          
          // css - css   
          // bsClasses
          
          // appid, type, name
          // layout.classes - user classes
          // layout.options - widget options
          let opts = layout.options?window.hWin.HEURIST4.util.cloneJSON(layout.options):{};
          if(layout.appid){
              opts.appid = layout.appid;
          }else if(layout.type){
              opts.type = layout.type;
          }
          if(layout.name){
              opts.name = layout.name;
          }
          if(layout.classes){
             opts.classes = layout.classes;
          }
          $d = $(`<div id="${layout.dom_id}"></div>`);
          opts = JSON.stringify(opts);
          $d[0].setAttribute('data-heurist-cms', opts);
          //$d.data('heurist-cms', opts);
          
      } else {
          $d = $(document.createElement("div"));

          if (!layout.dom_id) {
              
              do {
                  let uid = "" + window.hWin.HEURIST4.util.random();
                  uid = uid.substring(0,4);
                  
                  layout.dom_id = layout.appid
                  ? `cms-widget-${uid}`
                  : `cms-content-${uid}`;
              } while (this.body.find(`#${layout.dom_id}`).length > 0);
          }

          $d.attr("id", layout.dom_id).attr("data-hid", layout.key);

          if (classes) {
              $d.addClass(classes);
          }
      }

      if (layout.classes) { //adds publisher's classes
              $d.addClass(layout.classes);
      }

      return $d;
  }
  
  /*
  
  */
  #layoutSetCssAndClasses(layout, element){
    ifi(!element) return;
    if (!layout.css) layout.css = {};
    if (layout.css && !$.isEmptyObject(layout.css)) {
      element.css(layout.css);
    }
    if (layout.bsClasses){
        element.addClass(layout.bsClasses);
    }
  }
  
  /*
  
  */
  layoutInitGroup(layout, container, forStorage) {
      
        if(!layout.dom_id) layout.dom_id = 'cms-group-'+layout.key;
        
        let $d = this.#layoutCreateDiv(layout, !forStorage && this._isEditMode?'cms-element brick':'', forStorage);
        
        this.#layoutReplaceGroupDiv(container, $d)
        
        this.#layoutSetCssAndClasses(layout, $d);

        this.#layoutInitFromJSON(layout.children, $d, forStorage);
        
        return $d;
  }

  /*
  * assign text content to element
  */
  #layoutInitText(layout, container, forStorage) {
        const $d = this.#layoutCreateDiv(
            layout,
            !forStorage && this._isEditMode ? 'tinymce-body cms-element brick' : '', //later need to use either cms-element or brick
            forStorage
        );
        $d.appendTo(container);

        this.#layoutSetCssAndClasses(layout, $d);

        let content = "content";
        if (forStorage) {
            const aLangs = Object.keys(layout).filter((key) =>
                key.indexOf("content") === 0
            );
            //save all localizations
            if (aLangs.length > 1) {
                aLangs.forEach((lang) => {
                    const lang_code = lang.substring(7) || 'def';
                    $(
                        `<div css="${lang_code === 'def' ? '' : 'display:none'}" data-lang="${lang_code}">${layout[lang]}</div>`
                    ).appendTo($d);
                });
            } else {
                $d.html(layout[aLangs[0]]);
            }
        } else {
            //assign text content to element
            let lang = window.hWin.HAPI4.getLangCode3(this._supp_options['lang'], 'def');
            content = window.hWin.HAPI4.getContentTranslation(layout, lang);

            if(lang) $d.attr('data-lang', lang);

            
//TBD - call smarty to perform replaces                     
/*
console.log(content);
            if(content.indexOf('{$')>=0 && this._supp_options['page']  || this._supp_options['website']){
                content = content.replace('{$website.title}', this._supp_options.website?.title);
                content = content.replace('{$website.description}', this._supp_options.website?.description)
                content = content.replace('{$page.title}', this._supp_options.page?.title)
                content = content.replace('{$page.description}', this._supp_options.$page?.description)
            }
*/            
            $d.html(content);
        }
  }
  
  /**
   * Adds a widget to the layout.
   *
   * @param {Object} layout - The JSON configuration for the widget.
   * @param {jQuery} container - The container to add the widget to.
   * @returns {void}
   */
 #layoutAddWidget(layout, container, forStorage){

        let $d = this.#layoutCreateDiv(layout, !forStorage && this._isEditMode?'heurist-widget cms-element brick':'');

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
        if(app.minh>0 && !layout.css['minHeight'] && !layout.css['min-height']){
            layout.css['minHeight'] = app.minh;
        }

        if(layout.css && !$.isEmptyObject(layout)){
            
            $d.removeAttr('style');
            $d.css( layout.css );    
        }
        
        if(!forStorage){
            this.#layoutInitWidget(layout, container.find('div[data-hid='+layout.key+']'));
        }

    }
    
    //
    // returns widget descrition/definitions from cfg_widgets
    // this object contains name of widget, path to js, some default options
    //
    #getWidgetById(id){

        let i;
        for(i=0; i<window.hWin.cfg_widgets.length; i++){
            if(window.hWin.cfg_widgets[i].id==id){
                return window.hWin.cfg_widgets[i];
            }
        }
        return null;
    }
    
    //
    //
    //
    #layoutInitWidget(layout, container){

        let app = this.#getWidgetById(layout.appid); //find in app array (appid is heurist_Search for example)

        if(!layout.options) layout.options = {};

        let widgetOptions = $.extend({},layout.options); //window.hWin.HEURIST4.util.cloneJSON
         
        if(layout.appid=='heurist_Map'){
            widgetOptions['leaflet'] = true;
            widgetOptions['init_at_once'] = true;
        }
        
        if(this._supp_options[layout.appid]){
            widgetOptions = $.extend(widgetOptions, this._supp_options[layout.appid]);        
            
            if(layout.appid=='heurist_Navigation'){
                //keep supp_options separately for Navigation - since they are required for page init 
                widgetOptions['supp_options'] = this._supp_options;
            }
        }
        
        //lang,siteId,isEditMode
        
        if(this._supp_options['lang']){
            // xx - means it will use current language
            widgetOptions['language'] = window.hWin.HAPI4.getLangCode3(this._supp_options['lang'],'def');    
        }
        if(this._supp_options['siteId']){
            widgetOptions['siteId'] = this._supp_options['siteId'];
        }
        widgetOptions['isEditMode'] = this._supp_options['isEditMode'];
        
        if (app && app.script && app.widgetname) { //widgetname - function name to init widget

            if(window.hWin.HEURIST4.util.isFunction($('body')[app.widgetname])){ //OK! widget script js has been loaded            

                container[app.widgetname]( widgetOptions );   //call function
                
                container.attr('data-widgetname',app.widgetname);

            }else{

                $.getScript( window.hWin.HAPI4.baseURL + app.script, function() {  //+'?t='+(new Date().getTime())
                    if(window.hWin.HEURIST4.util.isFunction(container[app.widgetname])){
                        container[app.widgetname]( widgetOptions );   //call function
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Widget ${app.widgetname} not loaded. Verify your configuration`,
                            error_title: 'Widget loading failed',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }
                });

            }

        }
        

    }
  
    /*
    *
    */
    #layoutReplaceGroupDiv(container, new_element){

        const dom_id = new_element.attr('id');
        
        //find old one
        let old_element;
        if(container.attr('id')==dom_id){
            old_element = container;    
        }else{
            old_element = container.find('#'+dom_id);
        }
        
        //replace old one
        if(old_element.length>0){
            new_element.insertBefore(old_element);
            old_element.remove();
        }else{
            new_element.appendTo(container);
        }
        
        //remove old header (for tabs)
        //$('#'+dom_id+'-header').remove();
    }    
    
  
/**
   * Initializes or reinitializes a cardinal (multi-pane) layout structure within the given container.
   * Allows for defining regions like north, south, east, west, and center.
   *
   * @param {Object} layout - The JSON configuration object for the cardinal layout.
   * @param {jQuery} container - The jQuery object representing the container element for the cardinal layout.
   * @returns {void}
   */
    layoutInitCardinal(layout, container, forStorage){

        if(!layout.dom_id) layout.dom_id = 'cms-group-'+layout.key;
        
        let $parent = this.#layoutCreateDiv(layout, '', forStorage);
        
        this.#layoutReplaceGroupDiv(container, $parent)
        
        if( layout.css && !$.isEmptyObject(layout.css) ){
            $parent.css( layout.css );
        }
        
        let layout_opts = {applyDefaultStyles: true, maskContents: true, enableCursorHotkey: false};
    
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
                    $d2.attr('data-cms-options',JSON.stringify(layout.children[i].options));
                }
            
                $d2.appendTo($parent);
            }else{
                //create cardinal div
                let $d = $(document.createElement('div'));
            
                $d.addClass('ui-layout-'+pos)
                  .appendTo($parent);

                if(layout.children[i].children.length>1){
                  
                    if(!layout.dom_id) lpane.dom_id = 'cms-group-'+lpane.key;
                    //@todo additional container for children>1        
                    layout_opts[pos+'__contentSelector'] = '#'+lpane.dom_id;
                    
                    $d2 = this.#layoutCreateDiv(lpane, 'ui-layout-content2');  
                    $d2.appendTo($d);
                
                }else if(layout.children[i].children.length>0){
                    
                    let dom_id = layout.children[i].children[0].dom_id;
                    if(!dom_id){
                        dom_id = 'cms-group-'+lpane.key;
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
            $parent.layout( layout_opts );
        }
      
  }
  
  /**
   * Initializes or reinitializes a tabbed layout structure within the given container.
   * This is typically used during layout editing or dynamic updates.
   *
   * @param {Object} layout - The JSON configuration object for the tabs layout.
   * @param {jQuery} container - The jQuery object representing the container element for the tabs.
   * @returns {void}
   */
  layoutInitTabs(layout, container, forStorage){

        if(!layout.dom_id) layout.dom_id = 'cms-group-'+layout.key;
        
        let $d = this.#layoutCreateDiv(layout, !forStorage && this._isEditMode?'cms-element brick':'', forStorage);
                                          
        this.#layoutReplaceGroupDiv(container, $d)
        this.#layoutSetCssAndClasses(layout, $d);     
        
        //adds tab panels    
        this.#layoutInitFromJSON(layout.children, $d, forStorage);
        
        if(!forStorage) {
            //adds tab header
            let groupTabHeader = $('<ul>').attr('id',layout.dom_id+'-header');

            for(let i=0; i<layout.children.length; i++){
                $('<li>').html('<a href="#'+layout.children[i].dom_id
                                    +'"><span style="font-weight:bold">'
                                    +layout.children[i].name+'</span></a>')
                            .appendTo(groupTabHeader);
            }
            
            if(!layout.options?.nav_type){
                if(!layout.options) layout.options = {};
                layout.options.nav_type='nav-jquery';
            }
            
            if(layout.options?.nav_type!='nav-jquery'){
                //d-flex align-items-start
                
                //move all children to tabContent
                let tabContent = $('<div>').addClass('tab-content');
                $d.children().appendTo(tabContent);
                
                tabContent.prependTo($d);
                groupTabHeader.prependTo($d);
                
                // adds bootstrap classes
                groupTabHeader.addClass('nav');
                groupTabHeader.addClass(layout.options.nav_type);
                
                if(layout.options?.nav_dir=='nav-col'){
                    groupTabHeader.addClass('flex-column');
                    $d.addClass('d-flex align-items-start');
                }
                groupTabHeader.find('li').addClass('nav-item');
                groupTabHeader.find('li>a').addClass('nav-link')
                    .attr('data-bs-toggle','tab');

                $(groupTabHeader.find('li>a')[0]).addClass('active');
                    
                tabContent.children().addClass('tab-pane fade');
                $(tabContent.children()[0]).addClass('show active');
                
            }else{
                groupTabHeader.prependTo($d); //adds as a first child
                $d.tabs();        
            }
        }
        
        return $d;
        
    }
    
  /**
   * Initializes or reinitializes an accordion layout structure within the given container.
   * Useful for layout editing or dynamic content updates.
   *
   * @param {Object} layout - The JSON configuration object for the accordion layout.
   * @param {jQuery} container - The jQuery object representing the container element for the accordion.
   * @returns {void}
   */
    layoutInitAccordion(layout, container, forStorage){
       
        if(!layout.dom_id) layout.dom_id = 'cms-group-'+layout.key;
        
        let $d = this.#layoutCreateDiv(layout, !forStorage && this._isEditMode?'cms-element brick':'', forStorage);
        
        this.#layoutReplaceGroupDiv(container, $d)
        this.#layoutSetCssAndClasses(layout, $d);    
       
        //accordion panels    
        this.#layoutInitFromJSON(layout.children, $d, forStorage);


        if(!forStorage) {
            
            if(layout.options?.acc_type=='acc-bs'){
                // adds bootstrap classes
                $d.addClass('accordion');
                
                const isBtnCollapsed = layout.options?.acc_collapse?'collapsed':'';
                const isItemShow = layout.options?.acc_collapse?'':'show';

                for(let i=0; i<layout.children.length; i++){
              
                    let $child = $d.find('#'+layout.children[i].dom_id);
                    
      let $item = $(`<div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button ${isBtnCollapsed}" type="button" data-bs-toggle="collapse" data-bs-target="#${layout.children[i].dom_id}">
            ${layout.children[i].name}
          </button>
        </h2>
      </div>`);
      $d.append($item);
      $child.addClass(`accordion-collapse collapse accordion-body ${isItemShow}`).attr('data-bs-parent',`#${layout.dom_id}`);
      $item.append($child);
      
    /*
        <div id="${layout.children[i].dom_id}" class="accordion-collapse collapse show" data-bs-parent="#${layout.dom_id}">
          <div class="accordion-body">

          </div>
        </div>
    */                            
                }//for children
                
            }else{
                
                //accordion headers
                for(let i=0; i<layout.children.length; i++){
                    const child_dom_id = '#'+layout.children[i].dom_id;
                    $('<h2>').html( layout.children[i].name )
                             .insertBefore(child_dom_id);
                }
                
                $d.accordion({heightStyle: "content", 
                      active: false,
                      //active:(currGroupType == 'expanded')?0:false,
                      collapsible: layout.options?.acc_collapse!==false });        
            }
        }
        
        return $d;
    }
    
    //
    // Find configuration for element in array by internal key property
    //
    #layoutContentFindElement(content, ele_key){

        if(!Array.isArray(content)){
            if(content.key==ele_key){
                return content;    
            }
            if(content.children && content.children.length>0){
                return this.#layoutContentFindElement(content.children, ele_key);    
            }else{
                return null;
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
        return null; //not found
    }
    
    //
    // Find widget by application/widget name in cfg_widgets such as "heurist_SearchInput"
    //
    #layoutContentFindWidget(content, widget_name){
        
        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
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
        return null; //not found
    }

    //
    //
    //
    #layoutContentFindAllWidget(content){

        let res = [];
        
        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                let res2 =  this.#layoutContentFindAllWidget(content.children);    
                if(res2) res = res.concat(res2);
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].appid){
                res.push(content[i]);
            }else if(content[i].children && content[i].children.length>0){
                let res2 = this.#layoutContentFindAllWidget(content[i].children);    
                if(res2) res = res.concat(res2);
            }
        }
        return res;
    }
    
    //
    // Finds most used realm id 
    //
    #layoutContentFindMainRealm(content){
        //find all widgets on page
        let res = {};
        let widgets = this.#layoutContentFindAllWidget(content);
        for(let i=0; i<widgets.length; i++){
            if(!widgets[i].options.search_page && widgets[i].options.search_realm){
                if(res[widgets[i].options.search_realm]>0){
                    res[widgets[i].options.search_realm]++;
                }else{
                    res[widgets[i].options.search_realm]=1;
                }
            }
        }
        //find max usage
        let max_usage = 0; 
        let max_sg = ''
        widgets = Object.keys(res);
        for(let i=0; i<widgets.length; i++){
            if(res[widgets[i]]>max_usage){
                max_usage = res[widgets[i]];
                max_sg = widgets[i];
            }
        }
        return max_sg;
    }

    //
    // Find parent element for given key
    //
    #layoutContentFindParent(parent, ele_key){
        
        let children;
        if(Array.isArray(parent)){
            children = parent;
            parent = 'root';
        }else{
            children = parent.children;    
        }
        
        for(let i=0; i<children.length; i++){
            if(children[i].key == ele_key){
                return  parent;
            }else if(children[i].children && children[i].children.length>0){
                let res = this.#layoutContentFindParent(children[i], ele_key);    
                if(res) return res;
            }
        }
        return false; //not found
    }
    
    //
    // Replace element
    //    
    #layoutContentSaveElement(content, new_cfg){
            
        let ele_key = new_cfg.key;
        
        for(let i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                if(new_cfg.type && new_cfg.type.indexOf('text')==0){
                   content[i].content = new_cfg.content;
                }
                content[i] = new_cfg;
                return true 
            }else if(content[i].children && content[i].children.length>0){
                if (this.#layoutContentSaveElement(content[i].children, new_cfg)){
                    return true;
                }
            }
        }

        return false;            
    }
    
    //
    //
    //
    #prepareTemplate(layout, callback){ 
       
        if(layout.template=='default'){
        
           callback.call(this, layout.children[0]); 
            
        }else if(layout.template=='blog'){
            
           let ele = this.#layoutContentFindWidget(layout, 'heurist_SearchTree');
           if (ele && ele.options.init_svsID=='????') {
                layout.template = null;

                try{
                
                let that = this;                    
                    
                let sURL2 = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/blog.js';
                // 3. Execute template script to replace template variables, adds filters and smarty templates
                    $.getScript(sURL2, function(data, textStatus, jqxhr){ //it will trigger oncomplete
                          //function in blog.js
                          prepareTemplateBlog(that, layout, callback);
                          
                    }).fail(function( jqxhr, settings, exception ) {
                        console.error( 'Error in template script: '+exception );
                    });
                    
                    return true;    
                    
                }catch{
                    alert('Error in blog template script');
                }
           }
        }
    }
        
    
    //
    // Converts html to layout json
    //
    convertHTMLtoJSON(container, lvl){

        let res = [];
        let children = container.children();
        let that = this;
        

        if(lvl==0 && container.find('div[data-heurist-app-id]').length==0 && container.find('div[data-heurist-cms]').length==0){

            let ele;
            if(children.length==1){
                ele = $(children[0]);
            }else{
                ele = $('<div>');
                if(children.length>0){
                    container.children().appendTo(ele);
                }
                ele.appendTo( container );
            }
            
            res.push({name:'Content', type:"text",  content: ele[0].outerHTML });
            if(this._isEditMode){
                this.#layoutInitKey(res, 0);
                ele.attr('data-hid', res[0].key).addClass('cms-element');
                ele.addClass('tinymce-body cms-element brick');
            }
            
        }else{
            
        $.each(children, function(idx, ele){

            ele = $(ele);
            
            let child;
            
            let cmsClasses = 'cms-element brick';
            
            let widget_cfg = that.#convertWidgetHTMLtoJSON(ele);
            /*
            if(ele.attr('data-heurist-app-id')){
                //this is widget
                let opts = window.hWin.HEURIST4.util.isJSON(ele.text());

                child = {appid: ele.attr('data-heurist-app-id'),
                    options: opts};

                if(opts.__widget_name){
                    child.name = opts.__widget_name.replaceAll('=','').trim();
                }
                if(!child.name) child.name = "Widget "+lvl+'.'+idx;
            */
            if(widget_cfg && widget_cfg.appid){
                
                child = widget_cfg;
                //widget_cfg.key = this.pnl_counter;
                //this.pnl_counter++;
                if(!child.name) child.name = `Widget ${lvl}.${idx}`;
                
            }else if(widget_cfg && widget_cfg.type!='text') {
                //publisher defined GROUP|tabs|accordion|cardinal
                child = widget_cfg;
                if(!child.name) child.name = `Group ${lvl}.${idx}`;
                child.children = that.convertHTMLtoJSON(ele, lvl+1);
                
            }else if(widget_cfg?.type=='text' || ele.find('div[data-heurist-app-id]').length==0 && ele.find('div[data-heurist-cms]').length==0){
                
                //no widgets among children - convert to content
                child = widget_cfg?widget_cfg:{type: 'text'};
                
                if(!child.name) child.name = `Content ${lvl}.${idx}`;
                child.content = ele[0].innerHTML;
                cmsClasses = 'tinymce-body '+cmsClasses;

            }else{
                //assume these is a group of mixed unstructured content
                child = {name:`Group ${lvl}.${idx}`, type:'group', folder:true};
                
                child.children = that.convertHTMLtoJSON(ele, lvl+1);
                /*                
                cmsClasses = 'tinymce-body '+cmsClasses;
                //find all elements with data-heurist-cms
                $.each(ele.find('[data-heurist-cms]'), (idx, ele2) => {
                      ele2 = $(ele2);
                      let widget_cfg = that.#convertWidgetHTMLtoJSON(ele2);
                      that.#layoutInitKey( widget_cfg );
                      if(widget_cfg){
                          widget_cfg.mixed = true;
                          res.push(widget_cfg);
                      }
                });
                */
            }

            if(child){
                if(ele.attr('style') && !child['css']){
                    child['css'] = that.#css2json(ele.attr('style'));    
                }
                if(ele.attr('class')){
                    if(!child['classes']) child['classes'] = '';
                    child['classes'] += ele.attr('class');
                }
                
                if(this._isEditMode){
                    that.#layoutInitKey(child);
                    ele.attr('data-hid', child.key).addClass('cms-element');
                    ele.addClass(cmsClasses);
                }

                res.push(child);
            }
        }); //each children
        
        }

        if(lvl == 0){ //PAGE wrapper for root level
            let root_css = {};
            let root_cfg = that.#convertWidgetHTMLtoJSON(container);
            if(!root_cfg){
                root_cfg = {name:'Page', type:'group', folder:true, isPage:true, children:res};
            }else if(root_cfg.type=='group'){
                root_cfg['children'] = res; 
            }
            if(container.attr('style')){
                root_css = that.#css2json(container.attr('style'));    
                root_cfg['css'] = root_css;
            }
            if(container.attr('class')){
                if(!root_cfg['classes']) root_cfg['classes'] = '';
                root_cfg['classes'] += container.attr('class');
            }
            
            res = root_cfg;
            this.#layoutInitKey(res, 0);
            container.attr('data-hid', res.key).addClass('cms-element');
        }

        return res;
  }

    
  /*
  * Converts element with widget config to JSON configuration
  *  data-heurist-cms="{appid: , type: }"
  */ 
  #convertWidgetHTMLtoJSON(element){

      let widgetId = element.attr('data-heurist-cms');
      
      if(!widgetId){ //old version
          widgetId = element.attr('data-heurist-app-id');
      }

      if(!widgetId || widgetId=='text'){
          //widget is not defined
          return null;    
      }

      //widget configuration is value of attribute      
      let widget_cfg = window.hWin.HEURIST4.util.isJSON(widgetId);

      if(widget_cfg){ //attribute value is valid json
          widgetId = widget_cfg.appid || widget_cfg.type;
          if(widgetId=='text' || widgetId?.type=='text'){
               return widget_cfg;    
          }
      }else{          
         //take configuration from content of div
         widget_cfg = window.hWin.HEURIST4.util.isJSON(element.text());
         if(widget_cfg){
             element.empty();
             if(widgetId=='app'){
                widgetId = widget_cfg.appid;
             }
         }
      }
      
      if(!widget_cfg) widget_cfg = {};
      if(!widget_cfg.options){
            widget_cfg.options = window.hWin.HEURIST4.util.cloneJSON(widget_cfg);
            //except appid, name, type, classes
            delete widget_cfg.options.appid;
            delete widget_cfg.options.name;
            delete widget_cfg.options.type;
            delete widget_cfg.options.classes;
      }
      
      if(widgetId=='group' || widgetId=='tabs' || widgetId=='accordion'){

          widget_cfg.type = widgetId;
          widget_cfg.folder = true;
          
      }else{
          if(!widget_cfg.appid) widget_cfg.appid = widgetId;
          let app = this.#getWidgetById(widget_cfg.appid);

          if(!widget_cfg.name){
            const defName = app?app.name:'Widget not defined';
            widget_cfg.name = defName;
          }
      }
      
      widget_cfg.dom_id = element.attr('id');
      
      if(element.attr('style') && !widget_cfg['css']){
        widget_cfg['css'] = this.#css2json(element.attr('style'));    
      }
      
      return widget_cfg;
  }
    
    //
    //
    //
    #css2json(css) {
        let s = {};
        if (!css) return s;
        if (css instanceof CSSStyleDeclaration) {
            for (let i in css) {
                if ((css[i]).toLowerCase()) {
                    s[(css[i]).toLowerCase()] = (css[css[i]]);
                }        
            }
        } else if (typeof css == "string") {
            css = css.split(';');
            for (let i in css) {
                let vs = css[i].split(':');
                const key = String(vs.shift()).trim();
                const val = vs.join(':').trim();
                s[key.toLowerCase()] = val;
            }
        }
        return s;
    }    


  //============================================================================
  // Public methods

    
    // NEW 
    // Convert from JSON to human readable HTML string (to store in record page)
    // (without widget initialization)
    // 
    //  <div data-heurist-cms='cardinal|tabs|accordion|text'>
    //  <div data-heurist-cms='{"type":"cardinal|tabs|accordion|text","options":...}'
    //  <div data-heurist-cms='{"appid":"HRecordList","searchDomain":"sr1","viewMode": "offcanvas-start"}'
    //  <div data-heurist-cms='{"appid":"HRecordList","searchDomain":"sr1","viewMode": "offcanvas-start"}'
    //  <div data-heurist-cms="HRecordList">{options}</div>
    //
  /**
   * Converts a JSON layout configuration into a human-readable HTML string representation.
   * This HTML can be used for storage or for display in contexts where live widgets are not needed.
   * The private version also performs a round-trip conversion (JSON -> HTML -> JSON) for debugging.
   *
   * @param {(Array<Object>|Object)} content - The JSON layout configuration.
   * @returns {Array<Object>|Object} The result of converting the generated HTML back to JSON (primarily for debugging from the private method).
   */
   convertJSONtoHTML(content) {
        
        //from json
        //to html for storage        forStorage = true
        let res = this.#layoutInitFromJSON(content, null, true, true);
        
        //TEST PURPOSE and back to json
        //res = this.convertHTMLtoJSON(res, 0);
        
        console.log(res);
        
        return res;
    }
      
  
  /**
   * Converts an older v1 HTML-based CMS layout format to the current JSON configuration format.
   * In the v1 format, widget configurations are embedded within HTML elements using `data-heurist-app-id`.
   *
   * @param {string} layout_html - The HTML string of the old layout format.
   * @param {(jQuery|HTMLElement|string)} container_element - The container element where the old layout HTML will be temporarily rendered for parsing.
   * @returns {Array<Object>} The page configuration in the new JSON format.
   */
  convertOldCmsFormat(layout, container) {
    container = $(container);
    container.empty();
    container.html(layout);
    return this.convertHTMLtoJSON(container, 0);
  }
  
  /**
   * Assigns a unique key to a layout element if it doesn't already have one.
   * Also sets a default title and folder status based on children.
   * This key is used internally for managing layout elements.
   *
   * @param {Array<Object>} layout_array - The array of layout configuration objects.
   * @param {number} i - The index of the element in the layout array to process.
   * @returns {void}
   */
  layoutInitKey(layout, i) {
        this.#layoutInitKey(layout, i);
  }

  /**
   * Finds a specific layout element within a layout configuration tree by its internal key.
   *
   * @param {(Array<Object>|Object)} layout_cfg - The layout configuration (or a part of it) to search within.
   * @param {(string|number)} ele_key - The unique key of the element to find.
   * @returns {Object|null} The found layout element configuration, or null if not found.
   */
  layoutContentFindElement(layout_cfg, ele_key) {
    return this.#layoutContentFindElement(layout_cfg, ele_key);
  }


  /**
   * Finds the parent configuration object of a layout element specified by its key.
   *
   * @param {(Array<Object>|Object)} parent_config - The layout configuration (or a part of it, or 'root' for the top level) to search within.
   * @param {(string|number)} ele_key - The key of the element whose parent is to be found.
   * @returns {Object|string|false} The parent configuration object, 'root' if the element is at the top level, or false if not found.
   */
  layoutContentFindParent(parent_config, ele_key) {
    return this.#layoutContentFindParent(parent_config, ele_key);
  }


  /**
   * Finds a widget configuration within a layout tree by its application ID (e.g., "heurist_SearchInput").
   *
   * @param {(Array<Object>|Object)} layout_cfg - The layout configuration to search within.
   * @param {string} widget_name - The `appid` of the widget to find.
   * @returns {Object|null} The found widget configuration object, or null if not found.
   */
  layoutContentFindWidget(layout_cfg, widget_name) {
    return this.#layoutContentFindWidget(layout_cfg, widget_name);
  }


  /**
   * Finds the most frequently used `search_realm` ID among widgets in the layout configuration.
   * This can be used to determine a default or primary realm for the page.
   *
   * @param {(Array<Object>|Object)} layout_cfg - The layout configuration to analyze.
   * @returns {string} The `search_realm` ID that appears most often, or an empty string if none are found.
   */
  layoutContentFindMainRealm(layout_cfg) {
    return this.#layoutContentFindMainRealm(layout_cfg);
  }


  /**
   * Updates an element within the layout configuration JSON with new values.
   * The element is identified by its `key` property in `new_cfg`.
   *
   * @param {Array<Object>} layout_cfg - The layout configuration array to update.
   * @param {Object} new_cfg - The new configuration for the element, including its `key`.
   * @returns {boolean} True if the element was found and updated, false otherwise.
   */
  layoutContentSaveElement(layout_cfg, new_cfg) {
    return this.#layoutContentSaveElement(layout_cfg, new_cfg);
  }


  /**
   * Sets the edit mode for the layout manager.
   *
   * @param {boolean} newmode - True to enable edit mode, false to disable.
   * @returns {void}
   */
  setEditMode(newmode) {
    this.isEditMode = newmode;
  }

   /**
   * Prepares a layout template, potentially loading additional scripts or modifying the configuration.
   * For example, for a 'blog' template, it might load specific scripts and then invoke a callback.
   *
   * @param {Object} layout_config - The layout configuration object, which may include a `template` property.
   * @param {function} callback - A callback function to be executed after template preparation.
   * It's called with `this` set to the HLayoutMgr instance and the relevant part of the layout as an argument.
   * @returns {boolean|undefined} True if an asynchronous operation (like script loading) was initiated, otherwise undefined.
   */
  prepareTemplate(layout_config, callback) {
        return this.#prepareTemplate(layout_config, callback); // Added return
  }


  /**
   * Checks if all Heurist widgets currently in the document body have completed their initialization.
   *
   * @returns {boolean} True if all widgets are initialized, false otherwise.
   */
 layoutCheckWidgets() {
    const widgets = this.body.find("div[data-widgetname]");
    let are_all_widgets_inited = true;

    $.each(widgets, (i, item) => {
      const widgetname = $(item).attr("data-widgetname");
      if (widgetname) {
        const is_inited =
          $(item)[widgetname]("instance") &&
          $(item)[widgetname]("option", "init_completed");
          
        if (is_inited === false) {
          are_all_widgets_inited = false;
          return false;
        }
      }
    });
    return are_all_widgets_inited;
 }
  
  /**
   * Finds a predefined layout configuration by its ID from a global `cfg_layouts` array.
   *
   * @param {string} id - The ID of the predefined layout to find.
   * @returns {Object|null} The layout configuration object if found, otherwise null.
   */
 layoutGetById(id){
        if(id){
            id = id.toLowerCase();
            for(let i=0; i<window.hWin.cfg_layouts.length; i++){
                if(window.hWin.cfg_layouts[i].id.toLowerCase()==id){
                    return window.hWin.cfg_layouts[i];
                }
            }
        }
        return null;
 }    


 /**
   * Main method to initialize a layout. It generates HTML from the given layout configuration
   * (which can be JSON, HTML string, or sourced from the container itself) and initializes any widgets.
   *
   * @param {(Object|Array<Object>|string|null)} layout - The page layout configuration.
   * Can be a JSON object/array, an HTML string, or null/undefined to infer from the container's content.
   * @param {(jQuery|HTMLElement|string)} container - The container element to render the layout into.
   * @param {Object} [supp_options] - Supplementary options that can override or extend widget parameters
   * defined in the main layout configuration.
   * @returns {Object|Array<Object>|false|void} The processed layout configuration (if from JSON),
   * false if old v1 HTML format was processed, or void if initialized from existing HTML.
   */
 layoutInit(layout, container, supp_options, isEditMode, isFirstLevel) 
 {
//console.log(layout, supp_options);  
    this._supp_options = supp_options || {};
    this._isEditMode = isEditMode;
    
    isFirstLevel = (isFirstLevel!==false); //first level by default
  
    //main content
    if(layout && window.hWin.HEURIST4.util.isJSON(layout)){ //init from json
        return this.#layoutInitFromJSON(layout, container, false, isFirstLevel);
    }
    
    //not json, assing html to container and init widgets
    if(layout){
        $(container).html(layout);
    }
    
    return this.layoutInitFromHTML(container);
  }
  
  /**
   * Initializes a layout specifically from a JSON configuration.
   *
   * @param {(Object|Array<Object>)} layout_json - The page layout configuration in JSON format.
   * @param {(jQuery|HTMLElement|string)} container_element - The container element to render the layout into.
   * @param {Object} [supp_options] - Supplementary options for widget parameters.
   * @returns {Object|Array<Object>|false} The processed layout configuration, or false if an error/specific condition met in private method.
   */
  layoutInitFromJSON(layout_json, container_element, supp_options, isFirstLevel)
  {
    isFirstLevel = (isFirstLevel!==false);
    this._supp_options = supp_options || {};
    return this.#layoutInitFromJSON(layout_json, container_element, false, isFirstLevel);
  }
  
  /*
  *
  */
  executeWidgetMethod( element, widgetname, method, params ){
      
      let app = $(element);
      if(app && window.hWin.HEURIST4.util.isFunction($(app)[widgetname]) && $(app)[widgetname]('instance')){
          
          if(!Array.isArray(params)){
              params = [params];
          }
          
          $(app)[widgetname](method, ...params);
      
      }else if(!app){
            console.log('widget not found');
      }else if(!window.hWin.HEURIST4.util.isFunction($(app)[widgetname])){
            console.log('widget '+widgetname+' not loaded');
      }
    } 
}