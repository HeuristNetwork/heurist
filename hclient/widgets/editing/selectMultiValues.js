/**
 * selectMultiValues.js - Widget to select values from a multiselection list or tree.
 *
 * @fileOverview Defines the `heurist.selectMultiValues` jQuery UI widget.
 *               This widget serves as a base for creating UI components that allow users to select
 *               multiple values from a hierarchical tree structure, typically rendered using the
 *               Fancytree plugin. It provides core functionality for dialog display, tree initialization,
 *               and handling selections. It is intended to be extended by more specialized widgets
 *               like `heurist.selectFolders`.
 *
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\editing
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * @widget heurist.selectMultiValues
 * @alias selectMultiValues
 * @description A base jQuery UI widget for selecting multiple hierarchical values using a tree view,
 * typically implemented with Fancytree. It handles dialog creation, tree population,
 * and returning selected values. This widget is often extended for specific use cases
 * (e.g., {@link heurist.selectFolders}).
 *
 * The data for the tree (`options.allValues`) should be in a format compatible with Fancytree.
 * Selected values are managed as an array of strings, typically node keys or paths.
 */
$.widget( "heurist.selectMultiValues", {

    /**
     * @options
     * @description Default options for the selectMultiValues widget.
     */
    options: {
        /**
         * If true, the widget will be displayed as a modal dialog.
         * If false, it will be embedded within the element it's initialized on.
         * @option {boolean}
         * @default true
         */
        isdialog: true, //show in dialog or embedded
        
        /**
         * The title for the dialog window if `isdialog` is true.
         * Text is processed by `window.hWin.HR()` for internationalization.
         * @option {string}
         * @default 'Select Values'
         */
        title: 'Select Values',
        
        /**
         * Message displayed by `_initList` if `options.allValues` is empty or not provided,
         * and no data is fetched to populate it.
         * Text is processed by `window.hWin.HR()` for internationalization.
         * @option {string}
         * @default 'No values found'
         */
        emptyMessage: 'No values found',

        /**
         * If true, allows multiple items to be selected in the tree.
         * This influences the Fancytree `selectMode` option (typically 3 for multi-hier).
         * If false, selection might be restricted (though Fancytree `selectMode:1` would be needed).
         * REMARK: The Fancytree `selectMode` is hardcoded to `3` in `_initTreeView`, implying multiselect is always hierarchical.
         * This option's direct effect on Fancytree configuration might need review if single selection is desired.
         * @option {boolean}
         * @default true
         */
        multiselect: true, 
        
        /**
         * Callback function executed when the user confirms their selection (e.g., by clicking "Select" in dialog mode).
         * The function is called with the widget instance as `this` and one argument:
         * an array of strings representing the selected values (typically node keys or paths from Fancytree).
         * @option {function(string[]):void|null}
         * @default null
         */
        onselect: null,
        
        /**
         * An array of data objects used to populate the tree view (Fancytree).
         * Each object should conform to Fancytree's node data format
         * (e.g., `{title: "Node Title", key: "node_key", folder: true, children: [...]}`).
         * If this array is empty or not provided, inheriting widgets might attempt to fetch data,
         * or `_initList` will show `options.emptyMessage`.
         * @option {Array<Object>}
         * @default []
         */
        allValues: [],
        
        /**
         * An array of strings representing the initially selected values (node keys or paths) in the tree.
         * Can also be provided as a semicolon-separated string, which will be parsed into an array in `_initTreeView`.
         * These values are used in `_initTreeView` to pre-select nodes in the Fancytree.
         * @option {Array<string>|string}
         * @default []
         */
        selectedValues: [] //array or semicolon separated list
        
    },
    
    /**
     * Reference to the jQuery UI dialog instance if `options.isdialog` is true.
     * Initialized by `_showAsDialog`.
     * @private
     * @type {jQuery|null}
     */
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    
    /**
     * Initializes the selectMultiValues widget.
     * This is the constructor for the widget, part of the jQuery UI widget lifecycle.
     * It clears the widget's element, adds a base CSS class (`ui-heurist-bg-light`),
     * and appends a basic DOM structure consisting of a wrapper (`.ent_wrapper`),
     * a header (`.ent_header` which is hidden by default), and a content area (`.ent_content_full.recordList`).
     * A loading animation is temporarily shown in the content area.
     * Finally, it calls `_initList()` to populate or prepare the list/tree of values.
     * @private
     */
    _init: function() {

        let that = this; // For use in closures, though not used in this specific _init
        
        this.element.empty(); // Clear the target element
        
        this.element.addClass('ui-heurist-bg-light'); // Add base styling class

        // Append main DOM structure
        $('<div class="ent_wrapper">'
                +'<div class="ent_header"></div>' // Header for potential controls (e.g., added by extending widgets)
                +'<div class="ent_content_full recordList"></div>' // Container for the tree
                +'</div>').appendTo( this.element );
                
        // Hide header by default; extending widgets can show it if they add content
        let ent_header = this.element.find('.ent_header').hide();        
        
        this.recordList = this.element.find('.recordList'); // Store reference to tree container

        // Display a loading animation until tree is initialized
        $( "<div>" )
        .css({ 'width': '50%', 'height': '50%', 'top': '25%', 'margin': '0 auto', 'position': 'relative',
            'z-index':'99999999', 'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo( this.recordList );
        
        
        this._initList(); // Proceed to initialize the list/tree content
      
    }, //end _init
    
    /**
     * Initializes the list or tree of selectable values.
     * If `options.allValues` is an array and has content, it calls `_showAsDialog()`
     * (which will create a dialog if `options.isdialog` is true) and then `_initTreeView()`
     * to render the tree with `options.allValues`.
     * If `options.allValues` is empty or not an array, it displays the message
     * specified in `options.emptyMessage` using `window.hWin.HEURIST4.msg.showMsgFlash`.
     * This method can be overridden by extending widgets to provide custom data loading logic
     * (e.g., fetching data via AJAX as seen in `selectFolders`).
     * @private
     */
    _initList: function(){
        if(Array.isArray(this.options.allValues) && this.options.allValues.length>0){
            this._showAsDialog(); // Ensure dialog is visible if applicable
            this._initTreeView( this.options.allValues ); // Initialize tree with provided data
        }else{
            // If no data, show the empty message
            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR(this.options.emptyMessage));
        }
    },

    /**
     * Refreshes the widget.
     * Standard jQuery UI widget lifecycle method. Currently, this method is implemented as an empty function
     * in this base widget. Extending widgets may override it to implement specific refresh logic.
     * @private
     */
    _refresh: function(){
        // REMARK: This method is empty in the source code.
    },

    /**
     * Cleans up the widget instance upon removal.
     * It removes the `.recordList` element which typically contains the Fancytree.
     * Dialog cleanup (`_as_dialog.remove()`) is handled by the `close` callback of the dialog itself,
     * which is set up in `_showAsDialog`.
     * This is part of the standard jQuery UI widget lifecycle.
     * @private
     */
    _destroy: function() {
        // remove generated elements like the recordList which holds the tree
        this.recordList.remove();
        // Note: _as_dialog is removed in its own 'close' handler if it exists.
    },
    
    /**
     * If `options.isdialog` is true, this method wraps `this.element` (the widget's main DOM element)
     * in a jQuery UI dialog.
     * It configures the dialog with a title (from `options.title`), modal behavior,
     * dimensions, and "Select" and "Cancel" buttons.
     *
     * The "Select" button handler:
     *  - Retrieves selected nodes from the Fancytree instance (expected to be `this._treeview`).
     *    It uses `getSelectedNodes(true)` which gets top-level selected nodes in multi-hier mode.
     *  - Maps these nodes to an array of their key paths (adjusting for leading slashes).
     *  - Calls the `options.onselect` callback with this array of selected key paths.
     *  - Closes the dialog.
     *
     * The "Cancel" button simply closes the dialog.
     * The dialog instance is stored in `this._as_dialog`.
     * The dialog's `close` callback ensures the dialog element is removed from the DOM.
     * @private
     */
    _showAsDialog: function(){
        
        if(this.options.isdialog){ // Proceed only if isdialog option is true
            
            let that = this; // For use in closures
            
            let buttons = {};
            buttons[window.hWin.HR('Select')]  = function() { // "Select" button
                
                let wtrr = $.ui.fancytree.getTree(that._treeview); // Get Fancytree instance
                // Get a list of all selected TOP nodes (in multi-hier mode, true means top selected nodes in a hierarchy)
                let snodes = wtrr.getSelectedNodes(true);
                // Convert to an array of key paths
                let res = [];
                $.map(snodes, function(node){
                    let currname = node.getKeyPath();
                    if(currname[0]=='/') currname = currname.substring(1); // Remove leading slash if present
                    res.push(currname);
                });
                
                if(window.hWin.HEURIST4.util.isFunction(that.options.onselect)){
                    that.options.onselect.call(that, res); // Call onselect callback with results
                }
                that._as_dialog.dialog('close'); // Close dialog
            }; 
            buttons[window.hWin.HR('Cancel')]  = function() { // "Cancel" button
                that._as_dialog.dialog('close'); // Close dialog
            }; 

            // Create jQuery UI Dialog
            let $dlg = this.element.dialog({
                autoOpen: true, // Open immediately
                height: 640,
                width: 480,
                modal: true,
                title: window.hWin.HR(this.options.title),
                resizeStop: function( event, ui ) { // Adjust layout on resize
                    let pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', width:pele.width()-24 });
                },
                close:function(){ // When dialog closes
                    that._as_dialog.remove(); // Remove the dialog element from DOM
                },
                buttons: buttons // Assign buttons
            });
            
            this._as_dialog = $dlg; // Store reference to dialog instance
        }        
    },
    
    /**
     * Initializes the Fancytree instance within the widget's `.recordList` element using the provided `treeData`.
     * It first clears any existing content in `.recordList`.
     *
     * Key Fancytree options configured:
     *  - `checkbox: true`: Enables checkboxes for selection.
     *  - `focusOnSelect: true`: Sets focus on selection.
     *  - `source: treeData`: The data to populate the tree.
     *  - `selectMode: 3`: Enables multi-hierarchical selection (selecting a parent selects children, etc.).
     *  - `renderNode`: A custom function to modify node display. It makes the title font normal,
     *    appends `files_count` to the title if available, and adds a 'graytext' class to system folders.
     *  - `extensions: ['edit']`: Enables the Fancytree 'edit' extension.
     *  - `edit.beforeEdit`: Prevents editing of system folders.
     *  - `edit.save`: Handles saving edits (renaming/creating nodes) by making an HAPI call to
     *    `SystemMgr.get_sysfolders`. Updates the node title and other properties on success.
     *  - `edit.close`: Marks node as pending if save initiated an async request.
     *
     * After tree initialization, it processes `options.selectedValues`:
     *  - If `options.selectedValues` is a semicolon-separated string, it's split into an array.
     *  - It then iterates through these values and pre-selects the corresponding nodes in the tree
     *    by comparing their key paths (after removing any leading slash).
     *
     * The Fancytree instance is stored in `this._treeview`.
     * @private
     * @param {Array<Object>} treeData - An array of node objects for Fancytree, conforming to Fancytree's expected data structure.
     */
    _initTreeView: function( treeData ){
      
        let that = this;
        
        let fancytree_options =
        {
            checkbox: true, // Enable checkboxes
            //titlesTabbable: false,     // Add all node titles to TAB chain (commented out)
            focusOnSelect:true, // Focus node on selection
            source: treeData, // Data for the tree
            //quicksearch: true, // Commented out
            //icon: true, // Commented out
            selectMode: 3, // 1:single, 2:multi, 3:multi-hier (multi-hierarchical selection)
            renderNode: function(event, data) { // Custom node rendering
                    let node = data.node;
                    let $span = $(node.span).find("> span.fancytree-title").css({'font-weight':'normal'});
                    if(node.data.files_count>0) // Append files_count if present
                        $span.html(node.title+' <span style="font-weight:normal">('+node.data.files_count+')</span>');
                    if(node.data.issystem){ // Style system folders
                        $span.addClass('graytext');
                    }
            },            
            extensions: ['edit'], //'filter'], // Enable 'edit' extension (filter is commented out)
            //filter: { highlight:false, mode: "hide" }, // Filter options (commented out)
            edit: { // Configuration for 'edit' extension
            triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"], // How to start editing
            beforeEdit: function(event, data){ // Before editing starts
                // Return false to prevent edit mode for system folders
                return !data.node.data.issystem;
            }, 
            /*edit: function(event, data){ // Commented out: while editor is open
                // Editor was opened (available as data.input)
                data.input.val = data.node.key;
            },*/
            save:function(event, data){ // When saving an edit (e.g., after rename or create)
                
                let path = data.node.getParent().getKeyPath(); // Get parent path
                path = (path=='/')?'':(path+'/'); // Format path
                let newname = data.input.val(); // New name from input
                let newpath =  path+newname;
                
                let request;
                if(window.hWin.HEURIST4.util.isempty(data.node.origTitle)){ // If origTitle is empty, it's a new node
                    request = {operation:'create', name:newpath};
                }else{ // Existing node being renamed
                    let currname = path+data.node.origTitle;
                    request = {operation:'rename', name:currname, newname:newpath};
                }
                
                // HAPI call to save changes
                window.hWin.HAPI4.SystemMgr.get_sysfolders(request, 
                    function(response){
                        $(data.node.span).removeClass("pending"); // Remove pending state
                        if(response.status == window.hWin.ResponseStatus.OK){
                            data.node.setTitle(newname); // Update Fancytree node title
                            data.node.origTitle = newname; // Update original title tracker
                            data.node.key = newname; // REMARK: Key is updated to new name. If keys should be stable IDs, this might be an issue.
                            data.node.folder = true; // Ensure it's marked as a folder
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response); // Show error on failure
                        }
                    });
                
                return true; // Indicate that save is being handled (possibly async)
            },
            close: function(event, data){ // After editor closes
                if( data.save ) { // If save was attempted
                  // Since an async request was started, mark the node as "pending"
                  $(data.node.span).addClass("pending");
                }
            }        
            },
            select: function(event, data) { // On node selection/deselection (unused here)
            },
            activate: function(event, data) { // On node activation (e.g., click title, unused here)
            }
        };

        this.recordList.empty(); // Clear loading animation or previous tree
        
        this._treeview = this.recordList.fancytree(fancytree_options); // Initialize Fancytree
        
        // Pre-select nodes based on options.selectedValues
        if(window.hWin.HEURIST4.util.isempty(this.options.selectedValues)){
            this.options.selectedValues = [];
        }else if(!Array.isArray(this.options.selectedValues)){
            // If selectedValues is a string, split by semicolon
            this.options.selectedValues = this.options.selectedValues.split(';');
        }
        
        if(this.options.selectedValues.length>0){
            let wtrr = $.ui.fancytree.getTree(that._treeview); // Get tree instance
            
            wtrr.visit(function(node){ // Iterate over all nodes
                    if(!node.data.issystem){ // Don't select system folders based on this list
                        
                        /*var path = node.getParent().getKeyPath(); // Commented out old path logic
                        path = (path=='/')?'':(path+'/');
                        var currname = path+node.title;*/
                        let currname = node.getKeyPath(); // Get full path key
                        if(currname[0]=='/') currname = currname.substring(1); // Remove leading slash
                        
                        if(that.options.selectedValues.indexOf(currname)>=0){ // If path is in selectedValues
                            node.setSelected(true); // Select the node
                        }
                    }
            });
        }
    },
});
