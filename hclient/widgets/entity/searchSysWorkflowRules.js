/**
 * @file        searchSysWorkflowRules.js
 * @brief       Provides a search interface for System Workflow Rules.
 * @fileOverview This widget handles the search functionality for System Workflow Rules, primarily allowing filtering by Record Type.
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
 * @widget heurist.searchSysWorkflowRules
 * @brief Search widget for System Workflow Rules.
 * @augments $.heurist.searchEntity
 * @description This widget provides a user interface for searching and managing System Workflow Rules.
 *              The primary filter is by Record Type. It also provides controls for adding rules/stages
 *              and editing the associated workflow vocabulary.
 *
 * @property {?number} rty_ID The Record Type ID to initially filter by or set programmatically.
 *           This is primarily controlled via the `_setOption` method.
 *
 * @listens heurist.searchSysWorkflowRules#onadd - Fired when the "Add set of Rules" or "Add Stage" button is clicked.
 * @listens heurist.searchSysWorkflowRules#onvocabedit - Fired when the "Edit Workflow Vocabulary" control is clicked.
 * @listens heurist.searchSysWorkflowRules#onfilter - Inherited from `$.heurist.searchEntity`, triggered by `startSearch`.
 */
$.widget( "heurist.searchSysWorkflowRules", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the System Workflow Rules search widget.
     * @override
     * @memberof heurist.searchSysWorkflowRules
     * @description Sets up the title, "Add" button, "Edit Workflow Vocabulary" link,
     *              and a Record Type selector. It also fetches all users if not already cached,
     *              as this might be needed by the workflow management context.
     *              Triggers an initial search.
     */
    _initControls: function() {
        this._super();
        
        let that = this;
        
        this.element.find('#inner_title').text( this.options.entity.entityTitlePlural );
        
        //this.btn_search_start.css('float','right');   
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_add_record
                    .button({label: window.hWin.HR('Add set of Rules'), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
        this._on( this.btn_add_record, {
                        click: function(){
                                this._trigger( "onadd" );    
                        }} );

        this._on( this.element.find('#edit_swf_vocab'), {
                        click: function(){
                            this._trigger('onvocabedit');
                        }
        });
        
        this.input_search_rectype = this.element.find('#input_search_rectype');
        let rty_selector = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.input_search_rectype.get(0), 
                {rectypeList:  null, 
                 showAllRectypes: true}); //topOptions:'select record type'
        this._on(rty_selector,  { change:this.startSearch });

        if(!window.hWin.HEURIST4.allUsersCache || !window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HEURIST4.allUsersCache)){

            //get all users
            let request = {a:'search', entity:'sysUsers', details:'fullname', 'sort:ugr_LastName': '1'};
            //Note: it searches for all users - including disabled
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    let recordset = new HRecordSet(response.data);
                    window.hWin.HEURIST4.allUsersCache = [];                    
                    recordset.each2(function(id,rec){
                        window.hWin.HEURIST4.allUsersCache.push({id: id, name: rec['ugr_FullName']});
                    });
                    
                    that.startSearch();
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        
        }else{
            this.startSearch();     
        }
    },  
    
    /**
     * @brief Initiates a search for workflow rules based on the selected Record Type ID.
     * @override
     * @memberof heurist.searchSysWorkflowRules
     * @description If a specific Record Type is selected, it constructs a request to filter
     *              workflow rules by `swf_RecTypeID` and sorts them by `swf_Order`.
     *              If no specific Record Type is selected (or 'any'), it attempts to default
     *              to the first available Record Type with existing rules or the first in the list,
     *              then calls `_setOption` to trigger a search for that Record Type.
     *              Triggers an "onfilter" event with the request.
     */
    startSearch: function(){
        
            let request = {};
        
            if(this.input_search_rectype.val() && this.input_search_rectype.val()!='any'){
                request['swf_RecTypeID'] = this.input_search_rectype.val();        
                request['sort:swf_Order'] = 1;
            }else{
                
                let recset = $Db.swf();
                let id;
                if(recset.length()>0){
                    id = recset.fld(recset.getFirstRecord(),'swf_RecTypeID');
                }else{
                    //get first
                    id = this.input_search_rectype.find('option[value!=0]:first').attr('value');
                }

                this._setOption('rty_ID', id);
                return;
            }
            
            this._trigger( "onfilter", null, request); 
            
    },
    
    /**
     * @brief Gets the currently selected Record Type ID from the dropdown.
     * @memberof heurist.searchSysWorkflowRules
     * @returns {?string} The selected Record Type ID as a string, or 'any', or null/undefined if nothing is selected.
     */
    getSelectedRty: function(){
        return this.input_search_rectype.val();
    },

    /**
     * @brief Sets the label of the "Add" button.
     * @memberof heurist.searchSysWorkflowRules
     * @param {boolean} is_empty True if there are no existing rules/stages for the current context,
     *                           which sets the button label to "Add set of Rules".
     *                           False otherwise, setting the label to "Add Stage".
     */
    setButton: function(is_empty){

        if(is_empty){
            this.btn_add_record.button({label:window.hWin.HR('Add set of Rules')});    
        }else{
            this.btn_add_record.button({label:window.hWin.HR('Add Stage')});
        }
        
    },

    /**
     * @brief Sets an option for the widget.
     * @override
     * @memberof heurist.searchSysWorkflowRules
     * @param {string} key The name of the option to set.
     * @param {*} value The value to set for the option.
     * @description Handles the 'rty_ID' option specifically by updating the
     *              Record Type selector dropdown and then triggering `startSearch`.
     *              Calls the parent widget's `_setOption` for other keys.
     */
    _setOption: function( key, value ) {
        this._super( key, value );
        if(key == 'rty_ID'){
            this.input_search_rectype.val(value);
            this.input_search_rectype.hSelect('refresh');
            this.startSearch();
        }
    },

    /**
     * @brief Refreshes a displayed list of Record Type names that have workflow rules assigned.
     * @memberof heurist.searchSysWorkflowRules
     * @description Iterates through the cached System Workflow Rules (`$Db.swf()`) and compiles
     *              a unique list of Record Type names associated with these rules.
     *              Updates the text of an element with ID `existing_swr` to display these names.
     */
    refreshRectypeList: function(){

        // List rectypes that have swf assigned
        let rectype_names = [];

        $Db.swf().each2(function(id, record){

            let rty_name = $Db.rty(record['swf_RecTypeID'], 'rty_Name');

            if(!rectype_names.includes(rty_name)){ 
                rectype_names.push(rty_name); 
            }
        });

        this.element.find('span#existing_swr').text(rectype_names.join(', '));
    }
});
