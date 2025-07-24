/**
* @file manageSysBugreport.js
* @brief Manages System Bug Report entities.
* @fileOverview Provides a UI for users to submit bug reports and for administrators to manage them. Includes fields for bug description, reproduction steps, severity, status, etc.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.6.5
*/



//
// there is no search, select mode for bug report - only add and send by email
//

/**
 * @widget heurist.manageSysBugreport
 * @brief Widget for managing System Bug Reports.
 * @augments $.heurist.manageEntity
 * @description This widget provides a specialized interface for submitting bug reports.
 * It operates in 'editonly' mode, meaning it directly presents a form for a new bug report.
 *
 * @property {string} title Overridden to 'Heurist feedback'. Defines the title for the widget's dialog.
 * @property {string} edit_mode Set to 'editonly', so the widget opens directly into the edit form for a new bug report.
 * @property {string} select_mode Set to 'manager'. Although typically for list management, in this context, combined with 'editonly', it means no list view is presented.
 * @property {string} layout_mode Set to 'editonly', further reinforcing that only the editing interface is shown.
 * @property {number} width Default width of the widget dialog, set to 900 pixels.
 * @property {number} height Default height of the widget dialog, set to 932 pixels.
 */
$.widget( "heurist.manageSysBugreport", $.heurist.manageEntity, {
   
    _entityName:'sysBugreport',
    
    //keep to refresh after modifications
    _keepRequest:null,

    _checkDescription: true, // check if bug description is over 20 characters long

    _program_area: null,

    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageSysBugreport
     * Sets default options for title, edit_mode, select_mode, layout_mode, width, and height
     * to tailor the widget for bug report submission.
     */
    _init: function() {
        
        this.options.title = 'Heurist feedback';
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 900;
        this.options.height = 932;

        this._super();
    },
    
    //  
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageSysBugreport
     * @returns {boolean} Returns false if the parent `_initControls` fails, otherwise true.
     * Sets the default palette class and then calls the parent's `_initControls`.
     * Since this widget is 'editonly', it immediately calls `addEditRecord(-1)` to present
     * a form for a new bug report.
     */
    _initControls: function() {

        this.options.default_palette_class = 'ui-heurist-admin';

        if(!this._super()){
            return false;
        }

        // always new report
        this.addEditRecord(-1);

        return true;
    },
    
    /**
     * @brief Customizes the buttons for the edit dialog.
     * @override
     * @memberof heurist.manageSysBugreport
     * @returns {Array<object>} An array of button definition objects.
     * It retrieves the default buttons from the parent widget and then modifies the "Save"
     * button's text to "Send to heurist development team".
     */
    _getEditDialogButtons: function(){
        let btns = this._super();
        
        for(let idx in btns){
            if(btns[idx].class.indexOf('btnRecSave')>=0){
                btns[idx].text = window.hWin.HR('Send to heurist development team');
                break;
            }
        }
        
        return btns;
    },

    /**
     * @brief Validates the form values before submission.
     * @override
     * @memberof heurist.manageSysBugreport
     * @returns {?object} The validated field values, or null if validation fails.
     * Retrieves values from the parent widget. It then checks if the bug description
     * (`bug_Description`) has a minimum word count (20 words) if `_checkDescription` is true.
     * If the description is too short, it shows a dialog prompting the user for more details
     * or to proceed as-is. It also processes uploaded images for the `bug_Image` field.
     */
    _getValidatedValues: function(){

        let that = this;
        let res = this._super();

        if(!res){
            return null;
        }

        // Check for a usable description (a min of 20 words) or the inclusion of steps to reproduce
        let desc = res['bug_Description'];
        if(this._checkDescription && desc.split(' ').length < 20){

            let $dlg;
            let msg = 'In order for bugs to be found and fixed as quickly as possible, the team requires as many details about the issue you are encountering.<br>'
                + 'Providing the steps that has lead you to this issue will also greatly speed up the initial stages of fixing this issue.<br><br>'
                + 'Otherwise, you can click \'Proceed as-is\' if you feel that there are no more details you can provided about this issue.';

            let btns = {};
            btns[window.hWin.HR('Proceed as-is')] = () => {

                that._checkDescription = false;
                $dlg.dialog('close');

                that._saveEditAndClose();
            }
            btns[window.hWin.HR('Close')] = () => {
                $dlg.dialog('close');
            };

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'More information recommended'}, {default_palette_class: 'ui-heurist-admin'});

            return null;
        }

        res['bug_Image'] = [];
        let $img_div = this._editing.getFieldByName('bug_Image');
        $img_div.find('img').each((idx, img) => {
            let matches = img.src.match(/~\d{10}(?:%20%28\d+%29)?\.(?:png|gif|jpg)/);
            if(matches?.length == 1){
                res['bug_Image'].push(matches[0]);
            }
        });

        return res;
    },
    
