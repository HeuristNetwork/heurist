/**
 * selectFolders.js - Widget to select folders from a tree.
 *
 * @fileOverview Defines the `heurist.selectFolders` jQuery UI widget.
 *               This widget provides a UI for selecting one or more folders from a hierarchical tree structure.
 *               It extends the `$.heurist.selectMultiValues` widget, inheriting its core functionality
 *               for managing selections and dialog behavior, and specializes it for folder selection
 *               using the `fancytree` plugin for tree display. It also adds folder management features
 *               like creating and deleting folders if `options.allowEdit` is true.
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
 * @class selectFolders
 * @memberof Widgets.Editing
 * @augments selectMultiValues
 * @description A jQuery UI widget for selecting one or more folders from a hierarchical tree,
 * typically displayed using the Fancytree plugin. It extends {@link selectMultiValues}
 * to provide folder-specific interactions, including optional folder creation and deletion.
 *
 * The folder data is expected to be an array of objects suitable for Fancytree,
 * or it can be fetched from the server via `HAPI4.SystemMgr.get_sysfolders`.
 * Each folder node in Fancytree is expected to have a `data` object which may contain
 * `issystem` (boolean) and `files_count` (number).
 *
 * @example
 * // Initialize selectFolders to select from a predefined list of folders
 * var myFolders = [
 *     { title: "Research Documents", key: "1", folder: true, children: [
 *         { title: "Literature Reviews", key: "2" },
 *         { title: "Data Sets", key: "3" }
 *     ]},
 *     { title: "Project Proposals", key: "4" }
 * ];
 * $('<div></div>').selectFolders({
 *     allValues: myFolders, // Data for Fancytree
 *     currentValues: ["2"], // Pre-select "Literature Reviews" by key
 *     isDialog: true,       // Display as a dialog
 *     onOk: function(selectedFolders) {
 *         console.log("Selected folder keys:", selectedFolders);
 *         // selectedFolders will be an array of keys, e.g., ["2"]
 *     }
 * });
 */
