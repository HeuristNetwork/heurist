/**
* @file rectypeTitleMask.js
* @brief Provides a popup UI for managing Record Type title masks.
* @fileOverview This widget allows users to define a title mask for a Record Type, which controls how record titles are automatically generated or displayed based on field values.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



/**
 * @widget heurist.rectypeTitleMask
 * @brief Popup widget for managing Record Type title masks.
 * @augments $.heurist.recordAction
 * @description This widget provides a dialog interface for users to construct and test
 * a title mask for a specific record type. The title mask defines how record titles
 * are automatically generated based on the values of selected fields.
 *
 * @property {number} [height=700] The default height of the popup dialog.
 * @property {number} [width=800] The default width of the popup dialog.
 * @property {boolean} [modal=true] Whether the dialog is modal.
 * @property {string} [title='Record Type Title Mask Edit'] The default title displayed in the dialog's title bar.
 * @property {string} [default_palette_class='ui-heurist-design'] The default CSS class for theming the dialog.
 * @property {string} [path='widgets/entity/popups/'] The path to the widget's HTML template file.
 * @property {number} rty_ID The ID of the Record Type for which the title mask is being edited. Defaults to 0.
 * @property {string} [rty_TitleMask=''] The initial title mask string to be edited.
 * @property {string} [htmlContent='rectypeTitleMask.html'] The name of the HTML file used for the widget's UI.
 */
