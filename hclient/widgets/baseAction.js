/**
* @file baseAction.js
* @brief BASE widget for popup dialogue
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/

/**
 * @namespace Widgets
 */

/**
 * @class baseAction
 * @memberof Widgets
 * 
 * @widget heurist.recordAction
 * @description Base widget for popup dialogue
 *
 * @param {object} options - Configuration options for the widget.
 */
 $.widget( "heurist.baseAction", {

    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {object}
     * @property {string} actionName - The name of the action.
     * @property {string} path - Non-default path to HTML content.
     * @property {string} htmlContent - General layout HTML content.
     * @property {?string} helpContent - If false, help button is hidden. If null, it sets the name of the help file to the widgetName. Help file must be in documentation/context_help folder.
     * @property {string} default_palette_class - The default palette class.
     * @property {boolean} isdialog - Show as dialog.
     * @property {boolean} supress_dialog_title - Hide dialog title bar.
     * @property {number} height - The height of the dialog.
     * @property {number} width - The width of the dialog.
     * @property {object} position - The position of the dialog.
     * @property {boolean} modal - If true, the dialog will be modal.
     * @property {string} title - The title of the dialog.
     * @property {boolean} innerTitle - Show title as top panel.
     * @property {?function} onInitFinished - Event listener when dialog is fully initialized.
     * @property {?function} beforeClose - To show warning before close.
     * @property {?function} onClose - Event listener when dialog is closed.
     * @property {boolean} keep_instance - Keep instance after close.
     * @property {?object} hapi - The Heurist API object.
     */
    options: {

        actionName: '',

        path: '',  // non default path to html content 
        htmlContent: '', //general layout
        helpContent: null, //if false help button is hidden, if null it sets name of help file to widgetName,
                           // help file must be in documentation/context_help folder

        default_palette_class: 'ui-heurist-admin', 

        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        supress_dialog_title: false, //hide dialog title bar (applicable if isdialog=true
        
        height: 400,
        width:  760,
        position: null,
        modal:  true,
        title:  '',
        innerTitle: false, //show title as top panel 

        //listeners
        onInitFinished:null,  // event listener when dialog is fully inited
        beforeClose:null,     // to show warning before close
        onClose:null,

        keep_instance: false,

        hapi: null
    },

    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {jQuery}
     * @description Shorthand for this.element.find.
     */
    _$: $, // shorthand for this.element.find
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {object}
     * @description HEURIST4.utils.
     */
    $H: window.hWin.HEURIST4?.util, // HEURIST4.utils
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {object}
     * @description HEURIST4.msg.
     */
    $Hmsg: window.hWin.HEURIST4?.msg, // HEURIST4.msg
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {object}
     * @description HEURIST4.ui.
     */
    $Hui: window.hWin.HEURIST4?.ui, // HEURIST4.ui
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {?object}
     * @description The Heurist API object.
     */
    HAPI: null,
    
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @type {?object}
     * @description Reference to itself as dialog.
     * @see Widgets.baseAction#options.isdialog
     */
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @type {?object}
     * @description The toolbar object.
     */
    _toolbar:null,
    
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @type {boolean}
     * @description Flag to indicate if the widget is initialized.
     */
    _is_inited: false,
    
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @type {boolean}
     * @description Flag to indicate if the content needs to be loaded.
     */
    _need_load_content:true, //flag 
    
    /**
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @type {boolean}
     * @description Variable to be passed to options.onClose event listener.
     */
    _context_on_close:false, //variable to be passed to options.onClose event listener
    
    /**
     * @function _create
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description The widget's constructor.
     */
    _create: function() {
        // prevent double click to select text

        this._$ = selector => this.element.find(selector);

        this.HAPI = this.options.hapi ?? window.hWin.HAPI4;
    }, //end _create
    
    /**
     * @function _init
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description Load configuration and call _initControls.
     */
    _init: function() {
        
        if(this.options.keep_instance && this._is_inited){
            if(this.options.isdialog){
                this.popupDialog();
            }else{
                this.element.show();
            }
            return;
        }

        if(this.options.htmlContent==='' && this.options.actionName!=''){ // && this.options.path===''
            this.options.htmlContent = this.options.actionName+'.html';
        }
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }else{
            this.element.addClass('ui-heurist-bg-light');
        }
        
        //init layout
        let that = this;

        //load html from file
        if(this._need_load_content && this.options.htmlContent){  //load general layout      
            
            let url = this.options.htmlContent.indexOf(window.hWin.HAPI4.baseURL)===0
                    ?this.options.htmlContent
                    :window.hWin.HAPI4.baseURL+'hclient/' 
                        + this.options.path + this.options.htmlContent
                        +'?t='+window.hWin.HEURIST4.util.random();
            
            this.element.load(url, 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }else if(that._initControls() && 
                        window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                }
            });
            
        }else if(that._initControls() &&
                window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
                    that.options.onInitFinished.call(that);
        }
    },
    
     
    /**
     * @function _initControls
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description Invoked from _init after loading of html content.
     * @returns {boolean} - True if successful.
     */
    _initControls:function(){
        
        let that = this;
        
        //find and activate event listeners for elements
        
        if(this.options.isdialog){
            this._$('.ui-dialog-buttonset').hide();
            this._$('.ui-heurist-header').hide();
            
            this.popupDialog();
            
        }else {
            this._innerTitle = this._$('.ui-heurist-header');
            
            if(this.options.innerTitle){ 

                let fele = this.element.children().get(0);
                
                if(this._innerTitle.length==0){ //not created yet
                    //titlebar            
                    this._innerTitle = $('<div class="ui-heurist-header" style="top:0px;"></div>')
                                        .insertBefore(fele);
                    $(fele).css('margin-top', '38px');
                }
            
                this._innerTitle.text(this.options.title);

                this.closeBtn = $('<button>').button({icon:'ui-icon-closethick',showLabel:false, title:window.hWin.HR('Close')}) 
                .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                .addClass('ui-fade-color')
                .insertBefore( fele );
                this._on(this.closeBtn, {click:function(){
                    this.closeDialog();
                }});
                this.closeBtn.find('.ui-icon-closethick').css({'color': 'rgb(255,255,255)'});
                
                
            }else{
                this._innerTitle.hide();
            }
         
            // bottom bar buttons
            let btnPanel = this._$('.ui-dialog-buttonset');
            if(btnPanel.length>0){
                let btn_array = this._getActionButtons();
                btn_array.forEach(function(btn){
                    let btn_opts = {label:btn.label || btn.text,
                                   icon:btn.icon || btn.icons,
                                   showLabel:btn.showLabel!==false};
                    
                    $('<button>',btn).button(btn_opts).appendTo(btnPanel);
                });
            }
        }
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 
        
        this._is_inited = true;
        
        return true;
    },

    /**
     * @function _getActionButtons
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description Returns an array of button definitions.
     * @returns {Array<object>} - An array of button definitions.
     */
    _getActionButtons: function(){
        
        let that = this;        
        return [
                 {text:window.hWin.HR('Cancel'), 
                    class:'btnCancel',
                    css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                    click: function() { 
                        that.closeDialog();
                    }},
                 {text:window.hWin.HR('Go'),
                    class:'ui-button-action btnDoAction',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click:function() { 
                            that.doAction(); 
                    }}  
                 ];
    },
    
    /**
     * @function changeTitle
     * @memberof Widgets.baseAction
     * @instance
     * @description Changes the title of the dialog.
     * @param {string} new_title - The new title.
     */
    changeTitle: function(new_title){
        
       //this.options.title = new_title; 
        
       if(this.options.isdialog){
           this._as_dialog.parent().find('.ui-dialog-title').text(new_title);        
       }else{
           this._$('.ui-heurist-header').text(new_title);
       } 
        
    },

    /**
     * @function _defineActionButton2
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description Defines action buttons for the edit toolbar.
     * @param {object} options - The button options.
     * @param {jQuery} container - The container to append the button to.
     */
    _defineActionButton2: function(options, container){        
        
        //for dialog buttons jquery still uses "text"
        let btn_opts = {label:options.label || options.text, icon:options.icon || options.icons, title:options.title};
        
        let btn = $('<button>').button(btn_opts)
                    .appendTo(container);

        if(window.hWin.HEURIST4.util.isFunction(options.click)){
            btn.on('click', options.click);
        }                    
                    
        if(options.id){
            //btn.attr('id', options.id); 
            btn.addClass(options.id);
        }
        if(options.css){
            btn.css(options.css);
        }
        if(options.class){
            btn.addClass(options.class);
        }
    },
    
    
    /**
     * @function _initDialog
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description Initializes the dialog widget.
     * @see Widgets.baseAction#popupDialog
     * @see Widgets.baseAction#closeDialog
     */
    _initDialog: function(){
        
            let options = this.options,
                btn_array = this._getActionButtons();
            const that = this;
            if(!options.beforeClose){
                    options.beforeClose = function(){
                        //show warning on close
                        return true;
                    };
            }
            
            if(options.position==null) options.position = { my: "center", at: "center", of: window };
            
            let maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
            if(options['width']>maxw) options['width'] = maxw*0.95;
            let maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
            if(options['height']>maxh) options['height'] = maxh*0.95;
            let $dlg = this.element.dialog({
                autoOpen: false ,
                //element: this.element[0],
                height: options['height'],
                width:  options['width'],
                modal:  (options['modal']!==false),
                title: window.hWin.HEURIST4.util.isempty(options['title'])?'':window.hWin.HR(options['title']), //title will be set in  initControls as soon as entity config is loaded
                position: options['position'],
                beforeClose: options.beforeClose,
                resizeStop: function( event, ui ) {
                    that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                },
                close:function(){
                    if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                      //that.options.onClose(that._currentEditRecordset);  
                      that.options.onClose( that._context_on_close );
                    } 
                    if(!that.options.keep_instance){
                        that._as_dialog.remove();
                    }
                },
                buttons: btn_array
            }); 
            this._as_dialog = $dlg; 
            
    },
    
    /**
     * @function popupDialog
     * @memberof Widgets.baseAction
     * @instance
     * @description Shows itself as a popup dialog.
     */
    popupDialog: function(){
        if(this.options.isdialog){

            let $dlg = this._as_dialog.dialog("open");
            
            
            if(this._as_dialog.attr('data-palette')){
                $dlg.parent().removeClass(this._as_dialog.attr('data-palette'));
            }
            if(this.options.default_palette_class){
                this._as_dialog.attr('data-palette', this.options.default_palette_class);
                $dlg.parent().addClass(this.options.default_palette_class);
                this.element.removeClass('ui-heurist-bg-light');
            }else{
                this._as_dialog.attr('data-palette', null);
                this.element.addClass('ui-heurist-bg-light');
            }

            if(this.options.supress_dialog_title) $dlg.parent().find('.ui-dialog-titlebar').hide();

            if(this.options.helpContent==null){
                this.options.helpContent = this.widgetName;
            }
            
            if(this.options.helpContent){
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);    
            }
        }
    },
    
    /**
     * @function closeDialog
     * @memberof Widgets.baseAction
     * @instance
     * @description Closes the dialog.
     * @param {boolean} [is_force=false] - If true, the `beforeClose` event will not be triggered.
     */
    closeDialog: function(is_force){

        if(this._as_dialog && this._as_dialog.length > 0 && this._as_dialog.dialog('instance') !== undefined){

            if(is_force===true){
                this._as_dialog.dialog('option','beforeClose',null);
            }
            
            this._as_dialog.dialog("close");
        }else{
            
            let canClose = true;
            if(window.hWin.HEURIST4.util.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                    this.options.onClose( this._context_on_close );
                }
            }
            this.element.hide();
        }
    },

    /**
     * @function doAction
     * @memberof Widgets.baseAction
     * @instance
     * @description Performs the action.
     */
    doAction: function(){
        return;
    },

    /**
     * @function _afterActionEventHandler
     * @memberof Widgets.baseAction
     * @instance
     * @private
     * @description After action event handler.
     * @param {object} response - The response from the action.
     */
    _afterActionEventHandler: function( response ){
       return; 
    },

  
});