$.widget( "heurist.selectFolders", $.heurist.selectMultiValues, {

    /**
     * @memberof Widgets.Editing.selectFolders
     * @type {object}
     * @instance
     * @description Options for the selectFolders widget. Inherits options from
     * {@link selectMultiValues#options}, such as `currentValues`, `allValues` (used for Fancytree data),
     * `isDialog`, `onOk`, `onCancel`, `root_dir` (can be used in `_initList` when fetching folders).
     * @property {string} [title='Select Folders'] - The title for the dialog window
     * @property {string} [emptyMessage='No folders found'] - Message displayed when no folders are found or returned from the server.
     * @property {boolean} [allowEdit='true'] - If true, displays UI buttons for creating new folders/subfolders and deleting folders.
     * These operations interact with the Fancytree instance (expected to be part of the parent widget's `_treeview`)
     * and make HAPI calls to `HAPI4.SystemMgr.get_sysfolders` with appropriate 'operation' parameters.
     */
    options: {
        title: 'Select Folders',
        emptyMessage: 'No folders found',
        allowEdit: true
    },
    
    /**
     * Internal flag related to showing system folders in the Fancytree.
     * are currently commented out in the `_init` method of the source code.
     * 
     * If it were active, it would be used to filter branches in the Fancytree to show or hide system folders.
     * @private
     * @type {boolean}
     * @default false
     */
    _show_system_folders:false,

    /**
     * Initializes the selectFolders widget.
     * This method is part of the jQuery UI widget lifecycle. It calls the parent widget's `_init`
     * method using `this._super()` to inherit base initialization logic.
     *
     * It then enhances the widget by appending folder management buttons ("New folder", "New subfolder", "Delete")
     * to the widget's header element (`.ent_header`, assumed to be created by the parent widget).
     * Event handlers for these buttons interact with the Fancytree instance
     * (expected to be available as `this._treeview` from the parent widget `selectMultiValues`)
     * to perform actions such as creating new nodes (folders/subfolders) or removing existing ones.
     * These actions include checks (e.g., preventing deletion of system folders or non-empty folders)
     * and may involve HAPI calls to `SystemMgr.get_sysfolders` for persistence on the server-side
     * (e.g., for deleting a folder).
     *
     * The visibility of these folder management buttons is controlled by `this.options.allowEdit`.
     * If `allowEdit` is true, the header containing these buttons is shown.
     *
     * REMARK: A section of code for a "Show system folders" checkbox and its associated filtering logic
     * is commented out.
     * @private
     * @override
     */
    _init: function() {

        this._super(); // Call parent widget's _init
        const that = this;

        let ent_header = this.element.find('.ent_header'); // Assumes parent creates this element

        // Button to create a new top-level folder
        $('<div>').button({label:window.hWin.HR('New folder')}).on('click',
            function() {
                let node = that._treeview.fancytree('getRootNode'); // Get root of the Fancytree
                node.editCreateNode("child", "new folder"); // Trigger Fancytree's node creation UI
            }        
        ).appendTo(ent_header);

        // Button to create a new subfolder under the currently active (selected) folder
        $('<div>').button({label:window.hWin.HR('New subfolder')}).on('click',
            function() {
                let node = that._treeview.fancytree("getActiveNode"); // Get current active node
                if( !node ) {
                    window.hWin.HEURIST4.msg.showMsgFlash('Select parent folder');
                    return;
                }
                if(node.data.issystem){ // Prevent modification of system folders
                    window.hWin.HEURIST4.msg.showMsgFlash('System folder cannot be modified');
                    return;
                }
                node.editCreateNode("child", "new folder"); // Trigger Fancytree's node creation UI
            }        
        ).appendTo(ent_header);

        // Button to delete the currently active (selected) folder
        $('<div>').button({label:window.hWin.HR('Delete')}).on('click',
            function() {
                let node = that._treeview.fancytree("getActiveNode");
                if(node){
                    if(node.data.issystem){ // Prevent deletion of system folders
                        window.hWin.HEURIST4.msg.showMsgFlash('System folder cannot be deleted');
                    }else if(node.countChildren()>0 || node.data.files_count>0){ // Prevent deletion of non-empty folders
                        window.hWin.HEURIST4.msg.showMsgFlash('Cannot delete non-empty folder');
                    }else{
                        // Construct folder path for HAPI call
                        let path = node.getParent().getKeyPath();
                        path = (path=='/')?'':(path+'/'); // Adjust path format
                        let currname = path+node.title;

                        // HAPI call to delete folder on server
                        window.hWin.HAPI4.SystemMgr.get_sysfolders({operation:'delete', name:currname}, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    node.remove(); // Remove node from Fancytree on successful deletion
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response); // Show error on failure
                                }
                        });
                    }
                }
            }
        ).appendTo(ent_header);

        /* 
        // REMARK: This section for "Show system folders" is commented out
        $('<label><input type="checkbox">Show system folders</label>').css({'margin-left':'20px'}).appendTo(ent_header);
        ent_header.find('input').on('click',
        function(event){
        that._show_system_folders = $(event.target).is(':checked');

        var wtrr = $.ui.fancytree.getTree(that._treeview);
        wtrr.filterBranches(function(node){
        return that._show_system_folders || !node.data.issystem;
        }, {mode: "hide"});
        //that._treeview.fancytree('render')  
        });
        */

        if(this.options.allowEdit){ // Show header with editing buttons if allowed
            ent_header.show();
        }

    }, //end _init
    
    /**
     * Initializes the list of folders to be displayed in the Fancytree.
     * This method overrides the corresponding method in the parent widget `$.heurist.selectMultiValues`.
     *
     * If `this.options.allValues` (expected to be an array of folder data for Fancytree)
     * is already populated and is an array with items, this method calls `_showAsDialog()`
     * (inherited or from parent, to ensure dialog visibility if `isDialog` is true) and then
     * `_initTreeView()` (inherited or from parent, to initialize Fancytree with `this.options.allValues`).
     *
     * If `this.options.allValues` is not populated, this method fetches folder data from the server
     * using `window.hWin.HAPI4.SystemMgr.get_sysfolders`. The `this.options.root_dir` can be passed
     * as an option to this HAPI call to specify a root directory for fetching folders.
     *
     * On successful fetch from the server:
     *  - It populates `this.options.allValues` with the received folder data.
     *  - If data is received, it recursively calls itself (`this._initList()`) to now proceed with
     *    the Fancytree initialization using the newly fetched data.
     *  - If no data is returned from the server, it displays a message using
     *    `window.hWin.HEURIST4.msg.showMsgFlash` with the text from `this.options.emptyMessage`.
     * On a failed HAPI request, it shows an error message using `window.hWin.HEURIST4.msg.showMsgErr`.
     * @private
     * @override
     */
    _initList: function(){
        
        if(Array.isArray(this.options.allValues) && this.options.allValues.length>0){
            // If folder data is already provided in options, initialize the tree view
            this._showAsDialog(); // Ensure dialog is shown (if applicable, handled by parent)
            this._initTreeView( this.options.allValues ); // Initialize tree (handled by parent)
            
        }else{
            // Otherwise, fetch folder data from the server
            let that = this;                            
            let opts = {}; // Options for HAPI call
            if(this.options.root_dir){
                opts.root_dir = this.options.root_dir;
            }
       
            window.hWin.HAPI4.SystemMgr.get_sysfolders(opts, 
                function(response){ // Async callback for HAPI request
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.options.allValues = response.data; // Store fetched data
                        if(Array.isArray(that.options.allValues) && that.options.allValues.length>0){
                            that._initList(); // Re-call _initList to now use the fetched data
                        }else{
                            // No folders found, show empty message
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR(that.options.emptyMessage));                
                        }
                    }else{
                        // HAPI request failed, show error
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        }
    }
});