$.widget( "heurist.rectypeTitleMask", $.heurist.recordAction, {

    // default options
    options: {

        height: 800,
        width:  875,
        modal:  true,
        title:  'Record Type Title Mask Edit',
        default_palette_class: 'ui-heurist-design', 
        
        path: 'widgets/entity/popups/', //location of this widget
        rty_ID: 0, 
        rty_TitleMask: '',
        
        htmlContent: 'rectypeTitleMask.html'
    },

    action_in_progress: false,
    
    /**
     * @brief Destroys the widget.
     * @override
     * @memberof heurist.rectypeTitleMask
     * Calls the parent `_destroy` method. Also specifically destroys the Fancytree instance
     * used for displaying the record type structure if it exists.
     */
    _destroy: function() {
        this._super(); 
        
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
    },

    //
    /**
     * @brief Initializes the controls within the popup dialog.
     * @override
     * @memberof heurist.rectypeTitleMask
     * @returns {boolean} True if initialization is successful, undefined otherwise (e.g., if rty_ID is missing).
     * Validates that `options.rty_ID` is provided.
     * Sets the dialog title.
     * Initializes the Fancytree for record type field selection (`_loadRecordTypeTreeView`).
     * Sets up "Insert Field" and "Test Mask" buttons with their click handlers.
     * Initializes the "Select All" checkbox for the field tree.
     * Populates the title mask input with `options.rty_TitleMask`.
     * Loads a list of sample records for testing the mask.
     * Calls `popupDialog` to display the widget.
     * Applies competency level styling.
     */
    _initControls: function() {
        
        let that = this;
        
        if(!(this.options.rty_ID>0)){
            window.hWin.HEURIST4.msg.showMsgDlg('Record type ID is not defined');
            return;
        }
        
        // Change dialog title
        this.element.dialog('option', 'title', `Edit title mask for ${$Db.rty(this.options.rty_ID, 'rty_Name')}`);

        //init tree
        this._loadRecordTypeTreeView();
        
        //init buttons
        let btn = this.element.find('#btnInsertField').button();
        this._on(btn, {click: this._doInsert});

        btn = this.element.find('#btnTestMask').button();
        this._on(btn, {click: this._doTest});

        this._on(this.element.find('#selectAll'), {click: 
            function(e){

                let treediv = that.element.find('.rtt-tree');

                let check_status = $(e.target).is(":checked");

                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    let tree = $.ui.fancytree.getTree(treediv);
                    tree.visit(function(node){ 

                        if(!node.hasChildren() && node.type != "relmarker" && node.type != "resource" 
                            && (node.getLevel()==1 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                        ){    
                            node.setSelected(check_status);
                        }
                    });
                }
            }
        });

        this.element.find('#rty_TitleMask').val(this.options.rty_TitleMask);
        
        //load list of records for testing 
        let request = {q: 't:'+this.options.rty_ID, w: 'all', detail:'header', limit:100 };
         
        window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( recordset )
        {
            if(recordset!=null){
                
                // it returns several record of given record type to apply tests
                //fill list of records
                let sel = that.element.find('#listRecords')[0];
                //clear selection list
                while (sel.length>1){
                    sel.remove(1);
                }

                let recs = recordset.getRecords();
                for(let rec_ID in recs) 
                if(rec_ID>0){
                    window.hWin.HEURIST4.ui.addoption(sel, rec_ID, 
                        window.hWin.HEURIST4.util.stripTags(recordset.fld(recs[rec_ID], 'rec_Title')));
                }

                window.hWin.HEURIST4.ui.initHSelect($(sel), false);
                $(sel).hSelect('option', {searchable: true, searchType: 'std'});

                sel.selectedIndex = 0;
            }
        });
        
        
        this.popupDialog();
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), false );
        
        return true;
    },
    
    //    
    /**
     * @brief Gets the action buttons for the dialog.
     * @override
     * @memberof heurist.rectypeTitleMask
     * @returns {object[]} An array of button definition objects for the dialog.
     * Modifies the default "OK" button text to "Save Mask" and "Cancel" button text.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Save Mask');
        res[0].text = window.hWin.HR('Cancel');
        return res;
    },    
        
    //
    /**
     * @brief Main action performed when the primary dialog button ("Save Mask") is clicked.
     * @override
     * @memberof heurist.rectypeTitleMask
     * Initiates the save process by calling `_doSave_Step1_Verification`.
     */
    doAction: function(){
        this._doSave_Step1_Verification();
    },
    
    /**
     * @brief Inserts selected field codes into the title mask textarea.
     * @memberof heurist.rectypeTitleMask
     * @returns {boolean|undefined} False if no fields are selected, otherwise undefined.
     * Retrieves selected fields from the Fancytree.
     * If no fields are selected, shows a message and returns false.
     * Otherwise, constructs a string of field codes (e.g., `[field_code]\r\n`)
     * and inserts it at the current cursor position in the title mask textarea using `_insertAtCursor`.
     * Clears the tree selection after insertion.
     */
    _doInsert: function(){
        
        let textedit = this.element.find('#rty_TitleMask')[0],
        _text = '';

        
        let tree = $.ui.fancytree.getTree( this.element.find('.rtt-tree') );
        let fieldIds = tree.getSelectedNodes(false);
        let k, len = fieldIds.length;
        
        if(len<1){
            window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
                +'Please select at least one field in tree', 2000);
            return false;
        }
        
        
        for (k=0;k<len;k++){
            let node =  fieldIds[k];
            
            if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;

            _text = _text + '['+node.data.code+']\r\n'; //node.data.full_path
        }
        if(_text!=='')    {
            this._insertAtCursor(textedit, _text);
            
            //clear selection
            tree.visit(function(node){
                node.setSelected(false);
            });
        }
        
        
    },

    //
    /**
     * @brief Utility function to insert text at the current cursor position in a textarea or input field.
     * @memberof heurist.rectypeTitleMask
     * @param {HTMLTextAreaElement|HTMLInputElement} myField The DOM element of the text field.
     * @param {string} myValue The string value to insert.
     * @todo Consider moving this to a global UI utility library like `HEURIST4.ui`.
     */
    _insertAtCursor: function(myField, myValue) {
        //IE support
        if (document.selection) {
            myField.dispatchEvent(new Event('focus'));
            let sel = document.selection.createRange();
            sel.text = myValue;
        }
        //MOZILLA/NETSCAPE support
        else if (myField.selectionStart || myField.selectionStart == '0') {
            let startPos = myField.selectionStart;
            let endPos = myField.selectionEnd;
            myField.value = myField.value.substring(0, startPos)
            + myValue
            + myField.value.substring(endPos, myField.value.length);
        } else {
            myField.value += myValue;
        }
    },
    
    
    //
    /**
     * @brief Tests the current title mask against a selected sample record.
     * @memberof heurist.rectypeTitleMask
     * Retrieves the current title mask from the textarea.
     * Sends a request to the server (`rectype_titlemask.php` with `check:1`) to validate the mask syntax.
     * If syntax is valid and a sample record is selected from the dropdown:
     *   Sends another request to the server to generate the title for the selected record using the mask.
     *   Displays the generated title in the `#testResult` element.
     * Shows error messages if syntax is invalid or other issues occur.
     */
    _doTest: function(){
        
        let that = this;

        //verify text title mask    
        let mask = this.element.find('#rty_TitleMask').val().replace(/  +/g, ' '); // condense multiple spaces into one /[\t ]+/g
        this.element.find('#rty_TitleMask').val(mask);

        let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/rectype_titlemask.php";

        let request = {rty_id:this.options.rty_ID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                if(response.status != window.hWin.ResponseStatus.OK || response.message){

                    
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    
                }else{
                    
                    
                    
                    if(that.element.find('#listRecords > option').length>1){
                        
                        let sel = that.element.find("#listRecords")[0];
                        if (sel.selectedIndex>0){

                            let rec_id = sel.value;
                            
                            let request2 = {rty_id:that.options.rty_ID, rec_id:rec_id, mask:mask, 
                                     db:window.hWin.HAPI4.database}; //verify titlemask
                                     
                            window.hWin.HEURIST4.util.sendRequest(baseurl, request2, null,
                                function (response) {
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        that.element.find('#testResult').html(response.data);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                });
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash('Select a record from the pulldown to test your title mask');
                        }
                    }
                    
                }                                        
            }
        );
        
    },

    //
    /**
     * @brief First step in the save process: client-side verification of the title mask syntax.
     * @memberof heurist.rectypeTitleMask
     * Prevents concurrent save actions.
     * Retrieves the title mask, replaces multiple spaces with single spaces.
     * Sends a request to `rectype_titlemask.php` with `check:1` to validate the mask.
     * If valid, proceeds to `_doSave_Step2_SaveRectype`. Otherwise, shows an error.
     */
    _doSave_Step1_Verification: function()
    {
        if(this.action_in_progress) return;
        this.action_in_progress = true;

        let that = this;
        
        //verify text mask 
        let mask = this.element.find('#rty_TitleMask').val().replace(/  +/g, ' '); // condense multiple spaces into one
        this.element.find('#rty_TitleMask').val(mask);

        let baseurl = window.hWin.HAPI4.baseURL + 'hserv/controller/rectype_titlemask.php';

        let request = {rty_id:that.options.rty_ID, mask:mask, db: window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                
                if(response.status != window.hWin.ResponseStatus.OK || response.message){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    that.action_in_progress = false;
                }else{
                    that._doSave_Step2_SaveRectype();
                }                                        
            }
        );
        
    },
    
    //
    /**
     * @brief Second step in the save process: saves the validated title mask to the record type definition.
     * @memberof heurist.rectypeTitleMask
     * Retrieves the new title mask value.
     * If it's different from the original `options.rty_TitleMask`:
     *   Updates the local cache (`$Db.rty`) with the new mask.
     *   Sends a request to the server (entity `defRecTypes`, action `save`) to persist the change.
     *   On success, proceeds to `_updateTitleMask` to regenerate titles for existing records.
     *   Shows an error if the save fails.
     * If the mask hasn't changed, closes the dialog.
     */
    _doSave_Step2_SaveRectype: function(){
                    
            let newvalue = this.element.find('#rty_TitleMask').val();
            if(newvalue != this.options.rty_TitleMask){
                
                let that = this;

                window.hWin.HEURIST4.dbs.rty(this.options.rty_ID, 'rty_TitleMask', newvalue); //update in cache
                
                // NEW - @todo
                let fields = {rty_ID:this.options.rty_ID, rty_TitleMask:newvalue};
                
                let request = {
                    'a'          : 'save',
                    'entity'     : 'defRecTypes',
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields,
                    'isfull'     : false
                    };
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK ){
                            that._updateTitleMask();        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            that.action_in_progress = false;
                        }
                    });                
                
                /*
                var _defs = {};
                _defs[this.options.rty_ID] = [{common:[newvalue],dtFields:[]}];
                var oRectype = {rectype:{colNames:{common:['rty_TitleMask'],dtFields:[]},
                            defs:_defs}}; //{_rectypeID:[{common:[newvalue],dtFields:[]}]}
                
                var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php"; //saveRT
                
                var request = {method:'saveRT', db:window.hWin.HAPI4.database, data:oRectype, no_purify:1 }; //styep
                
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                    function (response) {
                        if(response.status == window.hWin.ResponseStatus.OK ){
                            that._updateTitleMask();        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            that.action_in_progress = false;
                        }
                    }                
                );
                */
                
            }else{
                this.action_in_progress = false;
                this._context_on_close = newvalue;
                this.closeDialog();
            }                    
            
    },
    
    /**
    * @brief Third step in the save process: initiates server-side regeneration of titles for existing records of this type.
    * @memberof heurist.manageSysDatabases
    * Opens a long operation dialog (`longOperationInit.php?type=titles`) that handles
    * updating the titles of all records belonging to the modified record type, using the new title mask.
    * Sets `_context_on_close` with the new mask value and closes the current dialog.
    */
    _updateTitleMask: function(){
        
        let that = this;
        
        //recalcTitlesSpecifiedRectypes.php
        let sURL = window.hWin.HAPI4.baseURL + 'admin/verification/longOperationInit.php?type=titles&db='
                                +window.hWin.HAPI4.database+"&recTypeIDs="+this.options.rty_ID;

        window.hWin.HEURIST4.msg.showDialog(sURL, {

                "close-on-blur": false,
                "no-resize": true,
                height: 400,
                width: 400,
                callback: function(context) {
                    that.action_in_progress = false;
                }
        });
        
        this._context_on_close = this.element.find('#rty_TitleMask').val();
        this.closeDialog();
    },
    
    
    //
    /**
     * @brief Loads and initializes the Fancytree view of the record type's structure.
     * @memberof heurist.manageSysDatabases
     * @param {?number} rtyID The Record Type ID (not directly used here, uses `this.options.rty_ID`).
     * Generates tree data using `HEURIST4.dbs.createRectypeStructureTree` for the current `options.rty_ID`.
     * Initializes the Fancytree in the `.rtt-tree` element with checkbox selection,
     * custom rendering for different node types (enum, separator), and lazy loading for related record structures.
     * Handles node selection, click, double-click, and keydown events for tree interaction.
     */
    _loadRecordTypeTreeView: function(rtyID){
        
        //generate treedata from rectype structure
        let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 3, this.options.rty_ID, ['all','parent_link'] );

        //load treeview
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }

        treediv.fancytree({
            //extensions: ["filter"],
            //            extensions: ["select"],
            checkbox: true,
            selectMode: 3,  // hierarchical multi-selection
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){

                    if(data.node.isExpanded()){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                    return false;
                }
            },
            renderNode: function(event, data){
                if(data.node.type == "enum") { // hide blue and expand arrows for terms
                    $(data.node.span.childNodes[0]).hide();
                    $(data.node.span.childNodes[1]).hide();
                }
                if(data.node.parent && (data.node.parent.type == 'resource' || data.node.parent.type == 'rectype')){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }else{

                    if(data.node.parent && data.node.parent.type == 'enum'){ // make term options inline and smaller
                        $(data.node.li).css('display', 'inline-block');
                        $(data.node.span.childNodes[0]).css('display', 'none');

                        if(data.node.key == 'term'){
                            $(data.node.parent.ul).css({'transform': 'scale(0.8)', 'padding': '0px', 'position': 'relative', 'left': '-12px'});
                        }
                    }
                }
                if(data.node.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }
            },
            lazyLoad: function(event, data){
                
                let node = data.node;
                let parentcode = node.data.code; 
                let rectypes = node.data.rt_ids;

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 3, 
                    rectypes, ['all'], parentcode );
                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                   
                    },500);
            },
            select: function(e, data) {
            },
            click: function(e, data){

                if(data.node.type == 'separator'){
                    return false;
                }

                let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                let setDefaults = !data.node.isExpanded();

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    
                    if(!isExpander){
                        data.node.setExpanded(!data.node.isExpanded());
                    }
                
                    if(setDefaults){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                }else if( data.node.lazy && !isExpander) {
                    data.node.setExpanded( true );
                }
            },
            dblclick: function(e, data) {
                if(data.node.type == 'separator'){
                    return false;
                }
                data.node.toggleSelected();
            },
            keydown: function(e, data) {
                if( e.which === 32 ) {
                    data.node.toggleSelected();
                    return false;
                }
            }
        });
    },
    
    //
    /**
     * @brief Converts title mask to/from a canonical (internal) representation. [Test purposes only]
     * @memberof heurist.manageSysDatabases
     * @param {number} mode If 2, converts current title mask to canonical and displays in `#rty_CanonincalMask`.
     *                      Otherwise, converts `#rty_CanonincalMask` to display format in `#rty_TitleMask`.
     * This method appears to be for testing or debugging the title mask parsing/generation logic.
     */
    _doCanonical: function(mode){

        let mask = (mode==2)?this.element.find('#rty_TitleMask').val()
                            :this.element.find('#rty_CanonincalMask').val()
        
        let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/rectype_titlemask.php";

        let request = {rty_id:this.options.rty_ID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        let that = this;
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                if(response.status != window.hWin.ResponseStatus.OK || response.message){

                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    
                }else{
                    if(mode==2){
                        that.element.find('#rty_CanonincalMask').val(response.data);
                    }else{
                        that.element.find('#rty_TitleMask').val(response.data);
                    }
                }                                        
            }
        );        
    }
    
    

});
