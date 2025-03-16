/**
* HBase - BASE widget for all Heurist UI widgets
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


$.widget( 'heurist.HBaseWidget', {
    
    // default options
    options: {
        hapi: null,
        
        resourcePath: null, //relative path+filename to resources: html, css and localization
        htmlContent: null, // custom content
        uiLibrary: null,   // 'bootstrap' or 'jqueryui'
        
        //event listeners
        onInitFinished: null
    },
    
    $H: window.hWin.HEURIST4.util,
    _$: $, //shorthand for this.element.find
    HAPI: null,

    _widgetId: null,    
    _needLoadContent: true, //flag to avoid repeatable load of html content
    _needLoadCss: false,
    _initCompleted: false,

    //
    // the widget's constructor
    //
    _create: function() {
        
        this._$ = selector => this.element.find(selector); //querySelector(selector); 
        
        this.HAPI = this.options.hapi??window.hWin.HAPI4;

        // prevent double click to select text
        this.element.disableSelection();
        
        if(this.$H.isempty(this.element.attr('id'))){
            this.element.uniqueId();
        }
        this._widgetId  = this.element.attr('id');

    }, //end _create

    //
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    //
    _init: function() {
        
        let that = this;    
        
        if(this.options.resourcePath && this._needLoadCss ){
            this._needLoadCss = false;
        
            const isCssLoaded = false; //selectorExists('.recordList-icon');
            if(!isCssLoaded){
                //add widget classes
                let css_url = this.HAPI.baseURL + this.options.resourcePath + '.css';
                $.getStyles(css_url);
            }
        }
        
        if(this.$H.isempty(this.options.htmlContent)){ 
            //load default content
            if(this.options.resourcePath && this._needLoadContent){
            
                this._needLoadContent = false;
                
                //get html resource url
                //let url = this.HAPI.HRes(this.options.resourcePath + '.html');
                let url = this.HAPI.baseURL
                            + this.options.resourcePath + '.html'
                            + '?t='+this.$H.random();
                            
                // +(this.HAPI.getLocale()=='FRE'?'_fre':'')+'.html';                         
                
                this.element.load(url, 
                function(response, status, xhr){
                    if ( status == "error" ) {
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: response,
                            error_title: 'Failed to load HTML content',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }else {
                        that._initControls();
                    }
                });
                return;
            }
        }else{
            //custom content
            this.element.html(this.options.htmlContent);
        }
        
        this._initControls();
    },
    
    /*
    * Use it a) to add event listeners for subelements of this widget
    *     b) perform some default actions (intial search for example) 
    * It is invoked from _init after loading of html content
    */
    _initControls:function(){
        
        /* For example
            this._on(this._('#myActionButton'), {click:()=>{}});
        */
        
        this._initCompleted = true;
        //trigger event
        if (this.$H.isFunction(this.options.onInitFinished)){
            this.options.onInitFinished.call(this);
        }
    },

    /* 
    * Use it to show/hide elements depends on current login status
    */
    _refresh: function(){

        if(!this._initCompleted) return;

        //show hide elements according to user status
        /* For example
        if(this.HAPI.has_access()){ //logged in
            this._('.logged-in-only').css('visibility','visible');
        }else{
            this._('.logged-in-only').css('visibility','hidden');
        }
        */
    },
    
    /* 
    * Cleanup. Use it remove generated elements and off event listeners
    */
    _destroy: function() {
        // remove generated elements
    }
});
