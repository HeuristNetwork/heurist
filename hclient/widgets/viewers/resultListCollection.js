/**
 * @file resultListCollection.js
 * @brief Manages a collection of records, typically interacting with a result list, to perform actions like adding, clearing, or creating a map from the collection.
 * @fileOverview
 * This file defines the `heurist.resultListCollection` jQuery UI widget. This widget provides
 * functionality to manage a temporary collection of records. Users can add records from a
 * selection (often from an associated `resultList`) to this collection, clear the collection,
 * and perform actions on the collected records, such as creating a map or saving the collection
 * as a new search/filter. It also listens to global events for record selection and collection updates
 * to synchronize its state. The widget displays information about the collection size and provides
 * UI controls (buttons) for various collection-related actions. It can also render a small preview
 * of the collected items using an internal `resultList` instance.
 *
 * @project     Heurist academic knowledge management system
 * @package hclient\widgets\viewers
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

/**
 * @widget heurist.resultListCollection
 * @description A widget for managing a collection of records.
 * It allows adding records from a selection (typically from an associated {@link heurist.resultList}),
 * clearing the collection, and performing actions such as creating a map or saving the collection.
 * The widget displays the current collection size and provides UI controls for these actions.
 *
 * @example
 * $('#myCollectionManager').resultListCollection({
 *     resultList: $('#myResultList'), // Reference to the main result list
 *     action_Label: 'Generate Report',
 *     action_mode: 'customAction',
 *     instructionText: 'Select records and add to collection for report.'
 * });
 */
