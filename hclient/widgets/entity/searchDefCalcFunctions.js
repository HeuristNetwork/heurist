/**
* @file searchDefCalcFunctions.js
* @brief Provides a search interface for Defined Calculated Functions.
* @fileOverview This widget is responsible for rendering the search controls and results list for Defined Calculated Functions.
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
 * @widget heurist.searchDefCalcFunctions
 * @brief Search widget for Defined Calculated Functions.
 * @extends $.heurist.searchEntity
 * @description This widget provides a search interface specifically for finding
 * Defined Calculated Functions. It typically includes a text input for searching by name
 * and an "Add New Formula" button.
 * It inherits its options from `$.heurist.searchEntity`.
 */
$.widget( "heurist.searchDefCalcFunctions", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the search widget.
     * @override
     * @memberof heurist.searchDefCalcFunctions
     * Calls the parent `_initControls` and then sets up the "Add New Formula" button,
     * including its label, icon, and click event handler which triggers an "onadd" event.
     * Finally, it calls `startSearch` to perform an initial search.
     */
    _initControls: function() {
        this._super();
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_add_record
                    .button({label: window.hWin.HR('Add New Formula'), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
        this._on( this.btn_add_record, {
                        click: function(){
                                this._trigger( "onadd" );    
                        }} );
        
        
        this.startSearch();            
    },  
    
    //
    /**
     * @brief Executes a search for Defined Calculated Functions.
     * @override
     * @memberof heurist.searchDefCalcFunctions
     * Constructs a search request object based on the value in the main search input field (`cfn_Name`).
     * If the search input is empty, it triggers an "onresult" event with an empty recordset.
     * Otherwise, it sets `details` to 'list', stores the request in `_search_request`,
     * and calls the parent's `_super()` (which is `searchEntity.startSearch`) to perform the search.
     */
    startSearch: function(){
        
            let request = {}
        
            request['cfn_Name'] = this.input_search.val();    
            
            if($.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new HRecordSet()} );
            }else{
                request['details']    = 'list';
                this._search_request = request;
                this._super();                
            }  
                     
    },

});
