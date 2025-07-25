/**
 * @file HBaseView.js
 * @brief A container widget for displaying popups using jQuery Dialog, Bootstrap Modal, Bootstrap Offcanvas, or inline.
 * @fileOverview A container widget for displaying popups using:
 * - jQuery Dialog (popup)
 * - Bootstrap Modal
 * - Bootstrap Offcanvas
 * or inline (it may add header and footer)
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */
import './HBaseWidget.js';

/* global bootstrap */

/**
 * @class HBaseView
 * @augments {HBaseWidget}
 * @memberof Widgets.UI
 * @description A container widget for displaying popups using jQuery Dialog, Bootstrap Modal, Bootstrap Offcanvas, or inline.
 * @param {object} options - Configuration options for the widget.
 */
$.widget( 'heurist.HBaseView', $.heurist.HBaseWidget, {

    /**
     * @memberof Widgets.UI.HBaseView
     * @type {object}
     * @property {string} viewMode - The view mode.
     * @property {boolean} showMargin - Whether to show the margin.
     * @property {string} default_palette_class - The default palette class.
     * @property {number} height - The height of the view.
     * @property {number} width - The width of the view.
     * @property {object} position - The position of the view.
     * @property {boolean} modal - Whether the view is modal.
     * @property {string} title - The title of the view.
     * @property {string} helpContent - The help content.
     * @property {boolean} isTitleVisible - Whether the title is visible.
     * @property {boolean} isHeaderVisible - Whether the header is visible.
     * @property {function} beforeClose - The function to call before closing.
     * @property {function} onClose - The function to call after closing.
     * @property {boolean} keepInstance - Whether to keep the instance.
     */
    options: {
        // View mode: Determines how the content is displayed
        viewMode: 'popup', // Options: 'offcanvas-*', 'modal-*', 'popup' (jQuery dialog), 'inline', 'full' (over main), 'container' (by dom id)
        showMargin: true,

        // Dialog-specific options
        default_palette_class: 'ui-heurist-admin',
        height: 400,
        width: 760,
        position: null,
        modal: true,
        title: '',
        helpContent: null,
        
        // Visibility toggles
        isTitleVisible: false, // Hide title
        isHeaderVisible: true,  // Show header as a top panel

        // Event listeners
        beforeClose: null, // Callback before closing (e.g., confirmation warning)
        onClose: null,     // Callback after closing

        keepInstance: false // If false, dialog instance is destroyed after closing
    },
    
    // Bootstrap & jQuery dialog instances
    bsModal: null,
    bsOffcanvas: null,
    jqDialog: null,

    // Flag to track close event context
    // Variable to be passed to options.onClose
    _contextOnClose:false, 
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Initialize the widget.
     */
    _init: function() {
        if (this.options.viewMode === 'inline') {
            this.options.isHeaderVisible = false;
        }

        // Handle Bootstrap modals and offcanvas
        if (this.options.viewMode === 'popup' || (this.options.viewMode === 'inline' && !this.options.isHeaderVisible)) {
            this._super();
            return;
        }
        
        const mode = this.options.viewMode.split('-')[0];
        this._container = this._$(`.${mode}-body`); //inline-body

        if (this._container.length === 0) {
            // Load modal/offcanvas layout or header/footer for inline
            let url = `${this.HAPI.baseURL}hclient/widgets/HBase/HBaseView.html div.${mode}`;
                                //+ '?t='+this.$H.random();
            this.loadHtmlContent(this.element, url, this._init);
            return;
        }
        // Call parent `_init`
        this._super();
    },
        
    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Destroy the widget and clean up modal/dialog instances.
     */
    _destroy: function() {
        if (this.bsModal) this.bsModal.dispose();
        if (this.bsOffcanvas) this.bsOffcanvas.dispose();
        if (this.jqDialog) this.jqDialog.dialog('close');

        // Call parent `_destroy`
        this._super();
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Initializes UI elements and event listeners.
     */
    _initControls: function() {
        if (this.options.viewMode.startsWith('modal')) {
            this._initModal();
        } else if (this.options.viewMode.startsWith('offcanvas')) {
            this._initOffcanvas();
        } else if (this.options.viewMode === 'inline') {
            this._initInnerHeader();
        } else {
            this._initDialog();
        }

        this._super();
    },
    
    /**
     * @memberof Widgets.UI.HBaseView
     * @description Returns the container element.
     * @returns {HTMLElement} The container element.
     */
    getContainer: function() {
        //for inline and jquery popup this is this.element
        return this._container[0];
    },

    /**
     * @memberof Widgets.UI.HBaseView
     * @description Opens the widget in the appropriate display mode.
     */
    show: function() {
        if (this.bsModal) {
            this.bsModal.show();
        } else if (this.bsOffcanvas) {
            this.bsOffcanvas.show();
        } else if (this.jqDialog) {
            this._popupDialog();
        }
    },

    /**
     * @memberof Widgets.UI.HBaseView
     * @description Closes the widget.
     * @param {boolean} isForce - Whether to force close without confirmation.
     */
    close: function(isForce) {
        if (this.jqDialog) {
            this._closeDialog(isForce);
        } else {
            
            /* TBD
            let canClose = true;
            if(window.hWin.HEURIST4.util.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                    this.options.onClose( this._contextOnClose );
                }
            }
            this.element.hide();
            */
                    
            if (this.bsModal) {
                this.bsModal.hide();
            } else if (this.bsOffcanvas) {
                this.bsOffcanvas.hide();
            }
        }
    },
    
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Initializes Bootstrap modal.
     */
    _initModal: function() {
        let modal = this._$('[data-heurist-role="container-modal"]')[0];

        // Ensure the modal has the correct class
        if (!modal.classList.contains(this.options.viewMode)) {
            modal.classList.add(this.options.viewMode);
        }

        this.bsModal = bootstrap.Modal.getOrCreateInstance(modal);

        // Add padding if required
        if (this.options.showMargin) {
            let viewDiv = modal.querySelector('.modal-body');
            if (!viewDiv.classList.contains('p-1')) {
                viewDiv.classList.add('p-1');
                viewDiv.style.overflowY = 'hidden';
                modal.querySelector('.modal-content').style.height = '100%';
            }
        }
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Initializes Bootstrap offcanvas.
     */
    _initOffcanvas: function() {
        let offcanvas = this._$('[data-heurist-role="container-offcanvas"]')[0];

        if (!offcanvas.classList.contains(this.options.viewMode)) {
            offcanvas.classList.add(this.options.viewMode);
        }

        // Set resizable handles based on placement
        let handles = 'w';
        if (this.options.viewMode.includes('-start')) handles = 'e';
        else if (this.options.viewMode.includes('-bottom')) handles = 'n';
        else if (this.options.viewMode.includes('-end')) handles = 's';

        /*temp disable   jquery ui-resizable sets position:relative
        $(offcanvas).resizable({
            minWidth: 400,
            handles: handles,
        });
        */

        this.bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvas);

        // Add padding if required
        if (this.options.showMargin) {
            let viewDiv = offcanvas.querySelector('.offcanvas-body');
            if (!viewDiv.classList.contains('p-1')) {
                viewDiv.classList.add('p-1');
                viewDiv.style.overflowY = 'hidden';
            }
        }
    },
        
 
    // ---------------- jQuery dialog

    
    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Initializes jQuery dialog.
     */
    _initDialog: function(){
        
        let options = this.options;
        let btnArray = this._getActionButtons();
        
        if (!options.beforeClose) {
            options.beforeClose = () => true; // Default close behavior
        }            

        // Ensure dialog dimensions fit within screen size
        let maxW = window.innerWidth;
        let maxH = window.innerHeight;
        if (options.width > maxW) options.width = maxW * 0.95;
        if (options.height > maxH) options.height = maxH * 0.95;            
        
        this.jqDialog = this.element.dialog({
            autoOpen: false ,
            height: options.height,
            width: options.width,
            modal: options.modal !== false,
            title: window.hWin.HR(options.title || ''),
            position: options.position || { my: "center", at: "center", of: window },
            beforeClose: options.beforeClose,
            close: () => {
                if(this.$H.isFunction(this.options.onClose)){
                    this.options.onClose(this._contextOnClose);
                }
                if (!this.options.keepInstance) {
                    this.jqDialog.remove();
                }
            },
            buttons: btnArray            
        }); 
        
        if(this.options.showMargin){
            this.element.css({'padding':0, 'overflow':'hidden'});
        }
        
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Opens the jQuery dialog.
     */
    _popupDialog: function(){

            let $dlg = this.jqDialog.dialog('open');
            
            if(this.jqDialog.attr('data-palette')){
                $dlg.parent().removeClass(this.jqDialog.attr('data-palette'));
            }
            if(this.options.default_palette_class){
                this.jqDialog.attr('data-palette', this.options.default_palette_class);
                $dlg.parent().addClass(this.options.default_palette_class);
                this.element.removeClass('ui-heurist-bg-light');
            }else{
                this.jqDialog.attr('data-palette', null);
                this.element.addClass('ui-heurist-bg-light');
            }

            if(this.options.helpContent){
                const helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this.jqDialog, null, helpURL, false);    
            }
            
            
            /* TBD
            if(this.options.supress_dialog_title) $dlg.parent().find('.ui-dialog-titlebar').hide();

            if(this.options.helpContent==null){
                this.options.helpContent = this.widgetName;
            }
            if(this.options.helpContent){
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this.jqDialog, null, helpURL, false);    
            }
            */
        
    },
    

    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Closes the jQuery dialog.
     * @param {boolean} isForce - Whether to force close without confirmation.
     */
    _closeDialog: function(isForce){
        if(this.jqDialog){
            if(isForce===true){
                this.jqDialog.dialog('option','beforeClose',null);
            }
            
            this.jqDialog.dialog('close');
        }
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Adds/hides an inner header for inline mode.
     */
    _initInnerHeader: function() {
        if (!this.options.isHeaderVisible) {
            this._$('.ui-dialog-titlebar').hide();
            this._$('.ui-dialog-buttonpane').hide();
        }
    },

    /**
     * @private
     * @memberof Widgets.UI.HBaseView
     * @description Returns action buttons for jQuery dialogs.
     * If function returns an empty array - buttons panel will be hidden
     * @returns {Array} The action buttons.
     */
    _getActionButtons: function() {
        return [{
            text: window.hWin.HR('Close'),
            class: 'btnCancel',
            css: { 'float': 'right', 'margin-left': '30px', 'margin-right': '20px' },
            click: () => this.close()
        }];
/*        return [
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
                 ];*/
    }    
    
});