$.widget( "heurist.resultListCollection", {

    /**
     * @typedef {object} heurist.resultListCollection.options
     * @description Options for configuring the resultListCollection widget.
     * @property {Array<number>|null} [rectype_set=null]
     *  An array of allowed record type IDs that can be added to the collection.
     *  If null, records of any type can be added.
     * @property {jQuery|null} [resultList=null]
     *  A jQuery object representing the `heurist.resultList` widget instance from which
     *  selections will be sourced. This is used to determine the search realm.
     * @property {string|null} [search_realm=null]
     *  The search realm associated with this collection. If a `resultList` is provided,
     *  this option is typically derived from the `resultList`'s `search_realm`.
     * @property {string} [action_Label='Create Map']
     *  The label for the main action button (e.g., "Create Map", "Generate Report").
     * @property {Function|null} [action_Function=null]
     *  A callback function to execute when the main action button is clicked and `action_mode`
     *  is not 'map' or 'filter'. This function would typically handle the collected records.
     *  (Note: The current implementation primarily uses `action_mode`).
     * @property {string} [action_mode='map']
     *  Defines the behavior of the main action button.
     *  - 'map': Triggers the `createMapSpace` method to generate a map from the collection.
     *  - 'filter': (Implied) Triggers saving the collection, potentially as a filter/search.
     *  - Other values might be used by a custom `action_Function`.
     * @property {string} [instructionText='']
     *  Instructional text displayed within the widget, guiding the user on how to use the collection.
     * @property {string} [target_db='']
     *  The target database identifier, used specifically when `action_mode` is 'map',
     *  to specify where the map space should be created or previewed.
     */
    options: {
        rectype_set:null,  //array of allowed record types
        resultList: null,  //reference to source result list
        search_realm: null,
        action_Label: 'Create Map',
        action_Function: null,
        
        action_mode: 'map', //or filter
        instructionText: '',
        target_db: ''
    },

    /**
     * @property {Array<object>|null} _selection
     * @private
     * @description Stores the current set of selected records (full record objects, not just IDs)
     * that are candidates to be added to the collection. This is typically updated based on
     * selections in an associated resultList or global selection events.
     */
    _selection: null,     //current set of selected records (not just ids)
    /**
     * @property {Array<number>|null} _collection
     * @private
     * @description Stores an array of record IDs that form the current collection.
     */
    _collection: null,

    /**
     * @function _create
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Initializes the widget. Sets up the UI elements including information labels,
     * action buttons (Add, Clear, Action), and a mini result list for displaying collected items.
     * Binds event listeners for global record selection and collection updates.
     */
    _create: function() {

        let that = this;

        this.element
        // prevent double click to select text
        .disableSelection();
        
        //padding:4px 2px 4px 14px
        this.labelCollectionInfo = $('<div style="display:inline-block;vertical-align:bottom;min-height:21px;font-weight:bold;padding-right:30px;font-size:13px">')
                .appendTo(this.element);
        
        //create set of buttons
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu')
                .css({display:'inline-block','font-style':'italic','font-size':'0.8em'})
                .appendTo($('<div style="display:inline-block;vertical-align:bottom">').appendTo(this.element));

        this._initBtn('Add');
       
        this._initBtn('Clear');
       
        
        this._initBtn('Action');    
        if(this.options.action_mode=='map') this['btn_Action'].find('a').css({'font-weight':'bold'});
        
        this.divMainMenuItems.menu();
        
        
        if(this.options.resultList){ //has the same realm as parent recultList
            this.options.search_realm = this.options.resultList.resultList('option', 'search_realm');
        } 
        
        this.labelInstruction = $('<div>').text(this.options.instructionText)
                            .addClass('heurist-helper2').css({padding:'4px'}).appendTo(this.element); //float:'right',
        //-----------------------     listener of global events
        let sevents = window.hWin.HAPI4.Event.ON_REC_SELECT+' '
                        +window.hWin.HAPI4.Event.ON_REC_COLLECT;

        $(window.hWin.document).on(sevents, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                    if(data.reset){
                        that._selection = null;
                    }else{
                        that._selection = window.hWin.HAPI4.getSelection(data.selection, false);
                    }
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                that.collectionRender( data.collection );
            }
        });
        
        
        // small result list to display collected records
        this.recordList = $('<div>')
                .css({'font-size':'9px',position:'relative',height:'80px'})
                .hide().appendTo(this.element);
        
        this.recordList.resultList( {
                       eventbased: false, //do not listent global events
                       select_mode: 'none',
                       show_toolbar: false,
                       view_mode: 'list',
                       renderer:function( recordset, record ){
                           let recIcon = window.hWin.HAPI4.iconBaseURL +
                                        recordset.fld(record, 'rec_RecTypeID');
                           let recTitle = recordset.fld(record, 'rec_Title'); 
                           let recTitle_strip2 = window.hWin.HEURIST4.util.stripTags(recTitle);
                           
                           return '<div class="recordDiv collected_perm" style="height:18px;padding:0px 2px;"><div class="recordIcons">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/></div>'
        + '<div class="recordTitle" style="left:22px;top:4px">' + recTitle_strip2 + '</div>'
        + '</div>';
                           
                       }
            
        } );
        

        this._refresh();

        //get collection
        window.hWin.HEURIST4.collection.collectionUpdate();
    }, //end _create

    /**
     * @function _isSameRealm
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Checks if the widget's current search realm matches the realm from incoming event data.
     * This is used to ensure the widget only responds to events relevant to its configured context.
     * An empty or null realm on either side is considered a match for broader compatibility.
     * @param {object} data Event data, expected to have a `search_realm` property.
     * @returns {boolean} True if the realms are considered the same, false otherwise.
     */
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    
    /**
     * @function _init
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Post-creation initialization. Currently empty, but can be used for tasks
     * that need to run after the widget is created and DOM elements are in place.
     */
    _init: function() {

    },

    /**
     * @function _setOptions
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Called when options are set on the widget, including during initialization.
     * It calls the parent widget's `_setOptions` method.
     * @param {object} options An object containing option key-value pairs to set.
     */
    _setOptions: function( options ) { // Note: Standard jQuery UI practice is to receive 'options' argument
        this._superApply( arguments ); // Pass all arguments to _superApply
    },

    /**
     * @function _refresh
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Refreshes the widget state. Currently, this method is a placeholder and does not
     * perform any specific actions. It could be used to update UI elements based on option changes
     * or other state modifications.
     */
    _refresh: function(){
    },
    
    /**
     * @function _destroy
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Cleans up the widget when it is destroyed. Unbinds global event listeners,
     * removes UI elements (buttons, labels, record list) created by the widget.
     */
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_REC_SELECT + '.' + this.widgetName); // Be specific with event namespacing
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_REC_COLLECT + '.' + this.widgetName);


        this.btn_Add.remove();
        // this.btn_Remove.remove(); // btn_Remove is not initialized in _create, potential error if called
        this.btn_Clear.remove();
        // this.btn_List.remove(); // btn_List is not initialized
        // this.btn_Save.remove(); // btn_Save is not initialized
        this.btn_Action.remove();
        this.divMainMenuItems.remove();
        this.labelCollectionInfo.remove();
        this.labelInstruction.remove();
        
        this.recordList.resultList('destroy').remove(); // Destroy inner widget and remove its element
        this.recordList.remove(); // Ensure div is removed
    },

    /**
     * @function _initBtn
     * @memberof heurist.resultListCollection
     * @instance
     * @private
     * @description Initializes a menu button with the given name. Creates a list item (`<li>`)
     * with a link (`<a>`), sets its text (internationalized), and appends it to the main menu.
     * Binds a click handler to the button that calls `menuActionHandler`.
     * The button element is stored as a property on the widget (e.g., `this.btn_Add`).
     * @param {string} name The action name for the button (e.g., "Add", "Clear", "Action").
     * This name is used for the `data-action` attribute and to name the widget property.
     */
    _initBtn: function(name){
        
        let label = (name=='Action')?this.options.action_Label:name;

        let link = $('<a>',{
            text: window.hWin.HR(label), href:'#'
        });
        
        this['btn_'+name] = $('<li data-action="'+name+'">')
            .css({background: 'lightgray','margin-right':'10px'})
            .append(link)
            .appendTo( this.divMainMenuItems );

        
        this._on( this['btn_'+name], {
                click : this.menuActionHandler
            });
        
    },


    /**
     * @function menuActionHandler
     * @memberof heurist.resultListCollection
     * @instance
     * @description Handles click events from the menu buttons. Determines the action based on
     * the `data-action` attribute of the clicked button and calls the appropriate
     * `window.hWin.HEURIST4.collection` method or internal widget method.
     * Actions include:
     * - "Add": Adds current `_selection` to the collection.
     * - "Remove": (Currently not fully implemented as a button) Removes `_selection` from collection.
     * - "Clear": Clears the entire collection.
     * - "List": (Currently not fully implemented as a button) Shows the collection.
     * - "Save": (Currently not fully implemented as a button) Saves the collection.
     * - "Action": Performs the main widget action, either `createMapSpace` (if `action_mode` is 'map')
     *   or saves the collection (if `action_mode` is 'filter').
     * @param {jQuery.Event} event The click event object.
     */
    menuActionHandler: function(event){

        let that = this; // 'that' is not used, consider replacing with 'this' or removing.
        let ele = $(event.target);
        if(!ele.is('li')){
            ele = ele.parents('li');
        }
        
        let action = ele.attr('data-action');

        if(action == "Add"){

            window.hWin.HEURIST4.collection.collectionAdd(null, this._selection);
            this.selectNone();

        }else if(action == "Remove"){ // Note: Button for "Remove" is not initialized by default in _create

            window.hWin.HEURIST4.collection.collectionDel(null, this._selection);
            this.selectNone();

        }else if(action == "Clear"){

            window.hWin.HEURIST4.collection.collectionClear();

        }else if(action == "List"){ // Note: Button for "List" is not initialized by default

            window.hWin.HEURIST4.collection.collectionShow();

        }else if(action == "Save"){ // Note: Button for "Save" is not initialized by default

            window.hWin.HEURIST4.collection.collectionSave();

        }else if(action == "Action"){

            if(this.options.action_mode=='map'){
                
                this.createMapSpace();
            }else{ // Assumed 'filter' mode or other custom action if action_Function was set
                window.hWin.HEURIST4.collection.collectionSave();
            }
            
        }

    },
    
    /**
     * @function selectNone
     * @memberof heurist.resultListCollection
     * @instance
     * @description Clears the internal `_selection` property and triggers a global
     * `ON_REC_SELECT` event with a null selection, indicating that no items are currently
     * selected within this widget's context.
     */
    selectNone: function(){
        this._selection = null;
        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:null, source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },
    

    //-------------------------------------- COLLECTIONS -------------------------------

    /**
     * @function createMapSpace
     * @memberof heurist.resultListCollection
     * @instance
     * @description Initiates the creation or preview of a map space using the current collection.
     * It checks if a `target_db` is defined in options and if the collection is not empty.
     * If valid, it constructs a URL to the map previewer and shows it in a dialog.
     * Displays error or informational messages if prerequisites are not met.
     */
    createMapSpace: function(){
        
        if(!this.options.target_db){
            
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Wrong configuration. Target database for mapspace is not defined',
                error_title: 'Missing target database'
            });

        }else
        if(!window.hWin.HEURIST4.util.isempty(this._collection)){
            
            //create virtual record set for temporal mapspace
            
            //open
            let url = window.hWin.HAPI4.baseURL 
            +'viewers/map/mapPreview.php?db='+window.hWin.HAPI4.database
            +'&ids='+this._collection.join(",")
            +'&target_db='+this.options.target_db;

            let init_params = {'ids': this._collection.join(","), target_db:this.options.target_db};

            window.hWin.HEURIST4.msg.showDialog(url, {height:'600', width:'1000',
                window: window.hWin,  //opener is top most heurist window
                dialogid: 'map_preview_dialog',
                params: init_params,
                title: window.hWin.HR('Preview mapspace'),
                class:'ui-heurist-bg-light'
                //callback: function(location){
                //    if( !window.hWin.HEURIST4.util.isempty(location) ){
                //        
                //    }
                //}
            } );
        
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Please add records to the collection for map');
        }
    },

    /**
     * @function collectionRender
     * @memberof heurist.resultListCollection
     * @instance
     * @description Updates the widget's display based on the provided collection data.
     * Sets the internal `_collection`, updates the information label with the collection size,
     * and refreshes the internal mini result list to display the collected items.
     * It also handles showing/hiding the mini list and adjusting layout (with hardcoded values).
     * @param {Array<number>} _collection An array of record IDs representing the current collection.
     */
    collectionRender: function(_collection) {
        
        this._collection = _collection;

        this.labelCollectionInfo.html( window.hWin.HR('Collected: ') + 
                (_collection && _collection.length>0?_collection.length:'0') + ' datasets');
                
        if(_collection && _collection.length>0){
            this.recordList.resultList('updateResultSet', new HRecordSet(_collection)); // Assumes HRecordSet is globally available
            this.recordList.show();
            $('#mywidget_3249').css('top',175); //hardcode for tlcmap
            
        }else{
            this.recordList.hide();
            
            $('#mywidget_3249').css('top',85); //hardcode for tlcmap
        }
                
    },
    
    /**
     * @function warningOnExit
     * @memberof heurist.resultListCollection
     * @instance
     * @description Displays a confirmation dialog if there are items in the collection when an
     * action that might lead to losing the collection (e.g., navigating away) is triggered.
     * The dialog offers to save the collection as a map or continue with the original action.
     * If the collection is empty, it directly calls the `callback_continue`.
     * @param {Function} callback_continue The function to call if the user chooses to continue
     * without saving or if the collection is empty.
     */
    warningOnExit: function( callback_continue ){

        let col = this._collection; 
        if( col && col.length>0 ){
            
                let that = this, $dlg, buttons = {};
                buttons['Save Map'] = function(){ 
                    that.createMapSpace();
                    $dlg.dialog('close'); 
                }; 
                buttons['Continue'] = function(){ 
                    callback_continue();
                    $dlg.dialog('close'); 
                };
            
            
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<h4>Records have been collected</h4>'
                +'<p>Do you want to save these as a map to appear in My Maps?</p>'
                +'<p>(you don\'t have to save a map now, the collection is remembered in any case for later use)</p>',
                buttons,
                {title:'Confirm'});

        }else{
            callback_continue();
        }

    }
    

});
