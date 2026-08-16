/**
 * imgFilter.js - Provides a UI dialog for defining CSS image filter configurations.
 *
 * @fileOverview Provides a UI dialog for defining CSS image filter configurations.
 *               The `imgFilter` function can render this UI either within a specified container
 *               or as a standalone popup dialog. It allows users to adjust various CSS filter
 *               properties (like brightness, contrast, saturation) and apply them.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * Initializes and displays an image filter selection dialog.
 * The dialog allows users to configure various CSS filter properties (e.g., brightness, contrast, blur, etc.).
 * It can be rendered within a provided jQuery container or as a new popup dialog.
 * The UI for the dialog is loaded from "hclient/widgets/editing/imgFilter.html".
 *
 * @function imgFilter
 * @param {Object|string} [current_cfg={}] - The current filter configuration object or a JSON string
 *                                           representing it. If a string, it will be parsed.
 *                                           Defaults to an empty object if not provided or invalid.
 *                                           Example: `{"blur": "5px", "brightness": "0.8"}`.
 * @param {function(Object): void} main_callback - A callback function that is executed when the user
 *                                                 applies the filters by clicking the "Apply" button.
 *                                                 It receives the new filter configuration object as its argument.
 *                                                 The configuration object contains filter names as keys and
 *                                                 their values (with units) as string values.
 * @param {jQuery} [$container=null] - Optional. A jQuery object representing the container where the
 *                                     dialog UI should be loaded. If null or not provided,
 *                                     the dialog will be shown as a popup managed by `window.hWin.HEURIST4.msg.showMsgDlgUrl`.
 */
