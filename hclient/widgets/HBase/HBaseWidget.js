/**
 * @file HBaseWidget.js
 * @brief Base (abstract) widget for all Heurist UI widgets
 * @fileOverview This widget handles the initialization process:
 * 1) Loads resources (CSS, HTML, localization) from `options.resourcePath` or `options.htmlContent`
 * 2) Calls `_initControls` after loading content, then triggers `options.onInitFinished`
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */

/**
 * @namespace Widgets.UI
 * @description User Interface widgets for v7. They use bootstrap for styling.
 */

/** 
 * @class HBaseWidget
 * @memberof Widgets.UI
 * @description Base (abstract) widget for all Heurist UI widgets.
 * This widget handles the initialization process:
 * 1) Loads resources (CSS, HTML, localization) from `options.resourcePath` or `options.htmlContent`
 * 2) Calls `_initControls` after loading content, then triggers `options.onInitFinished`
 *
 * @param {object} options - Configuration options for the widget.
 */
$.widget( 'heurist.HBaseWidget', {
    
    /**
     * @memberof Widgets.UI.HBaseWidget
     * @type {object}
     * @property {object} hapi - HAPI instance
     * @property {string} resourcePath - Path to resources (HTML, CSS, localization)
     * @property {string} htmlContent - Custom content (if provided, overrides `resourcePath`)
     * @property {string} uiLibrary - UI framework: 'bootstrap' or 'jqueryui'
     * @property {function} onInitFinished - Event listener callback when initialization is complete
     */
    options: {
        hapi: null, // HAPI instance
        
        resourcePath: null, // Path to resources (HTML, CSS, localization)
        htmlContent: null,  // Custom content (if provided, overrides `resourcePath`)
        uiLibrary: null,    // UI framework: 'bootstrap' or 'jqueryui'
        
        // Event listener callback when initialization is complete
        onInitFinished: null
    },

    // Utility shortcuts
    $H: window.hWin.HEURIST4.util, // Utility functions
    _$: $, // Shorthand for querying elements within `this.element`
    HAPI: null, // HAPI instance

    // Internal state variables
    _widgetId: null,    
    _needLoadContent: true, // Prevents repeated HTML content loads
    _needLoadCss: false,    // Controls CSS loading
    _initCompleted: false,  // Indicates if initialization is finished
    
    // Container for widget content (by default, `this.element`)
    _container: null, 
    _optionsEditor: null, //container for options editor
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseWidget
     * @description Widget constructor: Initializes the component.
     */
    _create: function() {
        
        // Define a shorthand function for querying elements inside `this.element`
        this._$ = selector => this.element.find(selector);  //querySelector(selector);
        
        // Assign HAPI instance (fallback to global HAPI4 if not provided)
        this.HAPI = this.options.hapi ?? window.hWin.HAPI4;

        // Prevents double-click text selection
        this.element.disableSelection();
        
        // Ensure the element has a unique ID
        if (this.$H.isempty(this.element.attr('id'))) {
            this.element.uniqueId();
        }
        
        // Store widget ID and set the container
        this._widgetId = this.element.attr('id');
        this._container = this.element;
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseWidget
     * @description Initializes the widget. Called automatically when the widget is created.
     */
    _init: function() {
        let that = this;

        // Load CSS if required
        if (this.options.resourcePath && this._needLoadCss) {
            this._needLoadCss = false;

            const isCssLoaded = false; // Placeholder, could be refined later selectorExists('.recordList-icon');
            if (!isCssLoaded) {
                let cssUrl = this.HAPI.baseURL + this.options.resourcePath + '.css';
                $.getStyles(cssUrl);
            }
        }

        // If no custom HTML content is provided, attempt to load from resource path
        if (this.$H.isempty(this.options.htmlContent)) {
            if (this.options.resourcePath && this._needLoadContent) {
                this._needLoadContent = false;

                // Construct the URL for loading HTML content
                // +(this.HAPI.getLocale()=='FRE'?'_fre':'')+'.html';                         
                let url = this.HAPI.baseURL + this.options.resourcePath + '.html' + '?t=' + this.$H.random();
                
                // Load HTML content into the container
                this.loadHtmlContent(this._container, url, this._initControls);
                return;
            }
        } else {
            // Use custom content directly
            this._container.html(this.options.htmlContent);
        }

        // Initialize UI controls after loading content
        this._initControls();
    },    
    
    /**
     * @memberof Widgets.UI.HBaseWidget
     * @description Loads HTML content into the target container.
     * @param {jQuery} target - The element to load content into.
     * @param {string} url - The URL to fetch content from.
     * @param {Function} callback - The function to call after content is loaded.
     */
    loadHtmlContent: function(target, url, callback) {
        let that = this;
        target.load(url, function(response, status, xhr) {
            if (status === "error") {
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: response,
                    error_title: `Failed to load HTML content: ${url}`,
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
            } else {
                callback.call(that);
            }
        });
    },    

    
    /**
     * @private
     * @memberof Widgets.UI.HBaseWidget
     * @description Initializes UI controls and event listeners after content is loaded.
     */
    _initControls: function() {
        // Example: Add an event listener for a button
        // this._on(this._$('#myActionButton'), {click: () => { /* handle click */ } });

        this._initCompleted = true;

        // Trigger the onInitFinished event if defined
        if (this.$H.isFunction(this.options.onInitFinished)) {
            this.options.onInitFinished.call(this);
        }
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseWidget
     * @description Refreshes the widget, updating UI elements based on login status.
     */
    _refresh: function() {
        if (!this._initCompleted) return;

        // Example: Show/hide elements based on user authentication
        // if (this.HAPI.has_access()) {
        //     this._$('.logged-in-only').css('visibility', 'visible');
        // } else {
        //     this._$('.logged-in-only').css('visibility', 'hidden');
        // }
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseWidget
     * @description Cleanup function. Removes generated elements and event listeners.
     */
    _destroy: function() {
        // Implement cleanup logic here if necessary
        if(this._optionsEditor!=null){
            this._optionsEditor.remove();    
        }
    },
    
    /**
     * @memberof Widgets.UI.HBaseWidget
     * @description Opens options editor popup
     * @param {jQuery} container - The element to load content into.
     * @param {Function} onChange - The function to call after content is loaded.
     * @param {jQuery} menuParent - The element to load content into.
     */
    openOptionsEditor: function(container, onChange, menuParent){
        
        const optEditor = this.widgetName+'Opts';
        
        if(this._optionsEditor==null || container){ //update if container defined
            this._optionsEditor = container?container:$('<div>').appendTo(this.element);    
        }

        if(this._optionsEditor[optEditor]('instance')){
            this._optionsEditor[optEditor]('show', this.options);
        }else{
            let that = this;
            this._optionsEditor[optEditor]({editOptions: this.options, 
                        viewMode: container ?'inline':'popup', 
                        isHeaderVisible: container ?false:true,
                        menuParent: menuParent, 
                        onChange: onChange,
                        onClose:this.onCloseOptionEditor});
                                        //recordTemplate: this.options.templateView,
                                        //keepInstance: true});
        }
    },
    
    /**
     * @memberof Widgets.UI.HBaseWidget
     * @description The function to call after the option editor is closed.
     * @param {Object} newOptions - The new options from the editor.
     */
    onCloseOptionEditor: function(newOptions){
        
    }
    
});
