/**
* @file manageServer.js
* @brief Provides a list of server management actions.
* @fileOverview This widget displays a list of links that, when clicked, submit a form
* to perform various server-side administrative tasks.
*
* @todo Use HBaseView as a parent.
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

/**
* @class manageServer
* @augments baseAction
* @memberof Widgets.Admin
* @description Provides a list of server management actions.
*
* @property {object} options - Configuration options for the widget.
*/
$.widget( "heurist.manageServer", $.heurist.baseAction, {


    /**
     * @memberof Widgets.Admin.manageServer
     * @type {object} Extends {@link baseAction.options}.
     * @property {number} [height=620] - The height of the widget (dialog).
     * @property {number} [width=400] - The width of the widget (dialog).
     * @property {string} [title='Server manager'] - The title displayed for the widget dialog.
     * @property {string} [default_palette_class='ui-heurist-admin'] - Default CSS class for the widget's palette.
     * @property {string} [actionName='manageServer'] - The name of the action, used for baseAction.
     * @property {string} [entered_password] - An optional password that can be pre-filled into the form. (Implicit option from usage in _initControls)
     */
    options: {
        height: 710,
        width:  475,
        title:  'Server manager',
        default_palette_class: 'ui-heurist-admin',
        actionName: 'manageServer'
    },
    
    /**
     * @function _initControls
     * @description Initializes the controls within the widget.
     * This method is invoked from `_init` (inherited from `baseAction`) after the HTML content is loaded.
     * It sets styles for list items, resolves hrefs for action links, and sets up click handlers
     * to submit a hidden form (`#mainForm`) to the respective action URL.
     * The form includes the current database name and an optional password.
     * @memberof Widgets.Admin.manageServer
     * @private
     * @override
     * @returns {boolean} Returns the result of the parent widget's `_initControls` method.
     */
    _initControls: function(){
        
            this._$('li').css({padding:'10px 0px'}); // Style list items
            
            // Process each action link
            $.each(this._$('a'), function(i,item){
                
                let href = $(item).attr('href');

                if(!href || href=='#'){
                    return;
                }
                
                // Ensure href is absolute, prepending baseURL if it's relative
                if(!(href.indexOf('http://')==0 || href.indexOf('https://')==0)){
                    href = window.hWin.HAPI4.baseURL + href;
                }
                $(item).attr('href', href);
            });

            // Attach click event handler to action links
            this._on(this._$('a'),{click:function(event){
                    let surl = $(event.target).attr('href'); // Get the URL from the clicked link
                    
                    if(!surl || surl=='#'){
                        return false;
                    }

                    this.submitForm(surl);
                    window.hWin.HEURIST4.util.stopEvent(event); // Prevent default link behavior and stop event propagation
                    return false; // Prevent default link behavior
                }});
                
            this._on(this._$('#fieldUsage'),{click:(event)=>{
                let that = this;
                window.hWin.HEURIST4.msg.showPrompt(
                    window.hWin.HR('Define concept code for field') + ':',
                    (value)=>{
                        if (!window.hWin.HEURIST4.util.isempty(value)) {
                            const re = /^([1-9][0-9]*)-([1-9][0-9]*)$/;
                            const matches = value.match(re);
                            if (!matches) {
                                window.hWin.HEURIST4.msg.showMsgFlash('Invalid concept code', 1000);
                            }else{
                                const surl = window.hWin.HAPI4.baseURL+'admin/verification/verifyFieldUsage.php'
                                that.submitForm(surl, value);
                                window.hWin.HEURIST4.util.stopEvent(event); // Prevent default link behavior and stop event propagation
                            }
                            return false; // Prevent default link behavior
                        }
                    },
                    { title: window.hWin.HR('Concept code'), yes: window.hWin.HR('Search'), no: window.hWin.HR('Cancel') }
                    //,{ default_palette_class: 'ui-heurist-publish' }
                );  
            }});              
        
        return this._super(); // Call parent's _initControls
    },
    
    submitForm: function(surl, code){

        let subform = this._$('#mainForm'); // Find the hidden form
        // If a password was entered/provided, set it in the form
        if(this.options.entered_password){
            subform.find('input[name="pwd"]').val(this.options.entered_password);   
        }
        // Set the current database name in the form
        subform.find('input[name="db"]').val(window.hWin.HAPI4.database);
        
        if(code){ //concept code
            subform.find('input[name="code"]').val(code);
        }
        
        // Set the form's action URL to the link's href
        subform.attr('action',surl);
        
        // Submit the form
        subform.trigger('submit');
    },

    /**
     * @function _getActionButtons
     * @description Retrieves the action buttons for the widget's dialog.
     * This method overrides the parent's `_getActionButtons` to customize the dialog buttons.
     * It changes the text of the first button to "Close" and removes any subsequent buttons.
     * @memberof Widgets.Admin.manageServer
     * @private
     * @override
     * @returns {Array<object>} An array containing a single button definition object for the "Close" button.
     *                          Each object has `text` and `click` (from parent) properties.
     */
    _getActionButtons: function(){
        let res = this._super(); // Get buttons from parent
        if (res && res.length > 0) { // Ensure parent returned buttons
            res[0].text = 'Close'; // Change the first button's text
            if (res.length > 1) {
                res.splice(1,1); // Remove the second button (typically "Do Action")
            }
        }
        return res;
    }
        
});