function imgFilter( current_cfg, main_callback, $container=null ){

    /**
     * @private
     * @const {string}
     * @default 'imgFilter'
     * @description Internal class name identifier for the component.
     */
    const _className = 'imgFilter';
    /**
     * @private
     * @type {Object}
     * @description Stores the default values of the filter input fields (sliders).
     *              Populated by `_initControls` with initial values from the HTML.
     *              Used by `_resetValues` to revert changes and by `_getValues`
     *              to determine which filters have been modified.
     */
    let _default_values = {};
    /**
     * @private
     * @type {jQuery|null}
     * @description jQuery object representing the dialog. This could be the user-provided `$container`
     *              itself, or a dialog dynamically created by `window.hWin.HEURIST4.msg.showMsgDlgUrl`.
     */
    let $dlg;
    
    /**
     * Initializes the dialog functionality.
     * If `$container` is provided, it loads the filter UI HTML into it.
     * Otherwise, it creates a popup dialog using `window.hWin.HEURIST4.msg.showMsgDlgUrl`.
     * Both methods use `_initControls` as a callback after the HTML is loaded.
     * It also parses `current_cfg` from a JSON string to an object if necessary.
     * @private
     * @function _init
     * @returns {void}
     */
    function _init(){


        if($container && $container.length>0){
            //container provided
            $dlg = $container;

            $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/editing/imgFilter.html',
                _initControls // Initialize controls after HTML is loaded
            );

        }else{
            //open as popup
            let buttons= [
                {text:window.hWin.HR('Cancel'), 
                    class:'btnCancel',
                    css:{'float':'right','margin-left':'10px','margin-right':'20px'}, 
                    click: function() { 
                        $dlg.dialog( "close" );
                }},
                {text:window.hWin.HR('Reset'), 
                    class:'btnReset',
                    css:{'float':'right','margin-left':'10px'}, 
                    click: function() { 
                        _resetValues(); // Call reset function
                }},
                {text:window.hWin.HR('Apply'), 
                    class:'ui-button-action btnDoAction',
                    //disabled:'disabled',
                    css:{'float':'right'}, 
                    click: function() { 
                            let config = _getValues(); // Get current filter configuration
                            main_callback.call(this, config); // Execute main callback with config
                            $dlg.dialog( "close" );    
            }}];
    
            $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/editing/imgFilter.html?t="+(new Date().getTime()), // Append timestamp to prevent caching
                buttons, 'Define Filters', 
                {   
                    default_palette_class: 'ui-heurist-explore',
                    width: 300,
                    height: 490,
                    close: function(){ // Cleanup on dialog close
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: _initControls // Initialize controls when dialog opens and content is loaded
            });
        }
        // Parse current_cfg if it's a JSON string; otherwise, use as is or default to empty object if invalid.
        current_cfg = window.hWin.HEURIST4.util.isJSON(current_cfg);
    }
    
    /**
     * Initializes the filter controls (input sliders) within the dialog.
     * This function is called after the dialog's HTML content (`imgFilter.html`) is loaded.
     * It iterates over each input element found in the dialog:
     * - Stores its initial value in `_default_values` (keyed by input name).
     * - Attaches a 'change' event listener to update the text display (previous sibling span)
     *   showing the current value of the slider.
     * - If `current_cfg` (the initial configuration passed to `imgFilter`) contains a value
     *   for the input, it sets the input's value to this, parsing it as a float, and triggers 'change'.
     * @private
     * @function _initControls
     * @returns {void}
     */
    function _initControls(){
        _default_values = {}; // Reset default values map

        $.each($dlg.find('input'), function(idx, item){
            item = $(item);
            const name = item.attr('name');
            _default_values[name] = item.val(); // Store initial value as default

            if(name === 'transparentColor'){
                const value = current_cfg && !window.hWin.HEURIST4.util.isempty(current_cfg[name])
                    ? current_cfg[name]
                    : '';

                item.colorpicker({
                    hideButton: false, // show button right to input
                    showOn: 'both',
                    val: value
                }).css('max-width', '130px');

                item.parent('.evo-cp-wrap').css({display:'inline-block', width:'180px'});
                return;
            }
            
            // Update display span when slider value changes
            item.on({change:function(e){
                $(e.target).prev().text( $(e.target).val() );
            }});

            // Apply current configuration if provided
            if(current_cfg && !window.hWin.HEURIST4.util.isempty(current_cfg[name])){
                let val = parseFloat(current_cfg[name]); // Ensure numeric value
                item.val( val ).trigger('change'); // Set value and trigger change to update display
            }
        });
    }
    
    /**
     * Resets all filter input fields in the dialog to their initial default values.
     * The default values are those stored in `_default_values` during `_initControls`.
     * @private
     * @function _resetValues
     * @returns {void}
     */
    function _resetValues(){
        $.each($dlg.find('input'), function(idx, item){
            $(item).val(_default_values[$(item).attr('name')]).trigger('change'); // Reset and trigger change for UI update
        });
    }
   
    /**
     * Collects the current values from all filter input fields (sliders) in the dialog.
     * It constructs a configuration object containing only the filters whose values
     * differ from their stored default values (from `_default_values`).
     * The values in the returned object include appropriate CSS units/suffixes
     * as defined by the 'data-suffix' attribute on the input elements.
     * @private
     * @function _getValues
     * @returns {Object} A configuration object where keys are filter names (e.g., "blur", "brightness")
     *                   and values are the filter values as strings (e.g., "5px", "0.8").
     *                   An empty object is returned if no filters are changed from defaults.
     */
    function _getValues(){
        
        let filter_cfg = {};
        $.each($dlg.find('input'), function(idx, item){
            item = $(item);
            
            const name = item.attr('name');
            let val = item.val();

            if(name === 'transparentColor'){
                val = String(val || '').trim();
                if(val !== ''){
                    filter_cfg[name] = val;
                }
                return;
            }

            // Only include if value is different from default
            if(val!=_default_values[name]){
                let suffix = item.attr('data-suffix'); // e.g., 'px', '%', 'deg'
                if(!suffix) suffix = ''; // Default to empty suffix
                
                filter_cfg[name] = val+suffix; // e.g., { "blur": "2px" }
                // filter = filter + item.attr('name')+'('+val+suffix+') '; // Example: "blur(2px) brightness(0.8) "
            }
        });
        return filter_cfg;
    }//_getValues


    /**
     * @public
     * @alias imgFilter.that
     * @description The public interface object returned by `imgFilter` when it's invoked.
     *              It provides a way to identify the component.
     */
    let that = {

    }

    _init(); // Initialize the component
    
    return that; // Return the public interface
}



