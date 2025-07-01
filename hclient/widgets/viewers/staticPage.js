/**
 * @file staticPage.js
 * @brief Displays static HTML content, either directly or within an iframe, inside a Heurist widget structure.
 * @fileOverview
 * This file defines the `heurist.staticPage` jQuery UI widget. Its primary purpose is to load
 * and display static HTML content from a specified URL. The content can be loaded directly into
 * the widget's main div or, if `options.isframe` is true, into an iframe. The URL can contain
 * placeholders like `[dbname]` and `[layout]` which are replaced with actual values from the
 * Heurist environment. The widget can initialize its content loading at creation or when it
 * becomes visible. It also handles refreshing content on login/logout events.
 *
 * @project     Heurist academic knowledge management system
 * @package hclient\widgets\viewers
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/**
 * @widget heurist.staticPage
 * @description A widget for displaying static HTML content from a URL.
 * The content can be loaded directly into the widget or within an iframe.
 * URL placeholders `[dbname]` and `[layout]` are supported.
 *
 * @example
 * $('#myStaticPageContainer').staticPage({
 *     title: 'About Us',
 *     url: 'content/about.html',
 *     isframe: false,
 *     init_at_once: true
 * });
 */
$.widget( "heurist.staticPage", {

    /**
     * @typedef {object} heurist.staticPage.options
     * @description Options for configuring the staticPage widget.
     * @property {string} [title='']
     *  The title to display for this page or widget instance. This can update
     *  associated header elements if they follow a specific class naming convention (`.header` + widget ID).
     * @property {string|null} [url=null]
     *  The URL from which to load the static content. Placeholders `[dbname]` and `[layout]`
     *  will be replaced with the current database name and layout identifier respectively.
     *  Relative URLs are resolved based on the Heurist base URL.
     * @property {boolean} [isframe=false]
     *  If true, the content from `options.url` will be loaded into an iframe.
     *  If false, the content will be loaded directly into the widget's main content div.
     * @property {boolean} [init_at_once=false]
     *  If true, the content loading process (`_refresh`) is triggered immediately upon widget creation.
     *  If false, content loading might be deferred (e.g., until the widget becomes visible,
     *  though explicit visibility handling like `myOnShowEvent` is also present).
     */
    options: {
        title: '',
        url:null,
        isframe: false,
        init_at_once: false
    },

    /**
     * @property {string|null} _loaded_url
     * @private
     * @description Stores the URL that was last loaded by the widget.
     * This is used by `_refresh` to avoid reloading the same content if the `options.url` hasn't changed.
     * It is reset on login/logout events to force a reload.
     */
    _loaded_url:null,

    /**
     * @function _create
     * @memberof heurist.staticPage
     * @instance
     * @private
     * @description Initializes the widget. Creates the main content div (`div_content`).
     * Binds a custom event `myOnShowEvent` (likely for handling visibility changes in tabbed layouts or similar)
     * and a global `ON_CREDENTIALS` event listener to refresh content on login/logout.
     * If `options.init_at_once` is true, it calls `_refresh()` to load content immediately.
     */
    _create: function() {

        let that = this;

        this.div_content = $('<div>').css({width:'100%', height:'100%'})  //.css('overflow','auto')
        /*.css({
        position:'absolute', top:(this.options.title==''?0:'2.5em'), bottom:0, left:0, right:0,
        'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center'})*/
        .appendTo( this.element );

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        
        $(this.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS, function(e, data) {
            that._loaded_url = null; //reload on login-logout
            that._refresh();
        });
        
        
        if(this.options.init_at_once){
            that._refresh();  
        }        
       

    }, //end _create

    /**
     * @function _setOptions
     * @memberof heurist.staticPage
     * @instance
     * @private
     * @description Called when options are set on the widget. Uses `_superApply` to call the base
     * widget's method, ensuring proper option handling, and then calls `_refresh()` to apply changes.
     * @param {object} options An object containing option key-value pairs to set.
     */
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },

    /*
     * @function _setOption
     * @memberof heurist.staticPage
     * @instance
     * @private
     * @ignore
     * @description (Commented out in original code) Sets a single option on the widget.
     * If the 'url' key is set, it prepends the Heurist base URL if not already an absolute URL.
     * If the 'title' key is set, it updates associated header elements.
     * Calls `_super` to set the option and then `_refresh()` to apply changes.
     * @param {string} key The name of the option to set.
     * @param {*} value The new value for the option.
     */
    /*_setOption: function( key, value ) {
    if(key=='url'){
    value = window.hWin.HAPI4.baseURL + value;
    }else if (key=='title'){
    var id = this.element.attr('id');
    $(".header"+id).html(value);
    $('a[href="#'+id+'"]').html(value);
    }

    this._super( key, value );
    this._refresh();
    },*/

    /**
     * @function _refresh
     * @memberof heurist.staticPage
     * @instance
     * @private
     * @description Refreshes the content of the widget.
     * Updates the title if `options.title` is set and corresponding header elements exist.
     * If the widget is not visible or `options.url` is empty, it does nothing.
     * If `options.url` has changed since the last load, it processes the URL (replacing placeholders),
     * then loads the content either directly into `div_content` or into an iframe
     * (if `options.isframe` is true). Manages a loading animation during iframe load.
     * Updates `_loaded_url` after successful loading.
     */
    _refresh: function(){

        if(this.options.title!=''){
            let id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }

        //refesh if element is visible only - otherwise it costs much resources
        if(!this.element.is(':visible') || window.hWin.HEURIST4.util.isempty(this.options.url)) return;

        if(this._loaded_url!==this.options.url){

            let url = this.options.url.replace("[dbname]",  window.hWin.HAPI4.database);
            url = url.replace("[layout]",  window.hWin.HAPI4.sysinfo['layout']);
            if(this.options.url.indexOf('http://')<0 && this.options.url.indexOf('https://')<0){
                this.options.url = window.hWin.HAPI4.baseURL + url;
            }

            
            if(this.options.isframe){

                if(!this.pageframe){ 
                    let that = this;
                    this.element.css({overflow: 'hidden'});
                    this.pageframe = $( "<iframe>" ).css({overflow:'hidden !important', width:'100% !important'}).appendTo( this.div_content );
                    this.pageframe.on('load', function(){
                        that.loadanimation(false);
                    })
                }
                this.loadanimation(true);
                this.pageframe.attr('src', this.options.url); // Uses the potentially modified this.options.url
            }else{
                $(this.div_content).load(this.options.url); // Uses the potentially modified this.options.url
            }
            this._loaded_url = this.options.url;
        }

    },

    /**
     * @function _destroy
     * @memberof heurist.staticPage
     * @instance
     * @private
     * @description Cleans up the widget when it is destroyed.
     * Unbinds the custom `myOnShowEvent` and removes the main content div (`div_content`)
     * and its children (including the iframe if present).
     */
    _destroy: function() {

        this.element.off("myOnShowEvent");

        // remove generated elements
        this.div_content.remove();
    },

    /**
     * @function loadanimation
     * @memberof heurist.staticPage
     * @instance
     * @description Shows or hides a loading animation on the widget's content area.
     * The animation is a centered GIF background image.
     * @param {boolean} show If true, displays the loading animation. If false, removes it
     * by setting the background to 'none'.
     */
    loadanimation: function(show){
        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },

});
