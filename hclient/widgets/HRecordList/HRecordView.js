/**
* HRecordView - widget to render info for a particular Heurist record
* 
* Content can be:
* - The built-in renderer (renderRecordData.php)
* - A built-in smarty template
* - A publisher’s smarty template.  
* 
* Programmatically render function can be defined as options.customRecordRender or overwrite the method renderContent if you use HRecordView as a template for a new widget.
* 
* As a descendant of HBaseView it can be presented in 
* - a given container (inline)
* - float or modal popup (jQuery Dialog or Bootstrap Modal)
* - offcanvas(side slide panel).  (Bootstrap Offcanvas)
* 
* This widget is integrated with HRecordList and its properties can be defined 
* along with the HRecordList property editor. As a standalone widget 
* the ID of the record (to be rendered) can be obtained via the search group 
* ON_SELECT event or defined as a widget property.
* 
* 
* @project     Heurist academic knowledge management system
* @package Widgets
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import '../HBase/HBaseView.js';

$.widget( 'heurist.HRecordView', $.heurist.HBaseView, {

    // Default options
    options: {

        // Defines where to display the record view:
        // Possible values: 'inline', 'offcanvas-*', 'modal-*', 'popup' (jQuery dialog)
        viewMode: 'popup',

        // Record type and ID
        entityType: 'rec', // Default entity type: 'rec'
        recID: 0,  // Record ID to display

        // Optional template for rendering
        templateView: null, // Uses the entity default Smarty report if not defined

        // Custom function to render the record
        customRecordRender: null        
    },
    
    // Iframe for embedded content (if applicable)
    iframe: null,


    /**
     * Cleanup function. Removes generated elements and event listeners.
     */
    _destroy: function() {
        // remove generated elements
        if(this.iframe) {
            this.iframe.remove();
        }
        this._super();
    },
    
    /**
     * Initializes controls and triggers rendering.
     */    
    _initControls:function(){
        this._super();
        this.show();  
    },
    
    /**
     * Clears the content inside the container.
     */
    clearContent: function(){
        
        if (!this._initCompleted) return;

        if (this.iframe) {
            this.iframe.src = '';   
        }

        let viewDiv = this.getContainer();
        $(viewDiv).empty();        
    },

    /**
     * Displays the record viewer.
     * @param {number} recID - The record ID to display (optional).
     */
    show: function(recID) {
        this._super();

        // If a new record ID is provided, update and render content
        if (recID !== this.options.recID) {
            if (recID > 0) this.options.recID = recID;
            this.renderContent();
        }
    },
    
    /**
     * Renders the record content inside the container.
     */
    renderContent: function() {
        const selectedRecID = this.options.recID;

        // Validate the record ID
        if (!this.$H.isPositiveInt(selectedRecID)) {
            this.clearContent();
            return;
        }

        let viewDiv = this.getContainer();
        let request;

        // If a template is specified, use it to render the content
        if (this.options.templateView) {
            request = {
                q: `ids:${selectedRecID}`, 
                db: this.HAPI.database, 
                template: this.options.templateView,
                lang: this.HAPI.getLocale()
            };

            $(viewDiv).load(this.HAPI.baseURL, request, function() { 
                // TBD: Adjust URLs and activate action links if necessary
                // console.log('>>', viewDiv.innerHTML);
            });

        } else {
            // Load content in an iframe if no template is specified
            let frame = viewDiv.querySelector('iframe');

            if (!frame) {
                // Create and append an iframe
                frame = document.createElement('iframe');
                frame.style.width = '100%';
                frame.style.height = '100%';
                viewDiv.append(frame);

                let viewParent = $(viewDiv).parents('.recordList-fullview');

                // Adjust iframe height dynamically if inside a full view container
                if (viewParent.length > 0) {
                    frame.addEventListener('load', () => { 
                        const height = frame.contentWindow.document.body.scrollHeight;
                        viewDiv.style.height = `${height}px`;
                        viewParent.height(`${height}px`);
                    });
                }
            }

            // Set the iframe source to load the record
            frame.src = `${this.HAPI.baseURL}?recID=${selectedRecID}&db=${this.HAPI.database}&format=html`;
            this.iframe = frame;
        }
    }        

});