//---------------------------------------------------------------------------------- 
    /**
     * @brief Handles the event after a bug report is successfully saved (sent).
     * @override
     * @memberof heurist.manageSysBugreport
     * @param {string} message The success message from the server (usually confirmation).
     * Displays a confirmation dialog titled "Bug report sent" and then closes the main widget dialog.
     */
    _afterSaveEventHandler: function(message){
        window.hWin.HEURIST4.msg.showMsgDlg(message, null, {title: 'Bug report sent'}, {default_palette_class: 'ui-heurist-admin'});
        this.closeDialog(true); //force to avoid warning
    },
    
    /**
     * @brief Performs actions after the edit form is initialized.
     * @override
     * @memberof heurist.manageSysBugreport
     * Calls the parent's `_afterInitEditForm`. Then, it sets up the file uploader's paste zone
     * to cover the entire dialog for easy screenshot pasting.
     * It pre-fills the `bug_URL` field with the current page URL.
     * Adds introductory help text and formats the `bug_Type` and `bug_Image` fields.
     * Finally, it calls `_setupProgramArea` to initialize the program area dropdown.
     */
    _afterInitEditForm: function(){

        this._super();

        //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
        let ele = this._as_dialog.find('input[type=file]');
        if(ele.length>0){
            ele.fileupload('option', 'pasteZone', this._as_dialog);
        }

        // Add default values to url
        this._editing.setFieldValueByName('bug_URL', location.href, false);

		// Add spacing between fields, and give textarea's larger height
        let eles = this._editing.getAllFields();
        let help = '';
        for(const ele of eles){ // ignore last element (image field)

            let $ele = $(ele);

            if($ele.find('textarea,input.text,.fileupload').length != 0){
                $ele.css({'padding-top': '10px', 'display': 'block'});
            }else if($ele.attr('data-dtid') == 'bug_Type'){
                $ele.find('.header').hide();

            }
            
            if(help === ''){

                let padding = `padding: 0px 15px 20px;`;
                help = 'We value your feedback and do our best to fix bugs rapidly and to incorporate your suggestions into our development process.<br>'
                     + 'Please don\'t hesitate to let us know about anything which annoys you or which you feel could be improved.<br><br>'
                     + 'We pop this form up monthly to encourage your feedback. It is accessible at any time through Help > Feedback / bug report.<br>'
                     + 'You can also paste an image which will be added to the screenshots.';

                // add extra info at top
				$('<div>', {
                    html: help,
                    style: `${padding} display: block;font-size: 12px;`
                }).insertBefore($ele);
            }
        }

        ele = this._editing.getFieldByName('bug_Image');
        let padding = `padding: 10px 15px 20px;`;
        $('<div>', {
            html: 'It is very helpful if you can provide a screen capture for annoyances and bug reports,<br>'
                + 'or an annotated screen capture or drawing for feature requests.',
            style: `${padding} display: block;font-size: 12px;`
        }).insertBefore($(ele));

        this._formatBugTypeField();
        this._formatBugImageField();

        this._setupProgramArea();
    },

    /**
     * @brief Sets up the 'Program Area' (bug_Location) dropdown field.
     * @memberof heurist.manageSysBugreport
     * This field allows users to specify which part of Heurist the bug relates to.
     * If the program area terms (`this._program_area`) haven't been loaded yet,
     * it fetches them from the reference Heurist database (specified in sysinfo).
     * Once terms are available, it converts the standard text input for `bug_Location`
     * into an hSelect dropdown populated with these terms, including hierarchical grouping.
     */
    _setupProgramArea: function(){

        let ele = this._editing.getFieldByName('bug_Location');
        if(!ele || ele.length == 0){
            return;
        }

        let $input = ele.find('input');

        if(this._program_area === null){

            let request = {
                terms: '6988',
                mode: 2,
                remote: `${window.hWin.HAPI4.sysinfo.referenceServerURL}?db=${window.hWin.HAPI4.sysinfo.referenceServerBugreportDatabase}`
            };

            window.hWin.HAPI4.SystemMgr.get_defs(request, (response) => {

                if(response.status != window.hWin.ResponseStatus.OK){

                    window.hWin.HEURIST4.msg.showMsgErr(response);

                    this._program_area = false;

                    $input.val('7105');
                    ele.hide();

                    return;
                }

                this._bugDBTerms = response.data.terms;
                this._program_area = [{key: '', title: 'Please select...'}];

                this._processProgramArea(6988);
                this._setupProgramArea();
            });

            return;
        }

        let $select = $('<select>').insertAfter($input);

        window.hWin.HEURIST4.ui.createSelector($select[0], this._program_area);
        window.hWin.HEURIST4.ui.initHSelect($select, false, null, {
            onSelectMenu: (e) => {
                $input.val($select.val()).trigger('change');
            }
        });
        $select.hSelect('option', { groupings: true, groupingsType: 'other' });

        $input.hide();
    },

    /**
     * @brief Recursively processes terms for the 'Program Area' dropdown.
     * @memberof heurist.manageSysBugreport
     * @param {number|string} parent_term_id The ID of the parent term whose children are to be processed.
     * @param {number} [depth=0] The current depth in the term hierarchy, used for indentation in the dropdown.
     * This function populates `this._program_area` with term objects suitable for `hSelect`.
     * It sorts terms by `trm_OrderInBranch` and then alphabetically.
     * For each term, it adds an object `{key: trm_ID, title: trm_Label, depth: depth}` to `this._program_area`.
     * If a term has children, it calls itself recursively for that term.
     */
    _processProgramArea: function(parent_term_id, depth = 0){

        let that = this;
        let trm_Label_idx = this._bugDBTerms.fieldNamesToIndex.trm_Label;
        let trm_Order_idx = this._bugDBTerms.fieldNamesToIndex.trm_OrderInBranch;

        function sortProgramArea(a, b){

            let a_name = that._bugDBTerms.termsByDomainLookup.enum[a][trm_Label_idx].toLocaleUpperCase();
            let b_name = that._bugDBTerms.termsByDomainLookup.enum[b][trm_Label_idx].toLocaleUpperCase();
            let a_order = parseInt(that._bugDBTerms.termsByDomainLookup.enum[a][trm_Order_idx], 10);
            let b_order = parseInt(that._bugDBTerms.termsByDomainLookup.enum[b][trm_Order_idx], 10);
    
            a_order = (!a_order || a_order < 1 || isNaN(a_order)) ? null : a_order;
            b_order = (!b_order || b_order < 1 || isNaN(b_order)) ? null : b_order;
    
            if(a_order == null && b_order == null){ // alphabetic
                return a_name.localeCompare(b_name);
            }else if(a_order == null || b_order == null){ // null is first
                return a_order == null;
            }else{ // branch order
                return (a_order - b_order);
            }
        }

        let terms = this._bugDBTerms.trm_Links[parent_term_id];

        terms.sort(sortProgramArea);

        for(let trm_ID of terms){

            let term = this._bugDBTerms.termsByDomainLookup.enum[trm_ID];

            this._program_area.push({key: trm_ID, title: term[trm_Label_idx], depth: depth});

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(this._bugDBTerms.trm_Links[trm_ID])){
                this._processProgramArea(trm_ID, depth + 1);
            }
        }
    },

    /**
     * @brief Formats the 'Bug Type' (bug_Type) enum field for better layout.
     * @memberof heurist.manageSysBugreport
     * This method adjusts the styling of the radio buttons (or similar enum inputs)
     * for the `bug_Type` field. It arranges them, typically into multiple columns,
     * by setting specific widths for the label elements. It also bolds the text
     * and hides the default field header.
     */
    _formatBugTypeField: function(){

        // Format widths
        let ele = this._editing.getFieldByName('bug_Type');
        $.each(ele.find('label.enum_input'), (idx, label) => {

            label = $(label);
            let width = idx % 3 === 0 ? '17' : '25';
            width = idx % 3 === 2 ? '23' : width;

            label.css({
                width: `${width}em`,
                'min-width': `${width}em`,
                'max-width': '',
                'margin-right': ''
            });
        });

        // Bold values
        ele.find('.input-div').css('font-weight', 'bold');

        // Hide field name
        ele.find('.header').hide();
    },

    /**
     * @brief Formats the 'Screenshot' (bug_Image) file upload field.
     * @memberof heurist.manageSysBugreport
     * Adjusts styling for the file input area, ensuring the input div is displayed
     * as inline-block and removes the "move" button typically associated with multi-file uploads.
     */
    _formatBugImageField: function(){

        let ele = this._editing.getFieldByName('bug_Image');

        if(!ele){
            return;
        }

        ele.find('.input-div').css({
            display: 'inline-block',
            'padding-right': '30px'
        });

        ele.find('.btn_input_move').remove();
    },

    /**
     * @brief Handles changes to form elements.
     * @override
     * @memberof heurist.manageSysBugreport
     * @param {object} [changed_element] The element that triggered the change, if available.
     * Calls the parent `onEditFormChange`. Additionally, if the `bug_Type` field changed (enum buttons),
     * it re-applies formatting via `_formatBugTypeField`. If a file field changed, it calls `_formatBugImageField`.
     */
    onEditFormChange: function(changed_element){

        this._super(changed_element);

        if(changed_element?.enum_buttons !== null){
            this._formatBugTypeField();
        }else if(changed_element?.detailType === 'file'){
            this._formatBugImageField();
        }
    },

    /**
     * @brief Handles the event when a new input is added to a multi-value field.
     * @override
     * @memberof heurist.manageSysBugreport
     * @param {object} [added_element] Details about the added input element.
     * If a new file input is added (e.g., for another screenshot), it calls
     * `_formatBugImageField` to ensure consistent styling.
     */
    onEditFormNewInput: function(added_element){

        if(added_element?.detailType === 'file'){
            this._formatBugImageField();
        }
    }
    
});
