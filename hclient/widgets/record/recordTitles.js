/**
* @file recordTitles.js
* @brief Rebuilds record titles for a scope of records.
* @fileOverview This file defines the `recordTitles` widget. Its purpose is to initiate a server-side
* process to rebuild the titles of records within a selected scope. This is typically used when title
* generation rules have changed or to ensure consistency.
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @widget heurist.recordTitles
 * @extends $.heurist.recordAction
 * @description jQuery widget to trigger the rebuilding of record titles.
 * This widget provides a simple interface to start a long operation
 * on the server (`longOperationInit.php?type=titles`) that rebuilds
 * record titles, likely based on predefined patterns or constituent fields.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=300] - The height of the dialog.
 * @param {number} [options.width=540] - The width of the dialog.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.init_scope='selected'] - Initial scope for record selection (though the action applies globally or to all types based on server logic).
 * @param {string} [options.title='Rebuild Record Titles'] - Title for the dialog.
 */
$.widget( "heurist.recordTitles", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordTitles
     * @type {object}
     * @property {number} [height=300] - Dialog height.
     * @property {number} [width=540] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [init_scope='selected'] - Initial record scope.
     * @property {string} [title='Rebuild Record Titles'] - Dialog title.
     */
    options: {
    
        height: 300,
        width:  540,
        modal:  true,
        init_scope: 'selected',
        title:  'Rebuild Record Titles'
    },

    /**
     * @function _initControls
     * @memberof heurist.recordTitles
     * @private
     * @description Initializes controls. Calls the parent widget's `_initControls` method.
     * (No specific additional initializations in this widget).
     * @returns {boolean|undefined} Value returned by parent's `_initControls`.
     */
    _initControls:function(){
        
        return this._super();
    },
    
    /**
     * @function _getActionButtons
     * @memberof heurist.recordTitles
     * @private
     * @description Gets action buttons for the dialog, setting the main action button text to 'Proceed'.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Proceed');
        return res;
    },    
    
    /**
     * @function doAction
     * @memberof heurist.recordTitles
     * @private
     * @description Performs the action of initiating the record title rebuilding process.
     * It constructs a URL to the `longOperationInit.php` admin script with `type=titles`
     * and opens this URL in a new dialog, effectively handing off the operation to that script.
     * The `selectRecordScope` value is checked but not directly passed to the URL in the current implementation.
     */
    doAction: function(){

        let scope_val = this.selectRecordScope.val();
        if(scope_val==''){
           return;  
        }   

        let base_url = `${window.hWin.HAPI4.baseURL}admin/verification/longOperationInit.php?type=titles&db=${window.hWin.HAPI4.database}`;
        //const rectypes = !all_rectypes ? `&recTypeIDs=${selected_rectypes.join(',')}` : '';

        window.hWin.HEURIST4.msg.showDialog( base_url, {} );    
        
    },
  
});
